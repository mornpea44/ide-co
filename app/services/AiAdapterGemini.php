<?php

declare(strict_types=1);
/**
 * ═══════════════════════════════════════════════════════════════════════════
 *  QUIRKY IDE — GOOGLE GEMINI WIRE ADAPTER  (v9)
 * ═══════════════════════════════════════════════════════════════════════════
 *
 *  Speaks POST {baseUrl}/models/{model}:generateContent?key=API_KEY
 *  (streaming: :streamGenerateContent?alt=sse&key=…).
 *
 *  Translation from the canonical form:
 *    • system messages      → top-level systemInstruction.parts[]
 *    • user                 → {role:'user',  parts:[{text}]}
 *    • assistant            → {role:'model', parts:[{text}|{functionCall}]}
 *    • role:'tool' results  → user turn with a {functionResponse:{name,response}}
 *                             part. Gemini has NO tool_call ids, so stable ids
 *                             (call_<hex>) are synthesized when converting
 *                             functionCall → canonical calls, and the id→name
 *                             pairing is remembered while walking THIS request's
 *                             history so functionResponse parts pair correctly.
 *    • tools                → [{functionDeclarations:[{name,description,parameters}]}]
 *    • sampling             → generationConfig{temperature,topP,maxOutputTokens}
 *
 *  Streaming: SSE "data:" chunks; candidates[0].content.parts[].text →
 *  onDelta; functionCall parts are collected and delivered together via
 *  onToolCall at the end of the stream.
 *
 *  Models: GET {baseUrl}/models?key= filtered on supportedGenerationMethods.
 *
 *  EXPOSES: AiAdapterGemini
 * ═══════════════════════════════════════════════════════════════════════════
 */

require_once __DIR__ . '/AiProvider.php';

class AiAdapterGemini extends AiAdapterBase
{
  const WIRE = 'gemini';

  /* ═══════════════════════════════════════════════════════════
     CANONICAL → GEMINI SERIALIZATION
    ═══════════════════════════════════════════════════════════ */

  /**
   * @return array{systemParts:array,contents:array}
   */
  protected function translate(array $messages): array
  {
    $systemParts = [];
    $contents = [];
    $pairMap = [];        // canonical tool_call id → function name (this walk only)
    // Merge consecutive tool results into ONE user turn of functionResponse parts.
    $pendingResponses = [];

    $flushResponses = function () use (&$pendingResponses, &$contents) {
      if (empty($pendingResponses)) return;
      $contents[] = ['role' => 'user', 'parts' => array_values($pendingResponses)];
      $pendingResponses = [];
    };

    foreach ($messages as $m) {
      if (!is_array($m)) continue;
      $role = (string) ($m['role'] ?? 'user');

      if ($role === 'system') {
        $t = trim((string) ($m['content'] ?? ''));
        if ($t !== '') $systemParts[] = ['text' => $t];
        continue;
      }

      if ($role === 'tool') {
        $cid  = (string) ($m['tool_call_id'] ?? '');
        $name = $pairMap[$cid] ?? ((string) ($m['name'] ?? '') ?: 'tool');
        $raw  = (string) ($m['content'] ?? '');
        $decoded = json_decode($raw, true);
        $response = is_array($decoded) ? $decoded : ['result' => $raw];
        $pendingResponses[] = [
          'functionResponse' => [
            'name'     => $name,
            'response' => $response ?: new \stdClass(),
          ],
        ];
        continue;
      }

      $flushResponses();

      if ($role === 'user') {
        $parts = [];
        foreach ($this->textParts($m) as $txt) $parts[] = ['text' => $txt];
        if (!empty($parts)) $contents[] = ['role' => 'user', 'parts' => $parts];
        continue;
      }

      if ($role === 'assistant') {
        $parts = [];
        $text = (string) ($m['content'] ?? '');
        if ($text !== '') $parts[] = ['text' => $text];
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
            $name = (string) ($fn['name'] ?? '');
            $id   = (string) ($tc['id'] ?? '');
            if ($id === '') $id = $this->newCallId();
            if ($name === '') continue;
            $pairMap[$id] = $name;
            $parts[] = ['functionCall' => ['name' => $name, 'args' => $args ?: new \stdClass()]];
          }
        }
        if (!empty($parts)) $contents[] = ['role' => 'model', 'parts' => $parts];
      }
    }
    $flushResponses();

    return ['systemParts' => $systemParts, 'contents' => $contents];
  }

  private function textParts(array $m): array
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

  protected function buildBody(array $messages, array $tools): array
  {
    $t = $this->translate($messages);
    $body = ['contents' => $t['contents']];
    if (!empty($t['systemParts'])) $body['systemInstruction'] = ['parts' => $t['systemParts']];
    $gen = [
      'temperature'     => $this->fCfg('temperature', 0.2),
      'topP'            => $this->fCfg('top_p', 0.9),
      'maxOutputTokens' => max(64, $this->iCfg('num_predict', 1200)),
    ];
    $body['generationConfig'] = $gen;
    if (!empty($tools)) {
      $decls = [];
      foreach ($tools as $t2) {
        if (!is_array($t2)) continue;
        $fn = is_array($t2['function'] ?? null) ? $t2['function'] : $t2;
        $name = (string) ($fn['name'] ?? '');
        if ($name === '') continue;
        $decls[] = [
          'name'        => $name,
          'description' => (string) ($fn['description'] ?? ''),
          'parameters'  => is_array($fn['parameters'] ?? null) ? $fn['parameters'] : ['type' => 'object', 'properties' => []],
        ];
      }
      if (!empty($decls)) $body['tools'] = [['functionDeclarations' => $decls]];
    }
    return $body;
  }

  private function urlFor(string $model, bool $stream): string
  {
    $key = rawurlencode($this->apiKey());
    $base = $this->baseUrl();
    if ($base === '') throw new \RuntimeException('[' . $this->label() . '] No Base URL configured.');
    $method = $stream ? ':streamGenerateContent?alt=sse&key=' : ':generateContent?key=';
    return $base . '/models/' . rawurlencode($model) . $method . $key;
  }

  private function headers(): array
  {
    // Key goes in the query string (survives odd proxies); extra headers still honored.
    $h = ['Content-Type' => 'application/json'];
    foreach ($this->extraHeaders() as $k => $v) $h[$k] = $v;
    return $h;
  }

  /** Extract text + tool calls out of one candidate content parts list. */
  private function parseParts(array $parts, array &$calls): string
  {
    $text = '';
    foreach ($parts as $p) {
      if (!is_array($p)) continue;
      if (isset($p['text']) && is_string($p['text']) && $p['text'] !== '') {
        $text .= $p['text'];
      }
      if (isset($p['functionCall']) && is_array($p['functionCall'])) {
        $fc = $p['functionCall'];
        $args = $fc['args'] ?? [];
        if ($args instanceof \stdClass) $args = (array) $args;
        if (!is_array($args)) $args = [];
        $calls[] = [
          'id'       => $this->newCallId(),   // stable within this turn
          'type'     => 'function',
          'function' => ['name' => (string) ($fc['name'] ?? ''), 'arguments' => $args],
        ];
      }
    }
    return $text;
  }

  private function checkBlocked(array $d): void
  {
    $pf = is_array($d['promptFeedback'] ?? null) ? $d['promptFeedback'] : [];
    if (isset($pf['blockReason']) && is_string($pf['blockReason'])) {
      throw new \RuntimeException('[' . $this->label() . '] Request blocked (' . $pf['blockReason'] . ').');
    }
    if (empty($d['candidates'])) {
      $msg = $this->extractErrorMessage(json_encode($d));
      throw new \RuntimeException('[' . $this->label() . '] No candidates returned.' . ($msg !== '' ? ' ' . $msg : ''));
    }
  }

  /* ═══════════════════════════════════════════════════════════
     CHAT (blocking)
    ═══════════════════════════════════════════════════════════ */

  public function chat(array $messages, string $model, array $tools = []): array
  {
    $this->requireKey();
    $res = $this->httpJson(
      $this->urlFor($model, false),
      $this->headers(),
      $this->buildBody($messages, $tools),
      max(60, $this->iCfg('timeout', 0) > 0 ? $this->iCfg('timeout', 120) : 120)
    );
    if ($res['code'] >= 400) throw new \RuntimeException($this->mapHttpError($res['code'], $res['raw']));
    $d = $res['decoded'];
    if (!is_array($d)) throw new \RuntimeException('[' . $this->label() . '] Unexpected (non-JSON) response.');
    if (isset($d['error'])) throw new \RuntimeException($this->mapHttpError($res['code'], $res['raw']));
    $this->checkBlocked($d);
    $cand = is_array($d['candidates'][0] ?? null) ? $d['candidates'][0] : [];
    $content = is_array($cand['content'] ?? null) ? $cand['content'] : [];
    $parts = is_array($content['parts'] ?? null) ? $content['parts'] : [];
    $calls = [];
    $text = $this->parseParts($parts, $calls);
    return ['content' => $text, 'tool_calls' => $this->canonCalls($calls)];
  }

  /* ═══════════════════════════════════════════════════════════
     CHAT STREAMING (alt=sse)
    ═══════════════════════════════════════════════════════════ */

  public function chatStream(
    array $messages,
    string $model,
    array $tools,
    callable $onDelta,
    callable $onToolCall
  ): array {
    $this->requireKey();

    $content = '';
    $sawData = false;
    $calls   = [];

    $processLine = function (string $line) use (&$content, &$sawData, &$calls, $onDelta) {
      $line = ltrim($line);
      if ($line === '' || strpos($line, 'data:') !== 0) return;
      $json = trim(substr($line, 5));
      if ($json === '' || $json === '[DONE]') return;
      $j = json_decode($json, true);
      if (!is_array($j)) return;
      if (isset($j['error'])) throw new \RuntimeException($this->mapHttpError(500, $json));

      $cand = is_array($j['candidates'][0] ?? null) ? $j['candidates'][0] : [];
      $ccontent = is_array($cand['content'] ?? null) ? $cand['content'] : [];
      $parts = is_array($ccontent['parts'] ?? null) ? $ccontent['parts'] : [];
      $txt = '';
      $before = count($calls);
      $txt = $this->parseParts($parts, $calls);
      if ($txt !== '') {
        $sawData = true;
        $content .= $txt;
        $onDelta($txt);
      }
      if (count($calls) > $before) $sawData = true;
    };

    $res = $this->streamLines(
      $this->urlFor($model, true),
      $this->headers(),
      $this->buildBody($messages, $tools),
      $processLine,
      max(120, $this->iCfg('timeout', 0) > 0 ? $this->iCfg('timeout', 300) : 300)
    );

    if (($res['code'] >= 400 || empty($calls)) && !$sawData) {
      throw new \RuntimeException($this->mapHttpError($res['code'] >= 400 ? $res['code'] : 502, $res['rawCap']));
    }

    if (!empty($calls)) $onToolCall($calls);

    return [
      'content'    => $content,
      'tool_calls' => $this->canonCalls($calls),
      'streamed'   => $sawData,
    ];
  }

  /* ═══════════════════════════════════════════════════════════
     MODELS — GET {base}/models?key= (filter generateContent)
    ═══════════════════════════════════════════════════════════ */

  public function listModels(bool $refresh = false): array
  {
    $curated = $this->curatedFallback();
    if (!$refresh || !$this->hasKey()) return $this->readModelCache() ?? $curated;
    try {
      $res = $this->httpJson(
        $this->baseUrl() . '/models?key=' . rawurlencode($this->apiKey()) . '&pageSize=100',
        ['Accept' => 'application/json'],
        null,
        12
      );
      if ($res['code'] >= 400 || !is_array($res['decoded'])) return $curated;
      $rows = is_array($res['decoded']['models'] ?? null) ? $res['decoded']['models'] : [];
      $live = [];
      foreach ($rows as $m) {
        if (!is_array($m)) continue;
        $name = (string) ($m['name'] ?? '');
        if ($name === '') continue;
        if (str_starts_with($name, 'models/')) $name = substr($name, 7);
        $methods = is_array($m['supportedGenerationMethods'] ?? null) ? $m['supportedGenerationMethods'] : [];
        if (!in_array('generateContent', $methods, true)) continue;
        $live[] = [
          'id'    => $name,
          'label' => (string) ($m['displayName'] ?? $this->prettyModelName($name)),
          'tag'   => 'Gemini',
        ];
      }
      if (!empty($live)) $this->writeModelCache($live);
      return !empty($live) ? $live : ($this->readModelCache() ?? $curated);
    } catch (\Throwable) {
      return $this->readModelCache() ?? $curated;
    }
  }
}
