<?php

/**
 * QUIRKY IDE — Mobile Panels Partial
 * The main content area: offline banner, Files, Editor, Preview, Terminal panels,
 * bottom tab bar, and floating action button.
 * Variables inherited from mobile-shell.php: none needed (pure HTML).
 */
?>
<!-- ── MAIN CONTENT AREA ── -->
<main id="mobile-content">

    <!-- ── OFFLINE BANNER (Task 6.4) ── -->
    <div id="m-offline-banner" class="m-offline-banner hidden">
        <span class="m-offline-icon">📡</span>
        <span class="m-offline-text">You are offline. Changes are saved locally.</span>
    </div>

    <!-- FILES PANEL -->
    <div class="m-panel" id="mp-files">
        <div class="m-panel-head">
            <span class="mph-title">EXPLORER</span>
            <span class="mph-sub mph-workspace-chip" id="mh-workspace-chip"
                title="Current workspace">
                <span class="mws-label">workspace/</span>
            </span>
        </div>
        <!-- ── Toggleable tree tools container (hidden by default) ── -->
        <div class="m-tree-tools hidden" id="m-tree-tools">
            <!-- Workspace / Project Switcher (Task 8.10) -->
            <div class="m-workspace-switcher" id="m-workspace-switcher">
                <div class="m-ws-chips" id="m-ws-chips"></div>
            </div>
            <div class="m-tree-search-wrap" id="m-tree-search-wrap">
                <span class="m-tree-search-icon">🔍</span>
                <input type="text" class="m-tree-search-input" id="m-tree-search-input"
                    placeholder="Filter files…" autocomplete="off" autocorrect="off"
                    autocapitalize="off" spellcheck="false">
                <!-- ★ Sort picker v2: icon-only chip + invisible native <select>.
                    The chip shows ONLY the current mode's icon; the real select
                    sits invisibly on top of it, so tapping the chip still opens
                    Android's own picker popup (full labels, never clipped). -->
                <span class="m-tree-sort-wrap" id="m-tree-sort-wrap">
                    <span class="m-tree-sort-ico" id="m-tree-sort-ico" aria-hidden="true">🔤</span>
                    <select class="m-tree-sort-select" id="m-tree-sort-select"
                        title="Sort files" aria-label="Sort files"></select>
                </span>
                <div class="m-tree-sort-drop hidden" id="m-tree-sort-drop" role="listbox"></div>
                <button class="m-tree-search-clear hidden" id="m-tree-search-clear"
                    title="Clear filter" aria-label="Clear filter">✕</button>
            </div>
            <!-- Empty filter result (Task 8.3) -->
            <div class="m-tree-filter-empty hidden" id="m-tree-filter-empty">
                <span class="m-tree-filter-empty-icon">🔍</span>
                <p>No files match "<span id="m-tree-filter-query"></span>"</p>
            </div>
            <!-- Recent files section (Task 8.4) — collapsible -->
            <div class="m-recent-wrap" id="m-recent-wrap">
                <div class="m-recent-head" id="m-recent-head">
                    <button class="m-recent-toggle" id="m-recent-toggle" title="Toggle recent files" aria-label="Toggle recent files">▾</button>
                    <span class="m-recent-title">RECENT FILES</span>
                    <span class="m-recent-count" id="m-recent-count"></span>
                    <button class="m-recent-clear" id="m-recent-clear" title="Clear recent files" aria-label="Clear recent files">Clear</button>
                </div>
                <div class="m-recent-list" id="m-recent-list"></div>
            </div>
        </div>
        <!-- Pull-to-refresh indicator (Task 2.4) — always visible -->
        <div class="m-ptr" id="m-ptr">
            <span class="m-ptr-spinner"></span>
            <span class="m-ptr-label">Pull to refresh</span>
        </div>
        <div class="m-panel-body" id="m-file-tree">
            <div class="m-tree-loading" id="m-tree-loading">
                <div class="m-tree-skeleton">
                    <div class="m-tree-skeleton-row">
                        <div class="m-skeleton m-tree-skeleton-icon"></div>
                        <div class="m-skeleton m-tree-skeleton-name"></div>
                    </div>
                    <div class="m-tree-skeleton-row">
                        <div class="m-skeleton m-tree-skeleton-icon"></div>
                        <div class="m-skeleton m-tree-skeleton-name"></div>
                    </div>
                    <div class="m-tree-skeleton-row">
                        <div class="m-skeleton m-tree-skeleton-icon"></div>
                        <div class="m-skeleton m-tree-skeleton-name"></div>
                    </div>
                    <div class="m-tree-skeleton-row">
                        <div class="m-skeleton m-tree-skeleton-icon"></div>
                        <div class="m-skeleton m-tree-skeleton-name"></div>
                    </div>
                    <div class="m-tree-skeleton-row">
                        <div class="m-skeleton m-tree-skeleton-icon"></div>
                        <div class="m-skeleton m-tree-skeleton-name"></div>
                    </div>
                    <div class="m-tree-skeleton-row">
                        <div class="m-skeleton m-tree-skeleton-icon"></div>
                        <div class="m-skeleton m-tree-skeleton-name"></div>
                    </div>
                    <div class="m-tree-skeleton-row">
                        <div class="m-skeleton m-tree-skeleton-icon"></div>
                        <div class="m-skeleton m-tree-skeleton-name"></div>
                    </div>
                    <div class="m-tree-skeleton-row">
                        <div class="m-skeleton m-tree-skeleton-icon"></div>
                        <div class="m-skeleton m-tree-skeleton-name"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- EDITOR PANEL (Task 3.1) -->
    <div class="m-panel hidden" id="mp-editor">
        <div class="m-panel-head">
            <span class="mph-title">EDITOR</span>
            <span class="mph-sub" id="m-editor-file">No file open</span>
            <span class="m-editor-pos" id="m-editor-pos"></span>
        </div>
        <!-- Editor Action Bar (Task 3.3 + Task 8.8 contextual buttons) -->
        <div class="m-action-bar hidden" id="m-action-bar">
            <button class="mab-btn" id="mab-save" title="Save file">💾</button>
            <?php if (!empty($featFormat)): ?>
                <button class="mab-btn" id="mab-format" title="Format code">✨</button>
            <?php endif; ?>
            <?php if (!empty($featMinify)): ?>
                <button class="mab-btn" id="mab-shrink" title="Shrink / Minify">🗜️</button>
            <?php endif; ?>
            <?php if (!empty($featAutocomplete)): ?>
                <button class="mab-btn mab-ac-toggle" id="mab-ac" title="Auto-suggest while typing" aria-pressed="false">💡</button>
            <?php endif; ?>
            <div class="mab-sep"></div>
            <!-- Task 8.8: Contextual buttons (shown/hidden based on file type) -->
            <button class="mab-btn mab-run hidden" id="mab-run" title="Run in terminal">▶</button>
            <button class="mab-btn mab-preview hidden" id="mab-preview" title="Preview this file">👁</button>
            <div class="mab-sep mab-ctx-sep hidden" id="mab-ctx-sep"></div>
            <button class="mab-btn" id="mab-undo" title="Undo">↶</button>
            <button class="mab-btn" id="mab-redo" title="Redo">↷</button>
            <div class="mab-sep"></div>
            <button class="mab-btn" id="mab-find" title="Find in file">🔍</button>
            <button class="mab-btn mab-diag mab-diag-ok" id="mab-diag" title="No problems — tap for report" aria-label="File diagnostics">📋</button>
            <button class="mab-btn" id="mab-wrap" title="Toggle word wrap" aria-pressed="false">↩</button>
            <div class="mab-sep"></div>
            <button class="mab-btn" id="mab-zoom-out" title="Smaller text">A−</button>
            <button class="mab-btn mab-zoom-chip" id="mab-zoom-reset" title="Text size — tap to reset">16</button>
            <button class="mab-btn" id="mab-zoom-in" title="Larger text">A+</button>
        </div>
        <!-- Tab Bar (Task 3.4) + Overflow Indicators (Task 8.2) -->
        <div class="m-tab-bar-wrap hidden" id="m-tab-bar-wrap">
            <div class="m-tab-bar" id="m-tab-bar"></div>
            <button class="m-tab-overflow m-tab-overflow-left hidden" id="m-tab-overflow-left"
                title="Scroll tabs left" aria-label="More tabs to the left">
                <span class="m-tab-overflow-arrow">‹</span>
                <span class="m-tab-overflow-count" id="m-tab-overflow-left-count">0</span>
            </button>
            <button class="m-tab-overflow m-tab-overflow-right hidden" id="m-tab-overflow-right"
                title="Scroll tabs right" aria-label="More tabs to the right">
                <span class="m-tab-overflow-count" id="m-tab-overflow-right-count">0</span>
                <span class="m-tab-overflow-arrow">›</span>
            </button>
        </div>
        <!-- Find Bar (Task 3.5) -->
        <div class="m-find-bar" id="m-find-bar">
            <input type="text" class="m-find-input" id="m-find-input" placeholder="Find in file…" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false">
            <span class="m-find-count" id="m-find-count">0 / 0</span>
            <button class="m-find-btn" id="m-find-prev" title="Previous match" disabled>↑</button>
            <button class="m-find-btn" id="m-find-next" title="Next match" disabled>↓</button>
            <button class="m-find-btn" id="m-find-close" title="Close (Esc)">✕</button>
        </div>
        <div class="m-panel-body m-editor-body" id="m-editor-body">
            <!-- Empty state: shown when no file is open -->
            <div class="m-editor-empty" id="m-editor-empty">
                <span class="mpp-icon">✏️</span>
                <p class="mpp-text">Tap a file in the Files tab<br>to start editing.</p>
            </div>
            <!-- Editor mount: hidden until a file is opened -->
            <div class="m-editor-wrap hidden" id="m-editor-wrap">
                <div id="m-editor-mount"></div>
                <!-- Skeleton overlay shown while a file is being fetched -->
                <div class="m-editor-skeleton hidden" id="m-editor-skeleton">
                    <div class="m-skeleton m-editor-skeleton-line"></div>
                    <div class="m-skeleton m-editor-skeleton-line"></div>
                    <div class="m-skeleton m-editor-skeleton-line"></div>
                    <div class="m-skeleton m-editor-skeleton-line"></div>
                    <div class="m-skeleton m-editor-skeleton-line"></div>
                    <div class="m-skeleton m-editor-skeleton-line"></div>
                    <div class="m-skeleton m-editor-skeleton-line"></div>
                    <div class="m-skeleton m-editor-skeleton-line"></div>
                    <div class="m-skeleton m-editor-skeleton-line"></div>
                    <div class="m-skeleton m-editor-skeleton-line"></div>
                    <div class="m-skeleton m-editor-skeleton-line"></div>
                    <div class="m-skeleton m-editor-skeleton-line"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- PREVIEW PANEL (Task 4.1) -->
    <?php if (!empty($featPreview)): ?>
        <div class="m-panel hidden" id="mp-preview">
            <div class="m-panel-head">
                <span class="mph-title">PREVIEW</span>
                <span class="mph-sub" id="m-preview-file">—</span>
            </div>
            <!-- Device switcher: Desktop / Tablet / Phone -->
            <div class="m-preview-bar">
                <div class="pv-devices" role="group" aria-label="Preview viewport">
                    <button class="pv-device active" data-device="phone" aria-pressed="true">📲 <span>Phone</span></button>
                    <button class="pv-device" data-device="tablet" aria-pressed="false">📱 <span>Tablet</span></button>
                    <button class="pv-device" data-device="desktop" aria-pressed="false">🖥️ <span>Desktop</span></button>
                </div>
                <!-- ★ PHASE 4: Apache server badge -->
                <span class="mpv-server-badge hidden" id="mpv-server-badge">🌐 Apache</span>
                <span class="pv-size" id="m-pv-size">Full width</span>
                <div class="mpv-zoom-controls">
                    <button class="mpv-zoom-btn" id="mpv-zoom-out" title="Zoom out">−</button>
                    <button class="mpv-zoom-btn mpv-zoom-chip" id="mpv-zoom-reset" title="Zoom level — tap to reset">100%</button>
                    <button class="mpv-zoom-btn" id="mpv-zoom-in" title="Zoom in">+</button>
                </div>
                <!-- Task 4.4: Refresh + Open-in-tab -->
                <button class="mpv-action-btn" id="mpv-refresh" title="Refresh preview">🔄</button>
                <button class="mpv-action-btn" id="mpv-open" title="Open in browser tab">↗</button>
            </div>
            <div class="m-panel-body m-preview-body" id="m-preview-body">
                <!-- Device frame + live preview window -->
                <div class="device-stage" id="m-device-stage">
                    <div class="m-device-wrap" id="m-device-wrap">
                        <div class="device-shell" id="m-device-shell" data-device="desktop">
                            <div class="device-sensor" aria-hidden="true"><i class="device-speaker"></i><i class="device-cam"></i></div>
                            <iframe id="m-preview-frame" title="Live preview"></iframe>
                            <div class="device-home" aria-hidden="true"></div>
                        </div>
                    </div>
                </div>
                <!-- Skeleton/loading overlay shown while preview is rendering -->
                <div class="m-preview-skeleton hidden" id="m-preview-skeleton">
                    <div class="m-preview-skeleton-spinner"></div>
                    <span class="m-preview-skeleton-text">Rendering preview…</span>
                    <div class="m-preview-skeleton-bars">
                        <div class="m-skeleton m-preview-skeleton-bar"></div>
                        <div class="m-skeleton m-preview-skeleton-bar"></div>
                        <div class="m-skeleton m-preview-skeleton-bar"></div>
                        <div class="m-skeleton m-preview-skeleton-bar"></div>
                    </div>
                </div>
                <!-- Friendly message when nothing can be previewed -->
                <div class="m-panel-placeholder" id="m-preview-empty">
                    <span class="mpp-icon">👁️</span>
                    <p class="mpp-text">Open a file in the Editor first —<br>the preview follows your active tab.</p>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- TERMINAL PANEL (Task 4.2) -->
    <?php if (!empty($featTerminal)): ?>
        <div class="m-panel hidden" id="mp-terminal">
            <div class="m-panel-head">
                <span class="mph-title">TERMINAL</span>
                <span class="m-term-cwd-badge" id="m-term-cwd">📂 workspace/</span>
            </div>
            <!-- Terminal toolbar -->
            <div class="m-term-toolbar" id="m-term-toolbar">
                <button class="mterm-btn" id="mterm-clear" title="Clear output">🧹</button>
                <button class="mterm-btn" id="mterm-copy" title="Copy session">⧉</button>
                <button class="mterm-btn mterm-cancel" id="mterm-cancel" title="Cancel running command" style="display:none">✕</button>
                <div class="mterm-spacer"></div>
                <span class="mterm-status" id="mterm-status"></span>
                <!-- Terminal zoom controls (50% – 200%) -->
                <div class="mterm-zoom-controls">
                    <button class="mterm-zoom-btn" id="mterm-zoom-out" title="Zoom out" aria-label="Zoom out terminal text">−</button>
                    <button class="mterm-zoom-btn mterm-zoom-chip" id="mterm-zoom-reset" title="Zoom level — tap to reset" aria-label="Reset terminal zoom">100%</button>
                    <button class="mterm-zoom-btn" id="mterm-zoom-in" title="Zoom in" aria-label="Zoom in terminal text">+</button>
                </div>
            </div>
            <!-- Terminal output (scrollable) -->
            <div class="m-panel-body m-term-output" id="m-term-output">
                <div class="m-term-welcome">Quirky IDE Terminal — commands run inside workspace/ only.</div>
            </div>
            <!-- Terminal input line -->
            <div class="m-term-input-line" id="m-term-input-line">
                <span class="m-term-prompt" id="m-term-prompt">workspace $</span>
                <input type="text" id="m-term-input" placeholder="Type a command…"
                    autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false">
                <button class="mterm-send" id="mterm-send">➤</button>
            </div>
        </div>
    <?php endif; ?>

</main>

<?php
// ── DYNAMIC NAV BAR LOGIC ──
$navItems = [
    ['id' => 'files', 'icon' => '🗂️', 'label' => 'Files', 'type' => 'panel'],
    ['id' => 'editor', 'icon' => '✏️', 'label' => 'Editor', 'type' => 'panel'],
];
if (!empty($featPreview)) $navItems[] = ['id' => 'preview', 'icon' => '👁️', 'label' => 'Preview', 'type' => 'panel'];
if (!empty($featTerminal)) $navItems[] = ['id' => 'terminal', 'icon' => '🖥️', 'label' => 'Terminal', 'type' => 'panel'];

$extras = [
    ['id' => 'settings', 'icon' => '⚙️', 'label' => 'Settings', 'type' => 'action'],
];
if (!empty($featWorkshop)) $extras[] = ['id' => 'workshop', 'icon' => '🔧', 'label' => 'Workshop', 'type' => 'action'];
if (!empty($featAi)) $extras[] = ['id' => 'ai', 'icon' => '🤖', 'label' => 'AI', 'type' => 'action'];
if (!empty($featGit)) $extras[] = ['id' => 'git', 'icon' => '🌿', 'label' => 'Git', 'type' => 'action'];
if (!empty($featHttpClient)) $extras[] = ['id' => 'http', 'icon' => '📡', 'label' => 'HTTP', 'type' => 'action'];

$MAX_NAV = 5;
$visibleNav = $navItems;
$overflowNav = [];

$baseCount = count($navItems);
$extraCount = count($extras);

if ($baseCount + $extraCount > $MAX_NAV) {
    // Need "More" button, so reserve 1 slot for it
    $slotsForExtras = $MAX_NAV - 1 - $baseCount;
    if ($slotsForExtras < 0) $slotsForExtras = 0;
    $visibleNav = array_merge($navItems, array_slice($extras, 0, $slotsForExtras));
    $overflowNav = array_slice($extras, $slotsForExtras);
} else {
    // Everything fits, no "More" button needed
    $visibleNav = array_merge($navItems, $extras);
    $overflowNav = [];
}
?>
<!-- ── BOTTOM TAB BAR ── -->
<nav id="mobile-tabbar">
    <?php foreach ($visibleNav as $item): ?>
        <?php if ($item['type'] === 'panel'): ?>
            <button class="mt-btn<?php echo ($item['id'] === 'files') ? ' active' : ''; ?>" data-panel="<?php echo $item['id']; ?>" aria-label="<?php echo $item['label']; ?>">
                <span class="mt-ico"><?php echo $item['icon']; ?></span>
                <span class="mt-label"><?php echo $item['label']; ?></span>
            </button>
        <?php else: ?>
            <button class="mt-btn" data-action="<?php echo $item['id']; ?>" aria-label="<?php echo $item['label']; ?>">
                <span class="mt-ico"><?php echo $item['icon']; ?></span>
                <span class="mt-label"><?php echo $item['label']; ?></span>
            </button>
        <?php endif; ?>
    <?php endforeach; ?>

    <?php if (!empty($overflowNav)): ?>
        <button class="mt-btn" data-panel="more" aria-label="More options">
            <span class="mt-ico">⋯</span>
            <span class="mt-label">More</span>
        </button>
    <?php endif; ?>
</nav>

<!-- ── FLOATING ACTION BUTTON (Task 2.5) ── -->
<button class="m-fab" id="m-fab" title="New File / Folder / Project" aria-label="Create new item">＋</button>

<!-- ── MULTI-SELECT SELECTION BAR (Task 8.9) ── -->
<div class="m-selection-bar hidden" id="m-selection-bar">
    <button class="msel-btn msel-cancel" id="msel-cancel" title="Exit selection" aria-label="Exit selection mode">✕</button>
    <span class="msel-count" id="msel-count">1 selected</span>
    <div class="msel-spacer"></div>
    <button class="msel-btn msel-more" id="msel-more" title="More actions" aria-label="More actions">⋯</button>
    <button class="msel-btn msel-copy" id="msel-copy" title="Copy" aria-label="Copy selected">📋</button>
    <button class="msel-btn msel-move" id="msel-move" title="Move" aria-label="Move selected">📦</button>
    <button class="msel-btn msel-delete" id="msel-delete" title="Delete" aria-label="Delete selected">🗑️</button>
</div>