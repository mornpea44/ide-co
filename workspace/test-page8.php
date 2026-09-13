<!DOCTYPE html>
<html lang="en">

<head>
  <?php
  // OPTIONAL PHP MODE — executes only when this file is served as .php (e.g. index.php)
  // by a PHP-enabled host. Served or opened as plain .html, this block is inert.
  $milav_build = gmdate('Ymd\THis\Z');
  ?>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>VANGUARD AIR COMMAND — Modern Airpower Operations Deck</title>
  <meta name="description" content="Interactive operations deck for modern military aviation: fifth-generation fighters, attack helicopters and unmanned systems. Fleet roster, comparative analysis, engine telemetry and doctrine.">
  <!-- BUILD REF: <?php echo isset($milav_build) ? $milav_build : 'static'; ?> -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Black+Ops+One&family=Rajdhani:wght@500;600;700&family=IBM+Plex+Mono:wght@400;500;600&family=IBM+Plex+Sans:wght@400;500;600&display=swap" rel="stylesheet">
  <style>
    /* ============================================================
   VANGUARD AIR COMMAND — single-file operations deck
   palette: gunmetal / avionics amber / radar green / threat red
   ============================================================ */
    :root {
      --bg0: #0a0e12;
      --bg1: #0e141b;
      --bg2: #121a23;
      --bg3: #16202b;
      --line: #26313d;
      --line2: #35424f;
      --ink: #dce4ea;
      --dim: #93a3b1;
      --faint: #5c6c79;
      --amber: #f5a83c;
      --amber2: #ffcf7d;
      --grn: #52e39a;
      --red: #ff5d5d;
      --cyn: #6cd3e8;
      --disp: 'Black Ops One', system-ui, sans-serif;
      --head: 'Rajdhani', system-ui, sans-serif;
      --body: 'IBM Plex Sans', system-ui, sans-serif;
      --mono: 'IBM Plex Mono', ui-monospace, monospace;
      --cham: polygon(14px 0, 100% 0, 100% calc(100% - 14px), calc(100% - 14px) 100%, 0 100%, 0 14px);
      --cham-s: polygon(9px 0, 100% 0, 100% calc(100% - 9px), calc(100% - 9px) 100%, 0 100%, 0 9px);
    }

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box
    }

    html {
      scroll-behavior: smooth
    }

    body {
      background: var(--bg0);
      background-image:
        linear-gradient(rgba(245, 168, 60, .028) 1px, transparent 1px),
        linear-gradient(90deg, rgba(245, 168, 60, .028) 1px, transparent 1px);
      background-size: 52px 52px;
      color: var(--ink);
      font-family: var(--body);
      font-size: 16px;
      line-height: 1.6;
      overflow-x: hidden;
    }

    ::selection {
      background: var(--amber);
      color: #111
    }

    ::-webkit-scrollbar {
      width: 10px
    }

    ::-webkit-scrollbar-track {
      background: var(--bg0)
    }

    ::-webkit-scrollbar-thumb {
      background: var(--line2);
      border: 2px solid var(--bg0)
    }

    a {
      color: inherit;
      text-decoration: none
    }

    img {
      display: block;
      max-width: 100%
    }

    button {
      font-family: inherit;
      cursor: pointer
    }

    .skip {
      position: absolute;
      left: -9999px;
      top: 0;
      background: var(--amber);
      color: #111;
      padding: 10px 16px;
      z-index: 200;
      font-family: var(--head);
      font-weight: 700
    }

    .skip:focus {
      left: 12px;
      top: 12px
    }

    :focus-visible {
      outline: 2px solid var(--amber2);
      outline-offset: 3px
    }

    /* film grain + scanlines */
    .noise {
      position: fixed;
      inset: 0;
      pointer-events: none;
      z-index: 90;
      opacity: .05;
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='140' height='140'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='2'/%3E%3C/filter%3E%3Crect width='140' height='140' filter='url(%23n)' opacity='0.6'/%3E%3C/svg%3E");
    }

    .scan {
      position: fixed;
      inset: 0;
      pointer-events: none;
      z-index: 89;
      opacity: .5;
      background: repeating-linear-gradient(0deg, rgba(255, 255, 255, .016) 0 1px, transparent 1px 3px);
    }

    .wrap {
      max-width: 1280px;
      margin: 0 auto;
      padding: 0 28px
    }

    /* ---------- topbar ---------- */
    .topbar {
      background: #080b0e;
      border-bottom: 1px solid var(--line);
      font-family: var(--mono);
      font-size: 11px;
      letter-spacing: .08em;
      color: var(--dim)
    }

    .topbar .wrap {
      display: flex;
      align-items: center;
      gap: 26px;
      height: 34px
    }

    .topbar .live {
      display: flex;
      align-items: center;
      gap: 8px;
      color: var(--grn);
      font-weight: 600
    }

    .dot {
      width: 7px;
      height: 7px;
      background: var(--grn);
      border-radius: 50%;
      box-shadow: 0 0 8px var(--grn);
      animation: pulse 1.6s infinite
    }

    @keyframes pulse {

      0%,
      100% {
        opacity: 1
      }

      50% {
        opacity: .35
      }
    }

    .topbar .zulu {
      color: var(--amber2)
    }

    .topbar .grow {
      flex: 1
    }

    .topbar .cond {
      color: var(--grn)
    }

    .topbar .sys i {
      font-style: normal;
      color: var(--grn)
    }

    @media(max-width:860px) {
      .topbar .hide-m {
        display: none
      }
    }

    /* ---------- header / nav ---------- */
    header.site {
      position: sticky;
      top: 0;
      z-index: 100;
      background: rgba(10, 14, 18, .94);
      border-bottom: 1px solid var(--line)
    }

    .nav {
      display: flex;
      align-items: center;
      gap: 20px;
      height: 66px
    }

    .brand {
      display: flex;
      align-items: center;
      gap: 14px
    }

    .brand svg {
      width: 44px;
      height: 44px;
      flex: none
    }

    .brand .bt {
      line-height: 1.05
    }

    .brand .bt b {
      font-family: var(--disp);
      font-weight: 400;
      font-size: 19px;
      letter-spacing: .04em;
      display: block
    }

    .brand .bt span {
      font-family: var(--mono);
      font-size: 10px;
      color: var(--amber);
      letter-spacing: .28em
    }

    .links {
      margin-left: auto;
      display: flex;
      align-items: center;
      gap: 6px
    }

    .links a {
      font-family: var(--head);
      font-weight: 700;
      font-size: 14px;
      letter-spacing: .16em;
      color: var(--dim);
      padding: 9px 14px;
      border: 1px solid transparent;
      transition: color .2s, border-color .2s, background .2s;
      position: relative
    }

    .links a:hover {
      color: var(--amber2);
      border-color: var(--line)
    }

    .links a[aria-current="true"] {
      color: var(--amber);
      border-color: var(--amber);
      background: rgba(245, 168, 60, .07)
    }

    .burger {
      display: none;
      margin-left: auto;
      background: none;
      border: 1px solid var(--line2);
      width: 44px;
      height: 44px;
      position: relative
    }

    .burger span {
      position: absolute;
      left: 11px;
      right: 11px;
      height: 2px;
      background: var(--amber);
      transition: transform .25s, top .25s
    }

    .burger span:nth-child(1) {
      top: 15px
    }

    .burger span:nth-child(2) {
      top: 21px
    }

    .burger span:nth-child(3) {
      top: 27px
    }

    body.menu-open .burger span:nth-child(1) {
      top: 21px;
      transform: rotate(45deg)
    }

    body.menu-open .burger span:nth-child(2) {
      opacity: 0
    }

    body.menu-open .burger span:nth-child(3) {
      top: 21px;
      transform: rotate(-45deg)
    }

    @media(max-width:920px) {
      .burger {
        display: block
      }

      .links {
        position: fixed;
        top: 100px;
        left: 0;
        right: 0;
        flex-direction: column;
        align-items: stretch;
        background: var(--bg1);
        border-bottom: 1px solid var(--line);
        padding: 14px 28px 22px;
        gap: 4px;
        display: none
      }

      body.menu-open .links {
        display: flex
      }

      .links a {
        padding: 13px 12px;
        border-bottom: 1px solid var(--line)
      }
    }

    /* ---------- hero / ops deck ---------- */
    .hero {
      position: relative;
      padding: 72px 0 0;
      overflow: hidden
    }

    .hero::before {
      content: "";
      position: absolute;
      inset: 0;
      pointer-events: none;
      background: radial-gradient(1000px 480px at 78% 12%, rgba(245, 168, 60, .09), transparent 62%),
        radial-gradient(700px 420px at 12% 88%, rgba(82, 227, 154, .05), transparent 60%);
    }

    .hero-grid {
      display: grid;
      grid-template-columns: 1.08fr .92fr;
      gap: 56px;
      align-items: start;
      position: relative
    }

    .kicker {
      font-family: var(--mono);
      font-size: 12px;
      letter-spacing: .22em;
      color: var(--amber);
      margin-bottom: 26px;
      display: flex;
      align-items: center;
      gap: 10px
    }

    .kicker::after {
      content: "";
      width: 9px;
      height: 16px;
      background: var(--amber);
      animation: blink 1.1s steps(2) infinite
    }

    @keyframes blink {
      50% {
        opacity: 0
      }
    }

    h1 {
      font-family: var(--disp);
      font-weight: 400;
      font-size: clamp(44px, 6.6vw, 92px);
      line-height: .98;
      letter-spacing: .01em;
      margin-bottom: 26px
    }

    h1 .l2 {
      color: var(--amber)
    }

    .hero p.lead {
      max-width: 52ch;
      color: var(--dim);
      font-size: 17px;
      margin-bottom: 34px
    }

    .hero p.lead strong {
      color: var(--ink);
      font-weight: 600
    }

    .cta-row {
      display: flex;
      gap: 14px;
      flex-wrap: wrap;
      margin-bottom: 44px
    }

    .btn {
      display: inline-flex;
      align-items: center;
      gap: 10px;
      font-family: var(--head);
      font-weight: 700;
      font-size: 14px;
      letter-spacing: .18em;
      padding: 15px 26px;
      clip-path: var(--cham-s);
      border: 1px solid var(--amber);
      background: rgba(245, 168, 60, .08);
      color: var(--amber2);
      transition: background .2s, color .2s, transform .2s
    }

    .btn:hover {
      background: var(--amber);
      color: #101317;
      transform: translateY(-2px)
    }

    .btn.ghost {
      border-color: var(--line2);
      color: var(--dim);
      background: transparent
    }

    .btn.ghost:hover {
      border-color: var(--grn);
      color: var(--grn);
      background: rgba(82, 227, 154, .06)
    }

    .stat-strip {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      border: 1px solid var(--line);
      background: rgba(14, 20, 27, .7)
    }

    .stat {
      padding: 18px 18px 14px;
      border-right: 1px solid var(--line)
    }

    .stat:last-child {
      border-right: 0
    }

    .stat b {
      font-family: var(--mono);
      font-weight: 600;
      font-size: clamp(20px, 2.2vw, 28px);
      color: var(--amber2);
      display: block
    }

    .stat span {
      font-family: var(--head);
      font-size: 11px;
      letter-spacing: .22em;
      color: var(--faint)
    }

    @media(max-width:640px) {
      .stat-strip {
        grid-template-columns: 1fr 1fr
      }

      .stat:nth-child(2) {
        border-right: 0
      }

      .stat {
        border-bottom: 1px solid var(--line)
      }

      .stat:nth-child(n+3) {
        border-bottom: 0
      }
    }

    /* radar panel */
    .radar-panel {
      border: 1px solid var(--line);
      background: linear-gradient(180deg, var(--bg2), var(--bg1));
      clip-path: var(--cham);
      position: relative
    }

    .panel-hd {
      display: flex;
      align-items: center;
      gap: 14px;
      padding: 14px 18px;
      border-bottom: 1px solid var(--line);
      font-family: var(--mono);
      font-size: 11px;
      letter-spacing: .16em;
      color: var(--dim)
    }

    .panel-hd b {
      color: var(--grn);
      font-weight: 600
    }

    .panel-hd .rng {
      margin-left: auto;
      color: var(--faint)
    }

    .radar-wrap {
      position: relative;
      aspect-ratio: 1;
      max-width: 480px;
      margin: 0 auto
    }

    #radarCanvas {
      width: 100%;
      height: 100%;
      display: block
    }

    .radar-ft {
      display: flex;
      gap: 20px;
      padding: 12px 18px;
      border-top: 1px solid var(--line);
      font-family: var(--mono);
      font-size: 10.5px;
      letter-spacing: .14em;
      color: var(--faint)
    }

    .radar-ft i {
      font-style: normal;
      display: inline-flex;
      align-items: center;
      gap: 7px
    }

    .radar-ft i::before {
      content: "";
      width: 8px;
      height: 8px;
      background: currentColor;
      clip-path: polygon(50% 0, 100% 50%, 50% 100%, 0 50%)
    }

    .radar-ft .f1 {
      color: var(--grn)
    }

    .radar-ft .f2 {
      color: var(--red)
    }

    .radar-ft .f3 {
      color: var(--amber)
    }

    .contacts {
      border-top: 1px solid var(--line);
      padding: 12px 18px 16px
    }

    .contacts h4 {
      font-family: var(--head);
      font-size: 12px;
      letter-spacing: .26em;
      color: var(--faint);
      margin-bottom: 10px
    }

    #contactList {
      list-style: none;
      font-family: var(--mono);
      font-size: 12px
    }

    #contactList li {
      display: flex;
      gap: 12px;
      padding: 6px 0;
      border-bottom: 1px dashed var(--line);
      color: var(--dim);
      animation: rowIn .4s ease both
    }

    #contactList li:last-child {
      border-bottom: 0
    }

    #contactList .c-dot {
      width: 8px;
      height: 8px;
      flex: none;
      margin-top: 6px;
      clip-path: polygon(50% 0, 100% 50%, 50% 100%, 0 50%)
    }

    #contactList .c-host {
      color: var(--red)
    }

    #contactList .c-frnd {
      color: var(--grn)
    }

    #contactList .c-unk {
      color: var(--amber)
    }

    @keyframes rowIn {
      from {
        opacity: 0;
        transform: translateX(-8px)
      }

      to {
        opacity: 1;
        transform: none
      }
    }

    /* heading tape */
    .hdg-tape {
      margin-top: 64px;
      border-top: 1px solid var(--line);
      border-bottom: 1px solid var(--line);
      height: 46px;
      position: relative;
      overflow: hidden;
      background: rgba(12, 17, 23, .8)
    }

    .tape-track {
      position: absolute;
      top: 0;
      left: 0;
      height: 100%;
      width: 5760px;
      animation: tapeSlide 90s linear infinite
    }

    @keyframes tapeSlide {
      to {
        transform: translateX(-2880px)
      }
    }

    .tk {
      position: absolute;
      bottom: 0;
      width: 1px;
      background: var(--line2)
    }

    .tk.min {
      height: 9px
    }

    .tk.maj {
      height: 16px;
      background: var(--faint)
    }

    .tk-lb {
      position: absolute;
      bottom: 18px;
      transform: translateX(-50%);
      font-family: var(--mono);
      font-size: 10px;
      color: var(--dim);
      letter-spacing: .1em
    }

    .tape-caret {
      position: absolute;
      left: 50%;
      top: 0;
      transform: translateX(-50%);
      z-index: 2;
      pointer-events: none
    }

    .tape-caret::before {
      content: "";
      position: absolute;
      left: -1px;
      top: 0;
      width: 2px;
      height: 100%;
      background: var(--amber)
    }

    .tape-caret::after {
      content: "";
      position: absolute;
      left: -7px;
      top: -1px;
      border: 7px solid transparent;
      border-top-color: var(--amber)
    }

    /* ticker */
    .ticker {
      border-bottom: 1px solid var(--line);
      background: #0c1116;
      overflow: hidden;
      position: relative
    }

    .ticker::before,
    .ticker::after {
      content: "";
      position: absolute;
      top: 0;
      bottom: 0;
      width: 90px;
      z-index: 2;
      pointer-events: none
    }

    .ticker::before {
      left: 0;
      background: linear-gradient(90deg, #0c1116, transparent)
    }

    .ticker::after {
      right: 0;
      background: linear-gradient(-90deg, #0c1116, transparent)
    }

    .ticker-track {
      display: inline-flex;
      white-space: nowrap;
      padding: 11px 0;
      font-family: var(--mono);
      font-size: 12px;
      letter-spacing: .1em;
      color: var(--dim);
      animation: tick 60s linear infinite;
      width: max-content
    }

    .ticker:hover .ticker-track {
      animation-play-state: paused
    }

    .ticker-track b {
      color: var(--amber);
      font-weight: 600
    }

    .ticker-track em {
      font-style: normal;
      color: var(--faint);
      margin: 0 22px
    }

    @keyframes tick {
      to {
        transform: translateX(-50%)
      }
    }

    /* ---------- sections ---------- */
    .sec {
      padding: 96px 0;
      position: relative
    }

    .sec-head {
      display: flex;
      align-items: baseline;
      gap: 22px;
      margin-bottom: 16px;
      flex-wrap: wrap
    }

    .sec-head .idx {
      font-family: var(--mono);
      font-size: 13px;
      color: var(--faint);
      letter-spacing: .2em
    }

    .sec-head h2 {
      font-family: var(--disp);
      font-weight: 400;
      font-size: clamp(30px, 4vw, 52px);
      letter-spacing: .02em
    }

    .sec-head .rule {
      flex: 1;
      height: 1px;
      background: var(--line);
      min-width: 60px
    }

    .sec-meta {
      font-family: var(--mono);
      font-size: 11.5px;
      letter-spacing: .14em;
      color: var(--faint)
    }

    .sec-sub {
      color: var(--dim);
      max-width: 70ch;
      margin-bottom: 44px
    }

    /* reveal machinery */
    .rv {
      opacity: 0;
      transform: translateY(26px);
      transition: opacity .7s ease, transform .7s ease
    }

    .rv.in {
      opacity: 1;
      transform: none
    }

    .rv.d1 {
      transition-delay: .08s
    }

    .rv.d2 {
      transition-delay: .16s
    }

    .rv.d3 {
      transition-delay: .24s
    }

    /* ---------- fleet ---------- */
    .chips {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
      margin-bottom: 38px
    }

    .chip {
      font-family: var(--head);
      font-weight: 700;
      font-size: 12.5px;
      letter-spacing: .16em;
      padding: 10px 18px;
      background: transparent;
      border: 1px solid var(--line2);
      color: var(--dim);
      clip-path: var(--cham-s);
      transition: all .2s
    }

    .chip small {
      color: var(--faint);
      font-family: var(--mono);
      margin-left: 8px
    }

    .chip:hover {
      border-color: var(--amber);
      color: var(--amber2)
    }

    .chip.on {
      background: var(--amber);
      border-color: var(--amber);
      color: #101317
    }

    .chip.on small {
      color: #101317
    }

    .fleet-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(295px, 1fr));
      gap: 22px
    }

    .card {
      border: 1px solid var(--line);
      background: linear-gradient(180deg, var(--bg2), var(--bg1));
      clip-path: var(--cham);
      display: flex;
      flex-direction: column;
      transition: transform .3s ease, border-color .3s, filter .3s
    }

    .card:hover {
      transform: translateY(-5px);
      border-color: var(--amber);
      filter: drop-shadow(0 14px 26px rgba(245, 168, 60, .13))
    }

    .card.hidden {
      display: none
    }

    .card-media {
      position: relative;
      overflow: hidden;
      aspect-ratio: 16/10;
      background: #0b0f13
    }

    .card-media img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      filter: grayscale(1) contrast(1.1) brightness(.88);
      transition: transform 1.4s ease
    }

    .card:hover .card-media img {
      transform: scale(1.07)
    }

    .card-media::after {
      content: "";
      position: absolute;
      inset: 0;
      background: linear-gradient(115deg, rgba(82, 227, 154, .14), transparent 45%, rgba(245, 168, 60, .12));
      pointer-events: none
    }

    .card-media .tag {
      position: absolute;
      top: 12px;
      left: 12px;
      z-index: 2;
      font-family: var(--mono);
      font-size: 10.5px;
      letter-spacing: .18em;
      background: rgba(8, 11, 14, .85);
      border: 1px solid var(--amber);
      color: var(--amber);
      padding: 5px 10px
    }

    .card-media .yr {
      position: absolute;
      bottom: 12px;
      right: 12px;
      z-index: 2;
      font-family: var(--mono);
      font-size: 10.5px;
      color: var(--dim);
      background: rgba(8, 11, 14, .8);
      padding: 4px 9px;
      letter-spacing: .14em
    }

    .card-body {
      padding: 20px 20px 18px;
      display: flex;
      flex-direction: column;
      flex: 1
    }

    .card-top {
      display: flex;
      align-items: baseline;
      gap: 12px;
      margin-bottom: 6px
    }

    .card-top h3 {
      font-family: var(--head);
      font-weight: 700;
      font-size: 21px;
      letter-spacing: .06em
    }

    .card-top .flag {
      margin-left: auto;
      font-family: var(--mono);
      font-size: 10px;
      letter-spacing: .2em;
      color: var(--faint);
      border: 1px solid var(--line2);
      padding: 3px 8px
    }

    .card-maker {
      font-family: var(--mono);
      font-size: 11px;
      color: var(--faint);
      letter-spacing: .08em;
      margin-bottom: 10px
    }

    .card-desc {
      font-size: 13.5px;
      color: var(--dim);
      margin-bottom: 16px;
      flex: 1
    }

    .specs {
      font-family: var(--mono);
      font-size: 11.5px;
      letter-spacing: .06em;
      color: var(--amber2);
      border: 1px dashed var(--line2);
      padding: 9px 12px;
      margin-bottom: 14px
    }

    .bars {
      display: grid;
      gap: 7px;
      margin-bottom: 16px
    }

    .bar {
      display: grid;
      grid-template-columns: 44px 1fr 30px;
      align-items: center;
      gap: 10px;
      font-family: var(--mono);
      font-size: 9.5px;
      color: var(--faint);
      letter-spacing: .1em
    }

    .bar .tr {
      height: 4px;
      background: var(--line);
      position: relative;
      overflow: hidden
    }

    .bar .tr i {
      position: absolute;
      inset: 0;
      right: auto;
      background: linear-gradient(90deg, var(--grn), var(--amber));
      width: 0;
      transition: width 1.1s cubic-bezier(.2, .8, .2, 1)
    }

    .card.in .bar .tr i {
      width: var(--w)
    }

    .card-foot {
      display: flex;
      align-items: center;
      border-top: 1px solid var(--line);
      padding-top: 14px;
      gap: 10px
    }

    .status {
      font-family: var(--mono);
      font-size: 10.5px;
      letter-spacing: .12em;
      display: flex;
      align-items: center;
      gap: 8px
    }

    .status::before {
      content: "";
      width: 7px;
      height: 7px;
      border-radius: 50%;
      background: currentColor;
      box-shadow: 0 0 8px currentColor
    }

    .status.grn {
      color: var(--grn)
    }

    .status.amber {
      color: var(--amber)
    }

    .status.cyn {
      color: var(--cyn)
    }

    .status.dim {
      color: var(--faint)
    }

    .cmp-btn {
      margin-left: auto;
      font-family: var(--head);
      font-weight: 700;
      font-size: 11.5px;
      letter-spacing: .16em;
      background: none;
      border: 1px solid var(--line2);
      color: var(--dim);
      padding: 8px 14px;
      transition: all .2s
    }

    .cmp-btn:hover {
      border-color: var(--amber);
      color: var(--amber);
      background: rgba(245, 168, 60, .08)
    }

    @media(min-width:880px) {
      .card.feat {
        grid-column: span 2
      }

      .card.feat .card-media {
        aspect-ratio: 21/9
      }
    }

    /* ---------- compare ---------- */
    .compare-panel {
      border: 1px solid var(--line);
      background: linear-gradient(180deg, var(--bg2), var(--bg1));
      clip-path: var(--cham);
      padding: 30px
    }

    .duel {
      display: grid;
      grid-template-columns: 250px 1fr 250px;
      gap: 30px;
      align-items: start
    }

    .duel-side select {
      width: 100%;
      appearance: none;
      -webkit-appearance: none;
      font-family: var(--head);
      font-weight: 700;
      font-size: 15px;
      letter-spacing: .08em;
      background: var(--bg0) url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%23f5a83c'/%3E%3C/svg%3E") no-repeat right 14px center;
      border: 1px solid var(--line2);
      color: var(--ink);
      padding: 12px 36px 12px 14px;
      cursor: pointer;
      transition: border-color .2s
    }

    .duel-side select:hover {
      border-color: var(--amber)
    }

    .mini {
      margin-top: 16px;
      border: 1px solid var(--line)
    }

    .mini img {
      width: 100%;
      height: 130px;
      object-fit: cover;
      filter: grayscale(1) contrast(1.1) brightness(.85)
    }

    .mini .mi {
      padding: 12px 14px
    }

    .mini .mi b {
      font-family: var(--head);
      font-weight: 700;
      font-size: 16px;
      letter-spacing: .06em;
      display: block
    }

    .mini .mi span {
      font-family: var(--mono);
      font-size: 10.5px;
      color: var(--faint);
      letter-spacing: .12em
    }

    .duel-mid {
      padding-top: 6px
    }

    .m-row {
      display: grid;
      grid-template-columns: 1fr 168px 1fr;
      gap: 12px;
      align-items: center;
      padding: 9px 0;
      border-bottom: 1px dashed var(--line)
    }

    .m-row:last-child {
      border-bottom: 0
    }

    .m-bar {
      height: 9px;
      background: var(--line);
      position: relative
    }

    .m-bar.a i {
      position: absolute;
      right: 0;
      top: 0;
      bottom: 0;
      background: var(--cyn);
      width: 0;
      transition: width .8s cubic-bezier(.2, .8, .2, 1)
    }

    .m-bar.b i {
      position: absolute;
      left: 0;
      top: 0;
      bottom: 0;
      background: var(--amber);
      width: 0;
      transition: width .8s cubic-bezier(.2, .8, .2, 1)
    }

    .m-row.win-a .m-bar.a i {
      box-shadow: 0 0 10px var(--cyn)
    }

    .m-row.win-b .m-bar.b i {
      box-shadow: 0 0 10px var(--amber)
    }

    .m-lab {
      text-align: center;
      line-height: 1.25
    }

    .m-lab b {
      font-family: var(--head);
      font-weight: 700;
      font-size: 12.5px;
      letter-spacing: .16em;
      display: block
    }

    .m-lab span {
      font-family: var(--mono);
      font-size: 10px;
      color: var(--faint);
      display: block;
      margin-top: 2px
    }

    .m-lab .pips {
      display: flex;
      justify-content: center;
      gap: 6px;
      margin-top: 4px
    }

    .pip {
      width: 8px;
      height: 8px;
      border: 1px solid var(--line2);
      transform: rotate(45deg)
    }

    .pip.a {
      background: var(--cyn);
      border-color: var(--cyn)
    }

    .pip.b {
      background: var(--amber);
      border-color: var(--amber)
    }

    .verdict {
      margin-top: 28px;
      border: 1px solid var(--line2);
      background: rgba(245, 168, 60, .06);
      padding: 16px 22px;
      display: flex;
      align-items: center;
      gap: 16px;
      flex-wrap: wrap
    }

    .verdict b {
      font-family: var(--disp);
      font-weight: 400;
      font-size: 19px;
      color: var(--amber2);
      letter-spacing: .03em
    }

    .verdict span {
      font-family: var(--mono);
      font-size: 11.5px;
      color: var(--dim);
      letter-spacing: .1em
    }

    .compare-note {
      font-family: var(--mono);
      font-size: 10.5px;
      color: var(--faint);
      margin-top: 14px;
      letter-spacing: .08em
    }

    @media(max-width:960px) {
      .duel {
        grid-template-columns: 1fr;
        gap: 20px
      }

      .duel-mid {
        order: 3
      }
    }

    .compare-panel.flash {
      animation: flash .7s ease
    }

    @keyframes flash {
      0% {
        border-color: var(--amber);
        filter: drop-shadow(0 0 24px rgba(245, 168, 60, .4))
      }

      100% {
        border-color: var(--line)
      }
    }

    /* ---------- telemetry ---------- */
    .tele-grid {
      display: grid;
      grid-template-columns: 1fr 1.15fr;
      gap: 26px
    }

    .console {
      border: 1px solid var(--line);
      background: linear-gradient(180deg, var(--bg2), var(--bg1));
      clip-path: var(--cham);
      padding: 26px
    }

    .console h3 {
      font-family: var(--head);
      font-weight: 700;
      font-size: 14px;
      letter-spacing: .24em;
      color: var(--faint);
      margin-bottom: 20px;
      display: flex;
      justify-content: space-between
    }

    .console h3 em {
      font-style: normal;
      color: var(--amber)
    }

    .throttle-lab {
      display: flex;
      justify-content: space-between;
      font-family: var(--mono);
      font-size: 11px;
      color: var(--dim);
      letter-spacing: .14em;
      margin-bottom: 10px
    }

    .throttle-lab b {
      color: var(--amber2)
    }

    input[type=range].thr {
      -webkit-appearance: none;
      width: 100%;
      height: 34px;
      background: transparent;
      cursor: pointer
    }

    input[type=range].thr::-webkit-slider-runnable-track {
      height: 12px;
      border: 1px solid var(--line2);
      background: linear-gradient(90deg, var(--grn) 0 55%, var(--amber) 55% 82%, var(--red) 82% 100%);
      background-image: repeating-linear-gradient(90deg, rgba(0, 0, 0, .35) 0 1px, transparent 1px 10%), linear-gradient(90deg, var(--grn) 0 55%, var(--amber) 55% 82%, var(--red) 82% 100%)
    }

    input[type=range].thr::-moz-range-track {
      height: 12px;
      border: 1px solid var(--line2);
      background: linear-gradient(90deg, var(--grn) 0 55%, var(--amber) 55% 82%, var(--red) 82% 100%)
    }

    input[type=range].thr::-webkit-slider-thumb {
      -webkit-appearance: none;
      width: 30px;
      height: 34px;
      margin-top: -12px;
      background: var(--bg3);
      border: 2px solid var(--amber);
      clip-path: polygon(0 0, 100% 0, 100% 70%, 50% 100%, 0 70%)
    }

    input[type=range].thr::-moz-range-thumb {
      width: 30px;
      height: 34px;
      background: var(--bg3);
      border: 2px solid var(--amber);
      border-radius: 0
    }

    .ctrl-row {
      display: flex;
      gap: 14px;
      margin-top: 26px;
      flex-wrap: wrap;
      align-items: center
    }

    .ab-btn {
      font-family: var(--disp);
      font-size: 15px;
      letter-spacing: .06em;
      padding: 12px 26px;
      background: var(--bg0);
      border: 2px solid var(--red);
      color: var(--red);
      transition: all .2s
    }

    .ab-btn[aria-pressed="true"] {
      background: var(--red);
      color: #0c0f12;
      box-shadow: 0 0 22px rgba(255, 93, 93, .5)
    }

    .indexer {
      display: flex;
      gap: 8px;
      margin-left: auto
    }

    .lamp {
      width: 38px;
      height: 14px;
      background: #1a222b;
      border: 1px solid var(--line);
      opacity: .28;
      transition: opacity .2s, box-shadow .2s
    }

    .lamp.grn {
      background: var(--grn)
    }

    .lamp.amber {
      background: var(--amber)
    }

    .lamp.red {
      background: var(--red)
    }

    .lamp.on {
      opacity: 1
    }

    .lamp.red.on {
      box-shadow: 0 0 14px var(--red)
    }

    .lamp.amber.on {
      box-shadow: 0 0 14px var(--amber)
    }

    .lamp.grn.on {
      box-shadow: 0 0 14px var(--grn)
    }

    .lamp-lab {
      display: flex;
      gap: 8px;
      margin: 8px 0 0 auto;
      justify-content: flex-end;
      width: fit-content;
      margin-left: auto
    }

    .lamp-lab span {
      width: 38px;
      text-align: center;
      font-family: var(--mono);
      font-size: 8.5px;
      color: var(--faint);
      letter-spacing: .06em
    }

    .gauges {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 18px;
      margin-bottom: 20px
    }

    .gauge {
      border: 1px solid var(--line);
      background: #0b1015;
      padding: 10px
    }

    .gauge canvas {
      width: 100%;
      display: block
    }

    .gauge .g-lab {
      text-align: center;
      font-family: var(--head);
      font-size: 11px;
      letter-spacing: .26em;
      color: var(--faint);
      padding-bottom: 8px
    }

    .readouts {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 12px
    }

    .ro {
      border: 1px solid var(--line);
      background: #0b1015;
      padding: 12px 14px
    }

    .ro b {
      font-family: var(--mono);
      font-weight: 600;
      font-size: 19px;
      color: var(--grn);
      display: block
    }

    .ro b.hot {
      color: var(--red)
    }

    .ro span {
      font-family: var(--head);
      font-size: 10px;
      letter-spacing: .22em;
      color: var(--faint)
    }

    @media(max-width:940px) {
      .tele-grid {
        grid-template-columns: 1fr
      }

      .readouts {
        grid-template-columns: 1fr 1fr
      }
    }

    /* ---------- timeline ---------- */
    .tl {
      display: grid;
      grid-template-columns: 320px 1fr;
      gap: 60px
    }

    .tl-left {
      position: sticky;
      top: 130px;
      align-self: start
    }

    .tl-era {
      font-family: var(--mono);
      font-size: 11.5px;
      letter-spacing: .3em;
      color: var(--amber);
      margin-bottom: 8px
    }

    .tl-year {
      font-family: var(--disp);
      font-size: clamp(64px, 7vw, 110px);
      line-height: 1;
      color: var(--ink)
    }

    .tl-year.flip {
      animation: yearFlip .45s ease
    }

    @keyframes yearFlip {
      0% {
        opacity: 0;
        transform: translateY(14px)
      }

      100% {
        opacity: 1;
        transform: none
      }
    }

    .tl-left p {
      color: var(--dim);
      font-size: 14px;
      margin-top: 18px;
      max-width: 30ch
    }

    .tl-right {
      border-left: 1px solid var(--line);
      padding-left: 44px;
      display: flex;
      flex-direction: column;
      gap: 56px
    }

    .tl-item {
      position: relative
    }

    .tl-item::before {
      content: "";
      position: absolute;
      left: -50px;
      top: 8px;
      width: 11px;
      height: 11px;
      background: var(--bg0);
      border: 2px solid var(--amber);
      transform: rotate(45deg)
    }

    .tl-item .tl-yr {
      font-family: var(--mono);
      font-size: 12px;
      letter-spacing: .2em;
      color: var(--amber);
      margin-bottom: 6px
    }

    .tl-item h3 {
      font-family: var(--head);
      font-weight: 700;
      font-size: 22px;
      letter-spacing: .05em;
      margin-bottom: 8px
    }

    .tl-item p {
      color: var(--dim);
      font-size: 14.5px;
      max-width: 60ch
    }

    .tl-item img {
      margin-top: 16px;
      border: 1px solid var(--line);
      filter: grayscale(1) contrast(1.08) brightness(.9);
      max-width: 440px;
      width: 100%
    }

    @media(max-width:920px) {
      .tl {
        grid-template-columns: 1fr;
        gap: 30px
      }

      .tl-left {
        position: static;
        display: flex;
        align-items: baseline;
        gap: 18px;
        flex-wrap: wrap
      }

      .tl-left p {
        display: none
      }

      .tl-right {
        padding-left: 30px
      }

      .tl-item::before {
        left: -36px
      }
    }

    /* ---------- doctrine ---------- */
    .doc-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 44px;
      align-items: start
    }

    .principle {
      display: grid;
      grid-template-columns: 74px 1fr;
      gap: 18px;
      padding: 22px 0;
      border-bottom: 1px solid var(--line)
    }

    .principle:first-child {
      padding-top: 0
    }

    .principle .pn {
      font-family: var(--disp);
      font-size: 34px;
      color: var(--amber);
      opacity: .85;
      line-height: 1
    }

    .principle h3 {
      font-family: var(--head);
      font-weight: 700;
      font-size: 18px;
      letter-spacing: .12em;
      margin-bottom: 5px
    }

    .principle p {
      color: var(--dim);
      font-size: 14px
    }

    .sortie {
      border: 1px solid var(--line);
      background: #070a0d;
      clip-path: var(--cham);
      position: sticky;
      top: 130px
    }

    .sortie .panel-hd b {
      color: var(--amber)
    }

    #sortieOut {
      font-family: var(--mono);
      font-size: 12.5px;
      line-height: 1.85;
      color: var(--grn);
      padding: 22px;
      min-height: 270px;
      white-space: pre-wrap;
      text-shadow: 0 0 8px rgba(82, 227, 154, .35)
    }

    #sortieOut::after {
      content: "█";
      animation: blink 1s steps(2) infinite;
      color: var(--grn)
    }

    .sortie-foot {
      padding: 0 22px 22px;
      display: flex;
      gap: 14px;
      align-items: center;
      flex-wrap: wrap
    }

    .sortie-foot .btn {
      width: auto
    }

    .threat {
      font-family: var(--mono);
      font-size: 11px;
      letter-spacing: .16em;
      padding: 6px 12px;
      border: 1px solid var(--line2);
      color: var(--dim)
    }

    .threat.low {
      color: var(--grn);
      border-color: var(--grn)
    }

    .threat.mod {
      color: var(--amber);
      border-color: var(--amber)
    }

    .threat.high {
      color: var(--red);
      border-color: var(--red)
    }

    @media(max-width:920px) {
      .doc-grid {
        grid-template-columns: 1fr
      }

      .sortie {
        position: static
      }
    }

    /* ---------- footer ---------- */
    footer {
      border-top: 1px solid var(--line);
      background: #080b0e;
      padding: 70px 0 34px;
      margin-top: 40px
    }

    .fmark {
      font-family: var(--disp);
      font-size: clamp(34px, 7.4vw, 104px);
      line-height: 1;
      color: transparent;
      -webkit-text-stroke: 1px var(--line2);
      letter-spacing: .02em;
      margin-bottom: 48px;
      user-select: none
    }

    .fcols {
      display: grid;
      grid-template-columns: 1.2fr 1fr 1fr 1fr;
      gap: 36px;
      margin-bottom: 52px
    }

    .fcols h4 {
      font-family: var(--head);
      font-weight: 700;
      font-size: 12px;
      letter-spacing: .28em;
      color: var(--faint);
      margin-bottom: 16px
    }

    .fcols li {
      list-style: none;
      margin-bottom: 9px
    }

    .fcols a,
    .fcols li span {
      font-family: var(--mono);
      font-size: 12.5px;
      color: var(--dim);
      transition: color .2s
    }

    .fcols a:hover {
      color: var(--amber2)
    }

    .fcols .colo {
      color: var(--dim);
      font-size: 13px;
      max-width: 34ch
    }

    .disc {
      border-top: 1px solid var(--line);
      padding-top: 26px;
      display: flex;
      justify-content: space-between;
      gap: 20px;
      flex-wrap: wrap;
      align-items: center;
      font-family: var(--mono);
      font-size: 11px;
      letter-spacing: .1em;
      color: var(--faint)
    }

    #toTop {
      background: none;
      border: 1px solid var(--line2);
      color: var(--amber);
      font-family: var(--head);
      font-weight: 700;
      font-size: 12px;
      letter-spacing: .2em;
      padding: 11px 18px;
      transition: all .2s
    }

    #toTop:hover {
      border-color: var(--amber);
      background: rgba(245, 168, 60, .08)
    }

    @media(max-width:880px) {
      .fcols {
        grid-template-columns: 1fr 1fr
      }
    }

    @media(max-width:560px) {
      .fcols {
        grid-template-columns: 1fr
      }
    }

    @media(max-width:980px) {
      .hero-grid {
        grid-template-columns: 1fr;
        gap: 44px
      }

      .hero {
        padding-top: 52px
      }
    }

    @media(max-width:640px) {
      .sec {
        padding: 70px 0
      }

      .wrap {
        padding: 0 18px
      }

      .compare-panel {
        padding: 20px
      }
    }

    /* reduced motion */
    @media (prefers-reduced-motion: reduce) {
      html {
        scroll-behavior: auto
      }

      *,
      *::before,
      *::after {
        animation-duration: .01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: .01ms !important
      }

      .tape-track,
      .ticker-track {
        animation: none;
        transform: none
      }

      .card:hover .card-media img {
        transform: none
      }

      .rv {
        opacity: 1;
        transform: none
      }

      .card .bar .tr i {
        width: var(--w)
      }
    }
  </style>
</head>

<body id="top">
  <a class="skip" href="#fleet">Skip to fleet roster</a>
  <div class="noise" aria-hidden="true"></div>
  <div class="scan" aria-hidden="true"></div>

  <!-- ======== TOPBAR ======== -->
  <div class="topbar" role="status" aria-label="Station status">
    <div class="wrap">
      <span class="live"><span class="dot"></span>UPLINK LIVE</span>
      <span class="zulu">ZULU <span id="clockUTC">--:--:--</span>Z</span>
      <span class="hide-m" id="dtg">DTG --</span>
      <span class="hide-m">GRID 38T LM 4417</span>
      <span class="grow"></span>
      <span class="cond hide-m">READINESS ▸ CONDITION III</span>
      <span class="sys hide-m">SYS <i>●</i> COM <i>●</i> NAV <i>●</i></span>
    </div>
  </div>

  <!-- ======== NAV ======== -->
  <header class="site">
    <div class="wrap nav">
      <a class="brand" href="#top" aria-label="Vanguard Air Command home">
        <svg viewBox="0 0 48 48" aria-hidden="true">
          <circle cx="24" cy="24" r="22" fill="none" stroke="#f5a83c" stroke-width="2" />
          <path d="M24 7 L39 37 L24 29 L9 37 Z" fill="#f5a83c" />
          <path d="M17 40 H31" stroke="#52e39a" stroke-width="2.5" />
        </svg>
        <span class="bt"><b>VANGUARD AIR COMMAND</b><span>7TH EXPEDITIONARY WING</span></span>
      </a>
      <button class="burger" aria-label="Toggle navigation" aria-expanded="false"><span></span><span></span><span></span></button>
      <nav class="links" aria-label="Primary">
        <a href="#fleet">FLEET</a>
        <a href="#compare">ANALYSIS</a>
        <a href="#telemetry">TELEMETRY</a>
        <a href="#timeline">TIMELINE</a>
        <a href="#doctrine">DOCTRINE</a>
      </nav>
    </div>
  </header>

  <!-- ======== OPS DECK / HERO ======== -->
  <section class="hero" aria-label="Operations deck">
    <div class="wrap hero-grid">
      <div>
        <p class="kicker">OPS DECK ▸ SECTOR WATCH ACTIVE</p>
        <h1><span class="scr" data-text="AIR SUPERIORITY">AIR SUPERIORITY</span><br>
          <span class="l2 scr" data-text="IS A SYSTEM.">IS A SYSTEM.</span>
        </h1>
        <p class="lead">A live operations console for <strong>modern airpower</strong> — fifth-generation fighters, attack helicopters and unmanned systems.
          Track the fleet, run airframe-versus-airframe analysis, spool up engine telemetry and build the tasking order. From radar picket to wheels-up.</p>
        <div class="cta-row">
          <a class="btn" href="#fleet">OPEN FLEET ROSTER ▸</a>
          <a class="btn ghost" href="#compare">RUN COMPARISON ◈</a>
        </div>
        <div class="stat-strip rv">
          <div class="stat"><b class="count" data-to="11">0</b><span>AIRFRAMES TRACKED</span></div>
          <div class="stat"><b><span class="count" data-to="2.25" data-dec="2">0</span>M</b><span>TOP SPEED · MACH</span></div>
          <div class="stat"><b><span class="count" data-to="65">0</span>K</b><span>MAX CEILING · FT</span></div>
          <div class="stat"><b class="count" data-to="214">0</b><span>SORTIES THIS WEEK</span></div>
        </div>
      </div>

      <div class="radar-panel rv d1" aria-label="Radar scope simulation">
        <div class="panel-hd"><b>PHASED ARRAY</b> ▸ SECTOR SWEEP <span class="rng">RNG 120 NM</span></div>
        <div class="radar-wrap"><canvas id="radarCanvas" role="img" aria-label="Animated radar sweep with simulated contacts"></canvas></div>
        <div class="radar-ft">
          <i class="f1">FRIENDLY</i><i class="f2">HOSTILE</i><i class="f3">UNKNOWN</i>
        </div>
        <div class="contacts">
          <h4>TRACK FILE ▸ LAST 4</h4>
          <ul id="contactList"></ul>
        </div>
      </div>
    </div>

    <!-- heading tape -->
    <div class="hdg-tape" aria-hidden="true">
      <div class="tape-track" id="tapeTrack"></div>
      <div class="tape-caret"></div>
    </div>
  </section>

  <!-- ======== NOTAM TICKER ======== -->
  <div class="ticker" aria-label="Station bulletin">
    <div class="ticker-track" id="tickerTrack"></div>
  </div>

  <!-- ======== 01 FLEET ======== -->
  <section class="sec" id="fleet" aria-label="Fleet roster">
    <div class="wrap">
      <div class="sec-head rv">
        <span class="idx">01 /</span>
        <h2 class="scr" data-text="FLEET ROSTER">FLEET ROSTER</h2>
        <span class="rule"></span>
        <span class="sec-meta" id="fleetMeta">-- AIRFRAMES · 4 CLASSES</span>
      </div>
      <p class="sec-sub rv d1">Every airframe assigned to the wing, from air-dominance stealth fighters to long-endurance unmanned systems. Filter by mission class, or push an aircraft straight into the comparison deck.</p>
      <div class="chips rv d2" id="chips" role="tablist" aria-label="Filter fleet by class"></div>
      <div class="fleet-grid" id="fleetGrid"></div>
    </div>
  </section>

  <!-- ======== 02 COMPARE ======== -->
  <section class="sec" id="compare" aria-label="Combat analysis">
    <div class="wrap">
      <div class="sec-head rv">
        <span class="idx">02 /</span>
        <h2 class="scr" data-text="COMBAT ANALYSIS">COMBAT ANALYSIS</h2>
        <span class="rule"></span>
        <span class="sec-meta">DUEL SIMULATOR ▸ 6 AXES</span>
      </div>
      <p class="sec-sub rv d1">Select two airframes. Envelope scores are normalized 0–100 across six axes — the side that owns each axis lights its pip. Aggregate edge decides the merge.</p>
      <div class="compare-panel rv d2" id="comparePanel">
        <div class="duel">
          <div class="duel-side">
            <label for="selA" class="sec-meta" style="display:block;margin-bottom:8px">BLUE AIR</label>
            <select id="selA" aria-label="Select blue airframe"></select>
            <div class="mini" id="miniA"></div>
          </div>
          <div class="duel-mid" id="duelMid"></div>
          <div class="duel-side">
            <label for="selB" class="sec-meta" style="display:block;margin-bottom:8px">RED AIR</label>
            <select id="selB" aria-label="Select red airframe"></select>
            <div class="mini" id="miniB"></div>
          </div>
        </div>
        <div class="verdict"><b id="verdictName">—</b><span id="verdictScore">—</span></div>
        <p class="compare-note">NORMALIZED TRAINING-AID FIGURES. EDITORIAL SIMULATION — NOT ENGINEERING OR PROCUREMENT DATA.</p>
      </div>
    </div>
  </section>

  <!-- ======== 03 TELEMETRY ======== -->
  <section class="sec" id="telemetry" aria-label="Engine telemetry">
    <div class="wrap">
      <div class="sec-head rv">
        <span class="idx">03 /</span>
        <h2 class="scr" data-text="ENGINE TELEMETRY">ENGINE TELEMETRY</h2>
        <span class="rule"></span>
        <span class="sec-meta">POWERPLANT ▸ F135-CLASS SIM</span>
      </div>
      <p class="sec-sub rv d1">Advance the throttle and watch the engine instruments respond — core speed, turbine temperature, fuel flow and nozzle position. Light the afterburner and hold the line.</p>
      <div class="tele-grid">
        <div class="console rv d1">
          <h3>THRUST CONTROL <em>QUADRANT</em></h3>
          <div class="throttle-lab"><span>THROTTLE POSITION</span><b id="thrVal">IDLE 08%</b></div>
          <input type="range" class="thr" id="thr" min="0" max="100" value="8" aria-label="Throttle position">
          <div class="ctrl-row">
            <button class="ab-btn" id="abBtn" type="button" aria-pressed="false">A/B</button>
            <div class="indexer" aria-hidden="true">
              <div class="lamp red" id="lampSlow"></div>
              <div class="lamp amber" id="lampOn"></div>
              <div class="lamp grn" id="lampFast"></div>
            </div>
          </div>
          <div class="lamp-lab" aria-hidden="true"><span>SLOW</span><span>ON&nbsp;SPD</span><span>FAST</span></div>
          <div class="ctrl-row" style="margin-top:22px">
            <span class="threat low" id="lampAB" style="border-color:var(--red);color:var(--red);opacity:.35">A/B LIT</span>
            <span class="threat mod" id="lampCaut" style="opacity:.35">EGT CAUTION</span>
          </div>
        </div>
        <div class="console rv d2">
          <h3>ENGINE INSTRUMENTS <em>CHANNEL A</em></h3>
          <div class="gauges">
            <div class="gauge"><canvas id="gN1" height="200"></canvas>
              <div class="g-lab">N1 CORE SPEED · %</div>
            </div>
            <div class="gauge"><canvas id="gEgt" height="200"></canvas>
              <div class="g-lab">EGT · °C</div>
            </div>
          </div>
          <div class="readouts">
            <div class="ro"><b id="rFF">0.0</b><span>FUEL FLOW KG/S</span></div>
            <div class="ro"><b id="rTHR">0</b><span>THRUST KN</span></div>
            <div class="ro"><b id="rNOZ">0</b><span>NOZZLE %</span></div>
            <div class="ro"><b id="rMODE">IDLE</b><span>THRUST MODE</span></div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- ======== 04 TIMELINE ======== -->
  <section class="sec" id="timeline" aria-label="Program timeline">
    <div class="wrap">
      <div class="sec-head rv">
        <span class="idx">04 /</span>
        <h2 class="scr" data-text="PROGRAM TIMELINE">PROGRAM TIMELINE</h2>
        <span class="rule"></span>
        <span class="sec-meta">1978 ▸ 2035</span>
      </div>
      <div class="tl">
        <div class="tl-left rv">
          <div>
            <div class="tl-era" id="tlEra">THE DIGITAL SHIFT</div>
            <div class="tl-year" id="tlYear">1978</div>
          </div>
          <p>From the first relaxed-stability fly-by-wire fighters to networked autonomy — half a century of vertical escalation.</p>
        </div>
        <div class="tl-right">
          <div class="tl-item rv" data-year="1978" data-era="THE DIGITAL SHIFT">
            <div class="tl-yr">1978</div>
            <h3>F-16 ENTERS SERVICE</h3>
            <p>Relaxed static stability plus a digital fly-by-wire flight computer makes a 9G, single-seat lightweight fighter flyable. The cockpit goes hands-on-throttle-and-stick; the analog era ends.</p>
          </div>
          <div class="tl-item rv" data-year="1984" data-era="THE DIGITAL SHIFT">
            <div class="tl-yr">1984</div>
            <h3>AH-64 APACHE IOC</h3>
            <p>Attack helicopters stop being flying tanks and become night-fighting systems — TADS/PNVS sensors, a mast-mounted radar and a gun slaved to the pilot's helmet.</p>
          </div>
          <div class="tl-item rv" data-year="1991" data-era="THE STEALTH ERA">
            <div class="tl-yr">1991</div>
            <h3>DESERT STORM PROVES THE PACKAGE</h3>
            <p>F-117s slip through the densest integrated air-defense network ever built. Precision-guided munitions, GPS and JSTARS fuse into a single campaign template that every air force studies for a generation.</p>
            <img src="https://picsum.photos/seed/desert-storm-strike/520/280" width="520" height="280" alt="Strike package formation archive imagery" loading="lazy" decoding="async">
          </div>
          <div class="tl-item rv" data-year="1997" data-era="THE STEALTH ERA">
            <div class="tl-yr">1997</div>
            <h3>F-22 FIRST FLIGHT</h3>
            <p>Supercruise, thrust vectoring and very-low-observable shaping in one airframe. Fifth generation is no longer a brochure term.</p>
          </div>
          <div class="tl-item rv" data-year="2001" data-era="SENSOR FUSION">
            <div class="tl-yr">2001</div>
            <h3>ARMED PREDATOR</h3>
            <p>A turboprop UAV fires Hellfires on cue from a crew a continent away. Persistence — not speed — becomes a weapon in its own right.</p>
          </div>
          <div class="tl-item rv" data-year="2006" data-era="SENSOR FUSION">
            <div class="tl-yr">2006</div>
            <h3>F-35 FIRST FLIGHT</h3>
            <p>The bet shifts from kinematics to information: APG-81 AESA, DAS sphere and EOTS fused into a single picture, shared across the force by MADL.</p>
          </div>
          <div class="tl-item rv" data-year="2017" data-era="SENSOR FUSION">
            <div class="tl-yr">2017</div>
            <h3>FIFTH-GEN PROLIFERATES</h3>
            <p>J-20 and Su-57 enter service. Stealth fighters are now built on three continents, and every exercise red air has a credible fifth-generation threat to replicate.</p>
            <img src="https://picsum.photos/seed/fifth-gen-hangar/520/280" width="520" height="280" alt="Hangar line archive imagery" loading="lazy" decoding="async">
          </div>
          <div class="tl-item rv" data-year="2024" data-era="THE AUTONOMY HORIZON">
            <div class="tl-yr">2024</div>
            <h3>COLLABORATIVE COMBAT AIRCRAFT</h3>
            <p>Uncrewed "loyal wingmen" fly in formation with crewed fighters under test control. Manned-unmanned teaming moves from concept to flight-test budget lines.</p>
          </div>
          <div class="tl-item rv" data-year="2035" data-era="THE AUTONOMY HORIZON">
            <div class="tl-yr">2035 ▸</div>
            <h3>SIXTH GENERATION FIELDING</h3>
            <p>NGAD-family and FCAS/GCAP programs point at networks of systems rather than single jets: adaptive-cycle engines, directed power budgets, and autonomy with a human on the loop.</p>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- ======== 05 DOCTRINE ======== -->
  <section class="sec" id="doctrine" aria-label="Doctrine and tasking">
    <div class="wrap">
      <div class="sec-head rv">
        <span class="idx">05 /</span>
        <h2 class="scr" data-text="DOCTRINE &amp; TASKING">DOCTRINE &amp; TASKING</h2>
        <span class="rule"></span>
        <span class="sec-meta">STANDING ORDERS ▸ REV 7</span>
      </div>
      <div class="doc-grid">
        <div class="rv">
          <div class="principle">
            <div class="pn">01</div>
            <div>
              <h3>MASS AT THE DECISIVE POINT</h3>
              <p>Concentrate sorties where the campaign hinges and accept calculated risk elsewhere. Weight of effort beats coverage.</p>
            </div>
          </div>
          <div class="principle">
            <div class="pn">02</div>
            <div>
              <h3>ONE PICTURE, MANY SENSORS</h3>
              <p>Datalinks beat monologues. The fused track — not any single radar — is the truth everyone shoots from.</p>
            </div>
          </div>
          <div class="principle">
            <div class="pn">03</div>
            <div>
              <h3>KILL THE ARCHER FIRST</h3>
              <p>SEAD and DEAD open the corridor. Blind the integrated air-defense system before anyone crosses the wire.</p>
            </div>
          </div>
          <div class="principle">
            <div class="pn">04</div>
            <div>
              <h3>ROTARY PERSISTENCE</h3>
              <p>Attack helicopters hold terrain in weather and nap-of-the-earth profiles that fixed-wing cannot. The low ground is owned, not visited.</p>
            </div>
          </div>
          <div class="principle">
            <div class="pn">05</div>
            <div>
              <h3>TRAIN LIKE YOU FIGHT</h3>
              <p>Aggressor squadrons, flag exercises and brutally honest debriefs buy the first ten combat sorties for every pilot.</p>
            </div>
          </div>
        </div>
        <div class="sortie rv d2">
          <div class="panel-hd"><b>MISSION TASKING ORDER</b> ▸ AUTO-GEN</div>
          <pre id="sortieOut" aria-live="polite">AWAITING TASKING…</pre>
          <div class="sortie-foot">
            <button class="btn" id="genBtn" type="button">GENERATE SORTIE ▸</button>
            <span class="threat mod">SIMULATED ORDER</span>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- ======== FOOTER ======== -->
  <footer>
    <div class="wrap">
      <div class="fmark rv" aria-hidden="true">VANGUARD AIR COMMAND</div>
      <div class="fcols">
        <div>
          <h4>COLOPHON</h4>
          <p class="colo">Single-file operations deck. Vanilla HTML, CSS and JavaScript — no frameworks, no build step, no dependencies. Serve as <span style="font-family:var(--mono)">.html</span> or <span style="font-family:var(--mono)">.php</span>.</p>
        </div>
        <div>
          <h4>DECKS</h4>
          <ul>
            <li><a href="#fleet">01 ▸ Fleet Roster</a></li>
            <li><a href="#compare">02 ▸ Combat Analysis</a></li>
            <li><a href="#telemetry">03 ▸ Engine Telemetry</a></li>
            <li><a href="#timeline">04 ▸ Program Timeline</a></li>
            <li><a href="#doctrine">05 ▸ Doctrine &amp; Tasking</a></li>
          </ul>
        </div>
        <div>
          <h4>BREVITY</h4>
          <ul>
            <li><span><b style="color:var(--amber)">FOX THREE</b> — active-radar missile away</span></li>
            <li><span><b style="color:var(--amber)">ANGELS</b> — altitude, thousands of feet</span></li>
            <li><span><b style="color:var(--amber)">WINCHESTER</b> — no ordnance remaining</span></li>
            <li><span><b style="color:var(--amber)">BUSTER</b> — max continuous thrust</span></li>
          </ul>
        </div>
        <div>
          <h4>STANDING ORDERS</h4>
          <ul>
            <li><span>EMCON ALPHA UNTIL TASKED</span></li>
            <li><span>IFF MODE 4 CHALLENGE REQ'D</span></li>
            <li><span>LASER CODES PER ATO DAILY</span></li>
            <li><span>LOSS OF LINK ▸ RTB PROFILE 7</span></li>
          </ul>
        </div>
      </div>
      <div class="disc">
        <span>UNCLASSIFIED // EXERCISE EXERCISE EXERCISE — EDITORIAL SIMULATION. NO AFFILIATION WITH ANY DEFENSE MINISTRY OR MANUFACTURER.</span>
        <span>© <span id="year"></span> VANGUARD AIR COMMAND</span>
        <a href="#top" id="toTop">▲ SCRAMBLE TO TOP</a>
      </div>
    </div>
  </footer>

  <noscript>
    <p style="padding:20px;font-family:monospace">JavaScript is required for the live operations deck. Static content remains readable.</p>
  </noscript>

  <script>
    (function() {
      "use strict";
      /* ============ helpers ============ */
      const $ = s => document.querySelector(s),
        $$ = s => Array.from(document.querySelectorAll(s));
      const RM = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
      const clamp = (v, a, b) => Math.max(a, Math.min(b, v));
      const rnd = (a, b) => a + Math.floor(Math.random() * (b - a + 1));
      const pick = a => a[Math.floor(Math.random() * a.length)];
      const pic = (seed, w, h) => `https://picsum.photos/seed/${seed}/${w}/${h}?grayscale`;

      /* ============ fleet data ============ */
      const FLEET = [{
          id: "f22",
          name: "F-22A RAPTOR",
          maker: "Lockheed Martin",
          cty: "USA",
          yr: 2005,
          role: "as",
          rl: "AIR SUPERIORITY",
          gen: "GEN 5",
          seed: "f22-raptor-stealth",
          feat: 1,
          desc: "First operational fifth-generation fighter. Supercruise, thrust vectoring and a sensor suite built to see first and shoot first.",
          line: "MACH 2.25 · CEIL 60K FT · RADIUS 760 KM",
          m: {
            spd: 96,
            rad: 62,
            ceil: 95,
            twr: 98,
            stl: 96,
            fus: 88
          },
          raw: {
            spd: "MACH 2.25",
            rad: "760 KM",
            ceil: "60,000 FT",
            twr: "1.26 T/W",
            stl: "VLO CLASS",
            fus: "IFDL · MADL"
          }
        },
        {
          id: "f35",
          name: "F-35A LIGHTNING II",
          maker: "Lockheed Martin",
          cty: "USA",
          yr: 2015,
          role: "mr",
          rl: "MULTIROLE STEALTH",
          gen: "GEN 5",
          seed: "f35-lightning-joint",
          desc: "The flying sensor node. AESA radar, DAS and EOTS fused into one picture and pushed to every asset on the network.",
          line: "MACH 1.6 · CEIL 55K FT · RADIUS 1,093 KM",
          m: {
            spd: 72,
            rad: 82,
            ceil: 88,
            twr: 74,
            stl: 92,
            fus: 99
          },
          raw: {
            spd: "MACH 1.6",
            rad: "1,093 KM",
            ceil: "55,000 FT",
            twr: "0.87 T/W",
            stl: "VLO CLASS",
            fus: "EOTS · DAS · MADL"
          }
        },
        {
          id: "fa18",
          name: "F/A-18E SUPER HORNET",
          maker: "Boeing",
          cty: "USA",
          yr: 1999,
          role: "mr",
          rl: "MULTIROLE STRIKE",
          gen: "GEN 4+",
          seed: "super-hornet-navy",
          desc: "Carrier workhorse. Hardpoint flexibility, AESA radar and a combat-proven strike record across two decades of operations.",
          line: "MACH 1.6 · CEIL 50K+ FT · RADIUS 740 KM",
          m: {
            spd: 72,
            rad: 58,
            ceil: 80,
            twr: 78,
            stl: 38,
            fus: 70
          },
          raw: {
            spd: "MACH 1.6",
            rad: "740 KM",
            ceil: "50,000+ FT",
            twr: "0.96 T/W",
            stl: "REDUCED RCS",
            fus: "APG-79 AESA"
          }
        },
        {
          id: "efa",
          name: "EUROFIGHTER TYPHOON",
          maker: "Eurofighter GmbH",
          cty: "EU CONSORTIUM",
          yr: 2003,
          role: "as",
          rl: "AIR SUPERIORITY",
          gen: "GEN 4.5",
          seed: "eurofighter-typhoon-ej200",
          desc: "Delta-canard interceptor that supercruises above Mach 1.5. Built to win the merge with energy and kinetics.",
          line: "MACH 2.0 · CEIL 65K FT · RADIUS 1,389 KM",
          m: {
            spd: 92,
            rad: 78,
            ceil: 96,
            twr: 90,
            stl: 35,
            fus: 74
          },
          raw: {
            spd: "MACH 2.0",
            rad: "1,389 KM",
            ceil: "65,000 FT",
            twr: "1.15 T/W",
            stl: "REDUCED RCS",
            fus: "CAPTOR-E AESA"
          }
        },
        {
          id: "raf",
          name: "RAFALE C",
          maker: "Dassault Aviation",
          cty: "FRANCE",
          yr: 2004,
          role: "mr",
          rl: "OMNIROLE",
          gen: "GEN 4.5",
          seed: "rafale-armee-air",
          desc: "One airframe, every mission in one sortie — strike, recon and nuclear deterrence with carrier qualification.",
          line: "MACH 1.8 · CEIL 50K FT · RADIUS 1,300 KM",
          m: {
            spd: 82,
            rad: 80,
            ceil: 84,
            twr: 82,
            stl: 40,
            fus: 78
          },
          raw: {
            spd: "MACH 1.8",
            rad: "1,300 KM",
            ceil: "50,000 FT",
            twr: "0.98 T/W",
            stl: "REDUCED RCS",
            fus: "RBE2-AA AESA"
          }
        },
        {
          id: "gripen",
          name: "JAS 39E GRIPEN",
          maker: "Saab",
          cty: "SWEDEN",
          yr: 2018,
          role: "mr",
          rl: "LIGHT MULTIROLE",
          gen: "GEN 4.5",
          seed: "gripen-swedish-jet",
          desc: "Dispersed-road operations, ten-minute road-turnarounds and a datalink culture that networked a whole air force early.",
          line: "MACH 2.0 · CEIL 50K FT · RADIUS 1,300 KM",
          m: {
            spd: 88,
            rad: 76,
            ceil: 86,
            twr: 80,
            stl: 36,
            fus: 72
          },
          raw: {
            spd: "MACH 2.0",
            rad: "1,300 KM",
            ceil: "50,000 FT",
            twr: "0.97 T/W",
            stl: "LOW RCS",
            fus: "RAVEN ES-05"
          }
        },
        {
          id: "su57",
          name: "SU-57 FELON",
          maker: "UAC / Sukhoi",
          cty: "RUSSIA",
          yr: 2020,
          role: "as",
          rl: "AIR SUPERIORITY",
          gen: "GEN 5",
          seed: "su57-felon-stealth",
          desc: "Thrust-vectored heavy fighter with cheek and wing-root arrays. Emphasis on kinematics, reach and infrared search.",
          line: "MACH 2.0 · CEIL 59K FT · RADIUS 1,500 KM",
          m: {
            spd: 94,
            rad: 84,
            ceil: 92,
            twr: 94,
            stl: 70,
            fus: 76
          },
          raw: {
            spd: "MACH 2.0",
            rad: "1,500 KM",
            ceil: "59,000 FT",
            twr: "1.19 T/W",
            stl: "REDUCED RCS",
            fus: "N036 BYELKA"
          }
        },
        {
          id: "ah64",
          name: "AH-64E APACHE GUARDIAN",
          maker: "Boeing",
          cty: "USA",
          yr: 2011,
          role: "rw",
          rl: "ATTACK ROTARY",
          gen: "ROTARY",
          seed: "apache-attack-helicopter",
          desc: "Longbow fire-control radar, manned-unmanned teaming with Grey Eagle, and the most combat-hours of any attack helicopter flying.",
          line: "293 KM/H · RADIUS 480 KM · 2× 1,492 KW",
          m: {
            spd: 34,
            rad: 42,
            ceil: 18,
            twr: 60,
            stl: 30,
            fus: 72
          },
          raw: {
            spd: "293 KM/H",
            rad: "480 KM",
            ceil: "6,100 FT OGE",
            twr: "2,000 SHP",
            stl: "LOW OBS",
            fus: "LONGBOW FCR"
          }
        },
        {
          id: "tiger",
          name: "TIGER HAD",
          maker: "Airbus Helicopters",
          cty: "FR / DE",
          yr: 2003,
          role: "rw",
          rl: "ATTACK ROTARY",
          gen: "ROTARY",
          seed: "tiger-had-helicopter",
          desc: "Slim-profile escort and anti-armor platform. Mast sight, SPIKE integration and an all-glass tandem cockpit.",
          line: "290 KM/H · RADIUS 400 KM · 2× 1,094 KW",
          m: {
            spd: 33,
            rad: 38,
            ceil: 16,
            twr: 56,
            stl: 28,
            fus: 64
          },
          raw: {
            spd: "290 KM/H",
            rad: "400 KM",
            ceil: "13,000 FT",
            twr: "1,464 SHP",
            stl: "LOW OBS",
            fus: "STRIX OSIGHT"
          }
        },
        {
          id: "ka52",
          name: "KA-52 ALLIGATOR",
          maker: "Russian Helicopters",
          cty: "RUSSIA",
          yr: 2011,
          role: "rw",
          rl: "ATTACK ROTARY",
          gen: "ROTARY",
          seed: "ka52-alligator-coaxial",
          desc: "Coaxial rotors, side-by-side crew and ejection seats — the only production helicopter with them. High dash speed for rotary.",
          line: "315 KM/H · RADIUS 460 KM · 2× 1,790 KW",
          m: {
            spd: 38,
            rad: 46,
            ceil: 20,
            twr: 66,
            stl: 32,
            fus: 60
          },
          raw: {
            spd: "315 KM/H",
            rad: "460 KM",
            ceil: "18,000 FT",
            twr: "2,400 SHP",
            stl: "LOW OBS",
            fus: "ARBALET L-BAND"
          }
        },
        {
          id: "mq9",
          name: "MQ-9A REAPER",
          maker: "General Atomics",
          cty: "USA",
          yr: 2007,
          role: "uav",
          rl: "UNMANNED ISR / STRIKE",
          gen: "UAV",
          seed: "mq9-reaper-uav",
          desc: "27 hours on station at 50,000 ft. Persistence as a weapon — eyes over the battle space that never cycle off.",
          line: "460 KM/H · CEIL 50K FT · ENDURANCE 27 H",
          m: {
            spd: 22,
            rad: 90,
            ceil: 80,
            twr: 20,
            stl: 45,
            fus: 70
          },
          raw: {
            spd: "460 KM/H",
            rad: "1,850 KM",
            ceil: "50,000 FT",
            twr: "749 KW",
            stl: "SMALL RCS",
            fus: "MTS-B / GDL"
          }
        }
      ];
      const STATUSES = [
        ["MISSION READY", "grn"],
        ["QRA ALERT", "amber"],
        ["TRAINING SORTIE", "cyn"],
        ["DEPOT MAINT", "dim"]
      ];
      const ROLES = {
        as: "AIR SUPERIORITY",
        mr: "MULTIROLE",
        rw: "ROTARY",
        uav: "UNMANNED"
      };
      const METRICS = [{
          k: "spd",
          lab: "MAX SPEED"
        }, {
          k: "rad",
          lab: "COMBAT RADIUS"
        }, {
          k: "ceil",
          lab: "CEILING"
        },
        {
          k: "twr",
          lab: "THRUST-TO-WEIGHT"
        }, {
          k: "stl",
          lab: "OBSERVABILITY (LOW RCS)"
        }, {
          k: "fus",
          lab: "SENSOR FUSION"
        }
      ];

      /* ============ clock + DTG ============ */
      const MONTHS = ["JAN", "FEB", "MAR", "APR", "MAY", "JUN", "JUL", "AUG", "SEP", "OCT", "NOV", "DEC"];

      function pad(n) {
        return String(n).padStart(2, "0");
      }

      function tickClock() {
        const d = new Date();
        $("#clockUTC").textContent = pad(d.getUTCHours()) + ":" + pad(d.getUTCMinutes()) + ":" + pad(d.getUTCSeconds());
        $("#dtg").textContent = "DTG " + pad(d.getUTCDate()) + pad(d.getUTCHours()) + pad(d.getUTCMinutes()) + "Z" + MONTHS[d.getUTCMonth()] + String(d.getUTCFullYear()).slice(2);
      }
      tickClock();
      setInterval(tickClock, 1000);
      $("#year").textContent = new Date().getFullYear();

      /* ============ ticker ============ */
      const TICKS = ["EX RED FLAG 25-3 ▸ 84 SORTIES SCHEDULED — RANGE COMPLEX OPEN", "AIRMET TANGO ▸ MODERATE TURBULENCE FL280–FL380 SECTOR 7",
        "VANDAL FLIGHT OF 4 ▸ F-35A RANGE ENTRY 14:20Z", "HAVOC 2 ▸ AH-64E NVG QUALIFICATION IN PROGRESS",
        "NOTAM 0417/25 ▸ TFR ACTIVE GRID 38T LM 4417", "LINK-16 NET ▸ 31 PARTICIPANTS · TRACK QUALITY NOMINAL",
        "SATCOM CH-2 ▸ SECURE UPLINK ESTABLISHED", "WX ▸ WIND 270/14 · VIS 10KM · FEW045",
        "TANKER SHELL 3 ▸ ON STATION FL240 TRACK EAST", "BASE OPS ▸ FOD WALK RUNWAY 09/27 COMPLETE"
      ];
      const half = TICKS.map(t => `<b>▸</b> ${t}`).join("<em>//</em>") + "<em>//</em>";
      $("#tickerTrack").innerHTML = half + half;

      /* ============ heading tape ============ */
      (function() {
        const W = 2880,
          per = W / 360;
        let s = "";
        for (let d = 0; d < 360; d += 5) {
          const x = d * per,
            maj = d % 10 === 0;
          s += `<span class="tk ${maj?"maj":"min"}" style="left:${x}px"></span>`;
          if (d % 30 === 0) {
            const lb = d === 0 ? "N" : d === 90 ? "E" : d === 180 ? "S" : d === 270 ? "W" : pad(d / 10);
            s += `<span class="tk-lb" style="left:${x}px">${lb}</span>`;
          }
        }
        $("#tapeTrack").innerHTML = s + s; // two cycles for seamless loop
      })();

      /* ============ scramble decode ============ */
      const POOL = "▮▯01345789ABCDEFXZ/\\-";

      function scramble(el) {
        const final = el.dataset.text || el.textContent;
        if (RM) {
          el.textContent = final;
          return;
        }
        if (el.dataset.done) return;
        el.dataset.done = "1";
        const dur = 700 + final.length * 22,
          t0 = performance.now();
        (function frame(t) {
          const p = clamp((t - t0) / dur, 0, 1),
            keep = Math.floor(p * final.length);
          let out = "";
          for (let i = 0; i < final.length; i++) {
            out += i < keep || final[i] === " " || final[i] === "&" ? final[i] : POOL[Math.floor(Math.random() * POOL.length)];
          }
          el.textContent = out;
          if (p < 1) requestAnimationFrame(frame);
          else el.textContent = final;
        })(t0);
      }
      $$(".scr[data-text]").forEach((el, i) => {
        el.dataset.text = el.dataset.text.replace(/&amp;/g, "&");
      });

      /* ============ reveal + counters + scramble triggers ============ */
      function countUp(el) {
        const to = parseFloat(el.dataset.to),
          dec = parseInt(el.dataset.dec || "0", 10);
        if (RM) {
          el.textContent = to.toFixed(dec);
          return;
        }
        const t0 = performance.now(),
          dur = 1300;
        (function f(t) {
          const p = clamp((t - t0) / dur, 0, 1),
            e = 1 - Math.pow(1 - p, 3);
          el.textContent = (to * e).toFixed(dec);
          if (p < 1) requestAnimationFrame(f);
        })(t0);
      }
      const io = new IntersectionObserver(es => {
        es.forEach(en => {
          if (!en.isIntersecting) return;
          en.target.classList.add("in");
          en.target.querySelectorAll(".count").forEach(countUp);
          if (en.target.classList.contains("count")) countUp(en.target);
          en.target.querySelectorAll(".scr").forEach(scramble);
          if (en.target.classList.contains("scr")) scramble(en.target);
          io.unobserve(en.target);
        });
      }, {
        threshold: .18
      });
      $$(".rv, .card, .sec-head, .scr").forEach(el => io.observe(el));
      $$(".count").forEach(el => io.observe(el));
      setTimeout(() => $$(".hero .scr").forEach((el, i) => setTimeout(() => scramble(el), i * 260)), 300);

      /* ============ fleet render + filters ============ */
      const grid = $("#fleetGrid");
      $("#fleetMeta").textContent = `${FLEET.length} AIRFRAMES · 4 CLASSES`;
      grid.innerHTML = FLEET.map((a, i) => {
        const st = STATUSES[i % STATUSES.length];
        const w = a.feat && window.innerWidth > 880 ? 1200 : 800,
          h = a.feat && window.innerWidth > 880 ? 520 : 500;
        return `<article class="card rv ${a.feat?"feat":""}" data-role="${a.role}" style="transition-delay:${(i%6)*60}ms">
    <div class="card-media">
      <img src="${pic(a.seed,w,h)}" width="${w}" height="${h}" alt="${a.name} archive imagery" loading="lazy" decoding="async">
      <span class="tag">${a.gen}</span><span class="yr">SINCE ${a.yr}</span>
    </div>
    <div class="card-body">
      <div class="card-top"><h3>${a.name}</h3><span class="flag">${a.cty}</span></div>
      <div class="card-maker">${a.maker} · ${a.rl}</div>
      <p class="card-desc">${a.desc}</p>
      <div class="specs">${a.line}</div>
      <div class="bars">
        <div class="bar"><span>SPD</span><span class="tr"><i style="--w:${a.m.spd}%"></i></span><span>${a.m.spd}</span></div>
        <div class="bar"><span>RNG</span><span class="tr"><i style="--w:${a.m.rad}%"></i></span><span>${a.m.rad}</span></div>
        <div class="bar"><span>STL</span><span class="tr"><i style="--w:${a.m.stl}%"></i></span><span>${a.m.stl}</span></div>
      </div>
      <div class="card-foot">
        <span class="status ${st[1]}">${st[0]}</span>
        <button class="cmp-btn" type="button" data-id="${a.id}">COMPARE +</button>
      </div>
    </div>
  </article>`;
      }).join("");

      const counts = {
        all: FLEET.length
      };
      FLEET.forEach(a => counts[a.role] = (counts[a.role] || 0) + 1);
      $("#chips").innerHTML = [
          ["all", "ALL"],
          ["as", "AIR SUPERIORITY"],
          ["mr", "MULTIROLE"],
          ["rw", "ROTARY"],
          ["uav", "UNMANNED"]
        ]
        .map(([k, l], i) => `<button class="chip ${i===0?"on":""}" type="button" data-f="${k}" role="tab" aria-selected="${i===0}">${l}<small>${counts[k]||0}</small></button>`).join("");
      $("#chips").addEventListener("click", e => {
        const b = e.target.closest(".chip");
        if (!b) return;
        $$("#chips .chip").forEach(c => {
          c.classList.toggle("on", c === b);
          c.setAttribute("aria-selected", c === b);
        });
        const f = b.dataset.f;
        $$(".card").forEach(c => {
          const show = f === "all" || c.dataset.role === f;
          c.classList.toggle("hidden", !show);
          if (show) {
            c.classList.remove("in");
            void c.offsetWidth;
            c.classList.add("in");
          }
        });
      });
      grid.addEventListener("click", e => {
        const b = e.target.closest(".cmp-btn");
        if (!b) return;
        $("#selA").value = b.dataset.id;
        renderCompare(true);
        $("#comparePanel").classList.remove("flash");
        void $("#comparePanel").offsetWidth;
        $("#comparePanel").classList.add("flash");
        document.getElementById("compare").scrollIntoView({
          behavior: RM ? "auto" : "smooth"
        });
      });

      /* ============ compare ============ */
      const selA = $("#selA"),
        selB = $("#selB");
      FLEET.forEach(a => {
        selA.add(new Option(a.name, a.id));
        selB.add(new Option(a.name, a.id));
      });
      selA.value = "f22";
      selB.value = "su57";
      const byId = id => FLEET.find(a => a.id === id);

      function miniHTML(a) {
        return `<img src="${pic(a.seed,300,200)}" width="300" height="200" alt="${a.name} profile" loading="lazy" decoding="async">
   <div class="mi"><b>${a.name}</b><span>${a.cty} · IOC ${a.yr} · ${a.gen}</span></div>`;
      }

      function renderCompare(fromCard) {
        const A = byId(selA.value),
          B = byId(selB.value);
        $("#miniA").innerHTML = miniHTML(A);
        $("#miniB").innerHTML = miniHTML(B);
        let wa = 0,
          wb = 0;
        $("#duelMid").innerHTML = METRICS.map(mt => {
          const va = A.m[mt.k],
            vb = B.m[mt.k];
          let pip = "";
          if (va > vb) {
            wa++;
            pip = '<span class="pips"><span class="pip a"></span></span>';
          } else if (vb > va) {
            wb++;
            pip = '<span class="pips"><span class="pip b"></span></span>';
          } else pip = '<span class="pips"><span class="pip"></span></span>';
          return `<div class="m-row ${va>vb?"win-a":vb>va?"win-b":""}">
      <div class="m-bar a"><i data-w="${va}"></i></div>
      <div class="m-lab"><b>${mt.lab}</b><span>${A.raw[mt.k]} ▾ ▴ ${B.raw[mt.k]}</span>${pip}</div>
      <div class="m-bar b"><i data-w="${vb}"></i></div>
    </div>`;
        }).join("");
        requestAnimationFrame(() => requestAnimationFrame(() => {
          $$("#duelMid .m-bar i").forEach(i => i.style.width = i.dataset.w + "%");
        }));
        if (wa > wb) {
          $("#verdictName").textContent = "AGGREGATE EDGE ▸ " + A.name;
          $("#verdictScore").textContent = `AXIS SCORE ${wa}–${wb} · ${A.rl}`;
        } else if (wb > wa) {
          $("#verdictName").textContent = "AGGREGATE EDGE ▸ " + B.name;
          $("#verdictScore").textContent = `AXIS SCORE ${wb}–${wa} · ${B.rl}`;
        } else {
          $("#verdictName").textContent = "DEAD HEAT AT THE MERGE";
          $("#verdictScore").textContent = `AXIS SCORE ${wa}–${wb} · PILOT DECIDES`;
        }
      }
      selA.addEventListener("change", () => renderCompare(false));
      selB.addEventListener("change", () => renderCompare(false));
      renderCompare(false);

      /* ============ radar ============ */
      const rc = $("#radarCanvas"),
        rctx = rc.getContext("2d");
      let blips = [],
        sweep = 0,
        radarVisible = true;
      const CLS = [
        ["FRND", "#52e39a"],
        ["HOSTILE", "#ff5d5d"],
        ["UNKNOWN", "#f5a83c"]
      ];
      for (let i = 0; i < 9; i++) {
        blips.push({
          a: Math.random() * 360,
          r: .18 + Math.random() * .72,
          c: pick([0, 0, 1, 2, 0, 2]),
          da: (Math.random() - .5) * .05
        });
      }

      function sizeRadar() {
        const dpr = window.devicePixelRatio || 1,
          w = rc.parentElement.clientWidth;
        rc.width = w * dpr;
        rc.height = w * dpr;
        rctx.setTransform(dpr, 0, 0, dpr, 0, 0);
      }

      function drawRadar() {
        const w = rc.parentElement.clientWidth,
          cx = w / 2,
          cy = w / 2,
          R = w / 2 - 6;
        rctx.clearRect(0, 0, w, w);
        rctx.strokeStyle = "rgba(82,227,154,.16)";
        rctx.lineWidth = 1;
        for (let i = 1; i <= 4; i++) {
          rctx.beginPath();
          rctx.arc(cx, cy, R * i / 4, 0, 7);
          rctx.stroke();
        }
        rctx.beginPath();
        rctx.moveTo(cx - R, cy);
        rctx.lineTo(cx + R, cy);
        rctx.moveTo(cx, cy - R);
        rctx.lineTo(cx, cy + R);
        rctx.stroke();
        rctx.strokeStyle = "rgba(82,227,154,.3)";
        for (let d = 0; d < 360; d += 10) {
          const rad = d * Math.PI / 180,
            inner = d % 30 === 0 ? R - 12 : R - 6;
          rctx.beginPath();
          rctx.moveTo(cx + Math.cos(rad) * inner, cy + Math.sin(rad) * inner);
          rctx.lineTo(cx + Math.cos(rad) * R, cy + Math.sin(rad) * R);
          rctx.stroke();
        }
        // sweep trail
        const sr = sweep * Math.PI / 180;
        for (let i = 0; i < 36; i++) {
          const a = sr - i * .02,
            al = (1 - i / 36) * .22;
          rctx.beginPath();
          rctx.moveTo(cx, cy);
          rctx.arc(cx, cy, R, a - .02, a + .005);
          rctx.closePath();
          rctx.fillStyle = `rgba(82,227,154,${al})`;
          rctx.fill();
        }
        rctx.strokeStyle = "rgba(140,255,190,.9)";
        rctx.lineWidth = 1.6;
        rctx.beginPath();
        rctx.moveTo(cx, cy);
        rctx.lineTo(cx + Math.cos(sr) * R, cy + Math.sin(sr) * R);
        rctx.stroke();
        // blips
        blips.forEach(b => {
          const rad = b.a * Math.PI / 180,
            x = cx + Math.cos(rad) * R * b.r,
            y = cy + Math.sin(rad) * R * b.r;
          let diff = (sweep - b.a) % 360;
          if (diff < 0) diff += 360;
          const al = diff < 140 ? (1 - diff / 140) : .06,
            col = CLS[b.c][1];
          rctx.save();
          rctx.globalAlpha = Math.max(al, .06);
          rctx.fillStyle = col;
          rctx.translate(x, y);
          rctx.rotate(Math.PI / 4);
          const s = al > .4 ? 5 : 3.5;
          rctx.fillRect(-s / 2, -s / 2, s, s);
          if (al > .75) {
            rctx.strokeStyle = col;
            rctx.lineWidth = 1;
            rctx.strokeRect(-s, -s, s * 2, s * 2);
          }
          rctx.restore();
        });
        // ownship
        rctx.fillStyle = "#dce4ea";
        rctx.beginPath();
        rctx.moveTo(cx, cy - 7);
        rctx.lineTo(cx + 6, cy + 6);
        rctx.lineTo(cx - 6, cy + 6);
        rctx.closePath();
        rctx.fill();
      }

      function radarLoop() {
        if (radarVisible && !RM) {
          sweep = (sweep + .9) % 360;
          blips.forEach(b => b.a = (b.a + b.da + 360) % 360);
          drawRadar();
          requestAnimationFrame(radarLoop);
        }
      }
      new IntersectionObserver(es => {
        const v = es[0].isIntersecting;
        if (v && !radarVisible && !RM) {
          radarVisible = true;
          radarLoop();
        } else radarVisible = v;
      }).observe(rc);
      sizeRadar();
      if (RM) {
        sweep = 45;
        drawRadar();
      } else {
        radarLoop();
      }
      window.addEventListener("resize", () => {
        sizeRadar();
        if (RM) drawRadar();
      });

      /* contact list feed */
      function contactRow() {
        const b = pick(blips),
          cls = CLS[b.c];
        return {
          id: pad(rnd(10, 99)),
          brg: pad(Math.round(b.a)) % 360,
          rng: Math.round(20 + b.r * 100),
          name: cls[0],
          col: b.c === 0 ? "c-frnd" : b.c === 1 ? "c-host" : "c-unk",
          dot: cls[1]
        };
      }

      function refreshContacts() {
        const rows = [contactRow(), contactRow(), contactRow(), contactRow()];
        $("#contactList").innerHTML = rows.map(r =>
          `<li><span class="c-dot" style="background:${r.dot}"></span>TRK ${r.id} · ${r.brg}° · ${r.rng} NM · <span class="${r.col}">${r.name}</span></li>`).join("");
      }
      refreshContacts();
      setInterval(refreshContacts, RM ? 5000 : 3200);

      /* ============ telemetry ============ */
      const thrEl = $("#thr"),
        abBtn = $("#abBtn");
      let AB = false,
        cur = {
          n1: 46,
          egt: 420
        },
        tgt = {
          n1: 46,
          egt: 420
        };

      function targets() {
        const t = thrEl.value / 100;
        tgt.n1 = clamp((45 + t * 55) + (AB ? 4 : 0), 0, 104);
        tgt.egt = 420 + t * 430 + (AB ? 180 : 0);
        const ff = (0.9 + t * 2.4 + (AB ? 1.9 : 0)).toFixed(1);
        const thrust = Math.round((AB ? 191 : 126) * (0.25 + 0.75 * t));
        $("#rFF").textContent = ff;
        $("#rTHR").textContent = thrust;
        $("#rNOZ").textContent = Math.round(15 + t * 85);
        $("#rMODE").textContent = AB ? "A/B" : t > .9 ? "MAX" : "MIL";
        $("#thrVal").textContent = (thrEl.value < 12 ? "IDLE " : "") + " " + pad(thrEl.value) + "%";
        const sim = +thrEl.value + (AB ? 30 : 0);
        $("#lampSlow").classList.toggle("on", sim < 45);
        $("#lampOn").classList.toggle("on", sim >= 45 && sim < 85);
        $("#lampFast").classList.toggle("on", sim >= 85);
        $("#lampAB").style.opacity = AB ? 1 : .35;
        $("#lampCaut").style.opacity = tgt.egt > 900 ? 1 : .35;
      }

      function drawGauge(cv, val, max, redFrom, amberFrom, label) {
        const ctx = cv.getContext("2d"),
          dpr = window.devicePixelRatio || 1;
        const w = cv.clientWidth || cv.parentElement.clientWidth,
          h = 200;
        if (cv.width !== w * dpr) {
          cv.width = w * dpr;
          cv.height = h * dpr;
          ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        }
        ctx.clearRect(0, 0, w, h);
        const cx = w / 2,
          cy = h / 2 + 26,
          R = Math.min(w, h) / 2 - 16,
          a0 = Math.PI * .75,
          span = Math.PI * 1.5;
        ctx.lineWidth = 6;
        ctx.lineCap = "round";
        const seg = (from, to, col) => {
          ctx.beginPath();
          ctx.arc(cx, cy, R, a0 + span * from, a0 + span * to);
          ctx.strokeStyle = col;
          ctx.stroke();
        };
        seg(0, amberFrom, "rgba(82,227,154,.5)");
        seg(amberFrom, redFrom, "rgba(245,168,60,.55)");
        seg(redFrom, 1, "rgba(255,93,93,.6)");
        ctx.lineWidth = 1;
        ctx.strokeStyle = "rgba(147,163,177,.5)";
        ctx.font = "9px IBM Plex Mono";
        ctx.fillStyle = "#5c6c79";
        ctx.textAlign = "center";
        for (let i = 0; i <= 10; i++) {
          const a = a0 + span * i / 10,
            r1 = R - 10,
            r2 = R - (i % 5 === 0 ? 18 : 14);
          ctx.beginPath();
          ctx.moveTo(cx + Math.cos(a) * r1, cy + Math.sin(a) * r1);
          ctx.lineTo(cx + Math.cos(a) * r2, cy + Math.sin(a) * r2);
          ctx.stroke();
          if (i % 5 === 0) ctx.fillText(Math.round(max * i / 10), cx + Math.cos(a) * (R - 28), cy + Math.sin(a) * (R - 28) + 3);
        }
        const av = clamp(val / max, 0, 1.02),
          na = a0 + span * av;
        ctx.strokeStyle = av >= redFrom ? "#ff5d5d" : av >= amberFrom ? "#f5a83c" : "#dce4ea";
        ctx.lineWidth = 2.5;
        ctx.beginPath();
        ctx.moveTo(cx, cy);
        ctx.lineTo(cx + Math.cos(na) * (R - 20), cy + Math.sin(na) * (R - 20));
        ctx.stroke();
        ctx.fillStyle = "#f5a83c";
        ctx.beginPath();
        ctx.arc(cx, cy, 4, 0, 7);
        ctx.fill();
        ctx.font = "600 20px IBM Plex Mono";
        ctx.fillStyle = av >= redFrom ? "#ff5d5d" : "#52e39a";
        ctx.fillText(Math.round(val), cx, cy - 24);
      }
      let teleFrame = 0;

      function teleLoop() {
        cur.n1 += (tgt.n1 - cur.n1) * (RM ? 1 : .09);
        cur.egt += (tgt.egt - cur.egt) * (RM ? 1 : .09);
        drawGauge($("#gN1"), cur.n1, 110, .92, .75, "N1");
        drawGauge($("#gEgt"), cur.egt, 1200, .76, .66, "EGT");
        teleFrame++;
        requestAnimationFrame(teleLoop);
      }
      thrEl.addEventListener("input", targets);
      abBtn.addEventListener("click", () => {
        AB = !AB;
        abBtn.setAttribute("aria-pressed", AB);
        targets();
      });
      targets();
      if (RM) {
        cur = {
          ...tgt
        };
        teleLoop();
      } else {
        teleLoop();
      }
      window.addEventListener("resize", () => {
        drawGauge($("#gN1"), cur.n1, 110, .92, .75);
        drawGauge($("#gEgt"), cur.egt, 1200, .76, .66);
      });

      /* ============ timeline scrollspy ============ */
      const tlYear = $("#tlYear"),
        tlEra = $("#tlEra");
      const spy = new IntersectionObserver(es => {
        es.forEach(en => {
          if (!en.isIntersecting) return;
          const y = en.target.dataset.year,
            era = en.target.dataset.era;
          if (tlYear.textContent !== y) {
            tlYear.textContent = y;
            tlEra.textContent = era;
            if (!RM) {
              tlYear.classList.remove("flip");
              void tlYear.offsetWidth;
              tlYear.classList.add("flip");
            }
          }
        });
      }, {
        rootMargin: "-42% 0px -42% 0px"
      });
      $$(".tl-item").forEach(el => spy.observe(el));

      /* ============ sortie generator ============ */
      const MSN = [{
          t: "COMBAT AIR PATROL",
          ac: ["F-22A RAPTOR", "EUROFIGHTER TYPHOON", "JAS 39E GRIPEN", "F-35A LIGHTNING II"],
          od: "6×AIM-120D · 2×AIM-9X · 2× 370G TANKS",
          alt: "FL280–FL420"
        },
        {
          t: "SEAD / DEAD SWEEP",
          ac: ["F/A-18E SUPER HORNET", "F-35A LIGHTNING II", "RAFALE C"],
          od: "2×AGM-88G AARGM-ER · 2×GBU-53F · CMDS LOAD B",
          alt: "FL180–FL300"
        },
        {
          t: "CLOSE AIR SUPPORT",
          ac: ["AH-64E APACHE", "TIGER HAD", "KA-52 ALLIGATOR"],
          od: "16×APKWS · 30MM CHAIN GUN · 4×HELLFIRE",
          alt: "200–1,500 FT AGL"
        },
        {
          t: "DEEP STRIKE",
          ac: ["F-35A LIGHTNING II", "F/A-18E SUPER HORNET", "RAFALE C"],
          od: "2×GBU-31 JDAM · 4×GBU-39 SDB",
          alt: "FL240–FL360"
        },
        {
          t: "ISR / RECON",
          ac: ["MQ-9A REAPER"],
          od: "MTS-B TURRET · 4×GDM-12 LGB (CONTINGENCY)",
          alt: "FL250 PERSISTENCE ORBIT"
        },
        {
          t: "AIR SUPERIORITY SWEEP",
          ac: ["F-22A RAPTOR", "SU-57 FELON [AGGRESSOR]"],
          od: "6×AIM-120D · INTERNAL GUN · EW POD",
          alt: "FL320–FL450"
        }
      ];
      const CALLS = ["VANDAL", "GHOST", "SABRE", "HAVOC", "TALON", "NOMAD", "VENOM", "SPECTRE", "IRON", "KODIAK"];
      const ROE = ["WEAPONS HOLD", "WEAPONS TIGHT", "WEAPONS FREE"];
      let typeToken = 0;

      function typeOut(lines) {
        const out = $("#sortieOut"),
          tok = ++typeToken,
          full = lines.join("\n");
        if (RM) {
          out.textContent = full;
          return;
        }
        out.textContent = "";
        let i = 0;
        (function step() {
          if (tok !== typeToken) return;
          out.textContent = full.slice(0, ++i);
          if (i < full.length) setTimeout(step, 9);
        })();
      }
      $("#genBtn").addEventListener("click", () => {
        const m = pick(MSN),
          cs = pick(CALLS) + " " + rnd(1, 4),
          d = new Date(Date.now() + rnd(20, 180) * 60000);
        const tot = pad(d.getUTCHours()) + pad(d.getUTCMinutes()) + "Z";
        const threat = rnd(0, 2),
          tLab = ["LOW", "MODERATE", "HIGH"][threat];
        typeOut([
          "┌─ MISSION TASKING ORDER ──────────────",
          `│ MISSION   ▸ ${m.t}`,
          `│ CALLSIGN  ▸ ${cs}`,
          `│ AIRFRAME  ▸ ${pick(m.ac)}`,
          `│ TOT       ▸ ${tot} · INGRESS NORTH`,
          `│ ALT BLOCK ▸ ${m.alt}`,
          `│ ORDNANCE  ▸ ${m.od}`,
          `│ ROE       ▸ ${pick(ROE)}`,
          `│ THREAT    ▸ ${tLab} — IADS ACTIVITY ${["MINIMAL","PATCHY","DENSE"][threat]}`,
          `│ RECOVERY  ▸ TANKER SHELL ${rnd(1,6)} FL240`,
          "└─ ACKNOWLEDGE ON DISCREET 3"
        ]);
      });

      /* ============ nav ============ */
      const burger = $(".burger");
      burger.addEventListener("click", () => {
        const open = document.body.classList.toggle("menu-open");
        burger.setAttribute("aria-expanded", open);
      });
      $$(".links a").forEach(a => a.addEventListener("click", () => {
        document.body.classList.remove("menu-open");
        burger.setAttribute("aria-expanded", "false");
      }));
      const navSpy = new IntersectionObserver(es => {
        es.forEach(en => {
          if (!en.isIntersecting) return;
          $$(".links a").forEach(a => a.setAttribute("aria-current", a.getAttribute("href") === "#" + en.target.id));
        });
      }, {
        rootMargin: "-40% 0px -55% 0px"
      });
      ["fleet", "compare", "telemetry", "timeline", "doctrine"].forEach(id => {
        const s = document.getElementById(id);
        if (s) navSpy.observe(s);
      });
    })();
  </script>
</body>

</html>