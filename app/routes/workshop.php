<?php

declare(strict_types=1);
/**
 * ═══════════════════════════════════════════════════════════════════════════
 *  QUIRKY IDE — WORKSHOP ROUTES (Phase 2 · Apache Extension + Logging)
 * ═══════════════════════════════════════════════════════════════════════════
 */

/**
 * @var mixed $action
 * @var mixed $method
 * @var mixed $config
 * @var mixed $security
 * @var mixed $input
 */

// Load the Workshop service + logger
require_once __DIR__ . '/../services/WorkshopLogger.php';
require_once __DIR__ . '/../services/Workshop.php';
/* ★ DYNAMIC WORKSHOP: community-package engine (Manifest v1). All of its
   classes live in this one file; loaded here AND lazily from
   IdeWorkshop::getCommunityPackages(), so api.php's classmap stays untouched. */
require_once __DIR__ . '/../services/WorkshopPackage.php';

$workshop = new IdeWorkshop($config, $security);

/**
 * Lazily build the community-package service (needs IdeSecurity, which the
 * dispatcher always provides).
 */
$communityPackages = function () use ($config, $security): IdeWorkshopPackage {
  return new IdeWorkshopPackage($config, $security);
};

/**
 * Stream a community operation over SSE using EXACTLY the apache installer's
 * event protocol (status/progress/out/err/error/done), so the frontend's
 * readSSEStream() reader is reused unchanged.
 *
 * @param callable(IdeWorkshopPackage): void $task
 */
$streamCommunity = function (callable $task) use ($config, $security): void {
  @set_time_limit(0);
  while (ob_get_level() > 0) @ob_end_flush();
  @ob_implicit_flush(true);
  header('Content-Type: text/event-stream');
  header('Cache-Control: no-cache, no-transform');
  header('X-Accelerate-Buffering: no');
  header('Connection: keep-alive');

  $emit = function (string $event, $data): void {
    echo 'event: ' . $event . "\n" . 'data: ' . json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n";
    @ob_flush();
    @flush();
  };

  $svc = new IdeWorkshopPackage($config, $security);
  $svc->setEmitter(function (string $event, array $data) use ($emit): void {
    $emit($event, $data);
  });

  try {
    $task($svc);
  } catch (\Throwable $e) {
    $emit('error', ['message' => $e->getMessage()]);
    $emit('done', ['success' => false]);
  }
  exit;
};

switch ($action) {
  /* ── LIST ALL EXTENSIONS (+ community section) ─────────────── */
  case 'workshop-list':
    if (!$workshop->isEnabled()) {
      $security->fail('Workshop is disabled. Enable it in config.php.', 403);
    }
    // ★ Community packages ride along as their own section — existing
    //   extension item shapes are untouched (spec F).
    $security->respond([
      'extensions' => $workshop->getExtensions(),
      'community'  => $workshop->getCommunityPackages(),
    ]);
    break;

  /* ── COMMUNITY CATALOG (installed package summaries) ──────── */
  case 'workshop-catalog':
    if (!$workshop->isEnabled()) {
      $security->fail('Workshop is disabled.', 403);
    }
    $security->respond(['packages' => $communityPackages()->catalog()]);
    break;

  /* ── INSTALL ────────────────────────────────────────────────
     Dual mode:
       • body {source:"owner/repo|zip-url|manifest-url"} → dynamic community
         install streamed over SSE (Manifest v1 pipeline);
       • body {id:"apache"}                              → legacy static
         extension install, byte-for-byte unchanged below.            */
  case 'workshop-install':
    if ($method !== 'POST') {
      $security->fail('Use POST to install.', 405);
    }
    if (!$workshop->isEnabled()) {
      $security->fail('Workshop is disabled.', 403);
    }
    $source = trim((string) ($input['source'] ?? ''));
    if ($source !== '') {
      $streamCommunity(function (IdeWorkshopPackage $svc) use ($source): void {
        $svc->installStream($source);
      });
      exit;
    }
    $id = trim((string) ($input['id'] ?? ''));
    if ($id === '') {
      $security->fail('Extension ID is required.');
    }
    $security->respond($workshop->install($id));
    break;

  /* ── UPDATE A COMMUNITY PACKAGE (re-run its recorded source) ──
     Streams SSE like the installer. Community-only: static extensions
     have no update flow.                                        */
  case 'workshop-update':
    if ($method !== 'POST') {
      $security->fail('Use POST to update.', 405);
    }
    if (!$workshop->isEnabled()) {
      $security->fail('Workshop is disabled.', 403);
    }
    $id = trim((string) ($input['id'] ?? ''));
    if ($id === '') {
      $security->fail('Package ID is required.');
    }
    if (!preg_match('/^[a-z0-9][a-z0-9._-]{2,63}$/', $id)) {
      $security->fail('Invalid package ID.');
    }
    if (!$communityPackages()->has($id)) {
      $security->fail('Unknown package: ' . $id);
    }
    $streamCommunity(function (IdeWorkshopPackage $svc) use ($id): void {
      $svc->updateStream($id);
    });
    exit;

  /* ── UNINSTALL ──────────────────────────────────────────────
     Community-first dispatch: an id present in packages.json is a community
     package; anything else falls through to the legacy extension uninstall. */
  case 'workshop-uninstall':
    if ($method !== 'POST') {
      $security->fail('Use POST to uninstall.', 405);
    }
    if (!$workshop->isEnabled()) {
      $security->fail('Workshop is disabled.', 403);
    }
    $id = trim((string) ($input['id'] ?? ''));
    if ($id === '') {
      $security->fail('Extension ID is required.');
    }
    try {
      if ($communityPackages()->has($id)) {
        $security->respond($communityPackages()->uninstall($id));
      }
    } catch (\InvalidArgumentException $e) {
      $security->fail($e->getMessage());
    } catch (\RuntimeException $e) {
      $security->fail($e->getMessage());
    }
    $security->respond($workshop->uninstall($id));
    break;

  /* ── TOGGLE ENABLE/DISABLE ──────────────────────────────────
     Community rows accept an explicit {enabled:bool}; legacy extensions flip. */
  case 'workshop-toggle':
    if ($method !== 'POST') {
      $security->fail('Use POST to toggle.', 405);
    }
    if (!$workshop->isEnabled()) {
      $security->fail('Workshop is disabled.', 403);
    }
    $id = trim((string) ($input['id'] ?? ''));
    if ($id === '') {
      $security->fail('Extension ID is required.');
    }
    try {
      if ($communityPackages()->has($id)) {
        $enabled = null;
        if (array_key_exists('enabled', $input)) {
          $enabled = filter_var($input['enabled'], FILTER_VALIDATE_BOOLEAN);
        }
        $security->respond(['package' => $communityPackages()->toggle($id, $enabled)]);
      }
    } catch (\InvalidArgumentException $e) {
      $security->fail($e->getMessage());
    } catch (\RuntimeException $e) {
      $security->fail($e->getMessage());
    }
    $security->respond($workshop->toggle($id));
    break;

  /* ── SERVE A COMMUNITY PACKAGE FILE ─────────────────────────
     GET ?api=workshop-asset&id=<slug>&f=<relative/path> — jailed to
     IDE_APP/.workshop/packages/<id>/ with index.php's containment rules.
     NOT a JSON envelope: browsers load these as css/js/img subresources. */
  case 'workshop-asset':
    if (!$workshop->isEnabled()) {
      http_response_code(403);
      exit;
    }
    $pkgId = (string) ($_GET['id'] ?? '');
    $pkgFile = (string) ($_GET['f'] ?? '');
    if ($pkgId === '' || $pkgFile === '') {
      http_response_code(400);
      exit;
    }
    $communityPackages()->serveAsset($pkgId, $pkgFile); // exits internally
    exit;

  /* ── GET WORKSHOP STATUS ─────────────────────────────────── */
  case 'workshop-status':
    if (!$workshop->isEnabled()) {
      $security->respond(['enabled' => false, 'extensions' => [], 'projects' => []]);
      break;
    }
    $statusData = $workshop->getStatus();
    // ★ PHASE 3: Include detected projects in the status response
    $statusData['projects'] = $workshop->scanForProjects();
    $security->respond($statusData);
    break;

  /* ═══════════════════════════════════════════════════════════
    APACHE-SPECIFIC ENDPOINTS (Phase 2)
    ═══════════════════════════════════════════════════════════ */

  case 'workshop-apache-install':
    if ($method !== 'POST') {
      $security->fail('Use POST to install Apache.', 405);
    }
    if (!$workshop->isEnabled()) {
      $security->fail('Workshop is disabled.', 403);
    }
    $workshop->installApacheStream();
    exit;

  case 'workshop-apache-uninstall':
    if ($method !== 'POST') {
      $security->fail('Use POST to uninstall Apache.', 405);
    }
    if (!$workshop->isEnabled()) {
      $security->fail('Workshop is disabled.', 403);
    }
    $security->respond($workshop->uninstall('apache'));
    break;

  case 'workshop-apache-start':
    if ($method !== 'POST') {
      $security->fail('Use POST to start Apache.', 405);
    }
    if (!$workshop->isEnabled()) {
      $security->fail('Workshop is disabled.', 403);
    }
    $security->respond($workshop->startApache());
    break;

  case 'workshop-apache-stop':
    if ($method !== 'POST') {
      $security->fail('Use POST to stop Apache.', 405);
    }
    if (!$workshop->isEnabled()) {
      $security->fail('Workshop is disabled.', 403);
    }
    $security->respond($workshop->stopApache());
    break;

  case 'workshop-apache-restart':
    if ($method !== 'POST') {
      $security->fail('Use POST to restart Apache.', 405);
    }
    if (!$workshop->isEnabled()) {
      $security->fail('Workshop is disabled.', 403);
    }
    $security->respond($workshop->restartApache());
    break;

  case 'workshop-apache-status':
    if (!$workshop->isEnabled()) {
      $security->respond(['installed' => false, 'running' => false]);
      break;
    }
    $security->respond($workshop->getApacheStatus());
    break;

  /* ── DIAGNOSE APACHE ─────────────────────────────────────── */
  case 'workshop-apache-diagnose':
    if (!$workshop->isEnabled()) {
      $security->respond(['checks' => [], 'verdict' => 'Workshop disabled']);
      break;
    }
    $security->respond($workshop->diagnoseApache());
    break;
  /* ═══════════════════════════════════════════════════════════
    WORKSHOP LOGS (NEW)
    ═══════════════════════════════════════════════════════════ */

  case 'workshop-logs':
    $logger = new IdeWorkshopLogger();
    $security->respond($logger->getLogs());
    break;

  case 'workshop-logs-clear':
    if ($method !== 'POST') {
      $security->fail('Use POST to clear logs.', 405);
    }
    $logger = new IdeWorkshopLogger();
    $logger->clearLogs();
    $security->respond(['cleared' => true]);
    break;

  default:
    $security->fail('Unknown workshop action: ' . $action, 404);
}
