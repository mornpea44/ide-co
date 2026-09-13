<?php

declare(strict_types=1);
/**
 * QUIRKY IDE — LIVE PREVIEW RENDERER (v4 · standalone-first execution)
 */
class IdePreview
{
    private IdeSecurity $security;
    private IdePhpTools $phpTools;
    private ?IdeWorkshop $workshop;

    private const PHP_EXTS   = ['php', 'phtml', 'php3', 'php4', 'php5'];
    private const MD_EXTS    = ['md', 'markdown'];
    private const IMAGE_EXTS = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'bmp', 'ico', 'avif', 'svg'];

    private const SERVER_PORT_MIN     = 8090;
    private const SERVER_PORT_MAX     = 8190;
    private const SERVER_RESERVED     = [8080, 8081, 8082, 8083, 8084];
    private const SERVER_IDLE_SECONDS  = 900;
    private const SERVER_SWEEP_EVERY   = 30;
    private const SERVER_BOOT_TIMEOUT  = 3.5;
    private const SERVER_FAIL_COOLDOWN = 45;
    private const SUBPROC_STDOUT_CAP   = 1048576;   // was 81920 — big landing pages were cut mid-document
    private const PHONE_DESIGN_W       = 390;        // same design width as the in-IDE phone preview
    private static ?array $scanCache = null;
    private static float $scanAt = 0;
    private static ?bool $apacheCache = null;
    private static float $apacheAt = 0;
    private static float $lastSweepAt = 0;

    private static array $procs = [];
    private static bool $shutdownHooked = false;

    public function __construct(IdeSecurity $security, ?IdeWorkshop $workshop = null)
    {
        $this->security = $security;
        $this->phpTools = new IdePhpTools();
        $this->workshop = $workshop;
    }

    private function apacheRunningCached(): bool
    {
        if ($this->workshop === null) return false;
        $now = microtime(true);
        if (self::$apacheCache !== null && ($now - self::$apacheAt) < 10) {
            return self::$apacheCache;
        }
        self::$apacheCache = $this->workshop->isApacheRunning();
        self::$apacheAt = $now;
        return self::$apacheCache;
    }

    private function scanProjectsCached(): array
    {
        if ($this->workshop === null) return [];
        $now = microtime(true);
        if (self::$scanCache !== null && ($now - self::$scanAt) < 10) {
            return self::$scanCache;
        }
        self::$scanCache = $this->workshop->scanForProjects();
        self::$scanAt = $now;
        return self::$scanCache;
    }

    private function resolveApacheUrl(string $rawPath): ?string
    {
        if ($this->workshop === null || !$this->apacheRunningCached()) {
            return null;
        }
        $ext = strtolower(pathinfo($rawPath, PATHINFO_EXTENSION));
        if (in_array($ext, self::MD_EXTS, true) || in_array($ext, self::IMAGE_EXTS, true)) {
            return null;
        }
        foreach ($this->scanProjectsCached() as $project) {
            $projectPath = (string) $project['path'];
            if ($rawPath === $projectPath || strpos($rawPath, $projectPath . '/') === 0) {
                return 'http://127.0.0.1:' . $this->workshop->getApachePort() . '/' . $rawPath;
            }
        }
        return null;
    }

    private function getProjectNameForPath(string $rawPath): string
    {
        if ($this->workshop !== null) {
            foreach ($this->scanProjectsCached() as $project) {
                $projectPath = (string) $project['path'];
                if ($rawPath === $projectPath || strpos($rawPath, $projectPath . '/') === 0) {
                    return (string) $project['name'];
                }
            }
        }
        return 'Apache';
    }

    private function serversDir(): ?string
    {
        $dir = IDE_APP . '/.workshop/servers';
        if (!is_dir($dir)) @mkdir($dir, 0777, true);
        return is_dir($dir) ? $dir : null;
    }

    private function resolveProjectContext(string $rawPath): array
    {
        static $memo = [];
        if (isset($memo[$rawPath])) return $memo[$rawPath];

        $ws = str_replace('\\', '/', rtrim((string) (realpath(IDE_WORKSPACE) ?: IDE_WORKSPACE), '/'));
        $ctx = ['root' => $ws, 'rootRel' => '', 'name' => 'Workspace'];

        $absFile = realpath($ws . '/' . $rawPath);
        $absFileN = $absFile ? str_replace('\\', '/', $absFile) : '';
        $dir = $ws;
        if ($absFileN !== '' && strpos($absFileN . '/', $ws . '/') === 0) {
            $dir = is_dir($absFileN) ? $absFileN : ((($p = strrpos($absFileN, '/')) !== false) ? substr($absFileN, 0, $p) : $ws);
        }

        if ($this->workshop !== null) {
            $rel = ($dir === $ws) ? '' : ltrim(substr($dir, strlen($ws)), '/');
            $parts = $rel === '' ? [] : explode('/', $rel);
            for ($i = count($parts); $i >= 0; $i--) {
                $relDir = implode('/', array_slice($parts, 0, $i));
                $absDir = $ws . ($relDir === '' ? '' : '/' . $relDir);
                $proj = $this->workshop->detectProject($absDir, $relDir);
                if ($proj !== null) {
                    $ctx = ['root' => str_replace('\\', '/', rtrim($absDir, '/')), 'rootRel' => $relDir, 'name' => (string) $proj['name']];
                    break;
                }
            }
        }

        $memo[$rawPath] = $ctx;
        return $ctx;
    }

    private function ensureProjectServer(array $ctx): ?array
    {
        if (!function_exists('proc_open')) return null;
        $srvDir = $this->serversDir();
        if ($srvDir === null) return null;
        $slug = md5($ctx['root']);
        $this->sweepIdleServers($srvDir);
        $portFile   = $srvDir . '/' . $slug . '.port';
        $pidFile    = $srvDir . '/' . $slug . '.pid';
        $tsFile     = $srvDir . '/' . $slug . '.ts';
        $routerFile = $srvDir . '/' . $slug . '-router.php';
        $lockFile   = $srvDir . '/' . $slug . '.lock';
        $failFile   = $srvDir . '/' . $slug . '.fail';
        $fh = @fopen($lockFile, 'c');
        $locked = false;
        if ($fh) $locked = @flock($fh, LOCK_EX);
        try {
            if (is_file($portFile)) {
                $port = (int) trim((string) @file_get_contents($portFile));
                if ($port > 0 && $this->portOpen($port, 0.35)) {
                    @file_put_contents($tsFile, (string) microtime(true));
                    $pid = (int) trim((string) @file_get_contents($pidFile));
                    return ['port' => $port, 'pid' => $pid];
                }
                @unlink($portFile);
                @unlink($pidFile);
                @unlink($tsFile);
            }
            if (is_file($failFile)) {
                $failTs = (float) trim((string) @file_get_contents($failFile));
                if ($failTs > 0 && (microtime(true) - $failTs) < self::SERVER_FAIL_COOLDOWN) {
                    return null;
                }
                @unlink($failFile);
            }
            if (
                !is_file($routerFile) || filesize($routerFile) === 0 ||
                strpos((string) @file_get_contents($routerFile), 'QRV_ROUTER_V3') === false
            ) {
                $this->generateRouter($routerFile, $ctx['root']);
            }
            $port = $this->findFreePort($srvDir);
            if ($port === null) {
                @file_put_contents($failFile, (string) microtime(true));
                return null;
            }
            $pid = $this->spawnServer($ctx['root'], $routerFile, $port, $srvDir . '/' . $slug . '.log');
            if ($pid === null) {
                @file_put_contents($failFile, (string) microtime(true));
                return null;
            }
            $ok = false;
            $deadline = microtime(true) + $this->bootTimeout();   // was: self::SERVER_BOOT_TIMEOUT
            while (microtime(true) < $deadline) {
                if ($this->portOpen($port, 0.3)) {
                    $ok = true;
                    break;
                }
                usleep(60000);
            }
            if (!$ok) {
                $logFile = $srvDir . '/' . $slug . '.log';
                $tail = is_file($logFile) ? trim(substr((string) @file_get_contents($logFile), -600)) : '';
                if ($tail !== '') @error_log('[QuirkyPreview] micro-server boot failed :' . $port . ' — ' . $tail);
                $this->terminateOwnPid($pid, basename($routerFile));
                @file_put_contents($failFile, (string) microtime(true));
                return null;
            }
            @unlink($failFile);
            @file_put_contents($portFile, (string) $port);
            @file_put_contents($pidFile, (string) $pid);
            @file_put_contents($tsFile, (string) microtime(true));
            return ['port' => $port, 'pid' => $pid];
        } finally {
            if ($fh) {
                if ($locked) @flock($fh, LOCK_UN);
                @fclose($fh);
            }
        }
    }

    private function touchServer(array $ctx): void
    {
        $srvDir = $this->serversDir();
        if ($srvDir === null) return;
        @file_put_contents($srvDir . '/' . md5($ctx['root']) . '.ts', (string) microtime(true));
    }

    private function getWarmServer(array $ctx): ?array
    {
        $srvDir = $this->serversDir();
        if ($srvDir === null) return null;
        $slug     = md5($ctx['root']);
        $portFile = $srvDir . '/' . $slug . '.port';
        $pidFile  = $srvDir . '/' . $slug . '.pid';
        if (!is_file($portFile)) return null;
        $port = (int) trim((string) @file_get_contents($portFile));
        if ($port <= 0 || !$this->portOpen($port, 0.35)) {
            return null;
        }
        $pid = (int) trim((string) @file_get_contents($pidFile));
        @file_put_contents($srvDir . '/' . $slug . '.ts', (string) microtime(true));
        return ['port' => $port, 'pid' => $pid];
    }

    private function portOpen(int $port, float $timeout): bool
    {
        $sock = @fsockopen('127.0.0.1', $port, $errno, $errstr, $timeout);
        if ($sock !== false) {
            fclose($sock);
            return true;
        }
        return false;
    }

    private function findFreePort(string $srvDir): ?int
    {
        $claimed = [];
        foreach ((glob($srvDir . '/*.port') ?: []) as $f) {
            $claimed[] = (int) trim((string) @file_get_contents($f));
        }
        for ($p = self::SERVER_PORT_MIN; $p <= self::SERVER_PORT_MAX; $p++) {
            if (in_array($p, self::SERVER_RESERVED, true)) continue;
            if (in_array($p, $claimed, true)) continue;
            $sock = @stream_socket_server('tcp://127.0.0.1:' . $p, $errno, $errstr);
            if ($sock !== false) {
                fclose($sock);
                return $p;
            }
        }
        return null;
    }

    private function phpCliBinary(): string
    {
        $bin  = (string) PHP_BINARY;
        $base = strtolower(basename($bin));
        if ($base === 'php' || $base === 'php.exe' || $base === 'libphp.so') return $bin;
        return (PHP_OS_FAMILY === 'Windows') ? 'php.exe' : 'php';
    }

    private function spawnServer(string $root, string $routerFile, int $port, string $logFile): ?int
    {
        if (is_file($logFile) && filesize($logFile) > 262144) {
            @rename($logFile, $logFile . '.old');
        }
        $cmd = [$this->phpCliBinary(), '-S', '127.0.0.1:' . $port, '-t', $root, $routerFile];

        /* ★ FIX (Android): never rebuild the child environment — inherit it.
       The explicit $env = getenv() array produced a stripped environment on
       device (no LD_LIBRARY_PATH), so libphp.so died at link time and the
       pool always fell back to the CLI renderer. putenv() IS visible to
       proc_open() when the env parameter is omitted. */
        putenv('PHP_CLI_SERVER_WORKERS=1');

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['file', $logFile, 'ab'],
            2 => ['file', $logFile, 'ab'],
        ];
        $pipes = [];
        $proc = @proc_open($cmd, $descriptors, $pipes, $root);   // ← no $env param
        if (!is_resource($proc)) return null;
        fclose($pipes[0]);
        $status = proc_get_status($proc);
        $pid = (int) $status['pid'];
        self::$procs[$pid] = $proc;
        $this->hookShutdown();
        return $pid > 0 ? $pid : null;
    }

    /* ★ Phones have cold linker caches; 3.5 s false-failed the first boot. */
    private function bootTimeout(): float
    {
        return (getenv('QUIRKY_APPLIB') !== false || getenv('QUIRKY_PREFIX') !== false)
            ? 9.0
            : self::SERVER_BOOT_TIMEOUT;
    }

    private function hookShutdown(): void
    {
        if (self::$shutdownHooked) return;
        self::$shutdownHooked = true;
        register_shutdown_function(static function (): void {
            foreach (self::$procs as $pid => $proc) {
                if (!is_resource($proc)) continue;
                $st = proc_get_status($proc);
                if (is_array($st) && !$st['running']) {
                    proc_close($proc);
                    unset(self::$procs[$pid]);
                }
            }
        });
    }

    private function terminateOwnPid(int $pid, string $needle): bool
    {
        $killed = false;
        if (isset(self::$procs[$pid]) && is_resource(self::$procs[$pid])) {
            proc_terminate(self::$procs[$pid], 9);
            usleep(60000);
            proc_close(self::$procs[$pid]);
            unset(self::$procs[$pid]);
            $killed = true;
        }
        if (!$killed) $killed = $this->killRecordedPid($pid, $needle);
        return $killed;
    }

    private function killRecordedPid(int $pid, string $needle): bool
    {
        if ($pid <= 0 || $needle === '') return false;
        if (PHP_OS_FAMILY === 'Windows') {
            $out = @shell_exec('powershell -NoProfile -Command "(Get-CimInstance Win32_Process -Filter \'ProcessId=' . $pid . '\').CommandLine" 2>nul');
            if (!is_string($out) || strpos($out, $needle) === false) return false;
            @exec('taskkill /F /T /PID ' . $pid . ' 2>nul');
            return true;
        }
        $cl = @file_get_contents('/proc/' . $pid . '/cmdline');
        if ($cl === false || strpos($cl, $needle) === false) return false;
        if (function_exists('posix_kill')) {
            @posix_kill($pid, 9);
        } else {
            @exec('kill -9 ' . $pid . ' 2>/dev/null');
        }
        return true;
    }

    private function sweepIdleServers(string $srvDir): void
    {
        $now = microtime(true);
        if (($now - self::$lastSweepAt) < self::SERVER_SWEEP_EVERY) return;
        self::$lastSweepAt = $now;
        foreach ((glob($srvDir . '/*.pid') ?: []) as $pidFile) {
            $slug = basename($pidFile, '.pid');
            $tsFile = $srvDir . '/' . $slug . '.ts';
            $ts = is_file($tsFile) ? (float) trim((string) @file_get_contents($tsFile)) : 0.0;
            if ($ts <= 0 || ($now - $ts) < self::SERVER_IDLE_SECONDS) continue;
            $pid = (int) trim((string) @file_get_contents($pidFile));
            $routerNeedle = $slug . '-router.php';
            if ($pid > 0) $this->killRecordedPid($pid, $routerNeedle);
            @unlink($pidFile);
            @unlink($srvDir . '/' . $slug . '.port');
            @unlink($tsFile);
            @unlink($srvDir . '/' . $slug . '.lock');
            @unlink($srvDir . '/' . $slug . '.fail');
            $shadowList = $srvDir . '/' . $slug . '.shadows';
            if (is_file($shadowList)) {
                foreach (array_filter(array_map('trim', file($shadowList, FILE_IGNORE_NEW_LINES) ?: [])) as $sh) {
                    if (is_file($sh)) @unlink($sh);
                }
                @unlink($shadowList);
            }
        }
    }

    private function generateRouter(string $routerFile, string $root): void
    {
        $exported = var_export(str_replace('\\', '/', rtrim($root, '/')), true);
        // ★ NOWDOC (single-quoted 'PHP' label) instead of a heredoc: the block
        // below is 99% literal PHP source with only ONE substitution point
        // (the project root). A heredoc forces every single $ in that source
        // to be escaped as \$, which is what was tripping up the editor's
        // parser (and is generally fragile/easy to break). A nowdoc does NO
        // interpolation at all, so the $ signs are written exactly as they'd
        // appear in a normal .php file — the placeholder below is swapped in
        // afterwards with a plain str_replace().
        $tpl = <<<'PHP'
            <?php
            /* Quirky IDE preview micro-router (auto-generated — safe to delete). QRV_ROUTER_V3 */
            $__qvRoot = __QV_ROOT_PLACEHOLDER__;
            /* ★ v5.5: true only when ONE selector chunk targets the DOCUMENT
               scroller (html / body / * / :root / bare ::-webkit-scrollbar).
               Scoped rules like .rail::-webkit-scrollbar style an inner
               element only and must NOT disable the phone emulation. */
                        function quirky_pv_sel_targets_root($__qvSel) {
                foreach (explode(',', (string) $__qvSel) as $__qvp) {
                    $__qvp = trim((string) preg_replace('/::-webkit-scrollbar.*$/i', '', $__qvp));
                    if ($__qvp === '') return true;
                    if (preg_match('/(^|[\s>+~,])(html|body|\*|:root)$/', $__qvp)) return true;
                }
                return false;
            }
            function quirky_pv_styles_root_scrollbar($__qvHtml) {
                if (preg_match_all('/[^{}]*::-webkit-scrollbar/i', $__qvHtml, $__qvm)) {
                    foreach ($__qvm[0] as $__qvs) { if (quirky_pv_sel_targets_root($__qvs)) return true; }
                }
                if (preg_match_all('/[^{}]*\{[^}]*scrollbar-(?:width|color)\s*:/i', $__qvHtml, $__qvm)) {
                    foreach ($__qvm[0] as $__qvc) {
                        if (quirky_pv_sel_targets_root(substr($__qvc, 0, (int) strrpos($__qvc, '{')))) return true;
                    }
                }
                return false;
            }
            /* ★ v5.4 real-browser illusion (same rule as the client painter): hide the
            default scrollbar ONLY when the page does not style its own ROOT scroller
            (★ v5.5: scoped inner-element scrollbar rules no longer disable us). */
            function quirky_pv_norm_scrollbar($__qvHtml) {
                if (!is_string($__qvHtml) || $__qvHtml === '') return $__qvHtml;
                if (quirky_pv_styles_root_scrollbar($__qvHtml)) return $__qvHtml;
                $__qvStyle = '<style data-quirky-pv="sb">html,body{scrollbar-width:none;-ms-overflow-style:none}html::-webkit-scrollbar,body::-webkit-scrollbar{width:0;height:0;display:none}</style>';
                if (preg_match('/<head\b[^>]*>/i', $__qvHtml, $__qvM, PREG_OFFSET_CAPTURE)) {
                    $__qvAt = $__qvM[0][1] + strlen($__qvM[0][0]);
                    return substr($__qvHtml, 0, $__qvAt) . $__qvStyle . substr($__qvHtml, $__qvAt);
                }
                $__qvPos = stripos($__qvHtml, '</head>');
                if ($__qvPos !== false) return substr($__qvHtml, 0, $__qvPos) . $__qvStyle . substr($__qvHtml, $__qvPos);
                return $__qvStyle . $__qvHtml;
            }
            $__qvUri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '/';
            $__qvPath = parse_url($__qvUri, PHP_URL_PATH);
            if (!is_string($__qvPath) || $__qvPath === '') { $__qvPath = '/'; }
            $__qvPath = rawurldecode($__qvPath);
            while (strpos($__qvPath, '//') !== false) { $__qvPath = str_replace('//', '/', $__qvPath); }
            if ($__qvPath !== '/' && substr($__qvPath, -1) === '/') { $__qvPath = rtrim($__qvPath, '/'); if ($__qvPath === '') { $__qvPath = '/'; } }
            $__qvRealRoot = str_replace('\\', '/', (string) realpath($__qvRoot));
            $__qvReal = realpath($__qvRealRoot . $__qvPath);
            if ($__qvReal === false) {
                http_response_code(404);
                header('Content-Type: text/plain; charset=utf-8');
                echo 'Quirky preview: not found: ' . htmlspecialchars($__qvPath, ENT_QUOTES);
                exit;
            }
            $__qvReal = str_replace('\\', '/', $__qvReal);
            if ($__qvReal !== $__qvRealRoot && strpos($__qvReal, $__qvRealRoot . '/') !== 0) {
                http_response_code(403);
                header('Content-Type: text/plain; charset=utf-8');
                echo 'Quirky preview: forbidden';
                exit;
            }
            if (is_dir($__qvReal)) {
                $__qvIdx = null;
                foreach (['index.php', 'index.html'] as $__qvi) {
                    if (is_file($__qvReal . '/' . $__qvi)) { $__qvIdx = $__qvReal . '/' . $__qvi; break; }
                }
                if ($__qvIdx === null) {
                    http_response_code(404);
                    header('Content-Type: text/plain; charset=utf-8');
                    echo 'Quirky preview: directory has no index.php/index.html';
                    exit;
                }
                $__qvReal = $__qvIdx;
            }
            if (preg_match('/\.p(html|hp[0-9]?)$/i', $__qvReal)) {
                $__qvScriptName = substr($__qvReal, strlen($__qvRealRoot));
                if ($__qvScriptName === '' || $__qvScriptName[0] !== '/') { $__qvScriptName = '/' . ltrim($__qvScriptName, '/'); }
                $_SERVER['DOCUMENT_ROOT'] = $__qvRealRoot;
                $_SERVER['SCRIPT_FILENAME'] = $__qvReal;
                $_SERVER['SCRIPT_NAME'] = $__qvScriptName;
                $_SERVER['PHP_SELF'] = $__qvScriptName;
                chdir(dirname($__qvReal));
                ob_start();
                include $__qvReal;
                $__qvOut = ob_get_clean();
                chdir($__qvRealRoot);
                echo quirky_pv_norm_scrollbar($__qvOut);
                return;
            }
            /* ★ v5.4 static HTML gets the same real-browser scrollbar treatment. */
            if (preg_match('/\.html?$/i', $__qvReal)) {
                $__qvOut = @file_get_contents($__qvReal);
                if ($__qvOut !== false) {
                    header('Content-Type: text/html; charset=utf-8');
                    echo quirky_pv_norm_scrollbar($__qvOut);
                    return;
                }
            }
            /* Any other existing static file — let the built-in server stream it natively. */
            return false;
            PHP;
        $tpl = str_replace('__QV_ROOT_PLACEHOLDER__', $exported, $tpl);
        @file_put_contents($routerFile, $tpl . "\n", LOCK_EX);
    }

    private function serverUrlFor(array $ctx, int $port, string $rawPath): string
    {
        $prefix = $ctx['rootRel'] === '' ? '' : $ctx['rootRel'] . '/';
        $within = ($prefix !== '' && strpos($rawPath . '/', $prefix) === 0)
            ? substr($rawPath, strlen($prefix))
            : $rawPath;
        $segs = explode('/', str_replace('\\', '/', $within));
        return 'http://127.0.0.1:' . $port . '/' . implode('/', array_map('rawurlencode', $segs));
    }

    private function shadowName(string $rawPath): string
    {
        return '.quirky-live-' . md5($rawPath) . '.php';
    }

    private function writeShadowBuffer(array $ctx, string $rawPath, string $buffer): ?string
    {
        $abs = $this->absWorkspacePath($rawPath);
        if ($abs === null) return null;
        $dir = dirname($abs);
        $shadowAbs = $dir . '/' . $this->shadowName($rawPath);
        if (@file_put_contents($shadowAbs, $buffer, LOCK_EX) === false) return null;
        $srvDir = $this->serversDir();
        if ($srvDir !== null) {
            $list = $srvDir . '/' . md5($ctx['root']) . '.shadows';
            @file_put_contents($list, $shadowAbs . "\n", FILE_APPEND | LOCK_EX);
        }
        $relDir = dirname($rawPath);
        return ($relDir === '.' || $relDir === '' ? '' : $relDir . '/') . $this->shadowName($rawPath);
    }

    private function removeShadowFor(string $rawPath): void
    {
        $abs = $this->absWorkspacePath($rawPath);
        if ($abs === null) return;
        $shadowAbs = dirname($abs) . '/' . $this->shadowName($rawPath);
        if (is_file($shadowAbs)) @unlink($shadowAbs);
    }

    public function handle(string $rawPath, array $input, string $method, bool $raw): void
    {
        $ext   = strtolower(pathinfo($rawPath, PATHINFO_EXTENSION));
        $isPhp = in_array($ext, self::PHP_EXTS, true);

        if (
            $rawPath === '' ||
            strpos($rawPath, "\0") !== false ||
            strpos($rawPath, '..') !== false ||
            preg_match('#^([a-zA-Z]:)?[\\\\/]#', $rawPath)
        ) {
            $this->security->fail('Invalid preview path.', 400);
        }

        $apacheUrl = $this->resolveApacheUrl($rawPath);
        if ($apacheUrl !== null) {
            if ($raw) {
                header('Location: ' . $apacheUrl);
                exit;
            }
            $this->security->respond([
                'mode'        => 'apache',
                'url'         => $apacheUrl,
                'projectName' => $this->getProjectNameForPath($rawPath),
            ]);
        }

        $device = (string) ($_GET['device'] ?? 'desktop');
        if (!in_array($device, ['phone', 'tablet', 'desktop'], true)) $device = 'desktop';
        $zoom = (float) ($_GET['zoom'] ?? 1);
        if (!is_finite($zoom) || $zoom <= 0) $zoom = 1.0;
        $zoom = max(0.25, min(2.0, $zoom));

        $bare = isset($_GET['bare']);

        $live = !empty($input['live']);

        if (in_array($ext, self::MD_EXTS, true)) {
            $content = $this->readWorkspaceFile($rawPath);
            $this->emitMarkdownPopOut($rawPath, $content, $device, $zoom);
        }

        if (in_array($ext, self::IMAGE_EXTS, true)) {
            if ($bare) {
                $this->emitImageViewer($rawPath, $zoom);
            }
            $this->emitImagePopOut($rawPath, $device, $zoom);
        }

        $ctx    = $this->resolveProjectContext($rawPath);
        $server = null;
        if ($live || $raw) {
            /* ★ Pop-outs (raw) and live keystrokes NEVER wait on a cold
               micro-server boot: reuse a warm one, or fall back fast. */
            $server = $this->getWarmServer($ctx);
        } else {
            try {
                $server = $this->ensureProjectServer($ctx);
            } catch (\Throwable $e) {
                $server = null;
            }
        }

        if (!$raw) {
            $buffer = (string) ($input['content'] ?? '');
            if ($buffer === '') {
                $fromDisk = $this->readDiskQuiet($rawPath);
                if ($fromDisk !== null) $buffer = $fromDisk;
            }
            if ($server !== null && in_array($ext, ['php', 'phtml', 'html', 'htm'], true)) {
                $disk = $this->readDiskQuiet($rawPath);
                if ($disk !== null && hash_equals(md5($disk), md5($buffer))) {
                    $this->removeShadowFor($rawPath);
                    $this->touchServer($ctx);
                    $this->security->respond([
                        'mode'        => 'server',
                        'url'         => $this->serverUrlFor($ctx, $server['port'], $rawPath),
                        'port'        => $server['port'],
                        'projectName' => $ctx['name'],
                        'language'    => $isPhp ? 'php' : $ext,
                    ]);
                }
                if ($disk !== null && $isPhp) {
                    $shadowRel = $this->writeShadowBuffer($ctx, $rawPath, $buffer);
                    if ($shadowRel !== null) {
                        $this->touchServer($ctx);
                        $this->security->respond([
                            'mode'        => 'server',
                            'url'         => $this->serverUrlFor($ctx, $server['port'], $shadowRel),
                            'port'        => $server['port'],
                            'projectName' => $ctx['name'],
                            'shadow'      => true,
                            'language'    => 'php',
                        ]);
                    }
                }
            }
            if ($isPhp) {
                [$html, $err] = $this->runPhpIsolated($buffer, dirname($this->absWorkspacePath($rawPath) ?? IDE_WORKSPACE), $rawPath);
            } else {
                $html = $buffer;
                $err  = null;
            }
            $this->security->respond([
                'mode'     => 'html',
                'html'     => $html,
                'error'    => $err,
                'language' => $isPhp ? 'php' : $ext,
            ]);
        }

        $content = $this->readWorkspaceFile($rawPath);

        if ($bare) {
            if ($server !== null) {
                $this->touchServer($ctx);
                header('Location: ' . $this->serverUrlFor($ctx, $server['port'], $rawPath)
                    . '?t=' . (int) microtime(true));
                exit;
            }
            if ($isPhp) {
                [$html, $err] = $this->runPhpIsolated($content, dirname($this->absWorkspacePath($rawPath) ?? IDE_WORKSPACE), $rawPath);
                $html = ($err !== null ? $this->phpBannerHtml($err) : '') . $html;
            } else {
                $html = $content;
            }
            $this->emitRaw($rawPath, $html, $zoom);
        }

        if ($server !== null) {
            $this->touchServer($ctx);
            $bareUrl = $this->serverUrlFor($ctx, $server['port'], $rawPath)
                . '?t=' . (int) microtime(true) . '&qvpop=1';
        } else {
            $bareUrl = 'index.php?api=preview-render&raw=1&bare=1&path=' . urlencode($rawPath) . '&zoom=' . $zoom;
        }
        $screen = '<div class="pvq-screen"><iframe src="' . htmlspecialchars($bareUrl, ENT_QUOTES) . '" title="Preview"></iframe></div>';
        $this->emitFramedPage($rawPath, $device, $screen);
    }

    private function absWorkspacePath(string $rawPath): ?string
    {
        $root = str_replace('\\', '/', rtrim((string) (realpath(IDE_WORKSPACE) ?: IDE_WORKSPACE), '/'));
        $abs  = realpath(IDE_WORKSPACE . '/' . $rawPath);
        $absN = $abs ? str_replace('\\', '/', $abs) : '';
        if (!$abs || strpos($absN, $root . '/') !== 0) return null;
        return $absN;
    }

    private function readWorkspaceFile(string $rawPath): string
    {
        $abs = $this->absWorkspacePath($rawPath);
        if ($abs === null) {
            $this->security->fail('Preview file not found.', 404);
        }
        return (string) @file_get_contents($abs);
    }

    private function readDiskQuiet(string $rawPath): ?string
    {
        $abs = $this->absWorkspacePath($rawPath);
        if ($abs === null || !is_file($abs)) return null;
        $c = @file_get_contents($abs);
        return $c === false ? null : $c;
    }

    private function diskExists(string $rawPath): bool
    {
        return $this->absWorkspacePath($rawPath) !== null;
    }

    private function runPhpIsolated(string $code, string $cwdDir, string $rawPath = ''): array
    {
        $html = '';
        $err  = null;

        if (!function_exists('proc_open')) {
            return ['', 'PHP preview unavailable: proc_open is disabled on this device and no preview server could be started.'];
        }
        if (!is_dir($cwdDir)) $cwdDir = sys_get_temp_dir();

        $key    = substr(md5($code . microtime(true)), 0, 12);
        $tmp    = rtrim(str_replace('\\', '/', $cwdDir), '/') . '/.quirky-preview-' . $key . '.php';
        $boot   = $tmp . '.boot.php';
        $wrote  = @file_put_contents($tmp, $code);
        if ($wrote === false) {
            $tmp  = rtrim(str_replace('\\', '/', sys_get_temp_dir()), '/') . '/quirky-preview-' . $key . '.php';
            $boot = $tmp . '.boot.php';
            if (@file_put_contents($tmp, $code) === false) {
                return ['', 'PHP preview failed: could not create a temporary execution file.'];
            }
        }
        /* ★ FIX: execute a shim that fakes a web GET request, then includes the
       user file. Plain CLI has no $_SERVER['REQUEST_METHOD'] etc. */
        @file_put_contents($boot, $this->cliWebShim($tmp, $rawPath));

        $pipes = [];
        $proc  = @proc_open(
            [PHP_BINARY, '-d', 'display_errors=STDERR', '-d', 'error_reporting=E_ALL', '-d', 'html_errors=0', $boot],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $cwdDir
        );
        if (!is_resource($proc)) {
            @unlink($tmp);
            @unlink($boot);
            return ['', 'PHP preview failed: could not spawn the isolated PHP process.'];
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $out = '';
        $errOut = '';
        $exit = -1;
        $deadline = microtime(true) + 20.0;
        while (microtime(true) < $deadline) {
            $st = proc_get_status($proc);
            $out .= (string) stream_get_contents($pipes[1]);
            $errOut .= (string) stream_get_contents($pipes[2]);
            if (!$st['running']) {
                $exit = (int) $st['exitcode'];
                break;
            }
            usleep(20000);
        }
        $out .= (string) stream_get_contents($pipes[1]);
        $errOut .= (string) stream_get_contents($pipes[2]);
        if ($exit === -1) {
            proc_terminate($proc, 9);
            usleep(50000);
            $st = proc_get_status($proc);
            $exit = (int) ($st['exitcode'] ?: -1);
            $errOut = "Preview timed out after 20s.\n" . $errOut;
        }
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($proc);
        @unlink($tmp);
        @unlink($boot);

        $html = substr($out, 0, self::SUBPROC_STDOUT_CAP);
        $errText = trim(substr($errOut, -4000));
        if ($errText !== '') {
            $err = $errText . (($exit !== 0 && $exit !== -1) ? "\n(process exited with code {$exit})" : '');
        } elseif ($exit !== 0 && $exit !== -1) {
            $err = 'The page stopped itself before finishing (exit code ' . $exit . ').';
        }
        return [$html, $err];
    }

    /* ★ NEW method — add next to runPhpIsolated() */
    private function cliWebShim(string $targetFile, string $rawPath): string
    {
        $u = var_export('/' . ltrim(str_replace('\\', '/', $rawPath), '/'), true);
        $t = var_export($targetFile, true);
        return "<?php\n"
            . "/* Quirky IDE CLI web shim — plain `php x.php` behaves like a GET request. */\n"
            . "\$_SERVER['REQUEST_METHOD']    = \$_SERVER['REQUEST_METHOD']    ?? 'GET';\n"
            . "\$_SERVER['REQUEST_URI']       = \$_SERVER['REQUEST_URI']       ?? $u;\n"
            . "\$_SERVER['SCRIPT_NAME']       = \$_SERVER['SCRIPT_NAME']       ?? $u;\n"
            . "\$_SERVER['PHP_SELF']          = \$_SERVER['PHP_SELF']          ?? $u;\n"
            . "\$_SERVER['SCRIPT_FILENAME']   = \$_SERVER['SCRIPT_FILENAME']   ?? $t;\n"
            . "\$_SERVER['HTTP_HOST']         = \$_SERVER['HTTP_HOST']         ?? '127.0.0.1';\n"
            . "\$_SERVER['SERVER_NAME']       = \$_SERVER['SERVER_NAME']       ?? '127.0.0.1';\n"
            . "\$_SERVER['SERVER_ADDR']       = \$_SERVER['SERVER_ADDR']       ?? '127.0.0.1';\n"
            . "\$_SERVER['SERVER_PORT']       = \$_SERVER['SERVER_PORT']       ?? 80;\n"
            . "\$_SERVER['SERVER_PROTOCOL']   = \$_SERVER['SERVER_PROTOCOL']   ?? 'HTTP/1.1';\n"
            . "\$_SERVER['SERVER_SOFTWARE']   = \$_SERVER['SERVER_SOFTWARE']   ?? 'QuirkyPreview';\n"
            . "\$_SERVER['GATEWAY_INTERFACE'] = \$_SERVER['GATEWAY_INTERFACE'] ?? 'CGI/1.1';\n"
            . "\$_SERVER['REMOTE_ADDR']       = \$_SERVER['REMOTE_ADDR']       ?? '127.0.0.1';\n"
            . "\$_SERVER['DOCUMENT_ROOT']     = \$_SERVER['DOCUMENT_ROOT']     ?? getcwd();\n"
            . "\$_SERVER['QUERY_STRING']      = \$_SERVER['QUERY_STRING']      ?? '';\n"
            . "\$_SERVER['HTTP_USER_AGENT']   = \$_SERVER['HTTP_USER_AGENT']   ?? 'QuirkyIDE';\n"
            . "include $t;\n";
    }

    private function zoomCss(float $zoom): string
    {
        $s = rtrim(rtrim(sprintf('%.3f', $zoom), '0'), '.');
        return $s === '' ? '1' : $s;
    }

    private function phpBannerHtml(string $err): string
    {
        return '<div style="font:13px/1.55 ui-monospace,monospace;color:#fca5a5;background:#3b1414;'
            . 'border:1px solid #7f1d1d;border-radius:8px;padding:10px 12px;margin:8px;white-space:pre-wrap;">'
            . '⚠ PHP preview error: ' . htmlspecialchars($err, ENT_QUOTES) . '</div>';
    }

    private function injectZoom(string $html, float $zoom): string
    {
        if (abs($zoom - 1.0) < 0.001) return $html;
        $style = '<style>html{zoom:' . $this->zoomCss($zoom) . ';}</style>';
        if (preg_match('/<head\b[^>]*>/i', $html, $m, PREG_OFFSET_CAPTURE)) {
            $end = $m[0][1] + strlen($m[0][0]);
            return substr($html, 0, $end) . $style . substr($html, $end);
        }
        return $style . $html;
    }

    private function injectScrollbarNorm(string $html): string
    {
        if ($html === '') return $html;
        if ($this->stylesRootScrollbar($html)) return $html;
        $style = '<style data-quirky-pv="sb">html,body{scrollbar-width:none;-ms-overflow-style:none}'
            . 'html::-webkit-scrollbar,body::-webkit-scrollbar{width:0;height:0;display:none}</style>';
        if (preg_match('/<head\b[^>]*>/i', $html, $m, PREG_OFFSET_CAPTURE)) {
            $at = $m[0][1] + strlen($m[0][0]);
            return substr($html, 0, $at) . $style . substr($html, $at);
        }
        if (preg_match('/<\/head>/i', $html, $m, PREG_OFFSET_CAPTURE)) {
            /** @disregard */
            return substr($html, 0, $m[0][1]) . $style . substr($html, $m[0][1]);
        }
        return $style . $html;
    }

    private function stylesRootScrollbar(string $html): bool
    {
        if (preg_match_all('/[^{}]*::-webkit-scrollbar/i', $html, $m)) {
            foreach ($m[0] as $sel) {
                if ($this->selectorTargetsRoot($sel)) return true;
            }
        }
        if (preg_match_all('/[^{}]*[{][^}]*scrollbar-(?:width|color)\s*:/i', $html, $m)) {
            foreach ($m[0] as $chunk) {
                $sel = substr($chunk, 0, (int) strrpos($chunk, '{'));
                if ($this->selectorTargetsRoot($sel)) return true;
            }
        }
        return false;
    }

    private function selectorTargetsRoot(string $sel): bool
    {
        foreach (explode(',', $sel) as $p) {
            $p = trim((string) preg_replace('/::-webkit-scrollbar.*[$]/i', '', $p));
            if ($p === '') return true;
            if (preg_match('/(^|[\s>+~,])(html|body|[*]|:root)[$]/', $p)) return true;
        }
        return false;
    }

    private function injectNavGuard(string $html): string
    {
        $tag = '<script>' . self::NAV_GUARD_JS . '</script>';
        if (preg_match('/<\/body>/i', $html)) {
            return preg_replace('/<\/body>/i', $tag . '</body>', $html, 1) ?? $html;
        }
        return $html . $tag;
    }

    private function emitRaw(string $rawPath, string $html, float $zoom): void
    {
        $dir = '';
        $lastSlash = strrpos($rawPath, '/');
        if ($lastSlash !== false && $lastSlash > 0) {
            $dir = substr($rawPath, 0, $lastSlash + 1);
        }
        $wsPrefix = 'index.php?workspace=' . $dir;

        $stash = [];
        $html = preg_replace_callback(
            '/<(script|style)\b[\s\S]*?<\/\1\s*>/i',
            function ($m) use (&$stash) {
                $stash[] = $m[0];
                return "\x00" . (count($stash) - 1) . "\x00";
            },
            $html
        );

        $html = preg_replace_callback(
            '/((?:src|href)\s*=\s*)("(?:[^"]*)"|\'(?:[^\']*)\'|[^\s>]+)/i',
            function ($m) use ($dir, $wsPrefix) {
                $pre   = $m[1];
                $val   = $m[2];
                $quote = '';
                $url   = $val;
                $first = $val !== '' ? $val[0] : '';
                if ($first === '"' || $first === "'") {
                    $quote = $first;
                    $url   = substr($val, 1, -1);
                }
                if ($url === '' || $url[0] === '/') return $m[0];
                if (preg_match('~^(https?://|//|data:|#|javascript:|mailto:|tel:|about:|index\.php)~i', $url)) return $m[0];
                $resolved = $this->phpTools->resolveRel($dir, preg_replace('/^\.\//', '', $url));
                return $pre . $quote . $wsPrefix . $resolved . $quote;
            },
            $html
        );

        $html = preg_replace_callback(
            '/\x00(\d+)\x00/',
            function ($m) use (&$stash) {
                return $stash[(int) $m[1]];
            },
            $html
        );

        $safeRawPath = htmlspecialchars($rawPath, ENT_QUOTES);
        $html = preg_replace_callback(
            '/<form\b([^>]*)>/i',
            function ($m) use ($safeRawPath) {
                return '<form' . $m[1] . '>'
                    . '<input type="hidden" name="api" value="preview-render">'
                    . '<input type="hidden" name="raw" value="1">'
                    . '<input type="hidden" name="bare" value="1">'
                    . '<input type="hidden" name="path" value="' . $safeRawPath . '">';
            },
            $html
        );

        $html = $this->injectZoom($html, $zoom);
        $html = $this->injectScrollbarNorm($html);
        $html = $this->injectNavGuard($html);

        header('Content-Type: text/html; charset=utf-8');
        echo $html;
        exit;
    }

    private function emitImageViewer(string $rawPath, float $zoom): void
    {
        $url = 'index.php?workspace=' . urlencode($rawPath);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<style>html,body{margin:0;width:100%;height:100%;overflow:hidden;background:#1a1a2e;'
            . 'display:flex;align-items:center;justify-content:center;}'
            . 'img{max-width:96%;max-height:96%;transform:scale(' . $this->zoomCss($zoom) . ');'
            . 'image-rendering:auto;user-select:none;-webkit-user-drag:none;}</style></head><body>'
            . '<img src="' . htmlspecialchars($url, ENT_QUOTES) . '" alt="">'
            . '</body></html>';
        exit;
    }

    private function emitImagePopOut(string $rawPath, string $device, float $zoom): void
    {
        $url = 'index.php?workspace=' . urlencode($rawPath);
        $screen = '<div class="pvq-screen pvq-dark"><img class="pvq-img" src="'
            . htmlspecialchars($url, ENT_QUOTES) . '" style="transform:scale(' . $this->zoomCss($zoom) . ')"></div>';
        $this->emitFramedPage($rawPath, $device, $screen);
    }

    private function frameCss(): string
    {
        return 'html,body{margin:0;height:100%;background:#0d1420;}'
            . 'body{display:flex;align-items:center;justify-content:center;overflow:hidden;}'
            . '#pvq-wrap{display:flex;flex-direction:column;align-items:center;transform-origin:center center;}'
            . '.pvq-shell{position:relative;background:#0a1018;border:1px solid #2c3d5c;display:flex;flex-direction:column;'
            . 'box-shadow:0 0 0 3px #0d1420,0 26px 60px -18px rgba(0,0,0,.8);}'
            . '.pvq-shell[data-device="tablet"]{border-radius:20px;padding:14px;}'
            . '.pvq-shell[data-device="desktop"]{border-radius:8px;padding:8px 8px 18px;}'
            . '.pvq-screen{background:#fff;overflow:hidden;position:relative;}'
            . '.pvq-screen.pvq-dark{background:#1a1a2e;}'
            . '.pvq-shell[data-device="tablet"] .pvq-screen{width:768px;height:1024px;border-radius:6px;}'
            . '.pvq-shell[data-device="desktop"] .pvq-screen{width:1024px;height:768px;border-radius:4px;}'
            . '.pvq-screen iframe{width:100%;height:100%;border:0;display:block;background:#fff;}'
            . '.pvq-img{position:absolute;inset:0;margin:auto;max-width:96%;max-height:96%;}'
            . '.pvq-stand{width:46px;height:26px;background:linear-gradient(#2c3d5c,#1a2537);border-radius:0 0 6px 6px;}'
            . '.pvq-base{width:170px;height:10px;border-radius:99px;background:linear-gradient(#2b3a55,#1f2c44);'
            . 'box-shadow:0 8px 16px rgba(0,0,0,.45);}'
            . '.pvq-shell[data-device="phone"]{border-radius:0;padding:0;border:none;background:transparent;box-shadow:none;'
            . 'position:fixed;top:0;left:0;right:0;bottom:0;width:100%;height:100%;overflow:hidden;}'
            . '.pvq-shell[data-device="phone"] .pvq-screen{position:absolute;top:0;left:0;width:' . self::PHONE_DESIGN_W . 'px;height:100%;border-radius:0;transform-origin:top left;}'
            . '.pvq-loader{position:absolute;z-index:6;display:flex;align-items:center;justify-content:center;background:#0d1420;transition:opacity .3s ease;}'
            . '.pvq-shell[data-device="phone"] .pvq-loader{inset:0;}'
            . '.pvq-shell[data-device="tablet"] .pvq-loader{inset:14px;border-radius:6px;}'
            . '.pvq-shell[data-device="desktop"] .pvq-loader{top:8px;left:8px;right:8px;height:768px;border-radius:4px;}'
            . '.pvq-loader.gone{opacity:0;pointer-events:none;}'
            . '.pvq-spinner{width:36px;height:36px;border:3px solid #263450;border-top-color:#f5a524;border-radius:50%;animation:pvqSpin .8s linear infinite;}'
            . '@keyframes pvqSpin{to{transform:rotate(360deg)}}'
            . '.pvq-home{transition:opacity .4s ease;}'
            . '.pvq-home.pvq-dim{opacity:.22;}'
            . '.pvq-home{position:fixed;z-index:2147483647;width:48px;height:48px;'
            . 'right:max(14px,env(safe-area-inset-right));bottom:calc(max(14px,env(safe-area-inset-bottom)) + 6px);'
            . 'border:none;border-radius:50%;padding:0;margin:0;'
            . 'background:rgba(13,20,32,.86);color:var(--accent,#f5a524);'
            . 'font-size:24px;line-height:46px;text-align:center;font-family:system-ui,sans-serif;'
            . 'box-shadow:0 4px 16px rgba(0,0,0,.45),inset 0 0 0 1px rgba(245,165,36,.35);cursor:pointer;'
            . '-webkit-tap-highlight-color:transparent;user-select:none;-webkit-user-select:none;touch-action:manipulation;}';
    }

    private function fitScript(string $device): string
    {
        if ($device === 'phone') {
            /* ★ PARITY with the in-IDE preview: lay the page out at the fixed
               390 px design width, then scale it to the real screen width.
               Height is extended so the scaled page fills the screen exactly. */
            return '<script>(function(){var sh=document.getElementById("pvq-shell");if(!sh)return;'
                . 'var scr=sh.querySelector(".pvq-screen");if(!scr)return;var W=' . self::PHONE_DESIGN_W . ';'
                . 'function vp(){var w=window.innerWidth||document.documentElement.clientWidth||390;'
                . 'var h=window.innerHeight||document.documentElement.clientHeight||700;'
                . 'if(document.documentElement.clientWidth>w)w=document.documentElement.clientWidth;'
                . 'if(document.documentElement.clientHeight>h)h=document.documentElement.clientHeight;'
                . 'if(window.visualViewport){if(window.visualViewport.width>w)w=window.visualViewport.width;'
                . 'if(window.visualViewport.height>h)h=window.visualViewport.height;}'
                . 'return{w:w,h:h};}'
                . 'function fit(){var v=vp();var s=v.w/W;'
                . 'sh.style.top="0";sh.style.left="0";sh.style.width=v.w+"px";sh.style.height=v.h+"px";'
                . 'scr.style.width=W+"px";'
                . 'scr.style.height=Math.ceil((v.h+2)/s)+"px";'
                . 'scr.style.transform="scale("+s+")";}'
                . 'window.addEventListener("resize",fit);window.addEventListener("orientationchange",fit);'
                . 'window.addEventListener("load",fit);'
                . 'if(window.visualViewport)window.visualViewport.addEventListener("resize",fit);'
                . 'fit();setTimeout(fit,120);setTimeout(fit,400);})();</script>';
        }
        return '<script>(function(){var w=document.getElementById("pvq-wrap");'
            . 'function fit(){w.style.transform="";var ow=w.offsetWidth,oh=w.offsetHeight;'
            . 'var s=Math.min((window.innerWidth-48)/ow,(window.innerHeight-48)/oh,1);'
            . 'w.style.transform=s<1?("scale("+s+")"):"";}'
            . 'window.addEventListener("resize",fit);fit();})();</script>';
    }

    /** ★ Hides the spinner as soon as the pop-out's iframe/image actually loads. */
    private function loaderScript(): string
    {
        return '<script>(function(){var l=document.getElementById("pvq-loader");if(!l)return;'
            . 'function gone(){l.classList.add("gone");}'
            . 'var els=document.querySelectorAll("#pvq-shell iframe,#pvq-shell img");'
            . 'for(var i=0;i<els.length;i++){(function(el){'
            . 'if(el.tagName==="IMG"){if(el.complete){gone();}else{el.addEventListener("load",gone);el.addEventListener("error",gone);}}'
            . 'else{el.addEventListener("load",gone);el.addEventListener("error",gone);}'
            . '})(els[i]);}'
            . 'setTimeout(gone,15000);})();</script>';
    }

    private function homeButtonHtml(): string
    {
        /* ★ Inside the Android app the native HOME chip already floats over
           the pop-out — the web button would only duplicate it. */
        if (isset($_GET['quirky_app'])) return '';
        return '<button type="button" id="pvq-home" class="pvq-home" aria-label="Back to IDE">&#x2302;</button>'
            . '<script>(function(){var b=document.getElementById("pvq-home");if(!b)return;'
            . 'b.addEventListener("click",function(){'
            . 'try{var q=location.search.replace(/^\\?/,"").split("&").filter(function(p){return /^(quirky_app|device_tier)=/.test(p);}).join("&");'
            . 'var base="index.php"+(q?"?"+q:"");'
            . 'if(window.history&&history.length>1){history.back();}else{location.replace(base);}}catch(e){location.replace("index.php");}'
            . '});'
            /* ★ idle fade: dim after 3 s without interaction, wake on any touch */
            . 'var t;function dim(){b.classList.add("pvq-dim");}'
            . 'function wake(){b.classList.remove("pvq-dim");clearTimeout(t);t=setTimeout(dim,3000);}'
            . '["touchstart","pointerdown","mousemove","scroll"].forEach(function(ev){window.addEventListener(ev,wake,{passive:true});});'
            . 'wake();})();</script>';
    }

    private const NAV_GUARD_JS = <<< 'JS'
(function() {
if(window.__pvNavGuard) return;window.__pvNavGuard=true;
function norm(id){return String(id||"").toLowerCase().replace(/-+/g,"-").replace(/^-+|-+$/g,"");}
function findTarget(id){var el=document.getElementById(id);if(el)return el;var n=norm(id);if(!n)return null;var cs=document.querySelectorAll("[id]");for(var i=0;i<cs.length;i++){if(norm(cs[i].id)===n)return cs[i];}return null;}
document.addEventListener("click",function(e){
var a=(e.target&&e.target.closest)?e.target.closest("a"):null;if(!a)return;
var h=a.getAttribute("href")||"";
if(h.charAt(0)==="#"){e.preventDefault();var el=findTarget(h.slice(1));if(el)el.scrollIntoView({behavior:"smooth",block:"start"});return;}
var url=a.href||"";if(!url){e.preventDefault();return;}
if(/^(mailto:|tel:|sms:|geo:)/i.test(url))return;
var isWs=url.indexOf("workspace=")!==-1;var isPv=url.indexOf("api=preview-render")!==-1;
if(isWs||isPv)return;
if(/^https?:\/\//i.test(url)){if(!a.getAttribute("target"))a.setAttribute("target","_blank");a.setAttribute("rel","noopener");return;}
e.preventDefault();e.stopPropagation();
},true);})();
JS;

    private function emitFramedPage(string $title, string $device, string $screenHtml): void
    {
        $safeTitle = htmlspecialchars(basename($title), ENT_QUOTES);
        $stand = ($device === 'desktop') ? '<div class="pvq-stand"></div><div class="pvq-base"></div>' : '';
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">'
            . '<title>' . $safeTitle . ' — Quirky IDE</title>'
            . '<style>' . $this->frameCss() . '</style></head><body>'
            . '<div id="pvq-wrap"><div id="pvq-shell" class="pvq-shell" data-device="' . htmlspecialchars($device, ENT_QUOTES) . '">'
            . '<div class="pvq-loader" id="pvq-loader"><div class="pvq-spinner"></div></div>'
            . $screenHtml
            . '</div>' . $stand . '</div>'
            . $this->fitScript($device)
            . $this->loaderScript()
            . $this->homeButtonHtml()
            . '</body></html>';
        exit;
    }

    private function emitMarkdownPopOut(string $rawPath, string $markdownContent, string $device, float $zoom): void
    {
        $safeJson = json_encode(
            $markdownContent,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP
        );
        $fileName = basename($rawPath);
        $safeName = htmlspecialchars($fileName, ENT_QUOTES);
        $jsName   = str_replace(['\\', '"'], ['\\\\', '\\"'], $fileName);
        $stand = ($device === 'desktop') ? '<div class="pvq-stand"></div><div class="pvq-base"></div>' : '';
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>' . $safeName . ' — Quirky IDE</title>'
            . '<style>' . $this->frameCss() . '</style></head><body>'
            . '<div id="pvq-wrap"><div id="pvq-shell" class="pvq-shell" data-device="' . htmlspecialchars($device, ENT_QUOTES) . '">'
            . '<div class="pvq-screen"><iframe id="pvq-frame" title="Markdown preview"></iframe></div>'
            . '</div>' . $stand . '</div>'
            . $this->fitScript($device)
            . $this->homeButtonHtml()
            . '<script>var _qmd=' . $safeJson . ';</script>'
            . '<script src="index.php?asset=js/markdown.js"></script>'
            . '<script>(function(){'
            . 'var md=window.IDE&&window.IDE.markdown;'
            . 'if(!md||!md.toHtml){document.body.style.overflow="auto";document.body.style.padding="2rem";'
            . 'document.body.style.fontFamily="monospace";document.body.style.whiteSpace="pre-wrap";'
            . 'document.body.textContent=_qmd;return;}'
            . 'var title=(md.deriveTitle)?md.deriveTitle(_qmd,"' . $jsName . '"):"' . $jsName . '";'
            . 'document.title=title+" — Quirky IDE";'
            . 'var html=md.wrap(md.toHtml(_qmd),title);'
            . 'html=html.replace("</head>","<style>html{zoom:' . $this->zoomCss($zoom) . ';}</style></head>");'
            . 'html=html.replace("</body>","<script src=\\"index.php?asset=js/preview-nav-guard.js\\"><\/script></body>");'
            . 'document.getElementById("pvq-frame").srcdoc=html;'
            . '})();</script></body></html>';
        exit;
    }
}
