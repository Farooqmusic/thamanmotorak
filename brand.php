<?php
declare(strict_types=1);

/* ============================================================================
   brand.php — the opening logo screen.

   Four seconds of the TMK mark, a gold light crossing the whole screen, then
   it clears itself away and the site is underneath. It runs on every page
   load — which on this site means once per visit, because the whole thing is
   one page and nothing inside it reloads. BRAND_EVERY_MS can space it out.

   The stylesheet is printed inline, exactly like concept.php, and for a
   sharper reason: this thing covers the entire screen. If it ever loaded
   without its CSS — a stale assets/app.css, a file that did not reach the
   server — the visitor would be staring at a giant image with no way past it.
   Inline, that cannot happen.
   ============================================================================ */

if (!function_exists('e')) {
    function e(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

const BRAND_DIR   = 'assets/brand';
const BRAND_MS    = 4000;      // how long the logo holds, before the fade
const BRAND_FADE  = 700;       // the fade itself
const BRAND_FELT  = '#191a1c'; // matches the dark felt the logo was drawn on
/* How long before the logo is shown again.
   0 = every time the page is loaded, which is what this site wants: it is a
   single page, and everything inside it moves without a reload — so “every
   load” already means once per visit in practice, and it removes the puzzle
   of a splash that refuses to come back while you are testing.
   Set it to 1800000 for once every 30 minutes, or 86400000 for once a day. */
const BRAND_EVERY_MS = 0;

/** Anyone arriving with a purpose in the address bar goes straight there. */
function brand_active(): bool
{
    /* ?splash=1 shows it every single time — the once-per-visit rule makes
       testing maddening otherwise, because the second look never shows it. */
    if (isset($_GET['splash']))                                return true;
    if (isset($_GET['nosplash']))                              return false;
    if (isset($_GET['id'])      && $_GET['id'] !== '')         return false;   // status link from an email
    if (isset($_GET['support']) && $_GET['support'] !== '')    return false;   // support receipt link
    return is_file(__DIR__ . '/' . BRAND_DIR . '/logo-splash.webp')
        || is_file(__DIR__ . '/' . BRAND_DIR . '/logo-splash.png');
}

/** goes in <head>: the preload, the styling, and the “already seen” check */
function brand_head(): string
{
    if (!brand_active()) return '';

    $hold  = BRAND_MS;
    $fade  = BRAND_FADE;
    $felt  = BRAND_FELT;
    $total = $hold + $fade;

    $out  = '<link rel="preload" as="image" href="' . asset(BRAND_DIR . '/logo-splash.webp') . '" type="image/webp">' . "\n";

    /* Runs before the first paint. Without it, a returning visitor would see
       the splash flash up for an instant before JavaScript could remove it. */
    $force = isset($_GET['splash']) ? 'true' : 'false';
    /* This used to sit in sessionStorage, which is per TAB — so the same tab
       never replayed it and a fresh browser always did. That is why it looked
       broken in Chrome and fine in Edge. A timestamp is honest about what
       “again” means; with BRAND_EVERY_MS at 0 the check is skipped entirely. */
    $every = (int)BRAND_EVERY_MS;
    $out .= '<script>(function(){var F=' . $force . ',E=' . $every . ';try{'
          . 'if(!F&&E>0){var t=+(localStorage.getItem("tmk_brand_at")||0);'
          . 'if(Date.now()-t < E) document.documentElement.className+=" brand-seen";}'
          . '}catch(e){}})();</script>' . "\n";

    $out .= "<style>\n" . <<<CSS
/* ============================================================================
   the opening logo screen
   ============================================================================ */
html.brand-seen #brandSplash { display: none !important; }

#brandSplash{
  position: fixed; inset: 0; z-index: 9999;
  background: {$felt};
  display: grid; place-items: center;
  overflow: hidden;
  cursor: pointer;                       /* a tap gets past it immediately */
  animation: brandOut {$fade}ms ease {$hold}ms forwards;
}
@keyframes brandOut { to { opacity: 0; visibility: hidden; } }

/* a soft lift in the middle, so the flat colour does not read as a dead screen */
#brandSplash::before{
  content:""; position:absolute; inset:0;
  background: radial-gradient(60% 45% at 50% 46%, rgba(255,255,255,.05), rgba(255,255,255,0) 70%);
}

#brandSplash .bmark{
  position: relative; z-index: 2;
  width: min(78vw, 46vh, 560px);
  opacity: 0; transform: scale(.94);
  animation: brandIn .85s cubic-bezier(.2,.8,.2,1) forwards;
  filter: drop-shadow(0 10px 34px rgba(0,0,0,.55));
}
@keyframes brandIn { to { opacity: 1; transform: none; } }

/* THE GOLD SWEEP — the whole screen, not a box around the logo.
   A wide diagonal band of light travels right across the display and over the
   mark on its way. 'screen' blending means it lifts whatever it passes over
   instead of painting a grey rectangle on top of it. */
#brandSplash .bsweep{
  position: absolute; top: -30%; bottom: -30%;
  width: 56%; left: -70%;
  z-index: 3; pointer-events: none;
  /* The gradient is wide and soft on its own. It used to lean on filter:blur()
     over a full-screen fixed layer, which some Chrome builds refuse to
     composite — and when that fails the whole overlay can come out blank.
     No filter now, and the light reads the same. */
  background: linear-gradient(100deg,
      rgba(255,232,170,0)    0%,
      rgba(255,232,170,.05) 22%,
      rgba(255,238,190,.20) 40%,
      rgba(255,246,222,.46) 50%,
      rgba(255,238,190,.20) 60%,
      rgba(255,232,170,.05) 78%,
      rgba(255,232,170,0)  100%);
  will-change: transform;
  transform: translate3d(0,0,0) skewX(-14deg);
  animation: brandSweep 1.55s cubic-bezier(.55,.06,.35,.96) .75s 2;
}
/* 'screen' only where the browser says it can do it, so a failure to composite
   costs a little brightness instead of the entire screen */
@supports (mix-blend-mode: screen){
  #brandSplash .bsweep{ mix-blend-mode: screen; }
}
@keyframes brandSweep{
  from { transform: translate3d(0,0,0)    skewX(-14deg); }
  to   { transform: translate3d(340%,0,0) skewX(-14deg); }
}

/* the four seconds, drawn */
#brandSplash .bring{ position:absolute; bottom: calc(30px + env(safe-area-inset-bottom,0px));
                     left:50%; transform:translateX(-50%); width:34px; height:34px; z-index:4; }
#brandSplash .bring circle{ fill:none; stroke-width:2.5; }
#brandSplash .bring .t{ stroke: rgba(201,162,39,.20); }
#brandSplash .bring .p{
  stroke:#c9a227; stroke-linecap:round; stroke-dasharray:88; stroke-dashoffset:88;
  transform: rotate(-90deg); transform-origin:50% 50%;
  animation: brandRing {$hold}ms linear forwards;
}
@keyframes brandRing { to { stroke-dashoffset: 0; } }

/* Someone who asked their phone for less movement gets the logo, held still,
   and a shorter wait — no sweep, no scaling. */
@media (prefers-reduced-motion: reduce){
  #brandSplash{ animation-delay: 1200ms; }
  #brandSplash .bmark{ animation: none; opacity: 1; transform: none; }
  #brandSplash .bsweep{ display: none; }
  #brandSplash .bring{ display: none; }
}
CSS
    . "\n</style>\n";

    /* Belt and braces: if the CSS animation never runs — an old browser, or a
       tab restored from the background — the overlay is still taken away. */
    $out .= '<script>(function(){var t=' . ($total + 900) . ';'
          . 'function go(){var s=document.getElementById("brandSplash");if(s&&s.parentNode)s.parentNode.removeChild(s);}'
          . 'try{localStorage.setItem("tmk_brand_at", String(Date.now()));}catch(e){}'
          . 'window.addEventListener("load",function(){setTimeout(go,t);});'
          . 'document.addEventListener("click",function(ev){'
          . 'var s=document.getElementById("brandSplash");'
          . 'if(s&&(ev.target===s||s.contains(ev.target))){s.style.transition="opacity .3s";s.style.opacity=0;setTimeout(go,320);}'
          . '},true);})();</script>' . "\n";

    return $out;
}

/** the overlay itself — the first thing inside <body> */
function brand_splash(): string
{
    if (!brand_active()) return '';
    $alt = e(ct('appName', 'ar') . ' — ' . ct('appName', 'en'));

    return '<div id="brandSplash" role="img" aria-label="' . $alt . '">'
         . '<div class="bsweep"></div>'
         . '<picture>'
         . '<source srcset="' . asset(BRAND_DIR . '/logo-splash.webp') . '" type="image/webp">'
         . '<img class="bmark" src="' . asset(BRAND_DIR . '/logo-splash.png') . '" alt="' . $alt . '" '
         . 'width="900" height="698" fetchpriority="high" decoding="async" '
         /* a missing picture must not become four seconds of blank black */
         . 'onerror="var s=document.getElementById(&quot;brandSplash&quot;);'
         . 'if(s&amp;&amp;s.parentNode)s.parentNode.removeChild(s);">'
         . '</picture>'
         . '<svg class="bring" viewBox="0 0 32 32" aria-hidden="true">'
         . '<circle class="t" cx="16" cy="16" r="14"/><circle class="p" cx="16" cy="16" r="14"/>'
         . '</svg>'
         . '</div>';
}
