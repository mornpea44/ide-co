<?php

/**
 * QUIRKY IDE — Mobile Header Partial
 * The top bar: logo, file name, tree-tools (search), palette trigger, save.
 * Uses $featCommandPalette from mobile-shell.php.
 */
?>
<!-- ── MOBILE HEADER ── -->
<header id="mobile-header">
    <span class="mh-logo">⚡</span>
    <span class="mh-file" id="mh-file">QUIRKY IDE</span>
    <div class="mh-spacer"></div>
    <!-- Toggleable tree tools (workspace switcher, filter, recents).
        Only shown on the Files tab — mobile-shell.js controls visibility. -->
    <button class="mh-btn" id="mh-tree-tools" title="Toggle tree tools" aria-label="Toggle tree tools" aria-pressed="false">🔍</button>
    <?php if (!empty($featCommandPalette)): ?>
        <button class="mh-btn" id="mh-palette" title="Quick Open / Commands" aria-label="Command palette">⌘</button>
    <?php endif; ?>
    <button class="mh-btn mh-save" id="mh-save" title="Save all changes" aria-label="Save all changes">💾</button>
</header>