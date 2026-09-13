/**
* ═══════════════════════════════════════════════════════════════════════════
*  QUIRKY IDE — MOBILE FILE TREE MODULE (Phase 7 · Task 7.8)
*  Updated: Lazy loading + Watch poller + Pinned files + Sort/Hidden tools
* ═══════════════════════════════════════════════════════════════════════════
*
*  Everything related to the Files panel on mobile:
*    • Recursive touch-friendly tree rendering (48px rows, chevrons, icons)
*    • Folder expand/collapse with persistence (localStorage)
*    • Pull-to-refresh (64px threshold, resistance, spinner preview)
*    • FAB wiring: New File / New Folder / New Project (template wizard)
*    • ★ Task 8.3: File tree search/filter
*    • ★ Task 8.4: Recent files list (collapsible)
*    • ★ Drag-and-drop to move files/folders into other folders
*    • ★ LAZY TREE: workspaces above EAGER_NODE_LIMIT load top-level only;
*      folders flagged unloaded:true fetch ONE level on first expand
*    • ★ WATCH POLLER: polls api.files.watch() on IDE_CONFIG.watchIntervalMs,
*      compares fingerprints and emits 'filetree:refresh' +
*      'filetree:external-change' for mutations made outside the IDE
*    • ★ PINNED FILES: localStorage-backed section above Recent Files
*    • ★ SORT + HIDDEN: Name/Size/Newest cycle chip + dotfiles eye toggle
*
*  EXPOSES: window.IDE.mobileFileTree
* ═══════════════════════════════════════════════════════════════════════════
*/
(function () {
  'use strict';

  var U = window.IDE && window.IDE.utils;
  var api = window.IDE && window.IDE.api;
  if (!U || !api) return;

  var treeContainer = document.getElementById('m-file-tree');
  if (!treeContainer) return;

  /* ═══════════════════════════════════════════════════════════════
  DOM REFERENCES + STATE
  ═══════════════════════════════════════════════════════════════ */
  var fab = document.getElementById('m-fab');
  var niSheet = document.getElementById('ms-new-item');
  var niBackdrop = document.getElementById('ni-backdrop');
  var nfSheet = document.getElementById('ms-new-file');
  var nfBackdrop = document.getElementById('nf-backdrop');
  var nfoSheet = document.getElementById('ms-new-folder');
  var nfoBackdrop = document.getElementById('nfo-backdrop');
  var npSheet = document.getElementById('ms-new-project');
  var npBackdrop = document.getElementById('np-backdrop');
  var fileNameEl = document.getElementById('mh-file');

  var LS_TREE_EXPANDED = 'quirky.ide.mobile.tree.expanded';
  var LS_RECENT = 'quirky.ide.mobile.recent.files';
  var LS_RECENT_COLLAPSED = 'quirky.ide.mobile.recent.collapsed';
  var RECENT_MAX = 5;

  /* ── Lazy tree + view prefs + pinned (Explorer enhancement) ── */
  var LS_PINNED = 'quirky.ide.mobile.tree.pinned';   // array of paths, max 12
  var LS_TREE_SORT = 'quirky.ide.mobile.tree.sort';   // SORT_MODES[].id
  var PINNED_MAX = 12;
  var EAGER_NODE_LIMIT = 600;   // total nodes above which lazy loading kicks in
  var LAZY_FETCH_DEPTH = 1;     // levels fetched per expand (single level)
  /* ★ SORT DROPDOWN: six modes with explicit direction.
     Each entry: { id, icon, label, sort, dir }
     sort + dir are sent to the server as ?sort=…&dir=…              */
  var SORT_MODES = [
    { id: 'mtime-desc', icon: '🕒', label: 'Newest', sort: 'mtime', dir: 'desc' },
    { id: 'mtime-asc', icon: '🕒', label: 'Oldest', sort: 'mtime', dir: 'asc' },
    { id: 'name-asc', icon: '🔤', label: 'Name A–Z', sort: 'name', dir: 'asc' },
    { id: 'name-desc', icon: '🔤', label: 'Name Z–A', sort: 'name', dir: 'desc' },
    { id: 'size-desc', icon: '📦', label: 'Size ↓', sort: 'size', dir: 'desc' },
    { id: 'size-asc', icon: '📦', label: 'Size ↑', sort: 'size', dir: 'asc' }
  ];
  var treeLoaded = false;
  var expandedFolders = new Set();
  var selectedTemplate = null;
  var wired = false;
  var loadPending = Promise.resolve();   // resolves when the current full load finishes

  var treeDataCache = null;
  var filterAllExpanded = false;

  /* ★ LAZY TREE state */
  var lazyMode = false;         // true once this workspace exceeded the limit
  var loadingFolders = {};      // path → in-flight Promise for a lazy fetch

  /* ★ VIEW PREFS state (sort options + dotfiles) */
  var treeSortMode = 'name-asc';   // one of SORT_MODES[].id

  /* ★ PINNED state */
  var pinnedPaths = [];         // newest first

  /* ★ WATCH POLLER state */
  var watchStarted = false;
  var lastFingerprint = null;   // null = re-baseline on next tick (no emit)

  /* ── Workspace switcher state (Task 8.10) ── */
  var LS_SELECTED_PROJECT = 'quirky.ide.mobile.workspace.project';
  var selectedProject = '';    // '' = "All Projects"
  var topLevelFolders = [];    // cached list of top-level folder names

  /* ── Multi-select state (Task 8.9) ── */
  var selectionMode = false;
  var selectedPaths = new Set();
  var selectionAnchor = null; // Tracks the start point for range selections
  var selectionBarEl = document.getElementById('m-selection-bar');
  var selectionCountEl = document.getElementById('msel-count');

  /* ── Drag-and-drop state (Gesture Revamp: drags the whole selection) ── */
  var dragState = {
    active: false,          // currently dragging
    armed: false,           // long-press fired, waiting for move or release
    items: null,            // selected items being dragged [{path,type,name}]
    ghostEl: null,          // the floating ghost element
    startX: 0,
    startY: 0,
    dropTarget: null,       // the folder row currently highlighted
    dropTargetPath: null
  };

  /* ── Click suppression (Gesture Revamp) ─────────────────────────────
     After a long-press fires (drag arm), the browser still fires a
     synthetic click on release. Left alone, that click would toggle
     the selection right back. Gestures call suppressNextClick() and
     the capture listener below eats the click.                      */
  var clickSuppressed = false;

  function suppressNextClick() {
    clickSuppressed = true;
    setTimeout(function () { clickSuppressed = false; }, 400);
  }

  treeContainer.addEventListener('click', function (e) {
    if (clickSuppressed) {
      clickSuppressed = false;
      e.preventDefault();
      e.stopPropagation();
    }
  }, true);

  /* ═══════════════════════════════════════════════════════════════
  HELPERS
  ═══════════════════════════════════════════════════════════════ */
  function toast(msg) {
    if (U && U.toast) U.toast(msg);
  }

  function haptic() {
    if (window.IDE && window.IDE.mobileActions && window.IDE.mobileActions.haptic) {
      window.IDE.mobileActions.haptic();
      return;
    }
    try { if (navigator.vibrate) navigator.vibrate(10); } catch (e) { }
  }

  function escHtml(str) {
    if (U && U.escapeHtml) return U.escapeHtml(str);
    if (typeof str !== 'string') return '';
    return str.replace(/&/g, '&amp;').replace(/</g, '&lt;')
      .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  function getFileIcon(filename, isBinary) {
    return (U && U.getFileIcon) ? U.getFileIcon(filename, isBinary) : '📄';
  }

  function openSheet(sheet, backdrop) {
    if (!sheet || !backdrop) return;
    backdrop.classList.remove('hidden');
    sheet.classList.remove('hidden');
    void sheet.offsetWidth;
    sheet.classList.add('show');
    backdrop.classList.add('show');
  }

  function closeSheet(sheet, backdrop) {
    if (!sheet || !backdrop) return;
    sheet.classList.remove('show');
    backdrop.classList.remove('show');
    setTimeout(function () {
      sheet.classList.add('hidden');
      backdrop.classList.add('hidden');
    }, 280);
  }

  /* ═══════════════════════════════════════════════════════════════
  TREE MODEL HELPERS (lazy loading)
  ═══════════════════════════════════════════════════════════════ */
  /** Count every node (folders + files) in a nested tree. */
  function countNodes(nodes) {
    var n = 0;
    (function walk(list) {
      (list || []).forEach(function (node) {
        n++;
        if (node.type === 'folder') walk(node.children);
      });
    })(nodes);
    return n;
  }

  /** Depth-first lookup of a node by its workspace-relative path. */
  function findNodeByPath(nodes, path) {
    if (!nodes || !path) return null;
    for (var i = 0; i < nodes.length; i++) {
      var node = nodes[i];
      if (node.path === path) return node;
      if (node.type === 'folder' && node.children &&
        path.indexOf(node.path + '/') === 0) {
        var hit = findNodeByPath(node.children, path);
        if (hit) return hit;
      }
    }
    return null;
  }

  /** Escape a path for use inside an attribute CSS selector. */
  function cssEscapePath(path) {
    return String(path).replace(/\\/g, '\\\\').replace(/"/g, '\\"');
  }

  /** Find the rendered children container of a folder row (or null). */
  function findChildContainer(path) {
    if (!treeContainer || !path) return null;
    return treeContainer.querySelector(
      '.m-tree-children[data-parent="' + cssEscapePath(path) + '"]');
  }

  /** Workspace depth of a path ('' → 0, 'a/b' → 1 …). */
  function depthOfPath(path) {
    return path ? path.split('/').length - 1 : 0;
  }

  /** Replace a folder's children in the local model after a lazy fetch. */
  function spliceChildren(path, childNodes) {
    var node = findNodeByPath(treeDataCache, path);
    if (!node || node.type !== 'folder') return false;
    node.children = childNodes || [];
    delete node.unloaded;
    node.childCount = node.children.length;
    return true;
  }

  /** Mark every folder in the list as an unloaded shell (children dropped). */
  function collapseOneLevel(nodes) {
    (nodes || []).forEach(function (node) {
      if (node.type !== 'folder') return;
      node.children = [];
      node.unloaded = true;
    });
  }

  /**
   * Shrink a too-large workspace to something renderable: every top-level
   * folder becomes an unloaded shell — except inside the ACTIVE project,
   * which keeps one level so the project switcher never shows an empty tree.
   */
  function collapseForLazyDisplay(treeData) {
    treeData.forEach(function (node) {
      if (node.type !== 'folder') return;
      if (selectedProject && node.name === selectedProject) {
        collapseOneLevel(node.children);
      } else {
        node.children = [];
        node.unloaded = true;
      }
    });
  }

  /* ═══════════════════════════════════════════════════════════════
  VIEW PREFS (sort mode + hidden dotfiles)
  ═══════════════════════════════════════════════════════════════ */
  function loadViewPrefs() {
    try {
      var s = localStorage.getItem(LS_TREE_SORT);
      /* Accept both new IDs ('name-asc') and legacy values ('name') */
      if (s) {
        var found = false;
        for (var i = 0; i < SORT_MODES.length; i++) {
          if (SORT_MODES[i].id === s) { found = true; break; }
        }
        if (found) treeSortMode = s;
        else if (s === 'name') treeSortMode = 'name-asc';
        else if (s === 'size') treeSortMode = 'size-desc';
        else if (s === 'mtime') treeSortMode = 'mtime-desc';
      }
      /* Dotfiles are ALWAYS visible. '.git' is blocked server-side
         via config blocked_segments — no client toggle needed. */
    } catch (e) { }
  }

  function saveViewPrefs() {
    try {
      localStorage.setItem(LS_TREE_SORT, treeSortMode);
    } catch (e) { }
  }

  /**
   * Build the ?api=tree query params for the CURRENT view preferences.
   * @param {string|null} path - Sub-folder for lazy fetches (null/'' = root)
   */
  function buildTreeParams(path) {
    var params = {};
    if (path) params.path = path;
    var mode = getSortMode();
    if (mode && mode.sort !== 'name') params.sort = mode.sort;
    if (mode && mode.dir !== 'asc') params.dir = mode.dir;
    /* Dotfiles are ALWAYS visible. '.git' NEVER appears — it is a
       blocked segment in config.php and is filtered server-side
       BEFORE the hidden flag is even evaluated. */
    params.hidden = '1';
    return params;
  }

  /** Resolve the current treeSortMode ID to its full mode object. */
  function getSortMode() {
    for (var i = 0; i < SORT_MODES.length; i++) {
      if (SORT_MODES[i].id === treeSortMode) return SORT_MODES[i];
    }
    return SORT_MODES[2]; /* fallback: name-asc */
  }

  /* ═══════════════════════════════════════════════════════════════
  EXPANDED FOLDER PERSISTENCE
  ═══════════════════════════════════════════════════════════════ */
  function loadExpandedFolders() {
    try {
      var raw = localStorage.getItem(LS_TREE_EXPANDED);
      if (raw) {
        var arr = JSON.parse(raw);
        if (Array.isArray(arr)) {
          arr.forEach(function (p) { expandedFolders.add(p); });
        }
      }
    } catch (e) { }
  }

  function saveExpandedFolders() {
    try {
      localStorage.setItem(LS_TREE_EXPANDED, JSON.stringify(Array.from(expandedFolders)));
    } catch (e) { }
  }

  /* ═══════════════════════════════════════════════════════════════
  TREE RENDERING
  ═══════════════════════════════════════════════════════════════ */
  function renderTreeNodes(nodes, container, depth) {
    if (!nodes || nodes.length === 0) return;
    nodes.forEach(function (node) {
      var indent = (depth * 20) + 12;
      if (node.type === 'folder') {
        /* ★ LAZY TREE: an unloaded folder never renders as expanded —
           its children don't exist in the model yet. */
        var isExpanded = !node.unloaded && expandedFolders.has(node.path);
        var row = document.createElement('div');
        var rowClasses = 'm-tree-row m-tree-folder' + (isExpanded ? ' expanded' : '');
        /* ★ PHASE 3: add project class for styling */
        if (node.isProject) rowClasses += ' m-tree-project';
        row.className = rowClasses;
        row.setAttribute('data-path', node.path);
        row.setAttribute('data-type', 'folder');
        row.setAttribute('data-depth', String(depth));
        row.style.paddingLeft = indent + 'px';

        /* ★ PHASE 3: project badge */
        var projectBadgeHtml = '';
        if (node.isProject) {
          var projLabel = node.projectName || node.name;
          projectBadgeHtml = '<span class="m-tree-project-badge" title="Project: ' + escHtml(projLabel) + '">🔧</span>';
        }

        row.innerHTML =
          '<span class="m-tree-chevron">' + (isExpanded ? '▾' : '▸') + '</span>' +
          '<span class="m-tree-icon">' + (isExpanded ? '📂' : '📁') + '</span>' +
          '<span class="m-tree-name">' + escHtml(node.name) + '</span>' +
          projectBadgeHtml;

        container.appendChild(row);

        var childContainer = document.createElement('div');
        childContainer.className = 'm-tree-children' + (isExpanded ? '' : ' hidden');
        childContainer.setAttribute('data-parent', node.path);
        container.appendChild(childContainer);

        if (isExpanded && node.children && node.children.length > 0) {
          renderTreeNodes(node.children, childContainer, depth + 1);
        }

        row.addEventListener('click', function () {
          /* In selection mode, tap toggles selection */
          if (selectionMode) {
            toggleSelection(node.path);
            return;
          }
          handleFolderTap(node.path, row, childContainer);
        });
      } else {
        var row = document.createElement('div');
        row.className = 'm-tree-row m-tree-file';
        row.setAttribute('data-path', node.path);
        row.setAttribute('data-type', 'file');
        row.setAttribute('data-depth', String(depth));
        row.style.paddingLeft = indent + 'px';
        row.innerHTML =
          '<span class="m-tree-icon">' + getFileIcon(node.name, node.binary) + '</span>' +
          '<span class="m-tree-name">' + escHtml(node.name) + '</span>';
        container.appendChild(row);
        row.addEventListener('click', function () {
          /* In selection mode, tap toggles selection */
          if (selectionMode) {
            toggleSelection(node.path);
            return;
          }
          if (U && U.emit) U.emit('file:open', node.path);
          if (fileNameEl) fileNameEl.textContent = node.name;
          if (typeof window._mobileSwitchTab === 'function') window._mobileSwitchTab('editor');
        });
      }
    });
  }

  function renderAllNodes(nodes, container, depth) {
    if (!nodes || nodes.length === 0) return;
    nodes.forEach(function (node) {
      var indent = (depth * 20) + 12;
      if (node.type === 'folder') {
        /* ★ LAZY TREE: unloaded folders render collapsed even when the
           persisted state says "expanded" — they have no children yet. */
        var isExpanded = !node.unloaded && expandedFolders.has(node.path);
        var row = document.createElement('div');
        var rowClasses = 'm-tree-row m-tree-folder' + (isExpanded ? ' expanded' : '');
        /* ★ PHASE 3: add project class for styling */
        if (node.isProject) rowClasses += ' m-tree-project';
        row.className = rowClasses;
        row.setAttribute('data-path', node.path);
        row.setAttribute('data-type', 'folder');
        row.setAttribute('data-depth', String(depth));
        row.style.paddingLeft = indent + 'px';

        /* ★ PHASE 3: project badge */
        var projectBadgeHtml = '';
        if (node.isProject) {
          var projLabel = node.projectName || node.name;
          projectBadgeHtml = '<span class="m-tree-project-badge" title="Project: ' + escHtml(projLabel) + '">🔧</span>';
        }

        row.innerHTML =
          '<span class="m-tree-chevron">' + (isExpanded ? '▾' : '▸') + '</span>' +
          '<span class="m-tree-icon">' + (isExpanded ? '📂' : '📁') + '</span>' +
          '<span class="m-tree-name">' + escHtml(node.name) + '</span>' +
          projectBadgeHtml;

        container.appendChild(row);

        var childContainer = document.createElement('div');
        childContainer.className = 'm-tree-children' + (isExpanded ? '' : ' hidden');
        childContainer.setAttribute('data-parent', node.path);
        container.appendChild(childContainer);

        if (node.children && node.children.length > 0) {
          renderAllNodes(node.children, childContainer, depth + 1);
        }

        row.addEventListener('click', function () {
          handleFolderTap(node.path, row, childContainer);
        });
      } else {
        var row = document.createElement('div');
        row.className = 'm-tree-row m-tree-file';
        row.setAttribute('data-path', node.path);
        row.setAttribute('data-type', 'file');
        row.setAttribute('data-depth', String(depth));
        row.style.paddingLeft = indent + 'px';
        row.innerHTML =
          '<span class="m-tree-icon">' + getFileIcon(node.name, node.binary) + '</span>' +
          '<span class="m-tree-name">' + escHtml(node.name) + '</span>';
        container.appendChild(row);
        row.addEventListener('click', function () {
          if (U && U.emit) U.emit('file:open', node.path);
          if (typeof window._mobileSwitchTab === 'function') window._mobileSwitchTab('editor');
        });
      }
    });
  }

  /* Re-applies the correct left indent to every row from its data-depth.
     Runs after each render so indents can never "stick" at the wrong
     position, no matter what touched the rows before. */
  function fixIndents() {
    if (!treeContainer) return;
    var rows = treeContainer.querySelectorAll('.m-tree-row[data-depth]');
    for (var i = 0; i < rows.length; i++) {
      var d = parseInt(rows[i].getAttribute('data-depth'), 10) || 0;
      rows[i].style.paddingLeft = ((d * 20) + 12) + 'px';
    }
  }

  /** Sync a folder row's chevron + icon with an expanded/collapsed state. */
  function setFolderRowVisual(rowEl, isExpanded) {
    var chevron = rowEl.querySelector('.m-tree-chevron');
    var icon = rowEl.querySelector('.m-tree-icon');
    if (chevron) chevron.textContent = isExpanded ? '▾' : '▸';
    if (icon) icon.textContent = isExpanded ? '📂' : '📁';
  }

  /**
   * Folder tap → expand or collapse. On EXPAND of an unloaded folder
   * (lazy tree), one level of children is fetched from the server first.
   * Always reads the FRESH node out of the model — render-time closures
   * would go stale after lazy splices mutate treeDataCache.
   */
  function handleFolderTap(path, rowEl, childContainer) {
    if (expandedFolders.has(path)) {
      /* ── Collapse ── */
      expandedFolders.delete(path);
      rowEl.classList.remove('expanded');
      childContainer.classList.add('hidden');
      setFolderRowVisual(rowEl, false);
    } else {
      /* ── Expand ── */
      expandedFolders.add(path);
      rowEl.classList.add('expanded');
      childContainer.classList.remove('hidden');
      setFolderRowVisual(rowEl, true);
      saveExpandedFolders();

      var node = findNodeByPath(treeDataCache, path);
      var depth = parseInt(rowEl.getAttribute('data-depth') || '0', 10);
      if (node && node.unloaded) {
        /* ★ LAZY TREE: fetch this level on demand */
        loadChildren(path, childContainer, depth + 1)
          .catch(function () { /* error UI already rendered by loadChildren */ });
      } else if (childContainer.children.length === 0 &&
        node && node.children && node.children.length > 0) {
        renderTreeNodes(node.children, childContainer, depth + 1);
      }
    }
  }

  /**
   * Fetch ONE level of an unloaded folder and splice it into the model,
   * then render it into the folder's child container when that container
   * is still attached. Resolves with the fetched children so callers can
   * await it via ensureLoaded() before operating on the subtree.
   */
  function loadChildren(path, childContainer, childDepth) {
    if (!path) return Promise.resolve([]);
    if (loadingFolders[path]) return loadingFolders[path];

    if (childContainer && childContainer.isConnected) {
      var wait = document.createElement('div');
      wait.className = 'm-tree-row';
      wait.style.cssText =
        'padding-left:' + ((childDepth * 20) + 12) + 'px;font-size:.78rem;' +
        'color:var(--muted,#7186a5);opacity:.7;min-height:36px;display:flex;align-items:center;';
      wait.textContent = 'Loading…';
      childContainer.innerHTML = '';
      childContainer.appendChild(wait);
    }

    var p = api.files.tree(buildTreeParams(path)).then(function (response) {
      delete loadingFolders[path];
      var kids = (response && response.tree) || [];
      spliceChildren(path, kids);
      if (childContainer && childContainer.isConnected) {
        childContainer.innerHTML = '';
        if (kids.length > 0) {
          renderTreeNodes(kids, childContainer, childDepth);
        }
        fixIndents();
        updateOpenFileIndicators();
        /* A live filter must re-apply to the freshly rendered rows */
        if (searchInput && searchInput.value.trim()) filterTree(searchInput.value);
      }
      return kids;
    }).catch(function (err) {
      delete loadingFolders[path];
      if (childContainer && childContainer.isConnected) {
        childContainer.innerHTML = '';
        var msg = document.createElement('div');
        msg.className = 'm-tree-row';
        msg.style.cssText =
          'padding-left:' + ((childDepth * 20) + 12) + 'px;font-size:.78rem;' +
          'color:var(--danger,#f87171);min-height:36px;display:flex;align-items:center;';
        msg.textContent = '⚠️ Could not load folder';
        var retry = document.createElement('button');
        retry.className = 'm-tree-search-clear';
        retry.style.cssText = 'flex-shrink:0;width:auto;padding:4px 10px;height:auto;' +
          'border:1px solid var(--border,#263450);border-radius:6px;color:var(--accent,#f5a524);font-size:.72rem;';
        retry.textContent = 'Retry';
        retry.addEventListener('click', function (e) {
          e.stopPropagation();
          loadChildren(path, childContainer, childDepth)
            .catch(function () { });
        });
        childContainer.appendChild(msg);
        childContainer.appendChild(retry);
      }
      throw err;
    });
    loadingFolders[path] = p;
    return p;
  }

  /**
   * ★ OP GUARD — resolves once `path`'s children exist in the local model.
   * Root ('') just makes sure the tree itself has loaded; loaded/eager
   * regions resolve immediately; unloaded folders trigger the same lazy
   * fetch an expansion uses. Drag-drop and other bulk operations call
   * this before touching a region that may never have been fetched.
   */
  function ensureLoaded(path) {
    path = path || '';
    if (!treeLoaded) {
      loadExpandedFolders();
      loadFileTree();
      if (treeToolsVisible) {
        renderRecentFiles();
        renderPinnedFiles();
      }
      if (!path) return Promise.resolve();
      return loadPending.then(function () { return ensureLoadedChild(path); });
    }
    if (!path) return Promise.resolve();
    return ensureLoadedChild(path);
  }

  function ensureLoadedChild(path) {
    var node = findNodeByPath(treeDataCache, path);
    if (!node || node.type !== 'folder' || !node.unloaded) return Promise.resolve();
    return loadChildren(
      path,
      findChildContainer(path),
      depthOfPath(path) + 1
    ).then(function () { });
  }

  /* ═══════════════════════════════════════════════════════════════
     OPEN IN EDITOR INDICATORS (Pulsing "E" Badge)
     ═══════════════════════════════════════════════════════════════ */
  function updateOpenFileIndicators() {
    if (!treeContainer) return;
    var openPaths = new Set();

    // Get all currently open tabs from the editor module
    if (window.IDE && window.IDE.mobileEditor && window.IDE.mobileEditor.getTabs) {
      var tabs = window.IDE.mobileEditor.getTabs();
      for (var i = 0; i < tabs.length; i++) {
        openPaths.add(tabs[i].path);
      }
    }

    // Scan all rendered tree rows and add/remove the badge
    var rows = treeContainer.querySelectorAll('.m-tree-row');
    rows.forEach(function (row) {
      var path = row.getAttribute('data-path');
      var badge = row.querySelector('.m-tree-open-badge');

      if (openPaths.has(path)) {
        if (!badge) {
          badge = document.createElement('span');
          badge.className = 'm-tree-open-badge';
          badge.textContent = 'E';
          badge.title = 'Open in editor';
          row.appendChild(badge);
        }
      } else {
        if (badge) badge.remove();
      }
    });
  }

  function loadFileTree() {
    if (!treeContainer) return Promise.resolve();
    if (!api || !api.files) {
      treeContainer.innerHTML = '<div class="m-tree-error">API not ready.</div>';
      return Promise.resolve();
    }
    var skeletonEl = document.getElementById('m-tree-loading');
    if (skeletonEl) {
      skeletonEl.classList.remove('hidden');
      treeContainer.innerHTML = '';
      treeContainer.appendChild(skeletonEl);
    } else {
      treeContainer.innerHTML = '<div class="m-tree-loading">Loading workspace…</div>';
    }
    loadPending = api.files.tree(buildTreeParams(null))
      .then(function (response) {
        var treeData = response.tree || response;

        /* ── ★ LAZY TREE: huge workspaces collapse to top-level shells.
           The full scan is still used to decide — the client counts the
           nodes and, above EAGER_NODE_LIMIT, keeps only what fits the
           first screen; deeper folders fetch on first expand. ── */
        var totalNodes = countNodes(treeData);
        lazyMode = totalNodes > EAGER_NODE_LIMIT;
        if (lazyMode) collapseForLazyDisplay(treeData);

        treeDataCache = treeData;
        filterAllExpanded = false;

        /* ── Task 8.10: Detect multiple projects ── */
        topLevelFolders = [];
        if (treeData && treeData.length > 0) {
          for (var i = 0; i < treeData.length; i++) {
            if (treeData[i].type === 'folder') {
              topLevelFolders.push(treeData[i].name);
            }
          }
        }
        renderWorkspaceSwitcher();

        /* ── Filter tree based on selected project ── */
        var filteredData = filterTreeByProject(treeData);

        treeContainer.innerHTML = '';
        if (!filteredData || filteredData.length === 0) {
          treeContainer.innerHTML = '<div class="m-tree-empty">' +
            '<div class="m-tree-empty-icon">📂</div>' +
            '<p>Workspace is empty.</p>' +
            '<p class="m-tree-empty-sub">Use the ＋ button to create files.</p>' +
            '</div>';
          treeLoaded = true;
          startWatchPoller();
          U.emit('filetree:updated');
          return;
        }
        renderTreeNodes(filteredData, treeContainer, 0);
        fixIndents();
        updateOpenFileIndicators(); // Apply "E" badges
        treeLoaded = true;
        /* ★ WATCH POLLER: armed after the FIRST successful tree load */
        startWatchPoller();
        /* ★ PALETTE SYNERGY: announce the fresh model so consumers can
           reseed from it instead of refetching the whole workspace. */
        U.emit('filetree:updated');
      })
      .catch(function (err) {
        /* Task 8.6: contextual error with retry button */
        var Actions = window.IDE.mobileActions;
        var friendly = err.message || 'Unknown error';
        if (Actions && Actions.categorizeError) {
          friendly = Actions.categorizeError('load', err.message);
        }
        var retryHtml = '';
        var canRetry = Actions && Actions.isRetryable && Actions.isRetryable(err.message);
        if (canRetry) {
          retryHtml = '<button class="m-tree-retry-btn" id="m-tree-retry">🔄 Retry</button>';
        }
        treeContainer.innerHTML = '<div class="m-tree-error">' +
          '<div class="m-tree-error-icon">⚠️</div>' +
          '<p>' + escHtml(friendly) + '</p>' +
          retryHtml +
          '</div>';
        if (canRetry) {
          var retryBtn = document.getElementById('m-tree-retry');
          if (retryBtn) {
            retryBtn.addEventListener('click', function () {
              loadFileTree();
            });
          }
        }
      });
  }

  function refreshFileTree() {
    treeLoaded = false;
    /* Our own reload must not read as an "external" change on the next
       watch tick — drop the baseline so the poller just re-anchors. */
    lastFingerprint = null;
    return loadFileTree();
  }

  /* ═══════════════════════════════════════════════════════════════
  PULL-TO-REFRESH (Task 2.4 · gesture fix)
  ═══════════════════════════════════════════════════════════════
  Pull straight down from the very top of the tree to reload it.
  
  GESTURE FIX: before doing anything, PTR now decides which AXIS the
  gesture is moving on. A horizontal swipe (file selection) aborts PTR
  immediately — even if the finger drifts a little downward — so a
  left/right swipe can never accidentally trigger a refresh. PTR only
  fires on a genuine top-to-bottom (vertical, downward) pull.
  ═══════════════════════════════════════════════════════════════ */
  var ptrEl = document.getElementById('m-ptr');
  var ptrLabel = ptrEl ? ptrEl.querySelector('.m-ptr-label') : null;
  var ptrSpinner = ptrEl ? ptrEl.querySelector('.m-ptr-spinner') : null;
  var PTR_THRESHOLD = 64;
  var PTR_MAX = 96;
  var PTR_AXIS_TOLERANCE = 12;  /* px of movement before we lock the direction */
  var ptrStartY = null;
  var ptrStartX = null;
  var ptrAxis = null;           /* null = undecided · 'v' = vertical pull · 'h' = horizontal (abort) */
  var ptrPulling = false;
  var ptrRefreshing = false;

  function ptrSetHeight(px, animate) {
    if (!ptrEl) return;
    ptrEl.style.transition = animate ? 'height 0.2s ease' : 'none';
    ptrEl.style.height = px + 'px';
  }

  function ptrReset() {
    ptrSetHeight(0, true);
    if (ptrEl) ptrEl.classList.remove('refreshing');
    if (ptrSpinner) ptrSpinner.style.transform = '';
    if (ptrLabel) ptrLabel.textContent = 'Pull to refresh';
    ptrRefreshing = false;
  }

  /* Cleanly abandon the current gesture and restore the bar. */
  function ptrAbort() {
    ptrStartY = null;
    ptrStartX = null;
    ptrAxis = null;
    ptrPulling = false;
    ptrSetHeight(0, false);
    if (ptrSpinner) ptrSpinner.style.transform = '';
    if (ptrLabel) ptrLabel.textContent = 'Pull to refresh';
  }

  if (ptrEl && treeContainer) {
    treeContainer.addEventListener('touchstart', function (e) {
      if (ptrRefreshing) return;
      if (treeContainer.scrollTop > 0) return;
      if (document.querySelector('.m-sheet.show')) return;
      /* Don't compete with long-press drag either */
      if (dragState.active || dragState.armed) return;
      ptrStartY = e.touches[0].clientY;
      ptrStartX = e.touches[0].clientX;
      ptrAxis = null;
      ptrPulling = false;
    }, { passive: true });

    treeContainer.addEventListener('touchmove', function (e) {
      if (ptrStartY === null || ptrRefreshing) return;
      if (dragState.active || dragState.armed) { ptrAbort(); return; }
      if (treeContainer.scrollTop > 0) { ptrAbort(); return; }

      var dy = e.touches[0].clientY - ptrStartY;
      var dx = e.touches[0].clientX - ptrStartX;

      /* Decide the gesture axis once, on the first real movement. */
      if (ptrAxis === null) {
        var ax = Math.abs(dx), ay = Math.abs(dy);
        if (ax > PTR_AXIS_TOLERANCE && ax > ay) {
          ptrAxis = 'h';   /* horizontal swipe → NOT pull-to-refresh */
        } else if (ay > PTR_AXIS_TOLERANCE && dy > 0 && ay > ax) {
          ptrAxis = 'v';   /* genuine downward pull → pull-to-refresh */
        }
      }

      /* Anything that isn't a clear vertical-down pull aborts PTR. */
      if (ptrAxis !== 'v') {
        if (ptrAxis === 'h') ptrAbort();
        return;
      }

      if (dy <= 0) {
        ptrSetHeight(0, false);
        return;
      }
      if (e.cancelable) e.preventDefault();
      var pull = Math.min(dy * 0.5, PTR_MAX);
      ptrPulling = pull >= PTR_THRESHOLD;
      ptrSetHeight(pull, false);
      if (ptrLabel) ptrLabel.textContent = ptrPulling ? 'Release to refresh' : 'Pull to refresh';
      if (ptrSpinner) ptrSpinner.style.transform = 'rotate(' + Math.round(dy * 3) + 'deg)';
    }, { passive: false });

    function ptrTouchEnd() {
      if (ptrStartY === null) return;   /* aborted or never tracked */
      var wasPulling = ptrPulling;
      var axis = ptrAxis;
      ptrStartY = null;
      ptrStartX = null;
      ptrAxis = null;
      ptrPulling = false;
      if (ptrRefreshing) return;
      /* Only refresh when it really was a vertical pull past the threshold */
      if (wasPulling && axis === 'v') {
        ptrRefreshing = true;
        ptrEl.classList.add('refreshing');
        if (ptrLabel) ptrLabel.textContent = 'Refreshing…';
        if (ptrSpinner) ptrSpinner.style.transform = '';
        ptrSetHeight(48, true);
        Promise.resolve(loadFileTree()).then(function () {
          setTimeout(ptrReset, 350);
        });
      } else {
        ptrSetHeight(0, true);
        if (ptrSpinner) ptrSpinner.style.transform = '';
      }
    }
    treeContainer.addEventListener('touchend', ptrTouchEnd);
    treeContainer.addEventListener('touchcancel', ptrTouchEnd);
  }

  /* ═══════════════════════════════════════════════════════════════
  DRAG-AND-DROP (Gesture Revamp)
  ═══════════════════════════════════════════════════════════════
  How it works now:
  1. Only items that are ALREADY SELECTED can be dragged.
  2. Long-press (500ms) a SELECTED item → "arms" it (visual glow).
     (Long-pressing an UNSELECTED item does nothing here — that
     shows the helpers via mobile-actions.js instead.)
  3. If you MOVE after arming → drag starts, ghost follows finger.
  4. Drag over a folder → the folder highlights as a drop target.
  5. Release over a folder → ALL selected items move there.
  6. Release anywhere else → drag cancels (selection stays).
  7. Release without moving → nothing happens (selection stays).
  ═══════════════════════════════════════════════════════════════ */

  /* Swipe bridge — called by mobile-swipe.js when a row is swiped.
     Swipe = select: the first swipe opens selection mode, later
     swipes (and taps) toggle items in and out of the selection.  */
  function toggleSelectBySwipe(path) {
    if (selectionMode) {
      rangeSelect(path); // Swipe while in selection mode = Range Select
    } else {
      enterSelectionMode(path);
    }
  }

  /* Select all visible items between the anchor and the target */
  function rangeSelect(targetPath) {
    if (!selectionAnchor) {
      toggleSelection(targetPath);
      return;
    }

    var rows = treeContainer.querySelectorAll('.m-tree-row');
    var anchorIndex = -1;
    var targetIndex = -1;

    for (var i = 0; i < rows.length; i++) {
      var p = rows[i].getAttribute('data-path');
      if (p === selectionAnchor) anchorIndex = i;
      if (p === targetPath) targetIndex = i;
    }

    if (anchorIndex !== -1 && targetIndex !== -1) {
      var start = Math.min(anchorIndex, targetIndex);
      var end = Math.max(anchorIndex, targetIndex);

      selectedPaths.clear();
      for (var i = start; i <= end; i++) {
        var p = rows[i].getAttribute('data-path');
        if (p) selectedPaths.add(p);
      }
      updateSelectionVisuals();
      updateSelectionBarCount();
    } else {
      if (!selectedPaths.has(targetPath)) {
        selectedPaths.add(targetPath);
      }
      updateSelectionVisuals();
      updateSelectionBarCount();
    }
  }

  function initDragAndDrop() {
    var limits = (window.IDE_CONFIG && window.IDE_CONFIG.deviceLimits) || {};
    if (limits.gestures === false) return; // ★ LOCKDOWN

    if (!treeContainer) return;

    var ARM_DELAY = 500;
    var MOVE_THRESHOLD = 12;

    var armTimer = null;
    var touchRow = null;

    treeContainer.addEventListener('touchstart', function (e) {
      var row = e.target.closest('.m-tree-row');
      if (!row) return;

      var path = row.getAttribute('data-path');
      /* ★ Drag only starts from items that are already selected.
         Unselected items belong to the helpers (mobile-actions.js). */
      if (!path || !selectedPaths.has(path)) return;

      touchRow = row;
      dragState.startX = e.touches[0].clientX;
      dragState.startY = e.touches[0].clientY;
      dragState.armed = false;
      dragState.active = false;

      armTimer = setTimeout(function () {
        dragState.armed = true;
        dragState.items = getSelectedItems();
        row.classList.add('m-drag-armed');
        haptic();
        /* The release after a long-press still fires a click —
           swallow it so the selection isn't toggled back. */
        suppressNextClick();
      }, ARM_DELAY);
    }, { passive: true });

    treeContainer.addEventListener('touchmove', function (e) {
      if (!touchRow) return;

      var dx = e.touches[0].clientX - dragState.startX;
      var dy = e.touches[0].clientY - dragState.startY;
      var dist = Math.sqrt(dx * dx + dy * dy);

      if (dragState.armed && !dragState.active && dist > MOVE_THRESHOLD) {
        /* Start dragging ALL selected items */
        dragState.active = true;
        touchRow.classList.remove('m-drag-armed');
        touchRow.classList.add('m-drag-source');
        createGhost(e.touches[0].clientX, e.touches[0].clientY);
        if (armTimer) { clearTimeout(armTimer); armTimer = null; }
      }

      if (dragState.active) {
        if (e.cancelable) e.preventDefault();
        moveGhost(e.touches[0].clientX, e.touches[0].clientY);
        updateDropTarget(e.touches[0].clientX, e.touches[0].clientY);
      } else if (!dragState.armed && dist > 10) {
        /* Plain scrolling — cancel the arm timer */
        if (armTimer) { clearTimeout(armTimer); armTimer = null; }
        touchRow = null;
      }
    }, { passive: false });

    treeContainer.addEventListener('touchend', function () {
      if (armTimer) { clearTimeout(armTimer); armTimer = null; }
      if (dragState.active) {
        /* Drop or cancel */
        finishDrag();
      }
      /* Armed but didn't move: nothing happens — the items stay selected. */
      if (touchRow) touchRow.classList.remove('m-drag-armed');
      touchRow = null;
      dragState.armed = false;
    }, { passive: true });

    treeContainer.addEventListener('touchcancel', function () {
      if (armTimer) { clearTimeout(armTimer); armTimer = null; }
      if (dragState.active) finishDrag();
      if (touchRow) touchRow.classList.remove('m-drag-armed');
      touchRow = null;
      dragState.armed = false;
      dragState.active = false;
    }, { passive: true });
  }

  function createGhost(x, y) {
    var items = dragState.items || [];
    var first = items[0] || { name: '' };
    var label = first.name;
    if (items.length > 1) label += '  +' + (items.length - 1);

    var ghost = document.createElement('div');
    ghost.className = 'm-drag-ghost';
    ghost.innerHTML =
      '<span class="m-drag-ghost-icon">' + getFileIcon(first.name) + '</span>' +
      '<span class="m-drag-ghost-name">' + escHtml(label) + '</span>';
    document.body.appendChild(ghost);
    dragState.ghostEl = ghost;
    moveGhost(x, y);
  }

  function moveGhost(x, y) {
    if (!dragState.ghostEl) return;
    dragState.ghostEl.style.left = (x - 20) + 'px';
    dragState.ghostEl.style.top = (y - 20) + 'px';
  }

  function updateDropTarget(x, y) {
    var el = document.elementFromPoint(x, y);
    if (!el) { clearDropTarget(); return; }

    var row = el.closest('.m-tree-row');

    /* ── Folder row: drop INTO that folder ── */
    if (row && row.getAttribute('data-type') === 'folder' && isValidDrop(row)) {
      var path = row.getAttribute('data-path');
      if (dragState.dropTarget !== row) {
        clearDropTarget();
        dragState.dropTarget = row;
        dragState.dropTargetPath = path;
        row.classList.add('m-drag-over');
      }
      return;
    }

    /* ── File row: drop into that file's PARENT directory ──
       This lets users move items to root by dropping on a root-level
       file, or to any subdirectory by dropping on a file in that dir. */
    if (row && row.getAttribute('data-type') === 'file' && isValidDropToParent(row)) {
      var filePath = row.getAttribute('data-path');
      var parentDir = getParentDir(filePath); // '' means root
      if (dragState.dropTarget !== row) {
        clearDropTarget();
        dragState.dropTarget = row;
        dragState.dropTargetPath = parentDir;
        row.classList.add('m-drag-over-file');
      }
      return;
    }

    /* ── Root drop: finger is inside the tree container but NOT on any
       row (empty space below items, or completely empty tree).
       This handles the "empty root" case where there are no rows. ── */
    if (!row && treeContainer && treeContainer.contains(el) && isValidRootDrop()) {
      if (dragState.dropTarget !== treeContainer) {
        clearDropTarget();
        dragState.dropTarget = treeContainer;
        dragState.dropTargetPath = '';
        treeContainer.classList.add('m-drag-over-root');
      }
      return;
    }

    clearDropTarget();
  }

  /** Can the dragged selection be dropped onto this folder row? */
  function isValidDrop(row) {
    var items = dragState.items || [];
    if (!items.length) return false;
    var targetPath = row.getAttribute('data-path');
    var targetType = row.getAttribute('data-type');
    if (!targetPath) return false;
    /* Only folders are valid drop targets */
    if (targetType !== 'folder') return false;
    for (var i = 0; i < items.length; i++) {
      var src = items[i];
      /* Never onto itself */
      if (targetPath === src.path) return false;
      /* Never into its own folder (or a subfolder of itself) */
      if (src.type === 'folder' && (targetPath + '/').indexOf(src.path + '/') === 0) return false;
      /* Never drop a folder onto a file inside it */
      if (src.type === 'folder' && targetPath.indexOf(src.path + '/') === 0) return false;
    }
    /* Skip when everything is already inside that folder */
    var allThere = items.every(function (src) {
      var parent = src.path.lastIndexOf('/') > 0
        ? src.path.substring(0, src.path.lastIndexOf('/'))
        : '';
      return parent === targetPath;
    });
    return !allThere;
  }

  /** Get the parent directory of a path. Returns '' for root-level items. */
  function getParentDir(path) {
    if (!path) return '';
    var lastSlash = path.lastIndexOf('/');
    return lastSlash > 0 ? path.substring(0, lastSlash) : '';
  }

  /** Can the dragged selection be dropped onto a FILE row (moving to its parent)? */
  function isValidDropToParent(row) {
    var items = dragState.items || [];
    if (!items.length) return false;

    var filePath = row.getAttribute('data-path');
    var targetParent = getParentDir(filePath);

    for (var i = 0; i < items.length; i++) {
      var src = items[i];
      /* Never drop onto itself */
      if (src.path === filePath) return false;
      /* Never drop a folder into its own subdirectory */
      if (src.type === 'folder' && filePath.indexOf(src.path + '/') === 0) return false;
    }

    /* Skip if every item is already in that parent directory */
    var allThere = items.every(function (src) {
      var srcParent = getParentDir(src.path);
      return srcParent === targetParent;
    });
    return !allThere;
  }

  /** Can the dragged selection be dropped onto the workspace root? */
  function isValidRootDrop() {
    var items = dragState.items || [];
    if (!items.length) return false;
    /* Skip if every item is already at the root level */
    var allAtRoot = items.every(function (src) {
      return src.path.indexOf('/') === -1;
    });
    if (allAtRoot) return false;
    /* Prevent dropping a folder onto the root if it's already there */
    for (var i = 0; i < items.length; i++) {
      var src = items[i];
      if (src.type === 'folder' && src.path.indexOf('/') === -1) {
        /* Folder already at root level — skip it but allow others */
        continue;
      }
    }
    return true;
  }

  function clearDropTarget() {
    if (dragState.dropTarget) {
      dragState.dropTarget.classList.remove('m-drag-over');
      dragState.dropTarget.classList.remove('m-drag-over-file');
      dragState.dropTarget.classList.remove('m-drag-over-root');
      dragState.dropTarget = null;
      dragState.dropTargetPath = null;
    }
  }

  /** Move all dragged (selected) items into the target folder.
  Name conflicts ask the user: Overwrite / Rename / Skip / Discard.
  When targetPath is '' (empty string), items move to the workspace root. */
  function finishDrag() {
    var items = dragState.items || [];
    var targetPath = dragState.dropTargetPath;
    /* Cleanup visuals */
    if (dragState.ghostEl) { dragState.ghostEl.remove(); dragState.ghostEl = null; }
    clearDropTarget();
    var sourceRow = treeContainer.querySelector('.m-tree-row.m-drag-source');
    if (sourceRow) sourceRow.classList.remove('m-drag-source');
    dragState.active = false;
    dragState.armed = false;
    dragState.items = null;
    /* ★ FIX: use strict null/undefined check so '' (root) is allowed */
    if (targetPath === null || targetPath === undefined || !items.length) return;
    var Actions = window.IDE.mobileActions;
    var moved = 0;
    var aborted = false;
    /* Fresh conflict session — an "apply to remaining" decision from an
    earlier paste/move must not leak into this drag. */
    if (Actions && Actions.beginConflictBatch) Actions.beginConflictBatch();
    /* Items move ONE AT A TIME so conflict sheets never stack. */
    function processItem(i) {
      if (aborted || i >= items.length) { finish(); return; }
      var src = items[i];
      /* ★ FIX: build destPath correctly for root (no leading slash) */
      var destPath = targetPath ? (targetPath + '/' + src.name) : src.name;
      if (destPath === src.path) { processItem(i + 1); return; }
      var remainingCount = items.length - i - 1;
      var resolutionPromise = (Actions && Actions.resolveNameConflict)
        ? Actions.resolveNameConflict(destPath, src.name, src.type, remainingCount)
        : Promise.resolve({ action: 'proceed', path: destPath });
      resolutionPromise.then(function (resolution) {
        if (!resolution) { processItem(i + 1); return; }
        if (resolution.action === 'discard') { aborted = true; processItem(i + 1); return; }
        if (resolution.action === 'skip') { processItem(i + 1); return; }
        var finalDest = resolution.path || destPath;
        api.files.rename(src.path, finalDest)
          .then(function () {
            moved++;
            U.emit('file:renamed', { from: src.path, to: finalDest });
            processItem(i + 1);
          })
          .catch(function (err) {
            var msg = (err && err.message) ? err.message : String(err);
            if (msg.indexOf('not found') !== -1 || msg.indexOf('404') !== -1) {
              processItem(i + 1);
            } else {
              toast('⚠ Move failed: ' + src.name + ' — ' + msg);
              processItem(i + 1);
            }
          });
      }).catch(function () {
        processItem(i + 1);
      });
    }
    function finish() {
      if (moved > 0) {
        /* ★ FIX: friendly label for root */
        var destLabel = targetPath ? (targetPath.split('/').pop() + '/') : 'workspace root';
        toast('📦 Moved ' + moved + ' item' + (moved > 1 ? 's' : '') + ' → ' + destLabel);
        exitSelectionMode();
        refreshFileTree();
      } else if (aborted) {
        toast('Move cancelled');
      }
    }
    /* ★ LAZY TREE OP GUARD: dropping INTO a folder that was never fetched
       loads it first, so the model matches disk before any conflict
       resolution or follow-up reads. Failures never block the move —
       the renames themselves are server-side. */
    var ready = targetPath
      ? ensureLoaded(targetPath).catch(function () { })
      : Promise.resolve();
    ready.then(function () { processItem(0); });
  }

  /* ═══════════════════════════════════════════════════════════════
  FAB + NEW FILE / FOLDER / PROJECT (Task 2.5)
  ═══════════════════════════════════════════════════════════════ */
  function loadTemplates() {
    var loadingEl = document.getElementById('np-templates-loading');
    var templatesEl = document.getElementById('np-templates');
    var nameWrap = document.getElementById('np-name-wrap');
    var confirmBtn = document.getElementById('np-confirm');
    selectedTemplate = null;
    if (loadingEl) loadingEl.classList.remove('hidden');
    if (templatesEl) templatesEl.classList.add('hidden');
    if (nameWrap) nameWrap.classList.add('hidden');
    if (confirmBtn) confirmBtn.classList.add('hidden');

    fetch('index.php?api=templates-list', { headers: { 'Accept': 'application/json' } })
      .then(function (res) { return res.json(); })
      .then(function (j) {
        if (!j.ok || !j.data || !j.data.templates) throw new Error('No templates');
        var templates = j.data.templates;
        if (loadingEl) loadingEl.classList.add('hidden');
        if (templatesEl) {
          templatesEl.innerHTML = '';
          templatesEl.classList.remove('hidden');
          templates.forEach(function (t) {
            var btn = document.createElement('button');
            btn.className = 'm-sheet-row m-template-option';
            btn.setAttribute('data-template', t.id);
            btn.innerHTML = '<span class="sr-ico">' + t.icon + '</span> <span class="sr-label">' + t.name + '</span>';
            btn.addEventListener('click', function () {
              selectedTemplate = t.id;
              templatesEl.querySelectorAll('.m-template-option').forEach(function (b) {
                b.classList.remove('selected');
              });
              btn.classList.add('selected');
              if (nameWrap) nameWrap.classList.remove('hidden');
              if (confirmBtn) confirmBtn.classList.remove('hidden');
              var nameInput = document.getElementById('np-name-input');
              if (nameInput) { nameInput.value = ''; nameInput.focus(); }
            });
            templatesEl.appendChild(btn);
          });
        }
      })
      .catch(function (err) {
        if (loadingEl) loadingEl.textContent = '⚠️ ' + err.message;
      });
  }

  function wireFab() {
    if (fab) fab.addEventListener('click', function () {
      haptic();
      /* Show/hide the storage-permission hint */
      var storageHint = document.getElementById('ni-storage-hint');
      if (storageHint && window.QuirkyStorage && typeof window.QuirkyStorage.hasAccess === 'function') {
        storageHint.style.display = window.QuirkyStorage.hasAccess() ? 'none' : '';
      }
      openSheet(niSheet, niBackdrop);
    });
    if (niBackdrop) niBackdrop.addEventListener('click', function () { closeSheet(niSheet, niBackdrop); });
    if (niSheet) {
      var niHandle = niSheet.querySelector('.m-sheet-handle');
      if (niHandle) niHandle.addEventListener('click', function () { closeSheet(niSheet, niBackdrop); });
      niSheet.querySelectorAll('.m-sheet-row').forEach(function (row) {
        row.addEventListener('click', function () {
          var action = row.getAttribute('data-action');
          closeSheet(niSheet, niBackdrop);
          if (action === 'new-file') {
            var nfHint = document.getElementById('nf-path-hint');
            if (nfHint) nfHint.style.display = 'none';
            openSheet(nfSheet, nfBackdrop);
            setTimeout(function () {
              var inp = document.getElementById('nf-input');
              if (inp) { inp.value = ''; inp.focus(); }
            }, 300);
          } else if (action === 'new-folder') {
            var nfoHint = document.getElementById('nfo-path-hint');
            if (nfoHint) nfoHint.style.display = 'none';
            openSheet(nfoSheet, nfoBackdrop);
            setTimeout(function () {
              var inp = document.getElementById('nfo-input');
              if (inp) { inp.value = ''; inp.focus(); }
            }, 300);
          } else if (action === 'new-project') {
            openSheet(npSheet, npBackdrop);
            loadTemplates();
          } else if (action === 'import-file' || action === 'import-folder') {
            /* ★ STORAGE GATE: imports read from shared storage */
            if (window.QuirkyStorage && typeof window.QuirkyStorage.hasAccess === 'function') {
              if (!window.QuirkyStorage.hasAccess()) {
                toast('🔒 Storage permission is required to import files');
                if (typeof window.QuirkyStorage.requestAccess === 'function') {
                  setTimeout(function () { window.QuirkyStorage.requestAccess(); }, 600);
                }
                return;
              }
            }
            /* Delegate to the workspace module if it owns the picker */
            var W = window.IDE && window.IDE.mobileWorkspace;
            if (W && typeof W.importPath === 'function') {
              W.importPath(action === 'import-folder' ? 'folder' : 'file');
            } else if (window.QuirkyFiles) {
              /* Fallback: call the Android bridge directly */
              if (action === 'import-file') window.QuirkyFiles.pickImportFiles();
              else window.QuirkyFiles.pickImportFolder();
            } else {
              toast('Import is unavailable right now');
            }
          }
        });
      });
    }

    var nfConfirm = document.getElementById('nf-confirm');
    var nfCancel = document.getElementById('nf-cancel');
    var nfInput = document.getElementById('nf-input');
    if (nfConfirm) nfConfirm.addEventListener('click', function () {
      var path = nfInput ? nfInput.value.trim().replace(/^\/+/, '') : '';
      if (!path) { toast('⚠️ Enter a file path'); return; }
      nfConfirm.disabled = true;
      api.files.save(path, '').then(function () {
        closeSheet(nfSheet, nfBackdrop);
        toast('📄 Created: ' + path);
        refreshFileTree();
        if (U && U.emit) U.emit('file:open', path);
      }).catch(function (err) {
        toast('⚠️ ' + err.message);
      }).finally(function () { nfConfirm.disabled = false; });
    });
    if (nfCancel) nfCancel.addEventListener('click', function () { closeSheet(nfSheet, nfBackdrop); });
    if (nfBackdrop) nfBackdrop.addEventListener('click', function () { closeSheet(nfSheet, nfBackdrop); });
    if (nfInput) nfInput.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') { e.preventDefault(); nfConfirm.click(); }
      if (e.key === 'Escape') closeSheet(nfSheet, nfBackdrop);
    });

    var nfoConfirm = document.getElementById('nfo-confirm');
    var nfoCancel = document.getElementById('nfo-cancel');
    var nfoInput = document.getElementById('nfo-input');
    if (nfoConfirm) nfoConfirm.addEventListener('click', function () {
      var path = nfoInput ? nfoInput.value.trim().replace(/^\/+/, '').replace(/\/+$/, '') : '';
      if (!path) { toast('⚠️ Enter a folder path'); return; }
      nfoConfirm.disabled = true;
      api.files.createFolder(path).then(function () {
        closeSheet(nfoSheet, nfoBackdrop);
        toast('📁 Created folder: ' + path);
        refreshFileTree();
      }).catch(function (err) {
        toast('⚠️ ' + err.message);
      }).finally(function () { nfoConfirm.disabled = false; });
    });
    if (nfoCancel) nfoCancel.addEventListener('click', function () { closeSheet(nfoSheet, nfoBackdrop); });
    if (nfoBackdrop) nfoBackdrop.addEventListener('click', function () { closeSheet(nfoSheet, nfoBackdrop); });
    if (nfoInput) nfoInput.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') { e.preventDefault(); nfoConfirm.click(); }
      if (e.key === 'Escape') closeSheet(nfoSheet, nfoBackdrop);
    });

    var npConfirm = document.getElementById('np-confirm');
    var npCancel = document.getElementById('np-cancel');
    var npNameInput = document.getElementById('np-name-input');
    if (npConfirm) npConfirm.addEventListener('click', function () {
      var name = npNameInput ? npNameInput.value.trim() : '';
      if (!selectedTemplate) { toast('⚠️ Select a template'); return; }
      if (!name) { toast('⚠️ Enter a project name'); return; }
      npConfirm.disabled = true;
      fetch('index.php?api=templates-create', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ template: selectedTemplate, name: name })
      }).then(function (res) { return res.json(); })
        .then(function (j) {
          if (!j.ok) throw new Error(j.error || 'Failed');
          closeSheet(npSheet, npBackdrop);
          toast('🚀 Created "' + j.data.name + '"');
          refreshFileTree();
        }).catch(function (err) {
          toast('⚠️ ' + err.message);
        }).finally(function () { npConfirm.disabled = false; });
    });
    if (npCancel) npCancel.addEventListener('click', function () { closeSheet(npSheet, npBackdrop); });
    if (npBackdrop) npBackdrop.addEventListener('click', function () { closeSheet(npSheet, npBackdrop); });
    if (npNameInput) npNameInput.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') { e.preventDefault(); npConfirm.click(); }
      if (e.key === 'Escape') closeSheet(npSheet, npBackdrop);
    });
  }

  /* ═══════════════════════════════════════════════════════════════
  FILE TREE SEARCH / FILTER (Task 8.3)
  ═══════════════════════════════════════════════════════════════ */
  var searchInput = document.getElementById('m-tree-search-input');
  var searchClearBtn = document.getElementById('m-tree-search-clear');
  var filterEmptyEl = document.getElementById('m-tree-filter-empty');
  var filterQueryEl = document.getElementById('m-tree-filter-query');
  var filterTimer = null;

  function ensureAllChildrenRendered() {
    if (filterAllExpanded || !treeDataCache) return;
    filterAllExpanded = true;
    treeContainer.innerHTML = '';
    renderAllNodes(treeDataCache, treeContainer, 0);
  }

  function filterTree(query) {
    query = (query || '').trim().toLowerCase();
    if (!treeContainer) return;
    /* Only filter if tree tools are visible */
    if (!treeToolsVisible) return;
    clearFilterHighlights();
    if (query === '') { clearFilter(); return; }
    ensureAllChildrenRendered();
    if (searchClearBtn) searchClearBtn.classList.remove('hidden');

    var allRows = treeContainer.querySelectorAll('.m-tree-row');
    var allChildren = treeContainer.querySelectorAll('.m-tree-children');
    var matchCount = 0;

    var matchingPaths = new Set();
    allRows.forEach(function (row) {
      var nameEl = row.querySelector('.m-tree-name');
      if (!nameEl) return;
      var name = (nameEl.textContent || '').toLowerCase();
      if (name.indexOf(query) !== -1) {
        matchingPaths.add(row.getAttribute('data-path'));
        highlightName(nameEl, query);
      }
    });

    var visiblePaths = new Set();
    matchingPaths.forEach(function (path) {
      visiblePaths.add(path);
      var safePath = String(path).replace(/\\/g, '\\\\').replace(/"/g, '\\"');
      var row = treeContainer.querySelector('.m-tree-row[data-path="' + safePath + '"]');
      if (!row) return;
      var parent = row.parentElement;
      while (parent && parent !== treeContainer) {
        if (parent.classList.contains('m-tree-children')) {
          var parentPath = parent.getAttribute('data-parent');
          if (parentPath) {
            visiblePaths.add(parentPath);
            parent.classList.remove('filter-hidden');
            parent.classList.remove('hidden');
            var safeParent = String(parentPath).replace(/\\/g, '\\\\').replace(/"/g, '\\"');
            var parentRow = treeContainer.querySelector('.m-tree-row[data-path="' + safeParent + '"]');
            if (parentRow) {
              parentRow.classList.add('expanded');
              parentRow.classList.remove('filter-hidden');
              var chevron = parentRow.querySelector('.m-tree-chevron');
              var icon = parentRow.querySelector('.m-tree-icon');
              if (chevron) chevron.textContent = '▾';
              if (icon) icon.textContent = '📂';
            }
          }
        }
        parent = parent.parentElement;
      }
    });

    allRows.forEach(function (row) {
      var path = row.getAttribute('data-path');
      if (visiblePaths.has(path)) {
        row.classList.remove('filter-hidden');
        matchCount++;
      } else {
        row.classList.add('filter-hidden');
      }
    });

    allChildren.forEach(function (container) {
      var parentPath = container.getAttribute('data-parent');
      if (parentPath && visiblePaths.has(parentPath)) {
        container.classList.remove('filter-hidden');
        container.classList.remove('hidden');
      } else {
        container.classList.add('filter-hidden');
      }
    });

    if (filterEmptyEl) {
      if (matchCount === 0) {
        filterEmptyEl.classList.remove('hidden');
        if (filterQueryEl) filterQueryEl.textContent = query;
      } else {
        filterEmptyEl.classList.add('hidden');
      }
    }
    updateOpenFileIndicators(); // Re-apply badges to visible rows
  }

  function clearFilter() {
    if (!treeContainer) return;
    treeContainer.querySelectorAll('.filter-hidden').forEach(function (el) {
      el.classList.remove('filter-hidden');
    });
    clearFilterHighlights();
    if (searchClearBtn) searchClearBtn.classList.add('hidden');
    if (filterEmptyEl) filterEmptyEl.classList.add('hidden');

    treeContainer.querySelectorAll('.m-tree-row.m-tree-folder').forEach(function (row) {
      var path = row.getAttribute('data-path');
      /* ★ LAZY TREE: an unloaded folder can't render as expanded —
         it has no children in the model yet. */
      var node = findNodeByPath(treeDataCache, path);
      var isExpanded = expandedFolders.has(path) && !(node && node.unloaded);
      var chevron = row.querySelector('.m-tree-chevron');
      var icon = row.querySelector('.m-tree-icon');
      if (isExpanded) { row.classList.add('expanded'); }
      else { row.classList.remove('expanded'); }
      if (chevron) chevron.textContent = isExpanded ? '▾' : '▸';
      if (icon) icon.textContent = isExpanded ? '📂' : '📁';
      var safePath = String(path).replace(/\\/g, '\\\\').replace(/"/g, '\\"');
      var childContainer = treeContainer.querySelector('.m-tree-children[data-parent="' + safePath + '"]');
      if (childContainer) {
        if (isExpanded) { childContainer.classList.remove('hidden'); }
        else { childContainer.classList.add('hidden'); }
      }
    });
    filterAllExpanded = false;
  }

  function clearFilterHighlights() {
    if (!treeContainer) return;
    treeContainer.querySelectorAll('.m-tree-name').forEach(function (nameEl) {
      var text = nameEl.textContent || '';
      nameEl.innerHTML = '';
      nameEl.textContent = text;
    });
  }

  function highlightName(nameEl, query) {
    var text = nameEl.textContent || '';
    var lowerText = text.toLowerCase();
    var lowerQuery = query.toLowerCase();
    var idx = lowerText.indexOf(lowerQuery);
    if (idx === -1) return;
    var before = text.substring(0, idx);
    var match = text.substring(idx, idx + query.length);
    var after = text.substring(idx + query.length);
    nameEl.innerHTML = '';
    if (before) nameEl.appendChild(document.createTextNode(before));
    var mark = document.createElement('mark');
    mark.textContent = match;
    nameEl.appendChild(mark);
    if (after) nameEl.appendChild(document.createTextNode(after));
  }

  function wireSearchFilter() {
    if (!searchInput) return;
    searchInput.addEventListener('input', function () {
      clearTimeout(filterTimer);
      var q = searchInput.value;
      filterTimer = setTimeout(function () { filterTree(q); }, 200);
    });
    searchInput.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') {
        e.preventDefault();
        searchInput.value = '';
        clearFilter();
        searchInput.blur();
      }
    });
    if (searchClearBtn) {
      searchClearBtn.addEventListener('click', function () {
        searchInput.value = '';
        clearFilter();
        searchInput.focus();
      });
    }
  }

  /* ═══════════════════════════════════════════════════════════════
  RECENT FILES (Task 8.4 — collapsible)
  ═══════════════════════════════════════════════════════════════ */
  function getRecentFiles() {
    try {
      var raw = localStorage.getItem(LS_RECENT);
      var arr = raw ? JSON.parse(raw) : [];
      if (!Array.isArray(arr)) return [];
      return arr.slice(0, RECENT_MAX);
    } catch (e) { return []; }
  }

  function saveRecentFiles(arr) {
    try { localStorage.setItem(LS_RECENT, JSON.stringify(arr.slice(0, RECENT_MAX))); } catch (e) { }
  }

  function trackRecentFile(path) {
    if (!path || typeof path !== 'string') return;
    var recents = getRecentFiles();
    var idx = recents.indexOf(path);
    if (idx !== -1) recents.splice(idx, 1);
    recents.unshift(path);
    if (recents.length > RECENT_MAX) recents.length = RECENT_MAX;
    saveRecentFiles(recents);
    renderRecentFiles();
  }

  function removeRecentFile(path) {
    var recents = getRecentFiles().filter(function (p) { return p !== path; });
    saveRecentFiles(recents);
    renderRecentFiles();
  }

  function clearRecentFiles() {
    saveRecentFiles([]);
    renderRecentFiles();
    toast('Recent files cleared');
  }

  function isRecentCollapsed() {
    try { return localStorage.getItem(LS_RECENT_COLLAPSED) === '1'; } catch (e) { return false; }
  }

  function setRecentCollapsed(collapsed) {
    try { localStorage.setItem(LS_RECENT_COLLAPSED, collapsed ? '1' : '0'); } catch (e) { }
  }

  function toggleRecentCollapsed() {
    var collapsed = !isRecentCollapsed();
    setRecentCollapsed(collapsed);
    renderRecentFiles();
  }

  function renderRecentFiles() {
    var wrap = document.getElementById('m-recent-wrap');
    var list = document.getElementById('m-recent-list');
    var countEl = document.getElementById('m-recent-count');
    var toggleBtn = document.getElementById('m-recent-toggle');
    var clearBtn = document.getElementById('m-recent-clear');
    if (!wrap || !list) return;
    /* Only render if tree tools container is visible */
    if (!treeToolsVisible) return;
    var recents = getRecentFiles();
    if (recents.length === 0) {
      wrap.style.display = 'none';
      return;
    }
    wrap.style.display = '';
    var collapsed = isRecentCollapsed();
    // Update toggle button
    if (toggleBtn) {
      toggleBtn.textContent = collapsed ? '▸' : '▾';
      toggleBtn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
    }
    // "Clear" only makes sense while the list is visible
    if (clearBtn) clearBtn.classList.toggle('hidden', collapsed);
    // Show/hide the list
    if (collapsed) {
      list.style.display = 'none';
      if (countEl) countEl.textContent = recents.length + ' file' + (recents.length === 1 ? '' : 's');
      return;
    }
    list.style.display = '';
    if (countEl) countEl.textContent = recents.length + ' file' + (recents.length === 1 ? '' : 's');
    list.innerHTML = '';
    recents.forEach(function (path) {
      var name = path.split('/').pop();
      var dir = path.indexOf('/') !== -1 ? path.substring(0, path.lastIndexOf('/')) : '';
      var row = document.createElement('div');
      row.className = 'm-recent-row';
      row.setAttribute('data-path', path);
      row.innerHTML =
        '<span class="m-recent-icon">' + getFileIcon(name) + '</span>' +
        '<span class="m-recent-name">' + escHtml(name) + '</span>' +
        (dir ? '<span class="m-recent-path">' + escHtml(dir) + '</span>' : '') +
        /* ★ PINNED: one-tap pin straight from recents */
        '<button class="m-recent-del" data-pin title="Pin file" aria-label="Pin ' + escHtml(name) + '">📌</button>' +
        '<button class="m-recent-del" title="Remove from recents" aria-label="Remove ' + escHtml(name) + ' from recents">✕</button>';
      row.addEventListener('click', function (e) {
        /* ✕ = remove just this entry (does NOT open the file) */
        if (e.target.closest('.m-recent-del[data-pin]')) {
          haptic();
          pinFile(path);
          return;
        }
        if (e.target.closest('.m-recent-del')) {
          haptic();
          removeRecentFile(path);
          toast('Removed ' + name + ' from recents');
          return;
        }
        U.emit('file:open', path);
        if (typeof window._mobileSwitchTab === 'function') {
          window._mobileSwitchTab('editor');
        }
      });
      list.appendChild(row);
    });
  }

  function wireRecentToggle() {
    var head = document.getElementById('m-recent-head');
    if (head) {
      head.addEventListener('click', function (e) {
        // The Clear button lives inside the head — don't collapse
        // the section when it is tapped.
        if (e.target.closest('.m-recent-clear')) return;
        toggleRecentCollapsed();
      });
    }
    var clearBtn = document.getElementById('m-recent-clear');
    if (clearBtn) {
      clearBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        haptic();
        clearRecentFiles();
      });
    }
  }

  /* ═══════════════════════════════════════════════════════════════
  PINNED FILES (★ Explorer enhancement)
  ═══════════════════════════════════════════════════════════════
  A localStorage-backed quick-access list ('quirky.ide.mobile.tree.pinned',
  max 12 paths) rendered ABOVE Recent Files. Tap opens, ✕ unpins. The
  list is remapped on file:renamed and pruned on file:deleted exactly
  like Recent Files. Rows reuse the .m-recent-* classes for identical
  styling.                                                        */
  function ensurePinnedSection() {
    var tools = document.getElementById('m-tree-tools');
    var recentWrap = document.getElementById('m-recent-wrap');
    if (!tools || !recentWrap) return null;
    var wrap = document.getElementById('m-pin-wrap');
    if (wrap) return wrap;
    wrap = document.createElement('div');
    wrap.id = 'm-pin-wrap';
    wrap.className = 'm-recent-wrap';   /* same section styling as recents */
    wrap.innerHTML =
      '<div class="m-recent-head" id="m-pin-head">' +
      '<span class="m-recent-title">📌 PINNED</span>' +
      '<span class="m-recent-count" id="m-pin-count"></span>' +
      '</div>' +
      '<div class="m-recent-list" id="m-pin-list"></div>';
    tools.insertBefore(wrap, recentWrap);
    return wrap;
  }

  function getPinnedPaths() {
    try {
      var raw = localStorage.getItem(LS_PINNED);
      var arr = raw ? JSON.parse(raw) : [];
      if (!Array.isArray(arr)) return [];
      return arr.filter(function (p) { return typeof p === 'string' && p; })
        .slice(0, PINNED_MAX);
    } catch (e) { return []; }
  }

  function savePinnedPaths(arr) {
    try {
      localStorage.setItem(LS_PINNED, JSON.stringify(arr.slice(0, PINNED_MAX)));
    } catch (e) { }
  }

  function loadPinnedPaths() {
    pinnedPaths = getPinnedPaths();
  }

  function pinFile(path) {
    if (!path || typeof path !== 'string') return;
    var pins = getPinnedPaths();
    var idx = pins.indexOf(path);
    if (idx !== -1) pins.splice(idx, 1);   /* re-pinning moves to top */
    pins.unshift(path);
    if (pins.length > PINNED_MAX) {
      toast('Pin limit reached (' + PINNED_MAX + ') — oldest pin removed');
      pins.length = PINNED_MAX;
    }
    savePinnedPaths(pins);
    renderPinnedFiles();
    toast('📌 Pinned ' + path.split('/').pop());
  }

  function unpinFile(path) {
    var pins = getPinnedPaths().filter(function (p) { return p !== path; });
    savePinnedPaths(pins);
    renderPinnedFiles();
  }

  function removePinnedFile(path) {
    unpinFile(path);
  }

  function renderPinnedFiles() {
    var wrap = ensurePinnedSection();
    var list = document.getElementById('m-pin-list');
    var countEl = document.getElementById('m-pin-count');
    if (!wrap || !list) return;
    /* Only render when tree tools container is visible */
    if (!treeToolsVisible) return;
    var pins = getPinnedPaths();
    if (pins.length === 0) {
      wrap.style.display = 'none';
      return;
    }
    wrap.style.display = '';
    if (countEl) countEl.textContent = pins.length + ' file' + (pins.length === 1 ? '' : 's');
    list.innerHTML = '';
    pins.forEach(function (path) {
      var name = path.split('/').pop();
      var dir = path.indexOf('/') !== -1 ? path.substring(0, path.lastIndexOf('/')) : '';
      var row = document.createElement('div');
      row.className = 'm-recent-row';
      row.setAttribute('data-path', path);
      row.innerHTML =
        '<span class="m-recent-icon">📌</span>' +
        '<span class="m-recent-name">' + escHtml(name) + '</span>' +
        (dir ? '<span class="m-recent-path">' + escHtml(dir) + '</span>' : '') +
        '<button class="m-recent-del" title="Unpin" aria-label="Unpin ' + escHtml(name) + '">✕</button>';
      row.addEventListener('click', function (e) {
        /* ✕ = unpin (does NOT open the file) */
        if (e.target.closest('.m-recent-del')) {
          haptic();
          unpinFile(path);
          toast('Unpinned ' + name);
          return;
        }
        U.emit('file:open', path);
        if (typeof window._mobileSwitchTab === 'function') {
          window._mobileSwitchTab('editor');
        }
      });
      list.appendChild(row);
    });
  }

  /* ═══════════════════════════════════════════════════════════════
  TOGGLEABLE TREE TOOLS (search icon in header)
  ═══════════════════════════════════════════════════════════════ */
  var treeToolsVisible = false;

  function toggleTreeTools() {
    treeToolsVisible = !treeToolsVisible;
    var container = document.getElementById('m-tree-tools');
    var headerBtn = document.getElementById('mh-tree-tools');
    if (container) {
      if (treeToolsVisible) {
        container.classList.remove('hidden');
        renderWorkspaceSwitcher();
        renderPinnedFiles();
        renderRecentFiles();
      } else {
        container.classList.add('hidden');
      }
    }
    if (headerBtn) {
      headerBtn.classList.toggle('active', treeToolsVisible);
      headerBtn.setAttribute('aria-pressed', treeToolsVisible ? 'true' : 'false');
    }
  }

  function wireTreeToolsToggle() {
    var headerBtn = document.getElementById('mh-tree-tools');
    if (headerBtn) {
      headerBtn.addEventListener('click', function () {
        toggleTreeTools();
      });
    }
  }

  /* ═══════════════════════════════════════════════════════════════
  TREE VIEW TOOLS ROW (★ Explorer enhancement)
  ═══════════════════════════════════════════════════════════════
  Built dynamically (no partial markup needed): a row under the filter
  input with
    • SORT chip   — cycles Name ↕ → Size ↓ → Newest ↓, persisted,
                    re-fetches the current view with ?sort=
    • HIDDEN chip — dotfiles eye toggle, persisted, re-fetches with
                    ?hidden=1 (blocked segments stay hidden server-side)
    • FIND FILE   — opens the command palette in files mode
  Chips reuse the .m-ws-chip styles from the workspace switcher.     */
  /* ═══════════════════════════════════════════════════════════════
    SORT PICKER v2 — icon-only chip + invisible native <select>.
    The options keep their full "icon + label" text so the phone's
    picker popup stays readable; only the little chip in the search
    row shows the current mode's icon (updateSortIcon()). Tapping
    the chip hits the invisible select → Android's own popup opens
    (never clipped, never swallowed by tree gestures).
    Dotfiles are always visible; '.git' is blocked server-side.
    ═══════════════════════════════════════════════════════════════ */    
  /** (Re)build the <option> rows, then sync the chip icon. */
  function renderSortSelect() {
    var sel = document.getElementById('m-tree-sort-select');
    if (!sel) return;
    sel.innerHTML = '';
    SORT_MODES.forEach(function (m) {
      var opt = document.createElement('option');
      opt.value = m.id;
      opt.textContent = m.icon + ' ' + m.label;
      sel.appendChild(opt);
    });
    sel.value = treeSortMode;
    updateSortIcon();
  }
  /** Show ONLY the current sort mode's icon on the visible chip. */
  function updateSortIcon() {
    var ico = document.getElementById('m-tree-sort-ico');
    if (!ico) return;
    ico.textContent = getSortMode().icon;
  }
  /** Wire the sort picker. Called once from init(). */
  function wireSortDropdown() {
    var sel = document.getElementById('m-tree-sort-select');
    if (!sel) return;
    renderSortSelect();
    sel.addEventListener('change', function () {
      if (sel.value && sel.value !== treeSortMode) {
        haptic();
        treeSortMode = sel.value;
        saveViewPrefs();
        updateSortIcon();
        refreshFileTree();
      }
    });
  }
  /* Kept so existing callers don't break — now refreshes the picker. */
  function updateViewToolsRow() {
    renderSortSelect();
  }
  /* ═══════════════════════════════════════════════════════════════
  WORKSPACE WATCH POLLER (external change detection)
  ═══════════════════════════════════════════════════════════════
  The server exposes a cheap fingerprint of every path+mtime+size
  (?api=watch). This poller compares fingerprints and, on a change it
  did NOT cause itself, announces it on the event bus:
    • 'filetree:refresh'          → debounced tree reload
    • 'filetree:external-change'  → palette index invalidation, …
  Self-inflicted mutations re-baseline instead of reloading (see the
  listeners at the bottom), so only OUTSIDE changes trigger work.
  Paused while document.hidden; instant catch-up on return. Skipped
  entirely on low-tier devices to protect battery.               */
  function startWatchPoller() {
    if (watchStarted) return;
    watchStarted = true;
    var cfg = window.IDE_CONFIG || {};
    /* ★ LOCKDOWN: low-tier devices never poll */
    if ((cfg.deviceTier || 'high') === 'low') return;
    var intervalMs = parseInt(cfg.watchIntervalMs, 10);
    if (!intervalMs || intervalMs < 3000) intervalMs = 15000;
    setInterval(function () {
      if (document.hidden) return;   /* paused while backgrounded */
      pollWatch();
    }, intervalMs);
    document.addEventListener('visibilitychange', function () {
      if (!document.hidden && treeLoaded) pollWatch();   /* catch-up */
    });
  }

  function pollWatch() {
    if (!treeLoaded || document.hidden) return;
    api.files.watch().then(function (data) {
      var fp = data && data.fingerprint;
      if (typeof fp !== 'string' || fp === '') return;
      var prev = lastFingerprint;
      lastFingerprint = fp;
      /* First tick after boot/load only sets the baseline */
      if (prev === null || prev === fp) return;
      U.emit('filetree:refresh');
      U.emit('filetree:external-change');
    }).catch(function () {
      /* Silent tolerance: a failed poll must never nag the user.
         Keep the old baseline so a transient blip doesn't reload. */
    });
  }

  /* ═══════════════════════════════════════════════════════════════
  EVENTS
  ═══════════════════════════════════════════════════════════════ */
  if (U && U.on) {
    /* ── Task 9.3: Debounce tree re-renders ──────────────────────────
       Many actions fire 'filetree:refresh' in quick succession
       (bulk delete, AI multi-file edits, paste, etc.). Without
       debouncing, each event triggers a full server fetch + DOM
       rebuild. The 300ms debounce coalesces rapid events into a
       single re-render. Explicit user actions (pull-to-refresh,
       FAB creates) call loadFileTree()/refreshFileTree() directly,
       so they are NOT affected by this debounce.                  */
    var debouncedTreeRefresh = U.debounce(function () {
      if (treeLoaded) refreshFileTree();
    }, 300);

    U.on('filetree:refresh', function () {
      debouncedTreeRefresh();
    });
    U.on('editor:tabs-updated', updateOpenFileIndicators);
    U.on('file:open', function (e) {
      var p = e.detail;
      if (p && typeof p === 'string') trackRecentFile(p);
    });
    U.on('file:deleted', function (e) {
      var p = e.detail;
      if (p && typeof p === 'string') {
        removeRecentFile(p);
        removePinnedFile(p);   /* ★ PINNED: prune like recents */
      }
    });
    /* ★ AUDIT FIX: keep recents pointing at the NEW path after a rename/move
    (also covers drag-and-drop moves), so a recent row never opens a ghost.
    ★ PINNED: the exact same remap keeps pins pointing at the new path. */
    U.on('file:renamed', function (e) {
      var d = e.detail || {};
      if (!d.from || !d.to) return;
      var recents = getRecentFiles();
      var idx = recents.indexOf(d.from);
      if (idx !== -1) {
        recents[idx] = d.to;
        saveRecentFiles(recents);
        renderRecentFiles();
      }
      var pins = getPinnedPaths();
      var pIdx = pins.indexOf(d.from);
      if (pIdx !== -1) {
        pins[pIdx] = d.to;
        savePinnedPaths(pins);
        renderPinnedFiles();
      }
    });
    /* ★ WATCH POLLER: mutations made through the IDE must not read as
       "external" on the next tick — drop the baseline instead. The
       next poll simply re-anchors without emitting anything. */
    ['editor:saved', 'fileSaved', 'file:renamed', 'file:deleted', 'filetree:refresh']
      .forEach(function (ev) {
        U.on(ev, function () { lastFingerprint = null; });
      });
  }

  /* ═══════════════════════════════════════════════════════════════
  MULTI-SELECT MODE (Task 8.9)
  ═══════════════════════════════════════════════════════════════ */

  /**
   * Enter selection mode with one item pre-selected.
   */
  function enterSelectionMode(path) {
    selectionMode = true;
    selectedPaths.clear();
    selectedPaths.add(path);
    selectionAnchor = path;
    updateSelectionVisuals();
    showSelectionBar();
  }

  /**
   * Exit selection mode and clear all selections.
   */
  function exitSelectionMode() {
    selectionMode = false;
    selectedPaths.clear();
    selectionAnchor = null;
    updateSelectionVisuals();
    hideSelectionBar();
  }

  /**
   * Toggle a path in/out of the selection.
   */
  function toggleSelection(path) {
    // Tap while in selection mode = Single Toggle
    if (selectedPaths.has(path)) {
      selectedPaths.delete(path);
    } else {
      selectedPaths.add(path);
      selectionAnchor = path; // Update anchor on single tap
    }
    if (selectedPaths.size === 0) {
      exitSelectionMode();
    } else {
      updateSelectionVisuals();
      updateSelectionBarCount();
    }
  }

  /**
   * Update the visual highlight on all rows.
   */
  function updateSelectionVisuals() {
    if (!treeContainer) return;
    var rows = treeContainer.querySelectorAll('.m-tree-row');
    rows.forEach(function (row) {
      var path = row.getAttribute('data-path');
      if (path && selectedPaths.has(path)) {
        row.classList.add('m-tree-selected');
      } else {
        row.classList.remove('m-tree-selected');
      }
    });
  }

  /**
   * Show the selection bar and update the count.
   */
  function showSelectionBar() {
    if (selectionBarEl) {
      selectionBarEl.classList.remove('hidden');
    }
    updateSelectionBarCount();
  }

  /**
   * Hide the selection bar.
   */
  function hideSelectionBar() {
    if (selectionBarEl) {
      selectionBarEl.classList.add('hidden');
    }
  }

  /**
   * Update the "N selected" text.
   */
  function updateSelectionBarCount() {
    if (selectionCountEl) {
      var n = selectedPaths.size;
      selectionCountEl.textContent = n + ' selected';
    }
    /* Show "More" button only when exactly 1 item is selected */
    var moreBtn = document.getElementById('msel-more');
    if (moreBtn) {
      if (selectedPaths.size === 1) {
        moreBtn.classList.remove('hidden');
      } else {
        moreBtn.classList.add('hidden');
      }
    }
  }

  /**
  * Get the selected items as an array of {path, type, name}.
  * Falls back to inferring type from the path when the DOM row is missing.
  */
  function getSelectedItems() {
    var items = [];
    selectedPaths.forEach(function (path) {
      var safe = String(path).replace(/\\/g, '\\\\').replace(/"/g, '\\"');
      var row = treeContainer.querySelector('.m-tree-row[data-path="' + safe + '"]');
      var type = 'file';
      if (row) {
        var dt = row.getAttribute('data-type');
        type = (dt === 'folder') ? 'folder' : 'file';
      } else {
        /* Fallback: if no dot in the last segment, likely a folder */
        var lastSeg = path.split('/').pop();
        if (lastSeg.indexOf('.') === -1) type = 'folder';
      }
      items.push({
        path: path,
        type: type,
        name: path.split('/').pop()
      });
    });
    return items;
  }

  /**
   * Wire the selection bar buttons.
   */
  function wireSelectionBar() {
    var cancelBtn = document.getElementById('msel-cancel');
    var deleteBtn = document.getElementById('msel-delete');
    var copyBtn = document.getElementById('msel-copy');
    var moveBtn = document.getElementById('msel-move');
    var moreBtn = document.getElementById('msel-more');

    if (cancelBtn) {
      cancelBtn.addEventListener('click', function () {
        exitSelectionMode();
      });
    }

    if (deleteBtn) {
      deleteBtn.addEventListener('click', function () {
        bulkDelete();
      });
    }

    if (copyBtn) {
      copyBtn.addEventListener('click', function () {
        bulkCopy();
      });
    }

    if (moveBtn) {
      moveBtn.addEventListener('click', function () {
        bulkMove();
      });
    }

    if (moreBtn) {
      moreBtn.addEventListener('click', function () {
        /* Open the single-item actions sheet for the one selected item */
        var items = getSelectedItems();
        if (items.length === 1) {
          var item = items[0];
          var Actions = window.IDE.mobileActions;
          if (Actions) {
            Actions.setCurrentTarget(item);
            var titleEl = document.getElementById('fa-title');
            if (titleEl) {
              titleEl.textContent = (item.type === 'folder' ? '📁 ' : '📄 ') + item.name;
            }
            var Sheets = window.IDE.mobileSheets;
            if (Sheets) Sheets.openSheet('ms-file-actions', 'fa-backdrop');
          }
          exitSelectionMode();
        }
      });
    }
  }

  /**
   * Bulk delete all selected items.
   */
  function bulkDelete() {
    var items = getSelectedItems();
    if (items.length === 0) return;

    var count = items.length;
    var hasFolders = items.some(function (i) { return i.type === 'folder'; });
    var msg = (hasFolders)
      ? 'Delete ' + count + ' item(s) including folders and ALL their contents?'
      : 'Delete ' + count + ' file(s)? This cannot be undone.';

    /* Use a simple confirm via the existing delete sheet pattern */
    var dlMsg = document.getElementById('dl-msg');
    if (dlMsg) dlMsg.textContent = msg;

    /* Store items for the confirm handler */
    window._bulkDeleteItems = items;

    var Sheets = window.IDE.mobileSheets;
    if (Sheets) Sheets.openSheet('ms-delete', 'dl-backdrop');

    /* Override the delete confirm button temporarily */
    var dlConfirm = document.getElementById('dl-confirm');
    if (dlConfirm) {
      /* Remove old listeners by cloning */
      var newBtn = dlConfirm.cloneNode(true);
      dlConfirm.parentNode.replaceChild(newBtn, dlConfirm);
      newBtn.addEventListener('click', function () {
        Sheets.closeSheet('ms-delete', 'dl-backdrop');
        executeBulkDelete(window._bulkDeleteItems);
        window._bulkDeleteItems = null;
      });
    }
  }

  /**
   * Execute the bulk delete after confirmation.
   */
  function executeBulkDelete(items) {
    if (!items || items.length === 0) return;
    var promises = items.map(function (item) {
      if (item.type === 'folder') {
        return api.files.deleteFolder(item.path);
      } else {
        return api.files.delete(item.path);
      }
    });
    Promise.all(promises).then(function () {
      toast('🗑️ Deleted ' + items.length + ' item(s)');
      items.forEach(function (item) {
        if (U && U.emit) U.emit('file:deleted', item.path);
      });
      exitSelectionMode();
      refreshFileTree();
    }).catch(function (err) {
      toast('⚠ Delete failed: ' + err.message);
    });
  }

  /**
   * Copy all selected items to clipboard.
   */
  function bulkCopy() {
    var items = getSelectedItems();
    if (items.length === 0) return;
    var Actions = window.IDE.mobileActions;
    if (Actions && Actions.setClipboardMulti) {
      Actions.setClipboardMulti(items, 'copy');
      toast('📋 Copied ' + items.length + ' item(s)');
    }
    exitSelectionMode();
  }

  /**
   * Move (cut) all selected items to clipboard.
   * User then navigates to destination and pastes.
   */
  function bulkMove() {
    var items = getSelectedItems();
    if (items.length === 0) return;
    var Actions = window.IDE.mobileActions;
    if (Actions && Actions.setClipboardMulti) {
      Actions.setClipboardMulti(items, 'cut');
      toast('📦 Cut ' + items.length + ' item(s) — navigate to destination and Paste');
    }
    exitSelectionMode();
  }

  /* ═══════════════════════════════════════════════════════════════
  WORKSPACE / PROJECT SWITCHER (Task 8.10)
  ═══════════════════════════════════════════════════════════════ */

  /**
   * Render the workspace switcher chips.
   * Only shown when there are 2+ top-level folders (projects).
   */
  function renderWorkspaceSwitcher() {
    var switcherEl = document.getElementById('m-workspace-switcher');
    var chipsEl = document.getElementById('m-ws-chips');
    if (!switcherEl || !chipsEl) return;
    /* Only render if tree tools are visible */
    if (!treeToolsVisible) return;

    /* Only show if there are 2+ projects */
    if (topLevelFolders.length < 2) {
      switcherEl.classList.add('hidden');
      return;
    }

    switcherEl.classList.remove('hidden');
    chipsEl.innerHTML = '';

    /* "All" chip */
    var allChip = document.createElement('button');
    allChip.className = 'm-ws-chip' + (selectedProject === '' ? ' active' : '');
    allChip.innerHTML = '<span class="m-ws-chip-icon">📂</span><span class="m-ws-chip-label">All</span>';
    allChip.addEventListener('click', function () {
      selectedProject = '';
      saveSelectedProject();
      renderWorkspaceSwitcher();
      refreshFileTree();
    });
    chipsEl.appendChild(allChip);

    /* One chip per project folder */
    topLevelFolders.forEach(function (folderName) {
      var chip = document.createElement('button');
      chip.className = 'm-ws-chip' + (selectedProject === folderName ? ' active' : '');
      chip.innerHTML = '<span class="m-ws-chip-icon">📁</span><span class="m-ws-chip-label">' + escHtml(folderName) + '</span>';
      chip.addEventListener('click', function () {
        selectedProject = folderName;
        saveSelectedProject();
        renderWorkspaceSwitcher();
        refreshFileTree();
      });
      chipsEl.appendChild(chip);
    });
  }

  /**
   * Filter the tree data to show only the selected project.
   * If selectedProject is '', show everything.
   */
  function filterTreeByProject(treeData) {
    if (!treeData || !treeData.length) return treeData;
    if (selectedProject === '') return treeData;

    /* Find the folder matching selectedProject and return only its children */
    for (var i = 0; i < treeData.length; i++) {
      if (treeData[i].type === 'folder' && treeData[i].name === selectedProject) {
        return treeData[i].children || [];
      }
    }
    /* If the selected project no longer exists, reset */
    selectedProject = '';
    saveSelectedProject();
    return treeData;
  }

  /**
   * Save the selected project to localStorage.
   */
  function saveSelectedProject() {
    try {
      localStorage.setItem(LS_SELECTED_PROJECT, selectedProject);
    } catch (e) { }
  }

  /**
   * Load the saved project selection from localStorage.
   */
  function loadSelectedProject() {
    try {
      selectedProject = localStorage.getItem(LS_SELECTED_PROJECT) || '';
    } catch (e) {
      selectedProject = '';
    }
  }

  /* ═══════════════════════════════════════════════════════════════
  WORKSPACE CHIP LABEL (★ Explorer Upgrade follow-up)
  ═══════════════════════════════════════════════════════════════
  The Explorer header chip should show the folder you are actually
  in: app default → "workspace/", custom → "<folder name>/".
  We ask the server once at boot; a workspace switch reloads the
  whole IDE, so boot-time is enough.                            */
  function updateWorkspaceChipLabel() {
    var chip = document.getElementById('mh-workspace-chip');
    var labelEl = chip ? chip.querySelector('.mws-label') : null;
    if (!chip || !labelEl) return;
    if (!api || !api.workspace || typeof api.workspace.info !== 'function') return;
    api.workspace.info().then(function (info) {
      if (!info || typeof info.path !== 'string' || info.path === '') return;
      var clean = info.path.replace(/\/+$/, '');
      var base = clean.split('/').pop() || 'workspace';
      labelEl.textContent = info.isDefault ? 'workspace/' : base + '/';
      chip.classList.toggle('m-ws-custom', !info.isDefault);
      chip.title = info.path;
    }).catch(function () { /* keep the static label if the call fails */ });
  }

  function wireProperties() {
    var Sheets = window.IDE.mobileSheets;

    var tabs = document.querySelectorAll('#ms-properties .m-props-tab');
    tabs.forEach(function (tab) {
      tab.addEventListener('click', function () {
        var view = tab.getAttribute('data-view');
        tabs.forEach(function (t) { t.classList.toggle('active', t === tab); });
        var basicView = document.getElementById('pr-view-basic');
        var advView = document.getElementById('pr-view-advanced');
        if (basicView) basicView.classList.toggle('hidden', view !== 'basic');
        if (advView) advView.classList.toggle('hidden', view !== 'advanced');
      });
    });

    var prClose = document.getElementById('pr-close');
    if (prClose) prClose.addEventListener('click', function () {
      if (Sheets) Sheets.closeSheet('ms-properties', 'pr-backdrop');
    });

    var prBackdrop = document.getElementById('pr-backdrop');
    if (prBackdrop) prBackdrop.addEventListener('click', function () {
      if (Sheets) Sheets.closeSheet('ms-properties', 'pr-backdrop');
    });

    var prHandle = document.querySelector('#ms-properties .m-sheet-handle');
    if (prHandle) prHandle.addEventListener('click', function () {
      if (Sheets) Sheets.closeSheet('ms-properties', 'pr-backdrop');
    });

    if (Sheets && Sheets.initDragToDismiss) {
      Sheets.initDragToDismiss('ms-properties', 'pr-backdrop');
    }
  }

  /* ═══════════════════════════════════════════════════════════════
  BOOT + EXPOSE
  ═══════════════════════════════════════════════════════════════ */
  function init() {
    if (wired) return;
    wired = true;
    loadSelectedProject();
    loadViewPrefs();
    loadPinnedPaths();
    wireSortDropdown();            /* ★ icon-only sort button + dropdown */
    updateWorkspaceChipLabel();
    wireFab();
    wireSearchFilter();
    wireRecentToggle();
    wireTreeToolsToggle();
    initDragAndDrop();
    wireSelectionBar();
    renderRecentFiles();
    renderPinnedFiles();
    wireProperties();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  window.IDE = window.IDE || {};
  window.IDE.mobileFileTree = {
    init: init,
    load: loadFileTree,
    refresh: refreshFileTree,
    updateWorkspaceChipLabel: updateWorkspaceChipLabel,
    /**
     * ★ OP GUARD — no-arg keeps the legacy boot behaviour (load tree +
     * render tool sections); with a path it resolves once that folder's
     * children are in the local model (lazy fetch when unloaded).
     * @param {string} [path='']
     * @returns {Promise<void>}
     */
    ensureLoaded: ensureLoaded,
    isLoaded: function () { return treeLoaded; },
    /**
     * ★ PALETTE SYNERGY — live nested model (may contain folders flagged
     * unloaded:true whose children haven't been fetched). Consumers walk
     * it instead of refetching ?api=tree.
     * @returns {Array<Object>|null}
     */
    getSnapshotTree: function () { return treeDataCache; },
    /** True while this workspace renders in lazy (top-level only) mode. */
    isLazyMode: function () { return lazyMode; },
    /** ★ PINNED — programmatic pin/unpin for other modules. */
    pinFile: pinFile,
    unpinFile: unpinFile,
    getPinnedFiles: function () { return getPinnedPaths(); },
    _dragActive: function () { return dragState.active; },
    _dragArmed: function () { return dragState.armed; },
    /* Task 8.9: expose selection methods */
    isSelectionMode: function () { return selectionMode; },
    getSelectedItems: getSelectedItems,
    exitSelectionMode: exitSelectionMode,
    /* Gesture Revamp: used by mobile-swipe.js + mobile-actions.js */
    isPathSelected: function (path) { return selectedPaths.has(path); },
    toggleSelectBySwipe: toggleSelectBySwipe,
    suppressNextClick: suppressNextClick
  };
})();