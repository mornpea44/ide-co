<?php

declare(strict_types=1);
/**
 * QUIRKY IDE — SPEECH ROUTES (v14 · dynamic TTS/STT engine)
 * Required by app/api.php; inherits $config, $security, $input, $method.
 */
/**
 * @var mixed $config
 * @var mixed $action
 * @var mixed $method
 */
$speech = new IdeSpeech($config, $security);

switch ($action) {
  case 'speech-catalog':
    $security->respond($speech->getCatalog());
    break;

  case 'speech-install':
    if ($method !== 'POST') $security->fail('Use POST to install.', 405);
    $speech->installStream((string) ($input['id'] ?? ''));
    exit;

  case 'speech-uninstall':
    if ($method !== 'POST') $security->fail('Use POST to uninstall.', 405);
    $security->respond($speech->uninstall((string) ($input['id'] ?? '')));
    break;

  case 'speech-community-refresh':
    if ($method !== 'POST') $security->fail('Use POST.', 405);
    try {
      $security->respond($speech->refreshCommunity((string) ($input['url'] ?? '')));
    } catch (\Throwable $e) {
      $security->fail($e->getMessage(), 400);
    }
    break;

  case 'speech-import-local':
    if ($method !== 'POST') $security->fail('Use POST to import.', 405);
    $path = (string) ($input['path'] ?? '');
    /* only accept staged files inside the app cache (set by the
           Android bridge) — never arbitrary user paths */
    $real = realpath($path);
    $cacheRoot = realpath(sys_get_temp_dir());
    if ($real === false || strpos($real, 'import-staging') === false) {
      $security->fail('Import path must be a staged package file.', 400);
    }
    $security->respond($speech->importLocal($real));
    break;

  default:
    $security->fail('Unknown speech action: ' . $action, 404);
}
