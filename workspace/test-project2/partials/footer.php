<?php
declare(strict_types=1);

if (!defined('APP_RUNNING')) {
  http_response_code(403);
  exit('Direct access not permitted.');
}

/** @var array $site */
/** @var array $nav_links */
/** @var array $visit */
?>

<footer class="footer">
  <div class="footer__inner">
    <div class="footer__top">
      <div class="footer__brand">
        <div class="footer__brand-mark"><?= e(mb_substr($site['name_jp'], 0, 1)) ?></div>
        <div class="footer__brand-text"><?= e(strtoupper($site['name'])) ?> · KYOTO ROASTERY</div>
        <p class="footer__brand-tag">A quiet ritual, brewed slowly — at the edge of the cedar grove, four kilometres north of Kyoto.</p>
      </div>
      <div class="footer__col">
        <p class="footer__col-title">Navigate</p>
        <ul class="footer__list">
          <?php foreach ($nav_links as $link): ?>
            <li><a href="<?= e($link['href']) ?>"><?= e($link['label']) ?></a></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <div class="footer__col">
        <p class="footer__col-title">Visit</p>
        <ul class="footer__list">
          <li><?= e($visit['address_lines'][0]) ?></li>
          <li><?= e($visit['address_lines'][1]) ?></li>
          <li><?= e($visit['address_lines'][2]) ?></li>
          <li><a href="tel:<?= preg_replace('/[^0-9+]/', '', $visit['phone']) ?>"><?= e($visit['phone']) ?></a></li>
        </ul>
      </div>
      <div class="footer__col">
        <p class="footer__col-title">Hours</p>
        <ul class="footer__list">
          <?php foreach ($visit['hours'] as $h): ?>
            <li><?= e($h['day']) ?> · <?= e($h['time']) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
    <div class="footer__bottom">
      <p class="footer__copyright">© <span data-year>2024</span> <?= e($site['name']) ?> · All rights reserved</p>
      <p class="footer__credits">Crafted slowly · Kyoto, Japan</p>
    </div>
  </div>
</footer>

<script src="assets/js/main.js" defer></script>
</body>
</html>