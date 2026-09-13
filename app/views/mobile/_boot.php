<?php
/**
* ═══════════════════════════════════════════════════════════════════════════
*  QUIRKY IDE — MOBILE BOOT PARTIAL (Phase 2 · Separation)
* ═══════════════════════════════════════════════════════════════════════════
*
*  Injected at the top of <head> in mobile-shell.php.
*  Outputs mobile-specific meta tags that don't belong on desktop:
*    • Viewport locked for touch (no user zoom, covers notch)
*    • PWA standalone mode hints
*    • PWA manifest link
*    • Theme color for the browser chrome
*
*  This file is included by mobile-shell.php BEFORE the main <head> content.
*
* ═══════════════════════════════════════════════════════════════════════════
*/
?>
<!-- ── MOBILE BOOT — viewport + PWA meta ── -->
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Quirky IDE">
<meta name="mobile-web-app-capable" content="yes">
<meta name="theme-color" content="#0d1420">
<meta name="format-detection" content="telephone=no">
<link rel="manifest" href="index.php?asset=manifest.json">
<link rel="apple-touch-icon" href="index.php?asset=icons/icon.svg">