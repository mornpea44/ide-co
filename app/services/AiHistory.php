<?php

declare(strict_types=1);
/**
 * ═══════════════════════════════════════════════════════════════════════════
 *  QUIRKY IDE — AI SESSION HISTORY  (v12 · durable state)
 * ═══════════════════════════════════════════════════════════════════════════
 *
 *  DROP-IN REPLACEMENT for AiHistory.php. Same class, same public API.
 *
 *  ── THE ACTUAL REMAINING BUG (proved by the latest screenshots) ──────────
 *
 *  The UI showed, in one session:
 *      • model selector  : “— no models —”
 *      • after approval  : “No pending tool call could be matched.”
 *      • after regenerate: “Nothing to regenerate yet — send a message first.”
 *
 *  Those three cannot happen together unless the SERVER-SIDE STATE IS EMPTY on
 *  each request. The transcript on screen is the frontend's own client-side
 *  copy. Server-side, $_SESSION was starting fresh every request, so:
 *      quirky_ai_pending  → gone  → approval could not match  → no file write
 *      quirky_ai_history  → gone  → regenerate had no last user message
 *      quirky_ai_models   → gone  → model slot empty (“no models”)
 *
 *  Why the PHP session can silently fail here (all seen in the wild on a
 *  phone-hosted IDE): a non-writable session.save_path, a session cookie that
 *  the mobile browser drops (SameSite/private mode/IP-vs-host origin change),
 *  requests arriving on a different host/port than the one that set the
 *  cookie, or the SSE endpoint being opened by EventSource without cookies.
 *  Previous versions blamed the agent loop, but no amount of loop hardening
 *  can recover state that is not there — which is exactly why the earlier
 *  fixes made the failure *visible* (“could not be matched”) rather than
 *  fixed. The state layer itself has to stop depending on cookies.
 *
 *  ── v12 FIX: SESSION-OPTIONAL DURABLE STATE ─────────────────────────────
 *
 *  Quirky IDE is a single-user local IDE with one workspace, so AI state is
 *  now persisted to a real file next to the app (IDE_APP/.ai-state.json) and
 *  the PHP session is kept only as a mirror/fast path:
 *
 *      load()   : read session AND file, use whichever is newer (by 'rev'),
 *                 so a working session is still authoritative mid-request and
 *                 a broken session transparently falls back to the file.
 *      persist(): write the file atomically (temp + rename, LOCK_EX) and
 *                 mirror into the session when one is available.
 *
 *  Effects: pending approvals, transcript, provider, per-provider model slots
 *  and permission mode all survive cookie-less requests, SSE reconnects and
 *  PHP session resets. Approvals match, files get written, and Regenerate has
 *  a last user message to work from.
 *
 *  PRESERVED: class name, constructor signature, every public method and
 *  return shape, the read-once/close-session locking behaviour, the legacy
 *  quirky_ai_model / quirky_ai_model_nvidia mirrors, cluster-safe capping,
 *  and v11's id-based stale-pending resolution.
 * ═══════════════════════════════════════════════════════════════════════════
 */
class AiHistory
{
  private IdeSecurity $security;
  private int $maxHistory;

  /* ── In-memory state (session stays CLOSED during AI work) ── */
  private array  $history        = [];
  private array  $pendingCalls   = [];
  private array  $modelSlots     = [];
  private string $sessionModel   = '';   // legacy slot (wire 'ollama')
  private string $sessionModelNv = '';   // legacy slot ('nvidia')
  private string $sessionPerm    = '';
  private string $provider       = 'ollama';

  /** Monotonic revision so session and file can be compared. */
  private int $rev = 0;
  private string $statePath = '';

  public function __construct(IdeSecurity $security, int $maxHistory = 6)
  {
    $this->security   = $security;
    $this->maxHistory = max(2, $maxHistory);
    $this->statePath  = $this->resolveStatePath();
    $this->loadState();
  }

  /* ═══════════════════════════════════════════════════════════════
     DURABLE STATE LOCATION
     ═══════════════════════════════════════════════════════════════ */
  private function resolveStatePath(): string
  {
    $dir = defined('IDE_APP') ? (string) IDE_APP : '';
    if ($dir === '' || !is_dir($dir) || !is_writable($dir)) {
      $dir = sys_get_temp_dir();
    }
    return rtrim(str_replace('\\', '/', $dir), '/') . '/.ai-state.json';
  }

  /* ═══════════════════════════════════════════════════════════════
     SESSION + FILE STATE — read once, work freely, write once
     ═══════════════════════════════════════════════════════════════ */
  private function loadState(): void
  {
    $fromSession = $this->readSessionState();   // also closes the session
    $fromFile    = $this->readFileState();

    // Use whichever snapshot is newer. A dead/blank session therefore falls
    // back to the durable file instead of resetting the assistant.
    $chosen = $fromSession;
    if ($fromFile !== null) {
      if ($fromSession === null || ($fromFile['rev'] ?? 0) > ($fromSession['rev'] ?? 0)) {
        $chosen = $fromFile;
      }
    }
    if ($chosen === null) return;

    $this->rev            = (int) ($chosen['rev'] ?? 0);
    $this->provider       = is_string($chosen['provider'] ?? null) && $chosen['provider'] !== ''
      ? (string) $chosen['provider'] : 'ollama';
    $this->history        = is_array($chosen['history'] ?? null)
      ? array_values(array_filter($chosen['history'], 'is_array')) : [];
    $this->pendingCalls   = $this->normalizePending($chosen['pending'] ?? []);
    $this->modelSlots     = $this->normalizeSlots($chosen['models'] ?? []);
    $this->sessionModel   = (string) ($chosen['model'] ?? '');
    $this->sessionModelNv = (string) ($chosen['modelNvidia'] ?? '');
    $perm = (string) ($chosen['perm'] ?? '');
    if (in_array($perm, ['always_ask', 'ask_destructive', 'auto_approve'], true)) {
      $this->sessionPerm = $perm;
    }
    // Legacy seeding so a pre-v9 session still selects the right model.
    if ($this->sessionModel !== '' && ($this->modelSlots['ollama'] ?? '') === '') {
      $this->modelSlots['ollama'] = $this->sessionModel;
    }
    if ($this->sessionModelNv !== '' && ($this->modelSlots['nvidia'] ?? '') === '') {
      $this->modelSlots['nvidia'] = $this->sessionModelNv;
    }
  }

  /** Read the legacy session keys, then release the session lock. */
  private function readSessionState(): ?array
  {
    try {
      $this->security->startSession();
    } catch (\Throwable $e) {
      return null;                                   // sessions unavailable
    }
    if (!isset($_SESSION) || !is_array($_SESSION)) {
      $this->closeSession();
      return null;
    }
    $has = isset($_SESSION['quirky_ai_history'])
      || isset($_SESSION['quirky_ai_pending'])
      || isset($_SESSION['quirky_ai_models'])
      || isset($_SESSION['quirky_ai_model'])
      || isset($_SESSION['quirky_ai_model_nvidia'])
      || isset($_SESSION['quirky_ai_perm'])
      || isset($_SESSION['quirky_ai_provider']);
    $state = $has ? [
      'rev'         => (int) ($_SESSION['quirky_ai_rev'] ?? 1),
      'provider'    => $_SESSION['quirky_ai_provider'] ?? 'ollama',
      'history'     => $_SESSION['quirky_ai_history'] ?? [],
      'pending'     => $_SESSION['quirky_ai_pending'] ?? [],
      'models'      => $_SESSION['quirky_ai_models'] ?? [],
      'model'       => $_SESSION['quirky_ai_model'] ?? '',
      'modelNvidia' => $_SESSION['quirky_ai_model_nvidia'] ?? '',
      'perm'        => $_SESSION['quirky_ai_perm'] ?? '',
    ] : null;
    $this->closeSession();
    return $state;
  }

  /** Read the durable snapshot. Never throws; a bad file is simply ignored. */
  private function readFileState(): ?array
  {
    if ($this->statePath === '' || !is_file($this->statePath)) return null;
    $raw = @file_get_contents($this->statePath);
    if ($raw === false || $raw === '') return null;
    $data = json_decode($raw, true);
    if (!is_array($data) || ($data['v'] ?? 0) !== 1) return null;
    return [
      'rev'         => (int) ($data['rev'] ?? 0),
      'provider'    => $data['provider'] ?? 'ollama',
      'history'     => $data['history'] ?? [],
      'pending'     => $data['pending'] ?? [],
      'models'      => $data['models'] ?? [],
      'model'       => $data['model'] ?? '',
      'modelNvidia' => $data['modelNvidia'] ?? '',
      'perm'        => $data['perm'] ?? '',
    ];
  }

  private function capByCluster(array $h, int $capMessages): array
  {
    $clusters = [];
    foreach ($h as $m) {
      if (!is_array($m)) continue;
      $role = $m['role'] ?? '';
      if ($role === 'tool' && !empty($clusters)) {
        $clusters[count($clusters) - 1][] = $m;
      } else {
        $clusters[] = [$m];
      }
    }
    $out = [];
    $count = 0;
    for ($i = count($clusters) - 1; $i >= 0; $i--) {
      $clusterCount = count($clusters[$i]);
      if (($count + $clusterCount) > $capMessages && !empty($out)) break;
      $count += $clusterCount;
      array_unshift($out, $clusters[$i]);
    }
    $flat = [];
    foreach ($out as $c) foreach ($c as $m) $flat[] = $m;
    return $flat;
  }

  /**
   * Persist to the durable file first (the source of truth when cookies fail)
   * and mirror into the session when one is actually available.
   */
  public function persistState(): void
  {
    $cap = max($this->maxHistory * 4, 16);
    if (count($this->history) > $cap) {
      $this->history = $this->capByCluster($this->history, $cap);
    }
    // Keep the legacy single-slot mirrors coherent.
    if (($this->modelSlots['ollama'] ?? '') !== '') $this->sessionModel = (string) $this->modelSlots['ollama'];
    if (($this->modelSlots['nvidia'] ?? '') !== '') $this->sessionModelNv = (string) $this->modelSlots['nvidia'];
    $this->rev++;

    $this->writeFileState();
    $this->writeSessionState();
  }

  private function writeFileState(): void
  {
    if ($this->statePath === '') return;
    $payload = [
      'v'           => 1,
      'rev'         => $this->rev,
      'time'        => time(),
      'provider'    => $this->provider,
      'history'     => $this->history,
      'pending'     => $this->pendingCalls,
      'models'      => $this->modelSlots,
      'model'       => $this->sessionModel,
      'modelNvidia' => $this->sessionModelNv,
      'perm'        => $this->sessionPerm,
    ];
    $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    if ($json === false) return;
    // Atomic replace so a concurrent reader never sees a half-written file.
    $tmp = $this->statePath . '.' . getmypid() . '.tmp';
    if (@file_put_contents($tmp, $json, LOCK_EX) === false) {
      @file_put_contents($this->statePath, $json, LOCK_EX);
      return;
    }
    if (!@rename($tmp, $this->statePath)) {
      @unlink($tmp);
      @file_put_contents($this->statePath, $json, LOCK_EX);
    }
  }

  private function writeSessionState(): void
  {
    try {
      $this->security->startSession();
    } catch (\Throwable $e) {
      return;                                        // file state already saved
    }
    if (!isset($_SESSION) || !is_array($_SESSION)) return;

    $_SESSION['quirky_ai_rev']      = $this->rev;
    $_SESSION['quirky_ai_provider'] = $this->provider;
    $_SESSION['quirky_ai_history']  = $this->history;
    if (!empty($this->pendingCalls)) $_SESSION['quirky_ai_pending'] = $this->pendingCalls;
    else unset($_SESSION['quirky_ai_pending']);
    if (!empty($this->modelSlots)) $_SESSION['quirky_ai_models'] = $this->modelSlots;
    else unset($_SESSION['quirky_ai_models']);
    if ($this->sessionModel !== '') $_SESSION['quirky_ai_model'] = $this->sessionModel;
    else unset($_SESSION['quirky_ai_model']);
    if ($this->sessionModelNv !== '') $_SESSION['quirky_ai_model_nvidia'] = $this->sessionModelNv;
    else unset($_SESSION['quirky_ai_model_nvidia']);
    if ($this->sessionPerm !== '') $_SESSION['quirky_ai_perm'] = $this->sessionPerm;
    else unset($_SESSION['quirky_ai_perm']);

    $this->closeSession();
  }

  private function closeSession(): void
  {
    if (session_status() === PHP_SESSION_ACTIVE) {
      @session_write_close();
    }
  }

  /* ═══════════════════════════════════════════════════════════════
     HISTORY ACCESSORS
     ═══════════════════════════════════════════════════════════════ */
  public function getHistory(): array
  {
    return $this->history;
  }

  public function saveHistory(array $h): void
  {
    $this->history = array_values(array_filter($h, 'is_array'));
  }

  public function clearHistory(): void
  {
    $this->history      = [];
    $this->pendingCalls = [];
    $this->persistState();
  }

  public function truncateHistory(string $mode): array
  {
    $h = $this->history;
    $lastUserIdx = -1;
    for ($i = count($h) - 1; $i >= 0; $i--) {
      if (($h[$i]['role'] ?? '') === 'user') {
        $lastUserIdx = $i;
        break;
      }
    }
    if ($lastUserIdx === -1) {
      return ['ok' => false, 'error' => 'Nothing to delete yet.'];
    }
    $h = $mode === 'response'
      ? array_slice($h, 0, $lastUserIdx + 1)
      : array_slice($h, 0, $lastUserIdx);
    $this->pendingCalls = [];
    $this->history = $h;
    $this->persistState();
    return ['ok' => true, 'remaining' => count($h)];
  }

  /* ═══════════════════════════════════════════════════════════════
     PENDING APPROVALS
     ═══════════════════════════════════════════════════════════════ */
  public function setPending(array $calls): void
  {
    $this->pendingCalls = $this->normalizePending($calls);
    // Pending must survive even if the caller dies before persistState():
    // an approval arrives in a SEPARATE request and needs this on disk.
    $this->persistState();
  }

  public function getPending(): array
  {
    return $this->pendingCalls;
  }

  public function clearPending(): void
  {
    $this->pendingCalls = [];
  }

  /**
   * Resolve a pending approval abandoned by a newer user message, pairing by
   * exact tool_call_id against the most recent assistant tool_calls block.
   */
  public function resolveStalePending(array &$h): void
  {
    $pending = $this->pendingCalls;
    if (empty($pending)) return;

    $ownerIndex = -1;
    for ($i = count($h) - 1; $i >= 0; $i--) {
      if (($h[$i]['role'] ?? '') === 'assistant' && !empty($h[$i]['tool_calls'])) {
        $ownerIndex = $i;
        break;
      }
    }
    if ($ownerIndex === -1) {
      $this->pendingCalls = [];
      return;
    }
    $expected = [];
    foreach ((array) $h[$ownerIndex]['tool_calls'] as $tc) {
      if (!is_array($tc)) continue;
      $id = (string) ($tc['id'] ?? '');
      if ($id !== '') $expected[$id] = true;
    }
    if (empty($expected)) {
      $this->pendingCalls = [];
      return;
    }
    $answered = [];
    for ($i = $ownerIndex + 1; $i < count($h); $i++) {
      $role = $h[$i]['role'] ?? '';
      if ($role === 'user') break;
      if ($role === 'tool') {
        $id = (string) ($h[$i]['tool_call_id'] ?? '');
        if ($id !== '') $answered[$id] = true;
      }
    }
    foreach ($pending as $call) {
      $id = (string) ($call['tool_call_id'] ?? $call['id'] ?? '');
      if ($id === '' || !isset($expected[$id]) || isset($answered[$id])) continue;
      $h[] = [
        'role'         => 'tool',
        'tool_call_id' => $id,
        'name'         => (string) ($call['name'] ?? ''),
        'content'      => json_encode(
          ['success' => false, 'error' => 'Denied by user (no response given).', 'denied' => true],
          JSON_UNESCAPED_SLASHES
        ),
      ];
      $answered[$id] = true;
    }
    $this->pendingCalls = [];
  }

  private function normalizePending($calls): array
  {
    if (!is_array($calls)) return [];
    $out = [];
    foreach ($calls as $call) {
      if (!is_array($call)) continue;
      $name = strtolower(trim((string) ($call['name'] ?? '')));
      $id = (string) ($call['id'] ?? '');
      $tcid = (string) ($call['tool_call_id'] ?? $id);
      if ($name === '' || $tcid === '') continue;
      $out[] = [
        'id'           => $id !== '' ? $id : $tcid,
        'tool_call_id' => $tcid,
        'name'         => $name,
        'arguments'    => is_array($call['arguments'] ?? null) ? $call['arguments'] : [],
      ];
    }
    return $out;
  }

  private function normalizeSlots($slots): array
  {
    if (!is_array($slots)) return [];
    $clean = [];
    foreach ($slots as $id => $model) {
      if (is_string($id) && (is_string($model) || is_numeric($model))) {
        $value = trim((string) $model);
        if ($value !== '') $clean[$id] = $value;
      }
    }
    return $clean;
  }

  /* ═══════════════════════════════════════════════════════════════
     MODEL & PROVIDER STATE
     ═══════════════════════════════════════════════════════════════ */
  public function getProvider(): string
  {
    return $this->provider;
  }

  public function setProvider(string $p): void
  {
    $p = trim($p);
    if ($p !== '') $this->provider = $p;
  }

  public function getSessionModel(): string
  {
    return $this->sessionModel;
  }

  public function setSessionModel(string $m): void
  {
    $this->sessionModel = trim($m);
    if ($this->sessionModel !== '') $this->modelSlots['ollama'] = $this->sessionModel;
  }

  public function getSessionModelNvidia(): string
  {
    return $this->sessionModelNv;
  }

  public function setSessionModelNvidia(string $m): void
  {
    $this->sessionModelNv = trim($m);
    if ($this->sessionModelNv !== '') $this->modelSlots['nvidia'] = $this->sessionModelNv;
  }

  public function getModelFor(string $providerId): string
  {
    $pid = trim($providerId);
    if ($pid !== '' && isset($this->modelSlots[$pid])) {
      return (string) $this->modelSlots[$pid];
    }
    if ($pid === 'ollama') return $this->sessionModel;
    if ($pid === 'nvidia') return $this->sessionModelNv;
    return '';
  }

  public function setModelFor(string $providerId, string $model): void
  {
    $pid = trim($providerId);
    $model = trim($model);
    if ($pid === '') return;
    if ($model !== '') $this->modelSlots[$pid] = $model;
    else unset($this->modelSlots[$pid]);
    if ($pid === 'ollama') $this->sessionModel = $model;
    if ($pid === 'nvidia') $this->sessionModelNv = $model;
  }

  public function getSessionPerm(): string
  {
    return $this->sessionPerm;
  }

  public function setSessionPerm(string $p): void
  {
    if (in_array($p, ['always_ask', 'ask_destructive', 'auto_approve'], true)) {
      $this->sessionPerm = $p;
    }
  }
}
