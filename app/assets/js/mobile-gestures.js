/**
 * ═══════════════════════════════════════════════════════════════════════════
 *  QUIRKY IDE — MOBILE SWIPE GESTURES (Phase 6 · Task 6.1)
 * ═══════════════════════════════════════════════════════════════════════════
 *
 *  Swipe left/right on the main content area to switch between
 *  Files / Editor / Preview / Terminal tabs.
 *
 *  • 50px horizontal threshold to trigger a switch
 *  • Vertical scrolling is never hijacked
 *  • Swipes on interactive elements (buttons, inputs, CodeMirror,
 *    tree rows, iframes, terminal output) are ignored
 *  • Visual feedback: the active panel follows your finger
 *  • Slide-in animation on the newly activated panel
 *
 *  EXPOSES: nothing (fully self-contained)
 * ═══════════════════════════════════════════════════════════════════════════
 */
(function () {
  'use strict';

  function init() {
    var limits = (window.IDE_CONFIG && window.IDE_CONFIG.deviceLimits) || {};
    if (limits.gestures === false) return; // ★ LOCKDOWN
    
    /* ── Guard: only run on the mobile shell ── */
    var content = document.getElementById('mobile-content');
    if (!content) return;

    /* ── Configuration ──
    Build the tab order from panels that actually exist, so features
    that are turned off (their panels are removed by the server) are
    skipped when you swipe between tabs. */
    var TAB_ORDER = ['files', 'editor', 'preview', 'terminal'].filter(function (name) {
      return !!document.getElementById('mp-' + name);
    });
    var SWIPE_THRESHOLD = 50;          // px needed to trigger a switch
    var HORIZONTAL_RATIO = 1.5;        // dx must exceed dy * this
    var INTENT_THRESHOLD = 10;         // px before we decide direction
    var RESISTANCE = 0.4;              // panel moves at 40% of finger speed
    var MAX_TRANSLATE_RATIO = 0.4;     // panel won't move past 40% of screen

    /* ── State ── */
    var startX = 0, startY = 0;
    var isSwiping = false;
    var isHorizontalIntent = false;
    var activePanelEl = null;

    /* ── Helpers ── */
    function getActivePanel() {
      var panels = content.querySelectorAll('.m-panel');
      for (var i = 0; i < panels.length; i++) {
        if (!panels[i].classList.contains('hidden')) return panels[i];
      }
      return null;
    }

    function getActiveTabName() {
      var activeBtn = document.querySelector('#mobile-tabbar .mt-btn.active');
      if (!activeBtn) return null;
      var panel = activeBtn.getAttribute('data-panel');
      return (TAB_ORDER.indexOf(panel) !== -1) ? panel : null;
    }

    function getNextTab(direction) {
      var current = getActiveTabName();
      if (!current) return null;
      var idx = TAB_ORDER.indexOf(current);
      if (direction === 'left') {
        return TAB_ORDER[(idx + 1) % TAB_ORDER.length];
      } else {
        return TAB_ORDER[(idx - 1 + TAB_ORDER.length) % TAB_ORDER.length];
      }
    }

    function switchToTab(tabName) {
      var btn = document.querySelector('#mobile-tabbar [data-panel="' + tabName + '"]');
      if (btn) btn.click();
    }

    /**
     * Returns true if the swipe should be blocked because the touch
     * started on an interactive element that needs its own gestures.
     */
    function isSwipeBlocked(target) {
      if (!target || !target.closest) return true;
      /* ★ RESTRICTION: tab-switch swipes are ONLY recognised on the panel
      head bar (the row that says EXPLORER / EDITOR / PREVIEW / TERMINAL).
      Every other surface keeps its own gestures:
      • preview stage outside the bezel → no accidental tab switch
      • empty space in Explorer        → no accidental tab switch
      • editor / terminal bodies       → untouched
      Pull-to-refresh is NOT affected: it listens for a VERTICAL pull on
      the tree, while tab switching needs a HORIZONTAL swipe on the head. */
      return !target.closest('.m-panel-head');
    }

    function resetPanelTransform() {
      if (activePanelEl) {
        activePanelEl.classList.add('swipe-reset');
        activePanelEl.style.transform = '';
        // Remove the transition class after the snap-back completes
        setTimeout(function () {
          if (activePanelEl) activePanelEl.classList.remove('swipe-reset');
        }, 220);
      }
    }

    function animatePanelIn(direction) {
      // Wait one frame so the new panel is visible in the DOM
      requestAnimationFrame(function () {
        var newPanel = getActivePanel();
        if (!newPanel) return;
        var animClass = (direction === 'left') ? 'swipe-in-left' : 'swipe-in-right';
        newPanel.classList.add(animClass);
        newPanel.addEventListener('animationend', function handler() {
          newPanel.classList.remove(animClass);
          newPanel.removeEventListener('animationend', handler);
        });
      });
    }

    /* ── Touch Handlers ── */
    function onTouchStart(e) {
      if (isSwipeBlocked(e.target)) return;
      var touch = e.touches[0];
      startX = touch.clientX;
      startY = touch.clientY;
      isSwiping = true;
      isHorizontalIntent = false;
      activePanelEl = getActivePanel();
    }

    /* ═══════════════════════════════════════════════════════════════
  TASK 9.7 — PRELOAD ADJACENT PANEL
  Determines which panel the user is swiping toward and starts
  loading its data immediately (before the swipe completes).
  ═══════════════════════════════════════════════════════════════ */
    function preloadAdjacentPanel(dx) {
      var currentTab = getActiveTabName();
      if (!currentTab) return;
      var direction = (dx < 0) ? 'left' : 'right';
      var targetTab = getNextTab(direction);
      if (targetTab && window._mobilePreloadTab) {
        window._mobilePreloadTab(targetTab);
      }
    }

    function onTouchMove(e) {
      if (!isSwiping) return;
      var touch = e.touches[0];
      var dx = touch.clientX - startX;
      var dy = touch.clientY - startY;
      // Decide intent on first significant movement
      if (!isHorizontalIntent) {
        var absDx = Math.abs(dx);
        var absDy = Math.abs(dy);
        if (absDx > INTENT_THRESHOLD && absDx > absDy * HORIZONTAL_RATIO) {
          isHorizontalIntent = true;
          /* ── Task 9.7: Preload the adjacent panel ── */
          preloadAdjacentPanel(dx);
        } else if (absDy > INTENT_THRESHOLD && absDy > absDx) {
          // Vertical scroll intent → abort swipe tracking
          isSwiping = false;
          return;
        }
      }
      if (isHorizontalIntent) {
        // Prevent the page/panel from scrolling while we swipe
        e.preventDefault();
        // Apply visual feedback with resistance
        var translateX = dx * RESISTANCE;
        var maxTranslate = window.innerWidth * MAX_TRANSLATE_RATIO;
        if (translateX > maxTranslate) translateX = maxTranslate;
        if (translateX < -maxTranslate) translateX = -maxTranslate;
        if (activePanelEl) {
          activePanelEl.style.transform = 'translateX(' + translateX + 'px)';
        }
      }
    }

    function onTouchEnd(e) {
      if (!isSwiping) return;
      isSwiping = false;

      if (!isHorizontalIntent) return;

      var touch = e.changedTouches[0];
      var dx = touch.clientX - startX;

      if (Math.abs(dx) >= SWIPE_THRESHOLD) {
        // Threshold met → switch tab
        var direction = (dx < 0) ? 'left' : 'right';
        var targetTab = getNextTab(direction);

        // Reset the dragged panel's transform before switching
        if (activePanelEl) {
          activePanelEl.style.transform = '';
        }

        if (targetTab) {
          switchToTab(targetTab);
          animatePanelIn(direction);
        }
      } else {
        // Below threshold → snap back
        resetPanelTransform();
      }
    }

    /* ── Wire events ── */
    content.addEventListener('touchstart', onTouchStart, { passive: true });
    content.addEventListener('touchmove', onTouchMove, { passive: false });
    content.addEventListener('touchend', onTouchEnd, { passive: true });
    content.addEventListener('touchcancel', onTouchEnd, { passive: true });
  }

  /* ── Boot: wait for DOMContentLoaded so the mobile shell has
        already wired up the tab buttons before we try to use them ── */
  if (document.readyState === 'loading' || document.readyState === 'interactive') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();