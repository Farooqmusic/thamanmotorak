<?php
declare(strict_types=1);

/* ============================================================================
   concept.php — "تصميم اليوم / Concept of the day"

   A full-bleed concept-car picture that fills the middle of the page while the
   maroon header stays on top and the navigation bar stays at the bottom.
   One gold button lifts the picture away; the valuation form is already
   underneath it.

   The picture changes CONCEPT_PER_DAY times every 24 hours (3 = a new car
   every 8 hours, Doha time). Everyone who opens the site inside the same slot
   sees the same car, so it still feels like a deliberate design of the moment
   rather than a random picture. No cron, no database.

   Drop a new pair of pictures in (name.jpg + name@sm.jpg, plus the .webp
   twins if you have them) and it joins the rotation by itself.
   ============================================================================ */

/* lib.php already provides e(); this keeps concept.php usable on its own. */
if (!function_exists('e')) {
    function e(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

const CONCEPT_DIR     = 'assets/concepts';  // relative to this file
const CONCEPT_TZ      = 'Asia/Qatar';       // slots are counted in Doha time
const CONCEPT_EPOCH   = '2026-01-01';       // slot 0 starts at midnight on this date

/* How many different cars a single day shows.
      1 = one a day        2 = every 12 hours
      3 = every 8 hours    4 = every 6 hours
   Change this one number and nothing else. */
const CONCEPT_PER_DAY = 3;

/* ---------------------------------------------------------------- the pool */
function concept_pool(): array
{
    static $pool = null;
    if ($pool !== null) return $pool;

    $pool = [];
    foreach (glob(__DIR__ . '/' . CONCEPT_DIR . '/*.jpg') ?: [] as $p) {
        $base = basename($p, '.jpg');
        if (substr($base, -3) === '@sm') continue;          // the small twin
        $pool[] = $base;
    }
    sort($pool, SORT_NATURAL);
    return $pool;
}

/* ------------------------------------------------------- the fixed running order */
function concept_order(): array
{
    /* Shuffle once, deterministically, seeded by the size of the pool, so the
       order is not simply car1, car2, car3 … but is identical on every request
       and on every server. */
    $order = concept_pool();
    $n = count($order);
    if ($n < 2) return $order;
    mt_srand($n * 7919);
    for ($i = $n - 1; $i > 0; $i--) {
        $j = mt_rand(0, $i);
        $t = $order[$i]; $order[$i] = $order[$j]; $order[$j] = $t;
    }
    mt_srand();
    return $order;
}

/* how long one picture stays up, in seconds */
function concept_slot_seconds(): int
{
    $per = max(1, (int)CONCEPT_PER_DAY);
    return intdiv(86400, $per);
}

/* Which slot we are in now — how many slots have passed since the epoch.
   intdiv() floors towards zero, so negative (pre-epoch) times are handled
   separately; otherwise the slot before the epoch would collide with slot 0. */
function concept_slot_number(?string $when = null): int
{
    $tz    = new DateTimeZone(CONCEPT_TZ);
    $now   = new DateTimeImmutable($when ?? 'now', $tz);
    $epoch = new DateTimeImmutable(CONCEPT_EPOCH . ' 00:00:00', $tz);
    $diff  = $now->getTimestamp() - $epoch->getTimestamp();
    $len   = concept_slot_seconds();
    return (int)floor($diff / $len);
}

/* kept so anything that still asks for a day number keeps working */
function concept_day_number(?string $date = null): int
{
    $tz    = new DateTimeZone(CONCEPT_TZ);
    $today = new DateTimeImmutable($date ?? 'today', $tz);
    $epoch = new DateTimeImmutable(CONCEPT_EPOCH, $tz);
    $day   = (int)$epoch->diff($today)->days;
    return $today < $epoch ? -$day : $day;
}

/* the picture for any given slot — this is the whole rotation rule */
function concept_for_slot(int $slot): ?string
{
    $order = concept_order();
    $n = count($order);
    if ($n === 0) return null;
    return $order[(($slot % $n) + $n) % $n];
}

/* old name, same rule — used by anything still counting in days */
function concept_for_day(int $day): ?string
{
    return concept_for_slot($day);
}

/* --------------------------------------------------- today's picture, once */
function concept_today(): ?array
{
    static $pick = null;
    if ($pick !== null) return $pick ?: null;

    if (count(concept_pool()) === 0) { $pick = false; return null; }

    /* Walk the order one step per slot. A picture only comes back after every
       other picture has had its turn. */
    $slot = concept_slot_number();
    $name = concept_for_slot($slot);

    /* ?car=car2 shows one particular picture — for showing every design in a
       demo without waiting five days. It changes nothing else. */
    $want = isset($_GET['car']) ? basename((string)$_GET['car']) : '';
    if ($want !== '' && in_array($want, concept_pool(), true)) $name = $want;

    $dir = __DIR__ . '/' . CONCEPT_DIR;
    $v   = (int)@filemtime("$dir/$name.jpg");           // cache-busting stamp

    /* read the real pixel widths, so replacing the pictures with other sizes
       never leaves a wrong srcset behind */
    $big = @getimagesize("$dir/$name.jpg");
    $sml = @getimagesize("$dir/$name@sm.jpg");

    $pick = [
        'name'   => $name,
        'slot'   => $slot,
        'day'    => intdiv($slot, max(1, (int)CONCEPT_PER_DAY)),
        'jpg'    => CONCEPT_DIR . "/$name.jpg?v=$v",
        'jpg_sm' => is_file("$dir/$name@sm.jpg") ? CONCEPT_DIR . "/$name@sm.jpg?v=$v" : null,
        'webp'   => is_file("$dir/$name.webp")     ? CONCEPT_DIR . "/$name.webp?v=$v"     : null,
        'webp_sm'=> is_file("$dir/$name@sm.webp")  ? CONCEPT_DIR . "/$name@sm.webp?v=$v"  : null,
        'w'      => $big ? (int)$big[0] : 1500,
        'h'      => $big ? (int)$big[1] : 897,
        'w_sm'   => $sml ? (int)$sml[0] : 860,
    ];
    return $pick;
}

/* ------------------------------------------------------------- is it on?   */
function concept_active(): bool
{
    if (concept_today() === null) return false;
    if (isset($_GET['nosplash'])) return false;          // ?nosplash=1 skips it
    if (isset($_GET['id']) && $_GET['id'] !== '') return false;  // e-mail link
    return true;
}

/* ---------------------------------------- hook 1: the class on <body>      */
function concept_body_class(): string
{
    return concept_active() ? ' class="concept-on"' : '';
}

/* ------------------------------------------------------------- the styling
   The stylesheet lives inside this file rather than in assets/, so the splash
   can never end up half-dressed because one file did not reach the server or
   a browser was holding an old copy. It is printed once, whichever hook runs
   first. About 6 KB, and it saves a round trip on the picture. */
function concept_css(): string
{
    static $done = false;
    if ($done) return '';
    $done = true;
    return "<style>\n" . <<<'CSS'
/* ============================================================================
   concept.css — the concept-car splash.

   It is a normal flex child of .app, dropped in between <header class="top">
   and <main>. That is what keeps the maroon header on top and the navigation
   bar at the bottom: the splash simply takes the space that is left over.
   ============================================================================ */

/* while the splash is up, the pages underneath are out of the way */
body.concept-on main,
body.concept-on footer.legal { display: none; }

.concept {
  position: relative;
  flex: 1 1 auto;
  min-height: 0;                 /* lets the flex child actually shrink */
  overflow: hidden;
  background: #07060a;
  display: flex;
  flex-direction: column;
  justify-content: flex-end;
  animation: cfade .35s ease both;
}
@keyframes cfade { from { opacity: 0 } to { opacity: 1 } }

/* the picture lifting away */
body.concept-off .concept {
  animation: clift .38s cubic-bezier(.4, 0, .2, 1) both;
}
@keyframes clift {
  from { opacity: 1; transform: none; }
  to   { opacity: 0; transform: translateY(-4%) scale(1.04); }
}
@media (prefers-reduced-motion: reduce) {
  .concept, body.concept-off .concept { animation-duration: .01s; }
}

/* ==========================================================================
   THE LIGHTNING  —  برق

   Qatar's two flag colours: a white bolt with a maroon glow, over a maroon
   wash of the whole picture.

   Two rules kept this safe rather than seizure-inducing:
     1. the big wash flashes ONCE per strike. Large-area flashing faster than
        three times a second is a real accessibility hazard (WCAG 2.3.1), so
        it does not.
     2. the bolt itself — a thin shape covering a fraction of the screen —
        flickers three times. Small-area flashes are exempt, and the flicker
        is what makes it read as lightning instead of a fade.

   Anyone whose phone is set to "reduce motion" gets no flash at all.
   ========================================================================== */
.cstorm {
  position: absolute; inset: 0;
  pointer-events: none;
  z-index: 3;
  opacity: 0;
}
.cstorm .cwash {
  position: absolute; inset: 0;
  background:
    radial-gradient(120% 80% at 50% 0%, rgba(255,255,255,.92) 0%, rgba(255,255,255,.35) 38%, rgba(255,255,255,0) 68%),
    linear-gradient(180deg, rgba(138,21,56,.55) 0%, rgba(138,21,56,0) 60%);
  opacity: 0;
  mix-blend-mode: screen;
}
.cstorm svg {
  position: absolute;
  top: -2%; inset-inline-start: 8%;
  width: 46%; height: 74%;
  max-width: none;
  opacity: 0;
  overflow: visible;
}
.cstorm .blt {
  fill: #ffffff;
  filter: drop-shadow(0 0 10px rgba(255,255,255,.95))
          drop-shadow(0 0 34px rgba(200,40,90,.85))
          drop-shadow(0 0 70px rgba(138,21,56,.6));
}
.cstorm .blt.b2 { opacity: .55; transform: translateX(34%) scale(.62); transform-origin: top left; }

/* one strike */
.concept.strike .cstorm       { opacity: 1; }
.concept.strike .cstorm .cwash{ animation: cwash 620ms ease-out both; }
.concept.strike .cstorm svg   { animation: cbolt 560ms steps(1, end) both; }

@keyframes cwash {          /* ONE rise and fall — never a strobe */
  0%   { opacity: 0 }
  12%  { opacity: .55 }
  100% { opacity: 0 }
}
@keyframes cbolt {          /* the thin bolt may flicker; it is a small area */
  0%, 6%    { opacity: 1 }
  7%, 13%   { opacity: 0 }
  14%, 22%  { opacity: .9 }
  23%, 30%  { opacity: 0 }
  31%, 46%  { opacity: 1 }
  47%, 100% { opacity: 0 }
}

@media (prefers-reduced-motion: reduce) {
  .cstorm { display: none !important; }
}

/* ------------------------------------------------------------- the picture */
.cstage { position: absolute; inset: 0; overflow: hidden; }

/* blurred copy of the same picture fills every corner, so the showroom runs
   right to the edges and there are never black bars */
.cstage .cblur {
  position: absolute; inset: -10%;
  width: 120%; height: 120%; max-width: none;
  object-fit: cover;
  filter: blur(38px) saturate(118%) brightness(.66);
}
html[data-theme="dark"] .cstage .cblur { filter: blur(38px) saturate(112%) brightness(.5); }

/* The sharp picture. Its own box is exactly the shape of the photo (--car-ar
   comes from the real file), which is what lets the mask fade the top and
   bottom edges into the blurred copy underneath — the two layers then read as
   one continuous room instead of a photo pasted onto a background. */
.cfit {
  position: absolute; left: 50%; top: 40%;
  transform: translate(-50%, -50%);
  width: 104%;
  aspect-ratio: var(--car-ar, 1.673) / 1;
  -webkit-mask-image: linear-gradient(to bottom, transparent 0, #000 17%, #000 84%, transparent 100%);
          mask-image: linear-gradient(to bottom, transparent 0, #000 17%, #000 84%, transparent 100%);
}
/* the sides fade too, for the wide screens where the picture does not reach
   the edges. Nesting the two masks is what makes them combine. */
.cfit picture {
  display: block; width: 100%; height: 100%;
  -webkit-mask-image: linear-gradient(to right, transparent 0, #000 7%, #000 93%, transparent 100%);
          mask-image: linear-gradient(to right, transparent 0, #000 7%, #000 93%, transparent 100%);
}
.cfit .ccar { display: block; width: 100%; height: 100%; max-width: none; object-fit: cover; }

/* never let it grow taller than the space it lives in */
@supports (width: 1cqh) {
  .cstage { container-type: size; }
  .cfit   { width: min(104%, calc(86cqh * var(--car-ar, 1.673))); }
}

/* a soft vignette ties the whole stage together */
.cstage::after {
  content: ""; position: absolute; inset: 0; pointer-events: none;
  background: radial-gradient(120% 78% at 50% 42%, transparent 40%, rgba(4, 3, 6, .55) 100%);
}

/* On a phone the middle of the page is tall and narrow while the picture is
   wide. Push it a little past the edges so the car is bigger; only the empty
   floor and glass at the sides get cropped, never the car itself. */
@media (max-aspect-ratio: 4/5) {
  .cfit { width: 112%; top: 38%; }
  @supports (width: 1cqh) { .cfit { width: min(112%, calc(86cqh * var(--car-ar, 1.673))); } }
}
@media (max-aspect-ratio: 3/5) {
  .cfit { width: 122%; top: 36%; }
  @supports (width: 1cqh) { .cfit { width: min(122%, calc(80cqh * var(--car-ar, 1.673))); } }
}

/* --------------------------------------------------------------- the panel */
.cpanel {
  position: relative;
  padding: 64px 20px calc(18px + env(safe-area-inset-bottom, 0px));
  text-align: center;
  color: #fff;
  background: linear-gradient(to top,
              rgba(6, 4, 8, .94) 0%,
              rgba(6, 4, 8, .86) 42%,
              rgba(6, 4, 8, .55) 74%,
              rgba(6, 4, 8, 0) 100%);
}
.cpanel > * { max-width: 460px; margin-inline: auto; }

.cbadge {
  display: inline-block;
  background: var(--gold, #c9a227); color: #3a2408;
  font-size: 12px; font-weight: 800;
  padding: 4px 12px; border-radius: 999px;
}
.ctitle {
  margin: 10px auto 6px;
  font-size: clamp(21px, 6.2vw, 28px);
  font-weight: 800; line-height: 1.3;
  text-shadow: 0 2px 14px rgba(0, 0, 0, .6);
}
.csub {
  margin: 0 auto 16px;
  font-size: clamp(13px, 3.6vw, 15px);
  line-height: 1.6; color: rgba(255, 255, 255, .84);
}

.cbtn {
  display: block; width: 100%;
  border: 0; border-radius: 14px;
  padding: 16px 18px;
  font-family: inherit; font-size: 17px; font-weight: 800;
  cursor: pointer; color: #3a2408;
  background: linear-gradient(180deg, #e2c04a 0%, var(--gold, #c9a227) 100%);
  box-shadow: 0 10px 26px rgba(0, 0, 0, .45), 0 0 0 1px rgba(255, 255, 255, .18) inset;
  transition: transform .08s ease;
}
.cbtn:active { transform: scale(.985); }

.clink {
  display: block; width: 100%;
  margin-top: 10px; padding: 10px 8px;
  background: none; border: 0;
  font-family: inherit; font-size: 13.5px; font-weight: 600;
  color: rgba(255, 255, 255, .78);
  text-decoration: underline; text-underline-offset: 3px;
  cursor: pointer;
}
.clink:active { color: #fff; }

.cmark {
  margin: 10px auto 0;
  font-size: 10.5px; font-weight: 600; letter-spacing: .6px;
  text-transform: uppercase;
  color: rgba(255, 255, 255, .42);
}

/* the brand in the header brings the picture back */
header.top .brand { cursor: pointer; transition: transform .08s ease; }
header.top .brand:active { transform: scale(.985); }
header.top .brand:focus-visible { outline: 2px solid var(--gold, #c9a227); outline-offset: 4px; border-radius: 12px; }
body.concept-on header.top .brand { cursor: default; }
body.concept-on header.top .brand:active { transform: none; }

/* one language at a time — the site's own button sets <html lang>.
   ^= not =, so a regional code such as ar-QA or en-GB still matches:
   an exact match once broke this and printed both languages at once. */
html[lang^="ar"] .concept .l-en,
html[lang^="en"] .concept .l-ar { display: none; }

/* short screens: give the picture more room */
@media (max-height: 620px) {
  .cpanel { padding-top: 44px; }
  .csub   { display: none; }
  .cmark  { display: none; }
}

/* wide screens: keep the text column readable */
@media (min-width: 720px) {
  .cpanel { padding: 90px 24px 30px; }
}
CSS . "</style>\n";
}

/* ---------------------------------------- hook 2: <head> css + preload     */
function concept_head(): string
{
    if (!concept_active()) return '';
    $c = concept_today();
    $h = concept_css();

    /* Preload the picture that is about to fill the screen. Tagged
       type="image/webp" so a browser that cannot read WebP ignores the hint
       and simply loads the JPEG from the <picture> below — never both. */
    if ($c['webp'] && $c['webp_sm']) {
        $h .= '<link rel="preload" as="image" type="image/webp"'
            . ' href="' . e($c['webp']) . '"'
            . ' imagesrcset="' . e($c['webp_sm']) . " {$c['w_sm']}w, " . e($c['webp']) . " {$c['w']}w\""
            . ' imagesizes="100vw" fetchpriority="high">' . "\n";
    } elseif ($c['jpg_sm']) {
        $h .= '<link rel="preload" as="image" href="' . e($c['jpg']) . '"'
            . ' imagesrcset="' . e($c['jpg_sm']) . " {$c['w_sm']}w, " . e($c['jpg']) . " {$c['w']}w\""
            . ' imagesizes="100vw" fetchpriority="high">' . "\n";
    } else {
        $h .= '<link rel="preload" as="image" href="' . e($c['jpg']) . '" fetchpriority="high">' . "\n";
    }

    /* Without JavaScript there is nothing to press, so never hide the form. */
    $h .= '<noscript><style>body.concept-on .concept{display:none}'
        . 'body.concept-on main,body.concept-on footer.legal{display:block}</style></noscript>';
    return $h;
}

/* ---------------------------------------- hook 3: the splash itself        */
function concept_splash(): string
{
    if (!concept_active()) return '';
    $c = concept_today();

    $src   = e($c['jpg']);
    $set   = $c['jpg_sm']
        ? 'srcset="' . e($c['jpg_sm']) . " {$c['w_sm']}w, " . e($c['jpg']) . " {$c['w']}w\""
        : '';
    $webp  = ($c['webp'] && $c['webp_sm'])
        ? '<source type="image/webp" sizes="100vw" srcset="' . e($c['webp_sm']) . " {$c['w_sm']}w, " . e($c['webp']) . " {$c['w']}w\">"
        : '';
    $blur  = e($c['webp_sm'] ?: ($c['jpg_sm'] ?: $c['jpg']));
    $ar    = number_format($c['w'] / max(1, $c['h']), 4, '.', '');   // shape of the picture
    $css   = concept_css();   // nothing if <head> already printed it

    /* the wording comes from the admin control panel */
    $badgeAr = e(ct('freeBadge',  'ar')); $badgeEn = e(ct('freeBadge',  'en'));
    $ttlAr   = e(ct('heroTitle',  'ar')); $ttlEn   = e(ct('heroTitle',  'en'));
    $subAr   = e(ct('heroSub',    'ar')); $subEn   = e(ct('heroSub',    'en'));
    $btnAr   = e(ct('splashBtn',  'ar')); $btnEn   = e(ct('splashBtn',  'en'));
    $lnkAr   = e(ct('splashLink', 'ar')); $lnkEn   = e(ct('splashLink', 'en'));

    /* the lightning is switched on and off from the control panel */
    $storm = (function_exists('cv') && cv('splashLightning') === '0') ? '0' : '1';

    /* “I already have a request number” duplicates the 🔎 button in the bottom
       bar, so it can be switched off from 🏠 Home page in the control panel. */
    $showLink = !(function_exists('cv') && cv('showSplashLink') === '0');
    $linkHtml = $showLink
        ? '<button type="button" class="clink" id="conceptId">'
          . '<span class="l-ar">' . $lnkAr . '</span><span class="l-en">' . $lnkEn . '</span>'
          . '</button>'
        : '';

    return $css . <<<HTML
<div class="concept" id="conceptSplash" data-storm="{$storm}">
  <div class="cstage">
    <img class="cblur" src="{$blur}" alt="" aria-hidden="true" decoding="async">
    <div class="cfit" style="--car-ar:{$ar}">
      <picture>{$webp}
        <img class="ccar" src="{$src}" sizes="100vw" {$set}
             alt="" aria-hidden="true" fetchpriority="high" decoding="async">
      </picture>
    </div>
  </div>

  <div class="cstorm" aria-hidden="true">
    <div class="cwash"></div>
    <svg viewBox="0 0 120 300" preserveAspectRatio="xMidYMin meet" aria-hidden="true" focusable="false">
      <path class="blt" d="M74 0 L26 132 L58 132 L34 214 L96 96 L62 96 L94 0 Z"/>
      <path class="blt b2" d="M74 0 L26 132 L58 132 L34 214 L96 96 L62 96 L94 0 Z"/>
    </svg>
  </div>

  <div class="cpanel">
    <span class="cbadge"><span class="l-ar">{$badgeAr}</span><span class="l-en">{$badgeEn}</span></span>
    <h2 class="ctitle"><span class="l-ar">{$ttlAr}</span><span class="l-en">{$ttlEn}</span></h2>
    <p class="csub">
      <span class="l-ar">{$subAr}</span>
      <span class="l-en">{$subEn}</span>
    </p>
    <button type="button" class="cbtn" id="conceptGo">
      <span class="l-ar">{$btnAr}</span><span class="l-en">{$btnEn}</span>
    </button>
    {$linkHtml}
    <p class="cmark"><span class="l-ar">تصميم اليوم</span><span class="l-en">Concept of the day</span></p>
  </div>
</div>
<script>
(function () {
  var s = document.getElementById('conceptSplash');
  if (!s) return;
  var body = document.body, gone = false, busy = false;

  function nav(view) { return document.querySelector('nav.bottom button[data-view="' + view + '"]'); }

  /* the picture lifts away and the form is underneath */
  function lift(view) {
    if (gone || busy) return;
    busy = true; gone = true;
    body.classList.add('concept-off');
    if (typeof stopStorm === 'function') stopStorm();
    try { if (navigator.vibrate) navigator.vibrate(10); } catch (e) {}
    setTimeout(function () {
      body.classList.remove('concept-on', 'concept-off');
      s.setAttribute('hidden', '');      /* kept in the page, ready to come back */
      window.scrollTo(0, 0);
      busy = false;
      if (view) { var b = nav(view); if (b) b.click(); }
    }, 380);
  }

  /* the picture comes back — pressing the name in the corner */
  function drop() {
    if (!gone || busy) return;
    var home = nav('form');                       /* land back on the first page  */
    if (home && !home.classList.contains('on')) home.click();   /* lift() no-ops here */
    gone = false;
    s.removeAttribute('hidden');
    body.classList.remove('concept-off');
    body.classList.add('concept-on');
    s.style.animation = 'none'; void s.offsetWidth; s.style.animation = '';  /* replay */
    if (stormOn && !calm) { setTimeout(strike, 380); schedule(); }
    window.scrollTo(0, 0);
    try { if (navigator.vibrate) navigator.vibrate(10); } catch (e) {}
  }

  document.getElementById('conceptGo').addEventListener('click', function () { lift(); });
  /* the link can be switched off in the control panel, so it may not be here */
  var cId = document.getElementById('conceptId');
  if (cId) cId.addEventListener('click', function () { lift('status'); });

  /* ---------------------------------------------------------------- lightning
     One strike as the page opens, then another every 14-20 seconds for as long
     as the picture is still up. It stops the moment the splash is lifted away,
     and it never runs at all for a visitor who has asked their phone to reduce
     motion. PHP decides whether it is switched on at all (control panel).     */
  var stormOn = s.getAttribute('data-storm') === '1';
  var calm = false;
  try { calm = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches; } catch (e) {}

  var stormT = null;
  function strike() {
    if (gone || document.hidden) return;
    s.classList.remove('strike');
    void s.offsetWidth;                 /* restart the animation */
    s.classList.add('strike');
    setTimeout(function () { s.classList.remove('strike'); }, 700);
  }
  function schedule() {
    clearTimeout(stormT);
    stormT = setTimeout(function () { strike(); schedule(); }, 14000 + Math.random() * 6000);
  }
  function stopStorm() { clearTimeout(stormT); stormT = null; }

  if (stormOn && !calm) {
    setTimeout(strike, 420);            /* after the picture has faded in */
    schedule();
    /* a tab left open in the background should not keep flashing */
    document.addEventListener('visibilitychange', function () {
      if (document.hidden) stopStorm(); else if (!gone) schedule();
    });
  } else {
    stopStorm();
  }

  document.addEventListener('DOMContentLoaded', function () {
    /* pressing anything in the bottom bar also lifts the picture away */
    var b = document.querySelectorAll('nav.bottom button');
    for (var i = 0; i < b.length; i++) b[i].addEventListener('click', function () { lift(); });

    /* the name and badge in the header are the way home */
    var brand = document.querySelector('header.top .brand');
    if (!brand) return;
    brand.setAttribute('role', 'button');
    brand.setAttribute('tabindex', '0');
    brand.setAttribute('title', 'تصميم اليوم · Concept of the day');
    brand.setAttribute('aria-label', 'تصميم اليوم · Concept of the day');
    brand.addEventListener('click', function () { drop(); });
    brand.addEventListener('keydown', function (ev) {
      if (ev.key === 'Enter' || ev.key === ' ' || ev.key === 'Spacebar') {
        ev.preventDefault(); ev.stopPropagation(); drop();
      }
    });
  });

  document.addEventListener('keydown', function (ev) { if (ev.key === 'Escape') lift(); });
})();
</script>
HTML;
}
