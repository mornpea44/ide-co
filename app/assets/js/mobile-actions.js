/**
* ═══════════════════════════════════════════════════════════════════════════
*  QUIRKY IDE — MOBILE FILE ACTIONS — CORE (Phase 7 · Task 7.11)
* ═══════════════════════════════════════════════════════════════════════════
*
*  Handles:
*    • Haptic feedback utility
*    • Long-press detection on file tree rows (500ms, 10px tolerance)
*    • File action sheet (Open, Rename, Copy, Cut, Paste, Duplicate, Delete)
*    • ★ NEW: "New File Inside" / "New Folder Inside" for folders
*    • Client-side clipboard state
*    • Paste name-conflict resolution (Overwrite / Rename / Discard)
*    • Rename / Delete confirmation sheets
*
*  Depends on: window.IDE.mobileSheets (loaded before this file)
*
*  EXPOSES: window.IDE.mobileActions
* ═══════════════════════════════════════════════════════════════════════════
*/
(function () {
    'use strict';

    var U = window.IDE && window.IDE.utils;
    var api = window.IDE && window.IDE.api;
    if (!U || !api) return;

    var Sheets = window.IDE.mobileSheets;
    function openSheet(id, bId) { Sheets.openSheet(id, bId); }
    function closeSheet(id, bId) { Sheets.closeSheet(id, bId); }

    var longPressTimer = null;
    var longPressTarget = null;
    var touchStartX = 0;
    var touchStartY = 0;
    var isLongPressActive = false;

    /* Clipboard now supports multiple items (Task 8.9) */
    var clipboard = { items: [], mode: 'copy' };
    var currentTarget = null;

    /* ═══════════════════════════════════════════════════════════════
    HAPTIC FEEDBACK UTILITY
    ═══════════════════════════════════════════════════════════════ */
    function haptic() {
        try {
            if (navigator.vibrate && (!navigator.userActivation || navigator.userActivation.isActive)) {
                navigator.vibrate(10);
            }
        } catch (e) { }
    }

    /* ═══════════════════════════════════════════════════════════════
    LONG-PRESS DETECTION
    ═══════════════════════════════════════════════════════════════ */
    function initLongPress() {
        var container = document.getElementById('m-file-tree');
        if (!container) return;

        container.addEventListener('touchstart', function (e) {
            var row = e.target.closest('.m-tree-row');
            if (!row) return;

            // Don't interfere if drag system is actively dragging
            if (window.IDE.mobileFileTree && window.IDE.mobileFileTree._dragActive()) return;

            // ★ Gesture Revamp: SELECTED items belong to the drag system.
            // Long-pressing them starts a drag (mobile-filetree.js) and
            // must NEVER show the helpers.
            var rowPath = row.getAttribute('data-path');
            if (rowPath && window.IDE.mobileFileTree &&
                window.IDE.mobileFileTree.isPathSelected &&
                window.IDE.mobileFileTree.isPathSelected(rowPath)) {
                return;
            }

            touchStartX = e.touches[0].clientX;
            touchStartY = e.touches[0].clientY;
            longPressTarget = row;
            isLongPressActive = false;

            longPressTimer = setTimeout(function () {
                // Check if drag took over
                if (window.IDE.mobileFileTree && window.IDE.mobileFileTree._dragArmed()) return;

                isLongPressActive = true;
                haptic();

                // ★ Gesture Revamp: the release after a long-press still fires
                // a synthetic click — swallow it so the file doesn't open the
                // moment the helpers sheet appears.
                if (window.IDE.mobileFileTree && window.IDE.mobileFileTree.suppressNextClick) {
                    window.IDE.mobileFileTree.suppressNextClick();
                }

                showFileActions(row);
            }, 500);
        }, { passive: true });

        container.addEventListener('touchmove', function (e) {
            if (!longPressTimer) return;
            var dx = Math.abs(e.touches[0].clientX - touchStartX);
            var dy = Math.abs(e.touches[0].clientY - touchStartY);
            if (dx > 10 || dy > 10) {
                clearTimeout(longPressTimer);
                longPressTimer = null;
                longPressTarget = null;
            }
        }, { passive: true });

        container.addEventListener('touchend', function () {
            if (longPressTimer) {
                clearTimeout(longPressTimer);
                longPressTimer = null;
            }
            if (isLongPressActive) {
                isLongPressActive = false;
            }
            longPressTarget = null;
        }, { passive: true });

        container.addEventListener('touchcancel', function () {
            if (longPressTimer) {
                clearTimeout(longPressTimer);
                longPressTimer = null;
            }
            longPressTarget = null;
            isLongPressActive = false;
        }, { passive: true });
    }

    /* ═══════════════════════════════════════════════════════════════
    SHOW FILE ACTIONS SHEET
    ═══════════════════════════════════════════════════════════════ */
    function showFileActions(row) {
        var path = row.getAttribute('data-path');
        var type = row.getAttribute('data-type');
        var nameEl = row.querySelector('.m-tree-name');
        var name = nameEl ? nameEl.textContent : path.split('/').pop();

        currentTarget = { path: path, type: type, name: name };

        var titleEl = document.getElementById('fa-title');
        if (titleEl) {
            titleEl.textContent = (type === 'folder' ? '📁 ' : '📄 ') + name;
        }

        var openBtn = document.querySelector('#ms-file-actions [data-action="open"]');
        if (openBtn) {
            var openLabel = openBtn.querySelector('.sr-label');
            if (openLabel) {
                openLabel.textContent = (type === 'folder') ? 'Expand / Collapse' : 'Open in Editor';
            }
        }

        /* ★ NEW: Show "New File Inside" and "New Folder Inside" only for folders */
        var newFileInBtn = document.getElementById('fa-new-file-in');
        var newFolderInBtn = document.getElementById('fa-new-folder-in');
        if (newFileInBtn) {
            if (type === 'folder') {
                newFileInBtn.classList.remove('hidden');
            } else {
                newFileInBtn.classList.add('hidden');
            }
        }
        if (newFolderInBtn) {
            if (type === 'folder') {
                newFolderInBtn.classList.remove('hidden');
            } else {
                newFolderInBtn.classList.add('hidden');
            }
        }
        /* ★ END NEW */

        var pasteBtn = document.getElementById('fa-paste');
        if (pasteBtn) {
            if (clipboard.items.length > 0) {
                pasteBtn.style.opacity = '1';
                pasteBtn.style.pointerEvents = '';
            } else {
                pasteBtn.style.opacity = '0.4';
                pasteBtn.style.pointerEvents = 'none';
            }
        }

        openSheet('ms-file-actions', 'fa-backdrop');
    }

    /* ═══════════════════════════════════════════════════════════════
    ACTION HANDLERS
    ═══════════════════════════════════════════════════════════════ */
    function handleAction(action) {
        if (!currentTarget) return;
        closeSheet('ms-file-actions', 'fa-backdrop');

        switch (action) {
            case 'open': actionOpen(); break;
            case 'rename': actionRename(); break;
            case 'copy': actionCopy(); break;
            case 'cut': actionCut(); break;
            case 'paste': actionPaste(); break;
            case 'duplicate': actionDuplicate(); break;
            case 'export': actionExport(); break;
            case 'info': actionProperties(); break;
            case 'delete': actionDelete(); break;
            /* ★ NEW: Create inside a folder */
            case 'new-file-in': actionNewFileInFolder(); break;
            case 'new-folder-in': actionNewFolderInFolder(); break;
            /* ★ END NEW */
        }
    }

    function actionOpen() {
        if (currentTarget.type === 'folder') {
            var safe = String(currentTarget.path).replace(/\\/g, '\\\\').replace(/"/g, '\\"');
            var row = document.querySelector('.m-tree-row[data-path="' + safe + '"]');
            if (row) row.click();
        } else {
            U.emit('file:open', currentTarget.path);
            var editorTab = document.querySelector('[data-panel="editor"]');
            if (editorTab) editorTab.click();
        }
    }

    /* ★ NEW: Create a file inside the selected folder */
    function actionNewFileInFolder() {
        var folderPath = currentTarget.path;
        var nfSheet = document.getElementById('ms-new-file');
        var nfBackdrop = document.getElementById('nf-backdrop');
        var nfInput = document.getElementById('nf-input');
        var nfHint = document.getElementById('nf-path-hint');

        if (!nfSheet || !nfBackdrop) return;

        /* Pre-fill with the folder path so the user just types the filename */
        if (nfInput) {
            nfInput.value = folderPath + '/';
        }
        /* Show a hint about where the file will be created */
        if (nfHint) {
            nfHint.textContent = 'Creating inside: ' + folderPath + '/';
            nfHint.style.display = '';
        }

        openSheet('ms-new-file', 'nf-backdrop');
        setTimeout(function () {
            if (nfInput) {
                nfInput.focus();
                /* Place cursor at the end so user can start typing the filename */
                nfInput.setSelectionRange(nfInput.value.length, nfInput.value.length);
            }
        }, 300);
    }

    /* ★ NEW: Create a folder inside the selected folder */
    function actionNewFolderInFolder() {
        var folderPath = currentTarget.path;
        var nfoSheet = document.getElementById('ms-new-folder');
        var nfoBackdrop = document.getElementById('nfo-backdrop');
        var nfoInput = document.getElementById('nfo-input');
        var nfoHint = document.getElementById('nfo-path-hint');

        if (!nfoSheet || !nfoBackdrop) return;

        /* Pre-fill with the folder path so the user just types the subfolder name */
        if (nfoInput) {
            nfoInput.value = folderPath + '/';
        }
        /* Show a hint about where the folder will be created */
        if (nfoHint) {
            nfoHint.textContent = 'Creating inside: ' + folderPath + '/';
            nfoHint.style.display = '';
        }

        openSheet('ms-new-folder', 'nfo-backdrop');
        setTimeout(function () {
            if (nfoInput) {
                nfoInput.focus();
                nfoInput.setSelectionRange(nfoInput.value.length, nfoInput.value.length);
            }
        }, 300);
    }
    /* ★ END NEW */

    function actionRename() {
        var input = document.getElementById('rn-input');
        if (input) {
            input.value = currentTarget.name;
        }
        openSheet('ms-rename', 'rn-backdrop');
        setTimeout(function () {
            if (input) {
                input.focus();
                input.select();
            }
        }, 300);
    }

    function actionCopy() {
        clipboard = {
            items: [{ path: currentTarget.path, type: currentTarget.type, name: currentTarget.name }],
            mode: 'copy'
        };
        if (U.toast) U.toast('📋 Copied: ' + currentTarget.name);
    }

    function actionCut() {
        clipboard = {
            items: [{ path: currentTarget.path, type: currentTarget.type, name: currentTarget.name }],
            mode: 'cut'
        };
        if (U.toast) U.toast('✂️ Cut: ' + currentTarget.name);
    }

    function actionPaste() {
        if (!clipboard || clipboard.items.length === 0) return;
        var destFolder;
        if (currentTarget.type === 'folder') {
            destFolder = currentTarget.path;
        } else {
            var lastSlash = currentTarget.path.lastIndexOf('/');
            destFolder = lastSlash > 0 ? currentTarget.path.substring(0, lastSlash) : '';
        }
        var items = clipboard.items;
        var mode = clipboard.mode;
        var aborted = false;   /* Discard was tapped → stop after the current item */
        var done = 0;
        /* Fresh conflict session for this batch — resets any leftover
        "apply to remaining" decision from an earlier paste/move. */
        beginConflictBatch();
        /* Items are processed ONE AT A TIME so conflict sheets never stack
        on top of each other when several items collide. */
        function processItem(i) {
            if (aborted || i >= items.length) { finish(); return; }
            var item = items[i];
            var destPath = (destFolder ? destFolder + '/' : '') + item.name;
            if (mode === 'cut' && destPath === item.path) {
                done++;
                processItem(i + 1);
                return;
            }
            resolveNameConflict(destPath, item.name, item.type, items.length - i - 1).then(function (resolution) {
                if (!resolution) { processItem(i + 1); return; }
                if (resolution.action === 'discard') { aborted = true; processItem(i + 1); return; }
                if (resolution.action === 'skip') { processItem(i + 1); return; }
                var finalPath = resolution.path || destPath;
                var op;
                if (mode === 'cut') {
                    op = api.files.rename(item.path, finalPath).then(function () {
                        if (U.emit) U.emit('file:renamed', { from: item.path, to: finalPath });
                    });
                } else {
                    op = api.files.copy(item.path, finalPath);
                }
                op.then(function () {
                    done++;
                    processItem(i + 1);
                }).catch(function (err) {
                    var msg = (err && err.message) ? err.message : String(err);
                    /* "Destination already exists" means the overwrite delete
                    didn't land — skip gracefully instead of blocking the batch */
                    if (msg.indexOf('already exists') !== -1) {
                        if (U.toast) U.toast('⚠ Skipped ' + item.name + ' — destination still occupied', 'warning');
                    } else {
                        if (U.toast) U.toast((mode === 'cut' ? 'Move failed: ' : 'Paste failed: ') + item.name + ' — ' + msg, 'error');
                    }
                    processItem(i + 1);
                });
            }).catch(function () {
                /* resolveNameConflict threw — skip this item */
                processItem(i + 1);
            });
        }
        function finish() {
            if (mode === 'cut') clipboard = { items: [], mode: 'copy' };
            if (aborted) {
                if (U.toast) {
                    U.toast(done > 0
                        ? '⚠️ Cancelled — ' + done + ' item(s) already ' + (mode === 'cut' ? 'moved' : 'pasted')
                        : (mode === 'cut' ? 'Move cancelled' : 'Paste cancelled'));
                }
            } else if (done > 0) {
                if (U.toast) U.toast((mode === 'cut' ? '📌 Moved ' : '📌 Pasted ') + done + ' item' + (done === 1 ? '' : 's'));
            }
            refreshTree();
        }
        processItem(0);
    }

    function actionDuplicate() {
        var name = currentTarget.name;
        var parentPath = currentTarget.path.substring(0, currentTarget.path.lastIndexOf('/'));

        generateUniqueName(parentPath, name, currentTarget.type).then(function (newName) {
            var destPath = (parentPath ? parentPath + '/' : '') + newName;
            api.files.copy(currentTarget.path, destPath)
                .then(function () {
                    if (U.toast) U.toast('📑 Duplicated: ' + newName);
                    refreshTree();
                })
                .catch(function (err) {
                    if (U.toast) U.toast('Duplicate failed: ' + err.message, 'error');
                });
        });
    }

    /* ★ Explorer Upgrade: Export — delegates to the workspace module so the
       sheet code stays thin. Folders are zipped server-side; the result is
       saved into Android's Downloads folder by the native DownloadListener. */
    function actionExport() {
        /* ★ STORAGE GATE: exports write to shared storage — block without permission */
        if (window.QuirkyStorage && typeof window.QuirkyStorage.hasAccess === 'function') {
            if (!window.QuirkyStorage.hasAccess()) {
                if (U.toast) U.toast('🔒 Storage permission is required to export files', 'warning');
                if (typeof window.QuirkyStorage.requestAccess === 'function') {
                    setTimeout(function () { window.QuirkyStorage.requestAccess(); }, 600);
                }
                return;
            }
        }
        var W = window.IDE && window.IDE.mobileWorkspace;
        if (W && typeof W.exportPath === 'function') {
            W.exportPath(currentTarget.path, currentTarget.type);
        } else if (U.toast) {
            U.toast('Export is unavailable right now', 'error');
        }
    }

    /* ═══════════════════════════════════════════════════════════════
    PROPERTIES / INFO POPUP
    ═══════════════════════════════════════════════════════════════ */

    /* ── Helper: escape HTML safely ── */
    function propsEsc(str) {
        if (U && U.escapeHtml) return U.escapeHtml(str);
        return String(str == null ? '' : str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    /* ── Helper: get file/folder icon ── */
    function propsIcon(name, type) {
        if (type === 'folder') return '📁';
        return (U && U.getFileIcon) ? U.getFileIcon(name) : '📄';
    }

    /* ── Helper: format timestamp ── */
    function propsDate(ts) {
        if (!ts) return '—';
        try {
            var d = new Date(ts * 1000);
            var months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            var p2 = function (n) { return (n < 10 ? '0' : '') + n; };
            return months[d.getMonth()] + ' ' + d.getDate() + ', ' + d.getFullYear() +
                ' · ' + p2(d.getHours()) + ':' + p2(d.getMinutes());
        } catch (e) { return '—'; }
    }

    /* ── Helper: time ago ── */
    function propsTimeAgo(ts) {
        if (!ts) return '';
        var diff = Math.floor(Date.now() / 1000) - ts;
        if (diff < 60) return 'just now';
        if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
        if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
        if (diff < 2592000) return Math.floor(diff / 86400) + 'd ago';
        return Math.floor(diff / 2592000) + 'mo ago';
    }

    /* ── Helper: format number with commas ── */
    function propsNum(n) {
        return String(n == null ? 0 : n).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }

    /* ── Helper: language label ── */
    function propsLang(name) {
        var L = window.IDE && window.IDE.language;
        if (L && L.byExt) {
            var ext = String(name || '').split('.').pop().toLowerCase();
            var d = L.byExt(ext);
            if (d && d.label) return d.label;
        }
        return 'Plain Text';
    }

    /* ── Helper: useful status indicators (derived, no backend call) ── */
    function propsStatusRows(info) {
        var rows = [];
        if (!info || info.type === 'folder') return rows;
        var cfg = window.IDE_CONFIG || {};
        var ext = (info.extension || '').toLowerCase();
        var normPath = String(info.path || '').replace(/^\/+/, '');
        /* Is this file currently open in an editor tab? */
        var isOpen = false;
        if (window.IDE.mobileEditor && window.IDE.mobileEditor.getTabs) {
            var tabs = window.IDE.mobileEditor.getTabs();
            for (var i = 0; i < tabs.length; i++) {
                if (String(tabs[i].path || '').replace(/^\/+/, '') === normPath) {
                    isOpen = true;
                    break;
                }
            }
        }
        rows.push(propsRow('Open in editor', isOpen ? '✓ Yes' : '✗ No'));
        /* Can this file be shown in the live preview? */
        var previewable = cfg.previewable || ['html', 'htm', 'php', 'svg', 'md'];
        var canPreview = previewable.indexOf(ext) !== -1;
        rows.push(propsRow('Previewable', canPreview ? '✓ Yes' : '✗ No'));
        /* Can this file be run in the terminal? */
        var runners = cfg.runners || {};
        var canRun = !!runners[ext];
        rows.push(propsRow('Runnable', canRun ? '✓ Yes (.' + propsEsc(ext) + ')' : '✗ No'));
        return rows;
    }

    /* ── Helper: build a property row ── */
    function propsRow(label, valueHtml) {
        return '<div class="m-props-row">' +
            '<span class="m-props-label">' + propsEsc(label) + '</span>' +
            '<span class="m-props-value">' + valueHtml + '</span>' +
            '</div>';
    }

    /* ── Main: open Properties sheet ── */
    function actionProperties() {
        var path = currentTarget.path;
        var type = currentTarget.type;
        var name = currentTarget.name;

        openSheet('ms-properties', 'pr-backdrop');

        var iconEl = document.getElementById('pr-icon');
        var nameEl = document.getElementById('pr-name');
        var subEl = document.getElementById('pr-sub');
        var basicV = document.getElementById('pr-view-basic');
        var advV = document.getElementById('pr-view-advanced');

        if (iconEl) iconEl.innerHTML = propsIcon(name, type);
        if (nameEl) nameEl.textContent = name;
        if (subEl) subEl.textContent = 'Loading…';
        if (basicV) basicV.innerHTML = '<div class="m-props-loading">Loading…</div>';
        if (advV) advV.innerHTML = '';

        if (!api || !api.files || !api.files.getInfo) {
            if (basicV) basicV.innerHTML = '<div class="m-props-loading">⚠ API not ready</div>';
            return;
        }

        api.files.getInfo(path)
            .then(function (info) {
                if (!info) {
                    if (basicV) basicV.innerHTML = '<div class="m-props-loading">⚠ No info available</div>';
                    return;
                }
                renderProperties(info, type);
            })
            .catch(function (err) {
                var msg = (err && err.message) ? err.message : 'Failed to load';
                if (basicV) basicV.innerHTML = '<div class="m-props-loading">⚠ ' + propsEsc(msg) + '</div>';
            });
    }

    /* ── Render all property rows ── */
    function renderProperties(info, type) {
        var basicV = document.getElementById('pr-view-basic');
        var advV = document.getElementById('pr-view-advanced');
        var subEl = document.getElementById('pr-sub');
        if (!basicV || !advV) return;

        /* ── Subtitle ── */
        var subParts = [];
        if (info.type === 'folder') {
            subParts.push('Folder');
        } else {
            subParts.push((info.extension || '').toUpperCase() || 'FILE');
            if (info.sizeHuman) subParts.push(info.sizeHuman);
        }
        if (subEl) subEl.textContent = subParts.join(' · ');

        /* ── Basic view ── */
        var bRows = [];
        bRows.push(propsRow('Type',
            info.type === 'folder'
                ? '📁 Folder'
                : propsIcon(info.name || '', 'file') + ' ' +
                ((info.extension || '').toUpperCase() ? (info.extension.toUpperCase() + ' File') : 'File')
        ));
        if (info.type !== 'folder' && info.sizeHuman) {
            bRows.push(propsRow('Size', propsEsc(info.sizeHuman)));
        }
        if (info.modifiedTs) {
            bRows.push(propsRow('Modified',
                propsEsc(propsDate(info.modifiedTs)) +
                ' <span class="m-props-ago">(' + propsTimeAgo(info.modifiedTs) + ')</span>'
            ));
        }
        bRows.push(propsRow('Location',
            info.parent ? 'workspace/' + propsEsc(info.parent) : 'workspace root'
        ));
        if (info.type === 'folder') {
            bRows.push(propsRow('Contents',
                propsNum(info.fileCount || 0) + ' files, ' +
                propsNum(info.folderCount || 0) + ' folders'
            ));
        }
        basicV.innerHTML = bRows.join('');

        /* ── Advanced view ── */
        var aRows = [];
        aRows.push(propsRow('Full path', 'workspace/' + propsEsc(info.path || '')));
        aRows.push(propsRow('Extension', info.extension ? '.' + propsEsc(info.extension) : '—'));
        if (info.type !== 'folder') {
            aRows.push(propsRow('Language', propsEsc(propsLang(info.name || ''))));
            /* Useful, at-a-glance status indicators */
            var statusRows = propsStatusRows(info);
            for (var si = 0; si < statusRows.length; si++) aRows.push(statusRows[si]);
        }
        if (info.size != null) {
            aRows.push(propsRow('Size (bytes)', propsNum(info.size) + ' bytes'));
        }
        if (info.lines != null) aRows.push(propsRow('Lines', propsNum(info.lines)));
        if (info.words != null) aRows.push(propsRow('Words', propsNum(info.words)));
        if (info.chars != null) aRows.push(propsRow('Characters', propsNum(info.chars)));
        if (info.createdTs) aRows.push(propsRow('Created', propsEsc(propsDate(info.createdTs))));
        if (info.accessedTs) aRows.push(propsRow('Last accessed', propsEsc(propsDate(info.accessedTs))));
        aRows.push(propsRow('Permissions',
            propsEsc(info.permissionsHuman || '—') +
            ' <span class="m-props-mono">(' + propsEsc(info.permissions || '?') + ')</span>'
        ));
        if (info.readable != null) aRows.push(propsRow('Readable', info.readable ? '✓ Yes' : '✗ No'));
        if (info.writable != null) aRows.push(propsRow('Writable', info.writable ? '✓ Yes' : '✗ No'));
        aRows.push(propsRow('Depth', (info.depth || 0) + ' level(s) deep'));
        advV.innerHTML = aRows.join('');
    }

    function actionDelete() {
        var msgEl = document.getElementById('dl-msg');
        if (msgEl) {
            if (currentTarget.type === 'folder') {
                msgEl.textContent = 'Delete folder "' + currentTarget.name + '" and ALL its contents? This cannot be undone.';
            } else {
                msgEl.textContent = 'Delete "' + currentTarget.name + '"? This cannot be undone.';
            }
        }
        openSheet('ms-delete', 'dl-backdrop');
    }

    function confirmDelete() {
        closeSheet('ms-delete', 'dl-backdrop');
        var promise;
        if (currentTarget.type === 'folder') {
            promise = api.files.deleteFolder(currentTarget.path);
        } else {
            promise = api.files.delete(currentTarget.path);
        }
        promise
            .then(function () {
                if (U.toast) U.toast('🗑️ Deleted: ' + currentTarget.name);
                U.emit('file:deleted', currentTarget.path);
                refreshTree();
            })
            .catch(function (err) {
                if (U.toast) U.toast('Delete failed: ' + err.message, 'error');
            });
    }

    function confirmRename() {
        var input = document.getElementById('rn-input');
        if (!input) return;
        var newName = input.value.trim();
        if (!newName || newName === currentTarget.name) {
            closeSheet('ms-rename', 'rn-backdrop');
            return;
        }
        var parentPath = currentTarget.path.substring(0, currentTarget.path.lastIndexOf('/'));
        var newPath = (parentPath ? parentPath + '/' : '') + newName;

        api.files.rename(currentTarget.path, newPath)
            .then(function () {
                if (U.toast) U.toast('✏️ Renamed: ' + currentTarget.name + ' → ' + newName);
                U.emit('file:renamed', { from: currentTarget.path, to: newPath });
                closeSheet('ms-rename', 'rn-backdrop');
                refreshTree();
            })
            .catch(function (err) {
                if (U.toast) U.toast('Rename failed: ' + err.message, 'error');
            });
    }

    /* ═══════════════════════════════════════════════════════════════
    NAME-CONFLICT RESOLUTION
    ═══════════════════════════════════════════════════════════════ */
    function getInfoOrNull(path) {
        return api.files.getInfo(path)
            .then(function (info) { return info; })
            .catch(function () { return null; });
    }

    function checkExists(path) {
        return getInfoOrNull(path).then(function (info) { return info !== null; });
    }

    function generateUniqueName(parentPath, name, type) {
        var base = (parentPath ? parentPath + '/' : '');
        var namePart = name;
        var extPart = '';
        /* Preserve file extension — only for actual files, not folders.
        Normalise type to guard against stale/wrong values from the DOM. */
        var isFile = (type !== 'folder');
        if (isFile) {
            var dotIdx = name.lastIndexOf('.');
            if (dotIdx > 0) {
                namePart = name.substring(0, dotIdx);
                extPart = name.substring(dotIdx);
            }
        }
        /* Numbered naming: "folder" → "folder (1)" → "folder (2)" …
        Files keep their extension: "style.css" → "style (1).css".
        If the name already ends in " (n)", keep counting from there so
        repeated conflicts stay clean: "folder (1)" → "folder (2)". */
        var strip = namePart.match(/^(.*) \((\d+)\)$/);
        var counter = 1;
        if (strip) {
            namePart = strip[1];
            counter = parseInt(strip[2], 10) + 1;
        }
        function candidate() {
            return namePart + ' (' + counter + ')' + extPart;
        }
        function tryNext() {
            if (counter > 500) return candidate();   /* safety valve */
            return checkExists(base + candidate()).then(function (exists) {
                if (!exists) return candidate();
                counter++;
                return tryNext();
            });
        }
        return tryNext();
    }

    /* ═══════════════════════════════════════════════════════════════
    BATCH MEMORY — "do the same for the rest"
    ═══════════════════════════════════════════════════════════════
    When several items are pasted / moved at once and more than one
    clashes, the sheet offers a toggle: "Apply this choice to the
    remaining N item(s)". Tick it, pick Overwrite / Rename / Skip
    once, and every later conflict in the SAME batch is answered the
    same way without another popup. The toggle starts OFF on every
    sheet, so Skip stays a one-file choice unless you explicitly opt
    in. beginConflictBatch() resets the memory at the start of each
    paste / drag-move batch.                                       */

    /* The remembered decision:
       null = ask every time · 'overwrite' | 'rename' | 'skip' = apply silently */
    var _bulkDecision = null;

    /**
     * Reset the bulk decision. Call at the START of every batch
     * (paste, drag-move) so an old "apply to all" never leaks into
     * a new operation.
     */
    function beginConflictBatch() {
        _bulkDecision = null;
    }

    function showConflictSheet(fileName, proposedName, remainingCount) {
        return new Promise(function (resolve) {
            var sheet = document.getElementById('ms-conflict');
            var backdrop = document.getElementById('cf-backdrop');
            var msgEl = document.getElementById('cf-msg');
            var btnOverwrite = document.getElementById('cf-overwrite');
            var btnRename = document.getElementById('cf-rename');
            var btnSkip = document.getElementById('cf-skip');
            var btnDiscard = document.getElementById('cf-discard');
            var bulkToggle = document.getElementById('cf-bulk-toggle');
            var bulkCheck = document.getElementById('cf-bulk-check');
            var bulkCount = document.getElementById('cf-bulk-count');
            var renameLabel = btnRename ? btnRename.querySelector('.sr-label') : null;
            var handle = sheet ? sheet.querySelector('.m-sheet-handle') : null;

            if (!sheet || !backdrop || !btnOverwrite || !btnRename || !btnSkip || !btnDiscard) {
                resolve({ choice: 'skip', applyToRemaining: false });
                return;
            }

            if (msgEl) {
                msgEl.textContent = '"' + fileName + '" already exists at the destination.';
            }

            /* Show the exact numbered name up front, so you know what you'll get */
            if (renameLabel) {
                renameLabel.textContent = 'Rename — keep both as "' + proposedName + '"';
            }

            /* ── "Apply to remaining" toggle — only shown when more items follow ── */
            var applyToRemaining = false;
            var hasRemaining = (typeof remainingCount === 'number' && remainingCount > 0);

            function onBulkToggle() {
                applyToRemaining = !applyToRemaining;
                if (bulkToggle) bulkToggle.classList.toggle('active', applyToRemaining);
                if (bulkCheck) bulkCheck.textContent = applyToRemaining ? '☑' : '☐';
                haptic();
            }

            if (bulkToggle) {
                if (hasRemaining) {
                    bulkToggle.classList.remove('hidden');
                    bulkToggle.classList.remove('active');
                    if (bulkCheck) bulkCheck.textContent = '☐';
                    if (bulkCount) bulkCount.textContent = String(remainingCount);
                } else {
                    bulkToggle.classList.add('hidden');
                }
            }

            function done(choice) {
                btnOverwrite.removeEventListener('click', onOverwrite);
                btnRename.removeEventListener('click', onRename);
                btnSkip.removeEventListener('click', onSkip);
                btnDiscard.removeEventListener('click', onDiscard);
                backdrop.removeEventListener('click', onDismiss);
                if (handle) handle.removeEventListener('click', onDismiss);
                if (bulkToggle) bulkToggle.removeEventListener('click', onBulkToggle);
                closeSheet('ms-conflict', 'cf-backdrop');
                resolve({
                    choice: choice,
                    /* a full cancel can never become the "rest of batch" answer */
                    applyToRemaining: applyToRemaining && choice !== 'discard'
                });
            }

            function onOverwrite() { done('overwrite'); }
            function onRename() { done('rename'); }
            function onSkip() { done('skip'); }
            function onDiscard() { done('discard'); }
            function onDismiss() { done('discard'); }

            btnOverwrite.addEventListener('click', onOverwrite);
            btnRename.addEventListener('click', onRename);
            btnSkip.addEventListener('click', onSkip);
            btnDiscard.addEventListener('click', onDiscard);
            backdrop.addEventListener('click', onDismiss);
            if (handle) handle.addEventListener('click', onDismiss);
            if (bulkToggle && hasRemaining) bulkToggle.addEventListener('click', onBulkToggle);

            openSheet('ms-conflict', 'cf-backdrop');
        });
    }

    /**
    * Turn one choice ('overwrite' | 'rename' | 'skip' | 'discard') into the
    * resolution object the batch loops understand. Split out so the
    * remembered bulk decision and the interactive answer share one path.
    */
    function applyConflictChoice(choice, destPath, itemName, itemType, existing, renamedPath) {
        /* Discard = cancel the whole operation */
        if (choice === 'discard') {
            return Promise.resolve({ action: 'discard' });
        }
        /* Skip = just this item, keep going with the rest */
        if (choice === 'skip') {
            return Promise.resolve({ action: 'skip' });
        }
        /* Overwrite = delete the existing item and take its place */
        if (choice === 'overwrite') {
            /* Use the BACKEND-reported type (existing.type) — never the
            client-side itemType which may be stale or wrong. */
            var isFolder = (existing && existing.type === 'folder');
            var deletePromise = isFolder
                ? api.files.deleteFolder(destPath)
                : api.files.delete(destPath);
            return deletePromise
                .then(function () {
                    return { action: 'overwrite', path: destPath };
                })
                .catch(function (err) {
                    var msg = (err && err.message) ? err.message : 'unknown error';
                    /* If it's already gone (404), treat as success */
                    if (msg.indexOf('not found') !== -1 || msg.indexOf('404') !== -1) {
                        return { action: 'overwrite', path: destPath };
                    }
                    if (U.toast) U.toast('Could not replace "' + itemName + '": ' + msg, 'error');
                    return { action: 'skip' };
                });
        }
        /* Rename = keep both, numbered copy */
        if (choice === 'rename') {
            return Promise.resolve({ action: 'rename', path: renamedPath });
        }
        /* Unknown choice — treat as skip for safety */
        return Promise.resolve({ action: 'skip' });
    }

    /**
    * Check whether the destination already exists; if it does, ask the user
    * (or apply the remembered bulk decision).
    * Resolves to one of:
    *   { action: 'proceed',   path }   → no conflict, use this path
    *   { action: 'overwrite', path }   → existing item deleted, use this path
    *   { action: 'rename',    path }   → use the numbered path instead
    *   { action: 'skip' }              → skip this item, continue the batch
    *   { action: 'discard' }           → cancel the entire paste/move
    *
    * @param {number} [remainingCount=0] How many items still follow in this
    *                 batch. When > 0 the sheet shows the "apply this choice
    *                 to the remaining items" toggle.
    */
    function resolveNameConflict(destPath, itemName, itemType, remainingCount) {
        return getInfoOrNull(destPath).then(function (existing) {
            /* No conflict → proceed as-is (a bulk decision stays armed for
            the next REAL conflict). */
            if (!existing) {
                return { action: 'proceed', path: destPath };
            }
            var lastSlash = destPath.lastIndexOf('/');
            var parentPath = lastSlash > 0 ? destPath.substring(0, lastSlash) : '';
            /* Normalise itemType: the backend is the source of truth for the
            EXISTING item's type; the caller's itemType may be stale. */
            var effectiveType = itemType;
            if (!effectiveType || (effectiveType !== 'file' && effectiveType !== 'folder')) {
                effectiveType = 'file';
            }
            /* Remembered overwrite / skip answers need no numbered name —
            answer instantly without the uniqueness scan. */
            if (_bulkDecision === 'overwrite' || _bulkDecision === 'skip') {
                return applyConflictChoice(_bulkDecision, destPath, itemName, effectiveType, existing, destPath);
            }
            /* Pre-compute the numbered name (needed for "rename", and shown
            on the sheet either way). Use the CORRECT type so extensions
            are preserved for files. */
            return generateUniqueName(parentPath, itemName, effectiveType).then(function (newName) {
                var newPath = (parentPath ? parentPath + '/' : '') + newName;
                /* Remembered rename answer (the numbered name was just built). */
                if (_bulkDecision === 'rename') {
                    return applyConflictChoice(_bulkDecision, destPath, itemName, effectiveType, existing, newPath);
                }
                var remaining = (typeof remainingCount === 'number') ? remainingCount : 0;
                return showConflictSheet(itemName, newName, remaining).then(function (result) {
                    if (!result || !result.choice) {
                        return { action: 'skip' };
                    }
                    var choice = result.choice;
                    if (result.applyToRemaining &&
                        (choice === 'overwrite' || choice === 'rename' || choice === 'skip')) {
                        _bulkDecision = choice;
                    }
                    return applyConflictChoice(choice, destPath, itemName, effectiveType, existing, newPath);
                });
            }).catch(function () {
                /* generateUniqueName failed — fall back to showing the sheet
                with a simple numbered suffix */
                var fallbackName = itemName + ' (1)';
                var fallbackPath = (parentPath ? parentPath + '/' : '') + fallbackName;
                var remaining = (typeof remainingCount === 'number') ? remainingCount : 0;
                return showConflictSheet(itemName, fallbackName, remaining).then(function (result) {
                    if (!result || !result.choice) return { action: 'skip' };
                    return applyConflictChoice(result.choice, destPath, itemName, effectiveType, existing, fallbackPath);
                });
            });
        }).catch(function () {
            /* getInfoOrNull threw unexpectedly — assume no conflict */
            return { action: 'proceed', path: destPath };
        });
    }

    /* ═══════════════════════════════════════════════════════════════
    HELPERS
    ═══════════════════════════════════════════════════════════════ */
    function refreshTree() {
        U.emit('filetree:refresh');
    }

    /* ═══════════════════════════════════════════════════════════════
    IMPROVED ERROR MESSAGES (Task 8.6)
    ═══════════════════════════════════════════════════════════════
    Categorizes errors into user-friendly messages and provides
    retry buttons where a retry is likely to succeed (network
    hiccups, transient lock errors, etc.).                         */

    /**
    * Categorize an error message into a user-friendly string.
    *
    * @param {string} context - What was being attempted ('save', 'load', 'delete', etc.)
    * @param {string} errMessage - The raw error message from the API
    * @returns {string} A friendly, contextual message
    */
    function categorizeError(context, errMessage) {
        var msg = (errMessage || '').toLowerCase();

        /* Network / connectivity */
        if (msg.indexOf('fetch') !== -1 || msg.indexOf('network') !== -1 ||
            msg.indexOf('failed to fetch') !== -1 || msg.indexOf('load failed') !== -1) {
            return 'Connection lost. Check your network and try again.';
        }

        /* Auth / session expired */
        if (msg.indexOf('authentication') !== -1 || msg.indexOf('401') !== -1 ||
            msg.indexOf('log in') !== -1) {
            return 'Session expired. Reload the page to log in again.';
        }

        /* File not found */
        if (msg.indexOf('not found') !== -1 || msg.indexOf('404') !== -1) {
            if (context === 'save') return 'The folder for this file no longer exists.';
            return 'File not found. It may have been deleted or renamed.';
        }

        /* File too large */
        if (msg.indexOf('too large') !== -1) {
            return 'File is too large to edit in the IDE.';
        }

        /* Permission / access denied */
        if (msg.indexOf('denied') !== -1 || msg.indexOf('403') !== -1 ||
            msg.indexOf('not allowed') !== -1) {
            return 'Access denied. This file type or path is restricted.';
        }

        /* Workspace escape */
        if (msg.indexOf('outside') !== -1 || msg.indexOf('escape') !== -1 ||
            msg.indexOf('navigate') !== -1) {
            return 'That path is outside the workspace and cannot be accessed.';
        }

        /* Already exists (conflict) */
        if (msg.indexOf('already exists') !== -1) {
            return 'A file or folder with that name already exists.';
        }

        /* Server error */
        if (msg.indexOf('server error') !== -1 || msg.indexOf('500') !== -1) {
            return 'The server hit a problem. Try again in a moment.';
        }

        /* Context-specific fallbacks */
        switch (context) {
            case 'save':
                return 'Save failed: ' + (errMessage || 'unknown error');
            case 'load':
                return 'Couldn\'t load files: ' + (errMessage || 'unknown error');
            case 'delete':
                return 'Delete failed: ' + (errMessage || 'unknown error');
            case 'rename':
                return 'Rename failed: ' + (errMessage || 'unknown error');
            case 'copy':
                return 'Copy failed: ' + (errMessage || 'unknown error');
            case 'create':
                return 'Create failed: ' + (errMessage || 'unknown error');
            default:
                return errMessage || 'Something went wrong.';
        }
    }

    /**
    * Determine if a retry is likely to help for this error.
    *
    * @param {string} errMessage - The raw error message
    * @returns {boolean} True if a retry button should be shown
    */
    function isRetryable(errMessage) {
        var msg = (errMessage || '').toLowerCase();
        return (
            msg.indexOf('fetch') !== -1 ||
            msg.indexOf('network') !== -1 ||
            msg.indexOf('failed to fetch') !== -1 ||
            msg.indexOf('server error') !== -1 ||
            msg.indexOf('500') !== -1 ||
            msg.indexOf('timeout') !== -1 ||
            msg.indexOf('load failed') !== -1
        );
    }

    /**
    * Show an error toast with an optional retry button.
    *
    * @param {string} friendlyMessage - The user-friendly error message
    * @param {Function|null} retryFn - If provided, a "Retry" button calls this
    */
    function showErrorToast(friendlyMessage, retryFn) {
        var toastEl = document.getElementById('toast');
        if (!toastEl) {
            if (U && U.toast) U.toast('⚠ ' + friendlyMessage);
            return;
        }

        /* Clear existing timers */
        if (window._mErrToastTimer) clearTimeout(window._mErrToastTimer);
        if (window._mErrCleanupTimer) clearTimeout(window._mErrCleanupTimer);

        toastEl.classList.remove('show', 'toast-success', 'toast-warning', 'toast-info', 'm-toast-has-action');
        toastEl.classList.add('toast-error');
        toastEl.innerHTML = '';

        var msgSpan = document.createElement('span');
        msgSpan.className = 'm-toast-msg';
        msgSpan.textContent = '⚠ ' + friendlyMessage;
        toastEl.appendChild(msgSpan);

        if (retryFn && typeof retryFn === 'function') {
            var retryBtn = document.createElement('button');
            retryBtn.className = 'm-toast-action';
            retryBtn.textContent = 'Retry';
            retryBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                if (window._mErrToastTimer) clearTimeout(window._mErrToastTimer);
                hideErrorToast();
                retryFn();
            });
            toastEl.appendChild(retryBtn);
            toastEl.classList.add('m-toast-has-action');
        }

        void toastEl.offsetWidth;
        toastEl.classList.add('show');

        window._mErrToastTimer = setTimeout(function () {
            hideErrorToast();
        }, retryFn ? 5000 : 3500);
    }

    function hideErrorToast() {
        var toastEl = document.getElementById('toast');
        if (!toastEl) return;
        toastEl.classList.remove('show');
        if (window._mErrCleanupTimer) clearTimeout(window._mErrCleanupTimer);
        window._mErrCleanupTimer = setTimeout(function () {
            if (toastEl.classList.contains('m-toast-has-action')) {
                toastEl.innerHTML = '';
                toastEl.classList.remove('m-toast-has-action');
            }
            window._mErrCleanupTimer = null;
        }, 300);
    }

    /**
    * All-in-one: categorize, show, and optionally attach retry.
    *
    * @param {string} context - What was being attempted
    * @param {string} errMessage - Raw error message
    * @param {Function|null} retryFn - Optional retry callback
    */
    function showError(context, errMessage, retryFn) {
        var friendly = categorizeError(context, errMessage);
        var canRetry = isRetryable(errMessage) && typeof retryFn === 'function';
        showErrorToast(friendly, canRetry ? retryFn : null);
    }

    /* ═══════════════════════════════════════════════════════════════
    WIRING
    ═══════════════════════════════════════════════════════════════ */
    function wire() {
        var actionSheet = document.getElementById('ms-file-actions');
        if (actionSheet) {
            actionSheet.querySelectorAll('.m-sheet-row[data-action]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    handleAction(btn.getAttribute('data-action'));
                });
            });
        }

        var faBackdrop = document.getElementById('fa-backdrop');
        if (faBackdrop) {
            faBackdrop.addEventListener('click', function () {
                closeSheet('ms-file-actions', 'fa-backdrop');
            });
        }

        var rnBackdrop = document.getElementById('rn-backdrop');
        if (rnBackdrop) {
            rnBackdrop.addEventListener('click', function () {
                closeSheet('ms-rename', 'rn-backdrop');
            });
        }

        var dlBackdrop = document.getElementById('dl-backdrop');
        if (dlBackdrop) {
            dlBackdrop.addEventListener('click', function () {
                closeSheet('ms-delete', 'dl-backdrop');
            });
        }

        var sheets = [
            ['ms-file-actions', 'fa-backdrop'],
            ['ms-rename', 'rn-backdrop'],
            ['ms-delete', 'dl-backdrop']
        ];
        sheets.forEach(function (pair) {
            var sheet = document.getElementById(pair[0]);
            if (sheet) {
                var handle = sheet.querySelector('.m-sheet-handle');
                if (handle) {
                    handle.addEventListener('click', function () {
                        closeSheet(pair[0], pair[1]);
                    });
                }
            }
        });

        var rnConfirm = document.getElementById('rn-confirm');
        if (rnConfirm) rnConfirm.addEventListener('click', confirmRename);
        var rnCancel = document.getElementById('rn-cancel');
        if (rnCancel) rnCancel.addEventListener('click', function () {
            closeSheet('ms-rename', 'rn-backdrop');
        });
        var rnInput = document.getElementById('rn-input');
        if (rnInput) {
            rnInput.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    confirmRename();
                }
                if (e.key === 'Escape') {
                    closeSheet('ms-rename', 'rn-backdrop');
                }
            });
        }

        var dlConfirm = document.getElementById('dl-confirm');
        if (dlConfirm) dlConfirm.addEventListener('click', confirmDelete);
        var dlCancel = document.getElementById('dl-cancel');
        if (dlCancel) dlCancel.addEventListener('click', function () {
            closeSheet('ms-delete', 'dl-backdrop');
        });

        Sheets.initDragToDismiss('ms-file-actions', 'fa-backdrop');
        Sheets.initDragToDismiss('ms-rename', 'rn-backdrop');
        Sheets.initDragToDismiss('ms-delete', 'dl-backdrop');

        initLongPress();
    }

    /* ═══════════════════════════════════════════════════════════════
    BOOT
    ═══════════════════════════════════════════════════════════════ */
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', wire);
    } else {
        wire();
    }

    /* ═══════════════════════════════════════════════════════════════
    EXPOSE
    ═══════════════════════════════════════════════════════════════ */
    window.IDE = window.IDE || {};
    window.IDE.mobileActions = {
        refreshTree: refreshTree,
        haptic: haptic,
        getClipboard: function () { return clipboard; },
        setClipboard: function (item) { clipboard = item; },
        /* Task 8.9: set clipboard with multiple items */
        setClipboardMulti: function (items, mode) {
            clipboard = { items: items, mode: mode };
        },
        getCurrentTarget: function () { return currentTarget; },
        setCurrentTarget: function (target) { currentTarget = target; },
        /* Task 8.6: improved error utilities */
        categorizeError: categorizeError,
        isRetryable: isRetryable,
        showError: showError,
        showErrorToast: showErrorToast,
        /* Conflict resolution — shared with mobile-filetree.js drag-and-drop */
        resolveNameConflict: resolveNameConflict,
        /* Start a fresh conflict batch (resets "apply to remaining") */
        beginConflictBatch: beginConflictBatch
    };
})();