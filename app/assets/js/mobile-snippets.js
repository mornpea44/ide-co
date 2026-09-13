/**
 * ═══════════════════════════════════════════════════════════════════════════
 *  QUIRKY IDE — SNIPPET STUDIO (Settings → Snippet Studio card)
 * ═══════════════════════════════════════════════════════════════════════════
 *
 *  A full snippet/boilerplate manager that lives inside the Settings panel.
 *  Features:
 *    • Coverage overview: shows built-in + custom snippet counts per language
 *    • Create / Edit / Delete custom snippets with a code editor
 *    • Import / Export as JSON files
 *    • Snippets auto-merge into autocomplete via registerPool()
 *
 *  EXPOSES: window.IDE.mobileSnippets = { render, wire, refreshAutocomplete }
 * ═══════════════════════════════════════════════════════════════════════════
 */
(function () {
  'use strict';

  var U = window.IDE && window.IDE.utils;
  var api = window.IDE && window.IDE.api;

  /* ── Built-in pool sizes (mirrors autocomplete.js POOLS) ── */
  var BUILTIN_COUNTS = {
    php: { keywords: 60, functions: 62, snippets: 18 },
    html: { keywords: 26, functions: 0, snippets: 30 },
    css: { keywords: 40, functions: 16, snippets: 12 },
    javascript: { keywords: 44, functions: 50, snippets: 19 },
    python: { keywords: 32, functions: 48, snippets: 14 },
    sql: { keywords: 50, functions: 35, snippets: 9 },
    shell: { keywords: 35, functions: 17, snippets: 10 },
    c: { keywords: 34, functions: 32, snippets: 14 },
    cpp: { keywords: 42, functions: 24, snippets: 12 },
    java: { keywords: 48, functions: 48, snippets: 17 },
    go: { keywords: 25, functions: 30, snippets: 9 },
    rust: { keywords: 38, functions: 20, snippets: 9 },
    ruby: { keywords: 38, functions: 28, snippets: 7 },
    lua: { keywords: 21, functions: 23, snippets: 7 },
    swift: { keywords: 40, functions: 16, snippets: 6 },
    dart: { keywords: 48, functions: 21, snippets: 6 },
    markdown: { keywords: 0, functions: 0, snippets: 17 }
  };

  var ALL_LANGS = [
    { id: 'php', label: 'PHP', icon: '🐘' },
    { id: 'html', label: 'HTML', icon: '🌐' },
    { id: 'css', label: 'CSS', icon: '🎨' },
    { id: 'javascript', label: 'JavaScript', icon: '⚡' },
    { id: 'python', label: 'Python', icon: '🐍' },
    { id: 'sql', label: 'SQL', icon: '🗄️' },
    { id: 'shell', label: 'Shell', icon: '💻' },
    { id: 'c', label: 'C', icon: '🔧' },
    { id: 'cpp', label: 'C++', icon: '🔧' },
    { id: 'java', label: 'Java', icon: '☕' },
    { id: 'go', label: 'Go', icon: '🐹' },
    { id: 'rust', label: 'Rust', icon: '🦀' },
    { id: 'ruby', label: 'Ruby', icon: '💎' },
    { id: 'lua', label: 'Lua', icon: '🌙' },
    { id: 'swift', label: 'Swift', icon: '🍎' },
    { id: 'dart', label: 'Dart', icon: '🎯' },
    { id: 'markdown', label: 'Markdown', icon: '📝' },
    { id: 'kotlin', label: 'Kotlin', icon: '🟣' },
    { id: 'csharp', label: 'C#', icon: '🟣' },
    { id: 'perl', label: 'Perl', icon: '🐪' },
    { id: 'text', label: 'Plain Text', icon: '📄' }
  ];

  var SNIPPET_TYPES = [
    { id: 'snippet', label: 'Snippet', desc: 'Small reusable code piece' },
    { id: 'boilerplate', label: 'Boilerplate', desc: 'Full file/starter template' }
  ];

  /* ═══════════════════════════════════════════════════════════
  RENDER — returns HTML for the Snippet Studio card body
  ═══════════════════════════════════════════════════════════ */
  function render() {
    return '<div class="settings-card-inner">' +
      /* ── Sub-header: Coverage Overview ── */
      '<div class="settings-group-box">' +
      '<div class="settings-group-title">Coverage Overview</div>' +
      '<div class="snip-coverage" id="snip-coverage">' +
      '<div class="settings-note-row">Loading coverage…</div>' +
      '</div>' +
      '</div>' +
      /* ── Sub-header: My Snippets ── */
      '<div class="settings-group-box">' +
      '<div class="settings-group-title">My Snippets</div>' +
      '<div class="snip-list" id="snip-list">' +
      '<div class="settings-note-row">Loading snippets…</div>' +
      '</div>' +
      '<div style="padding:0.6rem 0.8rem;">' +
      '<button type="button" class="settings-action-btn" id="snip-add-btn">＋ New Snippet</button>' +
      '</div>' +
      '</div>' +
      /* ── Sub-header: Import / Export ── */
      '<div class="settings-group-box">' +
      '<div class="settings-group-title">Import / Export</div>' +
      '<div class="settings-row">' +
      '<div class="settings-info">' +
      '<span class="settings-label">📤 Export all</span>' +
      '<span class="settings-desc">Download your snippets as a JSON backup file</span>' +
      '</div>' +
      '<div class="settings-control">' +
      '<button type="button" class="settings-action-btn" id="snip-export-btn">Export</button>' +
      '</div>' +
      '</div>' +
      '<div class="settings-row">' +
      '<div class="settings-info">' +
      '<span class="settings-label">📥 Import</span>' +
      '<span class="settings-desc">Load snippets from a JSON file (duplicates are skipped)</span>' +
      '</div>' +
      '<div class="settings-control">' +
      '<button type="button" class="settings-action-btn" id="snip-import-btn">Import…</button>' +
      '<input type="file" id="snip-import-file" accept=".json,application/json" hidden>' +
      '</div>' +
      '</div>' +
      '</div>' +
      '</div>';
  }

  /* ═══════════════════════════════════════════════════════════
  WIRE — attach event listeners after render
  ═══════════════════════════════════════════════════════════ */
  function wire(scope) {
    loadCoverage(scope);
    loadSnippets(scope);

    var addBtn = scope.querySelector('#snip-add-btn');
    if (addBtn) addBtn.addEventListener('click', function () { openEditor(scope, null); });

    var exportBtn = scope.querySelector('#snip-export-btn');
    if (exportBtn) exportBtn.addEventListener('click', doExport);

    var importBtn = scope.querySelector('#snip-import-btn');
    var importFile = scope.querySelector('#snip-import-file');
    if (importBtn && importFile) {
      importBtn.addEventListener('click', function () { importFile.click(); });
      importFile.addEventListener('change', function () {
        doImport(this.files && this.files[0], scope);
        this.value = '';
      });
    }
  }

  /* ═══════════════════════════════════════════════════════════
  COVERAGE OVERVIEW
  ═══════════════════════════════════════════════════════════ */
  function loadCoverage(scope) {
    var el = scope.querySelector('#snip-coverage');
    if (!el) return;

    api.snippets.coverage().then(function (data) {
      var custom = (data && data.coverage) || {};
      var html = '<div class="snip-cov-grid">';

      ALL_LANGS.forEach(function (lang) {
        var bi = BUILTIN_COUNTS[lang.id] || { keywords: 0, functions: 0, snippets: 0 };
        var cu = custom[lang.id] || { snippet: 0, boilerplate: 0 };
        var biTotal = bi.keywords + bi.functions + bi.snippets;
        var cuTotal = cu.snippet + cu.boilerplate;
        var total = biTotal + cuTotal;

        html += '<div class="snip-cov-row">' +
          '<span class="snip-cov-icon">' + lang.icon + '</span>' +
          '<span class="snip-cov-name">' + lang.label + '</span>' +
          '<span class="snip-cov-count">' +
          '<b>' + total + '</b> total' +
          (cuTotal > 0 ? ' <em class="snip-cov-custom">(+' + cuTotal + ' custom)</em>' : '') +
          '</span>' +
          '</div>';
      });

      html += '</div>';
      el.innerHTML = html;
    }).catch(function () {
      el.innerHTML = '<div class="settings-note-row">Could not load coverage data.</div>';
    });
  }

  /* ═══════════════════════════════════════════════════════════
  SNIPPET LIST
  ═══════════════════════════════════════════════════════════ */
  function loadSnippets(scope) {
    var el = scope.querySelector('#snip-list');
    if (!el) return;

    api.snippets.list().then(function (data) {
      var snippets = (data && data.snippets) || [];
      if (snippets.length === 0) {
        el.innerHTML = '<div class="settings-note-row">No custom snippets yet. Tap "＋ New Snippet" to create one.</div>';
        return;
      }

      var html = '';
      // Group by language
      var grouped = {};
      snippets.forEach(function (s) {
        if (!grouped[s.lang]) grouped[s.lang] = [];
        grouped[s.lang].push(s);
      });

      Object.keys(grouped).sort().forEach(function (lang) {
        var langInfo = ALL_LANGS.find(function (l) { return l.id === lang; }) || { label: lang, icon: '📄' };
        html += '<div class="snip-lang-group">' +
          '<div class="snip-lang-head">' + langInfo.icon + ' ' + langInfo.label +
          ' <span class="snip-lang-count">(' + grouped[lang].length + ')</span></div>';

        grouped[lang].forEach(function (s) {
          var typeIcon = s.type === 'boilerplate' ? '📋' : '⧉';
          html += '<div class="snip-item" data-id="' + escAttr(s.id) + '">' +
            '<span class="snip-item-icon">' + typeIcon + '</span>' +
            '<span class="snip-item-label">' + esc(s.label) + '</span>' +
            '<span class="snip-item-detail">' + esc(s.detail || '') + '</span>' +
            '<button class="snip-item-edit" data-id="' + escAttr(s.id) + '" title="Edit">✏️</button>' +
            '<button class="snip-item-export" data-id="' + escAttr(s.id) + '" title="Export">📤</button>' +
            '<button class="snip-item-del" data-id="' + escAttr(s.id) + '" title="Delete">🗑️</button>' +
            '</div>';
        });

        html += '</div>';
      });

      el.innerHTML = html;

      // Wire edit/delete buttons
      el.querySelectorAll('.snip-item-edit').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
          e.stopPropagation();
          var id = this.getAttribute('data-id');
          var snippet = snippets.find(function (s) { return s.id === id; });
          if (snippet) openEditor(scope, snippet);
        });
      });
      el.querySelectorAll('.snip-item-export').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
          e.stopPropagation();
          var id = this.getAttribute('data-id');
          var snippet = snippets.find(function (s) { return s.id === id; });
          if (snippet) doExportOne(snippet);
        });
      });
      el.querySelectorAll('.snip-item-del').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
          e.stopPropagation();
          var id = this.getAttribute('data-id');
          var snippet = snippets.find(function (s) { return s.id === id; });
          if (!snippet) return;
          U.modal.confirm({
            title: 'Delete snippet?',
            message: 'Delete "' + snippet.label + '" (' + snippet.lang + ')? This cannot be undone.',
            confirmText: 'Delete',
            danger: true
          }).then(function (ok) {
            if (!ok) return;
            api.snippets.delete(id).then(function () {
              if (U.toast) U.toast('🗑️ Deleted: ' + snippet.label);
              loadSnippets(scope);
              loadCoverage(scope);
              refreshAutocomplete();
            }).catch(function (err) {
              if (U.toast) U.toast('⚠ ' + err.message, 'error');
            });
          });
        });
      });
    }).catch(function () {
      el.innerHTML = '<div class="settings-note-row">Could not load snippets.</div>';
    });
  }

  /* ═══════════════════════════════════════════════════════════
  SNIPPET EDITOR (modal-based)
  ═══════════════════════════════════════════════════════════ */
  function openEditor(scope, existing) {
    var isEdit = !!existing;
    var title = isEdit ? 'Edit Snippet' : 'New Snippet';

    // Build a form using the modal system
    var langOptions = ALL_LANGS.map(function (l) {
      var sel = (existing && existing.lang === l.id) ? ' selected' : '';
      return '<option value="' + l.id + '"' + sel + '>' + l.icon + ' ' + l.label + '</option>';
    }).join('');

    var typeOptions = SNIPPET_TYPES.map(function (t) {
      var sel = (existing && existing.type === t.id) ? ' selected' : '';
      return '<option value="' + t.id + '"' + sel + '>' + t.label + ' — ' + t.desc + '</option>';
    }).join('');

    var bodyHtml =
      '<div class="snip-editor-form">' +
      '<label class="snip-field-label">Language</label>' +
      '<select id="snip-ed-lang" class="settings-select" style="width:100%">' + langOptions + '</select>' +
      '<label class="snip-field-label">Type</label>' +
      '<select id="snip-ed-type" class="settings-select" style="width:100%">' + typeOptions + '</select>' +
      '<label class="snip-field-label">Trigger Word (what you type)</label>' +
      '<input type="text" id="snip-ed-label" class="settings-text-input" ' +
      'value="' + escAttr(existing ? existing.label : '') + '" ' +
      'placeholder="e.g. api-class" autocomplete="off" spellcheck="false">' +
      '<label class="snip-field-label">Description (optional)</label>' +
      '<input type="text" id="snip-ed-detail" class="settings-text-input" ' +
      'value="' + escAttr(existing ? existing.detail : '') + '" ' +
      'placeholder="e.g. REST API class skeleton" autocomplete="off">' +
      '<label class="snip-field-label">Code Body</label>' +
      '<div class="snip-body-hint">Use · (middle dot) for indent markers, newlines for line breaks</div>' +
      '<textarea id="snip-ed-body" class="snip-editor-textarea" rows="10" ' +
      'placeholder="Your code here…">' + esc(existing ? existing.body : '') + '</textarea>' +
      '</div>';

    // Use a custom overlay since the modal system is limited for forms
    var ov = document.createElement('div');
    ov.className = 'snip-editor-overlay';
    ov.innerHTML =
      '<div class="snip-editor-panel">' +
      '<div class="snip-editor-header">' +
      '<span class="snip-editor-title">' + title + '</span>' +
      '<button class="snip-editor-close" id="snip-ed-close">✕</button>' +
      '</div>' +
      '<div class="snip-editor-body">' + bodyHtml + '</div>' +
      '<div class="snip-editor-footer">' +
      '<button class="settings-action-btn" id="snip-ed-cancel">Cancel</button>' +
      '<button class="settings-action-btn" id="snip-ed-save" style="border-color:var(--accent-border);color:var(--accent);">' +
      (isEdit ? '💾 Save Changes' : '＋ Create Snippet') + '</button>' +
      '</div>' +
      '</div>';

    document.body.appendChild(ov);

    // Wire
    ov.querySelector('#snip-ed-close').addEventListener('click', function () { ov.remove(); });
    ov.querySelector('#snip-ed-cancel').addEventListener('click', function () { ov.remove(); });
    ov.addEventListener('click', function (e) { if (e.target === ov) ov.remove(); });

    ov.querySelector('#snip-ed-save').addEventListener('click', function () {
      var lang = ov.querySelector('#snip-ed-lang').value;
      var type = ov.querySelector('#snip-ed-type').value;
      var label = ov.querySelector('#snip-ed-label').value.trim();
      var detail = ov.querySelector('#snip-ed-detail').value.trim();
      var body = ov.querySelector('#snip-ed-body').value;

      if (!label) { if (U.toast) U.toast('⚠ Trigger word is required', 'warning'); return; }
      if (!body) { if (U.toast) U.toast('⚠ Code body is required', 'warning'); return; }

      var payload = { lang: lang, type: type, label: label, detail: detail, body: body };
      if (isEdit) payload.id = existing.id;

      api.snippets.save(payload).then(function () {
        ov.remove();
        if (U.toast) U.toast(isEdit ? '✅ Snippet updated' : '✅ Snippet created');
        loadSnippets(scope);
        loadCoverage(scope);
        refreshAutocomplete();
      }).catch(function (err) {
        if (U.toast) U.toast('⚠ ' + err.message, 'error');
      });
    });
  }

  /* ═══════════════════════════════════════════════════════════
  IMPORT / EXPORT
  ═══════════════════════════════════════════════════════════ */
  /** Shared saver: uses IDE.utils.downloadFile (WebView-safe) when present. */
  function downloadSnippetsJson(name, payload) {
    var json = JSON.stringify(payload, null, 2);
    if (U && typeof U.downloadFile === 'function') {
      U.downloadFile(name, json, 'application/json');
      if (U.toast) U.toast('📤 Exported ' + name);
      return;
    }
    /* Legacy fallback for very old builds without the helper */
    var blob = new Blob([json], { type: 'application/json' });
    var a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = name;
    document.body.appendChild(a);
    a.click();
    setTimeout(function () { URL.revokeObjectURL(a.href); a.remove(); }, 500);
    if (U.toast) U.toast('📤 Exported ' + name);
  }
  /** Export ONE snippet/boilerplate as its own JSON file. */
  function doExportOne(snippet) {
    var safeLabel = String(snippet.label || 'snippet').replace(/[^\w\-.+]+/g, '_');
    var name = 'quirky-snippet-' + (snippet.lang || 'text') + '-' + safeLabel + '.json';
    downloadSnippetsJson(name, {
      app: 'quirky-ide-snippets',
      version: 1,
      exportedAt: new Date().toISOString(),
      snippets: [snippet]
    });
  }
  function doExport() {
    api.snippets.export().then(function (data) {
      var d = new Date();
      var name = 'quirky-snippets-' + d.getFullYear() +
        String(d.getMonth() + 1).padStart(2, '0') +
        String(d.getDate()).padStart(2, '0') + '.json';
      downloadSnippetsJson(name, data);
    }).catch(function (err) {
      if (U.toast) U.toast('⚠ Export failed: ' + err.message, 'error');
    });
  }

  function doImport(file, scope) {
    if (!file) return;
    var reader = new FileReader();
    reader.onerror = function () { if (U.toast) U.toast('Could not read file', 'error'); };
    reader.onload = function () {
      var data = null;
      try { data = JSON.parse(String(reader.result || '')); }
      catch (e) { if (U.toast) U.toast('Not a valid JSON file', 'error'); return; }

      if (!data || typeof data !== 'object' || !Array.isArray(data.snippets)) {
        if (U.toast) U.toast('File must contain a "snippets" array', 'error');
        return;
      }

      api.snippets.import(data).then(function (res) {
        var msg = '📥 Imported ' + res.imported + ' snippet(s)';
        if (res.skipped > 0) msg += ', skipped ' + res.skipped;
        if (U.toast) U.toast(msg, res.imported > 0 ? 'success' : 'warning');
        loadSnippets(scope);
        loadCoverage(scope);
        refreshAutocomplete();
      }).catch(function (err) {
        if (U.toast) U.toast('⚠ Import failed: ' + err.message, 'error');
      });
    };
    reader.readAsText(file);
  }

  /* ═══════════════════════════════════════════════════════════
  AUTOCOMPLETE INTEGRATION
  ═══════════════════════════════════════════════════════════ */
  /**
   * Re-fetches custom snippets and merges them into autocomplete pools.
   * Called after any create/edit/delete/import.
   */
  function refreshAutocomplete() {
    var AC = window.IDE && window.IDE.autocomplete;
    if (!AC || typeof AC.registerPool !== 'function') return;

    api.snippets.list().then(function (data) {
      var snippets = (data && data.snippets) || [];
      // Group by language
      var grouped = {};
      snippets.forEach(function (s) {
        if (!grouped[s.lang]) grouped[s.lang] = { snippets: [] };
        grouped[s.lang].snippets.push({
          label: s.label,
          body: s.body,
          detail: s.detail || (s.type === 'boilerplate' ? 'boilerplate' : 'snippet')
        });
      });
      // Register each language group
      Object.keys(grouped).forEach(function (lang) {
        AC.registerPool(lang, grouped[lang]);
      });
    }).catch(function () { /* silent */ });
  }

  /* ═══════════════════════════════════════════════════════════
  HELPERS
  ═══════════════════════════════════════════════════════════ */
  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;')
      .replace(/>/g, '&gt;');
  }
  function escAttr(s) {
    return esc(s).replace(/"/g, '&quot;');
  }

  /* ═══════════════════════════════════════════════════════════
  BOOT — load custom snippets into autocomplete on page load
  ═══════════════════════════════════════════════════════════ */
  function boot() {
    refreshAutocomplete();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }

  /* ═══════════════════════════════════════════════════════════
  EXPOSE
  ═══════════════════════════════════════════════════════════ */
  window.IDE = window.IDE || {};
  window.IDE.mobileSnippets = {
    render: render,
    wire: wire,
    refreshAutocomplete: refreshAutocomplete
  };

})();