<?php

declare(strict_types=1);
/**
 * ═══════════════════════════════════════════════════════════════════════════
 *  QUIRKY IDE — SERVICE INTERFACES (Phase 3 · Task 3.6)
 * ═══════════════════════════════════════════════════════════════════════════
 *
 *  Interfaces define the "contract" each service must fulfil.
 *  If a class says "implements IdeFileSystemInterface", PHP guarantees
 *  it has every method listed here with the correct signature.
 *
 *  Why this matters:
 *    - Makes the code self-documenting (you can read the interface to
 *      know exactly what a service can do)
 *    - Enables future testability (swap in a mock implementation)
 *    - Catches missing methods at load time instead of runtime
 *
 * ═══════════════════════════════════════════════════════════════════════════
 */
/**
 * Contract for the sandboxed file system service.
 */
interface IdeFileSystemInterface
{
  /** Get the full directory tree of the workspace. */
  public function getTree(int $maxDepth = 10): array;
  /** Read a file's content and metadata. */
  public function readFile(string $relativePath): array;
  /** Save (create or overwrite) a file. */
  public function saveFile(string $relativePath, string $content): array;
  /** Delete a single file. */
  public function deleteFile(string $relativePath): array;
  /** Create a new folder (including missing parents). */
  public function createFolder(string $relativePath): array;
  /** Delete a folder and everything inside it. */
  public function deleteFolder(string $relativePath): array;
  /** Rename or move a file/folder. */
  public function rename(string $from, string $to): array;
  /** Get metadata about a file or folder. */
  public function getFileInfo(string $relativePath): array;
  /** Check if a file or folder exists. */
  public function fileExists(string $relativePath): bool;
  /** Copy a file or folder recursively. */
  public function copyRecursive(string $source, string $dest): array;
  /** Get a lightweight fingerprint + mtime map for polling. */
  public function getWatchData(): array;

  /** Read multiple files in one request (batch session restore). */
  public function readFilesBatch(array $paths, int $maxFiles = 30): array;
}
/**
 * Contract for the workspace search service.
 */
interface IdeSearchInterface
{
  /** Search all files in the workspace. */
  public function search(string $query, array $options = []): array;
  /** Search a single file for matches. */
  public function searchFile(
    string $absolutePath,
    string $relativePath,
    string $query,
    bool $caseSensitive = false,
    bool $useRegex = false,
    int $budget = 50
  ): array;
  /** Replace across files. */
  public function replace(string $query, string $replacement, array $options, IdeFileSystem $fs): array;
  /** Get all searchable files in the workspace. */
  public function getSearchableFiles(string $fileFilter = ''): array;
  /** Get quick stats about searchable files. */
  public function getSearchStats(): array;
}
/**
 * Contract for the terminal / command runner service.
 */
interface IdeTerminalInterface
{
  /** Check whether the terminal feature is enabled. */
  public function isEnabled(): bool;
  /** Get information about the terminal environment. */
  public function getInfo(): array;
  /** Validate and execute a shell command. */
  public function run(string $command, string $stdin = ''): array;
  /** Stream a command's output via SSE. */
  public function runStream(string $command, string $stdin = ''): void;
  /** Cancel a running command. */
  public function cancel(): array;
}
/**
 * Contract for PHP code tools (format, minify, lint).
 */
interface IdePhpToolsInterface
{
  /** Format PHP code (re-indent). */
  public function format(string $code): string;
  /** Format PHP using PHP CS Fixer. */
  public function formatWithCsFixer(string $code): array;
  /** Safe PHP minifier. */
  public function minify(string $code): string;
  /** Server-side syntax lint. */
  public function lint(string $content, string $language): array;
  /** Guard top-level function definitions against redeclaration. */
  public function guardTopLevelFunctions(string $code): string;
  /** Resolve a relative URL against a base directory. */
  public function resolveRel(string $base, string $rel): string;
}
