<?php

declare(strict_types=1);

/**
 * ═══════════════════════════════════════════════════════════════════════════
 *  QUIRKY IDE — SEARCH ENGINE (File 5 of 20)
 * ═══════════════════════════════════════════════════════════════════════════
 *
 *  This is the search engine. It scans every file in the workspace and
 *  finds matching lines for a text query or regex pattern.
 *
 *  It powers two features in the IDE:
 *    1. "Search across files" (Ctrl+Shift+F) — searches the whole workspace
 *    2. "Find in file" (Ctrl+F) — searches a single open file
 *
 *  SECURITY: Only searches inside the workspace. Respects the allowed
 *  extensions and blocked segments from config.php. Skips binary files
 *  and files larger than the configured limit.
 *
 *  This class does NOT handle HTTP requests or JSON responses directly.
 *  It returns arrays. The API layer (File 7) calls these methods and
 *  sends the results to the browser.
 *
 * ═══════════════════════════════════════════════════════════════════════════
 */

class IdeSearch implements IdeSearchInterface
{
    /** @var array The full config from config.php */
    private array $config;

    /** @var IdeSecurity The security guard (path jail + validation) */
    private IdeSecurity $security;

    /** @var string Absolute, real path to the workspace folder */
    private string $workspaceRoot;

    /**
     * Maximum number of total matches to return across all files.
     * Prevents memory issues when searching for a very common string.
     */
    private const MAX_TOTAL_MATCHES = 500;

    /**
     * Maximum number of matches to return per file.
     * Prevents one huge file from consuming the entire result budget.
     */
    private const MAX_MATCHES_PER_FILE = 50;

    /**
     * Maximum file size (in bytes) to search.
     * Files larger than this are skipped to keep search fast.
     */
    private const MAX_SEARCH_FILE_SIZE = 1048576; // 1 MB

    /**
     * Maximum number of files to scan.
     * Safety valve for very large workspaces.
     */
    private const MAX_FILES_TO_SCAN = 2000;

    /**
     * Constructor — runs when the class is created.
     *
     * @param array       $config   The config array from config.php
     * @param IdeSecurity $security The security guard instance
     */
    public function __construct(array $config, IdeSecurity $security)
    {
        $this->config   = $config;
        $this->security = $security;

        $realRoot = realpath(IDE_WORKSPACE);

        if ($realRoot === false) {
            mkdir(IDE_WORKSPACE, 0777, true);
            $realRoot = realpath(IDE_WORKSPACE);
        }

        $this->workspaceRoot = str_replace('\\', '/', (string) $realRoot);
    }


    /* ═══════════════════════════════════════════════════════════════
       SEARCH ACROSS ALL FILES
       ═══════════════════════════════════════════════════════════════
       The main search method. Scans every searchable file in the
       workspace and returns all matching lines grouped by file.

       Options:
         caseSensitive  bool   Match exact case (default: false)
         useRegex       bool   Treat query as a regex pattern (default: false)
         fileFilter     string Only search files matching this glob (e.g. "*.php")
         maxResults     int    Override the default max total matches        */

    /**
     * Search all files in the workspace.
     *
     * @param string $query   The search query (text or regex pattern)
     * @param array  $options Search options (see above)
     * @return array Search results grouped by file
     */
    public function search(string $query, array $options = []): array
    {
        $query = trim($query);

        if ($query === '') {
            $this->security->fail('Search query cannot be empty.');
        }

        $caseSensitive = (bool) ($options['caseSensitive'] ?? false);
        $useRegex      = (bool) ($options['useRegex'] ?? false);
        $fileFilter    = trim((string) ($options['fileFilter'] ?? ''));
        $maxResults    = (int) ($options['maxResults'] ?? self::MAX_TOTAL_MATCHES);
        $maxResults    = max(1, min($maxResults, self::MAX_TOTAL_MATCHES));

        // If regex mode, validate the pattern BEFORE scanning any files.
        // This gives the user an immediate error instead of a slow scan
        // that fails on the first file.
        if ($useRegex) {
            $this->validateRegex($query);
        }

        // Get the list of files to search
        $files = $this->getSearchableFiles($fileFilter);

        $results       = [];
        $totalMatches  = 0;
        $totalFiles    = 0;
        $filesScanned  = 0;
        $truncated     = false;

        foreach ($files as $fileInfo) {
            // Stop if we've hit the result limit
            if ($totalMatches >= $maxResults) {
                $truncated = true;
                break;
            }

            // Safety valve: don't scan more files than the limit
            $filesScanned++;
            if ($filesScanned > self::MAX_FILES_TO_SCAN) {
                $truncated = true;
                break;
            }

            $fileMatches = $this->searchFile(
                $fileInfo['absolute'],
                $fileInfo['relative'],
                $query,
                $caseSensitive,
                $useRegex,
                $maxResults - $totalMatches  // remaining budget
            );

            if (!empty($fileMatches['matches'])) {
                $results[]     = $fileMatches;
                $totalMatches += $fileMatches['matchCount'];
                $totalFiles++;
            }
        }

        return [
            'query'        => $query,
            'caseSensitive' => $caseSensitive,
            'useRegex'     => $useRegex,
            'fileFilter'   => $fileFilter,
            'totalMatches' => $totalMatches,
            'totalFiles'   => $totalFiles,
            'filesScanned' => $filesScanned,
            'truncated'    => $truncated,
            'results'      => $results,
        ];
    }


    /* ═══════════════════════════════════════════════════════════════
       SEARCH A SINGLE FILE
       ═══════════════════════════════════════════════════════════════
       Searches one file and returns all matching lines. Used by both
       the workspace-wide search and the "Find in current file" feature.

       Returns:
         [
           'file'       => 'relative/path.php',
           'matchCount' => 3,
           'matches'    => [
             [
               'line'        => 12,           // 1-based line number
               'content'     => '    $x = 1;', // the full line (trimmed)
               'matchStart'  => 8,            // character offset of first match
               'matchLength' => 1,            // length of the matched text
             ],
             ...
           ]
         ]                                                        */

    /**
     * Search a single file for matches.
     *
     * @param string $absolutePath  Full server path to the file
     * @param string $relativePath  Path relative to workspace (for display)
     * @param string $query         The search query
     * @param bool   $caseSensitive Whether to match exact case
     * @param bool   $useRegex      Whether to treat query as regex
     * @param int    $budget        Max matches to find in this file
     * @return array File search results
     */
    public function searchFile(
        string $absolutePath,
        string $relativePath,
        string $query,
        bool   $caseSensitive = false,
        bool   $useRegex = false,
        int    $budget = self::MAX_MATCHES_PER_FILE
    ): array {
        $matches    = [];
        $matchCount = 0;
        $lineNumber = 0;
        $perFileMax = min($budget, self::MAX_MATCHES_PER_FILE);

        // Open the file and read it line by line.
        // This avoids loading the entire file into memory at once.
        $handle = @fopen($absolutePath, 'r');

        if ($handle === false) {
            return [
                'file'       => $relativePath,
                'matchCount' => 0,
                'matches'    => [],
            ];
        }

        while (($line = fgets($handle)) !== false) {
            $lineNumber++;

            // Stop if we've found enough matches in this file
            if ($matchCount >= $perFileMax) {
                break;
            }

            // Remove the trailing newline for clean display,
            // but keep the content otherwise intact.
            $lineContent = rtrim($line, "\r\n");

            // Skip empty lines (they can never match a non-empty query)
            if ($lineContent === '') {
                continue;
            }

            if ($useRegex) {
                // ── Regex search ──
                $found = $this->findRegexMatches(
                    $lineContent,
                    $query,
                    $caseSensitive
                );

                foreach ($found as $match) {
                    if ($matchCount >= $perFileMax) {
                        break;
                    }

                    $matches[] = [
                        'line'        => $lineNumber,
                        'content'     => $lineContent,
                        'matchStart'  => $match['start'],
                        'matchLength' => $match['length'],
                        'matchText'   => $match['text'],
                    ];
                    $matchCount++;
                }
            } else {
                // ── Plain text search ──
                $found = $this->findTextMatches(
                    $lineContent,
                    $query,
                    $caseSensitive
                );

                foreach ($found as $match) {
                    if ($matchCount >= $perFileMax) {
                        break;
                    }

                    $matches[] = [
                        'line'        => $lineNumber,
                        'content'     => $lineContent,
                        'matchStart'  => $match['start'],
                        'matchLength' => $match['length'],
                        'matchText'   => $match['text'],
                    ];
                    $matchCount++;
                }
            }
        }

        fclose($handle);

        return [
            'file'       => $relativePath,
            'matchCount' => $matchCount,
            'matches'    => $matches,
        ];
    }


    /* ═══════════════════════════════════════════════════════════════
REPLACE ACROSS FILES
═══════════════════════════════════════════════════════════════
Re-runs the same scan as search(), applies the replacement to
every file that has matches, and writes through IdeFileSystem so
the path jail + extension rules are enforced on every write.

Options: same as search(), plus:
files   array   Optional whitelist of relative paths (per-file replace)

Regex mode supports backreferences ($1, \1) in the replacement.
Plain-text mode is literal — "$1" stays "$1".                  */
    public function replace(string $query, string $replacement, array $options, IdeFileSystem $fs): array
    {
        $query = trim($query);
        if ($query === '') {
            $this->security->fail('Search query cannot be empty.');
        }

        $caseSensitive = (bool) ($options['caseSensitive'] ?? false);
        $useRegex      = (bool) ($options['useRegex'] ?? false);
        $fileFilter    = trim((string) ($options['fileFilter'] ?? ''));
        $onlyFiles     = $options['files'] ?? null;

        if ($useRegex) {
            $this->validateRegex($query);
        }

        // Optional whitelist for single-file replace
        $onlySet = null;
        if (is_array($onlyFiles) && count($onlyFiles) > 0) {
            $onlySet = [];
            foreach ($onlyFiles as $p) {
                $onlySet[trim((string) $p, '/')] = true;
            }
        }

        $files         = $this->getSearchableFiles($fileFilter);
        $report        = [];
        $totalReplaced = 0;
        $filesChanged  = 0;
        $scanned       = 0;

        foreach ($files as $fileInfo) {
            $scanned++;
            if ($scanned > self::MAX_FILES_TO_SCAN) {
                break;
            }

            $relative = $fileInfo['relative'];
            if ($onlySet !== null && !isset($onlySet[$relative])) {
                continue;
            }

            $content = @file_get_contents($fileInfo['absolute']);
            if ($content === false || $content === '') {
                continue;
            }

            $count      = 0;
            $newContent = null;

            if ($useRegex) {
                $flags      = $caseSensitive ? 'u' : 'iu';
                $regex      = '~' . $query . '~' . $flags;
                $newContent = @preg_replace($regex, $replacement, $content, -1, $count);
                if ($newContent === null) {
                    continue;   // preg error on this file — skip it
                }
            } elseif ($caseSensitive) {
                $newContent = str_replace($query, $replacement, $content, $count);
            } else {
                $newContent = str_ireplace($query, $replacement, $content, $count);
            }

            if ($count <= 0 || $newContent === $content) {
                continue;
            }

            // Write through the filesystem engine (jail + extension checks)
            $fs->saveFile($relative, $newContent);

            $filesChanged++;
            $totalReplaced += $count;
            $report[] = ['file' => $relative, 'replacements' => $count];
        }

        return [
            'query'         => $query,
            'replacement'   => $replacement,
            'filesChanged'  => $filesChanged,
            'totalReplaced' => $totalReplaced,
            'files'         => $report,
        ];
    }

    /* ═══════════════════════════════════════════════════════════════
       GET SEARCHABLE FILES
       ═══════════════════════════════════════════════════════════════
       Recursively scans the workspace and builds a list of files
       that are safe and sensible to search:
         - Inside the workspace (enforced by starting from workspaceRoot)
         - Not in a blocked segment (e.g. .git)
         - Not a symlink
         - Has an allowed extension (from config.php)
         - Not larger than MAX_SEARCH_FILE_SIZE
         - Not a binary file

       Optionally filters by a glob pattern (e.g. "*.php", "*.css").  */

    /**
     * Get all searchable files in the workspace.
     *
     * @param string $fileFilter Optional glob pattern (e.g. "*.php")
     * @return array List of ['absolute' => ..., 'relative' => ...] entries
     */
    public function getSearchableFiles(string $fileFilter = ''): array
    {
        $files = [];
        $this->scanForFiles($this->workspaceRoot, '', $files, $fileFilter);
        return $files;
    }

    /**
     * Recursively scan a directory for searchable files.
     * (Private — only called internally by getSearchableFiles)
     */
    private function scanForFiles(
        string $dir,
        string $relativeDir,
        array  &$files,
        string $fileFilter
    ): void {
        // Safety: don't collect more files than we can handle
        if (count($files) >= self::MAX_FILES_TO_SCAN) {
            return;
        }

        $entries = @scandir($dir);

        if ($entries === false) {
            return;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $absolutePath = $dir . '/' . $entry;
            $relativePath = $relativeDir === ''
                ? $entry
                : $relativeDir . '/' . $entry;

            // Skip symlinks (they could point outside the workspace)
            if (is_link($absolutePath)) {
                continue;
            }

            // Skip blocked segments (e.g. .git)
            if ($this->isBlockedSegment($entry)) {
                continue;
            }

            if (is_dir($absolutePath)) {
                // Recurse into subdirectories
                $this->scanForFiles($absolutePath, $relativePath, $files, $fileFilter);
                continue;
            }

            if (!is_file($absolutePath)) {
                continue;
            }

            // Check if the file is searchable
            if (!$this->isSearchableFile($absolutePath, $entry, $fileFilter)) {
                continue;
            }

            $files[] = [
                'absolute' => $absolutePath,
                'relative' => $relativePath,
            ];
        }
    }


    /* ═══════════════════════════════════════════════════════════════
       FILE SEARCHABILITY CHECKS
       ═══════════════════════════════════════════════════════════════ */

    /**
     * Check if a file is safe and sensible to search.
     *
     * @param string $absolutePath Full server path
     * @param string $fileName     Just the file name (for extension check)
     * @param string $fileFilter   Optional glob pattern
     * @return bool True if the file should be searched
     */
    private function isSearchableFile(
        string $absolutePath,
        string $fileName,
        string $fileFilter
    ): bool {
        // 1. Check file size — skip files that are too large
        $size = (int) @filesize($absolutePath);

        if ($size > self::MAX_SEARCH_FILE_SIZE) {
            return false;
        }

        // Skip empty files (nothing to search)
        if ($size === 0) {
            return false;
        }

        // 2. Check extension against the allowed list
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowed   = $this->config['security']['allowed_extensions'] ?? [];

        // Also allow files with no extension if their full name is in the list
        // (e.g. ".gitignore", "LICENSE", "Makefile")
        $baseName    = strtolower($fileName);
        $extAllowed  = in_array($extension, $allowed, true);
        $nameAllowed = in_array($baseName, $allowed, true);

        if (!$extAllowed && !$nameAllowed) {
            return false;
        }

        // 3. Apply the file filter glob if provided
        if ($fileFilter !== '') {
            if (!fnmatch($fileFilter, $fileName, FNM_CASEFOLD)) {
                return false;
            }
        }

        // 4. Check if the file is binary (not text)
        //    Read the first 8KB and look for null bytes.
        if ($this->isBinaryFile($absolutePath)) {
            return false;
        }

        return true;
    }

    /**
     * Check if a file appears to be binary (not text).
     * Reads the first 8KB and looks for null bytes.
     *
     * @param string $absolutePath Full server path
     * @return bool True if the file appears to be binary
     */
    private function isBinaryFile(string $absolutePath): bool
    {
        $handle = @fopen($absolutePath, 'rb');

        if ($handle === false) {
            return true; // Can't read it, treat as binary (skip it)
        }

        $sample = fread($handle, 8192);
        fclose($handle);

        if ($sample === false) {
            return true;
        }

        return strpos($sample, "\0") !== false;
    }

    /**
     * Check if a file/folder name is in the blocked list.
     *
     * @param string $name The file or folder name
     * @return bool True if blocked
     */
    private function isBlockedSegment(string $name): bool
    {
        $blocked = $this->config['security']['blocked_segments'] ?? [];
        return in_array($name, $blocked, true);
    }


    /* ═══════════════════════════════════════════════════════════════
       MATCH FINDING — PLAIN TEXT
       ═══════════════════════════════════════════════════════════════
       Finds all occurrences of a plain text string within a line.
       Uses strpos/stripos for speed. Returns an array of matches
       with their position and length.                              */

    /**
     * Find all plain text matches in a line.
     *
     * @param string $line          The line content
     * @param string $query         The search query
     * @param bool   $caseSensitive Whether to match exact case
     * @return array List of ['start' => int, 'length' => int, 'text' => string]
     */
    private function findTextMatches(
        string $line,
        string $query,
        bool   $caseSensitive
    ): array {
        $matches     = [];
        $queryLength = strlen($query);
        $offset      = 0;
        $lineLength  = strlen($line);

        // Choose the right search function based on case sensitivity
        $searchFn = $caseSensitive ? 'strpos' : 'stripos';

        while ($offset < $lineLength) {
            $pos = $searchFn($line, $query, $offset);

            if ($pos === false) {
                break;
            }

            $matches[] = [
                'start'  => $pos,
                'length' => $queryLength,
                'text'   => substr($line, $pos, $queryLength),
            ];

            // Move past this match to find the next one
            $offset = $pos + $queryLength;

            // Prevent infinite loop on zero-length matches (shouldn't happen
            // with plain text, but just in case)
            if ($queryLength === 0) {
                break;
            }
        }

        return $matches;
    }


    /* ═══════════════════════════════════════════════════════════════
       MATCH FINDING — REGEX
       ═══════════════════════════════════════════════════════════════
       Finds all regex matches in a line using preg_match_all with
       PREG_OFFSET_CAPTURE. Returns an array of matches with their
       position and length.                                        */

    /**
     * Find all regex matches in a line.
     *
     * @param string $line          The line content
     * @param string $pattern       The regex pattern (without delimiters)
     * @param bool   $caseSensitive Whether to match exact case
     * @return array List of ['start' => int, 'length' => int, 'text' => string]
     */
    private function findRegexMatches(
        string $line,
        string $pattern,
        bool   $caseSensitive
    ): array {
        $matches = [];

        // Build the full regex with delimiters and flags.
        // We use ~ as the delimiter since it rarely appears in patterns.
        $flags = $caseSensitive ? 'u' : 'iu';
        $regex = '~' . $pattern . '~' . $flags;

        $result = @preg_match_all(
            $regex,
            $line,
            $rawMatches,
            PREG_OFFSET_CAPTURE
        );

        if ($result === false || $result === 0) {
            return $matches;
        }

        // $rawMatches[0] contains the full matches with offsets
        foreach ($rawMatches[0] as $match) {
            $matchText   = $match[0];
            $matchOffset = $match[1];

            // Skip zero-length matches to prevent clutter
            if ($matchText === '') {
                continue;
            }

            $matches[] = [
                'start'  => $matchOffset,
                'length' => strlen($matchText),
                'text'   => $matchText,
            ];
        }

        return $matches;
    }


    /* ═══════════════════════════════════════════════════════════════
       REGEX VALIDATION
       ═══════════════════════════════════════════════════════════════
       Validates a regex pattern BEFORE searching. This gives the
       user an immediate, friendly error message instead of a slow
       scan that fails partway through.                            */

    /**
     * Validate a regex pattern. Fails with a friendly error if invalid.
     *
     * @param string $pattern The regex pattern (without delimiters)
     */
    private function validateRegex(string $pattern): void
    {
        // Use ~ as delimiter (same as in findRegexMatches)
        $regex = '~' . $pattern . '~u';

        // Suppress the warning and check the return value
        $result = @preg_match($regex, '');

        if ($result === false) {
            // Get the last regex error message
            $errorCode = preg_last_error();

            $errorMessages = [
                PREG_INTERNAL_ERROR        => 'Internal regex error.',
                PREG_BACKTRACK_LIMIT_ERROR => 'Regex is too complex (backtrack limit exceeded).',
                PREG_RECURSION_LIMIT_ERROR => 'Regex recursion limit exceeded.',
                PREG_BAD_UTF8_ERROR        => 'Invalid UTF-8 in the pattern.',
                PREG_BAD_UTF8_OFFSET_ERROR => 'Invalid UTF-8 offset in the pattern.',
            ];

            // PREG_JIT_STACKLIMIT_ERROR may not exist in older PHP versions
            if (defined('PREG_JIT_STACKLIMIT_ERROR')) {
                $errorMessages[constant('PREG_JIT_STACKLIMIT_ERROR')] =
                    'Regex JIT stack limit exceeded.';
            }

            $message = $errorMessages[$errorCode]
                ?? 'Invalid regular expression pattern.';

            $this->security->fail('Regex error: ' . $message);
        }
    }


    /* ═══════════════════════════════════════════════════════════════
       QUICK FILE STATS (for the search UI)
       ═══════════════════════════════════════════════════════════════
       Returns basic stats about the workspace that the search UI
       can display (e.g. "Searching 47 files…").                  */

    /**
     * Get quick stats about searchable files in the workspace.
     *
     * @return array ['totalFiles' => int, 'totalSize' => int, 'totalSizeHuman' => string]
     */
    public function getSearchStats(): array
    {
        $files     = $this->getSearchableFiles();
        $totalSize = 0;

        foreach ($files as $file) {
            $totalSize += (int) @filesize($file['absolute']);
        }

        return [
            'totalFiles'     => count($files),
            'totalSize'      => $totalSize,
            'totalSizeHuman' => $this->formatBytes($totalSize),
        ];
    }


    /* ═══════════════════════════════════════════════════════════════
       HELPERS
       ═══════════════════════════════════════════════════════════════ */

    /**
     * Format a byte count into a human-readable string.
     *
     * @param int $bytes The size in bytes
     * @return string Human-readable size (e.g. "1.5 MB")
     */
    private function formatBytes(int $bytes): string
    {
        if ($bytes === 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $i     = (int) floor(log($bytes, 1024));
        $i     = min($i, count($units) - 1);

        return round($bytes / pow(1024, $i), 1) . ' ' . $units[$i];
    }
}
