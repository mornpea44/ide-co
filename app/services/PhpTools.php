<?php

declare(strict_types=1);
/**
 * ═══════════════════════════════════════════════════════════════════════════
 *  QUIRKY IDE — PHP TOOLS SERVICE (Phase 3 · Task 3.6 · instance-based)
 * ═══════════════════════════════════════════════════════════════════════════
 *
 *  Server-side PHP code tools. These use PHP's own tokenizer
 *  (token_get_all), so they're safe with strings, heredocs, comments,
 *  and the special "?>" newline rule.
 *
 *    format()               → re-indents PHP (the ✨ Format button)
 *    formatWithCsFixer()    → industry-standard PHP formatting
 *    minify()               → safe PHP minifier (the 🗜️ Shrink button)
 *    lint()                 → multi-language lint for the error-lens
 *                             (php -l · python -m py_compile · node --check)
 *    guardTopLevelFunctions() → prevents "Cannot redeclare" fatals
 *    deviceWrapper()        → device-framed pop-out preview
 *    resolveRel()           → relative URL resolution
 *
 *  All methods are now INSTANCE methods (not static) for consistency
 *  with the rest of the service layer. Create one instance and reuse it:
 *
 *      $phpTools = new IdePhpTools();
 *      $result = $phpTools->format($code);
 *
 * ═══════════════════════════════════════════════════════════════════════════
 */
class IdePhpTools implements IdePhpToolsInterface
{
  /**
   * Constructor. PhpTools is stateless, but we give it a constructor
   * for consistency with the other services.
   */
  public function __construct()
  {
    // No state needed — all methods are pure functions.
  }
  /**
   * Format PHP using PHP CS Fixer (the industry-standard PHP formatter).
   *
   * Requires app/vendor/php-cs-fixer/php-cs-fixer.phar.
   *
   * @return array ['ok' => bool, 'code' => string] or ['ok' => false, 'reason' => string]
   */
  public function formatWithCsFixer(string $code): array
  {
    $phar = IDE_VENDOR . '/php-cs-fixer/php-cs-fixer.phar';
    if (!is_file($phar)) {
      return ['ok' => false, 'reason' => 'php-cs-fixer.phar not found in app/vendor/php-cs-fixer/'];
    }
    if (trim($code) === '') {
      return ['ok' => true, 'code' => $code];
    }
    $tmpBase = tempnam(sys_get_temp_dir(), 'quirky_fmt_');
    if ($tmpBase === false) {
      return ['ok' => false, 'reason' => 'could not create a temp file'];
    }
    @unlink($tmpBase);
    $tmp = $tmpBase . '.php';
    if (@file_put_contents($tmp, $code) === false) {
      return ['ok' => false, 'reason' => 'could not write the temp file'];
    }
    try {
      $bin  = PHP_BINARY;
      $base = strtolower(basename($bin));
      if ($base !== 'php' && $base !== 'php.exe') {
        $bin = (PHP_OS_FAMILY === 'Windows') ? 'php.exe' : 'php';
      }
      $cmd = escapeshellarg($bin) . ' ' . escapeshellarg($phar)
        . ' fix ' . escapeshellarg($tmp)
        . ' --rules=@PSR12'
        . ' --using-cache=no'
        . ' --allow-risky=no'
        . ' --quiet 2>&1';
      @exec($cmd, $out, $exit);
      $fixed = @file_get_contents($tmp);
      if ($fixed === false || $fixed === '') {
        return ['ok' => false, 'reason' => 'PHP CS Fixer returned nothing (exit ' . $exit . ')'];
      }
      return ['ok' => true, 'code' => $fixed];
    } finally {
      @unlink($tmp);
    }
  }
  /**
   * Basic PHP formatter — re-indents code using token-aware bracket counting.
   *
   * @param string $code Raw PHP source (may include HTML)
   * @return string Re-indented source
   */
  public function format(string $code): string
  {
    if (trim($code) === '') {
      return $code;
    }
    $tokens = @token_get_all($code);
    if (!is_array($tokens) || $tokens === []) {
      return $code;
    }
    $tab = '    ';
    $sanitized = '';
    $protected = [];
    $line = 0;
    foreach ($tokens as $token) {
      if (is_array($token)) {
        $id   = $token[0];
        $text = $token[1];
        $newlines = substr_count($text, "
");
        $isBlock = (
          $id === T_ENCAPSED_AND_WHITESPACE ||
          $id === T_COMMENT ||
          $id === T_DOC_COMMENT ||
          $id === T_START_HEREDOC ||
          $id === T_END_HEREDOC ||
          ($id === T_CONSTANT_ENCAPSED_STRING && $newlines > 0)
        );
        if ($isBlock) {
          for ($k = 0; $k <= $newlines; $k++) {
            $protected[$line + $k] = true;
          }
          $sanitized .= str_repeat("
", $newlines);
        } elseif ($id === T_CONSTANT_ENCAPSED_STRING) {
          $sanitized .= '""';
        } else {
          $sanitized .= $text;
        }
        $line += $newlines;
      } else {
        $sanitized .= $token;
      }
    }
    $origLines = preg_split('/\r
|\r|
/', $code);
    $sanLines  = preg_split('/\r
|\r|
/', $sanitized);
    $depth  = 0;
    $result = [];
    foreach ($origLines as $i => $origLine) {
      $sanLine = $sanLines[$i] ?? '';
      if (!empty($protected[$i])) {
        $result[] = $origLine;
        $depth = max(0, $depth
          + substr_count($sanLine, '{') + substr_count($sanLine, '(') + substr_count($sanLine, '[')
          - substr_count($sanLine, '}') - substr_count($sanLine, ')') - substr_count($sanLine, ']'));
        continue;
      }
      $trimmed = trim($origLine);
      if ($trimmed === '') {
        $result[] = '';
        continue;
      }
      $leadingClose = 0;
      if (preg_match('/^[\}\)\]]+/', ltrim($sanLine), $m)) {
        $leadingClose = strlen($m[0]);
      }
      $currentDepth = max(0, $depth - $leadingClose);
      $result[] = str_repeat($tab, $currentDepth) . $trimmed;
      $opens  = substr_count($sanLine, '{') + substr_count($sanLine, '(') + substr_count($sanLine, '[');
      $closes = substr_count($sanLine, '}') + substr_count($sanLine, ')') + substr_count($sanLine, ']');
      $depth  = max(0, $depth + $opens - $closes);
    }
    return implode("
", $result);
  }
  /**
   * Safe PHP minifier — strips comments and redundant whitespace.
   *
   * @param string $code Raw PHP source (may include HTML)
   * @return string Minified source
   */
  public function minify(string $code): string
  {
    if (trim($code) === '') {
      return $code;
    }
    $tokens = @token_get_all($code);
    if (!is_array($tokens) || $tokens === []) {
      return $code;
    }
    $startsWord = static function (string $t): bool {
      return $t !== '' && preg_match('/^[A-Za-z0-9_$]/', $t) === 1;
    };
    $endsWord = static function (string $t): bool {
      return $t !== '' && preg_match('/[A-Za-z0-9_]$/', $t) === 1;
    };
    $needsSpace = static function (string $prev, string $cur) use ($startsWord, $endsWord): bool {
      if ($prev === '') {
        return false;
      }
      if ($endsWord($prev) && $startsWord($cur)) {
        return true;
      }
      if ($cur === '.' && preg_match('/[0-9]$/', $prev) === 1) {
        return true;
      }
      if (preg_match('/^[0-9]/', $cur) === 1 && preg_match('/\.$/', $prev) === 1) {
        return true;
      }
      return false;
    };
    $out      = '';
    $prevText = '';
    foreach ($tokens as $token) {
      if (is_array($token)) {
        $id   = $token[0];
        $text = $token[1];
        if ($id === T_COMMENT || $id === T_DOC_COMMENT) {
          continue;
        }
        if ($id === T_WHITESPACE) {
          continue;
        }
        if ($needsSpace($prevText, $text)) {
          $out .= ' ';
        }
        $out .= $text;
        $prevText = $text;
      } else {
        if ($needsSpace($prevText, $token)) {
          $out .= ' ';
        }
        $out .= $token;
        $prevText = $token;
      }
    }
    return $out;
  }
  /**
   * Token-aware guard for top-level function definitions.
   *
   * @param string $code Raw PHP source (may include HTML)
   * @return string The source with top-level functions guarded
   */
  public function guardTopLevelFunctions(string $code): string
  {
    $tokens = @token_get_all($code);
    if (!is_array($tokens) || $tokens === []) {
      return $code;
    }
    $n      = count($tokens);
    $edits  = [];
    $offset = 0;
    $depth  = 0;
    $pendingName     = null;
    $pendingFnOffset = null;
    $pendingOpened   = false;
    for ($i = 0; $i < $n; $i++) {
      $tok = $tokens[$i];
      if (is_array($tok)) {
        $id   = $tok[0];
        $text = $tok[1];
        if ($id === T_FUNCTION && $depth === 0) {
          $name = null;
          for ($j = $i + 1; $j < $n; $j++) {
            $t2 = $tokens[$j];
            if (is_array($t2)) {
              if ($t2[0] === T_WHITESPACE || $t2[0] === T_COMMENT || $t2[0] === T_DOC_COMMENT) {
                continue;
              }
              if ($t2[0] === T_STRING) {
                $name = $t2[1];
              }
              break;
            }
            if ($t2 === '&') {
              continue;
            }
            break;
          }
          if ($name !== null) {
            $pendingName     = $name;
            $pendingFnOffset = $offset;
            $pendingOpened   = false;
          } else {
            $pendingName = null;
            $pendingFnOffset = null;
            $pendingOpened = false;
          }
        }
        $offset += strlen($text);
      } else {
        if ($tok === '{') {
          $depth++;
          if ($pendingName !== null && !$pendingOpened) {
            $pendingOpened = true;
          }
        } elseif ($tok === '}') {
          if ($pendingName !== null && $pendingOpened && $depth === 1) {
            $edits[] = [
              'at'     => $pendingFnOffset,
              'insert' => "if (!function_exists('" . $pendingName . "')) { ",
            ];
            $edits[] = [
              'at'     => $offset + 1,
              'insert' => ' }',
            ];
            $pendingName = null;
            $pendingFnOffset = null;
            $pendingOpened = false;
          }
          $depth--;
        }
        $offset += strlen($tok);
      }
    }
    if (empty($edits)) {
      return $code;
    }
    usort($edits, function ($a, $b) {
      return $b['at'] <=> $a['at'];
    });
    foreach ($edits as $e) {
      $code = substr($code, 0, $e['at']) . $e['insert'] . substr($code, $e['at']);
    }
    return $code;
  }
  /**
   * Device-framed "Pop out" wrapper.
   *
   * @param string $device   'tablet' | 'phone'
   * @param string $innerUrl URL that returns the bare page
   * @param string $title    file name, for the tab title
   */
  public function deviceWrapper(string $device, string $innerUrl, string $title): string
  {
    $device  = ($device === 'phone') ? 'phone' : 'tablet';
    $safeUrl = htmlspecialchars($innerUrl, ENT_QUOTES);
    $safeT   = htmlspecialchars($title, ENT_QUOTES);
    $label   = $device === 'phone' ? 'Phone · 375×667' : 'Tablet · 768×1024';
    $pageCss = 'html,body{height:100%;margin:0}'
      . 'body{background:#0a101b;color:#e8eef7;font-family:system-ui,sans-serif;display:flex;flex-direction:column;overflow:hidden}';
    return '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">'
      . '<meta name="viewport" content="width=device-width, initial-scale=1">'
      . '<title>' . $safeT . ' · ' . $label . ' — Quirky</title>'
      . '<link rel="stylesheet" href="index.php?asset=css/device-frames.css">'
      . '<style>' . $pageCss . '</style></head><body>'
      . '<div class="device-stage"><div class="device-shell" data-device="' . $device . '">'
      . '<div class="device-sensor" aria-hidden="true"><i class="device-speaker"></i><i class="device-cam"></i></div>'
      . '<iframe src="' . $safeUrl . '" title="Preview"></iframe>'
      . '<div class="device-home" aria-hidden="true"></div>'
      . '</div></div></body></html>';
  }
  /**
   * Resolve a relative URL against a base directory.
   *
   * @param string $base Base directory (e.g. "pages/")
   * @param string $rel  Relative URL (e.g. "../css/style.css")
   * @return string Resolved path
   */
  public function resolveRel(string $base, string $rel): string
  {
    $parts = explode('/', $base . $rel);
    $out = [];
    foreach ($parts as $p) {
      if ($p === '.' || $p === '') {
        continue;
      }
      if ($p === '..') {
        array_pop($out);
        continue;
      }
      $out[] = $p;
    }
    return implode('/', $out);
  }
  /* ═══════════════════════════════════════════════════════════════
       LINT — multi-language syntax checking for the error-lens.
       ═══════════════════════════════════════════════════════════════
       Dispatches by language:
         • php family → `php -l` (PHP's own binary)
         • py/pyw     → `python -m py_compile`   (best effort)
         • js/mjs     → `node --check`           (best effort)
         • ts/tsx/jsx → skipped (no compiler available on-device)
         • anything else → ok:true with no markers + a note

       Every external binary is looked up defensively: if Python or
       Node is not installed (or proc_open is disabled), we NEVER fail
       the request — we simply return zero markers plus a note so the
       editor keeps working. Works identically on Windows (Laragon)
       and Android (libphp.so + pkg-installed toolchains).            */

  /**
   * Server-side syntax lint for the error-lens feature.
   *
   * @return array {markers: [{line, severity, message, source}], language, ok, note?}
   */
  public function lint(string $content, string $language): array
  {
    $language = strtolower(trim($language));
    if (in_array($language, ['php', 'phtml', 'php3', 'php4', 'php5', 'phps'], true)) {
      return $this->lintPhp($content);
    }
    if (in_array($language, ['py', 'pyw', 'python'], true)) {
      return $this->lintPython($content);
    }
    // TypeScript / JSX need a real compiler; node --check would report
    // false errors on type annotations and JSX tags. Be honest instead.
    if (in_array($language, ['ts', 'tsx', 'jsx'], true)) {
      return [
        'markers'  => [],
        'language' => $language,
        'ok'       => true,
        'note'     => $language === 'ts' || $language === 'tsx'
          ? 'no TS compiler — TypeScript not checked'
          : 'no JSX parser — JSX not checked',
      ];
    }
    if (in_array($language, ['javascript', 'js', 'mjs', 'cjs'], true)) {
      return $this->lintNode($content, $language === 'mjs' ? 'mjs' : ($language === 'cjs' ? 'cjs' : 'js'));
    }
    return [
      'markers'  => [],
      'language' => $language,
      'ok'       => true,
      'note'     => 'no linter for this language',
    ];
  }

  /**
   * PHP family lint via `php -l`.
   *
   * @return array {markers: [...], language, ok}
   */
  private function lintPhp(string $content): array
  {
    if ($content === '') {
      return ['markers' => [], 'language' => 'php', 'ok' => true];
    }
    if (!function_exists('proc_open')) {
      return ['markers' => [], 'language' => 'php', 'ok' => true, 'note' => 'proc_open unavailable'];
    }
    $base = tempnam(sys_get_temp_dir(), 'quirky_lint_');
    if ($base === false) {
      return ['markers' => [], 'language' => 'php', 'ok' => true];
    }
    @unlink($base);
    $tmp = $base . '.php';
    @file_put_contents($tmp, $content);
    $markers = [];
    try {
      $bin  = PHP_BINARY;
      $baseName = strtolower(basename($bin));
      if ($baseName !== 'php' && $baseName !== 'php.exe') {
        $bin = (PHP_OS_FAMILY === 'Windows') ? 'php.exe' : 'php';
      }
      $cmd = escapeshellarg($bin) . ' -l -n ' . escapeshellarg($tmp);
      $pipes = [];
      $proc = @proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
      if (is_resource($proc)) {
        fclose($pipes[0]);
        $out = (string) stream_get_contents($pipes[1]);
        $err = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($proc);
        $text = $out . "
" . $err;
        if (!preg_match('/No syntax errors detected/i', $text)) {
          if (preg_match_all(
            '/(Parse|Fatal|Warning)\s*(?:error)?:\s*(.+?)\s+in\s+.+?\s+on\s+line\s+(\d+)/i',
            $text,
            $hits,
            PREG_SET_ORDER
          )) {
            foreach ($hits as $h) {
              $markers[] = [
                'line'     => (int) $h[3] - 1,
                'severity' => (strtolower($h[1]) === 'warning') ? 'warning' : 'error',
                'message'  => trim($h[2]),
                'source'   => 'php -l',
              ];
            }
          }
          if (!$markers && trim($text) !== '') {
            if (preg_match('/(parse|fatal|syntax)\s*error|unexpected|expecting|on line \d/i', $text)) {
              $markers[] = [
                'line'     => 0,
                'severity' => 'error',
                'message'  => trim(preg_replace('/\s+/', ' ', $text)) ?: 'Syntax error',
                'source'   => 'php -l',
              ];
            }
          }
        }
      }
    } finally {
      @unlink($tmp);
    }
    return ['markers' => $markers, 'language' => 'php', 'ok' => true];
  }

  /**
   * Python lint via `python -X utf8 -m py_compile <file>` (best effort).
   *
   * Runs in its own temp directory because py_compile drops a
   * __pycache__/*.pyc next to the source — the whole directory is
   * removed again in a finally block.
   */
  private function lintPython(string $content): array
  {
    if ($content === '') {
      return ['markers' => [], 'language' => 'python', 'ok' => true];
    }
    if (!function_exists('proc_open')) {
      return ['markers' => [], 'language' => 'python', 'ok' => true, 'note' => 'proc_open unavailable — lint skipped'];
    }
    $dir = $this->makeTempDir();
    if ($dir === null) {
      return ['markers' => [], 'language' => 'python', 'ok' => true, 'note' => 'could not create a temp file'];
    }
    $tmp = $dir . '/quirky_src.py';
    if (@file_put_contents($tmp, $content) === false) {
      $this->removeTempDir($dir);
      return ['markers' => [], 'language' => 'python', 'ok' => true, 'note' => 'could not write the temp file'];
    }
    try {
      // Raw PATH lookup on purpose: works for pkg-installed Python on
      // Android and any python.exe on PATH on Windows.
      $cmd = escapeshellarg('python') . ' -X utf8 -m py_compile ' . escapeshellarg($tmp) . ' 2>&1';
      $r = $this->runExternal($cmd, 20);
      if ($this->looksLikeMissingBinary($r)) {
        return ['markers' => [], 'language' => 'python', 'ok' => true, 'note' => 'Python not installed — lint skipped'];
      }
      if ($r['exitCode'] !== 0 && trim($r['output']) !== '') {
        return [
          'markers'  => $this->parseExternalError($r['output'], 'python'),
          'language' => 'python',
          'ok'       => true,
        ];
      }
    } finally {
      $this->removeTempDir($dir);
    }
    return ['markers' => [], 'language' => 'python', 'ok' => true];
  }

  /**
   * JavaScript lint via `node --check <file>` (best effort).
   *
   * @param string $ext Temp-file extension: 'js' | 'mjs' | 'cjs'
   */
  private function lintNode(string $content, string $ext): array
  {
    if ($content === '') {
      return ['markers' => [], 'language' => 'javascript', 'ok' => true];
    }
    if (!function_exists('proc_open')) {
      return ['markers' => [], 'language' => 'javascript', 'ok' => true, 'note' => 'proc_open unavailable — lint skipped'];
    }
    $base = tempnam(sys_get_temp_dir(), 'quirky_lint_');
    if ($base === false) {
      return ['markers' => [], 'language' => 'javascript', 'ok' => true, 'note' => 'could not create a temp file'];
    }
    @unlink($base);
    $tmp = $base . '.' . $ext;
    @file_put_contents($tmp, $content);
    try {
      $cmd = escapeshellarg('node') . ' --check ' . escapeshellarg($tmp) . ' 2>&1';
      $r = $this->runExternal($cmd, 20);
      if ($this->looksLikeMissingBinary($r)) {
        return ['markers' => [], 'language' => 'javascript', 'ok' => true, 'note' => 'Node.js not installed — lint skipped'];
      }
      if ($r['exitCode'] !== 0 && trim($r['output']) !== '') {
        return [
          'markers'  => $this->parseExternalError($r['output'], 'node'),
          'language' => 'javascript',
          'ok'       => true,
        ];
      }
    } finally {
      @unlink($tmp);
    }
    return ['markers' => [], 'language' => 'javascript', 'ok' => true];
  }

  /* ── LINT HELPERS ───────────────────────────────────────────── */

  /**
   * Run one external checker command with a timeout and output cap.
   *
   * @return array {launched: bool, exitCode: int, output: string}
   */
  private function runExternal(string $cmd, int $timeoutSec): array
  {
    $pipes = [];
    $proc = @proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($proc)) {
      return ['launched' => false, 'exitCode' => -1, 'output' => ''];
    }
    fclose($pipes[0]);
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);

    $out = '';
    $err = '';
    $exitCode = -1;
    $start = microtime(true);
    while (true) {
      $out .= (string) stream_get_contents($pipes[1]);
      $err .= (string) stream_get_contents($pipes[2]);
      if (strlen($out) + strlen($err) > 262144) { // runaway-output guard
        proc_terminate($proc, PHP_OS_FAMILY === 'Windows' ? 1 : 9);
        break;
      }
      $st = proc_get_status($proc);
      if (!$st['running']) {
        $exitCode = (int) $st['exitcode'];
        $out .= (string) stream_get_contents($pipes[1]);
        $err .= (string) stream_get_contents($pipes[2]);
        break;
      }
      if ((microtime(true) - $start) > $timeoutSec) {
        proc_terminate($proc, PHP_OS_FAMILY === 'Windows' ? 1 : 9);
        usleep(50000);
        break;
      }
      usleep(10000);
    }
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($proc);
    return ['launched' => true, 'exitCode' => $exitCode, 'output' => $out . "\n" . $err];
  }

  /**
   * True when the tool binary is missing rather than the CODE being bad:
   * proc_open could not launch it, the shell said "not found" (POSIX exit
   * 127), or the Windows Store stub answered ("Python was not found…").
   * In every case we skip linting instead of reporting fake markers.
   */
  private function looksLikeMissingBinary(array $r): bool
  {
    if (!$r['launched']) {
      return true;
    }
    if ($r['exitCode'] === 127 || $r['exitCode'] === 9009) {
      return true;
    }
    return $r['exitCode'] !== 0 && (bool) preg_match(
      '/(was not found|is not recognized|command not found|no such file|unable to find)/i',
      $r['output']
    );
  }

  /**
   * Turn a checker's raw stderr/stdout into an error-lens marker.
   *
   * Recognizes the two shapes we actually invoke:
   *   Python : File "x.py", line N  →  SomeError: message
   *   Node   : path/file.js:N       →  SyntaxError: message
   * Falls back to a single line-0 marker carrying the flattened output so
   * the diagnostic is never lost entirely.
   *
   * @return array[] [{line, severity, message, source}]
   */
  private function parseExternalError(string $output, string $source): array
  {
    $text = str_replace("\r\n", "\n", $output);
    $line = null;

    if (preg_match('/File\s+"[^"]*",\s*line\s+(\d+)/i', $text, $m)) {
      // Python py_compile shape.
      $line = (int) $m[1] - 1;
    } elseif (preg_match('/[\/\\\\][^\s:]+?\.(?:m?js|cjs):(\d+)/i', $text, $m)) {
      // node --check shape.
      $line = (int) $m[1] - 1;
    }

    $message = null;
    if (preg_match('/([\w.]*Error)\s*:\s*(.+)$/im', $text, $m)) {
      $message = trim($m[1]) . ': ' . trim(preg_replace('/\s+/', ' ', $m[2]));
    } elseif (trim($text) !== '') {
      $flat = trim((string) preg_replace('/\s+/', ' ', $text));
      $message = $flat !== '' ? $flat : 'Syntax error';
    }
    if ($message === null || trim($message) === '') {
      return [];
    }
    return [[
      'line'     => max(0, $line ?? 0),
      'severity' => 'error',
      'message'  => strlen($message) > 240 ? substr($message, 0, 240) . '…' : $message,
      'source'   => $source,
    ]];
  }

  /** Create an empty private temp directory under sys_get_temp_dir(). */
  private function makeTempDir(): ?string
  {
    $base = tempnam(sys_get_temp_dir(), 'quirky_lint_');
    if ($base === false) {
      return null;
    }
    @unlink($base);
    if (!@mkdir($base, 0700)) {
      return null;
    }
    return $base;
  }

  /** Delete a temp dir created by makeTempDir() (incl. __pycache__). */
  private function removeTempDir(string $dir): void
  {
    if (!is_dir($dir)) {
      @unlink($dir);
      return;
    }
    $items = @scandir($dir);
    if (is_array($items)) {
      foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
          continue;
        }
        $p = $dir . '/' . $item;
        if (is_dir($p) && !is_link($p)) {
          $this->removeTempDir($p);
        } else {
          @unlink($p);
        }
      }
    }
    @rmdir($dir);
  }
}
