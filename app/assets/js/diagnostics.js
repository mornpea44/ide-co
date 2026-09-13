/**
* ═══════════════════════════════════════════════════════════════════════════
*  QUIRKY IDE — DIAGNOSTICS / ERROR LENS v2 (MAJOR REWRITE)
* ═══════════════════════════════════════════════════════════════════════════
*
*  Two detection layers, merged into one marker list:
*
*   1. SERVER LINT  — ?api=lint (php -l / py_compile / node --check) via
*      api.lint.check(), with stale-request cancellation (AbortController).
*      Raw engine messages are enriched into plain English (explain + fix).
*
*   2. CLIENT RULES — a fast, dependency-free static-analysis engine that
*      runs on every device (no toolchain needed — C/Java/CSS/HTML/SQL get
*      real diagnostics even though nothing can compile them on-device):
*        • structural: bracket/tag balance, JSON parse, mixed indentation
*        • language rules: deprecated APIs, coercion bugs, unsafe calls,
*          bare array keys, assignment-in-condition, unknown CSS props,
*          invalid hex colors, missing alt, duplicate ids, dangerous SQL…
*      Every rule marker carries a human `explain` and an actionable `fix`.
*
*  Rendering goes ONLY through the editor-adapter intel facade
*  (setDiagnostics / clearDiagnostics), so CM5 and CM6 both work.
*  The action-bar 📋 button (#mab-diag) becomes a live status ring and
*  opens the full report overlay.
*
*  EXPOSES: window.IDE.diagnostics =
*    { refresh, clear, getMarkers, openReport, closeReport, isOpen }
* ═══════════════════════════════════════════════════════════════════════════
*/
(function () {
  'use strict';

  window.IDE = window.IDE || {};
  var U = window.IDE.utils || null;
  var API = window.IDE.api || null;
  var LANG = window.IDE.language || null;

  var featureOn = !!(window.IDE_CONFIG && window.IDE_CONFIG.features &&
    window.IDE_CONFIG.features.lint);

  var DEBOUNCE_MS = 700;      // live re-lint pause after typing
  var MAX_MARKERS = 300;      // phone-first cap
  var RULE_CHAR_CAP = 250000;   // skip client rules above this size (server still runs)
  var LS_LIVE = 'quirky.ide.settings.diagLive';

  /* Live checking can be soft-disabled without touching features.json */
  var liveOn = (function () {
    try {
      var raw = localStorage.getItem(LS_LIVE);
      return raw === null ? true : (raw === '1' || raw === 'true');
    } catch (e) { return true; }
  })();

  var cmRef = null;
  var current = { path: null, ext: null, langId: null, markers: [] };
  var debounceTimer = null;
  var lintController = null;
  var runSeq = 0;
  var overlayOpen = false;
  var activeFilter = 'all';
  var btnEl = null;

  /* ═══════════════════════════════════════════════════════════════
  SMALL HELPERS
  ═══════════════════════════════════════════════════════════════ */
  function toast(msg, type) {
    if (U && U.toast) U.toast(msg, type || 'info');
  }
  function esc(s) {
    if (U && U.escapeHtml) return U.escapeHtml(s);
    return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  }
  function langIdFor(ext) {
    if (LANG && typeof LANG.byExt === 'function') {
      try { return LANG.byExt(ext).id; } catch (e) { return 'text'; }
    }
    return 'text';
  }
  function serverLangFor(ext) {
    if (LANG && typeof LANG.lintSource === 'function') {
      try { return LANG.lintSource(ext); } catch (e) { return null; }
    }
    return null;
  }
  function normSev(s) {
    s = String(s || '').toLowerCase();
    if (s.indexOf('error') !== -1 || s.indexOf('fatal') !== -1) return 'error';
    if (s.indexOf('warn') !== -1) return 'warning';
    return 'info';
  }
  function mk(line, ch, length, severity, message, explain, fix, source) {
    return {
      line: Math.max(0, line | 0),
      ch: Math.max(0, ch | 0),
      length: Math.max(1, length || 1),
      severity: severity,
      message: String(message || 'Problem detected'),
      explain: explain || '',
      fix: fix || '',
      source: source || 'rules'
    };
  }

  /* ═══════════════════════════════════════════════════════════════
  MASKING — blank out comments + string CONTENTS (same length),
  so every rule/regex below only ever sees real code. Quotes stay,
  their contents become spaces. Returns the masked text.
  ═══════════════════════════════════════════════════════════════ */
  var MASK_PROFILE = {
    php: { line: '//', block: ['/*', '*/'] },
    javascript: { line: '//', block: ['/*', '*/'] },
    typescript: { line: '//', block: ['/*', '*/'] },
    json: { line: null, block: null },
    c: { line: '//', block: ['/*', '*/'] },
    cpp: { line: '//', block: ['/*', '*/'] },
    java: { line: '//', block: ['/*', '*/'] },
    csharp: { line: '//', block: ['/*', '*/'] },
    kotlin: { line: '//', block: ['/*', '*/'] },
    go: { line: '//', block: ['/*', '*/'] },
    rust: { line: '//', block: ['/*', '*/'] },
    swift: { line: '//', block: ['/*', '*/'] },
    dart: { line: '//', block: ['/*', '*/'] },
    css: { line: null, block: ['/*', '*/'] },
    scss: { line: '//', block: ['/*', '*/'] },
    less: { line: '//', block: ['/*', '*/'] },
    python: { line: '#', block: null },
    shell: { line: '#', block: null },
    ruby: { line: '#', block: null },
    perl: { line: '#', block: null },
    yaml: { line: '#', block: null },
    sql: { line: '--', block: null },
    lua: { line: '--', block: ['--[[', ']]'] },
    ini: { line: '#', block: null }
  };

  function maskNonCode(text, langId) {
    var prof = MASK_PROFILE[langId];
    if (!prof || (!prof.line && !prof.block)) return text;
    var out = text.split('');
    var n = text.length, i = 0;
    var inBlock = false, inStr = null;
    var lineC = prof.line, bOpen = prof.block ? prof.block[0] : null, bClose = prof.block ? prof.block[1] : null;

    while (i < n) {
      var c = text[i];
      if (inBlock) {
        if (bClose && text.substr(i, bClose.length) === bClose) {
          for (var k = 0; k < bClose.length; k++) out[i + k] = ' ';
          i += bClose.length; inBlock = false; continue;
        }
        if (c === '\n') out[i] = '\n'; else out[i] = ' ';
        i++; continue;
      }
      if (inStr) {
        if (c === '\\' && i + 1 < n) { out[i] = ' '; out[i + 1] = ' '; i += 2; continue; }
        if (c === inStr) { inStr = null; i++; continue; }
        if (c === '\n') { inStr = null; i++; continue; } // unterminated string → bail for this line
        out[i] = ' '; i++; continue;
      }
      if (bOpen && text.substr(i, bOpen.length) === bOpen) {
        for (var k2 = 0; k2 < bOpen.length; k2++) out[i + k2] = ' ';
        i += bOpen.length; inBlock = true; continue;
      }
      if (lineC && text.substr(i, lineC.length) === lineC) {
        /* lua '--' is also the block opener start — mask code handles
           block first above, so plain '--' here is a line comment */
        while (i < n && text[i] !== '\n') { out[i] = ' '; i++; }
        continue;
      }
      if (c === '"' || c === "'" || (c === '`' && (langId === 'javascript' || langId === 'typescript'))) {
        inStr = c; i++; continue;
      }
      i++;
    }
    return out.join('');
  }

  /* ═══════════════════════════════════════════════════════════════
  STRUCTURAL CHECKS
  ═══════════════════════════════════════════════════════════════ */
  var BRACKET_LANGS = {
    php: 1, javascript: 1, typescript: 1, c: 1, cpp: 1, java: 1, csharp: 1,
    kotlin: 1, go: 1, rust: 1, swift: 1, dart: 1, css: 1, scss: 1, less: 1,
    json: 1, sql: 1, lua: 1, perl: 1
  };

  function bracketScan(masked, langId, lineStarts) {
    if (!BRACKET_LANGS[langId]) return [];
    var out = [];
    var open = { '(': ')', '[': ']', '{': '}' };
    var close = { ')': '(', ']': '[', '}': '{' };
    var stack = [];
    for (var i = 0; i < masked.length && out.length < 20; i++) {
      var c = masked[i];
      if (open[c]) { stack.push({ ch: c, at: i }); continue; }
      if (close[c]) {
        var top = stack.pop();
        if (!top || top.ch !== close[c]) {
          var p = posAt(lineStarts, i);
          out.push(mk(p.line, p.ch, 1, 'error',
            'Unmatched "' + c + '" — no matching "' + (top ? top.ch : close[c]) + '" before it.',
            'This closing bracket has nothing to close. Every "' + c + '" needs a "' + (close[c]) + '" opened earlier.',
            'Delete the stray "' + c + '" or add the missing "' + (close[c]) + '" before it.',
            'structure'));
          if (top) stack.push(top); // resync
        }
      }
    }
    for (var j = 0; j < stack.length && out.length < 20; j++) {
      var o = stack[j], p2 = posAt(lineStarts, o.at);
      out.push(mk(p2.line, p2.ch, 1, 'error',
        '"' + o.ch + '" opened here is never closed.',
        'The file ends while this bracket is still open — usually a missing "' + open[o.ch] + '" near the end of the file.',
        'Add "' + open[o.ch] + '" where the block should end.',
        'structure'));
    }
    return out;
  }

  var VOID_TAGS = { area: 1, base: 1, br: 1, col: 1, embed: 1, hr: 1, img: 1, input: 1, link: 1, meta: 1, param: 1, source: 1, track: 1, wbr: 1 };

  function htmlTagScan(text, lineStarts) {
    var out = [];
    var cleaned = text.replace(/<!--[\s\S]*?-->/g, function (m) {
      return m.replace(/[^\n]/g, ' ');
    });
    var re = /<\/?([a-zA-Z][a-zA-Z0-9-]*)\b[^>]*?(\/?)>/g;
    var m, stack = [];
    while ((m = re.exec(cleaned)) !== null && out.length < 15) {
      var tag = m[1].toLowerCase();
      if (VOID_TAGS[tag] || m[2] === '/') continue;
      if (m[0].charAt(1) === '/') {
        var top = stack.pop();
        if (!top || top.tag !== tag) {
          var p = posAt(lineStarts, m.index);
          out.push(mk(p.line, p.ch, m[0].length, 'warning',
            '</' + tag + '> closes nothing' + (top ? ' — did you mean </' + top.tag + '>?' : '.'),
            'Closing tags must match the most recently opened tag.',
            top ? 'Change it to </' + top.tag + '> or close "' + tag + '" in the right order.' : 'Remove this closing tag.',
            'html'));
          if (top) stack.push(top);
        }
      } else {
        stack.push({ tag: tag, at: m.index, len: m[0].length });
      }
    }
    for (var i = 0; i < stack.length && out.length < 15; i++) {
      var o = stack[i], p2 = posAt(lineStarts, o.at);
      out.push(mk(p2.line, p2.ch, o.len, 'warning',
        '<' + o.tag + '> is never closed.',
        'Browsers will try to guess where it ends — that guess is often wrong and breaks the layout below it.',
        'Add </' + o.tag + '> where the element should end.',
        'html'));
    }
    /* missing alt on images */
    var imgRe = /<img\b[^>]*>/gi, im;
    while ((im = imgRe.exec(cleaned)) !== null && out.length < 25) {
      if (!/\balt\s*=/i.test(im[0])) {
        var p3 = posAt(lineStarts, im.index);
        out.push(mk(p3.line, p3.ch, im[0].length, 'info',
          '<img> without an alt attribute.',
          'Screen readers and search engines rely on alt text; it also shows when the image fails to load.',
          'Add alt="description" (or alt="" for purely decorative images).',
          'html'));
      }
    }
    /* duplicate ids */
    var idRe = /\bid\s*=\s*["']([^"']+)["']/gi, seen = {}, dm;
    while ((dm = idRe.exec(cleaned)) !== null) {
      var v = dm[1];
      if (seen[v] === 1) {
        var p4 = posAt(lineStarts, dm.index);
        out.push(mk(p4.line, p4.ch, dm[0].length, 'warning',
          'Duplicate id "' + v + '".',
          'IDs must be unique — getElementById and CSS #selectors will only ever see the first one.',
          'Rename one of them, or use a class if the style repeats.',
          'html'));
        seen[v] = 2;
      } else if (!seen[v]) seen[v] = 1;
    }
    /* _blank without rel */
    var blankRe = /<a\b[^>]*target\s*=\s*["']_blank["'][^>]*>/gi, bm;
    while ((bm = blankRe.exec(cleaned)) !== null && out.length < 30) {
      if (!/\brel\s*=/i.test(bm[0])) {
        var p5 = posAt(lineStarts, bm.index);
        out.push(mk(p5.line, p5.ch, bm[0].length, 'warning',
          'target="_blank" without rel="noopener".',
          'The opened page can redirect YOUR page (tab-nabbing) unless the link is cut loose.',
          'Add rel="noopener noreferrer" to the link.',
          'html'));
      }
    }
    return out;
  }

  function jsonCheck(text, lineStarts) {
    try { JSON.parse(text); return []; }
    catch (e) {
      var msg = (e && e.message) || 'Invalid JSON';
      var mPos = /position\s+(\d+)/i.exec(msg);
      var line = 0, ch = 0;
      if (mPos) { var p = posAt(lineStarts, Math.min(+mPos[1], text.length)); line = p.line; ch = p.ch; }
      var explain = 'The file is not valid JSON, so nothing that reads it (APIs, configs, the IDE itself) can parse it.';
      var fix = 'Check for missing commas/quotes and trailing commas — JSON is stricter than JavaScript.';
      if (/position/i.test(msg)) explain = 'The parser gave up exactly at the marked position.';
      return [mk(line, ch, 1, 'error', msg, explain, fix, 'json')];
    }
  }

  /* ═══════════════════════════════════════════════════════════════
  RULE TABLES — each rule fires on MASKED code only
  ═══════════════════════════════════════════════════════════════ */
  var RULES = {
    javascript: [
      {
        re: /[^=!<>+\-*\/%&|^]\s*(==|!=)\s*[^=]/g, sev: 'warning',
        msg: function (m) { return '"' + m[1] + '" compares with type coercion.'; },
        explain: '== considers 0 == "" and null == undefined as true, which causes subtle bugs.',
        fix: 'Use === / !== to compare value AND type.'
      },
      {
        re: /\b(if|while|for|switch)\s*\([^)]*[^=!<>]=[^=][^)]*\)/g, sev: 'warning',
        msg: 'Assignment (=) inside a condition.',
        explain: 'A single = WRITES a value and is always "truthy" — the check never does what it looks like.',
        fix: 'Use == or === to compare.'
      },
      {
        re: /\bvar\s+/g, sev: 'info',
        msg: 'Legacy "var" declaration.',
        explain: 'var is function-scoped and hoisted; it is the #1 source of "why is this variable wrong?" bugs.',
        fix: 'Prefer let (reassignable) or const (fixed).'
      },
      {
        re: /\bconsole\.(log|debug)\s*\(/g, sev: 'info',
        msg: 'console.log left in code.',
        explain: 'Harmless, but debug output usually should not ship.',
        fix: 'Remove it before publishing, or guard it behind a debug flag.'
      },
      {
        re: /\bparseInt\s*\(\s*[^,)]+\s*\)/g, sev: 'warning',
        msg: 'parseInt() without a radix.',
        explain: 'Without the base argument, some engines historically guessed octal/hex from the string.',
        fix: 'Pass the base explicitly: parseInt(x, 10).'
      },
      {
        re: /\bdocument\.write\s*\(/g, sev: 'warning',
        msg: 'document.write() is deprecated.',
        explain: 'It blocks parsing, breaks with deferred scripts, and can wipe the whole page after load.',
        fix: 'Build nodes with createElement/innerHTML or a template instead.'
      },
      {
        re: /\balert\s*\(/g, sev: 'info',
        msg: 'Blocking alert() dialog.',
        explain: 'alert freezes the tab until dismissed and cannot be styled.',
        fix: 'Use a toast/modal UI instead.'
      }
    ],
    php: [
      {
        re: /\bmysql_(query|connect|fetch_assoc|fetch_array|fetch_row|fetch_object|num_rows|result|close|select_db|error|insert_id|affected_rows|real_escape_string)\s*\(/g,
        sev: 'error',
        msg: function (m) { return 'mysql_' + m[1] + '() was removed in PHP 7.'; },
        explain: 'The old mysql_* API no longer exists — this code will fatal-error on any modern PHP.',
        fix: 'Switch to mysqli_* or PDO with prepared statements.'
      },
      {
        re: /\b(ereg|ereg_replace|eregi|eregi_replace|split|spliti)\s*\(/g, sev: 'warning',
        msg: function (m) { return m[1] + '() is deprecated/removed.'; },
        explain: 'POSIX regex functions were removed; PCRE is the maintained engine.',
        fix: 'Use preg_match / preg_replace / preg_split.'
      },
      {
        re: /\bcreate_function\s*\(/g, sev: 'warning',
        msg: 'create_function() is deprecated.',
        explain: 'It evals a string — slow and unsafe.',
        fix: 'Use a closure: function ($x) { … }.'
      },
      {
        re: /<\?(?!=|php|xml)/g, sev: 'warning',
        msg: 'Short open tag "<?".',
        explain: 'Short tags are disabled on many servers, so the file would render as plain text.',
        fix: 'Use <?php or <?= (echo short tag, always available).'
      },
      {
        re: /\$[A-Za-z_]\w*\[\s*[A-Za-z_]\w*\s*\]/g, sev: 'warning',
        msg: 'Unquoted array key.',
        explain: '$arr[key] treats key as a CONSTANT; PHP only falls back to the string with a warning, and it breaks the day a constant with that name exists.',
        fix: "Quote it: $arr['key']."
      },
      {
        re: /;;/g, sev: 'info',
        msg: 'Double semicolon ";;".',
        explain: 'The second semicolon is an empty statement — almost always a typo.',
        fix: 'Delete the extra ";".'
      },
      {
        re: /\bsizeof\s*\(/g, sev: 'info',
        msg: 'sizeof() is just an alias of count().',
        explain: 'It works, but count() is the conventional, clearer name.',
        fix: 'Use count().'
      }
    ],
    python: [
      {
        re: /^[ \t]*except\s*:[ \t]*$/gm, sev: 'warning',
        msg: 'Bare "except:" catches everything.',
        explain: 'It swallows KeyboardInterrupt, SystemExit and real bugs, making failures invisible.',
        fix: 'Catch a concrete type: except ValueError: (or except Exception: at minimum).'
      },
      {
        re: /\bdef\s+\w+\s*\([^)]*=\s*(\[\]|\{\})/g, sev: 'warning',
        msg: 'Mutable default argument.',
        explain: 'The list/dict is created ONCE and shared by every call — later calls see earlier mutations.',
        fix: 'Use None as default and create the collection inside the function.'
      },
      {
        re: /\bprint\s+[^\s(]/g, sev: 'error',
        msg: 'Python 2 print statement.',
        explain: 'In Python 3 print is a function — this is a SyntaxError.',
        fix: 'Add parentheses: print(value).'
      },
      {
        re: /\bxrange\s*\(/g, sev: 'error', msg: 'xrange() was removed in Python 3.',
        explain: 'Python 3 merged it into range().', fix: 'Use range().'
      },
      {
        re: /\.has_key\s*\(/g, sev: 'error', msg: '.has_key() was removed in Python 3.',
        explain: 'Dicts lost has_key() in the 2→3 migration.', fix: 'Use "key in dict".'
      },
      {
        re: /\.(iteritems|itervalues|iterkeys)\s*\(/g, sev: 'error',
        msg: function (m) { return '.' + m[1] + '() was removed in Python 3.'; },
        explain: 'The iter* dict methods are gone.', fix: 'Use .items() / .values() / .keys().'
      },
      {
        re: /<>/g, sev: 'error', msg: '<> is not a Python operator.',
        explain: 'Not-equal is written != in Python 3.', fix: 'Use !=.'
      }
    ],
    c: [
      {
        re: /\bgets\s*\(/g, sev: 'error',
        msg: 'gets() is banned — buffer overflow.',
        explain: 'gets() cannot know the buffer size, so any long input smashes the stack. It was removed from the C standard.',
        fix: 'Use fgets(buf, sizeof buf, stdin).'
      },
      {
        re: /\bvoid\s+main\s*\(/g, sev: 'warning',
        msg: 'void main().',
        explain: 'The standard requires main to return int so the OS can read the exit status.',
        fix: 'Use int main(void) and return 0;'
      },
      {
        re: /\b(if|while)\s*\([^)]*[^=!<>]=[^=][^)]*\)/g, sev: 'warning',
        msg: 'Assignment (=) inside a condition.',
        explain: 'if (a = b) assigns b to a and tests the new value — a classic C bug.',
        fix: 'Use == to compare.'
      },
      {
        re: /\bscanf\s*\(\s*"[^"]*%s/g, sev: 'warning',
        msg: 'scanf("%s") without a width limit.',
        explain: 'Unbounded %s overflows the buffer on long input.',
        fix: 'Limit it: scanf("%99s", buf) for a 100-byte buffer.'
      }
    ],
    java: [
      {
        re: /"[^"]*"\s*==|==\s*"[^"]*"/g, sev: 'warning',
        msg: 'String compared with ==.',
        explain: '== compares object identity, not text — two identical strings from different sources are usually NOT ==.',
        fix: 'Use a.equals(b) (or Objects.equals(a, b) when either can be null).'
      },
      {
        re: /\b(if|while)\s*\([^)]*[^=!<>]=[^=][^)]*\)/g, sev: 'warning',
        msg: 'Assignment (=) inside a condition.',
        explain: 'A single = writes a value; the condition then tests whatever was assigned.',
        fix: 'Use == to compare.'
      }
    ],
    css: [
      {
        re: /!important/g, sev: 'info',
        msg: '!important used.',
        explain: 'It wins against normal specificity, so future overrides need even more !important — an arms race.',
        fix: 'Increase selector specificity instead, or re-order the cascade.'
      }
    ],
    sql: [
      {
        re: /\bSELECT\s+\*/i, sev: 'info',
        msg: 'SELECT * fetches every column.',
        explain: 'It wastes memory/bandwidth and silently breaks when the table schema changes.',
        fix: 'List only the columns you actually use.'
      }
    ]
  };
  RULES.typescript = RULES.javascript;
  RULES.cpp = RULES.c.concat([
    {
      re: /\busing\s+namespace\s+std\s*;/g, sev: 'info',
      msg: '"using namespace std;" in file scope.',
      explain: 'It dumps the whole std namespace into global scope and causes name collisions as the project grows.',
      fix: 'Use std:: explicitly (or selective using-declarations).'
    },
    {
      re: /\bstd::endl\b/g, sev: 'info',
      msg: 'std::endl flushes every time.',
      explain: 'endl writes a newline AND forces a flush — slow in loops.',
      fix: "Prefer '\\n' unless you need the flush."
    }
  ]);

  /* ── CSS property dictionary (unknown-prop detection) ── */
  var CSS_PROPS = ('align-content,align-items,align-self,all,animation,animation-delay,animation-direction,' +
    'animation-duration,animation-fill-mode,animation-iteration-count,animation-name,animation-play-state,' +
    'animation-timing-function,aspect-ratio,backdrop-filter,backface-visibility,background,background-attachment,' +
    'background-blend-mode,background-clip,background-color,background-image,background-origin,background-position,' +
    'background-position-x,background-position-y,background-repeat,background-size,block-size,border,border-block,' +
    'border-block-color,border-block-end,border-block-start,border-block-style,border-block-width,border-bottom,' +
    'border-bottom-color,border-bottom-left-radius,border-bottom-right-radius,border-bottom-style,border-bottom-width,' +
    'border-collapse,border-color,border-image,border-inline,border-left,border-left-color,border-left-style,' +
    'border-left-width,border-radius,border-right,border-right-color,border-right-style,border-right-width,' +
    'border-spacing,border-style,border-top,border-top-color,border-top-left-radius,border-top-right-radius,' +
    'border-top-style,border-top-width,border-width,bottom,box-decoration-break,box-shadow,box-sizing,break-after,' +
    'break-before,break-inside,caption-side,caret-color,clear,clip,color,column-count,column-fill,column-gap,' +
    'column-rule,column-rule-color,column-rule-style,column-rule-width,column-span,column-width,columns,content,' +
    'counter-increment,counter-reset,cursor,direction,display,empty-cells,filter,flex,flex-basis,flex-direction,' +
    'flex-flow,flex-grow,flex-shrink,flex-wrap,float,font,font-family,font-feature-settings,font-kerning,' +
    'font-optical-sizing,font-size,font-size-adjust,font-stretch,font-style,font-synthesis,font-variant,' +
    'font-variant-caps,font-variant-ligatures,font-variant-numeric,font-weight,gap,grid,grid-area,grid-auto-columns,' +
    'grid-auto-flow,grid-auto-rows,grid-column,grid-column-end,grid-column-start,grid-gap,grid-row,grid-row-end,' +
    'grid-row-start,grid-template,grid-template-areas,grid-template-columns,grid-template-rows,height,hyphens,' +
    'image-rendering,inline-size,inset,inset-block,inset-inline,isolation,justify-content,justify-items,justify-self,' +
    'left,letter-spacing,line-break,line-height,list-style,list-style-image,list-style-position,list-style-type,margin,' +
    'margin-block,margin-bottom,margin-inline,margin-left,margin-right,margin-top,mask,mask-image,max-block-size,' +
    'max-height,max-inline-size,max-width,min-block-size,min-height,min-inline-size,min-width,mix-blend-mode,' +
    'object-fit,object-position,offset,opacity,order,orphans,outline,outline-color,outline-offset,outline-style,' +
    'outline-width,overflow,overflow-anchor,overflow-wrap,overflow-x,overflow-y,overscroll-behavior,padding,' +
    'padding-block,padding-bottom,padding-inline,padding-left,padding-right,padding-top,page-break-after,' +
    'page-break-before,page-break-inside,paint-order,perspective,perspective-origin,place-content,place-items,' +
    'place-self,pointer-events,position,quotes,resize,right,rotate,row-gap,ruby-align,scale,scroll-behavior,' +
    'scroll-margin,scroll-padding,scroll-snap-align,scroll-snap-stop,scroll-snap-type,scrollbar-color,' +
    'scrollbar-gutter,scrollbar-width,shape-image-threshold,shape-margin,shape-outside,tab-size,table-layout,' +
    'text-align,text-align-last,text-combine-upright,text-decoration,text-decoration-color,text-decoration-line,' +
    'text-decoration-skip-ink,text-decoration-style,text-decoration-thickness,text-emphasis,text-indent,text-justify,' +
    'text-orientation,text-overflow,text-rendering,text-shadow,text-transform,text-underline-offset,' +
    'text-underline-position,top,touch-action,transform,transform-box,transform-origin,transform-style,transition,' +
    'transition-delay,transition-duration,transition-property,transition-timing-function,translate,unicode-bidi,' +
    'user-select,vertical-align,visibility,white-space,widows,width,will-change,word-break,word-spacing,word-wrap,' +
    'writing-mode,z-index,zoom').split(',');
  var CSS_PROP_SET = {};
  (function () { for (var i = 0; i < CSS_PROPS.length; i++) CSS_PROP_SET[CSS_PROPS[i]] = 1; })();

  function cssExtraRules(masked, lineStarts) {
    var out = [];
    var re = /(^|[;{]\s*)([a-zA-Z-]+)\s*:/gm, m;
    while ((m = re.exec(masked)) !== null && out.length < 30) {
      var prop = m[2].toLowerCase();
      if (prop.charAt(0) === '-') {              // vendor prefix → test base
        prop = prop.replace(/^-[a-z]+-/, '');
      }
      if (prop.slice(0, 2) === '--') continue;   // custom property
      if (!CSS_PROP_SET[prop]) {
        var p = posAt(lineStarts, m.index + m[1].length);
        out.push(mk(p.line, p.ch, m[2].length, 'warning',
          'Unknown CSS property "' + m[2] + '".',
          'Browsers silently ignore unknown properties, so the rule does nothing at all.',
          'Check the spelling — property names are all lowercase-with-hyphens.',
          'css'));
      }
    }
    var hexRe = /#[0-9a-fA-F]+/g, h;
    while ((h = hexRe.exec(masked)) !== null && out.length < 40) {
      var digits = h[0].length - 1;
      if (digits !== 3 && digits !== 4 && digits !== 6 && digits !== 8) {
        var p2 = posAt(lineStarts, h.index);
        out.push(mk(p2.line, p2.ch, h[0].length, 'error',
          '"' + h[0] + '" is not a valid hex color.',
          'Hex colors need exactly 3, 4, 6 or 8 digits.',
          'Fix the digit count (e.g. #fff or #ffffff).',
          'css'));
      }
    }
    return out;
  }

  function sqlNoWhereScan(masked, lineStarts) {
    var out = [];
    var delRe = /\bDELETE\s+FROM\s+[\w."`\[\]]+/gi, m;
    while ((m = delRe.exec(masked)) !== null) {
      var end = masked.indexOf(';', m.index);
      if (end === -1) end = masked.length;
      if (/\bWHERE\b/i.test(masked.slice(m.index, end))) continue;
      var p = posAt(lineStarts, m.index);
      out.push(mk(p.line, p.ch, m[0].length, 'warning',
        'DELETE without a WHERE clause.',
        'This deletes EVERY row in the table.',
        'Add WHERE to limit which rows are removed.',
        'sql'));
    }
    var upRe = /\bUPDATE\s+[\w."`\[\]]+\s+SET\b/gi, u;
    while ((u = upRe.exec(masked)) !== null) {
      var end2 = masked.indexOf(';', u.index);
      if (end2 === -1) end2 = masked.length;
      if (/\bWHERE\b/i.test(masked.slice(u.index, end2))) continue;
      var p2 = posAt(lineStarts, u.index);
      out.push(mk(p2.line, p2.ch, u[0].length, 'warning',
        'UPDATE without a WHERE clause.',
        'This rewrites EVERY row in the table.',
        'Add WHERE to limit which rows change.',
        'sql'));
    }
    return out;
  }

  function pyMixedIndent(text, lineStarts) {
    var hasTab = false, hasSpaces = false, firstTabLine = -1, firstSpaceLine = -1;
    var lines = text.split('\n');
    for (var i = 0; i < lines.length; i++) {
      var m = /^([ \t]+)/.exec(lines[i]);
      if (!m) continue;
      if (m[1].indexOf('\t') !== -1 && m[1].indexOf(' ') === -1) {
        hasTab = true; if (firstTabLine < 0) firstTabLine = i;
      } else if (m[1].indexOf(' ') !== -1 && m[1].indexOf('\t') === -1 && m[1].length >= 2) {
        hasSpaces = true; if (firstSpaceLine < 0) firstSpaceLine = i;
      }
    }
    if (hasTab && hasSpaces) {
      var at = firstTabLine;
      return [mk(at, 0, 1, 'warning',
        'File mixes tab and space indentation.',
        'Python 3 refuses to mix tabs and spaces in ways that change meaning — this is a ticking IndentationError.',
        'Pick one style (4 spaces is the convention) and convert the whole file.',
        'python')];
    }
    return [];
  }

  /* ═══════════════════════════════════════════════════════════════
  POSITION HELPERS
  ═══════════════════════════════════════════════════════════════ */
  function buildLineStarts(text) {
    var starts = [0];
    for (var i = 0; i < text.length; i++) {
      if (text[i] === '\n') starts.push(i + 1);
    }
    return starts;
  }
  function posAt(lineStarts, index) {
    var lo = 0, hi = lineStarts.length - 1;
    while (lo < hi) {
      var mid = (lo + hi + 1) >> 1;
      if (lineStarts[mid] <= index) lo = mid; else hi = mid - 1;
    }
    return { line: lo, ch: index - lineStarts[lo] };
  }

  /* ═══════════════════════════════════════════════════════════════
  CLIENT RULE ENGINE
  ═══════════════════════════════════════════════════════════════ */
  function runRules(text, langId) {
    var out = [];
    if (!text || text.length > RULE_CHAR_CAP) return out;
    var lineStarts = buildLineStarts(text);

    if (langId === 'json') return jsonCheck(text, lineStarts);
    if (langId === 'html' || langId === 'xml') return htmlTagScan(text, lineStarts);

    var masked = maskNonCode(text, langId);

    /* structural first — the most important signal */
    out = out.concat(bracketScan(masked, langId, lineStarts));

    var rules = RULES[langId] || [];
    for (var r = 0; r < rules.length && out.length < MAX_MARKERS; r++) {
      var rule = rules[r];
      rule.re.lastIndex = 0;
      var m;
      while ((m = rule.re.exec(masked)) !== null && out.length < MAX_MARKERS) {
        if (m[0].length === 0) { rule.re.lastIndex++; continue; }
        var p = posAt(lineStarts, m.index);
        out.push(mk(p.line, p.ch, m[0].length, rule.sev,
          typeof rule.msg === 'function' ? rule.msg(m) : rule.msg,
          rule.explain, rule.fix, langId));
        if (rule.re.lastIndex === m.index) rule.re.lastIndex++;
      }
    }

    if (langId === 'css' || langId === 'scss' || langId === 'less') {
      out = out.concat(cssExtraRules(masked, lineStarts));
    }
    if (langId === 'sql') out = out.concat(sqlNoWhereScan(masked, lineStarts));
    if (langId === 'python') out = out.concat(pyMixedIndent(text, lineStarts));

    return out;
  }

  /* ═══════════════════════════════════════════════════════════════
  SERVER LINT + MESSAGE ENRICHMENT
  ═══════════════════════════════════════════════════════════════ */
  var SERVER_HINTS = [
    {
      re: /unexpected\s+'([^']+)'\s*,?\s*expecting\s+'?([^'"]+)'?/i,
      f: function (m) {
        return {
          explain: 'The parser met "' + m[1] + '" where only ' + m[2] + ' is allowed. Almost always the REAL mistake is a missing ";", ")" or "}" a line or two ABOVE this one.',
          fix: 'Look just above this line for a missing ";" / brace / parenthesis.'
        };
      }
    },
    {
      re: /unexpected\s+(?:end of file|EOF|\$end)/i,
      f: function () {
        return {
          explain: 'The file ended while a block was still open — a "{" or "(" somewhere above never got closed.',
          fix: 'Count your braces/parens; the last opened block is missing its closer.'
        };
      }
    },
    {
      re: /unexpected\s+'([^']+)'/i,
      f: function (m) {
        return {
          explain: '"' + m[1] + '" does not belong here. Check the statement just before it — a ";" or operator is usually missing.',
          fix: 'Add the missing ";" / operator above, or remove the stray token.'
        };
      }
    },
    {
      re: /expecting\s+'?; '?/i,
      f: function () {
        return {
          explain: 'The statement above never got its terminating semicolon.',
          fix: 'Add ";" at the end of the previous statement.'
        };
      }
    },
    {
      re: /invalid syntax/i,
      f: function () {
        return {
          explain: 'Python could not parse this line. Usual suspects: missing ":" after if/for/def/class, mismatched quotes, or a stray character.',
          fix: 'Check the marked line AND the line above it for a missing ":" or quote.'
        };
      }
    },
    {
      re: /expected an indented block/i,
      f: function () {
        return {
          explain: 'The line above (if/for/def/class …) opens a block, but nothing is indented under it.',
          fix: 'Indent the block body, or add "pass" as a placeholder.'
        };
      }
    },
    {
      re: /unexpected EOF while parsing|incomplete input/i,
      f: function () {
        return {
          explain: 'The file ends mid-expression — an open "(", "[" or string was never finished.',
          fix: 'Close the open bracket/string near the end of the file.'
        };
      }
    },
    {
      re: /Unexpected token\s+'?([^'"]+)'?/i,
      f: function (m) {
        return {
          explain: 'JavaScript hit "' + m[1] + '" where it cannot appear. Typically a missing ",", ";" or ")" just before.',
          fix: 'Check the tokens right before this position.'
        };
      }
    },
    {
      re: /Unexpected end of (JSON )?input/i,
      f: function () {
        return {
          explain: 'The code ends while something is still open.',
          fix: 'Close the remaining bracket/brace/template literal.'
        };
      }
    }
  ];

  function enrichServerMarker(m) {
    for (var i = 0; i < SERVER_HINTS.length; i++) {
      var mm = SERVER_HINTS[i].re.exec(m.message);
      if (mm) {
        var hint = SERVER_HINTS[i].f(mm);
        if (!m.explain) m.explain = hint.explain;
        if (!m.fix) m.fix = hint.fix;
        return;
      }
    }
  }

  function runServerLint(content, ext) {
    var lang = serverLangFor(ext);
    if (!lang || lang === 'json' || !API || !API.lint) return Promise.resolve([]);
    if (lintController) { try { lintController.abort(); } catch (e) { } }
    lintController = API.createController ? API.createController() : null;
    return API.lint.check(content, lang, lintController ? lintController.signal : null)
      .then(function (res) {
        var arr = (res && (res.markers || res.errors || res.list)) || [];
        var out = [];
        for (var i = 0; i < arr.length && i < 100; i++) {
          var e = arr[i] || {};
          var line = (e.line != null ? e.line : (e.row != null ? e.row : 1));
          var mm = mk(
            Math.max(0, line - 1),                 // server lines are 1-based
            Math.max(0, (e.col != null ? e.col : (e.ch != null ? e.ch : 0))),
            Math.max(1, e.length || 1),
            normSev(e.severity || 'error'),
            e.message || e.text || 'Syntax problem',
            '', '',
            e.source || lang
          );
          enrichServerMarker(mm);
          out.push(mm);
        }
        return out;
      })
      .catch(function (e) {
        if (API && API.isAbortError && API.isAbortError(e)) return [];
        return []; // silent — rules still cover the file
      });
  }

  /* ═══════════════════════════════════════════════════════════════
  MERGE + RENDER PIPELINE
  ═══════════════════════════════════════════════════════════════ */
  function lintBuffer() {
    if (!featureOn || !cmRef || !current.path) return;
    var content = '';
    try { content = cmRef.getValue(); } catch (e) { return; }
    var ext = current.ext, path = current.path;
    var mySeq = ++runSeq;

    var clientMarkers = runRules(content, current.langId || langIdFor(ext));

    runServerLint(content, ext).then(function (serverMarkers) {
      if (mySeq !== runSeq || current.path !== path) return; // stale
      var all = serverMarkers.concat(clientMarkers);
      var sevRank = { error: 0, warning: 1, info: 2 };
      all.sort(function (a, b) {
        if (a.line !== b.line) return a.line - b.line;
        if (sevRank[a.severity] !== sevRank[b.severity]) return sevRank[a.severity] - sevRank[b.severity];
        return a.ch - b.ch;
      });
      if (all.length > MAX_MARKERS) all = all.slice(0, MAX_MARKERS);
      current.markers = all;

      /* gutter + squiggles through the facade (0-based lines) */
      try {
        if (cmRef && cmRef.setDiagnostics) {
          cmRef.setDiagnostics(all.map(function (m) {
            return { line: m.line, ch: m.ch, length: m.length, severity: m.severity, message: m.message };
          }));
        }
      } catch (e) { }

      updateRing();
      if (overlayOpen) renderOverlayBody();
    });
  }

  function scheduleLint(delay) {
    if (!featureOn || !liveOn) return;
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(function () {
      debounceTimer = null;
      lintBuffer();
    }, delay == null ? DEBOUNCE_MS : delay);
  }

  /* ═══════════════════════════════════════════════════════════════
  STATUS RING (the 📋 action-bar button)
  ═══════════════════════════════════════════════════════════════ */
  function counts() {
    var e = 0, w = 0, i = 0;
    for (var k = 0; k < current.markers.length; k++) {
      var s = current.markers[k].severity;
      if (s === 'error') e++; else if (s === 'warning') w++; else i++;
    }
    return { e: e, w: w, i: i };
  }

  function updateRing() {
    btnEl = btnEl || document.getElementById('mab-diag');
    if (!btnEl) return;
    var c = counts();
    btnEl.classList.remove('mab-diag-ok', 'mab-diag-warn', 'mab-diag-error');
    if (c.e > 0) {
      btnEl.classList.add('mab-diag-error');
      btnEl.title = c.e + ' error' + (c.e === 1 ? '' : 's') + ' — tap for the full report';
    } else if (c.w > 0) {
      btnEl.classList.add('mab-diag-warn');
      btnEl.title = c.w + ' warning' + (c.w === 1 ? '' : 's') + ' — tap for the full report';
    } else {
      btnEl.classList.add('mab-diag-ok');
      btnEl.title = 'No problems — tap for the full report';
    }
  }

  /* ═══════════════════════════════════════════════════════════════
  REPORT OVERLAY
  ═══════════════════════════════════════════════════════════════ */
  function buildOverlay() {
    var ov = document.getElementById('m-diag-overlay');
    if (ov) return ov;
    ov = document.createElement('div');
    ov.id = 'm-diag-overlay';
    ov.className = 'm-diag-overlay hidden';
    ov.innerHTML =
      '<div class="m-diag-ov-header">' +
      '<span class="m-diag-ov-title">📋 File Report</span>' +
      '<span class="m-diag-ov-count" id="m-diag-ov-count"></span>' +
      '<button type="button" class="m-diag-ov-btn" id="m-diag-ov-recheck">🔄 Re-check</button>' +
      '<button type="button" class="m-diag-ov-btn" id="m-diag-ov-copy">📋 Copy</button>' +
      '<button type="button" class="m-diag-ov-close" id="m-diag-ov-close" aria-label="Close report">✕</button>' +
      '</div>' +
      '<div class="m-diag-filters" id="m-diag-filters">' +
      '<button type="button" data-filter="all" class="active">All</button>' +
      '<button type="button" data-filter="error">Errors</button>' +
      '<button type="button" data-filter="warning">Warnings</button>' +
      '<button type="button" data-filter="info">Info</button>' +
      '</div>' +
      '<div class="m-diag-ov-body" id="m-diag-ov-body"></div>';
    document.body.appendChild(ov);

    document.getElementById('m-diag-ov-close').addEventListener('click', closeReport);
    document.getElementById('m-diag-ov-recheck').addEventListener('click', function () {
      lintBuffer();
      toast('🔄 Re-checking…');
    });
    document.getElementById('m-diag-ov-copy').addEventListener('click', function () {
      var txt = current.markers.map(function (m) {
        return '[' + m.severity.toUpperCase() + '] Ln ' + (m.line + 1) + ' — ' + m.message +
          (m.explain ? '\n    Why: ' + m.explain : '') +
          (m.fix ? '\n    Fix: ' + m.fix : '');
      }).join('\n');
      if (!txt) txt = 'No problems found. ✨';
      if (U && U.copyToClipboard) {
        U.copyToClipboard(txt).then(function (ok) {
          toast(ok ? '📋 Report copied' : '⚠ Copy failed');
        });
      }
    });
    ov.querySelector('#m-diag-filters').addEventListener('click', function (e) {
      var b = e.target.closest ? e.target.closest('[data-filter]') : null;
      if (!b) return;
      activeFilter = b.getAttribute('data-filter');
      var chips = this.querySelectorAll('[data-filter]');
      for (var i = 0; i < chips.length; i++) chips[i].classList.toggle('active', chips[i] === b);
      renderOverlayBody();
    });
    return ov;
  }

  function renderOverlayBody() {
    var body = document.getElementById('m-diag-ov-body');
    var cntEl = document.getElementById('m-diag-ov-count');
    if (!body) return;
    var c = counts();
    if (cntEl) {
      cntEl.innerHTML =
        '<span class="m-diag-cnt err">' + c.e + '</span> ' +
        '<span class="m-diag-cnt warn">' + c.w + '</span> ' +
        '<span class="m-diag-cnt info">' + c.i + '</span>';
    }
    var list = current.markers.filter(function (m) {
      return activeFilter === 'all' || m.severity === activeFilter;
    });
    if (!list.length) {
      var note = current.markers.length
        ? 'Nothing at this severity level.'
        : 'No problems found in this file. ✨<div class="m-diag-ov-note">' +
        'Checked with on-device rules' + (serverLangFor(current.ext) ? ' + server lint' : '') +
        '. Re-check any time with 🔄.</div>';
      body.innerHTML = '<div class="m-diag-ov-empty">' + note + '</div>';
      return;
    }
    var html = '';
    for (var i = 0; i < list.length; i++) {
      var m = list[i];
      html += '<button type="button" class="m-diag-item m-diag-item-' + m.severity + '" data-line="' + m.line + '" data-ch="' + m.ch + '">' +
        '<span class="m-diag-dot"></span>' +
        '<span class="m-diag-label">' +
        '<span class="m-diag-msg">' + esc(m.message) + '</span>' +
        (m.explain ? '<span class="m-diag-explain">' + esc(m.explain) + '</span>' : '') +
        (m.fix ? '<span class="m-diag-fix">' + esc(m.fix) + '</span>' : '') +
        '</span>' +
        '<span class="m-diag-src">' + esc(m.source) + '</span>' +
        '<span class="m-diag-line-no">Ln ' + (m.line + 1) + '</span>' +
        '</button>';
    }
    body.innerHTML = html;
  }

  function openReport() {
    buildOverlay();
    renderOverlayBody();
    var ov = document.getElementById('m-diag-overlay');
    ov.classList.remove('hidden');
    overlayOpen = true;
    try {
      var S = window.IDE && window.IDE.mobileSheets;
      if (S && S.registerOverlay) S.registerOverlay('m-diag-overlay', closeReport);
    } catch (e) { }
  }
  function closeReport() {
    var ov = document.getElementById('m-diag-overlay');
    if (ov) ov.classList.add('hidden');
    overlayOpen = false;
    try {
      var S = window.IDE && window.IDE.mobileSheets;
      if (S && S.unregisterOverlay) S.unregisterOverlay('m-diag-overlay');
    } catch (e) { }
  }

  function wireOverlayJump() {
    document.addEventListener('click', function (e) {
      var item = e.target && e.target.closest ? e.target.closest('.m-diag-item') : null;
      if (!item || !overlayOpen) return;
      var line = parseInt(item.getAttribute('data-line'), 10);
      var ch = parseInt(item.getAttribute('data-ch'), 10) || 0;
      closeReport();
      var cm = cmRef || (window.IDE.mobileEditor && window.IDE.mobileEditor.getCM());
      if (!cm || isNaN(line)) return;
      try {
        cm.setCursor({ line: line, ch: ch });
        cm.scrollIntoView({ line: line, ch: ch }, 80);
        cm.focus();
      } catch (e) { }
    });
  }

  /* ═══════════════════════════════════════════════════════════════
  TAB LIFECYCLE
  ═══════════════════════════════════════════════════════════════ */
  function clearAll() {
    current.markers = [];
    try { if (cmRef && cmRef.clearDiagnostics) cmRef.clearDiagnostics(); } catch (e) { }
    updateRing();
  }

  function syncTab(detail) {
    var d = detail || {};
    current.path = d.path || null;
    current.ext = d.ext || (d.path ? String(d.path).split('.').pop().toLowerCase() : '');
    current.langId = d.id || langIdFor(current.ext);
    current.markers = [];
    cmRef = (window.IDE.mobileEditor && window.IDE.mobileEditor.getCM()) || cmRef;
    try { if (cmRef && cmRef.clearDiagnostics) cmRef.clearDiagnostics(); } catch (e) { }
    updateRing();
    if (cmRef && !cmRef._qkDiagWired) {
      cmRef._qkDiagWired = true;
      try {
        cmRef.on('change', function () { scheduleLint(); });
      } catch (e) { }
    }
    if (current.path) scheduleLint(150); // first pass fast, quiet
  }

  function wireBus() {
    if (!U || typeof U.on !== 'function') return;
    U.on('editor:tab-shown', function (e) { syncTab(e && e.detail); });
    U.on('editor:saved', function () { scheduleLint(120); });
    /* extension changes (rename while open) — re-sync language rules */
    U.on('file:renamed', function (e) {
      var d = (e && e.detail) || {};
      if (!d.to) return;
      var ME = window.IDE.mobileEditor;
      var tab = ME && ME.getActiveTab && ME.getActiveTab();
      if (tab && tab.path === d.to) {
        syncTab({ path: tab.path, ext: tab.ext, id: null });
      }
    });
  }

  function boot() {
    if (!featureOn) return;
    btnEl = document.getElementById('mab-diag');
    if (btnEl) {
      btnEl.classList.add('mab-diag-ok');
      btnEl.addEventListener('click', openReport);
    }
    wireOverlayJump();
    wireBus();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }

  /* ═══════════════════════════════════════════════════════════════
  EXPOSE
  ═══════════════════════════════════════════════════════════════ */
  window.IDE.diagnostics = {
    refresh: lintBuffer,
    clear: clearAll,
    getMarkers: function () { return current.markers.slice(); },
    openReport: openReport,
    closeReport: closeReport,
    isOpen: function () { return overlayOpen; },
    setLive: function (b) {
      liveOn = !!b;
      try { localStorage.setItem(LS_LIVE, liveOn ? '1' : '0'); } catch (e) { }
      if (liveOn) scheduleLint(150);
      return liveOn;
    },
    isLive: function () { return liveOn; }
  };
})();