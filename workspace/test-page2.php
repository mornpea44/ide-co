<!DOCTYPE html>
<html lang="en" data-theme="day">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <meta name="description" content="TSUKIYA 月屋 — Kyoto kissaten by day, craft sake brewery by night. Hand-drip coffee, house sake, and quiet hours in the old capital.">
  <meta name="theme-color" content="#f4ede1" id="metaTheme">
  <title>TSUKIYA 月屋 — Coffee by Day, Sake by Night | Kyoto</title>
  <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Ccircle cx='32' cy='32' r='28' fill='%23b3402a'/%3E%3Cpath d='M38 12a22 22 0 1 0 14 38A26 26 0 0 1 38 12z' fill='%23f4ede1'/%3E%3C/svg%3E">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Zen+Old+Mincho:wght@400;600;900&family=Zen+Kaku+Gothic+New:wght@300;400;500;700&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
  <style>
    /* ============================================================
TSUKIYA 月屋 — Single-file mobile landing page
Vanilla HTML / CSS / JS. No frameworks, no build step.
------------------------------------------------------------
TABLE OF CONTENTS
01. Design tokens & theme (day / night)
02. Reset & base
03. Utilities
04. Preloader
05. Scroll progress & back-to-top
06. Header & navigation
07. Overlay menu
08. Hero
09. Marquee
10. Section scaffolding
11. About / story
12. Day-night concept
13. Menu (tabs, cards, modal)
14. Brewery timeline
15. Seasonal carousel
16. Gallery
17. Omikuji fortune
18. Events
19. Testimonials
20. Reservation
21. FAQ
22. Newsletter
23. Footer
24. Toasts
25. Keyframes
26. Desktop breakpoint
27. Reduced motion
============================================================ */

    /* ------------------------------------------------------------
01. DESIGN TOKENS & THEME
------------------------------------------------------------ */
    :root {
      /* Day — warm kissaten paper */
      --bg: #f4ede1;
      --bg-alt: #ece2d0;
      --bg-deep: #e3d6bf;
      --surface: #fbf7ee;
      --surface-2: #f0e8d8;
      --ink: #1c1917;
      --ink-soft: #4a423a;
      --ink-faint: #8a7d6d;
      --line: rgba(28, 25, 23, 0.14);
      --line-strong: rgba(28, 25, 23, 0.32);
      --accent: #b3402a;
      /* vermillion */
      --accent-soft: rgba(179, 64, 42, 0.10);
      --accent-2: #6f7d4a;
      /* matcha */
      --accent-2-soft: rgba(111, 125, 74, 0.12);
      --gold: #a07a2c;
      --night-ink: #f0e6d2;
      --shadow: 0 18px 40px -18px rgba(28, 25, 23, 0.35);
      --shadow-soft: 0 8px 24px -12px rgba(28, 25, 23, 0.25);
      /* Type */
      --font-display: "Zen Old Mincho", "Hiragino Mincho ProN", "Yu Mincho", serif;
      --font-body: "Zen Kaku Gothic New", "Hiragino Kaku Gothic ProN", "Yu Gothic", sans-serif;
      --font-mono: "IBM Plex Mono", "SFMono-Regular", Menlo, monospace;
      /* Geometry */
      --radius-sm: 6px;
      --radius: 12px;
      --radius-lg: 20px;
      --gutter: clamp(20px, 6vw, 32px);
      --header-h: 64px;
      /* Motion */
      --ease: cubic-bezier(0.22, 1, 0.36, 1);
      --ease-io: cubic-bezier(0.65, 0, 0.35, 1);
      --dur: 0.6s;
    }

    [data-theme="night"] {
      --bg: #0f1318;
      --bg-alt: #141a21;
      --bg-deep: #0a0d11;
      --surface: #181f28;
      --surface-2: #1f2833;
      --ink: #f0e6d2;
      --ink-soft: #c9bda5;
      --ink-faint: #8b8272;
      --line: rgba(240, 230, 210, 0.14);
      --line-strong: rgba(240, 230, 210, 0.34);
      --accent: #d9a441;
      /* lantern amber */
      --accent-soft: rgba(217, 164, 65, 0.12);
      --accent-2: #9db4c0;
      /* moon indigo */
      --accent-2-soft: rgba(157, 180, 192, 0.12);
      --gold: #d9a441;
      --shadow: 0 18px 40px -18px rgba(0, 0, 0, 0.7);
      --shadow-soft: 0 8px 24px -12px rgba(0, 0, 0, 0.55);
    }

    /* ------------------------------------------------------------
02. RESET & BASE
------------------------------------------------------------ */
    *,
    *::before,
    *::after {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    html {
      scroll-behavior: smooth;
      -webkit-text-size-adjust: 100%;
      scroll-padding-top: calc(var(--header-h) + 12px);
    }

    body {
      font-family: var(--font-body);
      background: var(--bg);
      color: var(--ink);
      line-height: 1.7;
      font-weight: 400;
      overflow-x: hidden;
      transition: background 0.7s var(--ease), color 0.7s var(--ease);
      min-height: 100vh;
      min-height: 100dvh;
    }

    body.locked {
      overflow: hidden;
    }

    img,
    svg,
    canvas {
      display: block;
      max-width: 100%;
    }

    button,
    input,
    select,
    textarea {
      font: inherit;
      color: inherit;
      background: none;
      border: none;
    }

    button {
      cursor: pointer;
      -webkit-tap-highlight-color: transparent;
    }

    a {
      color: inherit;
      text-decoration: none;
      -webkit-tap-highlight-color: transparent;
    }

    ul,
    ol {
      list-style: none;
    }

    ::selection {
      background: var(--accent);
      color: var(--bg);
    }

    :focus-visible {
      outline: 2px solid var(--accent);
      outline-offset: 3px;
      border-radius: 2px;
    }

    /* Subtle paper grain over everything */
    body::before {
      content: "";
      position: fixed;
      inset: 0;
      z-index: 2;
      pointer-events: none;
      opacity: 0.5;
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='120' height='120'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='2' stitchTiles='stitch'/%3E%3CfeColorMatrix values='0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0.035 0'/%3E%3C/filter%3E%3Crect width='120' height='120' filter='url(%23n)'/%3E%3C/svg%3E");
    }

    /* Seigaiha (wave) pattern utility — pure CSS */
    .seigaiha {
      background-color: var(--bg-alt);
      background-image:
        radial-gradient(circle at 50% 100%, transparent 18px, var(--line) 19px, transparent 20px),
        radial-gradient(circle at 0% 100%, transparent 18px, var(--line) 19px, transparent 20px),
        radial-gradient(circle at 100% 100%, transparent 18px, var(--line) 19px, transparent 20px),
        radial-gradient(circle at 50% 100%, transparent 30px, var(--line) 31px, transparent 32px),
        radial-gradient(circle at 0% 100%, transparent 30px, var(--line) 31px, transparent 32px),
        radial-gradient(circle at 100% 100%, transparent 30px, var(--line) 31px, transparent 32px),
        radial-gradient(circle at 50% 100%, transparent 42px, var(--line) 43px, transparent 44px),
        radial-gradient(circle at 0% 100%, transparent 42px, var(--line) 43px, transparent 44px),
        radial-gradient(circle at 100% 100%, transparent 42px, var(--line) 43px, transparent 44px);
      background-size: 60px 30px;
      background-position: 0 0, 0 0, 0 0, 0 0, 0 0, 0 0, 0 0, 0 0, 0 0;
    }

    /* Vertical Japanese text */
    .v-text {
      writing-mode: vertical-rl;
      text-orientation: upright;
      letter-spacing: 0.35em;
      font-family: var(--font-display);
    }

    /* ------------------------------------------------------------
03. UTILITIES
------------------------------------------------------------ */
    .wrap {
      width: 100%;
      max-width: 1120px;
      margin: 0 auto;
      padding: 0 var(--gutter);
    }

    .eyebrow {
      display: inline-flex;
      align-items: center;
      gap: 10px;
      font-family: var(--font-mono);
      font-size: 11px;
      letter-spacing: 0.28em;
      text-transform: uppercase;
      color: var(--accent);
      margin-bottom: 14px;
    }

    .eyebrow::before {
      content: "";
      width: 28px;
      height: 1px;
      background: var(--accent);
    }

    .jp-eyebrow {
      font-family: var(--font-display);
      font-size: 13px;
      letter-spacing: 0.5em;
      color: var(--ink-faint);
    }

    h1,
    h2,
    h3 {
      font-family: var(--font-display);
      font-weight: 600;
      line-height: 1.2;
    }

    .h-display {
      font-size: clamp(34px, 9vw, 58px);
      letter-spacing: -0.01em;
    }

    .h-section {
      font-size: clamp(28px, 7vw, 44px);
    }

    .h-card {
      font-size: clamp(19px, 4.5vw, 23px);
    }

    .lede {
      color: var(--ink-soft);
      font-size: 15.5px;
      max-width: 52ch;
    }

    .btn {
      position: relative;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      padding: 15px 28px;
      font-family: var(--font-body);
      font-size: 14px;
      font-weight: 500;
      letter-spacing: 0.08em;
      border-radius: 999px;
      border: 1px solid var(--ink);
      color: var(--ink);
      background: transparent;
      transition: color 0.35s var(--ease), background 0.35s var(--ease),
        border-color 0.35s var(--ease), transform 0.2s var(--ease);
      overflow: hidden;
      min-height: 48px;
    }

    .btn:active {
      transform: scale(0.97);
    }

    .btn-solid {
      background: var(--ink);
      color: var(--bg);
    }

    .btn-solid:hover {
      background: var(--accent);
      border-color: var(--accent);
      color: #fff;
    }

    [data-theme="night"] .btn-solid:hover {
      color: #0f1318;
    }

    .btn-accent {
      background: var(--accent);
      border-color: var(--accent);
      color: #fff;
    }

    [data-theme="night"] .btn-accent {
      color: #0f1318;
    }

    .btn-accent:hover {
      filter: brightness(1.08);
    }

    .btn-ghost:hover {
      background: var(--accent-soft);
      border-color: var(--accent);
      color: var(--accent);
    }

    .btn-sm {
      padding: 10px 20px;
      font-size: 12.5px;
      min-height: 40px;
    }

    .btn-block {
      width: 100%;
    }

    .btn .btn-arrow {
      transition: transform 0.35s var(--ease);
    }

    .btn:hover .btn-arrow {
      transform: translateX(4px);
    }

    .tag {
      display: inline-block;
      padding: 3px 10px;
      font-family: var(--font-mono);
      font-size: 10px;
      letter-spacing: 0.14em;
      text-transform: uppercase;
      border: 1px solid var(--line-strong);
      border-radius: 999px;
      color: var(--ink-soft);
    }

    .tag-accent {
      border-color: var(--accent);
      color: var(--accent);
    }

    .tag-green {
      border-color: var(--accent-2);
      color: var(--accent-2);
    }

    .tag-gold {
      border-color: var(--gold);
      color: var(--gold);
    }

    .divider-kanji {
      display: flex;
      align-items: center;
      gap: 16px;
      color: var(--ink-faint);
      margin: 0 auto;
    }

    .divider-kanji::before,
    .divider-kanji::after {
      content: "";
      height: 1px;
      width: 46px;
      background: var(--line-strong);
    }

    /* Reveal-on-scroll */
    .reveal {
      opacity: 0;
      transform: translateY(26px);
      transition: opacity 0.9s var(--ease), transform 0.9s var(--ease);
    }

    .reveal.in-view {
      opacity: 1;
      transform: none;
    }

    .reveal-d1 {
      transition-delay: 0.08s;
    }

    .reveal-d2 {
      transition-delay: 0.16s;
    }

    .reveal-d3 {
      transition-delay: 0.24s;
    }

    .reveal-d4 {
      transition-delay: 0.32s;
    }

    /* Scrollbar (desktop nicety) */
    ::-webkit-scrollbar {
      width: 10px;
    }

    ::-webkit-scrollbar-track {
      background: var(--bg-deep);
    }

    ::-webkit-scrollbar-thumb {
      background: var(--ink-faint);
      border-radius: 5px;
    }

    ::-webkit-scrollbar-thumb:hover {
      background: var(--accent);
    }

    /* ------------------------------------------------------------
04. PRELOADER
------------------------------------------------------------ */
    #preloader {
      position: fixed;
      inset: 0;
      z-index: 1000;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: 22px;
      background: var(--bg);
      transition: opacity 0.7s var(--ease), visibility 0.7s;
    }

    #preloader.done {
      opacity: 0;
      visibility: hidden;
      pointer-events: none;
    }

    .pre-kanji {
      font-family: var(--font-display);
      font-size: 64px;
      color: var(--ink);
      display: flex;
      gap: 6px;
    }

    .pre-kanji span {
      opacity: 0;
      transform: translateY(14px);
      animation: preKanji 0.8s var(--ease) forwards;
    }

    .pre-kanji span:nth-child(2) {
      animation-delay: 0.15s;
    }

    .pre-kanji span:nth-child(3) {
      animation-delay: 0.3s;
    }

    @keyframes preKanji {
      to {
        opacity: 1;
        transform: none;
      }
    }

    .pre-bar {
      width: 140px;
      height: 2px;
      background: var(--line);
      border-radius: 2px;
      overflow: hidden;
    }

    .pre-bar i {
      display: block;
      height: 100%;
      width: 0%;
      background: var(--accent);
      transition: width 0.25s ease;
    }

    .pre-label {
      font-family: var(--font-mono);
      font-size: 10px;
      letter-spacing: 0.3em;
      text-transform: uppercase;
      color: var(--ink-faint);
    }

    /* ------------------------------------------------------------
05. SCROLL PROGRESS & BACK-TO-TOP
------------------------------------------------------------ */
    #scrollProgress {
      position: fixed;
      top: 0;
      left: 0;
      z-index: 90;
      height: 2px;
      width: 0%;
      background: linear-gradient(90deg, var(--accent), var(--gold));
      transition: width 0.1s linear;
    }

    #backTop {
      position: fixed;
      right: 18px;
      bottom: 18px;
      z-index: 80;
      width: 48px;
      height: 48px;
      border-radius: 50%;
      border: 1px solid var(--line-strong);
      background: var(--surface);
      color: var(--ink);
      display: grid;
      place-items: center;
      box-shadow: var(--shadow-soft);
      opacity: 0;
      transform: translateY(14px);
      pointer-events: none;
      transition: opacity 0.4s var(--ease), transform 0.4s var(--ease), background 0.3s;
    }

    #backTop.show {
      opacity: 1;
      transform: none;
      pointer-events: auto;
    }

    #backTop:hover {
      background: var(--accent);
      color: #fff;
      border-color: var(--accent);
    }

    #backTop svg {
      width: 18px;
      height: 18px;
    }

    /* ------------------------------------------------------------
06. HEADER & NAVIGATION
------------------------------------------------------------ */
    #siteHeader {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      z-index: 60;
      height: var(--header-h);
      display: flex;
      align-items: center;
      transition: background 0.45s var(--ease), box-shadow 0.45s var(--ease), border-color 0.45s;
      border-bottom: 1px solid transparent;
    }

    #siteHeader.scrolled {
      background: color-mix(in srgb, var(--bg) 88%, transparent);
      -webkit-backdrop-filter: blur(14px);
      backdrop-filter: blur(14px);
      border-bottom-color: var(--line);
    }

    .header-inner {
      width: 100%;
      max-width: 1120px;
      margin: 0 auto;
      padding: 0 var(--gutter);
      display: flex;
      align-items: center;
      justify-content: space-between;
    }

    .brand {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .brand-mark {
      width: 36px;
      height: 36px;
      border-radius: 50%;
      background: var(--accent);
      display: grid;
      place-items: center;
      color: var(--bg);
      font-family: var(--font-display);
      font-size: 17px;
      transition: background 0.5s var(--ease), transform 0.5s var(--ease);
    }

    [data-theme="night"] .brand-mark {
      background: var(--gold);
      color: #0f1318;
    }

    .brand:hover .brand-mark {
      transform: rotate(-8deg);
    }

    .brand-name {
      display: flex;
      flex-direction: column;
      line-height: 1.15;
    }

    .brand-name strong {
      font-family: var(--font-display);
      font-size: 16px;
      letter-spacing: 0.14em;
      font-weight: 600;
    }

    .brand-name small {
      font-family: var(--font-mono);
      font-size: 9px;
      letter-spacing: 0.24em;
      text-transform: uppercase;
      color: var(--ink-faint);
    }

    .header-actions {
      display: flex;
      align-items: center;
      gap: 10px;
    }

    /* Day/night toggle */
    #themeToggle {
      position: relative;
      width: 62px;
      height: 32px;
      border-radius: 999px;
      border: 1px solid var(--line-strong);
      background: var(--surface);
      transition: background 0.4s var(--ease), border-color 0.4s;
      flex-shrink: 0;
    }

    #themeToggle .knob {
      position: absolute;
      top: 3px;
      left: 3px;
      width: 24px;
      height: 24px;
      border-radius: 50%;
      background: var(--accent);
      display: grid;
      place-items: center;
      color: #fff;
      transition: transform 0.45s cubic-bezier(0.34, 1.56, 0.64, 1), background 0.4s;
    }

    [data-theme="night"] #themeToggle .knob {
      transform: translateX(30px);
      background: var(--gold);
      color: #0f1318;
    }

    #themeToggle svg {
      width: 13px;
      height: 13px;
    }

    #themeToggle .icon-moon {
      display: none;
    }

    [data-theme="night"] #themeToggle .icon-sun {
      display: none;
    }

    [data-theme="night"] #themeToggle .icon-moon {
      display: block;
    }

    /* Hamburger */
    #menuBtn {
      width: 44px;
      height: 44px;
      display: grid;
      place-items: center;
      border-radius: 50%;
      border: 1px solid var(--line-strong);
      background: var(--surface);
      transition: background 0.3s, border-color 0.3s;
    }

    #menuBtn:hover {
      border-color: var(--accent);
    }

    #menuBtn .bars {
      position: relative;
      width: 18px;
      height: 12px;
    }

    #menuBtn .bars i {
      position: absolute;
      left: 0;
      width: 100%;
      height: 1.6px;
      background: var(--ink);
      transition: transform 0.4s var(--ease), opacity 0.3s, background 0.3s;
    }

    #menuBtn .bars i:nth-child(1) {
      top: 0;
    }

    #menuBtn .bars i:nth-child(2) {
      top: 50%;
      transform: translateY(-50%);
    }

    #menuBtn .bars i:nth-child(3) {
      bottom: 0;
    }

    #menuBtn.active .bars i:nth-child(1) {
      transform: translateY(6px) rotate(45deg);
      background: var(--accent);
    }

    #menuBtn.active .bars i:nth-child(2) {
      opacity: 0;
    }

    #menuBtn.active .bars i:nth-child(3) {
      transform: translateY(-6px) rotate(-45deg);
      background: var(--accent);
    }

    /* ------------------------------------------------------------
07. OVERLAY MENU
------------------------------------------------------------ */
    #overlayMenu {
      position: fixed;
      inset: 0;
      z-index: 55;
      background: var(--bg);
      display: flex;
      flex-direction: column;
      padding: calc(var(--header-h) + 20px) var(--gutter) 28px;
      clip-path: circle(0% at calc(100% - 44px) 32px);
      transition: clip-path 0.75s var(--ease);
      overflow-y: auto;
      visibility: hidden;
    }

    #overlayMenu.open {
      clip-path: circle(150% at calc(100% - 44px) 32px);
      visibility: visible;
    }

    .overlay-nav {
      flex: 1;
      display: flex;
      flex-direction: column;
      justify-content: center;
      gap: 4px;
    }

    .overlay-nav a {
      display: flex;
      align-items: baseline;
      gap: 16px;
      padding: 13px 4px;
      border-bottom: 1px solid var(--line);
      font-family: var(--font-display);
      font-size: clamp(24px, 6vw, 34px);
      opacity: 0;
      transform: translateY(20px);
      transition: opacity 0.5s var(--ease), transform 0.5s var(--ease), color 0.3s;
    }

    #overlayMenu.open .overlay-nav a {
      opacity: 1;
      transform: none;
    }

    .overlay-nav a:nth-child(1) {
      transition-delay: 0.15s;
    }

    .overlay-nav a:nth-child(2) {
      transition-delay: 0.21s;
    }

    .overlay-nav a:nth-child(3) {
      transition-delay: 0.27s;
    }

    .overlay-nav a:nth-child(4) {
      transition-delay: 0.33s;
    }

    .overlay-nav a:nth-child(5) {
      transition-delay: 0.39s;
    }

    .overlay-nav a:nth-child(6) {
      transition-delay: 0.45s;
    }

    .overlay-nav a:nth-child(7) {
      transition-delay: 0.51s;
    }

    .overlay-nav a .nav-num {
      font-family: var(--font-mono);
      font-size: 11px;
      color: var(--accent);
      letter-spacing: 0.1em;
    }

    .overlay-nav a .nav-jp {
      margin-left: auto;
      font-size: 13px;
      color: var(--ink-faint);
      letter-spacing: 0.3em;
    }

    .overlay-nav a:active {
      color: var(--accent);
    }

    .overlay-footer {
      display: flex;
      justify-content: space-between;
      align-items: flex-end;
      gap: 16px;
      padding-top: 20px;
      opacity: 0;
      transition: opacity 0.5s var(--ease) 0.55s;
    }

    #overlayMenu.open .overlay-footer {
      opacity: 1;
    }

    .overlay-footer .hours {
      font-family: var(--font-mono);
      font-size: 11px;
      line-height: 1.9;
      color: var(--ink-soft);
      letter-spacing: 0.06em;
    }

    .overlay-footer .hours b {
      color: var(--ink);
      display: block;
      letter-spacing: 0.2em;
      margin-bottom: 2px;
    }

    /* ------------------------------------------------------------
08. HERO
------------------------------------------------------------ */
    #hero {
      position: relative;
      min-height: 100vh;
      min-height: 100dvh;
      display: flex;
      flex-direction: column;
      justify-content: flex-end;
      padding: 0 var(--gutter) 34px;
      overflow: hidden;
      isolation: isolate;
    }

    /* Sky gradient shifts with theme */
    .hero-sky {
      position: absolute;
      inset: 0;
      z-index: -3;
      background:
        radial-gradient(120% 70% at 78% 12%, var(--accent-soft) 0%, transparent 55%),
        linear-gradient(180deg, var(--bg) 0%, var(--bg-alt) 100%);
      transition: background 0.8s var(--ease);
    }

    [data-theme="night"] .hero-sky {
      background:
        radial-gradient(120% 70% at 70% 10%, rgba(217, 164, 65, 0.10) 0%, transparent 55%),
        linear-gradient(180deg, var(--bg-deep) 0%, var(--bg) 100%);
    }

    /* Sun / moon disc */
    .hero-disc {
      position: absolute;
      z-index: -2;
      top: 14%;
      right: 8%;
      width: clamp(120px, 38vw, 240px);
      aspect-ratio: 1;
      border-radius: 50%;
      background: radial-gradient(circle at 38% 34%, #d4573a, var(--accent) 68%);
      box-shadow: 0 24px 70px -20px rgba(179, 64, 42, 0.55);
      transition: background 0.9s var(--ease), box-shadow 0.9s var(--ease), transform 1.2s var(--ease);
      animation: discFloat 9s ease-in-out infinite;
    }

    [data-theme="night"] .hero-disc {
      background: radial-gradient(circle at 40% 36%, #fdf6e3, #e8d9b4 70%);
      box-shadow: 0 24px 70px -20px rgba(217, 164, 65, 0.4);
    }

    @keyframes discFloat {

      0%,
      100% {
        translate: 0 0;
      }

      50% {
        translate: 0 -14px;
      }
    }

    /* Stars (night only) */
    .hero-stars {
      position: absolute;
      inset: 0;
      z-index: -2;
      opacity: 0;
      transition: opacity 1s var(--ease);
      pointer-events: none;
    }

    [data-theme="night"] .hero-stars {
      opacity: 1;
    }

    .hero-stars i {
      position: absolute;
      width: 2px;
      height: 2px;
      border-radius: 50%;
      background: var(--night-ink);
      animation: twinkle 3.4s ease-in-out infinite;
    }

    @keyframes twinkle {

      0%,
      100% {
        opacity: 0.15;
      }

      50% {
        opacity: 0.9;
      }
    }

    /* Kyoto skyline silhouette */
    .hero-skyline {
      position: absolute;
      left: 0;
      right: 0;
      bottom: -2px;
      z-index: -1;
      height: clamp(120px, 26vw, 220px);
      color: var(--ink);
      opacity: 0.13;
      transition: opacity 0.8s var(--ease);
    }

    [data-theme="night"] .hero-skyline {
      opacity: 0.35;
    }

    .hero-skyline svg {
      width: 100%;
      height: 100%;
    }

    /* Vertical side kanji */
    .hero-side-kanji {
      position: absolute;
      top: 22%;
      left: calc(var(--gutter) * 0.35);
      font-size: clamp(15px, 3.4vw, 20px);
      color: var(--ink-faint);
      opacity: 0.8;
      pointer-events: none;
    }

    .hero-content {
      position: relative;
      max-width: 640px;
    }

    .hero-kicker {
      display: inline-flex;
      align-items: center;
      gap: 10px;
      font-family: var(--font-mono);
      font-size: 11px;
      letter-spacing: 0.26em;
      text-transform: uppercase;
      color: var(--ink-soft);
      margin-bottom: 18px;
    }

    .hero-kicker .dot {
      width: 7px;
      height: 7px;
      border-radius: 50%;
      background: var(--accent-2);
      animation: pulse 2.2s ease-in-out infinite;
    }

    @keyframes pulse {

      0%,
      100% {
        transform: scale(1);
        opacity: 1;
      }

      50% {
        transform: scale(1.5);
        opacity: 0.5;
      }
    }

    .hero-title {
      font-size: clamp(52px, 16vw, 108px);
      line-height: 0.98;
      letter-spacing: 0.04em;
      margin-bottom: 6px;
    }

    .hero-title .t-line {
      display: block;
      overflow: hidden;
    }

    .hero-title .t-line span {
      display: inline-block;
      transform: translateY(110%);
      animation: riseIn 1s var(--ease) forwards;
    }

    .hero-title .t-line:nth-child(2) span {
      animation-delay: 0.12s;
      color: var(--accent);
    }

    @keyframes riseIn {
      to {
        transform: none;
      }
    }

    .hero-sub {
      font-family: var(--font-display);
      font-size: clamp(15px, 4vw, 19px);
      letter-spacing: 0.42em;
      color: var(--ink-soft);
      margin-bottom: 16px;
      min-height: 1.6em;
    }

    .hero-desc {
      color: var(--ink-soft);
      font-size: 15px;
      max-width: 46ch;
      margin-bottom: 28px;
    }

    .hero-desc b {
      color: var(--ink);
      font-weight: 500;
    }

    .hero-cta {
      display: flex;
      flex-wrap: wrap;
      gap: 12px;
      margin-bottom: 34px;
    }

    .hero-meta {
      display: flex;
      gap: 22px;
      flex-wrap: wrap;
      font-family: var(--font-mono);
      font-size: 11px;
      letter-spacing: 0.1em;
      color: var(--ink-faint);
      border-top: 1px solid var(--line);
      padding-top: 16px;
    }

    .hero-meta b {
      color: var(--ink);
      font-weight: 500;
    }

    .hero-meta .sep {
      color: var(--accent);
    }

    /* Steam rising from bottom corner */
    .hero-steam {
      position: absolute;
      right: 10%;
      bottom: 20%;
      width: 44px;
      height: 120px;
      opacity: 0.5;
      pointer-events: none;
    }

    .hero-steam i {
      position: absolute;
      bottom: 0;
      left: 50%;
      width: 10px;
      height: 10px;
      border-radius: 50%;
      background: var(--ink-faint);
      filter: blur(6px);
      animation: steamUp 4s ease-in-out infinite;
    }

    .hero-steam i:nth-child(2) {
      animation-delay: 1.3s;
      left: 30%;
    }

    .hero-steam i:nth-child(3) {
      animation-delay: 2.6s;
      left: 68%;
    }

    @keyframes steamUp {
      0% {
        transform: translateY(0) scale(1);
        opacity: 0;
      }

      25% {
        opacity: 0.7;
      }

      100% {
        transform: translateY(-110px) translateX(8px) scale(2.4);
        opacity: 0;
      }
    }

    .scroll-hint {
      position: absolute;
      left: 50%;
      bottom: 12px;
      transform: translateX(-50%);
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 8px;
      font-family: var(--font-mono);
      font-size: 9px;
      letter-spacing: 0.3em;
      text-transform: uppercase;
      color: var(--ink-faint);
    }

    .scroll-hint .wheel {
      width: 1.6px;
      height: 34px;
      background: var(--line-strong);
      position: relative;
      overflow: hidden;
    }

    .scroll-hint .wheel::after {
      content: "";
      position: absolute;
      top: -100%;
      left: 0;
      width: 100%;
      height: 100%;
      background: var(--accent);
      animation: wheelDrop 1.8s var(--ease-io) infinite;
    }

    @keyframes wheelDrop {
      0% {
        top: -100%;
      }

      60%,
      100% {
        top: 100%;
      }
    }

    /* ------------------------------------------------------------
09. MARQUEE
------------------------------------------------------------ */
    .marquee {
      overflow: hidden;
      border-block: 1px solid var(--line);
      background: var(--bg-alt);
      padding: 13px 0;
      transition: background 0.7s var(--ease);
    }

    .marquee-track {
      display: flex;
      gap: 0;
      width: max-content;
      animation: marqueeScroll 26s linear infinite;
    }

    .marquee:hover .marquee-track {
      animation-play-state: paused;
    }

    .marquee-track span {
      display: inline-flex;
      align-items: center;
      gap: 26px;
      padding-right: 26px;
      font-family: var(--font-display);
      font-size: 15px;
      letter-spacing: 0.3em;
      color: var(--ink-soft);
      white-space: nowrap;
    }

    .marquee-track span i {
      font-style: normal;
      color: var(--accent);
      font-size: 12px;
    }

    @keyframes marqueeScroll {
      to {
        transform: translateX(-50%);
      }
    }

    /* ------------------------------------------------------------
10. SECTION SCAFFOLDING
------------------------------------------------------------ */
    .section {
      padding: clamp(70px, 14vw, 120px) 0;
      position: relative;
    }

    .section-head {
      margin-bottom: clamp(34px, 7vw, 56px);
    }

    .section-head .h-section {
      margin-bottom: 12px;
    }

    .section-head .title-jp {
      display: block;
      font-family: var(--font-display);
      font-size: 14px;
      letter-spacing: 0.5em;
      color: var(--ink-faint);
      margin-top: 10px;
    }

    .section-head.center {
      text-align: center;
    }

    .section-head.center .eyebrow {
      justify-content: center;
    }

    .section-head.center .eyebrow::after {
      content: "";
      width: 28px;
      height: 1px;
      background: var(--accent);
    }

    .section-head.center .lede {
      margin-inline: auto;
    }

    /* ------------------------------------------------------------
11. ABOUT / STORY
------------------------------------------------------------ */
    #about {
      background: var(--bg);
    }

    .about-grid {
      display: grid;
      gap: 34px;
    }

    .about-art {
      position: relative;
      border-radius: var(--radius-lg);
      overflow: hidden;
      aspect-ratio: 4 / 4.6;
      background: var(--bg-alt);
      box-shadow: var(--shadow);
    }

    .about-art svg {
      width: 100%;
      height: 100%;
    }

    .about-art .art-stamp {
      position: absolute;
      bottom: 16px;
      right: 16px;
      width: 46px;
      height: 46px;
      background: var(--accent);
      color: #fff;
      border-radius: 6px;
      display: grid;
      place-items: center;
      font-family: var(--font-display);
      font-size: 19px;
      transform: rotate(-4deg);
      box-shadow: var(--shadow-soft);
    }

    .about-copy .drop-cap::first-letter {
      font-family: var(--font-display);
      font-size: 3.1em;
      float: left;
      line-height: 0.85;
      padding-right: 10px;
      padding-top: 5px;
      color: var(--accent);
    }

    .about-copy p {
      color: var(--ink-soft);
      margin-bottom: 16px;
      font-size: 15px;
    }

    .about-copy p b {
      color: var(--ink);
      font-weight: 500;
    }

    .about-sign {
      display: flex;
      align-items: center;
      gap: 14px;
      margin-top: 24px;
      padding-top: 20px;
      border-top: 1px solid var(--line);
    }

    .about-sign .sig-kanji {
      font-family: var(--font-display);
      font-size: 26px;
      color: var(--accent);
      letter-spacing: 0.2em;
    }

    .about-sign .sig-meta {
      font-family: var(--font-mono);
      font-size: 10.5px;
      letter-spacing: 0.14em;
      text-transform: uppercase;
      color: var(--ink-faint);
      line-height: 1.8;
    }

    /* Stats strip */
    .stats-row {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 1px;
      background: var(--line);
      border: 1px solid var(--line);
      border-radius: var(--radius);
      overflow: hidden;
      margin-top: 44px;
    }

    .stat-cell {
      background: var(--surface);
      padding: 22px 18px;
      transition: background 0.5s var(--ease);
    }

    .stat-cell .stat-num {
      font-family: var(--font-display);
      font-size: clamp(28px, 7vw, 40px);
      color: var(--accent);
      line-height: 1;
    }

    .stat-cell .stat-num sub {
      font-size: 0.45em;
      color: var(--ink-faint);
    }

    .stat-cell .stat-label {
      font-family: var(--font-mono);
      font-size: 10px;
      letter-spacing: 0.18em;
      text-transform: uppercase;
      color: var(--ink-faint);
      margin-top: 8px;
    }

    /* ------------------------------------------------------------
12. DAY-NIGHT CONCEPT
------------------------------------------------------------ */
    #concept {
      background: var(--bg-alt);
      transition: background 0.7s var(--ease);
    }

    .concept-cards {
      display: grid;
      gap: 18px;
    }

    .concept-card {
      position: relative;
      border: 1px solid var(--line);
      border-radius: var(--radius-lg);
      background: var(--surface);
      padding: 30px 24px 26px;
      overflow: hidden;
      transition: transform 0.5s var(--ease), border-color 0.4s, background 0.6s var(--ease);
    }

    .concept-card:active {
      transform: scale(0.985);
    }

    .concept-card .cc-icon {
      width: 52px;
      height: 52px;
      border-radius: 14px;
      display: grid;
      place-items: center;
      margin-bottom: 18px;
      background: var(--accent-soft);
      color: var(--accent);
    }

    .concept-card.is-night .cc-icon {
      background: var(--accent-2-soft);
      color: var(--accent-2);
    }

    .concept-card .cc-icon svg {
      width: 26px;
      height: 26px;
    }

    .concept-card .cc-time {
      font-family: var(--font-mono);
      font-size: 10.5px;
      letter-spacing: 0.22em;
      text-transform: uppercase;
      color: var(--ink-faint);
      margin-bottom: 6px;
    }

    .concept-card h3 {
      font-size: 22px;
      margin-bottom: 4px;
    }

    .concept-card .cc-jp {
      font-family: var(--font-display);
      font-size: 13px;
      letter-spacing: 0.4em;
      color: var(--ink-faint);
      margin-bottom: 14px;
    }

    .concept-card p {
      color: var(--ink-soft);
      font-size: 14.5px;
      margin-bottom: 18px;
    }

    .concept-card .cc-list {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
    }

    .concept-card .cc-list li {
      font-family: var(--font-mono);
      font-size: 10.5px;
      letter-spacing: 0.1em;
      padding: 5px 12px;
      border: 1px solid var(--line);
      border-radius: 999px;
      color: var(--ink-soft);
    }

    .concept-card .cc-big {
      position: absolute;
      right: -8px;
      bottom: -26px;
      font-family: var(--font-display);
      font-size: 120px;
      line-height: 1;
      color: var(--ink);
      opacity: 0.05;
      pointer-events: none;
      user-select: none;
    }

    /* ------------------------------------------------------------
13. MENU (TABS / CARDS / MODAL)
------------------------------------------------------------ */
    #menu {
      background: var(--bg);
    }

    .menu-tabs {
      display: flex;
      gap: 8px;
      overflow-x: auto;
      scrollbar-width: none;
      -webkit-overflow-scrolling: touch;
      padding-bottom: 6px;
      margin-bottom: 26px;
    }

    .menu-tabs::-webkit-scrollbar {
      display: none;
    }

    .menu-tab {
      flex-shrink: 0;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 11px 20px;
      border-radius: 999px;
      border: 1px solid var(--line-strong);
      font-size: 13.5px;
      font-weight: 500;
      letter-spacing: 0.06em;
      color: var(--ink-soft);
      background: var(--surface);
      transition: all 0.35s var(--ease);
      min-height: 44px;
    }

    .menu-tab .tab-jp {
      font-family: var(--font-display);
      font-size: 12px;
      color: var(--ink-faint);
      transition: color 0.35s;
    }

    .menu-tab.active {
      background: var(--ink);
      border-color: var(--ink);
      color: var(--bg);
    }

    .menu-tab.active .tab-jp {
      color: var(--accent);
    }

    [data-theme="night"] .menu-tab.active .tab-jp {
      color: var(--gold);
    }

    .menu-note {
      display: flex;
      align-items: center;
      gap: 10px;
      font-family: var(--font-mono);
      font-size: 11px;
      letter-spacing: 0.08em;
      color: var(--ink-faint);
      margin-bottom: 22px;
      min-height: 20px;
    }

    .menu-note .note-dot {
      width: 6px;
      height: 6px;
      border-radius: 50%;
      background: var(--accent);
      flex-shrink: 0;
    }

    .menu-grid {
      display: grid;
      gap: 14px;
    }

    .menu-card {
      display: grid;
      grid-template-columns: 64px 1fr auto;
      gap: 16px;
      align-items: center;
      padding: 16px;
      border: 1px solid var(--line);
      border-radius: var(--radius);
      background: var(--surface);
      cursor: pointer;
      transition: transform 0.45s var(--ease), border-color 0.35s, box-shadow 0.45s var(--ease), opacity 0.4s;
      animation: cardIn 0.5s var(--ease) backwards;
    }

    @keyframes cardIn {
      from {
        opacity: 0;
        transform: translateY(16px);
      }
    }

    .menu-card:hover {
      border-color: var(--accent);
      box-shadow: var(--shadow-soft);
      transform: translateY(-2px);
    }

    .menu-card:active {
      transform: scale(0.985);
    }

    .menu-card .mc-glyph {
      width: 64px;
      height: 64px;
      border-radius: var(--radius-sm);
      background: var(--bg-alt);
      display: grid;
      place-items: center;
      color: var(--accent);
      transition: background 0.5s var(--ease);
    }

    .menu-card .mc-glyph svg {
      width: 30px;
      height: 30px;
    }

    .menu-card .mc-body h3 {
      font-family: var(--font-body);
      font-weight: 500;
      font-size: 15.5px;
      letter-spacing: 0.02em;
    }

    .menu-card .mc-body .mc-jp {
      font-family: var(--font-display);
      font-size: 11.5px;
      color: var(--ink-faint);
      letter-spacing: 0.2em;
      margin-top: 1px;
    }

    .menu-card .mc-body .mc-desc {
      font-size: 12.5px;
      color: var(--ink-faint);
      margin-top: 5px;
      display: -webkit-box;
      line-clamp: 1;
      -webkit-box-orient: vertical;
      overflow: hidden;
    }

    .menu-card .mc-side {
      text-align: right;
      display: flex;
      flex-direction: column;
      align-items: flex-end;
      gap: 7px;
    }

    .menu-card .mc-price {
      font-family: var(--font-mono);
      font-size: 15px;
      color: var(--ink);
      letter-spacing: 0.02em;
    }

    .menu-card .mc-tags {
      display: flex;
      gap: 4px;
    }

    .menu-card .mc-tags .tag {
      padding: 2px 7px;
      font-size: 8.5px;
    }

    /* Menu item modal */
    #menuModal {
      position: fixed;
      inset: 0;
      z-index: 70;
      display: flex;
      align-items: flex-end;
      justify-content: center;
      visibility: hidden;
    }

    #menuModal.open {
      visibility: visible;
    }

    #menuModal .modal-backdrop {
      position: absolute;
      inset: 0;
      background: rgba(10, 8, 6, 0.55);
      -webkit-backdrop-filter: blur(4px);
      backdrop-filter: blur(4px);
      opacity: 0;
      transition: opacity 0.4s var(--ease);
    }

    #menuModal.open .modal-backdrop {
      opacity: 1;
    }

    .modal-sheet {
      position: relative;
      width: 100%;
      max-width: 520px;
      max-height: 86dvh;
      overflow-y: auto;
      background: var(--surface);
      border-radius: var(--radius-lg) var(--radius-lg) 0 0;
      padding: 14px 24px 34px;
      transform: translateY(100%);
      transition: transform 0.5s var(--ease);
      box-shadow: var(--shadow);
    }

    #menuModal.open .modal-sheet {
      transform: none;
    }

    .modal-sheet .grab {
      width: 42px;
      height: 4px;
      border-radius: 2px;
      background: var(--line-strong);
      margin: 0 auto 20px;
    }

    .modal-sheet .modal-close {
      position: absolute;
      top: 14px;
      right: 16px;
      width: 36px;
      height: 36px;
      border-radius: 50%;
      border: 1px solid var(--line);
      display: grid;
      place-items: center;
      color: var(--ink-soft);
      transition: color 0.3s, border-color 0.3s;
    }

    .modal-sheet .modal-close:hover {
      color: var(--accent);
      border-color: var(--accent);
    }

    .modal-glyph {
      width: 100%;
      aspect-ratio: 2.6;
      border-radius: var(--radius);
      background: var(--bg-alt);
      display: grid;
      place-items: center;
      color: var(--accent);
      margin-bottom: 22px;
      position: relative;
      overflow: hidden;
    }

    .modal-glyph svg {
      width: 64px;
      height: 64px;
    }

    .modal-glyph .glyph-jp {
      position: absolute;
      right: 16px;
      bottom: 10px;
      font-family: var(--font-display);
      font-size: 40px;
      opacity: 0.16;
      letter-spacing: 0.2em;
    }

    .modal-title-row {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      gap: 14px;
      margin-bottom: 4px;
    }

    .modal-title-row h3 {
      font-size: 23px;
    }

    .modal-title-row .m-price {
      font-family: var(--font-mono);
      font-size: 19px;
      color: var(--accent);
      white-space: nowrap;
    }

    .modal-jp {
      font-family: var(--font-display);
      font-size: 13px;
      letter-spacing: 0.34em;
      color: var(--ink-faint);
      margin-bottom: 16px;
    }

    .modal-desc {
      color: var(--ink-soft);
      font-size: 14.5px;
      margin-bottom: 20px;
    }

    .modal-specs {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 1px;
      background: var(--line);
      border: 1px solid var(--line);
      border-radius: var(--radius-sm);
      overflow: hidden;
      margin-bottom: 22px;
    }

    .modal-specs .spec {
      background: var(--surface);
      padding: 12px 14px;
    }

    .modal-specs .spec dt {
      font-family: var(--font-mono);
      font-size: 9.5px;
      letter-spacing: 0.2em;
      text-transform: uppercase;
      color: var(--ink-faint);
    }

    .modal-specs .spec dd {
      font-size: 13.5px;
      margin-top: 3px;
    }

    .modal-note {
      display: flex;
      gap: 10px;
      align-items: flex-start;
      font-size: 12.5px;
      color: var(--ink-faint);
      border-top: 1px dashed var(--line-strong);
      padding-top: 16px;
    }

    .modal-note svg {
      width: 16px;
      height: 16px;
      flex-shrink: 0;
      margin-top: 2px;
      color: var(--accent);
    }

    /* ------------------------------------------------------------
14. BREWERY TIMELINE
------------------------------------------------------------ */
    #craft {
      background: var(--bg-alt);
      transition: background 0.7s var(--ease);
    }

    .timeline {
      position: relative;
      padding-left: 34px;
    }

    .timeline::before {
      content: "";
      position: absolute;
      left: 11px;
      top: 8px;
      bottom: 8px;
      width: 1.6px;
      background: linear-gradient(180deg, var(--accent), var(--line-strong));
    }

    .tl-item {
      position: relative;
      padding-bottom: 36px;
    }

    .tl-item:last-child {
      padding-bottom: 0;
    }

    .tl-item .tl-node {
      position: absolute;
      left: -34px;
      top: 2px;
      width: 24px;
      height: 24px;
      border-radius: 50%;
      background: var(--bg-alt);
      border: 1.6px solid var(--accent);
      display: grid;
      place-items: center;
      transition: background 0.7s var(--ease), transform 0.5s var(--ease);
    }

    .tl-item.in-view .tl-node {
      background: var(--accent);
      transform: scale(1.05);
    }

    .tl-item .tl-node i {
      width: 7px;
      height: 7px;
      border-radius: 50%;
      background: var(--accent);
      transition: background 0.4s;
    }

    .tl-item.in-view .tl-node i {
      background: var(--bg);
    }

    .tl-item .tl-step {
      font-family: var(--font-mono);
      font-size: 10px;
      letter-spacing: 0.26em;
      text-transform: uppercase;
      color: var(--accent);
      margin-bottom: 4px;
    }

    .tl-item h3 {
      font-size: 19.5px;
      margin-bottom: 3px;
    }

    .tl-item .tl-jp {
      font-family: var(--font-display);
      font-size: 12px;
      letter-spacing: 0.34em;
      color: var(--ink-faint);
      margin-bottom: 10px;
    }

    .tl-item p {
      font-size: 14px;
      color: var(--ink-soft);
      max-width: 54ch;
    }

    .tl-item .tl-fact {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      margin-top: 12px;
      font-family: var(--font-mono);
      font-size: 11px;
      letter-spacing: 0.08em;
      color: var(--ink);
      background: var(--surface);
      border: 1px solid var(--line);
      padding: 7px 13px;
      border-radius: 999px;
    }

    .tl-item .tl-fact svg {
      width: 13px;
      height: 13px;
      color: var(--gold);
    }

    /* ------------------------------------------------------------
15. SEASONAL CAROUSEL
------------------------------------------------------------ */
    #seasonal {
      background: var(--bg);
      overflow: hidden;
    }

    .carousel {
      display: flex;
      gap: 16px;
      overflow-x: auto;
      scroll-snap-type: x mandatory;
      scrollbar-width: none;
      padding: 6px var(--gutter) 22px;
      margin-inline: calc(var(--gutter) * -1);
      -webkit-overflow-scrolling: touch;
    }

    .carousel::-webkit-scrollbar {
      display: none;
    }

    .season-card {
      flex: 0 0 min(78vw, 320px);
      scroll-snap-align: start;
      border: 1px solid var(--line);
      border-radius: var(--radius-lg);
      background: var(--surface);
      overflow: hidden;
      transition: transform 0.5s var(--ease), box-shadow 0.5s var(--ease);
    }

    .season-card:hover {
      transform: translateY(-4px);
      box-shadow: var(--shadow);
    }

    .season-card .sc-art {
      aspect-ratio: 16 / 10;
      display: grid;
      place-items: center;
      position: relative;
      overflow: hidden;
    }

    .season-card .sc-art svg {
      width: 100%;
      height: 100%;
    }

    .season-card .sc-badge {
      position: absolute;
      top: 12px;
      left: 12px;
      font-family: var(--font-mono);
      font-size: 9.5px;
      letter-spacing: 0.2em;
      text-transform: uppercase;
      background: var(--ink);
      color: var(--bg);
      padding: 5px 11px;
      border-radius: 999px;
    }

    .season-card .sc-body {
      padding: 18px 18px 20px;
    }

    .season-card h3 {
      font-size: 18.5px;
      margin-bottom: 2px;
    }

    .season-card .sc-jp {
      font-family: var(--font-display);
      font-size: 11.5px;
      letter-spacing: 0.3em;
      color: var(--ink-faint);
      margin-bottom: 10px;
    }

    .season-card p {
      font-size: 13.5px;
      color: var(--ink-soft);
      margin-bottom: 14px;
    }

    .season-card .sc-foot {
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .season-card .sc-price {
      font-family: var(--font-mono);
      font-size: 15.5px;
      color: var(--accent);
    }

    .season-card .sc-until {
      font-family: var(--font-mono);
      font-size: 10px;
      letter-spacing: 0.12em;
      color: var(--ink-faint);
      text-transform: uppercase;
    }

    .carousel-ctl {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-top: 6px;
    }

    .carousel-dots {
      display: flex;
      gap: 8px;
    }

    .carousel-dots i {
      width: 7px;
      height: 7px;
      border-radius: 50%;
      background: var(--line-strong);
      transition: background 0.3s, transform 0.3s;
    }

    .carousel-dots i.on {
      background: var(--accent);
      transform: scale(1.25);
    }

    .carousel-btns {
      display: flex;
      gap: 10px;
    }

    .carousel-btns button {
      width: 44px;
      height: 44px;
      border-radius: 50%;
      border: 1px solid var(--line-strong);
      display: grid;
      place-items: center;
      color: var(--ink);
      background: var(--surface);
      transition: background 0.3s, color 0.3s, border-color 0.3s;
    }

    .carousel-btns button:hover {
      background: var(--accent);
      border-color: var(--accent);
      color: #fff;
    }

    .carousel-btns svg {
      width: 16px;
      height: 16px;
    }

    /* ------------------------------------------------------------
16. GALLERY
------------------------------------------------------------ */
    #gallery {
      background: var(--bg-alt);
      transition: background 0.7s var(--ease);
    }

    .gallery-strip {
      display: flex;
      gap: 14px;
      overflow-x: auto;
      scrollbar-width: none;
      padding-bottom: 12px;
      margin-inline: calc(var(--gutter) * -1);
      padding-inline: var(--gutter);
      cursor: grab;
    }

    .gallery-strip::-webkit-scrollbar {
      display: none;
    }

    .gallery-strip.dragging {
      cursor: grabbing;
      scroll-snap-type: none;
    }

    .g-tile {
      flex: 0 0 min(62vw, 250px);
      aspect-ratio: 3 / 4;
      border-radius: var(--radius);
      overflow: hidden;
      position: relative;
      border: 1px solid var(--line);
      background: var(--surface);
      user-select: none;
    }

    .g-tile svg {
      width: 100%;
      height: 100%;
      pointer-events: none;
    }

    .g-tile .g-label {
      position: absolute;
      left: 12px;
      bottom: 12px;
      right: 12px;
      display: flex;
      justify-content: space-between;
      align-items: baseline;
      font-family: var(--font-mono);
      font-size: 10px;
      letter-spacing: 0.16em;
      text-transform: uppercase;
      color: var(--ink);
      background: var(--surface);
      border: 1px solid var(--line);
      border-radius: 999px;
      padding: 8px 13px;
    }

    .g-tile .g-label b {
      font-family: var(--font-display);
      font-size: 13px;
      letter-spacing: 0.2em;
      font-weight: 600;
    }

    .g-hint {
      margin-top: 14px;
      font-family: var(--font-mono);
      font-size: 10.5px;
      letter-spacing: 0.18em;
      text-transform: uppercase;
      color: var(--ink-faint);
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .g-hint svg {
      width: 15px;
      height: 15px;
      animation: swipeHint 2s ease-in-out infinite;
    }

    @keyframes swipeHint {

      0%,
      100% {
        transform: translateX(0);
      }

      50% {
        transform: translateX(8px);
      }
    }

    /* ------------------------------------------------------------
17. OMIKUJI FORTUNE
------------------------------------------------------------ */
    #omikuji {
      background: var(--bg);
    }

    .omikuji-box {
      border: 1px solid var(--line);
      border-radius: var(--radius-lg);
      background: var(--surface);
      padding: clamp(28px, 6vw, 44px) 24px;
      text-align: center;
      position: relative;
      overflow: hidden;
    }

    .omikuji-box .oki-deco {
      position: absolute;
      inset: 0;
      pointer-events: none;
      opacity: 0.5;
      background:
        radial-gradient(circle at 12% 18%, var(--accent-soft) 0, transparent 140px),
        radial-gradient(circle at 88% 82%, var(--accent-2-soft) 0, transparent 160px);
    }

    .oki-cylinder {
      position: relative;
      width: 128px;
      height: 168px;
      margin: 8px auto 22px;
      cursor: pointer;
      -webkit-tap-highlight-color: transparent;
    }

    .oki-cylinder .oki-body {
      position: absolute;
      bottom: 0;
      left: 50%;
      transform: translateX(-50%);
      width: 96px;
      height: 128px;
      background: linear-gradient(180deg, #a34a2f, #7e3320);
      border-radius: 14px 14px 8px 8px;
      box-shadow: inset 0 8px 14px rgba(255, 255, 255, 0.18), inset 0 -12px 18px rgba(0, 0, 0, 0.3), var(--shadow-soft);
      display: grid;
      place-items: center;
    }

    [data-theme="night"] .oki-cylinder .oki-body {
      background: linear-gradient(180deg, #8c6a2f, #6b4e1e);
    }

    .oki-cylinder .oki-body .oki-kanji {
      font-family: var(--font-display);
      font-size: 34px;
      color: rgba(255, 248, 235, 0.92);
      writing-mode: vertical-rl;
      letter-spacing: 0.2em;
    }

    .oki-cylinder .oki-hole {
      position: absolute;
      top: 6px;
      left: 50%;
      transform: translateX(-50%);
      width: 128px;
      height: 30px;
      background: #5c2415;
      border-radius: 50%;
      box-shadow: inset 0 4px 8px rgba(0, 0, 0, 0.5);
    }

    [data-theme="night"] .oki-cylinder .oki-hole {
      background: #4a350f;
    }

    .oki-cylinder .oki-stick {
      position: absolute;
      top: -26px;
      left: 50%;
      width: 9px;
      height: 74px;
      background: linear-gradient(180deg, #e8d3a0, #cdb076);
      border-radius: 4px;
      transform: translateX(-50%) rotate(8deg);
      transform-origin: bottom center;
      transition: transform 0.3s var(--ease);
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
    }

    .oki-cylinder .oki-stick::after {
      content: "吉";
      position: absolute;
      top: 4px;
      left: 50%;
      transform: translateX(-50%);
      font-family: var(--font-display);
      font-size: 8px;
      color: #7e3320;
    }

    .oki-cylinder.shaking {
      animation: okiShake 0.5s ease-in-out;
    }

    @keyframes okiShake {

      0%,
      100% {
        transform: rotate(0);
      }

      20% {
        transform: rotate(-7deg);
      }

      40% {
        transform: rotate(6deg);
      }

      60% {
        transform: rotate(-5deg);
      }

      80% {
        transform: rotate(4deg);
      }
    }

    .oki-cylinder.drawn .oki-stick {
      animation: stickRise 0.9s var(--ease) forwards;
    }

    @keyframes stickRise {
      0% {
        transform: translateX(-50%) rotate(8deg) translateY(0);
      }

      60% {
        transform: translateX(-50%) rotate(-4deg) translateY(-34px);
      }

      100% {
        transform: translateX(-50%) rotate(-2deg) translateY(-22px);
      }
    }

    .oki-title {
      font-size: 23px;
      margin-bottom: 6px;
    }

    .oki-sub {
      font-size: 13.5px;
      color: var(--ink-soft);
      max-width: 40ch;
      margin: 0 auto 22px;
    }

    .oki-action {
      display: inline-flex;
    }

    /* Fortune card result */
    .oki-result {
      display: none;
      max-width: 360px;
      margin: 0 auto;
      animation: fortuneIn 0.7s var(--ease);
    }

    .oki-result.show {
      display: block;
    }

    @keyframes fortuneIn {
      from {
        opacity: 0;
        transform: translateY(20px) scale(0.96);
      }
    }

    .fortune-card {
      background: #fdf8ec;
      color: #2a241d;
      border-radius: var(--radius);
      padding: 26px 22px;
      text-align: center;
      border: 1px solid rgba(126, 51, 32, 0.2);
      box-shadow: var(--shadow);
      position: relative;
    }

    [data-theme="night"] .fortune-card {
      background: #f4ead2;
    }

    .fortune-card .f-rank {
      font-family: var(--font-display);
      font-size: 42px;
      color: #a34a2f;
      letter-spacing: 0.1em;
    }

    .fortune-card .f-rank-en {
      font-family: var(--font-mono);
      font-size: 10px;
      letter-spacing: 0.3em;
      text-transform: uppercase;
      color: #8a7d6d;
      margin-bottom: 14px;
    }

    .fortune-card .f-poem {
      font-family: var(--font-display);
      font-size: 14.5px;
      line-height: 2.1;
      color: #3d362c;
      margin-bottom: 16px;
    }

    .fortune-card .f-divider {
      width: 46px;
      height: 1px;
      background: rgba(126, 51, 32, 0.3);
      margin: 0 auto 16px;
    }

    .fortune-card .f-drink {
      font-size: 12.5px;
      color: #5c5344;
    }

    .fortune-card .f-drink b {
      color: #a34a2f;
      font-weight: 500;
    }

    .fortune-card .f-stamp {
      position: absolute;
      top: 12px;
      right: 12px;
      width: 34px;
      height: 34px;
      border-radius: 4px;
      background: #a34a2f;
      color: #fdf8ec;
      font-family: var(--font-display);
      font-size: 15px;
      display: grid;
      place-items: center;
      transform: rotate(6deg);
    }

    .oki-again {
      margin-top: 18px;
    }

    .oki-count {
      margin-top: 12px;
      font-family: var(--font-mono);
      font-size: 10px;
      letter-spacing: 0.2em;
      text-transform: uppercase;
      color: var(--ink-faint);
    }

    /* ------------------------------------------------------------
18. EVENTS
------------------------------------------------------------ */
    #events {
      background: var(--bg-alt);
      transition: background 0.7s var(--ease);
    }

    .event-list {
      display: grid;
      gap: 14px;
    }

    .event-card {
      display: grid;
      grid-template-columns: 66px 1fr;
      gap: 18px;
      padding: 18px;
      border: 1px solid var(--line);
      border-radius: var(--radius);
      background: var(--surface);
      transition: border-color 0.35s, transform 0.45s var(--ease);
    }

    .event-card:hover {
      border-color: var(--accent);
    }

    .event-date {
      text-align: center;
      border: 1px solid var(--line-strong);
      border-radius: var(--radius-sm);
      padding: 10px 4px;
      align-self: start;
      background: var(--bg-alt);
      transition: background 0.5s var(--ease);
    }

    .event-date .ed-month {
      font-family: var(--font-mono);
      font-size: 9.5px;
      letter-spacing: 0.24em;
      text-transform: uppercase;
      color: var(--accent);
    }

    .event-date .ed-day {
      font-family: var(--font-display);
      font-size: 27px;
      line-height: 1.15;
    }

    .event-date .ed-week {
      font-family: var(--font-mono);
      font-size: 9px;
      color: var(--ink-faint);
      letter-spacing: 0.1em;
    }

    .event-body h3 {
      font-size: 17.5px;
      margin-bottom: 2px;
    }

    .event-body .ev-jp {
      font-family: var(--font-display);
      font-size: 11px;
      letter-spacing: 0.3em;
      color: var(--ink-faint);
      margin-bottom: 8px;
    }

    .event-body p {
      font-size: 13px;
      color: var(--ink-soft);
      margin-bottom: 12px;
    }

    .event-foot {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 10px;
    }

    .event-meta {
      font-family: var(--font-mono);
      font-size: 10.5px;
      letter-spacing: 0.08em;
      color: var(--ink-faint);
    }

    .rsvp-btn {
      display: inline-flex;
      align-items: center;
      gap: 7px;
      padding: 8px 16px;
      border: 1px solid var(--ink);
      border-radius: 999px;
      font-size: 12px;
      font-weight: 500;
      letter-spacing: 0.08em;
      transition: all 0.35s var(--ease);
      min-height: 36px;
    }

    .rsvp-btn:hover {
      background: var(--ink);
      color: var(--bg);
    }

    .rsvp-btn.joined {
      background: var(--accent-2);
      border-color: var(--accent-2);
      color: #fff;
    }

    .rsvp-btn .check {
      display: none;
    }

    .rsvp-btn.joined .check {
      display: inline;
    }

    /* ------------------------------------------------------------
19. TESTIMONIALS
------------------------------------------------------------ */
    #voices {
      background: var(--bg);
    }

    .t-stage {
      position: relative;
      overflow: hidden;
    }

    .t-track {
      display: flex;
      transition: transform 0.65s var(--ease);
    }

    .t-slide {
      flex: 0 0 100%;
      padding: 6px 4px;
    }

    .t-card {
      border: 1px solid var(--line);
      border-radius: var(--radius-lg);
      background: var(--surface);
      padding: clamp(26px, 6vw, 40px) clamp(20px, 5vw, 34px);
      text-align: center;
      position: relative;
    }

    .t-card .t-mark {
      font-family: var(--font-display);
      font-size: 60px;
      line-height: 0.6;
      color: var(--accent);
      opacity: 0.35;
      margin-bottom: 18px;
    }

    .t-card blockquote {
      font-family: var(--font-display);
      font-size: clamp(16.5px, 4.4vw, 20px);
      line-height: 1.9;
      max-width: 34ch;
      margin: 0 auto 22px;
    }

    .t-card .t-author {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 12px;
    }

    .t-card .t-avatar {
      width: 42px;
      height: 42px;
      border-radius: 50%;
      display: grid;
      place-items: center;
      font-family: var(--font-display);
      font-size: 17px;
      color: #fff;
    }

    .t-card .t-name {
      text-align: left;
    }

    .t-card .t-name b {
      display: block;
      font-size: 14px;
      font-weight: 500;
    }

    .t-card .t-name span {
      font-family: var(--font-mono);
      font-size: 10px;
      letter-spacing: 0.14em;
      text-transform: uppercase;
      color: var(--ink-faint);
    }

    .t-nav {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 20px;
      margin-top: 26px;
    }

    .t-dots {
      display: flex;
      gap: 8px;
    }

    .t-dots button {
      width: 22px;
      height: 22px;
      display: grid;
      place-items: center;
    }

    .t-dots button i {
      width: 7px;
      height: 7px;
      border-radius: 50%;
      background: var(--line-strong);
      transition: background 0.3s, transform 0.3s;
      display: block;
    }

    .t-dots button.on i {
      background: var(--accent);
      transform: scale(1.3);
    }

    .t-arrow {
      width: 42px;
      height: 42px;
      border-radius: 50%;
      border: 1px solid var(--line-strong);
      display: grid;
      place-items: center;
      background: var(--surface);
      transition: background 0.3s, color 0.3s, border-color 0.3s;
    }

    .t-arrow:hover {
      background: var(--accent);
      border-color: var(--accent);
      color: #fff;
    }

    .t-arrow svg {
      width: 15px;
      height: 15px;
    }

    /* ------------------------------------------------------------
20. RESERVATION
------------------------------------------------------------ */
    #reserve {
      background: var(--bg-deep);
      color: var(--ink);
    }

    #reserve .section-head .h-section,
    #reserve .lede {
      color: inherit;
    }

    .reserve-panel {
      border: 1px solid var(--line-strong);
      border-radius: var(--radius-lg);
      background: var(--surface);
      overflow: hidden;
      box-shadow: var(--shadow);
    }

    .reserve-head {
      padding: 22px 22px 0;
      display: flex;
      justify-content: space-between;
      align-items: baseline;
      gap: 12px;
    }

    .reserve-head h3 {
      font-size: 20px;
    }

    .reserve-head .rh-jp {
      font-family: var(--font-display);
      font-size: 12px;
      letter-spacing: 0.4em;
      color: var(--ink-faint);
    }

    /* Steps indicator */
    .reserve-steps {
      display: flex;
      padding: 18px 22px 0;
      gap: 6px;
    }

    .reserve-steps i {
      flex: 1;
      height: 3px;
      border-radius: 2px;
      background: var(--line);
      transition: background 0.4s var(--ease);
    }

    .reserve-steps i.on {
      background: var(--accent);
    }

    .reserve-form {
      padding: 24px 22px 28px;
    }

    .f-step {
      display: none;
      animation: stepIn 0.5s var(--ease);
    }

    .f-step.active {
      display: block;
    }

    @keyframes stepIn {
      from {
        opacity: 0;
        transform: translateX(18px);
      }
    }

    .f-group {
      margin-bottom: 20px;
    }

    .f-label {
      display: flex;
      justify-content: space-between;
      align-items: baseline;
      font-family: var(--font-mono);
      font-size: 10.5px;
      letter-spacing: 0.22em;
      text-transform: uppercase;
      color: var(--ink-soft);
      margin-bottom: 9px;
    }

    .f-label .opt {
      color: var(--ink-faint);
      letter-spacing: 0.1em;
      text-transform: none;
    }

    .f-input,
    .f-select {
      width: 100%;
      padding: 14px 16px;
      border: 1px solid var(--line-strong);
      border-radius: var(--radius-sm);
      background: var(--bg);
      color: var(--ink);
      font-size: 15px;
      transition: border-color 0.3s, box-shadow 0.3s, background 0.5s var(--ease);
      min-height: 48px;
    }

    .f-input:focus,
    .f-select:focus {
      outline: none;
      border-color: var(--accent);
      box-shadow: 0 0 0 3px var(--accent-soft);
    }

    .f-input.invalid {
      border-color: #c0392b;
      box-shadow: 0 0 0 3px rgba(192, 57, 43, 0.12);
    }

    .f-error {
      display: none;
      font-size: 11.5px;
      color: #c0392b;
      margin-top: 6px;
      font-family: var(--font-mono);
      letter-spacing: 0.04em;
    }

    .f-error.show {
      display: block;
      animation: stepIn 0.3s var(--ease);
    }

    /* Party stepper */
    .party-stepper {
      display: flex;
      align-items: center;
      justify-content: space-between;
      border: 1px solid var(--line-strong);
      border-radius: var(--radius-sm);
      background: var(--bg);
      padding: 6px;
    }

    .party-stepper button {
      width: 44px;
      height: 44px;
      border-radius: var(--radius-sm);
      display: grid;
      place-items: center;
      font-size: 20px;
      color: var(--ink-soft);
      transition: background 0.25s, color 0.25s;
    }

    .party-stepper button:hover {
      background: var(--accent-soft);
      color: var(--accent);
    }

    .party-stepper .p-value {
      text-align: center;
    }

    .party-stepper .p-value b {
      font-family: var(--font-display);
      font-size: 24px;
      display: block;
      line-height: 1.1;
    }

    .party-stepper .p-value span {
      font-family: var(--font-mono);
      font-size: 9.5px;
      letter-spacing: 0.2em;
      text-transform: uppercase;
      color: var(--ink-faint);
    }

    /* Chip selectors */
    .chip-row {
      display: flex;
      flex-wrap: wrap;
      gap: 9px;
    }

    .chip {
      padding: 10px 17px;
      border: 1px solid var(--line-strong);
      border-radius: 999px;
      font-size: 13px;
      color: var(--ink-soft);
      background: var(--bg);
      transition: all 0.3s var(--ease);
      min-height: 42px;
    }

    .chip:hover {
      border-color: var(--accent);
      color: var(--accent);
    }

    .chip.on {
      background: var(--ink);
      border-color: var(--ink);
      color: var(--bg);
    }

    .chip.disabled {
      opacity: 0.35;
      pointer-events: none;
    }

    /* Time slots */
    .slot-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 9px;
    }

    .slot {
      padding: 11px 4px;
      border: 1px solid var(--line-strong);
      border-radius: var(--radius-sm);
      font-family: var(--font-mono);
      font-size: 12.5px;
      text-align: center;
      color: var(--ink-soft);
      background: var(--bg);
      transition: all 0.3s var(--ease);
    }

    .slot:hover {
      border-color: var(--accent);
      color: var(--accent);
    }

    .slot.on {
      background: var(--accent);
      border-color: var(--accent);
      color: #fff;
    }

    [data-theme="night"] .slot.on {
      color: #0f1318;
    }

    .slot.full {
      position: relative;
      opacity: 0.4;
      pointer-events: none;
    }

    .slot.full::after {
      content: "";
      position: absolute;
      left: 12%;
      right: 12%;
      top: 50%;
      height: 1px;
      background: var(--ink-faint);
      transform: rotate(-4deg);
    }

    .form-nav {
      display: flex;
      gap: 12px;
      margin-top: 26px;
    }

    .form-nav .btn {
      flex: 1;
    }

    .btn-back {
      border-color: var(--line-strong);
      color: var(--ink-soft);
    }

    .btn-back:hover {
      border-color: var(--ink);
      color: var(--ink);
      background: transparent;
    }

    /* Summary panel */
    .summary-box {
      border: 1px dashed var(--line-strong);
      border-radius: var(--radius);
      padding: 18px;
      margin-bottom: 22px;
      background: var(--bg);
    }

    .summary-box .s-row {
      display: flex;
      justify-content: space-between;
      gap: 12px;
      padding: 7px 0;
      font-size: 13.5px;
      border-bottom: 1px solid var(--line);
    }

    .summary-box .s-row:last-child {
      border-bottom: 0;
    }

    .summary-box .s-row span {
      color: var(--ink-faint);
      font-family: var(--font-mono);
      font-size: 10.5px;
      letter-spacing: 0.16em;
      text-transform: uppercase;
      padding-top: 3px;
    }

    .summary-box .s-row b {
      font-weight: 500;
      text-align: right;
    }

    /* Confirmation ticket */
    .reserve-done {
      display: none;
      padding: 34px 22px 36px;
      text-align: center;
      animation: stepIn 0.6s var(--ease);
    }

    .reserve-done.show {
      display: block;
    }

    .ticket {
      position: relative;
      max-width: 380px;
      margin: 0 auto;
      background: var(--bg);
      border: 1px solid var(--line-strong);
      border-radius: var(--radius);
      padding: 26px 22px;
      text-align: center;
    }

    .ticket::before,
    .ticket::after {
      content: "";
      position: absolute;
      top: 50%;
      width: 22px;
      height: 22px;
      border-radius: 50%;
      background: var(--surface);
      border: 1px solid var(--line-strong);
      transform: translateY(-50%);
    }

    .ticket::before {
      left: -12px;
      border-right-color: transparent;
    }

    .ticket::after {
      right: -12px;
      border-left-color: transparent;
    }

    .ticket .tk-stamp {
      width: 58px;
      height: 58px;
      margin: 0 auto 14px;
      border-radius: 50%;
      border: 2px solid var(--accent);
      color: var(--accent);
      display: grid;
      place-items: center;
      font-family: var(--font-display);
      font-size: 25px;
      transform: rotate(-8deg);
      animation: stampIn 0.6s cubic-bezier(0.34, 1.56, 0.64, 1) 0.3s backwards;
    }

    @keyframes stampIn {
      from {
        transform: rotate(-30deg) scale(1.8);
        opacity: 0;
      }
    }

    .ticket h4 {
      font-size: 19px;
      margin-bottom: 3px;
    }

    .ticket .tk-jp {
      font-family: var(--font-display);
      font-size: 11px;
      letter-spacing: 0.4em;
      color: var(--ink-faint);
      margin-bottom: 16px;
    }

    .ticket .tk-detail {
      font-size: 14px;
      color: var(--ink-soft);
      line-height: 2;
    }

    .ticket .tk-detail b {
      color: var(--ink);
      font-weight: 500;
    }

    .ticket .tk-code {
      margin-top: 16px;
      padding-top: 14px;
      border-top: 1px dashed var(--line-strong);
      font-family: var(--font-mono);
      font-size: 12px;
      letter-spacing: 0.3em;
      color: var(--accent);
    }

    .reserve-done .done-note {
      font-size: 12.5px;
      color: var(--ink-faint);
      margin-top: 18px;
      max-width: 38ch;
      margin-inline: auto;
    }

    /* ------------------------------------------------------------
21. FAQ
------------------------------------------------------------ */
    #faq {
      background: var(--bg);
    }

    .faq-list {
      display: grid;
      gap: 10px;
    }

    .faq-item {
      border: 1px solid var(--line);
      border-radius: var(--radius);
      background: var(--surface);
      overflow: hidden;
      transition: border-color 0.35s;
    }

    .faq-item.open {
      border-color: var(--accent);
    }

    .faq-q {
      width: 100%;
      display: flex;
      align-items: center;
      gap: 14px;
      padding: 17px 18px;
      text-align: left;
      font-size: 14.5px;
      font-weight: 500;
      letter-spacing: 0.02em;
      min-height: 52px;
    }

    .faq-q .q-num {
      font-family: var(--font-mono);
      font-size: 10.5px;
      color: var(--accent);
      letter-spacing: 0.1em;
      flex-shrink: 0;
    }

    .faq-q .q-icon {
      margin-left: auto;
      flex-shrink: 0;
      width: 26px;
      height: 26px;
      border-radius: 50%;
      border: 1px solid var(--line-strong);
      display: grid;
      place-items: center;
      transition: transform 0.45s var(--ease), background 0.3s, color 0.3s, border-color 0.3s;
    }

    .faq-q .q-icon svg {
      width: 11px;
      height: 11px;
    }

    .faq-item.open .q-icon {
      transform: rotate(45deg);
      background: var(--accent);
      border-color: var(--accent);
      color: #fff;
    }

    .faq-a {
      max-height: 0;
      overflow: hidden;
      transition: max-height 0.5s var(--ease);
    }

    .faq-a p {
      padding: 0 18px 18px 44px;
      font-size: 13.5px;
      color: var(--ink-soft);
    }

    /* ------------------------------------------------------------
22. NEWSLETTER
------------------------------------------------------------ */
    #newsletter {
      background: var(--bg-alt);
      transition: background 0.7s var(--ease);
    }

    .news-panel {
      border: 1px solid var(--line);
      border-radius: var(--radius-lg);
      padding: clamp(30px, 7vw, 52px) 24px;
      text-align: center;
      background: var(--surface);
      position: relative;
      overflow: hidden;
    }

    .news-panel .news-kanji {
      position: absolute;
      top: -18px;
      right: 6px;
      font-family: var(--font-display);
      font-size: 110px;
      color: var(--ink);
      opacity: 0.045;
      pointer-events: none;
      user-select: none;
    }

    .news-panel h2 {
      font-size: clamp(23px, 6vw, 32px);
      margin-bottom: 8px;
    }

    .news-panel .news-sub {
      font-size: 13.5px;
      color: var(--ink-soft);
      max-width: 42ch;
      margin: 0 auto 24px;
    }

    .news-form {
      display: flex;
      gap: 10px;
      max-width: 440px;
      margin: 0 auto;
    }

    .news-form .f-input {
      flex: 1;
    }

    .news-done {
      display: none;
      font-size: 14px;
      color: var(--accent-2);
      font-weight: 500;
    }

    .news-done.show {
      display: block;
      animation: stepIn 0.5s var(--ease);
    }

    .news-hint {
      margin-top: 14px;
      font-family: var(--font-mono);
      font-size: 9.5px;
      letter-spacing: 0.18em;
      text-transform: uppercase;
      color: var(--ink-faint);
    }

    /* ------------------------------------------------------------
23. FOOTER
------------------------------------------------------------ */
    #siteFooter {
      background: var(--bg-deep);
      padding: clamp(54px, 10vw, 90px) 0 0;
      position: relative;
      overflow: hidden;
      transition: background 0.7s var(--ease);
    }

    .footer-word {
      font-family: var(--font-display);
      font-size: clamp(64px, 20vw, 190px);
      line-height: 1;
      letter-spacing: 0.1em;
      text-align: center;
      color: var(--ink);
      opacity: 0.07;
      user-select: none;
      margin-bottom: clamp(30px, 6vw, 54px);
      white-space: nowrap;
    }

    .footer-grid {
      display: grid;
      gap: 34px;
      margin-bottom: 44px;
    }

    .footer-col h4 {
      font-family: var(--font-mono);
      font-size: 10.5px;
      letter-spacing: 0.26em;
      text-transform: uppercase;
      color: var(--accent);
      margin-bottom: 16px;
    }

    .footer-col p,
    .footer-col li {
      font-size: 13.5px;
      color: var(--ink-soft);
      line-height: 2;
    }

    .footer-col .f-hours {
      display: grid;
      grid-template-columns: auto 1fr;
      gap: 2px 16px;
    }

    .footer-col .f-hours dt {
      font-family: var(--font-mono);
      font-size: 11px;
      letter-spacing: 0.1em;
      color: var(--ink-faint);
      align-self: baseline;
      padding-top: 3px;
    }

    .footer-col .f-hours dd {
      font-size: 13.5px;
      color: var(--ink-soft);
    }

    .footer-col a {
      transition: color 0.3s;
    }

    .footer-col a:hover {
      color: var(--accent);
    }

    .footer-col .status-open {
      color: var(--accent-2);
      font-weight: 500;
    }

    .footer-col .status-closed {
      color: var(--accent);
      font-weight: 500;
    }

    .footer-social {
      display: flex;
      gap: 10px;
      margin-top: 4px;
    }

    .footer-social a {
      width: 42px;
      height: 42px;
      border-radius: 50%;
      border: 1px solid var(--line-strong);
      display: grid;
      place-items: center;
      transition: background 0.3s, color 0.3s, border-color 0.3s, transform 0.3s var(--ease);
    }

    .footer-social a:hover {
      background: var(--accent);
      border-color: var(--accent);
      color: #fff;
      transform: translateY(-3px);
    }

    .footer-social svg {
      width: 16px;
      height: 16px;
    }

    .footer-bottom {
      border-top: 1px solid var(--line);
      padding: 22px 0 calc(22px + env(safe-area-inset-bottom, 0px));
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
      justify-content: space-between;
      align-items: center;
    }

    .footer-bottom small {
      font-family: var(--font-mono);
      font-size: 10px;
      letter-spacing: 0.12em;
      color: var(--ink-faint);
    }

    .footer-bottom .clock {
      color: var(--accent);
    }

    /* ------------------------------------------------------------
24. TOASTS
------------------------------------------------------------ */
    #toastRack {
      position: fixed;
      left: 50%;
      bottom: 24px;
      transform: translateX(-50%);
      z-index: 120;
      display: flex;
      flex-direction: column;
      gap: 10px;
      align-items: center;
      width: min(92vw, 400px);
      pointer-events: none;
    }

    .toast {
      display: flex;
      align-items: center;
      gap: 11px;
      width: 100%;
      background: var(--ink);
      color: var(--bg);
      padding: 13px 18px;
      border-radius: 999px;
      font-size: 13px;
      letter-spacing: 0.02em;
      box-shadow: var(--shadow);
      opacity: 0;
      transform: translateY(18px) scale(0.96);
      transition: opacity 0.4s var(--ease), transform 0.4s var(--ease);
    }

    .toast.show {
      opacity: 1;
      transform: none;
    }

    .toast .t-ico {
      width: 20px;
      height: 20px;
      flex-shrink: 0;
      display: grid;
      place-items: center;
      color: var(--accent);
    }

    [data-theme="night"] .toast .t-ico {
      color: var(--gold);
    }

    .toast .t-ico svg {
      width: 16px;
      height: 16px;
    }

    /* ------------------------------------------------------------
25. KEYFRAMES (misc)
------------------------------------------------------------ */
    @keyframes fadeUp {
      from {
        opacity: 0;
        transform: translateY(22px);
      }

      to {
        opacity: 1;
        transform: none;
      }
    }

    @keyframes spinSlow {
      to {
        transform: rotate(360deg);
      }
    }

    @keyframes breathe {

      0%,
      100% {
        transform: scale(1);
      }

      50% {
        transform: scale(1.04);
      }
    }

    .spin-stamp {
      animation: spinSlow 24s linear infinite;
    }

    /* Rotating circular badge (hero corner) */
    .hero-badge {
      position: absolute;
      right: var(--gutter);
      bottom: 118px;
      width: 86px;
      height: 86px;
      pointer-events: none;
      opacity: 0.85;
    }

    .hero-badge svg {
      width: 100%;
      height: 100%;
    }

    .hero-badge text {
      font-family: var(--font-mono);
      font-size: 8.6px;
      letter-spacing: 2.6px;
      fill: var(--ink-soft);
      text-transform: uppercase;
    }

    .hero-badge .badge-center {
      position: absolute;
      inset: 0;
      display: grid;
      place-items: center;
      font-family: var(--font-display);
      font-size: 22px;
      color: var(--accent);
    }

    /* ------------------------------------------------------------
26. DESKTOP BREAKPOINT
------------------------------------------------------------ */
    @media (min-width: 760px) {
      .about-grid {
        grid-template-columns: 0.9fr 1.1fr;
        gap: 54px;
        align-items: center;
      }

      .about-art {
        aspect-ratio: 4 / 4.4;
        position: sticky;
        top: 90px;
      }

      .stats-row {
        grid-template-columns: repeat(4, 1fr);
      }

      .concept-cards {
        grid-template-columns: 1fr 1fr;
        gap: 22px;
      }

      .menu-grid {
        grid-template-columns: 1fr 1fr;
      }

      .menu-card .mc-body .mc-desc {
        line-clamp: 2;
      }

      .event-list {
        grid-template-columns: 1fr 1fr;
      }

      .footer-grid {
        grid-template-columns: 1.3fr 1fr 1fr 1fr;
        gap: 28px;
      }

      .slot-grid {
        grid-template-columns: repeat(4, 1fr);
      }

      .hero-badge {
        bottom: 90px;
      }

      .modal-sheet {
        border-radius: var(--radius-lg);
        margin-bottom: 6vh;
        max-height: 82dvh;
      }

      #menuModal {
        align-items: center;
        padding: 0 20px;
      }

      .modal-sheet {
        transform: translateY(30px);
        opacity: 0;
        transition: transform 0.45s var(--ease), opacity 0.45s var(--ease);
      }

      #menuModal.open .modal-sheet {
        transform: none;
        opacity: 1;
      }
    }

    @media (min-width: 1024px) {
      :root {
        --gutter: 48px;
      }

      .hero-content {
        max-width: 720px;
      }

      .tl-item p {
        font-size: 15px;
      }
    }

    /* ------------------------------------------------------------
27. REDUCED MOTION
------------------------------------------------------------ */
    @media (prefers-reduced-motion: reduce) {
      html {
        scroll-behavior: auto;
      }

      *,
      *::before,
      *::after {
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
      }

      .marquee-track {
        animation: none;
      }

      .hero-disc {
        animation: none;
      }

      .reveal {
        opacity: 1;
        transform: none;
      }
    }
  </style>
</head>

<body>

  <!-- ============================================================
PRELOADER
============================================================ -->
  <div id="preloader" aria-hidden="true">
    <div class="pre-kanji" lang="ja"><span>月</span><span>屋</span><span>へ</span></div>
    <div class="pre-bar"><i id="preBarFill"></i></div>
    <div class="pre-label">Tsukiya &mdash; Kyoto</div>
  </div>

  <!-- Scroll progress -->
  <div id="scrollProgress" aria-hidden="true"></div>

  <!-- ============================================================
HEADER
============================================================ -->
  <header id="siteHeader">
    <div class="header-inner">
      <a href="#hero" class="brand" aria-label="TSUKIYA home">
        <span class="brand-mark" lang="ja">月</span>
        <span class="brand-name">
          <strong>TSUKIYA 月屋</strong>
          <small>Kyoto &middot; est. 1987</small>
        </span>
      </a>
      <div class="header-actions">
        <button id="themeToggle" aria-label="Switch between day cafe and night brewery theme" aria-pressed="false">
          <span class="knob">
            <svg class="icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
              <circle cx="12" cy="12" r="4" />
              <path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4" />
            </svg>
            <svg class="icon-moon" viewBox="0 0 24 24" fill="currentColor">
              <path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z" />
            </svg>
          </span>
        </button>
        <button id="menuBtn" aria-label="Open menu" aria-expanded="false" aria-controls="overlayMenu">
          <span class="bars"><i></i><i></i><i></i></span>
        </button>
      </div>
    </div>
  </header>

  <!-- ============================================================
OVERLAY MENU
============================================================ -->
  <nav id="overlayMenu" aria-label="Main navigation">
    <div class="overlay-nav">
      <a href="#about"><span class="nav-num">01</span> Story <span class="nav-jp" lang="ja">物語</span></a>
      <a href="#menu"><span class="nav-num">02</span> Menu <span class="nav-jp" lang="ja">献立</span></a>
      <a href="#craft"><span class="nav-num">03</span> Craft <span class="nav-jp" lang="ja">醸造</span></a>
      <a href="#seasonal"><span class="nav-num">04</span> Seasonal <span class="nav-jp" lang="ja">季節</span></a>
      <a href="#events"><span class="nav-num">05</span> Events <span class="nav-jp" lang="ja">催し</span></a>
      <a href="#reserve"><span class="nav-num">06</span> Reserve <span class="nav-jp" lang="ja">予約</span></a>
      <a href="#faq"><span class="nav-num">07</span> Visit <span class="nav-jp" lang="ja">案内</span></a>
    </div>
    <div class="overlay-footer">
      <div class="hours">
        <b>Hours</b>
        Cafe&nbsp;&nbsp;08:00 &ndash; 17:00<br>
        Brewery&nbsp;&nbsp;18:00 &ndash; 24:00
      </div>
      <div class="hours" style="text-align:right">
        <b>Find us</b>
        Nakagyō-ku, Kyoto<br>
        <span id="menuClock">--:--</span> JST
      </div>
    </div>
  </nav>

  <!-- ============================================================
HERO
============================================================ -->
  <section id="hero">
    <div class="hero-sky" aria-hidden="true"></div>
    <div class="hero-disc" aria-hidden="true"></div>
    <div class="hero-stars" id="heroStars" aria-hidden="true"></div>
    <div class="hero-skyline" aria-hidden="true">
      <svg viewBox="0 0 800 160" preserveAspectRatio="none">
        <path fill="currentColor" d="M0,160 L0,110 L28,110 L28,88 L40,88 L40,110 L70,110 L70,70 L78,60 L86,70 L86,110 L120,110 L120,95 L150,95 L150,120 L190,120 L190,60 L198,40 L206,60 L206,120 L250,120 L250,100 L290,100 L290,80 L302,80 L302,100 L340,100 L340,120 L378,120 L378,50 L390,30 L402,50 L402,120 L440,120 L440,95 L480,95 L480,110 L520,110 L520,75 L532,62 L544,75 L544,110 L590,110 L590,90 L630,90 L630,115 L668,115 L668,55 L680,38 L692,55 L692,115 L730,115 L730,100 L770,100 L770,120 L800,120 L800,160 Z" />
        <path fill="currentColor" opacity="0.5" d="M0,160 L0,132 L60,132 L60,118 L110,118 L110,132 L200,132 L200,124 L280,124 L280,132 L390,132 L390,112 L410,112 L410,132 L520,132 L520,126 L610,126 L610,132 L700,132 L700,120 L760,120 L760,132 L800,132 L800,160 Z" />
      </svg>
    </div>
    <div class="hero-side-kanji v-text" lang="ja" aria-hidden="true">昼は珈琲 夜は酒</div>
    <div class="hero-steam" aria-hidden="true"><i></i><i></i><i></i></div>
    <div class="hero-badge" aria-hidden="true">
      <svg viewBox="0 0 100 100" class="spin-stamp">
        <defs>
          <path id="badgeCircle" d="M50,50 m-38,0 a38,38 0 1,1 76,0 a38,38 0 1,1 -76,0" />
        </defs>
        <text>
          <textPath href="#badgeCircle">COFFEE BY DAY &#183; SAKE BY NIGHT &#183; KYOTO &#183;</textPath>
        </text>
      </svg>
      <span class="badge-center" lang="ja">月</span>
    </div>
    <div class="hero-content">
      <p class="hero-kicker"><span class="dot"></span> <span id="openStatus">Kyoto &middot; Nakagyō ward</span></p>
      <h1 class="hero-title" lang="ja">
        <span class="t-line"><span>月屋</span></span>
        <span class="t-line"><span lang="en">TSUKIYA</span></span>
      </h1>
      <p class="hero-sub" id="heroSub" lang="ja">昼は珈琲、夜は酒</p>
      <p class="hero-desc" id="heroDesc">
        A 39-year-old kissaten in Kyoto's old quarter. By morning we pour
        <b>hand-drip coffee</b> roasted in-house; when the lanterns come on,
        the same room becomes our <b>sake brewery and bar</b>.
      </p>
      <div class="hero-cta">
        <a href="#reserve" class="btn btn-solid">Reserve a seat <span class="btn-arrow">&rarr;</span></a>
        <a href="#menu" class="btn btn-ghost">See the menu</a>
      </div>
      <div class="hero-meta">
        <span><b id="kyotoClock">--:--</b> JST <span class="sep">&#183;</span> Kyoto</span>
        <span>Roastery <b>since 1987</b></span>
        <span>Kura <b>48 koku / yr</b></span>
      </div>
    </div>
    <div class="scroll-hint" aria-hidden="true">
      <span>Scroll</span>
      <span class="wheel"></span>
    </div>
  </section>

  <!-- ============================================================
MARQUEE
============================================================ -->
  <div class="marquee" aria-hidden="true">
    <div class="marquee-track" id="marqueeTrack">
      <span>Hand-drip coffee <i>&#9679;</i> House-roasted beans <i>&#9679;</i> Junmai sake <i>&#9679;</i> Kyoto since 1987 <i>&#9679;</i> Tatami &amp; counter seats <i>&#9679;</i> <span lang="ja">月屋</span> <i>&#9679;</i></span>
    </div>
  </div>

  <!-- ============================================================
ABOUT / STORY
============================================================ -->
  <section id="about" class="section">
    <div class="wrap">
      <div class="about-grid">
        <div class="about-art reveal" aria-hidden="true">
          <svg viewBox="0 0 400 460" preserveAspectRatio="xMidYMid slice">
            <rect width="400" height="460" fill="var(--bg-deep)" />
            <!-- engi (sun/moon) circle -->
            <circle cx="200" cy="170" r="92" fill="var(--accent)" opacity="0.9" />
            <circle cx="232" cy="148" r="76" fill="var(--bg-deep)" />
            <!-- mountain -->
            <path d="M0,460 L0,300 Q80,250 140,290 T300,270 Q360,250 400,290 L400,460 Z" fill="var(--ink)" opacity="0.18" />
            <path d="M0,460 L0,350 Q110,310 200,345 T400,340 L400,460 Z" fill="var(--ink)" opacity="0.24" />
            <!-- torii -->
            <g fill="var(--accent)" opacity="0.95">
              <path d="M120,330 h160 l-8,14 h-144 z" />
              <rect x="128" y="352" width="144" height="9" />
              <rect x="142" y="344" width="13" height="92" />
              <rect x="245" y="344" width="13" height="92" />
            </g>
            <!-- birds -->
            <g stroke="var(--ink)" stroke-width="2.4" fill="none" opacity="0.6" stroke-linecap="round">
              <path d="M96,120 q7,-8 14,0 q7,-8 14,0" />
              <path d="M132,96 q6,-7 12,0 q6,-7 12,0" />
            </g>
            <!-- coffee steam curls -->
            <g stroke="var(--night-ink)" stroke-width="2" fill="none" opacity="0.35" stroke-linecap="round">
              <path d="M188,258 q-8,-14 0,-26 q8,-12 0,-24" />
              <path d="M212,262 q-8,-14 0,-26 q8,-12 0,-24" />
            </g>
          </svg>
          <span class="art-stamp" lang="ja">月</span>
        </div>
        <div class="about-copy">
          <div class="section-head reveal">
            <span class="eyebrow">01 &mdash; Our story</span>
            <h2 class="h-section">One room,<br>two lives.</h2>
            <span class="title-jp" lang="ja">一つの部屋、二つの顔</span>
          </div>
          <p class="drop-cap reveal reveal-d1">
            Tsukiya began in 1987 as a four-seat coffee counter tucked into a
            machiya townhouse on a quiet lane in Nakagyō. Our founder, Yamashita
            Kenji, roasted beans in a hand-cranked drum and kept a single rule:
            <b>the room should feel like a deep breath.</b>
          </p>
          <p class="reveal reveal-d2">
            A decade later, his daughter studied brewing in Fushimi and brought
            sake back to the same counter. Today, the roast drum and the cedar
            fermentation tanks share one wall. Mornings smell of coffee; evenings
            smell of steamed rice and cedar. <b>Nothing was renovated &mdash;
              the room simply learned a second craft.</b>
          </p>
          <div class="about-sign reveal reveal-d3">
            <span class="sig-kanji" lang="ja">山下</span>
            <span class="sig-meta">The Yamashita family<br>Keepers of Tsukiya, two generations</span>
          </div>
        </div>
      </div>
      <!-- Stats -->
      <div class="stats-row reveal">
        <div class="stat-cell">
          <div class="stat-num"><span class="count" data-count="39">0</span><sub>yrs</sub></div>
          <div class="stat-label">In the same machiya</div>
        </div>
        <div class="stat-cell">
          <div class="stat-num"><span class="count" data-count="48">0</span><sub>koku</sub></div>
          <div class="stat-label">Sake brewed yearly</div>
        </div>
        <div class="stat-cell">
          <div class="stat-num"><span class="count" data-count="12" data-suffix="k">0</span><sub>cups</sub></div>
          <div class="stat-label">Coffee poured monthly</div>
        </div>
        <div class="stat-cell">
          <div class="stat-num"><span class="count" data-count="2">0</span><sub>crafts</sub></div>
          <div class="stat-label">Roasting &amp; brewing</div>
        </div>
      </div>
    </div>
  </section>

  <!-- ============================================================
DAY / NIGHT CONCEPT
============================================================ -->
  <section id="concept" class="section seigaiha">
    <div class="wrap">
      <div class="section-head center reveal">
        <span class="eyebrow">02 &mdash; The rhythm</span>
        <h2 class="h-section">Follow the sun<br>and the moon.</h2>
        <span class="title-jp" lang="ja">太陽と月に従う</span>
        <p class="lede" style="margin-top:14px">The tap on the theme toggle above does more than change colours &mdash; try it while you browse.</p>
      </div>
      <div class="concept-cards">
        <article class="concept-card reveal">
          <span class="cc-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
              <circle cx="12" cy="12" r="4.5" />
              <path d="M12 2.5v2M12 19.5v2M3 12h2M19 12h2M5.3 5.3l1.4 1.4M17.3 17.3l1.4 1.4M5.3 18.7l1.4-1.4M17.3 6.7l1.4-1.4" />
            </svg>
          </span>
          <p class="cc-time">08:00 &ndash; 17:00</p>
          <h3>The Kissaten</h3>
          <p class="cc-jp" lang="ja">喫茶</p>
          <p>Morning light on the counter, vinyl turning slowly, and the smell of a fresh roast. We pour slow &mdash; every cup by hand, no rush, no takeaway cups at the counter.</p>
          <ul class="cc-list">
            <li>Hand-drip coffee</li>
            <li>House roast</li>
            <li>Matcha</li>
            <li>Thick hotcakes</li>
            <li>Vinyl mornings</li>
          </ul>
          <span class="cc-big" lang="ja" aria-hidden="true">陽</span>
        </article>
        <article class="concept-card is-night reveal reveal-d1">
          <span class="cc-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
              <path d="M20.5 13.5A8.5 8.5 0 1 1 10.5 3.5a7 7 0 0 0 10 10z" />
            </svg>
          </span>
          <p class="cc-time">18:00 &ndash; 24:00</p>
          <h3>The Kura Bar</h3>
          <p class="cc-jp" lang="ja">蔵</p>
          <p>Lanterns on, records off. The cedar tanks beside you hold this season's brew &mdash; junmai pressed a floor away, poured cold, warm, or in a flight of three.</p>
          <ul class="cc-list">
            <li>House junmai</li>
            <li>Sake flights</li>
            <li>Coffee porter</li>
            <li>Small plates</li>
            <li>Moon-viewing nights</li>
          </ul>
          <span class="cc-big" lang="ja" aria-hidden="true">月</span>
        </article>
      </div>
    </div>
  </section>

  <!-- ============================================================
MENU
============================================================ -->
  <section id="menu" class="section">
    <div class="wrap">
      <div class="section-head reveal">
        <span class="eyebrow">03 &mdash; The menu</span>
        <h2 class="h-section">What we pour<br>and plate.</h2>
        <span class="title-jp" lang="ja">献立</span>
      </div>
      <div class="menu-tabs reveal" role="tablist" aria-label="Menu categories" id="menuTabs"></div>
      <p class="menu-note"><span class="note-dot"></span><span id="menuNote">All prices in yen &middot; service included</span></p>
      <div class="menu-grid" id="menuGrid"></div>
      <p style="text-align:center; margin-top:28px" class="reveal">
        <a href="#reserve" class="btn btn-ghost btn-sm">Reserve before you come <span class="btn-arrow">&rarr;</span></a>
      </p>
    </div>
  </section>

  <!-- Menu item modal (bottom sheet) -->
  <div id="menuModal" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
    <div class="modal-backdrop" data-close-modal></div>
    <div class="modal-sheet">
      <div class="grab" aria-hidden="true"></div>
      <button class="modal-close" data-close-modal aria-label="Close">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
          <path d="M18 6L6 18M6 6l12 12" />
        </svg>
      </button>
      <div class="modal-glyph">
        <span id="modalGlyph"></span>
        <span class="glyph-jp" id="modalGlyphJp" lang="ja"></span>
      </div>
      <div class="modal-title-row">
        <h3 id="modalTitle"></h3>
        <span class="m-price" id="modalPrice"></span>
      </div>
      <p class="modal-jp" id="modalJp" lang="ja"></p>
      <p class="modal-desc" id="modalDesc"></p>
      <dl class="modal-specs" id="modalSpecs"></dl>
      <div class="modal-note">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
          <circle cx="12" cy="12" r="9" />
          <path d="M12 8v4M12 16h.01" />
        </svg>
        <span id="modalNoteText"></span>
      </div>
    </div>
  </div>

  <!-- ============================================================
CRAFT / BREWERY TIMELINE
============================================================ -->
  <section id="craft" class="section seigaiha">
    <div class="wrap">
      <div class="section-head reveal">
        <span class="eyebrow">04 &mdash; The craft</span>
        <h2 class="h-section">From rice and bean<br>to cup and cup.</h2>
        <span class="title-jp" lang="ja">醸造の工程</span>
        <p class="lede" style="margin-top:14px">Six steps, two crafts, one room. Our junmai follows the old Fushimi method in small 400-litre batches.</p>
      </div>
      <div class="timeline">
        <div class="tl-item reveal">
          <span class="tl-node"><i></i></span>
          <p class="tl-step">Step 01</p>
          <h3>Polish the rice</h3>
          <p class="tl-jp" lang="ja">精米</p>
          <p>We mill Yamada Nishiki down to 60% of the grain, removing the outer layers so only the starchy heart ferments. The bran goes to a neighbour's pickling jar &mdash; nothing leaves wasted.</p>
          <span class="tl-fact"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="9" />
              <path d="M12 7v5l3 2" />
            </svg> 48 hours per batch</span>
        </div>
        <div class="tl-item reveal">
          <span class="tl-node"><i></i></span>
          <p class="tl-step">Step 02</p>
          <h3>Steam &amp; seed the kōji</h3>
          <p class="tl-jp" lang="ja">蒸しと麹</p>
          <p>Steamed rice rests in cedar trays while kōji mould is dusted on by hand every few hours. It's the only step we never automate &mdash; and the one our brewer sleeps least during.</p>
          <span class="tl-fact"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M4 12h16M4 6h16M4 18h16" />
            </svg> 40 trays, turned by hand</span>
        </div>
        <div class="tl-item reveal">
          <span class="tl-node"><i></i></span>
          <p class="tl-step">Step 03</p>
          <h3>Build the mash</h3>
          <p class="tl-jp" lang="ja">酛・醪</p>
          <p>Over four days, kōji, water, and yeast become the starter; then three further additions grow it into the main mash. The tanks hum at 12&deg;C through winter.</p>
          <span class="tl-fact"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M12 3v18M5 8l7-5 7 5" />
            </svg> 18&deg;C &rarr; 12&deg;C curve</span>
        </div>
        <div class="tl-item reveal">
          <span class="tl-node"><i></i></span>
          <p class="tl-step">Step 04</p>
          <h3>Press &amp; settle</h3>
          <p class="tl-jp" lang="ja">搾り</p>
          <p>After roughly three weeks, the mash is pressed gently in a traditional fune. The first glass &mdash; cloudy, alive &mdash; is kept for the brewery's own table.</p>
          <span class="tl-fact"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M12 3c4 5 6 8 6 11a6 6 0 1 1-12 0c0-3 2-6 6-11z" />
            </svg> 400 litres per tank</span>
        </div>
        <div class="tl-item reveal">
          <span class="tl-node"><i></i></span>
          <p class="tl-step">Step 05</p>
          <h3>Rest in the kura</h3>
          <p class="tl-jp" lang="ja">貯蔵</p>
          <p>Bottles sleep upright in the dark storehouse for one season. Some are laid down for a year to become our amber-aged koshu, poured only at the night counter.</p>
          <span class="tl-fact"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <rect x="4" y="4" width="16" height="16" rx="2" />
              <path d="M4 12h16" />
            </svg> 90 days minimum rest</span>
        </div>
        <div class="tl-item reveal">
          <span class="tl-node"><i></i></span>
          <p class="tl-step">Step 06</p>
          <h3>Pour at the counter</h3>
          <p class="tl-jp" lang="ja">提供</p>
          <p>Sake meets coffee one last way: our house porter is brewed with the same well water and spent coffee cherries. Ask for the flight to taste the whole circle.</p>
          <span class="tl-fact"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M8 3h8l1 6a5 5 0 0 1-10 0z" />
              <path d="M12 14v7M8 21h8" />
            </svg> Same well, both crafts</span>
        </div>
      </div>
    </div>
  </section>

  <!-- ============================================================
SEASONAL CAROUSEL
============================================================ -->
  <section id="seasonal" class="section">
    <div class="wrap">
      <div class="section-head reveal">
        <span class="eyebrow">05 &mdash; This season</span>
        <h2 class="h-section">Only for<br>a few weeks.</h2>
        <span class="title-jp" lang="ja">季節の品</span>
      </div>
    </div>
    <div class="carousel reveal" id="seasonCarousel" tabindex="0" aria-label="Seasonal offers"></div>
    <div class="wrap">
      <div class="carousel-ctl reveal">
        <div class="carousel-dots" id="seasonDots"></div>
        <div class="carousel-btns">
          <button id="seasonPrev" aria-label="Previous seasonal item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
              <path d="M15 18l-6-6 6-6" />
            </svg></button>
          <button id="seasonNext" aria-label="Next seasonal item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
              <path d="M9 6l6 6-6 6" />
            </svg></button>
        </div>
      </div>
    </div>
  </section>

  <!-- ============================================================
GALLERY
============================================================ -->
  <section id="gallery" class="section">
    <div class="wrap">
      <div class="section-head reveal">
        <span class="eyebrow">06 &mdash; The room</span>
        <h2 class="h-section">Scenes from<br>the machiya.</h2>
        <span class="title-jp" lang="ja">店内</span>
      </div>
    </div>
    <div class="wrap">
      <div class="gallery-strip reveal" id="galleryStrip"></div>
      <p class="g-hint"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
          <path d="M5 12h14M13 6l6 6-6 6" />
        </svg> Drag to wander</p>
    </div>
  </section>

  <!-- ============================================================
OMIKUJI FORTUNE
============================================================ -->
  <section id="omikuji" class="section">
    <div class="wrap">
      <div class="section-head center reveal">
        <span class="eyebrow">07 &mdash; A little luck</span>
        <h2 class="h-section">Draw your fortune.</h2>
        <span class="title-jp" lang="ja">おみくじ</span>
      </div>
      <div class="omikuji-box reveal">
        <div class="oki-deco" aria-hidden="true"></div>
        <div id="okiIntro">
          <div class="oki-cylinder" id="okiCylinder" role="button" tabindex="0" aria-label="Shake the omikuji box to draw a fortune">
            <div class="oki-hole"></div>
            <div class="oki-stick"></div>
            <div class="oki-body"><span class="oki-kanji" lang="ja">御神籤</span></div>
          </div>
          <h3 class="oki-title">One question per visit.</h3>
          <p class="oki-sub">Tap the box &mdash; or shake your phone &mdash; and the sticks will answer with a fortune and the drink it suggests.</p>
          <span class="oki-action"><button class="btn btn-accent" id="okiDrawBtn">Shake the box</button></span>
        </div>
        <div class="oki-result" id="okiResult">
          <div class="fortune-card">
            <span class="f-stamp" lang="ja">月</span>
            <div class="f-rank" id="fRank" lang="ja"></div>
            <div class="f-rank-en" id="fRankEn"></div>
            <p class="f-poem" id="fPoem" lang="ja"></p>
            <div class="f-divider"></div>
            <p class="f-drink" id="fDrink"></p>
          </div>
          <button class="btn btn-ghost btn-sm oki-again" id="okiAgain">Draw again</button>
          <p class="oki-count" id="okiCount"></p>
        </div>
      </div>
    </div>
  </section>

  <!-- ============================================================
EVENTS
============================================================ -->
  <section id="events" class="section">
    <div class="wrap">
      <div class="section-head reveal">
        <span class="eyebrow">08 &mdash; Gatherings</span>
        <h2 class="h-section">Evenings worth<br>planning for.</h2>
        <span class="title-jp" lang="ja">催し</span>
      </div>
      <div class="event-list" id="eventList"></div>
    </div>
  </section>

  <!-- ============================================================
TESTIMONIALS
============================================================ -->
  <section id="voices" class="section">
    <div class="wrap">
      <div class="section-head center reveal">
        <span class="eyebrow">09 &mdash; Kind words</span>
        <h2 class="h-section">From the counter<br>guestbook.</h2>
        <span class="title-jp" lang="ja">お客様の声</span>
      </div>
      <div class="t-stage reveal" id="tStage">
        <div class="t-track" id="tTrack"></div>
      </div>
      <div class="t-nav reveal">
        <button class="t-arrow" id="tPrev" aria-label="Previous testimonial"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
            <path d="M15 18l-6-6 6-6" />
          </svg></button>
        <div class="t-dots" id="tDots"></div>
        <button class="t-arrow" id="tNext" aria-label="Next testimonial"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
            <path d="M9 6l6 6-6 6" />
          </svg></button>
      </div>
    </div>
  </section>

  <!-- ============================================================
RESERVATION
============================================================ -->
  <section id="reserve" class="section">
    <div class="wrap">
      <div class="section-head center reveal">
        <span class="eyebrow">10 &mdash; Save a seat</span>
        <h2 class="h-section">Reserve a place<br>at the counter.</h2>
        <span class="title-jp" lang="ja">ご予約</span>
        <p class="lede" style="margin-top:14px">Fourteen seats in total. We hold reservations for fifteen minutes past the hour.</p>
      </div>
      <div class="reserve-panel reveal" id="reservePanel">
        <div class="reserve-head">
          <h3>Reservation</h3>
          <span class="rh-jp" lang="ja">予約票</span>
        </div>
        <div class="reserve-steps" id="reserveSteps" aria-hidden="true"><i class="on"></i><i></i><i></i></div>
        <form class="reserve-form" id="reserveForm" novalidate>
          <!-- STEP 1: when & how many -->
          <div class="f-step active" data-step="1">
            <div class="f-group">
              <label class="f-label" for="rDate">Date</label>
              <input class="f-input" type="date" id="rDate" required>
              <p class="f-error" id="rDateErr">Please choose a date from today onwards.</p>
            </div>
            <div class="f-group">
              <span class="f-label">Party size</span>
              <div class="party-stepper">
                <button type="button" id="pMinus" aria-label="Fewer guests">&minus;</button>
                <div class="p-value"><b id="pValue">2</b><span id="pLabel">guests</span></div>
                <button type="button" id="pPlus" aria-label="More guests">+</button>
              </div>
            </div>
            <div class="f-group">
              <span class="f-label">Time &middot; <span id="slotModeLabel">cafe hours</span></span>
              <div class="slot-grid" id="slotGrid"></div>
              <p class="f-error" id="rTimeErr">Please pick a time slot.</p>
            </div>
            <div class="form-nav">
              <button type="button" class="btn btn-solid" id="toStep2">Continue <span class="btn-arrow">&rarr;</span></button>
            </div>
          </div>
          <!-- STEP 2: seating & details -->
          <div class="f-step" data-step="2">
            <div class="f-group">
              <span class="f-label">Seating preference</span>
              <div class="chip-row" id="seatChips">
                <button type="button" class="chip on" data-seat="Counter">Counter</button>
                <button type="button" class="chip" data-seat="Tatami room">Tatami room</button>
                <button type="button" class="chip" data-seat="Brew bar">Brew bar</button>
                <button type="button" class="chip" data-seat="No preference">No preference</button>
              </div>
            </div>
            <div class="f-group">
              <span class="f-label">Occasion <span class="opt">optional</span></span>
              <div class="chip-row" id="occasionChips">
                <button type="button" class="chip" data-occ="Just visiting">Just visiting</button>
                <button type="button" class="chip" data-occ="Birthday">Birthday</button>
                <button type="button" class="chip" data-occ="Anniversary">Anniversary</button>
                <button type="button" class="chip" data-occ="Business">Business</button>
              </div>
            </div>
            <div class="f-group">
              <label class="f-label" for="rNotes">Notes <span class="opt">allergies, requests&hellip;</span></label>
              <textarea class="f-input" id="rNotes" rows="3" maxlength="240" placeholder="Anything we should know?"></textarea>
            </div>
            <div class="form-nav">
              <button type="button" class="btn btn-back" id="backTo1">&larr; Back</button>
              <button type="button" class="btn btn-solid" id="toStep3">Continue <span class="btn-arrow">&rarr;</span></button>
            </div>
          </div>
          <!-- STEP 3: contact & confirm -->
          <div class="f-step" data-step="3">
            <div class="summary-box" id="summaryBox"></div>
            <div class="f-group">
              <label class="f-label" for="rName">Name</label>
              <input class="f-input" type="text" id="rName" autocomplete="name" placeholder="Your name" maxlength="60">
              <p class="f-error" id="rNameErr">Please tell us your name.</p>
            </div>
            <div class="f-group">
              <label class="f-label" for="rMail">Email</label>
              <input class="f-input" type="email" id="rMail" autocomplete="email" placeholder="you@example.com" inputmode="email">
              <p class="f-error" id="rMailErr">That email doesn't look right.</p>
            </div>
            <div class="form-nav">
              <button type="button" class="btn btn-back" id="backTo2">&larr; Back</button>
              <button type="submit" class="btn btn-accent">Confirm reservation</button>
            </div>
          </div>
        </form>
        <!-- Confirmation -->
        <div class="reserve-done" id="reserveDone">
          <div class="ticket">
            <span class="tk-stamp" lang="ja">予約</span>
            <h4>Seat reserved</h4>
            <p class="tk-jp" lang="ja">ご予約ありがとうございます</p>
            <p class="tk-detail" id="ticketDetail"></p>
            <p class="tk-code" id="ticketCode"></p>
          </div>
          <p class="done-note">We've saved your request &mdash; a confirmation would normally arrive by email. Show this code at the door.</p>
          <button class="btn btn-ghost btn-sm" id="newReserve" style="margin-top:18px">Make another reservation</button>
        </div>
      </div>
    </div>
  </section>

  <!-- ============================================================
FAQ
============================================================ -->
  <section id="faq" class="section">
    <div class="wrap">
      <div class="section-head reveal">
        <span class="eyebrow">11 &mdash; Before you visit</span>
        <h2 class="h-section">Small questions,<br>quick answers.</h2>
        <span class="title-jp" lang="ja">よくある質問</span>
      </div>
      <div class="faq-list reveal" id="faqList"></div>
    </div>
  </section>

  <!-- ============================================================
NEWSLETTER
============================================================ -->
  <section id="newsletter" class="section">
    <div class="wrap">
      <div class="news-panel reveal">
        <span class="news-kanji" lang="ja" aria-hidden="true">便り</span>
        <h2>One letter a month.</h2>
        <p class="news-sub">Roast dates, new brews, and the occasional moon-viewing invitation. No noise &mdash; we write only when there's something worth pouring.</p>
        <form class="news-form" id="newsForm" novalidate>
          <input class="f-input" type="email" id="newsMail" placeholder="you@example.com" aria-label="Email address" inputmode="email">
          <button class="btn btn-solid" type="submit">Join</button>
        </form>
        <p class="news-done" id="newsDone">&#10003; Welcome to the table &mdash; first letter at the next roast.</p>
        <p class="news-hint">No spam &middot; unsubscribe anytime &middot; <span lang="ja">月屋通信</span></p>
      </div>
    </div>
  </section>

  <!-- ============================================================
FOOTER
============================================================ -->
  <footer id="siteFooter">
    <div class="footer-word" lang="ja" aria-hidden="true">月屋</div>
    <div class="wrap">
      <div class="footer-grid">
        <div class="footer-col">
          <h4>Visit</h4>
          <p>
            2-14-8 Yanagi-baba, Nakagyō-ku<br>
            Kyoto 604-0000, Japan<br><br>
            <a href="#reserve">Reservations &rarr;</a><br>
            <span id="footerStatus">Checking hours&hellip;</span>
          </p>
        </div>
        <div class="footer-col">
          <h4>Hours</h4>
          <dl class="f-hours">
            <dt>MON&ndash;FRI</dt>
            <dd>08:00 &ndash; 17:00 &middot; 18:00 &ndash; 24:00</dd>
            <dt>SAT</dt>
            <dd>09:00 &ndash; 17:00 &middot; 18:00 &ndash; 01:00</dd>
            <dt>SUN</dt>
            <dd>09:00 &ndash; 16:00 &middot; closed night</dd>
            <dt>BREAK</dt>
            <dd>17:00 &ndash; 18:00 daily</dd>
          </dl>
        </div>
        <div class="footer-col">
          <h4>Contact</h4>
          <ul>
            <li><a href="tel:+81750000000">+81 75 000 0000</a></li>
            <li><a href="mailto:hello@tsukiya.example">hello@tsukiya.example</a></li>
            <li style="margin-top:8px"><span lang="ja">日本語 / English</span></li>
          </ul>
          <div class="footer-social">
            <a href="#" aria-label="Instagram" onclick="return false"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                <rect x="3" y="3" width="18" height="18" rx="5" />
                <circle cx="12" cy="12" r="4" />
                <circle cx="17.5" cy="6.5" r="1" fill="currentColor" stroke="none" />
              </svg></a>
            <a href="#" aria-label="X" onclick="return false"><svg viewBox="0 0 24 24" fill="currentColor">
                <path d="M17.5 3h3.1l-6.8 7.8L21.8 21h-6.3l-4.9-6.4L5 21H1.9l7.3-8.3L2.2 3h6.4l4.4 5.9L17.5 3zm-1.1 16.2h1.7L7.7 4.7H5.9l10.5 14.5z" />
              </svg></a>
            <a href="#" aria-label="Line" onclick="return false"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 11.5c0 4.1-4 7.5-9 7.5-1 0-2-.1-2.9-.4-.6.4-1.6.9-2.6 1.1-.3.1-.5-.2-.4-.4.2-.5.5-1.3.6-1.9C4 16.3 3 14 3 11.5 3 7.4 7 4 12 4s9 3.4 9 7.5z" />
              </svg></a>
          </div>
        </div>
        <div class="footer-col">
          <h4>The house</h4>
          <ul>
            <li><a href="#about">Our story</a></li>
            <li><a href="#craft">Brewing craft</a></li>
            <li><a href="#events">Events</a></li>
            <li><a href="#omikuji">Fortune corner</a></li>
            <li><a href="#faq">FAQ</a></li>
          </ul>
        </div>
      </div>
      <div class="footer-bottom">
        <small>&copy; 2026 TSUKIYA 月屋 &middot; A fictional demo &middot; Kyoto, Japan</small>
        <small>Kyoto time <span class="clock" id="footerClock">--:--</span></small>
      </div>
    </div>
  </footer>

  <!-- Back to top -->
  <button id="backTop" aria-label="Back to top">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
      <path d="M12 19V5M5 12l7-7 7 7" />
    </svg>
  </button>

  <!-- Toast rack -->
  <div id="toastRack" aria-live="polite"></div>

  <script>
    /* ============================================================
TSUKIYA 月屋 — Application script
Vanilla JS. No dependencies. In-memory state only.
------------------------------------------------------------
TABLE OF CONTENTS
A. Utilities & state
B. Preloader
C. Header, scroll progress, back-to-top
D. Overlay menu
E. Kyoto clock & open status
F. Theme toggle (day / night)
G. Reveal-on-scroll & counters
H. Menu: data, tabs, cards, modal
I. Seasonal carousel
J. Gallery drag-scroll
K. Omikuji fortune
L. Events & RSVP
M. Testimonials slider
N. Reservation flow
O. FAQ accordion
P. Newsletter
Q. Toasts
R. Init
============================================================ */
    'use strict';

    /* ------------------------------------------------------------
    A. UTILITIES & STATE
    ------------------------------------------------------------ */
    const $ = (sel, root = document) => root.querySelector(sel);
    const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));

    const state = {
      theme: 'day', // 'day' | 'night'
      menuTab: 'coffee', // active menu category
      party: 2, // reservation party size
      slot: null, // chosen time slot
      seat: 'Counter',
      occasion: null,
      tIndex: 0, // testimonial index
      tTimer: null,
      fortuneDraws: 0,
      lastThemeChoice: null // remember user's explicit choice
    };

    const clamp = (v, min, max) => Math.min(max, Math.max(min, v));
    const pad = (n) => String(n).padStart(2, '0');

    /* Kyoto time helper (JST = UTC+9, no DST) */
    function kyotoNow() {
      const now = new Date();
      const utc = now.getTime() + now.getTimezoneOffset() * 60000;
      return new Date(utc + 9 * 3600000);
    }

    /* Escape user-provided strings before inserting into HTML */
    function esc(str) {
      return String(str)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    /* ------------------------------------------------------------
    B. PRELOADER
    ------------------------------------------------------------ */
    function initPreloader() {
      const bar = $('#preBarFill');
      const loader = $('#preloader');
      let progress = 0;
      const tick = setInterval(() => {
        progress = Math.min(progress + Math.random() * 22, 92);
        bar.style.width = progress + '%';
      }, 160);
      window.addEventListener('load', () => {
        clearInterval(tick);
        bar.style.width = '100%';
        setTimeout(() => {
          loader.classList.add('done');
          document.body.classList.remove('locked');
        }, 450);
      });
      /* Safety: never trap the user behind the loader */
      setTimeout(() => {
        clearInterval(tick);
        loader.classList.add('done');
        document.body.classList.remove('locked');
      }, 4000);
    }
    document.body.classList.add('locked');

    /* ------------------------------------------------------------
    C. HEADER, SCROLL PROGRESS, BACK-TO-TOP
    ------------------------------------------------------------ */
    function initScrollEffects() {
      const header = $('#siteHeader');
      const progress = $('#scrollProgress');
      const backTop = $('#backTop');
      let ticking = false;

      function onScroll() {
        if (ticking) return;
        ticking = true;
        requestAnimationFrame(() => {
          const y = window.scrollY;
          const doc = document.documentElement;
          const max = doc.scrollHeight - window.innerHeight;
          const pct = max > 0 ? (y / max) * 100 : 0;
          header.classList.toggle('scrolled', y > 30);
          progress.style.width = pct + '%';
          backTop.classList.toggle('show', y > 600);
          ticking = false;
        });
      }
      window.addEventListener('scroll', onScroll, {
        passive: true
      });
      onScroll();
      backTop.addEventListener('click', () => {
        window.scrollTo({
          top: 0,
          behavior: 'smooth'
        });
      });
    }

    /* ------------------------------------------------------------
    D. OVERLAY MENU
    ------------------------------------------------------------ */
    function initOverlayMenu() {
      const btn = $('#menuBtn');
      const overlay = $('#overlayMenu');

      function setOpen(open) {
        btn.classList.toggle('active', open);
        btn.setAttribute('aria-expanded', String(open));
        btn.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
        overlay.classList.toggle('open', open);
        document.body.classList.toggle('locked', open);
      }
      btn.addEventListener('click', () => setOpen(!overlay.classList.contains('open')));
      /* Close on link click */
      $$('.overlay-nav a', overlay).forEach((a) => {
        a.addEventListener('click', () => setOpen(false));
      });
      /* Close on Escape */
      document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && overlay.classList.contains('open')) setOpen(false);
      });
    }

    /* ------------------------------------------------------------
    E. KYOTO CLOCK & OPEN STATUS
    ------------------------------------------------------------ */
    function initClocks() {
      const kyoto = $('#kyotoClock');
      const footer = $('#footerClock');
      const menu = $('#menuClock');
      const openStatus = $('#openStatus');
      const footerStatus = $('#footerStatus');

      function render() {
        const now = kyotoNow();
        const hh = pad(now.getHours());
        const mm = pad(now.getMinutes());
        const time = hh + ':' + mm;
        const stamp = time + ':' + pad(now.getSeconds());
        if (kyoto) kyoto.textContent = time;
        if (footer) footer.textContent = stamp;
        if (menu) menu.textContent = time;

        /* Derive open / closed / break status */
        const day = now.getDay(); // 0 = Sunday
        const mins = now.getHours() * 60 + now.getMinutes();
        let label = 'Closed now',
          cls = 'status-closed';
        const cafeOpen = day === 0 ? 9 * 60 : (day === 6 ? 9 * 60 : 8 * 60);
        const cafeClose = day === 0 ? 16 * 60 : 17 * 60;
        const nightOpen = 18 * 60;
        const nightClose = day === 6 ? 25 * 60 : 24 * 60; // Sat til 01:00
        const minsExt = mins < 3 * 60 && day === 0 ? mins + 24 * 60 : mins; // Sun 00-03 = Sat late

        if (day === 0 && mins >= nightOpen) {
          label = 'Closed — Sunday nights are quiet';
        } else if (mins >= cafeOpen && mins < cafeClose) {
          label = 'Open now — cafe hours';
          cls = 'status-open';
        } else if (mins >= cafeClose && mins < nightOpen) {
          label = 'Afternoon break — back at 18:00';
        } else if (minsExt >= nightOpen && minsExt < nightClose) {
          label = 'Open now — kura bar';
          cls = 'status-open';
        }
        if (openStatus) openStatus.innerHTML = 'Kyoto &middot; Nakagyō ward';
        if (footerStatus) {
          footerStatus.className = cls;
          footerStatus.textContent = label;
        }
      }
      render();
      setInterval(render, 1000);
    }

    /* ------------------------------------------------------------
    F. THEME TOGGLE (DAY / NIGHT)
    ------------------------------------------------------------ */
    const themeCopy = {
      day: {
        sub: '昼は珈琲、夜は酒',
        desc: 'A 39-year-old kissaten in Kyoto\u2019s old quarter. By morning we pour <b>hand-drip coffee</b> roasted in-house; when the lanterns come on, the same room becomes our <b>sake brewery and bar</b>.',
        meta: 'Theme color day',
        toast: 'Day mode — the kissaten is awake.'
      },
      night: {
        sub: '月の下で、一杯',
        desc: 'The lanterns are lit. Cedar tanks hum beside the counter while we pour <b>house junmai</b>, aged koshu, and a coffee porter brewed with the same well water.',
        meta: 'Theme color night',
        toast: 'Night mode — the kura bar opens.'
      }
    };

    function applyTheme(theme, announce) {
      state.theme = theme;
      document.documentElement.setAttribute('data-theme', theme);
      const toggle = $('#themeToggle');
      toggle.setAttribute('aria-pressed', String(theme === 'night'));
      const meta = $('#metaTheme');
      meta.setAttribute('content', theme === 'night' ? '#0f1318' : '#f4ede1');
      const sub = $('#heroSub');
      const desc = $('#heroDesc');
      if (sub) sub.textContent = themeCopy[theme].sub;
      if (desc) desc.innerHTML = themeCopy[theme].desc;
      /* Re-render menu so availability matches the hour we're pretending it is */
      renderMenuGrid();
      if (announce) toast(themeCopy[theme].toast, 'moon');
    }

    function initThemeToggle() {
      $('#themeToggle').addEventListener('click', () => {
        const next = state.theme === 'day' ? 'night' : 'day';
        state.lastThemeChoice = next;
        applyTheme(next, true);
      });
    }
  </script>

  <script>
    /* ------------------------------------------------------------
G. REVEAL-ON-SCROLL & COUNTERS
------------------------------------------------------------ */
    function initReveals() {
      const revealObserver = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            entry.target.classList.add('in-view');
            revealObserver.unobserve(entry.target);
          }
        });
      }, {
        threshold: 0.12,
        rootMargin: '0px 0px -6% 0px'
      });
      $$('.reveal, .tl-item').forEach((el) => revealObserver.observe(el));
    }

    function initCounters() {
      const counters = $$('.count');
      const counterObserver = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
          if (!entry.isIntersecting) return;
          const el = entry.target;
          counterObserver.unobserve(el);
          const target = parseInt(el.dataset.count, 10);
          const suffix = el.dataset.suffix || '';
          const dur = 1600;
          const t0 = performance.now();

          function frame(t) {
            const p = clamp((t - t0) / dur, 0, 1);
            const eased = 1 - Math.pow(1 - p, 3);
            el.textContent = Math.round(target * eased) + suffix;
            if (p < 1) requestAnimationFrame(frame);
          }
          requestAnimationFrame(frame);
        });
      }, {
        threshold: 0.6
      });
      counters.forEach((el) => counterObserver.observe(el));
    }

    /* ------------------------------------------------------------
    H. MENU — DATA, TABS, CARDS, MODAL
    ------------------------------------------------------------ */
    const GLYPHS = {
      drip: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><path d="M7 3h10M8 3l1.2 5h5.6L16 3"/><path d="M7.5 8h9l-1.2 9.5a3 3 0 0 1-3 2.5h-.6a3 3 0 0 1-3-2.5z"/><path d="M9 12c0 1.2.8 2 1.6 2.6"/></svg>',
      soda: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><path d="M8 3h8l-1 18h-6z"/><circle cx="11" cy="14" r="1"/><circle cx="13.5" cy="11" r="0.9"/><circle cx="11.5" cy="8" r="0.8"/><path d="M14 3l3-2"/></svg>',
      matcha: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><path d="M4 10h16a8 8 0 0 1-16 0z"/><path d="M8 6c0-1.5 1-1.5 1-3M12 6c0-1.5 1-1.5 1-3M16 6c0-1.5 1-1.5 1-3"/></svg>',
      ice: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><path d="M7 4h10l-1.5 16h-7z"/><path d="M8 9h8M9 13h6"/><path d="M16 2l1.5 2"/></svg>',
      affo: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><path d="M5 11h14l-1.5 9h-11z"/><path d="M8 8c0-1.6 1.2-1.6 1.2-3.2M12 8c0-1.6 1.2-1.6 1.2-3.2"/><circle cx="12" cy="14" r="2.4"/></svg>',
      sando: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"><path d="M4 5h16v4H4zM4 11h16l-8 8z"/><path d="M7 7.5h4"/></svg>',
      cake: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><ellipse cx="12" cy="9" rx="8" ry="3"/><path d="M4 9v5c0 1.7 3.6 3 8 3s8-1.3 8-3V9"/><path d="M12 6V4"/><circle cx="12" cy="3" r="1"/></svg>',
      onigiri: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"><path d="M12 3l8.5 14.5a1.8 1.8 0 0 1-1.6 2.5H5.1a1.8 1.8 0 0 1-1.6-2.5z"/><path d="M9.5 14h5v6h-5z"/></svg>',
      sake: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><path d="M9 3h6l-.7 4.5c1 1 1.7 2.2 1.7 3.5 0 3-1.8 5-4 5s-4-2-4-5c0-1.3.7-2.5 1.7-3.5z"/><path d="M12 16v3M8.5 21h7"/><path d="M9.8 7.5h4.4"/></svg>',
      yuzu: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><circle cx="12" cy="13" r="7"/><path d="M12 6V3M10 3.5h4"/><path d="M9.5 13a2.5 2.5 0 0 0 5 0"/></svg>',
      porter: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><path d="M7 5h10v13a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2z"/><path d="M17 8h2.5a1.5 1.5 0 0 1 0 6H17"/><path d="M7 9h10"/></svg>',
      highball: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><path d="M8 3h8l-1 18H9z"/><path d="M8.7 8h6.6M9.2 13h5.6"/><circle cx="11" cy="17" r="0.8"/><circle cx="13.5" cy="16" r="0.7"/></svg>',
      flight: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><path d="M4 10h4l-.6 8H4.6zM10 8h4l-.6 10h-2.8zM16 10h4l-.6 8h-2.8z"/><path d="M3 20h18"/></svg>',
      cheese: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"><path d="M4 9l16-3v11H4z"/><path d="M4 17h16v3H4z"/><circle cx="9" cy="11.5" r="0.9"/><circle cx="14.5" cy="10.5" r="0.8"/></svg>',
      yakitori: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><path d="M4 20L20 4"/><circle cx="9" cy="15" r="2.2"/><circle cx="13" cy="11" r="2.2"/><circle cx="17" cy="7" r="2.2"/></svg>',
      ochazuke: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><path d="M4 11h16a8 8 0 0 1-16 0z"/><path d="M7 8c1-1 2-1 3-2M13 7c1-1 2-1 3-2"/><path d="M9 14h6"/></svg>'
    };

    const MENU_DATA = [
      /* ---------- COFFEE ---------- */
      {
        cat: 'coffee',
        time: 'day',
        glyph: 'drip',
        name: 'Hand-drip House Roast',
        jp: 'ハンドドリップ',
        price: 750,
        desc: 'Single origin, roasted in our drum on Tuesdays, poured over ten unhurried minutes.',
        long: 'Our house blend leans chocolate and dried plum. Each cup is weighed, ground to order, and brewed with 91°C well water in a slow spiral. Choose it bright (Ethiopia) or deep (Kyoto dark) at the counter.',
        tags: ['signature'],
        specs: {
          Roast: 'Medium-dark',
          Origin: 'Blend, seasonal',
          Brew: 'Nel drip',
          Serve: 'Ceramic cup'
        },
        note: 'Counter seats only — we don\u2019t rush a pour.'
      },
      {
        cat: 'coffee',
        time: 'day',
        glyph: 'ice',
        name: 'Nel-drip Iced Coffee',
        jp: 'アイスコーヒー',
        price: 700,
        desc: 'Hot-brewed over ice, double strength, served tall with one clear cube.',
        long: 'Brewed hot directly onto ice to lock in aroma, then stirred once over a hand-cut cube. No cold-brew shortcuts — the flash-chill keeps the acidity alive.',
        tags: [],
        specs: {
          Roast: 'Medium',
          Method: 'Flash-chilled',
          Glass: 'Cut crystal',
          Pair: 'Hotcake'
        },
        note: 'Best before the ice whispers — drink within ten minutes.'
      },
      {
        cat: 'coffee',
        time: 'day',
        glyph: 'soda',
        name: 'Kissaten Cream Soda',
        jp: 'クリームソーダ',
        price: 850,
        desc: 'Melon soda, one scoop of vanilla, a cherry on top — the classic.',
        long: 'The brightest green you\u2019ll see all day. House-made melon syrup, cold soda, a scoop of Hokkaido vanilla, and the mandatory cherry. Nostalgia, carbonated.',
        tags: ['classic'],
        specs: {
          Base: 'Melon soda',
          Ice: 'Crushed',
          Scoop: 'Hokkaido vanilla',
          Top: 'Amarena cherry'
        },
        note: 'Ask for the sailor-moon glass while they last.'
      },
      {
        cat: 'coffee',
        time: 'day',
        glyph: 'affo',
        name: 'Hōjicha Affogato',
        jp: 'ほうじ茶アフォガート',
        price: 780,
        desc: 'Roasted-tea ice cream drowned in a double espresso.',
        long: 'A Kyoto twist on the Italian classic: hōjicha ice cream — toasty, faintly smoky — with a double shot of our dark roast poured tableside. Sweet, bitter, warm, cold, all at once.',
        tags: ['new'],
        specs: {
          Ice: 'Hōjicha',
          Shot: 'Double espresso',
          Pour: 'Tableside',
          Serve: 'Chilled stone bowl'
        },
        note: 'Contains dairy; oat version on request.'
      },
      /* ---------- MATCHA & TEA ---------- */
      {
        cat: 'tea',
        time: 'day',
        glyph: 'matcha',
        name: 'Ceremonial Matcha',
        jp: '抹茶',
        price: 800,
        desc: 'Stone-milled Uji matcha, whisked to order, with one wagashi sweet.',
        long: 'First-harvest leaves from Uji, ground weekly on our stone mill. Whisked in a warmed chawan until the foam is fine as silk, served with a seasonal wagashi from the shop next door.',
        tags: ['signature'],
        specs: {
          Grade: 'Ceremonial',
          Origin: 'Uji, Kyoto',
          Whisk: 'Chasen, to order',
          Sweet: 'Seasonal wagashi'
        },
        note: 'The whisk is yours to watch — counter seats recommended.'
      },
      {
        cat: 'tea',
        time: 'day',
        glyph: 'soda',
        name: 'Matcha Latte',
        jp: '抹茶ラテ',
        price: 780,
        desc: 'Uji matcha with silky milk, hot or over ice.',
        long: 'Two scoops of our ceremonial grade dissolved first in a little hot water, then folded into steamed milk. Ask for it less sweet — most guests do.',
        tags: [],
        specs: {
          Grade: 'Premium',
          Milk: 'Whole or oat',
          Style: 'Hot / iced',
          Sweet: 'Light cane'
        },
        note: 'Oat milk +¥50.'
      },
      {
        cat: 'tea',
        time: 'day',
        glyph: 'drip',
        name: 'Kobicha Pot',
        jp: '昆布茶',
        price: 550,
        desc: 'Kelp tea with a whisper of plum — the old Kyoto way to close a morning.',
        long: 'Not quite tea, not quite broth: fine kelp powder with a single dried plum, steeped hot. It\u2019s what our founder drank at closing time, and what regulars ask for when they\u2019ve stayed too long.',
        tags: ['classic'],
        specs: {
          Base: 'Kelp powder',
          Note: 'Ume plum',
          Pot: 'Serves two',
          Season: 'Year-round'
        },
        note: 'Poured twice — the second cup is the better one.'
      },
      /* ---------- SAKA / KITCHEN ---------- */
      {
        cat: 'kitchen',
        time: 'day',
        glyph: 'sando',
        name: 'Tamago Sando',
        jp: '玉子サンド',
        price: 900,
        desc: 'Thick egg salad on milk bread, crusts off, cut in fours.',
        long: 'Seven eggs per sandwich, folded with a little karashi mustard, pressed between pillowy milk bread. Cut into four perfect squares. The most photographed thing in this room, and we\u2019ve never advertised it once.',
        tags: ['signature'],
        specs: {
          Bread: 'Milk bread',
          Eggs: 'Seven, soft-set',
          Cut: 'Four squares',
          Mustard: 'Karashi, light'
        },
        note: 'Sold out most days by 14:00 — reserve one with your table.'
      },
      {
        cat: 'kitchen',
        time: 'day',
        glyph: 'cake',
        name: 'Thick Hotcake',
        jp: 'ホットケーキ',
        price: 950,
        desc: 'Copper-pan hotcake with maple butter, twenty patient minutes.',
        long: 'Batter poured into a copper pan over low flame, flipped once, never rushed. Served with whipped maple butter and a small jug of syrup. It takes twenty minutes; nobody has ever complained.',
        tags: ['classic'],
        specs: {
          Pan: 'Copper',
          Time: '20 minutes',
          Butter: 'Maple-whipped',
          Serve: 'Shared or solo'
        },
        note: 'Made to order — patience rewarded.'
      },
      {
        cat: 'kitchen',
        time: 'day',
        glyph: 'onigiri',
        name: 'Morning Onigiri Set',
        jp: 'おにぎり定食',
        price: 850,
        desc: 'Two rice balls, miso soup, pickles — breakfast as it should be.',
        long: 'Koshihikari rice cooked in our well water, shaped while hot. Fillings rotate: salmon, umeboshi, or kombu. With house miso soup and yesterday\u2019s pickles.',
        tags: [],
        specs: {
          Rice: 'Koshihikari',
          Fillings: 'Rotating two',
          Soup: 'House miso',
          Pickles: 'House-made'
        },
        note: 'Until 11:00 only.'
      },
      {
        cat: 'kitchen',
        time: 'night',
        glyph: 'yakitori',
        name: 'Yakitori Five Skewers',
        jp: '焼き鳥五本',
        price: 1100,
        desc: 'Charcoal-grilled skewers with our sake-kasu tare.',
        long: 'Five skewers over binchōtan: thigh, skin, tsukune, scallion wrap, and whatever the market gave us. Glazed with a tare that borrows a year of our own sake lees.',
        tags: ['signature'],
        specs: {
          Grill: 'Binchōtan',
          Skewers: 'Five',
          Tare: 'Sake-lees glaze',
          Salt: 'Okinawan'
        },
        note: 'Pair with the junmai flight.'
      },
      {
        cat: 'kitchen',
        time: 'night',
        glyph: 'cheese',
        name: 'Sake-kasu Cheesecake',
        jp: '酒粕チーズケーキ',
        price: 750,
        desc: 'Baked cheesecake folded with our brewery\u2019s fresh sake lees.',
        long: 'The lees from last month\u2019s pressing, blended into a dense baked cheesecake. Faintly floral, barely sweet, with a finish that keeps reminding you this place brews as well as bakes.',
        tags: ['new'],
        specs: {
          Base: 'Cream cheese',
          Lees: 'This season\u2019s',
          Bake: 'Dense, low oven',
          Serve: 'Room temp'
        },
        note: 'Contains trace alcohol from lees.'
      },
      {
        cat: 'kitchen',
        time: 'night',
        glyph: 'ochazuke',
        name: 'Sake-lees Ochazuke',
        jp: '酒粕茶漬け',
        price: 950,
        desc: 'Rice, salmon, and a broth of tea and sake lees — the closing dish.',
        long: 'Grilled salmon over rice, doused at the table with hōjicha broth enriched with sake lees. It\u2019s the dish we serve ourselves after midnight service, and the last thing off the menu.',
        tags: ['classic'],
        specs: {
          Rice: 'Koshihikari',
          Fish: 'Grilled salmon',
          Broth: 'Hōjicha + kasu',
          Pour: 'Tableside'
        },
        note: 'Last orders 23:30.'
      },
      /* ---------- SAKE & NIGHT DRINKS ---------- */
      {
        cat: 'sake',
        time: 'night',
        glyph: 'sake',
        name: 'Tsukiya Junmai',
        jp: '月屋 純米',
        price: 1200,
        desc: 'Our house junmai — cedar-adjacent, dry, brewed a floor away.',
        long: 'Yamada Nishiki polished to 60%, Kyoto well water, and eighteen cold days in the tank. Dry and quiet with a cedar whisper from the kura. Pour it cold for the nose, warm for the memory.',
        tags: ['signature'],
        specs: {
          Rice: 'Yamada Nishiki',
          Polish: '60%',
          Style: 'Dry junmai',
          Serve: 'Cold or warm'
        },
        note: 'The bottle you see on the shelf was brewed in this building.'
      },
      {
        cat: 'sake',
        time: 'night',
        glyph: 'flight',
        name: 'Aged Sake Flight',
        jp: '古酒飲み比べ',
        price: 1800,
        desc: 'Three pours: one, three, and five years in the bottle.',
        long: 'A study in patience. One-year junmai, three-year koshu amber, and a five-year bottle that tastes of roasted nuts and dried fig. Served on a cedar board with tasting notes.',
        tags: ['signature'],
        specs: {
          Pours: 'Three × 45ml',
          Ages: '1 / 3 / 5 yrs',
          Board: 'Cedar',
          Notes: 'Included'
        },
        note: 'Night counter only, twelve boards an evening.'
      },
      {
        cat: 'sake',
        time: 'night',
        glyph: 'yuzu',
        name: 'Yuzu Sour Sake',
        jp: '柚子サワー',
        price: 980,
        desc: 'House junmai sharpened with fresh yuzu and a little soda.',
        long: 'Our junmai, fresh-squeezed Kōchi yuzu, and a short pour of soda over ice. Bright enough for first-time sake drinkers, honest enough for regulars.',
        tags: [],
        specs: {
          Base: 'House junmai',
          Citrus: 'Kōchi yuzu',
          Fizz: 'Light',
          Serve: 'Over ice'
        },
        note: 'Yuzu season runs November to February.'
      },
      {
        cat: 'sake',
        time: 'night',
        glyph: 'porter',
        name: 'Coffee Porter',
        jp: 'コーヒーポーター',
        price: 900,
        desc: 'Dark ale brewed with the same well water and our coffee cherries.',
        long: 'Brewed quarterly with a Kyoto craft partner using our well water and spent coffee cherry. Cocoa, dark fruit, and a roast finish that closes the circle between the two crafts.',
        tags: ['new'],
        specs: {
          Style: 'Porter',
          ABV: '5.5%',
          Brew: 'Quarterly',
          Cherry: 'Cascade'
        },
        note: 'The bridge between our morning and evening selves.'
      },
      {
        cat: 'sake',
        time: 'night',
        glyph: 'highball',
        name: 'Shōchū Highball',
        jp: '焼酎ハイボール',
        price: 850,
        desc: 'Barley shōchū, hard soda, a twist of lemon — cold and clean.',
        long: 'A Kyoto bar staple. Barley shōchū pulled long with carbonated water over clear ice, lemon expressed and dropped. Nothing to improve.',
        tags: [],
        specs: {
          Base: 'Barley shōchū',
          Soda: 'House-charged',
          Citrus: 'Lemon twist',
          Glass: 'Frosty'
        },
        note: 'Ask for it extra dry.'
      }
    ];

    const MENU_TABS = [{
        id: 'coffee',
        en: 'Coffee',
        jp: '珈琲'
      },
      {
        id: 'tea',
        en: 'Matcha & Tea',
        jp: '茶'
      },
      {
        id: 'kitchen',
        en: 'Kitchen',
        jp: '食事'
      },
      {
        id: 'sake',
        en: 'Sake & Night',
        jp: '酒'
      }
    ];

    function renderMenuTabs() {
      const wrap = $('#menuTabs');
      wrap.innerHTML = MENU_TABS.map((t) =>
        `<button class="menu-tab ${t.id === state.menuTab ? 'active' : ''}"
      role="tab" data-tab="${t.id}"
      aria-selected="${t.id === state.menuTab}">
      ${t.en} <span class="tab-jp" lang="ja">${t.jp}</span>
    </button>`
      ).join('');
      $$('.menu-tab', wrap).forEach((btn) => {
        btn.addEventListener('click', () => {
          state.menuTab = btn.dataset.tab;
          renderMenuTabs();
          renderMenuGrid();
        });
      });
    }

    function renderMenuGrid() {
      const grid = $('#menuGrid');
      const note = $('#menuNote');
      const items = MENU_DATA.filter((i) => i.cat === state.menuTab);
      if (note) {
        const nightTab = state.menuTab === 'sake';
        note.textContent = nightTab ?
          'Night menu · served 18:00–24:00 · prices in yen' :
          'All prices in yen · service included';
      }
      grid.innerHTML = items.map((item, idx) => {
        const mismatch = state.theme === 'day' && item.time === 'night';
        return `
    <article class="menu-card ${mismatch ? 'dim' : ''}" data-id="${item.name}"
      style="animation-delay:${idx * 0.05}s" role="button" tabindex="0"
      aria-label="Open details for ${esc(item.name)}">
      <span class="mc-glyph">${GLYPHS[item.glyph]}</span>
      <span class="mc-body">
        <h3>${esc(item.name)}</h3>
        <span class="mc-jp" lang="ja">${esc(item.jp)}</span>
        <p class="mc-desc">${esc(item.desc)}</p>
      </span>
      <span class="mc-side">
        <span class="mc-price">&yen;${item.price}</span>
        <span class="mc-tags">
          ${item.tags.map((t) => `<span class="tag ${t === 'signature' ? 'tag-accent' : t === 'new' ? 'tag-gold' : 'tag-green'}">${t}</span>`).join('')}
        </span>
      </span>
    </article>`;
      }).join('');

      /* Dim items that belong to the other half of the day */
      $$('.menu-card.dim', grid).forEach((c) => {
        c.style.opacity = '0.55';
      });

      /* Bind card opens */
      $$('.menu-card', grid).forEach((card) => {
        const open = () => openMenuModal(card.dataset.id);
        card.addEventListener('click', open);
        card.addEventListener('keydown', (e) => {
          if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            open();
          }
        });
      });
    }

    /* Modal */
    function openMenuModal(name) {
      const item = MENU_DATA.find((i) => i.name === name);
      if (!item) return;
      const modal = $('#menuModal');
      $('#modalGlyph').innerHTML = GLYPHS[item.glyph];
      $('#modalGlyphJp').textContent = item.jp;
      $('#modalTitle').textContent = item.name;
      $('#modalPrice').innerHTML = '&yen;' + item.price;
      $('#modalJp').textContent = item.jp;
      $('#modalDesc').textContent = item.long;
      $('#modalSpecs').innerHTML = Object.entries(item.specs).map(([k, v]) =>
        `<div class="spec"><dt>${esc(k)}</dt><dd>${esc(v)}</dd></div>`
      ).join('');
      const avail = item.time === 'night' ? 'Served from 18:00 at the kura bar.' : 'Served during cafe hours, 8:00–17:00.';
      $('#modalNoteText').textContent = avail + ' ' + item.note;
      modal.classList.add('open');
      document.body.classList.add('locked');
      /* Focus management */
      setTimeout(() => $('.modal-close', modal).focus(), 300);
    }

    function closeMenuModal() {
      $('#menuModal').classList.remove('open');
      document.body.classList.remove('locked');
    }

    function initMenuModal() {
      $$('[data-close-modal]').forEach((el) => el.addEventListener('click', closeMenuModal));
      document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && $('#menuModal').classList.contains('open')) closeMenuModal();
      });
    }
  </script>

  <script>
    /* ------------------------------------------------------------
I. SEASONAL CAROUSEL
------------------------------------------------------------ */
    const SEASONAL_DATA = [{
        title: 'Tsukimi Dango Affogato',
        jp: '月見団子',
        badge: 'Autumn',
        until: 'Until Sep 30',
        price: 880,
        desc: 'Chewy rice dumplings under espresso and soft-serve, for moon-viewing season.',
        art: `<svg viewBox="0 0 320 200"><rect width="320" height="200" fill="#e8d9bd"/><circle cx="245" cy="52" r="34" fill="#f4ede1" stroke="#b3402a" stroke-width="2"/><path d="M0,200 L0,150 Q80,120 160,145 T320,140 L320,200 Z" fill="#6f7d4a" opacity="0.35"/><g fill="#fbf7ee" stroke="#c9bda5"><circle cx="120" cy="150" r="17"/><circle cx="152" cy="150" r="17"/><circle cx="136" cy="124" r="17"/></g><path d="M110 96 q-6,-10 0,-18 M136 90 q-6,-10 0,-18" stroke="#8a7d6d" fill="none" stroke-width="2" stroke-linecap="round"/></svg>`
      },
      {
        title: 'Sanma & Junmai Pairing',
        jp: '秋刀魚と純米',
        badge: 'Autumn',
        until: 'Until Nov 15',
        price: 1600,
        desc: 'Grilled autumn saury with our fresh junmai — the season\u2019s oldest conversation.',
        art: `<svg viewBox="0 0 320 200"><rect width="320" height="200" fill="#1c2430"/><circle cx="70" cy="48" r="26" fill="#d9a441"/><path d="M60,140 q60,-26 130,-8 q40,10 70,2 q-14,26 -50,30 q-80,10 -150,-24z" fill="#9db4c0" opacity="0.85"/><path d="M200,132 l34,-20 l-4,26z" fill="#9db4c0" opacity="0.65"/><g stroke="#f0e6d2" stroke-width="1.6" opacity="0.5" fill="none"><path d="M40,170 h40 M240,60 h30 M250,80 h20"/></g><path d="M252,30 q5,-8 0,-14 M262,34 q5,-8 0,-14" stroke="#f0e6d2" fill="none" stroke-width="2" stroke-linecap="round"/></svg>`
      },
      {
        title: 'New-Brew Cloudy Sake',
        jp: '新酒 濁り',
        badge: 'Winter preview',
        until: 'From Nov 1',
        price: 1000,
        desc: 'The first unfiltered pressing of the season, alive and faintly sparkling.',
        art: `<svg viewBox="0 0 320 200"><rect width="320" height="200" fill="#e3d6bf"/><path d="M130,60 h60 l-8,16 c14,8 20,20 20,34 0,26 -19,44 -42,44 s-42,-18 -42,-44 c0,-14 6,-26 20,-34z" fill="#fbf7ee" stroke="#b3402a" stroke-width="2"/><path d="M138,76 h44" stroke="#b3402a" stroke-width="2"/><circle cx="148" cy="110" r="3" fill="#c9bda5"/><circle cx="166" cy="122" r="2.5" fill="#c9bda5"/><circle cx="172" cy="102" r="2" fill="#c9bda5"/><path d="M70,40 q8,-10 0,-20 M84,44 q8,-10 0,-20" stroke="#8a7d6d" fill="none" stroke-width="2" stroke-linecap="round"/></svg>`
      },
      {
        title: 'Persimmon & Hōjicha Set',
        jp: '柿と焙じ茶',
        badge: 'Autumn',
        until: 'Until Oct 31',
        price: 720,
        desc: 'Ripe local persimmon with roasted tea and a black-sesame tuile.',
        art: `<svg viewBox="0 0 320 200"><rect width="320" height="200" fill="#f0e8d8"/><circle cx="120" cy="118" r="36" fill="#d97b3f"/><path d="M112,84 q8,-8 16,0 q-8,6 -16,0z" fill="#6f7d4a"/><path d="M120,84 v-10" stroke="#6f7d4a" stroke-width="2.5"/><path d="M210,96 h44 l-4,44 h-36z" fill="#fbf7ee" stroke="#8a7d6d" stroke-width="1.6"/><path d="M218,88 q-5,-8 0,-16 M232,88 q-5,-8 0,-16" stroke="#8a7d6d" fill="none" stroke-width="1.8" stroke-linecap="round"/></svg>`
      }
    ];

    function initSeasonal() {
      const carousel = $('#seasonCarousel');
      const dotsWrap = $('#seasonDots');
      carousel.innerHTML = SEASONAL_DATA.map((s) =>
        `<article class="season-card">
      <div class="sc-art">${s.art}<span class="sc-badge">${s.badge}</span></div>
      <div class="sc-body">
        <h3>${esc(s.title)}</h3>
        <p class="sc-jp" lang="ja">${esc(s.jp)}</p>
        <p>${esc(s.desc)}</p>
        <div class="sc-foot">
          <span class="sc-price">&yen;${s.price}</span>
          <span class="sc-until">${s.until}</span>
        </div>
      </div>
    </article>`
      ).join('');
      dotsWrap.innerHTML = SEASONAL_DATA.map((_, i) => `<i data-i="${i}"></i>`).join('');
      const dots = $$('i', dotsWrap);

      function updateDots() {
        const cardW = carousel.firstElementChild.offsetWidth + 16;
        const idx = clamp(Math.round(carousel.scrollLeft / cardW), 0, SEASONAL_DATA.length - 1);
        dots.forEach((d, i) => d.classList.toggle('on', i === idx));
      }
      carousel.addEventListener('scroll', updateDots, {
        passive: true
      });

      function step(dir) {
        const cardW = carousel.firstElementChild.offsetWidth + 16;
        const idx = clamp(Math.round(carousel.scrollLeft / cardW), 0, SEASONAL_DATA.length - 1);
        const next = clamp(idx + dir, 0, SEASONAL_DATA.length - 1);
        carousel.scrollTo({
          left: next * cardW,
          behavior: 'smooth'
        });
      }
      $('#seasonPrev').addEventListener('click', () => step(-1));
      $('#seasonNext').addEventListener('click', () => step(1));
      updateDots();
    }

    /* ------------------------------------------------------------
    J. GALLERY DRAG-SCROLL
    ------------------------------------------------------------ */
    const GALLERY_SCENES = [{
        label: 'The counter',
        jp: 'カウンター',
        svg: `<svg viewBox="0 0 250 334"><rect width="250" height="334" fill="#e8d9bd"/><rect x="0" y="210" width="250" height="124" fill="#8a5a3b"/><rect x="0" y="200" width="250" height="14" fill="#6d4429"/><circle cx="125" cy="110" r="52" fill="#b3402a"/><circle cx="148" cy="96" r="44" fill="#e8d9bd"/><rect x="88" y="176" width="74" height="8" rx="4" fill="#1c1917" opacity="0.8"/><path d="M118,168 q-5,-9 0,-16 M132,168 q-5,-9 0,-16" stroke="#1c1917" opacity="0.5" fill="none" stroke-width="2" stroke-linecap="round"/></svg>`
      },
      {
        label: 'Cedar tanks',
        jp: '杉桶',
        svg: `<svg viewBox="0 0 250 334"><rect width="250" height="334" fill="#141a21"/><g><rect x="22" y="60" width="58" height="180" rx="10" fill="#2a241d" stroke="#8a5a3b" stroke-width="3"/><rect x="96" y="40" width="58" height="200" rx="10" fill="#2a241d" stroke="#8a5a3b" stroke-width="3"/><rect x="170" y="60" width="58" height="180" rx="10" fill="#2a241d" stroke="#8a5a3b" stroke-width="3"/></g><circle cx="51" cy="100" r="8" fill="#d9a441" opacity="0.8"/><circle cx="125" cy="90" r="8" fill="#d9a441" opacity="0.8"/><circle cx="199" cy="100" r="8" fill="#d9a441" opacity="0.8"/><rect x="0" y="240" width="250" height="94" fill="#0a0d11"/><path d="M30,270 h40 M110,280 h30 M180,268 h40" stroke="#d9a441" opacity="0.25" stroke-width="2"/></svg>`
      },
      {
        label: 'Tatami room',
        jp: '座敷',
        svg: `<svg viewBox="0 0 250 334"><rect width="250" height="334" fill="#f0e8d8"/><rect x="0" y="0" width="250" height="130" fill="#fbf7ee"/><rect x="30" y="30" width="80" height="70" fill="#9db4c0" opacity="0.5" stroke="#1c1917" stroke-width="2"/><rect x="140" y="30" width="80" height="70" fill="#9db4c0" opacity="0.5" stroke="#1c1917" stroke-width="2"/><path d="M70,65 h0 M30,65 h80 M140,65 h80" stroke="#1c1917" stroke-width="1.4"/><g fill="#6f7d4a" opacity="0.7"><rect x="0" y="130" width="125" height="100"/><rect x="125" y="130" width="125" height="100" fill="#7a8a56"/></g><g fill="#8a5a3b"><rect x="0" y="230" width="125" height="104" opacity="0.8"/><rect x="125" y="230" width="125" height="104" opacity="0.9"/></g><circle cx="125" cy="185" r="14" fill="#b3402a" opacity="0.85"/></svg>`
      },
      {
        label: 'Roast drum',
        jp: '焙煎機',
        svg: `<svg viewBox="0 0 250 334"><rect width="250" height="334" fill="#e3d6bf"/><circle cx="125" cy="140" r="70" fill="#1c1917"/><circle cx="125" cy="140" r="52" fill="#8a5a3b"/><circle cx="125" cy="140" r="52" fill="none" stroke="#6d4429" stroke-width="8" stroke-dasharray="10 14"/><circle cx="125" cy="140" r="16" fill="#1c1917"/><g fill="#6d4429"><ellipse cx="100" cy="248" rx="9" ry="6"/><ellipse cx="122" cy="256" rx="9" ry="6"/><ellipse cx="146" cy="248" rx="9" ry="6"/><ellipse cx="134" cy="270" rx="9" ry="6"/></g><path d="M90,60 q-8,-12 0,-24 M125,50 q-8,-12 0,-24 M160,60 q-8,-12 0,-24" stroke="#8a7d6d" fill="none" stroke-width="2.5" stroke-linecap="round"/></svg>`
      },
      {
        label: 'Night lantern',
        jp: '提灯',
        svg: `<svg viewBox="0 0 250 334"><rect width="250" height="334" fill="#0f1318"/><circle cx="125" cy="150" r="90" fill="#d9a441" opacity="0.12"/><rect x="95" y="90" width="60" height="110" rx="26" fill="#d9a441"/><g stroke="#8c6a2f" stroke-width="2.4" opacity="0.7"><path d="M98,112 h54 M96,134 h58 M96,156 h58 M98,178 h54"/></g><path d="M110,104 v76 M140,104 v76" stroke="#8c6a2f" stroke-width="2" opacity="0.5"/><text x="125" y="158" text-anchor="middle" font-size="26" fill="#4a350f" font-family="serif">月</text><path d="M125,200 v26" stroke="#8c6a2f" stroke-width="3"/><circle cx="125" cy="234" r="5" fill="#d9a441"/><circle cx="60" cy="60" r="1.6" fill="#f0e6d2" opacity="0.7"/><circle cx="200" cy="80" r="1.4" fill="#f0e6d2" opacity="0.5"/><circle cx="180" cy="260" r="1.4" fill="#f0e6d2" opacity="0.6"/></svg>`
      },
      {
        label: 'The lane',
        jp: '路地',
        svg: `<svg viewBox="0 0 250 334"><rect width="250" height="334" fill="#1c2430"/><path d="M0,334 L95,140 h60 L250,334 Z" fill="#2a3542"/><rect x="30" y="120" width="52" height="130" fill="#3a4654"/><rect x="170" y="100" width="50" height="150" fill="#3a4654"/><g fill="#d9a441" opacity="0.85"><rect x="40" y="140" width="12" height="16"/><rect x="60" y="170" width="12" height="16"/><rect x="180" y="120" width="11" height="15"/><rect x="198" y="160" width="11" height="15"/></g><circle cx="125" cy="70" r="26" fill="#f0e6d2" opacity="0.9"/><circle cx="136" cy="62" r="22" fill="#1c2430"/><path d="M112,140 l13,-14 13,14z" fill="#b3402a" opacity="0.9"/></svg>`
      }
    ];

    function initGallery() {
      const strip = $('#galleryStrip');
      strip.innerHTML = GALLERY_SCENES.map((g) =>
        `<figure class="g-tile">${g.svg}
      <figcaption class="g-label"><b lang="ja">${esc(g.jp)}</b><span>${esc(g.label)}</span></figcaption>
    </figure>`
      ).join('');
      /* Drag to scroll (mouse); touch scrolls natively */
      let isDown = false,
        startX = 0,
        startScroll = 0;
      strip.addEventListener('mousedown', (e) => {
        isDown = true;
        strip.classList.add('dragging');
        startX = e.pageX;
        startScroll = strip.scrollLeft;
      });
      window.addEventListener('mousemove', (e) => {
        if (!isDown) return;
        e.preventDefault();
        strip.scrollLeft = startScroll - (e.pageX - startX);
      });
      window.addEventListener('mouseup', () => {
        isDown = false;
        strip.classList.remove('dragging');
      });
    }

    /* ------------------------------------------------------------
    K. OMIKUJI FORTUNE
    ------------------------------------------------------------ */
    const FORTUNES = [{
        rank: '大吉',
        en: 'Daikichi · Great blessing',
        poem: '月が出れば道も明るい — when the moon rises, the road brightens too.',
        drink: 'Celebration calls for the <b>Aged Sake Flight</b>.'
      },
      {
        rank: '中吉',
        en: 'Chūkichi · Moderate blessing',
        poem: '急がぬ湯は香を深める — water that isn\u2019t rushed deepens the aroma.',
        drink: 'A slow <b>Hand-drip House Roast</b> is written in this stick.'
      },
      {
        rank: '小吉',
        en: 'Shōkichi · Small blessing',
        poem: '小さき縁も杯から — even small bonds begin over a cup.',
        drink: 'Share a pot of <b>Ceremonial Matcha</b> with someone.'
      },
      {
        rank: '吉',
        en: 'Kichi · Blessing',
        poem: '待つものほど甘くなる — what is waited for turns sweet.',
        drink: 'The <b>Thick Hotcake</b> takes twenty minutes. Worth every one.'
      },
      {
        rank: '半吉',
        en: 'Hankichi · Half blessing',
        poem: '半分満ちた杯を祝え — celebrate the cup that is half full.',
        drink: 'Try the <b>Yuzu Sour Sake</b> — bright things ahead.'
      },
      {
        rank: '末吉',
        en: 'Suekichi · Growing luck',
        poem: '夜明け前の珈琲の香り — coffee aroma just before dawn.',
        drink: 'Come early; the first <b>Nel-drip Iced Coffee</b> is yours.'
      },
      {
        rank: '末小吉',
        en: 'Sueshōkichi · Quiet luck',
        poem: '静けさもまた一つの味 — quietness is also a flavour.',
        drink: 'The <b>Kobicha Pot</b> suits this mood, poured twice.'
      },
      {
        rank: '小凶→吉',
        en: 'Turning luck',
        poem: '濁りもやがて澄む — even cloudiness clears in time.',
        drink: 'Wait for the <b>New-Brew Cloudy Sake</b> — it\u2019s worth the season.'
      }
    ];

    function initOmikuji() {
      const cylinder = $('#okiCylinder');
      const intro = $('#okiIntro');
      const result = $('#okiResult');
      const drawBtn = $('#okiDrawBtn');
      const againBtn = $('#okiAgain');
      let shaking = false;

      function drawFortune() {
        if (shaking) return;
        shaking = true;
        cylinder.classList.remove('drawn');
        void cylinder.offsetWidth; /* restart animations */
        cylinder.classList.add('shaking');
        setTimeout(() => {
          cylinder.classList.remove('shaking');
          cylinder.classList.add('drawn');
        }, 500);
        setTimeout(() => {
          const f = FORTUNES[Math.floor(Math.random() * FORTUNES.length)];
          $('#fRank').textContent = f.rank;
          $('#fRankEn').textContent = f.en;
          $('#fPoem').textContent = f.poem;
          $('#fDrink').innerHTML = f.drink;
          state.fortuneDraws += 1;
          $('#okiCount').textContent = state.fortuneDraws === 1 ?
            'First draw of this visit' :
            `Draw ${state.fortuneDraws} this visit`;
          intro.style.display = 'none';
          result.classList.add('show');
          shaking = false;
        }, 1400);
      }

      function reset() {
        result.classList.remove('show');
        intro.style.display = 'block';
        cylinder.classList.remove('drawn');
      }

      drawBtn.addEventListener('click', drawFortune);
      againBtn.addEventListener('click', reset);
      cylinder.addEventListener('click', drawFortune);
      cylinder.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          drawFortune();
        }
      });

      /* Shake-to-draw on devices that expose motion events */
      let lastShake = 0;
      window.addEventListener('devicemotion', (e) => {
        const a = e.accelerationIncludingGravity;
        if (!a) return;
        const force = Math.abs(a.x) + Math.abs(a.y) + Math.abs(a.z);
        if (force > 38 && Date.now() - lastShake > 2500) {
          lastShake = Date.now();
          /* Only trigger when the section is on screen */
          const rect = $('#omikuji').getBoundingClientRect();
          if (rect.top < window.innerHeight && rect.bottom > 0) {
            if (result.classList.contains('show')) reset();
            drawFortune();
          }
        }
      }, {
        passive: true
      });
    }

    /* ------------------------------------------------------------
    L. EVENTS & RSVP
    ------------------------------------------------------------ */
    const EVENTS_DATA = [{
        month: 'SEP',
        day: '06',
        week: 'SAT',
        title: 'Morning Cupping Circle',
        jp: 'カッピングの会',
        desc: 'Taste four of this month\u2019s roasts side by side and learn to say what you taste. Beginner-friendly, 90 minutes.',
        meta: '10:00 · ¥1,500 · 8 seats'
      },
      {
        month: 'SEP',
        day: '13',
        week: 'SAT',
        title: 'Brewery Tour & Fresh Press',
        jp: '蔵見学',
        desc: 'Walk the tanks with our brewer, then taste the week\u2019s pressing before it\u2019s bottled. Includes two pours.',
        meta: '15:00 · ¥2,200 · 10 seats'
      },
      {
        month: 'SEP',
        day: '20',
        week: 'SAT',
        title: 'Vinyl Night: Jazz from Kyoto',
        jp: 'レコードの夜',
        desc: 'A local collector spins 50s–70s Japanese jazz while the kura bar pours. No cover, just come.',
        meta: '19:00 · Free · Walk-ins'
      },
      {
        month: 'SEP',
        day: '28',
        week: 'SUN',
        title: 'Tsukimi Moon Viewing',
        jp: '月見の会',
        desc: 'Rooftop mats, dango, and new-brew sake under the harvest moon. Our favourite night of the year.',
        meta: '19:30 · ¥3,000 · 20 seats'
      }
    ];

    function initEvents() {
      const list = $('#eventList');
      list.innerHTML = EVENTS_DATA.map((ev, i) =>
        `<article class="event-card reveal reveal-d${i % 4}">
      <div class="event-date">
        <div class="ed-month">${ev.month}</div>
        <div class="ed-day">${ev.day}</div>
        <div class="ed-week">${ev.week}</div>
      </div>
      <div class="event-body">
        <h3>${esc(ev.title)}</h3>
        <p class="ev-jp" lang="ja">${esc(ev.jp)}</p>
        <p>${esc(ev.desc)}</p>
        <div class="event-foot">
          <span class="event-meta">${esc(ev.meta)}</span>
          <button class="rsvp-btn" data-event="${esc(ev.title)}">
            <span class="check">&#10003;</span><span class="txt">RSVP</span>
          </button>
        </div>
      </div>
    </article>`
      ).join('');

      /* Re-observe the freshly inserted reveals */
      const obs = new IntersectionObserver((entries) => {
        entries.forEach((en) => {
          if (en.isIntersecting) {
            en.target.classList.add('in-view');
            obs.unobserve(en.target);
          }
        });
      }, {
        threshold: 0.12
      });
      $$('.reveal', list).forEach((el) => obs.observe(el));

      $$('.rsvp-btn', list).forEach((btn) => {
        btn.addEventListener('click', () => {
          const joined = btn.classList.toggle('joined');
          btn.querySelector('.txt').textContent = joined ? 'Going' : 'RSVP';
          if (joined) {
            toast(`Seat noted for "${btn.dataset.event}" — see you there.`, 'check');
          } else {
            toast(`RSVP cancelled for "${btn.dataset.event}".`, 'info');
          }
        });
      });
    }

    /* ------------------------------------------------------------
    M. TESTIMONIALS SLIDER
    ------------------------------------------------------------ */
    const TESTIMONIALS = [{
        quote: 'I came for the coffee and stayed until the lanterns came on. Then I stayed another three hours. This room has a way of keeping you.',
        name: 'Aiko M.',
        role: 'Regular, 6 years',
        kanji: '愛',
        color: '#b3402a'
      },
      {
        quote: 'The junmai flight taught me more about Kyoto than any guidebook. You can taste the building it was made in.',
        name: 'Daniel R.',
        role: 'Visitor, London',
        kanji: '旅',
        color: '#6f7d4a'
      },
      {
        quote: 'Twenty minutes for a hotcake and I would have waited forty. Some places earn your patience; Tsukiya rewards it.',
        name: 'Yuki T.',
        role: 'Weekend guest',
        kanji: '雪',
        color: '#a07a2c'
      },
      {
        quote: 'As a brewer myself, I\u2019m picky. Their kōji room discipline is impeccable — and the coffee isn\u2019t a side project, it\u2019s a peer.',
        name: 'Kenji S.',
        role: 'Fellow brewer',
        kanji: '匠',
        color: '#223a5e'
      }
    ];

    function initTestimonials() {
      const track = $('#tTrack');
      const dotsWrap = $('#tDots');
      const stage = $('#tStage');

      track.innerHTML = TESTIMONIALS.map((t) =>
        `<div class="t-slide">
      <div class="t-card">
        <div class="t-mark" lang="ja">「</div>
        <blockquote>${esc(t.quote)}</blockquote>
        <div class="t-author">
          <span class="t-avatar" lang="ja" style="background:${t.color}">${t.kanji}</span>
          <span class="t-name"><b>${esc(t.name)}</b><span>${esc(t.role)}</span></span>
        </div>
      </div>
    </div>`
      ).join('');

      dotsWrap.innerHTML = TESTIMONIALS.map((_, i) =>
        `<button data-i="${i}" aria-label="Go to testimonial ${i + 1}" class="${i === 0 ? 'on' : ''}"><i></i></button>`
      ).join('');

      function go(idx, user) {
        state.tIndex = (idx + TESTIMONIALS.length) % TESTIMONIALS.length;
        track.style.transform = `translateX(-${state.tIndex * 100}%)`;
        $$('button', dotsWrap).forEach((b, i) => b.classList.toggle('on', i === state.tIndex));
        if (user) restartAuto();
      }

      function restartAuto() {
        clearInterval(state.tTimer);
        state.tTimer = setInterval(() => go(state.tIndex + 1), 6500);
      }

      $('#tPrev').addEventListener('click', () => go(state.tIndex - 1, true));
      $('#tNext').addEventListener('click', () => go(state.tIndex + 1, true));
      $$('button', dotsWrap).forEach((b) =>
        b.addEventListener('click', () => go(parseInt(b.dataset.i, 10), true))
      );

      /* Touch swipe */
      let sx = 0;
      stage.addEventListener('touchstart', (e) => {
        sx = e.touches[0].clientX;
      }, {
        passive: true
      });
      stage.addEventListener('touchend', (e) => {
        const dx = e.changedTouches[0].clientX - sx;
        if (Math.abs(dx) > 44) go(state.tIndex + (dx < 0 ? 1 : -1), true);
      }, {
        passive: true
      });

      /* Pause auto-rotate while the section is off-screen */
      const visObs = new IntersectionObserver((entries) => {
        entries.forEach((en) => {
          if (en.isIntersecting) {
            restartAuto();
          } else {
            clearInterval(state.tTimer);
          }
        });
      }, {
        threshold: 0.25
      });
      visObs.observe(stage);

      go(0);
    }
  </script>

  <script>
    /* ------------------------------------------------------------
N. RESERVATION FLOW
------------------------------------------------------------ */
    const DAY_SLOTS = ['08:30', '09:30', '10:30', '11:30', '13:00', '14:00', '15:00', '16:00'];
    const NIGHT_SLOTS = ['18:00', '18:30', '19:00', '19:30', '20:00', '20:30', '21:00', '22:00'];
    const FULL_SLOTS = ['19:30', '10:30']; /* simulated capacity */

    function renderSlots() {
      const grid = $('#slotGrid');
      const slots = state.theme === 'night' ? NIGHT_SLOTS : DAY_SLOTS;
      $('#slotModeLabel').textContent = state.theme === 'night' ? 'kura bar hours' : 'cafe hours';
      grid.innerHTML = slots.map((s) => {
        const full = FULL_SLOTS.includes(s);
        return `<button type="button" class="slot ${full ? 'full' : ''} ${state.slot === s ? 'on' : ''}"
      data-slot="${s}" ${full ? 'aria-disabled="true"' : ''}>${s}</button>`;
      }).join('');
      $$('.slot:not(.full)', grid).forEach((btn) => {
        btn.addEventListener('click', () => {
          state.slot = btn.dataset.slot;
          $$('.slot', grid).forEach((b) => b.classList.toggle('on', b === btn));
          $('#rTimeErr').classList.remove('show');
        });
      });
    }

    function goToStep(n) {
      $$('.f-step').forEach((s) => s.classList.toggle('active', s.dataset.step === String(n)));
      $$('#reserveSteps i').forEach((bar, i) => bar.classList.toggle('on', i < n));
      $('#reservePanel').scrollIntoView({
        behavior: 'smooth',
        block: 'start'
      });
    }

    function validateStep1() {
      let ok = true;
      const dateInput = $('#rDate');
      const today = new Date();
      today.setHours(0, 0, 0, 0);
      const chosen = dateInput.value ? new Date(dateInput.value + 'T00:00:00') : null;
      if (!chosen || chosen < today) {
        dateInput.classList.add('invalid');
        $('#rDateErr').classList.add('show');
        ok = false;
      } else {
        dateInput.classList.remove('invalid');
        $('#rDateErr').classList.remove('show');
      }
      if (!state.slot) {
        $('#rTimeErr').classList.add('show');
        ok = false;
      }
      return ok;
    }

    function buildSummary() {
      const dateVal = $('#rDate').value;
      const d = new Date(dateVal + 'T00:00:00');
      const pretty = d.toLocaleDateString('en-GB', {
        weekday: 'short',
        day: 'numeric',
        month: 'short'
      });
      const mode = state.theme === 'night' ? 'Kura bar' : 'Cafe';
      $('#summaryBox').innerHTML = `
    <div class="s-row"><span>Date</span><b>${esc(pretty)}</b></div>
    <div class="s-row"><span>Time</span><b>${esc(state.slot)} &middot; ${mode}</b></div>
    <div class="s-row"><span>Party</span><b>${state.party} guest${state.party > 1 ? 's' : ''}</b></div>
    <div class="s-row"><span>Seating</span><b>${esc(state.seat)}</b></div>
    ${state.occasion ? `<div class="s-row"><span>Occasion</span><b>${esc(state.occasion)}</b></div>` : ''}
  `;
    }

    function validateStep3() {
      let ok = true;
      const name = $('#rName');
      const mail = $('#rMail');
      if (!name.value.trim() || name.value.trim().length < 2) {
        name.classList.add('invalid');
        $('#rNameErr').classList.add('show');
        ok = false;
      } else {
        name.classList.remove('invalid');
        $('#rNameErr').classList.remove('show');
      }
      const mailOk = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(mail.value.trim());
      if (!mailOk) {
        mail.classList.add('invalid');
        $('#rMailErr').classList.add('show');
        ok = false;
      } else {
        mail.classList.remove('invalid');
        $('#rMailErr').classList.remove('show');
      }
      return ok;
    }

    function generateCode() {
      const chars = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
      let code = 'TKY-';
      for (let i = 0; i < 4; i++) code += chars[Math.floor(Math.random() * chars.length)];
      return code;
    }

    function initReservation() {
      const form = $('#reserveForm');
      const dateInput = $('#rDate');

      /* Date input bounds: today + 60 days */
      const today = new Date();
      const max = new Date();
      max.setDate(max.getDate() + 60);
      dateInput.min = today.toISOString().split('T')[0];
      dateInput.max = max.toISOString().split('T')[0];
      dateInput.value = today.toISOString().split('T')[0];
      dateInput.addEventListener('change', () => {
        dateInput.classList.remove('invalid');
        $('#rDateErr').classList.remove('show');
      });

      /* Party stepper */
      const pValue = $('#pValue');
      const pLabel = $('#pLabel');
      $('#pMinus').addEventListener('click', () => {
        state.party = clamp(state.party - 1, 1, 8);
        pValue.textContent = state.party;
        pLabel.textContent = state.party === 1 ? 'guest' : 'guests';
      });
      $('#pPlus').addEventListener('click', () => {
        state.party = clamp(state.party + 1, 1, 8);
        pValue.textContent = state.party;
        pLabel.textContent = 'guests';
      });

      renderSlots();

      /* Chips: seating */
      $$('#seatChips .chip').forEach((chip) => {
        chip.addEventListener('click', () => {
          $$('#seatChips .chip').forEach((c) => c.classList.remove('on'));
          chip.classList.add('on');
          state.seat = chip.dataset.seat;
        });
      });

      /* Chips: occasion (toggle) */
      $$('#occasionChips .chip').forEach((chip) => {
        chip.addEventListener('click', () => {
          const wasOn = chip.classList.contains('on');
          $$('#occasionChips .chip').forEach((c) => c.classList.remove('on'));
          if (!wasOn) {
            chip.classList.add('on');
            state.occasion = chip.dataset.occ;
          } else {
            state.occasion = null;
          }
        });
      });

      /* Step navigation */
      $('#toStep2').addEventListener('click', () => {
        if (!validateStep1()) {
          toast('A couple of details need attention above.', 'info');
          return;
        }
        goToStep(2);
      });
      $('#backTo1').addEventListener('click', () => goToStep(1));
      $('#toStep3').addEventListener('click', () => {
        buildSummary();
        goToStep(3);
      });
      $('#backTo2').addEventListener('click', () => goToStep(2));

      /* Submit */
      form.addEventListener('submit', (e) => {
        e.preventDefault();
        if (!validateStep3()) return;
        const dateVal = dateInput.value;
        const d = new Date(dateVal + 'T00:00:00');
        const pretty = d.toLocaleDateString('en-GB', {
          weekday: 'long',
          day: 'numeric',
          month: 'long'
        });
        const code = generateCode();
        $('#ticketDetail').innerHTML =
          `${esc($('#rName').value.trim())} &middot; party of ${state.party}<br>` +
          `${esc(pretty)} at <b>${esc(state.slot)}</b><br>` +
          `${esc(state.seat)}${state.occasion ? ' &middot; ' + esc(state.occasion) : ''}`;
        $('#ticketCode').textContent = code;
        form.style.display = 'none';
        $('#reserveSteps').style.display = 'none';
        $('#reserveDone').classList.add('show');
        toast('Reservation received. We\u2019ll hold your seats.', 'check');
      });

      /* Start over */
      $('#newReserve').addEventListener('click', () => {
        form.reset();
        dateInput.value = today.toISOString().split('T')[0];
        state.party = 2;
        state.slot = null;
        state.seat = 'Counter';
        state.occasion = null;
        pValue.textContent = '2';
        pLabel.textContent = 'guests';
        $$('#seatChips .chip').forEach((c, i) => c.classList.toggle('on', i === 0));
        $$('#occasionChips .chip').forEach((c) => c.classList.remove('on'));
        renderSlots();
        $('#reserveDone').classList.remove('show');
        form.style.display = 'block';
        $('#reserveSteps').style.display = 'flex';
        goToStep(1);
      });
    }

    /* ------------------------------------------------------------
    O. FAQ ACCORDION
    ------------------------------------------------------------ */
    const FAQ_DATA = [{
        q: 'Do I need a reservation?',
        a: 'For the cafe, walk-ins are welcome and usually fine before 14:00. For the kura bar — especially Friday and Saturday evenings — we strongly recommend reserving. Fourteen seats fill fast.'
      },
      {
        q: 'Can I buy your beans or sake to take home?',
        a: 'Yes. Beans are sold at the counter for two weeks after roast day. Our junmai is sold in 300ml and 720ml bottles while each batch lasts — usually about a month.'
      },
      {
        q: 'Is the brewery tour suitable for beginners?',
        a: 'Completely. We explain each step in plain language and keep technical detail for whoever wants it. The tour ends with two tastings, so it\u2019s 20+ only for the pours; younger guests are welcome with juice instead.'
      },
      {
        q: 'Do you serve food for dietary restrictions?',
        a: 'We keep vegetarian options on both menus and can adjust most plates for allergies with a day\u2019s notice — mention it in your reservation notes and we\u2019ll confirm by email.'
      },
      {
        q: 'Is there a break between cafe and bar hours?',
        a: 'Yes, the room rests from 17:00 to 18:00 daily while we switch the lights, the music, and the menus. Come back at 18:00 and it\u2019s genuinely a different place.'
      },
      {
        q: 'How do I get there from Kyoto Station?',
        a: 'Take the Karasuma Line to Karasuma-Oike, exit 0, and walk six minutes north-east toward the canal. Look for the paper lantern with the moon mark — you\u2019ll smell the roast before you see it.'
      }
    ];

    function initFaq() {
      const list = $('#faqList');
      list.innerHTML = FAQ_DATA.map((f, i) =>
        `<div class="faq-item">
      <button class="faq-q" aria-expanded="false">
        <span class="q-num">${pad(i + 1)}</span>
        <span>${esc(f.q)}</span>
        <span class="q-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg></span>
      </button>
      <div class="faq-a"><p>${esc(f.a)}</p></div>
    </div>`
      ).join('');

      $$('.faq-item', list).forEach((item) => {
        const btn = $('.faq-q', item);
        const panel = $('.faq-a', item);
        btn.addEventListener('click', () => {
          const isOpen = item.classList.contains('open');
          /* Close others for a tidy accordion */
          $$('.faq-item.open', list).forEach((other) => {
            if (other !== item) {
              other.classList.remove('open');
              $('.faq-q', other).setAttribute('aria-expanded', 'false');
              $('.faq-a', other).style.maxHeight = '0px';
            }
          });
          item.classList.toggle('open', !isOpen);
          btn.setAttribute('aria-expanded', String(!isOpen));
          panel.style.maxHeight = isOpen ? '0px' : panel.scrollHeight + 'px';
        });
      });
    }

    /* ------------------------------------------------------------
    P. NEWSLETTER
    ------------------------------------------------------------ */
    function initNewsletter() {
      const form = $('#newsForm');
      const input = $('#newsMail');
      const done = $('#newsDone');
      form.addEventListener('submit', (e) => {
        e.preventDefault();
        const ok = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(input.value.trim());
        if (!ok) {
          input.classList.add('invalid');
          toast('Please check that email address.', 'info');
          return;
        }
        input.classList.remove('invalid');
        form.style.display = 'none';
        done.classList.add('show');
        toast('Subscribed — one letter a month, no noise.', 'check');
      });
      input.addEventListener('input', () => input.classList.remove('invalid'));
    }

    /* ------------------------------------------------------------
    Q. TOASTS
    ------------------------------------------------------------ */
    const TOAST_ICONS = {
      check: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M8.5 12.5l2.5 2.5 4.5-5"/></svg>',
      info: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v.01M12 11v5"/></svg>',
      moon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.5 13.5A8.5 8.5 0 1 1 10.5 3.5a7 7 0 0 0 10 10z"/></svg>'
    };

    function toast(message, icon = 'check') {
      const rack = $('#toastRack');
      const el = document.createElement('div');
      el.className = 'toast';
      el.setAttribute('role', 'status');
      el.innerHTML = `<span class="t-ico">${TOAST_ICONS[icon] || TOAST_ICONS.check}</span><span>${esc(message)}</span>`;
      rack.appendChild(el);
      requestAnimationFrame(() => el.classList.add('show'));
      setTimeout(() => {
        el.classList.remove('show');
        setTimeout(() => el.remove(), 450);
      }, 3400);
      /* Keep the rack shallow */
      while (rack.children.length > 3) rack.firstElementChild.remove();
    }

    /* ------------------------------------------------------------
    R. INIT
    ------------------------------------------------------------ */
    function initMarquee() {
      /* Duplicate track content for a seamless loop */
      const track = $('#marqueeTrack');
      track.innerHTML += track.innerHTML;
    }

    function initHeroStars() {
      const wrap = $('#heroStars');
      const frag = document.createDocumentFragment();
      for (let i = 0; i < 26; i++) {
        const s = document.createElement('i');
        s.style.left = Math.random() * 100 + '%';
        s.style.top = Math.random() * 55 + '%';
        s.style.animationDelay = (Math.random() * 3).toFixed(2) + 's';
        s.style.transform = 'scale(' + (0.6 + Math.random()).toFixed(2) + ')';
        frag.appendChild(s);
      }
      wrap.appendChild(frag);
    }

    document.addEventListener('DOMContentLoaded', () => {
      initPreloader();
      initScrollEffects();
      initOverlayMenu();
      initClocks();
      initThemeToggle();
      initReveals();
      initCounters();
      renderMenuTabs();
      renderMenuGrid();
      initMenuModal();
      initSeasonal();
      initGallery();
      initOmikuji();
      initEvents();
      initTestimonials();
      initReservation();
      initFaq();
      initNewsletter();
      initMarquee();
      initHeroStars();
      /* Apply initial theme without announcing it */
      applyTheme('day', false);
    });
  </script>
</body>

</html>