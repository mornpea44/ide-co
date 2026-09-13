// File: mobile-preview.js
/**
* ═══════════════════════════════════════════════════════════════════════════
*  QUIRKY IDE — MOBILE PREVIEW MODULE (Phase 7 · v5 · speed & reliability)
* ═══════════════════════════════════════════════════════════════════════════
*
*  v5 changes (paint-first pipeline):
*    • The render path NEVER blocks on workshop-status anymore. HTML/SVG/
*      Markdown/images paint instantly on-device; the Apache question is
*      resolved by a background check that re-points the frame only when
*      Apache is genuinely running for the current file.
*    • PHP live renders are coalesced (one request in flight, one queued),
*      abortable (15 s client cap), and show a thin progress line instead
*      of blanking the last good render with a skeleton.
*    • Server-mode repaints dedupe on url + content hash, so tab switches
*      are no-ops while real edits always reload.
*    • Images get a save/refresh-driven cache buster so replaced images
*      update without a manual reload.
*    • Blank-screen watchdog no longer re-renders while a PHP request is in
*      flight, right after a paint, or while an error strip is visible.
*    • SAVE mode refreshes every content type on save (images included).
*
*  v4 features kept: standalone micro-server mode, Apache mode, truthful
*  error strip, Live/Save chip, scroll preservation, console drawer, pane
*  form bridge, per-family zoom persistence, image/markdown/unsupported
*  content types, pinch zoom, nav guard, device frames.
* ═══════════════════════════════════════════════════════════════════════════
*/
(function () {
  'use strict';
  var U = window.IDE && window.IDE.utils;
  if (!U) return;

  /* ═══════════════════════════════════════════════════════════════
     DOM REFERENCES
  ═══════════════════════════════════════════════════════════════ */
  var mPreviewFrame = document.getElementById('m-preview-frame');
  var mPreviewEmpty = document.getElementById('m-preview-empty');
  var mPreviewFile = document.getElementById('m-preview-file');
  var mDeviceStage = document.getElementById('m-device-stage');
  var mDeviceShell = document.getElementById('m-device-shell');
  var mDeviceWrap = document.getElementById('m-device-wrap');
  var mPvSizeLbl = document.getElementById('m-pv-size');
  var mPreviewPanel = document.getElementById('mp-preview');
  var mPreviewBodyEl = document.getElementById('m-preview-body');

  /* ═══════════════════════════════════════════════════════════════
     STATE
  ═══════════════════════════════════════════════════════════════ */
  var mPvDevice = 'phone';
  var mPvHtmlTimer = null;
  var mPvPhpTimer = null;
  var mPvSeq = 0;
  var MPV_ZOOM_MIN = 0.25;
  var MPV_ZOOM_MAX = 2.0;
  var MPV_ZOOM_STEP = 0.25;
  var MPV_ZOOM_DEFAULT = 1.0;
  var MPV_IMG_ZOOM_DEFAULT = 0.80;
  var MPV_MD_ZOOM_DEFAULT = 0.75;
  var mPvUserMult = MPV_ZOOM_DEFAULT;
  var mPvPinchBase = null;
  var mPvZoomChip = document.getElementById('mpv-zoom-reset');

  /* ── Apache / project state (background-only in v5) ── */
  var mPvProjects = null;
  var mPvApacheRunning = false;
  var mPvApachePort = 8082;
  var mPvApacheServing = false;
  var mPvProjectsLoadedAt = 0;
  var mPvApacheTimer = null;
  var mPvProjFetching = false;        // v5: background fetch in flight
  var mPvApacheRepointed = '';        // v5: path we already re-pointed to

  /* ── micro-server + UX state ── */
  var mPvServerServing = false;
  var mPvServerPort = 0;
  var mPvLastServerKey = '';          // v5: url + content-hash dedupe key
  var mPvScrollMap = {};
  var mPvConsoleEntries = [];
  var MPV_CONSOLE_CAP = 200;
  var REFRESH_MODE_KEY = 'quirky.ide.preview.refresh';
  var ZOOM_STORE_PREFIX = 'quirky.ide.preview.zoom.';

  /* ── v5 pipeline state ── */
  var mPvContentType = 'web';
  var mPvHasRendered = false;         // got any paint yet? (skeleton gate)
  var mPvLastPaintAt = 0;             // watchdog guard timestamp
  var mPvPhpInFlight = false;         // one PHP request at a time
  var mPvPhpQueued = false;           // a newer render wants to run after
  var mPvPhpAbort = null;             // AbortController for the live request
  var mPvImgBust = 0;                 // image cache-buster generation

  var IMAGE_EXTS = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'bmp', 'ico', 'avif', 'svg'];
  var UNSUPPORTED_EXTS = ['mp4', 'webm', 'ogg', 'mp3', 'wav', 'avi', 'mov',
    'mkv', 'flac', 'aac', 'm4a', 'wma', 'mpg', 'mpeg', '3gp'];

  try {
    var storedDevice = localStorage.getItem('quirky.ide.preview.device');
    if (storedDevice === 'desktop' || storedDevice === 'tablet' || storedDevice === 'phone') mPvDevice = storedDevice;
  } catch (e) { }

  /* ── small helpers ── */
  function toast(msg) { if (U && U.toast) U.toast(msg); }
  function mPvEsc(s) {
    if (typeof s !== 'string') return '';
    return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }
  function showPreviewSkeleton() {
    var skel = document.getElementById('m-preview-skeleton');
    if (skel) skel.classList.remove('hidden');
  }
  function hidePreviewSkeleton() {
    var skel = document.getElementById('m-preview-skeleton');
    if (skel) skel.classList.add('hidden');
  }
  /* v5: cheap content fingerprint for server-mode dedupe */
  function mPvQuickHash(s) {
    var h = 5381;
    s = String(s || '');
    for (var i = 0; i < s.length; i++) { h = ((h << 5) + h + s.charCodeAt(i)) | 0; }
    return (h >>> 0).toString(36);
  }
  /* v5: thin "working on it" line — the last good render stays visible */
  function showUpdatingBar() {
    var b = document.getElementById('mpv-updating-bar');
    if (b) b.classList.remove('hidden');
  }
  function hideUpdatingBar() {
    var b = document.getElementById('mpv-updating-bar');
    if (b) b.classList.add('hidden');
  }

  /* ═══════════════════════════════════════════════════════════════
     CONTENT TYPE + ZOOM DEFAULTS
  ═══════════════════════════════════════════════════════════════ */
  function getContentType(ext) {
    if (IMAGE_EXTS.indexOf(ext) !== -1) return 'image';
    if (ext === 'md' || ext === 'markdown') return 'markdown';
    if (UNSUPPORTED_EXTS.indexOf(ext) !== -1) return 'unsupported';
    return 'web';
  }
  function shouldShowZoom() {
    return (mPvContentType === 'image' || mPvContentType === 'markdown');
  }
  function getDefaultZoom() {
    if (mPvContentType === 'image') return MPV_IMG_ZOOM_DEFAULT;
    if (mPvContentType === 'markdown') return MPV_MD_ZOOM_DEFAULT;
    return MPV_ZOOM_DEFAULT;
  }
  var mPvZoomForType = '';
  function storedZoomFor(type) {
    try {
      var v = parseFloat(localStorage.getItem(ZOOM_STORE_PREFIX + type));
      if (!isFinite(v)) return 0;
      return Math.max(MPV_ZOOM_MIN, Math.min(MPV_ZOOM_MAX, v));
    } catch (e) { return 0; }
  }
  function saveZoomFor(type, mult) {
    try { localStorage.setItem(ZOOM_STORE_PREFIX + type, String(Math.round(mult * 100) / 100)); } catch (e) { }
  }
  function mPvSetContentType(t) {
    mPvContentType = t;
    /* ★ v5.3: web pages keep the page's OWN scrollbar (the illusion of a
       real browser); the iframe's native scrollbar is hidden by CSS.
       Markdown keeps its clean invisible-scroll surface (mpv-clean). */
    if (mPreviewBodyEl) mPreviewBodyEl.classList.toggle('mpv-noscroll', t === 'web');
    if (mPvZoomForType === t) return;
    mPvZoomForType = t;
    var saved = storedZoomFor(t);
    mPvUserMult = saved || getDefaultZoom();
    mpvUpdateZoomChip();
    mpvUpdateZoomButtons();
  }

  /* ═══════════════════════════════════════════════════════════════
     ZOOM CONTROLS
  ═══════════════════════════════════════════════════════════════ */
  function mpvUpdateZoomChip() {
    if (!mPvZoomChip) return;
    var pct = Math.round(mPvUserMult * 100);
    mPvZoomChip.textContent = pct + '%';
    mPvZoomChip.classList.remove('mpv-zoom-pulse');
    void mPvZoomChip.offsetWidth;
    mPvZoomChip.classList.add('mpv-zoom-pulse');
  }
  function mpvUpdateZoomButtons() {
    var b = getZoomBounds();
    var bOut = document.getElementById('mpv-zoom-out');
    var bIn = document.getElementById('mpv-zoom-in');
    if (bOut) {
      bOut.disabled = mPvUserMult <= b.min;
      bOut.style.opacity = mPvUserMult <= b.min ? '0.3' : '1';
    }
    if (bIn) {
      bIn.disabled = mPvUserMult >= b.max;
      bIn.style.opacity = mPvUserMult >= b.max ? '0.3' : '1';
    }
  }
  function getZoomBounds() {
    if (mPvDevice === 'phone') return { min: MPV_ZOOM_MIN, max: MPV_ZOOM_MAX };
    return { min: 0.5, max: MPV_ZOOM_MAX };
  }
  function mpvSetZoom(mult) {
    var b = getZoomBounds();
    mPvUserMult = Math.max(b.min, Math.min(b.max, mult));
    saveZoomFor(mPvContentType || 'web', mPvUserMult);
    mpvUpdateZoomChip();
    mpvUpdateZoomButtons();
    applyContentZoom();
    mPvLayoutDevice();
  }
  function mpvResetZoom() {
    mPvUserMult = getDefaultZoom();
    saveZoomFor(mPvContentType || 'web', mPvUserMult);
    mpvUpdateZoomChip();
    mpvUpdateZoomButtons();
    applyContentZoom();
    mPvLayoutDevice();
  }
  function applyContentZoom() {
    if (mPvContentType === 'image') updateImageZoom();
  }
  function wireZoomButtons() {
    var bOut = document.getElementById('mpv-zoom-out');
    var bIn = document.getElementById('mpv-zoom-in');
    var bReset = document.getElementById('mpv-zoom-reset');
    if (bOut) bOut.addEventListener('click', function () { mpvSetZoom(mPvUserMult - MPV_ZOOM_STEP); });
    if (bIn) bIn.addEventListener('click', function () { mpvSetZoom(mPvUserMult + MPV_ZOOM_STEP); });
    if (bReset) bReset.addEventListener('click', function () {
      if (mPvUserMult !== getDefaultZoom()) { mpvResetZoom(); toast('Preview zoom reset'); }
    });
    mpvUpdateZoomButtons();
  }
  function updateZoomVisibility() {
    var zoomWrap = document.querySelector('#mp-preview .mpv-zoom-controls');
    if (zoomWrap) {
      if (mPvDevice === 'phone') zoomWrap.classList.toggle('hidden', !shouldShowZoom());
      else zoomWrap.classList.remove('hidden');
    }
    var bodyEl = document.getElementById('m-preview-body');
    if (bodyEl) bodyEl.classList.toggle('mpv-clean', mPvDevice === 'phone' && shouldShowZoom());
  }

  /* ═══════════════════════════════════════════════════════════════
     GESTURE INJECTION (pinch / double-tap inside the previewed page)
  ═══════════════════════════════════════════════════════════════ */
  var PV_GESTURE_SCRIPT =
    '<script>(function(){var S=null,last=0;' +
    'function ds(e){var a=e.touches[0],b=e.touches[1];return Math.hypot(a.clientX-b.clientX,a.clientY-b.clientY);}' +
    'document.addEventListener("touchstart",function(e){' +
    'if(e.touches.length===2){S=ds(e);}' +
    'else if(e.touches.length===1){var n=Date.now();if(last&&n-last<300){parent.postMessage({quirkyPv:"reset"},"*");last=0;}else{last=n;}}' +
    '}, { passive: true }); ' +
    'document.addEventListener("touchmove",function(e){' +
    'if(e.touches.length===2&&S){e.preventDefault();parent.postMessage({quirkyPv:"pinch",ratio:ds(e)/S},"*");}' +
    '}, { passive: false }); ' +
    'document.addEventListener("touchend",function(e){' +
    'if(e.touches.length<2&&S){S=null;parent.postMessage({quirkyPv:"pinch-end"},"*");}' +
    '}, { passive: true }); })();<\/script>';

  /* v5.1: single physical line — a multi-line '+' chain can lose its tail
     during copy/pack operations, which produced "Unexpected end of input". */
  var PV_NAV_GUARD_JS = '(function(){if(window.__pvNavGuard)return;window.__pvNavGuard=true;function norm(id){return String(id||"").toLowerCase().replace(/-+/g,"-").replace(/^-+|-+$/g,"");}function findTarget(id){var el=document.getElementById(id);if(el)return el;var n=norm(id);if(!n)return null;var cs=document.querySelectorAll("[id]");for(var i=0;i<cs.length;i++){if(norm(cs[i].id)===n)return cs[i];}return null;}document.addEventListener("click",function(e){var a=(e.target&&e.target.closest)?e.target.closest("a"):null;if(!a)return;var h=a.getAttribute("href")||"";if(h.charAt(0)==="#"){e.preventDefault();var el=findTarget(h.slice(1));if(el)el.scrollIntoView({behavior:"smooth",block:"start"});return;}var url=a.href||"";if(!url){e.preventDefault();return;}if(/^(mailto:|tel:|sms:|geo:)/i.test(url))return;var isWs=url.indexOf("workspace=")!==-1;var isPv=url.indexOf("api=preview-render")!==-1;if(isWs||isPv)return;if(/^https?:\\/\\//i.test(url)){if(!a.getAttribute("target"))a.setAttribute("target","_blank");a.setAttribute("rel","noopener");return;}e.preventDefault();e.stopPropagation();},true);})();';

  /* v5.1 integrity check — a truncated guard must NEVER be injected. */
  function mPvGuardOk() {
    return typeof PV_NAV_GUARD_JS === 'string' &&
      PV_NAV_GUARD_JS.length > 200 &&
      PV_NAV_GUARD_JS.indexOf('},true);})();') === PV_NAV_GUARD_JS.length - 13;
  }

  function mPvInjectGestures(html) {
    html = String(html || '');
    var i = html.lastIndexOf('</body>');
    if (i !== -1) return html.slice(0, i) + PV_GESTURE_SCRIPT + html.slice(i);
    return html + PV_GESTURE_SCRIPT;
  }

  /* ═══════════════════════════════════════════════════════════════
     CONSOLE CAPTURE
  ═══════════════════════════════════════════════════════════════ */
  var PV_CONSOLE_SCRIPT =
    '<script>(function(){if(window.__pvConsoleHook)return;window.__pvConsoleHook=true;' +
    'function fmt(a){try{return Array.prototype.map.call(a,function(x){' +
    'if(typeof x==="string")return x;try{return JSON.stringify(x);}catch(e){return String(x);}}).join(" ");}catch(e){return "";}}' +
    'function send(level,text){try{parent.postMessage({quirkyConsole:{level:level,text:String(text).slice(0,500)}},"*");}catch(e){}}' +
    '["log","info","warn","error"].forEach(function(m){var orig=console[m]?console[m].bind(console):function(){};' +
    'console[m]=function(){var lvl=(m==="info")?"log":m;send(lvl,fmt(arguments));orig.apply(null,arguments);};});' +
    'window.addEventListener("error",function(e){send("error",(e.message||"Error")+(e.lineno?(" @line "+e.lineno):""));});' +
    'window.addEventListener("unhandledrejection",function(e){var r=e.reason;send("error","Unhandled rejection: "+((r&&r.message)||r));});' +
    '})();<\/script>';

  function mPvInjectConsole(html) {
    html = String(html || '');
    var i = html.lastIndexOf('</body>');
    if (i !== -1) return html.slice(0, i) + PV_CONSOLE_SCRIPT + html.slice(i);
    return html + PV_CONSOLE_SCRIPT;
  }
  function onConsoleMessage(c) {
    if (!c || typeof c !== 'object') return;
    var level = (c.level === 'warn' || c.level === 'error') ? c.level : 'log';
    mPvConsoleEntries.push({ level: level, text: String(c.text || ''), t: new Date() });
    if (mPvConsoleEntries.length > MPV_CONSOLE_CAP) {
      mPvConsoleEntries.splice(0, mPvConsoleEntries.length - MPV_CONSOLE_CAP);
    }
    mpvUpdateConsoleChip();
    if (mpvIsConsoleOpen()) mpvRenderConsoleList();
  }

  /* ═══════════════════════════════════════════════════════════════
     IMAGE PREVIEW RENDERER (instant, on-device)
  ═══════════════════════════════════════════════════════════════ */
  function renderImagePreview(tab) {
    var imageUrl = 'index.php?workspace=' + encodeURIComponent(tab.path) + '&_t=' + mPvImgBust;
    var zoom = mPvUserMult || MPV_IMG_ZOOM_DEFAULT;
    var html = '<!DOCTYPE html><html><head><meta charset="UTF-8">' +
      '<meta name="viewport" content="width=device-width, initial-scale=1">' +
      '<title>' + mPvEsc(tab.name) + '</title>' +
      '<style>' +
      'html,body{margin:0;padding:0;width:100%;height:100%;overflow:hidden;background:#1a1a2e;}' +
      'body{display:flex;align-items:center;justify-content:center;}' +
      'img{flex:0 0 auto;max-width:none;max-height:none;height:auto;width:auto;' +
      'transition:width 0.15s ease;image-rendering:auto;user-select:none;-webkit-user-drag:none;}' +
      '</style></head><body>' +
      '<img src="' + imageUrl + '" alt="' + mPvEsc(tab.name) + '" id="pv-img" loading="eager">' +
      '<script>var _z=' + zoom + ';var _i=document.getElementById("pv-img");' +
      'function _a(){if(_i.naturalWidth>0){_i.style.width=Math.round(_i.naturalWidth*_z)+"px";}' +
      'else{_i.style.width=Math.round(_z*100)+"%";}}' +
      '_i.addEventListener("load",_a);if(_i.complete)_a();' +
      'window._pvSetImgZoom=function(z){_z=z;_a();};' +
      '<\/script></body></html>';
    mPvPaint(html);
  }
  function updateImageZoom() {
    if (!mPreviewFrame) return;
    try {
      var w = mPreviewFrame.contentWindow;
      if (w && typeof w._pvSetImgZoom === 'function') { w._pvSetImgZoom(mPvUserMult); return; }
      var img = mPreviewFrame.contentDocument.getElementById('pv-img');
      if (img) {
        if (img.naturalWidth > 0) img.style.width = Math.round(img.naturalWidth * mPvUserMult) + 'px';
        else img.style.width = Math.round(mPvUserMult * 100) + '%';
      }
    } catch (e) { /* cross-origin safety */ }
  }

  /* ═══════════════════════════════════════════════════════════════
     UNSUPPORTED FORMAT RENDERER
  ═══════════════════════════════════════════════════════════════ */
  function renderUnsupported(tab) {
    var html = '<!DOCTYPE html><html><head><meta charset="UTF-8">' +
      '<meta name="viewport" content="width=device-width, initial-scale=1">' +
      '<title>Unsupported</title>' +
      '<style>' +
      'html,body{margin:0;padding:0;width:100%;height:100%;' +
      'display:flex;align-items:center;justify-content:center;' +
      'background:#0d1420;color:#7186a5;font-family:system-ui,sans-serif;}' +
      '.box{text-align:center;padding:2rem;}' +
      '.icon{font-size:3rem;margin-bottom:1rem;}' +
      '.title{font-size:1.1rem;font-weight:700;color:#e8eef7;margin-bottom:0.5rem;}' +
      '.sub{font-size:0.85rem;color:#7186a5;}' +
      '</style></head><body>' +
      '<div class="box">' +
      '<div class="icon">🚫</div>' +
      '<div class="title">Unsupported Format</div>' +
      '<div class="sub">.' + mPvEsc(tab.ext) + ' files cannot be previewed.<br>' +
      'This preview supports: HTML, PHP, Markdown, SVG, and images.</div>' +
      '</div></body></html>';
    mPvPaint(html);
  }

  /* ═══════════════════════════════════════════════════════════════
     REAL PHONE VIEWPORT + DEVICE LAYOUT (unchanged from v4)
  ═══════════════════════════════════════════════════════════════ */
  var PHONE_VIEW_W = 390;
  var PHONE_VIEW_H = 844;

  function mPvLayoutDevice() {
    if (!mDeviceShell || !mDeviceStage) return;
    var iframe = mPreviewFrame;
    if (!iframe) return;
    iframe.style.transform = '';
    iframe.style.width = '';
    iframe.style.height = '';
    iframe.style.zoom = '';
    iframe.style.transformOrigin = '';
    iframe.style.transition = 'transform 0.2s ease';
    mDeviceShell.style.width = '';
    mDeviceShell.style.height = '';
    mDeviceShell.style.overflow = '';
    mDeviceShell.style.zoom = '';
    mDeviceShell.style.left = '';
    mDeviceShell.style.top = '';
    mDeviceShell.style.transition = 'zoom 0.2s ease';
    mDeviceStage.style.overflow = '';
    if (mDeviceWrap) mDeviceWrap.style.setProperty('--pv-scale', '1');
    var stageW = mDeviceStage.clientWidth;
    var stageH = mDeviceStage.clientHeight;
    if (stageW <= 0 || stageH <= 0) return;

    if (mPvDevice === 'phone') {
      var baseP = Math.max(0.2, Math.min(stageW / PHONE_VIEW_W, MPV_ZOOM_MAX));
      var isMd = (mPvContentType === 'markdown');
      var mMult = isMd ? mPvUserMult : 1;
      var scaleP = Math.max(0.2, Math.min(baseP * mMult, MPV_ZOOM_MAX));
      var layoutW = isMd ? Math.round(PHONE_VIEW_W / mMult) : PHONE_VIEW_W;
      var viewH = Math.max(240, Math.ceil(stageH / scaleP) + 2);
      iframe.style.transition = 'none';
      iframe.style.transform = '';
      iframe.style.transformOrigin = '';
      iframe.style.width = layoutW + 'px';
      iframe.style.height = viewH + 'px';
      iframe.style.zoom = '';
      mDeviceShell.style.width = layoutW + 'px';
      mDeviceShell.style.height = viewH + 'px';
      mDeviceShell.style.left = '0px';
      mDeviceShell.style.top = '0px';
      mDeviceShell.style.overflow = 'hidden';
      mDeviceShell.style.zoom = scaleP;
      mDeviceStage.style.overflow = 'hidden';
      schedulePhoneFitCheck();
      return;
    }
    if (mPvDevice === 'desktop') {
      var iframeW = 1024, iframeH = 768;
      var chrome = 22;
      var csD = window.getComputedStyle(mDeviceStage);
      var availW = stageW - (parseFloat(csD.paddingLeft) || 0) - (parseFloat(csD.paddingRight) || 0);
      var availH = stageH - (parseFloat(csD.paddingTop) || 0) - (parseFloat(csD.paddingBottom) || 0);
      var fit = Math.min((availW - chrome) / iframeW, (availH - chrome) / iframeH, 1);
      var scale = Math.min(fit * mPvUserMult, MPV_ZOOM_MAX);
      if (mDeviceWrap) mDeviceWrap.style.setProperty('--pv-scale', String(scale));
      if (mPvUserMult === 1 && scale >= 0.999) return;
      iframe.style.transform = 'scale(' + scale + ')';
      iframe.style.transformOrigin = 'top left';
      mDeviceShell.style.width = Math.round(iframeW * scale + chrome) + 'px';
      mDeviceShell.style.height = Math.round(iframeH * scale + chrome) + 'px';
      mDeviceShell.style.overflow = 'hidden';
    } else {
      var natW = 768 + 30;
      var natH = 1024 + 30 + 20 + 22;
      var cs = window.getComputedStyle(mDeviceStage);
      var availW2 = stageW - (parseFloat(cs.paddingLeft) || 0) - (parseFloat(cs.paddingRight) || 0);
      var availH2 = stageH - (parseFloat(cs.paddingTop) || 0) - (parseFloat(cs.paddingBottom) || 0);
      var fitT = Math.min(availW2 / natW, availH2 / natH, 1);
      var z = Math.min(fitT * mPvUserMult, MPV_ZOOM_MAX);
      if (mPvUserMult === 1 && z >= 0.999) return;
      mDeviceShell.style.zoom = z;
    }
  }
  function centerStage() {
    if (!mDeviceStage) return;
    mDeviceStage.scrollLeft = Math.max(0, (mDeviceStage.scrollWidth - mDeviceStage.clientWidth) / 2);
    mDeviceStage.scrollTop = Math.max(0, (mDeviceStage.scrollHeight - mDeviceStage.clientHeight) / 2);
  }
  var _phoneFitTimer = null;
  function schedulePhoneFitCheck() {
    if (_phoneFitTimer) clearTimeout(_phoneFitTimer);
    var attempts = 0;
    function check() {
      _phoneFitTimer = null;
      if (mPvDevice !== 'phone' || !mPreviewFrame || !mDeviceShell || !mDeviceStage) return;
      var stageH = mDeviceStage.clientHeight;
      var shellRect = mDeviceShell.getBoundingClientRect();
      if (stageH > 0 && shellRect.height > 0 && shellRect.height < stageH - 1) {
        var zoom = parseFloat(mDeviceShell.style.zoom) || 1;
        var missing = Math.ceil((stageH - shellRect.height) / zoom) + 1;
        var curShell = parseInt(mDeviceShell.style.height, 10) || 0;
        var curFrame = parseInt(mPreviewFrame.style.height, 10) || curShell;
        mDeviceShell.style.height = (curShell + missing) + 'px';
        mPreviewFrame.style.height = (curFrame + missing) + 'px';
      }
      attempts++;
      if (attempts < 3) _phoneFitTimer = setTimeout(check, 120);
    }
    _phoneFitTimer = setTimeout(check, 60);
  }
  function onPvMessage(e) {
    var d = e.data;
    if (!d || typeof d !== 'object') return;
    if (d.quirkyConsole) { onConsoleMessage(d.quirkyConsole); return; }
    if (!d.quirkyPv) return;
    if (mPvDevice === 'phone') return;
    if (d.quirkyPv === 'pinch') {
      if (mPvPinchBase == null) mPvPinchBase = mPvUserMult;
      var pb = getZoomBounds();
      var newMult = Math.max(pb.min, Math.min(pb.max, mPvPinchBase * (d.ratio || 1)));
      mPvUserMult = newMult;
      mpvUpdateZoomChip();
      mpvUpdateZoomButtons();
      mPvLayoutDevice();
    } else if (d.quirkyPv === 'pinch-end') {
      mPvPinchBase = null;
    } else if (d.quirkyPv === 'reset') {
      mpvResetZoom();
      toast('Preview zoom reset');
    }
  }
  function mPvUpdateSizeLabel() {
    if (!mPvSizeLbl) return;
    if (mPvDevice === 'tablet') mPvSizeLbl.textContent = '768 × 1024';
    else if (mPvDevice === 'desktop') mPvSizeLbl.textContent = '1024 × 768';
    else mPvSizeLbl.textContent = PHONE_VIEW_W + ' × ' + PHONE_VIEW_H;
  }

  /* ═══════════════════════════════════════════════════════════════
     ★ v5 BACKGROUND PROJECT / APACHE CHECK
     Never blocks a render. Fetches workshop-status at most once per
     60 s (or when explicitly marked stale), then re-points the frame
     to Apache ONLY when Apache is genuinely running for this file.
  ═══════════════════════════════════════════════════════════════ */
  function mPvBackgroundProjectRefresh(force) {
    if (mPvProjFetching) return;
    var now = Date.now();
    if (!force && mPvProjects !== null && (now - mPvProjectsLoadedAt) < 60000) {
      mPvMaybeRepointApache();
      return;
    }
    mPvProjFetching = true;
    fetch('index.php?api=workshop-status', { headers: { 'Accept': 'application/json' } })
      .then(function (res) { return res.json(); })
      .then(function (j) {
        mPvProjFetching = false;
        mPvProjectsLoadedAt = Date.now();
        if (j && j.ok && j.data) {
          mPvProjects = j.data.projects || [];
          var extensions = j.data.extensions || [];
          mPvApacheRunning = false;
          for (var i = 0; i < extensions.length; i++) {
            if (extensions[i].id === 'apache') {
              mPvApacheRunning = extensions[i].running === true;
              mPvApachePort = extensions[i].activePort || 8082;
              break;
            }
          }
        } else {
          mPvProjects = [];
          mPvApacheRunning = false;
        }
        mPvMaybeRepointApache();
      })
      .catch(function () {
        mPvProjFetching = false;
        mPvProjectsLoadedAt = Date.now();
        if (mPvProjects === null) mPvProjects = [];
        mPvApacheRunning = false;
        mPvMaybeRepointApache();
      });
  }

  /** Check if a file should be served by Apache (cheap, in-memory). */
  function mPvGetApacheUrl(filePath) {
    if (!mPvApacheRunning || !mPvProjects || mPvProjects.length === 0) return null;
    var ext = filePath.split('.').pop().toLowerCase();
    var skipExts = ['md', 'markdown', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'bmp', 'ico', 'avif', 'svg'];
    if (skipExts.indexOf(ext) !== -1) return null;
    for (var i = 0; i < mPvProjects.length; i++) {
      var projPath = mPvProjects[i].path;
      if (filePath === projPath || filePath.indexOf(projPath + '/') === 0) {
        return {
          url: 'http://127.0.0.1:' + mPvApachePort + '/' + filePath,
          projectName: mPvProjects[i].name || 'Apache'
        };
      }
    }
    return null;
  }

  /** Re-point (or fall back) once background data arrives. */
  function mPvMaybeRepointApache() {
    if (!mPreviewPanel || mPreviewPanel.classList.contains('hidden')) return;
    var tab = (window.IDE && window.IDE.mobileEditor) ? window.IDE.mobileEditor.getActiveTab() : null;
    if (!tab) return;
    if (!mPvApacheRunning) {
      if (mPvApacheServing) {           // Apache stopped under us → recover
        mPvHideApacheBadge();
        mPvLastServerKey = '';
        mobilePreviewRender();
      }
      return;
    }
    var info = mPvGetApacheUrl(tab.path);
    if (!info) return;
    if (mPvApacheServing && mPvApacheRepointed === tab.path) return; // already there
    mPvApacheRepointed = tab.path;
    mPvPaintApache(info.url, info.projectName);
  }

  /* ═══════════════════════════════════════════════════════════════
     FRAME PAINTERS
  ═══════════════════════════════════════════════════════════════ */
  function mPvPaintApache(url, projectName) {
    if (!mPreviewFrame) return;
    mPvShowFrame();
    mPvHideErrorStrip();
    mPreviewFrame.removeAttribute('srcdoc');
    var separator = url.indexOf('?') !== -1 ? '&' : '?';
    mPreviewFrame.src = url + separator + '_t=' + Date.now();
    mPvArmScrollRestore();
    mPvShowServerBadge('🌐 ' + (projectName || 'Apache'));
    mPvApacheServing = true;
    mPvServerServing = false;
    mPvSetContentType('web');
    updateZoomVisibility();
    mPvHasRendered = true;
    mPvLastPaintAt = Date.now();
  }

  function mPvPaintServer(url, port, dedupeKey) {
    if (!mPreviewFrame || !url) return;
    var key = dedupeKey || url;
    /* Identical URL + identical content (e.g. tab switch) → skip reload. */
    if (key === mPvLastServerKey) return;
    mPvLastServerKey = key;
    mPvShowFrame();
    mPvHideErrorStrip();
    mPreviewFrame.removeAttribute('srcdoc');
    var separator = url.indexOf('?') !== -1 ? '&' : '?';
    mPreviewFrame.src = url + separator + '_t=' + Date.now();
    mPvArmScrollRestore();
    mPvServerPort = port || 0;
    mPvShowServerBadge('Built-in server :' + (port || '?'));
    mPvServerServing = true;
    mPvApacheServing = false;
    mPvSetContentType('web');
    updateZoomVisibility();
    mPvHasRendered = true;
    mPvLastPaintAt = Date.now();
  }

  function mPvShowServerBadge(label) {
    var badge = document.getElementById('mpv-server-badge');
    if (badge) { badge.textContent = label; badge.classList.remove('hidden'); }
  }
  function mPvHideApacheBadge() {
    var badge = document.getElementById('mpv-server-badge');
    if (badge) badge.classList.add('hidden');
    mPvApacheServing = false;
    mPvServerServing = false;
    mPvServerPort = 0;
    mPvLastServerKey = '';
    mPvApacheRepointed = '';
  }

  /* ═══════════════════════════════════════════════════════════════
     SCROLL PRESERVATION
  ═══════════════════════════════════════════════════════════════ */
  function activeTabPath() {
    var t = (window.IDE && window.IDE.mobileEditor) ? window.IDE.mobileEditor.getActiveTab() : null;
    return t ? t.path : '';
  }
  function startScrollTracker() {
    setInterval(function () {
      if (!mPreviewFrame || !mPreviewPanel || mPreviewPanel.classList.contains('hidden')) return;
      var path = activeTabPath();
      if (!path) return;
      try {
        var w = mPreviewFrame.contentWindow;
        if (!w) return;
        var y = w.scrollY || w.pageYOffset || 0;
        if (y > 0) mPvScrollMap[path] = y;
      } catch (e) { /* cross-origin frame — ignore */ }
    }, 500);
  }
  function mPvArmScrollRestore() {
    if (!mPreviewFrame) return;
    var path = activeTabPath();
    if (!path) return;
    var handler = function () {
      mPreviewFrame.removeEventListener('load', handler);
      setTimeout(function () {
        try {
          var y = mPvScrollMap[path] || 0;
          if (y > 0 && mPreviewFrame.contentWindow) mPreviewFrame.contentWindow.scrollTo(0, y);
        } catch (e) { /* cross-origin */ }
      }, 80);
    };
    mPreviewFrame.addEventListener('load', handler);
  }

  /* ═══════════════════════════════════════════════════════════════
     TRUTHFUL ERROR STRIP
  ═══════════════════════════════════════════════════════════════ */
  function mPvShowErrorStrip(o) {
    o = o || {};
    mPvHideErrorStrip();
    var host = document.getElementById('mp-preview');
    if (!host) return;
    var el = document.createElement('div');
    el.id = 'mpv-error-strip';
    el.className = 'mpv-error-strip';
    var title = o.title || 'Preview failed';
    var html = '<div class="mpv-err-head">' +
      '<span class="mpv-err-title">' + mPvEsc(title) + '</span>' +
      '<span class="mpv-err-actions">' +
      '<button type="button" class="mpv-err-retry">Retry</button>' +
      '<button type="button" class="mpv-err-close" aria-label="Dismiss">&times;</button>' +
      '</span></div>';
    var detail = String(o.detail || '').slice(0, 200);
    if (detail) html += '<pre class="mpv-err-detail">' + mPvEsc(detail) + '</pre>';
    el.innerHTML = html;
    el.querySelector('.mpv-err-close').addEventListener('click', mPvHideErrorStrip);
    el.querySelector('.mpv-err-retry').addEventListener('click', function () {
      mPvHideErrorStrip();
      mPvLastServerKey = '';   // ★ v5.4: Retry = explicit forced reload
      var tab = (window.IDE && window.IDE.mobileEditor) ? window.IDE.mobileEditor.getActiveTab() : null; if (tab && (tab.ext === 'php' || tab.ext === 'phtml')) {
        if (!mPvHasRendered) showPreviewSkeleton();
        mPvRunPhp(tab);
      } else {
        mobilePreviewRender();
      }
    });
    host.appendChild(el);
  }
  function mPvHideErrorStrip() {
    var old = document.getElementById('mpv-error-strip');
    if (old && old.parentNode) old.parentNode.removeChild(old);
  }

  /* ═══════════════════════════════════════════════════════════════
     DEVICE SWITCHING
  ═══════════════════════════════════════════════════════════════ */
  function applyMobilePreviewDevice(mode) {
    if (mode !== 'tablet' && mode !== 'phone') mode = 'desktop';
    mPvDevice = mode;
    if (mDeviceShell) mDeviceShell.setAttribute('data-device', mode);
    if (mDeviceWrap) mDeviceWrap.setAttribute('data-device', mode);
    if (mDeviceStage) mDeviceStage.classList.toggle('framed', mode !== 'phone');
    document.querySelectorAll('#mp-preview .pv-device').forEach(function (b) {
      var on = b.getAttribute('data-device') === mode;
      b.classList.toggle('active', on);
      b.setAttribute('aria-pressed', on ? 'true' : 'false');
    });
    try { localStorage.setItem('quirky.ide.preview.device', mode); } catch (e) { }
    mPvUpdateSizeLabel();
    updateZoomVisibility();
    mpvResetZoom();
    mobilePreviewRender();
    requestAnimationFrame(function () { mPvLayoutDevice(); centerStage(); });
    if (mode === 'phone') {
      setTimeout(mPvLayoutDevice, 100);
      setTimeout(mPvLayoutDevice, 350);
    }
  }
  function wireDeviceButtons() {
    document.querySelectorAll('#mp-preview .pv-device').forEach(function (b) {
      b.addEventListener('click', function () { applyMobilePreviewDevice(b.getAttribute('data-device')); });
    });
  }

  /* ═══════════════════════════════════════════════════════════════
     REFRESH MODE (Live / Save)
  ═══════════════════════════════════════════════════════════════ */
  function getRefreshMode() {
    try { return localStorage.getItem(REFRESH_MODE_KEY) === 'save' ? 'save' : 'live'; } catch (e) { return 'live'; }
  }
  function setRefreshMode(mode) {
    try { localStorage.setItem(REFRESH_MODE_KEY, mode === 'save' ? 'save' : 'live'); } catch (e) { }
    mpvUpdateRefreshChip();
  }
  function mpvUpdateRefreshChip() {
    var chip = document.getElementById('mpv-refresh-mode');
    if (!chip) return;
    var mode = getRefreshMode();
    chip.textContent = mode === 'save' ? 'SAVE' : 'LIVE';
    chip.setAttribute('title', mode === 'save'
      ? 'Preview refreshes when you save — tap for live keystroke preview'
      : 'Preview refreshes on every keystroke — tap to refresh on save only');
    chip.classList.toggle('mpv-mode-save', mode === 'save');
  }

  /* ═══════════════════════════════════════════════════════════════
     PANE FORM BRIDGE
  ═══════════════════════════════════════════════════════════════ */
  function mPvInjectForms(html, path) {
    html = String(html || '');
    if (!/<form\b/i.test(html)) return html;
    var safePath = String(path || '')
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    return html.replace(/<form\b([^>]*)>/gi, function (m, attrs) {
      return '<form' + attrs + '>' +
        '<input type="hidden" name="api" value="preview-render">' +
        '<input type="hidden" name="raw" value="1">' +
        '<input type="hidden" name="bare" value="1">' +
        '<input type="hidden" name="path" value="' + safePath + '">';
    });
  }

  /* ═══════════════════════════════════════════════════════════════
     CONSOLE DRAWER UI
  ═══════════════════════════════════════════════════════════════ */
  function mpvConsoleCounts() {
    var c = { log: 0, warn: 0, error: 0 };
    for (var i = 0; i < mPvConsoleEntries.length; i++) c[mPvConsoleEntries[i].level]++;
    return c;
  }
  function mpvUpdateConsoleChip() {
    var chip = document.getElementById('mpv-console-toggle');
    if (!chip) return;
    var counts = mpvConsoleCounts();
    var total = mPvConsoleEntries.length;
    chip.classList.toggle('mpv-console-has-error', counts.error > 0);
    chip.classList.toggle('mpv-console-has-warn', counts.warn > 0 && counts.error === 0);
    var label = chip.querySelector('.mpv-console-count');
    if (label) label.textContent = String(total);
    chip.classList.toggle('hidden', total === 0);
  }
  function mpvIsConsoleOpen() {
    var d = document.getElementById('mpv-console-drawer');
    return !!(d && !d.classList.contains('hidden'));
  }
  function mpvRenderConsoleList() {
    var list = document.getElementById('mpv-console-list');
    if (!list) return;
    var rows = [];
    for (var i = 0; i < mPvConsoleEntries.length; i++) {
      var e = mPvConsoleEntries[i];
      rows.push('<div class="mpv-console-row mpv-console-' + e.level + '">' +
        '<span class="mpv-console-lvl">' + e.level.toUpperCase() + '</span>' +
        '<span class="mpv-console-text">' + mPvEsc(e.text) + '</span></div>');
    }
    list.innerHTML = rows.join('') || '<div class="mpv-console-row"><span class="mpv-console-text">No console output.</span></div>';
    list.scrollTop = list.scrollHeight;
  }
  function mpvToggleConsoleDrawer(forceOpen) {
    var host = document.getElementById('mp-preview');
    var drawer = document.getElementById('mpv-console-drawer');
    if (!host || !drawer) return;
    var open = (typeof forceOpen === 'boolean') ? forceOpen : drawer.classList.contains('hidden');
    drawer.classList.toggle('hidden', !open);
    if (open) mpvRenderConsoleList();
  }
  function mpvClearConsole() {
    mPvConsoleEntries.length = 0;
    mpvUpdateConsoleChip();
    mpvRenderConsoleList();
  }
  function ensurePreviewBarExtras() {
    var bar = document.querySelector('#mp-preview .m-preview-bar');
    if (!bar) return;
    if (!document.getElementById('mpv-console-toggle')) {
      var chip = document.createElement('button');
      chip.type = 'button';
      chip.id = 'mpv-console-toggle';
      chip.className = 'mpv-console-chip hidden';
      chip.setAttribute('aria-label', 'Toggle preview console');
      chip.innerHTML = '<span class="mpv-console-dot"></span><span class="mpv-console-count">0</span>';
      chip.addEventListener('click', function () { mpvToggleConsoleDrawer(); });
      var zoomWrap = bar.querySelector('.mpv-zoom-controls');
      if (zoomWrap && zoomWrap.parentNode === bar) bar.insertBefore(chip, zoomWrap);
      else bar.appendChild(chip);
    }
    if (!document.getElementById('mpv-refresh-mode')) {
      var mode = document.createElement('button');
      mode.type = 'button';
      mode.id = 'mpv-refresh-mode';
      mode.className = 'mpv-mode-chip';
      mode.addEventListener('click', function () {
        var next = getRefreshMode() === 'live' ? 'save' : 'live';
        setRefreshMode(next);
        toast(next === 'live' ? 'Live preview: refreshes as you type' : 'Save mode: preview refreshes on save');
        scheduleMobilePreview();
      });
      var zoomWrap2 = bar.querySelector('.mpv-zoom-controls');
      if (zoomWrap2 && zoomWrap2.parentNode === bar) bar.insertBefore(mode, zoomWrap2);
      else bar.appendChild(mode);
      mpvUpdateRefreshChip();
    }
    var host = document.getElementById('mp-preview');
    if (host && !document.getElementById('mpv-console-drawer')) {
      var drawer = document.createElement('div');
      drawer.id = 'mpv-console-drawer';
      drawer.className = 'mpv-console-drawer hidden';
      drawer.innerHTML =
        '<div class="mpv-console-head">' +
        '<span class="mpv-console-title">Console</span>' +
        '<button type="button" class="mpv-console-clear">Clear</button>' +
        '<button type="button" class="mpv-console-close" aria-label="Close console">&times;</button>' +
        '</div>' +
        '<div id="mpv-console-list" class="mpv-console-list"></div>';
      host.appendChild(drawer);
      drawer.querySelector('.mpv-console-clear').addEventListener('click', mpvClearConsole);
      drawer.querySelector('.mpv-console-close').addEventListener('click', function () { mpvToggleConsoleDrawer(false); });
    }
    /* v5: thin updating line (lives over the panel body) */
    if (host && !document.getElementById('mpv-updating-bar')) {
      var ub = document.createElement('div');
      ub.id = 'mpv-updating-bar';
      ub.className = 'mpv-updating-bar hidden';
      host.appendChild(ub);
    }
  }

  /* ═══════════════════════════════════════════════════════════════
     ★ v5 RENDER PIPELINE — paint-first, never blocks on the network
  ═══════════════════════════════════════════════════════════════ */
  function mPvShowEmpty(msg) {
    if (mDeviceStage) mDeviceStage.style.display = 'none';
    if (mPreviewEmpty) {
      mPreviewEmpty.classList.remove('hidden');
      if (msg) mPreviewEmpty.querySelector('.mpp-text').innerHTML = msg;
    }
  }
  function mPvShowFrame() {
    if (mDeviceStage) mDeviceStage.style.display = '';
    if (mPreviewEmpty) mPreviewEmpty.classList.add('hidden');
  }
  function injectLazyImages(html) {
    return String(html || '').replace(/<img\b(?![^>]*\bloading\s*=)/gi, '<img loading="lazy"');
  }
  /* ★ v5.4 real-browser illusion: hide the WebView's fat default scrollbar
  inside the painted page — but ONLY when the page does not style its own
  scrollbar. A file that defines its own keeps it (the file wins, exactly
  like a real browser tab). Injected at the START of <head> so any later
  page stylesheet can still override us. */
  /* True for one selector chunk like "html", "body", "*", ":root" or ""
     (bare pseudo-element = global rule). ".rail" etc. returns false. */
  function mPvSelTargetsRoot(sel) {
    var parts = String(sel || '').split(',');
    for (var i = 0; i < parts.length; i++) {
      var p = parts[i].replace(/::-webkit-scrollbar.*$/i, '').trim();
      if (p === '') return true;
      if (/(^|[\s>+~,])(html|body|\*|:root)$/.test(p)) return true;
    }
    return false;
  }
  /* ★ v5.5: true only when the page styles the DOCUMENT scrollbar.
     Scoped rules like .rail::-webkit-scrollbar must NOT disable the
     phone emulation (HAKKO bug: its rail rule re-enabled the main bar). */
  function mPvPageStylesRootScrollbar(html) {
    html = String(html || '');
    var m, re1 = /[^{}]*::-webkit-scrollbar/gi;
    while ((m = re1.exec(html))) { if (mPvSelTargetsRoot(m[0])) return true; }
    var re2 = /[^{}]*\{[^}]*scrollbar-(?:width|color)\s*:/gi;
    while ((m = re2.exec(html))) {
      if (mPvSelTargetsRoot(m[0].slice(0, m[0].lastIndexOf('{')))) return true;
    }
    return false;
  }
  function mPvInjectScrollbarNorm(html) {
    html = String(html || '');
    if (mPvPageStylesRootScrollbar(html)) return html; var style = '<style data-quirky-pv="sb">html,body{scrollbar-width:none;-ms-overflow-style:none}' +
      'html::-webkit-scrollbar,body::-webkit-scrollbar{width:0;height:0;display:none}</style>';
    var m = html.match(/<head\b[^>]*>/i);
    if (m) {
      var at = m.index + m[0].length;
      return html.slice(0, at) + style + html.slice(at);
    }
    var i = html.lastIndexOf('</head>');
    if (i !== -1) return html.slice(0, i) + style + html.slice(i);
    return style + html;
  }
  function mPvPaint(html) {
    if (!mPreviewFrame) return;
    mPvShowFrame();
    html = injectLazyImages(html);
    html = mPvInjectScrollbarNorm(html);   // ★ v5.4
    /* v5.1: bake the nav guard into the HTML itself (only when intact),
       so srcdoc frames are protected instantly and never depend on the
       post-load appendChild injection that crashed. */
    if (mPvGuardOk()) {
      html += '<script>' + PV_NAV_GUARD_JS + '<\/script>';
    }
    /* v4: gestures stay phone-only; console forwarder is always injected. */
    var painted = (mPvDevice === 'phone') ? html : mPvInjectGestures(html);
    /* ★ v5.2: clear any previous micro-server / Apache src so the frame
       cannot re-navigate to a stale cross-origin URL after we paint. */
    mPreviewFrame.removeAttribute('src');
    mPreviewFrame.srcdoc = mPvInjectConsole(painted);
    mPvArmScrollRestore();
    mPvHasRendered = true;
    mPvLastPaintAt = Date.now();
  }
  function mPvRewrite(html, path) {
    var PU = window.IDE.previewUrls;
    return (PU && PU.rewriteWorkspaceUrls) ? PU.rewriteWorkspaceUrls(html, path) : html;
  }
  function mPvPhpBanner(err) {
    return '<div style="font:13px/1.55 ui-monospace,monospace;color:#fca5a5;background:#3b1414;' +
      'border:1px solid #7f1d1d;border-radius:8px;padding:10px 12px;margin:8px;white-space:pre-wrap;">' +
      '⚠ PHP preview error: ' + mPvEsc(err) + '</div>';
  }

  /**
  * ★ v5 main entry. Paints IMMEDIATELY for every content type:
  *   unsupported → card · image → instant · markdown → instant
  *   html/svg    → instant srcdoc · php → coalesced async request
  * The Apache question is resolved in the background afterwards.
  */
  function mobilePreviewRender() {
    /* ★ v5.4: never paint into a hidden panel. Boot and tab-switch both
    call render; without this guard the same preview loads once while
    hidden and again when shown (visible double reload). onTabShown()
    performs the first visible paint. */
    if (mPreviewPanel && mPreviewPanel.classList.contains('hidden')) return;
    var cfg = window.IDE_CONFIG || {};
    if (!(cfg.features && cfg.features.preview)) {
      mPvSetContentType('web');
      mPvShowEmpty('Preview is currently turned off.<br>You can turn it on in Settings.');
      if (mPreviewFile) mPreviewFile.textContent = '—';
      updateZoomVisibility();
      return;
    }
    var tab = (window.IDE && window.IDE.mobileEditor) ? window.IDE.mobileEditor.getActiveTab() : null;
    var previewable = cfg.previewable || ['html', 'htm', 'php', 'svg', 'md', 'markdown'];
    if (!tab) {
      mPvSetContentType('web');
      mPvShowEmpty('Open a file in the Editor first —<br>the preview follows your active tab.');
      if (mPreviewFile) mPreviewFile.textContent = '—';
      mPvHideApacheBadge();
      updateZoomVisibility();
      return;
    }
    if (mPreviewFile) mPreviewFile.textContent = tab.name;

    var type = getContentType(tab.ext);
    mPvSetContentType(type);
    updateZoomVisibility();

    /* Unsupported media → friendly card, no network. */
    if (type === 'unsupported') {
      mPvHideApacheBadge();
      renderUnsupported(tab);
      return;
    }
    /* Not previewable at all (e.g. .css/.js source) → hint. */
    if (previewable.indexOf(tab.ext) === -1 && type !== 'image') {
      mPvSetContentType('web');
      mPvShowEmpty('Live preview works for<br>.html, .php, .svg, .md and image files.');
      if (mPreviewFile) mPreviewFile.textContent = '—';
      updateZoomVisibility();
      return;
    }

    /* Built-in paint first — Apache may re-point later in background. */
    mPvHideApacheBadge();
    var content = tab.content || '';
    if (type === 'image') {
      renderImagePreview(tab);
    } else if (tab.ext === 'md' || tab.ext === 'markdown') {
      var md = window.IDE && window.IDE.markdown;
      if (!md || !md.toHtml) { mPvShowEmpty('Markdown renderer not loaded.'); return; }
      var title = md.deriveTitle ? md.deriveTitle(content, tab.name) : tab.name;
      mPvPaint(mPvRewrite(md.wrap(md.toHtml(content), title), tab.path));
    } else if (tab.ext === 'php' || tab.ext === 'phtml') {
      mPvRunPhp(tab);
    } else {
      mPvPaint(mPvRewrite(mPvInjectForms(content, tab.path), tab.path));
    }

    /* Background Apache re-check — never blocks the paint above. */
    mPvBackgroundProjectRefresh(false);
  }

  /* ═══════════════════════════════════════════════════════════════
     ★ v5 PHP LIVE RENDER — one in flight, one queued, abortable
  ═══════════════════════════════════════════════════════════════ */
  function mPvSchedulePhp(tab) {
    clearTimeout(mPvPhpTimer);
    /* ★ v5.2: live=true tells the server to skip micro-server spawn
       so typing never waits on a process boot. */
    mPvPhpTimer = setTimeout(function () { mPvRunPhp(tab, true); }, 350);
  }

  function mPvRunPhp(tab, isLive) {
    if (!tab) return;
    /* Coalesce: a request is already running → queue ONE follow-up. */
    if (mPvPhpInFlight) { mPvPhpQueued = true; return; }
    mPvPhpInFlight = true;
    mPvLastPaintAt = Date.now();   /* ★ v5.3: blank frame is EXPECTED while fetching — watchdogs must not re-render */
    var seq = ++mPvSeq;
    var contentSent = tab.content || '';
    var pathSent = tab.path;
    /* First-ever paint → full skeleton. After that → thin progress line
       and the last good render stays visible underneath. */
    if (!mPvHasRendered) showPreviewSkeleton();
    else showUpdatingBar();
    var abortCtl = null;
    var abortTimer = null;
    if (typeof window.AbortController === 'function') {
      abortCtl = new window.AbortController();
      abortTimer = setTimeout(function () { try { abortCtl.abort(); } catch (e) { } }, 15000);
    }
    mPvPhpAbort = abortCtl;
    fetch('index.php?api=preview-render', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      /* ★ v5.2: live flag → server skips micro-server spawn for keystrokes */
      body: JSON.stringify({ path: pathSent, content: contentSent, live: !!isLive }),
      signal: abortCtl ? abortCtl.signal : undefined
    })
      .then(function (r) {
        return r.text().then(function (text) {
          var j = null;
          try { j = JSON.parse(text); } catch (e) { j = null; }
          if (!r.ok) {
            throw {
              pvTitle: 'Preview failed — HTTP ' + r.status,
              pvDetail: j ? ((j && j.error) || '') : String(text || '').slice(0, 200)
            };
          }
          if (!j) {
            throw { pvTitle: 'Preview failed — HTTP ' + r.status, pvDetail: String(text || '').slice(0, 200) };
          }
          if (j.ok === false) {
            throw { pvTitle: '⚠ PHP preview failed', pvDetail: String(j.error || '') };
          }
          return j.data;
        });
      })
      .then(function (d) {
        if (seq !== mPvSeq) return;
        var active = (window.IDE && window.IDE.mobileEditor) ? window.IDE.mobileEditor.getActiveTab() : null;
        if (!active || active.path !== pathSent) return;
        /* ★ v5.4: do NOT clear mPvLastServerKey here. mPvPaintServer()
        compares url+content-hash, so a second render of the SAME content
        (boot+tab-show overlap, tab switches) becomes a no-op instead of
        a second reload. Real edits change the hash → reload happens.
        Refresh / Retry clear the key when a forced reload is wanted. */
        if (d && d.mode === 'apache' && d.url) {
          mPvApacheRepointed = pathSent;
          mPvPaintApache(d.url, d.projectName);
          return;
        }
        if (d && d.mode === 'server' && d.url) {
          /* Dedupe key = url + content hash: tab switches are no-ops,
             real edits (incl. shadow renders) always reload. */
          var key = 'srv|' + d.url + '|' + mPvQuickHash(contentSent);
          mPvPaintServer(d.url, d.port, key);
          return;
        }
        mPvPaint(mPvRewrite(
          mPvInjectForms((d && d.error ? mPvPhpBanner(d.error) : '') + ((d && d.html) || ''), pathSent),
          pathSent
        ));
      })
      .catch(function (err) {
        if (seq !== mPvSeq) return;
        var aborted = err && (err.name === 'AbortError');
        if (aborted) {
          mPvShowErrorStrip({ title: '⚠ Preview timed out', detail: 'The render took too long. Last good preview kept.' });
        } else if (err && err.pvTitle) {
          mPvShowErrorStrip({ title: err.pvTitle, detail: err.pvDetail });
        } else {
          mPvShowErrorStrip({ title: '⚠ Preview request failed', detail: (err && err.message) || 'Network error' });
        }
      })
      .then(function () {
        /* finally — works without Promise.finally for older WebViews */
        if (abortTimer) clearTimeout(abortTimer);
        if (mPvPhpAbort === abortCtl) mPvPhpAbort = null;
        hidePreviewSkeleton();
        hideUpdatingBar();
        mPvPhpInFlight = false;
        if (mPvPhpQueued) {
          mPvPhpQueued = false;
          var fresh = (window.IDE && window.IDE.mobileEditor) ? window.IDE.mobileEditor.getActiveTab() : null;
          if (fresh && (fresh.ext === 'php' || fresh.ext === 'phtml')) mPvRunPhp(fresh);
        }
      });
  }

  /* ═══════════════════════════════════════════════════════════════
     ★ v5 KEYSTROKE SCHEDULER (Live watch)
  ═══════════════════════════════════════════════════════════════ */
  function scheduleMobilePreview() {
    if (!mPreviewPanel || mPreviewPanel.classList.contains('hidden')) return;
    var tab = (window.IDE && window.IDE.mobileEditor) ? window.IDE.mobileEditor.getActiveTab() : null;
    if (!tab) return;
    /* SAVE mode: keystrokes do nothing; editor:saved drives renders. */
    if (getRefreshMode() === 'save') return;
    /* Apache reads the real disk — live keystrokes would show stale
       content, so Apache previews refresh on save only. */
    if (mPvApacheServing) return;
    if (tab.ext === 'php' || tab.ext === 'phtml') {
      mPvSchedulePhp(tab);
      return;
    }
    clearTimeout(mPvHtmlTimer);
    mPvHtmlTimer = setTimeout(mobilePreviewRender, 120);
  }

  /* ═══════════════════════════════════════════════════════════════
     TAB-SHOWN HOOK
  ═══════════════════════════════════════════════════════════════ */
  function onTabShown() {
    var tab = (window.IDE && window.IDE.mobileEditor) ? window.IDE.mobileEditor.getActiveTab() : null;
    if (tab) {
      mPvSetContentType(getContentType(tab.ext));
      var savedZoom = storedZoomFor(mPvContentType);
      mPvUserMult = savedZoom || getDefaultZoom();
    } else {
      mPvUserMult = getDefaultZoom();
    }
    mpvUpdateZoomChip();
    mpvUpdateZoomButtons();
    mobilePreviewRender();
    requestAnimationFrame(mPvLayoutDevice);
    setTimeout(mPvLayoutDevice, 100);
    setTimeout(mPvLayoutDevice, 350);
  }

  /* ═══════════════════════════════════════════════════════════════
     REFRESH + OPEN-IN-TAB BUTTONS
  ═══════════════════════════════════════════════════════════════ */
  function pvToast(msg) { if (U && U.toast) U.toast(msg); }
  function wireActionButtons() {
    var refreshBtn = document.getElementById('mpv-refresh');
    var openBtn = document.getElementById('mpv-open');
    if (refreshBtn) {
      refreshBtn.addEventListener('click', function () {
        mPvHideErrorStrip();
        mPvLastServerKey = '';   // force a real reload of a server-mode preview
        mPvImgBust++;            // force image revalidation
        mPvBackgroundProjectRefresh(true);
        mobilePreviewRender();
        pvToast('🔄 Preview refreshed');
      });
    }
    if (openBtn) {
      openBtn.addEventListener('click', function () {
        var tab = (window.IDE && window.IDE.mobileEditor) ? window.IDE.mobileEditor.getActiveTab() : null;
        if (!tab) { pvToast('⚠ No file open — open a file first'); return; }
        var cfg = window.IDE_CONFIG || {};
        var previewable = cfg.previewable || ['html', 'htm', 'php', 'svg', 'md', 'markdown'];
        if (previewable.indexOf(tab.ext) === -1 && getContentType(tab.ext) !== 'image') {
          pvToast('⚠ .' + tab.ext + ' files cannot be previewed');
          return;
        }
        var popUrl = 'index.php?api=preview-render&raw=1&path=' + encodeURIComponent(tab.path) +
          '&device=' + encodeURIComponent(mPvDevice) +
          '&zoom=' + (Math.round(mPvUserMult * 100) / 100) +
          '&live=1' +                                        // ★ pop-out never waits on a cold server boot
          ((window.AndroidHardware) ? '&quirky_app=1' : ''); // ★ app provides its own HOME chip
        window.open(popUrl, '_blank');
        pvToast('↗ Opened in new tab');
      });
    }
  }

  /* ═══════════════════════════════════════════════════════════════
     PINCH-TO-ZOOM FOR IMAGES IN PHONE MODE
  ═══════════════════════════════════════════════════════════════ */
  var imgPinchState = { active: false, startDist: 0, startZoom: 1 };
  function initImagePinchZoom() {
    if (!mDeviceStage) return;
    mDeviceStage.addEventListener('touchstart', function (e) {
      if (mPvContentType !== 'image') return;
      if (e.touches.length === 2) {
        var dx = e.touches[0].clientX - e.touches[1].clientX;
        var dy = e.touches[0].clientY - e.touches[1].clientY;
        imgPinchState.startDist = Math.sqrt(dx * dx + dy * dy);
        imgPinchState.startZoom = mPvUserMult;
        imgPinchState.active = true;
      }
    }, { passive: true });
    mDeviceStage.addEventListener('touchmove', function (e) {
      if (!imgPinchState.active || mPvContentType !== 'image') return;
      if (e.touches.length === 2) {
        e.preventDefault();
        var dx = e.touches[0].clientX - e.touches[1].clientX;
        var dy = e.touches[0].clientY - e.touches[1].clientY;
        var dist = Math.sqrt(dx * dx + dy * dy);
        var ratio = dist / imgPinchState.startDist;
        var ib = getZoomBounds();
        var newZoom = Math.max(ib.min, Math.min(ib.max, imgPinchState.startZoom * ratio));
        mPvUserMult = newZoom;
        mpvUpdateZoomChip();
        mpvUpdateZoomButtons();
        updateImageZoom();
      }
    }, { passive: false });
    mDeviceStage.addEventListener('touchend', function (e) {
      if (e.touches.length < 2) imgPinchState.active = false;
    }, { passive: true });
  }

  /* ═══════════════════════════════════════════════════════════════
     INIT
  ═══════════════════════════════════════════════════════════════ */
  var inited = false;
  function init() {
    if (inited) return;
    inited = true;
    wireZoomButtons();
    wireDeviceButtons();
    initImagePinchZoom();
    window.addEventListener('resize', mPvLayoutDevice);
    window.addEventListener('message', onPvMessage);

    function pvFrameLooksBlank() {
      try {
        if (!mPreviewFrame) return true;
        var doc = mPreviewFrame.contentDocument;
        if (!doc) {
          /* ★ v5.4: cross-origin frame (Built-in server / Apache mode).
          The browser refuses to let us look inside — that used to be
          mis-read as "blank" and triggered up to 3 bogus "rescue"
          reloads of a perfectly healthy preview. A frame that carries
          a real src URL is serving content: NOT blank. */
          return !mPreviewFrame.getAttribute('src');
        }
        var body = doc.body;
        if (!body) return true;
        return body.children.length === 0 && (body.textContent || '').trim() === '';
      } catch (e) { return false; }
    }
    function pvInjectNavGuard() {
      try {
        var doc = mPreviewFrame.contentDocument;
        if (!doc || !doc.body) return;
        /* ★ SAFETY NET: if the iframe ever escaped into the IDE shell
        (the "IDE inside IDE" paradox), re-render the preview immediately. */
        if (doc.getElementById('mobile-root') || doc.getElementById('mobile-tabbar')) {
          mobilePreviewRender();
          return;
        }
        if (doc.getElementById('pv-nav-guard')) return;
        /* v5.1: already guarded (baked into the srcdoc) → nothing to do. */
        try { if (doc.defaultView && doc.defaultView.__pvNavGuard) return; } catch (e2) { }
        /* v5.1: validate FIRST, then isolate the injection in its own
           try/catch — a broken frame script can never abort the load
           handler again (the old "Unexpected end of input" crash). */
        if (!mPvGuardOk()) return;
        try {
          var s = doc.createElement('script');
          s.id = 'pv-nav-guard';
          s.textContent = PV_NAV_GUARD_JS;
          doc.body.appendChild(s);
        } catch (injectErr) { /* guard failed — preview keeps working */ }
      } catch (e) { /* cross-origin content — ignore */ }
    }
    function pvReplaceFrameNode() {
      var old = mPreviewFrame;
      if (!old || !old.parentNode) return;
      var fresh = document.createElement('iframe');
      fresh.id = 'm-preview-frame';
      fresh.title = 'Live preview';
      fresh.style.background = '#ffffff';
      old.parentNode.replaceChild(fresh, old);
      mPreviewFrame = fresh;
      fresh.addEventListener('load', pvInjectNavGuard);
    }
    var pvBlankFixKey = '';
    var pvBlankFixCount = 0;
    function pvRecoverIfBlank() {
      if (!inited) return;
      if (!mPreviewPanel || mPreviewPanel.classList.contains('hidden')) return;
      /* ★ v5 guards: never fight an in-flight PHP render, a paint that
         just happened, or a visible error strip. */
      /* ★ v5.3: a PHP fetch in flight OR one that JUST finished means the
          frame is legitimately (re)loading — never fight it. This removes
          the 2 false-alarm re-renders on first open (was: 3 total loads). */
      if (mPvPhpInFlight) return;
      if (Date.now() - mPvLastPaintAt < 2500) return;
      if (document.getElementById('mpv-error-strip')) return;
      if (!pvFrameLooksBlank()) {
        pvBlankFixKey = '';
        pvBlankFixCount = 0;
        requestAnimationFrame(mPvLayoutDevice);
        return;
      }
      var tab = (window.IDE && window.IDE.mobileEditor) ? window.IDE.mobileEditor.getActiveTab() : null;
      if (!tab || !(tab.content || '').trim()) return;
      var key = tab.path + ':' + (tab.content || '').length;
      if (pvBlankFixKey === key) {
        pvBlankFixCount++;
        if (pvBlankFixCount > 2) return;
      } else {
        pvBlankFixKey = key;
        pvBlankFixCount = 0;
      }
      var keepY = 0;
      try {
        if (mPreviewFrame && mPreviewFrame.contentWindow) {
          keepY = mPreviewFrame.contentWindow.scrollY || mPreviewFrame.contentWindow.pageYOffset || 0;
        }
      } catch (e) { keepY = 0; }
      pvReplaceFrameNode();
      mobilePreviewRender();
      if (keepY > 0 && mPreviewFrame) {
        mPreviewFrame.addEventListener('load', function h() {
          mPreviewFrame.removeEventListener('load', h);
          try { mPreviewFrame.contentWindow.scrollTo(0, keepY); } catch (e) { }
        });
      }
      requestAnimationFrame(mPvLayoutDevice);
      setTimeout(mPvLayoutDevice, 120);
    }
    var pvResumeTimer = null;
    var pvLastResumeAt = 0;
    function onAppResume() {
      var now = Date.now();
      if (now - pvLastResumeAt < 1500) return;
      if (mPvPhpInFlight) return;                    /* ★ v5.3: render already on the way */
      if (now - mPvLastPaintAt < 2500) return;       /* ★ v5.3: paint just landed — settle first */
      pvLastResumeAt = now;
      if (pvResumeTimer) clearTimeout(pvResumeTimer);
      pvResumeTimer = setTimeout(function () {
        pvResumeTimer = null;
        pvRecoverIfBlank();
        setTimeout(pvRecoverIfBlank, 650);
      }, 250);
    }
    document.addEventListener('visibilitychange', function () { if (!document.hidden) onAppResume(); });
    window.addEventListener('pageshow', onAppResume);
    window.addEventListener('focus', onAppResume);
    var pvLastTick = Date.now();
    var pvWatchdogToggle = false;
    setInterval(function () {
      var now = Date.now();
      if (now - pvLastTick > 5000) onAppResume();
      pvLastTick = now;
      pvWatchdogToggle = !pvWatchdogToggle;
      if (pvWatchdogToggle) pvRecoverIfBlank();
    }, 1000);
    if (typeof ResizeObserver !== 'undefined' && mDeviceStage) {
      var stageRO = new ResizeObserver(function () { mPvLayoutDevice(); });
      stageRO.observe(mDeviceStage);
    }
    if (mDeviceStage) {
      mDeviceStage.addEventListener('touchend', function () { setTimeout(pvRecoverIfBlank, 250); }, { passive: true });
    }
    window.addEventListener('load', function () { mPvLayoutDevice(); });
    setTimeout(mPvLayoutDevice, 150);
    setTimeout(mPvLayoutDevice, 400);
    ensurePreviewBarExtras();
    startScrollTracker();

    /* ★ v5 SAVE HOOK — refreshes disk-backed previews, honours SAVE
       mode, bumps the image cache generation, and marks the project
       data stale WITHOUT blocking anything. */
    if (U && U.on) {
      U.on('editor:saved', function (e) {
        var savedPath = e.detail && e.detail.path;
        mPvProjectsLoadedAt = 0;   // background refresh picks it up lazily
        mPvImgBust++;              // images may have changed on disk
        if (!savedPath || !mPreviewPanel || mPreviewPanel.classList.contains('hidden')) return;
        var tab = (window.IDE && window.IDE.mobileEditor) ? window.IDE.mobileEditor.getActiveTab() : null;
        if (!tab || tab.path !== savedPath) return;
        /* ★ v5.5: SAVE mode must refresh ONLY on a real user save.
           Autosave also emits editor:saved (explicit:false); reacting
           to it made SAVE mode behave like LIVE and reloaded the frame
           on every autosave (the flicker users saw). */
        var autoSave = !!(e.detail && e.detail.explicit === false);
        var needsRender;
        if (getRefreshMode() === 'save') {
          needsRender = !autoSave;   /* only 💾 / Ctrl+S repaints */
        } else {
          /* LIVE mode: keystrokes already repaint srcdoc types;
             disk-backed surfaces (server/Apache/images) refresh on saves. */
          needsRender = mPvApacheServing || mPvServerServing ||
            mPvContentType === 'image';
        }
        if (needsRender) {
          clearTimeout(mPvApacheTimer);
          mPvLastServerKey = '';     // saved content now differs from the paint
          mPvApacheTimer = setTimeout(function () { mobilePreviewRender(); }, 200);
        }
        /* ★ v5.2: non-forced refresh — mPvProjectsLoadedAt was
           already zeroed above, so the NEXT render picks it up
           lazily. This removes a blocking network round-trip
           from every single save. */
        mPvBackgroundProjectRefresh(false);
      });
    }
    wireActionButtons();
    if (mPreviewFrame) mPreviewFrame.addEventListener('load', pvInjectNavGuard);
    applyMobilePreviewDevice(mPvDevice);
    mpvUpdateZoomChip();
    mpvUpdateZoomButtons();
    /* Warm the project/Apache knowledge quietly in the background. */
    mPvBackgroundProjectRefresh(true);
  }
  window._mobileSchedulePreview = scheduleMobilePreview;
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  /* ═══════════════════════════════════════════════════════════════
     EXPOSE
  ═══════════════════════════════════════════════════════════════ */
  window.IDE = window.IDE || {};
  window.IDE.mobilePreview = {
    init: init,
    render: mobilePreviewRender,
    refresh: mobilePreviewRender,
    onTabShown: onTabShown,
    schedule: scheduleMobilePreview,
    setDevice: applyMobilePreviewDevice,
    getDevice: function () { return mPvDevice; },
    setZoom: mpvSetZoom,
    resetZoom: mpvResetZoom
  };
})();