<?php

declare(strict_types=1);

/**
 * ═══════════════════════════════════════════════════════════════════════════
 * QUIRKY IDE — MOBILE CONFIG OVERLAY
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * This file is loaded AFTER config.php and overrides specific settings
 * for the mobile platform. It does NOT replace config.php — it tweaks it.
 *
 * Loaded by: ide-m/index.php (after config.php, before security.php)
 *
 * ═══════════════════════════════════════════════════════════════════════════
 */

return [

  /* ── Security: tighter limits for phones ─────────────────────────── */
  'security' => [
    // FLOOR for the hardware-based limit. The real limit is computed
    // per-device (RAM/cores/storage) by app/mobile-limits.php and capped
    // at 264 MB. This value is only used until the phone reports once.
    'max_file_size' => 512 * 1024,
  ],

  /* ── Features: mobile defaults ───────────────────────────────────── */
  'features' => [
    'terminal_enabled' => true,
    'preview_enabled'      => true,
    'format_enabled'       => true,
    'minify_enabled'       => true,
    'git_enabled'          => true,
    'http_client_enabled'  => true,
    'ai_enabled'           => true,
    'command_palette_enabled' => true,
    'pkg_enabled'      => true,
    'workshop_enabled' => true,
  ],

  /* ── Editor: touch-friendly defaults ─────────────────────────────── */
  'editor' => [
    'theme'        => 'dark',
    // Larger font on phones (16px vs desktop 14px)
    'font_size'    => 16,
    'tab_size'     => 2,
    // Longer autosave debounce to save battery
    'autosave_ms'  => 1500,
    'default_file' => 'index.php',
  ],

  /* ── Preview: PHP + static + markdown ────────────────────────────── */
  // PHP files are rendered server-side via ?api=preview-render with
  // a 350ms debounce, so they are safe on mobile.
  'preview' => [
    'previewable' => ['html', 'htm', 'php', 'phtml', 'svg', 'md', 'markdown'],
  ],

  'pkg' => [
    'enabled'    => true,
    // Termux official repository (3000+ packages)
    'repo_base'  => 'https://packages.termux.dev/apt/termux-main',
    'timeout'    => 300,
  ],

  /* ── AI: force NVIDIA on mobile (Ollama is desktop-only) ─────────── */
  'ai' => [
    'enabled' => true,
    'provider' => 'nvidia',
  ],
  
  /* ── Workshop: extension system ────────────────────────────── */
  'workshop' => [
    'enabled' => true,
    'apache'  => [
      'auto_start' => false, // Don't auto-start Apache on mobile (battery)
    ],
  ],
];
