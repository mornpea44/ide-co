<?php
// File: mobile-shell.php

/**
 * ═══════════════════════════════════════════════════════════════════════════
 *  QUIRKY IDE — MOBILE SHELL (Phase 7 · Tasks 7.1–7.9)
 * ═══════════════════════════════════════════════════════════════════════════
 *
 *  Thin assembly file (like desktop shell.php). All UI markup lives in
 *  partials; all behaviour lives in JS modules. This file only:
 *    1. Sets up config/feature flags
 *    2. Outputs the HTML head (fonts, CSS bundle)
 *    3. Includes each partial in order
 *    4. Hands config to JavaScript
 *    5. Loads the JS bundle
 *    6. Registers the Service Worker
 *
 *  Partials (app/views/mobile/):
 *    _header.php    → mobile header bar
 *    _panels.php    → all panels + tab bar + FAB
 *    _sheets.php    → all bottom sheets
 *    _overlays.php  → all full-screen overlays + toast
 *
 *  JS modules (app/assets/js/):
 *    mobile-shell.js     → THIS bootstrap (tab switching, sheets, wiring)
 *    mobile-filetree.js  → tree rendering, FAB, pull-to-refresh
 *    mobile-editor.js    → CodeMirror, tabs, save, format, find, zoom
 *    mobile-preview.js   → preview rendering, device frames, zoom
 *    mobile-terminal.js  → ANSI rendering, execution, history, CWD
 *    mobile-git.js       → git overlay (read-only)
 *    mobile-http.js      → HTTP client overlay
 *    mobile-palette.js   → command palette overlay
 *    mobile-actions.js   → long-press, swipe, clipboard, conflict
 *    mobile-keyboard.js  → virtual keyboard toolbar
 *    mobile-gestures.js  → swipe between tabs
 *
 * ═══════════════════════════════════════════════════════════════════════════
 */
$meta     = $config['meta'] ?? [];
$editorC  = $config['editor'] ?? [];
$features = $config['features'] ?? [];
$previewC = $config['preview'] ?? [];
$appName    = $meta['name'] ?? 'Quirky IDE';
$appVersion = $meta['version'] ?? '1.0';
$theme      = $editorC['theme'] ?? 'dark';
$featTerminal     = !empty($features['terminal_enabled']);
$featPreview      = !empty($features['preview_enabled']);
$featFormat       = !empty($features['format_enabled']);
$featMinify       = !empty($features['minify_enabled']);
$featAutocomplete = !empty($features['autocomplete_enabled']);
$featGit          = !empty($features['git_enabled']);
$featHttpClient   = !empty($features['http_client_enabled']);
$featAi             = !empty($config['ai']['enabled']) && !empty($features['ai_enabled']);
$featCommandPalette = !empty($features['command_palette_enabled']);
$featWorkshop       = !empty($features['workshop_enabled']);
$ideConfig = [
  'name'        => $appName,
  'version'     => $appVersion,
  'theme'       => $theme,
  'fontSize'    => (int) ($editorC['font_size'] ?? 14),
  'tabSize'     => (int) ($editorC['tab_size'] ?? 2),
  'autosaveMs'  => (int) ($editorC['autosave_ms'] ?? 800),
  'defaultFile' => $editorC['default_file'] ?? 'index.php',
  'features'    => [
    'terminal'     => $featTerminal,
    'preview'      => $featPreview,
    'format'       => $featFormat,
    'minify'       => $featMinify,
    'autocomplete' => $featAutocomplete,
    'lint'         => !empty($features['lint_enabled']),
    'git'          => $featGit,
    'httpClient'   => $featHttpClient,
    'ai'           => $featAi,
    'commandPalette' => $featCommandPalette,
    'workshop'     => $featWorkshop,
    'packages'     => ($featPackages ?? false) && $featWorkshop,
    'snippets'     => true, // Snippet Studio always available
  ],
  'previewable' => $previewC['previewable'] ?? ['html', 'htm', 'php', 'svg', 'md'],
  'runners'     => $config['runners'] ?? [],
  'authEnabled' => !empty($config['security']['auth_enabled']),
  'watchIntervalMs' => (int) ($features['watch_interval_ms'] ?? $config['watch']['interval_ms'] ?? 15000),
  'maxFileSize' => (int) ($config['security']['max_file_size'] ?? 524288),
  'deviceRam'   => null,
  'deviceCores' => null,
  'hardware'    => null,  // Populated client-side by mobile-bridge.js
  'isMobile'    => true,
  'deviceTier'  => $tier ?? 'high',
  'deviceLimits' => [
    'gestures' => !isset($config['mobile']['gestures_enabled']) || !empty($config['mobile']['gestures_enabled']),
    'motions' => !isset($config['mobile']['motions_enabled']) || !empty($config['mobile']['motions_enabled']),
    'wordWrap' => !empty($config['editor']['word_wrap']),
    'watchIntervalMs' => (int) ($config['watch']['interval_ms'] ?? 15000),
  ],
  // Map of CodeMirror modes to their lazy-load URLs (Task 9.1)
  'cmModes'     => [
    'xml'        => 'index.php?vendor=codemirror/mode/xml/xml.js',
    'javascript' => 'index.php?vendor=codemirror/mode/javascript/javascript.js',
    'css'        => 'index.php?vendor=codemirror/mode/css/css.js',
    'clike'      => 'index.php?vendor=codemirror/mode/clike/clike.js',
    'htmlmixed'  => 'index.php?vendor=codemirror/mode/htmlmixed/htmlmixed.js',
    'php'        => 'index.php?vendor=codemirror/mode/php/php.js',
    'python'     => 'index.php?vendor=codemirror/mode/python/python.js',
    'markdown'   => 'index.php?vendor=codemirror/mode/markdown/markdown.js',
    'sql'        => 'index.php?vendor=codemirror/mode/sql/sql.js',
    'yaml'       => 'index.php?vendor=codemirror/mode/yaml/yaml.js',
    'shell'      => 'index.php?vendor=codemirror/mode/shell/shell.js',
    'properties' => 'index.php?vendor=codemirror/mode/properties/properties.js',
  ],
  // ★ True when app/vendor/cm6 exists → CM6 loads from local files (offline)
  'cm6Local' => is_dir(IDE_VENDOR . '/cm6'),
];
$uri    = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
$pos    = strpos($uri, '/private/');
$hubUrl = ($pos !== false ? substr($uri, 0, $pos) : '') . '/';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo htmlspecialchars($theme, ENT_QUOTES); ?>">

<head>
  <meta charset="UTF-8">
  <?php require __DIR__ . '/mobile/_boot.php'; ?>
  <title><?php echo htmlspecialchars($appName, ENT_QUOTES); ?> — Mobile</title>
  <!-- ★ v10 OFFLINE FIX: web fonts load ASYNC (media=print → all on load).
       A blocking <stylesheet> here made EVERY later <script> wait on the
       CDN — with Google Fonts unreachable, the whole IDE silently never
       booted (no console error, just nothing ran). This is an offline-first
       app: system-font fallback until the CDN answers, never a hard block. -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=Space+Grotesk:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;700&display=swap"
    rel="stylesheet" media="print" onload="this.media='all'">
  <noscript>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=Space+Grotesk:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
  </noscript>
  <link rel="stylesheet" href="index.php?bundle=mobile-css&v=<?php echo $appVersion; ?>-<?php echo filemtime(__FILE__); ?>">
  <?php if (is_dir(IDE_VENDOR . '/cm6')): ?>
    <!-- CM6 import map: bare specifiers → vendored query-string URLs.
     ★ v18 FIX: chunk-*.js entries added via glob() so the code-split
     CM6 build's RELATIVE imports ("./chunk-XXXX.js") are caught by the
     import map before the browser 404s at the site root. -->
    <script type="importmap">
      {
"imports": {
<?php
    $cm6ImportMap = [
      '@codemirror/state'                     => 'codemirror-state.js',
      '@codemirror/view'                      => 'codemirror-view.js',
      '@codemirror/language'                  => 'codemirror-language.js',
      '@codemirror/commands'                  => 'codemirror-commands.js',
      '@codemirror/search'                    => 'codemirror-search.js',
      '@codemirror/autocomplete'              => 'codemirror-autocomplete.js',
      '@codemirror/lang-xml'                  => 'lang-xml.js',
      '@codemirror/lang-javascript'           => 'lang-javascript.js',
      '@codemirror/lang-css'                  => 'lang-css.js',
      '@codemirror/lang-html'                 => 'lang-html.js',
      '@codemirror/lang-php'                  => 'lang-php.js',
      '@codemirror/lang-python'               => 'lang-python.js',
      '@codemirror/lang-markdown'             => 'lang-markdown.js',
      '@codemirror/lang-sql'                  => 'lang-sql.js',
      '@codemirror/lang-yaml'                 => 'lang-yaml.js',
      '@codemirror/legacy-modes/mode/clike'   => 'legacy-clike.js',
      '@codemirror-extra/indent-guides'       => 'indent-guides.js',
      '@codemirror/legacy-modes/mode/shell'   => 'legacy-shell.js',
      '@codemirror/legacy-modes/mode/properties' => 'legacy-properties.js',
      '@lezer/common'                         => 'lezer-common.js',
      '@lezer/highlight'                      => 'lezer-highlight.js',
      '@lezer/lr'                             => 'lezer-lr.js',
      '@lezer/xml'                            => 'lezer-xml.js',
      '@lezer/javascript'                     => 'lezer-javascript.js',
      '@lezer/css'                            => 'lezer-css.js',
      '@lezer/html'                           => 'lezer-html.js',
      '@lezer/php'                            => 'lezer-php.js',
      '@lezer/python'                         => 'lezer-python.js',
      '@lezer/markdown'                       => 'lezer-markdown.js',
    ];
    $cm6Lines = [];
    // Main package entries (bare specifier → query-string URL)
    foreach ($cm6ImportMap as $cm6Spec => $cm6File) {
      if (!is_file(IDE_VENDOR . '/cm6/' . $cm6File)) continue;
      $cm6Lines[] = '    "' . $cm6Spec . '": "./index.php?vendor=cm6/' . $cm6File . '"';
    }
    // ★ v18 FIX: chunk file entries (relative specifier → query-string URL)
    // The code-split CM6 build's entry modules import shared chunks as
    // "./chunk-XXXX.js". Under query-string module URLs the browser resolves
    // that to http://host/chunk-XXXX.js (site root) → 404. Adding them to
    // the import map intercepts that resolution and redirects to the real
    // vendor URL BEFORE any fetch occurs.
    $cm6Chunks = glob(IDE_VENDOR . '/cm6/chunk-*.js');
    if ($cm6Chunks) {
      foreach ($cm6Chunks as $chunkPath) {
        $chunkFile = basename($chunkPath);
        $cm6Lines[] = '    "./' . $chunkFile . '": "./index.php?vendor=cm6/' . $chunkFile . '"';
      }
    }
    echo implode(",\n", $cm6Lines);
?>
}
}
</script>
  <?php endif; ?>
</head>

<?php
$quirkyTierClass = 'device-tier-' . ($config['device']['tier'] ?? 'high');
$quirkyBodyClasses = 'is-mobile ' . $quirkyTierClass;

if (isset($config['mobile']['motions_enabled']) && !$config['mobile']['motions_enabled']) {
  $quirkyBodyClasses .= ' no-motions';
}

if (isset($config['mobile']['gestures_enabled']) && !$config['mobile']['gestures_enabled']) {
  $quirkyBodyClasses .= ' no-gestures';
}
?>

<body class="<?php echo htmlspecialchars($quirkyBodyClasses ?? 'is-mobile', ENT_QUOTES); ?>">
  <noscript>
    <div style="padding:2rem;font-family:sans-serif;background:#1a0d0d;color:#f87171;text-align:center;">
      ⚠️ Quirky IDE requires JavaScript. Please enable it in your browser.
    </div>
  </noscript>
  <div id="mobile-root">
    <?php require __DIR__ . '/mobile/_header.php'; ?>
    <?php require __DIR__ . '/mobile/_panels.php'; ?>
  </div>
  <?php require __DIR__ . '/mobile/_sheets.php'; ?>
  <?php require __DIR__ . '/mobile/_overlays.php'; ?>
  <?php
  // The Android APK runs two PHP servers: 
  // 1) Main server on 8080 (8 workers, which buffers SSE)
  // 2) Stream server on 8081 (0 workers, streams SSE in real-time)
  // On desktop (Laragon, XAMPP, standard PHP server), streaming works 
  // fine on the same origin, so we default to 'index.php'.
  $isAndroidApp = !empty($_GET['quirky_app']);
  $streamBase = $isAndroidApp ? 'http://127.0.0.1:8081/index.php' : 'index.php';
  ?>
  <?php
  // ★ Central device tier policy is applied in index.php / api.php.
  // This block only exposes the FINAL policy state to JavaScript.
  $tier = $config['device']['tier'] ?? 'high';
  $devicePolicy = $config['device']['policy'] ?? [];

  $ideConfig['deviceTier'] = $tier;
  $ideConfig['devicePolicy'] = $devicePolicy;
  $ideConfig['deviceUnlocks'] = $config['device']['unlocks'] ?? [];

  $ideConfig['deviceLimits'] = [
    'fileLimit' => (int) ($config['security']['max_file_size'] ?? 524288),
    'watchIntervalMs' => (int) ($config['watch']['interval_ms'] ?? 15000),
    'wordWrap' => !empty($config['editor']['word_wrap']),
    'gestures' => !isset($config['mobile']['gestures_enabled']) || !empty($config['mobile']['gestures_enabled']),
    'motions' => !isset($config['mobile']['motions_enabled']) || !empty($config['mobile']['motions_enabled']),
  ];

  // Rebuild feature flags after tier enforcement so JS always sees the final state.
  /* ═══════════════════════════════════════════════════════════════
   PHASE 3 — DEVICE TIER ENFORCEMENT (MOVED EARLIER)
   This must happen BEFORE feature flags are consumed by partials,
   otherwise low-tier restrictions would not hide panels/buttons.
═══════════════════════════════════════════════════════════════ */
  // ★ FIX: use the REAL tier that index.php/api.php computed from
  // app/.mobile-device.json ($config['device']['tier']). The old code
  // re-derived it from ?device_tier/$_SESSION — nothing ever sets those,
  // so the shell always ran the 'high' branch while API routes enforced
  // the true policy (UI/backend disagreed on low/medium phones).
  $tier = $config['device']['tier'] ?? 'high';
  if (isset($_GET['device_tier']) && in_array($_GET['device_tier'], ['low', 'medium', 'high'], true)) {
    // Explicit URL override still wins when actually provided (test harness).
    $tier = $_GET['device_tier'];
  }

  if (!isset($config['mobile']) || !is_array($config['mobile'])) {
    $config['mobile'] = [];
  }

  if ($tier === 'low') {
    // Non-negotiable heavy features OFF
    $config['features']['terminal_enabled'] = false;
    $config['features']['workshop_enabled'] = false;
    $config['features']['packages_enabled'] = false;
    $config['features']['pkg_enabled'] = false;

    // Low-tier stabilization defaults
    $config['features']['preview_enabled'] = false;
    $config['features']['ai_enabled'] = false;
    $config['features']['git_enabled'] = false;
    $config['features']['http_client_enabled'] = false;
    $config['features']['command_palette_enabled'] = false;
    $config['features']['autocomplete_enabled'] = false;
    $config['features']['lint_enabled'] = false;
    $config['features']['format_enabled'] = false;
    $config['features']['minify_enabled'] = false;

    // Editor / watch / motion / gesture stabilization
    $config['editor']['word_wrap'] = false;
    $config['editor']['font_size'] = 14;
    $config['watch']['interval_ms'] = 60000;
    $config['features']['watch_interval_ms'] = 60000;
    $config['mobile']['gestures_enabled'] = false;
    $config['mobile']['motions_enabled'] = false;
    $config['ai']['enabled'] = false;
  } elseif ($tier === 'medium') {
    // Non-negotiable heavy features OFF
    $config['features']['terminal_enabled'] = false;
    $config['features']['workshop_enabled'] = false;
    $config['features']['pkg_enabled'] = false;

    // Medium-tier stabilization
    $config['watch']['interval_ms'] = 30000;
    $config['features']['watch_interval_ms'] = 30000;

    if (!isset($config['mobile']['gestures_enabled'])) {
      $config['mobile']['gestures_enabled'] = true;
    }
    if (!isset($config['mobile']['motions_enabled'])) {
      $config['mobile']['motions_enabled'] = true;
    }
  } else {
    if (!isset($config['mobile']['gestures_enabled'])) {
      $config['mobile']['gestures_enabled'] = true;
    }
    if (!isset($config['mobile']['motions_enabled'])) {
      $config['mobile']['motions_enabled'] = true;
    }
  }

  // Re-sync feature flags after tier enforcement
  $features = $config['features'] ?? [];

  $featTerminal       = !empty($features['terminal_enabled']);
  $featPreview        = !empty($features['preview_enabled']);
  $featFormat         = !empty($features['format_enabled']);
  $featMinify         = !empty($features['minify_enabled']);
  $featAutocomplete   = !empty($features['autocomplete_enabled']);
  $featGit            = !empty($features['git_enabled']);
  $featHttpClient     = !empty($features['http_client_enabled']);
  $featAi             = !empty($config['ai']['enabled']) && !empty($features['ai_enabled']);
  $featCommandPalette = !empty($features['command_palette_enabled']);
  $featWorkshop       = !empty($features['workshop_enabled']);
  $featPackages       = !empty($features['packages_enabled']);

  // Body classes for CSS / JS stabilization
  $quirkyBodyClasses = 'is-mobile device-tier-' . $tier;

  if (isset($config['mobile']['motions_enabled']) && !$config['mobile']['motions_enabled']) {
    $quirkyBodyClasses .= ' no-motions';
  }

  if (isset($config['mobile']['gestures_enabled']) && !$config['mobile']['gestures_enabled']) {
    $quirkyBodyClasses .= ' no-gestures';
  }

  $ideConfig['features'] = [
    'terminal'       => $featTerminal,
    'preview'        => $featPreview,
    'format'         => $featFormat,
    'minify'         => $featMinify,
    'autocomplete'   => $featAutocomplete,
    'lint'           => !empty($features['lint_enabled']),
    'git'            => $featGit,
    'httpClient'     => $featHttpClient,
    'ai'             => $featAi,
    'commandPalette' => $featCommandPalette,
    'workshop'       => $featWorkshop,
    'packages'       => ($featPackages ?? false) && $featWorkshop,
  ];

  $ideConfig['watchIntervalMs'] = (int) (
    $config['features']['watch_interval_ms']
    ?? $config['watch']['interval_ms']
    ?? 15000
  );
  ?>
  <script>
    // Expose tier to JS for CodeMirror adjustments
    window.QUIRKY_DEVICE_TIER = <?php echo json_encode($tier); ?>;
  </script>
  <script>
    window.IDE_CONFIG = <?php echo json_encode($ideConfig, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
    // ★ Stream server URL — streaming endpoints (terminal, AI) go here.
    window.IDE_STREAM_BASE = '<?php echo $streamBase; ?>';
    // Task 9.1: Lazy-loader for CodeMirror language modes
    window.IDE = window.IDE || {};
    window.IDE.loadCmMode = function(mode) {
      // Already loaded? Resolve immediately
      if (window.CodeMirror && window.CodeMirror.modes && window.CodeMirror.modes[mode]) {
        return Promise.resolve();
      }
      var url = window.IDE_CONFIG.cmModes ? window.IDE_CONFIG.cmModes[mode] : null;
      if (!url) return Promise.reject('Unknown CodeMirror mode: ' + mode);

      return new Promise(function(resolve, reject) {
        var s = document.createElement('script');
        s.src = url;
        s.onload = function() {
          resolve();
        };
        s.onerror = function() {
          reject('Failed to load mode: ' + mode);
        };
        document.head.appendChild(s);
      });
    };

    // ★ v10 ANDROID BACK CONTRACT — consumed by MainActivity's onKeyDown.
    //   QuirkyBack.onBack(): panels can consume the hardware BACK key by
    //   returning 'handled' after closing themselves; '' lets Android do
    //   its normal history-back.
    //   QuirkyDirty.isDirty(): true when unsaved editor work exists →
    //   Android shows an "Exit anyway?" confirm before finishing.
    // Both must exist even in desktop browsers (harmless there).
    window.QuirkyBack = {
      onBack: function() {
        try {
          // Close the top-most registered overlay/panel and claim the key.
          var sheets = window.IDE && window.IDE.mobileSheets;
          if (sheets && typeof sheets.closeTopOverlay === 'function') {
            return sheets.closeTopOverlay() ? 'handled' : '';
          }
          var shell = window.IDE && window.IDE.mobileShell;
          if (shell && typeof shell.closeTopOverlay === 'function') {
            return shell.closeTopOverlay() ? 'handled' : '';
          }
        } catch (e) {
          /* never break the back key */
        }
        return '';
      }
    };
    window.QuirkyDirty = {
      isDirty: function() {
        try {
          var ed = window.IDE && window.IDE.mobileEditor;
          if (ed && typeof ed.hasDirtyTabs === 'function') return !!ed.hasDirtyTabs();
        } catch (e) {
          /* default: not dirty */
        }
        return false;
      }
    };
  </script>
  <script defer src="index.php?bundle=mobile-js&v=<?php echo $appVersion; ?>-<?php echo filemtime(__FILE__); ?>"></script>
  <script>
    if ('serviceWorker' in navigator) {
      window.addEventListener('load', function() {
        navigator.serviceWorker.register('index.php?sw=1', {
            scope: '.'
          })
          .then(function(registration) {
            console.log('[SW] Registered. Scope:', registration.scope);
          })
          .catch(function(error) {
            console.warn('[SW] Registration failed:', error);
          });
      });
    }
  </script>
</body>

</html>