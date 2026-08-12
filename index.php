<?php
declare(strict_types=1);
require __DIR__ . '/lib.php';
require __DIR__ . '/seo.php';         // canonical, hreflang, Open Graph, structured data
require __DIR__ . '/concept.php';     // concept-car splash — see README-CONCEPT.md
require __DIR__ . '/brand.php';       // the opening logo screen, once per visit
ensure_dirs();

/* Never let a phone keep this page. Everything that changes on the site
   arrives through index.php, and a cached copy is why an update seems not to
   have happened until the browser cache is cleared by hand. The files it
   pulls in are versioned (?v=…), so they can still be cached for a long time.
   Sent from PHP as well as .htaccess, because mod_headers is not on every host. */
header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
header('Pragma: no-cache');
$C = cfg();

/* ar by default, ?lang=en gives Google a second, separately indexable page */
$L = seo_lang();
?>
<!doctype html>
<html lang="<?= e($L) ?>" dir="<?= e(seo_dir($L)) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e(seo_title($L)) ?></title>
<meta name="description" content="<?= e(seo_description($L)) ?>">
<?= seo_head('index.php') ?>
<meta name="theme-color" content="#8a1538" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#131013" media="(prefers-color-scheme: dark)">
<meta id="tc" name="theme-color" content="#8a1538">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="<?= e(ct('appName', $L)) ?>">
<link rel="manifest" href="manifest.php">
<link rel="apple-touch-icon" href="<?= asset('icons/icon-180.png') ?>">
<link rel="icon" href="<?= asset('icons/icon-192.png') ?>">
<link rel="icon" sizes="32x32" href="<?= asset('icons/favicon-32.png') ?>">
<style>
/* Safety net, deliberately placed BEFORE app.css so app.css always wins when
   it is there. This page is never stored by a browser, so these few rules are
   guaranteed to arrive — which means the guide card can never appear as bare
   text on a device that is still holding an older stylesheet. Real colours,
   not variables, because the variables live in app.css. */
.guidecard{
  display:flex; align-items:center; gap:13px;
  background:linear-gradient(150deg,#8a1538,#a81c46);
  color:#fff; text-decoration:none;
  border-radius:18px; padding:16px 17px; margin-bottom:14px;
  box-shadow:0 6px 18px rgba(0,0,0,.10);
}
.guidecard .gic{ flex:0 0 auto; font-size:24px; line-height:1 }
.guidecard b{ display:block; font-size:15.5px }
.guidecard small{ display:block; font-size:12.5px; opacity:.82; line-height:1.7; margin-top:2px }
.guidecard .garr{ flex:0 0 auto; margin-inline-start:auto; opacity:.8; font-size:17px }
/* same idea for the waiting screen on submit — it must never be invisible */
.sending{
  position:fixed; inset:0; z-index:9998; background:rgba(12,10,12,.72);
  display:grid; place-items:center; padding:24px;
}
.sending[hidden]{ display:none }
.sending .sbox{
  background:#fff; border-radius:20px; padding:28px 26px 24px;
  width:min(340px,100%); text-align:center; position:relative;
  box-shadow:0 20px 60px rgba(0,0,0,.4);
}
.sending .sring{ width:74px; height:74px; display:block; margin:0 auto 6px }
</style>
<link rel="stylesheet" href="<?= asset('assets/app.css') ?>">
<?= concept_head() ?>
<?= brand_head() ?>
<script>
/* set the theme before the first paint so dark mode never flashes white */
(function(){try{var t=localStorage.getItem('eyc_theme');
if(t!=='dark'&&t!=='light')t=(window.matchMedia&&matchMedia('(prefers-color-scheme: dark)').matches)?'dark':'light';
document.documentElement.setAttribute('data-theme',t);}catch(e){}})();
</script>
</head>
<body<?= concept_body_class() ?>>
<?= brand_splash() ?>
<div class="app">

  <header class="top">
    <div class="brand">
      <picture>
        <source srcset="<?= asset('assets/brand/logo-mark.webp') ?>" type="image/webp">
        <img class="brandmark" src="<?= asset('assets/brand/logo-mark.png') ?>" width="1044" height="484"
             alt="<?= e(ct('appName', $L)) ?>" decoding="async">
      </picture>
      <div class="txt">
        <h1 data-i18n="appName"><?= e(ct('appName', $L)) ?></h1>
        <p data-i18n="tagline"><?= e(ct('tagline', $L)) ?></p>
      </div>
    </div>
    <button class="iconbtn" id="themeBtn" type="button" aria-label="Light / dark">🌙</button>
    <button class="langbtn" id="langBtn" type="button"
            aria-label="<?= $L === 'ar' ? 'Switch to English' : 'التبديل إلى العربية' ?>"><?= $L === 'ar' ? 'EN' : 'ع' ?></button>
  </header>

<?= concept_splash() ?>

  <main>

    <!-- ================= FORM ================= -->
    <section class="view on" id="view-form">

      <div class="card hero">
        <span class="badge" data-i18n="freeBadge"><?= e(ct('freeBadge', $L)) ?></span>
        <h2 data-i18n="heroTitle"><?= e(ct('heroTitle', $L)) ?></h2>
        <p class="sub" data-i18n="heroSub"><?= e(ct('heroSub', $L)) ?></p>
      </div>

      <div class="steps" id="steps"><i class="now"></i><i></i><i></i><i></i></div>

      <form id="f" novalidate>
        <input type="hidden" name="lang" id="langField" value="<?= e($L) ?>">

        <!-- step 1 -->
        <div class="card step" data-step="1">
          <h2 data-i18n="s1Title">بيانات السيارة</h2>
          <p class="sub" data-i18n="s1Sub">الخطوة 1 من 4</p>

          <div class="field">
            <label data-i18n="fMake">الشركة المصنعة</label>
            <select id="makeSel"></select>
            <input id="makeOther" hidden autocomplete="off" style="margin-top:8px" data-i18n-ph="phMakeOther" placeholder="اكتب اسم الشركة">
          </div>
          <div class="field">
            <label data-i18n="fClass">الفئة</label>
            <select id="classSel"></select>
            <input id="classOther" hidden autocomplete="off" style="margin-top:8px" data-i18n-ph="phClassOther" placeholder="اكتب اسم الفئة">
          </div>
          <div class="row2">
            <div class="field">
              <label data-i18n="fModel">الموديل / الفئة الفرعية</label>
              <input id="car_model" autocomplete="off" dir="ltr" placeholder="LT Premium">
            </div>
            <div class="field">
              <label data-i18n="fYear">سنة الصنع</label>
              <select id="yearSel"></select>
              <!-- filled by app.js with e.g. “2021 – 2027” when the chosen
                   model was only sold in some years. Numbers only, no words. -->
              <p class="hint yearhint" id="yearHint" dir="ltr" hidden></p>
            </div>
          </div>
          <div class="field">
            <label data-i18n="fKm">الممشى</label>
            <input name="mileage" id="mileage" inputmode="numeric" dir="ltr" placeholder="120000">
          </div>
          <div class="field">
            <label data-i18n="fReg">رقم الاستمارة</label>
            <input name="registration" autocomplete="off" dir="ltr" placeholder="—">
          </div>
          <div class="field">
            <label data-i18n="fVin">رقم الشاصي / الهيكل</label>
            <input name="chassis" autocomplete="off" dir="ltr" placeholder="—">
          </div>
          <div class="err" id="e1"></div>
          <button type="button" class="btn" data-go="2" data-i18n="next">التالي</button>
        </div>

        <!-- ============ step 2 · condition ============ -->
        <div class="card step" data-step="2" hidden>
          <h2 data-i18n="s2Title">حالة السيارة</h2>
          <p class="sub" data-i18n="s2Sub">الخطوة 2 من 4</p>

          <!-- ---- the panel diagram ---- -->
          <label class="clabel" data-i18n="cmTitle">حدّد الأجزاء المصبوغة أو المتضررة</label>
          <p class="hint" style="margin:0 0 12px" data-i18n="cmHint">اضغط على أي جزء مرة = صبغ، مرتين = حادث أو إصلاح، ثلاث مرات = إلغاء.</p>

          <div class="carmap" id="carMap"><?= car_map_svg([], true, $L) ?></div>

          <div class="cmlegend">
            <?php foreach (cm_states() as $sk => $sv): ?>
            <span class="cmkey"><i class="k-<?= e($sk) ?>"></i><span data-i18n="cm_<?= e($sk) ?>"><?= e($L === 'en' ? $sv['en'] : $sv['ar']) ?></span></span>
            <?php endforeach; ?>
            <button type="button" class="cmclear" id="cmClear" data-i18n="cmClear">مسح التحديد</button>
          </div>
          <div class="cmpicked" id="cmPicked"></div>
          <input type="hidden" name="panels" id="panelsField" value="">

          <!-- ---- paint status ---- -->
          <label class="clabel" style="margin-top:22px" data-i18n="cpTitle">حالة الصبغ</label>
          <div class="optlist" id="paintList">
            <?php foreach (cond_paint_options() as $k => $o): ?>
            <div class="optitem">
              <label class="optrow">
                <input type="radio" name="paint_status" value="<?= e($k) ?>">
                <span>
                  <b data-i18n="cp_<?= e($k) ?>"><?= e($L === 'en' ? $o['en'] : $o['ar']) ?></b>
                  <small data-i18n="cph_<?= e($k) ?>"><?= e($L === 'en' ? $o['hint_en'] : $o['hint_ar']) ?></small>
                </span>
              </label>
            </div>
            <?php endforeach; ?>
          </div>
          <p class="lockmsg" id="paintLock" hidden data-i18n="cpLockRepaint">حدّدت جزءاً كـ«حادث / إصلاح» على الرسم، لذلك لا يمكن اختيار «صبغ فقط».</p>

          <div id="extentWrap" hidden>
            <label class="clabel" style="margin-top:18px" data-i18n="ceTitle">هل الصبغ جزئي أم كامل؟</label>
            <div class="chips" data-group="paint_extent" data-field="extentField">
              <?php foreach (cond_extent_options() as $k => $o): ?>
              <button type="button" class="chip" data-value="<?= e($k) ?>" data-i18n="ce_<?= e($k) ?>"><?= e($L === 'en' ? $o['en'] : $o['ar']) ?></button>
              <?php endforeach; ?>
            </div>
            <input type="hidden" name="paint_extent" id="extentField" value="">
          </div>

          <!-- The customer's own words sit here now, right after the paint
               questions: this is the moment he is thinking about the car's
               condition, not while he is typing a chassis number. -->
          <div class="field" style="margin-top:20px">
            <label data-i18n="fNotes">ملاحظات</label>
            <textarea name="notes" rows="3" data-i18n-ph="phNotes" placeholder="حوادث، صبغ، إضافات..."></textarea>
          </div>

          <!-- ---- interior / engine / gearbox ---- -->
          <?php foreach (cond_scales() as $key => $def): ?>
          <div class="qblock">
            <div class="qhead">
              <span class="qicon"><?= cond_icon($def['icon']) ?></span>
              <span>
                <b data-i18n="q_<?= e($key) ?>"><?= e($L === 'en' ? $def['en'] : $def['ar']) ?></b>
                <small data-i18n="qs_<?= e($key) ?>"><?= e($L === 'en' ? $def['sub_en'] : $def['sub_ar']) ?></small>
              </span>
            </div>
            <div class="chips" data-group="q_<?= e($key) ?>" data-field="qField_<?= e($key) ?>">
              <?php foreach ($def['opts'] as $v => $o): $v = (string)$v; ?>
              <button type="button" class="chip" data-value="<?= e($v) ?>" data-i18n="qo_<?= e($key) ?>_<?= e($v) ?>"><?= e($L === 'en' ? $o['en'] : $o['ar']) ?></button>
              <?php endforeach; ?>
            </div>
            <input type="hidden" name="q_<?= e($key) ?>" id="qField_<?= e($key) ?>" value="">
          </div>
          <?php endforeach; ?>

          <div class="err" id="e2"></div>
          <div class="btnrow" style="margin-top:16px">
            <button type="button" class="btn ghost" data-go="1" data-i18n="back">رجوع</button>
            <button type="button" class="btn" data-go="3" data-i18n="next">التالي</button>
          </div>
        </div>

        <!-- ============ step 3 · photos ============ -->
        <div class="card step" data-step="3" hidden>
          <h2 data-i18n="s3Title">صور السيارة</h2>
          <p class="sub" data-i18n="s3Sub">الخطوة 3 من 4</p>

          <p class="sub" style="margin-top:-8px" data-i18n="slotHint">كل صندوق يوضّح الزاوية المطلوبة. اضغط «كاميرا» للتصوير مباشرة أو «من الجهاز» لاختيار صورة محفوظة.</p>

          <div class="slotgrid" id="slotGrid"></div>
          <div class="counter" id="cPhotos">0 / 5</div>

          <div style="height:20px"></div>

          <div class="picker" id="pickVideos">
            <div class="ic">🎬</div>
            <b data-i18n="addVideos">أضف فيديو (اختياري)</b>
            <span data-i18n="videoRule">حتى مقطعين — دوران حول السيارة وصوت المحرك</span>
          </div>
          <input type="file" id="inVideos" accept="video/*" capture="environment" multiple hidden>
          <div class="thumbs" id="thVideos"></div>

          <div class="err" id="e3"></div>
          <div class="btnrow" style="margin-top:16px">
            <button type="button" class="btn ghost" data-go="2" data-i18n="back">رجوع</button>
            <button type="button" class="btn" data-go="4" data-i18n="next">التالي</button>
          </div>
        </div>

        <!-- ============ step 4 · your details ============ -->
        <div class="card step" data-step="4" hidden>
          <h2 data-i18n="s4Title">بياناتك</h2>
          <p class="sub" data-i18n="s4Sub">الخطوة 4 من 4 — سنرسل رقم طلبك والنتيجة على بريدك</p>

          <div class="field">
            <label data-i18n="fName">الاسم</label>
            <input name="name" id="cname" autocomplete="name">
          </div>
          <div class="field">
            <label data-i18n="fPhone">رقم الجوال</label>
            <input name="phone" id="cphone" type="tel" inputmode="tel" autocomplete="tel" dir="ltr" placeholder="+974 ...">
          </div>
          <div class="field">
            <label data-i18n="fEmail">البريد الإلكتروني</label>
            <input name="email" id="cemail" type="email" inputmode="email" autocomplete="email" dir="ltr" placeholder="name@example.com">
          </div>

          <div class="field">
            <label data-i18n="fKeep">كم يوماً نحتفظ بصورك؟</label>
            <div class="opt">
              <label><input type="radio" name="retention" value="3" checked><span data-i18n="d3">3 أيام</span></label>
              <label><input type="radio" name="retention" value="7"><span data-i18n="d7">7 أيام</span></label>
            </div>
            <p class="hint" style="margin:8px 0 0" data-i18n="keepNote">تُحذف الصور والفيديو تلقائياً بعد هذه المدة.</p>
          </div>

          <div class="err" id="e4"></div>
          <div class="btnrow" style="margin-top:16px">
            <button type="button" class="btn ghost" data-go="3" data-i18n="back">رجوع</button>
            <button type="submit" class="btn gold" id="sendBtn" data-i18n="send">إرسال الطلب</button>
          </div>
        </div>
      </form>
    </section>

    <!-- ================= SENT ================= -->
    <section class="view" id="view-sent">
      <div class="card">
        <div class="light">
          <div class="lamp green">✅</div>
          <h3 data-i18n="sentTitle">تم استلام طلبك</h3>
          <p data-i18n="sentSub">احتفظ برقم الطلب — تدخل به بدون كلمة مرور.</p>
        </div>
        <div class="idbox">
          <small data-i18n="yourId">رقم طلبك</small>
          <b id="sentId" class="ltr">------</b>
        </div>
        <p class="sub" style="text-align:center" data-i18n="sentMail">أرسلنا الرقم أيضاً إلى بريدك الإلكتروني.</p>

        <!-- Our domain is young, so a first message can land in the spam folder.
             Saying so here — while the number is still on screen — means nobody
             is left waiting for an email they will never look for. -->
        <div class="junknote">
          <b data-i18n="junkT">لم يصلك البريد؟</b>
          <p data-i18n="junkB">تحقق من مجلد «الرسائل غير المرغوب فيها» (Junk / Spam). إن وجدته هناك، اضغط «ليس بريداً مزعجاً» حتى تصلك رسائلنا القادمة في البريد الوارد.</p>
          <button type="button" class="junklink" data-goview="support" data-i18n="junkS">ما زلت لا تجده؟ راسلنا من صفحة الدعم ←</button>
        </div>

        <button type="button" class="btn" id="goStatus" data-i18n="checkNow">تابع حالة الطلب</button>
        <button type="button" class="btn ghost" style="margin-top:10px" id="againBtn" data-i18n="another">تقييم سيارة أخرى</button>
      </div>
    </section>

    <!-- ================= STATUS ================= -->
    <section class="view" id="view-status">
      <div class="card">
        <h2 data-i18n="stTitle">حالة طلبك</h2>
        <p class="sub" data-i18n="stSub">أدخل رقم الطلب فقط — لا حاجة لكلمة مرور.</p>
        <div class="field">
          <label data-i18n="fId">رقم الطلب</label>
          <input id="idInput" maxlength="6" autocomplete="off" spellcheck="false" dir="ltr"
                 style="text-transform:uppercase;letter-spacing:6px;font-size:22px;font-weight:800;text-align:center">
        </div>
        <div class="err" id="eStatus"></div>
        <button type="button" class="btn" id="checkBtn" data-i18n="check">استعلام</button>
      </div>

      <div class="card" id="resultCard" hidden>
        <div id="resultBody"></div>
      </div>
    </section>

    <!-- ================= INFO ================= -->
    <section class="view" id="view-info">

      <!-- overview — every word of this card is written from the admin panel -->
      <div class="card">
        <h2 data-i18n="overviewTitle"><?= e(ct('overviewTitle', $L)) ?></h2>
        <p class="sub" style="margin:0;white-space:pre-line" data-i18n="overviewBody"><?= e(ct('overviewBody', $L)) ?></p>
      </div>

      <a class="guidecard" href="guide.php<?= $L === 'en' ? '?lang=en' : '' ?>">
        <span class="gic">📘</span>
        <span>
          <b data-i18n="guideTitle"><?= e($L === 'en' ? 'How the service works' : 'كيف تعمل الخدمة؟') ?></b>
          <small data-i18n="guideSub"><?= e($L === 'en'
              ? 'A short guide — the five steps, what you get, and the apps that are coming.'
              : 'دليل مختصر — الخطوات الخمس، وماذا تحصل عليه، والتطبيقات القادمة.') ?></small>
        </span>
        <span class="garr">↗</span>
      </a>

      <div class="card">
        <h2 data-i18n="infoTitle"><?= e(ct('infoTitle', $L)) ?></h2>
        <table class="kv">
          <tr><td data-i18n="step1k">1</td><td data-i18n="step1v"><?= e(ct('step1v', $L)) ?></td></tr>
          <tr><td data-i18n="step2k">2</td><td data-i18n="step2v"><?= e(ct('step2v', $L)) ?></td></tr>
          <tr><td data-i18n="step3k">3</td><td data-i18n="step3v"><?= e(ct('step3v', $L)) ?></td></tr>
          <tr><td data-i18n="step4k">4</td><td data-i18n="step4v"><?= e(ct('step4v', $L)) ?></td></tr>
        </table>
      </div>

      <div class="card">
        <h2 data-i18n="privTitle"><?= e(ct('privTitle', $L)) ?></h2>
        <p class="sub" style="margin:0 0 12px" data-i18n="privBody"><?= e(ct('privBody', $L)) ?></p>
        <p style="margin:0">
          <a class="linkrow" href="privacy.php"><span data-i18n="privacyTitle"><?= e(ct('privacyTitle', $L)) ?></span> ↗</a>
          <a class="linkrow" href="terms.php"><span data-i18n="termsTitle"><?= e(ct('termsTitle', $L)) ?></span> ↗</a>
        </p>
      </div>

      <div class="card">
        <h2 data-i18n="cTitle"><?= e(ct('cTitle', $L)) ?></h2>
        <table class="kv contact">
          <?php if (whatsapp_digits() !== ''): ?>
          <tr><td>WhatsApp</td><td><a class="ltr" href="https://wa.me/<?= e(whatsapp_digits()) ?>" style="color:var(--brand);font-weight:700"><?= e(cv('contactPhone')) ?></a></td></tr>
          <?php endif; ?>
          <?php if (cv('contactEmail') !== ''): ?>
          <tr><td data-i18n="cEmail">البريد</td><td><a class="ltr" href="mailto:<?= e(cv('contactEmail')) ?>" style="color:var(--brand);font-weight:700"><?= e(cv('contactEmail')) ?></a></td></tr>
          <?php endif; ?>
          <?php if (cv('instagramUrl') !== ''): ?>
          <tr><td>Instagram</td><td><a class="ltr" href="<?= e(cv('instagramUrl')) ?>" target="_blank" rel="noopener noreferrer" style="color:var(--brand);font-weight:700"><?= e(cv('instagramName') ?: cv('instagramUrl')) ?></a></td></tr>
          <?php endif; ?>
          <?php if (cv('websiteUrl') !== ''): ?>
          <tr><td data-i18n="cWeb">الموقع</td><td><a class="ltr" href="<?= e(cv('websiteUrl')) ?>" target="_blank" rel="noopener noreferrer" style="color:var(--brand);font-weight:700"><?= e(cv('websiteName') ?: cv('websiteUrl')) ?></a></td></tr>
          <?php endif; ?>
          <?php foreach (['extraLink1', 'extraLink2'] as $x): if (cv($x . 'Url') === '') continue; ?>
          <tr><td data-i18n="<?= e($x) ?>Label"><?= e(ct($x . 'Label', $L) ?: cv($x . 'Url')) ?></td>
              <td><a class="ltr" href="<?= e(cv($x . 'Url')) ?>" target="_blank" rel="noopener noreferrer" style="color:var(--brand);font-weight:700"><?= e(cv($x . 'Url')) ?></a></td></tr>
          <?php endforeach; ?>
        </table>
      </div>

      <?php if (cv('devCreditUrl') !== '' && ct('devCredit', $L) !== ''): ?>
      <p class="devcredit">
        <a href="<?= e(cv('devCreditUrl')) ?>" target="_blank" rel="noopener noreferrer"
           data-i18n="devCredit"><?= e(ct('devCredit', $L)) ?></a>
      </p>
      <?php endif; ?>
    </section>

    <!-- ================= SUPPORT ================= -->
    <section class="view" id="view-support">
      <div class="card" id="supCard">
        <h2 data-i18n="supportTitle"><?= e(ct('supportTitle', $L)) ?></h2>
        <p class="sub" style="white-space:pre-line" data-i18n="supportIntro"><?= e(ct('supportIntro', $L)) ?></p>

        <form id="supForm" novalidate>
          <div class="field">
            <label data-i18n="supKind">نوع الرسالة</label>
            <div class="chips" data-group="sup_kind" data-field="supKind" data-required>
              <?php foreach (support_kinds() as $k => $v): ?>
              <button type="button" class="chip" data-value="<?= e($k) ?>" data-i18n="sk_<?= e($k) ?>"><?= e($L === 'en' ? $v['en'] : $v['ar']) ?></button>
              <?php endforeach; ?>
            </div>
            <input type="hidden" name="sup_kind" id="supKind" value="problem">
          </div>

          <div class="field">
            <label data-i18n="supMsg">رسالتك</label>
            <textarea name="s_msg" id="sMsg" rows="5" data-i18n-ph="supMsgPh"
                      placeholder="اكتب المشكلة بالتفصيل — مثلاً: لم أجد شركة سيارتي في القائمة."></textarea>
          </div>

          <p class="hint" style="margin:-4px 0 14px" data-i18n="supReach">اترك بريدك أو رقم جوالك حتى نتمكن من الرد عليك — واحد منهما يكفي.</p>

          <div class="row2">
            <div class="field">
              <label data-i18n="fName">الاسم</label>
              <input name="s_name" id="sName" autocomplete="name">
            </div>
            <div class="field">
              <label data-i18n="supRef">رقم الطلب</label>
              <input name="s_ref" id="sRef" dir="ltr" maxlength="6" autocomplete="off" placeholder="——">
            </div>
          </div>
          <div class="field">
            <label data-i18n="fEmail">البريد الإلكتروني</label>
            <input name="s_email" id="sEmail" type="email" inputmode="email" autocomplete="email" dir="ltr" placeholder="name@example.com">
          </div>
          <div class="field">
            <label data-i18n="fPhone">رقم الجوال</label>
            <input name="s_phone" id="sPhone" type="tel" inputmode="tel" autocomplete="tel" dir="ltr" placeholder="+974 ...">
          </div>

          <!-- A bot fills every field it can find; nobody else ever sees this one.
               The name must stay meaningless: a field called "website" or "url"
               gets filled by browsers and password managers for real people. -->
          <input type="text" name="eyc_hp" id="sHoney" tabindex="-1" hidden
                 autocomplete="off" aria-hidden="true"
                 data-lpignore="true" data-1p-ignore data-form-type="other">

          <div class="err" id="eSup"></div>
          <button type="submit" class="btn gold" id="supBtn" data-i18n="supSend">إرسال الرسالة</button>
        </form>
      </div>

      <div class="card" id="supLookup">
        <h2 data-i18n="supFollowT">تابع رسالة سابقة</h2>
        <p class="sub" data-i18n="supFollowS">عندك رقم متابعة؟ أدخله لترى إن كنا قرأنا رسالتك وردّينا عليها.</p>
        <div class="field">
          <label data-i18n="supRefNo">رقم المتابعة</label>
          <input id="supIdInput" maxlength="7" autocomplete="off" spellcheck="false" dir="ltr"
                 style="text-transform:uppercase;letter-spacing:5px;font-size:20px;font-weight:800;text-align:center">
        </div>
        <div class="err" id="eSupFind"></div>
        <button type="button" class="btn" id="supFindBtn" data-i18n="check">استعلام</button>
        <div id="supFound" hidden style="margin-top:16px"></div>
      </div>

      <div class="card" id="supDone" hidden>
        <div class="light">
          <div class="lamp green">✅</div>
          <h3 data-i18n="supThanksT">تم إرسال رسالتك</h3>
          <p data-i18n="supportThanks"><?= e(ct('supportThanks', $L)) ?></p>
        </div>
        <div class="idbox">
          <small data-i18n="supRefNo">رقم المتابعة</small>
          <b id="supId" class="ltr">------</b>
        </div>
        <p class="sub" style="text-align:center" data-i18n="supKeep">احتفظ بهذا الرقم — تتابع به رسالتك في أي وقت، وأرسلناه أيضاً إلى بريدك.</p>

        <div class="junknote">
          <b data-i18n="junkT">لم يصلك البريد؟</b>
          <p data-i18n="junkB">تحقق من مجلد «الرسائل غير المرغوب فيها» (Junk / Spam). إن وجدته هناك، اضغط «ليس بريداً مزعجاً» حتى تصلك رسائلنا القادمة في البريد الوارد.</p>
        </div>
        <button type="button" class="btn ghost" id="supAgain" data-i18n="supAnother">إرسال رسالة أخرى</button>
      </div>
    </section>

  </main>

  <footer class="legal">
    <span data-i18n="footer"><?= e(ct('footer', $L)) ?></span>
    <span class="dot">·</span>
    <a href="terms.php"><span data-i18n="termsTitle"><?= e(ct('termsTitle', $L)) ?></span></a>
    <span class="dot">·</span>
    <a href="privacy.php"><span data-i18n="privacyTitle"><?= e(ct('privacyTitle', $L)) ?></span></a>
  </footer>

  <nav class="bottom">
    <button type="button" class="on" data-view="form"><span class="ic">🚗</span><span data-i18n="navEval">تقييم</span></button>
    <button type="button" data-view="status"><span class="ic">🔎</span><span data-i18n="navStatus">حالة الطلب</span></button>
    <button type="button" data-view="info"><span class="ic">ℹ️</span><span data-i18n="navInfo">معلومات</span></button>
    <button type="button" data-view="support"><span class="ic">💬</span><span data-i18n="navSupport">الدعم</span></button>
  </nav>
</div>

<!-- Shown while the photos are going up. A phone on a weak connection can take
     a minute, and the thin bar under the button was easy to miss — people were
     free to close the page half way through and lose everything. -->
<div class="sending" id="sending" hidden role="status" aria-live="polite">
  <div class="sbox">
    <svg class="sring" viewBox="0 0 44 44" aria-hidden="true">
      <circle class="t" cx="22" cy="22" r="19"/>
      <circle class="p" cx="22" cy="22" r="19"/>
    </svg>
    <b class="spct ltr" id="sPct">0%</b>
    <h3 data-i18n="sendingTitle" id="sTitle">جارٍ رفع الصور…</h3>
    <p data-i18n="sendingWarn">لا تغلق هذه الصفحة حتى ينتهي الرفع.</p>
  </div>
</div>

<div class="toast" id="toast"></div>

<script>
window.APP_CFG = {
  /* the language this page was rendered in (from ?lang=) — app.js starts here */
  lang: <?= json_encode($L) ?>,
  minPhotos: <?= (int)$C['min_photos'] ?>,
  maxPhotos: <?= (int)$C['max_photos'] ?>,
  maxVideos: <?= (int)$C['max_videos'] ?>,
  maxPhotoMB: <?= (int)$C['max_photo_mb'] ?>,
  maxVideoMB: <?= (int)$C['max_video_mb'] ?>,
  currency: { ar: <?= json_encode($C['currency_ar'], JSON_UNESCAPED_UNICODE) ?>, en: <?= json_encode($C['currency_en']) ?> },
  prefillId: <?= json_encode(strtoupper(substr((string)($_GET['id'] ?? ''), 0, 6))) ?>,
  /* ?support=SXXXXXX — the link in the support receipt email */
  prefillSupport: <?= json_encode(strtoupper(preg_replace('/[^A-Za-z0-9]/', '', substr((string)($_GET['support'] ?? ''), 0, 7)))) ?>,
  slots: <?= json_encode(slots(), JSON_UNESCAPED_UNICODE) ?>,
  /* the panel names and the two marking colours for the condition diagram */
  cond: <?= json_encode(cond_js_config(), JSON_UNESCAPED_UNICODE) ?>,
  supportKinds: <?= json_encode(support_kinds(), JSON_UNESCAPED_UNICODE) ?>,
  /* every word Khalid edited in the control panel — merged over the built-in
     translations in app.js, so switching language shows his text with no reload */
  i18n: <?= json_encode(content_i18n(), JSON_UNESCAPED_UNICODE) ?>
};
</script>
<script src="<?= asset('assets/cars.js') ?>"></script>
<script src="<?= asset('assets/app.js') ?>"></script>
<script>
/* ------------------------------------------------------------------
   Upload check — and self-repair.

   index.php and assets/ are separate files. If the browser ends up with an
   older app.css or app.js than the one sitting on the server, the page looks
   broken for reasons nobody can guess: a black diagram, panels that do not
   respond, a card that shows as plain text, a submit button with no waiting
   clock. It used to need somebody to clear the cache by hand on that one
   device.

   Now the page knows what the server really has (the two numbers below are
   read out of the files themselves) and compares them with what the browser
   actually loaded. If they do not match it repairs itself, once: it throws
   away the offline store, unregisters the old service worker and reloads. Only
   if that still does not help does it say something, and by then it is a
   genuine upload problem, not a cache.
   ------------------------------------------------------------------ */
(function () {
  var NEED_JS  = <?= js_build() ?>;
  var NEED_CSS = <?= css_build() ?>;

  window.addEventListener('load', function () {
    var stale = [];
    if (NEED_JS > 0 && window.__EYC_BUILD !== NEED_JS) stale.push('assets/app.js');
    var css = getComputedStyle(document.documentElement).getPropertyValue('--eyc-css-build');
    if (NEED_CSS > 0 && parseInt(css, 10) !== NEED_CSS) stale.push('assets/app.css');
    if (!stale.length) { try { sessionStorage.removeItem('eyc_fixed'); } catch (e) {} return; }

    /* first time we notice: clean everything this device is holding and reload */
    var tried = false;
    try { tried = sessionStorage.getItem('eyc_fixed') === '1'; } catch (e) {}
    if (!tried) {
      try { sessionStorage.setItem('eyc_fixed', '1'); } catch (e) {}
      var jobs = [];
      if (window.caches && caches.keys) {
        jobs.push(caches.keys().then(function (k) {
          return Promise.all(k.map(function (n) { return caches.delete(n); }));
        }).catch(function () {}));
      }
      if (navigator.serviceWorker && navigator.serviceWorker.getRegistrations) {
        jobs.push(navigator.serviceWorker.getRegistrations().then(function (rs) {
          return Promise.all(rs.map(function (r) { return r.unregister(); }));
        }).catch(function () {}));
      }
      Promise.all(jobs).then(function () {
        var u = location.href.split('#')[0];
        location.replace(u + (u.indexOf('?') > -1 ? '&' : '?') + 'fresh=' + Date.now());
      });
      return;
    }

    var b = document.createElement('div');
    b.setAttribute('dir', 'auto');
    b.style.cssText = 'position:fixed;inset-inline:0;bottom:0;z-index:9999;background:#8a1538;'
      + 'color:#fff;padding:14px 16px;font:600 13.5px/1.7 Segoe UI,Tahoma,Arial,sans-serif;'
      + 'text-align:center;box-shadow:0 -6px 20px rgba(0,0,0,.25)';
    b.innerHTML = '<div dir="rtl">لم يتم رفع ملفات التصميم الجديدة — الصفحة لن تعمل بشكل صحيح.</div>'
      + '<div>Old files on the server — this page will not work correctly.</div>'
      + '<div style="opacity:.85;font-weight:400;direction:ltr;margin-top:4px">re-upload: '
      + stale.join('  ·  ') + '</div>';
    document.body.appendChild(b);
  });
})();
</script>
</body>
</html>
