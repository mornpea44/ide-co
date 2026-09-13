<?php

declare(strict_types=1);
/**
 * ═══════════════════════════════════════════════════════════════════════════
 *  QUIRKY IDE — AI ASSISTANT ENGINE  (v11 · deterministic weak-model engine)
 * ═══════════════════════════════════════════════════════════════════════════
 *
 *  DROP-IN REPLACEMENT for AI.php (v9/v10). Same class name (IdeAI), same
 *  constructor signature, same public methods, same JSON + SSE frame contract
 *  (delta / tool / pending / error / done). Copy over the existing AI.php next
 *  to AiProviders.php / AiToolExecutor.php / AiHistory.php.
 *
 *  ── WHAT WAS ACTUALLY BROKEN (traced through the v10 source) ──────────────
 *
 *   1. THE "CREATED" LIE (the reported production bug). The approval path was
 *      structurally sound, but success was derived from *result shape*, never
 *      from *filesystem effect*. An executor replying ['ok'=>true] (no
 *      success/size/path) was treated as a confirmed write, and a path
 *      resolved outside the visible workspace still printed "✅ Created".
 *      v10 even reconstructed the missing evidence itself:
 *        if (empty($result['path'])) $result['path'] = $args['path'];
 *      → v11 adds real post-conditions (verifyStateChange): a write is only
 *        "verified" with positive evidence (a success flag, or an
 *        executor-returned path + byte count). A zero-byte write of non-empty
 *        content is a hard failure. A path mismatch is surfaced. Unverified
 *        results can never complete a "create" intent nor be claimed as done.
 *
 *   2. APPROVALS COULD END UP ORPHANED. applyApprovals() appended tool
 *      messages without checking that the matching assistant tool_calls
 *      message still existed in history. After truncation / stale pending /
 *      an older session, the provider received a tool result with no
 *      preceding tool_calls → hard 400 → the turn died AFTER the file had
 *      been written, so the UI showed "Approved" plus a generic reply and the
 *      file never appeared in the transcript.
 *      → v11 re-attaches (or synthesises) the canonical assistant message
 *        before any tool result and pairs ids explicitly, then persists
 *        immediately so an interrupted run cannot lose an approved write.
 *
 *   3. AN EXTRA LLM CALL AFTER EVERY WRITE. Post-approval the loop always went
 *      back to the model (summarize mode) even when the tool result already
 *      settled the request. Weak models answered "Sure, I'm ready to help…"
 *      (exactly the screenshot symptom) or hallucinated success.
 *      → v11 settles deterministically: settleAfterTools() answers straight
 *        from verified results, for auto-executed AND approved calls.
 *
 *   4. NO TOOL GATE. Every turn advertised tools, so weak models explored:
 *      list_dir → read_file → search → read again → …
 *      → v11 decides at the application level whether the turn can need the
 *        workspace (needsTools()). Pure conversation runs with tools withheld
 *        and a shorter prompt. If the model still prints a tool call, we
 *        re-open tools ONCE (bounded, never a loop).
 *
 *   5. trimToFit() ORPHANED PAIRS. It spliced index 1 repeatedly with no
 *      knowledge of assistant/tool pairing, so a tool result could survive
 *      while its assistant tool_calls was deleted → invalid wire payload.
 *      (groupIntoClusters() existed but was never used by trimToFit.)
 *      → v11 trims whole clusters: oldest first, system + newest kept,
 *        assistant/tool pairs atomic. Old tool output becomes a compact
 *        digest instead of 800 raw characters.
 *
 *   6. SILENT DUPLICATE SUPPRESSION. splitValidCalls() dropped duplicate /
 *      redundant calls with `continue`; a model that repeated a call got no
 *      feedback, and failed ops could be retried forever.
 *      → v11 records ok/failed per fingerprint: successful duplicates are
 *        suppressed (and the turn ends with the verified summary instead of
 *        another round), a FAILED op gets exactly ONE retry, and the
 *        suppression reason is fed back as an explicit tool result.
 *
 *   7. REGENERATION DESTROYED THE PREVIOUS ANSWER. The slice was applied
 *      before knowing whether the new run would succeed, so a rate-limited or
 *      empty response replaced a perfectly good answer with an error.
 *      → v11 keeps the previous branch and restores it verbatim when the new
 *        run produces nothing usable; both transports share one slice + one
 *        sanitiser; pending is resolved first; per-run state is reset.
 *
 *   8. EMPTY / TRUNCATED RESPONSES WERE TERMINAL.
 *      → v11 distinguishes provider error (actionable, no retry), empty
 *        content (ONE cheap recovery re-ask with a trimmed context and an
 *        explicit "answer now" nudge, only when nothing has been executed
 *        yet), malformed call (ONE bounded repair round), rate limit (never
 *        loops).
 *
 *   9. HALLUCINATED SUCCESS WAS ONLY CHECKED IN SUMMARIZE MODE.
 *      → v11 checks EVERY final text turn against real tool evidence
 *        (claimsSuccessWithoutEvidence) and replaces fabricated claims with
 *        the verified report.
 *
 *  10. PATH VALIDATION WAS TOO CRUDE. Any path containing ".." was rejected,
 *      so "report..v2.txt" was unusable; mb_* was assumed present.
 *      → v11 validates per segment, keeps the same guarantees (no absolute
 *        paths, no traversal, workspace-relative only) and is unicode-safe
 *        with a graceful fallback when ext-mbstring is absent.
 *
 *  ── PRESERVED, UNCHANGED ─────────────────────────────────────────────────
 *  public entry points + signatures, permission modes and their semantics,
 *  provider/model switching, history persistence semantics, canonical tool
 *  representation (args always arrays, ids always present), the SSE event
 *  contract and both emitters (byte-identical), IdeSecurity / IdeFileSystem /
 *  workspace / terminal boundaries. No vendor branching was reintroduced:
 *  provider differences still stop at the adapter boundary.
 * ═══════════════════════════════════════════════════════════════════════════
 */
require_once __DIR__ . '/AiProviders.php';

class IdeAI
{
  /* ═══════════════════════════════════════════════════════════════
     CONFIG / COLLABORATORS
     ═══════════════════════════════════════════════════════════════ */
  private array $config;
  private IdeSecurity $security;
  private IdeFileSystem $fs;
  private IdeSearch $search;
  private ?IdeTerminal $terminal;
  private AiToolExecutor $toolExecutor;
  private AiHistory $history;
  private AiProviders $providers;

  private string $model;
  private float  $temperature;
  private float  $topP;
  private float  $repeatPenalty;
  private int    $numPredict;
  private int    $numCtx;
  private string $keepAlive;
  private string $permissionMode;
  private int    $maxHistory;
  private int    $maxFileChars;
  private bool   $stream;
  private int    $timeout;

  private const READ_TOOLS     = ['list_dir', 'read_file', 'search_workspace', 'get_file_info'];
  private const MUTATING_TOOLS = ['write_file', 'delete_file', 'move_file', 'create_folder', 'delete_folder'];

  /* ═══════════════════════════════════════════════════════════════
     PER-RUN STATE (reset by beginRun() at the top of every runLoop)
     ═══════════════════════════════════════════════════════════════ */
  private ?array $toolDefsCache = null;
  private ?array $toolNameCache = null;
  private array  $systemPromptCache = [];
  private array  $completedOps = [];      // fingerprint => ['ok'=>bool,'tries'=>int]
  private int    $rounds = 0;
  private int    $repairRounds = 0;
  private bool   $recoveredEmpty = false;  // one cheap recovery re-ask per run
  private bool   $reopenedTools = false;   // one tools-reopen per run
  private string $lastUserText = '';
  private ?bool  $wantsVerification = null;
  private ?string $userMentionedPath = null;

  private const MAX_ROUNDS        = 6;   // safety net, never the control mechanism
  private const MAX_RESCUED_CALLS = 2;   // bound for text-call rescue
  private const MAX_REPAIR_ROUNDS = 1;   // bounded malformed-call repair
  private const MAX_FAILED_RETRY  = 1;   // one retry per failed op, per run
  private const OLD_TOOL_KEEP     = 400; // digest size for non-latest tool output
  private const SUMMARY_MAX       = 6000;
  /** Prefixes produced by finishError()/friendlyError() — never real content. */
  private const ERROR_PREFIXES = [
    'rate limited by the provider',
    'provider authentication failed',
    'provider unreachable',
    'model unavailable',
    'filesystem refused the operation',
    'the provider rejected the request shape',
    'the assistant run failed unexpectedly',
    'no ai provider is configured',
  ];

  public function __construct(
    array $config,
    IdeSecurity $security,
    IdeFileSystem $fs,
    IdeSearch $search,
    ?IdeTerminal $terminal = null
  ) {
    $this->config   = $config;
    $this->security = $security;
    $this->fs       = $fs;
    $this->search   = $search;
    $this->terminal = $terminal;
    $ai = $config['ai'] ?? [];
    $this->model          = (string) ($ai['model'] ?? 'qwen2.5-coder:1.5b');
    $this->permissionMode = (string) ($ai['permission_mode'] ?? 'ask_destructive');
    $this->maxHistory     = max(2, (int) ($ai['max_history'] ?? 6));
    $this->temperature    = (float) ($ai['temperature'] ?? 0.2);
    $this->topP           = (float) ($ai['top_p'] ?? 0.9);
    $this->repeatPenalty  = (float) ($ai['repeat_penalty'] ?? 1.08);
    $this->numPredict     = max(64, (int) ($ai['num_predict'] ?? 1200));
    $this->numCtx         = max(1024, (int) ($ai['num_ctx'] ?? 4096));
    $this->keepAlive      = (string) ($ai['keep_alive'] ?? '10m');
    $this->maxFileChars   = max(200, (int) ($ai['max_file_chars'] ?? 4000));
    $this->stream         = !empty($ai['stream']);
    $this->timeout        = (int) ($ai['timeout'] ?? 0);
    require_once __DIR__ . '/AiToolExecutor.php';
    require_once __DIR__ . '/AiHistory.php';
    require_once __DIR__ . '/AiProviders.php';
    $this->history      = new AiHistory($security, $this->maxHistory);
    $this->providers    = new AiProviders($config);
    $this->toolExecutor = new AiToolExecutor($fs, $search, $terminal, $this->maxFileChars);
    $sessionPerm = $this->history->getSessionPerm();
    if ($sessionPerm !== '' && in_array($sessionPerm, ['always_ask', 'ask_destructive', 'auto_approve'], true)) {
      $this->permissionMode = $sessionPerm;
    }
    @ini_set('default_socket_timeout', '300');
  }

  /* ═══════════════════════════════════════════════════════════════
     PROVIDER HELPERS  (unchanged dispatch — zero vendor conditionals)
     ═══════════════════════════════════════════════════════════════ */
  public function activeProviderId(): ?string
  {
    return $this->providers->activeId();
  }

  private function adapter(): AiProvider
  {
    $a = $this->providers->makeActive();
    if ($a === null) {
      throw new \RuntimeException(
        'No AI provider is configured yet. Open the provider picker in the AI panel and add one.'
      );
    }
    return $a;
  }

  private function effectiveModel(): string
  {
    $pid = $this->providers->activeId();
    if ($pid === null) return '';
    $m = $this->providers->slotModel($pid);
    if ($m === '') $m = trim($this->history->getModelFor($pid));
    if ($m === '') {
      $curated = $this->providers->curatedFirst($pid);
      if ($curated !== null && !empty($curated['id'])) $m = (string) $curated['id'];
    }
    return $m !== '' ? $m : $this->model;
  }

  /* ═══════════════════════════════════════════════════════════════
     STATUS / MODEL / PROVIDER SWITCH  (unchanged semantics)
     ═══════════════════════════════════════════════════════════════ */
  public function getStatus(): array
  {
    $pid = $this->providers->activeId();
    $out = [
      'permissionMode' => $this->permissionMode,
      'stream'         => $this->stream,
      'configuredModel' => $this->model,
      'providers'      => $this->providers->summaries(),
      'active'         => $pid,
    ];
    if ($pid === null) {
      return $out + [
        'provider' => null,
        'configured' => false,
        'online' => false,
        'url' => '',
        'baseUrl' => '',
        'availableModels' => [],
        'activeModel' => '',
        'modelLoaded' => false,
        'hasKey' => false,
        'maskedKey' => '',
      ];
    }
    $ad = $this->providers->make($pid);
    if ($ad === null) {
      return $out + ['provider' => $pid, 'configured' => false, 'online' => false];
    }
    $st = $ad->status();
    $effective = $this->effectiveModel();

    $merged = [
      'provider'        => $pid,
      'configured'      => true,
      'label'           => $st['label'],
      'wire'            => $st['wire'],
      'preset'          => $st['preset'] ?? null,
      'online'          => $st['online'],
      'url'             => $st['baseUrl'],
      'baseUrl'         => $st['baseUrl'],
      'availableModels' => $st['availableModels'],
      'activeModel'     => $effective !== '' ? $effective : (string) $st['activeModel'],
      'modelLoaded'     => $st['modelLoaded'],
      'hasKey'          => $st['hasKey'],
      'maskedKey'       => $st['maskedKey'],
      'keyOptional'     => $st['keyOptional'] ?? false,
      'testedAt'        => $st['testedAt'] ?? null,
      'lastOk'          => $st['lastOk'] ?? null,
      'lastError'       => $st['lastError'] ?? null,
    ];
    if (($st['wire'] ?? '') === 'ollama') {
      $names = [];
      foreach ((array) $st['availableModels'] as $m) {
        if (is_string($m)) $names[] = $m;
        elseif (is_array($m)) $names[] = (string) ($m['id'] ?? '');
      }
      $eff = $merged['activeModel'];
      $loaded = in_array($eff, $names, true);
      if (!$loaded) {
        foreach ($names as $n) {
          if ($n !== '' && str_starts_with($n, $eff)) {
            $loaded = true;
            break;
          }
        }
      }
      $merged['modelLoaded'] = ((bool) $st['online']) && $eff !== '' && $loaded;
    }
    return $out + $merged;
  }

  public function setModel(string $model, string $providerHint = ''): void
  {
    $model = trim($model);
    if ($model === '') $this->security->fail('Model name cannot be empty.');
    $this->security->startSession();
    $target = $providerHint !== '' ? $providerHint : (string) ($this->providers->activeId() ?? '');
    if ($target === '') $this->security->fail('No AI provider configured yet.');
    if (!$this->providers->exists($target)) {
      if ($this->providers->isPreset($target)) {
        $this->providers->ensureFromPreset($target);
      } else {
        $this->security->fail('Unknown provider "' . $target . '".');
      }
    }
    $wire = (string) ($this->providers->wireOf($target) ?? '');
    if ($wire === 'ollama') {
      $avail = [];
      try {
        $ad = $this->providers->make($target);
        foreach ($ad ? $ad->listModels(true) : [] as $m) $avail[] = (string) ($m['id'] ?? '');
      } catch (\Throwable $e) {
        $this->security->fail('Cannot reach Ollama to verify the model. Is it running?');
      }
      $ok = in_array($model, $avail, true);
      if (!$ok) {
        foreach ($avail as $n) {
          if ($n !== '' && str_starts_with($n, $model)) {
            $ok = true;
            break;
          }
        }
      }
      if (!$ok) $this->security->fail('Model "' . $model . '" is not in Ollama. Pull it first: ollama pull ' . $model);
    }
    $this->providers->setModelFor($target, $model);
    $this->providers->setActive($target);
    $this->history->setModelFor($target, $model);
    $this->history->setProvider($target);
    $_SESSION['quirky_ai_provider'] = $target;
    $this->history->persistState();
  }

  public function setProvider(string $provider): void
  {
    $this->security->startSession();
    if (!$this->providers->exists($provider)) {
      if (!$this->providers->isPreset($provider)) {
        $this->security->fail('Unknown provider "' . $provider . '".');
      }
      $this->providers->ensureFromPreset($provider);
    }
    $this->providers->setActive($provider);
    $_SESSION['quirky_ai_provider'] = $provider;
    $this->history->setProvider($provider);
    $this->history->persistState();
  }

  public function setPermissionMode(string $mode): void
  {
    if (!in_array($mode, ['always_ask', 'ask_destructive', 'auto_approve'], true)) return;
    $this->permissionMode = $mode;
    $this->history->setSessionPerm($mode);
    $this->history->persistState();
  }

  /* ═══════════════════════════════════════════════════════════════
     PUBLIC ENTRY POINTS  (same signatures + persistence semantics)
     ═══════════════════════════════════════════════════════════════ */
  public function clearHistory(): void
  {
    $this->history->clearHistory();
  }

  public function getTranscriptState(): array
  {
    $provider = $this->history->getProvider();
    return [
      'provider' => $provider,
      'model'    => $this->history->getModelFor($provider),
      'perm'     => $this->history->getSessionPerm(),
      'history'  => array_values($this->history->getHistory()),
    ];
  }

  public function chat(string $userMessage): array
  {
    @set_time_limit($this->timeout > 0 ? $this->timeout + 30 : 300);
    $h = $this->history->getHistory();
    $this->history->resolveStalePending($h);
    $h[] = ['role' => 'user', 'content' => $userMessage];
    $em = new JsonEmitter();
    $res = $this->runLoop($h, $em, false);
    $this->history->saveHistory($h);
    $this->history->persistState();
    return $res;
  }

  public function chatStream(string $userMessage): void
  {
    $this->beginStream();
    $h = $this->history->getHistory();
    $this->history->resolveStalePending($h);
    $h[] = ['role' => 'user', 'content' => $userMessage];
    $em = new SseEmitter();
    try {
      $this->runLoop($h, $em, true);
    } catch (\Throwable $e) {
      $em->error($this->friendlyError($e));
    }
    $this->history->saveHistory($h);
    $this->history->persistState();
    $em->done();
  }

  public function approveToolCalls(array $approvals): array
  {
    @set_time_limit($this->timeout > 0 ? $this->timeout + 30 : 300);
    $h = $this->history->getHistory();
    $outcome = $this->applyApprovals($h, $approvals);
    if (!empty($outcome['unresolved'])) {
      // A malformed/partial frontend approval must never become an implicit
      // denial and must never fall through to another LLM call. Keep the card
      // pending so the user can approve it again.
      $this->history->saveHistory($h);
      $this->history->persistState();
      return [
        'type'  => 'tool_calls',
        'calls' => $outcome['unresolved'],
        'error' => 'No valid approval decision was received for every pending tool call.',
      ];
    }
    if (($outcome['processed'] ?? 0) === 0) {
      $msg = 'No pending tool call could be matched. Refresh the AI transcript and try the action again.';
      return ['type' => 'text', 'content' => $msg, 'error' => $msg];
    }
    $em = new JsonEmitter();
    $res = $this->runLoop($h, $em, false, false);
    $this->history->saveHistory($h);
    $this->history->persistState();
    return $res;
  }

  public function approveStream(array $approvals): void
  {
    $this->beginStream();
    $h = $this->history->getHistory();
    $em = new SseEmitter();
    $outcome = $this->applyApprovals($h, $approvals);
    if (!empty($outcome['unresolved'])) {
      $em->error('No valid approval decision was received for every pending tool call.');
      $em->pending($outcome['unresolved']);
    } elseif (($outcome['processed'] ?? 0) === 0) {
      $em->error('No pending tool call could be matched. Refresh the AI transcript and try the action again.');
    } else {
      try {
        $this->runLoop($h, $em, true, false);
      } catch (\Throwable $e) {
        $em->error($this->friendlyError($e));
      }
    }
    $this->history->saveHistory($h);
    $this->history->persistState();
    $em->done();
  }

  public function regenerateStream(): void
  {
    $this->beginStream();
    $em = new SseEmitter();
    $h = $this->history->getHistory();
    $sliced = $this->sliceForRegeneration($h);
    if ($sliced === null) {
      $em->error('Nothing to regenerate yet — send a message first.');
      $em->done();
      return;
    }
    [$h, $backup] = $sliced;
    $this->history->clearPending();
    try {
      // Regeneration is a new run. Completed-op state from any previous method
      // call must not leak in; preserved tool results are settled from history.
      $this->runLoop($h, $em, true);
    } catch (\Throwable $e) {
      $em->error($this->friendlyError($e));
    }
    // A failed/empty regeneration must never destroy the previous answer.
    $h = $this->restoreBranchIfEmpty($h, $backup);
    $this->history->saveHistory($h);
    $this->history->persistState();
    $em->done();
  }

  public function regenerate(): array
  {
    @set_time_limit($this->timeout > 0 ? $this->timeout + 30 : 300);
    $h = $this->history->getHistory();
    $sliced = $this->sliceForRegeneration($h);
    if ($sliced === null) {
      return ['type' => 'text', 'content' => 'Nothing to regenerate yet — send a message first.'];
    }
    [$h, $backup] = $sliced;
    $this->history->clearPending();
    $em  = new JsonEmitter();
    $res = $this->runLoop($h, $em, false);
    if (($res['type'] ?? '') === 'text' && trim((string) ($res['content'] ?? '')) === '') {
      $res['content'] = '(the model returned no text — try rephrasing, or pick a larger model)';
    }
    if (($res['type'] ?? '') === 'text') {
      $h = $this->restoreBranchIfEmpty($h, $backup);
    }
    $this->history->saveHistory($h);
    $this->history->persistState();
    return $res;
  }

  public function truncateHistory(string $mode): array
  {
    return $this->history->truncateHistory($mode);
  }

  /* ═══════════════════════════════════════════════════════════════
     REGENERATION — one shared, sanitized slice for both transports.
     The branch being replaced is returned as $backup so a failed
     regeneration can restore it (v11: an answer is never lost).
     ═══════════════════════════════════════════════════════════════ */
  /**
   * @return array{0:array,1:array}|null  [sliced history, backup branch]
   */
  private function sliceForRegeneration(array $h): ?array
  {
    $lastUserIdx = $this->lastUserIndex($h);
    if ($lastUserIdx === -1) return null;
    $backup = array_slice($h, $lastUserIdx + 1);

    // If the previous branch contains completed tools, preserve the canonical
    // assistant/tool sequence and remove only prose generated after the final
    // result. Regenerating text must never replay write/delete/move/run side
    // effects that already happened. runLoop() will settle from those real
    // results immediately, with no provider call.
    $lastToolIdx = -1;
    $hasUnanswered = false;
    $open = [];
    for ($i = $lastUserIdx + 1; $i < count($h); $i++) {
      $role = $h[$i]['role'] ?? '';
      if ($role === 'assistant' && !empty($h[$i]['tool_calls'])) {
        foreach ((array) $h[$i]['tool_calls'] as $tc) {
          $id = (string) ($tc['id'] ?? '');
          if ($id !== '') $open[$id] = true;
        }
      } elseif ($role === 'tool') {
        $lastToolIdx = $i;
        unset($open[(string) ($h[$i]['tool_call_id'] ?? '')]);
      }
    }
    $hasUnanswered = !empty($open);
    if ($lastToolIdx > $lastUserIdx && !$hasUnanswered) {
      $sliced = array_slice($h, 0, $lastToolIdx + 1);
      return [$this->sanitizeTail($sliced), $backup];
    }

    // Pure text, malformed tails and unresolved approval branches restart at
    // the last user request. ClearPending() is performed by the caller.
    $sliced = array_slice($h, 0, $lastUserIdx + 1);
    return [$this->sanitizeTail($sliced), $backup];
  }

  /**
   * Guarantee: no trailing assistant tool_calls without matching tool
   * results. Missing results get explicit "cancelled" errors so provider
   * wires never see an orphaned call (fixes regeneration + stale pending).
   */
  private function sanitizeTail(array $h): array
  {
    $callIds = [];
    for ($i = count($h) - 1; $i >= 0; $i--) {
      $role = $h[$i]['role'] ?? '';
      if ($role === 'user') break;
      if ($role === 'assistant' && !empty($h[$i]['tool_calls'])) {
        foreach ($h[$i]['tool_calls'] as $tc) {
          $cid = (string) ($tc['id'] ?? '');
          if ($cid !== '') $callIds[$cid] = true;
        }
      }
    }
    if (empty($callIds)) return $h;
    $answered = [];
    for ($i = count($h) - 1; $i >= 0; $i--) {
      $role = $h[$i]['role'] ?? '';
      if ($role === 'user') break;
      if ($role === 'assistant') continue;
      if ($role === 'tool') {
        $answered[(string) ($h[$i]['tool_call_id'] ?? '')] = true;
      }
    }
    foreach (array_keys($callIds) as $cid) {
      if (empty($answered[$cid])) {
        $h[] = [
          'role'         => 'tool',
          'tool_call_id' => $cid,
          'name'         => '',
          'content'      => json_encode(['error' => 'Cancelled: superseded by a newer request.'], JSON_UNESCAPED_SLASHES),
        ];
      }
    }
    return $h;
  }

  /**
   * v11: put back the branch that was regenerated when the new run produced
   * nothing usable (provider error / empty reply). The user's previous answer
   * is never silently destroyed.
   */
  private function restoreBranchIfEmpty(array $h, array $backup): array
  {
    if (empty($backup)) return $h;
    $cut = $this->lastUserIndex($h);
    if ($cut === -1) return $h;
    for ($i = $cut + 1; $i < count($h); $i++) {
      $m = $h[$i];
      $role = $m['role'] ?? '';
      if ($role !== 'assistant') continue;
      if (!empty($m['tool_calls'])) return $h;
      $text = trim((string) ($m['content'] ?? ''));
      if ($text !== '' && !$this->isProviderErrorText($text)) return $h;
    }
    return array_merge(array_slice($h, 0, $cut + 1), $backup);
  }

  /* ═══════════════════════════════════════════════════════════════
     APPROVALS  (dual-key mapping + re-attachment + verified execution)
     ═══════════════════════════════════════════════════════════════ */
  /**
   * v11 hardening of the approval pipeline:
   *   • accepts approvals keyed by 'id' OR 'tool_call_id' (v10's silent deny),
   *   • falls back to positional approval when the client echoes no ids,
   *   • preserves the EXACT stored arguments (never re-derived),
   *   • guarantees an assistant tool_calls message exists before appending
   *     any tool result (previously: orphaned result → provider 400 →
   *     "Approved" with no visible outcome),
   *   • executes through safeExecute()+verifyToolResult() so a report of
   *     success is always backed by real filesystem evidence,
   *   • persists immediately so an interrupted run cannot lose a write.
   */
  /**
   * @return array{processed:int,approved:int,denied:int,unresolved:array}
   */
  private function applyApprovals(array &$h, array $approvals): array
  {
    // Session pending state can disappear on mobile when the approval request
    // is routed through a fresh PHP session/cookie. Reconstruct only truly open
    // calls from canonical history instead of silently asking the model again.
    $pending = $this->pendingCallsForApproval($h);
    if (empty($pending)) {
      return ['processed' => 0, 'approved' => 0, 'denied' => 0, 'unresolved' => []];
    }

    $map = $this->normalizeApprovalDecisions($approvals, $pending);
    $openIds = $this->openToolCallIds($h);
    $syntheticIndex = null;
    $unresolved = [];
    $processed = 0;
    $approved = 0;
    $denied = 0;

    foreach ($pending as $call) {
      if (!is_array($call)) continue;
      $id   = (string) ($call['id'] ?? '');
      $tcid = (string) ($call['tool_call_id'] ?? $call['id'] ?? '');
      if ($tcid === '') $tcid = 'call_repaired_' . $this->randId();
      $name = strtolower(trim((string) ($call['name'] ?? '')));
      $args = $call['arguments'] ?? [];
      $decision = array_key_exists($id, $map) ? $map[$id]
        : (array_key_exists($tcid, $map) ? $map[$tcid] : null);

      // Missing/ambiguous input is NOT a denial. Keep the approval pending so
      // the UI can retry with the correct id instead of losing the operation.
      if ($decision === null) {
        $unresolved[] = $call;
        continue;
      }

      if ($decision === true && $name !== '') {
        $v = $this->validateToolCall($name, $args);
        $result = $v['ok']
          ? $this->verifyToolResult($name, $v['args'], $this->safeExecute($name, $v['args']))
          : ['success' => false, 'error' => 'Invalid tool arguments for "'
            . ($name !== '' ? $name : '?') . '": ' . $v['error']];
        $this->recordCompletedOp($name, $v['ok'] ? $v['args'] : (is_array($args) ? $args : []), $result);
        $approved++;
      } else {
        $result = ['success' => false, 'error' => 'Denied by user.', 'denied' => true];
        $denied++;
      }
      $processed++;

      // Re-attach a missing owner before the result. This repairs a pending
      // card surviving longer than its assistant message without producing an
      // invalid OpenAI/NVIDIA tool-result sequence.
      if ($tcid !== '' && !isset($openIds[$tcid])) {
        $tcMsg = [
          'id'       => $tcid,
          'type'     => 'function',
          'function' => ['name' => $name, 'arguments' => is_array($args) ? $args : []],
        ];
        if ($syntheticIndex === null) {
          $h[] = ['role' => 'assistant', 'content' => '', 'tool_calls' => [$tcMsg]];
          $syntheticIndex = count($h) - 1;
        } else {
          $h[$syntheticIndex]['tool_calls'][] = $tcMsg;
        }
        $openIds[$tcid] = true;
      }
      $h[] = [
        'role'         => 'tool',
        'tool_call_id' => $tcid,
        'name'         => $name,
        'content'      => json_encode($result, JSON_UNESCAPED_SLASHES),
      ];
    }

    $this->history->setPending($unresolved);
    if ($processed > 0) $this->persistPartial($h);
    return compact('processed', 'approved', 'denied', 'unresolved');
  }

  /** Recover unresolved canonical calls when session pending state was lost. */
  private function pendingCallsForApproval(array $h): array
  {
    $stored = $this->history->getPending();
    if (!empty($stored)) return $stored;

    for ($i = count($h) - 1; $i >= 0; $i--) {
      if (($h[$i]['role'] ?? '') === 'user') break;
      if (($h[$i]['role'] ?? '') !== 'assistant' || empty($h[$i]['tool_calls'])) continue;

      // Scope results to THIS owning assistant branch. Provider call ids are
      // not guaranteed globally unique; an old `call_0` must never suppress a
      // current pending `call_0` from a later user turn.
      $answered = [];
      for ($j = $i + 1; $j < count($h); $j++) {
        if (($h[$j]['role'] ?? '') === 'user') break;
        if (($h[$j]['role'] ?? '') === 'tool') {
          $answered[(string) ($h[$j]['tool_call_id'] ?? '')] = true;
        }
      }
      $round = 0;
      $branchStart = 0;
      for ($k = $i - 1; $k >= 0; $k--) {
        if (($h[$k]['role'] ?? '') === 'user') { $branchStart = $k + 1; break; }
      }
      for ($k = $branchStart; $k < $i; $k++) {
        if (($h[$k]['role'] ?? '') === 'assistant') $round++;
      }
      $out = [];
      foreach ((array) $h[$i]['tool_calls'] as $n => $tc) {
        if (!is_array($tc)) continue;
        $tcid = (string) ($tc['id'] ?? '');
        if ($tcid === '' || isset($answered[$tcid])) continue;
        $fn = is_array($tc['function'] ?? null) ? $tc['function'] : [];
        $name = strtolower(trim((string) ($fn['name'] ?? '')));
        if ($name === '') continue;
        $out[] = [
          'id'           => 'tc_' . $round . '_' . $n,
          'tool_call_id' => $tcid,
          'name'         => $name,
          'arguments'    => $this->repairJsonArgs($fn['arguments'] ?? []),
        ];
      }
      return $out;
    }
    return [];
  }

  /**
   * Accept the common frontend envelopes without treating arbitrary truthy
   * strings as approval. Supports a single object, a list, or id=>decision.
   *
   * @return array<string,bool>
   */
  private function normalizeApprovalDecisions(array $input, array $pending): array
  {
    $map = [];
    $records = array_is_list($input) ? $input : [$input];
    if (!array_is_list($input)
      && !isset($input['id']) && !isset($input['tool_call_id'])
      && !isset($input['approved']) && !isset($input['decision'])) {
      $records = [];
      foreach ($input as $id => $value) {
        $records[] = ['id' => (string) $id, 'decision' => $value];
      }
    }

    $positional = [];
    foreach ($records as $record) {
      if (is_bool($record) || is_int($record) || is_string($record)) {
        $positional[] = $this->approvalBool($record);
        continue;
      }
      if (!is_array($record)) continue;
      $decision = null;
      foreach (['approved', 'decision', 'status', 'action', 'allow'] as $key) {
        if (array_key_exists($key, $record)) {
          $decision = $this->approvalBool($record[$key]);
          break;
        }
      }
      $id = (string) ($record['id'] ?? '');
      $tcid = (string) ($record['tool_call_id'] ?? '');
      if ($decision !== null && $id !== '') $map[$id] = $decision;
      if ($decision !== null && $tcid !== '') $map[$tcid] = $decision;
      if ($id === '' && $tcid === '') $positional[] = $decision;
    }

    // Positional decisions are safe only when the count exactly matches the
    // unresolved calls. This prevents a malformed object from auto-approving.
    if (count($positional) === count($pending)) {
      foreach ($pending as $i => $call) {
        $decision = $positional[$i] ?? null;
        if ($decision === null) continue;
        $map[(string) ($call['id'] ?? '')] = $decision;
        $map[(string) ($call['tool_call_id'] ?? '')] = $decision;
      }
    }
    return $map;
  }

  private function approvalBool($value): ?bool
  {
    if (is_bool($value)) return $value;
    if (is_int($value)) return $value === 1 ? true : ($value === 0 ? false : null);
    if (!is_string($value)) return null;
    $v = strtolower(trim($value));
    if (in_array($v, ['1', 'true', 'yes', 'approve', 'approved', 'allow', 'allowed'], true)) return true;
    if (in_array($v, ['0', 'false', 'no', 'deny', 'denied', 'reject', 'rejected'], true)) return false;
    return null;
  }

  /**
   * tool_call_ids advertised by an assistant message that have no tool result.
   * Scanned FORWARD so the pairing is order-accurate: a result that follows
   * its call closes it, while a result with no preceding call cannot be
   * re-attached and is left to repairToolCallIds().
   */
  private function openToolCallIds(array $h): array
  {
    $open = [];
    foreach ($h as $m) {
      $role = $m['role'] ?? '';
      if ($role === 'assistant' && !empty($m['tool_calls'])) {
        foreach ($m['tool_calls'] as $tc) {
          $cid = (string) ($tc['id'] ?? '');
          if ($cid !== '') $open[$cid] = true;
        }
      } elseif ($role === 'tool') {
        unset($open[(string) ($m['tool_call_id'] ?? '')]);
      }
    }
    return $open;
  }

  /* ═══════════════════════════════════════════════════════════════
     THE LOOP — orchestrator only; every decision is a named helper.
     External contract is unchanged.
     ═══════════════════════════════════════════════════════════════ */
  private function runLoop(array &$h, Emitter $em, bool $stream, bool $clearOps = true): array
  {
    $this->beginRun($h, $clearOps);

    // ── POST-APPROVAL / POST-EXECUTION SETTLE ──
    // Entering with a tool result as the last message means tools just ran
    // (auto-executed or freshly approved). If the verified results already
    // answer the request — or report a failure — answer NOW, deterministically,
    // with zero model calls. This is what removes the wasted "summarize" round
    // that let weak models answer "Sure, I'm ready to help…" after a write.
    if (($h[count($h) - 1]['role'] ?? '') === 'tool') {
      $settled = $this->settleAfterTools($h);
      if ($settled !== null) {
        if (!$stream) $em->text($settled);
        else $em->delta($settled);
        $h[] = ['role' => 'assistant', 'content' => $settled];
        return $stream ? ['type' => 'streamed'] : ['type' => 'text', 'content' => $settled];
      }
    }

    for ($round = 0; $round < self::MAX_ROUNDS; $round++) {
      $this->rounds = $round;
      $lastRole = !empty($h) ? ($h[count($h) - 1]['role'] ?? '') : '';
      $summarizeMode = ($lastRole === 'tool');

      // ── Tool gate: does this turn need the workspace at all? ──
      // Pure conversation is answered with tools withheld, which removes the
      // entire class of speculative list_dir/read_file from weak models.
      // After a tool result, withhold tools unless this is a genuinely
      // incomplete multi-step mutation (e.g. edit: read_file → write_file).
      // Completed/unknown tasks enter final-answer mode, preventing weak-model
      // verify loops and destructive replays during regeneration.
      $withTools = !$summarizeMode || $this->mayNeedMoreToolsAfterResults($h);
      if (!$summarizeMode && !$this->reopenedTools && !$this->needsTools($this->lastUserText)) {
        $withTools = false;
      }

      try {
        $resp = $this->askModelResilient($h, $stream, $em, $withTools);
      } catch (\Throwable $e) {
        // Provider failure: one actionable error, history-safe, never a loop.
        return $this->finishError($h, $em, $stream, $this->friendlyError($e));
      }

      $toolCalls = $resp['tool_calls'] ?? [];
      $content   = (string) ($resp['content'] ?? '');
      $streamed  = (bool) ($resp['streamed'] ?? false);

      // The model reached for tools while they were withheld → re-open once.
      if (!$withTools && empty($toolCalls) && $this->looksLikePrintedCall($content) && !$this->reopenedTools) {
        $this->reopenedTools = true;
        continue;
      }

      // ── TEXT TOOL-CALL RESCUE (bounded, validated, approval-preserving) ──
      if (empty($toolCalls) && trim($content) !== '' && $this->looksLikePrintedCall($content)) {
        $resc = $this->rescueToolCalls($content);
        if (!empty($resc['calls'])) {
          foreach ($resc['calls'] as $rc) {
            $toolCalls[] = [
              'id'       => 'call_' . $this->randId(),
              'type'     => 'function',
              'function' => ['name' => $rc['name'], 'arguments' => $rc['arguments']],
            ];
          }
          $content = $resc['clean'];
        }
      }

      // ── VALIDATE + FILTER ──
      [$validCalls, $invalidCalls, $suppressed] = $this->splitValidCalls($toolCalls);

      if (empty($validCalls) && !empty($invalidCalls)) {
        // All calls malformed: ONE bounded repair round with explicit errors.
        $h[] = ['role' => 'assistant', 'content' => $content, 'tool_calls' => $toolCalls];
        foreach ($invalidCalls as $bad) {
          $h[] = [
            'role'         => 'tool',
            'tool_call_id' => (string) ($bad['id'] ?? ''),
            'name'         => (string) ($bad['name'] ?? ''),
            'content'      => json_encode(['error' => $bad['error']], JSON_UNESCAPED_SLASHES),
          ];
        }
        $this->repairRounds++;
        if ($this->repairRounds > self::MAX_REPAIR_ROUNDS) {
          return $this->finishSummary($h, $em, $stream, $streamed);
        }
        continue;
      }

      $toolCalls = $validCalls;

      // Everything was a duplicate / redundant repeat: the work is already
      // done, so answer without another model round. A substantive model
      // explanation still wins, but a fabricated success claim is replaced by
      // the verified report.
      if (empty($toolCalls) && !empty($suppressed)) {
        $substantive = trim($content) !== ''
          && !$this->isGenericResponse($content)
          && !$this->claimsSuccessWithoutEvidence($content, $h);
        if ($substantive) {
          if (!$stream || !$streamed) $em->text($content);
          $h[] = ['role' => 'assistant', 'content' => $content];
          return $stream ? ['type' => 'streamed'] : ['type' => 'text', 'content' => $content];
        }
        return $this->finishSummary($h, $em, $stream, $streamed, $content);
      }

      // ── WEAK-MODEL RECOVERY AFTER TOOLS ──
      if (empty($toolCalls) && $summarizeMode) {
        if (trim($content) === '' || $this->isGenericResponse($content)
          || $this->claimsSuccessWithoutEvidence($content, $h)) {
          return $this->finishSummary($h, $em, $stream, $streamed);
        }
      }

      if (empty($toolCalls)) {
        if (!$stream || !$streamed) $em->text($content);
        $h[] = ['role' => 'assistant', 'content' => $content];
        return $stream ? ['type' => 'streamed'] : ['type' => 'text', 'content' => $content];
      }

      $h[] = ['role' => 'assistant', 'content' => $content, 'tool_calls' => $toolCalls];

      // ── ROUTE: auto-execute read-only calls, queue the rest for approval ──
      [$auto, $pending] = $this->routeCalls($toolCalls, $round);
      $this->executeAutoCalls($auto, $h, $em);

      if (!empty($pending)) {
        // Persist the owning assistant tool_calls message BEFORE the pending
        // record. The approval arrives as a separate request (often without a
        // usable session cookie), so both must already be durable.
        $this->persistPartial($h);
        $this->history->setPending($pending);
        $em->pending($pending);
        return $stream ? ['type' => 'streamed'] : ['type' => 'tool_calls', 'calls' => $pending];
      }

      // ── DETERMINISTIC SETTLE ──
      // If the verified tool results already answer the request (or report a
      // failure) we finish WITHOUT another model call. This is the single
      // biggest latency + weak-model win: a completed write cannot be fumbled.
      $short = $this->settleAfterTools($h);
      if ($short !== null) {
        if (!$stream) $em->text($short);
        else $em->delta($short);
        $h[] = ['role' => 'assistant', 'content' => $short];
        return $stream ? ['type' => 'streamed'] : ['type' => 'text', 'content' => $short];
      }
    }

    // Safety net only — normal turns end far earlier.
    $h = $this->sanitizeTail($h);
    return $this->finishSummary($h, $em, $stream, false, '⚠ I hit the maximum number of tool steps. Here is where I stopped.');
  }

  /**
   * @param bool $clearOps false when the run continues an in-flight turn
   *   (approvals/regeneration): ops recorded by applyApprovals() must survive
   *   so an approved write can never be executed twice.
   */
  private function beginRun(array $h, bool $clearOps = true): void
  {
    if ($clearOps) {
      $this->completedOps = [];
    }
    $this->rounds         = 0;
    $this->repairRounds   = 0;
    $this->recoveredEmpty = false;
    $this->reopenedTools  = false;
    $this->wantsVerification = null;
    $this->userMentionedPath = null;
    $this->lastUserText   = $this->extractLastUserText($h);
  }

  /** True when a message is one of our own provider-error reports. */
  private function isProviderErrorText(string $text): bool
  {
    $t = $this->lc($text);
    if ($t === '') return true;
    foreach (self::ERROR_PREFIXES as $p) {
      if (str_starts_with($t, $p)) return true;
    }
    return false;
  }

  /** Single place that emits + records a provider/agent error. */
  private function finishError(array &$h, Emitter $em, bool $stream, string $msg): array
  {
    $em->error($msg);
    $h[] = ['role' => 'assistant', 'content' => $msg];
    return $stream ? ['type' => 'streamed'] : ['type' => 'text', 'content' => $msg];
  }

  /** Emit + record a verified summary built from real tool results. */
  private function finishSummary(array &$h, Emitter $em, bool $stream, bool $streamed, string $prefix = ''): array
  {
    $summary = $this->buildToolSummary($h);
    if ($prefix !== '') {
      $summary = trim($prefix . "\n\n" . $summary);
    }
    if ($stream && $streamed) {
      $em->delta("\n\n" . $summary);
    } else {
      $em->text($summary);
    }
    $h[] = ['role' => 'assistant', 'content' => $summary];
    return $stream ? ['type' => 'streamed'] : ['type' => 'text', 'content' => $summary];
  }

  /**
   * Execute the calls that need no approval, verify each result, append the
   * canonical tool messages and persist immediately so an approved/auto write
   * can never be lost or applied twice.
   */
  private function executeAutoCalls(array $auto, array &$h, Emitter $em): void
  {
    foreach ($auto as $tc) {
      $name = (string) ($tc['function']['name'] ?? '');
      $args = $tc['function']['arguments'] ?? [];
      if (!is_array($args)) $args = [];
      $em->toolProgress($name, $args);
      $result = $this->verifyToolResult($name, $args, $this->safeExecute($name, $args));
      $this->recordCompletedOp($name, $args, $result);
      $h[] = [
        'role'         => 'tool',
        'tool_call_id' => (string) ($tc['id'] ?? ''),
        'name'         => $name,
        'content'      => json_encode($result, JSON_UNESCAPED_SLASHES),
      ];
    }
    if (!empty($auto)) $this->persistPartial($h);
  }

  /** Split calls into [auto-executable, needs-approval]. */
  private function routeCalls(array $toolCalls, int $round): array
  {
    $auto = [];
    $pending = [];
    foreach ($toolCalls as $i => $tc) {
      $name = (string) ($tc['function']['name'] ?? '');
      if ($this->needsApproval($name)) {
        $pending[] = [
          'id'           => 'tc_' . $round . '_' . $i,
          'tool_call_id' => (string) ($tc['id'] ?? ''),
          'name'         => $name,
          'arguments'    => $tc['function']['arguments'] ?? [],
        ];
      } else {
        $auto[] = $tc;
      }
    }
    return [$auto, $pending];
  }

  /* ═══════════════════════════════════════════════════════════════
     TOOL GATE — decide in the application whether tools can help.
     Uncertainty always resolves to "allow tools" (never blocks work).
     ═══════════════════════════════════════════════════════════════ */
  private function needsTools(string $text): bool
  {
    $t = $this->lc(trim($text));
    if ($t === '') return false;
    if ($this->ulen($t) > 600) return true;         // long messages are usually task work

    // 1) Any file/path reference → tools are relevant.
    if (preg_match('/\.[a-z0-9]{1,8}\b/', $t)) return true;
    if (preg_match('#(^|\s)[\w.-]+/[\w.-]+#', $t)) return true;
    if (strpos($t, 'workspace') !== false) return true;

    // 2) Workspace verbs and nouns.
    $verbs = 'create|write|save|make|new|add|delete|remove|rm|trash|rename|move|mv|cp|copy|read|open|show|list|ls|'
      . 'find|search|grep|locate|run|execute|install|fix|edit|update|modify|change|patch|refactor|build|compile|'
      . 'test|deploy|folder|directory|file|files|dir|code|terminal|command|script|commit|push';
    if (preg_match('~\b(' . $verbs . ')\b~', $t)) return true;

    // 3) Explicit small-talk / meta questions → answer directly, no tools.
    if (preg_match('/^\s*(hi|hey|hello|yo|thanks|thank you|thx|ty|ok|okay|cool|nice|got it|bye)\b[\s!.]*$/i', $t)) return false;
    if (preg_match('/\b(who are you|what can you do|what are you|your name|what do you do)\b/', $t)) return false;

    // 4) Conceptual questions with no workspace anchor.
    if (preg_match('/^\s*(what is|what are|who is|explain|define|tell me about|difference between|how does)\b/', $t)
      && !preg_match('/\b(my|this|our|the)\s+(code|file|project|repo|workspace|function|class)\b/', $t)) {
      return false;
    }
    return true;
  }

  /* ═══════════════════════════════════════════════════════════════
     TOOL-CALL VALIDATION + DEDUP + REDUNDANCY
     ═══════════════════════════════════════════════════════════════ */
  /** Names the model is actually allowed to call (terminal-gated). */
  private function knownToolNames(): array
  {
    if ($this->toolNameCache !== null) return $this->toolNameCache;
    $names = [];
    foreach ($this->toolDefs() as $d) {
      $n = (string) ($d['function']['name'] ?? '');
      if ($n !== '') $names[] = strtolower($n);
    }
    $this->toolNameCache = $names;
    return $names;
  }

  /**
   * Validate one tool call. Returns ['ok'=>bool,'args'=>array,'error'=>string].
   * On success 'args' is the NORMALIZED form (clean paths, coerced strings).
   * Never throws for model input; only known tools with sound args pass.
   */
  private function validateToolCall(string $name, $args): array
  {
    $name = strtolower(trim($name));
    if ($name === '' || !in_array($name, $this->knownToolNames(), true)) {
      return ['ok' => false, 'args' => [], 'error' => 'unknown tool "' . ($name !== '' ? $name : '?') . '"'];
    }
    $args = $this->repairJsonArgs($args);
    $need = [
      'list_dir' => ['path'], 'read_file' => ['path'],
      'write_file' => ['path', 'content'], 'delete_file' => ['path'],
      'move_file' => ['from', 'to'], 'create_folder' => ['path'],
      'delete_folder' => ['path'], 'search_workspace' => ['query'],
      'run_command' => ['command'],
    ];
    $required = $need[$name] ?? [];
    foreach ($required as $k) {
      if (!array_key_exists($k, $args)) {
        return ['ok' => false, 'args' => [], 'error' => 'missing "' . $k . '"'];
      }
      if (is_array($args[$k])) {
        // Weak models sometimes wrap strings in a one-element array.
        $flat = $this->flattenScalar($args[$k]);
        if ($flat === null) {
          return ['ok' => false, 'args' => [], 'error' => '"' . $k . '" must be a string'];
        }
        $args[$k] = $flat;
      }
      if (!is_string($args[$k]) && !is_numeric($args[$k]) && !is_bool($args[$k])) {
        return ['ok' => false, 'args' => [], 'error' => '"' . $k . '" must be a string'];
      }
      $args[$k] = (string) $args[$k];
    }
    try {
      foreach (['path', 'from', 'to'] as $k) {
        if (array_key_exists($k, $args)) {
          $args[$k] = $this->normalizePathArg((string) $args[$k], $name === 'list_dir' && $k === 'path');
        }
      }
    } catch (\RuntimeException $e) {
      return ['ok' => false, 'args' => [], 'error' => $e->getMessage()];
    }
    if ($name === 'search_workspace' && trim($args['query']) === '') {
      return ['ok' => false, 'args' => [], 'error' => 'empty "query"'];
    }
    if ($name === 'run_command') {
      if ($this->terminal === null || !$this->terminal->isEnabled()) {
        return ['ok' => false, 'args' => [], 'error' => 'terminal is disabled'];
      }
      if (trim($args['command']) === '') {
        return ['ok' => false, 'args' => [], 'error' => 'empty "command"'];
      }
    }
    if (($name === 'move_file') && $args['from'] === $args['to']) {
      return ['ok' => false, 'args' => [], 'error' => '"from" and "to" are identical'];
    }
    return ['ok' => true, 'args' => $args, 'error' => ''];
  }

  /** Collapse ['a'] / [['a']] style nesting into a plain string. */
  private function flattenScalar($v, int $depth = 0): ?string
  {
    if ($depth > 3) return null;
    if (is_string($v)) return $v;
    if (is_numeric($v) || is_bool($v)) return (string) $v;
    if (is_array($v) && count($v) === 1) {
      $first = reset($v);
      return $this->flattenScalar($first, $depth + 1);
    }
    return null;
  }

  /**
   * Repair model-produced arguments into an array. Handles arrays, stdClass,
   * JSON strings, fenced strings, single-quoted JSON, trailing commas,
   * unquoted keys and python-style True/False/None.
   * Unparseable input becomes [] (caller turns it into an explicit error).
   */
  private function repairJsonArgs($args): array
  {
    if (is_array($args)) return $args;
    if ($args instanceof \stdClass) return (array) $args;
    if (!is_string($args)) return [];
    $s = trim($args);
    if ($s === '') return [];
    $s = (string) preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $s);
    $s = trim($s);
    if ($s === '' || ($s[0] !== '{' && $s[0] !== '[')) return [];
    $d = json_decode($s, true);
    if (is_array($d)) return $this->unwrapArgs($d);
    // Progressive repair for weak models; each step is independent so a
    // partially broken payload still decodes.
    $noTrailing = (string) preg_replace('/,\s*([}\]])/', '$1', $s);
    $steps = [
      $noTrailing,
      (string) preg_replace('/([{,]\s*)([A-Za-z_][A-Za-z0-9_\-]*)\s*:/', '$1"$2":', $noTrailing),
      str_replace(["'"], ['"'], $noTrailing),
      str_replace(['True', 'False', 'None'], ['true', 'false', 'null'],
        (string) preg_replace('/([{,]\s*)([A-Za-z_][A-Za-z0-9_\-]*)\s*:/', '$1"$2":', $noTrailing)),
    ];
    foreach ($steps as $candidate) {
      $d = json_decode($candidate, true);
      if (is_array($d)) return $this->unwrapArgs($d);
    }
    return [];
  }

  /** Unwrap {"arguments":{…}} / {"parameters":{…}} / {"function":{…}} shapes. */
  private function unwrapArgs(array $d): array
  {
    if (isset($d['function']['arguments']) && is_array($d['function']['arguments'])) {
      return $d['function']['arguments'];
    }
    if (isset($d['arguments']) && is_array($d['arguments'])) return $d['arguments'];
    if (isset($d['parameters']) && is_array($d['parameters'])) return $d['parameters'];
    return $d;
  }

  /**
   * Clean a workspace-relative path. Throws RuntimeException on unsafe input.
   * list_dir("") (workspace root) is the only allowed empty path.
   * v11: traversal is detected per segment, so names like "report..v2.txt"
   * remain valid while "../../etc/passwd" is still rejected.
   */
  private function normalizePathArg(string $path, bool $allowEmpty = false): string
  {
    $p = trim(str_replace('\\', '/', $path));
    if ($p === '' || $p === '.' || $p === './') {
      if ($allowEmpty) return '';
      throw new \RuntimeException('empty path (a file path is required)');
    }
    $p = (string) preg_replace('#^(\./)+#', '', $p);
    $p = (string) preg_replace('#^workspace/#i', '', $p);
    $p = ltrim($p, '/');
    $p = (string) preg_replace('#/+#', '/', $p);
    if ($p === '' || $p === '.' || strpos($p, "\0") !== false) {
      if ($allowEmpty && $p === '') return '';
      throw new \RuntimeException('empty path (a file path is required)');
    }
    foreach (explode('/', $p) as $seg) {
      if ($seg === '..') {
        throw new \RuntimeException('unsafe path "' . $this->sub($path, 0, 80) . '" (stays inside the workspace)');
      }
    }
    return $p;
  }

  /**
   * Order/whitespace/case-insensitive fingerprint; long content is hashed so
   * equivalent writes dedupe without a compare cost.
   */
  private function fingerprintCall(string $name, array $args): string
  {
    $norm = [];
    foreach ($args as $k => $v) {
      if ($k === 'content' && is_string($v) && $this->ulen($v) > 200) {
        $norm[$k] = 'len:' . $this->ulen($v) . ':sha1:' . sha1($v);
      } elseif (is_string($v)) {
        $norm[$k] = strtolower(trim($v));
      } elseif (is_array($v)) {
        $norm[$k] = sha1(json_encode($v, JSON_UNESCAPED_SLASHES));
      } else {
        $norm[$k] = $v;
      }
    }
    ksort($norm);
    return strtolower($name) . ':' . json_encode($norm, JSON_UNESCAPED_SLASHES);
  }

  private function recordCompletedOp(string $name, array $args, array $result): void
  {
    $fp = $this->fingerprintCall($name, $args);
    $failed = !empty($result['error'])
      || (array_key_exists('success', $result) && empty($result['success']))
      || (array_key_exists('verified', $result) && empty($result['verified'])
        && in_array(strtolower($name), self::MUTATING_TOOLS, true));
    $prev = $this->completedOps[$fp] ?? ['tries' => 0];
    $this->completedOps[$fp] = [
      'name'  => strtolower($name),
      'args'  => $args,
      'fp'    => $fp,
      'ok'    => !$failed,
      'tries' => ($prev['tries'] ?? 0) + 1,
    ];
  }

  /**
   * Split raw calls into [valid, invalid, suppressedReasons].
   * Validation failures become explicit feedback for the model; exact
   * duplicates and redundant ops are suppressed at the application level so
   * the model cannot loop, while a genuinely failed op still gets ONE retry.
   */
  private function splitValidCalls(array $raw): array
  {
    $valid = [];
    $invalid = [];
    $suppressed = [];
    $seenBatch = [];   // fingerprints offered in THIS batch (before execution)
    foreach ((array) $raw as $tc) {
      $fn = (is_array($tc) && isset($tc['function']) && is_array($tc['function'])) ? $tc['function'] : [];
      $name = (string) ($fn['name'] ?? '');
      $id = (string) ((is_array($tc) && isset($tc['id'])) ? $tc['id'] : '');
      if ($id === '') $id = 'call_' . $this->randId();
      $v = $this->validateToolCall($name, $fn['arguments'] ?? []);
      if (!$v['ok']) {
        $invalid[] = ['id' => $id, 'name' => $name, 'error' => 'Invalid ' . ($name !== '' ? '"' . $name . '"' : 'tool call') . ': ' . $v['error'] . '.'];
        continue;
      }
      $lname = strtolower(trim($name));
      $fp = $this->fingerprintCall($lname, $v['args']);
      if (isset($seenBatch[$fp])) {
        // Two identical calls in one turn: the second can never add anything.
        $suppressed[] = 'duplicate call in the same turn (executed once)';
        continue;
      }
      $seenBatch[$fp] = true;
      $prior = $this->completedOps[$fp] ?? null;

      if ($prior !== null && !empty($prior['ok'])) {
        // Identical op already succeeded in this run — never repeat it.
        $suppressed[] = $this->suppressReason($lname, $v['args']) ?? 'already completed in this run';
        continue;
      }
      if ($prior !== null && empty($prior['ok']) && ($prior['tries'] ?? 0) >= self::MAX_FAILED_RETRY) {
        $invalid[] = ['id' => $id, 'name' => $lname, 'error' => 'The same call already failed once in this turn; it will not be retried automatically.'];
        continue;
      }
      $reason = $this->isRedundantOp($lname, $v['args']);
      if ($reason !== null) {
        $suppressed[] = $reason;
        continue;
      }
      $valid[] = [
        'id'       => $id,
        'type'     => 'function',
        'function' => ['name' => $lname, 'arguments' => $v['args']],
      ];
    }
    return [$valid, $invalid, $suppressed];
  }

  /** Human-readable reason why an identical successful op is not repeated. */
  private function suppressReason(string $name, array $args): ?string
  {
    $pathOf = function (array $a): string {
      foreach (['path', 'to', 'from'] as $k) {
        if (isset($a[$k]) && is_string($a[$k])) return $a[$k];
      }
      return '';
    };
    $path = $pathOf($args);
    if ($path !== '') return '"' . $name . ' ' . $path . '" already completed in this turn';
    if ($name === 'search_workspace') return 'the same search already ran in this turn';
    return '"' . $name . '" already completed in this turn';
  }

  /**
   * Redundancy rules over ops already completed in THIS run. Returns a reason
   * string or null. Suppression lifts when the user explicitly asked to
   * verify ("verify", "check", "confirm", "show me", "double-check").
   */
  private function isRedundantOp(string $name, array $args): ?string
  {
    if (empty($this->completedOps)) return null;
    $pathOf = function (array $a): string {
      foreach (['path', 'to', 'from'] as $k) {
        if (isset($a[$k]) && is_string($a[$k])) return $a[$k];
      }
      return '';
    };
    $wantsVerify = $this->userWantsVerification();
    $path = $pathOf($args);
    $query = strtolower(trim((string) ($args['query'] ?? '')));

    foreach ($this->completedOps as $op) {
      if (empty($op['ok'])) continue;   // failed ops may legitimately retry
      $on = $op['name'];
      $opath = $pathOf($op['args']);

      // Re-read of an unchanged file.
      if ($name === 'read_file' && $on === 'read_file' && $path !== '' && $path === $opath) {
        return 'file already read (unchanged)';
      }
      // Re-list of the same directory.
      if ($name === 'list_dir' && $on === 'list_dir' && $path === $opath) {
        return 'directory already listed';
      }
      // Same search twice.
      if ($name === 'search_workspace' && $on === 'search_workspace' && $query !== ''
        && $query === strtolower(trim((string) ($op['args']['query'] ?? '')))) {
        return 'search already ran';
      }
      // Verify-after-write: reading/listing right after a successful write.
      if (!$wantsVerify && $on === 'write_file' && ($name === 'read_file' || $name === 'list_dir')
        && ($name !== 'read_file' || ($path !== '' && $path === $opath))) {
        return 'write already confirmed — no verify pass needed';
      }
      // A task that is already complete: nothing further is required.
      if (!$wantsVerify && $this->isCompleteForIntent($on, $opath)) {
        return 'requested task is already complete';
      }
    }
    return null;
  }

  /** Did the user explicitly ask for verification this turn? */
  private function userWantsVerification(): bool
  {
    if ($this->wantsVerification !== null) return $this->wantsVerification;
    $u = $this->lc($this->lastUserText);
    $this->wantsVerification = false;
    if ($u !== '') {
      foreach (['verify', 'verifying', 'double-check', 'double check', 'confirm', 'check that', 'show me', 'prove'] as $w) {
        if (strpos($u, $w) !== false) {
          $this->wantsVerification = true;
          break;
        }
      }
    }
    return $this->wantsVerification;
  }

  /** Is the mutation the user asked for already done? (used to stop loops) */
  private function isCompleteForIntent(string $doneTool, string $donePath): bool
  {
    $intent = $this->taskIntent($this->lastUserText);
    if ($donePath === '') return false;
    $asked = $this->pathFromUserText();
    if ($asked !== '' && $asked !== $donePath) return false;
    switch ($intent) {
      case 'create':
      case 'edit':
        return $doneTool === 'write_file';
      case 'delete':
        return $doneTool === 'delete_file' || $doneTool === 'delete_folder';
      case 'move':
        return $doneTool === 'move_file';
      case 'mkdir':
        return $doneTool === 'create_folder';
    }
    return false;
  }

  /** Best-effort path mentioned by the user (used for completion checks). */
  private function pathFromUserText(): string
  {
    if ($this->userMentionedPath !== null) return $this->userMentionedPath;
    $this->userMentionedPath = '';
    if (preg_match('/([A-Za-z0-9._\-\/]+\.[A-Za-z0-9]{1,8})/', $this->lastUserText, $m)) {
      try {
        $this->userMentionedPath = $this->normalizePathArg($m[1]);
      } catch (\Throwable $e) {
        $this->userMentionedPath = '';
      }
    }
    return $this->userMentionedPath;
  }

  /* ═══════════════════════════════════════════════════════════════
     SAFE EXECUTION + REAL POST-CONDITIONS (no fake success, ever)
     ═══════════════════════════════════════════════════════════════ */
  private function safeExecute(string $name, array $args): array
  {
    try {
      $r = $this->toolExecutor->execute($name, $args);
      if (!is_array($r)) {
        return ['error' => 'Tool "' . $name . '" returned an unusable result; the operation was NOT confirmed.'];
      }
      return $r;
    } catch (\Throwable $e) {
      return ['error' => $this->friendlyError($e)];
    }
  }

  /**
   * A successful result must positively confirm success. v11 adds real
   * post-conditions for state-changing tools (verifyStateChange) so "✅
   * Created" can only ever be printed when the executor actually reports a
   * completed write — and a zero-byte write of non-empty content is an error.
   */
  private function verifyToolResult(string $name, array $args, $result): array
  {
    if (!is_array($result)) {
      return ['error' => 'Tool "' . $name . '" returned an unusable result; the operation was NOT confirmed.'];
    }
    if (!empty($result['error'])) return $result;
    if (array_key_exists('success', $result) && empty($result['success'])) {
      if (empty($result['error'])) $result['error'] = 'Tool "' . $name . '" reported failure.';
      $result['verified'] = false;
      return $result;
    }
    if (in_array(strtolower($name), self::MUTATING_TOOLS, true)) {
      return $this->verifyStateChange(strtolower($name), $args, $result);
    }
    return $result;
  }

  /**
   * Post-conditions for mutating tools. Never invents evidence: when the
   * executor provides none, the result is marked unverified and the summary
   * layer reports "not confirmed" instead of success.
   */
  private function verifyStateChange(string $name, array $args, array $r): array
  {
    // Explicit denial from the security/permission layer.
    if (!empty($r['denied']) || !empty($r['deniedByUser'])) {
      $r['error'] = $r['error'] ?? 'Operation was not permitted.';
      $r['verified'] = false;
      return $r;
    }

    $argPath = (string) ($args['path'] ?? $args['to'] ?? '');
    $resPath = '';
    foreach (['path', 'created', 'deleted', 'to', 'written'] as $k) {
      if (!empty($r[$k]) && is_string($r[$k])) {
        $resPath = (string) $r[$k];
        break;
      }
    }
    if ($resPath !== '' && $argPath !== '') {
      try {
        if ($this->normalizePathArg($resPath) !== $this->normalizePathArg($argPath)) {
          // The executor resolved the request somewhere else: say so.
          $r['warning'] = 'executor reported "' . $resPath . '" instead of "' . $argPath . '"';
          $r['verified'] = false;
          return $r;
        }
      } catch (\Throwable $e) {
        $r['warning'] = 'executor returned an unusable path: ' . $this->sub($resPath, 0, 80);
        $r['verified'] = false;
        return $r;
      }
    }
    if ($resPath !== '') $r['path'] = $resPath;
    elseif ($argPath !== '') $r['path'] = $argPath;
    if (!empty($args['from']) && empty($r['from'])) $r['from'] = (string) $args['from'];

    // Positive evidence hunt: the executor must positively say "this happened".
    // A bare generic flag such as ['ok'=>true] is deliberately NOT enough —
    // that is exactly the shape that used to be reported as "Created" while
    // nothing reached the disk.
    $evidence = null;
    foreach (['success', 'written', 'created', 'deleted', 'moved'] as $k) {
      if (array_key_exists($k, $r)) {
        $evidence = !empty($r[$k]);
        break;
      }
    }
    $weakOk = $evidence === null && array_key_exists('ok', $r);
    $bytes = null;
    foreach (['bytes', 'size'] as $k) {
      if (isset($r[$k]) && is_numeric($r[$k])) {
        $bytes = (int) $r[$k];
        break;
      }
    }
    // Only what the EXECUTOR reports counts as evidence. The path we asked for
    // is a request, not a confirmation, so it is deliberately excluded here.
    $described = ($resPath !== '' || $bytes !== null);

    if ($evidence === false) {
      $r['error'] = $r['error'] ?? ('Tool "' . $name . '" reported failure.');
      $r['verified'] = false;
      return $r;
    }
    if ($evidence === true || $described) {
      $r['verified'] = true;
      if ($weakOk) {
        $r['evidence'] = 'generic';
      }
    } else {
      // Nothing proves the mutation happened.
      if ($weakOk) {
        $r['warning'] = 'executor returned only a generic flag with no target or size';
      }
      $r['verified'] = false;
      $r['unverified'] = true;
      return $r;
    }
    if ($name === 'write_file' && $bytes === 0 && isset($args['content']) && trim((string) $args['content']) !== '') {
      $r['error'] = 'write_file reported 0 bytes for non-empty content; the file was NOT written.';
      $r['verified'] = false;
      return $r;
    }
    $r['verified'] = true;
    return $r;
  }

  /* ═══════════════════════════════════════════════════════════════
     TASK COMPLETION — answer from real results, skip redundant LLM calls
     ═══════════════════════════════════════════════════════════════ */
  private function extractLastUserText(array $h): string
  {
    $i = $this->lastUserIndex($h);
    return $i === -1 ? '' : (string) ($h[$i]['content'] ?? '');
  }

  private function lastUserIndex(array $h): int
  {
    for ($i = count($h) - 1; $i >= 0; $i--) {
      if (($h[$i]['role'] ?? '') === 'user') return $i;
    }
    return -1;
  }

  /**
   * Tool results since the last user message, oldest → newest. Decoded once
   * and shared by the completion check, the hallucination guard and the
   * summary builder (v10 decoded the same payloads up to three times).
   */
  private function toolResultsSinceLastUser(array $h, array $filter = []): array
  {
    $items = [];
    for ($i = count($h) - 1; $i >= 0; $i--) {
      $role = $h[$i]['role'] ?? '';
      if ($role === 'user') break;
      if ($role !== 'tool') continue;
      $name = strtolower((string) ($h[$i]['name'] ?? ''));
      if ($filter !== [] && !in_array($name, $filter, true)) continue;
      $decoded = json_decode((string) ($h[$i]['content'] ?? ''), true);
      $items[] = [
        'name'   => $name,
        'tcid'   => (string) ($h[$i]['tool_call_id'] ?? ''),
        'result' => is_array($decoded) ? $decoded : [],
      ];
    }
    return array_reverse($items);
  }

  /** Coarse intent of the last user message for completion detection. */
  private function taskIntent(string $text): string
  {
    $t = $this->lc($text);
    if (preg_match('/\b(create|write|save|overwrite|make|new file|add a file)\b/', $t)) return 'create';
    if (preg_match('/\b(edit|update|modify|change|fix|patch|rewrite)\b/', $t)
      && preg_match('/\b(file|code|function|bug|error)\b/', $t)) return 'edit';
    if (preg_match('/\b(delete|remove|trash)\b/', $t)) return 'delete';
    if (preg_match('/\b(move|rename)\b/', $t)) return 'move';
    if (preg_match('/\b(folder|directory|mkdir)\b/', $t) && preg_match('/\b(create|make|new)\b/', $t)) return 'mkdir';
    if (preg_match('/\b(list|show|what|explore|browse)\b/', $t)
      && preg_match('/\b(files|file|workspace|folder|directory|project)\b/', $t)) return 'list';
    if (preg_match('/\b(read|open|show|inspect|look at|view|display|print)\b/', $t)
      && preg_match('/\b(file|content|code)\b|\.[a-z0-9]{1,8}\b/', $t)) return 'read';
    if (preg_match('/\b(search|find|grep|locate|where)\b/', $t)) return 'search';
    if (preg_match('/\b(run|execute|command|install|test|build|npm|composer|php)\b/', $t)) return 'run';
    return 'other';
  }

  /**
   * When the verified tool results since the last user message already settle
   * the request (or report errors), return the summary so the loop finishes
   * WITHOUT another model call. Returns null when more work may be needed.
   */
  private function settleAfterTools(array $h): ?string
  {
    $user = $this->extractLastUserText($h);
    if ($user === '') return null;
    $this->lastUserText = $user;

    $items = $this->toolResultsSinceLastUser($h);
    if (empty($items)) return null;

    // Errors settle the turn immediately: never let a weak model spin or
    // whitewash a real failure.
    foreach ($items as $it) {
      $r = $it['result'];
      if (!empty($r['error']) || (array_key_exists('success', $r) && empty($r['success']))) {
        return $this->buildToolSummary($h);
      }
    }

    $names = [];
    foreach ($items as $it) $names[] = $it['name'];
    $last = $names === [] ? '' : $names[count($names) - 1];
    $has = function (string $n) use ($names): bool {
      return in_array($n, $names, true);
    };

    $done = false;
    switch ($this->taskIntent($user)) {
      case 'create':
        // A write only counts when the filesystem confirmed it.
        $done = ($last === 'write_file' && $this->confirmed($items));
        break;
      case 'read':
        $done = ($last === 'read_file');
        break;
      case 'list':
        $done = ($last === 'list_dir');
        break;
      case 'delete':
        $done = (($last === 'delete_file' || $last === 'delete_folder') && $this->confirmed($items));
        break;
      case 'search':
        $done = ($last === 'search_workspace');
        break;
      case 'edit':
        $done = ($last === 'write_file' && $this->confirmed($items) && ($has('read_file') || $this->reopenedTools));
        break;
      case 'move':
        $done = ($last === 'move_file' && $this->confirmed($items));
        break;
      case 'mkdir':
        $done = ($last === 'create_folder' && $this->confirmed($items));
        break;
      case 'run':
        $done = ($last === 'run_command');
        break;
    }
    // Weak phrasing such as “put Hello into test.txt” may not hit the coarse
    // create/edit regex, but a verified mutation is still definitive evidence
    // that the requested side effect completed. Settle instead of asking the
    // model to interpret (or repeat) it.
    if (!$done && $this->taskIntent($user) === 'other'
      && in_array($last, self::MUTATING_TOOLS, true)
      && $this->confirmed($items)) {
      $done = true;
    }
    return $done ? $this->buildToolSummary($h) : null;
  }

  /**
   * Only true for a known multi-step intent whose decisive tool has not run.
   * This keeps tools available for read→write edits while completed reads,
   * listings, searches and unknown turns enter final-answer mode.
   */
  private function mayNeedMoreToolsAfterResults(array $h): bool
  {
    $items = $this->toolResultsSinceLastUser($h);
    if (empty($items)) return false;
    $names = array_column($items, 'name');
    $intent = $this->taskIntent($this->extractLastUserText($h));
    return match ($intent) {
      'edit'   => !in_array('write_file', $names, true),
      'create' => !in_array('write_file', $names, true),
      'delete' => !in_array('delete_file', $names, true)
        && !in_array('delete_folder', $names, true),
      'move'   => !in_array('move_file', $names, true),
      'mkdir'  => !in_array('create_folder', $names, true),
      'run'    => !in_array('run_command', $names, true),
      default  => false,
    };
  }

  /** True when every mutating result in the set carries verified evidence. */
  private function confirmed(array $items): bool
  {
    foreach ($items as $it) {
      if (in_array($it['name'], self::MUTATING_TOOLS, true) && empty($it['result']['verified'])) {
        return false;
      }
    }
    return true;
  }

  private function isGenericResponse(string $content): bool
  {
    $c = $this->lc(trim($content));
    if ($c === '') return true;
    if ($this->ulen($c) < 120) {
      $genericPatterns = [
        'i\'m ready', 'ready to assist', 'ready to help', 'let me know',
        'how can i help', 'what would you like', 'i\'m here to help',
        'just let me know', 'what can i do', 'ready when you are',
        'feel free to ask', 'what do you want me', 'how may i assist',
        'i\'m standing by', 'happy to help', 'what do you need',
        'please let me know', 'tell me what', 'what shall i',
        'i can help you with', 'here to assist', 'sure, i\'m ready',
        'what would you like to work on',
      ];
      foreach ($genericPatterns as $p) {
        if (strpos($c, $p) !== false) return true;
      }
    }
    return false;
  }

  /**
   * Hallucinated-success guard, applied to EVERY final text turn (v10 only
   * checked summarize mode). If the model claims a mutation that no verified
   * tool result supports, the verified summary must win.
   */
  private function claimsSuccessWithoutEvidence(string $content, array $h): bool
  {
    $c = $this->lc(trim($content));
    if ($c === '') return false;
    foreach (['error', 'fail', 'denied', 'couldn', 'unable', 'not found', 'sorry', 'cannot', 'can\'t'] as $w) {
      if (strpos($c, $w) !== false) return false;   // model acknowledged trouble
    }
    $claims = false;
    foreach (['created', 'success', 'successfully', 'deleted', 'written', 'saved', 'wrote', 'renamed', 'moved', 'complete', 'finished'] as $w) {
      if (strpos($c, $w) !== false) {
        $claims = true;
        break;
      }
    }
    if (!$claims) return false;

    $items = $this->toolResultsSinceLastUser($h, self::MUTATING_TOOLS);
    if (empty($items)) return true;                 // claims without any mutation
    foreach ($items as $it) {
      $r = $it['result'];
      if (!empty($r['error']) || (array_key_exists('success', $r) && empty($r['success']))) {
        return true;                                // claims over a failure
      }
      if (empty($r['verified'])) return true;       // claims without evidence
    }
    return false;
  }

  /* ═══════════════════════════════════════════════════════════════
     TEXT TOOL-CALL RESCUE — bounded, validated, approval-preserving
     ═══════════════════════════════════════════════════════════════ */
  /** Cheap pre-check so the regexes run only when markers are present. */
  private function looksLikePrintedCall(string $content): bool
  {
    if (strpos($content, '<tool_call') !== false) return true;
    if (strpos($content, '```') !== false && strpos($content, '"name"') !== false) return true;
    if (strpos($content, '<function') !== false && strpos($content, '<parameter') !== false) return true;
    if (preg_match('/"name"\s*:\s*"(list_dir|read_file|write_file|delete_file|move_file|create_folder|delete_folder|search_workspace|run_command)"/i', $content)) return true;
    return false;
  }

  private function rescueToolCalls(string $content): array
  {
    $known = $this->knownToolNames();
    $calls = [];
    $clean = $content;

    // A) XML-ish <tool_call>…<function=name>…<parameter=k>v</parameter>… blocks
    if (strpos($clean, '<tool_call') !== false && count($calls) < self::MAX_RESCUED_CALLS) {
      $clean = (string) preg_replace_callback('/<tool_call>.*?<\/tool_call>/is', function ($m) use (&$calls, $known) {
        if (count($calls) >= self::MAX_RESCUED_CALLS) return $m[0];
        $block = $m[0];
        if (!preg_match('/<function\s*=\s*([a-zA-Z0-9_-]+)\s*>/i', $block, $fm)
          && !preg_match('/<function\s+name\s*=\s*["\']?([a-zA-Z0-9_-]+)["\']?\s*>/i', $block, $fm)) {
          return $block;
        }
        $name = strtolower($fm[1]);
        if (!in_array($name, $known, true)) return $block;
        $args = [];
        if (preg_match_all('/<parameter\s*=\s*([a-zA-Z0-9_-]+)\s*>(.*?)<\/parameter>/is', $block, $pm)) {
          foreach ($pm[1] as $i => $k) $args[strtolower($k)] = trim($pm[2][$i]);
        }
        // Only strip when the rescued call VALIDATES; junk stays text.
        $v = $this->validateToolCall($name, $args);
        if (!$v['ok']) return $block;
        $calls[] = ['name' => $name, 'arguments' => $v['args']];
        return '';
      }, $clean);
    }

    // B) <tool_call> JSON </tool_call>
    if (strpos($clean, '<tool_call') !== false && count($calls) < self::MAX_RESCUED_CALLS) {
      $clean = (string) preg_replace_callback('/<tool_call>(.*?)<\/tool_call>/is', function ($m) use (&$calls, $known) {
        if (count($calls) >= self::MAX_RESCUED_CALLS) return $m[0];
        $inner = trim($m[1] ?? '');
        if ($inner === '' || ($inner[0] !== '{' && $inner[0] !== '[')) return $m[0];
        $d = json_decode($inner, true);
        if (is_array($d)) {
          if ($this->acceptRescued($d, $calls, $known)) return '';
          return $m[0];
        }
        // Broken JSON inside the tags gets the repair path too.
        $repaired = $this->repairJsonArgs($inner);
        if ($repaired !== [] && $this->acceptRescued($repaired, $calls, $known)) return '';
        return $m[0];
      }, $clean);
    }

    // C) fenced JSON blocks naming a known tool
    if (strpos($clean, '```') !== false && count($calls) < self::MAX_RESCUED_CALLS) {
      $clean = (string) preg_replace_callback('/```(?:json)?\s*([\s\S]*?)\s*```/i', function ($m) use (&$calls, $known) {
        if (count($calls) >= self::MAX_RESCUED_CALLS) return $m[0];
        $inner = trim($m[1] ?? '');
        if ($inner === '' || $inner[0] !== '{') return $m[0];
        $d = json_decode($inner, true);
        if (!is_array($d)) return $m[0];
        if (!isset($d['name']) && !isset($d['function']['name'])) return $m[0];
        if ($this->acceptRescued($d, $calls, $known)) return '';
        return $m[0];
      }, $clean);
    }

    if (empty($calls)) return ['calls' => [], 'clean' => $content];
    $clean = trim((string) preg_replace("/[ \t]*\n[ \t]*\n[ \t]*\n+/", "\n\n", $clean));
    return ['calls' => array_slice($calls, 0, self::MAX_RESCUED_CALLS), 'clean' => $clean];
  }

  /** Validate + accept one decoded rescue candidate. Never bypasses approval. */
  private function acceptRescued(array $d, array &$calls, array $known): bool
  {
    $name = '';
    $args = [];
    if (isset($d['function']['name'])) {
      $name = (string) $d['function']['name'];
      $args = $d['function']['arguments'] ?? [];
    } elseif (isset($d['name'])) {
      $name = (string) $d['name'];
      $args = $d['arguments'] ?? ($d['parameters'] ?? []);
    }
    $name = strtolower(trim($name));
    if ($name === '' || !in_array($name, $known, true)) return false;
    $v = $this->validateToolCall($name, $args);
    if (!$v['ok']) return false;
    $calls[] = ['name' => $name, 'arguments' => $v['args']];
    return true;
  }

  private function needsApproval(string $tool): bool
  {
    if ($this->permissionMode === 'auto_approve') return false;
    if ($this->permissionMode === 'always_ask') return true;
    return !in_array(strtolower($tool), self::READ_TOOLS, true);
  }

  /* ═══════════════════════════════════════════════════════════════
     ASK THE MODEL — provider-neutral dispatch + bounded recovery
     ═══════════════════════════════════════════════════════════════ */
  private function askModelResilient(array &$h, bool $stream, Emitter $em, bool $withTools): array
  {
    $resp = $this->askModel($h, $stream, $em, $withTools);
    $content = trim((string) ($resp['content'] ?? ''));
    $calls   = $resp['tool_calls'] ?? [];

    // Empty / truncated output: ONE targeted recovery, only when nothing has
    // been executed yet (so we can never double-apply a destructive op) and
    // never more than once per run.
    if ($content === '' && empty($calls) && !$this->recoveredEmpty) {
      $executed = !empty($this->completedOps);
      $pendingOpen = !empty($this->history->getPending());
      if (!$executed && !$pendingOpen) {
        $this->recoveredEmpty = true;
        $h = $this->dropOldestCluster($h);
        $resp = $this->askModel($h, $stream, $em, $withTools, true);
      }
    }
    return $resp;
  }

  private function askModel(array &$h, bool $stream, Emitter $em, bool $withTools = true, bool $nudge = false): array
  {
    $ad = $this->adapter();
    $messages = $this->buildMessages($h, $withTools, $nudge);
    $tools = $withTools ? $this->toolDefs() : [];
    $model = $this->effectiveModel();
    if ($model === '') {
      throw new \RuntimeException(
        '[' . $ad->label() . '] No model selected yet. Pick one in the AI panel header.'
      );
    }
    if (!$stream) {
      $r = $ad->chat($messages, $model, $tools);
      return [
        'content'    => (string) ($r['content'] ?? ''),
        'tool_calls' => $this->normalizeTools($r['tool_calls'] ?? []),
      ];
    }
    $content = '';
    $sawDelta = false;
    $toolCallsFinal = [];
    $ad->chatStream(
      $messages,
      $model,
      $tools,
      function (string $text) use (&$content, &$sawDelta, $em) {
        if ($text === '') return;
        $content .= $text;
        $sawDelta = true;
        $em->delta($text);
      },
      function (array $toolCalls) use (&$toolCallsFinal) {
        $toolCallsFinal = $toolCalls;
      }
    );
    return [
      'content'    => $content,
      'tool_calls' => $this->normalizeTools($toolCallsFinal),
      'streamed'   => $sawDelta,
    ];
  }

  /* ═══════════════════════════════════════════════════════════════
     ACTIONABLE ERRORS — specific, secret-safe, non-looping
     ═══════════════════════════════════════════════════════════════ */
  private function friendlyError(\Throwable $e): string
  {
    $raw = $e->getMessage();
    $msg = (string) preg_replace('/(api[_-]?key|bearer|token)\s*[:=]\s*\S+/i', '$1: [redacted]', $raw);
    $low = $this->lc($msg);
    if (strpos($low, '429') !== false || strpos($low, 'rate limit') !== false || strpos($low, 'quota') !== false || strpos($low, 'too many requests') !== false) {
      return 'Rate limited by the provider (free tiers throttle aggressively). Wait a few seconds, then tap ↻ Regenerate — or switch model/provider. No tools were executed for this failed call.';
    }
    if (strpos($low, '401') !== false || strpos($low, '403') !== false || strpos($low, 'unauthorized') !== false || strpos($low, 'invalid api key') !== false || strpos($low, 'authentication') !== false) {
      return 'Provider authentication failed. Check the API key for the active provider in the provider picker.';
    }
    if (strpos($low, 'permission denied') !== false || strpos($low, 'failed to open stream') !== false || strpos($low, 'is not writable') !== false) {
      return 'Filesystem refused the operation: ' . $this->shorten($msg, 240) . ' Check that workspace/ is writable by the PHP process.';
    }
    if (strpos($low, 'model') !== false && (strpos($low, 'not found') !== false || strpos($low, '404') !== false || strpos($low, 'does not exist') !== false || strpos($low, 'unknown') !== false)) {
      return 'Model unavailable: ' . $this->shorten($msg, 220);
    }
    if (strpos($low, 'could not connect') !== false || strpos($low, 'connection') !== false || strpos($low, 'timed out') !== false || strpos($low, 'timeout') !== false || strpos($low, 'unreachable') !== false || strpos($low, 'failed to connect') !== false || strpos($low, 'curl') !== false) {
      return 'Provider unreachable (' . $this->shorten($msg, 180) . '). For Ollama: is it running? For cloud: check network / base URL.';
    }
    if (strpos($low, 'malformed') !== false || strpos($low, 'invalid tool') !== false || strpos($low, 'invalid_request') !== false) {
      return 'The provider rejected the request shape: ' . $this->shorten($msg, 240);
    }
    if (strpos($low, 'no ai provider') !== false || strpos($low, 'no model selected') !== false) {
      return $msg;
    }
    return $this->shorten($msg !== '' ? $msg : 'The assistant run failed unexpectedly.', 300);
  }

  private function shorten(string $s, int $max): string
  {
    $s = trim((string) preg_replace('/\s+/', ' ', $s));
    if ($this->ulen($s) <= $max) return $s;
    return $this->sub($s, 0, max(0, $max - 1)) . '…';
  }

  /* ═══════════════════════════════════════════════════════════════
     CONTEXT BUILDING — canonical, pair-safe, weak-model sized
     ═══════════════════════════════════════════════════════════════ */
  private function groupIntoClusters(array $h): array
  {
    $clusters = [];
    foreach ($h as $m) {
      $role = $m['role'] ?? '';
      if ($role === 'tool' && !empty($clusters)) {
        $clusters[count($clusters) - 1][] = $m;
      } else {
        $clusters[] = [$m];
      }
    }
    return $clusters;
  }

  private function flattenClusters(array $clusters): array
  {
    $out = [];
    foreach ($clusters as $c) foreach ($c as $m) $out[] = $m;
    return $out;
  }

  /** Drop the oldest cluster (used by the empty-response recovery re-ask). */
  private function dropOldestCluster(array $h): array
  {
    $clusters = $this->groupIntoClusters($h);
    if (count($clusters) <= 1) return $h;
    array_shift($clusters);
    return $this->flattenClusters($clusters);
  }

  private function buildMessages(array $h, bool $withTools = true, bool $nudge = false): array
  {
    $h = $this->sanitizeTail($h);
    $h = $this->truncateOldToolOutput($h);
    $clusters = $this->groupIntoClusters($h);
    if (count($clusters) > $this->maxHistory) {
      $clusters = array_slice($clusters, -$this->maxHistory);
    }
    $h = $this->flattenClusters($clusters);
    foreach ($h as &$m) {
      if (($m['role'] ?? '') === 'tool' && isset($m['content'])
        && $this->ulen((string) $m['content']) > $this->maxFileChars
      ) {
        $m['content'] = $this->sub((string) $m['content'], 0, $this->maxFileChars) . "\n…[truncated]";
      }
      if (($m['role'] ?? '') === 'assistant' && !empty($m['tool_calls'])) {
        foreach ($m['tool_calls'] as &$tc) {
          $args = $tc['function']['arguments'] ?? [];
          $tc['function']['arguments'] = $this->repairJsonArgs($args);   // ALWAYS arrays
          if (!isset($tc['id']) || $tc['id'] === '') {
            $tc['id'] = 'call_' . $this->randId();                       // ALWAYS an id
          }
          if (!isset($tc['type'])) $tc['type'] = 'function';
        }
        unset($tc);
      }
    }
    unset($m);
    $h = $this->repairToolCallIds($h);
    $messages = array_merge(
      [['role' => 'system', 'content' => $this->systemPrompt($withTools, $nudge)]],
      $h
    );
    return $this->trimToFit($messages);
  }

  /**
   * Keep the LATEST tool outputs full; shrink older ones to a compact digest.
   * Weak models get a much smaller, more relevant context and the current
   * results are never the first thing truncated.
   */
  private function truncateOldToolOutput(array $h): array
  {
    $lastNonTool = -1;
    for ($i = count($h) - 1; $i >= 0; $i--) {
      if (($h[$i]['role'] ?? '') !== 'tool') {
        $lastNonTool = $i;
        break;
      }
    }
    foreach ($h as $i => $m) {
      if (($m['role'] ?? '') !== 'tool' || $i > $lastNonTool) continue;
      $c = (string) ($m['content'] ?? '');
      if ($this->ulen($c) > self::OLD_TOOL_KEEP) {
        $h[$i]['content'] = $this->digestToolOutput($c);
      }
    }
    return $h;
  }

  /** Compact, information-preserving digest of an old tool result. */
  private function digestToolOutput(string $json): string
  {
    $d = json_decode($json, true);
    if (!is_array($d)) {
      return $this->sub($json, 0, self::OLD_TOOL_KEEP) . "\n…[older tool output truncated]";
    }
    $parts = [];
    foreach (['path', 'from', 'to', 'query', 'command', 'success', 'verified', 'isNew', 'size', 'bytes', 'exitCode'] as $k) {
      if (array_key_exists($k, $d) && !is_array($d[$k])) {
        $parts[] = $k . '=' . (is_bool($d[$k]) ? ($d[$k] ? 'true' : 'false') : (string) $d[$k]);
      }
    }
    if (isset($d['entries']) && is_array($d['entries'])) {
      $parts[] = 'entries=' . count($d['entries']);
    }
    if (isset($d['totalMatches'])) {
      $parts[] = 'matches=' . (string) $d['totalMatches'];
    }
    if (!empty($d['error'])) {
      $parts[] = 'error=' . $this->shorten((string) $d['error'], 160);
    }
    return '[earlier tool result] ' . implode('; ', $parts);
  }

  private function repairToolCallIds(array $h): array
  {
    $pendingIds = [];
    foreach ($h as &$m) {
      $role = $m['role'] ?? '';
      if ($role === 'assistant' && !empty($m['tool_calls'])) {
        $pendingIds = [];
        foreach ($m['tool_calls'] as $tc) {
          $pendingIds[] = (string) ($tc['id'] ?? '');
        }
      } elseif ($role === 'tool') {
        if (empty($m['tool_call_id'])) {
          $m['tool_call_id'] = !empty($pendingIds)
            ? array_shift($pendingIds)
            : 'call_repaired_' . $this->randId();
        }
      } elseif ($role === 'user' || $role === 'system') {
        $pendingIds = [];
      }
    }
    unset($m);
    return $h;
  }

  /**
   * v11 fix: budget pressure now drops WHOLE CLUSTERS oldest-first, so an
   * assistant tool_calls message can never be separated from its tool result
   * (v10 spliced index 1 blindly and produced invalid provider payloads).
   */
  private function trimToFit(array $messages): array
  {
    $budget = (int) (((float) $this->numCtx) * 4.0);
    $sizeOf = function (array $m): int {
      return strlen(json_encode($m, JSON_UNESCAPED_SLASHES));
    };
    $total = 0;
    foreach ($messages as $m) $total += $sizeOf($m);
    if ($total <= $budget || count($messages) < 2) return $messages;

    // Protect the system prompt and the newest cluster.
    $head = [$messages[0]];
    $body = array_slice($messages, 1);
    $clusters = $this->groupIntoClusters($body);
    $tail = count($clusters) > 1 ? [array_pop($clusters)] : [];
    while ($total > $budget && count($clusters) > 0) {
      $victim = array_shift($clusters);
      foreach ($victim as $m) $total -= $sizeOf($m);
    }
    $body = $this->flattenClusters(array_merge($clusters, $tail));
    $messages = array_merge($head, $body);

    // Still over: truncate the OLDEST content, never the newest.
    for ($i = 1; $i < count($messages) && $total > $budget; $i++) {
      $content = (string) ($messages[$i]['content'] ?? '');
      if ($content === '' || $this->ulen($content) <= 200) continue;
      $over = $total - $budget;
      $keep = max(200, $this->ulen($content) - $over - 120);
      if ($keep < $this->ulen($content)) {
        $messages[$i]['content'] = $this->sub($content, 0, $keep)
          . "…[truncated to fit the context window]";
        $total -= max(0, $this->ulen($content) - $keep);
      }
    }
    return $messages;
  }

  /**
   * v11: two short prompts — one for workspace turns, one for plain
   * conversation — instead of one long prompt the weak model must obey.
   * The behavioural guarantees live in the application now.
   */
  private function systemPrompt(bool $withTools = true, bool $nudge = false): string
  {
    $key = ($withTools ? 't' : 'p') . ($nudge ? 'n' : '');
    if (isset($this->systemPromptCache[$key])) return $this->systemPromptCache[$key];
    if (!$withTools) {
      $p = "You are Quirky AI, a coding assistant in a local IDE. Answer the user directly in plain text. "
        . "No tools are available in this turn; do not mention tools.";
      if ($nudge) $p .= " Give the answer now, in one short paragraph.";
    } else {
      $p = "You are Quirky AI, a precise coding assistant inside a local IDE working in the user's workspace/ folder. "
        . "You have tools to list, read, write, move and delete files, search the workspace, and run commands.\n"
        . "RULES:\n"
        . "1. Use tools ONLY for the files/dirs the user asked about. Never explore speculatively.\n"
        . "2. To CREATE or OVERWRITE a file: call write_file once with the FULL content and the exact relative path.\n"
        . "3. To EDIT a file: read_file first, then write_file once with the FULL updated content.\n"
        . "4. After tools return, summarize the REAL results specifically (names, sizes, paths). Never answer with a greeting or generic phrase after a tool result.\n"
        . "5. Use the native function-calling interface only. NEVER print tool calls as text, XML, or code blocks.\n"
        . "6. When the task is done, reply in plain text with NO further tool calls.";
      if ($nudge) $p .= "\n7. Your previous reply was empty. Respond now with the next step or the final answer.";
    }
    $this->systemPromptCache[$key] = $p;
    return $p;
  }

  /* ═══════════════════════════════════════════════════════════════
     TOOL DEFINITIONS — ONE canonical cached array for ALL providers
     ═══════════════════════════════════════════════════════════════ */
  private function toolDefs(): array
  {
    if ($this->toolDefsCache !== null) return $this->toolDefsCache;
    $t = function ($n, $d, $p, $r) {
      return ['type' => 'function', 'function' => [
        'name'        => $n,
        'description' => $d,
        'parameters'  => ['type' => 'object', 'properties' => $p, 'required' => $r],
      ]];
    };
    $s = function ($d) {
      return ['type' => 'string', 'description' => $d];
    };
    $defs = [
      $t('list_dir', 'List files and folders in a directory. Use empty string "" for workspace root.', ['path' => $s('Directory path relative to workspace, "" for root')], ['path']),
      $t('read_file', 'Read the full content of a file.', ['path' => $s('File path relative to workspace')], ['path']),
      $t('write_file', 'Create or overwrite a file with the given content.', ['path' => $s('File path'), 'content' => $s('Complete file content')], ['path', 'content']),
      $t('delete_file', 'Delete a single file.', ['path' => $s('File path')], ['path']),
      $t('move_file', 'Move or rename a file or folder.', ['from' => $s('Current path'), 'to' => $s('New path')], ['from', 'to']),
      $t('create_folder', 'Create a new folder (parent folders auto-created).', ['path' => $s('Folder path')], ['path']),
      $t('delete_folder', 'Delete a folder and ALL its contents.', ['path' => $s('Folder path')], ['path']),
      $t('search_workspace', 'Search all files for text or regex pattern.', ['query' => $s('Search query')], ['query']),
    ];
    if ($this->terminal && $this->terminal->isEnabled()) {
      $defs[] = $t('run_command', 'Run a shell command (php -v, composer install, etc). NOT for file operations.', ['command' => $s('Command to run')], ['command']);
    }
    $this->toolDefsCache = $defs;
    return $defs;
  }

  private function normalizeTools($raw): array
  {
    $out = [];
    foreach ((array) $raw as $tc) {
      if (!is_array($tc)) continue;
      $fn = (isset($tc['function']) && is_array($tc['function'])) ? $tc['function'] : [];
      $args = $this->repairJsonArgs($fn['arguments'] ?? []);
      $id = (string) ($tc['id'] ?? '');
      if ($id === '') {
        $id = 'call_' . $this->randId();
      }
      $out[] = [
        'id'       => $id,
        'type'     => 'function',
        'function' => ['name' => (string) ($fn['name'] ?? ''), 'arguments' => $args],
      ];
    }
    return $out;
  }

  /**
   * Persist an in-flight transcript durably without ever throwing into the
   * run. Writing through to persistState() matters because an approved write
   * (or a pending card) must survive a provider failure later in the same run
   * AND a follow-up request that arrives without a session cookie.
   */
  private function persistPartial(array $h): void
  {
    try {
      $this->history->saveHistory($h);
      $this->history->persistState();
    } catch (\Throwable $e) {
      // Persistence problems must never abort tool execution.
    }
  }

  private function randId(): string
  {
    return bin2hex(random_bytes(8));
  }

  /* ═══════════════════════════════════════════════════════════════
     TEXT HELPERS — unicode-safe with graceful fallback (no mbstring dep)
     ═══════════════════════════════════════════════════════════════ */
  private function lc(string $s): string
  {
    return function_exists('mb_strtolower') ? mb_strtolower($s, 'UTF-8') : strtolower($s);
  }

  private function ulen(string $s): int
  {
    return function_exists('mb_strlen') ? mb_strlen($s, 'UTF-8') : strlen($s);
  }

  private function sub(string $s, int $start, ?int $len = null): string
  {
    if (function_exists('mb_substr')) return mb_substr($s, $start, $len, 'UTF-8');
    return $len === null ? substr($s, $start) : substr($s, $start, $len);
  }

  /* ═══════════════════════════════════════════════════════════════
     SSE TRANSPORT  (unchanged)
     ═══════════════════════════════════════════════════════════════ */
  private function beginStream(): void
  {
    @set_time_limit(0);
    if (function_exists('apache_setenv')) {
      @apache_setenv('no-gzip', '1');
    }
    while (ob_get_level() > 0) {
      ob_end_flush();
    }
    @ob_implicit_flush(true);
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache, no-transform');
    header('X-Accelerate-Buffering: no');
    header('Connection: keep-alive');
  }

  /* ═══════════════════════════════════════════════════════════════
     VERIFIED TOOL SUMMARIES — from REAL results, never fabricated.
     Every branch is driven by the executor's own evidence; when the
     evidence is missing the wording says "not confirmed" instead of
     claiming success.
     ═══════════════════════════════════════════════════════════════ */
  private function buildToolSummary(array $h): string
  {
    $items = $this->toolResultsSinceLastUser($h);
    if (empty($items)) {
      return '✓ Tool executed successfully.';
    }
    $errors = [];
    foreach ($items as $it) {
      $r = $it['result'];
      if (!empty($r['error']) || (array_key_exists('success', $r) && empty($r['success']))) {
        $errors[] = $it;
      }
    }
    if (!empty($errors)) {
      if (count($errors) === 1) {
        $e = $errors[0];
        $label = $e['name'] !== '' ? ' (' . $e['name'] . ')' : '';
        return '⚠ Tool error' . $label . ': ' . (string) ($e['result']['error'] ?? 'the operation failed');
      }
      $lines = ['⚠ ' . count($errors) . ' tool operation(s) failed:'];
      foreach ($errors as $e) {
        $label = $e['name'] !== '' ? $e['name'] . ': ' : '';
        $lines[] = '• ' . $label . (string) ($e['result']['error'] ?? 'failed');
      }
      return implode("\n", $lines);
    }
    if (count($items) === 1) {
      return $this->summarizeOneResult($items[0]['name'], $items[0]['result'], $h, $items[0]['tcid']);
    }
    $lines = [];
    foreach ($items as $it) {
      $lines[] = '• ' . $this->summarizeOneResult($it['name'], $it['result'], $h, $it['tcid']);
    }
    $out = "Done — " . count($items) . " operations:\n" . implode("\n", $lines);
    return $this->sub($out, 0, self::SUMMARY_MAX);
  }

  /** Summarize ONE verified tool result; paths come from the result or the saved call. */
  private function summarizeOneResult(string $name, array $r, array $h, string $tcid): string
  {
    $argPath = $this->findCallArg($h, $tcid, 'path');
    $argFrom = $this->findCallArg($h, $tcid, 'from');
    $argTo   = $this->findCallArg($h, $tcid, 'to');
    $unverified = empty($r['verified']) && in_array(strtolower($name), self::MUTATING_TOOLS, true);

    if (isset($r['content']) && (isset($r['path']) || $argPath !== '')) {
      $path = (string) ($r['path'] ?? $argPath);
      $preview = $this->sub((string) $r['content'], 0, 1200);
      if ($this->ulen((string) $r['content']) > 1200) $preview .= "\n…[truncated]";
      return '📖 **' . $path . '** (' . $this->fmtBytes((int) ($r['size'] ?? strlen((string) $r['content']))) . "):\n```\n" . $preview . "\n```";
    }
    if (isset($r['entries']) && is_array($r['entries'])) {
      $lines = [];
      $folders = 0;
      $files = 0;
      foreach ($r['entries'] as $entry) {
        if (!is_array($entry)) continue;
        if (($entry['type'] ?? '') === 'folder') {
          $lines[] = '📁 ' . ($entry['name'] ?? '?') . '/';
          $folders++;
        } else {
          $size = isset($entry['size']) ? ' (' . $this->fmtBytes((int) $entry['size']) . ')' : '';
          $lines[] = '📄 ' . ($entry['name'] ?? '?') . $size;
          $files++;
        }
      }
      $dir = (string) ($r['path'] ?? $argPath);
      $header = '📂 **' . ($dir === '' || $dir === '(root)' ? 'workspace/' : $dir . '/') . '**';
      if (empty($lines)) return $header . ' — empty (no files or folders)';
      return $header . " — {$folders} folder(s), {$files} file(s)\n" . implode("\n", $lines);
    }
    if (isset($r['results']) && isset($r['totalMatches'])) {
      $total = $r['totalMatches'] ?? 0;
      $filesN = $r['totalFiles'] ?? 0;
      $summary = "🔍 Found **{$total}** match(es) in **{$filesN}** file(s).";
      if (!empty($r['results']) && is_array($r['results'])) {
        $summary .= "\n";
        foreach (array_slice($r['results'], 0, 5) as $file) {
          $summary .= '• `' . (is_array($file) ? ($file['file'] ?? '?') : '?') . "`\n";
        }
      }
      return rtrim($summary);
    }
    if (array_key_exists('output', $r)) {
      $out = $this->sub((string) $r['output'], 0, 1500);
      if ($this->ulen((string) $r['output']) > 1500) $out .= "\n…[truncated]";
      $code = (int) ($r['exitCode'] ?? 0);
      $status = ($code === 0) ? '✅' : '⚠️ exit ' . $code;
      return $status . " Command output:\n```\n" . $out . "\n```";
    }
    // State-changing tools: report the REAL target + size from the result.
    $lName = strtolower($name);
    if ($lName === 'write_file') {
      $what = (string) ($r['path'] ?? $argPath);
      $size = isset($r['size']) ? ' (' . $this->fmtBytes((int) $r['size']) . ')'
        : (isset($r['bytes']) ? ' (' . $this->fmtBytes((int) $r['bytes']) . ')' : '');
      $warn = !empty($r['warning']) ? ' — ⚠ ' . (string) $r['warning'] : '';
      if ($unverified) {
        return '⚠ write_file was requested for `' . $what . '` but the executor returned no confirmation — the file was NOT verified on disk.';
      }
      if (!empty($r['isNew'])) return '✅ Created: `' . $what . '`' . $size . $warn;
      return '✅ Wrote: `' . $what . '`' . $size . $warn;
    }
    if ($lName === 'delete_file' || $lName === 'delete_folder') {
      $what = (string) ($r['deleted'] ?? $r['path'] ?? $argPath);
      return $unverified
        ? '⚠ delete requested for `' . $what . '` but the executor returned no confirmation.'
        : '🗑️ Deleted: `' . $what . '`';
    }
    if ($lName === 'create_folder') {
      $what = (string) ($r['created'] ?? $r['path'] ?? $argPath);
      return $unverified
        ? '⚠ create_folder requested for `' . $what . '` but the executor returned no confirmation.'
        : '📁 Created folder: `' . $what . '`';
    }
    if ($lName === 'move_file') {
      $from = (string) ($r['from'] ?? $argFrom);
      $to = (string) ($r['to'] ?? $r['path'] ?? $argTo);
      return $unverified
        ? '⚠ move requested `' . $from . '` → `' . $to . '` but the executor returned no confirmation.'
        : '📦 Moved: `' . $from . '` → `' . $to . '`';
    }
    if (!empty($r['success']) || !empty($r['verified'])) {
      $what = (string) ($r['path'] ?? $r['created'] ?? $r['deleted'] ?? $r['to'] ?? $argPath);
      return $what !== '' ? '✅ Done: `' . $what . '`' : '✓ Tool executed successfully.';
    }
    return '✓ Tool executed successfully. Result: ' . $this->sub(json_encode($r, JSON_UNESCAPED_SLASHES), 0, 500);
  }

  /** Look up a saved argument from the assistant call matching a tool_call_id. */
  private function findCallArg(array $h, string $tcid, string $key): string
  {
    if ($tcid === '') return '';
    for ($i = count($h) - 1; $i >= 0; $i--) {
      if (($h[$i]['role'] ?? '') !== 'assistant' || empty($h[$i]['tool_calls'])) continue;
      foreach ($h[$i]['tool_calls'] as $tc) {
        if ((string) ($tc['id'] ?? '') === $tcid) {
          $a = $tc['function']['arguments'] ?? [];
          if (is_array($a) && isset($a[$key]) && is_string($a[$key])) return $a[$key];
        }
      }
    }
    return '';
  }

  private function fmtBytes(int $b): string
  {
    if ($b <= 0) return '0 B';
    $u = ['B', 'KB', 'MB'];
    $i = min((int) floor(log($b, 1024)), count($u) - 1);
    return round($b / pow(1024, $i), 1) . ' ' . $u[$i];
  }
}

/* ═══════════════════════════════════════════════════════════════════════
   EMITTERS — SSE frame contract is BYTE-IDENTICAL to v8/v9/v10 (do not touch)
   ═══════════════════════════════════════════════════════════════════════ */
interface Emitter
{
  public function delta(string $s): void;
  public function text(string $s): void;
  public function toolProgress(string $name, array $args): void;
  public function pending(array $calls): void;
  public function error(string $msg): void;
  public function done(): void;
}

final class JsonEmitter implements Emitter
{
  public string $textBuf = '';
  public function delta(string $s): void {}
  public function text(string $s): void
  {
    $this->textBuf = $s;
  }
  public function toolProgress(string $name, array $args): void {}
  public function pending(array $calls): void {}
  public function error(string $msg): void {}
  public function done(): void {}
}

final class SseEmitter implements Emitter
{
  private function ev(string $event, $data): void
  {
    echo 'event: ' . $event . "\n"
      . 'data: ' . json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n";
    @ob_flush();
    @flush();
  }
  public function delta(string $s): void
  {
    $this->ev('delta', $s);
  }
  public function text(string $s): void
  {
    $this->ev('delta', $s);
  }
  public function toolProgress(string $name, array $args): void
  {
    $hint = '';
    if (isset($args['path']))        $hint = (string) $args['path'];
    elseif (isset($args['command'])) $hint = (string) $args['command'];
    elseif (isset($args['query']))   $hint = (string) $args['query'];
    $this->ev('tool', ['name' => $name, 'hint' => $this->mbSub($hint, 0, 80)]);
  }
  public function pending(array $calls): void
  {
    $this->ev('pending', $calls);
  }
  public function error(string $msg): void
  {
    $this->ev('error', $msg);
  }
  public function done(): void
  {
    $this->ev('done', ['ok' => true]);
  }
  private function mbSub(string $s, int $start, int $len): string
  {
    return function_exists('mb_substr') ? mb_substr($s, $start, $len, 'UTF-8') : substr($s, $start, $len);
  }
}
