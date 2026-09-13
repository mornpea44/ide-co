<?php
declare(strict_types=1);

/**
 * Komorebi — Kyoto Coffee Roastery
 * Single-page landing site in vanilla PHP, JS, CSS.
 */

define('APP_RUNNING', true);

// ---------- Content ----------
 $site = [
  'name'    => 'Komorebi',
  'name_jp' => '木洩れ日',
  'tagline' => 'A quiet ritual, brewed slowly.',
];

 $nav_links = [
  ['label' => 'Philosophy', 'href' => '#philosophy'],
  ['label' => 'Menu',       'href' => '#menu'],
  ['label' => 'Method',     'href' => '#method'],
  ['label' => 'The Room',   'href' => '#room'],
  ['label' => 'Visit',      'href' => '#visit'],
];

 $hero = [
  'eyebrow'   => 'KOMOREBI · KYOTO ROASTERY · EST. 1947',
  'title'     => 'A quiet ritual,<br>brewed slowly.',
  'subtitle'  => 'Single-origin coffee from the highlands of Kyoto Prefecture, rested forty-eight hours between roast and pour. A small room. Twelve seats. Open from first light.',
  'cta_primary'   => 'Reserve a seat',
  'cta_secondary' => 'View the menu',
  'image' => 'https://picsum.photos/seed/komorebi-hero-forest-mist/2400/1600',
  'alt'   => 'Mist filtering through a cedar grove at dawn',
];

 $philosophy = [
  'eyebrow'   => 'OUR PHILOSOPHY',
  'title_lines' => ['The forest remembers', 'what the city forgets.'],
  'paragraphs' => [
    'We roast in a converted kominka at the edge of the cedar grove, four kilometres north of Kyoto. The room still smells of the hinoki beams that were cut in 1947, and of the small fires we light beneath the beans each morning.',
    'Coffee, to us, is not a beverage but a measure of attention. Forty-two hands touch each cherry between the branch and the cup. We count them. We name them. We rest the beans for two days after roasting so that the carbon dioxide leaves and what remains is the actual taste of the hillside.',
    'There is no music. There is no menu beyond the four pours. There is the window, the trees, and the long quiet hour that a proper cup demands.',
  ],
  'image' => 'https://picsum.photos/seed/komorebi-cedar-bar-interior/1200/1600',
  'image_caption' => 'THE CYPRESS BAR · PLANED FROM A SINGLE FALLEN TREE',
  'signature_name' => 'Mari Onoe',
  'signature_role' => 'Founder & Roast Master',
];

 $menu = [
  'eyebrow'  => 'SIGNATURE POURS',
  'title'    => 'Four cups.<br>One hillside.',
  'subtitle' => 'Each pour is rooted in the same seven-hectare grove above the village of Kurama. The difference is in time, temperature, and patience.',
  'items' => [
    [
      'number'      => '01',
      'name_jp'     => '幽玄',
      'name_en'     => 'Yūgen',
      'method'      => 'Single-origin · Light roast · 88°C',
      'notes'       => 'Cedar, yuzu peel, river stone',
      'description' => 'The first pour of the morning. Brewed at eighty-eight degrees to preserve the floral top notes that disappear at higher heat. A pale cup, the colour of new bamboo.',
      'price'       => '¥1,400',
    ],
    [
      'number'      => '02',
      'name_jp'     => '木洩れ日',
      'name_en'     => 'Komorebi',
      'method'      => 'Cold brew · 18 hours · Cedar barrel',
      'notes'       => 'Dark chocolate, kuro sato, tobacco',
      'description' => 'Steeped for eighteen hours in cedar barrels the previous owner used for pickling ume. The wood gives it a low, sweet undertone that no steel tank can reproduce.',
      'price'       => '¥1,600',
    ],
    [
      'number'      => '03',
      'name_jp'     => '森林浴',
      'name_en'     => 'Shinrinyoku',
      'method'      => 'Pour-over · Hand-dripped · 93°C',
      'notes'       => 'Forest floor, shiitake, black walnut',
      'description' => 'Our slowest cup. Four minutes of hand-pouring in a thin, even spiral. It tastes of the moss that grows on the north side of the cedars behind the roastery.',
      'price'       => '¥1,800',
    ],
    [
      'number'      => '04',
      'name_jp'     => 'いろは',
      'name_en'     => 'Iroha',
      'method'      => 'Espresso · Double-pulled · 25s',
      'notes'       => 'Stone fruit, molasses, graham',
      'description' => 'The only espresso we serve. Pulled in twenty-five seconds on a 1961 Faema E61, restored by a mechanic in Osaka who only works on machines older than he is.',
      'price'       => '¥1,200',
    ],
  ],
];

 $process = [
  'eyebrow'   => 'THE METHOD',
  'title'     => 'From cherry to cup,<br>in seven days.',
  'subtitle'  => 'We do not hurry. Each step has a duration that cannot be shortened without changing the taste. Here is the calendar of a single batch.',
  'meta' => [
    ['label' => 'Batch size', 'value' => '7 kg'],
    ['label' => 'Yield',      'value' => '140 cups'],
    ['label' => 'Duration',   'value' => '7 days'],
  ],
  'steps' => [
    [
      'day'         => 'DAY ONE',
      'name'        => 'Harvest',
      'description' => 'Hand-picked at 1,200 metres elevation, October only. Each cherry is sorted on a cedar table by the light of the morning sun, which reveals the imperfections the electric sorters miss.',
    ],
    [
      'day'         => 'DAY TWO',
      'name'        => 'Rest',
      'description' => 'Forty-eight hours in cedar barrels. The cherries sweat gently, the wood absorbing the moisture that would otherwise turn to ferment. This is the step the industry skips. We never have.',
    ],
    [
      'day'         => 'DAY FOUR',
      'name'        => 'Roast',
      'description' => 'A slow flame, thirty-six minutes per batch. Modern roasters take twelve. We take three times as long because the cellular structure of the bean needs time to open, not to crack.',
    ],
    [
      'day'         => 'DAY FIVE',
      'name'        => 'Steep',
      'description' => 'Hand-poured at ninety-three degrees. The water comes from the spring that runs beneath the floorboards of the roastery — the same spring that feeds the cedars.',
    ],
    [
      'day'         => 'DAY SEVEN',
      'name'        => 'Serve',
      'description' => 'In a hand-thrown chawan, on a cypress bar, to a guest who has walked four kilometres up the lane from the nearest bus stop. We do not deliver. We do not ship. You must come to the forest.',
    ],
  ],
];

 $gallery = [
  'eyebrow'   => 'THE ROOM',
  'title'     => 'Twelve seats.<br>One window.<br>The forest beyond.',
  'subtitle'  => 'The roastery was built in 1947 as a charcoal-burner\'s hut. We added the cypress bar in 2021, the iron stove in 2022. Nothing else has changed.',
  'pairs' => [
    [
      'left'  => ['url' => 'https://picsum.photos/seed/komorebi-cedar-bar-interior/900/1200', 'alt' => 'Cypress bar with twelve seats', 'caption' => 'THE BAR · 12 SEATS'],
      'right' => ['url' => 'https://picsum.photos/seed/komorebi-pour-over-hands/900/1200',   'alt' => 'Hands pouring water over coffee grounds', 'caption' => 'THE POUR · 4 MINUTES'],
    ],
    [
      'left'  => ['url' => 'https://picsum.photos/seed/komorebi-forest-window-view/900/1200', 'alt' => 'View of the cedar grove from the window', 'caption' => 'THE VIEW · NORTH-FACING'],
      'right' => ['url' => 'https://picsum.photos/seed/komorebi-hand-thrown-chawan/900/1200', 'alt' => 'A hand-thrown tea bowl filled with coffee', 'caption' => 'THE CHAWAN · MASHIKO-YAKI'],
    ],
    [
      'left'  => ['url' => 'https://picsum.photos/seed/komorebi-roasting-flame/900/1200', 'alt' => 'A small flame beneath a cast iron roaster', 'caption' => 'THE FLAME · SLOW FIRE'],
      'right' => ['url' => 'https://picsum.photos/seed/komorebi-coffee-cherry/900/1200',   'alt' => 'Red coffee cherries on a cedar sorting table', 'caption' => 'THE CHERRY · OCTOBER ONLY'],
    ],
  ],
];

 $visit = [
  'eyebrow' => 'FIND US',
  'title'   => 'One narrow lane,<br>behind the cedar grove.',
  'lead'    => 'There is no sign on the road. Look for the second cedar after the small bridge, then take the lane that turns to gravel. The gate is the one with the moss.',
  'address_lines' => [
    '27 Kurama-hondō',
    'Sakyō-ku, Kyoto 601-1111',
    'Japan',
  ],
  'phone' => '+81 75-000-0000',
  'email' => 'mori@komorebi.kyoto',
  'hours' => [
    ['day' => 'Tuesday — Friday', 'time' => '06:30 — 14:00'],
    ['day' => 'Saturday — Sunday', 'time' => '07:00 — 16:00'],
    ['day' => 'Monday', 'time' => 'Closed'],
  ],
  'note' => 'Reservations open fourteen days in advance, by telephone only, between 09:00 and 11:00. Walk-ins welcome for the first two seats at the bar.',
  'cta_primary'   => 'Reserve a seat',
  'cta_secondary' => 'View on map',
];

// ---------- Helper ----------
function e($value): string {
  return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

 $page_title       = $site['name'] . ' — ' . $site['tagline'];
 $page_description = 'A Kyoto coffee roastery at the edge of the cedar grove. Single-origin coffee, hand-poured, in twelve seats.';

include __DIR__ . '/partials/header.php';
?>

<main>
  <!-- HERO -->
  <section class="hero" id="top" data-hero>
    <div class="hero__image" data-hero-img>
      <img src="<?= e($hero['image']) ?>" alt="<?= e($hero['alt']) ?>" fetchpriority="high">
    </div>
    <div class="hero__overlay"></div>
    <div class="hero__content">
      <p class="eyebrow hero__eyebrow"><?= e($hero['eyebrow']) ?></p>
      <h1 class="hero__title"><?= $hero['title'] ?></h1>
      <p class="hero__subtitle"><?= e($hero['subtitle']) ?></p>
      <div class="hero__cta">
        <a href="#visit" class="btn btn--primary"><?= e($hero['cta_primary']) ?></a>
        <a href="#menu" class="btn btn--ghost"><?= e($hero['cta_secondary']) ?></a>
      </div>
    </div>
    <div class="hero__scroll" aria-hidden="true">
      <span>SCROLL</span>
      <div class="hero__scroll-line"></div>
    </div>
  </section>

  <!-- PHILOSOPHY -->
  <section class="section philosophy" id="philosophy" data-reveal>
    <div class="container">
      <div class="philosophy__grid">
        <div class="philosophy__visual">
          <img src="<?= e($philosophy['image']) ?>" alt="<?= e($philosophy['image_caption']) ?>" loading="lazy">
          <p class="philosophy__visual-caption"><?= e($philosophy['image_caption']) ?></p>
        </div>
        <div class="philosophy__body">
          <p class="eyebrow"><?= e($philosophy['eyebrow']) ?></p>
          <h2 class="philosophy__title">
            <?php foreach ($philosophy['title_lines'] as $line): ?>
              <span class="line"><span class="line__inner"><?= e($line) ?></span></span>
            <?php endforeach; ?>
          </h2>
          <div class="philosophy__text">
            <?php foreach ($philosophy['paragraphs'] as $para): ?>
              <p><?= e($para) ?></p>
            <?php endforeach; ?>
          </div>
          <div class="philosophy__signature">
            <div>
              <div class="philosophy__signature-name"><?= e($philosophy['signature_name']) ?></div>
              <div class="philosophy__signature-role"><?= e($philosophy['signature_role']) ?></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- MENU -->
  <section class="section menu" id="menu" data-reveal>
    <div class="container">
      <div class="menu__header">
        <p class="eyebrow"><?= e($menu['eyebrow']) ?></p>
        <h2 class="menu__title"><?= $menu['title'] ?></h2>
        <p class="menu__subtitle"><?= e($menu['subtitle']) ?></p>
      </div>
      <div class="menu__list" data-stagger>
        <?php foreach ($menu['items'] as $item): ?>
          <article class="menu__item" data-stagger-item>
            <div class="menu__number"><?= e($item['number']) ?></div>
            <div class="menu__body">
              <div class="menu__name-row">
                <span class="menu__name-jp"><?= e($item['name_jp']) ?></span>
                <span class="menu__name-en"><?= e($item['name_en']) ?></span>
              </div>
              <div class="menu__method"><?= e($item['method']) ?></div>
              <div class="menu__notes"><?= e($item['notes']) ?></div>
              <p class="menu__description"><?= e($item['description']) ?></p>
            </div>
            <div class="menu__price"><?= e($item['price']) ?></div>
          </article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- PROCESS -->
  <section class="section process" id="method" data-reveal>
    <div class="process__grid">
      <div class="process__header">
        <p class="eyebrow"><?= e($process['eyebrow']) ?></p>
        <h2 class="process__title"><?= $process['title'] ?></h2>
        <p class="process__subtitle"><?= e($process['subtitle']) ?></p>
        <div class="process__meta">
          <?php foreach ($process['meta'] as $m): ?>
            <div>
              <span class="process__meta-label"><?= e($m['label']) ?></span>
              <span class="process__meta-value"><?= e($m['value']) ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="process__steps" data-stagger>
        <?php foreach ($process['steps'] as $i => $step): ?>
          <article class="step" data-stagger-item>
            <div class="step__header">
              <span class="step__day"><?= e($step['day']) ?></span>
              <span class="step__number">0<?= $i + 1 ?></span>
            </div>
            <h3 class="step__name"><?= e($step['name']) ?></h3>
            <p class="step__description"><?= e($step['description']) ?></p>
          </article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- GALLERY -->
  <section class="section gallery" id="room" data-reveal>
    <div class="gallery__header">
      <p class="eyebrow"><?= e($gallery['eyebrow']) ?></p>
      <h2 class="gallery__title"><?= $gallery['title'] ?></h2>
      <p class="gallery__subtitle"><?= e($gallery['subtitle']) ?></p>
    </div>
    <div class="gallery__pairs">
      <?php foreach ($gallery['pairs'] as $i => $pair): ?>
        <div class="pair <?= $i % 2 === 1 ? 'pair--offset' : '' ?>" data-reveal>
          <div class="pair__left">
            <img src="<?= e($pair['left']['url']) ?>" alt="<?= e($pair['left']['alt']) ?>" loading="lazy">
            <span class="pair__caption"><?= e($pair['left']['caption']) ?></span>
          </div>
          <div class="pair__right">
            <img src="<?= e($pair['right']['url']) ?>" alt="<?= e($pair['right']['alt']) ?>" loading="lazy">
            <span class="pair__caption"><?= e($pair['right']['caption']) ?></span>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- VISIT -->
  <section class="section visit" id="visit" data-reveal>
    <div class="visit__grid">
      <div class="visit__header">
        <p class="eyebrow"><?= e($visit['eyebrow']) ?></p>
        <h2 class="visit__title"><?= $visit['title'] ?></h2>
        <p class="visit__lead"><?= e($visit['lead']) ?></p>
        <div class="visit__address">
          <p class="visit__address-label">Address</p>
          <p class="visit__address-text">
            <?php foreach ($visit['address_lines'] as $line): ?>
              <?= e($line) ?><br>
            <?php endforeach; ?>
          </p>
        </div>
        <div class="visit__info">
          <div class="visit__info-item">
            <p class="visit__info-label">Telephone</p>
            <p class="visit__info-value"><?= e($visit['phone']) ?></p>
          </div>
          <div class="visit__info-item">
            <p class="visit__info-label">Email</p>
            <p class="visit__info-value"><?= e($visit['email']) ?></p>
          </div>
        </div>
        <div class="visit__cta">
          <a href="tel:<?= preg_replace('/[^0-9+]/', '', $visit['phone']) ?>" class="btn btn--primary"><?= e($visit['cta_primary']) ?></a>
          <a href="https://maps.google.com/?q=Kurama+Kyoto+Japan" target="_blank" rel="noopener" class="btn btn--ghost"><?= e($visit['cta_secondary']) ?></a>
        </div>
      </div>
      <div class="visit__hours">
        <p class="visit__hours-label">Opening Hours</p>
        <div class="visit__hours-list">
          <?php foreach ($visit['hours'] as $h): ?>
            <div class="visit__hours-row">
              <span class="visit__hours-day"><?= e($h['day']) ?></span>
              <span class="visit__hours-time"><?= e($h['time']) ?></span>
            </div>
          <?php endforeach; ?>
        </div>
        <p class="visit__note"><?= e($visit['note']) ?></p>
      </div>
    </div>
  </section>
</main>

<?php include __DIR__ . '/partials/footer.php'; ?>