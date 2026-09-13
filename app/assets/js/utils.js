/**
 * ═══════════════════════════════════════════════════════════════════════════
 *  QUIRKY IDE — UTILITIES & HELPERS (File 13 of 20)
 * ═══════════════════════════════════════════════════════════════════════════
 * 
 *  The shared toolbox for the entire frontend. All functions are namespaced
 *  under `window.IDE` to prevent global scope pollution and keep the codebase
 *  clean and modular.
 * 
 *  Used by: api.js, filetree.js, editor.js, preview.js, terminal.js, app.js
 * 
 * ═══════════════════════════════════════════════════════════════════════════ */

// Ensure the IDE namespace exists
window.IDE = window.IDE || {};

(function () {
  'use strict';

  const utils = {};

  /* ═══════════════════════════════════════════════════════════════════════
     1. DOM HELPERS
     ═══════════════════════════════════════════════════════════════════════ */

  /**
  * Shorthand for document.getElementById
  * @param {string} id - The ID of the element to find
  * @returns {HTMLElement|null}
  */
  utils.$ = (id) => document.getElementById(id);

  /**
   * Shorthand for document.querySelectorAll (returns an Array)
   * @param {string} selector - CSS selector
   * @param {Document|HTMLElement} [context=document] - Context to search within
   * @returns {HTMLElement[]}
   */
  utils.$$ = (selector, context = document) => Array.from(context.querySelectorAll(selector));

  /* ═══════════════════════════════════════════════════════════════════════
     2. SECURITY & SANITIZATION
     ═══════════════════════════════════════════════════════════════════════ */

  /**
   * Escapes HTML special characters to prevent XSS attacks.
   * ALWAYS use this when inserting user-provided text (like file names) into the DOM.
   * @param {string} str - The raw string to escape
   * @returns {string} The safely escaped string
   */
  utils.escapeHtml = (str) => {
    if (typeof str !== 'string') return '';
    const map = {
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#039;'
    };
    return str.replace(/[&<>"']/g, (m) => map[m]);
  };

  /* ═══════════════════════════════════════════════════════════════════════
     3. FORMATTING
     ═══════════════════════════════════════════════════════════════════════ */

  /**
   * Converts a byte count into a human-readable file size.
   * @param {number} bytes - The size in bytes
   * @param {number} [decimals=1] - Number of decimal places
   * @returns {string} Formatted size (e.g., "1.5 KB", "2.3 MB")
   */
  utils.formatBytes = (bytes, decimals = 1) => {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const dm = decimals < 0 ? 0 : decimals;
    const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
  };

  /**
   * Returns a friendly file icon (emoji) based on the file extension.
   * @param {string} filename - The name of the file
   * @returns {string} The emoji icon
   */
  /* Build an <img> tag for one of the hand-drawn SVG icons in app/assets/icons/.
     ★ FIX: serve icons through the IDE's asset endpoint (index.php?asset=icons/…)
     — the same door the app icon and manifest already use. The raw folder path
     ("app/assets/icons/…") is unreachable when the app runs behind index.php
     (e.g. inside the Android APK), which caused the broken-image placeholders
     in the Explorer. */
  const svgIcon = (name) =>
    '<img class="fi-svg" src="index.php?asset=icons/icon_' + name + '.svg" alt="" draggable="false">';

  utils.getFileIcon = (filename, isBinary) => {
    if (!filename) return '📄';
    // ── 0. Compiled / binary artifacts get their own chip icon ──
    if (isBinary) return svgIcon('binary');

    // Normalise to the bare file name (callers sometimes pass a full path).
    const base = String(filename).split('/').pop();

    // ── 1. Special project files (checked FIRST so README.md isn't "md") ──
    if (/readme/i.test(base)) return svgIcon('readme');
    if (/changelog/i.test(base)) return svgIcon('changelog');
    if (/manifest/i.test(base)) return svgIcon('manifest');
    if (/^quirky\.setup$/i.test(base)) return svgIcon('qsetup');

    // ── 1b. Log files: "log", "error.log", "app.logs", anything *.log ──
    if (/^log$/i.test(base) || /\.(log|logs)$/i.test(base)) return svgIcon('log');

    // ── 2. Language-specific SVG icons ──
    const ext = base.includes('.') ? base.split('.').pop().toLowerCase() : '';
    // ── 2a. Compiled outputs (C objects, Java classes, Python bytecode,
    //        Windows executables, WASM…) → chip icon ──
    const compiledMap = {
      'o': 1, 'obj': 1, 'a': 1, 'lib': 1, 'so': 1, 'dylib': 1,
      'dll': 1, 'exe': 1, 'out': 1, 'bin': 1, 'elf': 1, 'wasm': 1,
      'class': 1, 'jar': 1, 'war': 1, 'ear': 1,
      'pyc': 1, 'pyo': 1, 'pyd': 1, 'dex': 1
    };
    if (compiledMap[ext]) return svgIcon('binary');
    const langIconMap = {
      'c': 'c',
      'cpp': 'cpp', 'cc': 'cpp', 'cxx': 'cpp', 'hpp': 'cpp', 'h': 'c',
      'java': 'java',
      'py': 'python', 'pyw': 'python',
      'js': 'js', 'mjs': 'js',
      'rs': 'rust',
      'go': 'go',
      'rb': 'ruby',
      'php': 'php', 'phtml': 'php',
      'lua': 'lua',
      'sh': 'shell', 'bash': 'shell',
      'pl': 'perl', 'pm': 'perl',
      'kt': 'kotlin', 'kts': 'kotlin',
      'dart': 'dart',
      'swift': 'swift'
    };
    if (langIconMap[ext]) return svgIcon(langIconMap[ext]);
    // Generic .setup files keep the original teal-slider icon.
    // (quirky.setup gets its own gear-and-bolt icon above.)
    if (ext === 'setup') return svgIcon('setup');

    // ── 3. Dotfiles ──
    if (base.startsWith('.')) {
      if (base === '.env') return '⚙️';
      if (base === '.gitignore') return '🚫';
      if (base === '.htaccess') return '🛡️';
      return '⚙️';
    }

    // ── 4. Emoji fallback for everything else ──
    const iconMap = {
      'html': '🌐', 'htm': '🌐', 'css': '🎨', 'scss': '🎨', 'sass': '🎨',
      'ts': '📘', 'jsx': '⚛️', 'vue': '💚',
      'cs': '🟣',
      'json': '📦', 'xml': '📰', 'yaml': '⚙️', 'yml': '⚙️', 'toml': '⚙️',
      'ini': '⚙️', 'conf': '⚙️', 'env': '⚙️', 'sql': '🗄️', 'csv': '📊',
      'md': '📝', 'markdown': '📝', 'txt': '📄', 'log': '📋', 'logs': '📋',
      'pdf': '📕',
      'png': '🖼️', 'jpg': '🖼️', 'jpeg': '🖼️', 'gif': '🖼️', 'svg': '🎨',
      'webp': '🖼️', 'mp4': '🎬', 'mp3': '🎵', 'wav': '🎵',
      'zip': '📦', 'tar': '📦', 'gz': '📦', 'rar': '📦'
    };
    return iconMap[ext] || '📄';
  };

  /* ═══════════════════════════════════════════════════════════════════════
     4. NOTIFICATIONS (TOAST)
     ═══════════════════════════════════════════════════════════════════════ */

  let toastTimer = null;

  /**
   * Displays a temporary toast notification at the bottom of the screen.
   * @param {string} message - The text to display
   * @param {string} [type='info'] - 'info', 'success', 'error', or 'warning'
   * @param {number} [duration=3000] - How long to show the toast in milliseconds
   */
  utils.toast = (message, type = 'info', duration = 3000) => {
    const toastEl = utils.$('toast');
    if (!toastEl) return;

    // Clear any existing timer to prevent overlapping fades
    clearTimeout(toastTimer);

    // Remove old type classes AND action-toast leftovers
    toastEl.classList.remove('toast-success', 'toast-error', 'toast-warning', 'toast-info', 'm-toast-has-action');

    // Clear any leftover action-button HTML from a previous action toast
    if (toastEl.querySelector('.m-toast-action')) {
      toastEl.innerHTML = '';
    }

    // Add new type class
    toastEl.classList.add(`toast-${type}`);

    // Set content and show
    toastEl.textContent = message;

    // Force reflow so the show transition re-triggers even if already visible
    void toastEl.offsetWidth;
    toastEl.classList.add('show');

    // Hide after duration
    toastTimer = setTimeout(() => {
      toastEl.classList.remove('show');
    }, duration);
  };

  /* ═══════════════════════════════════════════════════════════════════════
     5. PERFORMANCE & TIMING
     ═══════════════════════════════════════════════════════════════════════ */

  /**
   * Creates a debounced version of a function.
   * Useful for autosave or search inputs to prevent firing on every keystroke.
   * @param {Function} func - The function to debounce
   * @param {number} wait - Milliseconds to wait before executing
   * @returns {Function} The debounced function
   */
  utils.debounce = (func, wait) => {
    let timeout;
    return function executedFunction(...args) {
      const later = () => {
        clearTimeout(timeout);
        func(...args);
      };
      clearTimeout(timeout);
      timeout = setTimeout(later, wait);
    };
  };

  /* ═══════════════════════════════════════════════════════════════════════
     6. CLIPBOARD
     ═══════════════════════════════════════════════════════════════════════ */

  /**
   * Copies text to the system clipboard.
   * Falls back to the legacy execCommand method for older browsers or non-HTTPS contexts.
   * @param {string} text - The text to copy
   * @returns {Promise<boolean>} True if successful, false otherwise
   */
  utils.copyToClipboard = async (text) => {
    if (!text) return false;

    try {
      // Modern API (requires secure context like HTTPS or localhost)
      if (navigator.clipboard && window.isSecureContext) {
        await navigator.clipboard.writeText(text);
        return true;
      }
    } catch (err) {
      console.warn('Modern clipboard API failed, falling back.', err);
    }

    // Legacy fallback
    try {
      const textArea = document.createElement('textarea');
      textArea.value = text;

      // Make it invisible but part of the DOM
      textArea.style.position = 'fixed';
      textArea.style.left = '-9999px';
      textArea.style.top = '0';
      document.body.appendChild(textArea);

      textArea.focus();
      textArea.select();

      const successful = document.execCommand('copy');
      document.body.removeChild(textArea);

      return successful;
    } catch (err) {
      console.error('Fallback clipboard copy failed.', err);
      return false;
    }
  };

  /* ═══════════════════════════════════════════════════════════════════════════
  6b. DOWNLOADS (exports & backups)
  ═══════════════════════════════════════════════════════════════════════════
  ★ v12 FIX: inside the Android app, blob: URLs NEVER reach the WebView's
  DownloadListener — that was the "export failed / blob" bug. data: URLs
  DO reach it: MainActivity.handleDownload() decodes them and saves the
  file to the configured export folder (Settings → Explorer → Export
  Path) or to Downloads when no folder is set. Regular browsers keep
  using the nicer Blob URL path.                                             */
  /**
  * Download a text file (JSON exports, backups, …) on any device.
  * @param {string} filename - Suggested file name
  * @param {string} text     - File content
  * @param {string} [mime]   - MIME type (default application/json)
  * @returns {boolean} true when a download was triggered
  */
  utils.downloadFile = (filename, text, mime) => {
    const type = mime || 'application/octet-stream';
    const inApp = !!(window.QuirkyFiles || window.QuirkyStorage ||
      window.QuirkyIme || window.AndroidHardware);
    const a = document.createElement('a');
    let objectUrl = null;
    if (inApp) {
      // WebView-safe: base64 data URL (UTF-8 safe via encodeURIComponent trick)
      a.href = 'data:' + type + ';base64,' + btoa(unescape(encodeURIComponent(text)));
    } else {
      objectUrl = URL.createObjectURL(new Blob([text], { type: type }));
      a.href = objectUrl;
    }
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    a.remove();
    if (objectUrl) setTimeout(() => URL.revokeObjectURL(objectUrl), 5000);
    return true;
  };

  /* ═══════════════════════════════════════════════════════════════════════════
  7. EVENT BUS (Pub/Sub)
  ═══════════════════════════════════════════════════════════════════════════ */
  /**
   * A simple event bus for decoupled communication between modules.
   * Example: IDE.emit('fileSaved', { path: 'test.php' });
   *          IDE.on('fileSaved', (data) => { console.log(data.path); });
   */
  const eventBus = new EventTarget();

  /**
   * Subscribe to an event on the IDE event bus.
   * The callback will be invoked every time the event is emitted.
   * @param {string} eventName - The event to listen for (e.g. 'editor:active', 'file:open')
   * @param {Function} callback - The function to call when the event fires.
   *                              Receives a CustomEvent; access payload via `event.detail`.
   * @returns {void}
   * @example
   * IDE.utils.on('editor:saved', (e) => {
   *     console.log('Saved file:', e.detail.path);
   * });
   */
  utils.on = (eventName, callback) => {
    eventBus.addEventListener(eventName, callback);
  };

  /**
   * Unsubscribe a previously registered callback from an event.
   * The callback reference must be the same function object passed to `on()`.
   * @param {string} eventName - The event to stop listening for
   * @param {Function} callback - The exact function reference that was registered with `on()`
   * @returns {void}
   */
  utils.off = (eventName, callback) => {
    eventBus.removeEventListener(eventName, callback);
  };

  /**
   * Emit (broadcast) an event to all registered listeners.
   * @param {string} eventName - The event name to emit (e.g. 'layout:changed')
   * @param {*} [detail={}] - The payload data to attach to the event.
   *                           Listeners access it via `event.detail`.
   * @returns {void}
   * @example
   * IDE.utils.emit('file:open', 'my-project/index.php');
   */
  utils.emit = (eventName, detail = {}) => {
    const event = new CustomEvent(eventName, { detail });
    eventBus.dispatchEvent(event);
  };
  /* ═══════════════════════════════════════════════════════════════════════
  8. MODAL MANAGER
  ═══════════════════════════════════════════════════════════════════════
  A promise-based modal system. Replaces all prompt() / confirm() calls
  with a proper in-IDE dialog.
  
  Usage:
    const name = await utils.modal.input({
        title: 'New File',
        label: 'File name (relative to workspace):',
        placeholder: 'index.php',
        value: 'untitled.txt'
    });
    // name === null if cancelled, otherwise the trimmed string
  
    const ok = await utils.modal.confirm({
        title: 'Delete File',
        message: 'Are you sure you want to delete "app.js"?',
        danger: true
    });
    // ok === true or false
  ═══════════════════════════════════════════════════════════════════════ */
  utils.modal = (function () {
    const overlay = () => document.getElementById('modal-overlay');
    const titleEl = () => document.getElementById('modal-title');
    const bodyEl = () => document.getElementById('modal-body');
    const cancelBtn = () => document.getElementById('modal-cancel');
    const confirmBtn = () => document.getElementById('modal-confirm');
    const xBtn = () => document.getElementById('modal-x');

    let _resolve = null;

    function open() {
      const ov = overlay();
      if (ov) {
        ov.classList.remove('hidden');
        // Force reflow so the .show transition fires
        void ov.offsetWidth;
        ov.querySelector('.modal').classList.add('show');
      }
    }

    function close() {
      const ov = overlay();
      if (ov) {
        ov.querySelector('.modal').classList.remove('show');
        setTimeout(() => ov.classList.add('hidden'), 200);
      }
    }

    function cleanup() {
      const ov = overlay();
      if (ov) {
        ov.removeEventListener('click', onOverlayClick);
      }
      document.removeEventListener('keydown', onKeydown);
      if (cancelBtn()) cancelBtn().removeEventListener('click', onCancel);
      if (confirmBtn()) confirmBtn().removeEventListener('click', onConfirm);
      if (xBtn()) xBtn().removeEventListener('click', onCancel);
    }

    function onOverlayClick(e) {
      if (e.target === overlay()) cancel();
    }

    function onKeydown(e) {
      if (e.key === 'Escape') cancel();
      if (e.key === 'Enter') {
        // Only confirm on Enter if focus is NOT on a textarea
        if (document.activeElement && document.activeElement.tagName === 'TEXTAREA') return;
        e.preventDefault();
        confirm();
      }
    }

    function cancel() {
      cleanup();
      close();
      if (_resolve) { _resolve(null); _resolve = null; }
    }

    function confirm() {
      cleanup();
      const body = bodyEl();
      const input = body ? body.querySelector('input, textarea') : null;
      const value = input ? input.value.trim() : true;
      close();
      if (_resolve) { _resolve(value); _resolve = null; }
    }

    function onCancel() { cancel(); }
    function onConfirm() { confirm(); }

    /**
     * Input modal — returns a Promise<string|null>
     */
    function input(opts) {
      opts = opts || {};
      return new Promise(function (resolve) {
        _resolve = resolve;
        const t = titleEl();
        const b = bodyEl();
        const cb = confirmBtn();
        const xb = xBtn();

        if (t) t.textContent = opts.title || 'Input';
        if (cb) {
          cb.textContent = opts.confirmText || 'Create';
          cb.className = 'modal-btn primary';
        }

        if (b) {
          b.innerHTML =
            '<label style="display:block;font-size:.82rem;color:var(--text-2);margin-bottom:.5rem;font-family:var(--font-body)">' +
            utils.escapeHtml(opts.label || '') + '</label>' +
            '<input type="text" id="modal-input" value="' + utils.escapeHtml(opts.value || '') + '" ' +
            'placeholder="' + utils.escapeHtml(opts.placeholder || '') + '" ' +
            'style="width:100%" autocomplete="off" spellcheck="false">' +
            (opts.hint ? '<p style="margin-top:.5rem;font-size:.72rem;color:var(--muted);font-family:var(--font-mono)">' + utils.escapeHtml(opts.hint) + '</p>' : '');
        }

        open();

        // Focus + select the input
        requestAnimationFrame(function () {
          const inp = document.getElementById('modal-input');
          if (inp) {
            inp.focus();
            // Select the filename part (before extension) for easy overwrite
            const val = inp.value;
            const dotIdx = val.lastIndexOf('.');
            if (dotIdx > 0) inp.setSelectionRange(0, dotIdx);
            else inp.select();
          }
        });

        // Wire events
        const ov = overlay();
        if (ov) ov.addEventListener('click', onOverlayClick);
        document.addEventListener('keydown', onKeydown);
        if (cancelBtn()) cancelBtn().addEventListener('click', onCancel);
        if (cb) cb.addEventListener('click', onConfirm);
        if (xb) xb.addEventListener('click', onCancel);
      });
    }

    /**
     * Confirm modal — returns a Promise<boolean>
     */
    function confirmDialog(opts) {
      opts = opts || {};
      return new Promise(function (resolve) {
        _resolve = function (val) { resolve(val !== null); };
        const t = titleEl();
        const b = bodyEl();
        const cb = confirmBtn();
        const xb = xBtn();

        if (t) t.textContent = opts.title || 'Confirm';
        if (cb) {
          cb.textContent = opts.confirmText || 'Delete';
          cb.className = 'modal-btn primary' + (opts.danger ? ' danger' : '');
          /* ★ FIX: danger confirms were red-on-red — the .danger CSS class
             colors the TEXT var(--danger) while the inline style painted the
             BACKGROUND the same color → invisible label (screenshot bug).
             Force a readable pair inline so it wins over any stylesheet. */
          if (opts.danger) {
            cb.style.background = 'var(--danger)';
            cb.style.color = '#0b0f17';
          } else {
            cb.style.background = '';
            cb.style.color = '';
          }
        }

        if (b) {
          b.innerHTML =
            '<p style="font-size:.9rem;color:var(--text);line-height:1.6">' +
            (opts.message || 'Are you sure?') + '</p>' +
            (opts.detail ? '<p style="margin-top:.6rem;font-size:.78rem;color:var(--muted);font-family:var(--font-mono)">' + utils.escapeHtml(opts.detail) + '</p>' : '');
        }

        open();

        const ov = overlay();
        if (ov) ov.addEventListener('click', onOverlayClick);
        document.addEventListener('keydown', onKeydown);
        if (cancelBtn()) cancelBtn().addEventListener('click', onCancel);
        if (cb) cb.addEventListener('click', onConfirm);
        if (xb) xb.addEventListener('click', onCancel);

        requestAnimationFrame(function () { if (cb) cb.focus(); });
      });
    }

    /**
     * Three-way choice modal — returns a Promise that resolves to
     * 'overwrite' | 'rename' | 'discard'.
     * Used for name conflicts. Closing via ✕ / Escape / clicking the
     * dark backdrop counts as 'discard' (safest: do nothing).
     */
    function choose(opts) {
      opts = opts || {};
      return new Promise(function (resolve) {
        const t = titleEl();
        const b = bodyEl();
        const cb = confirmBtn();
        const cancelB = cancelBtn();
        const xb = xBtn();
        const footer = cb ? cb.parentNode : null;

        // Build the middle button dynamically (removed again on close)
        const old = document.getElementById('modal-mid');
        if (old) old.remove();
        const midBtn = document.createElement('button');
        midBtn.id = 'modal-mid';
        midBtn.className = 'modal-btn ghost';
        midBtn.textContent = opts.midText || 'Rename';
        if (footer && cb) footer.insertBefore(midBtn, cb);

        if (t) t.textContent = opts.title || 'Choose';
        if (cb) {
          cb.textContent = opts.confirmText || 'Overwrite';
          cb.className = 'modal-btn primary' + (opts.danger ? ' danger' : '');
          cb.style.background = opts.danger ? 'var(--danger)' : '';
        }
        if (cancelB) cancelB.textContent = opts.discardText || 'Discard';
        if (b) {
          b.innerHTML =
            '<p style="font-size:.9rem;color:var(--text);line-height:1.6">' +
            (opts.message || '') + '</p>' +
            (opts.detail ? '<p style="margin-top:.6rem;font-size:.78rem;color:var(--muted);font-family:var(--font-mono)">' + utils.escapeHtml(opts.detail) + '</p>' : '');
        }

        function done(val) {
          if (cb) cb.removeEventListener('click', onConfirm);
          midBtn.removeEventListener('click', onRename);
          if (cancelB) cancelB.removeEventListener('click', onDiscard);
          if (xb) xb.removeEventListener('click', onDiscard);
          document.removeEventListener('keydown', onKey);
          const ov = overlay();
          if (ov) ov.removeEventListener('click', onOverlay);
          midBtn.remove();                       // don't leak into other dialogs
          close();
          resolve(val);
        }
        function onConfirm() { done('overwrite'); }
        function onRename() { done('rename'); }
        function onDiscard() { done('discard'); }
        function onKey(e) { if (e.key === 'Escape') { e.preventDefault(); done('discard'); } }
        function onOverlay(e) { if (e.target === overlay()) done('discard'); }

        if (cb) cb.addEventListener('click', onConfirm);
        midBtn.addEventListener('click', onRename);
        if (cancelB) cancelB.addEventListener('click', onDiscard);
        if (xb) xb.addEventListener('click', onDiscard);
        document.addEventListener('keydown', onKey);
        const ov = overlay();
        if (ov) ov.addEventListener('click', onOverlay);

        open();
        requestAnimationFrame(function () { if (cb) cb.focus(); });
      });
    }

    return { input: input, confirm: confirmDialog, choose: choose };
  })();

  /* ═══════════════════════════════════════════════════════════════════════
     EXPOSE TO GLOBAL NAMESPACE
     ═══════════════════════════════════════════════════════════════════════ */

  // Attach all utilities to the global IDE object so other scripts can use them
  window.IDE.utils = utils;

})();