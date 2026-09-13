/**
 * ═══════════════════════════════════════════════════════════════════════════
 *  QUIRKY IDE — MOBILE VIRTUAL KEYBOARD TOOLBAR (Phase 3 · Task 3.2 + 4.3)
 *  v6 — HOTFIX C-1 + C-2
 * ═══════════════════════════════════════════════════════════════════════════
 *
 *  A horizontal strip of special keys that sits directly above the mobile
 *  virtual keyboard (Termux-style). While the strip is visible, the bottom
 *  navigation bar hides (body.m-kb-open) so the helpers take its space.
 *
 *  v6 changes:
 *    • HOTFIX C-1: the app layout now gets a bottom padding equal to the
 *      toolbar height (CSS var --mkb-h), so the terminal input line is no
 *      longer hidden BEHIND the helper strip when the keyboard is open.
 *      The rule is injected here AND lives in mobile-base.css.
 *    • HOTFIX C-2: terminal key set expanded — ← → arrows, Ctrl/Alt
 *      modifiers, a scrollable row of shell characters, and readline-style
 *      Ctrl+letter shortcuts (A/E/U/K/W/D) for the command input.
 *
 *  EXPOSES: window.IDE.mobileKeyboard = { show, hide, isVisible, setEditor, setMode, getMode, version }
 * ═══════════════════════════════════════════════════════════════════════════
 */
(function () {
    'use strict';

    var VERSION = 'v7';
    var U = window.IDE && window.IDE.utils;
    var toolbarEl = null;
    var cmInstance = null;
    var keyboardVisible = false;
    var ctrlActive = false;
    var altActive = false;
    var positionRafId = null;
    var lastKbInset = 0;          // HOTFIX D-3: last measured keyboard height (CSS px)
    var toolbarH = 54;            // cached strip height (no layout reads while typing)
    var lastKbDiff = -1;          // only reposition when the measured height changes
    var currentMode = 'editor'; // 'editor' or 'terminal'
    var keyStyle = 'raw';       // 'raw' or 'combined'
    var _ideClipboard = '';     // in-app clipboard fallback (system clipboard can be blocked in WebViews)

    /* ═══════════════════════════════════════════════════════════
       KEY DEFINITIONS — TWO SEPARATE SETS
       ═══════════════════════════════════════════════════════════ */

    /* ── Editor keys (Task 3.2 — coding keys) ── */
    var EDITOR_KEYS = [
        { label: '💡', action: 'suggest', type: 'action' },
        { label: '🏗️', action: 'boilerplate', type: 'action' },
        { label: 'Tab', action: 'tab', type: 'action' },
        { label: 'Esc', action: 'escape', type: 'action' },
        { label: '←', action: 'left', type: 'action' },
        { label: '→', action: 'right', type: 'action' },
        { label: '↑', action: 'up', type: 'action' },
        { label: '↓', action: 'down', type: 'action' },
        { label: 'Ctrl', action: 'ctrl', type: 'modifier' },
        { label: 'Alt', action: 'alt', type: 'modifier' },
        { label: '{', action: '{', type: 'char' },
        { label: '}', action: '}', type: 'char' },
        { label: '(', action: '(', type: 'char' },
        { label: ')', action: ')', type: 'char' },
        { label: ';', action: ';', type: 'char' },
        { label: '"', action: '"', type: 'char' },
        { label: "'", action: "'", type: 'char' },
        { label: '/', action: '/', type: 'char' },
        { label: '<', action: '<', type: 'char' },
        { label: '>', action: '>', type: 'char' }
    ];

    /* ── Terminal keys (Task 4.3 + HOTFIX C-2 — full command keys) ──
       The strip scrolls horizontally, so a generous set is fine.   */
    var TERMINAL_KEYS = [
        { label: 'Ctrl+C', action: 'term-ctrlc', type: 'terminal' },
        { label: 'Ctrl+L', action: 'term-ctrll', type: 'terminal' },
        { label: '↑', action: 'term-up', type: 'terminal' },
        { label: '↓', action: 'term-down', type: 'terminal' },
        { label: '←', action: 'term-left', type: 'terminal' },
        { label: '→', action: 'term-right', type: 'terminal' },
        { label: 'Tab', action: 'term-tab', type: 'terminal' },
        { label: 'Esc', action: 'term-esc', type: 'terminal' },
        { label: 'Enter', action: 'term-enter', type: 'terminal' },
        { label: 'Ctrl', action: 'ctrl', type: 'modifier' },
        { label: 'Alt', action: 'alt', type: 'modifier' },
        { label: '/', action: '/', type: 'char' },
        { label: '|', action: '|', type: 'char' },
        { label: '-', action: '-', type: 'char' },
        { label: '_', action: '_', type: 'char' },
        { label: '~', action: '~', type: 'char' },
        { label: '$', action: '$', type: 'char' },
        { label: '&', action: '&', type: 'char' },
        { label: '*', action: '*', type: 'char' },
        { label: ';', action: ';', type: 'char' },
        { label: '#', action: '#', type: 'char' },
        { label: '<', action: '<', type: 'char' },
        { label: '>', action: '>', type: 'char' },
        { label: '"', action: '"', type: 'char' },
        { label: "'", action: "'", type: 'char' },
        { label: '=', action: '=', type: 'char' },
        { label: '!', action: '!', type: 'char' },
        { label: '?', action: '?', type: 'char' },
        { label: '.', action: '.', type: 'char' },
        { label: ',', action: ',', type: 'char' },
        { label: '%', action: '%', type: 'char' },
        { label: '@', action: '@', type: 'char' },
        { label: '^', action: '^', type: 'char' },
        { label: '\\', action: '\\', type: 'char' },
        { label: ':', action: ':', type: 'char' },
        { label: '(', action: '(', type: 'char' },
        { label: ')', action: ')', type: 'char' },
        { label: '[', action: '[', type: 'char' },
        { label: ']', action: ']', type: 'char' },
        { label: '{', action: '{', type: 'char' },
        { label: '}', action: '}', type: 'char' }
    ];

    /* ── Combined shortcut keys — Editor mode ── */
    var EDITOR_KEYS_COMBINED = [
        { label: 'Ctrl+A', action: 'combo-selectall', type: 'combined' },
        { label: 'Ctrl+C', action: 'combo-copy', type: 'combined' },
        { label: 'Ctrl+V', action: 'combo-paste', type: 'combined' },
        { label: 'Ctrl+X', action: 'combo-cut', type: 'combined' },
        { label: 'Ctrl+Z', action: 'combo-undo', type: 'combined' },
        { label: 'Ctrl+Y', action: 'combo-redo', type: 'combined' },
        { label: 'Ctrl+S', action: 'combo-save', type: 'combined' },
        { label: 'Ctrl+F', action: 'combo-find', type: 'combined' },
        { label: 'Ctrl+/', action: 'combo-comment', type: 'combined' },
        { label: 'Ctrl+D', action: 'combo-selectword', type: 'combined' },
        { label: 'Ctrl+⇧+K', action: 'combo-deleteline', type: 'combined' },
        { label: 'Alt+↑', action: 'combo-moveup', type: 'combined' },
        { label: 'Alt+↓', action: 'combo-movedown', type: 'combined' },
        { label: 'Tab', action: 'tab', type: 'action' },
        { label: 'Esc', action: 'escape', type: 'action' }
    ];

    /* ── Combined shortcut keys — Terminal mode ── */
    var TERMINAL_KEYS_COMBINED = [
        { label: 'Ctrl+C', action: 'term-ctrlc', type: 'terminal' },
        { label: 'Ctrl+L', action: 'term-ctrll', type: 'terminal' },
        { label: 'Ctrl+A', action: 'tcombo-start', type: 'combined' },
        { label: 'Ctrl+E', action: 'tcombo-end', type: 'combined' },
        { label: 'Ctrl+U', action: 'tcombo-killleft', type: 'combined' },
        { label: 'Ctrl+K', action: 'tcombo-killright', type: 'combined' },
        { label: 'Ctrl+W', action: 'tcombo-delword', type: 'combined' },
        { label: 'Ctrl+D', action: 'tcombo-delchar', type: 'combined' },
        { label: '↑', action: 'term-up', type: 'terminal' },
        { label: '↓', action: 'term-down', type: 'terminal' },
        { label: 'Tab', action: 'term-tab', type: 'terminal' },
        { label: 'Esc', action: 'term-esc', type: 'terminal' },
        { label: 'Enter', action: 'term-enter', type: 'terminal' }
    ];

    /* ═══════════════════════════════════════════════════════════
       SELF-CONTAINED CSS (HOTFIX B-3 + C-1)
       While the helpers are visible, the bottom navigation / FAB /
       selection bar hide so the strip gets their space, AND the app
       layout gets a bottom padding equal to the strip's height so
       the terminal input line is never covered by the strip.
       Injected from JS so a stale CSS bundle can never break the fix.
       ═══════════════════════════════════════════════════════════ */
    function injectCss() {
        if (document.getElementById('mkb-extra-css')) return;
        var s = document.createElement('style');
        s.id = 'mkb-extra-css';
        s.textContent =
            'body.m-kb-open #mobile-tabbar,' +
            'body.m-kb-open .m-fab,' +
            'body.m-kb-open .m-selection-bar{display:none !important;}' +
            'body.m-kb-open #mobile-root{padding-bottom:var(--mkb-total,var(--mkb-h,54px));}' +
            'body.m-kb-open .m-ai-overlay,' +
            'body.m-kb-open .m-cmd-overlay,' +
            'body.m-kb-open .m-http-overlay{padding-bottom:var(--mkb-total,var(--mkb-h,54px));}' +
            'body.m-kb-open .m-sheet{bottom:var(--mkb-total,var(--mkb-h,54px));' +
            'max-height:calc(100vh - var(--mkb-total,var(--mkb-h,54px)));' +
            'max-height:calc(100dvh - var(--mkb-total,var(--mkb-h,54px)));}';
        document.head.appendChild(s);
    }

    /** HOTFIX C-1: measure the strip and publish its height as --mkb-h.
        HOTFIX D-3: also refresh the combined keyboard + strip inset. */
    function syncToolbarHeight() {
        if (!toolbarEl) return;
        var h = toolbarEl.offsetHeight || 54;
        toolbarH = h;
        try {
            document.documentElement.style.setProperty('--mkb-h', h + 'px');
        } catch (e) { }
        publishBottomInset(lastKbInset);
    }

    /** HOTFIX D-3: publish the keyboard height (--mkb-kb) and the combined
        keyboard + helper-strip inset (--mkb-total). The CSS uses --mkb-total
        as bottom padding so the terminal input line and the AI chat input
        are lifted ABOVE the keyboard instead of hiding behind it. */
    function publishBottomInset(kbPx) {
        var kb = Math.max(0, Math.round(kbPx || 0));
        /* HOTFIX E-1: only count the helper strip when it is actually on
        screen. While a sheet is lifted instead (strip hidden), the total
        inset is just the keyboard height — no phantom 54px gap. */
        var stripVisible = toolbarEl &&
            !toolbarEl.classList.contains('hidden') &&
            toolbarEl.offsetHeight > 0;
        var strip = stripVisible ? toolbarEl.offsetHeight : 0;
        try {
            document.documentElement.style.setProperty('--mkb-kb', kb + 'px');
            document.documentElement.style.setProperty('--mkb-total', (kb + strip) + 'px');
        } catch (e) { }
    }

    /* ═══════════════════════════════════════════════════════════
       BUILD / REBUILD THE TOOLBAR DOM
       ═══════════════════════════════════════════════════════════ */
    function buildToolbar() {
        if (!toolbarEl) {
            toolbarEl = document.createElement('div');
            toolbarEl.id = 'mobile-keyboard-toolbar';
            toolbarEl.className = 'mkb-toolbar hidden';

            /* Scrollable keys area (left) */
            var scrollWrap = document.createElement('div');
            scrollWrap.className = 'mkb-keys-scroll';
            scrollWrap.id = 'mkb-keys-scroll';
            toolbarEl.appendChild(scrollWrap);

            /* Fixed toggle button (right) */
            var toggleBtn = document.createElement('button');
            toggleBtn.className = 'mkb-mode-toggle';
            toggleBtn.id = 'mkb-mode-toggle';
            toggleBtn.setAttribute('aria-label', 'Switch key layout');
            toggleBtn.innerHTML = '<span class="mkb-toggle-icon">🔤</span><span class="mkb-toggle-label">RAW</span>';
            toggleBtn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                toggleKeyStyle();
            });
            toggleBtn.addEventListener('mousedown', function (e) { e.preventDefault(); });
            toggleBtn.addEventListener('touchstart', function (e) { e.stopPropagation(); }, { passive: true });
            toolbarEl.appendChild(toggleBtn);

            document.body.appendChild(toolbarEl);
        }
        rebuildKeys();
    }

    /** Rebuild the buttons inside the scroll area for the current mode + style. */
    function rebuildKeys() {
        if (!toolbarEl) return;
        var scrollWrap = document.getElementById('mkb-keys-scroll');
        if (!scrollWrap) return;
        scrollWrap.innerHTML = '';

        var keys;
        if (currentMode === 'terminal') {
            keys = (keyStyle === 'combined') ? TERMINAL_KEYS_COMBINED : TERMINAL_KEYS;
        } else {
            keys = (keyStyle === 'combined') ? EDITOR_KEYS_COMBINED : EDITOR_KEYS;
        }

        keys.forEach(function (key) {
            var btn = document.createElement('button');
            btn.className = 'mkb-btn';
            btn.setAttribute('data-action', key.action);
            btn.setAttribute('data-type', key.type);
            btn.textContent = key.label;
            btn.setAttribute('aria-label', key.label);
            if (key.type === 'modifier') {
                btn.classList.add('mkb-modifier');
            }
            if (key.type === 'terminal') {
                btn.classList.add('mkb-terminal');
            }
            if (key.type === 'combined') {
                btn.classList.add('mkb-combined');
            }
            if (key.type === 'char' && currentMode === 'terminal') {
                btn.classList.add('mkb-terminal');
            }
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                handleKeyPress(key);
            });
            btn.addEventListener('mousedown', function (e) {
                e.preventDefault();
            });
            btn.addEventListener('touchstart', function (e) {
                e.stopPropagation();
            }, { passive: true });
            scrollWrap.appendChild(btn);
        });

        /* Update the toggle button label */
        updateToggleLabel();
    }

    /* ═══════════════════════════════════════════════════════════
       SET MODE — switch between Editor and Terminal key sets (Task 4.3)
       ═══════════════════════════════════════════════════════════ */
    function setMode(mode) {
        if (mode !== 'editor' && mode !== 'terminal') return;
        if (mode === currentMode) return;
        currentMode = mode;
        ctrlActive = false;
        altActive = false;
        rebuildKeys();
        syncToolbarHeight();
    }

    function getMode() {
        return currentMode;
    }

    /* ═══════════════════════════════════════════════════════════
       HANDLE KEY PRESS — routes to editor or terminal handler
       ═══════════════════════════════════════════════════════════ */
    function handleKeyPress(key) {
        /* Combined shortcut keys get their own router */
        if (key.type === 'combined') {
            handleCombinedKey(key);
            return;
        }
        if (currentMode === 'terminal') {
            handleTerminalKey(key);
        } else {
            handleEditorKey(key);
        }
    }

    /* ── Toggle between Raw and Combined key layouts ── */
    function toggleKeyStyle() {
        keyStyle = (keyStyle === 'raw') ? 'combined' : 'raw';
        ctrlActive = false;
        altActive = false;
        rebuildKeys();
        syncToolbarHeight();
        if (U && U.toast) {
            U.toast(keyStyle === 'combined' ? '⚡ Combined shortcuts' : '🔤 Raw keys');
        }
    }

    function updateToggleLabel() {
        var btn = document.getElementById('mkb-mode-toggle');
        if (!btn) return;
        var icon = btn.querySelector('.mkb-toggle-icon');
        var label = btn.querySelector('.mkb-toggle-label');
        if (keyStyle === 'combined') {
            if (icon) icon.textContent = '⚡';
            if (label) label.textContent = 'COMBO';
        } else {
            if (icon) icon.textContent = '🔤';
            if (label) label.textContent = 'RAW';
        }
    }

    /* ── Combined key handler — routes to the right action ── */
    function handleCombinedKey(key) {
        if (currentMode === 'terminal') {
            handleTerminalCombined(key);
        } else {
            handleEditorCombined(key);
        }
    }

    function handleEditorCombined(key) {
        if (!cmInstance) return;
        switch (key.action) {
            case 'combo-selectall':
                cmInstance.execCommand('selectAll');
                break;
            case 'combo-copy':
                editorCopySelection(false);
                break;
            case 'combo-cut':
                editorCopySelection(true);
                break;
            case 'combo-paste':
                editorPaste();
                break;
            case 'combo-undo':
                cmInstance.execCommand('undo');
                break;
            case 'combo-redo':
                cmInstance.execCommand('redo');
                break;
            case 'combo-save':
                mobileSave();
                break;
            case 'combo-find':
                mobileFind();
                break;
            case 'combo-comment':
                cmInstance.execCommand('toggleComment');
                break;
            case 'combo-selectword':
                cmInstance.execCommand('selectWord');
                break;
            case 'combo-deleteline':
                cmInstance.execCommand('deleteLine');
                break;
            case 'combo-moveup':
                cmInstance.execCommand('swapLineUp');
                break;
            case 'combo-movedown':
                cmInstance.execCommand('swapLineDown');
                break;
        }
        cmInstance.focus();
    }

    function handleTerminalCombined(key) {
        var t = document.getElementById('m-term-input');
        if (!t) return;
        switch (key.action) {
            case 'tcombo-start':
                termCtrlChar('a');
                break;
            case 'tcombo-end':
                termCtrlChar('e');
                break;
            case 'tcombo-killleft':
                termCtrlChar('u');
                break;
            case 'tcombo-killright':
                termCtrlChar('k');
                break;
            case 'tcombo-delword':
                termCtrlChar('w');
                break;
            case 'tcombo-delchar':
                termCtrlChar('d');
                break;
        }
        t.focus();
    }

    /* ── Editor key handler (Task 3.2) ── */
    function handleEditorKey(key) {
        if (!cmInstance) return;
        var type = key.type;
        var action = key.action;
        if (type === 'modifier') {
            if (action === 'ctrl') {
                ctrlActive = !ctrlActive;
                updateModifierUI();
            } else if (action === 'alt') {
                altActive = !altActive;
                updateModifierUI();
            }
            return;
        }
        if (type === 'char') {
            if (ctrlActive || altActive) {
                handleModifiedChar(action);
            } else {
                insertChar(action);
            }
            resetModifiers();
            return;
        }
        if (type === 'action') {
            performEditorAction(action);
            resetModifiers();
            return;
        }
    }

    function insertChar(ch) {
        if (!cmInstance) return;
        cmInstance.replaceSelection(ch);
        cmInstance.focus();
        settleEditorInput();
    }
    /* ── v15.1 IME-safe programmatic inserts ─────────────────────────
After button-driven inserts (Tab, char keys) re-pin the caret on
the next tick so the soft keyboard rebuilds its composing region
from the truth. Without this, your next keystroke makes the
keyboard rewrite the line from a stale bubble and eat the indent
or the letter ("Tab then type → indent vanishes"). */
    /* ★ v16 FIX — IME HARD RESET ("Tab then type → indent vanishes"):
    Re-pinning the caret alone (v15.1) was NOT enough on real devices:
    the WebView's IME bridge keeps its OWN cached copy of the line, and
    a programmatic insert (TAB indent, toolbar char, snippet pick) never
    invalidates that cache. The next soft-keyboard keystroke then makes
    the keyboard rewrite the line from its stale copy — deleting the
    indent. The only reliable way to force the bridge to discard its
    cache is to BLUR the editor input for a moment (this ends the
    keyboard's hidden composing session against the old text), then
    refocus + restore the caret. Done only inside the Android app
    (bridge objects present); desktop browsers never need it. */
    function imeHardReset(cm) {
        try {
            var inApp = !!(window.QuirkyIme || window.QuirkyStorage ||
                window.QuirkyFiles || window.AndroidHardware);
            if (!inApp) return false;
            var wrap = null;
            try {
                if (cm.getWrapperElement) wrap = cm.getWrapperElement();
                else if (cm.view && cm.view.dom) wrap = cm.view.dom;
            } catch (e) { wrap = null; }
            var ae = document.activeElement;
            if (!wrap || !ae || ae === document.body || !wrap.contains(ae)) return false;
            ae.blur();
            return true;
        } catch (e) { return false; }
    }
    function settleEditorInput() {
        if (!cmInstance) return;
        var cm = cmInstance;
        var blurred = imeHardReset(cm);
        setTimeout(function () {
            try {
                var cur = cm.getCursor();
                cm.focus();
                if (blurred) {
                    /* ★ v18 FIX: same cure as autocomplete.js settleCaret() — the two
                       forced caret moves now run back-to-back in the same tick so the
                       intermediate column-1 caret is never painted (no more cursor
                       teleport), while the IME bridge still gets its selection resync. */
                    cm.setCursor({ line: cur.line, ch: 0 });
                    cm.setCursor({ line: cur.line, ch: cur.ch });
                } else {
                    cm.setCursor(cur);
                }
            } catch (e) { }
        }, blurred ? 90 : 0);
    }
    function handleModifiedChar(ch) {
        if (!cmInstance) return;
        var handled = false;
        if (ctrlActive && ch === 'z') { cmInstance.execCommand('undo'); handled = true; }
        else if (ctrlActive && ch === 'y') { cmInstance.execCommand('redo'); handled = true; }
        else if (ctrlActive && ch === 's') { mobileSave(); handled = true; }
        else if (ctrlActive && ch === 'f') { mobileFind(); handled = true; }
        else if (ctrlActive && ch === 'a') { cmInstance.execCommand('selectAll'); handled = true; }
        if (!handled) {
            insertChar(ch);
        }
        cmInstance.focus();
    }

    /* ── Real clipboard support for Combined keys (Ctrl+C / Ctrl+X / Ctrl+V) ──
       Copy/Cut: read the selection straight out of CodeMirror (the browser's
       own copy does nothing inside the editor), write it to the system
       clipboard, and keep an in-app copy as a guaranteed fallback.
       Paste: try the system clipboard first, then legacy paste, then the
       in-app clipboard, and finally show a helpful toast. Never silent. */
    function editorCopySelection(isCut) {
        if (!cmInstance) return;
        var text = cmInstance.getSelection();
        if (!text) {
            if (U && U.toast) U.toast(isCut ? '✂️ Nothing selected' : '📋 Nothing selected');
            cmInstance.focus();
            return;
        }
        _ideClipboard = text; // in-app fallback always works
        writeSystemClipboard(text).then(function (ok) {
            if (isCut) {
                cmInstance.replaceSelection('');
                if (U && U.toast) U.toast('✂️ Cut' + (ok ? '' : ' (in-app only)'));
            } else {
                if (U && U.toast) U.toast('📋 Copied' + (ok ? '' : ' (in-app only)'));
            }
            cmInstance.focus();
        });
    }
    function writeSystemClipboard(text) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            return navigator.clipboard.writeText(text).then(
                function () { return true; },
                function () { return legacyCopy(text); }
            );
        }
        return Promise.resolve(legacyCopy(text));
    }
    function legacyCopy(text) {
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.setAttribute('readonly', '');
        ta.style.position = 'fixed';
        ta.style.top = '-1000px';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.focus();
        ta.select();
        var ok = false;
        try { ok = document.execCommand('copy'); } catch (e) { ok = false; }
        document.body.removeChild(ta);
        return ok;
    }
    function editorPaste() {
        if (!cmInstance) return;
        cmInstance.focus();
        var done = function (text, source) {
            if (!text) return;
            cmInstance.replaceSelection(text);
            cmInstance.focus();
            if (U && U.toast) U.toast('📥 Pasted' + (source ? ' (' + source + ')' : ''));
        };
        if (navigator.clipboard && navigator.clipboard.readText) {
            navigator.clipboard.readText().then(function (text) {
                if (text) done(text, '');
                else if (_ideClipboard) done(_ideClipboard, 'in-app');
                else if (U && U.toast) U.toast('⚠ Clipboard is empty');
            }).catch(function () {
                fallbackPaste(done);
            });
        } else {
            fallbackPaste(done);
        }
    }
    function fallbackPaste(done) {
        /* 1) legacy browser paste (works in some WebViews) */
        var ta = document.createElement('textarea');
        ta.setAttribute('readonly', '');
        ta.style.position = 'fixed';
        ta.style.top = '-1000px';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.focus();
        var ok = false;
        try { ok = document.execCommand('paste'); } catch (e) { ok = false; }
        var text = ok ? ta.value : '';
        document.body.removeChild(ta);
        if (text) { done(text, ''); return; }
        /* 2) whatever you copied earlier with Ctrl+C / Ctrl+X inside the IDE */
        if (_ideClipboard) { done(_ideClipboard, 'in-app'); return; }
        /* 3) nothing worked — tell the user instead of staying silent */
        if (U && U.toast) U.toast('⚠ Browser blocked paste — long-press in the editor and choose Paste', 'warning', 4000);
        if (cmInstance) cmInstance.focus();
    }

    function performEditorAction(action) {
        if (!cmInstance) return;
        switch (action) {
            case 'tab':
                if (ctrlActive) {
                    cmInstance.execCommand('indentMore');
                    settleEditorInput(true);   /* ★ hard IME reset — indent survives the next keystroke */
                } else {
                    var tabKey = cmInstance.getOption('extraKeys');
                    if (tabKey && tabKey['Tab']) {
                        /* autocomplete.js owns this path; its doIndent() now ends
                           with hardResetIme(), so we must NOT settle here too —
                           a second early focus would cancel the IME reset. */
                        tabKey['Tab'](cmInstance);
                    } else {
                        cmInstance.execCommand('insertTab');
                        settleEditorInput(true);   /* ★ hard IME reset */
                    }
                }
                break;
            case 'escape':
                var dialogs = document.querySelectorAll('.CodeMirror-dialog');
                if (dialogs.length > 0) {
                    dialogs.forEach(function (d) {
                        var closeBtn = d.querySelector('button');
                        if (closeBtn) closeBtn.click();
                    });
                } else {
                    var cur = cmInstance.getCursor();
                    cmInstance.setCursor(cur);
                }
                break;
            case 'boilerplate':
                /* 🏗️ — v16: manual BOILERPLATE trigger (full-file starters only).
                   Snippets stay on the 💡 key; this one lists scaffolds like
                   html5, css reset, bash strict header, PHP class file… */
                try {
                    if (window.IDE && window.IDE.autocomplete &&
                        typeof window.IDE.autocomplete.triggerBoilerplates === 'function') {
                        window.IDE.autocomplete.triggerBoilerplates();
                    } else if (U && U.toast) {
                        U.toast('⚠ Autocomplete module not loaded', 'warning');
                    }
                } catch (e) { /* a dead scaffold engine must not break the bar */ }
                break;
            case 'suggest':
                /* ✨ — manual completion trigger (v10 intel stack). Prefer
                   the autocomplete module (knows cursor-language + pools),
                   fall back to the raw facade popup, then to show-hint. */
                try {
                    if (window.IDE && window.IDE.autocomplete &&
                        typeof window.IDE.autocomplete.trigger === 'function') {
                        window.IDE.autocomplete.trigger();
                    } else if (typeof cmInstance.openCompletion === 'function') {
                        cmInstance.openCompletion();
                    } else if (window.CodeMirror && window.CodeMirror.showHint) {
                        cmInstance.showHint({ completeSingle: false });
                    }
                } catch (e) { /* a dead suggestion engine must not break the bar */ }
                break;
            case 'left':
                cmInstance.execCommand(ctrlActive || altActive ? 'goWordLeft' : 'goCharLeft');
                break;
            case 'right':
                cmInstance.execCommand(ctrlActive || altActive ? 'goWordRight' : 'goCharRight');
                break;
            case 'up':
                cmInstance.execCommand(ctrlActive ? 'goDocStart' : 'goLineUp');
                break;
            case 'down':
                cmInstance.execCommand(ctrlActive ? 'goDocEnd' : 'goLineDown');
                break;
        }
        cmInstance.focus();
    }

    function mobileSave() {
        var saveBtn = document.getElementById('mh-save');
        if (saveBtn) saveBtn.click();
    }

    function mobileFind() {
        if (cmInstance && typeof cmInstance.execCommand === 'function') {
            try {
                cmInstance.execCommand('find');
            } catch (e) {
                if (U && U.toast) U.toast('Search not available');
            }
        }
    }

    function resetModifiers() {
        ctrlActive = false;
        altActive = false;
        updateModifierUI();
    }

    function updateModifierUI() {
        if (!toolbarEl) return;
        var ctrlBtn = toolbarEl.querySelector('[data-action="ctrl"]');
        var altBtn = toolbarEl.querySelector('[data-action="alt"]');
        if (ctrlBtn) ctrlBtn.classList.toggle('active', ctrlActive);
        if (altBtn) altBtn.classList.toggle('active', altActive);
    }

    /* ── Terminal key handler (Task 4.3 + HOTFIX C-2) ── */
    function handleTerminalKey(key) {
        var termInput = document.getElementById('m-term-input');
        var api = window.IDE && window.IDE.api;
        var cfg = window.IDE_CONFIG || {};
        if (!(cfg.features && cfg.features.terminal)) return;

        /* ── Modifier keys (Ctrl / Alt) ── */
        if (key.type === 'modifier') {
            if (key.action === 'ctrl') {
                ctrlActive = !ctrlActive;
                updateModifierUI();
            } else if (key.action === 'alt') {
                altActive = !altActive;
                updateModifierUI();
            }
            return;
        }

        /* ── Character keys: insert, readline shortcut, or raw control ── */
        if (key.type === 'char') {
            var MTc = window.IDE && window.IDE.mobileTerminal;
            var rawOn = MTc && MTc.isRunning && MTc.isRunning() && MTc.isPty && MTc.isPty();
            var sendRawEarly = MTc && MTc.sendStdinRaw;
            if (rawOn && sendRawEarly) {
                /* While a PTY program runs, chars go TO THE PROGRAM; the Ctrl /
                   Alt toggles turn them into real control / escape bytes. */
                if (ctrlActive) {
                    var cc = ctrlCodeOf(key.action);
                    sendRawEarly(cc !== null ? cc : key.action);
                } else if (altActive) {
                    sendRawEarly('\x1b' + key.action);
                } else {
                    sendRawEarly(key.action);
                }
            } else if (ctrlActive) {
                termCtrlChar(key.action);
            } else {
                termInsertChar(key.action);
            }
            resetModifiers();
            return;
        }

        /* ── Fixed-action keys ── */
        // ★ CONTEXT-AWARE: If a command is currently running, arrow keys and 
        // special keys send ANSI escape sequences to the running process's 
        // stdin (via the PTY). If idle, they navigate shell history / input.
        var isRunning = window.IDE.mobileTerminal && window.IDE.mobileTerminal.isRunning && window.IDE.mobileTerminal.isRunning();
        var sendRaw = window.IDE.mobileTerminal && window.IDE.mobileTerminal.sendStdinRaw;

        switch (key.action) {
            case 'term-ctrlc':
                if (api && api.terminal) {
                    api.terminal.cancel().catch(function () { });
                }
                if (termInput) {
                    termInput.value = '';
                    termInput.focus();
                }
                if (U && U.toast) U.toast('Cancelled');
                break;
            case 'term-ctrll':
                clearTerminalOutput();
                break;
            case 'term-up':
                if (isRunning && sendRaw) sendRaw('\x1b[A');
                else terminalHistoryUp();
                break;
            case 'term-down':
                if (isRunning && sendRaw) sendRaw('\x1b[B');
                else terminalHistoryDown();
                break;
            case 'term-left':
                if (isRunning && sendRaw) sendRaw('\x1b[D');
                else termMoveCursor(-1);
                break;
            case 'term-right':
                if (isRunning && sendRaw) sendRaw('\x1b[C');
                else termMoveCursor(1);
                break;
            case 'term-tab':
                if (isRunning && sendRaw) {
                    sendRaw('\t');
                } else if (termInput) {
                    var start = termInput.selectionStart;
                    var end = termInput.selectionEnd;
                    var val = termInput.value;
                    termInput.value = val.substring(0, start) + '  ' + val.substring(end);
                    termInput.setSelectionRange(start + 2, start + 2);
                    termInput.focus();
                }
                break;
            case 'term-esc':
                if (isRunning && sendRaw) {
                    sendRaw('\x1b');
                } else if (termInput) {
                    termInput.value = '';
                    termInput.focus();
                }
                break;
            case 'term-enter':
                var MTe = window.IDE.mobileTerminal;
                if (isRunning && MTe && MTe.isPty && MTe.isPty() && sendRaw) {
                    // PTY: a real Enter keypress = carriage return (\r).
                    // packer.py reads \r as ENTER; nano inserts a newline.
                    sendRaw('\r');
                } else if (isRunning && MTe && MTe.sendLine) {
                    // Pipe mode: send the typed line + newline.
                    MTe.sendLine();
                } else {
                    // Nothing is running -> submit the typed command, exactly like
                    // tapping the ➤ send button does.
                    var enterSendBtn = document.getElementById('mterm-send');
                    if (enterSendBtn) enterSendBtn.click();
                    else if (termInput) termInput.focus();
                }
                break;
        }
    }

    /** ★ Phase T-PTY-4: map a character to the byte Ctrl+<char> produces
    (Ctrl+X = \x18, Ctrl+C = \x03, ...). Null = no Ctrl mapping. */
    function ctrlCodeOf(ch) {
        var c = (ch || '').charCodeAt(0);
        if (c >= 65 && c <= 90) return String.fromCharCode(c - 64);
        if (c >= 97 && c <= 122) return String.fromCharCode(c - 96);
        var sym = {
            '@': '\x00', ' ': '\x00', '[': '\x1b', '\\': '\x1c',
            ']': '\x1d', '^': '\x1e', '_': '\x1f', '?': '\x7f',
            '/': '\x1f', '-': '\x1f'
        };
        return (ch in sym) ? sym[ch] : null;
    }
    /** HOTFIX C-2: insert a character at the cursor of the command input. */
    function termInsertChar(ch) {
        var t = document.getElementById('m-term-input');
        if (!t) return;
        var s = (t.selectionStart == null) ? t.value.length : t.selectionStart;
        var e = (t.selectionEnd == null) ? t.value.length : t.selectionEnd;
        t.value = t.value.substring(0, s) + ch + t.value.substring(e);
        var pos = s + ch.length;
        t.setSelectionRange(pos, pos);
        t.focus();
    }

    /** HOTFIX C-2: move the cursor inside the command input. */
    function termMoveCursor(delta) {
        var t = document.getElementById('m-term-input');
        if (!t) return;
        var pos = ((t.selectionStart == null) ? t.value.length : t.selectionStart) + delta;
        pos = Math.max(0, Math.min(t.value.length, pos));
        t.setSelectionRange(pos, pos);
        t.focus();
    }

    /** HOTFIX C-2: readline-style Ctrl+letter shortcuts for the input. */
    function termCtrlChar(ch) {
        var t = document.getElementById('m-term-input');
        if (!t) return;
        var v = t.value;
        var s = (t.selectionStart == null) ? v.length : t.selectionStart;
        var e = (t.selectionEnd == null) ? v.length : t.selectionEnd;
        if (ch === 'a') {
            t.setSelectionRange(0, 0);                      // start of line
        } else if (ch === 'e') {
            t.setSelectionRange(v.length, v.length);        // end of line
        } else if (ch === 'u') {
            t.value = v.slice(e);                           // kill left side
            t.setSelectionRange(0, 0);
        } else if (ch === 'k') {
            t.value = v.slice(0, s);                        // kill right side
            t.setSelectionRange(s, s);
        } else if (ch === 'w') {
            var before = v.slice(0, s);
            var m = before.match(/\S+\s*$/);
            var cut = m ? m[0].length : 0;
            t.value = before.slice(0, before.length - cut) + v.slice(e);
            var p = Math.max(0, s - cut);
            t.setSelectionRange(p, p);                      // delete word
        } else if (ch === 'd') {
            t.value = v.slice(0, s) + v.slice(e);           // delete char
            t.setSelectionRange(s, s);
        } else {
            termInsertChar(ch);
        }
        t.focus();
    }

    /** Clear the terminal output area. */
    function clearTerminalOutput() {
        var output = document.getElementById('m-term-output');
        if (output) {
            output.innerHTML = '<div class="m-term-info">— terminal cleared —</div>';
            output.scrollTop = output.scrollHeight;
        }
    }

    /** Navigate terminal history up. */
    function terminalHistoryUp() {
        var termInput = document.getElementById('m-term-input');
        if (!termInput) return;
        var LS_HIST = 'quirky.ide.mobile.terminal.history';
        try {
            var raw = localStorage.getItem(LS_HIST);
            var hist = raw ? JSON.parse(raw) : [];
            if (!Array.isArray(hist) || hist.length === 0) return;
            var idx = termInput.getAttribute('data-hist-idx');
            if (idx === null || idx === '') {
                termInput.setAttribute('data-hist-buffer', termInput.value);
                idx = hist.length - 1;
            } else {
                idx = parseInt(idx, 10);
                if (idx > 0) idx--;
            }
            termInput.setAttribute('data-hist-idx', String(idx));
            termInput.value = hist[idx];
        } catch (e) { }
    }

    /** Navigate terminal history down. */
    function terminalHistoryDown() {
        var termInput = document.getElementById('m-term-input');
        if (!termInput) return;
        var LS_HIST = 'quirky.ide.mobile.terminal.history';
        try {
            var raw = localStorage.getItem(LS_HIST);
            var hist = raw ? JSON.parse(raw) : [];
            if (!Array.isArray(hist) || hist.length === 0) return;
            var idx = termInput.getAttribute('data-hist-idx');
            if (idx === null || idx === '') return;
            idx = parseInt(idx, 10) + 1;
            if (idx >= hist.length) {
                termInput.removeAttribute('data-hist-idx');
                termInput.value = termInput.getAttribute('data-hist-buffer') || '';
                termInput.removeAttribute('data-hist-buffer');
            } else {
                termInput.setAttribute('data-hist-idx', String(idx));
                termInput.value = hist[idx];
            }
        } catch (e) { }
    }

    /* ═══════════════════════════════════════════════════════════
       KEYBOARD VISIBILITY — v5 EVENT-PROOF ENGINE (kept from v5)
       ═══════════════════════════════════════════════════════════ */
    var KEYBOARD_THRESHOLD = 120;
    var maxViewportHeight = 0;

    function currentViewportHeight() {
        if (window.visualViewport) return window.visualViewport.height;
        return window.innerHeight;
    }

    function initViewportTracking() {
        maxViewportHeight = currentViewportHeight();
        if (window.visualViewport) {
            window.visualViewport.addEventListener('resize', onViewportChange);
            window.visualViewport.addEventListener('scroll', onViewportChange);
        }
        window.addEventListener('resize', onViewportChange);
        window.addEventListener('orientationchange', function () {
            setTimeout(function () {
                maxViewportHeight = currentViewportHeight();
                checkKeyboard();
                if (keyboardVisible) syncToolbarHeight();
            }, 600);
        });
        // ★ THE HEARTBEAT — never relies on events alone.
        setInterval(checkKeyboard, 300);
    }

    function onViewportChange() {
        var h = currentViewportHeight();
        if (h > maxViewportHeight) maxViewportHeight = h;
        checkKeyboard();
        schedulePositionUpdate();
    }

    function checkKeyboard() {
        var nativeKb = nativeImeHeightCss();
        var diff;
        if (nativeKb !== null) {
            diff = nativeKb;
        } else {
            var h = currentViewportHeight();
            if (h > maxViewportHeight) maxViewportHeight = h;
            diff = maxViewportHeight - h;
        }
        if (diff > KEYBOARD_THRESHOLD) {
            showToolbar();
            refreshToolbarVisibility(); /* HOTFIX E-1: strip vs sheet lift */
        } else if (keyboardVisible && diff < 60) {
            hideToolbar();
        }
        /* Only reposition when the measured height actually changed —
        no forced layout/reflow while the user is typing. */
        if (keyboardVisible && Math.abs(diff - lastKbDiff) > 2) {
            lastKbDiff = diff;
            schedulePositionUpdate();
        }
    }

    /* ── Focus triggers (instant show) ── */
    function isRelevantFocus(el) {
        if (!el || !el.closest) return false;
        if (el.id === 'm-term-input') return true;
        if (el.closest('#m-editor-mount')) return true;
        if (el.closest('#m-find-bar')) return true;
        if (el.closest('#m-ai-overlay')) return true;   /* HOTFIX D-3: AI chat input */
        return false;
    }

    function initFocusTracking() {
        document.addEventListener('focusin', function (e) {
            var el = e.target;
            if (el.id === 'm-term-input') {
                setMode('terminal');
                showToolbar();
            } else if (el.closest && el.closest('#m-editor-mount')) {
                setMode('editor');
                showToolbar();
            } else if (el.closest && el.closest('#m-ai-overlay')) {
                /* HOTFIX D-3: the AI chat input gets the helper strip too */
                setMode('editor');
                showToolbar();
            } else if (isRelevantFocus(el)) {
                showToolbar();
            }
        });
        document.addEventListener('focusout', function () {
            setTimeout(checkKeyboard, 400);
        });
    }

    /* ═══════════════════════════════════════════════════════════
       POSITIONING — toolbar bottom aligns with keyboard top
       ═══════════════════════════════════════════════════════════ */
    var _posLoopId = null;

    /**
     * HOTFIX D-2 (JS side): read the *exact* keyboard height from the native
     * Android bridge (MainActivity's QuirkyIme.getImeHeight(), in physical
     * pixels) and convert to CSS px via devicePixelRatio.
     *   number >= 0 -> bridge present, this is the real IME height
     *   null        -> bridge not present -> caller falls back to visualViewport
     */
    function nativeImeHeightCss() {
        try {
            if (window.QuirkyIme && typeof window.QuirkyIme.getImeHeight === 'function') {
                var px = window.QuirkyIme.getImeHeight();
                if (typeof px === 'number' && isFinite(px) && px >= 0) {
                    var dpr = window.devicePixelRatio || 1;
                    return px / dpr;
                }
            }
        } catch (e) { }
        return null;
    }

    function keyboardHeightPx() {
        if (!window.visualViewport) return 0;
        var vv = window.visualViewport;
        var kb = window.innerHeight - (vv.offsetTop + vv.height);
        return kb > 0 ? Math.round(kb) : 0;
    }

    function schedulePositionUpdate() {
        if (positionRafId) return;
        positionRafId = requestAnimationFrame(function () {
            positionRafId = null;
            positionToolbar();
        });
        startStabilizer();
    }

    function positionToolbar() {
        if (!toolbarEl || !keyboardVisible) return;

        var nativeKb = nativeImeHeightCss();
        var kbInset = 0;

        if (nativeKb !== null) {
            // Exact keyboard height from Android — no drift, so the strip's
            // bottom edge lands exactly on the keyboard's top edge instead
            // of sinking a few px behind it.
            kbInset = Math.max(0, Math.round(nativeKb));
            toolbarEl.style.top = 'auto';
            toolbarEl.style.bottom = kbInset + 'px';
        } else if (window.visualViewport) {
            var vv = window.visualViewport;
            var visibleBottom = vv.offsetTop + vv.height;
            var h = toolbarH || 52;
            /* HOTFIX D-3: the gap between the layout bottom and the visible
               bottom IS the keyboard height (the window no longer resizes
               on modern Android, so we must measure the gap ourselves). */
            kbInset = Math.max(0, Math.round(window.innerHeight - visibleBottom));
            toolbarEl.style.bottom = 'auto';
            toolbarEl.style.top = Math.max(0, Math.round(visibleBottom - h)) + 'px';
        } else {
            kbInset = keyboardHeightPx();
            toolbarEl.style.top = 'auto';
            toolbarEl.style.bottom = kbInset + 'px';
        }

        toolbarEl.style.left = '0';
        toolbarEl.style.width = '100%';

        /* HOTFIX D-3: tell the CSS how much bottom space the keyboard +
           this strip need, so the input lines are lifted above both. */
        lastKbInset = kbInset;
        publishBottomInset(kbInset);
    }

    function startStabilizer() {
        if (_posLoopId !== null || !keyboardVisible) return;
        var lastKb = -1;
        var stable = 0;
        var started = Date.now();
        function tick() {
            _posLoopId = null;
            if (!keyboardVisible) return;
            positionToolbar();
            var nativeKb = nativeImeHeightCss();
            var kb = (nativeKb !== null) ? Math.round(nativeKb) : keyboardHeightPx();
            stable = (kb === lastKb) ? stable + 1 : 0;
            lastKb = kb;
            if (stable >= 5 || (Date.now() - started) > 1500) return;
            _posLoopId = requestAnimationFrame(tick);
        }
        _posLoopId = requestAnimationFrame(tick);
    }

    function stopStabilizer() {
        if (_posLoopId !== null) {
            cancelAnimationFrame(_posLoopId);
            _posLoopId = null;
        }
    }

    /* ═══════════════════════════════════════════════════════════
       SHOW / HIDE
       ═══════════════════════════════════════════════════════════ */
    /* HOTFIX E-1: returns the open bottom sheet that contains the focused
        element (New File / New Folder / Rename / New Project inputs), or null. */
    function focusedSheet() {
        var ae = document.activeElement;
        if (!ae || !ae.closest) return null;
        var sheet = ae.closest('.m-sheet');
        if (sheet && sheet.classList.contains('show')) return sheet;
        return null;
    }

    /* HOTFIX E-1: while the keyboard is open, decide WHAT gets the space:
    a focused sheet input → the sheet is lifted and the coding helper
    strip stays hidden; otherwise the strip is shown as before. */
    function refreshToolbarVisibility() {
        if (!keyboardVisible || !toolbarEl) return;
        if (focusedSheet()) {
            toolbarEl.classList.add('hidden');
        } else {
            toolbarEl.classList.remove('hidden');
        }
        publishBottomInset(lastKbInset); /* recompute --mkb-total */
    }

    function showToolbar() {
        if (!toolbarEl) buildToolbar();
        if (keyboardVisible) return;
        // Only show if the editor, terminal panel, or AI overlay is visible
        var editorWrap = document.getElementById('m-editor-wrap');
        var termInput = document.getElementById('m-term-input');
        var aiOverlayEl = document.getElementById('m-ai-overlay');
        var editorVisible = editorWrap && !editorWrap.classList.contains('hidden');
        var terminalVisible = termInput && !termInput.closest('.m-panel').classList.contains('hidden');
        /* HOTFIX D-3: the AI overlay covers the panels behind it, so treat
        "AI overlay open" as visible even when editor/terminal are hidden */
        var aiVisible = aiOverlayEl &&
            !aiOverlayEl.classList.contains('hidden') &&
            aiOverlayEl.classList.contains('show');
        /* HOTFIX E-1: a focused input inside an open bottom sheet
        (New File / New Folder / Rename / New Project) also counts. */
        var sheetFocus = !!focusedSheet();
        if (!editorVisible && !terminalVisible && !aiVisible && !sheetFocus) return;
        keyboardVisible = true;
        document.body.classList.add('m-kb-open');
        refreshToolbarVisibility();
        requestAnimationFrame(function () {
            positionToolbar();
            startStabilizer();
            syncToolbarHeight();   // HOTFIX C-1: publish the strip height
        });
    }

    function hideToolbar() {
        if (!keyboardVisible) return;
        keyboardVisible = false;
        lastKbDiff = -1;
        stopStabilizer();

        if (toolbarEl) {
            toolbarEl.classList.add('hidden');
        }
        document.body.classList.remove('m-kb-open');
        resetModifiers();

        /* HOTFIX D-3: release the reserved bottom space */
        lastKbInset = 0;
        try {
            document.documentElement.style.setProperty('--mkb-kb', '0px');
            document.documentElement.style.setProperty('--mkb-total', '0px');
        } catch (e) { }
    }

    function isVisible() {
        return keyboardVisible;
    }

    function isElementVisible(el) {
        if (!el) return false;
        // Robust to whatever class scheme toggles it (hidden/show/inline style/etc.)
        var style = window.getComputedStyle(el);
        return style.display !== 'none' && style.visibility !== 'hidden';
    }

    /* ═══════════════════════════════════════════════════════════
       SET THE CODEMIRROR INSTANCE (Editor mode)
       ═══════════════════════════════════════════════════════════ */
    function setEditor(cm) {
        cmInstance = cm;
    }

    /* ═══════════════════════════════════════════════════════════
       INIT
       ═══════════════════════════════════════════════════════════ */
    function announceVersion() {
        // v11: the "⌨️ Keyboard helpers vX active" toast was removed — it popped
        // up on every fresh launch and was noise for the user. We still record
        // the version so any deployment check can read it, but we never toast.
        try {
            localStorage.setItem('quirky.ide.mkb.ver', VERSION);
        } catch (e) { }
    }

    function init() {
        injectCss();
        buildToolbar();
        initViewportTracking();
        initFocusTracking();
        window.addEventListener('resize', function () {
            if (keyboardVisible) syncToolbarHeight();
        });
        //announceVersion();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    /* ── Expose ── */
    window.IDE = window.IDE || {};
    window.IDE.mobileKeyboard = {
        show: showToolbar,
        hide: hideToolbar,
        isVisible: isVisible,
        setEditor: setEditor,
        setMode: setMode,
        getMode: getMode,
        getKeyStyle: function () { return keyStyle; },
        setKeyStyle: function (s) { if (s === 'raw' || s === 'combined') { keyStyle = s; rebuildKeys(); } },
        /** ★ Phase T-PTY-4: read AND clear the sticky Ctrl/Alt toggles so the
            software keyboard can honour them (Ctrl + x → \x18 for nano). */
        consumeModifiers: function () {
            var m = { ctrl: ctrlActive, alt: altActive };
            ctrlActive = false;
            altActive = false;
            updateModifierUI();
            return m;
        },
        version: VERSION
    };
})();