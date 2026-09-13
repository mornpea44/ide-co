<?php

declare(strict_types=1);
/**
 * ═══════════════════════════════════════════════════════════════════════════
 *  QUIRKY IDE — API DISPATCHER (Phase 3 · split from the monolith)
 * ═══════════════════════════════════════════════════════════════════════════
 *
 *  The thin "traffic cop" that every IDE request passes through.
 *
 *  Its ONLY jobs:
 *    1. Bootstrap — load config, spin up the engines (security, fs, search…)
 *    2. Parse the request — read ?api=, the HTTP method, and the JSON body
 *    3. Auth gate — reject unauthenticated requests (except login/check/logout)
 *    4. Dispatch — hand the action to the correct route group file
 *
 *  The actual endpoint logic lives in app/routes/:
 *    files.php    → tree, file CRUD, copy, rename, folder, watch
 *    ai.php       → all AI / NVIDIA endpoints
 *    preview.php  → preview-render, preview-live
 *    tools.php    → search, terminal, features, lint, format, minify
 *
 *  Each route file is `require`d below and inherits every variable this
 *  file has set up ($config, $security, $fs, $search, $terminal, $action,
 *  $method, $input). No framework, no autoloading magic — just PHP.
 *
 * ═══════════════════════════════════════════════════════════════════════════
 */

/* ═══════════════════════════════════════════════════════════════════════
   1. BOOTSTRAP
   ═══════════════════════════════════════════════════════════════════════ */

$config = require __DIR__ . '/../config.php';
/* ★ AUDIT FIX: config.mobile.php + saved feature choices used to be
applied only in the shell (index.php) — API requests silently ran on
desktop defaults. Merge them here too, in the same order. */
$mobileConfigFile = __DIR__ . '/../config.mobile.php';
if (is_file($mobileConfigFile)) {
    $mobileOverrides = require $mobileConfigFile;
    if (is_array($mobileOverrides)) {
        foreach ($mobileOverrides as $key => $value) {
            if (is_array($value) && isset($config[$key]) && is_array($config[$key])) {
                $config[$key] = array_merge($config[$key], $value);
            } else {
                $config[$key] = $value;
            }
        }
    }
}
$quirkySavedFeatureOverrides = [];

$featuresOverrideFile = __DIR__ . '/features.json';
if (is_file($featuresOverrideFile)) {
    $featureOverrides = json_decode((string) @file_get_contents($featuresOverrideFile), true);
    if (is_array($featureOverrides)) {
        foreach ($featureOverrides as $fk => $fv) {
            $quirkySavedFeatureOverrides[] = (string) $fk;

            if (array_key_exists($fk, $config['features'])) {
                $config['features'][$fk] = (bool) $fv;
            }
        }

        // Compatibility: watch_interval_ms is persisted in features.json,
        // but it is not a normal boolean feature flag.
        if (isset($featureOverrides['watch_interval_ms'])) {
            $watchMs = max(3000, min((int) $featureOverrides['watch_interval_ms'], 999999));
            $config['features']['watch_interval_ms'] = $watchMs;
            $config['watch']['interval_ms'] = $watchMs;
        }
    }
}

// ★ Hardware-based file limit (same as the shell).
require_once __DIR__ . '/mobile-limits.php';
quirkyMobileApplyDeviceLimit($config);

// ★ Device tier policy engine: apply optimization/stabilization rules.
quirkyMobileApplyDeviceTierPolicy($config, $quirkySavedFeatureOverrides);
spl_autoload_register(function (string $class): void {
    $classMap = [
        'IdeSecurity'            => __DIR__ . '/security.php',
        'IdeFileSystemInterface' => __DIR__ . '/services/Interfaces.php',
        'IdeSearchInterface'     => __DIR__ . '/services/Interfaces.php',
        'IdeTerminalInterface'   => __DIR__ . '/services/Interfaces.php',
        'IdePhpToolsInterface'   => __DIR__ . '/services/Interfaces.php',
        'IdeFileSystem'          => __DIR__ . '/services/FileSystem.php',
        'IdeMobileWorkspace'     => __DIR__ . '/services/MobileWorkspace.php',
        'IdeSearch'              => __DIR__ . '/services/Search.php',
        'IdeTerminal'            => __DIR__ . '/services/Terminal.php',
        'IdeAI'                  => __DIR__ . '/services/AI.php',
        'AiToolExecutor'         => __DIR__ . '/services/AiToolExecutor.php',
        'AiHistory'              => __DIR__ . '/services/AiHistory.php',
        'NvidiaAI'               => __DIR__ . '/services/NvidiaAI.php',
        'IdePhpTools'            => __DIR__ . '/services/PhpTools.php',
        'IdeSymbols'             => __DIR__ . '/services/Symbols.php',
        'AiProvider'             => __DIR__ . '/services/AiProvider.php',
        'AiAdapterBase'          => __DIR__ . '/services/AiProvider.php',
        'AiAdapterOpenAI'        => __DIR__ . '/services/AiAdapterOpenAI.php',
        'AiAdapterOllama'        => __DIR__ . '/services/AiAdapterOllama.php',
        'AiAdapterAnthropic'     => __DIR__ . '/services/AiAdapterAnthropic.php',
        'AiAdapterGemini'        => __DIR__ . '/services/AiAdapterGemini.php',
        'AiProviders'            => __DIR__ . '/services/AiProviders.php',
        'IdePreview'             => __DIR__ . '/services/Preview.php',
        'IdeGit'                 => __DIR__ . '/services/Git.php',
        'IdeTemplates'           => __DIR__ . '/services/Templates.php',
        'IdeWorkshop'            => __DIR__ . '/services/Workshop.php',
        'IdeWorkshopLogger'      => __DIR__ . '/services/WorkshopLogger.php',
        'IdeSpeech'              => __DIR__ . '/services/Speech.php',
        'IdeSnippets'            => __DIR__ . '/services/Snippets.php',
    ];
    if (isset($classMap[$class]) && is_file($classMap[$class])) {
        require_once $classMap[$class];
    }
});

$security = new IdeSecurity($config);
$fs       = new IdeMobileWorkspace($config, $security);
$search   = new IdeSearch($config, $security);
$terminal = new IdeTerminal($config, $security);

/* ═══════════════════════════════════════════════════════════════════════
   CORS — Allow the WebView (port 8080) to talk to the stream server
   (port 8081). Without these headers the browser blocks every
   cross-port fetch, which is what causes "Failed to fetch" in the
   terminal and AI streaming.
═══════════════════════════════════════════════════════════════════════ */
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
// ★ SECURITY FIX: only echo an allowlisted loopback origin. The old code
// reflected ANY Origin with credentials:true, letting any website opened in
// any browser call this local API with the user's session cookie.
$allowedOrigins = [
    'http://127.0.0.1:8080',
    'http://127.0.0.1:8081',
    'http://localhost:8080',
    'http://localhost:8081',
];
if ($origin !== '' && (in_array($origin, $allowedOrigins, true) || preg_match('#^https?://(127\.0\.0\.1|localhost)(:\d+)?$#', $origin))) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Accept, X-Quirky-Platform');
    header('Access-Control-Max-Age: 86400');
}

// Handle the browser's automatic "preflight" OPTIONS request.
// This arrives BEFORE the real POST. We just say "yes, allowed" and stop.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}
/* ═══════════════════════════════════════════════════════════════════════
   2. REQUEST CONTEXT
   ═══════════════════════════════════════════════════════════════════════ */

$action = trim((string) ($_GET['api'] ?? ''));
$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

$input = [];
if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
    $raw = file_get_contents('php://input');
    if ($raw !== false && $raw !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $input = $decoded;
        }
    }
}

/* ═══════════════════════════════════════════════════════════════════════
3. AUTH GATE
═══════════════════════════════════════════════════════════════════════ */
$publicActions = ['auth', 'auth-check', 'logout'];
if ($action !== '' && !in_array($action, $publicActions, true)) {
    $security->requireAuth();
}

// ★ HOTFIX (Concurrency): Release the session lock immediately.
// PHP locks the session file when session_start() is called. If we
// keep it open during long-running tasks (terminal commands, AI streams,
// package installs), ALL other API requests (opening files, tree polling)
// will block at the OS level waiting for the lock. Closing it here
// allows the frontend to make truly concurrent requests.
// (Routes that need to write to the session, like AI/Mobile tokens,
// explicitly call $security->startSession() when they need it).
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

/* ═══════════════════════════════════════════════════════════════════════
4. DISPATCH
═══════════════════════════════════════════════════════════════════════
Auth endpoints stay inline (they're tiny and fundamental).
Everything else is routed to its group file.                    */

try {
    switch ($action) {

        /* ── AUTHENTICATION (inline — 3 tiny cases) ─────────────── */
        case 'auth':
            if ($method !== 'POST') {
                $security->fail('Use POST to log in.', 405);
            }
            $password = (string) ($input['password'] ?? '');
            if ($security->login($password)) {
                $security->respond(['authenticated' => true]);
            }
            $security->fail('Incorrect password.', 401);
            break;

        case 'auth-check':
            $security->respond(['authenticated' => $security->isAuthenticated()]);
            break;

        case 'logout':
            $security->logout();
            $security->respond(['authenticated' => false]);
            break;

        /* ── ROUTE GROUPS ───────────────────────────────────────── */
        default:
            $routeMap = [
                'files'    => [
                    'tree',
                    'file',
                    'file-info',
                    'copy',
                    'rename',
                    'folder',
                    'watch',
                    'files-batch',
                    // ★ Explorer Upgrade: export/import + dynamic workspace
                    'files-export-token',
                    'files-import-path',
                    'workspace-info',
                    'workspace-presets',
                    'workspace-switch',
                ],
                'ai'       => [
                    'ai-status',
                    'ai-chat',
                    'ai-approve',
                    'ai-clear',
                    'ai-permission',
                    'ai-model',
                    'ai-nvidia-status',
                    'ai-nvidia-save-key',
                    'ai-nvidia-clear-key',
                    'ai-nvidia-models',
                    'ai-provider',
                    'ai-provider-status',
                    'ai-stream',
                    'ai-approve-stream',
                    'ai-regenerate',
                    'ai-regenerate-json',
                    'ai-truncate',
                    'ai-nvidia-chat',
                    'ai-nvidia-stream',
                    'ai-providers-list',
                    'ai-providers-save',
                    'ai-providers-delete',
                    'ai-provider-test',
                    'ai-models',
                    'ai-history'
                ],
                'preview'  => ['preview-render'],
                'speech' => [
                    'speech-catalog',
                    'speech-install',
                    'speech-uninstall',
                    'speech-community-refresh',
                    'speech-import-local',
                ],
                'tools'    => [
                    'search',
                    'search-replace',
                    'search-stats',
                    'lint',
                    'symbols-index',
                    'snippets-list',
                    'snippets-save',
                    'snippets-delete',
                    'snippets-import',
                    'snippets-export',
                    'snippets-coverage',
                    'terminal',
                    'terminal-info',
                    'terminal-cancel',
                    'terminal-stream',
                    'terminal-stdin',
                    'terminal-resize',
                    'features',
                    'features-save',
                    'format',
                    'minify',
                    'git-info',
                    'git-status',
                    'git-log',
                    'git-diff',
                    'git-show',
                    'git-stage',
                    'git-unstage',
                    'git-commit',
                    'git-discard',
                    'git-init',
                    'git-branch-list',
                    'git-branch-create',
                    'git-branch-switch',
                    'git-pull',
                    'git-push',
                    'templates-list',
                    'templates-create',
                    'http-proxy'
                ],
                'mobile'   => [
                    'mobile.status',
                    'mobile.refresh-token',
                    'mobile.photo-upload',
                    'mobile.deep-link',
                    'mobile.device-tier',
                    // ★ v13: Storage & Cache card (Settings)
                    'mobile.storage-scan',
                    'mobile.storage-clean'
                ],
                'workshop' => [
                    'workshop-list',
                    'workshop-install',
                    'workshop-uninstall',
                    'workshop-toggle',
                    'workshop-status',
                    'workshop-update',
                    'workshop-catalog',
                    'workshop-asset',
                    'workshop-apache-install',
                    'workshop-apache-uninstall',
                    'workshop-apache-start',
                    'workshop-apache-stop',
                    'workshop-apache-restart',
                    'workshop-apache-status',
                    'workshop-apache-diagnose',
                    'workshop-logs',
                    'workshop-logs-clear'
                ],
            ];

            $matched = false;
            foreach ($routeMap as $group => $actions) {
                if (in_array($action, $actions, true)) {
                    require __DIR__ . '/routes/' . $group . '.php';
                    $matched = true;
                    break;
                }
            }

            if (!$matched) {
                if ($action === '') {
                    $security->fail('No API action specified. Use ?api=<action>.', 400);
                }
                $security->fail('Unknown API action: ' . $action, 404);
            }
            break;
    }
} catch (\Throwable $e) {
    $security->fail('Server error: ' . $e->getMessage(), 500);
}
