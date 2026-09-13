<?php
// File: Pkg.php
declare(strict_types=1);
/**
 * ═══════════════════════════════════════════════════════════════════════════
 *  QUIRKY IDE — PKG PACKAGE MANAGER (Phase C · Termux Repository Integration)
 * ═══════════════════════════════════════════════════════════════════════════
 *
 *  Connects to Termux's OFFICIAL package repository and installs real
 *  packages (python, node, git, wget, curl, vim, etc.) into the app's
 *  private prefix folder (files/usr).
 *
 *  HOW IT WORKS:
 *    1. Fetches the Debian "Packages" index from Termux's repo
 *    2. Resolves dependencies automatically (like real apt)
 *    3. Downloads .deb files (which are just ar archives)
 *    4. Extracts them using a bundled busybox binary
 *    5. Records installed packages in a local database
 *
 *  REQUIREMENTS:
 *    - A busybox binary at $prefix/bin/busybox. This is now BUNDLED INSIDE
 *      THE APP ITSELF (app/src/main/assets/toolbox/busybox) and copied into
 *      place by MainActivity.java when the app starts — Pkg.php no longer
 *      downloads it from the internet. This removes an entire class of
 *      "first run fails because GitHub/busybox.net didn't respond" bugs.
 *    - A CA certificate bundle at $prefix/etc/tls/cacert.pem, also bundled
 *      as an app asset and copied into place by MainActivity.java. Without
 *      this file, HTTPS downloads (the Termux package repo, .deb files,
 *      etc.) either fail SSL verification or have to skip verification
 *      entirely, which is unsafe. Pkg.php now points cURL directly at this
 *      bundled CA file.
 *    - Internet access (still required for actually browsing/downloading
 *      *packages* from the Termux repo — just not for busybox/cacert
 *      anymore, since those ship with the app).
 *    - Android device (binaries won't run on desktop)
 *
 * ═══════════════════════════════════════════════════════════════════════════
 */
class IdePkg
{
  private array $config;
  private IdeSecurity $security;

  /** Absolute path of the toolbox folder (files/usr). */
  private string $prefix;
  private string $binDir;
  private string $libDir;
  private string $tmpDir;
  private string $dbDir;
  private string $etcDir;
  private string $tlsDir;

  /** Path to the bundled CA certificate bundle (ships as an app asset). */
  private string $caCertPath;

  /**
   * Path to an OPTIONAL bundled static zstd binary (mirrors caCertPath /
   * busybox — an app asset copied into place by MainActivity.java, if
   * present). This does NOT exist yet unless MainActivity.java is updated
   * to ship it (see the big comment above extractDeb() for why this is
   * needed: Termux's own 'zstd' package is itself .zst-compressed, so
   * `pkg install zstd` can never bootstrap zstd support on a device that
   * doesn't already have it — busybox has no real zstd support either).
   */
  private string $staticZstdPath;
  /**
   * Path to an OPTIONAL bundled static xz binary. Termux's 'xz-utils'
   * package is itself .xz-compressed, creating the exact same chicken-and-egg
   * bootstrap loop as zstd. We need a static executable to break the loop.
   */
  private string $staticXzPath;

  /** Termux official repository base URL. */
  private string $repoBase = 'https://packages.termux.dev/apt/termux-main';
  private string $packagesIndexUrl;
  private bool $installingTar = false;

  /** List of known Termux mirrors for automatic fallback. */
  private const MIRRORS = [
    'https://packages.termux.dev/apt/termux-main',
    'https://termux.mentality.rip/termux-main',
    'https://mirror.mwt.me/termux/main',
    'https://mirrors.cfe.re/termux-main',
    'https://ftp.agdsn.de/termux/termux-main',
    'https://termux.cdn.lumito.net/termux-main',
    'https://mirror.fcix.net/termux-main',
    'https://mirror.csclub.uwaterloo.ca/termux/termux-main',
    'https://plug-mirror.rcac.purdue.edu/termux/termux-main',
    'https://mirror.quantum5.ca/termux/termux-main',
    'https://mirrors.utermux.dev/termux/termux-main',
    'https://mirror.vern.cc/termux/termux-main',
    'https://dl.kcubeterm.com/termux-main',
    'https://termux.danyael.xyz/termux-main',
    'https://mirror.sunred.org/termux/termux-main',
    'https://mirror.autkin.net/termux/termux-main',
    'https://mirror.bouwhuis.network/termux/termux-main',
    'https://nl.mirror.flokinet.net/termux/termux-main',
    'https://ro.mirror.flokinet.net/termux/termux-main',
    'https://mirror.accum.se/mirror/termux.dev/termux-main',
    'https://termux.3san.dev/termux/termux-main',
    'https://mirror.polido.pt/termux/termux-main',
    'https://grimler.se/termux/termux-main',
    'https://mirrors.nju.edu.cn/termux/apt/termux-main',
    'https://mirrors.zju.edu.cn/termux/termux-main',
    'https://mirrors.ustc.edu.cn/termux/termux-main',
    'https://mirrors.tuna.tsinghua.edu.cn/termux/apt/termux-main',
    'https://mirrors.aliyun.com/termux/termux-main',
    'https://mirrors.sdu.edu.cn/termux/termux-main',
  ];

  /** In-memory cache of the parsed Packages index. */
  private ?array $indexCache = null;

  /**
   * "Recommended" companion packages. Not a hard requirement the way
   * Depends: is (Termux's own index doesn't list these), but packages
   * almost everyone wants together. When someone runs
   * `pkg install python`, every package on the right of 'python' below
   * is silently added to the install list too (each gets its own
   * dependency resolution just like a normal install).
   *
   * To add more later: just add a new "'package' => ['companion']," line.
   */
  private const RECOMMENDS = [
    'python' => ['python-pip'],
    'nodejs' => ['npm'],
  ];

  private int $timeout;
  private string $lastError = '';

    /* ── LIVE STREAMING (Termux-style dynamic output) ─────────────
    When the terminal runs pkg in streaming mode, it attaches an
    "emitter" callback here. Every log line and download-progress
    step is then pushed to the screen IMMEDIATELY instead of being
    held until the command finishes. */
  /** @var callable|null function(string $event, string $text): void */
  private $emitter = null;

  /**
   * Attach (or detach) the live-output callback.
   * Event types:
   *   'out'  → normal line (appended)
   *   'err'  → error line (appended, red)
   *   'prog' → transient progress payload. May be a plain string (legacy
   *            text row) or an ARRAY with structured fields so the
   *            frontend owns the rendering:
   *              ['phase'=>'index'|'download'|'extract'|'link',
   *               'name'=>string, 'pct'=>int(0-100), 'got'=>bytes,
   *               'total'=>bytes, 'indeterminate'=>bool,
   *               'text'=>backward-compatible display string]
   *
   * @param callable|null $emitter function(string $event, string|array $payload): void
   */
  public function setEmitter(?callable $emitter): void
  {
    $this->emitter = $emitter;
  }

  /** Push one chunk to the live stream (no-op when not streaming). */
  private function emit(string $text, string $event = 'out'): void
  {
    if ($this->emitter !== null && $text !== '') {
      ($this->emitter)($event, $text);
    }
  }

  /** Throttled "last time a prog row went out" stamp (per phase chain). */
  private float $lastProgTs = 0;

  /**
   * ★ POLISH PASS — STRUCTURED SINGLE-LINE PROGRESS.
   * Emits one transient prog event carrying BOTH the legacy text field
   * (old frontends render it verbatim) and structured fields (new
   * frontends draw their own compact bar). Throttled to ~4 Hz so a fast
   * download doesn't flood the SSE stream.
   */
  private function emitProg(string $phase, string $name, int $pct, int $got, int $total, bool $indeterminate = false): void
  {
    if ($this->emitter === null) {
      return;
    }
    /* ★ HOTFIX — PROGRESS INVARIANT (0 <= got <= total, pct derived from
           the SAME pair). No faked numbers:
             1) got > total means the caller mixed two different artifacts
                (e.g. unpacked tar bytes vs the compressed .deb Size header).
                We do NOT clamp the bar; the tick degrades to INDETERMINATE
                so the UI shows only the honest byte counter and never an
                impossible pair like "100% 24.3 MB / 1.9 MB".
             2) Every determinate tick derives pct HERE from got/total, so
                the percentage can never disagree with the shown bytes. */
    if (!$indeterminate && $total > 0 && $got > $total) {
      $indeterminate = true;
    }
    if ($indeterminate) {
      $pct   = 0;
      $total = 0;               // meaningless total → hide it entirely
    } elseif ($total > 0) {
      $pct = (int) max(0, min(100, floor(($got / $total) * 100)));
    } else {
      $pct = max(0, min(100, $pct));
    }
    $now = microtime(true);
    if (($now - $this->lastProgTs) < 0.25 && $pct !== 100) {
      return; // never throttle the final 100%
    }
    $this->lastProgTs = $now;
    if ($phase === 'link') {
      $text = sprintf('✓ %s done (%d files)', ($name !== '') ? $name : $phase, max(0, $got));
    } elseif ($indeterminate) {
      $text = sprintf('↓ %s %s…', ($name !== '') ? $name : $phase, $this->fmtBytes(max(0, $got)));
    } else {
      $text = sprintf('↓ %s %3d%% [%s / %s]', ($name !== '') ? $name : $phase, $pct, $this->fmtBytes(max(0, $got)), $this->fmtBytes(max(0, $total)));
    }
    ($this->emitter)('prog', [
      'phase'         => $phase,
      'name'          => $name,
      'pct'           => $pct,
      'got'           => max(0, $got),
      'total'         => max(0, $total),
      'indeterminate' => $indeterminate,
      'text'          => $text,
    ]);
  }

  /** Append a line to the running log AND push it to the live stream. */
  private function log(string &$log, string $text): void
  {
    $log .= $text;
    $this->emit($text);
  }

  /**
   * Turn a raw cURL error string into a short, human-friendly reason.
   * The verbose original never reaches the live terminal — the raw
   * trace is kept for `pkg doctor` instead (see saveLastTrace()).
   */
  private function friendlyError(string $raw): string
  {
    $raw = trim($raw);
    if ($raw === '') {
      return 'unknown error';
    }
    if (preg_match('/timed out after (\d+) ms/i', $raw, $m)) {
      return 'connection timed out (' . round(((int) $m[1]) / 1000) . 's)';
    }
    if (stripos($raw, 'Could not resolve host') !== false) {
      return 'DNS lookup failed (host not found)';
    }
    if (stripos($raw, 'Recv failure') !== false || stripos($raw, 'connection abort') !== false) {
      return 'connection reset by remote host';
    }
    if (stripos($raw, 'Connection refused') !== false) {
      return 'connection refused';
    }
    if (stripos($raw, 'SSL certificate') !== false || stripos($raw, 'SSL peer') !== false) {
      return 'SSL certificate problem (run: pkg doctor)';
    }
    if (stripos($raw, 'SSL') !== false) {
      return 'SSL/TLS handshake failed';
    }
    if (preg_match('/^HTTP (\d{3})/', $raw, $m)) {
      return 'server answered HTTP ' . $m[1];
    }
    if (stripos($raw, 'no cURL') !== false || stripos($raw, 'Download failed') !== false) {
      return 'download failed';
    }
    $short = preg_replace('/\s+/', ' ', $raw);
    return (strlen($short) > 60) ? substr($short, 0, 60) . '…' : $short;
  }

  /**
   * Sleep efficiently until one of the given pipes has data (or hits
   * EOF), or until $maxWaitSec elapses — whichever comes first. This
   * replaces the old fixed "usleep(100ms) then check again" busy-loop
   * used while extracting a .deb's tar archive. stream_select() puts
   * PHP to sleep instead of spinning, so a slow/large extraction no
   * longer burns CPU the whole time it runs — it uses close to nothing
   * while waiting and wakes up the instant there's real output.
   *
   * @param array $pipes Numeric array containing at least indexes 1 and 2
   *                      (stdout, stderr) from a proc_open() pipes array.
   */
  private function waitForActivity(array $pipes, float $maxWaitSec): void
  {
    if ($maxWaitSec <= 0) {
      return;
    }
    $read   = [$pipes[1], $pipes[2]];
    $write  = null;
    $except = null;
    $sec  = (int) floor($maxWaitSec);
    $usec = (int) round(($maxWaitSec - $sec) * 1_000_000);
    // Suppressed: stream_select() can emit a harmless warning if
    // interrupted by a signal on some platforms — we just loop again.
    @stream_select($read, $write, $except, $sec, $usec);
  }

  /** Just the host part of a URL, for compact Hit:/Err: lines. */
  private function hostOf(string $url): string
  {
    $h = parse_url($url, PHP_URL_HOST);
    return (is_string($h) && $h !== '') ? $h : $url;
  }

  /** Persist the last raw cURL trace so `pkg doctor` can show it. */
  private function saveLastTrace(string $trace): void
  {
    if ($trace !== '') {
      @file_put_contents($this->dbDir . '/last-curl-trace.txt', $trace);
    }
  }

  public function __construct(array $config, IdeSecurity $security, string $prefix)
  {
    $this->config   = $config;
    $this->security = $security;

    $pkgCfg = $config['pkg'] ?? [];
    $this->timeout = max(60, (int) ($pkgCfg['timeout'] ?? 300));

    // ── Set up every prefix-based path FIRST. ──
    // getActiveMirror() below needs $this->dbDir to already have a value —
    // reading a typed property before it's assigned throws a fatal error
    // in PHP ("must not be accessed before initialization"), which is what
    // was happening before this fix and was breaking every pkg command.
    $this->prefix = rtrim(str_replace('\\', '/', $prefix), '/');
    $this->binDir = $this->prefix . '/bin';
    $this->libDir = $this->prefix . '/lib';
    $this->tmpDir = $this->prefix . '/tmp';
    $this->dbDir  = $this->prefix . '/var/lib/pkg';
    $this->etcDir = $this->prefix . '/etc';
    $this->tlsDir = $this->etcDir . '/tls';

    foreach ([$this->binDir, $this->libDir, $this->tmpDir, $this->dbDir, $this->etcDir, $this->tlsDir] as $d) {
      if (!is_dir($d)) {
        @mkdir($d, 0777, true);
      }
    }

    // Allow overriding via config (mostly useful for testing on desktop),
    // otherwise use the copy that MainActivity.java placed for us.
    $this->caCertPath = $pkgCfg['cacert_path'] ?? ($this->tlsDir . '/cacert.pem');

    // Same idea as caCertPath: allow a config override (for desktop
    // testing), otherwise look for whatever MainActivity.java copied
    // in from app/src/main/assets/toolbox/zstd. Perfectly fine if it
    // doesn't exist yet — every check below treats it as optional.
    $this->staticZstdPath = $pkgCfg['static_zstd_path'] ?? ($this->binDir . '/zstd-static');
    $this->staticXzPath = $pkgCfg['static_xz_path'] ?? ($this->binDir . '/xz-static');

    // Allow custom repo URL in config (fallback to Termux official)
    if (!empty($pkgCfg['repo_base'])) {
      $this->repoBase = rtrim($pkgCfg['repo_base'], '/');
    }

    // Override with saved mirror if available — safe now, dbDir exists.
    $savedMirror = $this->getActiveMirror();
    if (in_array($savedMirror, self::MIRRORS, true)) {
      $this->repoBase = $savedMirror;
    }

    // Use the gzip-compressed index instead of the raw one — it's roughly
    // 3x smaller, which matters a lot on a slow connection. Pkg.php
    // already knows how to gzdecode() this (see fetchPackagesIndex()).
    $this->packagesIndexUrl = $this->repoBase . '/dists/stable/main/binary-aarch64/Packages.gz';
  }

  /* ═══════════════════════════════════════════════════════════
    ENTRY POINT — called by IdeTerminal::routeToPkg()
    ════════════════════════════════════════════════════════════ */
  /**
   * Check if a specific package is installed.
   *
   * @param string $name The package name (e.g. 'apache2')
   * @return bool True if installed
   */
  public function isPackageInstalled(string $name): bool
  {
    $dbFile = $this->dbFile($name);
    return is_file($dbFile);
  }

  public function handleCommand(string $argsLine): array
  {
    if (!@ini_get('safe_mode')) {
      @set_time_limit($this->timeout + 60);
    }

    // ★ NON-NEGOTIABLE DEVICE TIER GUARD:
    // Even if terminal/pkg somehow gets enabled in UI/config,
    // the device policy can still block package operations completely.
    require_once dirname(__DIR__) . '/mobile-limits.php';
    if (!quirkyMobileCanUsePackages($this->config)) {
      return $this->out("🚫 Package management is locked on this device tier.", 1);
    }

    if (!$this->isEnabled()) {
      return $this->out("pkg is disabled. Set 'pkg_enabled' => true in config.php.\n", 1);
    }

    $args = $argsLine === '' ? [] : (preg_split('/\s+/', trim($argsLine)) ?: []);
    $sub  = strtolower((string) array_shift($args));

    switch ($sub) {
      case '':
      case 'help':
      case '-h':
      case '--help':
        return $this->out($this->helpText(), 0);

      case 'update':
        return $this->cmdUpdate();

      case 'list':
      case 'list-all':
        return $this->cmdList($args);

      case 'search':
        return $this->cmdSearch((string) ($args[0] ?? ''));

      case 'info':
      case 'show':
        return $this->cmdInfo((string) ($args[0] ?? ''));

      case 'install':
      case 'add':
        return $this->cmdInstall($args);

      case 'reinstall':
      case 'repair':
        return $this->cmdRepair($args);

      case 'remove':
      case 'uninstall':
      case 'rm':
        return $this->cmdRemove((string) ($args[0] ?? ''));

      case 'installed':
      case 'list-installed':
        return $this->cmdInstalled();

      case 'upgrade':
      case 'upgrade-all':
        return $this->cmdUpgrade();

      case 'depends':
        return $this->cmdDepends((string) ($args[0] ?? ''));
      case 'repo':
      case 'mirror':
      case 'mirrors':
        return $this->cmdMirrors($args);
      case 'doctor':
      case 'diagnose':
        return $this->cmdDoctor();

      default:
        return $this->out("pkg: unknown command '$sub'\n" . $this->helpText(), 1);
    }
  }

  /* ═══════════════════════════════════════════════════════════
    BUNDLED TOOLBOX — busybox + CA certs ship WITH the app now.
    Pkg.php no longer downloads either of these from the internet;
    it only checks that MainActivity.java did its job.
    ════════════════════════════════════════════════════════════ */

  /**
   * Verify the bundled busybox binary is present and executable.
   * busybox is copied into place by MainActivity.java on every app
   * launch (from app/src/main/assets/toolbox/busybox), so by the time
   * PHP runs this should already exist. This function does NOT hit
   * the network — if the file is missing, something is wrong with the
   * app packaging/extraction step, not the internet connection.
   */
  private function ensureBusybox(): bool
  {
    $busyboxPath = $this->binDir . '/busybox';

    if (!is_file($busyboxPath)) {
      $this->lastError =
        "busybox is missing from the app's bundled toolbox.\n" .
        "  Expected at: $busyboxPath\n" .
        "  This file ships INSIDE the app (assets/toolbox/busybox) and is\n" .
        "  copied into place automatically every time the app starts.\n" .
        "  Fix: fully close the app (not just background it) and reopen it.\n" .
        "  If that doesn't help, the installed app build is missing the\n" .
        "  asset — rebuild/reinstall the APK.";
      return false;
    }

    if (!is_executable($busyboxPath)) {
      @chmod($busyboxPath, 0755);
      clearstatcache(true, $busyboxPath);
      if (!is_executable($busyboxPath)) {
        $this->lastError =
          "busybox exists at $busyboxPath but is not executable, " .
          "and chmod() could not make it executable.";
        return false;
      }
    }

    $this->createBusyboxSymlinks($busyboxPath);
    return true;
  }

  /**
   * Create symlinks for common tools that busybox provides.
   */
  private function createBusyboxSymlinks(string $busyboxPath): void
  {
    $tools = [
      'ar',
      'tar',
      'xz',
      'gzip',
      'gunzip',
      'bzip2',
      'unzip',
      'wget',
      'curl',
      'ls',
      'cat',
      'cp',
      'mv',
      'rm',
      'mkdir',
      'chmod',
      'ln',
      'grep',
      'sed',
      'awk',
      'find',
      'sort',
      'head',
      'tail',
      'wc',
      'du',
      'df',
      'ps',
      'kill',
      'echo',
      'printf',
      'test',
      'env',
      'basename',
      'dirname',
      'realpath',
      'md5sum',
      'sha256sum',
      'diff',
      'patch',
      'vi',
      'less',
      'more',
      'which',
      'whoami',
      'uname',
      'hostname',
      'id',
    ];

    foreach ($tools as $tool) {
      $link = $this->binDir . '/' . $tool;
      if (!file_exists($link)) {
        @symlink($busyboxPath, $link);
      }
    }
  }

  /**
   * "pkg doctor" — a one-shot health check the user (or you, while
   * debugging) can run from the terminal to see exactly what's missing,
   * instead of guessing from a stack trace.
   */
  private function cmdDoctor(): array
  {
    $lines = [];
    $ok = true;

    $busyboxPath = $this->binDir . '/busybox';
    if (is_file($busyboxPath) && is_executable($busyboxPath)) {
      $lines[] = "✓ busybox found and executable ($busyboxPath)";
    } elseif (is_file($busyboxPath)) {
      $lines[] = "✗ busybox found but NOT executable ($busyboxPath)";
      $ok = false;
    } else {
      $lines[] = "✗ busybox MISSING ($busyboxPath)";
      $ok = false;
    }

    if (is_file($this->caCertPath)) {
      $lines[] = "✓ CA certificate bundle found (" . $this->caCertPath . ")";
    } else {
      $lines[] = "✗ CA certificate bundle MISSING (" . $this->caCertPath . ")";
      $lines[] = "  HTTPS downloads will be unverified or may fail.";
      $ok = false;
    }

    if (is_file($this->staticZstdPath)) {
      $lines[] = "✓ static zstd binary found (" . $this->staticZstdPath . ")";
    } elseif (is_file($this->binDir . '/zstdcat') || is_file($this->binDir . '/zstd')) {
      $lines[] = "✓ zstd decompressor available (installed package)";
    } else {
      $lines[] = "✗ no zstd decompressor available (no static binary, none installed)";
      $lines[] = "  Packages compressed as .tar.zst CANNOT be installed";
      $lines[] = "  until a static zstd binary ships as an app asset.";
    }

    if (is_file($this->staticXzPath)) {
      $lines[] = "✓ static xz binary found (" . $this->staticXzPath . ")";
    } elseif (is_file($this->binDir . '/xzcat') || is_file($this->binDir . '/xz')) {
      $lines[] = "✓ xz decompressor available (installed package)";
    } else {
      $lines[] = "✗ no xz decompressor available (no static binary, none installed)";
      $lines[] = "  Packages compressed as .tar.xz (e.g. git, less) CANNOT be installed";
      $lines[] = "  until a static xz binary ships as an app asset — busybox has no";
      $lines[] = "  real xz support, and the 'xz-utils' package can't bootstrap itself.";
      $ok = false;
    }

    $lines[] = function_exists('curl_init')
      ? "✓ PHP cURL extension available"
      : "✗ PHP cURL extension NOT available (falling back to file_get_contents)";
    $traceFile = $this->dbDir . '/last-curl-trace.txt';
    if (is_file($traceFile)) {
      $trace = trim((string) @file_get_contents($traceFile));
      if ($trace !== '') {
        $lines[] = "\nLast failed-download trace (technical detail):";
        $lines[] = $trace;
      }
    }

    $lines[] = "Prefix: {$this->prefix}";

    $lines[] = $ok
      ? "\nEverything looks good."
      : "\nSome required files are missing — see above. Close and reopen the app;\n"
      . "if that doesn't fix it, the APK build is missing bundled assets.";

    return $this->out(implode("\n", $lines) . "\n", $ok ? 0 : 1);
  }

  /* ═══════════════════════════════════════════════════════════
    MIRROR MANAGEMENT & FALLBACK
    ═════════════════════════════════════════════════════════════ */
  private function getActiveMirror(): string
  {
    $file = $this->dbDir . '/selected-mirror.txt';
    if (is_file($file)) {
      $m = trim((string) @file_get_contents($file));
      if ($m !== '' && in_array($m, self::MIRRORS, true)) {
        return $m;
      }
    }
    return self::MIRRORS[0];
  }

  private function setActiveMirror(string $url): void
  {
    @file_put_contents($this->dbDir . '/selected-mirror.txt', $url, LOCK_EX);
    $this->repoBase = $url;
    $this->packagesIndexUrl = $url . '/dists/stable/main/binary-aarch64/Packages.gz';
  }

  private function getMirrorsList(): array
  {
    $mirrors = self::MIRRORS;
    $active = $this->getActiveMirror();
    $key = array_search($active, $mirrors, true);
    if ($key !== false && $key > 0) {
      unset($mirrors[$key]);
      array_unshift($mirrors, $active);
      $mirrors = array_values($mirrors);
    }
    return $mirrors;
  }

  private function downloadToStringWithFallback(string $urlSuffix): ?string
  {
    $n = 0;
    foreach ($this->getMirrorsList() as $mirror) {
      $n++;
      $fullUrl = $mirror . $urlSuffix;
      $this->lastError = '';
      $result = $this->downloadToString($fullUrl, 'index');
      if ($result !== null) {
        $this->emit("Hit:$n " . $this->hostOf($mirror) . "\n");
        if ($mirror !== $this->getActiveMirror()) {
          $this->setActiveMirror($mirror);
        }
        return $result;
      }
      $this->emit("Err:$n " . $this->hostOf($mirror) . ' — ' . $this->friendlyError($this->lastError) . "\n");
    }
    return null;
  }

  private function downloadFileWithFallback(string $urlSuffix, string $dest, string $label = ''): bool
  {
    $n = 0;
    foreach ($this->getMirrorsList() as $mirror) {
      $n++;
      $fullUrl = $mirror . $urlSuffix;
      $this->lastError = '';
      if ($this->downloadFile($fullUrl, $dest, $label)) {
        $this->emit("Hit:$n " . $this->hostOf($mirror) . "\n");
        if ($mirror !== $this->getActiveMirror()) {
          $this->setActiveMirror($mirror);
        }
        return true;
      }
      $this->emit("Err:$n " . $this->hostOf($mirror) . ' — ' . $this->friendlyError($this->lastError) . "\n");
    }
    return false;
  }

  private function cmdMirrors(array $args): array
  {
    if (empty($args)) {
      $active = $this->getActiveMirror();
      $out = "Available Termux mirrors:\n";
      $out .= str_repeat('-', 60) . "\n";
      foreach (self::MIRRORS as $i => $m) {
        $mark = ($m === $active) ? ' * ' : '   ';
        $out .= sprintf("%s[%2d] %s\n", $mark, $i + 1, $m);
      }
      $out .= "\nUsage: pkg mirrors <number>  (e.g. pkg mirrors 2)\n";
      return $this->out($out, 0);
    }

    $choice = (int) ($args[0] ?? 0);
    if ($choice < 1 || $choice > count(self::MIRRORS)) {
      return $this->out("✗ Invalid choice. Use 'pkg mirrors' to see the list.\n", 1);
    }

    $newMirror = self::MIRRORS[$choice - 1];
    $this->setActiveMirror($newMirror);

    $out = "✓ Switched to: $newMirror\nUpdating package index...\n";
    $index = $this->fetchPackagesIndex(true);
    if ($index === null) {
      return $this->out($out . "✗ Failed to download index from the new mirror.\n  Reason: {$this->lastError}\n", 1);
    }
    return $this->out($out . "✓ Package index updated successfully.\n", 0);
  }

  /* ═══════════════════════════════════════════════════════════
    SUB-COMMANDS
    ═════════════════════════════════════════════════════════════ */
  private function cmdUpdate(): array
  {
    $log = '';
    $this->log($log, "Updating package index…\n");
    $index = $this->fetchPackagesIndex(true);
    if ($index === null) {
      $this->log($log, "✗ Failed to download the package index from all mirrors.\n");
      $this->log($log, "  Check your internet connection and try again.\n");
      $this->log($log, "  (run 'pkg doctor' for the full technical trace)\n");
      return $this->out($log, 1);
    }
    $count = count($index);
    $this->log($log, "✓ Package index updated — $count packages available.\n");
    return $this->out($log, 0);
  }

  private function cmdList(array $args): array
  {
    $index = $this->fetchPackagesIndex(false);
    if ($index === null) {
      return $this->out("✗ No package index. Run: pkg update\n", 1);
    }

    $installed = $this->getInstalledPackages();
    $limit = 50; // Don't dump 3000+ packages at once

    if (in_array('--all', $args, true)) {
      $limit = PHP_INT_MAX;
    }

    $rows = sprintf("%-20s %-12s %-10s %s\n", 'PACKAGE', 'VERSION', 'STATUS', 'DESCRIPTION');
    $rows .= str_repeat('-', 78) . "\n";

    $count = 0;
    foreach ($index as $name => $pkg) {
      if ($count >= $limit) {
        $rows .= "\n... and " . (count($index) - $limit) . " more. Use 'pkg list --all' to see everything.\n";
        break;
      }
      $status = isset($installed[$name]) ? 'installed' : 'available';
      $desc = substr($pkg['Description'] ?? '', 0, 40);
      $rows .= sprintf("%-20s %-12s %-10s %s\n", $name, $pkg['Version'] ?? '?', $status, $desc);
      $count++;
    }

    return $this->out($rows, 0);
  }

  private function cmdSearch(string $query): array
  {
    $query = trim($query);
    if ($query === '') {
      return $this->out("Usage: pkg search <text>\n", 1);
    }

    $index = $this->fetchPackagesIndex(false);
    if ($index === null) {
      return $this->out("✗ No package index. Run: pkg update\n", 1);
    }

    $installed = $this->getInstalledPackages();
    $hits = [];
    $queryLower = strtolower($query);

    foreach ($index as $name => $pkg) {
      $hay = strtolower($name . ' ' . ($pkg['Description'] ?? ''));
      if (strpos($hay, $queryLower) !== false) {
        $status = isset($installed[$name]) ? ' [installed]' : '';
        $hits[] = sprintf(
          "%-20s %-12s %s%s\n",
          $name,
          $pkg['Version'] ?? '?',
          substr($pkg['Description'] ?? '', 0, 50),
          $status
        );
      }
    }

    if (empty($hits)) {
      return $this->out("No packages found matching '$query'.\n", 0);
    }

    return $this->out(implode('', $hits) . "\n" . count($hits) . " result(s).\n", 0);
  }

  private function cmdInfo(string $name): array
  {
    $name = trim($name);
    if ($name === '') {
      return $this->out("Usage: pkg info <package-name>\n", 1);
    }

    $index = $this->fetchPackagesIndex(false);
    if ($index === null) {
      return $this->out("✗ No package index. Run: pkg update\n", 1);
    }

    if (!isset($index[$name])) {
      return $this->out("✗ Package '$name' not found in repository.\n", 1);
    }

    $pkg = $index[$name];
    $installed = $this->getInstalledPackages();
    $isInstalled = isset($installed[$name]);

    $txt  = "Package      : $name\n";
    $txt .= "Version      : " . ($pkg['Version'] ?? '?') . "\n";
    $txt .= "Description  : " . ($pkg['Description'] ?? 'N/A') . "\n";
    $txt .= "Architecture : " . ($pkg['Architecture'] ?? '?') . "\n";

    if (isset($pkg['Depends'])) {
      $txt .= "Dependencies : " . $pkg['Depends'] . "\n";
    }
    if (isset($pkg['Size'])) {
      $txt .= "Size         : " . $this->fmtBytes((int) $pkg['Size']) . "\n";
    }
    if (isset($pkg['Homepage'])) {
      $txt .= "Homepage     : " . $pkg['Homepage'] . "\n";
    }

    $txt .= "Status       : " . ($isInstalled ? "✓ INSTALLED" : "not installed") . "\n";
    $txt .= "Filename     : " . ($pkg['Filename'] ?? '?') . "\n";

    return $this->out($txt, 0);
  }

  private function cmdDepends(string $name): array
  {
    $name = trim($name);
    if ($name === '') {
      return $this->out("Usage: pkg depends <package-name>\n", 1);
    }

    $index = $this->fetchPackagesIndex(false);
    if ($index === null) {
      return $this->out("✗ No package index. Run: pkg update\n", 1);
    }

    if (!isset($index[$name])) {
      return $this->out("✗ Package '$name' not found.\n", 1);
    }

    $resolved = [];
    $this->resolveDependencies($name, $index, $resolved);

    $txt = "Full dependency tree for '$name':\n";
    $txt .= str_repeat('-', 50) . "\n";
    foreach ($resolved as $dep) {
      if ($dep !== $name) {
        $txt .= "  → $dep\n";
      }
    }
    $txt .= str_repeat('-', 50) . "\n";
    $txt .= count($resolved) . " package(s) total (including $name).\n";

    return $this->out($txt, 0);
  }

  private function cmdInstall(array $names): array
  {
    if (empty($names)) {
      return $this->out("Usage: pkg install <package> [more packages...]\n", 1);
    }

    // Make sure the bundled busybox is present/executable before we try
    // to extract anything with it. No network call happens here anymore.
    if (!$this->ensureBusybox()) {
      return $this->out("✗ Toolbox check failed:\n{$this->lastError}\n", 1);
    }

    // Silently pull in "recommended" companions (see RECOMMENDS above),
    // and tell the user we did it so it's never a surprise.
    $log = '';
    $names = $this->expandWithRecommends($names, $log);

    $index = $this->fetchPackagesIndex(false);
    if ($index === null) {
      return $this->out("✗ No package index. Run: pkg update first.\n", 1);
    }

    $exitCode = 0;

    // ★ FIX: don't bail out of the whole `pkg install a b c` batch just
    // because one of the requested packages failed — keep going so the
    // rest still get installed, and report failures at the end.
    foreach ($names as $name) {
      $name = trim($name);
      if ($name === '') continue;

      $result = $this->installPackage($name, $index);
      $log .= $result['log'];
      if ($result['exit'] !== 0) {
        $exitCode = 1;
      }
    }

    return $this->out($log, $exitCode);
  }

  /**
   * Add each name's RECOMMENDS companions to the install list (no
   * duplicates), and note in the log what got added and why.
   */
  private function expandWithRecommends(array $names, string &$log): array
  {
    $expanded = $names;
    foreach ($names as $n) {
      $n = trim($n);
      if (isset(self::RECOMMENDS[$n])) {
        foreach (self::RECOMMENDS[$n] as $rec) {
          if (!in_array($rec, $expanded, true)) {
            $expanded[] = $rec;
            $this->log($log, "• Also installing '$rec' (recommended alongside '$n')\n");
          }
        }
      }
    }
    return $expanded;
  }

  private function cmdRemove(string $name): array
  {
    $name = trim($name);
    if ($name === '') {
      return $this->out("Usage: pkg remove <package-name>\n", 1);
    }

    $dbFile = $this->dbFile($name);
    if (!is_file($dbFile)) {
      return $this->out("✗ Package '$name' is not installed.\n", 1);
    }

    $record = json_decode((string) file_get_contents($dbFile), true);
    $files = (array) ($record['files'] ?? []);

    $removed = 0;
    foreach ($files as $f) {
      $abs = $this->prefix . '/' . ltrim((string) $f, '/');
      if (is_link($abs) || is_file($abs)) {
        if (@unlink($abs)) $removed++;
      }
    }

    @unlink($dbFile);
    return $this->out("✓ Removed '$name' ($removed files deleted).\n", 0);
  }

  private function cmdInstalled(): array
  {
    $installed = $this->getInstalledPackages();
    if (empty($installed)) {
      return $this->out("No packages installed yet.\nTry: pkg install python\n", 0);
    }

    $rows = sprintf("%-20s %-15s %s\n", 'PACKAGE', 'VERSION', 'INSTALLED');
    $rows .= str_repeat('-', 55) . "\n";

    foreach ($installed as $name => $info) {
      $rows .= sprintf(
        "%-20s %-15s %s\n",
        $name,
        $info['version'] ?? '?',
        date('Y-m-d H:i', $info['time'] ?? 0)
      );
    }

    return $this->out($rows . "\n" . count($installed) . " package(s) installed.\n", 0);
  }

  private function cmdUpgrade(): array
  {
    $index = $this->fetchPackagesIndex(false);
    if ($index === null) {
      return $this->out("✗ No package index. Run: pkg update first.\n", 1);
    }

    $installed = $this->getInstalledPackages();
    $upgradable = [];

    foreach ($installed as $name => $info) {
      if (isset($index[$name])) {
        $repoVersion = $index[$name]['Version'] ?? '';
        $localVersion = $info['version'] ?? '';
        if ($repoVersion !== '' && $repoVersion !== $localVersion) {
          $upgradable[] = $name;
        }
      }
    }

    if (empty($upgradable)) {
      $this->emit("✓ All packages are up to date.\n");
      return $this->out("✓ All packages are up to date.\n", 0);
    }
    $log = '';
    $this->log($log, count($upgradable) . " package(s) can be upgraded:\n");
    foreach ($upgradable as $name) {
      $this->log($log, "  → $name\n");
    }
    $this->log($log, "\nUpgrading...\n");

    foreach ($upgradable as $name) {
      $this->removePackageFiles($name);
      $result = $this->installPackage($name, $index);
      $log .= $result['log'];
    }

    return $this->out($log, 0);
  }

  /* ═══════════════════════════════════════════════════════════
    CORE: INSTALL A SINGLE PACKAGE (with dependency resolution)
    ════════════════════════════════════════════════════════════ */

  /* ═══════════════════════════════════════════════════════════
REPAIR / FORCE REINSTALL
═════════════════════════════════════════════════════════════
This exists because a previous space-saving hotfix skipped
include/ folders. Packages installed during that period can
exist in the package database but be missing header files.

Usage:

    pkg repair clang

This removes and reinstalls the requested package plus all of
its resolved dependencies.
════════════════════════════════════════════════════════════ */
  private function cmdRepair(array $names): array
  {
    if (empty($names)) {
      return $this->out("Usage: pkg repair <package> [more packages...]\n", 1);
    }

    if (!$this->ensureBusybox()) {
      return $this->out("✗ Toolbox check failed:\n{$this->lastError}\n", 1);
    }

    $index = $this->fetchPackagesIndex(false);
    if ($index === null) {
      return $this->out("✗ No package index. Run: pkg update first.\n", 1);
    }

    $log = '';
    $exitCode = 0;
    $failed = [];

    foreach ($names as $name) {
      $name = trim($name);
      if ($name === '') {
        continue;
      }

      if (!isset($index[$name])) {
        $this->log($log, "✗ Package '$name' not found in repository.\n");
        $exitCode = 1;
        $failed[] = $name;
        continue;
      }

      $toRepair = [];
      $this->resolveDependencies($name, $index, $toRepair);

      $this->log($log, "Repairing '$name' — force reinstalling " . count($toRepair) . " package(s):\n");
      foreach ($toRepair as $dep) {
        $this->log($log, "  → $dep\n");
      }
      $this->log($log, "\n");

      foreach ($toRepair as $pkgName) {
        // Remove the old record and any files it knows about.
        // This is important because older broken installs may have
        // package records but missing header files.
        $this->removePackageFiles($pkgName);

        $result = $this->downloadAndInstall($pkgName, $index);
        $log .= $result['log'];

        if ($result['exit'] !== 0) {
          $exitCode = 1;
          $log .= "  ⚠ Failed to repair $pkgName. Continuing with remaining packages...\n";
          $failed[] = $pkgName;
        }
      }
    }

    if (!empty($failed)) {
      $log .= "\n⚠ The following packages failed to repair: " . implode(', ', $failed) . "\n";
      $log .= "Try again later, or run: pkg doctor\n";
    } else {
      $log .= "\n✓ Repair finished.\n";
    }

    return $this->out($log, $exitCode);
  }

  /* ═══════════════════════════════════════════════════════════
    ★ SELF-HEALING INSTALL DB (janitor reconciliation)
    ════════════════════════════════════════════════════════════
    MainActivity's launch-time space janitor deletes payload files from
    the prefix while our JSON DB still claims the package is installed.
    The old code answered `pkg install X` with "already installed" and
    never looked at disk — the exact reported bug: clang compiles fine,
    breaks after an app restart, and only `pkg repair clang` fixed it
    (until the next restart).

    Now: before honouring an "already installed" answer we spot-check up
    to the first 8 recorded bin/lib paths of the record on disk. If ≥3 of
    them are gone, the record is treated as BROKEN → auto
    removePackageFiles + full reinstall, exactly like `pkg repair`, but
    triggered automatically by the plain install command.                */

  /** Per-request memo of verdicts so repeated checks stay cheap. */
  private array $brokenCache = [];

  /**
   * Does this installed record still have its important files?
   * Samples the first ~8 recorded bin/ or lib/ paths; a package whose
   * compiler-rt static libs were janitored away fails here even though
   * its DB entry exists.
   */
  private function recordIsBroken(string $name, array $record): bool
  {
    if (isset($this->brokenCache[$name])) {
      return $this->brokenCache[$name];
    }
    $files = (array) ($record['files'] ?? []);
    if (count($files) === 0) {
      // A record with NO file list can't be verified — treat as intact
      // so we don't reinstall everything that ever failed to record.
      return $this->brokenCache[$name] = false;
    }
    $checked = 0;
    $missing = 0;
    foreach ($files as $f) {
      $f = (string) $f;
      // Only sample bin/lib payloads: those are what binaries need at
      // link/run time, and they're exactly what the janitor deletes.
      if (preg_match('#(^|/)(bin|lib)/#', $f)) {
        $checked++;
        if (
          !is_file($this->prefix . '/' . ltrim($f, '/'))
          && !is_link($this->prefix . '/' . ltrim($f, '/'))
        ) {
          $missing++;
        }
        if ($checked >= 8) {
          break;
        }
      }
    }
    // Fewer than 3 bin/lib entries sampled → not enough signal either way.
    $broken = ($checked >= 3 && $missing >= 3);
    return $this->brokenCache[$name] = $broken;
  }

  private function installPackage(string $name, array $index): array
  {
    $log = '';
    $installed = $this->getInstalledPackages();
    if (isset($installed[$name])) {
      if (!$this->recordIsBroken($name, $installed[$name])) {
        $msg = "• '$name' is already installed (" . $installed[$name]['version'] . ").\n";
        $this->emit($msg);
        return ['log' => $msg, 'exit' => 0];
      }
      // ★ SELF-HEAL: DB says installed, disk says gutted → wipe the stale
      // record/files and fall through to a real reinstall (auto-repair).
      $msg = "⚠ '$name' is recorded as installed but files are missing on disk — repairing automatically…\n";
      $this->emit($msg);
      $log .= $msg;
      $this->removePackageFiles($name);
    }
    if (!isset($index[$name])) {
      $msg = "✗ Package '$name' not found in repository.\n";
      $this->emit($msg);
      return ['log' => $msg, 'exit' => 1];
    }

    // Resolve all dependencies
    $toInstall = [];
    $this->resolveDependencies($name, $index, $toInstall);

    // Filter out already-installed packages — but treat BROKEN ones
    // (janitor-deleted payloads) as absent so their deps get restored too.
    $toInstall = array_filter($toInstall, function ($dep) use ($installed) {
      if (!isset($installed[$dep])) {
        return true;
      }
      if ($this->recordIsBroken($dep, $installed[$dep])) {
        $this->removePackageFiles($dep);
        return true;
      }
      return false;
    });

    if (empty($toInstall)) {
      return ['log' => "• All dependencies for '$name' are already satisfied.\n", 'exit' => 0];
    }

    $this->log($log, "The following packages will be installed:\n");
    foreach ($toInstall as $dep) {
      $this->log($log, "  → $dep\n");
    }
    $this->log($log, "\n");

    $exitCode = 0;
    $failedPkgs = [];
    // ★ FIX: no `break` here — one failed dependency shouldn't abort the
    // rest of the tree. We keep trying the remaining packages and report
    // everything that failed at the end.
    foreach ($toInstall as $pkgName) {
      $result = $this->downloadAndInstall($pkgName, $index);
      $log .= $result['log'];
      if ($result['exit'] !== 0) {
        $exitCode = 1;
        $log .= "  ⚠ Failed to install $pkgName. Continuing with remaining packages...\n";
        $failedPkgs[] = $pkgName;
      }
    }
    if (!empty($failedPkgs)) {
      $log .= "\n⚠ The following packages failed to install: " . implode(', ', $failedPkgs) . "\n";
      $log .= "You can try installing them again later using: pkg install " . implode(' ', $failedPkgs) . "\n";
    }
    return ['log' => $log, 'exit' => $exitCode];
  }

  /**
   * Recursively resolve all dependencies for a package.
   */
  private function resolveDependencies(string $name, array $index, array &$resolved, int $depth = 0): void
  {
    if ($depth > 20) return; // Safety: prevent infinite loops
    if (in_array($name, $resolved, true)) return;

    // ★ FIX: Post-order traversal. Add dependencies FIRST, then the package itself.
    // This ensures base libraries (like openssl/libssl) are installed and available
    // in prefix/lib BEFORE busybox tries to extract packages that depend on them.
    if (!isset($index[$name])) {
      $resolved[] = $name;
      return;
    }

    $dependsParts = [];

    if (!empty($index[$name]['Depends'])) {
      $dependsParts[] = $index[$name]['Depends'];
    }

    if (!empty($index[$name]['Pre-Depends'])) {
      $dependsParts[] = $index[$name]['Pre-Depends'];
    }

    $depends = implode(', ', $dependsParts);

    if ($depends !== '') {
      $depends = str_replace(["\r", "\n"], ' ', $depends);
      $deps = preg_split('/,\s*/', $depends);
      foreach ($deps as $dep) {
        $depName = trim(preg_replace('/\s*\(.*\)\s*$/', '', $dep));
        if (strpos($depName, '|') !== false) {
          $depName = trim(explode('|', $depName)[0]);
        }
        $depName = trim($depName, " \t\n\r\0\x0B,");
        if ($depName !== '' && isset($index[$depName])) {
          $this->resolveDependencies($depName, $index, $resolved, $depth + 1);
        }
      }
    }

    // Add the package AFTER its dependencies
    if (!in_array($name, $resolved, true)) {
      $resolved[] = $name;
    }
  }


  /**
   * Download a .deb file and install it.
   */
  private function downloadAndInstall(string $name, array $index): array
  {
    $pkg = $index[$name] ?? null;
    if ($pkg === null) {
      $msg = "✗ '$name' not found in index.\n";
      $this->emit($msg);
      return ['log' => $msg, 'exit' => 1];
    }

    $version = $pkg['Version'] ?? '?';
    $filename = $pkg['Filename'] ?? '';
    $size = (int) ($pkg['Size'] ?? 0);

    if ($filename === '') {
      $msg = "✗ No download URL for '$name'.\n";
      $this->emit($msg);
      return ['log' => $msg, 'exit' => 1];
    }
    $urlSuffix = '/' . ltrim($filename, '/');
    $debPath = $this->tmpDir . '/' . $name . '.deb';
    $log = '';
    $this->log($log, "↓ Downloading $name ($version, " . $this->fmtBytes($size) . ")\n");
    if (!$this->downloadFileWithFallback($urlSuffix, $debPath, $name)) {
      $this->log($log, "✗ Download failed: " . $this->friendlyError($this->lastError) . "\n");
      return ['log' => $log, 'exit' => 1];
    }
    // ★ Final pct:100 for the download phase BEFORE the next log line —
    // guarantees the transient row always ends at a complete bar.
    clearstatcache(true, $debPath);
    $gotBytes = (int) @filesize($debPath);
    if ($gotBytes > 0) {
      /* ★ Both numbers are the SAME artifact (the file on disk), so
               100% and the byte pair always agree. */
      $this->emitProg('download', $name, 100, $gotBytes, $gotBytes);
    } else {
      $this->emitProg('download', $name, 0, 0, 0, true);
    }
    // Verify SHA256 if available
    if (!empty($pkg['SHA256'])) {
      $actual = hash_file('sha256', $debPath);
      if (!hash_equals(strtolower($pkg['SHA256']), (string) $actual)) {
        @unlink($debPath);
        $this->log($log, "✗ Checksum mismatch! File may be corrupted.\n");
        return ['log' => $log, 'exit' => 1];
      }
      $this->log($log, "  ✓ Checksum verified\n");
    }
    // Extract the .deb (Size header = uncompressed estimate → extract %)
    $this->log($log, "  Extracting...\n");
    $extractResult = $this->extractDeb($debPath, $name, $size);
    @unlink($debPath);
    if ($extractResult['error'] !== '') {
      $this->log($log, "✗ Extraction failed: {$extractResult['error']}\n");
      return ['log' => $log, 'exit' => 1];
    }
    // ★ Final pct:100 for the extract phase before the "installed" line.
    $this->emitProg('link', $name, 100, count($extractResult['files']), count($extractResult['files']));

    // Record in database
    $record = [
      'name'    => $name,
      'version' => $version,
      'files'   => $extractResult['files'],
      'time'    => time(),
    ];
    @file_put_contents($this->dbFile($name), json_encode($record, JSON_PRETTY_PRINT));

    // ★ FIX: Some packages (like openjdk) put binaries in opt/ or lib/ 
    // instead of bin/, and rely on dpkg post-install scripts to create 
    // symlinks in $PREFIX/bin. Since we don't run post-install scripts, 
    // we scan for hidden bins and symlink them automatically.
    $this->linkHiddenBins();

    $this->log($log, "  ✓ $name ($version) installed (" . count($extractResult['files']) . " files)\n");
    return ['log' => $log, 'exit' => 0];
  }

  /* ═══════════════════════════════════════════════════════════
    DEB EXTRACTION
    ════════════════════════════════════════════════════════════
    A .deb file is an "ar" archive containing:
      - debian-binary  (text: "2.0\n")
      - control.tar.xz (metadata, scripts)
      - data.tar.xz    (the actual files we want)

    We use busybox to extract these.
    ════════════════════════════════════════════════════════════ */
  private function extractDeb(string $debPath, string $pkgName, int $expectedSize = 0): array
  {
    $busybox = $this->binDir . '/busybox';
    $extractDir = $this->tmpDir . '/deb_' . $pkgName . '_' . getmypid();
    @mkdir($extractDir, 0777, true);

    // Step 1: Extract the ar archive using pure PHP (no busybox 'ar' applet needed)
    $arResult = $this->extractArArchive($debPath, $extractDir);
    if ($arResult['error'] !== '') {
      $this->cleanupDir($extractDir);
      return ['error' => 'ar extraction failed: ' . $arResult['error'], 'files' => []];
    }

    // Step 2: Find the data tarball (could be .tar.xz, .tar.gz, or .tar.zst)
    $dataTar = null;
    foreach (['data.tar.xz', 'data.tar.gz', 'data.tar.zst', 'data.tar.bz2', 'data.tar'] as $candidate) {
      if (is_file($extractDir . '/' . $candidate)) {
        $dataTar = $extractDir . '/' . $candidate;
        break;
      }
    }

    if ($dataTar === null) {
      $this->cleanupDir($extractDir);
      return ['error' => 'Could not find data.tar inside .deb', 'files' => []];
    }

    // ★ DECOMPRESSOR HANDLING (ZSTD):
    // Some Termux packages (e.g. git, curl) ship as .tar.zst. Busybox has
    // no real zstd support, so we need either a bundled static zstd
    // binary (preferred — see $staticZstdPath) or a real installed
    // 'zstd' package.
    //
    // ★ THE BUG THIS REPLACES: the old code tried `pkg install zstd` to
    // bootstrap zstd support, but NEVER checked whether that install
    // actually succeeded. On this device it can't: Termux's own 'zstd'
    // package is ALSO .zst-compressed, so unpacking it requires zstd —
    // which we don't have yet. That's the exact "xz-utils needs liblzma
    // which is itself .xz" bootstrap loop the comment above already
    // warned about for .xz, just not spotted for .zst. installPackage()
    // silently "succeeds" at nothing, and extraction then falls through
    // to busybox's non-functional zstd applet, which exits 0 having
    // decompressed 0 bytes — tar happily "extracts" an empty archive,
    // and the caller sees the misleading "unexpected layout" error
    // instead of the real problem.
    //
    // Fix: only ever attempt the `pkg install zstd` bootstrap once, check
    // whether it actually left us with a working decompressor, and if
    // not, fail LOUDLY and CLEARLY right here — instead of silently
    // continuing into a broken extraction.
    $ext = strtolower(pathinfo($dataTar, PATHINFO_EXTENSION));

    if ($ext === 'zst') {
      $haveWorkingZstd = is_file($this->staticZstdPath)
        || (is_file($this->binDir . '/zstdcat') || is_file($this->binDir . '/zstd'));

      if (!$haveWorkingZstd && $pkgName !== 'zstd' && $pkgName !== 'libzstd') {
        $this->emit("  • Installing 'zstd' (required to extract this package)...\n");
        $index = $this->fetchPackagesIndex(false);
        if ($index !== null) {
          $this->installPackage('zstd', $index);
          if (isset($index['libzstd'])) {
            $this->installPackage('libzstd', $index);
          }
        }
        // Re-check — do NOT assume the install worked.
        $haveWorkingZstd = is_file($this->staticZstdPath)
          || (is_file($this->binDir . '/zstdcat') || is_file($this->binDir . '/zstd'));
      }

      if (!$haveWorkingZstd) {
        $this->cleanupDir($extractDir);
        return [
          'error' => "'$pkgName' is compressed with zstd, and this device has no way to "
            . "decompress it yet. Busybox doesn't support zstd, and the 'zstd' package "
            . "itself is ALSO zstd-compressed, so it can't be installed to bootstrap "
            . "itself (a chicken-and-egg problem). This needs a real zstd binary "
            . "bundled with the app (like busybox already is) before packages like "
            . "'$pkgName' can be installed. Run: pkg doctor",
          'files' => [],
        ];
      }
    }

    // Step 3: Extract the data tarball into the prefix.
    // The bundled busybox binary lacks a fully functional 'tar' applet for
    // .xz archives and falls back to executing 'tar' from PATH, which can
    // cause PATH confusion. /system/bin/tar is also missing on many Android
    // devices. So: use the real GNU 'tar' package from Termux once it's
    // installed, and fall back to `busybox tar` otherwise.
    $busyboxBin = $this->binDir . '/busybox';
    $tarBin = ($this->isPackageInstalled('tar') && is_file($this->prefix . '/bin/tar'))
      ? escapeshellarg($this->prefix . '/bin/tar')
      : (is_file($busyboxBin) ? escapeshellarg($busyboxBin) . ' tar' : 'tar');

    // ★ THE ACTUAL FIX (this is what was breaking every extraction):
    //
    // The previous version built the decompressor as a bare "A || B"
    // string and appended the filename only ONCE, at the very end, after
    // the whole "| tar xf -" pipeline had already been glued on:
    //
    //     $shellCmd = $decompressor . ' ' . $file . ' | ' . $tarBin . ' xf -';
    //     // e.g. "busybox xzcat 2>/dev/null || busybox unxz -c '/tmp/x.xz' | tar xf -"
    //
    // In POSIX shell, `|` binds tighter than `||`, so that string is
    // actually parsed as:
    //
    //     (busybox xzcat 2>/dev/null) || (busybox unxz -c '/tmp/x.xz' | tar xf -)
    //
    // The filename NEVER reached the first command, and if that first
    // command "succeeded" (busybox's dummy xz applet can exit 0 while
    // emitting nothing instead of failing loudly), the fallback + tar
    // pipeline never even ran. On other paths GNU tar's OWN built-in
    // decompression logic kicked in on a raw, still-compressed file and
    // threw "xz: Archive is compressed. Use -J option" from tar's own
    // child process.
    //
    // Fix: give EVERY candidate command its own copy of the file argument
    // and wrap the whole OR-chain in parentheses so shell precedence can't
    // silently split the `||` and `|` apart:
    //
    //     ( A <file> 2>/dev/null || B <file> 2>/dev/null ) | tar xf -
    //
    $bb   = escapeshellarg($busyboxBin);
    $file = escapeshellarg($dataTar);
    $decompressCmd = '';

    if (substr($dataTar, -3) === '.xz') {
      // ★ FIX: prefer the bundled static xz binary (real, always works)
      if (is_file($this->staticXzPath)) {
        $decompressCmd = escapeshellarg($this->staticXzPath) . " -dc $file";
      } elseif ($this->isPackageInstalled('xz-utils') && is_file($this->binDir . '/xzcat')) {
        // Real xzcat is installed — no fallback chain needed.
        $decompressCmd = escapeshellarg($this->binDir . '/xzcat') . " $file";
      } elseif (is_file($busyboxBin)) {
        $decompressCmd = "($bb xzcat $file 2>/dev/null || $bb unxz -c $file 2>/dev/null || $bb xz -dc $file 2>/dev/null)";
      } else {
        $decompressCmd = "(xzcat $file 2>/dev/null || unxz -c $file 2>/dev/null)";
      }
    } elseif (substr($dataTar, -3) === '.gz') {
      if ($this->isPackageInstalled('gzip') && is_file($this->binDir . '/zcat')) {
        $decompressCmd = escapeshellarg($this->binDir . '/zcat') . " $file";
      } elseif (is_file($busyboxBin)) {
        $decompressCmd = "($bb zcat $file 2>/dev/null || $bb gunzip -c $file 2>/dev/null)";
      } else {
        $decompressCmd = "(zcat $file 2>/dev/null || gunzip -c $file 2>/dev/null)";
      }
    } elseif (substr($dataTar, -4) === '.bz2') {
      if ($this->isPackageInstalled('bzip2') && is_file($this->binDir . '/bzcat')) {
        $decompressCmd = escapeshellarg($this->binDir . '/bzcat') . " $file";
      } elseif (is_file($busyboxBin)) {
        $decompressCmd = "($bb bzcat $file 2>/dev/null || $bb bunzip2 -c $file 2>/dev/null)";
      } else {
        $decompressCmd = "(bzcat $file 2>/dev/null || bunzip2 -c $file 2>/dev/null)";
      }
    } elseif (substr($dataTar, -4) === '.zst') {
      // ★ FIX: prefer the bundled static zstd binary (real, always works)
      // over the installed-package path, and over busybox's zstd applet,
      // which does not actually decompress anything (see the big comment
      // above extractDeb() for why installing 'zstd' via pkg can't be
      // relied on to produce a working decompressor).
      if (is_file($this->staticZstdPath)) {
        $decompressCmd = escapeshellarg($this->staticZstdPath) . " -dc $file";
      } elseif ($this->isPackageInstalled('zstd') && is_file($this->binDir . '/zstdcat')) {
        $decompressCmd = escapeshellarg($this->binDir . '/zstdcat') . " $file";
      } elseif (is_file($busyboxBin)) {
        $decompressCmd = "($bb zstdcat $file 2>/dev/null || $bb zstd -dc $file 2>/dev/null)";
      } else {
        $decompressCmd = "(zstdcat $file 2>/dev/null || zstd -dc $file 2>/dev/null)";
      }
    }

    // ★ FIX: Avoid pipes for decompression. If the decompressor fails (e.g. missing
    // libzstd.so), the pipe sends 0 bytes to tar, tar exits 0, and we get a
    // confusing "unexpected layout" error. By writing to a temp file and using
    // `&&`, we guarantee that if the decompressor fails, the whole command fails
    // with the correct exit code and stderr output.
    // ★ FIX 2: Add `[ -s file ]` to catch dummy busybox applets (like xzcat/zstdcat)
    // that exit 0 but produce 0 bytes. If the decoded tar is empty, the shell
    // command will fail BEFORE running tar, preventing the "unexpected layout" error.
    $decodedTar = $extractDir . '/data_decoded.tar';
    if ($decompressCmd !== '') {
      $shellCmd = $decompressCmd . ' > ' . escapeshellarg($decodedTar) . ' && [ -s ' . escapeshellarg($decodedTar) . ' ] && ' . $tarBin . ' xf ' . escapeshellarg($decodedTar);
    } else {
      $shellCmd = $tarBin . ' xf ' . $file;
    }

    $descriptors = [
      0 => ['pipe', 'r'],
      1 => ['pipe', 'w'],
      2 => ['pipe', 'w'],
    ];

    $busyboxEnv = (array) @getenv();
    $appLib = getenv('QUIRKY_APPLIB');
    if ($appLib !== false && $appLib !== '') {
      $busyboxEnv['LD_LIBRARY_PATH'] = $this->libDir . ':' . $appLib;
    } else {
      $busyboxEnv['LD_LIBRARY_PATH'] = $this->libDir;
    }
    $busyboxEnv['PATH'] = $this->binDir . ':' . ($busyboxEnv['PATH'] ?? '/system/bin');

    $proc = @proc_open(['/bin/sh', '-c', $shellCmd], $descriptors, $pipes, $extractDir, $busyboxEnv);

    if (!is_resource($proc)) {
      $this->cleanupDir($extractDir);
      return ['error' => 'Could not start tar extraction process', 'files' => []];
    }

    // No stdin needed — close it immediately so tar can never block
    // waiting on input.
    fclose($pipes[0]);
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);

    $tarTimeout = 90; // seconds — generous for even large packages
    $start = microtime(true);
    $output = '';
    $exitCode = -1;

    while (true) {
      $status = proc_get_status($proc);
      $output .= (string) stream_get_contents($pipes[1]);
      $output .= (string) stream_get_contents($pipes[2]);

      if (!$status['running']) {
        $exitCode = $status['exitcode'];
        break;
      }

      // ★ POLISH PASS — EXTRACT-PHASE PROGRESS: poll the decoded tar's
      // size against the deb's Size header estimate every ~300 ms (the
      // select ceiling below wakes us anyway). This keeps the single
      // progress row alive during the quietest part of an install AND
      // doubles as the SSE heartbeat so a disconnect is still noticed.
      clearstatcache(true, $decodedTar);
      $extracted = (int) @filesize($decodedTar);
      if ($extracted > 0) {
        /* ★ HOTFIX: extraction reads the UNPACKED tar stream while
                       $expectedSize is the COMPRESSED .deb Size header — two
                       different artifacts, so no percentage is derivable and
                       got/total would violate the invariant. Report an honest
                       indeterminate byte counter instead. */
        $this->emitProg('extract', $pkgName, 0, $extracted, 0, true);
      }

      $elapsed = microtime(true) - $start;
      if ($elapsed > $tarTimeout) {
        proc_terminate($proc, 9);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($proc);
        $this->cleanupDir($extractDir);
        return [
          'error' => "tar extraction timed out after {$tarTimeout}s — device may be "
            . 'under I/O pressure, or this busybox build is hanging on this archive '
            . '(run: pkg doctor)',
          'files' => [],
        ];
      }

      // Sleep until tar actually produces output (or up to 0.5s, so we
      // still notice the timeout promptly) instead of unconditionally
      // waking up every 100ms whether there's anything to read or not.
      // Large packages (python, nodejs, etc.) can take several seconds
      // to extract, and this used to spin the CPU the entire time.
      $this->waitForActivity($pipes, min(0.5, $tarTimeout - $elapsed));
    }

    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($proc);

    if ($exitCode !== 0) {
      $errMsg = trim($output);
      // ★ FIX: Detect dummy decompressor failure (0 bytes output).
      // Busybox's xzcat and zstdcat applets are dummies that exit 0 but write 0 bytes.
      // The `[ -s file ]` check in the shell command catches this and fails the command.
      if ($decompressCmd !== '' && is_file($decodedTar)) {
        clearstatcache(true, $decodedTar);
        if (filesize($decodedTar) === 0) {
          $ext = strtolower(pathinfo($dataTar, PATHINFO_EXTENSION));
          $neededPkg = ($ext === 'zst') ? 'zstd' : 'xz-utils';
          $errMsg = "Decompressor produced 0 bytes. This package is compressed with .$ext, "
            . "which the bundled busybox cannot handle. It requires the '$neededPkg' package, "
            . "but that package itself is compressed with .$ext and cannot be bootstrapped "
            . "on this device without a static binary. Run: pkg doctor";
        }
      }
      $this->cleanupDir($extractDir);
      return ['error' => 'tar extraction failed (exit ' . $exitCode . '): ' . $errMsg, 'files' => []];
    }

    // ★ FIX 1: Remove the raw .deb component files so they don't pollute $PREFIX
    foreach (['data.tar.xz', 'data.tar.gz', 'data.tar.zst', 'data.tar.bz2', 'data.tar', 'data_decoded.tar', 'control.tar.xz', 'control.tar.gz', 'control.tar.zst', 'debian-binary'] as $junk) {
      @unlink($extractDir . '/' . $junk);
    }

    // Step 4: Locate the real content root, however deeply the local
    // tar/busybox build nested it (usr/, data/, data/data/, ...), and
    // ONLY copy from there. Never fall back to copying $extractDir
    // itself — that's how archive junk (debian-binary, control.tar.xz,
    // data.tar.xz) used to leak straight into $PREFIX.
    $installedFiles = [];
    $sourceDir = $this->locateContentRoot($extractDir);

    if ($sourceDir === null) {
      $this->cleanupDir($extractDir);
      return [
        'error' => 'Could not locate package content inside the extracted archive '
          . '(unexpected layout from this device\'s tar build)',
        'files' => [],
      ];
    }

    $this->copyTree($sourceDir, $this->prefix, $installedFiles);

    // Step 5: Make binaries executable
    foreach ($installedFiles as $f) {
      $abs = $this->prefix . '/' . $f;
      // Match anything in a bin/ directory (e.g. bin/python, usr/bin/python)
      if (preg_match('#(^|/)bin/[^/]+$#', $f) && is_file($abs)) {
        @chmod($abs, 0755);
      }
    }

    // Cleanup
    $this->cleanupDir($extractDir);

    return ['error' => '', 'files' => $installedFiles];
  }

  /**
   * Scan $PREFIX/opt/* /bin and $PREFIX/lib/* /bin and symlink executables 
   * into $PREFIX/bin. Termux packages like openjdk put binaries in these
   * subdirectories and rely on post-install scripts to create the symlinks
   * in the main bin folder. Since we extract raw .deb files without running
   * those scripts, we must create the symlinks ourselves.
   */
  private function linkHiddenBins(): void
  {
    $searchDirs = [];

    // Scan opt/*/bin and one level deeper (e.g. opt/package/bin)
    $optDir = $this->prefix . '/opt';
    if (is_dir($optDir)) {
      foreach (@scandir($optDir) ?: [] as $d) {
        if ($d === '.' || $d === '..') continue;
        $p = $optDir . '/' . $d . '/bin';
        if (is_dir($p)) $searchDirs[] = $p;

        // Scan one level deeper
        $subDir = $optDir . '/' . $d;
        if (is_dir($subDir)) {
          foreach (@scandir($subDir) ?: [] as $sd) {
            if ($sd === '.' || $sd === '..') continue;
            $sp = $subDir . '/' . $sd . '/bin';
            if (is_dir($sp)) $searchDirs[] = $sp;
          }
        }
      }
    }

    // Scan lib/*/bin and deeper (e.g. lib/jvm/java-21-openjdk/bin)
    $libDir = $this->prefix . '/lib';
    if (is_dir($libDir)) {
      foreach (@scandir($libDir) ?: [] as $d) {
        if ($d === '.' || $d === '..') continue;
        $p = $libDir . '/' . $d . '/bin';
        if (is_dir($p)) $searchDirs[] = $p;
        // Scan one level deeper (catches lib/jvm/java-XX-openjdk/bin)
        $subDir = $libDir . '/' . $d;
        if (is_dir($subDir)) {
          foreach (@scandir($subDir) ?: [] as $sd) {
            if ($sd === '.' || $sd === '..') continue;
            $sp = $subDir . '/' . $sd . '/bin';
            if (is_dir($sp)) $searchDirs[] = $sp;
          }
        }
      }
    }

    // ★ FIX: Termux installs git helper binaries (like git-remote-https,
    // git-upload-pack) inside `libexec/git-core/` or `lib/git-core/`.
    // Symlink them into `bin/` so `git` can always find them via PATH,
    // fixing the "git: 'remote-https' is not a git command" error.
    $gitHelperDirs = [
      $this->prefix . '/libexec/git-core',
      $this->prefix . '/lib/git-core',
    ];
    foreach ($gitHelperDirs as $ghDir) {
      if (is_dir($ghDir)) {
        $searchDirs[] = $ghDir;
      }
    }

    foreach ($searchDirs as $binDir) {
      foreach (@scandir($binDir) ?: [] as $file) {
        if ($file === '.' || $file === '..') continue;
        $src = $binDir . '/' . $file;
        $dest = $this->binDir . '/' . $file;
        if (is_file($src) && !file_exists($dest)) {
          @chmod($src, 0755); // Ensure it's executable
          @symlink($src, $dest);
        }
      }
    }
  }

  /**
   * Find the real root of a package's extracted content, no matter how
   * many wrapper folders the local tar/busybox build introduced. Termux
   * packages always have at least one of bin/, lib/, etc/, share/,
   * libexec/, include/, opt/, var/ at their real top level. We descend
   * through known wrapper names (usr/, data/) and, as a last resort,
   * through any single lone subdirectory, until we find one of those
   * markers or give up (depth-capped so a malformed archive can't loop).
   */
  private function locateContentRoot(string $dir, int $depth = 0): ?string
  {
    static $markers = ['bin', 'lib', 'etc', 'share', 'libexec', 'include', 'opt', 'var'];

    foreach ($markers as $m) {
      if (is_dir($dir . '/' . $m)) {
        return $dir;
      }
    }

    if ($depth > 5) {
      return null;
    }

    foreach (['usr', 'data'] as $wrapper) {
      $candidate = $dir . '/' . $wrapper;
      if (is_dir($candidate)) {
        $found = $this->locateContentRoot($candidate, $depth + 1);
        if ($found !== null) {
          return $found;
        }
      }
    }

    // Last resort: exactly one real subdirectory left (ignoring leftover
    // ar-member junk) → descend into it.
    $entries = (array) @scandir($dir);
    $entries = array_values(array_filter(
      $entries,
      fn($e) => $e !== '.' && $e !== '..'
        && $e !== 'debian-binary'
        && !preg_match('/^(control|data)\.tar(\.\w+)?$/', $e)
    ));
    if (count($entries) === 1 && is_dir($dir . '/' . $entries[0])) {
      return $this->locateContentRoot($dir . '/' . $entries[0], $depth + 1);
    }

    return null;
  }

  /**
   * Pure PHP .deb (ar archive) extractor.
   * Bypasses the need for the 'ar' applet in busybox, which is often
   * missing from minimal Android busybox builds.
   */
  private function extractArArchive(string $arPath, string $destDir): array
  {
    $files = [];
    $fp = @fopen($arPath, 'rb');
    if (!$fp) {
      return ['error' => 'Cannot open .deb file for reading', 'files' => []];
    }

    $magic = fread($fp, 8);
    if ($magic !== "!<arch>\n") {
      fclose($fp);
      return ['error' => 'Not a valid ar archive (bad magic)', 'files' => []];
    }

    while (!feof($fp)) {
      $header = fread($fp, 60);
      if (strlen($header) < 60) {
        break; // End of file or truncated
      }

      $name = trim(substr($header, 0, 16));
      $sizeStr = trim(substr($header, 48, 10));
      $size = (int) $sizeStr;

      // Skip special GNU ar extensions (symbol table, long names)
      if ($name === '/' || $name === '//') {
        $skip = $size + ($size % 2);
        fseek($fp, $skip, SEEK_CUR);
        continue;
      }

      // Clean up name (remove trailing slash used in SVR4 ar format)
      $name = rtrim($name, '/');
      if ($name === '') {
        $skip = $size + ($size % 2);
        fseek($fp, $skip, SEEK_CUR);
        continue;
      }

      $outFile = $destDir . '/' . basename($name);
      $files[] = $name;

      $fOut = @fopen($outFile, 'wb');
      if (!$fOut) {
        fclose($fp);
        return ['error' => 'Cannot write extracted file: ' . $name, 'files' => $files];
      }

      $remaining = $size;
      while ($remaining > 0) {
        $chunkSize = min(8192, $remaining);
        $chunk = fread($fp, $chunkSize);
        if ($chunk === false || $chunk === '') {
          break;
        }
        fwrite($fOut, $chunk);
        $remaining -= strlen($chunk);
      }
      fclose($fOut);

      // ar pads files to an even byte boundary
      if ($size % 2 !== 0) {
        fseek($fp, 1, SEEK_CUR);
      }
    }

    fclose($fp);
    return ['error' => '', 'files' => $files];
  }

  /**
   * Recursively copy extracted files into the prefix.
   */
  private function copyTree(string $source, string $dest, array &$files, string $relPath = ''): void
  {
    $entries = @scandir($source);
    if ($entries === false) return;
    $termuxPrefix = '/data/data/com.termux/files/usr';
    foreach ($entries as $entry) {
      if ($entry === '.' || $entry === '..') continue;
      // Never let raw .deb/ar member files get installed into the
      // prefix, no matter what called us.
      if ($entry === 'debian-binary' || preg_match('/^(control|data)\.tar(\.\w+)?$/', $entry)) {
        continue;
      }
      $srcPath = $source . '/' . $entry;
      $dstPath = $dest . '/' . $entry;
      $rel = ($relPath === '') ? $entry : $relPath . '/' . $entry;
      // ★ NEW: Skip bloat directories to save massive amounts of space
      if (is_dir($srcPath) && $this->isBloatDirectory($rel)) {
        continue;
      }
      if (is_link($srcPath)) {
        $target = @readlink($srcPath);
        if ($target !== false) {
          // ★ FIX 1: Rewrite absolute Termux symlinks to our prefix.
          // Termux .deb packages often contain absolute symlinks pointing
          // to /data/data/com.termux/files/usr/... which don't exist here.
          if (strpos($target, $termuxPrefix) === 0) {
            $target = $this->prefix . substr($target, strlen($termuxPrefix));
          }
          // ★ FIX 3 (busybox-clobber fix): if the destination is one of
          // our busybox shortcut symlinks (e.g. bin/more → busybox),
          // replace it with the package's own symlink instead of letting
          // the old shortcut shadow the real package file.
          if (is_link($dstPath)) {
            @unlink($dstPath);
          }
          @symlink($target, $dstPath);
          $files[] = $rel;
        }
      } elseif (is_dir($srcPath)) {
        if (!is_dir($dstPath)) {
          @mkdir($dstPath, 0777, true);
        }
        $this->copyTree($srcPath, $dstPath, $files, $rel);
      } else {
        // ★ FIX 4 (THE busybox-clobber fix): PHP's copy() FOLLOWS a symlink
        // on the destination side. Packages like curl (bin/curl), less
        // (bin/less, bin/more), wget (bin/wget) or vim (bin/vi) ship real
        // files whose destination is currently a busybox shortcut symlink.
        // Copying "through" the shortcut overwrites the busybox binary
        // itself — turning every remaining shortcut (tar, xz, gzip, ...)
        // into the just-installed program. That is exactly the bug where
        // the next extraction silently runs "less" instead of tar, or runs
        // real curl and prints "Could not resolve host: tar".
        // Remove the shortcut symlink first so the real file lands in its
        // own inode and busybox stays intact.
        if (is_link($dstPath)) {
          @unlink($dstPath);
        }
        if (@copy($srcPath, $dstPath)) {
          $files[] = $rel;
          // ★ FIX 2: Rewrite Termux shebangs in scripts.
          // Scripts like pip or npm start with #!/data/data/com.termux/files/usr/bin/...
          // If we don't rewrite them, the shell can't find the interpreter.
          $this->rewriteScriptPaths($dstPath, $termuxPrefix);
        }
      }
    }
  }

  /**
   * Rewrites hardcoded Termux prefix paths in text scripts (shebangs).
   * Only touches files that start with #! to avoid corrupting ELF binaries.
   */
  private function rewriteScriptPaths(string $filePath, string $termuxPrefix): void
  {
    $fp = @fopen($filePath, 'rb');
    if (!$fp) return;
    $header = fread($fp, 256);
    fclose($fp);

    // Only process if it's a text script starting with #! and contains the Termux prefix
    if (strpos($header, '#!') === 0 && strpos($header, $termuxPrefix) !== false) {
      $content = file_get_contents($filePath);
      if ($content !== false) {
        $newContent = str_replace($termuxPrefix, $this->prefix, $content);
        if ($newContent !== $content) {
          file_put_contents($filePath, $newContent);
        }
      }
    }
  }

  /* ═══════════════════════════════════════════════════════════
    PACKAGE INDEX FETCHING & PARSING
    ════════════════════════════════════════════════════════════ */

  /**
   * Fetch and parse the Debian Packages index from Termux's repo.
   * Caches to disk so we don't re-download every time.
   */
  private function fetchPackagesIndex(bool $force = false): ?array
  {
    $cacheFile = $this->dbDir . '/packages-index.json';

    // Use cache if fresh (less than 24 hours old) and not forced
    if (!$force && is_file($cacheFile)) {
      $age = time() - (int) filemtime($cacheFile);
      if ($age < 86400) {
        $cached = json_decode((string) file_get_contents($cacheFile), true);
        if (is_array($cached) && !empty($cached)) {
          $this->indexCache = $cached;
          return $cached;
        }
      }
    }

    // Download the Packages index with automatic mirror fallback
    $rawContent = $this->downloadToStringWithFallback('/dists/stable/main/binary-aarch64/Packages.gz');
    if ($rawContent === null) {
      return null;
    }

    // Handle compressed index (Packages.gz or Packages.xz)
    if (substr($this->packagesIndexUrl, -3) === '.gz') {
      $rawContent = gzdecode($rawContent);
    }

    if ($rawContent === false || $rawContent === '') {
      $this->lastError = 'Empty or invalid package index.';
      return null;
    }

    // Parse the Debian Packages format
    $packages = $this->parsePackagesIndex($rawContent);

    if (empty($packages)) {
      $this->lastError = 'Could not parse any packages from the index.';
      return null;
    }

    // Cache to disk
    @file_put_contents($cacheFile, json_encode($packages), LOCK_EX);
    $this->indexCache = $packages;

    return $packages;
  }

  /**
   * Parse the Debian Packages file format into an associative array.
   * Format: blocks of "Key: Value" separated by blank lines.
   */
  private function parsePackagesIndex(string $content): array
  {
    $packages = [];
    $current = [];
    $lines = explode("\n", $content);
    $lastKey = '';

    foreach ($lines as $line) {
      $line = rtrim($line, "\r");
      if ($line === '') {
        // End of a package entry
        if (!empty($current) && isset($current['Package'])) {
          $packages[$current['Package']] = $current;
        }
        $current = [];
        $lastKey = '';
        continue;
      }
      // Continuation line (starts with space or tab)
      if (isset($line[0]) && ($line[0] === ' ' || $line[0] === "\t")) {
        if ($lastKey !== '' && isset($current[$lastKey])) {
          // Append to the last key (crucial for long Depends/Description fields)
          $current[$lastKey] .= "\n" . ltrim($line);
        }
        continue;
      }
      // Key: Value line
      $colonPos = strpos($line, ':');
      if ($colonPos !== false) {
        $key = trim(substr($line, 0, $colonPos));
        $value = trim(substr($line, $colonPos + 1));
        $current[$key] = $value;
        $lastKey = $key;
      }
    }
    // Don't forget the last entry
    if (!empty($current) && isset($current['Package'])) {
      $packages[$current['Package']] = $current;
    }
    return $packages;
  }

  /* ═══════════════════════════════════════════════════════════
    INSTALLED PACKAGES DATABASE
    ════════════════════════════════════════════════════════════ */

  private function getInstalledPackages(): array
  {
    $installed = [];
    foreach ((glob($this->dbDir . '/*.json') ?: []) as $f) {
      $base = basename($f, '.json');
      if ($base === 'packages-index') continue; // Skip the index cache

      $data = json_decode((string) file_get_contents($f), true);
      if (is_array($data) && isset($data['name'])) {
        $installed[$data['name']] = $data;
      }
    }
    return $installed;
  }

  private function dbFile(string $name): string
  {
    return $this->dbDir . '/' . $name . '.json';
  }

  private function removePackageFiles(string $name): void
  {
    $dbFile = $this->dbFile($name);
    if (!is_file($dbFile)) return;

    $record = json_decode((string) file_get_contents($dbFile), true);
    $files = (array) ($record['files'] ?? []);

    foreach ($files as $f) {
      $abs = $this->prefix . '/' . ltrim((string) $f, '/');
      if (is_link($abs) || is_file($abs)) {
        @unlink($abs);
      }
    }
    @unlink($dbFile);
  }

  /* ═══════════════════════════════════════════════════════════
    NETWORK HELPERS
    ════════════════════════════════════════════════════════════
    Both helpers below now point cURL (and the file_get_contents
    fallback) at the bundled CA certificate file, so HTTPS downloads
    from the Termux repo are actually verified instead of silently
    trusting anything or failing outright.
    ════════════════════════════════════════════════════════════ */

  /**
   * Identifies directories inside .deb packages that are useless for an IDE runtime.
   * Skipping these saves 20-60MB per package (especially Python, Node, and Git).
   */
  private function isBloatDirectory(string $relPath): bool
  {
    // Skip man pages, docs, info, bash-completion, zsh completions, locale.
    // These are safe to omit for an IDE/runtime environment.
    if (preg_match('#(^|/)share/(man|doc|info|bash-completion|zsh|locale|gtk-doc)#', $relPath)) {
      return true;
    }

    // Skip Python/Node test directories and pip vendor bloat.
    if (preg_match('#(^|/)lib/.*/(test|tests|testing|__pycache__|site-packages/pip/_vendor)#', $relPath)) {
      return true;
    }

    // IMPORTANT: do NOT skip include/ directories.
    // Compilers and development packages need header files.
    // Skipping include/ breaks clang/gcc with errors like:
    // fatal error: 'stdio.h' file not found
    return false;
  }

  private function downloadFile(string $url, string $dest, string $label = ''): bool
  {
    $this->lastError = '';
    $haveCaCert = is_file($this->caCertPath);

    if (!function_exists('curl_init')) {
      $sslOpts = ['verify_peer' => $haveCaCert, 'verify_peer_name' => $haveCaCert];
      if ($haveCaCert) {
        $sslOpts['cafile'] = $this->caCertPath;
      }
      $ctx = stream_context_create([
        'http' => ['timeout' => $this->timeout, 'user_agent' => 'QuirkyIDE-pkg/2.0'],
        'ssl'  => $sslOpts,
      ]);
      $data = @file_get_contents($url, false, $ctx);
      if ($data === false) {
        $this->lastError = 'Download failed (no cURL)';
        return false;
      }
      return @file_put_contents($dest, $data) !== false;
    }

    $fp = @fopen($dest, 'w');
    if ($fp === false) {
      $this->lastError = 'Cannot create download file';
      return false;
    }

    // Capture a verbose cURL trace so that IF this fails, we get a real
    // diagnostic (TLS handshake details, redirect chain, etc.) instead of
    // a one-line guess. Only shown to the user when something goes wrong.
    $verboseLog = fopen('php://temp', 'w+');

    $ch = curl_init($url);
    $opts = [
      CURLOPT_FILE           => $fp,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_MAXREDIRS      => 5,
      CURLOPT_TIMEOUT        => $this->timeout,
      CURLOPT_CONNECTTIMEOUT => 15,
      // ★ SPEED FIX: abandon slow mirrors much faster.
      // If a mirror drops below 10 KB/s for 15 seconds, cut it off and
      // try the next mirror. The old 1 KB/s limit meant a crawling server
      // could hold up the install for minutes before giving up.
      CURLOPT_LOW_SPEED_LIMIT => 10240,
      CURLOPT_LOW_SPEED_TIME  => 15,
      // Force plain HTTP/1.1. Some mobile carriers / proxies interfere
      // with HTTP/2 or HTTP/3 stream handling and reset the connection
      // mid-transfer — which shows up as exactly the
      // "Recv failure: Software caused connection abort" error.
      CURLOPT_HTTP_VERSION    => CURL_HTTP_VERSION_1_1,
      CURLOPT_SSL_VERIFYPEER  => $haveCaCert,
      CURLOPT_SSL_VERIFYHOST  => $haveCaCert ? 2 : 0,
      CURLOPT_USERAGENT       => 'QuirkyIDE-pkg/2.0',
      CURLOPT_VERBOSE         => true,
      CURLOPT_STDERR          => $verboseLog,
    ];
    if ($haveCaCert) {
      $opts[CURLOPT_CAINFO] = $this->caCertPath;
    }
    // ★ LIVE PROGRESS: one transient row, redrawn in place by the
    // frontend (like apt's status line). Structured fields ride along so
    // the UI renders its own compact bar; legacy text kept for old clients.
    // When Content-Length is missing (dlTotal == 0) we still emit —
    // flagged indeterminate — throttled by time instead of percentage.
    $lastPct = -1;
    $opts[CURLOPT_NOPROGRESS] = false;
    $opts[CURLOPT_PROGRESSFUNCTION] = function ($ch, $dlTotal, $dlNow) use (&$lastPct, $label) {
      $name = ($label !== '') ? $label : 'download';
      if ($dlTotal > 0) {
        $pct = (int) floor(($dlNow / $dlTotal) * 100);
        if ($pct !== $lastPct && ($pct - $lastPct >= 5 || $pct >= 100)) {
          $lastPct = $pct;
          $this->emitProg('download', $name, $pct, (int) $dlNow, (int) $dlTotal);
        }
      } elseif ((microtime(true) - $this->lastProgTs) >= 0.5) {
        $this->emitProg('download', $name, 0, (int) $dlNow, 0, true);
      }
      return 0;
    };
    curl_setopt_array($ch, $opts);
    curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = (string) curl_error($ch);
    // curl_close() is a no-op as of PHP 8.0 and deprecated as of PHP 8.5 —
    // the handle is freed automatically, so we just let it go out of scope.
    fclose($fp);
    if ($err !== '' || $code !== 200) {
      @unlink($dest);
      // Short reason only; the raw trace goes to `pkg doctor`.
      $this->lastError = $err !== '' ? $err : "HTTP $code";
      rewind($verboseLog);
      $trace = stream_get_contents($verboseLog);
      $this->saveLastTrace(($trace !== false && $trace !== '') ? substr(trim($trace), -1500) : '');
      fclose($verboseLog);
      return false;
    }

    fclose($verboseLog);
    return true;
  }

  private function downloadToString(string $url, string $label = 'index'): ?string
  {
    $this->lastError = '';
    $haveCaCert = is_file($this->caCertPath);

    // This is only ever used for the package index, which is now
    // gzip-compressed (a few hundred KB), so it doesn't need the full
    // 5-minute .deb-download timeout — but on a slow connection even a
    // few hundred KB can take a while, so give it more breathing room
    // than a bare 60 seconds.
    $indexTimeout = min($this->timeout, 180);

    if (function_exists('curl_init')) {
      $verboseLog = fopen('php://temp', 'w+');

      $ch = curl_init($url);
      $opts = [
        CURLOPT_RETURNTRANSFER  => true,
        CURLOPT_FOLLOWLOCATION  => true,
        CURLOPT_MAXREDIRS       => 5,
        CURLOPT_TIMEOUT         => $indexTimeout,
        CURLOPT_CONNECTTIMEOUT  => 15,
        // ★ SPEED FIX: abandon slow mirrors much faster (see downloadFile).
        CURLOPT_LOW_SPEED_LIMIT => 10240,
        CURLOPT_LOW_SPEED_TIME  => 15,
        CURLOPT_HTTP_VERSION    => CURL_HTTP_VERSION_1_1,
        CURLOPT_SSL_VERIFYPEER  => $haveCaCert,
        CURLOPT_SSL_VERIFYHOST  => $haveCaCert ? 2 : 0,
        CURLOPT_USERAGENT       => 'QuirkyIDE-pkg/2.0',
        CURLOPT_VERBOSE         => true,
        CURLOPT_STDERR          => $verboseLog,
      ];
      if ($haveCaCert) {
        $opts[CURLOPT_CAINFO] = $this->caCertPath;
      }
      // ★ LIVE PROGRESS: transient row, redrawn in place (every 5%).
      // Structured fields included (phase 'index'); indeterminate mode
      // when the server sends no Content-Length.
      $lastPct = -1;
      $opts[CURLOPT_NOPROGRESS] = false;
      $opts[CURLOPT_PROGRESSFUNCTION] = function ($ch, $dlTotal, $dlNow) use (&$lastPct, $label) {
        if ($dlTotal > 0) {
          $pct = (int) floor(($dlNow / $dlTotal) * 100);
          if ($pct !== $lastPct && ($pct - $lastPct >= 5 || $pct >= 100)) {
            $lastPct = $pct;
            $this->emitProg('index', $label, $pct, (int) $dlNow, (int) $dlTotal);
          }
        } elseif ((microtime(true) - $this->lastProgTs) >= 0.5) {
          $this->emitProg('index', $label, 0, (int) $dlNow, 0, true);
        }
        return 0;
      };
      curl_setopt_array($ch, $opts);
      $result = curl_exec($ch);
      $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
      $err  = (string) curl_error($ch);
      // curl_close() is a no-op as of PHP 8.0 / deprecated as of PHP 8.5 —
      // intentionally not calling it anymore.
      if ($result === false || $code !== 200) {
        $this->lastError = $err !== '' ? $err : "HTTP $code";
        rewind($verboseLog);
        $trace = stream_get_contents($verboseLog);
        $this->saveLastTrace(($trace !== false && $trace !== '') ? substr(trim($trace), -1500) : '');
        fclose($verboseLog);
        return null;
      }
      fclose($verboseLog);
      return (string) $result;
    }

    $sslOpts = ['verify_peer' => $haveCaCert, 'verify_peer_name' => $haveCaCert];
    if ($haveCaCert) {
      $sslOpts['cafile'] = $this->caCertPath;
    }
    $ctx = stream_context_create([
      'http' => ['timeout' => $indexTimeout, 'user_agent' => 'QuirkyIDE-pkg/2.0'],
      'ssl'  => $sslOpts,
    ]);
    $data = @file_get_contents($url, false, $ctx);
    if ($data === false) {
      $this->lastError = 'Download failed';
      return null;
    }
    return $data;
  }

  /* ═══════════════════════════════════════════════════════════
    UTILITIES
    ════════════════════════════════════════════════════════════ */

  private function cleanupDir(string $dir): void
  {
    if (!is_dir($dir)) return;
    $items = new RecursiveIteratorIterator(
      new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
      RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) {
      if ($item->isDir()) {
        @rmdir($item->getPathname());
      } else {
        @unlink($item->getPathname());
      }
    }
    @rmdir($dir);
  }

  private function isEnabled(): bool
  {
    return !empty($this->config['features']['pkg_enabled']);
  }

  private function fmtBytes(int $b): string
  {
    if ($b <= 0) return '0 B';
    $u = ['B', 'KB', 'MB', 'GB'];
    $i = min((int) floor(log($b, 1024)), count($u) - 1);
    return round($b / pow(1024, $i), 1) . ' ' . $u[$i];
  }

  private function helpText(): string
  {
    return "Quirky pkg — Termux-compatible package manager\n"
      . "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
      . "  pkg update              Download latest package list\n"
      . "  pkg search <text>       Search for packages\n"
      . "  pkg info <name>         Show package details\n"
      . "  pkg install <name>      Install a package (+ dependencies)\n"
      . "  pkg repair <name>       Force reinstall package + dependencies\n"
      . "  pkg remove <name>       Uninstall a package\n"
      . "  pkg list                Show available packages\n"
      . "  pkg list --all          Show ALL packages (3000+)\n"
      . "  pkg installed           Show installed packages\n"
      . "  pkg upgrade             Upgrade all installed packages\n"
      . "  pkg depends <name>      Show dependency tree\n"
      . "  pkg mirrors              List and change download mirrors\n"
      . "  pkg doctor               Check that busybox/CA cert are set up\n"
      . "  pkg help                Show this help\n"
      . "\n"
      . "Examples:\n"
      . "  pkg install python\n"
      . "  pkg install git wget curl\n"
      . "  pkg search node\n"
      . "\n"
      . "Packages come from Termux's official repository.\n"
      . "Installed to: {$this->prefix}\n";
  }

  private function out(string $text, int $code): array
  {
    return ['output' => $text, 'error' => '', 'exitCode' => $code];
  }
}
