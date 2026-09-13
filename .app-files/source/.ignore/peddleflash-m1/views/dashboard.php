<?php

/** @var array|null rendered by index.php (auth-guarded route) */
$member    = pf_current_member();
$nameParts = explode(' ', (string) ($member['name'] ?? 'Member'));
$firstName = pf_e($nameParts[0]);
$initials  = pf_e(
  strtoupper(
    substr($nameParts[0], 0, 1) . (isset($nameParts[1]) ? substr($nameParts[1], 0, 1) : '')
  )
);

$listings = [
  ['title' => 'Pixel 7 — mint condition', 'price' => 340, 'cat' => 'tech',    'ico' => '📱', 'meta' => 'Riverside · 2 h ago',  'status' => 'active',  'views' => 132],
  ['title' => 'Fixie bike, 54 cm',        'price' => 220, 'cat' => 'bikes',   'ico' => '🚲', 'meta' => 'Old Town · 5 h ago',   'status' => 'active',  'views' => 98],
  ['title' => 'Loft sofa, grey',          'price' => 180, 'cat' => 'home',    'ico' => '🛋️', 'meta' => 'Riverside · 1 d ago',  'status' => 'pending', 'views' => 41],
  ['title' => 'Denim jacket, size M',     'price' => 45,  'cat' => 'fashion', 'ico' => '🧥', 'meta' => 'Central · 2 d ago',    'status' => 'active',  'views' => 67],
  ['title' => 'Espresso machine',         'price' => 120, 'cat' => 'home',    'ico' => '☕', 'meta' => 'Old Town · 3 d ago',   'status' => 'sold',    'views' => 214],
  ['title' => 'Mechanical keyboard',      'price' => 75,  'cat' => 'tech',    'ico' => '⌨️', 'meta' => 'Central · 4 d ago',    'status' => 'active',  'views' => 58],
];
$statusLabel = ['active' => 'Active', 'pending' => 'Pending', 'sold' => 'Sold'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dashboard — Peddleflash</title>
  <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>

<body class="dash-body">

  <header class="dash-topbar">
    <div class="tb-inner">
      <a class="brand" href="index.php?route=home">
        <span class="brand-mark" aria-hidden="true">
          <svg viewBox="0 0 24 24" width="18" height="18">
            <path d="M13 2 4 14h6l-1 8 9-12h-6l1-8z" fill="currentColor" />
          </svg>
        </span>
        <span class="brand-name">Peddle<b>flash</b></span>
      </a>
      <div class="dash-search">
        <input type="search" id="listing-search" placeholder="Filter your listings…" aria-label="Filter listings">
      </div>
      <div class="tb-actions">
        <button class="btn-brand" id="new-listing-btn" type="button">＋ New listing</button>
        <span class="avatar" title="<?php echo pf_e((string) ($member['name'] ?? '')); ?>"><?php echo $initials; ?></span>
        <a class="tb-logout" href="index.php?route=logout">Log out</a>
      </div>
    </div>
  </header>

  <main class="container dash-main">
    <div class="dash-welcome">
      <h1>Good to see you, <?php echo $firstName; ?> 👋</h1>
      <p>Here’s what’s happening with your listings today.</p>
    </div>

    <section class="stat-cards">
      <div class="stat-card"><span class="stat-num" data-count="6">0</span><span class="stat-label">Active listings</span><span class="stat-delta up">▲ 2 this week</span></div>
      <div class="stat-card"><span class="stat-num" data-count="610">0</span><span class="stat-label">Profile views</span><span class="stat-delta up">▲ 18%</span></div>
      <div class="stat-card"><span class="stat-num" data-count="14">0</span><span class="stat-label">Unread messages</span><span class="stat-delta flat">— steady</span></div>
    </section>

    <div class="dash-head">
      <h2>Your listings</h2>
      <div class="chip-row" id="dash-filters">
        <button class="chip active" data-filter="all">All</button>
        <button class="chip" data-filter="tech">Tech</button>
        <button class="chip" data-filter="home">Home</button>
        <button class="chip" data-filter="bikes">Bikes</button>
        <button class="chip" data-filter="fashion">Fashion</button>
      </div>
    </div>

    <div class="listing-grid" id="listing-grid">
      <?php foreach ($listings as $item): ?>
        <article class="listing-card" data-category="<?php echo pf_e($item['cat']); ?>">
          <div class="listing-thumb"><?php echo $item['ico']; ?></div>
          <div class="listing-body">
            <div class="listing-top">
              <h3 class="listing-title"><?php echo pf_e($item['title']); ?></h3>
              <span class="listing-price">$<?php echo (int) $item['price']; ?></span>
            </div>
            <p class="listing-meta"><?php echo pf_e($item['meta']); ?></p>
            <div class="listing-foot">
              <span class="badge badge-<?php echo pf_e($item['status']); ?>"><?php echo $statusLabel[$item['status']]; ?></span>
              <span class="listing-views">👁 <?php echo (int) $item['views']; ?></span>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
    <p class="filter-empty" id="filter-empty" style="display:none">No listings match that filter.</p>
  </main>

  <!-- ── New listing modal ── -->
  <div class="modal-backdrop" id="listing-modal" aria-hidden="true">
    <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="modal-title">
      <div class="modal-head">
        <h2 id="modal-title">New listing</h2>
        <button class="modal-close" id="modal-close" type="button" aria-label="Close">✕</button>
      </div>
      <form id="modal-form">
        <div class="field">
          <label for="nl-title">Title</label>
          <input id="nl-title" type="text" placeholder="e.g. Road bike, 56 cm" required>
        </div>
        <div class="field-row">
          <div class="field">
            <label for="nl-price">Price ($)</label>
            <input id="nl-price" type="number" min="1" max="99999" value="50" required>
          </div>
          <div class="field">
            <label for="nl-category">Category</label>
            <select id="nl-category">
              <option value="tech">Tech</option>
              <option value="home">Home</option>
              <option value="bikes">Bikes</option>
              <option value="fashion">Fashion</option>
            </select>
          </div>
        </div>
        <div class="modal-actions">
          <button class="btn-ghost" id="modal-cancel" type="button">Cancel</button>
          <button class="btn-brand" type="submit">Publish listing</button>
        </div>
      </form>
    </div>
  </div>

  <script src="../assets/js/dashboard.js"></script>
</body>

</html>