<?php

declare(strict_types=1);
/**
 * ═══════════════════════════════════════════════════════════════════════════
 *  QUIRKY IDE — SNIPPET STUDIO SERVICE
 * ═══════════════════════════════════════════════════════════════════════════
 *
 *  Manages user-created boilerplates and snippets.
 *  Storage: app/.snippets/custom-snippets.json (outside workspace).
 *
 *  Each snippet:
 *    id       — unique identifier (auto-generated)
 *    lang     — language key matching language.js (php, javascript, css, etc.)
 *    label    — the trigger word you type to invoke it
 *    type     — 'snippet' (small reusable piece) or 'boilerplate' (full file starter)
 *    detail   — short description shown in autocomplete
 *    body     — the actual code (· = indent marker, \n = newline)
 *    createdAt / updatedAt — timestamps
 *
 * ═══════════════════════════════════════════════════════════════════════════
 */
class IdeSnippets
{
  private array $config;
  private IdeSecurity $security;

  /** Storage directory (app/.snippets/) */
  private string $storeDir;

  /** Storage file path */
  private string $storeFile;

  /** Maximum snippets total (safety cap for phones) */
  private const MAX_SNIPPETS = 500;

  /** Maximum body length per snippet (chars) */
  private const MAX_BODY_LENGTH = 50000;

  /** Maximum label length */
  private const MAX_LABEL_LENGTH = 80;

  /** Valid language keys (must match autocomplete.js pool ids) */
  private const VALID_LANGS = [
    'php',
    'html',
    'css',
    'javascript',
    'python',
    'sql',
    'shell',
    'c',
    'cpp',
    'java',
    'go',
    'rust',
    'ruby',
    'lua',
    'swift',
    'dart',
    'markdown',
    'kotlin',
    'csharp',
    'perl',
    'text'
  ];

  /** Valid snippet types */
  private const VALID_TYPES = ['snippet', 'boilerplate'];

  public function __construct(array $config, IdeSecurity $security)
  {
    $this->config   = $config;
    $this->security = $security;
    $this->storeDir = IDE_APP . '/.snippets';
    $this->storeFile = $this->storeDir . '/custom-snippets.json';
  }

    /* ═══════════════════════════════════════════════════════════
     *  LIST — get all custom snippets
     * ═══════════════════════════════════════════════════════════ */

  /**
   * Return all snippets, optionally filtered by language.
   */
  public function list(?string $lang = null): array
  {
    $data = $this->readStore();
    $snippets = $data['snippets'] ?? [];

    if ($lang !== null && $lang !== '') {
      $snippets = array_filter($snippets, function ($s) use ($lang) {
        return ($s['lang'] ?? '') === $lang;
      });
    }

    // Sort: newest first
    usort($snippets, function ($a, $b) {
      return ($b['updatedAt'] ?? 0) - ($a['updatedAt'] ?? 0);
    });

    return [
      'snippets' => array_values($snippets),
      'count'    => count($snippets),
      'total'    => count($data['snippets'] ?? []),
    ];
  }

  /* ═══════════════════════════════════════════════════════════
     *  SAVE — create or update a snippet
     * ═══════════════════════════════════════════════════════════ */

  public function save(array $input): array
  {
    $id     = trim((string) ($input['id'] ?? ''));
    $lang   = strtolower(trim((string) ($input['lang'] ?? '')));
    $label  = trim((string) ($input['label'] ?? ''));
    $type   = strtolower(trim((string) ($input['type'] ?? 'snippet')));
    $detail = trim((string) ($input['detail'] ?? ''));
    $body   = (string) ($input['body'] ?? '');

    // Validate language
    if (!in_array($lang, self::VALID_LANGS, true)) {
      $this->security->fail('Invalid language: "' . $lang . '". Valid: ' . implode(', ', self::VALID_LANGS));
    }

    // Validate label
    if ($label === '' || strlen($label) > self::MAX_LABEL_LENGTH) {
      $this->security->fail('Label must be 1–' . self::MAX_LABEL_LENGTH . ' characters.');
    }
    if (!preg_match('/^[A-Za-z0-9_\-\.\+]+$/', $label)) {
      $this->security->fail('Label can only contain letters, numbers, hyphens, underscores, dots, and plus signs.');
    }

    // Validate type
    if (!in_array($type, self::VALID_TYPES, true)) {
      $this->security->fail('Type must be "snippet" or "boilerplate".');
    }

    // Validate body
    if ($body === '') {
      $this->security->fail('Snippet body cannot be empty.');
    }
    if (mb_strlen($body) > self::MAX_BODY_LENGTH) {
      $this->security->fail('Snippet body exceeds ' . self::MAX_BODY_LENGTH . ' characters.');
    }

    $data = $this->readStore();
    $snippets = $data['snippets'] ?? [];

    // Check total limit (only for new snippets)
    if ($id === '' && count($snippets) >= self::MAX_SNIPPETS) {
      $this->security->fail('Snippet limit reached (' . self::MAX_SNIPPETS . '). Delete some first.');
    }

    // Check for duplicate label within same language
    foreach ($snippets as $existing) {
      if (
        $existing['lang'] === $lang
        && $existing['label'] === $label
        && ($id === '' || $existing['id'] !== $id)
      ) {
        $this->security->fail('A snippet named "' . $label . '" already exists for ' . $lang . '.');
      }
    }

    $now = time();

    if ($id !== '') {
      // UPDATE existing
      $found = false;
      foreach ($snippets as &$s) {
        if ($s['id'] === $id) {
          $s['lang']      = $lang;
          $s['label']     = $label;
          $s['type']      = $type;
          $s['detail']    = $detail;
          $s['body']      = $body;
          $s['updatedAt'] = $now;
          $found = true;
          break;
        }
      }
      unset($s);
      if (!$found) {
        $this->security->fail('Snippet not found: ' . $id, 404);
      }
    } else {
      // CREATE new
      $id = $this->generateId($lang);
      $snippets[] = [
        'id'        => $id,
        'lang'      => $lang,
        'label'     => $label,
        'type'      => $type,
        'detail'    => $detail,
        'body'      => $body,
        'createdAt' => $now,
        'updatedAt' => $now,
      ];
    }

    $data['snippets'] = $snippets;
    $this->writeStore($data);

    return [
      'saved' => true,
      'id'    => $id,
      'lang'  => $lang,
      'label' => $label,
    ];
  }

  /* ═══════════════════════════════════════════════════════════
     *  DELETE — remove a snippet
     * ═══════════════════════════════════════════════════════════ */

  public function delete(string $id): array
  {
    $data = $this->readStore();
    $snippets = $data['snippets'] ?? [];
    $before = count($snippets);

    $snippets = array_filter($snippets, function ($s) use ($id) {
      return $s['id'] !== $id;
    });

    if (count($snippets) === $before) {
      $this->security->fail('Snippet not found: ' . $id, 404);
    }

    $data['snippets'] = array_values($snippets);
    $this->writeStore($data);

    return ['deleted' => true, 'id' => $id];
  }

  /* ═══════════════════════════════════════════════════════════
     *  IMPORT — bulk import from JSON structure
     * ═══════════════════════════════════════════════════════════ */

  public function import(array $incoming): array
  {
    $items = $incoming['snippets'] ?? [];
    if (!is_array($items) || empty($items)) {
      $this->security->fail('No snippets found in the import data.');
    }

    $data = $this->readStore();
    $snippets = $data['snippets'] ?? [];
    $imported = 0;
    $skipped  = 0;
    $errors   = [];

    foreach ($items as $item) {
      if (!is_array($item)) {
        $skipped++;
        continue;
      }

      $lang  = strtolower(trim((string) ($item['lang'] ?? '')));
      $label = trim((string) ($item['label'] ?? ''));
      $type  = strtolower(trim((string) ($item['type'] ?? 'snippet')));
      $body  = (string) ($item['body'] ?? '');

      // Skip invalid entries
      if (
        !in_array($lang, self::VALID_LANGS, true)
        || $label === ''
        || $body === ''
        || strlen($label) > self::MAX_LABEL_LENGTH
        || mb_strlen($body) > self::MAX_BODY_LENGTH
      ) {
        $skipped++;
        continue;
      }

      if (!in_array($type, self::VALID_TYPES, true)) {
        $type = 'snippet';
      }

      // Check total limit
      if (count($snippets) >= self::MAX_SNIPPETS) {
        $errors[] = 'Snippet limit reached; remaining items skipped.';
        break;
      }

      // Skip duplicates (same lang + label)
      $isDup = false;
      foreach ($snippets as $existing) {
        if ($existing['lang'] === $lang && $existing['label'] === $label) {
          $isDup = true;
          break;
        }
      }
      if ($isDup) {
        $skipped++;
        continue;
      }

      $now = time();
      $snippets[] = [
        'id'        => $this->generateId($lang),
        'lang'      => $lang,
        'label'     => $label,
        'type'      => $type,
        'detail'    => trim((string) ($item['detail'] ?? '')),
        'body'      => $body,
        'createdAt' => $now,
        'updatedAt' => $now,
      ];
      $imported++;
    }

    $data['snippets'] = $snippets;
    $this->writeStore($data);

    return [
      'imported' => $imported,
      'skipped'  => $skipped,
      'errors'   => $errors,
    ];
  }

  /* ═══════════════════════════════════════════════════════════
     *  EXPORT — return all snippets as a portable JSON structure
     * ═══════════════════════════════════════════════════════════ */

  public function export(): array
  {
    $data = $this->readStore();
    return [
      'app'       => 'quirky-ide-snippets',
      'version'   => 1,
      'exportedAt' => date('c'),
      'snippets'  => $data['snippets'] ?? [],
    ];
  }

  /* ═══════════════════════════════════════════════════════════
     *  COVERAGE — snippet counts per language (for the UI overview)
     * ═══════════════════════════════════════════════════════════ */

  public function coverage(): array
  {
    $data = $this->readStore();
    $snippets = $data['snippets'] ?? [];
    $counts = [];

    foreach ($snippets as $s) {
      $lang = $s['lang'] ?? 'text';
      if (!isset($counts[$lang])) {
        $counts[$lang] = ['snippet' => 0, 'boilerplate' => 0];
      }
      $type = ($s['type'] ?? 'snippet') === 'boilerplate' ? 'boilerplate' : 'snippet';
      $counts[$lang][$type]++;
    }

    return ['coverage' => $counts];
  }

  /* ═══════════════════════════════════════════════════════════
     *  INTERNAL HELPERS
     * ═══════════════════════════════════════════════════════════ */

  private function readStore(): array
  {
    if (!is_file($this->storeFile)) {
      return ['version' => 1, 'snippets' => []];
    }
    $raw = @file_get_contents($this->storeFile);
    if ($raw === false || $raw === '') {
      return ['version' => 1, 'snippets' => []];
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
      return ['version' => 1, 'snippets' => []];
    }
    return $decoded;
  }

  private function writeStore(array $data): void
  {
    if (!is_dir($this->storeDir)) {
      @mkdir($this->storeDir, 0700, true);
    }
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (@file_put_contents($this->storeFile, $json, LOCK_EX) === false) {
      $this->security->fail('Could not write snippet store.', 500);
    }
  }

  private function generateId(string $lang): string
  {
    return $lang . '-' . substr(md5(uniqid((string) mt_rand(), true)), 0, 12);
  }
}
