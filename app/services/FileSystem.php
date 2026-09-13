<?php

declare(strict_types=1);
/**
 * ═══════════════════════════════════════════════════════════════════════════
 *  QUIRKY IDE — FILE SYSTEM ENGINE  (v11 mutation + watch-cache hardening)
 * ═══════════════════════════════════════════════════════════════════════════
 *
 *  Drop-in replacement for FileSystem.php. The public interface and tree/read/
 *  write/delete/move/info/watch/copy/batch behavior remain compatible.
 *
 *  Relevant production fixes:
 *
 *  1. resolvePathSafe() was not safe: it trimmed a leading slash and directly
 *     concatenated workspaceRoot + input. It did not reject null bytes, '..',
 *     blocked segments, or a symlinked parent. v11 uses the same construction
 *     validator as save/create and rejects every symlink segment.
 *
 *  2. Mutations did not invalidate app/.watch-cache.json. The watch fast-path
 *     compares second-resolution directory mtimes; a create/delete/rename in
 *     the same second as the cache scan can therefore keep the old fingerprint
 *     for WATCH_CACHE_TTL (five minutes). The file exists on disk but does not
 *     appear in the explorer — exactly the supplied screenshot symptom.
 *     v11 invalidates after every successful save/delete/mkdir/rmdir/rename /
 *     copy, forcing the next poll to rebuild a real tree fingerprint.
 *
 *  3. saveFile() accepted any non-false file_put_contents result, reported the
 *     requested strlen(), and did not clear stat cache. v11 writes with
 *     LOCK_EX, requires the exact byte count, confirms the target is a regular
 *     file, reads the actual disk size, and returns success + verified fields
 *     additively (old consumers can ignore them).
 *
 *  SECURITY: every public path still runs through IdeSecurity. Construction
 *  paths additionally reject symlink segments so a link inside workspace/
 *  cannot redirect a newly-created file outside the jail.
 * ═══════════════════════════════════════════════════════════════════════════
 */
class IdeFileSystem implements IdeFileSystemInterface
{
  protected array $config;
  protected IdeSecurity $security;
  protected string $workspaceRoot;

  private const WATCH_CACHE_TTL = 300;

  public function __construct(array $config, IdeSecurity $security)
  {
    $this->config = $config;
    $this->security = $security;
    $realRoot = realpath(IDE_WORKSPACE);
    if ($realRoot === false) {
      if (!@mkdir(IDE_WORKSPACE, 0777, true) && !is_dir(IDE_WORKSPACE)) {
        $this->security->fail('Could not create workspace folder.');
      }
      $realRoot = realpath(IDE_WORKSPACE);
    }
    if ($realRoot === false || !is_dir($realRoot)) {
      $this->security->fail('Workspace folder is unavailable.');
    }
    $this->workspaceRoot = rtrim(str_replace('\\', '/', (string) $realRoot), '/');
  }

  /**
   * Resolve a relative path without requiring it to exist.
   * Contrary to the old implementation, this performs real validation.
   */
  public function resolvePathSafe(string $relativePath): string
  {
    $clean = $this->normalizeRelativePath($relativePath, true);
    if ($clean === '') return $this->workspaceRoot;
    $this->security->checkBlockedSegments($clean);
    $this->assertNoSymlinkSegments($clean);
    $absolute = $this->workspaceRoot . '/' . $clean;
    $this->assertContainedExistingPath($absolute);
    return $absolute;
  }

  /* ═══════════════════════════════════════════════════════════════
     DIRECTORY TREE
     ═══════════════════════════════════════════════════════════════ */
  public function getTree(
    int $maxDepth = 10,
    string $sortBy = 'name',
    bool $showHidden = false,
    string $sortDir = 'asc'
  ): array {
    return $this->scanDirectory(
      $this->workspaceRoot,
      '',
      0,
      max(1, $maxDepth),
      $sortBy,
      $showHidden,
      $sortDir
    );
  }

  public function getSubTree(
    string $relativePath,
    ?int $maxDepth = null,
    string $sortBy = 'name',
    bool $showHidden = false,
    string $sortDir = 'asc'
  ): array {
    $clean = $this->normalizeRelativePath($relativePath, true);
    if ($clean === '') {
      return $this->getTree($maxDepth ?? 10, $sortBy, $showHidden, $sortDir);
    }
    $this->security->checkBlockedSegments($clean);
    $absolute = $this->security->resolvePath($clean);
    if (!is_dir($absolute)) $this->security->fail('Folder not found: ' . $clean, 404);
    return $this->scanDirectory(
      $absolute,
      $clean,
      0,
      max(1, $maxDepth ?? 10),
      $sortBy,
      $showHidden,
      $sortDir
    );
  }

  protected function scanDirectory(
    string $absolutePath,
    string $relativePath,
    int $depth,
    int $maxDepth,
    string $sortBy = 'name',
    bool $showHidden = false,
    string $sortDir = 'asc'
  ): array {
    if ($depth >= $maxDepth) return [];
    $entries = @scandir($absolutePath);
    if ($entries === false) return [];
    $folders = [];
    $files = [];

    foreach ($entries as $entry) {
      if ($entry === '.' || $entry === '..') continue;
      $entryAbs = $absolutePath . '/' . $entry;
      $entryRel = $relativePath === '' ? $entry : $relativePath . '/' . $entry;
      if (is_link($entryAbs) || $this->isBlockedSegment($entry)) continue;
      if (!$showHidden && str_starts_with($entry, '.')) continue;

      if (is_dir($entryAbs)) {
        $setupFile = $entryAbs . '/quirky.setup';
        $isProject = is_file($setupFile);
        $projectName = '';
        if ($isProject) {
          $setup = @file_get_contents($setupFile, false, null, 0, 2048);
          if ($setup !== false && preg_match('/^name\s*=\s*(.+)$/mi', $setup, $m)) {
            $projectName = trim($m[1]);
          }
          if ($projectName === '') $projectName = $entry;
        }
        $truncated = ($depth + 1 >= $maxDepth);
        $children = $truncated ? [] : $this->scanDirectory(
          $entryAbs,
          $entryRel,
          $depth + 1,
          $maxDepth,
          $sortBy,
          $showHidden,
          $sortDir
        );
        $mtime = (int) (@filemtime($entryAbs) ?: 0);
        $node = [
          'name' => $entry,
          'path' => $entryRel,
          'type' => 'folder',
          'modified' => date('Y-m-d H:i:s', $mtime),
          'children' => $children,
          'childCount' => count($children),
        ];
        if ($truncated) $node['unloaded'] = true;
        if ($isProject) {
          $node['isProject'] = true;
          $node['projectName'] = $projectName;
        }
        $folders[] = $node;
      } elseif (is_file($entryAbs)) {
        $size = (int) (@filesize($entryAbs) ?: 0);
        $mtime = (int) (@filemtime($entryAbs) ?: 0);
        $ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
        $node = [
          'name' => $entry,
          'path' => $entryRel,
          'type' => 'file',
          'size' => $size,
          'sizeHuman' => $this->formatBytes($size),
          'modified' => date('Y-m-d H:i:s', $mtime),
          'extension' => $ext,
        ];
        if ($ext === '') $node['binary'] = $this->isBinaryFileQuick($entryAbs);
        $files[] = $node;
      }
    }

    usort($folders, fn($a, $b) => strcasecmp($a['name'], $b['name']));
    if ($sortDir === 'desc') $folders = array_reverse($folders);
    $direction = $sortDir === 'desc' ? -1 : 1;
    usort($files, function ($a, $b) use ($sortBy, $direction) {
      $cmp = match ($sortBy) {
        'size' => (($a['size'] ?? 0) <=> ($b['size'] ?? 0)),
        'mtime' => strcmp((string) ($a['modified'] ?? ''), (string) ($b['modified'] ?? '')),
        default => strcasecmp((string) $a['name'], (string) $b['name']),
      };
      if ($cmp === 0) $cmp = strcasecmp((string) $a['name'], (string) $b['name']);
      return $cmp * $direction;
    });
    return array_merge($folders, $files);
  }

  /* ═══════════════════════════════════════════════════════════════
     READ / WRITE
     ═══════════════════════════════════════════════════════════════ */
  public function readFile(string $relativePath): array
  {
    $clean = $this->normalizeRelativePath($relativePath);
    $this->security->checkBlockedSegments($clean);
    $this->security->validateFileExtension($clean);
    $absolute = $this->security->resolvePath($clean);
    if (!file_exists($absolute)) $this->security->fail('File not found: ' . $clean, 404);
    if (!is_file($absolute)) $this->security->fail('"' . $clean . '" is not a file. It might be a folder.');
    $size = (int) filesize($absolute);
    $max = (int) ($this->config['security']['max_file_size'] ?? 2 * 1024 * 1024);
    if ($size > $max) {
      $this->security->fail('File is too large to open (' . $this->formatBytes($size)
        . '). Maximum allowed is ' . $this->formatBytes($max) . '.');
    }
    $content = @file_get_contents($absolute);
    if ($content === false) $this->security->fail('Could not read file: ' . $clean);
    $binary = $this->isBinary($content);
    return [
      'success' => true,
      'path' => $clean,
      'name' => basename($clean),
      'content' => $binary ? null : $content,
      'binary' => $binary,
      'size' => $size,
      'sizeHuman' => $this->formatBytes($size),
      'modified' => date('Y-m-d H:i:s', (int) filemtime($absolute)),
      'extension' => strtolower(pathinfo($absolute, PATHINFO_EXTENSION)),
    ];
  }

  public function saveFile(string $relativePath, string $content): array
  {
    $clean = $this->normalizeRelativePath($relativePath);
    $this->security->checkBlockedSegments($clean);
    $this->security->validateFileExtension($clean);
    $bytesRequested = strlen($content);
    $max = (int) ($this->config['security']['max_file_size'] ?? 2 * 1024 * 1024);
    if ($bytesRequested > $max) {
      $this->security->fail('Content is too large to save (' . $this->formatBytes($bytesRequested)
        . '). Maximum allowed is ' . $this->formatBytes($max) . '.');
    }
    $absolute = $this->buildSafePath($clean);
    $parent = dirname($absolute);
    if (!is_dir($parent) && !@mkdir($parent, 0777, true) && !is_dir($parent)) {
      $this->security->fail('Could not create directory: ' . dirname($clean));
    }
    $isNew = !file_exists($absolute);
    $written = @file_put_contents($absolute, $content, LOCK_EX);
    if ($written === false) $this->security->fail('Could not write file: ' . $clean);
    if ($written !== $bytesRequested) {
      $this->security->fail('Incomplete write for ' . $clean . ': wrote '
        . (int) $written . ' of ' . $bytesRequested . ' bytes.');
    }
    clearstatcache(true, $absolute);
    if (!is_file($absolute)) $this->security->fail('Write was not confirmed on disk: ' . $clean);
    $actual = @filesize($absolute);
    if ($actual === false || (int) $actual !== $bytesRequested) {
      $this->security->fail('Saved file size could not be verified: ' . $clean);
    }
    $this->invalidateWatchCache();
    return [
      'success' => true,
      'verified' => true,
      'path' => $clean,
      'name' => basename($clean),
      'isNew' => $isNew,
      'size' => (int) $actual,
      'bytes' => (int) $actual,
      'sizeHuman' => $this->formatBytes((int) $actual),
      'modified' => date('Y-m-d H:i:s', (int) filemtime($absolute)),
    ];
  }

  /* ═══════════════════════════════════════════════════════════════
     MUTATIONS
     ═══════════════════════════════════════════════════════════════ */
  public function deleteFile(string $relativePath): array
  {
    $clean = $this->normalizeRelativePath($relativePath);
    $this->security->checkBlockedSegments($clean);
    $absolute = $this->security->resolvePath($clean);
    if (!file_exists($absolute)) $this->security->fail('File not found: ' . $clean, 404);
    if (!is_file($absolute) || is_link($absolute)) {
      $this->security->fail('"' . $clean . '" is not a deletable regular file.');
    }
    if (!@unlink($absolute)) $this->security->fail('Could not delete file: ' . $clean);
    clearstatcache(true, $absolute);
    if (file_exists($absolute)) $this->security->fail('Deletion was not confirmed: ' . $clean);
    $this->invalidateWatchCache();
    return ['success' => true, 'verified' => true, 'path' => $clean, 'deleted' => true];
  }

  public function createFolder(string $relativePath): array
  {
    $clean = $this->normalizeRelativePath($relativePath);
    $this->security->checkBlockedSegments($clean);
    $absolute = $this->buildSafePath($clean);
    if (file_exists($absolute)) $this->security->fail('Already exists: ' . $clean);
    if (!@mkdir($absolute, 0777, true) && !is_dir($absolute)) {
      $this->security->fail('Could not create folder: ' . $clean);
    }
    clearstatcache(true, $absolute);
    if (!is_dir($absolute)) $this->security->fail('Folder creation was not confirmed: ' . $clean);
    $this->invalidateWatchCache();
    return [
      'success' => true,
      'verified' => true,
      'path' => $clean,
      'name' => basename($clean),
      'created' => true,
    ];
  }

  public function deleteFolder(string $relativePath): array
  {
    $clean = $this->normalizeRelativePath($relativePath);
    $this->security->checkBlockedSegments($clean);
    $absolute = $this->security->resolvePath($clean);
    if (!file_exists($absolute)) $this->security->fail('Folder not found: ' . $clean, 404);
    if (!is_dir($absolute) || is_link($absolute)) $this->security->fail('"' . $clean . '" is not a folder.');
    if (rtrim(str_replace('\\', '/', $absolute), '/') === $this->workspaceRoot) {
      $this->security->fail('You cannot delete the workspace root folder.');
    }
    $this->deleteRecursive($absolute);
    clearstatcache(true, $absolute);
    if (file_exists($absolute)) $this->security->fail('Folder deletion was not confirmed: ' . $clean);
    $this->invalidateWatchCache();
    return ['success' => true, 'verified' => true, 'path' => $clean, 'deleted' => true];
  }

  private function deleteRecursive(string $path): void
  {
    if (is_link($path) || is_file($path)) {
      if (!@unlink($path)) $this->security->fail('Could not delete: ' . basename($path));
      return;
    }
    $entries = @scandir($path);
    if ($entries === false) $this->security->fail('Could not read folder during deletion.');
    foreach ($entries as $entry) {
      if ($entry === '.' || $entry === '..') continue;
      $this->deleteRecursive($path . '/' . $entry);
    }
    if (!@rmdir($path)) $this->security->fail('Could not delete folder: ' . basename($path));
  }

  public function rename(string $from, string $to): array
  {
    $fromClean = $this->normalizeRelativePath($from);
    $toClean = $this->normalizeRelativePath($to);
    $this->security->checkBlockedSegments($fromClean);
    $this->security->checkBlockedSegments($toClean);
    $fromAbs = $this->security->resolvePath($fromClean);
    if (!file_exists($fromAbs)) $this->security->fail('Source not found: ' . $fromClean, 404);
    if (is_file($fromAbs)) $this->security->validateFileExtension($toClean);
    $toAbs = $this->buildSafePath($toClean);
    if (file_exists($toAbs)) $this->security->fail('Destination already exists: ' . $toClean);
    $parent = dirname($toAbs);
    if (!is_dir($parent) && !@mkdir($parent, 0777, true) && !is_dir($parent)) {
      $this->security->fail('Could not create destination directory.');
    }
    if (!@rename($fromAbs, $toAbs)) {
      $this->security->fail('Could not rename "' . $fromClean . '" to "' . $toClean . '".');
    }
    clearstatcache(true, $fromAbs);
    clearstatcache(true, $toAbs);
    if (file_exists($fromAbs) || !file_exists($toAbs)) {
      $this->security->fail('Move was not confirmed: ' . $fromClean . ' → ' . $toClean);
    }
    $this->invalidateWatchCache();
    return [
      'success' => true,
      'verified' => true,
      'from' => $fromClean,
      'to' => $toClean,
      'renamed' => true,
    ];
  }

  /* ═══════════════════════════════════════════════════════════════
     FILE INFO
     ═══════════════════════════════════════════════════════════════ */
  public function getFileInfo(string $relativePath): array
  {
    $clean = $this->normalizeRelativePath($relativePath);
    $this->security->checkBlockedSegments($clean);
    $absolute = $this->security->tryResolvePath($clean);
    if ($absolute === null || !file_exists($absolute)) {
      return ['path' => $clean, 'name' => basename($clean), 'exists' => false, 'notFound' => true];
    }
    $isDir = is_dir($absolute);
    $parent = dirname($clean);
    $mtime = (int) (@filemtime($absolute) ?: 0);
    $ctime = (int) (@filectime($absolute) ?: 0);
    $atime = (int) (@fileatime($absolute) ?: 0);
    $perms = @fileperms($absolute);
    $info = [
      'path' => $clean,
      'name' => basename($clean),
      'exists' => true,
      'type' => $isDir ? 'folder' : 'file',
      'parent' => $parent === '.' ? '' : $parent,
      'depth' => substr_count($clean, '/'),
      'modified' => date('Y-m-d H:i:s', $mtime),
      'modifiedTs' => $mtime,
      'created' => date('Y-m-d H:i:s', $ctime),
      'createdTs' => $ctime,
      'accessed' => date('Y-m-d H:i:s', $atime),
      'accessedTs' => $atime,
      'permissions' => $perms !== false ? substr(sprintf('%o', $perms), -4) : null,
      'permissionsHuman' => $perms !== false ? $this->permissionString($perms) : null,
    ];
    if (!$isDir) {
      $size = (int) filesize($absolute);
      $info += [
        'size' => $size,
        'sizeHuman' => $this->formatBytes($size),
        'extension' => strtolower(pathinfo($absolute, PATHINFO_EXTENSION)),
        'readable' => is_readable($absolute),
        'writable' => is_writable($absolute),
      ];
      if ($size > 0 && $size <= 2 * 1024 * 1024) {
        $content = @file_get_contents($absolute);
        if ($content !== false && !$this->isBinary($content)) {
          $info['lines'] = substr_count($content, "\n") + 1;
          $info['words'] = count(preg_split('/\s+/', trim($content), -1, PREG_SPLIT_NO_EMPTY));
          $info['chars'] = function_exists('mb_strlen') ? mb_strlen($content) : strlen($content);
        }
      }
    } else {
      $entries = @scandir($absolute) ?: [];
      $fileCount = 0;
      $folderCount = 0;
      foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') continue;
        is_dir($absolute . '/' . $entry) ? $folderCount++ : $fileCount++;
      }
      $info['childCount'] = $fileCount + $folderCount;
      $info['fileCount'] = $fileCount;
      $info['folderCount'] = $folderCount;
    }
    return $info;
  }

  public function fileExists(string $relativePath): bool
  {
    try {
      $clean = $this->normalizeRelativePath($relativePath);
      $absolute = $this->security->tryResolvePath($clean);
      return $absolute !== null && file_exists($absolute);
    } catch (\Throwable $e) {
      return false;
    }
  }

  /* ═══════════════════════════════════════════════════════════════
     WATCH / SYNC
     ═══════════════════════════════════════════════════════════════ */
  public function getWatchData(): array
  {
    $cache = $this->loadWatchCache();
    if ($cache === null) return $this->fullWatchScan(true);

    $rootMtime = @filemtime($this->workspaceRoot);
    if ($rootMtime === false || $rootMtime !== (int) ($cache['rootMtime'] ?? -1)) {
      return $this->fullWatchScan(true);
    }
    foreach ($cache['dirs'] as $rel => $cachedMtime) {
      $abs = $this->watchAbs((string) $rel);
      $mtime = $abs === null ? false : @filemtime($abs);
      if ($mtime === false || $mtime !== (int) $cachedMtime) return $this->fullWatchScan(true);
    }

    $changed = false;
    $mtimes = [];
    $sizes = [];
    foreach ($cache['files'] as $rel => $cached) {
      $abs = $this->watchAbs((string) $rel);
      $mtime = $abs === null ? false : @filemtime($abs);
      if ($mtime === false) return $this->fullWatchScan(true);
      $mtimes[$rel] = (int) $mtime;
      if ($mtime !== (int) ($cached['m'] ?? -1)) {
        $changed = true;
        $sizes[$rel] = (int) @filesize($abs);
      } else {
        $sizes[$rel] = (int) ($cached['s'] ?? 0);
      }
    }
    if (!$changed) return ['fingerprint' => (string) $cache['fingerprint'], 'mtimes' => $mtimes];

    $parts = [];
    foreach ($cache['dirs'] as $rel => $mtime) $parts[] = $rel . '/:' . $mtime . ':0';
    foreach ($mtimes as $rel => $mtime) $parts[] = $rel . ':' . $mtime . ':' . $sizes[$rel];
    sort($parts);
    $fingerprint = md5(implode('|', $parts));
    $files = [];
    foreach ($mtimes as $rel => $mtime) $files[$rel] = ['m' => $mtime, 's' => $sizes[$rel]];
    $this->saveWatchCache([
      'v' => 1,
      'root' => $this->workspaceRoot,
      'time' => time(),
      'rootMtime' => (int) $rootMtime,
      'fingerprint' => $fingerprint,
      'dirs' => $cache['dirs'],
      'files' => $files,
    ]);
    return ['fingerprint' => $fingerprint, 'mtimes' => $mtimes];
  }

  private function scanForWatch(string $dir, string $rel, array &$files): void
  {
    $entries = @scandir($dir);
    if ($entries === false) return;
    foreach ($entries as $entry) {
      if ($entry === '.' || $entry === '..') continue;
      $abs = $dir . '/' . $entry;
      $path = $rel === '' ? $entry : $rel . '/' . $entry;
      if (is_link($abs) || $this->isBlockedSegment($entry)) continue;
      if (is_dir($abs)) {
        $files[$path . '/'] = ['mtime' => (int) filemtime($abs), 'size' => 0];
        $this->scanForWatch($abs, $path, $files);
      } elseif (is_file($abs)) {
        $files[$path] = ['mtime' => (int) filemtime($abs), 'size' => (int) filesize($abs)];
      }
    }
  }

  private function fullWatchScan(bool $updateCache = false): array
  {
    $files = [];
    $this->scanForWatch($this->workspaceRoot, '', $files);
    $parts = [];
    $mtimes = [];
    foreach ($files as $path => $info) {
      $parts[] = $path . ':' . $info['mtime'] . ':' . $info['size'];
      $mtimes[$path] = $info['mtime'];
    }
    sort($parts);
    $fingerprint = md5(implode('|', $parts));
    if ($updateCache) {
      $dirs = [];
      $onlyFiles = [];
      foreach ($files as $path => $info) {
        if (str_ends_with($path, '/')) $dirs[substr($path, 0, -1)] = $info['mtime'];
        else $onlyFiles[$path] = ['m' => $info['mtime'], 's' => $info['size']];
      }
      $this->saveWatchCache([
        'v' => 1,
        'root' => $this->workspaceRoot,
        'time' => time(),
        'rootMtime' => (int) @filemtime($this->workspaceRoot),
        'fingerprint' => $fingerprint,
        'dirs' => $dirs,
        'files' => $onlyFiles,
      ]);
    }
    return ['fingerprint' => $fingerprint, 'mtimes' => $mtimes];
  }

  private function watchCachePath(): string
  {
    return rtrim(str_replace('\\', '/', IDE_APP), '/') . '/.watch-cache.json';
  }

  private function invalidateWatchCache(): void
  {
    $path = $this->watchCachePath();
    if (is_file($path)) @unlink($path);
    clearstatcache(true, $path);
  }

  private function loadWatchCache(): ?array
  {
    $raw = @file_get_contents($this->watchCachePath());
    if ($raw === false || $raw === '') return null;
    $data = json_decode($raw, true);
    if (!is_array($data)
      || ($data['v'] ?? 0) !== 1
      || ($data['root'] ?? '') !== $this->workspaceRoot
      || !isset($data['dirs'], $data['files'], $data['fingerprint'])
      || !is_array($data['dirs'])
      || !is_array($data['files'])
      || !is_string($data['fingerprint'])
      || time() - (int) ($data['time'] ?? 0) > self::WATCH_CACHE_TTL
    ) return null;
    return $data;
  }

  private function saveWatchCache(array $data): void
  {
    $json = json_encode($data, JSON_UNESCAPED_SLASHES);
    if ($json !== false) @file_put_contents($this->watchCachePath(), $json, LOCK_EX);
  }

  private function watchAbs(string $rel): ?string
  {
    try {
      return $rel === '' ? $this->workspaceRoot : $this->resolvePathSafe($rel);
    } catch (\Throwable $e) {
      return null;
    }
  }

  /* ═══════════════════════════════════════════════════════════════
     COPY / BATCH READ
     ═══════════════════════════════════════════════════════════════ */
  public function copyRecursive(string $source, string $dest): array
  {
    $source = $this->normalizeRelativePath($source);
    $dest = $this->normalizeRelativePath($dest);
    $this->security->checkBlockedSegments($source);
    $this->security->checkBlockedSegments($dest);
    $sourceAbs = $this->security->resolvePath($source);
    if (!file_exists($sourceAbs)) $this->security->fail('Source not found: ' . $source, 404);
    $destAbs = $this->buildSafePath($dest);
    if (file_exists($destAbs)) $this->security->fail('Destination already exists: ' . $dest);
    $stats = ['files' => 0, 'folders' => 0];
    if (is_file($sourceAbs)) {
      $this->security->validateFileExtension($dest);
      $parent = dirname($destAbs);
      if (!is_dir($parent) && !@mkdir($parent, 0777, true) && !is_dir($parent)) {
        $this->security->fail('Could not create destination directory.');
      }
      if (!@copy($sourceAbs, $destAbs)) $this->security->fail('Could not copy file: ' . $source);
      $stats['files'] = 1;
    } elseif (is_dir($sourceAbs)) {
      $this->copyDirRecursive($sourceAbs, $destAbs, $destAbs, $stats);
    } else {
      $this->security->fail('Source is neither a file nor a folder: ' . $source);
    }
    $this->invalidateWatchCache();
    return ['source' => $source, 'dest' => $dest, 'copied' => true] + $stats;
  }

  private function copyDirRecursive(string $source, string $dest, string $destRoot, array &$stats): void
  {
    if (!is_dir($dest) && !@mkdir($dest, 0777, true) && !is_dir($dest)) {
      $this->security->fail('Could not create directory during copy.');
    }
    $stats['folders']++;
    $entries = @scandir($source);
    if ($entries === false) $this->security->fail('Could not read source during copy.');
    $destRoot = rtrim(str_replace('\\', '/', $destRoot), '/');
    foreach ($entries as $entry) {
      if ($entry === '.' || $entry === '..') continue;
      $src = $source . '/' . $entry;
      $dst = $dest . '/' . $entry;
      if (is_link($src) || $this->isBlockedSegment($entry)) continue;
      if (rtrim(str_replace('\\', '/', $src), '/') === $destRoot) continue;
      if (is_dir($src)) {
        $this->copyDirRecursive($src, $dst, $destRoot, $stats);
      } elseif (is_file($src)) {
        $size = (int) filesize($src);
        $max = (int) ($this->config['security']['max_file_size'] ?? 2 * 1024 * 1024);
        if ($size <= $max && @copy($src, $dst)) $stats['files']++;
      }
    }
  }

  public function readFilesBatch(array $paths, int $maxFiles = 30): array
  {
    $results = [];
    foreach ($paths as $path) {
      if (count($results) >= max(1, $maxFiles)) break;
      $path = trim((string) $path);
      if ($path === '') continue;
      $results[] = $this->readFileSafe($path);
    }
    return ['files' => $results, 'count' => count($results)];
  }

  private function readFileSafe(string $relativePath): array
  {
    // Do not call security->fail() here: the production security layer may
    // emit an HTTP error and exit rather than throw, which would abort the
    // entire batch. Use its non-fatal predicates exactly as the original did.
    if ($this->security->hasBlockedSegment($relativePath)) {
      return ['path' => $relativePath, 'ok' => false, 'error' => 'Blocked path segment.'];
    }
    if (!$this->security->isExtensionAllowed($relativePath)) {
      return ['path' => $relativePath, 'ok' => false, 'error' => 'File type not allowed.'];
    }
    $absolute = $this->security->tryResolvePath($relativePath);
    if ($absolute === null) {
      return ['path' => $relativePath, 'ok' => false, 'error' => 'Invalid path (outside the workspace?).'];
    }
    if (!file_exists($absolute) || !is_file($absolute)) {
      return ['path' => $relativePath, 'ok' => false, 'error' => 'File not found.'];
    }
    $size = (int) filesize($absolute);
    $max = (int) ($this->config['security']['max_file_size'] ?? 2 * 1024 * 1024);
    if ($size > $max) {
      return ['path' => $relativePath, 'ok' => false, 'error' => 'File too large (' . $this->formatBytes($size) . ').'];
    }
    $content = @file_get_contents($absolute);
    if ($content === false) {
      return ['path' => $relativePath, 'ok' => false, 'error' => 'Could not read file.'];
    }
    $binary = $this->isBinary($content);
    return [
      'ok' => true,
      'path' => $relativePath,
      'name' => basename($relativePath),
      'content' => $binary ? null : $content,
      'binary' => $binary,
      'size' => $size,
      'sizeHuman' => $this->formatBytes($size),
      'modified' => date('Y-m-d H:i:s', (int) filemtime($absolute)),
      'extension' => strtolower(pathinfo($absolute, PATHINFO_EXTENSION)),
    ];
  }

  /* ═══════════════════════════════════════════════════════════════
     PRIVATE SECURITY / METADATA HELPERS
     ═══════════════════════════════════════════════════════════════ */
  private function normalizeRelativePath(string $relativePath, bool $allowEmpty = false): string
  {
    if (strpos($relativePath, "\0") !== false) $this->security->fail('Invalid path characters.');
    $clean = trim(str_replace('\\', '/', $relativePath));
    if (preg_match('/^[A-Za-z]:\//', $clean) || str_starts_with($clean, '/')) {
      $this->security->fail('Access denied: use a workspace-relative path.');
    }
    $clean = (string) preg_replace('#^(\./)+#', '', $clean);
    $clean = (string) preg_replace('#/+#', '/', $clean);
    $clean = trim($clean, '/');
    if ($clean === '') {
      if ($allowEmpty) return '';
      $this->security->fail('A non-empty workspace-relative path is required.');
    }
    foreach (explode('/', $clean) as $segment) {
      if ($segment === '..') $this->security->fail('Access denied: You cannot navigate outside the workspace.');
    }
    return $clean;
  }

  private function buildSafePath(string $relativePath): string
  {
    $clean = $this->normalizeRelativePath($relativePath);
    $this->assertNoSymlinkSegments($clean);
    $absolute = $this->workspaceRoot . '/' . $clean;
    $this->assertContainedExistingPath($absolute);
    return $absolute;
  }

  /** Reject a link anywhere in the path, including a non-leaf parent. */
  private function assertNoSymlinkSegments(string $clean): void
  {
    $cursor = $this->workspaceRoot;
    foreach (explode('/', $clean) as $segment) {
      $cursor .= '/' . $segment;
      if (is_link($cursor)) {
        $this->security->fail('Access denied: symbolic links are not allowed in workspace paths.');
      }
    }
  }

  /** If the path exists, its real target must remain under workspaceRoot. */
  private function assertContainedExistingPath(string $absolute): void
  {
    if (!file_exists($absolute)) return;
    $real = realpath($absolute);
    if ($real === false) $this->security->fail('Could not resolve workspace path.');
    $real = str_replace('\\', '/', $real);
    if ($real !== $this->workspaceRoot && !str_starts_with($real, $this->workspaceRoot . '/')) {
      $this->security->fail('Access denied: Path escapes the workspace.');
    }
  }

  private function isBlockedSegment(string $name): bool
  {
    return in_array($name, (array) ($this->config['security']['blocked_segments'] ?? []), true);
  }

  private function isBinary(string $content): bool
  {
    return strpos(substr($content, 0, 8192), "\0") !== false;
  }

  private function isBinaryFileQuick(string $absolute): bool
  {
    $h = @fopen($absolute, 'rb');
    if ($h === false) return false;
    $sample = fread($h, 8192);
    fclose($h);
    return $sample !== false && strpos($sample, "\0") !== false;
  }

  private function permissionString(int $perms): string
  {
    $type = match ($perms & 0xF000) {
      0xC000 => 's', 0xA000 => 'l', 0x8000 => '-', 0x6000 => 'b',
      0x4000 => 'd', 0x2000 => 'c', 0x1000 => 'p', default => '-',
    };
    $str = $type;
    foreach ([6, 3, 0] as $shift) {
      $bits = ($perms >> $shift) & 7;
      $str .= ($bits & 4) ? 'r' : '-';
      $str .= ($bits & 2) ? 'w' : '-';
      $str .= ($bits & 1) ? 'x' : '-';
    }
    return $str;
  }

  private function formatBytes(int $bytes): string
  {
    if ($bytes <= 0) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = min((int) floor(log($bytes, 1024)), count($units) - 1);
    return round($bytes / pow(1024, $i), 1) . ' ' . $units[$i];
  }
}
