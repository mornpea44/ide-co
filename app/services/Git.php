<?php

declare(strict_types=1);
/**
 * ═══════════════════════════════════════════════════════════════════════════
 *  QUIRKY IDE — GIT SERVICE (Phase 5 · read base + write operations)
 * ═══════════════════════════════════════════════════════════════════════════
 *
 *  Shells out to the `git` binary to give the IDE a view of — and control
 *  over — the repository that lives in workspace/:
 *    READ   status, history, diffs, commit details
 *    WRITE  stage/unstage/discard, commit(+amend), init,
 *           branch list/create/switch, pull/push, merge --abort
 *
 *  SAFETY RULES (local-only, but still strict):
 *    • Only a fixed whitelist of subcommands is ever executed (no free-form
 *      command strings from the client).
 *    • Every argument is escaped with escapeshellarg() — user input can
 *      never inject shell commands.
 *    • File paths are validated (no "..", no absolute paths, no blocked
 *      segments like .git) before they reach git; branch names match a
 *      strict pattern; commit hashes must be pure hex (7–40 chars).
 *    • Every command runs with cwd = workspace/ and a hard timeout (20s,
 *      120s for pull/push), and GIT_TERMINAL_PROMPT=0 so git can never hang
 *      waiting for credentials.
 *
 * ═══════════════════════════════════════════════════════════════════════════
 */
class IdeGit
{
  private array $config;
  private IdeSecurity $security;
  private string $workspaceRoot;
  private bool $isWindows;
  private string $binary;

  /**
   * Commit identity injected via `-c user.name=… -c user.email=…` so
   * commits work even when git has no global config on a fresh device.
   * Config keys: $config['git']['user_name'] / $config['git']['user_email'].
   */
  private string $userName;
  private string $userEmail;

  /** Hard timeout per git command (seconds). */
  private const TIMEOUT = 20;
  /** Raised timeout for network operations (pull/push). */
  private const TIMEOUT_NETWORK = 120;
  /** Output cap so a huge diff can't freeze the browser. */
  private const MAX_OUTPUT = 512000;
  /** Status list cap (a very dirty repo still renders fast). */
  private const MAX_STATUS_FILES = 1000;
  /** Max paths accepted per stage/unstage/discard call. */
  private const MAX_PATHS = 200;
  /** Branch name pattern + length enforced before checkout -b/switch. */
  private const BRANCH_NAME_RE = '/^[A-Za-z0-9._\-\/]{1,80}$/';

  public function __construct(array $config, IdeSecurity $security)
  {
    $this->config    = $config;
    $this->security  = $security;
    $this->isWindows = (PHP_OS_FAMILY === 'Windows');
    $this->binary    = trim((string) ($config['git']['binary'] ?? 'git'));
    if ($this->binary === '') {
      $this->binary = 'git';
    }
    $this->userName = trim((string) ($config['git']['user_name'] ?? 'Quirky'));
    if ($this->userName === '') {
      $this->userName = 'Quirky';
    }
    $this->userEmail = trim((string) ($config['git']['user_email'] ?? 'dev@quirky.local'));
    if ($this->userEmail === '') {
      $this->userEmail = 'dev@quirky.local';
    }
    $real = realpath(IDE_WORKSPACE);
    if ($real === false) {
      mkdir(IDE_WORKSPACE, 0777, true);
      $real = realpath(IDE_WORKSPACE);
    }
    $this->workspaceRoot = str_replace('\\', '/', (string) $real);
  }

  public function isEnabled(): bool
  {
    return !empty($this->config['features']['git_enabled']);
  }

  /* ═══════════════════════════════════════════════════════════════
       INFO — is git there, and is workspace/ a repository?
       ═══════════════════════════════════════════════════════════════ */
  public function getInfo(): array
  {
    if (!function_exists('proc_open')) {
      return [
        'available' => false,
        'isRepo' => false,
        'branch' => null,
        'reason' => 'proc_open() is disabled on this server'
      ];
    }
    $ver = $this->run(['--version']);
    if (!$ver['ok']) {
      return [
        'available' => false,
        'isRepo' => false,
        'branch' => null,
        'reason' => 'git was not found on this machine'
      ];
    }
    $firstLine = strtok(trim($ver['stdout']), "\n");

    $repo   = $this->run(['rev-parse', '--is-inside-work-tree']);
    $isRepo = $repo['ok'] && trim($repo['stdout']) === 'true';

    $branch = null;
    if ($isRepo) {
      $b = $this->run(['rev-parse', '--abbrev-ref', 'HEAD']);
      if ($b['ok']) {
        $branch = trim($b['stdout']);
      }
      // Repos with no commits yet: fall back to the branch name git chose
      if ($branch === '' || $branch === 'HEAD') {
        $s = $this->run(['symbolic-ref', '--short', 'HEAD']);
        if ($s['ok'] && trim($s['stdout']) !== '') {
          $branch = trim($s['stdout']);
        }
      }
    }
    return [
      'available'  => true,
      'gitVersion' => $firstLine !== false ? $firstLine : null,
      'isRepo'     => $isRepo,
      'branch'     => ($branch !== '' && $branch !== null) ? $branch : null,
    ];
  }

  /* ═══════════════════════════════════════════════════════════════
       STATUS — changed / new / deleted files
       ═══════════════════════════════════════════════════════════════ */
  public function status(): array
  {
    $info = $this->getInfo();
    if (!$info['available'] || !$info['isRepo']) {
      return [
        'ok' => false,
        'files' => [],
        'branch' => null,
        'reason' => $info['available'] ? 'Not a git repository' : 'git not available'
      ];
    }
    $r = $this->run(['status', '--porcelain=v1', '-z', '-uall']);
    if (!$r['ok']) {
      $this->security->fail('git status failed: ' . trim($r['stderr']));
    }
    // -z output: NUL-separated entries "XY PATH"; renames are "R  OLD\0NEW\0".
    $parts = explode("\0", $r['stdout']);
    $files = [];
    $n     = count($parts);
    for ($i = 0; $i < $n && count($files) < self::MAX_STATUS_FILES; $i++) {
      $entry = $parts[$i];
      if (strlen($entry) < 4) {
        continue;
      }
      $x    = $entry[0];   // index (staged) status
      $y    = $entry[1];   // worktree (unstaged) status
      $path = substr($entry, 3);
      $orig = null;
      if ($x === 'R' || $x === 'C') {
        $orig = $path;
        $path = (string) ($parts[$i + 1] ?? '');
        $i++;
      }
      if ($path === '') {
        continue;
      }
      $files[] = [
        'path'     => str_replace('\\', '/', $path),
        'origPath' => $orig !== null ? str_replace('\\', '/', $orig) : null,
        'index'    => $x,
        'worktree' => $y,
      ];
    }
    $staged = 0;
    $unstaged = 0;
    $untracked = 0;
    foreach ($files as $f) {
      if ($f['index'] === '?') {
        $untracked++;
        continue;
      }
      if (!in_array($f['index'], [' ', '!'], true)) {
        $staged++;
      }
      if (!in_array($f['worktree'], [' ', '!'], true)) {
        $unstaged++;
      }
    }
    return [
      'ok'        => true,
      'branch'    => $info['branch'],
      'files'     => $files,
      'truncated' => count($files) >= self::MAX_STATUS_FILES,
      'counts'    => [
        'total'     => count($files),
        'staged'    => $staged,
        'unstaged'  => $unstaged,
        'untracked' => $untracked,
      ],
    ];
  }

  /* ═══════════════════════════════════════════════════════════════
       LOG — recent commit history
       ═══════════════════════════════════════════════════════════════ */
  public function log(int $limit = 40): array
  {
    $info = $this->getInfo();
    if (!$info['available'] || !$info['isRepo']) {
      return ['ok' => false, 'commits' => []];
    }
    $limit = max(1, min($limit, 200));
    $r = $this->run([
      'log',
      '-' . $limit,
      '--pretty=format:%H%x00%h%x00%an%x00%ad%x00%s',
      '--date=iso',
    ]);
    if (!$r['ok']) {
      // A repo without any commits yet is not an error for the UI.
      return ['ok' => true, 'commits' => [], 'branch' => $info['branch']];
    }
    $commits = [];
    foreach (explode("\n", $r['stdout']) as $line) {
      $f = explode("\0", $line);
      if (count($f) < 5) {
        continue;
      }
      $commits[] = [
        'hash'    => $f[0],
        'short'   => $f[1],
        'author'  => $f[2],
        'date'    => $f[3],
        'subject' => implode("\0", array_slice($f, 4)),
      ];
    }
    return ['ok' => true, 'commits' => $commits, 'branch' => $info['branch']];
  }

  /* ═══════════════════════════════════════════════════════════════
       DIFF — changes for one file (or the whole repo)
       ═══════════════════════════════════════════════════════════════
       Shows the unstaged diff first; if that's empty it falls back to
       the staged diff, so clicking a file always shows something.     */
  public function diff(string $path = ''): array
  {
    $info = $this->getInfo();
    if (!$info['available'] || !$info['isRepo']) {
      return ['ok' => false, 'diff' => '', 'note' => 'git not available or not a repository'];
    }
    if ($path !== '') {
      $this->validatePath($path);
    }
    $args = ['diff', '--no-color', '--patch'];
    if ($path !== '') {
      $args[] = '--';
      $args[] = $path;
    }
    $r = $this->run($args);

    $stagedView = false;
    if ($r['ok'] && trim($r['stdout']) === '') {
      $args2 = ['diff', '--no-color', '--patch', '--staged'];
      if ($path !== '') {
        $args2[] = '--';
        $args2[] = $path;
      }
      $r2 = $this->run($args2);
      if ($r2['ok'] && trim($r2['stdout']) !== '') {
        $r          = $r2;
        $stagedView = true;
      }
    }
    $diff = substr($r['stdout'], 0, self::MAX_OUTPUT);
    $note = null;
    if (trim($diff) === '') {
      $note = $path !== ''
        ? 'No diff for this file — it may be untracked or identical to the last commit.'
        : 'No changes.';
    }
    return ['ok' => true, 'diff' => $diff, 'path' => $path, 'staged' => $stagedView, 'note' => $note];
  }

  /* ═══════════════════════════════════════════════════════════════
       SHOW — what one commit changed
       ═══════════════════════════════════════════════════════════════ */
  public function show(string $hash): array
  {
    $info = $this->getInfo();
    if (!$info['available'] || !$info['isRepo']) {
      return ['ok' => false, 'diff' => ''];
    }
    if (!preg_match('/^[0-9a-f]{7,40}$/i', $hash)) {
      $this->security->fail('Invalid commit hash.');
    }
    $r = $this->run(['show', '--no-color', '--format=%H%x00%h%x00%an%x00%ad%x00%s', $hash]);
    if (!$r['ok']) {
      $this->security->fail('git show failed: ' . trim($r['stderr']));
    }
    $meta = ['hash' => $hash, 'short' => $hash, 'author' => '', 'date' => '', 'subject' => ''];
    $out  = $r['stdout'];
    $diff = $out;
    $nl   = strpos($out, "\n");
    if ($nl !== false) {
      $f = explode("\0", substr($out, 0, $nl));
      if (count($f) >= 5) {
        $meta = [
          'hash'    => $f[0],
          'short'   => $f[1],
          'author'  => $f[2],
          'date'    => $f[3],
          'subject' => implode("\0", array_slice($f, 4)),
        ];
      }
      $diff = substr($out, $nl + 1);
    }
    return ['ok' => true, 'meta' => $meta, 'diff' => substr($diff, 0, self::MAX_OUTPUT)];
  }

  /* ═══════════════════════════════════════════════════════════════
       WRITE OPERATIONS — staging, commits, branches, remotes
       ═══════════════════════════════════════════════════════════════
       Same discipline as the read-only commands above: fixed
       subcommand whitelists, escapeshellarg on every argument,
       validated paths, prompt-free env, timeout + output caps.
       All results share one envelope:
         success → {ok:true, output:string, error:null, …extras}
         failure → {ok:false, output:string, error:string, exitCode:int}
                                                                   */

  /**
   * STAGE — git add -- <paths>
   */
  public function stage(array $paths): array
  {
    if ($err = $this->repoGuard()) {
      return $err;
    }
    $clean = $this->sanitizePaths($paths);
    if ($clean === []) {
      return ['ok' => false, 'output' => '', 'error' => 'No files to stage.', 'exitCode' => -1];
    }
    $r = $this->run(array_merge(['add', '--'], $clean));
    if (!$r['ok']) {
      return $this->failResult($r, 'git add failed.');
    }
    return ['ok' => true, 'output' => trim($r['stdout']), 'error' => null, 'staged' => $clean, 'count' => count($clean)];
  }

  /**
   * UNSTAGE — git restore --staged -- <paths>
   * Older git (<2.23) has no `restore`; when stderr says so we retry once
   * with the classic equivalent `git reset HEAD -- <paths>`.
   */
  public function unstage(array $paths): array
  {
    if ($err = $this->repoGuard()) {
      return $err;
    }
    $clean = $this->sanitizePaths($paths);
    if ($clean === []) {
      return ['ok' => false, 'output' => '', 'error' => 'No files to unstage.', 'exitCode' => -1];
    }
    $r = $this->run(array_merge(['restore', '--staged', '--'], $clean));
    if (!$r['ok'] && ($r['exitCode'] === 129 || preg_match(
      '/unknown (switch|option)|unrecognized|invalid option|usage: git/i',
      $r['stderr']
    ))) {
      $r = $this->run(array_merge(['reset', 'HEAD', '--'], $clean));
    }
    if (!$r['ok']) {
      return $this->failResult($r, 'Could not unstage.');
    }
    return ['ok' => true, 'output' => trim($r['stdout']), 'error' => null, 'unstaged' => $clean, 'count' => count($clean)];
  }

  /**
   * DISCARD — git checkout -- <paths>
   *
   * ★ DESTRUCTIVE: this permanently throws away UNCOMMITTED changes in
   * the listed files. The UI MUST ask for confirmation before calling.
   */
  public function discard(array $paths): array
  {
    if ($err = $this->repoGuard()) {
      return $err;
    }
    $clean = $this->sanitizePaths($paths);
    if ($clean === []) {
      return ['ok' => false, 'output' => '', 'error' => 'No files to discard.', 'exitCode' => -1];
    }
    // DESTRUCTIVE (see docblock) — checkout -- reverts working tree files.
    $r = $this->run(array_merge(['checkout', '--'], $clean));
    if (!$r['ok']) {
      return $this->failResult($r, 'Could not discard changes.');
    }
    return ['ok' => true, 'output' => trim($r['stdout']), 'error' => null, 'discarded' => $clean, 'count' => count($clean)];
  }

  /**
   * COMMIT — git commit -F - (message fed via STDIN), identity injected via
   * -c user.name / -c user.email from config (defaults:
   * 'Quirky' <dev@quirky.local>).
   * The message travels through stdin, NOT the command line, so no shell
   * quoting (or Windows escapeshellarg '%' replacement) can ever mangle it.
   * Refuses an empty message; --amend rewrites the previous commit.
   */
  public function commit(string $message, bool $amend = false): array
  {
    $message = trim($message);
    if ($message === '') {
      return ['ok' => false, 'output' => '', 'error' => 'Commit message cannot be empty.', 'exitCode' => -1];
    }
    if ($err = $this->repoGuard()) {
      return $err;
    }
    $args = [
      '-c', 'user.name=' . $this->userName,
      '-c', 'user.email=' . $this->userEmail,
      'commit',
    ];
    if ($amend) {
      $args[] = '--amend';
    }
    array_push($args, '-F', '-');

    $r = $this->run($args, self::TIMEOUT, $message . "\n");
    if (!$r['ok']) {
      return $this->failResult($r, $amend ? 'Commit amend failed.' : 'Commit failed.');
    }
    return [
      'ok'      => true,
      'output'  => trim($r['stdout'] !== '' ? $r['stdout'] : $r['stderr']),
      'error'   => null,
      'amended' => $amend,
      'branch'  => $this->currentBranch(),
    ];
  }

  /**
   * INIT — git init inside the workspace (idempotent; reports whether a
   * repository was created or already existed).
   */
  public function init(): array
  {
    $info = $this->getInfo();
    if (!$info['available']) {
      return ['ok' => false, 'output' => '', 'error' => 'git was not found on this machine', 'exitCode' => -1];
    }
    $wasRepo = $info['isRepo'];
    $r = $this->run(['init']);
    if (!$r['ok']) {
      return $this->failResult($r, 'git init failed.');
    }
    $created = stripos($r['stdout'], 'reinitializ') === false && !$wasRepo;
    return [
      'ok'          => true,
      'output'      => trim($r['stdout']),
      'error'       => null,
      'created'     => $created,
      'alreadyRepo' => $wasRepo || !$created,
      'branch'      => $this->currentBranch(),
    ];
  }

  /**
   * BRANCH LIST — parse `git branch --list --all` into
   * [{name, current, remote}] plus the current branch shortcut.
   */
  public function branchList(): array
  {
    if ($err = $this->repoGuard()) {
      return $err;
    }
    $r = $this->run(['branch', '--list', '--all']);
    if (!$r['ok']) {
      return $this->failResult($r, 'Could not list branches.');
    }
    $branches = [];
    $current = null;
    foreach (explode("\n", str_replace("\r", '', $r['stdout'])) as $line) {
      $line = rtrim($line);
      if (trim($line) === '') {
        continue;
      }
      $isCurrent = strpos($line, '* ') === 0;
      $name = trim($line, " *\t");
      $isRemote = strpos($name, 'remotes/') === 0;
      if ($isRemote) {
        $name = substr($name, strlen('remotes/'));
        // Skip the "origin/HEAD -> origin/main" pointer line itself.
        if (strpos($name, 'HEAD ->') === 0) {
          continue;
        }
        if (preg_match('/^(.+?)\s*->\s*(.+)$/', $name, $pm)) {
          $name = trim($pm[2]);
        }
      }
      if ($name === '' || strpos($name, '(HEAD detached') !== false || strpos($name, '(') === 0) {
        continue;
      }
      $branches[] = [
        'name'    => $name,
        'current' => $isCurrent,
        'remote'  => $isRemote,
      ];
      if ($isCurrent && $current === null) {
        $current = $name;
      }
    }
    return ['ok' => true, 'output' => trim($r['stdout']), 'error' => null, 'branches' => $branches, 'current' => $current];
  }

  /**
   * BRANCH CREATE — validate the name then `git checkout -b <name>`.
   */
  public function branchCreate(string $name): array
  {
    $this->validateBranchName($name);
    if ($err = $this->repoGuard()) {
      return $err;
    }
    $r = $this->run(['checkout', '-b', $name]);
    if (!$r['ok']) {
      return $this->failResult($r, "Could not create branch '{$name}'.");
    }
    return ['ok' => true, 'output' => trim($r['stdout'] . "\n" . $r['stderr']), 'error' => null, 'branch' => $name, 'created' => true];
  }

  /**
   * BRANCH SWITCH — validate the name then `git checkout <name>`.
   * A dirty tree refusal comes back as a clean error string from stderr.
   */
  public function branchSwitch(string $name): array
  {
    $this->validateBranchName($name);
    if ($err = $this->repoGuard()) {
      return $err;
    }
    $r = $this->run(['checkout', $name]);
    if (!$r['ok']) {
      return $this->failResult(
        $r,
        stripos($r['stderr'], 'would be overwritten') !== false
          ? 'You have uncommitted changes that would be overwritten — commit or stash first.'
          : "Could not switch to branch '{$name}'."
      );
    }
    return ['ok' => true, 'output' => trim($r['stdout'] . "\n" . $r['stderr']), 'error' => null, 'branch' => $name];
  }

  /**
   * PULL / PUSH — network operations with a raised 120s timeout.
   * Credential failures are surfaced as friendly guidance instead of raw
   * ssh noise (GIT_TERMINAL_PROMPT=0 already prevents interactive hangs).
   */
  public function pull(): array
  {
    return $this->remoteSync('pull');
  }

  public function push(): array
  {
    return $this->remoteSync('push');
  }

  /**
   * MERGE ABORT — back out of a conflicted merge (bonus escape hatch).
   */
  public function mergeAbort(): array
  {
    if ($err = $this->repoGuard()) {
      return $err;
    }
    $r = $this->run(['merge', '--abort']);
    if (!$r['ok']) {
      return $this->failResult($r, 'There is no merge in progress to abort.');
    }
    return ['ok' => true, 'output' => trim($r['stdout'] . "\n" . $r['stderr']), 'error' => null];
  }

  /* ── WRITE-OP HELPERS ───────────────────────────────────────── */

  /** Common precondition: git present and workspace is a repository. */
  private function repoGuard(): ?array
  {
    $info = $this->getInfo();
    if (!$info['available']) {
      return ['ok' => false, 'output' => '', 'error' => 'git was not found on this machine', 'exitCode' => -1];
    }
    if (!$info['isRepo']) {
      return ['ok' => false, 'output' => '', 'error' => 'Not a git repository — run "init" first.', 'exitCode' => -1];
    }
    return null;
  }

  /** Current branch name via rev-parse/symbolic-ref (null when detached). */
  private function currentBranch(): ?string
  {
    $b = $this->run(['rev-parse', '--abbrev-ref', 'HEAD']);
    $branch = $b['ok'] ? trim($b['stdout']) : '';
    if ($branch === '' || $branch === 'HEAD') {
      return null;
    }
    return $branch;
  }

  /** Validate + normalize path args for add/restore/checkout. */
  private function sanitizePaths(array $paths): array
  {
    $clean = [];
    foreach ($paths as $p) {
      if (!is_string($p)) {
        continue;
      }
      $p = ltrim(str_replace('\\', '/', trim($p)), '/');
      if ($p === '' || isset($clean[$p]) || count($clean) >= self::MAX_PATHS) {
        continue;
      }
      $this->validatePath($p); // fails hard on traversal/blocked segments
      $clean[$p] = true;
    }
    return array_keys($clean);
  }

  /** Branch names: letters/digits/dot/dash/slash, no "..", no leading "-". */
  private function validateBranchName(string $name): void
  {
    $name = trim($name);
    if (
      $name === ''
      || !preg_match(self::BRANCH_NAME_RE, $name)
      || strpos($name, '..') !== false
      || $name[0] === '-'
    ) {
      $this->security->fail('Invalid branch name: "' . $name . '".');
    }
  }

  /** Uniform failure envelope for write operations. */
  private function failResult(array $r, string $fallback): array
  {
    return [
      'ok'       => false,
      'output'   => trim($r['stdout']),
      'error'    => trim($r['stderr']) !== '' ? trim($r['stderr']) : $fallback,
      'exitCode' => $r['exitCode'],
    ];
  }

  /** pull/push core with the raised network timeout + auth guidance. */
  private function remoteSync(string $subcommand): array
  {
    if ($err = $this->repoGuard()) {
      return $err;
    }
    $r = $this->run([$subcommand], self::TIMEOUT_NETWORK);
    $blob = $r['stdout'] . "\n" . $r['stderr'];
    if (!$r['ok'] || $r['timedOut']) {
      if ($r['timedOut']) {
        return ['ok' => false, 'output' => trim($r['stdout']), 'error' => ucfirst($subcommand) . ' timed out after 120s.', 'exitCode' => -1];
      }
      if (preg_match('/permission denied|publickey|authentication|could not read from remote repository/i', $blob)) {
        return [
          'ok'       => false,
          'output'   => trim($r['stdout']),
          'error'    => 'Authentication required — set up SSH keys or a credential helper/token for this remote.',
          'exitCode' => $r['exitCode'],
        ];
      }
      if (preg_match('/no upstream|has no tracking|set-upstream/i', $blob)) {
        return [
          'ok'       => false,
          'output'   => trim($r['stdout']),
          'error'    => 'This branch has no upstream yet — run with --set-upstream once (e.g. from a terminal).',
          'exitCode' => $r['exitCode'],
        ];
      }
      return $this->failResult($r, ucfirst($subcommand) . ' failed.');
    }
    return ['ok' => true, 'output' => trim($blob) !== '' ? trim($blob) : (ucfirst($subcommand) . ' completed.'), 'error' => null, 'branch' => $this->currentBranch()];
  }

    /* ═══════════════════════════════════════════════════════════════
       PRIVATE HELPERS
       ═══════════════════════════════════════════════════════════════ */

  /** Validate a workspace-relative path before it reaches git. */
  private function validatePath(string $path): void
  {
    if (strpos($path, "\0") !== false) {
      $this->security->fail('Invalid path.');
    }
    $clean = ltrim(str_replace('\\', '/', trim($path)), '/');
    foreach (explode('/', $clean) as $segment) {
      if ($segment === '' || $segment === '.' || $segment === '..') {
        $this->security->fail('Invalid path: ' . $path);
      }
    }
    $this->security->checkBlockedSegments($clean);
  }

  /**
   * Run one git command inside the workspace, with a timeout.
   *
   * ★ WINDOWS QUIRK: since PHP 8.0, escapeshellarg() on Windows replaces
   * '%' and '!' with spaces (cmd.exe-injection hardening in php-src).
   * That silently corrupts arguments like --pretty=format:%H%x00%h… and
   * any path containing '%'. When such an argument is detected we switch
   * to proc_open's ARGV-ARRAY form instead — no shell, no escaping at
   * all, which is strictly SAFER than escaping and byte-exact everywhere.
   *
   * @param array  $args      Arguments AFTER "git" (whitelisted upstream)
   * @param int    $timeout   Per-command timeout override (pull/push use 120s)
   * @param string $stdinData Optional data fed to the command's stdin
   *                            (commit messages use this so '%' survives)
   * @return array ['ok' => bool, 'stdout' => string, 'stderr' => string, 'exitCode' => int, 'timedOut' => bool]
   */
  private function run(array $args, int $timeout = self::TIMEOUT, string $stdinData = ''): array
  {
    $direct = false;
    foreach ($args as $a) {
      if (is_string($a) && strpbrk($a, '%!') !== false) {
        $direct = true;
        break;
      }
    }

    if ($direct) {
      // argv-array form: every argument reaches git byte-for-byte.
      $spec  = array_merge([$this->binary], self::GLOBAL_ARGS, $args);
      $proc  = @proc_open($spec, $this->descriptors(), $pipes, $this->workspaceRoot, $this->childEnv());
      return $this->collect($proc, $pipes, $timeout, $stdinData);
    }

    $cmd = escapeshellarg($this->binary);
    foreach (array_merge(self::GLOBAL_ARGS, $args) as $a) {
      $cmd .= ' ' . escapeshellarg((string) $a);
    }
    $proc = @proc_open($cmd, $this->descriptors(), $pipes, $this->workspaceRoot, $this->childEnv());
    return $this->collect($proc, $pipes, $timeout, $stdinData);
  }

  /** Global git arguments applied to every invocation. */
  private const GLOBAL_ARGS = ['--no-pager', '-c', 'core.quotepath=false'];

  /** Stdio pipe spec shared by both invocation forms. */
  private function descriptors(): array
  {
    return [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
  }

  /**
   * Environment: never let git pause for a password/prompt.
   */
  private function childEnv(): array
  {
    $base = [];
    $allEnv = @getenv();
    if (is_array($allEnv)) {
      $base = $allEnv;
    }
    return array_merge($base, [
      'GIT_TERMINAL_PROMPT' => '0',
      'GIT_PAGER'           => 'cat',
      'GIT_OPTIONAL_LOCKS'  => '0',
    ]);
  }

  /**
   * Pump a started process to completion: feed optional stdin, enforce the
   * timeout + runaway-output caps, gather stdout/stderr.
   */
  private function collect($proc, array $pipes, int $timeout, string $stdinData = ''): array
  {
    if (!is_resource($proc)) {
      return ['ok' => false, 'stdout' => '', 'stderr' => 'Could not start git', 'exitCode' => -1];
    }
    if ($stdinData !== '') {
      // Small payloads only (a commit message fits the pipe buffer), so a
      // blocking write here cannot deadlock against unread output.
      @fwrite($pipes[0], $stdinData);
    }
    fclose($pipes[0]);
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);

    $stdout   = '';
    $stderr   = '';
    $exitCode = -1;
    $timedOut = false;
    $start    = microtime(true);
    while (true) {
      $stdout .= (string) stream_get_contents($pipes[1]);
      $stderr .= (string) stream_get_contents($pipes[2]);
      // Runaway-output guard
      if (strlen($stdout) > self::MAX_OUTPUT * 2) {
        proc_terminate($proc, $this->isWindows ? 1 : 9);
        break;
      }
      $st = proc_get_status($proc);
      if (!$st['running']) {
        $exitCode = (int) $st['exitcode'];
        $stdout .= (string) stream_get_contents($pipes[1]);
        $stderr .= (string) stream_get_contents($pipes[2]);
        break;
      }
      if ((microtime(true) - $start) > $timeout) {
        $timedOut = true;
        proc_terminate($proc, $this->isWindows ? 1 : 9);
        usleep(50000);
        break;
      }
      usleep(10000);
    }
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($proc);

    return [
      'ok'       => !$timedOut && $exitCode === 0,
      'stdout'   => substr($stdout, 0, self::MAX_OUTPUT),
      'stderr'   => $stderr,
      'exitCode' => $exitCode,
      'timedOut' => $timedOut,
    ];
  }
}
