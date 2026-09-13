/**
 * ═══════════════════════════════════════════════════════════════════════════
 *  QUIRKY IDE — EDITOR ADAPTER (CM5 ⇄ CM6 compatibility facade)
 * ═══════════════════════════════════════════════════════════════════════════
 *
 *  Purpose: let mobile-editor.js and settings.js keep calling the exact
 *  same method names they already call today (getValue, setOption, on,
 *  markText, getSearchCursor, undo/redo, ...) no matter which engine is
 *  actually running underneath:
 *
 *    • CM5  — the existing vendor/codemirror tree, loaded via <script>
 *              tags exactly as before. mobileCM IS the real CM5 instance;
 *              nothing about that code path changes.
 *    • CM6  — loaded on demand via dynamic ES-module import (no bundler
 *              required — modern mobile browsers support `import()` of a
 *              bare URL). mobileCM becomes a CM6Adapter instance that
 *              exposes the same CM5-shaped surface, backed by a real
 *              CodeMirror 6 EditorView.
 *
 *  ENGINE SELECTION ("auto" + "per-user"):
 *    • Settings → Editor → "Editor Engine": Auto / CM6 / CM5 (persisted
 *      via the existing settings.js schema, key "editorEngine").
*    • ★ v18 POLICY (matches the Settings description again): "auto"
*      (the default) AND an explicit "cm6" both try CM6 first — the
*      local vendored build when app/vendor/cm6 exists, the CDN
*      otherwise — and fall back to CM5 ONLY when CM6 really fails to
*      initialise. An explicit "cm5" always wins for CM5.
*      The fallback is remembered FOR THIS PAGE LOAD ONLY (in-memory):
*      the old sessionStorage latch survived reloads, and Settings
*      applies an engine change VIA a reload — so one past CM6 failure
*      silently pinned every later load (even explicit "cm6") to CM5.
 *
 *  INTEL FACADE (autocomplete / diagnostics / lens):
 *    Every instance handed back by create() — real CM5 instances AND
 *    CM6Adapter — additionally exposes an identical intelligence surface:
 *      setCompletionProvider(fnOrNull)   provider(context) -> {list:[item…], from?:{line,ch}} | null (SYNC)
 *          context = {line, ch, textBefore, word, doc, langId}
 *          item    = string | {label, insert?, detail?, type?}
 *                    type ∈ keyword|function|snippet|property|variable|text
 *      openCompletion() / closeCompletion()
 *      setDiagnostics(markers) / clearDiagnostics()
 *          markers = [{line(0-based), ch?, length?, severity ∈ error|warning|info, message}]
 *      setLens(entries) / clearLens()
 *          entries = [{line, node | factory}]   block widgets rendered ABOVE the line
 *      setLangId(id) / getLangId()
 *    Every facade method is wrapped defensively: a throwing provider or a
 *    stale marker must never break typing. Feature modules (autocomplete.js,
 *    diagnostics.js, editor-lens.js) talk ONLY to these methods.
 *
 *  EXPOSES: window.IDE.editorAdapter = { create, ensureMode, currentEngine }
 * ═══════════════════════════════════════════════════════════════════════════
 */
(function () {
    'use strict';

    window.IDE = window.IDE || {};

    var LS_ENGINE_KEY = 'quirky.ide.settings.editorEngine';
    /* ★ v18: CM6-failure latch is in-memory ONLY (per page load). The old
       sessionStorage key survived reloads and, because Settings applies an
       engine change through a reload, it permanently pinned failed sessions
       onto CM5 — including explicit "cm6" selections. */
    var _pageCm6Failed = false;

    /* Swap this if you'd rather self-host the CM6 packages instead of
     pulling them from a CDN (recommended for production — see the
     migration notes shipped alongside this file). Any ESM-compatible
     host works (esm.sh, jsDelivr's `/+esm`, a local /vendor/cm6/ build). */
    var CM6_CDN_BASE = 'https://esm.sh/';
    var CM6_VERSION = '@6';

    /* ★ LOCAL CM6: when app/vendor/cm6/ exists (detected by mobile-shell.php),
     use bare specifiers that the import map resolves to local files,
     so CM6 works fully offline. */
    var CM6_USE_LOCAL = !!(window.IDE_CONFIG && window.IDE_CONFIG.cm6Local);
    var _activeEngine = null; // 'cm5' | 'cm6', set once the first editor instance is created

    /* ═══════════════════════════════════════════════════════════════
  ENGINE RESOLUTION
  ═══════════════════════════════════════════════════════════════ */
    function getUserEnginePref() {
        try {
            var raw = localStorage.getItem(LS_ENGINE_KEY);
            if (raw === 'cm5' || raw === 'cm6' || raw === 'auto') return raw;
        } catch (e) { }
        return 'auto';
    }

    /** Decide which engine to use, calling back with 'cm5' or 'cm6'.
*  ★ v18: "auto" AND "cm6" try CM6; create() below falls back to CM5 if
*  the CM6 modules really cannot load. Only an explicit "cm5" (or a CM6
*  failure earlier on THIS page) short-circuits to CM5. */
    function resolveEngine(cb) {
        if (_pageCm6Failed) {
            cb('cm5');
            return;
        }
        var pref = getUserEnginePref();
        if (pref === 'cm5') {
            cb('cm5');
            return;
        }
        cb('cm6'); // 'auto' AND 'cm6'
    }

    /* ═══════════════════════════════════════════════════════════════
  CM5 LOADER  (relocated verbatim from mobile-editor.js — the
  behaviour here is unchanged from before this migration)
  ═══════════════════════════════════════════════════════════════ */
    var _cm5Loaded = false;
    var _cm5Loading = false;
    var _cm5Queue = [];

    function loadCM5(callback) {
        if (typeof CodeMirror !== 'undefined') {
            _cm5Loaded = true;
            callback();
            return;
        }
        if (_cm5Loaded) {
            callback();
            return;
        }
        if (_cm5Loading) {
            _cm5Queue.push(callback);
            return;
        }
        _cm5Loading = true;

        var styles = [
            'index.php?vendor=codemirror/lib/codemirror.css',
            'index.php?vendor=codemirror/addon/fold/foldgutter.css',
            'index.php?vendor=codemirror/addon/dialog/dialog.css',
            'index.php?vendor=codemirror/addon/hint/show-hint.css'];
        var scripts = [
            'index.php?vendor=codemirror/lib/codemirror.js',
            'index.php?vendor=codemirror/mode/xml/xml.js',
            'index.php?vendor=codemirror/mode/javascript/javascript.js',
            'index.php?vendor=codemirror/mode/css/css.js',
            'index.php?vendor=codemirror/mode/clike/clike.js',
            'index.php?vendor=codemirror/mode/htmlmixed/htmlmixed.js',
            'index.php?vendor=codemirror/mode/php/php.js',
            'index.php?vendor=codemirror/mode/python/python.js',
            'index.php?vendor=codemirror/mode/markdown/markdown.js',
            'index.php?vendor=codemirror/mode/sql/sql.js',
            'index.php?vendor=codemirror/mode/yaml/yaml.js',
            'index.php?vendor=codemirror/mode/shell/shell.js',
            'index.php?vendor=codemirror/mode/properties/properties.js',
            'index.php?vendor=codemirror/addon/edit/matchbrackets.js',
            'index.php?vendor=codemirror/addon/edit/closebrackets.js',
            'index.php?vendor=codemirror/addon/edit/closetag.js',
            'index.php?vendor=codemirror/addon/selection/active-line.js',
            'index.php?vendor=codemirror/addon/comment/comment.js',
            'index.php?vendor=codemirror/addon/fold/foldcode.js',
            'index.php?vendor=codemirror/addon/fold/foldgutter.js',
            'index.php?vendor=codemirror/addon/fold/brace-fold.js',
            'index.php?vendor=codemirror/addon/fold/xml-fold.js',
            'index.php?vendor=codemirror/addon/fold/comment-fold.js',
            'index.php?vendor=codemirror/addon/fold/indent-fold.js',
            'index.php?vendor=codemirror/addon/dialog/dialog.js',
            'index.php?vendor=codemirror/addon/search/searchcursor.js',
            'index.php?vendor=codemirror/addon/search/search.js',
            'index.php?vendor=codemirror/addon/search/jump-to-line.js'];

        function done() {
            _cm5Loaded = true;
            _cm5Loading = false;
            var q = _cm5Queue.slice();
            _cm5Queue = [];
            q.forEach(function (cb) {
                cb();
            });
        }

        function loadScripts() {
            var loaded = 0;

            function next() {
                loaded++;
                if (loaded >= scripts.length) {
                    done();
                    return;
                }
                var s = document.createElement('script');
                s.src = scripts[loaded];
                s.onload = next;
                s.onerror = next;
                document.head.appendChild(s);
            }
            var s = document.createElement('script');
            s.src = scripts[0];
            s.onload = next;
            s.onerror = next;
            document.head.appendChild(s);
        }
        var cssLeft = styles.length;

        function cssSettled() {
            cssLeft--;
            if (cssLeft <= 0) loadScripts();
        }
        styles.forEach(function (href) {
            if (document.querySelector('link[href="' + href + '"]')) {
                cssSettled();
                return;
            }
            var l = document.createElement('link');
            l.rel = 'stylesheet';
            l.href = href;
            l.onload = cssSettled;
            l.onerror = cssSettled;
            var firstCss = document.querySelector('head link[rel="stylesheet"]');
            if (firstCss) document.head.insertBefore(l, firstCss);
            else document.head.appendChild(l);
        });
    }

    /* CM5 per-file language "mode" lazy loader (relocated verbatim) */
    var _cm5ModeState = {};
    var _cm5ModeQueue = {};
    /* ★ Extended with the real vendored modes for the Go / Rust / Kotlin /
     Swift / Dart / Lua / Ruby / Perl families (language.js now maps those
     extensions to them instead of plaintext). Keys mirror IDE_CONFIG.cmModes
     in mobile-shell.php — the integrator should register the same keys there;
     these internal URLs are the fallback when a key is missing from config. */
    var CM5_MODE_URLS = {
        xml: 'index.php?vendor=codemirror/mode/xml/xml.js',
        javascript: 'index.php?vendor=codemirror/mode/javascript/javascript.js',
        css: 'index.php?vendor=codemirror/mode/css/css.js',
        clike: 'index.php?vendor=codemirror/mode/clike/clike.js',
        htmlmixed: 'index.php?vendor=codemirror/mode/htmlmixed/htmlmixed.js',
        php: 'index.php?vendor=codemirror/mode/php/php.js',
        python: 'index.php?vendor=codemirror/mode/python/python.js',
        markdown: 'index.php?vendor=codemirror/mode/markdown/markdown.js',
        sql: 'index.php?vendor=codemirror/mode/sql/sql.js',
        yaml: 'index.php?vendor=codemirror/mode/yaml/yaml.js',
        shell: 'index.php?vendor=codemirror/mode/shell/shell.js',
        properties: 'index.php?vendor=codemirror/mode/properties/properties.js',
        go: 'index.php?vendor=codemirror/mode/go/go.js',
        rust: 'index.php?vendor=codemirror/mode/rust/rust.js',
        swift: 'index.php?vendor=codemirror/mode/swift/swift.js',
        dart: 'index.php?vendor=codemirror/mode/dart/dart.js',
        lua: 'index.php?vendor=codemirror/mode/lua/lua.js',
        ruby: 'index.php?vendor=codemirror/mode/ruby/ruby.js',
        perl: 'index.php?vendor=codemirror/mode/perl/perl.js',
        /* Not a mode — the simple-mode addon rust.js is built on. Loaded as a
       dependency of 'rust' via CM5_MODE_DEPS below. */
        simple: 'index.php?vendor=codemirror/addon/mode/simple.js'
    };
    var CM5_MODE_DEPS = {
        php: ['xml', 'javascript', 'css', 'clike', 'htmlmixed', 'php'],
        htmlmixed: ['xml', 'javascript', 'css', 'htmlmixed'],
        markdown: ['xml', 'markdown'],
        xml: ['xml'],
        javascript: ['javascript'],
        css: ['css'],
        clike: ['clike'],
        python: ['python'],
        sql: ['sql'],
        yaml: ['yaml'],
        shell: ['shell'],
        properties: ['properties'],
        go: ['go'],
        rust: ['simple', 'rust'], // defineSimpleMode needs addon/mode/simple first
        swift: ['swift'],
        dart: ['clike', 'dart'], // dart.js extends the clike family
        lua: ['lua'],
        ruby: ['ruby'],
        perl: ['perl']
    };

    function modeKeyFor(mode) {
        if (!mode) return null;
        var name = (typeof mode === 'string') ? mode : (mode.name || '');
        name = String(name)
            .toLowerCase();
        if (name === 'application/x-httpd-php' || name === 'text/x-php') return 'php';
        /* MIME aliases for languages whose vendored mode registers by MIME
       rather than (only) by plain name. Kotlin rides the clike mode. */
        if (name === 'text/x-kotlin') return 'clike';
        if (name === 'text/x-rustsrc' || name === 'text/rust') return 'rust';
        if (name === 'text/x-go') return 'go';
        if (name === 'text/x-swift') return 'swift';
        if (name === 'application/dart' || name === 'text/x-dart') return 'dart';
        return CM5_MODE_URLS[name] ? name : null;
    }
    /** One-time toast helper — IDE.utils may not exist yet during boot. */
    function toastOnce(msg) {
        try {
            if (window.IDE && window.IDE.utils && typeof window.IDE.utils.toast === 'function') {
                window.IDE.utils.toast(msg, 'warning', 3500);
            }
        } catch (e) { }
    }
    /** ★ HARDENING: a failed <script> used to mark the mode loaded FOREVER
     *  (_cm5ModeState=true on onerror), so one bad fetch (stale SW cache,
     *  offline blip) poisoned that language for the whole session with no
     *  message and no retry. Now failures set state='failed', surface a
     *  one-time toast, still release the queued callers (the editor must
     *  keep working — it just stays plain-text), and allow a fresh fetch
     *  on the next ensureMode() call. */
    function loadCM5ModeKey(key, cb) {
        if (_cm5ModeState[key] === true) {
            cb();
            return;
        }
        if (_cm5ModeState[key] === 'loading') {
            _cm5ModeQueue[key].push(cb);
            return;
        }
        _cm5ModeState[key] = 'loading';
        _cm5ModeQueue[key] = [cb];
        var cfgUrls = (window.IDE_CONFIG && window.IDE_CONFIG.cmModes) || null;
        var s = document.createElement('script');
        s.src = (cfgUrls && cfgUrls[key]) || CM5_MODE_URLS[key];

        function done(ok) {
            var wasFailed = !ok;
            _cm5ModeState[key] = ok ? true : 'failed'; // 'failed' → retry allowed next time
            var q = _cm5ModeQueue[key] || [];
            _cm5ModeQueue[key] = [];
            q.forEach(function (fn) {
                fn();
            });
            if (wasFailed) {
                console.warn('[editor-adapter] language mode failed to load:', key);
                toastOnce('Language highlighting failed to load: ' + key + ' — reopen the file to retry');
            }
        }
        s.onload = function () {
            done(true);
        };
        s.onerror = function () {
            done(false);
        };
        document.head.appendChild(s);
    }

    function ensureCM5Mode(mode, cb) {
        var key = modeKeyFor(mode);
        if (!key || typeof CodeMirror === 'undefined') {
            cb();
            return;
        }
        var deps = CM5_MODE_DEPS[key] || [key];
        /* ★ FIX: load dependencies SEQUENTIALLY in the listed order.
       Parallel loading let e.g. php.js register before xml/htmlmixed
       existed, wiring the mode to an empty fallback. */
        var i = 0;

        function next() {
            if (i >= deps.length) {
                cb();
                return;
            }
            loadCM5ModeKey(deps[i++], next);
        }
        next();
    }

    /* ═══════════════════════════════════════════════════════════════
  INTEL FACADE — shared helpers (CM5 side)
  ─────────────────────────────────────────────────────────────────
  Everything below is defensive by contract: a throwing provider,
  an out-of-range marker or a detached DOM node must never break
  typing. All methods swallow their own errors.
  ═══════════════════════════════════════════════════════════════ */
    function escIntel(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }
    /** v14 SMART ABSORPTION: longest suffix of `before` (≥2 chars, ≤40)
     *  that is also a prefix of `insert`. Used to extend a completion's
     *  replacement range LEFTWARDS so typed beginnings are swallowed:
     *  "<?" + pick phptag (body starts "<?php") → "<?php …", never
     *  "><?php …". The ≥2 rule stops a single shared punctuation char
     *  ("<" typed, item starting "<p>") from being wrongly absorbed. */
    function absorbPrefix(before, insert) {
        if (!before || !insert) return 0;
        var max = Math.min(before.length, insert.length, 40);
        for (var n = max; n >= 2; n--) {
            if (before.slice(before.length - n) === insert.slice(0, n)) return n;
        }
        return 0;
    }
    var INTEL_TYPES = {
        keyword: 1,


        function: 1,
        snippet: 1,
        property: 1,
        variable: 1,
        text: 1
    };
    var INTEL_GLYPHS = {
        keyword: '◆',


        function: 'ƒ',
        snippet: '⧉',
        property: '▪',
        variable: '𝑥',
        text: '·'
    };

    /** Normalize one provider item into a show-hint compatible entry. */
    function normalizeIntelItem(item) {
        if (typeof item === 'string') {
            return {
                text: item,
                displayText: item,
                className: 'm-hint-item m-hint-text'
            };
        }
        if (!item || typeof item !== 'object') return null;
        var label = String(item.label != null ? item.label : '');
        if (!label) return null;
        var type = INTEL_TYPES[item.type] ? item.type : 'text';
        var insert = (item.insert != null && typeof item.insert === 'string') ? item.insert : label;
        var detail = (item.detail != null && typeof item.detail === 'string') ? item.detail : '';
        var entry = {
            text: insert,
            displayText: label,
            className: 'm-hint-item m-hint-' + type,
            _qkDetail: detail,
            _qkType: type,
            render: function (elt) {
                try {
                    elt.innerHTML =
                        '<span class="m-hint-glyph m-hint-glyph-' + type + '">' + (INTEL_GLYPHS[type] || '·') + '</span>' +
                        '<span class="m-hint-label">' + escIntel(label) + '</span>' + (detail ? '<span class="m-hint-detail">' + escIntel(detail) + '</span>' : '');
                } catch (e) { }
            }
        };
        return entry;
    }

    /** The CodeMirror.hint helper behind openCompletion(). Reads the
     *  provider stored on the instance, builds the context object from the
     *  current cursor position, and adapts the result for show-hint. */
    function quirkyIntelHintHelper(cm) {
        try {
            if (!cm || typeof cm._qkProvider !== 'function') return null;
            var cur = cm.getCursor();
            var lineText = cm.getLine(cur.line) || '';
            var end = Math.min(cur.ch, lineText.length),
                start = end;
            while (start > 0 && /[\w$]/.test(lineText.charAt(start - 1))) start--;
            var word = lineText.slice(start, end);
            var ctx = {
                line: cur.line,
                ch: cur.ch,
                textBefore: lineText.slice(0, cur.ch),
                word: word,
                doc: null,
                langId: cm._qkLangId || null
            };
            var res = cm._qkProvider(ctx);
            if (!res || !res.list || !res.list.length) return null;
            var list = [];
            for (var i = 0; i < res.list.length && list.length < 100; i++) {
                var e = normalizeIntelItem(res.list[i]);
                if (e) list.push(e);
            }
            if (!list.length) return null;
            /* v14: per-item left-absorption. show-hint honours a per-hint `from`,
      so each entry swallows exactly as much of your typed prefix as matches
      ITS OWN beginning (phptag eats "<?", an HTML item eats nothing). */
            var before = lineText.slice(0, start);
            for (var j = 0; j < list.length; j++) {
                var a = absorbPrefix(before, list[j].text);
                if (a > 0) list[j].from = CodeMirror.Pos(cur.line, start - a);
            }
            /* ★ v11 swallowing: the replacement MUST start at the beginning of the typed
        word so picking "div" while you've typed "di" yields "<div></div>" and
        NEVER "di<div></div>". `start` is the scanned word-start and is the
        authoritative anchor; we only trust res.from when it reaches EARLIER
        (a longer prefix to swallow), never later than the word start. */
            var fromCh = start;
            if (res.from && typeof res.from.line === 'number' && typeof res.from.ch === 'number' && res.from.line === cur.line && res.from.ch >= 0 && res.from.ch < fromCh) {
                fromCh = res.from.ch;
            }
            return {
                list: list,
                from: CodeMirror.Pos(cur.line, fromCh),
                to: CodeMirror.Pos(cur.line, end)
            };
        } catch (e) {
            return null; // a broken provider must never throw into show-hint
        }
    }

    /* ── diagnostics rendering (marks + line tints + gutter dots) ── */
    function intelClearDiag(cm) {
        var i;
        var marks = cm._diagMarks || [];
        for (i = 0; i < marks.length; i++) {
            try {
                marks[i].clear();
            } catch (e) { }
        }
        cm._diagMarks = [];
        var lines = cm._diagLines || [];
        for (i = 0; i < lines.length; i++) {
            try {
                cm.removeLineClass(lines[i].line, 'background', lines[i].cls);
            } catch (e) { }
        }
        cm._diagLines = [];
        var gut = cm._diagGutterLines || [];
        for (i = 0; gut && i < gut.length; i++) {
            try {
                cm.setGutterMarker(gut[i], 'm-diag-gutter', null);
            } catch (e) { }
        }
        cm._diagGutterLines = [];
    }

    /** Make sure the diagnostics gutter exists in options.gutters (perf-mode
     *  flips in mobile-editor.js rewrite that array, so re-check every time). */
    function intelEnsureDiagGutter(cm) {
        try {
            var g = cm.getOption('gutters');
            if (!Array.isArray(g)) return;
            if (g.indexOf('m-diag-gutter') === -1) {
                cm.setOption('gutters', g.concat(['m-diag-gutter']));
            }
        } catch (e) { }
    }

    function intelSeverity(sev) {
        return (sev === 'error' || sev === 'warning' || sev === 'info') ? sev : 'info';
    }

    function intelSetDiag(cm, markers) {
        try {
            intelClearDiag(cm);
            if (!markers || !markers.length) return;
            intelEnsureDiagGutter(cm);
            var lastLine = Math.max(0, (cm.lineCount ? cm.lineCount() : 1) - 1);
            var n = Math.min(markers.length, 200); // sanity cap — phones first
            for (var i = 0; i < n; i++) {
                var mk = markers[i];
                if (!mk || typeof mk !== 'object') continue;
                var ln = Number(mk.line);
                if (!isFinite(ln)) continue;
                ln = Math.max(0, Math.min(lastLine, Math.floor(ln)));
                var sev = intelSeverity(mk.severity);
                var lineText = cm.getLine(ln) || '';
                var ch = Math.max(0, Math.min(lineText.length, Number(mk.ch) || 0));
                var len = Number(mk.length);
                if (!isFinite(len) || len <= 0) len = Math.max(1, lineText.length - ch);
                len = Math.max(1, Math.min(len, lineText.length - ch || 1));
                /* squiggle underline */
                try {
                    cm._diagMarks.push(cm.markText({
                        line: ln,
                        ch: ch
                    }, {
                        line: ln,
                        ch: ch + len
                    }, {
                        className: 'm-diag-ul m-diag-ul-' + sev
                    }));
                } catch (e) { }
                /* whole-line background tint */
                var cls = 'm-diag-line-' + sev;
                try {
                    cm.addLineClass(ln, 'background', cls);
                    cm._diagLines.push({
                        line: ln,
                        cls: cls
                    });
                } catch (e) { }
                /* gutter severity dot */
                try {
                    var dot = document.createElement('div');
                    dot.className = 'm-diag-gutter-dot diag-gutter-dot ' + sev;
                    dot.textContent = '●';
                    dot.title = String(mk.message == null ? '' : mk.message);
                    cm.setGutterMarker(ln, 'm-diag-gutter', dot);
                    cm._diagGutterLines.push(ln);
                } catch (e) { }
            }
        } catch (e) { }
    }

    /* ── lens rendering (block widgets above a line) ── */
    function intelClearLens(cm) {
        var w = cm._lensWidgets || [];
        for (var i = 0; i < w.length; i++) {
            try {
                w[i].clear();
            } catch (e) { }
        }
        cm._lensWidgets = [];
    }

    function intelSetLens(cm, entries) {
        try {
            intelClearLens(cm);
            if (!entries || !entries.length) return;
            var lastLine = Math.max(0, (cm.lineCount ? cm.lineCount() : 1) - 1);
            var n = Math.min(entries.length, 60);
            for (var i = 0; i < n; i++) {
                var en = entries[i];
                if (!en || typeof en !== 'object') continue;
                var ln = Number(en.line);
                if (!isFinite(ln)) continue;
                ln = Math.max(0, Math.min(lastLine, Math.floor(ln)));
                var node = null;
                if (en.node && en.nodeType === 1) node = en.node;
                else if (typeof en.factory === 'function') {
                    try {
                        node = en.factory();
                    } catch (e) {
                        node = null;
                    }
                }
                if (!node || node.nodeType !== 1) continue;
                try {
                    cm._lensWidgets.push(
                        cm.addLineWidget(ln, node, {
                            above: true,
                            coverGutter: false,
                            noHScroll: true
                        }));
                } catch (e) { }
            }
        } catch (e) { }
    }

    /** Attach the identical intel method set to a real CM5 instance. */
    function attachIntelFacade(cm) {
        if (!cm || cm._intelAttached) return cm;
        cm._intelAttached = true;
        cm._qkProvider = null;
        cm._qkLangId = null;
        cm._diagMarks = [];
        cm._diagLines = [];
        cm._diagGutterLines = [];
        cm._lensWidgets = [];

        cm.setCompletionProvider = function (fn) {
            cm._qkProvider = (typeof fn === 'function') ? fn : null;
        };
        cm.openCompletion = function () {
            try {
                if (typeof CodeMirror === 'undefined' || !CodeMirror.showHint || typeof cm._qkProvider !== 'function') return false;
                cm.showHint({
                    hint: quirkyIntelHintHelper,
                    completeSingle: false,
                    alignWithWord: true,
                    closeOnUnfocus: true,
                    extraKeys: {
                        Tab: function (c2, handle) {
                            handle.pick();
                        }
                    }
                });
                return true;
            } catch (e) {
                return false;
            }
        };
        cm.closeCompletion = function () {
            try {
                var ca = cm.state && cm.state.completionActive;
                if (ca && typeof ca.close === 'function') ca.close();
            } catch (e) { }
        };
        cm.setDiagnostics = function (markers) {
            intelSetDiag(cm, markers);
        };
        cm.clearDiagnostics = function () {
            intelSetDiag(cm, null);
        };
        cm.setLens = function (entries) {
            intelSetLens(cm, entries);
        };
        cm.clearLens = function () {
            intelSetLens(cm, null);
        };
        cm.setLangId = function (id) {
            cm._qkLangId = (id == null) ? null : String(id);
        };
        cm.getLangId = function () {
            return cm._qkLangId;
        };
        return cm;
    }

    function createCM5(mountEl, options, cb) {
        loadCM5(function () {
            var cm = CodeMirror(mountEl, options);
            cb(attachIntelFacade(cm));
        });
    }

    /* ═══════════════════════════════════════════════════════════════
  CM6 CORE LOADER  (dynamic ESM import — no bundler needed)
  ═══════════════════════════════════════════════════════════════ */
    var _cm6ModPromise = null;

    function u(pkg) {
        if (CM6_USE_LOCAL) return pkg; // resolved by the import map → vendor/cm6
        return CM6_CDN_BASE + pkg + CM6_VERSION;
    }

    function loadCM6Core() {
        if (_cm6ModPromise) return _cm6ModPromise;
        _cm6ModPromise = Promise.all([
            import( /* webpackIgnore: true */ u('@codemirror/state')),
            import( /* webpackIgnore: true */ u('@codemirror/view')),
            import( /* webpackIgnore: true */ u('@codemirror/commands')),
            import( /* webpackIgnore: true */ u('@codemirror/language')),
            import( /* webpackIgnore: true */ u('@codemirror/search')),
            import( /* webpackIgnore: true */ u('@codemirror/autocomplete')),
            import( /* webpackIgnore: true */ u('@lezer/highlight'))])
            .then(function (m) {
                return {
                    state: m[0],
                    view: m[1],
                    commands: m[2],
                    language: m[3],
                    search: m[4],
                    autocomplete: m[5],
                    highlight: m[6]
                };
            });
        return _cm6ModPromise;
    }

    /* CM6 per-file language package loader (mirrors CM5_MODE_URLS keys
     1:1 so ensureMode() can share the same `mode` values from
     window.IDE.language.modeForExt()). */
    var CM6_LANG_LOADERS = {
        xml: function () {
            return import( /* webpackIgnore: true */ u('@codemirror/lang-xml'))
                .then(function (m) {
                    return m.xml();
                });
        },
        javascript: function () {
            return import( /* webpackIgnore: true */ u('@codemirror/lang-javascript'))
                .then(function (m) {
                    return m.javascript();
                });
        },
        css: function () {
            return import( /* webpackIgnore: true */ u('@codemirror/lang-css'))
                .then(function (m) {
                    return m.css();
                });
        },
        /* No generic "C-like" package in CM6 — fall back to the legacy
       StreamLanguage port so C/Java/C#-family files still get
       reasonable highlighting instead of none. */
        clike: function (mod) {
            return import( /* webpackIgnore: true */ u('@codemirror/legacy-modes/mode/clike'))
                .then(function (m) {
                    return new mod.language.LanguageSupport(mod.language.StreamLanguage.define(m.c));
                });
        },
        htmlmixed: function () {
            return import( /* webpackIgnore: true */ u('@codemirror/lang-html'))
                .then(function (m) {
                    return m.html();
                });
        },
        php: function () {
            return import( /* webpackIgnore: true */ u('@codemirror/lang-php'))
                .then(function (m) {
                    return m.php();
                });
        },
        python: function () {
            return import( /* webpackIgnore: true */ u('@codemirror/lang-python'))
                .then(function (m) {
                    return m.python();
                });
        },
        markdown: function () {
            return import( /* webpackIgnore: true */ u('@codemirror/lang-markdown'))
                .then(function (m) {
                    return m.markdown();
                });
        },
        sql: function () {
            return import( /* webpackIgnore: true */ u('@codemirror/lang-sql'))
                .then(function (m) {
                    return m.sql();
                });
        },
        yaml: function () {
            return import( /* webpackIgnore: true */ u('@codemirror/lang-yaml'))
                .then(function (m) {
                    return m.yaml();
                });
        },
        shell: function (mod) {
            return import( /* webpackIgnore: true */ u('@codemirror/legacy-modes/mode/shell'))
                .then(function (m) {
                    return new mod.language.LanguageSupport(mod.language.StreamLanguage.define(m.shell));
                });
        },
        properties: function (mod) {
            return import( /* webpackIgnore: true */ u('@codemirror/legacy-modes/mode/properties'))
                .then(function (m) {
                    return new mod.language.LanguageSupport(mod.language.StreamLanguage.define(m.properties));
                });
        },
        /* ★ v23 — OFFLINE COLOUR PARITY WITH CM5.
       Go / Rust / Swift / Lua / Ruby / Perl previously had NO entry here at
       all, so on a device with only the local vendor/cm6 build (no network
       reach to index.php?vendor=... for the CM5 bridge — see
       CM5_BRIDGE_SPECS above) these languages rendered completely
       colourless: _loadLang's CM5-bridge attempt fails offline, and there
       was no native-package loader to fall back to. @codemirror/legacy-modes
       ships official ports of every one of these (same StreamLanguage
       wrapping already used for clike/shell/properties above), so they can
       be bundled locally in vendor/cm6 exactly like the others and need no
       network round-trip either. */
        go: function (mod) {
            return import( /* webpackIgnore: true */ u('@codemirror/legacy-modes/mode/go'))
                .then(function (m) {
                    return new mod.language.LanguageSupport(mod.language.StreamLanguage.define(m.go));
                });
        },
        rust: function (mod) {
            return import( /* webpackIgnore: true */ u('@codemirror/legacy-modes/mode/rust'))
                .then(function (m) {
                    return new mod.language.LanguageSupport(mod.language.StreamLanguage.define(m.rust));
                });
        },
        swift: function (mod) {
            return import( /* webpackIgnore: true */ u('@codemirror/legacy-modes/mode/swift'))
                .then(function (m) {
                    return new mod.language.LanguageSupport(mod.language.StreamLanguage.define(m.swift));
                });
        },
        lua: function (mod) {
            return import( /* webpackIgnore: true */ u('@codemirror/legacy-modes/mode/lua'))
                .then(function (m) {
                    return new mod.language.LanguageSupport(mod.language.StreamLanguage.define(m.lua));
                });
        },
        ruby: function (mod) {
            return import( /* webpackIgnore: true */ u('@codemirror/legacy-modes/mode/ruby'))
                .then(function (m) {
                    return new mod.language.LanguageSupport(mod.language.StreamLanguage.define(m.ruby));
                });
        },
        perl: function (mod) {
            return import( /* webpackIgnore: true */ u('@codemirror/legacy-modes/mode/perl'))
                .then(function (m) {
                    return new mod.language.LanguageSupport(mod.language.StreamLanguage.define(m.perl));
                });
        },
        /* Dart has no legacy-modes port to lean on (CM5's own dart.js is
       itself built as a clike variant — see CM5_MODE_DEPS.dart above), so
       there is no "real" offline Dart grammar CM6 can load without the
       network-only CM5 bridge. Approximate with the clike legacy mode —
       keywords/strings/comments/numbers still colour correctly, which beats
       the previous behaviour of zero colour whenever the CM5 bridge can't
       reach the network. */
        dart: function (mod) {
            return import( /* webpackIgnore: true */ u('@codemirror/legacy-modes/mode/clike'))
                .then(function (m) {
                    return new mod.language.LanguageSupport(mod.language.StreamLanguage.define(m.c));
                });
        }
    };

    /* ★ v17.1 — THEMED CM6 HIGHLIGHT STYLE.
defaultHighlightStyle paints CodeMirror's own pale demo colours (or
nothing at all). We replace it with a class-based style whose classes
(tok-*) are already coloured by themes.css from the --syn-* tokens, so
CM6 matches CM5 in dark AND light theme, per language.
★ v21 FIX: The previous fix incorrectly removed standard Lezer tags
(t.tagName, t.function, t.regexp, t.modifier, t.atom, etc.) under the
false assumption that they didn't exist and were crashing the build.
In reality, the crash was likely caused by a typo in the old code.
The `validTags()` helper below ALREADY safely filters out undefined
tags, so we can safely include ALL official @lezer/highlight tags.
Restoring these tags brings back syntax highlighting for HTML tags,
functions, regexps, modifiers, and atoms in native CM6 languages. */
    function quirkyCm6HighlightStyle(highlightMod, languageMod) {
        if (quirkyCm6HighlightStyle._done) return quirkyCm6HighlightStyle._cache;
        var HS = (languageMod && languageMod.HighlightStyle) || (highlightMod && highlightMod.HighlightStyle);
        var t = (highlightMod && highlightMod.tags) || (languageMod && languageMod.tags) || {};
        // Helper to safely pick only defined tags
        function validTags() {
            var res = [];
            for (var i = 0; i < arguments.length; i++) {
                if (arguments[i]) res.push(arguments[i]);
            }
            return res.length ? (res.length === 1 ? res[0] : res) : null;
        }
        var styles = [];
        function add(cls) {
            var tags = validTags.apply(null, Array.prototype.slice.call(arguments, 1));
            if (tags) styles.push({ tag: tags, class: cls });
        }
        add('tok-comment', t.comment, t.lineComment, t.blockComment, t.docComment);
        add('tok-string', t.string);
        add('tok-string2', t.specialString, t.character, t.regexp); // ★ Restored t.regexp
        add('tok-keyword', t.keyword, t.operatorKeyword, t.controlKeyword, t.definitionKeyword, t.moduleKeyword);
        add('tok-atom', t.atom, t.literal, t.bool, t.null); // ★ Restored t.atom
        add('tok-number', t.number, t.integer, t.float);
        add('tok-variableName', t.variableName, t.specialVariableName);
        add('tok-propertyName', t.propertyName, t.attributeName, t.attributeValue);
        add('tok-typeName', t.typeName, t.className, t.namespace);
        add('tok-function', t.function, t.macroName, t.macroNameDefinition); // ★ Restored t.function
        add('tok-operator', t.operator, t.arithmeticOperator, t.logicOperator, t.bitwiseOperator, t.compareOperator, t.updateOperator, t.definitionOperator, t.typeOperator, t.controlOperator, t.pointerOperator, t.spreadOperator, t.derefOperator);
        add('tok-punctuation', t.punctuation, t.paren, t.squareBracket, t.brace, t.angleBracket, t.contentSeparator, t.delimiter);
        add('tok-meta', t.meta, t.process);
        add('tok-heading', t.heading, t.heading1, t.heading2, t.heading3, t.heading4, t.heading5, t.heading6);
        add('tok-quote', t.quote);
        add('tok-link', t.link);
        add('tok-strong', t.strong);
        add('tok-emphasis', t.emphasis);
        add('tok-strikethrough', t.strikethrough);
        add('tok-invalid', t.invalid);
        add('tok-tag', t.tagName); // ★ Restored
        add('tok-attribute', t.attributeName); // ★ Restored
        add('tok-modifier', t.modifier); // ★ Restored
        if (!styles.length) {
            // No tags were available — an empty HighlightStyle paints nothing
            // at all. Signal the caller to use CM6's built-in classHighlighter
            // (same tok-* class names our CSS already colours) instead.
            quirkyCm6HighlightStyle._done = true;
            quirkyCm6HighlightStyle._cache = null;
            return null;
        }
        var S = HS.define(styles);
        quirkyCm6HighlightStyle._done = true;
        quirkyCm6HighlightStyle._cache = S;
        return S;
    }

    /* ★ v19 FIX — CM6 LAYOUT + COLOUR GUARANTEE.
    CM6 renders its own DOM (.cm-editor / .cm-scroller / tok-* classes).
    It only scrolls and colours correctly when the CM6 CSS blocks
    (mobile-editor.css §17 + themes.css §7) actually reach the page.
    A stale stylesheet cache (service worker / bundle cache / old APK
    ide.zip extraction) silently removes them and produces exactly the
    two field symptoms: no vertical scrolling (the editor grows to full
    document height inside an overflow:hidden wrap) and no syntax
    colours (tok-* classes exist but nothing paints them).
    Injecting the critical rules from JS — scoped to #m-editor-mount and
    reading the same --syn-* / --editor-* design tokens — makes CM6
    correct no matter which stylesheet generation is served. Idempotent:
    one <style> tag per session, and a no-op when the real CSS is present. */
    function ensureCm6BaseCss() {
        if (document.getElementById('qk-cm6-base-css')) return;
        var css = [
            '::-webkit-scrollbar-corner{background:transparent;}',
            '#m-editor-mount{position:absolute;inset:0;overflow:hidden;}',
            '#m-editor-mount .cm-editor{position:absolute;inset:0;width:100%;height:100%;display:flex;flex-direction:column;background:var(--editor-bg,#0b111c);color:var(--text,#e8eef7);font-size:var(--m-ed-fs,16px);line-height:1.6;font-family:var(--font-mono,monospace);outline:none!important;}',
            '#m-editor-mount .cm-editor .cm-scroller{flex:1 1 auto;min-height:0;overflow:auto!important;overscroll-behavior:contain;-webkit-overflow-scrolling:touch;touch-action:pan-x pan-y;scrollbar-width:thin;}',
            '#m-editor-mount .cm-editor .cm-gutters{position:sticky!important;left:0!important;z-index:5;background:var(--gutter-bg,#0b111c);border-right:1px solid var(--border,#263450);color:var(--line-number,#4a5d7e);flex-shrink:0;}',
            '#m-editor-mount .cm-editor .cm-gutterElement{font-size:.75em;padding:0 6px 0 10px;}',
            '#m-editor-mount .cm-editor .cm-cursor,#m-editor-mount .cm-editor .cm-dropCursor{border-left:2px solid var(--cursor,#f5a524);}',
            '#m-editor-mount .cm-editor .cm-selectionBackground{background:var(--selection,rgba(96,165,250,.18))!important;}',
            '#m-editor-mount .cm-editor.cm-focused .cm-selectionBackground{background:var(--selection-strong,rgba(96,165,250,.28))!important;}',
            '#m-editor-mount .cm-editor .cm-activeLine{background:var(--active-line,rgba(96,165,250,.05));}',
            '#m-editor-mount .cm-editor .cm-activeLineGutter{background:var(--active-line,rgba(96,165,250,.05));color:var(--accent,#f5a524);font-weight:700;}',
            '#m-editor-mount .cm-editor .cm-matchingBracket{color:var(--match-bracket,#f5a524);font-weight:700;text-decoration:underline;}',
            '#m-editor-mount .cm-editor .cm-nonmatchingBracket{color:var(--danger,#f87171);font-weight:700;}',
            '#m-editor-mount .cm-editor .cm-searchMatch{background:var(--search-hl,rgba(45,212,191,.25));}',
            '#m-editor-mount .cm-editor .cm-searchMatch-selected{background:var(--search-hl-active,rgba(245,165,36,.40));}',
            '#m-editor-mount .cm-editor .tok-comment{color:var(--syn-comment,#5f7394);font-style:italic;}',
            '#m-editor-mount .cm-editor .tok-string,#m-editor-mount .cm-editor .tok-string2{color:var(--syn-string,#c3e88d);}',
            '#m-editor-mount .cm-editor .tok-regexp{color:var(--syn-string-2,#f07178);}',
            '#m-editor-mount .cm-editor .tok-keyword,#m-editor-mount .cm-editor .tok-modifier{color:var(--syn-keyword,#c084fc);}',
            '#m-editor-mount .cm-editor .tok-atom,#m-editor-mount .cm-editor .tok-bool{color:var(--syn-atom,#ffa657);}',
            '#m-editor-mount .cm-editor .tok-number,#m-editor-mount .cm-editor .tok-integer,#m-editor-mount .cm-editor .tok-float{color:var(--syn-number,#f78c6c);}',
            '#m-editor-mount .cm-editor .tok-variableName{color:var(--syn-variable,#dbe4f3);}',
            '#m-editor-mount .cm-editor .tok-variableName.tok-definition,#m-editor-mount .cm-editor .tok-definition,#m-editor-mount .cm-editor .tok-function{color:var(--syn-def,#82aaff);}',
            '#m-editor-mount .cm-editor .tok-typeName,#m-editor-mount .cm-editor .tok-className,#m-editor-mount .cm-editor .tok-namespace{color:var(--syn-type,#7fdbca);font-weight:600;}',
            '#m-editor-mount .cm-editor .tok-propertyName{color:var(--syn-property,#82aaff);}',
            '#m-editor-mount .cm-editor .tok-operator{color:var(--syn-operator,#89ddff);}',
            '#m-editor-mount .cm-editor .tok-punctuation,#m-editor-mount .cm-editor .tok-bracket,#m-editor-mount .cm-editor .tok-paren{color:var(--syn-bracket,#a8b7cf);}',
            '#m-editor-mount .cm-editor .tok-meta,#m-editor-mount .cm-editor .tok-processing{color:var(--syn-meta,#89ddff);}',
            '#m-editor-mount .cm-editor .tok-tag{color:var(--syn-tag,#f07178);}',
            '#m-editor-mount .cm-editor .tok-attributeName{color:var(--syn-attribute,#c792ea);}',
            '#m-editor-mount .cm-editor .tok-heading{color:var(--syn-header,#f5a524);font-weight:700;}',
            '#m-editor-mount .cm-editor .tok-quote{color:var(--syn-quote,#7c8fa8);font-style:italic;}',
            '#m-editor-mount .cm-editor .tok-link{color:var(--syn-link,#2dd4bf);text-decoration:underline;}',
            '#m-editor-mount .cm-editor .tok-emphasis{font-style:italic;}',
            '#m-editor-mount .cm-editor .tok-strong{font-weight:700;}',
            '#m-editor-mount .cm-editor .tok-strikethrough{text-decoration:line-through;}',
            '#m-editor-mount .cm-editor .tok-invalid{color:var(--syn-error,#f87171);}'
        ].join('\n');
        var el = document.createElement('style');
        el.id = 'qk-cm6-base-css';
        el.textContent = css;
        document.head.appendChild(el);
    }
    /* ★ v22: HEALTH CHECK for the loaded CM6 modules.
   A code-split vendored build can silently ship an incomplete
   @lezer/highlight chunk (no `tags` vocabulary). When that happens
   EVERY token resolves to "no tag", the highlight style ends up
   empty, and the editor renders completely colourless while the
   console fills with "Unknown highlighting tag …" warnings.
   We verify the pieces we depend on BEFORE building the editor;
   a failed check takes the same path as a CM6 boot failure →
   clean fallback to the always-reliable CM5 engine. */
    function cm6ModulesSane(mod) {
        try {
            if (!mod || !mod.state || !mod.state.EditorState) return false;
            if (!mod.view || !mod.view.EditorView) return false;
            if (!mod.language || typeof mod.language.StreamLanguage !== 'function') return false;
            if (typeof mod.language.syntaxHighlighting !== 'function') return false;
            var hs = mod.highlight && mod.highlight.HighlightStyle;
            var hs2 = mod.language && mod.language.HighlightStyle;
            if (typeof hs !== 'function' && typeof hs2 !== 'function') return false;
            var t = (mod.highlight && mod.highlight.tags) || (mod.language && mod.language.tags) || null;
            if (!t || typeof t !== 'object') return false;
            if (!t.keyword || !t.string || !t.comment || !t.variableName) return false;
            return true;
        } catch (e) { return false; }
    }
    function createCM6(mountEl, options, cb, onFail) {
        loadCM6Core()
            .then(function (mod) {
                try {
                    if (!cm6ModulesSane(mod)) {
                        throw new Error('CM6 build is missing its highlight tag vocabulary (tags/HighlightStyle) — refusing a colourless editor');
                    }
                    ensureCm6BaseCss();
                    var adapter = new CM6Adapter(mod, mountEl, options);
                    cb(adapter);
                } catch (e) {
                    if (onFail) onFail(e);
                    else throw e;
                }
            })
            .catch(function (e) {
                if (onFail) onFail(e);
            });
    }

    /* ═══════════════════════════════════════════════════════════════
  CM6Adapter — exposes the CM5-shaped API that mobile-editor.js and
  settings.js already call, backed by a real CM6 EditorView.
  ═══════════════════════════════════════════════════════════════ */
    /* ★ v22: pick the syntax-highlighting extension defensively.
       Preferred: our themed class-based style (tok-* classes coloured by
       themes.css). Fallback: CodeMirror's own classHighlighter, which emits
       the SAME tok-* class names and needs no tag imports from us.
       Last resort: no highlighting extension (editor still works). */
    function cm6HighlightExt(mod) {
        var custom = quirkyCm6HighlightStyle(mod.highlight, mod.language);
        if (custom) {
            return mod.language.syntaxHighlighting(custom, { fallback: true });
        }
        var builtin = (mod.highlight && mod.highlight.classHighlighter) || null;
        if (builtin && typeof mod.language.syntaxHighlighting === 'function') {
            return mod.language.syntaxHighlighting(builtin, { fallback: true });
        }
        return [];
    }

    function CM6Adapter(mod, mountEl, options) {
        var self = this;
        this.engine = 'cm6';
        this.mod = mod;
        this.mountEl = mountEl;
        this._opts = Object.assign({}, options);
        this._handlers = {
            change: [],
            cursorActivity: [],
            inputRead: []
        };
        this._marks = [];
        this._markSeq = 0;
        this._langCache = {}; // mode key -> resolved LanguageSupport extension
        this._htmlLangMod = null; // cached @codemirror/lang-html module (for autoCloseTags)
        this._pendingLangKey = null;
        /* ── INTEL FACADE state ── */
        this._provider = null; // completion provider fn (or null)
        this._langId = null;
        this._diagData = {
            markers: [],
            lens: []
        };
        this._diagFieldBuilt = false;

        var state = mod.state,
            view = mod.view,
            commands = mod.commands,
            language = mod.language,
            search = mod.search,
            autocomplete = mod.autocomplete,
            highlight = mod.highlight;
        var Compartment = state.Compartment;

        var c = this._c = {
            lineNumbers: new Compartment(),
            foldGutter: new Compartment(),
            matchBrackets: new Compartment(),
            closeBrackets: new Compartment(),
            autoCloseTags: new Compartment(),
            activeLine: new Compartment(),
            wrap: new Compartment(),
            lang: new Compartment(),
            tabSize: new Compartment(),
            indentUnit: new Compartment(),
            decorations: new Compartment(),
            autocomplete: new Compartment(), // INTEL FACADE: provider-driven completion
            diag: new Compartment() // INTEL FACADE: diagnostics + lens decorations
        };

        var indentUnitStr = options.indentWithTabs ? '\t' : ' '.repeat(Number(options.indentUnit || options.tabSize || 2));

        var extensions = [
            c.lineNumbers.of(options.lineNumbers !== false ? [view.lineNumbers()] : []),
            c.foldGutter.of(options.foldGutter ? [language.foldGutter()] : []),
            view.highlightSpecialChars(),
            commands.history(),
            view.drawSelection(),
            view.dropCursor(),
            state.EditorState.allowMultipleSelections.of(true),
            language.indentOnInput(),
            cm6HighlightExt(mod),
            c.matchBrackets.of(options.matchBrackets ? [language.bracketMatching()] : []),
            c.closeBrackets.of(options.autoCloseBrackets ? [autocomplete.closeBrackets()] : []),
            c.autoCloseTags.of([]), // populated once the html language package loads, if enabled
            /* ★ INTEL FACADE: completion is driven by the provider stored on this
             adapter (setCompletionProvider). The override source adapts the
             engine-neutral contract to @codemirror/autocomplete. */
            c.autocomplete.of(autocomplete.autocompletion({
                override: [function (ctx) {
                    return self._completionSource(ctx);
                }],
                activateOnTypingDelay: 250
            })),
            c.diag.of([]),
            view.rectangularSelection(),
            view.crosshairCursor(),
            c.activeLine.of(options.styleActiveLine ? [view.highlightActiveLine(), view.highlightActiveLineGutter()] : []),
            search.highlightSelectionMatches(),
            view.keymap.of([].concat(
                autocomplete.closeBracketsKeymap, commands.defaultKeymap, commands.historyKeymap,
                language.foldKeymap, search.searchKeymap, autocomplete.completionKeymap)),
            c.wrap.of(options.lineWrapping ? [view.EditorView.lineWrapping] : []),
            c.tabSize.of(state.EditorState.tabSize.of(Number(options.tabSize) || 2)),
            c.indentUnit.of(language.indentUnit.of(indentUnitStr)),
            c.lang.of([]),
            c.decorations.of(view.EditorView.decorations.of(view.Decoration.none)),
            view.EditorView.updateListener.of(function (update) {
                if (update.docChanged) {
                    self._handlers.change.forEach(function (h) {
                        try {
                            h();
                        } catch (e) { }
                    });
                    self._synthInputRead(update);
                }
                if (update.selectionSet || update.docChanged) {
                    self._handlers.cursorActivity.forEach(function (h) {
                        try {
                            h();
                        } catch (e) { }
                    });
                }
            })];
        this._extensions = extensions;

        this.view = new view.EditorView({
            state: state.EditorState.create({
                doc: options.value || '',
                extensions: extensions
            }),
            parent: mountEl
        });

        if (options.mode) this._applyMode(options.mode);
    }

    CM6Adapter.prototype._posToOffset = function (pos) {
        if (pos == null) return 0;
        if (typeof pos === 'number') return pos;
        var doc = this.view.state.doc;
        var lineNo = Math.max(1, Math.min(doc.lines, (pos.line || 0) + 1));
        var line = doc.line(lineNo);
        var ch = Math.max(0, Math.min(line.length, pos.ch || 0));
        return line.from + ch;
    };
    CM6Adapter.prototype._offsetToPos = function (off) {
        var doc = this.view.state.doc;
        off = Math.max(0, Math.min(doc.length, off));
        var line = doc.lineAt(off);
        return {
            line: line.number - 1,
            ch: off - line.from
        };
    };

    /* ── value / content ── */
    CM6Adapter.prototype.getValue = function () {
        return this.view.state.doc.toString();
    };
    CM6Adapter.prototype.setValue = function (text) {
        this.view.dispatch({
            changes: {
                from: 0,
                to: this.view.state.doc.length,
                insert: text || ''
            }
        });
    };
    CM6Adapter.prototype.lineCount = function () {
        return this.view.state.doc.lines;
    };
    CM6Adapter.prototype.getLine = function (n) {
        var ln = n + 1;
        if (ln < 1 || ln > this.view.state.doc.lines) return '';
        return this.view.state.doc.line(ln)
            .text;
    };
    CM6Adapter.prototype.replaceRange = function (text, from, to) {
        this.view.dispatch({
            changes: {
                from: this._posToOffset(from),
                to: this._posToOffset(to),
                insert: text
            }
        });
    };

    /* ── cursor / scrolling ── */
    CM6Adapter.prototype.getCursor = function () {
        return this._offsetToPos(this.view.state.selection.main.head);
    };
    CM6Adapter.prototype.setCursor = function (pos) {
        var off = this._posToOffset(pos);
        this.view.dispatch({
            selection: {
                anchor: off,
                head: off
            }
        });
    };
    /* ★ v11 intel support: range selection (autocomplete placeholder select).
     CM5 instances have this natively; this completes the facade contract. */
    CM6Adapter.prototype.setSelection = function (anchor, head) {
        var a = this._posToOffset(anchor);
        var h = this._posToOffset(head == null ? anchor : head);
        this.view.dispatch({
            selection: {
                anchor: a,
                head: h
            }
        });
    };
    CM6Adapter.prototype.scrollIntoView = function (pos, margin) {
        var off = this._posToOffset(pos);
        this.view.dispatch({
            effects: this.mod.view.EditorView.scrollIntoView(off, {
                yMargin: margin || 0
            })
        });
    };

    /* ── focus / refresh ── */
    CM6Adapter.prototype.focus = function () {
        this.view.focus();
    };
    CM6Adapter.prototype.refresh = function () {
        this.view.requestMeasure();
    };

    /* ── undo / redo / history ──
     CM6 keeps undo history as part of EditorState itself, so the most
     faithful way to give mobile-editor.js "per-tab undo history" (Task
     9.8) is to hand back/restore the *whole* EditorState as the opaque
     "history" token — this keeps doc + selection + undo stack in sync,
     which is actually more robust than CM5's separate history blob. */
    CM6Adapter.prototype.undo = function () {
        this.mod.commands.undo(this.view);
    };
    CM6Adapter.prototype.redo = function () {
        this.mod.commands.redo(this.view);
    };
    CM6Adapter.prototype.getHistory = function () {
        return {
            state: this.view.state
        };
    };
    CM6Adapter.prototype.setHistory = function (hist) {
        if (hist && hist.state) this.view.setState(hist.state);
    };
    CM6Adapter.prototype.clearHistory = function () {
        var doc = this.view.state.doc;
        var fresh = this.mod.state.EditorState.create({
            doc: doc,
            extensions: this._extensions,
            selection: this.view.state.selection
        });
        this.view.setState(fresh);
    };

    /* ── events ── */
    CM6Adapter.prototype.on = function (event, handler) {
        if (this._handlers[event]) this._handlers[event].push(handler);
    };

    /* ── search-result highlighting (facade over Decoration API) ── */
    CM6Adapter.prototype.markText = function (from, to, opts) {
        var self = this;
        var f = this._posToOffset(from),
            t = this._posToOffset(to);
        var id = ++this._markSeq;
        this._marks.push({
            id: id,
            from: f,
            to: t,
            className: (opts && opts.className) || ''
        });
        this._syncDecorations();
        return {
            clear: function () {
                self._marks = self._marks.filter(function (m) {
                    return m.id !== id;
                });
                self._syncDecorations();
            }
        };
    };
    CM6Adapter.prototype._syncDecorations = function () {
        var Decoration = this.mod.view.Decoration;
        var docLen = this.view.state.doc.length;
        var ranges = this._marks.filter(function (m) {
            return m.to > m.from && m.to <= docLen;
        })
            .sort(function (a, b) {
                return a.from - b.from || a.to - b.to;
            })
            .map(function (m) {
                return Decoration.mark({
                    class: m.className
                })
                    .range(m.from, m.to);
            });
        try {
            var set = Decoration.set(ranges, true);
            this.view.dispatch({
                effects: this._c.decorations.reconfigure(this.mod.view.EditorView.decorations.of(set))
            });
        } catch (e) { /* stale ranges after a big edit — ignore, next mark/clear resyncs */ }
    };

    /* ── find-in-file (facade over @codemirror/search's SearchCursor) ── */
    CM6Adapter.prototype.getSearchCursor = function (query, start, opts) {
        var self = this;
        var SearchCursor = this.mod.search.SearchCursor;
        var doc = this.view.state.doc;
        var normalize = (opts && opts.caseFold) ? function (s) {
            return s.toLowerCase();
        } : undefined;
        var startOff = start ? this._posToOffset(start) : 0;
        var raw = new SearchCursor(doc, query, startOff, doc.length, normalize);
        var last = null;
        return {
            findNext: function () {
                var r = raw.next();
                if (r.done) {
                    last = null;
                    return false;
                }
                last = r.value;
                return true;
            },
            from: function () {
                return last ? self._offsetToPos(last.from) : null;
            },
            to: function () {
                return last ? self._offsetToPos(last.to) : null;
            }
        };
    };

    /* ── options ── */
    CM6Adapter.prototype.getOption = function (name) {
        return this._opts[name];
    };
    CM6Adapter.prototype.setOption = function (name, value) {
        this._opts[name] = value;
        var mod = this.mod,
            c = this._c,
            view = mod.view,
            language = mod.language,
            autocomplete = mod.autocomplete;
        switch (name) {
            case 'lineWrapping':
                this.view.dispatch({
                    effects: c.wrap.reconfigure(value ? [view.EditorView.lineWrapping] : [])
                });
                break;
            case 'matchBrackets':
                this.view.dispatch({
                    effects: c.matchBrackets.reconfigure(value ? [language.bracketMatching()] : [])
                });
                break;
            case 'autoCloseBrackets':
                this.view.dispatch({
                    effects: c.closeBrackets.reconfigure(value ? [autocomplete.closeBrackets()] : [])
                });
                break;
            case 'autoCloseTags':
                this._applyAutoCloseTags();
                break;
            case 'styleActiveLine':
                this.view.dispatch({
                    effects: c.activeLine.reconfigure(value ? [view.highlightActiveLine(), view.highlightActiveLineGutter()] : [])
                });
                break;
            case 'foldGutter':
                this.view.dispatch({
                    effects: c.foldGutter.reconfigure(value ? [language.foldGutter()] : [])
                });
                break;
            case 'lineNumbers':
                this.view.dispatch({
                    effects: c.lineNumbers.reconfigure(value ? [view.lineNumbers()] : [])
                });
                break;
            case 'gutters':
                {
                    // CM5 passes an array of gutter class names; translate membership
                    // into the two boolean gutter compartments CM6 actually has.
                    var hasLN = Array.isArray(value) && value.indexOf('CodeMirror-linenumbers') !== -1;
                    var hasFold = Array.isArray(value) && value.indexOf('CodeMirror-foldgutter') !== -1;
                    this.view.dispatch({
                        effects: [
                            c.lineNumbers.reconfigure(hasLN ? [view.lineNumbers()] : []),
                            c.foldGutter.reconfigure(hasFold ? [language.foldGutter()] : [])]
                    });
                    break;
                }
            case 'tabSize':
                this.view.dispatch({
                    effects: c.tabSize.reconfigure(mod.state.EditorState.tabSize.of(Number(value) || 2))
                });
                break;
            case 'indentUnit':
                {
                    var unit = this._opts.indentWithTabs ? '\t' : ' '.repeat(Number(value) || 2);
                    this.view.dispatch({
                        effects: c.indentUnit.reconfigure(language.indentUnit.of(unit))
                    });
                    break;
                }
            case 'indentWithTabs':
                {
                    var unit2 = value ? '\t' : ' '.repeat(Number(this._opts.indentUnit) || 2);
                    this.view.dispatch({
                        effects: c.indentUnit.reconfigure(language.indentUnit.of(unit2))
                    });
                    break;
                }
            case 'theme':
                // CM6 theming is a separate extension system from CM5's CSS
                // themes; wiring a real one-to-one theme pack is a follow-up.
                // For now, expose a hook so the app's own CSS can style the
                // wrapper per engine/theme without CM6 crashing on the option.
                if (this.mountEl) this.mountEl.setAttribute('data-cm6-theme', value || 'default');
                break;
            case 'mode':
                this._applyMode(value);
                break;
            case 'viewportMargin':
            case 'inputStyle':
            case 'dragDrop':
                // No CM6 equivalent needed: it virtualises rendering internally
                // and always supports touch / contenteditable input.
                break;
            default:
                break;
        }
    };

    CM6Adapter.prototype._applyAutoCloseTags = function () {
        if (!this._htmlLangMod) return; // applied once lang-html has loaded (see _applyMode)
        var enabled = !!this._opts.autoCloseTags;
        var isHtmlish = this._pendingLangKey === 'htmlmixed' || this._pendingLangKey === 'xml';
        this.view.dispatch({
            effects: this._c.autoCloseTags.reconfigure(enabled && isHtmlish ? [this._htmlLangMod.autoCloseTags()] : [])
        });
    };

    /** Load (with caching/de-duping) the CM6 language extension for `key`.
     *  Returns a promise that always resolves (never rejects) so callers
     *  never have to worry about a missing/blocked package hanging them. */
    /* ★ v17.1 — CM5 → CM6 LANGUAGE BRIDGE.
   When a CM6 language package cannot be imported (offline app, missing
   vendored file), we load the CM5 mode script that ALREADY ships in the
   vendor folder and wrap it with CodeMirror 6's official legacy-mode
   bridge (StreamLanguage). Result: CM6 gets the same per-extension colour
   variety CM5 has, with zero network dependency. */
    var CM5_BRIDGE_SPECS = {
        xml: 'xml',
        javascript: {
            name: 'javascript'
        },
        css: 'css',
        clike: 'clike',
        htmlmixed: 'htmlmixed',
        php: 'application/x-httpd-php',
        python: 'python',
        markdown: 'markdown',
        sql: 'sql',
        yaml: 'yaml',
        shell: 'shell',
        properties: 'properties',
        go: 'go',
        rust: 'rust',
        swift: 'swift',
        dart: 'dart',
        lua: 'lua',
        ruby: 'ruby',
        perl: 'perl'
    };
    CM6Adapter.prototype._loadLangViaCM5 = function (key) {
        var self = this;
        var spec = CM5_BRIDGE_SPECS[key];
        if (!spec) return Promise.resolve(null);
        return new Promise(function (resolve) {
            loadCM5(function () {
                ensureCM5Mode(spec, function () {
                    try {
                        if (typeof CodeMirror === 'undefined' || !CodeMirror.getMode) {
                            resolve(null);
                            return;
                        }
                        var modeObj = CodeMirror.getMode({
                            indentUnit: 2,
                            tabSize: 2
                        }, spec);
                        if (!modeObj || typeof modeObj.token !== 'function') {
                            resolve(null);
                            return;
                        }

                        // ★ v22 FIX: CM5 → CM6 TOKEN BRIDGE, OFFICIAL VOCABULARY.
                        // CM6 resolves a stream-mode token style by looking each space/dot
                        // separated word up in (a) a small legacy alias table and (b) the
                        // @lezer/highlight tag names (keyword, string, comment, tagName,
                        // variableName, propertyName, …; modifiers are written as
                        // "name.modifier", e.g. "variableName.special"). The old v21 table
                        // renamed CM5 styles to strings that are NEITHER ("type",
                        // "variable"), so those tokens resolved to NO tag: "Unknown
                        // highlighting tag type/variable" warnings + colourless text.
                        // We now translate every CM5 style word to its official Lezer tag
                        // name and pass through anything already valid (keyword, string,
                        // comment, number, atom, operator, meta, bracket, …).
                        var CM5_TO_LEZER = {
                            'tag': 'tagName',
                            'attribute': 'attributeName',
                            'variable': 'variableName',
                            'variable-2': 'variableName.special',
                            'variable-3': 'typeName',
                            'def': 'variableName.definition',
                            'string-2': 'string.special',
                            'builtin': 'variableName.standard',
                            'qualifier': 'modifier',
                            'type': 'typeName',
                            'error': 'invalid',
                            'header': 'heading',
                            'property': 'propertyName',
                            'callee': 'variableName.function',
                            'em': 'emphasis',
                            'hr': 'contentSeparator',
                            'content': 'content'
                        };
                        var originalToken = modeObj.token;
                        var mapTypeWord = function (t) { return CM5_TO_LEZER[t] || t; };
                        modeObj.token = function (stream, state) {
                            var type = originalToken.call(this, stream, state);
                            if (!type) return type;
                            if (type.indexOf(' ') !== -1) {
                                return type.split(' ').map(mapTypeWord).join(' ');
                            }
                            return mapTypeWord(type);
                        };

                        var ext = new self.mod.language.LanguageSupport(
                            self.mod.language.StreamLanguage.define(modeObj));
                        self._langCache[key] = ext;
                        resolve(ext);
                    } catch (e) {
                        resolve(null);
                    }
                });
            });
        });
    };
    CM6Adapter.prototype._loadLang = function (key) {
        var self = this;
        if (this._langCache[key]) return Promise.resolve(this._langCache[key]);
        if (this._langLoading && this._langLoading[key]) return this._langLoading[key];
        this._langLoading = this._langLoading || {};
        var loader = CM6_LANG_LOADERS[key];
        /* ★ v20 — DETERMINISTIC SYNTAX COLOURS ON CM6.
        Old order: native CM6 language package first, CM5 bridge only when the
        import REJECTED. A package that imports fine but whose grammar chunks
        are broken/missing on-device parses NOTHING, so the editor stayed
        completely uncoloured with no error and no fallback (the "pale CM6"
        field report).
        New order: the CM5→CM6 bridge FIRST. Those vendored CM5 modes are
        provably the same modes that colour the CM5 engine on this exact
        device, and wrapping them in StreamLanguage is official CM6 API with
        zero extra chunk/lezer dependencies. The native CM6 packages remain
        as the fallback for keys the bridge does not cover. */
        var p = this._loadLangViaCM5(key)
            .then(function (ext) {
                if (ext) {
                    self._langSource = self._langSource || {};
                    self._langSource[key] = 'bridge';
                    return ext;
                }
                if (!loader) return null;
                return loader(self.mod)
                    .then(function (ext2) {
                        self._langCache[key] = ext2;
                        self._langSource = self._langSource || {};
                        self._langSource[key] = 'package';
                        if (key === 'htmlmixed' && !self._htmlLangMod) {
                            return import( /* webpackIgnore: true */ u('@codemirror/lang-html'))
                                .then(function (m) {
                                    self._htmlLangMod = m;
                                    return ext2;
                                })
                                .catch(function () {
                                    return ext2;
                                });
                        }
                        return ext2;
                    })
                    .catch(function () {
                        return null;
                    });
            });
        this._langLoading[key] = p;
        return p;
    };
    /* ★ v20 — SELF-CHECK: 600 ms after a language is mounted, look for at
    least one coloured token span in the editor DOM. If none exists (both
    sources can theoretically misbehave), swap to the OTHER source once and
    leave a console breadcrumb naming the attempt — a future pale editor
    self-heals AND tells us exactly what happened in Settings → Logs. */
    CM6Adapter.prototype._verifyHighlight = function (key) {
        var self = this;
        this._verifiedKeys = this._verifiedKeys || {};
        if (this._verifiedKeys[key]) return;
        this._verifiedKeys[key] = true;
        setTimeout(function () {
            try {
                if (!self.view || self._pendingLangKey !== key) return;
                if (self.view.state.doc.length < 40) return; // too small to judge
                var probe = self.view.dom.querySelector(
                    '.tok-keyword,.tok-string,.tok-comment,.tok-tag,.tok-number,' +
                    '.tok-variableName,.tok-propertyName,.tok-typeName,.tok-definition,.tok-function');
                if (probe) return; // colours are alive — nothing to do
                var src = (self._langSource || {})[key];
                if (window.console) console.warn('[editor-adapter] CM6 painted no highlight tokens for "' + key + '" (source=' + src + ') — swapping language source');
                var alt = (src === 'bridge')
                    ? (CM6_LANG_LOADERS[key]
                        ? CM6_LANG_LOADERS[key](self.mod).catch(function () { return null; })
                        : Promise.resolve(null))
                    : self._loadLangViaCM5(key);
                alt.then(function (ext2) {
                    if (!ext2 || self._pendingLangKey !== key) return;
                    self._langCache[key] = ext2;
                    self._langSource[key] = (src === 'bridge') ? 'package' : 'bridge';
                    self.view.dispatch({
                        effects: self._c.lang.reconfigure([ext2])
                    });
                });
            } catch (e) { /* never break typing for a cosmetic check */ }
        }, 600);
    };

    /** Apply `mode` immediately if already cached (sync), and kick off a
     *  (de-duped) load otherwise, reconfiguring once it resolves. */
    CM6Adapter.prototype._applyMode = function (mode) {
        var self = this;
        var key = modeKeyFor(mode);
        this._pendingLangKey = key;
        if (!key) {
            this.view.dispatch({
                effects: [this._c.lang.reconfigure([]), this._c.autoCloseTags.reconfigure([])]
            });
            return;
        }
        if (this._langCache[key]) {
            this.view.dispatch({
                effects: this._c.lang.reconfigure([this._langCache[key]])
            });
            this._applyAutoCloseTags();
            return;
        }
        this._loadLang(key)
            .then(function (ext) {
                if (ext && self._pendingLangKey === key) {
                    self.view.dispatch({
                        effects: self._c.lang.reconfigure([ext])
                    });
                    self._applyAutoCloseTags();
                    self._verifyHighlight(key);
                }
            });
    };

    /** Engine-aware "wait until this mode's language is actually loaded"
     *  used by ensureMode() below, so callers get the same load-then-
     *  recolour callback timing CM5 gave them. */
    CM6Adapter.prototype._waitForMode = function (mode, cb) {
        var key = modeKeyFor(mode);
        if (!key || this._langCache[key]) {
            cb();
            return;
        }
        this._loadLang(key)
            .then(function () {
                cb();
            });
    };

    /* ═══════════════════════════════════════════════════════════════
  CM6Adapter — INTEL FACADE (identical surface to the CM5 side)
  ═══════════════════════════════════════════════════════════════ */

    /* ── completion ── */
    /** Adapt the engine-neutral provider contract to an
     *  @codemirror/autocomplete override source. Synchronous by contract;
     *  any throw is swallowed so typing never breaks. */
    CM6Adapter.prototype._completionSource = function (ctx) {
        try {
            if (typeof this._provider !== 'function') return null;
            var off = ctx.pos;
            var line = this.view.state.doc.lineAt(off);
            var ch = off - line.from;
            var text = line.text;
            var end = Math.min(ch, text.length),
                start = end;
            while (start > 0 && /[\w$]/.test(text.charAt(start - 1))) start--;
            var word = text.slice(start, end);
            var res = this._provider({
                line: line.number - 1,
                ch: ch,
                textBefore: text.slice(0, ch),
                word: word,
                doc: null,
                langId: this._langId
            });
            if (!res || !res.list || !res.list.length) return null;
            var fromOff = start + line.from;
            if (res.from && typeof res.from.ch === 'number' && typeof res.from.line === 'number' && res.from.line === line.number - 1) {
                fromOff = line.from + Math.max(0, Math.min(ch, res.from.ch));
            }
            var options = [];
            for (var i = 0; i < res.list.length && options.length < 100; i++) {
                var it = res.list[i];
                var label, insert, type;
                if (typeof it === 'string') {
                    label = insert = it;
                    type = 'text';
                } else if (it && typeof it === 'object' && it.label != null) {
                    label = String(it.label);
                    insert = (it.insert != null && typeof it.insert === 'string') ? it.insert : label;
                    type = it.type;
                } else continue;
                if (!label) continue;
                options.push({
                    label: label,
                    detail: (it && it.detail != null) ? String(it.detail) : undefined,
                    type: type,
                    apply: (typeof insert === 'string') ? insert.split('')
                        .join('') : insert,
                    boost: (type === 'snippet' || type === 'function') ? 10 : (type === 'keyword' ? 5 : (typeof it === 'object' && it && it.type ? 0 : -5))
                });
            }
            if (!options.length) return null;
            return {
                from: fromOff,
                options: options,
                validFor: /^[\w$]*$/
            };
        } catch (e) {
            return null;
        }
    };
    CM6Adapter.prototype.setCompletionProvider = function (fn) {
        this._provider = (typeof fn === 'function') ? fn : null;
    };
    CM6Adapter.prototype.openCompletion = function () {
        try {
            if (typeof this._provider !== 'function') return false;
            this.mod.autocomplete.startCompletion(this.view);
            return true;
        } catch (e) {
            return false;
        }
    };
    CM6Adapter.prototype.closeCompletion = function () {
        try {
            this.mod.autocomplete.closeCompletion(this.view);
        } catch (e) { }
    };

    /* ── CM6 inputRead synthesis ──
     The auto-popup trigger in autocomplete.js listens for the CM5
     'inputRead' event. CM6 has no such event on our facade, so we
     synthesize one from pure single-character insertions. */
    CM6Adapter.prototype._synthInputRead = function (update) {
        try {
            var handlers = this._handlers.inputRead;
            if (!handlers || !handlers.length) return;
            var self = this;
            update.changes.iterChangedRanges(function (fromA, toA, fromB, toB) {
                if (fromA !== toA) return; // deletion/replacement — skip
                var inserted = update.state.doc.sliceString(fromB, toB);
                if (!inserted || /[\r\n]/.test(inserted)) return;
                var pos = self._offsetToPos(toB);
                self._handlers.inputRead.forEach(function (h) {
                    try {
                        h(self, {
                            line: pos.line,
                            ch: pos.ch,
                            text: inserted.charAt(inserted.length - 1)
                        });
                    } catch (e) { }
                });
            });
        } catch (e) { }
    };

    /* ── diagnostics + lens decorations ──
     One lazily-built StateField holds all decorations (squiggle marks,
     whole-line tints, block lens widgets); a sibling field feeds the
     gutter severity dots. Both rebuild from this._diagData whenever the
     SetDiag effect fires, and map through doc changes otherwise — the
     same trade-off the markText facade makes (no live re-anchoring). */
    CM6Adapter.prototype._ensureDiagState = function () {
        if (this._diagFieldBuilt) return;
        this._diagFieldBuilt = true;
        var state = this.mod.state,
            view = this.mod.view;
        var self = this;
        var SetDiag = state.StateEffect.define();

        function LensWidget(node) {
            view.WidgetType.call(this);
            this.node = node;
        }
        LensWidget.prototype = Object.create(view.WidgetType.prototype);
        LensWidget.prototype.constructor = LensWidget;
        LensWidget.prototype.eq = function (other) {
            return !!other && other.node === this.node;
        };
        LensWidget.prototype.toDOM = function () {
            return this.node;
        };
        LensWidget.prototype.ignoreEvent = function () {
            return true;
        };

        function DiagDot(sev, msg) {
            this.sev = sev;
            this.msg = msg || '';
        }
        DiagDot.prototype = Object.create(view.GutterMarker.prototype);
        DiagDot.prototype.constructor = DiagDot;
        DiagDot.prototype.eq = function (other) {
            return !!other && other.sev === this.sev;
        };
        DiagDot.prototype.toDOM = function () {
            var d = document.createElement('div');
            d.className = 'm-diag-gutter-dot diag-gutter-dot ' + this.sev;
            d.textContent = '●';
            d.title = this.msg;
            return d;
        };

        function buildDeco() {
            var Decoration = view.Decoration;
            var doc = self.view.state.doc;
            var ranges = [];
            var i, mk, ln, sev, lineObj, ch, len;
            var markers = self._diagData.markers || [];
            var n = Math.min(markers.length, 200);
            for (i = 0; i < n; i++) {
                mk = markers[i];
                if (!mk || typeof mk !== 'object') continue;
                ln = Number(mk.line);
                if (!isFinite(ln)) continue;
                ln = Math.max(0, Math.min(doc.lines - 1, Math.floor(ln)));
                sev = intelSeverity(mk.severity);
                lineObj = doc.line(ln + 1);
                ch = Math.max(0, Math.min(lineObj.length, Number(mk.ch) || 0));
                len = Number(mk.length);
                if (!isFinite(len) || len <= 0) len = Math.max(1, lineObj.length - ch);
                len = Math.max(1, Math.min(len, lineObj.length - ch || 1));
                try {
                    ranges.push(Decoration.mark({
                        class: 'm-diag-ul m-diag-ul-' + sev
                    })
                        .range(lineObj.from + ch, lineObj.from + ch + len));
                    ranges.push(Decoration.line({
                        class: 'm-diag-line-' + sev
                    })
                        .range(lineObj.from));
                } catch (e) { }
            }
            var lens = self._diagData.lens || [];
            var m = Math.min(lens.length, 60);
            for (i = 0; i < m; i++) {
                var en = lens[i];
                if (!en || typeof en !== 'object') continue;
                ln = Number(en.line);
                if (!isFinite(ln)) continue;
                ln = Math.max(0, Math.min(doc.lines - 1, Math.floor(ln)));
                var node = null;
                if (en.node && en.nodeType === 1) node = en.node;
                else if (typeof en.factory === 'function') {
                    try {
                        node = en.factory();
                    } catch (e) {
                        node = null;
                    }
                }
                if (!node || node.nodeType !== 1) continue;
                try {
                    ranges.push(Decoration.widget({
                        widget: new LensWidget(node),
                        side: -1,
                        block: true
                    })
                        .range(doc.line(ln + 1)
                            .from));
                } catch (e) { }
            }
            try {
                return view.Decoration.set(ranges, true);
            } catch (e) {
                return view.Decoration.none;
            }
        }

        function buildGutterSet() {
            var RangeSet = state.RangeSet;
            var doc = self.view.state.doc;
            var markers = self._diagData.markers || [];
            var n = Math.min(markers.length, 200);
            var out = [];
            for (var i = 0; i < n; i++) {
                var mk = markers[i];
                if (!mk || typeof mk !== 'object') continue;
                var ln = Number(mk.line);
                if (!isFinite(ln)) continue;
                ln = Math.max(0, Math.min(doc.lines - 1, Math.floor(ln)));
                try {
                    out.push(new DiagDot(intelSeverity(mk.severity), mk.message)
                        .range(doc.line(ln + 1)
                            .from));
                } catch (e) { }
            }
            try {
                return RangeSet.of(out, true);
            } catch (e) {
                return RangeSet.empty;
            }
        }

        var decoField = state.StateField.define({
            create: function () {
                return view.Decoration.none;
            },
            update: function (value, tr) {
                for (var i = 0; i < tr.effects.length; i++) {
                    if (tr.effects[i].is(SetDiag)) return buildDeco();
                }
                if (tr.docChanged) {
                    try {
                        return value.map(tr.changes);
                    } catch (e) {
                        return value;
                    }
                }
                return value;
            },
            provide: function (f) {
                return view.EditorView.decorations.from(f);
            }
        });

        var gutField = state.StateField.define({
            create: function () {
                return state.RangeSet.empty;
            },
            update: function (value, tr) {
                for (var i = 0; i < tr.effects.length; i++) {
                    if (tr.effects[i].is(SetDiag)) return buildGutterSet();
                }
                if (tr.docChanged) {
                    try {
                        return value.map(tr.changes);
                    } catch (e) {
                        return value;
                    }
                }
                return value;
            }
        });

        /* view.gutter() is the IMPERATIVE API. CM6 builds differ in whether it
returns a GutterView object (with .update) or an updater function, and
a signature mismatch must NEVER prevent the squiggle/lens field from
mounting — so assign the field first and guard the gutter call. */
        this._setDiagEffect = SetDiag;
        this._decoField = decoField;
        this._gutterUpdater = null;
        try {
            var gv = view.gutter(this.view, {
                class: 'm-diag-gutter-cm6',
                markers: gutField,
                renderEmptyElements: false
            });
            if (gv && typeof gv.update === 'function') {
                this._gutterUpdater = function (u) { try { gv.update(u); } catch (e) { } };
            } else if (typeof gv === 'function') {
                this._gutterUpdater = gv;
            }
        } catch (e) { /* gutter dots unavailable on this build — squiggles still render */ }
    };

    /** Rebuild diagnostics + lens decorations from this._diagData.
     *  The very first application also mounts the decoration field and the
     *  gutter into their dedicated compartment (kept empty until needed so
     *  files that never lint pay nothing). */
    CM6Adapter.prototype._applyDiag = function () {
        try {
            var self = this;
            this._ensureDiagState();
            if (!this._diagMounted) {
                this._diagMounted = true;
                var exts = [
                    this._decoField,
                    this.mod.view.EditorView.updateListener.of(function (u) {
                        try {
                            if (self._gutterUpdater) self._gutterUpdater(u);
                        } catch (e) { }
                    })];
                this.view.dispatch({
                    effects: this._c.diag.reconfigure(exts)
                });
                // Fields mounted fresh — fire the rebuild effect in a follow-up tick
                // so both fields see it after their initial create().
                setTimeout(function () {
                    try {
                        self.view.dispatch({
                            effects: self._setDiagEffect.of(Date.now())
                        });
                    } catch (e) { }
                }, 0);
                return;
            }
            this.view.dispatch({
                effects: this._setDiagEffect.of(Date.now())
            });
        } catch (e) { }
    };

    CM6Adapter.prototype.setDiagnostics = function (markers) {
        this._diagData.markers = Array.isArray(markers) ? markers : [];
        this._applyDiag();
    };
    CM6Adapter.prototype.clearDiagnostics = function () {
        this._diagData.markers = [];
        this._applyDiag();
    };
    CM6Adapter.prototype.setLens = function (entries) {
        this._diagData.lens = Array.isArray(entries) ? entries : [];
        this._applyDiag();
    };
    CM6Adapter.prototype.clearLens = function () {
        this._diagData.lens = [];
        this._applyDiag();
    };
    CM6Adapter.prototype.setLangId = function (id) {
        this._langId = (id == null) ? null : String(id);
    };
    CM6Adapter.prototype.getLangId = function () {
        return this._langId;
    };

    /* ═══════════════════════════════════════════════════════════════
  PUBLIC API
  ═══════════════════════════════════════════════════════════════ */

    /** Create an editor instance in `mountEl` using the resolved engine.
     *  `options` is the exact same CodeMirror-5-shaped config object
     *  mobile-editor.js already builds. Calls back with an object that
     *  exposes the full CM5-shaped API (real CM5 instance, or CM6Adapter). */
    function create(mountEl, options, onReady) {
        resolveEngine(function (engine) {
            if (engine === 'cm5') {
                createCM5(mountEl, options, function (cm) {
                    _activeEngine = 'cm5';
                    onReady(cm);
                });
                return;
            }
            createCM6(mountEl, options, function (adapter) {
                _activeEngine = 'cm6';
                onReady(adapter);
            }, function (err) {
                // CM6 failed to initialise — fall back to CM5 so the user never sees
                // a blank editor. ★ v18: latch in-memory only (this page load); the
                // next reload gets a fresh CM6 attempt instead of being pinned to CM5.
                _pageCm6Failed = true;
                if (window.console) console.warn('[editor-adapter] CM6 init failed, falling back to CM5:', err);
                createCM5(mountEl, options, function (cm) {
                    _activeEngine = 'cm5';
                    onReady(cm);
                });
            });
        });
    }

    /** Load (if needed) + apply the language for `mode` on `adapter`,
     *  then call `cb()`. Engine-aware: dispatches to the CM5 script
     *  loader or the CM6 dynamic-import loader as appropriate. */
    function ensureMode(adapter, mode, cb) {
        if (!adapter) {
            cb();
            return;
        }
        if (adapter.engine === 'cm6') {
            // Waits for the real dynamic import to resolve (de-duped/cached),
            // matching CM5's "load once, then let me re-apply" callback timing.
            adapter._waitForMode(mode, cb);
            return;
        }
        ensureCM5Mode(mode, cb);
    }

    function currentEngine() {
        return _activeEngine;
    }

    window.IDE.editorAdapter = {
        create: create,
        ensureMode: ensureMode,
        currentEngine: currentEngine
    };
})();