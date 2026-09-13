<?php

declare(strict_types=1);

/**
 * ═══════════════════════════════════════════════════════════════════════════
 *  QUIRKY IDE — HARDWARE-BASED MOBILE LIMITS + DEVICE TIER POLICY ENGINE
 * ═══════════════════════════════════════════════════════════════════════════
 *
 *  Phase 1 responsibilities:
 *
 *   1. Keep the existing hardware-based file limit engine.
 *   2. Add a real device tier:
 *        - low
 *        - medium
 *        - high
 *   3. Provide a central policy engine for optimization/stabilization.
 *   4. Provide hard guard helpers for Terminal / Workshop / pkg.
 *
 *  Non-negotiable policy goals:
 *
 *   LOW:
 *     - terminal OFF
 *     - workshop OFF
 *     - pkg OFF
 *     - word wrap OFF
 *     - preview OFF
 *     - AI OFF
 *     - source control OFF
 *     - gestures OFF
 *     - motions OFF
 *     - watch optimized
 *
 *   MEDIUM:
 *     - terminal OFF
 *     - workshop OFF
 *     - pkg OFF
 *     - watch optimized
 *     - some heavy features OFF by default
 *
 *   HIGH:
 *     - no limits
 *
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (!function_exists('quirkyMobileComputeFileLimit')) {

  /**
   * Grade the device and return the file limit in bytes.
   *
   * @param float $ramGb      navigator.deviceMemory (GB, 0 = unknown)
   * @param int   $cores      navigator.hardwareConcurrency (0 = unknown)
   * @param float $quotaBytes navigator.storage.estimate().quota (0 = unknown)
   * @return int Limit in bytes, clamped to [512 KB, 264 MB]
   */
  function quirkyMobileComputeFileLimit(float $ramGb, int $cores, float $quotaBytes): int
  {
    $FLOOR = 512 * 1024;          // never below this
    $CAP   = 264 * 1024 * 1024;   // hard cap

    // Nothing reported (very old WebView) → safe default.
    if ($ramGb <= 0 && $cores <= 0 && $quotaBytes <= 0) {
      return $FLOOR;
    }

    // Fill unknowns with conservative mid values.
    $ramGb = $ramGb > 0 ? $ramGb : 4;
    $cores = $cores > 0 ? $cores : 4;

    // 1) RAM tier
    if ($ramGb <= 2) {
      $limit = 2   * 1024 * 1024;
    } elseif ($ramGb <= 4) {
      $limit = 8   * 1024 * 1024;
    } elseif ($ramGb <= 6) {
      $limit = 16  * 1024 * 1024;
    } elseif ($ramGb <= 8) {
      $limit = 32  * 1024 * 1024;
    } else {
      $limit = 150 * 1024 * 1024;
    }

    // 2) Chipset strength (core count) multiplier
    if ($cores >= 8) {
      $limit = (int) round($limit * 2);
    } elseif ($cores >= 6) {
      $limit = (int) round($limit * 1.5);
    }

    // 3) Storage gate
    if ($quotaBytes > 0) {
      if ($quotaBytes < 200 * 1024 * 1024) {
        $limit = min($limit, 4  * 1024 * 1024);
      } elseif ($quotaBytes < 1024 * 1024 * 1024) {
        $limit = min($limit, 32 * 1024 * 1024);
      }
    }

    return max($FLOOR, min($limit, $CAP));
  }

  /**
   * Human-readable bytes for toasts / responses.
   */
  function quirkyMobileHumanBytes(int $bytes): string
  {
    if ($bytes >= 1048576) {
      return round($bytes / 1048576, 1) . ' MB';
    }

    return round($bytes / 1024, 1) . ' KB';
  }

  /**
   * Location of the persisted device state.
   */
  function quirkyMobileDeviceStateFile(): string
  {
    return __DIR__ . '/.mobile-device.json';
  }

  /**
   * Location of future tier unlock choices.
   *
   * This file is not created automatically in Phase 1.
   * It will later store features the user explicitly accepted
   * after a warning popup.
   */
  function quirkyMobileTierUnlocksFile(): string
  {
    return __DIR__ . '/.mobile-tier-unlocks.json';
  }

  /**
   * Read persisted device state.
   *
   * @return array
   */
  function quirkyMobileReadDeviceState(): array
  {
    $file = quirkyMobileDeviceStateFile();

    if (!is_file($file)) {
      return [];
    }

    $data = json_decode((string) @file_get_contents($file), true);

    return is_array($data) ? $data : [];
  }

  /**
   * Location of the manual tier override file (TESTING ONLY).
   * Create this file to force a tier; delete it to return to normal.
   */
  function quirkyMobileTierOverrideFile(): string
  {
    return __DIR__ . '/.mobile-tier-override.json';
  }

  /**
   * Read the manual tier override.
   * Expected format:  {"tier": "low"}            (forces the tier)
   * Optional:         {"tier": "low", "limit": 2097152}  (also fakes the file limit)
   * Returns ['tier' => 'low'|'medium'|'high'] (plus optional 'limit') or null.
   */
  function quirkyMobileReadTierOverride(): ?array
  {
    $file = quirkyMobileTierOverrideFile();
    if (!is_file($file)) {
      return null;
    }
    $data = json_decode((string) @file_get_contents($file), true);
    if (!is_array($data)) {
      return null;
    }
    $result = [];
    if (isset($data['tier'])) {
      $tier = strtolower(trim((string) $data['tier']));
      if ($tier === 'mid')      $tier = 'medium';
      if ($tier === 'lite')     $tier = 'low';
      if ($tier === 'flagship') $tier = 'high';
      if (in_array($tier, ['low', 'medium', 'high'], true)) {
        $result['tier'] = $tier;
      }
    }
    if (isset($data['limit']) && (int) $data['limit'] > 0) {
      $result['limit'] = (int) $data['limit'];
    }
    return $result === [] ? null : $result;
  }

  /**
   * Persist device state.
   */
  function quirkyMobilePersistDeviceState(array $state): void
  {
    @file_put_contents(
      quirkyMobileDeviceStateFile(),
      json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
      LOCK_EX
    );
  }

  /**
   * Read future tier unlock choices.
   *
   * Expected format:
   * {
   *   "low": {
   *     "preview_enabled": true,
   *     "git_enabled": true
   *   },
   *   "medium": {
   *     "ai_enabled": true
   *   }
   * }
   */
  function quirkyMobileReadTierUnlocks(): array
  {
    $file = quirkyMobileTierUnlocksFile();

    if (!is_file($file)) {
      return [];
    }

    $data = json_decode((string) @file_get_contents($file), true);

    return is_array($data) ? $data : [];
  }

  /**
   * Compute the device tier from hardware characteristics.
   *
   * This intentionally piggybacks on the file-limit score so the
   * tier stays consistent with the existing hardware grading system.
   *
   * @return string low|medium|high
   */
  function quirkyMobileComputeDeviceTier(float $ramGb, int $cores, float $quotaBytes): string
  {
    $limit = quirkyMobileComputeFileLimit($ramGb, $cores, $quotaBytes);

    if ($limit <= 8 * 1024 * 1024) {
      return 'low';
    }

    if ($limit <= 32 * 1024 * 1024) {
      return 'medium';
    }

    return 'high';
  }

  /**
   * Normalize incoming tier strings.
   */
  function quirkyMobileNormalizeDeviceTier(?string $tier, string $fallback = 'medium'): string
  {
    $tier = strtolower(trim((string) $tier));

    if ($tier === 'low' || $tier === 'medium' || $tier === 'high') {
      return $tier;
    }

    if ($tier === 'mid') {
      return 'medium';
    }

    if ($tier === 'lite') {
      return 'low';
    }

    if ($tier === 'flagship') {
      return 'high';
    }

    return $fallback;
  }

  /**
   * Small helper for setting nested config paths dynamically.
   *
   * Example:
   *   quirkyMobileSetConfigPath($config, 'mobile.gestures_enabled', false);
   *
   * @param mixed $value
   */
  function quirkyMobileSetConfigPath(array &$config, string $path, $value): void
  {
    $keys = explode('.', $path);
    $current = &$config;

    foreach ($keys as $i => $key) {
      if ($i === count($keys) - 1) {
        $current[$key] = $value;
        return;
      }

      if (!isset($current[$key]) || !is_array($current[$key])) {
        $current[$key] = [];
      }

      $current = &$current[$key];
    }
  }

  /**
   * The central tier policy definition.
   *
   * hard_locked_features:
   *   These are non-negotiable. They are forced OFF and cannot be saved ON.
   *
   * default_off_features:
   *   These are forced OFF unless a future unlock/warning flow explicitly
   *   allows them.
   *
   * forced:
   *   Direct config overrides applied to the live config array.
   */
  function quirkyMobileGetDeviceTierPolicy(string $tier): array
  {
    $tier = quirkyMobileNormalizeDeviceTier($tier, 'high');

    $policy = [
      'tier' => $tier,
      'hard_locked_features' => [],
      'default_off_features' => [],
      'forced' => [],
    ];

    if ($tier === 'low') {
      $policy['hard_locked_features'] = [
        'terminal_enabled',
        'workshop_enabled',
        'pkg_enabled',
      ];

      $policy['default_off_features'] = [
        'preview_enabled',
        'ai_enabled',
        'git_enabled',
        'http_client_enabled',
        'command_palette_enabled',
        'autocomplete_enabled',
        'lint_enabled',
        'format_enabled',
        'minify_enabled',
      ];

      $policy['forced'] = [
        // Editor stabilization
        'editor.word_wrap' => false,

        // Watch stabilization — non-negotiable
        'watch.interval_ms' => 60000,
        'watch.optimized' => true,

        // Gesture lockdown
        'mobile.gestures_enabled' => false,
        'mobile.swipe_tabs_enabled' => false,
        'mobile.swipe_select_enabled' => false,
        'mobile.pinch_zoom_enabled' => false,
        'mobile.pull_to_refresh_enabled' => false,
        'mobile.drag_drop_enabled' => false,

        // Motion lockdown
        'mobile.motions_enabled' => false,

        // Extra low-end safety
        'ai.enabled' => false,
        'workshop.enabled' => false,
        'pkg.enabled' => false,
      ];
    } elseif ($tier === 'medium') {
      $policy['hard_locked_features'] = [
        'terminal_enabled',
        'workshop_enabled',
        'pkg_enabled',
      ];

      $policy['default_off_features'] = [
        'ai_enabled',
        'http_client_enabled',
        'command_palette_enabled',
        'autocomplete_enabled',
        'lint_enabled',
      ];

      $policy['forced'] = [
        // Watch stabilization — non-negotiable
        'watch.interval_ms' => 30000,
        'watch.optimized' => true,

        // Keep heavy add-ons off by default
        'ai.enabled' => false,
        'workshop.enabled' => false,
        'pkg.enabled' => false,
      ];
    }

    return $policy;
  }

  /**
   * Apply the tier policy to the live config.
   *
   * This should be called AFTER:
   *   - config.php
   *   - config.mobile.php
   *   - features.json
   *   - quirkyMobileApplyDeviceLimit()
   *
   * @param array       $config
   * @param array       $savedFeatureOverrides Reserved for future use
   * @param string|null $requestedTier          Optional override for tooling/tests
   * @return string The final tier
   */
  function quirkyMobileApplyDeviceTierPolicy(array &$config, array $savedFeatureOverrides = [], ?string $requestedTier = null): string
  {
    $state = quirkyMobileReadDeviceState();

    /* ★ TEST OVERRIDE: app/.mobile-tier-override.json wins over everything,
       so you can force "low"/"medium"/"high" without touching real data. */
    $override = quirkyMobileReadTierOverride();

    if ($override !== null && isset($override['tier'])) {
      $tier = $override['tier'];
    } elseif ($requestedTier !== null && $requestedTier !== '') {
      $tier = quirkyMobileNormalizeDeviceTier($requestedTier, 'high');
    } else {
      $tier = quirkyMobileNormalizeDeviceTier(
        isset($state['tier']) ? (string) $state['tier'] : null,
        'high'
      );
    }

    $policy = quirkyMobileGetDeviceTierPolicy($tier);
    $unlocks = quirkyMobileReadTierUnlocks();
    $tierUnlocks = isset($unlocks[$tier]) && is_array($unlocks[$tier]) ? $unlocks[$tier] : [];

    if (!isset($config['features']) || !is_array($config['features'])) {
      $config['features'] = [];
    }

    // 1) Hard locks are absolute.
    foreach ($policy['hard_locked_features'] as $featureKey) {
      $config['features'][$featureKey] = false;
    }

    // 2) Default-off features are forced off unless explicitly unlocked later.
    foreach ($policy['default_off_features'] as $featureKey) {
      if (!empty($tierUnlocks[$featureKey])) {
        continue;
      }

      $config['features'][$featureKey] = false;
    }

    // 3) Apply direct forced config values.
    foreach ($policy['forced'] as $path => $value) {
      quirkyMobileSetConfigPath($config, $path, $value);
    }

    // 4) Sync dependent top-level config sections.
    if (isset($config['features']['ai_enabled'])) {
      $config['ai']['enabled'] = !empty($config['features']['ai_enabled']);
    }

    if (isset($config['features']['workshop_enabled'])) {
      $config['workshop']['enabled'] = !empty($config['features']['workshop_enabled']);
    }

    if (isset($config['features']['pkg_enabled'])) {
      $config['pkg']['enabled'] = !empty($config['features']['pkg_enabled']);
    }

    // 5) Keep watch interval visible in features for front-end compatibility.
    if (isset($config['watch']['interval_ms'])) {
      $config['features']['watch_interval_ms'] = (int) $config['watch']['interval_ms'];
    }

    // 6) Store device metadata in config for routes/services/views.
    $config['device']['tier'] = $tier;
    $config['device']['policy'] = $policy;
    $config['device']['unlocks'] = $tierUnlocks;

    if (!empty($state)) {
      $config['device']['file_limit'] = isset($state['limit']) ? (int) $state['limit'] : null;
      $config['device']['hardware'] = [
        'ramGb' => isset($state['ram']) ? (float) $state['ram'] : 0.0,
        'ramMb' => isset($state['ramMb']) ? (int) $state['ramMb'] : 0,
        'cores' => isset($state['cores']) ? (int) $state['cores'] : 0,
        'storageMb' => isset($state['storageMb']) ? (int) $state['storageMb'] : 0,
        'source' => $state['source'] ?? 'unknown',
      ];
    }

    /* ★ TEST OVERRIDE (continued): optionally fake the file-size limit too,
       so a desktop can fully simulate a low-end phone. */
    if ($override !== null && isset($override['limit'])) {
      $forcedLimit = max(512 * 1024, min((int) $override['limit'], 264 * 1024 * 1024));
      $config['security']['max_file_size'] = $forcedLimit;
      $config['device']['file_limit']      = $forcedLimit;
    }

    return $tier;
  }

  /**
   * Generic feature guard.
   */
  function quirkyMobileCanUseFeature(array $config, string $feature): bool
  {
    if (empty($config['features'][$feature])) {
      return false;
    }

    $tier = $config['device']['tier'] ?? null;

    if ($tier === null) {
      $state = quirkyMobileReadDeviceState();
      $tier = quirkyMobileNormalizeDeviceTier(
        isset($state['tier']) ? (string) $state['tier'] : null,
        'high'
      );
    }

    $policy = quirkyMobileGetDeviceTierPolicy($tier);

    if (in_array($feature, $policy['hard_locked_features'], true)) {
      return false;
    }

    if (in_array($feature, $policy['default_off_features'], true)) {
      $unlocks = quirkyMobileReadTierUnlocks();

      if (empty($unlocks[$tier][$feature])) {
        return false;
      }
    }

    return true;
  }

  /**
   * Terminal guard.
   */
  function quirkyMobileCanUseTerminal(array $config): bool
  {
    return quirkyMobileCanUseFeature($config, 'terminal_enabled');
  }

  /**
   * Workshop guard.
   */
  function quirkyMobileCanUseWorkshop(array $config): bool
  {
    return quirkyMobileCanUseFeature($config, 'workshop_enabled');
  }

  /**
   * Package manager guard.
   */
  function quirkyMobileCanUsePackages(array $config): bool
  {
    return quirkyMobileCanUseFeature($config, 'pkg_enabled');
  }

  /**
   * Apply the stored device limit to the live config.
   * Called by index.php and api.php on EVERY request.
   *
   * Also raises PHP's memory limit so files of this size can
   * actually be read / saved / JSON-encoded without a fatal.
   */
  function quirkyMobileApplyDeviceLimit(array &$config): void
  {
    $data = quirkyMobileReadDeviceState();

    if (empty($data) || !isset($data['limit'])) {
      return;
    }

    $limit = max(512 * 1024, min((int) $data['limit'], 264 * 1024 * 1024));

    $config['security']['max_file_size'] = $limit;

    // PHP needs ~4× the file size in memory for JSON encoding.
    $needMb = (int) ceil(($limit * 4 + 128 * 1024 * 1024) / (1024 * 1024));
    @ini_set('memory_limit', $needMb . 'M');

    // Store useful metadata for later policy/UI consumption.
    $config['device']['file_limit'] = $limit;
    $config['device']['hardware'] = [
      'ramGb' => isset($data['ram']) ? (float) $data['ram'] : 0.0,
      'ramMb' => isset($data['ramMb']) ? (int) $data['ramMb'] : 0,
      'cores' => isset($data['cores']) ? (int) $data['cores'] : 0,
      'storageMb' => isset($data['storageMb']) ? (int) $data['storageMb'] : 0,
      'source' => $data['source'] ?? 'unknown',
    ];

    if (!empty($data['tier'])) {
      $config['device']['tier'] = quirkyMobileNormalizeDeviceTier(
        (string) $data['tier'],
        'high'
      );
    }
  }
}
