<?php

declare(strict_types=1);
/**
 * ═══════════════════════════════════════════════════════════════════════════
 *  QUIRKY IDE — AI PROVIDER STORE · PRESETS · REGISTRY · MANAGER  (v9)
 * ═══════════════════════════════════════════════════════════════════════════
 *
 *  Fully generic multi-provider system. ANY provider works given a
 *  Base URL + API Key + model; the presets below merely PREFILL those
 *  fields. Nothing is hardcoded to one vendor, and nothing defaults to a
 *  local runtime (this is a phone app — Ollama/LM Studio are just LAN
 *  targets the user enters by URL).
 *
 *  STORE: app/.ai-providers.json  (LOCK_EX write, atomic tmp+rename)
 *    {"version":1,
 *     "active":"<providerId>",
 *     "providers":{"<id>":{"id","label","wire","preset?","baseUrl",
 *                          "apiKey","model","headers":{},"keyOptional?",
 *                          "createdAt","updatedAt","lastTest"?}}}
 *
 *  KEYS NEVER LEAVE THE SERVER. Every API path exposes maskedKey only.
 *
 *  MIGRATION (first load): legacy app/.ai-secrets.json shaped
 *    {"nvidia":{"api_key":"…","base_url":"…"}}
 *  seeds provider id 'nvidia' (preset 'nvidia'); active='nvidia' when it
 *  carries a key. The legacy file itself is left untouched so the old
 *  NvidiaAI shim keeps reading through the store transparently.
 *
 *  EXPOSES: AiProviders
 * ═══════════════════════════════════════════════════════════════════════════
 */

require_once __DIR__ . '/AiProvider.php';
require_once __DIR__ . '/AiAdapterOpenAI.php';
require_once __DIR__ . '/AiAdapterOllama.php';
require_once __DIR__ . '/AiAdapterAnthropic.php';
require_once __DIR__ . '/AiAdapterGemini.php';

class AiProviders
{
  public const WIRES = ['openai', 'ollama', 'anthropic', 'gemini'];

  /** wire → adapter class */
  private const ADAPTERS = [
    'openai'    => 'AiAdapterOpenAI',
    'ollama'    => 'AiAdapterOllama',
    'anthropic' => 'AiAdapterAnthropic',
    'gemini'    => 'AiAdapterGemini',
  ];

  /**
   * BUILTIN PRESETS — prefills only; every field stays user-editable.
   * `curated` is the offline fallback model list shown before/without a
   * live refresh (nvidia ports the full CURATED_MODELS catalog).
   */
  private const PRESETS = [
    'openai' => [
      'label' => 'OpenAI',
      'wire' => 'openai',
      'baseUrl' => 'https://api.openai.com/v1',
      'keyOptional' => false,
      'curated' => [
        ['id' => 'gpt-4o-mini', 'label' => 'GPT-4o mini', 'tag' => 'OpenAI · Fast & cheap'],
        ['id' => 'gpt-4o', 'label' => 'GPT-4o', 'tag' => 'OpenAI · Flagship multimodal'],
        ['id' => 'gpt-4.1-mini', 'label' => 'GPT-4.1 mini', 'tag' => 'OpenAI · Balanced'],
      ],
      'note' => 'platform.openai.com API key (sk-…)',
    ],
    'groq' => [
      'label' => 'Groq',
      'wire' => 'openai',
      'baseUrl' => 'https://api.groq.com/openai/v1',
      'keyOptional' => false,
      'curated' => [
        ['id' => 'llama-3.3-70b-versatile', 'label' => 'Llama 3.3 70B Versatile', 'tag' => 'Groq · Best general'],
        ['id' => 'llama-3.1-8b-instant', 'label' => 'Llama 3.1 8B Instant', 'tag' => 'Groq · Fastest'],
        ['id' => 'openai/gpt-oss-120b', 'label' => 'GPT-OSS 120B', 'tag' => 'Groq · Open-weight'],
        ['id' => 'qwen/qwen3-32b', 'label' => 'Qwen3 32B', 'tag' => 'Groq · Reasoning'],
      ],
      'note' => 'console.groq.com keys are free-tier',
    ],
    'deepseek' => [
      'label' => 'DeepSeek',
      'wire' => 'openai',
      'baseUrl' => 'https://api.deepseek.com/v1',
      'keyOptional' => false,
      'curated' => [
        ['id' => 'deepseek-chat', 'label' => 'DeepSeek Chat', 'tag' => 'DeepSeek · V3 general'],
        ['id' => 'deepseek-reasoner', 'label' => 'DeepSeek Reasoner', 'tag' => 'DeepSeek · R1 reasoning'],
      ],
      'note' => 'platform.deepseek.com',
    ],
    'together' => [
      'label' => 'Together AI',
      'wire' => 'openai',
      'baseUrl' => 'https://api.together.xyz/v1',
      'keyOptional' => false,
      'curated' => [
        ['id' => 'meta-llama/Llama-3.3-70B-Instruct-Turbo', 'label' => 'Llama 3.3 70B Turbo', 'tag' => 'Together · Fast'],
        ['id' => 'Qwen/Qwen2.5-Coder-32B-Instruct', 'label' => 'Qwen2.5 Coder 32B', 'tag' => 'Together · Code'],
        ['id' => 'deepseek-ai/DeepSeek-V3', 'label' => 'DeepSeek V3', 'tag' => 'Together · Powerful'],
      ],
      'note' => 'api.together.xyz',
    ],
    'openrouter' => [
      'label' => 'OpenRouter',
      'wire' => 'openai',
      'baseUrl' => 'https://openrouter.ai/api/v1',
      'keyOptional' => false,
      'curated' => [
        ['id' => 'openai/gpt-4o-mini', 'label' => 'GPT-4o mini', 'tag' => 'OR · Cheap'],
        ['id' => 'meta-llama/llama-3.3-70b-instruct', 'label' => 'Llama 3.3 70B', 'tag' => 'OR · Open'],
        ['id' => 'deepseek/deepseek-r1:free', 'label' => 'DeepSeek R1 (free)', 'tag' => 'OR · Free tier'],
        ['id' => 'qwen/qwen-2.5-coder-32b-instruct', 'label' => 'Qwen2.5 Coder 32B', 'tag' => 'OR · Code'],
      ],
      // OpenRouter etiquette headers are auto-added for this preset on save.
      'extraHeaders' => ['HTTP-Referer' => 'https://quirky.local', 'X-Title' => 'Quirky IDE'],
      'note' => 'One key, hundreds of models',
    ],
    'nvidia' => [
      'label' => 'NVIDIA NIM',
      'wire' => 'openai',
      'baseUrl' => 'https://integrate.api.nvidia.com/v1',
      'keyOptional' => false,
      'curated' => [
        ['id' => 'nvidia/nemotron-3-super-120b-a12b', 'label' => 'Nemotron 3 Super 120B', 'tag' => 'NVIDIA · Best all-rounder'],
        ['id' => 'nvidia/nemotron-3-ultra-550b-a55b', 'label' => 'Nemotron 3 Ultra 550B', 'tag' => 'NVIDIA · Most powerful'],
        ['id' => 'nvidia/nemotron-3-nano-30b-a3b', 'label' => 'Nemotron 3 Nano 30B', 'tag' => 'NVIDIA · Fast & efficient'],
        ['id' => 'nvidia/llama-3.3-nemotron-super-49b-v1.5', 'label' => 'Nemotron Super 49B v1.5', 'tag' => 'NVIDIA · Balanced'],
        ['id' => 'nvidia/llama-3.1-nemotron-ultra-253b-v1', 'label' => 'Nemotron Ultra 253B', 'tag' => 'NVIDIA · Heavy hitter'],
        ['id' => 'nvidia/nvidia-nemotron-nano-9b-v2', 'label' => 'Nemotron Nano 9B v2', 'tag' => 'NVIDIA · Lightweight'],
        ['id' => 'nvidia/nemotron-mini-4b-instruct', 'label' => 'Nemotron Mini 4B', 'tag' => 'NVIDIA · Fastest'],
        ['id' => 'meta/llama-3.3-70b-instruct', 'label' => 'Llama 3.3 70B', 'tag' => 'Meta · Excellent general'],
        ['id' => 'meta/llama-3.1-70b-instruct', 'label' => 'Llama 3.1 70B', 'tag' => 'Meta · Reliable'],
        ['id' => 'meta/llama-3.1-8b-instruct', 'label' => 'Llama 3.1 8B', 'tag' => 'Meta · Fast'],
        ['id' => 'meta/llama-3.2-3b-instruct', 'label' => 'Llama 3.2 3B', 'tag' => 'Meta · Tiny'],
        ['id' => 'qwen/qwen3-coder-480b-a35b-instruct', 'label' => 'Qwen3 Coder 480B', 'tag' => 'Qwen · Best for code'],
        ['id' => 'qwen/qwen2.5-coder-32b-instruct', 'label' => 'Qwen 2.5 Coder 32B', 'tag' => 'Qwen · Code specialist'],
        ['id' => 'qwen/qwen3-next-80b-a3b-instruct', 'label' => 'Qwen3 Next 80B', 'tag' => 'Qwen · New generation'],
        ['id' => 'qwen/qwq-32b', 'label' => 'QwQ 32B', 'tag' => 'Qwen · Deep reasoning'],
        ['id' => 'deepseek-ai/deepseek-v4-pro', 'label' => 'DeepSeek V4 Pro', 'tag' => 'DeepSeek · Powerful'],
        ['id' => 'deepseek-ai/deepseek-v4-flash', 'label' => 'DeepSeek V4 Flash', 'tag' => 'DeepSeek · Fast'],
        ['id' => 'mistralai/mixtral-8x22b-instruct', 'label' => 'Mixtral 8x22B', 'tag' => 'Mistral · Large MoE'],
        ['id' => 'mistralai/mixtral-8x7b-instruct', 'label' => 'Mixtral 8x7B', 'tag' => 'Mistral · Balanced'],
        ['id' => 'mistralai/mistral-nemotron', 'label' => 'Mistral Nemotron', 'tag' => 'Mistral · NVIDIA-tuned'],
        ['id' => 'openai/gpt-oss-120b', 'label' => 'GPT-OSS 120B', 'tag' => 'OpenAI · Open-weight'],
        ['id' => 'openai/gpt-oss-20b', 'label' => 'GPT-OSS 20B', 'tag' => 'OpenAI · Compact'],
        ['id' => 'moonshotai/kimi-k2.6', 'label' => 'Kimi K2.6', 'tag' => 'Moonshot · Agentic'],
        ['id' => 'moonshotai/kimi-k2-instruct', 'label' => 'Kimi K2', 'tag' => 'Moonshot · Strong reasoning'],
        ['id' => 'minimaxai/minimax-m3', 'label' => 'MiniMax M3', 'tag' => 'MiniMax · Flagship'],
        ['id' => 'minimaxai/minimax-m2.7', 'label' => 'MiniMax M2.7', 'tag' => 'MiniMax · New frontier'],
        ['id' => 'z-ai/glm5.1', 'label' => 'GLM 5.1', 'tag' => 'Z-AI · Agentic'],
        ['id' => 'z-ai/glm-5.2', 'label' => 'GLM 5.2', 'tag' => 'Z-AI · Latest'],
        ['id' => 'microsoft/phi-4-mini-instruct', 'label' => 'Phi-4 Mini', 'tag' => 'Microsoft · Small & smart'],
        ['id' => 'google/gemma-2-2b-it', 'label' => 'Gemma 2 2B', 'tag' => 'Google · Lightweight'],
        ['id' => 'bytedance/seed-oss-36b-instruct', 'label' => 'Seed OSS 36B', 'tag' => 'ByteDance · Open'],
      ],
      'note' => 'build.nvidia.com — free nvapi- keys',
    ],
    'lmstudio' => [
      'label' => 'LM Studio',
      'wire' => 'openai',
      'baseUrl' => 'http://localhost:1234/v1',
      'keyOptional' => true,
      'curated' => [],
      'note' => 'LAN/desktop server — enter your URL',
    ],
    'custom' => [
      'label' => 'Custom (OpenAI-compatible)',
      'wire' => 'openai',
      'baseUrl' => '',
      'keyOptional' => false,
      'curated' => [],
      'note' => 'Any OpenAI-style /chat/completions endpoint',
    ],
    'anthropic' => [
      'label' => 'Anthropic',
      'wire' => 'anthropic',
      'baseUrl' => 'https://api.anthropic.com/v1',
      'keyOptional' => false,
      'curated' => [
        ['id' => 'claude-sonnet-4-5', 'label' => 'Claude Sonnet 4.5', 'tag' => 'Anthropic · Balanced flagship'],
        ['id' => 'claude-opus-4-1', 'label' => 'Claude Opus 4.1', 'tag' => 'Anthropic · Most capable'],
        ['id' => 'claude-haiku-4-5', 'label' => 'Claude Haiku 4.5', 'tag' => 'Anthropic · Fastest'],
      ],
      'note' => 'console.anthropic.com — free-text models allowed',
    ],
    'gemini' => [
      'label' => 'Google Gemini',
      'wire' => 'gemini',
      'baseUrl' => 'https://generativelanguage.googleapis.com/v1beta',
      'keyOptional' => false,
      'curated' => [
        ['id' => 'gemini-2.5-flash', 'label' => 'Gemini 2.5 Flash', 'tag' => 'Gemini · Fast'],
        ['id' => 'gemini-2.5-pro', 'label' => 'Gemini 2.5 Pro', 'tag' => 'Gemini · Most capable'],
        ['id' => 'gemini-2.0-flash', 'label' => 'Gemini 2.0 Flash', 'tag' => 'Gemini · Cheap'],
      ],
      'note' => 'aistudio.google.com/apikey — free tier',
    ],
    'huggingface' => [
      'label' => 'Hugging Face',
      'wire' => 'openai',
      'baseUrl' => 'https://api-inference.huggingface.co/v1',
      'keyOptional' => false,
      'curated' => [
        ['id' => 'meta-llama/Meta-Llama-3.1-8B-Instruct', 'label' => 'Llama 3.1 8B Instruct', 'tag' => 'HF · Fast'],
        ['id' => 'meta-llama/Llama-3.3-70B-Instruct', 'label' => 'Llama 3.3 70B Instruct', 'tag' => 'HF · Powerful'],
        ['id' => 'mistralai/Mistral-Small-24B-Instruct-2501', 'label' => 'Mistral Small 24B', 'tag' => 'HF · Balanced'],
        ['id' => 'Qwen/Qwen2.5-Coder-32B-Instruct', 'label' => 'Qwen2.5 Coder 32B', 'tag' => 'HF · Code'],
      ],
      'note' => 'hf.co/settings/tokens — Inference API key (hf_…)',
    ],
    'ollama' => [
      'label' => 'Ollama / LAN server',
      'wire' => 'ollama',
      'baseUrl' => 'http://192.168.1.100:11434',
      'keyOptional' => true,
      'curated' => [],
      'note' => 'Enter your Ollama server URL (e.g. http://192.168.x.x:11434)',
    ],
  ];

  private array $data = ['version' => 1, 'active' => null, 'providers' => []];
  private array $aiKnobs = [];
  private string $storeFile;
  private string $legacySecretsFile;
  private bool $dirty = false;

  public function __construct(array $config = [], ?string $storeFile = null)
  {
    $this->aiKnobs = is_array($config['ai'] ?? null) ? $config['ai'] : [];
    $this->storeFile = $storeFile ?? __DIR__ . '/../.ai-providers.json';
    $this->legacySecretsFile = dirname($this->storeFile) . '/.ai-secrets.json';
    $this->load();
  }

  /* ═══════════════════════════════════════════════════════════
     PERSISTENCE (LOCK_EX, atomic tmp+rename) + MIGRATION
    ═══════════════════════════════════════════════════════════ */

  private function load(): void
  {
    if (is_file($this->storeFile)) {
      $raw = @file_get_contents($this->storeFile);
      $d = $raw ? json_decode($raw, true) : null;
      if (is_array($d)) {
        $this->data = [
          'version'   => 1,
          'active'    => isset($d['active']) && is_string($d['active']) && $d['active'] !== '' ? $d['active'] : null,
          'providers' => is_array($d['providers'] ?? null) ? $d['providers'] : [],
        ];
        foreach ($this->data['providers'] as $k => $def) {
          if (!is_array($def)) unset($this->data['providers'][$k]);
        }
        return;
      }
    }
    // First load (or unreadable store): migrate legacy secrets.
    $this->migrateLegacySecrets();
  }

  /**
   * Legacy shape: {"nvidia":{"api_key":"…","base_url":"…"}} in
   * .ai-secrets.json → seed provider 'nvidia' (preset nvidia) and make it
   * active when it holds a key. The legacy file is left in place.
   */
  private function migrateLegacySecrets(): void
  {
    if (!is_file($this->legacySecretsFile)) return;
    $raw = @file_get_contents($this->legacySecretsFile);
    $legacy = $raw ? json_decode($raw, true) : null;
    if (!is_array($legacy) || !isset($legacy['nvidia']) || !is_array($legacy['nvidia'])) return;

    $apiKey  = trim((string) ($legacy['nvidia']['api_key'] ?? ''));
    $baseUrl = trim((string) ($legacy['nvidia']['base_url'] ?? '')) ?: self::PRESETS['nvidia']['baseUrl'];
    $now = gmdate('c');
    $this->data['providers']['nvidia'] = [
      'id'          => 'nvidia',
      'label'       => self::PRESETS['nvidia']['label'],
      'wire'        => 'openai',
      'preset'      => 'nvidia',
      'baseUrl'     => rtrim($baseUrl, '/'),
      'apiKey'      => $apiKey,
      'model'       => '',
      'headers'     => [],
      'keyOptional' => false,
      'createdAt'   => $now,
      'updatedAt'   => $now,
    ];
    if ($apiKey !== '') $this->data['active'] = 'nvidia';
    $this->dirty = true;
    $this->persist();
  }

  private function persist(): void
  {
    if (!$this->dirty) return;
    $json = json_encode($this->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $dir = dirname($this->storeFile);
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $tmp = $this->storeFile . '.tmp';
    if (@file_put_contents($tmp, $json, LOCK_EX) === false) {
      $this->dirty = false;
      return;
    }
    if (is_file($this->storeFile)) @unlink($this->storeFile);
    @rename($tmp, $this->storeFile);
    $this->dirty = false;
  }

  /* ═══════════════════════════════════════════════════════════
     REGISTRY LOOKUPS
    ═══════════════════════════════════════════════════════════ */

  public static function presetIds(): array
  {
    return array_keys(self::PRESETS);
  }

  public static function preset(string $id): ?array
  {
    return self::PRESETS[$id] ?? null;
  }

  public function isPreset(string $id): bool
  {
    return isset(self::PRESETS[$id]);
  }

  public function wireOf(string $id): ?string
  {
    if (isset($this->data['providers'][$id]['wire'])) return (string) $this->data['providers'][$id]['wire'];
    if (isset(self::PRESETS[$id]['wire'])) return (string) self::PRESETS[$id]['wire'];
    return null;
  }

  public function adapterClassFor(string $wire): ?string
  {
    return self::ADAPTERS[$wire] ?? null;
  }

  /* ═══════════════════════════════════════════════════════════
     PROVIDER CRUD (definitions hold the raw apiKey SERVER-SIDE ONLY)
    ═══════════════════════════════════════════════════════════ */

  public function exists(string $id): bool
  {
    return isset($this->data['providers'][$id]);
  }

  /** Raw definition (contains apiKey — never hand this to an API response). */
  public function get(string $id): ?array
  {
    return $this->data['providers'][$id] ?? null;
  }

  /** @return string[] */
  public function ids(): array
  {
    return array_keys($this->data['providers']);
  }

  /**
   * Create/update from user input. Empty apiKey on update = keep existing.
   * @return array masked summary
   */
  public function save(array $input): array
  {
    $def = $this->validateSave($input);
    $existing = $this->get($def['id']);
    if ($existing !== null && trim((string) ($input['apiKey'] ?? '')) === '') {
      $def['apiKey'] = (string) ($existing['apiKey'] ?? '');
    }
    if ($existing !== null) {
      $def['createdAt'] = $existing['createdAt'] ?? gmdate('c');
      $def['lastTest']  = $existing['lastTest'] ?? null;
    }
    $def['updatedAt'] = gmdate('c');
    ksort($def);
    $this->data['providers'][$def['id']] = $def;
    // First configured provider becomes active automatically.
    if ($this->activeId() === null || $this->data['active'] === $def['id']) {
      $this->setActive($def['id']);
    } else {
      $this->dirty = true;
      $this->persist();
    }
    return $this->summary($def);
  }

  /** Validate + normalize save input. Throws RuntimeException on bad input. */
  public function validateSave(array $input): array
  {
    $presetId = isset($input['preset']) && is_string($input['preset']) && $input['preset'] !== ''
      ? $input['preset'] : null;
    if ($presetId !== null && !isset(self::PRESETS[$presetId])) {
      throw new \RuntimeException('Unknown preset "' . $presetId . '".');
    }
    $preset = $presetId !== null ? self::PRESETS[$presetId] : null;

    // ── id ──
    $id = trim((string) ($input['id'] ?? ''));
    if ($id === '') {
      if ($presetId !== null) $id = $presetId;
      elseif (isset(self::PRESETS[trim(strtolower((string) ($input['label'] ?? '')))])) $id = strtolower((string) $input['label']);
      else $id = strtolower(trim((string) preg_replace('/[^a-z0-9._-]+/i', '-', (string) ($input['label'] ?? 'provider')), '-'));
    }
    if (!preg_match('/^[a-z0-9][a-z0-9._-]{2,40}$/', $id)) {
      throw new \RuntimeException('Provider id must match ^[a-z0-9][a-z0-9._-]{2,40}$ (got "' . $id . '").');
    }

    $existing = $this->get($id);

    // ── wire ──
    $wire = trim((string) ($input['wire'] ?? ($existing['wire'] ?? ($preset['wire'] ?? ''))));
    if (!in_array($wire, self::WIRES, true)) {
      throw new \RuntimeException('Invalid wire "' . $wire . '". Use one of: ' . implode(', ', self::WIRES) . '.');
    }

    // ── baseUrl ──
    $baseUrl = rtrim(trim((string) ($input['baseUrl'] ?? ($existing['baseUrl'] ?? ($preset['baseUrl'] ?? '')))), '/');
    if ($baseUrl === '') {
      throw new \RuntimeException('Base URL is required (e.g. https://api.example.com/v1).');
    }
    if (!preg_match('#^https?://[^\s]+$#i', $baseUrl)) {
      throw new \RuntimeException('Base URL must be a valid http(s) URL.');
    }
    if (strlen($baseUrl) > 300) throw new \RuntimeException('Base URL too long.');

    // ── label ──
    $label = trim((string) ($input['label'] ?? ($existing['label'] ?? ($preset['label'] ?? $id))));
    if ($label === '') $label = $id;
    if (mb_strlen($label) > 60) throw new \RuntimeException('Label must be ≤ 60 characters.');

    // ── apiKey (raw stored internally only; empty = keep existing) ──
    $apiKey = trim((string) ($input['apiKey'] ?? ''));
    if ($apiKey !== '' && strlen($apiKey) > 400) throw new \RuntimeException('API key too long.');

    // ── model slot (free text) ──
    $model = trim((string) ($input['model'] ?? ($existing['model'] ?? '')));
    if (mb_strlen($model) > 200) throw new \RuntimeException('Model name too long.');

    // ── extra headers ──
    $headers = [];
    $hj = $input['headersJson'] ?? null;
    if (is_string($hj) && trim($hj) !== '') {
      $decoded = json_decode($hj, true);
      if (!is_array($decoded)) throw new \RuntimeException('headersJson must be a JSON object of header name/value pairs.');
      foreach ($decoded as $hk => $hv) {
        if (!is_string($hk) || $hk === '' || !is_scalar($hv)) continue;
        $headers[$hk] = (string) $hv;
      }
    } elseif (is_array($hj)) {
      foreach ($hj as $hk => $hv) {
        if (is_string($hk) && $hk !== '' && is_scalar($hv)) $headers[$hk] = (string) $hv;
      }
    }
    if ($headers === [] && $presetId === 'openrouter' && $existing === null) {
      $headers = self::PRESETS['openrouter']['extraHeaders'] ?? [];
    }

    return [
      'id'          => $id,
      'label'       => $label,
      'wire'        => $wire,
      'preset'      => $presetId ?? ($existing['preset'] ?? null),
      'baseUrl'     => $baseUrl,
      'apiKey'      => $apiKey,
      'model'       => $model,
      'headers'     => $headers,
      'keyOptional' => (bool) ($input['keyOptional'] ?? ($preset['keyOptional'] ?? ($existing['keyOptional'] ?? false))),
      'createdAt'   => gmdate('c'),
    ];
  }

  /** Update ONLY the key of a provider (used by the legacy ai-nvidia-save-key route + shim). */
  public function saveKey(string $id, string $key): array
  {
    if (!$this->exists($id)) throw new \RuntimeException('Unknown provider "' . $id . '".');
    $this->data['providers'][$id]['apiKey'] = trim($key);
    $this->data['providers'][$id]['updatedAt'] = gmdate('c');
    $this->dirty = true;
    $this->persist();
    return $this->summary($this->data['providers'][$id]);
  }

  /**
   * Delete a provider INSTANCE. Deletion is allowed for ANY instance
   * (including preset instances like nvidia — its definition simply returns
   * to "not configured"; the builtin PRESET list itself always remains).
   * Returns the id that became active afterwards (or null).
   */
  public function delete(string $id): ?string
  {
    if (!$this->exists($id)) return $this->activeId();
    unset($this->data['providers'][$id]);
    if (($this->data['active'] ?? null) === $id) {
      $this->data['active'] = $this->fallbackActive();
    }
    $this->dirty = true;
    $this->persist();
    return $this->data['active'];
  }

  /* ═══════════════════════════════════════════════════════════
     ACTIVE PROVIDER + PER-PROVIDER MODEL SLOT (persisted)
    ═══════════════════════════════════════════════════════════ */

  public function activeId(): ?string
  {
    $a = $this->data['active'] ?? null;
    if (is_string($a) && $a !== '' && $this->exists($a)) return $a;
    return $this->fallbackActive();
  }

  private function fallbackActive(): ?string
  {
    // Prefer a configured provider that actually has credentials.
    foreach ($this->data['providers'] as $id => $def) {
      if (trim((string) ($def['apiKey'] ?? '')) !== '') return (string) $id;
    }
    foreach ($this->data['providers'] as $id => $def) {
      if (!empty($def['keyOptional'])) return (string) $id;
    }
    foreach (array_keys($this->data['providers']) as $id) return (string) $id;
    return null;
  }

  /** @throws RuntimeException when id doesn't exist as instance or preset */
  public function setActive(string $id): array
  {
    if (!$this->exists($id)) {
      if ($this->isPreset($id)) $this->ensureFromPreset($id);
      else throw new \RuntimeException('Unknown provider "' . $id . '".');
    }
    $this->data['active'] = $id;
    $this->dirty = true;
    $this->persist();
    return $this->summary($this->get($id));
  }

  /** Persisted per-provider model slot ('' when untouched). */
  public function slotModel(?string $id = null): string
  {
    $id = $id ?? $this->activeId();
    if ($id === null) return '';
    return trim((string) ($this->data['providers'][$id]['model'] ?? ''));
  }

  public function setModelFor(string $id, string $model): void
  {
    if (!$this->exists($id)) throw new \RuntimeException('Unknown provider "' . $id . '".');
    $this->data['providers'][$id]['model'] = trim($model);
    $this->data['providers'][$id]['updatedAt'] = gmdate('c');
    $this->dirty = true;
    $this->persist();
  }

  /** First curated fallback model of a provider's preset (may be ''). */
  public function curatedFirst(?string $id = null): ?array
  {
    $id = $id ?? $this->activeId();
    if ($id === null) return null;
    $presetId = $this->data['providers'][$id]['preset'] ?? null;
    if (!is_string($presetId) || !isset(self::PRESETS[$presetId])) return null;
    foreach ((self::PRESETS[$presetId]['curated'] ?? []) as $m) {
      if (is_array($m) && !empty($m['id'])) return $m;
    }
    return null;
  }

  /* ═══════════════════════════════════════════════════════════
     INSTANTIATION
    ═══════════════════════════════════════════════════════════ */

  /** Build an adapter for a stored provider (null when unknown). */
  public function make(string $id): ?AiProvider
  {
    $def = $this->get($id);
    if ($def === null) return null;
    return $this->makeForDefinition($def);
  }

  /** Adapter for the ACTIVE provider (null when nothing configured yet). */
  public function makeActive(): ?AiProvider
  {
    $id = $this->activeId();
    return $id !== null ? $this->make($id) : null;
  }

  public function makeForDefinition(array $def): ?AiProvider
  {
    $wire = (string) ($def['wire'] ?? '');
    $class = self::ADAPTERS[$wire] ?? null;
    if ($class === null) return null;
    if (!in_array($wire, self::WIRES, true)) return null;
    return new $class($this->aiKnobs, $def);
  }

  /** Definition for an unconfigured preset WITHOUT creating an instance. */
  public function transientPresetDef(string $presetId): ?array
  {
    if (!isset(self::PRESETS[$presetId])) return null;
    $p = self::PRESETS[$presetId];
    return [
      'id'          => $presetId,
      'label'       => $p['label'],
      'wire'        => $p['wire'],
      'preset'      => $presetId,
      'baseUrl'     => $p['baseUrl'],
      'apiKey'      => '',
      'model'       => '',
      'headers'     => $p['extraHeaders'] ?? [],
      'keyOptional' => (bool) ($p['keyOptional'] ?? false),
      'curated'     => $p['curated'] ?? [],
      'transient'   => true,
    ];
  }

  /** Materialize an instance from a preset (no-op when it already exists). */
  public function ensureFromPreset(string $presetId): array
  {
    if ($this->exists($presetId)) return $this->get($presetId);
    $def = $this->transientPresetDef($presetId);
    if ($def === null) throw new \RuntimeException('Unknown preset "' . $presetId . '".');
    unset($def['transient']);
    $def['createdAt'] = gmdate('c');
    $def['updatedAt'] = gmdate('c');
    ksort($def);
    $this->data['providers'][$presetId] = $def;
    if ($this->activeId() === null) $this->data['active'] = $presetId;
    $this->dirty = true;
    $this->persist();
    return $def;
  }

  /* ═══════════════════════════════════════════════════════════
     MASKED SUMMARIES (the ONLY shape ever returned over HTTP)
    ═══════════════════════════════════════════════════════════ */

  public function summary(array $def): array
  {
    $hasKey = trim((string) ($def['apiKey'] ?? '')) !== '';
    $lt = is_array($def['lastTest'] ?? null) ? $def['lastTest'] : null;
    return [
      'id'          => (string) ($def['id'] ?? ''),
      'label'       => (string) ($def['label'] ?? ''),
      'wire'        => (string) ($def['wire'] ?? ''),
      'preset'      => isset($def['preset']) && is_string($def['preset']) ? $def['preset'] : null,
      'baseUrl'     => (string) ($def['baseUrl'] ?? ''),
      'model'       => trim((string) ($def['model'] ?? '')),
      'hasKey'      => $hasKey,
      'maskedKey'   => AiAdapterBase::maskKeyStatic((string) ($def['apiKey'] ?? '')),
      'keyOptional' => (bool) ($def['keyOptional'] ?? false),
      'configured'  => true,
      'testedAt'    => $lt['at'] ?? null,
      'lastOk'      => $lt['ok'] ?? null,
      'lastError'   => $lt['error'] ?? null,
      'capabilities' => $this->capabilitiesForWire((string) ($def['wire'] ?? '')),
    ];
  }

  /** @return array<int,array> all providers, masked */
  public function summaries(): array
  {
    $out = [];
    foreach ($this->data['providers'] as $def) {
      $out[] = $this->summary($def);
    }
    return $out;
  }

  /**
   * Status summaries for ALL providers — deliberately network-free
   * (except nothing); live probing happens only via ai-provider-test or
   * IdeAI::getStatus() for the ACTIVE provider.
   */
  public function statuses(): array
  {
    $out = [];
    foreach ($this->data['providers'] as $def) {
      $s = $this->summary($def);
      $wire = (string) ($def['wire'] ?? '');
      $ready = $s['hasKey'] || $s['keyOptional'];
      $models = [];
      if ($wire !== 'ollama') {   // ollama lists would need a LAN probe — skip here
        try {
          $ad = $this->makeForDefinition($def + ['curated' => $this->presetCuratedFor($def)]);
          $models = $ad ? $ad->listModels(false) : [];
        } catch (\Throwable) {
          $models = [];
        }
      }
      $s['online']          = $ready;
      $s['modelLoaded']     = $ready && $s['model'] !== '';
      $s['availableModels'] = $models;
      $s['activeModel']     = $s['model'];
      $out[] = $s;
    }
    return $out;
  }

  private function presetCuratedFor(array $def): array
  {
    $pid = $def['preset'] ?? null;
    return (is_string($pid) && isset(self::PRESETS[$pid]['curated'])) ? self::PRESETS[$pid]['curated'] : [];
  }

  private function capabilitiesForWire(string $wire): array
  {
    return match ($wire) {
      'openai', 'ollama', 'anthropic', 'gemini' => ['tools' => true, 'streaming' => true],
      default => ['tools' => false, 'streaming' => false],
    };
  }

  /** Preset descriptors for the UI picker (prefill values only). */
  public function presets(): array
  {
    $out = [];
    foreach (self::PRESETS as $id => $p) {
      $out[] = [
        'id'          => $id,
        'label'       => $p['label'],
        'wire'        => $p['wire'],
        'baseUrl'     => $p['baseUrl'],
        'keyOptional' => (bool) ($p['keyOptional'] ?? false),
        'note'        => $p['note'] ?? '',
        'curated'     => $p['curated'] ?? [],
        'instanceExists' => $this->exists($id),
      ];
    }
    return $out;
  }

  /* ═══════════════════════════════════════════════════════════
     LIVE OPERATIONS (test / models)
    ═══════════════════════════════════════════════════════════ */

  /** Run testConnection(id), persist lastTest, return result + testedAt. */
  public function test(string $id): array
  {
    $def = $this->exists($id) ? $this->get($id) : $this->transientPresetDef($id);
    if ($def === null) throw new \RuntimeException('Unknown provider "' . $id . '".');
    $ad = $this->makeForDefinition($def);
    if ($ad === null) throw new \RuntimeException('No adapter available for wire "' . ($def['wire'] ?? '?') . '".');
    $res = $ad->testConnection();
    $res['provider'] = $id;
    $res['testedAt'] = gmdate('c');
    if ($this->exists($id)) {
      $this->data['providers'][$id]['lastTest'] = [
        'at'        => $res['testedAt'],
        'ok'        => (bool) ($res['ok'] ?? false),
        'latencyMs' => (int) ($res['latencyMs'] ?? 0),
        'error'     => $res['error'] ?? null,
      ];
      $this->data['providers'][$id]['updatedAt'] = $res['testedAt'];
      $this->dirty = true;
      $this->persist();
    }
    return $res;
  }

  /** Live/fallback model list for a provider (null id = active). */
  public function listModelsFor(?string $id, bool $refresh = false): array
  {
    $id = $id ?? $this->activeId();
    if ($id === null) return [];
    $def = $this->exists($id) ? $this->get($id) : $this->transientPresetDef($id);
    if ($def === null) return [];
    $ad = $this->makeForDefinition($def);
    if ($ad === null) return [];
    try {
      return $ad->listModels($refresh);
    } catch (\Throwable $e) {
      throw new \RuntimeException($e->getMessage(), 0, $e);
    }
  }
}
