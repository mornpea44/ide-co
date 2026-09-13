/**
 * ═══════════════════════════════════════════════════════════════════════════
 *  QUIRKY IDE — SETTINGS PANEL (Mobile-native revamp)
 * ═══════════════════════════════════════════════════════════════════════════
 *
 *  A full-screen, touch-first settings screen rebuilt for phones:
 *    • Fixed header with a one-tap close button
 *    • Sticky search that filters every section live
 *    • Collapsible section cards (Editor / Appearance / Features / About)
 *    • Big touch controls: 52px toggle switches, chunky sliders, and
 *      feature toggles as tappable tiles in a 2-column grid
 *    • Bottom action bar (Reset + Save Features)
 *
 *  Editor/appearance changes apply instantly and persist in localStorage.
 *  Feature toggles are staged in a draft and written to the server when
 *  "💾 Save Features" is pressed (then a reload countdown applies them).
 *
 *  EXPOSES: window.IDE.settings = { open, close, toggle, get, set, reset, applyAll }
 * ═══════════════════════════════════════════════════════════════════════════
 */
(function () {
  'use strict';
  const U = window.IDE && window.IDE.utils;
  const cfg = window.IDE_CONFIG || {};
  const LS_PREFIX = 'quirky.ide.settings.';
  /* ── v10 settings extensions: keys owned by OTHER panels that the
     Backup/Privacy cards need to reach. Keep in sync with their modules:
       • HTTP history   → mobile-http.js (LS_HISTORY)
       • Recent files   → mobile-filetree.js (LS_RECENT)                */
  const HTTP_HISTORY_KEY = 'quirky.ide.mobile.http.history';
  const RECENT_FILES_KEY = 'quirky.ide.mobile.recent.files';

  /* ═══════════════════════════════════════════════════════════════
  SETTINGS SCHEMA — same keys as before, so saved preferences
  keep working. Only the presentation changed.
  ═══════════════════════════════════════════════════════════════ */
  const SCHEMA = [
    // ── Editor ─
    { key: 'fontSize', label: 'Font Size', desc: 'Editor font size in pixels', type: 'range', min: 10, max: 24, step: 1, unit: 'px', category: 'appearance', default: cfg.fontSize || 16 },
    { key: 'tabSize', label: 'Tab Size', desc: 'Spaces per indentation level', type: 'select', options: [2, 4, 8], category: 'editor', default: cfg.tabSize || 2 },
    { key: 'wordWrap', label: 'Word Wrap', desc: 'Wrap long lines instead of scrolling horizontally', type: 'toggle', category: 'editor', default: true },
    { key: 'autosaveMs', label: 'Autosave', desc: 'Milliseconds after last keystroke before auto-saving', type: 'range', min: 200, max: 3000, step: 100, unit: 'ms', category: 'editor', default: cfg.autosaveMs || 1500 },
    { key: 'acAutoPopup', label: 'Auto-Suggest While Typing', desc: 'Pop up snippets & completions automatically as you type. When OFF, the 💡 keyboard key still triggers suggestions manually.', type: 'toggle', category: 'editor', default: true },
    { key: 'matchBrackets', label: 'Match Brackets', desc: 'Highlight matching brackets when cursor is adjacent', type: 'toggle', category: 'editor', default: true },
    { key: 'autoCloseBrackets', label: 'Auto-Close Brackets', desc: 'Automatically insert closing bracket/quote', type: 'toggle', category: 'editor', default: true },
    { key: 'autoCloseTags', label: 'Auto-Close Tags', desc: 'Automatically close HTML/XML tags', type: 'toggle', category: 'editor', default: true },
    { key: 'foldGutter', label: 'Code Folding', desc: 'Show fold markers in the gutter', type: 'toggle', category: 'editor', default: true },
    { key: 'activeLine', label: 'Active Line Highlight', desc: 'Highlight the line the cursor is on', type: 'toggle', category: 'editor', default: true },
    { key: 'lineNumbers', label: 'Line Numbers', desc: 'Show line numbers in the gutter', type: 'toggle', category: 'editor', default: true },
    /* ── CM5 ⇄ CM6 migration ──
       "auto" tries CM6 first and silently falls back to CM5 if it can't
       load (offline, old browser, CDN blocked). Forcing "cm5" or "cm6"
       always wins over auto-detection. Changing this while the editor is
       already open takes effect next time it (re)loads — see
       applySetting()'s 'editorEngine' case below. */
    { key: 'editorEngine', label: 'Editor Engine', desc: 'Auto = CM6 with automatic fallback to CM5. Force one to override.', type: 'select', options: ['auto', 'cm6', 'cm5'], category: 'editor', default: 'auto' },
    { key: 'webFormatter', label: 'Web Formatter', desc: 'HTML · CSS · JS · JSON · Markdown. js-beautify = fast default · prettier = vendored, smarter (also formats Markdown)', type: 'select', options: ['js-beautify', 'prettier'], category: 'editor', default: 'js-beautify' },
    { key: 'phpFormatter', label: 'PHP Formatter', desc: 'Pure-PHP files. builtin = fast indenter · php-cs-fixer = vendored .phar, PSR-12 (slower but far smarter)', type: 'select', options: ['builtin', 'php-cs-fixer'], category: 'editor', default: 'builtin' },
    { key: 'watchIntervalMs', label: 'External change check', desc: 'How often the Files panel checks for changes made outside the IDE. Lower = faster detection, higher battery use. Applies after 💾 Save Features + reload.', type: 'featureRange', min: 3000, max: 60000, step: 1000, customMax: 999999, unit: 'ms', category: 'explorer' },
    // ── Appearance ─
    { key: 'theme', label: 'Theme', desc: 'Light or dark colour scheme', type: 'select', options: ['dark', 'light'], category: 'appearance', default: cfg.theme || 'dark' },
    { key: 'fontFamily', label: 'Editor Font', desc: 'Monospace font family for the code editor', type: 'select', options: ['JetBrains Mono', 'Fira Code', 'Cascadia Code', 'Consolas', 'Source Code Pro', 'monospace'], category: 'appearance', default: 'JetBrains Mono' },
    /* v10: free-text UI font family — applied to the --font-body theme
       token (same mechanism as fontSize's --editor-font-size). Blank =
       inherit the theme default. The sample line below the rows renders
       it live. NOTE: deliberately a SEPARATE key from 'fontFamily'
       above, which stays bound to the editor's --font-mono.           */
    { key: 'uiFontFamily', label: 'Interface Font', desc: 'Any CSS font-family for interface text (blank = theme default). The sample below updates live.', type: 'text', category: 'appearance', default: '', placeholder: "e.g. 'Roboto Mono', monospace" },
    /* ── Terminal prefs (v10) ──
 Persisted locally now; the terminal panel reads them on boot once
 its consumer lands (post-merge). Kept out of applySetting's live
 wiring on purpose.                                              */
    { key: 'termFontSize', label: 'Terminal Font Size', desc: 'Terminal panel font size in pixels (10–22)', type: 'range', min: 10, max: 22, step: 1, unit: 'px', category: 'terminal', default: 14 },
    { key: 'termWrap', label: 'Terminal Word Wrap', desc: 'Wrap long terminal lines instead of scrolling sideways', type: 'toggle', category: 'terminal', default: false },
    // ── Features (staged draft, saved to server) ──
    { key: 'terminal', label: 'Terminal', desc: 'Run commands inside workspace/', type: 'feature', category: 'features' },
    { key: 'preview', label: 'Live Preview', desc: 'Real-time HTML/PHP preview pane', type: 'feature', category: 'features' },
    { key: 'format', label: 'Format / Beautify', desc: 'One-click code formatting', type: 'feature', category: 'features' },
    { key: 'minify', label: 'Shrink / Minify', desc: 'Safe code minification', type: 'feature', category: 'features' },
    { key: 'git', label: 'Git Panel', desc: 'Read-only source control: status, history & diffs', type: 'feature', category: 'features' },
    { key: 'ai', label: 'AI Assistant', desc: 'Chat with the AI to read, create & edit files', type: 'feature', category: 'features' },
    { key: 'httpClient', label: 'HTTP Client', desc: 'Postman-style API request builder', type: 'feature', category: 'features' },
    { key: 'commandPalette', label: 'Command Palette', desc: 'Quick-open files & run commands (⌘)', type: 'feature', category: 'features' },
    /* v10: community-packages runtime tile (key packages_enabled). Wired
       identically to every other FEATURE_MAP entry — tier-lock /
       default-off policy applies through the same code path.          */
    { key: 'packages', label: 'Community Packages', desc: 'Community packages runtime (via Workshop)', type: 'feature', category: 'features' },
    // ── Voice (v11: TTS + STT master switches, rendered by the Voice card) ──
    { key: 'voiceTts', label: 'Text-to-Speech', desc: 'Master switch for everything that reads aloud', type: 'toggle', category: 'voice', default: true },
    { key: 'voiceStt', label: 'Speech-to-Text', desc: 'Master switch for everything that listens', type: 'toggle', category: 'voice', default: true }
  ];

  const CATEGORIES = [
    { id: 'editor', icon: '✏️', name: 'Editor', desc: 'Typing, display, formatting, engine & autosave' },
    { id: 'snippets', icon: '📝', name: 'Snippet Studio', desc: 'Custom boilerplates, snippets & autocomplete coverage', keywords: 'snippet boilerplate template autocomplete coverage import export custom code', custom: true, render: renderSnippetsSection },
    { id: 'appearance', icon: '🎨', name: 'Appearance', desc: 'Theme & fonts (UI + editor)' },
    { id: 'explorer', icon: '🗂️', name: 'Explorer', desc: 'Change detection, workspace directory & export folder', keywords: 'explorer workspace directory export folder location storage downloads watch interval external change detection', custom: true, render: renderExplorerSection },
    { id: 'features', icon: '⚙️', name: 'Features', desc: 'Toggle IDE modules (saved to server)' },
    { id: 'workshop', icon: '🔧', name: 'Workshop', desc: 'Extensions & add-on tools' },
    { id: 'terminal', icon: '🖥️', name: 'Terminal', desc: 'Terminal panel font & wrapping', keywords: 'terminal console pty shell font wrap', custom: true, render: renderTerminalSection },
    { id: 'device', icon: '📱', name: 'Device', desc: 'Hardware-based limits & configuration' },
    //{ id: 'voice', icon: '🎙️', name: 'Voice', desc: 'Read-aloud & dictation (TTS + STT)', keywords: 'voice speech tts stt microphone dictation speak listen audio mic talk read aloud', custom: true, render: renderVoiceSection },
    { id: 'backup', icon: '💾', name: 'Backup & Restore', desc: 'Export or import settings, features & HTTP history', keywords: 'backup export import restore json download upload', custom: true, render: renderBackupSection }, { id: 'privacy', icon: '🧹', name: 'Privacy & Data', desc: 'Clear stored history and AI conversation', keywords: 'privacy clear delete history recents ai conversation data cache', custom: true, render: renderPrivacySection },
    { id: 'storage', icon: '🗄️', name: 'Storage & Cache', desc: 'Safe-to-clear caches & reclaimable space', keywords: 'storage cache clear clean space junk bundle watch export import workshop terminal temp', custom: true, render: renderStorageSection },
    { id: 'logs', icon: '📋', name: 'Logs', desc: 'Captured console output & error history', keywords: 'logs console errors debug ide capture viewer clear copy', custom: true, render: renderLogsSection },
    { id: 'about', icon: 'ℹ️', name: 'About', desc: 'Version, gestures & info' },
  ];

  const FEATURE_ICONS = { terminal: '🖥️', preview: '👁️', format: '✨', minify: '🗜️', git: '🌿', ai: '🤖', httpClient: '📡', commandPalette: '⌘', packages: '📦' };

  /* ── ★ v12: boxed sub-section layout for Editor & Appearance ──
Keys inside each group follow the agreed control order —
toggles → selects (options) → radios → inputs — unless a fixed
order was specified (Formatting, Font). `sample: true` appends
the live font sample INSIDE that box (Appearance → FONT).   */
  const EDITOR_GROUPS = [
    { title: 'Typing', keys: ['acAutoPopup', 'matchBrackets', 'autoCloseBrackets', 'autoCloseTags', 'tabSize'] },
    { title: 'Display', keys: ['wordWrap', 'lineNumbers', 'foldGutter', 'activeLine'] },
    { title: 'Formatting', keys: ['phpFormatter', 'webFormatter'] },
    { title: 'Engine', keys: ['editorEngine'] },
    { title: 'Saving', keys: ['autosaveMs'] },
  ];
  const APPEARANCE_GROUPS = [
    { title: 'UI', keys: ['theme'] },
    { title: 'Font', keys: ['fontFamily', 'uiFontFamily', 'fontSize'], sample: true },
  ];

  /* ── ★ Terminal boxed sub-sections — Display first, then Appearance ── */
  const TERMINAL_GROUPS = [
    { title: 'Display', keys: ['termWrap'] },
    { title: 'Appearance', keys: ['termFontSize'] },
  ];
  /* ── ★ v12: Features grouped by what they DO for you — no generic
  "Utilities / Miscellaneous" buckets. Rename any title here freely. ── */
  const FEATURE_GROUPS = [
    { title: 'Code Shaping', keys: ['format', 'minify'] },
    { title: 'Run & Preview', keys: ['terminal', 'preview'] },
    { title: 'Track & Test', keys: ['git', 'httpClient'] },
    { title: 'Assistants', keys: ['ai', 'commandPalette'] },
    { title: 'Community', keys: ['packages'] },
  ];

  /* ═══════════════════════════════════════════════════════════════
  STORAGE
  ═══════════════════════════════════════════════════════════════ */
  function get(key) {
    const schema = SCHEMA.find(s => s.key === key);
    if (!schema) return undefined;
    try {
      const raw = localStorage.getItem(LS_PREFIX + key);
      if (raw === null) return schema.default;
      if (schema.type === 'toggle') return raw === '1' || raw === 'true';
      if (schema.type === 'range' || schema.type === 'featureRange') return Number(raw);
      return raw;
    } catch (e) { return schema.default; }
  }
  function set(key, value) {
    try {
      if (value === null || value === undefined) {
        localStorage.removeItem(LS_PREFIX + key);
      } else {
        localStorage.setItem(LS_PREFIX + key, String(value));
      }
    } catch (e) { }
    applySetting(key, value);
    updateSaveChangesUI();
  }
  function reset() {
    SCHEMA.forEach(s => {
      if (s.type === 'readonly' || s.type === 'feature' || s.type === 'featureRange') return;
      try { localStorage.removeItem(LS_PREFIX + s.key); } catch (e) { }
    });
    SCHEMA.forEach(s => {
      if (s.type === 'readonly' || s.type === 'feature' || s.type === 'featureRange') return;
      applySetting(s.key, s.default);
    });
    if (U && U.toast) U.toast('Settings reset to defaults');
    renderAll(searchQuery);
    /* Reset applies everything live, so re-baseline the snapshot and hide
    the "Save Changes" button again. */
    captureSettingsSnapshot();
    updateSaveChangesUI();
  }

  /* ═══════════════════════════════════════════════════════════════
  APPLY — push a setting value into the live editor / IDE
  ═══════════════════════════════════════════════════════════════ */
  /* ── Feature toggles: staged in a draft, saved to server on demand ── */
  let featureDraft = null;
  let featureDraftUnlocks = null;

  var FEATURE_KEY_MAP = {
    terminal: 'terminal_enabled', preview: 'preview_enabled', format: 'format_enabled',
    minify: 'minify_enabled', git: 'git_enabled', ai: 'ai_enabled',
    httpClient: 'http_client_enabled', commandPalette: 'command_palette_enabled',
    workshop: 'workshop_enabled', pkg: 'pkg_enabled', autocomplete: 'autocomplete_enabled', lint: 'lint_enabled',
    packages: 'packages_enabled'   /* v10: community packages runtime */
  };

  function ensureFeatureDraftUnlocks() {
    if (!featureDraftUnlocks) {
      featureDraftUnlocks = {};
      var curUnlocks = (cfg && cfg.deviceUnlocks) ? cfg.deviceUnlocks : {};
      for (var k in curUnlocks) { if (curUnlocks[k]) featureDraftUnlocks[k] = true; }
    }
    return featureDraftUnlocks;
  }
  function featureCurrent() {
    const f = (cfg && cfg.features) ? Object.assign({}, cfg.features) : {};
    if (cfg && cfg.watchIntervalMs != null) f.watchIntervalMs = cfg.watchIntervalMs;
    return f;
  }
  function ensureFeatureDraft() {
    if (!featureDraft) {
      featureDraft = {};
      const cur = featureCurrent();
      SCHEMA.forEach(function (s) {
        if (s.type === 'feature') featureDraft[s.key] = !!cur[s.key];
        if (s.type === 'featureRange') featureDraft[s.key] = cur[s.key] != null ? Number(cur[s.key]) : (cfg.watchIntervalMs || 15000);
      });
    }
    return featureDraft;
  }
  function featureIsDirty() {
    if (!featureDraft) return false;
    const cur = featureCurrent();
    return SCHEMA.some(function (s) {
      if (s.type === 'feature') return !!featureDraft[s.key] !== !!cur[s.key];
      if (s.type === 'featureRange') return Number(featureDraft[s.key]) !== Number(cur[s.key] != null ? cur[s.key] : (cfg.watchIntervalMs || 15000));
      return false;
    });
  }
  function updateFeatureSaveUI() {
    /* Save Features was merged into Save Changes — keep every existing
       call site (feature tiles, open, close, backup) working by routing
       them at the unified button. */
    updateSaveChangesUI();
  }
  function applySetting(key, value) {
    const IDE = window.IDE;
    const cm = IDE && IDE.mobileEditor && IDE.mobileEditor.getCM();
    switch (key) {
      case 'fontSize':
        document.documentElement.style.setProperty('--editor-font-size', value + 'px');
        document.documentElement.style.setProperty('--m-ed-fs', value + 'px');
        if (cm && cm.refresh) requestAnimationFrame(() => cm.refresh());
        break;
      case 'tabSize':
        if (cm && cm.setOption) {
          cm.setOption('tabSize', value);
          cm.setOption('indentUnit', value);
        }
        break;
      case 'wordWrap':
        if (cm && cm.setOption) cm.setOption('lineWrapping', !!value);
        try { localStorage.setItem('quirky.ide.wrap', value ? '1' : '0'); } catch (e) { }
        break;
      case 'matchBrackets':
        if (cm && cm.setOption) cm.setOption('matchBrackets', !!value);
        break;
      case 'autoCloseBrackets':
        if (cm && cm.setOption) cm.setOption('autoCloseBrackets', !!value);
        break;
      case 'autoCloseTags':
        if (cm && cm.setOption) cm.setOption('autoCloseTags', !!value);
        break;
      case 'foldGutter': {
        if (cm && cm.setOption) {
          cm.setOption('foldGutter', !!value);
          const g = [];
          if (get('lineNumbers')) g.push('CodeMirror-linenumbers');
          if (value) g.push('CodeMirror-foldgutter');
          cm.setOption('gutters', g);
        }
        break;
      }
      case 'activeLine':
        if (cm && cm.setOption) cm.setOption('styleActiveLine', !!value);
        break;
      case 'lineNumbers': {
        if (cm && cm.setOption) {
          cm.setOption('lineNumbers', !!value);
          const g = [];
          if (value) g.push('CodeMirror-linenumbers');
          if (get('foldGutter')) g.push('CodeMirror-foldgutter');
          cm.setOption('gutters', g);
        }
        break;
      }
      case 'theme':
        document.documentElement.setAttribute('data-theme', value);
        try { localStorage.setItem('quirky.ide.theme', value); } catch (e) { }
        if (cm && cm.refresh) requestAnimationFrame(() => cm.refresh());
        break;
      case 'fontFamily':
        document.documentElement.style.setProperty('--font-mono',
          "'" + value + "', ui-monospace, 'Courier New', monospace");
        if (cm && cm.refresh) requestAnimationFrame(() => cm.refresh());
        break;
      /* ── v10: UI font family — SAME mechanism as fontSize above
         (documentElement inline custom property), but pointed at the
         --font-body theme token so only interface text changes. The
         editor's --font-mono is intentionally untouched.           */
      case 'uiFontFamily':
        if (value && String(value).trim() !== '') {
          document.documentElement.style.setProperty('--font-body', String(value).trim());
        } else {
          document.documentElement.style.removeProperty('--font-body');
        }
        updateFontSample();
        break;
      /* ── v10: terminal prefs — Word Wrap now applies LIVE.
      mobile-terminal.js mirrors the choice into its own key and
      re-reads it at boot, so it also survives reloads. The silent
      flag stops the terminal's own toast from double-announcing. */
      case 'termWrap': {
        const MT = IDE && IDE.mobileTerminal;
        if (MT && typeof MT.setWordWrap === 'function') MT.setWordWrap(!!value, true);
        break;
      }
      case 'termFontSize':
        break;
      case 'autosaveMs':
        // Mobile editor reads this live via IDE.settings.get('autosaveMs')
        break;
      case 'acAutoPopup': {
        /* Live-sync the autocomplete module AND the 💡 action-bar button */
        var AC = IDE && IDE.autocomplete;
        if (AC && typeof AC.setAutoPopup === 'function') AC.setAutoPopup(!!value);
        if (IDE && IDE.mobileEditor && typeof IDE.mobileEditor.updateAutoSuggestUI === 'function') {
          IDE.mobileEditor.updateAutoSuggestUI();
        }
        break;
      }
      case 'editorEngine': {
        // The engine (CM5 vs CM6) is resolved once, when the editor
        // instance is created — switching a live instance would mean
        // tearing down and recreating it mid-edit, which is riskier
        // than asking for a reload (same pattern the Feature toggles
        // below already use).
        var alreadyInit = IDE && IDE.mobileEditor && IDE.mobileEditor.isReady && IDE.mobileEditor.isReady();
        if (alreadyInit && U && U.toast) {
          U.toast('⚙ Editor engine will change next time the app reloads');
        }
        break;
      }
    }
    if (cm && cm.refresh) requestAnimationFrame(() => cm.refresh());
  }
  /* Apply all persisted settings on boot */
  function applyAll() {
    SCHEMA.forEach(s => {
      if (s.type === 'readonly' || s.type === 'feature' || s.type === 'featureRange') return;
      const val = get(s.key);
      if (val !== s.default) applySetting(s.key, val);
    });
  }

  /* ── v10: live font sample line (Appearance card) ──
     Renders the chosen UI font at the editor's font size; theme colors
     come free through the CSS variables. Re-applied after every
     renderAll() because re-rendering resets the inline styles.       */
  const FONT_SAMPLE_TEXT = 'The quick brown fox jumps over the lazy dog — 0123456789 {}();';
  function updateFontSample() {
    const el = document.getElementById('settings-font-sample');
    if (!el) return;
    const uiFont = String(get('uiFontFamily') || '').trim();
    if (uiFont) el.style.fontFamily = uiFont;
    else el.style.removeProperty('font-family');
    el.style.fontSize = (Number(get('fontSize')) || 16) + 'px';
  }
  function renderFontSampleBlock() {
    return '<div class="settings-font-sample-wrap">' +
      '<span class="settings-font-sample-label">Live sample — UI font · size · theme</span>' +
      '<div class="settings-font-sample" id="settings-font-sample">' +
      esc(FONT_SAMPLE_TEXT) + '</div></div>';
  }

  /* ═══════════════════════════════════════════════════════════════
  UI — BUILD THE NEW MOBILE-NATIVE PANEL
  ═══════════════════════════════════════════════════════════════ */
  let panelEl = null;
  let isOpen = false;
  let searchQuery = '';
  /* Which sections are expanded right now (default: only Editor).
     Every section toggles independently — any number can be open at once. */
  let openCats = new Set(['editor']);
  /* Debounce timer for the live search so the DOM is not rebuilt on
  every single keystroke. */
  let searchTimer = null;
  /* Snapshot of editor/appearance settings captured when the panel opens.
  Used to detect whether the user changed anything, so we can offer a
  "Save Changes" (reload) button — same idea as "Save Features". */
  let settingsSnapshot = null;

  function buildPanel() {
    if (panelEl) return;
    panelEl = document.createElement('div');
    panelEl.id = 'settings-overlay';
    panelEl.className = 'settings-overlay hidden';
    panelEl.innerHTML =
      '<div class="settings-panel">' +
      '<div class="settings-header">' +
      '<button class="settings-close" id="settings-close" title="Close (Esc)" aria-label="Close settings">✕</button>' +
      '<span class="settings-title">⚙️ Settings</span>' +
      '</div>' +
      '<div class="settings-search-wrap">' +
      '<span class="settings-search-ico" aria-hidden="true">🔍</span>' +
      '<input type="text" id="settings-search" placeholder="Search settings…" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false">' +
      '<button class="settings-search-clear hidden" id="settings-search-clear" title="Clear search" aria-label="Clear search">✕</button>' +
      '</div>' +
      '<div class="settings-body" id="settings-content"></div>' +
      '<div class="settings-footer">' +
      '<button class="settings-reset" id="settings-reset">↺ Reset defaults</button>' +
      '<button class="settings-save-features hidden" id="settings-save-changes">💾 Save Changes</button>' +
      '</div>' +
      '</div>';
    document.body.appendChild(panelEl);

    // Wire chrome events
    document.getElementById('settings-close').addEventListener('click', close);
    document.getElementById('settings-reset').addEventListener('click', reset);
    document.getElementById('settings-save-changes').addEventListener('click', saveChanges);
    const search = document.getElementById('settings-search');
    const clearBtn = document.getElementById('settings-search-clear');
    search.addEventListener('input', function () {
      searchQuery = this.value.trim().toLowerCase();
      clearBtn.classList.toggle('hidden', this.value.trim() === '');
      clearTimeout(searchTimer);
      searchTimer = setTimeout(function () { renderAll(searchQuery); }, 120);
    });
    clearBtn.addEventListener('click', function () {
      search.value = '';
      searchQuery = '';
      clearBtn.classList.add('hidden');
      renderAll('');
      search.focus();
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && isOpen) close();
    });
  }

  /* ── Render every category as a collapsible card ── */
  function renderAll(filter) {
    const content = document.getElementById('settings-content');
    if (!content) return;
    const q = (filter || '').toLowerCase();
    let html = '';
    /* ★ About is structurally pinned last: even if someone reorders
    CATEGORIES later, it is always moved to the end of the render. */
    const ordered = CATEGORIES.filter(function (c) { return c.id !== 'about'; });
    const aboutCat = CATEGORIES.find(function (c) { return c.id === 'about'; });
    if (aboutCat) ordered.push(aboutCat);
    ordered.forEach(function (cat) {
      /* ── v10: custom-rendered cards (Terminal / Backup / Privacy) ──
         They have no SCHEMA rows, so search matches against their name,
         description and keyword list instead of the item labels.      */
      if (cat.render) {
        const hay = (cat.name + ' ' + cat.desc + ' ' + (cat.keywords || '')).toLowerCase();
        if (q && hay.indexOf(q) === -1) return;
        const open = q !== '' || openCats.has(cat.id);
        html +=
          '<section class="settings-card' + (open ? ' open' : '') + '" data-cat="' + cat.id + '">' +
          cardHead(cat, open) +
          '<div class="settings-card-body"><div class="settings-card-clip"><div class="settings-card-inner">' + cat.render() + '</div></div></div>' +
          '</section>';
        return;
      }
      if (cat.id === 'about') {
        if (q && 'about version info gestures shortcuts theme'.indexOf(q) === -1) return;
        /* While searching, matching cards are forced open so hits are
           visible; otherwise we honour the accordion state. */
        const open = q !== '' || openCats.has('about');
        html +=
          '<section class="settings-card' + (open ? ' open' : '') + '" data-cat="about">' +
          cardHead(cat, open) +
          '<div class="settings-card-body"><div class="settings-card-clip"><div class="settings-card-inner">' + renderAbout() + '</div></div></div>' +
          '</section>';
        return;
      }
      let items = SCHEMA.filter(s => s.category === cat.id);
      if (q) {
        items = items.filter(s =>
          s.label.toLowerCase().indexOf(q) !== -1 ||
          s.desc.toLowerCase().indexOf(q) !== -1 ||
          s.key.toLowerCase().indexOf(q) !== -1
        );
        if (!items.length) return; // hide empty cards while searching
      }
      const open = q !== '' || openCats.has(cat.id);
      html +=
        '<section class="settings-card' + (open ? ' open' : '') + '" data-cat="' + cat.id + '">' +
        cardHead(cat, open) +
        '<div class="settings-card-body"><div class="settings-card-clip">' + renderCardItems(cat.id, items) + '</div></div>' +
        '</section>';
    });
    content.innerHTML = html || '<div class="settings-empty">No settings match “' + esc(filter) + '”</div>';
    wireCardHeads(content);
    wireControls(content);
    updateFontSample();
  }
  function cardHead(cat, open) {
    return '<button class="settings-card-head" aria-expanded="' + (open ? 'true' : 'false') + '">' +
      '<span class="settings-card-ico" aria-hidden="true">' + cat.icon + '</span>' +
      '<span class="settings-card-titles">' +
      '<span class="settings-card-title">' + esc(cat.name) + '</span>' +
      '<span class="settings-card-sub">' + esc(cat.desc) + '</span>' +
      '</span>' +
      '<span class="settings-card-chev" aria-hidden="true">▾</span>' +
      '</button>';
  }

  function renderCardItems(catId, items) {
    if (catId === 'snippets') {
      return ''; // handled by the custom render function
    }
    if (catId === 'device') {
      return renderDeviceSection();
    }
    if (catId === 'workshop') {
      return renderWorkshopSection();
    }
    /* ★ v12: Editor & Appearance render as boxed sub-sections */
    if (catId === 'editor') {
      return '<div class="settings-card-inner">' + renderGroupedItems(EDITOR_GROUPS, items) + '</div>';
    }
    if (catId === 'appearance') {
      return '<div class="settings-card-inner">' + renderGroupedItems(APPEARANCE_GROUPS, items) + '</div>';
    }
    if (catId === 'features') {
      let html = '<div class="settings-card-inner">';
      FEATURE_GROUPS.forEach(function (g) {
        const tiles = g.keys
          .map(function (k) { return items.find(function (s) { return s.key === k; }); })
          .filter(Boolean);
        if (!tiles.length) return; // group fully filtered out by search
        html += '<div class="settings-group-box">' +
          '<div class="settings-group-title">' + esc(g.title) + '</div>' +
          '<div class="settings-feat-grid">' +
          tiles.map(renderFeatureCard).join('') +
          '</div></div>';
      });
      html += '</div><div class="settings-feat-note">Feature changes apply after 💾 Save + reload.</div>';
      return html;
    }
    return items.map(renderSettingRow).join('');
  }
  /* ── ★ v12: boxed sub-sections (Editor & Appearance) ─────────────
  Renders each group as a bordered box with a centered uppercase
  title bar. Rows keep the global row layout (name + description
  left, control right; input-type rows stacked: name/description
  on top, input area below). Groups whose rows were all filtered
  out by the search are skipped, so live search keeps working.
  `sample: true` on a group appends the live font sample inside
  that box.                                                    */
  function renderGroupedItems(groups, items) {
    let html = '';
    groups.forEach(function (g) {
      const rows = [];
      g.keys.forEach(function (k) {
        for (let i = 0; i < items.length; i++) {
          if (items[i].key === k) { rows.push(items[i]); break; }
        }
      });
      if (!rows.length) return;
      html += '<div class="settings-group-box">' +
        '<div class="settings-group-title">' + esc(g.title) + '</div>' +
        rows.map(renderSettingRow).join('') +
        (g.sample ? renderFontSampleBlock() : '') +
        '</div>';
    });
    return html;
  }
  /* ── Device section: hardware-based limits (read-only, extensible) ── */
  function renderDeviceSection() {
    const cfg = window.IDE_CONFIG || {};
    const hw = cfg.hardware || {};

    function humanBytes(b) {
      b = Number(b) || 0;
      if (b >= 1048576) return Math.round(b / 1048576) + ' MB';
      return Math.round(b / 1024) + ' KB';
    }

    function deviceRow(label, value, icon) {
      return '<div class="settings-row">' +
        '<div class="settings-info">' +
        '<span class="settings-label">' + (icon ? icon + ' ' : '') + label + '</span>' +
        '</div>' +
        '<div class="settings-control">' +
        '<span class="settings-badge on">' + value + '</span>' +
        '</div>' +
        '</div>';
    }

    let rowsHtml = '';
    // ── RAM (accurate from Android bridge when available) ──
    if (hw.ramGb && hw.ramGb > 0) {
      let ramDisplay = hw.ramGb >= 1
        ? (hw.ramGb % 1 === 0 ? hw.ramGb + ' GB' : hw.ramGb.toFixed(1) + ' GB')
        : hw.ramMb + ' MB';
      rowsHtml += deviceRow('RAM', ramDisplay, '🧠');
    }
    // ── CPU Cores ──
    if (hw.cores && hw.cores > 0) {
      rowsHtml += deviceRow('CPU Cores', hw.cores + ' cores', '⚡');
    }
    // ── Available Storage ──
    if (hw.storageMb && hw.storageMb > 0) {
      let storageDisplay = hw.storageMb >= 1024
        ? (hw.storageMb / 1024).toFixed(1) + ' GB'
        : hw.storageMb + ' MB';
      rowsHtml += deviceRow('Free Storage', storageDisplay, '💾');
    }
    // ── Device Tier ──
    if (hw.tier && hw.tier !== 'unknown') {
      let tierLabel = hw.tier.charAt(0).toUpperCase() + hw.tier.slice(1);
      let tierIcon = hw.tier === 'high' ? '🚀' : (hw.tier === 'medium' ? '⚙️' : '🐢');
      rowsHtml += deviceRow('Performance Tier', tierLabel, tierIcon);
    }
    // ── File Size Limit (the actual enforced limit) ──
    rowsHtml += deviceRow('File Size Limit', humanBytes(cfg.maxFileSize || 524288), '📄');
    // ── Data source indicator ──
    let sourceNote = '';
    if (hw.source === 'android-bridge') {
      sourceNote = 'Hardware data read directly from your device (accurate).';
    } else if (hw.source === 'browser-api') {
      sourceNote = 'Hardware data from browser APIs (may be approximate).';
    } else {
      sourceNote = 'Hardware data unavailable — using safe defaults.';
    }
    const noteHtml = '<div class="settings-feat-note">' +
      sourceNote + '<br>' +
      'File limits are determined automatically by your device\'s hardware. ' +
      'They cannot be changed manually.' +
      '</div>';
    return '<div class="settings-card-inner">' +
      '<div class="settings-group-box">' +
      '<div class="settings-group-title">Hardware</div>' +
      rowsHtml +
      '</div>' +
      noteHtml +
      '</div>';
  }

  /* ── Workshop section: boxed sub-sections. Each Workshop feature gets
    its own box, so future add-ons can simply append another one.
    ★ Wrapped in settings-card-inner so the group boxes (and their
    sub-header bars) get the same inset padding as Terminal/Editor. ── */
  function renderWorkshopSection() {
    return '<div class="settings-card-inner">' +
      '<div class="settings-group-box">' +
      '<div class="settings-group-title">Extensions</div>' +
      '<div class="settings-row">' +
      '<div class="settings-info">' +
      '<span class="settings-label">Workshop</span>' +
      '<span class="settings-desc">Manage extensions & add-on tools</span>' +
      '</div>' +
      '<div class="settings-control">' +
      '<button type="button" class="settings-action-btn" id="settings-open-workshop">🔧 Open</button>' +
      '</div>' +
      '</div>' +
      '</div>' +
      '<div class="settings-group-box">' +
      '<div class="settings-group-title">Logs</div>' +
      '<div class="settings-row">' +
      '<div class="settings-info">' +
      '<span class="settings-label">Workshop Logs</span>' +
      '<span class="settings-desc">Install errors, server output & activity history</span>' +
      '</div>' +
      '<div class="settings-control">' +
      '<button type="button" class="settings-action-btn" id="settings-open-workshop-logs">📋 View</button>' +
      '</div>' +
      '</div>' +
      '</div>' +
      '</div>';
  }

  /* ═══════════════════════════════════════════════════════════════
  v10 CARDS — Terminal prefs · Backup & Restore · Privacy & Data
  ═══════════════════════════════════════════════════════════════ */

  /* ── Snippet Studio: delegates to mobile-snippets.js ── */
  function renderSnippetsSection() {
    var MS = window.IDE && window.IDE.mobileSnippets;
    if (MS && typeof MS.render === 'function') return MS.render();
    return '<div class="settings-note-row">Snippet Studio module not loaded.</div>';
  }

  /* ── Terminal: boxed sub-sections — Display (word wrap) first, then
  Appearance (font size). The schema rows themselves are unchanged. ── */
  function renderTerminalSection() {
    const items = SCHEMA.filter(s => s.category === 'terminal');
    return renderGroupedItems(TERMINAL_GROUPS, items);
  }

  /* ── Backup & Restore — one boxed sub-section per direction ── */
  function renderBackupSection() {
    return '<div class="settings-group-box">' +
      '<div class="settings-group-title">Export</div>' +
      '<div class="settings-row">' +
      '<div class="settings-info">' +
      '<span class="settings-label">⬇️ Backup file</span>' +
      '<span class="settings-desc">Download a JSON backup of all settings, feature flags and HTTP history</span>' +
      '</div>' +
      '<div class="settings-control">' +
      '<button type="button" class="settings-action-btn" id="settings-export-btn">Export</button>' +
      '</div>' +
      '</div>' +
      '</div>' +
      '<div class="settings-group-box">' +
      '<div class="settings-group-title">Import</div>' +
      '<div class="settings-row">' +
      '<div class="settings-info">' +
      '<span class="settings-label">⬆️ Backup file</span>' +
      '<span class="settings-desc">Restore from a backup file — current settings are overwritten</span>' +
      '</div>' +
      '<div class="settings-control">' +
      '<button type="button" class="settings-action-btn" id="settings-import-btn">Import…</button>' +
      '<input type="file" id="settings-import-file" accept=".json,application/json" hidden>' +
      '</div>' +
      '</div>' +
      '</div>' +
      '<div class="settings-note-row">Everything stays on this device — export/import is pure client-side.</div>';
  }

  /** All quirky.ide.settings.* keys currently in localStorage → object. */
  function collectSettings() {
    const out = {};
    try {
      for (let i = 0; i < localStorage.length; i++) {
        const k = localStorage.key(i);
        if (k && k.indexOf(LS_PREFIX) === 0) out[k] = localStorage.getItem(k);
      }
    } catch (e) { }
    return out;
  }
  async function doExportBackup() {
    let features = null;
    try {
      const res = await fetch('index.php?api=features', { headers: { 'Accept': 'application/json' } });
      const j = await res.json();
      if (res.ok && j && j.ok !== false && j.data && j.data.features) features = j.data.features;
    } catch (e) { /* features optional in the backup */ }
    let httpHistory = null;
    try { httpHistory = JSON.parse(localStorage.getItem(HTTP_HISTORY_KEY) || 'null'); } catch (e) { }
    const payload = {
      app: 'quirky-ide-mobile',
      version: cfg.version || '1',
      exportedAt: new Date().toISOString(),
      settings: collectSettings(),
      features: features,
      httpHistory: httpHistory
    };
    const d = new Date();
    const pad = n => String(n).padStart(2, '0');
    const name = 'quirky-backup-' + d.getFullYear() + pad(d.getMonth() + 1) + pad(d.getDate()) +
      '-' + pad(d.getHours()) + pad(d.getMinutes()) + '.json';
    /* ★ v12: WebView-safe download (data: URL in the app → export folder /
    Downloads; blob URL in regular browsers). */
    U.downloadFile(name, JSON.stringify(payload, null, 2), 'application/json');
    if (U && U.toast) U.toast('💾 Backup exported');
  }

  function applyBackup(data) {
    /* 1. Settings → localStorage, then re-apply live */
    let applied = 0;
    Object.keys(data.settings || {}).forEach(k => {
      if (k.indexOf(LS_PREFIX) !== 0) return;
      try { localStorage.setItem(k, String(data.settings[k])); applied++; } catch (e) { }
    });
    /* 2. HTTP history (kept under its original key so the panel finds it) */
    if (Array.isArray(data.httpHistory)) {
      try { localStorage.setItem(HTTP_HISTORY_KEY, JSON.stringify(data.httpHistory)); } catch (e) { }
    }
    /* 3. Feature flags → staged draft → POST features-save.
       Tier unlocks can't be recovered from an export; pass {}.        */
    let featuresRestored = false;
    const postFeatures = async () => {
      const inv = {};
      Object.keys(FEATURE_KEY_MAP).forEach(fk => { inv[FEATURE_KEY_MAP[fk]] = fk; });
      const draft = {};
      Object.keys(data.features).forEach(sv => { if (inv[sv]) draft[inv[sv]] = !!data.features[sv]; });
      await fetch('index.php?api=features-save', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify(Object.assign({}, draft, { unlocks: {} }))
      });
    };
    const finish = () => {
      featureDraft = null;
      featureDraftUnlocks = null;
      applyAll();
      captureSettingsSnapshot();
      renderAll(searchQuery);
      updateSaveChangesUI();
      updateFeatureSaveUI();
      if (U && U.toast) U.toast('✅ Backup applied (' + applied + ' settings' +
        (featuresRestored ? ' + features' : '') + ') — reload to apply everything', 'success');
    };
    if (data.features && typeof data.features === 'object') {
      featuresRestored = true;
      postFeatures().then(finish).catch(function () {
        if (U && U.toast) U.toast('⚠ Settings restored but feature save failed', 'warning');
        finish();
      });
    } else {
      finish();
    }
  }

  function doImportBackup(file) {
    if (!file) return;
    const reader = new FileReader();
    reader.onerror = function () { if (U && U.toast) U.toast('Could not read that file', 'error'); };
    reader.onload = function () {
      let data = null;
      try { data = JSON.parse(String(reader.result || '')); }
      catch (e) { if (U && U.toast) U.toast('Not a valid JSON backup file', 'error'); return; }
      if (!data || typeof data !== 'object' ||
        (typeof data.settings !== 'object' && !Array.isArray(data.httpHistory))) {
        if (U && U.toast) U.toast('That file does not look like a Quirky backup', 'error');
        return;
      }
      const nSet = Object.keys(data.settings || {}).length;
      const nHist = Array.isArray(data.httpHistory) ? data.httpHistory.length : 0;
      const hasFeat = !!(data.features && typeof data.features === 'object');
      U.modal.confirm({
        title: 'Apply backup?',
        message: nSet + ' setting value(s)' + (hasFeat ? ', feature flags' : '') +
          (nHist ? ', ' + nHist + ' HTTP history entr' + (nHist === 1 ? 'y' : 'ies') : '') +
          ' will overwrite your current configuration.',
        confirmText: 'Apply',
        danger: true
      }).then(function (ok) {
        if (ok) applyBackup(data);
      });
    };
    reader.readAsText(file);
  }

  /* ═══════════════════════════════════════════════════════════════════════════
  ★ v12: EXPLORER CARD — Change detection · Workspace directory · Export path
  Three boxed sub-sections. Workspace & export are DRAFTS: picking an
  option (or typing a custom path) just marks them dirty — the general
  💾 Save Changes footer button applies them (export folder instantly,
  workspace via api.workspace.switch + the reload countdown). No per-row
  apply buttons anymore.
  ═══════════════════════════════════════════════════════════════════════ */
  const EXPORT_DOCS_PATH = '/storage/emulated/0/Documents';
  /* Fallbacks if the presets endpoint ever fails — same paths the server
  returns (routes/files.php → workspace-presets). */
  const WORKSPACE_FALLBACK_PATHS = {
    shared: '/storage/emulated/0/QuirkyIDE',
    downloads: '/storage/emulated/0/Download/QuirkyIDE',
    documents: '/storage/emulated/0/Documents/QuirkyIDE'
  };

  const explorerState = {
    loaded: false,
    loading: false,
    info: null,             // api.workspace.info() result
    presets: [],            // api.workspace.presets() list
    exportDir: null,        // null = not read yet · '' = Downloads default
    /* ── Workspace directory draft ── */
    wsChoice: 'default',    // 'default' | 'shared' | 'downloads' | 'documents' | 'custom'
    wsChoiceBase: 'default',// the choice that matches the LIVE workspace
    wsCustom: '',           // typed / browsed custom path
    wsCustomBase: '',       // the custom path as it is live right now
    wsCopy: true,           // copy current files to the new workspace
    /* ── Export path draft ── */
    expChoice: 'downloads', // 'downloads' | 'documents' | 'custom'
    expChoiceBase: 'downloads',
    expCustom: '',
    expCustomBase: ''
  };

  function explorerBridge() {
    return (typeof window.QuirkyFiles !== 'undefined') ? window.QuirkyFiles : null;
  }
  const xesc = v => esc(String(v == null ? '' : v)).replace(/"/g, '&quot;');

  function normPath(p) {
    return String(p == null ? '' : p).replace(/\\/g, '/').replace(/\/+$/, '');
  }

  function explorerLoad() {
    if (explorerState.loaded || explorerState.loading) return;
    explorerState.loading = true;
    const bridge = explorerBridge();
    if (bridge && typeof bridge.getExportDir === 'function') {
      try { explorerState.exportDir = bridge.getExportDir() || ''; }
      catch (e) { explorerState.exportDir = ''; }
    } else {
      explorerState.exportDir = ''; // desktop: browser download folder
    }
    Promise.all([
      window.IDE.api.workspace.info().catch(function () { return null; }),
      window.IDE.api.workspace.presets().catch(function () { return null; })
    ]).then(function (res) {
      explorerState.info = res[0];
      explorerState.presets = (res[1] && res[1].presets) ? res[1].presets : [];
      deriveWorkspaceChoice();
      deriveExportChoice();
      explorerState.loaded = true;
      explorerState.loading = false;
      explorerRefresh();
    });
  }

  /** Resolve a workspace choice ('shared'…) to a real path. */
  function presetPathForChoice(choice) {
    const choiceToId = { shared: 'shared-quirky', downloads: 'downloads', documents: 'documents' };
    for (let i = 0; i < explorerState.presets.length; i++) {
      if (explorerState.presets[i].id === choiceToId[choice]) {
        return explorerState.presets[i].path;
      }
    }
    return WORKSPACE_FALLBACK_PATHS[choice] || null;
  }

  /** Match the LIVE workspace against the known options so the right one
  starts selected; anything else pre-fills the custom row. */
  function deriveWorkspaceChoice() {
    const info = explorerState.info;
    explorerState.wsChoice = 'default';
    if (info && !info.isDefault) {
      const live = normPath(info.path);
      let matched = null;
      ['shared', 'downloads', 'documents'].forEach(function (c) {
        if (!matched && normPath(presetPathForChoice(c)) === live) matched = c;
      });
      if (matched) {
        explorerState.wsChoice = matched;
      } else {
        explorerState.wsChoice = 'custom';
        explorerState.wsCustom = info.path;
      }
    }
    explorerState.wsChoiceBase = explorerState.wsChoice;
    explorerState.wsCustomBase = explorerState.wsChoice === 'custom'
      ? normPath(explorerState.wsCustom) : '';
  }

  /** '' from the bridge = Downloads default. */
  function deriveExportChoice() {
    const dir = normPath(explorerState.exportDir);
    explorerState.expChoice = 'downloads';
    if (dir !== '') {
      if (dir === normPath(EXPORT_DOCS_PATH)) explorerState.expChoice = 'documents';
      else {
        explorerState.expChoice = 'custom';
        explorerState.expCustom = explorerState.exportDir;
      }
    }
    explorerState.expChoiceBase = explorerState.expChoice;
    explorerState.expCustomBase = explorerState.expChoice === 'custom' ? dir : '';
  }

  /* ── Draft dirty checks (drive the general 💾 Save Changes button) ── */
  function workspaceIsDirty() {
    if (!explorerState.loaded) return false;
    if (explorerState.wsChoice !== explorerState.wsChoiceBase) return true;
    if (explorerState.wsChoice === 'custom' &&
      normPath(explorerState.wsCustom) !== explorerState.wsCustomBase) return true;
    return false;
  }
  function exportIsDirty() {
    if (!explorerState.loaded || explorerState.exportDir === null) return false;
    if (explorerState.expChoice !== explorerState.expChoiceBase) return true;
    if (explorerState.expChoice === 'custom' &&
      normPath(explorerState.expCustom) !== explorerState.expCustomBase) return true;
    return false;
  }
  function explorerIsDirty() { return workspaceIsDirty() || exportIsDirty(); }

  /* ── Where the drafts want to point ── */
  function workspaceTarget() {
    if (explorerState.wsChoice === 'default') return '__default__';
    if (explorerState.wsChoice === 'custom') return explorerState.wsCustom.trim();
    return presetPathForChoice(explorerState.wsChoice);
  }
  function exportTarget() {
    if (explorerState.expChoice === 'downloads') return ''; // bridge: '' = Downloads
    if (explorerState.expChoice === 'documents') return EXPORT_DOCS_PATH;
    return explorerState.expCustom.trim();
  }

  /* Re-render only when the Explorer card is actually on screen */
  function explorerRefresh() {
    if (document.getElementById('explorer-ws-choices')) renderAll(searchQuery);
  }

  /* ── Shared renderer for one radio-style option row ── */
  function renderChoice(dataAttr, value, selected, label, sub) {
    return '<button type="button" class="settings-choice' + (selected ? ' selected' : '') +
      '" ' + dataAttr + '="' + xesc(value) + '" aria-pressed="' + (selected ? 'true' : 'false') + '">' +
      '<span class="settings-choice-dot" aria-hidden="true"></span>' +
      '<span class="settings-choice-label">' + esc(label) + '</span>' +
      (sub ? '<span class="settings-choice-sub">' + esc(sub) + '</span>' : '') +
      '</button>';
  }

  function renderExplorerSection() {
    const info = explorerState.info;
    const bridge = explorerBridge();
    const wsCurrent = explorerState.loading ? 'Loading…'
      : (info ? info.path : 'Unknown');
    const exportCurrent = explorerState.exportDir === null ? 'Loading…'
      : (explorerState.exportDir
        ? explorerState.exportDir
        : '/storage/emulated/0/Download (default)');

    const watchSchema = SCHEMA.find(function (s) { return s.key === 'watchIntervalMs'; });

    let html = '';

    /* ── 1 · Change Detection ── */
    html += '<div class="settings-group-box">' +
      '<div class="settings-group-title">Change Detection</div>' +
      (watchSchema ? renderSettingRow(watchSchema) : '') +
      '</div>';

    /* ── 2 · Workspace Directory ── */
    html += '<div class="settings-group-box">' +
      '<div class="settings-group-title">Workspace Directory</div>' +
      '<div class="settings-current-path"><span class="scp-label">Current workspace</span>' +
      esc(wsCurrent) + '</div>' +
      '<div class="settings-choices" id="explorer-ws-choices">' +
      renderChoice('data-ws-choice', 'default', explorerState.wsChoice === 'default', 'App Default', 'Private app storage') +
      renderChoice('data-ws-choice', 'shared', explorerState.wsChoice === 'shared', 'Shared Storage', '/QuirkyIDE') +
      renderChoice('data-ws-choice', 'downloads', explorerState.wsChoice === 'downloads', 'Downloads', '/Download/QuirkyIDE') +
      renderChoice('data-ws-choice', 'documents', explorerState.wsChoice === 'documents', 'Documents', '/Documents/QuirkyIDE') +
      renderChoice('data-ws-choice', 'custom', explorerState.wsChoice === 'custom', 'Custom', 'Any writable folder') +
      '</div>';
    if (explorerState.wsChoice === 'custom') {
      html += '<div class="settings-custom-path-edit">' +
        '<input type="text" class="settings-text-input" id="explorer-ws-custom" ' +
        'placeholder="/storage/emulated/0/QuirkyIDE" value="' + xesc(explorerState.wsCustom) + '" ' +
        'autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false">' +
        '<div class="settings-btn-row">' +
        '<button type="button" class="settings-action-btn" id="explorer-ws-browse">🗂️ Browse…</button>' +
        '</div>' +
        '<label class="settings-check-row"><input type="checkbox" id="explorer-ws-copy"' +
        (explorerState.wsCopy ? ' checked' : '') + '> Copy current files to the new workspace</label>' +
        '</div>';
    }
    html += '</div>';

    /* ── 3 · Export Path ── */
    html += '<div class="settings-group-box">' +
      '<div class="settings-group-title">Export Path</div>' +
      '<div class="settings-current-path"><span class="scp-label">Current export folder</span>' +
      esc(exportCurrent) + '</div>' +
      '<div class="settings-choices" id="explorer-exp-choices">' +
      renderChoice('data-exp-choice', 'downloads', explorerState.expChoice === 'downloads', 'Downloads', 'Default') +
      renderChoice('data-exp-choice', 'documents', explorerState.expChoice === 'documents', 'Documents', EXPORT_DOCS_PATH) +
      renderChoice('data-exp-choice', 'custom', explorerState.expChoice === 'custom', 'Custom', 'Any writable folder') +
      '</div>';
    if (explorerState.expChoice === 'custom') {
      html += '<div class="settings-custom-path-edit">' +
        '<input type="text" class="settings-text-input" id="explorer-exp-custom" ' +
        'placeholder="/storage/emulated/0/Download/QuirkyIDE" value="' + xesc(explorerState.expCustom) + '" ' +
        'autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false">' +
        '<div class="settings-btn-row">' +
        (bridge ? '<button type="button" class="settings-action-btn" id="explorer-exp-browse">🗂️ Browse…</button>' : '') +
        '</div>' +
        (bridge ? '' : '<div class="settings-note-row" style="margin:0">Only available inside the Android app — on desktop your browser\'s download folder is used.</div>') +
        '</div>';
    }
    html += '</div>';
    html += '<div class="settings-feat-note">Changes apply through 💾 Save Changes below. Imports live in the ＋ sheet, per-file export in the long-press menu.</div>';
    return html;
  }

  function wireExplorerButtons(scope) {
    if (!scope.querySelector('#explorer-ws-choices')) return; // card filtered out
    explorerLoad();

    const on = function (sel, fn) {
      const el = scope.querySelector(sel);
      if (el) el.addEventListener('click', fn);
    };

    /* ── Workspace options ── */
    scope.querySelectorAll('[data-ws-choice]').forEach(function (b) {
      b.addEventListener('click', function () {
        explorerState.wsChoice = b.getAttribute('data-ws-choice');
        renderAll(searchQuery);
        updateSaveChangesUI();
      });
    });
    const wsCustom = scope.querySelector('#explorer-ws-custom');
    if (wsCustom) wsCustom.addEventListener('input', function () {
      explorerState.wsCustom = this.value;
      updateSaveChangesUI();
    });
    on('#explorer-ws-browse', function () {
      const b = explorerBridge();
      if (b && b.pickWorkspaceFolder) b.pickWorkspaceFolder();
      else if (U && U.toast) U.toast('Browse is only available inside the Android app', 'warning');
    });
    const wsCopy = scope.querySelector('#explorer-ws-copy');
    if (wsCopy) wsCopy.addEventListener('change', function () {
      explorerState.wsCopy = this.checked;
    });

    /* ── Export options ── */
    scope.querySelectorAll('[data-exp-choice]').forEach(function (b) {
      b.addEventListener('click', function () {
        explorerState.expChoice = b.getAttribute('data-exp-choice');
        renderAll(searchQuery);
        updateSaveChangesUI();
      });
    });
    const expCustom = scope.querySelector('#explorer-exp-custom');
    if (expCustom) expCustom.addEventListener('input', function () {
      explorerState.expCustom = this.value;
      updateSaveChangesUI();
    });
    on('#explorer-exp-browse', function () {
      const b = explorerBridge();
      if (b && b.pickExportFolder) b.pickExportFolder();
      else if (U && U.toast) U.toast('Browse is only available inside the Android app', 'warning');
    });
  }

  /* Picker results coming back from the Android app (Browse buttons) */
  window.addEventListener('quirky-files-picked', function (e) {
    const d = (e && e.detail) ? e.detail : {};
    if (d.mode === 'workspace-pick' && !d.error && d.paths && d.paths.length) {
      explorerState.wsChoice = 'custom';
      explorerState.wsCustom = d.paths[0];
      if (document.getElementById('explorer-ws-choices')) renderAll(searchQuery);
      updateSaveChangesUI();
    }
    if (d.mode === 'export-pick' && !d.error && d.paths && d.paths.length) {
      explorerState.expChoice = 'custom';
      explorerState.expCustom = d.paths[0];
      if (document.getElementById('explorer-exp-choices')) renderAll(searchQuery);
      updateSaveChangesUI();
    }
  });

  /* ── Privacy & Data — one boxed sub-section per data domain.
Future clearers (e.g. IDE cache) just append another group box. ── */
  function renderPrivacySection() {
    return '<div class="settings-group-box">' +
      '<div class="settings-group-title">HTTP Client</div>' +
      '<div class="settings-row">' +
      '<div class="settings-info">' +
      '<span class="settings-label">📡 Clear HTTP history</span>' +
      '<span class="settings-desc">Deletes every saved request in the HTTP client</span>' +
      '</div>' +
      '<div class="settings-control">' +
      '<button type="button" class="settings-action-btn danger" id="settings-clear-http">Clear</button>' +
      '</div>' +
      '</div>' +
      '</div>' +
      '<div class="settings-group-box">' +
      '<div class="settings-group-title">Explorer</div>' +
      '<div class="settings-row">' +
      '<div class="settings-info">' +
      '<span class="settings-label">📁 Clear recent files</span>' +
      '<span class="settings-desc">Empties the recents list above the file tree</span>' +
      '</div>' +
      '<div class="settings-control">' +
      '<button type="button" class="settings-action-btn danger" id="settings-clear-recents">Clear</button>' +
      '</div>' +
      '</div>' +
      '</div>' +
      '<div class="settings-group-box">' +
      '<div class="settings-group-title">AI Assistant</div>' +
      '<div class="settings-row">' +
      '<div class="settings-info">' +
      '<span class="settings-label">🤖 Clear AI conversation</span>' +
      '<span class="settings-desc">Wipes the server-side chat session for this device</span>' +
      '</div>' +
      '<div class="settings-control">' +
      '<button type="button" class="settings-action-btn danger" id="settings-clear-ai">Clear</button>' +
      '</div>' +
      '</div>' +
      '</div>' +
      '<div class="settings-note-row">JS/CSS bundles cache-bust automatically on every deploy — if assets ever look stale, a plain reload rebuilds them. Provider API keys cannot be cleared here yet.</div>';
  }

  /* ── ★ v13: Logs card — one boxed sub-section per log source.
  Starts with a single "IDE" box (Console Logs). Future sources
  (PHP / server errors, Workshop quick links) append more boxes. ── */
  function renderLogsSection() {
    return '<div class="settings-group-box">' +
      '<div class="settings-group-title">IDE</div>' +
      '<div class="settings-row">' +
      '<div class="settings-info">' +
      '<span class="settings-label">📄 Console Logs</span>' +
      '<span class="settings-desc">Captured console output, JavaScript errors, failed resource loads & unhandled promise rejections</span>' +
      '</div>' +
      '<div class="settings-control">' +
      '<button type="button" class="settings-action-btn" id="settings-open-console-logs">📋 View</button>' +
      '</div>' +
      '</div>' +
      '</div>' +
      '<div class="settings-note-row">Capture runs continuously in the background — the last 500 entries are kept on this device and survive app restarts. PHP / server-side errors get their own sub-header here next.</div>';
  }

  /** Wire the Backup/Privacy action buttons (called from wireControls). */
  function wireDataButtons(scope) {
    const exportBtn = scope.querySelector('#settings-export-btn');
    if (exportBtn) exportBtn.addEventListener('click', doExportBackup);

    const importBtn = scope.querySelector('#settings-import-btn');
    const importFile = scope.querySelector('#settings-import-file');
    if (importBtn && importFile) {
      importBtn.addEventListener('click', function () { importFile.click(); });
      importFile.addEventListener('change', function () {
        doImportBackup(this.files && this.files[0]);
        this.value = '';
      });
    }

    const clearHttp = scope.querySelector('#settings-clear-http');
    if (clearHttp) {
      clearHttp.addEventListener('click', function () {
        let count = 0;
        try { count = JSON.parse(localStorage.getItem(HTTP_HISTORY_KEY) || '[]').length; } catch (e) { }
        U.modal.confirm({
          title: 'Clear HTTP history?',
          message: 'Delete ' + count + ' saved request(s)? This cannot be undone.',
          danger: true
        }).then(function (ok) {
          if (!ok) return;
          try { localStorage.removeItem(HTTP_HISTORY_KEY); } catch (e) { }
          if (window.IDE && window.IDE.mobileHttpClient && window.IDE.mobileHttpClient.clearHistory) {
            window.IDE.mobileHttpClient.clearHistory();   // keep the live panel in sync
          }
          if (U && U.toast) U.toast('HTTP history cleared');
        });
      });
    }

    const clearRecents = scope.querySelector('#settings-clear-recents');
    if (clearRecents) {
      clearRecents.addEventListener('click', function () {
        U.modal.confirm({
          title: 'Clear recent files?',
          message: 'The recents list in the file tree will be emptied.',
          danger: true
        }).then(function (ok) {
          if (!ok) return;
          try { localStorage.removeItem(RECENT_FILES_KEY); } catch (e) { }
          if (U && U.toast) U.toast('Recent files cleared');
        });
      });
    }

    const clearAi = scope.querySelector('#settings-clear-ai');
    if (clearAi) {
      clearAi.addEventListener('click', async function () {
        const ok = await U.modal.confirm({
          title: 'Clear AI conversation?',
          message: 'This wipes the stored chat session for this device.',
          danger: true
        });
        if (!ok) return;
        try {
          const res = await fetch('index.php?api=ai-clear', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: '{}'
          });
          const j = await res.json();
          if (!res.ok || (j && j.ok === false)) throw new Error((j && j.error) || 'Failed');
          if (U && U.toast) U.toast('AI conversation cleared');
        } catch (e) {
          if (U && U.toast) U.toast('AI clear failed: ' + e.message, 'error');
        }
      });
    }
  }

  /* ═══════════════════════════════════════════════════════════════════════════
     ★ v13: STORAGE & CACHE CARD — Android-style storage screen.
     The server (routes/mobile.php) owns the whitelist of safe-to-delete
     caches; we only display sizes and trigger cleans. Nothing user-made
     is ever listed here.
  ═══════════════════════════════════════════════════════════════════════════ */
  const storageState = { loaded: false, loading: false, items: [] };
  function storageFmtBytes(b) {
    b = Number(b) || 0;
    if (b >= 1073741824) return (b / 1073741824).toFixed(1) + ' GB';
    if (b >= 1048576) return (b / 1048576).toFixed(1) + ' MB';
    if (b >= 1024) return Math.round(b / 1024) + ' KB';
    return b + ' B';
  }
  function storageLoad() {
    if (storageState.loaded || storageState.loading) return;
    storageState.loading = true;
    fetch('index.php?api=mobile.storage-scan', { headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        storageState.items = (j && j.data && Array.isArray(j.data.items)) ? j.data.items : [];
        storageState.loaded = true;
        storageState.loading = false;
        storageRefresh();
      })
      .catch(function () {
        storageState.loaded = true;
        storageState.loading = false;
        storageRefresh();
      });
  }
  /* Re-render only while the card is on screen */
  function storageRefresh() {
    if (document.getElementById('storage-groups')) renderAll(searchQuery);
  }
  function storageClean(targetId, btn) {
    if (btn) btn.disabled = true;
    fetch('index.php?api=mobile.storage-clean', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify({ target: targetId })
    })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        if (j && j.data) {
          if (Array.isArray(j.data.items)) {
            storageState.items = j.data.items;
            storageState.loaded = true;
          }
          var freed = (j.data.cleaned && j.data.cleaned.freed) ? j.data.cleaned.freed : 0;
          if (U && U.toast) U.toast(' Freed ' + storageFmtBytes(freed), 'success');
        } else if (U && U.toast) {
          U.toast((j && j.error) || 'Clean failed', 'error');
        }
        renderAll(searchQuery);
      })
      .catch(function (e) {
        if (U && U.toast) U.toast('Clean failed: ' + e.message, 'error');
        if (btn) btn.disabled = false;
      });
  }
  function renderStorageSection() {
    if (!storageState.loaded) {
      return '<div class="settings-note-row" id="storage-groups" style="margin:0">Scanning caches…</div>';
    }
    var groups = [];
    storageState.items.forEach(function (it) {
      var g = groups.find(function (x) { return x.name === it.group; });
      if (!g) { g = { name: it.group, items: [] }; groups.push(g); }
      g.items.push(it);
    });
    var total = storageState.items.reduce(function (s, it) { return s + (it.bytes || 0); }, 0);
    var html = '<div id="storage-groups">';
    /* Overview box: total reclaimable + one-tap safe clean-all */
    html += '<div class="settings-group-box">' +
      '<div class="settings-group-title">Overview</div>' +
      '<div class="settings-row">' +
      '<div class="settings-info">' +
      '<span class="settings-label">💽 Total reclaimable</span>' +
      '<span class="settings-desc">Everything below is rebuildable or expired — nothing you made is touched</span>' +
      '</div>' +
      '<div class="settings-control">' +
      '<span class="settings-badge ' + (total > 0 ? 'off' : 'on') + '">' + storageFmtBytes(total) + '</span>' +
      '<button type="button" class="settings-action-btn" id="settings-clean-all"' + (total > 0 ? '' : ' disabled') + '>🧹 Clean All</button>' +
      '</div>' +
      '</div>' +
      '</div>';
    groups.forEach(function (g) {
      html += '<div class="settings-group-box">' +
        '<div class="settings-group-title">' + esc(g.name) + '</div>';
      g.items.forEach(function (it) {
        html += '<div class="settings-row">' +
          '<div class="settings-info">' +
          '<span class="settings-label">' + esc(it.label) + '</span>' +
          '<span class="settings-desc">' + esc(it.desc) + '</span>' +
          '</div>' +
          '<div class="settings-control">' +
          '<span class="settings-badge ' + ((it.bytes || 0) > 0 ? 'off' : 'on') + '">' +
          storageFmtBytes(it.bytes) + ((it.count || 0) > 0 ? ' · ' + it.count + ' file' + (it.count === 1 ? '' : 's') : '') +
          '</span>' +
          '<button type="button" class="settings-action-btn" data-storage-clear="' + esc(it.id) + '"' + ((it.bytes || 0) > 0 ? '' : ' disabled') + '>Clear</button>' +
          '</div>' +
          '</div>';
      });
      html += '</div>';
    });
    html += '<div class="settings-note-row">Installed Workshop packages, Apache config and Workshop logs are never touched here — this card only clears rebuildable caches and expired leftovers.</div>';
    html += '</div>';
    return html;
  }
  function wireStorageButtons(scope) {
    if (!scope.querySelector('#storage-groups')) return; // card filtered out by search
    storageLoad();
    var all = scope.querySelector('#settings-clean-all');
    if (all) all.addEventListener('click', function () { storageClean('all', all); });
    scope.querySelectorAll('[data-storage-clear]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        storageClean(btn.getAttribute('data-storage-clear'), btn);
      });
    });
  }
  function renderFeatureCard(s) {
    const draft = ensureFeatureDraft();
    const on = !!draft[s.key];
    return '<button type="button" class="settings-feat-card' + (on ? ' on' : '') + '" data-feature="' + s.key + '" aria-pressed="' + on + '" title="' + esc(s.desc) + '">' +
      '<span class="sf-ico" aria-hidden="true">' + (FEATURE_ICONS[s.key] || '⚙️') + '</span>' +
      '<span class="sf-label">' + esc(s.label) + '</span>' +
      '<span class="sf-state">' + (on ? 'ON' : 'OFF') + '</span>' +
      '</button>';
  }

  /* ── Description truncation ("… Read more") ─────────────────────
  Long descriptions are cut at a word boundary and get a tappable
  "Read more" that expands in place (and collapses again).     */
  const DESC_LIMIT = 96;
  /* ★ v12: thresholds for the dynamic select-row layout.
  A select row flips from Left/Right to Upper/Lower when its
  longest option text exceeds OPTION_STACK_CHARS, or its description
  exceeds DESC_STACK_CHARS — wide dropdowns or long descriptions
  otherwise squeeze the other column into a sliver. Toggles are
  never affected; sliders/text inputs are always Upper/Lower.   */
  const OPTION_STACK_CHARS = 8;
  const DESC_STACK_CHARS = 60;
  const DESC_STORE = {};
  function renderDesc(s) {
    const text = String(s.desc || '');
    if (text.length <= DESC_LIMIT) {
      return '<span class="settings-desc">' + esc(text) + '</span>';
    }
    let cut = text.slice(0, DESC_LIMIT);
    const sp = cut.lastIndexOf(' ');
    if (sp > DESC_LIMIT * 0.6) cut = cut.slice(0, sp);
    DESC_STORE[s.key] = { short: cut, full: text };
    return '<span class="settings-desc" id="desc-' + s.key + '">' + esc(cut) + '… ' +
      '<button type="button" class="settings-readmore" data-desc-toggle="' + s.key + '">Read more</button></span>';
  }
  function renderSettingRow(s) {
    const val = get(s.key);
    let control = '';
    if (s.type === 'toggle') {
      control = '<label class="settings-toggle">' +
        '<input type="checkbox" data-setting="' + s.key + '"' + (val ? ' checked' : '') + '>' +
        '<span class="settings-toggle-track"><span class="settings-toggle-thumb"></span></span>' +
        '</label>';
    } else if (s.type === 'range') {
      control = '<div class="settings-range-wrap">' +
        '<input type="range" data-setting="' + s.key + '" data-unit="' + (s.unit || '') + '"' +
        ' min="' + s.min + '" max="' + s.max + '" step="' + s.step + '" value="' + val + '">' +
        '<span class="settings-range-val" id="val-' + s.key + '">' + val + (s.unit || '') + '</span>' +
        '</div>';
    } else if (s.type === 'select') {
      control = '<select data-setting="' + s.key + '" class="settings-select">' +
        s.options.map(o => '<option value="' + o + '"' + (String(val) === String(o) ? ' selected' : '') + '>' + o + '</option>').join('') +
        '</select>';
    } else if (s.type === 'text') {
      /* v10: free-text setting (UI font family). Full-row width so long
         font stacks fit; applied live via applySetting on input.
         esc() doesn't cover quotes — this lands inside an attribute.  */
      const attrEsc = v => esc(String(v == null ? '' : v)).replace(/"/g, '&quot;');
      control = '<input type="text" class="settings-text-input" data-setting="' + s.key + '"' +
        ' value="' + attrEsc(val) + '" placeholder="' + attrEsc(s.placeholder || '') + '"' +
        ' autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false">';
    } else if (s.type === 'featureRange') {
      const draft = ensureFeatureDraft();
      const fVal = (draft[s.key] != null) ? draft[s.key] : (cfg.watchIntervalMs || 15000);
      const customMax = s.customMax || s.max;
      control = '<div class="settings-range-wrap">' +
        '<input type="range" data-feature-range="' + s.key + '" ' +
        'min="' + s.min + '" max="' + s.max + '" step="' + s.step + '" value="' + Math.min(fVal, s.max) + '">' +
        '<span class="settings-range-val" id="fval-' + s.key + '">' + fVal + (s.unit || '') + '</span>' +
        '</div>';
      if (customMax > s.max) {
        control += '<div class="settings-custom-wrap">' +
          '<label class="settings-custom-label">Custom:</label>' +
          '<input type="number" class="settings-custom-input" data-feature-range-custom="' + s.key + '" min="' + s.min + '" max="' + customMax + '" step="' + s.step + '" value="' + fVal + '">' +
          '<span class="settings-custom-range">' + s.min + '–' + customMax + ' ' + (s.unit || 'ms') + '</span>' +
          '</div>';
      }
    } else if (s.type === 'readonly') {
      control = '<span class="settings-badge ' + (s.value ? 'on' : 'off') + '">' +
        (s.value ? '✓ Enabled' : '✗ Disabled') + '</span>';
    }
    /* ★ v12: dynamic row layout —
    • toggle → always Left/Right (a switch never grows wider).
    • range / featureRange / text → always Upper/Lower (full-width control).
    • select → dynamic: Left/Right while both the dropdown and the
      description stay short; Upper/Lower as soon as either grows.  */
    let stacked;
    if (s.type === 'toggle') {
      stacked = false;
    } else if (s.type === 'select') {
      const longestOption = (s.options || []).reduce(function (m, o) {
        return Math.max(m, String(o).length);
      }, 0);
      stacked = longestOption > OPTION_STACK_CHARS ||
        String(s.desc || '').length > DESC_STACK_CHARS;
    } else {
      stacked = true; // sliders + text inputs stay permanently Upper/Lower
    }
    return '<div class="settings-row' + (stacked ? ' settings-row-stacked' : '') + '">' +
      '<div class="settings-info">' +
      '<span class="settings-label">' + esc(s.label) + '</span>' +
      renderDesc(s) +
      '</div>' +
      '<div class="settings-control">' + control + '</div>' +
      '</div>';
  }

  /* ── Wiring: collapse cards + all controls ── */
  function wireCardHeads(scope) {
    scope.querySelectorAll('.settings-card-head').forEach(function (head) {
      head.addEventListener('click', function () {
        const card = head.closest('.settings-card');
        const cat = card.getAttribute('data-cat');
        const nowOpen = !card.classList.contains('open');
        /* Independent toggle: opening this card never touches the others. */
        card.classList.toggle('open', nowOpen);
        head.setAttribute('aria-expanded', nowOpen ? 'true' : 'false');
        if (nowOpen) {
          openCats.add(cat);
          /* Once the expand animation has started, glide the card into
             view so its last row is reachable without hunting. */
          setTimeout(function () {
            card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
          }, 80);
        } else {
          openCats.delete(cat);
        }
      });
    });
  }

  function wireControls(content) {
    /* "Read more" toggles — delegated once so re-rendered buttons work */
    if (!content._descWired) {
      content._descWired = true;
      content.addEventListener('click', function (e) {
        const btn = e.target.closest('[data-desc-toggle]');
        if (!btn) return;
        e.preventDefault();
        e.stopPropagation();
        const key = btn.getAttribute('data-desc-toggle');
        const span = document.getElementById('desc-' + key);
        const store = DESC_STORE[key];
        if (!span || !store) return;
        const expanded = !span.classList.contains('desc-expanded');
        span.classList.toggle('desc-expanded', expanded);
        span.innerHTML = (expanded ? esc(store.full) : esc(store.short) + '…') +
          ' <button type="button" class="settings-readmore" data-desc-toggle="' + key + '">' +
          (expanded ? 'Show less' : 'Read more') + '</button>';
      });
    }
    content.querySelectorAll('[data-setting]').forEach(el => {
      const key = el.dataset.setting;
      if (el.type === 'checkbox') {
        el.addEventListener('change', function () { set(key, this.checked); });
      } else if (el.type === 'range') {
        el.addEventListener('input', function () {
          set(key, Number(this.value));
          const valEl = document.getElementById('val-' + key);
          if (valEl) valEl.textContent = this.value + (el.dataset.unit || '');
        });
      } else if (el.tagName === 'SELECT') {
        el.addEventListener('change', function () { set(key, this.value); });
      } else if (el.type === 'text') {
        /* v10: free-text settings — debounced live apply while typing */
        let textTimer = null;
        el.addEventListener('input', function () {
          const v = this.value;
          clearTimeout(textTimer);
          textTimer = setTimeout(function () { set(key, v.trim()); }, 250);
        });
        el.addEventListener('change', function () {
          clearTimeout(textTimer);
          set(key, this.value.trim());
        });
      }
    });
    /* v10: Backup & Restore + Privacy & Data action buttons */
    wireDataButtons(content);
    /* ★ Snippet Studio: wire after render */
    var snipScope = content.querySelector('[data-cat="snippets"] .settings-card-inner');
    if (snipScope && window.IDE && window.IDE.mobileSnippets && window.IDE.mobileSnippets.wire) {
      window.IDE.mobileSnippets.wire(snipScope);
    }
    /* ★ v11: Explorer card (workspace directory + export folder) */
    wireExplorerButtons(content);
    /* ★ v13: Storage & Cache card */
    wireStorageButtons(content);

    /* ★ v11: Voice card (TTS + STT) */
    wireVoiceButtons(content);
    // Feature tiles: stage into the draft, save on Save Features
    content.querySelectorAll('.settings-feat-card').forEach(function (card) {
      card.addEventListener('click', async function () {
        var fkey = card.getAttribute('data-feature');
        var cfgKey = FEATURE_KEY_MAP[fkey] || (fkey + '_enabled');
        var draft = ensureFeatureDraft();
        var policy = (cfg && cfg.devicePolicy) ? cfg.devicePolicy : {};
        var defaultOff = policy.default_off_features || [];
        var hardLocked = policy.hard_locked_features || [];
        var unlocks = ensureFeatureDraftUnlocks();

        // Block hard-locked features completely
        if (hardLocked.indexOf(cfgKey) !== -1) {
          if (U && U.toast) U.toast('🚫 ' + card.querySelector('.sf-label').textContent + ' is locked on this device tier.', 'error');
          return;
        }

        var isDefaultOff = defaultOff.indexOf(cfgKey) !== -1;
        var isUnlocked = !!unlocks[cfgKey];
        var turningOn = !draft[fkey];

        // Warning for soft-disabled features
        if (turningOn && isDefaultOff && !isUnlocked) {
          var ok = await U.modal.confirm({
            title: '⚠️ Performance Warning',
            message: 'Your device tier (' + (cfg.deviceTier || 'unknown') + ') recommends keeping this OFF for stability. Enabling it may cause lag or crashes. Enable anyway?',
            confirmText: 'Enable Anyway',
            danger: true
          });
          if (!ok) return;
          unlocks[cfgKey] = true;
        }

        draft[fkey] = !draft[fkey];
        card.classList.toggle('on', draft[fkey]);
        card.setAttribute('aria-pressed', draft[fkey] ? 'true' : 'false');
        var st = card.querySelector('.sf-state');
        if (st) st.textContent = draft[fkey] ? 'ON' : 'OFF';
        updateFeatureSaveUI();
      });
    });
    // Feature range inputs (watch interval) — staged, saved on Save
    content.querySelectorAll('[data-feature-range]').forEach(function (el) {
      const fkey = el.dataset.featureRange;
      const schema = SCHEMA.find(function (s) { return s.key === fkey; });
      const unit = (schema && schema.unit) || 'ms';
      el.addEventListener('input', function () {
        const v = Number(this.value);
        ensureFeatureDraft()[fkey] = v;
        const valEl = document.getElementById('fval-' + fkey);
        if (valEl) valEl.textContent = v + unit;
        const customEl = content.querySelector('[data-feature-range-custom="' + fkey + '"]');
        if (customEl) customEl.value = v;
        updateFeatureSaveUI();
      });
    });
    // Workshop open button
    var workshopBtn = content.querySelector('#settings-open-workshop');
    if (workshopBtn) {
      workshopBtn.addEventListener('click', function () {
        if (window._mobileOpenWorkshop) {
          window._mobileOpenWorkshop();
        } else if (U && U.toast) {
          U.toast('Workshop not available', 'error');
        }
      });
    }

    // Workshop Logs button
    var workshopLogsBtn = content.querySelector('#settings-open-workshop-logs');
    if (workshopLogsBtn) {
      workshopLogsBtn.addEventListener('click', function () {
        if (window.IDE && window.IDE.mobileWorkshop && window.IDE.mobileWorkshop.openLogs) {
          window.IDE.mobileWorkshop.openLogs();
        } else if (U && U.toast) {
          U.toast('Workshop logs not available', 'error');
        }
      });
    }

    // ★ v13: IDE Console Logs button (Settings → Logs → IDE)
    var consoleLogsBtn = content.querySelector('#settings-open-console-logs');
    if (consoleLogsBtn) {
      consoleLogsBtn.addEventListener('click', function () {
        if (window.IDE && window.IDE.mobileLogs && window.IDE.mobileLogs.openConsole) {
          window.IDE.mobileLogs.openConsole();
        } else if (U && U.toast) {
          U.toast('Console logs viewer not available', 'error');
        }
      });
    }

    // Custom numeric boxes for feature ranges
    content.querySelectorAll('[data-feature-range-custom]').forEach(function (el) {
      const fkey = el.dataset.featureRangeCustom;
      const schema = SCHEMA.find(function (s) { return s.key === fkey; });
      if (!schema) return;
      const customMax = schema.customMax || schema.max;
      el.addEventListener('input', function () {
        if (this.value.length > 6) this.value = this.value.slice(0, 6);
      });
      el.addEventListener('change', function () {
        let v = parseInt(this.value, 10);
        if (isNaN(v)) v = ensureFeatureDraft()[fkey] || (cfg.watchIntervalMs || 15000);
        v = Math.max(schema.min, Math.min(v, customMax));
        this.value = v;
        ensureFeatureDraft()[fkey] = v;
        const slider = content.querySelector('[data-feature-range="' + fkey + '"]');
        if (slider) slider.value = Math.min(v, schema.max);
        const valEl = document.getElementById('fval-' + fkey);
        if (valEl) valEl.textContent = v + (schema.unit || 'ms');
        updateFeatureSaveUI();
      });
    });
  }

  /* ═══════════════════════════════════════════════════════════════
  ★ v11 VOICE — Text-To-Speech + Speech-To-Text
  ──────────────────────────────────────────────────────────────────
  A dedicated "Voice" card with two boxed sub-sections (TTS / STT),
  plus two full-screen testers built dynamically:
    🔊 Voice Preview — pick a voice, speed & pitch, read sample text
    🎙️ Mic Check     — tap or press-and-hold dictation, live text
  
  Everything runs ON-DEVICE:
    • Android app → native bridges window.QuirkyTts / window.QuirkyStt
    • Desktop browser → Web Speech fallback for TTS only (dev comfort);
      STT shows an honest "Android app only" note
  No PHP / server involvement anywhere.
  ═══════════════════════════════════════════════════════════════ */
  const VOICE_SAMPLE_TEXT =
    'Hey! This is Quirky IDE. I can read text aloud — errors, comments, anything you select.';

  const STT_LANGS = [
    { id: 'system', label: 'System default' },
    { id: 'en-US', label: 'English (United States)' },
    { id: 'en-GB', label: 'English (United Kingdom)' },
    { id: 'es-ES', label: 'Spanish (Spain)' },
    { id: 'fr-FR', label: 'French (France)' },
    { id: 'de-DE', label: 'German (Germany)' },
    { id: 'hi-IN', label: 'Hindi (India)' },
    { id: 'id-ID', label: 'Indonesian (Indonesia)' },
    { id: 'ar-SA', label: 'Arabic (Saudi Arabia)' },
    { id: 'zh-CN', label: 'Chinese (Simplified)' },
    { id: 'ja-JP', label: 'Japanese (Japan)' },
    { id: 'ko-KR', label: 'Korean (Korea)' },
    { id: 'pt-BR', label: 'Portuguese (Brazil)' },
    { id: 'ru-RU', label: 'Russian (Russia)' },
    { id: 'tr-TR', label: 'Turkish (Turkey)' }
  ];

  const voiceState = { ttsInfo: null };
  let micState = 'idle';          // idle | listening | processing
  let micBaseText = '';           // finalized sentences so far
  let micHoldTimer = null;
  let micHoldMode = false;
  let voiceOverlayEscHandler = null;

  /* ── Voice prefs: stored under the same quirky.ide.settings.* prefix
     (so Backup & Restore picks them up for free) but OUTSIDE the
     SCHEMA — they apply live, no reload, so they must never dirty the
     "Save Changes" button. ── */
  function voicePrefGet(key, fallback) {
    try {
      const raw = localStorage.getItem(LS_PREFIX + key);
      if (raw === null) return fallback;
      if (typeof fallback === 'number') {
        const n = Number(raw);
        return isFinite(n) ? n : fallback;
      }
      return raw;
    } catch (e) { return fallback; }
  }
  function voicePrefSet(key, value) {
    try { localStorage.setItem(LS_PREFIX + key, String(value)); } catch (e) { }
  }

  /* ── Voice card renderer (two group boxes: TTS + STT) ── */
  function renderVoiceSection() {
    const nativeTts = !!window.QuirkyTts;
    const nativeStt = !!window.QuirkyStt;
    const browserTts = !nativeTts && ('speechSynthesis' in window);
    const micGranted = nativeStt && window.QuirkyStt.hasMicPermission();
    const ttsOn = get('voiceTts');
    const sttOn = get('voiceStt');

    /* TTS engine info row */
    let ttsEngineRow;
    if (nativeTts) {
      ttsEngineRow =
        '<div class="settings-row">' +
        '<div class="settings-info">' +
        '<span class="settings-label">🛠️ Voice engine</span>' +
        '<span class="settings-desc" id="voice-engine-desc">Detecting installed TTS engine…</span>' +
        '</div>' +
        '<div class="settings-control"><span class="settings-badge on" id="voice-engine-badge">…</span></div>' +
        '</div>';
    } else if (browserTts) {
      ttsEngineRow =
        '<div class="settings-row">' +
        '<div class="settings-info">' +
        '<span class="settings-label">🛠️ Voice engine</span>' +
        '<span class="settings-desc">Browser speech synthesis — desktop testing mode</span>' +
        '</div>' +
        '<div class="settings-control"><span class="settings-badge on">Browser</span></div>' +
        '</div>';
    } else {
      ttsEngineRow =
        '<div class="settings-row">' +
        '<div class="settings-info">' +
        '<span class="settings-label">🛠️ Voice engine</span>' +
        '<span class="settings-desc">No speech engine found on this device</span>' +
        '</div>' +
        '<div class="settings-control"><span class="settings-badge off">Unavailable</span></div>' +
        '</div>';
    }

    let html = '<div class="settings-card-inner" id="voice-card-inner">';

    /* ── TEXT TO SPEECH box ── */
    html += '<div class="settings-group-box">' +
      '<div class="settings-group-title">Text To Speech (TTS)</div>' +
      renderSettingRow(SCHEMA.find(s => s.key === 'voiceTts')) +
      ttsEngineRow +
      '<div class="settings-row">' +
      '<div class="settings-info">' +
      '<span class="settings-label">🔊 Voice Preview</span>' +
      '<span class="settings-desc">' +
      (ttsOn ? 'Hear the voice — choose voice, speed & pitch' : 'Turn on Text-to-Speech above to unlock') +
      '</span>' +
      '</div>' +
      '<div class="settings-control">' +
      '<button type="button" class="settings-action-btn" id="settings-voice-preview"' +
      (ttsOn ? '' : ' disabled') + '>Open</button>' +
      '</div>' +
      '</div>' +
      '</div>';

    /* ── SPEECH TO TEXT box ── */
    let micRow;
    if (!nativeStt) {
      micRow =
        '<div class="settings-row">' +
        '<div class="settings-info">' +
        '<span class="settings-label">🎤 Microphone access</span>' +
        '<span class="settings-desc">Dictation works inside the Android app</span>' +
        '</div>' +
        '<div class="settings-control"><span class="settings-badge off">App only</span></div>' +
        '</div>';
    } else if (micGranted) {
      micRow =
        '<div class="settings-row">' +
        '<div class="settings-info">' +
        '<span class="settings-label">🎤 Microphone access</span>' +
        '<span class="settings-desc">Granted — the mic is only active while you listen</span>' +
        '</div>' +
        '<div class="settings-control"><span class="settings-badge on">✓ Granted</span></div>' +
        '</div>';
    } else {
      micRow =
        '<div class="settings-row">' +
        '<div class="settings-info">' +
        '<span class="settings-label">🎤 Microphone access</span>' +
        '<span class="settings-desc">One-time Android permission needed for dictation</span>' +
        '</div>' +
        '<div class="settings-control">' +
        '<button type="button" class="settings-action-btn" id="settings-voice-mic-allow">Allow</button>' +
        '</div>' +
        '</div>';
    }

    const micHint = !nativeStt
      ? 'Open Quirky IDE in the Android app to use dictation'
      : (!micGranted ? 'Allow microphone access first'
        : (!sttOn ? 'Turn on Speech-to-Text above to unlock'
          : 'Speak and watch your words appear — tap or press & hold'));
    const micBlocked = !nativeStt || !micGranted || !sttOn;

    html += '<div class="settings-group-box">' +
      '<div class="settings-group-title">Speech To Text (STT)</div>' +
      renderSettingRow(SCHEMA.find(s => s.key === 'voiceStt')) +
      micRow +
      '<div class="settings-row">' +
      '<div class="settings-info">' +
      '<span class="settings-label">🎙️ Mic Check</span>' +
      '<span class="settings-desc">' + esc(micHint) + '</span>' +
      '</div>' +
      '<div class="settings-control">' +
      '<button type="button" class="settings-action-btn" id="settings-voice-mic-check"' +
      (micBlocked ? ' disabled' : '') + '>Open</button>' +
      '</div>' +
      '</div>' +
      '</div>';

    html += '<div class="settings-note-row">Voice stays on this device: speaking happens locally, and dictation runs through your phone\'s own speech service.</div>';
    html += '</div>';
    return html;
  }

  /* ── Wire the Voice card buttons (called from wireControls) ── */
  function wireVoiceButtons(scope) {
    if (!scope.querySelector('#voice-card-inner')) return;

    const previewBtn = scope.querySelector('#settings-voice-preview');
    if (previewBtn) previewBtn.addEventListener('click', openVoicePreviewOverlay);

    const micCheckBtn = scope.querySelector('#settings-voice-mic-check');
    if (micCheckBtn) micCheckBtn.addEventListener('click', openMicCheckOverlay);

    const allowBtn = scope.querySelector('#settings-voice-mic-allow');
    if (allowBtn) allowBtn.addEventListener('click', function () {
      if (window.QuirkyStt) window.QuirkyStt.requestMicPermission();
    });

    /* Flipping a master switch must re-render the card so the
       dependent "Open" buttons enable/disable immediately. The tiny
       delay lets the generic data-setting listener persist first. */
    ['voiceTts', 'voiceStt'].forEach(function (key) {
      const el = scope.querySelector('[data-setting="' + key + '"]');
      if (el) el.addEventListener('change', function () {
        setTimeout(function () { renderAll(searchQuery); }, 0);
      });
    });

    voiceRefreshEngineRow();
  }

  /* Fill the "Voice engine" row once the native TTS engine reports ready. */
  function voiceRefreshEngineRow() {
    const descEl = document.getElementById('voice-engine-desc');
    const badgeEl = document.getElementById('voice-engine-badge');
    if (!descEl || !window.QuirkyTts) return;

    const fill = function () {
      let info = null;
      try { info = JSON.parse(window.QuirkyTts.getInfo()); } catch (e) { info = null; }
      if (!info || !info.ready) return false;
      voiceState.ttsInfo = info;
      const label = (info.engine && info.engine.label) ? info.engine.label : 'TTS engine';
      const count = Array.isArray(info.voices) ? info.voices.length : 0;
      descEl.textContent = label + ' · ' + count + ' voice' + (count === 1 ? '' : 's') + ' ready';
      if (badgeEl) { badgeEl.textContent = 'Ready'; badgeEl.className = 'settings-badge on'; }
      return true;
    };
    if (fill()) return;

    let tries = 0;
    const poll = setInterval(function () {
      tries++;
      if (fill() || tries > 15) clearInterval(poll);
      if (tries > 15) {
        descEl.textContent = 'No TTS engine found — install one (e.g. "Speech Services by Google") from the Play Store';
        if (badgeEl) { badgeEl.textContent = 'Missing'; badgeEl.className = 'settings-badge off'; }
      }
    }, 400);
  }

  /* ═══════════════════════════════════════════════════════════════
  🔊 VOICE PREVIEW OVERLAY (TTS tester)
  ═══════════════════════════════════════════════════════════════ */
  function buildVoicePreviewOverlay() {
    let ov = document.getElementById('voice-preview-overlay');
    if (ov) return ov;
    ov = document.createElement('div');
    ov.id = 'voice-preview-overlay';
    ov.className = 'm-voice-overlay hidden';
    ov.innerHTML =
      '<div class="m-voice-header">' +
      '<span class="m-voice-title">🔊 Voice Preview</span>' +
      '<button type="button" class="m-voice-close" id="voice-preview-close" aria-label="Close voice preview">✕</button>' +
      '</div>' +
      '<div class="m-voice-body">' +

      '<div class="settings-group-box">' +
      '<div class="settings-group-title">Voice</div>' +
      '<div class="settings-row settings-row-stacked">' +
      '<div class="settings-info">' +
      '<span class="settings-label">TTS Provider</span>' +
      '<span class="settings-desc">The engine that reads the text aloud</span>' +
      '</div>' +
      '<div class="settings-control">' +
      '<div class="vdrop" id="voice-preview-provider"></div>' +
      '</div>' +
      '</div>' +
      '<div class="settings-row settings-row-stacked">' +
      '<div class="settings-info">' +
      '<span class="settings-label">Voice</span>' +
      '<span class="settings-desc" id="voice-preview-voice-count">Loading voices…</span>' +
      '</div>' +
      '<div class="settings-control">' +
      '<div class="vdrop" id="voice-preview-voice"></div>' +
      '</div>' +
      '</div>' +
      '<div class="settings-row settings-row-stacked">' +
      '<div class="settings-info"><span class="settings-label">Speed</span></div>' +
      '<div class="settings-control"><div class="settings-range-wrap" style="width:100%">' +
      '<input type="range" id="voice-preview-rate" min="0.5" max="2" step="0.1" style="flex:1 1 auto;width:auto">' +
      '<span class="settings-range-val" id="voice-preview-rate-val">1.0×</span>' +
      '</div></div>' +
      '</div>' +
      '<div class="settings-row settings-row-stacked">' +
      '<div class="settings-info"><span class="settings-label">Pitch</span></div>' +
      '<div class="settings-control"><div class="settings-range-wrap" style="width:100%">' +
      '<input type="range" id="voice-preview-pitch" min="0.5" max="2" step="0.1" style="flex:1 1 auto;width:auto">' +
      '<span class="settings-range-val" id="voice-preview-pitch-val">1.0×</span>' +
      '</div></div>' +
      '</div>' +
      '</div>' +

      '<div class="settings-group-box">' +
      '<div class="settings-group-title">Text To Read</div>' +
      '<div style="padding:0.7rem 0.8rem">' +
      '<textarea id="voice-preview-text" class="voice-textarea" maxlength="1000" ' +
      'placeholder="Type something for the voice to read…"></textarea>' +
      '<div class="voice-charcount" id="voice-preview-count">0 characters</div>' +
      '</div>' +
      '</div>' +

      '<div class="voice-ctl-row">' +
      '<button type="button" class="settings-action-btn" id="voice-preview-play">▶ Read Aloud</button>' +
      '<button type="button" class="settings-action-btn danger" id="voice-preview-stop" disabled>⏹ Stop</button>' +
      '</div>' +
      '<div class="voice-status" id="voice-preview-status">Ready</div>' +
      '<div class="settings-note-row">More voices come from TTS engine apps on your device — ' +
      'Android\'s TTS settings page lets you download extra voices and switch engines.</div>' +
      '<div class="voice-ctl-row" style="margin-top:0.6rem">' +
      '<button type="button" class="settings-action-btn" id="voice-preview-logs">📋 Logs</button>' +
      '</div>' +
      '</div>';
    document.body.appendChild(ov);
    return ov;
  }

  function openVoicePreviewOverlay() {
    const ov = buildVoicePreviewOverlay();
    wireVoicePreviewControls(ov);
    ov.classList.remove('hidden');
    voicePreviewRestore();
    voicePreviewLoadVoices();
    voicePreviewSetStatus('Ready');
    voicePreviewSetBusy(false);
    registerVoiceOverlayEscape(closeVoicePreviewOverlay);
    try {
      const S = window.IDE && window.IDE.mobileSheets;
      if (S && S.registerOverlay) S.registerOverlay('voice-preview-overlay', closeVoicePreviewOverlay);
    } catch (e) { }
  }

  function closeVoicePreviewOverlay() {
    voicePreviewStop();
    voicePreviewSetBusy(false);
    const ov = document.getElementById('voice-preview-overlay');
    if (ov) ov.classList.add('hidden');
    unregisterVoiceOverlayEscape();
    try {
      const S = window.IDE && window.IDE.mobileSheets;
      if (S && S.unregisterOverlay) S.unregisterOverlay('voice-preview-overlay');
    } catch (e) { }
  }

  function wireVoicePreviewControls(ov) {
    if (ov._wired) return;
    ov._wired = true;

    ov.querySelector('#voice-preview-close').addEventListener('click', closeVoicePreviewOverlay);

    const rateEl = ov.querySelector('#voice-preview-rate');
    const pitchEl = ov.querySelector('#voice-preview-pitch');
    rateEl.addEventListener('input', function () {
      voicePrefSet('voiceRate', this.value);
      voicePreviewUpdateSliderLabels();
    });
    pitchEl.addEventListener('input', function () {
      voicePrefSet('voicePitch', this.value);
      voicePreviewUpdateSliderLabels();
    });

    const textEl = ov.querySelector('#voice-preview-text');
    textEl.addEventListener('input', function () {
      voicePrefSet('voiceText', this.value);
      voicePreviewUpdateCount();
    });

    ov.querySelector('#voice-preview-voice').addEventListener('change', function () {
      voicePrefSet('voiceVoice', this.value);
    });

    ov.querySelector('#voice-preview-play').addEventListener('click', voicePreviewPlay);
    ov.querySelector('#voice-preview-stop').addEventListener('click', voicePreviewStop);

    ov.querySelector('#voice-preview-logs').addEventListener('click', function () {
      openVoiceLogs('tts');
    });
  }

  /* Restore voice/speed/pitch/text choices from the previous session. */
  function voicePreviewRestore() {
    const rateEl = document.getElementById('voice-preview-rate');
    const pitchEl = document.getElementById('voice-preview-pitch');
    const textEl = document.getElementById('voice-preview-text');
    if (rateEl) rateEl.value = voicePrefGet('voiceRate', 1);
    if (pitchEl) pitchEl.value = voicePrefGet('voicePitch', 1);
    voicePreviewUpdateSliderLabels();
    if (textEl) textEl.value = voicePrefGet('voiceText', VOICE_SAMPLE_TEXT);
    voicePreviewUpdateCount();
  }

  /* ═══════════════════════════════════════════════════════════
★ v15.2: DROPDOWNS + ENGINE PICKERS + IN-DROPDOWN DOWNLOADS
• Lists now merge: Android bridge (installed/active) + PHP
  catalog (downloadable entries) — so Piper / Whisper show up
  EVEN when nothing is installed yet, with a  chip.
•  chip → confirm popup → SSE install (speech-install).
• System TTS voices are dynamic (from the engine) and get a
  variant suffix so duplicate locale names are unique.
• STT language list stays curated ON PURPOSE: Android's
  SpeechRecognizer exposes no "supported languages" API.
═══════════════════════════════════════════════════════════ */
  /* ═══════════════════════════════════════════════════════════
  ★ v15.3: VOICE LOGS — separate TTS / STT diagnostic logs
  Voice Preview and Mic Check each get their own "📋 Logs" button
  opening a viewer that shows ONLY that side's entries, so install
  steps, engine states and errors can be read (and copied) clearly
  instead of guessing from a short toast. Entries persist in
  localStorage (max 200 per side).
  ═══════════════════════════════════════════════════════════ */
  var voiceLogs = { tts: [], stt: [] };
  var voiceLogsChannel = 'tts';
  var VOICE_LOG_MAX = 200;
  var voiceLogsSaveTimer = null;
  var sttHearingLogged = false;
  function voiceLogsLoad() {
    ['tts', 'stt'].forEach(function (ch) {
      try {
        var raw = localStorage.getItem('quirky.ide.voice.logs.' + ch);
        if (raw) {
          var arr = JSON.parse(raw);
          if (Array.isArray(arr)) voiceLogs[ch] = arr.slice(-VOICE_LOG_MAX);
        }
      } catch (e) { }
    });
  }
  function voiceLogsSave() {
    if (voiceLogsSaveTimer) clearTimeout(voiceLogsSaveTimer);
    voiceLogsSaveTimer = setTimeout(function () {
      try { localStorage.setItem('quirky.ide.voice.logs.tts', JSON.stringify(voiceLogs.tts)); } catch (e) { }
      try { localStorage.setItem('quirky.ide.voice.logs.stt', JSON.stringify(voiceLogs.stt)); } catch (e) { }
    }, 400);
  }
  /** Append one entry. level: 'info' | 'ok' | 'warn' | 'err'. */
  function voiceLog(channel, level, msg) {
    var arr = voiceLogs[channel];
    if (!arr || msg === null || msg === undefined || String(msg) === '') return;
    var d = new Date();
    var pad = function (n) { return (n < 10 ? '0' : '') + n; };
    arr.push({
      time: pad(d.getHours()) + ':' + pad(d.getMinutes()) + ':' + pad(d.getSeconds()),
      level: level,
      msg: String(msg)
    });
    if (arr.length > VOICE_LOG_MAX) arr.shift();
    voiceLogsSave();
    var ov = document.getElementById('voice-logs-overlay');
    if (ov && !ov.classList.contains('hidden') && channel === voiceLogsChannel) voiceLogsRender();
  }
  function voiceLogsRawText() {
    var arr = voiceLogs[voiceLogsChannel] || [];
    var lines = [];
    for (var i = 0; i < arr.length; i++) {
      lines.push('[' + arr[i].time + '] ' + String(arr[i].level).toUpperCase() + '  ' + arr[i].msg);
    }
    return lines.length ? lines.join('\n') : '(empty)';
  }
  function voiceLogsRender() {
    var body = document.getElementById('voice-logs-body');
    if (!body) return;
    var arr = voiceLogs[voiceLogsChannel] || [];
    var countEl = document.getElementById('voice-logs-count');
    if (countEl) countEl.textContent = String(arr.length);
    if (!arr.length) {
      body.innerHTML = '<div class="m-logs-empty"><span>📋</span><p>No logs yet.<br>Actions, downloads and errors will appear here.</p></div>';
      return;
    }
    var html = '';
    for (var i = 0; i < arr.length; i++) {
      var e = arr[i];
      var lvl = e.level === 'err' ? 'error' : (e.level === 'warn' ? 'warn' : (e.level === 'ok' ? 'ok' : 'info'));
      html += '<div class="mlog-entry lvl-' + lvl + '">' +
        '<div class="mlog-line1">' +
        '<span class="mlog-time">' + esc(e.time) + '</span>' +
        '<span class="mlog-lvl">' + esc(String(e.level).toUpperCase()) + '</span>' +
        '</div>' +
        '<div class="mlog-msg">' + esc(e.msg) + '</div>' +
        '</div>';
    }
    body.innerHTML = html;
    body.scrollTop = body.scrollHeight;
  }
  function buildVoiceLogsOverlay() {
    var ov = document.getElementById('voice-logs-overlay');
    if (ov) return ov;
    ov = document.createElement('div');
    ov.id = 'voice-logs-overlay';
    ov.className = 'm-logs-overlay m-voice-logs-overlay hidden';
    ov.innerHTML =
      '<div class="m-logs-header">' +
      '<span class="m-logs-title" id="voice-logs-title">📋 Logs</span>' +
      '<span class="m-logs-count" id="voice-logs-count">0</span>' +
      '<button type="button" class="m-logs-close" id="voice-logs-close" aria-label="Close logs">✕</button>' +
      '</div>' +
      '<div class="m-logs-toolbar">' +
      '<div class="m-logs-actions">' +
      '<button type="button" class="m-logs-btn" id="voice-logs-copy">📋 Copy</button>' +
      '<button type="button" class="m-logs-btn danger" id="voice-logs-clear">🗑 Clear</button>' +
      '</div>' +
      '</div>' +
      '<div class="m-logs-body" id="voice-logs-body"></div>';
    document.body.appendChild(ov);
    ov.querySelector('#voice-logs-close').addEventListener('click', closeVoiceLogs);
    ov.querySelector('#voice-logs-copy').addEventListener('click', function () {
      if (U && U.copyToClipboard) {
        U.copyToClipboard(voiceLogsRawText()).then(function (ok) {
          if (U && U.toast) U.toast(ok ? '📋 Logs copied' : '⚠ Copy failed');
        });
      }
    });
    ov.querySelector('#voice-logs-clear').addEventListener('click', function () {
      voiceLogs[voiceLogsChannel] = [];
      voiceLogsSave();
      voiceLogsRender();
      if (U && U.toast) U.toast('Logs cleared');
    });
    return ov;
  }
  function openVoiceLogs(channel) {
    voiceLogsChannel = (channel === 'stt') ? 'stt' : 'tts';
    var ov = buildVoiceLogsOverlay();
    var title = document.getElementById('voice-logs-title');
    if (title) title.textContent = (voiceLogsChannel === 'stt') ? '🎙️ Mic Check Logs' : '🔊 Voice Preview Logs';
    voiceLogsRender();
    ov.classList.remove('hidden');
    try {
      var S = window.IDE && window.IDE.mobileSheets;
      if (S && S.registerOverlay) S.registerOverlay('voice-logs-overlay', closeVoiceLogs);
    } catch (e) { }
  }
  function closeVoiceLogs() {
    var ov = document.getElementById('voice-logs-overlay');
    if (ov) ov.classList.add('hidden');
    try {
      var S = window.IDE && window.IDE.mobileSheets;
      if (S && S.unregisterOverlay) S.unregisterOverlay('voice-logs-overlay');
    } catch (e) { }
  }

  /** Installed-engine inventory from the Android bridge (null on desktop). */
  function speechJavaCatalog() {
    if (window.QuirkySpeech) {
      try { return JSON.parse(window.QuirkySpeech.getCatalog()); } catch (e) { }
    }
    return null;
  }
  /* Server catalog (Speech.php) — knows installed AND downloadable entries. */
  var speechState = { entries: [], loaded: false, loading: false, downloading: {} };
  function speechLoadCatalog(done) {
    if (speechState.loaded || speechState.loading) { if (done) done(); return; }
    speechState.loading = true;
    voiceLog('tts', 'info', 'Loading speech catalog (speech-catalog)…');
    voiceLog('stt', 'info', 'Loading speech catalog (speech-catalog)…');
    fetch('index.php?api=speech-catalog', { headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        speechState.entries = (j && j.data && Array.isArray(j.data.entries)) ? j.data.entries : [];
        speechState.loaded = true;
        speechState.loading = false;
        var msg = 'Catalog loaded — ' + speechState.entries.length + ' entr' + (speechState.entries.length === 1 ? 'y' : 'ies');
        voiceLog('tts', 'ok', msg);
        voiceLog('stt', 'ok', msg);
        if (done) done();
      })
      .catch(function (e) {
        speechState.loaded = true;
        speechState.loading = false;
        var msg = 'Catalog fetch failed: ' + ((e && e.message) ? e.message : e);
        voiceLog('tts', 'err', msg);
        voiceLog('stt', 'err', msg);
        if (done) done();
      });
  }
  function speechEntry(id) {
    for (var i = 0; i < speechState.entries.length; i++) {
      if (speechState.entries[i].id === id) return speechState.entries[i];
    }
    return null;
  }
  /** Which runtimes are TTS / STT — derived from the models that need them. */
  function speechRuntimeKinds() {
    var kinds = { tts: {}, stt: {} };
    speechState.entries.forEach(function (e) {
      if (e.kind === 'tts-model' && e.runtime) kinds.tts[e.runtime] = true;
      if (e.kind === 'stt-model' && e.runtime) kinds.stt[e.runtime] = true;
    });
    return kinds;
  }
  function prettyRuntime(id) {
    var s = String(id || '').replace(/[-_]+/g, ' ').trim();
    return s ? s.charAt(0).toUpperCase() + s.slice(1) : String(id || '');
  }
  function speechRuntimeInstalled(jcat, rt) {
    return !!(jcat && jcat.runtimes && jcat.runtimes[rt]);
  }
  /** '<runtime>|<model>' engine id when the model is installed, else null. */
  function speechModelEngineId(jcat, kind, rt, modelId) {
    var id = rt + '|' + modelId;
    var list = kind === 'tts' ? (jcat && jcat.tts) : (jcat && jcat.stt);
    if (Array.isArray(list)) {
      for (var i = 0; i < list.length; i++) if (list[i].id === id) return id;
    }
    return null;
  }
  /* ── Compact dropdown (2 visible rows, scrollable) + ⬇ chip ── */
  function closeAllVDrops(except) {
    document.querySelectorAll('.vdrop.open').forEach(function (d) {
      if (d === except) return;
      d.classList.remove('open');
      var l = d.querySelector('.vdrop-list');
      if (l) l.classList.add('hidden');
      var b = d.querySelector('.vdrop-btn');
      if (b) b.setAttribute('aria-expanded', 'false');
    });
  }
  document.addEventListener('click', function () { closeAllVDrops(null); });
  function vdropRender(container, items, currentId, onPick, sourceChannel) {
    if (!container) return;
    var cur = null;
    items.forEach(function (it) { if (it.id === currentId) cur = it; });
    if (!cur && items.length) cur = items[0];
    container.innerHTML =
      '<button type="button" class="vdrop-btn" aria-haspopup="listbox" aria-expanded="false">' +
      '<span class="vdrop-val">' + esc(cur ? cur.label : '—') + '</span>' +
      '<span class="vdrop-chev" aria-hidden="true">▾</span>' +
      '</button>' +
      '<div class="vdrop-list hidden" role="listbox">' +
      items.map(function (it) {
        var dlId = it.entryId || it.id;
        var busy = !!speechState.downloading[dlId];
        return '<button type="button" class="vdrop-item' +
          (cur && it.id === cur.id ? ' selected' : '') +
          '" data-vdrop-id="' + esc(it.id) + '">' +
          '<span class="vdrop-item-label">' + esc(it.label) + '</span>' +
          (it.downloadable
            ? '<span class="vdrop-dl' + (busy ? ' busy' : '') + '" data-vdrop-dl="' + esc(dlId) + '">' +
            (busy ? '…' : '⬇') + '</span>'
            : (it.installedOffline ? '<span class="vdrop-ok">✓</span>' : '')) +
          '</button>';
      }).join('') +
      '</div>';
    var btn = container.querySelector('.vdrop-btn');
    var list = container.querySelector('.vdrop-list');
    btn.addEventListener('click', function (e) {
      e.stopPropagation();
      closeAllVDrops(container);
      var willOpen = list.classList.contains('hidden');
      list.classList.toggle('hidden', !willOpen);
      container.classList.toggle('open', willOpen);
      btn.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
    });
    list.addEventListener('click', function (e) {
      /* ⬇ chip: start a download, keep the list open so % is visible */
      var dl = e.target.closest('[data-vdrop-dl]');
      if (dl) {
        e.stopPropagation();
        var entry = speechEntry(dl.getAttribute('data-vdrop-dl'));
        if (entry) speechDownload(entry, sourceChannel);
        return;
      }
      var item = e.target.closest('.vdrop-item');
      if (!item) return;
      e.stopPropagation();
      var id = item.getAttribute('data-vdrop-id');
      var picked = null;
      items.forEach(function (it) { if (it.id === id) picked = it; });
      list.classList.add('hidden');
      container.classList.remove('open');
      btn.setAttribute('aria-expanded', 'false');
      if (!picked) return;
      container.querySelector('.vdrop-val').textContent = picked.label;
      list.querySelectorAll('.vdrop-item').forEach(function (b) {
        b.classList.toggle('selected', b === item);
      });
      if (onPick) onPick(picked);
    });
  }
  /** Which log channel(s) an install belongs to. A shared runtime
    (one engine serving TTS AND STT) is logged to BOTH panels, so
    Voice Preview never looks empty while Mic Check tells the story. */
  function speechChannelsForEntry(entry) {
    if (entry && entry.kind === 'stt-model') return ['stt'];
    if (entry && entry.kind === 'tts-model') return ['tts'];
    var kinds = speechRuntimeKinds();
    var chs = [];
    if (entry && kinds.tts[entry.id]) chs.push('tts');
    if (entry && kinds.stt[entry.id]) chs.push('stt');
    if (!chs.length) chs.push('tts');
    return chs;
  }
  /* Back-compat alias (some call sites expect a single value). */
  function speechChannelForEntry(entry) { return speechChannelsForEntry(entry)[0]; }
  function speechDownload(entry, sourceChannel) {
    if (speechState.downloading[entry.id]) return;
    var chs = speechChannelsForEntry(entry);
    var ch = sourceChannel || chs[0];
    U.modal.confirm({
      title: 'Download ' + (entry.name || entry.id) + '?',
      message: '≈ ' + storageFmtBytes(entry.size || entry.bytesOnDisk || 0) +
        ' will be downloaded and stored on this device only. Required runtimes install automatically.',
      confirmText: 'Download'
    }).then(function (ok) {
      if (!ok) {
        voiceLog(ch, 'info', 'Download cancelled: ' + (entry.name || entry.id));
        return;
      }
      voiceLog(ch, 'info', 'Install started: ' + (entry.name || entry.id) + ' (id=' + entry.id + ')');
      speechState.downloading[entry.id] = { received: 0, total: entry.size || 0 };
      speechPaintDl(entry.id);
      speechInstallStream(entry.id, ch, function () {
        delete speechState.downloading[entry.id];
        voiceLog(ch, 'ok', 'Installed: ' + (entry.name || entry.id));
        if (U && U.toast) U.toast('✅ Installed: ' + entry.name, 'success');
        speechRefreshOpenDropdowns();
      }, function (err) {
        delete speechState.downloading[entry.id];
        voiceLog(ch, 'err', 'Install failed: ' + (err || 'unknown error'));
        if (U && U.toast) U.toast('⚠ ' + (err || 'Download failed'), 'error', 4500);
        speechPaintDl(entry.id);
      });
    });
  }
  /** Live-update the ⬇ chip into a % readout without re-rendering the list. */
  function speechPaintDl(id) {
    var el = document.querySelector('[data-vdrop-dl="' + id + '"]');
    if (!el) return;
    var st = speechState.downloading[id];
    if (!st) { el.textContent = '⬇'; el.classList.remove('busy'); return; }
    el.textContent = (st.total > 0) ? Math.round(st.received / st.total * 100) + '%' : '…';
    el.classList.add('busy');
  }
  function speechInstallStream(id, channel, onDone, onErr) {
    function vlog(level, msg) {
      voiceLog(channel, level, msg);
    }
    fetch((window.IDE_STREAM_BASE || 'index.php') + '?api=speech-install', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'text/event-stream' },
      body: JSON.stringify({ id: id })
    }).then(function (res) {
      var reader = res.body.getReader();
      var buf = '';
      var failed = null;
      function pump() {
        reader.read().then(function (chunk) {
          if (chunk.done) { if (failed) onErr(failed); else onDone(); return; }
          buf += new TextDecoder().decode(chunk.value);
          var idx;
          while ((idx = buf.indexOf('\n\n')) !== -1) {
            var block = buf.slice(0, idx); buf = buf.slice(idx + 2);
            var event = 'message', data = null;
            block.split('\n').forEach(function (line) {
              if (line.indexOf('event: ') === 0) event = line.slice(7).trim();
              else if (line.indexOf('data: ') === 0) { try { data = JSON.parse(line.slice(6)); } catch (e) { } }
            });
            if (!data) continue;
            if (event === 'progress' && speechState.downloading[id]) {
              speechState.downloading[id].received = data.received || speechState.downloading[id].received;
              if (data.total) speechState.downloading[id].total = data.total;
              speechPaintDl(id);
            } else if (event === 'status') {
              vlog('info', data.note || '');
            } else if (event === 'warn') {
              vlog('warn', data.note || '');
            } else if (event === 'out') {
              vlog('info', data.line || '');
            } else if (event === 'error') {
              failed = data.error || 'Install failed';
              vlog('err', 'Server error: ' + failed);
            }
          }
          pump();
        }).catch(function (e) {
          vlog('err', 'Stream read failed: ' + ((e && e.message) ? e.message : e));
          onErr(e && e.message);
        });
      }
      pump();
    }).catch(function (e) {
      vlog('err', 'speech-install request failed: ' + ((e && e.message) ? e.message : e));
      onErr(e && e.message);
    });
  }
  /** Re-render whichever tester overlay is currently open. */
  function speechRefreshOpenDropdowns() {
    var vp = document.getElementById('voice-preview-overlay');
    if (vp && !vp.classList.contains('hidden')) voicePreviewLoadVoices();
    var mc = document.getElementById('mic-check-overlay');
    if (mc && !mc.classList.contains('hidden')) micLoadEngines();
  }
  /* ── VOICE PREVIEW: provider + voice/model dropdowns ─────────── */
  function voicePreviewLoadVoices() {
    speechLoadCatalog(function () {
      var jcat = speechJavaCatalog();
      var kinds = speechRuntimeKinds();
      /* 1 · TTS Provider list: system + every TTS runtime in the catalog */
      var providers = [{ id: 'system', label: window.QuirkyTts ? 'Google (Default)' : 'System (Default)' }];
      Object.keys(kinds.tts).forEach(function (rt) {
        var re = speechEntry(rt);
        var inst = speechRuntimeInstalled(jcat, rt);
        providers.push({
          id: rt,
          label: (re && re.name) ? re.name : prettyRuntime(rt),
          entryId: rt,
          downloadable: !inst,
          installedOffline: inst
        });
      });
      var active = (jcat && jcat.active && jcat.active.tts) ? jcat.active.tts : 'system';
      var curProvider = active === 'system' ? 'system' : String(active).split('|')[0];
      if (!providers.some(function (p) { return p.id === curProvider; })) {
        curProvider = voiceState.provider || 'system';
      }
      if (!providers.some(function (p) { return p.id === curProvider; })) curProvider = 'system';
      voiceState.provider = curProvider;
      vdropRender(document.getElementById('voice-preview-provider'), providers, curProvider,
        function (p) {
          if (p.id !== 'system' && !speechRuntimeInstalled(speechJavaCatalog(), p.id)) {
            voiceLog('tts', 'warn', 'Provider not installed yet: ' + p.label);
            if (U && U.toast) U.toast('Tap ⬇ next to the engine to download it first', 'warning');
            return;
          }
          voiceLog('tts', 'info', 'TTS provider selected: ' + p.label);
          voiceState.provider = p.id;
          if (window.QuirkySpeech) {
            if (p.id === 'system') window.QuirkySpeech.setActiveTts('system');
            else activateFirstModelFor(p.id);
          }
          if (U && U.toast) U.toast('🔊 ' + p.label);
          renderVoiceModelDrop();
        }, 'tts');
      /* 2 · Voice / model list for the selected provider */
      renderVoiceModelDrop();
      /* 3 · System voices arrive asynchronously on Android — poll once. */
      if (window.QuirkyTts && !voiceState.ttsInfo) {
        var tries = 0;
        var poll = setInterval(function () {
          tries++;
          var info = null;
          try { info = JSON.parse(window.QuirkyTts.getInfo()); } catch (e) { }
          if (info && info.ready) {
            voiceState.ttsInfo = info;
            clearInterval(poll);
            if (voiceState.provider === 'system') renderVoiceModelDrop();
          } else if (tries > 15) {
            clearInterval(poll);
          }
        }, 400);
      }
      if (!window.QuirkyTts && 'speechSynthesis' in window) {
        try {
          window.speechSynthesis.onvoiceschanged = function () {
            if (voiceState.provider === 'system') renderVoiceModelDrop();
          };
        } catch (e) { }
      }
    });
  }
  /** When switching to an installed offline provider, activate a model. */
  function activateFirstModelFor(rt) {
    var jcat = speechJavaCatalog();
    var models = (jcat && Array.isArray(jcat.tts))
      ? jcat.tts.filter(function (m) { return String(m.id).indexOf(rt + '|') === 0; })
      : [];
    var active = (jcat && jcat.active && jcat.active.tts) ? jcat.active.tts : '';
    var target = null;
    models.forEach(function (m) { if (m.id === active) target = active; });
    if (!target && models.length) target = models[0].id;
    if (target) window.QuirkySpeech.setActiveTts(target);
  }
  /** Second Voice Preview dropdown (dynamic device voices OR offline models). */
  function renderVoiceModelDrop() {
    var countEl = document.getElementById('voice-preview-voice-count');
    var jcat = speechJavaCatalog();
    var items = [];
    var current = '';
    if (voiceState.provider === 'system') {
      items.push({ id: '', label: 'System (Default)' });
      if (window.QuirkyTts) {
        var info = voiceState.ttsInfo;
        var seen = {};
        if (info && Array.isArray(info.voices)) {
          info.voices.forEach(function (v) {
            /* Unique labels: Google ships several variant voices per locale
            (male/female). Append the variant so rows never repeat. */
            var variant = '';
            var hi = String(v.name || '').indexOf('#');
            if (hi > -1) variant = String(v.name).slice(hi + 1).replace(/_+/g, ' ').trim();
            var label = v.label || v.locale;
            if (variant) label += ' · ' + variant;
            if (seen[label]) label += ' (' + v.name + ')';
            seen[label] = true;
            items.push({ id: v.name, label: label });
          });
        }
      } else if ('speechSynthesis' in window) {
        (window.speechSynthesis.getVoices() || []).forEach(function (v, i) {
          items.push({ id: String(i), label: v.name + ' (' + v.lang + ')' });
        });
      }
      current = voicePrefGet('voiceVoice', '');
      if (countEl) {
        countEl.textContent = (items.length - 1) + ' voice' +
          ((items.length - 1) === 1 ? '' : 's') + ' available';
      }
    } else {
      var rt = voiceState.provider;
      speechState.entries.forEach(function (e) {
        if (e.kind !== 'tts-model' || e.runtime !== rt) return;
        var installed = !!speechModelEngineId(jcat, 'tts', rt, e.id);
        items.push({
          id: rt + '|' + e.id,
          label: e.name + (e.lang ? ' · ' + e.lang : ''),
          entryId: e.id,
          downloadable: !installed,
          installedOffline: installed
        });
      });
      var activeId = (jcat && jcat.active && jcat.active.tts) ? jcat.active.tts : '';
      current = activeId;
      if (!items.some(function (m) { return m.id === current; })) current = null;
      if (countEl) {
        var inst = items.filter(function (m) { return m.installedOffline; }).length;
        countEl.textContent = inst + ' installed · ' + (items.length - inst) + ' downloadable';
      }
    }
    vdropRender(document.getElementById('voice-preview-voice'), items, current,
      function (p) {
        if (voiceState.provider === 'system') {
          voicePrefSet('voiceVoice', p.id);
        } else if (p.installedOffline && window.QuirkySpeech) {
          window.QuirkySpeech.setActiveTts(p.id);
          if (U && U.toast) U.toast('🔊 ' + p.label);
        } else {
          if (U && U.toast) U.toast('Tap ⬇ next to the model to download it first', 'warning');
        }
      }, 'tts');
  }
  /* ── MIC CHECK: ASR engine + recognition language dropdowns ──── */
  function micLoadEngines() {
    speechLoadCatalog(function () {
      var jcat = speechJavaCatalog();
      var engines = [{ id: 'system', label: 'Google (Default)' }];
      speechState.entries.forEach(function (e) {
        if (e.kind !== 'stt-model') return;
        var rt = e.runtime || 'whisper-cpp';
        var installed = !!speechModelEngineId(jcat, 'stt', rt, e.id);
        engines.push({
          id: rt + '|' + e.id,
          label: e.name,
          entryId: e.id,
          downloadable: !installed,
          installedOffline: installed
        });
      });
      var active = (jcat && jcat.active && jcat.active.stt) ? jcat.active.stt : 'system';
      if (!engines.some(function (x) { return x.id === active; })) active = 'system';
      vdropRender(document.getElementById('mic-check-engine'), engines, active,
        function (p) {
          if (p.id === 'system') {
            voiceLog('stt', 'info', 'STT engine selected: system (Google)');
            if (window.QuirkySpeech) window.QuirkySpeech.setActiveStt('system');
            return;
          }
          if (!p.installedOffline) {
            voiceLog('stt', 'warn', 'Engine not installed yet: ' + p.label);
            if (U && U.toast) U.toast('Tap ⬇ next to the model to download it first', 'warning');
            return;
          }
          voiceLog('stt', 'info', 'STT engine selected: ' + p.label);
          if (window.QuirkySpeech) window.QuirkySpeech.setActiveStt(p.id);
          if (U && U.toast) U.toast('🎙️ ' + p.label);
        }, 'stt');
      micLoadLangs();
    });
  }
  function micLoadLangs() {
    var items = STT_LANGS.map(function (l) { return { id: l.id, label: l.label }; });
    vdropRender(document.getElementById('mic-check-lang'), items,
      voicePrefGet('voiceSttLang', 'system'), function (p) {
        voicePrefSet('voiceSttLang', p.id);
      }, 'stt');
  }

  function voicePreviewPlay() {
    const textEl = document.getElementById('voice-preview-text');
    const text = textEl ? textEl.value.trim() : '';
    if (!text) {
      voicePreviewSetStatus('Nothing to read — type some text first');
      return;
    }
    const rate = voicePrefGet('voiceRate', 1);
    const pitch = voicePrefGet('voicePitch', 1);
    const voiceChoice = voicePrefGet('voiceVoice', '');
    voiceLog('tts', 'info', 'Read Aloud → provider=' + voiceState.provider +
      ' rate=' + Number(rate).toFixed(1) + ' pitch=' + Number(pitch).toFixed(1) +
      ' chars=' + text.length);
    voicePreviewSetStatus('Speaking…');
    voicePreviewSetBusy(true);
    if (window.QuirkyTts) {
      /* The voice choice only applies to the Google/system provider —
      offline providers (Piper…) speak with the model picked above. */
      if (voiceState.provider === 'system' && voiceChoice) window.QuirkyTts.setVoice(voiceChoice);
      window.QuirkyTts.speak(text, rate, pitch);
      return; // status line updates from the 'quirky-tts-state' events
    }
    if ('speechSynthesis' in window) {
      try { window.speechSynthesis.cancel(); } catch (e) { }
      const u = new SpeechSynthesisUtterance(text);
      u.rate = rate;
      u.pitch = pitch;
      const voices = window.speechSynthesis.getVoices() || [];
      const idx = parseInt(voiceChoice, 10);
      if (!isNaN(idx) && voices[idx]) u.voice = voices[idx];
      u.onend = function () { voicePreviewSetStatus('Done'); voicePreviewSetBusy(false); };
      u.onerror = function () { voicePreviewSetStatus('Speech failed'); voicePreviewSetBusy(false); };
      window.speechSynthesis.speak(u);
      return;
    }
    voiceLog('tts', 'err', 'No speech engine available on this device');
    voicePreviewSetStatus('No speech engine available');
    voicePreviewSetBusy(false);
  }
  function voicePreviewStop() {
    voiceLog('tts', 'info', 'Stop requested');
    if (window.QuirkyTts) {
      try { window.QuirkyTts.stop(); } catch (e) { }
      return; // the 'stopped' state event updates the UI
    }
    if ('speechSynthesis' in window) {
      try { window.speechSynthesis.cancel(); } catch (e) { }
      voicePreviewSetStatus('Stopped');
      voicePreviewSetBusy(false);
    }
  }

  function voicePreviewSetStatus(msg) {
    const el = document.getElementById('voice-preview-status');
    if (el) el.textContent = msg;
  }
  function voicePreviewSetBusy(speaking) {
    const play = document.getElementById('voice-preview-play');
    const stop = document.getElementById('voice-preview-stop');
    if (play) play.disabled = !!speaking;
    if (stop) stop.disabled = !speaking;
  }
  function voicePreviewUpdateSliderLabels() {
    const r = document.getElementById('voice-preview-rate');
    const p = document.getElementById('voice-preview-pitch');
    const rv = document.getElementById('voice-preview-rate-val');
    const pv = document.getElementById('voice-preview-pitch-val');
    if (rv && r) rv.textContent = Number(r.value).toFixed(1) + '×';
    if (pv && p) pv.textContent = Number(p.value).toFixed(1) + '×';
  }
  function voicePreviewUpdateCount() {
    const t = document.getElementById('voice-preview-text');
    const c = document.getElementById('voice-preview-count');
    if (t && c) c.textContent = t.value.length + ' character' + (t.value.length === 1 ? '' : 's');
  }

  /* ═══════════════════════════════════════════════════════════════
  🎙️ MIC CHECK OVERLAY (STT tester)
  ═══════════════════════════════════════════════════════════════ */
  function buildMicCheckOverlay() {
    let ov = document.getElementById('mic-check-overlay');
    if (ov) return ov;
    ov = document.createElement('div');
    ov.id = 'mic-check-overlay';
    ov.className = 'm-voice-overlay hidden';
    ov.innerHTML =
      '<div class="m-voice-header">' +
      '<span class="m-voice-title">🎙️ Mic Check</span>' +
      '<button type="button" class="m-voice-close" id="mic-check-close" aria-label="Close mic check">✕</button>' +
      '</div>' +
      '<div class="m-voice-body">' +

      '<div class="settings-group-box">' +
      '<div class="settings-group-title">Language</div>' +
      '<div class="settings-row settings-row-stacked">' +
      '<div class="settings-info">' +
      '<span class="settings-label">ASR Engine</span>' +
      '<span class="settings-desc">The service that turns your speech into text</span>' +
      '</div>' +
      '<div class="settings-control">' +
      '<div class="vdrop" id="mic-check-engine"></div>' +
      '</div>' +
      '</div>' +
      '<div class="settings-row settings-row-stacked">' +
      '<div class="settings-info">' +
      '<span class="settings-label">Recognition language</span>' +
      '<span class="settings-desc">Google uses locale tags, Whisper uses language tokens</span>' +
      '</div>' +
      '<div class="settings-control">' +
      '<div class="vdrop" id="mic-check-lang"></div>' +
      '</div>' +
      '</div>' +
      '</div>' +

      '<div class="voice-mic-stage">' +
      '<button type="button" class="voice-mic-btn" id="mic-check-btn" aria-label="Start or stop listening">🎙️</button>' +
      '<div class="voice-status" id="mic-check-status">Tap to start · or press &amp; hold to talk</div>' +
      '</div>' +

      '<div class="settings-group-box">' +
      '<div class="settings-group-title">Heard</div>' +
      '<div style="padding:0.7rem 0.8rem">' +
      '<textarea id="mic-check-text" class="voice-textarea" readonly ' +
      'placeholder="Your words will appear here while you speak…"></textarea>' +
      '<div class="voice-ctl-row" style="margin-top:0.6rem">' +
      '<button type="button" class="settings-action-btn" id="mic-check-copy">📋 Copy</button>' +
      '<button type="button" class="settings-action-btn danger" id="mic-check-clear">🗑 Clear</button>' +
      '</div>' +
      '</div>' +
      '</div>' +

      '<div class="settings-note-row">🔒 Audio is handled by your device\'s speech service — nothing is sent to the IDE\'s server.</div>' +
      '<div class="voice-ctl-row" style="margin-top:0.6rem">' +
      '<button type="button" class="settings-action-btn" id="mic-check-logs">📋 Logs</button>' +
      '</div>' +
      '</div>';
    document.body.appendChild(ov);
    return ov;
  }

  function openMicCheckOverlay() {
    if (window.QuirkyStt && !window.QuirkyStt.hasMicPermission()) return; // gated in the card too
    const ov = buildMicCheckOverlay();
    wireMicCheckControls(ov);
    ov.classList.remove('hidden');
    micState = 'idle';
    micBaseText = '';
    const textEl = document.getElementById('mic-check-text');
    if (textEl) textEl.value = '';
    micLoadEngines();
    micSetStatus('Tap to start · or press & hold to talk');
    micSetMicVisual(false);
    registerVoiceOverlayEscape(closeMicCheckOverlay);
    try {
      const S = window.IDE && window.IDE.mobileSheets;
      if (S && S.registerOverlay) S.registerOverlay('mic-check-overlay', closeMicCheckOverlay);
    } catch (e) { }
  }

  function closeMicCheckOverlay() {
    if (micState === 'listening' || micState === 'processing') {
      try {
        if (window.QuirkyStt) window.QuirkyStt.cancelListening();
      } catch (e) { }
    }
    micState = 'idle';
    if (micHoldTimer) { clearTimeout(micHoldTimer); micHoldTimer = null; }
    micHoldMode = false;
    micSetMicVisual(false);
    const ov = document.getElementById('mic-check-overlay');
    if (ov) ov.classList.add('hidden');
    unregisterVoiceOverlayEscape();
    try {
      const S = window.IDE && window.IDE.mobileSheets;
      if (S && S.unregisterOverlay) S.unregisterOverlay('mic-check-overlay');
    } catch (e) { }
  }

  function wireMicCheckControls(ov) {
    if (ov._wired) return;
    ov._wired = true;

    ov.querySelector('#mic-check-close').addEventListener('click', closeMicCheckOverlay);

    /* The mic button works BOTH ways:
       quick tap → toggle listening · press & hold → walkie-talkie  */
    const btn = ov.querySelector('#mic-check-btn');
    btn.addEventListener('pointerdown', function (e) {
      e.preventDefault();
      micHoldMode = false;
      micHoldTimer = setTimeout(function () {
        micHoldMode = true;
        if (micState === 'idle') micStart();
      }, 350);
    });
    btn.addEventListener('pointerup', function () {
      if (micHoldTimer) { clearTimeout(micHoldTimer); micHoldTimer = null; }
      if (micHoldMode) {
        micStop();          // walkie-talkie: finger lifted = stop
      } else if (micState === 'idle') {
        micStart();         // tap: start
      } else {
        micStop();          // tap again: stop
      }
    });
    btn.addEventListener('pointerleave', function () {
      if (micHoldTimer) { clearTimeout(micHoldTimer); micHoldTimer = null; }
      if (micHoldMode) micStop();
    });

    ov.querySelector('#mic-check-copy').addEventListener('click', function () {
      const t = document.getElementById('mic-check-text');
      const text = t ? t.value : '';
      if (!text) { if (U && U.toast) U.toast('Nothing to copy yet'); return; }
      if (U && U.copyToClipboard) {
        U.copyToClipboard(text).then(function (ok) {
          if (U && U.toast) U.toast(ok ? '📋 Copied' : '⚠ Copy failed');
        });
      }
    });

    ov.querySelector('#mic-check-clear').addEventListener('click', function () {
      micBaseText = '';
      const t = document.getElementById('mic-check-text');
      if (t) t.value = '';
      if (U && U.toast) U.toast('Cleared');
    });
    ov.querySelector('#mic-check-logs').addEventListener('click', function () {
      openVoiceLogs('stt');
    });
  }

  function micStart() {
    if (!window.QuirkyStt) {
      voiceLog('stt', 'err', 'Mic start failed: QuirkyStt bridge missing (desktop browser?)');
      return;
    }
    if (!window.QuirkyStt.hasMicPermission()) {
      voiceLog('stt', 'warn', 'Mic start blocked: microphone permission missing');
      micSetStatus('Microphone permission missing — allow it in the Voice card');
      return;
    }
    const lang = voicePrefGet('voiceSttLang', 'system');
    voiceLog('stt', 'info', 'Mic start (lang=' + lang + ')');
    micSetStatus('Listening…');
    micSetMicVisual(true);
    try { window.QuirkyStt.startListening(lang); } catch (e) {
      voiceLog('stt', 'err', 'Mic start threw: ' + ((e && e.message) ? e.message : e));
      micSetStatus('Could not start the speech service');
      micSetMicVisual(false);
    }
  }
  function micStop() {
    if (!window.QuirkyStt) return;
    voiceLog('stt', 'info', 'Mic stop requested — handing audio to the engine');
    micSetStatus('Processing…');
    micSetMicVisual(false);
    try { window.QuirkyStt.stopListening(); } catch (e) { }
  }

  function micSetStatus(msg) {
    const el = document.getElementById('mic-check-status');
    if (el) el.textContent = msg;
  }
  function micSetMicVisual(active) {
    const btn = document.getElementById('mic-check-btn');
    if (btn) btn.classList.toggle('voice-mic-listening', !!active);
  }
  function micRenderHeard(partial) {
    const t = document.getElementById('mic-check-text');
    if (!t) return;
    t.value = micBaseText + (partial ? (micBaseText ? ' ' : '') + partial : '');
    t.scrollTop = t.scrollHeight;
  }
  function micErrorLabel(reason) {
    switch (reason) {
      case 'no-permission': return 'microphone permission missing';
      case 'not-available': return 'no speech service on this device';
      case 'network': case 'network-timeout': return 'network problem — the speech service needs a connection';
      case 'speech-timeout': return 'nothing heard — try speaking closer to the mic';
      case 'no-match': return 'could not understand that';
      case 'busy': return 'speech service busy — try again';
      case 'audio': return 'audio recording problem';
      case 'start-failed': return 'the speech service failed to start';
      default: return reason || 'unknown problem';
    }
  }

  /* ── Shared: Escape closes the TOP voice overlay only ── */
  function registerVoiceOverlayEscape(closeFn) {
    unregisterVoiceOverlayEscape();
    voiceOverlayEscHandler = function (e) {
      if (e.key === 'Escape') {
        e.preventDefault();
        e.stopPropagation();
        closeFn();
      }
    };
    document.addEventListener('keydown', voiceOverlayEscHandler, true);
  }
  function unregisterVoiceOverlayEscape() {
    if (voiceOverlayEscHandler) {
      document.removeEventListener('keydown', voiceOverlayEscHandler, true);
      voiceOverlayEscHandler = null;
    }
  }

  /* ── Native event handlers (registered once at boot, below) ── */
  function handleTtsStateEvent(e) {
    const st = e && e.detail ? e.detail.state : '';
    if (st === 'speaking') {
      voiceLog('tts', 'info', 'Engine state: speaking');
      voicePreviewSetStatus('Speaking…');
      voicePreviewSetBusy(true);
    } else if (st === 'done') {
      voiceLog('tts', 'ok', 'Engine state: done');
      voicePreviewSetStatus('Done');
      voicePreviewSetBusy(false);
    } else if (st === 'stopped') {
      voiceLog('tts', 'info', 'Engine state: stopped');
      voicePreviewSetStatus('Stopped');
      voicePreviewSetBusy(false);
    } else if (st === 'error') {
      const reason = (e.detail && e.detail.reason) || '';
      voiceLog('tts', 'err', 'Engine state: error' + (reason ? ' (' + reason + ')' : ''));
      voicePreviewSetStatus(reason === 'engine-not-ready'
        ? 'Engine unavailable — TTS is still starting or missing'
        : 'Engine unavailable');
      voicePreviewSetBusy(false);
    }
  }
  function handleSttStateEvent(e) {
    const d = e && e.detail ? e.detail : {};
    if (d.state === 'listening') {
      sttHearingLogged = false;
      voiceLog('stt', 'info', 'Engine state: listening');
      micState = 'listening';
      micSetStatus('Listening…');
      micSetMicVisual(true);
    } else if (d.state === 'hearing') {
      if (!sttHearingLogged) {
        sttHearingLogged = true;
        voiceLog('stt', 'info', 'Engine state: hearing you');
      }
      micSetStatus('Hearing you — keep talking');
    } else if (d.state === 'processing') {
      voiceLog('stt', 'info', 'Engine state: processing');
      micState = 'processing';
      micSetStatus('Processing…');
      micSetMicVisual(false);
    } else if (d.state === 'done' || d.state === 'idle') {
      voiceLog('stt', 'info', 'Engine state: ' + d.state);
      micState = 'idle';
      micSetStatus(d.state === 'done'
        ? 'Done — tap to speak again'
        : 'Tap to start · or press & hold to talk');
      micSetMicVisual(false);
    } else if (d.state === 'error') {
      voiceLog('stt', 'err', 'Engine state: error (' + micErrorLabel(d.reason) + ')');
      micState = 'idle';
      micSetMicVisual(false);
      micSetStatus('Problem: ' + micErrorLabel(d.reason));
    }
  }

  /* ═══════════════════════════════════════════════════════════════
  ABOUT PAGE
  ═══════════════════════════════════════════════════════════════ */
  function renderAbout() {
    const engine = getEngineLabel();
    function chip(val, label) {
      return '<span class="about-chip"><b>' + esc(val) + '</b><em>' + esc(label) + '</em></span>';
    }
    function humanBytes(b) {
      b = Number(b) || 0;
      if (b >= 1048576) return Math.round(b / 1048576) + ' MB';
      return Math.round(b / 1024) + ' KB';
    }
    return '<div class="settings-about">' +
      '<div class="settings-about-logo">⚡</div>' +
      '<h3>Quirky IDE <span class="accent">v' + esc(cfg.version || '1.0') + '</span></h3>' +
      '<p class="settings-about-sub">Your private web-dev cockpit.</p>' +
      '<div class="settings-about-chips">' +
      chip(engine, 'Engine') +
      chip(get('autosaveMs') + ' ms', 'Autosave') +
      chip(get('theme'), 'Theme') +
      chip(get('fontFamily') + ' · ' + get('fontSize') + 'px', 'Font') +
      '</div>' +
      '<div class="settings-sc-group"><h4>Mobile Gestures</h4><div class="settings-sc-grid">' +
      '<span class="kbd-combo">Swipe ←→</span><span>Switch between tabs</span>' +
      '<span class="kbd-combo">Long-press file</span><span>Open file actions menu</span>' +
      '<span class="kbd-combo">Swipe file row</span><span>Select file (multi-select)</span>' +
      '<span class="kbd-combo">Pull down tree</span><span>Refresh file list</span>' +
      '<span class="kbd-combo">Pinch preview</span><span>Zoom in/out (tablet/desktop frame)</span>' +
      '</div></div>' +
      '</div>';
  }
  function esc(s) {
    return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  }

  /* ═══════════════════════════════════════════════════════════════
  OPEN / CLOSE
  ═══════════════════════════════════════════════════════════════ */
  function open() {
    buildPanel();
    panelEl.classList.remove('hidden');
    isOpen = true;
    searchQuery = '';
    const search = document.getElementById('settings-search');
    if (search) search.value = '';
    const clearBtn = document.getElementById('settings-search-clear');
    if (clearBtn) clearBtn.classList.add('hidden');
    renderAll('');
    captureSettingsSnapshot();
    updateFeatureSaveUI();
    updateSaveChangesUI();
    const body = document.getElementById('settings-content');
    if (body) body.scrollTop = 0;
  }
  async function close() {
    if (featureIsDirty()) {
      const choice = await askUnsavedFeatures();
      if (choice === 'cancel') return;
      if (choice === 'save') { await saveChanges(); return; }
      featureDraft = null;
      updateFeatureSaveUI();
    }
    /* Explorer drafts are not persisted anywhere — closing would silently
    throw away a typed custom path, so ask first. */
    if (explorerIsDirty()) {
      const ok = await U.modal.confirm({
        title: 'Discard Explorer changes?',
        message: 'Your workspace / export folder selection was not saved.',
        confirmText: 'Discard',
        danger: true
      });
      if (!ok) return;
      deriveWorkspaceChoice();
      deriveExportChoice();
      updateSaveChangesUI();
    }
    if (panelEl) panelEl.classList.add('hidden');
    isOpen = false;
  }
  function toggle() {
    isOpen ? close() : open();
  }

  /* ═══════════════════════════════════════════════════════════════
  SAVE CHANGES (in-app reload) + DYNAMIC ENGINE LABEL
  ═══════════════════════════════════════════════════════════════
  "Save Changes" mirrors "Save Features": it appears when you've
  changed an editor/appearance setting and reloads the IDE in place
  (via the same cancel-able countdown), so you never have to close
  and reopen the app to apply your changes. Settings are already
  persisted to localStorage the moment you change them — this button
  just triggers the reload.                                        */
  /* ── Save Changes — applies editor/appearance settings AND the Explorer
  drafts. Order matters: the export folder is applied FIRST (instant, can
  fail cleanly while nothing has changed yet), then the workspace switch
  (confirmed, needs a reload). Export-only saves skip the reload. ── */
  /* ── Single unified Save. Applies, in order:
     1. Export folder (instant, Android-only)
     2. Feature toggles  → server (features-save), applied on reload
     3. Workspace switch → server (with confirm), applied on reload
   Then ONE reload covers features + workspace + settings. An
   export-only save skips the reload entirely. ── */
  async function saveChanges() {
    var btn = document.getElementById('settings-save-changes');
    if (btn) { btn.disabled = true; btn.textContent = 'Saving…'; }
    var willReload = false;
    try {
      const wsDirty = workspaceIsDirty();
      const expDirty = exportIsDirty();
      const featDirty = featureIsDirty();

      /* 1. Export folder — instant, Android-only. */
      if (expDirty) {
        const bridge = explorerBridge();
        if (!bridge || typeof bridge.setExportDir !== 'function') {
          if (U && U.toast) U.toast('The export folder can only be changed inside the Android app', 'warning');
          return;
        }
        if (explorerState.expChoice === 'custom' && exportTarget() === '') {
          if (U && U.toast) U.toast('⚠ Type a folder path for the custom export folder', 'warning');
          return;
        }
        if (!bridge.setExportDir(exportTarget())) {
          if (U && U.toast) U.toast('⚠ That export folder cannot be used — it must be on shared storage and writable', 'error');
          return;
        }
        explorerState.exportDir = (typeof bridge.getExportDir === 'function')
          ? (bridge.getExportDir() || '') : exportTarget();
      }

      /* 2. Feature toggles → persist to server. */
      if (featDirty) {
        const res = await fetch('index.php?api=features-save', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
          body: JSON.stringify(Object.assign({}, ensureFeatureDraft(), { unlocks: ensureFeatureDraftUnlocks() }))
        });
        const j = await res.json();
        if (!res.ok || (j && j.ok === false)) throw new Error((j && j.error) || 'Save failed');
      }

      /* 3. Workspace switch — confirm, then call the server. */
      if (wsDirty) {
        const target = workspaceTarget();
        if (!target) {
          if (U && U.toast) U.toast('⚠ Pick a workspace location first', 'warning');
          return;
        }
        const ok = await U.modal.confirm({
          title: 'Switch workspace?',
          message: 'The IDE will reload using this folder as the workspace: ' + target,
          confirmText: 'Switch',
          danger: true
        });
        if (!ok) return;
        await window.IDE.api.workspace.switch(target, explorerState.wsCopy);
      }

      /* 4. One reload for features/workspace/settings; export-only skips it. */
      if (wsDirty || settingsIsDirty() || featDirty) {
        willReload = true;
        startReloadCountdown('✅ Changes saved');
      } else if (expDirty) {
        deriveExportChoice();
        explorerRefresh();
        updateSaveChangesUI();
        if (U && U.toast) U.toast('✅ Export folder saved');
      }
    } catch (e) {
      if (U && U.toast) U.toast('⚠ Save failed: ' + ((e && e.message) ? e.message : e), 'error');
    } finally {
      if (!willReload && btn) {
        btn.disabled = false;
        btn.textContent = '💾 Save Changes';
      }
    }
  }
  /* Capture the current editor/appearance values so we can detect changes. */
  function captureSettingsSnapshot() {
    var snap = {};
    SCHEMA.forEach(function (s) {
      if (s.type === 'feature' || s.type === 'featureRange' || s.type === 'readonly') return;
      snap[s.key] = get(s.key);
    });
    settingsSnapshot = snap;
  }
  /* True when any editor/appearance setting differs from the snapshot. */
  function settingsIsDirty() {
    if (!settingsSnapshot) return false;
    return SCHEMA.some(function (s) {
      if (s.type === 'feature' || s.type === 'featureRange' || s.type === 'readonly') return false;
      return String(get(s.key)) !== String(settingsSnapshot[s.key]);
    });
  }
  /* Show/hide the "Save Changes" button. */
  function updateSaveChangesUI() {
    var btn = document.getElementById('settings-save-changes');
    if (btn) btn.classList.toggle('hidden', !settingsIsDirty() && !featureIsDirty() && !explorerIsDirty());
  }
  /* ── Dynamic CodeMirror engine label for the About section ──
    Shows the exact CodeMirror in use: "CodeMirror 6" when the CM6
    engine is active, otherwise the bundled CM5 version.

    ★ FIX: When no editor instance has been created yet (the editor
    is lazy-loaded on first file open), we now check the USER'S
    SAVED PREFERENCE instead of falling through to the CM5 version
    check. Previously, CM5 was always loaded in the bundle, so
    `CodeMirror.version` was always truthy, and the label always
    said "CodeMirror 5.x" regardless of the cm6 setting.        */
  function getEngineLabel() {
    var adapter = window.IDE && window.IDE.editorAdapter;
    var active = null;
    if (adapter && typeof adapter.currentEngine === 'function') {
      active = adapter.currentEngine();
    }

    /* An editor instance exists — report what's actually running */
    if (active === 'cm6') return 'CodeMirror 6';
    if (active === 'cm5') {
      return (typeof CodeMirror !== 'undefined' && CodeMirror.version)
        ? 'CodeMirror ' + CodeMirror.version
        : 'CodeMirror 5';
    }

    /* No editor created yet — report the user's configured preference.
    This is the key fix: previously this case fell straight through
    to the CM5 version check below, which always succeeded because
    CM5 is bundled. */
    var pref = get('editorEngine');
    if (pref === 'cm6') {
      var cfg6 = window.IDE_CONFIG || {};
      return cfg6.cm6Local ? 'CodeMirror 6 (offline)' : 'CodeMirror 6';
    }
    if (pref === 'cm5') {
      return (typeof CodeMirror !== 'undefined' && CodeMirror.version)
        ? 'CodeMirror ' + CodeMirror.version
        : 'CodeMirror 5';
    }

    /* 'auto' mode with no editor created yet — ★ v18: "auto" now means
"CM6 first, CM5 only if CM6 fails to boot", so report CM6 (with the
offline note when the vendored build is present). The old code probed
a sessionStorage key that editor-adapter.js no longer writes (the
latch that pinned reloads to CM5 was removed). Once the editor has
actually booted, the branches above report the REAL running engine. */
    var cfgA = window.IDE_CONFIG || {};
    if (cfgA.cm6Local) return 'CodeMirror 6 (offline)';
    return 'CodeMirror 6';
  }

  let reloadInterval = null;
  function startReloadCountdown(title) {
    if (panelEl) panelEl.classList.add('hidden');
    isOpen = false;
    let secs = 3;
    const ov = document.createElement('div');
    ov.id = 'reload-countdown';
    ov.innerHTML = '<div class="rc-box">' +
      '<div class="rc-title">' + esc(title || '✅ Features saved') + '</div>' +
      '<div class="rc-sub">Reloading the IDE to apply them… <b id="rc-secs">' + secs + '</b>s</div>' +
      '<button id="rc-cancel" class="rc-cancel">Cancel reload</button>' +
      '</div>';
    document.body.appendChild(ov);
    const secsEl = document.getElementById('rc-secs');
    document.getElementById('rc-cancel').addEventListener('click', function () {
      clearInterval(reloadInterval);
      ov.remove();
      if (U && U.toast) U.toast('Reload cancelled — features apply on your next manual reload', 'warning');
      open();
    });
    reloadInterval = setInterval(function () {
      secs--;
      if (secs <= 0) {
        clearInterval(reloadInterval);
        location.reload();
      } else if (secsEl) {
        secsEl.textContent = secs;
      }
    }, 1000);
  }
  /* 3-way dialog shown when closing with unsaved feature changes */
  function askUnsavedFeatures() {
    return new Promise(function (resolve) {
      const ov = document.createElement('div');
      ov.className = 'settings-overlay settings-modal';
      ov.innerHTML = '<div class="settings-panel">' +
        '<div class="settings-header"><span class="settings-title">⚠️ Unsaved feature changes</span></div>' +
        '<div class="settings-body settings-modal-body">' +
        '<p class="settings-modal-text">You flipped feature toggles but haven\'t saved them yet. Feature changes only take effect after saving and reloading the IDE.</p>' +
        '</div>' +
        '<div class="settings-footer settings-modal-footer">' +
        '<button class="modal-btn ghost" id="uf-cancel">Keep editing</button>' +
        '<button class="modal-btn ghost" id="uf-discard" style="color:var(--danger)">Discard</button>' +
        '<button class="modal-btn primary" id="uf-save">💾 Save &amp; Reload</button>' +
        '</div>' +
        '</div>';
      document.body.appendChild(ov);
      function done(val) { ov.remove(); resolve(val); }
      document.getElementById('uf-cancel').addEventListener('click', function () { done('cancel'); });
      document.getElementById('uf-discard').addEventListener('click', function () { done('discard'); });
      document.getElementById('uf-save').addEventListener('click', function () { done('save'); });
    });
  }

  /* ═══════════════════════════════════════════════════════════════
BOOT
═══════════════════════════════════════════════════════════════
★ FIX: applyAll() is now called UNCONDITIONALLY at boot.
Previously it only ran when the editor tab was first opened,
which meant theme/font/appearance changes were invisible on
every other tab until you happened to visit the Editor.

applyAll() safely handles a null CodeMirror instance (it just
skips the CM-specific setOption calls), so calling it early is
safe. We also re-apply when the editor becomes ready, so CM
options that need the live instance (tabSize, gutters, etc.)
get their overrides applied at that point.                     */
  applyAll();

  let editorSettingsApplied = false;
  if (U && U.on) {
    U.on('editor:tabs-updated', function () {
      if (!editorSettingsApplied && window.IDE && window.IDE.mobileEditor && window.IDE.mobileEditor.getCM()) {
        editorSettingsApplied = true;
        applyAll();
      }
    });
  }
  setTimeout(function () {
    if (!editorSettingsApplied && window.IDE && window.IDE.mobileEditor && window.IDE.mobileEditor.getCM()) {
      editorSettingsApplied = true;
      applyAll();
    }
  }, 800);

  /* ═══════════════════════════════════════════════════════════════
  ★ v11 VOICE — one-time native event wiring
  ═══════════════════════════════════════════════════════════════ */
  voiceLogsLoad();
  window.addEventListener('quirky-mic-changed', function () {
    if (document.getElementById('voice-card-inner')) renderAll(searchQuery);
  });
  window.addEventListener('quirky-tts-ready', function () {
    voiceLog('tts', 'info', 'System TTS ready event received');
    voiceRefreshEngineRow();
  });
  window.addEventListener('quirky-tts-state', handleTtsStateEvent);
  window.addEventListener('quirky-stt-state', handleSttStateEvent);
  window.addEventListener('quirky-stt-partial', function (e) {
    const text = e && e.detail ? String(e.detail.text || '') : '';
    micRenderHeard(text);
  });
  window.addEventListener('quirky-stt-final', function (e) {
    const text = e && e.detail ? String(e.detail.text || '') : '';
    voiceLog('stt', text ? 'ok' : 'warn', text
      ? 'Heard: ' + (text.length > 140 ? text.slice(0, 140) + '…' : text)
      : 'Recognition finished with empty text');
    if (text) {
      micBaseText = micBaseText
        ? micBaseText.replace(/\s+$/, '') + ' ' + text
        : text;
    }
    micRenderHeard('');
  });

  /* ═══════════════════════════════════════════════════════════════
  EXPOSE
  ═══════════════════════════════════════════════════════════════ */
  window.IDE = window.IDE || {};
  /**
   * @namespace window.IDE.settings
   * Settings panel management and preference storage.
   */
  window.IDE.settings = {
    open: open,
    close: close,
    toggle: toggle,
    get: get,
    set: set,
    reset: reset,
    applyAll: applyAll,
    isOpen: function () { return isOpen; }
  };
})();