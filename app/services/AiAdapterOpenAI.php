<?php

declare(strict_types=1);
/**
 * ═══════════════════════════════════════════════════════════════════════════
 *  QUIRKY IDE — OPENAI-COMPATIBLE WIRE ADAPTER  (v9)
 * ═══════════════════════════════════════════════════════════════════════════
 *
 *  Speaks POST {baseUrl}/chat/completions — the dialect shared by OpenAI,
 *  NVIDIA NIM, Groq, DeepSeek, Together, OpenRouter, LM Studio and every
 *  other OpenAI-style endpoint. This adapter REPLACES the old literal-
 *  'nvidia' code paths (NvidiaAI::chat/chatStream + buildMessages' 'nvidia'
 *  quirks): the null-content-with-tool_calls rule, stringified arguments,
 *  id repair and the SSE partial-line reader all live HERE now.
 *
 *  Request:  {model, messages, stream, temperature, top_p,
 *             max_tokens:<num_predict knob>, tools?}
 *  Stream:   "data: {…}" SSE frames; delta.content → onDelta; indexed
 *            delta.tool_calls fragments accumulate; finish_reason==='tool_calls'
 *            → onToolCall(assembled).
 *
 *  EXPOSES: AiAdapterOpenAI
 * ═══════════════════════════════════════════════════════════════════════════
 */

require_once __DIR__ . '/AiProvider.php';

class AiAdapterOpenAI extends AiAdapterBase
{
  const WIRE = 'openai';

  /* ═══════════════════════════════════════════════════════════
     CANONICAL → OPENAI SERIALIZATION
    ═══════════════════════════════════════════════════════════ */

  protected function serializeMessages(array $messages): array
  {
    $out = [];
    foreach ($messages as $m) {
      if (!is_array($m)) continue;
      $role   = (string) ($m['role'] ?? 'user');
      $msg    = ['role' => $role];

      if ($role === 'assistant' && !empty($m['tool_calls'])) {
        // Wire quirk (was keyed on literal 'nvidia'): assistant content must be
        // NULL when tool_calls are present, arguments must be JSON strings.
        $calls = [];
        foreach ($m['tool_calls'] as $tc) {
          if (!is_array($tc)) continue;
          $fn   = is_array($tc['function'] ?? null) ? $tc['function'] : [];
          $args = $fn['arguments'] ?? [];
          if (!is_string($args)) {
            if ($args instanceof \stdClass) $args = (array) $args;
            $args = json_encode(is_array($args) ? $args : [], JSON_UNESCAPED_SLASHES);
          }
          $id = (string) ($tc['id'] ?? '');
          if ($id === '') $id = $this->newCallId();
          $calls[] = [
            'id'       => $id,
            'type'     => 'function',
            'function' => [
              'name'      => (string) ($fn['name'] ?? ''),
              'arguments' => (string) $args,
            ],
          ];
        }
        $content = (string) ($m['content'] ?? '');
        $msg['content']   = $content !== '' ? $content : null;
        $msg['tool_calls'] = $calls;
      } elseif ($role === 'tool') {
        $msg['tool_call_id'] = (string) ($m['tool_call_id'] ?? '');
        $msg['name']         = (string) ($m['name'] ?? '');
        $msg['content']      = (string) ($m['content'] ?? '');
      } else {
        $msg['content'] = (string) ($m['content'] ?? '');
      }
      $out[] = $msg;
    }
    return $out;
  }

  protected function chatUrl(): string
  {
    return $this->baseUrl() . '/chat/completions';
  }

  protected function authHeaders(): array
  {
    $h = [];
    if ($this->hasKey()) $h['Authorization'] = 'Bearer ' . $this->apiKey();
    foreach ($this->extraHeaders() as $k => $v) $h[$k] = $v;
    return $h;
  }

  protected function buildBody(array $messages, string $model, array $tools, bool $stream): array
  {
    $body = [
      'model'       => $model,
      'messages'    => $this->serializeMessages($messages),
      'stream'      => $stream,
      'temperature' => $this->fCfg('temperature', 0.2),
      'top_p'       => $this->fCfg('top_p', 0.9),
      'max_tokens'  => max(64, $this->iCfg('num_predict', 1200)),
    ];
    if (!empty($tools)) $body['tools'] = $this->normalizeToolDefs($tools);
    return $body;
  }

  /** Canonical defs already match OpenAI's function shape — just enforce keys. */
  protected function normalizeToolDefs(array $tools): array
  {
    $out = [];
    foreach ($tools as $t) {
      if (!is_array($t)) continue;
      $fn = is_array($t['function'] ?? null) ? $t['function'] : $t;
      $out[] = [
        'type'     => 'function',
        'function' => [
          'name'        => (string) ($fn['name'] ?? ''),
          'description' => (string) ($fn['description'] ?? ''),
          'parameters'  => is_array($fn['parameters'] ?? null) ? $fn['parameters'] : ['type' => 'object', 'properties' => []],
        ],
      ];
    }
    return $out;
  }

  /* ═══════════════════════════════════════════════════════════
     CHAT (blocking)
    ═══════════════════════════════════════════════════════════ */

  public function chat(array $messages, string $model, array $tools = []): array
  {
    $this->requireKey();
    $tries = 0;
    do {
      $res = $this->httpJson(
        $this->chatUrl(),
        array_merge($this->authHeaders(), ['Content-Type' => 'application/json', 'Accept' => 'application/json']),
        $this->buildBody($messages, $model, $tools, false),
        max(60, $this->iCfg('timeout', 0) > 0 ? $this->iCfg('timeout', 120) : 120)
      );
      // ★ FREE-MODEL RETRY: one quiet retry on rate-limit / upstream 5xx.
      $retry = $tries === 0 && ($res['code'] === 429 || $res['code'] >= 500);
      if ($retry) sleep(2);
      $tries++;
    } while ($retry);
    if ($res['code'] >= 400) throw new \RuntimeException($this->mapHttpError($res['code'], $res['raw']));
    $d = $res['decoded'];
    if (!is_array($d)) throw new \RuntimeException('[' . $this->label() . '] Unexpected (non-JSON) response from the API.');
    if (isset($d['error'])) throw new \RuntimeException($this->mapHttpError($res['code'], $res['raw']));
    $choice = is_array($d['choices'][0] ?? null) ? $d['choices'][0] : [];
    $msg    = is_array($choice['message'] ?? null) ? $choice['message'] : [];
    return [
      'content'    => (string) ($msg['content'] ?? ''),
      'tool_calls' => $this->canonCalls(is_array($msg['tool_calls'] ?? null) ? $msg['tool_calls'] : []),
    ];
  }

  /* ═══════════════════════════════════════════════════════════
     CHAT STREAMING (SSE)
    ═══════════════════════════════════════════════════════════ */

  public function chatStream(
    array $messages,
    string $model,
    array $tools,
    callable $onDelta,
    callable $onToolCall
  ): array {
    $this->requireKey();
    $body = $this->buildBody($messages, $model, $tools, true);

    $contentBuffer = '';
    $toolCallsBuf  = [];   // indexed fragments
    $sawData  = false;

    $processLine = function (string $line) use (&$contentBuffer, &$toolCallsBuf, &$sawData, $onDelta, $onToolCall) {
      $line = trim($line);
      if ($line === '' || $line === 'data: [DONE]' || $line === 'data:[DONE]') return;
      if (strpos($line, 'data:') !== 0) return;   // ignore event:/comments/keepalives
      $json = trim(substr($line, 5));
      if ($json === '') return;
      $chunk = json_decode($json, true);
      if (!is_array($chunk)) return;
      if (isset($chunk['error'])) {
        throw new \RuntimeException($this->mapHttpError(500, $json));
      }
      $choice = is_array($chunk['choices'][0] ?? null) ? $chunk['choices'][0] : [];
      $delta  = is_array($choice['delta'] ?? null) ? $choice['delta'] : [];

      if (isset($delta['content']) && is_string($delta['content']) && $delta['content'] !== '') {
        $sawData = true;
        $contentBuffer .= $delta['content'];
        $onDelta($delta['content']);
      }
      if (!empty($delta['tool_calls']) && is_array($delta['tool_calls'])) {
        foreach ($delta['tool_calls'] as $tc) {
          if (!is_array($tc)) continue;
          $idx = is_numeric($tc['index'] ?? null) ? (int) $tc['index'] : 0;
          if (!isset($toolCallsBuf[$idx])) {
            $toolCallsBuf[$idx] = ['id' => '', 'type' => 'function', 'function' => ['name' => '', 'arguments' => '']];
          }
          if (!empty($tc['id']) && is_string($tc['id'])) $toolCallsBuf[$idx]['id'] = $tc['id'];
          if (isset($tc['function']['name']) && is_string($tc['function']['name'])) {
            $toolCallsBuf[$idx]['function']['name'] .= $tc['function']['name'];
          }
          if (isset($tc['function']['arguments']) && is_string($tc['function']['arguments'])) {
            $toolCallsBuf[$idx]['function']['arguments'] .= $tc['function']['arguments'];
          }
        }
      }
      if (($choice['finish_reason'] ?? null) === 'tool_calls' && !empty($toolCallsBuf)) {
        $assembled = [];
        foreach (array_values($toolCallsBuf) as $tc) {
          $assembled[] = [
            'id'       => $tc['id'] !== '' ? $tc['id'] : $this->newCallId(),
            'type'     => 'function',
            'function' => ['name' => $tc['function']['name'], 'arguments' => $tc['function']['arguments']],
          ];
        }
        // Fire with CANONICAL calls (arguments as arrays), matching the
        // contract every other wire honors.
        $onToolCall($this->canonCalls($assembled));
      }
    };

    $tries = 0;
    do {
      $res = $this->streamLines(
        $this->chatUrl(),
        array_merge($this->authHeaders(), ['Content-Type' => 'application/json']),
        $body,
        $processLine,
        max(120, $this->iCfg('timeout', 0) > 0 ? $this->iCfg('timeout', 300) : 300)
      );
      // ★ FREE-MODEL RETRY: only when NOTHING was delivered yet.
      $retry = $tries === 0
        && ($res['code'] === 429 || $res['code'] >= 500)
        && !$sawData && empty($toolCallsBuf);
      if ($retry) sleep(2);
      $tries++;
    } while ($retry);
    if ($res['code'] >= 400 && !$sawData && empty($toolCallsBuf)) {
      throw new \RuntimeException($this->mapHttpError($res['code'], $res['rawCap']));
    }
    if ($res['code'] >= 400 && $contentBuffer === '' && empty($toolCallsBuf)) {
      throw new \RuntimeException($this->mapHttpError($res['code'], $res['rawCap']));
    }

    $final = [];
    foreach (array_values($toolCallsBuf) as $tc) {
      $final[] = [
        'id'       => $tc['id'] !== '' ? $tc['id'] : $this->newCallId(),
        'type'     => 'function',
        'function' => ['name' => $tc['function']['name'], 'arguments' => $tc['function']['arguments']],
      ];
    }
    return [
      'content'    => $contentBuffer,
      'tool_calls' => $this->canonCalls($final),
      'streamed'   => $sawData,
    ];
  }

  /* ═══════════════════════════════════════════════════════════
     MODELS — GET {base}/models (Bearer), curated fallback merge
     (port of NvidiaAI::fetchLiveModels + CURATED_MODELS policy)
    ═══════════════════════════════════════════════════════════ */

  public function listModels(bool $refresh = false): array
  {
    $curated = $this->curatedFallback();
    // Cheap path (status polls / header select): fresh live cache wins
    // over the curated samples; no network probe here.
    if (!$refresh) {
      return $this->readModelCache() ?? $curated;
    }
    if ($this->baseUrl() === '') return $curated;
    try {
      // /models is PUBLIC on several hosts (OpenRouter) — auth headers
      // are added automatically when a key exists; a 401 on keyless
      // private hosts simply falls back to cache/curated below.
      $res = $this->httpJson(
        $this->baseUrl() . '/models',
        array_merge($this->authHeaders(), ['Accept' => 'application/json']),
        null,
        20
      );
      if ($res['code'] >= 400) return $this->readModelCache() ?? $curated;
      $rows = is_array($res['decoded']['data'] ?? null) ? $res['decoded']['data'] : [];
      if (empty($rows)) return $this->readModelCache() ?? $curated;
      $byCurated = [];
      foreach ($curated as $c) $byCurated[$c['id']] = $c;
      $live = [];
      foreach ($rows as $m) {
        $id = is_string($m) ? $m : (string) ($m['id'] ?? '');
        if ($id === '' || $this->isNonChatModel($id)) continue;
        $meta = $byCurated[$id] ?? null;
        // Prefer the provider's own human-readable name (OpenRouter
        // sends "name") over the guessed pretty-print of the id.
        $live[] = $meta ?? [
          'id'    => $id,
          'label' => (is_array($m) && !empty($m['name']))
            ? (string) $m['name']
            : $this->prettyModelName($id),
          'tag'   => $this->label(),
        ];
      }
      if (!empty($live)) {
        $this->writeModelCache($live);
        return $live;
      }
      return $this->readModelCache() ?? $curated;
    } catch (\Throwable) {
      return $this->readModelCache() ?? $curated;
    }
  }
}
