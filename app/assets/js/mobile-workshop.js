/**
 * ═══════════════════════════════════════════════════════════════════════════
 *  QUIRKY IDE — MOBILE WORKSHOP MODULE (Phase 2 · Apache + Logging)
 * ═══════════════════════════════════════════════════════════════════════════
 *
 *  The Workshop overlay for the mobile shell. Handles:
 *    • Loading the extension list from the API
 *    • Rendering extension cards with status badges
 *    • Apache install with real-time SSE progress
 *    • Apache start/stop/restart controls
 *    • Live status polling
 *    • ★ Workshop Logs viewer (persistent, selectable, copy/clear)
 *
 *  EXPOSES: window.IDE.mobileWorkshop
 *           window._mobileOpenWorkshop (bridge for mobile-shell.js)
 * ═══════════════════════════════════════════════════════════════════════════
 */
(function () {
  'use strict';
  var U = window.IDE && window.IDE.utils;
  var api = window.IDE && window.IDE.api;
  if (!U) return;

  var overlay = document.getElementById('m-workshop-overlay');
  if (!overlay) return;

  var isOpen = false;
  var statusPollTimer = null;
  var apacheInstalling = false;   // ★ guards polling during SSE install

  /* ═══════════════════════════════════════════════════════════
  OPEN / CLOSE
  ═══════════════════════════════════════════════════════════ */
  function openWorkshop() {
    if (isOpen) return;
    isOpen = true;
    overlay.classList.remove('hidden');
    void overlay.offsetWidth;
    overlay.classList.add('show');
    loadExtensions();
    startStatusPolling();
  }

  function closeWorkshop() {
    if (!isOpen) return;
    isOpen = false;
    overlay.classList.remove('show');
    stopStatusPolling();
    setTimeout(function () {
      overlay.classList.add('hidden');
    }, 300);
  }

  /* ═══════════════════════════════════════════════════════════
  LOAD EXTENSIONS FROM API
  ═══════════════════════════════════════════════════════════ */
  function loadExtensions() {
    var body = document.getElementById('m-workshop-body');
    if (!body) return;

    body.innerHTML = '<div class="m-workshop-loading">' +
      '<div class="m-workshop-spinner"></div>' +
      '<span>Loading extensions…</span>' +
      '</div>';

    fetch('index.php?api=workshop-list', {
      headers: { 'Accept': 'application/json' }
    })
      .then(function (res) { return res.json(); })
      .then(function (j) {
        if (!j.ok) throw new Error(j.error || 'Failed to load extensions');
        renderExtensions(j.data.extensions || []);
        /* ★ Dynamic Workshop: community packages arrive alongside the
           static extensions as their own section (spec F). */
        renderCommunityShell();
        fillCommunity(j.data.community);
        loadCommunityCatalog();
      })
      .catch(function (err) {
        body.innerHTML = '<div class="m-workshop-error">' +
          '<span class="m-workshop-error-icon">⚠️</span>' +
          '<p>' + U.escapeHtml(err.message) + '</p>' +
          '<button class="m-workshop-retry-btn" id="m-workshop-retry">🔄 Retry</button>' +
          '</div>';
        var retryBtn = document.getElementById('m-workshop-retry');
        if (retryBtn) {
          retryBtn.addEventListener('click', loadExtensions);
        }
      });
  }

  /* ═══════════════════════════════════════════════════════════
  RENDER EXTENSION CARDS
  ═══════════════════════════════════════════════════════════ */
  function renderExtensions(extensions) {
    var body = document.getElementById('m-workshop-body');
    if (!body) return;

    if (!extensions || extensions.length === 0) {
      body.innerHTML = '<div class="m-workshop-empty">' +
        '<span class="m-workshop-empty-icon">🔧</span>' +
        '<p>No extensions available yet.</p>' +
        '</div>';
      return;
    }

    var html = '<div class="m-workshop-intro">' +
      '<p>Extensions add extra capabilities to your IDE. ' +
      'Install them when you need them, remove them when you don\'t.</p>' +
      '</div>';

    extensions.forEach(function (ext) {
      html += renderExtensionCard(ext);
    });

    body.innerHTML = html;
    wireExtensionButtons(body);
  }

  function renderExtensionCard(ext) {
    var statusClass, statusText;
    if (!ext.installed) {
      statusClass = 'not-installed';
      statusText = 'Not Installed';
    } else if (ext.id === 'apache' && ext.running) {
      statusClass = 'running';
      statusText = '● Running (port ' + ext.activePort + ')';
    } else if (ext.enabled) {
      statusClass = 'enabled';
      statusText = 'Enabled';
    } else {
      statusClass = 'installed';
      statusText = 'Installed';
    }

    var actionBtns = '';
    if (!ext.installed) {
      actionBtns = '<button class="m-workshop-ext-btn m-workshop-ext-btn-primary" ' +
        'data-workshop-action="install" data-workshop-id="' + ext.id + '">' +
        '⬇ Install</button>';
    } else {
      if (ext.id === 'apache') {
        if (ext.running) {
          actionBtns = '<button class="m-workshop-ext-btn m-workshop-ext-btn-danger" ' +
            'data-workshop-action="apache-stop" data-workshop-id="' + ext.id + '">' +
            '⏹ Stop</button>' +
            '<button class="m-workshop-ext-btn m-workshop-ext-btn-primary" ' +
            'data-workshop-action="apache-restart" data-workshop-id="' + ext.id + '">' +
            '🔄 Restart</button>';
        } else {
          actionBtns = '<button class="m-workshop-ext-btn m-workshop-ext-btn-primary" ' +
            'data-workshop-action="apache-start" data-workshop-id="' + ext.id + '">' +
            '▶ Start</button>';
        }
        actionBtns += '<button class="m-workshop-ext-btn m-workshop-ext-btn-ghost" ' +
          'data-workshop-action="uninstall" data-workshop-id="' + ext.id + '">' +
          '🗑</button>';
      } else {
        actionBtns = '<button class="m-workshop-ext-btn m-workshop-ext-btn-primary" ' +
          'data-workshop-action="toggle" data-workshop-id="' + ext.id + '">' +
          (ext.enabled ? '⏸ Disable' : '▶ Enable') + '</button>' +
          '<button class="m-workshop-ext-btn m-workshop-ext-btn-danger" ' +
          'data-workshop-action="uninstall" data-workshop-id="' + ext.id + '">' +
          '🗑 Uninstall</button>';
      }
    }

    var providesHtml = '';
    if (ext.provides && ext.provides.length > 0) {
      providesHtml = '<div class="m-workshop-ext-provides">';
      ext.provides.forEach(function (p) {
        providesHtml += '<span class="m-workshop-provide-tag">' + U.escapeHtml(p) + '</span>';
      });
      providesHtml += '</div>';
    }

    return '<div class="m-workshop-ext-card" data-ext-id="' + ext.id + '">' +
      '<div class="m-workshop-ext-header">' +
      '<span class="m-workshop-ext-icon">' + ext.icon + '</span>' +
      '<div class="m-workshop-ext-titles">' +
      '<div class="m-workshop-ext-name">' + U.escapeHtml(ext.name) + '</div>' +
      '<span class="m-workshop-ext-status ' + statusClass + '">' + statusText + '</span>' +
      '</div>' +
      '</div>' +
      '<div class="m-workshop-ext-desc">' + U.escapeHtml(ext.description) + '</div>' +
      providesHtml +
      '<div class="m-workshop-ext-footer">' +
      '<span class="m-workshop-ext-size">📦 ' + U.escapeHtml(ext.size) + '</span>' +
      '<div class="m-workshop-ext-actions">' + actionBtns + '</div>' +
      '</div>' +
      '<div class="m-workshop-install-progress hidden" id="install-progress-' + ext.id + '">' +
      '<div class="m-workshop-progress-log" id="install-log-' + ext.id + '"></div>' +
      '</div>' +
      '</div>';
  }

  /* ═══════════════════════════════════════════════════════════
  WIRE BUTTON CLICKS
  ═══════════════════════════════════════════════════════════ */
  function wireExtensionButtons(container) {
    container.querySelectorAll('[data-workshop-action]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var action = btn.getAttribute('data-workshop-action');
        var id = btn.getAttribute('data-workshop-id');
        handleAction(action, id, btn);
      });
    });
  }

  function handleAction(action, id, btn) {
    if (action === 'apache-start') { apacheAction('start', id, btn); return; }
    if (action === 'apache-stop') { apacheAction('stop', id, btn); return; }
    if (action === 'apache-restart') { apacheAction('restart', id, btn); return; }

    if (action === 'install' && id === 'apache') {
      installApacheStream(id, btn);
      return;
    }

    btn.disabled = true;
    var originalText = btn.textContent;
    btn.textContent = '⏳ Working…';

    var endpoint = 'workshop-' + action;

    fetch('index.php?api=' + endpoint, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify({ id: id })
    })
      .then(function (res) { return res.json(); })
      .then(function (j) {
        if (!j.ok) throw new Error(j.error || 'Action failed');
        var successMsg = '';
        if (action === 'install') successMsg = '✓ Extension installed';
        else if (action === 'uninstall') successMsg = '✓ Extension removed';
        else if (action === 'toggle') successMsg = j.data.enabled ? '✓ Extension enabled' : '✓ Extension disabled';
        if (U.toast) U.toast(successMsg, 'success');
        loadExtensions();
      })
      .catch(function (err) {
        if (U.toast) U.toast('⚠ ' + err.message, 'error');
        btn.disabled = false;
        btn.textContent = originalText;
      });
  }

  /* ═══════════════════════════════════════════════════════════
  APACHE INSTALL WITH SSE STREAMING (Phase 2)
  ═══════════════════════════════════════════════════════════ */
  function installApacheStream(id, btn) {
    apacheInstalling = true;          // ★ block polling during install
    btn.disabled = true;
    btn.textContent = '⏳ Installing…';

    var progressEl = document.getElementById('install-progress-' + id);
    var logEl = document.getElementById('install-log-' + id);
    if (progressEl) progressEl.classList.remove('hidden');
    if (logEl) logEl.innerHTML = '';

    function appendLog(text, cls) {
      if (!logEl) return;
      var line = document.createElement('div');
      line.className = 'm-workshop-log-line' + (cls ? ' ' + cls : '');
      line.textContent = text;
      logEl.appendChild(line);
      logEl.scrollTop = logEl.scrollHeight;
    }

    fetch((window.IDE_STREAM_BASE || 'index.php') + '?api=workshop-apache-install', {
      method: 'POST',
      credentials: 'include',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'text/event-stream'
      },
      body: JSON.stringify({})
    })
      .then(function (response) {
        if (!response.ok) throw new Error('HTTP ' + response.status);
        return readSSEStream(response, {
          onStatus: function (data) { appendLog(data.message || ''); },
          onProgress: function (data) { if (data.text) appendLog(data.text, 'log-dim'); },
          onOut: function (data) { if (data.text) appendLog(data.text); },
          onErr: function (data) { if (data.text) appendLog(data.text, 'log-error'); },
          onError: function (data) { appendLog('✗ ' + (data.message || 'Error'), 'log-error'); },
          onDone: function (data) {
            apacheInstalling = false;   // ★ release polling guard
            if (data.success) {
              appendLog('✓ Installation complete!', 'log-success');
              if (U.toast) U.toast('🌐 Apache installed!', 'success');
              setTimeout(function () { loadExtensions(); }, 1000);
            } else {
              appendLog('✗ Installation failed.', 'log-error');
              if (U.toast) U.toast('⚠ Apache install failed — check Workshop Logs', 'error');
              btn.disabled = false;
              btn.textContent = '⬇ Install';
            }
          }
        });
      })
      .catch(function (err) {
        apacheInstalling = false;           // ★ release polling guard
        appendLog('✗ ' + err.message, 'log-error');
        if (U.toast) U.toast('⚠ Install error — check Workshop Logs', 'error');
        btn.disabled = false;
        btn.textContent = '⬇ Install';
      });
  }

  /**
   * Read an SSE stream from a fetch Response.
   */
  function readSSEStream(response, handlers) {
    var reader = response.body.getReader();
    var decoder = new TextDecoder();
    var buffer = '';

    function processBlock(block) {
      var event = 'message';
      var data = '';
      block.split('\n').forEach(function (line) {
        if (line.indexOf('event:') === 0) event = line.slice(6).trim();
        else if (line.indexOf('data:') === 0) data += line.slice(5).trim();
      });
      if (data === '') return;
      var payload;
      try { payload = JSON.parse(data); } catch (e) { return; }

      if (event === 'status' && handlers.onStatus) handlers.onStatus(payload);
      else if (event === 'progress' && handlers.onProgress) handlers.onProgress(payload);
      else if (event === 'out' && handlers.onOut) handlers.onOut(payload);
      else if (event === 'err' && handlers.onErr) handlers.onErr(payload);
      else if (event === 'error' && handlers.onError) handlers.onError(payload);
      else if (event === 'done' && handlers.onDone) handlers.onDone(payload);
    }

    function pump() {
      return reader.read().then(function (result) {
        if (result.done) {
          if (buffer.trim() !== '') processBlock(buffer);
          return;
        }
        buffer += decoder.decode(result.value, { stream: true });
        var idx;
        while ((idx = buffer.indexOf('\n\n')) !== -1) {
          var block = buffer.slice(0, idx);
          buffer = buffer.slice(idx + 2);
          processBlock(block);
        }
        return pump();
      });
    }
    return pump();
  }

  /* ═══════════════════════════════════════════════════════════
  APACHE START / STOP / RESTART (Phase 2)
  ═══════════════════════════════════════════════════════════ */
  function apacheAction(action, id, btn) {
    btn.disabled = true;
    var originalText = btn.textContent;
    btn.textContent = '⏳…';

    fetch('index.php?api=workshop-apache-' + action, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify({})
    })
      .then(function (res) { return res.json(); })
      .then(function (j) {
        if (!j.ok) throw new Error(j.error || 'Action failed');
        var data = j.data;
        if (action === 'start') {
          if (data.started) {
            if (U.toast) U.toast('🌐 Apache started on port ' + data.port, 'success');
          } else {
            if (U.toast) U.toast('⚠ Start failed — check Workshop Logs', 'error');
          }
        } else if (action === 'stop') {
          if (data.stopped) {
            if (U.toast) U.toast('⏹ Apache stopped', 'success');
          } else {
            if (U.toast) U.toast('⚠ ' + (data.reason || 'Failed to stop'), 'error');
          }
        } else if (action === 'restart') {
          if (data.started) {
            if (U.toast) U.toast('🔄 Apache restarted on port ' + data.port, 'success');
          } else {
            if (U.toast) U.toast('⚠ Restart failed — check Workshop Logs', 'error');
          }
        }
        /* ★ Small grace period so the port-check on the server
           side catches up before we re-render the cards.       */
        setTimeout(function () { loadExtensions(); }, 400);
      })
      .catch(function (err) {
        if (U.toast) U.toast('⚠ ' + err.message, 'error');
        btn.disabled = false;
        btn.textContent = originalText;
      });
  }

  /* ═══════════════════════════════════════════════════════════
  STATUS POLLING (Phase 2)
  ═══════════════════════════════════════════════════════════ */
  function startStatusPolling() {
    stopStatusPolling();
    statusPollTimer = setInterval(function () {
      if (!isOpen) return;
      fetch('index.php?api=workshop-apache-status', {
        headers: { 'Accept': 'application/json' }
      })
        .then(function (res) { return res.json(); })
        .then(function (j) {
          if (!j.ok) return;
          updateApacheStatusUI(j.data);
        })
        .catch(function () { /* silent */ });
    }, 5000);
  }

  function stopStatusPolling() {
    if (statusPollTimer) {
      clearInterval(statusPollTimer);
      statusPollTimer = null;
    }
  }

  function updateApacheStatusUI(status) {
    /* ── Never fight the SSE install stream ── */
    if (apacheInstalling) return;

    var card = document.querySelector('[data-ext-id="apache"]');
    if (!card) return;

    var statusEl = card.querySelector('.m-workshop-ext-status');
    var actionsEl = card.querySelector('.m-workshop-ext-actions');

    /* ── 1. Update the status badge (all three states) ── */
    if (status.running) {
      if (statusEl) {
        statusEl.className = 'm-workshop-ext-status running';
        statusEl.textContent = '● Running (port ' + status.port + ')';
      }
    } else if (status.installed) {
      if (statusEl) {
        statusEl.className = 'm-workshop-ext-status installed';
        statusEl.textContent = '○ Stopped';
      }
    } else {
      /* ★ FIX Issue 2: explicit "Not Installed" state */
      if (statusEl) {
        statusEl.className = 'm-workshop-ext-status not-installed';
        statusEl.textContent = 'Not Installed';
      }
    }

    /* ── 2. Update the action buttons to match ──
       Skip when a button is disabled (= an action is already
       in progress) so we don't yank the rug mid-tap.        */
    if (!actionsEl || actionsEl.querySelector('button:disabled')) return;

    var html = '';
    if (status.running) {
      html =
        '<button class="m-workshop-ext-btn m-workshop-ext-btn-danger" ' +
        'data-workshop-action="apache-stop" data-workshop-id="apache">' +
        '⏹ Stop</button>' +
        '<button class="m-workshop-ext-btn m-workshop-ext-btn-primary" ' +
        'data-workshop-action="apache-restart" data-workshop-id="apache">' +
        '🔄 Restart</button>' +
        '<button class="m-workshop-ext-btn m-workshop-ext-btn-ghost" ' +
        'data-workshop-action="uninstall" data-workshop-id="apache">' +
        '🗑</button>';
    } else if (status.installed) {
      html =
        '<button class="m-workshop-ext-btn m-workshop-ext-btn-primary" ' +
        'data-workshop-action="apache-start" data-workshop-id="apache">' +
        '▶ Start</button>' +
        '<button class="m-workshop-ext-btn m-workshop-ext-btn-ghost" ' +
        'data-workshop-action="uninstall" data-workshop-id="apache">' +
        '🗑</button>';
    } else {
      html =
        '<button class="m-workshop-ext-btn m-workshop-ext-btn-primary" ' +
        'data-workshop-action="install" data-workshop-id="apache">' +
        '⬇ Install</button>';
    }

    actionsEl.innerHTML = html;
    wireExtensionButtons(actionsEl);
  }

  /* ═══════════════════════════════════════════════════════════
  ★ WORKSHOP LOGS VIEWER
  ═══════════════════════════════════════════════════════════ */
  var logsOverlayOpen = false;

  function openLogs() {
    var logsOverlay = document.getElementById('m-workshop-logs-overlay');
    if (!logsOverlay) return;

    logsOverlayOpen = true;
    logsOverlay.classList.remove('hidden');
    void logsOverlay.offsetWidth;
    logsOverlay.classList.add('show');
    fetchLogs();
  }

  function closeLogs() {
    if (!logsOverlayOpen) return;
    logsOverlayOpen = false;
    var logsOverlay = document.getElementById('m-workshop-logs-overlay');
    if (!logsOverlay) return;
    logsOverlay.classList.remove('show');
    setTimeout(function () {
      logsOverlay.classList.add('hidden');
    }, 300);
  }

  function fetchLogs() {
    var body = document.getElementById('m-workshop-logs-body');
    if (!body) return;

    body.innerHTML = '<div class="m-workshop-loading">' +
      '<div class="m-workshop-spinner"></div>' +
      '<span>Loading logs…</span>' +
      '</div>';

    fetch('index.php?api=workshop-logs', {
      headers: { 'Accept': 'application/json' }
    })
      .then(function (res) { return res.json(); })
      .then(function (j) {
        if (!j.ok) throw new Error(j.error || 'Failed to load logs');
        renderLogs(j.data);
      })
      .catch(function (err) {
        body.innerHTML = '<div class="m-workshop-error">' +
          '<span class="m-workshop-error-icon">⚠️</span>' +
          '<p>' + U.escapeHtml(err.message) + '</p>' +
          '</div>';
      });
  }

  function renderLogs(data) {
    var body = document.getElementById('m-workshop-logs-body');
    var countEl = document.getElementById('m-workshop-logs-count');
    if (!body) return;

    var lines = data.lines || [];

    if (countEl) {
      countEl.textContent = lines.length + ' entr' + (lines.length === 1 ? 'y' : 'ies');
    }

    if (lines.length === 0) {
      body.innerHTML = '<div class="m-workshop-empty">' +
        '<span class="m-workshop-empty-icon">📋</span>' +
        '<p>No logs yet. Install or start Apache to generate logs.</p>' +
        '</div>';
      return;
    }

    var html = '';
    lines.forEach(function (line) {
      var levelClass = 'log-info';
      if (line.level === 'ERROR') levelClass = 'log-error';
      else if (line.level === 'WARN') levelClass = 'log-warn';
      else if (line.level === 'SUCCESS') levelClass = 'log-success';

      var sourceTag = line.source ? ' <span class="m-wslog-source">' + U.escapeHtml(line.source) + '</span>' : '';

      html += '<div class="m-wslog-entry ' + levelClass + '">' +
        '<span class="m-wslog-time">' + U.escapeHtml(line.time) + '</span>' +
        sourceTag +
        '<span class="m-wslog-msg">' + U.escapeHtml(line.message) + '</span>' +
        '</div>';
    });

    body.innerHTML = html;

    // Scroll to bottom (newest entries)
    body.scrollTop = body.scrollHeight;
  }

  function copyLogs() {
    fetch('index.php?api=workshop-logs', {
      headers: { 'Accept': 'application/json' }
    })
      .then(function (res) { return res.json(); })
      .then(function (j) {
        if (!j.ok) throw new Error(j.error || 'Failed to load logs');
        var lines = j.data.lines || [];
        var text = lines.map(function (l) {
          return '[' + l.time + '] [' + l.level + '] [' + l.source + '] ' + l.message;
        }).join('\n');

        if (U && U.copyToClipboard) {
          return U.copyToClipboard(text).then(function (ok) {
            if (U.toast) U.toast(ok ? '📋 Logs copied' : '⚠ Copy failed', ok ? 'success' : 'error');
          });
        }
        if (U.toast) U.toast('Clipboard not available', 'error');
      })
      .catch(function (err) {
        if (U.toast) U.toast('⚠ ' + err.message, 'error');
      });
  }

  function clearLogs() {
    fetch('index.php?api=workshop-logs-clear', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: '{}'
    })
      .then(function (res) { return res.json(); })
      .then(function (j) {
        if (!j.ok) throw new Error(j.error || 'Failed to clear logs');
        if (U.toast) U.toast('🗑 Logs cleared', 'success');
        fetchLogs(); // Refresh the view
      })
      .catch(function (err) {
        if (U.toast) U.toast('⚠ ' + err.message, 'error');
      });
  }

  /* ═══════════════════════════════════════════════════════════
★ WORKSHOP DIAGNOSTICS VIEWER (run / copy / clear)
════════════════════════════════════════════════════════════ */
  var diagOverlayOpen = false;
  var diagReportText = '';

  function openDiag() {
    var diagOverlay = document.getElementById('m-workshop-diag-overlay');
    if (!diagOverlay) return;
    diagOverlayOpen = true;
    diagOverlay.classList.remove('hidden');
    void diagOverlay.offsetWidth;
    diagOverlay.classList.add('show');
    fetchDiag();
  }

  function closeDiag() {
    if (!diagOverlayOpen) return;
    diagOverlayOpen = false;
    var diagOverlay = document.getElementById('m-workshop-diag-overlay');
    if (!diagOverlay) return;
    diagOverlay.classList.remove('show');
    setTimeout(function () {
      diagOverlay.classList.add('hidden');
    }, 300);
  }

  function fetchDiag() {
    var body = document.getElementById('m-workshop-diag-body');
    var countEl = document.getElementById('m-workshop-diag-count');
    if (!body) return;
    body.innerHTML = '<div class="m-workshop-loading">' +
      '<div class="m-workshop-spinner"></div>' +
      '<span>Running checks…</span>' +
      '</div>';
    fetch('index.php?api=workshop-apache-diagnose', {
      headers: { 'Accept': 'application/json' }
    })
      .then(function (res) { return res.json(); })
      .then(function (j) {
        if (!j.ok) throw new Error(j.error || 'Failed to run diagnostics');
        renderDiag(j.data);
      })
      .catch(function (err) {
        body.innerHTML = '<div class="m-workshop-error">' +
          '<span class="m-workshop-error-icon">⚠️</span>' +
          '<p>' + U.escapeHtml(err.message) + '</p>' +
          '</div>';
        if (countEl) countEl.textContent = 'error';
      });
  }

  function renderDiag(data) {
    var body = document.getElementById('m-workshop-diag-body');
    var countEl = document.getElementById('m-workshop-diag-count');
    if (!body) return;
    var checks = data.checks || [];
    var verdict = data.verdict || '';
    var failN = 0;
    var lines = [];
    lines.push('QUIRKY IDE — APACHE DIAGNOSTICS');
    lines.push('Time: ' + new Date().toLocaleString());
    lines.push('──────────────────────────────────────');
    var html = '';
    checks.forEach(function (c) {
      if (!c.pass) failN++;
      lines.push((c.pass ? '[PASS] ' : '[FAIL] ') + c.check + ' — ' + c.detail);
      html += '<div class="m-wsdiag-entry ' + (c.pass ? 'pass' : 'fail') + '">' +
        '<div class="m-wsdiag-name">' + (c.pass ? '✓ ' : '✗ ') + U.escapeHtml(c.check) + '</div>' +
        '<div class="m-wsdiag-detail">' + U.escapeHtml(c.detail) + '</div>' +
        '</div>';
    });
    lines.push('──────────────────────────────────────');
    lines.push('VERDICT: ' + verdict);
    diagReportText = lines.join('\n');
    var allPass = checks.length > 0 && failN === 0;
    body.innerHTML = '<div class="m-wsdiag-verdict ' + (allPass ? 'pass' : 'fail') + '">' +
      (allPass ? '✅ ' : '⚠️ ') + U.escapeHtml(verdict) + '</div>' + html;
    body.scrollTop = 0;
    if (countEl) {
      countEl.textContent = checks.length + ' checks · ' + failN + ' failed';
    }
  }

  function copyDiag() {
    var doCopy = function (text) {
      if (U && U.copyToClipboard) {
        return U.copyToClipboard(text).then(function (ok) {
          if (U.toast) U.toast(ok ? '📋 Diagnostics copied' : '⚠ Copy failed', ok ? 'success' : 'error');
        });
      }
      if (U.toast) U.toast('Clipboard not available', 'error');
      return Promise.resolve();
    };
    /* Nothing on screen yet — fetch a fresh report first, then copy it */
    if (diagReportText === '') {
      fetch('index.php?api=workshop-apache-diagnose', {
        headers: { 'Accept': 'application/json' }
      })
        .then(function (res) { return res.json(); })
        .then(function (j) {
          if (!j.ok) throw new Error(j.error || 'Failed');
          renderDiag(j.data);
          return doCopy(diagReportText);
        })
        .catch(function (err) {
          if (U.toast) U.toast('⚠ ' + err.message, 'error');
        });
      return;
    }
    doCopy(diagReportText);
  }

  function clearDiag() {
    var body = document.getElementById('m-workshop-diag-body');
    var countEl = document.getElementById('m-workshop-diag-count');
    diagReportText = '';
    if (body) {
      body.innerHTML = '<div class="m-workshop-empty">' +
        '<span class="m-workshop-empty-icon">🩺</span>' +
        '<p>Diagnostics cleared.<br>Tap "🔄 Run" to check again.</p>' +
        '</div>';
    }
    if (countEl) countEl.textContent = '—';
    if (U.toast) U.toast('🗑 Diagnostics cleared', 'success');
  }

  /* ═══════════════════════════════════════════════════════════
  ★ PHASE 3: DETECTED PROJECTS
  ═══════════════════════════════════════════════════════════ */
  function loadProjects() {
    fetch('index.php?api=workshop-status', {
      headers: { 'Accept': 'application/json' }
    })
      .then(function (res) { return res.json(); })
      .then(function (j) {
        if (!j.ok) return;
        renderProjects(j.data.projects || []);
      })
      .catch(function () { /* silent */ });
  }

  function renderProjects(projects) {
    var container = document.getElementById('m-workshop-projects');
    if (!container) return;

    if (!projects || projects.length === 0) {
      container.innerHTML = '<div class="m-workshop-empty">' +
        '<span class="m-workshop-empty-icon">📂</span>' +
        '<p>No projects detected.<br>Add a <code>quirky.setup</code> file to a folder to register it as a project.</p>' +
        '</div>';
      return;
    }

    var html = '<div class="m-workshop-intro">' +
      '<p>🔧 <strong>' + projects.length + '</strong> project' + (projects.length === 1 ? '' : 's') + ' detected via <code>quirky.setup</code></p>' +
      '</div>';

    projects.forEach(function (proj) {
      var stackIcon = '📄';
      if (proj.stack === 'php') stackIcon = '🐘';
      else if (proj.stack === 'html') stackIcon = '🌐';
      else if (proj.stack === 'python') stackIcon = '🐍';
      else if (proj.stack === 'node') stackIcon = '🟢';

      var htaccessTag = proj.hasHtaccess
        ? '<span class="m-workshop-provide-tag">.htaccess</span>'
        : '';

      html += '<div class="m-workshop-ext-card">' +
        '<div class="m-workshop-ext-header">' +
        '<span class="m-workshop-ext-icon">' + stackIcon + '</span>' +
        '<div class="m-workshop-ext-titles">' +
        '<div class="m-workshop-ext-name">' + U.escapeHtml(proj.name) + '</div>' +
        '<span class="m-workshop-ext-status installed">' + U.escapeHtml(proj.stack.toUpperCase()) + '</span>' +
        '</div>' +
        '</div>' +
        '<div class="m-workshop-ext-desc">' +
        '📁 ' + U.escapeHtml(proj.path) + '/' +
        (proj.router ? ' · Router: <code>' + U.escapeHtml(proj.router) + '</code>' : '') +
        '</div>' +
        '<div class="m-workshop-ext-provides">' +
        '<span class="m-workshop-provide-tag">' + U.escapeHtml(proj.stack) + '</span>' +
        htaccessTag +
        '<span class="m-workshop-provide-tag">entry: ' + U.escapeHtml(proj.entry) + '</span>' +
        '</div>' +
        '</div>';
    });

    container.innerHTML = html;
  }

  /* ═══════════════════════════════════════════════════════════
  ★ DYNAMIC WORKSHOP · COMMUNITY PACKAGES (Manifest v1)

  Users paste a GitHub repo / zip / manifest link; installs stream over SSE
  using the same event protocol as the apache installer (readSSEStream below),
  so one reader serves both. Runtime application lives in package-host.js
  (window.IDE.packages) — this UI only manages the registry + consent.
  ═══════════════════════════════════════════════════════════ */

  function pkgHost() {
    return window.IDE && window.IDE.packages;
  }

  /* ── Section shell (inserted ABOVE the static extension cards) ── */
  function renderCommunityShell() {
    var body = document.getElementById('m-workshop-body');
    if (!body || document.getElementById('m-workshop-community')) return;
    var shell = document.createElement('div');
    shell.id = 'm-workshop-community';
    shell.innerHTML =
      '<div class="m-wspkg-section-head">' +
      '<span class="m-wspkg-section-title">COMMUNITY PACKAGES</span>' +
      '<button class="m-wspkg-add-btn" id="m-wspkg-add">＋ Add from GitHub or URL</button>' +
      '</div>' +
      '<div class="m-wspkg-safemode">' +
      '<label class="m-wspkg-switch">' +
      '<input type="checkbox" id="m-wspkg-safemode-check">' +
      '<span class="m-wspkg-slider"></span>' +
      '</label>' +
      '<div class="m-wspkg-safemode-text">' +
      '<div class="m-wspkg-safemode-title">Safe mode</div>' +
      '<div class="m-wspkg-safemode-sub">Blocks all packages until turned off.</div>' +
      '</div>' +
      '</div>' +
      '<div id="m-wspkg-cards"><div class="m-workshop-loading"><span>Loading packages…</span></div></div>';
    body.insertBefore(shell, body.firstChild);

    shell.querySelector('#m-wspkg-add').addEventListener('click', openAddSheet);
    var safeCheck = shell.querySelector('#m-wspkg-safemode-check');
    if (pkgHost()) safeCheck.checked = pkgHost().isSafeMode();
    else safeCheck.checked = false;
    safeCheck.addEventListener('change', function () {
      var on = safeCheck.checked;
      if (pkgHost()) {
        pkgHost().safeMode(on);
        if (!on) pkgHost().refresh();
      }
      if (U.toast) U.toast(on ? '🛡 Safe mode ON — all packages blocked' : '✓ Safe mode off', 'success');
    });
  }

  /* ── Data plumbing: workshop-list gives a fast first paint, then the
        dedicated catalog endpoint is authoritative ── */
  function fillCommunity(packages) {
    if (Array.isArray(packages)) renderPackages(packages);
  }

  function loadCommunityCatalog() {
    fetch('index.php?api=workshop-catalog', {
      headers: { 'Accept': 'application/json' }
    })
      .then(function (res) { return res.json(); })
      .then(function (j) {
        if (!j.ok) return;
        renderPackages(j.data.packages || []);
      })
      .catch(function () { /* catalog is optional decoration for extensions */ });
  }

  function renderPackages(packages) {
    var wrap = document.getElementById('m-wspkg-cards');
    if (!wrap) return;

    cacheRows(packages);

    if (!packages.length) {
      wrap.innerHTML = '<div class="m-wspkg-empty">' +
        'No community packages yet.<br>Paste a repo link to install your first one.' +
        '</div>';
      return;
    }

    var html = '';
    packages.forEach(function (p) { html += renderPackageCard(p); });
    wrap.innerHTML = html;
    wirePackageCards(wrap);
    // Reflect actual runtime state (applied nodes / consent) where available.
    if (pkgHost()) {
      pkgHost().list().then(function (live) {
        var byId = {};
        live.forEach(function (p) { byId[p.id] = p; });
        wrap.querySelectorAll('[data-pkg-id]').forEach(function (card) {
          var p = byId[card.getAttribute('data-pkg-id')];
          if (!p) return;
          var badge = card.querySelector('.m-wspkg-runstate');
          if (!badge) return;
          if (p.runningUnsafe) {
            badge.textContent = '● code active';
            badge.className = 'm-wspkg-runstate active';
          } else if (p.appliedNow) {
            badge.textContent = '● applied';
            badge.className = 'm-wspkg-runstate applied';
          }
        });
      });
    }
  }

  function renderPackageCard(p) {
    var icon = renderPkgIcon(p);
    var chipClass = { theme: 'theme', snippets: 'snippets', extension: 'extension', toolchain: 'toolchain' }[p.type] || 'theme';

    var consentBits = [];
    if ((p.capabilities || {}).jsRun) consentBits.push('runs code');
    if ((p.capabilities || {}).storage) consentBits.push('stores data');
    if (((p.capabilities || {}).network || []).length) {
      consentBits.push('network: ' + p.capabilities.network.join(', '));
    }
    if (p.toolchain) consentBits.push('needs pkg ' + p.toolchain);
    var consentSummary = consentBits.length
      ? consentBits.join(' · ')
      : 'No special permissions (styles only).';

    var authorLine = p.author && p.author.name ? ' by ' + U.escapeHtml(p.author.name) : '';

    return '<div class="m-workshop-ext-card m-wspkg-card" data-pkg-id="' + U.escapeHtml(p.id) + '">' +
      '<div class="m-workshop-ext-header">' +
      '<span class="m-workshop-ext-icon">' + icon + '</span>' +
      '<div class="m-workshop-ext-titles">' +
      '<div class="m-workshop-ext-name">' + U.escapeHtml(p.name) +
      ' <span class="m-wspkg-version">v' + U.escapeHtml(p.version) + '</span></div>' +
      '<span class="m-workshop-provide-tag m-wspkg-type-' + chipClass + '">' + U.escapeHtml(p.type) + '</span>' +
      '<span class="m-wspkg-runstate" data-runstate="' + U.escapeHtml(p.id) + '"></span>' +
      '</div>' +
      '<label class="m-wspkg-switch">' +
      '<input type="checkbox" data-wspkg-toggle="' + U.escapeHtml(p.id) + '"' + (p.enabled ? ' checked' : '') + '>' +
      '<span class="m-wspkg-slider"></span>' +
      '</label>' +
      '</div>' +
      '<div class="m-workshop-ext-desc">' + U.escapeHtml(p.description) + '</div>' +
      '<div class="m-workshop-ext-provides">' + capabilityBadges(p.capabilities || {}) + (p.toolchain ? '<span class="m-workshop-provide-tag">pkg:' + U.escapeHtml(p.toolchain) + '</span>' : '') + '</div>' +
      '<div class="m-wspkg-consent-summary">' + U.escapeHtml(consentSummary) + authorLine + '</div>' +
      '<div class="m-workshop-ext-footer">' +
      '<span class="m-workshop-ext-size">' + U.escapeHtml(sourceLabel(p)) + '</span>' +
      '<div class="m-workshop-ext-actions">' +
      '<button class="m-workshop-ext-btn m-workshop-ext-btn-primary" data-wspkg-action="update" data-pkg-id="' + U.escapeHtml(p.id) + '">🔄 Update</button>' +
      '<button class="m-workshop-ext-btn m-workshop-ext-btn-danger" data-wspkg-action="remove" data-pkg-id="' + U.escapeHtml(p.id) + '">🗑 Remove</button>' +
      '</div>' +
      '</div>' +
      '<div class="m-workshop-install-progress hidden" id="wspkg-update-progress-' + U.escapeHtml(p.id) + '">' +
      '<div class="m-workshop-progress-log" id="wspkg-update-log-' + U.escapeHtml(p.id) + '"></div>' +
      '</div>' +
      '</div>';
  }

  function renderPkgIcon(p) {
    var icon = String(p.icon || '');
    var isEmoji = /^[\u{1F000}-\u{1FAFF}\u{2190}-\u{2BFF}\u{FE0F}]{1,4}$/u.test(icon);
    if (icon !== '' && !isEmoji) {
      return '<img class="m-wspkg-icon-img" src="' + U.escapeHtml(pkgAssetUrl(p.id, icon, p.version)) + '" alt="">';
    }
    return U.escapeHtml(icon || '📦');
  }

  function pkgAssetUrl(id, file, version) {
    return 'index.php?api=workshop-asset&id=' + encodeURIComponent(id) +
      '&f=' + encodeURIComponent(file) + '&v=' + encodeURIComponent(version || '');
  }

  function capabilityBadges(caps) {
    var out = '';
    if (caps.cssInject) out += '<span class="m-wspkg-badge css">CSS</span>';
    if (caps.jsRun) out += '<span class="m-wspkg-badge js">JS</span>';
    if (caps.storage) out += '<span class="m-wspkg-badge store">STORE</span>';
    if (Array.isArray(caps.network) && caps.network.length) {
      out += '<span class="m-wspkg-badge net">NET·' + caps.network.length + '</span>';
    }
    return out;
  }

  function sourceLabel(p) {
    if (p.sourceKind === 'github-zip') return 'GitHub' + (p.sourceRef ? '@' + p.sourceRef : '');
    if (p.sourceKind === 'zip-url') return 'zip URL';
    if (p.sourceKind === 'raw-manifest') return 'manifest URL';
    return 'installed ' + new Date((p.installedAt || 0) * 1000).toLocaleDateString();
  }

  function wirePackageCards(container) {
    container.querySelectorAll('[data-wspkg-toggle]').forEach(function (check) {
      check.addEventListener('change', function () {
        var id = check.getAttribute('data-wspkg-toggle');
        var wantOn = check.checked;
        var pkg = rowCache[id] || null;
        if (wantOn && pkg && needsConsent(pkg)) {
          check.checked = false; // consent sheet decides
          stashConsentTarget(pkg);
          openConsentSheet(pkg);
          return;
        }
        applyToggle(id, wantOn, check);
      });
    });

    container.querySelectorAll('[data-wspkg-action]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var action = btn.getAttribute('data-wspkg-action');
        var id = btn.getAttribute('data-pkg-id');
        if (action === 'update') updatePackageFlow(id, btn);
        else if (action === 'remove') removePackageFlow(id, btn);
      });
    });
  }

  /** Remember catalog rows so the toggle handler can read capabilities. */
  var rowCache = {};

  function cacheRows(packages) {
    packages.forEach(function (p) { rowCache[p.id] = p; });
  }

  function needsConsent(pkg) {
    var caps = pkg.capabilities || {};
    return !!caps.jsRun || !!caps.storage || (Array.isArray(caps.network) && caps.network.length > 0);
  }

  /* ── Enable / disable (host-first, raw-fetch fallback) ── */
  function applyToggle(id, wantOn, checkEl) {
    var done = function (okMsg) {
      if (U.toast) U.toast(okMsg, 'success');
      loadExtensions(); // re-render everything from the server's view
    };
    var fail = function (err) {
      if (checkEl) checkEl.checked = !wantOn;
      if (U.toast) U.toast('⚠ ' + err.message, 'error');
    };

    if (pkgHost()) {
      var pr = wantOn ? pkgHost().enable(id) : pkgHost().disable(id);
      pr.then(function () { done(wantOn ? '✓ Package enabled' : '✓ Package disabled'); })
        .catch(fail);
      return;
    }
    fetch('index.php?api=workshop-toggle', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({ id: id, enabled: wantOn })
    })
      .then(function (res) { return res.json(); })
      .then(function (j) {
        if (!j.ok) throw new Error(j.error || 'Toggle failed');
        done(wantOn ? '✓ Package enabled' : '✓ Package disabled');
      })
      .catch(fail);
  }

  /* ── Install flow (bottom sheet + SSE) ── */
  function openAddSheet() {
    var input = document.getElementById('wspkg-source-input');
    var msg = document.getElementById('wspkg-add-msg');
    var prog = document.getElementById('wspkg-add-progress');
    var log = document.getElementById('wspkg-add-log');
    var title = document.getElementById('wspkg-add-title');
    if (title) title.textContent = 'ADD PACKAGE';
    if (msg) msg.textContent = '';
    if (prog) prog.classList.add('hidden');
    if (log) log.innerHTML = '';
    if (input) input.value = '';
    var Sheets = window.IDE && window.IDE.mobileSheets;
    if (Sheets) Sheets.openSheet('wspkg-add-sheet', 'wspkg-backdrop');
    if (input) setTimeout(function () { input.focus(); }, 250);
  }

  function addSheetMessage(text, isError) {
    var msg = document.getElementById('wspkg-add-msg');
    if (!msg) return;
    msg.textContent = text;
    msg.className = 'm-sheet-msg' + (isError ? ' error' : '');
  }

  function appendSheetLog(text, cls) {
    var prog = document.getElementById('wspkg-add-progress');
    var log = document.getElementById('wspkg-add-log');
    if (!log) return;
    if (prog) prog.classList.remove('hidden');
    var line = document.createElement('div');
    line.className = 'm-workshop-log-line' + (cls ? ' ' + cls : '');
    line.textContent = text;
    log.appendChild(line);
    log.scrollTop = log.scrollHeight;
  }

  function startInstall(source) {
    var btn = document.getElementById('wspkg-install-btn');
    if (btn) { btn.disabled = true; btn.textContent = '⏳ Installing…'; }

    fetch((window.IDE_STREAM_BASE || 'index.php') + '?api=workshop-install', {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json', Accept: 'text/event-stream' },
      body: JSON.stringify({ source: source })
    })
      .then(function (response) {
        if (!response.ok) throw new Error('HTTP ' + response.status);
        return readSSEStream(response, {
          onStatus: function (d) { appendSheetLog(d.message || ''); },
          onProgress: function (d) { if (d.text) appendSheetLog(d.text, 'log-dim'); },
          onOut: function (d) { if (d.text) appendSheetLog(d.text); },
          onErr: function (d) { if (d.text) appendSheetLog(d.text, 'log-error'); },
          onError: function (d) { appendSheetLog('✗ ' + (d.message || 'Error'), 'log-error'); },
          onDone: function (d) {
            if (btn) { btn.disabled = false; btn.textContent = '⬇ Install'; }
            if (d.success) {
              appendSheetLog('✓ Installed. Review permissions…', 'log-success');
              setTimeout(function () {
                var Sheets = window.IDE && window.IDE.mobileSheets;
                if (Sheets) Sheets.closeSheet('wspkg-add-sheet', 'wspkg-backdrop');
                loadCommunityCatalog();
                offerConsentFor(d.id);
              }, 700);
            } else {
              addSheetMessage('Install failed — see log.', true);
            }
          }
        });
      })
      .catch(function (err) {
        if (btn) { btn.disabled = false; btn.textContent = '⬇ Install'; }
        addSheetMessage(err.message, true);
      });
  }

  /** After a successful install, surface the trust sheet when warranted. */
  function offerConsentFor(pkgId) {
    fetch('index.php?api=workshop-catalog', { headers: { Accept: 'application/json' } })
      .then(function (res) { return res.json(); })
      .then(function (j) {
        if (!j.ok) return;
        var found = null;
        (j.data.packages || []).forEach(function (p) { if (p.id === pkgId) found = p; });
        if (found) {
          cacheRows([found]);
          if (needsConsent(found)) {
            stashConsentTarget(found);
            openConsentSheet(found);
          } else {
            applyToggle(found.id, true, null); // harmless styles-only pack
          }
        }
      })
      .catch(function () { /* user can enable manually from the list */ });
  }

  /* ── Update flow (inline SSE progress in the card) ── */
  function updatePackageFlow(id, btn) {
    btn.disabled = true;
    btn.textContent = '⏳…';
    var prog = document.getElementById('wspkg-update-progress-' + id);
    var log = document.getElementById('wspkg-update-log-' + id);
    if (prog) prog.classList.remove('hidden');
    if (log) log.innerHTML = '';

    function appendLine(text, cls) {
      if (!log) return;
      var line = document.createElement('div');
      line.className = 'm-workshop-log-line' + (cls ? ' ' + cls : '');
      line.textContent = text;
      log.appendChild(line);
      log.scrollTop = log.scrollHeight;
    }

    fetch((window.IDE_STREAM_BASE || 'index.php') + '?api=workshop-update', {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json', Accept: 'text/event-stream' },
      body: JSON.stringify({ id: id })
    })
      .then(function (response) {
        if (!response.ok) throw new Error('HTTP ' + response.status);
        return readSSEStream(response, {
          onStatus: function (d) { appendLine(d.message || ''); },
          onProgress: function (d) { if (d.text) appendLine(d.text, 'log-dim'); },
          onError: function (d) { appendLine('✗ ' + (d.message || 'Error'), 'log-error'); },
          onDone: function (d) {
            if (d.success) {
              appendLine('✓ Updated to v' + (d.version || '?'), 'log-success');
              if (U.toast) U.toast('🔄 Package updated to v' + (d.version || '?'), 'success');
              if (pkgHost()) pkgHost().refresh();
              // New version ⇒ old code consent no longer matches; re-offer.
              setTimeout(function () { offerConsentFor(id); }, 400);
            } else {
              appendLine('✗ Update failed.', 'log-error');
            }
            btn.disabled = false;
            btn.textContent = '🔄 Update';
          }
        });
      })
      .catch(function (err) {
        appendLine('✗ ' + err.message, 'log-error');
        btn.disabled = false;
        btn.textContent = '🔄 Update';
      });
  }

  /* ── Remove flow (two-tap confirm, no modal needed) ── */
  function removePackageFlow(id, btn) {
    if (btn.getAttribute('data-confirming') !== '1') {
      btn.setAttribute('data-confirming', '1');
      btn.setAttribute('data-original', btn.textContent);
      btn.textContent = 'Tap again to remove';
      setTimeout(function () {
        if (btn.getAttribute('data-confirming') === '1') {
          btn.removeAttribute('data-confirming');
          btn.textContent = btn.getAttribute('data-original') || '🗑 Remove';
        }
      }, 3000);
      return;
    }

    btn.disabled = true;
    btn.textContent = '⏳…';
    var finish = function () {
      fetch('index.php?api=workshop-uninstall', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({ id: id })
      })
        .then(function (res) { return res.json(); })
        .then(function (j) {
          if (!j.ok) throw new Error(j.error || 'Remove failed');
          if (U.toast) U.toast('🗑 Package removed', 'success');
          loadCommunityCatalog();
        })
        .catch(function (err) {
          if (U.toast) U.toast('⚠ ' + err.message, 'error');
          btn.disabled = false;
          btn.textContent = '🗑 Remove';
        });
    };

    // Make sure any applied nodes/code are torn down before files vanish.
    if (pkgHost()) {
      pkgHost().disable(id).then(finish, finish);
    } else {
      finish();
    }
  }

  /* ── Consent / trust sheet ── */
  var consentTargetId = null;

  function stashConsentTarget(pkg) {
    consentTargetId = pkg.id;
    rowCache[pkg.id] = pkg;
  }

  function openConsentSheet(pkg) {
    var head = document.getElementById('wspkg-consent-head');
    var list = document.getElementById('wspkg-consent-list');
    var note = document.getElementById('wspkg-consent-note');

    var caps = pkg.capabilities || {};
    var rows = [];
    if (caps.cssInject) rows.push(['🎨', 'Injects CSS into the IDE interface']);
    if (caps.jsRun) rows.push(['⚙️', 'Runs JavaScript code inside the IDE']);
    if (caps.storage) rows.push(['💾', 'Stores its own data in the IDE']);
    if (Array.isArray(caps.network) && caps.network.length) {
      rows.push(['🌐', 'Declares network access to: ' + caps.network.join(', ')]);
    }
    if (pkg.toolchain) rows.push(['📦', 'Suggests installing the Termux package "' + pkg.toolchain + '"']);
    if (!rows.length) rows.push(['🎨', 'Styles only — no special permissions']);

    if (head) {
      head.innerHTML = renderPkgIcon(pkg) + ' <strong>' + U.escapeHtml(pkg.name) + '</strong> v' +
        U.escapeHtml(pkg.version) +
        (pkg.author && pkg.author.name ? ' <span class="m-wspkg-consent-by">by ' + U.escapeHtml(pkg.author.name) + '</span>' : '');
    }
    if (list) {
      list.innerHTML = rows.map(function (r) {
        return '<div class="m-wspkg-consent-row"><span class="m-wspkg-consent-ico">' + r[0] + '</span><span>' + U.escapeHtml(r[1]) + '</span></div>';
      }).join('');
    }
    if (note) {
      // The honest copy the spec asks for — shown exactly when it matters.
      note.textContent = caps.jsRun
        ? 'This package runs with full IDE privileges — install only authors you trust.'
        : 'You can change this later from the package list.';
      note.style.display = caps.jsRun ? 'block' : 'none';
    }

    var Sheets = window.IDE && window.IDE.mobileSheets;
    if (Sheets) Sheets.openSheet('wspkg-consent-sheet', 'wspkg-backdrop');
  }

  function closeConsentSheet() {
    var Sheets = window.IDE && window.IDE.mobileSheets;
    if (Sheets) Sheets.closeSheet('wspkg-consent-sheet', 'wspkg-backdrop');
    consentTargetId = null;
  }

  /* ═══════════════════════════════════════════════════════════
  WIRING + BOOT
  ═══════════════════════════════════════════════════════════ */
  function init() {
    var closeBtn = document.getElementById('m-workshop-close');
    if (closeBtn) closeBtn.addEventListener('click', closeWorkshop);

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') {
        if (consentTargetId !== null) closeConsentSheet();
        else if (diagOverlayOpen) closeDiag();
        else if (logsOverlayOpen) closeLogs();
        else if (isOpen) closeWorkshop();
      }
    });

    /* ── Community sheets wiring ── */
    var addBtn = document.getElementById('wspkg-install-btn');
    if (addBtn) {
      addBtn.addEventListener('click', function () {
        var input = document.getElementById('wspkg-source-input');
        var source = input ? input.value.trim() : '';
        if (source === '') {
          addSheetMessage('Paste a GitHub repo, .zip link, or quirky.workshop.json link first.', true);
          return;
        }
        startInstall(source);
      });
    }

    var cancelAdd = document.getElementById('wspkg-cancel-btn');
    if (cancelAdd) {
      cancelAdd.addEventListener('click', function () {
        var Sheets = window.IDE && window.IDE.mobileSheets;
        if (Sheets) Sheets.closeSheet('wspkg-add-sheet', 'wspkg-backdrop');
      });
    }

    // Enter in the source field == Install.
    var sourceInput = document.getElementById('wspkg-source-input');
    if (sourceInput) {
      sourceInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && addBtn && !addBtn.disabled) addBtn.click();
      });
    }

    var trustBtn = document.getElementById('wspkg-trust-btn');
    if (trustBtn) {
      trustBtn.addEventListener('click', function () {
        var pkg = rowCache[consentTargetId] || null;
        if (!pkg) { closeConsentSheet(); return; }
        if (pkgHost() && pkgHost().consent && pkgHost().consent.grant) {
          pkgHost().consent.grant(pkg, true);
        }
        applyToggle(pkg.id, true, null);
        closeConsentSheet();
      });
    }

    var denyBtn = document.getElementById('wspkg-deny-btn');
    if (denyBtn) denyBtn.addEventListener('click', closeConsentSheet);

    /* ★ Header revamp: drag-to-dismiss removed from ALL Workshop overlays.
      They now close only via the ✕ button or Escape/BACK — exactly like
      Settings, AI, Git and HTTP. (The other panels already had this removed.) */
    // Wire log viewer buttons
    var logsCloseBtn = document.getElementById('m-workshop-logs-close');
    if (logsCloseBtn) logsCloseBtn.addEventListener('click', closeLogs);

    var logsCopyBtn = document.getElementById('m-workshop-logs-copy');
    if (logsCopyBtn) logsCopyBtn.addEventListener('click', copyLogs);

    var logsClearBtn = document.getElementById('m-workshop-logs-clear');
    if (logsClearBtn) logsClearBtn.addEventListener('click', clearLogs);

    var logsRefreshBtn = document.getElementById('m-workshop-logs-refresh');
    if (logsRefreshBtn) logsRefreshBtn.addEventListener('click', fetchLogs);

    // Diagnostics viewer buttons
    var diagCloseBtn = document.getElementById('m-workshop-diag-close');
    if (diagCloseBtn) diagCloseBtn.addEventListener('click', closeDiag);

    var diagRefreshBtn = document.getElementById('m-workshop-diag-refresh');
    if (diagRefreshBtn) diagRefreshBtn.addEventListener('click', fetchDiag);

    var diagCopyBtn = document.getElementById('m-workshop-diag-copy');
    if (diagCopyBtn) diagCopyBtn.addEventListener('click', copyDiag);

    var diagClearBtn = document.getElementById('m-workshop-diag-clear');
    if (diagClearBtn) diagClearBtn.addEventListener('click', clearDiag);

    // Bridge for the "More" sheet
    window._mobileOpenWorkshop = openWorkshop;
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  /* ═══════════════════════════════════════════════════════════
  EXPOSE
  ═══════════════════════════════════════════════════════════ */
  window.IDE = window.IDE || {};
  window.IDE.mobileWorkshop = {
    open: openWorkshop,
    close: closeWorkshop,
    isOpen: function () { return isOpen; },
    refresh: loadExtensions,
    openLogs: openLogs,
    closeLogs: closeLogs,
    openDiag: openDiag,
    closeDiag: closeDiag
  };
})();