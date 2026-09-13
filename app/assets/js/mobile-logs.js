/**
 * ═══════════════════════════════════════════════════════════════════════════
 *  QUIRKY IDE — IDE CONSOLE LOGS (capture + viewer)      ★ v13 · Logs card #1
 * ═══════════════════════════════════════════════════════════════════════════
 *
 *  Captures what the desktop "DevTools console" would show, and gives the
 *  Settings → Logs card a full-screen viewer for it:
 *
 *    • console.log / info / warn / error / debug   (wrapped, never silenced)
 *    • Uncaught JavaScript runtime errors          (window 'error')
 *    • Resources that fail to load                 (script/link/img errors)
 *    • Unhandled promise rejections                ('unhandledrejection')
 *
 *  Entries live in a 500-slot ring buffer in memory AND are persisted to
 *  localStorage (debounced + whenever the page hides), so they survive an
 *  app restart. A flood guard stops a runaway loop from spamming storage.
 *
 *  EXPOSES: window.IDE.mobileLogs = { openConsole, closeConsole,
 *                                     getEntries, clearEntries }
 * ═══════════════════════════════════════════════════════════════════════════
 */
(function () {
  'use strict';

  var U = window.IDE && window.IDE.utils;

  var LS_KEY = 'quirky.ide.logs.console';
  var MAX_ENTRIES = 500;        // ring buffer size (oldest fall off)
  var MAX_MSG_CHARS = 2000;     // per-entry message cap
  var RENDER_CAP = 300;         // max rows painted at once (keeps DOM light)
  var SAVE_DEBOUNCE = 1500;     // ms before a background save
  var MAX_STORE_CHARS = 180000; // ~180 KB localStorage ceiling

  var entries = [];             // { t: ms, lvl, src, msg }
  var loaded = false;
  var saveTimer = null;
  var renderQueued = false;
  var filterLvl = 'all';        // 'all' | 'log' | 'info' | 'warn' | 'error'
  var stickToBottom = true;     // auto-scroll latch (terminal-style)
  var overlayEl = null;

  var LVL_LABEL = { log: 'LOG', info: 'INFO', warn: 'WARN', error: 'ERROR', debug: 'DBG' };

  /* ═══════════════════════════════════════════════════════════
     SMALL HELPERS
  ═══════════════════════════════════════════════════════════ */
  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  }

  function pad(n, w) { return String(n).padStart(w || 2, '0'); }

  function fmtTime(t) {
    var d = new Date(t);
    return pad(d.getHours()) + ':' + pad(d.getMinutes()) + ':' +
      pad(d.getSeconds()) + '.' + pad(d.getMilliseconds(), 3);
  }

  function fmtTimeFull(t) {
    var d = new Date(t);
    return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) +
      ' ' + fmtTime(t);
  }

  /* ═══════════════════════════════════════════════════════════
     SERIALIZATION — turn anything console received into text
  ═══════════════════════════════════════════════════════════ */
  function serializeArg(a) {
    try {
      if (a === null) return 'null';
      if (a === undefined) return 'undefined';
      var t = typeof a;
      if (t === 'string') return a;
      if (t === 'number' || t === 'boolean' || t === 'bigint') return String(a);
      if (t === 'function') return 'ƒ ' + (a.name || 'anonymous') + '()';
      if (t === 'symbol') return a.toString();
      if (a instanceof Error) return a.stack || (a.name + ': ' + a.message);
      if (typeof Element !== 'undefined' && a instanceof Element) {
        var tag = (a.tagName || 'element').toLowerCase();
        if (a.id) tag += '#' + a.id;
        return '<' + tag + '>';
      }
      var json = JSON.stringify(a, function (k, v) {
        if (typeof v === 'function') return 'ƒ ' + (v.name || 'anonymous') + '()';
        if (typeof v === 'bigint') return String(v) + 'n';
        return v;
      });
      if (json === undefined) return Object.prototype.toString.call(a);
      return json;
    } catch (e) {
      return Object.prototype.toString.call(a);
    }
  }

  function serializeArgs(args) {
    var parts = [];
    for (var i = 0; i < args.length; i++) parts.push(serializeArg(args[i]));
    var out = parts.join(' ');
    if (out.length > MAX_MSG_CHARS) out = out.slice(0, MAX_MSG_CHARS) + ' …[trimmed]';
    return out;
  }

  /* ═══════════════════════════════════════════════════════════
     FLOOD GUARD — a runaway log loop can't hammer storage
  ═══════════════════════════════════════════════════════════ */
  var floodTimes = [];
  function floodAllowed() {
    var now = Date.now();
    floodTimes = floodTimes.filter(function (t) { return now - t < 1000; });
    if (floodTimes.length >= 60) return false; // >60 entries/sec = drop
    floodTimes.push(now);
    return true;
  }

  /* ═══════════════════════════════════════════════════════════
     RING BUFFER + PERSISTENCE
  ═══════════════════════════════════════════════════════════ */
  function push(lvl, src, msg) {
    entries.push({ t: Date.now(), lvl: lvl, src: src, msg: String(msg == null ? '' : msg) });
    if (entries.length > MAX_ENTRIES) entries.splice(0, entries.length - MAX_ENTRIES);
    scheduleSave();
    if (overlayEl && !overlayEl.classList.contains('hidden')) queueRender();
  }

  function load() {
    if (loaded) return;
    loaded = true;
    try {
      var raw = localStorage.getItem(LS_KEY);
      if (raw) {
        var arr = JSON.parse(raw);
        if (Array.isArray(arr)) entries = arr.slice(-MAX_ENTRIES);
      }
    } catch (e) { entries = []; }
  }

  function scheduleSave() {
    if (saveTimer) return;
    saveTimer = setTimeout(function () { saveTimer = null; saveNow(); }, SAVE_DEBOUNCE);
  }

  function saveNow() {
    try {
      var json = JSON.stringify(entries);
      // Size guard: trim the oldest 20% until we fit the ceiling.
      while (json.length > MAX_STORE_CHARS && entries.length > 20) {
        entries = entries.slice(Math.ceil(entries.length * 0.2));
        json = JSON.stringify(entries);
      }
      localStorage.setItem(LS_KEY, json);
    } catch (e) { /* quota/serialization failure — never break the app */ }
  }

  /* ═══════════════════════════════════════════════════════════
     CAPTURE HOOKS
  ═══════════════════════════════════════════════════════════ */
  var LEVELS = ['log', 'info', 'warn', 'error', 'debug'];

  function installConsoleHooks() {
    if (!window.console || console.__quirkyLogsWrapped) return;
    console.__quirkyLogsWrapped = true;
    LEVELS.forEach(function (lvl) {
      var orig = (typeof console[lvl] === 'function') ? console[lvl].bind(console) : null;
      console[lvl] = function () {
        try {
          if (floodAllowed()) push(lvl, 'console', serializeArgs(arguments));
        } catch (e) { /* capturing must never break logging itself */ }
        if (orig) orig.apply(null, arguments); // original behavior untouched
      };
    });
  }

  function installErrorHooks() {
    // capture:true also sees resource-load failures (script/link/img)
    window.addEventListener('error', function (e) {
      try {
        if (e && e.target && e.target.tagName &&
          (e.target.tagName === 'SCRIPT' || e.target.tagName === 'LINK' || e.target.tagName === 'IMG')) {
          var res = e.target.src || e.target.href || '';
          if (res) push('error', 'network', 'Resource failed to load: ' + res);
        } else {
          var msg = '';
          if (e && e.error && e.error.stack) msg = String(e.error.stack);
          else msg = ((e && e.message) ? e.message : 'Unknown error') +
            ((e && e.filename) ? ' @ ' + e.filename + ':' + (e.lineno || 0) : '');
          push('error', 'runtime', msg);
        }
      } catch (err) { }
    }, true);

    window.addEventListener('unhandledrejection', function (e) {
      try {
        var r = e ? e.reason : null;
        var msg = (r && r.stack) ? String(r.stack) : serializeArg(r);
        push('error', 'promise', 'Unhandled promise rejection: ' + msg);
      } catch (err) { }
    });
  }

  /* ═══════════════════════════════════════════════════════════
     VIEWER OVERLAY (built on first open, reused afterwards)
  ═══════════════════════════════════════════════════════════ */
  function buildOverlay() {
    if (overlayEl) return;
    overlayEl = document.createElement('div');
    overlayEl.id = 'm-logs-overlay';
    overlayEl.className = 'm-logs-overlay hidden';
    overlayEl.innerHTML =
      '<div class="m-logs-header">' +
      '<button class="m-logs-close" id="m-logs-close" title="Close" aria-label="Close console logs">✕</button>' +
      '<span class="m-logs-title">📋 IDE CONSOLE LOGS</span>' +
      '<span class="m-logs-count" id="m-logs-count">0 entries</span>' +
      '</div>' +
      '<div class="m-logs-toolbar">' +
      '<div class="m-logs-filters" id="m-logs-filters">' +
      '<button type="button" data-lvl="all" class="active">All</button>' +
      '<button type="button" data-lvl="log">Log</button>' +
      '<button type="button" data-lvl="info">Info</button>' +
      '<button type="button" data-lvl="warn">Warn</button>' +
      '<button type="button" data-lvl="error">Error</button>' +
      '</div>' +
      '<div class="m-logs-actions">' +
      '<button type="button" class="m-logs-btn" id="m-logs-copy">📋 Copy</button>' +
      '<button type="button" class="m-logs-btn danger" id="m-logs-clear">🗑 Clear</button>' +
      '</div>' +
      '</div>' +
      '<div class="m-logs-body" id="m-logs-body"></div>';
    document.body.appendChild(overlayEl);

    document.getElementById('m-logs-close').addEventListener('click', closeConsole);

    // Filter chips
    var filters = document.getElementById('m-logs-filters');
    filters.addEventListener('click', function (e) {
      var btn = e.target.closest('[data-lvl]');
      if (!btn) return;
      filterLvl = btn.getAttribute('data-lvl');
      filters.querySelectorAll('button').forEach(function (b) {
        b.classList.toggle('active', b === btn);
      });
      renderNow();
    });

    // Copy (respects the active filter)
    document.getElementById('m-logs-copy').addEventListener('click', function () {
      var list = filteredEntries();
      if (!list.length) {
        if (U && U.toast) U.toast('Nothing to copy');
        return;
      }
      var text = list.map(function (en) {
        return '[' + fmtTimeFull(en.t) + '] [' + (LVL_LABEL[en.lvl] || en.lvl.toUpperCase()) +
          '] (' + en.src + ') ' + en.msg;
      }).join('\n');
      if (U && U.copyToClipboard) {
        U.copyToClipboard(text).then(function (ok) {
          if (U && U.toast) U.toast(ok ? 'Console logs copied' : 'Copy failed', ok ? 'success' : 'error');
        });
      }
    });

    // Clear (with confirmation — this is real data loss)
    document.getElementById('m-logs-clear').addEventListener('click', function () {
      var doClear = function () {
        entries = [];
        saveNow();
        renderNow();
        if (U && U.toast) U.toast('Console logs cleared');
      };
      if (U && U.modal && U.modal.confirm) {
        U.modal.confirm({
          title: 'Clear console logs?',
          message: 'All ' + entries.length + ' captured entr' +
            (entries.length === 1 ? 'y' : 'ies') + ' will be deleted.',
          confirmText: 'Clear',
          danger: true
        }).then(function (ok) { if (ok) doClear(); });
      } else {
        doClear();
      }
    });

    // Stick-to-bottom latch (same idea as the terminal)
    var body = document.getElementById('m-logs-body');
    body.addEventListener('scroll', function () {
      stickToBottom = (body.scrollHeight - body.scrollTop - body.clientHeight) < 60;
    }, { passive: true });
  }

  function filteredEntries() {
    return entries.filter(function (en) {
      if (filterLvl === 'all') return true;
      if (filterLvl === 'log') return en.lvl === 'log' || en.lvl === 'debug';
      return en.lvl === filterLvl;
    });
  }

  function renderEntry(en) {
    return '<div class="mlog-entry lvl-' + en.lvl + '">' +
      '<div class="mlog-line1">' +
      '<span class="mlog-time">' + fmtTime(en.t) + '</span>' +
      '<span class="mlog-lvl">' + (LVL_LABEL[en.lvl] || en.lvl) + '</span>' +
      '<span class="mlog-src">' + esc(en.src) + '</span>' +
      '</div>' +
      '<div class="mlog-msg">' + esc(en.msg) + '</div>' +
      '</div>';
  }

  function renderNow() {
    if (!overlayEl || overlayEl.classList.contains('hidden')) return;
    var body = document.getElementById('m-logs-body');
    var countEl = document.getElementById('m-logs-count');
    if (!body) return;

    var list = filteredEntries();
    var total = list.length;
    var shown = list.slice(-RENDER_CAP);

    if (!total) {
      body.innerHTML = '<div class="m-logs-empty"><span>📋</span><p>No console output captured' +
        (filterLvl !== 'all' ? ' for this filter' : '') + '.</p></div>';
    } else {
      var html = '';
      if (total > shown.length) {
        html += '<div class="m-logs-capnote">Showing the latest ' + shown.length +
          ' of ' + total + ' entries</div>';
      }
      for (var i = 0; i < shown.length; i++) html += renderEntry(shown[i]);
      body.innerHTML = html;
    }
    if (countEl) countEl.textContent = total + (total === 1 ? ' entry' : ' entries');
    if (stickToBottom) body.scrollTop = body.scrollHeight;
  }

  /* Live tail: new entries repaint at most once per animation frame */
  function queueRender() {
    if (renderQueued) return;
    renderQueued = true;
    requestAnimationFrame(function () {
      renderQueued = false;
      renderNow();
    });
  }

  /* ═══════════════════════════════════════════════════════════
     OPEN / CLOSE
  ═══════════════════════════════════════════════════════════ */
  function openConsole() {
    load();
    buildOverlay();
    stickToBottom = true;
    overlayEl.classList.remove('hidden');
    renderNow();
    // Join the shared overlay stack so Escape / Android BACK pop it first
    if (window.IDE && window.IDE.mobileSheets && window.IDE.mobileSheets.registerOverlay) {
      window.IDE.mobileSheets.registerOverlay('m-logs-overlay', closeConsole);
    }
  }

  function closeConsole() {
    if (!overlayEl) return;
    overlayEl.classList.add('hidden');
    if (window.IDE && window.IDE.mobileSheets && window.IDE.mobileSheets.unregisterOverlay) {
      window.IDE.mobileSheets.unregisterOverlay('m-logs-overlay');
    }
  }

  /* ═══════════════════════════════════════════════════════════
     BOOT + EXPOSE
  ═══════════════════════════════════════════════════════════ */
  load();
  installConsoleHooks();
  installErrorHooks();
  window.addEventListener('pagehide', saveNow);
  document.addEventListener('visibilitychange', function () {
    if (document.visibilityState === 'hidden') saveNow();
  });

  window.IDE = window.IDE || {};
  /**
   * @namespace window.IDE.mobileLogs
   * IDE console log capture + viewer (Settings → Logs).
   */
  window.IDE.mobileLogs = {
    openConsole: openConsole,
    closeConsole: closeConsole,
    getEntries: function () { return entries.slice(); },
    clearEntries: function () { entries = []; saveNow(); if (overlayEl) renderNow(); }
  };
})();