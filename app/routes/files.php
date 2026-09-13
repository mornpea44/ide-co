<?php

declare(strict_types=1);

/**
 * ═══════════════════════════════════════════════════════════════════════════
 *  QUIRKY IDE — FILE ROUTES (split from api.php · Phase 3)
 *  ★ Explorer Upgrade: + files-export-token, files-import-path,
 *    workspace-info, workspace-presets, workspace-switch
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (!defined('QUIRKY_EXPORT_MAX_BYTES')) {
  define('QUIRKY_EXPORT_MAX_BYTES', 64 * 1024 * 1024); // 64 MB per export
}

/**
 * @var mixed $action
 * @var mixed $method
 * @var mixed $config
 */
switch ($action) {

  case 'files-batch':
    if ($method !== 'POST') {
      $security->fail('Use POST to read a batch of files.', 405);
    }
    $paths = $input['paths'] ?? [];
    if (!is_array($paths)) {
      $security->fail('"paths" must be an array of file paths.');
    }
    $security->respond($fs->readFilesBatch($paths));
    break;

  case 'tree':
    $treePath = (string) ($_GET['path'] ?? '');
    $treeDepth = isset($_GET['depth']) ? (int) $_GET['depth'] : null;
    if ($treeDepth !== null) {
      $treeDepth = max(1, min(6, $treeDepth));
    }
    $treeSort = strtolower(trim((string) ($_GET['sort'] ?? '')));
    if (!in_array($treeSort, ['name', 'size', 'mtime'], true)) {
      $treeSort = 'name';
    }
    /* ★ SORT DIRECTION: 'asc' (default) or 'desc' */
    $treeDir = strtolower(trim((string) ($_GET['dir'] ?? 'asc')));
    if (!in_array($treeDir, ['asc', 'desc'], true)) {
      $treeDir = 'asc';
    }
    $treeHidden = (($_GET['hidden'] ?? '') === '1');
    if ($treePath !== '') {
      $security->respond(['tree' => $fs->getSubTree($treePath, $treeDepth, $treeSort, $treeHidden, $treeDir)]);
    }
    $security->respond(['tree' => $fs->getTree(10, $treeSort, $treeHidden, $treeDir)]);
    break;

  case 'file':
    if ($method === 'GET') {
      $path = (string) ($_GET['path'] ?? '');
      $security->respond($fs->readFile($path));
    } elseif ($method === 'POST' || $method === 'PUT') {
      $path    = (string) ($input['path'] ?? '');
      $content = (string) ($input['content'] ?? '');
      $security->respond($fs->saveFile($path, $content));
    } elseif ($method === 'DELETE') {
      $path = (string) ($_GET['path'] ?? '');
      $security->respond($fs->deleteFile($path));
    } else {
      $security->fail('Unsupported HTTP method for "file".', 405);
    }
    break;

  case 'file-info':
    $path = (string) ($_GET['path'] ?? '');
    $security->respond($fs->getFileInfo($path));
    break;

  case 'copy':
    if ($method !== 'POST') {
      $security->fail('Use POST to copy.', 405);
    }
    $from = (string) ($input['from'] ?? '');
    $to   = (string) ($input['to'] ?? '');
    $security->respond($fs->copyRecursive($from, $to));
    break;

  case 'rename':
    if ($method !== 'POST') {
      $security->fail('Use POST to rename.', 405);
    }
    $from = (string) ($input['from'] ?? '');
    $to   = (string) ($input['to'] ?? '');
    $security->respond($fs->rename($from, $to));
    break;

  case 'folder':
    if ($method === 'POST') {
      $path = (string) ($input['path'] ?? '');
      $security->respond($fs->createFolder($path));
    } elseif ($method === 'DELETE') {
      $path = (string) ($_GET['path'] ?? '');
      $security->respond($fs->deleteFolder($path));
    } else {
      $security->fail('Unsupported HTTP method for "folder".', 405);
    }
    break;

  case 'watch':
    $security->respond($fs->getWatchData());
    break;

  /* ── ★ EXPORT TOKEN ──────────────────────────────────────────────
       Creates a short-lived token; the actual bytes are served by
       index.php?export-token=… (before the auth gate, because the
       Android DownloadListener has no session cookies).            ── */
  case 'files-export-token':
    if ($method !== 'POST') {
      $security->fail('Use POST to create an export link.', 405);
    }
    $exportPath = (string) ($input['path'] ?? '');
    if ($exportPath === '') {
      // Whole workspace → zip everything.
      $targetAbs = realpath(IDE_WORKSPACE);
      if ($targetAbs === false) {
        $security->fail('Workspace not found.');
      }
      $exportType = 'folder';
      $exportName = (basename($targetAbs) !== '' ? basename($targetAbs) : 'workspace') . '.zip';
    } else {
      $security->checkBlockedSegments($exportPath);
      $targetAbs = $security->resolvePath($exportPath);
      $info = $fs->getFileInfo($exportPath);
      if (!$info || !empty($info['notFound'])) {
        $security->fail('File not found: ' . $exportPath, 404);
      }
      $exportType = (string) $info['type'];
      $exportName = $exportType === 'folder' ? ($info['name'] . '.zip') : (string) $info['name'];
    }
    $measure = quirkyFilesMeasure($targetAbs, QUIRKY_EXPORT_MAX_BYTES);
    if (!$measure['ok']) {
      $security->fail('Too large to export — the limit is '
        . round(QUIRKY_EXPORT_MAX_BYTES / 1048576) . ' MB per export.');
    }
    $tokens = quirkyFilesLoadTokens();
    $now = time();
    foreach ($tokens as $k => $t) {
      if ((int) ($t['time'] ?? 0) < $now - 600) {
        unset($tokens[$k]);
        @unlink(IDE_APP . '/.export-cache/' . $k . '.zip');
      }
    }
    $token = bin2hex(random_bytes(16));
    $tokens[$token] = [
      'path' => str_replace('\\', '/', $targetAbs),
      'name' => $exportName,
      'type' => $exportType,
      'time' => $now,
    ];
    quirkyFilesSaveTokens($tokens);
    $security->respond([
      'token' => $token,
      'name'  => $exportName,
      'type'  => $exportType,
      'size'  => $measure['bytes'],
      'url'   => 'index.php?export-token=' . $token,
    ]);
    break;

  /* ── ★ SERVER-SIDE IMPORT ──────────────────────────────────────────
       Copies an absolute source path (chosen via the Android picker,
       or staged into the app cache) INTO the workspace. The source is
       strictly allow-listed; the destination is strictly jailed.    ── */
  case 'files-import-path':
    if ($method !== 'POST') {
      $security->fail('Use POST to import files.', 405);
    }
    $from      = (string) ($input['from'] ?? '');
    $dest      = (string) ($input['dest'] ?? '');
    $overwrite = !empty($input['overwrite']);
    if ($from === '' || $dest === '') {
      $security->fail('Both "from" and "dest" are required.');
    }
    $fromReal = realpath($from);
    if ($fromReal === false || !file_exists($fromReal)) {
      $security->fail('Import source not found.', 404);
    }
    $fromReal = str_replace('\\', '/', $fromReal);
    if (!quirkyFilesImportSourceAllowed($fromReal)) {
      $security->fail('Files can only be imported from device storage or the app picker.', 403);
    }
    $wsReal = str_replace('\\', '/', (string) realpath(IDE_WORKSPACE));
    if (strpos($fromReal . '/', $wsReal . '/') === 0) {
      $security->fail('That item is already inside the workspace.');
    }
    if (strpos($fromReal . '/', str_replace('\\', '/', IDE_APP) . '/') === 0) {
      $security->fail('Cannot import from the application folder.', 403);
    }
    $destClean = str_replace('\\', '/', trim($dest));
    $destClean = ltrim($destClean, '/');
    if ($destClean === '' || strpos($destClean, "\0") !== false) {
      $security->fail('Invalid destination path.');
    }
    foreach (explode('/', $destClean) as $segment) {
      if ($segment === '..' || $segment === '') {
        $security->fail('Access denied: path escapes the workspace.');
      }
    }
    $destAbs = $wsReal . '/' . $destClean;
    if (strpos($destAbs . '/', $wsReal . '/') !== 0) {
      $security->fail('Access denied: path escapes the workspace.');
    }
    if (file_exists($destAbs)) {
      if (!$overwrite) {
        $security->fail('Destination already exists: ' . $destClean);
      }
      quirkyFilesImportDelete($destAbs);
    }
    $blocked = $config['security']['blocked_segments'] ?? [];
    $stats   = ['files' => 0, 'folders' => 0];
    if (is_file($fromReal)) {
      $parent = dirname($destAbs);
      if (!is_dir($parent)) @mkdir($parent, 0777, true);
      if (!copy($fromReal, $destAbs)) {
        $security->fail('Could not copy the file into the workspace.');
      }
      $stats['files'] = 1;
    } elseif (is_dir($fromReal)) {
      quirkyFilesImportCopyDir($fromReal, $destAbs, $blocked, $stats);
    } else {
      $security->fail('Unsupported import source.');
    }
    $security->respond([
      'imported' => true,
      'dest'     => $destClean,
      'files'    => $stats['files'],
      'folders'  => $stats['folders'],
    ]);
    break;

  /* ── ★ DYNAMIC WORKSPACE ──────────────────────────────────────────── */
  case 'workspace-info':
    $wsReal = str_replace('\\', '/', (string) (realpath(IDE_WORKSPACE) ?: IDE_WORKSPACE));
    [$fileCount, $folderCount, $totalBytes, $truncated] = quirkyFilesCountTree($wsReal, 20000);
    $defaultReal = @realpath(IDE_ROOT . '/workspace');
    $isDefault = ($defaultReal !== false)
      && (str_replace('\\', '/', $defaultReal) === $wsReal);
    $free = @disk_free_space($wsReal);
    $security->respond([
      'path'           => $wsReal,
      'isDefault'      => $isDefault,
      'files'          => $fileCount,
      'folders'        => $folderCount,
      'totalSize'      => $totalBytes,
      'totalSizeHuman' => quirkyFilesHumanBytes($totalBytes),
      'truncated'      => $truncated,
      'diskFree'       => $free === false ? null : (int) $free,
      'diskFreeHuman'  => $free === false ? '—' : quirkyFilesHumanBytes((int) $free),
      'sharedWritable' => @is_writable('/storage/emulated/0'),
    ]);
    break;

  case 'workspace-presets':
    $shared = '/storage/emulated/0';
    $presets = [
      ['id' => 'default',       'label' => 'App default (private)', 'path' => '__default__',                    'hint' => 'Ships with the app — always available'],
      ['id' => 'shared-quirky', 'label' => 'Shared storage',        'path' => $shared . '/QuirkyIDE',           'hint' => 'Visible to all file managers'],
      ['id' => 'downloads',     'label' => 'Downloads',             'path' => $shared . '/Download/QuirkyIDE',  'hint' => 'Inside the system Downloads folder'],
      ['id' => 'documents',     'label' => 'Documents',             'path' => $shared . '/Documents/QuirkyIDE', 'hint' => 'Inside the system Documents folder'],
    ];
    foreach ($presets as &$p) {
      if ($p['path'] === '__default__') {
        $p['exists']   = true;
        $p['writable'] = true;
        continue;
      }
      $p['exists'] = is_dir($p['path']);
      $probe = $p['path'];
      while ($probe !== '/' && $probe !== '' && !file_exists($probe)) {
        $probe = dirname($probe);
      }
      $p['writable'] = @is_writable($probe);
    }
    unset($p);
    $security->respond([
      'presets'        => $presets,
      'sharedRoot'     => $shared,
      'sharedWritable' => @is_writable($shared),
    ]);
    break;

  case 'workspace-switch':
    if ($method !== 'POST') {
      $security->fail('Use POST to switch the workspace.', 405);
    }
    $target    = trim((string) ($input['path'] ?? ''));
    $copyFiles = !empty($input['copyFiles']);
    $overrideFile = IDE_APP . '/.workspace-config.json';
    if ($target === '__default__') {
      @unlink($overrideFile);
      @unlink(IDE_APP . '/.watch-cache.json');
      $security->respond([
        'switched'  => true,
        'path'      => IDE_ROOT . '/workspace',
        'isDefault' => true,
      ]);
    }
    if (
      !function_exists('quirkyWorkspaceOverrideIsValid')
      || !quirkyWorkspaceOverrideIsValid($target)
    ) {
      $security->fail('That folder cannot be used as a workspace. '
        . 'Use a writable folder on device storage (for example /storage/emulated/0/QuirkyIDE).');
    }
    $newReal = rtrim(str_replace('\\', '/', (string) realpath($target)), '/');
    $curReal = str_replace('\\', '/', (string) (realpath(IDE_WORKSPACE) ?: IDE_WORKSPACE));
    if ($newReal === $curReal) {
      $security->fail('That folder is already the active workspace.');
    }
    if (strpos($newReal . '/', $curReal . '/') === 0) {
      $security->fail('The new folder is inside the current workspace — pick a folder outside it.');
    }
    if (strpos($curReal . '/', $newReal . '/') === 0) {
      $security->fail('The new folder contains the current workspace — pick a different folder.');
    }
    $copied = ['files' => 0, 'folders' => 0];
    if ($copyFiles) {
      // Everything the user owns moves across (empty blocked list on purpose).
      quirkyFilesImportCopyDir($curReal, $newReal, [], $copied);
    }
    @file_put_contents($overrideFile, json_encode([
      'path'          => $newReal,
      'previous'      => $curReal,
      'time'          => time(),
      'copiedFiles'   => $copied['files'],
      'copiedFolders' => $copied['folders'],
    ], JSON_UNESCAPED_SLASHES), LOCK_EX);
    // Fresh baseline for the watch poller on the next boot.
    @unlink(IDE_APP . '/.watch-cache.json');
    $security->respond([
      'switched'      => true,
      'path'          => $newReal,
      'copiedFiles'   => $copied['files'],
      'copiedFolders' => $copied['folders'],
    ]);
    break;
}

/* ═══════════════════════════════════════════════════════════════════════
HELPERS
═══════════════════════════════════════════════════════════════════════ */

function quirkyFilesHumanBytes(int $b): string
{
  if ($b <= 0) return '0 B';
  $u = ['B', 'KB', 'MB', 'GB'];
  $i = min((int) floor(log($b, 1024)), count($u) - 1);
  return round($b / pow(1024, $i), 1) . ' ' . $u[$i];
}

/** Count files/folders/bytes with a hard entry cap (never scans forever). */
function quirkyFilesCountTree(string $dir, int $entryCap = 20000): array
{
  $files = 0;
  $folders = 0;
  $bytes = 0;
  $count = 0;
  $truncated = false;
  $stack = [$dir];
  while (!empty($stack)) {
    $current = array_pop($stack);
    $entries = @scandir($current);
    if ($entries === false) continue;
    foreach ($entries as $entry) {
      if ($entry === '.' || $entry === '..') continue;
      $count++;
      if ($count > $entryCap) {
        $truncated = true;
        break 2;
      }
      $abs = $current . '/' . $entry;
      if (@is_link($abs)) continue;
      if (@is_dir($abs)) {
        $folders++;
        $stack[] = $abs;
      } elseif (@is_file($abs)) {
        $files++;
        $bytes += (int) @filesize($abs);
      }
    }
  }
  return [$files, $folders, $bytes, $truncated];
}

/** Measure a file or folder; ok=false once the byte cap is exceeded. */
function quirkyFilesMeasure(string $abs, int $capBytes): array
{
  if (is_file($abs)) {
    $s = (int) filesize($abs);
    return ['bytes' => $s, 'ok' => $s <= $capBytes];
  }
  $bytes = 0;
  $stack = [$abs];
  while (!empty($stack)) {
    $current = array_pop($stack);
    foreach ((@scandir($current) ?: []) as $entry) {
      if ($entry === '.' || $entry === '..') continue;
      $p = $current . '/' . $entry;
      if (@is_link($p)) continue;
      if (@is_dir($p)) {
        $stack[] = $p;
      } else {
        $bytes += (int) @filesize($p);
        if ($bytes > $capBytes) return ['bytes' => $bytes, 'ok' => false];
      }
    }
  }
  return ['bytes' => $bytes, 'ok' => true];
}

/** Import sources may only come from shared storage or our own sandbox. */
function quirkyFilesImportSourceAllowed(string $real): bool
{
  $roots = [
    '/storage/emulated/',
    '/storage/',
    '/data/data/com.quirky.ide/',
    '/data/user/0/com.quirky.ide/',
  ];
  // Derive the real app data dir from HOME (set to <dataDir>/cache/phptmp
  // by MainActivity) so work-profile installs also work.
  $home = (string) getenv('HOME');
  if ($home !== '') {
    $dataDir = dirname(dirname($home));
    if ($dataDir !== '' && $dataDir !== '/') {
      $roots[] = rtrim(str_replace('\\', '/', $dataDir), '/') . '/';
    }
  }
  foreach ($roots as $root) {
    if (strpos($real . '/', $root) === 0) return true;
  }
  return false;
}

/** Recursive copy used by imports and the workspace-switch "copy files". */
function quirkyFilesImportCopyDir(string $src, string $dst, array $blocked, array &$stats): void
{
  if (!is_dir($dst)) @mkdir($dst, 0777, true);
  $entries = @scandir($src);
  if ($entries === false) return;
  foreach ($entries as $entry) {
    if ($entry === '.' || $entry === '..') continue;
    if (in_array($entry, $blocked, true)) continue;
    $s = $src . '/' . $entry;
    $d = $dst . '/' . $entry;
    if (is_link($s)) continue;
    if (is_dir($s)) {
      $stats['folders']++;
      quirkyFilesImportCopyDir($s, $d, $blocked, $stats);
    } elseif (is_file($s)) {
      if (@copy($s, $d)) $stats['files']++;
    }
  }
}

/** Recursive delete used by the overwrite branch of imports. */
function quirkyFilesImportDelete(string $abs): void
{
  if (is_link($abs) || is_file($abs)) {
    @unlink($abs);
    return;
  }
  if (!is_dir($abs)) return;
  foreach ((@scandir($abs) ?: []) as $entry) {
    if ($entry === '.' || $entry === '..') continue;
    quirkyFilesImportDelete($abs . '/' . $entry);
  }
  @rmdir($abs);
}

function quirkyFilesLoadTokens(): array
{
  $file = IDE_APP . '/.export-tokens.json';
  if (!is_file($file)) return [];
  $data = json_decode((string) @file_get_contents($file), true);
  return is_array($data) ? $data : [];
}

function quirkyFilesSaveTokens(array $tokens): void
{
  @file_put_contents(
    IDE_APP . '/.export-tokens.json',
    json_encode($tokens, JSON_UNESCAPED_SLASHES),
    LOCK_EX
  );
}
