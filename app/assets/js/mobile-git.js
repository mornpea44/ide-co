/**
 * ═══════════════════════════════════════════════════════════════════════════
 *  QUIRKY IDE — MOBILE GIT PANEL (v10 redesign)
 * ═══════════════════════════════════════════════════════════════════════════
 *
 *  Full read-WRITE source control, workshop-style full-screen page
 *  (NO drag-to-close):
 *    • Segmented tabs: Changes(n) · Staged(n) · History(n)
 *    • Stage / unstage (swipe-left or long-press sheet), discard w/ confirm
 *    • Sticky commit bar with message + Amend toggle
 *    • Branch chip → bottom-sheet branch list / create / switch
 *    • ⋯ menu: Pull · Push · Init repository
 *    • Diff overlay toolbar: copy · wrap toggle · truncated notice
 *    • Merge-conflict banner (UU/AA detection from porcelain status)
 *    • Untracked files open a friendly card + [Open in editor]
 *
 *  Endpoints: git-info/status/log/diff/show + the v10 write ops
 *  (git-stage/unstage/discard/commit/init/branch-list/branch-create/
 *   branch-switch/pull/push). Client helpers live in api.js (api.git.*).
 *
 *  EXPOSES: window.IDE.mobileGit
 * ═══════════════════════════════════════════════════════════════════════════
 */
(function () {
  'use strict';
  var U = window.IDE && window.IDE.utils;
  if (!U) return;

  /* ═══════════════════════════════════════════════════════════════
  DOM REFERENCES
  ═══════════════════════════════════════════════════════════════ */
  var overlay = document.getElementById('m-git-overlay');
  if (!overlay) return;   // markup not present — nothing to do

  var closeBtn = document.getElementById('m-git-close');
  var refreshBtn = document.getElementById('m-git-refresh');
  var moreBtn = document.getElementById('m-git-more');
  var branchBtn = document.getElementById('m-git-branch');
  var tabsEl = document.getElementById('m-git-tabs');
  var mergeBanner = document.getElementById('m-git-merge-banner');
  var changesList = document.getElementById('m-git-changes-list');
  var changesCount = document.getElementById('m-git-changes-count');
  var stagedList = document.getElementById('m-git-staged-list');
  var stagedCount = document.getElementById('m-git-staged-count');
  var historyList = document.getElementById('m-git-history-list');
  var historyCount = document.getElementById('m-git-history-count');
  var loadMoreBtn = document.getElementById('m-git-loadmore');
  var commitBar = document.getElementById('m-git-commitbar');
  var msgInput = document.getElementById('m-git-msg');
  var amendChk = document.getElementById('m-git-amend');
  var commitBtn = document.getElementById('m-git-commit');

  var diffOverlay = document.getElementById('m-git-diff-overlay');
  var diffCloseBtn = document.getElementById('m-git-diff-close');
  var diffTitleEl = document.getElementById('m-git-diff-title');
  var diffBody = document.getElementById('m-git-diff-body');
  var diffCopyBtn = document.getElementById('m-git-diff-copy');
  var diffWrapBtn = document.getElementById('m-git-diff-wrap');

  var cfg = window.IDE_CONFIG || {};
  var state = {
    available: false,
    isRepo: false,
    branch: null,
    files: [],
    activeTab: 'changes',
    historyLimit: 40,
    commits: [],
    busy: false
  };
  var isOpen = false;
  var wired = false;
  var lastDiffText = '';

  /* ═══════════════════════════════════════════════════════════════
  HELPERS
  ═══════════════════════════════════════════════════════════════ */
  function esc(s) {
    if (U && U.escapeHtml) return U.escapeHtml(s);
    if (typeof s !== 'string') return '';
    return s.replace(/&/g, '&amp;').replace(/</g, '&lt;')
      .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }
  function toast(msg) { if (U && U.toast) U.toast(msg); }
  function getFileIcon(name) { return (U && U.getFileIcon) ? U.getFileIcon(name) : '📄'; }
  function dirOf(p) { var i = p.lastIndexOf('/'); return i > 0 ? p.slice(0, i) : ''; }
  function nameOf(p) { var i = p.lastIndexOf('/'); return i >= 0 ? p.slice(i + 1) : p; }

  /** Envelope-unwrapping fetch helper for GET and POST JSON. */
  function apiCall(action, params, body, method) {
    var url = 'index.php?api=' + action;
    Object.keys(params || {}).forEach(function (k) {
      if (params[k] != null && params[k] !== '') {
        url += '&' + k + '=' + encodeURIComponent(params[k]);
      }
    });
    var opts = { headers: { 'Accept': 'application/json' } };
    if (method === 'POST') {
      opts.method = 'POST';
      opts.headers['Content-Type'] = 'application/json';
      opts.body = JSON.stringify(body || {});
    }
    return fetch(url, opts).then(function (res) {
      return res.json().then(function (j) {
        if (!res.ok || (j && j.ok === false)) {
          throw new Error((j && j.error) || ('HTTP ' + res.status));
        }
        return j.data;
      });
    });
  }
  function apiGet(action, params) { return apiCall(action, params, null); }
  function apiPost(action, body) { return apiCall(action, null, body, 'POST'); }

  /** Status letters → badge letter / css class / tooltip. */
  function badgeFor(x, y) {
    if (x === '?') return { letter: '?', cls: 'unt', label: 'Untracked' };
    var c = (y && y !== ' ') ? y : x;
    var map = {
      M: ['M', 'mod', 'Modified'],
      A: ['A', 'add', 'Added'],
      D: ['D', 'del', 'Deleted'],
      R: ['R', 'ren', 'Renamed'],
      C: ['C', 'ren', 'Copied'],
      U: ['U', 'del', 'Conflict']
    };
    return map[c] || [c === ' ' ? '•' : c, 'mod', 'Changed'];
  }

  function setCount(el, n) {
    if (!el) return;
    el.textContent = String(n);
    el.classList.toggle('zero', !n);
  }

  /* ── Generic long-press action sheet (JS-injected .m-sheet) ──────── */
  var _sheet = null;
  function showActionSheet(title, items) {
    hideActionSheet();
    var backdrop = document.createElement('div');
    backdrop.className = 'm-sheet-backdrop ai-sheet-backdrop show';
    backdrop.id = 'mg-sheet-backdrop';
    var sheet = document.createElement('div');
    sheet.className = 'm-sheet ai-sheet show';
    sheet.id = 'mg-action-sheet';
    var h = '<div class="m-sheet-title">' + esc(title) + '</div>';
    items.forEach(function (it, idx) {
      h += '<button class="m-sheet-row' + (it.danger ? ' danger' : '') +
        '" data-idx="' + idx + '">' +
        '<span class="m-sheet-row-icon">' + (it.icon || '•') + '</span>' +
        esc(it.label) + '</button>';
    });
    h += '<button class="m-sheet-row" data-idx="-1"><span class="m-sheet-row-icon">✖</span>Cancel</button>';
    sheet.innerHTML = h;
    document.body.appendChild(backdrop);
    document.body.appendChild(sheet);
    _sheet = { backdrop: backdrop, sheet: sheet };
    sheet.addEventListener('click', function (e) {
      var btn = e.target.closest('[data-idx]');
      if (!btn) return;
      var idx = parseInt(btn.getAttribute('data-idx'), 10);
      hideActionSheet();
      if (idx >= 0 && items[idx] && typeof items[idx].fn === 'function') {
        setTimeout(items[idx].fn, 60);
      }
    });
    backdrop.addEventListener('click', hideActionSheet);
    var Sheets = window.IDE && window.IDE.mobileSheets;
    if (Sheets) {
      // Route hardware BACK/Escape through the shared stack when present.
      if (Sheets.registerOverlay) Sheets.registerOverlay(hideActionSheet);
    }
  }
  function hideActionSheet() {
    if (!_sheet) return;
    var s = _sheet;
    _sheet = null;
    s.sheet.classList.remove('show');
    s.backdrop.classList.remove('show');
    setTimeout(function () {
      try { s.sheet.remove(); s.backdrop.remove(); } catch (e) { }
    }, 260);
  }

  /* ═══════════════════════════════════════════════════════════════
  OPEN / CLOSE + TABS
  ═══════════════════════════════════════════════════════════════ */
  function openGit() {
    if (!cfg.features || !cfg.features.git) {
      toast('🌿 Git is currently turned off. You can turn it on in Settings.');
      return;
    }
    isOpen = true;
    overlay.classList.remove('hidden');
    void overlay.offsetWidth;
    overlay.classList.add('show');
    refreshAll();
  }
  function closeGit() {
    if (!isOpen) return;
    isOpen = false;
    overlay.classList.remove('show');
    setTimeout(function () { overlay.classList.add('hidden'); }, 300);
    hideActionSheet();
    closeDiff();
  }

  function switchTab(tab) {
    state.activeTab = tab;
    if (tabsEl) {
      tabsEl.querySelectorAll('.m-git-tab').forEach(function (b) {
        b.classList.toggle('active', b.getAttribute('data-tab') === tab);
      });
    }
    ['changes', 'staged', 'history'].forEach(function (t) {
      var pane = document.getElementById('m-git-pane-' + t);
      if (pane) pane.classList.toggle('hidden', t !== tab);
    });
    // Commit bar only makes sense while looking at Changes/Staged.
    if (commitBar) commitBar.classList.toggle('hidden', tab === 'history');
  }

  /* ═══════════════════════════════════════════════════════════════
  DATA LOADING
  ═══════════════════════════════════════════════════════════════ */
  function renderUnavailable(title, lines) {
    var html = '<div class="m-git-empty"><div class="m-git-empty-icon">🌿</div><b>' +
      esc(title) + '</b>' +
      lines.map(function (l) { return '<p>' + l + '</p>'; }).join('');
    if (state.available && !state.isRepo) {
      html += '<button class="m-git-init-btn" id="m-git-init">📦 Initialize repository</button>';
    }
    html += '</div>';
    if (changesList) changesList.innerHTML = html;
    if (stagedList) stagedList.innerHTML = html;
    if (historyList) historyList.innerHTML =
      '<div class="m-git-empty"><div class="m-git-empty-icon">🌿</div><b>' + esc(title) + '</b></div>';
    setCount(changesCount, 0);
    setCount(stagedCount, 0);
    setCount(historyCount, 0);
    if (branchBtn) branchBtn.textContent = '⎇ —';
    if (commitBar) commitBar.classList.add('hidden');
    var initBtn = document.getElementById('m-git-init');
    if (initBtn) initBtn.addEventListener('click', doInit);
  }

  function refreshAll() {
    if (changesList) changesList.innerHTML = '<div class="m-git-empty">Checking git…</div>';
    apiGet('git-info')
      .then(function (info) {
        state.available = !!info.available;
        state.isRepo = !!info.isRepo;
        state.branch = info.branch || null;
        if (!info.available) {
          renderUnavailable('git is not installed', [
            'Install Git (Workshop → packages, or Termux <code>pkg install git</code>) and reload.',
            'If git lives in a custom folder, set <code>git → binary</code> in config.php.'
          ]);
          return;
        }
        if (!info.isRepo) {
          renderUnavailable('This workspace is not a git repo', [
            'Initialize one below — no terminal needed.'
          ]);
          return;
        }
        if (commitBar) commitBar.classList.remove('hidden');
        updateBranchChip();
        return refreshChanges().then(function () { return refreshHistory(); });
      })
      .catch(function (err) {
        renderUnavailable('Git panel unavailable', [esc(err.message)]);
      });
  }

  function updateBranchChip() {
    if (branchBtn) branchBtn.textContent = '⎇ ' + (state.branch || 'HEAD');
  }

  function splitStatus(files) {
    var unstagedRows = [], stagedRows = [], conflicts = [];
    files.forEach(function (f) {
      var x = f.index, y = f.worktree;
      if (x === 'U' || y === 'U' || x === 'A' && y === 'A' || x === 'D' && y === 'D') {
        conflicts.push(f);
      }
      if (x === '?') { unstagedRows.push(f); return; }
      if (x && x !== ' ' && x !== '!') stagedRows.push(f);
      if (y && y !== ' ' && y !== '!' && x !== '?') unstagedRows.push(f);
    });
    return { unstaged: unstagedRows, staged: stagedRows, conflicts: conflicts };
  }

  function refreshChanges() {
    return apiGet('git-status').then(function (d) {
      state.files = d.files || [];
      var parts = splitStatus(state.files);
      var counts = d.counts || {};
      setCount(changesCount, counts.unstaged != null ? (counts.unstaged + counts.untracked) : parts.unstaged.length);
      setCount(stagedCount, counts.staged != null ? counts.staged : parts.staged.length);
      renderMergeBanner(parts.conflicts);
      renderFileList(changesList, parts.unstaged, 'unstaged');
      renderFileList(stagedList, parts.staged, 'staged');
      updateCommitState();
    }).catch(function (err) {
      if (changesList) changesList.innerHTML = '<div class="m-git-empty">⚠ ' + esc(err.message) + '</div>';
    });
  }

  function renderMergeBanner(conflicts) {
    if (!mergeBanner) return;
    if (!conflicts.length) { mergeBanner.classList.add('hidden'); return; }
    mergeBanner.classList.remove('hidden');
    mergeBanner.innerHTML =
      '<b>⚠ Merge in progress</b> — ' + conflicts.length +
      ' conflicted file(s). Resolve them in the editor, then Stage each resolved file.' +
      '<span class="m-git-merge-hint">(aborting a merge is available from a terminal: <code>git merge --abort</code>)</span>';
  }

  function fileRowHtml(f) {
    var b = badgeFor(f.index, f.worktree);
    var dir = dirOf(f.path);
    return '<div class="m-file-grip">⋮⋮</div>' +
      '<span class="m-git-file-icon">' + getFileIcon(nameOf(f.path)) + '</span>' +
      '<span class="m-git-file-name">' + esc(nameOf(f.path)) + '</span>' +
      (dir ? '<span class="m-git-file-dir">' + esc(dir) + '</span>' : '') +
      '<span class="m-git-badge ' + b.cls + '" title="' + b.label + '">' + b.letter + '</span>' +
      '<span class="m-file-swipe-action"></span>';
  }

  /**
   * Renders a file list. mode = 'unstaged' | 'staged'.
   * Rows support: tap→diff · long-press→action sheet · swipe-left→quick action.
   */
  function renderFileList(listEl, rows, mode) {
    if (!listEl) return;
    if (!rows.length) {
      listEl.innerHTML = mode === 'staged'
        ? '<div class="m-git-empty"><div class="m-git-empty-icon">🎯</div><b>Nothing staged</b><p>Swipe a change left or long-press it to stage.</p></div>'
        : '<div class="m-git-empty"><div class="m-git-empty-icon">✨</div><b>Working tree clean</b><p>No changes to show.</p></div>';
      return;
    }
    listEl.innerHTML = '';
    rows.forEach(function (f) {
      var row = document.createElement('div');
      row.className = 'm-git-file';
      row.innerHTML = fileRowHtml(f);

      row.addEventListener('click', function () { openFileDiff(f); });

      /* Long-press → action sheet */
      var lpTimer = null, lpFired = false, startX = 0, moved = false;
      var isUntracked = f.index === '?';
      row.addEventListener('touchstart', function (e) {
        lpFired = false; moved = false; startX = e.touches[0].clientX;
        lpTimer = setTimeout(function () {
          lpFired = true;
          if (navigator.vibrate) navigator.vibrate(12);
          showFileActions(f, mode);
        }, 480);
      }, { passive: true });
      row.addEventListener('touchmove', function (e) {
        if (Math.abs(e.touches[0].clientX - startX) > 8) moved = true;
        if (moved) { clearTimeout(lpTimer); }
      }, { passive: true });
      ['touchend', 'touchcancel'].forEach(function (ev) {
        row.addEventListener(ev, function () { clearTimeout(lpTimer); }, { passive: true });
      });
      row.addEventListener('contextmenu', function (e) {
        e.preventDefault();
        showFileActions(f, mode);
      });

      /* Swipe-left reveals quick action (stage / unstage) */
      attachSwipe(row, f, mode, function () {
        return !lpFired && !moved;
      });

      listEl.appendChild(row);
    });
  }

  /** Lightweight swipe-left handler revealing the primary action. */
  function attachSwipe(row, f, mode, canAct) {
    var sx = 0, sy = 0, dx = 0, tracking = false;
    var TH = 64;
    row.addEventListener('touchstart', function (e) {
      sx = e.touches[0].clientX; sy = e.touches[0].clientY; dx = 0; tracking = true;
      row.classList.remove('swiped');
    }, { passive: true });
    row.addEventListener('touchmove', function (e) {
      if (!tracking) return;
      var mx = e.touches[0].clientX - sx;
      var my = e.touches[0].clientY - sy;
      if (Math.abs(my) > Math.abs(mx)) { tracking = false; return; } // vertical scroll wins
      dx = Math.min(0, mx);
      row.style.transform = 'translateX(' + dx + 'px)';
    }, { passive: true });
    row.addEventListener('touchend', function () {
      tracking = false;
      row.style.transform = '';
      if (dx < -TH && canAct()) {
        if (mode === 'unstaged') doStage([f.path]);
        else doUnstage([f.path]);
      }
    }, { passive: true });
  }

  function showFileActions(f, mode) {
    var isUntracked = f.index === '?';
    var items = [{ icon: '📖', label: 'Open in editor', fn: function () { openInEditor(f.path); } }];
    if (mode === 'unstaged') {
      if (!isUntracked) {
        items.push({ icon: '➕', label: 'Stage', fn: function () { doStage([f.path]); } });
      } else {
        items.push({ icon: '➕', label: 'Track & stage', fn: function () { doStage([f.path]); } });
      }
      if (!isUntracked) {
        items.push({
          icon: '🗑', label: 'Discard changes', danger: true, fn: function () {
            U.modal.confirm({
              title: 'Discard changes?',
              message: 'Revert "' + f.path + '" to its last committed state? This cannot be undone.',
              danger: true
            }).then(function (ok) { if (ok) doDiscard([f.path]); });
          }
        });
      }
    } else {
      items.push({ icon: '➖', label: 'Unstage', fn: function () { doUnstage([f.path]); } });
    }
    items.push({
      icon: '📋', label: 'Copy path', fn: function () {
        if (navigator.clipboard) navigator.clipboard.writeText(f.path);
        toast('Path copied');
      }
    });
    showActionSheet(nameOf(f.path), items);
  }

  function openInEditor(path) {
    try {
      U.emit('file:open', path);
      toast('Opening ' + nameOf(path) + '…');
    } catch (e) {
      toast('Could not open editor');
    }
  }

  /* ── write operations ─────────────────────────────────────────── */
  function guard(fn) {
    if (state.busy) return;
    state.busy = true;
    fn().catch(function (err) { toast('⚠ ' + err.message); })
      .then(function () { state.busy = false; });
  }

  function doStage(paths) {
    guard(function () {
      return apiPost('git-stage', { paths: paths }).then(function (d) {
        toast('➕ Staged ' + (d.count || paths.length) + ' file(s)');
        return refreshChanges();
      });
    });
  }
  function doUnstage(paths) {
    guard(function () {
      return apiPost('git-unstage', { paths: paths }).then(function (d) {
        toast('➖ Unstaged ' + (d.count || paths.length) + ' file(s)');
        return refreshChanges();
      });
    });
  }
  function doDiscard(paths) {
    guard(function () {
      return apiPost('git-discard', { paths: paths }).then(function () {
        toast('↩ Discarded ' + paths.length + ' file(s)');
        return refreshChanges();
      });
    });
  }
  function doInit() {
    guard(function () {
      return apiPost('git-init', {}).then(function (d) {
        toast(d.alreadyRepo ? 'Already a repository' : '📦 Repository initialized');
        refreshAll();
      });
    });
  }

  function updateCommitState() {
    if (!commitBtn) return;
    var d = state.files ? splitStatus(state.files) : { staged: [] };
    var hasStaged = d.staged.length > 0;
    var amend = !!(amendChk && amendChk.checked);
    var msg = msgInput ? msgInput.value.trim() : '';
    commitBtn.disabled = !(hasStaged || amend) || !msg || msg.length < 2;
    if (amendChk) amendChk.parentElement.classList.toggle('enabled', amend);
  }

  function doCommit() {
    var msg = msgInput.value.trim();
    var amend = !!(amendChk && amendChk.checked);
    if ((!msg || msg.length < 2)) return;
    guard(function () {
      return apiPost('git-commit', { message: msg, amend: amend }).then(function (d) {
        toast((amend ? '✏️ Amended' : '✓ Committed') + (d.branch ? ' → ' + d.branch : ''));
        msgInput.value = '';
        if (amendChk) amendChk.checked = false;
        updateCommitState();
        state.historyLimit = 40;
        return Promise.all([refreshChanges(), refreshHistory()]);
      });
    });
  }

  /* ── history ──────────────────────────────────────────────────── */
  function relDate(s) {
    // Backend already returns a formatted date string; pass through.
    return esc(s || '');
  }
  function refreshHistory() {
    return apiGet('git-log', { limit: state.historyLimit })
      .then(function (d) {
        var commits = d.commits || [];
        state.commits = commits;
        setCount(historyCount, commits.length);
        if (loadMoreBtn) loadMoreBtn.classList.toggle('hidden', commits.length < state.historyLimit);
        if (!commits.length) {
          historyList.innerHTML = '<div class="m-git-empty"><div class="m-git-empty-icon">🕘</div><b>No commits yet</b><p>Stage some changes and make your first commit below.</p></div>';
          return;
        }
        historyList.innerHTML = '';
        commits.forEach(function (c) {
          var row = document.createElement('div');
          row.className = 'm-git-commit';
          row.innerHTML =
            '<div class="m-git-commit-top"><span class="m-git-hash">' + esc(c.short) + '</span>' +
            '<span class="m-git-subject">' + esc(c.subject) + '</span></div>' +
            '<div class="m-git-commit-meta">' + esc(c.author) + ' · ' + relDate(c.date) + '</div>';
          row.addEventListener('click', function () { openCommitDiff(c); });
          historyList.appendChild(row);
        });
      })
      .catch(function (err) {
        if (historyList) historyList.innerHTML = '<div class="m-git-empty">⚠ ' + esc(err.message) + '</div>';
      });
  }

  function loadOlderCommits() {
    state.historyLimit = state.historyLimit * 3;
    if (loadMoreBtn) {
      loadMoreBtn.disabled = true;
      loadMoreBtn.textContent = 'Loading…';
    }
    refreshHistory().then(function () {
      if (loadMoreBtn) {
        loadMoreBtn.disabled = false;
        loadMoreBtn.textContent = '↓ Load older commits';
      }
    });
  }

  /* ═══════════════════════════════════════════════════════════════
  BRANCH CHIP → SHEET
  ═══════════════════════════════════════════════════════════════ */
  function openBranchSheet() {
    if (!state.isRepo) return;
    apiGet('git-branch-list').then(function (d) {
      var branches = d.branches || [];
      var current = d.current || state.branch;
      var items = branches.map(function (b) {
        return {
          icon: b.current || b.name === current ? '📍' : '⎇',
          label: b.name + (b.remote ? '  (remote)' : ''),
          fn: (function (name, isRemote) {
            return function () {
              if (isRemote) { toast('Checking out remote branches requires a local branch'); return; }
              doSwitchBranch(name);
            };
          })(b.name, !!b.remote)
        };
      });
      items.push({
        icon: '✨', label: 'New branch…', fn: function () {
          U.modal.input({
            title: 'Create branch',
            label: 'Branch name:',
            placeholder: 'feature/my-idea',
            value: ''
          }).then(function (name) {
            if (!name) return;
            guard(function () {
              return apiPost('git-branch-create', { name: name }).then(function (r) {
                toast('🌱 Created + switched to ' + r.branch);
                state.branch = r.branch;
                updateBranchChip();
                refreshChanges();
              });
            });
          });
        }
      });
      showActionSheet('Branches', items.length ? items : [
        { icon: '⎇', label: current || 'HEAD', fn: function () { } },
        { icon: '✨', label: 'New branch…', fn: function () { toast('No branches yet — make a commit first'); } }
      ]);
    }).catch(function (err) { toast('⚠ ' + err.message); });
  }

  function doSwitchBranch(name) {
    guard(function () {
      return apiPost('git-branch-switch', { name: name }).then(function (r) {
        toast('⎇ Switched to ' + r.branch);
        state.branch = r.branch;
        updateBranchChip();
        refreshChanges();
      });
    });
  }

  /* ── ⋯ more menu ──────────────────────────────────────────────── */
  function openMoreMenu() {
    var items = [];
    if (state.isRepo) {
      items.push({ icon: '⬇️', label: 'Pull', fn: doPull });
      items.push({ icon: '⬆️', label: 'Push', fn: doPush });
    } else {
      items.push({ icon: '📦', label: 'Initialize repository', fn: doInit });
    }
    showActionSheet('Repository actions', items);
  }
  function doPull() {
    toast('⬇️ Pulling…');
    guard(function () {
      return apiPost('git-pull', {}).then(function (d) {
        toast('⬇️ ' + String(d.output || 'Up to date.').slice(0, 90));
        refreshChanges();
      });
    });
  }
  function doPush() {
    toast('⬆️ Pushing…');
    guard(function () {
      return apiPost('git-push', {}).then(function (d) {
        toast('⬆️ ' + String(d.output || 'Done.').slice(0, 90));
      });
    });
  }

  /* ═══════════════════════════════════════════════════════════════
  DIFF RENDERING
  ═══════════════════════════════════════════════════════════════ */
  function showDiff(title) {
    if (!diffOverlay) return;
    if (diffTitleEl) diffTitleEl.textContent = title || 'Diff';
    if (diffBody) diffBody.innerHTML = '<span class="mgd-empty">Loading…</span>';
    diffOverlay.classList.remove('hidden');
    void diffOverlay.offsetWidth;
    diffOverlay.classList.add('show');
  }
  function closeDiff() {
    if (!diffOverlay || diffOverlay.classList.contains('hidden')) return;
    diffOverlay.classList.remove('show');
    setTimeout(function () { diffOverlay.classList.add('hidden'); }, 300);
  }
  function renderDiffText(text, note, truncated) {
    lastDiffText = text || '';
    if (!diffBody) return;
    diffBody.innerHTML = '';
    var head = '';
    if (truncated) head += '<div class="mgd-truncated">⚠ Diff truncated — showing the first part only.</div>';
    if (note) head += '<div class="mgd-note">' + esc(note) + '</div>';
    if (!text || !String(text).trim()) {
      diffBody.innerHTML = head + '<span class="mgd-empty">' +
        esc(!text ? 'No differences.' : '') + '</span>';
      if (!text) return;
    }
    var frag = document.createDocumentFragment();
    if (head) {
      var hd = document.createElement('div');
      hd.innerHTML = head;
      frag.appendChild(hd);
    }
    String(text).split('\n').forEach(function (ln) {
      var d = document.createElement('div');
      var cls = '';
      if (ln.indexOf('+++') === 0 || ln.indexOf('---') === 0) cls = 'mgd-file';
      else if (ln.indexOf('diff --git') === 0 || ln.indexOf('index ') === 0) cls = 'mgd-meta';
      else if (ln.charAt(0) === '+') cls = 'mgd-add';
      else if (ln.charAt(0) === '-') cls = 'mgd-del';
      else if (ln.indexOf('@@') === 0) cls = 'mgd-hunk';
      d.className = cls;
      d.textContent = (ln === '') ? ' ' : ln;
      frag.appendChild(d);
    });
    diffBody.appendChild(frag);
  }
  function openFileDiff(f) {
    if (f.index === '?') {
      showDiff(f.path);
      renderDiffText('', 'New file — not tracked yet. Long-press it in Changes → "Track & stage" to include it in your next commit.');
      return;
    }
    showDiff(f.path);
    apiGet('git-diff', { path: f.path })
      .then(function (d) {
        if (diffTitleEl) diffTitleEl.textContent = f.path + (d.staged ? ' (staged view)' : '');
        renderDiffText(d.diff, d.note, d.truncated);
      })
      .catch(function (err) { renderDiffText('', '⚠ ' + err.message, false); });
  }
  function openCommitDiff(c) {
    showDiff(c.short + ' — ' + c.subject);
    apiGet('git-show', { hash: c.hash })
      .then(function (d) {
        var m = d.meta || {};
        if (diffTitleEl) {
          diffTitleEl.textContent = (m.short || c.short) + ' — ' + (m.subject || c.subject);
        }
        renderDiffText(d.diff, '', d.truncated);
      })
      .catch(function (err) { renderDiffText('', '⚠ ' + err.message, false); });
  }

  /* ═══════════════════════════════════════════════════════════════
  WIRING + BOOT
  ═══════════════════════════════════════════════════════════════ */
  function init() {
    if (wired) return;
    wired = true;

    if (closeBtn) closeBtn.addEventListener('click', closeGit);
    if (refreshBtn) refreshBtn.addEventListener('click', function () {
      if (!cfg.features || !cfg.features.git) {
        toast('🌿 Git is currently turned off. You can turn it on in Settings.');
        return;
      }
      refreshAll();
      toast('🌿 Refreshed');
    });
    if (moreBtn) moreBtn.addEventListener('click', openMoreMenu);
    if (branchBtn) branchBtn.addEventListener('click', openBranchSheet);

    if (tabsEl) {
      tabsEl.addEventListener('click', function (e) {
        var b = e.target.closest('.m-git-tab');
        if (b) switchTab(b.getAttribute('data-tab'));
      });
    }

    if (msgInput) {
      msgInput.addEventListener('input', updateCommitState);
      msgInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); doCommit(); }
      });
    }
    if (amendChk) amendChk.addEventListener('change', updateCommitState);
    if (commitBtn) commitBtn.addEventListener('click', doCommit);
    if (loadMoreBtn) loadMoreBtn.addEventListener('click', loadOlderCommits);

    if (diffCloseBtn) diffCloseBtn.addEventListener('click', closeDiff);
    if (diffWrapBtn) diffWrapBtn.addEventListener('click', function () {
      var wrapped = diffBody.classList.toggle('wrap');
      diffWrapBtn.classList.toggle('active', wrapped);
      diffWrapBtn.title = wrapped ? 'Disable word wrap' : 'Enable word wrap';
    });
    if (diffCopyBtn) diffCopyBtn.addEventListener('click', function () {
      if (!lastDiffText) return;
      if (navigator.clipboard) navigator.clipboard.writeText(lastDiffText)
        .then(function () { toast('📋 Diff copied'); })
        .catch(function () { toast('Copy failed'); });
      else toast('Clipboard unavailable');
    });

    /* Escape closes top-most layer only (sheet → diff → panel). */
    document.addEventListener('keydown', function (e) {
      if (e.key !== 'Escape') return;
      if (_sheet) { hideActionSheet(); return; }
      if (diffOverlay && !diffOverlay.classList.contains('hidden')) { closeDiff(); return; }
      if (isOpen) closeGit();
    });

    /* Bridge used by the "More" sheet (Source Control row) */
    window._mobileOpenGit = openGit;
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  /* ═══════════════════════════════════════════════════════════════
  EXPOSE
  ═══════════════════════════════════════════════════════════════ */
  window.IDE = window.IDE || {};
  /**
   * @namespace window.IDE.mobileGit
   * Mobile read-write git source control overlay (v10).
   */
  window.IDE.mobileGit = {
    init: init,
    open: openGit,
    close: closeGit,
    refresh: refreshAll,
    isOpen: function () { return isOpen; }
  };
})();
