/*! ============================================================================
 *  QUIRKY IDE — CM6 COLOUR GUARD  (v24 "why is my CM6 grey?" fixer)
 *  ---------------------------------------------------------------------------
 *  editor-adapter.js already refuses to boot CM6 without a highlight-tag
 *  vocabulary, injects the critical base CSS and swears it also falls back
 *  when CM6 really fails. In the field the editor STILL ships grey on some
 *  devices, and it always comes down to one of four causes:
 *
 *    1. the highlight style resolved to classes that nothing paints
 *       (themes.css §7 / the adapter's injected base CSS missing or stale),
 *    2. the @lezer/highlight tag vocabulary is missing or partial
 *       ("Unknown highlighting tag …" spam, every token unclassified),
 *    3. a language grammar imports fine but parses nothing, so no token
 *       ever gets a tag,
 *    4. an extension got wiped (per-tab undo history replaces the whole
 *       EditorState and takes runtime extensions with it).
 *
 *  This module is the missing escalation ladder between "CM6 says it is
 *  fine" and "editor-fallback-hl.js repaints everything":
 *
 *    diagnose(cm)   → structured report (also printed to Settings → Logs)
 *    repair(cm)     → run the ladder below once and report what it did
 *    autoBoot()     → quietly run diagnose + repair for the first CM6 editor
 *
 *  LADDER (each step is verified before moving on — never assumed):
 *    step 0  probe the DOM for a coloured tok-* span
 *    step 1  CSS     — probe computed colours; inject the palette stylesheet
 *                      when tok-* resolves to the plain text colour
 *    step 2  TAGS    — append CodeMirror's own classHighlighter as a
 *                      fallback:true syntaxHighlighting extension. It emits
 *                      the same tok-* class names, so it reuses the CSS.
 *    step 3  LEXER   — hand over to editor-fallback-hl.js (whole-document
 *                      token cache → decorations, or the DOM painter when
 *                      this build cannot render decorations at all).
 *
 *  EXPOSES: window.IDE.cm6ColorGuard = { diagnose, repair, autoBoot, probe }
 * ==========================================================================*/
(function () {
    'use strict';

    var W = typeof window !== 'undefined' ? window : null;
    if (!W) return;
    var IDE = W.IDE = W.IDE || {};
    var DOC = typeof document !== 'undefined' ? document : null;

    var VERSION = '24.1.0';
    var PROBE_SELECTOR = '.tok-keyword,.tok-string,.tok-comment,.tok-tag,.tok-number,' +
        '.tok-variableName,.tok-propertyName,.tok-typeName,.tok-definition,.tok-function';
    var PROBE_MIN_DOC = 40;
    var LADDER_DELAYS = [1200, 2600, 4400];

    function log(level, msg, data) {
        try {
            var fn = (W.console && W.console[level]) || (W.console && W.console.log);
            if (!fn) return;
            if (data === undefined) fn.call(W.console, '[cm6-color-guard] ' + msg);
            else fn.call(W.console, '[cm6-color-guard] ' + msg, data);
        } catch (e) { }
    }

    function fallback() {
        return IDE.editorFallbackHl || null;
    }

    function activeCM() {
        try {
            var ME = IDE.mobileEditor;
            if (ME && typeof ME.getCM === 'function') return ME.getCM();
        } catch (e) { }
        return null;
    }

    /* ── one shared "are tokens coloured?" answer for a given scope ── */
    function tokenColourProbe(scopeEl) {
        var host = scopeEl || (DOC && DOC.body);
        if (!DOC || !host || !W.getComputedStyle) return { ok: true, reason: 'no-dom' };
        var wrap = DOC.createElement('div');
        wrap.setAttribute('data-qk-guard-probe', '1');
        wrap.style.cssText = 'position:absolute;left:-9999px;top:0;visibility:hidden;';
        var plain = DOC.createElement('span');
        plain.textContent = 'x';
        var tok = DOC.createElement('span');
        tok.className = 'tok-keyword';
        tok.textContent = 'x';
        wrap.appendChild(plain);
        wrap.appendChild(tok);
        host.appendChild(wrap);
        var plainCol;
        var out = { ok: true, reason: 'colours-differ', plain: '', token: '' };
        try {
            plainCol = W.getComputedStyle(plain).color;
            out.plain = plainCol;
            out.token = W.getComputedStyle(tok).color;
            out.ok = !!plainCol && !!out.token && plainCol !== out.token;
            if (!out.ok) out.reason = 'tok-* resolves to the plain text colour';
        } catch (e) {
            out.reason = 'probe failed: ' + e.message;
        }
        if (wrap.parentNode) wrap.parentNode.removeChild(wrap);
        return out;
    }

    function domHasColouredToken(cm) {
        try {
            var dom = cm && cm.view && cm.view.dom;
            if (!dom) return null;
            if (cm.view.state.doc.length < PROBE_MIN_DOC) return null;
            return !!dom.querySelector(PROBE_SELECTOR);
        } catch (e) {
            return false;
        }
    }

    function moduleReport(cm) {
        var mod = cm && cm.mod;
        var out = { hasState: false, hasView: false, hasLanguage: false, hasSyntaxHighlighting: false, hasHighlightStyle: false, hasTagVocabulary: false, classHighlighter: false, languageNames: [] };
        try {
            out.hasState = !!(mod && mod.state && mod.state.EditorState);
            out.hasView = !!(mod && mod.view && mod.view.EditorView);
            out.hasLanguage = !!(mod && mod.language && typeof mod.language.StreamLanguage === 'function');
            out.hasSyntaxHighlighting = !!(mod && mod.language && typeof mod.language.syntaxHighlighting === 'function');
            out.hasHighlightStyle = !!(mod && (mod.language && mod.language.HighlightStyle || mod.highlight && mod.highlight.HighlightStyle));
            var tags = (mod && mod.highlight && mod.highlight.tags) || (mod && mod.language && mod.language.tags) || null;
            out.hasTagVocabulary = !!(tags && tags.keyword && tags.string && tags.comment && tags.variableName);
            out.classHighlighter = !!(mod && mod.highlight && mod.highlight.classHighlighter);
        } catch (e) { }
        return out;
    }

    function coloursIn(cm) {
        /* the editor can be perfectly coloured while a specific line is not;
           sample the DOM like the fallback does, but also count how many
           coloured spans exist so "one lucky token" cannot pass for healthy */
        try {
            var dom = cm && cm.view && cm.view.dom;
            if (!dom) return -1;
            return dom.querySelectorAll(PROBE_SELECTOR).length;
        } catch (e) {
            return -1;
        }
    }

    function diagnose(cm) {
        cm = cm || activeCM();
        var F = fallback();
        var report = {
            version: VERSION,
            engine: cm ? cm.engine : null,
            docLength: 0,
            colouredSpans: -1,
            css: null,
            cm6: null,
            fallback: F ? { version: F.version, mounted: !!F.isMounted(), painter: !!F.isPainterActive() } : null,
            lines: []
        };
        if (!cm || cm.engine !== 'cm6' || !cm.view) {
            report.lines.push('no CM6 editor on screen — nothing to diagnose');
            return report;
        }
        try {
            report.docLength = cm.view.state.doc.length;
        } catch (e) { }
        report.colouredSpans = coloursIn(cm);
        report.css = tokenColourProbe(cm.mountEl || DOC.getElementById('m-editor-mount'));
        report.cm6 = moduleReport(cm);

        if (!report.cm6.hasState || !report.cm6.hasView) {
            report.lines.push('the CM6 build is incomplete (EditorState/EditorView missing) — the adapter should have fallen back to CM5');
        }
        if (!report.cm6.hasTagVocabulary) {
            report.lines.push('@lezer/highlight tag vocabulary is missing/partial → every token would be unclassified (the "Unknown highlighting tag" spam)');
        }
        if (report.css && report.css.ok === false) {
            report.lines.push('tok-* classes resolve to the plain text colour (' + report.css.plain + ') → themes.css §7 + #qk-cm6-base-css are not being served');
        }
        if (report.colouredSpans === 0) {
            report.lines.push('no coloured token span in the editor DOM → the grammar parses nothing, or the highlight style never mounted');
        } else if (report.colouredSpans < 0) {
            report.lines.push('editor DOM not reachable yet (still mounting?)');
        }
        if (report.fallback && report.fallback.mounted) {
            report.lines.push('editor-fallback-hl.js is mounted' + (report.fallback.painter ? ' (DOM painter: this build cannot render mark decorations)' : ' (decoration stream from the document token cache)'));
        }
        return report;
    }

    function printReport(cm) {
        var r = diagnose(cm);
        log('group', 'diagnose() v' + VERSION);
        try {
            log('log', 'doc ' + r.docLength + ' chars · coloured spans: ' + r.colouredSpans);
            if (r.css) log('log', 'css probe: ' + (r.css.ok ? 'ok' : 'FAILED') + ' (' + r.css.reason + ')');
            if (r.cm6) log('log', 'cm6 modules: tags=' + r.cm6.hasTagVocabulary + ' style=' + r.cm6.hasHighlightStyle + ' syntaxHighlighting=' + r.cm6.hasSyntaxHighlighting + ' classHighlighter=' + r.cm6.classHighlighter);
            for (var i = 0; i < r.lines.length; i++) log('warn', r.lines[i]);
        } finally {
            try { W.console.groupEnd(); } catch (e) { }
        }
        return r;
    }

    /* ── ladder ──────────────────────────────────────────────────── */
    function stepCss(cm) {
        var F = fallback();
        var scope = cm.mountEl || (DOC && DOC.getElementById('m-editor-mount')) || (DOC && DOC.body);
        var probe = tokenColourProbe(scope);
        if (probe.ok) return { done: false, note: 'css already colours tok-*' };
        var injected = false;
        if (F && typeof F.ensureStyles === 'function') {
            F.ensureStyles(scope);
            injected = true;
        }
        log('warn', 'step 1: tok-* was painted with the plain text colour — injected the explicit palette stylesheet');
        return { done: true, note: 'palette injected (injected=' + injected + ')' };
    }

    function stepTags(cm) {
        var mod = cm && cm.mod;
        if (!mod || !mod.language || typeof mod.language.syntaxHighlighting !== 'function') {
            return { done: false, note: 'syntaxHighlighting() not available in this build' };
        }
        if (cm._qkGuardClassHighlighter) return { done: false, note: 'already appended' };
        var builtin = (mod.highlight && mod.highlight.classHighlighter) || null;
        if (!builtin) return { done: false, note: 'classHighlighter missing from the build' };
        try {
            /* a Compartment is not available here, so append: the extension is
               additive and CM6 stacks highlight styles without conflict */
            cm.view.dispatch({
                effects: mod.state.StateEffect.appendConfig.of([
                    mod.language.syntaxHighlighting(builtin, { fallback: true })
                ])
            });
            cm._qkGuardClassHighlighter = true;
            log('warn', 'step 2: appended CodeMirror classHighlighter (fallback:true) — same tok-* classes, no tag vocabulary required');
            return { done: true, note: 'classHighlighter appended' };
        } catch (e) {
            return { done: false, note: 'appendConfig failed: ' + e.message };
        }
    }

    function stepLexer(cm) {
        var F = fallback();
        if (!F) return { done: false, note: 'editor-fallback-hl.js is not loaded (include it after editor-adapter.js)' };
        try {
            if (F.isMounted()) {
                if (typeof F.repair === 'function') F.repair(cm);
                return { done: false, note: 'fallback already mounted → repair() re-armed it' };
            }
            var ok = typeof F.repair === 'function' ? F.repair(cm) : F.mount(cm);
            log('warn', 'step 3: handing over to editor-fallback-hl.js v' + F.version + ' (whole-document token cache)');
            return { done: !!ok, note: ok ? 'fallback mounted' : 'fallback refused to mount' };
        } catch (e) {
            return { done: false, note: 'fallback mount threw: ' + e.message };
        }
    }

    function repair(cm, opts) {
        opts = opts || {};
        cm = cm || activeCM();
        if (!cm || cm.engine !== 'cm6' || !cm.view) {
            log('warn', 'repair(): no CM6 editor to repair');
            return { ok: false, applied: [] };
        }
        var doc = '';
        try {
            doc = cm.view.state.doc.toString();
        } catch (e) { }
        if (doc.length < PROBE_MIN_DOC && !opts.force) {
            /* too small to judge — a tiny file can legitimately have no tokens */
            return { ok: true, applied: [], skipped: 'document smaller than ' + PROBE_MIN_DOC + ' chars' };
        }
        if (domHasColouredToken(cm) === true) {
            log('log', 'repair(): CM6 colours look healthy, nothing to do');
            return { ok: true, applied: ['none-needed'] };
        }
        var applied = [];
        var s1 = stepCss(cm);
        applied.push({ step: 'css', done: s1.done, note: s1.note });
        if (!s1.done) {
            var s2 = stepTags(cm);
            applied.push({ step: 'tags', done: s2.done, note: s2.note });
        }
        try {
            if (cm.view.requestMeasure) cm.view.requestMeasure();
        } catch (e) { }
        var s3 = stepLexer(cm);
        applied.push({ step: 'lexer', done: s3.done, note: s3.note });
        if (opts.report !== false) printReport(cm);
        return { ok: true, applied: applied, diagnose: opts.report === false ? null : diagnose(cm) };
    }

    /* ── autoBoot: watch for the first CM6 editor, then verify ───── */
    function autoBoot() {
        if (!DOC) return;
        var tries = 0;
        var armed = false;
        var poll = setInterval(function () {
            tries++;
            var cm = activeCM();
            if (cm && cm.engine === 'cm6' && cm.view) {
                if (armed) {
                    clearInterval(poll);
                    return;
                }
                armed = true;
                LADDER_DELAYS.forEach(function (ms, idx) {
                    setTimeout(function () {
                        if (domHasColouredToken(cm) === true) return;
                        /* let editor-fallback-hl.js's own probe run first, then
                           escalate only if the editor is still grey */
                        if (idx < LADDER_DELAYS.length - 1) return;
                        repair(cm, { force: true });
                    }, ms);
                });
                clearInterval(poll);
                return;
            }
            if (tries > 40) clearInterval(poll);
        }, 400);
    }

    IDE.cm6ColorGuard = {
        version: VERSION,
        diagnose: function (cm) { return printReport(cm || activeCM()); },
        report: function (cm) { return diagnose(cm || activeCM()); },
        repair: repair,
        probe: function (cm) { return domHasColouredToken(cm || activeCM()); },
        colourProbe: tokenColourProbe,
        autoBoot: autoBoot
    };

    var AUTOSTART = !(W.IDE_CONFIG && W.IDE_CONFIG.disableGuardAutostart);
    if (AUTOSTART) {
        if (DOC.readyState === 'loading') DOC.addEventListener('DOMContentLoaded', autoBoot);
        else autoBoot();
    }
})();
