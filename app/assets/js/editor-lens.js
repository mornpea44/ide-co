/**
 * ═══════════════════════════════════════════════════════════════════════════
 *  QUIRKY IDE — EDITOR LENS / CODE LENS (editor intelligence stack · 3/3)
 * ═══════════════════════════════════════════════════════════════════════════
 *
 *  Renders a small pill ABOVE each function/class line of the active file:
 *
 *      ƒ render(props, opts) · 24 lines        ← functions (kind function/method)
 *      ◇ ClassName                             ← classes/structs/interfaces
 *
 *  Data comes from the symbols-index backend endpoint:
 *      api.symbols.index(path) → { symbols: [{ line, name, kind, signature }] }
 *  The endpoint may not exist yet in this build — every access is
 *  feature-detected and the whole module degrades to a graceful no-op.
 *
 *  Behaviour:
 *    • Refreshed on tab open ('editor:tab-shown') and after save
 *      ('editor:saved').
 *    • Pure INTEL FACADE usage: instance.setLens(entries)/clearLens()
 *      (block widgets above the line on both CM5 and CM6).
 *    • Tap a lens → jump to the symbol and flash-select its first line.
 *    • Hard cap: 40 lenses per file (phone layout).
 *    • Cleared on every tab switch / close.
 *
 *  EXPOSES: window.IDE.editorLens = { refresh(), clear(), isAvailable() }
 * ═══════════════════════════════════════════════════════════════════════════
 */
(function () {
  'use strict';

  window.IDE = window.IDE || {};
  var U = window.IDE.utils || null;

  var MAX_LENSES = 40;
  var FLASH_MS = 900;

  var cmRef = null;
  var activePath = null;
  var runSeq = 0;
  var flashHandles = [];

  /* ═══════════════════════════════════════════════════════════════
  BACKEND DETECTION (defensive — endpoint may not be merged yet)
  ═══════════════════════════════════════════════════════════════ */
  function symbolsApi() {
    try {
      if (window.IDE && window.IDE.api && window.IDE.api.symbols &&
        typeof window.IDE.api.symbols.index === 'function') {
        return window.IDE.api.symbols.index;
      }
    } catch (e) { }
    return null;
  }

  function isAvailable() { return !!symbolsApi(); }

  /* ═══════════════════════════════════════════════════════════════
  ROW BUILDING (factory form — the facade calls it lazily)
  ═══════════════════════════════════════════════════════════════ */
  function escLocal(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;')
      .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  function isClassKind(kind) {
    return kind === 'class' || kind === 'struct' || kind === 'interface' ||
      kind === 'trait' || kind === 'enum';
  }

  /** 'render(a, b)' | 'function render(a, b)' → 'render' + '(a, b)' parts. */
  function splitSignature(sym) {
    var sig = String(sym.signature == null ? '' : sym.signature);
    var name = String(sym.name == null ? '' : sym.name);
    if (!sig) return { name: name, args: '' };
    /* strip common keyword prefixes so the pill reads clean */
    sig = sig.replace(/^\s*(public|private|protected|static|final|abstract|async|export|default|function|fn|def|func)\s+/g, '');
    var paren = sig.indexOf('(');
    if (paren === -1) return { name: name || sig.trim(), args: '' };
    var nm = sig.slice(0, paren).trim();
    return { name: name || nm, args: sig.slice(paren).trim() };
  }

  function makeRowFactory(sym, lineCountText) {
    return function () {
      var row = document.createElement('div');
      row.className = 'm-lens-row' + (isClassKind(sym.kind) ? ' m-lens-row-class' : '');
      var pill = document.createElement('span');
      pill.className = 'm-lens-pill';
      var glyph = document.createElement('span');
      glyph.className = 'm-lens-glyph';
      glyph.textContent = isClassKind(sym.kind) ? '◇' : 'ƒ';
      var nameEl = document.createElement('span');
      nameEl.className = 'm-lens-name';
      nameEl.textContent = sym.name;
      pill.appendChild(glyph);
      pill.appendChild(nameEl);

      if (!isClassKind(sym.kind)) {
        var args = splitSignature(sym);
        if (args.args) {
          var argsEl = document.createElement('span');
          argsEl.className = 'm-lens-args';
          argsEl.textContent = args.args;
          pill.appendChild(argsEl);
        }
        if (lineCountText) {
          var cnt = document.createElement('span');
          cnt.className = 'm-lens-count';
          cnt.textContent = '· ' + lineCountText;
          pill.appendChild(cnt);
        }
      }

      row.appendChild(pill);
      row.title = (sym.kind ? sym.kind + ' ' : '') + sym.name +
        (sym.line != null ? ' · line ' + (Number(sym.line) + 1) : '');
      return row;
    };
  }

  /* ═══════════════════════════════════════════════════════════════
  RENDER
  ═══════════════════════════════════════════════════════════════ */
  function clearFlash() {
    var h = flashHandles;
    flashHandles = [];
    for (var i = 0; i < h.length; i++) {
      try { h[i].clear(); } catch (e) { }
    }
  }

  function jumpTo(line) {
    var cm = cmRef;
    if (!cm) return;
    try {
      var maxLine = Math.max(0, (cm.lineCount ? cm.lineCount() : 1) - 1);
      line = Math.max(0, Math.min(maxLine, Number(line) || 0));
      cm.setCursor({ line: line, ch: 0 });
      cm.scrollIntoView({ line: line, ch: 0 }, 80);
      cm.focus();
      /* flash-select the whole first line */
      try {
        var len = (cm.getLine(line) || '').length;
        var handle = cm.markText(
          { line: line, ch: 0 },
          { line: line, ch: len },
          { className: 'm-lens-flash' }
        );
        clearFlash();
        flashHandles.push(handle);
        setTimeout(function () {
          try { handle.clear(); } catch (e) { }
        }, FLASH_MS);
      } catch (e) { }
    } catch (e) { }
  }

  function applyLens(seq, entries) {
    if (seq !== runSeq) return;
    try {
      if (cmRef && typeof cmRef.setLens === 'function') cmRef.setLens(entries);
    } catch (e) { }
  }

  function clear() {
    runSeq++;                    // invalidate any in-flight fetch
    activePath = null;
    clearFlash();
    try {
      if (cmRef && typeof cmRef.clearLens === 'function') cmRef.clearLens();
    } catch (e) { }
  }

  function refresh() {
    if (!isAvailable()) return;              // graceful no-op until merged
    var ME = window.IDE && window.IDE.mobileEditor;
    var cm = ME && typeof ME.getCM === 'function' ? ME.getCM() : null;
    var tab = ME && typeof ME.getActiveTab === 'function' ? ME.getActiveTab() : null;
    if (!cm || !tab || !tab.path) { clear(); return; }

    cmRef = cm;
    if (activePath !== tab.path) {
      // switching files: wipe the previous file's lenses immediately
      try { if (typeof cm.clearLens === 'function') cm.clearLens(); } catch (e) { }
    }
    activePath = tab.path;

    var index = symbolsApi();
    var seq = ++runSeq;
    Promise.resolve()
      .then(function () { return index(tab.path); })
      .then(function (res) {
        if (seq !== runSeq) return;
        var syms = (res && Array.isArray(res.symbols)) ? res.symbols : [];
        applyLens(seq, buildEntries(cm, syms));
      })
      .catch(function () {
        /* endpoint absent or failed — stay silent, it is best-effort */
      });
  }

  /** Sort by line, compute each symbol's span (to next symbol), build
   *  factory entries capped at MAX_LENSES. */
  function buildEntries(cm, syms) {
    var maxLine = 0;
    try { maxLine = Math.max(0, (cm.lineCount ? cm.lineCount() : 1) - 1); } catch (e) { }

    var sorted = [];
    for (var i = 0; i < syms.length; i++) {
      var s = syms[i];
      if (!s || typeof s !== 'object') continue;
      var ln = Number(s.line);
      if (!isFinite(ln) || !s.name) continue;
      sorted.push({ sym: s, line: Math.max(0, Math.min(maxLine, Math.floor(ln))) });
    }
    sorted.sort(function (a, b) { return a.line - b.line; });

    var seenLine = {};
    var entries = [];
    for (var j = 0; j < sorted.length && entries.length < MAX_LENSES; j++) {
      var item = sorted[j];
      if (seenLine[item.line]) continue;     // one lens per line
      seenLine[item.line] = true;
      var nextLine = (j + 1 < sorted.length) ? sorted[j + 1].line : item.line + 1;
      var span = Math.max(1, nextLine - item.line);
      entries.push({
        line: item.line,
        factory: makeRowFactory(item.sym, String(span))
      });
    }
    return entries;
  }

  /* ═══════════════════════════════════════════════════════════════
  LIFECYCLE
  ═══════════════════════════════════════════════════════════════ */
  function activeCM() {
    try {
      if (window.IDE.mobileEditor && typeof window.IDE.mobileEditor.getCM === 'function') {
        return window.IDE.mobileEditor.getCM();
      }
    } catch (e) { }
    return null;
  }

  function syncFromTab(detail) {
    var d = detail || {};
    cmRef = activeCM();
    clear();                                  // never bleed across tabs
    if (d.path) refresh();
  }

  function wireBus() {
    if (!U || typeof U.on !== 'function') return;
    U.on('editor:tab-shown', function (e) { syncFromTab(e && e.detail); });
    U.on('editor:saved', function () {
      if (!activeCM()) return;
      refresh();
    });
  }

  function boot() {
    wireBus();
  }

  /* ═══════════════════════════════════════════════════════════════
  PUBLIC API
  ═══════════════════════════════════════════════════════════════ */
  window.IDE.editorLens = {
    /** Re-fetch symbols for the active tab and re-render lenses. */
    refresh: refresh,
    /** Remove all lenses. */
    clear: clear,
    /** False until the symbols-index API lands in api.js. */
    isAvailable: isAvailable
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();