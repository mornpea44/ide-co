/**
 * ═══════════════════════════════════════════════════════════════════════════
 *  QUIRKY IDE — CODE MINIFIERS (extracted from editor.js · Phase 3)
 *  Mobile cleanup (Phase 3.10): desktop-only shrinkActive() removed.
 * ═══════════════════════════════════════════════════════════════════════════
 *
 *  Safe, literal-aware client-side minifiers for CSS, HTML, and JS.
 *  Strings, comments, and regex literals are protected so the code can
 *  never be corrupted. PHP minification stays server-side (token_get_all).
 *
 *  MOBILE NOTE: the mobile editor (mobile-editor.js → mobileShrink())
 *  calls the three pure functions below directly. The old desktop
 *  "Shrink button" handler (shrinkActive) and its desktop-editor helpers
 *  (getCM / getActiveTab) were removed in Phase 3.10 — the desktop shell
 *  no longer exists on this platform, and mobile has its own handler.
 *
 *  EXPOSES: window.IDE.editorMinify = { minifyCSS, minifyHTML, minifyJS }
 * ═══════════════════════════════════════════════════════════════════════════ */
(function () {
  'use strict';

  /* ── CSS minifier: protects strings & comments; drops whitespace around
  structural chars; collapses the rest to a single space. ────────── */
  function minifyCSS(s) {
    let out = '', i = 0, n = s.length, inStr = null;
    const structural = '{}:;,()>';
    while (i < n) {
      const c = s[i];
      if (!inStr && c === '/' && s[i + 1] === '*') {
        const e = s.indexOf('*/', i + 2);
        i = (e < 0) ? n : e + 2;
        continue;
      }
      if (!inStr && (c === '"' || c === "'")) {
        inStr = c; out += c; i++;
        while (i < n) {
          out += s[i];
          if (s[i] === '\\' && i + 1 < n) { out += s[i + 1]; i += 2; continue; }
          if (s[i] === inStr) { inStr = null; i++; break; }
          i++;
        }
        continue;
      }
      if (inStr) { out += c; i++; continue; }
      if (/\s/.test(c)) {
        let j = i; while (j < n && /\s/.test(s[j])) j++;
        const prev = out[out.length - 1] || '';
        const next = s[j] || '';
        if (!(structural.indexOf(prev) >= 0 || structural.indexOf(next) >= 0 || prev === '' || next === '')) out += ' ';
        i = j; continue;
      }
      out += c; i++;
    }
    return out.trim();
  }

  /* ── HTML minifier: drops comments; keeps <script>/<style>/<pre>/<textarea>
  verbatim; collapses other whitespace to a single space. ─────────── */
  function minifyHTML(s) {
    const re = /(<!--[\s\S]*?-->|<script[\s\S]*?<\/script>|<style[\s\S]*?<\/style>|<pre[\s\S]*?<\/pre>|<textarea[\s\S]*?<\/textarea>)/gi;
    let out = '', last = 0, m;
    while ((m = re.exec(s))) {
      out += s.slice(last, m.index).replace(/\s+/g, ' ');
      if (m[0].slice(0, 4) !== '<!--') out += m[0];
      last = re.lastIndex;
    }
    out += s.slice(last).replace(/\s+/g, ' ');
    return out.replace(/^ +| +$/g, '');
  }

  /* ── JS minifier (CONSERVATIVE & SAFE):
  • protects string / template / regex literals (copied verbatim);
  • drops line comments (keeps their terminating newline) and block
  comments (replaced by a newline if they contained one, else a space);
  • collapses every whitespace run to ONE space (no newline) or ONE
  newline (if it contained a newline) — it NEVER joins two lines and
  NEVER deletes a space between tokens, so operator/ASI behaviour is
  unchanged. Fully reversible with Undo. ───────────────────────── */
  function minifyJS(s) {
    const KEY = ['return', 'typeof', 'delete', 'throw', 'new', 'in', 'instanceof', 'void', 'case', 'do', 'else'];
    const SET = '([{,;:=!&|?+-*%<>^~';
    let out = '', i = 0, n = s.length, lastSig = '', lastWord = '';
    function regexAllowed() {
      if (lastSig === '' || SET.indexOf(lastSig) >= 0) return true;
      return KEY.indexOf(lastWord) >= 0;
    }
    while (i < n) {
      const c = s[i], c2 = s[i + 1];
      if (c === '/' && c2 === '/') {
        let j = i; while (j < n && s[j] !== '\n') j++;
        i = j; continue;
      }
      if (c === '/' && c2 === '*') {
        const e = s.indexOf('*/', i + 2);
        if (e < 0) { i = n; }
        else { out += s.slice(i, e + 2).indexOf('\n') >= 0 ? '\n' : ' '; i = e + 2; }
        continue;
      }
      if (c === '"' || c === "'") {
        const q = c; let j = i + 1;
        while (j < n) { if (s[j] === '\\' && j + 1 < n) { j += 2; continue; } if (s[j] === q) { j++; break; } j++; }
        out += s.slice(i, j); i = j; lastSig = q; lastWord = ''; continue;
      }
      if (c === '`') {
        let j = i + 1;
        while (j < n) {
          if (s[j] === '\\' && j + 1 < n) { j += 2; continue; }
          if (s[j] === '`') { j++; break; }
          if (s[j] === '$' && s[j + 1] === '{') {
            j += 2; let d = 1;
            while (j < n && d > 0) {
              const ch = s[j];
              if (ch === '\\' && j + 1 < n) { j += 2; continue; }
              if (ch === '"' || ch === "'") { const qq = ch; j++; while (j < n) { if (s[j] === '\\' && j + 1 < n) { j += 2; continue; } if (s[j] === qq) { j++; break; } j++; } continue; }
              if (ch === '`') { j++; while (j < n) { if (s[j] === '\\' && j + 1 < n) { j += 2; continue; } if (s[j] === '`') { j++; break; } j++; } continue; }
              if (ch === '{') d++; else if (ch === '}') d--;
              j++;
            }
            continue;
          }
          j++;
        }
        out += s.slice(i, j); i = j; lastSig = '`'; lastWord = ''; continue;
      }
      if (c === '/' && regexAllowed()) {
        let j = i + 1, inClass = false;
        while (j < n) {
          const ch = s[j];
          if (ch === '\\' && j + 1 < n) { j += 2; continue; }
          if (ch === '[') inClass = true;
          else if (ch === ']') inClass = false;
          else if (ch === '/' && !inClass) { j++; break; }
          else if (ch === '\n') break;
          j++;
        }
        while (j < n && /[a-zA-Z]/.test(s[j])) j++;
        out += s.slice(i, j); i = j; lastSig = '/'; lastWord = ''; continue;
      }
      if (/[A-Za-z_$]/.test(c)) {
        let j = i; while (j < n && /[A-Za-z0-9_$]/.test(s[j])) j++;
        const w = s.slice(i, j);
        out += w; i = j; lastWord = w; lastSig = w.charAt(w.length - 1); continue;
      }
      if (/[0-9]/.test(c)) {
        let j = i; while (j < n && /[0-9.]/.test(s[j])) j++;
        out += s.slice(i, j); i = j; lastSig = s[j - 1]; lastWord = ''; continue;
      }
      if (/\s/.test(c)) {
        let j = i, hasNL = false;
        while (j < n && /\s/.test(s[j])) { if (s[j] === '\n') hasNL = true; j++; }
        out += hasNL ? '\n' : ' ';
        i = j; continue;
      }
      out += c; i++; lastSig = c; lastWord = '';
    }
    return out.replace(/^ +| +$/g, '');
  }

  /* ═══════════════════════════════════════════════════════════════════════
  EXPOSE TO GLOBAL NAMESPACE
  ═══════════════════════════════════════════════════════════════════════
  Phase 3.10: only the three PURE minifiers are exposed. The desktop-only
  shrinkActive() handler and its getCM()/getActiveTab() helpers were
  removed — mobile uses mobileShrink() in mobile-editor.js instead.   */
  window.IDE = window.IDE || {};
  /**
   * @namespace window.IDE.editorMinify
   * Safe client-side minifiers (pure functions — no editor dependency).
   */
  window.IDE.editorMinify = {
    /**
     * Minify CSS by removing comments and collapsing whitespace.
     * Protects string literals and structural characters.
     * @param {string} s - The CSS source code
     * @returns {string} The minified CSS
     */
    minifyCSS: minifyCSS,
    /**
     * Minify HTML by removing comments and collapsing whitespace.
     * Preserves <script>, <style>, <pre>, and <textarea> contents verbatim.
     * @param {string} s - The HTML source code
     * @returns {string} The minified HTML
     */
    minifyHTML: minifyHTML,
    /**
     * Conservative JS minifier that protects strings, template literals,
     * and regex literals. Collapses whitespace but never joins lines.
     * @param {string} s - The JavaScript source code
     * @returns {string} The minified JavaScript
     */
    minifyJS: minifyJS
  };
})();