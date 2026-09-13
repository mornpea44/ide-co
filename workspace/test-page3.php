<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Kissa Mori — Japanese Coffee House</title>
  <meta
    name="description"
    content="Kissa Mori is a Japanese-inspired cafe and coffee brewery serving ceremonial matcha, hand-brewed coffee, seasonal sweets, and calm mornings."
  />

  <style>
    :root {
      --bg: #f8f1e7;
      --bg-soft: #fff8ee;
      --bg-card: rgba(255, 250, 240, 0.84);
      --ink: #241a14;
      --muted: #735f50;
      --muted-2: #9a8370;
      --line: rgba(60, 40, 25, 0.16);
      --accent: #9b2f22;
      --accent-2: #c46f3a;
      --matcha: #617a3c;
      --cream: #ffe8c8;
      --gold: #d8a34a;
      --shadow: 0 24px 80px rgba(59, 35, 20, 0.16);
      --shadow-soft: 0 12px 34px rgba(59, 35, 20, 0.11);
      --radius-lg: 32px;
      --radius-md: 22px;
      --radius-sm: 14px;
      --nav-h: 74px;
      --max: 1180px;
      --ease: cubic-bezier(.22, 1, .36, 1);
    }

    [data-theme="night"] {
      --bg: #12100e;
      --bg-soft: #1c1713;
      --bg-card: rgba(34, 27, 22, 0.84);
      --ink: #fff1dc;
      --muted: #c4aa90;
      --muted-2: #9c8672;
      --line: rgba(255, 232, 200, 0.15);
      --accent: #ff7b63;
      --accent-2: #dda36b;
      --matcha: #9bb66c;
      --cream: #35261e;
      --gold: #ffd284;
      --shadow: 0 24px 80px rgba(0, 0, 0, 0.42);
      --shadow-soft: 0 12px 34px rgba(0, 0, 0, 0.28);
    }

    * {
      box-sizing: border-box;
    }

    html {
      scroll-behavior: smooth;
    }

    body {
      margin: 0;
      font-family:
        ui-sans-serif,
        system-ui,
        -apple-system,
        BlinkMacSystemFont,
        "Segoe UI",
        sans-serif;
      color: var(--ink);
      background:
        radial-gradient(circle at 12% 8%, rgba(216, 163, 74, 0.25), transparent 32%),
        radial-gradient(circle at 86% 18%, rgba(155, 47, 34, 0.18), transparent 30%),
        linear-gradient(180deg, var(--bg), var(--bg-soft));
      overflow-x: hidden;
    }

    body.locked {
      overflow: hidden;
    }

    a {
      color: inherit;
      text-decoration: none;
    }

    button,
    input,
    select,
    textarea {
      font: inherit;
    }

    button {
      border: 0;
      cursor: pointer;
    }

    img {
      max-width: 100%;
      display: block;
    }

    ::selection {
      background: var(--accent);
      color: #fff;
    }

    .app-shell {
      min-height: 100vh;
      position: relative;
      isolation: isolate;
    }

    .grain {
      pointer-events: none;
      position: fixed;
      inset: 0;
      opacity: 0.28;
      z-index: -1;
      background-image:
        url("data:image/svg+xml,%3Csvg viewBox='0 0 300 300' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.65' numOctaves='3' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='300' height='300' filter='url(%23n)' opacity='.24'/%3E%3C/svg%3E");
      mix-blend-mode: multiply;
    }

    [data-theme="night"] .grain {
      mix-blend-mode: screen;
      opacity: 0.12;
    }

    .preloader {
      position: fixed;
      inset: 0;
      z-index: 100;
      display: grid;
      place-items: center;
      background: var(--bg);
      transition: opacity .5s var(--ease), visibility .5s var(--ease);
    }

    .preloader.done {
      opacity: 0;
      visibility: hidden;
    }

    .preloader-card {
      width: min(82vw, 320px);
      padding: 28px;
      border: 1px solid var(--line);
      border-radius: var(--radius-lg);
      background: var(--bg-card);
      box-shadow: var(--shadow);
      text-align: center;
      backdrop-filter: blur(20px);
    }

    .preloader-mark {
      width: 84px;
      height: 84px;
      margin: 0 auto 16px;
      border-radius: 50%;
      background:
        radial-gradient(circle at 50% 38%, var(--cream) 0 18%, transparent 19%),
        radial-gradient(circle at 50% 58%, var(--accent) 0 28%, transparent 29%),
        linear-gradient(135deg, var(--accent-2), var(--gold));
      display: grid;
      place-items: center;
      color: #fff;
      font-weight: 900;
      letter-spacing: .12em;
      box-shadow: var(--shadow-soft);
    }

    .steam-loader {
      height: 32px;
      display: flex;
      justify-content: center;
      gap: 9px;
      margin-bottom: 10px;
    }

    .steam-loader span {
      width: 8px;
      height: 28px;
      border-radius: 99px;
      background: var(--muted-2);
      opacity: .5;
      animation: steamRise 1.2s infinite ease-in-out;
    }

    .steam-loader span:nth-child(2) {
      animation-delay: .18s;
    }

    .steam-loader span:nth-child(3) {
      animation-delay: .36s;
    }

    @keyframes steamRise {
      0%, 100% { transform: translateY(8px) scaleY(.55); opacity: .18; }
      50% { transform: translateY(-8px) scaleY(1); opacity: .65; }
    }

    .site-header {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      height: var(--nav-h);
      z-index: 40;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 12px 16px;
      transition: transform .35s var(--ease), background .35s var(--ease), border .35s var(--ease);
    }

    .site-header.scrolled {
      background: color-mix(in srgb, var(--bg-card) 82%, transparent);
      border-bottom: 1px solid var(--line);
      backdrop-filter: blur(18px);
    }

    .nav {
      width: min(100%, var(--max));
      height: 100%;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 14px;
    }

    .brand {
      display: inline-flex;
      align-items: center;
      gap: 10px;
      font-weight: 900;
      letter-spacing: -.03em;
    }

    .brand-mark {
      width: 42px;
      height: 42px;
      border-radius: 16px;
      background:
        radial-gradient(circle at 52% 38%, var(--cream) 0 15%, transparent 16%),
        linear-gradient(135deg, var(--accent), var(--accent-2));
      display: grid;
      place-items: center;
      color: #fff;
      box-shadow: var(--shadow-soft);
    }

    .brand-text small {
      display: block;
      color: var(--muted);
      font-size: 10px;
      letter-spacing: .22em;
      text-transform: uppercase;
      margin-top: 1px;
    }

    .desktop-links {
      display: none;
      align-items: center;
      gap: 24px;
      color: var(--muted);
      font-weight: 700;
      font-size: 14px;
    }

    .desktop-links a {
      position: relative;
    }

    .desktop-links a::after {
      content: "";
      position: absolute;
      left: 0;
      bottom: -8px;
      width: 0;
      height: 2px;
      border-radius: 99px;
      background: var(--accent);
      transition: width .25s var(--ease);
    }

    .desktop-links a:hover::after {
      width: 100%;
    }

    .nav-actions {
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .icon-btn {
      width: 42px;
      height: 42px;
      border-radius: 15px;
      display: grid;
      place-items: center;
      background: var(--bg-card);
      color: var(--ink);
      border: 1px solid var(--line);
      box-shadow: 0 8px 24px rgba(60, 40, 25, .08);
      position: relative;
    }

    .cart-count {
      position: absolute;
      top: -5px;
      right: -4px;
      min-width: 19px;
      height: 19px;
      padding: 0 5px;
      border-radius: 999px;
      background: var(--accent);
      color: white;
      font-size: 11px;
      font-weight: 900;
      display: grid;
      place-items: center;
      border: 2px solid var(--bg);
    }

    .hamburger {
      display: grid;
      gap: 4px;
    }

    .hamburger i {
      width: 18px;
      height: 2px;
      background: currentColor;
      border-radius: 99px;
      transition: transform .25s var(--ease), opacity .25s var(--ease);
    }

    .hamburger.active i:nth-child(1) {
      transform: translateY(6px) rotate(45deg);
    }

    .hamburger.active i:nth-child(2) {
      opacity: 0;
    }

    .hamburger.active i:nth-child(3) {
      transform: translateY(-6px) rotate(-45deg);
    }

    .mobile-panel {
      position: fixed;
      top: calc(var(--nav-h) + 8px);
      left: 16px;
      right: 16px;
      z-index: 35;
      transform: translateY(-16px) scale(.98);
      opacity: 0;
      visibility: hidden;
      transition: .3s var(--ease);
      padding: 18px;
      border-radius: 26px;
      background: var(--bg-card);
      border: 1px solid var(--line);
      box-shadow: var(--shadow);
      backdrop-filter: blur(22px);
    }

    .mobile-panel.open {
      opacity: 1;
      visibility: visible;
      transform: translateY(0) scale(1);
    }

    .mobile-panel a {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 15px 4px;
      border-bottom: 1px solid var(--line);
      color: var(--muted);
      font-weight: 800;
    }

    .mobile-panel a:last-child {
      border-bottom: 0;
    }

    main {
      padding-top: var(--nav-h);
    }

    .section {
      padding: 74px 18px;
    }

    .container {
      width: min(100%, var(--max));
      margin: 0 auto;
    }

    .hero {
      min-height: calc(100svh - var(--nav-h));
      display: grid;
      align-items: center;
      padding: 28px 18px 52px;
      position: relative;
      overflow: hidden;
    }

    .hero-grid {
      width: min(100%, var(--max));
      margin: 0 auto;
      display: grid;
      gap: 34px;
      align-items: center;
    }

    .eyebrow {
      display: inline-flex;
      align-items: center;
      gap: 9px;
      width: max-content;
      max-width: 100%;
      padding: 8px 12px;
      border: 1px solid var(--line);
      border-radius: 999px;
      background: color-mix(in srgb, var(--bg-card) 85%, transparent);
      color: var(--muted);
      font-size: 12px;
      font-weight: 900;
      letter-spacing: .08em;
      text-transform: uppercase;
      backdrop-filter: blur(14px);
    }

    .eyebrow-dot {
      width: 8px;
      height: 8px;
      border-radius: 50%;
      background: var(--matcha);
      box-shadow: 0 0 0 6px color-mix(in srgb, var(--matcha) 20%, transparent);
    }

    .hero h1 {
      margin: 18px 0 16px;
      font-size: clamp(46px, 13vw, 104px);
      line-height: .88;
      letter-spacing: -.075em;
    }

    .hero h1 span {
      display: block;
      color: var(--accent);
      font-family: Georgia, "Times New Roman", serif;
      font-style: italic;
      letter-spacing: -.055em;
      font-weight: 500;
    }

    .hero-copy {
      color: var(--muted);
      line-height: 1.75;
      font-size: 16px;
      max-width: 58ch;
      margin: 0;
    }

    .hero-actions {
      display: flex;
      flex-wrap: wrap;
      gap: 12px;
      margin-top: 26px;
    }

    .btn {
      min-height: 48px;
      padding: 0 18px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 9px;
      border-radius: 999px;
      font-weight: 900;
      transition: transform .22s var(--ease), box-shadow .22s var(--ease), background .22s var(--ease);
      user-select: none;
    }

    .btn:active {
      transform: translateY(1px) scale(.98);
    }

    .btn-primary {
      color: white;
      background: linear-gradient(135deg, var(--accent), var(--accent-2));
      box-shadow: 0 15px 40px color-mix(in srgb, var(--accent) 28%, transparent);
    }

    .btn-secondary {
      color: var(--ink);
      background: var(--bg-card);
      border: 1px solid var(--line);
    }

    .hero-stats {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 10px;
      margin-top: 28px;
    }

    .stat {
      padding: 14px;
      border: 1px solid var(--line);
      border-radius: 20px;
      background: var(--bg-card);
      backdrop-filter: blur(18px);
    }

    .stat strong {
      display: block;
      font-size: 22px;
      letter-spacing: -.04em;
    }

    .stat small {
      color: var(--muted);
      font-weight: 700;
      font-size: 11px;
    }

    .hero-visual {
      position: relative;
      min-height: 420px;
    }

    .sun-disc {
      position: absolute;
      width: 270px;
      height: 270px;
      right: 0;
      top: 14px;
      border-radius: 50%;
      background:
        repeating-linear-gradient(
          0deg,
          rgba(255,255,255,.14) 0 7px,
          transparent 7px 16px
        ),
        linear-gradient(135deg, #e0422c, #e2a246);
      box-shadow: var(--shadow);
      animation: floaty 6s infinite ease-in-out;
    }

    .cup-card {
      position: absolute;
      left: 0;
      right: 0;
      bottom: 4px;
      margin: auto;
      width: min(92vw, 360px);
      min-height: 340px;
      border: 1px solid var(--line);
      border-radius: 40px;
      background:
        linear-gradient(180deg, color-mix(in srgb, var(--bg-card) 92%, transparent), color-mix(in srgb, var(--bg-card) 68%, transparent));
      box-shadow: var(--shadow);
      backdrop-filter: blur(22px);
      overflow: hidden;
      display: grid;
      place-items: center;
    }

    .cup {
      width: 220px;
      height: 150px;
      border-radius: 0 0 76px 76px;
      background:
        radial-gradient(ellipse at 50% 10%, #4c281c 0 38%, transparent 39%),
        linear-gradient(135deg, #fff3de, #d7a865);
      position: relative;
      box-shadow: inset 0 -22px 45px rgba(80, 42, 20, .16);
    }

    .cup::before {
      content: "";
      position: absolute;
      top: 28px;
      right: -42px;
      width: 70px;
      height: 70px;
      border: 18px solid #d7a865;
      border-left: 0;
      border-radius: 0 50px 50px 0;
    }

    .cup::after {
      content: "喫茶";
      position: absolute;
      inset: 45px 0 auto;
      text-align: center;
      font-size: 32px;
      font-weight: 900;
      color: var(--accent);
      opacity: .84;
    }

    .steam {
      position: absolute;
      top: 42px;
      display: flex;
      gap: 22px;
    }

    .steam b {
      width: 18px;
      height: 88px;
      border-radius: 50%;
      border-left: 4px solid color-mix(in srgb, var(--muted) 42%, transparent);
      animation: steamWiggle 2.5s infinite ease-in-out;
    }

    .steam b:nth-child(2) {
      animation-delay: .4s;
    }

    .steam b:nth-child(3) {
      animation-delay: .8s;
    }

    @keyframes steamWiggle {
      0%, 100% { transform: translateY(10px) translateX(0) scale(.92); opacity: .22; }
      50% { transform: translateY(-18px) translateX(8px) scale(1.08); opacity: .72; }
    }

    @keyframes floaty {
      0%, 100% { transform: translateY(0) rotate(0deg); }
      50% { transform: translateY(-16px) rotate(3deg); }
    }

    .floating-note {
      position: absolute;
      padding: 12px 14px;
      border-radius: 18px;
      border: 1px solid var(--line);
      background: var(--bg-card);
      box-shadow: var(--shadow-soft);
      color: var(--muted);
      font-size: 12px;
      font-weight: 800;
      backdrop-filter: blur(16px);
    }

    .floating-note.one {
      top: 86px;
      left: 8px;
    }

    .floating-note.two {
      right: 0;
      bottom: 92px;
    }

    .section-head {
      display: grid;
      gap: 12px;
      margin-bottom: 28px;
    }

    .section-kicker {
      color: var(--accent);
      font-size: 12px;
      text-transform: uppercase;
      letter-spacing: .16em;
      font-weight: 950;
    }

    .section-title {
      margin: 0;
      font-size: clamp(32px, 8vw, 66px);
      line-height: .95;
      letter-spacing: -.06em;
    }

    .section-text {
      color: var(--muted);
      line-height: 1.75;
      margin: 0;
      max-width: 64ch;
    }

    .story-grid {
      display: grid;
      gap: 18px;
    }

    .story-card {
      padding: 22px;
      border-radius: var(--radius-lg);
      background: var(--bg-card);
      border: 1px solid var(--line);
      box-shadow: var(--shadow-soft);
      backdrop-filter: blur(18px);
    }

    .story-card h3 {
      margin: 0 0 10px;
      font-size: 22px;
      letter-spacing: -.03em;
    }

    .story-card p {
      color: var(--muted);
      line-height: 1.75;
      margin: 0;
    }

    .story-card.featured {
      min-height: 260px;
      background:
        linear-gradient(135deg, color-mix(in srgb, var(--accent) 16%, transparent), transparent),
        var(--bg-card);
      display: flex;
      flex-direction: column;
      justify-content: space-between;
    }

    .kanji {
      font-size: 80px;
      line-height: 1;
      opacity: .16;
      font-weight: 900;
    }

    .menu-toolbar {
      position: sticky;
      top: calc(var(--nav-h) + 8px);
      z-index: 20;
      padding: 12px;
      margin: 0 -12px 24px;
      border: 1px solid var(--line);
      border-radius: 28px;
      background: color-mix(in srgb, var(--bg-card) 88%, transparent);
      backdrop-filter: blur(18px);
      box-shadow: 0 12px 30px rgba(60, 40, 25, .08);
    }

    .search-wrap {
      position: relative;
      margin-bottom: 10px;
    }

    .search-wrap input {
      width: 100%;
      height: 48px;
      padding: 0 44px 0 16px;
      border-radius: 999px;
      border: 1px solid var(--line);
      background: var(--bg-soft);
      color: var(--ink);
      outline: 0;
    }

    .search-wrap span {
      position: absolute;
      right: 16px;
      top: 50%;
      transform: translateY(-50%);
      color: var(--muted);
    }

    .chips {
      display: flex;
      gap: 8px;
      overflow-x: auto;
      scrollbar-width: none;
      padding-bottom: 1px;
    }

    .chips::-webkit-scrollbar {
      display: none;
    }

    .chip {
      flex: 0 0 auto;
      height: 38px;
      padding: 0 14px;
      border-radius: 999px;
      border: 1px solid var(--line);
      color: var(--muted);
      background: transparent;
      font-size: 13px;
      font-weight: 900;
    }

    .chip.active {
      color: #fff;
      background: var(--accent);
      border-color: var(--accent);
    }

    .menu-grid {
      display: grid;
      gap: 16px;
    }

    .menu-card {
      display: grid;
      grid-template-columns: 86px 1fr;
      gap: 14px;
      padding: 12px;
      border-radius: 25px;
      border: 1px solid var(--line);
      background: var(--bg-card);
      box-shadow: var(--shadow-soft);
      backdrop-filter: blur(16px);
    }

    .menu-art {
      height: 96px;
      border-radius: 20px;
      display: grid;
      place-items: center;
      color: white;
      font-size: 32px;
      background:
        radial-gradient(circle at 30% 24%, rgba(255,255,255,.34), transparent 24%),
        linear-gradient(135deg, var(--accent), var(--accent-2));
      overflow: hidden;
    }

    .menu-art.matcha {
      background:
        radial-gradient(circle at 30% 24%, rgba(255,255,255,.34), transparent 24%),
        linear-gradient(135deg, var(--matcha), #a6aa54);
    }

    .menu-art.sweet {
      background:
        radial-gradient(circle at 30% 24%, rgba(255,255,255,.34), transparent 24%),
        linear-gradient(135deg, #be626e, #e4a55d);
    }

    .menu-info {
      min-width: 0;
    }

    .menu-top {
      display: flex;
      justify-content: space-between;
      gap: 10px;
      align-items: flex-start;
    }

    .menu-card h3 {
      margin: 3px 0 5px;
      font-size: 18px;
      letter-spacing: -.035em;
    }

    .price {
      color: var(--accent);
      font-weight: 950;
      white-space: nowrap;
    }

    .menu-card p {
      margin: 0 0 10px;
      color: var(--muted);
      font-size: 13px;
      line-height: 1.55;
    }

    .menu-meta {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
    }

    .tag-list {
      display: flex;
      flex-wrap: wrap;
      gap: 6px;
    }

    .tag {
      padding: 5px 8px;
      border-radius: 999px;
      background: color-mix(in srgb, var(--accent-2) 15%, transparent);
      color: var(--muted);
      font-size: 10px;
      font-weight: 900;
    }

    .add-btn {
      width: 36px;
      height: 36px;
      border-radius: 13px;
      display: grid;
      place-items: center;
      color: #fff;
      background: var(--ink);
      flex: 0 0 auto;
    }

    .brew-panel {
      border-radius: var(--radius-lg);
      border: 1px solid var(--line);
      background: var(--bg-card);
      box-shadow: var(--shadow);
      overflow: hidden;
    }

    .brew-tabs {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      border-bottom: 1px solid var(--line);
    }

    .brew-tab {
      min-height: 56px;
      background: transparent;
      color: var(--muted);
      font-weight: 950;
      border-right: 1px solid var(--line);
    }

    .brew-tab:last-child {
      border-right: 0;
    }

    .brew-tab.active {
      color: #fff;
      background: var(--accent);
    }

    .brew-content {
      padding: 24px;
      display: grid;
      gap: 18px;
    }

    .brew-content h3 {
      margin: 0;
      font-size: 26px;
      letter-spacing: -.04em;
    }

    .brew-content p {
      color: var(--muted);
      line-height: 1.75;
      margin: 0;
    }

    .brew-steps {
      display: grid;
      gap: 10px;
      counter-reset: step;
    }

    .brew-step {
      counter-increment: step;
      display: grid;
      grid-template-columns: 34px 1fr;
      gap: 12px;
      align-items: start;
      color: var(--muted);
    }

    .brew-step::before {
      content: counter(step);
      width: 34px;
      height: 34px;
      border-radius: 13px;
      display: grid;
      place-items: center;
      color: #fff;
      background: var(--matcha);
      font-weight: 950;
    }

    .gallery-wrap {
      overflow: hidden;
      border-radius: var(--radius-lg);
      border: 1px solid var(--line);
      background: var(--bg-card);
      box-shadow: var(--shadow);
    }

    .gallery-track {
      display: flex;
      transition: transform .45s var(--ease);
    }

    .gallery-slide {
      min-width: 100%;
      min-height: 420px;
      padding: 22px;
      display: flex;
      align-items: flex-end;
      background:
        radial-gradient(circle at 30% 22%, rgba(255,255,255,.32), transparent 20%),
        linear-gradient(135deg, var(--accent), var(--accent-2));
      color: white;
      position: relative;
      overflow: hidden;
    }

    .gallery-slide:nth-child(2) {
      background:
        radial-gradient(circle at 20% 25%, rgba(255,255,255,.28), transparent 22%),
        linear-gradient(135deg, var(--matcha), #a78e43);
    }

    .gallery-slide:nth-child(3) {
      background:
        radial-gradient(circle at 70% 18%, rgba(255,255,255,.30), transparent 21%),
        linear-gradient(135deg, #2c1811, #a65536);
    }

    .gallery-slide::before {
      content: attr(data-kanji);
      position: absolute;
      right: -10px;
      top: 20px;
      font-size: 160px;
      font-weight: 950;
      opacity: .16;
    }

    .gallery-caption {
      position: relative;
      z-index: 1;
    }

    .gallery-caption h3 {
      font-size: 32px;
      margin: 0 0 8px;
      letter-spacing: -.045em;
    }

    .gallery-caption p {
      margin: 0;
      line-height: 1.65;
      opacity: .88;
    }

    .gallery-controls {
      display: flex;
      justify-content: space-between;
      gap: 12px;
      padding: 14px;
      border-top: 1px solid var(--line);
    }

    .gallery-dots {
      display: flex;
      align-items: center;
      gap: 7px;
    }

    .dot {
      width: 8px;
      height: 8px;
      border-radius: 999px;
      background: var(--muted-2);
      opacity: .45;
      transition: .25s var(--ease);
    }

    .dot.active {
      width: 24px;
      background: var(--accent);
      opacity: 1;
    }

    .split-grid {
      display: grid;
      gap: 18px;
    }

    .reservation-card,
    .hours-card {
      border-radius: var(--radius-lg);
      border: 1px solid var(--line);
      background: var(--bg-card);
      box-shadow: var(--shadow-soft);
      padding: 22px;
    }

    .form-grid {
      display: grid;
      gap: 12px;
      margin-top: 18px;
    }

    .field {
      display: grid;
      gap: 6px;
    }

    .field label {
      color: var(--muted);
      font-size: 12px;
      font-weight: 900;
      letter-spacing: .06em;
      text-transform: uppercase;
    }

    .field input,
    .field select,
    .field textarea {
      width: 100%;
      border: 1px solid var(--line);
      border-radius: 16px;
      min-height: 48px;
      padding: 12px 14px;
      color: var(--ink);
      background: var(--bg-soft);
      outline: none;
      transition: border .2s var(--ease), box-shadow .2s var(--ease);
    }

    .field textarea {
      min-height: 96px;
      resize: vertical;
    }

    .field input:focus,
    .field select:focus,
    .field textarea:focus {
      border-color: color-mix(in srgb, var(--accent) 70%, var(--line));
      box-shadow: 0 0 0 4px color-mix(in srgb, var(--accent) 14%, transparent);
    }

    .error-text {
      color: var(--accent);
      font-size: 12px;
      display: none;
    }

    .field.invalid .error-text {
      display: block;
    }

    .field.invalid input,
    .field.invalid select,
    .field.invalid textarea {
      border-color: var(--accent);
    }

    .hours-list {
      display: grid;
      gap: 10px;
      margin-top: 16px;
    }

    .hour-row {
      display: flex;
      justify-content: space-between;
      gap: 16px;
      padding: 12px 0;
      border-bottom: 1px solid var(--line);
      color: var(--muted);
    }

    .hour-row strong {
      color: var(--ink);
    }

    .faq {
      display: grid;
      gap: 10px;
    }

    .faq-item {
      border: 1px solid var(--line);
      border-radius: 20px;
      background: var(--bg-card);
      overflow: hidden;
    }

    .faq-q {
      width: 100%;
      min-height: 58px;
      padding: 0 16px;
      background: transparent;
      color: var(--ink);
      display: flex;
      align-items: center;
      justify-content: space-between;
      text-align: left;
      gap: 16px;
      font-weight: 950;
    }

    .faq-a {
      max-height: 0;
      overflow: hidden;
      transition: max-height .32s var(--ease);
    }

    .faq-a p {
      margin: 0;
      padding: 0 16px 16px;
      color: var(--muted);
      line-height: 1.7;
    }

    .faq-item.open .faq-a {
      max-height: 180px;
    }

    .site-footer {
      padding: 44px 18px 100px;
      border-top: 1px solid var(--line);
      background: color-mix(in srgb, var(--bg-card) 72%, transparent);
    }

    .footer-grid {
      width: min(100%, var(--max));
      margin: 0 auto;
      display: grid;
      gap: 24px;
    }

    .footer-links {
      display: flex;
      flex-wrap: wrap;
      gap: 12px 18px;
      color: var(--muted);
      font-weight: 800;
    }

    .bottom-bar {
      position: fixed;
      left: 14px;
      right: 14px;
      bottom: 14px;
      z-index: 30;
      display: grid;
      grid-template-columns: 1fr auto;
      gap: 10px;
      padding: 10px;
      border: 1px solid var(--line);
      border-radius: 24px;
      background: color-mix(in srgb, var(--bg-card) 88%, transparent);
      box-shadow: var(--shadow);
      backdrop-filter: blur(20px);
    }

    .bottom-bar .btn {
      min-height: 48px;
      padding: 0 16px;
    }

    .cart-drawer {
      position: fixed;
      inset: auto 0 0 0;
      z-index: 60;
      max-height: 86svh;
      transform: translateY(110%);
      transition: transform .36s var(--ease);
      border-radius: 32px 32px 0 0;
      border: 1px solid var(--line);
      background: var(--bg);
      box-shadow: var(--shadow);
      overflow: hidden;
    }

    .cart-drawer.open {
      transform: translateY(0);
    }

    .drawer-backdrop {
      position: fixed;
      inset: 0;
      z-index: 55;
      background: rgba(0,0,0,.32);
      opacity: 0;
      visibility: hidden;
      transition: .3s var(--ease);
    }

    .drawer-backdrop.open {
      opacity: 1;
      visibility: visible;
    }

    .cart-head {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 18px;
      border-bottom: 1px solid var(--line);
    }

    .cart-head h3 {
      margin: 0;
      font-size: 22px;
      letter-spacing: -.04em;
    }

    .cart-body {
      padding: 16px;
      overflow: auto;
      max-height: 52svh;
      display: grid;
      gap: 12px;
    }

    .cart-empty {
      color: var(--muted);
      text-align: center;
      padding: 28px 10px;
      line-height: 1.6;
    }

    .cart-item {
      display: grid;
      grid-template-columns: 1fr auto;
      gap: 12px;
      align-items: center;
      padding: 14px;
      border-radius: 18px;
      background: var(--bg-card);
      border: 1px solid var(--line);
    }

    .cart-item strong {
      display: block;
      margin-bottom: 4px;
    }

    .cart-item small {
      color: var(--muted);
    }

    .qty {
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .qty button {
      width: 30px;
      height: 30px;
      border-radius: 10px;
      background: var(--ink);
      color: var(--bg);
      font-weight: 900;
    }

    .cart-foot {
      padding: 16px;
      border-top: 1px solid var(--line);
      display: grid;
      gap: 12px;
    }

    .cart-total {
      display: flex;
      justify-content: space-between;
      font-size: 18px;
      font-weight: 950;
    }

    .toast {
      position: fixed;
      left: 50%;
      bottom: 92px;
      z-index: 90;
      transform: translateX(-50%) translateY(20px);
      opacity: 0;
      visibility: hidden;
      transition: .3s var(--ease);
      width: min(calc(100% - 28px), 420px);
      padding: 14px 16px;
      border-radius: 18px;
      background: var(--ink);
      color: var(--bg);
      box-shadow: var(--shadow);
      font-weight: 850;
      text-align: center;
    }

    .toast.show {
      opacity: 1;
      visibility: visible;
      transform: translateX(-50%) translateY(0);
    }

    .reveal {
      opacity: 0;
      transform: translateY(22px);
      transition: opacity .7s var(--ease), transform .7s var(--ease);
    }

    .reveal.visible {
      opacity: 1;
      transform: translateY(0);
    }

    @media (min-width: 760px) {
      .desktop-links {
        display: flex;
      }

      .hamburger-btn {
        display: none;
      }

      .hero-grid {
        grid-template-columns: 1.03fr .97fr;
      }

      .hero {
        padding-top: 40px;
      }

      .story-grid {
        grid-template-columns: 1.2fr .8fr .8fr;
      }

      .story-card.featured {
        grid-row: span 2;
      }

      .menu-grid {
        grid-template-columns: repeat(2, 1fr);
      }

      .split-grid {
        grid-template-columns: 1.1fr .9fr;
      }

      .bottom-bar {
        display: none;
      }

      .cart-drawer {
        left: auto;
        width: 430px;
        height: 100svh;
        max-height: 100svh;
        border-radius: 32px 0 0 32px;
        transform: translateX(110%);
      }

      .cart-drawer.open {
        transform: translateX(0);
      }

      .cart-body {
        max-height: calc(100svh - 190px);
      }
    }

    @media (min-width: 1024px) {
      .section {
        padding: 96px 24px;
      }

      .menu-grid {
        grid-template-columns: repeat(3, 1fr);
      }

      .menu-card {
        grid-template-columns: 1fr;
      }

      .menu-art {
        height: 180px;
      }
    }

    @media (prefers-reduced-motion: reduce) {
      *,
      *::before,
      *::after {
        animation-duration: .001ms !important;
        transition-duration: .001ms !important;
        scroll-behavior: auto !important;
      }

      .reveal {
        opacity: 1;
        transform: none;
      }
    }
  </style>
</head>

<body>
  <div class="app-shell">
    <div class="grain" aria-hidden="true"></div>

    <div class="preloader" id="preloader" aria-label="Loading page">
      <div class="preloader-card">
        <div class="preloader-mark">森</div>
        <div class="steam-loader" aria-hidden="true">
          <span></span><span></span><span></span>
        </div>
        <strong>Warming the kettle</strong>
      </div>
    </div>

    <header class="site-header" id="header">
      <nav class="nav" aria-label="Primary navigation">
        <a href="#top" class="brand" aria-label="Kissa Mori home">
          <span class="brand-mark">森</span>
          <span class="brand-text">
            Kissa Mori
            <small>Coffee House</small>
          </span>
        </a>

        <div class="desktop-links">
          <a href="#story">Story</a>
          <a href="#menu">Menu</a>
          <a href="#brew">Brewery</a>
          <a href="#gallery">Space</a>
          <a href="#reserve">Reserve</a>
        </div>

        <div class="nav-actions">
          <button class="icon-btn" id="themeBtn" aria-label="Toggle theme">☾</button>
          <button class="icon-btn" id="cartBtn" aria-label="Open order drawer">
            🧺
            <span class="cart-count" id="cartCount">0</span>
          </button>
          <button class="icon-btn hamburger-btn" id="menuBtn" aria-label="Open menu">
            <span class="hamburger" id="hamburger">
              <i></i><i></i><i></i>
            </span>
          </button>
        </div>
      </nav>
    </header>

    <div class="mobile-panel" id="mobilePanel">
      <a href="#story">Story <span>物語</span></a>
      <a href="#menu">Menu <span>献立</span></a>
      <a href="#brew">Brewery <span>焙煎</span></a>
      <a href="#gallery">Space <span>空間</span></a>
      <a href="#reserve">Reserve <span>予約</span></a>
    </div>

    <main id="top">
      <section class="hero">
        <div class="hero-grid">
          <div class="hero-copy-wrap reveal">
            <div class="eyebrow">
              <span class="eyebrow-dot"></span>
              Kyoto-inspired slow coffee
            </div>

            <h1>
              Quiet coffee,
              <span>deep mornings.</span>
            </h1>

            <p class="hero-copy">
              A Japanese cafe and micro-brewery for hand-poured coffee, ceremonial matcha,
              seasonal wagashi, and soft light between conversations.
            </p>

            <div class="hero-actions">
              <a class="btn btn-primary" href="#menu">Explore Menu →</a>
              <a class="btn btn-secondary" href="#reserve">Book a Table</a>
            </div>

            <div class="hero-stats">
              <div class="stat">
                <strong>18h</strong>
                <small>Cold brew steep</small>
              </div>
              <div class="stat">
                <strong>12</strong>
                <small>Single origins</small>
              </div>
              <div class="stat">
                <strong>7am</strong>
                <small>Doors open</small>
              </div>
            </div>
          </div>

          <div class="hero-visual reveal">
            <div class="sun-disc" aria-hidden="true"></div>
            <div class="floating-note one">炭火 roasted beans</div>
            <div class="floating-note two">Seasonal sakura sweets</div>
            <div class="cup-card">
              <div class="steam" aria-hidden="true">
                <b></b><b></b><b></b>
              </div>
              <div class="cup" aria-label="Illustrated coffee cup"></div>
            </div>
          </div>
        </div>
      </section>

      <section class="section" id="story">
        <div class="container">
          <div class="section-head reveal">
            <div class="section-kicker">Our philosophy</div>
            <h2 class="section-title">A kissaten for the modern city.</h2>
            <p class="section-text">
              Kissa Mori blends old Japanese coffee-house rituals with a clean contemporary
              experience: thoughtful sourcing, intentional brewing, and a space designed to slow
              the day down.
            </p>
          </div>

          <div class="story-grid">
            <article class="story-card featured reveal">
              <div class="kanji">間</div>
              <div>
                <h3>Designed around ma</h3>
                <p>
                  The Japanese idea of meaningful pause guides the space: low music, warm cedar,
                  soft linen, ceramic cups, and room to breathe between sips.
                </p>
              </div>
            </article>

            <article class="story-card reveal">
              <h3>Charcoal roast</h3>
              <p>
                Beans are roasted in small batches for deep aroma, low bitterness, and a lingering
                chocolate finish.
              </p>
            </article>

            <article class="story-card reveal">
              <h3>Tea ceremony care</h3>
              <p>
                Matcha is whisked to order with water temperature, bowl warmth, and texture treated
                as essential details.
              </p>
            </article>

            <article class="story-card reveal">
              <h3>Seasonal sweets</h3>
              <p>
                Our sweets rotate with the calendar: sakura, yuzu, chestnut, black sesame, and
                roasted soybean.
              </p>
            </article>

            <article class="story-card reveal">
              <h3>Evening brewery</h3>
              <p>
                After dusk, the bar shifts to coffee tonics, zero-proof infusions, cold brew flights,
                and dessert pairings.
              </p>
            </article>
          </div>
        </div>
      </section>

      <section class="section" id="menu">
        <div class="container">
          <div class="section-head reveal">
            <div class="section-kicker">Menu</div>
            <h2 class="section-title">Handmade, brewed, and quietly bold.</h2>
            <p class="section-text">
              Search, filter, and add favorites to your order basket.
            </p>
          </div>

          <div class="menu-toolbar reveal">
            <div class="search-wrap">
              <input id="menuSearch" type="search" placeholder="Search matcha, yuzu, cold brew..." />
              <span>⌕</span>
            </div>

            <div class="chips" role="tablist" aria-label="Menu filters">
              <button class="chip active" data-filter="all">All</button>
              <button class="chip" data-filter="coffee">Coffee</button>
              <button class="chip" data-filter="matcha">Matcha</button>
              <button class="chip" data-filter="brewery">Brewery</button>
              <button class="chip" data-filter="sweets">Sweets</button>
            </div>
          </div>

          <div class="menu-grid" id="menuGrid" aria-live="polite"></div>
        </div>
      </section>

      <section class="section" id="brew">
        <div class="container">
          <div class="section-head reveal">
            <div class="section-kicker">Brewery</div>
            <h2 class="section-title">Three rituals, one calm cup.</h2>
            <p class="section-text">
              Learn the house recipes behind our most requested preparations.
            </p>
          </div>

          <div class="brew-panel reveal">
            <div class="brew-tabs" role="tablist">
              <button class="brew-tab active" data-brew="pour">Pour-over</button>
              <button class="brew-tab" data-brew="matcha">Matcha</button>
              <button class="brew-tab" data-brew="cold">Cold brew</button>
            </div>

            <div class="brew-content" id="brewContent"></div>
          </div>
        </div>
      </section>

      <section class="section" id="gallery">
        <div class="container">
          <div class="section-head reveal">
            <div class="section-kicker">The space</div>
            <h2 class="section-title">Warm cedar, paper light, quiet tables.</h2>
          </div>

          <div class="gallery-wrap reveal">
            <div class="gallery-track" id="galleryTrack">
              <article class="gallery-slide" data-kanji="朝">
                <div class="gallery-caption">
                  <h3>Morning counter</h3>
                  <p>Watch the first pour-over bloom while the street is still quiet.</p>
                </div>
              </article>

              <article class="gallery-slide" data-kanji="茶">
                <div class="gallery-caption">
                  <h3>Matcha room</h3>
                  <p>A soft green corner for whisked tea, wagashi, and long reading.</p>
                </div>
              </article>

              <article class="gallery-slide" data-kanji="夜">
                <div class="gallery-caption">
                  <h3>Evening brewery</h3>
                  <p>Cold brew tonics, coffee flights, and low amber light after dusk.</p>
                </div>
              </article>
            </div>

            <div class="gallery-controls">
              <button class="btn btn-secondary" id="prevSlide">←</button>
              <div class="gallery-dots" id="galleryDots"></div>
              <button class="btn btn-secondary" id="nextSlide">→</button>
            </div>
          </div>
        </div>
      </section>

      <section class="section" id="reserve">
        <div class="container">
          <div class="section-head reveal">
            <div class="section-kicker">Reservations</div>
            <h2 class="section-title">Save your seat by the window.</h2>
          </div>

          <div class="split-grid">
            <article class="reservation-card reveal">
              <h3>Book a table</h3>
              <p class="section-text">
                For groups larger than six, please include a note and our host will confirm by phone.
              </p>

              <form class="form-grid" id="reservationForm" novalidate>
                <div class="field">
                  <label for="name">Name</label>
                  <input id="name" name="name" autocomplete="name" placeholder="Your name" required />
                  <span class="error-text">Please enter your name.</span>
                </div>

                <div class="field">
                  <label for="email">Email</label>
                  <input id="email" name="email" type="email" autocomplete="email" placeholder="you@example.com" required />
                  <span class="error-text">Please enter a valid email.</span>
                </div>

                <div class="field">
                  <label for="date">Date</label>
                  <input id="date" name="date" type="date" required />
                  <span class="error-text">Please choose a date.</span>
                </div>

                <div class="field">
                  <label for="party">Party size</label>
                  <select id="party" name="party" required>
                    <option value="">Select guests</option>
                    <option>1 guest</option>
                    <option>2 guests</option>
                    <option>3 guests</option>
                    <option>4 guests</option>
                    <option>5 guests</option>
                    <option>6 guests</option>
                    <option>7+ guests</option>
                  </select>
                  <span class="error-text">Please select a party size.</span>
                </div>

                <div class="field">
                  <label for="notes">Notes</label>
                  <textarea id="notes" name="notes" placeholder="Window seat, allergies, celebration..."></textarea>
                </div>

                <button class="btn btn-primary" type="submit">Request Reservation</button>
              </form>
            </article>

            <aside class="hours-card reveal">
              <h3>Hours & location</h3>

              <div class="hours-list">
                <div class="hour-row">
                  <strong>Mon–Thu</strong>
                  <span>7:00–19:00</span>
                </div>
                <div class="hour-row">
                  <strong>Fri</strong>
                  <span>7:00–22:00</span>
                </div>
                <div class="hour-row">
                  <strong>Sat</strong>
                  <span>8:00–22:00</span>
                </div>
                <div class="hour-row">
                  <strong>Sun</strong>
                  <span>8:00–18:00</span>
                </div>
              </div>

              <p class="section-text" style="margin-top:18px;">
                42 Hinoki Lane, Old Market District<br />
                Espresso bar, tea room, and evening coffee brewery.
              </p>

              <a class="btn btn-secondary" style="margin-top:18px;" href="mailto:hello@kissamori.example">
                hello@kissamori.example
              </a>
            </aside>
          </div>
        </div>
      </section>

      <section class="section">
        <div class="container">
          <div class="section-head reveal">
            <div class="section-kicker">FAQ</div>
            <h2 class="section-title">Before you visit.</h2>
          </div>

          <div class="faq reveal">
            <div class="faq-item open">
              <button class="faq-q">
                Do you take walk-ins?
                <span>+</span>
              </button>
              <div class="faq-a">
                <p>Yes. Counter seats are always reserved for walk-ins, especially during morning service.</p>
              </div>
            </div>

            <div class="faq-item">
              <button class="faq-q">
                Are there non-dairy options?
                <span>+</span>
              </button>
              <div class="faq-a">
                <p>Yes. Oat, soy, and almond milk are available for coffee and matcha drinks.</p>
              </div>
            </div>

            <div class="faq-item">
              <button class="faq-q">
                Is the evening brewery alcoholic?
                <span>+</span>
              </button>
              <div class="faq-a">
                <p>No. The brewery menu focuses on zero-proof coffee tonics, infusions, flights, and dessert pairings.</p>
              </div>
            </div>

            <div class="faq-item">
              <button class="faq-q">
                Can I work from the cafe?
                <span>+</span>
              </button>
              <div class="faq-a">
                <p>Laptops are welcome on weekdays. Weekends are laptop-free after 11:00 to keep tables open.</p>
              </div>
            </div>
          </div>
        </div>
      </section>
    </main>

    <footer class="site-footer">
      <div class="footer-grid">
        <a href="#top" class="brand">
          <span class="brand-mark">森</span>
          <span class="brand-text">
            Kissa Mori
            <small>Coffee House</small>
          </span>
        </a>

        <div class="footer-links">
          <a href="#story">Story</a>
          <a href="#menu">Menu</a>
          <a href="#brew">Brewery</a>
          <a href="#reserve">Reserve</a>
        </div>

        <p class="section-text">
          © <span id="year"></span> Kissa Mori. Crafted for slow mornings and late conversations.
        </p>
      </div>
    </footer>

    <div class="bottom-bar">
      <a class="btn btn-primary" href="#reserve">Reserve</a>
      <button class="btn btn-secondary" id="bottomCartBtn">
        Basket <span id="bottomCartCount">0</span>
      </button>
    </div>

    <div class="drawer-backdrop" id="drawerBackdrop"></div>

    <aside class="cart-drawer" id="cartDrawer" aria-label="Order basket">
      <div class="cart-head">
        <h3>Your basket</h3>
        <button class="icon-btn" id="closeCart" aria-label="Close basket">×</button>
      </div>

      <div class="cart-body" id="cartBody"></div>

      <div class="cart-foot">
        <div class="cart-total">
          <span>Total</span>
          <span id="cartTotal">$0.00</span>
        </div>
        <button class="btn btn-primary" id="checkoutBtn">Send Order Request</button>
      </div>
    </aside>

    <div class="toast" id="toast" role="status" aria-live="polite"></div>
  </div>

  <script>
    const $ = (selector, scope = document) => scope.querySelector(selector);
    const $$ = (selector, scope = document) => Array.from(scope.querySelectorAll(selector));

    const state = {
      filter: "all",
      query: "",
      cart: [],
      slide: 0,
      theme: "day",
    };

    const menuItems = [
      {
        id: "mori-pour",
        name: "Mori House Pour-over",
        category: "coffee",
        price: 6.5,
        icon: "☕",
        art: "coffee",
        desc: "Single-origin coffee brewed slowly with cedar-filtered water.",
        tags: ["Single origin", "Hot"]
      },
      {
        id: "charcoal-latte",
        name: "Charcoal Roast Latte",
        category: "coffee",
        price: 6.75,
        icon: "🥛",
        art: "coffee",
        desc: "Deep roast espresso, textured milk, brown sugar finish.",
        tags: ["Espresso", "Creamy"]
      },
      {
        id: "matcha-usucha",
        name: "Ceremonial Usucha",
        category: "matcha",
        price: 7.25,
        icon: "🍵",
        art: "matcha",
        desc: "Bright, balanced matcha whisked in a warmed ceramic bowl.",
        tags: ["Stone-milled", "Pure"]
      },
      {
        id: "yuzu-matcha",
        name: "Iced Yuzu Matcha",
        category: "matcha",
        price: 7.5,
        icon: "🍋",
        art: "matcha",
        desc: "Layered matcha, citrus yuzu, mineral water, and ice.",
        tags: ["Iced", "Citrus"]
      },
      {
        id: "kyoto-cold",
        name: "Kyoto Cold Brew",
        category: "brewery",
        price: 6.95,
        icon: "🧊",
        art: "coffee",
        desc: "Slow-dripped cold coffee with cocoa, molasses, and plum notes.",
        tags: ["18-hour", "Iced"]
      },
      {
        id: "coffee-tonic",
        name: "Ume Coffee Tonic",
        category: "brewery",
        price: 8.25,
        icon: "✨",
        art: "coffee",
        desc: "Cold brew concentrate, ume syrup, tonic, and citrus peel.",
        tags: ["Zero-proof", "Sparkling"]
      },
      {
        id: "sakura-mochi",
        name: "Sakura Mochi",
        category: "sweets",
        price: 4.95,
        icon: "🌸",
        art: "sweet",
        desc: "Soft rice cake, red bean, and salted cherry leaf.",
        tags: ["Seasonal", "Wagashi"]
      },
      {
        id: "sesame-roll",
        name: "Black Sesame Roll",
        category: "sweets",
        price: 5.75,
        icon: "🍰",
        art: "sweet",
        desc: "Cloud sponge cake with roasted black sesame cream.",
        tags: ["House-made", "Nutty"]
      },
      {
        id: "anko-toast",
        name: "Anko Butter Toast",
        category: "sweets",
        price: 6.25,
        icon: "🍞",
        art: "sweet",
        desc: "Milk bread, sweet red bean, cultured butter, sea salt.",
        tags: ["Warm", "Classic"]
      }
    ];

    const brewGuides = {
      pour: {
        title: "Mori Pour-over",
        desc: "A clean and rounded house recipe for sweet aromatics and a silky finish.",
        steps: [
          "Use 20g medium-ground coffee and 320g water at 93°C.",
          "Bloom with 50g water for 35 seconds.",
          "Pour in slow spirals to 200g, then pause.",
          "Finish to 320g and let drain by 2:45."
        ]
      },
      matcha: {
        title: "Ceremonial Matcha",
        desc: "Soft foam, vivid color, and balanced umami without harshness.",
        steps: [
          "Sift 2g matcha into a warmed chawan.",
          "Add 70g water at 78°C.",
          "Whisk in quick M-shaped strokes for 20 seconds.",
          "Serve immediately while the foam is glossy."
        ]
      },
      cold: {
        title: "Kyoto Cold Brew",
        desc: "Slow extraction for low acidity, chocolate depth, and a polished finish.",
        steps: [
          "Use 80g coarse-ground coffee and 720g chilled water.",
          "Set the drip rate to one drop per second.",
          "Extract slowly for 16 to 18 hours.",
          "Serve over clear ice or with tonic."
        ]
      }
    };

    function init() {
      $("#year").textContent = new Date().getFullYear();
      setMinDate();
      loadTheme();
      bindEvents();
      renderMenu();
      renderBrew("pour");
      renderDots();
      renderCart();
      initReveal();

      setTimeout(() => {
        $("#preloader").classList.add("done");
      }, 650);
    }

    function bindEvents() {
      window.addEventListener("scroll", handleScroll, { passive: true });

      $("#menuBtn").addEventListener("click", toggleMobileMenu);
      $$("#mobilePanel a").forEach(link => {
        link.addEventListener("click", closeMobileMenu);
      });

      $("#themeBtn").addEventListener("click", toggleTheme);

      $("#menuSearch").addEventListener("input", event => {
        state.query = event.target.value.trim().toLowerCase();
        renderMenu();
      });

      $$(".chip").forEach(chip => {
        chip.addEventListener("click", () => {
          $$(".chip").forEach(c => c.classList.remove("active"));
          chip.classList.add("active");
          state.filter = chip.dataset.filter;
          renderMenu();
        });
      });

      $$(".brew-tab").forEach(tab => {
        tab.addEventListener("click", () => {
          $$(".brew-tab").forEach(t => t.classList.remove("active"));
          tab.classList.add("active");
          renderBrew(tab.dataset.brew);
        });
      });

      $("#prevSlide").addEventListener("click", () => moveSlide(-1));
      $("#nextSlide").addEventListener("click", () => moveSlide(1));

      $("#cartBtn").addEventListener("click", openCart);
      $("#bottomCartBtn").addEventListener("click", openCart);
      $("#closeCart").addEventListener("click", closeCart);
      $("#drawerBackdrop").addEventListener("click", closeCart);
      $("#checkoutBtn").addEventListener("click", checkout);

      $("#reservationForm").addEventListener("submit", handleReservation);

      $$(".faq-q").forEach(button => {
        button.addEventListener("click", () => {
          const item = button.closest(".faq-item");
          item.classList.toggle("open");
        });
      });

      document.addEventListener("keydown", event => {
        if (event.key === "Escape") {
          closeCart();
          closeMobileMenu();
        }
      });
    }

    function handleScroll() {
      $("#header").classList.toggle("scrolled", window.scrollY > 20);
    }

    function toggleMobileMenu() {
      $("#mobilePanel").classList.toggle("open");
      $("#hamburger").classList.toggle("active");
    }

    function closeMobileMenu() {
      $("#mobilePanel").classList.remove("open");
      $("#hamburger").classList.remove("active");
    }

    function loadTheme() {
      try {
        const saved = localStorage.getItem("kissa-theme");
        if (saved === "night") {
          state.theme = "night";
          document.documentElement.dataset.theme = "night";
          $("#themeBtn").textContent = "☀";
        }
      } catch (error) {
        console.warn("Theme storage unavailable.");
      }
    }

    function toggleTheme() {
      state.theme = state.theme === "day" ? "night" : "day";
      document.documentElement.dataset.theme = state.theme === "night" ? "night" : "";
      $("#themeBtn").textContent = state.theme === "night" ? "☀" : "☾";

      try {
        localStorage.setItem("kissa-theme", state.theme);
      } catch (error) {
        console.warn("Theme could not be saved.");
      }
    }

    function renderMenu() {
      const grid = $("#menuGrid");

      const filtered = menuItems.filter(item => {
        const matchesFilter = state.filter === "all" || item.category === state.filter;
        const haystack = `${item.name} ${item.desc} ${item.tags.join(" ")}`.toLowerCase();
        const matchesQuery = !state.query || haystack.includes(state.query);
        return matchesFilter && matchesQuery;
      });

      if (!filtered.length) {
        grid.innerHTML = `
          <article class="story-card">
            <h3>No menu items found</h3>
            <p>Try another search or choose a different category.</p>
          </article>
        `;
        return;
      }

      grid.innerHTML = filtered.map(item => `
        <article class="menu-card reveal visible">
          <div class="menu-art ${item.art}" aria-hidden="true">${item.icon}</div>
          <div class="menu-info">
            <div class="menu-top">
              <h3>${escapeHTML(item.name)}</h3>
              <span class="price">$${item.price.toFixed(2)}</span>
            </div>
            <p>${escapeHTML(item.desc)}</p>
            <div class="menu-meta">
              <div class="tag-list">
                ${item.tags.map(tag => `<span class="tag">${escapeHTML(tag)}</span>`).join("")}
              </div>
              <button class="add-btn" data-add="${item.id}" aria-label="Add ${escapeHTML(item.name)}">+</button>
            </div>
          </div>
        </article>
      `).join("");

      $$("[data-add]").forEach(button => {
        button.addEventListener("click", () => addToCart(button.dataset.add));
      });
    }

    function renderBrew(key) {
      const guide = brewGuides[key];

      $("#brewContent").innerHTML = `
        <h3>${escapeHTML(guide.title)}</h3>
        <p>${escapeHTML(guide.desc)}</p>
        <div class="brew-steps">
          ${guide.steps.map(step => `<div class="brew-step">${escapeHTML(step)}</div>`).join("")}
        </div>
      `;
    }

    function renderDots() {
      const total = $$(".gallery-slide").length;
      $("#galleryDots").innerHTML = Array.from({ length: total }, (_, index) => `
        <span class="dot ${index === state.slide ? "active" : ""}" aria-hidden="true"></span>
      `).join("");
    }

    function moveSlide(direction) {
      const total = $$(".gallery-slide").length;
      state.slide = (state.slide + direction + total) % total;
      $("#galleryTrack").style.transform = `translateX(-${state.slide * 100}%)`;
      renderDots();
    }

    function addToCart(id) {
      const item = menuItems.find(product => product.id === id);
      const existing = state.cart.find(line => line.id === id);

      if (existing) {
        existing.qty += 1;
      } else {
        state.cart.push({ ...item, qty: 1 });
      }

      renderCart();
      showToast(`${item.name} added to basket`);
    }

    function changeQty(id, delta) {
      const line = state.cart.find(item => item.id === id);
      if (!line) return;

      line.qty += delta;

      if (line.qty <= 0) {
        state.cart = state.cart.filter(item => item.id !== id);
      }

      renderCart();
    }

    function renderCart() {
      const count = state.cart.reduce((sum, item) => sum + item.qty, 0);
      const total = state.cart.reduce((sum, item) => sum + item.price * item.qty, 0);

      $("#cartCount").textContent = count;
      $("#bottomCartCount").textContent = count;
      $("#cartTotal").textContent = `$${total.toFixed(2)}`;

      if (!state.cart.length) {
        $("#cartBody").innerHTML = `
          <div class="cart-empty">
            Your basket is empty.<br />
            Add coffee, matcha, or sweets from the menu.
          </div>
        `;
        return;
      }

      $("#cartBody").innerHTML = state.cart.map(item => `
        <div class="cart-item">
          <div>
            <strong>${escapeHTML(item.name)}</strong>
            <small>$${item.price.toFixed(2)} each</small>
          </div>
          <div class="qty">
            <button data-qty="${item.id}" data-delta="-1" aria-label="Decrease quantity">−</button>
            <strong>${item.qty}</strong>
            <button data-qty="${item.id}" data-delta="1" aria-label="Increase quantity">+</button>
          </div>
        </div>
      `).join("");

      $$("[data-qty]").forEach(button => {
        button.addEventListener("click", () => {
          changeQty(button.dataset.qty, Number(button.dataset.delta));
        });
      });
    }

    function openCart() {
      $("#cartDrawer").classList.add("open");
      $("#drawerBackdrop").classList.add("open");
      document.body.classList.add("locked");
    }

    function closeCart() {
      $("#cartDrawer").classList.remove("open");
      $("#drawerBackdrop").classList.remove("open");
      document.body.classList.remove("locked");
    }

    function checkout() {
      if (!state.cart.length) {
        showToast("Add something delicious first");
        return;
      }

      const summary = state.cart
        .map(item => `${item.qty}× ${item.name}`)
        .join(", ");

      showToast(`Order request prepared: ${summary}`);
    }

    function setMinDate() {
      const dateInput = $("#date");
      const today = new Date();
      const yyyy = today.getFullYear();
      const mm = String(today.getMonth() + 1).padStart(2, "0");
      const dd = String(today.getDate()).padStart(2, "0");
      dateInput.min = `${yyyy}-${mm}-${dd}`;
    }

    function handleReservation(event) {
      event.preventDefault();

      const form = event.currentTarget;
      const fields = ["name", "email", "date", "party"];
      let valid = true;

      fields.forEach(id => {
        const input = $(`#${id}`);
        const field = input.closest(".field");
        const isEmail = id === "email";
        const ok = isEmail
          ? /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(input.value.trim())
          : Boolean(input.value.trim());

        field.classList.toggle("invalid", !ok);

        if (!ok) valid = false;
      });

      if (!valid) {
        showToast("Please complete the highlighted fields");
        return;
      }

      const name = $("#name").value.trim();
      form.reset();
      showToast(`Thank you, ${name}. Your reservation request was received.`);
    }

    function initReveal() {
      const items = $$(".reveal");

      if (!("IntersectionObserver" in window)) {
        items.forEach(item => item.classList.add("visible"));
        return;
      }

      const observer = new IntersectionObserver(entries => {
        entries.forEach(entry => {
          if (entry.isIntersecting) {
            entry.target.classList.add("visible");
            observer.unobserve(entry.target);
          }
        });
      }, {
        threshold: 0.12,
        rootMargin: "0px 0px -60px 0px"
      });

      items.forEach(item => observer.observe(item));
    }

    function showToast(message) {
      const toast = $("#toast");
      toast.textContent = message;
      toast.classList.add("show");

      clearTimeout(showToast.timer);
      showToast.timer = setTimeout(() => {
        toast.classList.remove("show");
      }, 2600);
    }

    function escapeHTML(value) {
      return String(value)
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;")
        .replaceAll('"', "&quot;")
        .replaceAll("'", "&#039;");
    }

    init();
  </script>
</body>
</html>