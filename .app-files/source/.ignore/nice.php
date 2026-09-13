<?php
declare(strict_types=1);
session_start();

/* ---------- helpers ---------- */
function e(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function mins(string $t): int { [$h, $m] = array_map('intval', explode(':', $t)); return $h * 60 + $m; }

/* ---------- shop config ---------- */
$SHOP = [
    'name'     => 'HAKKO RAMEN HOUSE',
    'jp'       => '発酵らぁめん 白孔',
    'address'  => '2-14 Kōjimachi, Nakagyō-ku, Kyoto',
    'phone'    => '+81 75 231 0984',
    'timezone' => 'Asia/Tokyo',
    'seats'    => 12,
];

/* Sun..Sat, ranges in minutes-friendly strings */
$HOURS = [
    0 => [['11:30','15:00']],
    1 => [],
    2 => [['11:30','14:30'],['17:30','22:00']],
    3 => [['11:30','14:30'],['17:30','22:00']],
    4 => [['11:30','14:30'],['17:30','22:00']],
    5 => [['11:30','14:30'],['17:30','22:00']],
    6 => [['11:30','14:30'],['17:30','22:00']],
];
$DAY_LABELS = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
$DAY_JP     = ['日','月','火','水','木','金','土'];

date_default_timezone_set($SHOP['timezone']);
$now     = new DateTimeImmutable('now');
$today   = (int)$now->format('w');
$curMin  = (int)$now->format('H') * 60 + (int)$now->format('i');

/* open / closed status */
$isOpen = false; $closesAt = ''; $nextLabel = '';
foreach ($HOURS[$today] as $r) {
    if ($curMin >= mins($r[0]) && $curMin < mins($r[1])) { $isOpen = true; $closesAt = $r[1]; break; }
}
if (!$isOpen) {
    foreach ($HOURS[$today] as $r) if (mins($r[0]) > $curMin) { $nextLabel = "today {$r[0]}"; break; }
    if ($nextLabel === '') {
        for ($i = 1; $i <= 7; $i++) {
            $d = ($today + $i) % 7;
            if (!empty($HOURS[$d])) { $nextLabel = "{$DAY_LABELS[$d]} {$HOURS[$d][0][0]}"; break; }
        }
    }
}
$tonightNo = 300 + ((int)$now->format('z') % 64);

/* ---------- menu data ---------- */
$MENU = [
    ['slug'=>'shoyu',   'jp'=>'正油', 'en'=>'Shoyu Classic',    'desc'=>'48-h chicken–asari broth, 12-month soy tare, smoked duck breast.', 'price'=>1100, 'note'=>'kōji 72h', 'spice'=>0, 'tag'=>'SIGNATURE'],
    ['slug'=>'miso',    'jp'=>'味噌', 'en'=>'Miso Ember',       'desc'=>'Red + white miso blend torched with burnt scallion oil.',          'price'=>1250, 'note'=>'kōji 96h', 'spice'=>1, 'tag'=>'RICH'],
    ['slug'=>'shio',    'jp'=>'塩',   'en'=>'Shio Kōji',        'desc'=>'Clear salt bowl cured with house rice-kōji and yuzu peel.',        'price'=>1050, 'note'=>'kōji 72h', 'spice'=>0, 'tag'=>'DELICATE'],
    ['slug'=>'tantan',  'jp'=>'坦々', 'en'=>'Tantan Midnight',  'desc'=>'Black sesame, fermented chili, Sichuan pepper in dark shoyu.',     'price'=>1350, 'note'=>'kōji 120h','spice'=>3, 'tag'=>'SPICY'],
    ['slug'=>'tsukemen','jp'=>'つけ', 'en'=>'Tsukemen Reserve', 'desc'=>'Cold hand-cut noodles, double-strength dipping broth. 14/day.',    'price'=>1400, 'note'=>'kōji 96h', 'spice'=>0, 'tag'=>'LIMITED'],
];
$SIDES = [
    ['jp'=>'焼き餃子','en'=>'Grilled gyoza ×6','price'=>550],
    ['jp'=>'味玉','en'=>'Kōji-cured egg','price'=>180],
    ['jp'=>'黒ニンニク油','en'=>'Black garlic oil shot','price'=>120],
    ['jp'=>'柚子氷','en'=>'Yuzu sorbet','price'=>380],
];
$SEATINGS = ['17:30','18:15','19:00','19:45','20:30','21:15'];

if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$token = $_SESSION['csrf'];

/* ---------- booking handler ---------- */
$errors = []; $booking = null;
$old = ['name'=>'','phone'=>'','date'=>'','time'=>'','party'=>'2','notes'=>''];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($_POST['action'] ?? '') === 'book')) {
    $old = array_intersect_key($_POST, $old) + $old;

    if (!hash_equals($token, (string)($_POST['token'] ?? ''))) $errors[] = 'Session expired — please retry.';
    if (mb_strlen(trim((string)$old['name'])) < 2)              $errors[] = 'Please tell us your name.';
    if (strlen(preg_replace('/\D/', '', (string)$old['phone'])) < 8) $errors[] = 'A reachable phone number is required.';

    $dateOk = false;
    try {
        $d = new DateTimeImmutable((string)$old['date']);
        $min = new DateTimeImmutable('today'); $max = $min->modify('+30 days');
        $dateOk = $d >= $min && $d <= $max;
    } catch (Throwable) {}
    if (!$dateOk) $errors[] = 'Pick a date within the next 30 days.';
    if (!in_array($old['time'], $SEATINGS, true)) $errors[] = 'Choose one of the seating times.';
    if (!in_array((int)$old['party'], range(1, 6), true)) $errors[] = 'Party size is 1–6 guests.';

    if (!$errors) {
        $booking = [
            'code'   => 'HK-' . strtoupper(substr(md5(uniqid('', true)), 0, 5)),
            'name'   => trim((string)$old['name']),
            'date'   => $old['date'],
            'time'   => $old['time'],
            'party'  => (int)$old['party'],
            'placed' => $now->format('Y-m-d H:i'),
        ];
        try {
            file_put_contents(__DIR__ . '/bookings.json',
                json_encode($booking, JSON_UNESCAPED_UNICODE) . PHP_EOL,
                FILE_APPEND | LOCK_EX);
        } catch (Throwable) { /* keep the ticket even if disk fails */ }
    }
}

$dateMin = $now->format('Y-m-d');
$dateMax = $now->modify('+30 days')->format('Y-m-d');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#171310">
<meta name="description" content="HAKKO — small-batch fermented ramen. 48-hour broth, house kōji, 12 seats.">
<title>HAKKO — Fermented Ramen House · Kyoto</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Shippori+Mincho+B1:wght@500;700;800&family=Zen+Kaku+Gothic+New:wght@400;500;700;900&display=swap" rel="stylesheet">
<style>
/* ================= tokens ================= */
:root{
  --ink:#171310; --ink2:#221c17; --paper:#f2e8d5; --paper2:#eaddc2; --card:#faf3e3;
  --shu:#c8492a; --shu-deep:#a13a1f; --gold:#b98a3c;
  --line:rgba(23,19,16,.16); --line-soft:rgba(242,232,213,.18);
  --disp:"Shippori Mincho B1",serif; --body:"Zen Kaku Gothic New",system-ui,sans-serif;
  --tab:64px;
}
*{margin:0;padding:0;box-sizing:border-box;-webkit-tap-highlight-color:transparent}
html{scroll-behavior:smooth}
html,body{max-width:342px}
body{
  margin:0 auto;background:#0d0b09;color:var(--ink);
  font-family:var(--body);font-size:15px;line-height:1.55;
  overflow-x:hidden;overscroll-behavior-y:none;
  box-shadow:0 0 60px rgba(0,0,0,.7);
}
img{display:block;max-width:100%}
a{color:inherit}
button,input,select,textarea{font:inherit;color:inherit}
::selection{background:var(--shu);color:var(--paper)}

/* film grain over everything */
body::after{
  content:"";position:fixed;inset:-40px;pointer-events:none;z-index:120;opacity:.07;
  background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='140' height='140'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.9' numOctaves='2'/%3E%3C/filter%3E%3Crect width='140' height='140' filter='url(%23n)'/%3E%3C/svg%3E");
  mix-blend-mode:multiply;
}

.sec{padding:56px 20px}
.sec-dark{background:var(--ink);color:var(--paper)}
.sec-tint{background:var(--paper2)}
.kicker{font-size:10px;font-weight:700;letter-spacing:.34em;text-transform:uppercase;color:var(--shu);display:flex;align-items:center;gap:10px}
.kicker::after{content:"";flex:1;height:1px;background:currentColor;opacity:.35}
h2{font-family:var(--disp);font-weight:800;font-size:31px;line-height:1.18;margin:12px 0 8px;letter-spacing:.01em}
.jp-side{writing-mode:vertical-rl;text-orientation:upright;font-family:var(--disp)}

/* line-mask reveal */
.lm{display:block;overflow:hidden}
.lm>span{display:block;transform:translateY(112%);transition:transform .9s cubic-bezier(.2,.7,.2,1)}
.js .in .lm>span, .js .lm.in>span{transform:translateY(0)}

/* generic reveal */
.js .reveal{opacity:0;transform:translateY(20px);transition:opacity .7s ease,transform .7s cubic-bezier(.2,.7,.2,1)}
.js .reveal.in{opacity:1;transform:none}
.js .reveal[data-d="2"]{transition-delay:.12s}.js .reveal[data-d="3"]{transition-delay:.24s}

/* ================= topbar ================= */
.topbar{position:absolute;top:0;left:0;right:0;z-index:30;display:flex;justify-content:space-between;align-items:center;padding:14px 16px;color:var(--paper)}
.status{display:flex;align-items:center;gap:8px;font-size:10.5px;font-weight:700;letter-spacing:.14em}
.status .dot{width:7px;height:7px;border-radius:50%;background:#5ec46e;box-shadow:0 0 0 0 rgba(94,196,110,.5);animation:pulse 2s infinite}
.status.closed .dot{background:#8a8177;animation:none}
@keyframes pulse{70%{box-shadow:0 0 0 7px rgba(94,196,110,0)}100%{box-shadow:0 0 0 0 rgba(94,196,110,0)}}
.clock{font-family:var(--disp);font-size:13px;letter-spacing:.12em}
.brandmini{font-family:var(--disp);font-size:13px;letter-spacing:.28em}

/* ================= hero ================= */
.hero{position:relative;min-height:100vh;min-height:100svh;background:var(--ink);color:var(--paper);overflow:hidden;display:flex;flex-direction:column;justify-content:flex-end}
.hero-img{position:absolute;inset:-6%;background:url('https://picsum.photos/seed/ramen-bowl-dark-steam/700/1200') center 30%/cover;filter:brightness(.62) saturate(1.05);animation:breathe 18s ease-in-out infinite alternate}
@keyframes breathe{from{transform:scale(1.04)}to{transform:scale(1.15) translateY(-10px)}}
.hero-img::after{content:"";position:absolute;inset:0;background:linear-gradient(180deg,rgba(23,19,16,.72),rgba(23,19,16,.15) 40%,rgba(23,19,16,.92) 88%)}
.hero-vtx{position:absolute;top:74px;right:14px;z-index:5;font-size:26px;letter-spacing:.42em;color:var(--paper);opacity:.92;text-shadow:0 2px 14px rgba(0,0,0,.6)}
.hero-inner{position:relative;z-index:6;padding:0 20px 96px}
.steam{position:absolute;z-index:5;left:52%;bottom:220px;width:70px;height:120px;pointer-events:none}
.steam i{position:absolute;bottom:0;width:16px;height:64px;border-radius:50%;background:radial-gradient(closest-side,rgba(242,232,213,.5),transparent 70%);filter:blur(6px);animation:rise 4.5s ease-in infinite}
.steam i:nth-child(2){left:26px;animation-delay:1.4s;height:80px}
.steam i:nth-child(3){left:50px;animation-delay:2.7s}
@keyframes rise{0%{transform:translateY(14px) scaleX(1);opacity:0}25%{opacity:.9}100%{transform:translateY(-110px) translateX(10px) scaleX(1.7);opacity:0}}
.hero h1{font-family:var(--disp);font-weight:800;font-size:76px;line-height:.95;letter-spacing:.02em}
.hero h1 .scr{display:inline-block;min-width:.62em;text-align:center}
.hero-sub{margin-top:12px;font-size:11px;font-weight:700;letter-spacing:.34em;color:var(--gold)}
.hero-jp{margin-top:6px;font-family:var(--disp);font-size:13px;letter-spacing:.4em;opacity:.85}
.stamp{position:absolute;z-index:7;right:22px;bottom:150px;width:64px;height:64px;border-radius:50%;background:var(--shu);color:var(--paper);display:grid;place-items:center;font-family:var(--disp);font-size:30px;font-weight:800;transform:rotate(-9deg);box-shadow:0 6px 18px rgba(0,0,0,.4),inset 0 0 0 2px rgba(242,232,213,.85);animation:stampIn 1s .9s cubic-bezier(.2,1.4,.4,1) both}
@keyframes stampIn{from{transform:rotate(-30deg) scale(2.4);opacity:0}to{transform:rotate(-9deg) scale(1);opacity:1}}
.hero-chip{display:inline-flex;gap:8px;align-items:center;margin-top:20px;padding:8px 12px;border:1px solid var(--line-soft);font-size:10px;letter-spacing:.22em;font-weight:700;color:var(--paper)}
.hero-chip b{color:var(--gold)}
.scrollcue{position:absolute;left:20px;bottom:26px;z-index:7;display:flex;align-items:center;gap:10px;font-size:9px;letter-spacing:.36em;color:rgba(242,232,213,.75)}
.scrollcue i{width:1px;height:34px;background:linear-gradient(var(--paper),transparent);animation:drip 1.8s ease-in-out infinite}
@keyframes drip{0%{transform:scaleY(0);transform-origin:top}45%{transform:scaleY(1);transform-origin:top}55%{transform:scaleY(1);transform-origin:bottom}100%{transform:scaleY(0);transform-origin:bottom}}

/* ================= ticker ================= */
.ticker{background:var(--shu);color:var(--paper);overflow:hidden;border-block:1px solid rgba(0,0,0,.25)}
.ticker-track{display:flex;width:max-content;animation:tick 26s linear infinite}
.ticker span{font-family:var(--disp);font-size:12px;font-weight:700;letter-spacing:.24em;padding:9px 0;white-space:nowrap}
@keyframes tick{to{transform:translateX(-50%)}}

/* ================= story: sticky stacked cards ================= */
.stack{display:grid;gap:16px;margin-top:24px}
.stack article{position:sticky;background:var(--card);border:1px solid var(--line);padding:16px;box-shadow:0 14px 30px rgba(23,19,16,.14)}
.stack article:nth-child(1){top:64px}
.stack article:nth-child(2){top:78px}
.stack article:nth-child(3){top:92px}
.stack .idx{font-family:var(--disp);color:var(--shu);font-size:13px;font-weight:800;letter-spacing:.3em}
.stack h3{font-family:var(--disp);font-size:23px;font-weight:800;margin:6px 0 8px}
.stack p{font-size:13.5px;color:#4c4338}
.stack img{margin-top:12px;width:100%;height:170px;object-fit:cover;filter:sepia(.18) contrast(1.02)}

/* ================= numbers ================= */
.nums{display:grid;grid-template-columns:1fr 1fr}
.nums div{padding:22px 8px;text-align:center;border:1px solid var(--line-soft);margin:-.5px}
.nums b{display:block;font-family:var(--disp);font-weight:800;font-size:42px;line-height:1;color:var(--paper)}
.nums b small{font-size:18px;color:var(--gold)}
.nums span{display:block;margin-top:8px;font-size:9.5px;letter-spacing:.28em;text-transform:uppercase;color:rgba(242,232,213,.65)}

/* ================= menu ================= */
.rail{display:flex;gap:12px;overflow-x:auto;scroll-snap-type:x mandatory;padding:22px 20px 26px;margin:0 -20px;scrollbar-width:none}
.rail::-webkit-scrollbar{display:none}
.dish{flex:0 0 264px;scroll-snap-align:start;background:var(--card);border:1px solid var(--line);box-shadow:0 12px 26px rgba(23,19,16,.12)}
.dish figure{position:relative;height:186px;overflow:hidden}
.dish img{width:100%;height:100%;object-fit:cover;transition:transform 1.2s ease}
.dish:active img{transform:scale(1.06)}
.dish .tag{position:absolute;top:10px;left:10px;background:var(--shu);color:var(--paper);font-size:9px;font-weight:900;letter-spacing:.24em;padding:5px 8px}
.dish .body{padding:14px}
.dish h3{font-family:var(--disp);font-size:20px;font-weight:800;display:flex;justify-content:space-between;align-items:baseline}
.dish h3 .pr{font-family:var(--body);font-weight:900;font-size:15px;color:var(--shu)}
.dish .jp{font-size:11px;letter-spacing:.4em;color:#8b7c65;margin:2px 0 8px}
.dish p{font-size:12.5px;color:#544a3d;min-height:56px}
.dish .meta{display:flex;justify-content:space-between;margin-top:10px;padding-top:10px;border-top:1px dashed var(--line);font-size:10px;letter-spacing:.16em;font-weight:700;color:#8b7c65}
.spice i{display:inline-block;width:7px;height:7px;border-radius:50%;background:var(--line);margin-left:3px}
.spice i.on{background:var(--shu)}
.drag-hint{display:flex;align-items:center;gap:8px;font-size:10px;letter-spacing:.3em;font-weight:700;color:#8b7c65}
.drag-hint b{animation:nudge 1.6s ease-in-out infinite;display:inline-block}
@keyframes nudge{50%{transform:translateX(6px)}}
.sides{margin-top:26px;border-top:1px solid var(--line)}
.sides li{display:flex;align-items:baseline;gap:8px;padding:13px 2px;border-bottom:1px solid var(--line);font-size:13.5px}
.sides .l{font-weight:700}.sides .l small{display:block;font-weight:400;font-size:11px;color:#8b7c65;letter-spacing:.2em}
.sides .dots{flex:1;border-bottom:2px dotted rgba(23,19,16,.3);transform:translateY(-4px)}
.sides .p{font-family:var(--disp);font-weight:800;color:var(--shu)}

/* ================= craft timeline ================= */
.steps{position:relative;margin-top:28px;padding-left:30px}
.steps::before{content:"";position:absolute;left:8px;top:6px;bottom:6px;width:1px;background:var(--line-soft)}
#railFill{position:absolute;left:8px;top:6px;width:1px;height:0;background:var(--shu);transition:height .2s linear}
.step{position:relative;padding:0 0 34px}
.step::before{content:"";position:absolute;left:-26px;top:8px;width:9px;height:9px;border-radius:50%;background:var(--ink);border:1px solid var(--line-soft);transition:.3s}
.step.on::before{background:var(--shu);border-color:var(--shu);box-shadow:0 0 0 4px rgba(200,73,42,.25)}
.step .n{font-family:var(--disp);font-weight:800;font-size:34px;color:var(--gold);line-height:1}
.step h3{font-family:var(--disp);font-size:20px;font-weight:800;margin:6px 0 4px}
.step p{font-size:13px;color:rgba(242,232,213,.72)}

/* ================= gallery postcards ================= */
.polas{position:relative;height:560px;margin-top:18px}
.pola{position:absolute;background:#fdf8ec;padding:8px 8px 30px;box-shadow:0 16px 30px rgba(23,19,16,.22);cursor:pointer;transition:transform .5s cubic-bezier(.2,.9,.3,1.2),box-shadow .3s;will-change:transform}
.pola img{width:100%;height:100%;object-fit:cover}
.pola figcaption{position:absolute;bottom:6px;left:0;right:0;text-align:center;font-family:var(--disp);font-size:11px;letter-spacing:.24em;color:#6b5f4d}
.pola::before{content:"";position:absolute;top:-9px;left:50%;width:52px;height:16px;background:rgba(200,73,42,.5);transform:translateX(-50%) rotate(-3deg)}
.pola.top{box-shadow:0 26px 44px rgba(23,19,16,.34)}
.p1{top:6px;left:4px;width:200px;transform:rotate(-6deg)}
.p2{top:88px;right:0;width:176px;transform:rotate(5deg)}
.p3{top:250px;left:34px;width:222px;transform:rotate(-3deg)}
.p4{top:400px;right:8px;width:186px;transform:rotate(7deg)}

/* ================= booking ================= */
#book .frame{background:var(--card);border:1px solid var(--line);padding:22px 18px;margin-top:22px;box-shadow:0 14px 30px rgba(23,19,16,.14)}
.frow{margin-bottom:18px}
.frow label{display:block;font-size:9.5px;font-weight:900;letter-spacing:.3em;text-transform:uppercase;color:#8b7c65;margin-bottom:6px}
.frow input,.frow select,.frow textarea{width:100%;background:transparent;border:0;border-bottom:1px solid var(--line);padding:9px 2px;font-size:16px;outline:0;transition:border-color .2s}
.frow input:focus,.frow select:focus,.frow textarea:focus{border-color:var(--shu)}
.frow textarea{resize:none;height:56px}
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.party{display:flex;gap:6px}
.party button{flex:1;padding:10px 0;border:1px solid var(--line);background:transparent;font-weight:700;cursor:pointer;transition:.2s}
.party button.sel{background:var(--ink);color:var(--paper);border-color:var(--ink)}
.btn{position:relative;overflow:hidden;width:100%;border:0;background:var(--shu);color:var(--paper);font-weight:900;font-size:13px;letter-spacing:.3em;text-transform:uppercase;padding:17px;cursor:pointer;box-shadow:0 8px 0 var(--shu-deep);transition:transform .1s,box-shadow .1s}
.btn:active{transform:translateY(6px);box-shadow:0 2px 0 var(--shu-deep)}
.ripple{position:absolute;border-radius:50%;background:rgba(255,255,255,.4);transform:scale(0);animation:rip .5s ease-out forwards;pointer-events:none}
@keyframes rip{to{transform:scale(3.2);opacity:0}}
.errlist{margin:0 0 16px;padding:12px 14px;background:rgba(200,73,42,.1);border-left:3px solid var(--shu);font-size:12.5px;color:var(--shu-deep);list-style:none}
.errlist li::before{content:"▲ ";font-size:8px;vertical-align:2px}
.shake{animation:shake .4s}
@keyframes shake{25%{transform:translateX(-5px)}75%{transform:translateX(5px)}}
/* ticket */
.ticket{text-align:center;padding:8px 4px}
.ticket .code{font-family:var(--disp);font-size:40px;font-weight:800;letter-spacing:.06em;margin:8px 0 2px}
.ticket .meta{font-size:13px;color:#544a3d;line-height:1.9}
.ticket .note{margin-top:14px;font-size:11px;letter-spacing:.14em;color:#8b7c65}
.bigstamp{width:86px;height:86px;margin:18px auto 4px;border-radius:50%;background:var(--shu);color:var(--paper);display:grid;place-items:center;font-family:var(--disp);font-size:38px;font-weight:800;transform:rotate(-10deg);box-shadow:inset 0 0 0 3px rgba(242,232,213,.85);animation:stampIn .7s .15s cubic-bezier(.2,1.4,.4,1) both}
.again{display:inline-block;margin-top:16px;font-size:11px;font-weight:700;letter-spacing:.24em;color:var(--shu);border-bottom:1px solid currentColor;text-decoration:none;padding-bottom:2px}

/* ================= footer ================= */
footer{position:relative;background:var(--ink);color:var(--paper);padding:52px 20px calc(var(--tab) + 40px);overflow:hidden}
.hrs{margin:20px 0 26px;border-top:1px solid var(--line-soft)}
.hrs div{display:flex;justify-content:space-between;gap:10px;padding:9px 2px;border-bottom:1px solid var(--line-soft);font-size:12.5px;letter-spacing:.08em}
.hrs .d{display:flex;gap:10px;align-items:center}
.hrs .d em{font-style:normal;font-family:var(--disp);color:var(--gold);width:16px}
.hrs .today{color:var(--gold);font-weight:700}
.hrs .today::after{content:"TODAY";font-size:8px;letter-spacing:.3em;background:var(--shu);color:var(--paper);padding:2px 6px;align-self:center;margin-left:6px}
footer address{font-style:normal;font-size:13px;line-height:1.9;color:rgba(242,232,213,.8)}
footer .tel{display:inline-block;margin-top:10px;font-family:var(--disp);font-size:19px;letter-spacing:.1em;color:var(--paper);text-decoration:none;border-bottom:1px solid var(--gold);padding-bottom:2px}
.foot-vtx{position:absolute;right:-8px;top:40px;font-size:64px;letter-spacing:.3em;color:rgba(242,232,213,.06);pointer-events:none}
.credit{margin-top:34px;font-size:10px;letter-spacing:.22em;color:rgba(242,232,213,.4)}

/* ================= tab bar ================= */
.tabbar{position:fixed;bottom:0;left:50%;transform:translateX(-50%);width:100%;max-width:342px;z-index:100;display:grid;grid-template-columns:repeat(4,1fr);background:rgba(23,19,16,.94);backdrop-filter:blur(8px);border-top:1px solid var(--line-soft);padding:8px 6px calc(8px + env(safe-area-inset-bottom))}
.tabbar a{display:flex;flex-direction:column;align-items:center;gap:3px;text-decoration:none;color:rgba(242,232,213,.55);font-size:8.5px;font-weight:700;letter-spacing:.2em;padding:6px 0;transition:color .25s}
.tabbar a em{font-style:normal;font-family:var(--disp);font-size:16px;line-height:1}
.tabbar a.act{color:var(--paper)}
.tabbar a.act em{color:var(--shu)}
.tabbar a.go{color:var(--paper)}
.tabbar a.go em{color:var(--paper);background:var(--shu);border-radius:50%;width:30px;height:30px;display:grid;place-items:center;margin-top:-14px;box-shadow:0 4px 12px rgba(200,73,42,.5)}

@media (prefers-reduced-motion: reduce){
  *,*::before,*::after{animation-duration:.01ms !important;animation-iteration-count:1 !important;transition-duration:.01ms !important}
  html{scroll-behavior:auto}
  .hero-img{animation:none;transform:scale(1.05)}
  .ticker-track{animation:none;flex-wrap:wrap}
  .js .reveal,.lm>span{opacity:1;transform:none}
}

/* ══ REAL-PHONE FIX ══════════════════════════════════════════════
   The 342px cap on html/body (and the tab bar) was designed to make
   the site look like a phone app when viewed on a wide desktop
   screen. But real phones are 360–430px wide, so the cap left a
   black band on the right edge. On screens up to 430px we remove
   the cap so the page uses the full screen; on desktops nothing
   changes (the narrow centered column stays).                      */
@media (max-width:430px){
  html,body{max-width:none}
  .tabbar{max-width:none}
}
</style>
</head>
<body>

<!-- ================= HERO ================= -->
<header class="hero" id="top">
  <div class="hero-img" aria-hidden="true"></div>

  <div class="topbar">
    <div class="status <?= $isOpen ? '' : 'closed' ?>">
      <span class="dot"></span>
      <?= $isOpen ? 'OPEN — TILL ' . e($closesAt) : 'CLOSED — ' . e($nextLabel) ?>
    </div>
    <span class="clock" id="clock">--:--</span>
    <span class="brandmini">白孔</span>
  </div>

  <span class="hero-vtx jp-side" aria-hidden="true">発酵らぁめん</span>
  <div class="steam" aria-hidden="true"><i></i><i></i><i></i></div>
  <div class="stamp" aria-hidden="true">醸</div>

  <div class="hero-inner">
    <h1 id="heroTitle" aria-label="HAKKO">
      <span class="scr" data-ch="H">H</span><span class="scr" data-ch="A">A</span><span class="scr" data-ch="K">K</span><span class="scr" data-ch="K">K</span><span class="scr" data-ch="O">O</span>
    </h1>
    <p class="hero-sub">RAMEN HOUSE · KYOTO</p>
    <p class="hero-jp">麹から始まる一杯 — a bowl that begins with kōji.</p>
    <p class="hero-chip">TONIGHT <b>№<?= $tonightNo ?></b> · 5 BOWLS · <?= $SHOP['seats'] ?> SEATS</p>
  </div>

  <div class="scrollcue"><i></i>SWIPE</div>
</header>

<!-- ================= TICKER ================= -->
<div class="ticker" aria-hidden="true">
  <div class="ticker-track">
    <?php for ($i = 0; $i < 2; $i++): ?>
      <span>&nbsp;手打ち麺 HAND-CUT&nbsp;✦&nbsp;48H BROTH&nbsp;✦&nbsp;米麹 HOUSE KŌJI&nbsp;✦&nbsp;12 SEATS ONLY&nbsp;✦&nbsp;発酵 FERMENTED&nbsp;✦&nbsp;NO RAMEN WITHOUT PATIENCE&nbsp;✦&nbsp;</span>
    <?php endfor; ?>
  </div>
</div>

<!-- ================= STORY ================= -->
<section class="sec" id="story">
  <p class="kicker reveal">物語 — The Story</p>
  <h2><span class="lm"><span>Slow broth,</span></span><span class="lm"><span>fast city.</span></span></h2>
  <p class="reveal" data-d="2" style="font-size:13.5px;color:#544a3d;max-width:290px">Three rooms, one pot, and a mould that does most of the work. Scroll through the house.</p>

  <div class="stack">
    <article>
      <span class="idx">01 — 麹室</span>
      <h3>The Kōji Room</h3>
      <p>Every bowl starts in a cedar-lined room at 30°C. Rice is inoculated with kōji spores and turned by hand every four hours for three days, until it smells of chestnut and rain.</p>
      <img src="https://picsum.photos/seed/koji-room-cedar-trays/600/400" alt="Kōji room with cedar trays" loading="lazy">
    </article>
    <article>
      <span class="idx">02 — 鍋</span>
      <h3>The Pot</h3>
      <p>Chicken carcass, asari clams and roasted kombu simmer for 48 hours — rolled, never boiled — into a broth with the weight of cream and the clarity of dashi.</p>
      <img src="https://picsum.photos/seed/ramen-broth-pot-steam/600/400" alt="Broth pot simmering" loading="lazy">
    </article>
    <article>
      <span class="idx">03 — 席</span>
      <h3>The Counter</h3>
      <p>Twelve seats face the pot. Each bowl is finished to order in ninety seconds: tare, noodles, broth, aroma oil — then it crosses the counter to you, still trembling.</p>
      <img src="https://picsum.photos/seed/ramen-counter-chef/600/400" alt="Ramen counter" loading="lazy">
    </article>
  </div>
</section>

<!-- ================= NUMBERS ================= -->
<section class="sec sec-dark" style="padding-block:36px">
  <div class="nums">
    <div class="reveal"><b><span data-count="48">0</span><small>h</small></b><span>broth simmer</span></div>
    <div class="reveal" data-d="2"><b><span data-count="72">0</span><small>h</small></b><span>kōji culture</span></div>
    <div class="reveal"><b data-count="27">0</b><span>ingredients</span></div>
    <div class="reveal" data-d="2"><b data-count="12">0</b><span>counter seats</span></div>
  </div>
</section>

<!-- ================= MENU ================= -->
<section class="sec sec-tint" id="menu">
  <p class="kicker reveal">献立 — Menu</p>
  <h2><span class="lm"><span>Five bowls,</span></span><span class="lm"><span>one obsession.</span></span></h2>
  <p class="drag-hint" style="margin-top:10px">DRAG <b>→</b></p>

  <div class="rail">
    <?php foreach ($MENU as $m): ?>
      <article class="dish reveal">
        <figure>
          <img src="https://picsum.photos/seed/ramen-<?= e($m['slug']) ?>/528/380" alt="<?= e($m['en']) ?>" loading="lazy">
          <span class="tag"><?= e($m['tag']) ?></span>
        </figure>
        <div class="body">
          <h3><?= e($m['en']) ?><span class="pr">¥<?= number_format($m['price']) ?></span></h3>
          <p class="jp"><?= e($m['jp']) ?></p>
          <p><?= e($m['desc']) ?></p>
          <div class="meta">
            <span><?= e($m['note']) ?></span>
            <span class="spice" aria-label="spice level <?= $m['spice'] ?> of 3">
              <?php for ($s = 1; $s <= 3; $s++): ?><i class="<?= $s <= $m['spice'] ? 'on' : '' ?>"></i><?php endfor; ?>
            </span>
          </div>
        </div>
      </article>
    <?php endforeach; ?>
  </div>

  <p class="kicker reveal" style="margin-top:8px">小皿 — Sides</p>
  <ul class="sides reveal">
    <?php foreach ($SIDES as $s): ?>
      <li><span class="l"><?= e($s['en']) ?><small><?= e($s['jp']) ?></small></span><span class="dots"></span><span class="p">¥<?= $s['price'] ?></span></li>
    <?php endforeach; ?>
  </ul>
</section>

<!-- ================= CRAFT ================= -->
<section class="sec sec-dark" id="craft">
  <p class="kicker reveal">工程 — Craft</p>
  <h2><span class="lm"><span>Built in</span></span><span class="lm"><span>five movements.</span></span></h2>

  <div class="steps" id="steps">
    <i id="railFill"></i>
    <?php
    $STEPS = [
      ['Kōji','Rice cultured 72 hours, ground into shio-kōji for sweetness and depth.'],
      ['Broth','48-hour simmer of chicken, clams and kombu — rolled, never boiled.'],
      ['Tare','Soy aged twelve months in cedar, seasoned with dried scallop.'],
      ['Noodles','Hand-cut at dawn, two gauges: fine for the bowl, thick for tsukemen.'],
      ['Aroma','A final pour of black garlic oil, burnt just shy of bitter.'],
    ];
    foreach ($STEPS as $i => $st): ?>
      <div class="step reveal">
        <span class="n"><?= str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
        <h3><?= e($st[0]) ?></h3>
        <p><?= e($st[1]) ?></p>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<!-- ================= GALLERY ================= -->
<section class="sec" id="gallery" style="overflow:hidden">
  <p class="kicker reveal">断片 — Fragments</p>
  <h2><span class="lm"><span>From the</span></span><span class="lm"><span>counter.</span></span></h2>

  <div class="polas">
    <figure class="pola p1" data-speed="0.05"><img src="https://picsum.photos/seed/ramen-steam-closeup/400/300" alt="Steam over a bowl" loading="lazy"><figcaption>最初の湯気</figcaption></figure>
    <figure class="pola p2" data-speed="0.09"><img src="https://picsum.photos/seed/koji-trays-dark/340/420" alt="Kōji trays" loading="lazy"><figcaption>麹室 03:00</figcaption></figure>
    <figure class="pola p3" data-speed="0.06"><img src="https://picsum.photos/seed/ramen-chef-tweezers/440/300" alt="Chef finishing a bowl" loading="lazy"><figcaption>九十秒</figcaption></figure>
    <figure class="pola p4" data-speed="0.1"><img src="https://picsum.photos/seed/kyoto-alley-night/360/300" alt="Alley outside the shop" loading="lazy"><figcaption>小路の灯り</figcaption></figure>
  </div>
</section>

<!-- ================= BOOKING ================= -->
<section class="sec sec-tint" id="book" style="padding-bottom:80px">
  <p class="kicker reveal">予約 — Reserve</p>
  <h2><span class="lm"><span>Claim your</span></span><span class="lm"><span>twelve seats.</span></span></h2>

  <div class="frame">
    <?php if ($booking): ?>
      <div class="ticket" role="status">
        <div class="bigstamp">可</div>
        <p style="font-size:10px;letter-spacing:.3em;font-weight:900;color:#8b7c65">RESERVATION CONFIRMED</p>
        <p class="code"><?= e($booking['code']) ?></p>
        <p class="meta">
          <b><?= e($booking['name']) ?></b><br>
          <?= e(date('D d M', strtotime($booking['date']))) ?> · <?= e($booking['time']) ?><br>
          <?= $booking['party'] ?> guest<?= $booking['party'] > 1 ? 's' : '' ?> at the counter
        </p>
        <p class="note">We hold seats for 10 minutes past seating time.<br>Bowls go out hot — please arrive hungry.</p>
        <a class="again" href="<?= e(basename((string)($_SERVER['SCRIPT_NAME'] ?? 'index.php'))) ?>#book">MAKE ANOTHER</a>
      </div>
    <?php else: ?>
      <?php if ($errors): ?>
        <ul class="errlist shake"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul>
      <?php endif; ?>

      <form method="post" action="#book" novalidate id="bookForm">
        <input type="hidden" name="action" value="book">
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <input type="hidden" name="party" id="partyVal" value="<?= e($old['party']) ?>">

        <div class="frow">
          <label for="f-name">Name</label>
          <input id="f-name" name="name" required minlength="2" value="<?= e($old['name']) ?>" placeholder="Aiko Tanaka">
        </div>
        <div class="frow">
          <label for="f-phone">Phone</label>
          <input id="f-phone" name="phone" type="tel" required value="<?= e($old['phone']) ?>" placeholder="+81 …">
        </div>
        <div class="grid2">
          <div class="frow">
            <label for="f-date">Date</label>
            <input id="f-date" name="date" type="date" required min="<?= $dateMin ?>" max="<?= $dateMax ?>" value="<?= e($old['date']) ?>">
          </div>
          <div class="frow">
            <label for="f-time">Seating</label>
            <select id="f-time" name="time" required>
              <option value="" disabled <?= $old['time'] === '' ? 'selected' : '' ?>>—</option>
              <?php foreach ($SEATINGS as $t): ?>
                <option value="<?= e($t) ?>" <?= $old['time'] === $t ? 'selected' : '' ?>><?= e($t) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="frow">
          <label>Guests</label>
          <div class="party" id="party">
            <?php for ($p = 1; $p <= 6; $p++): ?>
              <button type="button" data-v="<?= $p ?>" class="<?= (int)$old['party'] === $p ? 'sel' : '' ?>"><?= $p ?></button>
            <?php endfor; ?>
          </div>
        </div>
        <div class="frow">
          <label for="f-notes">Notes <span style="opacity:.5;text-transform:none;letter-spacing:.05em">(allergies, occasions)</span></label>
          <textarea id="f-notes" name="notes" placeholder="…"><?= e($old['notes']) ?></textarea>
        </div>
        <button class="btn" type="submit">Reserve a seat</button>
      </form>
    <?php endif; ?>
  </div>
</section>

<!-- ================= FOOTER ================= -->
<footer>
  <span class="foot-vtx jp-side" aria-hidden="true">白孔発酵</span>
  <p class="kicker" style="color:var(--gold)">所在 — Find Us</p>
  <h2 style="color:var(--paper);margin-bottom:0"><span class="lm"><span><?= e($SHOP['jp']) ?></span></span></h2>

  <div class="hrs">
    <?php for ($d = 1; $d <= 7; $d++): $wd = $d % 7; $ranges = $HOURS[$wd];
      $label = $ranges ? implode(' / ', array_map(fn($r) => "{$r[0]}–{$r[1]}", $ranges)) : 'Closed'; ?>
      <div class="<?= $wd === $today ? 'today' : '' ?>">
        <span class="d"><em><?= e($DAY_JP[$wd]) ?></em><?= e($DAY_LABELS[$wd]) ?></span>
        <span><?= e($label) ?></span>
      </div>
    <?php endfor; ?>
  </div>

  <address>
    <?= e($SHOP['address']) ?><br>
    Last order 30 min before close · Counter only, no groups over 6.
  </address>
  <a class="tel" href="tel:<?= e(preg_replace('/\s/', '', $SHOP['phone'])) ?>"><?= e($SHOP['phone']) ?></a>

  <p class="credit">© <?= $now->format('Y') ?> HAKKO RAMEN HOUSE — brewed at <?= e($now->format('H:i')) ?> JST</p>
</footer>

<!-- ================= TAB BAR ================= -->
<nav class="tabbar" aria-label="Sections">
  <a href="#story" data-tab="story"><em>話</em>STORY</a>
  <a href="#menu" data-tab="menu"><em>献</em>MENU</a>
  <a href="#craft" data-tab="craft"><em>工</em>CRAFT</a>
  <a href="#book" class="go" data-tab="book"><em>予</em>BOOK</a>
</nav>

<script>
(function () {
  document.documentElement.classList.add('js');
  var RM = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ---- live clock (shop timezone via PHP-rendered offset) ---- */
  var clock = document.getElementById('clock');
  function tick() {
    var d = new Date();
    var jst = new Date(d.toLocaleString('en-US', { timeZone: 'Asia/Tokyo' }));
    clock.textContent = String(jst.getHours()).padStart(2, '0') + ':' + String(jst.getMinutes()).padStart(2, '0');
  }
  tick(); setInterval(tick, 15000);

  /* ---- scramble-decode hero title ---- */
  var GLYPHS = 'アイウエオカキクケコサシスセソ発酵麺湯0123456789';
  var scrs = document.querySelectorAll('.scr');
  if (!RM) {
    scrs.forEach(function (el, i) {
      var target = el.dataset.ch, frame = 0, total = 14 + i * 6;
      (function run() {
        if (frame >= total) { el.textContent = target; return; }
        el.textContent = GLYPHS[Math.floor(Math.random() * GLYPHS.length)];
        frame++; setTimeout(run, 46);
      })();
    });
  }

  /* ---- reveals + line masks ---- */
  var io = new IntersectionObserver(function (es) {
    es.forEach(function (e) { if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target); } });
  }, { threshold: 0.18 });
  document.querySelectorAll('.reveal, .lm').forEach(function (el) { io.observe(el); });

  /* ---- counters ---- */
  var cio = new IntersectionObserver(function (es) {
    es.forEach(function (e) {
      if (!e.isIntersecting) return;
      var el = e.target, end = +el.dataset.count, t0 = null, dur = RM ? 1 : 1300;
      (function step(ts) {
        if (!t0) t0 = ts;
        var p = Math.min((ts - t0) / dur, 1);
        el.textContent = Math.round(end * (1 - Math.pow(1 - p, 3)));
        if (p < 1) requestAnimationFrame(step);
      })(performance.now());
      cio.unobserve(el);
    });
  }, { threshold: 0.6 });
  document.querySelectorAll('[data-count]').forEach(function (el) { cio.observe(el); });

  /* ---- tab bar active state ---- */
  var tabs = document.querySelectorAll('.tabbar a');
  var sio = new IntersectionObserver(function (es) {
    es.forEach(function (e) {
      if (!e.isIntersecting) return;
      tabs.forEach(function (t) { t.classList.toggle('act', t.dataset.tab === e.target.id); });
    });
  }, { rootMargin: '-35% 0px -55% 0px' });
  ['story', 'menu', 'craft', 'book'].forEach(function (id) {
    var s = document.getElementById(id); if (s) sio.observe(s);
  });

  /* ---- craft rail progress + step lights ---- */
  var stepsWrap = document.getElementById('steps'), fill = document.getElementById('railFill');
  var steps = stepsWrap.querySelectorAll('.step');
  function rail() {
    var r = stepsWrap.getBoundingClientRect(), vh = innerHeight;
    var p = Math.min(Math.max((vh * 0.7 - r.top) / r.height, 0), 1);
    fill.style.height = (p * 100) + '%';
    steps.forEach(function (s) {
      s.classList.toggle('on', s.getBoundingClientRect().top < vh * 0.7);
    });
  }

  /* ---- postcard parallax + tap-to-front ---- */
  var polas = document.querySelectorAll('.pola'), zTop = 10;
  function parallax() {
    if (RM) return;
    var base = document.querySelector('.polas').getBoundingClientRect();
    polas.forEach(function (p) {
      var y = (base.top + base.offsetHeight / 2 - innerHeight / 2) * -parseFloat(p.dataset.speed);
      var r = p.classList.contains('top') ? ' scale(1.05)' : '';
      p.style.transform = getComputedStyle(p).transform === 'none' ? '' : '';
      p.style.marginTop = y + 'px';
    });
  }
  polas.forEach(function (p) {
    p.addEventListener('click', function () {
      polas.forEach(function (q) { q.classList.remove('top'); });
      p.classList.add('top'); p.style.zIndex = ++zTop;
    });
  });

  var ticking = false;
  addEventListener('scroll', function () {
    if (ticking) return; ticking = true;
    requestAnimationFrame(function () { rail(); parallax(); ticking = false; });
  }, { passive: true });
  rail();

  /* ---- party selector ---- */
  var partyVal = document.getElementById('partyVal');
  document.querySelectorAll('#party button').forEach(function (b) {
    b.addEventListener('click', function () {
      document.querySelectorAll('#party button').forEach(function (x) { x.classList.remove('sel'); });
      b.classList.add('sel'); partyVal.value = b.dataset.v;
    });
  });

  /* ---- ripple on buttons ---- */
  document.querySelectorAll('.btn, .party button').forEach(function (b) {
    b.addEventListener('pointerdown', function (ev) {
      if (RM) return;
      var r = b.getBoundingClientRect(), s = document.createElement('span');
      s.className = 'ripple';
      var size = Math.max(r.width, r.height);
      s.style.width = s.style.height = size + 'px';
      s.style.left = (ev.clientX - r.left - size / 2) + 'px';
      s.style.top = (ev.clientY - r.top - size / 2) + 'px';
      b.appendChild(s);
      s.addEventListener('animationend', function () { s.remove(); });
    });
  });

  /* ---- client-side guard before PHP validates ---- */
  var form = document.getElementById('bookForm');
  if (form) form.addEventListener('submit', function (ev) {
    var bad = false;
    form.querySelectorAll('[required]').forEach(function (f) {
      if (!f.value) { bad = true; f.style.borderColor = 'var(--shu)'; }
    });
    if (bad) {
      ev.preventDefault();
      form.classList.remove('shake'); void form.offsetWidth; form.classList.add('shake');
    }
  });
})();
</script>
</body>
</html>