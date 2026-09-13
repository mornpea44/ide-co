<?php

declare(strict_types=1);
/**
 * QUIRKY IDE — MOBILE WORKSPACE CONSTRAINTS (v11-compatible)
 *
 * Retains the existing mobile depth/memory policy. All disk verification,
 * workspace-jail enforcement and watch-cache invalidation remain in the parent
 * IdeFileSystem implementation, so mobile and desktop AI writes have identical
 * correctness guarantees.
 */
require_once __DIR__ . '/FileSystem.php';

final class IdeMobileWorkspace extends IdeFileSystem
{
  private const MAX_DEPTH = 6;
  private const MIN_BYTES = 524288;
  private const CAP_BYTES = 264 * 1024 * 1024;

  public function getTree(
    int $maxDepth = 10,
    string $sortBy = 'name',
    bool $showHidden = false,
    string $sortDir = 'asc'
  ): array {
    return parent::getTree(min(max(1, $maxDepth), self::MAX_DEPTH), $sortBy, $showHidden, $sortDir);
  }

  public function getSubTree(
    string $relativePath,
    ?int $maxDepth = null,
    string $sortBy = 'name',
    bool $showHidden = false,
    string $sortDir = 'asc'
  ): array {
    $depth = min($maxDepth ?? self::MAX_DEPTH, self::MAX_DEPTH);
    return parent::getSubTree($relativePath, max(1, $depth), $sortBy, $showHidden, $sortDir);
  }

  public function saveFile(string $relativePath, string $content): array
  {
    $limit = (int) ($this->config['security']['max_file_size'] ?? self::MIN_BYTES);
    $limit = max(self::MIN_BYTES, min($limit, self::CAP_BYTES));
    if (strlen($content) > $limit) {
      throw new RuntimeException('Mobile: file exceeds ' . round($limit / 1048576, 1) . ' MB limit');
    }
    $normalized = str_replace('\\', '/', $relativePath);
    if (preg_match('#(^|/)\.git(/|$)#', $normalized)) {
      throw new RuntimeException('Mobile: .git is read-only on mobile');
    }
    return parent::saveFile($relativePath, $content);
  }
}
