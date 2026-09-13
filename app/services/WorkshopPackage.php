<?php

declare(strict_types=1);
/**
 * ═══════════════════════════════════════════════════════════════════════════
 *  QUIRKY IDE — WORKSHOP COMMUNITY PACKAGES (dynamic Workshop · Manifest v1)
 * ═══════════════════════════════════════════════════════════════════════════
 *
 *  Lets community members publish a repository (typically GitHub) holding a
 *  `quirky.workshop.json` manifest, and lets users paste a link to install it.
 *  Packages contribute THEMES (css), SNIPPET PACKS (autocomplete pools),
 *  simple JS EXTENSIONS, and TOOLCHAIN items (Termux pkg names).
 *
 *  PIPELINE (install / update share one path):
 *    1. parseSource()   github <o>/<r>[@ref] | direct zip URL | raw manifest URL
 *    2. download()      hardened cURL (mirrors IdePkg discipline — see note)
 *    3. extract()       PharData (ext/zip is ABSENT on-device AND on desktop
 *                       Laragon) into a neutral staging dir, enforcing:
 *                         • zip-slip ('..'/absolute/drive/backslash rejected)
 *                         • per-entry ≤10 MB, totals ≤25 MB compressed /
 *                           60 MB unpacked / ≤4000 files
 *    4. locate manifest at archive ROOT or single top-level subdir
 *    5. VALIDATE BEFORE staging (validateManifest + type-specific checks)
 *    6. promote: atomic rename of the validated tree over packages/<id>/
 *    7. registry JSON at IDE_APP/.workshop/packages.json (LOCK_EX)
 *
 *  ★ STAGING-PATH NOTE (deliberate deviation from the original plan of
 *    extracting directly into packages/<id>/.staging-<ts>/): the package id
 *    lives INSIDE the manifest, so it is unknowable until after download +
 *    extraction. Staging therefore happens in IDE_APP/.workshop/tmp/stage-<ts>
 *    and promotion is a single atomic rename (old tree moved aside first, then
 *    new tree renamed into place, old tree deleted). Containment and the
 *    never-partial-state property are identical; failed installs leave any
 *    previously installed version untouched.
 *
 *  ★ WHY cURL HELPERS ARE DUPLICATED FROM IdePkg: those methods are private
 *    and welded to the Termux toolbox layout (prefix db dirs, mirror rotation,
 *    pkg tier guards). Constructing an IdePkg here would drag all of that in
 *    for three ~60-line helpers. Mirrored discipline instead: bundled CA chain,
 *    low-speed abort, redirects ≤5, HTTP/1.1, hard timeout, progress events.
 *
 *  REGISTRY: packages.json is fully separate from apache's extensions.json —
 *  the static Workshop flows are never touched by this class.
 *
 *  RAW-MANIFEST MODE: a URL pointing at quirky.workshop.json installs
 *  SINGLE-FILE packs only — the manifest plus individually-fetched entrypoint
 *  files (≤12 files). Whole snippet directories cannot be enumerated over
 *  plain HTTP, so in raw mode entrypoints.snippets must name explicit .json
 *  pool file(s), not a directory.
 *
 *  SSE PROTOCOL: identical to workshop-apache-install — events
 *  status{message} / progress{text} / out{text} / err{text} / error{message} /
 *  done{success,…} — so the frontend reader in mobile-workshop.js is reused
 *  unchanged.
 *
 *  CLASS LOADING: this file defines ALL new classes for the dynamic Workshop
 *  (currently just IdeWorkshopPackage). It is pulled in via require_once from
 *  IdeWorkshop::getCommunityPackages() and from routes/workshop.php — api.php's
 *  classmap did not need to change.
 * ═══════════════════════════════════════════════════════════════════════════
 */
class IdeWorkshopPackage
{
  /** Exact manifest file name (spec A). */
  public const MANIFEST_NAME = 'quirky.workshop.json';

  private const ID_RE        = '/^[a-z0-9][a-z0-9._-]{2,63}$/';
  private const VERSION_RE   = '/^\d+\.\d+(\.\d+)?(-[\w.]+)?$/';
  private const TYPES        = ['theme', 'snippets', 'extension', 'toolchain'];
  private const TERMUX_PKG_RE = '/^[a-z0-9][a-z0-9.+-]{0,63}$/';
  private const HOST_RE      = '/^(\*\.)?[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/';
  private const ENGINES_RE   = '/^(>=|<=|>|<|=)?\d+(\.\d+)*$/';

  /** Safety budgets. */
  private const MAX_ENTRY_BYTES      = 10 * 1024 * 1024;   // 10 MB per archive entry
  private const MAX_COMPRESSED_BYTES = 25 * 1024 * 1024;   // 25 MB compressed download
  private const MAX_UNPACKED_BYTES   = 60 * 1024 * 1024;   // 60 MB unpacked
  private const MAX_FILES            = 4000;
  private const MAX_RAW_FILES        = 12;                 // raw-manifest single-file packs
  private const DOWNLOAD_TIMEOUT     = 120;                // seconds per transfer

  /** Extensions this runtime will ever serve or apply (spec C mime table). */
  private const ALLOWED_EXT = ['css', 'js', 'json', 'txt', 'png', 'svg', 'woff2'];

  private array $config;
  private IdeSecurity $security;
  private string $packagesDir;
  private string $tmpDir;
  private string $registryFile;
  private string $prefix;
  private ?string $caCert = null;

  /** @var callable|null function(string $event, array $data): void */
  private $emitter = null;

  public function __construct(array $config, IdeSecurity $security)
  {
    $this->config   = $config;
    $this->security = $security;

    $stateDir = IDE_APP . '/.workshop';
    $this->packagesDir = $stateDir . '/packages';
    $this->tmpDir      = $stateDir . '/tmp';
    $this->registryFile = $stateDir . '/packages.json';

    foreach ([$stateDir, $this->packagesDir, $this->tmpDir] as $d) {
      if (!is_dir($d)) @mkdir($d, 0777, true);
    }

    $prefix = getenv('QUIRKY_PREFIX');
    if ($prefix === false || $prefix === '') {
      $prefix = IDE_ROOT . '/.usr';
    }
    $this->prefix = rtrim(str_replace('\\', '/', (string) $prefix), '/');
  }

  /* ═══════════════════════════════════════════════════════════
      EMITTER (SSE)
      ═══════════════════════════════════════════════════════════ */

  /**
   * Attach the SSE emitter. Event contract (identical to apache installer):
   *   status  {message}    transient headline
   *   progress{text}       download progress lines
   *   out     {text}       normal log line
   *   err     {text}       error-colored log line (non-fatal)
   *   error   {message}    fatal problem
   *   done    {success,…}  terminal event
   */
  public function setEmitter(?callable $emitter): void
  {
    $this->emitter = $emitter;
  }

  private function emit(string $event, array $data): void
  {
    if ($this->emitter !== null) {
      ($this->emitter)($event, $data);
    }
  }

  private function emitOut(string $text): void
  {
    $this->emit('out', ['text' => $text]);
  }

  /* ═══════════════════════════════════════════════════════════
      PUBLIC OPERATIONS
      ═══════════════════════════════════════════════════════════ */

  /** Install (or first-time add) from a user-supplied source string. */
  public function installStream(string $source): void
  {
    try {
      $row = $this->installFromSource(trim($source), null);
      $this->emit('status', [
        'message' => '✓ Installed ' . (string) $row['name'] . ' ' . (string) $row['version']
          . ' — review its permissions, then enable.',
      ]);
      $this->emit('done', [
        'success' => true,
        'id'      => (string) $row['id'],
        'version' => (string) $row['version'],
      ]);
    } catch (\Throwable $e) {
      $this->emit('error', ['message' => $e->getMessage()]);
      $this->emit('done', ['success' => false]);
    }
  }

  /** Re-run the pipeline for an installed package, keeping its enable state. */
  public function updateStream(string $id): void
  {
    try {
      $registry = $this->loadRegistry();
      if (!isset($registry['packages'][$id])) {
        throw new RuntimeException('Unknown package: ' . $id);
      }
      $row = $registry['packages'][$id];
      $input = (string) ($row['source']['input'] ?? '');
      if ($input === '') {
        throw new RuntimeException('Package has no recorded source to update from.');
      }
      $this->emit('status', ['message' => 'Updating ' . (string) $row['name'] . '…']);
      $fresh = $this->installFromSource($input, $row);
      $this->emit('status', [
        'message' => '✓ Updated to ' . (string) $fresh['version'],
      ]);
      $this->emit('done', [
        'success' => true,
        'id'      => $id,
        'version' => (string) $fresh['version'],
      ]);
    } catch (\Throwable $e) {
      $this->log()->error('PACKAGE', "Update failed for $id: " . $e->getMessage());
      $this->emit('error', ['message' => $e->getMessage()]);
      $this->emit('done', ['success' => false]);
    }
  }

  /** Registry summaries for the UI — no filesystem internals exposed. */
  public function catalog(): array
  {
    $registry = $this->loadRegistry();
    $out = [];
    foreach ($registry['order'] as $id) {
      $row = $registry['packages'][$id] ?? null;
      if (!is_array($row)) continue;
      $out[] = $this->summaryOf($row);
    }
    return $out;
  }

  public function has(string $id): bool
  {
    if (!preg_match(self::ID_RE, $id)) return false;
    $registry = $this->loadRegistry();
    return isset($registry['packages'][$id]);
  }

  /** Enable/disable. $enabled=null flips current state. Returns summary. */
  public function toggle(string $id, ?bool $enabled = null): array
  {
    if (!preg_match(self::ID_RE, $id)) {
      throw new InvalidArgumentException('Invalid package id.');
    }
    $registry = $this->loadRegistry();
    if (!isset($registry['packages'][$id])) {
      throw new RuntimeException('Unknown package: ' . $id);
    }
    $row = $registry['packages'][$id];
    $newState = $enabled ?? empty($row['enabled']);
    $row['enabled'] = (bool) $newState;
    $registry['packages'][$id] = $row;
    $this->saveRegistry($registry);

    $this->log()->info('PACKAGE', "Package $id " . ($row['enabled'] ? 'enabled' : 'disabled'));
    return $this->summaryOf($row);
  }

  /** Disable + remove files + drop registry row. */
  public function uninstall(string $id): array
  {
    if (!preg_match(self::ID_RE, $id)) {
      throw new InvalidArgumentException('Invalid package id.');
    }
    $registry = $this->loadRegistry();
    if (!isset($registry['packages'][$id])) {
      throw new RuntimeException('Unknown package: ' . $id);
    }
    $name = (string) ($registry['packages'][$id]['name'] ?? $id);

    $target = $this->packagesDir . '/' . $id;
    if (is_dir($target)) {
      $this->assertInsidePackagesDir($target);
      $this->rmtree($target);
    }

    unset($registry['packages'][$id]);
    $registry['order'] = array_values(array_diff($registry['order'], [$id]));
    $this->saveRegistry($registry);

    $this->log()->success('PACKAGE', "Removed package $id ($name)");
    return ['removed' => true, 'id' => $id];
  }

  /* ═══════════════════════════════════════════════════════════
      CORE PIPELINE
      ═══════════════════════════════════════════════════════════ */

  /**
   * Full pipeline for one source string. $previousRow (when updating) keeps
   * the enabled flag across the version bump.
   *
   * @return array The promoted registry row.
   */
  private function installFromSource(string $source, ?array $previousRow): array
  {
    if ($source === '') {
      throw new RuntimeException('Source is required (github owner/repo, a .zip URL, or a quirky.workshop.json URL).');
    }
    @set_time_limit(0);

    $src = $this->parseSource($source);
    if ($src === null) {
      throw new RuntimeException(
        'Unsupported source. Use "owner/repo" (@branch or @tag optional), a direct https .zip link, '
        . 'or a link to a quirky.workshop.json file.'
      );
    }

    $work = $this->tmpDir . '/job-' . date('Ymd-His') . '-' . getmypid();
    if (!is_dir($work)) @mkdir($work, 0777, true);

    try {
      if ($src['kind'] === 'raw-manifest') {
        $this->emit('status', ['message' => 'Fetching manifest…']);
        $manifest = $this->fetchRawPackage($src, $work . '/stage');
      } else {
        $archive = $work . '/package.zip';
        $label = $src['kind'] === 'github-zip' ? 'package' : 'download';
        $this->emit('status', ['message' => 'Downloading package…']);
        try {
          $this->download($src['url'], $archive, $label, self::MAX_COMPRESSED_BYTES);
        } catch (\Throwable $first) {
          // GitHub default-branch guess: "main" 404s on repos still using
          // "master" — retry once with the alternate guess before failing.
          if (!empty($src['altUrl']) && strpos($first->getMessage(), 'HTTP 404') !== false) {
            $this->emitOut('↓ main branch not found — trying master…');
            $this->download($src['altUrl'], $archive, $label, self::MAX_COMPRESSED_BYTES);
          } else {
            throw $first;
          }
        }

        $this->emit('status', ['message' => 'Extracting…']);
        $this->extractArchive($archive, $work . '/stage');

        $rootRel = $this->locateManifestRoot($work . '/stage');
        $this->flattenStage($work . '/stage', $rootRel);

        $manifest = $this->readManifestFile($work . '/stage/' . self::MANIFEST_NAME);
      }

      /* ── VALIDATE BEFORE STAGING ── */
      $errors = $this->validateManifest($manifest);
      $typeChecks = $this->validateContents($manifest, $work . '/stage');
      $errors = array_merge($errors, $typeChecks);
      if (!empty($errors)) {
        throw new RuntimeException('Manifest rejected: ' . implode(' ', $errors));
      }

      $id = (string) $manifest['id'];
      $final = $this->packagesDir . '/' . $id;
      $this->assertInsidePackagesDir($final);

      /* ── PROMOTE (atomic swap) ── */
      $retired = null;
      if (is_dir($final)) {
        $retired = $this->tmpDir . '/old-' . date('Ymd-His') . '-' . getmypid();
        if (!@rename($final, $retired)) {
          throw new RuntimeException('Could not move the previous version aside.');
        }
      }
      if (!@rename($work . '/stage', $final)) {
        if ($retired !== null) @rename($retired, $final); // restore
        throw new RuntimeException('Could not move the validated package into place.');
      }
      if ($retired !== null) $this->rmtree($retired);

      $enabled = isset($previousRow['enabled']) ? (bool) $previousRow['enabled'] : false;

      // Themes imply the cssInject capability even when the manifest omits it
      // (validateManifest checks the type requirement; normalize here so the
      // runtime host applies the stylesheet without extra manifest ceremony).
      $caps = is_array($manifest['capabilities'] ?? null) ? $manifest['capabilities'] : [];
      if (($manifest['type'] ?? '') === 'theme' && !isset($caps['cssInject'])) {
        $caps['cssInject'] = true;
      }

      $registry = $this->loadRegistry();
      $row = [
        // Manifest passthrough (validated fields only)
        'id'           => $id,
        'name'         => (string) $manifest['name'],
        'version'      => (string) $manifest['version'],
        'type'         => (string) $manifest['type'],
        'description'  => (string) ($manifest['description'] ?? ''),
        'icon'         => isset($manifest['icon']) ? (string) $manifest['icon'] : '',
        'author'       => $manifest['author'] ?? [],
        'engines'      => $manifest['engines'] ?? [],
        'entrypoints'  => $manifest['entrypoints'] ?? [],
        'capabilities' => $caps,
        'toolchain'    => $manifest['toolchain'] ?? [],
        // Bookkeeping
        'enabled'      => $enabled,
        'installedAt'  => time(),
        'source'       => ['kind' => $src['kind'], 'url' => $src['url'], 'ref' => $src['ref'], 'input' => $source],
        'filesRoot'    => '', // trees are flattened to packages/<id>/ at promotion
      ];
      if (!isset($registry['packages'][$id])) {
        $registry['order'][] = $id;
      }
      $registry['packages'][$id] = $row;
      $this->saveRegistry($registry);

      $verb = $previousRow !== null ? 'Updated' : 'Installed';
      $this->log()->success('PACKAGE', "$verb package $id {$row['version']} from {$src['kind']} ({$src['url']})");
      return $row;
    } finally {
      $this->rmtree($work);
    }
  }

  /* ═══════════════════════════════════════════════════════════
      SOURCE RESOLUTION
      ═══════════════════════════════════════════════════════════ */

  /**
   * Accepts:
   *   owner/repo                → codeload …/refs/heads/main (default branch guess: main, master)
   *   owner/repo@dev            → codeload …/refs/heads/dev
   *   owner/repo@v1.2           → codeload …/refs/tags/v1.2 (v-prefixed refs look like tags)
   *   https://github.com/o/r[/tree/x]
   *   https://<any>/path/pkg.zip → direct zip
   *   https://<any>/…/quirky.workshop.json → raw-manifest single-file pack
   *
   * @return array{kind:string,url:string,ref:?string,altUrl?:string}|null
   */
  public function parseSource(string $source): ?array
  {
    $source = trim($source);
    if ($source === '') return null;

    // Strip a leading "git+" / trailing ".git" for github shorthands.
    $looksGithub = preg_match('~^(?:https?://)?(?:www\.)?github\.com/([A-Za-z0-9_.-]+)/([A-Za-z0-9_.-]+?)(?:\.git)?(?:/tree/([^/\s?]+))?(?:[/?].*)?$~i', $source, $m);

    if ($looksGithub && !str_ends_with(strtolower($source), '.json')) {
      $owner = $m[1];
      $repo  = $m[2];
      $ref   = $m[3] ?? '';
      return $this->codeloadSource($owner, $repo, $ref);
    }

    // Bare shorthand: owner/repo[@ref]
    if (preg_match('#^([A-Za-z0-9_.-]+)/([A-Za-z0-9_.-]+)(?:@([^\s@]+))?$#', $source, $m)
      && !str_contains($source, '://')
      && strtolower(pathinfo($m[2], PATHINFO_EXTENSION)) !== 'json'
    ) {
      return $this->codeloadSource($m[1], $m[2], $m[3] ?? '');
    }

    // Anything else must be an http(s) URL.
    if (!preg_match('#^https?://#i', $source)) return null;
    $host = strtolower((string) parse_url($source, PHP_URL_HOST));
    if ($host === '') return null;
    // Block trivially dangerous targets regardless of scheme.
    foreach (['localhost', '127.0.0.1', '0.0.0.0', '::1', '[::1]', '169.254.169.254'] as $bad) {
      if ($host === $bad) return null;
    }
    if (preg_match('/^10\.|^192\.168\.|^172\.(1[6-9]|2\d|3[01])\./', $host)) return null;

    if (str_ends_with(strtolower(parse_url($source, PHP_URL_PATH) ?? ''), '/' . self::MANIFEST_NAME)
      || str_ends_with(strtolower($source), self::MANIFEST_NAME)) {
      return ['kind' => 'raw-manifest', 'url' => $source, 'ref' => null];
    }

    return ['kind' => 'zip-url', 'url' => $source, 'ref' => null];
  }

  private function codeloadSource(string $owner, string $repo, string $ref): array
  {
    $owner = rawurlencode($owner);
    $repo  = rawurlencode(rtrim($repo, '.'));
    $altUrl = null;
    if ($ref === '') {
      // No ref given — GitHub serves the default branch only by name. Guess
      // "main" first; installFromSource retries with "master" on HTTP 404.
      $ref = 'main';
      $altUrl = "https://codeload.github.com/$owner/$repo/zip/refs/heads/master";
    }
    $kind = preg_match('/^v\d/i', $ref) ? 'tags' : 'heads';
    $out = [
      'kind' => 'github-zip',
      'url'  => "https://codeload.github.com/$owner/$repo/zip/refs/$kind/" . rawurlencode($ref),
      'ref'  => $ref,
    ];
    if ($altUrl !== null) $out['altUrl'] = $altUrl;
    return $out;
  }

  /* ═══════════════════════════════════════════════════════════
      NETWORK (hardened cURL — mirrors IdePkg discipline)
      ═══════════════════════════════════════════════════════════ */

  /**
   * CA bundle fallback chain: config override → app-bundled cacert.pem
   * (copied into the prefix by MainActivity.java) → php.ini pointers.
   */
  private function resolveCaCert(): ?string
  {
    if ($this->caCert !== null) return $this->caCert ?: null;
    $candidates = [
      (string) ($this->config['pkg']['cacert_path'] ?? ''),
      $this->prefix . '/etc/tls/cacert.pem',
      (string) @ini_get('openssl.cafile'),
      (string) @ini_get('curl.cainfo'),
    ];
    foreach ($candidates as $c) {
      if ($c !== '' && is_file($c)) {
        $this->caCert = $c;
        return $c;
      }
    }
    $this->caCert = '';
    return null;
  }

  /**
   * Download $url to $dest with caps + live progress. Throws RuntimeException.
   */
  private function download(string $url, string $dest, string $label, int $maxBytes): int
  {
    if (!function_exists('curl_init')) {
      throw new RuntimeException('PHP cURL extension is required to install packages.');
    }
    $ca = $this->resolveCaCert();

    $fp = @fopen($dest, 'wb');
    if ($fp === false) {
      throw new RuntimeException('Cannot create temporary download file.');
    }

    $attempt = function () use ($url, $fp, $ca, $label, $maxBytes): array {
      rewind($fp);
      ftruncate($fp, 0);
      $lastPct = -1;
      $ch = curl_init($url);
      curl_setopt_array($ch, [
        CURLOPT_FILE            => $fp,
        CURLOPT_FOLLOWLOCATION  => true,
        CURLOPT_MAXREDIRS       => 5,
        CURLOPT_TIMEOUT         => self::DOWNLOAD_TIMEOUT,
        CURLOPT_CONNECTTIMEOUT  => 15,
        // Same low-speed abort as IdePkg: kill mirrors crawling under 10 KB/s
        // for 15 s instead of hanging the SSE stream for minutes.
        CURLOPT_LOW_SPEED_LIMIT => 10240,
        CURLOPT_LOW_SPEED_TIME  => 15,
        CURLOPT_HTTP_VERSION    => CURL_HTTP_VERSION_1_1,
        // Verify whenever we have any trust anchor. Without one, curl still
        // uses its compiled-in default store; the caller retries unverified
        // ONLY after an SSL-specific failure, loudly.
        CURLOPT_SSL_VERIFYPEER  => true,
        CURLOPT_SSL_VERIFYHOST  => 2,
        CURLOPT_USERAGENT       => 'QuirkyIDE-workshop/1.0',
        CURLOPT_NOPROGRESS      => false,
        CURLOPT_PROGRESSFUNCTION => function ($ch, $dlTotal, $dlNow) use (&$lastPct, $label, $maxBytes): int {
          if ($dlNow > $maxBytes) return 1; // non-zero aborts the transfer
          if ($dlTotal > 0) {
            $pct = (int) floor(($dlNow / $dlTotal) * 100);
            if ($pct !== $lastPct && ($pct - $lastPct >= 5 || $pct >= 100)) {
              $lastPct = $pct;
              $this->emit('progress', ['text' => sprintf(
                '↓ %s %3d%% [%s / %s]',
                $label,
                $pct,
                $this->fmtBytes((int) $dlNow),
                $this->fmtBytes((int) $dlTotal)
              )]);
            }
          }
          return 0;
        },
      ]);
      if ($ca !== null) {
        curl_setopt($ch, CURLOPT_CAINFO, $ca);
      }
      $ok = curl_exec($ch);
      $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
      $sslErr = stripos((string) curl_error($ch), 'SSL') !== false
        || stripos((string) curl_error($ch), 'certificate') !== false;
      $effective = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
      return [$ok, $code, $sslErr, $effective, (string) curl_error($ch)];
    };

    try {
      [$ok, $code, $sslErr, $effective, $err] = $attempt();
      if (($ok === false || $code !== 200) && $sslErr) {
        $this->emit('err', ['text' => '⚠ TLS verification failed and no trusted CA bundle was found — retrying UNVERIFIED. Only continue if you trust this network.']);
        [$ok, $code, $sslErr, $effective, $err] = $attempt();
      }
      if ($ok === false) {
        throw new RuntimeException('Download failed: ' . ($err !== '' ? $err : 'unknown cURL error'));
      }
      if ($code !== 200) {
        throw new RuntimeException("Download failed: server answered HTTP $code.");
      }
      if (!preg_match('#^https?://#i', $effective)) {
        throw new RuntimeException('Download redirected to a non-http(s) URL.');
      }
      clearstatcache(true, $dest);
      $size = (int) @filesize($dest);
      if ($size <= 0) {
        throw new RuntimeException('Downloaded file is empty.');
      }
      if ($size > $maxBytes) {
        throw new RuntimeException('Download exceeds the ' . $this->fmtBytes($maxBytes) . ' safety limit.');
      }
      return $size;
    } finally {
      fclose($fp);
    }
  }

  /** Small GET-to-string for raw manifests / individual pack files. */
  private function fetchUrl(string $url, int $maxBytes): string
  {
    if (!function_exists('curl_init')) {
      throw new RuntimeException('PHP cURL extension is required.');
    }
    $ca = $this->resolveCaCert();
    $ch = curl_init($url);
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER  => true,
      CURLOPT_FOLLOWLOCATION  => true,
      CURLOPT_MAXREDIRS       => 5,
      CURLOPT_TIMEOUT         => 45,
      CURLOPT_CONNECTTIMEOUT  => 15,
      CURLOPT_LOW_SPEED_LIMIT => 10240,
      CURLOPT_LOW_SPEED_TIME  => 15,
      CURLOPT_HTTP_VERSION    => CURL_HTTP_VERSION_1_1,
      CURLOPT_SSL_VERIFYPEER  => true,
      CURLOPT_SSL_VERIFYHOST  => 2,
      CURLOPT_USERAGENT       => 'QuirkyIDE-workshop/1.0',
    ]);
    if ($ca !== null) curl_setopt($ch, CURLOPT_CAINFO, $ca);
    $result = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = (string) curl_error($ch);
    if ($result === false || $code !== 200) {
      throw new RuntimeException("Fetch failed (HTTP $code" . ($err !== '' ? ", $err" : '') . ').');
    }
    if (strlen((string) $result) > $maxBytes) {
      throw new RuntimeException('Remote file exceeds the ' . $this->fmtBytes($maxBytes) . ' safety limit.');
    }
    return (string) $result;
  }

  /* ═══════════════════════════════════════════════════════════
      EXTRACTION (PharData — ext/zip is ABSENT everywhere we run)
      ═══════════════════════════════════════════════════════════ */

  /**
   * Extract a zip/tar into $stageDir with per-entry enforcement. We copy
   * entry CONTENTS out ourselves (never in-place extraction), so archive
   * symlinks can only ever materialize as regular files — and anything that
   * somehow lands as a link is rejected by the post-copy is_link() check.
   *
   * @param array|null $manifestOut receives nothing; kept for symmetry.
   */
  private function extractArchive(string $archivePath, string $stageDir): void
  {
    if (!is_dir($stageDir)) @mkdir($stageDir, 0777, true);

    try {
      $phar = new \PharData($archivePath);
    } catch (\Throwable $e) {
      throw new RuntimeException('Not a readable zip archive (' . $this->shortErr($e) . ').');
    }

    $archPrefix = 'phar://' . str_replace('\\', '/', $archivePath);
    $totalBytes = 0;
    $fileCount = 0;

    try {
      $entries = [];
      foreach (new \RecursiveIteratorIterator(
        $phar,
        \RecursiveIteratorIterator::LEAVES_ONLY
      ) as $spl) {
        /** @var \SplFileInfo $spl */
        $p = str_replace('\\', '/', (string) $spl->getPathname());
        if (strpos($p, $archPrefix . '/') !== 0) continue; // unexpected phar format — ignore
        $entries[] = substr($p, strlen($archPrefix . '/'));
      }
    } catch (\Throwable $e) {
      throw new RuntimeException('Could not read the archive (' . $this->shortErr($e) . ').');
    }

    if (empty($entries)) {
      throw new RuntimeException('Archive is empty.');
    }
    if (count($entries) > self::MAX_FILES) {
      throw new RuntimeException('Archive holds more than ' . self::MAX_FILES . ' entries.');
    }

    foreach ($entries as $rel) {
      if ($rel === '') continue;
      if (!$this->isSafeArchiveName($rel)) {
        throw new RuntimeException("Unsafe archive entry rejected: $rel");
      }

      $abs = 'phar://' . str_replace('\\', '/', $archivePath) . '/' . $rel;
      if (is_dir($abs)) {
        $this->mkdirParents($stageDir . '/' . $rel);
        continue;
      }

      $size = (int) @filesize($abs);
      if ($size > self::MAX_ENTRY_BYTES) {
        throw new RuntimeException("Archive entry '$rel' exceeds the 10 MB per-file limit.");
      }
      $totalBytes += max(0, $size);
      if ($totalBytes > self::MAX_UNPACKED_BYTES) {
        throw new RuntimeException('Archive unpacks beyond the 60 MB safety limit.');
      }
      $fileCount++;
      if ($fileCount > self::MAX_FILES) {
        throw new RuntimeException('Archive holds more than ' . self::MAX_FILES . ' files.');
      }

      $target = $stageDir . '/' . $rel;
      $normTarget = str_replace('\\', '/', $target);
      $normStage = rtrim(str_replace('\\', '/', $stageDir), '/') . '/';
      if (strpos($normTarget, $normStage) !== 0) {
        throw new RuntimeException("Unsafe archive path rejected: $rel");
      }
      $this->mkdirParents(dirname($target));

      $in = @fopen($abs, 'rb');
      $out = @fopen($target, 'wb');
      if ($in === false || $out === false) {
        if ($in !== false) fclose($in);
        if ($out !== false) fclose($out);
        throw new RuntimeException("Could not extract '$rel'.");
      }
      while (($chunk = fread($in, 65536)) !== false && $chunk !== '') {
        fwrite($out, $chunk);
      }
      fclose($in);
      fclose($out);

      if (is_link($target)) {
        throw new RuntimeException("Symlink entry rejected: $rel");
      }
    }
  }

  /**
   * Archive entry-name allowlist: relative, no traversal, no drive letters,
   * no backslashes, no NUL/control chars, no leading dots segments.
   */
  private function isSafeArchiveName(string $rel): bool
  {
    if ($rel === '' || strlen($rel) > 250) return false;
    if (strpos($rel, "\0") !== false) return false;
    if (preg_match('/[\x00-\x1f]/', $rel)) return false;
    if (strpos($rel, '\\') !== false) return false;
    if (preg_match('#^[A-Za-z]:#', $rel)) return false;      // drive letter
    if (str_starts_with($rel, '/')) return false;            // absolute
    if (preg_match('~^(?:\.\./|\./)(?:.*(?:/\.\./|/\./))?$~', $rel)) return false; // quick reject
    foreach (explode('/', $rel) as $seg) {
      if ($seg === '' || $seg === '.' || $seg === '..') return false;
    }
    return true;
  }

  /**
   * Find quirky.workshop.json at the archive ROOT or inside ONE top-level
   * subdir (GitHub wraps zips in <repo>-<sha>/).
   *
   * @return string Relative subdir ('' for root, e.g. 'repo-main')
   */
  private function locateManifestRoot(string $stageDir): string
  {
    if (is_file($stageDir . '/' . self::MANIFEST_NAME)) return '';
    $dirs = [];
    foreach ((array) @scandir($stageDir) as $e) {
      if ($e === '.' || $e === '..') continue;
      if (is_dir($stageDir . '/' . $e) && !is_link($stageDir . '/' . $e)) {
        $dirs[] = $e;
      }
    }
    $withManifest = array_values(array_filter(
      $dirs,
      fn ($d) => is_file($stageDir . '/' . $d . '/' . self::MANIFEST_NAME)
    ));
    if (count($dirs) === 1 && count($withManifest) === 1) {
      return $dirs[0];
    }
    throw new RuntimeException(
      self::MANIFEST_NAME . ' not found at the archive root or in a single top-level folder.'
    );
  }

  /** Move the manifest-root subtree up to the stage root (flatten wrapper). */
  private function flattenStage(string $stageDir, string $rootRel): void
  {
    if ($rootRel === '') return;
    $from = $stageDir . '/' . $rootRel;
    foreach ((array) @scandir($from) as $e) {
      if ($e === '.' || $e === '..') continue;
      if (!@rename($from . '/' . $e, $stageDir . '/' . $e)) {
        throw new RuntimeException('Could not normalize the package layout.');
      }
    }
    $this->rmtree($from);
  }

  /* ═══════════════════════════════════════════════════════════
      RAW-MANIFEST MODE (single-file packs, ≤12 files)
      ═══════════════════════════════════════════════════════════ */

  /**
   * Fetch a quirky.workshop.json plus its referenced entrypoint files
   * individually into $stageDir. Directories cannot be enumerated over HTTP,
   * so entrypoints.snippets must point at explicit .json pool file(s).
   *
   * @return array Parsed manifest.
   */
  private function fetchRawPackage(array $src, string $stageDir): array
  {
    if (!is_dir($stageDir)) @mkdir($stageDir, 0777, true);
    $base = preg_replace('~(^|/)[^/]*$~', '$1', $src['url']); // dirname + slash

    $raw = $this->fetchUrl($src['url'], self::MAX_ENTRY_BYTES);
    $manifest = json_decode($raw, true);
    if (!is_array($manifest)) {
      throw new RuntimeException('The manifest URL did not return valid JSON.');
    }
    @file_put_contents($stageDir . '/' . self::MANIFEST_NAME, $raw, LOCK_EX);

    // Cheap structural gate BEFORE fetching siblings (so garbage manifests
    // don't trigger downloads). Full validation runs later as usual.
    $pre = $this->validateManifest($manifest, true);
    if (!empty($pre)) {
      throw new RuntimeException('Manifest rejected: ' . implode(' ', $pre));
    }

    $files = [];
    $eps = is_array($manifest['entrypoints'] ?? null) ? $manifest['entrypoints'] : [];

    foreach (((array) ($eps['css'] ?? [])) as $f) {
      $files[] = [(string) $f, 'css'];
    }
    foreach (((array) ($eps['js'] ?? [])) as $f) {
      $files[] = [(string) $f, 'js'];
    }
    if (!empty($eps['snippets'])) {
      $sn = (string) $eps['snippets'];
      if (strtolower(pathinfo($sn, PATHINFO_EXTENSION)) !== 'json') {
        throw new RuntimeException(
          'Raw-manifest sources cannot include whole directories — point entrypoints.snippets at explicit .json pool files, or publish a zip.'
        );
      }
      $files[] = [$sn, 'json'];
    }
    $icon = (string) ($manifest['icon'] ?? '');
    if ($icon !== '' && !str_starts_with($icon, 'data:') && !preg_match('/^[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', $icon)) {
      $files[] = [$icon, 'asset'];
    }

    // De-duplicate, cap at 12 files, validate each path, then fetch.
    $seen = [self::MANIFEST_NAME => true];
    $unique = [];
    foreach ($files as [$f, $kind]) {
      $f = ltrim(str_replace('\\', '/', trim($f)), '/');
      if ($f === '' || isset($seen[$f])) continue;
      $seen[$f] = true;
      $unique[] = [$f, $kind];
    }
    if (count($unique) + 1 > self::MAX_RAW_FILES) {
      throw new RuntimeException('Raw-manifest packs are limited to ' . self::MAX_RAW_FILES . ' files (including the manifest).');
    }

    foreach ($unique as [$f, $kind]) {
      if (!$this->isSafeArchiveName($f)) {
        throw new RuntimeException("Unsafe entrypoint path rejected: $f");
      }
      $allowedExt = in_array(strtolower(pathinfo($f, PATHINFO_EXTENSION)), self::ALLOWED_EXT, true);
      if (!$allowedExt) {
        throw new RuntimeException("File type not allowed in raw packs: $f");
      }
      $this->emitOut("↓ $f");
      $data = $this->fetchUrl($base . rawurlencode_path($f), self::MAX_ENTRY_BYTES);
      $target = $stageDir . '/' . $f;
      $this->mkdirParents(dirname($target));
      @file_put_contents($target, $data, LOCK_EX);
    }

    return $manifest;
  }

  /* ═══════════════════════════════════════════════════════════
      MANIFEST VALIDATION (spec A — strict allowlist of knowns;
      unknown top-level keys are IGNORED, known ones validated)
      ═══════════════════════════════════════════════════════════ */

  /**
   * Validate the decoded manifest. Returns an array of human-readable error
   * strings; empty array == valid. Public so tooling/tests can call it.
   *
   * @param bool $structureOnly Skip checks that need the files on disk.
   */
  public function validateManifest(array $m, bool $structureOnly = false): array
  {
    $errors = [];

    if (($m['manifest'] ?? null) !== 1) {
      $errors[] = '"manifest" must be 1.';
    }

    $id = (string) ($m['id'] ?? '');
    if (!preg_match(self::ID_RE, $id)) {
      $errors[] = '"id" must match ^[a-z0-9][a-z0-9._-]{2,63}$ (it doubles as the install folder slug).';
    }

    $name = trim((string) ($m['name'] ?? ''));
    if ($name === '' || mb_strlen($name) > 80) {
      $errors[] = '"name" is required (max 80 chars).';
    }

    $version = (string) ($m['version'] ?? '');
    if (!preg_match(self::VERSION_RE, $version)) {
      $errors[] = '"version" must look like 1.0, 1.2.3 or 1.2.3-beta.1.';
    }

    $type = (string) ($m['type'] ?? '');
    if (!in_array($type, self::TYPES, true)) {
      $errors[] = '"type" must be one of: ' . implode(', ', self::TYPES) . '.';
    }

    $description = trim((string) ($m['description'] ?? ''));
    if ($description === '' || mb_strlen($description) > 500) {
      $errors[] = '"description" is required (max 500 chars).';
    }

    // icon: emoji OR a safe relative path to a servable image
    $icon = (string) ($m['icon'] ?? '');
    if ($icon !== '') {
      $isEmoji = preg_match('/^[\x{1F000}-\x{1FAFF}\x{2190}-\x{2BFF}\x{FE0F}]{1,4}$/u', $icon) === 1;
      if (!$isEmoji) {
        $ext = strtolower(pathinfo($icon, PATHINFO_EXTENSION));
        if (!in_array($ext, ['png', 'svg'], true) || !$this->isSafeArchiveName(ltrim(str_replace('\\', '/', $icon), '/'))) {
          $errors[] = '"icon" must be an emoji or a relative .png/.svg path.';
        }
      }
    }

    if (isset($m['author'])) {
      $author = $m['author'];
      if (!is_array($author) || trim((string) ($author['name'] ?? '')) === '') {
        $errors[] = '"author.name" is required when "author" is present.';
      } elseif (isset($author['url']) && !preg_match('#^https?://#i', (string) $author['url'])) {
        $errors[] = '"author.url" must be an http(s) URL.';
      }
    }

    if (isset($m['engines'])) {
      $engines = $m['engines'];
      $ideC = (string) ($engines['ide'] ?? '');
      if ($engines !== [] && !is_array($engines)) {
        $errors[] = '"engines" must be an object.';
      } elseif ($ideC !== '') {
        if (!preg_match(self::ENGINES_RE, $ideC)) {
          $errors[] = '"engines.ide" must look like ">=1.0".';
        } elseif (!$structureOnly && !$this->enginesSatisfied($ideC)) {
          $errors[] = "This package needs IDE $ideC — your IDE is older.";
        }
      }
    }

    /* ── entrypoints ── */
    $eps = $m['entrypoints'] ?? null;
    if (!is_array($eps)) {
      $errors[] = '"entrypoints" object is required.';
      $eps = [];
    }
    $cssList = $eps['css'] ?? [];
    $jsList  = $eps['js'] ?? [];
    $snips   = $eps['snippets'] ?? null;

    if (isset($eps['css'])) {
      if (!is_array($cssList)) {
        $errors[] = '"entrypoints.css" must be an array of .css paths.';
      } else {
        foreach ($cssList as $f) {
          if (!is_string($f) || strtolower(pathinfo($f, PATHINFO_EXTENSION)) !== 'css' || !$this->isSafeArchiveName($f)) {
            $errors[] = 'Bad css entrypoint: ' . $this->preview((string) $f);
          }
        }
      }
    }
    if (isset($eps['js'])) {
      if (!is_array($jsList)) {
        $errors[] = '"entrypoints.js" must be an array of .js paths.';
      } else {
        foreach ($jsList as $f) {
          if (!is_string($f) || strtolower(pathinfo($f, PATHINFO_EXTENSION)) !== 'js' || !$this->isSafeArchiveName($f)) {
            $errors[] = 'Bad js entrypoint: ' . $this->preview((string) $f);
          }
        }
      }
    }
    if ($snips !== null && (!is_string($snips) || $snips === '' || !$this->isSafeArchiveName(rtrim(ltrim(str_replace('\\', '/', $snips), '/'), '/')))) {
      $errors[] = '"entrypoints.snippets" must be a relative folder path (or a .json pool file for raw packs).';
    }

    /* ── capabilities ── */
    if (isset($m['capabilities'])) {
      $caps = $m['capabilities'];
      if (!is_array($caps)) {
        $errors[] = '"capabilities" must be an object.';
      } else {
        foreach (['cssInject', 'jsRun', 'storage'] as $flag) {
          if (array_key_exists($flag, $caps) && !is_bool($caps[$flag])) {
            $errors[] = "\"capabilities.$flag\" must be true or false.";
          }
        }
        if (isset($caps['network'])) {
          if (!is_array($caps['network'])) {
            $errors[] = '"capabilities.network" must be an array of host names.';
          } else {
            foreach ($caps['network'] as $h) {
              if (!is_string($h) || !preg_match(self::HOST_RE, $h)) {
                $errors[] = 'Bad network host: ' . $this->preview(is_string($h) ? $h : '');
              }
            }
          }
        }
      }
    }

    /* ── toolchain ── */
    if (isset($m['toolchain'])) {
      $tc = $m['toolchain'];
      if (!is_array($tc) || !isset($tc['pkg']) || !is_string($tc['pkg']) || !preg_match(self::TERMUX_PKG_RE, $tc['pkg'])) {
        $errors[] = '"toolchain.pkg" must be a Termux package name (letters, digits, dot, plus, hyphen).';
      }
    }

    /* ── type-dependent requirements ── */
    if (in_array($type, self::TYPES, true)) {
      if ($type === 'theme') {
        if (!is_array($cssList) || count($cssList) === 0) {
          $errors[] = 'Themes require at least one css entrypoint.';
        }
      } elseif ($type === 'snippets') {
        if (!is_string($snips) || $snips === '') {
          $errors[] = 'Snippet packs require "entrypoints.snippets".';
        }
      } elseif ($type === 'extension') {
        if (!is_array($jsList) || count($jsList) === 0) {
          $errors[] = 'Extensions require at least one js entrypoint.';
        }
      } elseif ($type === 'toolchain' && (!isset($m['toolchain']['pkg']))) {
        $errors[] = 'Toolchain packages require "toolchain.pkg".';
      }
    }

    return $errors;
  }

  /**
   * Content checks that need the extracted tree on disk: declared files exist,
   * snippet pools parse, icon resolves. Returns error strings ([] == ok).
   */
  private function validateContents(array $m, string $root): array
  {
    $errors = [];
    $eps = is_array($m['entrypoints'] ?? null) ? $m['entrypoints'] : [];
    $type = (string) ($m['type'] ?? '');

    foreach (((array) ($eps['css'] ?? [])) as $f) {
      if (!is_file($root . '/' . $f)) $errors[] = "Missing file in package: $f";
    }
    foreach (((array) ($eps['js'] ?? [])) as $f) {
      if (!is_file($root . '/' . $f)) $errors[] = "Missing file in package: $f";
    }

    if (!empty($eps['snippets'])) {
      $sn = (string) $eps['snippets'];
      if (strtolower(pathinfo($sn, PATHINFO_EXTENSION)) === 'json') {
        $errs = $this->validateSnippetPoolFile($root . '/' . $sn);
        $errors = array_merge($errors, $errs);
      } else {
        $dir = $root . '/' . rtrim($sn, '/');
        if (!is_dir($dir)) {
          $errors[] = "Missing snippets folder: $sn";
        } else {
          $pools = (array) glob($dir . '/*.json');
          if (empty($pools)) {
            $errors[] = "Snippets folder '$sn' contains no .json pool files.";
          }
          $n = 0;
          foreach ($pools as $pool) {
            if (++$n > 24) break; // sanity cap
            $errors = array_merge($errors, $this->validateSnippetPoolFile((string) $pool));
          }
        }
      }
    }

    $icon = (string) ($m['icon'] ?? '');
    if ($icon !== '' && in_array(strtolower(pathinfo($icon, PATHINFO_EXTENSION)), ['png', 'svg'], true)) {
      if (!is_file($root . '/' . ltrim(str_replace('\\', '/', $icon), '/'))) {
        $errors[] = "Icon file missing: $icon";
      }
    }

    if ($type === 'theme' && empty($errors)) {
      $hasCss = false;
      foreach (((array) ($eps['css'] ?? [])) as $f) {
        if (is_file($root . '/' . $f)) { $hasCss = true; break; }
      }
      if (!$hasCss) $errors[] = 'Theme ships no readable css file.';
    }

    return $errors;
  }

  /**
   * Validate one snippet pool file: {"<language-key>":[{label,body,detail?},…]}
   * Bodies may embed U+00B7 (·) indent markers — passed through untouched.
   *
   * @return string[] Errors ([] == valid).
   */
  private function validateSnippetPoolFile(string $path): array
  {
    $rel = basename($path);
    if (!is_file($path)) return ["Missing snippet pool: $rel"];
    clearstatcache(true, $path);
    if ((int) @filesize($path) > 256 * 1024) return ["Snippet pool '$rel' exceeds 256 KB."];
    $data = json_decode((string) @file_get_contents($path), true);
    if (!is_array($data) || empty($data)) return ["Snippet pool '$rel' is not a non-empty JSON object."];
    foreach ($data as $langKey => $items) {
      if (!is_string($langKey) || !preg_match('/^[a-z0-9_-]{1,32}$/', $langKey)) {
        return ["Snippet pool '$rel' has an invalid language key."];
      }
      if (!is_array($items) || empty($items)) {
        return ["Snippet pool '$rel' key '$langKey' must hold a non-empty array."];
      }
      foreach ($items as $item) {
        if (!is_array($item)) return ["Snippet pool '$rel'/$langKey holds a non-object item."];
        $label = (string) ($item['label'] ?? '');
        if ($label === '' || mb_strlen($label) > 100) {
          return ["Snippet pool '$rel'/$langKey items need a label (max 100 chars)."];
        }
        if (!isset($item['body']) || !is_string($item['body']) || $item['body'] === '') {
          return ["Snippet '{$label}' in '$rel' needs a body string."];
        }
        if (isset($item['detail']) && !is_string($item['detail'])) {
          return ["Snippet '{$label}' in '$rel' has a non-string detail."];
        }
      }
    }
    return [];
  }

  /** Compare ">=1.0"-style constraints against the running IDE version. */
  private function enginesSatisfied(string $constraint): bool
  {
    if (!preg_match(self::ENGINES_RE, $constraint, $m)) return false;
    $op = $m[1] ?: '>=';
    $need = array_map('intval', explode('.', substr($constraint, strlen($m[1]))));
    $haveStr = (string) ($this->config['meta']['version'] ?? '1.0');
    $have = array_map('intval', explode('.', preg_replace('/[^\d.].*$/', '', $haveStr) ?: '1.0'));
    $len = max(count($need), count($have));
    for ($i = 0; $i < $len; $i++) {
      $n = $need[$i] ?? 0;
      $h = $have[$i] ?? 0;
      if ($h !== $n) {
        return match ($op) {
          '>=' => $h > $n,
          '>'  => $h > $n,
          '<=' => $h < $n,
          '<'  => $h < $n,
          '='  => false,
          default => false,
        };
      }
    }
    // Equal versions satisfy everything except strict > and <
    return !in_array($op, ['>', '<'], true);
  }

  /* ═══════════════════════════════════════════════════════════
      ASSET SERVING (jail — index.php containment logic adapted)
      ═══════════════════════════════════════════════════════════ */

  /**
   * Serve one file from packages/<id>/ — GET index.php?api=workshop-asset&id=…&f=…
   * Containment mirrors index.php's asset server (null byte / '..' / '.'
   * segment rejection, realpath-prefix check) tightened further for untrusted
   * content: id must match the registry slug regex, symlinks refused, mime
   * locked to a small extension whitelist. Exits after responding.
   */
  public function serveAsset(string $id, string $file): void
  {
    if ($file === '' || strpos($file, "\0") !== false) {
      http_response_code(400);
      exit;
    }
    if (!preg_match(self::ID_RE, $id)) {
      http_response_code(400);
      exit;
    }

    $file = str_replace('\\', '/', $file);
    foreach (explode('/', $file) as $segment) {
      if ($segment === '..' || $segment === '.' || $segment === '') {
        http_response_code(403);
        exit;
      }
      if (stripos($segment, ':') !== false || preg_match('/[\x00-\x1f]/', $segment)) {
        http_response_code(403); // drive letters / stream wrappers / controls
        exit;
      }
    }

    $pkgRoot = realpath($this->packagesDir . '/' . $id);
    if ($pkgRoot === false) {
      http_response_code(404);
      exit;
    }
    $fullPath = realpath($pkgRoot . '/' . ltrim($file, '/'));
    if ($fullPath === false || !is_file($fullPath) || is_link($fullPath)) {
      http_response_code(404);
      exit;
    }

    $rootNorm = str_replace('\\', '/', $pkgRoot);
    $fullNorm = str_replace('\\', '/', $fullPath);
    if (strpos($fullNorm, $rootNorm . '/') !== 0) {
      http_response_code(403);
      exit;
    }

    $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
    $mimeTypes = [
      'css'   => 'text/css',
      'js'    => 'text/javascript',
      'json'  => 'application/json',
      'txt'   => 'text/plain',
      'png'   => 'image/png',
      'svg'   => 'image/svg+xml',
      'woff2' => 'font/woff2',
    ];
    if (!isset($mimeTypes[$ext])) {
      http_response_code(403); // only whitelisted types ever leave the jail
      exit;
    }

    $mtime = (int) @filemtime($fullPath);
    $etag = '"' . md5($id . '|' . $file . '|' . $mtime) . '"';

    header('Content-Type: ' . $mimeTypes[$ext] . '; charset=utf-8');
    header('Cache-Control: public, max-age=3600');
    header('ETag: ' . $etag);
    header('X-Content-Type-Options: nosniff');
    if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
      http_response_code(304);
      exit;
    }
    readfile($fullPath);
    exit;
  }

  /* ═══════════════════════════════════════════════════════════
      REGISTRY (packages.json — separate from extensions.json)
      ═══════════════════════════════════════════════════════════ */

  private function loadRegistry(): array
  {
    if (!is_file($this->registryFile)) {
      return ['packages' => [], 'order' => []];
    }
    $data = json_decode((string) @file_get_contents($this->registryFile), true);
    if (!is_array($data) || !isset($data['packages']) || !is_array($data['packages'])) {
      return ['packages' => [], 'order' => []];
    }
    $order = isset($data['order']) && is_array($data['order']) ? array_values($data['order']) : array_keys($data['packages']);
    return ['packages' => $data['packages'], 'order' => $order];
  }

  private function saveRegistry(array $registry): void
  {
    if (!is_dir($this->packagesDir)) @mkdir($this->packagesDir, 0777, true);
    @file_put_contents(
      $this->registryFile,
      json_encode($registry, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
      LOCK_EX
    );
  }

  /** Public-safe projection of a registry row. */
  private function summaryOf(array $row): array
  {
    $caps = is_array($row['capabilities'] ?? null) ? $row['capabilities'] : [];
    return [
      'id'           => (string) ($row['id'] ?? ''),
      'name'         => (string) ($row['name'] ?? ''),
      'version'      => (string) ($row['version'] ?? ''),
      'type'         => (string) ($row['type'] ?? ''),
      'description'  => (string) ($row['description'] ?? ''),
      'icon'         => (string) ($row['icon'] ?? ''),
      'author'       => [
        'name' => (string) ($row['author']['name'] ?? ''),
        'url'  => isset($row['author']['url']) ? (string) $row['author']['url'] : '',
      ],
      'enabled'      => (bool) ($row['enabled'] ?? false),
      'installedAt'  => (int) ($row['installedAt'] ?? 0),
      'capabilities' => [
        'cssInject' => (bool) ($caps['cssInject'] ?? false),
        'jsRun'     => (bool) ($caps['jsRun'] ?? false),
        'storage'   => (bool) ($caps['storage'] ?? false),
        'network'   => array_values((array) ($caps['network'] ?? [])),
      ],
      'entrypoints'  => [
        'css'      => array_values((array) ($row['entrypoints']['css'] ?? [])),
        'js'       => array_values((array) ($row['entrypoints']['js'] ?? [])),
        'snippets' => isset($row['entrypoints']['snippets']) ? (string) $row['entrypoints']['snippets'] : '',
      ],
      'toolchain'    => isset($row['toolchain']['pkg']) ? (string) $row['toolchain']['pkg'] : '',
      'sourceKind'   => (string) ($row['source']['kind'] ?? ''),
      'sourceRef'    => (string) ($row['source']['ref'] ?? ''),
    ];
  }

  /* ═══════════════════════════════════════════════════════════
      SMALL HELPERS
      ═══════════════════════════════════════════════════════════ */

  private function readManifestFile(string $path): array
  {
    if (!is_file($path)) {
      throw new RuntimeException('Package manifest missing after extraction.');
    }
    clearstatcache(true, $path);
    if ((int) @filesize($path) > 256 * 1024) {
      throw new RuntimeException('Manifest exceeds 256 KB.');
    }
    $manifest = json_decode((string) @file_get_contents($path), true);
    if (!is_array($manifest)) {
      throw new RuntimeException('Manifest is not valid JSON.');
    }
    return $manifest;
  }

  private function assertInsidePackagesDir(string $path): void
  {
    $base = realpath($this->packagesDir);
    if ($base === false) return;
    $p = realpath($path);
    if ($p === false) {
      $p = str_replace('\\', '/', $path);
      $base = str_replace('\\', '/', $base);
      if (strpos($p, $base . '/') !== 0 && $p !== $base) {
        throw new RuntimeException('Refusing to touch a path outside the package jail.');
      }
      return;
    }
    if (strpos(str_replace('\\', '/', $p), str_replace('\\', '/', $base) . '/') !== 0) {
      throw new RuntimeException('Refusing to touch a path outside the package jail.');
    }
  }

  private function mkdirParents(string $dir): void
  {
    if ($dir === '' || is_dir($dir)) return;
    $this->assertInsidePackagesDirFallback($dir);
    @mkdir($dir, 0777, true);
  }

  /** mkdirParents runs before the package has an id — jail-check against tmp/stage roots. */
  private function assertInsidePackagesDirFallback(string $path): void
  {
    $p = str_replace('\\', '/', $path);
    $roots = [rtrim(str_replace('\\', '/', $this->tmpDir), '/') . '/', rtrim(str_replace('\\', '/', $this->packagesDir), '/') . '/'];
    foreach ($roots as $r) {
      if (strpos($p, $r) === 0) return;
    }
    throw new RuntimeException('Staging path escaped the workshop jail.');
  }

  /** Recursive delete that refuses to follow symlinks or escape the jail. */
  private function rmtree(string $dir): void
  {
    if (!is_dir($dir) || is_link($dir)) return;
    $items = new \RecursiveIteratorIterator(
      new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
      \RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) {
      $path = (string) $item->getPathname();
      if ($item->isDir() && !$item->isLink()) {
        @rmdir($path);
      } else {
        @unlink($path); // links are unlinked, never followed
      }
    }
    @rmdir($dir);
  }

  private function preview(string $s): string
  {
    $s = trim(preg_replace('/\s+/', ' ', $s) ?? '');
    return mb_strlen($s) > 48 ? mb_substr($s, 0, 48) . '…' : ($s === '' ? '(empty)' : $s);
  }

  private function shortErr(\Throwable $e): string
  {
    return $this->preview($e->getMessage());
  }

  private function fmtBytes(int $b): string
  {
    if ($b <= 0) return '0 B';
    $u = ['B', 'KB', 'MB', 'GB'];
    $i = min((int) floor(log($b, 1024)), count($u) - 1);
    return round($b / pow(1024, $i), 1) . ' ' . $u[$i];
  }

  private function log(): IdeWorkshopLogger
  {
    static $logger = null;
    if ($logger === null) {
      require_once __DIR__ . '/WorkshopLogger.php';
      $logger = new IdeWorkshopLogger();
    }
    return $logger;
  }
}

/* ═══════════════════════════════════════════════════════════════════════════
   Helper: percent-encode each path segment of a relative entrypoint path for
   a URL (raw-manifest mode). Kept as a plain function (not a method) because
   it is used inside string interpolation contexts.
   ═══════════════════════════════════════════════════════════════════════════ */
if (!function_exists('rawurlencode_path')) {
  function rawurlencode_path(string $rel): string
  {
    return implode('/', array_map('rawurlencode', explode('/', str_replace('\\', '/', $rel))));
  }
}
