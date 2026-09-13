<?php

declare(strict_types=1);

/**
 * ═══════════════════════════════════════════════════════════════════════════
 *  QUIRKY IDE — DYNAMIC SPEECH ENGINE SERVICE (v14)
 * ═══════════════════════════════════════════════════════════════════════════
 *
 *  Owns the DOWNLOAD side of offline TTS/STT. Nothing here ships in the APK:
 *
 *    catalog.json        bundled seed list (copied by MainActivity)
 *    community.json      user-added remote catalog (cached)
 *    local-catalog.json  developer override (app/.speech/)
 *    registry.json       what is actually installed (read by SpeechManager)
 *
 *  Install sources for an entry's files:
 *    • URL download with resume (.part + Range), SHA-256 verification,
 *      SSE progress streaming, exec-bit for runtime binaries.
 *    • "pkg": "<name>" → route through IdePkg (Termux repo) when the
 *      runtime exists there (e.g. whisper-cpp). Zero re-downloading.
 *    • Offline .zip packages containing quirky.speech.json (importLocal).
 *
 *  Community extensibility:
 *    A "catalog" is just JSON with this shape:
 *      { "catalogVersion":1, "entries":[ <entry>, ... ] }
 *    Anyone can host one (raw GitHub URL) — users paste the URL in the
 *    Model Manager. SSRF guard blocks loopback/private hosts.
 * ═══════════════════════════════════════════════════════════════════════════
 */
class IdeSpeech
{
  private array $config;
  private IdeSecurity $security;
  private string $speechDir;

  public function __construct(array $config, IdeSecurity $security)
  {
    $this->config   = $config;
    $this->security = $security;

    /* Android: files/speech (set by MainActivity). Desktop dev fallback. */
    $env = getenv('QUIRKY_SPEECH');
    $this->speechDir = ($env !== false && $env !== '')
      ? rtrim(str_replace('\\', '/', $env), '/')
      : IDE_APP . '/.speech';
    if (!is_dir($this->speechDir)) {
      @mkdir($thisDir = $this->speechDir, 0777, true);
    }
  }

  /* ═══════════════════════════════════════════════════════════
       PATHS + REGISTRY
       ═══════════════════════════════════════════════════════════ */

  private function registryPath(): string
  {
    return $this->speechDir . '/registry.json';
  }
  private function catalogPath(): string
  {
    return $this->speechDir . '/catalog.json';
  }
  private function communityPath(): string
  {
    return $this->speechDir . '/community.json';
  }
  private function communityUrlPath(): string
  {
    return $this->speechDir . '/community-url.txt';
  }
  private function localOverridePath(): string
  {
    return IDE_APP . '/.speech/local-catalog.json';
  }

  private function loadRegistry(): array
  {
    $raw = @file_get_contents($this->registryPath());
    $dec = $raw !== false ? json_decode($raw, true) : null;
    return is_array($dec) ? $dec : ['version' => 1, 'runtimes' => [], 'models' => []];
  }

  private function saveRegistry(array $reg): void
  {
    @file_put_contents(
      $this->registryPath(),
      json_encode($reg, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
      LOCK_EX
    );
  }

  /* ═══════════════════════════════════════════════════════════
       CATALOG MERGE — bundled + community + local override
       ═══════════════════════════════════════════════════════════ */

  public function getCatalog(): array
  {
    $merged = [];
    foreach ([$this->catalogPath(), $this->communityPath(), $this->localOverridePath()] as $path) {
      if (!is_file($path)) continue;
      $dec = json_decode((string) @file_get_contents($path), true);
      if (!is_array($dec) || empty($dec['entries'])) continue;
      foreach ($dec['entries'] as $entry) {
        if (is_array($entry) && !empty($entry['id'])) {
          $merged[(string) $entry['id']] = $entry; // later sources win
        }
      }
    }

    $reg = $this->loadRegistry();
    $entries = [];
    foreach ($merged as $id => $entry) {
      $installed = false;
      $bytes = 0;
      if (($entry['kind'] ?? '') === 'runtime') {
        $installed = !empty($reg['runtimes'][$id]['installed']);
      } else {
        $installed = !empty($reg['models'][$id]['installed']);
        $bytes = (int) ($reg['models'][$id]['bytes'] ?? 0);
      }
      $entry['installed'] = $installed;
      $entry['bytesOnDisk'] = $bytes;
      $entries[] = $entry;
    }

    return [
      'entries'      => $entries,
      'communityUrl' => trim((string) @file_get_contents($this->communityUrlPath())),
      'speechDir'    => $this->speechDir,
    ];
  }

  private function findEntry(string $id): ?array
  {
    foreach ($this->getCatalog()['entries'] as $entry) {
      if (($entry['id'] ?? '') === $id) return $entry;
    }
    return null;
  }

  /* ═══════════════════════════════════════════════════════════
       COMMUNITY CATALOG REFRESH (SSRF-guarded)
       ═══════════════════════════════════════════════════════════ */

  public function refreshCommunity(string $url): array
  {
    $url = trim($url);
    if ($url === '') {
      @unlink($this->communityUrlPath());
      @unlink($this->communityPath());
      return ['ok' => true, 'cleared' => true];
    }
    $this->assertSafeUrl($url);

    $json = $this->httpGet($url, 1048576); // 1 MB cap
    $dec = json_decode($json, true);
    if (!is_array($dec) || !isset($dec['entries']) || !is_array($dec['entries'])) {
      return ['ok' => false, 'error' => 'That URL does not serve a speech catalog JSON'];
    }
    @file_put_contents($this->communityPath(), $json, LOCK_EX);
    @file_put_contents($this->communityUrlPath(), $url, LOCK_EX);
    return ['ok' => true, 'count' => count($dec['entries'])];
  }

  /** Same discipline as WorkshopPackage::parseSource — no loopback/private hosts. */
  private function assertSafeUrl(string $url): void
  {
    $parts = parse_url($url);
    $scheme = strtolower((string) ($parts['scheme'] ?? ''));
    $host = strtolower((string) ($parts['host'] ?? ''));
    if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
      throw new RuntimeException('Only http(s) URLs are allowed');
    }
    if (in_array($host, ['localhost', '127.0.0.1', '0.0.0.0', '::1'], true)) {
      throw new RuntimeException('Local URLs are not allowed as catalogs');
    }
    if (preg_match('/(^10\.|^192\.168\.|^172\.(1[6-9]|2\d|3[01])\.|^169\.254\.)/', $host)) {
      throw new RuntimeException('Private-network URLs are not allowed');
    }
  }

  private function httpGet(string $url, int $maxBytes): string
  {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_MAXREDIRS      => 5,
      CURLOPT_CONNECTTIMEOUT => 20,
      CURLOPT_TIMEOUT        => 120,
      CURLOPT_MAXFILESIZE    => $maxBytes,
    ]);
    $ca = $this->caBundle();
    if ($ca !== null) curl_setopt($ch, CURLOPT_CAINFO, $ca);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($body === false || $code < 200 || $code >= 300) {
      throw new RuntimeException('Download failed (' . ($err ?: 'HTTP ' . $code) . ')');
    }
    return (string) $body;
  }

  private function caBundle(): ?string
  {
    $prefix = getenv('QUIRKY_PREFIX');
    $candidates = [];
    if ($prefix !== false && $prefix !== '') {
      $candidates[] = rtrim($prefix, '/\\') . '/etc/tls/cacert.pem';
    }
    foreach (glob('C:/laragon/etc/ssl/cacert.pem') ?: [] as $p) $candidates[] = $p;
    foreach ($candidates as $c) {
      if (is_file($c) && is_readable($c)) return $c;
    }
    return null;
  }

  /* ═══════════════════════════════════════════════════════════
       INSTALL — SSE STREAM
       events: status · progress · warn · error · done
       ═══════════════════════════════════════════════════════════ */

  public function installStream(string $id): void
  {
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('X-Accel-Buffering: no');

    try {
      $entry = $this->findEntry($id);
      if ($entry === null) {
        $this->sse('error', ['error' => 'Unknown entry: ' . $id]);
        return;
      }
      $kind = (string) ($entry['kind'] ?? '');

      if ($kind === 'runtime') {
        $this->installRuntime($entry);
      } elseif ($kind === 'tts-model' || $kind === 'stt-model') {
        /* dependency first: the runtime this model needs */
        $runtimeId = (string) ($entry['runtime'] ?? '');
        $reg = $this->loadRegistry();
        if ($runtimeId !== '' && empty($reg['runtimes'][$runtimeId]['installed'])) {
          $rt = $this->findEntry($runtimeId);
          if ($rt === null) {
            $this->sse('error', ['error' => 'Runtime "' . $runtimeId . '" not in catalog']);
            return;
          }
          $this->sse('status', ['note' => 'Installing required runtime: ' . ($rt['name'] ?? $runtimeId)]);
          $this->installRuntime($rt);
        }
        $this->installModel($entry);
      } else {
        $this->sse('error', ['error' => 'Unsupported kind: ' . $kind]);
        return;
      }
    } catch (\Throwable $e) {
      $this->sse('error', ['error' => $e->getMessage()]);
    }
  }

  private function installRuntime(array $entry): void
  {
    $id = (string) $entry['id'];
    $reg = $this->loadRegistry();
    $pkgError = '';
    /* ★ pkg-route: runtime exists in the Termux repo → let IdePkg do it.
           The registry then points at the prefix binary instead.
           ★ FIX: Pkg.php was never loaded in this request (it is not in the
           api.php classmap), so class_exists('IdePkg') was ALWAYS false and
           the pkg route was silently skipped; and even if it had run, the
           IdePkg constructor REQUIRES the prefix as a 3rd argument. Both
           mistakes made whisper-cpp fall into the direct-download path,
           which then died with "Entry has no files" (whisper-cpp has no
           files BY DESIGN — it can only come from pkg). */
    if (!empty($entry['pkg']) && getenv('QUIRKY_PREFIX')) {
      $this->sse('status', ['note' => 'Installing via pkg: ' . $entry['pkg']]);
      try {
        require_once __DIR__ . '/Pkg.php';
        $pkg = new IdePkg(
          $this->config,
          $this->security,
          rtrim((string) getenv('QUIRKY_PREFIX'), '/')
        );
        if (method_exists($pkg, 'setEmitter')) {
          $pkg->setEmitter(function (string $event, $payload): void {
            if ($event === 'prog') return; // progress rows are terminal UI, not log lines
            $this->sse('out', ['line' => is_string($payload) ? $payload : (string) json_encode($payload)]);
          });
        }
        $res = $pkg->handleCommand('install ' . $entry['pkg']);
        if ((int) ($res['exitCode'] ?? 1) !== 0) {
          throw new RuntimeException('pkg install reported an error');
        }
        $binRel = $this->locatePkgBinary((string) $entry['pkg'], (string) ($entry['binary'] ?? ''));
        if ($binRel === null) throw new RuntimeException('pkg install finished but binary not found');
        /* binaries installed via pkg live under the PREFIX, not speechDir —
                   registry stores an absolute-ish marker the Java side resolves */
        $reg['runtimes'][$id] = [
          'installed'  => true,
          'installedAt' => time(),
          'contract'   => (string) ($entry['contract'] ?? ''),
          'binary'     => $binRel,
          'absolute'   => true,
        ];
        $this->saveRegistry($reg);
        $this->sse('status', ['note' => 'Runtime installed: ' . $id]);
        return;
      } catch (\Throwable $e) {
        $pkgError = $e->getMessage();
        $this->sse('warn', ['note' => 'pkg route failed (' . $pkgError . ')']);
      }
    }
    /* ★ FIX: entries that ONLY know how to install via pkg must not fall
           through to the downloader — report the real reason instead of the
           cryptic "Entry has no files". */
    if (empty($entry['files'])) {
      throw new RuntimeException(
        $pkgError !== ''
          ? '"' . $id . '" installs through pkg only, and the pkg route failed: ' . $pkgError
          : '"' . $id . '" has no downloadable files in the catalog'
      );
    }
    $this->downloadEntryFiles($entry);
    /* ★ FIX: the catalog can only guess where the binary lands after
           extraction (archive layouts vary). Locate it by name anywhere
           under the speech dir, remember the REAL relative path in the
           registry, and make sure it is executable. */
    $binary    = (string) ($entry['binary'] ?? '');
    $asrBinary = '';

    /* ★ v15.5 — JNI-LIBS RUNTIME.
           The official sherpa-onnx *android* archive contains ONLY native
           libraries (jniLibs/<abi>/lib*.so), never an executable. So when we
           see that layout we weld the libraries into a tiny driver program
           ("sherpa-bridge") right on the device with clang, and register the
           wrapper script as both the TTS and the STT binary. */
    $jniDir = $this->speechDir . '/runtimes/jniLibs/arm64-v8a';
    if (is_file($jniDir . '/libsherpa-onnx-jni.so') && is_file($jniDir . '/libonnxruntime.so')) {
      $wrapperRel = $this->ensureSherpaBridge($jniDir);
      $binary     = $wrapperRel;
      $asrBinary  = $wrapperRel;
    } else {
      /* classic layout: look for the binary the catalog named */
      $binAbs = $this->speechDir . '/' . ltrim($binary, '/');
      if (!is_file($binAbs)) {
        $found = $this->findFileRecursive($this->speechDir, basename($binary));
        if ($found !== null) {
          $binary = ltrim(substr(str_replace('\\', '/', $found), strlen($this->speechDir) + 1), '/');
          $binAbs = $found;
        }
      }
      if (!is_file($binAbs)) {
        throw new RuntimeException('Extraction finished but the runtime binary "'
          . basename($binary) . '" was not found. Extracted: '
          . $this->listTree($this->speechDir . '/runtimes'));
      }
      @chmod($binAbs, 0755);
      $sib = dirname($binAbs) . '/sherpa-onnx-offline';
      if (is_file($sib)) {
        @chmod($sib, 0755);
        $asrBinary = ltrim(substr(str_replace('\\', '/', $sib), strlen($this->speechDir) + 1), '/');
      }
    }

    $reg['runtimes'][$id] = [
      'installed'  => true,
      'installedAt' => time(),
      'contract'   => (string) ($entry['contract'] ?? ''),
      'binary'     => $binary,
      'jniDir'     => ltrim(substr($jniDir, strlen($this->speechDir) + 1), '/'),
    ];
    if ($asrBinary !== '') {
      $reg['runtimes'][$id]['asrBinary'] = $asrBinary;
    }
    $this->saveRegistry($reg);
    $this->sse('status', ['note' => 'Runtime installed: ' . $id]);
  }

  /* ═══════════════════════════════════════════════════════════
       v15.5 — ON-DEVICE BRIDGE BUILDER
       Compiles a minimal C driver against the downloaded sherpa-onnx
       JNI libraries and returns the registry-relative path of a wrapper
       script that sets LD_LIBRARY_PATH and execs it.
       ═══════════════════════════════════════════════════════════ */
  private function ensureSherpaBridge(string $jniDir): string
  {
    $rtDir   = $this->speechDir . '/runtimes';
    $bridge  = $rtDir . '/sherpa-bridge';
    $wrapper = $rtDir . '/sherpa-bridge-run';
    $src     = $rtDir . '/sherpa-bridge.c';
    $marker  = $rtDir . '/.bridge-ok';
    $soMtime = (int) @filemtime($jniDir . '/libsherpa-onnx-jni.so');

    if (
      is_file($wrapper) && is_file($marker)
      && (int) @file_get_contents($marker) === $soMtime
    ) {
      return ltrim(substr($wrapper, strlen($this->speechDir) + 1), '/');
    }

    $this->sse('status', ['note' => 'Building on-device engine bridge (one-time)…']);
    file_put_contents($src, $this->bridgeSource());

    /* ── Locate prefix / applibs / clang (derivation fallbacks) ──
       speechDir = files/speech  →  prefix = files/usr, applibs = files/applibs,
       so even a missing env var can never break the paths again. */
    $prefix = rtrim((string) getenv('QUIRKY_PREFIX'), '/');
    if ($prefix === '') $prefix = dirname($this->speechDir) . '/usr';
    $applib = rtrim((string) getenv('QUIRKY_APPLIB'), '/');
    if ($applib === '') $applib = dirname($this->speechDir) . '/applibs';

    $clang = is_file($prefix . '/bin/clang') ? $prefix . '/bin/clang' : null;
    if ($clang === null) {
      $this->sse('status', ['note' => 'clang missing — trying pkg install clang (large, one-time)…']);
      try {
        require_once __DIR__ . '/Pkg.php';
        $pkg = new IdePkg($this->config, $this->security, $prefix);
        if (method_exists($pkg, 'setEmitter')) {
          $pkg->setEmitter(function (string $event, $payload): void {
            if ($event === 'prog') return;
            $this->sse('out', ['line' => is_string($payload) ? $payload : (string) json_encode($payload)]);
          });
        }
        $res = $pkg->handleCommand('install clang');
        if ((int) ($res['exitCode'] ?? 1) !== 0) {
          throw new RuntimeException('pkg install clang reported an error');
        }
      } catch (\Throwable $e) {
        throw new RuntimeException('clang is required but could not be installed ('
          . $e->getMessage() . '). Install it yourself in the Terminal: pkg install clang');
      }
      if (!is_file($prefix . '/bin/clang')) {
        throw new RuntimeException('clang install finished but the binary is missing — run: pkg repair clang');
      }
      $clang = $prefix . '/bin/clang';
    }

    /* ── Linker toolbox: a private folder with correctly-NAMED copies
         of every support library clang may ask for. ── */
    $linklib = $rtDir . '/linklib';
    if (!is_dir($linklib)) @mkdir($linklib, 0777, true);
    $this->populateLinklib($linklib, $prefix, $applib);

    $env = array_merge((array) @getenv(), [
      'LD_LIBRARY_PATH' => $linklib . ':' . $prefix . '/lib' . ':' . $applib,
      'PATH'            => $prefix . '/bin:/system/bin',
      'TMPDIR'          => $prefix . '/tmp',
      'HOME'            => $prefix . '/tmp',
    ]);

    /* ── Preflight + SELF-HEAL loop: start clang; if the linker still
         complains "cannot find libX.so.N", create that name and retry. ── */
    $lastOut = '';
    for ($attempt = 0; $attempt < 24; $attempt++) {
      $code = $this->runCapture([$clang, '--version'], $env, $rtDir, $lastOut);
      if ($code === 0) break;
      if (
        preg_match('/cannot find "([^"]+)"/', $lastOut, $m)
        && $this->healMissingLib($m[1], $linklib, $prefix, $applib)
      ) {
        $this->sse('status', ['note' => 'Linker self-heal: provided ' . $m[1]]);
        continue;
      }
      throw new RuntimeException('clang cannot start on this device: '
        . substr(trim(preg_replace('/\s+/', ' ', $lastOut)), 0, 400));
    }

    /* ── Compile the bridge ── */
    $argv = [
      $clang,
      '-std=c11',
      '-O2',
      '-Wall',
      '-Wno-unused-function',
      '-o',
      $bridge,
      $src,
      '-ldl',
    ];
    $err = '';
    $exit = $this->runCapture($argv, $env, $rtDir, $err);
    if ($exit !== 0 || !is_file($bridge)) {
      if (preg_match('/cannot find "([^"]+)"/', $err, $m)) {
        throw new RuntimeException('Bridge build needs "' . $m[1] . '" but no installed library provides it — run in Terminal: pkg repair clang, then retry');
      }
      throw new RuntimeException('Bridge build failed (exit ' . $exit . '): ' . substr(trim($err), -600));
    }
    @chmod($bridge, 0755);

    /* ★ FIX (v16.2): No wrapper script. Java's ProcessBuilder sets
        LD_LIBRARY_PATH directly, which avoids the shebang-interpreter
        ENOENT that Android's SELinux app namespace can produce for
        #!/system/bin/sh scripts. Register the compiled binary + the
        jniLibs directory; SpeechManager.java reads both. */
    @file_put_contents($marker, (string) $soMtime);
    $this->sse('status', ['note' => 'Engine bridge built successfully']);
    return ltrim(substr($bridge, strlen($this->speechDir) + 1), '/');
  }

  /** Run a command (no shell) with a custom environment; capture output. */
  private function runCapture(array $argv, array $env, string $cwd, string &$out): int
  {
    $out = '';
    $proc = @proc_open($argv, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $cwd, $env);
    if (!is_resource($proc)) {
      $out = 'could not start process';
      return -1;
    }
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);
    $start = microtime(true);
    while (true) {
      $out .= (string) stream_get_contents($pipes[1]);
      $out .= (string) stream_get_contents($pipes[2]);
      $st = proc_get_status($proc);
      if (!$st['running']) {
        $out .= (string) stream_get_contents($pipes[1]);
        $out .= (string) stream_get_contents($pipes[2]);
        break;
      }
      if ((microtime(true) - $start) > 300) {
        proc_terminate($proc, 9);
        usleep(50000);
        break;
      }
      usleep(30000);
    }
    fclose($pipes[1]);
    fclose($pipes[2]);
    return proc_close($proc);
  }

  /** Proactively provide the versioned SONAME names the linker asks for. */
  private function populateLinklib(string $linklib, string $prefix, string $applib): void
  {
    $map = [
      'libz.so' => ['libz.so.1'],
      'libbz2.so' => ['libbz2.so.1.0', 'libbz2.so.1'],
      'liblzma.so' => ['liblzma.so.5'],
      'libzstd.so' => ['libzstd.so.1'],
      'libssl.so' => ['libssl.so.3'],
      'libcrypto.so' => ['libcrypto.so.3'],
      'libcurl.so' => ['libcurl.so.4'],
      'libsqlite3.so' => ['libsqlite3.so.0'],
      'libxml2.so' => ['libxml2.so.2'],
      'libncursesw.so' => ['libncursesw.so.6'],
      'libreadline.so' => ['libreadline.so.8'],
      'libedit.so' => ['libedit.so.0'],
      'libffi.so' => ['libffi.so.8'],
      'libiconv.so' => ['libiconv.so.2'],
      'libicudata.so' => ['libicudata.so.76'],
      'libicuuc.so' => ['libicuuc.so.76'],
      'libicui18n.so' => ['libicui18n.so.76'],
      'libicuio.so' => ['libicuio.so.76'],
      'libnghttp2.so' => ['libnghttp2.so.14'],
      'libssh2.so' => ['libssh2.so.1'],
      'libuuid.so' => ['libuuid.so.1'],
      'libc++_shared.so' => ['libc++_shared.so'],
      'libandroid-support.so' => ['libandroid-support.so'],
    ];
    foreach ($map as $base => $sonames) {
      foreach ($sonames as $soname) {
        $this->healMissingLib($soname, $linklib, $prefix, $applib, $base);
      }
    }
  }

  /** Create linklib/<missing> pointing at a real library file.
   *  Returns true when a new link was created (false = nothing to do). */
  private function healMissingLib(string $missing, string $linklib, string $prefix, string $applib, string $base = ''): bool
  {
    $target = $linklib . '/' . $missing;
    if (file_exists($target)) return false;
    if ($base === '') {
      $base = preg_replace('/(\.so)(\.[0-9][0-9.]*)$/', '$1', $missing) ?? $missing;
    }
    $candidates = [
      $prefix . '/lib/' . $missing,
      $applib . '/' . $missing,
      $prefix . '/lib/' . $base,
      $applib . '/' . $base,
    ];
    foreach ($candidates as $c) {
      if (is_file($c)) {
        @symlink($c, $target);
        return file_exists($target);
      }
    }
    return false;
  }

  private function bridgeSource(): string
  {
    return <<<'CBRIDGE'
    /* quirky sherpa-bridge v2: TTS + Whisper ASR via libsherpa-onnx-jni.so */
    #include <dlfcn.h>
    #include <stdio.h>
    #include <stdlib.h>
    #include <string.h>
    #include <stdint.h>

    typedef struct {
        const char *model;
        const char *lexicon;
        const char *tokens;
        const char *data_dir;
        const char *dict_dir;
        float noise_scale;
        float noise_scale_w;
        float length_scale;
    } VitsCfg;

    typedef struct {
        VitsCfg vits;
        const char *provider;
        int32_t num_threads;
        int32_t debug;
    } TtsModelCfg;

    typedef struct {
        TtsModelCfg model;
        const char *rule_fsts;
        const char *rule_fars;
        int32_t max_num_sentences;
    } TtsCfg;

    typedef void Tts;

    typedef struct {
        const float *samples;
        int32_t sample_rate;
        int32_t n;
    } Audio;

    typedef struct {
        const char *encoder;
        const char *decoder;
        const char *language;
        int32_t task;
    } WhisperCfg;

    typedef struct {
        const char *encoder;
        const char *decoder;
        const char *joiner;
        const char *tokens;
        int32_t num_threads;
        int32_t debug;
        const char *provider;
        const char *model_type;
        const char *modeling_unit;
        const char *bpe_vocab;
        WhisperCfg whisper;
    } AsrModelCfg;

    typedef struct {
        int32_t sample_rate;
        int32_t feature_dim;
        AsrModelCfg model_config;
        const char *decoding_method;
        int32_t max_active_paths;
        const char *hotwords_file;
        float hotwords_score;
        const char *rule_fsts;
        const char *rule_fars;
    } AsrCfg;

    typedef void Rec;
    typedef void Stream;
    typedef void Result;

    static void *H;

    static void *need(const char *n) {
        void *p = dlsym(H, n);
        if (!p) {
            fprintf(stderr, "BRIDGE MISSING SYMBOL: %s\n", n);
            exit(3);
        }
        return p;
    }

    static void write_wav(const char *path, const float *s, int32_t n, int32_t rate) {
        FILE *f = fopen(path, "wb");
        if (!f) {
            fprintf(stderr, "cannot write %s\n", path);
            exit(4);
        }
        int32_t data = n * 2;
        uint8_t h[44];
        memset(h, 0, 44);
        memcpy(h, "RIFF", 4);
        *(int32_t *)(h + 4) = 36 + data;
        memcpy(h + 8, "WAVE", 4);
        memcpy(h + 12, "fmt ", 4);
        *(int32_t *)(h + 16) = 16;
        *(int16_t *)(h + 20) = 1;
        *(int16_t *)(h + 22) = 1;
        *(int32_t *)(h + 24) = rate;
        *(int32_t *)(h + 28) = rate * 2;
        *(int16_t *)(h + 32) = 2;
        *(int16_t *)(h + 34) = 16;
        memcpy(h + 36, "data", 4);
        *(int32_t *)(h + 40) = data;
        fwrite(h, 1, 44, f);
        for (int32_t i = 0; i < n; i++) {
            float v = s[i];
            if (v > 1) v = 1;
            if (v < -1) v = -1;
            int16_t q = (int16_t)(v * 32767.0f);
            fwrite(&q, 2, 1, f);
        }
        fclose(f);
    }

    static int read_wav(const char *path, float **out, int32_t *n, int32_t *rate) {
        FILE *f = fopen(path, "rb");
        if (!f) return -1;
        uint8_t h[44];
        if (fread(h, 1, 44, f) != 44) {
            fclose(f);
            return -1;
        }
        *rate = *(int32_t *)(h + 24);
        int32_t data = *(int32_t *)(h + 40);
        int16_t *q = (int16_t *)malloc(data);
        if (!q) {
            fclose(f);
            return -1;
        }
        if ((int32_t)fread(q, 1, data, f) != data) {
            free(q);
            fclose(f);
            return -1;
        }
        fclose(f);
        int32_t cnt = data / 2;
        float *s = (float *)malloc(cnt * sizeof(float));
        for (int32_t i = 0; i < cnt; i++) {
            s[i] = q[i] / 32768.0f;
        }
        free(q);
        *out = s;
        *n = cnt;
        return 0;
    }

    int main(int argc, char **argv) {
        H = dlopen("libsherpa-onnx-jni.so", RTLD_NOW | RTLD_LOCAL);
        if (!H) {
            H = dlopen("libsherpa-onnx-jni.so", RTLD_NOW | RTLD_GLOBAL);
        }
        if (!H) {
            fprintf(stderr, "BRIDGE: cannot load libsherpa-onnx-jni.so: %s\n", dlerror());
            return 3;
        }

        const char *mode = (argc > 1) ? argv[1] : "";
        const char *model = NULL;
        const char *tokens = NULL;
        const char *dataDir = NULL;
        const char *outWav = NULL;
        const char *enc = NULL;
        const char *dec = NULL;
        const char *wav = NULL;
        const char *lang = "en";
        float ls = 1.0f;
        int threads = 4;
        const char *text = "";

        for (int i = 2; i < argc; i++) {
            const char *a = argv[i];
            if (!strcmp(a, "--vits-model")) {
                model = argv[++i];
            } else if (!strcmp(a, "--vits-tokens")) {
                tokens = argv[++i];
            } else if (!strcmp(a, "--vits-data-dir")) {
                dataDir = argv[++i];
            } else if (!strcmp(a, "--output-filename")) {
                outWav = argv[++i];
            } else if (!strcmp(a, "--length-scale")) {
                ls = (float)atof(argv[++i]);
            } else if (!strcmp(a, "--num-threads") || !strcmp(a, "-t")) {
                threads = atoi(argv[++i]);
            } else if (!strcmp(a, "-m")) {
                model = argv[++i];
            } else if (!strcmp(a, "-f")) {
                wav = argv[++i];
            } else if (!strcmp(a, "-l")) {
                lang = argv[++i];
                if (!strcmp(lang, "auto")) {
                    lang = "en";
                }
            } else if (!strcmp(a, "-nt")) {
                /* no timestamps flag - accepted but unused */
            } else if (!strcmp(a, "--whisper-encoder")) {
                enc = argv[++i];
            } else if (!strcmp(a, "--whisper-decoder")) {
                dec = argv[++i];
            } else if (!strcmp(a, "--tokens")) {
                tokens = argv[++i];
            } else if (!strcmp(a, "--whisper-language")) {
                lang = argv[++i];
                if (!strcmp(lang, "auto")) {
                    lang = "en";
                }
            } else {
                text = a;
            }
        }

        /* ---- TTS MODE ---- */
        if (!strcmp(mode, "tts") && model) {
            Tts *(*create)(const TtsCfg *) =
                (Tts *(*)(const TtsCfg *))need("SherpaOnnxCreateOfflineTts");
            const Audio *(*gen)(const Tts *, const char *, int32_t, float) =
                (const Audio *(*)(const Tts *, const char *, int32_t, float))need("SherpaOnnxOfflineTtsGenerate");
            void (*destroy)(const Audio *) =
                (void (*)(const Audio *))dlsym(H, "SherpaOnnxDestroyOfflineTtsGeneratedAudio");

            TtsCfg cfg;
            memset(&cfg, 0, sizeof(cfg));
            cfg.model.vits.model = model;
            cfg.model.vits.lexicon = "";
            cfg.model.vits.tokens = tokens ? tokens : "";
            cfg.model.vits.data_dir = dataDir ? dataDir : "";
            cfg.model.vits.dict_dir = "";
            cfg.model.vits.noise_scale = 0.667f;
            cfg.model.vits.noise_scale_w = 0.8f;
            cfg.model.vits.length_scale = ls;
            cfg.model.provider = "cpu";
            cfg.model.num_threads = threads;
            cfg.model.debug = 0;
            cfg.rule_fsts = "";
            cfg.rule_fars = "";
            cfg.max_num_sentences = 1;

            Tts *tts = create(&cfg);
            if (!tts) {
                fprintf(stderr, "BRIDGE: tts create failed\n");
                return 5;
            }
            const Audio *a = gen(tts, text, 0, 1.0f);
            if (!a || a->n <= 0) {
                fprintf(stderr, "BRIDGE: tts generated nothing\n");
                return 5;
            }
            write_wav(outWav ? outWav : "out.wav", a->samples, a->n, a->sample_rate);
            if (destroy) {
                destroy(a);
            }
            return 0;
        }

        /* ---- ASR MODE ---- */
        if (!strcmp(mode, "asr") && model) {
            char dec2[1024];
            char tok2[1024];

            const char *e = enc ? enc : model;

            /* derive decoder path from encoder path */
            const char *d = dec;
            if (!d) {
                snprintf(dec2, sizeof(dec2), "%s", e);
                char *p = strstr(dec2, ".encoder.");
                if (p) {
                    strcpy(p, ".decoder.int8.onnx");
                }
                d = dec2;
            }

            /* derive tokens path */
            const char *t = tokens;
            if (!t) {
                char base[512];
                snprintf(base, sizeof(base), "%s", e);
                char *p = strstr(base, ".encoder.");
                if (p) {
                    *p = 0;
                }
                const char *slash = strrchr(base, '/');
                const char *basename = slash ? slash + 1 : base;

                char dir[768];
                snprintf(dir, sizeof(dir), "%s", e);
                char *s2 = strrchr(dir, '/');
                if (s2) {
                    *s2 = 0;
                    snprintf(tok2, sizeof(tok2), "%s/%s.tokens.txt", dir, basename);
                } else {
                    snprintf(tok2, sizeof(tok2), "%s.tokens.txt", basename);
                }
                t = tok2;
            }

            Rec *(*createR)(const AsrCfg *) =
                (Rec *(*)(const AsrCfg *))need("SherpaOnnxCreateOfflineRecognizer");
            Stream *(*createS)(const Rec *) =
                (Stream *(*)(const Rec *))need("SherpaOnnxCreateOfflineStream");
            void (*accept)(Stream *, int32_t, const float *, int32_t) =
                (void (*)(Stream *, int32_t, const float *, int32_t))need("SherpaOnnxAcceptWaveform");
            void (*finish)(Stream *) =
                (void (*)(Stream *))need("SherpaOnnxInputFinished");
            void (*decode)(const Rec *, Stream *) =
                (void (*)(const Rec *, Stream *))need("SherpaOnnxDecodeOfflineStream");
            const Result *(*getres)(const Stream *) =
                (const Result *(*)(const Stream *))need("SherpaOnnxGetOfflineStreamResult");
            const char *(*gettext)(const Result *) =
                (const char *(*)(const Result *))need("SherpaOnnxOfflineRecognizerResultGetText");

            AsrCfg cfg;
            memset(&cfg, 0, sizeof(cfg));
            cfg.sample_rate = 16000;
            cfg.feature_dim = 80;
            cfg.model_config.whisper.encoder = e;
            cfg.model_config.whisper.decoder = d;
            cfg.model_config.whisper.language = lang;
            cfg.model_config.whisper.task = 0;
            cfg.model_config.tokens = t;
            cfg.model_config.num_threads = threads;
            cfg.model_config.debug = 0;
            cfg.model_config.provider = "cpu";
            cfg.model_config.model_type = "whisper";
            cfg.model_config.modeling_unit = "cjkchar";
            cfg.model_config.bpe_vocab = "";
            cfg.decoding_method = "greedy_search";
            cfg.max_active_paths = 4;
            cfg.hotwords_file = "";
            cfg.hotwords_score = 1.5f;
            cfg.rule_fsts = "";
            cfg.rule_fars = "";

            Rec *r = createR(&cfg);
            if (!r) {
                fprintf(stderr, "BRIDGE: asr create failed\n");
                return 6;
            }
            float *samples = NULL;
            int32_t n = 0;
            int32_t rate = 16000;
            if (!wav || read_wav(wav, &samples, &n, &rate) != 0) {
                fprintf(stderr, "BRIDGE: cannot read wav\n");
                return 6;
            }
            Stream *st = createS(r);
            accept(st, rate, samples, n);
            finish(st);
            decode(r, st);
            const Result *res = getres(st);
            printf("%s\n", res ? gettext(res) : "");
            free(samples);
            return 0;
        }

        fprintf(stderr, "usage: sherpa-bridge tts|asr ...\n");
        return 2;
    }
    CBRIDGE;
  }

  private function installModel(array $entry): void
  {
    $id = (string) $entry['id'];
    $this->downloadEntryFiles($entry);
    /* ★ FIX: same layout-agnostic correction as installRuntime — if the
           model file did not land exactly where the catalog guessed, find
           it by name and store the real path in the registry. */
    $entryRel = (string) ($entry['entry'] ?? '');
    $entryAbs = '';
    if ($entryRel !== '') {
      $entryAbs = $this->speechDir . '/' . ltrim($entryRel, '/');
      if (!is_file($entryAbs)) {
        $found = $this->findFileRecursive($this->speechDir . '/models', basename($entryRel));
        if ($found !== null) {
          $entryRel = ltrim(substr(str_replace('\\', '/', $found), strlen($this->speechDir) + 1), '/');
          $entryAbs = $found;
        }
      }
      if (!is_file($entryAbs)) {
        throw new RuntimeException('Extraction finished but the model file "' . basename($entryRel) . '" was not found');
      }
    }
    $bytes = 0;
    foreach ((array) ($entry['files'] ?? []) as $f) {
      $p = $this->speechDir . '/' . ltrim((string) ($f['dest'] ?? ''), '/');
      if (is_file($p)) $bytes += (int) filesize($p);
    }
    if ($bytes === 0 && $entryAbs !== '' && is_file($entryAbs)) {
      $bytes = (int) @filesize($entryAbs);
    }
    $reg = $this->loadRegistry();
    $reg = $this->loadRegistry();
    $rec = [
      'kind'       => strpos((string) $entry['kind'], 'tts') === 0 ? 'tts' : 'stt',
      'runtime'    => (string) ($entry['runtime'] ?? ''),
      'name'       => (string) ($entry['name'] ?? $id),
      'lang'       => (string) ($entry['lang'] ?? ''),
      'entry'      => $entryRel,
      'bytes'      => $bytes,
      'installed'  => true,
      'installedAt' => time(),
    ];
    // ★ v15.4: carry the extra file pointers the Android side needs
    // (sherpa-onnx whisper models: encoder / decoder / tokens).
    foreach (['contract', 'encoder', 'decoder', 'tokens', 'dataDir'] as $k) {
      if (isset($entry[$k])) {
        $rel = ltrim((string) $entry[$k], '/');
        $abs = $this->speechDir . '/' . $rel;
        if (!is_file($abs) && !is_dir($abs)) {
          $f = $this->findFileRecursive($this->speechDir . '/models', basename($rel));
          if ($f !== null) {
            $rel = ltrim(substr(str_replace('\\', '/', $f), strlen($this->speechDir) + 1), '/');
          }
        }
        $rec[$k] = $rel;
      }
    }
    $reg['models'][$id] = $rec;
    $this->saveRegistry($reg);
    $this->sse('done', ['id' => $id, 'ok' => true, 'bytes' => $bytes]);
  }

  /** Downloads every file of an entry (resume + sha256 + exec bit). */
  private function downloadEntryFiles(array $entry): void
  {
    $files = (array) ($entry['files'] ?? []);
    if (!$files) throw new RuntimeException('Entry has no files');
    $idx = 0;
    foreach ($files as $f) {
      $idx++;
      $url  = (string) ($f['url'] ?? '');
      $dest = ltrim((string) ($f['dest'] ?? ''), '/');
      $sha  = strtolower(trim((string) ($f['sha256'] ?? '')));
      $extract = !empty($f['extract']);
      $stripComponents = (int) ($f['stripComponents'] ?? 0);

      if ($url === '' || $dest === '') throw new RuntimeException('Bad file entry in manifest');
      $this->assertSafeUrl($url);
      $this->sse('status', ['note' => "Downloading $idx/" . count($files) . ': ' . basename($dest)]);

      $abs = $this->speechDir . '/' . $dest;

      if ($extract) {
        // Download to a temporary file, then extract
        $tempFile = $abs . '.download';
        $tempDir = dirname($abs);
        if (!is_dir($tempDir)) @mkdir($tempDir, 0777, true);

        $this->downloadWithResume($url, $tempFile, (string) $entry['id'], $idx, count($files));

        // Verify SHA-256 before extracting
        if ($sha !== '') {
          $actual = strtolower((string) hash_file('sha256', $tempFile));
          if ($actual !== $sha) {
            @unlink($tempFile);
            throw new RuntimeException('SHA-256 mismatch for ' . basename($dest) . ' — archive deleted');
          }
        }

        // Extract the archive
        $this->sse('status', ['note' => "Extracting " . basename($dest) . "..."]);
        // ★ FIX: pass the INTENDED file name as a format hint — the
        // temp file ends in ".download", which made extractArchive()
        // report "Unsupported archive format: ….tar.bz2.download".
        $this->extractArchive($tempFile, $tempDir, $stripComponents, $dest);

        // Clean up the downloaded archive
        @unlink($tempFile);

        // Make binaries executable if specified
        if (!empty($f['exec'])) {
          // Find the binary in the extracted files
          $binaryName = basename($f['binary'] ?? '');
          if ($binaryName !== '') {
            $this->makeExecRecursive($tempDir, $binaryName);
          }
        }
      } else {
        // Regular file download (no extraction)
        $this->downloadWithResume($url, $abs, (string) $entry['id'], $idx, count($files));

        if ($sha !== '') {
          $actual = strtolower((string) hash_file('sha256', $abs));
          if ($actual !== $sha) {
            @unlink($abs);
            throw new RuntimeException('SHA-256 mismatch for ' . basename($dest) . ' — file deleted');
          }
        } else {
          $this->sse('warn', ['note' => basename($dest) . ' has no SHA-256 in the catalog — could not verify integrity']);
        }

        if (!empty($f['exec'])) @chmod($abs, 0755);
      }
    }
  }

  /**
   * Recursively find and chmod +x a binary by name within a directory.
   */
  private function makeExecRecursive(string $dir, string $binaryName): void
  {
    $entries = @scandir($dir);
    if (!$entries) return;
    foreach (array_diff($entries, ['.', '..']) as $entry) {
      $path = $dir . '/' . $entry;
      if (is_dir($path)) {
        $this->makeExecRecursive($path, $binaryName);
      } elseif ($entry === $binaryName && is_file($path)) {
        @chmod($path, 0755);
      }
    }
  }

  /**
   * Depth-first search for a file by exact name. Used to locate runtime
   * binaries / model files after extraction, because archive layouts vary
   * and the catalog can only guess destination paths.
   */
  private function findFileRecursive(string $dir, string $baseName, int $depth = 0): ?string
  {
    if ($depth > 6 || !is_dir($dir)) return null;
    $entries = @scandir($dir);
    if (!is_array($entries)) return null;
    foreach ($entries as $e) {
      if ($e === '.' || $e === '..') continue;
      $p = $dir . '/' . $e;
      if (is_file($p) && $e === $baseName) return $p;
    }
    foreach ($entries as $e) {
      if ($e === '.' || $e === '..') continue;
      $p = $dir . '/' . $e;
      if (is_dir($p)) {
        $found = $this->findFileRecursive($p, $baseName, $depth + 1);
        if ($found !== null) return $found;
      }
    }
    return null;
  }

  /** Fuzzy fallback: first file whose name starts with / contains the needle. */
  private function findFileFuzzy(string $dir, string $baseName, int $depth = 0): ?string
  {
    if ($depth > 6 || !is_dir($dir)) return null;
    $entries = @scandir($dir);
    if (!is_array($entries)) return null;
    foreach ($entries as $e) {
      if ($e === '.' || $e === '..') continue;
      $p = $dir . '/' . $e;
      if (is_file($p) && strpos($e, $baseName) === 0) return $p;
    }
    foreach ($entries as $e) {
      if ($e === '.' || $e === '..') continue;
      $p = $dir . '/' . $e;
      if (is_dir($p)) {
        $found = $this->findFileFuzzy($p, $baseName, $depth + 1);
        if ($found !== null) return $found;
      }
    }
    return null;
  }

  /** If a discovered binary sits in runtimes/<ABI>/bin/, move that ABI
   *  folder's contents (bin, lib, share…) up one level to runtimes/. */
  private function promoteAbiWrapper(string $foundAbs): void
  {
    $rel = ltrim(substr(str_replace('\\', '/', $foundAbs), strlen($this->speechDir) + 1), '/');
    $parts = explode('/', $rel);
    if (count($parts) < 4 || $parts[0] !== 'runtimes') return; // runtimes/<abi>/bin/<file>
    $abiDir  = $this->speechDir . '/runtimes/' . $parts[1];
    $rootDir = $this->speechDir . '/runtimes';
    if (!is_dir($abiDir)) return;
    foreach ((array) @scandir($abiDir) as $child) {
      if ($child === '.' || $child === '..') continue;
      if (!file_exists($rootDir . '/' . $child)) {
        @rename($abiDir . '/' . $child, $rootDir . '/' . $child);
      }
    }
    @rmdir($abiDir);
  }

  /** Compact listing of what an extraction produced (for honest errors). */
  private function listTree(string $dir, int $max = 15): string
  {
    $out = [];
    $base = rtrim($dir, '/');
    if (!is_dir($base)) return '(directory missing)';
    $it = new RecursiveIteratorIterator(
      new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS),
      RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($it as $item) {
      $out[] = ltrim(substr(str_replace('\\', '/', $item->getPathname()), strlen($base)), '/');
      if (count($out) >= $max) break;
    }
    return $out ? implode(', ', $out) : '(empty)';
  }

  /** curl download with .part resume + throttled SSE progress. */
  private function downloadWithResume(string $url, string $dest, string $entryId, int $fileIdx, int $fileCount): void
  {
    $dir = dirname($dest);
    if (!is_dir($dir)) @mkdir($dir, 0777, true);
    $part = $dest . '.part';
    $offset = is_file($part) ? (int) filesize($part) : 0;

    $attempt = function (int $off) use ($url, $part, $entryId, $fileIdx, $fileCount, $dest) {
      $fh = fopen($part, $off > 0 ? 'ab' : 'wb');
      if ($fh === false) throw new RuntimeException('Cannot open ' . $part);
      $lastEmit = 0.0;
      $ch = curl_init($url);
      curl_setopt_array($ch, [
        CURLOPT_FILE           => $fh,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_CONNECTTIMEOUT => 20,
        CURLOPT_LOW_SPEED_LIMIT => 1,
        CURLOPT_LOW_SPEED_TIME => 60,
      ]);
      if ($off > 0) curl_setopt($ch, CURLOPT_RESUME_FROM, $off);
      $ca = $this->caBundle();
      if ($ca !== null) curl_setopt($ch, CURLOPT_CAINFO, $ca);
      curl_setopt($ch, CURLOPT_NOPROGRESS, false);
      curl_setopt(
        $ch,
        CURLOPT_PROGRESSFUNCTION,
        function ($ch, $dlTotal, $dlNow) use (&$lastEmit, $entryId, $fileIdx, $fileCount, $off) {
          $now = microtime(true);
          if ($now - $lastEmit < 0.25) return 0;
          $lastEmit = $now;
          $this->sse('progress', [
            'id'       => $entryId,
            'file'     => $fileIdx,
            'files'    => $fileCount,
            'received' => $off + (int) $dlNow,
            'total'    => $dlTotal > 0 ? $off + (int) $dlTotal : 0,
          ]);
          return 0;
        }
      );
      $ok = curl_exec($ch);
      $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
      $err = curl_error($ch);
      curl_close($ch);
      fclose($fh);
      if (!$ok) throw new RuntimeException('Download failed: ' . $err);
      return $code;
    };

    $code = $attempt($offset);
    if ($offset > 0 && $code === 200) {
      /* Server ignored Range → we appended full content after partial.
               Restart cleanly. */
      $code = $attempt(0);
    }
    if ($code < 200 || $code >= 300) {
      throw new RuntimeException('Download failed: HTTP ' . $code);
    }
    @rename($part, $dest);
  }

  /**
   * Extract a downloaded archive into a destination directory.
   * Supports .tar.bz2, .tar.gz, .tar.xz, .tar.zst, .tar and .zip.
   *
   * ★ FIX: tar families are decompressed FIRST (bzcat / zcat / static xz /
   * static zstd, with busybox fallbacks — the exact chains Pkg.php already
   * proved on this device) and then unpacked with a plain `tar xf`, so we
   * no longer depend on the tar build having built-in bzip2/zstd support
   * (busybox tar often lacks it).
   *
   * @param string $archivePath     Absolute path to the downloaded archive
   * @param string $destDir         Directory to extract into
   * @param int    $stripComponents Leading path components to strip
   * @param string $formatHint      Optional logical name used for format
   *                                detection when $archivePath is a temp
   *                                file with a mangled extension
   */
  private function extractArchive(string $archivePath, string $destDir, int $stripComponents = 0, string $formatHint = ''): void
  {
    if (!is_dir($destDir)) {
      @mkdir($destDir, 0777, true);
    }
    $format = strtolower($formatHint !== '' ? $formatHint : $archivePath);

    // ── ZIP via PharData (unchanged — proven path) ──
    if (preg_match('/\.zip$/', $format)) {
      try {
        $phar = new \PharData($archivePath);
        $phar->extractTo($destDir, null, true);
        if ($stripComponents > 0) {
          $this->stripDirComponents($destDir, $stripComponents);
        }
        return;
      } catch (\Exception $e) {
        throw new RuntimeException('Zip extraction failed: ' . $e->getMessage());
      }
    }

    $prefix = (string) getenv('QUIRKY_PREFIX');
    $prefix = ($prefix !== '') ? rtrim($prefix, '/') : '';
    $bb = ($prefix !== '' && is_file($prefix . '/bin/busybox')) ? $prefix . '/bin/busybox' : null;

    // ── Decompressor candidates per format (shell-free argv lists).
    //    Each candidate is tried as a REAL process (no shell sentence),
    //    and a candidate only wins when it produced > 0 bytes — this
    //    neutralises dummy busybox applets that exit 0 with no output. ──
    $candidates = [];
    if (preg_match('/\.(tar\.bz2|tbz2?)$/', $format)) {
      if ($prefix !== '' && is_file($prefix . '/bin/bzcat')) $candidates[] = [$prefix . '/bin/bzcat', $archivePath];
      if ($bb !== null) {
        $candidates[] = [$bb, 'bzcat', $archivePath];
        $candidates[] = [$bb, 'bunzip2', '-c', $archivePath];
      }
    } elseif (preg_match('/\.(tar\.gz|tgz)$/', $format)) {
      if ($prefix !== '' && is_file($prefix . '/bin/zcat')) $candidates[] = [$prefix . '/bin/zcat', $archivePath];
      if ($bb !== null) {
        $candidates[] = [$bb, 'zcat', $archivePath];
        $candidates[] = [$bb, 'gunzip', '-c', $archivePath];
      }
    } elseif (preg_match('/\.(tar\.xz|txz)$/', $format)) {
      if ($prefix !== '' && is_file($prefix . '/bin/xz-static')) $candidates[] = [$prefix . '/bin/xz-static', '-dc', $archivePath];
      if ($prefix !== '' && is_file($prefix . '/bin/xzcat'))     $candidates[] = [$prefix . '/bin/xzcat', $archivePath];
      if ($bb !== null) {
        $candidates[] = [$bb, 'xzcat', $archivePath];
        $candidates[] = [$bb, 'unxz', '-c', $archivePath];
      }
    } elseif (preg_match('/\.(tar\.zst|tzst)$/', $format)) {
      if ($prefix !== '' && is_file($prefix . '/bin/zstd-static')) $candidates[] = [$prefix . '/bin/zstd-static', '-dc', $archivePath];
      if ($prefix !== '' && is_file($prefix . '/bin/zstdcat'))     $candidates[] = [$prefix . '/bin/zstdcat', $archivePath];
      if ($bb !== null) {
        $candidates[] = [$bb, 'zstdcat', $archivePath];
        $candidates[] = [$bb, 'zstd', '-dc', $archivePath];
      }
    } elseif (preg_match('/\.tar$/', $format)) {
      // plain tar — nothing to decompress
    } else {
      throw new RuntimeException('Unsupported archive format: ' . basename($format));
    }

    $decodedTar = $archivePath . '.decoded.tar';
    $tarInput   = $archivePath;
    if ($candidates !== []) {
      $decompressed = false;
      $tried = [];
      foreach ($candidates as $argv) {
        if (!is_file($argv[0])) continue;
        $tried[] = basename($argv[0]);
        $bytes = $this->runTo($argv, $decodedTar, 600);
        if ($bytes > 0) {
          $decompressed = true;
          break;
        }
        @unlink($decodedTar);
      }
      if (!$decompressed) {
        throw new RuntimeException('No working decompressor for ' . basename($format)
          . ' (tried: ' . (implode(', ', $tried) ?: 'none') . ')');
      }
      $tarInput = $decodedTar;
    }

    // ── Extract with proc_open + cwd. NO shell, NO -C, NO --strip-components:
    //    this is the exact pattern Pkg.php already proves on this device. ──
    $tarArgv = null;
    if ($prefix !== '' && is_file($prefix . '/bin/tar')) {
      $tarArgv = [$prefix . '/bin/tar'];
    } elseif ($bb !== null) {
      $tarArgv = [$bb, 'tar'];
    }
    if ($tarArgv === null) {
      if ($candidates !== []) @unlink($decodedTar);
      throw new RuntimeException('tar is not available — cannot extract archives. Install it via: pkg install tar');
    }
    $tarArgv[] = 'xf';
    $tarArgv[] = $tarInput;

    $errTail = '';
    $exit = $this->runIn($tarArgv, $destDir, 600, $errTail);
    if ($candidates !== []) @unlink($decodedTar);
    if ($exit !== 0) {
      throw new RuntimeException('tar extraction failed (exit ' . $exit . '): ' . $errTail);
    }
    $entries = @scandir($destDir);
    if (!is_array($entries) || count(array_diff($entries, ['.', '..'])) === 0) {
      throw new RuntimeException('tar reported success but extracted nothing — busybox tar mis-parsed the options');
    }

    // ── Strip wrapper folders in PHP (deterministic, no tar quirks) ──
    if ($stripComponents > 0) {
      $this->stripDirComponents($destDir, $stripComponents);
    }
  }

  /** Run a command (no shell) and stream its stdout into $destFile.
   *  Returns the number of bytes written (0 = failed / dummy applet). */
  private function runTo(array $argv, string $destFile, int $timeoutSec): int
  {
    $proc = @proc_open($argv, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($proc)) return 0;
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);
    $fh = @fopen($destFile, 'wb');
    if ($fh === false) {
      fclose($pipes[1]);
      fclose($pipes[2]);
      proc_close($proc);
      return 0;
    }
    $written = 0;
    $start = microtime(true);
    while (true) {
      $chunk = fread($pipes[1], 65536);
      if ($chunk !== false && $chunk !== '') {
        fwrite($fh, $chunk);
        $written += strlen($chunk);
      }
      $st = proc_get_status($proc);
      if (!$st['running']) {
        while (($chunk = fread($pipes[1], 65536)) !== false && $chunk !== '') {
          fwrite($fh, $chunk);
          $written += strlen($chunk);
        }
        break;
      }
      if ((microtime(true) - $start) > $timeoutSec) {
        proc_terminate($proc, 9);
        break;
      }
      if ($chunk === false || $chunk === '') usleep(20000);
    }
    fclose($fh);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($proc);
    clearstatcache(true, $destFile);
    return $written;
  }

  /** Run a command (no shell) inside $cwd. Returns exit code; stderr tail in $errTail. */
  private function runIn(array $argv, string $cwd, int $timeoutSec, string &$errTail): int
  {
    $errTail = '';
    $proc = @proc_open($argv, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $cwd);
    if (!is_resource($proc)) {
      $errTail = 'could not start process';
      return -1;
    }
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);
    $err = '';
    $start = microtime(true);
    $exit = -1;
    while (true) {
      $err .= (string) stream_get_contents($pipes[2]);
      $st = proc_get_status($proc);
      if (!$st['running']) {
        $exit = (int) $st['exitcode'];
        $err .= (string) stream_get_contents($pipes[2]);
        break;
      }
      if ((microtime(true) - $start) > $timeoutSec) {
        proc_terminate($proc, 9);
        usleep(50000);
        break;
      }
      usleep(50000);
    }
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($proc);
    $errTail = substr($err, -2000);
    return $exit;
  }

  /** Strip N leading wrapper directories in pure PHP (works for any archive). */
  private function stripDirComponents(string $dir, int $levels): void
  {
    for ($i = 0; $i < $levels; $i++) {
      $entries = @scandir($dir);
      if (!$entries) break;
      $entries = array_diff($entries, ['.', '..']);
      if (count($entries) !== 1) break; // only strip a single wrapper dir
      $subdir = $dir . '/' . reset($entries);
      if (!is_dir($subdir)) break;
      $subEntries = @scandir($subdir);
      if (!$subEntries) break;
      foreach (array_diff($subEntries, ['.', '..']) as $item) {
        @rename($subdir . '/' . $item, $dir . '/' . $item);
      }
      @rmdir($subdir);
    }
  }

  /**
   * Strip leading directory components from an extracted zip.
   * Used when PharData extracts a zip with a wrapper directory.
   */
  private function stripZipComponents(string $dir, int $levels): void
  {
    $this->stripDirComponents($dir, $levels);
  }

  /** pkg-installed runtime binaries live in $PREFIX/bin. */
  private function locatePkgBinary(string $pkgName, string $fallbackRel): ?string
  {
    $prefix = rtrim((string) getenv('QUIRKY_PREFIX'), '/');
    if ($prefix === '') return null;
    $candidates = [
      $prefix . '/bin/whisper-cli',
      $prefix . '/bin/whisper',
      $prefix . '/bin/' . $pkgName,
      $prefix . '/bin/main',
    ];
    foreach ($candidates as $c) {
      if (is_file($c)) return $c; // absolute path — registry marks absolute:true
    }
    return null;
  }

  /* ═══════════════════════════════════════════════════════════
       UNINSTALL
       ═══════════════════════════════════════════════════════════ */

  public function uninstall(string $id): array
  {
    $reg = $this->loadRegistry();

    if (isset($reg['models'][$id])) {
      $m = $reg['models'][$id];
      $entry = $this->findEntry($id);
      if ($entry !== null) {
        foreach ((array) ($entry['files'] ?? []) as $f) {
          @unlink($this->speechDir . '/' . ltrim((string) ($f['dest'] ?? ''), '/'));
        }
      }
      unset($reg['models'][$id]);
      $this->saveRegistry($reg);
      $this->pruneEmptyDirs($this->speechDir . '/models');
      return ['ok' => true, 'removed' => $id];
    }

    if (isset($reg['runtimes'][$id])) {
      foreach ($reg['models'] ?? [] as $mid => $m) {
        if (($m['runtime'] ?? '') === $id) {
          return ['ok' => false, 'error' => "Uninstall model \"$mid\" first — it depends on this runtime"];
        }
      }
      $r = $reg['runtimes'][$id];
      if (empty($r['absolute'])) {
        $entry = $this->findEntry($id);
        if ($entry !== null) {
          foreach ((array) ($entry['files'] ?? []) as $f) {
            @unlink($this->speechDir . '/' . ltrim((string) ($f['dest'] ?? ''), '/'));
          }
        }
      }
      unset($reg['runtimes'][$id]);
      $this->saveRegistry($reg);
      $this->pruneEmptyDirs($this->speechDir . '/runtimes');
      return ['ok' => true, 'removed' => $id];
    }

    return ['ok' => false, 'error' => 'Not installed: ' . $id];
  }

  private function pruneEmptyDirs(string $root): void
  {
    if (!is_dir($root)) return;
    $it = new RecursiveIteratorIterator(
      new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
      RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $item) {
      if ($item->isDir() && count((array) @scandir($item->getPathname())) <= 2) {
        @rmdir($item->getPathname());
      }
    }
  }

  /* ═══════════════════════════════════════════════════════════
       OFFLINE ZIP IMPORT (quirky.speech.json)
       Community members can distribute a model as a plain .zip:
         quirky.speech.json     (manifest, root or one level deep)
         <files referenced by "src">
       ═══════════════════════════════════════════════════════════ */

  public function importLocal(string $absZipPath): array
  {
    if (!is_file($absZipPath)) return ['ok' => false, 'error' => 'File not found'];

    $tmp = sys_get_temp_dir() . '/quirky_speech_' . bin2hex(random_bytes(6));
    if (!@mkdir($tmp, 0700)) return ['ok' => false, 'error' => 'Temp dir failed'];

    try {
      $phar = new \PharData($absZipPath);
      $phar->extractTo($tmp, null, true);

      $manifest = $this->findManifest($tmp);
      if ($manifest === null) {
        return ['ok' => false, 'error' => 'No quirky.speech.json found in the zip'];
      }
      $dec = json_decode((string) file_get_contents($manifest), true);
      if (!is_array($dec)) return ['ok' => false, 'error' => 'quirky.speech.json is not valid JSON'];

      /* compact validation */
      $id = (string) ($dec['id'] ?? '');
      if (!preg_match('/^[a-z0-9][a-z0-9_-]{2,64}$/', $id)) {
        return ['ok' => false, 'error' => 'Invalid id'];
      }
      $kind = (string) ($dec['kind'] ?? '');
      if (!in_array($kind, ['tts-model', 'stt-model'], true)) {
        return ['ok' => false, 'error' => 'kind must be tts-model or stt-model'];
      }
      $runtime = (string) ($dec['runtime'] ?? '');
      $reg = $this->loadRegistry();
      if ($runtime === '' || empty($reg['runtimes'][$runtime]['installed'])) {
        return ['ok' => false, 'error' => "Runtime \"$runtime\" must be installed before importing this model"];
      }
      if (empty($dec['entry']) || empty($dec['files']) || !is_array($dec['files'])) {
        return ['ok' => false, 'error' => 'Manifest needs entry + files[]'];
      }

      $base = dirname($manifest);
      $bytes = 0;
      foreach ($dec['files'] as $f) {
        $src = $base . '/' . ltrim((string) ($f['src'] ?? ''), '/');
        $destRel = ltrim((string) ($f['dest'] ?? ''), '/');
        if (!is_file($src) || $destRel === '') {
          return ['ok' => false, 'error' => 'Missing file in zip: ' . ($f['src'] ?? '?')];
        }
        if (strpos($destRel, '..') !== false) {
          return ['ok' => false, 'error' => 'Unsafe dest path'];
        }
        $sha = strtolower(trim((string) ($f['sha256'] ?? '')));
        if ($sha !== '' && strtolower((string) hash_file('sha256', $src)) !== $sha) {
          return ['ok' => false, 'error' => 'SHA-256 mismatch: ' . basename($destRel)];
        }
        $abs = $this->speechDir . '/' . $destRel;
        if (!is_dir(dirname($abs))) @mkdir(dirname($abs), 0777, true);
        if (!@copy($src, $abs)) return ['ok' => false, 'error' => 'Copy failed: ' . $destRel];
        if (!empty($f['exec'])) @chmod($abs, 0755);
        $bytes += (int) filesize($abs);
      }

      $reg['models'][$id] = [
        'kind' => strpos($kind, 'tts') === 0 ? 'tts' : 'stt',
        'runtime' => $runtime,
        'name' => (string) ($dec['name'] ?? $id),
        'lang' => (string) ($dec['lang'] ?? ''),
        'entry' => (string) $dec['entry'],
        'bytes' => $bytes,
        'installed' => true,
        'installedAt' => time(),
      ];
      $this->saveRegistry($reg);
      return ['ok' => true, 'id' => $id, 'bytes' => $bytes];
    } catch (\Throwable $e) {
      return ['ok' => false, 'error' => $e->getMessage()];
    } finally {
      $this->rrmdir($tmp);
    }
  }

  private function findManifest(string $dir): ?string
  {
    if (is_file($dir . '/quirky.speech.json')) return $dir . '/quirky.speech.json';
    foreach ((array) @scandir($dir) as $sub) {
      if ($sub === '.' || $sub === '..') continue;
      $p = $dir . '/' . $sub . '/quirky.speech.json';
      if (is_dir($dir . '/' . $sub) && is_file($p)) return $p;
    }
    return null;
  }

  private function rrmdir(string $dir): void
  {
    if (!is_dir($dir)) return;
    $it = new RecursiveIteratorIterator(
      new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
      RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $item) {
      $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
    }
    @rmdir($dir);
  }

  /* ═══════════════════════════════════════════════════════════
       SSE HELPER
       ═══════════════════════════════════════════════════════════ */

  private function sse(string $event, array $data): void
  {
    echo 'event: ' . $event . "\n";
    echo 'data: ' . json_encode($data, JSON_UNESCAPED_SLASHES) . "\n\n";
    @ob_flush();
    @flush();
  }
}
