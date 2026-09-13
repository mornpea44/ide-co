<?php
declare(strict_types=1);

if (!defined('APP_RUNNING')) {
  http_response_code(403);
  exit('Direct access not permitted.');
}

/** @var string $page_title */
/** @var string $page_description */
/** @var array $site */
/** @var array $nav_links */
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <meta name="theme-color" content="#0d1310">
  <title><?= e($page_title) ?></title>
  <meta name="description" content="<?= e($page_description) ?>">

  <!-- Open Graph -->
  <meta property="og:title" content="<?= e($page_title) ?>">
  <meta property="og:description" content="<?= e($page_description) ?>">
  <meta property="og:type" content="website">
  <meta property="og:image" content="https://picsum.photos/seed/komorebi-hero-forest-mist/1200/630">

  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600;700&family=Inter:wght@300;400;500&display=swap" rel="stylesheet">

  <!-- Styles -->
  <link rel="stylesheet" href="assets/css/styles.css">

  <!-- Favicon -->
  <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>木</text></svg>">
</head>
<body>
<header class="nav" data-nav>
  <div class="nav__inner">
    <a href="#top" class="nav__brand" aria-label="<?= e($site['name']) ?> home">
      <span class="nav__brand-mark"><?= e(mb_substr($site['name_jp'], 0, 1)) ?></span>
      <span class="nav__brand-text"><?= e(strtoupper($site['name'])) ?></span>
    </a>
    <nav class="nav__menu" data-nav-menu aria-label="Primary">
      <?php foreach ($nav_links as $link): ?>
        <a href="<?= e($link['href']) ?>" class="nav__link"><?= e($link['label']) ?></a>
      <?php endforeach; ?>
      <a href="#visit" class="btn btn--small btn--primary nav__cta">Reserve</a>
    </nav>
    <button class="nav__toggle" data-nav-toggle aria-label="Toggle menu" aria-expanded="false">
      <span></span>
      <span></span>
    </button>
  </div>
</header>