<?php

/**
 * QUIRKY IDE — Mobile Overlays Partial
 * Full-screen overlays: Command Palette, Generic Modal, AI Assistant,
 * Git Panel, Git Diff, HTTP Client. Plus the toast notification element.
 * Variables inherited from mobile-shell.php: $config (for AI feature flag).
 */
/**
 * @var array $config
 */
?>
<!-- ── COMMAND PALETTE OVERLAY (Task 5.5) ── -->
<?php if (!empty($featCommandPalette)): ?>
    <div class="m-cmd-overlay hidden" id="m-cmd-overlay">
        <div class="m-cmd-header">
            <button class="m-cmd-close" id="m-cmd-close" title="Close" aria-label="Close palette">✕</button>
            <span class="m-cmd-title">⌘ Command Palette</span>
        </div>
        <div class="m-cmd-subhead">
            <div class="m-cmd-modes">
                <button class="m-cmd-mode active" data-mode="files">📄 Files</button>
                <button class="m-cmd-mode" data-mode="commands">⚡ Commands</button>
            </div>
        </div>
        <div class="m-cmd-input-wrap">
            <input type="text" class="m-cmd-input" id="m-cmd-input"
                placeholder="Search files…  ( > commands · : go to line )"
                autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false">
        </div>
        <div class="m-cmd-list" id="m-cmd-list"></div>
    </div>
<?php endif; ?>

<!-- ── GENERIC MODAL (for AI edit/delete confirmations) ── -->
<div id="modal-overlay" class="modal-overlay hidden">
    <div class="modal" role="dialog" aria-modal="true">
        <div class="modal-header">
            <h3 id="modal-title">Dialog</h3>
            <button class="modal-x" id="modal-x" title="Close" aria-label="Close dialog">✕</button>
        </div>
        <div class="modal-body" id="modal-body"></div>
        <div class="modal-footer">
            <button class="modal-btn ghost" id="modal-cancel">Cancel</button>
            <button class="modal-btn primary" id="modal-confirm">Confirm</button>
        </div>
    </div>
</div>

<?php if (!empty($featAi)): ?>
    <!-- ── AI ASSISTANT OVERLAY (v10 redesign: workshop-style page · NO drag-to-close ·
     decluttered header = ✕ | title | provider chip | model — the chip itself opens
     the manager sheet, so there is NO separate ⚙ button anymore.
     Provider manager / add-form live in JS-injected .m-sheet layers; the agent
     permission dropdown sits in the composer bar, below the input. ── -->
    <div class="m-ai-overlay hidden" id="m-ai-overlay">
        <div class="m-ai-header">
            <button class="m-ai-close" id="m-ai-close" title="Close" aria-label="Close AI">✕</button>
            <span class="m-ai-title">🤖 AI Assistant</span>
        </div>
        <div class="m-ai-subhead">
            <button type="button" class="ai-provider-chip" id="ai-provider-chip"
                title="AI providers" aria-haspopup="true">
                <span class="ai-chip-ico" id="ai-chip-ico">⚡</span>
                <span class="ai-chip-label" id="ai-chip-label">Provider</span>
                <span class="ai-chip-dot off" id="ai-chip-dot" aria-hidden="true"></span>
            </button>
            <select id="ai-model-select" class="ai-model-select" title="Model">
                <option value="">Loading…</option>
            </select>
        </div>
        <div class="side-body ai-chat-body" id="ai-chat"></div>
        <div class="ai-composer" id="ai-composer">
            <textarea id="ai-input" rows="1" placeholder="Ask me anything…" spellcheck="false" autocomplete="off"></textarea>
            <div class="ai-composer-bar">
                <select id="ai-permission-mode" title="Agent permissions" aria-label="Agent permissions">
                    <option value="always_ask">🔒 Ask always</option>
                    <option value="ask_destructive" selected>🛡 Writes ask</option>
                    <option value="auto_approve">⚡ Auto</option>
                </select>
                <button id="ai-send" title="Send" aria-label="Send message">➤</button>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- ── GIT PANEL OVERLAY (v10 redesign: tabs · staging · branches · commit bar · NO drag-to-close) ── -->
<?php if (!empty($featGit)): ?>
    <div class="m-git-overlay hidden" id="m-git-overlay">
        <div class="m-git-header">
            <button class="m-git-close" id="m-git-close" title="Close" aria-label="Close Git panel">✕</button>
            <span class="m-git-title">🌿 Source Control</span>
        </div>
        <div class="m-git-subhead">
            <button class="m-git-branch" id="m-git-branch" title="Switch / create branch">⎇ …</button>
            <button class="m-git-refresh" id="m-git-refresh" title="Refresh">🔄</button>
            <button class="m-git-more" id="m-git-more" title="More actions">⋯</button>
        </div>
        <div class="m-git-tabs" id="m-git-tabs" role="tablist">
            <button class="m-git-tab active" data-tab="changes" role="tab">Changes <span class="m-git-count zero" id="m-git-changes-count">0</span></button>
            <button class="m-git-tab" data-tab="staged" role="tab">Staged <span class="m-git-count zero" id="m-git-staged-count">0</span></button>
            <button class="m-git-tab" data-tab="history" role="tab">History <span class="m-git-count zero" id="m-git-history-count">0</span></button>
        </div>
        <div class="m-git-merge-banner hidden" id="m-git-merge-banner"></div>
        <div class="m-git-body" id="m-git-body">
            <div class="m-git-tabpane" id="m-git-pane-changes">
                <div class="m-git-list" id="m-git-changes-list">
                    <div class="m-git-empty">Loading…</div>
                </div>
            </div>
            <div class="m-git-tabpane hidden" id="m-git-pane-staged">
                <div class="m-git-list" id="m-git-staged-list">
                    <div class="m-git-empty">Loading…</div>
                </div>
            </div>
            <div class="m-git-tabpane hidden" id="m-git-pane-history">
                <div class="m-git-list" id="m-git-history-list">
                    <div class="m-git-empty">Loading…</div>
                </div>
                <button class="m-git-loadmore hidden" id="m-git-loadmore">↓ Load older commits</button>
            </div>
        </div>
        <div class="m-git-commitbar" id="m-git-commitbar">
            <input type="text" class="m-git-msg" id="m-git-msg" placeholder="Commit message…" autocomplete="off" spellcheck="false">
            <label class="m-git-amend" for="m-git-amend"><input type="checkbox" id="m-git-amend"><span>Amend</span></label>
            <button class="m-git-commit-btn" id="m-git-commit" disabled>✓ Commit</button>
        </div>
    </div>

    <!-- ── GIT DIFF OVERLAY (v10: toolbar with copy + wrap toggle) ── -->
    <div class="m-git-diff-overlay hidden" id="m-git-diff-overlay">
        <div class="m-git-diff-header">
            <button class="m-git-close" id="m-git-diff-close" title="Close" aria-label="Close diff">✕</button>
            <span class="m-git-diff-title" id="m-git-diff-title">Diff</span>
            <button class="m-git-diff-tool" id="m-git-diff-copy" title="Copy diff">📋</button>
            <button class="m-git-diff-tool" id="m-git-diff-wrap" title="Toggle word wrap">↵</button>
        </div>
        <pre class="m-git-diff-body" id="m-git-diff-body">Loading…</pre>
    </div>
<?php endif; ?>

<!-- ── HTTP CLIENT OVERLAY (v10 redesign: sticky builder bar · body modes · auth · quota-safe history · NO drag-to-close, NO autofocus) ── -->
<?php if (!empty($featHttpClient)): ?>
    <div class="m-http-overlay hidden" id="m-http-overlay">
        <div class="m-http-header">
            <button class="m-http-close" id="m-http-close" title="Close" aria-label="Close HTTP Client">✕</button>
            <span class="m-http-title">📡 HTTP Client</span>
        </div>
        <div class="m-http-subhead">
            <button class="m-http-history-btn" id="m-http-history-btn" title="History">🕘 History</button>
        </div>
        <!-- Sticky builder bar: always visible above the scrolling sections -->
        <div class="m-http-sendbar">
            <select class="m-http-method" id="m-http-method" aria-label="HTTP method">
                <option value="GET">GET</option>
                <option value="POST">POST</option>
                <option value="PUT">PUT</option>
                <option value="PATCH">PATCH</option>
                <option value="DELETE">DELETE</option>
                <option value="HEAD">HEAD</option>
                <option value="OPTIONS">OPTIONS</option>
            </select>
            <input type="text" class="m-http-url-input" id="m-http-url" placeholder="https://api.example.com/endpoint"
                autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" inputmode="url">
            <button class="m-http-send" id="m-http-send">Send</button>
        </div>
        <!-- In-flight meta row: timeout countdown while sending, empty when idle -->
        <div class="m-http-meta-row" id="m-http-meta-row" aria-live="polite"></div>

        <!-- Builder + response share one scroll region so the response
             renders BELOW the request sections and can auto-scroll in -->
        <div class="m-http-body" id="m-http-body">
            <section class="m-http-sec">
                <button class="m-http-sec-head" id="m-http-toggle-headers" aria-expanded="false">
                    <span>Headers</span>
                    <span class="m-http-sec-side">
                        <span class="m-http-count zero" id="m-http-header-count">0</span>
                        <span class="m-http-arrow">▸</span>
                    </span>
                </button>
                <div class="m-http-sec-content hidden" id="m-http-headers-section">
                    <div class="m-http-headers-list" id="m-http-headers-list"></div>
                    <button class="m-http-add-header" id="m-http-add-header">+ Add Header</button>
                </div>
            </section>

            <section class="m-http-sec">
                <button class="m-http-sec-head" id="m-http-toggle-body" aria-expanded="false">
                    <span>Body</span>
                    <span class="m-http-sec-side"><span class="m-http-arrow">▸</span></span>
                </button>
                <div class="m-http-sec-content hidden" id="m-http-body-section">
                    <div class="m-http-seg" id="m-http-body-modes" role="tablist" aria-label="Body mode">
                        <button type="button" data-bmode="json" class="active">JSON</button>
                        <button type="button" data-bmode="form">Form</button>
                        <button type="button" data-bmode="raw">Raw</button>
                    </div>
                    <div class="m-http-bpane" id="m-http-pane-json">
                        <textarea class="m-http-textarea" id="m-http-json-text" placeholder='{"key": "value"}' spellcheck="false"></textarea>
                        <div class="m-http-btnrow">
                            <button type="button" class="m-http-minibtn" id="m-http-json-validate">✅ Validate</button>
                            <button type="button" class="m-http-minibtn" id="m-http-json-beautify">✨ Beautify</button>
                        </div>
                    </div>
                    <div class="m-http-bpane hidden" id="m-http-pane-form">
                        <div class="m-http-headers-list" id="m-http-form-list"></div>
                        <button class="m-http-add-header" id="m-http-add-field">+ Add Field</button>
                    </div>
                    <div class="m-http-bpane hidden" id="m-http-pane-raw">
                        <textarea class="m-http-textarea" id="m-http-raw-text" placeholder="raw payload…" spellcheck="false"></textarea>
                    </div>
                </div>
            </section>

            <section class="m-http-sec">
                <button class="m-http-sec-head" id="m-http-toggle-auth" aria-expanded="false">
                    <span>Auth</span>
                    <span class="m-http-sec-side">
                        <span class="m-http-auth-hint" id="m-http-auth-hint">None</span>
                        <span class="m-http-arrow">▸</span>
                    </span>
                </button>
                <div class="m-http-sec-content hidden" id="m-http-auth-section">
                    <select class="m-http-auth-select" id="m-http-auth-type" aria-label="Auth type">
                        <option value="none">No Auth</option>
                        <option value="bearer">Bearer Token</option>
                        <option value="basic">Basic (user / password)</option>
                    </select>
                    <div class="m-http-auth-row hidden" id="m-http-bearer-row">
                        <input type="text" id="m-http-bearer" placeholder="Bearer token" autocomplete="off" spellcheck="false">
                    </div>
                    <div class="m-http-auth-row hidden" id="m-http-basic-row">
                        <input type="text" id="m-http-basic-user" placeholder="username" autocomplete="off" autocapitalize="off" spellcheck="false">
                        <input type="password" id="m-http-basic-pass" placeholder="password" autocomplete="new-password">
                    </div>
                    <p class="m-http-auth-note">The Authorization header is composed at send time and never stored with the request.</p>
                </div>
            </section>

            <div class="m-http-response hidden" id="m-http-response">
                <div class="m-http-resp-top">
                    <span class="m-http-status err" id="m-http-resp-status">—</span>
                    <span class="m-http-chip" id="m-http-resp-time"></span>
                    <span class="m-http-chip" id="m-http-resp-size"></span>
                    <span class="m-http-chip hidden" id="m-http-resp-redir"></span>
                </div>
                <button class="m-http-collapse hidden" id="m-http-toggle-resp-headers">Headers ▾</button>
                <div class="m-http-resp-headers hidden" id="m-http-resp-headers"></div>
                <div class="m-http-tabs hidden" id="m-http-resp-tabs" role="tablist" aria-label="Response body view">
                    <button type="button" data-vt="pretty" class="active">Pretty</button>
                    <button type="button" data-vt="raw">Raw</button>
                    <button type="button" data-vt="copy">📋 Copy</button>
                </div>
                <div class="m-http-resp-body" id="m-http-resp-body"></div>
            </div>
        </div>

        <!-- History view -->
        <div class="m-http-history hidden" id="m-http-history">
            <div class="m-http-history-top">
                <input type="text" class="m-http-filter" id="m-http-history-filter"
                    placeholder="Filter by URL or method…" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false">
                <button class="m-http-history-clear" id="m-http-history-clear" title="Clear all history">🗑</button>
            </div>
            <div class="m-http-history-list" id="m-http-history-list"></div>
        </div>
    </div>
<?php endif; ?>

<?php if (!empty($featWorkshop)): ?>
    <!-- ── WORKSHOP OVERLAY (Phase 1) ── -->
    <?php require __DIR__ . '/_workshop.php'; ?>

    <!-- ── WORKSHOP LOGS OVERLAY ── -->
    <div class="m-workshop-logs-overlay hidden" id="m-workshop-logs-overlay">
        <div class="m-workshop-header">
            <button class="m-workshop-close" id="m-workshop-logs-close" title="Close" aria-label="Close Workshop Logs">✕</button>
            <span class="m-workshop-title">📋 Workshop Logs</span>
        </div>
        <div class="m-workshop-logs-toolbar">
            <button class="m-workshop-ext-btn" id="m-workshop-logs-refresh">🔄 Refresh</button>
            <button class="m-workshop-ext-btn" id="m-workshop-logs-copy">📋 Copy All</button>
            <button class="m-workshop-ext-btn m-workshop-ext-btn-danger" id="m-workshop-logs-clear">🗑 Clear</button>
            <span class="m-workshop-subtitle" id="m-workshop-logs-count">0 entries</span>
        </div>
        <div class="m-workshop-logs-body" id="m-workshop-logs-body">
            <div class="m-workshop-loading">
                <div class="m-workshop-spinner"></div>
                <span>Loading logs…</span>
            </div>
        </div>
    </div>
    <!-- ── WORKSHOP DIAGNOSTICS OVERLAY ── -->
    <div class="m-workshop-logs-overlay hidden" id="m-workshop-diag-overlay">
        <div class="m-workshop-header">
            <button class="m-workshop-close" id="m-workshop-diag-close" title="Close" aria-label="Close Diagnostics">✕</button>
            <span class="m-workshop-title">🩺 Apache Diagnostics</span>
        </div>
        <div class="m-workshop-logs-toolbar">
            <button class="m-workshop-ext-btn" id="m-workshop-diag-refresh">🔄 Run</button>
            <button class="m-workshop-ext-btn" id="m-workshop-diag-copy">📋 Copy</button>
            <button class="m-workshop-ext-btn m-workshop-ext-btn-danger" id="m-workshop-diag-clear">🗑 Clear</button>
            <span class="m-workshop-subtitle" id="m-workshop-diag-count">—</span>
        </div>
        <div class="m-workshop-logs-body" id="m-workshop-diag-body">
            <div class="m-workshop-empty">
                <span class="m-workshop-empty-icon">🩺</span>
                <p>Tap "🔄 Run" to check your Apache installation.</p>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Toast notifications -->
<div id="toast" class="toast" role="status" aria-live="polite" aria-atomic="true"></div>