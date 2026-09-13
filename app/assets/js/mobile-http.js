/**
 * ═══════════════════════════════════════════════════════════════════════════
 *  QUIRKY IDE — MOBILE HTTP CLIENT (v10 redesign)
 * ═══════════════════════════════════════════════════════════════════════════
 *
 *  Postman-style request builder, workshop-style full-screen page
 *  (NO drag-to-close, NO autofocus-on-open — the keyboard must not pop
 *  until the user taps a field):
 *
 *    • Header: ✕ | 📡 HTTP CLIENT | history 🕘
 *    • STICKY BUILDER BAR under the header: method select + URL + Send
 *      (label swaps to ⏹ Stop while in-flight via AbortController; the
 *      meta row underneath counts down the 30 s timeout)
 *    • Collapsible sections: Headers (KV rows) · Body (JSON | Form | Raw,
 *      JSON gets ✅ Validate + ✨ Beautify via vendored js-beautify,
 *      Form renders urlencoded KV rows) · Auth (Bearer or Basic,
 *      Authorization header composed at SEND time — never stored there)
 *    • Auto-set Content-Type on body-mode switch unless the user typed
 *      their own Content-Type header
 *    • Response renders BELOW the builder and auto-scrolls into view:
 *      status pill (2xx green / 3xx amber / 4xx-5xx red), time + size +
 *      redirect chips, collapsible response headers, body tabs
 *      Pretty / Raw / Copy, image/* rendered from a Blob URL, other
 *      binary bodies as a size+mime placeholder card
 *    • History: rows tinted by status class, live filter input,
 *      tap = duplicate into builder, swipe-left OR 🗑 deletes one row.
 *      Persistence is quota-safe: only a ≤4 KB body preview is stored;
 *      on QuotaExceededError the oldest half is dropped once and the
 *      write retried; if that also fails the user gets a toast.
 *
 *  Sends through the PHP proxy (index.php?api=http-proxy) — no CORS pain.
 *  Markup lives in app/views/mobile/_overlays.php (HTTP CLIENT OVERLAY).
 *  The "More" sheet opens it through the window._mobileOpenHttp bridge.
 *
 *  EXPOSES: window.IDE.mobileHttpClient (+ window._mobileOpenHttp)
 * ═══════════════════════════════════════════════════════════════════════════
 */
(function () {
  'use strict';
  var U = window.IDE && window.IDE.utils;
  if (!U) return;
  var cfg = window.IDE_CONFIG || {};

  /* ═══════════════════════════════════════════════════════════════
  DOM REFERENCES (ids match _overlays.php — HTTP CLIENT OVERLAY)
  ═══════════════════════════════════════════════════════════════ */
  var overlay = document.getElementById('m-http-overlay');
  if (!overlay) return;   // markup not present — nothing to do

  var closeBtn = document.getElementById('m-http-close');
  var historyBtn = document.getElementById('m-http-history-btn');
  var metaRow = document.getElementById('m-http-meta-row');
  var bodyEl = document.getElementById('m-http-body');
  var historyEl = document.getElementById('m-http-history');

  /* builder bar */
  var methodSel = document.getElementById('m-http-method');
  var urlInput = document.getElementById('m-http-url');
  var sendBtn = document.getElementById('m-http-send');

  /* headers section */
  var toggleHeadersBtn = document.getElementById('m-http-toggle-headers');
  var headersSection = document.getElementById('m-http-headers-section');
  var headersList = document.getElementById('m-http-headers-list');
  var addHeaderBtn = document.getElementById('m-http-add-header');
  var headerCountEl = document.getElementById('m-http-header-count');

  /* body section (modes) */
  var toggleBodyBtn = document.getElementById('m-http-toggle-body');
  var bodySection = document.getElementById('m-http-body-section');
  var bodyModesEl = document.getElementById('m-http-body-modes');
  var paneJson = document.getElementById('m-http-pane-json');
  var paneForm = document.getElementById('m-http-pane-form');
  var paneRaw = document.getElementById('m-http-pane-raw');
  var jsonText = document.getElementById('m-http-json-text');
  var jsonValidateBtn = document.getElementById('m-http-json-validate');
  var jsonBeautifyBtn = document.getElementById('m-http-json-beautify');
  var formList = document.getElementById('m-http-form-list');
  var addFieldBtn = document.getElementById('m-http-add-field');
  var rawText = document.getElementById('m-http-raw-text');

  /* auth section */
  var toggleAuthBtn = document.getElementById('m-http-toggle-auth');
  var authSection = document.getElementById('m-http-auth-section');
  var authHint = document.getElementById('m-http-auth-hint');
  var authTypeSel = document.getElementById('m-http-auth-type');
  var bearerRow = document.getElementById('m-http-bearer-row');
  var basicRow = document.getElementById('m-http-basic-row');
  var bearerInput = document.getElementById('m-http-bearer');
  var basicUser = document.getElementById('m-http-basic-user');
  var basicPass = document.getElementById('m-http-basic-pass');

  /* response */
  var responseEl = document.getElementById('m-http-response');
  var respStatusPill = document.getElementById('m-http-resp-status');
  var respTimeChip = document.getElementById('m-http-resp-time');
  var respSizeChip = document.getElementById('m-http-resp-size');
  var respRedirChip = document.getElementById('m-http-resp-redir');
  var toggleRespHeadersBtn = document.getElementById('m-http-toggle-resp-headers');
  var respHeaders = document.getElementById('m-http-resp-headers');
  var respTabsEl = document.getElementById('m-http-resp-tabs');
  var respBody = document.getElementById('m-http-resp-body');

  /* history */
  var historyList = document.getElementById('m-http-history-list');
  var historyFilter = document.getElementById('m-http-history-filter');
  var historyClearBtn = document.getElementById('m-http-history-clear');

  /* ═══════════════════════════════════════════════════════════════
  STATE
  ═══════════════════════════════════════════════════════════════ */
  var LS_HISTORY = 'quirky.ide.mobile.http.history';
  var MAX_HISTORY = 50;
  var BODY_PREVIEW_LIMIT = 4 * 1024;   // ≤4 KB preview persisted per entry
  var REQ_TIMEOUT = 30;                // seconds (proxy clamps 1–120)

  var history = loadHistory();
  var busy = false;
  var wired = false;
  var overlayOpen = false;
  var showingHistory = false;

  var bodyMode = 'json';               // 'json' | 'form' | 'raw'
  var currentVT = 'pretty';            // response viewer tab: 'pretty' | 'raw'
  var respData = null;                 // last response payload (for Copy)
  var respBlobUrl = null;              // object URL of a rendered image

  /* in-flight bookkeeping */
  var aborter = null;                  // AbortController for the active send
  var countdownTimer = null;
  var secsLeft = 0;
  var timedOut = false;

  /* ═══════════════════════════════════════════════════════════════
  HELPERS
  ═══════════════════════════════════════════════════════════════ */
  function toast(msg) {
    if (U && U.toast) U.toast(msg);
  }
  function esc(s) {
    if (U && U.escapeHtml) return U.escapeHtml(s);
    if (typeof s !== 'string') return '';
    return s.replace(/&/g, '&amp;').replace(/</g, '&lt;')
      .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }
  function formatSize(bytes) {
    bytes = Number(bytes);
    if (isNaN(bytes) || bytes <= 0) return '0 B';
    var units = ['B', 'KB', 'MB', 'GB'];
    var i = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
    return (bytes / Math.pow(1024, i)).toFixed(1) + ' ' + units[i];
  }
  /** Envelope-unwrapping fetch helper (same shape as mobile-git.js). */
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
  function statusClass(status) {
    if (status >= 200 && status < 300) return 'ok';
    if (status >= 300 && status < 400) return 'warn';
    return 'err';                       // 4xx, 5xx and status 0
  }
  function httpEnabled() {
    return !!(cfg.features && cfg.features.httpClient);
  }

  /* ── vendored js-beautify wrappers (global builds from index.php bundle) */
  function beautifyJs(src) {
    try {
      if (typeof window.js_beautify === 'function') {
        return window.js_beautify(src, { indent_size: 2, end_with_newline: true });
      }
      if (window.beautifier && typeof window.beautifier.js === 'function') {
        return window.beautifier.js(src, { indent_size: 2 });
      }
    } catch (e) { }
    return null;
  }
  function beautifyHtml(src) {
    try {
      if (typeof window.html_beautify === 'function') {
        return window.html_beautify(src, { indent_size: 2 });
      }
    } catch (e) { }
    return null;
  }
  /** Pretty-print JSON: js-beautify first, JSON.stringify fallback. */
  function prettifyJson(txt) {
    var obj = null;
    try { obj = JSON.parse(txt); } catch (e) { return null; }
    var b = beautifyJs(txt);
    if (b != null) return b;
    try { return JSON.stringify(obj, null, 2); } catch (e) { return txt; }
  }

  /* ═══════════════════════════════════════════════════════════════
  HISTORY STORAGE (quota-safe)
  ═══════════════════════════════════════════════════════════════ */
  function loadHistory() {
    try {
      var raw = localStorage.getItem(LS_HISTORY);
      var arr = raw ? JSON.parse(raw) : [];
      return Array.isArray(arr) ? arr : [];
    } catch (e) {
      return [];
    }
  }
  /**
   * Persist with a retry-once-after-dropping-the-oldest-half strategy.
   * Returns true when the write landed somewhere.
   */
  function saveHistory() {
    try {
      localStorage.setItem(LS_HISTORY, JSON.stringify(history.slice(-MAX_HISTORY)));
      return true;
    } catch (e) { /* fall through to the retry */ }
    history = history.slice(Math.ceil(history.length / 2));
    try {
      localStorage.setItem(LS_HISTORY, JSON.stringify(history));
      toast('⚠ Oldest history trimmed to fit storage');
      return true;
    } catch (e2) {
      toast('History not saved (storage full)');
      return false;
    }
  }
  function truncatePreview(s) {
    s = String(s == null ? '' : s);
    return s.length > BODY_PREVIEW_LIMIT ? s.slice(0, BODY_PREVIEW_LIMIT) : s;
  }

  /* ═══════════════════════════════════════════════════════════════
  OPEN / CLOSE / VIEWS
  ═══════════════════════════════════════════════════════════════ */
  function openOverlay() {
    if (!overlay || overlayOpen) return;
    overlayOpen = true;
    setView(showingHistory ? 'history' : 'builder');   // no autofocus — ever
    overlay.classList.remove('hidden');
    void overlay.offsetWidth;   // force reflow so the slide-up transition runs
    overlay.classList.add('show');
    if (!showingHistory) renderHistory();   // keep badge/list fresh
  }
  function closeOverlay() {
    if (!overlay || !overlayOpen) return;
    overlayOpen = false;
    overlay.classList.remove('show');
    setTimeout(function () {
      overlay.classList.add('hidden');
    }, 300);
  }
  function setView(view) {
    showingHistory = (view === 'history');
    if (bodyEl) bodyEl.classList.toggle('hidden', showingHistory);
    if (historyEl) historyEl.classList.toggle('hidden', !showingHistory);
    if (historyBtn) historyBtn.classList.toggle('active', showingHistory);
    if (showingHistory) renderHistory();
  }

  /* ═══════════════════════════════════════════════════════════════
  SECTION TOGGLES (Headers / Body / Auth)
  ═══════════════════════════════════════════════════════════════ */
  function wireSectionToggle(btn, section) {
    if (!btn || !section) return;
    btn.addEventListener('click', function () {
      var opening = section.classList.contains('hidden');
      section.classList.toggle('hidden', !opening);
      btn.classList.toggle('open', opening);
    });
  }
  function setSectionCount(el, n) {
    if (!el) return;
    el.textContent = String(n);
    el.classList.toggle('zero', !n);
  }

  /* ═══════════════════════════════════════════════════════════════
  KV ROW EDITORS (request headers + form fields share one builder)
  ═══════════════════════════════════════════════════════════════ */
  function addKvRow(listEl, opts) {
    opts = opts || {};
    if (!listEl) return null;
    var row = document.createElement('div');
    row.className = 'm-http-header-row';
    if (opts.auto) row.setAttribute('data-auto', '1');
    row.innerHTML =
      '<input type="text" class="m-http-header-key" placeholder="' + esc(opts.keyPh || 'Name') +
      '" value="' + esc(opts.key || '') + '" spellcheck="false" autocapitalize="off">' +
      '<input type="text" class="m-http-header-val" placeholder="' + esc(opts.valPh || 'Value') +
      '" value="' + esc(opts.val || '') + '" spellcheck="false" autocapitalize="off">' +
      '<button class="m-http-header-del" title="Remove" aria-label="Remove row">✕</button>';
    row.querySelector('.m-http-header-del').addEventListener('click', function () {
      row.remove();
      syncKvCounts();
    });
    listEl.appendChild(row);
    return row;
  }
  function kvRows(listEl) {
    var out = [];
    if (!listEl) return out;
    listEl.querySelectorAll('.m-http-header-row').forEach(function (row) {
      var key = row.querySelector('.m-http-header-key');
      var val = row.querySelector('.m-http-header-val');
      if (key && val && key.value.trim() !== '') {
        out.push({ key: key.value.trim(), value: val.value.trim() });
      }
    });
    return out;
  }
  function rebuildKvRows(listEl, pairs, ph) {
    if (!listEl) return;
    listEl.innerHTML = '';
    (pairs || []).forEach(function (h) {
      addKvRow(listEl, { key: h.key, val: h.value, keyPh: ph && ph.keyPh, valPh: ph && ph.valPh });
    });
  }
  function syncKvCounts() {
    setSectionCount(headerCountEl, kvRows(headersList).length);
  }
  function hasHeader(pairs, name) {
    name = String(name).toLowerCase();
    for (var i = 0; i < pairs.length; i++) {
      if (String(pairs[i].key).toLowerCase() === name) return true;
    }
    return false;
  }
  function getHeaders() { return kvRows(headersList); }

  /**
   * Auto Content-Type management. A row tagged data-auto="1" is ours and
   * may be updated on later mode switches; a row the USER created is
   * never touched again.
   */
  function applyAutoContentType(ctValue) {
    if (!ctValue || !headersList) return;
    var rows = Array.prototype.slice.call(
      headersList.querySelectorAll('.m-http-header-row'));
    var userRow = null, autoRow = null;
    rows.forEach(function (r) {
      var k = r.querySelector('.m-http-header-key');
      if (!k || k.value.trim() === '') return;
      if (k.value.trim().toLowerCase() !== 'content-type') return;
      if (r.getAttribute('data-auto') === '1' && !autoRow) autoRow = r;
      else userRow = r;
    });
    if (userRow) return;   // user typed one — hands off
    if (autoRow) {
      autoRow.querySelector('.m-http-header-val').value = ctValue;
      return;
    }
    addKvRow(headersList, { key: 'Content-Type', val: ctValue, auto: true });
    syncKvCounts();
  }

  /* ═══════════════════════════════════════════════════════════════
  BODY MODES (JSON | Form | Raw)
  ═══════════════════════════════════════════════════════════════ */
  function setBodyMode(mode, opts) {
    if (['json', 'form', 'raw'].indexOf(mode) === -1) mode = 'json';
    bodyMode = mode;
    if (bodyModesEl) {
      bodyModesEl.querySelectorAll('button[data-bmode]').forEach(function (b) {
        b.classList.toggle('active', b.getAttribute('data-bmode') === mode);
      });
    }
    if (paneJson) paneJson.classList.toggle('hidden', mode !== 'json');
    if (paneForm) paneForm.classList.toggle('hidden', mode !== 'form');
    if (paneRaw) paneRaw.classList.toggle('hidden', mode !== 'raw');
    /* Auto-set Content-Type unless the user typed their own header */
    if (!opts || !opts.skipContentType) {
      if (mode === 'json') applyAutoContentType('application/json');
      else if (mode === 'form') applyAutoContentType('application/x-www-form-urlencoded');
      /* raw: leave whatever the user manages */
    }
  }
  function serializeForm() {
    var parts = [];
    kvRows(formList).forEach(function (f) {
      parts.push(encodeURIComponent(f.key) + '=' + encodeURIComponent(f.value));
    });
    return parts.join('&');
  }
  function buildRequestBody() {
    if (bodyMode === 'json') return jsonText ? jsonText.value : '';
    if (bodyMode === 'form') return serializeForm();
    return rawText ? rawText.value : '';
  }

  /* ═══════════════════════════════════════════════════════════════
  AUTH (Authorization header composed at SEND time only)
  ═══════════════════════════════════════════════════════════════ */
  function setAuthType(t) {
    if (['none', 'bearer', 'basic'].indexOf(t) === -1) t = 'none';
    if (authTypeSel) authTypeSel.value = t;
    if (bearerRow) bearerRow.classList.toggle('hidden', t !== 'bearer');
    if (basicRow) basicRow.classList.toggle('hidden', t !== 'basic');
    if (authHint) {
      authHint.textContent = t === 'bearer' ? 'Bearer' : (t === 'basic' ? 'Basic' : 'None');
    }
  }
  function b64utf8(str) {
    try {
      return btoa(unescape(encodeURIComponent(str)));
    } catch (e) {
      try { return btoa(str); } catch (e2) { return ''; }
    }
  }
  /** Headers array + auth-derived Authorization (unless user supplied one). */
  function buildRequestHeaders() {
    var headers = getHeaders();
    var t = authTypeSel ? authTypeSel.value : 'none';
    if (t !== 'none' && !hasHeader(headers, 'authorization')) {
      if (t === 'bearer' && bearerInput && bearerInput.value.trim() !== '') {
        headers.push({ key: 'Authorization', value: 'Bearer ' + bearerInput.value.trim() });
      } else if (t === 'basic') {
        var u = basicUser ? basicUser.value : '';
        var p = basicPass ? basicPass.value : '';
        if (u !== '' || p !== '') {
          var b64 = b64utf8(u + ':' + p);
          if (b64) headers.push({ key: 'Authorization', value: 'Basic ' + b64 });
        }
      }
    }
    return headers;
  }

  /* ═══════════════════════════════════════════════════════════════
  SEND REQUEST (through the PHP proxy — same endpoint as desktop)
  Send doubles as ⏹ Stop while in-flight (AbortController).
  ═══════════════════════════════════════════════════════════════ */
  function setMeta(text) {
    if (!metaRow) return;
    metaRow.textContent = text || '';
    metaRow.classList.toggle('on', !!text);
  }
  function startCountdown() {
    secsLeft = REQ_TIMEOUT;
    timedOut = false;
    stopCountdown();
    setMeta('⏳ Sending ' + (methodSel ? methodSel.value : '') +
      ' — timeout in ' + secsLeft + ' s · tap ⏹ Stop to cancel');
    countdownTimer = setInterval(function () {
      secsLeft--;
      if (secsLeft <= 0) {
        timedOut = true;
        setMeta('⏱ Timed out after ' + REQ_TIMEOUT + ' s — cancelling…');
        if (aborter) aborter.abort();
      } else {
        setMeta('⏳ Sending ' + (methodSel ? methodSel.value : '') +
          ' — timeout in ' + secsLeft + ' s · tap ⏹ Stop to cancel');
      }
    }, 1000);
  }
  function stopCountdown() {
    if (countdownTimer) { clearInterval(countdownTimer); countdownTimer = null; }
  }
  function setSendLabel(inFlight) {
    if (!sendBtn) return;
    sendBtn.textContent = inFlight ? '⏹ Stop' : 'Send';
    sendBtn.classList.toggle('inflight', !!inFlight);
  }

  function sendRequest() {
    /* Second tap while in-flight = stop */
    if (busy) {
      timedOut = false;
      setMeta('🛑 Cancelling…');
      if (aborter) aborter.abort();
      return;
    }
    var url = urlInput ? urlInput.value.trim() : '';
    var method = methodSel ? methodSel.value : 'GET';
    if (!url) {
      toast('⚠ Enter a URL first');
      return;
    }
    if (!/^https?:\/\//i.test(url)) {
      toast('⚠ URL must start with http:// or https://');
      return;
    }
    var headers = buildRequestHeaders();
    var body = buildRequestBody();

    busy = true;
    setSendLabel(true);
    startCountdown();

    var opts = {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify({
        method: method, url: url, headers: headers,
        body: body, timeout: REQ_TIMEOUT
      })
    };
    if (typeof AbortController !== 'undefined') {
      aborter = new AbortController();
      opts.signal = aborter.signal;
    }

    fetch('index.php?api=http-proxy', opts)
      .then(function (r) {
        return r.json().then(function (j) {
          if (!r.ok || (j && j.ok === false)) throw new Error((j && j.error) || 'Request failed');
          return j.data;
        });
      })
      .then(function (data) {
        setMeta('');
        renderResponse(data, { method: method, url: url });
        addToHistory({
          method: method,
          url: url,
          headers: headers,
          bodyMode: bodyMode,
          body: body,
          formFields: bodyMode === 'form' ? kvRows(formList) : null,
          auth: snapshotAuth(),
          status: data.status || 0,
          statusText: data.statusText || '',
          time: data.time,
          size: data.size,
          contentType: data.contentType || '',
          respPreview: truncatePreview(data.body),
          ts: Date.now()
        });
      })
      .catch(function (err) {
        setMeta('');
        var msg = err && err.name === 'AbortError'
          ? (timedOut ? 'Timed out after ' + REQ_TIMEOUT + ' s' : 'Cancelled')
          : (err && err.message) || 'Request failed';
        renderResponse({ success: false, error: msg, time: null }, { method: method, url: url });
      })
      .finally(function () {
        busy = false;
        aborter = null;
        stopCountdown();
        setSendLabel(false);
      });
  }
  function snapshotAuth() {
    var t = authTypeSel ? authTypeSel.value : 'none';
    if (t === 'none') return null;
    /* Never persist the Basic password — replay asks for it again. */
    return {
      type: t,
      bearer: t === 'bearer' && bearerInput ? bearerInput.value : '',
      user: t === 'basic' && basicUser ? basicUser.value : ''
    };
  }
  function restoreAuth(auth) {
    if (!auth || !auth.type) { setAuthType('none'); return; }
    setAuthType(auth.type);
    if (bearerInput) bearerInput.value = auth.bearer || '';
    if (basicUser) basicUser.value = auth.user || '';
    if (basicPass) basicPass.value = '';
  }

  /* ═══════════════════════════════════════════════════════════════
  RESPONSE RENDERING (renders below the builder, auto-scrolls)
  ═══════════════════════════════════════════════════════════════ */
  function clearRespBlob() {
    if (respBlobUrl) {
      try { URL.revokeObjectURL(respBlobUrl); } catch (e) { }
      respBlobUrl = null;
    }
  }
  function isBinaryType(ct) {
    if (!ct) return false;
    if (/^(text\/)/i.test(ct)) return false;
    if (/json|javascript|xml|html|csv|x-www-form-urlencoded/i.test(ct)) return false;
    return /^(image\/|audio\/|video\/|font\/|application\/(octet-stream|pdf|zip|gzip|x-gzip|tar|x-tar|wasm|sqlite|rss|rtf|msword|vnd\.))/i.test(ct);
  }
  function binaryCardHtml(ct, size) {
    return '<div class="m-http-binary-card">' +
      '<span class="m-http-binary-ico">📦</span>' +
      '<b>' + esc(ct.split(';')[0] || 'binary') + '</b>' +
      '<span>' + formatSize(size) + '</span>' +
      '<p>Binary body — not displayed.</p></div>';
  }
  function computeViews(d) {
    /* Returns {kind:'image'|'binary'|'text', pretty:String, raw:String} */
    var raw = d.body == null ? '' : String(d.body);
    var ct = (d.contentType || '').toLowerCase();
    if (/^image\//i.test(ct)) {
      return { kind: 'image', raw: raw, pretty: raw, mime: ct.split(';')[0] };
    }
    if (isBinaryType(ct)) {
      return { kind: 'binary', raw: raw, pretty: raw, mime: ct.split(';')[0] };
    }
    var pretty = null;
    var head = raw.replace(/^\s+/, '').charAt(0);
    if (ct.indexOf('json') !== -1 || head === '{' || head === '[') {
      pretty = prettifyJson(raw);
    }
    if (pretty == null && /xml|html|^text\/plain/.test(ct) && /(^\s*<)|(<\/[a-z]+>)/i.test(raw.slice(0, 400))) {
      pretty = beautifyHtml(raw);
    }
    return { kind: 'text', raw: raw, pretty: (pretty == null ? raw : pretty) };
  }
  function renderRespBodyPane() {
    if (!respBody || !respData) return;
    clearRespBlob();
    if (respData.error) {
      respBody.innerHTML =
        '<div class="m-http-binary-card fail"><span class="m-http-binary-ico">⚠</span>' +
        '<b>Request failed</b><span></span><p>' + esc(respData.error) + '</p></div>';
      return;
    }
    var v = respData.views;
    if (v.kind === 'image') {
      try {
        var bytes = new Uint8Array(v.raw.length);
        for (var i = 0; i < v.raw.length; i++) bytes[i] = v.raw.charCodeAt(i) & 0xFF;
        var blob = new Blob([bytes], { type: v.mime || 'image/png' });
        respBlobUrl = URL.createObjectURL(blob);
        respBody.innerHTML = '<img class="m-http-resp-img" alt="response image">';
        var img = respBody.querySelector('img');
        img.onload = function () { img.classList.add('ready'); };
        img.onerror = function () {
          clearRespBlob();
          respBody.innerHTML = binaryCardHtml(v.mime || 'image/*',
            respData.size != null ? respData.size : v.raw.length) +
            '<p class="m-http-binary-warn">Image could not be decoded from the proxied text body.</p>';
        };
        img.src = respBlobUrl;
      } catch (e) {
        respBody.innerHTML = binaryCardHtml(v.mime || 'image/*', respData.size);
      }
      return;
    }
    if (v.kind === 'binary') {
      respBody.innerHTML = binaryCardHtml(v.mime, respData.size);
      return;
    }
    respBody.textContent = (currentVT === 'raw') ? v.raw : v.pretty;
  }
  function setViewerTab(vt) {
    if (vt !== 'pretty' && vt !== 'raw') vt = 'pretty';
    currentVT = vt;
    if (respTabsEl) {
      respTabsEl.querySelectorAll('button[data-vt]').forEach(function (b) {
        b.classList.toggle('active', b.getAttribute('data-vt') === vt);
      });
    }
    renderRespBodyPane();
  }
  function renderResponse(data, reqInfo) {
    if (!responseEl) return;
    respData = { error: (!data || data.success === false) ? ((data && data.error) || 'Unknown error') : null };
    responseEl.classList.remove('hidden');

    /* ── Failure (proxy error, network error, cancellation) ── */
    if (respData.error) {
      if (respStatusPill) {
        respStatusPill.className = 'm-http-status err';
        respStatusPill.textContent = '✗ ERR';
      }
      if (respTimeChip) respTimeChip.textContent = (data && data.time != null) ? ('⏱ ' + data.time + ' ms') : '';
      if (respSizeChip) respSizeChip.textContent = '';
      if (respRedirChip) respRedirChip.classList.add('hidden');
      if (toggleRespHeadersBtn) toggleRespHeadersBtn.classList.add('hidden');
      if (respHeaders) { respHeaders.innerHTML = ''; respHeaders.classList.add('hidden'); }
      if (respTabsEl) respTabsEl.classList.add('hidden');
      respData.views = null;
      renderRespBodyPane();
      scrollResponseIntoView();
      return;
    }

    /* ── Success ── */
    var status = data.status || 0;
    var cls = statusClass(status);
    if (respStatusPill) {
      respStatusPill.className = 'm-http-status ' + cls;
      respStatusPill.textContent = (status || '—') + ' ' + (data.statusText || '');
    }
    if (respTimeChip) respTimeChip.textContent = data.time != null ? ('⏱ ' + data.time + ' ms') : '';
    if (respSizeChip) respSizeChip.textContent = data.size != null ? ('📦 ' + formatSize(data.size)) : '';
    /* Backend may not return redirectCount yet — feature-detect it */
    if (respRedirChip) {
      if (typeof data.redirectCount === 'number' && data.redirectCount > 0) {
        respRedirChip.textContent = '↪ ' + data.redirectCount + ' redirect' + (data.redirectCount === 1 ? '' : 's');
        respRedirChip.classList.remove('hidden');
      } else {
        respRedirChip.textContent = '';
        respRedirChip.classList.add('hidden');
      }
    }
    /* Response headers (collapsed by default) */
    var heads = Array.isArray(data.headers) ? data.headers : [];
    if (respHeaders) {
      respHeaders.innerHTML = heads.map(function (h) {
        return '<div class="m-http-resp-header-row">' +
          '<span class="m-http-resp-hkey">' + esc(h.key) + ':</span>' +
          '<span class="m-http-resp-hval">' + esc(h.value) + '</span></div>';
      }).join('');
      respHeaders.classList.toggle('hidden', !heads.length);
    }
    if (toggleRespHeadersBtn) {
      toggleRespHeadersBtn.classList.toggle('hidden', !heads.length);
      toggleRespHeadersBtn.textContent = 'Headers ▾';
    }
    /* Body views + tabs */
    respData.views = computeViews(data);
    if (respTabsEl) respTabsEl.classList.remove('hidden');
    setViewerTab(currentVT);
    scrollResponseIntoView();
  }
  function scrollResponseIntoView() {
    requestAnimationFrame(function () {
      try {
        responseEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
      } catch (e) {
        try { responseEl.scrollIntoView(); } catch (e2) { }
      }
    });
  }

  /* ═══════════════════════════════════════════════════════════════
  HISTORY (replayable · filterable · swipe-or-🗑 deletable)
  ═══════════════════════════════════════════════════════════════ */
  function addToHistory(item) {
    history.push(item);
    if (history.length > MAX_HISTORY) history.shift();
    saveHistory();
    if (showingHistory) renderHistory();
  }
  function clearAllHistory() {
    U.modal.confirm({
      title: 'Clear HTTP history?',
      message: 'Delete all ' + history.length + ' saved request(s)? This cannot be undone.',
      danger: true
    }).then(function (ok) {
      if (!ok) return;
      history = [];
      saveHistory();
      renderHistory();
      toast('History cleared');
    });
  }
  function deleteHistoryItem(idx) {
    history.splice(idx, 1);
    saveHistory();
    renderHistory();
  }
  function filteredHistory() {
    var q = historyFilter ? historyFilter.value.trim().toLowerCase() : '';
    if (!q) return history.map(function (item, idx) { return { item: item, idx: idx }; });
    return history.map(function (item, idx) { return { item: item, idx: idx }; })
      .filter(function (e) {
        return (String(e.item.method) + ' ' + String(e.item.url)).toLowerCase().indexOf(q) !== -1;
      });
  }
  function renderHistory() {
    if (!historyList) return;
    if (!history.length) {
      historyList.innerHTML =
        '<div class="m-http-empty"><div class="m-http-empty-icon">🕘</div>' +
        '<b>No requests yet</b><br>Send one and it will appear here.</div>';
      return;
    }
    var rows = filteredHistory();
    if (!rows.length) {
      historyList.innerHTML = '<div class="m-http-empty">No requests match “' +
        esc(historyFilter ? historyFilter.value : '') + '”.</div>';
      return;
    }
    historyList.innerHTML = '';
    rows.reverse().forEach(function (e) {
      var item = e.item;
      var cls = item.status ? statusClass(Number(item.status)) : 'na';
      var when = item.ts ? new Date(item.ts).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : '';
      var el = document.createElement('div');
      el.className = 'm-http-hitem ' + cls;
      el.innerHTML =
        '<span class="m-http-hmethod">' + esc(item.method || '') + '</span>' +
        '<span class="m-http-hmain">' +
        '<span class="m-http-hurl">' + esc(item.url || '') + '</span>' +
        '<span class="m-http-hmeta">' +
        (item.status ? esc(String(item.status) + (item.statusText ? ' ' + item.statusText : '')) : '—') +
        (item.time != null ? ' · ' + item.time + ' ms' : '') +
        (when ? ' · ' + when : '') +
        '</span></span>' +
        '<span class="m-http-hstatus ' + cls + '">' + esc(item.status ? String(item.status) : '—') + '</span>' +
        '<button class="m-http-hdel" title="Delete" aria-label="Delete entry">🗑</button>' +
        '<span class="m-http-hswipe" aria-hidden="true"></span>';
      el.querySelector('.m-http-hdel').addEventListener('click', function (ev) {
        ev.stopPropagation();
        deleteHistoryItem(e.idx);
      });
      /* tap = duplicate into the builder */
      el.addEventListener('click', function () { replayRequest(item); });
      /* swipe-left = quick delete (vertical scroll always wins) */
      attachSwipeDelete(el, function () { deleteHistoryItem(e.idx); });
      historyList.appendChild(el);
    });
  }
  function attachSwipeDelete(row, onDelete) {
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
      if (Math.abs(my) > Math.abs(mx)) { tracking = false; row.style.transform = ''; row.classList.remove('swiped'); return; }
      dx = Math.min(0, mx);
      row.style.transform = 'translateX(' + dx + 'px)';
      row.classList.toggle('swiped', dx < -8);
    }, { passive: true });
    row.addEventListener('touchend', function () {
      tracking = false;
      row.style.transform = '';
      if (dx < -TH) onDelete();
    }, { passive: true });
  }
  function replayRequest(item) {
    if (!item) return;
    setView('builder');
    if (methodSel) methodSel.value = item.method || 'GET';
    if (urlInput) urlInput.value = item.url || '';
    /* Body: restore into the recorded mode (guess from payload otherwise) */
    var mode = ['json', 'form', 'raw'].indexOf(item.bodyMode) !== -1 ? item.bodyMode : null;
    if (!mode) {
      var b = String(item.body || '');
      var h = b.replace(/^\s+/, '').charAt(0);
      mode = (h === '{' || h === '[') ? 'json' : 'raw';
    }
    if (jsonText) jsonText.value = mode === 'json' ? String(item.body || '') : '';
    if (rawText) rawText.value = mode === 'raw' ? String(item.body || '') : '';
    rebuildKvRows(formList, mode === 'form' ? (item.formFields || []) : [],
      { keyPh: 'Field', valPh: 'Value' });
    setBodyMode(mode, { skipContentType: true });   // replay ≠ mode switch
    rebuildKvRows(headersList, item.headers);
    restoreAuth(item.auth);
    syncKvCounts();
    if (bodyEl) bodyEl.scrollTop = 0;
    toast('Loaded: ' + (item.method || '') + ' ' + (item.url || ''));
  }

  /* ═══════════════════════════════════════════════════════════════
  WIRING (runs once)
  ═══════════════════════════════════════════════════════════════ */
  function init() {
    if (wired) return;
    wired = true;

    if (closeBtn) closeBtn.addEventListener('click', closeOverlay);
    if (historyBtn) historyBtn.addEventListener('click', function () {
      setView(showingHistory ? 'builder' : 'history');
    });

    /* collapsible sections */
    wireSectionToggle(toggleHeadersBtn, headersSection);
    wireSectionToggle(toggleBodyBtn, bodySection);
    wireSectionToggle(toggleAuthBtn, authSection);

    /* headers editor */
    if (addHeaderBtn) addHeaderBtn.addEventListener('click', function () {
      addKvRow(headersList);
      syncKvCounts();
    });

    /* body modes */
    if (bodyModesEl) {
      bodyModesEl.addEventListener('click', function (e) {
        var b = e.target.closest('button[data-bmode]');
        if (b) setBodyMode(b.getAttribute('data-bmode'));
      });
    }
    if (addFieldBtn) addFieldBtn.addEventListener('click', function () {
      addKvRow(formList, { keyPh: 'Field', valPh: 'Value' });
    });
    if (jsonValidateBtn) jsonValidateBtn.addEventListener('click', function () {
      var src = jsonText ? jsonText.value.trim() : '';
      if (!src) { toast('Nothing to validate'); return; }
      try { JSON.parse(src); toast('✓ Valid JSON'); }
      catch (e) { toast('✗ Invalid JSON: ' + e.message); }
    });
    if (jsonBeautifyBtn) jsonBeautifyBtn.addEventListener('click', function () {
      var src = jsonText ? jsonText.value : '';
      if (!src.trim()) { toast('Nothing to beautify'); return; }
      var out = prettifyJson(src);
      if (out == null) {
        out = beautifyJs(src);
        if (out == null) { toast('✗ Not valid JSON'); return; }
        toast('✨ Formatted (was not strict JSON)');
      } else {
        toast('✨ Beautified');
      }
      if (jsonText) jsonText.value = out;
    });

    /* auth */
    if (authTypeSel) authTypeSel.addEventListener('change', function () {
      setAuthType(this.value);
    });

    /* send / stop */
    if (sendBtn) sendBtn.addEventListener('click', sendRequest);

    /* response viewer */
    if (toggleRespHeadersBtn) toggleRespHeadersBtn.addEventListener('click', function () {
      if (!respHeaders) return;
      var hidden = respHeaders.classList.contains('hidden');
      respHeaders.classList.toggle('hidden', !hidden);
      toggleRespHeadersBtn.textContent = hidden ? 'Headers ▴' : 'Headers ▾';
    });
    if (respTabsEl) {
      respTabsEl.addEventListener('click', function (e) {
        var b = e.target.closest('button[data-vt]');
        if (!b || !respData || respData.error) return;
        var vt = b.getAttribute('data-vt');
        if (vt === 'copy') {
          var text = respData.views ? respData.views.raw : '';
          if (!text) { toast('Nothing to copy'); return; }
          if (navigator.clipboard) {
            navigator.clipboard.writeText(text)
              .then(function () { toast('📋 Response copied'); })
              .catch(function () { toast('Copy failed'); });
          } else {
            toast('Clipboard unavailable');
          }
          b.classList.add('copied');
          setTimeout(function () { b.classList.remove('copied'); }, 900);
          return;
        }
        setViewerTab(vt);
      });
    }

    /* history */
    if (historyFilter) historyFilter.addEventListener('input', renderHistory);
    if (historyClearBtn) historyClearBtn.addEventListener('click', clearAllHistory);

    /* Escape closes the overlay */
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && overlayOpen) closeOverlay();
    });
  }

  /* ═══════════════════════════════════════════════════════════════
  BOOT
  ═══════════════════════════════════════════════════════════════ */
  function boot() {
    window.IDE = window.IDE || {};
    /**
     * @namespace window.IDE.mobileHttpClient
     * Mobile HTTP client overlay (sticky builder · body modes · auth ·
     * quota-safe history). v10 redesign.
     */
    window.IDE.mobileHttpClient = {
      /** Wire the overlay controls (idempotent). */
      init: init,
      /** Open the HTTP client overlay. */
      open: openOverlay,
      /** Close the HTTP client overlay. */
      close: closeOverlay,
      /** True while the overlay is visible. */
      isOpen: function () { return overlayOpen; },
      /** Focus the URL field (only ever on explicit call — never on open). */
      focus: function () { if (urlInput) urlInput.focus(); },
      /** Drop every saved request (used by Settings → Privacy & Data). */
      clearHistory: function () {
        history = [];
        saveHistory();
        if (showingHistory) renderHistory();
      }
    };
    if (!httpEnabled()) {
      /* Feature off → the More sheet gets a friendly explanation */
      window._mobileOpenHttp = function () {
        toast('📡 HTTP Client is currently turned off. You can turn it on in Settings.');
      };
      return;
    }
    init();

    /* Bridge used by the "More" sheet (HTTP Client row) — unchanged name */
    window._mobileOpenHttp = openOverlay;
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
