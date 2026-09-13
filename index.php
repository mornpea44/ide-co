<?php

declare(strict_types=1);

/**
 * ═══════════════════════════════════════════════════════════════════════════
 *  QUIRKY IDE — MOBILE FRONT CONTROLLER
 * ═══════════════════════════════════════════════════════════════════════════
 *
 *  This is the SINGLE entry point for the mobile IDE.
 *  It ALWAYS serves the mobile shell. No desktop code lives here.
 *
 *  Its jobs:
 *  1. MOBILE GUARD — blocks any attempt to reference desktop-only files.
 *  2. CONFIG — loads base config + mobile overlay.
 *  3. ASSET SERVER — serves CSS/JS/vendor files from the jail.
 *  4. BUNDLE ENDPOINT — serves the mobile CSS/JS bundle.
 *  5. SERVICE WORKER — serves the PWA service worker.
 *  6. API SWITCHBOARD — routes ?api= to app/api.php.
 *  7. SECURITY DESK — password gate.
 *  8. MOBILE SHELL — always loads views/mobile-shell.php.
 *
 * ═══════════════════════════════════════════════════════════════════════════
 */

/* ═══════════════════════════════════════════════════════════════════════
   0. URL NORMALIZATION
   ═══════════════════════════════════════════════════════════════════════ */

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if ($requestPath === null || $requestPath === false || $requestPath === '') {
  $requestPath = '/';
}

$endsWithSlash    = substr($requestPath, -1) === '/';
$endsWithIndexPhp = substr($requestPath, -strlen('index.php')) === 'index.php';
$hasPathInfo      = !empty($_SERVER['PATH_INFO'])
  || preg_match('#/index\.php/(vendor|assets|workspace)/#', $requestPath);

if (!$endsWithSlash && !$endsWithIndexPhp && !$hasPathInfo) {
  $queryString    = $_SERVER['QUERY_STRING'] ?? '';
  $redirectTarget = $requestPath . '/' . ($queryString !== '' ? '?' . $queryString : '');
  header('Location: ' . $redirectTarget, true, 302);
  exit;
}

/* ═══════════════════════════════════════════════════════════════════════
   1. MOBILE GUARD — load BEFORE anything else
   ═══════════════════════════════════════════════════════════════════════ */

require_once __DIR__ . '/app/mobile-only.php';
IdeMobileGuard::assertNoDesktopRefs($_SERVER['REQUEST_URI'] ?? '');

/* ═══════════════════════════════════════════════════════════════════════
   2. BOOTSTRAP — config + mobile overlay + security
   ═══════════════════════════════════════════════════════════════════════ */

$config = require __DIR__ . '/config.php';

// Mobile config overlay (tunes defaults for phones)
$mobileConfigFile = __DIR__ . '/config.mobile.php';
if (file_exists($mobileConfigFile)) {
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
// ★ FIX: Re-apply features.json AFTER the mobile overlay merge.
// The overlay hard-codes defaults (e.g. terminal_enabled => true),
// which would overwrite whatever the user saved via Settings → Features.
// The user's choices must always win, so we re-read features.json last.
$quirkySavedFeatureOverrides = [];

$featuresOverrideFile = IDE_APP . '/features.json';
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

// ★ Hardware-based file limit: use the tier the phone reported.
require_once __DIR__ . '/app/mobile-limits.php';
quirkyMobileApplyDeviceLimit($config);

// ★ Device tier policy engine: apply optimization/stabilization rules.
quirkyMobileApplyDeviceTierPolicy($config, $quirkySavedFeatureOverrides);

require_once __DIR__ . '/app/security.php';
session_set_cookie_params([
  'lifetime' => 0,
  'path'     => '/',
  'httponly' => true,
  'samesite' => 'Lax',
]);

$security = new IdeSecurity($config);

/* ═══════════════════════════════════════════════════════════════════════════
   2b. THE BUNDLE ENDPOINT (Mobile-specific)
   ═══════════════════════════════════════════════════════════════════════════ */
if (isset($_GET['bundle'])) {
  $type = (string) $_GET['bundle'];

  // Mobile platform ONLY supports mobile-css and mobile-js
  if ($type === 'mobile-js') {
    handleMobileBundle('js', $config);
    exit;
  }
  if ($type === 'mobile-css') {
    handleMobileBundle('css', $config);
    exit;
  }

  // Reject desktop bundles on the mobile platform
  http_response_code(400);
  header('Content-Type: text/plain; charset=utf-8');
  exit('Invalid bundle. Mobile platform only supports ?bundle=mobile-css or ?bundle=mobile-js');
}

/* ═══════════════════════════════════════════════════════════════════════
   3. SERVICE WORKER ENDPOINT (PWA — mobile only)
   ═══════════════════════════════════════════════════════════════════════ */

if (isset($_GET['sw'])) {
  $swFile = IDE_ASSETS . '/js/sw.js';
  if (is_file($swFile)) {
    header('Content-Type: application/javascript; charset=utf-8');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Service-Worker-Allowed: /');
    readfile($swFile);
  } else {
    http_response_code(404);
    echo 'Service worker file not found.';
  }
  exit;
}

/* ═══════════════════════════════════════════════════════════════════════════
4. ASSET SERVER — serves CSS/JS/vendor/workspace files
═══════════════════════════════════════════════════════════════════════════ */
/* ★ v18 FIX: PATH-STYLE asset URLs (index.php/vendor/…, index.php/assets/…,
   index.php/workspace/…). The CM6 ES modules in app/vendor/cm6/ are a
   code-split build: entry modules import their shared chunks with RELATIVE
   specifiers ("./chunk-XXXX.js"). A relative specifier resolves against the
   DIRECTORY of the importing module's URL — under the old query-string URLs
   (index.php?vendor=cm6/codemirror-state.js) that directory is the site
   root, so every chunk request 404'd and CM6 could never boot (silent
   fallback to CM5). Path-style URLs give the modules a real directory
   (…/index.php/vendor/cm6/), so relative imports stay inside the folder. */
if (
  !isset($_GET['asset']) && !isset($_GET['vendor']) && !isset($_GET['workspace'])
  && preg_match('#^/index\.php/(vendor|assets|workspace)/(.+)$#', $requestPath, $pm)
) {
  $pmKey = ($pm[1] === 'assets') ? 'asset' : $pm[1];
  $_GET[$pmKey] = urldecode($pm[2]);
}
if (isset($_GET['asset']) || isset($_GET['vendor']) || isset($_GET['workspace'])) {
  $isVendor    = isset($_GET['vendor']);
  $isWorkspace = isset($_GET['workspace']);
  $requested   = (string) ($isWorkspace ? $_GET['workspace'] : ($isVendor ? $_GET['vendor'] : $_GET['asset']));

  if ($requested === '' || strpos($requested, "\0") !== false) {
    http_response_code(400);
    exit;
  }

  $requested = str_replace('\\', '/', $requested);
  foreach (explode('/', $requested) as $segment) {
    if ($segment === '..' || $segment === '.') {
      http_response_code(403);
      exit;
    }
  }

  $baseDir = $isWorkspace ? IDE_WORKSPACE : ($isVendor ? IDE_VENDOR : IDE_ASSETS);

  if ($isWorkspace) {
    $blocked = $config['security']['blocked_segments'] ?? [];
    foreach (explode('/', $requested) as $seg) {
      if (in_array($seg, $blocked, true)) {
        http_response_code(403);
        echo '403 — Blocked path segment';
        exit;
      }
    }
  }

  $realBase = realpath($baseDir);
  if ($realBase === false) {
    http_response_code(404);
    exit;
  }

  $fullPath = realpath($realBase . '/' . ltrim($requested, '/'));

  if ($fullPath === false || !is_file($fullPath)) {
    $ext404  = strtolower(pathinfo($requested, PATHINFO_EXTENSION));
    $mime404 = [
      'css'   => 'text/css',
      'js'    => 'text/javascript',
      'mjs'   => 'text/javascript',
      'woff'  => 'font/woff',
      'woff2' => 'font/woff2',
      'ttf'   => 'font/ttf',
      'json'  => 'application/json',
      'map'   => 'application/json',
    ][$ext404] ?? 'application/octet-stream';
    http_response_code(404);
    header('Content-Type: ' . $mime404);
    exit;
  }

  $realBaseNorm = str_replace('\\', '/', $realBase);
  $fullPathNorm = str_replace('\\', '/', $fullPath);

  if (strpos($fullPathNorm, $realBaseNorm . '/') !== 0) {
    http_response_code(403);
    exit;
  }

  $extension = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
  $mimeTypes = [
    'css' => 'text/css',
    'js' => 'text/javascript',
    'mjs' => 'text/javascript',
    'map' => 'application/json',
    'json' => 'application/json',
    'woff' => 'font/woff',
    'woff2' => 'font/woff2',
    'ttf' => 'font/ttf',
    'svg' => 'image/svg+xml',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'html' => 'text/html',
    'txt' => 'text/plain',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'webp' => 'image/webp',
    'ico' => 'image/x-icon',
    'avif' => 'image/avif',
    'otf' => 'font/otf',
    'eot' => 'application/vnd.ms-fontobject',
    'mp4' => 'video/mp4',
    'webm' => 'video/webm',
    'mp3' => 'audio/mpeg',
    'wav' => 'audio/wav',
    'ogg' => 'audio/ogg',
    'xml' => 'application/xml',
    'pdf' => 'application/pdf',
    'wasm' => 'application/wasm',
    'htm' => 'text/html',
  ];

  $mime = $mimeTypes[$extension] ?? 'application/octet-stream';
  header('Content-Type: ' . $mime . '; charset=utf-8');

  if ($isWorkspace) {
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
  } else {
    header('Cache-Control: public, max-age=86400');
  }

  readfile($fullPath);
  exit;
}

/**
 * Serve the MOBILE-specific bundle.
 * Only includes what the mobile shell actually needs.
 */
function handleMobileBundle(string $type, array $config): void
{
  if ($type !== 'js' && $type !== 'css') {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Invalid bundle. Use ?bundle=css or ?bundle=js');
  }

  $files = ($type === 'js') ? mobileBundleJsFiles($config) : mobileBundleCssFiles();

  $sigParts = [];
  foreach ($files as $f) {
    $sigParts[] = is_file($f['path'])
      ? $f['rel'] . ':' . (int) filemtime($f['path'])
      : $f['rel'] . ':MISSING';
  }
  $signature = md5(implode('|', $sigParts));
  $etag = '"mobile-' . $type . '-' . $signature . '"';

  header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
  header('Pragma: no-cache');
  header('Expires: 0');
  header('ETag: ' . $etag);

  if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
    http_response_code(304);
    exit;
  }

  $contentType = ($type === 'js')
    ? 'text/javascript; charset=utf-8'
    : 'text/css; charset=utf-8';

  $cacheFile   = IDE_APP . '/.bundle-mobile-' . $type . '-' . $signature . '.cache';
  $cacheUsable = is_file($cacheFile) && filesize($cacheFile) > 0;

  if (!$cacheUsable) {
    $content = '';
    foreach ($files as $f) {
      if (!is_file($f['path'])) continue;
      $content .= '/* ===== ' . $f['rel'] . " ===== */\n";
      $content .= (string) file_get_contents($f['path']);
      $content .= "\n";
    }
    $written = @file_put_contents($cacheFile, $content, LOCK_EX);
    if ($written === false) {
      header('Content-Type: ' . $contentType);
      echo $content;
      exit;
    }
    foreach ((glob(IDE_APP . '/.bundle-mobile-' . $type . '-*.cache') ?: []) as $old) {
      if ($old !== $cacheFile) @unlink($old);
    }
  }

  header('Content-Type: ' . $contentType);
  readfile($cacheFile);
}

/**
 * Mobile JS bundle: ONLY the files the mobile shell needs at boot.
 */
function mobileBundleJsFiles(array $config): array
{
  $features = $config['features'] ?? [];
  $list = [];

  $asset = function (string $rel) use (&$list): void {
    $list[] = ['rel' => 'asset/' . $rel, 'path' => IDE_ASSETS . '/' . $rel];
  };
  $vendor = function (string $rel) use (&$list): void {
    $list[] = ['rel' => 'vendor/' . $rel, 'path' => IDE_VENDOR . '/' . $rel];
  };

  // CodeMirror Core & Essential Addons
  $vendor('codemirror/lib/codemirror.js');
  $vendor('codemirror/addon/edit/matchbrackets.js');
  $vendor('codemirror/addon/edit/closebrackets.js');
  $vendor('codemirror/addon/edit/closetag.js');
  $vendor('codemirror/addon/selection/active-line.js');
  $vendor('codemirror/addon/comment/comment.js');
  $vendor('codemirror/addon/fold/foldcode.js');
  $vendor('codemirror/addon/fold/foldgutter.js');
  $vendor('codemirror/addon/fold/brace-fold.js');
  $vendor('codemirror/addon/fold/xml-fold.js');
  $vendor('codemirror/addon/fold/comment-fold.js');
  $vendor('codemirror/addon/fold/indent-fold.js');
  $vendor('codemirror/addon/search/searchcursor.js');
  $vendor('codemirror/addon/search/search.js');
  $vendor('codemirror/addon/search/jump-to-line.js');
  $vendor('codemirror/addon/dialog/dialog.js');
  $vendor('codemirror/addon/hint/show-hint.js');
  $vendor('codemirror/addon/hint/anyword-hint.js');

  // js-beautify (client-side formatter for HTML/CSS/JS — the real files are the .min builds)
  $vendor('js-beautify/beautify.min.js');
  $vendor('js-beautify/beautify-css.min.js');
  $vendor('js-beautify/beautify-html.min.js');

  // Foundation
  $asset('js/utils.js');
  $asset('js/api.js');
  $asset('js/language.js');
  $asset('js/mobile-bridge.js');
  $asset('js/editor-minify.js');
  $asset('js/settings.js');
  // ★ Snippet Studio (custom boilerplates + autocomplete integration)
  $asset('js/mobile-snippets.js');
  // ★ v13: IDE console log capture + viewer (Settings → Logs). Loads early
  //        so the console hooks catch as much of the boot as possible.
  $asset('js/mobile-logs.js');

  // Preview support
  if (!empty($features['preview_enabled'])) {
    $asset('js/workspace-urls.js');
    $asset('js/markdown.js');
  }

  // Mobile-specific modules (load order matters)
  $asset('js/mobile-sheets.js');
  $asset('js/mobile-actions.js');
  $asset('js/mobile-swipe.js');
  $asset('js/mobile-keyboard.js');
  // ★ Editor engine adapter (CM5 ⇄ CM6) — MUST load before mobile-editor.js,
  //   otherwise the editor cannot find window.IDE.editorAdapter and never boots.
  $asset('js/editor-adapter.js');
  $asset('js/mobile-editor.js');
  // Editor intelligence (autocomplete / diagnostics / code lens) — talk to the
  // editor ONLY through the INTEL facade methods on IDE.editorAdapter instances.
  $asset('js/autocomplete.js');
  $asset('js/diagnostics.js');
  $asset('js/editor-lens.js');
  // ★ v23: CM6 last-resort syntax colours (mounts only when CM6 paints nothing)
  $asset('js/editor-fallback-hl.js');
  $asset('js/cm6-color-guard.js');
  $asset('js/mobile-preview.js');
  $asset('js/mobile-terminal.js');
  $asset('js/mobile-git.js');
  $asset('js/mobile-http.js');
  $asset('js/mobile-palette.js');
  $asset('js/mobile-filetree.js');
  $asset('js/mobile-workspace.js');
  $asset('js/mobile-workshop.js');
  // Workshop community-package runtime host (loads enabled packages' css/js
  // via the jailed workshop-asset route after all core modules registered).
  $asset('js/package-host.js');
  $asset('js/mobile-gestures.js');

  // AI module
  if (!empty($config['ai']['enabled'])) {
    $asset('js/ai.js');
  }

  // Shell bootstrap — MUST load last
  $asset('js/mobile-shell.js');

  return $list;
}

/**
 * Mobile CSS bundle: themes + mobile styles only.
 * Desktop layout CSS is NOT included.
 */
function mobileBundleCssFiles(): array
{
  $list = [];

  $asset = function (string $rel) use (&$list): void {
    $list[] = ['rel' => 'asset/' . $rel, 'path' => IDE_ASSETS . '/' . $rel];
  };
  $vendor = function (string $rel) use (&$list): void {
    $list[] = ['rel' => 'vendor/' . $rel, 'path' => IDE_VENDOR . '/' . $rel];
  };

  // CodeMirror Core CSS
  $vendor('codemirror/lib/codemirror.css');
  $vendor('codemirror/addon/fold/foldgutter.css');
  $vendor('codemirror/addon/dialog/dialog.css');
  $vendor('codemirror/addon/hint/show-hint.css');

  // Design tokens
  $asset('css/themes.css');
  // AI panel styles
  $asset('css/ai-panel.css');
  // Settings styles
  $asset('css/settings.css');
  // Device frames (preview)
  $asset('css/device-frames.css');
  // Runtime styles (ANSI colors, etc.)
  $asset('css/runtime.css');
  // Mobile styles
  $asset('css/mobile-base.css');
  $asset('css/mobile-tree.css');
  $asset('css/mobile-editor.css');
  $asset('css/mobile-preview.css');
  $asset('css/mobile-terminal.css');
  $asset('css/mobile-panels.css');
  $asset('css/mobile-sheets.css');
  $asset('css/mobile-workshop.css');
  $asset('css/mobile-only.css');

  return $list;
}



/* ═══════════════════════════════════════════════════════════════════════
5b. EXPORT DOWNLOAD (token created via ?api=files-export-token)
────────────────────────────────────────────────────────────────────────
The Android DownloadListener re-fetches this URL WITHOUT session
cookies, so it authenticates with a short-lived one-time-ish token
instead of the session. It intentionally runs BEFORE the auth gate. */
if (isset($_GET['export-token'])) {
  quirkyHandleExportDownload((string) $_GET['export-token']);
  exit;
}

/* ═══════════════════════════════════════════════════════════════════════
6. API SWITCHBOARD
═══════════════════════════════════════════════════════════════════════ */
if (isset($_GET['api'])) {
  require __DIR__ . '/app/api.php';
  exit;
}

/* ═══════════════════════════════════════════════════════════════════════
   7. SECURITY DESK — Login / Logout Gate
   ═══════════════════════════════════════════════════════════════════════ */

$authRequired = !empty($config['security']['auth_enabled']);

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
  $security->logout();
}

if ($authRequired && !$security->isAuthenticated()) {
  $loginError = '';

  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ide_password'])) {
    $submittedPassword = (string) $_POST['ide_password'];
    if ($security->login($submittedPassword)) {
      // Success — fall through
    } else {
      $loginError = 'Incorrect password. Access denied.';
    }
  }

  if (!$security->isAuthenticated()) {
    renderLoginScreen($config, $loginError);
    exit;
  }
}

/* ═══════════════════════════════════════════════════════════════════════
   8. MOBILE SHELL — ALWAYS loads the mobile shell
   ═══════════════════════════════════════════════════════════════════════ */

$shellPath = IDE_VIEWS . '/mobile-shell.php';

if (!file_exists($shellPath)) {
  http_response_code(500);
  exit('Quirky IDE Mobile error: app/views/mobile-shell.php is missing.');
}

require $shellPath;
exit;

/* ═══════════════════════════════════════════════════════════════════════
   UI HELPER — Lock Screen
   ═══════════════════════════════════════════════════════════════════════ */

function renderLoginScreen(array $config, string $error = ''): void
{
  $appName   = htmlspecialchars($config['meta']['name'] ?? 'Quirky IDE', ENT_QUOTES);
  $safeError = htmlspecialchars($error, ENT_QUOTES);
?>
  <!DOCTYPE html>
  <html lang="en">

  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#0d1420">
    <title><?php echo $appName; ?> — Locked</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=Space+Grotesk:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
      :root {
        --bg: #0b0f17;
        --surface: #131a26;
        --border: #263450;
        --text: #e8eef7;
        --muted: #7186a5;
        --amber: #f5a524;
        --amber-dim: rgba(245, 165, 36, 0.12);
        --red: #f87171;
        --red-dim: rgba(248, 113, 113, 0.1);
        --mono: 'JetBrains Mono', ui-monospace, monospace;
        --body: 'Space Grotesk', system-ui, sans-serif;
        --display: 'Syne', sans-serif;
      }

      * {
        box-sizing: border-box;
        margin: 0;
        padding: 0;
      }

      body {
        font-family: var(--body);
        background: var(--bg);
        color: var(--text);
        min-height: 100vh;
        min-height: 100dvh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 2rem 1rem;
        padding-top: calc(2rem + env(safe-area-inset-top, 0px));
        padding-bottom: calc(2rem + env(safe-area-inset-bottom, 0px));
      }

      body::before {
        content: '';
        position: fixed;
        inset: 0;
        pointer-events: none;
        background-image:
          linear-gradient(rgba(96, 165, 250, 0.04) 1px, transparent 1px),
          linear-gradient(90deg, rgba(96, 165, 250, 0.04) 1px, transparent 1px);
        background-size: 44px 44px;
      }

      .lock-card {
        position: relative;
        z-index: 1;
        width: 100%;
        max-width: 340px;
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 16px;
        padding: 2rem 1.5rem;
        text-align: center;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
      }

      .lock-icon {
        width: 64px;
        height: 64px;
        margin: 0 auto 1rem;
        display: grid;
        place-items: center;
        font-size: 1.8rem;
        background: var(--amber-dim);
        border: 1px solid var(--amber);
        border-radius: 50%;
      }

      .lock-card h1 {
        font-family: var(--display);
        font-size: 1.4rem;
        font-weight: 800;
        margin-bottom: 0.3rem;
      }

      .lock-card h1 span {
        color: var(--amber);
      }

      .lock-card .subtitle {
        font-family: var(--mono);
        font-size: 0.62rem;
        letter-spacing: 0.2em;
        text-transform: uppercase;
        color: var(--muted);
        margin-bottom: 1.5rem;
      }

      .error-banner {
        background: var(--red-dim);
        border: 1px solid var(--red);
        color: var(--red);
        font-size: 0.82rem;
        font-weight: 600;
        padding: 0.6rem 0.9rem;
        border-radius: 9px;
        margin-bottom: 1rem;
      }

      .password-field {
        margin-bottom: 1rem;
      }

      .password-field input {
        width: 100%;
        background: var(--bg);
        border: 1px solid var(--border);
        color: var(--text);
        font-family: var(--mono);
        font-size: 16px;
        /* prevents iOS zoom */
        padding: 0.85rem 1rem;
        border-radius: 10px;
        text-align: center;
      }

      .password-field input:focus {
        outline: none;
        border-color: var(--amber);
        box-shadow: 0 0 0 3px var(--amber-dim);
      }

      .unlock-btn {
        width: 100%;
        background: var(--amber);
        color: #0b0f17;
        border: none;
        font-family: var(--body);
        font-weight: 700;
        font-size: 1rem;
        padding: 0.85rem;
        border-radius: 10px;
        cursor: pointer;
      }

      .unlock-btn:active {
        opacity: 0.85;
      }

      .lock-footer {
        margin-top: 1.5rem;
        font-family: var(--mono);
        font-size: 0.6rem;
        color: var(--muted);
        letter-spacing: 0.08em;
      }
    </style>
  </head>

  <body>
    <div class="lock-card">
      <div class="lock-icon">🔒</div>
      <h1>QUIR<span>KY</span> IDE</h1>
      <div class="subtitle">Mobile · Restricted Workspace</div>

      <?php if ($safeError !== ''): ?>
        <div class="error-banner">⚠ <?php echo $safeError; ?></div>
      <?php endif; ?>

      <form method="POST" action="">
        <div class="password-field">
          <input type="password" name="ide_password" placeholder="••••••••"
            autofocus autocomplete="current-password" required
            inputmode="text">
        </div>
        <button type="submit" class="unlock-btn">
          Unlock Workspace →
        </button>
      </form>

      <div class="lock-footer">LOCAL DEV ONLY · DELETE FROM PRODUCTION</div>
    </div>
  </body>

  </html>
<?php
}

/* ═══════════════════════════════════════════════════════════════════════
EXPORT DOWNLOAD HELPERS (used by section 5b)
═══════════════════════════════════════════════════════════════════════ */

function quirkyExportTokensPath(): string
{
  return IDE_APP . '/.export-tokens.json';
}

function quirkyExportCacheDir(): string
{
  return IDE_APP . '/.export-cache';
}

/** Serve one export payload for a valid, unexpired token. */
function quirkyHandleExportDownload(string $token): void
{
  $token  = preg_replace('/[^a-f0-9]/', '', $token);
  $file   = quirkyExportTokensPath();
  $tokens = [];
  if (is_file($file)) {
    $decoded = json_decode((string) @file_get_contents($file), true);
    if (is_array($decoded)) $tokens = $decoded;
  }
  $now      = time();
  $cacheDir = quirkyExportCacheDir();
  foreach ($tokens as $k => $t) {
    if ((int) ($t['time'] ?? 0) < $now - 600) {
      unset($tokens[$k]);
      @unlink($cacheDir . '/' . $k . '.zip');
    }
  }
  $rec = $tokens[$token] ?? null;
  @file_put_contents($file, json_encode($tokens, JSON_UNESCAPED_SLASHES), LOCK_EX);
  if (!is_array($rec) || empty($rec['path'])) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit('This export link has expired. Create a new one and try again.');
  }
  $pathAbs = (string) $rec['path'];
  $name    = (string) ($rec['name'] ?? 'export');

  // Re-verify the target is still inside the CURRENT workspace
  // (the workspace may have been switched since the token was made).
  $wsReal     = realpath(IDE_WORKSPACE);
  $targetReal = realpath($pathAbs);
  if ($wsReal === false || $targetReal === false) {
    http_response_code(410);
    exit('Export target no longer exists.');
  }
  if (strpos($targetReal . '/', str_replace('\\', '/', $wsReal) . '/') !== 0) {
    http_response_code(403);
    exit('Export blocked: the workspace changed since this link was created.');
  }

  $safeName = str_replace(['"', '\\', "\r", "\n"], '', $name);
  $safeName = preg_replace('/[^\x20-\x7E]/', '_', $safeName);
  if ($safeName === '') $safeName = 'export';

  if (is_dir($pathAbs)) {
    if (!class_exists('ZipArchive')) {
      http_response_code(500);
      exit('Folder export needs PHP zip support (ZipArchive).');
    }
    if (!is_dir($cacheDir)) @mkdir($cacheDir, 0777, true);
    $zipPath = $cacheDir . '/' . $token . '.zip';
    if (!is_file($zipPath) && !quirkyExportBuildZip($pathAbs, $zipPath)) {
      http_response_code(500);
      exit('Could not build the zip archive.');
    }
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $safeName . '"');
    header('Content-Length: ' . (string) filesize($zipPath));
    header('Cache-Control: no-store');
    readfile($zipPath);
    return;
  }

  $ext  = strtolower(pathinfo($pathAbs, PATHINFO_EXTENSION));
  $mime = [
    'txt' => 'text/plain',
    'md' => 'text/markdown',
    'html' => 'text/html',
    'htm' => 'text/html',
    'css' => 'text/css',
    'js' => 'text/javascript',
    'json' => 'application/json',
    'xml' => 'application/xml',
    'php' => 'application/octet-stream',
    'zip' => 'application/zip',
    'png' => 'image/png',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'gif' => 'image/gif',
    'svg' => 'image/svg+xml',
    'pdf' => 'application/pdf',
  ][$ext] ?? 'application/octet-stream';
  header('Content-Type: ' . $mime);
  header('Content-Disposition: attachment; filename="' . $safeName . '"');
  header('Content-Length: ' . (string) filesize($pathAbs));
  header('Cache-Control: no-store');
  readfile($pathAbs);
}

/** Recursively zip a directory (symlinks skipped). */
function quirkyExportBuildZip(string $dirAbs, string $zipPath): bool
{
  $zip = new ZipArchive();
  if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    return false;
  }
  $rootPrefix = rtrim(str_replace('\\', '/', $dirAbs), '/') . '/';
  $added = 0;
  $it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($dirAbs, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::LEAVES_ONLY
  );
  foreach ($it as $item) {
    if ($item->isLink() || !$item->isFile()) continue;
    $full = str_replace('\\', '/', (string) $item->getPathname());
    if (strpos($full, $rootPrefix) !== 0) continue;
    $zip->addFile($full, substr($full, strlen($rootPrefix)));
    $added++;
  }
  if ($added === 0) {
    $zip->addEmptyDir(basename($dirAbs));
  }
  return $zip->close();
}
