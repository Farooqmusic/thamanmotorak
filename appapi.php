<?php
/* ============================================================
   appapi.php — everything the mobile app needs to draw itself.

   The app ships with NO car database, NO translations and NO
   panel geometry of its own. It asks for all of it here, once
   per launch, and caches the answer. That means:

     • Khalid edits a word in the control panel  → the app shows it
     • a car model is added to assets/cars.js    → the app offers it
     • a panel shape changes in carmap.php       → the app redraws it

   …with no store update. One source of truth, three renderers
   (browser SVG, PDF via GD, and now Flutter).

   Reached as:  api.php?do=config
   ============================================================ */
declare(strict_types=1);

/* ------------------------------------------------------------------ */
/*  The Qatar car database                                             */
/*                                                                     */
/*  It lives in assets/cars.js because the website reads it as a plain */
/*  script — no PHP round trip. That file is a single JSON object with */
/*  a `window.CAR_DB =` in front of it, so we can hand the same bytes  */
/*  to the app without maintaining a second copy that would drift.     */
/* ------------------------------------------------------------------ */
function app_car_db(): array
{
    static $cache = null;
    if ($cache !== null) return $cache;

    $path = APP_ROOT . '/assets/cars.js';
    $js   = @file_get_contents($path);
    if ($js === false) { log_line('APPAPI: cars.js unreadable at ' . $path); return $cache = []; }

    /* from the first "{" after the assignment to the last "}" */
    $start = strpos($js, '{', (int)strpos($js, 'CAR_DB'));
    $end   = strrpos($js, '}');
    if ($start === false || $end === false || $end <= $start) {
        log_line('APPAPI: cars.js did not parse — CAR_DB assignment not found');
        return $cache = [];
    }

    $db = json_decode(substr($js, $start, $end - $start + 1), true);
    if (!is_array($db)) {
        log_line('APPAPI: cars.js did not parse — ' . json_last_error_msg());
        return $cache = [];
    }
    return $cache = $db;
}

/* ------------------------------------------------------------------ */
/*  Panel geometry, flattened for a client that is not a browser       */
/*                                                                     */
/*  carmap.php stores each polygon as a flat [x,y,x,y,…] list because  */
/*  that is what both the SVG writer and GD want. Flutter wants pairs, */
/*  so we pair them here rather than in Dart — if the storage format   */
/*  ever changes, this one function is the only place to follow.       */
/* ------------------------------------------------------------------ */
function app_pairs(array $flat): array
{
    $out = [];
    for ($i = 0; $i + 1 < count($flat); $i += 2) {
        $out[] = [round((float)$flat[$i], 2), round((float)$flat[$i + 1], 2)];
    }
    return $out;
}

function app_car_map(): array
{
    $parts = [];
    foreach (car_parts() as $key => $p) {
        $parts[$key] = [
            'ar'   => $p['ar'],
            'en'   => $p['en'],
            'poly' => app_pairs($p['poly']),
        ];
    }

    $glass = $grab = $wheels = [];
    foreach (car_decor() as $d) {
        switch ($d['kind'] ?? '') {
            case 'glass': $glass[]  = app_pairs($d['poly']); break;
            case 'grab':  $grab[]   = app_pairs($d['poly']); break;
            case 'wheel': $wheels[] = array_map('floatval', $d['circle']); break;
        }
    }

    return [
        'width'  => CM_W,
        'height' => CM_H,
        'blank'  => CM_BLANK,
        'line'   => CM_LINE,
        'parts'  => $parts,
        'order'  => car_part_keys(),
        'glass'  => $glass,
        'grab'   => $grab,
        'wheels' => $wheels,
    ];
}

/* ------------------------------------------------------------------ */
/*  The condition step, in full                                        */
/* ------------------------------------------------------------------ */
function app_condition(): array
{
    $states = [];
    foreach (cm_states() as $k => $s) {
        $states[$k] = ['ar' => $s['ar'], 'en' => $s['en'], 'color' => $s['color'], 'ink' => $s['ink']];
    }

    $paint = [];
    foreach (cond_paint_options() as $k => $o) {
        $paint[$k] = [
            'ar' => $o['ar'], 'en' => $o['en'],
            'hint_ar' => $o['hint_ar'], 'hint_en' => $o['hint_en'],
        ];
    }

    $scales = [];
    foreach (cond_scales() as $key => $def) {
        $opts = [];
        /* the order matters — 90 % first, 25 % last — and a JSON object does
           not promise one, so the keys are listed as well as mapped */
        foreach ($def['opts'] as $v => $o) $opts[$v] = ['ar' => $o['ar'], 'en' => $o['en']];
        $scales[$key] = [
            'ar' => $def['ar'], 'en' => $def['en'],
            'sub_ar' => $def['sub_ar'], 'sub_en' => $def['sub_en'],
            'opts' => $opts,
            'order' => array_keys($def['opts']),
        ];
    }

    return [
        'states'       => $states,
        'state_order'  => array_keys(cm_states()),
        'paint'        => $paint,
        'paint_order'  => array_keys(cond_paint_options()),
        'extent'       => cond_extent_options(),
        'extent_order' => array_keys(cond_extent_options()),
        'scales'       => $scales,
        'scale_order'  => array_keys(cond_scales()),
    ];
}

/* ------------------------------------------------------------------ */
/*  Trim — «الموديل / الفئة الفرعية»                                    */
/*                                                                     */
/*  This was a free-text box on the website and in v1 of the app, and a */
/*  free-text box is where data goes to die: GXR, gxr, G X R, "full     */
/*  option gxr" all mean one thing and sort as four. It is a list now,  */
/*  with «أخرى» still opening the box so nothing a seller owns can be   */
/*  impossible to describe.                                             */
/*                                                                     */
/*  Two axes, because Gulf sellers use both and they are not the same   */
/*  question: the factory badge (GXR, VXR, LTZ) and how loaded the car  */
/*  is (فل كامل / نص فل / ستاندرد).                                     */
/*                                                                     */
/*  Edit this list freely — the app reads it on launch, so a trim added */
/*  here appears on every phone the next morning with no new release.   */
/* ------------------------------------------------------------------ */
function app_trims(): array
{
    $badge = function (string $s): array {
        return ['key' => $s, 'ar' => $s, 'en' => $s];
    };

    /* Trims belong to a manufacturer, not to cars in general.
       GXR is a Toyota word. LTZ is a Chevrolet word. Rubicon is a Jeep word.
       Offering all ninety at once asks a Land Cruiser owner to find "GXR"
       somewhere between "Denali" and "Scat Pack" — which is not a list, it is
       a puzzle. The app now shows only the badges of the make chosen in the
       question above. */
    $byMake = [
        'Toyota'        => ['GX', 'GXR', 'VX', 'VXR', 'TXL', 'EXR', 'GR Sport', 'SE', 'SR5', 'XLI', 'GLI', 'Limited', 'TRD'],
        'Lexus'         => ['Standard', 'Prestige', 'Platinum', 'F Sport', 'Ultra Luxury'],
        'Nissan'        => ['XE', 'SE', 'SV', 'SL', 'LE', 'Platinum', 'Nismo', 'Titanium'],
        'Infiniti'      => ['Luxe', 'Essential', 'Sensory', 'Autograph', 'Sport'],
        'Mitsubishi'    => ['GLX', 'GLS', 'Highline', 'Standard', 'Ralliart'],
        'Honda'         => ['DX', 'LX', 'EX', 'EX-L', 'Sport', 'Touring', 'Type R'],
        'Mazda'         => ['S', 'SV', 'Touring', 'Grand Touring', 'Signature'],
        'Hyundai'       => ['GL', 'GLS', 'Smart', 'Comfort', 'Premium', 'Limited', 'Calligraphy', 'N Line'],
        'Kia'           => ['LX', 'EX', 'SX', 'GT-Line', 'Base', 'Mid', 'Full'],
        'Genesis'       => ['Premium', 'Luxury', 'Prestige', 'Sport'],
        'Chevrolet'     => ['LS', 'LT', 'LTZ', 'RS', 'Premier', 'High Country', 'Z71', 'SS'],
        'GMC'           => ['SLE', 'SLT', 'AT4', 'Denali', 'Elevation'],
        'Cadillac'      => ['Luxury', 'Premium Luxury', 'Sport', 'Platinum', 'V'],
        'Ford'          => ['XL', 'XLT', 'Lariat', 'King Ranch', 'Platinum', 'Titanium', 'ST', 'Raptor'],
        'Lincoln'       => ['Standard', 'Reserve', 'Black Label'],
        'Dodge'         => ['SXT', 'GT', 'R/T', 'SRT', 'Scat Pack', 'Hellcat'],
        'RAM'           => ['Tradesman', 'Big Horn', 'Laramie', 'Limited', 'Rebel', 'TRX'],
        'Chrysler'      => ['Touring', 'Limited', 'S'],
        'Jeep'          => ['Sport', 'Sahara', 'Rubicon', 'Laredo', 'Limited', 'Overland', 'Summit', 'Trailhawk'],
        'Mercedes-Benz' => ['Base', 'AMG Line', 'AMG', 'Maybach', '4MATIC', 'Night Package'],
        'BMW'           => ['Base', 'Sport Line', 'Luxury Line', 'M Sport', 'M', 'xDrive'],
    ];

    $out = [];
    foreach ($byMake as $make => $list) $out[$make] = array_map($badge, $list);

    return [
        'levels' => [
            ['key' => 'full', 'ar' => 'فل كامل',  'en' => 'Full option'],
            ['key' => 'mid',  'ar' => 'نص فل',    'en' => 'Mid option'],
            ['key' => 'std',  'ar' => 'ستاندرد',  'en' => 'Standard'],
        ],
        'byMake' => $out,
        /* For a make not listed above — the Chinese brands, mostly, where the
           badge is usually just a number. Short on purpose. */
        'default' => array_map($badge, ['Base', 'Mid', 'Full', 'Comfort', 'Luxury', 'Sport', 'Premium']),
        'other'  => ['ar' => 'أخرى — اكتب الفئة', 'en' => 'Other — type it'],
    ];
}

/* ------------------------------------------------------------------ */
/*  تصميم اليوم — the concept car of the day                            */
/*                                                                     */
/*  The website opens on a full-bleed concept picture that changes      */
/*  three times a day, the same car for everybody. The app opens on the */
/*  same one — picked here, by the server, so a phone and a laptop in   */
/*  the same room never show different cars.                            */
/* ------------------------------------------------------------------ */
function app_concept(): ?array
{
    $file = APP_ROOT . '/concept.php';
    if (!is_file($file)) return null;
    require_once $file;
    if (!function_exists('concept_today')) return null;

    $c = concept_today();
    if (!is_array($c)) return null;

    $base = rtrim(base_url(), '/') . '/';
    $abs = function (?string $rel) use ($base): ?string {
        return $rel === null ? null : $base . ltrim($rel, '/');
    };

    return [
        'name'   => (string)($c['name'] ?? ''),
        'slot'   => (int)($c['slot'] ?? 0),
        /* WebP first — it is roughly half the bytes, and every Android and
           iOS version that can run this app can decode it. */
        'image'  => $abs($c['webp'] ?? $c['jpg'] ?? null),
        'image_sm' => $abs($c['webp_sm'] ?? $c['jpg_sm'] ?? null),
        'jpg'    => $abs($c['jpg'] ?? null),
        'width'  => (int)($c['w'] ?? 0),
        'height' => (int)($c['h'] ?? 0),
        'button' => ['ar' => ct('splashBtn', 'ar'), 'en' => ct('splashBtn', 'en')],
    ];
}

/* ------------------------------------------------------------------ */
/*  The whole payload                                                  */
/* ------------------------------------------------------------------ */
function app_config_payload(): array
{
    $C = cfg();

    /* Only the fields a customer-facing screen actually needs. Nothing from
       config.php that is a credential, and nothing from the admin side. */
    $contact = public_contact();

    return [
        'ok'      => true,

        /* The app compares this against what it has cached. Bump it whenever
           the SHAPE of this payload changes — not its contents. */
        'schema'  => 1,

        /* Content revision: the app re-fetches when this moves, so an edit in
           the control panel reaches every phone on next launch. */
        'build'   => [
            'js'  => js_build(),
            'css' => css_build(),
        ],

        'site'    => rtrim(base_url(), '/'),

        'limits'  => [
            'minPhotos'     => (int)$C['min_photos'],
            'maxPhotos'     => (int)$C['max_photos'],
            'maxVideos'     => (int)$C['max_videos'],
            'maxPhotoMB'    => (int)$C['max_photo_mb'],
            'maxVideoMB'    => (int)$C['max_video_mb'],
            'retentionDays' => array_values(array_map('intval', (array)$C['retention_days'])),
        ],

        'currency' => ['ar' => $C['currency_ar'], 'en' => $C['currency_en']],
        'paidMode' => (bool)$C['paid_mode'],
        'price'    => ['ar' => (string)$C['price_ar'], 'en' => (string)$C['price_en']],

        'slots'    => slots(),
        'cond'     => app_condition(),
        'map'      => app_car_map(),
        'cars'     => app_car_db(),
        'trims'    => app_trims(),
        'concept'  => app_concept(),

        'supportKinds' => support_kinds(),

        /* every string Khalid can edit, in both languages */
        'i18n'     => content_i18n(),
        /* the built-in labels for the condition step, same shape */
        'condI18n' => cond_js_config()['i18n'],

        /* public_contact() returns a LIST of rows — [k, v, href] — not a map,
           and reading it as a map is why the app's contact card came up empty.
           Passed through as it comes: whatever Khalid has filled in appears, a
           field he clears disappears, and adding a fifth link to the panel
           needs no change here or in the app. */
        'contact'  => array_map(static function (array $c): array {
            return [
                'kind'  => (string)($c['k'] ?? ''),
                'label' => (string)($c['v'] ?? ''),
                'href'  => (string)($c['href'] ?? ''),
            ];
        }, $contact),

        /* «تطوير: فاروق» — the line is bilingual content, the address behind it
           is not, so the two travel separately. */
        'devCredit' => [
            'ar'  => ct('devCredit', 'ar'),
            'en'  => ct('devCredit', 'en'),
            'url' => (string)cv('devCreditUrl'),
        ],

        /* The app opens these in a web view rather than duplicating the legal
           text — one wording, edited in one place, and never out of date on a
           phone that has not been updated. */
        'pages'    => [
            'terms'   => rtrim(base_url(), '/') . '/terms.php',
            'privacy' => rtrim(base_url(), '/') . '/privacy.php',
            'guide'   => rtrim(base_url(), '/') . '/guide.php',
        ],

        /* Lets us retire an old build politely instead of letting it fail in
           ways the customer cannot understand. Raise minBuild only when an old
           app genuinely cannot work any more. */
        'app'      => [
            'minBuild' => 1,
            'notice'   => ['ar' => '', 'en' => ''],
        ],
    ];
}
