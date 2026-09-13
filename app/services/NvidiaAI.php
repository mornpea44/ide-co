<?php

declare(strict_types=1);
/**
 * ═══════════════════════════════════════════════════════════════════════════
 *  QUIRKY IDE — NVIDIA NIM · LEGACY COMPATIBILITY SHIM  (v9)
 * ═══════════════════════════════════════════════════════════════════════════
 *
 *  DECISION (documented): this file was GUTTED in v9. NVIDIA is no longer a
 *  special-cased provider — it is one instance of the generic multi-provider
 *  system (wire 'openai', preset 'nvidia', store app/.ai-providers.json).
 *
 *  The CLASS is kept because the api.php classmap still references
 *  NvidiaAI → services/NvidiaAI.php and old code paths / extensions may
 *  still call it. It now extends the generic OpenAI-compatible adapter:
 *
 *    • construction reads the provider 'nvidia' definition from the
 *      AiProviders store (falling back to a transient preset definition,
 *      which itself was seeded from the legacy .ai-secrets.json migration);
 *    • saveApiKey() writes THROUGH the store, so the store stays the single
 *      source of truth;
 *    • chat()/chatStream()/listModels() are inherited from
 *      AiAdapterOpenAI with identical signatures. NOTE: chat() now returns
 *      the canonical {content,tool_calls} array instead of the raw decoded
 *      response body — the only historical caller was IdeAI, which has been
 *      rewritten accordingly.
 *
 *  New code should use AiProviders/AiAdapterOpenAI directly.
 * ═══════════════════════════════════════════════════════════════════════════
 */

require_once __DIR__ . '/AiProvider.php';
require_once __DIR__ . '/AiAdapterOpenAI.php';
require_once __DIR__ . '/AiProviders.php';

class NvidiaAI extends AiAdapterOpenAI
{
  private AiProviders $store;

  public function __construct(array $config)
  {
    $this->store = new AiProviders($config);
    $def = $this->store->get('nvidia') ?? $this->store->transientPresetDef('nvidia') ?? [];
    if (($def['wire'] ?? '') === '') $def['wire'] = 'openai';
    parent::__construct(is_array($config['ai'] ?? null) ? $config['ai'] : [], $def);
  }

  /** Store-backed key write (keeps .ai-providers.json authoritative). */
  public function saveApiKey(string $key): void
  {
    if (!$this->store->exists('nvidia')) {
      $this->store->ensureFromPreset('nvidia');
    }
    $this->store->saveKey('nvidia', trim($key));
    $def = $this->store->get('nvidia');
    if ($def !== null) $this->setDefinition($def);
  }

  public function clearApiKey(): void
  {
    $this->saveApiKey('');
  }

  public function hasApiKey(): bool
  {
    return $this->hasKey();
  }

  public function getMaskedKey(): string
  {
    return $this->maskedKey();
  }

  /**
   * Legacy status shape: {provider,hasKey,maskedKey,baseUrl,
   * availableModels,activeModel,modelLoaded,online}.
   */
  public function getStatus(): array
  {
    $s = $this->status();
    // Legacy behavior read the session slot for the active model first.
    if (session_status() === PHP_SESSION_ACTIVE) {
      $sess = trim((string) ($_SESSION['quirky_ai_model_nvidia'] ?? ''));
      if ($sess !== '') $s['activeModel'] = $sess;
    }
    if ($s['activeModel'] === '') {
      foreach ($s['availableModels'] as $m) {
        $s['activeModel'] = (string) ($m['id'] ?? '');
        break;
      }
    }
    $ready = $s['hasKey'];
    return [
      'provider'        => 'nvidia',
      'label'           => $this->label(),
      'hasKey'          => $ready,
      'maskedKey'       => $s['maskedKey'],
      'baseUrl'         => $s['baseUrl'],
      'availableModels' => $s['availableModels'],
      'activeModel'     => $s['activeModel'],
      'modelLoaded'     => $ready,   // cloud policy carried over: keyed ⇒ loaded
      'online'          => $ready,
    ];
  }

  /** Legacy alias of listModels() (curated catalog + optional live refresh). */
  public function getModelList(bool $refresh = false): array
  {
    return $this->listModels($refresh);
  }

  /**
   * Legacy static tool definitions (kept byte-for-byte from v8 so any
   * external caller sees the same array). IdeAI now uses its own single
   * canonical defs and never calls this.
   */
  public function getToolDefinitions(): array
  {
    $t = fn(string $n, string $d, array $p, array $r) => [
      'type' => 'function',
      'function' => [
        'name' => $n, 'description' => $d,
        'parameters' => ['type' => 'object', 'properties' => $p, 'required' => $r],
      ],
    ];
    $s = fn(string $d) => ['type' => 'string', 'description' => $d];
    return [
      $t('list_dir', 'List files and folders in a workspace directory', ['path' => $s('Directory path relative to workspace (empty = root)')], []),
      $t('read_file', 'Read the contents of a file in the workspace', ['path' => $s('File path relative to workspace')], ['path']),
      $t('write_file', 'Write content to a file (creates or overwrites)', ['path' => $s('File path relative to workspace'), 'content' => $s('The content to write')], ['path', 'content']),
      $t('delete_file', 'Delete a file from the workspace', ['path' => $s('File path relative to workspace')], ['path']),
      $t('create_folder', 'Create a new folder in the workspace', ['path' => $s('Folder path relative to workspace')], ['path']),
      $t('search_workspace', 'Search for text across all files in the workspace', ['query' => $s('Search query')], ['query']),
      $t('run_command', 'Run a shell command in the workspace directory', ['command' => $s('The command to execute')], ['command']),
      $t('get_file_info', 'Get metadata about a file (size, modified time, etc.)', ['path' => $s('File path relative to workspace')], ['path']),
    ];
  }
}
