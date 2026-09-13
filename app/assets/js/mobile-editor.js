/**
 * ═══════════════════════════════════════════════════════════════════════════
 *  QUIRKY IDE — MOBILE EDITOR MODULE (Phase 7 · Task 7.2)
 * ═══════════════════════════════════════════════════════════════════════════
 *
 *  The multi-tab code editor for the mobile shell. Manages:
 *    • CodeMirror instance (lazy-loaded on first use)
 *    • Tab model: open, switch, close, dirty tracking
 *    • Save / Save All
 *    • Format & Shrink (reuses desktop engines)
 *    • Word-wrap toggle
 *    • Find-in-file bar (Task 3.5)
 *    • Editor zoom: manual buttons + pinch-to-zoom
 *    • Action bar wiring
 *
 *  EXPOSES: window.IDE.mobileEditor
 * ═══════════════════════════════════════════════════════════════════════════
 */
(function () {
    'use strict';

    var U = window.IDE && window.IDE.utils;
    var api = window.IDE && window.IDE.api;

    if (!U || !api) return;

    /* ═══════════════════════════════════════════════════════════════
  STATE
  ═══════════════════════════════════════════════════════════════ */
    var mobileCM = null;
    var mobileEditorReady = false;
    var mobileEditorCreating = false; // ★ lock: only one engine boot at a time
    var _editorReadyQueue = []; // ★ callbacks waiting for the engine to boot
    var mobileTabs = [];
    var mobileActiveTabId = null;
    var mobileTabSeq = 0;
    /* ── Task 9.8: per-tab undo history + memory cap ── */
    var HISTORY_TAB_LIMIT = 5; // keep stored history for at most this many tabs
    var _histOrder = []; // ids of tabs with stored history, most-recent last
    /* ── Task 9.2: large-file performance mode (v2 — tiered) ── */
    var LARGE_FILE_LINES = 3000; // perf tier threshold (lowered)
    var LARGE_FILE_CHARS = 500000; // ~500 KB
    var ULTRA_FILE_LINES = 5000; // ultra-perf tier (7000+ line files)
    var ULTRA_FILE_CHARS = 1000000; // ~1 MB
    var perfModeActive = false;
    var ultraModeActive = false; // ★ ultra tier flag
    var changeSyncTimer = null;
    var _cursorRafPending = false; // ★ throttled cursor position updates
    /* ── Input-safe refresh guard ──
     Background repaints (language-mode loading, keyboard resize,
     orientation changes) that land in the middle of a keystroke
     roll the character back on phone keyboards. We track "the user
     is composing / just typed" and defer refreshes until they pause. */
    var cmComposing = false;
    var lastInputAt = 0;

    /* ═══════════════════════════════════════════════════════════════
  DOM REFERENCES
  ═══════════════════════════════════════════════════════════════ */
    var mEditorWrap = document.getElementById('m-editor-wrap');
    var mEditorEmpty = document.getElementById('m-editor-empty');
    var mEditorMount = document.getElementById('m-editor-mount');
    var mEditorFileLabel = document.getElementById('m-editor-file');
    var mTabBar = document.getElementById('m-tab-bar');
    var mTabBarWrap = document.getElementById('m-tab-bar-wrap');
    var mTabOverflowLeft = document.getElementById('m-tab-overflow-left');
    var mTabOverflowRight = document.getElementById('m-tab-overflow-right');
    var mTabOverflowLeftCount = document.getElementById('m-tab-overflow-left-count');
    var mTabOverflowRightCount = document.getElementById('m-tab-overflow-right-count');
    var mActionBar = document.getElementById('m-action-bar');
    var fileNameEl = document.getElementById('mh-file');
    var saveBtn = document.getElementById('mh-save');

    /* ═══════════════════════════════════════════════════════════════
  HELPERS (safe wrappers around shared utilities)
  ═══════════════════════════════════════════════════════════════ */
    function haptic() {
        if (window.IDE.mobileActions && window.IDE.mobileActions.haptic) {
            window.IDE.mobileActions.haptic();
            return;
        }
        try {
            if (navigator.vibrate) navigator.vibrate(10);
        } catch (e) { }
    }

    function toast(msg) {
        if (U && U.toast) U.toast(msg);
    }

    /* ── Action Toast (Task 8.1 — FIXED) ─────────────────────────────
     Shows a toast with a tappable action button (e.g. "Redo" after
     an Undo). Uses careful cleanup so it doesn't conflict with
     regular U.toast() calls.                                       */
    var _actionToastTimer = null;
    var _actionCleanupTimer = null;

    function showActionToast(message, actionLabel, actionCallback, duration) {
        duration = duration || 3000;
        var toastEl = document.getElementById('toast');
        if (!toastEl) return;

        // Clear any existing timers
        clearTimeout(_actionToastTimer);
        clearTimeout(_actionCleanupTimer);
        _actionCleanupTimer = null;

        // Remove leftover classes
        toastEl.classList.remove('toast-success', 'toast-error', 'toast-warning', 'toast-info', 'show');

        // Build content: message text + action button
        toastEl.innerHTML = '';
        var msgSpan = document.createElement('span');
        msgSpan.className = 'm-toast-msg';
        msgSpan.textContent = message;
        toastEl.appendChild(msgSpan);

        if (actionLabel && typeof actionCallback === 'function') {
            var actionBtn = document.createElement('button');
            actionBtn.className = 'm-toast-action';
            actionBtn.textContent = actionLabel;
            actionBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                clearTimeout(_actionToastTimer);
                hideActionToast();
                actionCallback();
            });
            toastEl.appendChild(actionBtn);
            toastEl.classList.add('m-toast-has-action');
        } else {
            toastEl.classList.remove('m-toast-has-action');
        }

        // Force reflow so transition re-triggers
        void toastEl.offsetWidth;
        toastEl.classList.add('show');

        // Auto-hide
        _actionToastTimer = setTimeout(function () {
            hideActionToast();
        }, duration);
    }

    function hideActionToast() {
        var toastEl = document.getElementById('toast');
        if (!toastEl) return;
        clearTimeout(_actionCleanupTimer);
        toastEl.classList.remove('show');
        // Clean up action button AFTER the CSS transition (250ms)
        // Only clean if it still has the action class (don't wipe a regular toast)
        _actionCleanupTimer = setTimeout(function () {
            if (toastEl.classList.contains('m-toast-has-action')) {
                toastEl.innerHTML = '';
                toastEl.classList.remove('m-toast-has-action');
            }
            _actionCleanupTimer = null;
        }, 300);
    }

    function getFileIcon(name) {
        if (U && U.getFileIcon) return U.getFileIcon(name);
        return '📄';
    }

    function escHtml(str) {
        if (U && U.escapeHtml) return U.escapeHtml(str);
        if (typeof str !== 'string') return '';
        return str.replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function switchToEditorPanel() {
        if (typeof window._mobileSwitchTab === 'function') {
            window._mobileSwitchTab('editor');
        }
    }

    function notifyPreviewChange() {
        if (typeof window._mobileSchedulePreview === 'function') {
            window._mobileSchedulePreview();
        }
    }
    /* ═══════════════════════════════════════════════════════════════
  LARGE-FILE PERFORMANCE MODE (Task 9.2)
  ─────────────────────────────────────────────────
  CodeMirror virtualises rendering — it only paints lines near
  the viewport (`viewportMargin` = extra lines painted above /
  below the screen). For files over 5,000 lines we go more
  aggressive:
    • viewportMargin 50 → 10 (paint less off-screen)
    • word wrap OFF (wrapped lines force costly measuring)
    • bracket match / active line / folding paused (they add
      per-keystroke or per-scroll work)
  Small files keep the full-fat experience. The engine is
  re-tuned on every tab switch, so one huge file never slows
  down the rest of the editor.
  ═══════════════════════════════════════════════════════════════ */
    /** Returns 0 = normal, 1 = perf, 2 = ultra */
    function getDocTier(content) {
        if (!content) return 0;
        if (content.length > ULTRA_FILE_CHARS) return 2;
        var lines = 1,
            i = -1;
        while ((i = content.indexOf('\n', i + 1)) !== -1) {
            lines++;
            if (lines > ULTRA_FILE_LINES) return 2; // early exit
        }
        if (lines > LARGE_FILE_LINES || content.length > LARGE_FILE_CHARS) return 1;
        return 0;
    }

    /** Back-compat wrapper (used by external callers) */
    function isLargeDoc(content) {
        return getDocTier(content) > 0;
    }

    function applyEditorPerfMode(large) {
        if (!mobileCM) return;
        var tier = large ? (typeof large === 'number' ? large : 1) : 0;
        perfModeActive = tier > 0;
        ultraModeActive = tier >= 2;

        if (ultraModeActive) {
            /* ★ ULTRA: 5000+ lines — absolute minimum rendering */
            mobileCM.setOption('viewportMargin', 3);
            mobileCM.setOption('lineWrapping', false);
            mobileCM.setOption('matchBrackets', false);
            mobileCM.setOption('styleActiveLine', false);
            mobileCM.setOption('autoCloseBrackets', false);
            mobileCM.setOption('autoCloseTags', false);
            mobileCM.setOption('foldGutter', false);
            mobileCM.setOption('gutters', ['CodeMirror-linenumbers']);
        } else if (perfModeActive) {
            /* PERF: 3000–5000 lines */
            mobileCM.setOption('viewportMargin', 8);
            mobileCM.setOption('lineWrapping', false);
            mobileCM.setOption('matchBrackets', false);
            mobileCM.setOption('styleActiveLine', false);
            mobileCM.setOption('autoCloseBrackets', false);
            mobileCM.setOption('autoCloseTags', false);
            mobileCM.setOption('foldGutter', false);
            mobileCM.setOption('gutters', ['CodeMirror-linenumbers', 'm-diag-gutter']);
        } else {
            mobileCM.setOption('viewportMargin', 50);
            mobileCM.setOption('lineWrapping', true);
            mobileCM.setOption('matchBrackets', true);
            mobileCM.setOption('styleActiveLine', true);
            mobileCM.setOption('autoCloseBrackets', true);
            mobileCM.setOption('autoCloseTags', true);
            mobileCM.setOption('foldGutter', true);
            mobileCM.setOption('gutters', intelGutters());
        }

        /* keep the ↩ wrap button honest about the new state */
        var wrapBtn = document.getElementById('mab-wrap');
        if (wrapBtn) {
            wrapBtn.setAttribute('aria-pressed', mobileCM.getOption('lineWrapping') ? 'true' : 'false');
        }
    }
    /** Copy the editor text into the tab model + refresh UI dots.
     *  ★ PERF v2: `skipHeavy` avoids getValue() + string comparison
     *  when we already know the content hasn't been externally modified. */
    function syncTabContent(tab, skipHeavy) {
        if (!mobileCM || !tab) return;
        if (!skipHeavy) {
            tab.content = mobileCM.getValue();
            tab.dirty = tab.content !== tab.savedContent;
        }
        updateSaveIndicator();
        renderTabDirty(tab);
        /* ★ Skip preview notification during ultra-perf typing */
        if (!ultraModeActive) notifyPreviewChange();
    }

    /** Lightweight dirty-mark for perf/ultra modes (NO getValue). */
    function markDirtyLight(tab) {
        if (!tab) return;
        tab.dirty = true;
        updateSaveIndicator();
        renderTabDirty(tab);
    }

    /** Flush a deferred large-file sync (used before Save / Save All / tab switch). */
    function flushPendingSync(tab) {
        if (changeSyncTimer) {
            clearTimeout(changeSyncTimer);
            changeSyncTimer = null;
        }
        /* Always do a FULL sync on flush (save needs real content) */
        syncTabContent(tab, false);
    }
    /** Refresh CodeMirror ONLY when the user is not mid-keystroke.
    If they are typing/composing, retry after a short pause instead
    of interrupting the keyboard (which deletes the typed char). */
    function safeRefresh() {
        if (!mobileCM) return;
        if (cmComposing || (Date.now() - lastInputAt) < 350) {
            setTimeout(safeRefresh, 380);
            return;
        }
        mobileCM.refresh();
    }

    /* ═══════════════════════════════════════════════════════════════
  ENGINE LOADING (CM5 ⇄ CM6)
  ═══════════════════════════════════════════════════════════════
  Loading + language-mode fetching is now owned by editor-adapter.js
  (window.IDE.editorAdapter), which picks CM5 or CM6 per the user's
  Settings → Editor Engine choice (or auto-detects with fallback) and
  hands back an object exposing the same CM5-shaped API used
  everywhere below (getValue, setOption, on, markText, ...). See
  editor-adapter.js for the CM5 script-loader and CM6 dynamic-import
  loader implementations — nothing else in this file needs to know
  which engine is actually running.                                */
    function ensureMode(mode, cb) {
        var EA = window.IDE.editorAdapter;
        if (!EA) {
            cb();
            return;
        }
        EA.ensureMode(mobileCM, mode, cb);
    }

    /* ═══════════════════════════════════════════════════════════════
  INTEL LIFECYCLE (autocomplete / diagnostics / editor-lens)
  ────────────────────────────────────────────────────────────────
  The three intelligence modules never import this file — they listen
  for 'editor:tab-shown' on the IDE.utils event bus and discover the
  active instance themselves via IDE.mobileEditor.getCM(). Our whole
  job here is: keep the facade's langId current, then announce the new
  active tab. The gutter list keeps 'm-diag-gutter' alive across perf
  flips so diagnostics dots always have somewhere to render.
  ═══════════════════════════════════════════════════════════════ */
    var BASE_GUTTERS = ['CodeMirror-linenumbers', 'CodeMirror-foldgutter'];

    /** Gutters incl. the diagnostics dot gutter (idempotent). */
    function intelGutters(extra) {
        var g = BASE_GUTTERS.slice();
        g.push('m-diag-gutter');
        return g;
    }
    /** ★ Write the active language onto the editor mount so themes.css can
     *  apply per-language syntax palettes (e.g. the CSS-only palette). */
    function setEditorLangAttr(ext) {
        if (!mEditorMount) return;
        var id = 'text';
        var LANGAPI = window.IDE.language;
        if (LANGAPI && typeof LANGAPI.byExt === 'function') {
            try {
                id = LANGAPI.byExt(ext || '')
                    .id || 'text';
            } catch (e) {
                id = 'text';
            }
        }
        mEditorMount.setAttribute('data-lang', id);
    }

    /** Announce the newly-active tab to the intelligence modules. */
    function wireIntelForTab(tab) {
        try {
            if (!mobileCM || !tab) return;
            var langId = null;
            var LANGAPI = window.IDE.language;
            if (LANGAPI && typeof LANGAPI.byExt === 'function') {
                langId = LANGAPI.byExt(tab.ext)
                    .id;
            }
            if (typeof mobileCM.setLangId === 'function') {
                mobileCM.setLangId(langId);
            } else {
                mobileCM._qkLangId = langId;
            }
            if (U && U.emit) {
                U.emit('editor:tab-shown', {
                    path: tab.path,
                    ext: tab.ext,
                    id: langId
                });
            }
        } catch (e) { }
    }

    /* ═══════════════════════════════════════════════════════════════
  EDITOR INITIALISATION
  ═══════════════════════════════════════════════════════════════ */
    function initMobileEditor(onReady) {
        if (mobileEditorReady) {
            if (typeof onReady === 'function') onReady();
            return;
        }

        /* ★ FIX: queue every caller so nobody is dropped while the engine boots */
        if (typeof onReady === 'function') _editorReadyQueue.push(onReady);

        /* ★ FIX: only ONE engine instance may ever be created. CM6 boot is
       asynchronous (it imports modules over the network), so without this
       lock two fast callers could create two editors on the same mount
       and break it. */
        if (mobileEditorCreating) return;

        var EA = window.IDE.editorAdapter;
        if (!EA) {
            toast('⚠ Editor engine unavailable — check that editor-adapter.js loaded');
            _editorReadyQueue = [];
            return;
        }

        mobileEditorCreating = true;

        var cfg = window.IDE_CONFIG || {};
        var tabSize = cfg.tabSize || 2;

        EA.create(mEditorMount, {
            value: '',
            mode: null,
            lineNumbers: true,
            fixedGutter: false,
            lineWrapping: true,
            /* ★ v17 FIX — "Tab then type eats the indent": inside the Android app
         use CodeMirror's classic hidden-TEXTAREA input. The contenteditable
         input path is experimental on Android WebViews and lets the keyboard
         keep a stale composing/selection cache that swallows programmatic
         inserts (TAB indent, snippets) on the next keystroke.
         Desktop browsers keep contenteditable. */
            inputStyle: ((window.QuirkyIme || window.QuirkyStorage || window.QuirkyFiles || window.AndroidHardware) ? 'textarea' : 'contenteditable'),
            dragDrop: false,
            matchBrackets: true,
            autoCloseBrackets: true,
            autoCloseTags: true,
            styleActiveLine: true,
            foldGutter: true,
            gutters: ['CodeMirror-linenumbers', 'CodeMirror-foldgutter'],
            indentUnit: tabSize,
            tabSize: tabSize,
            indentWithTabs: false,
            theme: 'default',
            viewportMargin: 50
        }, function (instance) {
            mobileCM = instance;

            mobileCM.on('change', function () {
                var tab = getActiveTab();
                if (!tab) return;

                if (perfModeActive) {
                    /* ★ PERF v2: mark dirty instantly, NEVER call getValue() during
           typing. Content is only synced on save/tab-switch/close. */
                    if (!tab.dirty) markDirtyLight(tab);

                    if (ultraModeActive) {
                        /* ULTRA: skip the deferred sync entirely during typing.
               Content will be read fresh from CM on save/switch. */
                        return;
                    }

                    /* PERF tier: still do a deferred sync but with longer debounce */
                    clearTimeout(changeSyncTimer);
                    changeSyncTimer = setTimeout(function () {
                        changeSyncTimer = null;
                        if (getActiveTab() === tab) syncTabContent(tab, false);
                    }, 600);
                    return;
                }

                syncTabContent(tab);
            });

            /* Task 8.5: update line/column indicator on cursor movement.
                ★ PERF v2: throttle with rAF to avoid DOM writes on every pixel move. */
            mobileCM.on('cursorActivity', function () {
                if (_cursorRafPending) return;
                _cursorRafPending = true;
                requestAnimationFrame(function () {
                    _cursorRafPending = false;
                    updateEditorPosition();
                });
            });
            /* Input-safe guard: remember whenever the user is composing or typing */
            mEditorMount.addEventListener('compositionstart', function () {
                cmComposing = true;
            }, true);
            mEditorMount.addEventListener('compositionend', function () {
                cmComposing = false;
                setTimeout(safeRefresh, 120);
            }, true);
            mEditorMount.addEventListener('keydown', function () {
                lastInputAt = Date.now();
            }, true);
            mEditorMount.addEventListener('input', function () {
                lastInputAt = Date.now();
            }, true);

            mobileEditorReady = true;
            mobileEditorCreating = false; // ★ FIX: engine boot finished

            // ★ LOW-END STABILIZATION: Force aggressive perf mode globally
            if (window.QUIRKY_DEVICE_TIER === 'low') {
                applyEditorPerfMode(2); // Ultra tier: viewportMargin=3, minimal gutters
                document.body.classList.add('disable-animations');
            }

            if (window.IDE.mobileKeyboard && window.IDE.mobileKeyboard.setEditor) {
                window.IDE.mobileKeyboard.setEditor(mobileCM);
            }

            /* ★ FIX: notify EVERY caller that queued up while we were booting */
            var queue = _editorReadyQueue.slice();
            _editorReadyQueue = [];
            queue.forEach(function (fn) {
                try {
                    fn();
                } catch (e) { }
            });
        });
    }

    /**
     * Called when the Editor tab is opened. Ensures CodeMirror is
     * loaded and the editor is ready. Safe to call multiple times.
     */
    function ensureInit() {
        initMobileEditor(function () {
            requestAnimationFrame(function () {
                if (mobileCM) mobileCM.refresh();
            });
            setTimeout(function () {
                if (mobileCM) mobileCM.refresh();
            }, 60);
        });
    }

    /* ═══════════════════════════════════════════════════════════════
  TAB MANAGEMENT
  ═══════════════════════════════════════════════════════════════ */
    function getActiveTab() {
        if (!mobileActiveTabId) return null;
        for (var i = 0; i < mobileTabs.length; i++) {
            if (mobileTabs[i].id === mobileActiveTabId) return mobileTabs[i];
        }
        return null;
    }

    function getTabById(id) {
        for (var i = 0; i < mobileTabs.length; i++) {
            if (mobileTabs[i].id === id) return mobileTabs[i];
        }
        return null;
    }

    function getTabByPath(path) {
        for (var i = 0; i < mobileTabs.length; i++) {
            if (mobileTabs[i].path === path) return mobileTabs[i];
        }
        return null;
    }

    function openFileInEditor(path) {
        if (!path) return;

        var existing = getTabByPath(path);
        if (existing) {
            switchToTab(existing.id);
            return;
        }

        if (!api || !api.files) {
            toast('API not ready');
            return;
        }

        showEditorSkeleton();
        api.files.read(path)
            .then(function (data) {
                if (!data) {
                    hideEditorSkeleton();
                    toast('⚠ File not found');
                    return;
                }
                if (data.binary) {
                    hideEditorSkeleton();
                    toast('⚠ Binary file — cannot edit');
                    return;
                }
                /* Wait until CodeMirror is truly ready, THEN create the tab.
               This is the fix for the "black editor" race: previously we
               bailed out here when the engine was still downloading. */
                initMobileEditor(function () {
                    hideEditorSkeleton();
                    var dup = getTabByPath(path);
                    if (dup) {
                        switchToTab(dup.id);
                        return;
                    }
                    var ext = path.split('.')
                        .pop()
                        .toLowerCase();
                    var LANGAPI = window.IDE.language;
                    var mode = null;
                    if (LANGAPI) mode = LANGAPI.modeForExt(ext)
                        .mode;
                    /* Task 9.1 fix: fetch this file's language mode
                 (the syntax colours) before showing the tab. */
                    ensureMode(mode, function () {
                        var tab = {
                            id: 'mt' + (++mobileTabSeq),
                            path: path,
                            name: data.name || path.split('/')
                                .pop(),
                            content: data.content || '',
                            savedContent: data.content || '',
                            dirty: false,
                            ext: ext,
                            mode: mode
                        };
                        mobileTabs.push(tab);
                        switchToTab(tab.id);
                        renderTabBar();
                        if (U && U.emit) U.emit('editor:tabs-updated'); // Notify tree
                    });
                });
            })
            .
            catch(function (err) {
                hideEditorSkeleton();
                toast('⚠ ' + err.message);
            });
    }

    function switchToTab(id) {
        var tab = getTabById(id);
        if (!tab) return;

        var currentTab = getActiveTab();
        if (currentTab && mobileCM) {
            currentTab.content = mobileCM.getValue();
            /* Task 9.8: remember this tab's undo history + cursor before leaving */
            rememberHistory(currentTab);
            try {
                currentTab.cursor = mobileCM.getCursor();
            } catch (e) {
                currentTab.cursor = null;
            }
        }
        mobileActiveTabId = id;
        /* ★ PERF v2: tiered engine tuning based on file size */
        var tier = getDocTier(tab.content);
        var wasPerf = perfModeActive;
        applyEditorPerfMode(tier);
        if (tier === 2 && !ultraModeActive) toast('🚀 Ultra performance mode — very large file');
        else if (tier === 1 && !wasPerf) toast('⚡ Performance mode — large file');
        mobileCM.setValue(tab.content);
        mobileCM.setOption('mode', tab.mode);
        /* Task 9.1 fix: if this language's mode file hasn't been
       fetched yet, fetch it now and re-apply so colours appear. */
        ensureMode(tab.mode, function () {
            if (mobileCM && getActiveTab() === tab) {
                mobileCM.setOption('mode', tab.mode);
                safeRefresh();
            }
        });
        /* Task 9.8: restore this tab's own undo history (if still stored),
    otherwise start fresh. This keeps per-tab undo while capping memory. */
        if (tab.history) {
            try {
                mobileCM.setHistory(tab.history);
            } catch (e) {
                mobileCM.clearHistory();
            }
        } else {
            mobileCM.clearHistory();
        }
        if (tab.cursor) {
            try {
                mobileCM.setCursor(tab.cursor);
            } catch (e) { }
        }
        mobileCM.setOption('lineWrapping', !perfModeActive);

        if (mEditorFileLabel) mEditorFileLabel.textContent = tab.name;
        if (fileNameEl) fileNameEl.textContent = tab.name;
        if (mEditorEmpty) mEditorEmpty.classList.add('hidden');
        if (mEditorWrap) mEditorWrap.classList.remove('hidden');
        if (mActionBar) mActionBar.classList.remove('hidden');
        if (mTabBar) mTabBar.classList.remove('hidden');

        updateSaveIndicator();
        updateContextualButtons(); /* Task 8.8: update Run/Preview visibility */
        renderTabBar();
        /* Task 8.2: scroll the tab bar so the newly-active tab is visible */
        requestAnimationFrame(function () {
            scrollToActiveTab();
        });
        /* Triple refresh: the first frame may still measure the old (hidden)
    layout; the 60ms timeout repaints once visible; the 250ms timeout
    covers the (lazy) CodeMirror CSS finishing its first apply. */
        requestAnimationFrame(function () {
            if (mobileCM) {
                mobileCM.refresh();
                mobileCM.focus();
            }
        });
        setTimeout(function () {
            safeRefresh();
        }, 60);
        setTimeout(function () {
            safeRefresh();
        }, 250);

        /* Task 8.5: refresh position indicator for the new tab */
        updateEditorPosition();

        /* ★ Intel lifecycle: publish this tab's language id and announce it to
       autocomplete.js / diagnostics.js / editor-lens.js (they clear the
       previous tab's markers/lenses on this event). */
        setEditorLangAttr(tab.ext);
        wireIntelForTab(tab);
    }

    function closeTab(id) {
        var idx = -1;
        for (var i = 0; i < mobileTabs.length; i++) {
            if (mobileTabs[i].id === id) {
                idx = i;
                break;
            }
        }
        if (idx === -1) return;
        /* Task 9.8: release this tab's stored history + recency entry */
        var closing = mobileTabs[idx];
        if (closing) closing.history = null;
        var hi = _histOrder.indexOf(id);
        if (hi !== -1) _histOrder.splice(hi, 1);
        mobileTabs.splice(idx, 1);
        if (U && U.emit) U.emit('editor:tabs-updated'); // Notify tree

        if (mobileTabs.length === 0) {
            mobileActiveTabId = null;
            if (mEditorEmpty) {
                mEditorEmpty.classList.remove('hidden');
                mEditorEmpty.innerHTML = '<span class="mpp-icon">✏️</span>' +
                    '<p class="mpp-text">Tap a file in the Files tab<br>to start editing.</p>';
            }
            if (mEditorWrap) mEditorWrap.classList.add('hidden');
            if (mActionBar) mActionBar.classList.add('hidden');
            if (mTabBar) mTabBar.classList.add('hidden');
            if (mEditorFileLabel) mEditorFileLabel.textContent = 'No file open';
            if (fileNameEl) fileNameEl.textContent = 'QUIRKY IDE';
            setEditorLangAttr('');
            updateSaveIndicator();
            updateContextualButtons(); /* Task 8.8: hide Run/Preview when no file */
            updateEditorPosition(); /* Task 8.5: clear position when no file */
            return;
        }

        if (mobileActiveTabId === id) {
            var nextTab = mobileTabs[idx] || mobileTabs[idx - 1];
            if (nextTab) switchToTab(nextTab.id);
        }

        renderTabBar();
    }

    /* ═══════════════════════════════════════════════════════════════
  TASK 9.8 — PER-TAB UNDO HISTORY + MEMORY CAP
  Stores each tab's CodeMirror history when you switch away, restores
  it when you switch back, and caps how many histories are kept in
  memory (the oldest are disposed first) to protect low-memory phones.
  ═══════════════════════════════════════════════════════════════ */
    function rememberHistory(tab) {
        if (!mobileCM || !tab) return;
        /* Task 9.2 synergy: skip storing history for very large files —
    they are the biggest memory consumers and already run in
    performance mode, so a fresh history is acceptable. */
        if (isLargeDoc(tab.content)) {
            tab.history = null;
            return;
        }
        try {
            tab.history = mobileCM.getHistory();
        } catch (e) {
            tab.history = null;
        }
        if (!tab.history) return;
        /* Track recency so the memory cap drops the oldest histories first */
        var i = _histOrder.indexOf(tab.id);
        if (i !== -1) _histOrder.splice(i, 1);
        _histOrder.push(tab.id);
        enforceHistoryLimit();
    }

    function enforceHistoryLimit() {
        while (_histOrder.length > HISTORY_TAB_LIMIT) {
            var oldestId = _histOrder.shift();
            if (oldestId === mobileActiveTabId) continue; // never drop the live tab
            var t = getTabById(oldestId);
            if (t) t.history = null;
        }
    }

    function renderTabBar() {
        if (!mTabBar) return;
        if (!mTabBar) return;
        /* Task 8.2: use the wrapper for visibility when present */
        var wrap = mTabBarWrap || mTabBar.parentElement;
        if (mobileTabs.length === 0) {
            if (wrap) wrap.classList.add('hidden');
            mTabBar.classList.add('hidden');
            return;
        }
        if (wrap) wrap.classList.remove('hidden');
        mTabBar.classList.remove('hidden');

        /* Task 9.6: batch all DOM writes using a DocumentFragment
       to avoid triggering a reflow for every single tab element. */
        var fragment = document.createDocumentFragment();
        mobileTabs.forEach(function (tab) {
            var el = document.createElement('div');
            el.className = 'm-tab-item' + (tab.id === mobileActiveTabId ? ' active' : '') + (tab.dirty ? ' dirty' : '');
            el.setAttribute('data-tab-id', tab.id);
            /* Only the ACTIVE tab gets the ✕ close button — prevents accidental closes */
            var closeBtnHtml = (tab.id === mobileActiveTabId) ? '<button class="m-tab-close" data-close="' + tab.id + '" title="Close tab" aria-label="Close ' + escHtml(tab.name) + '">✕</button>' : '';
            el.innerHTML =
                '<span class="m-tab-icon">' + getFileIcon(tab.name) + '</span>' +
                '<span class="m-tab-name">' + escHtml(tab.name) + '</span>' + (tab.dirty ? '<span class="m-tab-dot">●</span>' : '') + closeBtnHtml;
            el.addEventListener('click', function (e) {
                /* ✕ button closes the tab without switching to it */
                if (e.target.closest('.m-tab-close')) {
                    e.stopPropagation();
                    closeTab(tab.id);
                    return;
                }
                if (e.target.closest('.m-tab-dot')) return;
                switchToTab(tab.id);
            });
            /* Long-press to close */
            var lpTimer = null;
            el.addEventListener('touchstart', function () {
                lpTimer = setTimeout(function () {
                    closeTab(tab.id);
                    toast('Closed: ' + tab.name);
                }, 500);
            }, {
                passive: true
            });
            el.addEventListener('touchmove', function () {
                clearTimeout(lpTimer);
            }, {
                passive: true
            });
            el.addEventListener('touchend', function () {
                clearTimeout(lpTimer);
            }, {
                passive: true
            });
            /* Swipe left to close */
            var swipeStartX = 0;
            el.addEventListener('touchstart', function (e) {
                swipeStartX = e.touches[0].clientX;
            }, {
                passive: true
            });
            el.addEventListener('touchend', function (e) {
                var dx = e.changedTouches[0].clientX - swipeStartX;
                if (dx < -60) {
                    closeTab(tab.id);
                    toast('Closed: ' + tab.name);
                }
            }, {
                passive: true
            });
            fragment.appendChild(el);
        });

        /* Task 9.6: single DOM write — append the whole fragment at once */
        mTabBar.innerHTML = '';
        mTabBar.appendChild(fragment);

        /* Task 8.2: refresh overflow indicators after re-render */
        updateTabOverflowIndicators();
    }

    /* ═══════════════════════════════════════════════════════════════
  TAB OVERFLOW INDICATORS (Task 8.2)
  ═══════════════════════════════════════════════════════════════ */

    /**
     * Count tabs that are scrolled off-screen to the left / right,
     * then show or hide the indicator badges accordingly.
     */
    function updateTabOverflowIndicators() {
        if (!mTabBar) return;
        var tabs = mTabBar.querySelectorAll('.m-tab-item');
        if (tabs.length === 0 || mTabBar.classList.contains('hidden')) {
            if (mTabOverflowLeft) mTabOverflowLeft.classList.add('hidden');
            if (mTabOverflowRight) mTabOverflowRight.classList.add('hidden');
            return;
        }
        var barRect = mTabBar.getBoundingClientRect();
        /* ★ FIX: when the editor panel is hidden (display:none) every rect is 0,
    which made the math count every tab as "off-screen". Bail out. */
        if (barRect.width === 0 || barRect.height === 0) {
            if (mTabOverflowLeft) mTabOverflowLeft.classList.add('hidden');
            if (mTabOverflowRight) mTabOverflowRight.classList.add('hidden');
            return;
        }
        var hiddenLeft = 0;
        var hiddenRight = 0;
        for (var i = 0; i < tabs.length; i++) {
            var rect = tabs[i].getBoundingClientRect();
            if (rect.right <= barRect.left + 1) hiddenLeft++;
            else if (rect.left >= barRect.right - 1) hiddenRight++;
        }
        if (mTabOverflowLeft) {
            if (hiddenLeft > 0) {
                mTabOverflowLeft.classList.remove('hidden');
                if (mTabOverflowLeftCount) mTabOverflowLeftCount.textContent = String(hiddenLeft);
            } else {
                mTabOverflowLeft.classList.add('hidden');
            }
        }
        if (mTabOverflowRight) {
            if (hiddenRight > 0) {
                mTabOverflowRight.classList.remove('hidden');
                if (mTabOverflowRightCount) mTabOverflowRightCount.textContent = String(hiddenRight);
            } else {
                mTabOverflowRight.classList.add('hidden');
            }
        }
    }

    /**
     * Scroll the tab bar so the active tab is centred and visible.
     * Called every time the user switches tabs.
     */
    function scrollToActiveTab() {
        if (!mTabBar) return;
        var activeEl = mTabBar.querySelector('.m-tab-item.active');
        if (!activeEl) return;
        var barRect = mTabBar.getBoundingClientRect();
        var tabRect = activeEl.getBoundingClientRect();
        /* Already fully visible — nothing to do */
        if (tabRect.left >= barRect.left && tabRect.right <= barRect.right) return;
        /* Centre the tab in the visible area */
        var barCenter = barRect.left + barRect.width / 2;
        var tabCenter = tabRect.left + tabRect.width / 2;
        var scrollAmount = tabCenter - barCenter;
        mTabBar.scrollBy({
            left: scrollAmount,
            behavior: 'smooth'
        });
    }

    /**
     * Wire the overflow indicator buttons and the scroll listener.
     * Called once from boot().
     */
    function wireTabOverflow() {
        if (!mTabBar) return;
        /* Update indicators whenever the user scrolls the tab bar */
        mTabBar.addEventListener('scroll', function () {
            updateTabOverflowIndicators();
        }, {
            passive: true
        });
        /* Left indicator → scroll left */
        if (mTabOverflowLeft) {
            mTabOverflowLeft.addEventListener('click', function () {
                haptic();
                mTabBar.scrollBy({
                    left: -mTabBar.clientWidth * 0.8,
                    behavior: 'smooth'
                });
            });
        }
        /* Right indicator → scroll right */
        if (mTabOverflowRight) {
            mTabOverflowRight.addEventListener('click', function () {
                haptic();
                mTabBar.scrollBy({
                    left: mTabBar.clientWidth * 0.8,
                    behavior: 'smooth'
                });
            });
        }
        /* Re-check on window resize (orientation change, etc.) */
        window.addEventListener('resize', function () {
            updateTabOverflowIndicators();
        });
    }

    function renderTabDirty(tab) {
        if (!mTabBar) return;
        var el = mTabBar.querySelector('[data-tab-id="' + tab.id + '"]');
        if (!el) return;
        el.classList.toggle('dirty', tab.dirty);
        var dot = el.querySelector('.m-tab-dot');
        if (tab.dirty && !dot) {
            var d = document.createElement('span');
            d.className = 'm-tab-dot';
            d.textContent = '●';
            el.appendChild(d);
        } else if (!tab.dirty && dot) {
            dot.remove();
        }
    }

    /* ═══════════════════════════════════════════════════════════════
  SAVE
  ═══════════════════════════════════════════════════════════════ */
    function mobileEditorSave() {
        var tab = getActiveTab();
        if (!tab || !mobileCM) {
            toast('💾 No file open');
            return;
        }
        /* ★ PERF v2: flush deferred sync + read fresh content from CM */
        flushPendingSync(tab);
        /* In ultra mode, content was never synced during typing — read now */
        if (ultraModeActive && mobileCM) {
            tab.content = mobileCM.getValue();
            tab.dirty = tab.content !== tab.savedContent;
        }
        if (!tab.dirty && tab.content === tab.savedContent) {
            toast('Already saved');
            return;
        }

        if (!api || !api.files) {
            toast('API not ready');
            return;
        }

        tab.content = mobileCM.getValue();

        api.files.save(tab.path, tab.content)
            .then(function () {
                tab.savedContent = tab.content;
                tab.dirty = false;
                updateSaveIndicator();
                renderTabDirty(tab);
                toast('💾 Saved ' + tab.name);
                if (U && U.emit) {
                    U.emit('editor:saved', {
                        path: tab.path,
                        content: tab.content,
                        ext: tab.ext,
                        explicit: true
                    });
                }
                /* ★ PHASE 4: Force preview refresh after save.
               For Apache-served content, the preview reads from disk,
               so it needs an explicit reload after the file is saved. */
                notifyPreviewChange();
            })
            .
            catch(function (err) {
                /* Task 8.6: contextual error with retry */
                var Actions = window.IDE.mobileActions;
                if (Actions && Actions.showError) {
                    Actions.showError('save', err.message, function () {
                        mobileEditorSave();
                    });
                } else {
                    toast('⚠ Save failed: ' + err.message);
                }
            });
    }

    function mobileSaveAll() {
        if (!api || !api.files) {
            toast('API not ready');
            return;
        }
        /* ★ PERF v2: flush + ultra-mode content read */
        flushPendingSync(getActiveTab());
        if (ultraModeActive && mobileCM) {
            var at = getActiveTab();
            if (at) {
                at.content = mobileCM.getValue();
                at.dirty = at.content !== at.savedContent;
            }
        }
        var dirtyTabs = mobileTabs.filter(function (t) {
            return t.dirty;
        });
        if (dirtyTabs.length === 0) {
            toast('All files saved');
            return;
        }

        var saved = 0;
        var promises = dirtyTabs.map(function (tab) {
            if (tab.id === mobileActiveTabId && mobileCM) {
                tab.content = mobileCM.getValue();
            }
            return api.files.save(tab.path, tab.content)
                .then(function () {
                    tab.savedContent = tab.content;
                    tab.dirty = false;
                    renderTabDirty(tab);
                    saved++;
                });
        });

        Promise.all(promises)
            .then(function () {
                updateSaveIndicator();
                toast('💾 Saved ' + saved + ' file' + (saved === 1 ? '' : 's'));
            })
            .
            catch(function (err) {
                toast('⚠ Save All error: ' + err.message);
            });
    }

    function updateSaveIndicator() {
        if (!saveBtn) return;
        var anyDirty = false;
        for (var i = 0; i < mobileTabs.length; i++) {
            if (mobileTabs[i].dirty) {
                anyDirty = true;
                break;
            }
        }
        if (anyDirty) {
            saveBtn.classList.add('unsaved');
            saveBtn.title = 'Save all changes (unsaved)';
        } else {
            saveBtn.classList.remove('unsaved');
            saveBtn.title = 'Save all changes';
        }
    }

    /* ═══════════════════════════════════════════════════════════════
  CONTEXTUAL ACTION BAR (Task 8.8)
  Shows/hides Run and Preview buttons based on the active file's
  extension. Called every time the active tab changes.
  ═══════════════════════════════════════════════════════════════ */

    /** Extensions that can be run in the terminal (matches config runners). */
    var RUNNABLE_EXTS = ['py', 'pyw', 'js', 'mjs', 'sh', 'bash', 'rb'];

    /** Extensions that can be previewed (matches config previewable). */
    var PREVIEWABLE_EXTS = ['html', 'htm', 'php', 'phtml', 'svg', 'md', 'markdown'];

    /**
     * Update the contextual action bar buttons based on the active tab.
     * Shows ▶ Run for executable files, 👁 Preview for previewable files.
     */
    function updateContextualButtons() {
        var tab = getActiveTab();
        var runBtn = document.getElementById('mab-run');
        var previewBtn = document.getElementById('mab-preview');
        var ctxSep = document.getElementById('mab-ctx-sep');

        if (!runBtn || !previewBtn) return;

        var ext = tab ? tab.ext : '';
        var cfg = window.IDE_CONFIG || {};
        var feat = (cfg && cfg.features) ? cfg.features : {};
        var canRun = RUNNABLE_EXTS.indexOf(ext) !== -1 && !!feat.terminal;
        var canPreview = PREVIEWABLE_EXTS.indexOf(ext) !== -1 && !!feat.preview;

        /* Show/hide Run button */
        if (canRun) {
            runBtn.classList.remove('hidden');
        } else {
            runBtn.classList.add('hidden');
        }

        /* Show/hide Preview button */
        if (canPreview) {
            previewBtn.classList.remove('hidden');
        } else {
            previewBtn.classList.add('hidden');
        }

        /* Show/hide the separator only if at least one contextual button is visible */
        if (ctxSep) {
            if (canRun || canPreview) {
                ctxSep.classList.remove('hidden');
            } else {
                ctxSep.classList.add('hidden');
            }
        }
    }

    /**
     * Run the active file in the terminal.
     * Saves the file first if dirty, then switches to terminal and executes.
     */
    function mobileRunFile() {
        var tab = getActiveTab();
        if (!tab) {
            toast('No file open');
            return;
        }

        var cfg = window.IDE_CONFIG || {};
        var runners = cfg.runners || {};
        var tpl = runners[tab.ext];

        if (!tpl) {
            toast('⚠ No runner configured for .' + tab.ext);
            return;
        }

        /* Save first if dirty */
        if (tab.dirty && mobileCM) {
            tab.content = mobileCM.getValue();
            api.files.save(tab.path, tab.content)
                .then(function () {
                    tab.savedContent = tab.content;
                    tab.dirty = false;
                    updateSaveIndicator();
                    renderTabDirty(tab);
                    executeInTerminal(tpl, tab.path);
                })
                .
                catch(function (err) {
                    toast('⚠ Save failed: ' + err.message);
                });
        } else {
            executeInTerminal(tpl, tab.path);
        }
    }

    /**
     * Build the command from the runner template and execute in terminal.
     */
    function executeInTerminal(tpl, filePath) {
        var safePath = filePath.replace(/"/g, '\\"');
        var cmd = tpl.replace('{file}', safePath);

        /* Switch to terminal tab */
        if (typeof window._mobileSwitchTab === 'function') {
            window._mobileSwitchTab('terminal');
        }

        /* Execute the command via the terminal module */
        setTimeout(function () {
            var Term = window.IDE.mobileTerminal;
            if (Term && Term.runCommand) {
                Term.runCommand(cmd);
            } else {
                toast('⚠ Terminal not ready');
            }
        }, 300);
    }

    /**
     * Preview the active file.
     * Switches to the Preview tab — the preview module renders automatically.
     */
    function mobilePreviewFile() {
        var tab = getActiveTab();
        if (!tab) {
            toast('No file open');
            return;
        }

        var cfg = window.IDE_CONFIG || {};
        var previewable = cfg.previewable || PREVIEWABLE_EXTS;
        if (previewable.indexOf(tab.ext) === -1) {
            toast('⚠ .' + tab.ext + ' files cannot be previewed');
            return;
        }

        /* Save first if dirty so preview shows latest content */
        if (tab.dirty && mobileCM) {
            tab.content = mobileCM.getValue();
            api.files.save(tab.path, tab.content)
                .then(function () {
                    tab.savedContent = tab.content;
                    tab.dirty = false;
                    updateSaveIndicator();
                    renderTabDirty(tab);
                    switchToPreviewTab();
                })
                .
                catch(function (err) {
                    toast('⚠ Save failed: ' + err.message);
                });
        } else {
            switchToPreviewTab();
        }
    }

    /**
     * Switch to the Preview tab.
     */
    function switchToPreviewTab() {
        if (typeof window._mobileSwitchTab === 'function') {
            window._mobileSwitchTab('preview');
        }
    }

    /* ═══════════════════════════════════════════════════════════════
  EDITOR POSITION INDICATOR (Task 8.5)
  Shows "Ln 12, Col 5" in the panel head, updating on every
  cursor movement. Hidden when no file is open.
  ═══════════════════════════════════════════════════════════════ */
    function updateEditorPosition() {
        var posEl = document.getElementById('m-editor-pos');
        if (!posEl) return;
        if (!mobileCM || !mobileActiveTabId) {
            posEl.textContent = '';
            posEl.classList.add('hidden');
            return;
        }
        var cur = mobileCM.getCursor();
        posEl.textContent = 'Ln ' + (cur.line + 1) + ', Col ' + (cur.ch + 1);
        posEl.classList.remove('hidden');
    }

    /* ═══════════════════════════════════════════════════════════════
  FORMAT & SHRINK
  ═══════════════════════════════════════════════════════════════ */
    function setEditorText(text) {
        if (!mobileCM) return;
        var lastLine = mobileCM.lineCount() - 1;
        var lastCh = mobileCM.getLine(lastLine)
            .length;
        mobileCM.replaceRange(text, {
            line: 0,
            ch: 0
        }, {
            line: lastLine,
            ch: lastCh
        });
    }

    /* ── js-beautify lazy loader (client-side formatter for web files) ──
If the beautify library isn't in memory yet, inject its three vendor
scripts on demand, then run the callback. Missing files never block.
★ FIX: the real vendor files are the *.min.js builds. */
    function ensureBeautify(cb) {
        if (typeof js_beautify === 'function' && typeof css_beautify === 'function' && typeof html_beautify === 'function') {
            cb();
            return;
        }
        var urls = [
            'index.php?vendor=js-beautify/beautify.min.js',
            'index.php?vendor=js-beautify/beautify-css.min.js',
            'index.php?vendor=js-beautify/beautify-html.min.js'];
        var left = urls.length;
        urls.forEach(function (u) {
            var s = document.createElement('script');
            s.src = u;
            s.onload = function () {
                if (--left === 0) cb();
            };
            s.onerror = function () {
                if (--left === 0) cb();
            };
            document.head.appendChild(s);
        });
    }

    /* ── Prettier lazy loader ─────────────────────────────────────────
  Prettier lives in app/vendor/prettier/ (standalone.js plus one
  "parser" per language family). We load ONLY what the current file
  needs, once, and remember it:
  babel    → JS / TS / JSON        (parser-babel.js)
  css/scss/less → stylesheets      (parser-postcss.js)
  html     → HTML / Vue            (parser-html.js)
  markdown → Markdown              (parser-markdown.js)
  A missing file never blocks — we fall back to js-beautify.      */
    var PRETTIER_CORE = 'index.php?vendor=prettier/standalone.js';
    var PRETTIER_PARSER_URLS = {
        babel: 'index.php?vendor=prettier/parser-babel.js',
        css: 'index.php?vendor=prettier/parser-postcss.js',
        scss: 'index.php?vendor=prettier/parser-postcss.js',
        less: 'index.php?vendor=prettier/parser-postcss.js',
        html: 'index.php?vendor=prettier/parser-html.js',
        markdown: 'index.php?vendor=prettier/parser-markdown.js'
    };
    var _ptState = {}; // url → 'loading' | true
    var _ptQueue = {}; // url → [waiting callbacks]
    function loadScriptOnce(url, cb) {
        if (_ptState[url] === true) {
            cb();
            return;
        }
        if (_ptState[url] === 'loading') {
            _ptQueue[url].push(cb);
            return;
        }
        _ptState[url] = 'loading';
        _ptQueue[url] = [cb];
        var s = document.createElement('script');
        s.src = url;

        function done() {
            _ptState[url] = true;
            var q = _ptQueue[url] || [];
            _ptQueue[url] = [];
            q.forEach(function (fn) {
                fn();
            });
        }
        s.onload = done;
        s.onerror = done;
        document.head.appendChild(s);
    }

    function ensurePrettier(parserKey, cb) {
        loadScriptOnce(PRETTIER_CORE, function () {
            loadScriptOnce(PRETTIER_PARSER_URLS[parserKey], cb);
        });
    }
    /** Which Prettier parser handles this extension (null = unsupported). */
    function prettierParserFor(ext) {
        if (ext === 'js' || ext === 'mjs' || ext === 'ts' || ext === 'jsx' || ext === 'json') return 'babel';
        if (ext === 'css') return 'css';
        if (ext === 'scss') return 'scss';
        if (ext === 'less') return 'less';
        if (ext === 'html' || ext === 'htm' || ext === 'vue') return 'html';
        if (ext === 'md' || ext === 'markdown') return 'markdown';
        return null;
    }
    /** Run Prettier. Works with both old (sync) and new (async) builds. */
    function runPrettier(parserKey, content, indent, onOk, onFail) {
        try {
            if (!window.prettier || !window.prettierPlugins) {
                onFail('Prettier not loaded');
                return;
            }
            var out = window.prettier.format(content, {
                parser: parserKey,
                plugins: window.prettierPlugins,
                tabWidth: indent,
                useTabs: false
            });
            if (out && typeof out.then === 'function') {
                out.then(onOk)
                    .
                    catch(function (e) {
                        onFail(e && e.message ? e.message : String(e));
                    });
            } else {
                onOk(out);
            }
        } catch (e) {
            onFail(e && e.message ? e.message : String(e));
        }
    }

    /* ── js-beautify formatting path (the default engine + fallback) ── */
    function formatWithBeautify(content, ext, indent) {
        var result = null;
        if (ext === 'js' || ext === 'mjs' || ext === 'json' || ext === 'ts') {
            if (typeof js_beautify === 'function') result = js_beautify(content, {
                indent_size: indent,
                end_with_newline: true
            });
        } else if (ext === 'css' || ext === 'scss' || ext === 'sass' || ext === 'less') {
            if (typeof css_beautify === 'function') result = css_beautify(content, {
                indent_size: indent,
                end_with_newline: true
            });
        } else if (ext === 'html' || ext === 'htm' || ext === 'vue') {
            if (typeof html_beautify === 'function') result = html_beautify(content, {
                indent_size: indent,
                end_with_newline: true
            });
        }
        return result;
    }

    function applyFormatResult(result, content, label, ext) {
        if (result && result !== content) {
            setEditorText(result);
            toast('✨ Formatted' + (label ? ' (' + label + ')' : ''));
        } else if (result === content) {
            toast('Already formatted');
        } else {
            toast('No formatter for .' + ext);
        }
    }

    function mobileFormat() {
        var tab = getActiveTab();
        if (!tab || !mobileCM) {
            toast('No file open');
            return;
        }
        var cfg = window.IDE_CONFIG || {};
        if (cfg.features && cfg.features.format === false) {
            toast('✨ Formatting is currently turned off. You can turn it on in Settings.');
            return;
        }
        var content = mobileCM.getValue();
        var ext = tab.ext;
        var indent = mobileCM.getOption('indentUnit') || 2;

        /* ── PHP: formatted SERVER-SIDE. Engine picked in Settings →
    "PHP Formatter": 'builtin' (fast) or 'php-cs-fixer' (the vendored
    .phar — slower but far smarter). The server automatically falls
    back to builtin and tells us if the .phar is ever unavailable. ── */
        if (ext === 'php' || ext === 'phtml') {
            var formatter = 'builtin';
            if (window.IDE.settings && window.IDE.settings.get) {
                formatter = window.IDE.settings.get('phpFormatter') || 'builtin';
            }
            toast(formatter === 'php-cs-fixer' ? '✨ Formatting with PHP CS Fixer…' : '✨ Formatting…');
            api.code.format(content, 'php', formatter)
                .then(function (res) {
                    var changed = res && res.code != null && res.code !== content;
                    if (changed) setEditorText(res.code);
                    if (res && res.note) {
                        toast('✨ ' + res.note);
                    } else if (changed) {
                        toast('✨ Formatted' + (res.formatter === 'php-cs-fixer' ? ' (PHP CS Fixer)' : ''));
                    } else {
                        toast('Already formatted');
                    }
                })
                .
                catch(function (err) {
                    toast('⚠ Format failed: ' + err.message);
                });
            return;
        }

        /* ── Web languages: engine picked in Settings → "Web Formatter" ── */
        var webFormatter = 'js-beautify';
        if (window.IDE.settings && window.IDE.settings.get) {
            webFormatter = window.IDE.settings.get('webFormatter') || 'js-beautify';
        }

        if (webFormatter === 'prettier') {
            var parserKey = prettierParserFor(ext);
            if (parserKey) {
                toast('✨ Loading Prettier…');
                ensurePrettier(parserKey, function () {
                    runPrettier(parserKey, content, indent,

                        function (formatted) {
                            applyFormatResult(formatted, content, 'Prettier', ext);
                        },

                        function (reason) {
                            /* Prettier missing/broken → graceful fallback to js-beautify */
                            ensureBeautify(function () {
                                var r = formatWithBeautify(content, ext, indent);
                                if (r != null) applyFormatResult(r, content, 'js-beautify fallback', ext);
                                else toast('⚠ Prettier failed: ' + reason);
                            });
                        });
                });
                return;
            }
            /* Prettier can't handle this type (e.g. .sass) → beautify below */
        }

        ensureBeautify(function () {
            applyFormatResult(formatWithBeautify(content, ext, indent), content, '', ext);
        });
    }

    function mobileShrink() {
        var tab = getActiveTab();
        if (!tab || !mobileCM) {
            toast('No file open');
            return;
        }
        var cfg = window.IDE_CONFIG || {};
        if (cfg.features && cfg.features.minify === false) {
            toast('🗜️ Minify is currently turned off. You can turn it on in Settings.');
            return;
        }
        var content = mobileCM.getValue();
        var ext = tab.ext;
        /* ★ FIX: PHP is minified SERVER-SIDE (safe token_get_all minifier) */
        if (ext === 'php' || ext === 'phtml') {
            toast('🗜️ Minifying…');
            api.code.minify(content, 'php')
                .then(function (res) {
                    if (res && res.code != null && res.code !== content) {
                        setEditorText(res.code);
                        toast('🗜️ Minified');
                    } else {
                        toast('Nothing to minify');
                    }
                })
                .
                catch(function (err) {
                    toast('⚠ Minify failed: ' + err.message);
                });
            return;
        }
        var result = null;
        var minMod = window.IDE.editorMinify;
        if (ext === 'css' || ext === 'scss' || ext === 'sass' || ext === 'less') {
            if (minMod && minMod.minifyCSS) result = minMod.minifyCSS(content);
        } else if (ext === 'html' || ext === 'htm') {
            if (minMod && minMod.minifyHTML) result = minMod.minifyHTML(content);
        } else if (ext === 'js' || ext === 'mjs' || ext === 'ts') {
            if (minMod && minMod.minifyJS) result = minMod.minifyJS(content);
        } else if (ext === 'json') {
            try {
                result = JSON.stringify(JSON.parse(content));
            } catch (e) {
                toast('⚠ Invalid JSON');
                return;
            }
        }
        if (result != null && result !== content) {
            setEditorText(result);
            toast('🗜️ Minified');
        } else if (result === content) {
            toast('Nothing to minify');
        } else {
            toast('No minifier for .' + ext);
        }
    }

    function mobileToggleWrap() {
        if (!mobileCM) {
            toast('No editor');
            return;
        }
        var current = mobileCM.getOption('lineWrapping');
        mobileCM.setOption('lineWrapping', !current);
        mobileCM.refresh();
        var wrapBtn = document.getElementById('mab-wrap');
        if (wrapBtn) wrapBtn.setAttribute('aria-pressed', (!current) ? 'true' : 'false');
        toast(!current ? '↩ Wrap ON' : '↩ Wrap OFF');
    }

    /* ═══════════════════════════════════════════════════════════════
  SKELETON HELPERS (Task 6.3)
  ═══════════════════════════════════════════════════════════════ */
    function showEditorSkeleton() {
        /* The skeleton lives INSIDE #m-editor-wrap, so the wrap must be
       visible (and the empty state hidden) or the shimmer is never seen. */
        var wrap = document.getElementById('m-editor-wrap');
        var empty = document.getElementById('m-editor-empty');
        var skel = document.getElementById('m-editor-skeleton');
        if (wrap) wrap.classList.remove('hidden');
        if (empty) empty.classList.add('hidden');
        if (skel) skel.classList.remove('hidden');
    }

    function hideEditorSkeleton() {
        var skel = document.getElementById('m-editor-skeleton');
        if (skel) skel.classList.add('hidden');
    }

    /* ═══════════════════════════════════════════════════════════════
  FIND IN FILE (Task 3.5)
  ═══════════════════════════════════════════════════════════════ */
    var findBarOpen = false;
    var findMarks = [];
    var findMatches = [];
    var findIndex = -1;
    var findInputTimer = null;
    var findChangeTimer = null;
    var findChangeWired = false;

    function getFindEls() {
        return {
            bar: document.getElementById('m-find-bar'),
            input: document.getElementById('m-find-input'),
            count: document.getElementById('m-find-count'),
            prev: document.getElementById('m-find-prev'),
            next: document.getElementById('m-find-next'),
            close: document.getElementById('m-find-close')
        };
    }

    function toggleFindBar() {
        if (findBarOpen) closeFindBar();
        else openFindBar();
    }

    function openFindBar() {
        var els = getFindEls();
        if (!els.bar) return;
        if (!mobileCM) {
            toast('Open a file first');
            return;
        }

        findBarOpen = true;
        els.bar.classList.add('open');
        wireFindBar();

        if (els.input.value.trim()) runFind(els.input.value);

        setTimeout(function () {
            els.input.focus();
            els.input.select();
        }, 220);
    }

    function closeFindBar() {
        var els = getFindEls();
        if (!els.bar) return;
        findBarOpen = false;
        els.bar.classList.remove('open');
        clearFindMarks();
        updateFindCount(0, -1);
        if (mobileCM) mobileCM.focus();
    }

    function wireFindBar() {
        var els = getFindEls();
        if (els.bar._wired) return;
        els.bar._wired = true;

        els.input.addEventListener('input', function () {
            clearTimeout(findInputTimer);
            var q = els.input.value;
            findInputTimer = setTimeout(function () {
                runFind(q);
            }, 220);
        });

        els.input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                if (e.shiftKey) prevMatch();
                else nextMatch();
            }
            if (e.key === 'Escape') {
                e.preventDefault();
                closeFindBar();
            }
        });

        els.prev.addEventListener('click', function () {
            prevMatch();
            els.input.focus();
        });
        els.next.addEventListener('click', function () {
            nextMatch();
            els.input.focus();
        });
        els.close.addEventListener('click', function () {
            closeFindBar();
        });

        if (mobileCM && !findChangeWired) {
            findChangeWired = true;
            mobileCM.on('change', function () {
                if (findBarOpen && els.input.value.trim()) {
                    clearTimeout(findChangeTimer);
                    findChangeTimer = setTimeout(function () {
                        runFind(els.input.value, false);
                    }, 400);
                }
            });
        }
    }

    function clearFindMarks() {
        findMarks.forEach(function (m) {
            try {
                m.clear();
            } catch (e) { }
        });
        findMarks = [];
        findMatches = [];
        findIndex = -1;
    }

    function runFind(query, navigate) {
        clearFindMarks();
        query = (query || '')
            .trim();
        if (!mobileCM || !query) {
            updateFindCount(0, -1);
            return;
        }

        var cursor = mobileCM.getSearchCursor(query, null, {
            caseFold: true
        });
        var safety = 0;
        while (cursor.findNext() && safety < 5000) {
            findMatches.push({
                from: cursor.from(),
                to: cursor.to()
            });
            findMarks.push(mobileCM.markText(cursor.from(), cursor.to(), {
                className: 'cm-m-search-hl'
            }));
            safety++;
        }

        if (findMatches.length > 0) {
            if (navigate !== false) goToMatch(0);
            else updateFindCount(findMatches.length, -1);
        } else {
            updateFindCount(0, -1);
        }
    }

    function goToMatch(idx) {
        if (!mobileCM || findMatches.length === 0) return;

        if (findIndex >= 0 && findMarks[findIndex]) {
            try {
                findMarks[findIndex].clear();
                findMarks[findIndex] = mobileCM.markText(
                    findMatches[findIndex].from, findMatches[findIndex].to, {
                    className: 'cm-m-search-hl'
                });
            } catch (e) { }
        }

        findIndex = idx;

        if (findMarks[findIndex]) {
            try {
                findMarks[findIndex].clear();
                findMarks[findIndex] = mobileCM.markText(
                    findMatches[findIndex].from, findMatches[findIndex].to, {
                    className: 'cm-m-search-hl-active'
                });
            } catch (e) { }
        }

        var m = findMatches[idx];
        mobileCM.setCursor(m.from);
        mobileCM.scrollIntoView(m.from, 80);
        updateFindCount(findMatches.length, idx);
    }

    function nextMatch() {
        if (findMatches.length === 0) return;
        if (findIndex < 0) goToMatch(0);
        else goToMatch((findIndex + 1) % findMatches.length);
    }

    function prevMatch() {
        if (findMatches.length === 0) return;
        if (findIndex < 0) goToMatch(findMatches.length - 1);
        else goToMatch((findIndex - 1 + findMatches.length) % findMatches.length);
    }

    function updateFindCount(total, current) {
        var els = getFindEls();
        if (!els.count) return;
        if (total === 0) {
            els.count.textContent = '0 / 0';
            els.count.classList.remove('has-match');
        } else if (current < 0) {
            els.count.textContent = total + ' found';
            els.count.classList.add('has-match');
        } else {
            els.count.textContent = (current + 1) + ' / ' + total;
            els.count.classList.add('has-match');
        }
        if (els.prev) els.prev.disabled = total === 0;
        if (els.next) els.next.disabled = total === 0;
    }

    /* ═══════════════════════════════════════════════════════════════
  EDITOR ZOOM (manual buttons + pinch-to-zoom)
  ═══════════════════════════════════════════════════════════════ */
    var ZOOM_MIN = 8;
    var ZOOM_MAX = 32;
    var ZOOM_DEFAULT = 16;
    var ZOOM_STEP = 1;
    var LS_ZOOM = 'quirky.ide.mobile.editorzoom';
    var editorZoom = ZOOM_DEFAULT;
    var zoomAccumulator = 0;

    try {
        var storedZoom = parseInt(localStorage.getItem(LS_ZOOM), 10);
        if (isFinite(storedZoom)) editorZoom = Math.max(ZOOM_MIN, Math.min(ZOOM_MAX, storedZoom));
    } catch (e) { }

    function applyEditorZoom(px, persist, animate, instant) {
        editorZoom = Math.max(ZOOM_MIN, Math.min(ZOOM_MAX, Math.round(px)));

        /* instant = pinch commit: suspend the 0.15s font-size CSS transition
       so the editor appears at the FINAL size in one frame. With the
       transition left on, the text visibly grows/shrinks after the
       snapshot disappears — that was the "bounce back". */
        var cmEl = mEditorWrap ? mEditorWrap.querySelector('.CodeMirror') : null;
        if (instant && cmEl) cmEl.style.transition = 'none';

        if (mEditorWrap) mEditorWrap.style.setProperty('--m-ed-fs', editorZoom + 'px');

        var chip = document.getElementById('mab-zoom-reset');
        if (chip) {
            chip.textContent = String(editorZoom);
            if (animate !== false) {
                chip.classList.remove('zoom-pulse');
                void chip.offsetWidth;
                chip.classList.add('zoom-pulse');
            }
        }

        if (mobileCM) {
            requestAnimationFrame(function () {
                if (mobileCM) mobileCM.refresh();
                /* transition back on afterwards (harmless when it was never
           suspended — the A− / A+ buttons keep their smooth feel) */
                if (cmEl) cmEl.style.transition = '';
            });
        }

        if (persist) {
            try {
                localStorage.setItem(LS_ZOOM, String(editorZoom));
            } catch (e) { }
        }
        updateZoomBounds();
    }

    function updateZoomBounds() {
        var bOut = document.getElementById('mab-zoom-out');
        var bIn = document.getElementById('mab-zoom-in');
        if (bOut) {
            bOut.disabled = editorZoom <= ZOOM_MIN;
            bOut.style.opacity = editorZoom <= ZOOM_MIN ? '0.3' : '1';
        }
        if (bIn) {
            bIn.disabled = editorZoom >= ZOOM_MAX;
            bIn.style.opacity = editorZoom >= ZOOM_MAX ? '0.3' : '1';
        }
    }

    function wireZoomButtons() {
        var bOut = document.getElementById('mab-zoom-out');
        var bIn = document.getElementById('mab-zoom-in');
        var bReset = document.getElementById('mab-zoom-reset');
        var repeatTimer = null;
        var repeatInterval = null;

        function startRepeat(direction) {
            stopRepeat();
            applyEditorZoom(editorZoom + direction, true);
            repeatTimer = setTimeout(function () {
                repeatInterval = setInterval(function () {
                    var next = editorZoom + direction;
                    if (next < ZOOM_MIN || next > ZOOM_MAX) {
                        stopRepeat();
                        return;
                    }
                    applyEditorZoom(next, true);
                }, 120);
            }, 400);
        }

        function stopRepeat() {
            clearTimeout(repeatTimer);
            clearInterval(repeatInterval);
            repeatTimer = null;
            repeatInterval = null;
        }

        if (bOut) {
            bOut.addEventListener('pointerdown', function (e) {
                e.preventDefault();
                startRepeat(-ZOOM_STEP);
            });
            bOut.addEventListener('pointerup', stopRepeat);
            bOut.addEventListener('pointerleave', stopRepeat);
        }
        if (bIn) {
            bIn.addEventListener('pointerdown', function (e) {
                e.preventDefault();
                startRepeat(ZOOM_STEP);
            });
            bIn.addEventListener('pointerup', stopRepeat);
            bIn.addEventListener('pointerleave', stopRepeat);
        }
        if (bReset) {
            bReset.addEventListener('click', function () {
                if (editorZoom !== ZOOM_DEFAULT) {
                    applyEditorZoom(ZOOM_DEFAULT, true);
                    toast('Text size reset to ' + ZOOM_DEFAULT + 'px');
                }
            });
        }
        updateZoomBounds();
    }

    /* ═══════════════════════════════════════════════════════════════
  PINCH-TO-ZOOM (two-finger gesture on the editor surface)
  ═══════════════════════════════════════════════════════════════
  Two fingers pinch apart → zoom in, pinch together → zoom out.

  Fixes over the previous attempt:
  • CAPTURE-phase listeners — we see the touch before CodeMirror's
    own contenteditable touch/selection handling does, and
    stopPropagation once 2 fingers are confirmed so CM never starts
    a competing gesture underneath the pinch.
  • Live feedback is a CSS transform: scale() on the mount, not a
    font-size change — scaling is a compositor-only operation with
    no layout cost, so it stays smooth even on long/complex files.
    (Changing font-size every frame, even via a CSS var, forces the
    browser to re-lay-out the text on every frame — that alone was
    a big chunk of the previous jank, separate from refresh().)
  • mobileCM.refresh() and the real font-size commit happen ONCE,
    on release — never during the drag.
  • No momentum/velocity-based overshoot on release. Momentum was
    the direct cause of "zooms the wrong way on release": it was
    computed from a single unsmoothed touch sample, and that sample
    is the noisiest one you'll ever see (finger lift-off jitter).
    Pinch-to-zoom conventions (Photos, Docs, Maps) settle exactly
    where your fingers left it — no coasting.
  • Direct 1:1 tracking of finger distance → zoom, no EMA lag. The
    dead zone still absorbs small jitter at gesture start.
  ═══════════════════════════════════════════════════════════════ */
    var PINCH_DEAD_ZONE = 12; // px of movement before zoom activates
    var PINCH_MIN_FINGERS = 2;
    var pinchState = {
        active: false, // dead zone cleared, actively scaling
        tracking: false, // 2 fingers down, watching for dead zone
        ids: [null, null],
        startDist: 0,
        startZoom: ZOOM_DEFAULT,
        liveZoom: ZOOM_DEFAULT,
        rafPending: false,
        latestTouches: null,
        snapshotEl: null, // lightweight static copy scaled during the gesture
        originX: 0, // pinch centroid (relative to the editor wrap)
        originY: 0, //   = the anchor the snapshot zooms around
        startScrollTop: 0, // editor scroll position when the gesture began
        startScrollLeft: 0
    };

    function pinchDistance(t1, t2) {
        var dx = t1.clientX - t2.clientX;
        var dy = t1.clientY - t2.clientY;
        return Math.sqrt(dx * dx + dy * dy);
    }

    function pinchFindTouches(touchList) {
        var found = [null, null];
        for (var i = 0; i < touchList.length; i++) {
            var t = touchList[i];
            if (t.identifier === pinchState.ids[0]) found[0] = t;
            else if (t.identifier === pinchState.ids[1]) found[1] = t;
        }
        return found;
    }

    /* ── Snapshot helpers (tearing fix) ─────────────────────────────
     We clone only what CodeMirror has painted and clip it to the
     screen, so the GPU gets a SMALL texture to scale during the
     gesture. The live editor is hidden underneath for the duration
     of the pinch and restored afterwards. */
    function createPinchSnapshot(t1, t2) {
        if (!mEditorWrap || !mobileCM) return;
        /* Remove any leftover overlay from a previous gesture */
        var old = mEditorWrap.querySelectorAll('.m-ed-pinch-overlay');
        for (var i = 0; i < old.length; i++) old[i].remove();
        var cmEl = mEditorWrap.querySelector('.CodeMirror');
        if (!cmEl) return;

        /* Remember the finger midpoint (zoom anchor) and the current
       scroll position. The same numbers are used on release to put
       the exact same line back under your fingers — that removes
       the little jump/bounce the hand-back used to have. */
        var rect = mEditorWrap.getBoundingClientRect();
        pinchState.originX = Math.round((t1.clientX + t2.clientX) / 2 - rect.left);
        pinchState.originY = Math.round((t1.clientY + t2.clientY) / 2 - rect.top);
        var scroller = cmEl.querySelector('.CodeMirror-scroll');
        pinchState.startScrollTop = scroller ? scroller.scrollTop : 0;
        pinchState.startScrollLeft = scroller ? scroller.scrollLeft : 0;

        var overlay = document.createElement('div');
        overlay.className = 'm-ed-pinch-overlay';
        var bg = '';
        try {
            bg = window.getComputedStyle(cmEl)
                .backgroundColor;
        } catch (e) { }
        overlay.style.background = (bg && bg !== 'transparent') ? bg : '#0b111c';
        /* Zoom around your fingers, not around the top-left corner */
        overlay.style.transformOrigin = pinchState.originX + 'px ' + pinchState.originY + 'px';

        var clone = cmEl.cloneNode(true);
        var cur = clone.querySelector('.CodeMirror-cursor');
        if (cur) cur.remove();
        var cloneScroller = clone.querySelector('.CodeMirror-scroll');
        if (cloneScroller) cloneScroller.style.overflow = 'hidden';
        overlay.appendChild(clone);
        mEditorWrap.appendChild(overlay);
        /* Freeze the clone at the same scroll position as the live editor */
        if (scroller && cloneScroller) {
            cloneScroller.scrollTop = pinchState.startScrollTop;
            cloneScroller.scrollLeft = pinchState.startScrollLeft;
        }
        /* Hide the live editor (and its mount, belt & braces) so no ghost
       copy can shine through under the snapshot */
        cmEl.style.visibility = 'hidden';
        if (mEditorMount) mEditorMount.style.visibility = 'hidden';
        pinchState.snapshotEl = overlay;
    }

    function revealLiveEditor() {
        if (mEditorMount) mEditorMount.style.visibility = '';
        if (mEditorWrap) {
            var cmEl = mEditorWrap.querySelector('.CodeMirror');
            if (cmEl) cmEl.style.visibility = '';
        }
    }

    function pinchApplyFrame() {
        pinchState.rafPending = false;
        if (!pinchState.active || !pinchState.latestTouches) return;
        var touches = pinchFindTouches(pinchState.latestTouches);
        if (!touches[0] || !touches[1]) return;
        var dist = pinchDistance(touches[0], touches[1]);
        if (dist <= 0 || pinchState.startDist <= 0) return;
        var ratio = dist / pinchState.startDist;
        var target = Math.max(ZOOM_MIN, Math.min(ZOOM_MAX, pinchState.startZoom * ratio));
        pinchState.liveZoom = target;
        /* Live preview: scale the tiny snapshot overlay ONLY. The real
       CodeMirror is never transformed — stretching its huge paint
       layer is exactly what caused the tearing / black-out. */
        if (pinchState.snapshotEl) {
            var visualScale = target / pinchState.startZoom;
            pinchState.snapshotEl.style.transform = 'scale(' + visualScale + ')';
        }
        var chip = document.getElementById('mab-zoom-reset');
        if (chip) chip.textContent = String(Math.round(target));
    }

    function pinchQueueFrame() {
        if (pinchState.rafPending) return;
        pinchState.rafPending = true;
        requestAnimationFrame(pinchApplyFrame);
    }

    function pinchReset(keepSnapshot) {
        pinchState.active = false;
        pinchState.tracking = false;
        pinchState.ids = [null, null];
        pinchState.latestTouches = null;
        if (!keepSnapshot) {
            if (pinchState.snapshotEl) {
                pinchState.snapshotEl.remove();
                pinchState.snapshotEl = null;
            }
            revealLiveEditor();
        }
        if (mEditorMount) {
            mEditorMount.style.transform = '';
            mEditorMount.style.transformOrigin = '';
            mEditorMount.style.willChange = '';
        }
    }

    function initPinchZoom() {
        var limits = (window.IDE_CONFIG && window.IDE_CONFIG.deviceLimits) || {};
        if (limits.gestures === false) return; // ★ LOCKDOWN

        if (!mEditorWrap) return;

        /* CAPTURE phase: intercept before CodeMirror's own touch handling */
        mEditorWrap.addEventListener('touchstart', function (e) {
            if (e.touches.length === PINCH_MIN_FINGERS && !pinchState.tracking) {
                var t1 = e.touches[0],
                    t2 = e.touches[1];
                pinchState.ids[0] = t1.identifier;
                pinchState.ids[1] = t2.identifier;
                pinchState.startDist = pinchDistance(t1, t2);
                pinchState.startZoom = editorZoom;
                pinchState.liveZoom = editorZoom;
                pinchState.tracking = true;
                pinchState.active = false; // still waiting for dead zone
            } else if (e.touches.length > PINCH_MIN_FINGERS && pinchState.tracking) {
                /* Stray 3rd finger mid-gesture — swallow it, don't let it
        reach CodeMirror and disrupt the drag */
                e.preventDefault();
                e.stopPropagation();
            }
        }, {
            passive: false,
            capture: true
        });

        mEditorWrap.addEventListener('touchmove', function (e) {
            if (!pinchState.tracking || e.touches.length < PINCH_MIN_FINGERS) return;

            var touches = pinchFindTouches(e.touches);
            if (!touches[0] || !touches[1]) return;

            var dist = pinchDistance(touches[0], touches[1]);
            var delta = dist - pinchState.startDist;

            if (!pinchState.active) {
                if (Math.abs(delta) < PINCH_DEAD_ZONE) {
                    /* Still inside the dead zone — let CodeMirror alone in case
          this turns out to be a scroll/selection, not a pinch */
                    return;
                }
                /* Dead zone cleared — commit to pinch-zoom and take over the
        gesture fully so CodeMirror doesn't also react to it */
                pinchState.active = true;
                pinchState.startDist = dist - (delta > 0 ? PINCH_DEAD_ZONE : -PINCH_DEAD_ZONE);
                createPinchSnapshot(touches[0], touches[1]);
            }

            e.preventDefault();
            e.stopPropagation();

            pinchState.latestTouches = e.touches;
            pinchQueueFrame();
        }, {
            passive: false,
            capture: true
        });

        function pinchEnd(e) {
            var remaining = 0;
            for (var i = 0; i < e.touches.length; i++) {
                if (e.touches[i].identifier === pinchState.ids[0] || e.touches[i].identifier === pinchState.ids[1]) {
                    remaining++;
                }
            }
            if (remaining >= PINCH_MIN_FINGERS) return; // still pinching

            var wasActive = pinchState.active;
            var finalZoom = pinchState.liveZoom;
            var snap = pinchState.snapshotEl;
            var sTop = pinchState.startScrollTop;
            var sLeft = pinchState.startScrollLeft;
            var ox = pinchState.originX;
            var oy = pinchState.originY;
            var baseZoom = pinchState.startZoom;

            pinchState.snapshotEl = null; // we manage the snapshot ourselves now
            pinchReset(true); // reset gesture state, keep snapshot for now

            if (wasActive) {
                /* Single commit: real font-size change + the ONE necessary
        CodeMirror refresh for the whole gesture, persisted.
        4th argument = INSTANT: the 0.15s font-size CSS transition is
        skipped for this change, so the real editor appears at the
        final size in one frame — no grow/shrink "bounce back". */
                applyEditorZoom(finalZoom, true, true, true);

                /* Put the exact same line back under your fingers: re-scroll
           with the same math the snapshot was scaled with, so the
           hand-back from snapshot → real editor is seamless. */
                var s = finalZoom / baseZoom;
                var scroller = mEditorWrap ? mEditorWrap.querySelector('.CodeMirror-scroll') : null;
                if (scroller && s !== 1) {
                    scroller.scrollTop = Math.max(0, Math.round(s * sTop + (s - 1) * oy));
                    scroller.scrollLeft = Math.max(0, Math.round(s * sLeft + (s - 1) * ox));
                }

                /* Keep the snapshot on screen until the real editor has
        repainted at the new size (two animation frames), then clean
        up — no flash, no pop. */
                window.requestAnimationFrame(function () {
                    window.requestAnimationFrame(function () {
                        if (snap && snap.parentNode) snap.parentNode.removeChild(snap);
                        if (!pinchState.snapshotEl) revealLiveEditor();
                    });
                });
            } else {
                if (snap && snap.parentNode) snap.parentNode.removeChild(snap);
                revealLiveEditor();
            }
        }

        mEditorWrap.addEventListener('touchend', pinchEnd, {
            capture: true,
            passive: true
        });
        /* Wrapped so the Event object can't be misread as keepSnapshot=true */
        mEditorWrap.addEventListener('touchcancel', function () {
            pinchReset(false);
        }, {
            capture: true,
            passive: true
        });
    }

    /* ═══════════════════════════════════════════════════════════════
  AUTO-SUGGEST TOGGLE (💡 action-bar button)
  ─────────────────────────────────────────────────────────────────
  Toggles autocomplete's "popup while typing" mode. The state lives
  inside autocomplete.js (persisted via the settings key
  'acAutoPopup'), so Settings → Editor and this button stay in sync.
  The keyboard-helper 💡 key is untouched — it remains a MANUAL
  trigger, which stays handy even when auto-suggest is OFF.
  ═══════════════════════════════════════════════════════════════ */
    function autoSuggestOn() {
        var AC = window.IDE.autocomplete;
        return !!(AC && typeof AC.isAutoPopup === 'function' && AC.isAutoPopup());
    }

    function updateAutoSuggestUI() {
        var btn = document.getElementById('mab-ac');
        if (!btn) return;
        var on = autoSuggestOn();
        btn.classList.toggle('mab-active', on);
        btn.setAttribute('aria-pressed', on ? 'true' : 'false');
        btn.title = on ? 'Auto-suggest ON — tap to turn off' : 'Auto-suggest OFF — tap to turn on';
    }

    function toggleAutoSuggest() {
        var AC = window.IDE.autocomplete;
        if (!AC || typeof AC.setAutoPopup !== 'function') {
            toast('⚠ Autocomplete module not loaded');
            return;
        }
        if (typeof AC.isEnabled === 'function' && !AC.isEnabled()) {
            toast('⚠ Autocomplete is turned off in Settings → Features');
            return;
        }
        var on = AC.setAutoPopup(!autoSuggestOn());
        updateAutoSuggestUI();
        toast(on ? '💡 Auto-suggest ON — snippets appear as you type' : '💡 Auto-suggest OFF — use the 💡 key to trigger manually');
    }

    /* ═══════════════════════════════════════════════════════════════
  ACTION BAR WIRING
  ═══════════════════════════════════════════════════════════════ */
    function wireActionBar() {
        var btn;

        btn = document.getElementById('mab-save');
        if (btn) btn.addEventListener('click', function () {
            haptic();
            mobileEditorSave();
        });

        btn = document.getElementById('mab-format');
        if (btn) btn.addEventListener('click', function () {
            haptic();
            mobileFormat();
        });

        btn = document.getElementById('mab-shrink');
        if (btn) btn.addEventListener('click', function () {
            haptic();
            mobileShrink();
        });

        /* ★ Auto-suggest toggle (💡) — keyboard-helper 💡 stays a manual trigger */
        btn = document.getElementById('mab-ac');
        if (btn) btn.addEventListener('click', function () {
            haptic();
            toggleAutoSuggest();
        });

        btn = document.getElementById('mab-undo');
        if (btn) btn.addEventListener('click', function () {
            haptic();
            if (mobileCM) {
                mobileCM.undo();
                mobileCM.focus();
                var tab = getActiveTab();
                var fileName = tab ? tab.name : 'file';
                showActionToast('↶ Undo — ' + fileName, 'Redo', function () {
                    if (mobileCM) {
                        mobileCM.redo();
                        mobileCM.focus();
                        var t2 = getActiveTab();
                        var fn2 = t2 ? t2.name : 'file';
                        showActionToast('↷ Redo — ' + fn2, 'Undo', function () {
                            if (mobileCM) {
                                mobileCM.undo();
                                mobileCM.focus();
                            }
                        }, 3000);
                    }
                }, 3000);
            }
        });
        btn = document.getElementById('mab-redo');
        if (btn) btn.addEventListener('click', function () {
            haptic();
            if (mobileCM) {
                mobileCM.redo();
                mobileCM.focus();
                var tab = getActiveTab();
                var fileName = tab ? tab.name : 'file';
                showActionToast('↷ Redo — ' + fileName, 'Undo', function () {
                    if (mobileCM) {
                        mobileCM.undo();
                        mobileCM.focus();
                        var t2 = getActiveTab();
                        var fn2 = t2 ? t2.name : 'file';
                        showActionToast('↶ Undo — ' + fn2, 'Redo', function () {
                            if (mobileCM) {
                                mobileCM.redo();
                                mobileCM.focus();
                            }
                        }, 3000);
                    }
                }, 3000);
            }
        });

        btn = document.getElementById('mab-find');
        if (btn) btn.addEventListener('click', function () {
            haptic();
            toggleFindBar();
        });

        btn = document.getElementById('mab-wrap');
        if (btn) btn.addEventListener('click', function () {
            haptic();
            mobileToggleWrap();
        });

        /* Task 8.8: Contextual buttons */
        btn = document.getElementById('mab-run');
        if (btn) btn.addEventListener('click', function () {
            haptic();
            mobileRunFile();
        });

        btn = document.getElementById('mab-preview');
        if (btn) btn.addEventListener('click', function () {
            haptic();
            mobilePreviewFile();
        });
    }

    /* ═══════════════════════════════════════════════════════════════
  EVENT LISTENERS
  ═══════════════════════════════════════════════════════════════ */
    function wireEvents() {
        if (U && U.on) {
            U.on('file:open', function (e) {
                var path = e.detail;
                if (path) openFileInEditor(path);
            });
            /* ★ SAFEGUARD: When a file is deleted, close its editor tab
      so the user isn't editing a ghost file. */
            U.on('file:deleted', function (e) {
                var path = e.detail;
                if (!path) return;
                var tab = getTabByPath(path);
                if (tab) {
                    closeTab(tab.id);
                    toast('🗑️ Closed: ' + tab.name);
                }
            });
            /* ★ SAFEGUARD: When a file is renamed/moved, update the tab's
   path and name so it stays in sync with the file tree.
   ★ v11: ALSO swap the language mode + intel language LIVE —
   stest.php → stest.c instantly gets C colors AND C snippets,
   no close/reopen required. */
            U.on('file:renamed', function (e) {
                var d = e.detail || {};
                if (!d.from || !d.to) return;
                var tab = getTabByPath(d.from);
                if (!tab) return;
                tab.path = d.to;
                tab.name = d.to.split('/')
                    .pop() || d.to;
                tab.ext = tab.name.split('.')
                    .pop()
                    .toLowerCase();
                /* Update the language mode for the new extension */
                var LANGAPI = window.IDE.language;
                if (LANGAPI) {
                    var mi = LANGAPI.modeForExt(tab.ext);
                    tab.mode = mi.mode;
                }
                /* ★ LIVE LANGUAGE SWITCH: apply the new mode to the editor NOW
           (fetching it first if it hasn't been loaded yet), refresh the
           syntax palette attribute, and re-announce the tab to the intel
           modules so autocomplete + error lens follow the rename. */
                if (mobileCM) {
                    mobileCM.setOption('mode', tab.mode);
                    ensureMode(tab.mode, function () {
                        if (mobileCM && getActiveTab() === tab) {
                            mobileCM.setOption('mode', tab.mode);
                            safeRefresh();
                        }
                    });
                }
                if (mobileActiveTabId === tab.id) {
                    setEditorLangAttr(tab.ext);
                    wireIntelForTab(tab);
                }
                /* Refresh the tab bar and header */
                renderTabBar();
                if (U && U.emit) U.emit('editor:tabs-updated'); // Notify tree of path change
                if (mobileActiveTabId === tab.id) {
                    if (mEditorFileLabel) mEditorFileLabel.textContent = tab.name;
                    if (fileNameEl) fileNameEl.textContent = tab.name;
                }
            });

            /* ★ Keep the 💡 button in sync when the toggle changes elsewhere
      (Settings → Editor), via the autocomplete module's bus event. */
            U.on('autocomplete:autopopup-changed', function () {
                updateAutoSuggestUI();
            });
        }
        /* Header save button — saves ALL open files (global action) */
        if (saveBtn) {
            saveBtn.addEventListener('click', function () {
                haptic();
                mobileSaveAll();
            });
        }
    }

    /* ═══════════════════════════════════════════════════════════════
  BOOT
  ═══════════════════════════════════════════════════════════════ */
    function boot() {
        wireActionBar();
        updateAutoSuggestUI(); /* ★ sync 💡 auto-suggest button */
        wireZoomButtons();
        initPinchZoom(); /* ★ Restored: two-finger pinch-to-zoom */
        wireEvents();
        wireTabOverflow(); /* Task 8.2: overflow indicators + scroll listener */
        applyEditorZoom(editorZoom, false);
        setTimeout(updateAutoSuggestUI, 400); /* ★ re-sync after all modules boot */
        /* Repaint the editor whenever the screen size changes
       (rotation, keyboard opening/closing, tab switches). */
        window.addEventListener('resize', function () {
            safeRefresh();
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }

    /* ═══════════════════════════════════════════════════════════════
  EXPOSE
  ═══════════════════════════════════════════════════════════════ */
    window.IDE = window.IDE || {};

    /**
     * @namespace window.IDE.mobileEditor
     * Mobile multi-tab code editor module.
     */
    window.IDE.mobileEditor = {
        /** Open a file in the editor (or switch to it if already open). */
        openFile: openFileInEditor,
        /** Switch to a tab by its internal ID. */
        switchTo: switchToTab,
        /** Close a tab by its internal ID. */
        close: closeTab,
        /** Save the currently active file. */
        save: mobileEditorSave,
        /** Save all open files with unsaved changes. */
        saveAll: mobileSaveAll,
        /** Format the active file. */
        format: mobileFormat,
        /** Minify/shrink the active file. */
        shrink: mobileShrink,
        /** Toggle word wrap. */
        toggleWrap: mobileToggleWrap,
        /** Get the currently active tab object (or null). */
        getActiveTab: getActiveTab,
        /** Get all open tabs. */
        getTabs: function () {
            return mobileTabs;
        },
        /** v10: true when any open tab has unsaved changes (Android BACK guard). */
        hasDirtyTabs: function () {
            for (var i = 0; i < mobileTabs.length; i++) {
                if (mobileTabs[i].dirty) return true;
            }
            return false;
        },
        /** Get the raw CodeMirror instance (or null before init). */
        getCM: function () {
            return mobileCM;
        },
        /** Check if the editor is initialised. */
        isReady: function () {
            return mobileEditorReady;
        },
        /** Ensure the editor is loaded and ready (call when Editor tab opens). */
        ensureInit: ensureInit,
        /** Force a CodeMirror refresh (call after layout changes). */
        refresh: function () {
            if (mobileCM) mobileCM.refresh();
        },
        /** Toggle the find-in-file bar. */
        toggleFind: toggleFindBar,
        /** Re-check tab-bar overflow badges (call when the editor becomes visible). */
        refreshOverflow: updateTabOverflowIndicators,
        /** ★ Sync the 💡 auto-suggest action-bar button (settings changes call this). */
        updateAutoSuggestUI: updateAutoSuggestUI
    };
})();