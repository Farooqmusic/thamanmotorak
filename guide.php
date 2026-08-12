<?php
declare(strict_types=1);
/* ============================================================================
   guide.php — “How the service works”, in Arabic and English.

   A standalone page rather than another card inside ℹ️ Info: this is the thing
   to send someone who asks what the service is, so it needs its own address
   and its own share preview. It follows ?lang= like the rest of the site and
   carries its own styling, so it cannot arrive half-dressed.
   ============================================================================ */
require __DIR__ . '/lib.php';
require __DIR__ . '/seo.php';
ensure_dirs();

header('Cache-Control: no-cache, must-revalidate, max-age=0');

$L   = seo_lang();
$ar  = ($L !== 'en');
$dir = $ar ? 'rtl' : 'ltr';

/** picks the right half of a bilingual pair */
function g(string $arText, string $enText): string {
    return $GLOBALS['ar'] ? $arText : $enText;
}

$steps = [
    ['1', '🚗', 'اختر سيارتك',            'Choose your car',
          'الشركة والفئة وسنة الصنع من القوائم. لم تجد سيارتك؟ اختر «أخرى» واكتبها بنفسك.',
          'Make, class and year from the lists. Car not there? Pick “Other” and type it in.'],
    ['2', '🎨', 'صف حالتها',              'Describe its condition',
          'حدّد على الرسم أي جزء مصبوغ أو متضرر، ثم قيّم المقصورة والمحرك والقير.',
          'Mark any repainted or damaged panel on the diagram, then rate the interior, engine and gearbox.'],
    ['3', '📸', 'صوّر سيارتك',            'Photograph the car',
          'خمس صور مطلوبة: الأمام، الخلف، الجانبان، السقف. وثلاث اختيارية تساعدنا على دقة أعلى.',
          'Five photos are required: front, back, both sides and the roof. Three optional ones make the estimate sharper.'],
    ['4', '📩', 'استلم رقم طلبك',          'Get your request number',
          'رقم من ست خانات يظهر على الشاشة ويصلك على بريدك. تتابع به طلبك في أي وقت، بدون كلمة مرور.',
          'A six-character number appears on screen and arrives by email. You track your request with it — no password.'],
    ['5', '🟢', 'يصلك السعر',              'Your price arrives',
          'ضوء أحمر يعني أن الطلب تحت المراجعة، وأخضر يعني أن السعر جاهز — مع تقرير PDF كامل.',
          'A red light means it is under review; green means the price is ready — with a full PDF report.'],
];

$gets = [
    ['💰', 'سعر تقديري أو نطاق سعري', 'An estimated price, or a price range',
           'رقم واحد أو نطاق من–إلى، مبني على خبرة طويلة في سوق السيارات المستعملة في قطر.',
           'A single figure or a from–to range, based on long experience of the used-car market in Qatar.'],
    ['📄', 'تقرير PDF من ثلاث صفحات', 'A three-page PDF report',
           'بياناتك، صورك، وتقرير حالة يوضّح الأجزاء المصبوغة وحالة المقصورة والمحرك والقير.',
           'Your details, your photos, and a condition report showing the repainted panels and the state of the interior, engine and gearbox.'],
    ['🔒', 'خصوصية بمهلة تحذفها', 'Privacy with an expiry date',
           'صورك تُحذف من الخادم تلقائياً بعد ٣ أو ٧ أيام حسب اختيارك. لا نشاركها مع أي جهة.',
           'Your photos are deleted from the server automatically after 3 or 7 days, whichever you chose. We never share them.'],
    ['🆓', 'مجاناً بالكامل', 'Completely free',
           'بدون رسوم، وبدون زيارة معرض. كل شيء من جوالك.',
           'No fee, and no showroom visit. All of it from your phone.'],
];
?>
<!doctype html>
<html lang="<?= e($L) ?>" dir="<?= e($dir) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e(g('كيف تعمل الخدمة؟ — ' . ct('appName', 'ar'), 'How it works — ' . ct('appName', 'en'))) ?></title>
<meta name="description" content="<?= e(g(
    'دليل مختصر لخدمة ' . ct('appName', 'ar') . ': كيف تحصل على سعر تقديري لسيارتك مجاناً من جوالك.',
    'A short guide to ' . ct('appName', 'en') . ': how to get a free estimated price for your car from your phone.')) ?>">
<?= seo_head('guide.php') ?>
<meta name="theme-color" content="#8a1538">
<link rel="apple-touch-icon" href="<?= asset('icons/icon-180.png') ?>">
<link rel="icon" href="<?= asset('icons/icon-192.png') ?>">
<link rel="stylesheet" href="<?= asset('assets/app.css') ?>">
<script>
(function(){try{var t=localStorage.getItem('eyc_theme');
if(t!=='dark'&&t!=='light')t=(window.matchMedia&&matchMedia('(prefers-color-scheme: dark)').matches)?'dark':'light';
document.documentElement.setAttribute('data-theme',t);}catch(e){}})();
</script>
<style>
/* ------------------------------------------------------------------
   Styling lives here, not in app.css: this page is often the first
   thing someone is sent, and it must look right even if the shared
   stylesheet is an upload behind.
   ------------------------------------------------------------------ */
.gwrap{ width:100%; max-width:820px; margin:0 auto; padding:18px 16px 40px; }

.ghero{
  position:relative; overflow:hidden;
  border-radius:22px; padding:34px 24px 30px; text-align:center;
  background:linear-gradient(160deg,#8a1538,#5e0d26 70%);
  color:#fff; margin-bottom:20px;
}
.ghero::after{
  content:""; position:absolute; inset:-40% -20% auto -20%; height:150%;
  background:radial-gradient(60% 55% at 50% 0%, rgba(255,232,170,.22), transparent 70%);
  pointer-events:none;
}
.ghero img{ width:min(220px,52vw); height:auto; display:block; margin:0 auto 14px; position:relative; z-index:1; }
.ghero h1{ margin:0 0 8px; font-size:26px; line-height:1.4; position:relative; z-index:1; }
.ghero p{ margin:0 auto; max-width:34em; font-size:15px; line-height:1.9; opacity:.9; position:relative; z-index:1; }
.gfree{
  display:inline-block; margin-bottom:14px; position:relative; z-index:1;
  background:var(--gold); color:#3a2408; font-weight:800; font-size:12.5px;
  padding:6px 14px; border-radius:99px;
}

.gsec{ margin:26px 0 12px; font-size:19px; font-weight:800; color:var(--ink); }
.gsec small{ display:block; font-size:13px; font-weight:500; color:var(--muted); margin-top:3px; }

.gstep{
  display:flex; gap:14px; align-items:flex-start;
  background:var(--card); border:1px solid var(--line); border-radius:16px;
  padding:16px 17px; margin-bottom:10px; box-shadow:var(--shadow);
}
.gnum{
  flex:0 0 auto; width:38px; height:38px; border-radius:12px;
  background:var(--tint); color:var(--brand);
  display:grid; place-items:center; font-size:19px;
}
.gstep b{ display:block; font-size:15.5px; color:var(--ink); margin-bottom:3px; }
.gstep p{ margin:0; font-size:13.5px; color:var(--muted); line-height:1.85; }
.gstep .gi{ font-size:11px; font-weight:800; color:var(--brand); letter-spacing:.5px; }

.ggrid{ display:grid; grid-template-columns:repeat(auto-fit,minmax(240px,1fr)); gap:10px; }

.gapps{
  border-radius:18px; padding:22px 20px; text-align:center; margin-top:26px;
  background:var(--tint); border:1px dashed color-mix(in srgb, var(--brand) 34%, transparent);
}
.gapps b{ display:block; font-size:16.5px; color:var(--ink); margin-bottom:5px; }
.gapps p{ margin:0 0 14px; font-size:13.5px; color:var(--muted); line-height:1.8; }
.gbadges{ display:flex; gap:10px; justify-content:center; flex-wrap:wrap; }
.gbadge{
  display:flex; align-items:center; gap:9px;
  background:var(--ink); color:#fff; border-radius:12px; padding:9px 16px;
  font-size:13px; opacity:.55; cursor:default;
}
.gbadge .b1{ font-size:19px; line-height:1; }
.gbadge span span{ display:block; font-size:10px; opacity:.75; }
.gsoon{
  display:inline-block; margin-top:12px; font-size:11.5px; font-weight:800;
  background:var(--gold); color:#3a2408; padding:5px 12px; border-radius:99px;
}

.gcta{ display:block; margin:26px auto 0; max-width:420px; }
.gback{
  display:inline-flex; align-items:center; gap:7px; margin-bottom:14px;
  color:var(--brand); font-weight:700; font-size:14px; text-decoration:none;
}
</style>
</head>
<body>
<div class="app">

  <header class="top">
    <div class="brand">
      <picture>
        <source srcset="<?= asset('assets/brand/logo-mark.webp') ?>" type="image/webp">
        <img class="brandmark" src="<?= asset('assets/brand/logo-mark.png') ?>" width="1044" height="484" alt="" decoding="async">
      </picture>
      <div class="txt">
        <h1><?= e(ct('appName', $L)) ?></h1>
        <p><?= e(ct('tagline', $L)) ?></p>
      </div>
    </div>
    <a class="langbtn" href="guide.php<?= $ar ? '?lang=en' : '' ?>" style="text-decoration:none"><?= $ar ? 'EN' : 'ع' ?></a>
  </header>

  <main>
    <div class="gwrap">

      <a class="gback" href="index.php<?= $ar ? '' : '?lang=en' ?>">← <?= e(g('رجوع إلى الموقع', 'Back to the site')) ?></a>

      <section class="ghero">
        <picture>
          <source srcset="<?= asset('assets/brand/logo-mark.webp') ?>" type="image/webp">
          <img src="<?= asset('assets/brand/logo-mark.png') ?>" width="1044" height="484" alt="<?= e(ct('appName', $L)) ?>">
        </picture>
        <span class="gfree"><?= e(ct('freeBadge', $L)) ?></span>
        <h1><?= e(g('كم يساوي موترك؟ اعرف قبل أن تبيع.', 'What is your car worth? Know before you sell.')) ?></h1>
        <p><?= e(g(
          'خدمة قطرية تعطيك سعراً تقديرياً لسيارتك المستعملة من صور ترسلها من جوالك — بدون زيارة معرض، وبدون رسوم.',
          'A Qatari service that gives you an estimated price for your used car from photos you send from your phone — no showroom visit, no fee.')) ?></p>
      </section>

      <h2 class="gsec"><?= e(g('كيف تعمل الخدمة؟', 'How it works')) ?>
        <small><?= e(g('خمس خطوات، كلها من جوالك.', 'Five steps, all from your phone.')) ?></small></h2>

      <?php foreach ($steps as [$n, $icon, $tAr, $tEn, $dAr, $dEn]): ?>
      <div class="gstep">
        <div class="gnum"><?= $icon ?></div>
        <div>
          <span class="gi"><?= e(g('الخطوة ', 'STEP ')) . $n ?></span>
          <b><?= e(g($tAr, $tEn)) ?></b>
          <p><?= e(g($dAr, $dEn)) ?></p>
        </div>
      </div>
      <?php endforeach; ?>

      <h2 class="gsec"><?= e(g('ماذا تحصل عليه؟', 'What you get')) ?></h2>
      <div class="ggrid">
        <?php foreach ($gets as [$icon, $tAr, $tEn, $dAr, $dEn]): ?>
        <div class="gstep">
          <div class="gnum"><?= $icon ?></div>
          <div>
            <b><?= e(g($tAr, $tEn)) ?></b>
            <p><?= e(g($dAr, $dEn)) ?></p>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <h2 class="gsec"><?= e(g('أسئلة سريعة', 'Quick questions')) ?></h2>
      <div class="gstep"><div class="gnum">❓</div><div>
        <b><?= e(g('هل السعر نهائي؟', 'Is the price final?')) ?></b>
        <p><?= e(g(
          'لا. هو سعر تقديري مبني على الصور والبيانات التي أرسلتها، وقد يختلف بعد معاينة السيارة على الطبيعة.',
          'No. It is an estimate based on the photos and details you sent, and it may change after the car is seen in person.')) ?></p>
      </div></div>
      <div class="gstep"><div class="gnum">🔑</div><div>
        <b><?= e(g('نسيت رقم الطلب، ماذا أفعل؟', 'I lost my request number — what now?')) ?></b>
        <p><?= e(g(
          'الرقم موجود في البريد الذي أرسلناه لك عند الاستلام. إن لم تجده، راسلنا من صفحة «الدعم» وسنساعدك.',
          'It is in the email we sent when we received your request. If you cannot find it, write to us from the Support page and we will help.')) ?></p>
      </div></div>
      <div class="gstep"><div class="gnum">💬</div><div>
        <b><?= e(g('عندي مشكلة أو اقتراح', 'I have a problem or a suggestion')) ?></b>
        <p><?= e(g(
          'اكتب لنا من زر «الدعم» في أسفل الموقع. كل رسالة تحصل على رقم متابعة، وتقدر تتابع الرد به.',
          'Write to us from the Support button at the bottom of the site. Every message gets a reference number you can follow the reply with.')) ?></p>
      </div></div>

      <section class="gapps">
        <b><?= e(g('التطبيقات قادمة قريباً', 'The apps are coming soon')) ?></b>
        <p><?= e(g(
          'نعمل على تطبيقي أندرويد وآيفون. حتى ذلك الحين يعمل الموقع على جوالك تماماً كالتطبيق — افتحه من المتصفح واختر «إضافة إلى الشاشة الرئيسية».',
          'Android and iPhone apps are on the way. Until then the site works on your phone just like an app — open it in the browser and choose “Add to Home screen”.')) ?></p>
        <div class="gbadges">
          <div class="gbadge"><span class="b1">🤖</span><span><span><?= e(g('قريباً على', 'Soon on')) ?></span>Google Play</span></div>
          <div class="gbadge"><span class="b1">🍎</span><span><span><?= e(g('قريباً على', 'Soon on')) ?></span>App Store</span></div>
        </div>
        <span class="gsoon"><?= e(g('قيد التطوير', 'In development')) ?></span>
      </section>

      <a class="btn gold gcta" style="text-decoration:none;text-align:center"
         href="index.php<?= $ar ? '' : '?lang=en' ?>"><?= e(ct('splashBtn', $L)) ?></a>
    </div>
  </main>

  <footer class="legal">
    <span><?= e(ct('footer', $L)) ?></span>
    <span class="dot">·</span><a href="terms.php"><?= e(ct('termsTitle', $L)) ?></a>
    <span class="dot">·</span><a href="privacy.php"><?= e(ct('privacyTitle', $L)) ?></a>
  </footer>
</div>
<script src="<?= asset('assets/theme.js') ?>"></script>
</body>
</html>
