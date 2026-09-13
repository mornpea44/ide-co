/**
* ═══════════════════════════════════════════════════════════════════════════
*  QUIRKY IDE — LANGUAGE REGISTRY (One Source of Truth)
*  v2 — extended coverage so compiled languages (Java, C, C++, Go, Rust,
*  Kotlin, Swift, Dart, Lua, Ruby, Perl) get syntax modes, snippet pools,
*  symbols and lint hooks just like the web languages.
*
*  EXPOSES: window.IDE.language
* ═══════════════════════════════════════════════════════════════════════════ */
(function () {
  'use strict';
  var TABLE = {
    /* ── PHP ── */
    php: { id: 'php', label: 'PHP', cm: 'application/x-httpd-php', monaco: 'php', snippet: 'php', symbols: 'php', lint: 'php', monoLint: false },
    phtml: { id: 'php', label: 'PHP', cm: 'application/x-httpd-php', monaco: 'php', snippet: 'php', symbols: 'php', lint: 'php', monoLint: false },
    /* ── Markup ── */
    html: { id: 'html', label: 'HTML', cm: 'htmlmixed', monaco: 'html', snippet: 'html', symbols: null, lint: null, monoLint: true },
    htm: { id: 'html', label: 'HTML', cm: 'htmlmixed', monaco: 'html', snippet: 'html', symbols: null, lint: null, monoLint: true },
    vue: { id: 'html', label: 'Vue', cm: 'htmlmixed', monaco: 'html', snippet: 'html', symbols: null, lint: null, monoLint: true },
    xml: { id: 'xml', label: 'XML', cm: 'xml', monaco: 'xml', snippet: null, symbols: null, lint: null, monoLint: false },
    svg: { id: 'xml', label: 'SVG', cm: 'xml', monaco: 'xml', snippet: null, symbols: null, lint: null, monoLint: false },
    /* ── JavaScript family ── */
    js: { id: 'javascript', label: 'JavaScript', cm: { name: 'javascript' }, monaco: 'javascript', snippet: 'javascript', symbols: 'javascript', lint: 'javascript', monoLint: false },
    mjs: { id: 'javascript', label: 'JavaScript', cm: { name: 'javascript' }, monaco: 'javascript', snippet: 'javascript', symbols: 'javascript', lint: 'javascript', monoLint: false },
    jsx: { id: 'javascript', label: 'JSX', cm: { name: 'javascript' }, monaco: 'javascript', snippet: 'javascript', symbols: 'javascript', lint: 'javascript', monoLint: false },
    ts: { id: 'typescript', label: 'TypeScript', cm: { name: 'javascript', typescript: true }, monaco: 'typescript', snippet: 'javascript', symbols: 'typescript', lint: 'javascript', monoLint: false },
    json: { id: 'json', label: 'JSON', cm: { name: 'javascript', json: true }, monaco: 'json', snippet: 'javascript', symbols: null, lint: 'json', monoLint: true },
    /* ── Stylesheets ── */
    css: { id: 'css', label: 'CSS', cm: 'css', monaco: 'css', snippet: 'css', symbols: 'css', lint: null, monoLint: true },
    scss: { id: 'css', label: 'SCSS', cm: 'css', monaco: 'css', snippet: 'css', symbols: 'scss', lint: null, monoLint: true },
    sass: { id: 'css', label: 'Sass', cm: 'css', monaco: 'css', snippet: 'css', symbols: null, lint: null, monoLint: true },
    less: { id: 'css', label: 'Less', cm: 'css', monaco: 'css', snippet: 'css', symbols: 'less', lint: null, monoLint: true },
    /* ── Docs ── */
    md: { id: 'markdown', label: 'Markdown', cm: 'markdown', monaco: 'markdown', snippet: 'markdown', symbols: null, lint: null, monoLint: false },
    markdown: { id: 'markdown', label: 'Markdown', cm: 'markdown', monaco: 'markdown', snippet: 'markdown', symbols: null, lint: null, monoLint: false },
    /* ── Data / config ── */
    sql: { id: 'sql', label: 'SQL', cm: 'sql', monaco: 'sql', snippet: 'sql', symbols: null, lint: null, monoLint: false },
    yml: { id: 'yaml', label: 'YAML', cm: 'yaml', monaco: 'yaml', snippet: null, symbols: null, lint: null, monoLint: false },
    yaml: { id: 'yaml', label: 'YAML', cm: 'yaml', monaco: 'yaml', snippet: null, symbols: null, lint: null, monoLint: false },
    ini: { id: 'ini', label: 'INI', cm: 'properties', monaco: 'ini', snippet: null, symbols: null, lint: null, monoLint: false },
    conf: { id: 'ini', label: 'Config', cm: 'properties', monaco: 'ini', snippet: null, symbols: null, lint: null, monoLint: false },
    env: { id: 'ini', label: 'ENV', cm: 'properties', monaco: 'ini', snippet: null, symbols: null, lint: null, monoLint: false },
    properties: { id: 'ini', label: 'Properties', cm: 'properties', monaco: 'ini', snippet: null, symbols: null, lint: null, monoLint: false },
    gitignore: { id: 'ini', label: 'gitignore', cm: 'properties', monaco: 'ini', snippet: null, symbols: null, lint: null, monoLint: false },
    htaccess: { id: 'ini', label: '.htaccess', cm: 'properties', monaco: 'ini', snippet: null, symbols: null, lint: null, monoLint: false },
    editorconfig: { id: 'ini', label: 'editorconfig', cm: 'properties', monaco: 'ini', snippet: null, symbols: null, lint: null, monoLint: false },
    toml: { id: 'ini', label: 'TOML', cm: 'properties', monaco: 'ini', snippet: null, symbols: null, lint: null, monoLint: false },
    csv: { id: 'text', label: 'CSV', cm: null, monaco: 'plaintext', snippet: null, symbols: null, lint: null, monoLint: false },
    txt: { id: 'text', label: 'Plain Text', cm: null, monaco: 'plaintext', snippet: null, symbols: null, lint: null, monoLint: false },
    log: { id: 'text', label: 'Log', cm: null, monaco: 'plaintext', snippet: null, symbols: null, lint: null, monoLint: false },
    /* ── Shell ── */
    sh: { id: 'shell', label: 'Shell', cm: 'shell', monaco: 'shell', snippet: 'shell', symbols: null, lint: null, monoLint: false },
    bash: { id: 'shell', label: 'Bash', cm: 'shell', monaco: 'shell', snippet: 'shell', symbols: null, lint: null, monoLint: false },
    /* ── Python ── */
    py: { id: 'python', label: 'Python', cm: 'python', monaco: 'python', snippet: 'python', symbols: 'python', lint: 'python', monoLint: false },
    pyw: { id: 'python', label: 'Python', cm: 'python', monaco: 'python', snippet: 'python', symbols: 'python', lint: 'python', monoLint: false },
    pyi: { id: 'python', label: 'Python', cm: 'python', monaco: 'python', snippet: 'python', symbols: 'python', lint: 'python', monoLint: false },
    /* ── C family (CodeMirror 'clike'; full snippet + symbol coverage) ── */
    c: { id: 'c', label: 'C', cm: 'clike', monaco: 'c', snippet: 'c', symbols: 'c', lint: null, monoLint: false },
    cpp: { id: 'cpp', label: 'C++', cm: 'clike', monaco: 'cpp', snippet: 'cpp', symbols: 'cpp', lint: null, monoLint: false },
    cc: { id: 'cpp', label: 'C++', cm: 'clike', monaco: 'cpp', snippet: 'cpp', symbols: 'cpp', lint: null, monoLint: false },
    cxx: { id: 'cpp', label: 'C++', cm: 'clike', monaco: 'cpp', snippet: 'cpp', symbols: 'cpp', lint: null, monoLint: false },
    h: { id: 'c', label: 'C header', cm: 'clike', monaco: 'c', snippet: 'c', symbols: 'c', lint: null, monoLint: false },
    hpp: { id: 'cpp', label: 'C++ header', cm: 'clike', monaco: 'cpp', snippet: 'cpp', symbols: 'cpp', lint: null, monoLint: false },
    java: { id: 'java', label: 'Java', cm: 'clike', monaco: 'java', snippet: 'java', symbols: 'java', lint: null, monoLint: false },
    cs: { id: 'csharp', label: 'C#', cm: 'clike', monaco: 'csharp', snippet: 'java', symbols: 'csharp', lint: null, monoLint: false },
    /* ── Systems / scripting languages with real vendored CM5 modes ── */
    go: { id: 'go', label: 'Go', cm: 'go', monaco: 'go', snippet: 'go', symbols: 'go', lint: null, monoLint: false },
    rs: { id: 'rust', label: 'Rust', cm: 'rust', monaco: 'rust', snippet: 'rust', symbols: 'rust', lint: null, monoLint: false },
    kt: { id: 'kotlin', label: 'Kotlin', cm: 'text/x-kotlin', monaco: 'kotlin', snippet: 'java', symbols: 'kotlin', lint: null, monoLint: false },
    kts: { id: 'kotlin', label: 'Kotlin script', cm: 'text/x-kotlin', monaco: 'kotlin', snippet: 'java', symbols: 'kotlin', lint: null, monoLint: false },
    swift: { id: 'swift', label: 'Swift', cm: 'swift', monaco: 'swift', snippet: 'swift', symbols: 'swift', lint: null, monoLint: false },
    dart: { id: 'dart', label: 'Dart', cm: 'dart', monaco: 'dart', snippet: 'dart', symbols: 'dart', lint: null, monoLint: false },
    lua: { id: 'lua', label: 'Lua', cm: 'lua', monaco: 'lua', snippet: 'lua', symbols: 'lua', lint: null, monoLint: false },
    rb: { id: 'ruby', label: 'Ruby', cm: 'ruby', monaco: 'ruby', snippet: 'ruby', symbols: 'ruby', lint: null, monoLint: false },
    rake: { id: 'ruby', label: 'Ruby', cm: 'ruby', monaco: 'ruby', snippet: 'ruby', symbols: 'ruby', lint: null, monoLint: false },
    pl: { id: 'perl', label: 'Perl', cm: 'perl', monaco: 'perl', snippet: 'perl', symbols: 'perl', lint: null, monoLint: false },
    pm: { id: 'perl', label: 'Perl', cm: 'perl', monaco: 'perl', snippet: 'perl', symbols: 'perl', lint: null, monoLint: false }
  };
  var PLAINTEXT = { id: 'text', label: 'Plain Text', cm: null, monaco: 'plaintext', snippet: null, symbols: null, lint: null, monoLint: false };
  function normalise(ext) { return String(ext == null ? '' : ext).toLowerCase(); }
  function extOf(path) {
    var name = String(path == null ? '' : path).split('/').pop() || '';
    var dot = name.lastIndexOf('.');
    return dot >= 0 ? name.slice(dot + 1).toLowerCase() : '';
  }
  function byExt(ext) { return TABLE[normalise(ext)] || PLAINTEXT; }
  function modeForExt(ext) {
    var e = normalise(ext);
    if (!TABLE[e] && e) return { mode: null, label: e.toUpperCase() };
    var d = byExt(ext);
    return { mode: d.cm, label: d.label };
  }
  function lintSource(ext) { return byExt(ext).lint; }
  function monoLint(ext) { return byExt(ext).monoLint; }
  function symbolLang(ext) { return byExt(ext).symbols; }
  function snippetKey(ext) { return byExt(ext).snippet; }
  function monacoLang(ext) { return byExt(ext).monaco; }
  window.IDE = window.IDE || {};
  window.IDE.language = {
    TABLE: TABLE,
    extOf: extOf,
    byExt: byExt,
    modeForExt: modeForExt,
    lintSource: lintSource,
    monoLint: monoLint,
    symbolLang: symbolLang,
    snippetKey: snippetKey,
    monacoLang: monacoLang
  };
})();