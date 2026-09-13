<?php

declare(strict_types=1);
/**
 * ═══════════════════════════════════════════════════════════════════════════
 *  QUIRKY IDE — AI TOOL EXECUTOR  (v11 companion hardening)
 * ═══════════════════════════════════════════════════════════════════════════
 *
 *  DROP-IN REPLACEMENT for the existing AiToolExecutor.php (the supplied
 *  filename in the prompt says AiToolExecute.php; AI.php requires the class
 *  companion as AiToolExecutor.php).
 *
 *  IMPORTANT DIAGNOSIS
 *  ───────────────────
 *  The production write bug is here, not just in AI.php:
 *
 *      $r = $this->fs->saveFile($p, $c);
 *      return ['success' => true, 'path' => $p, ...];
 *
 *  The old tWrite() discarded $r and claimed success regardless of whether
 *  saveFile() returned success:false / error / an invalid byte count. It then
 *  reported strlen($c), i.e. the REQUESTED size rather than the actual file
 *  size. That is precisely how the UI could show an approved “Created” card
 *  while no file existed in workspace/.
 *
 *  v11 contracts
 *  ──────────────
 *  • The filesystem result is the source of truth. No handler returns
 *    success:true unless the lower layer positively confirmed it.
 *  • write_file returns actual path + actual on-disk size/bytes, never the
 *    requested strlen() alone. The target is resolved inside the existing
 *    IdeFileSystem path jail before write; parent creation remains delegated
 *    to saveFile(), which already owns that policy.
 *  • Every handler normalizes legacy filesystem result shapes (success / ok /
 *    error) without weakening errors. Exceptions become safe error results.
 *  • Destructive operations validate their post-condition using filesystem
 *    metadata where the existing IdeFileSystem exposes it, but do not bypass
 *    or duplicate the filesystem jail.
 *  • Public class / constructor / execute() contract and tool names are
 *    unchanged. Core IdeAI remains provider-neutral.
 * ═══════════════════════════════════════════════════════════════════════════
 */
class AiToolExecutor
{
  private IdeFileSystem $fs;
  private IdeSearch $search;
  private ?IdeTerminal $terminal;
  private int $maxFileChars;

  public function __construct(
    IdeFileSystem $fs,
    IdeSearch $search,
    ?IdeTerminal $terminal,
    int $maxFileChars = 4000
  ) {
    $this->fs           = $fs;
    $this->search       = $search;
    $this->terminal     = $terminal;
    $this->maxFileChars = max(200, $maxFileChars);
  }

  /**
   * Dispatch a canonical tool call. Returns an array on every path; model
   * input and filesystem exceptions never escape into the agent loop.
   *
   * @param string $name Tool name (e.g. 'read_file')
   * @param array  $args Normalized tool arguments from IdeAI
   */
  public function execute(string $name, array $args): array
  {
    try {
      return match (strtolower(trim($name))) {
        'list_dir'         => $this->tList($args),
        'read_file'        => $this->tRead($args),
        'write_file'       => $this->tWrite($args),
        'delete_file'      => $this->tDel($args),
        'move_file'        => $this->tMove($args),
        'create_folder'    => $this->tMkdir($args),
        'delete_folder'    => $this->tRmdir($args),
        'search_workspace' => $this->tSearch($args),
        'run_command'      => $this->tRun($args),
        default            => ['success' => false, 'error' => 'Unknown tool: ' . $name],
      };
    } catch (\Throwable $e) {
      return ['success' => false, 'error' => $this->safeMessage($e)];
    }
  }

  /* ═══════════════════════════════════════════════════════════════
     INDIVIDUAL TOOL HANDLERS
     ═══════════════════════════════════════════════════════════════ */

  private function tList(array $a): array
  {
    $p = trim((string) ($a['path'] ?? ''), '/');
    $abs = $p === ''
      ? str_replace('\\', '/', (string) realpath(IDE_WORKSPACE))
      : $this->fs->resolvePathSafe($p);
    if ($abs === '' || !is_dir($abs)) return $this->failure('Not a directory: ' . ($p ?: '(root)'));
    $e = @scandir($abs);
    if ($e === false) return $this->failure('Cannot read directory: ' . ($p ?: '(root)'));
    $items = [];
    foreach ($e as $x) {
      if ($x === '.' || $x === '..') continue;
      $full = $abs . '/' . $x;
      $rel  = $p === '' ? $x : $p . '/' . $x;
      $size = is_file($full) ? @filesize($full) : null;
      $items[] = [
        'name' => $x,
        'path' => $rel,
        'type' => is_dir($full) ? 'folder' : 'file',
        'size' => $size === false ? null : $size,
      ];
    }
    usort(
      $items,
      fn($x, $y) => ($x['type'] === $y['type'])
        ? strcasecmp((string) $x['name'], (string) $y['name'])
        : ($x['type'] === 'folder' ? -1 : 1)
    );
    return [
      'success' => true,
      'path'    => $p ?: '(root)',
      'entries' => $items,
      'count'   => count($items),
    ];
  }

  private function tRead(array $a): array
  {
    $p = (string) ($a['path'] ?? '');
    if ($p === '') return $this->failure('No path');
    $d = $this->asResult($this->fs->readFile($p));
    if (!empty($d['error']) || (array_key_exists('success', $d) && empty($d['success']))) {
      return $this->failure((string) ($d['error'] ?? 'Cannot read file: ' . $p));
    }
    // Binary reads intentionally return content:null. Never turn that into an
    // apparently successful empty text file for the model.
    if (!empty($d['binary']) || !array_key_exists('content', $d) || $d['content'] === null) {
      return $this->failure(!empty($d['binary'])
        ? 'Binary file cannot be read as text: ' . $p
        : 'Filesystem did not return content for: ' . $p);
    }
    $content = (string) $d['content'];
    $truncated = false;
    if ($this->length($content) > $this->maxFileChars) {
      $content = $this->cut($content, 0, $this->maxFileChars) . "\n…[truncated]";
      $truncated = true;
    }
    return [
      'success'   => true,
      'path'      => (string) ($d['path'] ?? $p),
      'content'   => $content,
      'size'      => isset($d['size']) && is_numeric($d['size']) ? (int) $d['size'] : $this->length((string) $d['content']),
      'truncated' => $truncated,
    ];
  }

  /**
   * The critical production fix.
   *
   * The old version unconditionally returned success:true after saveFile().
   * This version carries filesystem failure through verbatim, then confirms
   * the target is an on-disk file and reports filesystem bytes — never just
   * strlen($content). Thus a "Created" result means a file really exists.
   */
  private function tWrite(array $a): array
  {
    $p = (string) ($a['path'] ?? '');
    $c = (string) ($a['content'] ?? '');
    if ($p === '') return $this->failure('No path');

    // Validate the model path independently; the supplied resolvePathSafe()
    // only concatenates strings and is NOT a jail. saveFile() still performs
    // the authoritative IdeSecurity + buildSafePath checks.
    $pathError = $this->validateRelativePath($p);
    if ($pathError !== null) return $this->failure($pathError);

    $wasFile = $this->fs->fileExists($p);
    $r = $this->asResult($this->fs->saveFile($p, $c));
    if (!empty($r['error']) || (array_key_exists('success', $r) && empty($r['success']))) {
      return $this->failure((string) ($r['error'] ?? 'Write failed: ' . $p));
    }

    // Verify through the filesystem engine's public jailed APIs rather than
    // trusting the requested path or its non-validating resolvePathSafe().
    if (!$this->fs->fileExists($p)) {
      return $this->failure('Write was not confirmed in workspace: ' . $p);
    }
    $info = $this->asResult($this->fs->getFileInfo($p));
    if (!empty($info['notFound']) || ($info['type'] ?? '') !== 'file') {
      return $this->failure('Write target is not a file in workspace: ' . $p);
    }
    $size = isset($info['size']) && is_numeric($info['size'])
      ? (int) $info['size']
      : (isset($r['size']) && is_numeric($r['size']) ? (int) $r['size'] : -1);
    if ($size < 0) return $this->failure('Write completed but file size could not be verified: ' . $p);
    if ($c !== '' && $size === 0) {
      return $this->failure('Write produced 0 bytes for non-empty content: ' . $p);
    }
    if ($size !== strlen($c)) {
      return $this->failure('Write size mismatch for ' . $p . ': expected '
        . strlen($c) . ' bytes, found ' . $size . '.');
    }

    return [
      'success'  => true,
      'verified' => true,
      'path'     => (string) ($info['path'] ?? $r['path'] ?? $p),
      'isNew'    => array_key_exists('isNew', $r) ? (bool) $r['isNew'] : !$wasFile,
      'size'     => $size,
      'bytes'    => $size,
    ];
  }

  private function tDel(array $a): array
  {
    $p = (string) ($a['path'] ?? '');
    if ($p === '') return $this->failure('No path');
    $pathError = $this->validateRelativePath($p);
    if ($pathError !== null) return $this->failure($pathError);
    $before = $this->asResult($this->fs->getFileInfo($p));
    if (!empty($before['notFound']) || ($before['type'] ?? '') !== 'file') {
      return $this->failure('File not found: ' . $p);
    }
    $r = $this->asResult($this->fs->deleteFile($p));
    if (!empty($r['error']) || (array_key_exists('success', $r) && empty($r['success']))) {
      return $this->failure((string) ($r['error'] ?? 'Delete failed: ' . $p));
    }
    if ($this->fs->fileExists($p)) return $this->failure('Delete was not confirmed in workspace: ' . $p);
    return ['success' => true, 'verified' => true, 'deleted' => $p, 'path' => $p];
  }

  private function tMove(array $a): array
  {
    $f = (string) ($a['from'] ?? '');
    $t = (string) ($a['to'] ?? '');
    if ($f === '' || $t === '') return $this->failure('from & to required');
    $fromError = $this->validateRelativePath($f);
    $toError = $this->validateRelativePath($t);
    if ($fromError !== null) return $this->failure($fromError);
    if ($toError !== null) return $this->failure($toError);
    if (!$this->fs->fileExists($f)) return $this->failure('Source not found: ' . $f);
    $r = $this->asResult($this->fs->rename($f, $t));
    if (!empty($r['error']) || (array_key_exists('success', $r) && empty($r['success']))) {
      return $this->failure((string) ($r['error'] ?? 'Move failed: ' . $f . ' → ' . $t));
    }
    if ($this->fs->fileExists($f) || !$this->fs->fileExists($t)) {
      return $this->failure('Move was not confirmed in workspace: ' . $f . ' → ' . $t);
    }
    return ['success' => true, 'verified' => true, 'from' => $f, 'to' => $t, 'path' => $t];
  }

  private function tMkdir(array $a): array
  {
    $p = (string) ($a['path'] ?? '');
    if ($p === '') return $this->failure('No path');
    $pathError = $this->validateRelativePath($p);
    if ($pathError !== null) return $this->failure($pathError);
    $r = $this->asResult($this->fs->createFolder($p));
    if (!empty($r['error']) || (array_key_exists('success', $r) && empty($r['success']))) {
      return $this->failure((string) ($r['error'] ?? 'Could not create folder: ' . $p));
    }
    $info = $this->asResult($this->fs->getFileInfo($p));
    if (!empty($info['notFound']) || ($info['type'] ?? '') !== 'folder') {
      return $this->failure('Folder creation was not confirmed in workspace: ' . $p);
    }
    return ['success' => true, 'verified' => true, 'created' => $p, 'path' => $p];
  }

  private function tRmdir(array $a): array
  {
    $p = (string) ($a['path'] ?? '');
    if ($p === '') return $this->failure('No path');
    $pathError = $this->validateRelativePath($p);
    if ($pathError !== null) return $this->failure($pathError);
    $before = $this->asResult($this->fs->getFileInfo($p));
    if (!empty($before['notFound']) || ($before['type'] ?? '') !== 'folder') {
      return $this->failure('Folder not found: ' . $p);
    }
    $r = $this->asResult($this->fs->deleteFolder($p));
    if (!empty($r['error']) || (array_key_exists('success', $r) && empty($r['success']))) {
      return $this->failure((string) ($r['error'] ?? 'Could not delete folder: ' . $p));
    }
    if ($this->fs->fileExists($p)) return $this->failure('Folder deletion was not confirmed in workspace: ' . $p);
    return ['success' => true, 'verified' => true, 'deleted' => $p, 'path' => $p];
  }

  private function tSearch(array $a): array
  {
    $q = (string) ($a['query'] ?? '');
    if ($q === '') return $this->failure('No query');
    $r = $this->asResult($this->search->search($q, ['maxResults' => 20]));
    if (!empty($r['error']) || (array_key_exists('success', $r) && empty($r['success']))) {
      return $this->failure((string) ($r['error'] ?? 'Search failed'));
    }
    $compact = [];
    foreach (($r['results'] ?? []) as $f) {
      if (!is_array($f)) continue;
      $m = [];
      foreach (array_slice((array) ($f['matches'] ?? []), 0, 5) as $x) {
        if (!is_array($x)) continue;
        $m[] = 'L' . (int) ($x['line'] ?? 0) . ': ' . trim((string) ($x['content'] ?? $x['text'] ?? ''));
      }
      $compact[] = ['file' => (string) ($f['file'] ?? '?'), 'matches' => $m];
    }
    return [
      'success'      => true,
      'query'        => $q,
      'totalFiles'   => (int) ($r['totalFiles'] ?? count($compact)),
      'totalMatches' => (int) ($r['totalMatches'] ?? 0),
      'results'      => $compact,
    ];
  }

  private function tRun(array $a): array
  {
    if (!$this->terminal || !$this->terminal->isEnabled()) {
      return $this->failure('Terminal disabled in config.php');
    }
    $c = (string) ($a['command'] ?? '');
    if ($c === '') return $this->failure('No command');
    $r = $this->asResult($this->terminal->run($c));
    if (!empty($r['error'])) return $this->failure((string) $r['error']);
    return $r + ['success' => !isset($r['exitCode']) || (int) $r['exitCode'] === 0];
  }

  /* ═══════════════════════════════════════════════════════════════
     NORMALISATION + SAFETY HELPERS
     ═══════════════════════════════════════════════════════════════ */

  /** Ensure collaborator output can safely be handled as a result object. */
  private function asResult($result): array
  {
    return is_array($result)
      ? $result
      : ['success' => false, 'error' => 'Filesystem returned an unusable result.'];
  }

  private function failure(string $message): array
  {
    return ['success' => false, 'error' => $message];
  }

  /**
   * Validate a model path before it reaches FileSystem.php. This deliberately
   * does not call resolvePathSafe(): the supplied implementation merely
   * concatenates workspaceRoot + input and performs no security validation.
   * The filesystem's own IdeSecurity checks remain authoritative.
   */
  private function validateRelativePath(string $path): ?string
  {
    $clean = trim(str_replace('\\', '/', $path));
    if ($clean === '' || str_starts_with($clean, '/') || preg_match('/^[A-Za-z]:\//', $clean)) {
      return 'A non-empty workspace-relative path is required.';
    }
    if (strpos($clean, "\0") !== false) return 'Invalid path characters.';
    foreach (explode('/', $clean) as $segment) {
      if ($segment === '..') return 'Access denied: path escapes the workspace.';
    }
    return null;
  }

  private function safeMessage(\Throwable $e): string
  {
    $msg = trim((string) preg_replace('/\s+/', ' ', $e->getMessage()));
    return $msg !== '' ? $msg : 'Tool execution failed unexpectedly.';
  }

  private function length(string $s): int
  {
    return function_exists('mb_strlen') ? mb_strlen($s, 'UTF-8') : strlen($s);
  }

  private function cut(string $s, int $start, int $length): string
  {
    return function_exists('mb_substr')
      ? mb_substr($s, $start, $length, 'UTF-8')
      : substr($s, $start, $length);
  }
}
  