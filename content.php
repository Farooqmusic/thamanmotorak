<?php
/* ============================================================
   content.php — every word, link and contact detail that Khalid
   can change from the admin control panel.

   Defaults live here.  Whatever he saves goes to data/content.json
   and wins over the default, so the site never breaks even if the
   JSON file is deleted.

   Nothing in this file needs a database.
   ============================================================ */
declare(strict_types=1);

if (!defined('APP_ROOT')) define('APP_ROOT', __DIR__);
define('CONTENT_FILE', APP_ROOT . '/data/content.json');

/* ------------------------------------------------------------------
   THE SCHEMA
   ------------------------------------------------------------------
   group : which box it appears in inside the control panel
   type  : text | textarea | url | tel | email | toggle
   bi    : true  = separate Arabic and English value
           false = one value (a phone number, a link…)
   js    : true  = the key is also a translation key used by assets/app.js,
                   so the change shows the moment the visitor switches
                   language without a page reload.
   ------------------------------------------------------------------ */
function content_fields(): array
{
    static $f = null;
    if ($f !== null) return $f;

    $f = [

    /* ============ 1. brand ============ */
    'appName' => [
        'group' => 'brand', 'type' => 'text', 'bi' => true, 'js' => true,
        'la' => 'اسم الموقع', 'le' => 'Site name',
        'ar' => 'ثـمـــن مــوتــرك', 'en' => 'Evaluate Your Car',
    ],
    'tagline' => [
        'group' => 'brand', 'type' => 'text', 'bi' => true, 'js' => true,
        'la' => 'الجملة تحت الاسم', 'le' => 'Tagline under the name',
        'ar' => 'أول خدمة تقييم مجانية في قطر', 'en' => 'First free valuation service in Qatar',
    ],
    'metaDescription' => [
        'group' => 'brand', 'type' => 'textarea', 'bi' => true, 'js' => false,
        'la' => 'وصف الموقع لمحركات البحث', 'le' => 'Search-engine description',
        'ar' => 'أول خدمة مجانية لتقييم السيارات المستعملة أونلاين في قطر.',
        'en' => 'First free online used-car valuation service in Qatar.',
    ],
    'footer' => [
        'group' => 'brand', 'type' => 'text', 'bi' => true, 'js' => true,
        'la' => 'سطر أسفل الصفحة', 'le' => 'Footer line',
        'ar' => 'ثـمـــن مــوتــرك', 'en' => 'Evaluate Your Car',
    ],

    /* ============ 1b. Google & search engines ============ */
    'seoIndex' => [
        'group' => 'seo', 'type' => 'toggle', 'bi' => false, 'js' => false,
        'la' => 'السماح لجوجل بإظهار الموقع', 'le' => 'Let Google list the site',
        'v'  => '1',
    ],
    'siteUrl' => [
        'group' => 'seo', 'type' => 'url', 'bi' => false, 'js' => false,
        'la' => 'العنوان الرسمي للموقع', 'le' => 'Official website address',
        'v'  => 'https://thamanmotorak.com',
    ],
    'seoTitle' => [
        'group' => 'seo', 'type' => 'text', 'bi' => true, 'js' => true,
        'la' => 'العنوان الذي يظهر في نتائج البحث', 'le' => 'Headline shown in search results',
        'ar' => 'ثـمـــن مــوتــرك — تقييم مجاني لسيارتك في قطر',
        'en' => 'Evaluate Your Car — free used-car valuation in Qatar',
    ],
    'googleVerify' => [
        'group' => 'seo', 'type' => 'text', 'bi' => false, 'js' => false,
        'la' => 'رمز التحقق من Google Search Console', 'le' => 'Google Search Console verification code',
        'v'  => '',
    ],
    'bingVerify' => [
        'group' => 'seo', 'type' => 'text', 'bi' => false, 'js' => false,
        'la' => 'رمز التحقق من Bing', 'le' => 'Bing verification code',
        'v'  => '',
    ],
    'ogImage' => [
        'group' => 'seo', 'type' => 'url', 'bi' => false, 'js' => false,
        'la' => 'صورة المشاركة (واتساب/فيسبوك)', 'le' => 'Sharing image (WhatsApp / Facebook)',
        'v'  => '',
    ],

    /* ============ 2. home page ============ */
    'freeBadge' => [
        'group' => 'home', 'type' => 'text', 'bi' => true, 'js' => true,
        'la' => 'الشارة الذهبية', 'le' => 'Gold badge',
        'ar' => 'مجاناً 100%', 'en' => '100% FREE',
    ],
    'heroTitle' => [
        'group' => 'home', 'type' => 'text', 'bi' => true, 'js' => true,
        'la' => 'العنوان الرئيسي', 'le' => 'Main headline',
        'ar' => 'كم تساوي مــوتــرك؟', 'en' => 'What is your car worth?',
    ],
    'heroSub' => [
        'group' => 'home', 'type' => 'textarea', 'bi' => true, 'js' => true,
        'la' => 'الجملة تحت العنوان', 'le' => 'Line under the headline',
        'ar' => 'صوّر مــوتــرك، أرسل الصور، واحصل على السعر التقديري. بدون رسوم.',
        'en' => 'Photograph your car, send the pictures, get an estimated price. No fee.',
    ],
    'splashBtn' => [
        'group' => 'home', 'type' => 'text', 'bi' => true, 'js' => false,
        'la' => 'زر صفحة الترحيب', 'le' => 'Splash screen button',
        'ar' => 'ابدأ التقييم المجاني', 'en' => 'Start free valuation',
    ],
    'splashLightning' => [
        'group' => 'home', 'type' => 'toggle', 'bi' => false,
        'la' => 'برق على صورة الترحيب', 'le' => 'Lightning on the splash picture',
        'v'  => '1',
    ],
    'showSplashLink' => [
        'group' => 'home', 'type' => 'toggle', 'bi' => false,
        'la' => 'إظهار رابط «لديّ رقم طلب» تحت الزر',
        'le' => 'Show the “I already have a request number” link',
        'v'  => '1',
    ],
    'splashLink' => [
        'group' => 'home', 'type' => 'text', 'bi' => true, 'js' => false,
        'la' => 'الرابط الصغير تحت الزر', 'le' => 'Small link under the button',
        'ar' => 'لديّ رقم طلب', 'en' => 'I already have a request number',
    ],

    /* ============ 3. info page ============ */
    'overviewTitle' => [
        'group' => 'info', 'type' => 'text', 'bi' => true, 'js' => true,
        'la' => 'عنوان «نبذة عنا»', 'le' => 'Overview heading',
        'ar' => 'نبذة عنا', 'en' => 'Overview',
    ],
    'overviewBody' => [
        'group' => 'info', 'type' => 'textarea', 'bi' => true, 'js' => true,
        'la' => 'نص «نبذة عنا»', 'le' => 'Overview text',
        'ar' => 'ثـمـــن مــوتــرك خدمة قطرية تعطيك سعراً تقديرياً لسيارتك المستعملة خلال وقت قصير، '
              . 'من صور ترسلها من جوالك — بدون زيارة معرض وبدون أي رسوم. '
              . 'نعتمد على خبرة طويلة في سوق السيارات المستعملة في قطر وعلى أسعار البيع الفعلية، '
              . 'حتى تعرف قيمة سيارتك الحقيقية قبل أن تبيعها أو تبدّلها.',
        'en' => 'Evaluate Your Car is a Qatar-based service that gives you an estimated price for your '
              . 'used car in a short time, from photos you send with your phone — no showroom visit and no fee. '
              . 'The estimate is based on long experience in the Qatari used-car market and on real selling '
              . 'prices, so you know what your car is really worth before you sell it or trade it in.',
    ],
    'infoTitle' => [
        'group' => 'info', 'type' => 'text', 'bi' => true, 'js' => true,
        'la' => 'عنوان «كيف تعمل الخدمة»', 'le' => '“How it works” heading',
        'ar' => 'كيف تعمل الخدمة؟', 'en' => 'How it works',
    ],
    'step1v' => [
        'group' => 'info', 'type' => 'text', 'bi' => true, 'js' => true,
        'la' => 'الخطوة 1', 'le' => 'Step 1',
        'ar' => 'اختر بيانات موترك من القوائم.', 'en' => 'Pick your car from the dropdowns.',
    ],
    'step2v' => [
        'group' => 'info', 'type' => 'text', 'bi' => true, 'js' => true,
        'la' => 'الخطوة 2', 'le' => 'Step 2',
        'ar' => 'صوّر السيارة حسب الصناديق الثمانية.', 'en' => 'Photograph the car following the eight boxes.',
    ],
    'step3v' => [
        'group' => 'info', 'type' => 'text', 'bi' => true, 'js' => true,
        'la' => 'الخطوة 3', 'le' => 'Step 3',
        'ar' => 'تستلم رقم طلب على الشاشة وعلى بريدك.', 'en' => 'You get a request ID on screen and by email.',
    ],
    'step4v' => [
        'group' => 'info', 'type' => 'text', 'bi' => true, 'js' => true,
        'la' => 'الخطوة 4', 'le' => 'Step 4',
        'ar' => 'ضوء أحمر = تحت المراجعة. ضوء أخضر = السعر جاهز.',
        'en' => 'Red light = under review. Green light = price ready.',
    ],
    'privTitle' => [
        'group' => 'info', 'type' => 'text', 'bi' => true, 'js' => true,
        'la' => 'عنوان صندوق الخصوصية', 'le' => 'Privacy box heading',
        'ar' => 'الخصوصية', 'en' => 'Privacy',
    ],
    'privBody' => [
        'group' => 'info', 'type' => 'textarea', 'bi' => true, 'js' => true,
        'la' => 'نص صندوق الخصوصية', 'le' => 'Privacy box text',
        'ar' => 'صورك وفيديوهاتك تُحذف تلقائياً من الخادم بعد 3 أو 7 أيام حسب اختيارك. لا نشاركها مع أي جهة أخرى.',
        'en' => 'Your photos and videos are deleted from the server automatically after 3 or 7 days, whichever you chose. We never share them.',
    ],
    'cTitle' => [
        'group' => 'info', 'type' => 'text', 'bi' => true, 'js' => true,
        'la' => 'عنوان «تواصل معنا»', 'le' => '“Contact us” heading',
        'ar' => 'تواصل معنا', 'en' => 'Contact us',
    ],

    /* ============ 3b. support / report a problem ============ */
    'supportTitle' => [
        'group' => 'info', 'type' => 'text', 'bi' => true, 'js' => true,
        'la' => 'عنوان صفحة الدعم', 'le' => 'Support page heading',
        'ar' => 'الدعم والاقتراحات', 'en' => 'Support & suggestions',
    ],
    'supportIntro' => [
        'group' => 'info', 'type' => 'textarea', 'bi' => true, 'js' => true,
        'la' => 'نص صفحة الدعم', 'le' => 'Support page text',
        'ar' => 'واجهت مشكلة في الموقع؟ عندك اقتراح أو سؤال؟ اكتب لنا مباشرة من هنا وسنرد عليك.',
        'en' => 'Ran into a problem? Have a suggestion or a question? Write to us here and we will reply.',
    ],
    'supportThanks' => [
        'group' => 'info', 'type' => 'textarea', 'bi' => true, 'js' => true,
        'la' => 'رسالة الشكر بعد الإرسال', 'le' => 'Thank-you message after sending',
        'ar' => 'وصلتنا رسالتك، شكراً لك. سنتواصل معك على البريد أو الجوال الذي كتبته.',
        'en' => 'We have your message, thank you. We will get back to you on the email or number you gave.',
    ],

    /* ============ 4. contact details & links ============ */
    'contactEmail' => [
        'group' => 'contact', 'type' => 'email', 'bi' => false,
        'la' => 'البريد المعروض للزوار', 'le' => 'Email shown to visitors',
        'v'  => 'contact@thamanmotorak.com',
    ],
    'notifyEmail' => [
        'group' => 'contact', 'type' => 'email', 'bi' => false,
        'la' => 'البريد الذي تصله الطلبات الجديدة', 'le' => 'Email that receives new requests',
        'v'  => 'contact@thamanmotorak.com',
    ],
    'contactPhone' => [
        'group' => 'contact', 'type' => 'tel', 'bi' => false,
        'la' => 'رقم الجوال / واتساب', 'le' => 'Mobile / WhatsApp number',
        'v'  => '+974 3032 2225',
    ],
    'instagramUrl' => [
        'group' => 'contact', 'type' => 'url', 'bi' => false,
        'la' => 'رابط إنستقرام', 'le' => 'Instagram link',
        'v'  => 'https://www.instagram.com/k.f.a_5535',
    ],
    'instagramName' => [
        'group' => 'contact', 'type' => 'text', 'bi' => false,
        'la' => 'اسم حساب إنستقرام', 'le' => 'Instagram handle',
        'v'  => '@k.f.a_5535',
    ],
    'websiteUrl' => [
        'group' => 'contact', 'type' => 'url', 'bi' => false,
        'la' => 'رابط الموقع', 'le' => 'Website link',
        'v'  => 'https://thamanmotorak.com',
    ],
    'websiteName' => [
        'group' => 'contact', 'type' => 'text', 'bi' => false,
        'la' => 'الموقع كما يُكتب', 'le' => 'Website as written',
        'v'  => 'thamanmotorak.com',
    ],
    'extraLink1Url' => [
        'group' => 'contact', 'type' => 'url', 'bi' => false,
        'la' => 'رابط إضافي 1 — العنوان', 'le' => 'Extra link 1 — address',
        'v'  => '',
    ],
    'extraLink1Label' => [
        'group' => 'contact', 'type' => 'text', 'bi' => true, 'js' => true,
        'la' => 'رابط إضافي 1 — الاسم', 'le' => 'Extra link 1 — name',
        'ar' => '', 'en' => '',
    ],
    'extraLink2Url' => [
        'group' => 'contact', 'type' => 'url', 'bi' => false,
        'la' => 'رابط إضافي 2 — العنوان', 'le' => 'Extra link 2 — address',
        'v'  => '',
    ],
    'extraLink2Label' => [
        'group' => 'contact', 'type' => 'text', 'bi' => true, 'js' => true,
        'la' => 'رابط إضافي 2 — الاسم', 'le' => 'Extra link 2 — name',
        'ar' => '', 'en' => '',
    ],

    /* the developer credit at the bottom of the info screen.
       Clearing the link removes the whole line from the page. */
    'devCredit' => [
        'group' => 'contact', 'type' => 'text', 'bi' => true, 'js' => true,
        'la' => 'سطر المطوّر', 'le' => 'Developer credit line',
        'ar' => 'تطوير: فاروق', 'en' => 'Developed by Farooq',
    ],
    'devCreditUrl' => [
        'group' => 'contact', 'type' => 'url', 'bi' => false,
        'la' => 'رابط المطوّر (لا يظهر العنوان، فقط السطر أعلاه)',
        'le' => 'Developer link (the address is not shown, only the line above)',
        'v'  => 'https://www.farooqmusic.com',
    ],

    /* ============ 5. terms & conditions page ============ */
    'termsTitle' => [
        'group' => 'terms', 'type' => 'text', 'bi' => true, 'js' => true,
        'la' => 'عنوان الصفحة', 'le' => 'Page title',
        'ar' => 'الشروط والأحكام', 'en' => 'Terms & Conditions',
    ],
    'termsBody' => [
        'group' => 'terms', 'type' => 'textarea', 'bi' => true, 'js' => false,
        'la' => 'نص الشروط والأحكام', 'le' => 'Terms & Conditions text',
        'ar' => terms_default_ar(), 'en' => terms_default_en(),
    ],

    /* ============ 6. privacy page ============ */
    'privacyTitle' => [
        'group' => 'privacy', 'type' => 'text', 'bi' => true, 'js' => true,
        'la' => 'عنوان الصفحة', 'le' => 'Page title',
        'ar' => 'سياسة الخصوصية', 'en' => 'Privacy Policy',
    ],
    'privacyBody' => [
        'group' => 'privacy', 'type' => 'textarea', 'bi' => true, 'js' => false,
        'la' => 'نص سياسة الخصوصية', 'le' => 'Privacy Policy text',
        'ar' => privacy_default_ar(), 'en' => privacy_default_en(),
    ],

    /* ============ 7. company details used by the legal pages ============ */
    'legalName' => [
        'group' => 'legal', 'type' => 'text', 'bi' => true, 'js' => false,
        'la' => 'الاسم التجاري', 'le' => 'Trading name',
        'ar' => 'ثـمـــن مــوتــرك', 'en' => 'Thaman Motorak',
    ],
    'legalCR' => [
        'group' => 'legal', 'type' => 'text', 'bi' => false,
        'la' => 'رقم السجل التجاري (اتركه فارغاً إن لم يوجد)', 'le' => 'Commercial Registration no. (leave empty if none)',
        'v'  => '',
    ],
    'legalAddress' => [
        'group' => 'legal', 'type' => 'text', 'bi' => true, 'js' => false,
        'la' => 'العنوان', 'le' => 'Address',
        'ar' => 'الدوحة، دولة قطر', 'en' => 'Doha, State of Qatar',
    ],
    'legalUpdated' => [
        'group' => 'legal', 'type' => 'text', 'bi' => false,
        'la' => 'تاريخ آخر تحديث للصفحات القانونية', 'le' => 'Legal pages last-updated date',
        'v'  => 'August 2026',
    ],
    ];

    return $f;
}

/** Groups, in the order they appear in the control panel. */
function content_groups(): array
{
    return [
        'brand'   => ['ar' => 'الهوية والاسم',        'en' => 'Brand & name',       'ic' => '🏷️'],
        'seo'     => ['ar' => 'الظهور في جوجل',       'en' => 'Google & search',    'ic' => '🔎'],
        'home'    => ['ar' => 'الصفحة الرئيسية',      'en' => 'Home page',          'ic' => '🏠'],
        'info'    => ['ar' => 'صفحة المعلومات',       'en' => 'Info page',          'ic' => 'ℹ️'],
        'contact' => ['ar' => 'التواصل والروابط',     'en' => 'Contact & links',    'ic' => '📞'],
        'terms'   => ['ar' => 'الشروط والأحكام',      'en' => 'Terms & Conditions', 'ic' => '📄'],
        'privacy' => ['ar' => 'سياسة الخصوصية',       'en' => 'Privacy Policy',     'ic' => '🔒'],
        'legal'   => ['ar' => 'بيانات الشركة',        'en' => 'Company details',    'ic' => '🏢'],
    ];
}

/* ------------------------------------------------------------------
   reading
   ------------------------------------------------------------------ */

/** Saved overrides, or [] when nothing has been saved yet. */
function content_saved(): array
{
    /* After content_save() the freshly written array is put in this global, so the
       rest of the same request sees the new words instead of the old cached ones. */
    if (isset($GLOBALS['__content_saved_now']) && is_array($GLOBALS['__content_saved_now'])) {
        return $GLOBALS['__content_saved_now'];
    }
    static $s = null;
    if ($s !== null) return $s;
    $raw = @file_get_contents(CONTENT_FILE);
    $j   = json_decode((string)$raw, true);
    $s   = is_array($j) ? $j : [];
    return $s;
}

/**
 * Bilingual value.  ct('heroTitle') → Arabic, ct('heroTitle','en') → English.
 * Falls back to the other language, then to the default, so nothing is ever blank.
 */
function ct(string $key, string $lang = 'ar'): string
{
    $F = content_fields();
    if (!isset($F[$key])) return '';
    $f = $F[$key];
    $lang = ($lang === 'en') ? 'en' : 'ar';

    if (empty($f['bi'])) return cv($key);

    $saved = content_saved();
    $v = $saved[$key][$lang] ?? null;
    if (is_string($v) && trim($v) !== '') return $v;

    $other = $saved[$key][$lang === 'ar' ? 'en' : 'ar'] ?? null;
    $def   = (string)($f[$lang] ?? '');
    if ($def !== '') return $def;
    return is_string($other) ? $other : '';
}

/** Single (non-translated) value: a phone number, a link, a CR number… */
function cv(string $key): string
{
    $F = content_fields();
    if (!isset($F[$key])) return '';
    $saved = content_saved();
    $v = $saved[$key]['v'] ?? null;
    if (is_string($v) && trim($v) !== '') return trim($v);
    // an empty saved value is a deliberate "leave this out"
    if (is_string($v)) return '';
    return (string)($F[$key]['v'] ?? '');
}

/** Where new-request notifications go. Content wins, config.php is the fallback. */
function owner_email(): string
{
    $v = cv('notifyEmail');
    if ($v !== '' && filter_var($v, FILTER_VALIDATE_EMAIL)) return $v;
    return (string)cfg('owner_email');
}

/** Digits only, for wa.me links. */
function whatsapp_digits(): string
{
    return preg_replace('/[^0-9]/', '', cv('contactPhone')) ?: '';
}

/** The overrides handed to assets/app.js so a language switch needs no reload. */
function content_i18n(): array
{
    $out = ['ar' => [], 'en' => []];
    foreach (content_fields() as $k => $f) {
        if (empty($f['js']) || empty($f['bi'])) continue;
        $out['ar'][$k] = ct($k, 'ar');
        $out['en'][$k] = ct($k, 'en');
    }
    return $out;
}

/* ------------------------------------------------------------------
   writing  (admin control panel)
   ------------------------------------------------------------------ */

/** Save whatever the control-panel form posted. Returns the number of fields written. */
function content_save(array $post): int
{
    $F   = content_fields();
    $out = content_saved();
    $n   = 0;

    foreach ($F as $key => $f) {
        if (!empty($f['bi'])) {
            $ar = $post['c_' . $key . '_ar'] ?? null;
            $en = $post['c_' . $key . '_en'] ?? null;
            if ($ar === null && $en === null) continue;          // field not on this form
            $out[$key] = [
                'ar' => clean_content((string)$ar, $f['type']),
                'en' => clean_content((string)$en, $f['type']),
            ];
            $n++;
        } else {
            $v = $post['c_' . $key] ?? null;
            if ($v === null) continue;
            $out[$key] = ['v' => clean_content((string)$v, $f['type'])];
            $n++;
        }
    }

    if (!is_dir(dirname(CONTENT_FILE))) @mkdir(dirname(CONTENT_FILE), 0755, true);
    $tmp = CONTENT_FILE . '.tmp';
    @file_put_contents($tmp, json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    @rename($tmp, CONTENT_FILE);
    $GLOBALS['__content_saved_now'] = $out;
    return $n;
}

/** Put everything back to the defaults in this file. */
function content_restore_defaults(): void
{
    @unlink(CONTENT_FILE);
    $GLOBALS['__content_saved_now'] = [];
}

function clean_content(string $v, string $type): string
{
    $v = str_replace("\r\n", "\n", trim($v));
    // no HTML is ever stored — the pages escape everything they print
    $v = strip_tags($v);
    if ($type === 'url') {
        if ($v === '') return '';
        if (!preg_match('~^https?://~i', $v)) $v = 'https://' . ltrim($v, '/');
        return filter_var($v, FILTER_VALIDATE_URL) ? $v : '';
    }
    if ($type === 'email') {
        return filter_var($v, FILTER_VALIDATE_EMAIL) ? $v : '';
    }
    if ($type === 'toggle') {
        return $v === '1' ? '1' : '0';
    }
    if ($type !== 'textarea') $v = str_replace("\n", ' ', $v);
    return mb_substr($v, 0, $type === 'textarea' ? 20000 : 400);
}

/* ------------------------------------------------------------------
   rendering the long legal texts
   ------------------------------------------------------------------
   The stored text is plain — no HTML — so Khalid can safely edit it.
      ## a line starting with two hashes  → a heading
      - a line starting with a dash       → a bullet
      a blank line                        → a new paragraph
   ------------------------------------------------------------------ */
function content_render_body(string $text): string
{
    $out    = '';
    $inList = false;
    foreach (explode("\n", str_replace("\r\n", "\n", $text)) as $line) {
        $l = trim($line);
        if ($l === '') { if ($inList) { $out .= "</ul>\n"; $inList = false; } continue; }

        if (strncmp($l, '## ', 3) === 0) {
            if ($inList) { $out .= "</ul>\n"; $inList = false; }
            $out .= '<h3>' . htmlspecialchars(trim(substr($l, 3)), ENT_QUOTES, 'UTF-8') . "</h3>\n";
            continue;
        }
        if (strncmp($l, '- ', 2) === 0) {
            if (!$inList) { $out .= "<ul>\n"; $inList = true; }
            $out .= '<li>' . htmlspecialchars(trim(substr($l, 2)), ENT_QUOTES, 'UTF-8') . "</li>\n";
            continue;
        }
        if ($inList) { $out .= "</ul>\n"; $inList = false; }
        $out .= '<p>' . htmlspecialchars($l, ENT_QUOTES, 'UTF-8') . "</p>\n";
    }
    if ($inList) $out .= "</ul>\n";
    return $out;
}

/* ==================================================================
   The default legal texts.  Kept at the bottom so the schema above
   stays readable.  Khalid can rewrite every word from the panel.
   ================================================================== */

function terms_default_ar(): string
{
    return <<<'TXT'
## 1. قبول الشروط
باستخدامك هذا الموقع وطلب تقييم سيارتك فإنك توافق على الشروط والأحكام التالية. إذا لم توافق عليها، يرجى عدم استخدام الخدمة.

## 2. طبيعة الخدمة
نقدّم سعراً تقديرياً لسيارتك المستعملة اعتماداً على الصور والبيانات التي ترسلها أنت، وعلى خبرتنا بسوق السيارات المستعملة في قطر.

- السعر تقديري فقط وليس عرض شراء ولا التزاماً بالبيع أو الشراء بهذا السعر.
- قد يختلف السعر بعد المعاينة الفعلية للسيارة أو بعد فحصها فنياً.
- الخدمة ليست تقييماً معتمداً لأغراض التأمين أو القضاء أو التمويل البنكي.
- نحن لسنا طرفاً في أي عملية بيع أو شراء تتم بينك وبين أي شخص آخر.

## 3. مسؤوليتك عن البيانات والصور
- تتعهد بأن البيانات التي تدخلها صحيحة، وأن السيارة مملوكة لك أو أنك مخوّل بالتصرف بها.
- تتعهد بأن الصور والفيديو التي ترسلها من تصويرك أو أنك تملك حق استخدامها.
- يمنع إرسال أي محتوى مخالف للقانون أو للآداب العامة أو يحتوي على بيانات أشخاص آخرين دون إذنهم.
- إذا كانت البيانات أو الصور غير دقيقة أو غير كاملة، فقد يكون التقدير غير صحيح، ولا نتحمل مسؤولية ذلك.

## 4. استخدام الصور
نستخدم صورك وفيديوهاتك لغرض واحد فقط هو إعداد التقدير والتواصل معك بشأنه. لا نبيعها ولا ننشرها ولا نشاركها مع طرف ثالث. تُحذف تلقائياً بعد المدة التي تختارها (3 أو 7 أيام). التفاصيل في صفحة سياسة الخصوصية.

## 5. الرسوم
الخدمة مجانية بالكامل في الوقت الحالي. إذا أضفنا رسوماً مستقبلاً فسيظهر ذلك بوضوح على الموقع قبل إرسال الطلب، ولن تُفرض أي رسوم على طلب أرسلته قبل ذلك.

## 6. مدة الاحتفاظ والحذف
أنت تختار مدة الاحتفاظ بالملفات (3 أو 7 أيام) عند إرسال الطلب. بعد انتهاء المدة تُحذف الصور والفيديو من الخادم آلياً. يبقى سجل مختصر بالطلب (رقم الطلب وبيانات السيارة والسعر) لأغراض المتابعة، ويمكنك طلب حذفه بمراسلتنا.

## 7. حدود المسؤولية
- تُقدَّم الخدمة «كما هي» دون أي ضمان من أي نوع.
- لا نتحمل أي مسؤولية عن خسارة مالية أو ربح فائت أو أي ضرر مباشر أو غير مباشر ينتج عن الاعتماد على السعر التقديري.
- لا نضمن أن الموقع سيعمل دون انقطاع أو دون أخطاء، وقد نوقف الخدمة أو نعدّلها في أي وقت.
- قرار البيع أو الشراء وسعره قرارك أنت وحدك.

## 8. الملكية الفكرية
اسم الموقع وشعاره وتصميمه ونصوصه مملوكة لنا، ولا يجوز نسخها أو استخدامها تجارياً دون إذن كتابي.

## 9. الروابط الخارجية
قد يحتوي الموقع على روابط لمواقع أو حسابات أخرى مثل إنستقرام أو واتساب. هذه الجهات لها شروطها وسياسات خصوصيتها الخاصة، ولا نتحمل مسؤولية محتواها.

## 10. سوء الاستخدام
يحق لنا رفض أي طلب أو حظر أي مستخدم يسيء استخدام الخدمة، أو يرسل طلبات وهمية أو مكررة، أو يحاول الإضرار بالموقع.

## 11. تعديل الشروط
قد نعدّل هذه الشروط في أي وقت، ويسري التعديل من تاريخ نشره على هذه الصفحة. استمرارك في استخدام الخدمة بعد التعديل يعني موافقتك عليه.

## 12. القانون الواجب التطبيق
تخضع هذه الشروط لقوانين دولة قطر، وتختص محاكم دولة قطر بالنظر في أي نزاع ينشأ عنها.

## 13. التواصل
لأي استفسار بخصوص هذه الشروط، راسلنا على البريد الإلكتروني الموضّح في صفحة «تواصل معنا».
TXT;
}

function terms_default_en(): string
{
    return <<<'TXT'
## 1. Acceptance
By using this website and requesting a valuation you agree to the terms below. If you do not agree with them, please do not use the service.

## 2. What the service is
We give you an estimated price for your used car, based on the photos and details you send us and on our experience of the used-car market in Qatar.

- The price is an estimate only. It is not an offer to buy and it does not commit anyone to buy or sell at that price.
- The estimate may change after the car is seen or inspected in person.
- The service is not a certified valuation for insurance, court or bank-finance purposes.
- We are not a party to any sale you make with anyone else.

## 3. Your responsibility for the details and photos
- You confirm that the details you enter are correct and that you own the car or are authorised to act for its owner.
- You confirm that the photos and videos you send are yours to send.
- You must not send anything unlawful or indecent, or anything containing other people's personal details without their permission.
- If the details or photos are inaccurate or incomplete the estimate may be wrong, and we are not responsible for that.

## 4. Use of your photos
Your photos and videos are used for one purpose only: producing the estimate and contacting you about it. We do not sell, publish or share them with any third party. They are deleted automatically after the period you choose (3 or 7 days). See the Privacy Policy for details.

## 5. Fees
The service is completely free at present. If we introduce a fee in future it will be shown clearly on the site before you send a request, and no fee will ever be applied to a request you sent before that.

## 6. Retention and deletion
You choose how long we keep your files (3 or 7 days) when you send the request. After that period the photos and videos are deleted from the server automatically. A short record of the request (request ID, car details and price) is kept for follow-up, and you can ask us to delete it.

## 7. Limitation of liability
- The service is provided "as is", with no warranty of any kind.
- We accept no liability for financial loss, lost profit, or any direct or indirect damage arising from reliance on the estimated price.
- We do not guarantee that the site will be uninterrupted or error-free, and we may suspend or change it at any time.
- Any decision to sell or buy, and at what price, is yours alone.

## 8. Intellectual property
The name, logo, design and text of this site belong to us and may not be copied or used commercially without written permission.

## 9. External links
The site may link to other sites or accounts such as Instagram or WhatsApp. Those services have their own terms and privacy policies and we are not responsible for their content.

## 10. Misuse
We may refuse any request, or block any user who misuses the service, sends fake or duplicate requests, or attempts to damage the site.

## 11. Changes to these terms
We may change these terms at any time. Changes take effect when published on this page. Continuing to use the service after that means you accept them.

## 12. Governing law
These terms are governed by the laws of the State of Qatar, and the courts of the State of Qatar have jurisdiction over any dispute arising from them.

## 13. Contact
For any question about these terms, write to the email address shown on the "Contact us" page.
TXT;
}

function privacy_default_ar(): string
{
    return <<<'TXT'
## 1. مقدمة
تشرح هذه الصفحة ما نجمعه من بيانات عند استخدامك خدمة تقييم السيارات، ولماذا نجمعه، وكم مدة الاحتفاظ به، وما هي حقوقك.

## 2. البيانات التي نجمعها
- بيانات التواصل: الاسم، رقم الجوال، البريد الإلكتروني.
- بيانات السيارة: الشركة المصنعة، الفئة، الموديل، سنة الصنع، الممشى، رقم الاستمارة ورقم الشاصي إن أدخلتهما (وكلاهما اختياري)، وأي ملاحظات تكتبها.
- الصور والفيديو التي ترسلها للسيارة.
- بيانات تقنية بسيطة: عنوان IP وتاريخ ووقت الإرسال، لأغراض الأمان ومنع إساءة الاستخدام.

لا نطلب ولا نجمع أي بيانات بنكية أو بطاقات دفع.

## 3. لماذا نستخدمها
- لإعداد السعر التقديري لسيارتك.
- لإرسال رقم الطلب والنتيجة إلى بريدك الإلكتروني.
- للتواصل معك إذا احتجنا توضيحاً عن السيارة.
- لحماية الخدمة من الاستخدام المسيء.

الأساس القانوني هو موافقتك، التي تعطيها عند إرسال الطلب، ويمكنك سحبها في أي وقت بمراسلتنا.

## 4. مدة الاحتفاظ
- الصور والفيديو: تُحذف آلياً من الخادم بعد 3 أو 7 أيام حسب اختيارك عند الإرسال.
- سجل الطلب (رقم الطلب، بيانات السيارة، السعر، بيانات التواصل): يُحتفظ به لأغراض المتابعة والسجلات.
- يمكنك في أي وقت طلب حذف سجلك بالكامل بمراسلتنا على البريد الموضّح في صفحة «تواصل معنا»، ونستجيب خلال مدة معقولة.

## 5. مع من نشاركها
لا نبيع بياناتك ولا نؤجّرها ولا نشاركها لأغراض تسويقية. قد تمر البيانات عبر مزوّدي خدمة يقومون بتشغيل الموقع نيابة عنا فقط:

- شركة الاستضافة التي يعمل عليها الموقع والبريد.
- خدمة البريد الإلكتروني التي تُرسل رسائل التأكيد والنتيجة.

قد نفصح عن البيانات إذا طلبت ذلك جهة رسمية مختصة بموجب القانون القطري.

## 6. أمن البيانات
يعمل الموقع عبر اتصال مشفّر (HTTPS). الصور والفيديو محفوظة في مجلد لا يمكن الوصول إليه مباشرة من المتصفح، ولا تُفتح إلا عبر رابط موقّع أو من لوحة التحكم. مع ذلك، لا يمكن ضمان الأمان المطلق لأي نقل عبر الإنترنت.

## 7. حقوقك
لك الحق في:
- طلب نسخة من البيانات المحفوظة عنك.
- طلب تصحيح أي بيان غير صحيح.
- طلب حذف بياناتك.
- سحب موافقتك في أي وقت.

راسلنا على البريد الإلكتروني الموضّح في صفحة «تواصل معنا» ونتعامل مع طلبك.

## 8. ملفات تعريف الارتباط والتخزين المحلي
لا نستخدم إعلانات ولا أدوات تتبّع خارجية. نستخدم فقط تخزيناً محلياً في متصفحك لحفظ تفضيلين اثنين: اللغة المختارة، والوضع الليلي أو النهاري. يمكنك مسحهما من إعدادات المتصفح دون التأثير على الخدمة.

## 9. الأطفال
الخدمة موجّهة لمن أتمّ 18 عاماً. لا نجمع عن قصد بيانات من هم دون هذه السن.

## 10. تعديل السياسة
قد نحدّث هذه السياسة، ويسري التحديث من تاريخ نشره على هذه الصفحة.

## 11. التواصل
لأي سؤال عن الخصوصية أو لطلب حذف بياناتك، راسلنا على البريد الإلكتروني الموضّح في صفحة «تواصل معنا».
TXT;
}

function privacy_default_en(): string
{
    return <<<'TXT'
## 1. Introduction
This page explains what we collect when you use the car valuation service, why we collect it, how long we keep it, and what your rights are.

## 2. What we collect
- Contact details: your name, mobile number and email address.
- Car details: make, class, model, year, mileage, registration number and chassis/VIN if you enter them (both optional), and any notes you write.
- The photos and videos of the car that you send.
- Basic technical data: your IP address and the date and time of submission, for security and to prevent misuse.

We never ask for or collect any bank or payment card details.

## 3. Why we use it
- To produce the estimated price for your car.
- To email you the request ID and the result.
- To contact you if we need something clarified about the car.
- To protect the service from abuse.

The legal basis is your consent, which you give when you send the request, and which you can withdraw at any time by writing to us.

## 4. How long we keep it
- Photos and videos: deleted from the server automatically after 3 or 7 days, whichever you chose when submitting.
- The request record (request ID, car details, price, contact details): kept for follow-up and record-keeping.
- You can ask us at any time to delete your record completely by writing to the email address on the "Contact us" page, and we respond within a reasonable period.

## 5. Who we share it with
We do not sell, rent or share your data for marketing. Data may pass through service providers who only run the site on our behalf:

- The hosting company the website and mail run on.
- The email service that sends the confirmation and result messages.

We may disclose data if a competent authority requires it under Qatari law.

## 6. Security
The site runs over an encrypted connection (HTTPS). Photos and videos are stored in a folder that cannot be reached directly from a browser and are only opened through a signed link or from the admin panel. Even so, no transmission over the internet can be guaranteed to be completely secure.

## 7. Your rights
You have the right to:
- Ask for a copy of the data we hold about you.
- Ask us to correct anything that is wrong.
- Ask us to delete your data.
- Withdraw your consent at any time.

Write to the email address on the "Contact us" page and we will deal with your request.

## 8. Cookies and local storage
We use no advertising and no third-party tracking. We only use local storage in your browser to remember two preferences: your chosen language, and light or dark mode. You can clear these from your browser settings without affecting the service.

## 9. Children
The service is intended for people aged 18 and over. We do not knowingly collect data from anyone younger.

## 10. Changes to this policy
We may update this policy. Updates take effect when published on this page.

## 11. Contact
For any privacy question, or to ask us to delete your data, write to the email address shown on the "Contact us" page.
TXT;
}
