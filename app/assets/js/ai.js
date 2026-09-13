/**
 * ═══════════════════════════════════════════════════════════════════════════
 *  QUIRKY IDE — AI ASSISTANT PANEL (v10 redesign · multi-provider frontend)
 * ═══════════════════════════════════════════════════════════════════════════
 *
 *  Workshop-style full-screen page (NO drag-to-close), decluttered header:
 *      ✕ |  AI ASSISTANT | [provider chip] | [model]
 *  (the chip opens the manager sheet — the old  button is gone). The composer
 *  carries the agent-permission dropdown in a bar below the full-width input.
 * 
 *  What lives here:
 *    • Provider chip → manager bottom-sheet (.m-sheet, JS-injected):
 *      configured providers (tap = switch, ⋯/long-press = Edit/Test/Delete),
 *      "+ Add provider", agent-permission segmented control, Clear chat.
 *    • Add/Edit form sheet: preset autofill (baseUrl + curated model datalist),
 *      key (password, maskedKey placeholder, empty = keep), model + "Fetch
 *      models", advanced headers JSON, TEST / SAVE / DELETE.
 *    • Transcript restore on open (GET ai-history) — stored bubbles rendered
 *      through the same renderer, welcome screen only when history is empty.
 *    • Composer: auto-grow textarea (max 6 rows), Enter = newline (touch),
 *      combined Send/Stop button (AbortController → "stopped" divider),
 *      quick-prompt chips on the welcome screen.
 *    • Streaming perf: delta buffer flushed at most once per ~100 ms aligned
 *      to rAF; finished paragraphs are appended ONCE as separate nodes and
 *      only the trailing unfinished paragraph is re-rendered. Shared by the
 *      chat / approve / regenerate paths.
 *    • ONE mutable tool-progress row per turn, updated in place, collapsing
 *      to a compact "ran N tools" summary when the turn finishes.
 *    • Per-bubble 📋 copy; per-code-block 📋 copy + "⤵ Editor" insert via
 *      IDE.mobileEditor.getCM().
 *    • Status plumbing: chip dot (offline grey / testing amber / online green
 *      / error red-outline), fresh lastError tooltip, dismissible setup card
 *      when nothing is configured, friendlyError() with "Fix it" deep-link.
 *
 *  Backend contract (live): envelope JSON {ok,data|error} on index.php?api=*;
 *  SSE frames delta|tool|pending|error|done on window.IDE_STREAM_BASE.
 *  Keys NEVER appear in payloads — maskedKey only.
 *
 *  EXPOSES: window.IDE.mobileAi  { open, close, focus, refresh, isOpen }
 * ═══════════════════════════════════════════════════════════════════════════ */
(function () {
  'use strict';

  var U = window.IDE && window.IDE.utils;

  function $(id) { return document.getElementById(id); }

  /* ═══════════════════════════════════════════════════════════════
  DOM REFERENCES (ids shared with views/mobile/_overlays.php — the
  root id/class pair #m-ai-overlay / .m-ai-overlay is hardcoded in
  mobile-keyboard.js and MUST NOT be renamed.)
  ═══════════════════════════════════════════════════════════════ */
  var overlay = $('m-ai-overlay');
  var chatEl = $('ai-chat');
  var inputEl = $('ai-input');
  var sendBtn = $('ai-send');
  var chipBtn = $('ai-provider-chip');
  var chipIco = $('ai-chip-ico');
  var chipLabel = $('ai-chip-label');
  var chipDot = $('ai-chip-dot');
  var modelSel = $('ai-model-select');
  var permSel = $('ai-permission-mode');

  if (!overlay || !chatEl || !inputEl) return;   // markup not present

  /* ═══════════════════════════════════════════════════════════════
  STATE
  ═══════════════════════════════════════════════════════════════ */
  var state = {
    providers: [],        // masked summaries from ai-providers-list / ai-status
    presets: [],
    activeId: null,
    activeWire: null,
    perm: 'ask_destructive',
    busy: false,
    stopped: false,
    ctrl: null,           // AbortController of the in-flight stream
    testing: false,       // chip amber while a test/switch/model-change runs
    restored: false,      // transcript fetched this page-load
    setupDismissed: false
  };
  var isOpen = false;
  var wired = false;
  var turn = null;        // active streaming turn context

  try { state.setupDismissed = localStorage.getItem('quirky.ide.ai.setupDismissed') === '1'; } catch (e) { }

  var LS_SETUP_DISMISS = 'quirky.ide.ai.setupDismissed';
  var QUICK_PROMPTS = ['List my files', 'Explain this file', 'Write tests'];
  var PERM_MODES = [
    { mode: 'always_ask', label: '🔒 Ask always' },
    { mode: 'ask_destructive', label: '🛡 Writes ask' },
    { mode: 'auto_approve', label: '⚡ Auto' }
  ];
  var WIRE_EMOJI = { openai: '🔵', anthropic: '🟣', gemini: '🔶', ollama: '🦙' };
  var INPUT_MAX_H = 148;  // ≈ 6 rows @16px/1.4 + padding

  /* ═══════════════════════════════════════════════════════════════
  SMALL HELPERS
  ═══════════════════════════════════════════════════════════════ */
  function esc(s) {
    if (U && U.escapeHtml) return U.escapeHtml(s);
    if (typeof s !== 'string') s = String(s == null ? '' : s);
    return s.replace(/&/g, '&amp;').replace(/</g, '&lt;')
      .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }
  function toast(msg, type) { if (U && U.toast) U.toast(msg, type); }

  /** Envelope-unwrapping fetch helper (mobile-git house style). */
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

  function scrollBottom() {
    requestAnimationFrame(function () { chatEl.scrollTop = chatEl.scrollHeight; });
  }

  function wireEmoji(wire) { return WIRE_EMOJI[wire] || '⚡'; }

  /** testedAt considered fresh for 15 minutes. */
  function freshTs(ts) {
    if (!ts) return false;
    var t = Date.parse(ts);
    if (isNaN(t)) return false;
    return (Date.now() - t) < 15 * 60 * 1000;
  }
  function ago(ts) {
    var t = Date.parse(ts || '');
    if (isNaN(t)) return '';
    var s = Math.max(0, (Date.now() - t) / 1000);
    if (s < 90) return 'just now';
    if (s < 3600) return Math.round(s / 60) + ' min ago';
    if (s < 86400) return Math.round(s / 3600) + ' h ago';
    return Math.round(s / 86400) + ' d ago';
  }
  function findProvider(idOrLabel) {
    var needle = String(idOrLabel || '').toLowerCase();
    var i, p;
    for (i = 0; i < state.providers.length; i++) {
      p = state.providers[i];
      if (p.id === needle || String(p.label || '').toLowerCase() === needle) return p;
    }
    for (i = 0; i < state.providers.length; i++) {
      if (state.providers[i].id === idOrLabel) return state.providers[i];
    }
    return null;
  }
  function findPreset(id) {
    for (var i = 0; i < state.presets.length; i++) {
      if (state.presets[i].id === id) return state.presets[i];
    }
    return null;
  }
  function findLabelByWire(wire) {
    for (var i = 0; i < state.providers.length; i++) {
      if (state.providers[i].wire === wire) return state.providers[i].label;
    }
    return null;
  }

  /* ═══════════════════════════════════════════════════════════════
  MARKDOWN (fence-aware paragraph splitting + code-block actions)
  ═══════════════════════════════════════════════════════════════ */

  /**
   * Split raw text into paragraphs on blank lines ("\n\n"), WITHOUT
   * splitting inside ``` fenced code blocks. Used by the streaming
   * renderer so finished paragraphs are appended once and only the
   * trailing paragraph is ever re-rendered.
   */
  function splitStreamBlocks(text) {
    var parts = [];
    var buf = '';
    var inFence = false;
    var atLineStart = true;
    var i = 0;
    while (i < text.length) {
      // A "\n\n" pair outside a fence is a paragraph break. (The first
      // \n ends the previous line, so atLineStart is intentionally NOT
      // required here.)
      if (!inFence && text.charAt(i) === '\n' && text.charAt(i + 1) === '\n') {
        parts.push(buf);
        buf = '';
        i += 2;
        atLineStart = true;
        continue;
      }
      if (atLineStart && text.charAt(i) === '`' && text.slice(i, i + 3) === '```') {
        inFence = !inFence;
      }
      var ch = text.charAt(i);
      buf += ch;
      atLineStart = (ch === '\n');
      i++;
    }
    parts.push(buf);
    return parts;
  }

  function codeWrapHtml(code) {
    return '<div class="ai-code-wrap">' +
      '<div class="ai-code-bar">' +
      '<button type="button" class="ai-cb-btn" data-cb="copy">📋 Copy</button>' +
      '<button type="button" class="ai-cb-btn" data-cb="insert">⤵ Editor</button>' +
      '</div>' +
      '<pre class="ai-code-block"><code>' + code.replace(/\n$/, '') + '</code></pre>' +
      '</div>';
  }

  function fmt(t) {
    var blocks = [];
    var s = esc(t).replace(/```(?:[a-zA-Z0-9_-]*)\n?([\s\S]*?)```/g, function (m, code) {
      blocks.push(codeWrapHtml(code));
      return '\u0000' + (blocks.length - 1) + '\u0000';
    });
    s = s
      .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
      .replace(/`([^`]+)`/g, '<code>$1</code>')
      .replace(/\[([^\]]+)\]\((https?:\/\/[^)\s]+)\)/g, '<a href="$2" target="_blank" rel="noopener">$1</a>')
      .replace(/(^|[\s(])((?:https?:\/\/)[^\s<)]+)/g, function (mm, pre, url) {
        return pre + '<a href="' + url + '" target="_blank" rel="noopener">' + url + '</a>';
      })
      .replace(/\n/g, '<br>');
    return s.replace(/\u0000(\d+)\u0000/g, function (mm, i) { return blocks[+i]; });
  }

  function toolIcon(n) {
    return ({
      list_dir: '📂', read_file: '📖', write_file: '📝', delete_file: '🗑️',
      move_file: '📦', create_folder: '📁', delete_folder: '🗑️',
      search_workspace: '🔍', run_command: '⚡', get_file_info: 'ℹ️'
    })[n] || '🔧';
  }

  /* ═══════════════════════════════════════════════════════════════
  BUBBLES
  ═══════════════════════════════════════════════════════════════ */
  function removeWelcome() {
    var w = chatEl.querySelector('.ai-welcome');
    if (w) w.remove();
    var sc = $('ai-setup-card');
    if (sc) sc.remove();
  }

  function addUserMsg(text) {
    removeWelcome();
    var d = document.createElement('div');
    d.className = 'ai-msg ai-msg-user';
    d.textContent = text;
    d.dataset.raw = text;
    chatEl.appendChild(d);
    scrollBottom();
    return d;
  }

  function addAiMsg(text, rawForCopy) {
    removeWelcome();
    var html = fmt(text);
    var d = document.createElement('div');
    d.className = 'ai-msg ai-msg-ai';
    d.dataset.raw = rawForCopy != null ? rawForCopy : text;
    if (!html || !html.trim()) {
      d.style.opacity = '.6';
      d.style.fontStyle = 'italic';
      d.textContent = '(the model returned no text — try rephrasing, or pick a larger model)';
    } else {
      d.innerHTML = html;
    }
    chatEl.appendChild(d);
    scrollBottom();
    return d;
  }

  function addStoppedDivider() {
    var d = document.createElement('div');
    d.className = 'ai-stopped';
    d.textContent = '⏹ stopped';
    chatEl.appendChild(d);
    scrollBottom();
  }

  /* ── approval cards ────────────────────────────────────────────── */
  function addToolCards(calls) {
    removeWelcome();
    var wrap = document.createElement('div');
    wrap.className = 'ai-tool-cards';
    (calls || []).forEach(function (call) {
      var card = document.createElement('div');
      card.className = 'ai-tool-card';
      card.dataset.id = call.id;
      var args = Object.keys(call.arguments || {}).map(function (k) {
        var v = String(call.arguments[k]);
        if (v.length > 120) v = v.slice(0, 120) + '…';
        return '<span class="ai-tool-arg"><b>' + esc(k) + ':</b> ' + esc(v) + '</span>';
      }).join('');
      card.innerHTML =
        '<div class="ai-tool-head">' + toolIcon(call.name) + ' <b>' + esc(call.name) + '</b></div>' +
        '<div class="ai-tool-args">' + args + '</div>' +
        '<div class="ai-tool-actions">' +
        '<button class="ai-approve" data-id="' + esc(call.id) + '">✓ Approve</button>' +
        '<button class="ai-deny" data-id="' + esc(call.id) + '">✗ Deny</button></div>';
      wrap.appendChild(card);
    });
    chatEl.appendChild(wrap);
    scrollBottom();
    wrap.querySelectorAll('.ai-approve').forEach(function (b) {
      b.addEventListener('click', function () { resolve(wrap, b.dataset.id, true); });
    });
    wrap.querySelectorAll('.ai-deny').forEach(function (b) {
      b.addEventListener('click', function () { resolve(wrap, b.dataset.id, false); });
    });
  }

  /* ── compact log cards (restored transcript) ───────────────────── */
  function briefArgs(args) {
    var out = [];
    var src = (args && typeof args === 'object') ? args : {};
    var ks = Object.keys(src);
    for (var i = 0; i < ks.length && out.join(', ').length < 90; i++) {
      var v = String(src[ks[i]]);
      if (v.length > 40) v = v.slice(0, 40) + '…';
      out.push(ks[i] + ': ' + v);
    }
    return out.join(', ');
  }
  function excerptOneLine(s, n) {
    var t = String(s == null ? '' : s).replace(/\s+/g, ' ').trim();
    if (t.length > n) t = t.slice(0, n) + '…';
    return t;
  }
  function toolLogFromCalls(tcs) {
    var d = document.createElement('div');
    d.className = 'ai-tool-log';
    var lines = '';
    (tcs || []).forEach(function (tc) {
      var fn = (tc && tc.function) || {};
      lines += '<div class="ai-tl-line">' + toolIcon(fn.name) + ' <b>' + esc(fn.name || 'tool') + '</b>' +
        (fn.arguments ? '<span class="ai-tl-args">' + esc(briefArgs(fn.arguments)) + '</span>' : '') +
        '</div>';
    });
    d.innerHTML = lines;
    return d;
  }
  function toolLogResult(entry) {
    var d = document.createElement('div');
    d.className = 'ai-tool-log';
    d.innerHTML = '<div class="ai-tl-line">' + toolIcon(entry.name) + ' <b>' + esc(entry.name || 'tool') + '</b>' +
      '<span class="ai-tl-args">' + esc(excerptOneLine(entry.content, 120)) + '</span></div>';
    return d;
  }

  /* ═══════════════════════════════════════════════════════════════
  TURN CONTEXT — thinking dots + ONE mutable tool row + stream bubble
  ═══════════════════════════════════════════════════════════════ */
  function beginTurn() {
    removeWelcome();
    var think = document.createElement('div');
    think.className = 'ai-msg ai-msg-ai ai-thinking';
    think.innerHTML = '<span class="ai-dots"><span></span><span></span><span></span></span> Thinking…';
    chatEl.appendChild(think);
    scrollBottom();
    turn = {
      thinking: think,
      bubble: null,       // streaming bubble controller
      toolRow: null,
      toolCount: 0,
      lastName: '',
      lastHint: ''
    };
    return turn;
  }

  function removeThinking(t) {
    var el = (t && t.thinking) || chatEl.querySelector('.ai-thinking');
    if (el && el.parentNode) el.remove();
  }

  /** Streaming bubble: buffered deltas flushed ≤1×/100 ms (rAF-aligned);
      finished paragraphs become immutable nodes, only the tail re-renders. */
  function makeStreamBubble() {
    var el = document.createElement('div');
    el.className = 'ai-msg ai-msg-ai streaming';
    var cursor = document.createElement('span');
    cursor.className = 'ai-cursor';
    el.appendChild(cursor);
    chatEl.appendChild(el);
    var raw = '';
    var doneCount = 0;      // paragraphs already appended as finished nodes
    var tailNode = null;
    var scheduled = false;
    var lastFlush = 0;
    var schedTimer = null;  // pending setTimeout id (cancelled on finish)
    var finished = false;   // true once finish() ran — stale flushes must no-op
    /* ★ CRASH FIX: insertBefore() threw NotFoundError when a stale scheduled
    flush ran after finish() had detached the cursor (or removed the whole
    bubble for an empty response). insertNode now degrades to appendChild,
    and flush() no-ops once finished / once the cursor is gone. */
    function insertNode(n) {
      if (cursor.parentNode === el) el.insertBefore(n, cursor);
      else el.appendChild(n);
    }
    function paint() {
      var parts = splitStreamBlocks(raw);
      // Everything except the last part is finished — append once, in order.
      while (doneCount < parts.length - 1) {
        if (tailNode) { tailNode.remove(); tailNode = null; }   // old tail IS this block
        var b = document.createElement('div');
        b.className = 'ai-blk';
        b.innerHTML = fmt(parts[doneCount]);
        insertNode(b);
        doneCount++;
      }
      var tailText = parts[doneCount] || '';
      if (!tailNode) {
        tailNode = document.createElement('div');
        tailNode.className = 'ai-blk ai-tail';
        insertNode(tailNode);
      }
      tailNode.innerHTML = fmt(tailText);
    }
    function flush() {
      scheduled = false;
      schedTimer = null;
      if (finished || !cursor.parentNode) return;   // bubble already closed
      paint();
    }
    function schedule() {
      if (scheduled || finished) return;
      scheduled = true;
      var now = (window.performance && performance.now) ? performance.now() : Date.now();
      var wait = Math.max(0, 100 - (now - lastFlush));
      schedTimer = setTimeout(function () {
        schedTimer = null;
        requestAnimationFrame(function () {
          lastFlush = (window.performance && performance.now) ? performance.now() : Date.now();
          flush();
          scrollBottom();
        });
      }, wait);
    }
    return {
      el: el,
      append: function (delta) {
        raw += String(delta == null ? '' : delta);
        schedule();
      },
      isEmpty: function () { return raw.trim() === ''; },
      /** Finalize synchronously: append remaining blocks, drop cursor. */
      finish: function () {
        if (schedTimer) { clearTimeout(schedTimer); schedTimer = null; }
        scheduled = false;
        finished = true;                    // any in-flight rAF flush becomes a no-op
        try { if (cursor.parentNode) paint(); } catch (e) { }
        var parts = splitStreamBlocks(raw);
        while (doneCount < parts.length) {
          var b = document.createElement('div');
          b.className = 'ai-blk';
          b.innerHTML = fmt(parts[doneCount]);
          insertNode(b);
          doneCount++;
        }
        if (tailNode) { tailNode.remove(); tailNode = null; }
        if (cursor.parentNode) cursor.remove();
        el.classList.remove('streaming');
        if (raw.trim() === '') el.remove();     // nothing ever arrived
        scrollBottom();
      }
    };
  }

  function ensureBubble(t) {
    if (t.bubble) return t.bubble;
    removeThinking(t);
    var sb = makeStreamBubble();
    // The mutable tool row must stay ABOVE the streaming bubble.
    if (t.toolRow && t.toolRow.parentNode === chatEl) {
      chatEl.insertBefore(sb.el, t.toolRow.nextSibling);
    }
    t.bubble = sb;
    scrollBottom();
    return sb;
  }

  function updateToolRow(t) {
    t.toolRow.innerHTML =
      '<span class="ai-tp-ico">' + toolIcon(t.lastName || 'tool') + '</span>' +
      '<span class="ai-tp-name">' + esc(t.lastName || 'working') + '</span>' +
      (t.lastHint ? '<span class="ai-tp-hint">' + esc(t.lastHint) + '</span>' : '') +
      '<span class="ai-tp-count">' + t.toolCount + '</span>';
  }

  function ensureToolRow(t) {
    if (t.toolRow) { updateToolRow(t); return; }
    t.toolRow = document.createElement('div');
    t.toolRow.className = 'ai-tool-progress';
    updateToolRow(t);
    if (t.bubble) chatEl.insertBefore(t.toolRow, t.bubble.el);
    else chatEl.appendChild(t.toolRow);
    scrollBottom();
  }

  function toolEvent(t, name, hint) {
    t.toolCount++;
    t.lastName = String(name || 'tool');
    t.lastHint = hint ? String(hint) : '';
    ensureToolRow(t);
  }

  /** Turn finished → collapse the progress row to a compact summary. */
  function collapseTools(t) {
    if (!t.toolRow) return;
    var row = t.toolRow;
    t.toolRow = null;
    if (t.toolCount > 0 && row.parentNode === chatEl) {
      var done = document.createElement('div');
      done.className = 'ai-tool-done';
      done.textContent = '🔧 ran ' + t.toolCount + ' tool' + (t.toolCount === 1 ? '' : 's');
      chatEl.insertBefore(done, row);
    }
    if (row.parentNode) row.remove();
  }

  function endTurn(t) {
    if (!t) return;
    collapseTools(t);
    if (t.bubble) t.bubble.finish();
    removeThinking(t);
    if (turn === t) turn = null;
    refreshTurnActions();
  }

  /* ═══════════════════════════════════════════════════════════════
  FRIENDLY ERRORS (+ "Fix it" deep-link)
  ═══════════════════════════════════════════════════════════════ */
  function friendlyError(msg) {
    var m = String(msg || '').replace(/^Server error:\s*/i, '');
    var label = null;
    var pm = m.match(/^\[([^\]]+)\]\s*/);           // '[<Provider Label>] …'
    if (pm) { label = pm[1]; m = m.slice(pm[0].length); }

    function r(text, fix) { return { text: text, fixLabel: fix || label }; }

    if (/rate.?limit|\b429\b|resource.?exhausted|too many requests|quota/i.test(m)) {
      return r((label ? label + ': ' : '') + 'rate limit hit. Wait a moment, then tap ↻ Regenerate.');
    }
    if (/api key|unauthorized|\b401\b|invalid.{0,14}key|authentication|x-api-key|nvapi/i.test(m)) {
      if (!label) {
        for (var i = 0; i < state.providers.length; i++) {
          if (m.toLowerCase().indexOf(String(state.providers[i].label).toLowerCase()) !== -1) {
            label = state.providers[i].label;
            break;
          }
        }
      }
      return r((label ? label + ': ' : '') + 'the API key looks missing or wrong.', label);
    }
    if (/insufficient|credit|billing|balance/i.test(m)) {
      return r((label ? label + ': ' : '') + 'this key is out of credits — top up or switch provider.', label);
    }
    if (/cannot reach|refused|econn|could not connect|timed?\s?out|getaddrinfo|enotfound|network|ollama serve/i.test(m)) {
      var local = /ollama|localhost|127\.0\.0\.1/i.test(m) || state.activeWire === 'ollama';
      if (local) {
        return r('Cannot reach Ollama. Start it with “ollama serve”, or check the server URL/port.',
          label || findLabelByWire('ollama'));
      }
      return r('Cannot reach the provider URL. Check the Base URL and your connection.', label);
    }
    if (/HTTP 5\d\d|internal server error/i.test(m)) {
      return r('The AI service had a temporary hiccup. Tap ↻ Regenerate to try again.');
    }
    if (/tool_call_id/i.test(m)) {
      return r('Conversation-format hiccup (missing tool_call_id). Try ↻ Regenerate, or clear the chat if it persists.');
    }
    if (/model returned no text|empty response/i.test(m)) {
      return r('The model produced an empty response — common with very small models. Pick a larger model or rephrase.');
    }
    return r(m);
  }

  function addErrorWithRegenerate(rawMsg) {
    removeThinking(turn);
    var fe = friendlyError(rawMsg);
    var d = document.createElement('div');
    d.className = 'ai-msg ai-msg-error';
    var html = '<div class="ai-err-text">⚠ ' + esc(fe.text) + '</div>' +
      '<div class="ai-msg-actions">' +
      '<button class="ai-act ai-act-regen" title="Regenerate response">↻ Regenerate</button>';
    if (fe.fixLabel) {
      html += '<button class="ai-act ai-act-fix" data-fix="' + esc(fe.fixLabel) + '" title="Open this provider’s settings">🔧 Fix it</button>';
    }
    html += '</div>';
    d.innerHTML = html;
    chatEl.appendChild(d);
    scrollBottom();
  }

  /* ═══════════════════════════════════════════════════════════════
  BUBBLE ACTION BARS (copy · regenerate · delete · edit)
  ═══════════════════════════════════════════════════════════════ */
  function removeAfter(node) {
    var n = node.nextSibling;
    while (n) { var next = n.nextSibling; n.remove(); n = next; }
  }

  /** Remove the node itself and everything after it (exchange delete / edit). */
  function removeFrom(node) {
    if (!node) return;
    removeAfter(node);
    node.remove();
  }

  function attachUserActions(bubble) {
    if (!bubble || bubble.querySelector('.ai-msg-actions')) return;
    var bar = document.createElement('div');
    bar.className = 'ai-msg-actions ai-msg-actions-user';
    bar.innerHTML =
      '<button class="ai-act ai-act-copy" title="Copy text">📋</button>' +
      '<button class="ai-act ai-act-edit" title="Edit &amp; re-send this message">✏️ Edit</button>' +
      '<button class="ai-act ai-act-del" title="Delete this message and the reply">🗑</button>';
    bubble.appendChild(bar);
  }

  function attachAiActions(bubble) {
    if (!bubble || bubble.querySelector('.ai-msg-actions')) return;
    var bar = document.createElement('div');
    bar.className = 'ai-msg-actions';
    bar.innerHTML =
      '<button class="ai-act ai-act-copy" title="Copy text">📋</button>' +
      '<button class="ai-act ai-act-regen" title="Regenerate the latest response">↻</button>' +
      '<button class="ai-act ai-act-del" title="Delete this response">🗑</button>';
    bubble.appendChild(bar);
  }

  function refreshTurnActions() {
    chatEl.querySelectorAll('.ai-msg-actions').forEach(function (el) {
      if (!el.closest('.ai-msg-error')) el.remove();
    });
    var userBubbles = chatEl.querySelectorAll('.ai-msg-user');
    if (!userBubbles.length) return;
    var lastUser = userBubbles[userBubbles.length - 1];
    var aiBubbles = chatEl.querySelectorAll('.ai-msg-ai:not(.ai-thinking)');
    var lastAi = aiBubbles.length ? aiBubbles[aiBubbles.length - 1] : null;
    attachUserActions(lastUser);
    if (lastAi && (lastUser.compareDocumentPosition(lastAi) & Node.DOCUMENT_POSITION_FOLLOWING)) {
      attachAiActions(lastAi);
    }
  }

  function copyText(text, msg) {
    text = String(text == null ? '' : text);
    if (!text) return;
    if (navigator.clipboard) {
      navigator.clipboard.writeText(text)
        .then(function () { toast(msg || '📋 Copied'); })
        .catch(function () { toast('Copy failed', 'error'); });
    } else {
      toast('Clipboard unavailable', 'warning');
    }
  }

  function insertIntoEditor(code) {
    var ME = window.IDE && window.IDE.mobileEditor;
    var cm = (ME && typeof ME.getCM === 'function') ? ME.getCM() : null;
    if (!cm) { toast('Open a file in the editor first', 'warning'); return; }
    var txt = /\n$/.test(code) ? code : code + '\n';
    try {
      if (typeof cm.replaceSelection === 'function') {
        cm.replaceSelection(txt);
      } else if (typeof cm.replaceRange === 'function') {
        var pos = typeof cm.getCursor === 'function' ? cm.getCursor() : { line: 0, ch: 0 };
        cm.replaceRange(txt, pos);
      } else {
        toast('This editor cannot accept inserts', 'warning');
        return;
      }
      if (typeof window._mobileSwitchTab === 'function') window._mobileSwitchTab('editor');
      toast('⤵ Inserted into editor');
    } catch (e) {
      toast('Insert failed: ' + e.message, 'error');
    }
  }

  /* ═══════════════════════════════════════════════════════════════
  SSE TRANSPORT
  ═══════════════════════════════════════════════════════════════ */
  async function sseRequest(action, bodyObj, signal) {
    var base = window.IDE_STREAM_BASE || 'index.php';
    var res = await fetch(base + '?api=' + action, {
      method: 'POST',
      credentials: 'include',
      signal: signal || undefined,
      headers: { 'Content-Type': 'application/json', 'Accept': 'text/event-stream' },
      body: JSON.stringify(bodyObj || {})
    });
    if (!res.ok) {
      var detail = 'HTTP ' + res.status;
      try {
        var j = await res.json();
        if (j && j.error) detail = j.error;
      } catch (e) { }
      var err = new Error(detail);
      err.notSse = true;
      throw err;
    }
    var ct = res.headers.get('content-type') || '';
    if (ct.indexOf('text/event-stream') === -1) {
      var e2 = new Error('not-sse');
      e2.notSse = true;
      throw e2;
    }
    return res;
  }

  function isAbortErr(e) {
    if (!e) return false;
    if (e.aborted) return true;
    if (e.name === 'AbortError') return true;
    return /abort/i.test(String(e.message || ''));
  }

  /**
   * Pump an opened SSE response into a turn.
   * Returns {delivered:boolean, aborted:boolean}.
   */
  async function pumpStream(res, t, signal) {
    var reader = res.body.getReader();
    var dec = new TextDecoder();
    var buf = '';
    var delivered = false;

    function dispatch(block) {
      var event = 'message', data = '';
      block.split('\n').forEach(function (line) {
        if (line.indexOf('event:') === 0) event = line.slice(6).trim();
        else if (line.indexOf('data:') === 0) data += line.slice(5).trim();
      });
      if (data === '') return;
      var payload;
      try { payload = JSON.parse(data); } catch (e) { return; }
      if (event === 'delta') {
        if (typeof payload === 'string' && payload.trim() === '') return;
        delivered = true;
        ensureBubble(t).append(payload);
      }
      else if (event === 'tool') {
        delivered = true;
        toolEvent(t, payload && payload.name, payload && payload.hint);
      }
      else if (event === 'pending') { delivered = true; addToolCards(payload); }
      else if (event === 'error') {
        delivered = true;
        addErrorWithRegenerate(typeof payload === 'string' ? payload : ((payload && payload.message) || 'model error'));
      }
      else if (event === 'done') { /* finalized by endTurn */ }
    }

    try {
      while (true) {
        var r = await reader.read();
        if (r.done) break;
        buf += dec.decode(r.value, { stream: true });
        var idx;
        while ((idx = buf.indexOf('\n\n')) !== -1) {
          var block = buf.slice(0, idx);
          buf = buf.slice(idx + 2);
          dispatch(block);
        }
      }
      buf += dec.decode();
      if (buf.trim() !== '') dispatch(buf);
    } catch (e) {
      if (isAbortErr(e)) return { delivered: delivered, aborted: true };
      throw e;
    }
    return { delivered: delivered, aborted: false };
  }

  function stopStreaming() {
    state.stopped = true;
    if (state.ctrl) {
      try { state.ctrl.abort(); } catch (e) { }
    }
  }

  function setBusyUI(b) {
    state.busy = b;
    if (!sendBtn) return;
    sendBtn.classList.toggle('stopping', b);
    sendBtn.textContent = b ? '⏹' : '➤';
    sendBtn.title = b ? 'Stop' : 'Send';
    sendBtn.setAttribute('aria-label', b ? 'Stop generating' : 'Send message');
  }

  /**
   * One conversational round: stream first; fall back to the JSON
   * endpoint ONLY when the stream failed before delivering any event
   * (prevents the double-post duplication bug). User-initiated stops
   * render an error-free "stopped" divider instead.
   *   makeReq(signal-era fn) → {action, body}
   *   runJson()              → JSON fallback coroutine
   */
  async function converse(makeReq, runJson) {
    state.stopped = false;
    var ctrl = (typeof AbortController !== 'undefined') ? new AbortController() : null;
    state.ctrl = ctrl;
    var t = beginTurn();
    setBusyUI(true);

    var outcome = { delivered: false, aborted: false, err: null };
    try {
      var req = makeReq();
      var res = await sseRequest(req.action, req.body, ctrl ? ctrl.signal : undefined);
      outcome = await pumpStream(res, t, ctrl ? ctrl.signal : undefined);
    } catch (e) {
      if (isAbortErr(e)) outcome.aborted = true;
      else outcome.err = e;
    }

    if (outcome.aborted) {
      // Stopped by the user — never shown as an error.
      addStoppedDivider();
    } else if (outcome.err && !outcome.err.notSse && outcome.delivered) {
      addErrorWithRegenerate('Stream interrupted — tap ↻ Regenerate.');
    } else if (outcome.err && !outcome.err.notSse) {
      addErrorWithRegenerate(outcome.err.message || 'Request failed');
    } else if (!outcome.delivered) {
      // Zero bytes/events → safe to retry via the JSON endpoint.
      try { await runJson(); }
      catch (e2) { addErrorWithRegenerate((e2 && e2.message) || 'Request failed'); }
    }

    endTurn(t);
    setBusyUI(false);
    state.ctrl = null;
    try { inputEl.focus(); } catch (e) { }
  }

  /* ═══════════════════════════════════════════════════════════════
  SEND / APPROVE / REGENERATE / JSON FALLBACKS
  ═══════════════════════════════════════════════════════════════ */
  function send() {
    var text = inputEl.value.trim();
    if (!text || state.busy) return;
    addUserMsg(text);
    inputEl.value = '';
    autoGrow();
    converse(
      function () { return { action: 'ai-stream', body: { message: text } }; },
      function () { return jsonChat(text); }
    );
  }

  async function jsonChat(text) {
    var d = await apiPost('ai-chat', { message: text });
    handleJson(d);
  }

  async function jsonApprove(approvals) {
    var d = await apiPost('ai-approve', { approvals: approvals });
    handleJson(d);
  }

  async function jsonRegenerate() {
    var d = await apiPost('ai-regenerate-json', {});
    handleJson(d);
  }

  function handleJson(data) {
    if (!data) return;
    if (data.type === 'text') addAiMsg(data.content || '(empty response)');
    else if (data.type === 'tool_calls') addToolCards(data.calls || []);
  }

  /* ── approvals ─────────────────────────────────────────────────── */
  async function resolve(wrap, id, approved) {
    wrap.querySelectorAll('button').forEach(function (b) { b.disabled = true; });
    var card = wrap.querySelector('[data-id="' + id + '"]');
    if (card) {
      card.classList.add(approved ? 'approved' : 'denied');
      var act = card.querySelector('.ai-tool-actions');
      if (act) {
        act.innerHTML = approved
          ? '<span class="ai-tool-status ok">✓ Approved</span>'
          : '<span class="ai-tool-status no">✗ Denied</span>';
      }
    }
    if (wrap.querySelectorAll('.ai-tool-card.approved, .ai-tool-card.denied').length <
      wrap.querySelectorAll('.ai-tool-card').length) return;

    var approvals = [];
    wrap.querySelectorAll('.ai-tool-card').forEach(function (c) {
      approvals.push({ id: c.dataset.id, approved: c.classList.contains('approved') });
    });

    await converse(
      function () { return { action: 'ai-approve-stream', body: { approvals: approvals } }; },
      function () { return jsonApprove(approvals); }
    );
  }

  /* ── regenerate ────────────────────────────────────────────────── */
  function regenerate() {
    if (state.busy) return;
    var users = chatEl.querySelectorAll('.ai-msg-user');
    if (!users.length) { toast('Nothing to regenerate — send a message first', 'warning'); return; }
    removeAfter(users[users.length - 1]);
    converse(
      function () { return { action: 'ai-regenerate', body: {} }; },
      function () { return jsonRegenerate(); }
    );
  }

  /* ── delete / edit (server truncation endpoints) ───────────────── */
  async function truncateHistory(mode) {
    try {
      await apiPost('ai-truncate', { mode: mode });
      return true;
    } catch (e) {
      toast('Delete failed: ' + e.message, 'error');
      return false;
    }
  }

  async function deleteLastResponse() {
    if (state.busy) return;
    var ok = await U.modal.confirm({
      title: '🗑 Delete response',
      message: "Delete the AI's last response? Your message stays.",
      confirmText: 'Delete',
      danger: true
    });
    if (!ok) return;
    if (!(await truncateHistory('response'))) return;
    var users = chatEl.querySelectorAll('.ai-msg-user');
    var lastUser = users[users.length - 1];
    if (lastUser) removeAfter(lastUser);
    refreshTurnActions();
    toast('Response deleted');
  }

  async function deleteLastExchange() {
    if (state.busy) return;
    var ok = await U.modal.confirm({
      title: '🗑 Delete message',
      message: "Delete your last message and the AI's reply?",
      confirmText: 'Delete',
      danger: true
    });
    if (!ok) return;
    if (!(await truncateHistory('exchange'))) return;
    var users = chatEl.querySelectorAll('.ai-msg-user');
    var lastUser = users[users.length - 1];
    removeFrom(lastUser);                  // the message AND everything after it
    refreshTurnActions();
    toast('Message deleted');
  }

  async function editLastMessage() {
    if (state.busy) return;
    var users = chatEl.querySelectorAll('.ai-msg-user');
    var lastUser = users[users.length - 1];
    if (!lastUser) return;
    var currentText = lastUser.dataset.raw || lastUser.textContent;
    var newText = await U.modal.input({
      title: '✏️ Edit your message',
      label: 'Message:',
      value: currentText,
      confirmText: 'Save & Re-send',
      hint: "The old reply is removed and the AI answers your edited message."
    });
    if (!newText) return;
    if (newText === currentText) { toast('No changes'); return; }
    if (!(await truncateHistory('exchange'))) return;
    removeFrom(lastUser);
    refreshTurnActions();
    inputEl.value = newText;
    autoGrow();
    send();
  }

  function handleDelete(btn) {
    var bubble = btn.closest('.ai-msg');
    if (!bubble) return;
    if (bubble.classList.contains('ai-msg-user')) deleteLastExchange();
    else deleteLastResponse();
  }

  /* ═══════════════════════════════════════════════════════════════
  COMPOSER — auto-grow (≤6 rows), Enter = newline (touch native)
  ═══════════════════════════════════════════════════════════════ */
  function autoGrow() {
    inputEl.style.height = 'auto';
    var h = Math.min(inputEl.scrollHeight, INPUT_MAX_H);
    inputEl.style.height = h + 'px';
    inputEl.style.overflowY = inputEl.scrollHeight > INPUT_MAX_H ? 'auto' : 'hidden';
  }

  /* ═══════════════════════════════════════════════════════════════
  WELCOME · SETUP CARD · TRANSCRIPT RESTORE
  ═══════════════════════════════════════════════════════════════ */
  function welcomeHtml() {
    var chips = QUICK_PROMPTS.map(function (q) {
      return '<button type="button" class="ai-quick" data-q="' + esc(q) + '">' + esc(q) + '</button>';
    }).join('');
    return '<div class="ai-welcome"><div class="ai-welcome-icon">🤖</div>' +
      '<p>Ask me to read, create, edit, or search files.</p>' +
      '<p class="ai-welcome-hint">I can use workspace tools — writes ask first.</p>' +
      '<div class="ai-quick-row">' + chips + '</div></div>';
  }

  function renderWelcome() {
    if (chatEl.querySelector('.ai-welcome')) return;
    chatEl.insertAdjacentHTML('afterbegin', welcomeHtml());
  }

  function clearChatDom() { chatEl.innerHTML = ''; }

  function toggleSetupCard(show) {
    var existing = $('ai-setup-card');
    if (!show) { if (existing) existing.remove(); return; }
    if (existing || state.setupDismissed) return;
    var card = document.createElement('div');
    card.className = 'ai-setup-card';
    card.id = 'ai-setup-card';
    card.innerHTML =
      '<div class="ai-setup-head">🛠 <b>No AI provider configured yet</b></div>' +
      '<p>Add a provider — NVIDIA, Groq, Gemini, Anthropic, Ollama on your LAN… — and paste its API key.</p>' +
      '<div class="ai-setup-actions">' +
      '<button type="button" class="ai-setup-add" id="ai-setup-add">＋ Add provider</button>' +
      '<button type="button" class="ai-setup-x" id="ai-setup-x">Dismiss</button>' +
      '</div>';
    chatEl.insertBefore(card, chatEl.firstChild);
    scrollBottom();
  }

  function dismissSetup() {
    state.setupDismissed = true;
    try { localStorage.setItem(LS_SETUP_DISMISS, '1'); } catch (e) { }
    toggleSetupCard(false);
  }

  /** GET ai-history → render stored bubbles before anything else. Silent on failure. */
  async function restoreTranscript() {
    state.restored = true;
    try {
      var d = await apiGet('ai-history');
      if (d && d.perm) state.perm = d.perm;
      syncPermUI();
      var hist = (d && d.history) || [];
      if (!hist.length) { renderWelcome(); return; }
      clearChatDom();
      hist.forEach(renderHistoryEntry);
      refreshTurnActions();
      scrollBottom();
    } catch (e) {
      renderWelcome();   // failures are silent
    }
  }

  function renderHistoryEntry(h) {
    if (!h || !h.role) return;
    if (h.role === 'user') {
      addUserMsg(String(h.content == null ? '' : h.content));
      return;
    }
    if (h.role === 'assistant') {
      var tcs = Array.isArray(h.tool_calls) ? h.tool_calls : [];
      if (tcs.length) chatEl.appendChild(toolLogFromCalls(tcs));
      var c = String(h.content == null ? '' : h.content);
      if (c.trim()) addAiMsg(c);
      return;
    }
    if (h.role === 'tool') {
      chatEl.appendChild(toolLogResult(h));
    }
  }

  /* ═══════════════════════════════════════════════════════════════
  STATUS PLUMBING — providers list + legacy status → chip / models
  ═══════════════════════════════════════════════════════════════ */
  async function refreshProviders() {
    var list = await apiGet('ai-providers-list');
    state.providers = (list && list.providers) || [];
    state.presets = (list && list.presets) || [];
    if (list && list.active != null) state.activeId = list.active;
  }

  async function refreshStatus() {
    try { await refreshProviders(); } catch (e) { /* keep previous cache */ }
    try {
      var st = await apiGet('ai-status');
      applyStatus(st);
    } catch (e) {
      applyStatus(null);
    }
    renderManagerIfOpen();
  }

  function applyStatus(st) {
    st = st || {};
    var activeId = st.active != null ? st.active : (st.provider != null ? st.provider : state.activeId);
    state.activeId = activeId;
    var p = findProvider(activeId);
    state.activeWire = p ? p.wire : (st.wire || state.activeWire);
    if (st.permissionMode) state.perm = st.permissionMode;
    syncPermUI();
    updateChip(p, st);
    populateModels(st);

    var configured = 0;
    state.providers.forEach(function (pr) {
      if (pr.hasKey || pr.keyOptional) configured++;
    });
    toggleSetupCard(configured === 0 && isOpen);
  }

  function updateChip(p, st) {
    if (!chipBtn) return;
    var label = p ? (p.label || p.id) : 'No provider';
    if (chipLabel) chipLabel.textContent = label;
    if (chipIco) chipIco.textContent = wireEmoji(p && p.wire);

    var dotCls = 'off';
    var tip = label + ' · tap to manage providers';
    if (state.testing) {
      dotCls = 'testing';
      tip = 'Testing…';
    } else if (p && p.lastOk === false && freshTs(p.testedAt)) {
      dotCls = 'err';
      tip = label + ' · ' + (p.lastError || 'last check failed');
    } else if (st && st.online) {
      if (st.modelLoaded) {
        dotCls = 'on';
        tip = label + ' · ' + (st.activeModel || (p && p.model) || '');
      } else {
        dotCls = 'warn';
        tip = label + ' · reachable — pick a model';
      }
    } else if (p) {
      tip = label + ' · not verified yet (tap ⚙ to test)';
    }
    if (chipDot) chipDot.className = 'ai-chip-dot ' + dotCls;
    chipBtn.classList.toggle('err', dotCls === 'err');
    chipBtn.title = tip;
  }

  function setChipTesting(b) {
    state.testing = b;
    if (b) updateChip(findProvider(state.activeId), null);
  }

  function populateModels(st) {
    if (!modelSel) return;
    st = st || {};
    var list = st.availableModels || [];
    var active = st.activeModel || '';
    if (!list.length) {
      modelSel.innerHTML = '<option value="">— no models —</option>';
      modelSel.disabled = true;
      return;
    }
    var isObjects = (typeof list[0] === 'object' && list[0] !== null);
    var normalised = isObjects
      ? list.map(function (m) { return { value: m.id, label: m.label || m.id }; })
      : list.map(function (m) { return { value: m, label: m }; });
    var sorted = normalised.slice().sort(function (a, b) { return a.label.localeCompare(b.label); });
    modelSel.innerHTML = sorted.map(function (m) {
      return '<option value="' + esc(m.value) + '"' + (m.value === active ? ' selected' : '') + '>' + esc(m.label) + '</option>';
    }).join('');
    if (active && !sorted.some(function (m) { return m.value === active; })) {
      var o = document.createElement('option');
      o.value = active;
      o.textContent = active + '  (unavailable)';
      o.selected = true;
      modelSel.insertBefore(o, modelSel.firstChild);
    }
    modelSel.disabled = false;
  }

  async function onModelChange() {
    var m = modelSel.value;
    if (!m) return;
    setChipTesting(true);
    try {
      var d = await apiPost('ai-model', { model: m, provider: state.activeId });
      applyStatus(d);
      toast('🤖 Model → ' + (d.activeModel || m));
    } catch (e) {
      toast('Model switch failed: ' + e.message, 'error');
      refreshStatus();
    }
    setChipTesting(false);
  }

  async function switchProvider(id, label) {
    hideSheet();
    setChipTesting(true);
    try {
      await apiPost('ai-provider', { provider: id });
      toast((wireEmoji(findProvider(id) && findProvider(id).wire)) + ' Switched to ' + (label || id));
    } catch (e) {
      toast('Provider switch failed: ' + e.message, 'error');
    }
    setChipTesting(false);
    refreshStatus();
  }

  /* ═══════════════════════════════════════════════════════════════
  SHEET INFRASTRUCTURE (JS-injected .m-sheet, mobile-git pattern)
  NOTE: base .m-sheet sits at z-index 100 — BELOW the z-200 overlay —
  so AI sheets carry their own classes raised to sub-overlay level.
  ═══════════════════════════════════════════════════════════════ */
  var _sheet = null;
  var mgrRoot = null;   // live manager sheet container (for re-render)

  function sheetsApi() { return window.IDE && window.IDE.mobileSheets; }

  function buildSheet(title, contentNode, cls) {
    hideSheet();
    var backdrop = document.createElement('div');
    backdrop.className = 'm-sheet-backdrop ai-sheet-backdrop show';
    var sheet = document.createElement('div');
    sheet.className = 'm-sheet ai-sheet show' + (cls ? ' ' + cls : '');
    sheet.innerHTML = '<div class="m-sheet-handle"></div>' +
      '<div class="m-sheet-title">' + esc(title) + '</div>';
    sheet.appendChild(contentNode);
    document.body.appendChild(backdrop);
    document.body.appendChild(sheet);
    _sheet = { backdrop: backdrop, sheet: sheet };

    backdrop.addEventListener('click', hideSheet);
    var handle = sheet.querySelector('.m-sheet-handle');
    if (handle) handle.addEventListener('click', hideSheet);
    var S = sheetsApi();
    if (S && S.registerOverlay) S.registerOverlay('ai-sheet', hideSheet);
    return sheet;
  }

  function hideSheet() {
    var S = sheetsApi();
    if (S && S.unregisterOverlay) S.unregisterOverlay('ai-sheet');
    mgrRoot = null;
    if (!_sheet) return;
    var s = _sheet;
    _sheet = null;
    s.sheet.classList.remove('show');
    s.backdrop.classList.remove('show');
    setTimeout(function () {
      try { s.sheet.remove(); s.backdrop.remove(); } catch (e) { }
    }, 260);
  }

  function renderManagerIfOpen() {
    if (mgrRoot) renderManager(mgrRoot);
  }

  /* ═══════════════════════════════════════════════════════════════
  MANAGER SHEET — providers list · add · permissions · clear
  ═══════════════════════════════════════════════════════════════ */
  function provFreshDot(p) {
    var cls = 'none';
    var title = 'Never tested';
    if (p.testedAt) {
      title = 'Tested ' + ago(p.testedAt) +
        (p.lastOk === true ? ' ✓' : '') +
        (p.lastOk === false ? ' — ' + (p.lastError || 'failed') : '');
      if (p.lastOk === true) cls = 'ok';
      else if (p.lastOk === false) cls = 'bad';
    }
    return '<span class="ai-fresh ' + cls + '" title="' + esc(title) + '"></span>';
  }

  function provRowHtml(p) {
    var act = p.id === state.activeId;
    var meta = (p.model ? p.model : '(default model)') + ' · ' + (p.maskedKey || (p.keyOptional ? 'no key (optional)' : '⚠ no key'));
    return '<div class="ai-prov-row' + (act ? ' active' : '') + '" data-id="' + esc(p.id) + '">' +
      '<span class="ai-prov-ico">' + wireEmoji(p.wire) + '</span>' +
      '<span class="ai-prov-main">' +
      '<span class="ai-prov-name">' + esc(p.label || p.id) +
      (act ? ' <b class="ai-badge-active">ACTIVE</b>' : '') + '</span>' +
      '<span class="ai-prov-meta">' + esc(meta) + '</span>' +
      '</span>' +
      provFreshDot(p) +
      '<button type="button" class="ai-prov-more" aria-label="Provider actions">⋯</button>' +
      '</div>';
  }

  function permSegHtml() {
    return PERM_MODES.map(function (pm) {
      return '<button type="button" data-mode="' + pm.mode + '"' +
        (state.perm === pm.mode ? ' class="active"' : '') + '>' + pm.label + '</button>';
    }).join('');
  }

  function renderManager(root) {
    mgrRoot = root;
    var html = '<div class="ai-mgr-list">';
    if (!state.providers.length) {
      html += '<div class="ai-mgr-empty">No providers yet.<br>Add one and paste its API key to start chatting.</div>';
    }
    state.providers.forEach(function (p) { html += provRowHtml(p); });
    html += '</div>';

    html += '<button type="button" class="ai-mgr-add" id="ai-mgr-add">＋ Add provider</button>';
    html += '<div class="ai-mgr-sub">AGENT PERMISSIONS</div>';
    html += '<div class="ai-seg" id="ai-perm-seg">' + permSegHtml() + '</div>';
    html += '<button type="button" class="ai-mgr-clear" id="ai-mgr-clear">🧹 Clear conversation</button>';
    root.innerHTML = html;

    // Provider rows: tap = switch · ⋯ / long-press = actions
    root.querySelectorAll('.ai-prov-row').forEach(function (row) {
      var pid = row.getAttribute('data-id');
      var p = findProvider(pid);
      var moreBtn = row.querySelector('.ai-prov-more');

      row.addEventListener('click', function (e) {
        if (e.target.closest('.ai-prov-more')) return;
        if (lpFired) { lpFired = false; return; }   // long-press already handled
        switchProvider(pid, p && p.label);
      });

      moreBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        openRowMenu(p);
      });

      var lpTimer = null, lpFired = false, moved = false, sx = 0;
      row.addEventListener('touchstart', function (e) {
        moved = false;
        lpFired = false;
        sx = e.touches[0].clientX;
        lpTimer = setTimeout(function () {
          lpFired = true;                     // swallow the trailing click
          if (navigator.vibrate) navigator.vibrate(12);
          openRowMenu(p);
        }, 480);
      }, { passive: true });
      row.addEventListener('touchmove', function (e) {
        if (Math.abs(e.touches[0].clientX - sx) > 8) moved = true;
        if (moved && lpTimer) clearTimeout(lpTimer);
      }, { passive: true });
      ['touchend', 'touchcancel'].forEach(function (ev) {
        row.addEventListener(ev, function () { if (lpTimer) clearTimeout(lpTimer); }, { passive: true });
      });
      row.addEventListener('contextmenu', function (e) {
        e.preventDefault();
        openRowMenu(p);
      });
    });

    var addBtn = root.querySelector('#ai-mgr-add');
    if (addBtn) addBtn.addEventListener('click', function () { openFormSheet(null); });

    var seg = root.querySelector('#ai-perm-seg');
    if (seg) {
      seg.addEventListener('click', function (e) {
        var b = e.target.closest('[data-mode]');
        if (!b) return;
        postPermission(b.getAttribute('data-mode'));
      });
    }

    var clearBtn = root.querySelector('#ai-mgr-clear');
    if (clearBtn) clearBtn.addEventListener('click', clearConversation);
  }

  function openManagerSheet() {
    ensureProvidersLoaded().then(function () {
      var c = document.createElement('div');
      c.className = 'ai-mgr';
      buildSheet('AI PROVIDERS', c);
      renderManager(c);
    }).catch(function (e) {
      toast('Could not load providers: ' + e.message, 'error');
    });
  }

  async function ensureProvidersLoaded() {
    if (!state.presets.length || !state.providers.length) {
      try { await refreshProviders(); } catch (e) { /* surfaced by callers */ }
    }
  }

  /** Keep the composer dropdown AND the manager sheet's segmented control
    showing the same (live) permission mode, wherever it was changed. */
  function syncPermUI() {
    if (permSel && permSel.value !== state.perm) permSel.value = state.perm;
    if (mgrRoot) {
      mgrRoot.querySelectorAll('#ai-perm-seg [data-mode]').forEach(function (b) {
        b.classList.toggle('active', b.getAttribute('data-mode') === state.perm);
      });
    }
  }
  async function postPermission(mode) {
    try {
      await apiPost('ai-permission', { mode: mode });
      state.perm = mode;
      var labels = {
        always_ask: '🔒 Always ask',
        ask_destructive: '🛡 Ask before writes',
        auto_approve: '⚡ Auto-approve'
      };
      toast('AI permission: ' + (labels[mode] || mode));
    } catch (e) {
      toast('Permission change failed: ' + e.message, 'error');
    }
    syncPermUI();
  }

  async function clearConversation() {
    var ok = await U.modal.confirm({
      title: '🧹 Clear conversation?',
      message: 'Wipes the whole AI chat session for this device. This cannot be undone.',
      confirmText: 'Clear',
      danger: true
    });
    if (!ok) return;
    try {
      await apiPost('ai-clear', {});
      state.restored = false;   // next open re-fetches a fresh (empty) transcript
      clearChatDom();
      renderWelcome();
      toast('🧹 AI conversation cleared');
      hideSheet();
    } catch (e) {
      toast('Clear failed: ' + e.message, 'error');
    }
  }

  /* ═══════════════════════════════════════════════════════════════
  ROW MENU — Edit / Test / Delete
  ═══════════════════════════════════════════════════════════════ */
  function openRowMenu(p) {
    if (!p) return;
    hideSheet();
    var items = [
      { icon: '✏️', label: 'Edit', fn: function () { openFormSheet(p); } },
      {
        icon: '⚡', label: 'Test connection', fn: function () {
          setChipTesting(true);
          apiPost('ai-provider-test', { id: p.id })
            .then(function (r) {
              if (r && r.ok) toast('⚡ ' + (p.label) + ' OK · ' + (r.latencyMs || 0) + 'ms');
              else toast('⚡ ' + (p.label) + ': ' + ((r && r.error) || 'failed'), 'error');
            })
            .catch(function (e) { toast('Test failed: ' + e.message, 'error'); })
            .then(function () { setChipTesting(false); refreshStatus(); });
        }
      },
      {
        icon: '🗑', label: 'Delete', danger: true, fn: function () {
          U.modal.confirm({
            title: 'Delete provider?',
            message: 'Remove "' + (p.label || p.id) + '"' + (p.hasKey ? ' and its stored key' : '') + '?',
            confirmText: 'Delete',
            danger: true
          }).then(function (ok) {
            if (!ok) return;
            apiPost('ai-providers-delete', { id: p.id })
              .then(function (r) {
                toast('🗑 Deleted ' + (p.label || p.id));
                if (r && r.active != null) state.activeId = r.active;
                refreshStatus();
              })
              .catch(function (e) { toast('Delete failed: ' + e.message, 'error'); });
          });
        }
      }
    ];

    var backdrop = document.createElement('div');
    backdrop.className = 'm-sheet-backdrop ai-sheet-backdrop show';
    var sheet = document.createElement('div');
    sheet.className = 'm-sheet ai-sheet show';
    var h = '<div class="m-sheet-title">' + esc(p.label || p.id) + '</div>';
    items.forEach(function (it, idx) {
      h += '<button class="m-sheet-row' + (it.danger ? ' danger' : '') + '" data-idx="' + idx + '">' +
        '<span class="sr-ico">' + it.icon + '</span>' + esc(it.label) + '</button>';
    });
    h += '<button class="m-sheet-row" data-idx="-1"><span class="sr-ico">✖</span>Cancel</button>';
    sheet.innerHTML = h;
    document.body.appendChild(backdrop);
    document.body.appendChild(sheet);
    _sheet = { backdrop: backdrop, sheet: sheet };
    var S = sheetsApi();
    if (S && S.registerOverlay) S.registerOverlay('ai-sheet', hideSheet);

    sheet.addEventListener('click', function (e) {
      var btn = e.target.closest('[data-idx]');
      if (!btn) return;
      var idx = parseInt(btn.getAttribute('data-idx'), 10);
      hideSheet();
      if (idx >= 0 && items[idx] && typeof items[idx].fn === 'function') {
        setTimeout(items[idx].fn, 60);
      }
    });
    backdrop.addEventListener('click', hideSheet);
  }

  /* ═══════════════════════════════════════════════════════════════
  FORM SHEET — Add / Edit provider
  ═══════════════════════════════════════════════════════════════ */
  function openFormSheet(editing) {
    ensureProvidersLoaded().then(function () {
      buildFormSheet(editing);
    }).catch(function (e) {
      toast('Could not load presets: ' + e.message, 'error');
    });
  }

  function buildFormSheet(editing) {
    hideSheet();
    var isNew = !editing;
    var c = document.createElement('div');
    c.className = 'ai-form';

    var presetOpts = '<option value="">' + (isNew ? '— choose a preset —' : '(keep current)') + '</option>';
    state.presets.forEach(function (pr) {
      var sel = editing && editing.preset === pr.id ? ' selected' : '';
      presetOpts += '<option value="' + esc(pr.id) + '"' + sel + '>' +
        wireEmoji(pr.wire) + ' ' + esc(pr.label) + '</option>';
    });
    if (editing && editing.preset && !findPreset(editing.preset)) {
      presetOpts += '<option value="" selected>(unknown preset)</option>';
    }

    c.innerHTML =
      '<div class="ai-form-body">' +
      '<label class="ai-fl" for="ai-f-preset">Preset</label>' +
      '<select id="ai-f-preset" class="ai-fi">' + presetOpts + '</select>' +
      '<p class="ai-f-note" id="ai-f-note"></p>' +

      '<label class="ai-fl" for="ai-f-label">Label</label>' +
      '<input id="ai-f-label" class="ai-fi" type="text" maxlength="60" autocomplete="off" spellcheck="false"' +
      ' value="' + esc(editing ? (editing.label || '') : '') + '">' +

      '<label class="ai-fl" for="ai-f-url">Base URL</label>' +
      '<input id="ai-f-url" class="ai-fi" type="text" inputmode="url" autocomplete="off" spellcheck="false"' +
      ' placeholder="https://api.example.com/v1" value="' + esc(editing ? (editing.baseUrl || '') : '') + '">' +

      '<label class="ai-fl" for="ai-f-key">API key</label>' +
      '<input id="ai-f-key" class="ai-fi" type="password" autocomplete="new-password"' +
      ' placeholder="' + esc(editing && editing.maskedKey ? editing.maskedKey : 'paste API key') + '">' +
      '<p class="ai-f-note" id="ai-f-keyhint"></p>' +

      '<label class="ai-fl" for="ai-f-model">Model</label>' +
      '<div class="ai-f-row">' +
      '<input id="ai-f-model" class="ai-fi" type="text" list="ai-f-models" autocomplete="off" spellcheck="false"' +
      ' placeholder="model id" value="' + esc(editing ? (editing.model || '') : '') + '">' +
      '<datalist id="ai-f-models"></datalist>' +
      '<button type="button" class="ai-f-mini" id="ai-f-fetch">⇩ Fetch</button>' +
      '</div>' +

      '<button type="button" class="ai-f-adv-toggle" id="ai-f-advbtn">Advanced ▸ extra headers (JSON)</button>' +
      '<textarea id="ai-f-headers" class="ai-fi hidden" rows="3" spellcheck="false"' +
      ' placeholder=\'{"X-Title": "Quirky IDE"}\'></textarea>' +
      '<p class="ai-f-note">Leave blank to clear extra headers. Never sent anywhere but this provider.</p>' +

      '<div class="ai-f-status" id="ai-f-status"></div>' +
      '</div>' +
      '<div class="ai-f-actions">' +
      (isNew ? '' : '<button type="button" class="ai-f-btn danger" id="ai-f-del">🗑 Delete</button>') +
      '<button type="button" class="ai-f-btn ghost" id="ai-f-test">⚡ Test</button>' +
      '<button type="button" class="ai-f-btn primary" id="ai-f-save">💾 Save</button>' +
      '</div>';

    var sheet = buildSheet(isNew ? 'ADD PROVIDER' : 'EDIT PROVIDER', c, 'ai-form-sheet');
    if (!sheet) return;

    var selPreset = c.querySelector('#ai-f-preset');
    var noteEl = c.querySelector('#ai-f-note');
    var labelIn = c.querySelector('#ai-f-label');
    var urlIn = c.querySelector('#ai-f-url');
    var keyIn = c.querySelector('#ai-f-key');
    var keyHint = c.querySelector('#ai-f-keyhint');
    var modelIn = c.querySelector('#ai-f-model');
    var datalist = c.querySelector('#ai-f-models');
    var fetchBtn = c.querySelector('#ai-f-fetch');
    var advBtn = c.querySelector('#ai-f-advbtn');
    var advTa = c.querySelector('#ai-f-headers');
    var statusEl = c.querySelector('#ai-f-status');
    var testBtn = c.querySelector('#ai-f-test');
    var saveBtn = c.querySelector('#ai-f-save');
    var delBtn = c.querySelector('#ai-f-del');

    var labelTouched = !isNew;
    labelIn.addEventListener('input', function () { labelTouched = true; });

    function setStatus(msg, kind) {
      statusEl.textContent = msg || '';
      statusEl.className = 'ai-f-status' + (kind ? ' ' + kind : '');
    }

    function applyPreset(pid, keepValues) {
      var pr = findPreset(pid);
      if (!pr) { noteEl.textContent = ''; return; }
      if (!keepValues) {
        if (!labelTouched) labelIn.value = pr.label;
        urlIn.value = pr.baseUrl || '';
      }
      noteEl.textContent = pr.note || '';
      keyHint.textContent = pr.keyOptional
        ? 'Key optional for this preset.'
        : (isNew ? '' : 'Leave empty to keep the saved key.');
      datalist.innerHTML = (pr.curated || []).map(function (m) {
        return '<option value="' + esc(m.id) + '">' + esc((m.label || '') + (m.tag ? ' · ' + m.tag : '')) + '</option>';
      }).join('');
    }

    selPreset.addEventListener('change', function () { applyPreset(selPreset.value, false); });
    if (editing) applyPreset(editing.preset, true);
    else if (state.presets.length === 1) applyPreset(state.presets[0].id, false);

    advBtn.addEventListener('click', function () {
      var open = advTa.classList.toggle('hidden');
      advBtn.textContent = 'Advanced ' + (open ? '▸' : '▾') + ' extra headers (JSON)';
    });

    function effectiveFetchId() {
      if (editing) return editing.id;
      return selPreset.value || null;   // transient presets are fetchable server-side
    }

    fetchBtn.addEventListener('click', function () {
      var pid = effectiveFetchId();
      if (!pid) { setStatus('Pick a preset first (or save the provider).', 'warn'); return; }
      fetchBtn.disabled = true;
      fetchBtn.textContent = '…';
      // ★ Live models need the provider (and its key) on the server. When the
      // form already holds a valid definition, save it QUIETLY first — same
      // pattern as the ⚡ Test button — so a typed-but-unsaved key is usable
      // for the real /models call (public hosts like OpenRouter work anyway).
      var saveFirst = (validate(collect()) === null)
        ? persist(true).catch(function () { return null; })
        : Promise.resolve(null);
      saveFirst.then(function () {
        var target = (editing && editing.id) ? editing.id : pid;
        return apiGet('ai-models', { provider: target, refresh: 1 });
      })
        .then(function (d) {
          var models = (d && d.models) || [];
          datalist.innerHTML = models.map(function (m) {
            var id = (typeof m === 'object' && m) ? m.id : m;
            var lb = (typeof m === 'object' && m) ? (m.label || m.id) : m;
            return '<option value="' + esc(id) + '">' + esc(lb) + '</option>';
          }).join('');
          setStatus(models.length
            ? '✓ ' + models.length + ' live models — tap the model field for suggestions.'
            : 'No public model list — type the model id manually.', models.length ? 'ok' : 'warn');
        })
        .catch(function (e) { setStatus('✗ ' + e.message, 'err'); })
        .then(function () {
          fetchBtn.disabled = false;
          fetchBtn.textContent = '⇩ Fetch';
        });
    });

    function collect() {
      var payload = {
        label: labelIn.value.trim(),
        baseUrl: urlIn.value.trim(),
        apiKey: keyIn.value,               // '' = keep existing (server-side rule)
        model: modelIn.value.trim()
      };
      if (editing) payload.id = editing.id;
      if (selPreset.value) payload.preset = selPreset.value;
      var hj = advTa.value.trim();
      if (hj !== '') payload.headersJson = hj;
      return payload;
    }

    function validate(payload) {
      if (!editing && !payload.preset) return 'Choose a preset (its wire format defines the endpoint).';
      if (!payload.baseUrl) return 'Base URL is required.';
      if (!/^https?:\/\/.+/i.test(payload.baseUrl)) return 'Base URL must start with http:// or https://';
      if (!payload.label) return 'Label is required.';
      return null;
    }

    function persist(quiet) {
      var payload = collect();
      var bad = validate(payload);
      if (bad) { setStatus('⚠ ' + bad, 'err'); return Promise.reject(new Error(bad)); }
      if (quiet !== true) setStatus('Saving…');
      return apiPost('ai-providers-save', payload).then(function (sum) {
        if (sum && sum.id) {
          editing = sum;                    // subsequent Test/Delete target this id
          if (!selPreset.value && sum.preset) selPreset.value = sum.preset;
        }
        if (quiet !== true) {
          setStatus('✓ Saved' + (sum && sum.maskedKey && sum.hasKey ? ' · key ' + sum.maskedKey : ''), 'ok');
          toast('💾 Saved ' + (sum && sum.label ? sum.label : 'provider'));
        }
        return refreshStatus();   // repaint chip / models / setup card
      });
    }

    saveBtn.addEventListener('click', function () {
      saveBtn.disabled = true;
      persist(false)
        .then(function () {
          setTimeout(function () {
            hideSheet();
            openManagerSheet();
          }, 500);
        })
        .catch(function () { /* status already shows the reason */ })
        .then(function () { saveBtn.disabled = false; });
    });

    testBtn.addEventListener('click', function () {
      testBtn.disabled = true;
      var prev = testBtn.textContent;
      testBtn.textContent = '⏳ Testing…';
      // The endpoint tests STORED definitions — persist the form first so the
      // test reflects what the user sees.
      persist(true)
        .then(function () {
          return apiPost('ai-provider-test', { id: editing.id });
        })
        .then(function (r) {
          if (r && r.ok) {
            setStatus('✓ ' + (r.latencyMs || 0) + 'ms' + (r.detail ? ' · ' + r.detail : ''), 'ok');
          } else {
            setStatus('✗ ' + ((r && r.error) || 'connection failed'), 'err');
          }
        })
        .catch(function (e) { setStatus('✗ ' + e.message, 'err'); })
        .then(function () {
          testBtn.disabled = false;
          testBtn.textContent = prev;
          refreshStatus();
        });
    });

    if (delBtn) {
      delBtn.addEventListener('click', function () {
        var target = editing;
        U.modal.confirm({
          title: 'Delete provider?',
          message: 'Remove "' + (target.label || target.id) + '"' + (target.hasKey ? ' and its stored key' : '') + '?',
          confirmText: 'Delete',
          danger: true
        }).then(function (ok) {
          if (!ok) return;
          apiPost('ai-providers-delete', { id: target.id })
            .then(function (r) {
              toast('🗑 Deleted ' + (target.label || target.id));
              if (r && r.active != null) state.activeId = r.active;
              hideSheet();
              openManagerSheet();
              refreshStatus();
            })
            .catch(function (e) { setStatus('✗ ' + e.message, 'err'); });
        });
      });
    }
  }

  /* ═══════════════════════════════════════════════════════════════
  OPEN / CLOSE (workshop lifecycle — no drag-to-close)
  ═══════════════════════════════════════════════════════════════ */
  function openPanel() {
    if (isOpen) { refreshStatus(); return; }
    isOpen = true;
    overlay.classList.remove('hidden');
    void overlay.offsetWidth;             // reflow → transition fires
    overlay.classList.add('show');
    autoGrow();                           // re-measure now that the panel is visible
    var S = sheetsApi();
    if (S && S.registerOverlay) S.registerOverlay('m-ai-overlay', closePanel);

    if (!state.restored) {
      restoreTranscript();                // renders welcome/history
    } else if (!chatEl.children.length) {
      renderWelcome();
    }
    refreshStatus();
  }

  function closePanel() {
    if (!isOpen) return;
    isOpen = false;
    overlay.classList.remove('show');
    var S = sheetsApi();
    if (S && S.unregisterOverlay) S.unregisterOverlay('m-ai-overlay');
    setTimeout(function () { overlay.classList.add('hidden'); }, 300);
    hideSheet();
  }

  /* ═══════════════════════════════════════════════════════════════
  WIRING
  ═══════════════════════════════════════════════════════════════ */
  function init() {
    if (wired) return;
    wired = true;

    // Send ⇄ Stop (single combined button)
    if (sendBtn) {
      sendBtn.addEventListener('click', function () {
        if (state.busy) stopStreaming();
        else send();
      });
    }

    if (inputEl) inputEl.addEventListener('input', autoGrow);
    // NOTE: no Enter-to-submit trap — on touch, Enter inserts a newline.

    if (chipBtn) chipBtn.addEventListener('click', openManagerSheet);
    if (modelSel) {
      modelSel.addEventListener('change', function () { onModelChange(); });
    }
    if (permSel) {
      permSel.addEventListener('change', function () { postPermission(permSel.value); });
    }

    chatEl.addEventListener('click', function (e) {
      var q = e.target.closest('.ai-quick');
      if (q) {
        inputEl.value = q.getAttribute('data-q') || q.textContent;
        autoGrow();
        inputEl.focus();
        return;
      }
      if (e.target.closest('#ai-setup-add')) { openFormSheet(null); return; }
      if (e.target.closest('#ai-setup-x')) { dismissSetup(); return; }

      var cb = e.target.closest('[data-cb]');
      if (cb) {
        var wrap = cb.closest('.ai-code-wrap');
        var codeEl = wrap ? wrap.querySelector('code') : null;
        if (!codeEl) return;
        if (cb.getAttribute('data-cb') === 'insert') insertIntoEditor(codeEl.textContent);
        else copyText(codeEl.textContent, '📋 Code copied');
        return;
      }

      if (e.target.closest('.ai-act-regen')) { e.preventDefault(); regenerate(); return; }
      if (e.target.closest('.ai-act-edit')) { e.preventDefault(); editLastMessage(); return; }
      var fixBtn = e.target.closest('.ai-act-fix');
      if (fixBtn) {
        var p = findProvider(fixBtn.getAttribute('data-fix'));
        if (p) openFormSheet(p);
        else openManagerSheet();
        return;
      }
      var cp = e.target.closest('.ai-act-copy');
      if (cp) {
        var b = cp.closest('.ai-msg');
        copyText(b ? (b.dataset.raw || b.textContent) : '', '📋 Copied');
        return;
      }
      var delBtn = e.target.closest('.ai-act-del');
      if (delBtn) { e.preventDefault(); handleDelete(delBtn); return; }
    });

    autoGrow();
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
   * @namespace window.IDE.mobileAi
   * Multi-provider AI assistant panel (v10 redesign).
   */
  window.IDE.mobileAi = {
    /** Open the AI overlay (refreshes status + restores transcript). */
    open: openPanel,
    /** Close the AI overlay. */
    close: closePanel,
    /** Focus the chat input. */
    focus: function () { inputEl.focus(); },
    /** Re-fetch providers + status and repaint the header chip. */
    refresh: refreshStatus,
    isOpen: function () { return isOpen; }
  };
})();
