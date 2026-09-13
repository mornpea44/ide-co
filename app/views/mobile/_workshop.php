<?php

/**
 * QUIRKY IDE — Mobile Workshop Overlay Partial (Phase 1 + Phase 3)
 * Full-screen overlay for managing IDE extensions and detected projects.
 * Rendered by JS (mobile-workshop.js) — this is just the shell.
 */
?>
<!-- ── WORKSHOP OVERLAY (Phase 1 + Phase 3) ── -->
<div class="m-workshop-overlay hidden" id="m-workshop-overlay">
    <div class="m-workshop-header">
        <button class="m-workshop-close" id="m-workshop-close" title="Close" aria-label="Close Workshop">✕</button>
        <span class="m-workshop-title">🔧 Workshop</span>
    </div>
    <div class="m-workshop-body" id="m-workshop-body">
        <div class="m-workshop-loading">
            <div class="m-workshop-spinner"></div>
            <span>Loading extensions…</span>
        </div>
    </div>
</div>

<!-- ══ COMMUNITY PACKAGES · bottom sheets (dynamic Workshop) ══
     Shared backdrop; opened/closed through IDE.mobileSheets.openSheet(). -->
<div class="m-sheet-backdrop hidden" id="wspkg-backdrop"></div>

<!-- ── Add / install sheet ── -->
<div class="m-sheet hidden" id="wspkg-add-sheet">
    <div class="m-sheet-handle"></div>
    <div class="m-sheet-title" id="wspkg-add-title">ADD PACKAGE</div>
    <div class="m-sheet-hint">github user/repo · direct .zip link · link to quirky.workshop.json</div>
    <div class="m-sheet-input-wrap">
        <input type="text" id="wspkg-source-input" class="m-sheet-input" placeholder="owner/repo or https://…" autocomplete="off" autocapitalize="off" spellcheck="false">
    </div>
    <div class="m-sheet-msg" id="wspkg-add-msg"></div>
    <div class="m-wspkg-progress hidden" id="wspkg-add-progress">
        <div class="m-workshop-log-line" id="wspkg-add-log"></div>
    </div>
    <button class="m-sheet-row" id="wspkg-install-btn">⬇ Install</button>
    <button class="m-sheet-row" id="wspkg-cancel-btn">Cancel</button>
</div>

<!-- ── Trust / consent sheet (capabilities review before enabling) ── -->
<div class="m-sheet hidden" id="wspkg-consent-sheet">
    <div class="m-sheet-handle"></div>
    <div class="m-sheet-title">REVIEW PERMISSIONS</div>
    <div class="m-wspkg-consent-head" id="wspkg-consent-head"></div>
    <div class="m-wspkg-consent-list" id="wspkg-consent-list"></div>
    <div class="m-wspkg-consent-note" id="wspkg-consent-note"></div>
    <button class="m-sheet-row" id="wspkg-trust-btn">✓ Trust &amp; enable</button>
    <button class="m-sheet-row danger" id="wspkg-deny-btn">Not now</button>
</div>