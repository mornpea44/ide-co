<?php

declare(strict_types=1);
/**
 * ═══════════════════════════════════════════════════════════════════════════
 *  QUIRKY IDE — OLLAMA NATIVE WIRE ADAPTER  (v9)
 * ═══════════════════════════════════════════════════════════════════════════
 *
 *  Speaks POST {baseUrl}/api/chat (NDJSON streaming) — the dialect of a
 *  local or LAN Ollama server. This is a phone app, so nothing DEFAULTS to
 *  it: an Ollama provider is just another user-entered Base URL target.
 *
 *  Request:  {model, messages, stream, keep_alive,
 *             options{temperature,top_p,repeat_penalty,num_predict,num_ctx},
 *             tools?}
 *  Stream:   one JSON object per line; message.content → onDelta;
 *            message.tool_calls accumulate; done:true ends the stream.
 *  Models:   GET {baseUrl}/api/tags → models[].name
 *
 *  Behavior ported verbatim from IdeAI::askOllama/httpPost/httpPostStream,
 *  including the exact friendly error text the frontend matches on:
 *    'Cannot reach Ollama at <url> — is `ollama serve` running?'
 *
 *  EXPOSES: AiAdapterOllama
 * ═══════════════════════════════════════════════════════════════════════════
 */

require_once __DIR__ . '/AiProvider.php';

class AiAdapterOllama extends AiAdapterBase
{
  const WIRE = 'ollama';

  /* ═══════════════════════════════════════════════════════════
     CANONICAL → OLLAMA SERIALIZATION (near-passthrough)
    ═══════════════════════════════════════════════════════════ */

  protected function serializeMessages(array $messages): array
  {
    $out = [];
    foreach ($messages as $m) {
      if (!is_array($m)) continue;
      $role = (string) ($m['role'] ?? 'user');
      $msg  = ['role' => $role];
      if ($role === 'assistant' && !empty($m['tool_calls'])) {
        $calls = [];
        foreach ($m['tool_calls'] as $tc) {
          if (!is_array($tc)) continue;
          $fn   = is_array($tc['function'] ?? null) ? $tc['function'] : [];
          $args = $fn['arguments'] ?? [];
          if ($args instanceof \stdClass) $args = (array) $args;
          if (!is_array($args)) {
            $d = json_decode((string) $args, true);
            $args = is_array($d) ? $d : [];
          }
          // Ollama historically expects arguments as an OBJECT.
          $call = [
            'function' => ['name' => (string) ($fn['name'] ?? ''), 'arguments' => $args ?: new \stdClass()],
          ];
          if (!empty($tc['id']) && is_string($tc['id'])) $call['id'] = $tc['id'];
          $calls[] = $call;
        }
        $msg['content']    = (string) ($m['content'] ?? '');
        $msg['tool_calls'] = $calls;
      } elseif ($role === 'tool') {
        $msg['content']      = (string) ($m['content'] ?? '');
        $msg['tool_call_id'] = (string) ($m['tool_call_id'] ?? '');
        $msg['name']         = (string) ($m['name'] ?? '');
      } else {
        $msg['content'] = (string) ($m['content'] ?? '');
      }
      $out[] = $msg;
    }
    return $out;
  }

  protected function buildBody(array $messages, string $model, array $tools, bool $stream): array
  {
    $body = [
      'model'      => $model,
      'messages'   => $this->serializeMessages($messages),
      'stream'     => $stream,
      'keep_alive' => (string) ($this->cfg('keep_alive', '10m') ?? '10m'),
      'options'    => [
        'temperature'    => $this->fCfg('temperature', 0.2),
        'top_p'          => $this->fCfg('top_p', 0.9),
        'repeat_penalty' => $this->fCfg('repeat_penalty', 1.08),
        'num_predict'    => max(64, $this->iCfg('num_predict', 1200)),
        'num_ctx'        => max(1024, $this->iCfg('num_ctx', 4096)),
      ],
    ];
    if (!empty($tools)) {
      $defs = [];
      foreach ($tools as $t) {
        if (!is_array($t)) continue;
        $fn = is_array($t['function'] ?? null) ? $t['function'] : $t;
        $defs[] = [
          'type'     => 'function',
          'function' => [
            'name'        => (string) ($fn['name'] ?? ''),
            'description' => (string) ($fn['description'] ?? ''),
            'parameters'  => is_array($fn['parameters'] ?? null) ? $fn['parameters'] : ['type' => 'object', 'properties' => []],
          ],
        ];
      }
      $body['tools'] = $defs;
    }
    return $body;
  }

  private function chatUrl(): string
  {
    return $this->baseUrl() . '/api/chat';
  }

  private function unreachable(): \RuntimeException
  {
    return new \RuntimeException(
      'Cannot reach Ollama at ' . $this->baseUrl() . ' — is `ollama serve` running?'
    );
  }

  /* ═══════════════════════════════════════════════════════════
     CHAT (blocking NDJSON single response)
    ═══════════════════════════════════════════════════════════ */

  public function chat(array $messages, string $model, array $tools = []): array
  {
    $res = $this->httpJson(
      $this->chatUrl(),
      ['Content-Type' => 'application/json'],
      $this->buildBody($messages, $model, $tools, false),
      max(60, $this->iCfg('timeout', 0) > 0 ? $this->iCfg('timeout', 120) : 120)
    );
    if ($res['code'] >= 400 || $res['raw'] === false || $res['raw'] === '') throw $this->unreachable();
    $d = $res['decoded'];
    if (!is_array($d)) throw $this->unreachable();
    if (isset($d['error']) && is_string($d['error'])) throw new \RuntimeException((string) $d['error']);
    if (!isset($d['message']) || !is_array($d['message'])) {
      throw new \RuntimeException('Unexpected response from Ollama.');
    }
    $msg = $d['message'];
    return [
      'content'    => (string) ($msg['content'] ?? ''),
      'tool_calls' => $this->canonCalls(is_array($msg['tool_calls'] ?? null) ? $msg['tool_calls'] : []),
    ];
  }

  /* ═══════════════════════════════════════════════════════════
     CHAT STREAMING (NDJSON)
    ═══════════════════════════════════════════════════════════ */

  public function chatStream(
    array $messages,
    string $model,
    array $tools,
    callable $onDelta,
    callable $onToolCall
  ): array {
    $content = '';
    $sawDelta = false;
    $acc = [];   // index → ['name','args','id']
    $done = false;

    $processLine = function (string $line) use (&$content, &$sawDelta, &$acc, &$done, $onDelta) {
      $line = trim($line);
      if ($line === '') return;
      $chunk = json_decode($line, true);
      if (!is_array($chunk)) return;
      if (isset($chunk['error'])) throw new \RuntimeException((string) $chunk['error']);
      $msg = is_array($chunk['message'] ?? null) ? $chunk['message'] : [];
      if (isset($msg['content']) && is_string($msg['content']) && $msg['content'] !== '') {
        $content .= $msg['content'];
        $sawDelta = true;
        $onDelta($msg['content']);
      }
      if (!empty($msg['tool_calls']) && is_array($msg['tool_calls'])) {
        foreach ($msg['tool_calls'] as $i => $tc) {
          if (!is_array($tc)) continue;
          $fn = is_array($tc['function'] ?? null) ? $tc['function'] : [];
          if (!isset($acc[$i])) $acc[$i] = ['name' => '', 'args' => null, 'id' => ''];
          if (!empty($fn['name'])) $acc[$i]['name'] = (string) $fn['name'];
          if (isset($fn['arguments'])) $acc[$i]['args'] = $fn['arguments'];
          if (!empty($tc['id'])) $acc[$i]['id'] = (string) $tc['id'];
        }
      }
      if (!empty($chunk['done'])) $done = true;
    };

    $res = $this->streamLines(
      $this->chatUrl(),
      ['Content-Type' => 'application/json', 'Accept' => 'application/x-ndjson'],
      $this->buildBody($messages, $model, $tools, true),
      $processLine,
      max(120, $this->iCfg('timeout', 0) > 0 ? $this->iCfg('timeout', 300) : 300)
    );

    if (!$sawDelta && empty($acc) && !$done) {
      // Never got a usable frame — surface reachability/HTTP failure.
      if ($res['code'] >= 400) throw $this->unreachable();
      if ($res['bytes'] === 0) throw $this->unreachable();
    }

    $final = [];
    foreach ($acc as $a) {
      $args = $a['args'];
      if ($args instanceof \stdClass) $args = (array) $args;
      if (is_string($args)) {
        $d = json_decode($args, true);
        $args = is_array($d) ? $d : [];
      }
      if (!is_array($args)) $args = [];
      $final[] = [
        'id'       => $a['id'],
        'function' => ['name' => $a['name'], 'arguments' => $args],
      ];
    }
    if (!empty($final)) $onToolCall($this->canonCalls($final));
    return [
      'content'    => $content,
      'tool_calls' => $this->canonCalls($final),
      'streamed'   => $sawDelta,
    ];
  }

  /* ═══════════════════════════════════════════════════════════
     MODELS + STATUS — GET {base}/api/tags (the liveness probe)
    ═══════════════════════════════════════════════════════════ */

  public function listModels(bool $refresh = false): array
  {
    // The tags fetch IS the probe for this wire — always live, throws when
    // unreachable so setModel() keeps its "Cannot reach Ollama" validation.
    try {
      $res = $this->httpJson($this->baseUrl() . '/api/tags', ['Accept' => 'application/json'], null, 8);
    } catch (\RuntimeException $e) {
      if (str_contains($e->getMessage(), '[' . $this->label() . ']')) {
        // Normalize transport errors to the legacy frontend-matched text.
        throw $this->unreachable();
      }
      throw $e;
    }
    if ($res['code'] >= 400 || !is_array($res['decoded'])) throw $this->unreachable();
    $rows = is_array($res['decoded']['models'] ?? null) ? $res['decoded']['models'] : [];
    $out = [];
    foreach ($rows as $m) {
      $name = (string) ($m['name'] ?? '');
      if ($name === '') continue;
      $size = isset($m['size']) && is_numeric($m['size']) ? (int) $m['size'] : null;
      $out[] = [
        'id'    => $name,
        'label' => $name,
        'tag'   => 'Ollama',
      ] + ($size !== null ? ['size' => $size] : []);
    }
    return $out;
  }

  public function status(): array
  {
    $tags = [];
    $online = false;
    try {
      $tags = $this->listModels(false);
      $online = true;
    } catch (\Throwable) {
    }
    $names = [];
    foreach ($tags as $t) $names[] = (string) ($t['id'] ?? '');

    $configured = trim((string) ($this->cfg('model', '') ?? ''));
    $active = (string) ($this->def['model'] ?? '');
    if ($active === '') $active = $configured;

    $lt = is_array($this->def['lastTest'] ?? null) ? $this->def['lastTest'] : null;
    return [
      'provider'        => $this->id(),
      'label'           => $this->label(),
      'wire'            => $this->wire(),
      'preset'          => $this->def['preset'] ?? null,
      'online'          => $online,
      'url'             => $this->baseUrl(),
      'baseUrl'         => $this->baseUrl(),
      'availableModels' => $names,
      'activeModel'     => $active,
      'modelLoaded'     => $online && $active !== ''
        && (in_array($active, $names, true) || $this->hasTagPrefix($names, $active)),
      'hasKey'          => false,
      'maskedKey'       => '',
      'keyOptional'     => true,
      'capabilities'    => $this->capabilities(),
      'testedAt'        => $lt['at'] ?? null,
      'lastOk'          => $lt['ok'] ?? null,
      'lastError'       => $lt['error'] ?? null,
    ];
  }

  private function hasTagPrefix(array $names, string $needle): bool
  {
    foreach ($names as $n) {
      if ($n !== '' && str_starts_with($n, $needle)) return true;
    }
    return false;
  }

  public function testConnection(): array
  {
    $t0 = microtime(true);
    try {
      $tags = $this->listModels(false);
      return [
        'ok'        => true,
        'latencyMs' => (int) round((microtime(true) - $t0) * 1000),
        'detail'    => count($tags) . ' model(s) pulled',
      ];
    } catch (\Throwable $e) {
      return [
        'ok'        => false,
        'latencyMs' => (int) round((microtime(true) - $t0) * 1000),
        'error'     => $e->getMessage(),
      ];
    }
  }
}
