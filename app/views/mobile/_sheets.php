<?php

/**
 * QUIRKY IDE — Mobile Bottom Sheets Partial
 * All bottom sheets: More menu, Overflow menu, File Actions, Rename,
 * Delete, Paste Conflict, New Item, New File, New Folder, New Project.
 * No feature flags needed — sheets are always present in the DOM.
 */
?>
<!-- ── BOTTOM SHEET: "More" tab menu (Dynamic Overflow) ── -->
<div class="m-sheet-backdrop hidden" id="ms-backdrop"></div>
<div class="m-sheet hidden" id="ms-more">
    <div class="m-sheet-handle"></div>
    <div class="m-sheet-title">MORE OPTIONS</div>
    <?php if (empty($overflowNav)): ?>
        <div class="m-sheet-row" style="opacity:0.5;pointer-events:none;">No extra options</div>
    <?php else: ?>
        <?php foreach ($overflowNav as $item): ?>
            <button class="m-sheet-row" data-action="<?php echo $item['id']; ?>">
                <span class="sr-ico"><?php echo $item['icon']; ?></span> <?php echo $item['label']; ?>
            </button>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- ── BOTTOM SHEET: File Actions (Task 2.2) ── -->
<div class="m-sheet-backdrop hidden" id="fa-backdrop"></div>
<div class="m-sheet hidden" id="ms-file-actions">
    <div class="m-sheet-handle"></div>
    <div class="m-sheet-title" id="fa-title">filename.php</div>
    <button class="m-sheet-row" data-action="open">
        <span class="sr-ico">📂</span> <span class="sr-label">Open in Editor</span>
    </button>
    <!-- ★ NEW: Create inside folder (only shown when target is a folder) -->
    <button class="m-sheet-row hidden" data-action="new-file-in" id="fa-new-file-in">
        <span class="sr-ico">📄</span> <span class="sr-label">New File Inside</span>
    </button>
    <button class="m-sheet-row hidden" data-action="new-folder-in" id="fa-new-folder-in">
        <span class="sr-ico">📁</span> <span class="sr-label">New Folder Inside</span>
    </button>
    <!-- ★ END NEW -->
    <button class="m-sheet-row" data-action="rename">
        <span class="sr-ico">✏️</span> <span class="sr-label">Rename</span>
    </button>
    <button class="m-sheet-row" data-action="copy">
        <span class="sr-ico">📋</span> <span class="sr-label">Copy</span>
    </button>
    <button class="m-sheet-row" data-action="cut">
        <span class="sr-ico">✂️</span> <span class="sr-label">Cut</span>
    </button>
    <button class="m-sheet-row" data-action="paste" id="fa-paste">
        <span class="sr-ico">📌</span> <span class="sr-label">Paste</span>
    </button>
    <button class="m-sheet-row" data-action="duplicate">
        <span class="sr-ico">📑</span> <span class="sr-label">Duplicate</span>
    </button>
    <button class="m-sheet-row" data-action="export">
        <span class="sr-ico">📤</span> <span class="sr-label">Export</span>
    </button>
    <button class="m-sheet-row" data-action="info">
        <span class="sr-ico">ℹ️</span> <span class="sr-label">Properties</span>
    </button>
    <div class="m-sheet-sep"></div>
    <button class="m-sheet-row danger" data-action="delete">
        <span class="sr-ico">🗑️</span> <span class="sr-label">Delete</span>
    </button>
</div>

<!-- ── BOTTOM SHEET: Rename Input (Task 2.2) ── -->
<div class="m-sheet-backdrop hidden" id="rn-backdrop"></div>
<div class="m-sheet hidden" id="ms-rename">
    <div class="m-sheet-handle"></div>
    <div class="m-sheet-title">RENAME</div>
    <div class="m-sheet-input-wrap">
        <input type="text" id="rn-input" class="m-sheet-input" placeholder="New name" autocomplete="off" spellcheck="false">
    </div>
    <button class="m-sheet-row" id="rn-confirm">
        <span class="sr-ico">✓</span> <span class="sr-label">Rename</span>
    </button>
    <button class="m-sheet-row" id="rn-cancel">
        <span class="sr-ico">✕</span> <span class="sr-label">Cancel</span>
    </button>
</div>

<!-- ── BOTTOM SHEET: Delete Confirmation (Task 2.2) ── -->
<div class="m-sheet-backdrop hidden" id="dl-backdrop"></div>
<div class="m-sheet hidden" id="ms-delete">
    <div class="m-sheet-handle"></div>
    <div class="m-sheet-title">⚠️ DELETE</div>
    <div class="m-sheet-msg" id="dl-msg">Are you sure you want to delete this item?</div>
    <button class="m-sheet-row danger" id="dl-confirm">
        <span class="sr-ico">🗑️</span> <span class="sr-label">Delete Permanently</span>
    </button>
    <button class="m-sheet-row" id="dl-cancel">
        <span class="sr-ico">✕</span> <span class="sr-label">Cancel</span>
    </button>
</div>

<!-- ── BOTTOM SHEET: Name Conflict (paste / move / drag) ── -->
<div class="m-sheet-backdrop hidden" id="cf-backdrop"></div>
<div class="m-sheet hidden" id="ms-conflict">
    <div class="m-sheet-handle"></div>
    <div class="m-sheet-title">⚠️ NAME CONFLICT</div>
    <div class="m-sheet-msg" id="cf-msg">"file" already exists at the destination.</div>
    <div class="m-sheet-hint">Overwrite = replace it · Rename = keep both, numbered · Skip = just this item · Discard = cancel everything</div>
    <!-- Batch toggle: shown only when more items follow in the same paste/move -->
    <button class="m-sheet-row m-cf-bulk hidden" id="cf-bulk-toggle">
        <span class="sr-ico" id="cf-bulk-check">☐</span>
        <span class="sr-label">Apply this choice to the remaining <b id="cf-bulk-count">0</b> item(s)</span>
    </button>
    <button class="m-sheet-row danger" id="cf-overwrite">
        <span class="sr-ico">♻️</span> <span class="sr-label">Overwrite</span>
    </button>
    <button class="m-sheet-row" id="cf-rename">
        <span class="sr-ico">✏️</span> <span class="sr-label">Rename (keep both)</span>
    </button>
    <button class="m-sheet-row" id="cf-skip">
        <span class="sr-ico">⏭️</span> <span class="sr-label">Skip this item</span>
    </button>
    <button class="m-sheet-row" id="cf-discard">
        <span class="sr-ico">✕</span> <span class="sr-label">Discard (cancel everything)</span>
    </button>
</div>

<!-- ── BOTTOM SHEET: New Item Menu (Task 2.5) ── -->
<div class="m-sheet-backdrop hidden" id="ni-backdrop"></div>
<div class="m-sheet hidden" id="ms-new-item">
    <div class="m-sheet-handle"></div>
    <div class="m-sheet-title">CREATE &amp; IMPORT</div>
    <button class="m-sheet-row" data-action="new-file">
        <span class="sr-ico">📄</span> <span class="sr-label">New File</span>
    </button>
    <button class="m-sheet-row" data-action="new-folder">
        <span class="sr-ico">📁</span> <span class="sr-label">New Folder</span>
    </button>
    <button class="m-sheet-row" data-action="new-project">
        <span class="sr-ico">🚀</span> <span class="sr-label">New Project</span>
    </button>
    <div class="m-sheet-sep"></div>
    <div class="m-sheet-hint" id="ni-storage-hint" style="display:none;padding:4px 16px;font-size:.72rem;color:var(--muted,#7186a5);">
        🔒 Import &amp; Export require storage permission
    </div>
    <button class="m-sheet-row" data-action="import-file">
        <span class="sr-ico">📥</span> <span class="sr-label">Import File…</span>
    </button>
    <button class="m-sheet-row" data-action="import-folder">
        <span class="sr-ico">📥</span> <span class="sr-label">Import Folder…</span>
    </button>
</div>

<!-- ── BOTTOM SHEET: New File Input (Task 2.5) ── -->
<div class="m-sheet-backdrop hidden" id="nf-backdrop"></div>
<div class="m-sheet hidden" id="ms-new-file">
    <div class="m-sheet-handle"></div>
    <div class="m-sheet-title">📄 NEW FILE</div>
    <!-- ★ NEW: hint showing where the file will be created -->
    <div class="m-sheet-hint" id="nf-path-hint" style="display:none"></div>
    <div class="m-sheet-input-wrap">
        <input type="text" id="nf-input" class="m-sheet-input" placeholder="e.g. project/index.php" autocomplete="off" spellcheck="false">
    </div>
    <div class="m-sheet-hint">Path is relative to workspace/. Folders are created automatically.</div>
    <button class="m-sheet-row" id="nf-confirm">
        <span class="sr-ico">✓</span> <span class="sr-label">Create File</span>
    </button>
    <button class="m-sheet-row" id="nf-cancel">
        <span class="sr-ico">✕</span> <span class="sr-label">Cancel</span>
    </button>
</div>

<!-- ── BOTTOM SHEET: New Folder Input (Task 2.5) ── -->
<div class="m-sheet-backdrop hidden" id="nfo-backdrop"></div>
<div class="m-sheet hidden" id="ms-new-folder">
    <div class="m-sheet-handle"></div>
    <div class="m-sheet-title">📁 NEW FOLDER</div>
    <!-- ★ NEW: hint showing where the folder will be created -->
    <div class="m-sheet-hint" id="nfo-path-hint" style="display:none"></div>
    <div class="m-sheet-input-wrap">
        <input type="text" id="nfo-input" class="m-sheet-input" placeholder="e.g. src/components" autocomplete="off" spellcheck="false">
    </div>
    <div class="m-sheet-hint">Path is relative to workspace/. Parent folders are created automatically.</div>
    <button class="m-sheet-row" id="nfo-confirm">
        <span class="sr-ico">✓</span> <span class="sr-label">Create Folder</span>
    </button>
    <button class="m-sheet-row" id="nfo-cancel">
        <span class="sr-ico">✕</span> <span class="sr-label">Cancel</span>
    </button>
</div>

<!-- ── BOTTOM SHEET: New Project (Task 2.5) ── -->
<div class="m-sheet-backdrop hidden" id="np-backdrop"></div>
<div class="m-sheet hidden" id="ms-new-project">
    <div class="m-sheet-handle"></div>
    <div class="m-sheet-title">🚀 NEW PROJECT</div>
    <div class="m-sheet-msg" id="np-templates-loading">Loading templates…</div>
    <div id="np-templates" class="m-template-list hidden"></div>
    <div class="m-sheet-input-wrap hidden" id="np-name-wrap">
        <input type="text" id="np-name-input" class="m-sheet-input" placeholder="Project name (e.g. my-website)" autocomplete="off" spellcheck="false">
    </div>
    <button class="m-sheet-row hidden" id="np-confirm">
        <span class="sr-ico">✓</span> <span class="sr-label">Create Project</span>
    </button>
    <button class="m-sheet-row" id="np-cancel">
        <span class="sr-ico">✕</span> <span class="sr-label">Cancel</span>
    </button>
</div>

<!-- ── BOTTOM SHEET: Properties / Info ── -->
<div class="m-sheet-backdrop hidden" id="pr-backdrop"></div>
<div class="m-sheet hidden" id="ms-properties">
    <div class="m-sheet-handle"></div>
    <div class="m-props-head">
        <span class="m-props-icon" id="pr-icon">📄</span>
        <div class="m-props-titlewrap">
            <div class="m-sheet-title m-props-name" id="pr-name">filename.php</div>
            <div class="m-props-sub" id="pr-sub">Loading…</div>
        </div>
        <button class="m-props-close" id="pr-close" title="Close" aria-label="Close properties">✕</button>
    </div>
    <div class="m-props-tabs">
        <button class="m-props-tab active" data-view="basic">Basic</button>
        <button class="m-props-tab" data-view="advanced">Advanced</button>
    </div>
    <div class="m-props-body">
        <div class="m-props-view" id="pr-view-basic"></div>
        <div class="m-props-view hidden" id="pr-view-advanced"></div>
    </div>
</div>