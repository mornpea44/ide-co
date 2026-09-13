<!DOCTYPE html>
<html lang="en" data-theme="night">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>AESIR × KAMI RAILWAYS — 神々の鉄道</title>
  <meta name="description" content="A mythic railway uniting the realms of Norse Gods and Japanese Kami. Book your divine journey today.">
  <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Cinzel+Decorative:wght@400;700;900&family=Cinzel:wght@400;500;600;700&family=Noto+Serif+JP:wght@400;500;600;700;900&family=Shippori+Mincho:wght@400;600;800&display=swap" rel="stylesheet">

  <style>
    :root {
      --bg-void: #070713;
      --bg-deep: #0c0f24;
      --bg-panel: #12162e;
      --bg-panel-2: #171c3a;
      --gold: #d4af37;
      --gold-soft: #f2d98a;
      --torii-red: #b8362f;
      --torii-red-2: #e0483d;
      --teal: #2f9c95;
      --indigo-glow: #6d5bd0;
      --text-main: #eee7d8;
      --text-dim: #a9a4c4;
      --border-glow: rgba(212, 175, 55, 0.35);
    }

    html[data-theme="day"] {
      --bg-void: #fbf3e3;
      --bg-deep: #fdf6e9;
      --bg-panel: #fffaf0;
      --bg-panel-2: #fff3dd;
      --gold: #b8860b;
      --gold-soft: #8a6300;
      --torii-red: #b8362f;
      --torii-red-2: #c94b3c;
      --teal: #12766f;
      --indigo-glow: #7a63d1;
      --text-main: #2a2015;
      --text-dim: #5c5340;
      --border-glow: rgba(184, 54, 47, 0.35);
    }

    * {
      box-sizing: border-box;
    }

    html {
      scroll-behavior: smooth;
    }

    body {
      background: var(--bg-void);
      color: var(--text-main);
      font-family: 'Cinzel', serif;
      overflow-x: hidden;
      transition: background .6s ease, color .6s ease;
    }

    .font-jp {
      font-family: 'Noto Serif JP', serif;
    }

    .font-display {
      font-family: 'Cinzel Decorative', serif;
    }

    .font-mincho {
      font-family: 'Shippori Mincho', serif;
    }

    #particle-canvas {
      position: fixed;
      inset: 0;
      width: 100%;
      height: 100%;
      z-index: 0;
      pointer-events: none;
    }

    .grain::before {
      content: "";
      position: fixed;
      inset: 0;
      z-index: 1;
      pointer-events: none;
      opacity: .04;
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='120' height='120'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='2' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
    }

    .seigaiha {
      background-image: radial-gradient(circle at 50% 100%, transparent 20px, var(--border-glow) 21px, var(--border-glow) 22px, transparent 23px);
      background-size: 44px 22px;
      background-position: 0 0, 22px 11px;
    }

    .divine-border {
      border: 1px solid var(--border-glow);
      box-shadow: 0 0 0 1px rgba(212, 175, 55, 0.08), 0 10px 40px -10px rgba(0, 0, 0, .6);
    }

    .glow-gold {
      text-shadow: 0 0 18px rgba(212, 175, 55, .55), 0 0 40px rgba(212, 175, 55, .25);
    }

    .glow-red {
      text-shadow: 0 0 18px rgba(224, 72, 61, .5);
    }

    .rune-divider {
      letter-spacing: .5em;
      color: var(--gold);
      opacity: .55;
      font-size: .85rem;
    }

    /* Torii logo mark */
    .torii-mark path {
      stroke: var(--gold);
    }

    /* Nav */
    #main-nav {
      backdrop-filter: blur(14px);
      background: linear-gradient(to bottom, rgba(7, 7, 19, .85), rgba(7, 7, 19, .55));
      border-bottom: 1px solid var(--border-glow);
      transition: all .4s ease;
    }

    html[data-theme="day"] #main-nav {
      background: linear-gradient(to bottom, rgba(251, 243, 227, .9), rgba(251, 243, 227, .6));
    }

    .nav-link {
      position: relative;
    }

    .nav-link::after {
      content: "";
      position: absolute;
      left: 0;
      bottom: -6px;
      height: 1px;
      width: 0%;
      background: var(--gold);
      transition: width .35s ease;
    }

    .nav-link:hover::after,
    .nav-link.active::after {
      width: 100%;
    }

    /* Hero */
    .hero-title {
      background: linear-gradient(120deg, var(--gold-soft), var(--gold) 40%, var(--torii-red-2) 70%, var(--gold-soft));
      background-size: 300% 300%;
      -webkit-background-clip: text;
      background-clip: text;
      color: transparent;
      animation: shimmer 8s ease infinite;
    }

    @keyframes shimmer {
      0% {
        background-position: 0% 50%
      }

      50% {
        background-position: 100% 50%
      }

      100% {
        background-position: 0% 50%
      }
    }

    .aurora {
      position: absolute;
      inset: 0;
      overflow: hidden;
      z-index: 0;
      background:
        radial-gradient(60% 50% at 20% 10%, rgba(109, 91, 208, .35), transparent 60%),
        radial-gradient(50% 40% at 80% 0%, rgba(47, 156, 149, .28), transparent 60%),
        radial-gradient(70% 60% at 50% 100%, rgba(184, 54, 47, .25), transparent 60%);
      animation: auroraShift 14s ease-in-out infinite alternate;
    }

    @keyframes auroraShift {
      0% {
        filter: hue-rotate(0deg) brightness(1);
      }

      100% {
        filter: hue-rotate(25deg) brightness(1.15);
      }
    }

    .torii-frame {
      filter: drop-shadow(0 0 25px rgba(212, 175, 55, .25));
    }

    .scroll-indicator {
      animation: bob 2s ease-in-out infinite;
    }

    @keyframes bob {

      0%,
      100% {
        transform: translateY(0);
      }

      50% {
        transform: translateY(10px);
      }
    }

    /* Cards */
    .line-card {
      background: linear-gradient(160deg, var(--bg-panel), var(--bg-panel-2));
      border: 1px solid var(--border-glow);
      transition: transform .45s cubic-bezier(.2, .8, .2, 1), box-shadow .45s ease, border-color .45s ease;
    }

    .line-card:hover {
      transform: translateY(-10px) scale(1.015);
      box-shadow: 0 25px 60px -20px var(--line-color, rgba(212, 175, 55, .4));
      border-color: var(--line-color, var(--gold));
    }

    .line-chip {
      background: var(--line-color);
    }

    /* Deity flip cards */
    .flip-card {
      perspective: 1400px;
      height: 280px;
    }

    .flip-inner {
      position: relative;
      width: 100%;
      height: 100%;
      transition: transform .7s cubic-bezier(.4, .2, .2, 1);
      transform-style: preserve-3d;
    }

    .flip-card.flipped .flip-inner {
      transform: rotateY(180deg);
    }

    .flip-face {
      position: absolute;
      inset: 0;
      backface-visibility: hidden;
      border-radius: 1rem;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 1.25rem;
    }

    .flip-back {
      transform: rotateY(180deg);
    }

    /* Reveal */
    .reveal {
      opacity: 0;
      transform: translateY(40px);
      transition: opacity .9s ease, transform .9s ease;
    }

    .reveal.in {
      opacity: 1;
      transform: translateY(0);
    }

    /* Departure board flip */
    .flip-status {
      display: inline-block;
      transition: all .3s ease;
    }

    .board-row {
      transition: background .3s ease;
    }

    .board-row:hover {
      background: rgba(212, 175, 55, .06);
    }

    @keyframes flicker {

      0%,
      100% {
        opacity: 1;
      }

      45% {
        opacity: 1;
      }

      46% {
        opacity: .4;
      }

      47% {
        opacity: 1;
      }

      78% {
        opacity: 1;
      }

      79% {
        opacity: .5;
      }

      80% {
        opacity: 1;
      }
    }

    .board-flicker {
      animation: flicker 6s infinite;
    }

    ::-webkit-scrollbar {
      width: 10px;
    }

    ::-webkit-scrollbar-track {
      background: var(--bg-void);
    }

    ::-webkit-scrollbar-thumb {
      background: var(--gold);
      border-radius: 10px;
    }

    input[type="date"]::-webkit-calendar-picker-indicator {
      filter: invert(.6) sepia(1) saturate(4) hue-rotate(1deg);
      cursor: pointer;
    }

    .toggle-track {
      width: 60px;
      height: 30px;
      border-radius: 999px;
      position: relative;
      cursor: pointer;
      background: linear-gradient(90deg, #1a1a2e, #0c0f24);
      border: 1px solid var(--border-glow);
    }

    .toggle-dot {
      position: absolute;
      top: 2px;
      left: 2px;
      width: 24px;
      height: 24px;
      border-radius: 50%;
      background: var(--gold);
      transition: left .4s cubic-bezier(.5, .2, .2, 1.4);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: .7rem;
    }

    html[data-theme="day"] .toggle-dot {
      left: 32px;
      background: var(--torii-red-2);
    }

    .modal-overlay {
      backdrop-filter: blur(6px);
      background: rgba(0, 0, 0, .6);
    }

    .accordion-content {
      max-height: 0;
      overflow: hidden;
      transition: max-height .5s ease;
    }

    input:focus,
    select:focus,
    textarea:focus {
      outline: none;
      box-shadow: 0 0 0 2px var(--gold);
    }

    .btn-primary {
      background: linear-gradient(135deg, var(--torii-red), var(--torii-red-2));
      box-shadow: 0 8px 25px -8px rgba(184, 54, 47, .6);
      transition: transform .3s ease, box-shadow .3s ease;
    }

    .btn-primary:hover {
      transform: translateY(-3px);
      box-shadow: 0 15px 35px -8px rgba(184, 54, 47, .8);
    }

    .btn-gold {
      background: linear-gradient(135deg, var(--gold), var(--gold-soft));
      color: #241a05;
      transition: transform .3s ease, box-shadow .3s ease;
    }

    .btn-gold:hover {
      transform: translateY(-3px);
      box-shadow: 0 15px 35px -8px rgba(212, 175, 55, .7);
    }

    .stat-num {
      font-variant-numeric: tabular-nums;
    }

    .marquee-wrap {
      overflow: hidden;
      white-space: nowrap;
    }

    .marquee-track {
      display: inline-block;
      padding-left: 100%;
      animation: marquee 30s linear infinite;
    }

    @keyframes marquee {
      0% {
        transform: translateX(0);
      }

      100% {
        transform: translateX(-100%);
      }
    }

    .testimonial-track {
      transition: transform .6s ease;
    }

    .hidden-scroll::-webkit-scrollbar {
      display: none;
    }

    .hidden-scroll {
      -ms-overflow-style: none;
      scrollbar-width: none;
    }
  </style>
</head>

<body class="grain relative">

  <canvas id="particle-canvas"></canvas>

  <!-- ============ NAV ============ -->
  <nav id="main-nav" class="fixed top-0 left-0 w-full z-50">
    <div class="max-w-7xl mx-auto px-5 md:px-8 flex items-center justify-between h-20">
      <a href="#home" class="flex items-center gap-3 group">
        <svg class="torii-mark w-10 h-10" viewBox="0 0 64 64" fill="none" stroke-width="3">
          <path d="M6 16 L58 16" stroke-linecap="round" />
          <path d="M4 22 L60 22" stroke-linecap="round" />
          <path d="M18 22 L15 58" stroke-linecap="round" />
          <path d="M46 22 L49 58" stroke-linecap="round" />
          <path d="M25 30 L39 30" stroke-linecap="round" />
        </svg>
        <div class="leading-tight">
          <p class="font-display text-lg md:text-xl tracking-wide" style="color:var(--gold)">AESIR<span style="color:var(--torii-red-2)">×</span>KAMI</p>
          <p class="font-jp text-[10px] tracking-[.3em]" style="color:var(--text-dim)">神々の鉄道 RAILWAYS</p>
        </div>
      </a>

      <div class="hidden lg:flex items-center gap-8 font-jp text-sm tracking-wide" id="nav-links">
        <a href="#lines" class="nav-link">Divine Lines</a>
        <a href="#booking" class="nav-link">Book Passage</a>
        <a href="#departures" class="nav-link">Departures</a>
        <a href="#deities" class="nav-link">Guardians</a>
        <a href="#testimonials" class="nav-link">Voices</a>
        <a href="#faq" class="nav-link">FAQ</a>
      </div>

      <div class="flex items-center gap-4">
        <div class="toggle-track" id="theme-toggle" title="Toggle Day (Amaterasu) / Night (Odin)">
          <div class="toggle-dot" id="toggle-dot">☀</div>
        </div>
        <button id="hamburger" class="lg:hidden flex flex-col gap-1.5 w-8">
          <span class="h-[2px] w-full" style="background:var(--gold)"></span>
          <span class="h-[2px] w-full" style="background:var(--gold)"></span>
          <span class="h-[2px] w-3/4" style="background:var(--gold)"></span>
        </button>
      </div>
    </div>

    <div id="mobile-menu" class="lg:hidden hidden flex-col px-6 pb-6 gap-4 font-jp text-sm divine-border border-t-0" style="background:var(--bg-deep)">
      <a href="#lines" class="nav-link py-1">Divine Lines</a>
      <a href="#booking" class="nav-link py-1">Book Passage</a>
      <a href="#departures" class="nav-link py-1">Departures</a>
      <a href="#deities" class="nav-link py-1">Guardians</a>
      <a href="#testimonials" class="nav-link py-1">Voices</a>
      <a href="#faq" class="nav-link py-1">FAQ</a>
    </div>
  </nav>

  <!-- ============ HERO ============ -->
  <section id="home" class="relative min-h-screen flex items-center justify-center pt-24 pb-10 seigaiha">
    <div class="aurora"></div>
    <div class="relative z-10 max-w-6xl mx-auto px-6 text-center">
      <p class="rune-divider font-display mb-4">ᚠ ᚢ ᚦ ᚨ ᚱ ᚲ ⛩ 神 ⛩ ᚷ ᚹ ᚺ ᚾ</p>
      <h1 class="hero-title font-display text-4xl sm:text-6xl md:text-7xl font-black leading-tight mb-6">
        RAILWAYS OF GODS<br class="hidden sm:block"> AND KAMI
      </h1>
      <p class="font-jp text-base md:text-lg max-w-2xl mx-auto mb-3" style="color:var(--text-dim)">
        北欧の神々と日本の神が結ぶ、天と地を渡る鉄道
      </p>
      <p class="max-w-2xl mx-auto text-sm md:text-base mb-10" style="color:var(--text-dim)">
        Where the roots of Yggdrasil meet the torii of Takamagahara — one sacred rail network binds
        nine realms and eight million kami. Board a train blessed by Odin, guided by Amaterasu.
      </p>
      <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
        <a href="#booking" class="btn-primary px-8 py-4 rounded-full font-jp tracking-wide text-sm">✦ Book a Divine Journey</a>
        <a href="#lines" class="btn-gold px-8 py-4 rounded-full font-jp tracking-wide text-sm">⛩ Explore the Lines</a>
      </div>

      <div class="grid grid-cols-2 md:grid-cols-4 gap-6 mt-16 max-w-3xl mx-auto">
        <div class="divine-border rounded-xl py-5 px-2" style="background:rgba(18,22,46,.5)">
          <p class="stat-num font-display text-2xl md:text-3xl" style="color:var(--gold)" data-count="9">0</p>
          <p class="text-[11px] tracking-widest mt-1 font-jp" style="color:var(--text-dim)">REALMS LINKED</p>
        </div>
        <div class="divine-border rounded-xl py-5 px-2" style="background:rgba(18,22,46,.5)">
          <p class="stat-num font-display text-2xl md:text-3xl" style="color:var(--gold)" data-count="42">0</p>
          <p class="text-[11px] tracking-widest mt-1 font-jp" style="color:var(--text-dim)">SACRED STATIONS</p>
        </div>
        <div class="divine-border rounded-xl py-5 px-2" style="background:rgba(18,22,46,.5)">
          <p class="stat-num font-display text-2xl md:text-3xl" style="color:var(--gold)" data-count="1247">0</p>
          <p class="text-[11px] tracking-widest mt-1 font-jp" style="color:var(--text-dim)">YEARS IN SERVICE</p>
        </div>
        <div class="divine-border rounded-xl py-5 px-2" style="background:rgba(18,22,46,.5)">
          <p class="stat-num font-display text-2xl md:text-3xl" style="color:var(--gold)" data-count="16">0</p>
          <p class="text-[11px] tracking-widest mt-1 font-jp" style="color:var(--text-dim)">DEITY GUARDIANS</p>
        </div>
      </div>
    </div>

    <div class="scroll-indicator absolute bottom-6 left-1/2 -translate-x-1/2 text-2xl" style="color:var(--gold)">⟶ ⟶ ⟶ ↓</div>
  </section>

  <!-- ============ LEGEND / ABOUT ============ -->
  <section id="legend" class="relative py-24 px-6" style="background:var(--bg-deep)">
    <div class="max-w-6xl mx-auto grid md:grid-cols-2 gap-14 items-center">
      <div class="reveal">
        <p class="font-jp tracking-[.3em] text-sm mb-3" style="color:var(--teal)">THE LEGEND ｜ 伝説</p>
        <h2 class="font-display text-3xl md:text-4xl mb-6" style="color:var(--gold)">When Bifröst Met the Torii</h2>
        <p class="mb-4 leading-relaxed text-sm md:text-base" style="color:var(--text-dim)">
          Long after Ragnarök's ashes settled and the sun-goddess Amaterasu once again emerged from her cave,
          the Aesir and the Kami struck an ancient pact: a rail line forged from rainbow bridge and shrine gate,
          running iron rails across nine Norse realms and the Plain of High Heaven.
        </p>
        <p class="mb-4 leading-relaxed text-sm md:text-base" style="color:var(--text-dim)">
          Each line is watched over by a guardian deity — Odin lends wisdom to the signal towers, Susanoo commands
          the storms that power the turbines, and Freya's tears of gold light every platform lantern.
        </p>
        <p class="leading-relaxed text-sm md:text-base" style="color:var(--text-dim)">
          Today, mortals, einherjar, and yōkai alike ride together — for on these rails, all realms are equal.
        </p>
        <div class="mt-8 flex gap-6 font-jp text-sm">
          <div><span class="font-display text-xl" style="color:var(--torii-red-2)">卍</span> Sanctified Tracks</div>
          <div><span class="font-display text-xl" style="color:var(--gold)">☯</span> Balanced Realms</div>
        </div>
      </div>
      <div class="reveal relative flex justify-center">
        <svg viewBox="0 0 400 400" class="torii-frame w-full max-w-md">
          <defs>
            <linearGradient id="treeGrad" x1="0" y1="0" x2="0" y2="1">
              <stop offset="0%" stop-color="var(--gold)" />
              <stop offset="100%" stop-color="var(--torii-red-2)" />
            </linearGradient>
          </defs>
          <!-- Yggdrasil trunk -->
          <path d="M200 380 C 190 300, 210 260, 200 200 C 195 150, 205 100, 200 40" stroke="url(#treeGrad)" stroke-width="6" fill="none" stroke-linecap="round" />
          <path d="M200 260 C 160 230, 120 220, 90 180" stroke="url(#treeGrad)" stroke-width="4" fill="none" stroke-linecap="round" />
          <path d="M200 260 C 240 230, 280 220, 310 180" stroke="url(#treeGrad)" stroke-width="4" fill="none" stroke-linecap="round" />
          <path d="M200 160 C 170 130, 140 120, 110 90" stroke="url(#treeGrad)" stroke-width="3" fill="none" stroke-linecap="round" />
          <path d="M200 160 C 230 130, 260 120, 290 90" stroke="url(#treeGrad)" stroke-width="3" fill="none" stroke-linecap="round" />
          <!-- torii gate at base -->
          <path d="M120 380 L280 380" stroke="var(--gold)" stroke-width="5" stroke-linecap="round" />
          <path d="M130 340 L270 340" stroke="var(--torii-red-2)" stroke-width="8" stroke-linecap="round" />
          <path d="M110 330 L290 330" stroke="var(--torii-red-2)" stroke-width="5" stroke-linecap="round" />
          <path d="M150 340 L145 380" stroke="var(--torii-red-2)" stroke-width="6" stroke-linecap="round" />
          <path d="M250 340 L255 380" stroke="var(--torii-red-2)" stroke-width="6" stroke-linecap="round" />
          <!-- train track -->
          <path d="M40 380 L360 380" stroke="var(--text-dim)" stroke-width="2" />
          <path d="M60 372 L60 388 M100 372 L100 388 M300 372 L300 388 M340 372 L340 388" stroke="var(--text-dim)" stroke-width="2" />
          <!-- sun -->
          <circle cx="200" cy="40" r="16" fill="none" stroke="var(--gold)" stroke-width="3" />
        </svg>
      </div>
    </div>
  </section>

  <!-- ============ DIVINE LINES ============ -->
  <section id="lines" class="relative py-24 px-6">
    <div class="max-w-7xl mx-auto">
      <div class="text-center mb-14 reveal">
        <p class="font-jp tracking-[.3em] text-sm mb-3" style="color:var(--teal)">DIVINE LINES ｜ 神々の路線</p>
        <h2 class="font-display text-3xl md:text-5xl" style="color:var(--gold)">Six Sacred Lines</h2>
        <p class="mt-4 max-w-xl mx-auto text-sm" style="color:var(--text-dim)">Each route blessed and bound to a deity's domain — choose your patron, choose your path.</p>
      </div>
      <div id="lines-grid" class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6"></div>
    </div>
  </section>

  <!-- ============ BOOKING ============ -->
  <section id="booking" class="relative py-24 px-6" style="background:var(--bg-deep)">
    <div class="max-w-5xl mx-auto">
      <div class="text-center mb-12 reveal">
        <p class="font-jp tracking-[.3em] text-sm mb-3" style="color:var(--teal)">BOOK PASSAGE ｜ 予約</p>
        <h2 class="font-display text-3xl md:text-5xl" style="color:var(--gold)">Reserve Your Journey</h2>
      </div>

      <form id="booking-form" class="reveal divine-border rounded-2xl p-6 md:p-10 grid md:grid-cols-2 gap-6" style="background:var(--bg-panel)">
        <div>
          <label class="font-jp text-xs tracking-widest" style="color:var(--text-dim)">DEPARTING FROM</label>
          <select id="from-station" required class="w-full mt-2 rounded-lg px-4 py-3 bg-transparent divine-border" style="background:var(--bg-panel-2)"></select>
        </div>
        <div>
          <label class="font-jp text-xs tracking-widest" style="color:var(--text-dim)">ARRIVING AT</label>
          <select id="to-station" required class="w-full mt-2 rounded-lg px-4 py-3 bg-transparent divine-border" style="background:var(--bg-panel-2)"></select>
        </div>
        <div>
          <label class="font-jp text-xs tracking-widest" style="color:var(--text-dim)">JOURNEY DATE</label>
          <input id="travel-date" type="date" required class="w-full mt-2 rounded-lg px-4 py-3 divine-border" style="background:var(--bg-panel-2)">
        </div>
        <div>
          <label class="font-jp text-xs tracking-widest" style="color:var(--text-dim)">TRAVELERS</label>
          <input id="passengers" type="number" min="1" max="8" value="1" required class="w-full mt-2 rounded-lg px-4 py-3 divine-border" style="background:var(--bg-panel-2)">
        </div>
        <div class="md:col-span-2">
          <label class="font-jp text-xs tracking-widest" style="color:var(--text-dim)">CLASS OF PASSAGE</label>
          <div class="grid grid-cols-3 gap-3 mt-2" id="class-select">
            <button type="button" data-class="Mortal" data-mult="1" class="class-btn divine-border rounded-lg py-3 text-xs md:text-sm font-jp">Mortal 人間<br><span class="opacity-60">x1.0</span></button>
            <button type="button" data-class="Kami" data-mult="2.2" class="class-btn divine-border rounded-lg py-3 text-xs md:text-sm font-jp">Kami 神<br><span class="opacity-60">x2.2</span></button>
            <button type="button" data-class="Einherjar" data-mult="3.5" class="class-btn divine-border rounded-lg py-3 text-xs md:text-sm font-jp">Einherjar<br><span class="opacity-60">x3.5</span></button>
          </div>
        </div>
        <div class="md:col-span-2">
          <button type="submit" class="btn-primary w-full py-4 rounded-full font-jp tracking-widest text-sm">⚡ SEEK DIVINE PASSAGE ⚡</button>
        </div>
      </form>

      <div id="results" class="mt-10 grid gap-5"></div>
    </div>
  </section>

  <!-- ============ DEPARTURES BOARD ============ -->
  <section id="departures" class="relative py-24 px-6">
    <div class="max-w-6xl mx-auto">
      <div class="text-center mb-12 reveal">
        <p class="font-jp tracking-[.3em] text-sm mb-3" style="color:var(--teal)">LIVE BOARD ｜ 出発案内</p>
        <h2 class="font-display text-3xl md:text-5xl" style="color:var(--gold)">Departures From Asgard Terminal</h2>
        <p class="mt-3 text-sm font-jp" style="color:var(--text-dim)">Current Realm Time: <span id="live-clock" style="color:var(--gold)"></span></p>
      </div>
      <div class="reveal divine-border rounded-2xl overflow-hidden" style="background:var(--bg-panel)">
        <div class="overflow-x-auto hidden-scroll">
          <table class="w-full text-xs md:text-sm font-jp">
            <thead>
              <tr class="text-left" style="color:var(--gold); background:var(--bg-panel-2)">
                <th class="px-4 py-4">LINE</th>
                <th class="px-4 py-4">DESTINATION</th>
                <th class="px-4 py-4">SCHEDULED</th>
                <th class="px-4 py-4">PLATFORM</th>
                <th class="px-4 py-4">STATUS</th>
              </tr>
            </thead>
            <tbody id="departure-body"></tbody>
          </table>
        </div>
      </div>
    </div>
  </section>

  <!-- ============ DEITIES / GUARDIANS ============ -->
  <section id="deities" class="relative py-24 px-6" style="background:var(--bg-deep)">
    <div class="max-w-7xl mx-auto">
      <div class="text-center mb-14 reveal">
        <p class="font-jp tracking-[.3em] text-sm mb-3" style="color:var(--teal)">GUARDIANS ｜ 守護神</p>
        <h2 class="font-display text-3xl md:text-5xl" style="color:var(--gold)">Meet the Divine Guardians</h2>
        <p class="mt-4 max-w-xl mx-auto text-sm" style="color:var(--text-dim)">Click a card to reveal the deity's lore and station of dominion.</p>
      </div>
      <div id="deities-grid" class="grid sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-6"></div>
    </div>
  </section>

  <!-- ============ TESTIMONIALS ============ -->
  <section id="testimonials" class="relative py-24 px-6 overflow-hidden">
    <div class="max-w-4xl mx-auto text-center reveal">
      <p class="font-jp tracking-[.3em] text-sm mb-3" style="color:var(--teal)">VOICES OF TRAVELERS ｜ 旅人の声</p>
      <h2 class="font-display text-3xl md:text-5xl mb-14" style="color:var(--gold)">Tales From the Rails</h2>
    </div>
    <div class="relative max-w-3xl mx-auto reveal">
      <div class="overflow-hidden">
        <div id="testimonial-track" class="testimonial-track flex"></div>
      </div>
      <div class="flex justify-center gap-4 mt-8">
        <button id="t-prev" class="w-11 h-11 rounded-full divine-border font-display" style="color:var(--gold)">←</button>
        <button id="t-next" class="w-11 h-11 rounded-full divine-border font-display" style="color:var(--gold)">→</button>
      </div>
    </div>
  </section>

  <!-- ============ FAQ ============ -->
  <section id="faq" class="relative py-24 px-6" style="background:var(--bg-deep)">
    <div class="max-w-3xl mx-auto">
      <div class="text-center mb-12 reveal">
        <p class="font-jp tracking-[.3em] text-sm mb-3" style="color:var(--teal)">FAQ ｜ よくある質問</p>
        <h2 class="font-display text-3xl md:text-5xl" style="color:var(--gold)">Traveler's Questions</h2>
      </div>
      <div id="faq-list" class="space-y-4 reveal"></div>
    </div>
  </section>

  <!-- ============ NEWSLETTER / CTA ============ -->
  <section class="relative py-20 px-6">
    <div class="max-w-4xl mx-auto text-center divine-border rounded-2xl p-10 md:p-14 reveal" style="background:linear-gradient(135deg, var(--bg-panel), var(--bg-panel-2))">
      <h2 class="font-display text-2xl md:text-4xl mb-4" style="color:var(--gold)">Receive Omens & Offers</h2>
      <p class="text-sm mb-8" style="color:var(--text-dim)">Subscribe to our raven-post for fare drops, eclipse specials, and shrine festival routes.</p>
      <form id="newsletter-form" class="flex flex-col sm:flex-row gap-3 max-w-md mx-auto">
        <input type="email" required placeholder="your.spirit@realm.com" class="flex-1 px-4 py-3 rounded-full divine-border" style="background:var(--bg-panel-2)">
        <button class="btn-gold px-6 py-3 rounded-full font-jp text-sm">Subscribe</button>
      </form>
      <p id="newsletter-msg" class="text-xs mt-4" style="color:var(--teal)"></p>
    </div>
  </section>

  <!-- ============ FOOTER ============ -->
  <footer class="relative pt-16 pb-8 px-6 border-t" style="background:var(--bg-void); border-color:var(--border-glow)">
    <div class="max-w-7xl mx-auto grid md:grid-cols-4 gap-10 mb-12">
      <div>
        <div class="flex items-center gap-3 mb-4">
          <svg class="torii-mark w-8 h-8" viewBox="0 0 64 64" fill="none" stroke-width="3">
            <path d="M6 16 L58 16" stroke-linecap="round" />
            <path d="M4 22 L60 22" stroke-linecap="round" />
            <path d="M18 22 L15 58" stroke-linecap="round" />
            <path d="M46 22 L49 58" stroke-linecap="round" />
            <path d="M25 30 L39 30" stroke-linecap="round" />
          </svg>
          <p class="font-display" style="color:var(--gold)">AESIR × KAMI</p>
        </div>
        <p class="text-sm" style="color:var(--text-dim)">Uniting nine realms and the plain of high heaven, one sacred rail at a time.</p>
      </div>
      <div>
        <p class="font-jp text-sm tracking-widest mb-4" style="color:var(--gold)">NAVIGATE</p>
        <ul class="space-y-2 text-sm" style="color:var(--text-dim)">
          <li><a href="#lines" class="hover:underline">Divine Lines</a></li>
          <li><a href="#booking" class="hover:underline">Book Passage</a></li>
          <li><a href="#departures" class="hover:underline">Departures</a></li>
          <li><a href="#deities" class="hover:underline">Guardians</a></li>
        </ul>
      </div>
      <div>
        <p class="font-jp text-sm tracking-widest mb-4" style="color:var(--gold)">TERMINALS</p>
        <ul class="space-y-2 text-sm" style="color:var(--text-dim)">
          <li>Asgard Terminal</li>
          <li>Ise Grand Shrine Stop</li>
          <li>Yomi Underground</li>
          <li>Midgard Central</li>
        </ul>
      </div>
      <div>
        <p class="font-jp text-sm tracking-widest mb-4" style="color:var(--gold)">FOLLOW THE RAVENS</p>
        <div class="flex gap-3">
          <a href="#" class="w-10 h-10 rounded-full divine-border flex items-center justify-center">ᚱ</a>
          <a href="#" class="w-10 h-10 rounded-full divine-border flex items-center justify-center">神</a>
          <a href="#" class="w-10 h-10 rounded-full divine-border flex items-center justify-center">⛩</a>
        </div>
      </div>
    </div>
    <div class="marquee-wrap py-3 border-t border-b mb-6" style="border-color:var(--border-glow)">
      <div class="marquee-track font-jp text-xs" style="color:var(--text-dim)">
        ᚠᚢᚦᚨᚱᚲ ⛩ 安全な旅を ⛩ MAY ODIN'S RAVENS GUIDE YOUR PATH ⛩ 神鉄道へようこそ ⛩ THUNDER & TORII ⛩ ᛊᛏᛒᛖᛗᛚᛜ ⛩ AESIR KAMI RAILWAYS ⛩
      </div>
    </div>
    <p class="text-center text-xs" style="color:var(--text-dim)">© <span id="year"></span> Aesir × Kami Railways — A Mythical Transit Experience. Built for legends, mortals, and everything in between.</p>
  </footer>

  <button id="back-to-top" class="fixed bottom-6 right-6 w-12 h-12 rounded-full btn-gold flex items-center justify-center font-display text-lg z-40 opacity-0 pointer-events-none transition-opacity">↑</button>

  <!-- ============ MODAL ============ -->
  <div id="modal" class="fixed inset-0 modal-overlay z-[100] hidden items-center justify-center p-4">
    <div id="modal-content" class="divine-border rounded-2xl max-w-lg w-full p-8 relative" style="background:var(--bg-panel)"></div>
  </div>

  <script>
    /* ===================== DATA ===================== */
    const stations = [
      "Asgard Terminal", "Valhalla Junction", "Midgard Central", "Niflheim Depot", "Vanaheim Gardens",
      "Takamagahara Sky Station", "Ise Grand Shrine", "Izumo Gateway", "Yomi Underground", "Ryujin's Deep"
    ];

    const lines = [{
        id: "odin",
        name: "Odin Line",
        jp: "オーディン線",
        deity: "Odin",
        color: "#7c3aed",
        icon: "𐊃",
        desc: "The Wisdom Express — glides through realms of knowledge, guided by two ravens scouting the tracks ahead.",
        stations: ["Asgard Terminal", "Valhalla Junction", "Midgard Central"],
        speed: "320 km/h",
        freq: "Every 40 min"
      },
      {
        id: "amaterasu",
        name: "Amaterasu Line",
        jp: "天照線",
        deity: "Amaterasu",
        color: "#f2b400",
        icon: "☀",
        desc: "The Sunrise Line — first train to catch dawn's light across the Plain of High Heaven.",
        stations: ["Takamagahara Sky Station", "Ise Grand Shrine", "Izumo Gateway"],
        speed: "280 km/h",
        freq: "Every 30 min"
      },
      {
        id: "thor",
        name: "Thor Line",
        jp: "トール線",
        deity: "Thor",
        color: "#2563eb",
        icon: "⚡",
        desc: "Thunder Rapid — the fastest storm-powered service, propelled by Mjölnir-forged turbines.",
        stations: ["Valhalla Junction", "Midgard Central", "Niflheim Depot"],
        speed: "410 km/h",
        freq: "Every 25 min"
      },
      {
        id: "susanoo",
        name: "Susanoo Line",
        jp: "須佐之男線",
        deity: "Susanoo",
        color: "#0d9488",
        icon: "🌊",
        desc: "Storm Line — cuts fearlessly through Izumo's tempests and eight-headed serpent territory.",
        stations: ["Izumo Gateway", "Ryujin's Deep", "Yomi Underground"],
        speed: "300 km/h",
        freq: "Every 45 min"
      },
      {
        id: "freya",
        name: "Freya Line",
        jp: "フレイヤ線",
        deity: "Freya",
        color: "#db2777",
        icon: "💛",
        desc: "Love & Fortune Line — golden tears light every platform lantern along its route.",
        stations: ["Vanaheim Gardens", "Asgard Terminal", "Ise Grand Shrine"],
        speed: "260 km/h",
        freq: "Every 35 min"
      },
      {
        id: "tsukuyomi",
        name: "Tsukuyomi Line",
        jp: "月読線",
        deity: "Tsukuyomi",
        color: "#6366f1",
        icon: "🌙",
        desc: "Moonlight Line — the only night service running between the underworld and Midgard.",
        stations: ["Yomi Underground", "Niflheim Depot", "Midgard Central"],
        speed: "290 km/h",
        freq: "Every 50 min"
      },
    ];

    const deities = [{
        name: "Odin",
        jp: "オーディン",
        origin: "Norse",
        icon: "𐊃",
        line: "Odin Line",
        lore: "All-Father of Asgard, sacrificed an eye at Mimir's well for wisdom. Now watches every signal tower with his ravens Huginn & Muninn."
      },
      {
        name: "Thor",
        jp: "トール",
        origin: "Norse",
        icon: "⚡",
        line: "Thor Line",
        lore: "God of thunder, wields Mjölnir to power the storm turbines that drive the fastest rapid service in the network."
      },
      {
        name: "Freya",
        jp: "フレイヤ",
        origin: "Norse",
        icon: "💛",
        line: "Freya Line",
        lore: "Goddess of love and fortune, her golden tears light the lanterns of every platform on her line."
      },
      {
        name: "Loki",
        jp: "ロキ",
        origin: "Norse",
        icon: "🔥",
        line: "Special Charters",
        lore: "Trickster god who arranges 'mysterious' delayed departures and secret detour routes — ride at your own risk."
      },
      {
        name: "Heimdall",
        jp: "ヘイムダル",
        origin: "Norse",
        icon: "🎺",
        line: "Security & Gates",
        lore: "Guardian of the Bifröst, his horn Gjallarhorn sounds the final boarding call across all terminals."
      },
      {
        name: "Amaterasu",
        jp: "天照大神",
        origin: "Japanese",
        icon: "☀",
        line: "Amaterasu Line",
        lore: "Sun goddess whose light powers the solar rail network across Takamagahara, the Plain of High Heaven."
      },
      {
        name: "Susanoo",
        jp: "須佐之男命",
        origin: "Japanese",
        icon: "🌊",
        line: "Susanoo Line",
        lore: "Storm god who slew the eight-headed serpent Yamata no Orochi, now channels tempests into rail power."
      },
      {
        name: "Tsukuyomi",
        jp: "月読命",
        origin: "Japanese",
        icon: "🌙",
        line: "Tsukuyomi Line",
        lore: "Moon god, brother of Amaterasu, oversees the sole night line connecting Yomi to the mortal realm."
      },
      {
        name: "Inari",
        jp: "稲荷神",
        origin: "Japanese",
        icon: "🦊",
        line: "Freight & Fortune",
        lore: "Deity of rice, fox spirits, and prosperity — blesses every cargo car with bountiful harvests."
      },
      {
        name: "Raijin",
        jp: "雷神",
        origin: "Japanese",
        icon: "🥁",
        line: "Thor Line (Co-Guardian)",
        lore: "Thunder god who drums the storm clouds, working alongside Thor to keep the Rapid line electrified."
      },
    ];

    const testimonials = [{
        quote: "Rode the Odin Line at dawn — the ravens really do fly alongside the windows. Wisest ticket I ever bought.",
        who: "A Wandering Skald, Midgard"
      },
      {
        quote: "The Amaterasu Line's sunrise view over Ise Shrine is worth the pilgrimage alone.",
        who: "Shrine Maiden, Ise Grand Shrine"
      },
      {
        quote: "Thor Line got me to Niflheim before the storm even finished brewing. Terrifyingly fast.",
        who: "Merchant of Vanaheim"
      },
      {
        quote: "Loki 'accidentally' rerouted my train through a hidden valley. Best detour of my life.",
        who: "Anonymous Traveler"
      },
      {
        quote: "Tsukuyomi Line at midnight is eerie, elegant, and always on time. Highly recommend the Kami class.",
        who: "Yōkai Commuter, Yomi Underground"
      },
    ];

    const faqs = [{
        q: "Can mortals really board these trains?",
        a: "Absolutely. Mortal Class tickets are available on every line — just don't stare directly at Amaterasu's carriage windows during sunrise."
      },
      {
        q: "What happens if Loki is my conductor?",
        a: "Expect delightful chaos: surprise detours, riddle contests for free upgrades, and occasionally your seat turning into a goat. All in good fun."
      },
      {
        q: "Are pets and familiars allowed?",
        a: "Yes — ravens, foxes (kitsune), cats, and even small dragons travel free in the Familiar Car on all Kami and Einherjar class tickets."
      },
      {
        q: "How do I get refunded for a cursed journey?",
        a: "Visit any station's Shrine Desk within 7 sunrises. Curses lifted, fares refunded, no questions asked (usually)."
      },
      {
        q: "Is there Wi-Fi on the Bifröst bridge sections?",
        a: "Heimdall personally maintains the signal — connectivity is stronger there than anywhere else in the nine realms."
      },
    ];

    /* ===================== THEME TOGGLE ===================== */
    const html = document.documentElement;
    const themeToggle = document.getElementById('theme-toggle');
    const toggleDot = document.getElementById('toggle-dot');
    themeToggle.addEventListener('click', () => {
      const isNight = html.getAttribute('data-theme') === 'night';
      html.setAttribute('data-theme', isNight ? 'day' : 'night');
      toggleDot.textContent = isNight ? '☀' : '☾';
    });

    /* ===================== MOBILE MENU ===================== */
    const hamburger = document.getElementById('hamburger');
    const mobileMenu = document.getElementById('mobile-menu');
    hamburger.addEventListener('click', () => {
      mobileMenu.classList.toggle('hidden');
      mobileMenu.classList.toggle('flex');
    });
    document.querySelectorAll('#mobile-menu a').forEach(a => a.addEventListener('click', () => {
      mobileMenu.classList.add('hidden');
      mobileMenu.classList.remove('flex');
    }));

    /* ===================== NAV ACTIVE LINK ===================== */
    const sections = document.querySelectorAll('section[id]');
    const navLinks = document.querySelectorAll('.nav-link');
    window.addEventListener('scroll', () => {
      let current = '';
      sections.forEach(sec => {
        if (window.scrollY >= sec.offsetTop - 150) current = sec.getAttribute('id');
      });
      navLinks.forEach(link => {
        link.classList.toggle('active', link.getAttribute('href') === '#' + current);
      });
      const btt = document.getElementById('back-to-top');
      if (window.scrollY > 600) {
        btt.style.opacity = 1;
        btt.style.pointerEvents = 'auto';
      } else {
        btt.style.opacity = 0;
        btt.style.pointerEvents = 'none';
      }
    });
    document.getElementById('back-to-top').addEventListener('click', () => window.scrollTo({
      top: 0,
      behavior: 'smooth'
    }));

    /* ===================== POPULATE STATIONS ===================== */
    const fromSel = document.getElementById('from-station');
    const toSel = document.getElementById('to-station');
    stations.forEach(s => {
      fromSel.innerHTML += `<option value="${s}">${s}</option>`;
      toSel.innerHTML += `<option value="${s}">${s}</option>`;
    });
    toSel.selectedIndex = 1;
    document.getElementById('travel-date').min = new Date().toISOString().split('T')[0];
    document.getElementById('travel-date').value = new Date().toISOString().split('T')[0];

    /* ===================== CLASS SELECT ===================== */
    let selectedClass = {
      name: "Mortal",
      mult: 1
    };
    document.querySelectorAll('.class-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        document.querySelectorAll('.class-btn').forEach(b => {
          b.style.borderColor = '';
          b.style.background = '';
        });
        btn.style.borderColor = 'var(--gold)';
        btn.style.background = 'rgba(212,175,55,.12)';
        selectedClass = {
          name: btn.dataset.class,
          mult: parseFloat(btn.dataset.mult)
        };
      });
    });
    document.querySelector('.class-btn[data-class="Mortal"]').click();

    /* ===================== RENDER LINES ===================== */
    const linesGrid = document.getElementById('lines-grid');
    lines.forEach(line => {
      const card = document.createElement('div');
      card.className = 'line-card rounded-2xl p-6 cursor-pointer reveal';
      card.style.setProperty('--line-color', line.color);
      card.innerHTML = `
    <div class="flex items-center justify-between mb-4">
      <span class="text-3xl">${line.icon}</span>
      <span class="line-chip text-[10px] px-3 py-1 rounded-full font-jp tracking-widest" style="color:#fff">${line.deity.toUpperCase()}</span>
    </div>
    <h3 class="font-display text-xl mb-1" style="color:${line.color}">${line.name}</h3>
    <p class="font-jp text-xs mb-3" style="color:var(--text-dim)">${line.jp}</p>
    <p class="text-sm mb-4" style="color:var(--text-dim)">${line.desc}</p>
    <div class="flex justify-between text-xs font-jp" style="color:var(--text-dim)">
      <span>🚄 ${line.speed}</span>
      <span>⏱ ${line.freq}</span>
    </div>
  `;
      card.addEventListener('click', () => openLineModal(line));
      linesGrid.appendChild(card);
    });

    function openLineModal(line) {
      const modal = document.getElementById('modal');
      const content = document.getElementById('modal-content');
      content.innerHTML = `
    <button onclick="closeModal()" class="absolute top-4 right-5 text-xl" style="color:var(--gold)">✕</button>
    <div class="text-4xl mb-3">${line.icon}</div>
    <h3 class="font-display text-2xl mb-1" style="color:${line.color}">${line.name}</h3>
    <p class="font-jp text-sm mb-4" style="color:var(--text-dim)">${line.jp} · Guardian: ${line.deity}</p>
    <p class="text-sm mb-5" style="color:var(--text-dim)">${line.desc}</p>
    <p class="font-jp text-xs tracking-widest mb-2" style="color:var(--gold)">ROUTE STATIONS</p>
    <div class="flex flex-wrap gap-2 mb-5">
      ${line.stations.map(s => `<span class="text-xs px-3 py-1 rounded-full divine-border">${s}</span>`).join('')}
    </div>
    <div class="flex justify-between text-sm font-jp">
      <span style="color:var(--text-dim)">Top Speed: <b style="color:var(--gold)">${line.speed}</b></span>
      <span style="color:var(--text-dim)">Frequency: <b style="color:var(--gold)">${line.freq}</b></span>
    </div>
  `;
      modal.classList.remove('hidden');
      modal.classList.add('flex');
    }

    function closeModal() {
      const modal = document.getElementById('modal');
      modal.classList.add('hidden');
      modal.classList.remove('flex');
    }
    document.getElementById('modal').addEventListener('click', e => {
      if (e.target.id === 'modal') closeModal();
    });

    /* ===================== BOOKING SEARCH ===================== */
    document.getElementById('booking-form').addEventListener('submit', e => {
      e.preventDefault();
      const from = fromSel.value,
        to = toSel.value,
        pax = parseInt(document.getElementById('passengers').value);
      if (from === to) {
        alert('Even the gods cannot travel to the same realm they departed from. Please choose different stations.');
        return;
      }

      const resultsDiv = document.getElementById('results');
      resultsDiv.innerHTML = `<p class="text-center font-jp text-sm animate-pulse" style="color:var(--gold)">Consulting the Norns for available trains...</p>`;

      setTimeout(() => {
        resultsDiv.innerHTML = '';
        const shuffled = [...lines].sort(() => .5 - Math.random()).slice(0, 3);
        shuffled.forEach((line, i) => {
          const basePrice = 45 + Math.random() * 80;
          const price = (basePrice * selectedClass.mult * pax).toFixed(2);
          const depHour = 6 + i * 4 + Math.floor(Math.random() * 2);
          const dur = 1 + Math.floor(Math.random() * 3);
          const arrHour = (depHour + dur) % 24;
          const card = document.createElement('div');
          card.className = 'reveal in divine-border rounded-xl p-5 md:p-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4';
          card.style.background = 'var(--bg-panel)';
          card.innerHTML = `
        <div class="flex items-center gap-4">
          <span class="text-3xl">${line.icon}</span>
          <div>
            <p class="font-display" style="color:${line.color}">${line.name}</p>
            <p class="text-xs font-jp" style="color:var(--text-dim)">${from} → ${to}</p>
          </div>
        </div>
        <div class="flex gap-6 text-sm font-jp" style="color:var(--text-dim)">
          <div><p class="text-xs" style="color:var(--gold)">DEPART</p>${String(depHour).padStart(2,'0')}:00</div>
          <div><p class="text-xs" style="color:var(--gold)">ARRIVE</p>${String(arrHour).padStart(2,'0')}:00</div>
          <div><p class="text-xs" style="color:var(--gold)">CLASS</p>${selectedClass.name}</div>
        </div>
        <div class="flex items-center gap-4">
          <p class="font-display text-xl" style="color:var(--gold)">$${price}</p>
          <button class="book-btn btn-primary px-5 py-2 rounded-full text-xs font-jp">SELECT</button>
        </div>
      `;
          card.querySelector('.book-btn').addEventListener('click', () => showConfirmation(line, from, to, depHour, arrHour, price));
          resultsDiv.appendChild(card);
        });
      }, 900);
    });

    function showConfirmation(line, from, to, depHour, arrHour, price) {
      const modal = document.getElementById('modal');
      const content = document.getElementById('modal-content');
      const code = 'AK-' + Math.random().toString(36).substring(2, 8).toUpperCase();
      content.innerHTML = `
    <button onclick="closeModal()" class="absolute top-4 right-5 text-xl" style="color:var(--gold)">✕</button>
    <div class="text-center">
      <p class="text-4xl mb-3">${line.icon}</p>
      <h3 class="font-display text-2xl mb-1" style="color:var(--gold)">Journey Blessed!</h3>
      <p class="font-jp text-xs mb-6" style="color:var(--text-dim)">祝福された旅 — ${line.deity} watches over your path</p>
      <div class="text-left divine-border rounded-xl p-5 space-y-2 text-sm" style="background:var(--bg-panel-2)">
        <p><span style="color:var(--gold)">Confirmation:</span> ${code}</p>
        <p><span style="color:var(--gold)">Line:</span> ${line.name}</p>
        <p><span style="color:var(--gold)">Route:</span> ${from} → ${to}</p>
        <p><span style="color:var(--gold)">Time:</span> ${String(depHour).padStart(2,'0')}:00 – ${String(arrHour).padStart(2,'0')}:00</p>
        <p><span style="color:var(--gold)">Class:</span> ${selectedClass.name}</p>
        <p><span style="color:var(--gold)">Total Fare:</span> $${price}</p>
      </div>
      <p class="text-xs mt-5" style="color:var(--text-dim)">May the ravens guide you safely to your platform.</p>
    </div>
  `;
      modal.classList.remove('hidden');
      modal.classList.add('flex');
    }

    /* ===================== LIVE CLOCK ===================== */
    function updateClock() {
      const now = new Date();
      document.getElementById('live-clock').textContent = now.toLocaleTimeString();
    }
    setInterval(updateClock, 1000);
    updateClock();

    /* ===================== DEPARTURE BOARD ===================== */
    const statuses = ['On Time', 'Boarding', 'Delayed', 'Departed'];

    function randomTime() {
      const h = Math.floor(Math.random() * 24),
        m = Math.floor(Math.random() * 60);
      return String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0');
    }
    let boardData = [];
    for (let i = 0; i < 8; i++) {
      const line = lines[i % lines.length];
      boardData.push({
        line,
        dest: line.stations[line.stations.length - 1],
        time: randomTime(),
        platform: 1 + Math.floor(Math.random() * 9),
        status: statuses[Math.floor(Math.random() * statuses.length)]
      });
    }

    function statusColor(s) {
      return {
        'On Time': '#2f9c95',
        'Boarding': '#d4af37',
        'Delayed': '#e0483d',
        'Departed': '#6d5bd0'
      } [s];
    }

    function renderBoard() {
      const body = document.getElementById('departure-body');
      body.innerHTML = boardData.map(row => `
    <tr class="board-row border-t" style="border-color:var(--border-glow)">
      <td class="px-4 py-4"><span style="color:${row.line.color}">${row.line.icon} ${row.line.name}</span></td>
      <td class="px-4 py-4">${row.dest}</td>
      <td class="px-4 py-4 board-flicker">${row.time}</td>
      <td class="px-4 py-4">${row.platform}</td>
      <td class="px-4 py-4"><span class="flip-status px-3 py-1 rounded-full text-[10px]" style="background:${statusColor(row.status)}22; color:${statusColor(row.status)}; border:1px solid ${statusColor(row.status)}">${row.status}</span></td>
    </tr>
  `).join('');
    }
    renderBoard();
    setInterval(() => {
      const idx = Math.floor(Math.random() * boardData.length);
      boardData[idx].status = statuses[Math.floor(Math.random() * statuses.length)];
      renderBoard();
    }, 4000);

    /* ===================== DEITIES FLIP CARDS ===================== */
    const deitiesGrid = document.getElementById('deities-grid');
    deities.forEach(d => {
      const card = document.createElement('div');
      card.className = 'flip-card reveal';
      card.innerHTML = `
    <div class="flip-inner">
      <div class="flip-face flip-front divine-border" style="background:var(--bg-panel)">
        <p class="text-5xl mb-3">${d.icon}</p>
        <p class="font-display text-lg" style="color:var(--gold)">${d.name}</p>
        <p class="font-jp text-xs mb-2" style="color:var(--text-dim)">${d.jp}</p>
        <span class="text-[10px] px-3 py-1 rounded-full divine-border" style="color:var(--text-dim)">${d.origin}</span>
        <p class="text-[10px] mt-3" style="color:var(--text-dim)">Click to reveal lore ↴</p>
      </div>
      <div class="flip-face flip-back divine-border" style="background:var(--bg-panel-2)">
        <p class="font-display text-sm mb-2" style="color:var(--gold)">${d.name} — ${d.line}</p>
        <p class="text-xs leading-relaxed" style="color:var(--text-dim)">${d.lore}</p>
      </div>
    </div>
  `;
      card.addEventListener('click', () => card.classList.toggle('flipped'));
      deitiesGrid.appendChild(card);
    });

    /* ===================== TESTIMONIALS CAROUSEL ===================== */
    const track = document.getElementById('testimonial-track');
    testimonials.forEach(t => {
      const slide = document.createElement('div');
      slide.className = 'w-full flex-shrink-0 px-4';
      slide.innerHTML = `
    <div class="divine-border rounded-2xl p-8 text-center" style="background:var(--bg-panel)">
      <p class="text-4xl mb-4" style="color:var(--gold)">❝</p>
      <p class="text-base md:text-lg mb-6 italic" style="color:var(--text-main)">${t.quote}</p>
      <p class="font-jp text-sm" style="color:var(--gold)">— ${t.who}</p>
    </div>
  `;
      track.appendChild(slide);
    });
    let tIndex = 0;

    function updateTestimonial() {
      track.style.transform = `translateX(-${tIndex*100}%)`;
    }
    document.getElementById('t-next').addEventListener('click', () => {
      tIndex = (tIndex + 1) % testimonials.length;
      updateTestimonial();
    });
    document.getElementById('t-prev').addEventListener('click', () => {
      tIndex = (tIndex - 1 + testimonials.length) % testimonials.length;
      updateTestimonial();
    });
    setInterval(() => {
      tIndex = (tIndex + 1) % testimonials.length;
      updateTestimonial();
    }, 6000);

    /* ===================== FAQ ACCORDION ===================== */
    const faqList = document.getElementById('faq-list');
    faqs.forEach((f, i) => {
      const item = document.createElement('div');
      item.className = 'divine-border rounded-xl overflow-hidden';
      item.style.background = 'var(--bg-panel)';
      item.innerHTML = `
    <button class="faq-toggle w-full text-left px-5 py-4 flex justify-between items-center font-jp text-sm" style="color:var(--gold)">
      <span>${f.q}</span><span class="faq-icon transition-transform">+</span>
    </button>
    <div class="accordion-content px-5">
      <p class="pb-4 text-sm" style="color:var(--text-dim)">${f.a}</p>
    </div>
  `;
      const btn = item.querySelector('.faq-toggle');
      const content = item.querySelector('.accordion-content');
      const icon = item.querySelector('.faq-icon');
      btn.addEventListener('click', () => {
        const isOpen = content.style.maxHeight && content.style.maxHeight !== '0px';
        document.querySelectorAll('.accordion-content').forEach(c => c.style.maxHeight = '0px');
        document.querySelectorAll('.faq-icon').forEach(ic => ic.textContent = '+');
        if (!isOpen) {
          content.style.maxHeight = content.scrollHeight + 'px';
          icon.textContent = '−';
        }
      });
      faqList.appendChild(item);
    });

    /* ===================== NEWSLETTER ===================== */
    document.getElementById('newsletter-form').addEventListener('submit', e => {
      e.preventDefault();
      document.getElementById('newsletter-msg').textContent = '✦ The ravens have accepted your offering. Watch your realm-mail for omens.';
      e.target.reset();
    });

    /* ===================== STAT COUNTERS ===================== */
    const counters = document.querySelectorAll('[data-count]');
    let countersStarted = false;

    function animateCounters() {
      counters.forEach(c => {
        const target = parseInt(c.dataset.count);
        let cur = 0;
        const step = Math.max(1, Math.ceil(target / 60));
        const timer = setInterval(() => {
          cur += step;
          if (cur >= target) {
            cur = target;
            clearInterval(timer);
          }
          c.textContent = cur;
        }, 25);
      });
    }

    /* ===================== SCROLL REVEAL ===================== */
    const revealEls = document.querySelectorAll('.reveal');
    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.add('in');
          if (entry.target.closest('#home') && !countersStarted) {
            countersStarted = true;
            animateCounters();
          }
        }
      });
    }, {
      threshold: 0.15
    });
    revealEls.forEach(el => observer.observe(el));
    // trigger hero counters immediately since hero has no .reveal wrapper on stats container
    window.addEventListener('load', () => {
      if (!countersStarted) {
        countersStarted = true;
        animateCounters();
      }
    });

    /* ===================== PARTICLE CANVAS (sakura + runes) ===================== */
    const canvas = document.getElementById('particle-canvas');
    const ctx = canvas.getContext('2d');
    let W, H;

    function resize() {
      W = canvas.width = window.innerWidth;
      H = canvas.height = window.innerHeight;
    }
    resize();
    window.addEventListener('resize', resize);

    const runeChars = ['ᚠ', 'ᚢ', 'ᚦ', 'ᚨ', 'ᚱ', 'ᚲ', 'ᚷ', 'ᚹ', 'ᚺ', 'ᚾ', 'ᛁ', 'ᛃ'];
    const particles = [];
    const PARTICLE_COUNT = 45;
    for (let i = 0; i < PARTICLE_COUNT; i++) {
      const isRune = Math.random() > 0.55;
      particles.push({
        x: Math.random() * W,
        y: Math.random() * H,
        size: isRune ? 10 + Math.random() * 10 : 4 + Math.random() * 6,
        speedY: 0.3 + Math.random() * 0.7,
        speedX: (Math.random() - 0.5) * 0.6,
        isRune,
        char: runeChars[Math.floor(Math.random() * runeChars.length)],
        opacity: 0.2 + Math.random() * 0.5,
        sway: Math.random() * Math.PI * 2
      });
    }

    function draw() {
      ctx.clearRect(0, 0, W, H);
      particles.forEach(p => {
        p.y += p.speedY;
        p.sway += 0.01;
        p.x += p.speedX + Math.sin(p.sway) * 0.3;
        if (p.y > H + 20) {
          p.y = -20;
          p.x = Math.random() * W;
        }
        if (p.x > W + 20) p.x = -20;
        if (p.x < -20) p.x = W + 20;

        ctx.globalAlpha = p.opacity;
        if (p.isRune) {
          ctx.fillStyle = getComputedStyle(html).getPropertyValue('--gold').trim();
          ctx.font = `${p.size}px serif`;
          ctx.fillText(p.char, p.x, p.y);
        } else {
          ctx.fillStyle = getComputedStyle(html).getPropertyValue('--torii-red-2').trim();
          ctx.beginPath();
          ctx.ellipse(p.x, p.y, p.size, p.size * 0.6, p.sway, 0, Math.PI * 2);
          ctx.fill();
        }
      });
      ctx.globalAlpha = 1;
      requestAnimationFrame(draw);
    }
    draw();

    /* ===================== MISC ===================== */
    document.getElementById('year').textContent = new Date().getFullYear();

    /* ESC closes modal */
    window.addEventListener('keydown', e => {
      if (e.key === 'Escape') closeModal();
    });
  </script>
</body>

</html>