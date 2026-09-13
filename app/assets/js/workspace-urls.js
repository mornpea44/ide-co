/**
* ═══════════════════════════════════════════════════════════════════════════
*  QUIRKY IDE — WORKSPACE URL REWRITER (shared module · Phase 2B)
* ═══════════════════════════════════════════════════════════════════════════
*
*  The SINGLE client-side implementation of "make relative src/href point
*  at the workspace file server". Both preview surfaces load it:
*    • preview.js             — the in-IDE live pane
*    • views/live-preview.php — the standalone live tab
*  (The server-side equivalent for the raw pop out lives in
*   app/services/Preview.php — PHP can't share this file.)
*
*  EXPOSES: window.IDE.previewUrls = { resolveRel, rewriteWorkspaceUrls }
* ═══════════════════════════════════════════════════════════════════════════ */
(function () {
  'use strict';
  /** Resolve "./" and "../" segments against the file's directory. */
  function resolveRel(base, rel) {
    const parts = (base + rel).split('/');
    const out = [];
    for (let i = 0; i < parts.length; i++) {
      if (parts[i] === '.' || parts[i] === '') continue;
      if (parts[i] === '..') { out.pop(); continue; }
      out.push(parts[i]);
    }
    return out.join('/');
  }
  /**
  * Rewrite relative src/href attributes (double-quoted, single-quoted, or
  * unquoted) so assets resolve through the workspace file server. Absolute
  * paths and external/special URLs are left alone.
  *
  * Protects <script> and <style> blocks first: src=/href= that appear
  * inside JS/CSS code (e.g. `img.src = ...` or markup built in a string)
  * must NOT be rewritten — doing so corrupts the code and throws a
  * SyntaxError in the preview. Stash them, rewrite the rest, restore.
  */
  function rewriteWorkspaceUrls(html, filePath) {
    if (!filePath) return html;
    let dir = '';
    const slash = filePath.lastIndexOf('/');
    if (slash > 0) dir = filePath.substring(0, slash + 1);
    const prefix = 'index.php?workspace=';
    // ── Stash <script>/<style> blocks so their contents are never touched ──
    const stash = [];
    let safe = html.replace(/<(script|style)\b[\s\S]*?<\/\1\s*>/gi, function (block) {
      stash.push(block);
      return '\u0000' + (stash.length - 1) + '\u0000';
    });
    // ── Rewrite relative src/href in the remaining HTML ──
    safe = safe.replace(
      /((?:src|href)\s*=\s*)("(?:[^"]*)"|'(?:[^']*)'|[^\s>]+)/gi,
      function (m, pre, val) {
        let quote = '';
        let url = val;
        const first = val.charAt(0);
        if (first === '"' || first === "'") {
          quote = first;
          url = val.slice(1, -1);
        }
        if (url === '' || url.charAt(0) === '/') return m;
        if (/^(https?:\/\/|\/\/|data:|#|javascript:|mailto:|tel:|about:|index\.php)/i.test(url)) return m;
        const resolved = resolveRel(dir, url.replace(/^\.\//, ''));
        return pre + quote + prefix + encodeURIComponent(resolved).replace(/%2F/g, '/') + quote;
      }
    );
    // ── Restore the stashed blocks ──
    return safe.replace(/\u0000(\d+)\u0000/g, function (m, i) { return stash[+i]; });
  }
  window.IDE = window.IDE || {};
  window.IDE.previewUrls = {
    resolveRel: resolveRel,
    rewriteWorkspaceUrls: rewriteWorkspaceUrls
  };
})();