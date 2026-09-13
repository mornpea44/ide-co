<?php

declare(strict_types=1);
/**
 * ═══════════════════════════════════════════════════════════════════════════
 *  QUIRKY IDE — TOOLS ROUTES (split from api.php · Phase 3)
 * ═══════════════════════════════════════════════════════════════════════════
 *
 *  Handles: search, search-replace, search-stats, symbols-index, lint,
 *           terminal (run / info / cancel / stream / stdin / resize),
 *           features, features-save, format, minify,
 *           git reads (info/status/log/diff/show) + writes
 *           (stage, unstage, discard, commit, init, branches,
 *            pull, push).
 *
 *  This file is `require`d by app/api.php and inherits:
 *    $config, $security, $fs, $search, $terminal, $action, $method, $input
 *
 * ═══════════════════════════════════════════════════════════════════════════
 */

/**
 * @var mixed $action
 * @var mixed $method
 * @var mixed $config
 * @var mixed $input
 */
// PhpTools is now instance-based (Phase 3 · Task 3.6)
$phpTools = new IdePhpTools();
// Symbols indexer (document outline) — jail-contained, regex-based.
$symbols = new IdeSymbols($config, $security);
switch ($action) {
  /* ── SEARCH ─────────────────────────────────────────────────── */
  case 'search':
    if ($method !== 'POST') {
      $security->fail('Use POST to search.', 405);
    }
    $query   = (string) ($input['query'] ?? '');
    $options = [
      'caseSensitive' => (bool) ($input['caseSensitive'] ?? false),
      'useRegex'      => (bool) ($input['useRegex'] ?? false),
      'fileFilter'    => (string) ($input['fileFilter'] ?? ''),
      'maxResults'    => (int) ($input['maxResults'] ?? 500),
    ];
    $security->respond($search->search($query, $options));
    break;

  case 'search-replace':
    if ($method !== 'POST') {
      $security->fail('Use POST to replace.', 405);
    }
    $query       = (string) ($input['query'] ?? '');
    $replacement = (string) ($input['replacement'] ?? '');
    $options     = [
      'caseSensitive' => (bool) ($input['caseSensitive'] ?? false),
      'useRegex'      => (bool) ($input['useRegex'] ?? false),
      'fileFilter'    => (string) ($input['fileFilter'] ?? ''),
      'files'         => (isset($input['files']) && is_array($input['files'])) ? $input['files'] : null,
    ];
    $security->respond($search->replace($query, $replacement, $options, $fs));
    break;

  case 'search-stats':
    $security->respond($search->getSearchStats());
    break;

  /* ── TERMINAL ───────────────────────────────────────────────── */
  case 'terminal':
    if ($method !== 'POST') {
      $security->fail('Use POST to run a command.', 405);
    }
    $command = (string) ($input['command'] ?? '');
    $stdin   = (string) ($input['stdin'] ?? '');
    $security->respond($terminal->run($command, $stdin));
    break;

  case 'terminal-info':
    $security->respond($terminal->getInfo());
    break;

  case 'terminal-cancel':
    $security->respond($terminal->cancel());
    break;

  case 'terminal-stream':
    if ($method !== 'POST') {
      $security->fail('Use POST to run a command.', 405);
    }
    $command = (string) ($input['command'] ?? '');
    $stdin   = (string) ($input['stdin'] ?? '');
    $cols    = (int) ($input['cols'] ?? 0);
    $rows    = (int) ($input['rows'] ?? 0);
    $terminal->runStream($command, $stdin, $cols, $rows);
    exit;

    /* ── INTERACTIVE STDIN (Phase: send typed input to a running command) ── */
  case 'terminal-stdin':
    if ($method !== 'POST') {
      $security->fail('Use POST to send stdin.', 405);
    }
    $stdinInput = (string) ($input['input'] ?? '');
    $security->respond($terminal->sendStdin($stdinInput));
    break;
  /* ── LIVE PTY RESIZE (T-PTY-7) ── */
  case 'terminal-resize':
    if ($method !== 'POST') {
      $security->fail('Use POST to resize.', 405);
    }
    $security->respond($terminal->resizePty(
      (int) ($input['cols'] ?? 0),
      (int) ($input['rows'] ?? 0)
    ));
    break;

  /* ── FEATURES (Settings panel persistence) ──────────────────── */
  case 'features':
    $security->respond(['features' => $config['features']]);
    break;

  case 'features-save':
    if ($method !== 'POST') {
      $security->fail('Use POST to save features.', 405);
    }

    require_once __DIR__ . '/../mobile-limits.php';

    $featureMap = [
      'terminal'       => 'terminal_enabled',
      'preview'        => 'preview_enabled',
      'format'         => 'format_enabled',
      'minify'         => 'minify_enabled',
      'autocomplete'   => 'autocomplete_enabled',
      'lint'           => 'lint_enabled',
      'git'            => 'git_enabled',
      'ai'             => 'ai_enabled',
      'httpClient'     => 'http_client_enabled',
      'commandPalette' => 'command_palette_enabled',
      'workshop'       => 'workshop_enabled',
      'packages'       => 'packages_enabled',
      'pkg'            => 'pkg_enabled',
    ];

    $featuresFile = IDE_APP . '/features.json';
    $existing = [];

    if (is_file($featuresFile)) {
      $decoded = json_decode((string) @file_get_contents($featuresFile), true);
      if (is_array($decoded)) {
        $existing = $decoded;
      }
    }

    // Preserve existing saved values, then apply incoming changes.
    $toSave = $existing;

    foreach ($featureMap as $frontKey => $cfgKey) {
      if (array_key_exists($frontKey, $input)) {
        $toSave[$cfgKey] = (bool) $input[$frontKey];
      }
    }

    // Watch interval: numeric value, clamped to safe range.
    if (array_key_exists('watchIntervalMs', $input)) {
      $wi = (int) $input['watchIntervalMs'];
      $toSave['watch_interval_ms'] = max(3000, min($wi, 999999));
    }

    // ★ DEVICE TIER ENFORCEMENT
    // Non-negotiable features cannot be saved as enabled.
    $deviceTier = quirkyMobileNormalizeDeviceTier(
      $config['device']['tier'] ?? null,
      'high'
    );

    $policy = quirkyMobileGetDeviceTierPolicy($deviceTier);
    $unlocks = quirkyMobileReadTierUnlocks();
    $tierUnlocks = isset($unlocks[$deviceTier]) && is_array($unlocks[$deviceTier])
      ? $unlocks[$deviceTier]
      : [];

    // ★ NEW: Process incoming unlocks from the frontend
    $incomingUnlocks = $input['unlocks'] ?? [];
    if (is_array($incomingUnlocks)) {
      foreach ($incomingUnlocks as $cfgKey => $val) {
        if ($val && in_array($cfgKey, $policy['default_off_features'], true)) {
          $tierUnlocks[$cfgKey] = true;
        }
      }
      $allUnlocks = quirkyMobileReadTierUnlocks();
      $allUnlocks[$deviceTier] = $tierUnlocks;
      @file_put_contents(IDE_APP . '/.mobile-tier-unlocks.json', json_encode($allUnlocks, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
    }

    foreach ($policy['hard_locked_features'] as $cfgKey) {
      $toSave[$cfgKey] = false;
    }

    foreach ($policy['default_off_features'] as $cfgKey) {
      if (empty($tierUnlocks[$cfgKey])) {
        $toSave[$cfgKey] = false;
      }
    }

    // Watch optimization is non-negotiable on low/medium devices.
    if ($deviceTier === 'low') {
      $toSave['watch_interval_ms'] = 60000;
    } elseif ($deviceTier === 'medium') {
      $toSave['watch_interval_ms'] = max(
        (int) ($toSave['watch_interval_ms'] ?? 30000),
        30000
      );
    }

    if (@file_put_contents($featuresFile, json_encode($toSave, JSON_PRETTY_PRINT)) === false) {
      $security->fail('Could not write app/features.json.', 500);
    }

    $security->respond([
      'saved' => true,
      'features' => $toSave,
      'deviceTier' => $deviceTier,
      'hardLocked' => $policy['hard_locked_features'],
    ]);
    break;

  /* ── LINT (multi-language error-lens backend) ───────────────── */
  case 'lint':
    if ($method !== 'POST') {
      $security->fail('Use POST to lint.', 405);
    }
    $code = (string) ($input['content'] ?? ($input['code'] ?? ''));
    $lang = strtolower((string) ($input['language'] ?? 'php'));
    $security->respond($phpTools->lint($code, $lang));
    break;

  /* ── SYMBOLS INDEX (document outline / jump-to-symbol) ──────── */
  case 'symbols-index':
    $symbolsPath = (string) ($_GET['path'] ?? ($input['path'] ?? ''));
    if ($symbolsPath === '') {
      $security->fail('Missing file path (?path=…).');
    }
    $security->respond(['symbols' => $symbols->index($symbolsPath)]);
    break;

  /* ── SNIPPET STUDIO (custom boilerplates & snippets) ──────── */
  case 'snippets-list':
    $snippets = new IdeSnippets($config, $security);
    $langFilter = isset($_GET['lang']) ? (string) $_GET['lang'] : null;
    $security->respond($snippets->list($langFilter));
    break;

  case 'snippets-save':
    if ($method !== 'POST') {
      $security->fail('Use POST to save a snippet.', 405);
    }
    $snippets = new IdeSnippets($config, $security);
    $security->respond($snippets->save($input));
    break;

  case 'snippets-delete':
    if ($method !== 'POST') {
      $security->fail('Use POST to delete a snippet.', 405);
    }
    $snippets = new IdeSnippets($config, $security);
    $security->respond($snippets->delete((string) ($input['id'] ?? '')));
    break;

  case 'snippets-import':
    if ($method !== 'POST') {
      $security->fail('Use POST to import snippets.', 405);
    }
    $snippets = new IdeSnippets($config, $security);
    $security->respond($snippets->import($input));
    break;

  case 'snippets-export':
    $snippets = new IdeSnippets($config, $security);
    $security->respond($snippets->export());
    break;

  case 'snippets-coverage':
    $snippets = new IdeSnippets($config, $security);
    $security->respond($snippets->coverage());
    break;

  /* ── FORMAT ─────────────────────────────────────────────────── */
  case 'format':
    if ($method !== 'POST') {
      $security->fail('Use POST to format.', 405);
    }
    $code      = (string) ($input['code'] ?? '');
    $lang      = strtolower((string) ($input['language'] ?? 'php'));
    $formatter = strtolower((string) ($input['formatter'] ?? 'builtin'));
    if (in_array($lang, ['php', 'phtml', 'php3', 'php4', 'php5', 'phps'], true)) {
      if ($formatter === 'php-cs-fixer') {
        $r = $phpTools->formatWithCsFixer($code);
        if (!empty($r['ok'])) {
          $security->respond(['code' => $r['code'], 'language' => 'php', 'formatter' => 'php-cs-fixer']);
        }
        $security->respond([
          'code'      => $phpTools->format($code),
          'language'  => 'php',
          'formatter' => 'builtin',
          'note'      => 'PHP CS Fixer unavailable (' . ($r['reason'] ?? 'unknown') . ') — used built-in indenter.',
        ]);
      }
      $security->respond(['code' => $phpTools->format($code), 'language' => 'php', 'formatter' => 'builtin']);
    } else {
      $security->respond([
        'code'     => $code,
        'language' => $lang,
        'note'     => 'This language is minified in the browser.',
      ]);
    }
    break;

  /* ── GIT — read-only source control (Phase 5 · Task 5.1) ───── */
  case 'git-info':
    if (empty($config['features']['git_enabled'])) {
      $security->fail('Git panel is disabled in config.php.', 403);
    }
    $git = new IdeGit($config, $security);
    $security->respond($git->getInfo());
    break;

  case 'git-status':
    if (empty($config['features']['git_enabled'])) {
      $security->fail('Git panel is disabled in config.php.', 403);
    }
    $git = new IdeGit($config, $security);
    $security->respond($git->status());
    break;

  case 'git-log':
    if (empty($config['features']['git_enabled'])) {
      $security->fail('Git panel is disabled in config.php.', 403);
    }
    $git = new IdeGit($config, $security);
    $security->respond($git->log((int) ($_GET['limit'] ?? 40)));
    break;

  case 'git-diff':
    if (empty($config['features']['git_enabled'])) {
      $security->fail('Git panel is disabled in config.php.', 403);
    }
    $git = new IdeGit($config, $security);
    $security->respond($git->diff((string) ($_GET['path'] ?? '')));
    break;

  case 'git-show':
    if (empty($config['features']['git_enabled'])) {
      $security->fail('Git panel is disabled in config.php.', 403);
    }
    $git = new IdeGit($config, $security);
    $security->respond($git->show((string) ($_GET['hash'] ?? '')));
    break;

  /* ── GIT — write operations (staging / commits / branches) ──── */
  /* Body fields: paths[] (or single path), message, amend, name.  */

  case 'git-stage':
    if (empty($config['features']['git_enabled'])) {
      $security->fail('Git panel is disabled in config.php.', 403);
    }
    if ($method !== 'POST') {
      $security->fail('Use POST to stage files.', 405);
    }
    $git = new IdeGit($config, $security);
    $security->respond($git->stage(gitPathsFromBody($input)));
    break;

  case 'git-unstage':
    if (empty($config['features']['git_enabled'])) {
      $security->fail('Git panel is disabled in config.php.', 403);
    }
    if ($method !== 'POST') {
      $security->fail('Use POST to unstage files.', 405);
    }
    $git = new IdeGit($config, $security);
    $security->respond($git->unstage(gitPathsFromBody($input)));
    break;

  case 'git-discard':
    // DESTRUCTIVE: reverts uncommitted changes — the UI must confirm first.
    if (empty($config['features']['git_enabled'])) {
      $security->fail('Git panel is disabled in config.php.', 403);
    }
    if ($method !== 'POST') {
      $security->fail('Use POST to discard changes.', 405);
    }
    $git = new IdeGit($config, $security);
    $security->respond($git->discard(gitPathsFromBody($input)));
    break;

  case 'git-commit':
    if (empty($config['features']['git_enabled'])) {
      $security->fail('Git panel is disabled in config.php.', 403);
    }
    if ($method !== 'POST') {
      $security->fail('Use POST to commit.', 405);
    }
    $git = new IdeGit($config, $security);
    $security->respond($git->commit(
      (string) ($input['message'] ?? ''),
      (bool) ($input['amend'] ?? false)
    ));
    break;

  case 'git-init':
    if (empty($config['features']['git_enabled'])) {
      $security->fail('Git panel is disabled in config.php.', 403);
    }
    if ($method !== 'POST') {
      $security->fail('Use POST to init a repository.', 405);
    }
    $git = new IdeGit($config, $security);
    $security->respond($git->init());
    break;

  case 'git-branch-list':
    if (empty($config['features']['git_enabled'])) {
      $security->fail('Git panel is disabled in config.php.', 403);
    }
    $git = new IdeGit($config, $security);
    $security->respond($git->branchList());
    break;

  case 'git-branch-create':
    if (empty($config['features']['git_enabled'])) {
      $security->fail('Git panel is disabled in config.php.', 403);
    }
    if ($method !== 'POST') {
      $security->fail('Use POST to create a branch.', 405);
    }
    $git = new IdeGit($config, $security);
    $security->respond($git->branchCreate((string) ($input['name'] ?? '')));
    break;

  case 'git-branch-switch':
    if (empty($config['features']['git_enabled'])) {
      $security->fail('Git panel is disabled in config.php.', 403);
    }
    if ($method !== 'POST') {
      $security->fail('Use POST to switch branches.', 405);
    }
    $git = new IdeGit($config, $security);
    $security->respond($git->branchSwitch((string) ($input['name'] ?? '')));
    break;

  case 'git-pull':
    if (empty($config['features']['git_enabled'])) {
      $security->fail('Git panel is disabled in config.php.', 403);
    }
    if ($method !== 'POST') {
      $security->fail('Use POST to pull.', 405);
    }
    $git = new IdeGit($config, $security);
    $security->respond($git->pull());
    break;

  case 'git-push':
    if (empty($config['features']['git_enabled'])) {
      $security->fail('Git panel is disabled in config.php.', 403);
    }
    if ($method !== 'POST') {
      $security->fail('Use POST to push.', 405);
    }
    $git = new IdeGit($config, $security);
    $security->respond($git->push());
    break;

  /* ── MINIFY ─────────────────────────────────────────────────── */
  case 'minify':
    if ($method !== 'POST') {
      $security->fail('Use POST to minify.', 405);
    }
    $code = (string) ($input['code'] ?? '');
    $lang = strtolower((string) ($input['language'] ?? 'php'));
    if (in_array($lang, ['php', 'phtml', 'php3', 'php4', 'php5', 'phps'], true)) {
      $security->respond(['code' => $phpTools->minify($code), 'language' => 'php']);
    } else {
      $security->respond([
        'code'     => $code,
        'language' => $lang,
        'note'     => 'This language is minified in the browser.',
      ]);
    }
    break;

  /* ── PROJECT TEMPLATES (Phase 5 · Task 5.4) ────────────────── */
  case 'templates-list':
    $templates = new IdeTemplates($config, $security);
    $security->respond(['templates' => $templates->getAvailableTemplates()]);
    break;

  case 'templates-create':
    if ($method !== 'POST') {
      $security->fail('Use POST to create a project.', 405);
    }
    $templateId  = (string) ($input['template'] ?? '');
    $projectName = (string) ($input['name'] ?? '');
    $templates = new IdeTemplates($config, $security);
    $security->respond($templates->createProject($templateId, $projectName));
    break;

  /* ── HTTP CLIENT PROXY (Phase 5 · Task 5.6) ─────────────────── */
  case 'http-proxy':
    if ($method !== 'POST') {
      $security->fail('Use POST to proxy a request.', 405);
    }
    $httpMethod  = strtoupper(trim((string) ($input['method'] ?? 'GET')));
    $url         = trim((string) ($input['url'] ?? ''));
    $reqHeaders  = $input['headers'] ?? [];
    $reqBody     = (string) ($input['body'] ?? '');
    $timeout     = max(1, min((int) ($input['timeout'] ?? 30), 120));

    // Validate
    if ($url === '') {
      $security->fail('URL cannot be empty.');
    }
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
      $security->fail('Invalid URL format.');
    }
    // Only allow http/https schemes
    $scheme = parse_url($url, PHP_URL_SCHEME);
    if (!in_array(strtolower((string) $scheme), ['http', 'https'], true)) {
      $security->fail('Only http:// and https:// URLs are allowed.');
    }
    if (!in_array($httpMethod, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'], true)) {
      $security->fail('Unsupported HTTP method: ' . $httpMethod);
    }
    if (!function_exists('curl_init')) {
      $security->fail('cURL is not available on this server.', 500);
    }

    /* ★ v10 TLS hardening: verification is ON by default against a real CA
       bundle. The old code disabled VERIFYPEER unconditionally — every
       HTTPS request was MITM-able. Resolution order:
         config('http')['ca_bundle'] → Laragon's shipped cacert.pem →
         Android toolbox prefix cacert.pem → last-resort insecure fallback
       (flagged in the payload so the UI can warn). The client may also
       pass insecure:true for self-signed dev servers. */
    $insecureRequested = !empty($input['insecure']);
    $caBundle = quirkyHttpResolveCaBundle();
    $verifyOn = !$insecureRequested && $caBundle !== null;
    $insecureFallback = !$verifyOn && !$insecureRequested; // wanted security, had no bundle

    // Build cURL request
    $ch = curl_init($url);
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_CUSTOMREQUEST  => $httpMethod,
      CURLOPT_TIMEOUT        => $timeout,
      CURLOPT_CONNECTTIMEOUT => 15,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_MAXREDIRS      => 5,
      CURLOPT_HEADER         => true,
      CURLOPT_SSL_VERIFYPEER => $verifyOn,
      CURLOPT_SSL_VERIFYHOST => $verifyOn ? 2 : 0,
    ]);
    if ($verifyOn) {
      curl_setopt($ch, CURLOPT_CAINFO, $caBundle);
    }

    // Headers
    $curlHeaders = [];
    if (is_array($reqHeaders)) {
      foreach ($reqHeaders as $h) {
        $key   = trim((string) ($h['key'] ?? ''));
        $value = trim((string) ($h['value'] ?? ''));
        if ($key !== '') {
          $curlHeaders[] = $key . ': ' . $value;
        }
      }
    }
    if (!empty($curlHeaders)) {
      curl_setopt($ch, CURLOPT_HTTPHEADER, $curlHeaders);
    }

    // Body
    if ($reqBody !== '' && in_array($httpMethod, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
      curl_setopt($ch, CURLOPT_POSTFIELDS, $reqBody);
    }

    $startTime = microtime(true);
    $response  = curl_exec($ch);
    $elapsed   = round((microtime(true) - $startTime) * 1000, 1);

    if ($response === false) {
      $error = curl_error($ch);

      $security->respond([
        'success' => false,
        'error'   => $error ?: 'Request failed',
        'time'    => $elapsed,
      ]);
    }

    $httpCode    = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize  = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $contentType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $redirects   = (int) curl_getinfo($ch, CURLINFO_REDIRECT_COUNT);

    /* ★ v10: with FOLLOWLOCATION the raw header blob concatenates EVERY
       hop's headers. Keep only the FINAL hop: split on the status-line
       boundary and take the last segment that starts with "HTTP/". */
    $rawHeaders = substr((string) $response, 0, $headerSize);
    $segments   = preg_split('/(?=^HTTP\/)/m', $rawHeaders) ?: [];
    $finalHop   = '';
    foreach ($segments as $seg) {
      if (strpos(ltrim($seg), 'HTTP/') === 0) {
        $finalHop = $seg; // last match wins
      }
    }
    if ($finalHop === '') {
      $finalHop = $rawHeaders; // unusual response shape — parse what we have
    }
    $body = substr((string) $response, $headerSize);

    // Parse final-hop response headers only
    $parsedHeaders = [];
    foreach (explode("\r\n", $finalHop) as $line) {
      if (strpos($line, ':') !== false) {
        list($hk, $hv) = explode(':', $line, 2);
        $parsedHeaders[] = ['key' => trim($hk), 'value' => trim($hv)];
      }
    }

    $security->respond([
      'success'         => true,
      'status'          => $httpCode,
      'statusText'      => httpStatusText($httpCode),
      'headers'         => $parsedHeaders,
      'body'            => $body,
      'contentType'     => $contentType,
      'time'            => $elapsed,
      'size'            => strlen($body),
      'redirectCount'   => $redirects,
      'tlsVerified'     => $verifyOn,
      'insecureFallback' => $insecureFallback,
    ]);
    break;
}

/**
 * Helper: extract a paths array from a JSON request body.
 * Accepts {paths: ["a", "b"]} or a single {path: "a"} for convenience.
 */
function gitPathsFromBody(array $input): array
{
  if (isset($input['paths']) && is_array($input['paths'])) {
    return array_values($input['paths']);
  }
  if (isset($input['path']) && is_string($input['path']) && trim($input['path']) !== '') {
    return [$input['path']];
  }
  return [];
}

/**
 * Helper: HTTP status code → reason phrase.
 */
/**
 * ★ v10: resolve a CA certificate bundle for TLS verification.
 * Order: config('http')['ca_bundle'] → Laragon's shipped cacert.pem →
 * Android toolbox prefix (QUIRKY_PREFIX/etc/tls/cacert.pem) → null
 * (caller falls back to unverified and flags it).
 */
function quirkyHttpResolveCaBundle(): ?string
{
  global $config;
  $candidates = [];
  $configured = (string) ($config['http']['ca_bundle'] ?? '');
  if ($configured !== '') {
    $candidates[] = $configured;
  }
  foreach (glob('C:/laragon/etc/ssl/cacert.pem') ?: [] as $p) {
    $candidates[] = $p;
  }
  foreach (glob('C:/laragon/bin/php/*/cacert.pem') ?: [] as $p) {
    $candidates[] = $p;
  }
  $prefix = getenv('QUIRKY_PREFIX');
  if ($prefix !== false && $prefix !== '') {
    $candidates[] = rtrim($prefix, '/\\') . '/etc/tls/cacert.pem';
  }
  foreach ($candidates as $c) {
    if (is_string($c) && $c !== '' && is_file($c) && is_readable($c)) {
      return $c;
    }
  }
  return null;
}

function httpStatusText(int $code): string
{
  $map = [
    200 => 'OK',
    201 => 'Created',
    202 => 'Accepted',
    204 => 'No Content',
    301 => 'Moved Permanently',
    302 => 'Found',
    304 => 'Not Modified',
    400 => 'Bad Request',
    401 => 'Unauthorized',
    403 => 'Forbidden',
    404 => 'Not Found',
    405 => 'Method Not Allowed',
    409 => 'Conflict',
    422 => 'Unprocessable Entity',
    429 => 'Too Many Requests',
    500 => 'Internal Server Error',
    502 => 'Bad Gateway',
    503 => 'Service Unavailable',
    504 => 'Gateway Timeout',
  ];
  return $map[$code] ?? '';
}
