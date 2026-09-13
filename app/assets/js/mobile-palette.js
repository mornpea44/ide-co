/**
 * ═══════════════════════════════════════════════════════════════════════════
 *  QUIRKY IDE — MOBILE COMMAND PALETTE (Phase 7 · Task 7.7)
 * ═══════════════════════════════════════════════════════════════════════════
 *
 *  The ⌘ full-screen palette for the mobile shell. Three modes:
 *    • Files (default)  — fuzzy-search every workspace file; matched
 *                         characters highlighted in amber; directory
 *                         shown right-aligned; recent files float up.
 *    • Commands         — ">" prefix or the ⚡ Commands tab: every mobile
 *                         IDE action (Save, Save All, Format, Shrink, Wrap,
 *                         Find, tab switching, New File, theme toggle,
 *                         Settings, AI, Git, HTTP, Switch to Desktop).
 *    • Go to Line       — ":" prefix: type a line number, Enter jumps the
 *                         editor cursor.
 *
 *  Behaviour (mirrors desktop palette.js, touch-adapted):
 *    • Fuzzy matcher rewards word boundaries, camelCase humps,
 *      consecutive runs and early matches.
 *    • File index is built from the tree API, cached, and invalidated
 *      on any tree event.
 *    • Enter executes the TOP result; tapping any row executes it.
 *    • Escape / ✕ closes the overlay.
 *
 *  EXPOSES: window.IDE.mobilePalette
 * ═══════════════════════════════════════════════════════════════════════════
 */
(function () {
  'use strict';
  var U = window.IDE && window.IDE.utils;
  var api = window.IDE && window.IDE.api;
  if (!U || !api) return;

  /* ═══════════════════════════════════════════════════════════════
  DOM REFERENCES
  ═══════════════════════════════════════════════════════════════ */
  var overlay = document.getElementById('m-cmd-overlay');
  if (!overlay) return;
  var closeBtn = document.getElementById('m-cmd-close');
  var input = document.getElementById('m-cmd-input');
  var list = document.getElementById('m-cmd-list');
  var modeBtns = overlay.querySelectorAll('.m-cmd-mode');

  /* ═══════════════════════════════════════════════════════════════
  STATE
  ═══════════════════════════════════════════════════════════════ */
  var isOpen = false;
  var wired = false;
  var tabMode = 'files';      // what the mode tabs say ('files' | 'commands')
  var mode = 'files';         // effective mode ('files' | 'commands' | 'line')
  var query = '';
  var rows = [];              // actionable rows currently rendered
  var filesCache = null;      // [{path, name}] flat file index
  var filesBusy = false;
  var recency = [];           // most-recently-opened paths (newest first)

  /* ═══════════════════════════════════════════════════════════════
  HELPERS
  ═══════════════════════════════════════════════════════════════ */
  function toast(msg) {
    if (U && U.toast) U.toast(msg);
  }
  function esc(s) {
    if (U && U.escapeHtml) return U.escapeHtml(s);
    if (typeof s !== 'string') return '';
    return s.replace(/&/g, '&amp;').replace(/</g, '&lt;')
      .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }
  function getFileIcon(name) {
    if (U && U.getFileIcon) return U.getFileIcon(name);
    return '📄';
  }

  /* ═══════════════════════════════════════════════════════════════
  FUZZY MATCHER — adapted from desktop palette.js.
  Subsequence match with positional scoring.
  Returns { score, idx[] } or null when not all chars are found.
  ═══════════════════════════════════════════════════════════════ */
  function fuzzy(q, text) {
    q = q.toLowerCase();
    if (!q) return { score: 0, idx: [] };
    var tl = text.toLowerCase();
    var idx = [];
    var ti = 0, score = 0, streak = 0, last = -1;
    for (var qi = 0; qi < q.length; qi++) {
      var found = tl.indexOf(q[qi], ti);
      if (found < 0) return null;
      ti = found + 1;
      idx.push(found);
      var pts = 1;
      if (found === 0) {
        pts += 9;                                              // first char
      } else {
        var pv = text[found - 1], ch = text[found];
        if ('/._- '.indexOf(pv) >= 0) pts += 7;                // word boundary
        else if (pv >= 'a' && pv <= 'z' && ch >= 'A' && ch <= 'Z') pts += 5;  // camelCase
      }
      if (found === last + 1) { streak++; pts += 2 + Math.min(streak, 5); } // run bonus
      else { streak = 0; pts -= Math.min(found - last - 1, 8) * 0.4; }     // gap penalty
      score += pts;
      last = found;
    }
    return { score: score, idx: idx };
  }
  /** Highlight matched characters in amber (.m-cmd-hl). */
  function hl(text, idx) {
    if (!idx || !idx.length) return esc(text);
    var set = {};
    idx.forEach(function (i) { set[i] = true; });
    var out = '', span = false;
    for (var i = 0; i < text.length; i++) {
      if (set[i]) { if (!span) { out += '<b class="m-cmd-hl">'; span = true; } out += esc(text[i]); }
      else { if (span) { out += '</b>'; span = false; } out += esc(text[i]); }
    }
    return out + (span ? '</b>' : '');
  }

  /* ═══════════════════════════════════════════════════════════════
  FILE INDEX — flat list from the tree, cached until any tree event
  invalidates it. ★ PALETTE SYNERGY: the index is seeded directly from
  the Explorer's live model (IDE.mobileFileTree.getSnapshotTree()) —
  no extra ?api=tree round-trip — and rebuilt from its fresh snapshot
  whenever the Explorer finishes reloading ('filetree:updated'). A
  full fetch only happens as a fallback (Explorer absent / lazy mode,
  where the snapshot only holds top-level shells).
  Recency boosts recent files, reading the SAME localStorage store
  the Explorer maintains.
  ═══════════════════════════════════════════════════════════════ */

  /* Same store + cap the Explorer uses for Recent Files */
  var LS_RECENT_FILES = 'quirky.ide.mobile.recent.files';

  function readStoredRecents() {
    try {
      var arr = JSON.parse(localStorage.getItem(LS_RECENT_FILES) || '[]');
      if (!Array.isArray(arr)) return [];
      return arr.filter(function (p) { return typeof p === 'string' && p; })
        .slice(0, 12);
    } catch (e) { return []; }
  }

  /** Sync palette recency with the Explorer's recent-files store. */
  function seedRecency() {
    recency = readStoredRecents();
  }

  /** Flatten the Explorer's live model, skipping unloaded subtrees. */
  function indexFromSnapshot(tree) {
    var out = [];
    (function walk(nodes) {
      (nodes || []).forEach(function (n) {
        if (n.type === 'folder') {
          if (n.unloaded) return;   /* children not fetched yet */
          walk(n.children || []);
        } else {
          out.push({ path: n.path, name: n.name, binary: n.binary });
        }
      });
    })(tree);
    return out;
  }

  function ensureFiles() {
    if (filesCache || filesBusy) return;
    /* ★ Prefer the Explorer's live model — zero extra network. */
    var ft = window.IDE && window.IDE.mobileFileTree;
    if (ft && ft.getSnapshotTree && ft.isLoaded && ft.isLoaded() &&
        !(ft.isLazyMode && ft.isLazyMode())) {
      filesCache = indexFromSnapshot(ft.getSnapshotTree());
      if (isOpen && mode === 'files') render();
      return;
    }
    /* Fallback: build the index ourselves (legacy path). */
    filesBusy = true;
    api.files.getTree()
      .then(function (data) {
        var tree = (data && data.tree) || data || [];
        var out = [];
        (function walk(nodes) {
          (nodes || []).forEach(function (n) {
            if (n.type === 'folder') walk(n.children || []);
            else out.push({ path: n.path, name: n.name, binary: n.binary });
          });
        })(tree);
        filesCache = out;
        filesBusy = false;
        if (isOpen && mode === 'files') render();
      })
      .catch(function () {
        filesCache = [];
        filesBusy = false;
        if (isOpen && mode === 'files') render();
      });
  }
  if (U.on) {
    ['filetree:external-change', 'file:renamed', 'file:deleted']
      .forEach(function (ev) {
        U.on(ev, function () { filesCache = null; });
      });
    /* ★ Emitted by the Explorer AFTER its model reload completes —
       rebuild from the fresh snapshot instead of refetching. */
    U.on('filetree:updated', function () {
      filesCache = null;
      seedRecency();
      ensureFiles();
      if (isOpen && mode === 'files') render();
    });
    U.on('file:open', function (e) {
      var p = e.detail;
      if (!p || typeof p !== 'string') return;
      recency = recency.filter(function (x) { return x !== p; });
      recency.unshift(p);
      if (recency.length > 12) recency.pop();
    });
  }

  /* ═══════════════════════════════════════════════════════════════
  COMMAND REGISTRY — built fresh on every render so feature-gated
  entries appear and disappear with config.php.
  ═══════════════════════════════════════════════════════════════ */
  function switchTabSafe(name) {
    if (typeof window._mobileSwitchTab === 'function') window._mobileSwitchTab(name);
  }
  function toggleTheme() {
    var cur = document.documentElement.getAttribute('data-theme') === 'light' ? 'light' : 'dark';
    var next = (cur === 'dark') ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', next);
    try { localStorage.setItem('quirky.ide.theme', next); } catch (e) { }
    toast(next === 'dark' ? '🌙 Dark theme' : '☀️ Light theme');
  }
  function commands() {
    var L = [];
    function add(cat, ico, label, kw, run) {
      L.push({ cat: cat, ico: ico, label: label, kw: kw || '', run: run });
    }
    var E = window.IDE;
    var cfg = window.IDE_CONFIG || {};
    var feat = cfg.features || {};
    /* ── File ─ */
    add('File', '💾', 'Save', 'write commit', function () { if (E.mobileEditor) E.mobileEditor.save(); });
    add('File', '💾', 'Save All Open Files', 'write', function () { if (E.mobileEditor) E.mobileEditor.saveAll(); });
    if (feat.format) add('File', '✨', 'Format Code', 'beautify prettify', function () { if (E.mobileEditor) E.mobileEditor.format(); });
    if (feat.minify) add('File', '🗜️', 'Shrink / Minify Code', 'compress', function () { if (E.mobileEditor) E.mobileEditor.shrink(); });
    add('File', '📄', 'New File / Folder / Project', 'create', function () {
      var fab = document.getElementById('m-fab');
      if (fab) fab.click();
    });
    /* ── View ── */
    add('View', '🗂️', 'Go to Files', 'tab explorer', function () { switchTabSafe('files'); });
    add('View', '✏️', 'Go to Editor', 'tab code', function () { switchTabSafe('editor'); });
    if (feat.preview) add('View', '👁️', 'Go to Preview', 'tab render', function () { switchTabSafe('preview'); });
    if (feat.terminal) add('View', '🖥️', 'Go to Terminal', 'tab console', function () { switchTabSafe('terminal'); });
    add('View', '↩️', 'Toggle Word Wrap', 'line', function () { if (E.mobileEditor) E.mobileEditor.toggleWrap(); });
    add('View', '🔍', 'Find in File', 'search', function () { if (E.mobileEditor) E.mobileEditor.toggleFind(); });
    add('View', '🌙', 'Theme: Toggle Dark / Light', 'color scheme', toggleTheme);
    /* ── App ── */
    add('App', '⚙️', 'Open Settings', 'preferences config', function () { if (E.settings && E.settings.open) E.settings.open(); });
    if (document.getElementById('m-ai-overlay')) {
      add('App', '🤖', 'AI Assistant', 'chat ollama', function () {
        var row = document.querySelector('#ms-more [data-action="ai"]');
        if (row) row.click();
      });
    }
    if (feat.git) add('App', '🌿', 'Source Control (Git)', 'status history', function () { if (E.mobileGit) E.mobileGit.open(); });
    if (feat.httpClient) add('App', '📡', 'HTTP Client', 'api request postman', function () { if (E.mobileHttpClient) E.mobileHttpClient.open(); });
    return L;
  }

  /* ═══════════════════════════════════════════════════════════════
  RENDER
  ═══════════════════════════════════════════════════════════════ */
  function render() {
    if (!list) return;
    if (mode === 'line') { renderLine(); return; }
    if (mode === 'commands') { renderCommands(); return; }
    renderFiles();
  }
  function renderFiles() {
    var q = query.trim();
    if (!filesCache) {
      rows = [];
      list.innerHTML = '<div class="m-cmd-empty">' + (filesBusy ? 'Indexing workspace…' : 'Loading…') + '</div>';
      return;
    }
    var scored = [];
    filesCache.forEach(function (f) {
      var s, idx = [];
      if (q) {
        var r = fuzzy(q, f.path);
        if (!r) return;
        s = r.score;
        idx = r.idx;
      } else {
        s = 0;
      }
      var ri = recency.indexOf(f.path);
      if (ri >= 0) s += 14 - ri;                    // recent files float up
      s -= f.path.split('/').length * 0.6;          // shallow paths beat deep ones
      scored.push({ f: f, s: s, idx: idx });
    });
    scored.sort(function (a, b) {
      return b.s - a.s || a.f.path.length - b.f.path.length || a.f.path.localeCompare(b.f.path);
    });
    rows = scored.slice(0, 30).map(function (r) { return { kind: 'file', file: r.f, idx: r.idx }; });
    if (!rows.length) {
      list.innerHTML = '<div class="m-cmd-empty">' +
        (q ? 'No files match “' + esc(q) + '”' : 'Workspace is empty') + '</div>';
      return;
    }
    var html = '';
    rows.forEach(function (r, i) {
      var f = r.file;
      var off = f.path.length - f.name.length;
      var nameIdx = r.idx.filter(function (x) { return x >= off; }).map(function (x) { return x - off; });
      var dir = off > 1 ? f.path.slice(0, off - 1) : '';
      html += '<div class="m-cmd-row" data-idx="' + i + '">' +
        '<span class="m-cmd-row-icon">' + getFileIcon(f.name, f.binary) + '</span>' +
        '<span class="m-cmd-row-label">' + hl(f.name, nameIdx) + '</span>' +
        (dir ? '<span class="m-cmd-row-dir">' + esc(dir) + '</span>' : '') +
        '</div>';
    });
    list.innerHTML = html;
  }
  function renderCommands() {
    var q = query.trim();
    var all = commands();
    var out;
    if (!q) {
      out = all.map(function (c) { return { kind: 'cmd', cmd: c, idx: [] }; });
    } else {
      var scored = [];
      all.forEach(function (c) {
        var r = fuzzy(q, c.label + ' ' + c.kw);
        if (!r) return;
        scored.push({
          kind: 'cmd', cmd: c, score: r.score,
          idx: r.idx.filter(function (i) { return i < c.label.length; })
        });
      });
      scored.sort(function (a, b) { return b.score - a.score; });
      out = scored.slice(0, 30);
    }
    rows = out;
    if (!rows.length) {
      list.innerHTML = '<div class="m-cmd-empty">No commands match “' + esc(q) + '”</div>';
      return;
    }
    var html = '';
    rows.forEach(function (r, i) {
      html += '<div class="m-cmd-row" data-idx="' + i + '">' +
        '<span class="m-cmd-row-icon">' + r.cmd.ico + '</span>' +
        '<span class="m-cmd-row-label">' + hl(r.cmd.label, r.idx) + '</span>' +
        '<span class="m-cmd-row-dir">' + esc(r.cmd.cat) + '</span>' +
        '</div>';
    });
    list.innerHTML = html;
  }
  function renderLine() {
    var n = parseInt(query, 10);
    var cm = window.IDE.mobileEditor ? window.IDE.mobileEditor.getCM() : null;
    var max = (cm && cm.lineCount) ? cm.lineCount() : 0;
    rows = (!isNaN(n) && n >= 1 && (!max || n <= max)) ? [{ kind: 'line', n: n }] : [];
    list.innerHTML = rows.length
      ? '<div class="m-cmd-row" data-idx="0"><span class="m-cmd-row-icon">↕️</span>' +
      '<span class="m-cmd-row-label">Go to line <b class="m-cmd-hl">' + n + '</b> in the active file</span></div>'
      : '<div class="m-cmd-empty">' + (max ? 'Enter a line number between 1 and ' + max : 'No file open') + '</div>';
  }

  /* ═══════════════════════════════════════════════════════════════
  EXECUTE
  ═══════════════════════════════════════════════════════════════ */
  function execute(i) {
    var row = rows[i];
    if (!row) return;
    close();
    if (row.kind === 'cmd') {
      try { row.cmd.run(); } catch (e) { }
    } else if (row.kind === 'file') {
      U.emit('file:open', row.file.path);
      switchTabSafe('editor');
    } else if (row.kind === 'line') {
      switchTabSafe('editor');
      var cm = window.IDE.mobileEditor ? window.IDE.mobileEditor.getCM() : null;
      if (cm) {
        cm.setCursor({ line: row.n - 1, ch: 0 });
        if (cm.scrollIntoView) cm.scrollIntoView({ line: row.n - 1, ch: 0 }, 80);
        if (cm.focus) cm.focus();
      }
    }
  }

  /* ═══════════════════════════════════════════════════════════════
  OPEN / CLOSE / MODE UI
  ═══════════════════════════════════════════════════════════════ */
  function updateModeUI() {
    var show = (mode === 'line') ? tabMode : mode;
    modeBtns.forEach(function (b) {
      b.classList.toggle('active', b.getAttribute('data-mode') === show);
    });
  }
  function open() {
    if (isOpen) return;
    var cfg = window.IDE_CONFIG || {};
    if (!(cfg.features && cfg.features.commandPalette)) return;
    isOpen = true;
    mode = tabMode;
    query = '';
    if (input) input.value = '';
    overlay.classList.remove('hidden');
    void overlay.offsetWidth;
    overlay.classList.add('show');
    updateModeUI();
    ensureFiles();
    render();
    setTimeout(function () { if (input) input.focus(); }, 250);
  }
  function close() {
    if (!isOpen) return;
    isOpen = false;
    overlay.classList.remove('show');
    setTimeout(function () { overlay.classList.add('hidden'); }, 300);
  }
  function toggle() { isOpen ? close() : open(); }
  /**
   * ★ Open the palette pre-switched to FILES mode — used by the
   * Explorer's "Find file" chip in the tree-tools bar.
   */
  function openFiles() {
    tabMode = 'files';
    if (isOpen) {
      /* Already open: just switch modes and reset the query */
      mode = tabMode;
      query = '';
      if (input) input.value = '';
      updateModeUI();
      ensureFiles();
      render();
      if (input) input.focus();
      return;
    }
    open();
  }

  /* ═══════════════════════════════════════════════════════════════
  WIRING (once)
  ═══════════════════════════════════════════════════════════════ */
  function init() {
    if (wired) return;
    wired = true;
    if (closeBtn) closeBtn.addEventListener('click', close);
    modeBtns.forEach(function (btn) {
      btn.addEventListener('click', function () {
        tabMode = (btn.getAttribute('data-mode') === 'commands') ? 'commands' : 'files';
        mode = tabMode;
        query = '';
        if (input) input.value = '';
        updateModeUI();
        render();
        if (input) input.focus();
      });
    });
    if (input) {
      input.addEventListener('input', function () {
        var v = input.value;
        if (v.charAt(0) === '>') { mode = 'commands'; query = v.slice(1); }
        else if (v.charAt(0) === ':') { mode = 'line'; query = v.slice(1); }
        else { mode = tabMode; query = v; }
        render();
      });
      input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
          e.preventDefault();
          if (rows.length) execute(0);      // Enter executes the TOP result
        } else if (e.key === 'Escape') {
          e.preventDefault();
          close();
        }
      });
    }
    if (list) {
      list.addEventListener('click', function (e) {
        var r = e.target.closest('.m-cmd-row');
        if (!r) return;
        execute(+r.getAttribute('data-idx'));
      });
    }
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && isOpen) close();
    });
  }

  /* ═══════════════════════════════════════════════════════════════
  BOOT + BRIDGE
  ═══════════════════════════════════════════════════════════════ */
  function boot() {
    init();
    seedRecency();   /* ★ start from the Explorer's recent-files store */
    /* Bridge used by the ⌘ header button in mobile-shell.php */
    window._mobilePaletteToggle = toggle;
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();

    /* Task 8.7: Drag-to-dismiss on the command palette overlay */
    var Sheets = window.IDE.mobileSheets;
    if (Sheets && Sheets.initOverlayDragToDismiss) {
      Sheets.initOverlayDragToDismiss('m-cmd-overlay', close);
    }
  }

  /* ═══════════════════════════════════════════════════════════════
  EXPOSE
  ═══════════════════════════════════════════════════════════════ */
  window.IDE = window.IDE || {};
  /**
   * @namespace window.IDE.mobilePalette
   * Mobile command palette (fuzzy files, commands, go-to-line).
   */
  window.IDE.mobilePalette = {
    /** Wire events (idempotent). */
    init: init,
    /** Open the palette overlay. */
    open: open,
    /** ★ Open the palette pre-switched to files mode (Explorer bridge). */
    openFiles: openFiles,
    /** Close the palette overlay. */
    close: close,
    /** Toggle open/closed. */
    toggle: toggle,
    /** True while the overlay is visible. */
    isOpen: function () { return isOpen; }
  };
})();