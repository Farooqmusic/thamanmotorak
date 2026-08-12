<?php
/* ============================================================
   legalpage.php — the shared shell behind terms.php and privacy.php.

   Both pages are bilingual on one screen: the visitor's language
   button swaps between them instantly, no reload, exactly like the
   main app. Every word comes from the admin control panel.
   ============================================================ */
declare(strict_types=1);

function render_legal_page(string $titleKey, string $bodyKey): void
{
    require_once __DIR__ . '/seo.php';
    $C = cfg();
    $L = seo_lang();
    $me = basename((string)($_SERVER['SCRIPT_NAME'] ?? 'terms.php'));

    $titleAr = ct($titleKey, 'ar');
    $titleEn = ct($titleKey, 'en');
    $bodyAr  = content_render_body(ct($bodyKey, 'ar'));
    $bodyEn  = content_render_body(ct($bodyKey, 'en'));

    $nameAr  = ct('legalName', 'ar');
    $nameEn  = ct('legalName', 'en');
    $addrAr  = ct('legalAddress', 'ar');
    $addrEn  = ct('legalAddress', 'en');
    $cr      = cv('legalCR');
    $updated = cv('legalUpdated');
    $mail    = cv('contactEmail');
    ?>
<!doctype html>
<html lang="<?= e($L) ?>" dir="<?= e(seo_dir($L)) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($L === 'ar' ? $titleAr : $titleEn) ?> — <?= e(ct('appName', $L)) ?></title>
<meta name="description" content="<?= e(($L === 'ar' ? $titleAr : $titleEn) . ' — ' . ct('appName', $L)) ?>">
<?= seo_head($me, ($L === 'ar' ? $titleAr : $titleEn) . ' — ' . ct('appName', $L),
             ($L === 'ar' ? $titleAr : $titleEn) . ' — ' . ct('appName', $L)) ?>
<meta name="theme-color" content="#8a1538" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#131013" media="(prefers-color-scheme: dark)">
<meta id="tc" name="theme-color" content="#8a1538">
<link rel="icon" href="icons/icon-192.png">
<link rel="stylesheet" href="<?= asset('assets/app.css') ?>">
<script>
(function(){try{var t=localStorage.getItem('eyc_theme');
if(t!=='dark'&&t!=='light')t=(window.matchMedia&&matchMedia('(prefers-color-scheme: dark)').matches)?'dark':'light';
document.documentElement.setAttribute('data-theme',t);}catch(e){}})();
</script>
<style>
  .langpane[hidden]{display:none}
  .backlink{display:inline-block;margin-bottom:14px;color:var(--muted);text-decoration:none;font-size:14px}
  .backlink:hover{color:var(--brand)}
</style>
</head>
<body>
<div class="app">

  <header class="top">
    <a class="brand" href="index.php" style="text-decoration:none;color:inherit">
      <div class="mark">ث</div>
      <div class="txt">
        <h1><span class="l-ar"><?= e(ct('appName', 'ar')) ?></span><span class="l-en" hidden><?= e(ct('appName', 'en')) ?></span></h1>
        <p><span class="l-ar"><?= e($titleAr) ?></span><span class="l-en" hidden><?= e($titleEn) ?></span></p>
      </div>
    </a>
    <button class="iconbtn" id="themeBtn" type="button" aria-label="Light / dark">🌙</button>
    <button class="langbtn" id="langBtn" type="button">English</button>
  </header>

  <main>
    <div class="card">
      <a class="backlink" href="index.php">
        <span class="l-ar">→ رجوع إلى الصفحة الرئيسية</span>
        <span class="l-en" hidden>← Back to the home page</span>
      </a>

      <!-- Arabic -->
      <div class="langpane legaldoc" id="paneAr" dir="rtl">
        <h2 style="margin:0 0 6px"><?= e($titleAr) ?></h2>
        <p class="updated">
          <?= e($nameAr) ?><?= $addrAr !== '' ? ' — ' . e($addrAr) : '' ?>
          <?= $cr !== '' ? ' — س.ت. ' . e($cr) : '' ?>
          <?= $updated !== '' ? ' — آخر تحديث: ' . e($updated) : '' ?>
        </p>
        <hr style="border:0;border-top:1px solid var(--line);margin:14px 0 18px">
        <?= $bodyAr ?>
        <?php if ($mail !== ''): ?>
          <p style="margin-top:18px">📧 <a href="mailto:<?= e($mail) ?>" style="color:var(--brand);font-weight:700" dir="ltr"><?= e($mail) ?></a></p>
        <?php endif; ?>
      </div>

      <!-- English -->
      <div class="langpane legaldoc" id="paneEn" dir="ltr" hidden>
        <h2 style="margin:0 0 6px"><?= e($titleEn) ?></h2>
        <p class="updated">
          <?= e($nameEn) ?><?= $addrEn !== '' ? ' — ' . e($addrEn) : '' ?>
          <?= $cr !== '' ? ' — CR ' . e($cr) : '' ?>
          <?= $updated !== '' ? ' — last updated: ' . e($updated) : '' ?>
        </p>
        <hr style="border:0;border-top:1px solid var(--line);margin:14px 0 18px">
        <?= $bodyEn ?>
        <?php if ($mail !== ''): ?>
          <p style="margin-top:18px">📧 <a href="mailto:<?= e($mail) ?>" style="color:var(--brand);font-weight:700"><?= e($mail) ?></a></p>
        <?php endif; ?>
      </div>
    </div>
  </main>

  <footer class="legal">
    <span class="l-ar"><?= e(ct('footer', 'ar')) ?></span><span class="l-en" hidden><?= e(ct('footer', 'en')) ?></span>
    <span class="dot">·</span>
    <a href="terms.php"><span class="l-ar"><?= e(ct('termsTitle', 'ar')) ?></span><span class="l-en" hidden><?= e(ct('termsTitle', 'en')) ?></span></a>
    <span class="dot">·</span>
    <a href="privacy.php"><span class="l-ar"><?= e(ct('privacyTitle', 'ar')) ?></span><span class="l-en" hidden><?= e(ct('privacyTitle', 'en')) ?></span></a>
  </footer>
</div>

<script>
(function () {
  var d = document.documentElement;
  function store(k, v) { try { if (v === undefined) return localStorage.getItem(k); localStorage.setItem(k, v); } catch (e) {} return null; }

  function apply(lang) {
    d.lang = lang; d.dir = (lang === 'ar') ? 'rtl' : 'ltr';
    document.getElementById('paneAr').hidden = (lang !== 'ar');
    document.getElementById('paneEn').hidden = (lang !== 'en');
    Array.prototype.forEach.call(document.querySelectorAll('.l-ar'), function (el) { el.hidden = (lang !== 'ar'); });
    Array.prototype.forEach.call(document.querySelectorAll('.l-en'), function (el) { el.hidden = (lang !== 'en'); });
    document.getElementById('langBtn').textContent = (lang === 'ar') ? 'English' : 'العربية';
    /* keep ?lang= in the address so the page can be shared and indexed per language */
    try {
      var u = new URL(window.location.href);
      if (lang === 'en') u.searchParams.set('lang', 'en'); else u.searchParams.delete('lang');
      if (u.href !== window.location.href) history.replaceState(null, '', u.href);
    } catch (e) {}
  }

  /* the address wins — that is what a search result and a shared link carry */
  var lang = <?= json_encode($L) ?>;
  if (!/[?&]lang=/.test(window.location.search) && store('eyc_lang') === 'en') lang = 'en';
  apply(lang);

  document.getElementById('langBtn').addEventListener('click', function () {
    lang = (lang === 'ar') ? 'en' : 'ar';
    store('eyc_lang', lang);
    apply(lang);
  });

  /* light / dark, same switch as the main app */
  var COL = { light: '#8a1538', dark: '#131013' };
  function applyTheme(th) {
    d.setAttribute('data-theme', th);
    var m = document.getElementById('tc'); if (m) m.setAttribute('content', COL[th] || COL.light);
    var b = document.getElementById('themeBtn'); if (b) b.textContent = (th === 'dark') ? '☀️' : '🌙';
  }
  var saved = store('eyc_theme');
  applyTheme((saved === 'dark' || saved === 'light') ? saved
    : ((window.matchMedia && matchMedia('(prefers-color-scheme: dark)').matches) ? 'dark' : 'light'));
  document.getElementById('themeBtn').addEventListener('click', function () {
    var next = (d.getAttribute('data-theme') === 'dark') ? 'light' : 'dark';
    store('eyc_theme', next); applyTheme(next);
  });
})();
</script>
</body>
</html>
    <?php
}
