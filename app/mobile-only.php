<?php

declare(strict_types=1);

/**
 * ═══════════════════════════════════════════════════════════════════════════
 * QUIRKY IDE — MOBILE PLATFORM GUARD
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * The bouncer for the mobile IDE.
 *
 * Every request that hits ide-m/index.php passes through this check FIRST.
 * If the URL references a desktop-only file, it throws an error immediately
 * instead of letting a broken reference slip through.
 *
 * This is a safety net. After we delete all desktop files from ide-m/,
 * this guard ensures nothing accidentally tries to load them.
 *
 * ═══════════════════════════════════════════════════════════════════════════
 */

final class IdeMobileGuard
{
  /**
   * Files that belong to the DESKTOP platform only.
   * If any of these appear in a request URL on the mobile platform,
   * something is wrong and we stop immediately.
   */
  private const DESKTOP_ONLY = [
    // Desktop shell + partials
    'app/views/shell.php',
    'app/views/_titlebar.php',
    'app/views/_sidebar.php',
    'app/views/_editor.php',
    'app/views/_preview.php',
    'app/views/_terminal.php',
    'app/views/_statusbar.php',
    'app/views/_modals.php',

    // Desktop JS modules
    'app/assets/js/app.js',
    'app/assets/js/filetree.js',
    'app/assets/js/loader.js',
    'app/assets/js/git.js',
    'app/assets/js/http-client.js',

    // Desktop CSS
    'app/assets/css/git-panel.css',
    'app/assets/css/http-client.css',
  ];

  /**
   * Check the request URI for any desktop-only references.
   * Throws a RuntimeException if found, stopping the request.
   *
   * @param string $reqUri The full request URI (e.g. "/ide-m/index.php?asset=js/app.js")
   */
  public static function assertNoDesktopRefs(string $reqUri): void
  {
    foreach (self::DESKTOP_ONLY as $needle) {
      if (str_contains($reqUri, $needle)) {
        throw new RuntimeException(
          'Mobile guard blocked a desktop-only reference: ' . $needle
        );
      }
    }
  }
}
