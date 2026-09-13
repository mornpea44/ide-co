/**
* ═══════════════════════════════════════════════════════════════════════════
*  QUIRKY IDE — MOBILE SHELL BOOTSTRAP (Phase 7 · Task 7.9)
* ═══════════════════════════════════════════════════════════════════════════
*
*  The "app.js" equivalent for the mobile shell. Wires everything together:
*    • Lazy module loader (Task 6.5)
*    • Tab switching + panel visibility
*    • Bottom sheet open/close
*    • Header wiring (palette trigger, overflow menu)
*    • "More" sheet routing (Settings, AI, Git, HTTP, Desktop)
*    • AI assistant overlay open/close
*    • Offline indicator (Task 6.4)
*    • FAB visibility (delegates to mobile-filetree.js)
*    • Global Escape key handler
*    • Saved-tab restore
*
*  This file loads LAST in the mobile JS bundle (after all other modules),
*  exactly like app.js does on desktop.
*
*  EXPOSES: window._mobileSwitchTab (bridge for other modules)
* ═══════════════════════════════════════════════════════════════════════════
*/
(function () {
  'use strict';

  /* ═══════════════════════════════════════════════════════════════
  TASK 6.5 — LAZY MODULE LOADER INFRASTRUCTURE
  Modules are registered here but only initialised the first time
  their tab/overlay is opened (see switchTab below).
  ═══════════════════════════════════════════════════════════════ */
  var _modules = {
    preview: { loaded: false, init: null },
    terminal: { loaded: false, init: null },
    git: { loaded: false, init: null },
    http: { loaded: false, init: null },
    palette: { loaded: false, init: null }
  };

  function ensureModule(name) {
    var m = _modules[name];
    if (!m || m.loaded) return;
    // ★ FIX (boot race): if the init hook hasn't been registered yet,
    // do NOT burn the one-shot flag — just retry on the next call.
    if (typeof m.init !== 'function') return;
    m.loaded = true;
    m.init();
  }

  /* ═══════════════════════════════════════════════════════════════
  DOM REFERENCES
  ═══════════════════════════════════════════════════════════════ */
  var LS_TAB = 'quirky.ide.mobile.tab';

  var panels = {
    files: document.getElementById('mp-files'),
    editor: document.getElementById('mp-editor'),
    preview: document.getElementById('mp-preview'),
    terminal: document.getElementById('mp-terminal')
  };
  var tabBtns = document.querySelectorAll('#mobile-tabbar .mt-btn');
  var moreSheet = document.getElementById('ms-more');
  var moreBackdrop = document.getElementById('ms-backdrop');
  var fileNameEl = document.getElementById('mh-file');

  /* ═══════════════════════════════════════════════════════════════
  HELPERS
  ═══════════════════════════════════════════════════════════════ */
  function haptic() {
    if (window.IDE && window.IDE.mobileActions && window.IDE.mobileActions.haptic) {
      window.IDE.mobileActions.haptic();
      return;
    }
    try { if (navigator.vibrate) navigator.vibrate(10); } catch (e) { }
  }

  function toast(msg) {
    var U = window.IDE && window.IDE.utils;
    if (U && U.toast) U.toast(msg);
  }

  /* ═══════════════════════════════════════════════════════════════
  TASK 6.4 — OFFLINE INDICATOR
  Shows a banner when navigator.onLine is false. The editor and
  file system still work since everything is local.
  ═══════════════════════════════════════════════════════════════ */
  (function initOfflineIndicator() {
    var banner = document.getElementById('m-offline-banner');
    if (!banner) return;
    function updateStatus() {
      if (navigator.onLine) {
        banner.classList.add('hidden');
      } else {
        banner.classList.remove('hidden');
      }
    }
    window.addEventListener('online', updateStatus);
    window.addEventListener('offline', updateStatus);
    updateStatus();
  })();

  /* ═══════════════════════════════════════════════════════════════
  FAB VISIBILITY
  Gets the FAB element locally (it lives inside mobile-filetree.js's
  scope, so we can't reference a shared variable). Shows the FAB
  only when the Files panel is active.
  ═══════════════════════════════════════════════════════════════ */
  function updateFabVisibility() {
    var fab = document.getElementById('m-fab');
    if (!fab) return;
    var activePanel = document.querySelector('.m-panel:not(.hidden)');
    if (activePanel && activePanel.id === 'mp-files') {
      fab.classList.remove('hidden');
    } else {
      fab.classList.add('hidden');
    }
  }

  /* ═══════════════════════════════════════════════════════════════
  TAB SWITCHING
  The central routing function. Every tab change goes through here.
  Handles panel visibility, lazy module init, and FAB show/hide.
  ═══════════════════════════════════════════════════════════════ */
  function switchTab(tabName) {
    /* "More" opens a sheet instead of switching panels */
    if (tabName === 'more') {
      openSheet(moreSheet, moreBackdrop);
      return;
    }
    if (!panels[tabName]) return;
    /* The tree-tools (🔍) button only makes sense on the Files/Explorer tab */
    var treeToolsBtn = document.getElementById('mh-tree-tools');
    if (treeToolsBtn) {
      treeToolsBtn.style.display = (tabName === 'files') ? '' : 'none';
    }

    /* Hide all panels, show the selected one */
    Object.keys(panels).forEach(function (key) {
      if (panels[key]) panels[key].classList.add('hidden');
    });
    panels[tabName].classList.remove('hidden');

    /* Update tab bar active state */
    tabBtns.forEach(function (btn) {
      btn.classList.toggle('active', btn.getAttribute('data-panel') === tabName);
    });

    /* Persist the choice */
    try { localStorage.setItem(LS_TAB, tabName); } catch (e) { }

    /* ── Per-tab lazy init ── */
    if (tabName === 'files') {
      if (window.IDE.mobileFileTree) window.IDE.mobileFileTree.ensureLoaded();
    }
    if (tabName === 'editor') {
      if (window.IDE.mobileEditor) {
        window.IDE.mobileEditor.ensureInit();
        /* Repaint tab-bar overflow badges now that the panel is visible */
        requestAnimationFrame(function () {
          if (window.IDE.mobileEditor.refreshOverflow) window.IDE.mobileEditor.refreshOverflow();
        });
      }
    }
    if (tabName === 'preview') {
      ensureModule('preview');
      if (window.IDE.mobilePreview && window.IDE.mobilePreview.onTabShown) {
        window.IDE.mobilePreview.onTabShown();
      }
    }
    if (tabName === 'terminal') {
      ensureModule('terminal');
      if (window.IDE.mobileTerminal && window.IDE.mobileTerminal.onTabShown) {
        window.IDE.mobileTerminal.onTabShown();
      }
    }

    /* Show/hide the FAB */
    updateFabVisibility();
  }

  /* ═══════════════════════════════════════════════════════════════
  TASK 9.7 — PRELOAD ADJACENT PANEL
  Loads a panel's data WITHOUT switching to it. Called by the
  swipe gesture detector as soon as swipe direction is detected,
  so data is ready before the user releases their finger.
  ═══════════════════════════════════════════════════════════════ */
  function preloadTab(tabName) {
    if (!panels[tabName]) return;
    if (tabName === 'files') {
      if (window.IDE.mobileFileTree) window.IDE.mobileFileTree.ensureLoaded();
    }
    if (tabName === 'editor') {
      if (window.IDE.mobileEditor) {
        window.IDE.mobileEditor.ensureInit();
        /* Repaint tab-bar overflow badges now that the panel is visible */
        requestAnimationFrame(function () {
          if (window.IDE.mobileEditor.refreshOverflow) window.IDE.mobileEditor.refreshOverflow();
        });
      }
    }
    if (tabName === 'preview') {
      ensureModule('preview');
    }
    if (tabName === 'terminal') {
      ensureModule('terminal');
    }
  }

  /* Cross-module bridge: expose preloadTab for mobile-gestures.js */
  window._mobilePreloadTab = preloadTab;

  /* Cross-module bridge: expose switchTab so other modules (file tree,
     palette, editor) can programmatically switch the active panel.
     Without this, tapping a file opens it in the editor but the UI
     never switches away from the Files panel. */
  window._mobileSwitchTab = switchTab;

  /* ═══════════════════════════════════════════════════════════════
  SHEET OPEN / CLOSE
  Reusable helpers for the bottom-sheet pattern.
  ═══════════════════════════════════════════════════════════════ */
  function openSheet(sheet, backdrop) {
    if (!sheet || !backdrop) return;
    backdrop.classList.remove('hidden');
    sheet.classList.remove('hidden');
    void sheet.offsetWidth; /* force reflow for CSS transition */
    sheet.classList.add('show');
    backdrop.classList.add('show');
  }

  function closeSheet(sheet, backdrop) {
    if (!sheet || !backdrop) return;
    sheet.classList.remove('show');
    backdrop.classList.remove('show');
    setTimeout(function () {
      sheet.classList.add('hidden');
      backdrop.classList.add('hidden');
    }, 280);
  }

  function closeAllSheets() {
    closeSheet(moreSheet, moreBackdrop);
  }

  /* ═══════════════════════════════════════════════════════════════
 WIRE: TAB BUTTONS (Dynamic Panels + Actions)
 ═══════════════════════════════════════════════════════════════ */
  tabBtns.forEach(function (btn) {
    btn.addEventListener('click', function () {
      haptic();
      var panel = btn.getAttribute('data-panel');
      var action = btn.getAttribute('data-action');
      if (panel) {
        switchTab(panel);
      } else if (action) {
        handleTabAction(action);
      }
    });
  });

  function handleTabAction(action) {
    if (action === 'settings') {
      if (window.IDE.settings && window.IDE.settings.open) window.IDE.settings.open();
    } else if (action === 'workshop') {
      if (window._mobileOpenWorkshop) window._mobileOpenWorkshop();
    } else if (action === 'ai') {
      openAiOverlay();
    } else if (action === 'git') {
      if (window._mobileOpenGit) window._mobileOpenGit();
    } else if (action === 'http') {
      if (window._mobileOpenHttp) window._mobileOpenHttp();
    }
  }

  /* ═══════════════════════════════════════════════════════════════
  WIRE: "MORE" SHEET
  Routes each row to the correct action: Settings, AI, Git, HTTP,
  or Switch to Desktop.
  ═══════════════════════════════════════════════════════════════ */
  if (moreBackdrop) moreBackdrop.addEventListener('click', function () {
    closeSheet(moreSheet, moreBackdrop);
  });

  if (moreSheet) {
    var moreHandle = moreSheet.querySelector('.m-sheet-handle');
    if (moreHandle) moreHandle.addEventListener('click', function () {
      closeSheet(moreSheet, moreBackdrop);
    });

    moreSheet.querySelectorAll('.m-sheet-row').forEach(function (row) {
      row.addEventListener('click', function () {
        haptic();
        var action = row.getAttribute('data-action');
        closeSheet(moreSheet, moreBackdrop);
        if (action) handleTabAction(action);
      });
    });
  }

  /* ═══════════════════════════════════════════════════════════════
  WIRE: COMMAND PALETTE TRIGGER (⌘ button in header)
  ═══════════════════════════════════════════════════════════════ */
  var paletteTriggerBtn = document.getElementById('mh-palette');
  if (paletteTriggerBtn) {
    paletteTriggerBtn.addEventListener('click', function () {
      ensureModule('palette');
      if (window._mobilePaletteToggle) window._mobilePaletteToggle();
    });
  }

  /* (Header ⋯ overflow menu removed — its actions live in the editor
    action bar and the command palette.) */

  /* ═══════════════════════════════════════════════════════════════
  AI ASSISTANT OVERLAY (v10)
  The panel owns its lifecycle in IDE.mobileAi (open = slide-in +
  status refresh + transcript restore; close = guarded slide-out).
  The shell only routes entry points here and falls back to raw DOM
  toggles when the AI module is not bundled (feature disabled).
  ═══════════════════════════════════════════════════════════════ */
  var aiOverlay = document.getElementById('m-ai-overlay');

  function openAiOverlay() {
    var cfg = window.IDE_CONFIG || {};
    if (!(cfg.features && cfg.features.ai)) {
      toast('🤖 AI Assistant is currently turned off. You can turn it on in Settings.');
      return;
    }
    if (window.IDE && window.IDE.mobileAi && window.IDE.mobileAi.open) {
      window.IDE.mobileAi.open();
      return;
    }
    if (!aiOverlay) return;
    aiOverlay.classList.remove('hidden');
    void aiOverlay.offsetWidth;
    aiOverlay.classList.add('show');
  }

  function closeAiOverlay() {
    if (window.IDE && window.IDE.mobileAi && window.IDE.mobileAi.close) {
      window.IDE.mobileAi.close();
      return;
    }
    if (!aiOverlay) return;
    aiOverlay.classList.remove('show');
    setTimeout(function () {
      aiOverlay.classList.add('hidden');
    }, 300);
  }

  /* v10: NO drag-to-dismiss on the AI overlay (workshop-style page).
     The ✕ button closes it; Escape pops the top-most overlay only. */
  var aiCloseBtn = document.getElementById('m-ai-close');
  if (aiCloseBtn) aiCloseBtn.addEventListener('click', closeAiOverlay);

  /* ═══════════════════════════════════════════════════════════════
  GLOBAL ESCAPE KEY
  Closes any open sheet or overlay.
  ═══════════════════════════════════════════════════════════════ */
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      closeAllSheets();
      closeAiOverlay();
    }
  });

  /* ═══════════════════════════════════════════════════════════════
MODULE INIT HOOKS
Each lazy module registers its init function here. ensureModule()
calls it exactly once, the first time the relevant tab is opened.
★ FIX: these registrations MUST run BEFORE the saved-tab restore
below. Otherwise a boot that lands directly on Terminal/Preview
burns the one-shot "loaded" flag while the hook is still null,
leaving the Send / Clear / zoom buttons dead forever.
═══════════════════════════════════════════════════════════════ */
  _modules.preview.init = function () {
    if (window.IDE.mobilePreview && window.IDE.mobilePreview.init) {
      window.IDE.mobilePreview.init();
    }
  };
  _modules.terminal.init = function () {
    if (window.IDE.mobileTerminal && window.IDE.mobileTerminal.init) {
      window.IDE.mobileTerminal.init();
    }
  };
  _modules.git.init = function () {
    if (window.IDE.mobileGit && window.IDE.mobileGit.init) {
      window.IDE.mobileGit.init();
    }
  };
  _modules.http.init = function () {
    if (window.IDE.mobileHttpClient && window.IDE.mobileHttpClient.init) {
      window.IDE.mobileHttpClient.init();
    }
  };
  _modules.palette.init = function () {
    if (window.IDE.mobilePalette && window.IDE.mobilePalette.init) {
      window.IDE.mobilePalette.init();
    }
  };

  /* ═══════════════════════════════════════════════════════════════
  RESTORE SAVED TAB
  Reads the last active tab from localStorage and switches to it.
  (Now runs AFTER the hooks above are registered.)
  ═══════════════════════════════════════════════════════════════ */
  var savedTab = 'files';
  try { savedTab = localStorage.getItem(LS_TAB) || 'files'; } catch (e) { }
  if (savedTab !== 'more' && panels[savedTab]) {
    switchTab(savedTab);
  } else {
    switchTab('files');
  }
})();