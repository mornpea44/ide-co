/**
 * ═══════════════════════════════════════════════════════════════════════════
 *  QUIRKY IDE — MOBILE WORKSPACE MODULE (★ v11: import / export runtime)
 * ═══════════════════════════════════════════════════════════════════════════
 *
 *  v11 scope (after the Explorer adjustment):
 *    • IMPORT — wires the "Import File… / Import Folder…" rows inside the
 *      ＋ (New Item) sheet to the Android picker (window.QuirkyFiles), and
 *      copies the picked items into the workspace via api.files.importPath,
 *      with the shared name-conflict sheet for duplicates.
 *    • EXPORT — exportPath() is called by the long-press "Export" action in
 *      mobile-actions.js. It creates a short-lived server token and hands
 *      the URL to the WebView, whose DownloadListener (MainActivity.java)
 *      saves the file on a background thread into the configured export
 *      folder (Settings → Explorer) or Downloads.
 *
 *  Removed in v11 (moved to Settings → Explorer card):
 *    • the clickable "workspace/" chip,
 *    • the ms-workspace / ms-workspace-picker bottom sheets.
 *
 *  EXPOSES: window.IDE.mobileWorkspace
 * ═══════════════════════════════════════════════════════════════════════════
 */
(function () {
  'use strict';
  var U = window.IDE && window.IDE.utils;
  var api = window.IDE && window.IDE.api;
  if (!U || !api) return;

  function toast(msg, type) { if (U && U.toast) U.toast(msg, type); }
  function hasBridge() { return typeof window.QuirkyFiles !== 'undefined' && window.QuirkyFiles; }
  function basename(p) {
    p = String(p || '');
    var i = p.lastIndexOf('/');
    return i >= 0 ? p.substring(i + 1) : p;
  }

  /* ═══════════════════════════════════════════════════════════════
     EXPORT (long-press → Export)
     ═══════════════════════════════════════════════════════════════ */
  function exportPath(path, type) {
    if (!path) { toast('⚠ Nothing to export', 'warning'); return; }
    toast('📦 Preparing export…');
    api.files.exportToken(path).then(function (t) {
      if (!t || !t.url) throw new Error('No export link received');
      var a = document.createElement('a');
      a.href = t.url;                       // index.php?export-token=…
      a.download = t.name || 'export';      // triggers the DownloadListener
      a.style.display = 'none';
      document.body.appendChild(a);
      a.click();
      setTimeout(function () { a.remove(); }, 800);
    }).catch(function (err) {
      toast('⚠ Export failed: ' + ((err && err.message) ? err.message : err), 'error');
    });
  }

  /* Success toast when the Android side finished saving the file */
  window.addEventListener('quirky-download-finished', function (e) {
    var name = (e && e.detail && e.detail.name) ? e.detail.name : '';
    toast('✅ Export saved' + (name ? ': ' + name : ''));
  });

  /* ═══════════════════════════════════════════════════════════════
     IMPORT (＋ sheet rows: Import File… / Import Folder…)
     ═══════════════════════════════════════════════════════════════ */
  function wireImportRows() {
    var niSheet = document.getElementById('ms-new-item');
    if (!niSheet) return;
    niSheet.querySelectorAll('.m-sheet-row[data-action="import-file"], .m-sheet-row[data-action="import-folder"]')
      .forEach(function (row) {
        row.addEventListener('click', function () {
          var action = row.getAttribute('data-action');
          if (!hasBridge()) {
            toast('⚠ Import is only available inside the Android app', 'warning');
            return;
          }
          if (action === 'import-file') window.QuirkyFiles.pickImportFiles();
          else window.QuirkyFiles.pickImportFolder();
        });
      });
  }

  /* Picker results → copy into the workspace, one item at a time so
     conflict sheets never stack (same pattern as paste/drag-move). */
  window.addEventListener('quirky-files-picked', function (e) {
    var d = (e && e.detail) ? e.detail : {};
    if (d.mode !== 'import-file' && d.mode !== 'import-folder') return; // not ours
    if (d.error) {
      if (d.error !== 'cancelled') toast('⚠ ' + d.error, 'error');
      return;
    }
    var paths = d.paths || [];
    if (!paths.length) return;
    var isFolder = (d.mode === 'import-folder');
    var Actions = window.IDE && window.IDE.mobileActions;
    if (Actions && Actions.beginConflictBatch) Actions.beginConflictBatch();

    var i = 0, done = 0;
    (function next() {
      if (i >= paths.length) {
        if (done > 0) {
          toast('📥 Imported ' + done + ' item' + (done === 1 ? '' : 's'));
          if (U && U.emit) U.emit('filetree:refresh');
        }
        return;
      }
      var from = paths[i++];
      var name = (isFolder && d.name) ? d.name : basename(from);
      var resolve = (Actions && Actions.resolveNameConflict)
        ? Actions.resolveNameConflict(name, name, isFolder ? 'folder' : 'file', paths.length - i)
        : Promise.resolve({ action: 'proceed', path: name });
      resolve.then(function (res) {
        if (!res || res.action === 'skip' || res.action === 'discard') { next(); return; }
        var dest = res.path || name;
        var overwrite = (res.action === 'overwrite');
        api.files.importPath(from, dest, overwrite).then(function () {
          done++;
          next();
        }).catch(function (err) {
          toast('⚠ ' + basename(from) + ': ' + ((err && err.message) ? err.message : err), 'error');
          next();
        });
      }).catch(function () { next(); });
    })();
  });

  /* ═══════════════════════════════════════════════════════════════
     BOOT + EXPOSE
     ═══════════════════════════════════════════════════════════════ */
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', wireImportRows);
  } else {
    wireImportRows();
  }

  window.IDE = window.IDE || {};
  window.IDE.mobileWorkspace = {
    exportPath: exportPath
  };
})();