<?php

declare(strict_types=1);
/**
 * ═══════════════════════════════════════════════════════════════════════════
 *  QUIRKY IDE — AI ROUTES (v9 · multi-provider)
 * ═══════════════════════════════════════════════════════════════════════════
 *
 *  All v8 actions keep working. The ai-nvidia-* family became thin aliases
 *  over the generic provider store: they operate on the 'nvidia' provider
 *  INSTANCE (auto-created from its preset when missing) instead of a
 *  hardcoded NvidiaAI client.
 *
 *  NEW in v9:
 *    ai-providers-list   GET   → {active, presets, providers:[masked]}
 *    ai-providers-save   POST  → {…masked summary}   (empty apiKey = keep)
 *    ai-providers-delete POST  {id}                  → any instance deletable
 *    ai-provider-test    POST  {id}                  → {ok,latencyMs,…}
 *    ai-models           GET   ?provider=&refresh=1  → {models:[]}
 *
 *  EXTENDED:
 *    ai-provider         POST {provider} — accepts ANY stored provider id or
 *                        preset id (unknown ids still fail; 'ollama'/'nvidia'
 *                        keep working because presets materialize on demand)
 *    ai-provider-status        — active + all masked statuses
 *
 *  KEYS NEVER LEAVE THE SERVER: every response carries maskedKey only.
 *
 *  This file is `require`d by app/api.php and inherits:
 *    $config, $security, $fs, $search, $terminal, $action, $method, $input
 *
 * ═══════════════════════════════════════════════════════════════════════════
 */

/**
 * @var mixed $action
 * @var mixed $method
 * @var mixed $config
 */

require_once __DIR__ . '/../services/AiProviders.php';

/** Local helper: store wired to this install's config. */
$aiStore = fn(): AiProviders => new AiProviders($config);

/** Local helper: resolve a provider id from input, failing when unknown. */
$aiResolveProvider = function (string $id) use ($aiStore, $security): AiProviders {
  $store = $aiStore();
  if ($id === '') $security->fail('Provider id is required.');
  if (!$store->exists($id)) {
    // Presets materialize as instances on demand; truly unknown ids fail.
    if (!$store->isPreset($id)) $security->fail('Unknown provider "' . $id . '".');
  }
  return $store;
};

switch ($action) {

  /* ── AI ASSISTANT ───────────────────────────────────────────── */
  case 'ai-status':
    if (empty($config['ai']['enabled'])) {
      $security->respond(['enabled' => false]);
    }
    $ai = new IdeAI($config, $security, $fs, $search, $terminal);
    $security->respond($ai->getStatus());
    break;

  case 'ai-chat':
    if ($method !== 'POST') $security->fail('Use POST.', 405);
    if (empty($config['ai']['enabled'])) $security->fail('AI is disabled in config.php.', 403);
    $ai = new IdeAI($config, $security, $fs, $search, $terminal);
    $message = trim((string) ($input['message'] ?? ''));
    if ($message === '') $security->fail('Message cannot be empty.');
    $security->respond($ai->chat($message));
    break;

  case 'ai-approve':
    if ($method !== 'POST') $security->fail('Use POST.', 405);
    if (empty($config['ai']['enabled'])) $security->fail('AI is disabled.', 403);
    $ai = new IdeAI($config, $security, $fs, $search, $terminal);
    $approvals = $input['approvals'] ?? [];
    if (!is_array($approvals)) $security->fail('Invalid approvals.');
    $security->respond($ai->approveToolCalls($approvals));
    break;

  case 'ai-clear':
    if ($method !== 'POST') $security->fail('Use POST.', 405);
    $ai = new IdeAI($config, $security, $fs, $search, $terminal);
    $ai->clearHistory();
    $security->respond(['cleared' => true]);
    break;

  /* ── v9.1: transcript restore — the panel renders the stored
     conversation on open instead of a blank welcome screen. ── */
  case 'ai-history':
    $ai = new IdeAI($config, $security, $fs, $search, $terminal);
    // {provider, model, perm, history:[{role,content,tool_calls?,…}]}
    $security->respond($ai->getTranscriptState());
    break;

  case 'ai-permission':
    if ($method !== 'POST') $security->fail('Use POST.', 405);
    $ai = new IdeAI($config, $security, $fs, $search, $terminal);
    $ai->setPermissionMode((string) ($input['mode'] ?? 'ask_destructive'));
    $security->respond($ai->getStatus());
    break;

  case 'ai-model':
    if ($method !== 'POST') $security->fail('Use POST.', 405);
    if (empty($config['ai']['enabled'])) $security->fail('AI is disabled in config.php.', 403);
    $ai = new IdeAI($config, $security, $fs, $search, $terminal);
    // v9: honor the provider hint the client already sends (was silently dropped).
    $ai->setModel((string) ($input['model'] ?? ''), (string) ($input['provider'] ?? ''));
    $security->respond($ai->getStatus());
    break;

  /* ── NVIDIA ACTIONS (legacy aliases over the generic store) ─── */
  case 'ai-nvidia-status':
    $store = $aiResolveProvider('nvidia');
    $def = $store->exists('nvidia') ? $store->get('nvidia') : $store->transientPresetDef('nvidia');
    $ad = $def ? $store->makeForDefinition($def) : null;
    if ($ad === null) $security->fail('NVIDIA provider is not available.');
    $status = $ad->status();
    $security->startSession();
    $status['permissionMode'] = $_SESSION['quirky_ai_perm']
      ?? ($config['ai']['permission_mode'] ?? 'ask_destructive');
    $security->respond($status);
    break;

  case 'ai-nvidia-save-key':
    if ($method !== 'POST') $security->fail('Use POST.', 405);
    $key = trim((string) ($input['api_key'] ?? ''));
    if ($key === '') $security->fail('API key cannot be empty.');
    $store = $aiStore();
    if (!$store->exists('nvidia')) $store->ensureFromPreset('nvidia');
    $summary = $store->saveKey('nvidia', $key);
    // Keep the active selection pointing somewhere usable.
    if ($store->activeId() === null) $store->setActive('nvidia');
    $security->respond(['saved' => true, 'maskedKey' => $summary['maskedKey']]);
    break;

  case 'ai-nvidia-clear-key':
    if ($method !== 'POST') $security->fail('Use POST.', 405);
    $store = $aiResolveProvider('nvidia');
    if (!$store->exists('nvidia')) $store->ensureFromPreset('nvidia');
    $store->saveKey('nvidia', '');
    $security->respond(['cleared' => true]);
    break;

  case 'ai-nvidia-models':
    $store = $aiResolveProvider('nvidia');
    $refresh = !empty($_GET['refresh']);
    try {
      $models = $store->listModelsFor('nvidia', $refresh);
    } catch (\Throwable $e) {
      $security->fail($e->getMessage());
    }
    $security->respond(['models' => $models]);
    break;

  /* ── PROVIDER SWITCHING (v9: ANY configured/preset provider) ── */
  case 'ai-provider':
    if ($method !== 'POST') $security->fail('Use POST.', 405);
    $provider = trim((string) ($input['provider'] ?? ''));
    if ($provider === '') {
      // Legacy default when the client sends no body ('ollama' in v8).
      $store0 = $aiStore();
      $provider = (string) ($store0->activeId() ?? 'ollama');
    }
    $ai = new IdeAI($config, $security, $fs, $search, $terminal);
    $ai->setProvider($provider);   // fails with envelope error when unknown
    $security->respond(['provider' => $provider, 'active' => $provider]);
    break;

  case 'ai-provider-status':
    $store = $aiStore();
    $active = $store->activeId();
    $security->respond([
      'provider'  => $active,          // legacy key (pre-v9 clients read this)
      'active'    => $active,
      'providers' => $store->statuses(),   // network-free masked summaries
    ]);
    break;

  /* ── MULTI-PROVIDER MANAGEMENT (NEW v9) ─────────────────────── */
  case 'ai-providers-list':
    $store = $aiStore();
    $security->respond([
      'active'    => $store->activeId(),
      'presets'   => $store->presets(),
      'providers' => $store->summaries(),
    ]);
    break;

  case 'ai-providers-save':
    if ($method !== 'POST') $security->fail('Use POST.', 405);
    $store = $aiStore();
    try {
      $summary = $store->save(is_array($input) ? $input : []);
    } catch (\RuntimeException $e) {
      $security->fail($e->getMessage());
    }
    $security->respond($summary);
    break;

  case 'ai-providers-delete':
    if ($method !== 'POST') $security->fail('Use POST.', 405);
    $id = trim((string) ($input['id'] ?? ''));
    if ($id === '') $security->fail('Provider id is required.');
    $store = $aiStore();
    if (!$store->exists($id)) $security->fail('Unknown provider "' . $id . '".');
    $newActive = $store->delete($id);
    $security->respond(['deleted' => true, 'id' => $id, 'active' => $newActive]);
    break;

  case 'ai-provider-test':
    if ($method !== 'POST') $security->fail('Use POST.', 405);
    $id = trim((string) ($input['id'] ?? ''));
    if ($id === '') $security->fail('Provider id is required.');
    $store = $aiResolveProvider($id);
    try {
      $result = $store->test($id);   // persists testedAt/lastError on the instance
    } catch (\Throwable $e) {
      $security->fail($e->getMessage());
    }
    $security->respond($result);
    break;

  case 'ai-models':
    $store = $aiStore();
    $provider = trim((string) ($_GET['provider'] ?? ($input['provider'] ?? '')));
    $refresh = !empty($_GET['refresh']) || !empty($input['refresh']);
    if ($provider !== '') $aiResolveProvider($provider);
    try {
      $models = $store->listModelsFor($provider !== '' ? $provider : null, $refresh);
    } catch (\Throwable $e) {
      $security->fail($e->getMessage());
    }
    $security->respond([
      'provider' => $provider !== '' ? $provider : $store->activeId(),
      'models'   => $models,
    ]);
    break;

  /* ── STREAMING ENDPOINTS (SSE — these exit on their own) ────── */
  case 'ai-stream':
    if ($method !== 'POST') $security->fail('Use POST.', 405);
    if (empty($config['ai']['enabled'])) $security->fail('AI is disabled in config.php.', 403);
    $ai = new IdeAI($config, $security, $fs, $search, $terminal);
    $message = trim((string) ($input['message'] ?? ''));
    if ($message === '') $security->fail('Message cannot be empty.');
    $ai->chatStream($message);
    exit;

  case 'ai-approve-stream':
    if ($method !== 'POST') $security->fail('Use POST.', 405);
    if (empty($config['ai']['enabled'])) $security->fail('AI is disabled.', 403);
    $ai = new IdeAI($config, $security, $fs, $search, $terminal);
    $approvals = $input['approvals'] ?? [];
    if (!is_array($approvals)) $security->fail('Invalid approvals.');
    $ai->approveStream($approvals);
    exit;

  case 'ai-regenerate':
    if ($method !== 'POST') $security->fail('Use POST.', 405);
    if (empty($config['ai']['enabled'])) $security->fail('AI is disabled in config.php.', 403);
    $ai = new IdeAI($config, $security, $fs, $search, $terminal);
    $ai->regenerateStream();
    exit;

  case 'ai-regenerate-json':
    if ($method !== 'POST') $security->fail('Use POST.', 405);
    if (empty($config['ai']['enabled'])) $security->fail('AI is disabled in config.php.', 403);
    $ai = new IdeAI($config, $security, $fs, $search, $terminal);
    $security->respond($ai->regenerate());
    break;

  case 'ai-truncate':
    if ($method !== 'POST') $security->fail('Use POST.', 405);
    if (empty($config['ai']['enabled'])) $security->fail('AI is disabled in config.php.', 403);
    $ai = new IdeAI($config, $security, $fs, $search, $terminal);
    $mode = (string) ($input['mode'] ?? 'response');
    if (!in_array($mode, ['response', 'exchange'], true)) {
      $security->fail('Invalid mode. Use "response" or "exchange".');
    }
    $security->respond($ai->truncateHistory($mode));
    break;

  /* ── NVIDIA CHAT ENDPOINTS (force nvidia active, then normal flow) */
  case 'ai-nvidia-chat':
    if ($method !== 'POST') $security->fail('Use POST.', 405);
    if (empty($config['ai']['enabled'])) $security->fail('AI is disabled in config.php.', 403);
    $ai = new IdeAI($config, $security, $fs, $search, $terminal);
    $ai->setProvider('nvidia');
    $message = trim((string) ($input['message'] ?? ''));
    if ($message === '') $security->fail('Message cannot be empty.');
    $security->respond($ai->chat($message));
    break;

  case 'ai-nvidia-stream':
    if ($method !== 'POST') $security->fail('Use POST.', 405);
    if (empty($config['ai']['enabled'])) $security->fail('AI is disabled in config.php.', 403);
    $ai = new IdeAI($config, $security, $fs, $search, $terminal);
    $ai->setProvider('nvidia');
    $message = trim((string) ($input['message'] ?? ''));
    if ($message === '') $security->fail('Message cannot be empty.');
    $ai->chatStream($message);
    exit;
}
