<?php

declare(strict_types=1);
/**
 * ═══════════════════════════════════════════════════════════════════════════
 *  QUIRKY IDE — MULTI-PROVIDER AI LAYER · CORE CONTRACT  (v9)
 * ═══════════════════════════════════════════════════════════════════════════
 *
 *  This file holds TWO things:
 *
 *    1. `AiProvider`       — the interface every wire adapter implements.
 *    2. `AiAdapterBase`    — abstract base with all the shared plumbing:
 *                             • definition/knob accessors
 *                             • masked-key algorithm (ported from NvidiaAI)
 *                             • Android CA-bundle resolution (QUIRKY_PREFIX +
 *                               IDE_ROOT/.usr + Laragon fallback chain)
 *                             • hardened cURL (timeouts, low-speed abort)
 *                             • SSE / NDJSON partial-line buffered reader
 *                               (FIX #4 carried over: a "data:" line split
 *                               across TCP chunks is never thrown away)
 *                             • friendly error mapping prefixed "[<label>] "
 *
 *  CANONICAL MESSAGE FORM (produced by IdeAI::buildMessages):
 *      ['role'=>'system'|'user'|'assistant'|'tool', 'content'=>string]
 *      assistant may carry 'tool_calls':
 *          [['id','type'=>'function','function'=>['name','arguments'=>ARRAY]], …]
 *      tool results: role 'tool' + 'tool_call_id' + 'name' + 'content'
 *
 *  Each adapter serializes this canonical form into its own wire dialect.
 *  Adapters NEVER see provider-name conditionals — the wire() value picks
 *  the adapter class, everything below askModel() is provider-neutral.
 *
 *  EXPOSES: AiProvider (interface), AiAdapterBase (abstract)
 * ═══════════════════════════════════════════════════════════════════════════
 */

interface AiProvider
{
  public function id(): string;
  public function label(): string;
  public function wire(): string;

  /** Attach the stored provider definition (id,label,wire,baseUrl,apiKey,model,headers,…). */
  public function setDefinition(array $def): void;
  public function setKnobs(array $knobs): void;

  /** @return array{tools:bool,streaming:bool} */
  public function capabilities(): array;

  /**
   * Static-ish health snapshot (NO expensive network call except the
   * ollama wire, whose cheap /api/tags probe IS its liveness check).
   * Keys: online, modelLoaded, availableModels, activeModel, hasKey,
   *       maskedKey, baseUrl, keyOptional, testedAt?, lastError?
   */
  public function status(): array;

  /** @return array<int,array{id:string,label?:string,tag?:string}> */
  public function listModels(bool $refresh = false): array;

  /**
   * Canonical in → canonical out.
   * @param array $messages canonical messages
   * @param array $tools    canonical tool defs (OpenAI function shape)
   * @return array{content:string,tool_calls:array}
   */
  public function chat(array $messages, string $model, array $tools = []): array;

  /**
   * Streaming variant. $onDelta(string $text) fires per text fragment;
   * $onToolCall(array $toolCalls) fires once when assembled tool calls land.
   * @return array{content:string,tool_calls:array,streamed:bool}
   */
  public function chatStream(
    array $messages,
    string $model,
    array $tools,
    callable $onDelta,
    callable $onToolCall
  ): array;

  /** Cheap reachability/auth probe. @return array{ok:bool,latencyMs:int,httpCode?:int,detail?:string,error?:string} */
  public function testConnection(): array;
}

/* ═══════════════════════════════════════════════════════════════════════════
   ABSTRACT BASE — shared hardened plumbing for every wire adapter
   ═══════════════════════════════════════════════════════════════════════════ */
abstract class AiAdapterBase implements AiProvider
{
  protected array $def   = [];
  protected array $knobs = [];
  protected string $caCertPath = '';
  private bool $caResolved = false;

  public function __construct(array $knobs = [], array $def = [])
  {
    $this->setKnobs($knobs);
    if ($def !== []) $this->setDefinition($def);
  }

  /* ═══════════════════════════════════════════════════════════
     DEFINITION + CONFIG KNOBS
    ═══════════════════════════════════════════════════════════ */
  public function setKnobs(array $knobs): void
  {
    $this->knobs = $knobs;
  }

  public function setDefinition(array $def): void
  {
    $def['id']        = (string) ($def['id'] ?? '');
    $def['label']     = (string) ($def['label'] ?? '');
    /** @disregard P1012 */
    $def['wire']      = (string) ($def['wire'] ?? static::WIRE);
    $def['preset']    = isset($def['preset']) ? (string) $def['preset'] : null;
    $def['baseUrl']   = rtrim(trim((string) ($def['baseUrl'] ?? '')), '/');
    $def['apiKey']    = trim((string) ($def['apiKey'] ?? ''));
    $def['model']     = trim((string) ($def['model'] ?? ''));
    $def['headers']   = isset($def['headers']) && is_array($def['headers']) ? $def['headers'] : [];
    $def['keyOptional'] = !empty($def['keyOptional']);
    $this->def = $def;
  }

  public function id(): string
  {
    return (string) ($this->def['id'] ?? '');
  }

  public function label(): string
  {
    $l = (string) ($this->def['label'] ?? '');
    return $l !== '' ? $l : $this->id();
  }


  
  public function wire(): string
  {
    /** @disregard P1012 */
    return (string) ($this->def['wire'] ?? static::WIRE);
  }

  protected function baseUrl(): string
  {
    return (string) ($this->def['baseUrl'] ?? '');
  }

  protected function apiKey(): string
  {
    return (string) ($this->def['apiKey'] ?? '');
  }

  public function hasKey(): bool
  {
    return $this->apiKey() !== '';
  }

  protected function keyOptional(): bool
  {
    return !empty($this->def['keyOptional']);
  }

  /** Extra per-provider headers from the store (assoc name=>value). */
  protected function extraHeaders(): array
  {
    $h = $this->def['headers'] ?? [];
    $out = [];
    foreach (is_array($h) ? $h : [] as $k => $v) {
      if (is_string($k) && $k !== '' && is_scalar($v)) $out[$k] = (string) $v;
    }
    return $out;
  }

  /** Raw $config['ai'] knob accessors — sampling params apply across ALL providers. */
  protected function cfg(string $key, mixed $default = null): mixed
  {
    return $this->knobs[$key] ?? $default;
  }
  protected function fCfg(string $key, float $default): float
  {
    return is_numeric($this->knobs[$key] ?? null) ? (float) $this->knobs[$key] : $default;
  }
  protected function iCfg(string $key, int $default): int
  {
    return is_numeric($this->knobs[$key] ?? null) ? (int) $this->knobs[$key] : $default;
  }

  public function capabilities(): array
  {
    return ['tools' => true, 'streaming' => true];
  }

  /* ═══════════════════════════════════════════════════════════
     MASKED KEY  (algorithm ported verbatim from NvidiaAI)
     NEVER expose the raw key through any API path.
    ═══════════════════════════════════════════════════════════ */

  public function maskedKey(): string
  {
    return self::maskKeyStatic($this->apiKey());
  }

  public static function maskKeyStatic(string $key): string
  {
    $key = trim($key);
    if ($key === '') return '';
    $len = strlen($key);
    if ($len <= 8) return str_repeat('•', $len);
    return substr($key, 0, 4) . str_repeat('•', $len - 8) . substr($key, -4);
  }

  /** Guard for authenticated calls — local/keyless servers pass through. */
  protected function requireKey(): void
  {
    if ($this->hasKey() || $this->keyOptional()) return;
    throw new \RuntimeException(
      '[' . $this->label() . '] No API key set. Open the AI provider settings and add one.'
    );
  }

  /* ═══════════════════════════════════════════════════════════
     TLS — bundled CA resolution (Android sandbox + Laragon dev)
     Ported from NvidiaAI::resolveCaCert/applyCa and widened with a
     Laragon cacert fallback chain for desktop HTTPS calls.
    ═══════════════════════════════════════════════════════════ */

  protected function caCert(): string
  {
    if ($this->caResolved) return $this->caCertPath;
    $this->caResolved = true;
    $this->caCertPath = '';
    $candidates = [];

    $prefix = getenv('QUIRKY_PREFIX');
    if (is_string($prefix) && $prefix !== '') {
      $candidates[] = rtrim(str_replace('\\', '/', $prefix), '/') . '/etc/tls/cacert.pem';
    }
    $root = defined('IDE_ROOT') ? rtrim(str_replace('\\', '/', (string) IDE_ROOT), '/') : '';
    if ($root !== '') {
      $candidates[] = $root . '/.usr/etc/tls/cacert.pem';
      // Walk up a few levels hunting a Laragon-style install:
      //   <laragon>/etc/ssl/cacert.pem  or  <laragon>/etc/ca/cacert.pem
      $parts = explode('/', $root);
      $max = min(count($parts) - 1, 5);
      for ($i = 1; $i <= $max; $i++) {
        $base = implode('/', array_slice($parts, 0, $i));
        $candidates[] = $base . '/etc/ssl/cacert.pem';
        $candidates[] = $base . '/etc/ca/cacert.pem';
      }
    }
    $candidates[] = 'C:/laragon/etc/ssl/cacert.pem';
    $candidates[] = 'C:/laragon/etc/ca/cacert.pem';

    foreach ($candidates as $c) {
      if ($c !== '' && is_file($c)) {
        $this->caCertPath = $c;
        @ini_set('openssl.cafile', $c);
        break;
      }
    }
    return $this->caCertPath;
  }

  /** Attach the CA bundle to a cURL handle (no-op when nothing found). */
  protected function applyCa($ch): void
  {
    $ca = $this->caCert();
    if ($ca === '') return;
    curl_setopt($ch, CURLOPT_CAINFO, $ca);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
  }

  /* ═══════════════════════════════════════════════════════════
     HARDENED cURL HELPERS
    ═══════════════════════════════════════════════════════════ */

  protected function headerLines(array $headers): array
  {
    $lines = [];
    foreach ($headers as $k => $v) {
      if (is_int($k)) $lines[] = (string) $v;
      else $lines[] = $k . ': ' . (string) $v;
    }
    return $lines;
  }

  protected function host(): string
  {
    $p = parse_url($this->baseUrl());
    $h = (string) ($p['host'] ?? '');
    if ($h === '') return $this->baseUrl();
    return isset($p['port']) ? $h . ':' . $p['port'] : $h;
  }

  /**
   * Blocking JSON request. Returns ['code'=>int,'raw'=>string,'decoded'=>?array].
   * Throws a friendly RuntimeException on transport failure.
   */
  protected function httpJson(string $url, array $headers, ?array $body, int $timeout = 120): array
  {
    $ch = curl_init($url);
    $opts = [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_TIMEOUT        => max(5, $timeout),
      CURLOPT_CONNECTTIMEOUT => 10,
      // Abort stalled transfers: <1 byte/s for 45 s means the pipe is dead.
      CURLOPT_LOW_SPEED_LIMIT => 1,
      CURLOPT_LOW_SPEED_TIME  => 45,
      CURLOPT_FOLLOWLOCATION  => true,
      CURLOPT_MAXREDIRS       => 3,
      CURLOPT_HTTPHEADER      => $this->headerLines($headers),
    ];
    if ($body !== null) {
      $opts[CURLOPT_POST]       = true;
      $opts[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
    curl_setopt_array($ch, $opts);
    $this->applyCa($ch);
    $resp = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($resp === false) throw new \RuntimeException($this->transportError($err));
    $raw = (string) $resp;
    return [
      'code'    => $code,
      'raw'     => $raw,
      'decoded' => json_decode($raw, true),
    ];
  }

  /**
   * Streaming request (SSE or NDJSON — both are newline-delimited).
   * Feeds COMPLETE lines only to $onLine(string $line); partial lines
   * are buffered across TCP chunks (FIX #4 from NvidiaAI::chatStream).
   * Returns ['code','rawCap'(first 64 KB),'bytes','error'] so callers can
   * build a proper error when the server answered non-200 before streaming.
   */
  protected function streamLines(
    string $url,
    array $headers,
    array $body,
    callable $onLine,
    int $timeout = 300,
    int $idleAbortSec = 90
  ): array {
    $ch  = curl_init($url);
    $buf = '';
    $cap = '';
    $bytes = 0;

    curl_setopt_array($ch, [
      CURLOPT_POST           => true,
      CURLOPT_POSTFIELDS     => json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
      CURLOPT_HTTPHEADER     => array_merge($this->headerLines($headers), ['Accept: text/event-stream']),
      CURLOPT_RETURNTRANSFER => false,
      CURLOPT_TIMEOUT        => max(30, $timeout),
      CURLOPT_CONNECTTIMEOUT => 10,
      CURLOPT_LOW_SPEED_LIMIT => 1,
      CURLOPT_LOW_SPEED_TIME  => max(30, $idleAbortSec),
      CURLOPT_WRITEFUNCTION  => function ($ch, $data) use (&$buf, &$cap, &$bytes, $onLine) {
        $bytes += strlen($data);
        if (strlen($cap) < 65536) $cap .= substr($data, 0, 65536 - strlen($cap));
        $buf .= $data;
        while (($nl = strpos($buf, "\n")) !== false) {
          $line = substr($buf, 0, $nl);
          $buf  = substr($buf, $nl + 1);
          $onLine(rtrim($line, "\r"));
        }
        return strlen($data);
      },
    ]);
    $this->applyCa($ch);
    $ok   = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $cerr = (string) curl_error($ch);
    curl_close($ch);

    // Flush a final unterminated line.
    if (trim($buf) !== '') {
      $onLine(rtrim($buf, "\r"));
      $buf = '';
    }
    if ($ok === false && $bytes === 0) {
      throw new \RuntimeException($this->transportError($cerr));
    }
    return ['code' => $code, 'rawCap' => $cap, 'bytes' => $bytes, 'error' => $cerr];
  }

  /** Friendly transport-failure text, prefixed with the provider label. */
  protected function transportError(string $curlErr): string
  {
    $l = '[' . $this->label() . '] ';
    $e = strtolower($curlErr);
    $host = $this->host();
    if ($host === '') return $l . 'No Base URL configured.';
    if (
      str_contains($e, 'couldnt resolve') || str_contains($e, 'could not resolve')
      || str_contains($e, 'name or service not known') || str_contains($e, 'nodename')
    ) {
      return $l . 'Cannot reach ' . $host . ' — address lookup failed. Check the Base URL and your connection.';
    }
    if (str_contains($e, 'refused')) {
      return $l . 'Cannot reach ' . $host . ' — connection refused. Is the server running?';
    }
    if (str_contains($e, 'timed out') || str_contains($e, 'timeout')) {
      return $l . 'Connection to ' . $host . ' timed out.';
    }
    if (str_contains($e, 'certificate') || str_contains($e, 'ssl') || str_contains($e, 'tls')) {
      return $l . 'TLS certificate error talking to ' . $host . ' (' . $curlErr . ')';
    }
    return $l . 'Cannot reach ' . $host . ' (' . $curlErr . ')';
  }

  /** Map an HTTP error status onto friendly text, prefixed "[<label>] ". */
  protected function mapHttpError(int $code, string $raw): string
  {
    $msg = $this->extractErrorMessage($raw);
    $l = '[' . $this->label() . '] ';
    if ($code === 401) return $l . 'Invalid API key. Check your key in AI settings.' . $this->errMsg($msg);
    if ($code === 403) return $l . 'Access denied (HTTP 403) — your key may lack permission for this model.' . $this->errMsg($msg);
    if ($code === 404) return $l . 'Not found (HTTP 404) — check the Base URL and model name.' . $this->errMsg($msg);
    if ($code === 429) return $l . 'Rate limit reached. Wait a moment and try again.' . $this->errMsg($msg);
    if ($code >= 500) return $l . 'Upstream provider error (HTTP ' . $code . ').' . $this->errMsg($msg);
    return $l . 'API error (HTTP ' . $code . ').' . $this->errMsg($msg);
  }

  private function errMsg(string $m): string
  {
    return $m !== '' ? ' ' . $m : '';
  }

  /** Best-effort extraction of a provider-supplied error message. */
  protected function extractErrorMessage(string $raw): string
  {
    $d = json_decode($raw, true);
    if (!is_array($d)) return '';
    $msg = $d['error']['message'] ?? null;
    if (is_array($msg)) $msg = $msg[0]['text'] ?? '';
    if (!is_string($msg) || $msg === '') $msg = is_string($d['error'] ?? null) ? $d['error'] : '';
    if ($msg === '') $msg = (string) ($d['message'] ?? '');
    if ($msg === '') $msg = (string) ($d['detail'] ?? '');
    if ($msg === '' && isset($d[0]['error']['message'])) $msg = (string) $d[0]['error']['message'];
    return trim((string) $msg);
  }

  /* ═══════════════════════════════════════════════════════════
     CANONICAL TOOL-CALL NORMALIZATION
    ═══════════════════════════════════════════════════════════ */

  protected function newCallId(): string
  {
    return 'call_' . bin2hex(random_bytes(8));
  }

  /**
   * Normalize whatever a wire returned into the canonical tool_calls shape:
   *   [['id','type'=>'function','function'=>['name','arguments'=>ARRAY]], …]
   * Accepts OpenAI fragments (arguments as JSON string), native objects
   * (stdClass), and flat {name,arguments} shapes.
   */
  protected function canonCalls(array $raw): array
  {
    $out = [];
    foreach ($raw as $tc) {
      if (!is_array($tc)) continue;
      $fn = is_array($tc['function'] ?? null) ? $tc['function'] : [];
      $args = $fn['arguments'] ?? ($tc['arguments'] ?? []);
      if (is_string($args)) {
        $d = json_decode($args, true);
        $args = is_array($d) ? $d : [];
      } elseif ($args instanceof \stdClass) {
        $args = (array) $args;
      }
      if (!is_array($args)) $args = [];
      $id = (string) ($tc['id'] ?? '');
      if ($id === '') $id = $this->newCallId();
      $name = (string) ($fn['name'] ?? ($tc['name'] ?? ''));
      if ($name === '') continue;
      $out[] = ['id' => $id, 'type' => 'function', 'function' => ['name' => $name, 'arguments' => $args]];
    }
    return $out;
  }

  /** Pretty-print a model id: "meta/llama-3.3-70b-instruct" → "Llama 3.3 70b Instruct". */
  protected function prettyModelName(string $id): string
  {
    $parts = explode('/', $id, 2);
    $name = $parts[1] ?? $parts[0];
    $name = str_replace(['-', '_'], ' ', $name);
    return ucwords($name);
  }

  /** True when a model id looks like a non-chat model (embed/rerank/…). */
  protected function isNonChatModel(string $id): bool
  {
    $lower = strtolower($id);
    foreach (['embed', 'rerank', 'ocr', 'guard', 'safety', 'translate', 'tts', 'image', 'video', 'audio', 'whisper', 'moderation'] as $s) {
      if (str_contains($lower, $s)) return true;
    }
    return false;
  }

  /** Merge the definition's curated fallback under freshly fetched models. */
  protected function curatedFallback(): array
  {
    $cur = $this->def['curated'] ?? null;
    if (!is_array($cur)) return [];
    $out = [];
    foreach ($cur as $row) {
      if (is_string($row)) {
        $out[] = ['id' => $row, 'label' => $this->prettyModelName($row), 'tag' => $this->label()];
      } elseif (is_array($row) && !empty($row['id'])) {
        $out[] = [
          'id'    => (string) $row['id'],
          'label' => (string) ($row['label'] ?? $this->prettyModelName((string) $row['id'])),
          'tag'   => (string) ($row['tag'] ?? $this->label()),
        ];
      }
    }
    return $out;
  }

  /* ═══════════════════════════════════════════════════════════
LIVE-MODEL CACHE (15 min TTL)
A refresh=1 fetch stores the real provider list here so cheap
callers (status(), the header model select) show live models
without hitting the provider on every poll.
═══════════════════════════════════════════════════════════ */
  protected function modelCachePath(): string
  {
    return sys_get_temp_dir() . '/quirky_ai_models_'
      . md5($this->id() . '|' . $this->baseUrl()) . '.json';
  }
  /** @return array|null fresh cached live model rows, or null */
  protected function readModelCache(int $ttlSec = 900): ?array
  {
    $p = $this->modelCachePath();
    if (!is_file($p)) return null;
    if (time() - (int) @filemtime($p) > $ttlSec) return null;
    $d = json_decode((string) @file_get_contents($p), true);
    return (is_array($d) && !empty($d)) ? $d : null;
  }
  protected function writeModelCache(array $models): void
  {
    @file_put_contents($this->modelCachePath(), json_encode($models), LOCK_EX);
  }
/* ═══════════════════════════════════════════════════════════
DEFAULT STATUS + CONNECTION TEST
═══════════════════════════════════════════════════════════ */

  /** Does this wire have a live HTTP model-list endpoint? (Anthropic: no → curated only) */
  protected function hasHttpModelList(): bool
  {
    return true;
  }

  public function status(): array
  {
    $models = [];
    try {
      $models = $this->listModels(false);
    } catch (\Throwable) {
    }
    $hasKey = $this->hasKey();
    // Policy carried over from NvidiaAI: a cloud provider is "ready" when
    // credentials exist; keyless local wires are ready unconditionally.
    $ready = $hasKey || $this->keyOptional();
    $active = (string) ($this->def['model'] ?? '');
    if ($active === '') {
      foreach ($models as $m) {
        $active = (string) ($m['id'] ?? '');
        break;
      }
    }
    $lt = is_array($this->def['lastTest'] ?? null) ? $this->def['lastTest'] : null;
    return [
      'provider'        => $this->id(),
      'label'           => $this->label(),
      'wire'            => $this->wire(),
      'preset'          => $this->def['preset'] ?? null,
      'online'          => $ready,
      'modelLoaded'     => $ready && $active !== '',
      'availableModels' => $models,
      'activeModel'     => $active,
      'hasKey'          => $hasKey,
      'maskedKey'       => $this->maskedKey(),
      'baseUrl'         => $this->baseUrl(),
      'keyOptional'     => $this->keyOptional(),
      'capabilities'    => $this->capabilities(),
      'testedAt'        => $lt['at'] ?? null,
      'lastOk'          => $lt['ok'] ?? null,
      'lastError'       => $lt['error'] ?? null,
    ];
  }

  /**
   * Default probe: prefer a FREE models-list GET (validates connectivity +
   * auth without spending tokens); fall back to a minimal 1-token chat when
   * the list endpoint is missing/unavailable.
   */
  public function testConnection(): array
  {
    $t0 = microtime(true);
    $latency = static fn(): int => (int) round((microtime(true) - $t0) * 1000);
    $firstError = null;
    if ($this->hasHttpModelList()) {
      try {
        $this->requireKey();
        $models = $this->listModels(true);
        if (!empty($models)) {
          return ['ok' => true, 'latencyMs' => $latency(), 'detail' => count($models) . ' models available'];
        }
      } catch (\Throwable $e) {
        $firstError = $e;
      }
    }
    // Minimal chat fallback — proves auth AND generation in one shot.
    try {
      $model = (string) ($this->def['model'] ?? '');
      if ($model === '') {
        foreach ($this->curatedFallback() as $m) {
          $model = (string) $m['id'];
          break;
        }
      }
      if ($model === '') {
        throw new \RuntimeException('[' . $this->label() . '] Set a model first — nothing to test.');
      }
      $r = $this->chat([['role' => 'user', 'content' => 'ping']], $model, []);
      $detail = trim((string) ($r['content'] ?? ''));
      return [
        'ok'        => true,
        'latencyMs' => $latency(),
        'detail'    => $detail !== '' ? mb_substr($detail, 0, 80) : 'model replied',
      ];
    } catch (\Throwable $e) {
      $err = $e->getMessage();
      if (strlen($err) > 300) $err = substr($err, 0, 300) . '…';
      return [
        'ok'        => false,
        'latencyMs' => $latency(),
        'error'     => $err !== '' ? $err : ($firstError?->getMessage() ?? 'Unknown failure'),
      ];
    }
  }
}
