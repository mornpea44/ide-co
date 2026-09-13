/**
* ═══════════════════════════════════════════════════════════════════════════
*  QUIRKY IDE — MOBILE SHEET MANAGEMENT (Phase 7 · Task 7.11)
* ═══════════════════════════════════════════════════════════════════════════
*
*  Reusable bottom-sheet open/close/dismiss logic.
*  Accepts either a DOM element or a string ID for both the sheet
*  and its backdrop, so any module can use it.
*
*  EXPOSES: window.IDE.mobileSheets
* ═══════════════════════════════════════════════════════════════════════════
*/
(function () {
  'use strict';

  /* ═══════════════════════════════════════════════════════════════
  HELPERS — resolve a DOM element from an element or a string ID
  ═══════════════════════════════════════════════════════════════ */
  function resolveEl(elOrId) {
    if (typeof elOrId === 'string') return document.getElementById(elOrId);
    return elOrId;
  }

  /* Pending close timers, keyed by the sheet element's id.
     closeSheet() hides the sheet 280ms after the slide-down starts.
     If the SAME sheet is re-opened before that timer fires (back-to-back
     name-conflict popups in a paste/move batch), the stale timer would
     hide the freshly opened sheet — that was the "second popup
     auto-closes" bug. openSheet() now cancels any pending close. */
  var _closeTimers = {};

  /* ═══════════════════════════════════════════════════════════════
  OPEN SHEET
  Shows the backdrop and slides the sheet up into view.
  ═══════════════════════════════════════════════════════════════ */
  function openSheet(sheetOrId, backdropOrId) {
    var sheet = resolveEl(sheetOrId);
    var backdrop = resolveEl(backdropOrId);
    if (!sheet || !backdrop) return;
    /* Cancel a pending close from a moment ago so a fast re-open
       (e.g. the next name-conflict popup) is not swallowed. */
    var key = sheet.id || '';
    if (_closeTimers[key]) {
      clearTimeout(_closeTimers[key]);
      _closeTimers[key] = null;
    }
    backdrop.classList.remove('hidden');
    sheet.classList.remove('hidden');
    void sheet.offsetWidth; // force reflow so the CSS transition fires
    sheet.classList.add('show');
    backdrop.classList.add('show');
  }

  /* ═══════════════════════════════════════════════════════════════
  CLOSE SHEET
  Slides the sheet down and hides both after the transition ends.
  ═══════════════════════════════════════════════════════════════ */
  function closeSheet(sheetOrId, backdropOrId) {
    var sheet = resolveEl(sheetOrId);
    var backdrop = resolveEl(backdropOrId);
    if (!sheet || !backdrop) return;
    var key = sheet.id || '';
    if (_closeTimers[key]) clearTimeout(_closeTimers[key]);
    sheet.classList.remove('show');
    backdrop.classList.remove('show');
    _closeTimers[key] = setTimeout(function () {
      sheet.classList.add('hidden');
      backdrop.classList.add('hidden');
      _closeTimers[key] = null;
    }, 280);
  }

  /* ═══════════════════════════════════════════════════════════════
  CLOSE ALL ACTION SHEETS
  Convenience: closes every file-action-related sheet at once.
  ═══════════════════════════════════════════════════════════════ */
  function closeAllActionSheets() {
    closeSheet('ms-file-actions', 'fa-backdrop');
    closeSheet('ms-rename', 'rn-backdrop');
    closeSheet('ms-delete', 'dl-backdrop');
    closeSheet('ms-conflict', 'cf-backdrop');
  }

  /* ═══════════════════════════════════════════════════════════════
  DRAG-TO-DISMISS
  Basic threshold-based swipe-down-to-close on a sheet.
  The sheet must be passed by ID (string) so we can query it.
  ═══════════════════════════════════════════════════════════════ */
  function initDragToDismiss(sheetId, backdropId) {
    var sheet = document.getElementById(sheetId);
    if (!sheet) return;

    var startY = 0;
    var dragging = false;

    sheet.addEventListener('touchstart', function (e) {
      // Only start dragging from the top 60px (handle area)
      if (e.touches[0].clientY - sheet.getBoundingClientRect().top < 60) {
        startY = e.touches[0].clientY;
        dragging = true;
      }
    }, { passive: true });

    sheet.addEventListener('touchmove', function (e) {
      if (!dragging) return;
      var dy = e.touches[0].clientY - startY;
      if (dy > 80) {
        dragging = false;
        closeSheet(sheetId, backdropId);
      }
    }, { passive: true });

    sheet.addEventListener('touchend', function () {
      dragging = false;
    }, { passive: true });
  }

  /* ═══════════════════════════════════════════════════════════════
  DRAG-TO-DISMISS FOR FULL-SCREEN OVERLAYS (Task 8.7)
  ═══════════════════════════════════════════════════════════════
  Works like the sheet version but with richer visual feedback:
    • Overlay follows your finger downward (at 60% resistance)
    • A subtle opacity fade as you drag further
    • If you release past the threshold (120px) → close with animation
    • If you release before → snap back smoothly
  
  The drag only starts from the header area (first 80px of the overlay),
  so scrolling inside the overlay body is never hijacked.
  
  @param {string}   overlayId   - The overlay element ID
  @param {Function} closeFn     - Called to close/dismiss the overlay
  ═══════════════════════════════════════════════════════════════ */
  function initOverlayDragToDismiss(overlayId, closeFn) {
    var overlay = document.getElementById(overlayId);
    if (!overlay || typeof closeFn !== 'function') return;

    var startY = 0;
    var currentY = 0;
    var dragging = false;
    var THRESHOLD = 120;       // px of downward drag needed to dismiss
    var RESISTANCE = 0.6;      // overlay moves at 60% of finger speed
    var MAX_TRANSLATE = 280;   // cap so it doesn't fly off screen

    overlay.addEventListener('touchstart', function (e) {
      // Only start from the header zone (top 80px of the overlay)
      var rect = overlay.getBoundingClientRect();
      var touchY = e.touches[0].clientY;
      if (touchY - rect.top < 80) {
        startY = touchY;
        currentY = touchY;
        dragging = true;
        overlay.style.transition = 'none';
      }
    }, { passive: true });

    overlay.addEventListener('touchmove', function (e) {
      if (!dragging) return;
      currentY = e.touches[0].clientY;
      var dy = (currentY - startY) * RESISTANCE;
      if (dy < 0) dy = 0;   // don't allow upward drag
      if (dy > MAX_TRANSLATE) dy = MAX_TRANSLATE;
      overlay.style.transform = 'translateY(' + dy + 'px)';
      // Fade slightly as you drag further
      var opacity = Math.max(0.4, 1 - (dy / (MAX_TRANSLATE * 1.5)));
      overlay.style.opacity = String(opacity);
    }, { passive: true });

    overlay.addEventListener('touchend', function () {
      if (!dragging) return;
      dragging = false;
      var dy = (currentY - startY) * RESISTANCE;

      if (dy >= THRESHOLD) {
        // Dismiss: slide fully down and close
        overlay.style.transition = 'transform 0.25s ease-out, opacity 0.25s ease-out';
        overlay.style.transform = 'translateY(100%)';
        overlay.style.opacity = '0';
        setTimeout(function () {
          overlay.style.transform = '';
          overlay.style.opacity = '';
          overlay.style.transition = '';
          closeFn();
        }, 260);
      } else {
        // Snap back
        overlay.style.transition = 'transform 0.2s ease-out, opacity 0.2s ease-out';
        overlay.style.transform = 'translateY(0)';
        overlay.style.opacity = '1';
        setTimeout(function () {
          overlay.style.transition = '';
        }, 220);
      }
    }, { passive: true });
  }

  /* ═══════════════════════════════════════════════════════════════
  OVERLAY STACK (v10)
  ═══════════════════════════════════════════════════════════════
  Panels register themselves on open and unregister on close; ONE
  shared Escape/BACK handler pops only the top entry. This replaces
  the old per-module Escape listeners that could close several stacked
  overlays at once. */
  var _stack = [];   // [{id, close}]

  function registerOverlay(id, closeFn) {
    unregisterOverlay(id);
    _stack.push({ id: id, close: closeFn });
  }
  function unregisterOverlay(id) {
    for (var i = _stack.length - 1; i >= 0; i--) {
      if (_stack[i].id === id) { _stack.splice(i, 1); return; }
    }
  }
  function closeTopOverlay() {
    if (!_stack.length) return false;
    var top = _stack[_stack.length - 1];
    try { top.close(); } catch (e) { }
    unregisterOverlay(top.id);
    return true;
  }

  // Single document-level Escape handler — pops the top overlay only.
  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    if (_stack.length && e.target &&
        !/INPUT|TEXTAREA|SELECT/.test((e.target.tagName || ''))) {
      if (closeTopOverlay()) {
        e.preventDefault();
        e.stopPropagation();
      }
    }
  }, true);

  /* ═══════════════════════════════════════════════════════════════
  EXPOSE
  ═══════════════════════════════════════════════════════════════ */
  window.IDE = window.IDE || {};
  /**
   * @namespace window.IDE.mobileSheets
   * Reusable bottom-sheet management for the mobile shell.
   */
  window.IDE.mobileSheets = {
    /** Open a sheet (accepts DOM element or string ID). */
    openSheet: openSheet,
    /** Close a sheet (accepts DOM element or string ID). */
    closeSheet: closeSheet,
    /** Close all file-action sheets at once. */
    closeAllActionSheets: closeAllActionSheets,
    /** Wire drag-to-dismiss on a sheet (by string ID). */
    initDragToDismiss: initDragToDismiss,
    /** Wire drag-to-dismiss on a full-screen overlay (legacy; v10 panels no longer use it). */
    initOverlayDragToDismiss: initOverlayDragToDismiss,
    /** v10: register an open overlay so Escape pops it first. */
    registerOverlay: registerOverlay,
    /** v10: unregister by id (call from your close path). */
    unregisterOverlay: unregisterOverlay,
    /** v10: close+unregister the top-most registered overlay. Returns true when one was closed. */
    closeTopOverlay: closeTopOverlay
  };
})();