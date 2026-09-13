<?php

declare(strict_types=1);
/**
 * ═══════════════════════════════════════════════════════════════════════════
 *  QUIRKY IDE — ANTHROPIC MESSAGES API WIRE ADAPTER  (v9)
 * ═══════════════════════════════════════════════════════════════════════════
 *
 *  Speaks POST {baseUrl}/messages  (headers: x-api-key + anthropic-version).
 *
 *  Translation from the canonical form:
 *    • system messages      → hoisted into ONE top-level "system" string
 *    • user                 → {role:'user',   content:[{type:'text',text}]}
 *    • assistant.tool_calls → content blocks {type:'tool_use',id,name,input}
 *    • consecutive role:'tool' results are grouped into exactly ONE
 *      following user message of {type:'tool_result'} blocks — Anthropic
 *      rejects orphaned/unpaired tool_results.
 *    • max_tokens is REQUIRED → max(1024, num_predict knob)
 *    • tools → [{name,description,input_schema}]
 *
 *  Streaming (SSE): message_start · content_block_start (supplies tool ids)
 *    · content_block_delta (text_delta → onDelta; input_json_delta →
 *      accumulate partial_json per block index) · message_delta
 *      (stop_reason==='tool_use' → assemble → onToolCall) · message_stop.
 *
 *  Models: curated static list + free-text always allowed.
 *
 *  EXPOSES: AiAdapterAnthropic
 * ═══════════════════════════════════════════════════════════════════════════
 */

require_once __DIR__ . '/AiProvider.php';

class AiAdapterAnthropic extends AiAdapterBase
{
  const WIRE = 'anthropic';
  const VERSION = '2023-06-01';

  protected function hasHttpModelList(): bool
  {
    return false; // curated static list; free-text model entry always allowed
  }

  /* ═══════════════════════════════════════════════════════════
     CANONICAL → ANTHROPIC SERIALIZATION
    ═══════════════════════════════════════════════════════════ */

  /**
   * @return array{system:string,messages:array}
   */
  protected function translate(array $messages): array
  {
    $system = [];
    $out = [];
    $pendingResults = [];   // tool_result blocks waiting to be flushed as ONE user msg

    $flushResults = function () use (&$pendingResults, &$out) {
      if (empty($pendingResults)) return;
      // Group ALL consecutive tool results into a single user turn — an
      // orphaned or split tool_result would be rejected by the API.
      $out[] = ['role' => 'user', 'content' => array_values($pendingResults)];
      $pendingResults = [];
    };

    foreach ($messages as $m) {
      if (!is_array($m)) continue;
      $role = (string) ($m['role'] ?? 'user');

      if ($role === 'system') {
        $t = trim((string) ($m['content'] ?? ''));
        if ($t !== '') $system[] = $t;
        continue;
      }

      if ($role === 'tool') {
        $pendingResults[] = [
          'type'        => 'tool_result',
          'tool_use_id' => (string) ($m['tool_call_id'] ?? ''),
          'content'     => (string) ($m['content'] ?? ''),
        ];
        continue;
      }

      // Any non-tool message first flushes dangling results (orphan guard:
      // e.g. history trimmed so a user msg follows tool results directly).
      if ($role === 'user') {
        $flushResults();
        $blocks = [];
        foreach ($this->splitUserContent($m) as $part) {
          $blocks[] = ['type' => 'text', 'text' => $part];
        }
        if (empty($blocks)) $blocks[] = ['type' => 'text', 'text' => '(empty message)'];
        $out[] = ['role' => 'user', 'content' => $blocks];
        continue;
      }

      if ($role === 'assistant') {
        $flushResults();
        $blocks = [];
        $text = (string) ($m['content'] ?? '');
        if ($text !== '') $blocks[] = ['type' => 'text', 'text' => $text];
        if (!empty($m['tool_calls']) && is_array($m['tool_calls'])) {
          foreach ($m['tool_calls'] as $tc) {
            if (!is_array($tc)) continue;
            $fn   = is_array($tc['function'] ?? null) ? $tc['function'] : [];
            $args = $fn['arguments'] ?? [];
            if ($args instanceof \stdClass) $args = (array) $args;
            if (is_string($args)) {
              $d = json_decode($args, true);
              $args = is_array($d) ? $d : [];
            }
            if (!is_array($args)) $args = [];
            $id = (string) ($tc['id'] ?? '');
            if ($id === '') $id = $this->newCallId();
            $blocks[] = [
              'type'  => 'tool_use',
              'id'    => $id,
              'name'  => (string) ($fn['name'] ?? ''),
              'input' => $args ?: new \stdClass(),
            ];
          }
        }
        if (empty($blocks)) $blocks[] = ['type' => 'text', 'text' => '(no content)'];
        $out[] = ['role' => 'assistant', 'content' => $blocks];
      }
    }
    $flushResults();

    return [
      'system'   => implode("\n\n", $system),
      'messages' => $out,
    ];
  }

  /** Flatten canonical user messages into text parts (images unsupported). */
  private function splitUserContent(array $m): array
  {
    $c = $m['content'] ?? '';
    if (is_array($c)) {
      $parts = [];
      foreach ($c as $p) {
        if (is_string($p)) $parts[] = $p;
        elseif (is_array($p) && isset($p['text']) && is_string($p['text'])) $parts[] = $p['text'];
      }
      return $parts;
    }
    $s = (string) $c;
    return $s !== '' ? [$s] : [];
  }

  protected function buildBody(array $messages, string $model, array $tools, bool $stream, ?int $maxTokens = null): array
  {
    $t = $this->translate($messages);
    $body = [
      'model'      => $model,
      'max_tokens' => $maxTokens ?? max(1024, $this->iCfg('num_predict', 1200)),
      'messages'   => $t['messages'],
    ];
    if ($t['system'] !== '') $body['system'] = $t['system'];
    $temperature = $this->fCfg('temperature', 0.2);
    if ($temperature > 0) $body['temperature'] = $temperature;
    $topP = $this->fCfg('top_p', 0.9);
    if ($topP > 0 && $topP < 1) $body['top_p'] = $topP;
    if (!empty($tools)) $body['tools'] = $this->translateTools($tools);
    if ($stream) $body['stream'] = true;
    return $body;
  }

  /** Canonical OpenAI function defs → Anthropic tool shape. */
  protected function translateTools(array $tools): array
  {
    $out = [];
    foreach ($tools as $t) {
      if (!is_array($t)) continue;
      $fn = is_array($t['function'] ?? null) ? $t['function'] : $t;
      $schema = is_array($fn['parameters'] ?? null) ? $fn['parameters'] : ['type' => 'object', 'properties' => []];
      $out[] = [
        'name'         => (string) ($fn['name'] ?? ''),
        'description'  => (string) ($fn['description'] ?? ''),
        'input_schema' => $schema,
      ];
    }
    return $out;
  }

  private function headers(bool $json = true): array
  {
    $h = [
      'x-api-key'         => $this->apiKey(),
      'anthropic-version' => self::VERSION,
    ];
    if ($json) $h['Content-Type'] = 'application/json';
    foreach ($this->extraHeaders() as $k => $v) $h[$k] = $v;
    return $h;
  }

  /* ═══════════════════════════════════════════════════════════
     CHAT (blocking)
    ═══════════════════════════════════════════════════════════ */

  public function chat(array $messages, string $model, array $tools = []): array
  {
    $this->requireKey();
    $res = $this->httpJson(
      $this->baseUrl() . '/messages',
      $this->headers(),
      $this->buildBody($messages, $model, $tools, false),
      max(60, $this->iCfg('timeout', 0) > 0 ? $this->iCfg('timeout', 120) : 120)
    );
    if ($res['code'] >= 400 || isset($res['decoded']['type']) && $res['decoded']['type'] === 'error') {
      throw new \RuntimeException($this->mapHttpError(max($res['code'], 400), $res['raw']));
    }
    $d = $res['decoded'];
    if (!is_array($d) || !isset($d['content'])) {
      throw new \RuntimeException('[' . $this->label() . '] Unexpected response from the Messages API.');
    }
    $content = '';
    $calls = [];
    foreach (is_array($d['content']) ? $d['content'] : [] as $block) {
      if (!is_array($block)) continue;
      switch ($block['type'] ?? '') {
        case 'text':
          $content .= (string) ($block['text'] ?? '');
          break;
        case 'tool_use':
          $calls[] = [
            'id'       => (string) ($block['id'] ?? '') ?: $this->newCallId(),
            'type'     => 'function',
            'function' => [
              'name'      => (string) ($block['name'] ?? ''),
              'arguments' => is_array($block['input'] ?? null) ? $block['input'] : [],
            ],
          ];
          break;
      }
    }
    return ['content' => $content, 'tool_calls' => $this->canonCalls($calls)];
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

    $content   = '';
    $sawData   = false;
    $blocks    = [];   // index → ['type','id','name','json'=>string]
    $assembled = [];
    $toolFired = false;

    $assembleCalls = function () use (&$blocks, &$assembled) {
      $out = [];
      foreach ($blocks as $b) {
        if (($b['type'] ?? '') !== 'tool_use') continue;
        $input = json_decode($b['json'] !== '' ? $b['json'] : '{}', true);
        $out[] = [
          'id'       => (string) ($b['id'] ?? '') ?: $this->newCallId(),
          'type'     => 'function',
          'function' => [
            'name'      => (string) ($b['name'] ?? ''),
            'arguments' => is_array($input) ? $input : [],
          ],
        ];
      }
      return $out;
    };

    $processLine = function (string $line) use (&$content, &$sawData, &$blocks, &$assembled, &$toolFired, $onDelta, $onToolCall, $assembleCalls) {
      $line = ltrim($line);
      if ($line === '' || strpos($line, 'data:') !== 0) return;  // event:/ping lines ignored
      $json = trim(substr($line, 5));
      if ($json === '' || $json === '[DONE]') return;
      $j = json_decode($json, true);
      if (!is_array($j)) return;
      $type = (string) ($j['type'] ?? '');

      switch ($type) {
        case 'error':
          throw new \RuntimeException($this->mapHttpError(500, $json));

        case 'content_block_start':
          $idx = is_numeric($j['index'] ?? null) ? (int) $j['index'] : 0;
          $cb  = is_array($j['content_block'] ?? null) ? $j['content_block'] : [];
          $blocks[$idx] = [
            'type' => (string) ($cb['type'] ?? 'text'),
            'id'   => (string) ($cb['id'] ?? ''),
            'name' => (string) ($cb['name'] ?? ''),
            'json' => '',
          ];
          break;

        case 'content_block_delta':
          $idx   = is_numeric($j['index'] ?? null) ? (int) $j['index'] : 0;
          $delta = is_array($j['delta'] ?? null) ? $j['delta'] : [];
          $dtype = (string) ($delta['type'] ?? '');
          if ($dtype === 'text_delta' && isset($delta['text']) && $delta['text'] !== '') {
            $sawData = true;
            $content .= $delta['text'];
            $onDelta($delta['text']);
          } elseif ($dtype === 'input_json_delta' && isset($delta['partial_json'])) {
            if (!isset($blocks[$idx])) $blocks[$idx] = ['type' => 'tool_use', 'id' => '', 'name' => '', 'json' => ''];
            $blocks[$idx]['json'] .= (string) $delta['partial_json'];
          }
          break;

        case 'message_delta':
          $stop = (string) (((is_array($j['delta'] ?? null) ? $j['delta'] : [])['stop_reason'] ?? ''));
          if ($stop === 'tool_use' && !$toolFired) {
            $assembled = $assembleCalls();
            if (!empty($assembled)) {
              $toolFired = true;
              $onToolCall($assembled);
            }
          }
          break;
      }
    };

    $res = $this->streamLines(
      $this->baseUrl() . '/messages',
      $this->headers(),
      $body,
      $processLine,
      max(120, $this->iCfg('timeout', 0) > 0 ? $this->iCfg('timeout', 300) : 300)
    );

    if (!$sawData && empty($assembled)) {
      // Nothing usable streamed — surface the HTTP/transport failure.
      $code = $res['code'] >= 400 ? $res['code'] : 502;
      throw new \RuntimeException($this->mapHttpError($code, $res['rawCap']));
    }

    // Stream ended without a message_delta stop_reason (aborted?) — still
    // deliver assembled calls if any input_json arrived.
    if (!$toolFired) {
      $assembled = $assembleCalls();
      if (!empty($assembled)) $onToolCall($assembled);
    }

    return [
      'content'    => $content,
      'tool_calls' => $this->canonCalls($assembled),
      'streamed'   => $sawData,
    ];
  }

  /* ═══════════════════════════════════════════════════════════
     MODELS — curated static (free-text always allowed)
    ═══════════════════════════════════════════════════════════ */

  public function listModels(bool $refresh = false): array
  {
    $curated = $this->curatedFallback();
    if (!empty($curated)) return $curated;
    return [
      ['id' => 'claude-sonnet-4-5', 'label' => 'Claude Sonnet 4.5', 'tag' => 'Anthropic · Balanced flagship'],
      ['id' => 'claude-opus-4-1',   'label' => 'Claude Opus 4.1',   'tag' => 'Anthropic · Most capable'],
      ['id' => 'claude-haiku-4-5',  'label' => 'Claude Haiku 4.5',  'tag' => 'Anthropic · Fastest'],
    ];
  }
}
