<?php

declare(strict_types=1);
/**
 * ═══════════════════════════════════════════════════════════════════════════
 *  QUIRKY IDE — PREVIEW ROUTES (v4 · Apache > micro-server > subprocess)
 * ═══════════════════════════════════════════════════════════════════════════
 *  preview-render: the IdePreview service decides the execution mode per
 *  request — Apache short-circuit when the Workshop extension is running for
 *  a quirky.setup project, else the standalone per-project micro-server,
 *  else subprocess isolation. User code never runs inside this process.
 */
/**
 * @var mixed $action
 * @var mixed $method
 * @var mixed $config
 * @var mixed $input
 * @var mixed $security
 */
switch ($action) {
    case 'preview-render': {
            $rawPath = (string) ($input['path'] ?? $_GET['path'] ?? '');
            $raw     = isset($_GET['raw']);

            // ★ DEVICE TIER GUARD:
            // Preview can be disabled by low-tier stabilization policy.
            if (empty($config['features']['preview_enabled'])) {
                $security->fail('Preview is disabled for this device tier.', 403);
            }

            /* ★ PHASE 4: Create Workshop instance for Apache routing.
           This lets the Preview service check if Apache is running
           and if the file belongs to a detected project. */
            require_once __DIR__ . '/../services/WorkshopLogger.php';
            require_once __DIR__ . '/../services/Workshop.php';
            $workshop = new IdeWorkshop($config, $security);

            $preview = new IdePreview($security, $workshop);
            $preview->handle($rawPath, $input, $method, $raw);
            break;
        }
}
