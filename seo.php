<?php
/* ============================================================
   seo.php — everything Google, Bing and WhatsApp need in order to
   find, understand and preview the site.

   Nothing here is decorative. In order:
     • the visitor's language, taken from ?lang= so Arabic and English
       are two real, separately indexable addresses
     • <link rel="canonical"> so the same page under different query
       strings is not counted as duplicate content
     • hreflang, so Google serves the Arabic page to Arabic searchers
       and the English page to everyone else
     • Open Graph + Twitter cards — the preview card in WhatsApp
     • JSON-LD structured data describing the business and the service
     • the Search Console / Bing verification tags

   Switched on and off from the control panel:  🔎 Google & search.
   Included by index.php, terms.php and privacy.php (via legalpage.php).
   ============================================================ */
declare(strict_types=1);

if (!function_exists('cfg')) { require_once __DIR__ . '/lib.php'; }

/** ar | en — the language this request should be rendered in. */
function seo_lang(): string
{
    $q = strtolower(trim((string)($_GET['lang'] ?? '')));
    if ($q === 'en' || $q === 'ar') return $q;
    return 'ar';
}

function seo_dir(string $lang = ''): string
{
    return (($lang ?: seo_lang()) === 'ar') ? 'rtl' : 'ltr';
}

/** https://thamanmotorak.com — no trailing slash. Panel value wins, else the live host. */
function seo_origin(): string
{
    $v = cv('siteUrl');
    if ($v !== '' && preg_match('~^https?://~i', $v)) return rtrim($v, '/');

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
          || (int)($_SERVER['SERVER_PORT'] ?? 0) === 443
          || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $host = (string)($_SERVER['HTTP_HOST'] ?? 'thamanmotorak.com');
    return ($https ? 'https://' : 'http://') . $host;
}

/** The folder the app is installed in, '' at the domain root. */
function seo_base(): string
{
    $p = rtrim(str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/index.php'))), '/');
    return ($p === '/' || $p === '.') ? '' : $p;
}

/** Absolute URL for one of our own pages. seo_url('index.php','en') etc. */
function seo_url(string $file = '', string $lang = ''): string
{
    $u = seo_origin() . seo_base() . '/' . ltrim($file, '/');
    if ($lang === 'en') $u .= (strpos($u, '?') === false ? '?' : '&') . 'lang=en';
    return $u;
}

/** Is Google allowed to list us? Panel switch first, config.php as the fallback. */
function seo_indexable(): bool
{
    $saved = content_saved();
    if (isset($saved['seoIndex']['v'])) return (string)$saved['seoIndex']['v'] !== '0';
    return !cfg('noindex');
}

/** The picture WhatsApp and Facebook show when the link is shared. */
function seo_image(): string
{
    $v = cv('ogImage');
    if ($v !== '' && preg_match('~^https?://~i', $v)) return $v;
    return seo_origin() . seo_base() . '/icons/icon-512.png';
}

function seo_title(string $lang): string
{
    $t = ct('seoTitle', $lang);
    if (trim($t) !== '') return $t;
    return trim(ct('appName', $lang) . ' — ' . ct('tagline', $lang), ' —');
}

function seo_description(string $lang): string
{
    $d = trim(ct('metaDescription', $lang));
    if ($d === '') $d = trim(ct('heroSub', $lang));
    return mb_substr($d, 0, 300);
}

/* ------------------------------------------------------------------
   structured data — what tells Google *what this site is*
   ------------------------------------------------------------------ */

function seo_jsonld(string $lang): string
{
    $home = seo_url('');
    $org  = $home . '#org';

    $sameAs = [];
    foreach (['instagramUrl', 'websiteUrl', 'extraLink1Url', 'extraLink2Url'] as $k) {
        $v = cv($k);
        if ($v !== '' && preg_match('~^https?://~i', $v)) $sameAs[] = $v;
    }

    $business = [
        '@type'      => ['Organization', 'AutomotiveBusiness'],
        '@id'        => $org,
        'name'       => ct('appName', $lang),
        'alternateName' => ct('appName', $lang === 'ar' ? 'en' : 'ar'),
        'url'        => $home,
        'logo'       => seo_image(),
        'image'      => seo_image(),
        'description'=> seo_description($lang),
        'areaServed' => ['@type' => 'Country', 'name' => $lang === 'ar' ? 'قطر' : 'Qatar'],
        'knowsLanguage' => ['ar', 'en'],
    ];
    if (cv('contactEmail') !== '')  $business['email']     = cv('contactEmail');
    if (cv('contactPhone') !== '')  $business['telephone'] = cv('contactPhone');
    if ($sameAs)                    $business['sameAs']    = array_values(array_unique($sameAs));
    if (ct('legalAddress', $lang) !== '') {
        $business['address'] = ['@type' => 'PostalAddress',
                                'streetAddress'  => ct('legalAddress', $lang),
                                'addressCountry' => 'QA'];
    }
    if (ct('legalName', $lang) !== '') $business['legalName'] = ct('legalName', $lang);

    $graph = [
        [
            '@type'      => 'WebSite',
            '@id'        => $home . '#website',
            'url'        => $home,
            'name'       => ct('appName', $lang),
            'inLanguage' => $lang === 'ar' ? 'ar-QA' : 'en',
            'publisher'  => ['@id' => $org],
        ],
        $business,
        [
            '@type'       => 'Service',
            '@id'         => $home . '#service',
            'name'        => $lang === 'ar' ? 'تقييم السيارات المستعملة أونلاين' : 'Online used-car valuation',
            'serviceType' => $lang === 'ar' ? 'تقييم سيارة' : 'Car valuation',
            'provider'    => ['@id' => $org],
            'areaServed'  => ['@type' => 'Country', 'name' => $lang === 'ar' ? 'قطر' : 'Qatar'],
            'description' => seo_description($lang),
            'offers'      => [
                '@type'         => 'Offer',
                'price'         => cfg('paid_mode') ? preg_replace('/[^0-9.]/', '', (string)cfg('price_en')) : '0',
                'priceCurrency' => 'QAR',
                'availability'  => 'https://schema.org/InStock',
                'url'           => $home,
            ],
        ],
    ];

    return json_encode(
        ['@context' => 'https://schema.org', '@graph' => $graph],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
}

/* ------------------------------------------------------------------
   the block that goes inside <head>
   ------------------------------------------------------------------ */

/**
 * $file  — 'index.php' | 'terms.php' | 'privacy.php'  (the page we are on)
 * $title / $desc — override the automatic ones when a page has its own.
 */
function seo_head(string $file = 'index.php', string $title = '', string $desc = ''): string
{
    $lang = seo_lang();
    $home = ($file === 'index.php' || $file === '');
    $path = $home ? '' : $file;

    $title = $title !== '' ? $title : seo_title($lang);
    $desc  = $desc  !== '' ? $desc  : seo_description($lang);

    $canon = seo_url($path, $lang === 'en' ? 'en' : '');
    $urlAr = seo_url($path);
    $urlEn = seo_url($path, 'en');

    $h  = '<meta name="robots" content="' . (seo_indexable()
            ? 'index, follow, max-image-preview:large, max-snippet:-1'
            : 'noindex, nofollow') . '">' . "\n";
    $h .= '<link rel="canonical" href="' . e($canon) . '">' . "\n";
    $h .= '<link rel="alternate" hreflang="ar" href="' . e($urlAr) . '">' . "\n";
    $h .= '<link rel="alternate" hreflang="en" href="' . e($urlEn) . '">' . "\n";
    $h .= '<link rel="alternate" hreflang="x-default" href="' . e($urlAr) . '">' . "\n";

    /* the preview card in WhatsApp, Facebook, LinkedIn, X */
    $h .= '<meta property="og:type" content="website">' . "\n";
    $h .= '<meta property="og:site_name" content="' . e(ct('appName', $lang)) . '">' . "\n";
    $h .= '<meta property="og:title" content="' . e($title) . '">' . "\n";
    $h .= '<meta property="og:description" content="' . e($desc) . '">' . "\n";
    $h .= '<meta property="og:url" content="' . e($canon) . '">' . "\n";
    $h .= '<meta property="og:image" content="' . e(seo_image()) . '">' . "\n";
    $h .= '<meta property="og:locale" content="' . ($lang === 'ar' ? 'ar_QA' : 'en_US') . '">' . "\n";
    $h .= '<meta property="og:locale:alternate" content="' . ($lang === 'ar' ? 'en_US' : 'ar_QA') . '">' . "\n";
    $h .= '<meta name="twitter:card" content="summary_large_image">' . "\n";
    $h .= '<meta name="twitter:title" content="' . e($title) . '">' . "\n";
    $h .= '<meta name="twitter:description" content="' . e($desc) . '">' . "\n";
    $h .= '<meta name="twitter:image" content="' . e(seo_image()) . '">' . "\n";

    /* ownership proof for the two search consoles */
    if (cv('googleVerify') !== '') {
        $h .= '<meta name="google-site-verification" content="' . e(cv('googleVerify')) . '">' . "\n";
    }
    if (cv('bingVerify') !== '') {
        $h .= '<meta name="msvalidate.01" content="' . e(cv('bingVerify')) . '">' . "\n";
    }

    $h .= '<script type="application/ld+json">' . seo_jsonld($lang) . '</script>' . "\n";

    return $h;
}
