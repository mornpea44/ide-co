
/**
* ═══════════════════════════════════════════════════════════════════════════
*  QUIRKY IDE — MOBILE SWIPE GESTURES ON TREE ROWS (Gesture Revamp + animation)
* ═══════════════════════════════════════════════════════════════════════════
*
*  Swipe LEFT or RIGHT on a file/folder row = SELECT it.
*  Both directions do exactly the same thing.
*
*  DECORATION: while you swipe, the row content follows your finger
*  (with soft resistance + a cap), the row glows amber, and on release
*  everything springs back into place before the selection toggles.
*  There are NO buttons behind the row — the slide is feedback only.
*
*  Depends on: window.IDE.mobileFileTree.toggleSelectBySwipe (lazy lookup)
*  EXPOSES: nothing (fully self-contained)
* ═══════════════════════════════════════════════════════════════════════════
*/
(function () {
    'use strict';

    var U = window.IDE && window.IDE.utils;
    if (!U) return;

    var container = document.getElementById('m-file-tree');
    if (!container) return;

    /* ── Tuning ── */
    var AXIS_TOLERANCE = 12;   // px before we decide horizontal vs vertical
    var SWIPE_MIN = 20;        // px of horizontal travel that counts as a swipe
    var MAX_SLIDE = 56;        // max px the content follows the finger
    var RESISTANCE = 0.45;     // content moves at 45% of finger speed

    var suppressClick = false;
    var touch = null;

    /* Lazy handle — mobile-filetree.js loads AFTER this file in the bundle */
    function FT() { return window.IDE.mobileFileTree; }

    function freshTouch() {
        return {
            row: null, content: null,
            startX: 0, startY: 0,
            axis: null, swiped: false
        };
    }

    /* ── Content wrapper — the row's indent (padding-left) stays on the ROW,
          only the wrapper slides. Same safe approach as the original module. ── */
    function ensureContent(row) {
        var content = row.querySelector('.m-tree-content');
        if (content) return content;
        content = document.createElement('div');
        content.className = 'm-tree-content';
        while (row.firstChild) content.appendChild(row.firstChild);
        row.appendChild(content);
        return content;
    }

    /* ── Spring back to rest (uses the CSS transition for the bounce) ── */
    function snapBack(content) {
        if (!content) return;
        content.style.transition = '';    // hand control back to CSS
        content.style.transform = '';
    }

    function clearSwipeGlow(row) {
        if (row) row.classList.remove('m-swiping');
    }

    /* ═══════════════════════════════════════════════════════════════
    TOUCH HANDLERS
    ═══════════════════════════════════════════════════════════════ */
    function onTouchStart(e) {
        /* Don't fight the drag system if it already owns this touch */
        var ft = FT();
        if (ft && (ft._dragActive() || ft._dragArmed())) return;

        var row = (e.target.closest) ? e.target.closest('.m-tree-row') : null;
        if (!row) return;

        var t = e.touches[0];
        touch = freshTouch();
        touch.row = row;
        touch.content = ensureContent(row);
        touch.startX = t.clientX;
        touch.startY = t.clientY;
    }

    function onTouchMove(e) {
        if (!touch || !touch.row) return;

        /* If the drag system took over mid-gesture, back off completely */
        var ft = FT();
        if (ft && (ft._dragActive() || ft._dragArmed())) {
            snapBack(touch.content);
            clearSwipeGlow(touch.row);
            touch = null;
            return;
        }

        var t = e.touches[0];
        var dx = t.clientX - touch.startX;
        var dy = t.clientY - touch.startY;

        /* Decide direction once, on the first significant movement */
        if (!touch.axis) {
            var ax = Math.abs(dx), ay = Math.abs(dy);
            if (ax > AXIS_TOLERANCE && ax > ay) touch.axis = 'h';
            else if (ay > AXIS_TOLERANCE && ay > ax) { touch = null; return; } /* vertical = scrolling */
        }
        if (touch.axis !== 'h') return;

        /* Horizontal swipe: stop the page from scrolling while we track it */
        if (e.cancelable) e.preventDefault();

        /* ── THE ANIMATION: content follows the finger ──
           Resistance keeps it subtle; the cap keeps it decorative;
           a little rubber-band lets it stretch past the cap softly. */
        var offset = dx * RESISTANCE;
        if (offset > MAX_SLIDE) offset = MAX_SLIDE + (offset - MAX_SLIDE) * 0.2;
        if (offset < -MAX_SLIDE) offset = -MAX_SLIDE + (offset + MAX_SLIDE) * 0.2;

        touch.content.style.transition = 'none';   // finger is in charge right now
        touch.content.style.transform = 'translateX(' + offset + 'px)';
        touch.row.classList.add('m-swiping');      // amber glow feedback

        if (Math.abs(dx) >= SWIPE_MIN) touch.swiped = true;
    }

    function onTouchEnd() {
        var t = touch;
        touch = null;
        if (!t || !t.row) return;

        /* Always spring back first, then apply the selection */
        snapBack(t.content);
        clearSwipeGlow(t.row);

        if (!t.swiped) return;

        /* Kill the synthetic click the browser fires after touchend,
           otherwise it would instantly toggle the selection back. */
        suppressClick = true;
        setTimeout(function () { suppressClick = false; }, 400);

        var path = t.row.getAttribute('data-path');
        if (!path) return;

        var ft = FT();
        if (!ft || !ft.toggleSelectBySwipe) return;

        if (window.IDE.mobileActions && window.IDE.mobileActions.haptic) {
            window.IDE.mobileActions.haptic();
        }
        ft.toggleSelectBySwipe(path);
    }

    function onTouchCancel() {
        if (touch) {
            snapBack(touch.content);
            clearSwipeGlow(touch.row);
        }
        touch = null;
    }

    /* ═══════════════════════════════════════════════════════════════
    WIRE UP
    ═══════════════════════════════════════════════════════════════ */
    function initSwipeGestures() {
        var limits = (window.IDE_CONFIG && window.IDE_CONFIG.deviceLimits) || {};
        if (limits.gestures === false) return; // ★ LOCKDOWN
        
        container.addEventListener('touchstart', onTouchStart, { passive: true });
        container.addEventListener('touchmove', onTouchMove, { passive: false });
        container.addEventListener('touchend', onTouchEnd, { passive: true });
        container.addEventListener('touchcancel', onTouchCancel, { passive: true });

        /* Swallow the click a finished swipe would otherwise fire on the row */
        container.addEventListener('click', function (e) {
            if (suppressClick) {
                suppressClick = false;
                e.preventDefault();
                e.stopPropagation();
            }
        }, true);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSwipeGestures);
    } else {
        initSwipeGestures();
    }
})();
