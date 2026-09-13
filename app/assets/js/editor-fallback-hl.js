/*! ============================================================================
 *  QUIRKY IDE — CM6 LAST-RESORT SYNTAX COLOURS  (v24.3 "mixed + mobile")
 *  ---------------------------------------------------------------------------
 *  Drop-in replacement for editor-fallback-hl.js (v23.1). Public API kept:
 *    window.IDE.editorFallbackHl = { isMounted, probeNow, isPainterActive, ... }
 *  plus a new, engine-independent lexer that is also useful on its own:
 *    window.IDE.syntaxLex = { tokenizeDocument, tokenizeLine, highlightHtml, ... }
 *
 *  WHY v23.1 LOOKED "BROKEN"
 *  1. ONE big regex, alternation ordered comment-before-string, so anything
 *     after a marker INSIDE a string was repainted as a comment:
 *        const url = "https://x/y";   ->   //  y";   turned grey
 *        print("# not a comment")     ->   # not a comment")  turned grey
 *     That is the "some tokens look grey like a comment" field report.
 *  2. Tokenizing started at the top of the VIEWPORT, so a /* block comment,
 *     a triple-quoted docstring, a heredoc or a template literal that opened
 *     ABOVE the viewport was unknown: its body painted as code and, worse,
 *     the colours changed while you scrolled. Viewport-dependent colouring.
 *  3. `.cm-line` DOM writing only ever touched visible lines, so anything
 *     just outside the viewport (and the whole document for exports/tests)
 *     had no classes at all.
 *
 *  WHAT v24 DOES
 *  • A real, stateful lexer (CodeMirror stream-mode style): per language
 *    profiles scan the WHOLE document once, carrying state across lines, so
 *    multi-line comments / strings / heredocs / fences are correct no matter
 *    where the viewport is.
 *  • Incremental: on every edit only the changed line and the following
 *    lines up to the first line whose start-state matches the cached one are
 *    re-scanned. Scrolling never re-tokenizes.
 *  • Same tok-* taxonomy as CM6's classHighlighter/highlight style, emitted
 *    with the alias classes the IDE CSS already colours, e.g.
 *    "tok-function tok-variableName", "tok-tag tok-tagName",
 *    "tok-definition tok-variableName" — so themes.css §7 and the adapter's
 *    injected base CSS paint it identically to native CM6 colours.
 *  • Two renderers, both fed from the same token cache so they can never
 *    disagree: a CM6 ViewPlugin (mark decorations around the viewport) and
 *    the .cm-line DOM painter used only when the CM6 build cannot render
 *    decorations at all.
 *  • Colour guarantee: if the token classes resolve to the same colour as
 *    plain text (themes.css / base CSS missing or stale), an explicit
 *    palette stylesheet is injected instead of silently rendering grey.
 *  • Diagnostics: IDE.editorFallbackHl.diagnose() explains what was found.
 *
 *  v24.3 — MOBILE BUDGET (why a phone lagged where a desktop did not)
 *  1. Tokens are cached LINE-RELATIVE, so an insert/delete that shifts every
 *     following offset still reuses the untouched tail verbatim. v24.2 keyed
 *     reuse on absolute offsets, so one keystroke re-lexed the whole file.
 *  2. Start-states are compared with a flat signature string instead of a
 *     deep clone + recursive compare (~70% of all tokenize time before).
 *  3. Periodic state checkpoints let an edit near the BOTTOM restart a few
 *     lines above it instead of from line 1.
 *  4. The </style> - </script> - ?> boundary scan is one allocation-free
 *     pass; v24.2 ran a second full tokenization of every embedded line.
 *  5. The DOM painter repaints on a frame budget and resumes next frame.
 *  Net on a 658-line PHP page: cold 50ms, every edit position 1-2ms
 *  (was 12-25ms, and a bottom edit re-lexed the entire file).
 *
 *  MIXED-FILE COLOUR PRECEDENCE
 *  On a device CM6 may paint an entire <style> body as one string. Our marks
 *  land on the same characters, so every fallback span also carries `qk-fb`
 *  and the injected palette gives `.qk-fb.tok-*` the cascade weight to win.
 *
 *  COVERAGE (roles → tok-* classes, all painted by the IDE palette)
 *    comment, doc-comment, string, string2/char, regexp, escape, keyword,
 *    modifier, atom, bool, number/unit, variableName, definition, function,
 *    macroName, propertyName, typeName, className, namespace, operator,
 *    punctuation/paren/bracket, meta/processing, tag, attributeName,
 *    attributeValue, heading, quote, link, url, emphasis, strong,
 *    strikethrough, labelName, literal, invalid
 *
 *  LANGUAGES (per-language profiles, not one regex)
 *    js/ts/jsx/json · python · c/cpp · java · csharp · go · rust · swift ·
 *    kotlin · dart · php (HTML+PHP+CSS+JS+JSON router) · ruby · perl · lua · shell ·
 *    sql · yaml · toml/ini/properties · css/scss/less · html/xml/vue/svelte ·
 *    markdown (with embedded code fences) · plaintext
 * ==========================================================================*/
(function () {
  'use strict';

  var W = typeof window !== 'undefined' ? window : null;
  if (!W) return;
  var IDE = W.IDE = W.IDE || {};
  var DOC = typeof document !== 'undefined' ? document : null;

  var VERSION = '24.3.0';

  /* ═══════════════════════════════════════════════════════════════
     1 · TOKEN TAXONOMY
     Canonical CM6 classHighlighter class first, alias class second
     (the alias is what editor-adapter/themes.css colour today).
     ═══════════════════════════════════════════════════════════════ */
  var C = {
    comment: 'tok-comment',
    doc: 'tok-comment docComment',
    string: 'tok-string',
    string2: 'tok-string2',
    regexp: 'tok-regexp',
    escape: 'tok-escape',
    keyword: 'tok-keyword',
    modifier: 'tok-modifier',
    atom: 'tok-atom',
    bool: 'tok-bool',
    number: 'tok-number',
    variable: 'tok-variableName',
    def: 'tok-definition tok-variableName',
    func: 'tok-function tok-variableName',
    prop: 'tok-propertyName',
    type: 'tok-typeName',
    cls: 'tok-className',
    ns: 'tok-namespace',
    macro: 'tok-macroName tok-function',
    op: 'tok-operator',
    ptn: 'tok-punctuation',
    paren: 'tok-punctuation tok-paren',
    bracket: 'tok-punctuation tok-bracket',
    meta: 'tok-meta',
    proc: 'tok-meta tok-processing',
    tag: 'tok-tag tok-tagName',
    attr: 'tok-attributeName',
    attrVal: 'tok-attributeValue tok-string',
    head: 'tok-heading',
    quote: 'tok-quote',
    link: 'tok-link',
    url: 'tok-url tok-link',
    em: 'tok-emphasis',
    strong: 'tok-strong',
    strike: 'tok-strikethrough',
    label: 'tok-labelName',
    literal: 'tok-literal',
    invalid: 'tok-invalid'
  };

  var ROLE_NOTES = [
    ['comment', 'tok-comment', 'line + block + doc comments (stateful, nested where the language nests)'],
    ['string', 'tok-string', 'single/double/triple/raw/verbatim strings, heredocs, template literals'],
    ['string2', 'tok-string2', 'char literals, regexes, interpolated segments, attribute values'],
    ['number', 'tok-number', 'int/float/hex/bin/oct/underscores, CSS units'],
    ['keyword', 'tok-keyword', 'language keywords + control flow'],
    ['definition', 'tok-definition', 'declared names (function/class/const/param)'],
    ['function', 'tok-function', 'call sites, macros, builtins used as calls'],
    ['property', 'tok-propertyName', 'member access, object keys, YAML/CSS properties'],
    ['type', 'tok-typeName', 'primitive + capitalised type names, class names'],
    ['operator', 'tok-operator', 'full operator set incl. word operators'],
    ['meta', 'tok-meta', 'preprocessor, decorators, annotations, attributes, fences']
  ];

  /* ═══════════════════════════════════════════════════════════════
     2 · SHARED SCANNING PRIMITIVES
     ═══════════════════════════════════════════════════════════════ */
  var IDENT = /[A-Za-z_$\u00A1-\uFFFF][A-Za-z0-9_$\u00A1-\uFFFF]*/y;
  var NUMB = /(?:0[xX][\da-fA-F_]+|0[bB][01_]+|0[oO][0-7_]+|(?:\d[\d_]*(?:\.[\d_]*)?|\.[\d_]+)(?:[eE][+-]?\d+)?)[a-zA-Z_]{0,3}/y;
  var NUMU = /(?:\d[\d_]*(?:\.[\d_]*)?|\.[\d_]+)(?:[a-zA-Z%]{0,4})?/y;

  function setList(str) {
    var o = {}, a = String(str || '').split(/\s+/), i;
    for (i = 0; i < a.length; i++) if (a[i]) o[a[i]] = 1;
    return o;
  }

  function isDigit(ch) {
    return ch >= '0' && ch <= '9';
  }

  function identStart(ch) {
    return !!ch && /[A-Za-z_$\u00A1-\uFFFF]/.test(ch);
  }

  function isUpper(ch) {
    return ch >= 'A' && ch <= 'Z';
  }

  function nextNonSpace(text, i) {
    while (i < text.length && (text.charAt(i) === ' ' || text.charAt(i) === '\t')) i++;
    return text.charAt(i) || '';
  }

  function startsWithAny(text, i, arr) {
    for (var k = 0; k < arr.length; k++) if (text.startsWith(arr[k].open, i)) return arr[k];
    return null;
  }

  function restOrMatch(lineComments, text, i) {
    for (var k = 0; k < lineComments.length; k++) if (text.startsWith(lineComments[k], i)) return true;
    return false;
  }

  /** After a type word, an identifier followed by one of these is a
   *  declaration (`int x = 1;`) rather than an expression (`String(a)`). */
  function DECLARE_AFTER(ch) {
    return ch === '' || ch === '=' || ch === ';' || ch === ',' || ch === ')' ||
      ch === ':' || ch === ']' || ch === '}' || ch === '|';
  }

  function trimCR(s) {
    return s.charCodeAt(s.length - 1) === 13 ? s.slice(0, -1) : s;
  }

  function cloneFrame(f) {
    if (!f) return f;
    if (f.kind === 'block') return { kind: 'block', spec: f.spec, depth: f.depth };
    if (f.kind === 'str') return { kind: 'str', spec: f.spec };
    if (f.kind === 'tmpl') return { kind: 'tmpl', spec: f.spec };
    return { kind: 'brace' };
  }

  function cloneExtra(ex) {
    if (!ex) return null;
    var out = {}, k;
    for (k in ex) {
      if (!Object.prototype.hasOwnProperty.call(ex, k)) continue;
      var v = ex[k];
      if (k === 'subStates' && v) {
        var subs = {}, s;
        for (s in v) if (Object.prototype.hasOwnProperty.call(v, s)) subs[s] = v[s] ? cloneState(v[s]) : null;
        out.subStates = subs;
      } else if (v && typeof v === 'object' && !v.nodeType) {
        out[k] = Object.assign({}, v);
      } else {
        out[k] = v;
      }
    }
    return out;
  }

  /* Deep snapshot. Engines always REPLACE frame objects instead of mutating
     them, so a shallow copy of the frames array + copied frame objects is a
     valid value snapshot — which is what the incremental cache compares. */
  function cloneState(st) {
    if (!st) return null;
    var frames = [], i;
    for (i = 0; i < st.frames.length; i++) frames.push(cloneFrame(st.frames[i]));
    return {
      frames: frames,
      expect: st.expect,
      dot: st.dot,
      prev: st.prev,
      partial: !!st.partial,
      extra: cloneExtra(st.extra)
    };
  }

  function samePlainObject(a, b) {
    var ak = Object.keys(a || {}), bk = Object.keys(b || {}), i, k;
    if (ak.length !== bk.length) return false;
    for (i = 0; i < ak.length; i++) {
      k = ak[i];
      if (a[k] && typeof a[k] === 'object' && b[k] && typeof b[k] === 'object') {
        if (!samePlainObject(a[k], b[k])) return false;
      } else if (a[k] !== b[k]) return false;
    }
    return true;
  }

  function sameExtra(a, b) {
    a = a || {};
    b = b || {};
    var ka = Object.keys(a), kb = Object.keys(b), i, k;
    if (ka.length !== kb.length) return false;
    for (i = 0; i < ka.length; i++) {
      k = ka[i];
      var va = a[k], vb = b[k];
      if (k === 'subStates') {
        var sa = va || {}, sb = vb || {};
        var ksa = Object.keys(sa), ksb = Object.keys(sb), j;
        if (ksa.length !== ksb.length) return false;
        for (j = 0; j < ksa.length; j++) {
          var va2 = sa[ksa[j]], vb2 = sb[ksa[j]];
          if (!va2 || !vb2) {
            if (va2 !== vb2) return false;
            continue;
          }
          if (!sameState(va2, vb2)) return false;
        }
        continue;
      }
      if (va && typeof va === 'object' && vb && typeof vb === 'object') {
        if (!samePlainObject(va, vb)) return false;
      } else if (va !== vb) return false;
    }
    return true;
  }

  function sameState(a, b) {
    if (!a || !b) return false;
    if (a.expect !== b.expect) return false;
    if (!!a.dot !== !!b.dot) return false;
    if ((a.prev || '') !== (b.prev || '')) return false;
    if (!!a.partial !== !!b.partial) return false;
    if (a.frames.length !== b.frames.length) return false;
    for (var i = 0; i < a.frames.length; i++) {
      var f = a.frames[i], g = b.frames[i];
      if (f.kind !== g.kind) return false;
      if (f.spec !== g.spec) return false;
      if ((f.depth || 0) !== (g.depth || 0)) return false;
    }
    return sameExtra(a.extra, b.extra);
  }

  /* Block comment scanner. `fresh` = the open token is at `i`. */
  function scanBlock(text, i, spec, depth, fresh) {
    var k = i, n = text.length, d = depth || 1;
    if (fresh) k += spec.open.length;
    while (k < n) {
      if (spec.nested && text.startsWith(spec.open, k)) {
        d++;
        k += spec.open.length;
        continue;
      }
      if (text.startsWith(spec.close, k)) {
        d--;
        k += spec.close.length;
        if (d <= 0) return { end: k, closed: true, depth: 0 };
        continue;
      }
      k++;
    }
    return { end: n, closed: false, depth: d };
  }

  /* String body scanner: `start` is the first CONTENT character. Returns
     strEnd (end of content), end (after the close token / interpolation
     marker) and what happened. Used both for a fresh string (start = i +
     open.length) and when resuming the tail of a template literal after a
     ${…} interpolation closes mid-line. */
  function scanStringBody(text, start, spec) {
    var q = spec.close || spec.open, k = start, n = text.length;
    while (k < n) {
      var ch = text.charAt(k);
      if (spec.esc && ch === spec.esc) {
        k += 2;
        continue;
      }
      if (spec.interp && text.startsWith(spec.interp, k)) {
        return { strEnd: k, end: k + spec.interp.length, closed: false, interp: true };
      }
      if (spec.multi ? text.startsWith(q, k) : ch === q) {
        return { strEnd: k, end: k + q.length, closed: true, interp: false };
      }
      k++;
    }
    return { strEnd: n, end: n, closed: false, interp: false, eol: true };
  }

  function scanString(text, i, spec) {
    return scanStringBody(text, i + spec.open.length, spec);
  }

  function escHtml(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;')
      .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  /* ═══════════════════════════════════════════════════════════════
     3 · ENGINE A — STREAM ENGINE (C-like / JS / PHP / SQL …)
     cfg: keywords, types, builtins, atoms, blocks, lineComments,
          strings, decorator, compilerMeta, quotedKeys, capitalType,
          wordOperators, ops, numUnits, caseInsensitive
     ═══════════════════════════════════════════════════════════════ */
  function streamEngine(cfg) {
    var kw = cfg.keywords || {}, ty = cfg.types || {}, bl = cfg.builtins || {},
      at = cfg.atoms || {}, wo = cfg.wordOperators || {};
    var lineC = cfg.lineComments || [], blocks = cfg.blocks || [];
    var specs = (cfg.strings || []).slice();
    var decl = cfg.declKeywords || setList('function def fn func class interface struct enum trait impl type let const var new extends implements instanceof namespace package import use from module with');
    var ops = cfg.ops || /[=+\-*/%<>!&|^~?:]+/y;
    var capType = !!cfg.capitalType;
    var ci = !!cfg.caseInsensitive;
    var quotedKeys = !!cfg.quotedKeys;
    var numRe = cfg.numUnits ? NUMU : NUMB;
    var hooks = cfg.hooks || [];

    function start() {
      return { frames: [], expect: null, dot: false, prev: '', partial: false, extra: null };
    }

    function line(st, text) {
      var toks = [];
      var n = text.length, i = 0;

      function tok(a, b, cls) {
        if (b > a && cls) toks.push([a, b, cls]);
      }

      /* fresh=true: the opener is at `at` (scan content after it).
         fresh=false: `at` is already the first content character — used
         when a template literal resumes right after its ${…} marker. */
      /* Some languages hide another token INSIDE their strings
         (shell "$USER", php "{$obj->x}"). stringSub re-scans the string
         range so those parts keep their own class instead of turning
         the whole literal one colour. */
      function pushStr(a, b, cls) {
        if (!cfg.stringSub || b <= a) {
          tok(a, b, cls);
          return;
        }
        var slice = text.slice(a, b), last = 0, m, any = false;
        var re = cfg.stringSub.re, sub2 = cfg.stringSub.cls;
        re.lastIndex = 0;
        while ((m = re.exec(slice)) !== null) {
          if (!m[0]) {
            re.lastIndex++;
            continue;
          }
          if (m.index > last) tok(a + last, a + m.index, cls);
          else if (m.index < last) continue;
          tok(a + m.index, a + m.index + m[0].length, sub2);
          last = m.index + m[0].length;
          any = true;
        }
        if (!any) {
          tok(a, b, cls);
          return;
        }
        tok(a + last, b, cls);
      }

      function enterString(spec, at, fresh) {
        var r = fresh ? scanString(text, at, spec) : scanStringBody(text, at, spec);
        var cls = spec.cls || C.string;
        if (r.interp) {
          pushStr(at, r.strEnd, cls);
          tok(r.strEnd, r.end, C.ptn);
          st.frames.push({ kind: 'tmpl', spec: spec });
        } else {
          pushStr(at, r.end, cls);
          if (!r.closed && (spec.multi || st.partial)) st.frames.push({ kind: 'str', spec: spec });
        }
        return r.end;
      }

      while (i < n) {
        var frame = st.frames.length ? st.frames[st.frames.length - 1] : null;

        /* ---- resume constructs that began on an earlier line ---- */
        if (frame && frame.kind === 'block') {
          var rb = scanBlock(text, i, frame.spec, frame.depth, false);
          tok(i, rb.end, frame.spec.cls || C.comment);
          if (rb.closed) st.frames.pop();
          else st.frames[st.frames.length - 1] = { kind: 'block', spec: frame.spec, depth: rb.depth };
          i = rb.end;
          continue;
        }
        if (frame && frame.kind === 'str') {
          var r0 = scanStringBody(text, i, frame.spec);
          var scls = frame.spec.cls || C.string;
          if (r0.interp) {
            st.frames.pop();
            pushStr(i, r0.strEnd, scls);
            tok(r0.strEnd, r0.end, C.ptn);
            st.frames.push({ kind: 'tmpl', spec: frame.spec });
          } else {
            pushStr(i, r0.end, scls);
            if (r0.closed) st.frames.pop();
          }
          i = r0.end;
          continue;
        }

        var ch = text.charAt(i);

        if (ch === ' ' || ch === '\t') {
          i++;
          continue;
        }

        /* ---- language hooks FIRST: they are the most specific rules and
               must win over line comments (#! shebang), generic strings
               (rust lifetimes 'a) and operators (-1 flags) ---- */
        if (hooks.length) {
          var hi, hit = null;
          for (hi = 0; hi < hooks.length && !hit; hi++) {
            hooks[hi].re.lastIndex = i;
            var hm = hooks[hi].re.exec(text);
            if (hm) hit = { h: hooks[hi], m: hm };
          }
          if (hit) {
            var hcls = typeof hit.h.cls === 'function' ? hit.h.cls(hit.m) : hit.h.cls;
            tok(i, i + hit.m[0].length, hcls);
            if (hit.h.set) hit.h.set(st, hit.m);
            i += hit.m[0].length;
            continue;
          }
        }

        /* ---- line comments (only reachable outside strings, because the
               string frame path above consumes their content) ---- */
        if (restOrMatch(lineC, text, i)) {
          tok(i, n, C.comment);
          i = n;
          break;
        }

        /* ---- block comments ---- */
        var bs = blocks.length ? startsWithAny(text, i, blocks) : null;
        if (bs) {
          var rc = scanBlock(text, i, bs, 1, true);
          tok(i, rc.end, bs.cls || C.comment);
          if (!rc.closed) st.frames.push({ kind: 'block', spec: bs, depth: rc.depth });
          i = rc.end;
          continue;
        }

        /* ---- strings ---- */
        var spec = startsWithAny(text, i, specs);
        if (spec) {
          var strEnd = enterString(spec, i, true);
          if (quotedKeys) {
            var nxt = nextNonSpace(text, strEnd);
            if (nxt === ':') {
              var lastT = toks[toks.length - 1];
              if (lastT) lastT[2] = C.prop;
            }
          }
          i = strEnd;
          continue;
        }

        /* ---- decorators / annotations ---- */
        if (cfg.decorator) {
          cfg.decorator.lastIndex = i;
          var dm = cfg.decorator.exec(text);
          if (dm) {
            tok(i, i + dm[0].length, cfg.decoratorCls || C.meta);
            i += dm[0].length;
            continue;
          }
        }

        /* ---- numbers ---- */
        if (isDigit(ch) || (ch === '.' && isDigit(text.charAt(i + 1)))) {
          numRe.lastIndex = i;
          var nm = numRe.exec(text);
          if (nm) {
            tok(i, i + nm[0].length, C.number);
            i += nm[0].length;
            continue;
          }
        }

        /* ---- identifiers ---- */
        if (identStart(ch)) {
          IDENT.lastIndex = i;
          var im = IDENT.exec(text);
          var word = im[0];
          var end = i + word.length;
          var look = ci ? word.toLowerCase() : word;
          var after = nextNonSpace(text, end);
          /* `String describe(` declares, `String(x)` calls: the
             difference is whether the name is glued to the type */
          var glued = i > 0 && text.charAt(i - 1) !== ' ' && text.charAt(i - 1) !== '\t';
          var cls = null;
          var kind = 'var';

          if (at[word] || at[look]) {
            cls = C.bool;
            kind = 'atom';
          } else if (ty[word] || ty[look]) {
            /* types win over the keyword list: `int flags;` declares */
            cls = C.type;
            kind = 'type';
          } else if (kw[word] || kw[look]) {
            cls = C.keyword;
            kind = 'kw';
          } else if (wo[word] || wo[look]) {
            cls = C.op;
            kind = 'op';
          } else if (bl[word] || bl[look]) {
            cls = C.ns;
            kind = 'builtin';
          } else if (st.dot) {
            /* member access beats a pending declaration */
            cls = after === '(' ? C.func : C.prop;
            kind = cls === C.func ? 'fn' : 'prop';
          } else if (st.expect === 'def') {
            cls = C.def;
            kind = 'def';
          } else if (st.expect === 'cls') {
            cls = C.cls;
            kind = 'def';
          } else if (st.expect === 'ns') {
            cls = C.ns;
            kind = 'ns';
          } else if (st.prev === 'type' && (DECLARE_AFTER(after) || (after === '(' && !glued))) {
            /* `String name = "x"` · `size_t len;` · `int flags;` */
            cls = C.def;
            kind = 'def';
          } else if (cfg.colonKeys && after === ':') {
            /* Object-literal / destructuring keys. CSS and JSON
               have dedicated engines; this is for JS/TS objects. */
            cls = C.prop;
            kind = 'prop';
          } else if (after === '(' && cfg.callIsFunc !== false) {
            cls = C.func;
            kind = 'fn';
          } else if (cfg.upperConst && /^[A-Z][A-Z0-9_]{1,}$/.test(word)) {
            cls = C.def;
            kind = 'def';
          } else if (capType && isUpper(word.charAt(0)) && word.length > 1) {
            cls = C.type;
            kind = 'type';
          } else {
            cls = C.variable;
          }

          tok(i, end, cls);
          st.dot = false;
          /* a keyword opens an expectation that survives the types in
             between (`private static final String PREFIX`); types and
             builtins keep the pending expectation, plain identifiers
             and atoms consume it */
          if (kind === 'kw') {
            var role = decl[look];
            /* 'keep' = visibility/qualifier keywords: `public static
               final String PREFIX` keeps waiting for PREFIX */
            if (role !== 'keep') st.expect = role || null;
          } else if (kind !== 'type' && kind !== 'builtin' && kind !== 'atom') {
            st.expect = null;
          }
          st.prev = kind;
          i = end;
          continue;
        }

        /* ---- operators ---- */
        ops.lastIndex = i;
        var om = ops.exec(text);
        if (om) {
          tok(i, i + om[0].length, C.op);
          /* an assignment ends a declarator run; < > * & do not, so
             `char *msg = …` and `List<T> items` still resolve */
          if (om[0].indexOf('=') !== -1) st.prev = '';
          if (cfg.memberOps && cfg.memberOps[om[0]]) st.dot = true;
          i += om[0].length;
          continue;
        }

        /* ---- brackets / punctuation / interpolation frames ---- */
        if (ch === '(' || ch === ')') {
          tok(i, i + 1, C.paren);
          st.prev = '';
          if (ch === '(') st.expect = null;
          i++;
          continue;
        }
        if (ch === '[' || ch === ']') {
          tok(i, i + 1, C.bracket);
          i++;
          continue;
        }
        if (ch === '{' || ch === '}') {
          st.prev = '';
          st.expect = null;
          if (ch === '}') {
            var top = st.frames.length ? st.frames[st.frames.length - 1] : null;
            if (top && top.kind === 'brace') {
              st.frames.pop();
            } else if (top && top.kind === 'tmpl') {
              st.frames.pop();
              tok(i, i + 1, C.paren);
              i = enterString(top.spec, i + 1, false);
              continue;
            }
          } else {
            var top2 = st.frames.length ? st.frames[st.frames.length - 1] : null;
            if (top2 && (top2.kind === 'tmpl' || top2.kind === 'brace')) st.frames.push({ kind: 'brace' });
          }
          tok(i, i + 1, C.paren);
          i++;
          continue;
        }
        if (ch === '.') {
          tok(i, i + 1, C.ptn);
          st.dot = true;
          i++;
          continue;
        }
        if (ch === ',' || ch === ';' || ch === ':') {
          tok(i, i + 1, C.ptn);
          st.prev = '';
          if (ch === ';') st.expect = null;
          i++;
          continue;
        }
        i++;
      }
      return { tokens: toks, state: st };
    }

    return { name: cfg.name || 'stream', start: start, line: line };
  }

  /* ═══════════════════════════════════════════════════════════════
     4 · ENGINE B — HASH FAMILIES
     python / ruby / perl / shell / lua share the stream engine; their
     comment markers, string flavours and hooks are configured in §9.
     ═══════════════════════════════════════════════════════════════ */

  /* ═══════════════════════════════════════════════════════════════
     5 · ENGINE C — CSS
     ═══════════════════════════════════════════════════════════════ */
  var CSS_VALUE_KW = setList('inherit initial unset revert auto none normal bold bolder lighter italic oblique ' +
    'underline overline line-through block inline inline-block flex grid table none hidden visible collapse ' +
    'absolute relative fixed sticky static solid dashed dotted double groove ridge inset outset thin medium thick ' +
    'center left right top bottom middle baseline stretch space-between space-around flex-start flex-end ' +
    'uppercase lowercase capitalize nowrap pre wrap pointer move grab zoom-in zoom-out text overflow ' +
    'transparent currentColor break-all keep-all ' +
    'aliceblue antiquewhite aqua aquamarine azure beige bisque black blanchedalmond blue blueviolet brown burlywood ' +
    'cadetblue chartreuse chocolate coral cornflowerblue cornsilk crimson cyan darkblue darkcyan darkgoldenrod ' +
    'darkgray darkgreen darkgrey darkkhaki darkmagenta darkolivegreen darkorange darkorchid darkred darksalmon ' +
    'darkseagreen darkslateblue darkslategray darkslategrey darkturquoise darkviolet deeppink deepskyblue dimgray ' +
    'dimgrey dodgerblue firebrick floralwhite forestgreen fuchsia gainsboro ghostwhite gold goldenrod gray green ' +
    'greenyellow grey honeydew hotpink indianred indigo ivory khaki lavender lavenderblush lawngreen lemonchiffon ' +
    'lightblue lightcoral lightcyan lightgoldenrodyellow lightgray lightgreen lightgrey lightpink lightsalmon ' +
    'lightseagreen lightskyblue lightslategray lightslategrey lightsteelblue lightyellow lime limegreen linen ' +
    'magenta maroon mediumaquamarine mediumblue mediumorchid mediumpurple mediumseagreen mediumslateblue ' +
    'mediumspringgreen mediumturquoise mediumvioletred midnightblue mintcream mistyrose moccasin navajowhite navy ' +
    'oldlace olive olivedrab orange orangered orchid palegoldenrod palegreen paleturquoise palevioletred ' +
    'papayaWhip peachpuff peru pink plum powderblue purple rebeccapurple red rosybrown royalblue saddlebrown ' +
    'salmon sandybrown seagreen seashell sienna silver skyblue slateblue slategray slategrey snow springgreen ' +
    'steelblue tan teal thistle tomato turquoise violet wheat white whitesmoke yellow yellowgreen');

  var CSS_IDENT = /-?[A-Za-z_][\w-]*/y;

  function cssEngine(cfg) {
    cfg = cfg || {};
    var scss = !!cfg.scss;
    var blocks = [{ open: '/*', close: '*/', nested: false }];
    var strings = [
      { open: '"', esc: '\\', cls: C.string },
      { open: "'", esc: '\\', cls: C.string }
    ];
    function start() {
      return {
        frames: [], expect: null, dot: false,
        extra: { depth: 0, inValue: false, pendingProperty: false, parenDepth: 0, inline: false }
      };
    }

    function line(st, text) {
      var toks = [], n = text.length, i = 0;
      var depth = st.extra ? st.extra.depth : 0;
      var inValue = !!(st.extra && st.extra.inValue);
      var pendingProperty = !!(st.extra && st.extra.pendingProperty);
      var parenDepth = (st.extra && st.extra.parenDepth) || 0;
      var inline = !!(st.extra && st.extra.inline);

      function tok(a, b, cls) {
        if (b > a && cls) toks.push([a, b, cls]);
      }

      function escapedEol() {
        var slashes = 0, p = n - 1;
        while (p >= 0 && text.charAt(p--) === '\\') slashes++;
        return (slashes % 2) === 1;
      }

      while (i < n) {
        var ch = text.charAt(i);
        if (ch === ' ' || ch === '\t') {
          i++;
          continue;
        }
        var frame = st.frames.length ? st.frames[st.frames.length - 1] : null;
        if (frame && frame.kind === 'block') {
          var rb = scanBlock(text, i, frame.spec, frame.depth, false);
          tok(i, rb.end, C.comment);
          if (rb.closed) st.frames.pop();
          i = rb.end;
          continue;
        }
        if (frame && frame.kind === 'str') {
          var rs2 = scanStringBody(text, i, frame.spec);
          tok(i, rs2.end, C.string);
          if (rs2.closed) st.frames.pop();
          else if (!escapedEol() && !st.partial) st.frames.pop();
          i = rs2.end;
          continue;
        }
        if (text.startsWith('/*', i)) {
          var rc = scanBlock(text, i, blocks[0], 1, true);
          tok(i, rc.end, C.comment);
          if (!rc.closed) st.frames.push({ kind: 'block', spec: blocks[0], depth: 1 });
          i = rc.end;
          continue;
        }
        if (text.startsWith('//', i) && scss) {
          tok(i, n, C.comment);
          break;
        }

        /* at-rules */
        if (ch === '@') {
          var am = /@[-\w]+/y;
          am.lastIndex = i;
          var am2 = am.exec(text);
          if (am2) {
            tok(i, i + am2[0].length, C.keyword);
            i += am2[0].length;
            continue;
          }
        }
        /* strings */
        var spec = startsWithAny(text, i, strings);
        if (spec) {
          var rs = scanString(text, i, spec);
          tok(i, rs.end, C.string);
          /* CSS strings only cross a physical line through a trailing
             escape. A stray quote must not turn the rest of <style>
             green until another quote happens to appear. */
          if (!rs.closed && (escapedEol() || st.partial)) st.frames.push({ kind: 'str', spec: spec });
          i = rs.end;
          continue;
        }
        /* scss variable */
        if (scss && ch === '$') {
          var vm = /\$[-\w]+/y;
          vm.lastIndex = i;
          var vm2 = vm.exec(text);
          if (vm2) {
            tok(i, i + vm2[0].length, C.variable);
            i += vm2[0].length;
            continue;
          }
        }
        /* custom property / variable */
        if (ch === '-' && text.charAt(i + 1) === '-') {
          var cm2 = /--[-\w]+/y;
          cm2.lastIndex = i;
          var cm3 = cm2.exec(text);
          if (cm3) {
            var customEnd = i + cm3[0].length;
            var customIsProp = (depth > 0 || inline) && !inValue && nextNonSpace(text, customEnd) === ':';
            tok(i, customEnd, customIsProp ? C.prop : C.variable);
            if (customIsProp) pendingProperty = true;
            i += cm3[0].length;
            continue;
          }
        }
        /* hex colour */
        if (ch === '#' && /[\da-fA-F]/.test(text.charAt(i + 1) || '')) {
          var hm = /#[\da-fA-F]{3,8}\b/y;
          hm.lastIndex = i;
          var hm2 = hm.exec(text);
          if (hm2) {
            tok(i, i + hm2[0].length, C.atom);
            i += hm2[0].length;
            continue;
          }
        }
        /* class / id selector (never interpret a decimal or hex value
           as a selector while inside a declaration value) */
        if (!inValue && (ch === '.' || ch === '#') && identStart(text.charAt(i + 1))) {
          var selRe = /[.#][-\w]+/y;
          selRe.lastIndex = i;
          var sm = selRe.exec(text);
          if (sm) {
            tok(i, i + sm[0].length, ch === '.' ? C.cls : C.def);
            i += sm[0].length;
            continue;
          }
        }
        /* pseudo class / element. A colon after a pending property is
           the declaration separator and is handled below. */
        if (!inValue && !pendingProperty && ch === ':' &&
          (identStart(text.charAt(i + 1)) || text.charAt(i + 1) === ':')) {
          var pm = /::?[-\w]+(?:\([^)]*\))?/y;
          pm.lastIndex = i;
          var pm2 = pm.exec(text);
          if (pm2) {
            tok(i, i + pm2[0].length, C.modifier);
            i += pm2[0].length;
            continue;
          }
        }
        if (ch === '!' && text.startsWith('!important', i)) {
          tok(i, i + 10, C.modifier);
          i += 10;
          continue;
        }
        /* numbers with units */
        if (isDigit(ch) || (ch === '.' && isDigit(text.charAt(i + 1)))) {
          NUMU.lastIndex = i;
          var nm = NUMU.exec(text);
          if (nm) {
            tok(i, i + nm[0].length, C.number);
            i += nm[0].length;
            continue;
          }
        }
        /* identifiers: property / function / selector type / value keyword */
        if (identStart(ch) || (ch === '-' && identStart(text.charAt(i + 1)))) {
          CSS_IDENT.lastIndex = i;
          var im = CSS_IDENT.exec(text);
          if (!im) {
            i++;
            continue;
          }
          var word = im[0], end = i + word.length;
          var after = nextNonSpace(text, end);
          var cls;
          if ((depth > 0 || inline) && !inValue && after === ':') {
            cls = C.prop;
            pendingProperty = true;
          } else if (after === '(') {
            cls = C.func;
          } else if (inValue && (CSS_VALUE_KW[word] || CSS_VALUE_KW[word.toLowerCase()])) {
            cls = C.keyword;
          } else if (inValue) {
            /* CSS bare values (ease, cover, sans-serif, custom
               animation names) are atoms, not source variables. */
            cls = C.atom;
          } else if (after === ':' && depth === 0) {
            cls = C.cls;
          } else if (CSS_VALUE_KW[word] || CSS_VALUE_KW[word.toLowerCase()]) {
            cls = C.keyword;
          } else if (isUpper(word.charAt(0))) {
            cls = C.type;
          } else {
            /* Element selectors share the type colour in VS Code's
               CSS grammar; using HTML's tag colour made mixed
               blocks look like markup. */
            cls = C.type;
          }
          tok(i, end, cls);
          i = end;
          continue;
        }
        if (ch === '!' || ch === '~' || ch === '>' || ch === '+' || ch === '=' || ch === '*' || ch === '/' || ch === '%') {
          tok(i, i + 1, C.op);
          i++;
          continue;
        }
        if (ch === '{') {
          depth++;
          inValue = false;
          pendingProperty = false;
          tok(i, i + 1, C.paren);
          i++;
          continue;
        }
        if (ch === '}') {
          depth = Math.max(0, depth - 1);
          inValue = false;
          pendingProperty = false;
          parenDepth = 0;
          tok(i, i + 1, C.paren);
          i++;
          continue;
        }
        if (ch === '(' || ch === ')') {
          if (ch === '(') parenDepth++;
          else parenDepth = Math.max(0, parenDepth - 1);
          tok(i, i + 1, C.paren);
          i++;
          continue;
        }
        if (ch === ':') {
          tok(i, i + 1, pendingProperty ? C.op : C.ptn);
          if (pendingProperty) {
            inValue = true;
            pendingProperty = false;
          }
          i++;
          continue;
        }
        if (ch === ';') {
          tok(i, i + 1, C.ptn);
          if (parenDepth === 0) inValue = false;
          pendingProperty = false;
          i++;
          continue;
        }
        if (ch === '[' || ch === ']' || ch === ',' || ch === '.') {
          tok(i, i + 1, ch === '[' || ch === ']' ? C.bracket : C.ptn);
          i++;
          continue;
        }
        i++;
      }

      st.extra = {
        depth: depth,
        inValue: inValue,
        pendingProperty: pendingProperty,
        parenDepth: parenDepth,
        inline: inline
      };
      return { tokens: toks, state: st };
    }
    return { name: 'css', start: start, line: line };
  }

  /* ═══════════════════════════════════════════════════════════════
     6 · ENGINE D — MARKUP (html / xml / vue / svelte / php hybrid)
     ═══════════════════════════════════════════════════════════════ */
  var EMBED_NEEDLE = { js: '</script', json: '</script', css: '</style' };

  /* Stateful mixed-language router. PHP is a template boundary, so it may
     interrupt HTML, a tag attribute, CSS or JS and must return to exactly
     that language after ?>. */
  function markupEngine(cfg) {
    cfg = cfg || {};
    var php = !!cfg.php;
    var vue = !!cfg.vue;
    var interp = cfg.interp || (vue ? '{{' : null);
    var profiles = {};

    function start() {
      return {
        frames: [], expect: null, dot: false,
        extra: {
          mode: 'html', tag: '', closingTag: false,
          attrName: '', attrQuote: '', expectAttrValue: false, attrs: {},
          returnMode: 'html', subStates: {}
        }
      };
    }

    function lang(key) {
      var base = key;
      if (key === 'attr-css') base = 'css';
      else if (key === 'attr-js') base = 'javascript';
      else if (key === 'js') base = 'javascript';
      else if (key === 'php') base = 'php-inline';
      if (!profiles[key]) profiles[key] = profileFor(base);
      return profiles[key];
    }

    function subState(st, key, fresh) {
      var p = lang(key);
      if (fresh || !st.extra.subStates[key]) {
        st.extra.subStates[key] = p.start();
        if (key === 'attr-css' && st.extra.subStates[key].extra) {
          st.extra.subStates[key].extra.depth = 1;
          st.extra.subStates[key].extra.inline = true;
        }
      }
      return st.extra.subStates[key];
    }

    function phpOpenAt(text, at) {
      var low = text.slice(at, at + 6).toLowerCase();
      if (low.indexOf('<?xml') === 0) return null;
      if (low.indexOf('<?php') === 0 && !/[\w]/.test(text.charAt(at + 5) || ' ')) return { at: at, len: 5 };
      if (text.substr(at, 3) === '<?=') return { at: at, len: 3 };
      if (text.substr(at, 2) === '<?') return { at: at, len: 2 };
      return null;
    }

    function nextPhpOpen(text, from) {
      if (!php) return null;
      var at = text.indexOf('<?', from);
      while (at !== -1) {
        var hit = phpOpenAt(text, at);
        if (hit) return hit;
        at = text.indexOf('<?', at + 2);
      }
      return null;
    }

    /* ── cheap boundary scanner ──────────────────────────────────
       A `</style>`, `</script>` or `?>` that sits inside a string or a
       comment is data, not a boundary. v24.2 answered that by running a
       SECOND full tokenization of the rest of the line (plus a deep
       cloneState) for every embedded line — the single biggest cost in a
       large <style> block. This walks the text once, tracks only the
       quote/comment state that can hide a boundary, allocates nothing and
       is ~40x cheaper. */
    var CLOSE_SYNTAX = {
      css: { line: [], block: [['/*', '*/']], quotes: '"\'', esc: '\\' },
      js: { line: ['//'], block: [['/*', '*/']], quotes: '"\'`', esc: '\\' },
      json: { line: [], block: [], quotes: '"', esc: '\\' },
      php: { line: ['//', '#'], block: [['/*', '*/']], quotes: '"\'', esc: '\\' }
    };

    /* Resume the hiding state a previous line left open. */
    function openFrameKind(state) {
      var f = state && state.frames && state.frames.length
        ? state.frames[state.frames.length - 1] : null;
      if (!f) return null;
      if (f.kind === 'block') return { block: f.spec.close };
      if (f.kind === 'str' || f.kind === 'tmpl') return { quote: (f.spec.close || f.spec.open), esc: f.spec.esc };
      return null;
    }

    function safeClose(text, from, key, needle, state) {
      var cfgC = CLOSE_SYNTAX[key] || CLOSE_SYNTAX.js;
      var n = text.length, i = from, k;
      var resume = openFrameKind(state);
      var inBlock = resume && resume.block ? resume.block : null;
      var inQuote = resume && resume.quote ? resume.quote : null;
      var quoteEsc = resume && resume.esc;

      while (i < n) {
        if (inBlock) {
          var bEnd = text.indexOf(inBlock, i);
          if (bEnd === -1) return null;
          i = bEnd + inBlock.length;
          inBlock = null;
          continue;
        }
        if (inQuote) {
          var ch2 = text.charAt(i);
          if (quoteEsc && ch2 === quoteEsc) { i += 2; continue; }
          if (text.startsWith(inQuote, i)) { i += inQuote.length; inQuote = null; continue; }
          i++;
          continue;
        }
        if (text.charAt(i) === needle.charAt(0) &&
          text.substr(i, needle.length).toLowerCase() === needle) {
          /* `</script >` and `</style  >` are legal; consume to '>' */
          var end = i + needle.length;
          if (needle.charAt(0) === '<') {
            while (end < n && text.charAt(end) !== '>') end++;
            if (end < n) end++;
          }
          return { at: i, len: end - i, raw: text.slice(i, end) };
        }
        var ch = text.charAt(i);
        var hidden = false;
        for (k = 0; k < cfgC.line.length; k++) {
          if (text.startsWith(cfgC.line[k], i)) return null; /* rest of line is a comment */
        }
        for (k = 0; k < cfgC.block.length; k++) {
          if (text.startsWith(cfgC.block[k][0], i)) {
            inBlock = cfgC.block[k][1];
            i += cfgC.block[k][0].length;
            hidden = true;
            break;
          }
        }
        if (hidden) continue;
        if (cfgC.quotes.indexOf(ch) !== -1) {
          inQuote = ch;
          quoteEsc = cfgC.esc;
          i++;
          continue;
        }
        i++;
      }
      return null;
    }

    function line(st, text) {
      var toks = [], n = text.length, i = 0;
      if (!st.extra) st.extra = start().extra;
      var ex = st.extra;
      if (!ex.subStates) ex.subStates = {};

      function tok(a, b, cls) {
        if (b > a && cls) toks.push([a, b, cls]);
      }

      function appendProfile(key, from, to, fresh, partial) {
        if (to <= from) return;
        var p = lang(key), s = subState(st, key, fresh);
        s.partial = !!partial;
        var r = p.line(s, text.slice(from, to));
        r.state.partial = false;
        ex.subStates[key] = r.state;
        for (var q = 0; q < r.tokens.length; q++) {
          toks.push([from + r.tokens[q][0], from + r.tokens[q][1], r.tokens[q][2]]);
        }
      }

      function appendAttribute(from, to, partial) {
        if (to <= from) return;
        var key = ex.attrName.toLowerCase() === 'style' ? 'attr-css' :
          (/^on[a-z]+$/i.test(ex.attrName) ? 'attr-js' : null);
        if (!key) {
          tok(from, to, C.attrVal);
          return;
        }
        var before = toks.length;
        appendProfile(key, from, to, false, partial);
        /* Whitespace and unknown values still inherit the attribute
           colour instead of disappearing into the plain foreground. */
        if (toks.length === before) tok(from, to, C.attrVal);
      }

      function enterPhp(returnMode, hit) {
        ex.returnMode = returnMode;
        ex.mode = 'php';
        subState(st, 'php', true);
        tok(hit.at, hit.at + hit.len, C.proc);
        i = hit.at + hit.len;
      }

      function emitClosingTag(hit) {
        var raw = hit.raw, slash = raw.indexOf('/'), gt = raw.lastIndexOf('>');
        var nameStart = slash + 1;
        while (/\s/.test(raw.charAt(nameStart))) nameStart++;
        var nameEnd = nameStart;
        while (/[\w:.-]/.test(raw.charAt(nameEnd))) nameEnd++;
        tok(hit.at, hit.at + nameStart, C.ptn);
        tok(hit.at + nameStart, hit.at + nameEnd, C.tag);
        tok(hit.at + nameEnd, hit.at + (gt >= 0 ? gt + 1 : raw.length), C.ptn);
      }

      function finishTag() {
        var name = ex.tag.toLowerCase(), type = String(ex.attrs.type || '').toLowerCase();
        ex.attrName = '';
        ex.attrQuote = '';
        ex.expectAttrValue = false;
        if (ex.closingTag) {
          ex.mode = 'html';
        } else if (name === 'style') {
          ex.mode = 'css';
          subState(st, 'css', true);
        } else if (name === 'script' && (/json|importmap/.test(type))) {
          ex.mode = 'json';
          subState(st, 'json', true);
        } else if (name === 'script') {
          ex.mode = 'js';
          subState(st, 'js', true);
        } else {
          ex.mode = 'html';
        }
      }

      function scanAttribute() {
        var startAt = i;
        while (i < n) {
          var open = nextPhpOpen(text, i);
          var quoteAt = text.indexOf(ex.attrQuote, i);
          if (open && (quoteAt === -1 || open.at < quoteAt)) {
            appendAttribute(startAt, open.at, true);
            enterPhp('attr', open);
            return;
          }
          if (quoteAt !== -1) {
            appendAttribute(startAt, quoteAt, false);
            tok(quoteAt, quoteAt + 1, C.attrVal);
            if (ex.attrName) ex.attrs[ex.attrName.toLowerCase()] = text.slice(startAt, quoteAt);
            i = quoteAt + 1;
            ex.attrQuote = '';
            ex.attrName = '';
            ex.expectAttrValue = false;
            ex.mode = 'tag';
            return;
          }
          appendAttribute(startAt, n, true);
          i = n;
          return;
        }
      }

      function scanEmbedded(mode) {
        var key = mode, p = lang(key), state = subState(st, key, false);
        var close = safeClose(text, i, key, EMBED_NEEDLE[mode], state);
        var open = nextPhpOpen(text, i);
        if (open && (!close || open.at < close.at)) {
          appendProfile(key, i, open.at, false, true);
          enterPhp(mode, open);
          return;
        }
        if (close) {
          appendProfile(key, i, close.at, false, false);
          emitClosingTag(close);
          i = close.at + close.len;
          ex.mode = 'html';
          ex.subStates[key] = null;
          return;
        }
        appendProfile(key, i, n, false, false);
        i = n;
      }

      function scanPhp() {
        var state = subState(st, 'php', false);
        var close = safeClose(text, i, 'php', '?>', state);
        if (!close) {
          appendProfile('php', i, n, false, false);
          i = n;
          return;
        }
        appendProfile('php', i, close.at, false, false);
        tok(close.at, close.at + close.len, C.proc);
        i = close.at + close.len;
        ex.mode = ex.returnMode || 'html';
        ex.returnMode = 'html';
        ex.subStates.php = null;
      }

      while (i < n) {
        var mode = ex.mode;
        if (mode === 'php') {
          scanPhp();
          continue;
        }
        if (mode === 'css' || mode === 'js' || mode === 'json') {
          scanEmbedded(mode);
          continue;
        }
        if (mode === 'attr') {
          scanAttribute();
          continue;
        }

        var ch = text.charAt(i);
        if (mode === 'tag') {
          if (ch === ' ' || ch === '\t') {
            i++;
            continue;
          }
          var tagPhp = nextPhpOpen(text, i);
          if (tagPhp && tagPhp.at === i) {
            enterPhp('tag', tagPhp);
            continue;
          }
          if (ch === '>') {
            tok(i, i + 1, C.ptn);
            i++;
            finishTag();
            continue;
          }
          if (ch === '/' && text.charAt(i + 1) === '>') {
            tok(i, i + 2, C.ptn);
            i += 2;
            ex.mode = 'html';
            continue;
          }
          if (ch === '=') {
            tok(i, i + 1, C.op);
            ex.expectAttrValue = true;
            i++;
            continue;
          }
          if (ch === '"' || ch === "'") {
            ex.attrQuote = ch;
            ex.expectAttrValue = false;
            tok(i, i + 1, C.attrVal);
            i++;
            ex.mode = 'attr';
            subState(st, ex.attrName.toLowerCase() === 'style' ? 'attr-css' : 'attr-js', true);
            continue;
          }
          var aw = /[^\s=/>'"`]+/y;
          aw.lastIndex = i;
          var am = aw.exec(text);
          if (am) {
            if (ex.expectAttrValue) {
              tok(i, i + am[0].length, C.attrVal);
              if (ex.attrName) ex.attrs[ex.attrName.toLowerCase()] = am[0];
              ex.attrName = '';
              ex.expectAttrValue = false;
            } else {
              ex.attrName = am[0];
              tok(i, i + am[0].length, C.attr);
            }
            i += am[0].length;
            continue;
          }
          i++;
          continue;
        }

        /* HTML/XML content. Resume a multi-line comment before
           looking for tags so '<style>' inside a comment stays data. */
        var frame = st.frames.length ? st.frames[st.frames.length - 1] : null;
        if (frame && frame.kind === 'block') {
          var rb = scanBlock(text, i, frame.spec, frame.depth, false);
          tok(i, rb.end, frame.spec.cls || C.comment);
          if (rb.closed) st.frames.pop();
          else st.frames[st.frames.length - 1] = { kind: 'block', spec: frame.spec, depth: rb.depth };
          i = rb.end;
          continue;
        }
        if (text.startsWith('<!--', i)) {
          var cmSpec = { open: '<!--', close: '-->', cls: C.comment };
          var bc = scanBlock(text, i, cmSpec, 1, true);
          tok(i, bc.end, C.comment);
          if (!bc.closed) st.frames.push({ kind: 'block', spec: cmSpec, depth: 1 });
          i = bc.end;
          continue;
        }
        if (text.startsWith('<![CDATA[', i)) {
          var cdSpec = { open: '<![CDATA[', close: ']]>', cls: C.string2 };
          var cd = scanBlock(text, i, cdSpec, 1, true);
          tok(i, cd.end, C.string2);
          if (!cd.closed) st.frames.push({ kind: 'block', spec: cdSpec, depth: 1 });
          i = cd.end;
          continue;
        }
        var htmlPhp = nextPhpOpen(text, i);
        if (htmlPhp && htmlPhp.at === i) {
          enterPhp('html', htmlPhp);
          continue;
        }
        /* XML declarations outrank PHP short tags. */
        if (text.slice(i, i + 5).toLowerCase() === '<?xml' || text.startsWith('<!', i)) {
          var dr = /<[!?][^>]*>?/y;
          dr.lastIndex = i;
          var dm = dr.exec(text);
          if (dm) {
            tok(i, i + dm[0].length, C.proc);
            i += dm[0].length;
            continue;
          }
        }
        if (ch === '<') {
          var tr = /<\/?[a-zA-Z][\w:.-]*/y;
          tr.lastIndex = i;
          var tm = tr.exec(text);
          if (tm) {
            ex.closingTag = tm[0].charAt(1) === '/';
            var nameStart = ex.closingTag ? i + 2 : i + 1;
            ex.tag = tm[0].slice(ex.closingTag ? 2 : 1).toLowerCase();
            ex.attrs = {};
            ex.attrName = '';
            ex.expectAttrValue = false;
            tok(i, nameStart, C.ptn);
            tok(nameStart, i + tm[0].length, C.tag);
            i += tm[0].length;
            ex.mode = 'tag';
            continue;
          }
        }
        if (interp && text.startsWith(interp, i)) {
          var interpClose = interp === '{{' ? '}}' : interp;
          var ei = text.indexOf(interpClose, i + interp.length);
          if (ei === -1) {
            tok(i, n, C.meta);
            i = n;
          } else {
            tok(i, i + interp.length, C.meta);
            appendProfile('js', i + interp.length, ei, true, false);
            tok(ei, ei + interpClose.length, C.meta);
            i = ei + interpClose.length;
          }
          continue;
        }
        if (ch === '&') {
          var em = /&[#\w]{1,14};/y;
          em.lastIndex = i;
          var entity = em.exec(text);
          if (entity) {
            tok(i, i + entity[0].length, C.atom);
            i += entity[0].length;
            continue;
          }
        }
        var stop = n;
        var candidates = [text.indexOf('<', i + 1), text.indexOf('&', i + 1),
        interp ? text.indexOf(interp, i + 1) : -1];
        for (var c = 0; c < candidates.length; c++) {
          if (candidates[c] >= 0 && candidates[c] < stop) stop = candidates[c];
        }
        var end = stop <= i ? i + 1 : stop;
        /* Text nodes use the editor foreground, matching CM6/VS Code.
           Calling prose a variable made a failed mode switch look
           like an entire green CSS block. */
        i = end;
      }
      return { tokens: toks, state: st };
    }
    return { name: 'markup', start: start, line: line };
  }


  /* ═══════════════════════════════════════════════════════════════
     7 · ENGINE E — SQL / YAML / PROPERTIES
     ═══════════════════════════════════════════════════════════════ */
  function yamlEngine() {
    function start() {
      return { frames: [], expect: null, dot: false, extra: { blockIndent: -1, blockKey: '' } };
    }
    function line(st, text) {
      var toks = [], n = text.length, i = 0;
      function tok(a, b, cls) {
        if (b > a && cls) toks.push([a, b, cls]);
      }
      if (text.indexOf('\t') === 0 && !text.trim()) return { tokens: toks, state: st };
      var indent = 0;
      while (indent < n && text.charAt(indent) === ' ') indent++;

      /* block scalar body */
      if (st.extra.blockIndent >= 0) {
        if (!text.trim()) return { tokens: toks, state: st };
        if (indent > st.extra.blockIndent) {
          tok(indent, n, C.string);
          return { tokens: toks, state: st };
        }
        st.extra.blockIndent = -1;
      }

      /* doc markers */
      var dm = /^(---|\.\.\.)\s*$/.exec(text);
      if (dm) {
        tok(0, dm[0].length, C.meta);
        return { tokens: toks, state: st };
      }
      /* comment-only tail helper */
      function commentFrom(idx) {
        /* '#' starts a comment when at line start or preceded by space */
        var c = idx;
        while (c < n) {
          if (text.charAt(c) === '#' && (c === 0 || text.charAt(c - 1) === ' ' || text.charAt(c - 1) === '\t' || text.charAt(c - 1) === '-')) {
            return c;
          }
          c++;
        }
        return -1;
      }
      if (indent < n && text.charAt(indent) === '#') {
        tok(indent, n, C.comment);
        return { tokens: toks, state: st };
      }
      /* list marker */
      if (text.charAt(indent) === '-' && (n === indent + 1 || text.charAt(indent + 1) === ' ')) {
        tok(indent, indent + 1, C.ptn);
        i = indent + 1;
        while (i < n && text.charAt(i) === ' ') i++;
      } else {
        i = indent;
      }
      /* key detection: first ':' followed by space/EOL, outside quotes */
      var keyEnd = -1, k = i, q = null;
      while (k < n) {
        var cch = text.charAt(k);
        if (q) {
          if (cch === q) q = null;
          k++;
          continue;
        }
        if (cch === '"' || cch === "'") {
          q = cch;
          k++;
          continue;
        }
        if (cch === '#' && (k === 0 || text.charAt(k - 1) === ' ')) break;
        if (cch === ':' && (k + 1 >= n || text.charAt(k + 1) === ' ' || text.charAt(k + 1) === '\t')) {
          keyEnd = k;
          break;
        }
        if (cch === '{' || cch === '[') break;
        k++;
      }
      if (keyEnd > i) {
        tok(i, keyEnd, C.prop);
        tok(keyEnd, keyEnd + 1, C.ptn);
        i = keyEnd + 1;
        while (i < n && text.charAt(i) === ' ') i++;
      }
      /* rest of line: scalars */
      while (i < n) {
        var ch = text.charAt(i);
        if (ch === ' ' || ch === '\t') {
          i++;
          continue;
        }
        if (ch === '#') {
          break;
        }
        if (ch === '"' || ch === "'") {
          var spec = { open: ch, esc: ch === "'" ? "'" : '\\', multi: false, cls: C.string };
          var rs = scanString(text, i, spec);
          tok(i, rs.end, C.string);
          i = rs.end;
          continue;
        }
        if (ch === '|' || ch === '>') {
          var bm = /[|>][+-]?\d*/y;
          bm.lastIndex = i;
          var bm2 = bm.exec(text);
          tok(i, i + bm2[0].length, C.meta);
          st.extra.blockIndent = indent;
          i += bm2[0].length;
          continue;
        }
        if (ch === '&' || ch === '*') {
          var lr = /[&*][\w.-]+/y;
          lr.lastIndex = i;
          var lr2 = lr.exec(text);
          if (lr2) {
            tok(i, i + lr2[0].length, C.label);
            i += lr2[0].length;
            continue;
          }
        }
        if (ch === '!') {
          var nr = /!![\w:.-]+|!<[^>]*>/y;
          nr.lastIndex = i;
          var nr2 = nr.exec(text);
          if (nr2) {
            tok(i, i + nr2[0].length, C.meta);
            i += nr2[0].length;
            continue;
          }
        }
        if (ch === '{' || ch === '[' || ch === '}' || ch === ']' || ch === ',') {
          tok(i, i + 1, C.ptn);
          i++;
          continue;
        }
        if (isDigit(ch) || (ch === '-' && isDigit(text.charAt(i + 1)))) {
          var dr = /[+-]?(?:\d[\d_]*(?:\.[\d_]*)?(?:[eE][+-]?\d+)?|\d{4}-\d{2}-\d{2}(?:[T ][\d:.]+Z?)?)[a-zA-Z]*/y;
          dr.lastIndex = i;
          var dr2 = dr.exec(text);
          if (dr2) {
            tok(i, i + dr2[0].length, C.number);
            i += dr2[0].length;
            continue;
          }
        }
        if (identStart(ch)) {
          IDENT.lastIndex = i;
          var im = IDENT.exec(text);
          var w = im[0].toLowerCase(), end = i + im[0].length;
          var cls = C.variable;
          if (w === 'true' || w === 'false' || w === 'yes' || w === 'no' || w === 'on' || w === 'off') cls = C.bool;
          else if (w === 'null' || w === 'none' || im[0] === '~') cls = C.atom;
          tok(i, end, cls);
          i = end;
          continue;
        }
        if (ch === ':' || ch === '?' || ch === '-' || ch === '*' || ch === '|' || ch === '>' || ch === '=') {
          tok(i, i + 1, C.op);
          i++;
          continue;
        }
        i++;
      }
      var ci2 = commentFrom(indent);
      if (ci2 >= 0) tok(ci2, n, C.comment);
      return { tokens: toks, state: st };
    }
    return { name: 'yaml', start: start, line: line };
  }

  function propsEngine(toml) {
    function start() {
      return { frames: [], expect: null, dot: false, extra: { section: false } };
    }
    function line(st, text) {
      var toks = [], n = text.length, i = 0;
      function tok(a, b, cls) {
        if (b > a && cls) toks.push([a, b, cls]);
      }
      while (i < n && (text.charAt(i) === ' ' || text.charAt(i) === '\t')) i++;
      if (i >= n) return { tokens: toks, state: st };
      if (text.charAt(i) === '#' || (!toml && text.charAt(i) === ';')) {
        tok(i, n, C.comment);
        return { tokens: toks, state: st };
      }
      /* [section] */
      if (text.charAt(i) === '[') {
        var sm = /\[[^\]]*\]/y;
        sm.lastIndex = i;
        var sm2 = sm.exec(text);
        if (sm2) {
          tok(i, i + sm2[0].length, C.head);
          i += sm2[0].length;
          var tail = text.indexOf('#', i);
          if (tail >= 0) tok(tail, n, C.comment);
          return { tokens: toks, state: st };
        }
      }
      /* key = value (both = and : accepted outside quotes) */
      var eq = -1, k = i;
      while (k < n) {
        var ch = text.charAt(k);
        if (ch === '"' || ch === "'") break;
        if ((ch === '=' && toml) || (ch === '=' || ch === ':')) {
          eq = k;
          break;
        }
        if (ch === '#') break;
        k++;
      }
      if (eq > i) {
        /* dotted keys → each segment a property */
        var seg = text.slice(i, eq);
        var parts = seg.split('.');
        var pos = i;
        for (var p = 0; p < parts.length; p++) {
          var piece = parts[p];
          var lead = piece.length - piece.replace(/^\s+/, '').length;
          var trail = piece.length - piece.replace(/\s+$/, '').length;
          if (piece.trim()) {
            tok(pos + lead, pos + piece.length - trail, C.prop);
          }
          pos += piece.length;
          if (p < parts.length - 1) {
            tok(pos, pos + 1, C.ptn);
            pos += 1;
          }
        }
        tok(eq, eq + 1, C.op);
        i = eq + 1;
      }
      while (i < n) {
        var c2 = text.charAt(i);
        if (c2 === ' ' || c2 === '\t') {
          i++;
          continue;
        }
        if (c2 === '#' || (!toml && c2 === ';')) {
          tok(i, n, C.comment);
          break;
        }
        if (c2 === '"') {
          var rs = scanString(text, i, { open: '"', esc: '\\', cls: C.string });
          tok(i, rs.end, C.string);
          i = rs.end;
          continue;
        }
        if (c2 === "'") {
          var rs2 = scanString(text, i, { open: "'", esc: toml ? null : '\\', cls: C.string });
          tok(i, rs2.end, C.string);
          i = rs2.end;
          continue;
        }
        if (c2 === '[' || c2 === ']' || c2 === '{' || c2 === '}' || c2 === ',') {
          tok(i, i + 1, C.ptn);
          i++;
          continue;
        }
        if (isDigit(c2)) {
          NUMB.lastIndex = i;
          var nm = NUMB.exec(text);
          if (nm) {
            tok(i, i + nm[0].length, C.number);
            i += nm[0].length;
            continue;
          }
        }
        if (c2 === '\\' && (i + 1 < n)) {
          i += 2;
          continue;
        }
        if (identStart(c2) || c2 === '-' || c2 === '+' || c2 === '.') {
          IDENT.lastIndex = i;
          var im = IDENT.exec(text);
          var w = im ? im[0] : text.charAt(i);
          var wl = w.toLowerCase();
          tok(i, i + w.length, (wl === 'true' || wl === 'false') ? C.bool : C.variable);
          i += w.length;
          continue;
        }
        i++;
      }
      return { tokens: toks, state: st };
    }
    return { name: toml ? 'toml' : 'properties', start: start, line: line };
  }

  /* ═══════════════════════════════════════════════════════════════
     8 · ENGINE F — MARKDOWN (with embedded code fences)
     ═══════════════════════════════════════════════════════════════ */
  var MD_FENCE_RE = /^(\s*)(`{3,}|~{3,})\s*([\w+#.-]*)/;

  function markdownEngine() {
    function start() {
      return { frames: [], expect: null, dot: false, extra: { fence: null, fenceState: null, lang: '', prevText: false } };
    }
    function line(st, text) {
      var toks = [], n = text.length, i = 0;
      function tok(a, b, cls) {
        if (b > a && cls) toks.push([a, b, cls]);
      }

      /* inside a fenced code block */
      if (st.extra.fence) {
        var fm = MD_FENCE_RE.exec(text);
        if (fm && fm[2].charAt(0) === st.extra.fence && fm[3] === '') {
          tok(0, n, C.meta);
          st.extra.fence = null;
          st.extra.fenceState = null;
          st.extra.lang = '';
          return { tokens: toks, state: st };
        }
        var prof = st.extra.lang ? profileFor(st.extra.lang) : null;
        if (prof) {
          if (!st.extra.fenceState) st.extra.fenceState = prof.start();
          var fr = prof.line(st.extra.fenceState, text);
          st.extra.fenceState = fr.state;
          for (var f2 = 0; f2 < fr.tokens.length; f2++) toks.push(fr.tokens[f2]);
        } else {
          tok(0, n, C.string2);
        }
        return { tokens: toks, state: st };
      }

      var fence = MD_FENCE_RE.exec(text);
      if (fence) {
        tok(fence[1].length, n, C.meta);
        st.extra.fence = fence[2].charAt(0);
        st.extra.lang = (fence[3] || '').toLowerCase();
        st.extra.fenceState = null;
        return { tokens: toks, state: st };
      }
      if (/^\s{0,3}(=+|-+)\s*$/.test(text) && st.extra.prevText) {
        tok(0, n, C.head);
        return { tokens: toks, state: st };
      }
      if (/^\s{0,3}([*_-])\s*(\1\s*){2,}$/.test(text)) {
        tok(0, n, C.meta);
        return { tokens: toks, state: st };
      }
      var hm = /^\s{0,3}(#{1,6})(\s+)/.exec(text);
      if (hm) {
        tok(0, hm[1].length, C.meta);
        tok(hm[0].length, n, C.head);
        return { tokens: toks, state: st };
      }
      var qm = /^\s{0,3}(>+\s?)/.exec(text);
      var quoteOff = 0;
      if (qm) {
        tok(0, qm[0].length, C.quote);
        quoteOff = qm[0].length;
      }
      var lm = /^(\s*)(?:([-*+])|(\d{1,9}[.)]))(\s+)/.exec(text);
      var li = quoteOff;
      if (lm && lm.index === quoteOff) {
        tok(quoteOff + lm[1].length, lm[0].length, lm[2] ? C.meta : C.number);
        li = lm[0].length;
      }
      i = li;
      var isQuote = !!qm;
      while (i < n) {
        var ch = text.charAt(i);
        if (ch === ' ') {
          i++;
          continue;
        }
        if (ch === '\\' && i + 1 < n) {
          tok(i, i + 2, C.escape);
          i += 2;
          continue;
        }
        if (ch === '`') {
          var tick = /`+/y;
          tick.lastIndex = i;
          var tm = tick.exec(text);
          var close = text.indexOf(tm[0], i + tm[0].length);
          if (close !== -1) {
            tok(i, close + tm[0].length, C.string2);
            i = close + tm[0].length;
            continue;
          }
        }
        if (text.startsWith('**', i) || text.startsWith('__', i)) {
          var mark = text.slice(i, i + 2);
          var e2 = text.indexOf(mark, i + 2);
          if (e2 !== -1) {
            tok(i, i + 2, C.strong);
            tok(i + 2, e2, C.strong);
            tok(e2, e2 + 2, C.strong);
            i = e2 + 2;
            continue;
          }
        }
        if (text.startsWith('~~', i)) {
          var e3 = text.indexOf('~~', i + 2);
          if (e3 !== -1) {
            tok(i, e3 + 2, C.strike);
            i = e3 + 2;
            continue;
          }
        }
        if (ch === '*' || ch === '_') {
          var e4 = text.indexOf(ch, i + 1);
          if (e4 > i + 1) {
            tok(i, i + 1, C.em);
            tok(i + 1, e4, C.em);
            tok(e4, e4 + 1, C.em);
            i = e4 + 1;
            continue;
          }
        }
        if (ch === '[') {
          var lm2 = /\[([^\]]*)\]\(([^)\s]+)(?:\s+"[^"]*")?\)/y;
          lm2.lastIndex = i;
          var lm3 = lm2.exec(text);
          if (lm3) {
            var labelStart = i + 1;
            var labelEnd = labelStart + lm3[1].length;
            tok(i, i + 1, C.ptn);
            tok(labelStart, labelEnd, C.link);
            tok(labelEnd, labelEnd + 2, C.ptn);
            var urlStart = labelEnd + 2;
            tok(urlStart, urlStart + lm3[2].length, C.url);
            tok(i + lm3[0].length - 1, i + lm3[0].length, C.ptn);
            i += lm3[0].length;
            continue;
          }
          var fn = /\[\^?[\w -]*\](?!\()/y;
          fn.lastIndex = i;
          var fn2 = fn.exec(text);
          if (fn2) {
            tok(i, i + fn2[0].length, C.label);
            i += fn2[0].length;
            continue;
          }
        }
        if (text.startsWith('<', i)) {
          var htm = /<[^>\s]+(?:[^>]*)>/y;
          htm.lastIndex = i;
          var hm2 = htm.exec(text);
          if (hm2 && hm2[0].length < 220) {
            tok(i, i + hm2[0].length, C.tag);
            i += hm2[0].length;
            continue;
          }
        }
        if ((ch === 'h' || ch === 'w') && !/[\w]/.test(text.charAt(i - 1) || ' ') && /https?:\/\//.test(text.slice(i, i + 8))) {
          var u = /https?:\/\/[^\s<>)\]]+/y;
          u.lastIndex = i;
          var um = u.exec(text);
          if (um && um[0].length > 8) {
            tok(i, i + um[0].length, C.url);
            i += um[0].length;
            continue;
          }
        }
        i++;
      }
      st.extra.prevText = text.trim().length > 0 && !isQuote;
      return { tokens: toks, state: st };
    }
    return { name: 'markdown', start: start, line: line };
  }

  function plainEngine() {
    function start() {
      return { frames: [], expect: null, dot: false, extra: null };
    }
    function line(st) {
      return { tokens: [], state: st };
    }
    return { name: 'plaintext', start: start, line: line };
  }

  /* ═══════════════════════════════════════════════════════════════
     9 · LANGUAGE CONFIGS
     ═══════════════════════════════════════════════════════════════ */
  var KW_JS = setList('var let const function class extends implements interface type enum export import from default return if else for while do switch case break continue new delete typeof instanceof in of void yield await async static get set this super try catch finally throw debugger with as satisfies readonly declare namespace abstract public private protected override module require');
  var KW_PY = setList('def class return if elif else for while break continue pass import from as with try except finally raise lambda yield global nonlocal assert del in is not and or await async match case self cls print');
  var KW_C = setList('auto break case char const continue default do double else enum extern float for goto if inline int long register restrict return short signed sizeof static struct switch typedef union unsigned void volatile while class namespace template typename using public private protected virtual override final new delete this nullptr true false operator friend explicit constexpr thread_local mutable noexcept struct');
  var KW_JAVA = setList('abstract assert boolean break byte case catch char class const continue default do double else enum extends final finally float for if implements import instanceof int interface long native new package private protected public return short static strictfp super switch synchronized this throw throws transient try void volatile while var record sealed permits yield');
  var KW_CS = setList('abstract as base bool break byte case catch char checked class const continue decimal default delegate do double else enum event explicit extern false finally fixed float for foreach goto if implicit in int interface internal is lock long namespace new null object operator out override params private protected public readonly ref return sbyte sealed short sizeof stackalloc static string struct switch this throw true try typeof uint ulong unchecked unsafe ushort using virtual void volatile while var record init get set value async await yield when');
  var KW_GO = setList('break case chan const continue default defer else fallthrough for func go goto if import interface map package range return select struct switch type var nil true false iota func');
  var KW_RUST = setList('as async await break const continue crate dyn else enum extern false fn for if impl in let loop match mod move mut pub ref return self Self static struct super trait true type unsafe use where while yield macro_rules');
  var KW_SWIFT = setList('associatedtype class deinit enum extension fileprivate func import init inout internal let open operator private precedencegroup protocol public rethrows static struct subscript typealias var break case catch continue default defer do else fallthrough for guard if in repeat return switch throw throws try where while as is any some await async actor');
  var KW_KT = setList('as break class continue do else false for fun if in interface is null object package return super this throw true try typealias val var when while by catch constructor delegate dynamic field finally get import init param property receiver set setparam where actual abstract annotation companion const crossinline data enum expect external final infix inline inner internal lateinit noinline open operator out override private protected public reified sealed suspend tailrec vararg');
  var KW_DART = setList('abstract as assert async await break case catch class const continue covariant default deferred do dynamic else enum export extends extension external factory false final finally for Function get hide if implements import in interface is late library mixin new null on operator part required rethrow return set show static super switch sync this throw true try typedef var void while with yield');
  var KW_PHP = setList('abstract and array as break callable case catch class clone const continue declare default do echo else elseif empty enddeclare endfor endforeach endif endswitch endwhile enum extends final finally fn for foreach function global goto if implements include include_once instanceof insteadof interface isset list match namespace new or print private protected public readonly require require_once return static switch throw trait try unset use var while xor yield true false null');
  var KW_RB = setList('alias and begin break case class def defined? do else elsif end ensure false for if in module next nil not or redo rescue retry return self super then true undef unless until when while yield require require_relative attr_accessor attr_reader attr_writer include extend prepend lambda proc puts raise loop');
  var KW_PL = setList('my our local sub use package require if elsif else unless while until for foreach do return last next redo goto die warn eval print printf say chomp chop split join push pop shift unshift keys values each sort reverse map grep length substr index scalar defined undef exists delete bless ref want eq ne lt gt le ge cmp and or not xor');
  var KW_SH = setList('if then else elif fi for while until do done case esac function return in local export unset readonly declare typeset shift source alias unalias set trap exit break continue eval exec test time select');
  var KW_SQL = setList('select from where insert into values update set delete create table alter drop index view join inner left right full outer cross on group by having order asc desc limit offset union all distinct as and or not null is between like ilike in exists case when then else end primary key foreign references unique check default autoincrement cascade constraint begin commit rollback transaction grant revoke with returning using over partition window recursive temporary if replace truncate');
  var KW_LUA = setList('and break do else elseif end false for function goto if in local nil not or repeat return then true until while self');

  var BUILTINS_JS = setList('Object Array String Number Boolean Math JSON Promise Symbol Map Set WeakMap WeakSet Date RegExp Error console window document process globalThis setTimeout setInterval clearTimeout clearInterval fetch require module exports Reflect Proxy BigInt Intl');
  var BUILTINS_PY = setList('print len range str int float list dict set tuple bool type isinstance super enumerate zip map filter sum min max abs sorted open input format repr getattr setattr hasattr next iter id hash bytes bytearray frozenset complex Exception ValueError TypeError');
  var BUILTINS_C = setList('printf malloc free calloc realloc memcpy memset strlen strcpy fopen fclose fgets puts scanf NULL stderr stdin stdout exit abort assert getenv sprintf snprintf system');
  var TYPES_C = setList('int char float double void bool long short size_t ssize_t uint8_t uint16_t uint32_t uint64_t int8_t int16_t int32_t int64_t FILE wchar_t ptrdiff_t time_t unsigned signed');
  var TYPES_JAVA = setList('String Integer Long Double Float Boolean Character Object List Map Set ArrayList HashMap IOException Exception Thread Runnable Byte Short Void Number Math StringBuilder');
  var TYPES_CS = setList('String Int32 Int64 Double Single Boolean Char Object Task List Dictionary IEnumerable IList Exception Byte Decimal Guid DateTime');
  var TYPES_GO = setList('string int int8 int16 int32 int64 uint uint8 uint16 uint32 uint64 uintptr float32 float64 complex64 complex128 bool byte rune error any');
  var BUILTINS_GO = setList('make new len cap append copy delete panic recover close print println clear min max');
  var TYPES_RUST = setList('i8 i16 i32 i64 i128 u8 u16 u32 u64 u128 f32 f64 usize isize bool char str String Vec Option Result Box Rc Arc HashMap HashSet');
  var BUILTINS_RUST = setList('println print format vec panic todo unimplemented assert assert_eq dbg matches write writeln');
  var TYPES_SWIFT = setList('Int Int8 Int16 Int32 Int64 Double Float Bool String Character Array Dictionary Set Optional Any AnyObject Void Self UInt');
  var TYPES_KT = setList('Int Long Double Float Boolean String Char Any Unit List Map Set Array Nothing');
  var TYPES_DART = setList('int double num String bool List Map Set dynamic Object Future void Null Iterable');
  var TYPES_PHP = setList('string int float bool object mixed void never iterable array callable self static parent');
  var BUILTINS_PHP = setList('count strlen array_map array_filter in_array explode implode sprintf printf var_dump print_r json_encode json_decode preg_match str_replace strpos substr trim date isset empty define array_push array_keys array_values');
  var BUILTINS_RB = setList('puts print p require_relative gets loop map each select reduce inject attr_accessor new to_s to_i to_a');
  var BUILTINS_SH = setList('echo printf read cd pwd ls cp mv rm mkdir rmdir touch cat grep sed awk cut tr sort uniq head tail wc find xargs chmod chown ln df du ps kill sleep tar curl wget git npm node python pip make sudo which type command test export');
  var BUILTINS_SQL = setList('count sum avg min max coalesce nullif cast now current_date current_timestamp upper lower length trim substring concat round abs floor ceil row_number rank dense_rank lag lead');
  var TYPES_SQL = setList('int integer smallint bigint tinyint decimal numeric float real double precision char varchar nvarchar text blob clob date time datetime timestamp boolean bool json jsonb uuid serial bigserial money');
  var BUILTINS_LUA = setList('require print pairs ipairs type tostring tonumber setmetatable getmetatable assert error pcall xpcall select unpack rawget rawset next');

  var STR_DQ = { open: '"', esc: '\\', multi: false, cls: C.string };
  var STR_SQ = { open: "'", esc: '\\', multi: false, cls: C.string };
  var STR_CHR = { open: "'", esc: '\\', multi: false, cls: C.string2 };
  var BLK_CSTYLE = { open: '/*', close: '*/', nested: false, cls: C.comment };
  var BLK_NEST = { open: '/*', close: '*/', nested: true, cls: C.comment };

  var JSDOC = { open: '/**', close: '*/', nested: false, cls: C.comment };

  /* --- per language factories -------------------------------------- */
  function jsProfile() {
    return streamEngine({
      name: 'javascript',
      keywords: KW_JS,
      builtins: BUILTINS_JS,
      atoms: setList('true false null undefined NaN Infinity'),
      lineComments: ['//'],
      blocks: [BLK_CSTYLE],
      strings: [
        { open: '`', esc: '\\', multi: true, cls: C.string, interp: '${' },
        STR_DQ, STR_SQ
      ],
      declKeywords: { 'function': 'def', 'class': 'cls', 'const': 'def', 'let': 'def', 'var': 'def', 'new': 'cls', 'extends': 'cls', 'implements': 'cls', 'interface': 'cls', 'type': 'cls', 'enum': 'cls', 'import': 'ns', 'from': 'ns', 'export': 'keep', 'async': 'keep', 'static': 'keep', 'readonly': 'keep', 'abstract': 'keep', 'public': 'keep', 'private': 'keep', 'protected': 'keep', 'override': 'keep', 'declare': 'keep', 'get': 'def', 'set': 'def', 'namespace': 'ns' },
      capitalType: true,
      upperConst: true,
      colonKeys: true,
      ops: /[=+\-*/%<>!&|^~?:]+/y,
      numUnits: false
    });
  }
  function jsonProfile() {
    return streamEngine({
      name: 'json',
      keywords: {},
      atoms: setList('true false null'),
      lineComments: [],
      blocks: [],
      strings: [{ open: '"', esc: '\\', cls: C.string }],
      quotedKeys: true,
      capitalType: false
    });
  }
  function pythonProfile() {
    return streamEngine({
      name: 'python',
      keywords: KW_PY,
      builtins: BUILTINS_PY,
      atoms: setList('None True False'),
      types: setList('int float str bytes bool list dict set tuple object type Exception'),
      wordOperators: setList('and or not in is'),
      lineComments: ['#'],
      blocks: [],
      strings: [
        { open: '"""', close: '"""', esc: '\\', multi: true, cls: C.doc },
        { open: "'''", close: "'''", esc: '\\', multi: true, cls: C.doc },
        { open: 'r"""', close: '"""', esc: null, multi: true, cls: C.string2 },
        { open: "r'''", close: "'''", esc: null, multi: true, cls: C.string2 },
        { open: 'f"', close: '"', esc: '\\', cls: C.string, interp: '{' },
        { open: "f'", close: "'", esc: '\\', cls: C.string, interp: '{' },
        { open: 'r"', close: '"', esc: null, cls: C.string2 },
        { open: "r'", close: "'", esc: null, cls: C.string2 },
        STR_DQ, STR_SQ
      ],
      decorator: /@[\w.]*/y,
      decoratorCls: C.meta,
      declKeywords: { 'def': 'def', 'class': 'cls', 'import': 'ns', 'from': 'ns', 'as': 'ns', 'raise': 'cls', 'except': 'cls', 'for': 'def', 'while': 'def', 'with': 'def', 'lambda': 'def' },
      capitalType: true,
      upperConst: true
    });
  }
  function cProfile(lang) {
    var isCpp = lang === 'cpp' || lang === 'c++';
    return streamEngine({
      name: isCpp ? 'cpp' : 'c',
      keywords: KW_C,
      types: TYPES_C,
      builtins: BUILTINS_C,
      atoms: setList('true false nullptr NULL'),
      wordOperators: setList('sizeof new delete'),
      lineComments: ['//'],
      blocks: [{ open: '/*', close: '*/', nested: false, cls: C.comment }],
      strings: [STR_DQ, STR_CHR],
      capitalType: true,
      macroHooks: true,
      hooks: [
        { re: /#\s*\w+/y, cls: C.proc },
        { re: /<[a-zA-Z0-9_./+-]*>/y, cls: C.string2 }
      ],
      upperConst: true,
      declKeywords: { 'struct': 'cls', 'class': 'cls', 'enum': 'cls', 'union': 'cls', 'typedef': 'cls', 'namespace': 'ns', 'using': 'ns', 'new': 'cls', 'const': 'def', 'template': 'cls', 'typename': 'cls', 'auto': 'def', 'static': 'keep', 'inline': 'keep', 'virtual': 'keep', 'explicit': 'keep', 'mutable': 'keep', 'constexpr': 'keep', 'thread_local': 'keep', 'override': 'keep', 'final': 'keep', 'extern': 'keep' }
    });
  }
  function javaProfile() {
    return streamEngine({
      name: 'java',
      keywords: KW_JAVA,
      types: TYPES_JAVA,
      atoms: setList('true false null'),
      lineComments: ['//'],
      blocks: [{ open: '/**', close: '*/', cls: C.doc }, BLK_CSTYLE],
      strings: [STR_DQ, STR_CHR],
      decorator: /@[\w.]*/y,
      capitalType: true,
      upperConst: true,
      declKeywords: { 'class': 'cls', 'interface': 'cls', 'enum': 'cls', 'record': 'cls', 'extends': 'cls', 'implements': 'cls', 'new': 'cls', 'instanceof': 'cls', 'import': 'ns', 'package': 'ns', 'public': 'keep', 'private': 'keep', 'protected': 'keep', 'static': 'keep', 'final': 'keep', 'abstract': 'keep', 'synchronized': 'keep', 'native': 'keep', 'transient': 'keep', 'volatile': 'keep', 'override': 'keep' }
    });
  }
  function csharpProfile() {
    return streamEngine({
      name: 'csharp',
      keywords: KW_CS,
      types: TYPES_CS,
      atoms: setList('true false null'),
      lineComments: ['//'],
      blocks: [{ open: '///', close: '*/', cls: C.doc }, BLK_CSTYLE],
      strings: [
        { open: '@"', close: '"', esc: null, cls: C.string2 },
        { open: '$"', close: '"', esc: '\\', cls: C.string, interp: '{' },
        STR_DQ, STR_CHR
      ],
      capitalType: true,
      upperConst: true,
      declKeywords: { 'class': 'cls', 'struct': 'cls', 'interface': 'cls', 'enum': 'cls', 'new': 'cls', 'namespace': 'ns', 'using': 'ns', 'typeof': 'cls', 'is': 'cls', 'as': 'cls', 'const': 'def', 'var': 'def', 'public': 'keep', 'private': 'keep', 'protected': 'keep', 'internal': 'keep', 'static': 'keep', 'readonly': 'keep', 'override': 'keep', 'partial': 'keep', 'abstract': 'keep', 'sealed': 'keep', 'virtual': 'keep', 'async': 'keep' }
    });
  }
  function goProfile() {
    return streamEngine({
      name: 'go',
      keywords: KW_GO,
      types: TYPES_GO,
      builtins: BUILTINS_GO,
      atoms: setList('nil true false iota'),
      lineComments: ['//'],
      blocks: [BLK_CSTYLE],
      strings: [
        { open: '`', close: '`', esc: null, multi: true, cls: C.string2 },
        STR_DQ, STR_CHR
      ],
      declKeywords: { 'func': 'def', 'type': 'cls', 'struct': 'cls', 'interface': 'cls', 'map': 'cls', 'chan': 'cls', 'package': 'ns', 'import': 'ns', 'var': 'def', 'const': 'def' },
      capitalType: false,
      ops: /[=+\-*/%<>!&|^:]+/y
    });
  }
  function rustProfile() {
    return streamEngine({
      name: 'rust',
      keywords: KW_RUST,
      types: TYPES_RUST,
      builtins: BUILTINS_RUST,
      atoms: setList('true false None Some Ok Err'),
      lineComments: ['//'],
      blocks: [BLK_NEST],
      strings: [
        { open: 'r#"', close: '"#', esc: null, multi: true, cls: C.string2 },
        STR_DQ,
        STR_CHR
      ],
      hooks: [
        { re: /'[a-zA-Z_]\w*(?!')/y, cls: C.label },
        { re: /#!?\[[^\]\n]*\]?/y, cls: C.meta },
        { re: /[a-z_]\w*!/y, cls: C.macro }
      ],
      declKeywords: { 'fn': 'def', 'struct': 'cls', 'enum': 'cls', 'trait': 'cls', 'impl': 'cls', 'mod': 'ns', 'use': 'ns', 'let': 'def', 'const': 'def', 'static': 'def', 'type': 'cls', 'macro_rules': 'def', 'pub': 'def' },
      capitalType: true,
      upperConst: true,
      ops: /[=+\-*/%<>!&|^?:]+/y
    });
  }
  function swiftProfile() {
    return streamEngine({
      name: 'swift',
      keywords: KW_SWIFT,
      types: TYPES_SWIFT,
      builtins: setList('print println fatalError assert'),
      atoms: setList('true false nil self'),
      lineComments: ['//'],
      blocks: [{ open: '///', close: '*/', cls: C.doc }, BLK_NEST],
      strings: [STR_DQ, { open: '"""', close: '"""', esc: '\\', multi: true, cls: C.string }],
      decorator: /@[\w.]+/y,
      capitalType: true,
      upperConst: true,
      declKeywords: { 'func': 'def', 'class': 'cls', 'struct': 'cls', 'enum': 'cls', 'protocol': 'cls', 'extension': 'cls', 'let': 'def', 'var': 'def', 'typealias': 'cls', 'import': 'ns', 'init': 'def', 'subscript': 'def', 'public': 'keep', 'private': 'keep', 'fileprivate': 'keep', 'internal': 'keep', 'open': 'keep', 'static': 'keep', 'override': 'keep', 'mutating': 'keep', 'final': 'keep', 'lazy': 'keep', 'weak': 'keep' }
    });
  }
  function kotlinProfile() {
    return streamEngine({
      name: 'kotlin',
      keywords: KW_KT,
      types: TYPES_KT,
      builtins: setList('println print listOf mapOf setOf mutableListOf require check'),
      atoms: setList('true false null'),
      lineComments: ['//'],
      blocks: [BLK_NEST],
      strings: [
        { open: '"""', close: '"""', esc: null, multi: true, cls: C.string },
        { open: '"', esc: '\\', cls: C.string, interp: '${' },
        STR_SQ
      ],
      decorator: /@[\w.:]*/y,
      capitalType: true,
      upperConst: true,
      declKeywords: { 'fun': 'def', 'class': 'cls', 'interface': 'cls', 'object': 'cls', 'enum': 'cls', 'data': 'cls', 'sealed': 'cls', 'val': 'def', 'var': 'def', 'typealias': 'cls', 'package': 'ns', 'import': 'ns', 'public': 'keep', 'private': 'keep', 'protected': 'keep', 'internal': 'keep', 'override': 'keep', 'open': 'keep', 'abstract': 'keep', 'suspend': 'keep', 'lateinit': 'keep', 'const': 'keep', 'inline': 'keep', 'operator': 'keep', 'infix': 'keep', 'tailrec': 'keep' }
    });
  }
  function dartProfile() {
    return streamEngine({
      name: 'dart',
      keywords: KW_DART,
      types: TYPES_DART,
      builtins: setList('print printToConsole debugPrint'),
      atoms: setList('true false null'),
      lineComments: ['//'],
      blocks: [{ open: '///', close: '*/', cls: C.doc }, BLK_NEST],
      strings: [
        { open: "r'", close: "'", esc: null, cls: C.string2 },
        { open: 'r"', close: '"', esc: null, cls: C.string2 },
        { open: "'''", close: "'''", esc: '\\', multi: true, cls: C.string },
        { open: '"""', close: '"""', esc: '\\', multi: true, cls: C.string },
        { open: "'", esc: '\\', cls: C.string, interp: '${' },
        { open: '"', esc: '\\', cls: C.string, interp: '${' }
      ],
      decorator: /@\w+/y,
      capitalType: true,
      upperConst: true,
      declKeywords: { 'class': 'cls', 'mixin': 'cls', 'enum': 'cls', 'typedef': 'cls', 'extends': 'cls', 'implements': 'cls', 'with': 'cls', 'const': 'def', 'var': 'def', 'late': 'keep', 'static': 'keep', 'final': 'keep', 'override': 'keep', 'abstract': 'keep', 'external': 'keep' }
    });
  }
  function phpProfile(inline) {
    return streamEngine({
      name: 'php',
      keywords: KW_PHP,
      types: TYPES_PHP,
      builtins: BUILTINS_PHP,
      atoms: setList('true false null TRUE FALSE NULL'),
      caseInsensitive: true,
      lineComments: ['//', '#'],
      blocks: [BLK_CSTYLE],
      strings: [
        { open: '"', esc: '\\', cls: C.string, interp: '{$' },
        { open: "'", esc: '\\', cls: C.string },
        { open: '`', close: '`', esc: '\\', cls: C.string2 }
      ],
      stringSub: { re: /\$[A-Za-z_][\w]*/g, cls: C.variable },
      hooks: [
        { re: /#\[[^\]\n]*\]?/y, cls: C.meta },
        { re: /\$\w+/y, cls: C.variable },
        /* heredoc / nowdoc: <<<EOT … EOT — the body becomes a string
           frame until the terminator is seen at the start of a line */
        {
          re: /<<<\s*['"]?([A-Za-z_][\w]*)['"]?/y,
          cls: C.proc,
          set: function (st, m) {
            st.frames.push({
              kind: 'block',
              spec: { open: '<<<', close: m[1], nested: false, cls: C.string },
              depth: 1
            });
          }
        }
      ],
      declKeywords: { 'function': 'def', 'class': 'cls', 'interface': 'cls', 'trait': 'cls', 'enum': 'cls', 'new': 'cls', 'extends': 'cls', 'implements': 'cls', 'namespace': 'ns', 'use': 'ns', 'const': 'def', 'public': 'keep', 'private': 'keep', 'protected': 'keep', 'static': 'keep', 'final': 'keep', 'abstract': 'keep', 'readonly': 'keep' },
      capitalType: true,
      upperConst: true,
      memberOps: { '->': 1, '?->': 1, '::': 1 }
    });
  }
  function rubyProfile() {
    return streamEngine({
      name: 'ruby',
      keywords: KW_RB,
      builtins: BUILTINS_RB,
      atoms: setList('nil true false self'),
      lineComments: ['#'],
      blocks: [
        { open: '=begin', close: '=end', nested: false, cls: C.doc }
      ],
      strings: [
        { open: '<<~', close: '\n', esc: null, multi: false, cls: C.label },
        { open: '%w[', close: ']', esc: '\\', cls: C.string2 },
        { open: '%w(', close: ')', esc: '\\', cls: C.string2 },
        { open: '"', esc: '\\', cls: C.string, interp: '#{' },
        { open: "'", esc: '\\', cls: C.string },
        { open: '/', close: '/', esc: '\\', cls: C.regexp }
      ],
      hooks: [
        { re: /:[\w?!]*/y, cls: C.literal },
        { re: /@@?\w+/y, cls: C.variable }
      ],
      declKeywords: { 'def': 'def', 'class': 'cls', 'module': 'ns', 'require': 'ns', 'require_relative': 'ns', 'attr_accessor': 'def', 'attr_reader': 'def', 'attr_writer': 'def', 'lambda': 'def', 'proc': 'def', 'for': 'def', 'do': 'def' },
      capitalType: true
    });
  }
  function perlProfile() {
    return streamEngine({
      name: 'perl',
      keywords: KW_PL,
      builtins: setList('print printf say open close chomp die length substr index push pop shift'),
      atoms: setList('undef'),
      wordOperators: setList('eq ne lt gt le ge cmp and or not xor'),
      lineComments: ['#'],
      blocks: [BLK_CSTYLE],
      strings: [STR_DQ, STR_SQ, { open: '`', close: '`', esc: '\\', cls: C.string2 }],
      hooks: [
        { re: /[$@%]\w+/y, cls: C.variable },
        { re: /=~\s*[smy]?[\/{}].*/y, cls: C.ns }
      ],
      capitalType: true
    });
  }
  function shellProfile() {
    return streamEngine({
      name: 'shell',
      keywords: KW_SH,
      builtins: BUILTINS_SH,
      atoms: setList('true false'),
      lineComments: ['#'],
      blocks: [],
      strings: [
        { open: "'", close: "'", esc: null, cls: C.string },
        { open: '"', esc: '\\', cls: C.string },
        { open: '`', close: '`', esc: '\\', cls: C.string2 }
      ],
      stringSub: { re: /\$\{[^}]*\}|\$[\w*#?@!$-]+/g, cls: C.variable },
      hooks: [
        { re: /#!\/[^\n]*/y, cls: C.proc },
        { re: /\$\{[^}]*\}/y, cls: C.variable },
        { re: /\$\(/y, cls: C.meta },
        { re: /\$[\w*#?@!$-]+/y, cls: C.variable },
        { re: /-{1,2}[-\w]+/y, cls: C.attr }
      ],
      declKeywords: { 'function': 'def', 'for': 'def', 'while': 'def', 'case': 'def', 'until': 'def', 'select': 'def', 'local': 'def', 'export': 'def', 'declare': 'def', 'typeset': 'def' },
      capitalType: false,
      ops: /[=|&<>]+/y
    });
  }
  function luaProfile() {
    return streamEngine({
      name: 'lua',
      keywords: KW_LUA,
      builtins: BUILTINS_LUA,
      atoms: setList('true false nil'),
      lineComments: ['--'],
      blocks: [{ open: '--[[', close: ']]', nested: false, cls: C.comment }],
      strings: [
        { open: '[[', close: ']]', esc: null, multi: true, cls: C.string },
        STR_DQ, STR_SQ
      ],
      declKeywords: { 'function': 'def', 'local': 'def', 'require': 'ns', 'then': 'def', 'do': 'def', 'for': 'def', 'while': 'def' },
      capitalType: false
    });
  }
  function sqlProfile() {
    return streamEngine({
      name: 'sql',
      keywords: KW_SQL,
      types: TYPES_SQL,
      builtins: BUILTINS_SQL,
      atoms: setList('null true false unknown'),
      caseInsensitive: true,
      lineComments: ['--', '#'],
      blocks: [BLK_NEST],
      strings: [
        { open: "'", esc: null, cls: C.string },
        { open: '"', esc: null, cls: C.string2 },
        { open: '`', close: '`', esc: null, cls: C.string2 }
      ],
      hooks: [
        { re: /\$[A-Za-z_]*\$/y, cls: C.meta }
      ],
      declKeywords: { 'table': 'cls', 'view': 'cls', 'index': 'cls', 'as': 'def', 'into': 'def', 'on': 'def', 'using': 'def', 'returns': 'cls', 'function': 'def', 'cast': 'cls' },
      capitalType: false,
      ops: /[=<>!+\-*/%|&:^]+/y
    });
  }

  /* ═══════════════════════════════════════════════════════════════
     10 · REGISTRY
     ═══════════════════════════════════════════════════════════════ */
  var ALIASES = {
    js: 'javascript', jsx: 'javascript', mjs: 'javascript', cjs: 'javascript', node: 'javascript',
    ts: 'typescript', tsx: 'typescript', mts: 'typescript',
    py: 'python', python3: 'python', rb: 'ruby', pl: 'perl', pm: 'perl', kt: 'kotlin', kts: 'kotlin',
    rs: 'rust', golang: 'go', cs: 'csharp', 'c#': 'csharp', 'c++': 'cpp', cxx: 'cpp', hpp: 'cpp', cc: 'cpp',
    clike: 'cpp', 'text/x-c': 'c', h: 'c',
    yml: 'yaml', yamllint: 'yaml', toml: 'toml', ini: 'ini', cfg: 'properties', conf: 'properties',
    properties: 'properties', env: 'properties',
    sh: 'shell', bash: 'shell', zsh: 'shell', ksh: 'shell', console: 'shell',
    mysql: 'sql', pgsql: 'sql', postgres: 'sql', postgresql: 'sql', plsql: 'sql', sqlite: 'sql',
    scss: 'scss', sass: 'scss', less: 'less',
    htm: 'html', xhtml: 'html', htmlmixed: 'html', xml: 'xml', svg: 'xml', vue: 'vue', svelte: 'svelte',
    md: 'markdown', mdown: 'markdown', mkd: 'markdown',
    json5: 'json', jsonc: 'json', geojson: 'json',
    text: 'plaintext', txt: 'plaintext', plain: 'plaintext', log: 'plaintext', '': 'plaintext',
    'application/x-httpd-php': 'php', 'text/x-php': 'php', php5: 'php', php7: 'php', php8: 'php',
    'text/x-kotlin': 'kotlin', 'text/x-rustsrc': 'rust', 'text/x-go': 'go', 'text/x-swift': 'swift',
    'text/x-dart': 'dart', 'application/dart': 'dart', 'text/x-java': 'java', 'text/x-python': 'python',
    'text/x-sql': 'sql', 'text/x-yaml': 'yaml', 'text/x-sh': 'shell', 'text/x-ruby': 'ruby',
    'text/x-perl': 'perl', 'text/x-lua': 'lua', 'text/x-csharp': 'csharp', 'text/x-c++src': 'cpp',
    'application/json': 'json', 'text/markdown': 'markdown', 'text/html': 'html', 'text/css': 'css',
    'text/xml': 'xml', 'application/javascript': 'javascript', 'text/javascript': 'javascript',
    'text/typescript': 'typescript', 'text/x-scss': 'scss'
  };

  var LANGS = [
    { id: 'javascript', label: 'JavaScript / JSX', engine: 'stream' },
    { id: 'typescript', label: 'TypeScript', engine: 'stream' },
    { id: 'json', label: 'JSON', engine: 'stream' },
    { id: 'python', label: 'Python', engine: 'stream' },
    { id: 'c', label: 'C', engine: 'stream' },
    { id: 'cpp', label: 'C++', engine: 'stream' },
    { id: 'java', label: 'Java', engine: 'stream' },
    { id: 'csharp', label: 'C#', engine: 'stream' },
    { id: 'go', label: 'Go', engine: 'stream' },
    { id: 'rust', label: 'Rust', engine: 'stream' },
    { id: 'swift', label: 'Swift', engine: 'stream' },
    { id: 'kotlin', label: 'Kotlin', engine: 'stream' },
    { id: 'dart', label: 'Dart', engine: 'stream' },
    { id: 'php', label: 'PHP', engine: 'markup' },
    { id: 'ruby', label: 'Ruby', engine: 'stream' },
    { id: 'perl', label: 'Perl', engine: 'stream' },
    { id: 'lua', label: 'Lua', engine: 'stream' },
    { id: 'shell', label: 'Shell / Bash', engine: 'stream' },
    { id: 'sql', label: 'SQL', engine: 'stream' },
    { id: 'yaml', label: 'YAML', engine: 'yaml' },
    { id: 'toml', label: 'TOML', engine: 'props' },
    { id: 'ini', label: 'INI / .properties', engine: 'props' },
    { id: 'css', label: 'CSS', engine: 'css' },
    { id: 'scss', label: 'SCSS / Less', engine: 'css' },
    { id: 'html', label: 'HTML (embedded js/css)', engine: 'markup' },
    { id: 'xml', label: 'XML / SVG', engine: 'markup' },
    { id: 'vue', label: 'Vue (single file)', engine: 'markup' },
    { id: 'markdown', label: 'Markdown', engine: 'markdown' },
    { id: 'plaintext', label: 'Plain text', engine: 'plain' }
  ];

  function normalize(langId) {
    var id = String(langId == null ? '' : langId).trim().toLowerCase();
    if (ALIASES[id]) id = ALIASES[id];
    return id;
  }

  var PROFILE_CACHE = {};
  function buildProfile(id) {
    switch (id) {
      case 'javascript': return jsProfile();
      case 'typescript': return jsProfile();
      case 'json': return jsonProfile();
      case 'python': return pythonProfile();
      case 'c': return cProfile('c');
      case 'cpp': return cProfile('cpp');
      case 'java': return javaProfile();
      case 'csharp': return csharpProfile();
      case 'go': return goProfile();
      case 'rust': return rustProfile();
      case 'swift': return swiftProfile();
      case 'kotlin': return kotlinProfile();
      case 'dart': return dartProfile();
      case 'php': return markupEngine({ php: true });
      case 'php-inline': return phpProfile(true);
      case 'ruby': return rubyProfile();
      case 'perl': return perlProfile();
      case 'lua': return luaProfile();
      case 'shell': return shellProfile();
      case 'sql': return sqlProfile();
      case 'yaml': return yamlEngine();
      case 'toml': return propsEngine(true);
      case 'ini': return propsEngine(false);
      case 'properties': return propsEngine(false);
      case 'css': return cssEngine({ scss: false });
      case 'scss': return cssEngine({ scss: true });
      case 'less': return cssEngine({ scss: true });
      case 'html': return markupEngine({});
      case 'xml': return markupEngine({});
      case 'vue': return markupEngine({ vue: true });
      case 'svelte': return markupEngine({ vue: true });
      case 'markdown': return markdownEngine();
      default: return plainEngine();
    }
  }

  function profileFor(langId) {
    var id = normalize(langId);
    if (!PROFILE_CACHE[id]) PROFILE_CACHE[id] = buildProfile(id);
    return PROFILE_CACHE[id];
  }

  /* ═══════════════════════════════════════════════════════════════
     11 · DOCUMENT TOKENIZER + INCREMENTAL CACHE
     ═══════════════════════════════════════════════════════════════ */
  var CKPT_EVERY = 24;
  var MAX_LINES = 40000;
  var MAX_CHARS = 1200000;

  function splitLines(text) {
    var out = [], i = 0, n = text.length, start = 0;
    while (i < n) {
      if (text.charCodeAt(i) === 10) {
        out.push({ from: start, text: text.slice(start, i) });
        i++;
        start = i;
      } else i++;
    }
    out.push({ from: start, text: text.slice(start, n) });
    return out;
  }

  /* ── state signature ────────────────────────────────────────────
     The incremental cache only ever needs to answer "is the lexer in the
     same state it was here last time?". v24.2 answered it with a deep
     cloneState() per line plus a recursive deep compare — that was ~70% of
     all tokenize time on a big <style> block. A flat signature string is
     allocation-light and compares in one === . */
  var SPEC_SEQ = 0;
  function specId(spec) {
    if (!spec) return 0;
    if (!spec.__qkid) {
      try { spec.__qkid = ++SPEC_SEQ; } catch (e) { return 0; }
    }
    return spec.__qkid;
  }

  function stateSig(st) {
    if (!st) return '-';
    var out = (st.expect || '') + '~' + (st.dot ? 1 : 0) + '~' + (st.prev || '') + '~' + (st.partial ? 1 : 0) + '~';
    var i, f;
    for (i = 0; i < st.frames.length; i++) {
      f = st.frames[i];
      out += f.kind + ':' + specId(f.spec) + ':' + (f.depth || 0) + ',';
    }
    var ex = st.extra, k, v;
    if (ex) {
      out += '|';
      for (k in ex) {
        if (!Object.prototype.hasOwnProperty.call(ex, k)) continue;
        v = ex[k];
        if (v === null || v === undefined) continue;
        if (k === 'subStates') {
          for (var sk in v) {
            if (v[sk]) out += sk + '{' + stateSig(v[sk]) + '}';
          }
        } else if (typeof v === 'object') {
          for (var ok in v) {
            if (Object.prototype.hasOwnProperty.call(v, ok)) out += k + '.' + ok + '=' + v[ok] + ';';
          }
        } else {
          out += k + '=' + v + ';';
        }
      }
    }
    return out;
  }

  function newCache() {
    return {
      langId: '', lines: [], sigs: [], tokens: [],
      ckIdx: [], ckSt: [],
      truncated: false, chars: 0, reused: 0, old: null
    };
  }

  /* One forward pass, carrying lexer state across lines (that is what keeps
     block comments, heredocs and <style> blocks correct outside the
     viewport).

     Tokens are stored LINE-RELATIVE, exactly as the engines emit them, so:
       • no per-token object is allocated during tokenization, and
       • an edit that shifts every following character by ±n still reuses
         the untouched tail verbatim. v24.2 compared absolute offsets, so
         inserting or deleting one character invalidated the whole document
         and re-lexed every line on every keystroke — the delete lag. */
  function tokenizeInto(cache, text, langId) {
    var prof = profileFor(langId);
    var lines = splitLines(text);
    var st = prof.start();
    var limit = Math.min(lines.length, MAX_LINES);
    var prev = cache.old || null;
    var reused = 0;
    cache.langId = langId;
    cache.truncated = lines.length > limit;
    cache.chars = text.length;
    cache.lines = lines;
    cache.sigs = [];
    cache.tokens = [];
    cache.ckIdx = [];
    cache.ckSt = [];

    /* Longest identical trailing run, computed ONCE (O(n)). Comparing it
       per-line was O(n^2) and showed up as a stall on long files. Aligning
       from the end also survives whole lines being added or removed. */
    var pn = prev ? prev.lines.length : 0, cn = lines.length, suffix = 0;
    if (prev) {
      while (suffix < pn && suffix < cn &&
        prev.lines[pn - 1 - suffix].text === lines[cn - 1 - suffix].text &&
        prev.tokens[pn - 1 - suffix]) suffix++;
    }
    var reuseFrom = prev ? cn - suffix : -1;

    /* Longest identical LEADING run. Text equality there implies identical
       states, so we can restart from the newest checkpoint inside it
       instead of re-lexing the whole head. Without this an edit near the
       bottom of a 700-line file re-lexed all 700 lines per keystroke. */
    var startAt = 0;
    if (prev && prev.ckIdx && prev.ckIdx.length) {
      var prefix = 0;
      while (prefix < pn && prefix < cn &&
        prev.lines[prefix].text === lines[prefix].text && prev.tokens[prefix]) prefix++;
      var pick = -1;
      for (var c = 0; c < prev.ckIdx.length; c++) {
        if (prev.ckIdx[c] <= prefix) pick = c;
        else break;
      }
      if (pick >= 0 && prev.ckIdx[pick] > 0) {
        var upto = prev.ckIdx[pick];
        for (var q = 0; q < upto; q++) {
          cache.sigs.push(prev.sigs[q]);
          cache.tokens.push(prev.tokens[q]);
          reused++;
        }
        st = cloneState(prev.ckSt[pick]);
        startAt = upto;
      }
    }

    for (var i = startAt; i < limit; i++) {
      var line = lines[i];
      var sig = stateSig(st);

      /* Inside the identical tail and the lexer re-entered it in the
         same state ⇒ every remaining line is unchanged. Copy and stop. */
      if (prev && reuseFrom >= 0 && i >= reuseFrom) {
        var pi = pn - (cn - i);
        if (pi >= 0 && prev.sigs[pi] === sig) {
          for (var j = i; j < limit; j++) {
            var pj = pn - (cn - j);
            cache.sigs.push(prev.sigs[pj]);
            cache.tokens.push(prev.tokens[pj]);
            reused++;
          }
          cache.reused = reused;
          return cache;
        }
      }

      if ((i % CKPT_EVERY) === 0) {
        cache.ckIdx.push(i);
        cache.ckSt.push(cloneState(st));
      }
      cache.sigs.push(sig);
      if (cache.chars > MAX_CHARS && i > 200) {
        cache.truncated = true;
        break;
      }
      var r = prof.line(st, trimCR(line.text));
      st = r.state;
      cache.tokens.push(r.tokens);
    }
    cache.reused = reused;
    return cache;
  }

  function buildCache(text, langId, prev) {
    var cache = newCache();
    if (prev && prev.lines && prev.lines.length && prev.sigs && prev.sigs.length) {
      cache.old = {
        lines: prev.lines, sigs: prev.sigs, tokens: prev.tokens,
        ckIdx: prev.ckIdx, ckSt: prev.ckSt
      };
    }
    tokenizeInto(cache, text, langId);
    cache.old = null;
    return cache;
  }

  var CACHE = newCache();
  var CACHE_SRC = '';

  function cacheFor(text, langId, reuse) {
    var id = normalize(langId);
    if (reuse && CACHE_SRC === text && CACHE.langId === id && CACHE.tokens.length) return CACHE;
    CACHE = buildCache(text, id, reuse && CACHE.langId === id ? CACHE : null);
    CACHE_SRC = text;
    return CACHE;
  }

  function byClassOf(cache) {
    var counts = {}, i, j, t;
    for (i = 0; i < cache.tokens.length; i++) {
      var row = cache.tokens[i] || [];
      for (j = 0; j < row.length; j++) {
        t = row[j][2];
        counts[t] = (counts[t] || 0) + 1;
      }
    }
    return counts;
  }

  /* ---------- public tokenizer ---------- */
  function tokenizeDocument(text, langId, opts) {
    opts = opts || {};
    var src = String(text == null ? '' : text);
    var cache = opts.reuse ? cacheFor(src, langId, true) : buildCache(src, normalize(langId), null);
    var linesOut = [], count = 0, max = opts.maxTokens || Infinity;
    var i, j;
    var byClass = {};
    for (i = 0; i < cache.tokens.length; i++) {
      var line = cache.lines[i];
      var row = cache.tokens[i] || [];
      var outToks = [];
      for (j = 0; j < row.length; j++) {
        var cls = row[j][2];
        outToks.push({ from: line.from + row[j][0], to: line.from + row[j][1], cls: cls });
        byClass[cls] = (byClass[cls] || 0) + 1;
      }
      linesOut.push({ number: i + 1, from: line.from, text: line.text, tokens: outToks });
      count += outToks.length;
      if (count > max) break;
    }
    return {
      langId: normalize(langId),
      engine: profileFor(langId).name,
      lineCount: cache.lines.length,
      truncated: cache.truncated,
      tokenCount: count,
      byClass: byClass,
      lines: linesOut,
      version: VERSION
    };
  }

  function tokenizeLine(text, langId, state) {
    var prof = profileFor(langId);
    var st = state || prof.start();
    var r = prof.line(st, trimCR(String(text == null ? '' : text)));
    return { tokens: r.tokens, state: r.state };
  }

  function highlightHtml(text, langId) {
    var res = tokenizeDocument(text, langId);
    var out = [], i, j;
    for (i = 0; i < res.lines.length; i++) {
      var line = res.lines[i], pos = 0, html = '';
      for (j = 0; j < line.tokens.length; j++) {
        var t = line.tokens[j];
        var a = t.from - line.from, b = t.to - line.from;
        if (a > pos) html += escHtml(line.text.slice(pos, a));
        html += '<span class="' + t.cls + '">' + escHtml(line.text.slice(a, b)) + '</span>';
        pos = b;
      }
      html += escHtml(line.text.slice(pos));
      out.push(html);
    }
    return out.join('\n');
  }

  /* ═══════════════════════════════════════════════════════════════
     12 · COLOUR GUARANTEE
     If the tok-* classes do not resolve to a colour different from
     plain text, no stylesheet generation reached this page. Inject an
     explicit palette so colours never silently vanish.
     ═══════════════════════════════════════════════════════════════ */
  /* ── override marker ────────────────────────────────────────────
     When the fallback takes over a MIXED file it is competing with CM6's
     own (wrong) decorations — typically the whole <style> body painted as
     one string. Both marks land on the same characters, and whichever rule
     wins the cascade decides the colour, which is why a device could show
     a fully green CSS block while our token stream was perfectly correct.
     Every fallback span therefore also carries `qk-fb`, and the palette
     below gives `.qk-fb.tok-*` the specificity to win. */
  var OVERRIDE_CLASS = 'qk-fb';
  var OVERRIDE_PREFIX = OVERRIDE_CLASS + ' ';

  var OVERRIDE_ROLES = [
    ['tok-comment', '--syn-comment', '#5f7394'],
    ['tok-string', '--syn-string', '#c3e88d'],
    ['tok-string2', '--syn-string-2', '#f07178'],
    ['tok-regexp', '--syn-string-2', '#f07178'],
    ['tok-escape', '--syn-meta', '#89ddff'],
    ['tok-keyword', '--syn-keyword', '#c084fc'],
    ['tok-modifier', '--syn-keyword', '#c084fc'],
    ['tok-atom', '--syn-atom', '#ffa657'],
    ['tok-bool', '--syn-atom', '#ffa657'],
    ['tok-literal', '--syn-atom', '#ffa657'],
    ['tok-number', '--syn-number', '#f78c6c'],
    ['tok-variableName', '--syn-variable', '#dbe4f3'],
    ['tok-definition', '--syn-def', '#82aaff'],
    ['tok-function', '--syn-def', '#82aaff'],
    ['tok-macroName', '--syn-def', '#82aaff'],
    ['tok-typeName', '--syn-type', '#7fdbca'],
    ['tok-className', '--syn-type', '#7fdbca'],
    ['tok-namespace', '--syn-property', '#82aaff'],
    ['tok-propertyName', '--syn-property', '#82aaff'],
    ['tok-operator', '--syn-operator', '#89ddff'],
    ['tok-punctuation', '--syn-bracket', '#a8b7cf'],
    ['tok-meta', '--syn-meta', '#89ddff'],
    ['tok-processing', '--syn-meta', '#89ddff'],
    ['tok-tag', '--syn-tag', '#f07178'],
    ['tok-attributeName', '--syn-attribute', '#c792ea'],
    ['tok-attributeValue', '--syn-string', '#c3e88d'],
    ['tok-heading', '--syn-header', '#f5a524'],
    ['tok-quote', '--syn-quote', '#7c8fa8'],
    ['tok-link', '--syn-link', '#2dd4bf'],
    ['tok-url', '--syn-link', '#2dd4bf'],
    ['tok-labelName', '--syn-label', '#c792ea'],
    ['tok-invalid', '--syn-error', '#f87171']
  ];

  function overrideCss() {
    var out = [], i;
    for (i = 0; i < OVERRIDE_ROLES.length; i++) {
      var r = OVERRIDE_ROLES[i];
      out.push('.cm-editor .' + OVERRIDE_CLASS + '.' + r[0] + ',' +
        '.qk-syntax .' + OVERRIDE_CLASS + '.' + r[0] +
        '{color:var(' + r[1] + ',' + r[2] + ')!important;}');
    }
    out.push('.cm-editor .' + OVERRIDE_CLASS + '.tok-comment{font-style:italic!important;}');
    out.push('.cm-editor .' + OVERRIDE_CLASS + '.tok-typeName,.cm-editor .' + OVERRIDE_CLASS +
      '.tok-className{font-weight:600;}');
    out.push('.cm-editor .' + OVERRIDE_CLASS + '.tok-heading{font-weight:700!important;}');
    return out.join('\n');
  }

  var STYLE_ID = 'qk-hl-palette-css';
  var PALETTE_CSS = [
    '#m-editor-mount .tok-comment, .qk-syntax .tok-comment{color:var(--syn-comment,#5f7394);font-style:italic;}',
    '#m-editor-mount .tok-docComment, .qk-syntax .tok-docComment{color:var(--syn-comment,#5f7394);font-style:italic;}',
    '#m-editor-mount .tok-string, .qk-syntax .tok-string{color:var(--syn-string,#c3e88d);}',
    '#m-editor-mount .tok-string2, .qk-syntax .tok-string2{color:var(--syn-string-2,#f07178);}',
    '#m-editor-mount .tok-regexp, .qk-syntax .tok-regexp{color:var(--syn-string-2,#f07178);}',
    '#m-editor-mount .tok-escape, .qk-syntax .tok-escape{color:var(--syn-meta,#89ddff);}',
    '#m-editor-mount .tok-keyword, .qk-syntax .tok-keyword{color:var(--syn-keyword,#c084fc);}',
    '#m-editor-mount .tok-modifier, .qk-syntax .tok-modifier{color:var(--syn-keyword,#c084fc);}',
    '#m-editor-mount .tok-atom, .qk-syntax .tok-atom{color:var(--syn-atom,#ffa657);}',
    '#m-editor-mount .tok-bool, .qk-syntax .tok-bool{color:var(--syn-atom,#ffa657);}',
    '#m-editor-mount .tok-literal, .qk-syntax .tok-literal{color:var(--syn-atom,#ffa657);}',
    '#m-editor-mount .tok-number, .qk-syntax .tok-number{color:var(--syn-number,#f78c6c);}',
    '#m-editor-mount .tok-integer, .qk-syntax .tok-integer{color:var(--syn-number,#f78c6c);}',
    '#m-editor-mount .tok-variableName, .qk-syntax .tok-variableName{color:var(--syn-variable,#dbe4f3);}',
    '#m-editor-mount .tok-definition, .qk-syntax .tok-definition{color:var(--syn-def,#82aaff);}',
    '#m-editor-mount .tok-function, .qk-syntax .tok-function{color:var(--syn-def,#82aaff);}',
    '#m-editor-mount .tok-macroName, .qk-syntax .tok-macroName{color:var(--syn-def,#82aaff);}',
    '#m-editor-mount .tok-typeName, .qk-syntax .tok-typeName{color:var(--syn-type,#7fdbca);font-weight:600;}',
    '#m-editor-mount .tok-className, .qk-syntax .tok-className{color:var(--syn-type,#7fdbca);font-weight:600;}',
    '#m-editor-mount .tok-namespace, .qk-syntax .tok-namespace{color:var(--syn-property,#82aaff);}',
    '#m-editor-mount .tok-propertyName, .qk-syntax .tok-propertyName{color:var(--syn-property,#82aaff);}',
    '#m-editor-mount .tok-operator, .qk-syntax .tok-operator{color:var(--syn-operator,#89ddff);}',
    '#m-editor-mount .tok-punctuation, .qk-syntax .tok-punctuation{color:var(--syn-bracket,#a8b7cf);}',
    '#m-editor-mount .tok-paren, .qk-syntax .tok-paren{color:var(--syn-bracket,#a8b7cf);}',
    '#m-editor-mount .tok-bracket, .qk-syntax .tok-bracket{color:var(--syn-bracket,#a8b7cf);}',
    '#m-editor-mount .tok-meta, .qk-syntax .tok-meta{color:var(--syn-meta,#89ddff);}',
    '#m-editor-mount .tok-processing, .qk-syntax .tok-processing{color:var(--syn-meta,#89ddff);}',
    '#m-editor-mount .tok-tag, .qk-syntax .tok-tag{color:var(--syn-tag,#f07178);}',
    '#m-editor-mount .tok-attributeName, .qk-syntax .tok-attributeName{color:var(--syn-attribute,#c792ea);}',
    '#m-editor-mount .tok-attributeValue, .qk-syntax .tok-attributeValue{color:var(--syn-string,#c3e88d);}',
    '#m-editor-mount .tok-heading, .qk-syntax .tok-heading{color:var(--syn-header,#f5a524);font-weight:700;}',
    '#m-editor-mount .tok-quote, .qk-syntax .tok-quote{color:var(--syn-quote,#7c8fa8);font-style:italic;}',
    '#m-editor-mount .tok-link, .qk-syntax .tok-link{color:var(--syn-link,#2dd4bf);text-decoration:underline;}',
    '#m-editor-mount .tok-url, .qk-syntax .tok-url{color:var(--syn-link,#2dd4bf);}',
    '#m-editor-mount .tok-emphasis, .qk-syntax .tok-emphasis{font-style:italic;}',
    '#m-editor-mount .tok-strong, .qk-syntax .tok-strong{font-weight:700;}',
    '#m-editor-mount .tok-strikethrough, .qk-syntax .tok-strikethrough{text-decoration:line-through;}',
    '#m-editor-mount .tok-labelName, .qk-syntax .tok-labelName{color:var(--syn-label,#c792ea);}',
    '#m-editor-mount .tok-invalid, .qk-syntax .tok-invalid{color:var(--syn-error,#f87171);}'
  ].join('\n') + '\n' + overrideCss();

  function injectPalette() {
    if (!DOC || DOC.getElementById(STYLE_ID)) return false;
    var el = DOC.createElement('style');
    el.id = STYLE_ID;
    el.textContent = PALETTE_CSS;
    (DOC.head || DOC.documentElement).appendChild(el);
    return true;
  }

  function probeTokenColour(scope) {
    if (!DOC || !W.getComputedStyle) return true;
    var host = scope || DOC.body;
    if (!host) return true;
    var wrap = DOC.createElement('div');
    wrap.setAttribute('data-qk-probe', '1');
    wrap.style.cssText = 'position:absolute;left:-9999px;top:0;visibility:hidden;';
    var plain = DOC.createElement('span');
    plain.textContent = 'x';
    var tok = DOC.createElement('span');
    tok.className = 'tok-keyword';
    tok.textContent = 'x';
    wrap.appendChild(plain);
    wrap.appendChild(tok);
    host.appendChild(wrap);
    var ok = true;
    try {
      var a = W.getComputedStyle(plain).color;
      var b = W.getComputedStyle(tok).color;
      ok = !!a && !!b && a !== b;
    } catch (e) {
      ok = true;
    }
    if (wrap.parentNode) wrap.parentNode.removeChild(wrap);
    return ok;
  }

  function ensureColours(scope) {
    if (probeTokenColour(scope)) return { injected: false, alive: true };
    injectPalette();
    return { injected: true, alive: probeTokenColour(scope) };
  }

  /* ═══════════════════════════════════════════════════════════════
     13 · RENDERERS — CM6 decorations + DOM painter
     ═══════════════════════════════════════════════════════════════ */
  var PAD_LINES = 300;
  var PROBE_SELECTOR = '.tok-keyword,.tok-string,.tok-comment,.tok-tag,.tok-number,' +
    '.tok-variableName,.tok-propertyName,.tok-typeName,.tok-definition,.tok-function,' +
    '.tok-meta,.tok-operator,.tok-heading,.tok-string2';
  var PROBE_MIN_DOC = 40;

  var COVERAGE_TTL = 2500;
  var COVERAGE_MAX_LINES = 40;
  var _covAt = 0, _covVal = null, _covKey = '';

  function mixedCoverage(cm, force) {
    var id = CACHE.langId;
    if (id !== 'php' && id !== 'html' && id !== 'vue' && id !== 'svelte') return null;
    var now = Date.now();
    var key = id + ':' + CACHE_SRC.length;
    if (!force && _covVal && key === _covKey && (now - _covAt) < COVERAGE_TTL) return _covVal;
    try {
      var lines = cm.view.dom.querySelectorAll('.cm-line');
      var expected = {}, seen = {}, inspected = 0;
      var cap = Math.min(lines.length, COVERAGE_MAX_LINES);
      for (var i = 0; i < cap; i++) {
        var el = lines[i], ln = cm.view.state.doc.lineAt(cm.view.posAtDOM(el, 0)).number - 1;
        var row = CACHE.tokens[ln] || [];
        if (CACHE.lines[ln] && CACHE.lines[ln].text !== (el.textContent || '')) continue;
        var spans = el.querySelectorAll('[class*="tok-"]');
        for (var s = 0; s < spans.length; s++) {
          var classes = String(spans[s].className || '').split(/\s+/);
          for (var c = 0; c < classes.length; c++) if (classes[c].indexOf('tok-') === 0) seen[classes[c]] = 1;
        }
        for (var t = 0; t < row.length; t++) {
          var parts = String(row[t][2] || '').split(/\s+/);
          var useful = [];
          for (var p = 0; p < parts.length; p++) {
            if (parts[p] === 'tok-variableName' || parts[p] === 'tok-punctuation' ||
              parts[p] === 'tok-paren' || parts[p] === 'tok-bracket') continue;
            if (parts[p].indexOf('tok-') === 0) useful.push(parts[p]);
          }
          if (useful.length) {
            expected[useful.join('|')] = useful;
            inspected++;
          }
        }
      }
      var keys = Object.keys(expected);
      if (keys.length < 3 || inspected < 4) return null;
      var matched = 0, missing = [];
      for (var k = 0; k < keys.length; k++) {
        var aliases = expected[keys[k]], ok = false;
        for (var a = 0; a < aliases.length; a++) if (seen[aliases[a]]) ok = true;
        if (ok) matched++;
        else missing.push(aliases[0]);
      }
      _covVal = { alive: matched / keys.length >= 0.55, matched: matched, expected: keys.length, missing: missing };
      _covKey = key;
      _covAt = now;
      return _covVal;
    } catch (e) {
      return null;
    }
  }

  function decoFor(mod, view, cache) {
    var Decoration = mod.view.Decoration;
    var doc = view.state.doc;
    var vp = view.viewport || { from: 0, to: doc.length };
    var first = doc.lineAt(Math.max(0, Math.min(doc.length, vp.from))).number - 1;
    var last = doc.lineAt(Math.max(0, Math.min(doc.length, vp.to))).number - 1;
    var from = Math.max(0, first - PAD_LINES);
    var to = Math.min(cache.tokens.length - 1, last + PAD_LINES);
    var ranges = [], i, j;
    for (i = from; i <= to; i++) {
      var toks = cache.tokens[i];
      if (!toks || !toks.length) continue;
      var lineFrom = cache.lines[i].from;
      var lineLen = cache.lines[i].text.length;
      for (j = 0; j < toks.length; j++) {
        var t = toks[j];
        /* clamp to the line: a cache from a previous document revision
           must never emit an out-of-range decoration */
        var absFrom = lineFrom + t[0];
        var absTo = Math.min(lineFrom + lineLen, lineFrom + t[1]);
        if (absTo <= absFrom) continue;
        try {
          ranges.push(Decoration.mark({ class: OVERRIDE_PREFIX + t[2] }).range(absFrom, absTo));
        } catch (e) { /* stale offset — skip, the next rebuild resyncs */ }
      }
    }
    try {
      return Decoration.set(ranges, true);
    } catch (e) {
      return Decoration.none;
    }
  }

  function makePlugin(mod) {
    var ViewPlugin = mod.view.ViewPlugin;
    function Fb(view) {
      this.lang = CACHE.langId;
      this.deco = decoFor(mod, view, CACHE);
    }
    Fb.prototype.update = function (u) {
      /* rebuild on edits, on scroll (viewport window) and whenever the
         language changed underneath us (tab/file switch) */
      if (u.docChanged || u.viewportChanged || u.geometryChanged || this.lang !== CACHE.langId) {
        this.lang = CACHE.langId;
        this.deco = decoFor(mod, u.view, CACHE);
      }
    };
    return ViewPlugin.fromClass(Fb, {
      decorations: function (v) {
        return v.deco;
      }
    });
  }

  /* ---- DOM painter: same cache, same class names ---- */
  var painter = { on: false, scrollFn: null, raf: 0, more: false };
  var PAINT_LINE_CAP = 400;
  var PAINT_BUDGET_MS = 8;

  function lineHtml(cache, ln) {
    var line = cache.lines[ln];
    if (!line) return null;
    var toks = cache.tokens[ln] || [];
    var text = line.text;
    var out = '', pos = 0, j, any = false;
    for (j = 0; j < toks.length; j++) {
      var a = toks[j][0], b = toks[j][1];
      if (b > text.length) b = text.length;
      if (b <= a || a < pos) continue;
      if (a > pos) out += escHtml(text.slice(pos, a));
      out += '<span class="' + OVERRIDE_PREFIX + toks[j][2] + '">' + escHtml(text.slice(a, b)) + '</span>';
      pos = b;
      any = true;
    }
    out += escHtml(text.slice(pos));
    return any ? out : null;
  }

  function paintVisible(cm, cache) {
    var view = cm.view;
    if (!view || !view.dom) return 0;
    var els = view.dom.querySelectorAll('.cm-line');
    var painted = 0, scanned = 0;
    var t0 = Date.now();
    var doc = view.state.doc;
    for (var i = 0; i < els.length && painted < PAINT_LINE_CAP; i++) {
      /* innerHTML is the expensive DOM op on a phone. Give the painter a
         frame budget and let the next rAF continue where it stopped,
         instead of blocking the UI thread on a long viewport. */
      if ((++scanned & 7) === 0 && (Date.now() - t0) > PAINT_BUDGET_MS) {
        painter.more = true;
        return painted;
      }
      var el = els[i];
      if (el.querySelector('.cm-cursor,.cm-selectionBackground,.m-diag-ul,.cm-searchMatch,.cm-searchMatch-selected')) continue;
      var text = el.textContent || '';
      var ln = -1;
      try {
        ln = doc.lineAt(view.posAtDOM(el, 0)).number - 1;
      } catch (e) {
        ln = -1;
      }
      if (ln < 0 || ln >= cache.lines.length) continue;
      if (cache.lines[ln].text !== text) {
        /* element/line mismatch (mid-update) — try the next one */
        continue;
      }
      /* Signature covers the token stream, not just the text, so a
         re-lex that changes colours still repaints. */
      var row = cache.tokens[ln] || [];
      var sig = cache.langId + ':' + text.length + ':' + row.length +
        (row.length ? ':' + row[0][2] + ':' + row[row.length - 1][1] : '');
      if (el.getAttribute('data-qkfb') === sig) continue;
      el.setAttribute('data-qkfb', sig);
      /* Painter mode means CM6 decorations are unavailable or only
         partially cover this mixed-language line. Replace the display
         line from the complete cache even when one native token exists. */
      var html = lineHtml(cache, ln);
      if (html !== null) {
        el.innerHTML = html;
        painted++;
      }
    }
    painter.more = false;
    return painted;
  }

  function schedulePaint(cm) {
    if (!painter.on || painter.raf) return;
    painter.raf = (W.requestAnimationFrame || setTimeout)(function () {
      painter.raf = 0;
      paintVisible(cm, CACHE);
      /* budget exhausted → finish the rest on the next frame */
      if (painter.more) schedulePaint(cm);
    }, 16);
  }

  function startPainter(cm) {
    if (painter.on) return;
    painter.on = true;
    paintVisible(cm, CACHE);
    try {
      painter.scrollFn = function () {
        schedulePaint(cm);
      };
      cm.view.scrollDOM.addEventListener('scroll', painter.scrollFn, { passive: true });
      cm.view.dom.addEventListener('scroll', painter.scrollFn, { passive: true });
    } catch (e) { }
    if (W.console) {
      W.console.warn('[editor-fallback-hl] CM6 will not render mark decorations on this build — DOM painter active (identical token classes, whole-document token stream)');
    }
  }

  function stopPainter(cm) {
    if (!painter.on) return;
    painter.on = false;
    try {
      if (painter.scrollFn && cm && cm.view) {
        cm.view.scrollDOM.removeEventListener('scroll', painter.scrollFn);
        cm.view.dom.removeEventListener('scroll', painter.scrollFn);
      }
    } catch (e) { }
    painter.scrollFn = null;
  }

  /* ═══════════════════════════════════════════════════════════════
     14 · DETECTION · MOUNTING · HEALTH
     ═══════════════════════════════════════════════════════════════ */
  function activeCM() {
    try {
      var ME = IDE.mobileEditor;
      if (ME && typeof ME.getCM === 'function') return ME.getCM();
    } catch (e) { }
    return null;
  }

  function currentLang(cm) {
    try {
      if (cm && typeof cm.getLangId === 'function' && cm.getLangId()) return normalize(cm.getLangId());
      if (cm && cm._pendingLangKey) return normalize(cm._pendingLangKey);
      if (cm && cm._opts && cm._opts.mode) return normalize(cm._opts.mode && cm._opts.mode.name);
    } catch (e) { }
    return 'plaintext';
  }

  function syncCache(cm) {
    var langId = currentLang(cm);
    var text = '';
    try {
      text = cm.view.state.doc.toString();
    } catch (e) {
      return CACHE;
    }
    if (CACHE_SRC === text && CACHE.langId === langId) return CACHE;
    cacheFor(text, langId, true);
    return CACHE;
  }

  function coloursAlive(cm) {
    try {
      var dom = cm.view && cm.view.dom;
      if (!dom) return false;
      if (cm.view.state.doc.length < PROBE_MIN_DOC) return null;
      if (!dom.querySelector(PROBE_SELECTOR)) return false;
      var coverage = mixedCoverage(cm);
      return coverage ? coverage.alive : true;
    } catch (e) {
      return false;
    }
  }

  function appendPlugin(cm) {
    var ext = makePlugin(cm.mod);
    cm.view.dispatch({ effects: cm.mod.state.StateEffect.appendConfig.of(ext) });
  }

  function mount(cm, reason) {
    if (!cm || cm._qkFbHlMounted) return false;
    try {
      var view = cm.view, mod = cm.mod;
      if (!view || !mod || !mod.view || !mod.view.ViewPlugin) return false;
      if (!mod.state || !mod.state.StateEffect || !mod.state.StateEffect.appendConfig) return false;
      cm._qkFbHlMounted = true;
      syncCache(cm);
      /* Always inject: the `.qk-fb` override rules are what let our
         marks beat CM6's own decorations on a mixed file, even when the
         plain-colour probe says the theme is healthy. */
      injectPalette();
      ensureColours(cm.mountEl || (DOC && DOC.getElementById('m-editor-mount')));
      appendPlugin(cm);
      if (view.requestMeasure) view.requestMeasure();
      if (W.console) {
        W.console.warn('[editor-fallback-hl] v' + VERSION + ' mounted (reason: ' + (reason || 'probe') +
          ', ' + CACHE.tokens.length + ' lines tokenized, engine=' + profileFor(CACHE.langId).name + ')');
      }
      return true;
    } catch (e) {
      return false;
    }
  }

  var PROBE_DELAYS = [900, 2000, 3600];

  function scheduleProbes(cm) {
    if (!cm || cm._qkFbHlScheduled) return;
    cm._qkFbHlScheduled = true;
    PROBE_DELAYS.forEach(function (ms, idx) {
      setTimeout(function () {
        if (cm._qkFbHlMounted) return;
        syncCache(cm);
        var alive = coloursAlive(cm);
        if (alive === true) return;
        if (alive === false || idx === PROBE_DELAYS.length - 1) mount(cm, 'probe#' + (idx + 1));
      }, ms);
    });
  }

  var HEALTH_MS = 1000;
  var reAppendTries = 0;

  function healthTick() {
    var cm = activeCM();
    if (!cm || cm.engine !== 'cm6' || !cm.view) return;
    try {
      if (cm.view.state.doc.length < PROBE_MIN_DOC) return;
    } catch (e) {
      return;
    }
    var cache = syncCache(cm);
    if (coloursAlive(cm)) {
      stopPainter(cm);
      reAppendTries = 0;
      return;
    }
    if (!cm._qkFbHlMounted) {
      mount(cm, 'partial mixed-language coverage');
      return;
    }
    /* tab switches replace the editor state (per-tab undo history) and wipe
       runtime extensions — re-install a couple of times before escalating */
    if (reAppendTries < 2 && cache.tokens.length) {
      reAppendTries++;
      try { appendPlugin(cm); } catch (e) { }
      return;
    }
    if (!painter.on) startPainter(cm);
    else schedulePaint(cm);
  }

  function onTabShown() {
    var cm = activeCM();
    if (!cm || cm.engine !== 'cm6') return;
    syncCache(cm);
    if (cm._qkFbHlMounted) {
      try { cm.view.dispatch({}); } catch (e) { }
      if (painter.on) schedulePaint(cm);
      return;
    }
    scheduleProbes(cm);
  }

  function boot() {
    var U = IDE.utils;
    if (U && U.on) U.on('editor:tab-shown', onTabShown);
    var tries = 0;
    var poll = setInterval(function () {
      tries++;
      var cm = activeCM();
      if (cm && cm.engine === 'cm6' && !cm._qkFbHlMounted) {
        scheduleProbes(cm);
        clearInterval(poll);
      } else if (tries > 40 || (cm && cm._qkFbHlMounted)) {
        clearInterval(poll);
      }
    }, 500);
    setInterval(healthTick, HEALTH_MS);
  }

  function diagnose(cm) {
    cm = cm || activeCM();
    var out = {
      version: VERSION,
      engine: cm ? cm.engine : null,
      mounted: !!(cm && cm._qkFbHlMounted),
      painter: painter.on,
      langId: cm ? currentLang(cm) : null,
      coloursAlive: null,
      tokens: 0,
      chars: 0,
      profile: null,
      mixedCoverage: null,
      perf: null,
      paletteInjected: !!(DOC && DOC.getElementById(STYLE_ID)),
      viewHasCss: null,
      note: ''
    };
    if (!cm || cm.engine !== 'cm6') {
      out.note = 'fallback highlighter only applies to the CM6 engine';
      return out;
    }
    try {
      out.coloursAlive = coloursAlive(cm);
    } catch (e) { }
    var cache = syncCache(cm);
    out.tokens = cache.tokens.length;
    out.chars = CACHE_SRC.length;
    out.profile = profileFor(cache.langId).name;
    out.mixedCoverage = mixedCoverage(cm);
    out.perf = {
      lines: cache.lines.length,
      reusedLastPass: cache.reused,
      checkpoints: cache.ckIdx.length,
      truncated: cache.truncated,
      painterBudgetMs: PAINT_BUDGET_MS
    };
    try {
      var scroller = cm.view.scrollDOM;
      out.viewHasCss = !!scroller && W.getComputedStyle(scroller).overflowY !== '';
    } catch (e) { }
    if (out.coloursAlive === false && !out.mounted) {
      out.note = 'CM6 painted no coloured tokens — call repair() or wait for the probe (900/2000/3600 ms)';
    } else if (out.mounted) {
      out.note = painter.on
        ? 'CM6 cannot render decorations on this build — DOM painter is colouring from the token cache'
        : 'fallback highlighter decorating from the whole-document token cache';
      if (out.mixedCoverage && !out.mixedCoverage.alive) {
        out.note += '; mixed-language roles missing: ' + out.mixedCoverage.missing.join(', ');
      }
    } else {
      out.note = 'CM6 colours look healthy; fallback stays idle';
    }
    return out;
  }

  function repair(cm) {
    cm = cm || activeCM();
    if (!cm || cm.engine !== 'cm6' || !cm.view) return false;
    syncCache(cm);
    ensureColours(cm.mountEl || (DOC && DOC.getElementById('m-editor-mount')));
    var ok = mount(cm, 'repair()');
    if (!ok && cm._qkFbHlMounted) {
      try { appendPlugin(cm); } catch (e) { }
    }
    healthTick();
    return true;
  }

  /* ═══════════════════════════════════════════════════════════════
     15 · EXPOSE
     ═══════════════════════════════════════════════════════════════ */
  IDE.syntaxLex = {
    version: VERSION,
    classes: C,
    roles: ROLE_NOTES,
    languages: LANGS,
    normalize: normalize,
    profileName: function (langId) { return profileFor(langId).name; },
    tokenizeDocument: tokenizeDocument,
    tokenizeLine: tokenizeLine,
    highlightHtml: highlightHtml,
    splitLines: splitLines,
    ensurePalette: injectPalette,
    probeColours: probeTokenColour
  };

  IDE.editorFallbackHl = {
    version: VERSION,
    isMounted: function () {
      var cm = activeCM();
      return !!(cm && cm._qkFbHlMounted);
    },
    isPainterActive: function () { return painter.on; },
    probeNow: function () {
      var cm = activeCM();
      if (!cm || cm.engine !== 'cm6' || cm._qkFbHlMounted) return false;
      syncCache(cm);
      if (coloursAlive(cm) === true) return false;
      return mount(cm, 'probeNow()');
    },
    mount: function (cm) { return mount(cm || activeCM(), 'manual'); },
    repair: repair,
    diagnose: diagnose,
    ensureStyles: function (scope) { return ensureColours(scope || (DOC && DOC.getElementById('m-editor-mount'))); },
    tokenize: tokenizeDocument,
    highlightHtml: highlightHtml,
    cache: function () { return CACHE; }
  };

  /* auto-boot only inside the real app shell */
  var AUTOSTART = !(W.IDE_CONFIG && W.IDE_CONFIG.disableFallbackAutostart);
  if (AUTOSTART && DOC) {
    if (DOC.readyState === 'loading') DOC.addEventListener('DOMContentLoaded', boot);
    else boot();
  }
})();
