<?php

declare(strict_types=1);
/**
 * ═══════════════════════════════════════════════════════════════════════════
 *  QUIRKY IDE — WORKSHOP SERVICE (Phase 2 · Apache Extension + ELF Fix + Diagnostics)
 * ═══════════════════════════════════════════════════════════════════════════
 *
 *  CRITICAL FIX in this revision:
 *    Termux .deb binaries have the Termux linker path hardcoded in their
 *    ELF header (PT_INTERP = /data/data/com.termux/files/usr/bin/linker64).
 *    Our app lives at /data/user/0/com.quirky.ide/, so the kernel can't
 *    find that linker and kills the process with exit 127.
 *
 *    The fix: patchElfInterpreter() rewrites the PT_INTERP path inside
 *    the ELF binary to /system/bin/linker64 (the Android system linker),
 *    which exists on every device. Libraries are still found via
 *    LD_LIBRARY_PATH, which we set correctly.
 *
 * ═══════════════════════════════════════════════════════════════════════════
 */
class IdeWorkshop
{
  private array $config;
  private IdeSecurity $security;
  private string $stateDir;
  private string $stateFile;
  private string $prefix;
  private string $binDir;
  private string $appLibDir;
  private ?IdePkg $pkg = null;
  private ?IdeWorkshopLogger $logger = null;

  private const EXTENSIONS = [
    'apache' => [
      'name'        => 'Apache Web Server',
      'description' => 'Full Apache HTTP server with .htaccess, mod_rewrite, and PHP support. Enables real-server preview for PHP projects with pretty URLs.',
      'icon'        => '🌐',
      'packages'    => ['apache2', 'php-apache'],
      'size'        => '~52 MB',
      'provides'    => ['htaccess', 'mod_rewrite', 'php-fpm', 'virtual-hosts'],
      'port'        => 8082,
    ],
  ];

  public function __construct(array $config, IdeSecurity $security)
  {
    $this->config   = $config;
    $this->security = $security;

    $this->stateDir  = IDE_APP . '/.workshop';
    $this->stateFile = $this->stateDir . '/extensions.json';

    if (!is_dir($this->stateDir)) {
      @mkdir($this->stateDir, 0777, true);
    }

    foreach (['apache', 'apache/logs'] as $sub) {
      $d = $this->stateDir . '/' . $sub;
      if (!is_dir($d)) {
        @mkdir($d, 0777, true);
      }
    }

    $prefix = getenv('QUIRKY_PREFIX');
    if ($prefix === false || $prefix === '') {
      $prefix = IDE_ROOT . '/.usr';
    }
    $this->prefix = rtrim(str_replace('\\', '/', (string) $prefix), '/');
    $this->binDir = $this->prefix . '/bin';

    $appLib = getenv('QUIRKY_APPLIB');
    if ($appLib === false || $appLib === '') {
      $appLib = dirname($this->prefix) . '/applibs';
    }
    $this->appLibDir = rtrim(str_replace('\\', '/', (string) $appLib), '/');
  }

  /* ═══════════════════════════════════════════════════════════
      LOGGER
      ═══════════════════════════════════════════════════════════ */

  private function log(): IdeWorkshopLogger
  {
    if ($this->logger === null) {
      require_once __DIR__ . '/WorkshopLogger.php';
      $this->logger = new IdeWorkshopLogger();
    }
    return $this->logger;
  }

  /* ═══════════════════════════════════════════════════════════
      ELF BINARY PATCHER — fixes exit 127
      ═══════════════════════════════════════════════════════════ */

  public function getElfInterpreter(string $binaryPath): ?string
  {
    if (!is_file($binaryPath)) return null;

    $fp = @fopen($binaryPath, 'rb');
    if (!$fp) return null;

    $magic = fread($fp, 4);
    if ($magic !== "\x7fELF") {
      fclose($fp);
      return null;
    }

    fseek($fp, 4);
    $class = ord(fread($fp, 1));
    if ($class !== 2) {
      fclose($fp);
      return null;
    }

    fseek($fp, 32);
    $raw = fread($fp, 8);
    $phoff = unpack('P', $raw)[1];

    fseek($fp, 54);
    $phentsize = unpack('v', fread($fp, 2))[1];

    fseek($fp, 56);
    $phnum = unpack('v', fread($fp, 2))[1];

    for ($i = 0; $i < $phnum; $i++) {
      $hdrOffset = $phoff + ($i * $phentsize);
      fseek($fp, $hdrOffset);
      $p_type = unpack('V', fread($fp, 4))[1];

      if ($p_type === 3) {
        fseek($fp, $hdrOffset + 8);
        $p_offset = unpack('P', fread($fp, 8))[1];

        fseek($fp, $hdrOffset + 32);
        $p_filesz = unpack('P', fread($fp, 8))[1];

        fseek($fp, $p_offset);
        $interp = fread($fp, $p_filesz);
        fclose($fp);
        return rtrim($interp, "\0");
      }
    }

    fclose($fp);
    return null;
  }

  /**
   * Detect the real system linker path at runtime by reading the
   * ELF header of a known working system binary.
   */
  private function getSystemLinkerPath(): string
  {
    $systemBins = ['/system/bin/sh', '/system/bin/ls', '/system/bin/cat', '/system/bin/linker64'];
    foreach ($systemBins as $bin) {
      if (@is_file($bin) && @is_readable($bin)) {
        $interp = $this->getElfInterpreter($bin);
        if ($interp !== null && strpos($interp, 'linker') !== false) {
          return $interp;
        }
      }
    }
    return '/system/bin/linker64';
  }

  public function patchElfInterpreter(string $binaryPath): bool
  {
    if (!is_file($binaryPath)) {
      $this->log()->warn('APACHE', "ELF patch: file not found: $binaryPath");
      return false;
    }

    $fp = @fopen($binaryPath, 'r+b');
    if (!$fp) {
      $this->log()->warn('APACHE', "ELF patch: cannot open for writing: $binaryPath");
      return false;
    }

    $magic = fread($fp, 4);
    if ($magic !== "\x7fELF") {
      fclose($fp);
      return false;
    }

    fseek($fp, 4);
    $class = ord(fread($fp, 1));
    if ($class !== 2) {
      fclose($fp);
      return false;
    }

    fseek($fp, 32);
    $phoff = unpack('P', fread($fp, 8))[1];
    fseek($fp, 54);
    $phentsize = unpack('v', fread($fp, 2))[1];
    fseek($fp, 56);
    $phnum = unpack('v', fread($fp, 2))[1];

    for ($i = 0; $i < $phnum; $i++) {
      $hdrOffset = $phoff + ($i * $phentsize);
      fseek($fp, $hdrOffset);
      $p_type = unpack('V', fread($fp, 4))[1];

      if ($p_type === 3) {
        fseek($fp, $hdrOffset + 8);
        $p_offset = unpack('P', fread($fp, 8))[1];
        fseek($fp, $hdrOffset + 32);
        $p_filesz = unpack('P', fread($fp, 8))[1];

        fseek($fp, $p_offset);
        $interp = fread($fp, $p_filesz);
        $currentPath = rtrim($interp, "\0");

        $systemLinker = $this->getSystemLinkerPath();

        if ($currentPath === $systemLinker) {
          fclose($fp);
          return true;
        }

        if (
          strpos($currentPath, '/data/data/com.termux/') === false
          && strpos($currentPath, '/data/user/0/com.termux/') === false
        ) {
          fclose($fp);
          return false;
        }

        $newPath = $systemLinker;

        if (strlen($newPath) > $p_filesz) {
          $this->log()->error(
            'APACHE',
            "ELF patch: new interpreter path ($newPath) is longer than the slot ($p_filesz bytes)."
          );
          fclose($fp);
          return false;
        }

        $padded = str_pad($newPath, $p_filesz, "\0");
        fseek($fp, $p_offset);
        fwrite($fp, $padded);
        fclose($fp);

        $this->log()->info('APACHE', "ELF patch: $binaryPath interpreter changed to '$newPath'");
        return true;
      }
    }

    fclose($fp);
    return false;
  }

  public function patchAllBinaries(): int
  {
    $binDir = $this->prefix . '/bin';
    if (!is_dir($binDir)) return 0;

    $patched = 0;
    $entries = @scandir($binDir);
    if ($entries === false) return 0;

    foreach ($entries as $entry) {
      if ($entry === '.' || $entry === '..') continue;
      $path = $binDir . '/' . $entry;
      if (!is_file($path)) continue;
      if (is_link($path)) continue;

      if ($this->patchElfInterpreter($path)) {
        $patched++;
      }
    }

    if ($patched > 0) {
      $this->log()->info('APACHE', "ELF patch: patched $patched binaries in $binDir");
    }
    return $patched;
  }

  /* ═══════════════════════════════════════════════════════════
      DIAGNOSTICS
      ═══════════════════════════════════════════════════════════ */

  public function diagnoseApache(): array
  {
    $checks = [];
    $httpdPath = $this->findHttpdBinary();

    $checks[] = [
      'check'  => 'httpd binary exists',
      'pass'   => $httpdPath !== null,
      'detail' => $httpdPath ?? 'NOT FOUND in ' . $this->prefix . '/bin/',
    ];

    if ($httpdPath === null) {
      return ['checks' => $checks, 'verdict' => 'Apache binary not found. Install Apache first.'];
    }

    $checks[] = [
      'check'  => 'httpd is executable',
      'pass'   => is_executable($httpdPath),
      'detail' => is_executable($httpdPath) ? 'yes' : 'no (chmod needed)',
    ];

    $interp = $this->getElfInterpreter($httpdPath);
    $systemLinker = $this->getSystemLinkerPath();
    $interpOk = ($interp === $systemLinker);
    $checks[] = [
      'check'  => 'ELF interpreter',
      'pass'   => $interpOk,
      'detail' => $interp ?? 'not an ELF binary (might be a script)',
    ];

    if ($interp !== null && !$interpOk) {
      $patched = $this->patchElfInterpreter($httpdPath);
      $checks[] = [
        'check'  => 'ELF interpreter auto-patch',
        'pass'   => $patched,
        'detail' => $patched ? 'patched to ' . $systemLinker : 'patch failed',
      ];
    }

    $confPath = $this->stateDir . '/apache/httpd.conf';
    $checks[] = [
      'check'  => 'httpd.conf exists',
      'pass'   => is_file($confPath),
      'detail' => is_file($confPath) ? $confPath : 'missing — will be generated on start',
    ];

    $moduleDir = $this->findModuleDir();
    $hasModules = is_dir($moduleDir) && !empty(glob($moduleDir . '/mod_*.so'));
    $checks[] = [
      'check'  => 'Apache modules directory',
      'pass'   => $hasModules,
      'detail' => $moduleDir . ($hasModules ? ' (modules found)' : ' (NO .so files!)'),
    ];

    $keyLibs = ['libapr-1.so', 'libaprutil-1.so', 'libpcre2-8.so'];
    foreach ($keyLibs as $lib) {
      $found = is_file($this->prefix . '/lib/' . $lib) || is_file($this->appLibDir . '/' . $lib);
      $checks[] = [
        'check'  => "Library: $lib",
        'pass'   => $found,
        'detail' => $found ? 'found' : 'MISSING',
      ];
    }

    $port = $this->getApachePort();
    $portFree = !$this->isPortInUse($port);
    $checks[] = [
      'check'  => "Port $port available",
      'pass'   => $portFree,
      'detail' => $portFree ? 'free' : 'IN USE',
    ];

    $mimeTypes = $this->prefix . '/etc/apache2/mime.types';
    $checks[] = [
      'check'  => 'mime.types exists',
      'pass'   => is_file($mimeTypes),
      'detail' => is_file($mimeTypes) ? $mimeTypes : 'MISSING — reinstall apache2',
    ];

    $logsDir = $this->stateDir . '/apache/logs';
    $logsOk  = is_dir($logsDir) && is_writable($logsDir);
    $checks[] = [
      'check'  => 'Log directory writable',
      'pass'   => $logsOk,
      'detail' => $logsDir . ($logsOk ? ' (writable)' : ' (NOT writable)'),
    ];

    $allPass = true;
    foreach ($checks as $c) {
      if (!$c['pass']) {
        $allPass = false;
        break;
      }
    }

    return [
      'checks'  => $checks,
      'verdict' => $allPass ? 'All checks passed. Apache should start.' : 'Some checks failed. See details above.',
    ];
  }

  /* ═══════════════════════════════════════════════════════════
      EXTENSION MANAGEMENT
      ═══════════════════════════════════════════════════════════ */

  public function isEnabled(): bool
  {
    // ★ NON-NEGOTIABLE DEVICE TIER GUARD:
    // Workshop can be hard-disabled by the device tier policy.
    require_once dirname(__DIR__) . '/mobile-limits.php';

    return quirkyMobileCanUseWorkshop($this->config);
  }

  public function getExtensions(): array
  {
    $state  = $this->loadState();
    $result = [];
    foreach (self::EXTENSIONS as $id => $ext) {
      $extState = $state[$id] ?? ['installed' => false, 'enabled' => false, 'version' => null, 'installed_at' => null];
      $result[] = [
        'id' => $id,
        'name' => $ext['name'],
        'description' => $ext['description'],
        'icon' => $ext['icon'],
        'size' => $ext['size'],
        'provides' => $ext['provides'],
        'port' => $ext['port'] ?? null,
        'installed' => (bool) $extState['installed'],
        'enabled' => (bool) $extState['enabled'],
        'version' => $extState['version'],
        'installed_at' => $extState['installed_at'],
        'running' => ($id === 'apache') ? $this->isApacheRunning() : null,
        'activePort' => ($id === 'apache') ? $this->getApachePort() : null,
      ];
    }
    return $result;
  }

  public function install(string $id): array
  {
    if (!isset(self::EXTENSIONS[$id])) $this->security->fail('Unknown extension: ' . $id);
    $ext = self::EXTENSIONS[$id];
    $pkg = $this->makePkg();
    $results = [];
    $this->log()->info('WORKSHOP', "Installing extension: $id");

    foreach ($ext['packages'] as $packageName) {
      $result = $pkg->handleCommand('install ' . $packageName);
      $results[] = ['package' => $packageName, 'output' => $result['output'] ?? '', 'error' => $result['error'] ?? '', 'exitCode' => $result['exitCode'] ?? 0];
      if (($result['exitCode'] ?? 1) !== 0) {
        $this->log()->error('PKG', "Failed to install $packageName: " . ($result['error'] ?? 'unknown'));
      } else {
        $this->log()->success('PKG', "Installed $packageName");
      }
    }

    if ($id === 'apache') {
      $this->generateApacheConfig();
      $this->generatePhpIni();
      $patched = $this->patchAllBinaries();
      $this->log()->info('APACHE', "Post-install: patched $patched ELF binaries, php.ini generated");
    }

    $state = $this->loadState();
    $state[$id] = ['installed' => true, 'enabled' => false, 'version' => date('Y-m-d H:i'), 'installed_at' => time()];
    $this->saveState($state);
    $this->log()->success('WORKSHOP', "Extension $id installed successfully");
    return ['installed' => true, 'extension' => $id, 'results' => $results];
  }

  public function uninstall(string $id): array
  {
    if (!isset(self::EXTENSIONS[$id])) $this->security->fail('Unknown extension: ' . $id);
    $this->log()->info('WORKSHOP', "Uninstalling extension: $id");
    if ($id === 'apache' && $this->isApacheRunning()) $this->stopApache();

    $ext = self::EXTENSIONS[$id];
    $pkg = $this->makePkg();
    foreach ($ext['packages'] as $packageName) $pkg->handleCommand('remove ' . $packageName);

    if ($id === 'apache') {
      @unlink($this->stateDir . '/apache/httpd.conf');
      @unlink($this->stateDir . '/apache/httpd.pid');
    }
    $state = $this->loadState();
    unset($state[$id]);
    $this->saveState($state);
    $this->log()->success('WORKSHOP', "Extension $id uninstalled");
    return ['uninstalled' => true, 'extension' => $id];
  }

  public function toggle(string $id): array
  {
    $state = $this->loadState();
    if (!isset($state[$id]) || empty($state[$id]['installed'])) $this->security->fail('Extension not installed: ' . $id);
    $state[$id]['enabled'] = !$state[$id]['enabled'];
    $this->saveState($state);
    $this->log()->info('WORKSHOP', "Extension $id " . ($state[$id]['enabled'] ? 'enabled' : 'disabled'));
    return ['enabled' => (bool) $state[$id]['enabled'], 'extension' => $id];
  }

  public function getStatus(): array
  {
    return ['enabled' => $this->isEnabled(), 'extensions' => $this->getExtensions()];
  }

  /* ═══════════════════════════════════════════════════════════
      COMMUNITY PACKAGES (dynamic Workshop · Manifest v1)
      ═══════════════════════════════════════════════════════════ */

  /**
   * Installed community-package summaries for the workshop-list payload.
   * Additive: never throws into the apache flows — a broken package store
   * degrades to an empty list.
   *
   * The IdeWorkshopPackage class lives in services/WorkshopPackage.php and is
   * require_once'd HERE (not via api.php's classmap, which is not ours to
   * edit); all new dynamic-Workshop PHP code lives in that one file.
   */
  public function getCommunityPackages(): array
  {
    try {
      require_once __DIR__ . '/WorkshopPackage.php';
      $svc = new IdeWorkshopPackage($this->config, $this->security);
      return $svc->catalog();
    } catch (\Throwable $e) {
      $this->log()->error('PACKAGE', 'Community catalog unavailable: ' . $e->getMessage());
      return [];
    }
  }

  public function getExtensionState(string $id): ?array
  {
    return $this->loadState()[$id] ?? null;
  }
  public function isExtensionActive(string $id): bool
  {
    $state = $this->loadState();
    return isset($state[$id]) && !empty($state[$id]['installed']) && !empty($state[$id]['enabled']);
  }

  /* ═══════════════════════════════════════════════════════════
      APACHE MANAGEMENT
      ═══════════════════════════════════════════════════════════ */

  public function installApacheStream(): void
  {
    @set_time_limit(0);
    while (ob_get_level() > 0) ob_end_flush();
    @ob_implicit_flush(true);
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache, no-transform');
    header('X-Accelerate-Buffering: no');
    header('Connection: keep-alive');

    $emit = function (string $event, $data): void {
      echo 'event: ' . $event . "\n" . 'data: ' . json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n";
      @ob_flush();
      @flush();
    };

    $this->log()->info('APACHE', 'Starting Apache installation via SSE stream');
    $emit('status', ['message' => 'Starting Apache installation…']);
    $pkg = $this->makePkg();
    $pkg->setEmitter(function (string $event, string $text) use ($emit): void {
      $emit($event === 'prog' ? 'progress' : $event, ['text' => $text]);
    });

    $emit('status', ['message' => 'Installing apache2…']);
    $result1 = $pkg->handleCommand('install apache2');
    if (($result1['exitCode'] ?? 1) !== 0) {
      $emit('error', ['message' => 'Failed to install apache2']);
      $emit('done', ['success' => false]);
      return;
    }
    $emit('status', ['message' => 'Installing php-apache…']);
    $result2 = $pkg->handleCommand('install php-apache');
    if (($result2['exitCode'] ?? 1) !== 0) {
      $emit('error', ['message' => 'Failed to install php-apache']);
      $emit('done', ['success' => false]);
      return;
    }
    $pkg->setEmitter(null);

    $emit('status', ['message' => 'Patching binaries for this device…']);
    $this->patchAllBinaries();
    $emit('status', ['message' => 'Generating Apache configuration…']);
    $this->generateApacheConfig();

    $state = $this->loadState();
    $state['apache'] = ['installed' => true, 'enabled' => true, 'version' => date('Y-m-d H:i'), 'installed_at' => time()];
    $this->saveState($state);
    $emit('status', ['message' => '✓ Apache installed successfully!']);
    $emit('done', ['success' => true, 'port' => $this->getApachePort()]);
  }

  private function findHttpdBinary(): ?string
  {
    foreach ([$this->prefix . '/bin/httpd', $this->prefix . '/bin/apache2', $this->prefix . '/bin/apachectl'] as $path) {
      if (is_file($path)) {
        if (!is_executable($path)) @chmod($path, 0755);
        return $path;
      }
    }
    return null;
  }

  private function buildApacheEnv(): array
  {
    $baseEnv = (array) @getenv();
    $libPaths = [];
    if (is_dir($this->prefix . '/lib')) {
      $libPaths[] = $this->prefix . '/lib';
    }
    if (is_dir($this->appLibDir)) {
      $libPaths[] = $this->appLibDir;
    }
    if (is_dir($this->prefix . '/lib/apache2')) {
      $libPaths[] = $this->prefix . '/lib/apache2';
    }
    $ldPath = implode(':', $libPaths);
    if (!empty($baseEnv['LD_LIBRARY_PATH'])) {
      $ldPath .= ':' . $baseEnv['LD_LIBRARY_PATH'];
    }
    return array_merge($baseEnv, [
      'PREFIX'          => $this->prefix,
      'LD_LIBRARY_PATH' => $ldPath,
      'TMPDIR'          => $this->prefix . '/tmp',
      'HOME'            => $this->prefix . '/tmp',
      'PHPRC'           => $this->stateDir . '/apache',
      'PATH'            => $this->prefix . '/bin:' . ($baseEnv['PATH'] ?? '/system/bin'),
    ]);
  }

  /**
   * Generates a custom php.ini to override Termux's hardcoded temp paths.
   * Termux's PHP binary has /data/data/com.termux/files/usr/tmp hardcoded
   * for OPcache lock files, which causes "Permission denied" in our app sandbox.
   *
   * FIX: We DISABLE opcache entirely (not needed for a local dev server)
   * and redirect all temp paths to our own writable directory.
   */
  private function generatePhpIni(): void
  {
    $confDir = $this->stateDir . '/apache';
    if (!is_dir($confDir)) {
      @mkdir($confDir, 0777, true);
    }
    $logDir = $confDir . '/logs';
    if (!is_dir($logDir)) {
      @mkdir($logDir, 0777, true);
    }
    $tmpDir = $this->prefix . '/tmp';
    if (!is_dir($tmpDir)) {
      @mkdir($tmpDir, 0777, true);
    }

    $phpIniPath = $confDir . '/php.ini';
    $phpIniContent = <<<INI
      [PHP]
      ; Redirect ALL temp paths away from Termux's hardcoded /data/data/com.termux/...
      sys_temp_dir = "{$tmpDir}"
      upload_tmp_dir = "{$tmpDir}"
      session.save_path = "{$tmpDir}"
      error_log = "{$logDir}/php_error.log"

      [opcache]
      ; DISABLE opcache entirely — it's not needed for a local dev server
      ; and its lock file path is hardcoded to Termux's directory in the binary.
      opcache.enable = 0
      opcache.enable_cli = 0
      opcache.lockfile_path = "{$tmpDir}"
      opcache.file_cache = ""
      INI;
    @file_put_contents($phpIniPath, $phpIniContent, LOCK_EX);
    $this->log()->info('APACHE', "php.ini generated at: $phpIniPath (opcache disabled, tmpDir=$tmpDir)");
  }

  public function startApache(): array
  {
    if ($this->isApacheRunning()) {
      return ['started' => false, 'reason' => 'Apache is already running.', 'port' => $this->getApachePort()];
    }

    $this->log()->info('APACHE', 'Attempting to start Apache…');

    $httpdPath = $this->findHttpdBinary();
    if ($httpdPath === null) {
      $errMsg = 'Apache binary not found. Install Apache first.';
      $this->log()->error('APACHE', $errMsg);
      return ['started' => false, 'reason' => $errMsg];
    }

    $interp = $this->getElfInterpreter($httpdPath);
    $systemLinker = $this->getSystemLinkerPath();
    if ($interp !== null && $interp !== $systemLinker) {
      $this->log()->info('APACHE', "ELF interpreter is '$interp' — patching…");
      if (!$this->patchElfInterpreter($httpdPath)) {
        $this->log()->error('APACHE', 'ELF patch failed.');
      }
    }

    $confPath = $this->generateApacheConfig();
    $this->generatePhpIni();
    $port   = $this->getApachePort();
    $logDir = $this->stateDir . '/apache/logs';
    if (!is_dir($logDir)) @mkdir($logDir, 0777, true);
    @file_put_contents($logDir . '/error.log', '');

    /* ── Config test ── */
    $test = $this->runHttpdCommand(
      escapeshellarg($httpdPath) . ' -f ' . escapeshellarg($confPath) . ' -t',
      15
    );
    if ($test['exitCode'] !== 0) {
      $reason = "Apache config test failed (exit {$test['exitCode']}):\n"
        . trim($test['output']) . $this->diagFailureNotes();
      $this->log()->error('APACHE', $reason);
      return ['started' => false, 'reason' => $reason];
    }

    /* ── Start Apache detached via exec() + background ──────────────
       Using exec() with "&" lets the shell fork and exit immediately.
       Apache becomes an independent orphan process owned by init,
       so PHP never blocks on proc_close()/waitpid().              ── */
    // ★ FIX: Use setsid to put Apache in its OWN session/process group.
    // This prevents signals sent to Apache from propagating to our PHP server.
    // Without setsid, Apache shares the PHP server's process group on Android,
    // and "httpd -k stop" can accidentally kill the PHP server too.
    // Try setsid first; if not available, fall back to nohup
    $cmd = $this->shellEnvExports() . ' '
      . '(setsid ' . escapeshellarg($httpdPath)
      . ' -f ' . escapeshellarg($confPath)
      . ' -D FOREGROUND 2>/dev/null || '
      . 'nohup ' . escapeshellarg($httpdPath)
      . ' -f ' . escapeshellarg($confPath)
      . ' -D FOREGROUND 2>/dev/null)'
      . ' > /dev/null 2>&1 &';
    @exec('/bin/sh -c ' . escapeshellarg($cmd));

    /* ── Wait up to 8 seconds for the port to open ── */
    $started  = false;
    $deadline = microtime(true) + 8.0;
    while (microtime(true) < $deadline) {
      if ($this->isPortInUse($port)) {
        $started = true;
        break;
      }
      usleep(150000);
    }

    /* Extra grace: sometimes the socket opens a beat after the child */
    if (!$started) {
      $grace = microtime(true) + 3.0;
      while (microtime(true) < $grace) {
        if ($this->isPortInUse($port)) {
          $started = true;
          break;
        }
        usleep(250000);
      }
    }

    if (!$started) {
      $errorLogTail = $this->tailApacheErrorLog();
      $reason = 'Apache failed to start (port ' . $port . ' never opened).';
      if ($errorLogTail !== '') {
        $reason .= "\nApache error.log says:\n" . $errorLogTail;
      }
      $reason .= $this->diagFailureNotes();
      $this->log()->error('APACHE', $reason);
      return ['started' => false, 'reason' => $reason];
    }

    $pid = $this->getApachePid();
    if ($pid > 0) @file_put_contents($this->stateDir . '/apache/httpd.pid', (string) $pid);
    @file_put_contents($this->stateDir . '/apache/port', (string) $port);

    $this->log()->success('APACHE', "Apache started on port $port (PID: $pid)");
    return ['started' => true, 'port' => $port, 'pid' => $pid];
  }

  public function stopApache(): array
  {
    if (!$this->isApacheRunning()) {
      // Clean up stale PID file if it exists
      @unlink($this->stateDir . '/apache/httpd.pid');
      return ['stopped' => false, 'reason' => 'Apache is not running.'];
    }

    $httpdPath = $this->findHttpdBinary();
    $confPath  = $this->stateDir . '/apache/httpd.conf';

    // Step 1: Try graceful stop via httpd -k stop
    if ($httpdPath !== null && is_file($confPath)) {
      $this->runHttpdCommand(
        escapeshellarg($httpdPath) . ' -f ' . escapeshellarg($confPath) . ' -k stop',
        10
      );
    }

    // Wait up to 3 seconds for graceful shutdown
    $deadline = microtime(true) + 3.0;
    while (microtime(true) < $deadline) {
      if (!$this->isPortInUse($this->getApachePort())) {
        // Apache stopped gracefully
        @unlink($this->stateDir . '/apache/httpd.pid');
        $this->log()->success('APACHE', 'Apache stopped gracefully');
        return ['stopped' => true];
      }
      usleep(200000);
    }

    // Step 2: Graceful stop didn't work — find the PID and send SIGTERM
    $pid = $this->getApachePidSafe();
    if ($pid > 0) {
      // SAFETY: Verify this PID is actually httpd, NOT our PHP server
      if (!$this->isSafeToKill($pid)) {
        $this->log()->error('APACHE', "Refusing to kill PID $pid — it is not httpd");
        @unlink($this->stateDir . '/apache/httpd.pid');
        return ['stopped' => false, 'reason' => 'Could not safely identify Apache process.'];
      }

      // Send SIGTERM first (graceful)
      @exec('kill -15 ' . $pid . ' 2>/dev/null');

      // Wait up to 2 more seconds
      $deadline2 = microtime(true) + 2.0;
      while (microtime(true) < $deadline2) {
        if (!$this->isPortInUse($this->getApachePort())) {
          @unlink($this->stateDir . '/apache/httpd.pid');
          $this->log()->success('APACHE', 'Apache stopped via SIGTERM');
          return ['stopped' => true];
        }
        usleep(200000);
      }

      // Step 3: Last resort — SIGKILL, but ONLY after verification
      if ($this->isSafeToKill($pid)) {
        @exec('kill -9 ' . $pid . ' 2>/dev/null');
        usleep(300000);
      }
    }

    @unlink($this->stateDir . '/apache/httpd.pid');
    $this->log()->success('APACHE', 'Apache stopped');
    return ['stopped' => true];
  }

  /**
   * Safely get the Apache PID.
   * ONLY returns a PID if we can verify it belongs to httpd.
   * Never falls back to broad pgrep patterns that could match PHP.
   */
  private function getApachePidSafe(): int
  {
    // Method 1: Read the PID file (most reliable)
    $pidFile = $this->stateDir . '/apache/httpd.pid';
    if (is_file($pidFile)) {
      $pid = (int) trim((string) @file_get_contents($pidFile));
      if ($pid > 0 && $this->isSafeToKill($pid)) {
        return $pid;
      }
    }

    // Method 2: Use pgrep with a VERY specific pattern
    // Match ONLY the exact httpd binary path, not a broad "httpd.*-f"
    $httpdPath = $this->findHttpdBinary();
    if ($httpdPath !== null && PHP_OS_FAMILY !== 'Windows') {
      // Use the exact binary name in the pattern
      $httpdName = basename($httpdPath);
      $output = @shell_exec('pgrep -x "' . $httpdName . '" 2>/dev/null');
      if (is_string($output) && trim($output) !== '') {
        $pids = array_filter(array_map('intval', explode("\n", trim($output))));
        foreach ($pids as $pid) {
          if ($pid > 0 && $this->isSafeToKill($pid)) {
            return $pid;
          }
        }
      }
    }

    return 0;
  }

  /**
   * CRITICAL SAFETY CHECK: Verify a PID is safe to kill.
   * Returns FALSE if the PID belongs to:
   *   - Our PHP server (port 8080 or 8081)
   *   - The Android app process itself
   *   - Any process we can't identify
   */
  private function isSafeToKill(int $pid): bool
  {
    if ($pid <= 1) return false;

    // Never kill our own PHP process
    $myPid = getmypid();
    if ($pid === $myPid) return false;

    // On Linux/Android, check /proc/<pid>/cmdline to verify it's httpd
    if (PHP_OS_FAMILY !== 'Windows') {
      $cmdlineFile = '/proc/' . $pid . '/cmdline';
      if (is_file($cmdlineFile)) {
        $cmdline = @file_get_contents($cmdlineFile);
        if ($cmdline !== false) {
          // cmdline uses null bytes as separators
          $cmd = str_replace("\0", ' ', $cmdline);

          // BLOCK: if it contains "php" or "libphp", it's our server
          if (stripos($cmd, 'php') !== false) {
            return false;
          }
          if (stripos($cmd, 'libphp') !== false) {
            return false;
          }
          // BLOCK: if it's the Android app process
          if (stripos($cmd, 'com.quirky.ide') !== false) {
            return false;
          }
          // ALLOW: only if it looks like httpd/apache
          if (stripos($cmd, 'httpd') !== false || stripos($cmd, 'apache') !== false) {
            return true;
          }
          // UNKNOWN process — don't kill it
          return false;
        }
      }
      // Can't read cmdline — process might already be dead
      return false;
    }

    return true;
  }

  public function restartApache(): array
  {
    $this->stopApache();
    usleep(500000);
    return $this->startApache();
  }

  public function getApacheStatus(): array
  {
    return [
      'installed' => $this->isApacheInstalled(),
      'running' => $this->isApacheRunning(),
      'port' => $this->isApacheRunning() ? $this->getApachePort() : null,
      'pid' => $this->isApacheRunning() ? $this->getApachePid() : null,
      'configPath' => $this->stateDir . '/apache/httpd.conf',
      'binary' => $this->findHttpdBinary(),
    ];
  }

  public function isApacheInstalled(): bool
  {
    return $this->makePkg()->isPackageInstalled('apache2');
  }

  public function isApacheRunning(): bool
  {
    // Primary check: is the port in use?
    $port = $this->getApachePort();
    if ($port > 0 && $this->isPortInUse($port)) {
      return true;
    }

    // Secondary check: does the PID file exist and point to a live httpd?
    $pid = $this->getApachePidSafe();
    if ($pid > 0) {
      if (PHP_OS_FAMILY === 'Windows') {
        $output = @shell_exec('tasklist /FI "PID eq ' . $pid . '" 2>nul');
        return is_string($output) && strpos($output, (string) $pid) !== false;
      } else {
        $kill = 'posix_kill';
        return @$kill($pid, 0) === true || @file_exists('/proc/' . $pid);
      }
    }

    return false;
  }

  public function getApachePort(): int
  {
    $portFile = $this->stateDir . '/apache/port';
    if (is_file($portFile)) {
      $port = (int) trim((string) @file_get_contents($portFile));
      if ($port > 0) return $port;
    }
    return (int) ($this->config['workshop']['apache']['port'] ?? 8082);
  }

  /* ═══════════════════════════════════════════════════════════
      APACHE CONFIG GENERATION
      ═══════════════════════════════════════════════════════════ */

  public function generateApacheConfig(): string
  {
    $confDir  = $this->stateDir . '/apache';
    $confPath = $confDir . '/httpd.conf';
    $logDir   = $confDir . '/logs';
    $port     = $this->findAvailablePort();
    if (!is_dir($logDir)) @mkdir($logDir, 0777, true);

    $workspace = str_replace('\\', '/', IDE_WORKSPACE);
    $prefix    = $this->prefix;
    $moduleDir = $this->findModuleDir();
    $phpModule = $this->findPhpModule($moduleDir);
    $mpmLine   = $this->findMpmModuleLine($moduleDir, $phpModule !== null);

    $config = <<<CONF
# Quirky IDE — Apache Configuration (auto-generated)
ServerRoot "{$prefix}"
Listen {$port}
{$mpmLine}
LoadModule authz_core_module {$moduleDir}/mod_authz_core.so
LoadModule authz_host_module {$moduleDir}/mod_authz_host.so
LoadModule dir_module {$moduleDir}/mod_dir.so
LoadModule mime_module {$moduleDir}/mod_mime.so
LoadModule log_config_module {$moduleDir}/mod_log_config.so
LoadModule unixd_module {$moduleDir}/mod_unixd.so
LoadModule rewrite_module {$moduleDir}/mod_rewrite.so
LoadModule alias_module {$moduleDir}/mod_alias.so
CONF;

    if ($phpModule !== null) {
      $config .= "\nLoadModule php_module {$phpModule}\n<FilesMatch \"\\.php$\">\n    SetHandler application/x-httpd-php\n</FilesMatch>\n";
    }

    $config .= <<<CONF
ServerAdmin admin@quirky.local
ServerName 127.0.0.1:{$port}
DocumentRoot "{$workspace}"
DefaultRuntimeDir "{$confDir}"
<Directory "{$workspace}">
    Options Indexes FollowSymLinks
    AllowOverride All
    Require all granted
</Directory>
<IfModule dir_module>
    DirectoryIndex index.php index.html index.htm
</IfModule>
ErrorLog "{$logDir}/error.log"
LogLevel warn
<IfModule log_config_module>
    LogFormat "%h %l %u %t \"%r\" %>s %b" common
    CustomLog "{$logDir}/access.log" common
</IfModule>
PidFile "{$confDir}/httpd.pid"
<IfModule mime_module>
    TypesConfig "{$prefix}/etc/apache2/mime.types"
</IfModule>
<FilesMatch "^\.">
    Require all denied
</FilesMatch>
CONF;

    @file_put_contents($confPath, $config, LOCK_EX);
    @file_put_contents($confDir . '/port', (string) $port);
    $this->log()->info('APACHE', "Config generated: $confPath (port $port)");
    return $confPath;
  }

  /* ═══════════════════════════════════════════════════════════
      PRIVATE HELPERS
      ═══════════════════════════════════════════════════════════ */

  private function findModuleDir(): string
  {
    foreach ([$this->prefix . '/libexec/apache2', $this->prefix . '/lib/apache2', $this->prefix . '/lib/httpd/modules'] as $dir) {
      if (is_dir($dir) && !empty(glob($dir . '/mod_*.so'))) return $dir;
    }
    return $this->prefix . '/libexec/apache2';
  }

  private function findMpmModuleLine(string $moduleDir, bool $needsPhp): string
  {
    $mpms = ['prefork' => 'mod_mpm_prefork.so', 'event' => 'mod_mpm_event.so', 'worker' => 'mod_mpm_worker.so'];
    $order = $needsPhp ? ['prefork'] : ['event', 'worker', 'prefork'];
    foreach ($order as $name) {
      if (is_file($moduleDir . '/' . $mpms[$name])) return "LoadModule mpm_{$name}_module {$moduleDir}/{$mpms[$name]}";
    }
    return '# ERROR: no MPM module found in ' . $moduleDir;
  }

  private function findPhpModule(string $moduleDir): ?string
  {
    foreach ([$moduleDir . '/libphp.so', $moduleDir . '/mod_php.so', $moduleDir . '/php_module.so'] as $path) {
      if (is_file($path)) return $path;
    }
    $found = glob($moduleDir . '/*php*');
    return !empty($found) ? $found[0] : null;
  }

  private function shellEnvExports(): string
  {
    $tlsDir = $this->prefix . '/etc/tls';
    $confDir = $this->stateDir . '/apache';
    // NOTE: We deliberately exclude $this->appLibDir from LD_LIBRARY_PATH here.
    // It contains an ABI-incompatible OpenSSL build that causes dlopen crashes with Apache.
    return 'export PREFIX="' . $this->prefix . '"; '
      . 'export PATH="' . $this->binDir . ':$PATH"; '
      . 'export LD_LIBRARY_PATH="' . $this->prefix . '/lib:' . $this->prefix . '/libexec/apache2${LD_LIBRARY_PATH:+:$LD_LIBRARY_PATH}"; '
      . 'export TMPDIR="' . $this->prefix . '/tmp"; '
      . 'export PHPRC="' . $confDir . '"; '
      . 'export OPENSSL_CONF="' . $tlsDir . '/openssl.cnf"; '
      . 'export SSL_CERT_FILE="' . $tlsDir . '/cacert.pem"; '
      . 'export SSL_CERT_DIR="' . $tlsDir . '";';
  }

  private function runHttpdCommand(string $shellCmd, int $timeoutSec = 15): array
  {
    $full = $this->shellEnvExports() . ' ' . $shellCmd . ' 2>&1';
    $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $proc = @proc_open(['/bin/sh', '-c', $full], $descriptors, $pipes, $this->prefix);
    if (!is_resource($proc)) return ['exitCode' => -1, 'output' => 'proc_open failed'];

    fclose($pipes[0]);
    stream_set_blocking($pipes[1], false);
    $out = '';
    $exit = -1;
    $deadline = microtime(true) + max(1, $timeoutSec);
    while (microtime(true) < $deadline) {
      $status = proc_get_status($proc);
      $out .= (string) stream_get_contents($pipes[1]);
      if (!$status['running']) {
        $exit = (int) $status['exitcode'];
        break;
      }
      usleep(50000);
    }
    $out .= (string) stream_get_contents($pipes[1]);
    if ($exit === -1) {
      proc_terminate($proc, 9);
      usleep(50000);
    }
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($proc);
    return ['exitCode' => $exit, 'output' => substr($out, 0, 20000)];
  }

  private function tailApacheErrorLog(int $maxLines = 30): string
  {
    $file = $this->stateDir . '/apache/logs/error.log';
    if (!is_file($file)) return '';
    $content = trim((string) @file_get_contents($file));
    if ($content === '') return '';
    $lines = explode("\n", $content);
    return substr(implode("\n", array_slice($lines, -$maxLines)), 0, 8000);
  }

  private function diagFailureNotes(): string
  {
    try {
      $diag = $this->diagnoseApache();
      $failed = [];
      foreach (($diag['checks'] ?? []) as $c) {
        if (empty($c['pass'])) {
          $failed[] = ' • ' . $c['check'] . ': ' . $c['detail'];
          $this->log()->error('APACHE', 'Diagnostic FAIL — ' . $c['check'] . ': ' . $c['detail']);
        }
      }
      return empty($failed) ? '' : "\nFailed diagnostics:\n" . implode("\n", $failed);
    } catch (\Throwable $e) {
      return '';
    }
  }

  private function loadState(): array
  {
    if (!is_file($this->stateFile)) return [];
    $data = json_decode((string) @file_get_contents($this->stateFile), true);
    return is_array($data) ? $data : [];
  }

  private function saveState(array $state): void
  {
    @file_put_contents($this->stateFile, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
  }

  private function makePkg(): IdePkg
  {
    if ($this->pkg === null) {
      require_once __DIR__ . '/Pkg.php';
      $this->pkg = new IdePkg($this->config, $this->security, $this->prefix);
    }
    return $this->pkg;
  }

  private function findAvailablePort(): int
  {
    $wsConfig  = $this->config['workshop']['apache'] ?? [];
    $primary   = (int) ($wsConfig['port'] ?? 8082);
    $fallbacks = $wsConfig['fallback_ports'] ?? [8083, 8084];
    if (!$this->isPortInUse($primary)) return $primary;
    foreach ($fallbacks as $port) {
      if (!$this->isPortInUse((int) $port)) return (int) $port;
    }
    return $primary;
  }

  private function isPortInUse(int $port): bool
  {
    $sock = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.5);
    if ($sock !== false) {
      fclose($sock);
      return true;
    }
    return false;
  }

  private function getApachePid(): int
  {
    $pidFile = $this->stateDir . '/apache/httpd.pid';
    if (is_file($pidFile)) {
      $pid = (int) trim((string) @file_get_contents($pidFile));
      if ($pid > 0) return $pid;
    }
    if (PHP_OS_FAMILY !== 'Windows') {
      $output = @shell_exec('pgrep -f "httpd.*-f" 2>/dev/null');
      if (is_string($output) && trim($output) !== '') {
        $pids = array_filter(array_map('intval', explode("\n", trim($output))));
        if (!empty($pids)) return (int) $pids[0];
      }
    }
    return 0;
  }
    /* ═══════════════════════════════════════════════════════════
    QUIRKY.SETUP — PROJECT DETECTION (Phase 3)
    ═══════════════════════════════════════════════════════════ */

  /**
   * Parse a quirky.setup file (simple INI-style).
   *
   * Format:
   *   [section]
   *   key = value
   *   ; comment
   *
   * @param string $absolutePath Full server path to the quirky.setup file
   * @return array Parsed config, or empty array on failure
   */
  public function parseSetupFile(string $absolutePath): array
  {
    if (!is_file($absolutePath) || !is_readable($absolutePath)) {
      return [];
    }

    $raw = @file_get_contents($absolutePath);
    if ($raw === false || trim($raw) === '') {
      return [];
    }

    $result  = [];
    $section = '';

    foreach (explode("\n", $raw) as $line) {
      $line = trim($line);

      // Skip empty lines and comments
      if ($line === '' || $line[0] === ';' || $line[0] === '#') {
        continue;
      }

      // Section header: [section_name]
      if ($line[0] === '[' && substr($line, -1) === ']') {
        $section = strtolower(trim(substr($line, 1, -1)));
        if (!isset($result[$section])) {
          $result[$section] = [];
        }
        continue;
      }

      // Key = value
      $eqPos = strpos($line, '=');
      if ($eqPos === false) {
        continue;
      }

      $key   = strtolower(trim(substr($line, 0, $eqPos)));
      $value = trim(substr($line, $eqPos + 1));

      if ($key === '') {
        continue;
      }

      if ($section !== '') {
        $result[$section][$key] = $value;
      } else {
        $result[$key] = $value;
      }
    }

    return $result;
  }

  /**
   * Check if a directory contains a valid quirky.setup file.
   *
   * @param string $dirAbsolute Full server path to the directory
   * @param string $dirRelative Workspace-relative path (for display)
   * @return array|null Project info, or null if not a project
   */
  public function detectProject(string $dirAbsolute, string $dirRelative = ''): ?array
  {
    $setupFile = rtrim($dirAbsolute, '/') . '/quirky.setup';

    if (!is_file($setupFile)) {
      return null;
    }

    $parsed = $this->parseSetupFile($setupFile);

    if (empty($parsed)) {
      return null;
    }

    // Extract project metadata with sensible defaults
    $projectSection = $parsed['project'] ?? [];
    $serverSection  = $parsed['server'] ?? [];
    $previewSection = $parsed['preview'] ?? [];

    $name = $projectSection['name'] ?? '';
    if ($name === '') {
      // Fall back to the folder name
      $name = $dirRelative !== ''
        ? basename($dirRelative)
        : basename($dirAbsolute);
    }

    return [
      'name'          => $name,
      'path'          => $dirRelative,
      'stack'         => strtolower($projectSection['stack'] ?? 'php'),
      'documentRoot'  => $serverSection['document_root'] ?? '/',
      'router'        => $serverSection['router'] ?? 'index.php',
      'htaccess'      => strtolower($serverSection['htaccess'] ?? 'false') === 'true',
      'entry'         => $previewSection['entry'] ?? ($serverSection['router'] ?? 'index.php'),
      'hasHtaccess'   => is_file(rtrim($dirAbsolute, '/') . '/.htaccess'),
    ];
  }

  /**
   * Scan the entire workspace for projects (folders with quirky.setup).
   * Scans up to 3 levels deep to avoid deep recursion.
   *
   * @return array List of project info arrays
   */
  public function scanForProjects(): array
  {
    $workspaceRoot = IDE_WORKSPACE;
    $projects      = [];
    $maxDepth      = 3;

    $this->scanDirForProjects($workspaceRoot, '', 0, $maxDepth, $projects);

    return $projects;
  }

  /**
   * Recursive helper for scanForProjects().
   */
  private function scanDirForProjects(
    string $dir,
    string $relDir,
    int $depth,
    int $maxDepth,
    array &$projects
  ): void {
    if ($depth > $maxDepth) {
      return;
    }

    $entries = @scandir($dir);
    if ($entries === false) {
      return;
    }

    foreach ($entries as $entry) {
      if ($entry === '.' || $entry === '..') {
        continue;
      }

      $entryAbs = $dir . '/' . $entry;
      $entryRel = $relDir === '' ? $entry : $relDir . '/' . $entry;

      if (is_link($entryAbs)) {
        continue;
      }

      if (!is_dir($entryAbs)) {
        continue;
      }

      // Skip blocked segments
      $blocked = $this->config['security']['blocked_segments'] ?? [];
      if (in_array($entry, $blocked, true)) {
        continue;
      }

      // Check for quirky.setup
      $project = $this->detectProject($entryAbs, $entryRel);
      if ($project !== null) {
        $projects[] = $project;
        // Don't recurse into project folders looking for nested projects
        continue;
      }

      // Recurse into non-project folders
      $this->scanDirForProjects($entryAbs, $entryRel, $depth + 1, $maxDepth, $projects);
    }
  }
}
