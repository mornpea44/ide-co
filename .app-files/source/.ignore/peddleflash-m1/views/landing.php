<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Peddleflash — Buy &amp; sell locally, in a flash</title>
  <meta name="description" content="Peddleflash connects you with nearby buyers and sellers. List in minutes, meet around the corner.">
  <link rel="stylesheet" href="../assets/css/landing.css">
</head>

<body id="top">

  <header class="site-header">
    <div class="container header-inner">
      <a class="brand" href="index.php?route=home">
        <span class="brand-mark" aria-hidden="true">
          <svg viewBox="0 0 24 24" width="18" height="18">
            <path d="M13 2 4 14h6l-1 8 9-12h-6l1-8z" fill="currentColor" />
          </svg>
        </span>
        <span class="brand-name">Peddle<b>flash</b></span>
      </a>
      <nav class="main-nav" id="main-nav" aria-label="Main">
        <a href="#categories">Categories</a>
        <a href="#how">How it works</a>
        <a href="#featured">Fresh finds</a>
      </nav>
      <div class="header-actions">
        <a class="btn btn-ghost" href="index.php?route=login">Log in</a>
        <a class="btn btn-brand" href="index.php?route=login">Start selling</a>
        <button class="nav-toggle" id="nav-toggle" aria-label="Open menu" aria-expanded="false">
          <svg viewBox="0 0 24 24" width="20" height="20">
            <path d="M4 7h16M4 12h16M4 17h16" stroke="currentColor" stroke-width="2" stroke-linecap="round" fill="none" />
          </svg>
        </button>
      </div>
    </div>
  </header>

  <main>
    <!-- ── Hero ── -->
    <section class="hero">
      <div class="container hero-grid">
        <div class="hero-copy reveal">
          <span class="hero-kicker">⚡ The local marketplace</span>
          <h1>Sell it in a <span class="flash">flash</span>.</h1>
          <p class="hero-sub">Peddleflash connects you with buyers and sellers around the corner.
            List in minutes, meet nearby, keep more of your money — your first three listings are fee&#8209;free.</p>
          <form class="hero-search" id="hero-search" role="search">
            <input id="hero-search-input" type="search" placeholder="Try “road bike” or “sofa”…" aria-label="Search listings">
            <button class="btn btn-brand" type="submit">Search</button>
          </form>
          <ul class="hero-tags" aria-label="Popular categories">
            <li><button type="button" data-tag="bikes">🚲 Bikes</button></li>
            <li><button type="button" data-tag="tech">📱 Tech</button></li>
            <li><button type="button" data-tag="home">🛋️ Home</button></li>
            <li><button type="button" data-tag="fashion">🧥 Fashion</button></li>
          </ul>
        </div>
        <div class="hero-art reveal" aria-hidden="true">
          <div class="hero-blob"></div>
          <div class="float-card fc-1"><span>📱</span>
            <div><b>Pixel 7 — mint</b><small>$340 · Riverside</small></div>
          </div>
          <div class="float-card fc-2"><span>🛋️</span>
            <div><b>Loft sofa</b><small>$180 · Old Town</small></div>
          </div>
          <div class="float-card fc-3"><span>🚲</span>
            <div><b>Fixie 54 cm</b><small>$220 · Central</small></div>
          </div>
        </div>
      </div>
    </section>

    <!-- ── Stats ─ -->
    <section class="stats-band">
      <div class="container stats">
        <div><b data-count="12400">0</b><span>live listings</span></div>
        <div><b data-count="38">0</b><span>cities</span></div>
        <div><b data-count="97" data-suffix="%">0</b><span>happy sellers</span></div>
      </div>
    </section>

    <!-- ── Categories ── -->
    <section class="section" id="categories">
      <div class="container">
        <div class="section-head reveal">
          <h2>Browse by category</h2>
          <p>From spare phones to spare sofas — someone nearby wants it.</p>
        </div>
        <div class="cat-grid">
          <div class="cat-card reveal"><span class="cat-ico">📱</span><span class="cat-name">Tech</span><span class="cat-count">2,140 listings</span></div>
          <div class="cat-card reveal"><span class="cat-ico">🛋️</span><span class="cat-name">Home</span><span class="cat-count">3,320 listings</span></div>
          <div class="cat-card reveal"><span class="cat-ico">🚲</span><span class="cat-name">Bikes</span><span class="cat-count">980 listings</span></div>
          <div class="cat-card reveal"><span class="cat-ico">🧥</span><span class="cat-name">Fashion</span><span class="cat-count">1,760 listings</span></div>
          <div class="cat-card reveal"><span class="cat-ico">🎸</span><span class="cat-name">Music</span><span class="cat-count">640 listings</span></div>
          <div class="cat-card reveal"><span class="cat-ico"></span><span class="cat-name">Garden</span><span class="cat-count">510 listings</span></div>
        </div>
      </div>
    </section>

    <!-- ── How it works ── -->
    <section class="section section-alt" id="how">
      <div class="container">
        <div class="section-head reveal">
          <h2>How it works</h2>
          <p>Three steps between you and a done deal.</p>
        </div>
        <div class="steps">
          <div class="step reveal"><span class="step-num">1</span>
            <h3>Snap &amp; list</h3>
            <p>Take a photo, set a price, publish. Most listings go live in under four minutes.</p>
          </div>
          <div class="step reveal"><span class="step-num">2</span>
            <h3>Chat nearby</h3>
            <p>Buyers message you instantly. Agree on a time and a public spot that suits you both.</p>
          </div>
          <div class="step reveal"><span class="step-num">3</span>
            <h3>Hand it over</h3>
            <p>Meet, check, done. No shipping labels, no fees on your first three listings.</p>
          </div>
        </div>
      </div>
    </section>

    <!-- ── Featured listings ── -->
    <section class="section" id="featured">
      <div class="container">
        <div class="section-head reveal">
          <h2>Fresh finds near you</h2>
          <p>A taste of what neighbours are listing right now.</p>
        </div>
        <div class="chip-row reveal" id="filter-chips">
          <button class="chip active" data-filter="all">All</button>
          <button class="chip" data-filter="tech">Tech</button>
          <button class="chip" data-filter="home">Home</button>
          <button class="chip" data-filter="bikes">Bikes</button>
          <button class="chip" data-filter="fashion">Fashion</button>
        </div>
        <div class="listing-grid" id="listing-grid">
          <article class="listing-card reveal" data-category="tech">
            <div class="listing-thumb">📱</div>
            <div class="listing-body">
              <div class="listing-top">
                <h3 class="listing-title">Pixel 7 — mint condition</h3><span class="listing-price">$340</span>
              </div>
              <p class="listing-meta">Riverside · 2 h ago</p>
              <div class="listing-foot"><span class="badge badge-active">Active</span><span class="listing-views">👁 132</span></div>
            </div>
          </article>
          <article class="listing-card reveal" data-category="bikes">
            <div class="listing-thumb">🚲</div>
            <div class="listing-body">
              <div class="listing-top">
                <h3 class="listing-title">Fixie bike, 54 cm</h3><span class="listing-price">$220</span>
              </div>
              <p class="listing-meta">Old Town · 5 h ago</p>
              <div class="listing-foot"><span class="badge badge-active">Active</span><span class="listing-views">👁 98</span></div>
            </div>
          </article>
          <article class="listing-card reveal" data-category="home">
            <div class="listing-thumb">🛋️</div>
            <div class="listing-body">
              <div class="listing-top">
                <h3 class="listing-title">Loft sofa, grey</h3><span class="listing-price">$180</span>
              </div>
              <p class="listing-meta">Riverside · 1 d ago</p>
              <div class="listing-foot"><span class="badge badge-pending">Pending</span><span class="listing-views">👁 41</span></div>
            </div>
          </article>
          <article class="listing-card reveal" data-category="fashion">
            <div class="listing-thumb">🧥</div>
            <div class="listing-body">
              <div class="listing-top">
                <h3 class="listing-title">Denim jacket, size M</h3><span class="listing-price">$45</span>
              </div>
              <p class="listing-meta">Central · 2 d ago</p>
              <div class="listing-foot"><span class="badge badge-active">Active</span><span class="listing-views">👁 67</span></div>
            </div>
          </article>
          <article class="listing-card reveal" data-category="home">
            <div class="listing-thumb">☕</div>
            <div class="listing-body">
              <div class="listing-top">
                <h3 class="listing-title">Espresso machine</h3><span class="listing-price">$120</span>
              </div>
              <p class="listing-meta">Old Town · 3 d ago</p>
              <div class="listing-foot"><span class="badge badge-sold">Sold</span><span class="listing-views">👁 214</span></div>
            </div>
          </article>
          <article class="listing-card reveal" data-category="tech">
            <div class="listing-thumb">⌨️</div>
            <div class="listing-body">
              <div class="listing-top">
                <h3 class="listing-title">Mechanical keyboard</h3><span class="listing-price">$75</span>
              </div>
              <p class="listing-meta">Central · 4 d ago</p>
              <div class="listing-foot"><span class="badge badge-active">Active</span><span class="listing-views">👁 58</span></div>
            </div>
          </article>
          <article class="listing-card reveal" data-category="bikes">
            <div class="listing-thumb">🛴</div>
            <div class="listing-body">
              <div class="listing-top">
                <h3 class="listing-title">City scooter</h3><span class="listing-price">$95</span>
              </div>
              <p class="listing-meta">Riverside · 5 d ago</p>
              <div class="listing-foot"><span class="badge badge-active">Active</span><span class="listing-views">👁 73</span></div>
            </div>
          </article>
          <article class="listing-card reveal" data-category="fashion">
            <div class="listing-thumb">👟</div>
            <div class="listing-body">
              <div class="listing-top">
                <h3 class="listing-title">Runner sneakers, 42</h3><span class="listing-price">$60</span>
              </div>
              <p class="listing-meta">Central · 6 d ago</p>
              <div class="listing-foot"><span class="badge badge-sold">Sold</span><span class="listing-views">👁 156</span></div>
            </div>
          </article>
        </div>
        <p class="filter-empty" id="filter-empty" style="display:none">Nothing matches that search yet — try another word.</p>
      </div>
    </section>

    <!-- ── CTA ── -->
    <section class="cta-band">
      <div class="container cta-inner reveal">
        <h2>Got something gathering dust?</h2>
        <p>Turn it into cash this week. Your neighbours are already browsing.</p>
        <a class="btn btn-light" href="index.php?route=login">Create a free account</a>
      </div>
    </section>
  </main>

  <footer class="site-footer">
    <div class="container footer-inner">
      <span>© <span id="year">2025</span> Peddleflash · Made for neighbours.</span>
      <span class="footer-links"><a href="#top">Back to top</a> · <a href="index.php?route=login">Seller login</a></span>
    </div>
  </footer>

  <script src="../assets/js/landing.js"></script>
</body>

</html>