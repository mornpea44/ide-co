<?php

declare(strict_types=1);
/**
 * ═══════════════════════════════════════════════════════════════════════════
 *  QUIRKY IDE — SYMBOLS INDEXER (document outline backend)
 * ═══════════════════════════════════════════════════════════════════════════
 *
 *  Builds a lightweight "outline" of one workspace file: its functions,
 *  methods, classes, interfaces, traits, structs, constants and exported
 *  variables — enough to power a symbol list / jump-to-symbol UI without
 *  shipping a full parser per language.
 *
 *  HOW IT WORKS
 *    • The file is read THROUGH the security jail (resolvePath), so a path
 *      can never escape workspace/ or touch .git internals.
 *    • Per-language regex passes over comment-blanked lines. A small brace
 *      / indent state machine tracks class ownership so members come back
 *      as {kind:'method', owner:'ClassName'}.
 *    • Output is capped and sorted; nothing here can fatal on weird
 *      content — worst case you get an empty or partial list.
 *
 *  SYMBOL SHAPE
 *    { line: int(0-based), name: string, kind: string, signature: string,
 *      owner?: string }
 *
 *    kind ∈ function | method | class | interface | trait | struct |
 *           constant | variable-export
 *
 * ═══════════════════════════════════════════════════════════════════════════
 */
class IdeSymbols
{
  /** @var array The config array from config.php */
  private array $config;

  /** @var IdeSecurity The security guard (path jail + validation) */
  private IdeSecurity $security;

  /** Files bigger than this are not indexed at all. */
  private const MAX_FILE_BYTES = 524288; // 512 KB
  /** Hard cap on returned symbols (outline UIs don't need more). */
  private const MAX_SYMBOLS = 500;

  public function __construct(array $config, IdeSecurity $security)
  {
    $this->config   = $config;
    $this->security = $security;
  }

  /**
   * Index one workspace file.
   *
   * @param string $path Workspace-relative path (e.g. 'app/main.py')
   * @return array[] Sorted-by-line symbol list ([] for unsupported/binary/
   *                 oversized files)
   */
  public function index(string $path): array
  {
    $path = trim(str_replace('\\', '/', $path));
    if ($path === '') {
      return [];
    }

    // Jail first — same discipline as every other FS-touching service.
    // (resolvePath/checkBlockedSegments fail the request on bad paths.)
    $this->security->checkBlockedSegments($path);
    $full = $this->security->resolvePath($path);

    $lang = $this->languageFor($path);
    if ($lang === null) {
      return []; // css/html/md/etc. are deliberately skipped
    }

    if (!is_file($full) || !is_readable($full)) {
      return [];
    }
    clearstatcache(true, $full);
    $size = @filesize($full);
    if ($size === false || $size > self::MAX_FILE_BYTES || $size === 0) {
      return [];
    }
    $src = @file_get_contents($full);
    if ($src === false || $src === '') {
      return [];
    }
    // Strip UTF-8 BOM so ^-anchored regexes match on line 1.
    if (strncmp($src, "\xEF\xBB\xBF", 3) === 0) {
      $src = substr($src, 3);
    }

    try {
      switch ($lang) {
        case 'php':
          $symbols = $this->indexPhp($src);
          break;
        case 'js':
          $symbols = $this->indexJs($src);
          break;
        case 'python':
          $symbols = $this->indexPython($src);
          break;
        case 'cfamily':
          $symbols = $this->indexCFamily($src);
          break;
        case 'go':
          $symbols = $this->indexGo($src);
          break;
        default:
          $symbols = [];
      }
    } catch (\Throwable $e) {
      // Never fatal on weird content — an empty/partial outline beats a 500.
      return [];
    }

    usort($symbols, static function (array $a, array $b): int {
      return [$a['line'], $a['name']] <=> [$b['line'], $b['name']];
    });
    return array_slice($symbols, 0, self::MAX_SYMBOLS);
  }

  /* ═══════════════════════════════════════════════════════════════
       LANGUAGE DISPATCH (by extension)
       ═══════════════════════════════════════════════════════════════ */

  /** Map a file extension to an indexer key; null = skip this file. */
  private function languageFor(string $path): ?string
  {
    $ext = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
    switch ($ext) {
      case 'php':
      case 'phtml':
      case 'php3':
      case 'php4':
      case 'php5':
      case 'phps':
        return 'php';
      case 'js':
      case 'mjs':
      case 'cjs':
      case 'jsx':
      case 'ts':
      case 'tsx':
        return 'js';
      case 'py':
      case 'pyw':
        return 'python';
      case 'c':
      case 'cc':
      case 'cpp':
      case 'cxx':
      case 'h':
      case 'hh':
      case 'hpp':
      case 'hxx':
      case 'java':
      case 'cs':
      case 'kt':
      case 'kts':
        return 'cfamily';
      case 'go':
        return 'go';
      default:
        // css/scss/less, html/htm, md/markdown, json, … → skip.
        return null;
    }
  }

  /* ═══════════════════════════════════════════════════════════════
       PHP
       ═══════════════════════════════════════════════════════════════ */

  /** @return array[] */
  private function indexPhp(string $src): array
  {
    $lines = $this->blankBlockComments($src);
    $out = [];
    $depth = 0;
    $owners = []; // stack of ['depth' => int, 'name' => string]

    foreach ($lines as $i => $code) {
      $classMatch = null;
      // Classes / interfaces / traits / enums.
      if (preg_match(
        '/^\s*(?:(?:final|abstract|readonly)\s+)*(class|interface|trait|enum)\s+([A-Za-z_]\w*)/',
        $code,
        $cm
      )) {
        $classMatch = $cm;
        $kind = $cm[1] === 'interface' ? 'interface'
          : ($cm[1] === 'trait' ? 'trait' : 'class'); // enum renders as class
        $out[] = $this->sym($i, $cm[2], $kind, $code);
      }

      // Functions & methods (visibility/static modifiers preserved).
      if (preg_match(
        '/^\s*((?:(?:abstract|final|static|public|protected|private)\s+)*?)function\s+&?\s*([A-Za-z_]\w*)\s*\(/',
        $code,
        $fm
      )) {
        $owner = $this->topOwner($owners, $depth);
        $mods = trim((string) preg_replace('/\s+/', ' ', (string) $fm[1]));
        $params = $this->parenPart($code);
        $sig = ($mods !== '' ? $mods . ' ' : '') . 'function ' . $fm[2] . '(' . $params;
        $out[] = $this->sym(
          $i,
          $fm[2],
          $owner !== null ? 'method' : 'function',
          $sig,
          $owner
        );
      }

      // define('NAME', …) constants.
      if (preg_match('/^\s*define\s*\(\s*([\'"])([\w\\\\]+)\1/i', $code, $dm)) {
        $out[] = $this->sym($i, ltrim($dm[2], '\\'), 'constant', $code);
      }

      // Brace-depth bookkeeping for class ownership.
      $open = substr_count($code, '{');
      $close = substr_count($code, '}');
      $depth += $open - $close;
      $d = max($depth, 0);
      if ($classMatch !== null && $open > $close) {
        // Class scope really opens on this line (not a "class X {}" one-liner).
        $owners[] = ['depth' => $d, 'name' => (string) $classMatch[2]];
      }
      while ($owners !== [] && end($owners)['depth'] > $d) {
        array_pop($owners);
      }
    }
    return $out;
  }

  /* ═══════════════════════════════════════════════════════════════
       JAVASCRIPT / TYPESCRIPT / JSX
       ═══════════════════════════════════════════════════════════════ */

  /** @return array[] */
  private function indexJs(string $src): array
  {
    $lines = $this->blankBlockComments($src);
    $out = [];
    $depth = 0;
    $owners = [];

    // Shorthand-method candidates that are really control flow / calls.
    $notKeyword = ['if', 'for', 'while', 'switch', 'catch', 'return', 'else', 'do', 'try', 'new', 'delete', 'typeof', 'void', 'in', 'of', 'instanceof', 'await', 'yield', 'throw'];

    foreach ($lines as $i => $code) {
      $classMatch = null;

      // Class declarations (export/default/abstract tolerated).
      if (preg_match(
        '/^\s*(?:export\s+default\s+|export\s+|declare\s+|abstract\s+)*class\s+([A-Za-z_$][\w$]*)/',
        $code,
        $cm
      )) {
        $classMatch = $cm;
        $out[] = $this->sym($i, $cm[1], 'class', $code);
      }

      if (!$classMatch && preg_match(
        // Function declarations: [export] [default] [async] function name(
        '/^\s*(?:export\s+default\s+|export\s+|declare\s+)*(async\s+)?function\s*\*?\s*([A-Za-z_$][\w$]*)\s*\(/',
        $code,
        $fm
      )) {
        $owner = $this->topOwner($owners, $depth);
        $prefix = ((string) ($fm[1] ?? '')) !== '' ? 'async function' : 'function';
        $out[] = $this->sym(
          $i,
          $fm[2],
          $owner !== null ? 'method' : 'function',
          $prefix . ' ' . $fm[2] . '(' . $this->parenPart($code),
          $owner
        );
      } elseif (!$classMatch && preg_match(
        // Arrow functions assigned to bindings: const x = (…) => · x = a =>
        '/^\s*(export\s+)?(const|let|var)\s+([A-Za-z_$][\w$]*)\s*(?::[^=]+)?=\s*(async\s+)?(\([^()]*\)|[A-Za-z_$][\w$]*)\s*=>/',
        $code,
        $am
      )) {
        $owner = $this->topOwner($owners, $depth);
        $kw = (string) $am[2];
        $params = ltrim(rtrim((string) $am[5]));
        $sig = (($am[1] ?? '') !== '' ? 'export ' : '') . $kw . ' ' . $am[3]
          . ' = ' . (($am[4] ?? '') !== '' ? 'async ' : '') . $params . ' => …';
        $out[] = $this->sym(
          $i,
          $am[3],
          $owner !== null ? 'method' : 'function',
          $sig,
          $owner
        );
      } elseif (!$classMatch && preg_match(
        // Other exported bindings: export const/let/var name =
        '/^\s*export\s+(const|let|var)\s+([A-Za-z_$][\w$]*)/',
        $code,
        $em
      )) {
        $out[] = $this->sym($i, $em[2], 'variable-export', $code);
      }

      // Method shorthand inside a known class body:
      //   foo(a, b) {   async foo() {   static bar() {   get x() {
      // Guarded against call statements (`it('…', () => {`) by refusing
      // lines with quotes/arrow functions in them.
      if ($owners !== []
        && strpos($code, '\'') === false && strpos($code, '"') === false && strpos($code, '`') === false
        && strpos($code, '=>') === false
        && preg_match(
          '/^\s+(?:(?:static|async|get|set|public|private|protected|readonly|override)\s+|\*\s*)*([A-Za-z_$][\w$]*)\s*(?:<[^<>]*>)?\s*\([^;{}]*\)\s*\{/',
          $code,
          $mm
        )
      ) {
        $name = $mm[1];
        if (!in_array($name, $notKeyword, true)) {
          $owner = $this->topOwner($owners, $depth);
          if ($owner !== null) {
            $out[] = $this->sym($i, $name, 'method', trim($code), $owner);
          }
        }
      }

      // Brace-depth bookkeeping for class ownership.
      $open = substr_count($code, '{');
      $close = substr_count($code, '}');
      $depth += $open - $close;
      $d = max($depth, 0);
      if ($classMatch !== null && $open > $close) {
        $owners[] = ['depth' => $d, 'name' => (string) $classMatch[1]];
      }
      while ($owners !== [] && end($owners)['depth'] > $d) {
        array_pop($owners);
      }
    }
    return $out;
  }

  /* ═══════════════════════════════════════════════════════════════
       PYTHON (indentation-aware)
       ═══════════════════════════════════════════════════════════════ */

  /** @return array[] */
  private function indexPython(string $src): array
  {
    $lines = $this->blankBlockComments($src);
    $out = [];
    $classes = []; // stack of ['indent' => int, 'name' => string]

    foreach ($lines as $i => $code) {
      // Blank / fully-commented lines carry NO indentation meaning in
      // Python — skipping them stops them from closing a class scope.
      if (trim($code) === '') {
        continue;
      }
      $indent = $this->indentOf($code);

      // Leaving a class scope when this line dedents past it.
      while ($classes !== [] && $indent <= end($classes)['indent']) {
        array_pop($classes);
      }

      if (preg_match('/^\s*(?:async\s+)?class\s+([A-Za-z_]\w*)/', $code, $m)) {
        $out[] = $this->sym($i, $m[1], 'class', $code);
        $classes[] = ['indent' => $indent, 'name' => $m[1]];
        continue;
      }

      if (preg_match('/^\s*(async\s+)?def\s+([A-Za-z_]\w*)\s*\(/', $code, $m)) {
        $owner = end($classes)['name'] ?? null;
        $sig = trim($code);
        // Cut a trailing ": return-annotation" (the colon AFTER the closing
        // paren), but keep parameter annotations like `def f(a: int)`.
        $closeParen = strrpos($sig, ')');
        $lastColon = strrpos($sig, ':');
        if ($lastColon !== false && ($closeParen === false || $lastColon > $closeParen)) {
          $sig = rtrim(substr($sig, 0, $lastColon));
        }
        $out[] = $this->sym(
          $i,
          $m[2],
          $owner !== null ? 'method' : 'function',
          ((string) $m[1] !== '' ? 'async def ' : 'def ') . $m[2] .
            (strpos($sig, '(') !== false ? '(' . $this->betweenParens($sig) . ')' : ''),
          $owner
        );
      }
    }
    return $out;
  }

  /* ═══════════════════════════════════════════════════════════════
       C / C++ / H / JAVA / C# / KOTLIN (conservative)
       ═══════════════════════════════════════════════════════════════ */

  /** @return array[] */
  private function indexCFamily(string $src): array
  {
    $lines = $this->blankBlockComments($src);
    $out = [];
    $depth = 0;
    $owners = [];

    $keywords = [
      'if', 'for', 'while', 'switch', 'catch', 'return', 'else', 'do', 'try',
      'new', 'delete', 'sizeof', 'typeof', 'using', 'namespace', 'import',
      'package', 'foreach', 'lock', 'checked', 'unsafe', 'await', 'match',
      'fn', 'pub', 'get', 'set', 'add', 'remove', 'value', 'when', 'base',
      'this', 'super', 'synchronized', 'select', 'case',
    ];

    foreach ($lines as $i => $code) {
      $classMatch = null;

      // Kotlin's `fun` declarations.
      if (preg_match(
        '/^\s*(?:(?:public|private|protected|internal|open|override|suspend|inline|expect|actual)\s+)*fun\s+(?:<[^(]*>\s*)?(?:[A-Za-z_][\w.]*\.)?([A-Za-z_]\w*)\s*\(/',
        $code,
        $km
      )) {
        $owner = $this->topOwner($owners, $depth);
        $out[] = $this->sym($i, $km[1], $owner !== null ? 'method' : 'function', $code, $owner);
      }
      // class / struct / interface declarations.
      elseif (preg_match(
        '/^\s*(?:(?:export\s+|abstract\s+|sealed\s+|static\s+|public\s+|private\s+|protected\s+|internal\s+|final\s+|partial\s+|open\s+|data\s+)*)?(class|struct|interface)\s+([A-Za-z_]\w*)/',
        $code,
        $cm
      )) {
        $classMatch = $cm;
        $kind = $cm[1];
        $out[] = $this->sym(
          $i,
          isset($cm[2]) ? $cm[2] : '?',
          $kind === 'struct' ? 'struct' : ($kind === 'interface' ? 'interface' : 'class'),
          $code
        );
      }
      // Conservative "function-ish" definitions: TYPE name(args) { …
      // The leading type is built from WHOLE tokens only, so control flow
      // ("if (…) {") matches with name='if' and hits the blacklist instead
      // of being sliced into fake names like 'f'.
      elseif (preg_match(
        '/^\s*(?:@?(?:[A-Za-z_~][\w:&<>,]*|[\*&]+)(?:\[[^\]]*\])?\s+)*(@?[A-Za-z_][\w:]*)\s*\(([^;{)]*)\)\s*(?:const\s+)?(?:noexcept\s+)?(?:throws\s+[\w.,\s]+)?\{/',
        $code,
        $fm
      )) {
        $name = ltrim($fm[1], '@');
        if (!in_array($name, $keywords, true)) {
          $owner = $this->topOwner($owners, $depth);
          $out[] = $this->sym($i, $name, $owner !== null ? 'method' : 'function', $code, $owner);
        }
      }

      // Brace-depth bookkeeping for type ownership.
      $open = substr_count($code, '{');
      $close = substr_count($code, '}');
      $depth += $open - $close;
      $d = max($depth, 0);
      if ($classMatch !== null && $open > $close) {
        $owners[] = ['depth' => $d, 'name' => (string) $classMatch[2]];
      }
      while ($owners !== [] && end($owners)['depth'] > $d) {
        array_pop($owners);
      }
    }
    return $out;
  }

  /* ═══════════════════════════════════════════════════════════════
       GO
       ═══════════════════════════════════════════════════════════════ */

  /** @return array[] */
  private function indexGo(string $src): array
  {
    $lines = $this->blankBlockComments($src);
    $out = [];
    foreach ($lines as $i => $code) {
      if (preg_match('/^\s*func\s+(?:\(\s*\w+\s+\*?([A-Za-z_]\w*)\s*\)\s*)?([A-Za-z_]\w*)(?:\[[^\]]*\])?\s*\(/', $code, $m)) {
        $recv = ($m[1] ?? '') !== '' ? $m[1] : null;
        $out[] = $this->sym($i, $m[2], $recv !== null ? 'method' : 'function', $code, $recv);
      }
    }
    return $out;
  }

  /* ═══════════════════════════════════════════════════════════════
       SHARED HELPERS
       ═══════════════════════════════════════════════════════════════ */

  /**
   * Split source into lines with block comments (/* … *​/) blanked so
   * commented-out declarations can't pollute the outline. Full-line //
   * comments are blanked too; trailing ones are left alone (cheap but
   * good enough for declaration matching).
   *
   * @return string[]
   */
  private function blankBlockComments(string $src): array
  {
    $normalized = str_replace("\r\n", "\n", str_replace("\r", "\n", $src));
    $lines = explode("\n", $normalized);
    $inBlock = false;
    foreach ($lines as &$line) {
      if ($inBlock) {
        $end = stripos($line, '*/');
        if ($end === false) {
          $line = '';
          continue;
        }
        $line = substr($line, $end + 2);
        $inBlock = false;
      }
      if (stripos($line, '/*') !== false) {
        $start = strpos($line, '/*');
        $end = strpos($line, '*/', $start + 2);
        if ($end !== false) {
          $line = substr($line, 0, $start) . ' ' . substr($line, $end + 2);
          // handle multiple blocks on one line
          while (strpos($line, '/*') !== false) {
            $s2 = strpos($line, '/*');
            $e2 = strpos($line, '*/', $s2 + 2);
            if ($e2 === false) {
              $line = substr($line, 0, $s2);
              $inBlock = true;
              break;
            }
            $line = substr($line, 0, $s2) . ' ' . substr($line, $e2 + 2);
          }
        } else {
          $line = substr($line, 0, $start);
          $inBlock = true;
        }
      }
      $trimmed = ltrim($line);
      if ($trimmed === '' || $trimmed[0] === '/' && strlen($trimmed) > 1 && $trimmed[1] === '/') {
        $line = '';
      }
    }
    unset($line);
    return $lines;
  }

  /** Leading-whitespace width of a line (tab counts as 4). */
  private function indentOf(string $line): int
  {
    $n = 0;
    $len = strlen($line);
    for ($i = 0; $i < $len; $i++) {
      $ch = $line[$i];
      if ($ch === ' ') {
        $n++;
      } elseif ($ch === "\t") {
        $n += 4;
      } else {
        break;
      }
    }
    return $n;
  }

  /** Nearest enclosing class owner whose scope still contains $depth. */
  private function topOwner(array $owners, int $depth): ?string
  {
    foreach (array_reverse($owners) as $entry) {
      if ($entry['depth'] <= $depth) {
        return (string) $entry['name'];
      }
    }
    return null;
  }

  /** Build one output symbol (signature cleaned + capped at 120 chars). */
  private function sym(int $line, string $name, string $kind, string $rawLine, ?string $owner = null): array
  {
    $signature = $this->cleanSig($rawLine);
    $symbol = [
      'line'      => max(0, $line),
      'name'      => $name !== '' ? $name : '?',
      'kind'      => $kind,
      'signature' => $signature,
    ];
    if ($owner !== null && $owner !== '') {
      $symbol['owner'] = $owner;
    }
    return $symbol;
  }

  /** Collapse whitespace, drop braces, cap length at 120 chars. */
  private function cleanSig(string $raw): string
  {
    $sig = preg_replace('/\s+/', ' ', trim($raw)) ?? '';
    $brace = strpos($sig, '{');
    if ($brace !== false && $brace > 0) {
      $sig = rtrim(substr($sig, 0, $brace));
    }
    $sig = rtrim($sig, "{ \t");
    if ($sig === '') {
      $sig = trim($raw);
    }
    if (strlen($sig) > 120) {
      $cut = function_exists('mb_substr') ? mb_substr($sig, 0, 120) : substr($sig, 0, 120);
      $sig = rtrim($cut) . '…';
    }
    return $sig;
  }

  /** Whatever sits between the outermost parens of a signature-ish line. */
  private function betweenParens(string $sig): string
  {
    $open = strpos($sig, '(');
    if ($open === false) {
      return '';
    }
    $close = strpos($sig, ')', $open);
    if ($close === false) {
      return rtrim(substr($sig, $open + 1));
    }
    return substr($sig, $open + 1, $close - $open - 1);
  }

  /** Text after the first '(' up to end-of-line or '{' (JS signatures). */
  private function parenPart(string $code): string
  {
    $part = $this->betweenParens(trim($code));
    return $part . ')';
  }
}
