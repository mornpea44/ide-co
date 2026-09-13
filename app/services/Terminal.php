<?php
// File: Terminal.php
declare(strict_types=1);

use PHPUnit\Framework\MockObject\Rule\Parameters;

/**
 * ═══════════════════════════════════════════════════════════════════════════
 *  QUIRKY IDE — TERMINAL ENGINE (Performance & Stability Pass)
 * ═══════════════════════════════════════════════════════════════════════════
 *
 *  This is the command runner. It executes shell commands typed in the IDE's
 *  terminal panel and returns their output.
 *
 *  ⚠️  SECURITY REALITY CHECK (please read):
 *  ─────────────────────────────────────────
 *  A terminal runs with YOUR user account's full permissions. There is no
 *  way to perfectly sandbox a shell in plain PHP. We mitigate risk with:
 *
 *    1. DISABLED BY DEFAULT — must be explicitly enabled in config.php
 *    2. A blocklist of catastrophic commands (rm -rf /, format c:, etc.)
 *    3. Privilege-escalation blocking (sudo, su, runas)
 *    4. Directory-escape blocking (cd /, cd .., cd \)
 *    5. A hard timeout that kills runaway commands
 *    6. Output size cap so the browser can't freeze
 *
 *  This is safe for your own machine. NEVER enable this on a public server.
 *
 *  PERSISTENT WORKING DIRECTORY ("cd finally works"):
 *  ──────────────────────────────────────────────────
 *  Every command still runs in its own fresh shell process (that's why `cd`
 *  used to be forgotten instantly — the shell died before the next command).
 *  Now, after each command we secretly ask the shell "which folder did you
 *  end up in?" (a hidden marker + `cd`/`pwd` appended to the command),
 *  remember that folder in a tiny per-session file, and start the NEXT
 *  command there. The marker + its answer line are stripped from the output
 *  before it ever reaches your screen.
 *
 *  Safety: the remembered folder is ALWAYS validated to be inside the
 *  workspace — even if a command somehow ended up outside, we simply don't
 *  remember it, so the persistent location can never escape the sandbox.
 *
 *  ─────────────────────────────────────────────────────────────────────────
 *  PERFORMANCE & STABILITY PASS — what changed and why
 *  ─────────────────────────────────────────────────────────────────────────
 *   1. EFFICIENT WAITING: the old code checked "is it done yet?" every 10ms
 *      in a tight loop the entire time a command ran (a "busy wait"), which
 *      burns CPU/battery for no reason. It now uses stream_select(), which
 *      sleeps the PHP process until there's actually output to read (or a
 *      short ceiling elapses so we can still notice a timeout / cancellation
 *      promptly). Same responsiveness, far less wasted work.
 *   2. BOUNDED MEMORY: output is now trimmed to the size cap AS IT ARRIVES,
 *      not after the whole command finishes. A runaway command that prints
 *      forever (e.g. `yes`) can no longer balloon memory usage before being
 *      cut off.
 *   3. NO ORPHANED PROCESSES: if the browser/app disconnects mid-command
 *      (tab closed, app backgrounded, network drop during a streamed pkg
 *      install), the spawned process is now detected and killed instead of
 *      being left running unattended in the background — this was the
 *      biggest real "leak" risk, since an orphaned process keeps using
 *      battery/data with nobody watching it.
 *   4. SAFETY-NET CLEANUP: a shutdown handler guarantees the child process,
 *      temp files, and PID file are cleaned up no matter how the request
 *      ends (crash, timeout, disconnect) — not just on the "happy path".
 *   5. HONEST LIMITATION: this still uses plain pipes, not a PTY. Simple
 *      commands, scripts, and non-interactive tools (git, python script.py,
 *      pip install, node app.js, curl, etc.) work great. Full-screen
 *      interactive programs (vim, top, a bare `python` REPL waiting for
 *      keystrokes) will not behave like a real interactive terminal — that
 *      would require a pseudo-terminal, which isn't reliably available
 *      across Android PHP builds. Nothing here pretends otherwise.
 *
 * ═══════════════════════════════════════════════════════════════════════════
 */
class IdeTerminal implements IdeTerminalInterface
{
    /** @var array The full config from config.php */
    private array $config;

    /** @var IdeSecurity The security guard (for fail() responses + sessions) */
    private IdeSecurity $security;

    /** @var string Absolute path to the workspace folder (where commands run) */
    private string $workspaceRoot;

    /** @var bool Whether we're on Windows (Laragon) or Unix */
    private bool $isWindows;

    /** @var string Path of the per-session "remembered folder" file */
    private string $cwdFile = '';

    /** Phase B: the Termux-style toolbox folder (files/usr). */
    private string $prefix = '';
    private string $binDir = '';
    private string $libDir = '';

    /**
     * Cached copy of the parent process's environment variables.
     * Reading getenv() with no arguments re-scans the whole environment
     * table every time it's called. We only need it once per request,
     * so we grab it in the constructor instead of on every single
     * command execution.
     */
    private array $baseEnv = [];

    /**
     * Maximum output size (in characters) returned to the browser.
     * Prevents the browser from freezing on commands with huge output.
     * 100,000 chars ≈ 100 KB — more than enough for any normal command.
     */
    private const MAX_OUTPUT_CHARS = 100000;

    /**
     * Ceiling (in seconds, may be fractional) on each stream_select() wait.
     * We don't wait indefinitely for output even though stream_select can
     * block efficiently — capping each wait keeps us checking the overall
     * timeout and "did the connection drop?" at least this often, so
     * cancellation/timeout detection still feels instant.
     */
    private const SELECT_CEILING_SEC = 0.2;

    /**
     * How often (in microseconds) we poll after proc_terminate() while
     * waiting for a killed process to actually exit. This is a short,
     * bounded wait — not a busy loop over the command's whole runtime.
     */
    private const TERMINATE_GRACE_US = 50000;

    /**
     * ★ STABILITY PASS — HEARTBEAT INTERVAL (seconds).
     * connection_aborted() only flips once PHP WRITES to the dead socket.
     * A silent compile produces no writes, so a dropped tab used to go
     * unnoticed for the whole timeout. We now emit a harmless SSE 'ping'
     * event at least this often (the frontend's readSSEStream drops
     * unknown events by contract), forcing a socket write and making
     * abort detection accurate within ~2 s even with zero output.
     */
    private const HEARTBEAT_SEC = 2.0;

    /**
     * ★ STABILITY PASS — STDIN MAILBOX CAP (bytes).
     * Pasting megabytes into a program that isn't reading must not grow
     * the mailbox file forever. Beyond 64 KB sendStdin() refuses with
     * sent:false / 'buffer full' (the frontend keeps the text in the box).
     */
    private const STDIN_MAILBOX_MAX = 65536;

    /**
     * ★ STABILITY PASS — SEPARATE OUTPUT BUDGETS.
     * out and err each get their own MAX_OUTPUT_CHARS budget so chatty
     * stderr (verbose compilers) can no longer starve stdout down to
     * "[output truncated]" while real results were still streaming.
     */

    /** Resolved shell for Unix spawns (see resolveShell()). */
    private string $shellPath = '/bin/sh';
    /** Extra argv prefix when the resolved "shell" is busybox ('sh' arg). */
    private array $shellArgvPrefix = [];

    public function __construct(array $config, IdeSecurity $security)
    {
        $this->config   = $config;
        $this->security = $security;
        $this->isWindows = (PHP_OS_FAMILY === 'Windows');

        $envSnapshot = @getenv();
        $this->baseEnv = is_array($envSnapshot) ? $envSnapshot : [];

        // Resolve the true physical path of the workspace.
        // This is the folder every command will run inside.
        $realRoot = realpath(IDE_WORKSPACE);
        if ($realRoot === false) {
            mkdir(IDE_WORKSPACE, 0777, true);
            $realRoot = realpath(IDE_WORKSPACE);
        }
        $this->workspaceRoot = str_replace('\\', '/', (string) $realRoot);

        // Phase B: locate the package toolbox. MainActivity passes its path
        // via the QUIRKY_PREFIX environment variable. On the desktop there is
        // no such folder, so we fall back to a local directory.
        $prefix = $this->baseEnv['QUIRKY_PREFIX'] ?? getenv('QUIRKY_PREFIX');
        if ($prefix === false || $prefix === '' || $prefix === null) {
            $prefix = IDE_ROOT . '/.usr';
        }
        $this->prefix = rtrim(str_replace('\\', '/', (string) $prefix), '/');
        $this->binDir = $this->prefix . '/bin';
        $this->libDir = $this->prefix . '/lib';

        // ★ HOTFIX (B-1.1): make "php" runnable in the terminal.
        $this->ensurePhpWrapper();

        // ★ STABILITY PASS: resolve a shell that actually exists on THIS
        // device (stock Android has no /bin/sh — only /system/bin/sh, or
        // busybox inside our prefix). Used by BOTH spawn sites and
        // reported honestly by getInfo().
        $this->resolveShell();
    }

    /**
     * ★ STABILITY PASS — SHELL RESOLUTION.
     * Old code hardcoded ['/bin/sh','-c',…] which fails to spawn at all on
     * devices where ptyrun is unavailable and no /bin/sh exists. We now pick
     * the first existing executable from:
     *   1. /bin/sh          (desktop Linux / proot environments)
     *   2. /system/bin/sh   (every stock Android)
     *   3. $PREFIX/bin/busybox with argument 'sh' (bundled toolbox)
     * The choice is stored in $shellPath / $shellArgvPrefix and used in both
     * run() and runStream(); getInfo() reports the resolved path.
     */
    private function resolveShell(): void
    {
        if ($this->isWindows) {
            return; // cmd.exe path never uses these fields
        }
        $candidates = ['/bin/sh', '/system/bin/sh'];
        foreach ($candidates as $sh) {
            if (@is_file($sh) && (@is_executable($sh) || @chmod($sh, 0755))) {
                $this->shellPath = $sh;
                $this->shellArgvPrefix = [];
                return;
            }
        }
        // Last resort: the bundled busybox applet launcher.
        $busybox = $this->binDir . '/busybox';
        if (@is_file($busybox)) {
            if (!@is_executable($busybox)) {
                @chmod($busybox, 0755);
            }
            $this->shellPath = $busybox;
            $this->shellArgvPrefix = ['sh']; // busybox sh -c …
            return;
        }
        // Nothing found — keep the historical default so behaviour
        // degrades exactly as before instead of failing differently.
        $this->shellPath = '/bin/sh';
        $this->shellArgvPrefix = [];
    }

    /** Build the proc_open argv for "<shell> -c <cmd>" using the resolved shell. */
    private function shellCommand(string $inner): array
    {
        return array_merge(
            [$this->shellPath],
            $this->shellArgvPrefix,
            ['-c', $inner]
        );
    }

    /* ═══════════════════════════════════════════════════════════════
       PER-SESSION PID FILE
       ═══════════════════════════════════════════════════════════════
       Each browser session gets its own PID file so concurrent
       commands (or two tabs) can never overwrite each other's PID.  */

    private function pidFilePath(): string
    {
        $sid = session_id();
        if ($sid === false || $sid === '') {
            // Fallback if no session is active (shouldn't happen in normal use)
            return sys_get_temp_dir() . '/quirky_ide_terminal.pid';
        }
        return sys_get_temp_dir() . '/quirky_term_' . $sid . '.pid';
    }

    /* ═══════════════════════════════════════════════════════════════
       ★ STABILITY PASS — PER-RUN NONCE + SINGLE-FLIGHT LOCK
       ═══════════════════════════════════════════════════════════════
       The pid file now stores a small JSON record {pid,nonce,cmd} instead
       of a bare integer. Each run mints a nonce that travels to the
       frontend in the SSE 'started' event; stdin frames echo it back
       (see sendStdin) so a stale tab can never type into a newer command,
       and cancel()/resizePty() accept an optional nonce parameter for the
       same check (the current routes don't pass one yet — when absent we
       act on the current run, exactly as before).

       A per-session lock file (quirky_term_<sid>.lock) makes runs
       single-flight: a second concurrent runStream/run fails fast with a
       friendly "a command is already running" instead of silently sharing
       the pid file, the stdin mailbox and the PTY with the first command.
                                                                   */

    /** Mint a fresh per-run nonce. */
    private function newNonce(): string
    {
        try {
            return bin2hex(random_bytes(4));
        } catch (\Throwable $e) {
            return substr(md5((string) mt_rand()), 0, 8);
        }
    }

    /**
     * Write the per-session pid record as JSON ({pid,nonce,cmd}).
     */
    private function writePidRecord(int $pid, string $nonce, string $command): void
    {
        @file_put_contents(
            $this->pidFilePath(),
            json_encode(['pid' => $pid, 'nonce' => $nonce, 'cmd' => $command], JSON_UNESCAPED_SLASHES),
            LOCK_EX
        );
    }

    /**
     * Read back the pid record. Tolerates the legacy bare-integer format.
     * @return array{pid:int,nonce:string,cmd:string}|null
     */
    private function readPidRecord(): ?array
    {
        $path = $this->pidFilePath();
        if (!is_file($path)) {
            return null;
        }
        $raw = trim((string) @file_get_contents($path));
        if ($raw === '') {
            return null;
        }
        $rec = json_decode($raw, true);
        if (is_array($rec) && isset($rec['pid'])) {
            return [
                'pid'   => (int) $rec['pid'],
                'nonce' => (string) ($rec['nonce'] ?? ''),
                'cmd'   => (string) ($rec['cmd'] ?? ''),
            ];
        }
        if (ctype_digit($raw)) { // legacy format written by an older build
            return ['pid' => (int) $raw, 'nonce' => '', 'cmd' => ''];
        }
        return null;
    }

    /**
     * Delete the pid file ONLY if it still belongs to our own run.
     * Without this guard a finishing request's shutdown handler could
     * unlink a NEWER command's pid record (both live in the same session).
     */
    private function unlinkPidRecordIfOurs(string $nonce): void
    {
        $rec = $this->readPidRecord();
        if ($rec !== null && $nonce !== '' && $rec['nonce'] !== $nonce) {
            return; // a newer run owns the file now — leave it alone
        }
        @unlink($this->pidFilePath());
    }

    /** Path of the per-session single-flight lock file. */
    private function runLockPath(): string
    {
        $sid = session_id();
        return sys_get_temp_dir() . '/quirky_term_'
            . (($sid !== false && $sid !== '') ? $sid : 'default') . '.lock';
    }

    /**
     * Try to take the per-session run lock WITHOUT blocking.
     * Returns the flock handle (keep it until releaseRunLock), or null
     * when another command in this session is already running.
     *
     * @return resource|null
     */
    private function acquireRunLock()
    {
        $fp = @fopen($this->runLockPath(), 'c');
        if (!$fp) {
            return null; // can't create the lock → behave unlocked rather than dead
        }
        if (!flock($fp, LOCK_EX | LOCK_NB)) {
            fclose($fp);
            return null;
        }
        return $fp;
    }

    /**
     * Release the per-session run lock.
     *
     * @param resource|null $lockHandle
     */
    private function releaseRunLock($lockHandle): void
    {
        if (is_resource($lockHandle)) {
            @flock($lockHandle, LOCK_UN);
            @fclose($lockHandle);
        }
    }

    /* ═══════════════════════════════════════════════════════════════
    INTERACTIVE STDIN — lets running programs receive typed input
    ═══════════════════════════════════════════════════════════════
    A running command keeps its stdin pipe OPEN. The browser sends
    keystrokes to sendStdin(), which appends them to a small per-session
    "mailbox" file. The streaming loop reads that mailbox on every wake-up
    (see runStream) and pours the text into the command's stdin pipe.   */
    private function stdinFilePath(): string
    {
        $sid = session_id();
        if ($sid === false || $sid === '') {
            return sys_get_temp_dir() . '/quirky_ide_terminal.stdin';
        }
        return sys_get_temp_dir() . '/quirky_stdin_' . $sid . '.in';
    }

    /**
     * Append user input to the running command's stdin mailbox.
     * Called by the new ?api=terminal-stdin endpoint while a command runs.
     *
     * ★ STABILITY PASS:
     *  - The frontend may prefix the payload with a per-run nonce marker
     *    ("\x00Q<nonce>\x00", stripped here before the bytes reach the
     *    program). A stale UI (old tab steering a NEWER command) fails the
     *    check and its keystrokes are dropped instead of typed into the
     *    wrong program.
     *  - The mailbox is capped at STDIN_MAILBOX_MAX; beyond it we refuse
     *    with sent:false / 'buffer full' so a giant paste can't balloon
     *    the temp file (the program will drain what's already queued).
     */
    public function sendStdin(string $input): array
    {
        if (!$this->isEnabled()) {
            $this->security->fail(
                'Terminal is disabled. Enable it in config.php to send input.',
                403
            );
        }
        // Resolve the mailbox + PID paths while the session is active so they
        // match the ones runStream() used when the command started.
        $this->security->startSession();
        $stdinFile = $this->stdinFilePath();
        $pidFile   = $this->pidFilePath();
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        // Only accept input when a command is actually running.
        if (!is_file($pidFile)) {
            return ['sent' => false, 'reason' => 'No command is currently running.'];
        }

        // ★ Per-run nonce verification (marker embedded by the frontend).
        $nonce = null;
        if (strncmp($input, "\x00Q", 2) === 0) {
            $end = strpos($input, "\x00", 2);
            if ($end === false) {
                return ['sent' => false, 'reason' => 'Malformed input frame.'];
            }
            $nonce = substr($input, 2, $end - 2);
            $input = (string) substr($input, $end + 1);
            $rec = $this->readPidRecord();
            if ($rec === null) {
                return ['sent' => false, 'reason' => 'No command is currently running.'];
            }
            if ($rec['nonce'] !== '' && $nonce !== $rec['nonce']) {
                return ['sent' => false, 'reason' => 'Stale terminal — this tab no longer owns the running command.'];
            }
        }
        if ($input === '') {
            return ['sent' => true];
        }

        // ★ Backpressure cap: refuse once the mailbox already holds 64 KB.
        clearstatcache(true, $stdinFile);
        $pending = @filesize($stdinFile);
        if ($pending !== false && $pending >= self::STDIN_MAILBOX_MAX) {
            return ['sent' => false, 'reason' => 'Input buffer full — the program is not reading right now.'];
        }

        $fp = @fopen($stdinFile, 'a');
        if (!$fp) {
            return ['sent' => false, 'reason' => 'Could not open the stdin channel.'];
        }
        if (flock($fp, LOCK_EX)) {
            fwrite($fp, $input);
            fflush($fp);
            flock($fp, LOCK_UN);
        }
        fclose($fp);
        return ['sent' => true];
    }

    /**
     * Cheap check: does the mailbox hold undelivered bytes?
     * (stat only — no open/lock; used to decide whether the select loop
     * should include the stdin pipe in its WRITE set.)
     */
    private function pendingStdinWaiting(): bool
    {
        $stdinFile = $this->stdinFilePath();
        clearstatcache(true, $stdinFile);
        return @filesize($stdinFile) > 0;
    }

    /**
     * Pour pending mailbox text into the command's stdin pipe — WITHOUT
     * ever blocking. The stdin pipe is non-blocking (see runStream), so a
     * program that isn't reading can no longer deadlock the whole SSE loop:
     * fwrite() now writes exactly what fits in the kernel pipe buffer and
     * returns; whatever didn't fit is written BACK to the mailbox and stays
     * there until select() reports the pipe writable again. Returns true
     * when at least one byte was injected (used to reset the timeout).
     *
     * @param resource $stdinPipe
     */
    private function injectPendingStdin($stdinPipe): bool
    {
        $stdinFile = $this->stdinFilePath();
        if (!is_file($stdinFile)) {
            return false;
        }
        $fp = @fopen($stdinFile, 'r+');
        if (!$fp) {
            return false;
        }
        $injected = false;
        if (flock($fp, LOCK_EX)) {
            $content = (string) stream_get_contents($fp);
            if ($content !== '') {
                $written = @fwrite($stdinPipe, $content);
                if ($written === false || $written <= 0) {
                    $written = 0;               // pipe full right now → keep everything queued
                } else {
                    $injected = true;
                }
                $rest = substr($content, $written);
                if ($rest !== '') {
                    ftruncate($fp, 0);
                    rewind($fp);
                    fwrite($fp, $rest);         // chunk stays in the mailbox for the next wake-up
                } else {
                    ftruncate($fp, 0);
                }
                fflush($fp);
            }
            flock($fp, LOCK_UN);
        }
        fclose($fp);
        return $injected;
    }

    /* ═══════════════════════════════════════════════════════════════
       PERSISTENT WORKING DIRECTORY — remembers `cd` between commands
       ═══════════════════════════════════════════════════════════════
       The remembered folder lives in a tiny text file keyed by your
       browser session. We deliberately do NOT keep the PHP session
       open while a command runs — that would block the rest of the
       IDE (and the Cancel button) for the whole duration.           */

    /**
     * ★ STABILITY PASS — DURABLE CWD FALLBACK.
     * The session-keyed file dies whenever Android mints a new session id
     * or purges the cache dir (app restart). A second copy lives in the
     * workspace-adjacent IDE_ROOT folder, which survives both. Read order:
     * session file first (fast path), durable file as fallback; writes go
     * to BOTH. Same workspace validation applies to either source.
     */
    private function durableCwdFile(): string
    {
        return IDE_ROOT . '/.terminal-cwd.txt';
    }

    /**
     * Validate a workspace-relative cwd exactly like before: no NULs, no
     * '..', not absolute, still exists, and realpath stays inside ROOT.
     */
    private function validateRelCwd(string $rel): bool
    {
        if ($rel === '') {
            return true;
        }
        if (strpos($rel, "\0") !== false || strpos($rel, '..') !== false || $rel[0] === '/') {
            return false;
        }
        $abs = $this->workspaceRoot . '/' . $rel;
        if (!is_dir($abs)) {
            return false;                    // folder deleted meanwhile → back to root
        }
        $real = realpath($abs);
        if ($real === false) {
            return false;
        }
        $realNorm = str_replace('\\', '/', $real);
        $wsNorm   = rtrim($this->workspaceRoot, '/');
        if ($realNorm !== $wsNorm && strpos($realNorm, $wsNorm . '/') !== 0) {
            return false;                    // somehow escaped the workspace → reset
        }
        return true;
    }

    /**
     * Read the remembered working directory (relative to the workspace,
     * '' = workspace root). Opens the session only long enough to get
     * your session id, then closes it again right away.
     */
    private function getStoredCwd(): string
    {
        $this->security->startSession();
        $sid = session_id();
        $this->cwdFile = sys_get_temp_dir() . '/quirky_term_cwd_'
            . (($sid !== '' && $sid !== false) ? $sid : 'default') . '.txt';
        session_write_close();

        $rel = is_file($this->cwdFile)
            ? trim((string) @file_get_contents($this->cwdFile))
            : '';

        // ★ Durable fallback: after an app restart the per-session file is
        // gone, but the user's location shouldn't be. Try IDE_ROOT's copy.
        if ($rel === '' && !$this->isWindows && is_file($this->durableCwdFile())) {
            $rel = trim((string) @file_get_contents($this->durableCwdFile()));
        }

        if (!$this->validateRelCwd($rel)) {
            return '';
        }
        return $rel;
    }

    /**
     * Save the working directory for the next command — to BOTH the fast
     * per-session file and the durable copy.
     */
    private function setStoredCwd(string $rel): void
    {
        if ($this->cwdFile === '') {
            $this->getStoredCwd();          // safety net: initialise the file path
        }
        @file_put_contents($this->cwdFile, $rel, LOCK_EX);
        // Durable mirror survives session-id churn + cache purges. Only on
        //-device (Unix); on desktop Laragon sessions are stable anyway.
        if (!$this->isWindows) {
            @file_put_contents($this->durableCwdFile(), $rel, LOCK_EX);
        }
    }

    /**
     * Turn an absolute directory (reported by the shell after a command
     * ran) into a validated workspace-relative path. Returns NULL when
     * the directory is OUTSIDE the workspace — the caller must simply
     * NOT remember that.
     */
    private function absToWorkspaceRel(string $absCwd): ?string
    {
        $real = @realpath($absCwd);
        if ($real === false) {
            return null;
        }
        $realNorm = str_replace('\\', '/', $real);
        $wsNorm   = rtrim($this->workspaceRoot, '/');
        if ($realNorm === $wsNorm) {
            return '';
        }
        if (strpos($realNorm, $wsNorm . '/') === 0) {
            return substr($realNorm, strlen($wsNorm) + 1);
        }
        return null;
    }

    /**
     * Create a short-lived temp file used to capture the working directory.
     */
    private function makeCwdTempFile(): string
    {
        $tmp = @tempnam(sys_get_temp_dir(), 'qcwd_');
        if ($tmp === false) {
            $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'qcwd_' . bin2hex(random_bytes(6));
        }
        return $tmp;
    }

    /**
     * Read the folder the command ended in (from the temp file), convert it
     * to a workspace-relative path, and delete the temp file.
     * Returns null when nothing usable was captured.
     */
    private function readCwdTempFile(string $path): ?string
    {
        $reported = '';
        if ($path !== '' && is_file($path)) {
            $reported = trim((string) @file_get_contents($path));
            @unlink($path);
        }
        if ($reported === '') {
            return null;
        }
        return $this->absToWorkspaceRel($reported);
    }

    /**
     * Delete the cwd temp file (used when a command fails to start, or
     * during safety-net cleanup).
     */
    private function removeCwdTempFile(string $path): void
    {
        if ($path !== '' && @is_file($path)) {
            @unlink($path);
        }
    }

    /* ═══════════════════════════════════════════════════════════════
       IS THE TERMINAL ENABLED?
       ═══════════════════════════════════════════════════════════════
       The terminal is OFF by default. The user must flip
       'terminal_enabled' => true in config.php to use it.            */

    public function isEnabled(): bool
    {
        return !empty($this->config['features']['terminal_enabled']);
    }

    /* ═══════════════════════════════════════════════════════════════
       TERMINAL INFO
       ═══════════════════════════════════════════════════════════════ */

    public function getInfo(): array
    {
        // ★ STABILITY PASS: report the shell we ACTUALLY resolved for this
        // device instead of a hardcoded '/bin/sh' that may not exist.
        if ($this->isWindows) {
            $shell = 'cmd.exe';
            $shellLabel = 'cmd.exe (Windows)';
        } else {
            $shell = $this->shellPath
                . ($this->shellArgvPrefix ? ' (' . implode(' ', $this->shellArgvPrefix) . ' applet)' : '');
            $shellLabel = basename($this->shellPath)
                . ($this->shellArgvPrefix ? ' (' . implode(' ', $this->shellArgvPrefix) . ' via busybox)' : ' (Unix)');
        }
        return [
            'enabled'     => $this->isEnabled(),
            'shell'       => $shell,
            'shellLabel'  => $shellLabel,
            'cwd'         => $this->workspaceRoot,
            'cwdRelative' => $this->getStoredCwd(),
            'phpVersion'  => PHP_VERSION,
            'os'          => PHP_OS . ' (' . PHP_OS_FAMILY . ')',
            'osFamily'    => PHP_OS_FAMILY,
            'isWindows'   => $this->isWindows,
            'timeout'     => (int) ($this->config['terminal']['timeout'] ?? 10),
            'procOpenOk'  => $this->isProcOpenAvailable(),
            'pty'         => $this->ptyKind() !== '',
        ];
    }

    /* ═══════════════════════════════════════════════════════════════
       EFFICIENT WAITING (replaces the old fixed-interval busy loop)
       ═══════════════════════════════════════════════════════════════
       stream_select() puts PHP to sleep until one of the given streams
       actually has data (or reaches EOF), or until $maxWaitSec elapses
       — whichever comes first. This uses essentially zero CPU while
       waiting, unlike calling usleep() in a tight loop the entire time
       a command is running.                                          */
    private function waitForActivity(array $pipes, float $maxWaitSec, bool $watchStdinWrite = false): void
    {
        if ($maxWaitSec <= 0) {
            return;
        }
        $read  = [$pipes[1], $pipes[2]];
        // ★ STABILITY PASS: when the stdin mailbox holds undelivered bytes,
        // include the stdin pipe in the WRITE set. select() then wakes us as
        // soon as the pipe has room again, so queued input drains without
        // any busy-polling (and a full pipe can never wedge the loop).
        $write  = ($watchStdinWrite && isset($pipes[0])) ? [$pipes[0]] : null;
        $except = null;
        $sec  = (int) floor($maxWaitSec);
        $usec = (int) round(($maxWaitSec - $sec) * 1_000_000);
        // stream_select emits warnings if interrupted by a signal on some
        // platforms — harmless here, so we suppress and just loop again.
        @stream_select($read, $write, $except, $sec, $usec);
    }

    /**
     * Append $chunk onto $buffer, but never let $buffer grow past $max
     * characters. Returns true once the cap has been hit (so the caller
     * can add a one-time "truncated" notice).
     */
    private function appendCapped(string &$buffer, string $chunk, int $max, bool $alreadyTruncated): bool
    {
        if ($chunk === '' || $alreadyTruncated) {
            return $alreadyTruncated;
        }
        $room = $max - strlen($buffer);
        if ($room <= 0) {
            return true;
        }
        if (strlen($chunk) > $room) {
            $buffer .= substr($chunk, 0, $room);
            return true;
        }
        $buffer .= $chunk;
        return false;
    }

    /**
     * Registers a "no matter what happens" cleanup for a spawned process.
     * If the request ends normally, this becomes a harmless no-op (the
     * caller will already have closed the process/files by then). If the
     * request dies unexpectedly — a PHP fatal error, the connection
     * dropping, or anything else — this still runs and makes sure we
     * don't leave an orphaned process or stray temp/PID file behind.
     *
     * @param resource $process
     */
    private function registerSafetyNetCleanup($process, string $pidFile, string $cwdTmp, string $nonce = ''): void
    {
        register_shutdown_function(function () use ($process, $pidFile, $cwdTmp, $nonce): void {
            if (is_resource($process)) {
                $status = @proc_get_status($process);
                if (is_array($status) && !empty($status['running'])) {
                    @proc_terminate($process, $this->isWindows ? 1 : 9);
                    usleep(self::TERMINATE_GRACE_US);
                }
                @proc_close($process);
            }
            // ★ Only remove the pid record when it is still OURS — a newer
            // command in this session may have written its own meanwhile.
            if ($nonce !== '') {
                $rec = $this->readPidRecord();
                if ($rec === null || $rec['nonce'] !== $nonce) {
                    // already cleaned up normally, or a newer run owns it → skip
                } else {
                    @unlink($pidFile);
                }
            } else {
                @unlink($pidFile);
            }
            $this->removeCwdTempFile($cwdTmp);
        });
    }

    /* ═══════════════════════════════════════════════════════════════
       RUN A COMMAND (one-shot)
       ═══════════════════════════════════════════════════════════════
       The main method. Takes a command string, validates it against
       the safety rules, executes it inside the remembered folder,
       and returns everything the browser needs to display the result. */
    public function run(string $command, string $stdin = ''): array
    {
        // Phase B: "pkg ..." and "apt ..." are handled by the package
        // manager, never sent to the shell.
        $pkgResult = $this->routeToPkg($command);
        if ($pkgResult !== null) {
            return $pkgResult;
        }

        // Phase 2 Workshop: "apache ..." commands route to Workshop
        $apacheResult = $this->routeToApache($command);
        if ($apacheResult !== null) {
            return $apacheResult;
        }

        if (!$this->isEnabled()) {
            $this->security->fail(
                'Terminal is disabled. To enable it, open config.php and set '
                    . "'terminal_enabled' => true in the features section.",
                403
            );
        }

        $this->checkProcOpenAvailable();

        $command = trim($command);
        if ($command === '') {
            $this->security->fail('Command cannot be empty.');
        }

        // ★ STABILITY PASS — SINGLE-FLIGHT: refuse politely when another
        // command in this session is already running instead of silently
        // sharing the pid file / stdin mailbox / PTY with it.
        $lockHandle = $this->acquireRunLock();
        if ($lockHandle === null) {
            $relCwdBusy = $this->getStoredCwd();
            return [
                'command'     => $command,
                'output'      => "⚠ A command is already running in this terminal.\nWait for it to finish (or press Cancel) before starting another one.\n",
                'error'       => '',
                'exitCode'    => 1,
                'timedOut'    => false,
                'duration'    => 0,
                'cwd'         => $this->workspaceRoot,
                'cwdRelative' => $relCwdBusy,
                'shell'       => 'terminal (busy)',
            ];
        }

        // ── Persistent working directory (read first so validation knows how deep we are) ──
        $relCwd = $this->getStoredCwd();

        $this->validateCommand($command, $relCwd);

        $nonce     = $this->newNonce();
        $timeout   = (int) ($this->config['terminal']['timeout'] ?? 10);
        @set_time_limit($timeout + 10);
        $startTime = microtime(true);

        $launchDir = $relCwd === ''
            ? $this->workspaceRoot
            : $this->workspaceRoot . '/' . $relCwd;
        if (!is_dir($launchDir)) {
            $launchDir = $this->workspaceRoot;
            $relCwd = '';
        }

        // ── Capture the final folder via a hidden temp file ──
        $cwdTmp      = $this->makeCwdTempFile();
        $finalRelCwd = $relCwd;

        if ($this->isWindows) {
            $shellCmd  = 'cmd.exe /c chcp 65001 >nul 2>&1 & ' . $command
                . ' & cd > "' . $cwdTmp . '" 2>nul';
            $shellName = 'cmd.exe';
            $childEnv = array_merge($this->baseEnv, [
                'PYTHONIOENCODING' => 'utf-8',
                'PYTHONUTF8'       => '1',
            ]);
        } else {
            $shellCmd  = $this->shellCommand($this->getLowEndShellAliases() . $this->pkgEnvPrefix() . $command . '; __quirky_exit=$?; pwd > ' . escapeshellarg($cwdTmp) . ' 2>/dev/null; exit $__quirky_exit');
            $shellName = $this->shellPath;
            $childEnv = array_merge($this->baseEnv, [
                'LANG'   => 'en_US.UTF-8',
                'LC_ALL' => 'en_US.UTF-8',
                'SHELL'  => $this->shellPath,
                // ★ TERM stays xterm-256color BY DESIGN: this release's
                // frontend emulator implements SGR 38;5/48;5 (256-colour),
                // quantized truecolor and DEC graphics, so the advertised
                // terminfo now matches what the UI can actually render.
                'TERM'   => 'xterm-256color',
                'TERMINFO' => $this->prefix . '/share/terminfo',
                'TERMINFO_DIRS' => $this->prefix . '/share/terminfo:/usr/share/terminfo:/lib/terminfo',
                'ESCDELAY' => '200', // Fixes arrow key latency for nano/vim/TUIs
            ]);
        }

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $pipes = [];
        $process = proc_open($shellCmd, $descriptors, $pipes, $launchDir, $childEnv);
        if (!is_resource($process)) {
            $this->removeCwdTempFile($cwdTmp);
            $this->releaseRunLock($lockHandle);
            $this->security->fail('Failed to start the command process.');
        }

        $pidFile = $this->pidFilePath();
        $procStatus = proc_get_status($process);
        $childPid   = (int) ($procStatus['pid'] ?? 0);
        if ($childPid > 0) {
            $this->writePidRecord($childPid, $nonce, $command);
        }

        // Safety net: guarantees this process and its temp files get
        // cleaned up even if something below throws or the request dies
        // unexpectedly. Becomes a no-op once we close things normally.
        $this->registerSafetyNetCleanup($process, $pidFile, $cwdTmp, $nonce);

        if ($stdin !== '') {
            fwrite($pipes[0], $stdin);
        }
        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $stdout   = '';
        $stderr   = '';
        $timedOut = false;
        $exitCode = -1;
        $outTruncated = false;
        $errTruncated = false;

        while (true) {
            $status = proc_get_status($process);
            if (!$status['running']) {
                $outTruncated = $this->appendCapped($stdout, (string) stream_get_contents($pipes[1]), self::MAX_OUTPUT_CHARS, $outTruncated);
                $errTruncated = $this->appendCapped($stderr, (string) stream_get_contents($pipes[2]), self::MAX_OUTPUT_CHARS, $errTruncated);
                $exitCode = (int) $status['exitcode'];
                break;
            }

            $elapsed = microtime(true) - $startTime;
            if ($elapsed > $timeout) {
                $timedOut = true;
                proc_terminate($process, $this->isWindows ? 1 : 9);
                usleep(self::TERMINATE_GRACE_US);
                $outTruncated = $this->appendCapped($stdout, (string) stream_get_contents($pipes[1]), self::MAX_OUTPUT_CHARS, $outTruncated);
                $errTruncated = $this->appendCapped($stderr, (string) stream_get_contents($pipes[2]), self::MAX_OUTPUT_CHARS, $errTruncated);
                $exitCode = -1;
                break;
            }

            // Sleep efficiently until there's output, or at most the
            // select ceiling — whichever comes first — instead of
            // busy-polling every few milliseconds.
            $this->waitForActivity($pipes, min(self::SELECT_CEILING_SEC, $timeout - $elapsed));

            $outTruncated = $this->appendCapped($stdout, (string) stream_get_contents($pipes[1]), self::MAX_OUTPUT_CHARS, $outTruncated);
            $errTruncated = $this->appendCapped($stderr, (string) stream_get_contents($pipes[2]), self::MAX_OUTPUT_CHARS, $errTruncated);
        }

        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);
        $this->unlinkPidRecordIfOurs($nonce);

        $duration = round((microtime(true) - $startTime) * 1000, 1);

        // ── Read back the folder the command ended in, and remember it ──
        $newRel = $this->readCwdTempFile($cwdTmp);
        if ($newRel !== null) {
            $this->setStoredCwd($newRel);
            $finalRelCwd = $newRel;
        }

        if ($outTruncated) {
            $stdout .= "\n... [output truncated to protect your browser]";
        }
        if ($errTruncated) {
            $stderr .= "\n... [error output truncated to protect your browser]";
        }

        // ★ Single-flight lock released — the session may run the next cmd.
        $this->releaseRunLock($lockHandle);

        return [
            'command'     => $command,
            'output'      => $stdout,
            'error'       => $stderr,
            'exitCode'    => $exitCode,
            'timedOut'    => $timedOut,
            'duration'    => $duration,
            'cwd'         => $launchDir,
            'cwdRelative' => $finalRelCwd,
            'shell'       => $shellName,
        ];
    }

    /* ═══════════════════════════════════════════════════════════════
       RUN A COMMAND — STREAMING (Server-Sent Events)
       ═══════════════════════════════════════════════════════════════
       Same safety gates as run(), but output streams to the browser
       in real time. Now also detects a dropped connection (tab closed,
       app backgrounded, network loss) and kills the underlying process
       immediately instead of leaving it running unattended.           */
    public function runStream(string $command, string $stdin = '', int $cols = 0, int $rows = 0): void
    {
        // Phase B: pkg/apt commands stream LIVE.
        if (preg_match('/^(pkg|apt)(\s+|$)/i', trim($command))) {
            $this->streamPkg(trim($command));
            return;
        }

        if (!$this->isEnabled()) {
            $this->security->fail(
                'Terminal is disabled. To enable it, open config.php and set '
                    . "'terminal_enabled' => true in the features section.",
                403
            );
        }

        $this->checkProcOpenAvailable();

        $command = trim($command);
        if ($command === '') {
            $this->security->fail('Command cannot be empty.');
        }

        // ★ STABILITY PASS — SINGLE-FLIGHT: take the per-session lock BEFORE
        // touching anything else. When it's held we answer with a friendly
        // done-event instead of spawning a second command that would fight
        // the first one over the pid file, stdin mailbox and PTY.
        $lockHandle = $this->acquireRunLock();
        if ($lockHandle === null) {
            // Same SSE plumbing as the real stream below, so the reply is
            // flushed immediately instead of sitting in an output buffer.
            @set_time_limit(0);
            @ignore_user_abort(true);
            while (ob_get_level() > 0) {
                ob_end_flush();
            }
            @ob_implicit_flush(true);
            @header('Content-Type: text/event-stream');
            @header('Cache-Control: no-cache, no-transform');
            @header('X-Accelerate-Buffering: no');
            $emitBusy = function (string $event, $data): void {
                echo 'event: ' . $event . "\n"
                    . 'data: ' . json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n";
                @ob_flush();
                @flush();
            };
            $emitBusy('out', "⚠ A command is already running in this terminal — wait for it to finish or press Cancel.\n");
            $emitBusy('done', [
                'exitCode'    => -1,
                'timedOut'    => false,
                'duration'    => 0,
                'command'     => $command,
                'cwdRelative' => $this->getStoredCwd(),
                'busy'        => true,
            ]);
            return;
        }

        $relCwd = $this->getStoredCwd();
        $this->validateCommand($command, $relCwd);

        $launchDir = $relCwd === ''
            ? $this->workspaceRoot
            : $this->workspaceRoot . '/' . $relCwd;
        if (!is_dir($launchDir)) {
            $launchDir = $this->workspaceRoot;
            $relCwd = '';
        }

        $cwdTmp  = $this->makeCwdTempFile();
        $exitTmp = $this->makeCwdTempFile();
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        // NOTE: session_id() stays readable after session_write_close(),
        // so pidFilePath()/stdinFilePath() still resolve to the same paths
        // the lock was taken under.
        $nonce = $this->newNonce();

        @set_time_limit(0);
        // We want to detect a dropped connection ourselves (so we can
        // kill the child process cleanly) rather than have PHP silently
        // abandon the script — so we keep the script alive across a
        // disconnect and check connection_aborted() explicitly below.
        @ignore_user_abort(true);
        if (function_exists('apache_setenv')) {
            @apache_setenv('no-gzip', '1');
        }
        while (ob_get_level() > 0) {
            ob_end_flush();
        }
        @ob_implicit_flush(true);

        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache, no-transform');
        header('X-Accelerate-Buffering: no');
        header('Connection: keep-alive');

        $emit = function (string $event, $data): void {
            echo 'event: ' . $event . "\n"
                . 'data: ' . json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n";
            @ob_flush();
            @flush();
        };

        $timeout   = (int) ($this->config['terminal']['timeout'] ?? 10);
        $startTime = microtime(true);

        if ($this->isWindows) {
            $shellCmd = 'cmd.exe /c chcp 65001 >nul 2>&1 & ' . $command
                . ' & cd > "' . $cwdTmp . '" 2>nul';
            $childEnv = array_merge($this->baseEnv, ['PYTHONIOENCODING' => 'utf-8', 'PYTHONUTF8' => '1']);
        } else {
            // The full shell program. It also records the REAL exit code into
            // $exitTmp, because PTY wrappers don't always pass it through.
            $inner = $this->getLowEndShellAliases() . $this->pkgEnvPrefix() . $command
                . '; __quirky_exit=$?; pwd > ' . escapeshellarg($cwdTmp) . ' 2>/dev/null'
                . '; printf \'%s\' "$__quirky_exit" > ' . escapeshellarg($exitTmp)
                . '; exit $__quirky_exit';

            // ★ REAL TERMINAL MODE: wrap the shell in a pseudo-terminal when
            // the bundled ptyrun is available. Prompts appear instantly and
            // typed input is echoed inline, exactly like Termux.
            $kind = $this->ptyKind();
            if ($kind === 'ptyrun') {
                // ★ Phase T-PTY-5: size the PTY to the phone screen. The browser
                // measured how many columns fit; programs now draw to fit it.
                $w = ($cols >= 20 && $cols <= 400) ? $cols : 80;
                $h = ($rows >= 5 && $rows <= 200) ? $rows : 24;
                $shellCmd = [$this->binDir . '/ptyrun', '-w', (string) $w, '-h', (string) $h, '-c', $inner];
            } else {
                // ★ STABILITY PASS: resolved shell (see resolveShell()) — no
                // more guaranteed spawn failure on /bin/sh-less devices.
                $shellCmd = $this->shellCommand($inner);
            }
            $childEnv = array_merge($this->baseEnv, [
                'LANG'   => 'en_US.UTF-8',
                'LC_ALL' => 'en_US.UTF-8',
                'SHELL'  => $this->shellPath,
                // ★ TERM HONESTY DECISION: stays xterm-256color this release,
                // because the frontend emulator NOW implements SGR 38;5/48;5
                // (256-colour classes), quantized 38;2/48;2 truecolor and
                // ESC(0 DEC graphics (mobile-terminal.js + the generated CSS
                // palette). Advertising less than we support would make
                // modern TUIs (agentty/ink/rich) render dull 16-colour
                // fallbacks for no reason.
                'TERM'   => 'xterm-256color',
                'TERMINFO' => $this->prefix . '/share/terminfo',
                'TERMINFO_DIRS' => $this->prefix . '/share/terminfo:/usr/share/terminfo:/lib/terminfo',
                'ESCDELAY' => '200', // Fixes arrow key latency for nano/vim/TUIs
            ]);
            // ★ T-PTY-7: live-resize hook. New ptyrun builds create this
            // file and poll it; the kernel then delivers SIGWINCH to the
            // running TUI whenever we write a new size into it.
            if ($kind === 'ptyrun') {
                $childEnv['PTYRUN_WINSZ_FILE'] = $this->ptyWinsizeFile();
            }
        }

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $pipes = [];
        $process = proc_open($shellCmd, $descriptors, $pipes, $launchDir, $childEnv);
        if (!is_resource($process)) {
            $this->removeCwdTempFile($cwdTmp);
            $this->removeCwdTempFile($exitTmp);
            $emit('done', [
                'exitCode'    => -1,
                'timedOut'    => false,
                'duration'    => 0,
                'command'     => $command,
                'cwdRelative' => $relCwd,
            ]);
            $this->releaseRunLock($lockHandle);
            return;
        }

        $pidFile = $this->pidFilePath();
        $procStatus = proc_get_status($process);
        $childPid = (int) ($procStatus['pid'] ?? 0);
        if ($childPid > 0) {
            $this->writePidRecord($childPid, $nonce, $command);
        }
        // ★ STABILITY PASS: announce the run nonce. The frontend echoes it
        // on every stdin frame (and may pass it to cancel/resize once the
        // routes forward it), so a stale tab can never steer this command.
        // Old frontends simply ignore the unknown event type — harmless by
        // SSE contract.
        $emit('started', ['nonce' => $nonce, 'pid' => $childPid]);

        // Safety net for crashes/unexpected termination of THIS script.
        $this->registerSafetyNetCleanup($process, $pidFile, $cwdTmp, $nonce);

        if ($stdin !== '') {
            @fwrite($pipes[0], $stdin);
        }
        // ★ INTERACTIVE STDIN: do NOT close $pipes[0] here anymore. Keeping it
        // open is what lets scanf()/input()/readLine() wait for real input.
        // Reset the mailbox so a previous session's leftovers don't leak in.
        @file_put_contents($this->stdinFilePath(), '', LOCK_EX);
        // ★ STABILITY PASS — STDIN DEADLOCK FIX: pipes[0] used to stay in
        // BLOCKING mode, so fwrite() into a program that wasn't reading
        // (classic full-duplex pipe deadlock) froze the whole SSE loop and
        // with it connection_aborted() + the timeout check. Non-blocking
        // stdin + select() write-set below make injection chunk-safe.
        stream_set_blocking($pipes[0], false);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $timedOut = false;
        $exitCode = -1;
        // ★ STABILITY PASS: SEPARATE budgets — chatty stderr can no longer
        // eat stdout's cap (they were one shared counter before).
        $emittedOut = 0;
        $emittedErr = 0;
        $outTruncNoticeSent = false;
        $errTruncNoticeSent = false;
        $lastEmitTs         = microtime(true);
        $connectionLost     = false;

        while (true) {
            // ── Detect a dropped connection and stop the process ──
            // Without this, closing the tab mid-command (e.g. during a
            // long pkg install) would leave the process running in the
            // background with nobody watching it — wasting battery/data
            // until it finishes or the hard timeout eventually hits.
            if (connection_aborted()) {
                $connectionLost = true;
                proc_terminate($process, $this->isWindows ? 1 : 9);
                usleep(self::TERMINATE_GRACE_US);
                break;
            }

            $status = proc_get_status($process);
            if (!$status['running']) {
                $outChunk = (string) stream_get_contents($pipes[1]);
                $errChunk = (string) stream_get_contents($pipes[2]);
                if ($outChunk !== '' && $emittedOut < self::MAX_OUTPUT_CHARS) {
                    $emit('out', $outChunk);
                    $emittedOut += strlen($outChunk);
                }
                if ($errChunk !== '' && $emittedErr < self::MAX_OUTPUT_CHARS) {
                    $emit('err', $errChunk);
                    $emittedErr += strlen($errChunk);
                }
                $exitCode = (int) $status['exitcode'];
                break;
            }

            $elapsed = microtime(true) - $startTime;
            if ($elapsed > $timeout) {
                $timedOut = true;
                proc_terminate($process, $this->isWindows ? 1 : 9);
                usleep(self::TERMINATE_GRACE_US);
                $exitCode = -1;
                break;
            }

            // ★ INTERACTIVE STDIN: pour any typed input into the command before
            // we go back to sleep. User activity also resets the timeout clock so
            // an interactive session isn't killed mid-conversation.
            // ★ Only attempted when the mailbox actually holds bytes; the
            // write itself is chunk-safe now (non-blocking pipe — see above).
            $stdinWaiting = $this->pendingStdinWaiting();
            if ($stdinWaiting && $this->injectPendingStdin($pipes[0])) {
                $startTime = microtime(true);
                $stdinWaiting = $this->pendingStdinWaiting(); // remainder may still be queued
            }

            // Efficient sleep-until-data instead of a fixed-interval poll.
            // ★ The stdin pipe joins the WRITE set whenever input is queued,
            // so a full pipe wakes us the moment the program reads again.
            $this->waitForActivity($pipes, min(self::SELECT_CEILING_SEC, max(0.0, $timeout - $elapsed)), $stdinWaiting);

            // ★ STABILITY PASS — HEARTBEAT: connection_aborted() only flips
            // when PHP writes to the socket. A silent compile used to keep
            // the loop selecting forever with zero writes. Emitting a tiny
            // 'ping' every HEARTBEAT_SEC forces that write; the frontend's
            // SSE reader drops unknown events by contract, so this stays
            // invisible while making abort detection accurate within ~2 s
            // even during completely silent commands.
            if ((microtime(true) - $lastEmitTs) > self::HEARTBEAT_SEC) {
                $emit('ping', '');
                $lastEmitTs = microtime(true);
            }

            $outChunk = (string) stream_get_contents($pipes[1]);
            $errChunk = (string) stream_get_contents($pipes[2]);

            if ($outChunk !== '') {
                if ($emittedOut < self::MAX_OUTPUT_CHARS) {
                    $emit('out', $outChunk);
                    $emittedOut += strlen($outChunk);
                    $lastEmitTs = microtime(true);
                } elseif (!$outTruncNoticeSent) {
                    $emit('out', "\n... [output truncated to protect your browser]");
                    $outTruncNoticeSent = true;
                }
            }
            if ($errChunk !== '') {
                if ($emittedErr < self::MAX_OUTPUT_CHARS) {
                    $emit('err', $errChunk);
                    $emittedErr += strlen($errChunk);
                    $lastEmitTs = microtime(true);
                } elseif (!$errTruncNoticeSent) {
                    $emit('err', "\n... [error output truncated to protect your browser]");
                    $errTruncNoticeSent = true;
                }
            }
        }

        @fclose($pipes[0]); // ★ close the stdin pipe now that the command is done
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);
        $this->unlinkPidRecordIfOurs($nonce);
        @unlink($this->stdinFilePath()); // clean up the stdin mailbox
        @unlink($this->ptyWinsizeFile()); // clean up the live-resize hook

        // ★ The shell wrote the true exit code into $exitTmp (PTY wrappers like
        // ptyrun don't always forward the child's exit status). Trust it.
        if (!$timedOut && is_file($exitTmp)) {
            $exitFileVal = trim((string) @file_get_contents($exitTmp));
            if ($exitFileVal !== '' && ctype_digit($exitFileVal)) {
                $exitCode = (int) $exitFileVal;
            }
        }
        $this->removeCwdTempFile($exitTmp);

        $duration = round((microtime(true) - $startTime) * 1000, 1);

        $newRel = $this->readCwdTempFile($cwdTmp);
        if ($newRel !== null) {
            $this->setStoredCwd($newRel);
            $relCwd = $newRel;
        }

        // ★ Single-flight lock released — the session may run again.
        $this->releaseRunLock($lockHandle);

        // Nobody is listening anymore if the connection dropped — don't
        // bother trying to emit a final event to a closed pipe.
        if (!$connectionLost) {
            $emit('done', [
                'exitCode'    => $exitCode,
                'timedOut'    => $timedOut,
                'duration'    => $duration,
                'command'     => $command,
                'cwdRelative' => $relCwd,
            ]);
        }
    }

    /* ═══════════════════════════════════════════════════════════════
       CANCEL A RUNNING COMMAND
       ═══════════════════════════════════════════════════════════════ */

    /**
     * Cancel a running command.
     *
     * ★ STABILITY PASS:
     *  - $nonce (optional): when provided and it doesn't match the running
     *    record's nonce, the request comes from a stale tab → refused.
     *    The current routes don't forward a nonce yet; absent nonce keeps
     *    today's behaviour (act on whatever runs).
     *  - After killing the direct child, a /proc PPID-chain sweep kills the
     *    whole subtree LEAVES-FIRST (SIGTERM, then SIGKILL). proc_open()
     *    doesn't create a new process group, so `kill -TERM -PID` hit
     *    nothing and grandchildren (vim under ptyrun under sh) survived.
     *    The sweep is guarded — when /proc is unavailable it's skipped.
     */
    public function cancel(?string $nonce = null): array
    {
        // ★ FIX: start the session so pidFilePath() resolves to the SAME
        // per-session PID file that runStream() wrote. Without this,
        // session_id() was empty here and cancel() looked at the wrong file,
        // found nothing, and never killed the command.
        $this->security->startSession();
        $pidFile   = $this->pidFilePath();
        $stdinFile = $this->stdinFilePath();
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        if (!file_exists($pidFile)) {
            return ['cancelled' => false, 'reason' => 'No command is currently running.'];
        }
        $rec = $this->readPidRecord();
        if ($rec === null) {
            @unlink($pidFile);
            return ['cancelled' => false, 'reason' => 'Invalid PID.'];
        }
        if ($nonce !== null && $nonce !== '' && $rec['nonce'] !== '' && $nonce !== $rec['nonce']) {
            return ['cancelled' => false, 'reason' => 'Stale terminal — this tab no longer owns the running command.'];
        }
        $pid = $rec['pid'];
        if ($pid <= 0) {
            @unlink($pidFile);
            return ['cancelled' => false, 'reason' => 'Invalid PID.'];
        }

        if ($this->isWindows) {
            @exec('taskkill /PID ' . $pid . ' /F /T 2>nul');
        } else {
            // Kill the whole process tree so a child can't survive:
            //  1) the process group (works when the shell happens to be the leader)
            //  2) the direct children
            //  3) the recorded child itself
            @exec('kill -TERM -' . $pid . ' 2>/dev/null');
            @exec('pkill -TERM -P ' . $pid . ' 2>/dev/null');
            @exec('kill -TERM ' . $pid . ' 2>/dev/null');

            // ★ /proc fallback: collect every descendant of $pid by walking
            // PPID links, then kill deepest-first so no one re-parents a
            // still-live grandchild to init while we're walking.
            $tree = $this->descendantPids($pid);
            for ($i = count($tree) - 1; $i >= 0; $i--) {   // leaves first
                @exec('kill -TERM ' . $tree[$i] . ' 2>/dev/null');
            }

            usleep(200000); // 0.2s grace for a clean exit

            // Force pass: same order, SIGKILL.
            for ($i = count($tree) - 1; $i >= 0; $i--) {
                @exec('kill -KILL ' . $tree[$i] . ' 2>/dev/null');
            }
            @exec('kill -KILL -' . $pid . ' 2>/dev/null');
            @exec('pkill -KILL -P ' . $pid . ' 2>/dev/null');
            @exec('kill -KILL ' . $pid . ' 2>/dev/null');
        }

        @unlink($pidFile);
        @unlink($stdinFile); // also clean up the interactive stdin mailbox
        return ['cancelled' => true, 'pid' => $pid];
    }

    /**
     * ★ STABILITY PASS — /proc descendant scan.
     * Reads /proc/<pid>/stat for every numeric entry and returns all PIDs
     * whose PPID chain leads back to $root (excluding the root itself),
     * ordered shallow→deep so callers can kill leaves-first from the end.
     * Returns [] on Windows or whenever /proc isn't readable.
     *
     * @return int[]
     */
    private function descendantPids(int $root): array
    {
        if ($root <= 0 || $this->isWindows || !is_dir('/proc')) {
            return [];
        }
        $ppidOf = [];
        foreach ((@scandir('/proc') ?: []) as $entry) {
            if (!ctype_digit((string) $entry)) {
                continue;
            }
            $stat = @file_get_contents('/proc/' . $entry . '/stat');
            if ($stat === false) {
                continue; // process vanished between scandir and read
            }
            /* Format: pid (comm) state ppid … — comm may contain spaces and
               ')' sequences, so anchor on the LAST ')' in the header part. */
            $close = strrpos($stat, ')');
            if ($close === false) {
                continue;
            }
            $fields = explode(' ', trim(substr($stat, $close + 1)));
            if (count($fields) < 2) {
                continue;
            }
            $ppid = (int) $fields[1]; // field 4 overall = index 1 after "state"
            if ($ppid > 0) {
                $ppidOf[(int) $entry] = $ppid;
            }
        }
        // BFS from the root through children links.
        $out = [];
        $queue = [$root];
        $seen = [$root => true];
        while (!empty($queue)) {
            $cur = array_shift($queue);
            foreach ($ppidOf as $child => $pp) {
                if ($pp === $cur && !isset($seen[$child])) {
                    $seen[$child] = true;
                    $out[] = $child;
                    $queue[] = $child;
                }
            }
        }
        return $out; // shallow→deep; iterate from the END for leaves-first
    }


    /* ═══════════════════════════════════════════════════════════════
       LIVE PTY RESIZE (T-PTY-7)
       ═══════════════════════════════════════════════════════════════
       New ptyrun builds create the winsize file below at startup (that
       very act is the capability signal) and poll it ~5×/second. When
       we write a new "cols rows" into it, ptyrun pushes TIOCSWINSZ and
       the kernel delivers SIGWINCH to the running TUI, which redraws.
       Old ptyrun builds never create the file → resizePty() answers
       live:false and the frontend gracefully waits for the next cmd. */
    private function ptyWinsizeFile(): string
    {
        $sid = session_id();
        return sys_get_temp_dir() . '/quirky_ptywin_'
            . (($sid !== false && $sid !== '') ? $sid : 'default') . '.ws';
    }

    /**
     * Forward a new terminal size to the running ptyrun process.
     *
     * ★ STABILITY PASS: accepts an optional per-run nonce (see cancel()).
     *
     * @param int $cols New column count (20–400)
     * @param int $rows New row count (5–200)
     * @return array ['live' => bool, ...] live:true = running TUI will redraw
     */
    public function resizePty(int $cols, int $rows, ?string $nonce = null): array
    {
        if ($cols < 20 || $cols > 400 || $rows < 5 || $rows > 200) {
            return ['live' => false, 'reason' => 'size out of range'];
        }
        $this->security->startSession();
        $winFile = $this->ptyWinsizeFile();
        $pidFile = $this->pidFilePath();
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        // Only meaningful while a PTY command is actually running, and
        // only with a ptyrun build that created the file itself.
        if (!is_file($pidFile) || !is_file($winFile)) {
            return ['live' => false];
        }
        // Stale tab steering a newer command's PTY → refuse.
        if ($nonce !== null && $nonce !== '') {
            $rec = $this->readPidRecord();
            if ($rec !== null && $rec['nonce'] !== '' && $nonce !== $rec['nonce']) {
                return ['live' => false, 'reason' => 'stale run'];
            }
        }
        @file_put_contents($winFile, $cols . ' ' . $rows, LOCK_EX);
        return ['live' => true, 'cols' => $cols, 'rows' => $rows];
    }

    /* ═══════════════════════════════════════════════════════════════
       COMMAND VALIDATION (THE SAFETY GUARD)
       ═══════════════════════════════════════════════════════════════ */

    private function validateCommand(string $command, string $currentRel = ''): void
    {
        $lower = strtolower($command);

        $blockedPatterns = $this->config['terminal']['blocked_commands'] ?? [];
        foreach ($blockedPatterns as $pattern) {
            if (strpos($lower, strtolower((string) $pattern)) !== false) {
                $this->security->fail(
                    'Command blocked for safety: it matches the forbidden pattern "'
                        . $pattern . '". This pattern is listed in config.php → terminal → blocked_commands.'
                );
            }
        }

        $privEscPatterns = [
            'sudo ',
            'sudo\t',
            'su ',
            'su\t',
            'su -',
            'runas ',
            'runas\t',
            'doas ',
            'doas\t',
            'pkexec ',
            'gksudo ',
            'kdesudo ',
        ];
        foreach ($privEscPatterns as $p) {
            if (strpos($lower, $p) !== false) {
                $this->security->fail(
                    'Privilege-escalation commands (sudo / su / runas / doas) are not allowed '
                        . 'in the IDE terminal. Run those directly in your system terminal if needed.'
                );
            }
        }
        $firstWord = strtolower(strtok($command, " \t"));
        if (in_array($firstWord, ['sudo', 'su', 'runas', 'doas', 'pkexec'], true)) {
            $this->security->fail(
                'Privilege-escalation commands are not allowed in the IDE terminal.'
            );
        }

        if (preg_match_all('/\bcd\s+(?:"([^"]*)"|\'([^\']*)\'|([^\s;&|]+))/', $command, $cdMatches, PREG_SET_ORDER)) {
            foreach ($cdMatches as $m) {
                $target = isset($m[1]) && $m[1] !== '' ? $m[1]
                    : (isset($m[2]) && $m[2] !== '' ? $m[2]
                        : (isset($m[3]) ? $m[3] : ''));
                $target = trim($target);
                if ($target === '') {
                    continue;
                }
                if (preg_match('#^([a-zA-Z]:|[/\\\\])#', $target)) {
                    $this->security->fail(
                        'Cannot change to an absolute path. '
                            . 'Commands always run inside the workspace/ folder. '
                            . 'Use a relative path like "cd my-project" instead.'
                    );
                }
                if ($this->resolveCdTarget($currentRel, $target) === null) {
                    $this->security->fail(
                        'That "cd" would move ABOVE the workspace root, which is not allowed. '
                            . 'You are already as high as the IDE can go.'
                    );
                }
            }
        }

        $redirectCheck = preg_replace('/>>?\s*("|\')?\/dev\/null(?=$|[\s"\'|&;])/i', '', $command);
        if (preg_match('/>>?\s*("|\')?(\/[^"\']*|[a-zA-Z]:[\\\\\/])/', $redirectCheck)) {
            $this->security->fail(
                'Cannot redirect output to a path outside the workspace. '
                    . 'Use a relative filename like "> output.txt" instead.'
            );
        }

        if (preg_match('/(^|[\s;&|])(\.|source|bash|sh|zsh)\s+("|\')?\//', $command)) {
            $this->security->fail(
                'Cannot execute or source files from absolute paths outside the workspace. '
                    . 'Place your scripts inside the workspace/ folder and run them with a relative path.'
            );
        }
    }

    private function resolveCdTarget(string $currentRel, string $target): ?string
    {
        $stack = ($currentRel === '')
            ? []
            : explode('/', str_replace('\\', '/', $currentRel));

        foreach (explode('/', str_replace('\\', '/', $target)) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                if (empty($stack)) {
                    return null;
                }
                array_pop($stack);
            } else {
                $stack[] = $segment;
            }
        }

        return implode('/', $stack);
    }

    /* ═══════════════════════════════════════════════════════════════
    Phase B — PKG ROUTING
    ═══════════════════════════════════════════════════════════════ */
    private function routeToPkg(string $command): ?array
    {
        $trimmed = trim($command);
        if (!preg_match('/^(pkg|apt)(\s+|$)/i', $trimmed)) {
            return null;
        }

        // ★ BLOCK PKG/APT when the device tier policy forbids packages.
        require_once dirname(__DIR__) . '/mobile-limits.php';
        if (!quirkyMobileCanUsePackages($this->config)) {
            return [
                'command'     => $trimmed,
                'output'      => "🚫 Package management is restricted on low-end devices to prevent crashes.\n",
                'error'       => '',
                'exitCode'    => 1,
                'timedOut'    => false,
                'duration'    => 0,
                'cwd'         => $this->workspaceRoot,
                'cwdRelative' => $this->getStoredCwd(),
                'shell'       => 'pkg (blocked)',
            ];
        }

        $startTime = microtime(true);
        $relCwd = $this->getStoredCwd();

        ob_start();
        try {
            $pkg = $this->makePkg();
            $argsLine = trim((string) preg_replace('/^(pkg|apt)\s*/i', '', $trimmed));
            $result = $pkg->handleCommand($argsLine);
        } catch (\Throwable $e) {
            $result = [
                'output'   => '',
                'error'    => 'pkg internal error: ' . $e->getMessage(),
                'exitCode' => 1,
            ];
        }
        $stray = trim((string) ob_get_clean());
        if ($stray !== '') {
            $result['output'] = $stray . "\n" . ($result['output'] ?? '');
        }

        return [
            'command'     => $trimmed,
            'output'      => (string) ($result['output'] ?? ''),
            'error'       => (string) ($result['error'] ?? ''),
            'exitCode'    => (int) ($result['exitCode'] ?? 0),
            'timedOut'    => false,
            'duration'    => round((microtime(true) - $startTime) * 1000, 1),
            'cwd'         => $this->workspaceRoot,
            'cwdRelative' => $relCwd,
            'shell'       => 'pkg (built-in)',
        ];
    }

    /**
     * Route "apache start/stop/restart/status" commands to the Workshop.
     * Returns null if the command is not an apache command.
     */
    private function routeToApache(string $command): ?array
    {
        $trimmed = trim($command);
        if (!preg_match('/^apache(\s+|$)/i', $trimmed)) {
            return null;
        }

        $startTime = microtime(true);
        $relCwd = $this->getStoredCwd();

        require_once __DIR__ . '/Workshop.php';
        $workshop = new IdeWorkshop($this->config, $this->security);

        $sub = strtolower(trim((string) preg_replace('/^apache\s*/i', '', $trimmed)));

        switch ($sub) {
            case 'start':
                $result = $workshop->startApache();
                $output = $result['started']
                    ? "✓ Apache started on port {$result['port']}
"
                    : "✗ {$result['reason']}
";
                break;

            case 'stop':
                $result = $workshop->stopApache();
                $output = $result['stopped']
                    ? "✓ Apache stopped.
"
                    : "✗ {$result['reason']}
";
                break;

            case 'restart':
                $result = $workshop->restartApache();
                $output = ($result['started'] ?? false)
                    ? "✓ Apache restarted on port {$result['port']}
"
                    : "✗ " . ($result['reason'] ?? 'Restart failed.') . "
";
                break;

            case 'status':
                $status = $workshop->getApacheStatus();
                if ($status['running']) {
                    $output = "● Apache is RUNNING on port {$status['port']} (PID: {$status['pid']})
";
                } elseif ($status['installed']) {
                    $output = "○ Apache is installed but NOT running.
  Start it with: apache start
";
                } else {
                    $output = "✗ Apache is not installed.
  Install it via Workshop → Apache → Install
";
                }
                break;

            case '':
            case 'help':
                $output = "Quirky Apache Manager
"
                    . "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
"
                    . "  apache start      Start the Apache server
"
                    . "  apache stop       Stop the Apache server
"
                    . "  apache restart    Restart the Apache server
"
                    . "  apache status     Show current status
"
                    . "  apache help       Show this help
";
                break;

            default:
                $output = "✗ Unknown apache command: '$sub'
  Try: apache help
";
        }

        return [
            'command'     => $trimmed,
            'output'      => $output,
            'error'       => '',
            'exitCode'    => 0,
            'timedOut'    => false,
            'duration'    => round((microtime(true) - $startTime) * 1000, 1),
            'cwd'         => $this->workspaceRoot,
            'cwdRelative' => $relCwd,
            'shell'       => 'workshop (apache)',
        ];
    }

    private function makePkg(): IdePkg
    {
        require_once __DIR__ . '/Pkg.php';
        return new IdePkg($this->config, $this->security, $this->prefix);
    }

    private function streamPkg(string $command): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        @set_time_limit(0);
        @ignore_user_abort(true);
        if (function_exists('apache_setenv')) {
            @apache_setenv('no-gzip', '1');
        }
        while (ob_get_level() > 0) {
            ob_end_flush();
        }
        @ob_implicit_flush(true);
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache, no-transform');
        header('X-Accelerate-Buffering: no');
        header('Connection: keep-alive');

        $emit = function (string $event, $data): void {
            echo 'event: ' . $event . "\n"
                . 'data: ' . json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n";
            @ob_flush();
            @flush();
        };

        $startTime    = microtime(true);
        // ★ STABILITY PASS: separate out/err budgets (same rationale as the
        // shell streaming loop) + a heartbeat timestamp for the idle path.
        $emittedOutChars = 0;
        $emittedErrChars = 0;
        $lastEmitTs      = microtime(true);

        $pkg = $this->makePkg();
        $pkg->setEmitter(function (string $event, $chunk) use ($emit, &$emittedOutChars, &$emittedErrChars, &$lastEmitTs): void {
            // ★ HEARTBEAT (idle path): Pkg.php can stay silent for long
            // stretches between log lines. Whenever an emit DOES arrive,
            // first check whether a 'ping' is due — this keeps writes on
            // the socket during quiet phases so connection_aborted() stays
            // accurate. (Extraction itself now also emits structured prog
            // ticks every ~300 ms, which doubles as a heartbeat there.)
            if ((microtime(true) - $lastEmitTs) > self::HEARTBEAT_SEC) {
                $emit('ping', '');
                $lastEmitTs = microtime(true);
            }
            $isProg = ($event === 'prog');
            $len = $isProg && is_array($chunk)
                ? strlen((string) json_encode($chunk))
                : strlen((string) $chunk);
            if ($event === 'err') {
                if ($emittedErrChars >= self::MAX_OUTPUT_CHARS) {
                    return;
                }
            } elseif (!$isProg && $emittedOutChars >= self::MAX_OUTPUT_CHARS) {
                return; // budget spent; transient prog rows still pass through
            }
            // If the browser has disconnected mid pkg-install, stop
            // spending effort emitting output nobody will see. The pkg
            // manager itself still runs to completion (installs must
            // finish atomically), but we stop wasting flush() calls.
            if (connection_aborted()) {
                return;
            }
            if ($event === 'err') {
                $emittedErrChars += $len;
            } elseif (!$isProg) {
                $emittedOutChars += $len;
            }
            $lastEmitTs = microtime(true);
            $emit($event, $chunk);
        });

        ob_start();
        try {
            $argsLine = trim((string) preg_replace('/^(pkg|apt)\s*/i', '', $command));
            $result   = $pkg->handleCommand($argsLine);
        } catch (\Throwable $e) {
            $result = [
                'output'   => '',
                'error'    => 'pkg internal error: ' . $e->getMessage(),
                'exitCode' => 1,
            ];
        }
        $stray = trim((string) ob_get_clean());
        if ($stray !== '') {
            $emit('out', $stray . "\n");
        }

        if ($emittedChars === 0 && !empty($result['output'])) {
            $emit('out', $result['output']);
        }
        if (!empty($result['error'])) {
            $emit('err', $result['error']);
        }
        if (!connection_aborted()) {
            $emit('done', [
                'exitCode'    => (int) ($result['exitCode'] ?? 0),
                'timedOut'    => false,
                'duration'    => round((microtime(true) - $startTime) * 1000, 1),
                'command'     => $command,
                'cwdRelative' => $this->getStoredCwd(),
            ]);
        }
    }

    private function ensurePhpWrapper(): void
    {
        if ($this->isWindows || $this->binDir === '') {
            return;
        }
        if (!is_dir($this->binDir)) {
            @mkdir($this->binDir, 0777, true);
        }
        $wrapper = $this->binDir . '/php';
        if (file_exists($wrapper)) {
            return;
        }
        $real = defined('PHP_BINARY') ? (string) PHP_BINARY : '';
        if ($real === '' || !is_file($real)) {
            return;
        }
        $script = "#!/system/bin/sh\nexec '" . $real . "' \"\$@\"\n";
        if (@file_put_contents($wrapper, $script) !== false) {
            @chmod($wrapper, 0755);
        }
    }

    /* ═══════════════════════════════════════════════════════════════
    LOW-END DEVICE SHELL INTERCEPTION
    Blocks heavy package managers at the shell level to prevent
    OOM crashes and storage exhaustion on low-tier devices.
    ═══════════════════════════════════════════════════════════════ */
    private function getLowEndShellAliases(): string
    {
        require_once dirname(__DIR__) . '/mobile-limits.php';

        $tier = quirkyMobileNormalizeDeviceTier(
            $this->config['device']['tier'] ?? null,
            'high'
        );

        if ($tier !== 'low') {
            return '';
        }

        return 'export QUIRKY_LOW_END=1; '
            . 'alias pkg=\'echo "🚫 Package management is restricted on low-end devices."\'; '
            . 'alias apt=\'echo "🚫 Package management is restricted."\'; '
            . 'alias apt-get=\'echo "🚫 Package management is restricted."\'; '
            . 'npm() { if [[ "$*" =~ (install|i|update|up|cache) ]]; then echo "🚫 npm install/update is restricted."; return 1; fi; command npm "$@"; }; '
            . 'composer() { if [[ "$*" =~ (require|global|update) ]]; then echo "🚫 Composer installs are restricted."; return 1; fi; command composer "$@"; }; ';
    }

    /* ═══════════════════════════════════════════════════════════════
    PTY SUPPORT — makes interactive programs behave like a real terminal
    ═══════════════════════════════════════════════════════════════════
    Uses the bundled ptyrun binary (shipped inside the app's assets
    at toolbox/ptyrun and copied into prefix/bin/ by MainActivity.java
    on every launch). No external dependencies (util-linux, etc.).

    The on/off switch lives in config.php → terminal → 'pty':
      'auto' = use ptyrun when available
      'off'  = force plain pipe mode (interactive programs still
               receive typed input, but without a real PTY)

    Result is cached for 1 hour so we don't re-probe every command. */
    private function ptyKind(): string
    {
        static $kind = null;
        if ($kind !== null) return $kind;

        // ★ Respect the config on/off switch (config.php → terminal → 'pty').
        //   'off'  = plain pipe mode, no PTY at all.
        //   'auto' = use the bundled ptyrun when it's available.
        $ptyMode = strtolower((string) ($this->config['terminal']['pty'] ?? 'off'));
        if ($ptyMode === 'off') {
            $kind = '';
            return $kind;
        }

        if ($this->isWindows) {
            $kind = '';
            return $kind;
        }

        $cacheFile = sys_get_temp_dir() . '/quirky_pty_' . md5($this->binDir) . '.txt';
        if (is_file($cacheFile) && (time() - (int) @filemtime($cacheFile)) < 3600) {
            $cached = trim((string) @file_get_contents($cacheFile));
            if (in_array($cached, ['ptyrun', '0'], true)) {
                $kind = ($cached === '0') ? '' : $cached;
                return $kind;
            }
        }

        $kind = '';

        // The bundled ptyrun binary — shipped inside the app at
        // assets/toolbox/ptyrun and copied into prefix/bin/ptyrun
        // by MainActivity.java on every launch. No internet needed.
        if (is_file($this->binDir . '/ptyrun') && is_executable($this->binDir . '/ptyrun')) {
            $kind = 'ptyrun';
            @file_put_contents($cacheFile, 'ptyrun');
            return $kind;
        }

        // No PTY provider found (ptyrun asset missing or not executable).
        @file_put_contents($cacheFile, '0');
        return $kind;
    }

    private function pkgEnvPrefix(): string
    {
        if ($this->isWindows || $this->binDir === '') {
            return '';
        }
        $tlsDir         = $this->prefix . '/etc/tls';
        $sslCertsDir    = $this->prefix . '/etc/ssl/certs';
        $includeDir     = $this->prefix . '/include';
        $sysrootInclude = $this->prefix . '/sysroot/usr/include';
        $sysrootLib     = $this->prefix . '/sysroot/usr/lib';
        $terminfoDir    = $this->prefix . '/share/terminfo';

        // ★ FIX: Determine the best CA bundle path.
        // 1. The one installed by the `ca-certificates` package (standard Termux path)
        // 2. The bundled fallback copied by MainActivity.java
        $caBundle = '';
        if (is_file($sslCertsDir . '/ca-certificates.crt')) {
            $caBundle = $sslCertsDir . '/ca-certificates.crt';
        } elseif (is_file($tlsDir . '/cacert.pem')) {
            $caBundle = $tlsDir . '/cacert.pem';
        }

        // ★ FIX: Add git helper directories to PATH so git can find
        // git-remote-https even if GIT_EXEC_PATH is ignored or overridden.
        $gitPaths = $this->binDir;
        if (is_dir($this->prefix . '/libexec/git-core')) {
            $gitPaths .= ':' . $this->prefix . '/libexec/git-core';
        }
        if (is_dir($this->prefix . '/lib/git-core')) {
            $gitPaths .= ':' . $this->prefix . '/lib/git-core';
        }

        $env = 'export PREFIX="' . $this->prefix . '"; '
            . 'export PATH="' . $gitPaths . ':$PATH"; '
            . 'export LD_LIBRARY_PATH="' . $this->libDir . '${LD_LIBRARY_PATH:+:$LD_LIBRARY_PATH}"; '
            . 'export TMPDIR="' . $this->prefix . '/tmp"; '
            . 'export TERMINFO="' . $terminfoDir . '"; '
            . 'export TERMINFO_DIRS="' . $terminfoDir . ':/usr/share/terminfo:/lib/terminfo"; '
            . 'export ESCDELAY=200; ';

        // ★ FIX 1: Only set OPENSSL_CONF if the file actually exists.
        // OpenSSL 3.x will fail the TLS handshake (or refuse to connect)
        // if OPENSSL_CONF points to a missing file, because it won't load
        // the default cipher suites and TLS versions.
        if (is_file($tlsDir . '/openssl.cnf')) {
            $env .= 'export OPENSSL_CONF="' . $tlsDir . '/openssl.cnf"; ';
        }

        if ($caBundle !== '') {
            // SSL_CERT_FILE is used by OpenSSL and many CLI tools.
            $env .= 'export SSL_CERT_FILE="' . $caBundle . '"; ';
            $env .= 'export SSL_CERT_DIR="' . dirname($caBundle) . '"; ';

            // ★ FIX 2: CURL_CA_BUNDLE forces curl to use this exact file,
            // bypassing the hardcoded /data/data/com.termux/... path it
            // was compiled with. This fixes "TLS handshake failure" or
            // "certificate verify failed" when downloading from HTTPS.
            $env .= 'export CURL_CA_BUNDLE="' . $caBundle . '"; ';
        }

        // ★ FIX 3: Git & Build environment variables.
        // Termux's git binary is compiled with a hardcoded system config path
        // (/data/data/com.termux/files/usr/etc/gitconfig). Since that path is
        // outside our app's sandbox, git gets "Permission denied" when it tries
        // to stat it. Pointing these variables to our own prefix fixes it.
        // ★ FIX 4 (remote-https missing): Termux installs git helper binaries
        // (like git-remote-https, git-remote-http) inside `libexec/git-core/`,
        // NOT `lib/git-core/`. Overriding GIT_EXEC_PATH to the wrong folder
        // made git blind to its own HTTPS helper, breaking `git clone https://`.
        // We now point it to the correct `libexec` folder, with a fallback to
        // `lib` just in case a specific package version differs.
        $gitExecPath = $this->prefix . '/libexec/git-core';
        if (!is_dir($gitExecPath) && is_dir($this->libDir . '/git-core')) {
            $gitExecPath = $this->libDir . '/git-core';
        }

        $env .= 'export GIT_CONFIG_SYSTEM="' . $this->prefix . '/etc/gitconfig"; '
            .  'export GIT_EXEC_PATH="' . $gitExecPath . '"; '
            .  'export GIT_TEMPLATE_DIR="' . $this->prefix . '/share/git-core/templates"; '
            .  'export HOME="' . $this->workspaceRoot . '"; '
            .  'export CMAKE_PREFIX_PATH="' . $this->prefix . '"; ';

        // ★ FIX: Git SSL Certificate Path Override
        // Termux's git binary is hardcoded to look for trust anchors at
        // `/data/data/com.termux/files/usr/etc/tls/cert.pem`. Since our
        // app's prefix is different, git fails the TLS handshake with
        // "error adding trust anchors". Setting GIT_SSL_CAINFO forces
        // git to use our correctly installed CA bundle instead.
        if ($caBundle !== '') {
            $env .= 'export GIT_SSL_CAINFO="' . $caBundle . '"; '
                .  'export GIT_SSL_CAPATH="' . dirname($caBundle) . '"; ';
        }

        $env .= 'export CPATH="' . $includeDir . ':' . $sysrootInclude . '${CPATH:+:$CPATH}"; '
            . 'export C_INCLUDE_PATH="' . $includeDir . ':' . $sysrootInclude . '${C_INCLUDE_PATH:+:$C_INCLUDE_PATH}"; '
            . 'export CPLUS_INCLUDE_PATH="' . $includeDir . '/c++/v1:' . $includeDir . ':' . $sysrootInclude . '${CPLUS_INCLUDE_PATH:+:$CPLUS_INCLUDE_PATH}"; '
            . 'export OBJC_INCLUDE_PATH="' . $includeDir . ':' . $sysrootInclude . '${OBJC_INCLUDE_PATH:+:$OBJC_INCLUDE_PATH}"; '
            . 'export LIBRARY_PATH="' . $this->libDir . ':' . $sysrootLib . '${LIBRARY_PATH:+:$LIBRARY_PATH}"; '
            . 'export PKG_CONFIG_PATH="' . $this->libDir . '/pkgconfig:' . $this->prefix . '/share/pkgconfig${PKG_CONFIG_PATH:+:$PKG_CONFIG_PATH}"; ';

        return $env;
    }

    public function getPrefixInspector(): array
    {
        $tmpDir = $this->prefix . '/tmp';
        $files = [];
        if (is_dir($tmpDir)) {
            foreach (@scandir($tmpDir) ?: [] as $f) {
                if ($f === '.' || $f === '..') continue;
                $path = $tmpDir . '/' . $f;
                $files[] = [
                    'name' => $f,
                    'type' => is_dir($path) ? 'folder' : 'file',
                    'size' => is_file($path) ? (int) filesize($path) : 0,
                    'modified' => date('Y-m-d H:i', (int) filemtime($path))
                ];
            }
        }
        return ['prefix' => $this->prefix, 'tmp_files' => $files];
    }

    public function cleanPrefixTemp(): array
    {
        $tmpDir = $this->prefix . '/tmp';
        $deleted = 0;
        if (is_dir($tmpDir)) {
            foreach (@scandir($tmpDir) ?: [] as $f) {
                if ($f === '.' || $f === '..') continue;
                $path = $tmpDir . '/' . $f;
                if (is_file($path) || is_link($path)) {
                    if (@unlink($path)) $deleted++;
                } elseif (is_dir($path)) {
                    $this->deleteRecursiveSys($path);
                    $deleted++;
                }
            }
        }
        return ['cleaned' => true, 'deleted' => $deleted];
    }

    private function deleteRecursiveSys(string $path): void
    {
        if (is_link($path)) {
            @unlink($path);
            return;
        }
        if (is_file($path)) {
            @unlink($path);
            return;
        }
        $entries = @scandir($path);
        if ($entries === false) return;
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            $this->deleteRecursiveSys($path . '/' . $entry);
        }
        @rmdir($path);
    }

    private function fmtBytesSys(int $b): string
    {
        if ($b <= 0) return '0 B';
        $u = ['B', 'KB', 'MB', 'GB'];
        $i = min((int) floor(log($b, 1024)), count($u) - 1);
        return round($b / pow(1024, $i), 1) . ' ' . $u[$i];
    }

    /* ═══════════════════════════════════════════════════════════════
       HELPERS
       ═══════════════════════════════════════════════════════════════ */

    private function checkProcOpenAvailable(): void
    {
        if (!function_exists('proc_open')) {
            $this->security->fail(
                'The terminal cannot run because proc_open() is disabled on this server. '
                    . 'Check the disable_functions directive in php.ini. '
                    . '(On Laragon / local dev, this is normally enabled.)',
                500
            );
        }
    }

    private function isProcOpenAvailable(): bool
    {
        return function_exists('proc_open');
    }

    /**
     * Kept as a defensive safety net (e.g. for any code path that still
     * builds a full string before returning). Normal output is now
     * capped as it arrives, so this should rarely have to trim anything.
     */
    private function capOutput(string $output): string
    {
        if (strlen($output) > self::MAX_OUTPUT_CHARS) {
            $removed = strlen($output) - self::MAX_OUTPUT_CHARS;
            return substr($output, 0, self::MAX_OUTPUT_CHARS)
                . "\n... [output truncated — " . number_format($removed)
                . " more characters hidden to protect your browser]";
        }
        return $output;
    }
}
