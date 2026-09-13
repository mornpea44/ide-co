<!DOCTYPE html>
<html lang="en" data-realm="asgard">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="description" content="The Yggdrasil Line — an aether-boilered railway serving the Nine Realms and the eight million kami. Live departures, route map, patrons and tickets.">
<meta name="theme-color" content="#050a12">
<title>Yggdrasil Line · Æther Railway of the Two Pantheons</title>
<style>
/* ============================================================
   1. TOKENS
   ============================================================ */
*,*::before,*::after{box-sizing:border-box}
:root{
  --brass:#c9973f;
  --brass-hi:#f2d68f;
  --brass-lo:#7a5a20;
  --copper:#b3663a;
  --paper:#f3e7cf;
  --font-display:ui-serif,"Iowan Old Style","Palatino Linotype",Palatino,"Book Antiqua",Georgia,"Times New Roman",serif;
  --font-body:system-ui,-apple-system,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif;
  --font-mono:ui-monospace,"SF Mono",SFMono-Regular,Menlo,Consolas,"Liberation Mono",monospace;
  --maxw:1200px;
  --rail-dur:2.6s;
  --wheel-dur:1.8s;
  --ease:cubic-bezier(.22,.61,.36,1);
}
html[data-realm="asgard"]{
  --accent:#74d6ee; --accent-2:#aab6ff; --accent-3:#2f6f86;
  --bg:#050a12; --bg-2:#091320; --panel:#0c1926; --panel-2:#112534;
  --text:#e9f2f8; --muted:#8fa6b8; --line:#1c3245;
  --glow:rgba(116,214,238,.30);
  --sky-1:#04101f; --sky-2:#0a2740; --sky-3:#14485f;
  --seal:#8fd6ea;
}
html[data-realm="takamagahara"]{
  --accent:#ff7d54; --accent-2:#ffd88c; --accent-3:#a1442c;
  --bg:#0d0608; --bg-2:#170b0e; --panel:#1b0f12; --panel-2:#28171b;
  --text:#fcefe7; --muted:#bb9c92; --line:#3d2226;
  --glow:rgba(255,125,84,.28);
  --sky-1:#150a0c; --sky-2:#3b1519; --sky-3:#82371f;
  --seal:#ff9169;
}

/* ============================================================
   2. BASE
   ============================================================ */
html{scroll-behavior:smooth;-webkit-text-size-adjust:100%}
body{
  margin:0;background:var(--bg);color:var(--text);
  font-family:var(--font-body);font-size:16px;line-height:1.6;
  overflow-x:hidden;transition:background-color .7s var(--ease),color .7s var(--ease);
}
img,svg{display:block;max-width:100%}
button,input,select{font:inherit;color:inherit}
a{color:var(--accent);text-decoration:none}
h1,h2,h3,h4{font-family:var(--font-display);font-weight:600;line-height:1.12;margin:0;letter-spacing:.005em}
p{margin:0}
:focus-visible{outline:2px solid var(--accent);outline-offset:3px;border-radius:4px}
.wrap{width:min(100% - 2.5rem,var(--maxw));margin-inline:auto}
.skip{position:fixed;left:.75rem;top:-4rem;z-index:200;background:var(--brass);color:#1a1206;
  padding:.6rem 1rem;border-radius:0 0 8px 8px;font-weight:700;transition:top .2s}
.skip:focus{top:0}
.grain{position:fixed;inset:0;z-index:120;pointer-events:none;opacity:.05;mix-blend-mode:overlay;
  background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='160' height='160'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.8' numOctaves='3'/%3E%3C/filter%3E%3Crect width='160' height='160' filter='url(%23n)'/%3E%3C/svg%3E")}
.sr{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap;border:0}

/* ============================================================
   3. SHARED ORNAMENTS
   ============================================================ */
.plate{
  background:linear-gradient(160deg,var(--panel-2),var(--panel) 55%,var(--bg-2));
  border:1px solid var(--line);border-radius:12px;position:relative;
  box-shadow:0 1px 0 rgba(255,255,255,.04) inset,0 22px 50px -30px #000;
}
.plate.riveted::before,.plate.riveted::after{
  content:"";position:absolute;width:7px;height:7px;border-radius:50%;
  background:radial-gradient(circle at 32% 30%,var(--brass-hi),var(--brass-lo) 70%);
  box-shadow:0 0 0 1px rgba(0,0,0,.5);
}
.plate.riveted::before{top:9px;left:9px}
.plate.riveted::after{bottom:9px;right:9px}
.brass-edge{
  border-image:linear-gradient(120deg,var(--brass-lo),var(--brass-hi) 30%,var(--brass) 55%,var(--brass-lo)) 1;
}
.eyebrow{
  font-family:var(--font-mono);font-size:.7rem;letter-spacing:.28em;text-transform:uppercase;
  color:var(--brass);display:flex;align-items:center;gap:.7rem;
}
.eyebrow::after{content:"";height:1px;flex:1;background:linear-gradient(90deg,var(--brass-lo),transparent)}
.sect-head{display:flex;flex-wrap:wrap;gap:1.2rem 2rem;align-items:flex-end;justify-content:space-between;margin-bottom:2rem}
.sect-head h2{font-size:clamp(1.7rem,3.4vw,2.6rem)}
.sect-head p{color:var(--muted);max-width:46ch;font-size:.95rem}
section{padding:clamp(3.5rem,8vw,6.5rem) 0;position:relative;z-index:2}
.btn{
  --bg-a:linear-gradient(180deg,var(--brass-hi),var(--brass) 45%,var(--brass-lo));
  appearance:none;border:1px solid var(--brass-lo);background:var(--bg-a);color:#20160a;
  padding:.72rem 1.35rem;border-radius:7px;font-weight:700;font-size:.86rem;letter-spacing:.09em;
  text-transform:uppercase;cursor:pointer;position:relative;
  box-shadow:0 1px 0 rgba(255,255,255,.45) inset,0 -2px 0 rgba(0,0,0,.28) inset,0 10px 22px -14px #000;
  transition:transform .16s var(--ease),filter .2s;
}
.btn:hover{filter:brightness(1.09)}
.btn:active{transform:translateY(1px)}
.btn.ghost{
  background:transparent;color:var(--text);border:1px solid var(--line);
  box-shadow:none;font-weight:600;
}
.btn.ghost:hover{border-color:var(--accent);color:var(--accent)}
.btn[disabled]{opacity:.4;cursor:not-allowed;filter:none}
.chip{
  appearance:none;background:transparent;border:1px solid var(--line);color:var(--muted);
  padding:.4rem .85rem;border-radius:999px;font-size:.76rem;letter-spacing:.11em;text-transform:uppercase;
  cursor:pointer;font-family:var(--font-mono);transition:.22s var(--ease);
}
.chip:hover{color:var(--text);border-color:var(--accent-3)}
.chip[aria-pressed="true"]{
  background:color-mix(in srgb,var(--accent) 16%,transparent);
  border-color:var(--accent);color:var(--accent);
}

/* ============================================================
   4. HEADER / NAV
   ============================================================ */
.progress{position:fixed;top:0;left:0;height:2px;z-index:110;width:0%;
  background:linear-gradient(90deg,var(--brass-lo),var(--brass-hi),var(--accent));
  box-shadow:0 0 12px var(--glow)}
header.nav{
  position:sticky;top:0;z-index:100;
  background:color-mix(in srgb,var(--bg) 84%,transparent);
  backdrop-filter:blur(14px) saturate(140%);-webkit-backdrop-filter:blur(14px) saturate(140%);
  border-bottom:1px solid var(--line);
}
.nav-in{display:flex;align-items:center;gap:1.2rem;padding:.7rem 0;min-height:64px}
.brand{display:flex;align-items:center;gap:.75rem;margin-right:auto}
.brand .mk{width:38px;height:38px;flex:none;color:var(--brass);filter:drop-shadow(0 0 8px var(--glow))}
.brand b{font-family:var(--font-display);font-size:1.06rem;letter-spacing:.13em;text-transform:uppercase;display:block;line-height:1.1}
.brand small{font-family:var(--font-mono);font-size:.6rem;letter-spacing:.22em;color:var(--muted);text-transform:uppercase}
.nav-links{display:flex;gap:.2rem;list-style:none;margin:0;padding:0}
.nav-links a{
  display:block;padding:.5rem .8rem;border-radius:6px;color:var(--muted);font-size:.82rem;
  letter-spacing:.1em;text-transform:uppercase;font-family:var(--font-mono);transition:.2s;position:relative;
}
.nav-links a:hover{color:var(--text);background:color-mix(in srgb,var(--accent) 9%,transparent)}
.nav-links a.active{color:var(--accent)}
.nav-links a.active::after{content:"";position:absolute;left:.8rem;right:.8rem;bottom:.2rem;height:1px;background:var(--accent);box-shadow:0 0 8px var(--accent)}
.nav-tools{display:flex;align-items:center;gap:.5rem;flex:none}
.brand{min-width:0}
.brand>span{min-width:0}
.brand b{white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.seg button{white-space:nowrap}
.seg{display:flex;border:1px solid var(--line);border-radius:8px;overflow:hidden;background:var(--panel)}
.seg button{
  appearance:none;background:transparent;border:0;color:var(--muted);cursor:pointer;
  padding:.45rem .7rem;font-family:var(--font-mono);font-size:.66rem;letter-spacing:.14em;text-transform:uppercase;transition:.25s;
}
.seg button[aria-pressed="true"]{background:color-mix(in srgb,var(--accent) 20%,transparent);color:var(--accent);text-shadow:0 0 10px var(--glow)}
.icon-btn{
  appearance:none;width:38px;height:38px;display:grid;place-items:center;border-radius:8px;
  border:1px solid var(--line);background:var(--panel);color:var(--muted);cursor:pointer;transition:.22s;
}
.icon-btn:hover{color:var(--accent);border-color:var(--accent-3)}
.icon-btn[aria-pressed="true"]{color:var(--accent);border-color:var(--accent);box-shadow:0 0 16px -4px var(--glow)}
.burger{display:none}
@media (max-width:900px){
.nav-tools{margin-left:auto}
  .console{margin-top:-12px}
  .nav-links{
    position:absolute;top:100%;left:0;right:0;flex-direction:column;gap:0;padding:.5rem 1.25rem 1rem;
    background:var(--bg-2);border-bottom:1px solid var(--line);
    clip-path:inset(0 0 100% 0);opacity:0;pointer-events:none;transition:.34s var(--ease);
  }
  .nav-links.open{clip-path:inset(0 0 0 0);opacity:1;pointer-events:auto}
  .nav-links a{padding:.85rem .4rem;border-bottom:1px solid var(--line);font-size:.9rem}
  .burger{display:grid}
  .brand small{display:none}
}
@media (max-width:520px){ .seg button{padding:.45rem .5rem;font-size:.6rem} }
@media (max-width:560px){
  .nav-in{gap:.6rem}
  .brand .mk{width:32px;height:32px}
  .brand b{font-size:.92rem}
  .icon-btn{width:34px;height:34px}
}
/* ============================================================
   5. HERO
   ============================================================ */
.hero{position:relative;min-height:min(94svh,860px);display:flex;flex-direction:column;justify-content:flex-end;
  overflow:hidden;padding:0;isolation:isolate}
.sky{position:absolute;inset:0;z-index:-4;
  background:
    radial-gradient(120% 80% at 78% 8%,color-mix(in srgb,var(--accent) 16%,transparent),transparent 60%),
    radial-gradient(90% 60% at 12% 0%,color-mix(in srgb,var(--accent-2) 12%,transparent),transparent 62%),
    linear-gradient(180deg,var(--sky-1),var(--sky-2) 48%,var(--sky-3) 88%,var(--bg) 100%);
  transition:background .8s var(--ease)}
.stars{position:absolute;inset:0;z-index:-3;opacity:.75;
  background-image:
    radial-gradient(1.4px 1.4px at 12% 18%,#fff,transparent),
    radial-gradient(1.2px 1.2px at 28% 9%,#fff,transparent),
    radial-gradient(1.6px 1.6px at 44% 26%,#fff,transparent),
    radial-gradient(1.1px 1.1px at 61% 12%,#fff,transparent),
    radial-gradient(1.5px 1.5px at 74% 30%,#fff,transparent),
    radial-gradient(1.2px 1.2px at 88% 16%,#fff,transparent),
    radial-gradient(1.3px 1.3px at 19% 38%,#fff,transparent),
    radial-gradient(1.1px 1.1px at 53% 42%,#fff,transparent),
    radial-gradient(1.4px 1.4px at 92% 40%,#fff,transparent);
  animation:twinkle 7s ease-in-out infinite alternate}
@keyframes twinkle{from{opacity:.4}to{opacity:.85}}
#ambient{position:absolute;inset:0;z-index:-2;width:100%;height:100%;pointer-events:none}
.mountains{position:absolute;left:0;right:0;bottom:132px;z-index:-2;color:#000;opacity:.55}
.mountains svg{width:100%;height:auto}

.hero-copy{padding:clamp(6rem,14vh,9rem) 0 2rem;position:relative;z-index:3}
.hero h1{
  font-size:clamp(2.3rem,6.6vw,5rem);max-width:19ch;margin:.9rem 0 1.1rem;
  text-shadow:0 6px 40px rgba(0,0,0,.7);
}
.hero h1 em{font-style:italic;color:var(--accent);text-shadow:0 0 34px var(--glow)}
.hero .lede{color:#cfdbe5;max-width:56ch;font-size:clamp(.98rem,1.4vw,1.12rem)}
html[data-realm="takamagahara"] .hero .lede{color:#e9d6cd}
.hero-cta{display:flex;flex-wrap:wrap;gap:.7rem;margin-top:1.8rem}
.stat-strip{display:flex;flex-wrap:wrap;gap:2.2rem;margin-top:2.6rem;padding-top:1.5rem;border-top:1px solid var(--line)}
.stat b{font-family:var(--font-display);font-size:1.65rem;display:block;line-height:1;color:var(--brass-hi)}
.stat span{font-family:var(--font-mono);font-size:.63rem;letter-spacing:.2em;text-transform:uppercase;color:var(--muted)}

/* track + locomotive */
.track-zone{position:relative;z-index:3;height:200px;flex:none}
.track{position:absolute;left:0;right:0;bottom:0;height:96px;
  background:
    linear-gradient(180deg,transparent 0 46px,#000 46px 52px,transparent 52px),
    repeating-linear-gradient(90deg,#000 0 26px,transparent 26px 60px),
    linear-gradient(180deg,transparent 0 62px,var(--brass-lo) 62px 64px,transparent 64px),
    linear-gradient(180deg,transparent 0 76px,var(--brass-lo) 76px 78px,transparent 78%);
  background-size:auto,240px 100%,auto,auto;
  animation:railScroll var(--rail-dur) linear infinite;opacity:.92}
@keyframes railScroll{to{background-position:0 0,-240px 0,0 0,0 0}}
.track::after{content:"";position:absolute;inset:80px 0 0;background:linear-gradient(180deg,#000,transparent)}
.loco{position:absolute;left:6%;width:clamp(220px,30vw,360px);
  bottom:calc(33px - clamp(220px,30vw,360px)*0.0588);z-index:4;
  filter:drop-shadow(0 16px 24px rgba(0,0,0,.75))}
.loco svg{width:100%;height:auto;display:block}
.loco .wheel{transform-box:fill-box;transform-origin:center;animation:spin var(--wheel-dur) linear infinite}
.loco .rod{animation:orbit var(--wheel-dur) linear infinite}
@keyframes spin{to{transform:rotate(360deg)}}
@keyframes orbit{
  0%{transform:translate(8px,0)}12.5%{transform:translate(5.7px,5.7px)}25%{transform:translate(0,8px)}
  37.5%{transform:translate(-5.7px,5.7px)}50%{transform:translate(-8px,0)}62.5%{transform:translate(-5.7px,-5.7px)}
  75%{transform:translate(0,-8px)}87.5%{transform:translate(5.7px,-5.7px)}100%{transform:translate(8px,0)}}
#funnelAnchor{position:absolute;left:72.5%;top:15%;width:2px;height:2px;pointer-events:none}
.scroll-hint{position:absolute;right:1.2rem;bottom:110px;z-index:5;writing-mode:vertical-rl;
  font-family:var(--font-mono);font-size:.62rem;letter-spacing:.3em;text-transform:uppercase;color:var(--muted);
  display:flex;align-items:center;gap:.7rem}
.scroll-hint::after{content:"";width:1px;height:52px;background:linear-gradient(180deg,var(--brass),transparent);
  animation:drip 2.4s var(--ease) infinite}
@keyframes drip{0%{transform:scaleY(.2);opacity:0}40%{opacity:1}100%{transform:scaleY(1);opacity:0}}
@media (max-width:720px){.scroll-hint{display:none}}

/* ============================================================
   6. CAB CONSOLE
   ============================================================ */
.console{position:relative;z-index:6;margin-top:-30px}
.console-in{display:grid;grid-template-columns:auto 1fr auto;gap:1.6rem;align-items:center;padding:1.15rem 1.4rem}
@media (max-width:900px){.console-in{grid-template-columns:1fr;gap:1.3rem}}
.gauge{width:132px;height:132px;flex:none;position:relative}
.gauge svg{width:100%;height:100%;overflow:visible}
.gauge .needle{transform-origin:60px 60px;transition:transform .5s var(--ease)}
.gauge-lbl{position:absolute;inset:auto 0 16px;text-align:center;font-family:var(--font-mono);
  font-size:.55rem;letter-spacing:.2em;color:var(--muted);text-transform:uppercase}
.gauge-val{position:absolute;inset:auto 0 30px;text-align:center;font-family:var(--font-display);
  font-size:1.5rem;color:var(--brass-hi);line-height:1}
.controls{display:flex;flex-direction:column;gap:.55rem;min-width:0}
.controls .row{display:flex;align-items:center;gap:1rem;flex-wrap:wrap}
.throttle{-webkit-appearance:none;appearance:none;width:100%;height:34px;background:transparent;cursor:grab;flex:1;min-width:180px}
.throttle:active{cursor:grabbing}
.throttle::-webkit-slider-runnable-track{height:10px;border-radius:6px;
  background:linear-gradient(90deg,var(--accent-3),var(--brass) 55%,#b3392b);
  border:1px solid rgba(0,0,0,.6);box-shadow:0 1px 0 rgba(255,255,255,.1) inset}
.throttle::-webkit-slider-thumb{-webkit-appearance:none;width:22px;height:30px;margin-top:-11px;border-radius:4px;
  background:linear-gradient(180deg,#f6e2b0,var(--brass) 50%,var(--brass-lo));border:1px solid #4a3411;
  box-shadow:0 2px 6px rgba(0,0,0,.6),0 0 0 1px rgba(255,255,255,.15) inset}
.throttle::-moz-range-track{height:10px;border-radius:6px;background:linear-gradient(90deg,var(--accent-3),var(--brass) 55%,#b3392b);border:1px solid rgba(0,0,0,.6)}
.throttle::-moz-range-thumb{width:20px;height:28px;border-radius:4px;border:1px solid #4a3411;
  background:linear-gradient(180deg,#f6e2b0,var(--brass) 50%,var(--brass-lo))}
.readouts{display:grid;grid-template-columns:repeat(auto-fit,minmax(96px,1fr));gap:.5rem;width:100%}
.readout{background:rgba(0,0,0,.36);border:1px solid var(--line);border-radius:7px;padding:.45rem .6rem}
.readout span{display:block;font-family:var(--font-mono);font-size:.56rem;letter-spacing:.18em;color:var(--muted);text-transform:uppercase}
.readout b{font-family:var(--font-mono);font-size:1.02rem;color:var(--accent);font-variant-numeric:tabular-nums}
.readout b.warn{color:#ff8a6a;animation:blink .7s steps(2) infinite}
@keyframes blink{50%{opacity:.35}}
.console-actions{display:flex;flex-direction:column;gap:.5rem}
.bar{height:8px;border-radius:5px;background:rgba(0,0,0,.5);border:1px solid var(--line);overflow:hidden;position:relative}
.bar i{display:block;height:100%;width:0%;background:linear-gradient(90deg,var(--accent-3),var(--accent));
  box-shadow:0 0 12px var(--glow);transition:width .35s var(--ease),background .3s}
.bar.hot i{background:linear-gradient(90deg,#b3392b,#ff8a5c)}
.alarm{position:absolute;inset:0;border:1px solid #ff6a4d;border-radius:12px;pointer-events:none;opacity:0;transition:opacity .3s}
.alarm.on{opacity:1;animation:alarmpulse 1s ease-in-out infinite}
@keyframes alarmpulse{50%{box-shadow:0 0 0 3px rgba(255,106,77,.16),0 0 40px -6px rgba(255,106,77,.6) inset}}

/* ============================================================
   7. TICKER
   ============================================================ */
.ticker{border-block:1px solid var(--line);background:var(--bg-2);overflow:hidden;position:relative;z-index:2}
.ticker-in{display:flex;align-items:center;gap:1rem;padding:.65rem 0;min-height:46px}
.ticker .tag{flex:none;font-family:var(--font-mono);font-size:.6rem;letter-spacing:.2em;text-transform:uppercase;
  color:#20160a;background:linear-gradient(180deg,var(--brass-hi),var(--brass));padding:.25rem .6rem;border-radius:4px}
#tickerText{font-family:var(--font-display);font-style:italic;font-size:.98rem;color:var(--muted);
  white-space:nowrap;overflow:hidden;text-overflow:ellipsis;transition:opacity .45s,transform .45s var(--ease)}
#tickerText.out{opacity:0;transform:translateY(-6px)}

/* ============================================================
   8. DEPARTURES
   ============================================================ */
.board-head{display:flex;flex-wrap:wrap;gap:1rem;align-items:center;justify-content:space-between;margin-bottom:1rem}
.clockbox{display:flex;align-items:baseline;gap:.7rem;font-family:var(--font-mono)}
.clockbox b{font-size:clamp(1.6rem,4vw,2.4rem);color:var(--brass-hi);font-variant-numeric:tabular-nums;letter-spacing:.04em}
.clockbox span{font-size:.63rem;letter-spacing:.2em;text-transform:uppercase;color:var(--muted)}
.filters{display:flex;gap:.4rem;flex-wrap:wrap}
.board{padding:.4rem;overflow:hidden}
.brow{
  display:grid;grid-template-columns:minmax(0,2fr) minmax(0,1.05fr) .95fr .85fr .7fr minmax(0,1.15fr);
  gap:.4rem;align-items:center;padding:.62rem .8rem;border-bottom:1px solid rgba(255,255,255,.045);
}
.brow.head{
  font-family:var(--font-mono);font-size:.6rem;letter-spacing:.2em;text-transform:uppercase;color:var(--brass);
  border-bottom:1px solid var(--line);padding-block:.7rem;background:rgba(0,0,0,.25)
}
.brow:not(.head):hover{background:color-mix(in srgb,var(--accent) 6%,transparent)}
.brow:last-child{border-bottom:0}
.dest{display:flex;align-items:center;gap:.7rem;min-width:0}
.dest .gl{width:30px;height:30px;flex:none;display:grid;place-items:center;border-radius:6px;
  border:1px solid var(--line);background:rgba(0,0,0,.35);color:var(--accent);font-size:.95rem}
.dest .tx{min-width:0}
.dest .tx b{display:block;font-family:var(--font-display);font-size:1.02rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.dest .tx small{display:block;font-family:var(--font-mono);font-size:.6rem;letter-spacing:.14em;color:var(--muted);text-transform:uppercase}
.cell{font-family:var(--font-mono);font-size:.82rem;font-variant-numeric:tabular-nums;color:#cfe0ec}
html[data-realm="takamagahara"] .cell{color:#e8d8d0}
.st{justify-self:start;font-family:var(--font-mono);font-size:.66rem;letter-spacing:.13em;text-transform:uppercase;
  padding:.28rem .6rem;border-radius:4px;border:1px solid currentColor;white-space:nowrap}
.st.ontime{color:#7fd6a5}.st.boarding{color:var(--accent);animation:blink 1.5s steps(2) infinite}
.st.delayed{color:#ffb35c}.st.departed{color:var(--muted);opacity:.65}.st.held{color:#ff8a7a}
.flip{animation:flipcell .42s var(--ease)}
@keyframes flipcell{0%{transform:rotateX(0)}45%{transform:rotateX(-88deg);opacity:.25}55%{transform:rotateX(88deg);opacity:.25}100%{transform:rotateX(0)}}
.board-foot{display:flex;flex-wrap:wrap;gap:.8rem;justify-content:space-between;align-items:center;
  padding:.75rem 1rem;border-top:1px solid var(--line);background:rgba(0,0,0,.22);
  font-family:var(--font-mono);font-size:.62rem;letter-spacing:.15em;text-transform:uppercase;color:var(--muted)}
@media (max-width:820px){
  .brow.head{display:none}
  .brow{grid-template-columns:1fr;gap:.5rem;padding:.9rem .9rem;border:1px solid var(--line);border-radius:9px;margin:.5rem;background:rgba(0,0,0,.2)}
  .cell,.st{display:grid;grid-template-columns:6.4rem 1fr;align-items:center;gap:.6rem;justify-self:stretch}
  .cell::before,.st::before{content:attr(data-l);font-size:.57rem;letter-spacing:.18em;text-transform:uppercase;color:var(--muted)}
  .st{border:0;padding:0}
  .st::before{color:var(--muted)}
  .st span{border:1px solid currentColor;padding:.2rem .55rem;border-radius:4px;justify-self:start}
}

/* ============================================================
   9. ROUTE MAP
   ============================================================ */
.line-grid{display:grid;grid-template-columns:minmax(0,1.65fr) minmax(0,1fr);gap:1.4rem;align-items:start}
@media (max-width:980px){.line-grid{grid-template-columns:1fr}}
.mapbox{padding:1rem 1rem .6rem;overflow:hidden}
.mapbox svg{width:100%;height:auto;overflow:visible}
.rail-bg{fill:none;stroke:rgba(255,255,255,.05);stroke-width:14;stroke-linecap:round}
.rail{fill:none;stroke:url(#railGrad);stroke-width:3.4;stroke-linecap:round;filter:drop-shadow(0 0 8px var(--glow))}
.rail-ties{fill:none;stroke:rgba(0,0,0,.55);stroke-width:7;stroke-dasharray:2 11;stroke-linecap:butt}
.station{cursor:pointer}
.station .hit{fill:transparent}
.station .ring{fill:var(--bg);stroke:var(--brass);stroke-width:2;transition:.3s var(--ease)}
.station .dot{fill:var(--brass-hi);transition:.3s}
.station .gl{font-family:var(--font-display);font-size:15px;fill:var(--muted);transition:.3s;text-anchor:middle}
.station .nm{font-family:var(--font-mono);font-size:9.5px;letter-spacing:.11em;fill:var(--muted);text-transform:uppercase;transition:.3s}
.station:hover .ring,.station:focus-visible .ring{stroke:var(--accent);r:9}
.station:hover .nm,.station:focus-visible .nm{fill:var(--text)}
.station.sel .ring{stroke:var(--accent);fill:color-mix(in srgb,var(--accent) 22%,var(--bg))}
.station.sel .nm{fill:var(--accent)}
.station.sel .gl{fill:var(--accent)}
.station.passed .dot{fill:var(--accent)}
.station .pulse{fill:none;stroke:var(--accent);stroke-width:2;opacity:0}
.station.sel .pulse{animation:pulseRing 2.1s var(--ease) infinite}
@keyframes pulseRing{0%{r:8;opacity:.85}100%{r:24;opacity:0}}
#trainMarker{filter:drop-shadow(0 0 10px var(--glow))}
.bridge-mark{stroke:var(--accent-2);stroke-width:1.6;fill:none;opacity:.75}
.map-legend{display:flex;flex-wrap:wrap;gap:.35rem 1.4rem;padding:.7rem 1rem;border-top:1px solid var(--line);
  font-family:var(--font-mono);font-size:.6rem;letter-spacing:.14em;text-transform:uppercase;color:var(--muted)}
.map-legend i{display:inline-block;width:16px;height:2px;background:var(--accent);vertical-align:middle;margin-right:.45rem}
.map-legend i.b{background:repeating-linear-gradient(90deg,var(--accent-2) 0 4px,transparent 4px 8px)}
.station-panel{padding:1.25rem}
.station-panel .glyph{width:52px;height:52px;display:grid;place-items:center;border-radius:10px;
  border:1px solid var(--line);background:rgba(0,0,0,.32);color:var(--accent);font-size:1.5rem;margin-bottom:.9rem;
  box-shadow:0 0 24px -8px var(--glow) inset}
.station-panel h3{font-size:1.5rem;margin-bottom:.15rem}
.station-panel .meta{display:flex;flex-wrap:wrap;gap:.4rem 1rem;font-family:var(--font-mono);font-size:.62rem;
  letter-spacing:.15em;text-transform:uppercase;color:var(--muted);margin:.55rem 0 .9rem}
.station-panel .lore{font-size:.93rem;color:#d5e2ec}
html[data-realm="takamagahara"] .station-panel .lore{color:#e6d5cd}
.svc{display:flex;flex-wrap:wrap;gap:.35rem;margin-top:1rem;list-style:none;padding:0}
.svc li{font-family:var(--font-mono);font-size:.6rem;letter-spacing:.12em;text-transform:uppercase;
  border:1px solid var(--line);border-radius:999px;padding:.25rem .6rem;color:var(--muted)}
.run-bar{margin-top:1.2rem}
.run-bar .bar{margin-top:.4rem}
.journey-log{margin-top:.9rem;font-family:var(--font-mono);font-size:.68rem;color:var(--muted);
  max-height:96px;overflow:auto;border-top:1px dashed var(--line);padding-top:.6rem;line-height:1.75}
.journey-log b{color:var(--accent);font-weight:400}

/* ============================================================
   10. PATRONS
   ============================================================ */
.patron-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(232px,1fr));gap:1rem}
.pcard{padding:1.15rem;cursor:pointer;text-align:left;transition:transform .3s var(--ease),box-shadow .3s,border-color .3s;overflow:hidden}
.pcard:hover{transform:translateY(-4px);border-color:var(--accent-3);box-shadow:0 26px 50px -30px #000,0 0 0 1px var(--glow)}
.pcard .top{display:flex;gap:.9rem;align-items:flex-start}
.pcard .sigil{width:46px;height:46px;flex:none;color:var(--brass);transition:.4s var(--ease)}
.pcard:hover .sigil{color:var(--accent);transform:rotate(-8deg) scale(1.06);filter:drop-shadow(0 0 10px var(--glow))}
.pcard h3{font-size:1.16rem}
.pcard .ep{font-family:var(--font-mono);font-size:.6rem;letter-spacing:.15em;text-transform:uppercase;color:var(--brass);margin-top:.2rem}
.pcard .dom{margin-top:.85rem;font-size:.86rem;color:var(--muted)}
.pcard .pn{position:absolute;top:.8rem;right:.9rem;font-family:var(--font-mono);font-size:.55rem;
  letter-spacing:.18em;text-transform:uppercase;color:var(--muted);opacity:.75}
.modal{position:fixed;inset:0;z-index:150;display:grid;place-items:center;padding:1.25rem;
  background:rgba(2,5,9,.78);backdrop-filter:blur(7px);-webkit-backdrop-filter:blur(7px);
  opacity:0;pointer-events:none;transition:opacity .3s}
.modal.open{opacity:1;pointer-events:auto}
.modal-card{width:min(100%,600px);max-height:86svh;overflow:auto;padding:1.75rem;transform:translateY(14px) scale(.98);transition:transform .34s var(--ease)}
.modal.open .modal-card{transform:none}
.modal-card .head{display:flex;gap:1.1rem;align-items:flex-start}
.modal-card .sigil{width:64px;height:64px;flex:none;color:var(--accent);filter:drop-shadow(0 0 14px var(--glow))}
.modal-card h3{font-size:1.85rem}
.modal-card .body{margin-top:1.2rem;font-size:.96rem;color:#d7e3ec}
html[data-realm="takamagahara"] .modal-card .body{color:#e7d7cf}
.modal-card .kv{display:grid;grid-template-columns:8.5rem 1fr;gap:.5rem 1rem;margin-top:1.2rem;
  font-family:var(--font-mono);font-size:.75rem;border-top:1px solid var(--line);padding-top:1rem}
.modal-card .kv dt{color:var(--brass);letter-spacing:.14em;text-transform:uppercase;font-size:.62rem}
.modal-card .kv dd{margin:0;color:var(--muted)}
.modal-close{position:absolute;top:.7rem;right:.7rem}

/* ============================================================
   11. TICKETS
   ============================================================ */
.ticket-grid{display:grid;grid-template-columns:minmax(0,1.15fr) minmax(0,1fr);gap:1.4rem;align-items:start}
@media (max-width:940px){.ticket-grid{grid-template-columns:1fr}}
form.booking{padding:1.5rem}
.fset{border:0;padding:0;margin:0 0 1.4rem}
.fset legend{font-family:var(--font-mono);font-size:.62rem;letter-spacing:.22em;text-transform:uppercase;
  color:var(--brass);padding:0;margin-bottom:.7rem}
.two{display:grid;grid-template-columns:1fr 1fr;gap:.9rem}
@media (max-width:560px){.two{grid-template-columns:1fr}}
.field label{display:block;font-family:var(--font-mono);font-size:.6rem;letter-spacing:.15em;
  text-transform:uppercase;color:var(--muted);margin-bottom:.35rem}
.field select,.field input[type="text"]{
  width:100%;background:rgba(0,0,0,.34);border:1px solid var(--line);border-radius:7px;
  padding:.66rem .8rem;font-size:.92rem;transition:.22s;appearance:none}
.field select{background-image:linear-gradient(45deg,transparent 50%,var(--brass) 50%),linear-gradient(135deg,var(--brass) 50%,transparent 50%);
  background-position:calc(100% - 18px) 52%,calc(100% - 13px) 52%;background-size:5px 5px,5px 5px;background-repeat:no-repeat}
.field select:focus,.field input:focus{border-color:var(--accent);box-shadow:0 0 0 3px color-mix(in srgb,var(--accent) 14%,transparent);outline:none}
.radios{display:grid;gap:.5rem}
.radio{display:flex;align-items:center;gap:.8rem;border:1px solid var(--line);border-radius:8px;padding:.7rem .85rem;cursor:pointer;transition:.22s;background:rgba(0,0,0,.2)}
.radio:hover{border-color:var(--accent-3)}
.radio input{accent-color:var(--accent);width:16px;height:16px;flex:none}
.radio .rt{flex:1;min-width:0}
.radio .rt b{display:block;font-family:var(--font-display);font-size:1rem}
.radio .rt small{display:block;font-family:var(--font-mono);font-size:.58rem;letter-spacing:.13em;text-transform:uppercase;color:var(--muted)}
.radio .rp{font-family:var(--font-mono);font-size:.85rem;color:var(--brass-hi);white-space:nowrap}
.radio:has(input:checked){border-color:var(--accent);background:color-mix(in srgb,var(--accent) 9%,transparent)}
.stepper{display:inline-flex;align-items:center;border:1px solid var(--line);border-radius:8px;overflow:hidden;background:rgba(0,0,0,.3)}
.stepper button{appearance:none;background:transparent;border:0;width:40px;height:40px;cursor:pointer;color:var(--brass);font-size:1.15rem;transition:.2s}
.stepper button:hover{background:color-mix(in srgb,var(--accent) 14%,transparent);color:var(--accent)}
.stepper output{min-width:44px;text-align:center;font-family:var(--font-mono);font-size:1.05rem;font-variant-numeric:tabular-nums}
.addons{display:grid;grid-template-columns:repeat(auto-fit,minmax(215px,1fr));gap:.5rem}
.addon{display:flex;gap:.7rem;align-items:flex-start;border:1px solid var(--line);border-radius:8px;padding:.65rem .8rem;cursor:pointer;transition:.22s;background:rgba(0,0,0,.2)}
.addon:hover{border-color:var(--accent-3)}
.addon:has(input:checked){border-color:var(--accent);background:color-mix(in srgb,var(--accent) 8%,transparent)}
.addon input{accent-color:var(--accent);margin-top:.2rem;flex:none}
.addon b{display:block;font-size:.86rem;font-weight:600}
.addon small{display:block;font-family:var(--font-mono);font-size:.58rem;letter-spacing:.1em;color:var(--muted);text-transform:uppercase}
.addon .cost{margin-left:auto;font-family:var(--font-mono);font-size:.76rem;color:var(--brass-hi);white-space:nowrap}

.fare{padding:1.4rem;position:sticky;top:84px}
.fare h3{font-size:1.25rem;margin-bottom:.15rem}
.fare .sub{font-family:var(--font-mono);font-size:.6rem;letter-spacing:.18em;text-transform:uppercase;color:var(--muted)}
.fare-lines{margin:1.2rem 0;border-top:1px dashed var(--line);padding-top:1rem;display:grid;gap:.5rem}
.fl{display:flex;justify-content:space-between;gap:1rem;font-family:var(--font-mono);font-size:.79rem}
.fl span:first-child{color:var(--muted)}
.fl span:last-child{font-variant-numeric:tabular-nums;color:#dbe7ef}
html[data-realm="takamagahara"] .fl span:last-child{color:#eadcd5}
.fl.total{border-top:1px solid var(--line);margin-top:.5rem;padding-top:.75rem;font-size:1.05rem}
.fl.total span:first-child{color:var(--text);letter-spacing:.14em;text-transform:uppercase;font-size:.66rem;align-self:center}
.fl.total span:last-child{color:var(--brass-hi);font-size:1.5rem;font-family:var(--font-display)}
.note{font-family:var(--font-mono);font-size:.6rem;letter-spacing:.1em;color:var(--muted);line-height:1.7;margin-top:.9rem}

/* issued ticket */
.ticket-out{margin-top:1.2rem;perspective:1200px}
.stub{
  --stub-bg:linear-gradient(155deg,#f6ecd6,#e6d6b4 60%,#d8c39a);
  background:var(--stub-bg);color:#2b2114;border-radius:10px;position:relative;overflow:hidden;
  display:grid;grid-template-columns:1fr 118px;box-shadow:0 30px 60px -30px #000;
  transform:rotateX(-14deg) scale(.96);opacity:0;transition:transform .6s var(--ease),opacity .5s;
}
.stub.show{transform:none;opacity:1}
.stub .main{padding:1.15rem 1.3rem}
.stub .perf{border-left:2px dashed rgba(43,33,20,.4);position:relative;display:grid;place-items:center;padding:.8rem .5rem}
.stub .perf::before,.stub .perf::after{content:"";position:absolute;left:-9px;width:16px;height:16px;border-radius:50%;background:var(--panel)}
.stub .perf::before{top:-8px}.stub .perf::after{bottom:-8px}
.stub .co{font-family:var(--font-mono);font-size:.55rem;letter-spacing:.24em;text-transform:uppercase;color:#7a5f2c}
.stub h4{font-family:var(--font-display);font-size:1.28rem;margin:.35rem 0 .1rem;color:#241a0d}
.stub .route{display:flex;align-items:center;gap:.6rem;margin:.85rem 0;font-family:var(--font-display);font-size:1.05rem;flex-wrap:wrap}
.stub .route .arw{flex:1;min-width:34px;height:1px;background:repeating-linear-gradient(90deg,#8a6a2c 0 5px,transparent 5px 9px);position:relative}
.stub .route .arw::after{content:"";position:absolute;right:-1px;top:-3.5px;border:4px solid transparent;border-left-color:#8a6a2c}
.stub .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(88px,1fr));gap:.7rem;margin-top:.85rem;
  border-top:1px solid rgba(43,33,20,.2);padding-top:.8rem}
.stub .grid div span{display:block;font-family:var(--font-mono);font-size:.52rem;letter-spacing:.16em;text-transform:uppercase;color:#8a7040}
.stub .grid div b{font-family:var(--font-mono);font-size:.86rem;color:#2b2114}
.stub .seal{width:74px;height:74px;border-radius:50%;display:grid;place-items:center;text-align:center;
  background:radial-gradient(circle at 34% 30%,#d9553c,#8e2a19);color:#ffe9df;
  font-family:var(--font-display);font-size:.62rem;line-height:1.25;letter-spacing:.06em;
  box-shadow:0 4px 12px rgba(0,0,0,.35),0 0 0 3px rgba(255,255,255,.14) inset;transform:scale(0) rotate(-24deg);transition:transform .5s var(--ease) .35s}
.stub.show .seal{transform:scale(1) rotate(-11deg)}
.stub .bars{display:flex;gap:2px;align-items:flex-end;height:34px;margin-top:.9rem}
.stub .bars i{display:block;width:2px;background:#2b2114;border-radius:1px}
.stub .ser{font-family:var(--font-mono);font-size:.58rem;letter-spacing:.14em;color:#7a5f2c;margin-top:.4rem}
@media (max-width:520px){.stub{grid-template-columns:1fr}.stub .perf{border-left:0;border-top:2px dashed rgba(43,33,20,.4);padding:.9rem}
  .stub .perf::before{top:auto;left:-8px;bottom:calc(50% - 8px)}.stub .perf::after{left:auto;right:-8px;top:calc(50% - 8px);bottom:auto}}

/* ============================================================
   12. FOOTER
   ============================================================ */
footer{border-top:1px solid var(--line);background:var(--bg-2);padding:3rem 0 2rem;position:relative;z-index:2}
.foot-grid{display:grid;grid-template-columns:minmax(0,1.4fr) repeat(2,minmax(0,1fr));gap:2rem}
@media (max-width:760px){.foot-grid{grid-template-columns:1fr}}
.foot-grid h4{font-family:var(--font-mono);font-size:.62rem;letter-spacing:.2em;text-transform:uppercase;color:var(--brass);margin-bottom:.8rem}
.foot-grid ul{list-style:none;margin:0;padding:0;display:grid;gap:.45rem}
.foot-grid li a,.foot-grid li span{color:var(--muted);font-size:.86rem}
.foot-grid li a:hover{color:var(--accent)}
.colophon{margin-top:2.5rem;padding-top:1.2rem;border-top:1px solid var(--line);display:flex;flex-wrap:wrap;
  gap:.6rem 1.6rem;justify-content:space-between;font-family:var(--font-mono);font-size:.6rem;
  letter-spacing:.14em;text-transform:uppercase;color:var(--muted)}

/* ============================================================
   13. REVEAL + MOTION PREFS + PRINT
   ============================================================ */
[data-reveal]{opacity:0;transform:translateY(22px);transition:opacity .7s var(--ease),transform .7s var(--ease)}
[data-reveal].in{opacity:1;transform:none}
@media (prefers-reduced-motion:reduce){
  html{scroll-behavior:auto}
  *,*::before,*::after{animation-duration:.001ms!important;animation-iteration-count:1!important;transition-duration:.001ms!important}
  [data-reveal]{opacity:1;transform:none}
}
@media print{
  body>*:not(.ticket-out),.grain,.progress{display:none!important}
  body{background:#fff;color:#000}
  .ticket-out{margin:0;position:static}
}
</style>
</head>
<body>
<a class="skip" href="#main">Skip to the platform</a>
<div class="grain" aria-hidden="true"></div>
<div class="progress" id="progress" aria-hidden="true"></div>

<!-- ══════════ HEADER ══════════ -->
<header class="nav">
  <div class="wrap nav-in">
    <a class="brand" href="#top" aria-label="Yggdrasil Line, home">
      <svg class="mk" viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
        <circle cx="24" cy="24" r="19"/><path d="M24 5v38M5 24h38"/>
        <path d="M11 11l26 26M37 11L11 37"/><circle cx="24" cy="24" r="7" fill="currentColor" fill-opacity=".14"/>
        <path d="M17 31l7-14 7 14" stroke-linejoin="round"/>
      </svg>
      <span><b>Yggdrasil Line</b><small>Æther Railway Co. · Est. Ragnarök I</small></span>
    </a>
    <nav aria-label="Primary">
      <ul class="nav-links" id="navLinks">
        <li><a href="#departures">Departures</a></li>
        <li><a href="#line">The Line</a></li>
        <li><a href="#patrons">Patrons</a></li>
        <li><a href="#tickets">Tickets</a></li>
      </ul>
    </nav>
    <div class="nav-tools">
      <div class="seg" role="group" aria-label="Realm livery">
        <button type="button" data-realm-set="asgard" aria-pressed="true">Asgard</button>
        <button type="button" data-realm-set="takamagahara" aria-pressed="false">高天原</button>
      </div>
      <button type="button" class="icon-btn" id="soundBtn" aria-pressed="false" title="Engine ambience (synthesised)" aria-label="Toggle engine ambience">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
          <path d="M4 9v6h4l5 4V5L8 9H4z"/><path d="M17 8.5a5 5 0 010 7" id="wave1"/><path d="M19.5 6a8.5 8.5 0 010 12" id="wave2"/>
        </svg>
      </button>
      <button type="button" class="icon-btn burger" id="burger" aria-expanded="false" aria-controls="navLinks" aria-label="Menu">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
      </button>
    </div>
  </div>
</header>

<main id="main">
<!-- ══════════ HERO ══════════ -->
<section class="hero" id="top">
  <div class="sky" id="sky" aria-hidden="true"></div>
  <div class="stars" aria-hidden="true"></div>
  <canvas id="ambient" aria-hidden="true"></canvas>
  <div class="mountains" aria-hidden="true">
    <svg viewBox="0 0 1440 220" preserveAspectRatio="none">
      <path d="M0 220 L120 96 L210 150 L330 42 L470 158 L560 108 L700 190 L820 74 L960 166 L1080 112 L1210 182 L1330 88 L1440 160 L1440 220Z" fill="currentColor"/>
    </svg>
  </div>

  <div class="wrap hero-copy">
    <p class="eyebrow" id="heroEyebrow">Nine realms · One gauge · Eight million kami</p>
    <h1>The line between worlds runs on <em>steam</em> and oath.</h1>
    <p class="lede">Departing Midgard Exchange at the turning of the tide, the <em>Gungnir&nbsp;No.&nbsp;7</em> climbs the frost yards of Niflheim, crosses the Floating Bridge of Heaven under a changed bogie, and arrives at Yomi Terminal before the lanterns go out. Coal from Múspell. Water from Mímir's well. Timetable guaranteed by two pantheons.</p>
    <div class="hero-cta">
      <a class="btn" href="#tickets">Book a berth</a>
      <a class="btn ghost" href="#departures">See departures</a>
    </div>
    <div class="stat-strip">
      <div class="stat"><b data-count="10">0</b><span>Stations</span></div>
      <div class="stat"><b data-count="9">0</b><span>Realms served</span></div>
      <div class="stat"><b data-count="847">0</b><span>Leagues of rail</span></div>
      <div class="stat"><b data-count="2">0</b><span>Pantheons</span></div>
    </div>
  </div>

  <div class="track-zone">
    <div class="track" aria-hidden="true"></div>
<div class="loco" aria-hidden="true">
  <svg viewBox="0 0 340 170">
    <defs>
      <linearGradient id="locoBody" x1="0" y1="0" x2="0" y2="1">
        <stop offset="0" stop-color="#1a2530"/><stop offset=".55" stop-color="#0b1119"/><stop offset="1" stop-color="#05080c"/>
      </linearGradient>
    </defs>
    <ellipse cx="175" cy="152" rx="150" ry="6" fill="#000" opacity=".5"/>
    <rect x="58" y="46" width="92" height="68" rx="7" fill="url(#locoBody)" stroke="var(--brass)" stroke-width="1.6"/>
    <rect x="52" y="38" width="104" height="11" rx="5" fill="#0b1119" stroke="var(--brass)" stroke-width="1.4"/>
    <rect x="96" y="56" width="30" height="22" rx="4" fill="#0e2233" stroke="var(--brass-lo)"/>
    <rect x="100" y="60" width="22" height="14" rx="3" fill="var(--accent)" opacity=".55"/>
    <rect x="146" y="62" width="152" height="52" rx="12" fill="url(#locoBody)" stroke="var(--brass)" stroke-width="1.6"/>
    <path d="M186 62v52M226 62v52M266 62v52" stroke="var(--brass-lo)" stroke-width="1.2" opacity=".8"/>
    <circle cx="298" cy="88" r="26" fill="#0b1119" stroke="var(--brass)" stroke-width="1.6"/>
    <circle cx="298" cy="88" r="10" fill="none" stroke="var(--brass-lo)" stroke-width="1.4"/>
    <rect x="288" y="44" width="20" height="18" rx="4" fill="#0b1119" stroke="var(--brass)" stroke-width="1.4"/>
    <circle cx="298" cy="53" r="9" fill="var(--accent)" opacity=".25"/>
    <circle cx="298" cy="53" r="5" fill="var(--accent)"/>
    <path d="M238 62 V40 L231 26 H265 L258 40 V62 Z" fill="#0b1119" stroke="var(--brass)" stroke-width="1.6"/>
    <path d="M196 62a13 13 0 0 1 26 0Z" fill="#1a2530" stroke="var(--brass)" stroke-width="1.4"/>
    <path d="M164 62a10 10 0 0 1 20 0Z" fill="#1a2530" stroke="var(--brass)" stroke-width="1.4"/>
    <rect x="30" y="112" width="286" height="10" rx="3" fill="#0b1119" stroke="var(--brass)" stroke-width="1.5"/>
    <path d="M316 122 L334 150 H306 Z" fill="#0b1119" stroke="var(--brass)" stroke-width="1.5"/>
    <g class="wheel"><circle cx="104" cy="128" r="22" fill="#0a1119" stroke="var(--brass-hi)" stroke-width="2.4"/><path d="M104 108v40M84 128h40M90 114l28 28M118 114l-28 28" stroke="var(--brass)" stroke-width="1.6"/><circle cx="104" cy="128" r="4.5" fill="var(--brass-hi)"/></g>
    <g class="wheel"><circle cx="158" cy="128" r="22" fill="#0a1119" stroke="var(--brass-hi)" stroke-width="2.4"/><path d="M158 106v44M136 128h44M142 112l32 32M174 112l-32 32" stroke="var(--brass)" stroke-width="1.6"/><circle cx="158" cy="128" r="4.5" fill="var(--brass-hi)"/></g>
    <g class="wheel"><circle cx="222" cy="136" r="14" fill="#0a1119" stroke="var(--brass-hi)" stroke-width="2"/><path d="M222 124v24M210 136h24" stroke="var(--brass)" stroke-width="1.4"/><circle cx="222" cy="136" r="3" fill="var(--brass-hi)"/></g>
    <g class="wheel"><circle cx="268" cy="136" r="14" fill="#0a1119" stroke="var(--brass-hi)" stroke-width="2"/><path d="M268 124v24M256 136h24" stroke="var(--brass)" stroke-width="1.4"/><circle cx="268" cy="136" r="3" fill="var(--brass-hi)"/></g>
    <g class="rod"><rect x="96" y="124.5" width="78" height="7" rx="3.5" fill="#0b1119" stroke="var(--brass-hi)" stroke-width="1.3"/><circle cx="112" cy="128" r="4" fill="var(--brass-hi)"/><circle cx="166" cy="128" r="4" fill="var(--brass-hi)"/></g>
  </svg>
  <span id="funnelAnchor"></span>
</div>
    <div class="scroll-hint" aria-hidden="true">Platform below</div>
  </div>

  <!-- CAB CONSOLE -->
  <div class="wrap console">
    <div class="plate riveted console-in" id="console">
      <div class="alarm" id="alarm" aria-hidden="true"></div>
      <div class="gauge">
        <svg viewBox="0 0 120 120" aria-hidden="true">
          <defs>
            <linearGradient id="brassG" x1="0" y1="0" x2="0" y2="1">
              <stop offset="0" stop-color="#f2d68f"/><stop offset=".5" stop-color="#c9973f"/><stop offset="1" stop-color="#7a5a20"/>
            </linearGradient>
          </defs>
          <circle cx="60" cy="60" r="56" fill="#080d13" stroke="url(#brassG)" stroke-width="5"/>
          <circle cx="60" cy="60" r="46" fill="none" stroke="var(--line)" stroke-width="1"/>
          <g stroke="var(--muted)" stroke-width="1.6" stroke-linecap="round">
            <path d="M60 20v7"/><path d="M88 32l-4 6"/><path d="M100 60h-7"/><path d="M88 88l-4-6"/>
            <path d="M32 32l4 6"/><path d="M20 60h7"/><path d="M32 88l4-6"/>
          </g>
          <path d="M88 32A38 38 0 0198 60" fill="none" stroke="#ff6a4d" stroke-width="3" stroke-linecap="round" opacity=".8"/>
          <g class="needle" id="needle">
            <path d="M60 60 L60 24" stroke="var(--accent)" stroke-width="2.6" stroke-linecap="round"/>
            <path d="M56 60 L60 66 L64 60Z" fill="var(--accent)"/>
          </g>
          <circle cx="60" cy="60" r="6" fill="url(#brassG)" stroke="#3b2c11"/>
        </svg>
        <div class="gauge-val" id="gaugeVal">0</div>
        <div class="gauge-lbl">Boiler · bar</div>
      </div>

      <div class="controls">
        <div class="row">
          <label class="eyebrow" for="throttle" style="flex:none">Regulator</label>
          <input class="throttle" type="range" id="throttle" min="0" max="100" value="42" step="1"
                 aria-describedby="throttleHelp" aria-valuetext="42 percent regulator">
        </div>
        <div class="readouts">
          <div class="readout"><span>Speed</span><b id="roSpeed">0</b></div>
          <div class="readout"><span>Firebox</span><b id="roFire">0</b></div>
          <div class="readout"><span>Water</span><b id="roWater">0</b></div>
          <div class="readout"><span>Aether flux</span><b id="roFlux">0</b></div>
        </div>
        <div class="bar" id="pressBar" role="img" aria-label="Boiler pressure"><i></i></div>
        <p class="note" id="throttleHelp">Opening the regulator past 88% trips the safety valve. The stoker is a dwarf; he does not appreciate it.</p>
      </div>

      <div class="console-actions">
        <button class="btn ghost" type="button" id="whistleBtn">Sound whistle</button>
        <button class="btn ghost" type="button" id="ventBtn">Vent steam</button>
      </div>
    </div>
  </div>
</section>

<!-- ══════════ TICKER ══════════ -->
<div class="ticker">
  <div class="wrap ticker-in">
    <span class="tag">Line notice</span>
    <p id="tickerText">Heimdallr reminds passengers: the Gjallarhorn sounds ninety seconds before departure, not after.</p>
  </div>
</div>

<!-- ══════════ DEPARTURES ══════════ -->
<section id="departures">
  <div class="wrap">
    <div class="sect-head" data-reveal>
      <div>
        <p class="eyebrow">Platform 3 · Split-flap</p>
        <h2>Departures</h2>
      </div>
      <p>Every service on the Yggdrasil Line, kept to the minute by a Norn in the signalling box and audited each quarter by Ōkuninushi. Delays are attributed to weather, giants, or the tide of the dead.</p>
    </div>

    <div class="board-head" data-reveal>
      <div class="clockbox">
        <b id="clock">--:--:--</b>
        <span>Station clock<br><span id="clockDate">—</span></span>
      </div>
      <div class="filters" role="group" aria-label="Filter departures">
        <button class="chip" type="button" data-filter="all" aria-pressed="true">All</button>
        <button class="chip" type="button" data-filter="norse" aria-pressed="false">Realm-bound</button>
        <button class="chip" type="button" data-filter="japan" aria-pressed="false">Kami-bound</button>
        <button class="chip" type="button" data-filter="delayed" aria-pressed="false">Delayed</button>
      </div>
    </div>

    <div class="plate riveted board" data-reveal>
      <div class="brow head" aria-hidden="true">
        <span>Destination</span><span>Consist</span><span>Departs</span><span>In</span><span>Road</span><span>Status</span>
      </div>
      <ul id="boardList" style="list-style:none;margin:0;padding:0"></ul>
      <div class="board-foot">
        <span id="boardCount">— services</span>
        <span>Auto-updating · <span id="lastSync">just now</span></span>
        <button class="chip" type="button" id="resync">Resync timetable</button>
      </div>
      <p class="sr" aria-live="polite" id="boardLive"></p>
    </div>
  </div>
</section>

<!-- ══════════ THE LINE ══════════ -->
<section id="line">
  <div class="wrap">
    <div class="sect-head" data-reveal>
      <div>
        <p class="eyebrow">Midgard Exchange → Yomi Terminal</p>
        <h2>The Line</h2>
      </div>
      <p>Ten stations, two cosmologies, one very long bridge. Select a station to read its notice; run a service to watch the locomotive work the road.</p>
    </div>

    <div class="line-grid">
      <div class="plate riveted mapbox" data-reveal>
        <svg viewBox="0 0 1200 400" role="img" aria-labelledby="mapTitle mapDesc">
          <title id="mapTitle">Route map of the Yggdrasil Line</title>
          <desc id="mapDesc">A serpentine railway running from Midgard Exchange in the west through the Norse realms, across the Floating Bridge of Heaven, and east through the kami realms to Yomi Terminal.</desc>
          <defs>
            <linearGradient id="railGrad" x1="0" y1="0" x2="1" y2="0">
              <stop offset="0" style="stop-color:var(--accent)"/>
              <stop offset=".5" style="stop-color:var(--accent-2)"/>
              <stop offset="1" style="stop-color:var(--accent)"/>
            </linearGradient>
            <radialGradient id="headG"><stop offset="0" stop-color="#fff8dc" stop-opacity=".95"/><stop offset="1" stop-color="#fff8dc" stop-opacity="0"/></radialGradient>
          </defs>
          <g opacity=".5" aria-hidden="true">
            <text x="120" y="52" font-family="var(--font-mono)" font-size="11" letter-spacing="4" fill="var(--accent)">NINE REALMS</text>
            <text x="900" y="52" font-family="var(--font-mono)" font-size="11" letter-spacing="4" fill="var(--accent-2)">八百万神</text>
            <path d="M600 20V380" stroke="var(--line)" stroke-dasharray="3 8"/>
          </g>
          <path id="railPath" class="rail-bg" d="M30 280 C130 280,150 170,260 168 C370 166,380 288,500 292 C620 296,640 190,760 186 C880 182,900 296,1010 300 C1090 303,1120 210,1172 196"/>
          <path class="rail-ties" d="M30 280 C130 280,150 170,260 168 C370 166,380 288,500 292 C620 296,640 190,760 186 C880 182,900 296,1010 300 C1090 303,1120 210,1172 196"/>
          <path class="rail" d="M30 280 C130 280,150 170,260 168 C370 166,380 288,500 292 C620 296,640 190,760 186 C880 182,900 296,1010 300 C1090 303,1120 210,1172 196"/>
          <g id="bridgeMark"></g>
          <g id="stationLayer"></g>
          <g id="trainMarker" opacity="0">
            <ellipse cx="0" cy="16" rx="30" ry="7" fill="#000" opacity=".45"/>
            <path d="M-30 8 L26 8 L32 -2 L32 -12 L14 -12 L10 -22 L-6 -22 L-10 -12 L-26 -12 Z" fill="#0b1119" stroke="var(--brass)" stroke-width="1.5"/>
            <circle cx="-18" cy="10" r="6" fill="#0b1119" stroke="var(--brass-hi)" stroke-width="1.4"/>
            <circle cx="4" cy="10" r="6" fill="#0b1119" stroke="var(--brass-hi)" stroke-width="1.4"/>
            <circle cx="24" cy="10" r="4.5" fill="#0b1119" stroke="var(--brass-hi)" stroke-width="1.4"/>
            <circle cx="34" cy="-16" r="12" fill="url(#headG)"/>
          </g>
        </svg>
        <div class="map-legend">
          <span><i></i>Running line</span>
          <span><i class="b"></i>Bogie exchange</span>
          <span>10 stations · 847 leagues · gauge 1 435 mm → 1 067 mm at the Bridge</span>
        </div>
      </div>

      <aside class="plate riveted station-panel" data-reveal id="stationPanel" aria-live="polite">
        <div class="glyph" id="spGlyph">ᛗ</div>
        <h3 id="spName">Midgard Exchange</h3>
        <p class="eyebrow" id="spKind" style="margin-top:.3rem">Origin · Norse</p>
        <div class="meta">
          <span id="spElev">Elev. 12 m</span>
          <span id="spDist">0 leagues</span>
          <span id="spRoad">Road 1</span>
        </div>
        <p class="lore" id="spLore">—</p>
        <ul class="svc" id="spSvc"></ul>
        <div class="run-bar">
          <div style="display:flex;gap:.5rem;flex-wrap:wrap">
            <button class="btn" type="button" id="runBtn">Run stopping service</button>
            <button class="btn ghost" type="button" id="expressBtn">Express</button>
            <button class="btn ghost" type="button" id="stopBtn" disabled>Halt</button>
          </div>
          <div class="bar" style="margin-top:.9rem"><i id="runProgress"></i></div>
          <p class="note" id="runNote">Locomotive standing at the starting signal.</p>
        </div>
        <div class="journey-log" id="journeyLog"></div>
      </aside>
    </div>
  </div>
</section>

<!-- ══════════ PATRONS ══════════ -->
<section id="patrons">
  <div class="wrap">
    <div class="sect-head" data-reveal>
      <div>
        <p class="eyebrow">The board of directors, roughly</p>
        <h2>Patrons of the Line</h2>
      </div>
      <p>No service runs without a blessing signed at both ends. The Æsir warrant the boilers; the kami warrant the weather. Where their jurisdictions overlap, the timetable suffers.</p>
    </div>
    <div class="filters" style="margin-bottom:1.2rem" role="group" aria-label="Filter patrons" data-reveal>
      <button class="chip" type="button" data-pan="all" aria-pressed="true">All patrons</button>
      <button class="chip" type="button" data-pan="norse" aria-pressed="false">Norse</button>
      <button class="chip" type="button" data-pan="kami" aria-pressed="false">Kami</button>
    </div>
    <div class="patron-grid" id="patronGrid" data-reveal></div>
  </div>
</section>

<!-- ══════════ TICKETS ══════════ -->
<section id="tickets">
  <div class="wrap">
    <div class="sect-head" data-reveal>
      <div>
        <p class="eyebrow">Booking office · Open all hours</p>
        <h2>Take a Ticket</h2>
      </div>
      <p>Fares are quoted in aurum and settled at the barrier. Children under the height of a sword-hilt travel free; the dead travel at their own risk and full fare.</p>
    </div>

    <div class="ticket-grid">
      <form class="plate riveted booking" id="bookingForm" data-reveal novalidate>
        <fieldset class="fset">
          <legend>Journey</legend>
          <div class="two">
            <div class="field">
              <label for="fromSel">From</label>
              <select id="fromSel" name="from"></select>
            </div>
            <div class="field">
              <label for="toSel">To</label>
              <select id="toSel" name="to"></select>
            </div>
          </div>
        </fieldset>

        <fieldset class="fset">
          <legend>Accommodation</legend>
          <div class="radios" id="classList"></div>
        </fieldset>

        <fieldset class="fset">
          <legend>Passengers</legend>
          <div class="stepper">
            <button type="button" id="paxMinus" aria-label="Fewer passengers">−</button>
            <output id="paxOut" for="paxMinus paxPlus" aria-live="polite">2</output>
            <button type="button" id="paxPlus" aria-label="More passengers">+</button>
          </div>
        </fieldset>

        <fieldset class="fset">
          <legend>Provisions &amp; warrants</legend>
          <div class="addons" id="addonList"></div>
        </fieldset>

        <button class="btn" type="submit" id="issueBtn" style="width:100%">Issue ticket</button>
        <p class="note" id="formNote">Tickets are non-transferable between the living and the otherwise.</p>
      </form>

      <div data-reveal>
        <div class="plate riveted fare">
          <h3>Fare estimate</h3>
          <p class="sub" id="fareRoute">Midgard Exchange → Yomi Terminal</p>
          <div class="fare-lines" id="fareLines" aria-live="polite"></div>
          <p class="note">Night services (22:00–05:00 station time) carry a 12% watchman's levy. A bogie-exchange fee applies to any journey crossing the Floating Bridge of Heaven.</p>
          <button class="btn ghost" type="button" id="printBtn" style="margin-top:1rem;width:100%">Print stub</button>
        </div>

        <div class="ticket-out" id="ticketOut" aria-live="polite"></div>
      </div>
    </div>
  </div>
</section>
</main>

<!-- ══════════ FOOTER ══════════ -->
<footer>
  <div class="wrap">
    <div class="foot-grid">
      <div>
        <h4>Yggdrasil Line</h4>
        <p style="color:var(--muted);font-size:.9rem;max-width:44ch">A joint undertaking of the Æsir and the kami of Takamagahara, incorporated after the second flooding of the world. Rolling stock maintained at Múspell Forge Works. Signals lit by foxfire.</p>
      </div>
      <div>
        <h4>Departures</h4>
        <ul>
          <li><a href="#departures">Live board</a></li>
          <li><a href="#line">Route map</a></li>
          <li><a href="#tickets">Fares &amp; classes</a></li>
          <li><span>Freight &amp; funeral traffic</span></li>
        </ul>
      </div>
      <div>
        <h4>The Company</h4>
        <ul>
          <li><a href="#patrons">Patrons</a></li>
          <li><span>By-laws of the nine realms</span></li>
          <li><span>Lost property (Helheim desk)</span></li>
          <li><span>Accessibility &amp; ramp service</span></li>
        </ul>
      </div>
    </div>
    <div class="colophon">
      <span>© Year 12 of the reforging · Yggdrasil Line Æther Railway Co.</span>
      <span>The company accepts no liability for passage beyond Yomi Terminal.</span>
      <span>Built with steam, ink and vanilla JavaScript</span>
    </div>
  </div>
</footer>

<!-- ══════════ MODAL ══════════ -->
<div class="modal" id="modal" role="dialog" aria-modal="true" aria-labelledby="mTitle" hidden>
  <div class="plate riveted modal-card" id="modalCard">
    <button class="icon-btn modal-close" type="button" id="modalClose" aria-label="Close">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="M5 5l14 14M19 5L5 19"/></svg>
    </button>
    <div class="head">
      <svg class="sigil" id="mSigil" viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"></svg>
      <div>
        <h3 id="mTitle">—</h3>
        <p class="eyebrow" id="mEp" style="margin-top:.35rem">—</p>
      </div>
    </div>
    <div class="body" id="mBody"></div>
    <dl class="kv" id="mKv"></dl>
    <button class="btn" type="button" id="mInvoke" style="margin-top:1.4rem">Invoke for the journey</button>
  </div>
</div>

<script>
(() => {
'use strict';

/* ==========================================================
   UTILITIES
   ========================================================== */
const $  = (s, r = document) => r.querySelector(s);
const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));
const clamp = (v, a, b) => Math.min(b, Math.max(a, v));
const rand  = (a, b) => a + Math.random() * (b - a);
const randInt = (a, b) => Math.floor(rand(a, b + 1));
const pick  = arr => arr[Math.floor(Math.random() * arr.length)];
const pad2  = n => String(n).padStart(2, '0');
const REDUCED = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

/* ==========================================================
   DATA
   ========================================================== */
const STATIONS = [
  { name:'Midgard Exchange', glyph:'ᛗ', pan:'norse', kind:'Origin terminus', elev:'12 m', road:'Roads 1–6',
    svc:['Customs','Coal & aether','Left luggage','Ramp service'],
    lore:'The human end of the line, built on the site of an older ford. Passengers board under a clock that runs four seconds slow, which the company has never corrected because the Norns prefer it that way.' },
  { name:'Bifröst Junction', glyph:'ᛒ', pan:'norse', kind:'Junction', elev:'440 m', road:'Roads 2–5',
    svc:['Platform change','Heimdallr signal box','Refreshment'],
    lore:'Where the rainbow road was laid on steel in the second reforging. Seven tracks cross here; the eighth is reserved for a train that has not yet been built. Heimdallr blows the horn ninety seconds early, always.' },
  { name:'Asgard Central', glyph:'ᚨ', pan:'norse', kind:'Principal station', elev:'1 120 m', road:'Roads 1–9',
    svc:['Valhalla lounge','Aether works','Baggage','Oath desk'],
    lore:'Granite, brass, and one hall roofed entirely in shields. Every ticket sold here is sworn to rather than merely purchased, which makes refunds a matter of theology.' },
  { name:'Jötunheim Pass', glyph:'ᛃ', pan:'norse', kind:'Mountain halt', elev:'2 880 m', road:'Road 1',
    svc:['Snow plough','Frost depot','Sheep crossing'],
    lore:'A single platform cut into the rock, staffed by three men and one very patient goat. Trains sound the horn for eleven seconds here; the echo is counted as a twelfth.' },
  { name:'Niflheim Frost Yard', glyph:'ᚺ', pan:'norse', kind:'Stabling & works', elev:'−60 m', road:'Roads 1–4',
    svc:['Icebreaker','Cold stabling','Boiler washing'],
    lore:'Eleven roads of standing mist where locomotives are washed in meltwater and left to cool. Skadi oversees the ploughs. Nothing rusts here; nothing else happens either.' },
  { name:'Ame-no-Ukihashi Bridge', glyph:'橋', pan:'threshold', kind:'Gauge change · border', elev:'6 400 m', road:'Road 1 (exchange)',
    svc:['Bogie exchange','Dual customs','Bridge tea house'],
    lore:'The Floating Bridge of Heaven, laid rail-on-rail across the seam between cosmologies. Here the 1 435 mm running gear is lifted off and 1 067 mm fitted beneath, and passengers change the direction they face.' },
  { name:'Takamagahara Sky Shrine', glyph:'天', pan:'kami', kind:'Principal station', elev:'5 900 m', road:'Roads 1–8',
    svc:['Shrine approach','Lantern gallery','Silent carriage'],
    lore:'A thousand vermilion gates, then a platform of pale cedar. Amaterasu keeps the headlamp depot; the first train out each morning is lit from her mirror and no other flame.' },
  { name:'Fushimi Fox Junction', glyph:'稲', pan:'kami', kind:'Junction', elev:'210 m', road:'Roads 2–6',
    svc:['Foxfire signals','Inari freight','Street food'],
    lore:'Signals here are not electric. Small white foxes carry lamps between the boxes, and the timetable accounts for their enthusiasm. Freight sidings handle rice, sake, and unexplained quantities of straw.' },
  { name:'Izumo Crossing', glyph:'出', pan:'kami', kind:'Coastal junction', elev:'8 m', road:'Roads 1–5',
    svc:['Tidal platform','Ōkuninushi works','Ferry link'],
    lore:'Where the year of gods arrives by rail instead of by sea. Ōkuninushi built the tie-beams himself and still signs off every track renewal, in duplicate, with a very fine brush.' },
  { name:'Yomi Terminal', glyph:'黄', pan:'kami', kind:'Terminus', elev:'−900 m', road:'Road 1 (one way)',
    svc:['Lantern hall','Final desk','No return service'],
    lore:'The end of the line, and the reason the company prints the return leg in a different colour. Izanami receives passengers at the barrier. The platform clock has only one hand.' }
];
const BRIDGE_IDX = 5;

const EXTRA_DESTS = [
  { name:'Múspell Forge Works', glyph:'ᛋ', pan:'norse' },
  { name:'Álfheim Glowhalt',     glyph:'ᛖ', pan:'norse' },
  { name:'Helheim Descent',      glyph:'ᛞ', pan:'norse' },
  { name:'Vanaheim Orchards',    glyph:'ᚹ', pan:'norse' },
  { name:'Ryūgū Tidal Platform', glyph:'龍', pan:'kami' },
  { name:'Suwa Storm Halt',      glyph:'諏', pan:'kami' },
  { name:'Kōya Skygate',         glyph:'高', pan:'kami' },
  { name:'Kurama Tengu Siding',  glyph:'鞍', pan:'kami' }
];

const CONSISTS = [
  '9-coach express','Loco + 4','Sleeper (11)','Mixed freight','Observation set',
  'Boiler + 2 + brake','Royal consist','Pilgrim special'
];

const CLASSES = [
  { id:'iron',  name:'Iron Bench',            note:'Third · open saloon, coal warmth',      mult:1.0, base:14 },
  { id:'brass', name:'Brass Coupé',           note:'Second · six berths, curtained',        mult:1.8, base:14 },
  { id:'valky', name:'Valkyrie Salon',        note:'First · mead service, panorama glass',  mult:3.2, base:14 },
  { id:'imperial', name:'Amaterasu Observation', note:'Imperial · mirror-lit dome, silent', mult:6.5, base:14 }
];

const ADDONS = [
  { id:'plough',   name:'Frost plough seat',   note:'Forward-facing, Niflheim legs', cost:9,  per:'seat' },
  { id:'lantern',  name:'Fox-lantern',         note:'Carried, returns itself',       cost:6,  per:'ticket' },
  { id:'mead',     name:'Mead & mochi service',note:'Two realms, one tray',          cost:18, per:'pax' },
  { id:'berth',    name:'Boggart sleeper berth',note:'Made twice, slept once',       cost:25, per:'pax' },
  { id:'yomi',     name:'Yomi return warranty',note:'Subject to the one-hand clock',  cost:40, per:'ticket' }
];

const PATRONS = [
  { id:'odin', pan:'norse', name:'Óðinn', ep:'The Far-Seeing · Route surveys',
    domain:'Alignment, wayleave and the pricing of land no one owns.',
    office:'Chief Engineer, in absentia',
    bless:'Two ravens walk the permanent way each dawn. What they see, the timetable knows.',
    long:'Óðinn gave an eye at Mímir’s well and received, among other things, an unnerving feel for gradients. Every curve on the Yggdrasil Line was set out by him personally, in fog, at night, with a length of knotted cord. He has never explained the ninth curve beyond Jötunheim Pass, and the surveyors who redrew it woke up in Niflheim yard.',
    kv:{ Patronage:['Alignment, wayleave, the gallows-road'], Residence:['Asgard Central, Road 9'], Sign:['Huginn & Muninn, permanent-way walkers'], Due:['One eye, paid in full'] },
    sigil:'<circle cx="24" cy="24" r="15"/><path d="M24 9v30M9 24h30"/><path d="M13.5 13.5l21 21M34.5 13.5l-21 21"/><circle cx="24" cy="24" r="5.5"/><path d="M24 3v4M24 41v4M3 24h4M41 24h4"/>',
    invoke:'Óðinn has walked the alignment. Survey marks refreshed.' },

  { id:'thor', pan:'norse', name:'Þórr', ep:'The Boiler Warden · Pressure & thunder',
    domain:'Fireboxes, safety valves, and the loud parts of the schedule.',
    office:'Superintendent of Steam',
    bless:'Strike the crown sheet twice and the draught holds. Strike it three times and you walk home.',
    long:'Þórr approves of a boiler that is slightly too large for its frame. He inspected the class of locomotive that hauls the Yomi service, found the pressure rating timid, and raised it by decree — with the result that the safety valves on the Frost Yard engines now open at a bar figure the original builders would have called blasphemy. His goats travel in the brake van and are not to be fed.',
    kv:{ Patronage:['Boilers, lightning, the honest argument'], Residence:['Múspell Forge Works, shed 3'], Sign:['Mjǫllnir, stamped on every crown sheet'], Due:['A full firebox, weekly'] },
    sigil:'<path d="M12 10h24v10H12z"/><path d="M24 20v16"/><path d="M17 36h14l-7 8z"/><path d="M36 24l5 5-3 2 5 6"/>',
    invoke:'Þórr has struck the crown sheet. Boiler pressure stabilised.' },

  { id:'freyja', pan:'norse', name:'Freyja', ep:'Lady of the Salon · First class',
    domain:'Upholstery, hospitality, and who gets the window seat.',
    office:'Chair, Accommodation Committee',
    bless:'Half of the fallen go to Fólkvangr. The other half get a berth with a lamp.',
    long:'Freyja specified the Valkyrie Salon down to the thread count and the angle of the reading lamp, then vetoed three of her own designs for being showy. Her cat-drawn carriage runs the length of the platform on feast days, which causes delays the company records as “hospitality”. Passengers in her cars are, by long custom, never turned away for want of fare.',
    kv:{ Patronage:['Saloon cars, linen, the unrefused guest'], Residence:['Fólkvangr, via Asgard Central'], Sign:['Brísingamen, worn as a lamp chain'], Due:['Fresh flowers at every terminus'] },
    sigil:'<circle cx="24" cy="27" r="12" stroke-dasharray="2 4.5"/><circle cx="24" cy="27" r="4.5"/><path d="M24 8v7"/><path d="M15 42l9-7 9 7"/><path d="M18 12l6 4 6-4"/>',
    invoke:'Freyja has inspected the linen. Salon cars released for service.' },

  { id:'skadi', pan:'norse', name:'Skaði', ep:'Mistress of the Plough · Winter ops',
    domain:'Snow, gradient braking, and the mountain sections.',
    office:'Winter Operations Director',
    bless:'When the pass closes, the line does not. It merely goes slower and louder.',
    long:'Skaði holds the contract for every plough, flanger and rotary on the system and has never lost a train to drift. She skis the Jötunheim section ahead of the first service, reads the snow like a signalman reads a block instrument, and has an unbroken record of telling the control room what will happen roughly eleven minutes before it does.',
    kv:{ Patronage:['Snowploughs, skis, high cold places'], Residence:['Jötunheim Pass, platform end'], Sign:['An arrow, nocked, left in the snow'], Due:['The first frost of the year'] },
    sigil:'<path d="M8 34L24 11l16 23z"/><path d="M24 5v6"/><path d="M13 40h22"/><path d="M17 45h14"/><path d="M20 26l4-6 4 6"/>',
    invoke:'Skaði has skied the pass. Winter timetable in force.' },

  { id:'heimdall', pan:'norse', name:'Heimdallr', ep:'The Stationmaster · Signals & horn',
    domain:'Blocks, headways, and the ninety seconds before departure.',
    office:'Stationmaster, Bifröst Junction',
    bless:'He hears the wool growing on the sheep beside the embankment. He hears your ticket.',
    long:'Heimdallr requires less sleep than a signal box requires staffing, which is why the Junction has never had a headway incident. His horn is the departure instrument for the entire line: one long note for a stopping service, two for an express, and a specific short third blast that only the guards hear, meaning something is on the line that is not a train.',
    kv:{ Patronage:['Signals, watchkeeping, the Gjallarhorn'], Residence:['Himinbjǫrg, above Bifröst Junction'], Sign:['A horn note, ninety seconds early'], Due:['Unbroken attention'] },
    sigil:'<path d="M10 31c9-2 13-11 13-19"/><path d="M23 12c8 1 14 7 16 15"/><path d="M7 36c4-3 8-3 12 0s8 3 12 0 8-3 12 0"/><path d="M7 42c4-3 8-3 12 0s8 3 12 0 8-3 12 0"/>',
    invoke:'Heimdallr has sounded the horn. All blocks clear.' },

  { id:'amaterasu', pan:'kami', name:'Amaterasu', ep:'The Sun Lamp · Headlamps & light',
    domain:'Every flame that leaves the shed, and the mirror it is checked against.',
    office:'Lamp Warden, Takamagahara',
    bless:'The first train of the morning is lit from the mirror. No other flame will do.',
    long:'Amaterasu keeps the headlamp depot at the Sky Shrine, where a single light has been carried unbroken since the cave was opened. Locomotive lamps are lit from it in sequence each dawn; a lamp lit any other way is, in her opinion and in the opinion of the entire engineering department, simply not the same light. She withdrew once, for reasons of family, and the whole system ran on candles for three days.',
    kv:{ Patronage:['Sun, mirrors, headlamps, weaving'], Residence:['Takamagahara Sky Shrine'], Sign:['A disc of light, eight-rayed'], Due:['The first light of the first train'] },
    sigil:'<circle cx="24" cy="24" r="9"/><path d="M24 4v6M24 38v6M4 24h6M38 24h6M10 10l4.5 4.5M33.5 33.5L38 38M38 10l-4.5 4.5M14.5 33.5L10 38"/>',
    invoke:'Amaterasu has lit the depot lamp. Headlamps burning true.' },

  { id:'susanoo', pan:'kami', name:'Susanoo', ep:'The Storm Breaker · Cowcatchers',
    domain:'Weather, obstruction, and things that must be pushed off the line.',
    office:'Inspector of Obstruction',
    bless:'Eight heads, eight tails, one very long serpent — and still the 07:40 got through.',
    long:'Susanoo was appointed after an incident involving a river, a bridge and his own temper, and has since channelled the energy into the design of cowcatchers, which are the most aggressively shaped pieces of metal on the railway. He walks the line in storms when nobody else will, clears fallen timber by hand, and has been formally asked, four times, to stop challenging the express to contests.',
    kv:{ Patronage:['Storms, swords, rivers cleared of obstruction'], Residence:['Izumo Crossing, tide mark'], Sign:['A blade above three waves'], Due:['One honest fight a season'] },
    sigil:'<path d="M24 5v25"/><path d="M16 14h16"/><path d="M6 35c4-3 8-3 12 0s8 3 12 0 8-3 12 0"/><path d="M10 42c4-2.5 8-2.5 12 0s8 2.5 12 0"/>',
    invoke:'Susanoo has walked the storm. Obstruction cleared from the four-foot.' },

  { id:'tsukuyomi', pan:'kami', name:'Tsukuyomi', ep:'The Night Timetable · Working books',
    domain:'Counting, counting correctly, and the hours after midnight.',
    office:'Keeper of the Working Timetable',
    bless:'The sun keeps the day. I keep the book that says what the day is for.',
    long:'Tsukuyomi compiles the working timetable — the dense, unglamorous volume of paths and margins that makes the pretty public one possible. He is meticulous, unforgiving of optimistic running times, and has not spoken to his sister at a company function in a very long number of years. Every night service on the line carries his handwritten marginal note in the guard’s copy.',
    kv:{ Patronage:['Moon, measures, the night working book'], Residence:['Kōya Skygate, west tower'], Sign:['A crescent and a counting rod'], Due:['An accurate return, filed on time'] },
    sigil:'<path d="M32 8a16 16 0 100 32 19 19 0 010-32z"/><circle cx="34" cy="24" r="3.5"/><path d="M8 40h14"/><path d="M10 40v4M15 40v4M20 40v4"/>',
    invoke:'Tsukuyomi has ruled the night paths. Working book closed.' },

  { id:'inari', pan:'kami', name:'Inari', ep:'The Fox Signals · Fire & freight',
    domain:'Rice, lamplight, and the small white animals that carry it.',
    office:'Signal & Freight Superintendent, Fushimi',
    bless:'Where the lamps go, the train may follow. The lamps are not on wires.',
    long:'Inari’s signals are foxfire, and the Junction at Fushimi is the busiest block post on the kami side entirely because of it. Freight traffic in rice, sake and straw is handled under a standing contract that has never been written down, since nobody can agree which of Inari’s many aspects signed it. The company maintains a fund for lamps, fried bean curd, and unavoidable minor damage to platform flowerbeds.',
    kv:{ Patronage:['Rice, foxes, foxfire signals, prosperity'], Residence:['Fushimi Fox Junction'], Sign:['A gate of two beams, and ears'], Due:['Fried bean curd, left at the post'] },
    sigil:'<path d="M7 11h34"/><path d="M11 16h26"/><path d="M14 16v22M34 16v22"/><path d="M19 27l5-7 5 7z"/><path d="M24 32v6"/>',
    invoke:'Inari has loosed the lamps. Foxfire signals lit at Fushimi.' },

  { id:'izanami', pan:'kami', name:'Izanami', ep:'The Terminal Keeper · Yomi',
    domain:'The last platform, the barrier, and what is on the other side of it.',
    office:'Terminal Warden, Yomi',
    bless:'Come as a passenger. Do not come looking for me.',
    long:'Izanami holds the barrier at Yomi Terminal, and the company’s by-laws on that platform are the shortest in the system. She was, in the beginning, one of the two who built the islands the eastern section now runs across; the relationship with her surviving husband is described in official documents only as “not currently operational”. Trains terminate here. They are not, strictly speaking, required to return.',
    kv:{ Patronage:['The terminal barrier, creation, the descent'], Residence:['Yomi Terminal, beyond the barrier'], Sign:['A spiral, turning downward'], Due:['Nothing. Bring nothing.'] },
    sigil:'<path d="M24 24a3 3 0 113 3 6 6 0 11-6-6 9 9 0 119 9 12 12 0 11-12-12"/><path d="M11 41h26"/><path d="M16 41v4M24 41v4M32 41v4"/>',
    invoke:'Izanami has opened the barrier. Terminal road clear — one way.' }
];

const BLESSINGS = [
  'Heimdallr reminds passengers: the Gjallarhorn sounds ninety seconds before departure, not after.',
  'Foxfire signals at Fushimi are lit manually. Please do not thank the foxes directly; it encourages them.',
  'Skaði advises: the Jötunheim section is single line. The goat has right of way.',
  'Bogie exchange at the Floating Bridge takes eleven minutes. The tea house is on the platform and the tea is good.',
  'Þórr has raised the working pressure on the Frost Yard engines again. Passengers may notice enthusiasm.',
  'Yomi Terminal is a terminus. The company prints the return leg in a different colour for a reason.',
  'Amaterasu requires that no lamp be lit from any flame but the depot flame. Engineering agrees with her.',
  'Tsukuyomi has ruled the night paths. The 23:40 to Izumo will not be advertised as fast.',
  'Freyja’s Salon cars are never refused for want of fare. This is a by-law and not a rumour.',
  'Left luggage at Helheim Desk is held for nine days. After that it is held by someone else.'
];

const STATUS = [
  { k:'ontime',   t:'On time' },
  { k:'ontime',   t:'On time' },
  { k:'boarding', t:'Boarding' },
  { k:'delayed',  t:'Delayed · frost' },
  { k:'delayed',  t:'Delayed · mist' },
  { k:'delayed',  t:'Delayed · giants' },
  { k:'held',     t:'Held at signal' },
  { k:'departed', t:'Departed' }
];

/* ==========================================================
   SOUND (synthesised, opt-in)
   ========================================================== */
const Sound = (() => {
  let ctx = null, master = null, noiseBuf = null, hiss = null, hissGain = null, on = false, chuffTimer = null;
  function ensure(){
    if (ctx) return ctx;
    const AC = window.AudioContext || window.webkitAudioContext;
    if (!AC) return null;
    ctx = new AC();
    master = ctx.createGain(); master.gain.value = 0.5; master.connect(ctx.destination);
    const len = Math.floor(ctx.sampleRate * 2);
    noiseBuf = ctx.createBuffer(1, len, ctx.sampleRate);
    const d = noiseBuf.getChannelData(0);
    let last = 0;
    for (let i = 0; i < len; i++){ const w = Math.random()*2-1; last = (last + 0.02*w) / 1.02; d[i] = last*3.2; }
    return ctx;
  }
  function startHiss(){
    if (!ctx || hiss) return;
    hiss = ctx.createBufferSource(); hiss.buffer = noiseBuf; hiss.loop = true;
    const lp = ctx.createBiquadFilter(); lp.type = 'lowpass'; lp.frequency.value = 780;
    hissGain = ctx.createGain(); hissGain.gain.value = 0;
    hiss.connect(lp); lp.connect(hissGain); hissGain.connect(master); hiss.start();
  }
  function level(v){ if (hissGain && ctx) hissGain.gain.setTargetAtTime(on ? v * 0.05 : 0, ctx.currentTime, 0.35); }
  function chuff(strength){
    if (!ctx || !on) return;
    const t = ctx.currentTime;
    const src = ctx.createBufferSource(); src.buffer = noiseBuf;
    const bp = ctx.createBiquadFilter(); bp.type = 'bandpass'; bp.frequency.value = 150 + strength*90; bp.Q.value = 1.1;
    const g = ctx.createGain();
    g.gain.setValueAtTime(0, t);
    g.gain.linearRampToValueAtTime(0.055 * strength, t + 0.012);
    g.gain.exponentialRampToValueAtTime(0.0008, t + 0.16);
    src.connect(bp); bp.connect(g); g.connect(master);
    src.start(t); src.stop(t + 0.2);
  }
  function whistle(){
    if (!ensure() || !on) return;
    if (ctx.state === 'suspended') ctx.resume();
    const t = ctx.currentTime, g = ctx.createGain();
    const lp = ctx.createBiquadFilter(); lp.type = 'lowpass'; lp.frequency.value = 2600;
    g.gain.setValueAtTime(0, t);
    g.gain.linearRampToValueAtTime(0.1, t + 0.09);
    g.gain.setValueAtTime(0.1, t + 0.85);
    g.gain.exponentialRampToValueAtTime(0.0005, t + 1.5);
    g.connect(lp); lp.connect(master);
    [392, 523.25, 659.25].forEach((f, i) => {
      const o = ctx.createOscillator(); o.type = i === 2 ? 'sine' : 'triangle';
      o.frequency.value = f; o.detune.value = (i - 1) * 6;
      const og = ctx.createGain(); og.gain.value = i === 2 ? 0.22 : 0.5;
      o.connect(og); og.connect(g); o.start(t); o.stop(t + 1.55);
    });
  }
  function chime(freq = 880){
    if (!ctx || !on) return;
    const t = ctx.currentTime, o = ctx.createOscillator(), g = ctx.createGain();
    o.type = 'sine'; o.frequency.value = freq;
    g.gain.setValueAtTime(0, t);
    g.gain.linearRampToValueAtTime(0.05, t + 0.01);
    g.gain.exponentialRampToValueAtTime(0.0004, t + 0.7);
    o.connect(g); g.connect(master); o.start(t); o.stop(t + 0.75);
  }
  function scheduleChuff(speedFactor){
    clearInterval(chuffTimer);
    if (!on || speedFactor <= 0.02) return;
    const ms = clamp(1100 / (0.35 + speedFactor * 2.4), 130, 1600);
    let beat = 0;
    chuffTimer = setInterval(() => { chuff(beat % 2 ? 0.55 : 1); beat++; }, ms);
  }
  function toggle(){
    if (!on){
      if (!ensure()) return false;
      if (ctx.state === 'suspended') ctx.resume();
      on = true; startHiss();
    } else {
      on = false; clearInterval(chuffTimer); level(0);
    }
    return on;
  }
  return { toggle, level, whistle, chime, scheduleChuff, isOn: () => on };
})();

/* ==========================================================
   AMBIENT CANVAS (steam + snow/embers)
   ========================================================== */
const Ambient = (() => {
  const cv = $('#ambient'); if (!cv) return { start(){}, stop(){}, setThrottle(){}, burst(){} };
  const ctx = cv.getContext('2d', { alpha: true });
  let W = 0, H = 0, DPR = 1, parts = [], running = false, throttle = 42, realm = 'asgard';
  let sprite = null, warmSprite = null, funnel = { x: 0, y: 0 }, raf = 0, lastT = 0;

  function makeSprite(color){
    const s = document.createElement('canvas'); s.width = s.height = 64;
    const c = s.getContext('2d');
    const g = c.createRadialGradient(32, 32, 0, 32, 32, 32);
    g.addColorStop(0, color.replace('ALPHA', '0.85'));
    g.addColorStop(0.45, color.replace('ALPHA', '0.30'));
    g.addColorStop(1, color.replace('ALPHA', '0'));
    c.fillStyle = g; c.fillRect(0, 0, 64, 64);
    return s;
  }
  function buildSprites(){
    sprite = makeSprite('rgba(226,238,246,ALPHA)');
    const warm = realm === 'takamagahara' ? 'rgba(255,178,120,ALPHA)' : 'rgba(190,225,255,ALPHA)';
    warmSprite = makeSprite(warm);
  }
  function resize(){
    const r = cv.getBoundingClientRect();
    if (!r.width || !r.height) return;
    DPR = Math.min(window.devicePixelRatio || 1, 2);
    W = r.width; H = r.height;
    cv.width = Math.round(W * DPR); cv.height = Math.round(H * DPR);
    ctx.setTransform(DPR, 0, 0, DPR, 0, 0);
    const anchor = $('#funnelAnchor');
    if (anchor){
      const a = anchor.getBoundingClientRect();
      funnel.x = a.left - r.left + a.width / 2;
      funnel.y = a.top - r.top;
    }
  }
  function spawnSteam(n){
    for (let i = 0; i < n; i++){
      const spread = 6 + throttle * 0.14;
      parts.push({
        t:'steam', x: funnel.x + rand(-spread, spread), y: funnel.y + rand(-6, 6),
        vx: rand(-0.25, 0.55) + throttle * 0.006, vy: -(0.5 + Math.random() * 0.9 + throttle * 0.018),
        r: rand(7, 15), g: rand(0.14, 0.30), a: rand(0.28, 0.5), life: 0, max: rand(150, 260)
      });
    }
  }
  function spawnSky(n){
    for (let i = 0; i < n; i++){
      const ember = realm === 'takamagahara';
      parts.push(ember
        ? { t:'ember', x: rand(0, W), y: H + rand(10, 120), vx: rand(-0.25, 0.45), vy: -rand(0.25, 0.85),
            r: rand(1, 2.6), a: rand(0.3, 0.85), life: 0, max: rand(320, 620), ph: rand(0, 6.28) }
        : { t:'snow', x: rand(-40, W + 40), y: rand(-H, 0), vx: rand(-0.22, 0.42), vy: rand(0.25, 0.85),
            r: rand(0.9, 2.4), a: rand(0.25, 0.75), life: 0, max: rand(500, 1100), ph: rand(0, 6.28) });
    }
  }
  function frame(t){
    if (!running) return;
    const dt = Math.min(48, t - lastT || 16); lastT = t;
    ctx.clearRect(0, 0, W, H);

    if (!REDUCED){
      spawnSteam(1 + Math.round(throttle / 26));
      if (Math.random() < 0.35) spawnSky(1);
    } else if (parts.length < 30){ spawnSteam(4); spawnSky(8); }

    for (let i = parts.length - 1; i >= 0; i--){
      const p = parts[i];
      const k = REDUCED ? 0.35 : dt / 16.67;
      p.life += dt;
      if (p.t === 'steam'){
        p.x += p.vx * k; p.y += p.vy * k;
        p.vy *= 0.995; p.r += p.g * k;
        const f = 1 - p.life / p.max;
        if (f <= 0){ parts.splice(i, 1); continue; }
        ctx.globalAlpha = p.a * f * 0.75;
        ctx.drawImage(sprite, p.x - p.r, p.y - p.r, p.r * 2, p.r * 2);
      } else {
        p.ph += 0.02 * k;
        p.x += (p.vx + Math.sin(p.ph) * 0.22) * k;
        p.y += p.vy * k;
        if (p.life > p.max || p.y < -20 || p.y > H + 40){ parts.splice(i, 1); continue; }
        const f = Math.min(1, (p.max - p.life) / 160);
        ctx.globalAlpha = p.a * f;
        ctx.drawImage(p.t === 'ember' ? warmSprite : sprite, p.x - p.r * 3, p.y - p.r * 3, p.r * 6, p.r * 6);
      }
    }
    ctx.globalAlpha = 1;
    if (parts.length > 340) parts.splice(0, parts.length - 340);
    raf = requestAnimationFrame(frame);
  }
  function start(){ if (running || REDUCED && false) return; if (running) return; running = true; lastT = performance.now(); resize(); raf = requestAnimationFrame(frame); }
  function stop(){ running = false; cancelAnimationFrame(raf); ctx.clearRect(0, 0, W, H); }
  function burst(n = 26){
    if (!W) resize();
    for (let i = 0; i < n; i++){
      parts.push({ t:'steam', x: funnel.x + rand(-24, 24), y: funnel.y + rand(-14, 14),
        vx: rand(-2.4, 2.4), vy: -rand(1.4, 3.6), r: rand(10, 22), g: rand(0.4, 0.8),
        a: rand(0.4, 0.7), life: 0, max: rand(90, 170) });
    }
  }
  window.addEventListener('resize', () => { resize(); }, { passive: true });
  buildSprites();
  return {
    start, stop, burst,
    setThrottle: v => { throttle = v; },
    setRealm: r => { realm = r; buildSprites(); parts.length = 0; },
    ready: () => W > 0
  };
})();

/* ==========================================================
   ENGINE CONSOLE
   ========================================================== */
const Engine = (() => {
  const th = $('#throttle'), needle = $('#needle'), gaugeVal = $('#gaugeVal');
  const roSpeed = $('#roSpeed'), roFire = $('#roFire'), roWater = $('#roWater'), roFlux = $('#roFlux');
  const bar = $('#pressBar'), barFill = bar.querySelector('i'), alarm = $('#alarm');
  const root = document.documentElement;
  let water = 100, venting = false, alarmOn = false;

  function update(){
    const v = +th.value;                       // 0..100
    const pressure = Math.round(v * 0.96 + Math.sin(Date.now() / 900) * 1.2);
    const speed = Math.round(v * 1.62);
    const fire = Math.round(clamp(v * 1.05 + 6, 0, 118));
    const flux = (v / 100 * 4.7 + 0.3).toFixed(2);

    // needle: -126deg at 0, +126deg at 100
    needle.style.transform = `rotate(${-126 + (v / 100) * 252}deg)`;
    gaugeVal.textContent = pressure;
    roSpeed.textContent = speed + ' lg/h';
    roFire.textContent  = fire + ' °×';
    roWater.textContent = water.toFixed(0) + '%';
    roFlux.textContent  = flux + ' Φ';
    roFire.classList.toggle('warn', fire > 108);
    roWater.classList.toggle('warn', water < 22);

    barFill.style.width = clamp(pressure, 0, 100) + '%';
    bar.classList.toggle('hot', pressure > 78);

    // motion speeds
    root.style.setProperty('--rail-dur', (v < 2 ? 0 : clamp(9 - v * 0.075, 0.55, 9)).toFixed(2) + 's');
    root.style.setProperty('--wheel-dur', (v < 2 ? 0 : clamp(6.5 - v * 0.055, 0.4, 6.5)).toFixed(2) + 's');
    if (v < 2){
      $('.track').style.animationPlayState = 'paused';
      $$('.loco .wheel, .loco .rod').forEach(w => w.style.animationPlayState = 'paused');
    } else {
      $('.track').style.animationPlayState = 'running';
      $$('.loco .wheel, .loco .rod').forEach(w => w.style.animationPlayState = 'paused');
    }

    Ambient.setThrottle(v);
    Sound.level(v / 100);
    Sound.scheduleChuff(v / 100);

    // overpressure
    if (pressure > 88 && !venting && !alarmOn){
      alarmOn = true; alarm.classList.add('on');
      Ambient.burst(34); Sound.chime(220);
      setNote('Safety valve lifted — venting. The stoker is displeased.');
      setTimeout(() => { ventOpen(true); }, 60);
      setTimeout(() => { ventOpen(false); alarmOn = false; }, 2200);
    }
    // water consumption
    water = clamp(water - (v / 100) * 0.012 + 0.004, 0, 100);
    if (water <= 0.5) water = 100;             // injector refills at Mímir's well
  }
  function ventOpen(on){
    venting = on;
    bar.classList.toggle('hot', on);
    if (on) Ambient.burst(20);
  }
  function setNote(txt){ $('#throttleHelp').textContent = txt; }

  th.addEventListener('input', () => {
    th.setAttribute('aria-valuetext', th.value + ' percent regulator');
    update();
  });
  $('#whistleBtn').addEventListener('click', () => { Sound.whistle(); Ambient.burst(18); });
  $('#ventBtn').addEventListener('click', () => {
    ventOpen(true); setNote('Steam vented manually. Boiler settling.');
    setTimeout(() => { ventOpen(false); setNote('Regulator under driver control.'); }, 1400);
  });

  setInterval(update, 240);
  update();
  return { value: () => +th.value };
})();

/* ==========================================================
   REALM THEME
   ========================================================== */
(() => {
  const btns = $$('[data-realm-set]');
  const meta = $('meta[name="theme-color"]');
  const eyebrow = $('#heroEyebrow');
  const EYEBROWS = {
    asgard: 'Nine realms · One gauge · Eight million kami',
    takamagahara: '八百万の神 · One gauge · Nine realms'
  };
  function apply(realm, announce){
    document.documentElement.dataset.realm = realm;
    btns.forEach(b => b.setAttribute('aria-pressed', String(b.dataset.realmSet === realm)));
    if (meta) meta.setAttribute('content', realm === 'asgard' ? '#050a12' : '#0d0608');
    eyebrow.textContent = EYEBROWS[realm];
    Ambient.setRealm(realm);
    try { localStorage.setItem('yl-realm', realm); } catch (e) {}
    if (announce) Ticker.push(realm === 'asgard'
      ? 'Livery changed to Asgard frost. Bifröst signals re-lit in blue.'
      : 'Livery changed to Takamagahara vermilion. Foxfire signals answering.');
  }
  btns.forEach(b => b.addEventListener('click', () => apply(b.dataset.realmSet, true)));
  let saved = null;
  try { saved = localStorage.getItem('yl-realm'); } catch (e) {}
  apply(saved === 'takamagahara' ? 'takamagahara' : 'asgard', false);
})();

/* ==========================================================
   TICKER
   ========================================================== */
const Ticker = (() => {
  const el = $('#tickerText');
  let queue = BLESSINGS.slice(), i = randInt(0, queue.length - 1), timer = null;
  function show(txt){
    el.classList.add('out');
    setTimeout(() => { el.textContent = txt; el.classList.remove('out'); }, REDUCED ? 0 : 420);
  }
  function next(){ i = (i + 1) % queue.length; show(queue[i]); }
  function start(){ clearInterval(timer); timer = setInterval(next, 7600); }
  function push(txt){ queue.unshift(txt); i = 0; show(txt); start(); }
  start();
  return { push };
})();

/* ==========================================================
   DEPARTURES BOARD
   ========================================================== */
const Board = (() => {
  const list = $('#boardList'), live = $('#boardLive'), countEl = $('#boardCount'), syncEl = $('#lastSync');
  const N = 9;
  let rows = [], filter = 'all', lastSyncAt = Date.now();

  const DEST_POOL = STATIONS.concat(EXTRA_DESTS).map(s => ({ name:s.name, glyph:s.glyph, pan:s.pan }));

  function makeRow(offsetMin){
    const dest = pick(DEST_POOL);
    const departs = Date.now() + (offsetMin + randInt(2, 26)) * 60000;
    const st = pick(STATUS);
    return {
      dest,
      consist: pick(CONSISTS),
      departs,
      road: 'Road ' + randInt(1, 12),
      status: st,
      pan: dest.pan === 'threshold' ? 'norse' : dest.pan
    };
  }
  function seed(){
    rows = [];
    let m = 1;
    for (let i = 0; i < N; i++){ rows.push(makeRow(m)); m += randInt(4, 19); }
    rows.sort((a, b) => a.departs - b.departs);
  }
  function fmtTime(ts){
    const d = new Date(ts);
    return pad2(d.getHours()) + ':' + pad2(d.getMinutes());
  }
  function countdown(ts){
    let s = Math.max(0, Math.floor((ts - Date.now()) / 1000));
    const h = Math.floor(s / 3600); s -= h * 3600;
    const m = Math.floor(s / 60); s -= m * 60;
    if (h > 0) return h + 'h ' + pad2(m) + 'm';
    if (m > 0) return m + 'm ' + pad2(s) + 's';
    return pad2(s) + 's';
  }
  function visible(r){
    if (filter === 'all') return true;
    if (filter === 'delayed') return r.status.k === 'delayed' || r.status.k === 'held';
    return r.pan === filter;
  }
  function cell(cls, label, html){
    return `<span class="${cls}" data-l="${label}">${html}</span>`;
  }
  function render(){
    const vis = rows.filter(visible);
    list.innerHTML = vis.map((r, i) => `
      <li class="brow" data-i="${rows.indexOf(r)}">
        <div class="dest">
          <span class="gl" aria-hidden="true">${r.dest.glyph}</span>
          <span class="tx"><b>${r.dest.name}</b><small>${r.pan === 'kami' ? 'Kami section' : 'Realm section'}</small></span>
        </div>
        ${cell('cell', 'Consist', r.consist)}
        ${cell('cell', 'Departs', fmtTime(r.departs))}
        ${cell('cell cd', 'In', countdown(r.departs))}
        ${cell('cell', 'Road', r.road.replace('Road ', ''))}
        <span class="st ${r.status.k}" data-l="Status"><span>${r.status.t}</span></span>
      </li>`).join('');
    countEl.textContent = vis.length + ' of ' + rows.length + ' services shown';
  }
  function tick(){
    // clock
    const now = new Date();
    $('#clock').textContent = pad2(now.getHours()) + ':' + pad2(now.getMinutes()) + ':' + pad2(now.getSeconds());
    $('#clockDate').textContent = now.toLocaleDateString(undefined, { weekday:'short', day:'numeric', month:'short' });

    let changed = false, departedNames = [];
    rows.forEach(r => {
      const diff = r.departs - Date.now();
      if (diff <= 0 && r.status.k !== 'departed'){
        r.status = { k:'departed', t:'Departed' }; changed = true; departedNames.push(r.dest.name);
      } else if (diff < 120000 && r.status.k === 'ontime' && Math.random() < 0.06){
        r.status = { k:'boarding', t:'Boarding' }; changed = true;
      } else if (diff < 600000 && r.status.k === 'ontime' && Math.random() < 0.012){
        r.status = { k:'delayed', t: pick(['Delayed · frost','Delayed · mist','Delayed · foxes']) }; changed = true;
      }
    });
    // recycle departed rows
    if (rows.filter(r => r.status.k === 'departed').length >= 3){
      rows = rows.map(r => r.status.k === 'departed' && Math.random() < 0.5
        ? makeRow(rows[rows.length - 1] ? ((rows[rows.length - 1].departs - Date.now()) / 60000) + 6 : 20) : r);
      rows.sort((a, b) => a.departs - b.departs);
      changed = true;
    }
    // update countdowns in place (cheap)
    $$('#boardList .cd').forEach(el => {
      const li = el.closest('.brow');
      const r = rows[+li.dataset.i];
      if (r) el.textContent = r.status.k === 'departed' ? '—' : countdown(r.departs);
    });
    if (changed){
      render();
      if (departedNames.length) live.textContent = departedNames.join(', ') + ' has departed.';
    }
    const ago = Math.round((Date.now() - lastSyncAt) / 1000);
    syncEl.textContent = ago < 5 ? 'just now' : ago + 's ago';
  }
  $$('.filters [data-filter]').forEach(btn => {
    btn.addEventListener('click', () => {
      filter = btn.dataset.filter;
      $$('.filters [data-filter]').forEach(b => b.setAttribute('aria-pressed', String(b === btn)));
      render();
    });
  });
  $('#resync').addEventListener('click', () => {
    seed(); lastSyncAt = Date.now(); render();
    live.textContent = 'Timetable resynchronised with the Norn signalling box.';
    Ticker.push('Timetable resynchronised. Tsukuyomi has ruled new night paths.');
    Sound.chime(660);
  });
  seed(); render(); setInterval(tick, 1000); tick();
  return { refresh: () => { seed(); render(); } };
})();

/* ==========================================================
   ROUTE MAP
   ========================================================== */
const Map = (() => {
  const svgNS = 'http://www.w3.org/2000/svg';
  const path = $('#railPath'), layer = $('#stationLayer'), marker = $('#trainMarker'), bridgeG = $('#bridgeMark');
  const total = path.getTotalLength();
  const pts = STATIONS.map((s, i) => {
    const f = i / (STATIONS.length - 1);
    const p = path.getPointAtLength(f * total);
    const p2 = path.getPointAtLength(Math.min(total, f * total + 2));
    return { ...s, i, f, x: p.x, y: p.y, ang: Math.atan2(p2.y - p.y, p2.x - p.x) * 180 / Math.PI };
  });

  // bridge marker
  const b = pts[BRIDGE_IDX];
  bridgeG.innerHTML = `
    <path class="bridge-mark" d="M${b.x - 26} ${b.y + 22} V${b.y - 20} H${b.x + 26} V${b.y + 22}"/>
    <path class="bridge-mark" d="M${b.x - 32} ${b.y - 20} H${b.x + 32}"/>
    <path class="bridge-mark" d="M${b.x - 22} ${b.y - 11} H${b.x + 22}"/>`;

  // stations
  pts.forEach(s => {
    const g = document.createElementNS(svgNS, 'g');
    g.setAttribute('class', 'station');
    g.setAttribute('tabindex', '0');
    g.setAttribute('role', 'button');
    g.setAttribute('aria-label', s.name + ', station ' + (s.i + 1) + ' of ' + pts.length);
    const above = s.i % 2 === 0;
    const ly = above ? -24 : 34;
    const gy = above ? -40 : 50;
    g.innerHTML = `
      <circle class="hit" cx="${s.x}" cy="${s.y}" r="26"/>
      <circle class="pulse" cx="${s.x}" cy="${s.y}" r="8"/>
      <circle class="ring" cx="${s.x}" cy="${s.y}" r="8"/>
      <circle class="dot" cx="${s.x}" cy="${s.y}" r="3"/>
      <text class="gl" x="${s.x}" y="${gy + 5}">${s.glyph}</text>
      <text class="nm" x="${s.x}" y="${ly}" text-anchor="middle">${s.name.toUpperCase()}</text>`;
    g.addEventListener('click', () => select(s.i));
    g.addEventListener('keydown', e => {
      if (e.key === 'Enter' || e.key === ' '){ e.preventDefault(); select(s.i); }
      if (e.key === 'ArrowRight'){ e.preventDefault(); focus((s.i + 1) % pts.length); }
      if (e.key === 'ArrowLeft'){ e.preventDefault(); focus((s.i - 1 + pts.length) % pts.length); }
    });
    layer.appendChild(g);
  });
  const nodes = $$('.station', layer);
  function focus(i){ nodes[i] && nodes[i].focus(); }

  let selected = 0;
  function select(i, quiet){
    selected = i;
    const s = pts[i];
    nodes.forEach((n, k) => n.classList.toggle('sel', k === i));
    $('#spGlyph').textContent = s.glyph;
    $('#spName').textContent = s.name;
    $('#spKind').textContent = s.kind + ' · ' + (s.pan === 'kami' ? 'Kami section' : s.pan === 'threshold' ? 'Border' : 'Realm section');
    $('#spElev').textContent = 'Elev. ' + s.elev;
    $('#spDist').textContent = Math.round(s.f * 847) + ' leagues';
    $('#spRoad').textContent = s.road;
    $('#spLore').textContent = s.lore;
    $('#spSvc').innerHTML = s.svc.map(x => `<li>${x}</li>`).join('');
    if (!quiet) Sound.chime(520 + i * 42);
  }

  /* ---- running a service ---- */
  let running = false, raf = 0, dist = 0, dwellUntil = 0, express = false, log = [];
  const runBtn = $('#runBtn'), expBtn = $('#expressBtn'), stopBtn = $('#stopBtn');
  const progFill = $('#runProgress'), note = $('#runNote'), logEl = $('#journeyLog');
  let nextStation = 0;

  function place(d){
    const p = path.getPointAtLength(d);
    const p2 = path.getPointAtLength(Math.min(total, d + 3));
    const ang = Math.atan2(p2.y - p.y, p2.x - p.x) * 180 / Math.PI;
    marker.setAttribute('transform', `translate(${p.x},${p.y}) rotate(${ang})`);
  }
  function addLog(txt){
    log.push(txt);
    if (log.length > 24) log.shift();
    logEl.innerHTML = log.slice().reverse().map(l => `<div>${l}</div>`).join('');
  }
  function stop(halt){
    running = false; cancelAnimationFrame(raf);
    stopBtn.disabled = true; runBtn.disabled = false; expBtn.disabled = false;
    if (halt){
      marker.setAttribute('opacity', '0'); dist = 0; nextStation = 0;
      nodes.forEach(n => n.classList.remove('passed'));
      progFill.style.width = '0%';
      note.textContent = 'Service cancelled. Locomotive returned to the starting signal.';
      addLog('<b>Halted</b> by order of the control room.');
    }
  }
  function start(mode){
    if (running) return;
    express = mode === 'express';
    running = true; dist = 0; nextStation = 0; log = [];
    marker.setAttribute('opacity', '1');
    nodes.forEach(n => n.classList.remove('passed'));
    runBtn.disabled = true; expBtn.disabled = true; stopBtn.disabled = false;
    progFill.style.width = '0%';
    note.textContent = express ? 'Express working — no intermediate stops.' : 'Stopping service — calls at all ten stations.';
    addLog('<b>Departed</b> ' + pts[0].name + ' · ' + (express ? 'express' : 'stopping') + ' working');
    select(0, true);
    Sound.whistle();
    let last = performance.now();
    function frame(t){
      if (!running) return;
      const dt = Math.min(50, t - last); last = t;
      const speed = (total / 15000) * (0.5 + Engine.value() / 100 * 1.6);  // px per ms
      if (dwellUntil > t){ raf = requestAnimationFrame(frame); return; }
      dist = Math.min(total, dist + speed * dt);
      place(dist);
      progFill.style.width = (dist / total * 100) + '%';

      // station arrivals
      while (nextStation < pts.length && dist >= pts[nextStation].f * total){
        const s = pts[nextStation];
        nodes[nextStation].classList.add('passed');
        select(nextStation, true);
        Sound.chime(560 + nextStation * 40);
        if (nextStation === BRIDGE_IDX){
          addLog('<b>' + s.name + '</b> — bogie exchange, 1 435 mm → 1 067 mm');
          note.textContent = 'Bogie exchange at the Floating Bridge. Eleven minutes.';
          if (!express) dwellUntil = t + 1500;
        } else {
          addLog((nextStation === pts.length - 1 ? '<b>Arrived</b> ' : '<b>Called at</b> ') + s.name);
          if (!express && nextStation < pts.length - 1){
            dwellUntil = t + 850;
            note.textContent = 'Standing at ' + s.name + '. Doors on the left.';
          } else {
            note.textContent = 'Running to ' + (pts[Math.min(nextStation + 1, pts.length - 1)].name) + '.';
          }
        }
        nextStation++;
      }
      if (dist >= total){
        running = false;
        stopBtn.disabled = true; runBtn.disabled = false; expBtn.disabled = false;
        note.textContent = 'Arrived Yomi Terminal. The barrier is open. Return leg printed separately.';
        addLog('<b>Arrived</b> Yomi Terminal — ' + Math.round((performance.now() - t0) / 1000) + 's on the road');
        Ticker.push('The ' + (express ? 'express' : 'stopping service') + ' has arrived at Yomi Terminal. Izanami has opened the barrier.');
        Ambient.burst(30); Sound.whistle();
        return;
      }
      raf = requestAnimationFrame(frame);
    }
    const t0 = performance.now();
    raf = requestAnimationFrame(frame);
  }
  runBtn.addEventListener('click', () => start('stop'));
  expBtn.addEventListener('click', () => start('express'));
  stopBtn.addEventListener('click', () => stop(true));

  place(0);
  select(0, true);
  $('#spSvc').innerHTML = pts[0].svc.map(x => `<li>${x}</li>`).join('');
  return { select };
})();

/* ==========================================================
   PATRONS
   ========================================================== */
(() => {
  const grid = $('#patronGrid');
  const modal = $('#modal'), card = $('#modalCard'), closeBtn = $('#modalClose');
  let current = null, lastFocus = null, panFilter = 'all';

  function sigilSVG(inner){
    return `<svg class="sigil" viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.6"
      stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${inner}</svg>`;
  }
  function render(){
    const items = PATRONS.filter(p => panFilter === 'all' || p.pan === panFilter);
    grid.innerHTML = items.map(p => `
      <article class="plate riveted pcard" data-id="${p.id}" tabindex="0" role="button"
               aria-label="${p.name}, ${p.ep}. Open details.">
        <span class="pn">${p.pan === 'norse' ? 'Norse' : 'Kami'}</span>
        <div class="top">
          ${sigilSVG(p.sigil)}
          <div><h3>${p.name}</h3><p class="ep">${p.ep}</p></div>
        </div>
        <p class="dom">${p.domain}</p>
      </article>`).join('');
    $$('.pcard', grid).forEach(el => {
      const open = () => showModal(PATRONS.find(x => x.id === el.dataset.id), el);
      el.addEventListener('click', open);
      el.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' '){ e.preventDefault(); open(); } });
    });
  }
  function showModal(p, trigger){
    if (!p) return;
    current = p; lastFocus = trigger || document.activeElement;
    $('#mSigil').innerHTML = p.sigil;
    $('#mTitle').textContent = p.name;
    $('#mEp').textContent = p.ep;
    $('#mBody').innerHTML = `<p><em>“${p.bless}”</em></p><p style="margin-top:1rem">${p.long}</p>`;
    $('#mKv').innerHTML = Object.entries(p.kv).map(([k, v]) =>
      `<dt>${k}</dt><dd>${Array.isArray(v) ? v.join(' · ') : v}</dd>`).join('');
    modal.hidden = false;
    requestAnimationFrame(() => modal.classList.add('open'));
    document.body.style.overflow = 'hidden';
    setTimeout(() => closeBtn.focus(), 60);
  }
  function hideModal(){
    modal.classList.remove('open');
    document.body.style.overflow = '';
    setTimeout(() => { modal.hidden = true; }, REDUCED ? 0 : 300);
    if (lastFocus) lastFocus.focus();
  }
  closeBtn.addEventListener('click', hideModal);
  modal.addEventListener('click', e => { if (e.target === modal) hideModal(); });
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && !modal.hidden){ hideModal(); return; }
    if (e.key === 'Tab' && !modal.hidden){
      const f = $$('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])', card)
        .filter(el => !el.disabled && el.offsetParent !== null);
      if (!f.length) return;
      const first = f[0], last = f[f.length - 1];
      if (e.shiftKey && document.activeElement === first){ e.preventDefault(); last.focus(); }
      else if (!e.shiftKey && document.activeElement === last){ e.preventDefault(); first.focus(); }
    }
  });
  $('#mInvoke').addEventListener('click', () => {
    if (!current) return;
    Ticker.push(current.invoke);
    Sound.chime(current.pan === 'norse' ? 392 : 660);
    Ambient.burst(24);
    $('#mInvoke').textContent = 'Blessing recorded ✓';
    $('#mInvoke').disabled = true;
    setTimeout(() => { $('#mInvoke').textContent = 'Invoke for the journey'; $('#mInvoke').disabled = false; }, 2400);
  });
  $$('[data-pan]').forEach(btn => btn.addEventListener('click', () => {
    panFilter = btn.dataset.pan;
    $$('[data-pan]').forEach(b => b.setAttribute('aria-pressed', String(b === btn)));
    render();
  }));
  render();
})();

/* ==========================================================
   TICKETS & FARES
   ========================================================== */
(() => {
  const fromSel = $('#fromSel'), toSel = $('#toSel'), classList = $('#classList'), addonList = $('#addonList');
  const paxOut = $('#paxOut'), fareLines = $('#fareLines'), fareRoute = $('#fareRoute');
  const form = $('#bookingForm'), out = $('#ticketOut'), note = $('#formNote');
  let pax = 2, cls = 'iron';

  STATIONS.forEach((s, i) => {
    fromSel.add(new Option(s.name, i));
    toSel.add(new Option(s.name, i));
  });
  fromSel.value = 0; toSel.value = STATIONS.length - 1;

  classList.innerHTML = CLASSES.map((c, i) => `
    <label class="radio">
      <input type="radio" name="cls" value="${c.id}" ${i === 0 ? 'checked' : ''}>
      <span class="rt"><b>${c.name}</b><small>${c.note}</small></span>
      <span class="rp">×${c.mult.toFixed(1)}</span>
    </label>`).join('');
  addonList.innerHTML = ADDONS.map(a => `
    <label class="addon">
      <input type="checkbox" name="addon" value="${a.id}">
      <span><b>${a.name}</b><small>${a.note}</small></span>
      <span class="cost">₳${a.cost}</span>
    </label>`).join('');

  $$('#classList input').forEach(r => r.addEventListener('change', () => { cls = r.value; quote(); }));
  $$('#addonList input').forEach(c => c.addEventListener('change', quote));
  $('#paxMinus').addEventListener('click', () => { pax = clamp(pax - 1, 1, 9); paxOut.textContent = pax; quote(); });
  $('#paxPlus').addEventListener('click', () => { pax = clamp(pax + 1, 1, 9); paxOut.textContent = pax; quote(); });
  fromSel.addEventListener('change', () => { if (fromSel.value === toSel.value) toSel.value = (Number(fromSel.value) + 1) % STATIONS.length; quote(); });
  toSel.addEventListener('change', () => { if (fromSel.value === toSel.value) fromSel.value = (Number(toSel.value) + STATIONS.length - 1) % STATIONS.length; quote(); });

  const money = n => '₳' + n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

  function quote(){
    const a = Number(fromSel.value), b = Number(toSel.value);
    const legs = Math.abs(b - a);
    const C = CLASSES.find(c => c.id === cls);
    const fare = C.base * C.mult * legs;
    const perPax = fare * pax;

    const addons = $$('#addonList input:checked').map(i => ADDONS.find(x => x.id === i.value));
    let addonTotal = 0;
    const addonLines = addons.map(x => {
      const units = x.per === 'pax' ? pax : x.per === 'seat' ? pax : 1;
      const cost = x.cost * units;
      addonTotal += cost;
      return `<div class="fl"><span>${x.name} × ${units}</span><span>${money(cost)}</span></div>`;
    });

    const crosses = (a < BRIDGE_IDX && b > BRIDGE_IDX) || (b < BRIDGE_IDX && a > BRIDGE_IDX);
    const gauge = crosses ? 5 * pax : 0;
    const hour = new Date().getHours();
    const night = hour >= 22 || hour < 5;
    const subtotal = perPax + addonTotal + gauge;
    const levy = night ? subtotal * 0.12 : 0;
    const total = subtotal + levy;

    fareRoute.textContent = STATIONS[a].name + ' → ' + STATIONS[b].name;
    fareLines.innerHTML = `
      <div class="fl"><span>${C.name} · ${legs} leg${legs === 1 ? '' : 's'} · ${pax} pax</span><span>${money(perPax)}</span></div>
      ${addonLines.join('')}
      ${gauge ? `<div class="fl"><span>Bogie exchange (Bridge) × ${pax}</span><span>${money(gauge)}</span></div>` : ''}
      ${night ? `<div class="fl"><span>Watchman's levy · night service</span><span>${money(levy)}</span></div>` : ''}
      <div class="fl total"><span>Total due</span><span>${money(total)}</span></div>`;
    return { a, b, legs, C, total, addons, crosses, night };
  }

  function serial(q){
    const code = s => (STATIONS[s].glyph || 'X');
    const r = Math.random().toString(36).slice(2, 7).toUpperCase();
    const realm = document.documentElement.dataset.realm === 'asgard' ? 'ASG' : 'TKM';
    return `YL-${realm}-${code(q.a)}${code(q.b)}-${r}`;
  }
  function bars(n){
    let h = '';
    for (let i = 0; i < n; i++) h += `<i style="height:${randInt(40, 100)}%;opacity:${rand(0.55, 1).toFixed(2)}"></i>`;
    return h;
  }

  form.addEventListener('submit', e => {
    e.preventDefault();
    const q = quote();
    if (q.a === q.b){
      note.textContent = 'Origin and destination cannot be the same platform. Even Óðinn must go somewhere.';
      note.style.color = '#ff9c7a';
      return;
    }
    note.style.color = '';
    note.textContent = 'Ticket issued. Present at the barrier; the barrier is guarded.';

    const dep = new Date(Date.now() + randInt(12, 140) * 60000);
    const when = pad2(dep.getHours()) + ':' + pad2(dep.getMinutes());
    const road = 'Road ' + randInt(1, 12);
    const coach = String.fromCharCode(65 + randInt(0, 8)) + randInt(1, 14);
    const seat = randInt(1, 68);
    const ser = serial(q);

    out.innerHTML = `
      <div class="stub" id="stub">
        <div class="main">
          <p class="co">Yggdrasil Line · Æther Railway Co. · ${q.C.name}</p>
          <h4>${q.crosses ? 'Through ticket · both cosmologies' : 'Single journey ticket'}</h4>
          <div class="route">
            <span>${STATIONS[q.a].name}</span><span class="arw"></span><span>${STATIONS[q.b].name}</span>
          </div>
          <div class="grid">
            <div><span>Departs</span><b>${when}</b></div>
            <div><span>Road</span><b>${road.replace('Road ', '')}</b></div>
            <div><span>Coach</span><b>${coach}</b></div>
            <div><span>Seat</span><b>${seat}</b></div>
            <div><span>Passengers</span><b>${pax}</b></div>
            <div><span>Fare</span><b>${money(q.total)}</b></div>
          </div>
          ${q.addons.length ? `<p class="co" style="margin-top:.75rem">Provisions: ${q.addons.map(x => x.name).join(' · ')}</p>` : ''}
          <div class="bars">${bars(46)}</div>
          <p class="ser">${ser} · ${q.night ? 'NIGHT SERVICE' : 'DAY SERVICE'} · ${q.crosses ? 'GAUGE CHANGE AT BRIDGE' : 'SAME GAUGE THROUGHOUT'}</p>
        </div>
        <div class="perf">
          <div class="seal">
            <span>${document.documentElement.dataset.realm === 'asgard' ? 'ᚨ<br>SEALED' : '天<br>御印'}</span>
          </div>
        </div>
      </div>`;
    const stub = $('#stub');
    requestAnimationFrame(() => stub.classList.add('show'));
    Sound.chime(784); Ambient.burst(16);
    Ticker.push('Ticket ' + ser + ' issued at the booking office. ' + STATIONS[q.a].name + ' to ' + STATIONS[q.b].name + ', ' + q.C.name.toLowerCase() + '.');
    out.scrollIntoView({ behavior: REDUCED ? 'auto' : 'smooth', block: 'center' });
  });

  $('#printBtn').addEventListener('click', () => {
    if (!$('#stub')){ note.textContent = 'Issue a ticket first, then the stub will print.'; note.style.color = '#ff9c7a'; return; }
    note.style.color = '';
    window.print();
  });

  quote();
})();

/* ==========================================================
   UI CHROME — nav, reveal, counters, progress, parallax
   ========================================================== */
(() => {
  // mobile nav
  const burger = $('#burger'), links = $('#navLinks');
  burger.addEventListener('click', () => {
    const open = links.classList.toggle('open');
    burger.setAttribute('aria-expanded', String(open));
  });
  $$('#navLinks a').forEach(a => a.addEventListener('click', () => {
    links.classList.remove('open'); burger.setAttribute('aria-expanded', 'false');
  }));

  // scroll progress
  const prog = $('#progress');
  const onScroll = () => {
    const h = document.documentElement.scrollHeight - window.innerHeight;
    prog.style.width = (h > 0 ? (window.scrollY / h) * 100 : 0) + '%';
  };
  window.addEventListener('scroll', onScroll, { passive: true }); onScroll();

  // reveal
  const io = new IntersectionObserver(es => {
    es.forEach(e => { if (e.isIntersecting){ e.target.classList.add('in'); io.unobserve(e.target); } });
  }, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });
  $$('[data-reveal]').forEach((el, i) => { el.style.transitionDelay = (i % 4) * 70 + 'ms'; io.observe(el); });

  // counters
  const cio = new IntersectionObserver(es => {
    es.forEach(e => {
      if (!e.isIntersecting) return;
      const el = e.target, target = +el.dataset.count;
      cio.unobserve(el);
      if (REDUCED){ el.textContent = target; return; }
      const t0 = performance.now(), dur = 1100;
      (function step(t){
        const k = clamp((t - t0) / dur, 0, 1);
        el.textContent = Math.round(target * (1 - Math.pow(1 - k, 3)));
        if (k < 1) requestAnimationFrame(step);
      })(t0);
    });
  }, { threshold: 0.6 });
  $$('[data-count]').forEach(el => cio.observe(el));

  // active nav link
  const secs = ['departures', 'line', 'patrons', 'tickets'].map(id => document.getElementById(id)).filter(Boolean);
  const nio = new IntersectionObserver(es => {
    es.forEach(e => {
      if (!e.isIntersecting) return;
      $$('#navLinks a').forEach(a => a.classList.toggle('active', a.getAttribute('href') === '#' + e.target.id));
    });
  }, { rootMargin: '-45% 0px -50% 0px' });
  secs.forEach(s => nio.observe(s));

  // ambient canvas only runs while hero is visible
  const hero = $('.hero');
  const hio = new IntersectionObserver(es => {
    es.forEach(e => { if (e.isIntersecting) Ambient.start(); else Ambient.stop(); });
  }, { threshold: 0.02 });
  hio.observe(hero);

  // subtle parallax
  if (!REDUCED){
    const sky = $('#sky'), stars = $('.stars');
    hero.addEventListener('pointermove', e => {
      const r = hero.getBoundingClientRect();
      const x = (e.clientX - r.left) / r.width - 0.5;
      const y = (e.clientY - r.top) / r.height - 0.5;
      sky.style.transform = `translate3d(${x * -14}px, ${y * -8}px, 0)`;
      stars.style.transform = `translate3d(${x * 22}px, ${y * 12}px, 0)`;
    }, { passive: true });
    window.addEventListener('scroll', () => {
      const y = window.scrollY;
      if (y < window.innerHeight * 1.2) stars.style.marginTop = (y * 0.12) + 'px';
    }, { passive: true });
  }

  // sound toggle
  const sBtn = $('#soundBtn');
  sBtn.addEventListener('click', () => {
    const on = Sound.toggle();
    sBtn.setAttribute('aria-pressed', String(on));
    $('#wave1').style.opacity = on ? 1 : 0.25;
    $('#wave2').style.opacity = on ? 1 : 0.25;
    if (on){ Ticker.push('Engine ambience on — boiler hiss, exhaust beat and the Gjallarhorn are live.'); Sound.whistle(); }
  });
  $('#wave1').style.opacity = 0.25; $('#wave2').style.opacity = 0.25;

  // keyboard shortcut: W for whistle
  document.addEventListener('keydown', e => {
    if (e.target.matches('input,select,textarea')) return;
    if (e.key.toLowerCase() === 'w' && !e.metaKey && !e.ctrlKey){ Sound.whistle(); Ambient.burst(16); }
  });
})();

})();
</script>
</body>
</html>