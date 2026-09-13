<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>YGGDRAIL — 神鉄 | The Divine Railway</title>
  <meta name="description" content="YGGDRAIL — where the Nine Realms meet the Eight Million Kami. Book passage on the divine railway between Norse and Japanese mythic lands." />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;500;600;700;800;900&family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;0,700;1,400;1,500;1,600&family=Noto+Serif+JP:wght@300;400;500;600;700;900&family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
  <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
  <style>
    :root {
      --ink: #070605;
      --ink-2: #100e0c;
      --crimson: #9b1b1b;
      --vermillion: #c23b22;
      --gold: #c9a84c;
      --gold-bright: #e8d5a3;
      --ice: #9ec5d4;
      --sakura: #e8b4b8;
      --parchment: #f3ead8;
      --forest: #10180f;
    }

    * {
      box-sizing: border-box;
    }

    html {
      scroll-behavior: smooth;
    }

    body {
      margin: 0;
      background: var(--ink);
      color: var(--parchment);
      font-family: 'Outfit', system-ui, sans-serif;
      overflow-x: hidden;
    }

    h1,
    h2,
    h3,
    h4,
    .font-cinzel {
      font-family: 'Cinzel', serif;
    }

    .font-jp {
      font-family: 'Noto Serif JP', serif;
    }

    .font-corm {
      font-family: 'Cormorant Garamond', serif;
    }

    ::selection {
      background: var(--crimson);
      color: var(--gold-bright);
    }

    ::-webkit-scrollbar {
      width: 8px;
    }

    ::-webkit-scrollbar-track {
      background: #0b0a09;
    }

    ::-webkit-scrollbar-thumb {
      background: linear-gradient(#7a1515, #c9a84c);
      border-radius: 8px;
    }

    #particles {
      position: fixed;
      inset: 0;
      z-index: 2;
      pointer-events: none;
    }

    .seigaiha {
      background-image: url("data:image/svg+xml,%3Csvg width='60' height='30' viewBox='0 0 60 30' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M0 30c10 0 15-10 30-10S50 30 60 30M0 15c10 0 15-10 30-10S50 15 60 15M0 0c10 0 15-10 30-10S50 0 60 0' fill='none' stroke='%23c9a84c' stroke-opacity='0.08' stroke-width='1'/%3E%3C/svg%3E");
    }

    .asano {
      background-image: radial-gradient(circle at 1px 1px, rgba(201, 168, 76, .12) 1px, transparent 0);
      background-size: 22px 22px;
    }

    .gold-line {
      height: 1px;
      background: linear-gradient(90deg, transparent, var(--gold), transparent);
    }

    .gold-border {
      border: 1px solid rgba(201, 168, 76, .35);
      box-shadow: inset 0 0 0 1px rgba(201, 168, 76, .08), 0 20px 50px rgba(0, 0, 0, .45);
    }

    .torii-frame {
      position: relative;
    }

    .torii-frame::before,
    .torii-frame::after {
      content: "";
      position: absolute;
      height: 8px;
      left: -6px;
      right: -6px;
      background: linear-gradient(90deg, #7a1515, #c23b22, #7a1515);
      border-radius: 2px;
    }

    .torii-frame::before {
      top: -6px;
    }

    .torii-frame::after {
      bottom: -6px;
    }

    .btn-gold {
      background: linear-gradient(180deg, #e8d5a3 0%, #c9a84c 45%, #8c6d1f 100%);
      color: #1a1204;
      font-family: 'Cinzel', serif;
      letter-spacing: .16em;
      font-weight: 700;
      transition: transform .25s ease, box-shadow .25s ease, filter .25s ease;
      box-shadow: 0 8px 24px rgba(201, 168, 76, .25);
    }

    .btn-gold:hover {
      transform: translateY(-2px);
      filter: brightness(1.08);
      box-shadow: 0 14px 32px rgba(201, 168, 76, .4);
    }

    .btn-ghost {
      border: 1px solid rgba(201, 168, 76, .5);
      color: var(--gold-bright);
      letter-spacing: .14em;
      font-family: 'Cinzel', serif;
      transition: background .25s ease, color .25s ease;
    }

    .btn-ghost:hover {
      background: rgba(201, 168, 76, .12);
    }

    .reveal {
      opacity: 0;
      transform: translateY(28px);
      transition: opacity .8s ease, transform .8s ease;
    }

    .reveal.in {
      opacity: 1;
      transform: none;
    }

    .nav-scrolled {
      background: rgba(7, 6, 5, .82);
      backdrop-filter: blur(18px);
      border-bottom: 1px solid rgba(201, 168, 76, .18);
    }

    .hero-vignette {
      background:
        linear-gradient(180deg, rgba(7, 6, 5, .35) 0%, rgba(7, 6, 5, .2) 30%, rgba(7, 6, 5, .55) 70%, #070605 100%),
        radial-gradient(ellipse at 50% 80%, rgba(155, 27, 27, .25), transparent 55%);
    }

    .vertical-jp {
      writing-mode: vertical-rl;
      text-orientation: mixed;
      letter-spacing: .45em;
    }

    .departures {
      font-variant-numeric: tabular-nums;
    }

    .board-row {
      border-bottom: 1px solid rgba(201, 168, 76, .12);
    }

    .board-row:hover {
      background: rgba(201, 168, 76, .06);
    }

    .seat {
      width: 36px;
      height: 36px;
      border-radius: 8px 8px 4px 4px;
      border: 1px solid rgba(201, 168, 76, .35);
      background: rgba(243, 234, 216, .08);
      color: var(--parchment);
      font-size: 10px;
      cursor: pointer;
      transition: .2s ease;
    }

    .seat:hover:not(.taken):not(.selected) {
      background: rgba(201, 168, 76, .25);
      transform: translateY(-2px);
    }

    .seat.taken {
      background: #2a1a1a;
      color: #6b5c5c;
      cursor: not-allowed;
      border-color: #3a2a2a;
    }

    .seat.selected {
      background: linear-gradient(180deg, #e8d5a3, #c9a84c);
      color: #1a1204;
      border-color: #e8d5a3;
      box-shadow: 0 0 12px rgba(201, 168, 76, .55);
    }

    .aisle {
      width: 18px;
    }

    .ticket {
      background:
        linear-gradient(180deg, rgba(201, 168, 76, .08), transparent 30%),
        repeating-linear-gradient(0deg, transparent, transparent 27px, rgba(201, 168, 76, .05) 28px),
        #16110c;
      background-size: auto, auto, auto;
    }

    .ticket-notch {
      position: relative;
    }

    .ticket-notch::before,
    .ticket-notch::after {
      content: "";
      position: absolute;
      width: 28px;
      height: 28px;
      background: #070605;
      border-radius: 50%;
      top: 50%;
      transform: translateY(-50%);
    }

    .ticket-notch::before {
      left: -14px;
    }

    .ticket-notch::after {
      right: -14px;
    }

    .modal-bg {
      background: rgba(4, 3, 2, .82);
      backdrop-filter: blur(10px);
    }

    input,
    select,
    textarea {
      background: rgba(243, 234, 216, .06);
      border: 1px solid rgba(201, 168, 76, .28);
      color: var(--parchment);
      outline: none;
      transition: border .2s ease, box-shadow .2s ease;
    }

    input:focus,
    select:focus,
    textarea:focus {
      border-color: var(--gold);
      box-shadow: 0 0 0 3px rgba(201, 168, 76, .15);
    }

    option {
      background: #1a1510;
      color: #f3ead8;
    }

    input[type="date"]::-webkit-calendar-picker-indicator {
      filter: invert(.8) sepia(1) saturate(3) hue-rotate(5deg);
    }

    #preloader {
      position: fixed;
      inset: 0;
      z-index: 100;
      background: #070605;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: opacity .8s ease, visibility .8s ease;
    }

    #preloader.gone {
      opacity: 0;
      visibility: hidden;
    }

    .rune-spin {
      animation: runeSpin 8s linear infinite;
    }

    @keyframes runeSpin {
      to {
        transform: rotate(360deg);
      }
    }

    @keyframes pulseGold {

      0%,
      100% {
        opacity: .45;
      }

      50% {
        opacity: 1;
      }
    }

    @keyframes floatY {

      0%,
      100% {
        transform: translateY(0);
      }

      50% {
        transform: translateY(-10px);
      }
    }

    @keyframes trainDash {
      to {
        stroke-dashoffset: 0;
      }
    }

    @keyframes shimmer {
      0% {
        background-position: -200% 0;
      }

      100% {
        background-position: 200% 0;
      }
    }

    .shimmer-text {
      background: linear-gradient(90deg, #c9a84c 0%, #fff4d0 50%, #c9a84c 100%);
      background-size: 200% auto;
      -webkit-background-clip: text;
      background-clip: text;
      color: transparent;
      animation: shimmer 5s linear infinite;
    }

    .map-node {
      cursor: pointer;
      transition: transform .2s ease;
    }

    .map-node:hover {
      transform: scale(1.15);
    }

    .toast {
      position: fixed;
      bottom: 24px;
      right: 24px;
      z-index: 80;
      background: #16110c;
      border: 1px solid rgba(201, 168, 76, .4);
      color: var(--parchment);
      padding: 14px 18px;
      transform: translateY(120%);
      opacity: 0;
      transition: .4s ease;
      max-width: 320px;
    }

    .toast.show {
      transform: none;
      opacity: 1;
    }

    .class-card:hover .class-img {
      transform: scale(1.08);
    }

    .class-img {
      transition: transform 1.2s ease;
    }

    .route-card {
      transition: transform .35s ease, box-shadow .35s ease;
    }

    .route-card:hover {
      transform: translateY(-8px);
      box-shadow: 0 24px 50px rgba(155, 27, 27, .25);
    }

    #mobileMenu {
      transform: translateX(100%);
      transition: transform .4s ease;
    }

    #mobileMenu.open {
      transform: none;
    }

    .qr-fake {
      background-image:
        linear-gradient(#c9a84c 50%, transparent 50%),
        linear-gradient(90deg, #c9a84c 50%, transparent 50%);
      background-size: 6px 6px, 6px 6px;
      opacity: .85;
      filter: contrast(1.4);
    }

    @media print {
      body * {
        visibility: hidden;
      }

      #ticketPrint,
      #ticketPrint * {
        visibility: visible;
      }

      #ticketPrint {
        position: absolute;
        left: 0;
        top: 0;
      }
    }
  </style>
</head>

<body>
  <div id="preloader">
    <div class="text-center">
      <svg class="rune-spin mx-auto mb-6" width="92" height="92" viewBox="0 0 92 92" fill="none">
        <circle cx="46" cy="46" r="42" stroke="#c9a84c" stroke-opacity=".25" stroke-width="1" />
        <circle cx="46" cy="46" r="34" stroke="#9b1b1b" stroke-opacity=".6" stroke-width="1" stroke-dasharray="8 6" />
        <path d="M46 16 L50 34 L68 30 L54 44 L70 56 L50 52 L46 76 L42 52 L22 56 L38 44 L24 30 L42 34 Z" fill="#c9a84c" fill-opacity=".9" />
      </svg>
      <div class="font-cinzel tracking-[.5em] text-sm text-[#c9a84c]">YGGDRAIL</div>
      <div class="font-jp text-xs text-[#e8b4b8] mt-2 tracking-[.4em]">神々の鉄路</div>
      <div class="w-48 h-px mx-auto mt-6 overflow-hidden bg-[#2a2214]">
        <div id="loadBar" class="h-full bg-gradient-to-r from-[#9b1b1b] to-[#c9a84c]" style="width:0%;transition:width .3s"></div>
      </div>
    </div>
  </div>

  <canvas id="particles"></canvas>

  <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 bg-[#c9a84c] text-black px-3 py-2 z-50">Skip to content</a>

  <nav id="nav" class="fixed top-0 left-0 right-0 z-40 transition-all duration-300">
    <div class="max-w-7xl mx-auto px-5 md:px-8 flex items-center justify-between h-20">
      <a href="#top" class="flex items-center gap-3 group">
        <svg width="34" height="34" viewBox="0 0 92 92" fill="none" aria-hidden="true">
          <circle cx="46" cy="46" r="42" stroke="#c9a84c" stroke-opacity=".4" stroke-width="2" />
          <path d="M46 16 L50 34 L68 30 L54 44 L70 56 L50 52 L46 76 L42 52 L22 56 L38 44 L24 30 L42 34 Z" fill="#c9a84c" />
        </svg>
        <div>
          <div class="font-cinzel tracking-[.35em] text-sm text-[#e8d5a3] leading-none">YGGDRAIL</div>
          <div class="font-jp text-[10px] text-[#c23b22] tracking-[.28em] mt-1">神鉄</div>
        </div>
      </a>
      <div class="hidden lg:flex items-center gap-8 text-[11px] tracking-[.22em] uppercase font-cinzel text-[#e8d5a3]/80">
        <a href="#routes" class="hover:text-[#c9a84c]">Routes</a>
        <a href="#destinations" class="hover:text-[#c9a84c]">Realms</a>
        <a href="#fleet" class="hover:text-[#c9a84c]">Fleet</a>
        <a href="#classes" class="hover:text-[#c9a84c]">Classes</a>
        <a href="#chronicle" class="hover:text-[#c9a84c]">Chronicle</a>
        <a href="#passages" class="hover:text-[#c9a84c]">My Passages</a>
      </div>
      <div class="flex items-center gap-3">
        <button id="langBtn" class="hidden md:inline-flex text-[11px] tracking-[.2em] font-cinzel text-[#c9a84c] border border-[#c9a84c]/30 px-3 py-1.5 hover:bg-[#c9a84c]/10" aria-label="Toggle language">EN · 日本語</button>
        <a href="#book" class="btn-gold hidden sm:inline-flex text-[11px] px-5 py-2.5">Book Passage</a>
        <button id="menuBtn" class="lg:hidden w-10 h-10 flex flex-col items-center justify-center gap-1.5" aria-label="Open menu">
          <span class="block w-6 h-px bg-[#e8d5a3]"></span>
          <span class="block w-6 h-px bg-[#e8d5a3]"></span>
          <span class="block w-4 h-px bg-[#c9a84c]"></span>
        </button>
      </div>
    </div>
  </nav>

  <div id="mobileMenu" class="fixed inset-y-0 right-0 w-80 max-w-[88vw] z-50 bg-[#100e0c] border-l border-[#c9a84c]/20 p-8">
    <button id="closeMenu" class="absolute top-6 right-6 text-[#e8d5a3]" aria-label="Close menu">✕</button>
    <div class="font-cinzel tracking-[.4em] text-[#c9a84c] mb-10">YGGDRAIL</div>
    <div class="flex flex-col gap-5 font-cinzel tracking-[.2em] text-sm text-[#f3ead8]">
      <a href="#routes" class="menu-link">Routes</a>
      <a href="#destinations" class="menu-link">Realms</a>
      <a href="#fleet" class="menu-link">Fleet</a>
      <a href="#classes" class="menu-link">Classes</a>
      <a href="#chronicle" class="menu-link">Chronicle</a>
      <a href="#passages" class="menu-link">My Passages</a>
      <a href="#book" class="btn-gold text-center py-3 mt-4 menu-link">Book Passage</a>
    </div>
  </div>

  <header id="top" class="relative min-h-screen flex items-end overflow-hidden">
    <div class="absolute inset-0">
      <img src="https://images.pexels.com/photos/30173395/pexels-photo-30173395.jpeg?auto=compress&cs=tinysrgb&w=1920" alt="Aurora over snowy peaks" class="w-full h-full object-cover" />
      <div class="absolute inset-0 hero-vignette"></div>
      <div class="absolute inset-0 bg-gradient-to-r from-[#070605]/70 via-transparent to-[#070605]/50"></div>
    </div>
    <div class="hidden md:block absolute right-8 top-32 bottom-32 vertical-jp font-jp text-[#e8d5a3]/30 text-sm z-10">九つの世界と八百万の神々を結ぶ</div>
    <div class="relative z-10 w-full max-w-7xl mx-auto px-5 md:px-8 pb-24 pt-36">
      <div class="reveal">
        <div class="flex items-center gap-3 mb-6">
          <span class="gold-line w-12"></span>
          <span class="font-jp text-[#c23b22] tracking-[.4em] text-xs">神々の鉄路 · 西暦紀</span>
        </div>
        <h1 class="text-5xl sm:text-7xl lg:text-8xl font-cinzel font-bold leading-[.9] shimmer-text">YGGDRAIL</h1>
        <p class="font-corm italic text-2xl md:text-4xl text-[#e8d5a3] mt-4 max-w-2xl">Where the Nine Realms meet the Eight Million Kami.</p>
        <p class="mt-6 max-w-xl text-[#f3ead8]/70 leading-relaxed">A sovereign railway spanning Bifrost and Torii, fjord and shrine. Book passage aboard trains named for gods — Amaterasu’s dawn, Odin’s eight-legged night, Raijin’s thunderbolt.</p>
        <div class="flex flex-wrap gap-4 mt-10">
          <a href="#book" class="btn-gold px-8 py-4 text-xs">Begin Passage</a>
          <a href="#routes" class="btn-ghost px-8 py-4 text-xs">Explore Lines</a>
        </div>
      </div>
      <div class="mt-16 grid grid-cols-2 md:grid-cols-4 gap-6 reveal">
        <div>
          <div class="font-cinzel text-2xl text-[#c9a84c]" id="statTrains">12</div>
          <div class="text-[10px] tracking-[.25em] uppercase text-[#f3ead8]/50 mt-1">Divine Lines</div>
        </div>
        <div>
          <div class="font-cinzel text-2xl text-[#c9a84c]">16</div>
          <div class="text-[10px] tracking-[.25em] uppercase text-[#f3ead8]/50 mt-1">Realm Stations</div>
        </div>
        <div>
          <div class="font-cinzel text-2xl text-[#c9a84c]">320</div>
          <div class="text-[10px] tracking-[.25em] uppercase text-[#f3ead8]/50 mt-1">Kami-knots</div>
        </div>
        <div>
          <div class="font-cinzel text-2xl text-[#c9a84c]" id="nextDept">—</div>
          <div class="text-[10px] tracking-[.25em] uppercase text-[#f3ead8]/50 mt-1">Next Departure</div>
        </div>
      </div>
    </div>
  </header>

  <main id="main">
    <section id="book" class="relative z-20 -mt-10 px-5 md:px-8">
      <div class="max-w-7xl mx-auto gold-border bg-[#100e0c]/95 seigaiha p-6 md:p-8 torii-frame">
        <div class="flex items-end justify-between mb-6 flex-wrap gap-3">
          <div>
            <div class="font-jp text-[#c23b22] text-xs tracking-[.3em]">乗車券</div>
            <h2 class="font-cinzel text-xl md:text-2xl text-[#e8d5a3]">Chart Your Passage</h2>
          </div>
          <div class="text-xs text-[#f3ead8]/50 font-jp" id="liveClock"></div>
        </div>
        <form id="searchForm" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-4">
          <label class="lg:col-span-1 block">
            <span class="block text-[10px] tracking-[.2em] uppercase text-[#c9a84c] mb-2">Origin</span>
            <select id="origin" class="w-full px-3 py-3 text-sm" required></select>
          </label>
          <label class="lg:col-span-1 block">
            <span class="block text-[10px] tracking-[.2em] uppercase text-[#c9a84c] mb-2">Destination</span>
            <select id="dest" class="w-full px-3 py-3 text-sm" required></select>
          </label>
          <label class="block">
            <span class="block text-[10px] tracking-[.2em] uppercase text-[#c9a84c] mb-2">Date</span>
            <input type="date" id="date" class="w-full px-3 py-3 text-sm" required />
          </label>
          <label class="block">
            <span class="block text-[10px] tracking-[.2em] uppercase text-[#c9a84c] mb-2">Travelers</span>
            <div class="flex items-center border border-[#c9a84c]/28 px-2 py-2">
              <button type="button" id="paxMinus" class="w-8 h-8 text-[#c9a84c]">−</button>
              <input id="pax" value="1" readonly class="w-full text-center bg-transparent border-0 py-1" />
              <button type="button" id="paxPlus" class="w-8 h-8 text-[#c9a84c]">+</button>
            </div>
          </label>
          <label class="block">
            <span class="block text-[10px] tracking-[.2em] uppercase text-[#c9a84c] mb-2">Class</span>
            <select id="klass" class="w-full px-3 py-3 text-sm">
              <option value="mortal">Mortal Coach · 冥界席</option>
              <option value="einherjar">Einherjar · 英霊席</option>
              <option value="kami">Kami Suite · 神席</option>
            </select>
          </label>
          <div class="flex items-end">
            <button type="submit" class="btn-gold w-full py-3.5 text-xs">Seek Trains</button>
          </div>
        </form>
        <button type="button" id="swapBtn" class="mt-4 text-[11px] tracking-[.2em] uppercase text-[#c9a84c]/80 hover:text-[#c9a84c]">⇄ Reverse Realms</button>
      </div>
    </section>

    <section class="max-w-7xl mx-auto px-5 md:px-8 py-20">
      <div class="flex items-end justify-between mb-8 reveal">
        <div>
          <div class="font-jp text-[#c23b22] text-xs tracking-[.3em]">発車案内</div>
          <h2 class="font-cinzel text-3xl text-[#e8d5a3]">Live Departures</h2>
        </div>
        <div class="text-[10px] tracking-[.25em] uppercase text-[#9ec5d4]">Asgard Central · Platform of Worlds</div>
      </div>
      <div class="gold-border overflow-hidden departures">
        <div class="grid grid-cols-12 gap-2 px-4 py-3 text-[10px] tracking-[.2em] uppercase text-[#c9a84c] bg-[#1a1510]">
          <div class="col-span-2">Time</div>
          <div class="col-span-3">Service</div>
          <div class="col-span-3">Destination</div>
          <div class="col-span-2">Platform</div>
          <div class="col-span-2 text-right">Status</div>
        </div>
        <div id="board"></div>
      </div>
    </section>

    <section id="routes" class="py-10 seigaiha">
      <div class="max-w-7xl mx-auto px-5 md:px-8">
        <div class="reveal mb-12">
          <div class="font-jp text-[#c23b22] text-xs tracking-[.3em]">神の路線</div>
          <h2 class="font-cinzel text-3xl md:text-5xl text-[#e8d5a3]">Sacred Lines</h2>
          <p class="font-corm italic text-xl text-[#f3ead8]/70 mt-3 max-w-2xl">Each train is consecrated to a deity. Choose your patron for the crossing.</p>
        </div>
        <div id="routeGrid" class="grid md:grid-cols-2 lg:grid-cols-3 gap-6"></div>
      </div>
    </section>

    <section id="destinations" class="py-24">
      <div class="max-w-7xl mx-auto px-5 md:px-8">
        <div class="reveal mb-12 flex flex-col md:flex-row md:items-end md:justify-between gap-4">
          <div>
            <div class="font-jp text-[#c23b22] text-xs tracking-[.3em]">領域</div>
            <h2 class="font-cinzel text-3xl md:text-5xl text-[#e8d5a3]">Sixteen Realms</h2>
          </div>
          <div class="flex gap-2 text-[10px] tracking-[.18em] uppercase">
            <button data-filter="all" class="filter-btn px-3 py-2 border border-[#c9a84c] text-[#c9a84c]">All</button>
            <button data-filter="norse" class="filter-btn px-3 py-2 border border-[#c9a84c]/30 text-[#f3ead8]/70">Norse</button>
            <button data-filter="jp" class="filter-btn px-3 py-2 border border-[#c9a84c]/30 text-[#f3ead8]/70">Japanese</button>
            <button data-filter="fusion" class="filter-btn px-3 py-2 border border-[#c9a84c]/30 text-[#f3ead8]/70">Junction</button>
          </div>
        </div>
        <div id="destGrid" class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4"></div>
      </div>
    </section>

    <section class="py-16 px-5">
      <div class="max-w-7xl mx-auto gold-border bg-[#0c0b09] p-6 md:p-10">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-8 gap-4">
          <div>
            <div class="font-jp text-[#c23b22] text-xs tracking-[.3em]">世界樹図</div>
            <h2 class="font-cinzel text-3xl text-[#e8d5a3]">The World-Tree Map</h2>
          </div>
          <p class="text-sm text-[#f3ead8]/60 max-w-md">Click a station to set destination; shift-click to set origin. Gold paths are Bifrost-class; crimson paths are Torii-class.</p>
        </div>
        <div class="overflow-x-auto">
          <svg id="realmMap" viewBox="0 0 900 520" class="w-full min-w-[720px] h-auto">
            <defs>
              <linearGradient id="bifrost" x1="0" y1="0" x2="1" y2="0">
                <stop offset="0%" stop-color="#9ec5d4" />
                <stop offset="50%" stop-color="#c9a84c" />
                <stop offset="100%" stop-color="#c23b22" />
              </linearGradient>
            </defs>
            <rect width="900" height="520" fill="#0a0908" />
            <g id="mapLines" fill="none" stroke-width="1.4"></g>
            <g id="mapNodes"></g>
          </svg>
        </div>
        <div id="mapInfo" class="mt-6 text-sm text-[#e8d5a3]/80 font-corm italic">Hover a node — the realms will speak.</div>
      </div>
    </section>

    <section id="fleet" class="py-24 asano">
      <div class="max-w-7xl mx-auto px-5 md:px-8">
        <div class="reveal mb-12">
          <div class="font-jp text-[#c23b22] text-xs tracking-[.3em]">車輌</div>
          <h2 class="font-cinzel text-3xl md:text-5xl text-[#e8d5a3]">The Consecrated Fleet</h2>
        </div>
        <div class="grid lg:grid-cols-2 gap-8">
          <article class="relative overflow-hidden gold-border min-h-[340px] group">
            <img src="https://images.pexels.com/photos/30936433/pexels-photo-30936433.jpeg?auto=compress&cs=tinysrgb&w=1400" alt="High-speed divine train" class="absolute inset-0 w-full h-full object-cover group-hover:scale-105 transition duration-700" />
            <div class="absolute inset-0 bg-gradient-to-t from-[#070605] via-[#070605]/40 to-transparent"></div>
            <div class="relative p-8 h-full flex flex-col justify-end">
              <div class="font-jp text-xs text-[#c23b22] tracking-[.3em]">雷神 · RAIJIN</div>
              <h3 class="font-cinzel text-3xl text-[#e8d5a3]">Thunderbolt 320</h3>
              <p class="text-sm text-[#f3ead8]/70 mt-2">The fastest crossing in the Nine Realms. Drum-skin hull, lightning capacitors, tea served at Mach prayer.</p>
            </div>
          </article>
          <article class="relative overflow-hidden gold-border min-h-[340px] group">
            <img src="https://images.pexels.com/photos/29096923/pexels-photo-29096923.jpeg?auto=compress&cs=tinysrgb&w=1400" alt="Luxury dining car" class="absolute inset-0 w-full h-full object-cover group-hover:scale-105 transition duration-700" />
            <div class="absolute inset-0 bg-gradient-to-t from-[#070605] via-[#070605]/40 to-transparent"></div>
            <div class="relative p-8 h-full flex flex-col justify-end">
              <div class="font-jp text-xs text-[#c23b22] tracking-[.3em]">天照 · AMATERASU</div>
              <h3 class="font-cinzel text-3xl text-[#e8d5a3]">Sun Empress Sleeper</h3>
              <p class="text-sm text-[#f3ead8]/70 mt-2">Gold-leaf suites, shrine-quiet corridors, dawn tea as the sun goddess raises the east.</p>
            </div>
          </article>
          <article class="relative overflow-hidden gold-border min-h-[280px] group">
            <img src="https://images.pexels.com/photos/4823601/pexels-photo-4823601.jpeg?auto=compress&cs=tinysrgb&w=1400" alt="Vintage train window" class="absolute inset-0 w-full h-full object-cover group-hover:scale-105 transition duration-700" />
            <div class="absolute inset-0 bg-gradient-to-t from-[#070605] via-[#070605]/50 to-transparent"></div>
            <div class="relative p-8 h-full flex flex-col justify-end">
              <div class="font-jp text-xs text-[#c23b22] tracking-[.3em]">オーディン · ODIN</div>
              <h3 class="font-cinzel text-2xl text-[#e8d5a3]">Sleipnir Night</h3>
              <p class="text-sm text-[#f3ead8]/70 mt-2">Eight-legged overnight. Rune-lit berths. The Allfather’s ravens report the weather.</p>
            </div>
          </article>
          <article class="relative overflow-hidden gold-border min-h-[280px] group">
            <img src="https://images.pexels.com/photos/2170475/pexels-photo-2170475.jpeg?auto=compress&cs=tinysrgb&w=1400" alt="Train interior" class="absolute inset-0 w-full h-full object-cover group-hover:scale-105 transition duration-700" />
            <div class="absolute inset-0 bg-gradient-to-t from-[#070605] via-[#070605]/50 to-transparent"></div>
            <div class="relative p-8 h-full flex flex-col justify-end">
              <div class="font-jp text-xs text-[#c23b22] tracking-[.3em]">稲荷 · INARI</div>
              <h3 class="font-cinzel text-2xl text-[#e8d5a3]">Kitsune Whisper</h3>
              <p class="text-sm text-[#f3ead8]/70 mt-2">Scenic fox-fire coaches. Sake and rice-wine pairings through Inari Grove at dusk.</p>
            </div>
          </article>
        </div>
      </div>
    </section>

    <section id="classes" class="py-24">
      <div class="max-w-7xl mx-auto px-5 md:px-8">
        <div class="reveal mb-12">
          <div class="font-jp text-[#c23b22] text-xs tracking-[.3em]">座席等級</div>
          <h2 class="font-cinzel text-3xl md:text-5xl text-[#e8d5a3]">Three Orders of Passage</h2>
        </div>
        <div class="grid md:grid-cols-3 gap-6">
          <article class="class-card gold-border overflow-hidden bg-[#100e0c]">
            <div class="h-48 overflow-hidden">
              <img src="https://images.pexels.com/photos/23345454/pexels-photo-23345454.jpeg?auto=compress&cs=tinysrgb&w=1000" alt="Mortal coach" class="class-img w-full h-full object-cover" />
            </div>
            <div class="p-6">
              <div class="font-jp text-xs text-[#c23b22]">冥界席</div>
              <h3 class="font-cinzel text-2xl text-[#e8d5a3] mt-1">Mortal Coach</h3>
              <p class="text-sm text-[#f3ead8]/70 mt-3">Honest timber benches, paper lanterns, a view of every realm. For pilgrims and poets.</p>
              <ul class="mt-4 space-y-2 text-xs text-[#f3ead8]/60">
                <li>— 2+3 seating · tea service</li>
                <li>— Shrine blessing at origin</li>
                <li>From KR 4,800</li>
              </ul>
            </div>
          </article>
          <article class="class-card gold-border overflow-hidden bg-[#100e0c] relative">
            <div class="absolute top-3 right-3 text-[9px] tracking-[.2em] uppercase bg-[#c9a84c] text-[#1a1204] px-2 py-1 font-cinzel z-10">Favored</div>
            <div class="h-48 overflow-hidden">
              <img src="https://images.pexels.com/photos/2170475/pexels-photo-2170475.jpeg?auto=compress&cs=tinysrgb&w=1000" alt="Einherjar class" class="class-img w-full h-full object-cover" />
            </div>
            <div class="p-6">
              <div class="font-jp text-xs text-[#c23b22]">英霊席</div>
              <h3 class="font-cinzel text-2xl text-[#e8d5a3] mt-1">Einherjar</h3>
              <p class="text-sm text-[#f3ead8]/70 mt-3">The warrior’s rest. Wider seats, mead or matcha, a private kamidana alcove.</p>
              <ul class="mt-4 space-y-2 text-xs text-[#f3ead8]/60">
                <li>— 2+2 seating · dining car</li>
                <li>— Priority Bifrost boarding</li>
                <li>From KR 12,400</li>
              </ul>
            </div>
          </article>
          <article class="class-card gold-border overflow-hidden bg-[#100e0c]">
            <div class="h-48 overflow-hidden">
              <img src="https://images.pexels.com/photos/29096923/pexels-photo-29096923.jpeg?auto=compress&cs=tinysrgb&w=1000" alt="Kami suite" class="class-img w-full h-full object-cover" />
            </div>
            <div class="p-6">
              <div class="font-jp text-xs text-[#c23b22]">神席</div>
              <h3 class="font-cinzel text-2xl text-[#e8d5a3] mt-1">Kami Suite</h3>
              <p class="text-sm text-[#f3ead8]/70 mt-3">A private shrine on rails. Gold leaf, attendant priest-stewards, open-sky observation.</p>
              <ul class="mt-4 space-y-2 text-xs text-[#f3ead8]/60">
                <li>— 1+2 suites · kaiseki & hunt-feast</li>
                <li>— Patron deity consecration</li>
                <li>From KR 28,000</li>
              </ul>
            </div>
          </article>
        </div>
      </div>
    </section>

    <section id="chronicle" class="relative py-28 overflow-hidden">
      <img src="https://images.pexels.com/photos/7107625/pexels-photo-7107625.jpeg?auto=compress&cs=tinysrgb&w=1920" alt="Torii pathway" class="absolute inset-0 w-full h-full object-cover opacity-25" />
      <div class="absolute inset-0 bg-gradient-to-b from-[#070605] via-[#070605]/80 to-[#070605]"></div>
      <div class="relative max-w-4xl mx-auto px-5 md:px-8 text-center">
        <div class="font-jp text-[#c23b22] text-xs tracking-[.4em]">縁起</div>
        <h2 class="font-cinzel text-3xl md:text-5xl text-[#e8d5a3] mt-3">Chronicle of the Crossing</h2>
        <div class="gold-line w-24 mx-auto my-8"></div>
        <p class="font-corm text-xl md:text-2xl italic text-[#f3ead8]/85 leading-relaxed">In the age after Ragnarök’s ember and before Amaterasu’s second dawn, the World Tree grew iron roots. Heimdall struck a bargain with Inari: a railway of living timber and consecrated steel, so mortals might walk the same corridors as gods — provided they paid the fare, and bowed twice.</p>
        <p class="mt-8 text-[#f3ead8]/60 leading-relaxed">YGGDRAIL is that covenant. Sixteen stations. Twelve named trains. Three classes of soul. Your ticket is both contract and ofuda. Keep it until the last torii.</p>
      </div>
    </section>

    <section class="py-20">
      <div class="max-w-7xl mx-auto px-5 md:px-8 grid md:grid-cols-3 gap-8">
        <blockquote class="gold-border p-8 bg-[#100e0c]">
          <p class="font-corm italic text-xl text-[#e8d5a3]">“The Sleipnir Night passed through Bifrost as if the rainbow had been laid as track. I slept beneath Odin’s eye and woke to shrine bells.”</p>
          <footer class="mt-6 text-xs tracking-[.2em] uppercase text-[#c9a84c]">Sigrid H. · Einherjar</footer>
        </blockquote>
        <blockquote class="gold-border p-8 bg-[#100e0c]">
          <p class="font-corm italic text-xl text-[#e8d5a3]">“Kitsune Whisper at dusk is not a train. It is a procession of lanterns that happens to have wheels.”</p>
          <footer class="mt-6 text-xs tracking-[.2em] uppercase text-[#c9a84c]">Aoi Takahashi · Kami Suite</footer>
        </blockquote>
        <blockquote class="gold-border p-8 bg-[#100e0c]">
          <p class="font-corm italic text-xl text-[#e8d5a3]">“Mortal Coach, Kyoto-Asgard Junction. A farmer, a skald, and a fox spirit shared rice balls. That is the railway.”</p>
          <footer class="mt-6 text-xs tracking-[.2em] uppercase text-[#c9a84c]">Anonymous pilgrim</footer>
        </blockquote>
      </div>
    </section>

    <section id="passages" class="py-16 px-5 md:px-8">
      <div class="max-w-7xl mx-auto">
        <div class="flex items-end justify-between mb-8">
          <div>
            <div class="font-jp text-[#c23b22] text-xs tracking-[.3em]">我が道</div>
            <h2 class="font-cinzel text-3xl text-[#e8d5a3]">My Passages</h2>
          </div>
          <button id="clearPassages" class="text-[10px] tracking-[.2em] uppercase text-[#c9a84c]/70 hover:text-[#c9a84c]">Clear ledger</button>
        </div>
        <div id="passagesList" class="grid md:grid-cols-2 gap-4"></div>
      </div>
    </section>
  </main>

  <footer class="border-t border-[#c9a84c]/20 pt-16 pb-8 seigaiha">
    <div class="max-w-7xl mx-auto px-5 md:px-8 grid md:grid-cols-4 gap-10">
      <div>
        <div class="font-cinzel tracking-[.35em] text-[#e8d5a3]">YGGDRAIL</div>
        <div class="font-jp text-xs text-[#c23b22] mt-1">神鉄株式会社</div>
        <p class="text-sm text-[#f3ead8]/50 mt-4">A covenant railway of the Æsir and the Kami. Fares in Kami-Rune (KR).</p>
      </div>
      <div>
        <div class="text-[10px] tracking-[.25em] uppercase text-[#c9a84c] mb-4">Passage</div>
        <ul class="space-y-2 text-sm text-[#f3ead8]/60">
          <li><a href="#book">Book</a></li>
          <li><a href="#routes">Sacred Lines</a></li>
          <li><a href="#classes">Orders</a></li>
        </ul>
      </div>
      <div>
        <div class="text-[10px] tracking-[.25em] uppercase text-[#c9a84c] mb-4">Realms</div>
        <ul class="space-y-2 text-sm text-[#f3ead8]/60">
          <li>Asgard Central</li>
          <li>Amaterasu Gate</li>
          <li>Kyoto-Asgard Junction</li>
        </ul>
      </div>
      <div>
        <div class="text-[10px] tracking-[.25em] uppercase text-[#c9a84c] mb-4">Blessing</div>
        <p class="text-sm text-[#f3ead8]/50">May Heimdall keep the bridge and Inari keep the rice. Travel with two bows and one ticket.</p>
      </div>
    </div>
    <div class="gold-line max-w-7xl mx-auto mt-12 mb-6"></div>
    <div class="text-center text-[10px] tracking-[.25em] uppercase text-[#f3ead8]/35">© YGGDRAIL Divine Railway · 九界連絡 · Not a real carrier</div>
  </footer>

  <div id="resultsModal" class="fixed inset-0 z-50 hidden modal-bg overflow-y-auto">
    <div class="min-h-full flex items-start justify-center p-4 md:p-10">
      <div class="w-full max-w-4xl bg-[#100e0c] gold-border p-6 md:p-8 relative">
        <button data-close="resultsModal" class="absolute top-4 right-5 text-[#e8d5a3] text-xl">×</button>
        <div class="font-jp text-[#c23b22] text-xs tracking-[.3em]">検索結果</div>
        <h3 class="font-cinzel text-2xl text-[#e8d5a3] mb-2">Available Services</h3>
        <p id="resultsMeta" class="text-sm text-[#f3ead8]/60 mb-6"></p>
        <div id="resultsList" class="space-y-4"></div>
      </div>
    </div>
  </div>

  <div id="seatModal" class="fixed inset-0 z-50 hidden modal-bg overflow-y-auto">
    <div class="min-h-full flex items-start justify-center p-4 md:p-10">
      <div class="w-full max-w-3xl bg-[#100e0c] gold-border p-6 md:p-8 relative">
        <button data-close="seatModal" class="absolute top-4 right-5 text-[#e8d5a3] text-xl">×</button>
        <div class="font-jp text-[#c23b22] text-xs tracking-[.3em]">座席指定</div>
        <h3 class="font-cinzel text-2xl text-[#e8d5a3] mb-1">Choose Your Place</h3>
        <p id="seatMeta" class="text-sm text-[#f3ead8]/60 mb-6"></p>
        <div class="flex gap-4 text-[10px] tracking-[.15em] uppercase mb-4 text-[#f3ead8]/50">
          <span class="flex items-center gap-2"><i class="seat selected pointer-events-none" style="width:16px;height:16px"></i> Chosen</span>
          <span class="flex items-center gap-2"><i class="seat pointer-events-none" style="width:16px;height:16px"></i> Free</span>
          <span class="flex items-center gap-2"><i class="seat taken pointer-events-none" style="width:16px;height:16px"></i> Taken</span>
        </div>
        <div class="overflow-x-auto pb-4">
          <div class="inline-block min-w-full">
            <div class="text-center text-[10px] tracking-[.4em] text-[#c9a84c] mb-3">▲ DIRECTION OF TRAVEL ▲</div>
            <div id="seatMap" class="flex flex-col items-center gap-2"></div>
          </div>
        </div>
        <div class="flex items-center justify-between mt-6 gap-4 flex-wrap">
          <div id="seatChosen" class="text-sm text-[#e8d5a3]">No seats chosen</div>
          <button id="toPassenger" class="btn-gold px-6 py-3 text-xs">Continue</button>
        </div>
      </div>
    </div>
  </div>

  <div id="passengerModal" class="fixed inset-0 z-50 hidden modal-bg overflow-y-auto">
    <div class="min-h-full flex items-start justify-center p-4 md:p-10">
      <div class="w-full max-w-2xl bg-[#100e0c] gold-border p-6 md:p-8 relative">
        <button data-close="passengerModal" class="absolute top-4 right-5 text-[#e8d5a3] text-xl">×</button>
        <div class="font-jp text-[#c23b22] text-xs tracking-[.3em]">旅客情報</div>
        <h3 class="font-cinzel text-2xl text-[#e8d5a3] mb-6">Name & Patron</h3>
        <form id="passengerForm" class="space-y-4">
          <label class="block">
            <span class="block text-[10px] tracking-[.2em] uppercase text-[#c9a84c] mb-2">Full name</span>
            <input id="pname" required class="w-full px-3 py-3" placeholder="As it should appear on the ofuda" />
          </label>
          <label class="block">
            <span class="block text-[10px] tracking-[.2em] uppercase text-[#c9a84c] mb-2">Seal (email)</span>
            <input id="pemail" type="email" required class="w-full px-3 py-3" placeholder="you@realm.mail" />
          </label>
          <label class="block">
            <span class="block text-[10px] tracking-[.2em] uppercase text-[#c9a84c] mb-2">Patron deity</span>
            <select id="pdeity" class="w-full px-3 py-3"></select>
          </label>
          <div class="gold-border p-4 text-sm text-[#f3ead8]/70" id="fareBox"></div>
          <button type="submit" class="btn-gold w-full py-4 text-xs">Consecrate Ticket · Pay in KR</button>
        </form>
      </div>
    </div>
  </div>

  <div id="ticketModal" class="fixed inset-0 z-50 hidden modal-bg overflow-y-auto">
    <div class="min-h-full flex items-start justify-center p-4 md:p-10">
      <div class="w-full max-w-xl relative">
        <button data-close="ticketModal" class="absolute -top-2 right-0 text-[#e8d5a3] text-xl z-10">×</button>
        <div id="ticketPrint" class="ticket gold-border p-8">
          <div class="flex justify-between items-start">
            <div>
              <div class="font-cinzel tracking-[.4em] text-xs text-[#c9a84c]">YGGDRAIL</div>
              <div class="font-jp text-[10px] text-[#c23b22] tracking-[.3em]">神鉄 乗車券</div>
            </div>
            <div class="text-right text-[10px] text-[#f3ead8]/50" id="tRef"></div>
          </div>
          <div class="gold-line my-5"></div>
          <h3 class="font-cinzel text-2xl text-[#e8d5a3]" id="tTrain"></h3>
          <div class="font-jp text-sm text-[#e8b4b8]" id="tTrainJp"></div>
          <div class="grid grid-cols-2 gap-6 mt-6">
            <div>
              <div class="text-[10px] tracking-[.2em] uppercase text-[#c9a84c]">From</div>
              <div class="text-lg" id="tFrom"></div>
              <div class="font-jp text-xs text-[#f3ead8]/50" id="tFromJp"></div>
              <div class="mt-1 text-[#9ec5d4]" id="tDep"></div>
            </div>
            <div class="text-right">
              <div class="text-[10px] tracking-[.2em] uppercase text-[#c9a84c]">To</div>
              <div class="text-lg" id="tTo"></div>
              <div class="font-jp text-xs text-[#f3ead8]/50" id="tToJp"></div>
              <div class="mt-1 text-[#9ec5d4]" id="tArr"></div>
            </div>
          </div>
          <div class="ticket-notch gold-line my-6"></div>
          <div class="grid grid-cols-4 gap-3 text-sm">
            <div>
              <div class="text-[10px] tracking-[.15em] text-[#c9a84c]">DATE</div>
              <div id="tDate"></div>
            </div>
            <div>
              <div class="text-[10px] tracking-[.15em] text-[#c9a84c]">CLASS</div>
              <div id="tClass"></div>
            </div>
            <div>
              <div class="text-[10px] tracking-[.15em] text-[#c9a84c]">SEAT</div>
              <div id="tSeat"></div>
            </div>
            <div>
              <div class="text-[10px] tracking-[.15em] text-[#c9a84c]">FARE</div>
              <div id="tFare"></div>
            </div>
          </div>
          <div class="mt-6 flex justify-between items-end">
            <div>
              <div class="text-[10px] tracking-[.15em] text-[#c9a84c]">TRAVELER</div>
              <div id="tName" class="font-corm italic text-xl"></div>
              <div class="text-xs text-[#f3ead8]/50" id="tDeity"></div>
            </div>
            <div class="qr-fake w-16 h-16"></div>
          </div>
          <p class="mt-6 text-[10px] tracking-[.12em] text-[#f3ead8]/40">May the kami and the æsir guard your passage. Bow twice at the torii. Do not look too long at Bifrost.</p>
        </div>
        <div class="flex gap-3 mt-4">
          <button id="printTicket" class="btn-gold flex-1 py-3 text-xs">Print Ofuda</button>
          <button data-close="ticketModal" class="btn-ghost flex-1 py-3 text-xs">Close</button>
        </div>
      </div>
    </div>
  </div>

  <div id="destModal" class="fixed inset-0 z-50 hidden modal-bg overflow-y-auto">
    <div class="min-h-full flex items-center justify-center p-4">
      <div class="w-full max-w-lg bg-[#100e0c] gold-border overflow-hidden relative">
        <button data-close="destModal" class="absolute top-4 right-5 text-[#e8d5a3] text-xl z-10">×</button>
        <img id="dImg" alt="" class="w-full h-52 object-cover" />
        <div class="p-6">
          <div class="font-jp text-[#c23b22] text-xs" id="dJp"></div>
          <h3 class="font-cinzel text-2xl text-[#e8d5a3]" id="dName"></h3>
          <p class="text-sm text-[#f3ead8]/70 mt-3" id="dLore"></p>
          <button id="bookFromDest" class="btn-gold w-full mt-6 py-3 text-xs">Set as Destination</button>
        </div>
      </div>
    </div>
  </div>

  <div id="toast" class="toast font-corm italic"></div>

  <script>
    const STATIONS = [{
        id: 'asgard',
        name: 'Asgard Central',
        jp: 'アスガルド中央',
        realm: 'Asgard',
        type: 'norse',
        x: 160,
        y: 90,
        img: 'https://images.pexels.com/photos/30173395/pexels-photo-30173395.jpeg?auto=compress&cs=tinysrgb&w=1200',
        lore: 'Seat of the Æsir. Golden halls, raven post, and the first platform of the World-Tree line.'
      },
      {
        id: 'valhalla',
        name: 'Valhalla Terminus',
        jp: 'ヴァルハラ終着',
        realm: 'Asgard',
        type: 'norse',
        x: 280,
        y: 60,
        img: 'https://images.pexels.com/photos/9789224/pexels-photo-9789224.jpeg?auto=compress&cs=tinysrgb&w=1200',
        lore: 'Where the honored dead feast. Trains arrive to horn-song. Departures are rarer.'
      },
      {
        id: 'bifrost',
        name: 'Bifröst Bridge',
        jp: 'ビフレスト橋',
        realm: 'Bifröst',
        type: 'norse',
        x: 340,
        y: 150,
        img: 'https://images.pexels.com/photos/33049911/pexels-photo-33049911.jpeg?auto=compress&cs=tinysrgb&w=1200',
        lore: 'Rainbow iron. Heimdall inspects every ticket with hearing that catches grass growing.'
      },
      {
        id: 'midgard',
        name: 'Midgard Crossing',
        jp: 'ミッドガルド交差',
        realm: 'Midgard',
        type: 'norse',
        x: 420,
        y: 240,
        img: 'https://images.pexels.com/photos/32574837/pexels-photo-32574837.jpeg?auto=compress&cs=tinysrgb&w=1200',
        lore: 'The mortal hub. Markets, skalds, and transfers to every realm except those that refuse maps.'
      },
      {
        id: 'jotun',
        name: 'Jötunheim Fjord',
        jp: 'ヨトゥンヘイム峡湾',
        realm: 'Jötunheim',
        type: 'norse',
        x: 180,
        y: 260,
        img: 'https://images.pexels.com/photos/38064857/pexels-photo-38064857.jpeg?auto=compress&cs=tinysrgb&w=1200',
        lore: 'Ice-giants lease the platform. Dress warmly. Do not accept stones that look like bread.'
      },
      {
        id: 'nifl',
        name: 'Niflheim Ice',
        jp: 'ニヴルヘイム氷',
        realm: 'Niflheim',
        type: 'norse',
        x: 80,
        y: 360,
        img: 'https://images.pexels.com/photos/31265162/pexels-photo-31265162.jpeg?auto=compress&cs=tinysrgb&w=1200',
        lore: 'Mist so thick the timetable is carved in ice. Limited winter service.'
      },
      {
        id: 'helheim',
        name: 'Helheim Descent',
        jp: 'ヘルヘイム降路',
        realm: 'Helheim',
        type: 'norse',
        x: 220,
        y: 430,
        img: 'https://images.pexels.com/photos/7618513/pexels-photo-7618513.jpeg?auto=compress&cs=tinysrgb&w=1200',
        lore: 'One-way rumors are false. Return tickets exist. Hel is a punctual stationmaster.'
      },
      {
        id: 'kyoto',
        name: 'Kyoto-Asgard Junction',
        jp: '京都アスガルド結節',
        realm: 'Kansai',
        type: 'fusion',
        x: 520,
        y: 220,
        img: 'https://images.pexels.com/photos/16640875/pexels-photo-16640875.jpeg?auto=compress&cs=tinysrgb&w=1200',
        lore: 'The hinge of two pantheons. Torii and rune-stone share a plaza. Bow, then raise a horn.'
      },
      {
        id: 'amaterasu',
        name: 'Amaterasu Gate',
        jp: '天照門',
        realm: 'Takamagahara',
        type: 'jp',
        x: 680,
        y: 80,
        img: 'https://images.pexels.com/photos/16226263/pexels-photo-16226263.jpeg?auto=compress&cs=tinysrgb&w=1200',
        lore: 'The sun’s own threshold. No night service — the station is daylight, even at midnight.'
      },
      {
        id: 'takama',
        name: 'Takamagahara',
        jp: '高天原',
        realm: 'Takamagahara',
        type: 'jp',
        x: 800,
        y: 50,
        img: 'https://images.pexels.com/photos/35264136/pexels-photo-35264136.jpeg?auto=compress&cs=tinysrgb&w=1200',
        lore: 'Plain of High Heaven. Clouds are the ballast. Mortals require a kami co-signer.'
      },
      {
        id: 'inari',
        name: 'Inari Grove',
        jp: '稲荷の杜',
        realm: 'Chūbu',
        type: 'jp',
        x: 700,
        y: 200,
        img: 'https://images.pexels.com/photos/7107625/pexels-photo-7107625.jpeg?auto=compress&cs=tinysrgb&w=1200',
        lore: 'Ten thousand vermillion gates. Foxes punch tickets. Leave a rice offering on the luggage rack.'
      },
      {
        id: 'fujin',
        name: 'Fujin Pass',
        jp: '風神峠',
        realm: 'Chūbu',
        type: 'jp',
        x: 620,
        y: 300,
        img: 'https://images.pexels.com/photos/37194588/pexels-photo-37194588.jpeg?auto=compress&cs=tinysrgb&w=1200',
        lore: 'Wind-god altitude. Windows may open themselves. Hold your hat and your soul.'
      },
      {
        id: 'raijin',
        name: 'Raijin Peak',
        jp: '雷神峰',
        realm: 'Kantō',
        type: 'jp',
        x: 780,
        y: 280,
        img: 'https://images.pexels.com/photos/2337927/pexels-photo-2337927.jpeg?auto=compress&cs=tinysrgb&w=1200',
        lore: 'Thunder drums in the tunnels. The 320 service was born here, screaming.'
      },
      {
        id: 'itsuku',
        name: 'Itsukushima Torii',
        jp: '厳島鳥居',
        realm: 'Seto',
        type: 'jp',
        x: 640,
        y: 400,
        img: 'https://images.pexels.com/photos/20594682/pexels-photo-20594682.jpeg?auto=compress&cs=tinysrgb&w=1200',
        lore: 'The sea is the platform. Tide tables are as important as the timetable.'
      },
      {
        id: 'izanagi',
        name: 'Izanagi Harbor',
        jp: '伊邪那岐港',
        realm: 'Awaji',
        type: 'jp',
        x: 500,
        y: 420,
        img: 'https://images.pexels.com/photos/32853965/pexels-photo-32853965.jpeg?auto=compress&cs=tinysrgb&w=1200',
        lore: 'Where the islands were stirred into being. Ferry-trains and creation myths share a quay.'
      },
      {
        id: 'susanoo',
        name: 'Susanoo Coast',
        jp: '須佐之男海岸',
        realm: 'Izumo',
        type: 'jp',
        x: 380,
        y: 380,
        img: 'https://images.pexels.com/photos/37716664/pexels-photo-37716664.jpeg?auto=compress&cs=tinysrgb&w=1200',
        lore: 'Storm-brother’s shore. Sake strong enough to slay an eight-headed delay.'
      }
    ];

    const TRAINS = [{
        id: 'amaterasu',
        name: 'Amaterasu Express',
        jp: '天照急行',
        deity: 'Amaterasu Ōmikami',
        kind: 'Daylight luxury',
        speed: 280,
        color: '#e8d5a3',
        img: 'https://images.pexels.com/photos/16226263/pexels-photo-16226263.jpeg?auto=compress&cs=tinysrgb&w=1200'
      },
      {
        id: 'sleipnir',
        name: "Odin's Sleipnir",
        jp: 'スレイプニル夜行',
        deity: 'Odin',
        kind: 'Eight-legged sleeper',
        speed: 210,
        color: '#9ec5d4',
        img: 'https://images.pexels.com/photos/4823601/pexels-photo-4823601.jpeg?auto=compress&cs=tinysrgb&w=1200'
      },
      {
        id: 'raijin',
        name: 'Raijin Thunderbolt',
        jp: '雷神三二〇',
        deity: 'Raijin',
        kind: 'High-speed',
        speed: 320,
        color: '#c23b22',
        img: 'https://images.pexels.com/photos/30936433/pexels-photo-30936433.jpeg?auto=compress&cs=tinysrgb&w=1200'
      },
      {
        id: 'valkyrie',
        name: 'Valkyrie Twilight',
        jp: 'ヴァルキリー黄昏',
        deity: 'Freyja',
        kind: 'Evening scenic',
        speed: 190,
        color: '#e8b4b8',
        img: 'https://images.pexels.com/photos/33049911/pexels-photo-33049911.jpeg?auto=compress&cs=tinysrgb&w=1200'
      },
      {
        id: 'kitsune',
        name: 'Kitsune Whisper',
        jp: '狐火ささやき',
        deity: 'Inari Ōkami',
        kind: 'Grove local',
        speed: 160,
        color: '#c9a84c',
        img: 'https://images.pexels.com/photos/7107625/pexels-photo-7107625.jpeg?auto=compress&cs=tinysrgb&w=1200'
      },
      {
        id: 'mjolnir',
        name: "Thor's Mjölnir",
        jp: 'ミョルニル山岳',
        deity: 'Thor',
        kind: 'Mountain climber',
        speed: 175,
        color: '#8c6d1f',
        img: 'https://images.pexels.com/photos/32574837/pexels-photo-32574837.jpeg?auto=compress&cs=tinysrgb&w=1200'
      },
      {
        id: 'susanoo',
        name: 'Susanoo Storm',
        jp: '須佐之男嵐',
        deity: 'Susanoo-no-Mikoto',
        kind: 'All-weather express',
        speed: 240,
        color: '#9b1b1b',
        img: 'https://images.pexels.com/photos/32853965/pexels-photo-32853965.jpeg?auto=compress&cs=tinysrgb&w=1200'
      },
      {
        id: 'fenrir',
        name: 'Fenrir Shadow',
        jp: 'フェンリル影',
        deity: 'Fenrir',
        kind: 'Midnight express',
        speed: 230,
        color: '#4a5560',
        img: 'https://images.pexels.com/photos/19657289/pexels-photo-19657289.jpeg?auto=compress&cs=tinysrgb&w=1200'
      },
      {
        id: 'tsuku',
        name: 'Tsukuyomi Silver',
        jp: '月読銀',
        deity: 'Tsukuyomi',
        kind: 'Moonlight',
        speed: 200,
        color: '#cfd8e0',
        img: 'https://images.pexels.com/photos/2337927/pexels-photo-2337927.jpeg?auto=compress&cs=tinysrgb&w=1200'
      },
      {
        id: 'freya',
        name: 'Freyja Falcon',
        jp: 'フレイヤ隼',
        deity: 'Freyja',
        kind: 'Cat-drawn day',
        speed: 185,
        color: '#d4a0a8',
        img: 'https://images.pexels.com/photos/37716664/pexels-photo-37716664.jpeg?auto=compress&cs=tinysrgb&w=1200'
      },
      {
        id: 'heimdall',
        name: 'Heimdall Watch',
        jp: 'ヘイムダル監視',
        deity: 'Heimdall',
        kind: 'Bridge shuttle',
        speed: 150,
        color: '#9ec5d4',
        img: 'https://images.pexels.com/photos/30173395/pexels-photo-30173395.jpeg?auto=compress&cs=tinysrgb&w=1200'
      },
      {
        id: 'hachiman',
        name: 'Hachiman Guard',
        jp: '八幡守',
        deity: 'Hachiman',
        kind: 'Fortress local',
        speed: 155,
        color: '#7a1515',
        img: 'https://images.pexels.com/photos/19080126/pexels-photo-19080126.jpeg?auto=compress&cs=tinysrgb&w=1200'
      }
    ];

    const DEITIES = [{
        id: 'amaterasu',
        name: 'Amaterasu Ōmikami',
        jp: '天照大御神',
        bless: 'May your windows always face the sun.'
      },
      {
        id: 'odin',
        name: 'Odin Allfather',
        jp: 'オーディン',
        bless: 'May two ravens keep your luggage.'
      },
      {
        id: 'inari',
        name: 'Inari Ōkami',
        jp: '稲荷大神',
        bless: 'May foxes guide you to the correct platform.'
      },
      {
        id: 'thor',
        name: 'Thor',
        jp: 'トール',
        bless: 'May thunder clear the tracks ahead.'
      },
      {
        id: 'freyja',
        name: 'Freyja',
        jp: 'フレイヤ',
        bless: 'May your seat be a small Fólkvangr.'
      },
      {
        id: 'susanoo',
        name: 'Susanoo',
        jp: '須佐之男',
        bless: 'May storms bow to your timetable.'
      },
      {
        id: 'raijin',
        name: 'Raijin',
        jp: '雷神',
        bless: 'May drums keep the wheels honest.'
      },
      {
        id: 'heimdall',
        name: 'Heimdall',
        jp: 'ヘイムダル',
        bless: 'May no impostor pass your ticket gate.'
      },
      {
        id: 'tsukuyomi',
        name: 'Tsukuyomi',
        jp: '月読',
        bless: 'May moonlight silver the rails.'
      },
      {
        id: 'benzaiten',
        name: 'Benzaiten',
        jp: '弁財天',
        bless: 'May the journey sound like a biwa.'
      }
    ];

    const CLASS_META = {
      mortal: {
        name: 'Mortal Coach',
        jp: '冥界席',
        mult: 1,
        rows: 8,
        layout: ['A', 'B', 'C', 'aisle', 'D', 'E']
      },
      einherjar: {
        name: 'Einherjar',
        jp: '英霊席',
        mult: 2.4,
        rows: 6,
        layout: ['A', 'B', 'aisle', 'C', 'D']
      },
      kami: {
        name: 'Kami Suite',
        jp: '神席',
        mult: 5.2,
        rows: 4,
        layout: ['A', 'aisle', 'B', 'C']
      }
    };

    const LINKS = [
      ['asgard', 'valhalla'],
      ['asgard', 'bifrost'],
      ['asgard', 'jotun'],
      ['valhalla', 'bifrost'],
      ['bifrost', 'midgard'],
      ['midgard', 'kyoto'],
      ['midgard', 'jotun'],
      ['jotun', 'nifl'],
      ['nifl', 'helheim'],
      ['helheim', 'susanoo'],
      ['midgard', 'susanoo'],
      ['kyoto', 'inari'],
      ['kyoto', 'amaterasu'],
      ['amaterasu', 'takama'],
      ['inari', 'fujin'],
      ['inari', 'raijin'],
      ['fujin', 'itsuku'],
      ['raijin', 'itsuku'],
      ['itsuku', 'izanagi'],
      ['izanagi', 'susanoo'],
      ['kyoto', 'fujin'],
      ['midgard', 'izanagi']
    ];

    const booking = {
      origin: null,
      dest: null,
      date: null,
      pax: 1,
      klass: 'einherjar',
      train: null,
      seats: [],
      passenger: null,
      fare: 0,
      dep: '',
      arr: ''
    };

    const $ = (s, r = document) => r.querySelector(s);
    const $$ = (s, r = document) => [...r.querySelectorAll(s)];

    function toast(msg) {
      const t = $('#toast');
      t.textContent = msg;
      t.classList.add('show');
      setTimeout(() => t.classList.remove('show'), 3200);
    }

    function openModal(id) {
      $('#' + id).classList.remove('hidden');
      document.body.style.overflow = 'hidden';
    }

    function closeModal(id) {
      $('#' + id).classList.add('hidden');
      document.body.style.overflow = '';
    }

    function stationById(id) {
      return STATIONS.find(s => s.id === id);
    }

    function dist(a, b) {
      const A = stationById(a),
        B = stationById(b);
      return Math.hypot(A.x - B.x, A.y - B.y);
    }

    function formatKR(n) {
      return 'KR ' + n.toLocaleString('en-US');
    }

    function pad(n) {
      return String(n).padStart(2, '0');
    }

    function fmtTime(d) {
      return pad(d.getHours()) + ':' + pad(d.getMinutes());
    }

    function seedFrom(str) {
      let h = 2166136261;
      for (const c of str) {
        h ^= c.charCodeAt(0);
        h = Math.imul(h, 16777619);
      }
      return Math.abs(h);
    }

    /* ---------- preloader ---------- */
    let load = 0;
    const loadTimer = setInterval(() => {
      load = Math.min(100, load + Math.random() * 18);
      $('#loadBar').style.width = load + '%';
      if (load >= 100) {
        clearInterval(loadTimer);
        setTimeout(() => $('#preloader').classList.add('gone'), 400);
      }
    }, 180);

    /* ---------- particles: sakura + snow + gold sparks ---------- */
    const canvas = $('#particles');
    const ctx = canvas.getContext('2d');
    let parts = [];

    function resize() {
      canvas.width = innerWidth;
      canvas.height = innerHeight;
    }
    addEventListener('resize', resize);
    resize();

    function spawn() {
      const kind = Math.random();
      const p = {
        x: Math.random() * canvas.width,
        y: -20,
        r: kind > .7 ? 2 + Math.random() * 3 : 4 + Math.random() * 5,
        s: .4 + Math.random() * 1.2,
        a: Math.random() * Math.PI,
        k: kind > .85 ? 'gold' : kind > .45 ? 'snow' : 'sakura',
        rot: Math.random() * Math.PI
      };
      parts.push(p);
    }

    function drawPetal(p) {
      ctx.save();
      ctx.translate(p.x, p.y);
      ctx.rotate(p.rot);
      ctx.beginPath();
      ctx.ellipse(0, 0, p.r, p.r * .55, 0, 0, Math.PI * 2);
      ctx.fillStyle = p.k === 'sakura' ? 'rgba(232,180,184,.7)' : p.k === 'gold' ? 'rgba(201,168,76,.7)' : 'rgba(243,234,216,.55)';
      ctx.fill();
      ctx.restore();
    }

    function tick() {
      ctx.clearRect(0, 0, canvas.width, canvas.height);
      if (parts.length < 70) spawn();
      parts.forEach(p => {
        p.y += p.s;
        p.x += Math.sin(p.a += 0.01) * 0.6;
        p.rot += 0.01;
        drawPetal(p);
      });
      parts = parts.filter(p => p.y < canvas.height + 20);
      requestAnimationFrame(tick);
    }
    tick();

    /* ---------- nav ---------- */
    const nav = $('#nav');
    addEventListener('scroll', () => nav.classList.toggle('nav-scrolled', scrollY > 40));
    $('#menuBtn').onclick = () => $('#mobileMenu').classList.add('open');
    $('#closeMenu').onclick = () => $('#mobileMenu').classList.remove('open');
    $$('.menu-link').forEach(a => a.onclick = () => $('#mobileMenu').classList.remove('open'));

    const i18n = {
      en: {
        book: 'Book Passage',
        seek: 'Seek Trains'
      },
      jp: {
        book: '乗車券を求める',
        seek: '列車を探す'
      }
    };
    let lang = 'en';
    $('#langBtn').onclick = () => {
      lang = lang === 'en' ? 'jp' : 'en';
      toast(lang === 'jp' ? '言語：日本語 — 神々は両方を聴く' : 'Language: English — the gods hear both');
    };

    /* ---------- clock ---------- */
    function tickClock() {
      const n = new Date();
      $('#liveClock').textContent = 'Asgard-Kyoto mean time · ' + n.toLocaleString();
    }
    tickClock();
    setInterval(tickClock, 1000);

    /* ---------- populate selects ---------- */
    function fillSelects() {
      const opts = STATIONS.map(s => `<option value="${s.id}">${s.name} · ${s.jp}</option>`).join('');
      $('#origin').innerHTML = opts;
      $('#dest').innerHTML = opts;
      $('#origin').value = 'asgard';
      $('#dest').value = 'kyoto';
      const d = new Date();
      d.setDate(d.getDate() + 1);
      $('#date').value = d.toISOString().slice(0, 10);
      $('#date').min = new Date().toISOString().slice(0, 10);
      $('#pdeity').innerHTML = DEITIES.map(x => `<option value="${x.id}">${x.name} · ${x.jp}</option>`).join('');
    }
    fillSelects();

    $('#paxMinus').onclick = () => {
      booking.pax = Math.max(1, booking.pax - 1);
      $('#pax').value = booking.pax;
    };
    $('#paxPlus').onclick = () => {
      booking.pax = Math.min(6, booking.pax + 1);
      $('#pax').value = booking.pax;
    };
    $('#swapBtn').onclick = () => {
      const a = $('#origin').value;
      $('#origin').value = $('#dest').value;
      $('#dest').value = a;
    };

    /* ---------- live board ---------- */
    function buildBoard() {
      const now = new Date();
      const rows = [];
      for (let i = 0; i < 8; i++) {
        const t = TRAINS[(now.getMinutes() + i * 3) % TRAINS.length];
        const dest = STATIONS[(i * 5 + 3) % STATIONS.length];
        const dt = new Date(now.getTime() + (8 + i * 11) * 60000);
        const status = i === 0 ? 'BOARDING' : i === 1 ? 'ON TIME' : i === 4 ? 'HEIMDALL HOLD' : 'ON TIME';
        const color = status === 'BOARDING' ? 'text-[#c9a84c]' : status.includes('HOLD') ? 'text-[#c23b22]' : 'text-[#9ec5d4]';
        rows.push(`<div class="board-row grid grid-cols-12 gap-2 px-4 py-3 text-sm items-center">
          <div class="col-span-2 font-cinzel text-[#e8d5a3]">${fmtTime(dt)}</div>
          <div class="col-span-3">${t.name}</div>
          <div class="col-span-3">${dest.name}</div>
          <div class="col-span-2 font-jp text-xs">${['一','二','三','鳥居','虹','七','八','九'][i]} · ${i+1}</div>
          <div class="col-span-2 text-right text-[10px] tracking-[.2em] ${color}">${status}</div>
        </div>`);
      }
      $('#board').innerHTML = rows.join('');
      const next = new Date(now.getTime() + 8 * 60000);
      $('#nextDept').textContent = fmtTime(next);
    }
    buildBoard();
    setInterval(buildBoard, 30000);

    /* ---------- routes grid ---------- */
    function renderRoutes() {
      $('#routeGrid').innerHTML = TRAINS.slice(0, 6).map(t => `
        <article class="route-card gold-border overflow-hidden bg-[#100e0c]">
          <div class="h-44 overflow-hidden relative">
            <img src="${t.img}" alt="${t.name}" class="w-full h-full object-cover" />
            <div class="absolute inset-0 bg-gradient-to-t from-[#100e0c] to-transparent"></div>
            <div class="absolute bottom-3 left-4 font-jp text-xs text-[#c23b22]">${t.jp}</div>
          </div>
          <div class="p-5">
            <h3 class="font-cinzel text-xl text-[#e8d5a3]">${t.name}</h3>
            <p class="text-xs text-[#f3ead8]/50 mt-1">${t.kind} · Patron ${t.deity} · ${t.speed} kami-knots</p>
            <button data-train="${t.id}" class="book-line mt-4 text-[10px] tracking-[.25em] uppercase text-[#c9a84c] hover:underline">Book this line →</button>
          </div>
        </article>`).join('');
      $$('.book-line').forEach(b => b.onclick = () => {
        booking.preferred = b.dataset.train;
        document.getElementById('book').scrollIntoView({
          behavior: 'smooth'
        });
        toast(TRAINS.find(t => t.id === b.dataset.train).name + ' will lead the next search.');
      });
    }
    renderRoutes();

    /* ---------- destinations ---------- */
    function renderDest(filter = 'all') {
      const list = STATIONS.filter(s => filter === 'all' || s.type === filter);
      $('#destGrid').innerHTML = list.map(s => `
        <button data-dest="${s.id}" class="dest-card text-left gold-border overflow-hidden group bg-[#100e0c]">
          <div class="h-36 overflow-hidden">
            <img src="${s.img}" alt="${s.name}" class="w-full h-full object-cover group-hover:scale-110 transition duration-700" />
          </div>
          <div class="p-4">
            <div class="font-jp text-[10px] text-[#c23b22]">${s.jp}</div>
            <div class="font-cinzel text-[#e8d5a3]">${s.name}</div>
            <div class="text-[10px] tracking-[.2em] uppercase text-[#f3ead8]/40 mt-1">${s.realm} · ${s.type}</div>
          </div>
        </button>`).join('');
      $$('.dest-card').forEach(c => c.onclick = () => showDest(c.dataset.dest));
    }
    renderDest();
    $$('.filter-btn').forEach(btn => btn.onclick = () => {
      $$('.filter-btn').forEach(b => {
        b.classList.remove('border-[#c9a84c]', 'text-[#c9a84c]');
        b.classList.add('border-[#c9a84c]/30', 'text-[#f3ead8]/70');
      });
      btn.classList.add('border-[#c9a84c]', 'text-[#c9a84c]');
      btn.classList.remove('border-[#c9a84c]/30', 'text-[#f3ead8]/70');
      renderDest(btn.dataset.filter);
    });

    let destFocus = null;

    function showDest(id) {
      const s = stationById(id);
      destFocus = s;
      $('#dImg').src = s.img;
      $('#dImg').alt = s.name;
      $('#dJp').textContent = s.jp + ' · ' + s.realm;
      $('#dName').textContent = s.name;
      $('#dLore').textContent = s.lore;
      openModal('destModal');
    }
    $('#bookFromDest').onclick = () => {
      if (destFocus) {
        $('#dest').value = destFocus.id;
        closeModal('destModal');
        $('#book').scrollIntoView({
          behavior: 'smooth'
        });
        toast('Destination set: ' + destFocus.name);
      }
    };

    /* ---------- map ---------- */
    function drawMap() {
      const lines = LINKS.map(([a, b]) => {
        const A = stationById(a),
          B = stationById(b);
        const fusion = A.type !== B.type || A.type === 'fusion' || B.type === 'fusion';
        return `<line x1="${A.x}" y1="${A.y}" x2="${B.x}" y2="${B.y}" stroke="${fusion ? 'url(#bifrost)' : A.type==='jp' ? '#c23b22' : '#9ec5d4'}" stroke-opacity=".55"/>`;
      }).join('');
      $('#mapLines').innerHTML = lines;
      $('#mapNodes').innerHTML = STATIONS.map(s => `
        <g class="map-node" data-node="${s.id}">
          <circle cx="${s.x}" cy="${s.y}" r="9" fill="#070605" stroke="${s.type==='jp'?'#c23b22':s.type==='fusion'?'#c9a84c':'#9ec5d4'}" stroke-width="2"/>
          <circle cx="${s.x}" cy="${s.y}" r="3" fill="#c9a84c"/>
          <text x="${s.x+12}" y="${s.y+4}" fill="#f3ead8" font-size="10" font-family="Cinzel, serif">${s.name}</text>
        </g>`).join('');
      $$('[data-node]').forEach(n => {
        n.onmouseenter = () => {
          const s = stationById(n.dataset.node);
          $('#mapInfo').textContent = s.name + ' · ' + s.jp + ' — ' + s.lore;
        };
        n.onclick = (ev) => {
          const s = stationById(n.dataset.node);
          if (ev.shiftKey) {
            if ($('#dest').value === s.id) {
              toast('Origin and destination cannot be the same realm.');
              return;
            }
            $('#origin').value = s.id;
          } else {
            if ($('#origin').value === s.id) {
              toast('That is already your origin. Shift-click to set origin instead.');
              return;
            }
            $('#dest').value = s.id;
          }
          toast((ev.shiftKey ? 'Origin: ' : 'Destination: ') + s.name + ' · ' + stationById($('#origin').value).name + ' → ' + stationById($('#dest').value).name);
        };
      });
    }
    drawMap();

    /* ---------- search / results ---------- */
    $('#searchForm').onsubmit = (e) => {
      e.preventDefault();
      booking.origin = $('#origin').value;
      booking.dest = $('#dest').value;
      booking.date = $('#date').value;
      booking.klass = $('#klass').value;
      booking.pax = +$('#pax').value;
      if (booking.origin === booking.dest) {
        toast('The World Tree does not loop so cheaply. Choose two realms.');
        return;
      }
      showResults();
    };

    function showResults() {
      const o = stationById(booking.origin),
        d = stationById(booking.dest);
      const km = Math.round(dist(booking.origin, booking.dest) * 3.2);
      $('#resultsMeta').textContent = `${o.name} → ${d.name} · ${booking.date} · ${booking.pax} traveler(s) · ${CLASS_META[booking.klass].name}`;
      const seed = seedFrom(booking.origin + booking.dest + booking.date);
      const services = [];
      const order = TRAINS.slice();
      if (booking.preferred) {
        const pref = order.findIndex(t => t.id === booking.preferred);
        if (pref > -1) order.unshift(order.splice(pref, 1)[0]);
      }
      for (let i = 0; i < 5; i++) {
        const train = order[i % order.length];
        const hour = 6 + ((seed + i * 3) % 16);
        const min = (seed + i * 17) % 60;
        const durMin = Math.round((km / train.speed) * 60) + 18;
        const dep = new Date(`${booking.date}T${pad(hour)}:${pad(min)}:00`);
        const arr = new Date(dep.getTime() + durMin * 60000);
        const base = Math.round(km * 28 * CLASS_META[booking.klass].mult);
        const fare = Math.round(base / 100) * 100;
        const occ = 40 + ((seed + i * 9) % 55);
        services.push({
          train,
          dep,
          arr,
          fare,
          occ,
          durMin,
          platform: (i % 7) + 1
        });
      }
      $('#resultsList').innerHTML = services.map((s, i) => `
        <article class="gold-border p-4 md:p-5 flex flex-col md:flex-row md:items-center gap-4 bg-[#16110c]">
          <div class="flex-1">
            <div class="font-cinzel text-[#e8d5a3] text-lg">${s.train.name}</div>
            <div class="font-jp text-xs text-[#c23b22]">${s.train.jp} · ${s.train.deity}</div>
            <div class="mt-2 text-sm text-[#f3ead8]/70">${fmtTime(s.dep)} → ${fmtTime(s.arr)} · ${Math.floor(s.durMin/60)}h ${s.durMin%60}m · Platform ${s.platform}</div>
            <div class="mt-2 h-1 bg-[#2a2214]"><div class="h-full bg-gradient-to-r from-[#c9a84c] to-[#c23b22]" style="width:${s.occ}%"></div></div>
            <div class="text-[10px] text-[#f3ead8]/40 mt-1">${s.occ}% of the car already sworn</div>
          </div>
          <div class="text-right">
            <div class="font-cinzel text-xl text-[#c9a84c]">${formatKR(s.fare)}</div>
            <div class="text-[10px] text-[#f3ead8]/40">per soul</div>
            <button data-idx="${i}" class="choose-train btn-gold mt-3 px-5 py-2 text-[10px]">Select</button>
          </div>
        </article>`).join('');
      window._services = services;
      $$('.choose-train').forEach(b => b.onclick = () => chooseTrain(window._services[+b.dataset.idx]));
      openModal('resultsModal');
    }

    function chooseTrain(s) {
      booking.train = s.train;
      booking.fare = s.fare;
      booking.dep = fmtTime(s.dep);
      booking.arr = fmtTime(s.arr);
      booking.seats = [];
      closeModal('resultsModal');
      renderSeats(s.occ);
      $('#seatMeta').textContent = `${s.train.name} · ${CLASS_META[booking.klass].name} · choose ${booking.pax} seat(s)`;
      openModal('seatModal');
    }

    function renderSeats(occ) {
      const meta = CLASS_META[booking.klass];
      const takenRatio = occ / 100;
      const seed = seedFrom(booking.train.id + booking.date + booking.origin);
      let html = '';
      for (let r = 1; r <= meta.rows; r++) {
        html += `<div class="flex items-center gap-1.5">
          <span class="w-6 text-[10px] text-[#c9a84c] text-right">${r}</span>`;
        meta.layout.forEach(col => {
          if (col === 'aisle') {
            html += `<span class="aisle"></span>`;
            return;
          }
          const id = r + col;
          const h = seedFrom(id + String(seed));
          const taken = (h % 100) < takenRatio * 100;
          html += `<button type="button" class="seat ${taken?'taken':''}" data-seat="${id}" ${taken?'disabled':''}>${id}</button>`;
        });
        html += `</div>`;
      }
      $('#seatMap').innerHTML = html;
      $$('.seat:not(.taken)').forEach(btn => btn.onclick = () => toggleSeat(btn));
      updateSeatLabel();
    }

    function toggleSeat(btn) {
      const id = btn.dataset.seat;
      if (booking.seats.includes(id)) {
        booking.seats = booking.seats.filter(s => s !== id);
        btn.classList.remove('selected');
      } else {
        if (booking.seats.length >= booking.pax) {
          toast('You have already claimed ' + booking.pax + ' place(s).');
          return;
        }
        booking.seats.push(id);
        btn.classList.add('selected');
      }
      updateSeatLabel();
    }

    function updateSeatLabel() {
      $('#seatChosen').textContent = booking.seats.length ? ('Seats: ' + booking.seats.join(', ')) : 'No seats chosen';
    }

    $('#toPassenger').onclick = () => {
      if (booking.seats.length !== booking.pax) {
        toast('Select exactly ' + booking.pax + ' seat(s).');
        return;
      }
      closeModal('seatModal');
      const total = booking.fare * booking.pax;
      $('#fareBox').innerHTML = `<div class="flex justify-between"><span>${booking.train.name}</span><span>${booking.seats.join(', ')}</span></div>
        <div class="flex justify-between mt-2 font-cinzel text-[#c9a84c]"><span>Tribute</span><span>${formatKR(total)}</span></div>
        <div class="text-[10px] mt-2 text-[#f3ead8]/40">Includes shrine tax, Bifrost toll, and fox-handling fee.</div>`;
      openModal('passengerModal');
    };

    $('#passengerForm').onsubmit = (e) => {
      e.preventDefault();
      const deity = DEITIES.find(d => d.id === $('#pdeity').value);
      booking.passenger = {
        name: $('#pname').value.trim(),
        email: $('#pemail').value.trim(),
        deity
      };
      const ticket = {
        ref: 'YG-' + seedFrom(booking.passenger.name + Date.now()).toString(36).toUpperCase().slice(0, 8),
        ...booking,
        created: new Date().toISOString()
      };
      const all = JSON.parse(localStorage.getItem('yggdrail') || '[]');
      all.unshift(ticket);
      localStorage.setItem('yggdrail', JSON.stringify(all));
      closeModal('passengerModal');
      paintTicket(ticket);
      openModal('ticketModal');
      renderPassages();
      chime();
      toast(deity.bless);
    };

    function paintTicket(t) {
      const o = stationById(t.origin),
        d = stationById(t.dest);
      $('#tRef').textContent = t.ref;
      $('#tTrain').textContent = t.train.name;
      $('#tTrainJp').textContent = t.train.jp;
      $('#tFrom').textContent = o.name;
      $('#tFromJp').textContent = o.jp;
      $('#tTo').textContent = d.name;
      $('#tToJp').textContent = d.jp;
      $('#tDep').textContent = t.dep;
      $('#tArr').textContent = t.arr;
      $('#tDate').textContent = t.date;
      $('#tClass').textContent = CLASS_META[t.klass].name;
      $('#tSeat').textContent = t.seats.join(', ');
      $('#tFare').textContent = formatKR(t.fare * t.pax);
      $('#tName').textContent = t.passenger.name;
      $('#tDeity').textContent = 'Under ' + t.passenger.deity.name + ' · ' + t.passenger.deity.jp;
    }

    $('#printTicket').onclick = () => window.print();

    function renderPassages() {
      const all = JSON.parse(localStorage.getItem('yggdrail') || '[]');
      if (!all.length) {
        $('#passagesList').innerHTML = `<p class="text-[#f3ead8]/40 font-corm italic">No passages recorded. The ledger is empty as Niflheim at noon.</p>`;
        return;
      }
      $('#passagesList').innerHTML = all.map((t, i) => {
        const o = stationById(t.origin),
          d = stationById(t.dest);
        return `<button data-pi="${i}" class="pass-card gold-border p-5 text-left bg-[#100e0c] hover:bg-[#16110c]">
          <div class="flex justify-between"><span class="font-cinzel text-[#e8d5a3]">${t.train.name}</span><span class="text-xs text-[#c9a84c]">${t.ref}</span></div>
          <div class="text-sm text-[#f3ead8]/70 mt-2">${o.name} → ${d.name}</div>
          <div class="text-xs text-[#f3ead8]/40 mt-1">${t.date} · ${t.dep} · ${t.seats.join(', ')} · ${t.passenger.name}</div>
        </button>`;
      }).join('');
      $$('.pass-card').forEach(b => b.onclick = () => {
        paintTicket(all[+b.dataset.pi]);
        openModal('ticketModal');
      });
    }
    renderPassages();
    $('#clearPassages').onclick = () => {
      localStorage.removeItem('yggdrail');
      renderPassages();
      toast('The ledger is burned. Hel shrugs.');
    };

    $$('[data-close]').forEach(b => b.onclick = () => closeModal(b.dataset.close));
    $$('.modal-bg').forEach(m => m.addEventListener('click', e => {
      if (e.target === m) m.classList.add('hidden');
      document.body.style.overflow = '';
    }));

    const io = new IntersectionObserver(entries => {
      entries.forEach(en => {
        if (en.isIntersecting) en.target.classList.add('in');
      });
    }, {
      threshold: .15
    });
    $$('.reveal').forEach(el => io.observe(el));

    function chime() {
      try {
        const ac = new(window.AudioContext || window.webkitAudioContext)();
        [523, 659, 784].forEach((f, i) => {
          const o = ac.createOscillator();
          const g = ac.createGain();
          o.type = 'sine';
          o.frequency.value = f;
          g.gain.value = 0.0001;
          o.connect(g);
          g.connect(ac.destination);
          const t = ac.currentTime + i * 0.18;
          g.gain.setValueAtTime(0.0001, t);
          g.gain.exponentialRampToValueAtTime(0.08, t + 0.02);
          g.gain.exponentialRampToValueAtTime(0.0001, t + 1.2);
          o.start(t);
          o.stop(t + 1.3);
        });
      } catch (e) {}
    }
  </script>
</body>

</html>