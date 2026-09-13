<?php

declare(strict_types=1);
/**
 * ═══════════════════════════════════════════════════════════════════════════
 *  QUIRKY IDE — MOBILE-ONLY ROUTES (Phase 2 · Separation)
 * ═══════════════════════════════════════════════════════════════════════════
 *
 *  API endpoints that ONLY exist on the mobile platform.
 *  Desktop (ide/) will never see these routes.
 *
 *  Current endpoints:
 *    mobile.status       → Returns mobile platform info (battery, viewport)
 *    mobile.refresh-token → Issues a short-lived token for pull-to-refresh
 *    mobile.photo-upload  → Accepts photo uploads from the camera
 *    mobile.deep-link     → Resolves deep links into IDE actions
 *
 *  This file is loaded by app/api.php and merges into the route table.
 *
 * ═══════════════════════════════════════════════════════════════════════════
 * @var mixed $action
 * @var mixed $method
 * @var mixed $config
 * @var mixed $input
 * @var mixed $security
 * @var mixed $fs
 */
switch ($action) {
  /* ── MOBILE STATUS ────────────────────────────────────────────
Returns basic mobile platform info so the client JS knows
what it's working with.                                     */
  case 'mobile.status':
    $security->respond([
      'platform'    => 'mobile',
      'version'     => $config['meta']['version'] ?? '1.0',
      'features'    => $config['features'] ?? [],
      'previewable' => $config['preview']['previewable'] ?? [],
    ]);
    break;
  /* ── PULL-TO-REFRESH TOKEN ────────────────────────────────────
Issues a short-lived token the mobile client uses to
authenticate pull-to-refresh requests. Prevents abuse.     */
  case 'mobile.refresh-token':
    if ($method !== 'POST') {
      $security->fail('Use POST to get a refresh token.', 405);
    }
    // Generate a short-lived token (5 minutes)
    $token = bin2hex(random_bytes(16));
    $expiresAt = time() + 300; // 5 minutes
    // Store in session for validation
    $security->startSession();
    $_SESSION['mobile_refresh_token'] = $token;
    $_SESSION['mobile_refresh_expires'] = $expiresAt;
    $security->respond([
      'token'     => $token,
      'expiresAt' => $expiresAt,
      'validFor'  => 300,
    ]);
    break;
  /* ── PHOTO UPLOAD ─────────────────────────────────────────────
Accepts photo uploads from the mobile camera. Photos are
stored inside the workspace under a 'uploads/' folder.
Only images are allowed.                                    */
  case 'mobile.photo-upload':
    if ($method !== 'POST') {
      $security->fail('Use POST to upload a photo.', 405);
    }
    // Check if file was uploaded
    if (empty($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
      $security->fail('No photo uploaded or upload failed.');
    }
    $file = $_FILES['photo'];
    // Validate it's actually an image
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    if (!in_array($mimeType, $allowedTypes, true)) {
      $security->fail('Only JPEG, PNG, WebP, and GIF images are allowed.');
    }
    // Max 10 MB for photos
    $maxSize = 10 * 1024 * 1024;
    if ($file['size'] > $maxSize) {
      $security->fail('Photo is too large (max 10 MB).');
    }
    // Build a safe filename
    $extension = match ($mimeType) {
      'image/jpeg' => 'jpg',
      'image/png'  => 'png',
      'image/webp' => 'webp',
      'image/gif'  => 'gif',
      default      => 'img',
    };
    $fileName = 'upload_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
    $relativePath = 'uploads/' . $fileName;
    // Resolve the safe absolute path
    $absolutePath = $security->resolvePath($relativePath);
    // Create uploads/ folder if it doesn't exist
    $uploadDir = dirname($absolutePath);
    if (!is_dir($uploadDir)) {
      mkdir($uploadDir, 0777, true);
    }
    // Move the file into the workspace
    if (!move_uploaded_file($file['tmp_name'], $absolutePath)) {
      $security->fail('Failed to save the photo.');
    }
    $security->respond([
      'uploaded' => true,
      'path'     => $relativePath,
      'name'     => $fileName,
      'size'     => $file['size'],
      'mime'     => $mimeType,
    ]);
    break;
  /* ── DEEP LINK ────────────────────────────────────────────────
Resolves a deep link (e.g. "quirky://open?file=index.php")
into an IDE action. Used when the PWA is opened from a
notification or shortcut.                                  */
  case 'mobile.deep-link':
    if ($method !== 'POST') {
      $security->fail('Use POST for deep links.', 405);
    }
    $link = (string) ($input['link'] ?? '');
    if ($link === '') {
      $security->fail('No deep link provided.');
    }
    // Parse the deep link
    // Format: quirky://action?param=value
    $parsed = [];
    if (preg_match('#^quirky://([a-z-]+)\??(.*)$#i', $link, $m)) {
      $parsed['action'] = $m[1];
      if (!empty($m[2])) {
        parse_str($m[2], $parsed['params']);
      }
    }
    if (empty($parsed['action'])) {
      $security->fail('Invalid deep link format. Expected: quirky://action?params');
    }
    // Route to the appropriate IDE action
    switch ($parsed['action']) {
      case 'open':
      case 'file':
        $security->respond([
          'redirect' => 'editor',
          'openFile' => $parsed['params']['file'] ?? $parsed['params']['path'] ?? '',
        ]);
        break;
      case 'files':
      case 'explorer':
        $security->respond(['redirect' => 'files']);
        break;
      case 'terminal':
        $security->respond(['redirect' => 'terminal']);
        break;
      case 'preview':
        $security->respond(['redirect' => 'preview']);
        break;
      default:
        $security->fail('Unknown deep link action: ' . $parsed['action']);
    }
    break;
  /* ── DEVICE TIER REPORT ─────────────────────────────────────
    The phone sends RAM / cores / storage quota once per launch.
    We compute the hardware-based file limit, store it for all
    future requests, and echo it back so the UI can display it. */
  case 'mobile.device-tier':
    if ($method !== 'POST') {
      $security->fail('Use POST to report the device tier.', 405);
    }

    require_once __DIR__ . '/../mobile-limits.php';

    $ram   = (float) ($input['deviceMemory'] ?? 0);
    $cores = (int) ($input['cores'] ?? 0);
    $quota = (float) ($input['storageQuota'] ?? 0);

    $limit = quirkyMobileComputeFileLimit($ram, $cores, $quota);
    $tier  = quirkyMobileComputeDeviceTier($ram, $cores, $quota);

    // Keep the TRUE hardware values for the persisted state file...
    $persistTier  = $tier;
    $persistLimit = $limit;

    /* ★ TEST OVERRIDE: answer with the forced tier/limit so the UI
           shows (and keeps) the tier you are testing. */
    $override = quirkyMobileReadTierOverride();
    if ($override !== null) {
      if (isset($override['tier'])) {
        $tier = $override['tier'];
      }
      if (isset($override['limit'])) {
        $limit = max(512 * 1024, min((int) $override['limit'], 264 * 1024 * 1024));
      }
    }

    $policy = quirkyMobileGetDeviceTierPolicy($tier);

    quirkyMobilePersistDeviceState([
      'ram'       => $ram,
      'ramMb'     => (int) round($ram * 1024),
      'cores'     => $cores,
      'quota'     => $quota,
      'storageMb' => (int) round($quota / (1024 * 1024)),
      'tier'      => $persistTier,
      'limit'     => $persistLimit,
      'source'    => 'device-report',
      'time'      => time(),
    ]);

    $security->respond([
      'limit'      => $limit,
      'limitHuman' => quirkyMobileHumanBytes($limit),
      'deviceTier' => $tier,
      'policy'     => $policy,
      'hardware'   => [
        'ramGb'     => $ram,
        'ramMb'     => (int) round($ram * 1024),
        'cores'     => $cores,
        'storageMb' => (int) round($quota / 1024 / 1024),
        'tier'      => $tier,
      ],
    ]);
    break;

  /* ── ★ v13 STORAGE & CACHE (Settings → Storage & Cache card) ────────
   Scan = report sizes of a hard-coded whitelist of safe-to-delete
   caches. Clean = delete one target (or all). No user-supplied paths
   ever reach the filesystem, so there is zero traversal risk.      */
  case 'mobile.storage-scan':
    $security->respond(['items' => quirkyStorageScan()]);
    break;
  case 'mobile.storage-clean':
    if ($method !== 'POST') {
      $security->fail('Use POST to clean caches.', 405);
    }
    $target  = (string) ($input['target'] ?? '');
    $cleaned = quirkyStorageClean($target);
    if ($cleaned === null) {
      $security->fail('Unknown cache target: ' . $target);
    }
    $security->respond(['cleaned' => $cleaned, 'items' => quirkyStorageScan()]);
    break;
}

/* ═══════════════════════════════════════════════════════════════
   ★ v13 STORAGE & CACHE HELPERS
   The whitelist below is the WHOLE truth of what can be cleaned.
   Deliberately NOT listed: .workshop/packages/, extensions.json,
   packages.json, workshop.log, .mobile-device.json, the workspace.
═══════════════════════════════════════════════════════════════ */
function quirkyStorageTargets(): array
{
  $tmp = rtrim(sys_get_temp_dir(), '/\\');
  return [
    [
      'id'     => 'bundle_cache',
      'group'  => 'IDE Caches',
      'label'  => 'JS & CSS bundle caches',
      'desc'   => 'Pre-built mobile bundles — rebuilt automatically on the next load',
      'kind'   => 'glob',
      'paths'  => [IDE_APP . '/.bundle-mobile-js-*.cache', IDE_APP . '/.bundle-mobile-css-*.cache'],
      'maxAge' => 0,
    ],
    [
      'id'     => 'watch_cache',
      'group'  => 'IDE Caches',
      'label'  => 'Workspace watch cache',
      'desc'   => 'File-tree fingerprint (.watch-cache.json) — rescanned on the next poll',
      'kind'   => 'glob',
      'paths'  => [IDE_APP . '/.watch-cache.json'],
      'maxAge' => 0,
    ],
    [
      'id'     => 'export_cache',
      'group'  => 'Transfers',
      'label'  => 'Export cache & expired links',
      'desc'   => 'Zipped exports + 10-minute download links (valid links re-zip on demand)',
      'kind'   => 'export',
      'paths'  => [IDE_APP . '/.export-cache', IDE_APP . '/.export-tokens.json'],
      'maxAge' => 0,
    ],
    [
      'id'     => 'import_staging',
      'group'  => 'Transfers',
      'label'  => 'Import staging leftovers',
      'desc'   => 'Copies of picked files that were never imported (older than 24 h)',
      'kind'   => 'dir-aged',
      'paths'  => [dirname($tmp) . '/import-staging'],
      'maxAge' => 86400,
    ],
    [
      'id'     => 'ws_servers',
      'group'  => 'Workshop',
      'label'  => 'Stale preview server records',
      'desc'   => 'State files (.workshop/servers) of preview micro-servers that no longer run',
      'kind'   => 'server-state',
      'paths'  => [IDE_APP . '/.workshop/servers'],
      'maxAge' => 86400,
    ],
    [
      'id'     => 'ws_tmp',
      'group'  => 'Workshop',
      'label'  => 'Workshop temp leftovers',
      'desc'   => 'Leftover download / extract temp files in .workshop/tmp (older than 24 h)',
      'kind'   => 'dir-aged',
      'paths'  => [IDE_APP . '/.workshop/tmp'],
      'maxAge' => 86400,
    ],
    [
      'id'     => 'term_temp',
      'group'  => 'Terminal',
      'label'  => 'Terminal session temp files',
      'desc'   => 'Old PID files, stdin mailboxes & cwd notes (older than 24 h)',
      'kind'   => 'glob',
      'paths'  => [$tmp . '/quirky_term_*.pid', $tmp . '/quirky_stdin_*.in', $tmp . '/quirky_term_cwd_*.txt'],
      'maxAge' => 86400,
    ],
  ];
}

/** Files matching a target's glob patterns, honouring its maxAge (0 = any age). */
function quirkyStorageGlobFiles(array $target): array
{
  $files  = [];
  $maxAge = (int) ($target['maxAge'] ?? 0);
  foreach ($target['paths'] as $pattern) {
    foreach (glob($pattern) ?: [] as $f) {
      if (!is_file($f)) continue;
      if ($maxAge > 0 && (time() - (int) filemtime($f)) < $maxAge) continue;
      $files[] = $f;
    }
  }
  return $files;
}

/** All files inside $dir older than $maxAge seconds (recursive). */
function quirkyStorageAgedFiles(string $dir, int $maxAge): array
{
  if (!is_dir($dir)) return [];
  $out = [];
  $it  = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::LEAVES_ONLY
  );
  foreach ($it as $f) {
    if (!$f->isFile()) continue;
    if ((time() - (int) $f->getMTime()) < $maxAge) continue;
    $out[] = $f->getPathname();
  }
  return $out;
}

/** true = alive · false = dead · null = cannot tell (Windows) → caller uses age rule. */
function quirkyStoragePidAlive(int $pid): ?bool
{
  if ($pid <= 1) return false;
  if (is_dir('/proc')) return is_dir('/proc/' . $pid);          // Android / Linux
  if (strncasecmp(PHP_OS, 'WIN', 3) === 0) return null;         // Windows
  return function_exists('posix_kill') ? @posix_kill($pid, 0) : null;
}

/** Preview-server state files whose process is gone (or unknown + older than maxAge). */
function quirkyStorageStaleServerStates(string $dir, int $maxAge): array
{
  if (!is_dir($dir)) return [];
  $out = [];
  foreach (glob($dir . '/*.json') ?: [] as $f) {
    $st    = json_decode((string) @file_get_contents($f), true);
    $pid   = is_array($st) ? (int) ($st['pid'] ?? 0) : 0;
    $alive = $pid > 0 ? quirkyStoragePidAlive($pid) : false;
    if ($alive === true) continue;                            // running → keep
    if ($alive === null && (time() - (int) filemtime($f)) < $maxAge) continue;
    $out[] = $f;
  }
  return $out;
}

/** Every .zip in the export cache (valid tokens simply re-zip on demand). */
function quirkyStorageExportFiles(): array
{
  $files    = [];
  $cacheDir = IDE_APP . '/.export-cache';
  if (is_dir($cacheDir)) {
    foreach (glob($cacheDir . '/*') ?: [] as $f) {
      if (is_file($f)) $files[] = $f;
    }
  }
  return $files;
}

/** Resolve one target to the concrete file list it would clean right now. */
function quirkyStorageFilesFor(array $target): array
{
  switch ($target['kind']) {
    case 'glob':
      return quirkyStorageGlobFiles($target);
    case 'dir-aged':
      $out = [];
      foreach ($target['paths'] as $d) {
        $out = array_merge($out, quirkyStorageAgedFiles($d, (int) $target['maxAge']));
      }
      return $out;
    case 'server-state':
      $out = [];
      foreach ($target['paths'] as $d) {
        $out = array_merge($out, quirkyStorageStaleServerStates($d, (int) $target['maxAge']));
      }
      return $out;
    case 'export':
      return quirkyStorageExportFiles();
  }
  return [];
}

/** Scan all targets → [{id, group, label, desc, bytes, count}]. */
function quirkyStorageScan(): array
{
  $items = [];
  foreach (quirkyStorageTargets() as $t) {
    $files = quirkyStorageFilesFor($t);
    $bytes = 0;
    foreach ($files as $f) $bytes += (int) @filesize($f);
    $items[] = [
      'id'    => $t['id'],
      'group' => $t['group'],
      'label' => $t['label'],
      'desc'  => $t['desc'],
      'bytes' => $bytes,
      'count' => count($files),
    ];
  }
  return $items;
}

/** Clean one target id or 'all'. Returns ['freed' => bytes, 'removed' => n] or null. */
function quirkyStorageClean(string $targetId): ?array
{
  $chosen = [];
  foreach (quirkyStorageTargets() as $t) {
    if ($targetId === 'all' || $t['id'] === $targetId) $chosen[] = $t;
  }
  if ($targetId !== 'all' && !$chosen) return null;
  $freed   = 0;
  $removed = 0;
  foreach ($chosen as $t) {
    foreach (quirkyStorageFilesFor($t) as $f) {
      $b = (int) @filesize($f);
      if (@unlink($f)) {
        $freed += $b;
        $removed++;
      }
    }
    if ($t['kind'] === 'export') {
      // Prune expired download tokens; valid ones re-zip on demand.
      $tf     = IDE_APP . '/.export-tokens.json';
      $tokens = json_decode((string) @file_get_contents($tf), true);
      if (is_array($tokens)) {
        $now  = time();
        $keep = array_values(array_filter($tokens, function ($tok) use ($now) {
          return is_array($tok) && (int) ($tok['time'] ?? 0) >= $now - 600;
        }));
        @file_put_contents($tf, json_encode($keep, JSON_UNESCAPED_SLASHES), LOCK_EX);
      }
    }
  }
  return ['freed' => $freed, 'removed' => $removed];
}
