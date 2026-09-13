// File: mobile-terminal.js
/**
 * ═══════════════════════════════════════════════════════════════════════════
 *  QUIRKY IDE — MOBILE TERMINAL MODULE (v5 · Phase T-PTY-3 "Real Keystrokes")
 * ═══════════════════════════════════════════════════════════════════════════
 *
 *  v6 — STABILITY & POLISH PASS:
 *    ★ ONE WRAP POLICY — nowrap everywhere by DEFAULT (Termux look);
 *      screen and scrollback render identically, long lines escape via
 *      horizontal flick. Soft wrap is an opt-in toolbar ↔ toggle
 *      (.m-term-wrap / setWordWrap(true)).
 *    ★ SGR 256-COLOUR + TRUECOLOR (quantized) + DEC graphics ESC(0,
 *      DECAWM ?7, DECOM ?6 best-effort — matches the exported
 *      TERM=xterm-256color for real this time.
 *    ★ STICK-TO-BOTTOM autoscroll (manual scroll position survives
 *      output bursts), silent success footer, hardened TUI takeover
 *      heuristic (block-glyph bursts alone no longer wipe scrollback),
 *      per-run nonce echo, zoom/resize race fix, compact structured
 *      progress bar, condensed 3-row banner.
 *
 *  v5 — THE KEYBOARD-FEEL UPDATE (fixes Video 3 findings):
 *    ★ REAL-TIME RAW KEYSTROKES: while a command runs on a
 *      PTY, the input box becomes an invisible KEY CATCHER. Every character,
 *      sign and space is sent to the program THE INSTANT it is typed
 *      (beforeinput → stdin), Backspace sends a real delete (\x7f),
 *      Enter sends a real newline (\r). This is how a hardware terminal
 *      behaves and makes nano / vim / mpacker / REPLs / AI agents fully
 *      usable. The send button hides itself in this mode.
 *      Pipe mode (no PTY) keeps the old "type a line + Enter" behaviour.
 *    ★ NO TRAILING BLANK NOISE — the 24-row screen no longer paints its
 *      empty bottom rows, so vertical scrolling stops at the last real
 *      line instead of a blank dead zone.
 *    ★ REAL-TIME RAW KEYSTROKES (the big one): while a command runs on a
 *      PTY, the input box becomes an invisible KEY CATCHER. Every character,
 *      sign and space is sent to the program THE INSTANT it is typed
 *      (beforeinput → stdin), Backspace sends a real delete (\x7f),
 *      Enter sends a real newline (\r). This is how a hardware terminal
 *      behaves and makes nano / vim / mpacker / REPLs / AI agents fully
 *      usable. The send button hides itself in this mode.
 *      Pipe mode (no PTY) keeps the old "type a line + Enter" behaviour.
 *    ★ NO TRAILING BLANK NOISE — the 24-row screen no longer paints its
 *      empty bottom rows, so vertical scrolling stops at the last real
 *      line instead of a blank dead zone.
 *
 *  Everything from v4 is preserved: single session screen + scrollback,
 *  alternate screen (nano takes over, restores on exit), clear/ED3 wipes,
 *  CPR replies for vim, banner, history, zoom, transient progress line.
 *
 *  EXPOSES: window.IDE.mobileTerminal
 * ═══════════════════════════════════════════════════════════════════════════
 */
(function () {
  'use strict';
  var U = window.IDE && window.IDE.utils;
  var api = window.IDE && window.IDE.api;
  if (!U || !api) return;

  /* ═══════════════════════════════════════════════════════════
     DOM REFERENCES
     ═══════════════════════════════════════════════════════════ */
  var termOutput = document.getElementById('m-term-output');
  var termInput = document.getElementById('m-term-input');
  var termInputLine = document.getElementById('m-term-input-line');
  var termSendBtn = document.getElementById('mterm-send');
  var termClearBtn = document.getElementById('mterm-clear');
  var termCopyBtn = document.getElementById('mterm-copy');
  var termCancelBtn = document.getElementById('mterm-cancel');
  var termPrompt = document.getElementById('m-term-prompt');
  var termCwd = document.getElementById('m-term-cwd');
  var termStatus = document.getElementById('mterm-status');

  /* ═══════════════════════════════════════════════════════════
     STATE
     ═══════════════════════════════════════════════════════════ */
  var running = false;
  /* ★ HOTFIX: what kind of run is in flight. Shell runs emit SSE
     'started'; pkg/pipe streams never do. Pipe streams have NO server
     PTY, so the emulator owns wrapping and must reflow locally on zoom
     even while running (the old code waited for a server "live" ack that
     pipe runs can never produce → frozen cols → clipped text). */
  var runShell = false;
  var runPipe = false;
  /* ★ STABILITY PASS: per-run nonce from the server's SSE 'started'
     event. Echoed on every stdin frame (\x00Q<nonce>\x00 prefix, stripped
     server-side) so a stale tab can't type into a newer command. */
  var runNonce = '';
  var history = [];
  var historyIndex = -1;
  var currentBuffer = '';
  var currentCwdRel = '';
  var termInfoFetched = false;
  var termPty = false;
  var inited = false;
  var LS_HIST = 'quirky.ide.mobile.terminal.history';
  var HIST_MAX = 100;

  /* Terminal zoom (50% – 200%, persisted in localStorage) */
  var TERM_ZOOM_MIN = 30;
  var TERM_ZOOM_MAX = 200;
  var TERM_ZOOM_STEP = 10;
  var TERM_ZOOM_DEFAULT = 100;
  var LS_TERM_ZOOM = 'quirky.ide.mobile.termzoom';
  var termZoomPct = TERM_ZOOM_DEFAULT;
  try {
    var storedTermZoom = parseInt(localStorage.getItem(LS_TERM_ZOOM), 10);
    if (isFinite(storedTermZoom)) {
      termZoomPct = Math.max(TERM_ZOOM_MIN, Math.min(TERM_ZOOM_MAX, storedTermZoom));
    }
  } catch (e) { }

  /* ═══════════════════════════════════════════════════════════
     SMALL HELPERS
     ═══════════════════════════════════════════════════════════ */
  function toast(msg) {
    if (U && U.toast) U.toast(msg);
  }
  function escHtml(str) {
    if (typeof str !== 'string') return '';
    return str.replace(/&/g, '&amp;').replace(/</g, '&lt;')
      .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }
  function terminalEnabled() {
    var cfg = window.IDE_CONFIG || {};
    return !!(cfg.features && cfg.features.terminal);
  }
  function scrollTermBottom() {
    if (termOutput) termOutput.scrollTop = termOutput.scrollHeight;
  }

  /* ═══════════════════════════════════════════════════════════
     COMMAND HISTORY (localStorage persistence)
     ═══════════════════════════════════════════════════════════ */
  function loadHistory() {
    try {
      var raw = localStorage.getItem(LS_HIST);
      var arr = raw ? JSON.parse(raw) : [];
      return Array.isArray(arr) ? arr : [];
    } catch (e) { return []; }
  }
  function saveHistory() {
    try { localStorage.setItem(LS_HIST, JSON.stringify(history)); } catch (e) { }
  }
  function pushToHistory(cmd) {
    if (!cmd) return;
    if (history[history.length - 1] !== cmd) {
      history.push(cmd);
      if (history.length > HIST_MAX) history.shift();
      saveHistory();
    }
    historyIndex = -1;
    currentBuffer = '';
  }
  function historyUp() {
    if (!history.length) return;
    if (historyIndex === -1) {
      currentBuffer = termInput.value;
      historyIndex = history.length - 1;
    } else if (historyIndex > 0) {
      historyIndex--;
    }
    termInput.value = history[historyIndex];
  }
  function historyDown() {
    if (historyIndex === -1) return;
    historyIndex++;
    if (historyIndex >= history.length) {
      historyIndex = -1;
      termInput.value = currentBuffer;
    } else {
      termInput.value = history[historyIndex];
    }
  }

  /* ═══════════════════════════════════════════════════════════
     CWD TRACKING (prompt + header badge)
     ═══════════════════════════════════════════════════════════ */
  function cwdDisplay() {
    return 'workspace' + (currentCwdRel ? '/' + currentCwdRel : '');
  }
  function updateCwdUI() {
    if (termPrompt) termPrompt.textContent = '$';
    if (termCwd) termCwd.textContent = cwdDisplay() + '/';
  }

  /* ═══════════════════════════════════════════════════════════════
     SINGLE-SCREEN VT100 EMULATOR (v5: trims trailing blank rows)
     ═══════════════════════════════════════════════════════════════ */
  var EMU_ROWS = 24;   // MUST match ptyrun -h in Terminal.php
  var SB_MAX = 400;    // scrollback rows kept above the screen
  var EMU_COLS_MIN = 30;
  var EMU_COLS_MAX = 250;
  var currentCols = 80; // columns we last measured for this screen/zoom
  /* ★ HOTFIX: safety gutter for TUIs that paint their right border one
     cell past the requested width. It now applies ONLY to the column
     count we send to the PTY (ptyColsFor), never to the emulator grid
     that soft-wraps plain output. */
  var PTY_GUTTER = 2;

  /* ── FIT-TO-SCREEN (Phase T-PTY-5) ─────────────────────────────
  Measure how many monospace characters actually fit across the
  visible terminal pane at the current zoom. This number is sent
  to the server and given to the PTY, so TUI programs (nano,
  agentty, ...) draw their boxes to fit the phone screen instead
  of an invisible 80-column grid that word-wrap then shreds. */
  function measureCols() {
    if (!termOutput) return 80;
    var cs = window.getComputedStyle(termOutput);
    var fp = cs.fontSize || '';
    var charW = 0;
    if (charWCache.px === fp && charWCache.w > 0) {
      charW = charWCache.w;   // ★ font unchanged → reuse metrics (no probe, no forced reflow)
    } else {
      var probe = document.createElement('span');
      probe.style.position = 'absolute';
      probe.style.visibility = 'hidden';
      probe.style.display = 'inline-block';
      probe.style.whiteSpace = 'pre';
      probe.textContent = new Array(101).join('M'); // 100 chars
      termOutput.appendChild(probe);
      charW = probe.getBoundingClientRect().width / 100;
      termOutput.removeChild(probe);
      if (charW > 0) charWCache = { px: fp, w: charW };
    }
    if (!charW || charW <= 0) return 80;
    lastFontPx = fp;
    var usable = termOutput.clientWidth
      - parseFloat(cs.paddingLeft || '0')
      - parseFloat(cs.paddingRight || '0');
    /* ★ wrap fix preserved: FULL visible column count, no safety gutter
       (the gutter lives only in ptyColsFor, server-side). */
    var cols = Math.floor(usable / charW);
    return Math.max(EMU_COLS_MIN, Math.min(EMU_COLS_MAX, cols));
  }
  /* Columns we ask the PTY for: visible count minus the TUI gutter.
     A grid WIDER than the PTY is harmless (TUI lines arrive pre-wrapped
     narrower); a grid NARROWER than the PTY would double-wrap — so the
     gutter lives only on this side of the wire. */
  function ptyColsFor(viewColumns) {
    return Math.max(EMU_COLS_MIN, Math.min(EMU_COLS_MAX, viewColumns - PTY_GUTTER));
  }

  /* Re-measure and, if the width changed, resize the emulator. */
  function syncCols() {
    var c = measureCols();
    if (c !== currentCols) {
      currentCols = c;
      if (emu) emu.resize(c);
    }
    return currentCols;
  }
  /* ★ T-PTY-7 — REAL-TIME RESIZE.
     Zoom / rotation now apply WHILE a command runs:
       1. the font size changes instantly (CSS variable);
       2. the new column count is sent to the server, which writes it
          into ptyrun's winsize file — ptyrun fires SIGWINCH and the
          running TUI (nano, agentty, …) redraws to fit, exactly like
          dragging a desktop terminal window;
       3. when the server confirms (live:true) the emulator grid is
          resized too. With an OLD ptyrun (no winsize hook) the server
          answers live:false and we keep the current grid until the
          next command — the text size still changes immediately. */
  var liveResizeTimer = null;
  var liveResizeRaf = 0;
  var lastFontPx = '';
  var charWCache = { px: '', w: 0 };

  /* ★ STABILITY PASS — ZOOM/RESIZE RACE FIX. measureCols() used to sample
   the pane mid font-size transition (0.15s CSS) after only a 120 ms
   debounce, so the PTY got a width belonging to an intermediate zoom
   and TUIs drew boxes for the wrong grid. Now:
     • while a command runs, the font-size transition is DISABLED
       entirely (.m-term-running → transition:none), so any delay is
       purely a settle window;
     • the debounce is 260 ms (> 150 ms animation) AND we re-arm once
       more on 'transitionend', so measurement happens only after the
       font truly stopped moving;
     • exactly ONE resize is sent per settled size change. */
  /* ★ HOTFIX — RESPONSIVE ZOOM PIPELINE:
     zoom click → CSS var updates instantly (presentation only)
                → rAF-coalesced measurement (ONE per frame batch)
                → settle ONLY while the font is genuinely animating
                → a single reflow (cheap path skips re-wrap entirely
                  when no logical line crosses either width) → repaint.
   No blind 260 ms delay: running commands (transition off) reflow on
   the next animation frame; idle zooms settle on transitionend. */
  function doResizePass() {
    liveResizeTimer = null;
    liveResizeRaf = 0;
    var c = measureCols();
    var changed = (c !== currentCols);
    if (!running || runPipe || !termPty) {
      if (changed) { currentCols = c; if (emu) emu.resize(c); }
      return;
    }
    if (api && api.terminal && api.terminal.resize) {
      api.terminal.resize(ptyColsFor(c), EMU_ROWS, runNonce || undefined).then(function (r) {
        if (r && r.live && changed) {
          currentCols = c;
          if (emu) emu.resize(c);
        }
      }).catch(function () { });
    } else if (changed) {
      currentCols = c;
      if (emu) emu.resize(c);
    }
  }
  function scheduleLiveResize() {
    if (liveResizeTimer) { clearTimeout(liveResizeTimer); liveResizeTimer = null; }
    if (liveResizeRaf) return;                  // already coalesced for next frame
    liveResizeRaf = requestAnimationFrame(function () {
      liveResizeRaf = 0;
      var fp = fontPxNow();
      var animating = (fp !== lastFontPx) && transitionRuns();
      if (animating) {
        liveResizeTimer = setTimeout(doResizePass, 160); // tied to the 0.15s font transition
      } else {
        doResizePass();
      }
    });
  }
  function finishResizeSettle() {
    if (liveResizeTimer) { clearTimeout(liveResizeTimer); liveResizeTimer = null; }
    if (liveResizeRaf) { cancelAnimationFrame(liveResizeRaf); liveResizeRaf = 0; }
    doResizePass();
  }
  function transitionRuns() {
    if (!termOutput || running) return false;   // .m-term-running disables the transition
    try {
      var d = window.getComputedStyle(termOutput).transitionDuration || '0s';
      return d !== '0s' && d !== '';
    } catch (e) { return false; }
  }
  function fontPxNow() {
    if (!termOutput) return '';
    try { return window.getComputedStyle(termOutput).fontSize || ''; } catch (e) { return ''; }
  }

  function emuState() {
    return { fg: null, bg: null, bold: false, dim: false, italic: false, underline: false };
  }
  function emuKey(s) {
    return (s.bold ? 'b' : '') + (s.dim ? 'd' : '') + (s.italic ? 'i' : '') + (s.underline ? 'u' : '') +
      'f' + (s.fg == null ? '' : s.fg) + 'b' + (s.bg == null ? '' : s.bg);
  }
  function emuClasses(s) {
    var cls = [];
    if (s.fg != null) cls.push('ansi-fg-' + s.fg);
    if (s.bg != null) cls.push('ansi-bg-' + s.bg);
    if (s.bold) cls.push('ansi-bold');
    if (s.dim) cls.push('ansi-dim');
    if (s.italic) cls.push('ansi-italic');
    if (s.underline) cls.push('ansi-underline');
    return cls.join(' ');
  }

  /* ── SGR 256-COLOUR / TRUECOLOR SUPPORT (stability pass) ────────
     The server exports TERM=xterm-256color, so programs legitimately
     emit 38;5;n / 48;5;n (and modern TUIs emit 38;2;r;g;b). Palette
     entries become classes ansi-fg-c<n> / ansi-bg-c<n>; truecolor is
     quantized to the NEAREST of the 256 xterm palette entries (squared
     RGB distance — imperceptible for TUI accents on a phone screen).
     The palette itself is a generated CSS table in mobile-terminal.css
     (~10 KB, no runtime cost beyond class lookup). */
  var PALETTE_RGB = (function () {
    var base = [
      [26, 26, 26], [224, 82, 82], [74, 222, 128], [251, 191, 36],
      [96, 165, 250], [192, 132, 252], [45, 212, 191], [200, 214, 232],
      [113, 134, 165], [248, 113, 113], [134, 239, 172], [253, 224, 71],
      [147, 197, 253], [216, 180, 254], [94, 234, 212], [248, 250, 252]
    ];
    var lv = [0, 95, 135, 175, 215, 255];
    for (var r = 0; r < 6; r++) {
      for (var g = 0; g < 6; g++) {
        for (var b = 0; b < 6; b++) {
          base.push([lv[r], lv[g], lv[b]]);
        }
      }
    }
    for (var i = 0; i < 24; i++) {
      var v = 8 + i * 10;
      base.push([v, v, v]);
    }
    return base; // 16 + 216 + 24 = 256 entries
  })();
  function nearestPaletteIndex(r, g, b) {
    var best = 0, bestD = Infinity;
    for (var i = 0; i < 256; i++) {
      var c = PALETTE_RGB[i];
      var dr = c[0] - r, dg = c[1] - g, db = c[2] - b;
      var d = dr * dr + dg * dg + db * db;
      if (d < bestD) { bestD = d; best = i; }
    }
    return best;
  }

  /* ── SPLIT-CHUNK GUARD (T-PTY-7) ─────────────────────────────────
     Output arrives in network chunks that can cut an escape sequence
     in half ("\x1b[2" + "J"). Without this guard the parser printed
     "[2" as literal text and the CLEAR never happened. We hold back a
     trailing incomplete sequence and glue it onto the next chunk. */
  function tailIncompleteEscape(t) {
    var i = t.lastIndexOf('\x1b');
    if (i === -1) return 0;
    var tail = t.slice(i);
    /* complete sequences → parse normally */
    if (/^\x1b[78=>M]$/.test(tail)) return 0;
    if (/^\x1b\([A-Za-z0-9]$/.test(tail)) return 0;
    if (/^\x1b\[[0-9;?]*[A-Za-z]$/.test(tail)) return 0;
    if (/^\x1b\][^\x07\x1b]*(?:\x07|\x1b\\)$/.test(tail)) return 0;
    /* incomplete CSI / charset / bare ESC (short tails only) */
    if (tail.length <= 16 && /^\x1b(?:\[[0-9;?]*|\(?)?$/.test(tail)) return tail.length;
    /* incomplete OSC (title sequence) without its terminator */
    if (tail.length <= 96 && /^\x1b\][^\x07\x1b]*$/.test(tail)) return tail.length;
    return 0;
  }

  /* Parser: OSC | CSI | charset sel (captured — DEC graphics) | DEC escapes | CR | LF | TAB | BS | BEL | char */
  var EMU_RE = /\x1b\][^\x07\x1b]*(?:\x07|\x1b\\)|\x1b\[([0-9;?]*)([A-Za-z])|\x1b\(([A-Za-z0-9])|\x1b([78=>M])|(\r)|(\n)|(\t)|(\x08)|(\x07)|([\s\S])/g;

  /* ── DEC SPECIAL GRAPHICS (ESC(0) mapping ────────────────────────
     ncurses apps that can't find our terminfo draw borders as
     "lqkxjmn" letters. While the G0 charset is '0' these chars map to
     the real box-drawing glyphs; ESC(B restores normal ASCII. */
  var DEC_GRAPHICS = {
    '`': '◆', 'a': '▒', 'b': '␉', 'c': '␌', 'd': '␍', 'e': '␊',
    'f': '°', 'g': '±', 'h': '␤', 'i': '␋', 'j': '┘', 'k': '┐',
    'l': '┌', 'm': '└', 'n': '┼', 'o': '⎺', 'p': '⎻', 'q': '─',
    'r': '⎼', 's': '⎽', 't': '├', 'u': '┤', 'v': '┴', 'w': '┬',
    'x': '│', 'y': '≤', 'z': '≥', '{': 'π', '|': '≠', '}': '£',
    '~': '·', '_': ' '
  };

  /* ── T-PTY-9: does this row contain characters that phone fonts
render at the wrong width (box drawing, blocks, arrows, braille,
geometric shapes, emoji)? Those rows get fixed-width cells. ── */
  function rowHasWide(cells) {
    for (var i = 0; i < cells.length; i++) {
      var c = cells[i];
      if (!c) continue;
      var code = c.c.charCodeAt(0);
      if ((code >= 0x2500 && code <= 0x25FF) ||
        (code >= 0x2190 && code <= 0x21FF) ||
        (code >= 0x2800 && code <= 0x28FF) ||
        (code >= 0x2B00 && code <= 0x2BFF) ||
        (code >= 0x1F000)) return true;
    }
    return false;
  }
  /* Build one row's DOM node from its cell data */
  function rowNode(cells, isCursorRow, cursorX, cursorVisible) {
    var div = document.createElement('div');
    div.className = 'm-te-row';
    var last = -1;
    for (var i = cells.length - 1; i >= 0; i--) {
      if (cells[i] && cells[i].c !== ' ') { last = i; break; }
    }
    if (isCursorRow && cursorVisible && cursorX > last) last = cursorX;
    if (last < 0) return div;
    /* ★ T-PTY-9: rows with box/block characters are rendered as one
    fixed-width (1ch) cell per column. Each glyph is clipped/centered
    into its own cell, so borders and ASCII-art logos stay perfectly
    aligned even when the phone font has wrong glyph widths. */
    if (rowHasWide(cells)) {
      for (var x2 = 0; x2 <= last; x2++) {
        var cell2 = cells[x2];
        var isCur2 = isCursorRow && cursorVisible && x2 === cursorX;
        var span2 = document.createElement('span');
        span2.className = 'm-te-cell' +
          (cell2 && cell2.cls ? ' ' + cell2.cls : '') +
          (isCur2 ? ' m-te-cursor' : '');
        span2.appendChild(document.createTextNode(cell2 ? cell2.c : ' '));
        div.appendChild(span2);
      }
      return div;
    }
    var span = null, lastKey = null;
    for (var x = 0; x <= last; x++) {
      var cell = cells[x];
      var ch = cell ? cell.c : ' ';
      var cls = cell ? cell.cls : '';
      var isCur = isCursorRow && cursorVisible && x === cursorX;
      var key = cls + (isCur ? '|C' : '');
      if (key !== lastKey || !span) {
        span = document.createElement('span');
        span.className = cls + (isCur ? (cls ? ' ' : '') + 'm-te-cursor' : '');
        div.appendChild(span);
        lastKey = key;
      }
      span.appendChild(document.createTextNode(ch));
    }
    return div;
  }

  /* Trim trailing empty cells so logical-line concatenation does not
     carry row padding into the next wrapped segment. */
  function trimCells(cells) {
    var end = cells.length;
    while (end > 0 && !cells[end - 1]) end--;
    return cells.slice(0, end);
  }
  function TermEmu(screenHost, sbHost, callbacks) {
    this.sbData = [];  // ★ scrollback row metadata {cells, soft} for reflow
    this.host = screenHost;
    this.sbHost = sbHost;
    this.cb = callbacks || {};
    this.cols = currentCols;
    this.rows = EMU_ROWS;
    this._raf = 0;
    this._ov = null;      // per-call class override (echo/info/error lines)
    this.progY = null;    // legacy field (kept harmless)
    this.progPayload = null; // ★ latest download/install progress STATE
    this._sbBatch = null;    // ★ fragment batch used during reflow
    this.reset();
  }
  TermEmu.prototype.blankRow = function () {
    var r = new Array(this.cols);
    for (var i = 0; i < this.cols; i++) r[i] = null;
    return r;
  };
  TermEmu.prototype.reset = function () {
    this.screen = [];
    this.rowSoft = [];  // ★ per-row flag: row ended by autowrap (true) or \n (false)
    for (var r = 0; r < this.rows; r++) { this.screen.push(this.blankRow()); this.rowSoft.push(false); }
    this.x = 0; this.y = 0;
    this.top = 0; this.bot = this.rows - 1;
    this.st = emuState();
    this._ck = emuKey(this.st);
    this._cc = '';
    this.saved = null;
    this.alt = null;
    this._takeover = false;
    this.cursorVisible = true;
    this._pend = '';
    this._blockBurst = 0;
    this.g0 = 'B';        // G0 charset: 'B' ASCII, '0' DEC special graphics
    this.autowrap = true; // DECAWM ?7 (h toggles on, l off)
    this.origin = false;  // DECOM ?6 — best-effort origin mode
    this._tuiSignal = false; // corroborating TUI signal within current burst
    this.scheduleRender();
  };
  /* Full wipe: screen + scrollback (toolbar Clear / local `clear`) */
  TermEmu.prototype.hardReset = function () {
    if (this.alt) { this.alt = null; }
    this._takeover = false;
    this.altActive = false;
    if (termOutput) termOutput.classList.remove('m-term-fresh');
    if (this.sbHost) {
      this.sbHost.innerHTML = '';
      this.sbHost.style.display = '';
    }
    this.sbData = [];
    if (termOutput) termOutput.classList.remove('m-term-alt');
    this.progY = null;
    this.progPayload = null;   // ★ a fresh session starts without progress state
    this.reset();
  };
  /* Phase T-PTY-5: change screen width. The old screen content is
  pushed into scrollback first so zooming never eats history. */
  TermEmu.prototype.pushSb = function (rowData, soft) {
    var host = this._sbBatch || this.sbHost;   // ★ batch mode during reflow
    if (!host) return;
    host.appendChild(rowNode(rowData, false, 0, false));
    this.sbData.push({ cells: rowData, soft: !!soft });
    while (this.sbData.length > SB_MAX) {
      this.sbData.shift();
      var first = host.firstChild;
      if (first && first.classList && first.classList.contains('m-term-banner')) {
        first = first.nextSibling;
      }
      if (first) host.removeChild(first);
    }
  };
  TermEmu.prototype.resize = function (cols) {
    if (cols === this.cols) return;
    var oldCols = this.cols;
    this.cols = cols;
    /* Gather row metadata (scrollback + live screen) with soft flags. */
    var meta = this.sbData.slice();
    var screenMetaCount = 0;
    for (var r = 0; r < this.screen.length; r++) {
      var hasContent = this.rowSoft[r];
      for (var c = 0; c < this.screen[r].length; c++) {
        if (this.screen[r][c]) { hasContent = true; break; }
      }
      if (hasContent) { meta.push({ cells: this.screen[r], soft: this.rowSoft[r] }); screenMetaCount++; }
    }
    /* Rebuild logical lines (hard \n = boundary, soft = join). */
    var lines = [];
    var cur = null;
    var minC = Math.min(oldCols, cols);
    var needRebuild = false;
    for (var i = 0; i < meta.length; i++) {
      var cells = trimCells(meta[i].cells);
      cur = (cur === null) ? cells : cur.concat(cells);
      if (!meta[i].soft) {
        lines.push(cur);
        if (cur.length > minC) needRebuild = true;  // ★ crosses either width → splits change
        cur = null;
      }
    }
    if (cur !== null) { lines.push(cur); if (cur.length > minC) needRebuild = true; }
    var bannerNode = this.sbHost ? this.sbHost.querySelector('.m-term-banner') : null;
    if (needRebuild) {
      /* Full reflow, but the DOM is built ONCE into a fragment (single
         invalidation instead of one appendChild per row). */
      this._sbBatch = document.createDocumentFragment();
      this.sbData = [];
      for (var li = 0; li < lines.length; li++) {
        var line = lines[li];
        if (!line.length) { this.pushSb(this.blankRow(), false); continue; }
        for (var pos = 0; pos < line.length; pos += cols) {
          var chunk = line.slice(pos, pos + cols);
          var isLast = (pos + cols >= line.length);
          while (chunk.length < cols) chunk.push(null);
          this.pushSb(chunk, !isLast);
        }
      }
      if (this.sbHost) {
        this.sbHost.innerHTML = '';
        if (bannerNode) this.sbHost.appendChild(bannerNode);
        this.sbHost.appendChild(this._sbBatch);
      }
      this._sbBatch = null;
    } else {
      /* ★ CHEAP PATH: no logical line crosses either width, so every
         existing split stays byte-identical — move the live screen nodes
         into scrollback as-is (no re-wrap, no rowNode churn). */
      for (var mi = meta.length - screenMetaCount; mi < meta.length; mi++) {
        this.sbData.push({ cells: meta[mi].cells, soft: meta[mi].soft });
      }
      if (this.sbHost && this.host) {
        while (this.host.firstChild) this.sbHost.appendChild(this.host.firstChild);
      }
      while (this.sbData.length > SB_MAX) {
        this.sbData.shift();
        var first = this.sbHost ? this.sbHost.firstChild : null;
        if (first && first.classList && first.classList.contains('m-term-banner')) first = first.nextSibling;
        if (first && this.sbHost) this.sbHost.removeChild(first);
      }
    }
    /* Screen resets (history preserved above), cursor home. */
    this.screen = [];
    this.rowSoft = [];
    for (var i2 = 0; i2 < this.rows; i2++) { this.screen.push(this.blankRow()); this.rowSoft.push(false); }
    this.x = 0; this.y = 0;
    this.top = 0; this.bot = this.rows - 1;
    this.progY = null;   // legacy; progress state (progPayload) intentionally survives resize
    if (this.alt) {
      this.alt = null;
      this.altActive = false;
      if (this.sbHost) this.sbHost.style.display = '';
      if (termOutput) termOutput.classList.remove('m-term-alt');
    }
    this.scheduleRender();
  };
  TermEmu.prototype.clampCursor = function () {
    if (this.x < 0) this.x = 0;
    if (this.x >= this.cols) this.x = this.cols - 1;
    if (this.y < 0) this.y = 0;
    if (this.y >= this.rows) this.y = this.rows - 1;
  };
  TermEmu.prototype.put = function (ch) {
    /* ★ DECAWM ?7: when the program disabled auto-wrap, printing at the
       right edge OVERWRITES the last column instead of wrapping. */
    if (this.x >= this.cols) {
      if (this.autowrap === false) { this.x = this.cols - 1; }
      else { this.x = 0; this.lf(true); }   // renderer-generated break
    }
    /* ★ DEC graphics: while G0 = '0' (ESC(0 active), remap line chars. */
    if (this.g0 === '0' && Object.prototype.hasOwnProperty.call(DEC_GRAPHICS, ch)) {
      ch = DEC_GRAPHICS[ch];
    }
    this.screen[this.y][this.x] = { c: ch, k: this._ck, cls: (this._ov !== null ? this._ov : this._cc) };
    this.x++;
  };
  /* REAL-TERMINAL LF: down AND back to column 0 (ONLCR behaviour). */
  TermEmu.prototype.lf = function (soft) {
    /* ★ SEMANTICS: record HOW this row ended. soft=true → break inserted
       by the renderer (autowrap); soft=false → a real \n from the program.
       resize() rebuilds logical lines from these flags, so hard newlines
       survive every zoom/resize untouched while soft wraps re-flow. */
    this.rowSoft[this.y] = !!soft;
    this.x = 0;
    /* ★ A newline ends a "burst": block-glyph counters and the TUI
       corroborating signals only ever accumulate WITHIN one burst, so
       glyph-heavy plain output (figlet banners, progress bars) can never
       carry a stale cursor-hide/clear flag across lines and wipe the
       scrollback by accident. */
    this._blockBurst = 0;
    this._tuiSignal = false;
    if (this.y === this.bot) this.scrollUp(1);
    else if (this.y < this.rows - 1) this.y++;
  };
  TermEmu.prototype.scrollUp = function (n) {
    for (var i = 0; i < n; i++) {
      var old = this.screen.shift();
      var oldSoft = this.rowSoft.shift();
      this.screen.push(this.blankRow());
      this.rowSoft.push(false);
      this.pushSb(old, oldSoft);
      if (this.progY !== null) this.progY--;
      if (this.progY !== null && this.progY < 0) this.progY = null;
    }
  };
  TermEmu.prototype.scrollDown = function (n) {
    for (var i = 0; i < n; i++) {
      this.screen.splice(this.bot, 1);
      this.screen.splice(this.top, 0, this.blankRow());
      this.rowSoft.splice(this.bot, 1);
      this.rowSoft.splice(this.top, 0, false);
    }
  };
  TermEmu.prototype.tab = function () {
    var next = (Math.floor(this.x / 8) + 1) * 8;
    this.x = Math.min(this.cols - 1, next);
  };
  TermEmu.prototype.write = function (text, overrideCls) {
    if (this._pend) { text = this._pend + text; this._pend = ''; }
    var hold = tailIncompleteEscape(text);
    if (hold > 0) {
      this._pend = text.slice(text.length - hold);
      text = text.slice(0, text.length - hold);
      if (text === '') return;
    }
    this._ov = overrideCls || null;
    EMU_RE.lastIndex = 0;
    var m;
    while ((m = EMU_RE.exec(text)) !== null) {
      if (m[1] !== undefined) { this.csi(m[1], m[2]); }
      else if (m[3] !== undefined) {
        /* charset selector: ESC(0 → DEC graphics, ESC(B → ASCII */
        this.g0 = (m[3] === '0') ? '0' : 'B';
      }
      else if (m[4] !== undefined) { this.esc(m[4]); }
      else if (m[5] !== undefined) { this.x = 0; }                        // CR
      else if (m[6] !== undefined) { this.lf(false); }                   // LF = HARD newline      else if (m[7] !== undefined) { this.tab(); }                       // TAB
      else if (m[8] !== undefined) { this.x = Math.max(0, this.x - 1); } // BS
      else if (m[9] !== undefined) { /* bell */ }
      else if (m[10] !== undefined) {
        var code = m[10].charCodeAt(0);
        if (code >= 32 && code !== 127) {
          /* ★ HARDENED TAKEOVER HEURISTIC: a burst of block glyphs
          (U+2580-259F) ALONE no longer wipes the scrollback — plain
          output full of █ progress bars / figlet logos used to trip it.
          Takeover now ALSO requires a corroborating TUI signal seen in
          the same burst window: the cursor was hidden (ESC[?25l) or an
          ED2/ED3 clear occurred (both set _tuiSignal). Otherwise the
          glyphs are simply rendered as the text they are. Counters reset
          on every newline (see lf()). */
          if (code >= 0x2580 && code <= 0x259F) {
            this._blockBurst = (this._blockBurst || 0) + 1;
            if (this._blockBurst >= 40 && this._tuiSignal) {
              this._blockBurst = 0;
              this.takeover();
            }
          }
          this.put(m[10]);
        }
      }
    }
    this._ov = null;
    this.scheduleRender();
  };
  /* Write styled segments then a newline (echo / info / error lines) */
  TermEmu.prototype.printlnSegs = function (segs) {
    if (this.x > 0) this.lf(false);
    for (var i = 0; i < segs.length; i++) {
      this._ov = segs[i].c || null;
      var t = segs[i].t;
      for (var j = 0; j < t.length; j++) this.put(t.charAt(j));
      this._ov = null;
    }
    this.lf(false);
    this.scheduleRender();
  };
  /* ── transient progress line (apt-style, ONE row, redrawn in place) ──
     ★ POLISH PASS: accepts either a legacy plain string or the server's
     structured payload {phase,name,pct,got,total,indeterminate,text} and
     renders a COMPACT single line: "⇣ clang ██████░░░░ 58% 4.3/7.4MB".
     The bar gives instant feedback at phone width; everything after the
     pct is dropped first when space runs out. */
  function fmtMb(n) {
    n = Math.max(0, n || 0);
    if (n >= 1048576) return (n / 1048576).toFixed(1) + 'MB';
    if (n >= 1024) return (n / 1024).toFixed(0) + 'KB';
    return n + 'B';
  }
  function progDisplayText(payload) {
    if (typeof payload === 'string') return payload;
    if (!payload || typeof payload !== 'object') return String(payload == null ? '' : payload);
    var icon = payload.phase === 'extract' ? '⇩' : (payload.phase === 'link' ? '✓' : '⇣');
    var name = payload.name || payload.phase || '';
    var pct = (payload.pct == null) ? '' : (payload.pct + '%');
    if (payload.indeterminate) {
      /* Honest byte counter only — no percentage, no fake total. */
      return icon + ' ' + name + ((payload.got || 0) > 0 ? ' ' + fmtMb(payload.got) : '') + '…';
    }
    var BAR_W = 10;
    var filled = Math.max(0, Math.min(BAR_W, Math.round((payload.pct || 0) / 100 * BAR_W)));
    var bar = '';
    for (var i = 0; i < BAR_W; i++) bar += (i < filled ? '█' : '░');
    var tail = '';
    if (payload.total > 0 && payload.phase !== 'link') {
      tail = fmtMb(payload.got) + '/' + fmtMb(payload.total);
    }
    var t = icon + ' ' + name + ' ' + bar + ' ' + pct;
    if (tail) t += ' ' + tail;
    return t;
  }
  /* One transient progress row built from the latest payload (state),
   truncated to the current column count so it never wraps/scrolls. */
  function progRowNode(payload, cols) {
    var div = document.createElement('div');
    div.className = 'm-te-row';
    var span = document.createElement('span');
    span.className = 'm-term-prog';
    var t = progDisplayText(payload);
    var max = Math.max(8, (cols || 80) - 1);
    if (t.length > max) t = t.slice(0, max);
    span.textContent = t;
    div.appendChild(span);
    return div;
  }
  /* ★ HOTFIX — PROGRESS IS RENDERER STATE, NOT BUFFER CONTENT.
   The old code painted the progress row INTO the screen grid, so every
   zoom/reflow froze it into scrollback (duplicate/stale rows) and lost
   progY. Now the latest payload lives in this.progPayload; render()
   draws it fresh at the CURRENT width each frame, and resize/reflow
   never touches it. Zoom = presentation only; downloads keep running
   completely independently of it. */
  TermEmu.prototype.showProg = function (payload) {
    if (!payload || typeof payload !== 'object') {
      this.progPayload = null;
      this.scheduleRender();
      return;
    }
    var p = payload;
    /* Renderer-side consistency guard (mirrors the backend invariant):
       pct always agrees with got/total; impossible pairs degrade to
       indeterminate instead of being silently clamped. */
    if (!p.indeterminate && p.total > 0) {
      if (p.got > p.total) {
        p = Object.assign({}, p, { indeterminate: true, pct: 0 });
      } else {
        p = Object.assign({}, p, { pct: Math.max(0, Math.min(100, Math.round((p.got / p.total) * 100))) });
      }
    }
    this.progPayload = p;
    this.scheduleRender();
  };
  TermEmu.prototype.clearProg = function () {
    if (this.progPayload === null) return;
    this.progPayload = null;
    this.scheduleRender();
  };
  TermEmu.prototype.esc = function (ch) {
    if (ch === '7') {
      this.saved = { x: this.x, y: this.y, st: this.st };
    } else if (ch === '8') {
      if (this.saved) {
        this.x = this.saved.x; this.y = this.saved.y; this.st = this.saved.st;
        this._ck = emuKey(this.st); this._cc = emuClasses(this.st);
      }
    } else if (ch === 'M') {
      if (this.y === this.top) this.scrollDown(1);
      else if (this.y > 0) this.y--;
    }
  };
  TermEmu.prototype.csi = function (paramsStr, final) {
    var priv = paramsStr.charAt(0) === '?' ? '?' : '';
    var raw = (priv === '?') ? paramsStr.slice(1) : paramsStr;
    var parts = raw === '' ? [] : raw.split(';');
    var p = [];
    for (var i = 0; i < parts.length; i++) {
      p.push(parts[i] === '' ? -1 : parseInt(parts[i], 10));
    }
    function P(idx, def) {
      var v = p[idx];
      return (v == null || isNaN(v) || v === -1) ? def : v;
    }
    var n, r, c, row, i2;
    switch (final) {
      case 'A': this.y -= P(0, 1); this.clampCursor(); break;
      case 'B': this.y += P(0, 1); this.clampCursor(); break;
      case 'C': this.x += P(0, 1); this.clampCursor(); break;
      case 'D': this.x -= P(0, 1); this.clampCursor(); break;
      case 'E': this.y += P(0, 1); this.x = 0; this.clampCursor(); break;
      case 'F': this.y -= P(0, 1); this.x = 0; this.clampCursor(); break;
      case 'G': this.x = P(0, 1) - 1; this.clampCursor(); break;
      case 'H': case 'f':
        this.y = P(0, 1) - 1;
        /* ★ DECOM ?6: row addressing is relative to the scroll region top
           and clamped inside it (best-effort origin mode). */
        if (this.origin) {
          this.y = this.top + (this.y < 0 ? 0 : this.y);
          if (this.y > this.bot) this.y = this.bot;
        }
        this.x = P(1, 1) - 1; this.clampCursor();
        break;
      case 'J':
        n = P(0, 0);
        if (n === 0) {
          row = this.screen[this.y];
          for (c = this.x; c < this.cols; c++) row[c] = null;
          for (r = this.y + 1; r < this.rows; r++) this.screen[r] = this.blankRow();
        } else if (n === 1) {
          for (r = 0; r < this.y; r++) this.screen[r] = this.blankRow();
          row = this.screen[this.y];
          for (c = 0; c <= this.x && c < this.cols; c++) row[c] = null;
        } else if (n === 2 || n === 3) {
          /* ★ T-PTY-6: BOTH 2 and 3 wipe the visible screen AND the
             scrollback history (clean slate, like the desktop). */
          for (r = 0; r < this.rows; r++) this.screen[r] = this.blankRow();
          if (this.sbHost) this.sbHost.innerHTML = '';
          this.progY = null;
          /* ★ STABILITY PASS: an ED2/ED3 clear is a corroborating TUI
             signal for the block-glyph takeover heuristic… */
          this._tuiSignal = true;
          /* ★ T-PTY-8: …and a clear itself means "I own the screen now". */
          this.takeover();
        }
        break;
      case 'K':
        n = P(0, 0);
        row = this.screen[this.y];
        if (n === 0) { for (c = this.x; c < this.cols; c++) row[c] = null; }
        else if (n === 1) { for (c = 0; c <= this.x && c < this.cols; c++) row[c] = null; }
        else { this.screen[this.y] = this.blankRow(); }
        break;
      case 'L':
        n = Math.min(P(0, 1), this.bot - this.y + 1);
        if (this.y >= this.top && this.y <= this.bot && n > 0) {
          for (i2 = 0; i2 < n; i2++) {
            this.screen.splice(this.bot, 1);
            this.screen.splice(this.y, 0, this.blankRow());
          }
        }
        this.x = 0;
        break;
      case 'M':
        n = Math.min(P(0, 1), this.bot - this.y + 1);
        if (this.y >= this.top && this.y <= this.bot && n > 0) {
          for (i2 = 0; i2 < n; i2++) {
            this.screen.splice(this.y, 1);
            this.screen.splice(this.bot, 0, this.blankRow());
          }
        }
        this.x = 0;
        break;
      case 'P':
        n = P(0, 1);
        row = this.screen[this.y];
        row.splice(this.x, n);
        while (row.length < this.cols) row.push(null);
        break;
      case '@':
        n = P(0, 1);
        row = this.screen[this.y];
        for (i2 = 0; i2 < n; i2++) row.splice(this.x, 0, null);
        row.length = this.cols;
        break;
      case 'X':
        n = P(0, 1);
        row = this.screen[this.y];
        for (c = this.x; c < this.x + n && c < this.cols; c++) row[c] = null;
        break;
      case 'S': this.scrollUp(P(0, 1)); break;
      case 'T': this.scrollDown(P(0, 1)); break;
      case 'd':
        this.y = P(0, 1) - 1;
        if (this.origin) {
          this.y = this.top + (this.y < 0 ? 0 : this.y);
          if (this.y > this.bot) this.y = this.bot;
        }
        this.clampCursor();
        break;
      case 'm': this.sgr(p); break;
      case 'n':
        if (P(0, 0) === 6 && this.cb.cpr) {
          this.cb.cpr(this.y + 1, this.x + 1);
        }
        break;
      case 'r':
        var t = P(0, 1) - 1;
        var b = P(1, this.rows) - 1;
        if (t < 0) t = 0;
        if (b > this.rows - 1) b = this.rows - 1;
        if (t < b) { this.top = t; this.bot = b; }
        this.x = 0; this.y = 0;
        break;
      case 's': this.saved = { x: this.x, y: this.y, st: this.st }; break;
      case 'u':
        if (this.saved) { this.x = this.saved.x; this.y = this.saved.y; this.st = this.saved.st; this._ck = emuKey(this.st); this._cc = emuClasses(this.st); }
        break;
      case 'h': case 'l':
        if (priv === '?') this.decMode(P(0, 0), final === 'h');
        break;
      default: break;
    }
  };
  /* Alternate screen: nano/vim take over the WHOLE visible terminal. */
  TermEmu.prototype.decMode = function (mode, on) {
    if (mode === 1049 || mode === 1047) {
      if (on && !this.alt) {
        this.alt = { screen: this.screen, x: this.x, y: this.y };
        this.screen = [];
        for (var r = 0; r < this.rows; r++) this.screen.push(this.blankRow());
        this.x = 0; this.y = 0;
        this.progY = null;
        this.altActive = true;
        if (this.sbHost) this.sbHost.style.display = 'none';
        if (termOutput) termOutput.classList.add('m-term-alt');
      } else if (!on && this.alt) {
        this.screen = this.alt.screen;
        this.x = this.alt.x; this.y = this.alt.y;
        this.alt = null;
        this.altActive = false;
        if (this.sbHost) this.sbHost.style.display = '';
        if (termOutput) termOutput.classList.remove('m-term-alt');
      }
      this.scheduleRender();
    } else if (mode === 1048) {
      if (on) this.saved = { x: this.x, y: this.y, st: this.st };
      else if (this.saved) { this.x = this.saved.x; this.y = this.saved.y; }
    } else if (mode === 25) {
      this.cursorVisible = on;
      /* ★ STABILITY PASS: a cursor hide inside the current burst is a
         corroborating TUI signal for the block-glyph takeover heuristic.
         (The direct takeover-on-hide stays: full-screen programs hide the
         hardware cursor the moment they own the pane.) */
      if (!on) {
        this._tuiSignal = true;
        this.takeover();
      }
    } else if (mode === 7) {
      /* ★ DECAWM — auto-wrap toggle. off → printing past the last column
         overwrites it instead of wrapping (minimal but honest support). */
      this.autowrap = on;
    } else if (mode === 6) {
      /* ★ DECOM — origin mode: CUP/CUP-style moves become relative to the
         scroll region (clamped inside it). Best-effort. */
      this.origin = on;
      if (on) { this.x = 0; this.y = this.top; }
    } else if (mode === 2004) {
      /* ★ Bracketed paste: markers (?2004h/l) are consumed here as a
         harmless no-op. We deliberately do NOT wrap sent paste in
         ESC[200~/201~ — programs that enable the mode also handle plain
         input fine, and unwrapped keys keep single keystrokes simple. */
    }
  };
  TermEmu.prototype.sgr = function (p) {
    if (!p.length) p = [0];
    var s = this.st;
    for (var i = 0; i < p.length; i++) {
      var c = p[i];
      if (isNaN(c) || c === -1) c = 0;
      /* ★ SGR 38/48 — extended colour (256-colour + truecolor). The
         sub-parameters are consumed here so the outer loop skips them. */
      if ((c === 38 || c === 48) && i + 1 < p.length) {
        var mode = p[i + 1];
        var isFg = (c === 38);
        var col = null;
        if (mode === 5 && i + 2 < p.length) {          // 38;5;n → palette
          var idx = p[i + 2];
          col = (isNaN(idx) || idx < 0 || idx > 255) ? 7 : idx;
          i += 2;
        } else if (mode === 2 && i + 3 < p.length) {   // 38;2;r;g;b → nearest
          var r8 = p[i + 2], g8 = p[i + 3], b8 = p[i + 4];
          col = (isNaN(r8) || isNaN(g8) || isNaN(b8))
            ? 7 : nearestPaletteIndex(
              Math.max(0, Math.min(255, r8 | 0)),
              Math.max(0, Math.min(255, g8 | 0)),
              Math.max(0, Math.min(255, b8 | 0)));
          i += 4;
        } else {
          // Malformed extended colour — skip just the intro pair.
          i += 1;
        }
        if (col !== null) {
          if (isFg) s.fg = 'c' + col; else s.bg = 'c' + col;
        }
      }
      else if (c === 0) s = emuState();
      else if (c === 1) s.bold = true;
      else if (c === 2) s.dim = true;
      else if (c === 3) s.italic = true;
      else if (c === 4) s.underline = true;
      else if (c === 22) { s.bold = false; s.dim = false; }
      else if (c === 23) s.italic = false;
      else if (c === 24) s.underline = false;
      else if (c >= 30 && c <= 37) s.fg = c - 30;
      else if (c === 39) s.fg = null;
      else if (c >= 90 && c <= 97) s.fg = 'b' + (c - 90);
      else if (c >= 40 && c <= 47) s.bg = c - 40;
      else if (c === 49) s.bg = null;
      else if (c >= 100 && c <= 107) s.bg = 'b' + (c - 100);
    }
    this.st = s;
    this._ck = emuKey(s);
    this._cc = emuClasses(s);
  };
  /* ★ T-PTY-8 — TUI TAKEOVER ("fresh desktop window" behaviour).
     Full-screen programs (agentty, ink/rich/blessed-style TUIs) hide the
     hardware cursor the moment they take over, and draw their own "▌"
     as plain text. On a desktop they always start in a NEW empty window;
     here we mimic that: the first time a running PTY program hides the
     cursor (or sends ESC[2J/3J), we wipe the scrollback + banner and
     switch to the full-pane "ceiling" layout so the TUI owns the whole
     terminal — logo on top, status bar pinned at the bottom — exactly
     like the desktop reference. nano/vim are unaffected: they enter the
     alternate screen first, and takeover() never double-fires. */
  TermEmu.prototype.takeover = function () {
    if (this._takeover || this.alt) return;
    if (!running || !termPty) return;   // only real PTY programs take over
    this._takeover = true;
    this.altActive = true;              // reuse ceiling layout + untrimmed render
    if (this.sbHost) { this.sbHost.innerHTML = ''; this.sbHost.style.display = 'none'; }
    for (var tr = 0; tr < this.rows; tr++) this.screen[tr] = this.blankRow();
    this.x = 0; this.y = 0;
    this.progY = null;
    if (termOutput) termOutput.classList.add('m-term-alt');
    this.scheduleRender();
  };
  TermEmu.prototype.freeze = function () {
    this.cursorVisible = false;
    this._blockBurst = 0;
    /* ★ T-PTY-8: when the program ends, hand the pane back. The last
       frame stays on screen as normal scrollable content, but the
       ceiling layout is released so the next command behaves normally. */
    if (this._takeover) {
      this._takeover = false;
      this.altActive = false;
      if (this.sbHost) this.sbHost.style.display = '';
      if (termOutput) termOutput.classList.remove('m-term-alt');
    }
    this.scheduleRender();
  };
  TermEmu.prototype.scheduleRender = function () {
    if (this._raf) return;
    var self = this;
    this._raf = requestAnimationFrame(function () {
      self._raf = 0;
      self.render();
    });
  };
  TermEmu.prototype.render = function () {
    var host = this.host;
    if (!host) return;
    /* ★ v5: find the last row that actually has content (or the
       cursor) and stop there. Empty bottom rows are never painted,
       so there is no blank "noise" zone to scroll into. */
    var lastRow = this.rows - 1;
    var firstRow = 0;
    if (!this.altActive) {
      /* trim trailing blank rows (kept from v5) */
      while (lastRow > 0) {
        var hasContent = (this.cursorVisible && lastRow === this.y);
        if (!hasContent) {
          var rr = this.screen[lastRow];
          for (var cc = 0; cc < this.cols; cc++) {
            if (rr[cc]) { hasContent = true; break; }
          }
        }
        if (hasContent) break;
        lastRow--;
      }
      /* ★ T-PTY-6: trim LEADING blank rows too. TUIs that clear the
      screen and then paint their logo in the middle of it must not
      leave a tall dead zone of empty lines above the picture. */
      while (firstRow < lastRow) {
        var hasTop = (this.cursorVisible && firstRow === this.y);
        if (!hasTop) {
          var rt = this.screen[firstRow];
          for (var ct = 0; ct < this.cols; ct++) {
            if (rt[ct]) { hasTop = true; break; }
          }
        }
        if (hasTop) break;
        firstRow++;
      }
    }
    /* ★ T-PTY-7: sweep leading blank scrollback rows so TUIs that
       scroll with newlines never leave a tall dead zone above the
       session (the "stale look" fix). */
    if (!this.altActive && this.sbHost) {
      var sbNode = this.sbHost.firstChild;
      if (sbNode && sbNode.classList && sbNode.classList.contains('m-term-banner')) {
        sbNode = sbNode.nextSibling;
      }
      while (sbNode) {
        var sbNext = sbNode.nextSibling;
        if (((sbNode.textContent || '').replace(/\s/g, '')) !== '') break;
        this.sbHost.removeChild(sbNode);
        sbNode = sbNext;
      }
    }
    /* ★ STABILITY PASS — STICK-TO-BOTTOM AUTOSCROLL. The old code
       unconditionally jumped to the bottom after every rebuild, so
       scrolling up to read earlier output while a command kept printing
       was impossible. Capture the position BEFORE the rebuild; only snap
       to bottom when we were already within 40px of it, otherwise
       restore the previous offset (content above keeps its place). */
    var stick = true;
    if (termOutput) {
      var so = termOutput.scrollTop;
      var sh = termOutput.scrollHeight;
      var chH = termOutput.clientHeight;
      stick = (sh - (so + chH)) <= 40 || sh <= chH;
      host.innerHTML = '';
      var frag = document.createDocumentFragment();
      for (var r = firstRow; r <= lastRow; r++) {
        frag.appendChild(rowNode(this.screen[r], r === this.y, this.x, this.cursorVisible));
      }
      /* ★ transient progress row: drawn from STATE at the current width,
         never stored in the grid → survives zoom/reflow untouched. */
      if (this.progPayload) frag.appendChild(progRowNode(this.progPayload, this.cols));
      host.appendChild(frag);
      termOutput.scrollTop = stick ? termOutput.scrollHeight : Math.min(so, Math.max(0, termOutput.scrollHeight - chH));
    } else {
      host.innerHTML = '';
      var frag2 = document.createDocumentFragment();
      for (var r2 = firstRow; r2 <= lastRow; r2++) {
        frag2.appendChild(rowNode(this.screen[r2], r2 === this.y, this.x, this.cursorVisible));
      }
      host.appendChild(frag2);
    }
  };

  /* ── The ONE session-wide emulator instance ── */
  var emu = null;
  var flowEl = null, sbEl = null, screenEl = null;
  function ensureEmu() {
    if (emu) return emu;
    if (!termOutput) return null;
    termOutput.innerHTML = '';   // removes the old welcome line
    flowEl = document.createElement('div');
    flowEl.className = 'm-term-flow';
    sbEl = document.createElement('div');
    sbEl.className = 'm-term-sb';
    screenEl = document.createElement('div');
    screenEl.className = 'm-term-screen';
    flowEl.appendChild(sbEl);
    flowEl.appendChild(screenEl);
    termOutput.appendChild(flowEl);
    emu = new TermEmu(screenEl, sbEl, {
      cpr: function (r, c) {
        if (running) sendStdinRaw('\x1b[' + r + ';' + c + 'R');
      }
    });
    return emu;
  }

  /* ═══════════════════════════════════════════════════════════
STARTUP BANNER — PLAIN-TEXT WELCOME (Termux-style).
No box, no card: just coloured text that word-wraps naturally,
so nothing is ever truncated at any zoom or pane width. The
hints are a command + explanation list (soft two-column look:
commands align on the left via a min-width, explanations flow
on the right and wrap on narrow screens — see the CSS).
While the welcome is the only content, #m-term-output carries
.m-term-fresh so it sits centered vertically; the first
submitted command removes the class (see submitCommand) and
the pane behaves like a real bottom-stuck terminal.
═══════════════════════════════════════════════════════════ */
  function renderBanner(info) {
    var E = ensureEmu();
    if (!E || !sbEl) return;
    var phpVer = (info && info.phpVersion) ? 'PHP ' + info.phpVersion : 'PHP';
    var shellName = (info && info.shellLabel) ? String(info.shellLabel).split(' ')[0] : 'sh';
    var metaParts = [phpVer, shellName];
    if (info && info.osFamily) metaParts.push(info.osFamily);
    var now = new Date();
    var pad = function (n) { return (n < 10 ? '0' : '') + n; };
    var dateStr = pad(now.getHours()) + ':' + pad(now.getMinutes());
    var html = ''
      + '<div class="m-term-banner m-term-banner-plain">'
      + '<div class="m-term-banner-title">⚡ QUIRKY TERMINAL <span class="m-term-banner-meta">' + escHtml(metaParts.join(' · ')) + '</span></div>'
      + '<div class="m-term-banner-list">'
      + '<div class="m-term-banner-row"><span class="m-term-banner-cmd">pkg install</span><span class="m-term-banner-desc">install packages (+ dependencies)</span></div>'
      + '<div class="m-term-banner-row"><span class="m-term-banner-cmd">pkg update</span><span class="m-term-banner-desc">refresh the package list</span></div>'
      + '<div class="m-term-banner-row"><span class="m-term-banner-cmd">cd</span><span class="m-term-banner-desc">persists between commands</span></div>'
      + '<div class="m-term-banner-row"><span class="m-term-banner-cmd">↑ / ↓</span><span class="m-term-banner-desc">command history</span></div>'
      + '<div class="m-term-banner-row"><span class="m-term-banner-cmd">Ctrl+C</span><span class="m-term-banner-desc">cancel a running command</span></div>'
      + '</div>'
      + '<div class="m-term-banner-footer">' + escHtml(dateStr) + ' · commands run inside workspace/ only</div>'
      + '</div>';
    var banner = document.createElement('div');
    banner.innerHTML = html;
    sbEl.appendChild(banner.firstChild);
    /* Entry state: center the welcome text and hide the lone block
    cursor until the session has real content of its own. */
    if (termOutput) termOutput.classList.add('m-term-fresh');
    scrollTermBottom();
  }

  /* ═══════════════════════════════════════════════════════════
     STREAMING COMMAND EXECUTION (SSE)
     ═══════════════════════════════════════════════════════════ */
  function submitCommand() {
    if (running) return;
    var E = ensureEmu();
    if (!E) return;
    syncCols();
    var cmd = termInput ? termInput.value.trim() : '';
    if (!cmd) return;
    termInput.value = '';
    // Handle 'clear' locally (no server round-trip needed)
    if (cmd === 'clear' || cmd === 'cls') {
      E.hardReset();
      E.printlnSegs([{ t: '— terminal cleared —', c: 'm-term-info-text' }]);
      pushToHistory(cmd);
      return;
    }
    // Entry card hands the pane over to the session: un-center the
    // flow and reveal the emulated screen before the echo lands.
    if (termOutput) termOutput.classList.remove('m-term-fresh');
    // Echo the command INTO the screen (amber $ + white command)
    E.printlnSegs([
      { t: '$ ', c: 'm-term-echo-prompt' },
      { t: cmd, c: 'm-term-echo-cmd' }
    ]);
    pushToHistory(cmd);
    runNonce = '';   // fresh per-run nonce arrives with SSE 'started'
    runShell = false;
    runPipe = false;
    setRunning(true);
    if (!api || !api.terminal) {
      E.printlnSegs([{ t: 'Terminal API not available.', c: 'm-term-error-text' }]);
      setRunning(false);
      return;
    }
    // ★ v5: on a PTY, open the keyboard right away — every key now
    // flows straight into the program (raw keystroke mode).
    if (termPty && termInput) termInput.focus();
    fetch((window.IDE_STREAM_BASE || 'index.php') + '?api=terminal-stream', {
      method: 'POST',
      credentials: 'include',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'text/event-stream'
      },
      body: JSON.stringify({ command: cmd, stdin: '', cols: ptyColsFor(E.cols), rows: E.rows })
    })
      .then(function (response) {
        if (!response.ok) {
          throw new Error('HTTP ' + response.status);
        }
        return readSSEStream(response, {
          onStarted: function (info) {
            /* ★ per-run nonce — echoed on stdin frames so a stale tab
               can't type into a newer command. */
            runNonce = (info && info.nonce) ? String(info.nonce) : '';
            runShell = true;
          },
          onOut: function (text) {
            if (!runShell) runPipe = true;   // output before 'started' ⇒ pipe stream
            E.clearProg();
            E.write(text);
          },
          onErr: function (text) {
            if (!runShell) runPipe = true;
            E.clearProg();
            E.write(text, 'm-term-error-text');
          },
          onProg: function (payload) {
            if (!runShell) runPipe = true;
            E.showProg(payload);
          },
          onDone: function (info) {
            E.clearProg();
            finishCommand(info, cmd);
          }
        });
      })
      .catch(function (err) {
        E.clearProg();
        E.printlnSegs([{ t: '✗ ' + (err.message || err), c: 'm-term-error-text' }]);
        runNonce = '';
        setRunning(false);
        if (termInput) termInput.focus();
      });
  }

  function finishCommand(info, cmd) {
    runNonce = '';           // this run is over — frames no longer carry its nonce
    if (emu) emu.freeze();   // hide the block cursor
    if (info && info.cwdRelative != null) {
      currentCwdRel = info.cwdRelative;
      updateCwdUI();
    }
    /* ★ Server-side busy reply (another command already running): the
       explanatory line was already printed via 'out'; don't add noise. */
    if (info && info.busy) {
      setRunning(false);
      return;
    }
    /* ★ Termux-style silence on success (STABILITY PASS): a footer line
       "● exit 0 · 123ms" after EVERY command was clutter. Print nothing
       when exitCode === 0 and it didn't time out; keep the footer for
       real failures so errors are never silent. */
    var okExit = info && Number(info.exitCode) === 0 && !info.timedOut;
    if (!okExit) {
      var parts = [];
      if (info && info.timedOut) parts.push('⏱ timed out');
      parts.push('exit ' + (info ? info.exitCode : '?'));
      if (info && info.duration != null) parts.push(info.duration + 'ms');
      if (emu) emu.printlnSegs([{ t: '● ' + parts.join(' · '), c: 'm-term-info-text' }]);
    }
    setRunning(false);
    if (termInput) termInput.focus();
  }

  function readSSEStream(response, handlers) {
    var reader = response.body.getReader();
    var decoder = new TextDecoder();
    var buffer = '';
    function processBlock(block) {
      var event = 'message';
      var data = '';
      block.split('\n').forEach(function (line) {
        if (line.indexOf('event:') === 0) event = line.slice(6).trim();
        else if (line.indexOf('data:') === 0) data += line.slice(5).trim();
      });
      if (data === '') return;   // heartbeat pings ('ping', empty data) land here — harmless
      var payload;
      try { payload = JSON.parse(data); } catch (e) { return; }
      if (event === 'started' && handlers.onStarted) handlers.onStarted(payload);
      else if (event === 'out' && handlers.onOut) handlers.onOut(payload);
      else if (event === 'err' && handlers.onErr) handlers.onErr(payload);
      else if (event === 'prog' && handlers.onProg) handlers.onProg(payload);
      else if (event === 'done' && handlers.onDone) handlers.onDone(payload);
      /* unknown events (future/heartbeat with payload) fall through harmlessly */
    }
    function pump() {
      return reader.read().then(function (result) {
        if (result.done) {
          if (buffer.trim() !== '') processBlock(buffer);
          return;
        }
        buffer += decoder.decode(result.value, { stream: true });
        var idx;
        while ((idx = buffer.indexOf('\n\n')) !== -1) {
          var block = buffer.slice(0, idx);
          buffer = buffer.slice(idx + 2);
          processBlock(block);
        }
        return pump();
      });
    }
    return pump();
  }

  /* ═══════════════════════════════════════════════════════════
     ZOOM CONTROLS (50% – 200%, buttons only)
     ═══════════════════════════════════════════════════════════ */
  function termUpdateZoomUI() {
    var chip = document.getElementById('mterm-zoom-reset');
    var bOut = document.getElementById('mterm-zoom-out');
    var bIn = document.getElementById('mterm-zoom-in');
    if (chip) chip.textContent = termZoomPct + '%';
    if (bOut) {
      bOut.disabled = termZoomPct <= TERM_ZOOM_MIN;
      bOut.style.opacity = termZoomPct <= TERM_ZOOM_MIN ? '0.3' : '1';
    }
    if (bIn) {
      bIn.disabled = termZoomPct >= TERM_ZOOM_MAX;
      bIn.style.opacity = termZoomPct >= TERM_ZOOM_MAX ? '0.3' : '1';
    }
  }
  function termApplyZoom(animate) {
    if (termOutput) {
      termOutput.style.setProperty('--m-term-zoom', String(termZoomPct / 100));
    }
    var chip = document.getElementById('mterm-zoom-reset');
    if (chip && animate !== false) {
      chip.classList.remove('mterm-zoom-pulse');
      void chip.offsetWidth;
      chip.classList.add('mterm-zoom-pulse');
    }
    termUpdateZoomUI();
    try { localStorage.setItem(LS_TERM_ZOOM, String(termZoomPct)); } catch (e) { }
    scheduleLiveResize();   /* ★ T-PTY-7: real-time, also while running */
    scrollTermBottom();
  }
  function termSetZoom(pct) {
    termZoomPct = Math.max(TERM_ZOOM_MIN, Math.min(TERM_ZOOM_MAX, Math.round(pct)));
    termApplyZoom();
  }
  function wireTermZoom() {
    var bOut = document.getElementById('mterm-zoom-out');
    var bIn = document.getElementById('mterm-zoom-in');
    var bReset = document.getElementById('mterm-zoom-reset');
    if (bOut) bOut.addEventListener('click', function () { termSetZoom(termZoomPct - TERM_ZOOM_STEP); });
    if (bIn) bIn.addEventListener('click', function () { termSetZoom(termZoomPct + TERM_ZOOM_STEP); });
    if (bReset) bReset.addEventListener('click', function () {
      if (termZoomPct !== TERM_ZOOM_DEFAULT) {
        termZoomPct = TERM_ZOOM_DEFAULT;
        termApplyZoom();
        toast('Terminal zoom reset to 100%');
      }
    });
    termApplyZoom(false);
  }

  /* ═══════════════════════════════════════════════════════════
     WORD WRAP — ONE UNIFIED POLICY (stability pass)
     Default is now NOWRAP everywhere: the live screen AND the
     scrollback render identically (Termux look), rows already fit
     because the PTY is sized to the pane, and long lines flick
     sideways through the flow container's overflow-x. Wrapping is an
     OPT-IN via the toolbar ↔ toggle (.m-term-wrap), persisted.
     The old split (screen=pre, scrollback=pre-wrap/anywhere) is gone.
     ═══════════════════════════════════════════════════════════ */
  var LS_TERM_WRAP = 'quirky.ide.mobile.termwrap';
  var wrapOn = false;
  /* ★ v10: seed from Settings → Terminal prefs (termFontSize / termWrap).
     The settings card writes these keys; consumers wired post-merge. */
  try {
    var prefFs = parseInt(localStorage.getItem('quirky.ide.settings.termFontSize') || '', 10);
    if (prefFs >= 10 && prefFs <= 22) {
      document.documentElement.style.setProperty(
        '--m-term-base', (prefFs / 16).toFixed(3) + 'rem');
    }
    var prefWrap = localStorage.getItem('quirky.ide.settings.termWrap');
    if (prefWrap === '1' || prefWrap === 'true') wrapOn = true;
  } catch (e) { /* private mode etc. — defaults stand */ }
  function applyWordWrap() {
    if (!termOutput) return;
    termOutput.classList.toggle('m-term-wrap', wrapOn);
    var btn = document.getElementById('mterm-wrap');
    if (btn) {
      btn.style.opacity = wrapOn ? '1' : '';
      btn.style.color = wrapOn ? 'var(--accent, #f5a524)' : '';
    }
  }
  function setWordWrap(on) {
    wrapOn = !!on;
    applyWordWrap();
    try { localStorage.setItem(LS_TERM_WRAP, wrapOn ? '1' : '0'); } catch (e) { }
    toast(wrapOn ? 'Terminal: soft wrap ON' : 'Terminal: nowrap (flick sideways)');
  }
  function loadWordWrap() {
    try {
      /* Legacy value '0' meant nowrap too, so only '1' opts into wrap. */
      wrapOn = localStorage.getItem(LS_TERM_WRAP) === '1';
    } catch (e) { wrapOn = false; }
    applyWordWrap();
  }
  /* Inject the wrap toggle into the toolbar (toolbar DOM belongs to a
     view file we don't own, so the button is created here). */
  function wireWrapToggle() {
    var bar = document.getElementById('m-term-toolbar');
    if (!bar || document.getElementById('mterm-wrap')) return;
    var copyBtn = document.getElementById('mterm-copy');
    var btn = document.createElement('button');
    btn.className = 'mterm-btn';
    btn.id = 'mterm-wrap';
    btn.title = 'Toggle word wrap';
    btn.setAttribute('aria-label', 'Toggle terminal word wrap');
    btn.textContent = '↔';
    btn.addEventListener('click', function () { setWordWrap(!wrapOn); });
    if (copyBtn && copyBtn.nextSibling) {
      bar.insertBefore(btn, copyBtn.nextSibling);
    } else {
      bar.insertBefore(btn, bar.firstChild);
    }
    applyWordWrap(); // reflect persisted state on the fresh button
  }

  /* ═══════════════════════════════════════════════════════════
     TOOLBAR CONTROLS
     ═══════════════════════════════════════════════════════════ */
  function clearTerminal() {
    var E = ensureEmu();
    if (!E) return;
    E.hardReset();
    E.printlnSegs([{ t: '— terminal cleared —', c: 'm-term-info-text' }]);
  }
  function copySession() {
    var text = termOutput ? termOutput.innerText : '';
    if (U && U.copyToClipboard) {
      U.copyToClipboard(text).then(function (ok) {
        toast(ok ? '📋 Session copied' : '⚠ Copy failed');
      });
    } else {
      toast('Clipboard not available');
    }
  }
  function cancelRunning() {
    if (!running) return;
    if (api && api.terminal) {
      api.terminal.cancel().then(function () {
        setTimeout(function () {
          if (running) {
            if (emu) emu.printlnSegs([{ t: '● cancelled', c: 'm-term-info-text' }]);
            setRunning(false);
          }
        }, 1500);
      }).catch(function () { });
    }
  }

  /* ═══════════════════════════════════════════════════════════
     INTERACTIVE STDIN
     ═══════════════════════════════════════════════════════════
     PIPE MODE  : sendStdinLine() — type a whole line, Enter sends
                  it plus a newline (old behaviour, still needed
                  when no PTY is available).
     PTY MODE   : raw keystrokes — every key is forwarded the
                  instant it happens (see beforeinput handler in
                  init). No newline is added by us; the Enter key
                  itself sends \r, Backspace sends \x7f.        */
  /* ★ Wrap a payload in the per-run nonce frame ("\x00Q<nonce>\x00").
     Stripped server-side before the bytes reach the program; lets the
     backend drop keystrokes from a stale tab steering a newer command.
     Rides through the existing terminal-stdin route untouched. */
  function nonceFrame(text) {
    return runNonce ? ('\x00Q' + runNonce + '\x00' + text) : text;
  }
  function sendStdinLine() {
    var text = termInput ? termInput.value : '';
    if (termInput) termInput.value = '';
    if (!termPty && emu) {
      emu.printlnSegs([
        { t: '› ', c: 'm-term-stdin-prompt' },
        { t: text, c: 'm-term-stdin-text' }
      ]);
    }
    if (api && api.terminal && api.terminal.sendStdin) {
      api.terminal.sendStdin(nonceFrame(text + '\n')).catch(function (err) {
        if (emu) emu.printlnSegs([{ t: '✗ stdin: ' + (err && err.message ? err.message : err), c: 'm-term-error-text' }]);
      });
    }
    if (termInput) termInput.focus();
  }
  /** ★ Phase T-PTY-4: turn "x" into the byte Ctrl+X produces (\x18),
    "c" into \x03, etc., so nano/vim receive REAL control keys.
    Returns null when the character has no Ctrl mapping. */
  function ctrlCode(text) {
    var ch = String(text || '').charAt(0);
    if (!ch) return null;
    var c = ch.charCodeAt(0);
    if (c >= 65 && c <= 90) return String.fromCharCode(c - 64);   // A-Z
    if (c >= 97 && c <= 122) return String.fromCharCode(c - 96);  // a-z
    var sym = {
      '@': '\x00', ' ': '\x00', '[': '\x1b', '\\': '\x1c',
      ']': '\x1d', '^': '\x1e', '_': '\x1f', '?': '\x7f',
      '/': '\x1f', '-': '\x1f'
    };
    return (ch in sym) ? sym[ch] : null;
  }
  function sendStdinRaw(text) {
    if (!text) return;
    if (api && api.terminal && api.terminal.sendStdin) {
      api.terminal.sendStdin(nonceFrame(text)).catch(function () { });
    }
  }
  function handleSend() {
    if (running) {
      // PTY raw mode: the send button is hidden (CSS) and the
      // Enter key is handled by the keydown/beforeinput hooks.
      if (!termPty) sendStdinLine();
    } else {
      submitCommand();
    }
  }
  function setRunning(r) {
    running = r;
    if (termInput) termInput.disabled = false;
    if (termCancelBtn) termCancelBtn.style.display = r ? '' : 'none';
    /* ★ ZOOM/RESIZE RACE FIX: while a PTY command runs the font-size
       transition is disabled entirely (.m-term-running → transition:none),
       so measureCols() can never sample a mid-animation width. */
    if (termOutput) termOutput.classList.toggle('m-term-running', !!r);
    if (termInputLine) termInputLine.classList.toggle('m-term-raw', r && termPty);
    if (termInput) {
      termInput.placeholder = (r && termPty)
        ? '⌨ keys go straight to the program…'
        : 'Type a command…';
    }
    if (termStatus) {
      termStatus.textContent = r
        ? (termPty ? '⏳ running… keys go straight to the program' : '⏳ running… (type + Enter sends input)')
        : '';
    }
    if (termPrompt) termPrompt.textContent = r ? '›' : '$';
    if (termSendBtn) termSendBtn.textContent = r ? '⏎' : '➤';
  }

  /* ═══════════════════════════════════════════════════════════
     SERVER INFO (banner data + initial CWD)
     ═══════════════════════════════════════════════════════════ */
  function maybeFetchTermInfo() {
    if (termInfoFetched) return;
    termInfoFetched = true;
    if (api && api.terminal) {
      api.terminal.getInfo().then(function (info) {
        termPty = !!(info && info.pty);
        if (info.cwdRelative != null) {
          currentCwdRel = info.cwdRelative;
          updateCwdUI();
        }
        renderBanner(info);
      }).catch(function () {
        renderBanner(null);
      });
    } else {
      renderBanner(null);
    }
  }

  /* ═══════════════════════════════════════════════════════════
     TAB-SHOWN HOOK
     ═══════════════════════════════════════════════════════════ */
  function onTabShown() {
    if (!terminalEnabled()) return;
    ensureEmu();
    maybeFetchTermInfo();
    requestAnimationFrame(function () {
      syncCols();   // ★ never start a session with stale viewport metrics
      if (termInput && !running) termInput.focus();
    });
  }

  /* ═══════════════════════════════════════════════════════════
     INIT (lazy — first Terminal-tab visit, idempotent)
     ═══════════════════════════════════════════════════════════ */
  function init() {
    if (inited) return;
    inited = true;
    if (!terminalEnabled()) return;
    if (!termOutput || !termInput) return;
    history = loadHistory();
    loadWordWrap();
    if (termSendBtn) termSendBtn.addEventListener('click', handleSend);
    if (termInput) {
      termInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
          e.preventDefault();
          if (running && termPty) {
            sendStdinRaw('\r');   // real newline keypress
          } else {
            handleSend();
          }
          return;
        }
        if (!running && e.key === 'ArrowUp') { e.preventDefault(); historyUp(); }
        else if (!running && e.key === 'ArrowDown') { e.preventDefault(); historyDown(); }
      });
      /* ★ v5 RAW KEYSTROKE MODE — the heart of the nano fix.
         While a PTY program runs, the box never stores text:
         each edit is intercepted and forwarded as a keypress. */
      termInput.addEventListener('beforeinput', function (e) {
        if (!running || !termPty) return;   // idle / pipe mode: normal editing
        var t = e.inputType;
        if (t === 'insertText' || t === 'insertCompositionText' || t === 'insertReplacementText') {
          if (e.data) {
            /* ★ Honor the helper strip's Ctrl / Alt toggles so
               Ctrl + x (lower or UPPER case) reaches nano as the
               real control byte \x18 instead of a literal "x". */
            var KB = window.IDE && window.IDE.mobileKeyboard;
            var mods = (KB && KB.consumeModifiers) ? KB.consumeModifiers() : null;
            if (mods && mods.ctrl) {
              var cc = ctrlCode(e.data);
              sendStdinRaw(cc !== null ? cc : e.data);
            } else if (mods && mods.alt) {
              sendStdinRaw('\x1b' + e.data);   // Alt = ESC prefix
            } else {
              sendStdinRaw(e.data);
            }
          }
          e.preventDefault();
        } else if (t === 'deleteContentBackward') {
          sendStdinRaw('\x7f');           // real Backspace
          e.preventDefault();
        } else if (t === 'deleteContentForward') {
          sendStdinRaw('\x1b[3~');        // real Forward-Delete
          e.preventDefault();
        } else if (t === 'insertLineBreak' || t === 'insertParagraph') {
          sendStdinRaw('\r');             // real Enter
          e.preventDefault();
        } else {
          e.preventDefault();             // block everything else
        }
      });
    }
    if (termClearBtn) termClearBtn.addEventListener('click', clearTerminal);
    if (termCopyBtn) termCopyBtn.addEventListener('click', copySession);
    if (termCancelBtn) termCancelBtn.addEventListener('click', cancelRunning);
    wireTermZoom();
    wireWrapToggle();
    updateCwdUI();
    window.addEventListener('resize', function () {
      scheduleLiveResize();   /* ★ T-PTY-7: real-time, no more "next command" */
    });
    /* ★ ZOOM/RESIZE RACE FIX: re-measure once the font-size transition has
       fully settled (belt-and-braces with the 260 ms debounce). */
    if (termOutput) {
      termOutput.addEventListener('transitionend', function () {
        scheduleLiveResize();
      });
    }
  }

  /* ═══════════════════════════════════════════════════════════
     EXPOSE
     ═══════════════════════════════════════════════════════════ */
  window.IDE = window.IDE || {};
  /**
   * @namespace window.IDE.mobileTerminal
   * Mobile terminal module (single-screen VT100 emulator, raw PTY
   * keystrokes, streaming, history, CWD, banner, zoom, wrap).
   */
  window.IDE.mobileTerminal = {
    init: init,
    onTabShown: onTabShown,
    clear: clearTerminal,
    copySession: copySession,
    cancel: cancelRunning,
    isRunning: function () { return running; },
    isPty: function () { return termPty; },
    getCwd: function () { return currentCwdRel; },
    sendStdinRaw: sendStdinRaw,
    sendLine: sendStdinLine,
    setWordWrap: setWordWrap,
    /**
     * Programmatically run a command (used by the Run button).
     */
    runCommand: function (cmd) {
      if (!termInput) return;
      if (running) {
        toast('⚠ Terminal busy — wait for the current command to finish');
        return;
      }
      termInput.value = cmd;
      submitCommand();
    }
  };
})();