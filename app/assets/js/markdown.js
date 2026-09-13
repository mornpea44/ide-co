/**
* ═══════════════════════════════════════════════════════════════════════════
*  QUIRKY IDE — MARKDOWN RENDERER (shared module)
* ═══════════════════════════════════════════════════════════════════════════
*
*  The Markdown → HTML renderer, extracted from preview.js so it can be
*  used by BOTH preview surfaces:
*    • preview.js            — the in-IDE live pane
*    • views/live-preview.php — the standalone "open in new tab" page
*
*  Pure string functions, no DOM dependencies (except copyCode(), which is
*  only ever invoked from a click handler inside a real document, and the
*  Mermaid/KaTeX bootstrap script emitted by wrap(), which only runs in a
*  real browser).
*
*  FEATURES
*    • YAML front matter → rendered as a metadata card
*    • headings with slugged ids + hover anchor links
*    • fenced code blocks: language badge, copy button, syntax highlighting
*      (js/ts, py, java, c/c++/c#, php, ruby, go, rust, bash, sql, css,
*      json, yaml, html/xml) — ```mermaid``` fences render as real diagrams
*    • $$ ... $$ / $ ... $ math survives untouched for client-side KaTeX
*      auto-render (wired up in wrap())
*    • GitHub-style callouts: > [!NOTE] / [!TIP] / [!IMPORTANT] /
*      [!WARNING] / [!CAUTION]
*    • tables (alignment, escaped `\|`) · nested blockquotes
*    • ordered/unordered/task lists (nested, correct start numbers)
*    • definition lists (Term\n: definition)
*    • reference-style & shortcut links/images ([text][id], [id], ![a][id])
*      plus footnotes ([^1] refs + [^1]: definitions)
*    • abbreviations (*[SLO]: Service Level Objective)
*    • wiki-links ([[Page]], [[Page|Label]])
*    • raw inline/block HTML passthrough (opted in — <details>, <kbd>,
*      <sup>, <u>, comments, etc. render as real HTML) with a light safety
*      net: <script>/<style>/<iframe>/<object>/<embed>/<link>/<meta>/<form>
*      are dropped and on* handlers / javascript: URLs are stripped
*    • bold / italic / bold-italic / strikethrough / `code` / ==highlight==
*      / superscript / escaped punctuation / autolinked bare URLs
*    • hard line breaks (trailing "  " or "\") vs. soft breaks
*
*  EXPOSES: window.IDE.markdown = { toHtml, wrap, copyCode }
* ═══════════════════════════════════════════════════════════════════════════ */
(function () {
  'use strict';
  /* ─────────────────────────────── helpers ─────────────────────────────── */
  function mdEscapeHtml(s) {
    return String(s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }
  function mdEscapeRe(s) { return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); }
  function mdSafeUrl(u) {
    u = (u || '').trim();
    if (/^(javascript|vbscript|data):/i.test(u) && !/^data:image\//i.test(u)) return '#';
    return u;
  }
  function mdLinkTarget(url) {
    return (url.charAt(0) === '#') ? '' : ' target="_blank" rel="noopener"';
  }
  /* Per-render (toHtml call) state — reset in mdToHtml() below. */
  let SLUGS = null;    // heading-id de-duplication registry
  let FN = null;       // footnotes: { defs: {label: text}, order: [label,...], counts: {label: n} }
  let REFS = null;     // reference-style link/image definitions: { label: {url, title} }
  let ABBRS = null;    // abbreviations: { term: definition }
  let HEADINGS = null; // collected { level, id, text } entries, used to render [[TOC]]
  function mdSlugify(rawText) {
    let base = String(rawText)
      .toLowerCase()
      .replace(/<[^>]+>/g, '')
      .replace(/`([^`]*)`/g, '$1')
      .replace(/[*_~]/g, '')
      .replace(/[^\w\s-]/g, '')
      .trim()
      .replace(/\s+/g, '-');
    if (!base) base = 'section';
    if (!SLUGS) SLUGS = {};
    if (!Object.prototype.hasOwnProperty.call(SLUGS, base)) {
      SLUGS[base] = 0;
      return base;
    }
    SLUGS[base] += 1;
    return base + '-' + SLUGS[base];
  }
  function mdStripMd(s) {
    return String(s)
      .replace(/`([^`]+)`/g, '$1')
      .replace(/\*\*\*([^*]+)\*\*\*/g, '$1')
      .replace(/\*\*([^*]+)\*\*/g, '$1')
      .replace(/\*([^*]+)\*/g, '$1')
      .replace(/__([^_]+)__/g, '$1')
      .replace(/_([^_]+)_/g, '$1')
      .replace(/~~([^~]+)~~/g, '$1')
      .replace(/\[([^\]]+)\]\([^)]*\)/g, '$1');
  }
  /* ──────────────────────────── syntax highlighting ─────────────────────── */
  const KEYWORD_SETS = {
    js: ('break case catch class const continue debugger default delete do else export extends ' +
      'finally for function if import in instanceof interface new return static super switch this ' +
      'throw try type typeof var void while with yield let async await of get set enum implements ' +
      'private public protected readonly namespace declare as from null true false undefined NaN').split(' '),
    py: ('and as assert async await break class continue def del elif else except finally for from ' +
      'global if import in is lambda nonlocal not or pass raise return try while with yield self ' +
      'None True False').split(' '),
    java: ('abstract assert boolean break byte case catch char class const continue default do double ' +
      'else enum extends final finally float for goto if implements import instanceof int interface ' +
      'long native new package private protected public return short static strictfp super switch ' +
      'synchronized this throw throws transient try void volatile while true false null var record ' +
      'sealed permits yield').split(' '),
    c: ('auto break case char const continue default do double else enum extern float for goto if ' +
      'inline int long register restrict return short signed sizeof static struct switch typedef ' +
      'union unsigned void volatile while true false NULL class public private protected virtual ' +
      'template namespace using new delete try catch throw this friend operator').split(' '),
    cs: ('abstract as base bool break byte case catch char checked class const continue decimal ' +
      'default delegate do double else enum event explicit extern false finally fixed float for ' +
      'foreach goto if implicit in int interface internal is lock long namespace new null object ' +
      'operator out override params private protected public readonly record ref return sbyte ' +
      'sealed short sizeof stackalloc static string struct switch this throw true try typeof uint ' +
      'ulong unchecked unsafe ushort using var virtual void volatile while async await').split(' '),
    php: ('abstract and array as break callable case catch class clone const continue declare default ' +
      'do echo else elseif empty enddeclare endfor endforeach endif endswitch endwhile extends final ' +
      'finally fn for foreach function global goto if implements include include_once instanceof ' +
      'insteadof interface isset list match namespace new or print private protected public require ' +
      'require_once return static switch throw trait try unset use var while xor yield true false ' +
      'null').split(' '),
    rb: ('begin break case class def defined? do else elsif end ensure false for if in module next ' +
      'nil not or redo rescue retry return self super then true undef unless until when while yield').split(' '),
    go: ('break case chan const continue default defer else fallthrough for func go goto if import ' +
      'interface map package range return select struct switch type var true false nil iota').split(' '),
    rs: ('as break const continue crate dyn else enum extern false fn for if impl in let loop match ' +
      'mod move mut pub ref return self Self static struct super trait true type unsafe use where ' +
      'while async await').split(' '),
    sh: ('if then elif else fi for while until do done case esac function in return break continue ' +
      'exit local export readonly echo printf source alias unset shift').split(' '),
    sql: ('SELECT FROM WHERE INSERT INTO VALUES UPDATE SET DELETE CREATE TABLE ALTER DROP JOIN LEFT ' +
      'RIGHT INNER OUTER FULL ON GROUP BY ORDER HAVING LIMIT AS AND OR NOT NULL IS DISTINCT UNION ' +
      'ALL EXISTS IN LIKE BETWEEN CASE WHEN THEN END DESC ASC DEFAULT PRIMARY KEY FOREIGN REFERENCES ' +
      'INDEX VIEW WITH').split(' '),
    css: ('important media supports keyframes from to auto inherit initial unset none block inline ' +
      'flex grid absolute relative fixed sticky solid dashed dotted').split(' '),
    yaml: ('true false null yes no on off').split(' '),
    json: ('true false null').split(' '),
    kt: ('as break class continue do else false for fun if in interface is null object package return ' +
      'super this throw true try typealias typeof val var when while by companion constructor init ' +
      'internal lateinit private protected public sealed open override data enum inline suspend ' +
      'vararg where reified crossinline noinline annotation').split(' '),
    swift: ('associatedtype class deinit enum extension func import init inout let operator private ' +
      'protocol public rethrows static struct subscript typealias var break case continue default ' +
      'defer do else fallthrough for guard if in repeat return switch where while as Any catch false ' +
      'is nil rethrows super self Self throw throws true try discardableResult available final ' +
      'lazy weak strong unowned optional required override mutating').split(' '),
    dart: ('abstract as assert async await base break case catch class const continue covariant ' +
      'default deferred do dynamic else enum export extends extension external factory false final ' +
      'finally for Function get hide if implements import in interface is late library mixin new ' +
      'null on operator part required rethrow return sealed set show static super switch sync this ' +
      'throw true try typedef var void while with yield').split(' '),
    scala: ('abstract case catch class def do else extends false final finally for forSome if implicit ' +
      'import lazy match new null object override package private protected return sealed super this ' +
      'throw trait try true type val var while with yield given using enum extension').split(' '),
    lua: ('and break do else elseif end false for function goto if in local nil not or repeat return ' +
      'then true until while').split(' '),
    perl: ('my our local sub if elsif else unless while until for foreach do return last next redo ' +
      'use no package require qw use strict warnings undef defined ref bless die eval print').split(' '),
    r: ('if else repeat while function for next break TRUE FALSE NULL NA Inf NaN in library require').split(' '),
    dockerfile: ('FROM RUN CMD LABEL MAINTAINER EXPOSE ENV ADD COPY ENTRYPOINT VOLUME USER WORKDIR ARG ' +
      'ONBUILD STOPSIGNAL HEALTHCHECK SHELL AS').split(' '),
    ini: ('true false yes no on off').split(' '),
    ps1: ('begin break catch class continue data define do dynamicparam else elseif end exit filter ' +
      'finally for foreach from function if in inlinescript param process return switch throw trap ' +
      'try until using var while workflow parallel sequence true false null').split(' '),
    graphql: ('query mutation subscription fragment on type interface union enum input schema scalar ' +
      'directive implements extend true false null').split(' '),
    groovy: ('as assert break case catch class continue def default do else enum extends false final ' +
      'finally for goto if implements import in instanceof interface new null package return static ' +
      'super switch this throw throws trait true try void while').split(' ')
  };
  const LANG_ALIAS = {
    javascript: 'js', js: 'js', jsx: 'js', mjs: 'js', cjs: 'js',
    typescript: 'js', ts: 'js', tsx: 'js',
    python: 'py', py: 'py', py3: 'py',
    java: 'java',
    c: 'c', h: 'c', cpp: 'c', 'c++': 'c', cc: 'c', hpp: 'c', 'objective-c': 'c',
    csharp: 'cs', 'c#': 'cs', cs: 'cs',
    php: 'php',
    ruby: 'rb', rb: 'rb',
    go: 'go', golang: 'go',
    rust: 'rs', rs: 'rs',
    bash: 'sh', sh: 'sh', shell: 'sh', zsh: 'sh', console: 'sh', shellsession: 'sh',
    sql: 'sql', mysql: 'sql', postgres: 'sql', postgresql: 'sql', sqlite: 'sql',
    css: 'css', scss: 'css', less: 'css',
    yaml: 'yaml', yml: 'yaml',
    json: 'json', json5: 'json', jsonc: 'json',
    html: 'markup', htm: 'markup', xml: 'markup', svg: 'markup', vue: 'markup',
    kotlin: 'kt', kt: 'kt', kts: 'kt',
    swift: 'swift',
    dart: 'dart',
    scala: 'scala', sc: 'scala',
    lua: 'lua',
    perl: 'perl', pl: 'perl',
    r: 'r',
    dockerfile: 'dockerfile', docker: 'dockerfile',
    ini: 'ini', toml: 'ini', conf: 'ini', cfg: 'ini', properties: 'ini',
    powershell: 'ps1', ps1: 'ps1', pwsh: 'ps1',
    graphql: 'graphql', gql: 'graphql',
    groovy: 'groovy', gvy: 'groovy',
    diff: 'diff', patch: 'diff'
  };
  function mdHighlightGeneric(raw, key) {
    const kws = KEYWORD_SETS[key];
    const kwPattern = kws ? kws.map(mdEscapeRe).join('|') : null;
    const parts = [
      '(?<blockComment>\\/\\*[\\s\\S]*?\\*\\/)',
      '(?<lineComment>\\/\\/[^\\n]*|#(?![!{])[^\\n]*|--[^\\n]*)',
      '(?<str>"(?:\\\\.|[^"\\\\\\n])*"|\'(?:\\\\.|[^\'\\\\\\n])*\'|`(?:\\\\.|[^`\\\\])*`)',
      '(?<num>\\b0[xX][0-9a-fA-F]+\\b|\\b\\d+(?:\\.\\d+)?\\b)'
    ];
    if (kwPattern) parts.push('(?<kw>\\b(?:' + kwPattern + ')\\b)');
    let re;
    try {
      re = new RegExp(parts.join('|'), 'g');
    } catch (e) {
      return mdEscapeHtml(raw); // engine without named-group support: degrade gracefully
    }
    let out = '', last = 0, m, guard = 0;
    while ((m = re.exec(raw)) && guard++ < 100000) {
      if (m.index > last) out += mdEscapeHtml(raw.slice(last, m.index));
      const g = m.groups || {};
      const cls = (g.blockComment !== undefined || g.lineComment !== undefined) ? 'tok-comment'
        : g.str !== undefined ? 'tok-string'
          : g.num !== undefined ? 'tok-number'
            : 'tok-keyword';
      out += '<span class="' + cls + '">' + mdEscapeHtml(m[0]) + '</span>';
      last = re.lastIndex;
      if (m[0].length === 0) re.lastIndex++;
    }
    out += mdEscapeHtml(raw.slice(last));
    return out;
  }
  function mdHighlightMarkup(raw) {
    const re = /<!--[\s\S]*?-->|<\/?[a-zA-Z][^<>]*>/g;
    let out = '', last = 0, m;
    while ((m = re.exec(raw))) {
      if (m.index > last) out += mdEscapeHtml(raw.slice(last, m.index));
      const tag = m[0];
      if (/^<!--/.test(tag)) {
        out += '<span class="tok-comment">' + mdEscapeHtml(tag) + '</span>';
      } else {
        const tm = tag.match(/^(<\/?)([a-zA-Z][\w:-]*)([\s\S]*?)(\/?>)$/);
        if (tm) {
          const inner = mdEscapeHtml(tm[3]).replace(
            /([a-zA-Z_:][\w:.-]*)(=)(&quot;[^&]*?&quot;|&#39;[^&]*?&#39;)/g,
            function (mm, an, eq, av) {
              return '<span class="tok-attr">' + an + '</span>' + eq + '<span class="tok-string">' + av + '</span>';
            }
          );
          out += '&lt;' + mdEscapeHtml(tm[1].slice(1)) +
            '<span class="tok-tag">' + mdEscapeHtml(tm[2]) + '</span>' +
            inner + mdEscapeHtml(tm[4]);
        } else {
          out += mdEscapeHtml(tag);
        }
      }
      last = re.lastIndex;
    }
    out += mdEscapeHtml(raw.slice(last));
    return out;
  }
  function mdHighlightDiff(raw) {
    return raw.split('\n').map(function (line) {
      if (/^\+\+\+/.test(line) || /^---/.test(line)) return '<span class="tok-diff-meta">' + mdEscapeHtml(line) + '</span>';
      if (/^@@/.test(line)) return '<span class="tok-diff-hunk">' + mdEscapeHtml(line) + '</span>';
      if (/^\+/.test(line)) return '<span class="tok-diff-add">' + mdEscapeHtml(line) + '</span>';
      if (/^-/.test(line)) return '<span class="tok-diff-del">' + mdEscapeHtml(line) + '</span>';
      return mdEscapeHtml(line);
    }).join('\n');
  }
  function mdHighlightCode(raw, lang) {
    const key = LANG_ALIAS[(lang || '').toLowerCase()];
    if (!key) return mdEscapeHtml(raw);
    if (key === 'markup') return mdHighlightMarkup(raw);
    if (key === 'diff') return mdHighlightDiff(raw);
    return mdHighlightGeneric(raw, key);
  }
  /* Splits already-highlighted (span-only) HTML back into per-line HTML strings,
     correctly re-opening/closing <span> tags that straddle a line break — needed
     for line numbers / line highlighting without corrupting multi-line tokens
     (e.g. block comments) that a naive '\n'.split() would break. */
  function mdSplitHighlightedLines(html) {
    const tokenRe = /<span class="[^"]*">|<\/span>|[^<]+/g;
    const stack = [];
    const lines = [];
    let cur = '';
    let m;
    while ((m = tokenRe.exec(html))) {
      const tok = m[0];
      if (tok === '</span>') { stack.pop(); cur += tok; continue; }
      if (tok.charAt(0) === '<') { stack.push(tok); cur += tok; continue; }
      const parts = tok.split('\n');
      for (let i = 0; i < parts.length; i++) {
        cur += parts[i];
        if (i < parts.length - 1) {
          for (let s = stack.length - 1; s >= 0; s--) cur += '</span>';
          lines.push(cur);
          cur = stack.join('');
        }
      }
    }
    lines.push(cur);
    return lines;
  }
  const COPY_ICON =
    '<svg viewBox="0 0 20 20" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">' +
    '<rect x="7" y="7" width="10" height="10" rx="1.6"></rect>' +
    '<path d="M13 7V4.6A1.6 1.6 0 0 0 11.4 3H4.6A1.6 1.6 0 0 0 3 4.6v6.8A1.6 1.6 0 0 0 4.6 13H7"></path>' +
    '</svg>';
  const CHECK_ICON =
    '<svg viewBox="0 0 20 20" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' +
    '<path d="M4 10.5l3.5 3.5L16 5.5"></path></svg>';
  /* ──────────────────────────── emoji shortcodes ─────────────────────────── */
  const EMOJI_MAP = {
    smile: '😄', laughing: '😆', blush: '😊', grinning: '😀', joy: '😂', wink: '😉',
    heart: '❤️', broken_heart: '💔', thumbsup: '👍', '+1': '👍', thumbsdown: '👎', '-1': '👎',
    rocket: '🚀', fire: '🔥', warning: '⚠️', bulb: '💡', tada: '🎉', sparkles: '✨',
    white_check_mark: '✅', heavy_check_mark: '✔️', x: '❌', question: '❓', exclamation: '❗',
    star: '⭐', eyes: '👀', clap: '👏', wave: '👋', '100': '💯', zap: '⚡', bug: '🐛',
    construction: '🚧', memo: '📝', book: '📖', gear: '⚙️', lock: '🔒', unlock: '🔓',
    key: '🔑', mag: '🔍', bell: '🔔', package: '📦', pushpin: '📌', hourglass: '⏳',
    computer: '💻', link: '🔗', email: '📧', phone: '📱', calendar: '📅',
    chart_with_upwards_trend: '📈', money_with_wings: '💸', raised_hands: '🙌',
    muscle: '💪', crossed_fingers: '🤞', thinking: '🤔', checkered_flag: '🏁',
    recycle: '♻️', hourglass_flowing_sand: '⏳', no_entry: '⛔', anchor: '⚓'
  };
  /* ─────────────────────── raw HTML passthrough (opt-in) ─────────────────── */
  const HTML_TAG_RE = /<!--[\s\S]*?-->|<\/?[a-zA-Z][a-zA-Z0-9-]*(?:\s+[a-zA-Z_:][\w:.-]*(?:\s*=\s*(?:"[^"]*"|'[^']*'|[^\s"'=<>`]+))?)*\s*\/?>/g;
  const BLOCKED_TAGS_RE = /^\s*<\/?(script|style|iframe|object|embed|link|meta|base|form)\b/i;
  function mdCleanRawTag(tagText) {
    if (/^<!--/.test(tagText)) return tagText;
    let cleaned = tagText.replace(/\s+on[a-zA-Z]+\s*=\s*("[^"]*"|'[^']*'|[^\s>]+)/g, '');
    cleaned = cleaned.replace(/(href|src)\s*=\s*"(javascript:|vbscript:)[^"]*"/gi, '$1="#"');
    cleaned = cleaned.replace(/(href|src)\s*=\s*'(javascript:|vbscript:)[^']*'/gi, "$1='#'");
    return cleaned;
  }
  function mdIsHtmlBlockLine(line) {
    const t = line.trim();
    if (!t || t.indexOf('<') === -1) return false;
    const stripped = t.replace(HTML_TAG_RE, '').trim();
    return stripped === '';
  }
  /* ──────────────────────────────── inline ──────────────────────────────── */
  function mdInline(t) {
    const stash = [];
    function save(html) { stash.push(html); return '\u0000' + (stash.length - 1) + '\u0000'; }
    // 1. literal backslash-escapes
    t = t.replace(/\\([\\`*_{}[\]()#+\-.!~>|=^])/g, function (m, ch) { return save(mdEscapeHtml(ch)); });
    // 2. inline code spans
    t = t.replace(/`([^`]+)`/g, function (m, c) { return save('<code>' + mdEscapeHtml(c) + '</code>'); });
    // 2.5 inline math ($$...$$ then $...$) — stashed untouched for client-side KaTeX auto-render
    t = t.replace(/\$\$([^\n]+?)\$\$/g, function (m, expr) { return save('$$' + mdEscapeHtml(expr) + '$$'); });
    t = t.replace(/\$([^\s$](?:[^$\n]*[^\s$])?)\$/g, function (m, expr) { return save('$' + mdEscapeHtml(expr) + '$'); });
    // 3. raw HTML passthrough
    t = t.replace(HTML_TAG_RE, function (m) {
      if (/^<!--/.test(m)) return save(m);
      if (BLOCKED_TAGS_RE.test(m)) return '';
      return save(mdCleanRawTag(m));
    });
    // 4. wiki-links
    t = t.replace(/\[\[([^\]|]+)(?:\|([^\]]+))?\]\]/g, function (m, target, label) {
      const disp = (label || target).trim();
      const slug = target.trim().replace(/\s+/g, '-');
      return save('<a class="wiki-link" href="#/' + encodeURIComponent(slug) +
        '" data-wiki-target="' + mdEscapeHtml(target.trim()) + '">' + mdEscapeHtml(disp) + '</a>');
    });
    // 5. images
    t = t.replace(/!\[([^\]]*)\]\(([^)\s]+)(?:\s+"([^"]*)")?\)/g, function (m, alt, url, title) {
      return save('<img src="' + mdSafeUrl(url) + '" alt="' + mdEscapeHtml(alt) + '"' +
        (title ? ' title="' + mdEscapeHtml(title) + '"' : '') + ' loading="lazy">');
    });
    t = t.replace(/!\[([^\]]*)\]\[([^\]]*)\]/g, function (m, alt, label) {
      const key = (label || alt).trim().toLowerCase();
      const ref = REFS && REFS[key];
      if (!ref) return m;
      return save('<img src="' + mdSafeUrl(ref.url) + '" alt="' + mdEscapeHtml(alt) + '"' +
        (ref.title ? ' title="' + mdEscapeHtml(ref.title) + '"' : '') + ' loading="lazy">');
    });
    // 6. links
    t = t.replace(/\[([^\]]+)\]\(([^)\s]+)(?:\s+"([^"]*)")?\)/g, function (m, txt, url, title) {
      return save('<a href="' + mdSafeUrl(url) + '"' + (title ? ' title="' + mdEscapeHtml(title) + '"' : '') +
        mdLinkTarget(url) + '>' + mdEscapeHtml(txt) + '</a>');
    });
    t = t.replace(/\[([^\]]+)\]\[([^\]]*)\]/g, function (m, txt, label) {
      const key = (label || txt).trim().toLowerCase();
      const ref = REFS && REFS[key];
      if (!ref) return m;
      return save('<a href="' + mdSafeUrl(ref.url) + '"' + (ref.title ? ' title="' + mdEscapeHtml(ref.title) + '"' : '') +
        mdLinkTarget(ref.url) + '>' + mdEscapeHtml(txt) + '</a>');
    });
    t = t.replace(/\[([^\]]+)\](?!\(|\[|:|\u0000)/g, function (m, label) {
      const key = label.trim().toLowerCase();
      const ref = REFS && REFS[key];
      if (!ref) return m;
      return save('<a href="' + mdSafeUrl(ref.url) + '"' + (ref.title ? ' title="' + mdEscapeHtml(ref.title) + '"' : '') +
        mdLinkTarget(ref.url) + '>' + mdEscapeHtml(label) + '</a>');
    });
    // 7. footnote references
    t = t.replace(/\[\^([^\]\s]+)\]/g, function (m, label) {
      if (!FN) return m;
      let idx = FN.order.indexOf(label);
      if (idx === -1) { FN.order.push(label); idx = FN.order.length - 1; }
      const n = idx + 1;
      FN.counts[label] = (FN.counts[label] || 0) + 1;
      const occ = FN.counts[label];
      return save('<sup class="footnote-ref"><a href="#fn-' + n + '" id="fnref-' + n + '-' + occ + '">' + n + '</a></sup>');
    });
    // 7.5 emoji shortcodes
    t = t.replace(/:([a-zA-Z0-9_+\-]+):/g, function (m, code) {
      const e = EMOJI_MAP[code.toLowerCase()];
      return e ? save(e) : m;
    });
    // 8. bare URL autolinking
    t = t.replace(/(^|[\s(])((?:https?:\/\/|www\.)[^\s<]+)/g, function (m, pre, url) {
      let trail = '';
      const tm = url.match(/[).,;:!?]+$/);
      if (tm) { trail = tm[0]; url = url.slice(0, -trail.length); }
      if (!url) return m;
      const href = /^https?:\/\//i.test(url) ? url : 'http://' + url;
      return pre + save('<a href="' + mdSafeUrl(href) + '" target="_blank" rel="noopener">' + mdEscapeHtml(url) + '</a>') + trail;
    });
    // 9. escape + emphasis
    t = mdEscapeHtml(t);
    t = t.replace(/\*\*\*([^*]+)\*\*\*/g, '<strong><em>$1</em></strong>');
    t = t.replace(/___([^_]+)___/g, '<strong><em>$1</em></strong>');
    t = t.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
    t = t.replace(/__([^_]+)__/g, '<strong>$1</strong>');
    t = t.replace(/\*([^*\n]+)\*/g, '<em>$1</em>');
    t = t.replace(/(^|[\s(])_([^_\n]+)_(?=[\s).,!?:;]|$)/g, '$1<em>$2</em>');
    // 10. strikethrough, subscript, highlight, superscript
    t = t.replace(/~~([^~]+)~~/g, '<del>$1</del>');
    t = t.replace(/~([^~\s]+)~/g, '<sub>$1</sub>');
    t = t.replace(/==([^=]+)==/g, '<mark>$1</mark>');
    t = t.replace(/\^([^\s^]+)\^/g, '<sup>$1</sup>');
    // 11. line breaks — smart newline detection
    // (a) Classic forced break: two trailing spaces or a backslash.
    //     The newline is consumed so it can't double up below.
    t = t.replace(/(?: {2,}|\\)\n/g, '<br>');
    // (b) SMART NEWLINE (GitHub-style): any remaining single line break
    //     becomes a visible line break instead of being folded into a
    //     space. Empty lines still start a new paragraph as before.
    t = t.replace(/\n/g, '<br>');
    // restore stashed fragments
    t = t.replace(/\u0000(\d+)\u0000/g, function (m, i) { return stash[+i]; });
    return t;
  }
  /* ──────────────────────────────── block helpers ────────────────────────── */
  function mdIsHeading(l) { return /^#{1,6}\s/.test(l); }
  function mdIsFence(l) { return /^\s*```/.test(l); }
  function mdIsHr(l) { return /^\s*([-*_])\s*(?:\1\s*){2,}$/.test(l); }
  function mdIsQuote(l) { return /^\s*>\s?/.test(l); }
  function mdIsListItem(l) { return /^\s*(?:[-*+]|\d+[.)])\s+/.test(l); }
  function mdIsFootnoteDef(l) { return /^\[\^[^\]\s]+\]:/.test(l); }
  function mdIsMathFence(l) { return l.trim() === '$$'; }
  function mdIsDefMarker(l) { return /^:\s+/.test(l); }
  function mdIsTableAt(lines, i) {
    if (i + 1 >= lines.length) return false;
    const a = lines[i], b = lines[i + 1];
    return a.includes('|') && /^\s*\|?[\s:|-]+\|?\s*$/.test(b) && b.includes('-') && a.trim() !== '';
  }
  function mdParseRow(line) {
    line = line.trim();
    if (line.startsWith('|')) line = line.slice(1);
    if (line.endsWith('|')) line = line.slice(0, -1);
    const ESC = '\u0001';
    line = line.replace(/\\\|/g, ESC);
    return line.split('|').map(function (c) {
      return c.trim().split(ESC).join('|');
    });
  }
  function mdParseTable(lines, start) {
    const headers = mdParseRow(lines[start]);
    const aligns = mdParseRow(lines[start + 1]).map(function (c) {
      const L = c.startsWith(':'), R = c.endsWith(':');
      return L && R ? 'center' : R ? 'right' : L ? 'left' : '';
    });
    let i = start + 2;
    const rows = [];
    while (i < lines.length && lines[i].includes('|') && lines[i].trim() !== '') {
      rows.push(mdParseRow(lines[i]));
      i++;
    }
    let h = '<div class="table-wrap"><table><thead><tr>';
    headers.forEach(function (hd, idx) {
      const a = aligns[idx] ? ' style="text-align:' + aligns[idx] + '"' : '';
      h += '<th' + a + '>' + mdInline(hd) + '</th>';
    });
    h += '</tr></thead><tbody>';
    rows.forEach(function (r) {
      h += '<tr>';
      headers.forEach(function (_, idx) {
        const a = aligns[idx] ? ' style="text-align:' + aligns[idx] + '"' : '';
        h += '<td' + a + '>' + mdInline(r[idx] || '') + '</td>';
      });
      h += '</tr>';
    });
    h += '</tbody></table></div>';
    return { html: h, next: i };
  }
  function mdParseList(lines, start) {
    const base = (lines[start].match(/^(\s*)/) || ['', ''])[0].length;
    const firstMarker = (lines[start].match(/^\s*(?:[-*+]|\d+[.)])/) || [''])[0];
    const type = /\d/.test(firstMarker) ? 'ol' : 'ul';
    let startNum = null;
    if (type === 'ol') {
      const nm = lines[start].match(/^\s*(\d+)[.)]/);
      if (nm) startNum = parseInt(nm[1], 10);
    }
    let i = start;
    const items = [];
    while (i < lines.length) {
      const line = lines[i];
      const m = line.match(/^(\s*)([-*+]|\d+[.)])\s+(.*)$/);
      if (!m) {
        if (line.trim() === '') {
          if (i + 1 < lines.length) {
            const nm2 = lines[i + 1].match(/^(\s*)([-*+]|\d+[.)])\s+/);
            if (nm2 && nm2[1].length >= base) {
              const nextType = /\d/.test(nm2[2]) ? 'ol' : 'ul';
              if (nextType === type) { i++; continue; }
            }
          }
          break;
        }
        if (items.length && /^\s+/.test(line)) {
          items[items.length - 1].text += ' ' + line.trim();
          i++; continue;
        }
        break;
      }
      const indent = m[1].length;
      if (indent < base) break;
      if (indent > base) {
        const nested = mdParseList(lines, i);
        if (items.length) items[items.length - 1].nested += nested.html;
        i = nested.next; continue;
      }
      items.push({ text: m[3], nested: '' });
      i++;
    }
    let h = '<' + type + (type === 'ol' && startNum && startNum !== 1 ? ' start="' + startNum + '"' : '') + '>';
    items.forEach(function (it) {
      const task = it.text.match(/^\[([ xX])\]\s+(.*)$/);
      if (task) {
        const ck = task[1] !== ' ';
        h += '<li class="task"><label><input type="checkbox" disabled' + (ck ? ' checked' : '') +
          '><span>' + mdInline(task[2]) + '</span></label>' + it.nested + '</li>';
      } else {
        h += '<li>' + mdInline(it.text) + it.nested + '</li>';
      }
    });
    h += '</' + type + '>';
    return { html: h, next: i };
  }
  /* ────────────────────── document-level extraction passes ──────────────── */
  function mdExtractFrontMatter(src) {
    const m = src.match(/^---[ \t]*\n([\s\S]*?)\n---[ \t]*\n?/);
    if (!m) return { src: src, meta: null, order: [] };
    const meta = {}, order = [];
    m[1].split('\n').forEach(function (line) {
      const km = line.match(/^([A-Za-z_][\w-]*):\s*(.*)$/);
      if (!km) return;
      const key = km[1], raw = km[2].trim();
      let val;
      if (/^\[[\s\S]*\]$/.test(raw)) {
        val = raw.slice(1, -1).split(',').map(function (s) {
          return s.trim().replace(/^["']|["']$/g, '');
        }).filter(function (s) { return s !== ''; });
      } else if (/^".*"$/.test(raw) || /^'.*'$/.test(raw)) {
        val = raw.slice(1, -1);
      } else {
        val = raw;
      }
      meta[key] = val;
      order.push(key);
    });
    return { src: src.slice(m[0].length), meta: meta, order: order };
  }
  function mdRenderFrontMatter(meta, order) {
    if (!meta) return '';
    let rows = '';
    order.forEach(function (key) {
      if (key.toLowerCase() === 'title') return;
      const val = meta[key];
      let display;
      if (Array.isArray(val)) {
        display = '<span class="fm-tags">' + val.map(function (v) {
          return '<span class="fm-tag">' + mdEscapeHtml(v) + '</span>';
        }).join('') + '</span>';
      } else {
        display = mdEscapeHtml(String(val));
      }
      const label = key.replace(/[_-]/g, ' ').replace(/\b\w/g, function (c) { return c.toUpperCase(); });
      rows += '<div class="fm-row"><span class="fm-key">' + mdEscapeHtml(label) + '</span>' +
        '<span class="fm-val">' + display + '</span></div>';
    });
    const titleHtml = meta.title ? '<div class="fm-title">' + mdEscapeHtml(String(meta.title)) + '</div>' : '';
    return '<div class="frontmatter">' + titleHtml + '<div class="fm-grid">' + rows + '</div></div>';
  }
  function mdExtractFootnoteDefs(src) {
    const lines = src.split('\n'), kept = [], defs = {};
    for (let i = 0; i < lines.length; i++) {
      const m = lines[i].match(/^\[\^([^\]\s]+)\]:\s?(.*)$/);
      if (m) {
        const label = m[1];
        let text = m[2];
        while (i + 1 < lines.length && /^(\s{2,}|\t)\S/.test(lines[i + 1])) {
          i++;
          text += ' ' + lines[i].trim();
        }
        defs[label] = text;
      } else {
        kept.push(lines[i]);
      }
    }
    return { src: kept.join('\n'), defs: defs };
  }
  function mdExtractLinkRefs(src) {
    const lines = src.split('\n'), kept = [], refs = {};
    for (let i = 0; i < lines.length; i++) {
      const m = lines[i].match(/^\s*\[([^\]]+)\]:\s*<?([^\s>]+)>?(?:\s+"([^"]*)"|\s+'([^']*)'|\s+\(([^)]*)\))?\s*$/);
      if (m && m[1].charAt(0) !== '^') {
        const key = m[1].trim().toLowerCase();
        refs[key] = { url: m[2], title: m[3] || m[4] || m[5] || '' };
      } else {
        kept.push(lines[i]);
      }
    }
    return { src: kept.join('\n'), refs: refs };
  }
  function mdExtractAbbrDefs(src) {
    const lines = src.split('\n'), kept = [], abbrs = {};
    for (let i = 0; i < lines.length; i++) {
      const m = lines[i].match(/^\*\[([^\]]+)\]:\s*(.+)$/);
      if (m) { abbrs[m[1]] = m[2].trim(); } else { kept.push(lines[i]); }
    }
    return { src: kept.join('\n'), abbrs: abbrs };
  }
  /* ─────────────────────────────── block renderer ────────────────────────── */
  function renderMarkdownToHtml(src) {
    src = src.replace(/\r\n?/g, '\n');
    const lines = src.split('\n');
    const out = [];
    let i = 0;
    while (i < lines.length) {
      const line = lines[i];
      if (mdIsFootnoteDef(line)) { i++; continue; }
      if (mdIsFence(line)) {
        const fenceMeta = line.trim().slice(3).trim();
        const fm = fenceMeta.match(/^(\S*)\s*(.*)$/) || ['', '', ''];
        const lang = fm[1] || '';
        const attrs = fm[2] || '';
        const buf = [];
        i++;
        while (i < lines.length && !mdIsFence(lines[i])) { buf.push(lines[i]); i++; }
        i++;
        const code = buf.join('\n');
        if (lang.toLowerCase() === 'mermaid') {
          out.push(
            '<div class="code-block mermaid-block">' +
            '<button type="button" class="code-copy" onclick="window.IDE.markdown.copyCode(this)" aria-label="Copy diagram source">' +
            '<span class="icon-copy">' + COPY_ICON + '</span><span class="icon-check">' + CHECK_ICON + '</span>' +
            '</button>' +
            '<pre class="mermaid" data-source="' + mdEscapeHtml(code) + '">' + mdEscapeHtml(code) + '</pre>' +
            '</div>'
          );
          continue;
        }
        const showLineNumbers = /\bshowLineNumbers\b/i.test(attrs);
        const hlSet = {};
        const hlm = attrs.match(/\{([\d,\s-]+)\}/);
        if (hlm) {
          hlm[1].split(',').forEach(function (part) {
            part = part.trim();
            const rangeM = part.match(/^(\d+)-(\d+)$/);
            if (rangeM) {
              for (let n = parseInt(rangeM[1], 10); n <= parseInt(rangeM[2], 10); n++) hlSet[n] = true;
            } else if (/^\d+$/.test(part)) {
              hlSet[parseInt(part, 10)] = true;
            }
          });
        }
        const hasLineFeatures = showLineNumbers || hlm;
        const codeLines = code.split('\n');
        const collapsible = !hasLineFeatures && codeLines.length > 30;
        let body;
        if (hasLineFeatures) {
          const highlighted = mdHighlightCode(code, lang);
          const perLine = mdSplitHighlightedLines(highlighted);
          body = perLine.map(function (lineHtml, idx) {
            const n = idx + 1;
            return '<span class="code-line' + (hlSet[n] ? ' code-line-hl' : '') + '" data-line="' + n + '">' + lineHtml + '</span>';
          }).join('\n');
        } else {
          body = mdHighlightCode(code, lang);
        }
        const label = lang ? mdEscapeHtml(lang) : '';
        const wrapClasses = 'code-block' + (showLineNumbers ? ' line-numbers' : '') + (collapsible ? ' code-collapsible' : '');
        out.push(
          '<div class="' + wrapClasses + '"' + (label ? ' data-lang="' + label + '"' : '') + '>' +
          '<button type="button" class="code-copy" onclick="window.IDE.markdown.copyCode(this)" aria-label="Copy code">' +
          '<span class="icon-copy">' + COPY_ICON + '</span><span class="icon-check">' + CHECK_ICON + '</span>' +
          '</button>' +
          '<pre><code>' + body + '</code></pre>' +
          (collapsible ? '<button type="button" class="code-expand" onclick="window.IDE.markdown.toggleCode(this)">Show more</button>' : '') +
          '</div>'
        );
        continue;
      }
      if (mdIsMathFence(line)) {
        const mbuf = [];
        i++;
        while (i < lines.length && !mdIsMathFence(lines[i])) { mbuf.push(lines[i]); i++; }
        i++;
        out.push('<div class="math-block">$$' + mdEscapeHtml(mbuf.join('\n')) + '$$</div>');
        continue;
      }
      const hm = line.match(/^(#{1,6})\s+(.*)$/);
      if (hm) {
        const lv = hm[1].length;
        const raw = hm[2].replace(/\s+#+\s*$/, '');
        const id = mdSlugify(raw);
        if (HEADINGS) HEADINGS.push({ level: lv, id: id, text: mdStripMd(raw) });
        out.push('<h' + lv + ' id="' + id + '"><a class="heading-anchor" href="#' + id + '" aria-hidden="true">#</a>' +
          mdInline(raw) + '</h' + lv + '>');
        i++; continue;
      }
      if (/^\s*\[\[?TOC\]?\]\s*$/i.test(line)) { out.push('\u0000TOC\u0000'); i++; continue; }
      if (mdIsHr(line)) { out.push('<hr>'); i++; continue; }
      if (mdIsQuote(line)) {
        const qbuf = [];
        while (i < lines.length && mdIsQuote(lines[i])) { qbuf.push(lines[i].replace(/^\s*>\s?/, '')); i++; }
        const callout = qbuf.length && qbuf[0].match(/^\s*\[!(NOTE|TIP|IMPORTANT|WARNING|CAUTION)\]\s*(.*)$/i);
        let cls = 'blockquote';
        let titleHtml = '';
        if (callout) {
          const kind = callout[1].toUpperCase();
          cls += ' callout callout-' + kind.toLowerCase();
          titleHtml = '<div class="callout-title">' + kind.charAt(0) + kind.slice(1).toLowerCase() + '</div>';
          qbuf[0] = callout[2];
          if (qbuf[0].trim() === '') qbuf.shift();
        }
        out.push('<blockquote class="' + cls + '">' + titleHtml + renderMarkdownToHtml(qbuf.join('\n')) + '</blockquote>');
        continue;
      }
      if (mdIsTableAt(lines, i)) {
        const t = mdParseTable(lines, i);
        out.push(t.html); i = t.next; continue;
      }
      if (mdIsListItem(line)) {
        const l = mdParseList(lines, i);
        out.push(l.html); i = l.next; continue;
      }
      if (line.trim() !== '' && i + 1 < lines.length && mdIsDefMarker(lines[i + 1]) &&
        !mdIsHeading(line) && !mdIsListItem(line) && !mdIsQuote(line) && !mdIsFence(line) &&
        !mdIsHr(line) && !mdIsTableAt(lines, i) && !mdIsHtmlBlockLine(line)) {
        const termText = line.trim();
        i++;
        const ddParts = [];
        while (i < lines.length && mdIsDefMarker(lines[i])) {
          const dm = lines[i].match(/^:\s+(.*)$/);
          let ddText = dm[1];
          i++;
          while (i < lines.length && /^[ \t]+\S/.test(lines[i]) && lines[i].trim() !== '') {
            ddText += ' ' + lines[i].trim(); i++;
          }
          ddParts.push('<dd>' + mdInline(ddText) + '</dd>');
        }
        const block = '<dl><dt>' + mdInline(termText) + '</dt>' + ddParts.join('') + '</dl>';
        if (out.length && /^<dl>[\s\S]*<\/dl>$/.test(out[out.length - 1])) {
          const prev = out.pop();
          out.push(prev.slice(0, -'</dl>'.length) + block.slice('<dl>'.length));
        } else {
          out.push(block);
        }
        continue;
      }
      if (line.trim() === '') { i++; continue; }
      if (mdIsHtmlBlockLine(line)) { out.push(line.trim()); i++; continue; }
      const pbuf = [];
      while (i < lines.length && lines[i].trim() !== '' && !mdIsHeading(lines[i]) && !mdIsFence(lines[i]) &&
        !mdIsMathFence(lines[i]) && !mdIsHr(lines[i]) && !mdIsQuote(lines[i]) && !mdIsListItem(lines[i]) &&
        !mdIsTableAt(lines, i) && !mdIsFootnoteDef(lines[i]) && !mdIsHtmlBlockLine(lines[i]) &&
        !(i + 1 < lines.length && mdIsDefMarker(lines[i + 1]) && pbuf.length === 0 === false)) {
        pbuf.push(lines[i]); i++;
      }
      if (pbuf.length) {
        const soloImg = pbuf.length === 1 &&
          pbuf[0].trim().match(/^!\[([^\]]*)\]\(([^)\s]+)\s+"([^"]*)"\)$/);
        if (soloImg) {
          out.push('<figure><img src="' + mdSafeUrl(soloImg[2]) + '" alt="' + mdEscapeHtml(soloImg[1]) + '" loading="lazy">' +
            '<figcaption>' + mdEscapeHtml(soloImg[3]) + '</figcaption></figure>');
        } else {
          out.push('<p>' + mdInline(pbuf.join('\n')) + '</p>');
        }
      }
    }
    return out.join('\n');
  }
  /* ─────────────────────────── abbreviation post-pass ────────────────────── */
  function mdApplyAbbreviations(html, abbrs) {
    const keys = Object.keys(abbrs || {});
    if (!keys.length) return html;
    keys.sort(function (a, b) { return b.length - a.length; });
    const segs = html.split(/(<[^>]+>)/);
    for (let s = 0; s < segs.length; s += 2) {
      let seg = segs[s];
      if (!seg) continue;
      keys.forEach(function (term) {
        const re = new RegExp('\\b' + mdEscapeRe(term) + '\\b', 'g');
        seg = seg.replace(re, '<abbr title="' + mdEscapeHtml(abbrs[term]) + '">' + term + '</abbr>');
      });
      segs[s] = seg;
    }
    return segs.join('');
  }
  /* Public entry point */
  function mdToHtml(src) {
    SLUGS = {};
    FN = { defs: {}, order: [], counts: {} };
    REFS = {};
    ABBRS = {};
    HEADINGS = [];
    const raw = String(src == null ? '' : src);
    const front = mdExtractFrontMatter(raw);
    const afterFn = mdExtractFootnoteDefs(front.src);
    FN.defs = afterFn.defs;
    const afterRefs = mdExtractLinkRefs(afterFn.src);
    REFS = afterRefs.refs;
    const afterAbbr = mdExtractAbbrDefs(afterRefs.src);
    ABBRS = afterAbbr.abbrs;
    let html = renderMarkdownToHtml(afterAbbr.src);
    if (HEADINGS.length && /\u0000TOC\u0000/.test(html)) {
      html = html.replace(/\u0000TOC\u0000/g, mdRenderToc(HEADINGS));
    }
    if (FN.order.length) {
      let fh = '<hr class="footnotes-sep"><ol class="footnotes">';
      FN.order.forEach(function (label, idx) {
        const n = idx + 1;
        const content = Object.prototype.hasOwnProperty.call(FN.defs, label)
          ? mdInline(FN.defs[label])
          : '<em>Undefined reference: ' + mdEscapeHtml(label) + '</em>';
        const occCount = FN.counts[label] || 1;
        let backs = '';
        for (let occ = 1; occ <= occCount; occ++) {
          backs += ' <a href="#fnref-' + n + '-' + occ + '" class="footnote-back" aria-label="Back to content">↩' +
            (occCount > 1 ? '<sup>' + occ + '</sup>' : '') + '</a>';
        }
        fh += '<li id="fn-' + n + '">' + content + backs + '</li>';
      });
      fh += '</ol>';
      html += fh;
    }
    html = mdApplyAbbreviations(html, ABBRS);
    html = mdRenderFrontMatter(front.meta, front.order) + html;
    SLUGS = null; FN = null; REFS = null; ABBRS = null; HEADINGS = null;
    return html;
  }
  function mdRenderToc(headings) {
    if (!headings.length) return '';
    const minLevel = headings.reduce(function (m, h) { return Math.min(m, h.level); }, 6);
    let html = '<ul>';
    let curLevel = minLevel;
    headings.forEach(function (h, idx) {
      if (h.level > curLevel) {
        while (curLevel < h.level) { html += '<ul>'; curLevel++; }
      } else if (h.level < curLevel) {
        while (curLevel > h.level) { html += '</li></ul>'; curLevel--; }
        html += '</li>';
      } else if (idx !== 0) {
        html += '</li>';
      }
      html += '<li><a href="#' + h.id + '">' + mdEscapeHtml(h.text) + '</a>';
    });
    html += '</li></ul>';
    return '<nav class="toc"><div class="toc-title">Contents</div>' + html + '</nav>';
  }
  /**
  * Extract a document title from Markdown content.
  * Priority: 1) YAML front matter "title:" field, 2) first # heading,
  * 3) fall back to the provided filename.
  * Used by preview.js and live-preview.php to set the tab/page title.
  */
  function deriveTitle(content, fallbackName) {
    if (!content || typeof content !== 'string') return fallbackName || 'Preview';

    // 1. Check YAML front matter for a title field
    var fmMatch = content.match(/^---[ \t]*\r?\n([\s\S]*?)\r?\n---/);
    if (fmMatch) {
      var titleMatch = fmMatch[1].match(/^title:\s*["']?([^"'\r\n]+)["']?\s*$/m);
      if (titleMatch && titleMatch[1].trim() !== '') {
        return titleMatch[1].trim();
      }
    }

    // 2. Check for the first H1 heading (# Title)
    var h1Match = content.match(/^#{1}\s+(.+)$/m);
    if (h1Match) {
      var text = h1Match[1].trim();
      // Strip any trailing closing hashes: "# Title ##" → "Title"
      text = text.replace(/\s+#+\s*$/, '');
      // Strip inline markdown formatting
      text = text.replace(/`([^`]+)`/g, '$1')
        .replace(/\*\*\*([^*]+)\*\*\*/g, '$1')
        .replace(/\*\*([^*]+)\*\*/g, '$1')
        .replace(/\*([^*]+)\*/g, '$1')
        .replace(/__([^_]+)__/g, '$1')
        .replace(/_([^_]+)_/g, '$1')
        .replace(/\[([^\]]+)\]\([^)]*\)/g, '$1')
        .replace(/!\[([^\]]*)\]\([^)]*\)/g, '$1');
      if (text.trim() !== '') return text.trim();
    }

    // 3. Fall back to the filename
    return fallbackName || 'Preview';
  }
  /* Invoked from the copy button's onclick inside the rendered document. */
  function copyCode(btn) {
    try {
      const block = btn.closest ? btn.closest('.code-block') : null;
      const mermaidEl = block ? block.querySelector('.mermaid[data-source]') : null;
      let text;
      if (mermaidEl) {
        text = mermaidEl.getAttribute('data-source');
      } else {
        const codeEl = block ? block.querySelector('code') : null;
        text = codeEl ? codeEl.textContent : '';
      }
      const flash = function () {
        btn.classList.add('copied');
        setTimeout(function () { btn.classList.remove('copied'); }, 1400);
      };
      if (typeof navigator !== 'undefined' && navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(flash, flash);
      } else if (typeof document !== 'undefined') {
        const ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); } catch (e) { /* no-op */ }
        document.body.removeChild(ta);
        flash();
      }
    } catch (e) { /* clipboard access denied or unavailable — fail silently */ }
  }
  /* Invoked from the "Show more" / "Show less" button on long collapsed code blocks. */
  function toggleCode(btn) {
    try {
      const block = btn.closest ? btn.closest('.code-block') : null;
      if (!block) return;
      const collapsed = block.classList.toggle('expanded');
      btn.textContent = collapsed ? 'Show less' : 'Show more';
    } catch (e) { /* no-op */ }
  }
  /* ─────────────────────────────── document wrap ────────────────────────── */
  function wrapMarkdownHtml(bodyHtml, title) {
    return '<!DOCTYPE html><html><head><meta charset="UTF-8">' +
      '<meta name="viewport" content="width=device-width, initial-scale=1">' +
      '<title>' + mdEscapeHtml(title || 'Preview') + '</title>' +
      '<link rel="stylesheet" href="index.php?vendor=katex/katex.min.css" ' +
      'onerror="this.onerror=null;this.href=\'https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/katex.min.css\'">' +
      '<style>' + MD_CSS + '</style>' +
      '</head><body><article class="md">' + bodyHtml + '</article>' +
      '<script src="index.php?vendor=mermaid/mermaid.min.js"></script>' +
      '<script src="index.php?vendor=katex/katex.min.js"></script>' +
      '<script src="index.php?vendor=katex/contrib/auto-render.min.js"></script>' +
      '<script>' + MD_BOOTSTRAP_JS + '</script>' +
      '</body></html>';
  }
  const MD_BOOTSTRAP_JS =
    '(function(){' +
    'function _toast(msg){var t=document.querySelector(".md-copied-toast");' +
    'if(!t){t=document.createElement("div");t.className="md-copied-toast";document.body.appendChild(t);}' +
    't.textContent=msg;t.classList.add("visible");clearTimeout(t._h);t._h=setTimeout(function(){t.classList.remove("visible");},1200);}' +
    'document.addEventListener("click",function(e){' +
    'var a=e.target;while(a&&a.tagName!=="A")a=a.parentElement;' +
    'if(!a)return;' +
    'var h=a.getAttribute("href")||"";' +
    'if(h.charAt(0)!=="#")return;' +
    'e.preventDefault();' + /* ★ ALWAYS swallow fragment navs — kills the IDE-in-IDE paradox */
    'var id=h.slice(1);if(!id)return;' +
    'var el=document.getElementById(id);' +
    'if(!el){var n=id.toLowerCase().replace(/-+/g,"-").replace(/^-+|-+$/g,"");' + /* ★ fuzzy: "--" == "-" */
    'var cs=document.querySelectorAll("[id]");' +
    'for(var i=0;i<cs.length;i++){var c=cs[i].id.toLowerCase().replace(/-+/g,"-").replace(/^-+|-+$/g,"");if(c===n){el=cs[i];break;}}}' +
    'if(!el)return;' +
    'el.scrollIntoView({behavior:"smooth",block:"start"});' +
    'if(a.classList.contains("heading-anchor")){' +
    'try{history.replaceState(null,"",h);}catch(err){}' +
    'var url=location.origin+location.pathname+location.search+h;' +
    'if(navigator.clipboard&&navigator.clipboard.writeText){navigator.clipboard.writeText(url).then(function(){_toast("Link copied");});}' +
    '}' +
    '});' +
    'function _readingTime(){try{' +
    'var art=document.querySelector("article.md");if(!art)return;' +
    'var words=(art.textContent||"").trim().split(/\\s+/).filter(Boolean).length;' +
    'if(words<40)return;' +
    'var mins=Math.max(1,Math.round(words/200));' +
    'var badge=document.createElement("div");badge.className="md-reading-time";' +
    'badge.textContent=words+" words · "+mins+" min read";' +
    'var firstH=art.querySelector("h1,h2");' +
    'if(firstH&&firstH.nextSibling){firstH.parentNode.insertBefore(badge,firstH.nextSibling);}else{art.insertBefore(badge,art.firstChild);}' +
    '}catch(e){}}' +
    'function _backToTop(){try{' +
    'var btn=document.createElement("button");btn.className="md-back-to-top";btn.setAttribute("aria-label","Back to top");btn.textContent="↑";' +
    'document.body.appendChild(btn);' +
    'btn.addEventListener("click",function(){window.scrollTo({top:0,behavior:"smooth"});});' +
    'window.addEventListener("scroll",function(){btn.classList.toggle("visible",window.scrollY>600);},{passive:true});' +
    '}catch(e){}}' +
    '_readingTime();_backToTop();' +
    'var dark=window.matchMedia&&window.matchMedia("(prefers-color-scheme: dark)").matches;' +
    'function _ld(u,cb){var s=document.createElement("script");s.src=u;s.onload=cb;s.onerror=cb;document.body.appendChild(s);}' +
    'function _mm(){try{if(!window.mermaid)return;' +
    'window.mermaid.initialize({startOnLoad:false,theme:dark?"dark":"default",securityLevel:"strict",' +
    'gantt:{barHeight:24,barGap:6,topPadding:40,leftPadding:70,rightPadding:10,gridLineStartPadding:5,gridLineEndPadding:5,fontSize:12,sectionFontSize:12,numberSectionStyles:2,axisFormat:"%b %d",tickInterval:"1week",barBorderRadius:3},' +
    'flowchart:{useMaxWidth:true},sequence:{useMaxWidth:true},pie:{useMaxWidth:true}});' +
    'var _gp=document.querySelectorAll("pre.mermaid");' +
    'for(var _i=0;_i<_gp.length;_i++){if(((_gp[_i].getAttribute("data-source")||"").trim()).indexOf("gantt")===0){_gp[_i].style.width="900px";_gp[_i].style.maxWidth="none";}}' +
    'var _gr=function(){if(window.mermaid.run){window.mermaid.run().then(_gf).catch(function(){});}};' +
    'if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",_gr);}else{_gr();}' +
    '}catch(e){}}' +
    'function _gf(){var _sv=document.querySelectorAll("pre.mermaid svg");' +
    'for(var _j=0;_j<_sv.length;_j++){var _p=_sv[_j].closest("pre.mermaid");if(!_p)continue;if(((_p.getAttribute("data-source")||"").trim()).indexOf("gantt")!==0)continue;' +
    'var _w=parseFloat(_sv[_j].getAttribute("width"))||0;var _h=parseFloat(_sv[_j].getAttribute("height"))||0;' +
    'if(_w>0&&_h>0){_sv[_j].setAttribute("viewBox","0 0 "+_w+" "+_h);_sv[_j].removeAttribute("width");_sv[_j].removeAttribute("height");}' +
    '_sv[_j].style.width="100%";_sv[_j].style.height="auto";_sv[_j].style.maxWidth="100%";' +
    '_p.style.width="";_p.style.maxWidth="";}}' +
    'function _kx(){try{if(window.renderMathInElement){window.renderMathInElement(document.querySelector("article.md"),{' +
    'delimiters:[{left:"$$",right:"$$",display:true},{left:"$",right:"$",display:false}],throwOnError:false});}}catch(e){}}' +
    'if(window.mermaid){_mm();}else{_ld("https://cdn.jsdelivr.net/npm/mermaid@11/dist/mermaid.min.js",_mm);}' +
    'if(window.renderMathInElement){_kx();}else{_ld("https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/katex.min.js",function(){_ld("https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/contrib/auto-render.min.js",_kx);});}' +
    /* ★ FIX: the "Show more" / copy buttons inside the preview call
    window.IDE.markdown.* — which only existed on the OUTER page.
    Define them INSIDE the wrapped document so the buttons work. */
    'function _cb(btn){try{var b=btn.closest(".code-block");if(!b)return;' +
    'var m=b.querySelector(".mermaid[data-source]");' +
    'var t=m?m.getAttribute("data-source"):(b.querySelector("code")?b.querySelector("code").textContent:"");' +
    'var f=function(){btn.classList.add("copied");setTimeout(function(){btn.classList.remove("copied");},1400);};' +
    'if(navigator.clipboard&&navigator.clipboard.writeText){navigator.clipboard.writeText(t).then(f,f);}' +
    'else{var ta=document.createElement("textarea");ta.value=t;ta.style.position="fixed";ta.style.opacity="0";document.body.appendChild(ta);ta.select();try{document.execCommand("copy");}catch(e){}document.body.removeChild(ta);f();}}catch(e){}}' +
    'function _tb(btn){try{var b=btn.closest(".code-block");if(!b)return;var x=b.classList.toggle("expanded");btn.textContent=x?"Show less":"Show more";}catch(e){}}' +
    'window.IDE=window.IDE||{};window.IDE.markdown=window.IDE.markdown||{};window.IDE.markdown.copyCode=_cb;window.IDE.markdown.toggleCode=_tb;' +
    '})();';
  const MD_CSS =
    ':root{' +
    '--md-bg:#ffffff;--md-fg:#1c2430;--md-muted:#64748b;--md-border:#e2e8f0;' +
    '--md-accent:#2563eb;--md-quote-bg:#eff6ff;--md-quote-border:#3b82f6;' +
    '--md-code-bg:#0f172a;--md-code-fg:#e2e8f0;--md-inline-code-bg:#f1f5f9;--md-inline-code-fg:#0f766e;' +
    '--md-mark-bg:#fef08a;--md-mark-fg:#3f2d00;--md-table-stripe:#f8fafc;' +
    '--tok-comment:#8b98a8;--tok-string:#7ee787;--tok-number:#ffab70;--tok-keyword:#79c0ff;' +
    '--tok-tag:#7ee787;--tok-attr:#ffab70;' +
    '--callout-note:#2563eb;--callout-tip:#16a34a;--callout-important:#7c3aed;--callout-warning:#d97706;--callout-caution:#dc2626;' +
    '--md-scroll-thumb:#c3ccd9;--md-scroll-hover:#d97706;' +
    '}' +
    '@media (prefers-color-scheme: dark){:root{' +
    '--md-bg:#15181e;--md-fg:#dde3ea;--md-muted:#8b95a5;--md-border:#2a2f3a;' +
    '--md-accent:#60a5fa;--md-quote-bg:#1b2433;--md-quote-border:#3b82f6;' +
    '--md-code-bg:#0b0e14;--md-code-fg:#dde3ea;--md-inline-code-bg:#232833;--md-inline-code-fg:#5eead4;' +
    '--md-mark-bg:#4a3d0a;--md-mark-fg:#fde68a;--md-table-stripe:#1b1f28;' +
    '--md-scroll-thumb:#3d4a5c;--md-scroll-hover:#f5a524;' +
    '}}' +
    '*,*::before,*::after{box-sizing:border-box}' +
    'html{overflow-x:hidden}' +
    'body{margin:0;background:var(--md-bg);color:var(--md-fg);overflow-x:hidden}' +
    '*{scrollbar-width:none;-ms-overflow-style:none}' +
    '::-webkit-scrollbar{width:0;height:0;display:none}' +
    '::-webkit-scrollbar-track{background:transparent}' +
    '::-webkit-scrollbar-thumb{background:transparent}' +
    '::-webkit-scrollbar-corner{background:transparent}' +
    'article.md .mermaid-block pre.mermaid svg{display:block;margin:0 auto;}' +
    'article.md{font-family:-apple-system,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;' +
    'max-width:800px;margin:0 auto;padding:2.5rem 2rem 4rem;line-height:1.7;font-size:16px;' +
    'overflow-wrap:break-word;word-break:break-word;}' +
    'article.md h1,article.md h2,article.md h3,article.md h4,article.md h5,article.md h6{' +
    'margin:1.7em 0 .6em;line-height:1.3;font-weight:700;position:relative;scroll-margin-top:1.5rem;}' +
    'article.md h1:first-child,article.md h2:first-child{margin-top:0;}' +
    'article.md h1{font-size:1.85rem;border-bottom:1px solid var(--md-border);padding-bottom:.5rem;}' +
    'article.md h2{font-size:1.4rem;border-bottom:1px solid var(--md-border);padding-bottom:.4rem;}' +
    'article.md h3{font-size:1.15rem;}article.md h4{font-size:1.02rem;}' +
    'article.md h5,article.md h6{font-size:.92rem;color:var(--md-muted);text-transform:uppercase;letter-spacing:.04em;}' +
    'article.md .heading-anchor{position:absolute;left:-1.15em;opacity:0;text-decoration:none;' +
    'color:var(--md-muted);font-weight:400;padding-right:.3em;transition:opacity .12s;}' +
    'article.md h1:hover .heading-anchor,article.md h2:hover .heading-anchor,article.md h3:hover .heading-anchor,' +
    'article.md h4:hover .heading-anchor,article.md h5:hover .heading-anchor,article.md h6:hover .heading-anchor{opacity:1;}' +
    'article.md p{margin:.95em 0;}' +
    'article.md a{color:var(--md-accent);text-decoration:none;}article.md a:hover{text-decoration:underline;}' +
    'article.md a.wiki-link{border-bottom:1px dashed var(--md-accent);}' +
    'article.md strong{font-weight:700;}article.md em{font-style:italic;}' +
    'article.md mark{background:var(--md-mark-bg);color:var(--md-mark-fg);border-radius:3px;padding:.05em .25em;}' +
    'article.md sup{font-size:.75em;}' +
    'article.md abbr{cursor:help;text-decoration:underline dotted;text-underline-offset:2px;}' +
    'article.md kbd{font-family:"SF Mono",ui-monospace,Menlo,Consolas,monospace;font-size:.8em;' +
    'background:var(--md-inline-code-bg);border:1px solid var(--md-border);border-bottom-width:2px;' +
    'border-radius:5px;padding:.1em .5em;box-shadow:0 1px 0 var(--md-border);}' +
    'article.md code{font-family:"SF Mono",ui-monospace,Menlo,Consolas,monospace;font-size:.85em;' +
    'background:var(--md-inline-code-bg);padding:.15em .4em;border-radius:5px;color:var(--md-inline-code-fg);}' +
    'article.md .code-block{position:relative;margin:1.2em 0;border-radius:10px;overflow:hidden;background:var(--md-code-bg);}' +
    'article.md .code-block pre{margin:0;padding:1.1rem 1.3rem;overflow-x:auto;max-width:100%;}' +
    'article.md .code-block[data-lang]{padding-top:0;}' +
    'article.md .code-block[data-lang]::before{content:attr(data-lang);display:block;padding:.5rem 1.3rem .1rem;' +
    'font-size:.65rem;letter-spacing:.1em;text-transform:uppercase;color:var(--md-muted);}' +
    'article.md .code-block code{background:none;color:var(--md-code-fg);padding:0;font-size:.85rem;' +
    'font-family:"SF Mono",ui-monospace,Menlo,Consolas,monospace;line-height:1.6;}' +
    'article.md .code-block .tok-comment{color:var(--tok-comment);font-style:italic;}' +
    'article.md .code-block .tok-string{color:var(--tok-string);}' +
    'article.md .code-block .tok-number{color:var(--tok-number);}' +
    'article.md .code-block .tok-keyword{color:var(--tok-keyword);font-weight:600;}' +
    'article.md .code-block .tok-tag{color:var(--tok-tag);}' +
    'article.md .code-block .tok-attr{color:var(--tok-attr);}' +
    'article.md .code-copy{position:absolute;top:.6rem;right:.6rem;display:flex;align-items:center;justify-content:center;' +
    'width:28px;height:28px;border:1px solid rgba(255,255,255,.14);border-radius:6px;background:rgba(255,255,255,.06);' +
    'color:#c7cedb;cursor:pointer;opacity:0;transition:opacity .12s,background .12s;z-index:2;}' +
    'article.md .code-block:hover .code-copy{opacity:1;}' +
    'article.md .code-copy:hover{background:rgba(255,255,255,.14);}' +
    'article.md .code-copy .icon-check{display:none;}' +
    'article.md .code-copy.copied .icon-copy{display:none;}article.md .code-copy.copied .icon-check{display:flex;color:#4ade80;}' +
    'article.md .mermaid-block{background:var(--md-bg);border:1px solid var(--md-border);}' +
    'article.md .mermaid-block pre.mermaid{background:transparent;padding:1.3rem;text-align:center;overflow-x:auto;}' +
    'article.md .math-block{margin:1.2em 0;overflow-x:auto;}' +
    'article.md blockquote{border-left:3px solid var(--md-quote-border);background:var(--md-quote-bg);' +
    'padding:.8rem 1.2rem;margin:1.1em 0;border-radius:0 8px 8px 0;color:var(--md-fg);}' +
    'article.md blockquote p{margin:.4em 0;}' +
    'article.md blockquote.callout{border-left-width:4px;}' +
    'article.md .callout-title{font-weight:700;margin-bottom:.3em;}' +
    'article.md .callout-note{border-left-color:var(--callout-note);}article.md .callout-note .callout-title{color:var(--callout-note);}' +
    'article.md .callout-tip{border-left-color:var(--callout-tip);}article.md .callout-tip .callout-title{color:var(--callout-tip);}' +
    'article.md .callout-important{border-left-color:var(--callout-important);}article.md .callout-important .callout-title{color:var(--callout-important);}' +
    'article.md .callout-warning{border-left-color:var(--callout-warning);}article.md .callout-warning .callout-title{color:var(--callout-warning);}' +
    'article.md .callout-caution{border-left-color:var(--callout-caution);}article.md .callout-caution .callout-title{color:var(--callout-caution);}' +
    'article.md ul,article.md ol{margin:.9em 0;padding-left:1.6rem;}article.md li{margin:.35em 0;}' +
    'article.md li.task{list-style:none;margin-left:-1.4rem;}' +
    'article.md li.task label{display:flex;align-items:flex-start;gap:.55rem;cursor:default;}' +
    'article.md li.task input{margin-top:.35rem;}' +
    'article.md dl{margin:1em 0;}article.md dt{font-weight:700;}' +
    'article.md dd{margin:.15em 0 .8em 1.2rem;color:var(--md-fg);}' +
    'article.md hr{border:none;height:1px;background:linear-gradient(90deg,transparent,var(--md-border),transparent);margin:2em 0;}' +
    'article.md img{max-width:100%;height:auto;border-radius:8px;}' +
    'article.md details{border:1px solid var(--md-border);border-radius:8px;padding:.7rem 1rem;margin:1.1em 0;}' +
    'article.md summary{cursor:pointer;font-weight:600;}' +
    'article.md details[open] summary{margin-bottom:.6em;}' +
    'article.md .table-wrap{overflow-x:auto;margin:1.2em 0;max-width:100%;}' +
    'article.md table{width:100%;border-collapse:collapse;font-size:.9rem;}' +
    'article.md th,article.md td{border:1px solid var(--md-border);padding:.55rem .85rem;text-align:left;}' +
    'article.md th{background:var(--md-table-stripe);font-weight:700;}' +
    'article.md tbody tr:nth-child(even){background:var(--md-table-stripe);}' +
    'article.md del{color:var(--md-muted);}' +
    'article.md .footnotes-sep{margin-top:2.5em;}' +
    'article.md ol.footnotes{font-size:.88rem;color:var(--md-muted);padding-left:1.4rem;}' +
    'article.md .footnote-ref a{padding:0 .1em;}' +
    'article.md .footnote-back{margin-left:.3em;text-decoration:none;}' +
    'article.md .frontmatter{border:1px solid var(--md-border);border-radius:10px;padding:1.1rem 1.4rem;margin-bottom:1.8em;background:var(--md-table-stripe);}' +
    'article.md .fm-title{font-size:1.05rem;font-weight:700;margin-bottom:.6em;}' +
    'article.md .fm-grid{display:flex;flex-direction:column;align-items:stretch;gap:.4em;font-size:.85rem;}' +
    'article.md .fm-row{display:flex;gap:.4em;align-items:baseline;}' +
    'article.md .fm-key{color:var(--md-muted);text-transform:uppercase;font-size:.72rem;letter-spacing:.04em;}' +
    'article.md .fm-tags{display:flex;flex-wrap:wrap;gap:.3em;}' +
    'article.md .fm-tag{background:var(--md-inline-code-bg);color:var(--md-inline-code-fg);border-radius:999px;padding:.1em .7em;font-size:.78rem;}' +
    'article.md .code-block .tok-diff-add{display:inline-block;width:100%;background:rgba(46,160,67,.15);color:#7ee787;}' +
    'article.md .code-block .tok-diff-del{display:inline-block;width:100%;background:rgba(248,81,73,.15);color:#ff7b72;}' +
    'article.md .code-block .tok-diff-hunk{display:inline-block;width:100%;color:var(--tok-keyword);}' +
    'article.md .code-block .tok-diff-meta{display:inline-block;width:100%;color:var(--md-muted);}' +
    'article.md .code-block.line-numbers .code-line{counter-increment:md-line;position:relative;display:block;padding-left:3.2em;}' +
    'article.md .code-block.line-numbers pre{counter-reset:md-line;}' +
    'article.md .code-block.line-numbers .code-line::before{content:counter(md-line);position:absolute;left:0;width:2.4em;text-align:right;color:var(--md-muted);opacity:.6;user-select:none;}' +
    'article.md .code-block .code-line-hl{display:block;background:rgba(96,165,250,.12);box-shadow:inset 3px 0 0 var(--md-accent);}' +
    'article.md .code-block.code-collapsible pre{max-height:22em;overflow:hidden;position:relative;}' +
    'article.md .code-block.code-collapsible.expanded pre{max-height:none;}' +
    'article.md .code-block.code-collapsible:not(.expanded) pre::after{content:"";position:absolute;left:0;right:0;bottom:0;height:3.5em;background:linear-gradient(to bottom,transparent,var(--md-code-bg));}' +
    'article.md .code-expand{display:block;width:100%;padding:.5rem;border:none;border-top:1px solid rgba(255,255,255,.08);' +
    'background:rgba(255,255,255,.04);color:#c7cedb;font-size:.78rem;cursor:pointer;}' +
    'article.md .code-expand:hover{background:rgba(255,255,255,.1);}' +
    'article.md a[target="_blank"]::after{content:"↗";display:inline-block;font-size:.72em;margin-left:.15em;opacity:.6;text-decoration:none;transform:translateY(-1px);}' +
    'article.md figure{margin:1.2em 0;text-align:center;}' +
    'article.md figure img{max-width:100%;border-radius:8px;}' +
    'article.md figcaption{margin-top:.5em;font-size:.85rem;color:var(--md-muted);}' +
    'article.md sub{font-size:.75em;}' +
    'article.md .toc{border:1px solid var(--md-border);border-radius:10px;padding:.9rem 1.3rem;margin:1.2em 0;background:var(--md-table-stripe);}' +
    'article.md .toc-title{font-weight:700;margin-bottom:.4em;font-size:.9rem;text-transform:uppercase;letter-spacing:.04em;color:var(--md-muted);}' +
    'article.md .toc ul{margin:.2em 0;padding-left:1.2rem;list-style:none;}' +
    'article.md .toc > ul{padding-left:0;}' +
    'article.md .toc li{margin:.25em 0;}' +
    'article.md .toc a{color:var(--md-fg);}article.md .toc a:hover{color:var(--md-accent);}' +
    '.md-back-to-top{position:fixed;right:1.5rem;bottom:1.5rem;width:40px;height:40px;border-radius:50%;' +
    'background:var(--md-accent);color:#fff;border:none;cursor:pointer;font-size:1.1rem;line-height:1;' +
    'box-shadow:0 2px 10px rgba(0,0,0,.25);opacity:0;pointer-events:none;transition:opacity .15s,transform .15s;z-index:50;}' +
    '.md-back-to-top.visible{opacity:.9;pointer-events:auto;}' +
    '.md-back-to-top:hover{opacity:1;transform:translateY(-2px);}' +
    '.md-reading-time{color:var(--md-muted);font-size:.82rem;margin:-1em 0 1.6em;}' +
    '.md-copied-toast{position:fixed;left:50%;bottom:1.5rem;transform:translateX(-50%) translateY(10px);' +
    'background:var(--md-fg);color:var(--md-bg);padding:.5em 1em;border-radius:8px;font-size:.82rem;' +
    'opacity:0;pointer-events:none;transition:opacity .15s,transform .15s;z-index:60;}' +
    '.md-copied-toast.visible{opacity:.95;transform:translateX(-50%) translateY(0);}';
  window.IDE = window.IDE || {};
  /**
   * @namespace window.IDE.markdown
   * Shared Markdown → HTML renderer (used by preview pane + live tab).
   */
  window.IDE.markdown = {
    /**
     * Convert raw Markdown source to HTML.
     * Handles front matter, callouts, tables, task lists, footnotes,
     * wiki-links, abbreviations, code highlighting, math, and more.
     * @param {string} src - The raw Markdown content
     * @returns {string} Rendered HTML (no full document wrapper)
     */
    toHtml: mdToHtml,

    /**
     * Wrap rendered Markdown body HTML into a full standalone document.
     * Includes KaTeX CSS, custom styles, and bootstrap scripts.
     * @param {string} bodyHtml - The rendered Markdown body HTML
     * @param {string} [title] - The page title (usually the file name)
     * @param {string} [theme='dark'] - The IDE theme for matching styles
     * @returns {string} Complete HTML document string
     */
    wrap: wrapMarkdownHtml,

    /**
     * Copy code from a code block's copy button (invoked via onclick).
     * @param {HTMLElement} btn - The copy button element
     * @returns {void}
     */
    copyCode: copyCode,

    /**
     * Toggle a collapsed/expanded long code block (invoked via onclick).
     * @param {HTMLElement} btn - The "Show more"/"Show less" button
     * @returns {void}
     */
    toggleCode: toggleCode,

    /**
     * Extract a document title from Markdown content.
     * Priority: 1) YAML front matter title, 2) first # heading, 3) filename.
     * @param {string} content - The Markdown content to inspect
     * @param {string} [fallbackName='Preview'] - Fallback title if none found
     * @returns {string} The extracted title
     */
    deriveTitle: deriveTitle
  };
})();
