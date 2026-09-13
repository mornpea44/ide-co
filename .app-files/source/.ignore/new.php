<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>NORDLYS · Night Rail of the North</title>
  <meta name="description" content="NORDLYS — the sleeper rail network of the North. Live departures, four night lines, cabins and a working reservation desk.">
  <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
  <link href="https://cdn.jsdelivr.net/fontsource/css/bricolage-grotesque@latest/latin-600-normal.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/fontsource/css/bricolage-grotesque@latest/latin-700-normal.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/fontsource/css/bricolage-grotesque@latest/latin-800-normal.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/fontsource/css/archivo@latest/latin-400-normal.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/fontsource/css/archivo@latest/latin-500-normal.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/fontsource/css/archivo@latest/latin-600-normal.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/fontsource/css/ibm-plex-mono@latest/latin-400-normal.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/fontsource/css/ibm-plex-mono@latest/latin-500-normal.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/fontsource/css/ibm-plex-mono@latest/latin-600-normal.css" rel="stylesheet">
  <style>
    :root {
      --ink: #0c1512;
      --ink2: #101c17;
      --panel: #15231d;
      --panel2: #1a2b23;
      --line: #26392f;
      --line2: #34503f;
      --text: #e8efe8;
      --mut: #9fb4a7;
      --dim: #6d8377;
      --amber: #ffb454;
      --amber2: #ffcf87;
      --green: #41d98d;
      --red: #ff6157;
      --blue: #6fb7ff;
      --paper: #eef0e4;
      --paper2: #e3e7d6;
      --disp: 'Bricolage Grotesque', Georgia, serif;
      --body: 'Archivo', system-ui, sans-serif;
      --mono: 'IBM Plex Mono', ui-monospace, monospace;
      --hdrH: 68px;
    }

    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0
    }

    html {
      scroll-behavior: smooth;
      scroll-padding-top: 112px
    }

    body {
      background: var(--ink);
      color: var(--text);
      font: 400 16px/1.55 var(--body);
      overflow-x: hidden;
      -webkit-font-smoothing: antialiased
    }

    img {
      display: block;
      max-width: 100%
    }

    a {
      color: inherit;
      text-decoration: none
    }

    button {
      font-family: inherit;
      cursor: pointer
    }

    section[id] {
      scroll-margin-top: 112px
    }

    ::selection {
      background: var(--amber);
      color: #1a1206
    }

    :focus-visible {
      outline: 2px solid var(--amber);
      outline-offset: 3px;
      border-radius: 3px
    }

    ::-webkit-scrollbar {
      width: 11px;
      height: 11px
    }

    ::-webkit-scrollbar-track {
      background: #0a110e
    }

    ::-webkit-scrollbar-thumb {
      background: #22352b;
      border-radius: 6px;
      border: 2px solid #0a110e
    }

    ::-webkit-scrollbar-thumb:hover {
      background: #2f4a3b
    }

    #sky {
      position: fixed;
      inset: 0;
      z-index: 0;
      pointer-events: none
    }

    .aurora {
      position: fixed;
      inset: -30% -10%;
      z-index: 0;
      pointer-events: none;
      opacity: .55;
      filter: blur(46px);
      background:
        radial-gradient(40% 30% at 24% 18%, rgba(47, 191, 113, .20), transparent 70%),
        radial-gradient(34% 26% at 72% 10%, rgba(111, 183, 255, .10), transparent 70%),
        radial-gradient(30% 22% at 54% 32%, rgba(255, 180, 84, .08), transparent 70%);
      animation: aur 38s ease-in-out infinite alternate
    }

    @keyframes aur {
      to {
        transform: translate3d(4%, -3%, 0) scale(1.1) rotate(2deg)
      }
    }

    .wrap {
      max-width: 1200px;
      margin: 0 auto;
      padding: 0 24px
    }

    .kicker {
      font: 600 12px/1 var(--mono);
      letter-spacing: .2em;
      text-transform: uppercase;
      color: var(--amber);
      display: inline-flex;
      align-items: center;
      gap: 10px
    }

    .kicker::before {
      content: "";
      width: 26px;
      height: 1px;
      background: var(--amber);
      opacity: .7
    }

    h2 {
      font: 800 clamp(2rem, 4.4vw, 3.3rem)/1.02 var(--disp);
      letter-spacing: -.02em;
      margin: 14px 0
    }

    .lead {
      color: var(--mut);
      max-width: 46ch;
      font-size: 1.02rem
    }

    .btn {
      display: inline-flex;
      align-items: center;
      gap: 9px;
      border: 1px solid transparent;
      border-radius: 9px;
      padding: 12px 20px;
      font: 600 14px/1 var(--body);
      transition: transform .18s, box-shadow .25s, background .2s, color .2s, border-color .2s;
      white-space: nowrap
    }

    .btn-amber {
      background: var(--amber);
      color: #1d1304;
      box-shadow: 0 10px 24px -12px rgba(255, 180, 84, .6)
    }

    .btn-amber:hover {
      transform: translateY(-2px);
      box-shadow: 0 16px 30px -12px rgba(255, 180, 84, .7);
      background: var(--amber2)
    }

    .btn-ghost {
      background: transparent;
      border-color: var(--line2);
      color: var(--text)
    }

    .btn-ghost:hover {
      border-color: var(--amber);
      color: var(--amber)
    }

    .mono {
      font-family: var(--mono)
    }

    /* header */
    header {
      position: sticky;
      top: 0;
      z-index: 60;
      background: rgba(9, 15, 12, .66);
      transition: background .3s, box-shadow .3s
    }

    header.scrolled {
      background: rgba(9, 15, 12, .96);
      box-shadow: 0 1px 0 var(--line), 0 14px 34px -20px #000
    }

    .hdr {
      display: flex;
      align-items: center;
      justify-content: space-between;
      height: var(--hdrH);
      gap: 18px
    }

    .brand {
      display: flex;
      align-items: center;
      gap: 12px
    }

    .brand .wm {
      display: flex;
      flex-direction: column;
      line-height: 1
    }

    .brand .wm b {
      font: 800 18px/1 var(--disp);
      letter-spacing: .06em
    }

    .brand .wm span {
      font: 500 9px/1 var(--mono);
      letter-spacing: .34em;
      color: var(--dim);
      margin-top: 5px
    }

    .nav {
      display: flex;
      align-items: center;
      gap: 26px
    }

    .nav a {
      font: 500 13px/1 var(--mono);
      letter-spacing: .06em;
      color: var(--mut);
      position: relative;
      padding: 6px 0;
      transition: color .2s
    }

    .nav a::after {
      content: "";
      position: absolute;
      left: 0;
      bottom: -2px;
      width: 0;
      height: 2px;
      background: var(--amber);
      transition: width .25s
    }

    .nav a:hover {
      color: var(--text)
    }

    .nav a:hover::after,
    .nav a.active::after {
      width: 100%
    }

    .nav a.active {
      color: var(--amber)
    }

    .hdr-cta {
      display: flex;
      align-items: center;
      gap: 14px
    }

    .hdr-clock {
      font: 500 12px/1 var(--mono);
      color: var(--dim);
      letter-spacing: .08em
    }

    .menu-btn {
      display: none;
      width: 42px;
      height: 42px;
      border: 1px solid var(--line2);
      border-radius: 9px;
      background: transparent;
      color: var(--text);
      align-items: center;
      justify-content: center;
      transition: border-color .2s, color .2s
    }

    .menu-btn:hover {
      border-color: var(--amber);
      color: var(--amber)
    }

    .menu-btn svg {
      display: block
    }

    @media(max-width:900px) {
      .nav {
        display: none
      }

      .hdr-clock {
        display: none
      }

      .menu-btn {
        display: inline-flex
      }
    }

    .mnav {
      display: none
    }

    @media(max-width:900px) {
      .mnav {
        display: block;
        position: absolute;
        left: 0;
        right: 0;
        top: 100%;
        background: rgba(9, 15, 12, .985);
        border-top: 1px solid var(--line);
        border-bottom: 1px solid var(--line);
        padding: 10px 24px 18px;
        visibility: hidden;
        opacity: 0;
        transform: translateY(-8px);
        pointer-events: none;
        transition: opacity .25s, transform .25s, visibility .25s;
        backdrop-filter: blur(8px)
      }

      .mnav.open {
        visibility: visible;
        opacity: 1;
        transform: none;
        pointer-events: auto
      }

      .mnav a {
        display: block;
        font: 600 15px/1 var(--mono);
        letter-spacing: .04em;
        color: var(--mut);
        padding: 13px 0;
        border-bottom: 1px dashed var(--line);
        transition: color .2s, padding-left .2s
      }

      .mnav a:hover,
      .mnav a.active {
        color: var(--amber);
        padding-left: 6px
      }

      .mnav .btn {
        width: 100%;
        justify-content: center;
        margin-top: 16px
      }
    }

    /* ticker */
    .ticker {
      overflow: hidden;
      border-bottom: 1px solid var(--line);
      background: #0a120e
    }

    .ticker-track {
      display: flex;
      width: max-content;
      animation: tick 52s linear infinite
    }

    .ticker:hover .ticker-track {
      animation-play-state: paused
    }

    .ticker span {
      padding: 9px 0;
      font: 500 12px/1 var(--mono);
      letter-spacing: .07em;
      color: var(--amber2);
      white-space: nowrap
    }

    @keyframes tick {
      to {
        transform: translateX(-50%)
      }
    }

    main,
    footer {
      position: relative;
      z-index: 2
    }

    /* ===== hall / departures ===== */
    .hall {
      position: relative;
      padding: 64px 0 150px;
      overflow: hidden
    }

    .hall-grid {
      display: grid;
      grid-template-columns: minmax(0, 7fr) minmax(320px, 4fr);
      gap: 26px;
      align-items: start;
      position: relative;
      z-index: 3
    }

    @media(max-width:980px) {
      .hall-grid {
        grid-template-columns: 1fr
      }
    }

    .board {
      background: #0a100d;
      border: 1px solid var(--line);
      border-radius: 12px;
      box-shadow: 0 36px 70px -34px rgba(0, 0, 0, .85);
      overflow: hidden
    }

    .board-top {
      display: flex;
      align-items: center;
      gap: 14px;
      padding: 14px 18px;
      border-bottom: 1px solid var(--line);
      background: linear-gradient(180deg, #0e1712, #0a100d)
    }

    .lamps {
      display: flex;
      gap: 7px
    }

    .lamp {
      width: 10px;
      height: 10px;
      border-radius: 50%;
      background: #1c2b22
    }

    .lamp.g {
      background: var(--green);
      box-shadow: 0 0 9px var(--green);
      animation: pulse 2.4s infinite
    }

    .lamp.a {
      background: var(--amber);
      box-shadow: 0 0 9px var(--amber);
      animation: pulse 2.4s .8s infinite
    }

    .lamp.r {
      background: var(--red);
      box-shadow: 0 0 9px var(--red);
      animation: pulse 2.4s 1.6s infinite
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

    .board-top .bt {
      font: 600 13px/1 var(--mono);
      letter-spacing: .14em
    }

    .board-top .bt small {
      color: var(--dim);
      font-weight: 400;
      letter-spacing: .1em
    }

    .board-top .right {
      margin-left: auto;
      font: 500 11px/1 var(--mono);
      color: var(--dim);
      letter-spacing: .12em
    }

    .board-scroll {
      overflow-x: auto
    }

    .board-in {
      min-width: 600px
    }

    .bhead {
      display: grid;
      grid-template-columns: 78px 92px 1fr 56px 150px;
      gap: 10px;
      padding: 11px 18px 8px;
      font: 600 10px/1 var(--mono);
      letter-spacing: .16em;
      color: var(--dim);
      text-transform: uppercase
    }

    .brow {
      display: grid;
      grid-template-columns: 78px 92px 1fr 56px 150px;
      gap: 10px;
      padding: 9px 18px;
      align-items: center;
      border-bottom: 1px dashed #16231b;
      transition: background .2s
    }

    .brow:hover {
      background: #0e1712
    }

    .brow.next {
      box-shadow: inset 3px 0 0 var(--amber);
      background: #0d1611
    }

    .cell {
      display: flex;
      gap: 2px;
      flex-wrap: nowrap;
      font: 500 14px/1 var(--mono)
    }

    .flap {
      min-width: 1.05ch;
      text-align: center;
      padding: 4px 0;
      border-radius: 3px;
      color: #d7e4d8;
      background: linear-gradient(180deg, #16211a 48%, #101a14 52%);
      box-shadow: inset 0 0 0 1px #0a0f0c
    }

    .flap.blank {
      background: transparent;
      box-shadow: none
    }

    .flap.spin {
      opacity: .8
    }

    .brow.st-on .c-st .flap {
      color: var(--green)
    }

    .brow.st-board .c-st .flap {
      color: var(--amber);
      animation: blink 1.1s steps(2) infinite
    }

    .brow.st-del .c-st .flap {
      color: var(--red)
    }

    .brow.st-due .c-st .flap {
      color: var(--blue)
    }

    @keyframes blink {
      50% {
        opacity: .45
      }
    }

    .c-time .flap {
      color: var(--amber2)
    }

    .side {
      display: flex;
      flex-direction: column;
      gap: 18px
    }

    .card {
      background: linear-gradient(180deg, var(--panel), var(--ink2));
      border: 1px solid var(--line);
      border-radius: 12px
    }

    .clockcard {
      padding: 22px
    }

    .clockcard .top {
      display: flex;
      align-items: center;
      gap: 18px
    }

    #clockFace {
      width: 124px;
      height: 124px;
      flex: 0 0 auto
    }

    .cf-rim {
      fill: #0a100d;
      stroke: var(--line2);
      stroke-width: 2
    }

    .cf-tick {
      stroke: #3a5445;
      stroke-width: 1.4
    }

    .cf-tick.maj {
      stroke: #7d9486;
      stroke-width: 2.2
    }

    .cf-h {
      stroke: var(--text);
      stroke-width: 4;
      stroke-linecap: round
    }

    .cf-m {
      stroke: var(--text);
      stroke-width: 3;
      stroke-linecap: round
    }

    .cf-s {
      stroke: var(--amber);
      stroke-width: 1.6;
      stroke-linecap: round
    }

    .cf-cap {
      fill: var(--amber)
    }

    .cf-n {
      fill: var(--amber);
      font: 700 11px var(--mono)
    }

    .digi {
      font: 600 34px/1 var(--mono);
      letter-spacing: .04em
    }

    .date {
      font: 500 12px/1 var(--mono);
      color: var(--dim);
      margin-top: 9px;
      letter-spacing: .04em
    }

    .condcard {
      padding: 18px 20px
    }

    .condcard h4 {
      font: 600 11px/1 var(--mono);
      letter-spacing: .18em;
      color: var(--dim);
      text-transform: uppercase;
      margin-bottom: 6px
    }

    .cond {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 11px 0;
      border-bottom: 1px dashed var(--line);
      gap: 14px
    }

    .cond:last-child {
      border-bottom: 0
    }

    .cond .lab {
      font: 500 11px/1 var(--mono);
      letter-spacing: .1em;
      color: var(--mut);
      text-transform: uppercase;
      display: flex;
      align-items: center;
      gap: 8px
    }

    .cond .val {
      font: 600 14px/1 var(--mono)
    }

    .dotlive {
      width: 7px;
      height: 7px;
      border-radius: 50%;
      background: var(--green);
      box-shadow: 0 0 8px var(--green);
      animation: pulse 1.8s infinite
    }

    .kbar {
      height: 6px;
      background: #132019;
      border-radius: 4px;
      overflow: hidden;
      margin-top: 7px
    }

    .kbar i {
      display: block;
      height: 100%;
      background: linear-gradient(90deg, var(--green), var(--amber));
      transition: width .8s
    }

    .nextcard {
      padding: 18px 20px;
      border-left: 3px solid var(--amber);
      background: linear-gradient(180deg, #16241c, #101c16)
    }

    .nextcard .lab {
      font: 600 11px/1 var(--mono);
      letter-spacing: .18em;
      color: var(--amber);
      text-transform: uppercase
    }

    .nextcard .cd {
      font: 700 40px/1 var(--mono);
      letter-spacing: .02em;
      margin: 8px 0 6px
    }

    .nextcard .sub {
      font: 500 13px/1 var(--mono);
      color: var(--mut)
    }

    /* mountains + streak */
    .mountains {
      position: absolute;
      left: 0;
      right: 0;
      bottom: -1px;
      z-index: 1;
      pointer-events: none
    }

    .mountains svg {
      display: block;
      width: 100%;
      height: auto
    }

    .streak {
      position: absolute;
      left: 0;
      bottom: 64px;
      z-index: 2;
      width: 120px;
      height: 14px;
      pointer-events: none;
      opacity: 0;
      animation: streak 17s linear infinite
    }

    .streak::before {
      content: "";
      position: absolute;
      right: 0;
      top: 4px;
      width: 120px;
      height: 6px;
      border-radius: 6px;
      background: linear-gradient(90deg, transparent, rgba(255, 207, 135, .0) 30%, rgba(255, 207, 135, .85))
    }

    .streak::after {
      content: "";
      position: absolute;
      right: 0;
      top: 3px;
      width: 9px;
      height: 9px;
      border-radius: 50%;
      background: var(--amber2);
      box-shadow: 0 0 14px 4px rgba(255, 180, 84, .7)
    }

    @keyframes streak {
      0% {
        transform: translateX(-160px);
        opacity: 0
      }

      2% {
        opacity: 1
      }

      14% {
        transform: translateX(110vw);
        opacity: 1
      }

      15%,
      100% {
        opacity: 0
      }
    }

    /* ===== network ===== */
    .sec {
      padding: 96px 0
    }

    .sec-alt {
      background: rgba(10, 17, 14, .6);
      border-top: 1px solid var(--line);
      border-bottom: 1px solid var(--line)
    }

    .net-head {
      display: flex;
      flex-wrap: wrap;
      align-items: flex-end;
      justify-content: space-between;
      gap: 24px
    }

    .stats {
      display: flex;
      gap: 30px;
      flex-wrap: wrap
    }

    .stat b {
      display: block;
      font: 800 30px/1 var(--disp);
      color: var(--amber)
    }

    .stat span {
      font: 500 11px/1 var(--mono);
      letter-spacing: .14em;
      color: var(--dim);
      text-transform: uppercase
    }

    .net-grid {
      display: grid;
      grid-template-columns: minmax(0, 1.5fr) minmax(300px, .9fr);
      gap: 26px;
      margin-top: 34px;
      align-items: start
    }

    @media(max-width:920px) {
      .net-grid {
        grid-template-columns: 1fr
      }
    }

    .mapcard {
      padding: 16px 16px 12px
    }

    #netMap {
      display: block;
      width: 100%;
      height: auto
    }

    #netMap .line {
      fill: none;
      stroke-width: 3.4;
      stroke-linecap: round;
      stroke-linejoin: round;
      transition: opacity .35s, stroke-width .25s
    }

    #netMap .hit {
      fill: none;
      stroke: transparent;
      stroke-width: 20;
      cursor: pointer
    }

    #netMap.has-active .route:not(.active) .line {
      opacity: .16
    }

    #netMap .route.active .line {
      stroke-width: 5.4;
      filter: url(#glow)
    }

    #netMap .route:hover .line {
      opacity: 1
    }

    #netMap .city circle {
      fill: #0c1512;
      stroke: #9fb4a7;
      stroke-width: 1.6
    }

    #netMap .city.term circle {
      stroke: var(--amber)
    }

    #netMap .city text {
      fill: #9fb4a7;
      font: 500 11px var(--mono)
    }

    .t-glow {
      fill: none;
      stroke-width: 2.4;
      opacity: .55
    }

    .t-core {
      fill: #eef4ec
    }

    .compass text {
      fill: var(--dim);
      font: 600 11px var(--mono)
    }

    .scalebar line {
      stroke: var(--dim);
      stroke-width: 1.4
    }

    .scalebar text {
      fill: var(--dim);
      font: 500 10px var(--mono)
    }

    .legend {
      display: flex;
      flex-wrap: wrap;
      gap: 9px;
      margin-top: 14px
    }

    .leg {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 7px 13px;
      border: 1px solid var(--line2);
      border-radius: 999px;
      background: transparent;
      color: var(--mut);
      font: 500 12px/1 var(--mono);
      transition: .2s
    }

    .leg .dot {
      width: 10px;
      height: 10px;
      border-radius: 50%;
      background: var(--c)
    }

    .leg:hover {
      color: var(--text);
      border-color: var(--c)
    }

    .leg[aria-pressed="true"] {
      color: var(--text);
      border-color: var(--c);
      background: color-mix(in srgb, var(--c) 14%, transparent)
    }

    .panel {
      position: sticky;
      top: 96px
    }

    .rp-head {
      display: flex;
      gap: 14px;
      align-items: flex-start
    }

    .rp-dot {
      width: 14px;
      height: 14px;
      border-radius: 50%;
      margin-top: 6px;
      flex: 0 0 auto;
      box-shadow: 0 0 12px currentColor
    }

    .rp-kicker {
      font: 600 11px/1.3 var(--mono);
      letter-spacing: .12em;
      color: var(--dim);
      text-transform: uppercase
    }

    .rp-head h3 {
      font: 800 26px/1.05 var(--disp);
      margin-top: 6px
    }

    .rp-desc {
      color: var(--mut);
      margin: 14px 0 18px;
      font-size: .96rem
    }

    .rp-stats {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 1px;
      background: var(--line);
      border: 1px solid var(--line);
      border-radius: 10px;
      overflow: hidden
    }

    .rp-stats>div {
      background: var(--ink2);
      padding: 13px 14px
    }

    .rp-stats small {
      display: block;
      font: 500 10px/1 var(--mono);
      letter-spacing: .14em;
      color: var(--dim);
      text-transform: uppercase;
      margin-bottom: 7px
    }

    .rp-stats b {
      font: 600 16px/1 var(--mono);
      color: var(--text)
    }

    .rp-stops {
      list-style: none;
      margin: 18px 0 18px;
      position: relative;
      padding-left: 18px
    }

    .rp-stops::before {
      content: "";
      position: absolute;
      left: 4px;
      top: 6px;
      bottom: 6px;
      width: 2px;
      background: var(--line)
    }

    .rp-stops li {
      position: relative;
      padding: 7px 0;
      display: flex;
      gap: 14px;
      align-items: baseline
    }

    .rp-stops li::before {
      content: "";
      position: absolute;
      left: -18px;
      top: 12px;
      width: 9px;
      height: 9px;
      border-radius: 50%;
      background: var(--ink);
      border: 2px solid var(--line2)
    }

    .rp-stops li.end::before {
      border-color: var(--amber);
      background: var(--amber)
    }

    .rp-stops .t {
      font: 600 13px/1 var(--mono);
      color: var(--amber2);
      width: 48px;
      flex: 0 0 auto
    }

    .rp-stops .n {
      font: 500 14px/1 var(--body);
      color: var(--text)
    }

    .rp-cta {
      width: 100%;
      justify-content: center
    }

    /* ===== cabins ===== */
    .cab-grid {
      display: grid;
      grid-template-columns: 340px 1fr;
      gap: 26px;
      margin-top: 34px;
      align-items: start
    }

    @media(max-width:900px) {
      .cab-grid {
        grid-template-columns: 1fr
      }
    }

    .ctabs {
      display: flex;
      flex-direction: column;
      gap: 12px
    }

    .ctab {
      display: flex;
      align-items: center;
      gap: 14px;
      width: 100%;
      text-align: left;
      padding: 15px 16px;
      border: 1px solid var(--line);
      border-radius: 11px;
      background: var(--ink2);
      color: var(--text);
      transition: .2s;
      position: relative;
      overflow: hidden
    }

    .ctab::before {
      content: "";
      position: absolute;
      left: 0;
      top: 0;
      bottom: 0;
      width: 3px;
      background: var(--amber);
      transform: scaleY(0);
      transition: transform .25s
    }

    .ctab:hover {
      border-color: var(--line2);
      background: var(--panel)
    }

    .ctab.on {
      border-color: var(--amber);
      background: var(--panel)
    }

    .ctab.on::before {
      transform: scaleY(1)
    }

    .ctab .idx {
      font: 600 12px/1 var(--mono);
      color: var(--dim)
    }

    .ctab .meta {
      flex: 1
    }

    .ctab .meta b {
      display: block;
      font: 600 16px/1.2 var(--body)
    }

    .ctab .meta small {
      font: 500 11px/1 var(--mono);
      color: var(--dim);
      letter-spacing: .06em
    }

    .ctab .pr {
      font: 600 14px/1 var(--mono);
      color: var(--amber)
    }

    .ctab-note {
      font: 500 12px/1.5 var(--mono);
      color: var(--dim);
      padding: 14px 4px 0;
      border-top: 1px dashed var(--line);
      margin-top: 4px
    }

    .cab-stage .swapzone {
      display: grid;
      grid-template-columns: 1.05fr 1fr;
      gap: 24px;
      align-items: stretch;
      transition: opacity .18s, transform .18s
    }

    .cab-stage.fade .swapzone {
      opacity: 0;
      transform: translateY(8px)
    }

    @media(max-width:760px) {
      .cab-stage .swapzone {
        grid-template-columns: 1fr
      }
    }

    .cab-media {
      position: relative;
      min-height: 300px;
      border: 1px solid var(--line);
      border-radius: 11px;
      overflow: hidden;
      background: #0a100d
    }

    .cab-media img {
      position: absolute;
      inset: 0;
      width: 100%;
      height: 100%;
      object-fit: cover;
      transition: transform .7s
    }

    .cab-media:hover img {
      transform: scale(1.05)
    }

    .cab-tag {
      font: 600 11px/1 var(--mono);
      letter-spacing: .18em;
      color: var(--amber);
      text-transform: uppercase
    }

    .cab-body {
      display: flex;
      flex-direction: column
    }

    .cab-body h3 {
      font: 800 30px/1.05 var(--disp);
      margin: 10px 0 8px
    }

    .cab-berths {
      font: 500 12px/1 var(--mono);
      color: var(--mut);
      letter-spacing: .04em;
      margin-bottom: 14px
    }

    .cab-desc {
      color: var(--mut);
      font-size: .97rem;
      margin-bottom: 16px
    }

    .cab-amen {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      margin-bottom: 16px
    }

    .chip {
      border: 1px solid var(--line2);
      border-radius: 999px;
      padding: 5px 11px;
      font: 500 11px/1 var(--mono);
      color: var(--mut)
    }

    .cab-diag {
      background: #0a100d;
      border: 1px dashed var(--line2);
      border-radius: 9px;
      padding: 12px
    }

    .cab-diag svg {
      width: 100%;
      height: auto
    }

    .cab-foot {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 14px;
      margin-top: auto;
      padding-top: 18px;
      flex-wrap: wrap
    }

    .cab-price {
      font: 800 30px/1 var(--disp);
      color: var(--amber)
    }

    .cab-unit {
      font: 500 11px/1 var(--mono);
      color: var(--dim);
      letter-spacing: .06em
    }

    /* ===== night timeline ===== */
    .night-grid {
      display: grid;
      grid-template-columns: 5fr 7fr;
      gap: 48px;
      align-items: start
    }

    @media(max-width:880px) {
      .night-grid {
        grid-template-columns: 1fr;
        gap: 30px
      }
    }

    .night-left {
      position: sticky;
      top: 100px
    }

    .night-left .img {
      margin-top: 22px;
      border: 1px solid var(--line);
      border-radius: 11px;
      overflow: hidden
    }

    .night-left .img img {
      width: 100%;
      aspect-ratio: 16/10;
      object-fit: cover
    }

    .night-left .cap {
      font: 500 11px/1 var(--mono);
      color: var(--dim);
      margin-top: 9px;
      letter-spacing: .06em
    }

    .tl {
      position: relative;
      padding-left: 36px
    }

    .tl::before {
      content: "";
      position: absolute;
      left: 11px;
      top: 6px;
      bottom: 6px;
      width: 2px;
      background: var(--line)
    }

    .tl-fill {
      position: absolute;
      left: 11px;
      top: 6px;
      width: 2px;
      height: 0;
      background: linear-gradient(var(--amber), var(--green));
      box-shadow: 0 0 10px rgba(255, 180, 84, .4)
    }

    .tl-item {
      position: relative;
      padding: 0 0 30px
    }

    .tl-item::before {
      content: "";
      position: absolute;
      left: -30px;
      top: 4px;
      width: 12px;
      height: 12px;
      border-radius: 50%;
      background: var(--ink);
      border: 2px solid var(--line2);
      transition: .3s
    }

    .tl-item.lit::before {
      border-color: var(--amber);
      background: var(--amber);
      box-shadow: 0 0 12px rgba(255, 180, 84, .6)
    }

    .tl-item .t {
      font: 600 13px/1 var(--mono);
      color: var(--amber2);
      letter-spacing: .04em
    }

    .tl-item h4 {
      font: 600 17px/1.2 var(--body);
      margin: 6px 0 5px
    }

    .tl-item p {
      color: var(--mut);
      font-size: .94rem;
      max-width: 46ch
    }

    /* ===== journal (paper) ===== */
    .paper {
      background: var(--paper);
      color: #16211b
    }

    .paper .kicker {
      color: #9a6b00
    }

    .paper .kicker::before {
      background: #9a6b00
    }

    .paper h2 {
      color: #16211b
    }

    .paper .lead {
      color: #4a5a50
    }

    .jgrid {
      display: grid;
      grid-template-columns: 1.35fr 1fr;
      gap: 24px;
      margin-top: 34px
    }

    .jart {
      background: #f7f8ef;
      border: 1px solid #d8dcc6;
      border-radius: 12px;
      overflow: hidden;
      display: flex;
      flex-direction: column;
      transition: transform .3s, box-shadow .3s
    }

    .jart:hover {
      transform: translateY(-4px);
      box-shadow: 0 24px 44px -26px rgba(20, 30, 24, .45)
    }

    .jart.big {
      grid-row: span 2
    }

    .jart .ph {
      flex: 1 1 auto;
      min-height: 0;
      overflow: hidden
    }

    .jart .ph img {
      width: 100%;
      object-fit: cover;
      aspect-ratio: 16/10;
      transition: transform .6s
    }

    .jart.big .ph img {
      aspect-ratio: 4/3
    }

    .jart:hover .ph img {
      transform: scale(1.05)
    }

    .jart .body {
      padding: 20px;
      display: flex;
      flex-direction: column;
      flex: 0 0 auto
    }

    .jart .tagrow {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-bottom: 10px
    }

    .jtag {
      font: 600 10px/1 var(--mono);
      letter-spacing: .16em;
      text-transform: uppercase;
      color: #fff;
      background: #1f3a2c;
      padding: 5px 9px;
      border-radius: 5px
    }

    .jdate {
      font: 500 11px/1 var(--mono);
      color: #7a8a72;
      letter-spacing: .04em
    }

    .jart h3 {
      font: 800 22px/1.1 var(--disp);
      color: #16211b;
      letter-spacing: -.01em
    }

    .jart.big h3 {
      font-size: 30px
    }

    .jart p {
      color: #4a5a50;
      font-size: .94rem;
      margin: 10px 0 16px
    }

    .jlink {
      font: 600 12px/1 var(--mono);
      letter-spacing: .08em;
      color: #1f3a2c;
      display: inline-flex;
      align-items: center;
      gap: 8px
    }

    .jlink span {
      transition: transform .25s
    }

    .jart:hover .jlink span {
      transform: translateX(5px)
    }

    @media(min-width:761px) {
      .jart.big .ph img {
        height: 100%;
        aspect-ratio: auto
      }
    }

    @media(max-width:760px) {
      .jgrid {
        grid-template-columns: 1fr
      }

      .jart.big {
        grid-row: auto
      }
    }

    /* ===== booking ===== */
    .book-grid {
      display: grid;
      grid-template-columns: minmax(0, 7fr) minmax(300px, 5fr);
      gap: 26px;
      margin-top: 34px;
      align-items: start
    }

    @media(max-width:880px) {
      .book-grid {
        grid-template-columns: 1fr
      }
    }

    .formcard {
      padding: 26px
    }

    .frow {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 16px;
      margin-bottom: 16px
    }

    @media(max-width:560px) {
      .frow {
        grid-template-columns: 1fr
      }
    }

    .lab {
      display: block;
      font: 600 11px/1 var(--mono);
      letter-spacing: .12em;
      text-transform: uppercase;
      color: var(--dim);
      margin-bottom: 8px
    }

    .inp {
      width: 100%;
      background: #0c1511;
      border: 1px solid var(--line2);
      border-radius: 9px;
      padding: 12px 14px;
      color: var(--text);
      font: 500 15px/1 var(--body);
      transition: border-color .2s, box-shadow .2s
    }

    select.inp {
      appearance: none;
      background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'><path d='M1 1l5 5 5-5' stroke='%239fb4a7' stroke-width='1.6' fill='none' stroke-linecap='round'/></svg>");
      background-repeat: no-repeat;
      background-position: right 14px center;
      padding-right: 38px
    }

    .inp:focus {
      border-color: var(--amber);
      box-shadow: 0 0 0 3px rgba(255, 180, 84, .16);
      outline: none
    }

    .stepper {
      display: flex;
      border: 1px solid var(--line2);
      border-radius: 9px;
      overflow: hidden
    }

    .stepper button {
      width: 46px;
      background: var(--panel);
      color: var(--text);
      font: 600 18px/1 var(--mono);
      border: 0;
      transition: background .2s, color .2s
    }

    .stepper button:hover {
      background: var(--amber);
      color: #1d1304
    }

    .stepper .pv {
      flex: 1;
      display: flex;
      align-items: center;
      justify-content: center;
      font: 600 16px/1 var(--mono);
      background: #0c1511
    }

    .pills {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 10px
    }

    @media(max-width:560px) {
      .pills {
        grid-template-columns: 1fr 1fr
      }
    }

    .pill {
      position: relative;
      border: 1px solid var(--line2);
      border-radius: 9px;
      padding: 11px 12px;
      cursor: pointer;
      display: flex;
      flex-direction: column;
      gap: 3px;
      transition: .2s;
      background: #0c1511
    }

    .pill input {
      position: absolute;
      opacity: 0;
      inset: 0;
      cursor: pointer
    }

    .pill b {
      font: 600 14px/1.1 var(--body)
    }

    .pill small {
      font: 500 10px/1 var(--mono);
      color: var(--dim);
      letter-spacing: .04em
    }

    .pill.on {
      border-color: var(--amber);
      background: rgba(255, 180, 84, .09)
    }

    .pill.on small {
      color: var(--amber)
    }

    .extras {
      display: flex;
      flex-wrap: wrap;
      gap: 12px;
      margin: 6px 0 18px
    }

    .chk {
      display: inline-flex;
      align-items: center;
      gap: 10px;
      border: 1px solid var(--line2);
      border-radius: 9px;
      padding: 10px 14px;
      cursor: pointer;
      font: 500 13px/1 var(--body);
      transition: .2s
    }

    .chk input {
      position: absolute;
      opacity: 0
    }

    .chk .box {
      width: 18px;
      height: 18px;
      border: 1.6px solid var(--line2);
      border-radius: 5px;
      display: grid;
      place-items: center;
      transition: .2s;
      flex: 0 0 auto
    }

    .chk .box::after {
      content: "✓";
      font: 700 12px/1 var(--mono);
      color: #1d1304;
      opacity: 0;
      transform: scale(.5);
      transition: .18s
    }

    .chk.on {
      border-color: var(--amber)
    }

    .chk.on .box {
      background: var(--amber);
      border-color: var(--amber)
    }

    .chk.on .box::after {
      opacity: 1;
      transform: scale(1)
    }

    .formerr {
      color: var(--red);
      font: 500 12px/1 var(--mono);
      min-height: 16px;
      margin: 4px 0 14px
    }

    .book-submit {
      width: 100%;
      justify-content: center;
      font-size: 15px;
      padding: 15px
    }

    .sumcard {
      padding: 22px;
      position: sticky;
      top: 96px
    }

    .sumcard h4 {
      font: 600 11px/1 var(--mono);
      letter-spacing: .18em;
      color: var(--dim);
      text-transform: uppercase;
      margin-bottom: 4px
    }

    .sum-route {
      font: 700 18px/1.2 var(--disp);
      margin-bottom: 14px
    }

    .srow {
      display: flex;
      justify-content: space-between;
      gap: 14px;
      padding: 10px 0;
      border-bottom: 1px dashed var(--line);
      font: 500 13px/1.3 var(--mono);
      color: var(--mut)
    }

    .srow span:last-child {
      color: var(--text)
    }

    .sum-total {
      display: flex;
      justify-content: space-between;
      align-items: baseline;
      padding: 16px 0 6px
    }

    .sum-total .l {
      font: 600 12px/1 var(--mono);
      letter-spacing: .14em;
      color: var(--dim);
      text-transform: uppercase
    }

    .sum-total .v {
      font: 800 28px/1 var(--disp);
      color: var(--amber)
    }

    .sum-note {
      font: 500 11px/1.5 var(--mono);
      color: var(--dim);
      margin-top: 6px
    }

    .ticket {
      display: none;
      margin-top: 18px;
      background: #f4f1e2;
      color: #161d17;
      border-radius: 13px;
      padding: 22px;
      position: relative;
      overflow: hidden
    }

    .ticket.show {
      display: block;
      animation: tpop .5s cubic-bezier(.2, .8, .2, 1)
    }

    @keyframes tpop {
      from {
        transform: scale(.94) rotate(-1.5deg);
        opacity: 0
      }
    }

    .ticket .th {
      display: flex;
      justify-content: space-between;
      align-items: center;
      border-bottom: 1px dashed #b9b49a;
      padding-bottom: 12px;
      margin-bottom: 14px
    }

    .ticket .th b {
      font: 800 15px/1 var(--disp);
      letter-spacing: .04em
    }

    .ticket .th small {
      font: 600 9px/1 var(--mono);
      letter-spacing: .18em;
      color: #7a7558
    }

    .ticket .tg {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 14px 18px
    }

    .ticket .tg div small {
      display: block;
      font: 600 9px/1 var(--mono);
      letter-spacing: .14em;
      color: #7a7558;
      margin-bottom: 5px
    }

    .ticket .tg div b {
      font: 600 14px/1.2 var(--mono)
    }

    .ticket .tg .full {
      grid-column: 1/-1
    }

    .ticket .bar {
      height: 42px;
      margin-top: 16px;
      background: repeating-linear-gradient(90deg, #161d17 0 2px, transparent 2px 4px, #161d17 4px 5px, transparent 5px 9px, #161d17 9px 12px, transparent 12px 14px);
      border-radius: 3px
    }

    .ticket .code {
      font: 700 13px/1 var(--mono);
      letter-spacing: .2em;
      text-align: center;
      margin-top: 9px
    }

    .stamp {
      position: absolute;
      right: 18px;
      top: 16px;
      border: 2px solid #2f7d4f;
      color: #2f7d4f;
      padding: 4px 10px;
      border-radius: 6px;
      transform: rotate(8deg);
      font: 700 10px/1 var(--mono);
      letter-spacing: .2em;
      opacity: .9
    }

    .again {
      margin-top: 14px;
      width: 100%;
      justify-content: center
    }

    /* ===== footer ===== */
    footer {
      border-top: 1px solid var(--line);
      padding: 64px 0 30px;
      background: #0a110e
    }

    .fgrid {
      display: grid;
      grid-template-columns: 2fr 1fr 1fr 1.5fr;
      gap: 34px
    }

    @media(max-width:820px) {
      .fgrid {
        grid-template-columns: 1fr 1fr
      }
    }

    @media(max-width:520px) {
      .fgrid {
        grid-template-columns: 1fr
      }
    }

    .fbrand .blurb {
      color: var(--mut);
      font-size: .93rem;
      margin: 16px 0 16px;
      max-width: 34ch
    }

    .fstatus {
      display: inline-flex;
      align-items: center;
      gap: 9px;
      font: 500 12px/1 var(--mono);
      color: var(--mut);
      border: 1px solid var(--line);
      border-radius: 999px;
      padding: 7px 13px
    }

    .fcol h5 {
      font: 600 11px/1 var(--mono);
      letter-spacing: .16em;
      text-transform: uppercase;
      color: var(--dim);
      margin-bottom: 16px
    }

    .fcol a {
      display: block;
      font: 500 13px/1.9 var(--mono);
      color: var(--mut);
      transition: color .2s
    }

    .fcol a:hover {
      color: var(--amber)
    }

    .nlform {
      display: flex;
      gap: 8px;
      margin-top: 4px
    }

    .nlform input {
      flex: 1;
      background: #0c1511;
      border: 1px solid var(--line2);
      border-radius: 8px;
      padding: 11px 13px;
      color: var(--text);
      font: 500 14px/1 var(--body)
    }

    .nlform input:focus {
      border-color: var(--amber);
      outline: none
    }

    .nlform button {
      background: var(--amber);
      color: #1d1304;
      border: 0;
      border-radius: 8px;
      padding: 0 16px;
      font: 600 13px/1 var(--body)
    }

    .nl-err {
      color: var(--red);
      font: 500 11px/1 var(--mono);
      margin-top: 8px;
      min-height: 14px
    }

    .nl-ok {
      color: var(--green);
      font: 500 13px/1.4 var(--mono)
    }

    .fnote {
      font: 500 12px/1.5 var(--mono);
      color: var(--dim);
      margin-top: 14px
    }

    .fbar {
      display: flex;
      flex-wrap: wrap;
      justify-content: space-between;
      gap: 12px;
      border-top: 1px solid var(--line);
      margin-top: 46px;
      padding-top: 22px;
      font: 500 11px/1.5 var(--mono);
      color: var(--dim)
    }

    #toTop {
      position: fixed;
      right: 22px;
      bottom: 22px;
      z-index: 70;
      width: 46px;
      height: 46px;
      border-radius: 50%;
      border: 1px solid var(--amber);
      background: rgba(10, 17, 14, .92);
      color: var(--amber);
      display: grid;
      place-items: center;
      opacity: 0;
      pointer-events: none;
      transition: opacity .3s, background .2s, color .2s, transform .2s
    }

    #toTop.show {
      opacity: 1;
      pointer-events: auto
    }

    #toTop:hover {
      background: var(--amber);
      color: #1d1304;
      transform: translateY(-3px)
    }

    .rev {
      opacity: 0;
      transform: translateY(24px);
      transition: opacity .7s ease, transform .7s cubic-bezier(.2, .7, .2, 1);
      transition-delay: var(--d, 0s)
    }

    .rev.in {
      opacity: 1;
      transform: none
    }

    .flash {
      animation: flashb 1s
    }

    @keyframes flashb {

      0%,
      100% {
        box-shadow: 0 0 0 0 rgba(255, 180, 84, 0)
      }

      30% {
        box-shadow: 0 0 0 4px rgba(255, 180, 84, .4)
      }
    }

    .shake {
      animation: shake .4s
    }

    @keyframes shake {

      10%,
      90% {
        transform: translateX(-2px)
      }

      20%,
      80% {
        transform: translateX(4px)
      }

      30%,
      50%,
      70% {
        transform: translateX(-7px)
      }

      40%,
      60% {
        transform: translateX(7px)
      }
    }

    @media(prefers-reduced-motion:reduce) {
      * {
        animation-duration: .001ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: .001ms !important;
        scroll-behavior: auto !important
      }

      .rev {
        opacity: 1;
        transform: none
      }

      .ticker-track {
        animation: none
      }

      .streak {
        display: none
      }
    }
  </style>
</head>

<body>
  <canvas id="sky"></canvas>
  <div class="aurora"></div>

  <header id="hdr">
    <div class="wrap hdr">
      <a class="brand" href="#departures" aria-label="NORDLYS home">
        <svg width="34" height="34" viewBox="0 0 34 34" aria-hidden="true">
          <rect x="1.5" y="1.5" width="31" height="31" rx="8" fill="#132019" stroke="#2c4136" />
          <circle cx="17" cy="13" r="4.5" fill="#ffb454" />
          <path d="M8 24c3-4 6-6 9-6s6 2 9 6" stroke="#41d98d" stroke-width="2.4" fill="none" stroke-linecap="round" />
        </svg>
        <span class="wm"><b>NORDLYS</b><span>NIGHT RAIL · NO</span></span>
      </a>
      <nav class="nav" aria-label="Primary">
        <a href="#departures">Departures</a>
        <a href="#network">Network</a>
        <a href="#cabins">Cabins</a>
        <a href="#night">The Night</a>
        <a href="#journal">Journal</a>
      </nav>
      <div class="hdr-cta">
        <span class="hdr-clock" id="hdrClock">—</span>
        <a class="btn btn-amber" href="#book">Book a berth</a>
        <button class="menu-btn" id="menuBtn" aria-label="Open menu" aria-expanded="false" aria-controls="mnav">
          <svg width="20" height="20" viewBox="0 0 20 20" aria-hidden="true">
            <path d="M3 5h14M3 10h14M3 15h14" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
          </svg>
        </button>
      </div>
    </div>
    <div class="mnav" id="mnav">
      <a href="#departures">Departures</a>
      <a href="#network">Network</a>
      <a href="#cabins">Cabins</a>
      <a href="#night">The Night</a>
      <a href="#journal">Journal</a>
      <a class="btn btn-amber" href="#book">Book a berth</a>
    </div>
  </header>

  <div class="ticker" aria-label="Operational notices">
    <div class="ticker-track" id="tickTrack"></div>
  </div>

  <main>
    <!-- ===== DEPARTURES ===== -->
    <section class="hall" id="departures">
      <div class="wrap">
        <div class="hall-grid">
          <div class="rev">
            <span class="kicker">Live · Oslo Sentralstasjon</span>
            <h2>The board never sleeps.</h2>
            <p class="lead" style="margin-bottom:22px">Every night, the same ritual: a hall of amber numbers, a quiet platform, and a train that carries you north while the country goes dark.</p>
            <div class="board">
              <div class="board-top">
                <span class="lamps"><i class="lamp g"></i><i class="lamp a"></i><i class="lamp r"></i></span>
                <span class="bt">DEPARTURES <small>· HALL A</small></span>
                <span class="right">PLATFORM HALL A · OSLO S</span>
              </div>
              <div class="board-scroll">
                <div class="board-in">
                  <div class="bhead"><span>Time</span><span>Train</span><span>Destination</span><span>Spor</span><span>Status</span></div>
                  <div id="boardRows"></div>
                </div>
              </div>
            </div>
          </div>

          <aside class="side">
            <div class="card clockcard rev" style="--d:.08s">
              <div class="top">
                <svg id="clockFace" viewBox="0 0 200 200" aria-label="Oslo station clock">
                  <circle class="cf-rim" cx="100" cy="100" r="96" />
                  <g id="ticks"></g>
                  <text class="cf-n" x="100" y="34" text-anchor="middle">N</text>
                  <line id="hh" class="cf-h" x1="100" y1="100" x2="100" y2="50" />
                  <line id="mh" class="cf-m" x1="100" y1="100" x2="100" y2="28" />
                  <line id="sh" class="cf-s" x1="100" y1="100" x2="100" y2="16" />
                  <circle class="cf-cap" cx="100" cy="100" r="4" />
                </svg>
                <div>
                  <div class="digi" id="digi">--:--:--</div>
                  <div class="date" id="dateLine">—</div>
                </div>
              </div>
            </div>

            <div class="card condcard rev" style="--d:.14s">
              <h4>Line conditions tonight</h4>
              <div class="cond"><span class="lab"><i class="dotlive"></i>Aurora · KP index</span><span class="val" id="kpVal">4.1</span></div>
              <div class="kbar"><i id="kpBar" style="width:46%"></i></div>
              <div class="cond" style="margin-top:8px"><span class="lab">Myrdal plateau · 867 m</span><span class="val" id="tempVal">-3.2 °C</span></div>
              <div class="cond"><span class="lab">Sunrise · Bergen</span><span class="val">04:47</span></div>
              <div class="cond"><span class="lab">Dining car</span><span class="val">closes 23:00</span></div>
            </div>

            <div class="card nextcard rev" style="--d:.2s">
              <div class="lab">Next departure in</div>
              <div class="cd" id="cd">--:--:--</div>
              <div class="sub" id="cdSub">—</div>
            </div>
          </aside>
        </div>
      </div>

      <div class="streak" aria-hidden="true"></div>
      <div class="mountains" aria-hidden="true">
        <svg viewBox="0 0 1440 240" preserveAspectRatio="none">
          <path fill="#0d1813" d="M0 240V150l120-46 110 30 150-72 130 54 160-66 140 60 150-50 130 44 150-58V240z" />
          <path fill="#0a1310" d="M0 240V190l160-30 140 24 170-44 150 36 180-40 160 30 150-34 130 26V240z" />
          <circle cx="318" cy="118" r="2.4" fill="#ffb454">
            <animate attributeName="opacity" values="1;.3;1" dur="3s" repeatCount="indefinite" />
          </circle>
          <circle cx="905" cy="104" r="2.4" fill="#ffb454">
            <animate attributeName="opacity" values=".3;1;.3" dur="3.6s" repeatCount="indefinite" />
          </circle>
        </svg>
      </div>
    </section>

    <!-- ===== NETWORK ===== -->
    <section class="sec sec-alt" id="network">
      <div class="wrap">
        <div class="net-head rev">
          <div>
            <span class="kicker">The network</span>
            <h2>Four lines.<br>One long night.</h2>
          </div>
          <div class="stats">
            <div class="stat"><b>4</b><span>Night lines</span></div>
            <div class="stat"><b>27</b><span>Stations</span></div>
            <div class="stat"><b>3 412</b><span>Km of dark</span></div>
            <div class="stat"><b>100%</b><span>Hydro power</span></div>
          </div>
        </div>

        <div class="net-grid">
          <div class="card mapcard rev" style="--d:.08s">
            <svg id="netMap" viewBox="0 0 940 700" role="img" aria-label="Schematic night rail network map">
              <defs>
                <pattern id="grid" width="34" height="34" patternUnits="userSpaceOnUse">
                  <circle cx="1.5" cy="1.5" r="1.1" fill="#1c2b23" />
                </pattern>
                <filter id="glow" x="-60%" y="-60%" width="220%" height="220%">
                  <feGaussianBlur stdDeviation="4" result="b" />
                  <feMerge>
                    <feMergeNode in="b" />
                    <feMergeNode in="SourceGraphic" />
                  </feMerge>
                </filter>
              </defs>
              <rect width="940" height="700" fill="url(#grid)" />
              <g id="routes">
                <g class="route" data-id="nl1" data-dur="26" data-ph="0">
                  <path class="hit" d="M330 470 L250 452 L170 420" />
                  <path class="line" id="p-nl1" d="M330 470 L250 452 L170 420" stroke="var(--green)" />
                  <g class="train">
                    <circle class="t-glow" r="8" stroke="var(--green)" />
                    <circle class="t-core" r="3.4" />
                  </g>
                </g>
                <g class="route" data-id="nl2" data-dur="34" data-ph="0.4">
                  <path class="hit" d="M620 420 L700 300 L640 190 L560 150 L640 80" />
                  <path class="line" id="p-nl2" d="M620 420 L700 300 L640 190 L560 150 L640 80" stroke="var(--amber)" />
                  <g class="train">
                    <circle class="t-glow" r="8" stroke="var(--amber)" />
                    <circle class="t-core" r="3.4" />
                  </g>
                </g>
                <g class="route" data-id="nl3" data-dur="30" data-ph="0.7">
                  <path class="hit" d="M330 470 L380 330 L470 210 L560 150" />
                  <path class="line" id="p-nl3" d="M330 470 L380 330 L470 210 L560 150" stroke="var(--red)" />
                  <g class="train">
                    <circle class="t-glow" r="8" stroke="var(--red)" />
                    <circle class="t-core" r="3.4" />
                  </g>
                </g>
                <g class="route" data-id="nl4" data-dur="38" data-ph="0.2">
                  <path class="hit" d="M330 470 L430 545 L520 585 L560 640 L760 660" />
                  <path class="line" id="p-nl4" d="M330 470 L430 545 L520 585 L560 640 L760 660" stroke="var(--blue)" />
                  <g class="train">
                    <circle class="t-glow" r="8" stroke="var(--blue)" />
                    <circle class="t-core" r="3.4" />
                  </g>
                </g>
              </g>
              <g id="cities">
                <g class="city term" transform="translate(640,80)">
                  <circle r="5.5" />
                  <text x="-10" y="4" text-anchor="end">Tromsø</text>
                </g>
                <g class="city term" transform="translate(560,150)">
                  <circle r="5.5" />
                  <text x="-10" y="4" text-anchor="end">Narvik</text>
                </g>
                <g class="city" transform="translate(640,190)">
                  <circle r="4.5" />
                  <text x="10" y="4">Kiruna</text>
                </g>
                <g class="city" transform="translate(700,300)">
                  <circle r="4.5" />
                  <text x="10" y="4">Luleå</text>
                </g>
                <g class="city term" transform="translate(470,210)">
                  <circle r="5.5" />
                  <text x="-10" y="4" text-anchor="end">Bodø</text>
                </g>
                <g class="city" transform="translate(380,330)">
                  <circle r="4.5" />
                  <text x="-10" y="4" text-anchor="end">Trondheim</text>
                </g>
                <g class="city term" transform="translate(620,420)">
                  <circle r="5.5" />
                  <text x="10" y="4">Stockholm C</text>
                </g>
                <g class="city term" transform="translate(330,470)">
                  <circle r="5.5" />
                  <text x="0" y="20" text-anchor="middle">Oslo S</text>
                </g>
                <g class="city term" transform="translate(170,420)">
                  <circle r="5.5" />
                  <text x="-10" y="4" text-anchor="end">Bergen</text>
                </g>
                <g class="city" transform="translate(250,452)">
                  <circle r="4" />
                  <text x="0" y="18" text-anchor="middle">Myrdal</text>
                </g>
                <g class="city" transform="translate(430,545)">
                  <circle r="4.5" />
                  <text x="-10" y="4" text-anchor="end">Gothenburg</text>
                </g>
                <g class="city" transform="translate(520,585)">
                  <circle r="4.5" />
                  <text x="-10" y="4" text-anchor="end">Copenhagen</text>
                </g>
                <g class="city" transform="translate(560,640)">
                  <circle r="4.5" />
                  <text x="-10" y="4" text-anchor="end">Hamburg</text>
                </g>
                <g class="city term" transform="translate(760,660)">
                  <circle r="5.5" />
                  <text x="10" y="4">Vienna</text>
                </g>
              </g>
              <g class="compass" transform="translate(880,40)">
                <path d="M0 -12 L5 6 L0 1 L-5 6 Z" fill="var(--dim)" />
                <text x="0" y="22" text-anchor="middle">N</text>
              </g>
              <g class="scalebar" transform="translate(30,660)">
                <line x1="0" y1="0" x2="80" y2="0" />
                <line x1="0" y1="-4" x2="0" y2="4" />
                <line x1="80" y1="-4" x2="80" y2="4" />
                <text x="0" y="18">0</text>
                <text x="80" y="18" text-anchor="end">200 km</text>
              </g>
            </svg>
            <div class="legend" id="legend"></div>
          </div>

          <aside class="panel card rev" style="--d:.14s;padding:22px" id="routePanel"></aside>
        </div>
      </div>
    </section>

    <!-- ===== CABINS ===== -->
    <section class="sec" id="cabins">
      <div class="wrap">
        <div class="rev">
          <span class="kicker">Cabins &amp; berths</span>
          <h2>Sleep your way north.</h2>
          <p class="lead">From the honest six-berth couchette to a private cabin with its own shower — pick how much of the night you want to yourself.</p>
        </div>

        <div class="cab-grid">
          <div class="ctabs rev" style="--d:.06s">
            <div id="cabTabs"></div>
            <p class="ctab-note">Solo traveller? Couchettes are sold per berth — you'll share the cabin with fellow night owls. Sleepers are sold per cabin.</p>
          </div>

          <div class="cab-stage card rev" style="--d:.12s;padding:22px" id="cabStage">
            <div class="swapzone">
              <div class="cab-media"><img id="cabImg" alt="" width="900" height="675" loading="lazy"></div>
              <div class="cab-body">
                <p class="cab-tag" id="cabTag"></p>
                <h3 id="cabName"></h3>
                <p class="cab-berths" id="cabBerths"></p>
                <p class="cab-desc" id="cabDesc"></p>
                <div class="cab-amen" id="cabAmen"></div>
                <div class="cab-diag" id="cabDiag"></div>
                <div class="cab-foot">
                  <div><span class="cab-price" id="cabPrice"></span> <span class="cab-unit" id="cabUnit"></span></div>
                  <button class="btn btn-amber" id="cabReserve">Reserve this cabin</button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ===== THE NIGHT ===== -->
    <section class="sec sec-alt" id="night">
      <div class="wrap">
        <div class="night-grid">
          <div class="night-left rev">
            <span class="kicker">Anatomy of a night</span>
            <h2>Eight hours, told in stops.</h2>
            <p class="lead">A single crossing on the Bergensbanen — from the last light of Oslo to the gulls of Bergen.</p>
            <div class="img"><img src="https://image.qwenlm.ai/public_source/a2cd99e2-82ec-403f-a92c-37ed59af77dd/1c89e4864-9a58-494b-bad4-19808fb08705.png" alt="Sleeper train crossing a fjord bridge under the aurora" width="1400" height="900" loading="lazy"></div>
            <p class="cap">02:31 — somewhere over the Hardangervidda, 867 m.</p>
          </div>

          <div class="tl rev" id="tl" style="--d:.1s">
            <div class="tl-fill" id="tlFill"></div>
            <div class="tl-item"><span class="t">21:40</span>
              <h4>Boarding opens, Platform 3</h4>
              <p>The conductor checks your ticket at the door and points you down the corridor. The quiet car is already hushed.</p>
            </div>
            <div class="tl-item"><span class="t">22:04</span>
              <h4>Depart Oslo S</h4>
              <p>The platform slides away. City lights thin out into fields, then forest, then nothing but the rhythm of the rails.</p>
            </div>
            <div class="tl-item"><span class="t">22:47</span>
              <h4>Cabin lights dim to amber</h4>
              <p>The conductor takes the last tickets and folds the upper berths down. A reading lamp is all that's left.</p>
            </div>
            <div class="tl-item"><span class="t">23:30</span>
              <h4>Dining car closes — last aquavit</h4>
              <p>The banker lamps go out one by one. The corridor smells faintly of reindeer stew and old wood.</p>
            </div>
            <div class="tl-item"><span class="t">00:41</span>
              <h4>Ål — snow on the high plateau</h4>
              <p>The first flakes catch the window. The train begins the long climb toward the tree line.</p>
            </div>
            <div class="tl-item"><span class="t">02:31</span>
              <h4>Myrdal, 867 m — the high point</h4>
              <p>Aurora over the plateau, KP 4.2. The conductor taps your window if you asked to be woken for it.</p>
            </div>
            <div class="tl-item"><span class="t">04:58</span>
              <h4>Voss — the coffee cart</h4>
              <p>Someone is already up in the corridor. The descent begins; the fjord country returns, grey and waking.</p>
            </div>
            <div class="tl-item"><span class="t">06:51</span>
              <h4>Arrive Bergen</h4>
              <p>Fjord light, gulls, and a city that started its day while you were asleep above the snow line.</p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ===== JOURNAL ===== -->
    <section class="sec paper" id="journal">
      <div class="wrap">
        <div class="rev">
          <span class="kicker">Field notes from the line</span>
          <h2>Night Mail.</h2>
          <p class="lead">Dispatches, menus and platform notebooks from the people who keep the dark moving.</p>
        </div>

        <div class="jgrid">
          <article class="jart big rev" style="--d:.06s">
            <div class="ph"><img src="https://image.qwenlm.ai/public_source/a2cd99e2-82ec-403f-a92c-37ed59af77dd/1871fc9b1-adac-4ce0-8595-3c4556647a9c.png" alt="Aurora seen through a rain-speckled train window at dawn" width="1200" height="800" loading="lazy"></div>
            <div class="body">
              <div class="tagrow"><span class="jtag">Dispatch</span><span class="jdate">18 Jul 2026</span></div>
              <h3>Aurora over the Hardangervidda, from seat 41</h3>
              <p>We asked the conductor to wake us at Myrdal and he did, exactly, with a knock and a cup of black coffee. The whole corridor was at the windows. The green moved slow, like smoke under ice, and nobody said a word for eleven minutes.</p>
              <a class="jlink" href="#network">Read the dispatch <span>→</span></a>
            </div>
          </article>

          <article class="jart rev" style="--d:.12s">
            <div class="ph"><img src="https://image.qwenlm.ai/public_source/a2cd99e2-82ec-403f-a92c-37ed59af77dd/190a08b6f-5004-48ff-93de-57c380457b16.png" alt="Night train dining car with a green banker lamp" width="1200" height="800" loading="lazy"></div>
            <div class="body">
              <div class="tagrow"><span class="jtag">Menu</span><span class="jdate">12 Jul 2026</span></div>
              <h3>What the cook serves after midnight</h3>
              <p>A short menu, a long night. Reindeer stew, lingonberries, and the last bottle of aquavit kept behind the counter for the regulars.</p>
              <a class="jlink" href="#book">Read the menu <span>→</span></a>
            </div>
          </article>

          <article class="jart rev" style="--d:.18s">
            <div class="ph"><img src="https://image.qwenlm.ai/public_source/a2cd99e2-82ec-403f-a92c-37ed59af77dd/195f8f728-bcda-4b63-8bbf-e20c527258b7.png" alt="Snowy platform at night with a luggage cart and lantern" width="1200" height="800" loading="lazy"></div>
            <div class="body">
              <div class="tagrow"><span class="jtag">Station</span><span class="jdate">03 Jul 2026</span></div>
              <h3>Platform 3, 21:40 — a porter's notebook</h3>
              <p>Thirty-one years of red tail lights. A small ledger of the things left behind, the things found, and the trains that ran on time.</p>
              <a class="jlink" href="#night">Read the notebook <span>→</span></a>
            </div>
          </article>
        </div>
      </div>
    </section>

    <!-- ===== BOOKING ===== -->
    <section class="sec sec-alt" id="book">
      <div class="wrap">
        <div class="rev">
          <span class="kicker">Reservations</span>
          <h2>Reserve a berth before the lights go down.</h2>
          <p class="lead">No booking fees. Changes free until 18:00 the day before departure.</p>
        </div>

        <div class="book-grid">
          <form class="formcard card rev" id="bookForm" style="--d:.06s" novalidate>
            <div class="frow">
              <div>
                <label class="lab" for="from">From</label>
                <select class="inp" id="from"></select>
              </div>
              <div>
                <label class="lab" for="to">To</label>
                <select class="inp" id="to"></select>
              </div>
            </div>
            <div class="frow">
              <div>
                <label class="lab" for="date">Night of</label>
                <input class="inp" type="date" id="date">
              </div>
              <div>
                <label class="lab">Travellers</label>
                <div class="stepper">
                  <button type="button" id="paxMinus" aria-label="Fewer travellers">−</button><span class="pv" id="paxVal">2</span>
                  <button type="button" id="paxPlus" aria-label="More travellers">+</button>
                </div>
              </div>
            </div>
            <label class="lab">Cabin class</label>
            <div class="pills" id="pills">
              <label class="pill on">
                <input type="radio" name="cls" value="c6" checked><b>Couchette 6</b><small>per berth</small>
              </label>
              <label class="pill">
                <input type="radio" name="cls" value="c4"><b>Couchette 4</b><small>per berth</small>
              </label>
              <label class="pill">
                <input type="radio" name="cls" value="tw"><b>Sleeper Twin</b><small>per cabin</small>
              </label>
              <label class="pill">
                <input type="radio" name="cls" value="dl"><b>Deluxe</b><small>per cabin</small>
              </label>
            </div>
            <label class="lab" style="margin-top:18px">Add to your night</label>
            <div class="extras">
              <label class="chk">
                <input type="checkbox" id="chkBrek"><span class="box"></span>Breakfast basket · +89 kr / person
              </label>
              <label class="chk">
                <input type="checkbox" id="chkBike"><span class="box"></span>Bicycle space · +149 kr
              </label>
            </div>
            <div class="formerr" id="formErr" role="alert"></div>
            <button class="btn btn-amber book-submit" type="submit">Confirm reservation →</button>
          </form>

          <aside class="sumcard card rev" style="--d:.12s">
            <div id="estimateBox">
              <h4>Fare estimate</h4>
              <div class="sum-route" id="estRoute">Oslo → Bergen</div>
              <div id="sumRows"></div>
              <div class="sum-total"><span class="l">Total</span><span class="v" id="sumTotal">—</span></div>
              <p class="sum-note">Estimate in NOK. Final price confirmed at payment. Sleeper fares are per cabin; couchettes per berth.</p>
            </div>

            <div class="ticket" id="ticketBox">
              <div class="stamp">CONFIRMED</div>
              <div class="th"><b>NORDLYS NIGHT RAIL</b><small>BOARDING PASS</small></div>
              <div class="tg">
                <div class="full"><small>Route</small><b id="tRoute"></b></div>
                <div><small>Night of</small><b id="tDate"></b></div>
                <div><small>Cabin</small><b id="tClass"></b></div>
                <div><small>Travellers</small><b id="tPax"></b></div>
                <div><small>Total paid</small><b id="tTotal"></b></div>
              </div>
              <div class="bar"></div>
              <div class="code" id="tCode"></div>
              <button class="btn btn-ghost again" id="again" type="button">Make another booking</button>
            </div>
          </aside>
        </div>
      </div>
    </section>
  </main>

  <footer>
    <div class="wrap">
      <div class="fgrid">
        <div class="fbrand">
          <a class="brand" href="#departures">
            <svg width="34" height="34" viewBox="0 0 34 34" aria-hidden="true">
              <rect x="1.5" y="1.5" width="31" height="31" rx="8" fill="#132019" stroke="#2c4136" />
              <circle cx="17" cy="13" r="4.5" fill="#ffb454" />
              <path d="M8 24c3-4 6-6 9-6s6 2 9 6" stroke="#41d98d" stroke-width="2.4" fill="none" stroke-linecap="round" />
            </svg>
            <span class="wm"><b>NORDLYS</b><span>NIGHT RAIL · NO</span></span>
          </a>
          <p class="blurb">The sleeper rail network of the North. Carbon-free since 2024, running on hydro power and good linen.</p>
          <span class="fstatus"><i class="dotlive"></i>All lines running · updated live</span>
        </div>
        <div class="fcol">
          <h5>Network</h5>
          <a href="#network">Bergensbanen</a><a href="#network">Arctic Line</a><a href="#network">Dovre–Nordland</a><a href="#network">Continental Night</a><a href="#departures">Live timetable</a>
        </div>
        <div class="fcol">
          <h5>Company</h5>
          <a href="#journal">Journal</a><a href="#cabins">Cabins</a><a href="#night">On board</a><a href="#book">Reservations</a><a href="#book">Contact</a>
        </div>
        <div class="fcol">
          <h5>Night mail</h5>
          <p class="fnote" style="margin-top:0;margin-bottom:12px">One dispatch a week — aurora forecasts, new routes, and the cook's specials.</p>
          <form class="nlform" id="nlForm" novalidate>
            <input type="email" id="nlInput" placeholder="you@fjord.no" aria-label="Email address">
            <button type="submit">Join</button>
          </form>
          <div class="nl-err" id="nlErr"></div>
        </div>
      </div>
      <div class="fbar">
        <span>© 2026 Nordlys Night Rail AS · Oslo Sentralstasjon, Spor 1</span>
        <span>Timetables &amp; fares shown are illustrative — a fictional network, lovingly timetabled.</span>
      </div>
    </div>
  </footer>

  <button id="toTop" aria-label="Back to top">
    <svg width="18" height="18" viewBox="0 0 18 18" aria-hidden="true">
      <path d="M9 14V4M4 9l5-5 5 5" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round" />
    </svg>
  </button>

  <script>
    'use strict';
    const $ = s => document.querySelector(s),
      $$ = s => [...document.querySelectorAll(s)];
    const pad2 = n => String(n).padStart(2, '0');
    const fmtKr = n => n.toLocaleString('nb-NO') + ' kr';
    const RM = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    const hdr = $('#hdr'),
      tickerEl = $('.ticker'),
      toTop = $('#toTop');

    function headerOffset() {
      return hdr.offsetHeight + (tickerEl ? tickerEl.offsetHeight : 0) + 6;
    }

    function scrollToId(id) {
      const el = document.getElementById(id);
      if (!el) return;
      const y = el.getBoundingClientRect().top + window.scrollY - headerOffset() + 1;
      window.scrollTo({
        top: Math.max(0, y),
        behavior: RM ? 'auto' : 'smooth'
      });
    }

    /* ---------- mobile menu ---------- */
    const menuBtn = $('#menuBtn'),
      mnav = $('#mnav');

    function closeMenu() {
      mnav.classList.remove('open');
      menuBtn.setAttribute('aria-expanded', 'false');
    }
    menuBtn.addEventListener('click', () => {
      const open = mnav.classList.toggle('open');
      menuBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
    });

    /* ---------- in-page anchor routing (no hash navigation) ---------- */
    document.addEventListener('click', e => {
      const a = e.target.closest('a[href^="#"]');
      if (!a) return;
      const id = a.getAttribute('href').slice(1);
      if (!id) return;
      if (!document.getElementById(id)) return;
      e.preventDefault();
      closeMenu();
      scrollToId(id);
      try {
        history.replaceState(null, '', '#' + id);
      } catch (_) {}
    });

    /* ---------- ticker ---------- */
    const NOTICE = [
      'NJ 104 to Bergen — boarding Platform 3, quiet car fully booked',
      'Aurora forecast KP 4.2 over Hardangervidda — window seats recommended',
      'Dining car open until 23:00 — last call for reindeer stew',
      'Arctic Line NL 218 departs Stockholm 21:35 — bike car has 4 spaces left',
      'Quiet hours 22:30–07:00 on all services',
      'Snow report Myrdal 867 m: -3 °C, clear skies'
    ];
    $('#tickTrack').innerHTML = [0, 1].map(() => '<span>' + NOTICE.join('  ◆  ') + '  ◆  </span>').join('');

    /* ---------- starfield ---------- */
    const cv = $('#sky'),
      cx = cv.getContext('2d');
    let W, H, stars = [],
      shoot = null,
      nextShoot = performance.now() + 6000;

    function sizeCv() {
      const dpr = Math.min(devicePixelRatio || 1, 2);
      W = cv.width = innerWidth * dpr;
      H = cv.height = innerHeight * dpr;
      cv.style.width = innerWidth + 'px';
      cv.style.height = innerHeight + 'px';
    }

    function initStars() {
      stars = Array.from({
        length: 150
      }, () => ({
        x: Math.random() * W,
        y: Math.random() * H,
        r: (Math.random() * .9 + .4) * (Math.min(devicePixelRatio || 1, 2)),
        p: Math.random() * 6.28,
        s: .4 + Math.random() * 1.2
      }));
    }

    function drawSky(t) {
      cx.clearRect(0, 0, W, H);
      const off = (scrollY * (Math.min(devicePixelRatio || 1, 2)) * .06) % H;
      for (const st of stars) {
        const a = .22 + .55 * (.5 + .5 * Math.sin(t / 1000 * st.s + st.p));
        cx.globalAlpha = a;
        cx.fillStyle = '#dfe9df';
        const y = (st.y - off + H) % H;
        cx.beginPath();
        cx.arc(st.x, y, st.r, 0, 7);
        cx.fill();
      }
      cx.globalAlpha = 1;
      if (!shoot && t > nextShoot) {
        shoot = {
          x: Math.random() * W * .6 + W * .2,
          y: Math.random() * H * .3,
          vx: (3 + Math.random() * 2),
          vy: (1.2 + Math.random()),
          life: 1
        };
      }
      if (shoot) {
        shoot.x += shoot.vx;
        shoot.y += shoot.vy;
        shoot.life -= .02;
        const g = cx.createLinearGradient(shoot.x, shoot.y, shoot.x - shoot.vx * 10, shoot.y - shoot.vy * 10);
        g.addColorStop(0, 'rgba(255,214,150,' + (.8 * Math.max(0, shoot.life)) + ')');
        g.addColorStop(1, 'rgba(255,214,150,0)');
        cx.strokeStyle = g;
        cx.lineWidth = 1.6;
        cx.beginPath();
        cx.moveTo(shoot.x, shoot.y);
        cx.lineTo(shoot.x - shoot.vx * 10, shoot.y - shoot.vy * 10);
        cx.stroke();
        if (shoot.life <= 0 || shoot.x > W + 60) {
          shoot = null;
          nextShoot = t + 7000 + Math.random() * 9000;
        }
      }
      requestAnimationFrame(drawSky);
    }

    function drawStatic() {
      cx.clearRect(0, 0, W, H);
      for (const st of stars) {
        cx.globalAlpha = .5;
        cx.fillStyle = '#dfe9df';
        cx.beginPath();
        cx.arc(st.x, st.y, st.r, 0, 7);
        cx.fill();
      }
      cx.globalAlpha = 1;
    }
    sizeCv();
    initStars();
    addEventListener('resize', () => {
      sizeCv();
      initStars();
      if (RM) drawStatic();
    });
    if (RM) drawStatic();
    else requestAnimationFrame(drawSky);

    /* ---------- analog clock ticks ---------- */
    const ticks = $('#ticks');
    for (let i = 0; i < 60; i++) {
      const a = (i * 6 - 90) * Math.PI / 180;
      const maj = i % 5 === 0;
      const r1 = maj ? 80 : 86,
        r2 = 92;
      const l = document.createElementNS('http://www.w3.org/2000/svg', 'line');
      l.setAttribute('class', 'cf-tick' + (maj ? ' maj' : ''));
      l.setAttribute('x1', 100 + Math.cos(a) * r1);
      l.setAttribute('y1', 100 + Math.sin(a) * r1);
      l.setAttribute('x2', 100 + Math.cos(a) * r2);
      l.setAttribute('y2', 100 + Math.sin(a) * r2);
      ticks.appendChild(l);
    }

    function hand(el, deg, len) {
      const a = (deg - 90) * Math.PI / 180;
      el.setAttribute('x2', 100 + Math.cos(a) * len);
      el.setAttribute('y2', 100 + Math.sin(a) * len);
    }

    function osloParts() {
      const f = new Intl.DateTimeFormat('en-GB', {
        timeZone: 'Europe/Oslo',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: false
      }).formatToParts(new Date());
      const o = {};
      f.forEach(p => o[p.type] = p.value);
      return o;
    }
    const dateLine = $('#dateLine');
    dateLine.textContent = new Intl.DateTimeFormat('en-GB', {
      timeZone: 'Europe/Oslo',
      weekday: 'long',
      day: 'numeric',
      month: 'long',
      year: 'numeric'
    }).format(new Date());

    /* ---------- departure board ---------- */
    const DESTS = [
      ['Bergen', 'NL 104'],
      ['Trondheim', 'NL 214'],
      ['Stockholm C', 'NL 320'],
      ['Narvik', 'NL 218'],
      ['Vienna Hbf', 'NL 452'],
      ['Bodø', 'NL 226'],
      ['Gothenburg', 'NL 336'],
      ['Kiruna', 'NL 222']
    ];
    const WID = {
      time: 5,
      train: 7,
      dest: 14,
      plat: 2,
      status: 12
    };
    const FLAP = 'ABCDEFGHIJKLMNOPQRSTUVWXYZÆØÅ0123456789:+· ';
    const SPIN = 'ABCDEFGHIJKLMNOPQRSTUVWXYZÆØÅ0123456789:+·';
    const boardEl = $('#boardRows');

    function slug(s) {
      return s.startsWith('ON') ? 'on' : s.startsWith('BOARD') ? 'board' : s.startsWith('DELAY') ? 'del' : 'due';
    }

    function fmtHM(d) {
      return pad2(d.getHours()) + ':' + pad2(d.getMinutes());
    }

    function makeCell(cls, len) {
      const d = document.createElement('div');
      d.className = 'cell ' + cls;
      for (let i = 0; i < len; i++) {
        const s = document.createElement('span');
        s.className = 'flap blank';
        s.textContent = ' ';
        d.appendChild(s);
      }
      return d;
    }

    function buildRow() {
      const r = document.createElement('div');
      r.className = 'brow';
      r.appendChild(makeCell('c-time', WID.time));
      r.appendChild(makeCell('c-train', WID.train));
      r.appendChild(makeCell('c-dest', WID.dest));
      r.appendChild(makeCell('c-plat', WID.plat));
      r.appendChild(makeCell('c-st', WID.status));
      return r;
    }
    const rowEls = Array.from({
      length: 7
    }, () => {
      const r = buildRow();
      boardEl.appendChild(r);
      return r;
    });

    function flap(cell, text) {
      const spans = [...cell.children];
      if (cell._iv) clearInterval(cell._iv);
      const settle = text.split('').map((c, i) => 4 + i * 2 + Math.floor(Math.random() * 3));
      let frame = 0;
      cell._iv = setInterval(() => {
        frame++;
        let done = true;
        spans.forEach((s, i) => {
          if (frame >= settle[i]) {
            const ch = text[i];
            if (s.textContent !== ch) {
              s.textContent = ch;
            }
            s.classList.toggle('blank', ch === ' ');
            s.classList.remove('spin');
          } else {
            done = false;
            s.textContent = SPIN[Math.floor(Math.random() * SPIN.length)];
            s.classList.remove('blank');
            s.classList.add('spin');
          }
        });
        if (done) {
          clearInterval(cell._iv);
          cell._iv = null;
        }
      }, 32);
    }

    function setCell(cell, text, animate) {
      const t = text.padEnd(cell.children.length, ' ').slice(0, cell.children.length);
      if (animate && !RM) flap(cell, t);
      else [...cell.children].forEach((s, i) => {
        s.textContent = t[i];
        s.classList.toggle('blank', t[i] === ' ');
        s.classList.remove('spin');
      });
    }
    const now0 = new Date();
    const OFF = [7, 18, 29, 43, 56, 70, 84];
    let boardRows = OFF.map((m, i) => {
      const d = DESTS[i % DESTS.length];
      return {
        time: new Date(now0.getTime() + m * 60000),
        train: d[1],
        dest: d[0],
        plat: String((i * 3 + 5) % 9 + 1),
        status: i === 0 ? 'BOARDING' : (i === 3 ? 'DELAYED +5' : 'ON TIME')
      };
    });
    let destCursor = 7;

    function renderBoard(anim) {
      const set = anim || new Set([0, 1, 2, 3, 4, 5, 6]);
      boardRows.forEach((row, i) => {
        const r = rowEls[i];
        const cells = [r.children[0], r.children[1], r.children[2], r.children[3], r.children[4]];
        setCell(cells[0], fmtHM(row.time), set.has(i));
        setCell(cells[1], row.train, set.has(i));
        setCell(cells[2], row.dest, set.has(i));
        setCell(cells[3], row.plat, set.has(i));
        setCell(cells[4], row.status, set.has(i));
        r.className = 'brow' + (i === 0 ? ' next' : '') + ' st-' + slug(row.status);
      });
    }
    renderBoard();

    function mutate() {
      if (Math.random() < 0.45) {
        const i = 1 + Math.floor(Math.random() * 6);
        const opts = ['ON TIME', 'ON TIME', 'BOARDING', 'DELAYED +5', 'DUE'];
        const s = opts[Math.floor(Math.random() * opts.length)];
        if (boardRows[i].status !== s) {
          boardRows[i].status = s;
          renderBoard(new Set([i]));
        }
      } else {
        boardRows.shift();
        const last = boardRows[boardRows.length - 1].time;
        const t = new Date(last.getTime() + (11 + Math.floor(Math.random() * 6)) * 60000);
        const d = DESTS[destCursor % DESTS.length];
        destCursor++;
        boardRows.push({
          time: t,
          train: d[1],
          dest: d[0],
          plat: String(1 + Math.floor(Math.random() * 9)),
          status: 'ON TIME'
        });
        renderBoard();
      }
    }
    setInterval(mutate, 6500);

    /* ---------- conditions drift ---------- */
    const kpVal = $('#kpVal'),
      kpBar = $('#kpBar'),
      tempVal = $('#tempVal');
    setInterval(() => {
      const t = Date.now() / 60000;
      const kp = (4.1 + Math.sin(t) * 0.5 + Math.random() * 0.2);
      kpVal.textContent = kp.toFixed(1);
      kpBar.style.width = Math.min(100, (kp / 9) * 100) + '%';
      tempVal.textContent = (-3.2 + Math.sin(t * 1.3) * 0.6).toFixed(1) + ' °C';
    }, 4000);

    /* ---------- master tick ---------- */
    const digi = $('#digi'),
      hh = $('#hh'),
      mh = $('#mh'),
      sh = $('#sh'),
      cd = $('#cd'),
      cdSub = $('#cdSub'),
      hdrClock = $('#hdrClock');

    function tick() {
      const p = osloParts();
      const h = +p.hour,
        m = +p.minute,
        s = +p.second;
      digi.textContent = p.hour + ':' + p.minute + ':' + p.second;
      hdrClock.textContent = 'OSL ' + p.hour + ':' + p.minute;
      hand(hh, ((h % 12) + m / 60) * 30, 50);
      hand(mh, (m + s / 60) * 6, 72);
      hand(sh, s * 6, 84);
      let diff = boardRows[0].time - Date.now();
      if (diff < 0) {
        mutate();
        diff = boardRows[0].time - Date.now();
      }
      const d = Math.max(0, diff);
      cd.textContent = pad2(Math.floor(d / 3.6e6)) + ':' + pad2(Math.floor(d / 6e4) % 60) + ':' + pad2(Math.floor(d / 1e3) % 60);
      cdSub.textContent = boardRows[0].train + ' · ' + boardRows[0].dest + ' · Platform ' + boardRows[0].plat;
    }
    setInterval(tick, 250);
    tick();

    /* ---------- network map ---------- */
    const ROUTES = [{
        id: 'nl1',
        num: 'NL 1',
        name: 'Bergensbanen',
        hex: '#41d98d',
        tag: 'Oslo ↔ Bergen · via Myrdal',
        desc: 'Cross the Hardangervidda plateau while you sleep — wake to fjords and the seven mountains of Bergen.',
        km: '496 km',
        dur: '8 h 47 m',
        dep: '22:04',
        price: 'from 449 kr',
        from: 'Oslo',
        to: 'Bergen',
        stops: [
          ['22:04', 'Oslo S'],
          ['22:58', 'Hønefoss'],
          ['00:41', 'Ål'],
          ['01:22', 'Geilo'],
          ['02:31', 'Myrdal'],
          ['04:02', 'Voss'],
          ['06:51', 'Bergen']
        ]
      },
      {
        id: 'nl2',
        num: 'NL 2',
        name: 'Arctic Line',
        hex: '#ffb454',
        tag: 'Stockholm ↔ Narvik · via Kiruna',
        desc: "Europe's last great wilderness railway. Reindeer country, iron ore, and aurora over Kiruna.",
        km: '1 238 km',
        dur: '10 h 05 m',
        dep: '21:35',
        price: 'from 629 kr',
        from: 'Stockholm',
        to: 'Narvik',
        stops: [
          ['21:35', 'Stockholm C'],
          ['23:41', 'Uppsala C'],
          ['02:12', 'Östersund'],
          ['05:04', 'Kiruna'],
          ['07:40', 'Narvik']
        ]
      },
      {
        id: 'nl3',
        num: 'NL 3',
        name: 'Dovre–Nordland',
        hex: '#ff6157',
        tag: 'Oslo ↔ Trondheim ↔ Bodø',
        desc: 'The full north–south crossing — sleep past Trondheim, breakfast through the Saltfjellet.',
        km: '1 196 km',
        dur: '17 h 50 m',
        dep: '22:40',
        price: 'from 579 kr',
        from: 'Oslo',
        to: 'Bodø',
        stops: [
          ['22:40', 'Oslo S'],
          ['00:58', 'Hamar'],
          ['05:05', 'Trondheim S'],
          ['09:41', 'Mo i Rana'],
          ['13:22', 'Fauske'],
          ['16:30', 'Bodø']
        ]
      },
      {
        id: 'nl4',
        num: 'NL 4',
        name: 'Continental Night',
        hex: '#6fb7ff',
        tag: 'Oslo ↔ Vienna · via Copenhagen & Hamburg',
        desc: 'One ticket from the fjords to the Danube. Breakfast is served somewhere past Hamburg.',
        km: '1 730 km',
        dur: '21 h 15 m',
        dep: '19:50',
        price: 'from 899 kr',
        from: 'Oslo',
        to: 'Vienna',
        stops: [
          ['19:50', 'Oslo S'],
          ['23:58', 'Gothenburg C'],
          ['03:41', 'Copenhagen H'],
          ['08:20', 'Hamburg Hbf'],
          ['17:05', 'Vienna Hbf']
        ]
      }
    ];
    const netMap = $('#netMap'),
      panel = $('#routePanel'),
      legend = $('#legend');
    legend.innerHTML = ROUTES.map(r => '<button class="leg" data-id="' + r.id + '" style="--c:' + r.hex + '" aria-pressed="false"><span class="dot"></span>' + r.name + '</button>').join('');

    function renderPanel(r) {
      panel.innerHTML = '<div class="rp-head"><span class="rp-dot" style="background:' + r.hex + ';color:' + r.hex + '"></span><div><p class="rp-kicker">' + r.num + ' · ' + r.tag + '</p><h3>' + r.name + '</h3></div></div>' +
        '<p class="rp-desc">' + r.desc + '</p>' +
        '<div class="rp-stats"><div><small>Distance</small><b>' + r.km + '</b></div><div><small>Duration</small><b>' + r.dur + '</b></div><div><small>Departs</small><b>' + r.dep + '</b></div><div><small>Fare</small><b>' + r.price + '</b></div></div>' +
        '<ol class="rp-stops">' + r.stops.map((s, i) => '<li class="' + (i === 0 || i === r.stops.length - 1 ? 'end' : '') + '"><span class="t">' + s[0] + '</span><span class="n">' + s[1] + '</span></li>').join('') + '</ol>' +
        '<button class="btn btn-amber rp-cta" data-from="' + r.from + '" data-to="' + r.to + '">Reserve this line →</button>';
      panel.querySelector('.rp-cta').addEventListener('click', e => {
        fromSel.value = e.target.dataset.from;
        toSel.value = e.target.dataset.to;
        updateSummary();
        scrollToId('book');
        flash($('#bookForm'));
      });
    }

    function selectRoute(id) {
      netMap.classList.add('has-active');
      $$('#routes .route').forEach(g => g.classList.toggle('active', g.dataset.id === id));
      $$('.leg').forEach(b => b.setAttribute('aria-pressed', b.dataset.id === id ? 'true' : 'false'));
      const r = ROUTES.find(x => x.id === id);
      renderPanel(r);
    }
    $$('#routes .route .hit').forEach(h => h.addEventListener('click', () => selectRoute(h.parentElement.dataset.id)));
    $$('.leg').forEach(b => b.addEventListener('click', () => selectRoute(b.dataset.id)));
    selectRoute('nl1');

    const dots = {};
    $$('#routes .route').forEach(g => {
      const p = g.querySelector('.line');
      dots[g.dataset.id] = {
        p,
        tr: g.querySelector('.train'),
        len: p.getTotalLength(),
        dur: +g.dataset.dur,
        ph: +g.dataset.ph
      };
    });

    function dotsLoop(t) {
      for (const k in dots) {
        const o = dots[k];
        const pr = ((t / 1000) / o.dur + o.ph) % 1;
        const pt = o.p.getPointAtLength(pr * o.len);
        o.tr.setAttribute('transform', 'translate(' + pt.x + ',' + pt.y + ')');
      }
      requestAnimationFrame(dotsLoop);
    }
    if (RM) {
      for (const k in dots) {
        const o = dots[k];
        const pt = o.p.getPointAtLength(o.ph * o.len);
        o.tr.setAttribute('transform', 'translate(' + pt.x + ',' + pt.y + ')');
      }
    } else requestAnimationFrame(dotsLoop);

    /* ---------- cabins ---------- */
    const IMG = {
      c6: 'https://image.qwenlm.ai/public_source/a2cd99e2-82ec-403f-a92c-37ed59af77dd/1533903f6-e302-481a-b2ed-8170494887c5.png',
      c4: 'https://image.qwenlm.ai/public_source/a2cd99e2-82ec-403f-a92c-37ed59af77dd/1dbec4355-d385-49c5-88f8-12640fc3b984.png',
      tw: 'https://image.qwenlm.ai/public_source/a2cd99e2-82ec-403f-a92c-37ed59af77dd/1c806a6eb-e578-4918-aacb-f02ffef271e3.png',
      dl: 'https://image.qwenlm.ai/public_source/a2cd99e2-82ec-403f-a92c-37ed59af77dd/113dcdcd7-bd73-4574-8d1e-bc94f2281704.png'
    };
    const DIAG = {
      c6: '<svg viewBox="0 0 240 100" aria-hidden="true"><g fill="none" stroke="#3f5a4a" stroke-width="2"><rect x="10" y="10" width="150" height="24" rx="5"/><rect x="10" y="40" width="150" height="24" rx="5"/><rect x="10" y="70" width="150" height="24" rx="5"/><line x1="178" y1="8" x2="178" y2="96"/><line x1="170" y1="26" x2="186" y2="26"/><line x1="170" y1="52" x2="186" y2="52"/><line x1="170" y1="78" x2="186" y2="78"/></g><g fill="#24382d"><rect x="16" y="15" width="30" height="14" rx="4"/><rect x="16" y="45" width="30" height="14" rx="4"/><rect x="16" y="75" width="30" height="14" rx="4"/></g><circle cx="214" cy="22" r="6" fill="#ffb454"/><circle cx="214" cy="22" r="11" fill="none" stroke="#ffb454" opacity=".35"/></svg>',
      c4: '<svg viewBox="0 0 240 100" aria-hidden="true"><g fill="none" stroke="#3f5a4a" stroke-width="2"><rect x="10" y="12" width="100" height="32" rx="5"/><rect x="10" y="56" width="100" height="32" rx="5"/><rect x="130" y="12" width="100" height="32" rx="5"/><rect x="130" y="56" width="100" height="32" rx="5"/></g><g fill="#24382d"><rect x="16" y="18" width="28" height="20" rx="4"/><rect x="16" y="62" width="28" height="20" rx="4"/><rect x="136" y="18" width="28" height="20" rx="4"/><rect x="136" y="62" width="28" height="20" rx="4"/></g><circle cx="120" cy="50" r="5" fill="#ffb454"/></svg>',
      tw: '<svg viewBox="0 0 240 100" aria-hidden="true"><g fill="none" stroke="#3f5a4a" stroke-width="2"><rect x="10" y="12" width="86" height="76" rx="6"/><rect x="108" y="12" width="86" height="76" rx="6"/><circle cx="216" cy="30" r="13"/></g><g fill="#24382d"><rect x="18" y="20" width="34" height="20" rx="4"/><rect x="116" y="20" width="34" height="20" rx="4"/></g><line x1="216" y1="43" x2="216" y2="60" stroke="#3f5a4a" stroke-width="2"/><circle cx="216" cy="30" r="3" fill="#6fb7ff"/></svg>',
      dl: '<svg viewBox="0 0 240 100" aria-hidden="true"><g fill="none" stroke="#3f5a4a" stroke-width="2"><rect x="10" y="28" width="140" height="56" rx="7"/><rect x="168" y="12" width="60" height="76" rx="6"/></g><g fill="#24382d"><rect x="20" y="36" width="40" height="20" rx="4"/><rect x="66" y="36" width="40" height="20" rx="4"/></g><circle cx="198" cy="32" r="7" fill="none" stroke="#6fb7ff" stroke-width="2"/><line x1="198" y1="39" x2="198" y2="58" stroke="#6fb7ff" stroke-width="2"/><circle cx="120" cy="14" r="5" fill="#ffb454"/></svg>'
    };
    const CABINS = [{
        id: 'c6',
        name: 'Couchette 6',
        tag: 'THE CLASSIC BUNK',
        unit: 'per berth',
        price: '449',
        berths: '6 berths · washroom at end of car',
        desc: 'Three tiers of honest sleep. Curtains, reading lights, fresh linen — and the shared coffee pot that appears at 06:00 sharp.',
        amen: ['Fresh linen', 'Reading light', 'Privacy curtain', 'Locker', '06:00 coffee pot']
      },
      {
        id: 'c4',
        name: 'Couchette 4',
        tag: 'ROOM TO TURN',
        unit: 'per berth',
        price: '589',
        berths: '4 berths · two tiers each side',
        desc: 'The same calm, with a bunk to yourself on top and space below. Families and card players favour the lower two.',
        amen: ['Fresh linen', 'Reading light', 'Privacy curtain', 'Locker', 'Window table']
      },
      {
        id: 'tw',
        name: 'Sleeper Twin',
        tag: 'A REAL BED',
        unit: 'per cabin',
        price: '1 180',
        berths: '2 beds · sink in cabin',
        desc: 'Two proper single beds made by hand, a sink with hot water, and a door that locks. The conductor wakes you with coffee at your requested stop.',
        amen: ['Made beds', 'Private sink', 'Wake-up coffee', 'Towels', 'Door locks']
      },
      {
        id: 'dl',
        name: 'Sleeper Deluxe',
        tag: 'THE WHOLE NIGHT, PRIVATE',
        unit: 'per cabin',
        price: '1 890',
        berths: 'Double bed · private shower & WC',
        desc: 'A double bed across the full width of the car, your own shower, and breakfast from the dining car delivered at first light.',
        amen: ['Double bed', 'Private shower + WC', 'Breakfast included', 'Bathrobe', 'Priority boarding']
      }
    ];
    const cabTabs = $('#cabTabs');
    cabTabs.innerHTML = CABINS.map((c, i) => '<button class="ctab' + (i === 0 ? ' on' : '') + '" data-i="' + i + '"><span class="idx">' + pad2(i + 1) + '</span><span class="meta"><b>' + c.name + '</b><small>' + c.berths.split(' · ')[0] + '</small></span><span class="pr">' + c.price + '</span></button>').join('');
    const cabImg = $('#cabImg'),
      cabTag = $('#cabTag'),
      cabName = $('#cabName'),
      cabBerths = $('#cabBerths'),
      cabDesc = $('#cabDesc'),
      cabAmen = $('#cabAmen'),
      cabDiag = $('#cabDiag'),
      cabPrice = $('#cabPrice'),
      cabUnit = $('#cabUnit'),
      stage = $('#cabStage');

    function fillCab(c) {
      cabImg.src = c.img;
      cabImg.alt = c.name + ' cabin interior';
      cabTag.textContent = c.tag;
      cabName.textContent = c.name;
      cabBerths.textContent = c.berths;
      cabDesc.textContent = c.desc;
      cabAmen.innerHTML = c.amen.map(a => '<span class="chip">' + a + '</span>').join('');
      cabDiag.innerHTML = DIAG[c.id];
      cabPrice.textContent = c.price + ' kr';
      cabUnit.textContent = c.unit;
    }
    let curCab = 0;
    CABINS.forEach(c => c.img = IMG[c.id]);
    fillCab(CABINS[0]);

    function setCabin(i) {
      if (i === curCab) return;
      curCab = i;
      $$('.ctab').forEach((t, j) => t.classList.toggle('on', j === i));
      if (!RM) {
        stage.classList.add('fade');
        setTimeout(() => {
          fillCab(CABINS[i]);
          stage.classList.remove('fade');
        }, 180);
      } else fillCab(CABINS[i]);
    }
    $$('.ctab').forEach(b => b.addEventListener('click', () => setCabin(+b.dataset.i)));
    $('#cabReserve').addEventListener('click', () => {
      const id = CABINS[curCab].id;
      const radio = document.querySelector('input[name=cls][value="' + id + '"]');
      if (radio) {
        radio.checked = true;
        syncPills();
        updateSummary();
      }
      scrollToId('book');
      flash($('#bookForm'));
    });

    /* ---------- booking ---------- */
    const GEO = {
      Oslo: [330, 470],
      Bergen: [170, 420],
      Trondheim: [380, 330],
      Bodø: [470, 210],
      Myrdal: [250, 452],
      Stockholm: [620, 420],
      Kiruna: [640, 190],
      Narvik: [560, 150],
      Gothenburg: [430, 545],
      Copenhagen: [520, 585],
      Hamburg: [560, 640],
      Vienna: [760, 660]
    };
    const STATIONS = Object.keys(GEO);
    const CLASSES = {
      c6: {
        label: 'Couchette 6',
        mult: 1
      },
      c4: {
        label: 'Couchette 4',
        mult: 1.3
      },
      tw: {
        label: 'Sleeper Twin',
        mult: 1.85
      },
      dl: {
        label: 'Sleeper Deluxe',
        mult: 2.6
      }
    };
    const fromSel = $('#from'),
      toSel = $('#to'),
      dateIn = $('#date'),
      paxVal = $('#paxVal');

    function baseFare(a, b) {
      const A = GEO[a],
        B = GEO[b];
      if (!A || !B) return 399;
      const d = Math.hypot(A[0] - B[0], A[1] - B[1]);
      return Math.max(249, Math.round(160 + d * 1.15));
    }
    STATIONS.forEach((s) => {
      fromSel.insertAdjacentHTML('beforeend', '<option value="' + s + '"' + (s === 'Oslo' ? ' selected' : '') + '>' + s + (s === 'Oslo' ? ' S' : '') + '</option>');
      toSel.insertAdjacentHTML('beforeend', '<option value="' + s + '"' + (s === 'Bergen' ? ' selected' : '') + '>' + s + (s === 'Oslo' ? ' S' : '') + '</option>');
    });
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    dateIn.min = today.toISOString().slice(0, 10);
    dateIn.value = dateIn.min;
    let pax = 2;
    $('#paxMinus').addEventListener('click', () => {
      pax = Math.max(1, pax - 1);
      paxVal.textContent = pax;
      updateSummary();
    });
    $('#paxPlus').addEventListener('click', () => {
      pax = Math.min(6, pax + 1);
      paxVal.textContent = pax;
      updateSummary();
    });
    const brek = $('#chkBrek'),
      bike = $('#chkBike');

    function syncPills() {
      $$('.pill').forEach(p => p.classList.toggle('on', p.querySelector('input').checked));
    }
    $$('input[name=cls]').forEach(r => r.addEventListener('change', () => {
      syncPills();
      updateSummary();
    }));
    [brek, bike].forEach(c => c.addEventListener('change', e => {
      e.target.closest('.chk').classList.toggle('on', e.target.checked);
      updateSummary();
    }));
    fromSel.addEventListener('change', updateSummary);
    toSel.addEventListener('change', updateSummary);
    const sumRows = $('#sumRows'),
      sumTotal = $('#sumTotal'),
      estRoute = $('#estRoute');

    function compute() {
      const a = fromSel.value,
        b = toSel.value;
      const base = baseFare(a, b);
      const cls = CLASSES[document.querySelector('input[name=cls]:checked').value];
      const cabin = Math.round(base * cls.mult) * pax;
      const baseSub = Math.round(base) * pax;
      let extra = 0;
      const lines = [
        ['Base fare × ' + pax, fmtKr(baseSub)],
        [cls.label + ' supplement', fmtKr(Math.max(0, cabin - baseSub))]
      ];
      if (brek.checked) {
        const v = 89 * pax;
        extra += v;
        lines.push(['Breakfast basket × ' + pax, fmtKr(v)]);
      }
      if (bike.checked) {
        extra += 149;
        lines.push(['Bicycle space', fmtKr(149)]);
      }
      return {
        lines,
        total: cabin + extra,
        cls
      };
    }

    function updateSummary() {
      const a = fromSel.value,
        b = toSel.value;
      estRoute.textContent = a + (a === 'Oslo' ? ' S' : '') + '  →  ' + b + (b === 'Oslo' ? ' S' : '');
      const c = compute();
      sumRows.innerHTML = c.lines.map(l => '<div class="srow"><span>' + l[0] + '</span><span>' + l[1] + '</span></div>').join('');
      sumTotal.textContent = fmtKr(c.total);
    }
    updateSummary();

    const bookForm = $('#bookForm'),
      formErr = $('#formErr'),
      estimateBox = $('#estimateBox'),
      ticketBox = $('#ticketBox');
    bookForm.addEventListener('submit', e => {
      e.preventDefault();
      let err = '';
      const a = fromSel.value,
        b = toSel.value;
      if (a === b) err = 'Choose two different stations.';
      if (!dateIn.value) err = err || 'Pick a travel night.';
      else {
        const dt = new Date(dateIn.value + 'T12:00');
        if (dt < today) err = err || 'That night has already passed.';
      }
      formErr.textContent = err;
      if (err) {
        bookForm.classList.remove('shake');
        void bookForm.offsetWidth;
        bookForm.classList.add('shake');
        return;
      }
      const c = compute();
      $('#tRoute').textContent = a + (a === 'Oslo' ? ' S' : '') + '  →  ' + b + (b === 'Oslo' ? ' S' : '');
      $('#tDate').textContent = new Date(dateIn.value + 'T12:00').toLocaleDateString('en-GB', {
        weekday: 'short',
        day: 'numeric',
        month: 'short',
        year: 'numeric'
      });
      $('#tClass').textContent = c.cls.label;
      $('#tPax').textContent = pax + (pax > 1 ? ' travellers' : ' traveller');
      $('#tTotal').textContent = fmtKr(c.total);
      $('#tCode').textContent = 'NL-' + Math.random().toString(36).slice(2, 8).toUpperCase();
      estimateBox.style.display = 'none';
      ticketBox.classList.add('show');
      ticketBox.scrollIntoView({
        behavior: RM ? 'auto' : 'smooth',
        block: 'center'
      });
    });
    $('#again').addEventListener('click', () => {
      ticketBox.classList.remove('show');
      estimateBox.style.display = '';
    });

    /* ---------- newsletter ---------- */
    const nlForm = $('#nlForm'),
      nlInput = $('#nlInput'),
      nlErr = $('#nlErr');
    nlForm.addEventListener('submit', e => {
      e.preventDefault();
      const v = nlInput.value.trim();
      if (!/^\S+@\S+\.\S+$/.test(v)) {
        nlErr.textContent = 'That address looks off — try again.';
        return;
      }
      nlForm.innerHTML = '<p class="nl-ok">✓ You are on the night mail. First dispatch Friday.</p>';
    });

    /* ---------- timeline progress + lit ---------- */
    const tl = $('#tl'),
      tlFill = $('#tlFill');

    function tlProg() {
      const r = tl.getBoundingClientRect();
      const vh = innerHeight;
      const p = Math.min(1, Math.max(0, (vh * .75 - r.top) / r.height));
      tlFill.style.height = (p * 100) + '%';
    }
    addEventListener('scroll', tlProg, {
      passive: true
    });
    tlProg();
    const ioLit = new IntersectionObserver(es => es.forEach(x => {
      if (x.isIntersecting) x.target.classList.add('lit');
    }), {
      threshold: .5
    });
    $$('.tl-item').forEach(el => ioLit.observe(el));

    /* ---------- reveals ---------- */
    const ioRev = new IntersectionObserver(es => es.forEach(x => {
      if (x.isIntersecting) {
        x.target.classList.add('in');
        ioRev.unobserve(x.target);
      }
    }), {
      threshold: .14
    });
    $$('.rev').forEach(el => ioRev.observe(el));

    /* ---------- header / toTop / nav active (scroll-based) ---------- */
    const navLinks = $$('.nav a[href^="#"], .mnav a[href^="#"]');
    const sections = $$('main section[id]');

    function activeNav() {
      const line = headerOffset() + innerHeight * 0.30;
      let cur = sections.length ? sections[0].id : '';
      for (const s of sections) {
        const top = s.getBoundingClientRect().top + scrollY;
        if (top <= line) cur = s.id;
      }
      navLinks.forEach(l => l.classList.toggle('active', l.getAttribute('href') === '#' + cur));
    }
    let navRaf = 0;

    function onScroll() {
      hdr.classList.toggle('scrolled', scrollY > 10);
      toTop.classList.toggle('show', scrollY > 600);
      if (!navRaf) navRaf = requestAnimationFrame(() => {
        navRaf = 0;
        activeNav();
      });
    }
    addEventListener('scroll', onScroll, {
      passive: true
    });
    addEventListener('resize', activeNav);
    toTop.addEventListener('click', () => scrollTo({
      top: 0,
      behavior: RM ? 'auto' : 'smooth'
    }));
    activeNav();
    onScroll();

    /* ---------- helpers ---------- */
    function flash(el) {
      el.classList.remove('flash');
      void el.offsetWidth;
      el.classList.add('flash');
    }

    /* honour an incoming hash without a hard jump */
    if (location.hash) {
      const id = location.hash.slice(1);
      if (document.getElementById(id)) requestAnimationFrame(() => scrollToId(id));
    }

    /* preload cabin images */
    Object.values(IMG).forEach(src => {
      const im = new Image();
      im.src = src;
    });
  </script>
</body>

</html>