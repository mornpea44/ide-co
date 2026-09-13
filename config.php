<?php

declare(strict_types=1);
/**
 * ═══════════════════════════════════════════════════════════════════════════
 *  QUIRKY IDE — CENTRAL CONFIGURATION
 * ═══════════════════════════════════════════════════════════════════════════
 *
 *  This is the ONE file you are expected to edit. Every path, password,
 *  feature toggle, and limit for the IDE lives here, and each setting has a
 *  plain-English comment above it.
 *
 *  The other files (security.php, api.php, index.php, etc.) all READ this
 *  file — you normally never need to touch them.
 *
 *  ⚠️ FIRST THING TO DO: change the password in section 2 below.
 *
 *  Version: 1.0
 * ═══════════════════════════════════════════════════════════════════════════
 */


/* ═══════════════════════════════════════════════════════════════════════
   1. PATHS  (auto-detected — you should NOT need to change these)
   ═══════════════════════════════════════════════════════════════════════
   These are figured out automatically from where this file lives, so the
   IDE keeps working even if you move the whole "quirky" folder.           */

$paths = [
    'ide_root'  => __DIR__,                    // .../private/platforms/ide
    'app'       => __DIR__ . '/app',           // the IDE application code
    'workspace' => __DIR__ . '/workspace',     // 🔒 the ONLY folder the IDE can touch
    'vendor'    => __DIR__ . '/app/vendor',    // downloaded libraries (CodeMirror, js-beautify)
    'assets'    => __DIR__ . '/app/assets',    // the IDE's own CSS & JS
    'views'     => __DIR__ . '/app/views',     // HTML templates
    'services'  => __DIR__ . '/app/services',  // the "engine" (file logic, search, terminal)
];

/* ── Dynamic workspace override (mobile) ──────────────────────────────────
   The Android app can point the workspace at another folder (Explorer →
   "workspace/" chip → Change directory). The chosen folder is persisted in
   app/.workspace-config.json and applied HERE, before the IDE_WORKSPACE
   constant exists, so files, terminal, git and preview all follow it
   automatically. Binaries / pkg packages live in the private prefix and
   are NEVER touched by this.                                              */
if (!function_exists('quirkyWorkspaceOverrideIsValid')) {
    /**
     * Validate a candidate workspace directory: absolute path, creatable,
     * readable + writable, never a system partition, and never the IDE's
     * own code folder (or an ancestor of it).
     */
    function quirkyWorkspaceOverrideIsValid(string $p): bool
    {
        $p = rtrim(str_replace('\\', '/', trim($p)), '/');
        if ($p === '' || $p[0] !== '/') {
            return false;
        }
        foreach (['/system', '/proc', '/sys', '/dev', '/vendor', '/odm', '/product', '/apex', '/data/app'] as $banned) {
            if ($p === $banned || strpos($p, $banned . '/') === 0) {
                return false;
            }
        }
        $appDir = str_replace('\\', '/', (string) realpath(__DIR__));
        if ($appDir !== '') {
            // Must not BE the IDE folder and must not CONTAIN it.
            if ($p === $appDir || strpos($appDir . '/', $p . '/') === 0) {
                return false;
            }
        }
        if (!is_dir($p)) {
            if (!@mkdir($p, 0777, true)) {
                return false;
            }
        }
        $real = realpath($p);
        if ($real === false) {
            return false;
        }
        return is_readable($real) && is_writable($real);
    }
}

$__workspaceOverrideFile = __DIR__ . '/app/.workspace-config.json';
if (is_file($__workspaceOverrideFile)) {
    $__wsOverride = json_decode((string) @file_get_contents($__workspaceOverrideFile), true);
    if (is_array($__wsOverride) && !empty($__wsOverride['path']) && is_string($__wsOverride['path'])) {
        $__wsCandidate = str_replace('\\', '/', (string) $__wsOverride['path']);
        if (quirkyWorkspaceOverrideIsValid($__wsCandidate)) {
            $paths['workspace'] = rtrim($__wsCandidate, '/');
        }
    }
    unset($__wsOverride, $__wsCandidate);
}
unset($__workspaceOverrideFile);

/* ═══════════════════════════════════════════════════════════════════════
   2. SECURITY  (⚠️ change the password!)
   ═══════════════════════════════════════════════════════════════════════ */

$security = [
    // ── Password gate ────────────────────────────────────────────────
    // When enabled, you must type a password to open the IDE. Strongly
    // recommended, because the IDE can write files and (optionally) run
    // commands on your machine.
    'auth_enabled'  => false,

    // 🔑 THE PASSWORD. Change "quirky" to something only you know.
    'auth_password' => 'quirky',

    // How long (in seconds) you stay logged in before the IDE asks for
    // the password again. 8 hours = a full work day.
    'auth_lifetime' => 8 * 60 * 60,

    // ── File safety ──────────────────────────────────────────────────
    // The biggest file (in bytes) the IDE will open or save. This stops
    // your browser from freezing on a huge file. 2 MB is plenty for code.
    'max_file_size' => 2 * 1024 * 1024,

    // The file types the IDE is allowed to create and edit. These are all
    // plain-text formats. Anything not on this list (images, .exe, etc.)
    // is refused — the IDE is a code editor, not a file manager for binaries.
    'allowed_extensions' => [
        // Web
        'php',
        'phtml',
        'html',
        'htm',
        'css',
        'scss',
        'js',
        'mjs',
        'ts',
        'jsx',
        'vue',
        // Data & config
        'json',
        'xml',
        'yml',
        'yaml',
        'ini',
        'conf',
        'env',
        'toml',
        'csv',
        'sql',
        // Docs & text
        'md',
        'markdown',
        'txt',
        'log',
        'logs',               // ← *.logs files
        // Scripts
        'sh',
        'bash',
        'bat',
        'ps1',
        'py',                 // ← Python
        'pyw',                // ← Python (Windows no-console)
        'rb',                 // ← Ruby
        'lua',                // ← Lua
        'pl',                 // ← Perl
        'pm',                 // ← Perl module
        // Compiled / native languages (Phase 8 test kits)
        'c',                  // ← C
        'h',                  // ← C/C++ header
        'cpp',                // ← C++
        'cc',                 // ← C++ (alt)
        'cxx',                // ← C++ (alt)
        'hpp',                // ← C++ header
        'java',               // ← Java
        'rs',                 // ← Rust
        'go',                 // ← Go
        'kt',                 // ← Kotlin
        'kts',                // ← Kotlin script
        'dart',               // ← Dart
        'swift',              // ← Swift
        // Other
        'svg',
        'gitignore',
        'htaccess',
        'editorconfig',
        'setup'
    ],

    // Folder/file names that are NEVER allowed anywhere inside the
    // workspace. This is a second layer of protection (the main layer is
    // the "path jail" in security.php that blocks anything outside the
    // workspace). We block version-control internals so the IDE can't
    // accidentally corrupt a repository.
    'blocked_segments' => ['.git', '.svn', '.hg', '.history'],
];


/* ═══════════════════════════════════════════════════════════════════════
   3. FEATURES  (turn things on / off)
   ═══════════════════════════════════════════════════════════════════════ */

$features = [
    'terminal_enabled' => false,
    'preview_enabled'  => true,
    'format_enabled'   => true,
    'minify_enabled'   => true,
    'autocomplete_enabled' => true,
    'lint_enabled'     => true,
    'git_enabled'      => true,
    'http_client_enabled' => true,
    'ai_enabled'       => true,
    'command_palette_enabled' => true,
    'pkg_enabled'      => true,
    'workshop_enabled' => true,
    // ★ Community Workshop packages (quirky.workshop manifests). Master
    // kill-switch for third-party package code; the Settings→Features
    // toggle and the Workshop panel's Safe mode both respect it.
    'packages_enabled' => true,
];

/* ── Feature overrides saved from the Settings panel ─────────────
   The Settings → Features toggles write app/features.json. Those
   choices override the defaults above so they survive a reload.
   Delete app/features.json to restore the defaults.            */
$featuresOverrideFile = __DIR__ . '/app/features.json';
if (is_file($featuresOverrideFile)) {
    $featureOverrides = json_decode((string) @file_get_contents($featuresOverrideFile), true);
    if (is_array($featureOverrides)) {
        foreach ($featureOverrides as $fk => $fv) {
            if (array_key_exists($fk, $features)) {
                $features[$fk] = (bool) $fv;
            }
        }
    }
}

/* ═══════════════════════════════════════════════════════════════════════
   4. TERMINAL SAFETY  (only used when terminal_enabled = true)
   ═══════════════════════════════════════════════════════════════════════ */

$terminal = [
    // Commands containing any of these fragments are refused outright.
    // This is a safety net for destructive operations a beginner (or a
    // typo) should never run.
    'blocked_commands' => [
        'rm -rf /',
        'rm -rf ~',
        'format c:',
        'del /f /s',
        'deltree',
        'mkfs',
        'dd if=',
        ':(){',
        'shutdown',
        'reboot',
        'halt',
        '> /dev/sda',
        'chmod -r 777 /',
        ':wq!',
    ],

    // How many seconds a command may run before it is stopped. 10s is too
    // short for real scripts (a Python build, a test run), so 60s is the
    // default. Raise it for long tasks; for never-ending programs (a web
    // server, a game loop, a REPL) use your system terminal instead — the
    // web terminal closes stdin, so input() / interactive prompts won't work.
    'timeout' => 180,

    // Where commands run. ALWAYS the workspace — never anywhere else.
    'cwd' => 'workspace',
    // ★ PTY ("real terminal") mode for interactive programs.
    //   'auto' = use the bundled ptyrun binary (ships inside the app,
    //            copied to prefix/bin/ptyrun by MainActivity.java).
    //   'off'  = force plain pipe mode. Use this if compiled programs ever
    //            die with "exit -1" while waiting for input — programs still
    //            receive your typed input (type + Enter) in pipe mode.
    'pty' => 'auto',
];


/* ═══════════════════════════════════════════════════════════════════════
   5. EDITOR DEFAULTS  (your personal comfort settings)
   ═══════════════════════════════════════════════════════════════════════ */

$editor = [
    'theme'        => 'dark',   // 'dark' or 'light'
    'font_size'    => 14,       // editor font size in pixels
    'tab_size'     => 2,        // spaces per tab
    'autosave_ms'  => 800,      // how often the editor auto-saves (milliseconds)
    'default_file' => 'index.php', // name suggested when you create a new file
];


/* ═══════════════════════════════════════════════════════════════════════
   6. LIVE PREVIEW  (which file types can be previewed)
   ═══════════════════════════════════════════════════════════════════════
   'php' is included so you can watch your real PHP projects run. This is
   safe here because the preview is behind the password gate and only ever
   reads from your own workspace on your own machine.                     */

$preview = [
    'previewable' => [
        'html',
        'htm',
        'php',
        'phtml',
        'svg',
        'md',
        'markdown',
        'png',
        'jpg',
        'jpeg',
        'gif',
        'webp',
        'bmp',
        'ico',
        'avif'
    ],
    // These extensions show "Unsupported Format" in the preview
    'unsupported' => [
        'mp4',
        'webm',
        'ogg',
        'mp3',
        'wav',
        'avi',
        'mov',
        'mkv',
        'flac',
        'aac',
        'm4a',
        'wma'
    ],
];

/* ═══════════════════════════════════════════════════════════════════════
6b. AI ASSISTANT  (local LLM via Ollama)
═══════════════════════════════════════════════════════════════════════
The AI assistant talks to a local Ollama instance. No data ever leaves
your machine. The model can read/write files in the workspace using
tools, with a permission system you control.

Permission modes:
  'always_ask'      → every tool call needs your approval
  'ask_destructive' → read-only tools (list, read, search) run freely;
                      write/delete/move tools ask first  (RECOMMENDED)
  'auto_approve'    → everything runs without asking (power-user mode)
═══════════════════════════════════════════════════════════════════════ */
$ai = [
    'enabled'         => true,
    'ollama_url'      => 'http://localhost:11434',
    // ⚠️ RECOMMENDED: Use at least a 7B model for reliable tool calling.
    // The 0.8b model is too small to consistently summarize tool results.
    // Run: ollama pull qwen2.5-coder:7b
    // Then change to: 'model' => 'qwen2.5-coder:7b',
    'model'           => 'qwen2.5-coder:7b',
    // NOTE ON MODEL SIZE: sub-3B models (like this 0.8b default) are fast but
    // struggle with multi-step tool use and coherent answers. For a much smarter
    // assistant, pull a >=7B coder model — e.g. run:  ollama pull qwen2.5-coder:7b
    // — then change the line above to:  'model' => 'qwen2.5-coder:7b',
    'permission_mode' => 'ask_destructive',
    'timeout'         => 0,
    'stream'          => true,
    'max_history'     => 12,
    'temperature'     => 0.2,
    'top_p'           => 0.9,
    'repeat_penalty'  => 1.08,

    // 🚀 INCREASED: Allow up to ~6000 tokens of output (enough for massive files)
    'num_predict'     => 4096,
    // 🚀 INCREASED: Total context window to accommodate the larger output
    'num_ctx'         => 32768,

    'keep_alive'      => '10m',

    // 🚀 INCREASED: Allow the AI to read up to 40,000 characters of a file at once
    'max_file_chars' => 40000,
];

/* ═══════════════════════════════════════════════════════════════════════
6c. WORKSPACE WATCH  (how often the file tree checks for outside changes)
═══════════════════════════════════════════════════════════════════════
The sidebar polls the server to notice files changed by OTHER programs
(a Git pull, another editor, a build script). Each poll scans the whole
workspace, so a smaller number = more disk work. 15000 ms (15 s) is a
gentle default; the frontend also checks instantly when you return to
the tab, so you rarely actually wait this long. The code floors this at
3000 ms so a typo can't hammer the server.                            */
$watch = [
    'interval_ms' => 10000, // 10s = 3× gentler on disk than the old hard-coded 5s
];

/* ═══════════════════════════════════════════════════════════════════════
6d. GIT  (the 🌿 Source Control panel)
═══════════════════════════════════════════════════════════════════════
The panel runs read-only git commands (status / log / diff) inside
workspace/. Normally plain 'git' works because it's on your PATH.
If Windows says git isn't found (Laragon sometimes keeps it in its
own folder), point 'binary' at the full path, e.g.:
'C:/laragon/bin/git/cmd/git.exe'                                 */
$git = [
    'binary' => 'git',
    // ★ Commit identity used by the new write operations (stage/commit).
    // Overridable; the IDE falls back to these when the repo has no
    // user.name / user.email configured.
    'user_name'  => 'Quirky',
    'user_email' => 'dev@quirky.local',
];


/* ═══════════════════════════════════════════════════════════════════════
6e. PKG PACKAGE MANAGER (Phase B — Termux-style installs)
═══════════════════════════════════════════════════════════════════════
'pkg' downloads real programs (busybox, git, ...) from your GitHub
repo "quirky-pkg" and installs them into the app's private toolbox
folder (files/usr). The binaries only RUN on Android — on your
Windows/Laragon desktop you can still browse/search the store.

⚠️ ACTION REQUIRED: replace YOUR_GITHUB_USERNAME below with your
actual GitHub username (the one that owns the quirky-pkg repo).     */
$pkg = [
    'enabled'    => true,
    'repo_base'  => 'https://packages.termux.dev/apt/termux-main',
    'timeout'    => 300,
];

/* ═══════════════════════════════════════════════════════════════════════
6f. WORKSHOP (extension system — Phase 1)
═══════════════════════════════════════════════════════════════════════
The Workshop lets you install optional extensions (like Apache) that
add extra capabilities to the IDE. Extensions are managed through
the More → Workshop menu.                                            */
$workshop = [
    'enabled' => true,
    'apache'  => [
        'port'           => 8082,
        'fallback_ports' => [8083, 8084],
        'auto_start'     => false,
    ],
];

/* ═══════════════════════════════════════════════════════════════════════
   7. META  (name & version — shown in the IDE's title bar / about)
   ═══════════════════════════════════════════════════════════════════════ */

$meta = [
    'name'    => 'Quirky IDE',
    'version' => '1.0',
];


/* ═══════════════════════════════════════════════════════════════════════
   ASSEMBLE & RETURN
   ═══════════════════════════════════════════════════════════════════════
   You don't need to edit below this line.                                */

$config = [
    'paths'    => $paths,
    'security' => $security,
    'features' => $features,
    'terminal' => $terminal,
    'git'      => $git,
    'pkg'      => $pkg,
    'workshop' => $workshop,
    'editor'   => $editor,
    'preview'  => $preview,
    'watch'    => $watch,
    'runners'  => [
        'py'   => 'python "{file}"',
        'pyw'  => 'python "{file}"',
        'js'   => 'node "{file}"',
        'mjs'  => 'node "{file}"',
        'sh'   => 'bash "{file}"',
        'bash' => 'bash "{file}"',
    ],
    'ai'       => $ai,
    'meta'     => $meta,
];

// Define handy constants so other files can write IDE_WORKSPACE instead of
// $config['paths']['workspace']. The "if" guard makes it safe even if this
// file gets loaded more than once.
if (!defined('IDE_ROOT')) {
    define('IDE_ROOT',      $paths['ide_root']);
    define('IDE_APP',       $paths['app']);
    define('IDE_WORKSPACE', $paths['workspace']);
    define('IDE_VENDOR',    $paths['vendor']);
    define('IDE_ASSETS',    $paths['assets']);
    define('IDE_VIEWS',     $paths['views']);
    define('IDE_SERVICES',  $paths['services']);
}

return $config;
