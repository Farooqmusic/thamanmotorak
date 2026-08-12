<?php
/* ============================================================
   carmap.php — the car condition module.

   ONE source of truth for:
     · the exploded car diagram (panel polygons)
     · the paint / accident status question
     · the interior, engine and gearbox quality questions

   The same geometry is used three times:
     · car_map_svg()  — the interactive diagram inside the form
     · car_map_gd()   — the same diagram drawn onto page 3 of the PDF
     · admin.php      — a read-only copy of the customer's answers

   Every shape is a plain polygon (wheel arches are arcs baked into the
   point list) so GD and SVG draw exactly the same picture.
   ============================================================ */
declare(strict_types=1);

define('CM_W', 800);          // diagram viewBox
define('CM_H', 764);

/* fallback colours written straight onto the SVG shapes — see car_map_svg() */
define('CM_BLANK', '#f0eaed');
define('CM_LINE',  '#b3a3aa');
define('CM_GLASS', '#e2ebf4');

/* the two marking states a panel can carry */
function cm_states(): array
{
    return [
        'painted'  => ['ar' => 'صبغ',          'en' => 'Repainted',       'color' => '#e0a12c', 'ink' => '#5a3d00'],
        'accident' => ['ar' => 'حادث / إصلاح', 'en' => 'Accident repair', 'color' => '#d1435b', 'ink' => '#ffffff'],
    ];
}

function cm_state_label(string $s, string $lang = 'ar'): string
{
    $m = cm_states();
    return isset($m[$s]) ? (string)$m[$s][$lang === 'en' ? 'en' : 'ar'] : '';
}

/* ------------------------------------------------------------------ */
/*  geometry helpers                                                   */
/* ------------------------------------------------------------------ */

/** points along an arc; angles in degrees, y grows downwards */
function cm_arc(float $cx, float $cy, float $r, float $a1, float $a2, int $steps = 16): array
{
    $p = [];
    for ($i = 0; $i <= $steps; $i++) {
        $a = deg2rad($a1 + ($a2 - $a1) * $i / $steps);
        $p[] = round($cx + $r * cos($a), 1);
        $p[] = round($cy + $r * sin($a), 1);
    }
    return $p;
}

/** mirror a flat point list around the vertical centre line */
function cm_mirror(array $pts): array
{
    $out = [];
    for ($i = 0; $i < count($pts); $i += 2) {
        $out[] = CM_W - $pts[$i];
        $out[] = $pts[$i + 1];
    }
    /* keep the winding order sane after mirroring */
    $rev = [];
    for ($i = count($out) - 2; $i >= 0; $i -= 2) { $rev[] = $out[$i]; $rev[] = $out[$i + 1]; }
    return $rev;
}

function cm_rect(float $x1, float $y1, float $x2, float $y2): array
{
    return [$x1, $y1, $x2, $y1, $x2, $y2, $x1, $y2];
}

/** rounded rectangle, clockwise from the top-left corner */
function cm_rrect(float $x1, float $y1, float $x2, float $y2, float $r, int $steps = 5): array
{
    $r = min($r, ($x2 - $x1) / 2, ($y2 - $y1) / 2);
    return array_merge(
        cm_arc($x1 + $r, $y1 + $r, $r, 180, 270, $steps),
        cm_arc($x2 - $r, $y1 + $r, $r, 270, 360, $steps),
        cm_arc($x2 - $r, $y2 - $r, $r,   0,  90, $steps),
        cm_arc($x1 + $r, $y2 - $r, $r,  90, 180, $steps)
    );
}

/** rounded trapezoid: $x1..$x2 at the top, $x3..$x4 at the bottom */
function cm_trap(float $x1, float $x2, float $y1, float $x3, float $x4, float $y2, float $r = 12): array
{
    $in = function (float $ax, float $ay, float $bx, float $by, float $d) {
        $len = max(0.001, sqrt(($bx - $ax) ** 2 + ($by - $ay) ** 2));
        return [$ax + ($bx - $ax) / $len * $d, $ay + ($by - $ay) / $len * $d];
    };
    $c   = [[$x1, $y1], [$x2, $y1], [$x4, $y2], [$x3, $y2]];
    $out = [];
    foreach ($c as $i => $p) {
        $prev = $c[($i + 3) % 4];
        $next = $c[($i + 1) % 4];
        [$ax, $ay] = $in($p[0], $p[1], $prev[0], $prev[1], $r);
        [$bx, $by] = $in($p[0], $p[1], $next[0], $next[1], $r);
        /* a 3-point curve rounds the corner well enough at this size */
        $out[] = round($ax, 1); $out[] = round($ay, 1);
        $out[] = round(($ax + $p[0] * 1.1 + $bx) / 3.1, 1);
        $out[] = round(($ay + $p[1] * 1.1 + $by) / 3.1, 1);
        $out[] = round($bx, 1); $out[] = round($by, 1);
    }
    return $out;
}

/* ------------------------------------------------------------------ */
/*  THE PANELS                                                         */
/* ------------------------------------------------------------------ */

function car_parts(): array
{
    static $p = null;
    if ($p !== null) return $p;

    /* ---- left side ----
       The side of the car is drawn “unfolded”: the edge nearest the centre
       column is the roofline (so the windows sit there) and the far edge is
       the bottom of the car (so the wheel arches are cut into it).          */

    // front-left wing — wheel arch cut into the outer edge, near the bottom
    $fenderFL = array_merge(
        cm_arc(142, 100, 14, 180, 270, 4),                 // top-left corner
        cm_arc(248, 100, 14, 270, 360, 4),                 // top-right corner
        cm_arc(248, 238, 14,   0,  90, 4),                 // bottom-right corner
        cm_arc(142, 238, 14,  90, 180, 4),                 // bottom-left corner
        cm_arc(128, 196, 42,  90, -90, 18)                 // the wheel arch, cut upwards
    );
    // rear-left quarter — same panel, arch near its top edge
    $fenderRL = array_merge(
        cm_arc(142, 570, 14, 180, 270, 4),
        cm_arc(248, 570, 14, 270, 360, 4),
        cm_arc(248, 712, 14,   0,  90, 4),
        cm_arc(142, 712, 14,  90, 180, 4),
        cm_arc(128, 614, 42,  90, -90, 18)
    );

    $p = [

        /* ============ centre column ============ */
        'front_bumper' => [
            'ar' => 'الصدام الأمامي', 'en' => 'Front bumper',
            'poly' => cm_trap(310, 490, 14, 272, 528, 68, 22),
        ],
        'hood' => [
            'ar' => 'غطاء المحرك (الكبوت)', 'en' => 'Bonnet / hood',
            'poly' => cm_trap(296, 504, 82, 276, 524, 218, 24),
        ],
        'roof' => [
            'ar' => 'السقف', 'en' => 'Roof',
            'poly' => cm_rrect(288, 306, 512, 452, 20),
        ],
        'trunk' => [
            'ar' => 'غطاء الصندوق', 'en' => 'Boot lid / trunk',
            'poly' => cm_trap(280, 520, 528, 296, 504, 656, 22),
        ],
        'rear_bumper' => [
            'ar' => 'الصدام الخلفي', 'en' => 'Rear bumper',
            'poly' => cm_trap(272, 528, 670, 306, 494, 724, 22),
        ],

        /* ============ left side ============ */
        'fender_fl' => ['ar' => 'الرفرف الأمامي الأيسر', 'en' => 'Front left wing',   'poly' => $fenderFL],
        'door_fl'   => ['ar' => 'الباب الأمامي الأيسر',  'en' => 'Front left door',   'poly' => cm_rrect(126, 260, 262, 406, 13)],
        'door_rl'   => ['ar' => 'الباب الخلفي الأيسر',   'en' => 'Rear left door',    'poly' => cm_rrect(126, 414, 262, 552, 13)],
        'fender_rl' => ['ar' => 'الرفرف الخلفي الأيسر',  'en' => 'Rear left quarter', 'poly' => $fenderRL],
        'rocker_l'  => ['ar' => 'العتبة اليسرى',         'en' => 'Left sill',         'poly' => cm_rrect(84, 268, 112, 544, 12)],

        /* ============ right side (mirrored) ============ */
        'fender_fr' => ['ar' => 'الرفرف الأمامي الأيمن', 'en' => 'Front right wing',   'poly' => cm_mirror($fenderFL)],
        'door_fr'   => ['ar' => 'الباب الأمامي الأيمن',  'en' => 'Front right door',   'poly' => cm_mirror(cm_rrect(126, 260, 262, 406, 13))],
        'door_rr'   => ['ar' => 'الباب الخلفي الأيمن',   'en' => 'Rear right door',    'poly' => cm_mirror(cm_rrect(126, 414, 262, 552, 13))],
        'fender_rr' => ['ar' => 'الرفرف الخلفي الأيمن',  'en' => 'Rear right quarter', 'poly' => cm_mirror($fenderRL)],
        'rocker_r'  => ['ar' => 'العتبة اليمنى',         'en' => 'Right sill',         'poly' => cm_mirror(cm_rrect(84, 268, 112, 544, 12))],
    ];
    return $p;
}

function car_part_keys(): array { return array_keys(car_parts()); }

function car_part_label(string $key, string $lang = 'ar'): string
{
    $p = car_parts();
    return isset($p[$key]) ? (string)$p[$key][$lang === 'en' ? 'en' : 'ar'] : $key;
}

/** glass, wheels and door handles — drawn on top, never clickable */
function car_decor(): array
{
    /* windows hug the beltline edge (x ≈ 262 on the left column) */
    $winFL  = cm_rrect(186, 276, 250, 366, 9);
    $winRL  = cm_rrect(186, 430, 250, 512, 9);
    $grabFL = cm_rrect(150, 296, 176, 306, 5);
    $grabRL = cm_rrect(150, 450, 176, 460, 5);

    return [
        ['kind' => 'glass', 'poly' => cm_trap(300, 500, 224, 282, 518, 296, 16)],   // windscreen
        ['kind' => 'glass', 'poly' => cm_trap(292, 508, 460, 304, 496, 524, 16)],   // rear screen
        ['kind' => 'glass', 'poly' => $winFL],
        ['kind' => 'glass', 'poly' => $winRL],
        ['kind' => 'glass', 'poly' => cm_mirror($winFL)],
        ['kind' => 'glass', 'poly' => cm_mirror($winRL)],
        ['kind' => 'grab',  'poly' => $grabFL],
        ['kind' => 'grab',  'poly' => $grabRL],
        ['kind' => 'grab',  'poly' => cm_mirror($grabFL)],
        ['kind' => 'grab',  'poly' => cm_mirror($grabRL)],
        ['kind' => 'wheel', 'circle' => [114.0, 196.0, 38.0]],
        ['kind' => 'wheel', 'circle' => [114.0, 614.0, 38.0]],
        ['kind' => 'wheel', 'circle' => [686.0, 196.0, 38.0]],
        ['kind' => 'wheel', 'circle' => [686.0, 614.0, 38.0]],
    ];
}

/* ------------------------------------------------------------------ */
/*  SVG                                                                */
/* ------------------------------------------------------------------ */

/**
 * $states = ['hood' => 'painted', 'door_fl' => 'accident', …]
 * $interactive = true  → the version inside the form (tappable)
 *              = false → a read-only picture for the admin panel
 */
function car_map_svg(array $states = [], bool $interactive = true, string $lang = 'ar'): string
{
    /* Every shape also carries plain fill/stroke attributes. A stylesheet always
       beats a presentation attribute, so app.css still owns the look — but if
       app.css is ever missing or stale the diagram stays readable instead of
       falling back to the SVG default, which is solid black. */
    $mark = cm_states();

    $s = '<svg class="cmsvg" viewBox="0 0 ' . CM_W . ' ' . CM_H . '" xmlns="http://www.w3.org/2000/svg" '
       . 'role="img" aria-label="' . htmlspecialchars($lang === 'en' ? 'Car panels' : 'أجزاء السيارة', ENT_QUOTES) . '">';

    /* wheels sit behind the body panels */
    $s .= '<g class="cm-wheels" aria-hidden="true">';
    foreach (car_decor() as $d) {
        if (($d['kind'] ?? '') !== 'wheel') continue;
        [$cx, $cy, $r] = $d['circle'];
        $s .= '<circle class="cm-wheel" cx="' . $cx . '" cy="' . $cy . '" r="' . $r . '"'
            . ' fill="' . CM_BLANK . '" stroke="' . CM_LINE . '" stroke-width="2"/>';
    }
    $s .= '</g>';

    $s .= '<g class="cm-parts">';
    foreach (car_parts() as $key => $p) {
        $st  = (string)($states[$key] ?? '');
        $pts = [];
        for ($i = 0; $i < count($p['poly']); $i += 2) $pts[] = $p['poly'][$i] . ',' . $p['poly'][$i + 1];
        $label = htmlspecialchars($p[$lang === 'en' ? 'en' : 'ar'], ENT_QUOTES);
        $fill  = $st !== '' && isset($mark[$st]) ? $mark[$st]['color'] : CM_BLANK;
        $s .= '<polygon class="cm-part" data-part="' . $key . '"'
            . ($st !== '' ? ' data-state="' . htmlspecialchars($st, ENT_QUOTES) . '"' : '')
            . ($interactive ? ' tabindex="0" role="button"' : '')
            . ' aria-label="' . $label . '"'
            . ' fill="' . $fill . '" stroke="' . CM_LINE . '" stroke-width="2" stroke-linejoin="round"'
            . ' points="' . implode(' ', $pts) . '"><title>' . $label . '</title></polygon>';
    }
    $s .= '</g>';

    $s .= '<g class="cm-decor" aria-hidden="true">';
    foreach (car_decor() as $d) {
        if (($d['kind'] ?? '') === 'wheel') continue;
        $pts = [];
        for ($i = 0; $i < count($d['poly']); $i += 2) $pts[] = $d['poly'][$i] . ',' . $d['poly'][$i + 1];
        $grab = ($d['kind'] === 'grab');
        $s .= '<polygon class="cm-' . ($grab ? 'grab' : 'glass') . '"'
            . ' fill="' . ($grab ? CM_LINE : CM_GLASS) . '"'
            . ($grab ? '' : ' stroke="' . CM_LINE . '" stroke-width="2"')
            . ' points="' . implode(' ', $pts) . '"/>';
    }
    $s .= '</g></svg>';
    return $s;
}

/* ------------------------------------------------------------------ */
/*  GD — the same picture, drawn onto page 3 of the PDF                 */
/* ------------------------------------------------------------------ */

function cm_poly($im, array $pts, $color): void
{
    $ints = array_map('intval', array_map(function ($v) { return (int)round((float)$v); }, $pts));
    if (PHP_VERSION_ID >= 80100) { imagefilledpolygon($im, $ints, $color); }
    else { imagefilledpolygon($im, $ints, (int)(count($ints) / 2), $color); }
}

function cm_poly_line($im, array $pts, $color): void
{
    $ints = array_map(function ($v) { return (int)round((float)$v); }, $pts);
    if (PHP_VERSION_ID >= 80100) { imagepolygon($im, $ints, $color); }
    else { imagepolygon($im, $ints, (int)(count($ints) / 2), $color); }
}

/**
 * Draw the diagram into $im inside the box (x, y, w) — the height follows
 * the aspect ratio and is returned. Rendered at 3× and resampled down so
 * the edges are smooth in the PDF.
 */
function car_map_gd($im, int $x, int $y, int $w, array $states = []): int
{
    $h  = (int)round($w * CM_H / CM_W);
    $ss = 3;                                          // supersampling
    $bw = CM_W * $ss / 4;                             // work canvas — 600 × 573 at ss=3
    $sc = ($ss / 4);
    $cw = (int)round(CM_W * $sc);
    $chh = (int)round(CM_H * $sc);

    $c = imagecreatetruecolor($cw, $chh);
    imagealphablending($c, true);
    imagefilledrectangle($c, 0, 0, $cw, $chh, imagecolorallocate($c, 255, 255, 255));

    $rgb = function ($hex) use ($c) {
        $hex = ltrim($hex, '#');
        return imagecolorallocate($c, (int)hexdec(substr($hex, 0, 2)), (int)hexdec(substr($hex, 2, 2)), (int)hexdec(substr($hex, 4, 2)));
    };
    $line  = $rgb('9c8d94');
    $blank = $rgb('f3eef0');
    $glass = $rgb('e2ebf4');
    $grab  = $rgb('cfc3c8');
    $wheel = $rgb('e8e1e4');
    $cols  = [];
    foreach (cm_states() as $k => $v) $cols[$k] = $rgb($v['color']);

    $scale = function (array $pts) use ($sc) {
        $o = [];
        foreach ($pts as $i => $v) $o[] = $v * $sc;
        return $o;
    };

    imagesetthickness($c, max(1, (int)round(1.7 * $sc)));

    /* wheels first — they sit behind the body panels */
    foreach (car_decor() as $d) {
        if (($d['kind'] ?? '') !== 'wheel') continue;
        [$cx, $cy, $r] = $d['circle'];
        $dd = (int)round($r * 2 * $sc);
        imagefilledellipse($c, (int)round($cx * $sc), (int)round($cy * $sc), $dd, $dd, $wheel);
        imageellipse($c, (int)round($cx * $sc), (int)round($cy * $sc), $dd, $dd, $line);
    }
    foreach (car_parts() as $key => $p) {
        $pts = $scale($p['poly']);
        $st  = (string)($states[$key] ?? '');
        cm_poly($c, $pts, $st !== '' && isset($cols[$st]) ? $cols[$st] : $blank);
        cm_poly_line($c, $pts, $line);
    }
    foreach (car_decor() as $d) {
        if (($d['kind'] ?? '') === 'wheel') continue;
        $pts = $scale($d['poly']);
        cm_poly($c, $pts, ($d['kind'] === 'grab') ? $grab : $glass);
        cm_poly_line($c, $pts, $line);
    }
    imagesetthickness($c, 1);

    imagecopyresampled($im, $c, $x, $y, 0, 0, $w, $h, $cw, $chh);
    imagedestroy($c);
    return $h;
}

/* ================================================================== */
/*  THE CONDITION QUESTIONS                                            */
/* ================================================================== */

/** Question 3 — was the car ever repainted, and was there an accident? */
function cond_paint_options(): array
{
    return [
        'original' => [
            'ar' => 'أصلي بالكامل — بدون صبغ',
            'en' => 'Fully original — never repainted',
            'hint_ar' => 'كل قطع السيارة بالصبغ الأصلي من الوكالة.',
            'hint_en' => 'Every panel still carries its factory paint.',
        ],
        'repaint' => [
            'ar' => 'صبغ فقط — بدون أي حادث',
            'en' => 'Repainted only — no accident',
            'hint_ar' => 'صبغ جزئي أو كامل لتحسين الشكل، بدون اصطدام.',
            'hint_en' => 'Partly or fully resprayed for looks, no impact damage.',
        ],
        'accident' => [
            'ar' => 'صبغ بعد حادث أو إصلاح',
            'en' => 'Repainted after an accident / repair',
            'hint_ar' => 'حدد الأجزاء المتضررة على الرسم أعلاه.',
            'hint_en' => 'Mark the affected panels on the diagram above.',
        ],
    ];
}

/** part or full — only meaningful for the two “repainted” answers */
function cond_extent_options(): array
{
    return [
        'part' => ['ar' => 'جزئي — بعض القطع', 'en' => 'Partial — some panels'],
        'full' => ['ar' => 'كامل — السيارة كلها', 'en' => 'Full — the whole car'],
    ];
}

/** Questions 4, 5, 6 — interior, engine, gearbox */
function cond_scales(): array
{
    $hi = ['ar' => '90% فأكثر', 'en' => '90% & above'];
    return [
        'interior' => [
            'ar' => 'حالة المقصورة الداخلية', 'en' => 'Interior quality',
            'sub_ar' => 'المقاعد والمقود والتابلوه', 'sub_en' => 'Seats, steering wheel, dashboard',
            'icon' => 'seat',
            'opts' => [
                '90' => $hi,
                '80' => ['ar' => '80%', 'en' => '80%'],
                '70' => ['ar' => '70%', 'en' => '70%'],
                '50' => ['ar' => '50% فأقل', 'en' => '50% & below'],
            ],
        ],
        'engine' => [
            'ar' => 'حالة المحرك', 'en' => 'Engine condition',
            'sub_ar' => 'الأداء والصوت والتسريبات', 'sub_en' => 'Performance, noise, leaks',
            'icon' => 'engine',
            'opts' => [
                '90' => $hi,
                '75' => ['ar' => '75%', 'en' => '75%'],
                '50' => ['ar' => '50%', 'en' => '50%'],
                '25' => ['ar' => '25%', 'en' => '25%'],
            ],
        ],
        'gearbox' => [
            'ar' => 'حالة القير', 'en' => 'Gearbox condition',
            'sub_ar' => 'نعومة التنقل بين الغيارات', 'sub_en' => 'Smoothness of the shifts',
            'icon' => 'gearbox',
            'opts' => [
                '90' => $hi,
                '75' => ['ar' => '75%', 'en' => '75%'],
                '50' => ['ar' => '50%', 'en' => '50%'],
                '25' => ['ar' => '25%', 'en' => '25%'],
            ],
        ],
    ];
}

/** small inline icons for the three quality questions */
function cond_icon(string $name): string
{
    /* width/height are set here as well as in the CSS: without them a missing
       stylesheet renders these at the full width of the card */
    $a = 'width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.7"'
       . ' stroke-linecap="round" stroke-linejoin="round"';
    switch ($name) {
        case 'seat':   // a seat with a steering wheel beside it
            return '<svg viewBox="0 0 24 24" class="cmi" ' . $a . '>'
                 . '<path d="M6 3.5h3.2a2 2 0 0 1 2 1.75l.8 6.25H7.4a2 2 0 0 1-2-1.78L4.9 5.7A2 2 0 0 1 6 3.5Z"/>'
                 . '<path d="M6.6 13.5h6.2M7 13.5v4.2a2 2 0 0 0 2 2h3.4"/>'
                 . '<circle cx="17.5" cy="9.5" r="4.2"/><circle cx="17.5" cy="9.5" r="1.1"/>'
                 . '<path d="M13.4 9.5h2.9m2.4 0h2.9M17.5 10.6v3.1"/></svg>';
        case 'engine':
            return '<svg viewBox="0 0 24 24" class="cmi" ' . $a . '>'
                 . '<path d="M4 10.5h2.2l2-2h4.3l1.8 1.8h2.4v-1.6h2.6v6.1h-2.6v-1.7h-1.9l-2 2H8.2l-1.9-1.9H4Z"/>'
                 . '<path d="M8.6 8.5V6.2h4.2M2.6 11v2.6"/></svg>';
        case 'gearbox':
            return '<svg viewBox="0 0 24 24" class="cmi" ' . $a . '>'
                 . '<path d="M6 5.2v5.6M12 5.2v5.6M18 5.2v5.6M6 10.8h12"/>'
                 . '<path d="M12 10.8v5.4"/><circle cx="12" cy="18.2" r="2"/>'
                 . '<circle cx="6" cy="4.2" r="1.4"/><circle cx="12" cy="4.2" r="1.4"/><circle cx="18" cy="4.2" r="1.4"/></svg>';
    }
    return '';
}

/* ------------------------------------------------------------------ */
/*  reading what the customer sent                                     */
/* ------------------------------------------------------------------ */

/**
 * Clean and validate the condition part of a submitted form.
 * Anything unrecognised is dropped, so the stored record is always sane.
 */
function cond_from_post(array $post): array
{
    $status = (string)($post['paint_status'] ?? '');
    if (!isset(cond_paint_options()[$status])) $status = '';

    $extent = (string)($post['paint_extent'] ?? '');
    if (!isset(cond_extent_options()[$extent]) || $status === 'original') $extent = '';

    /* “repainted only, no accident” cannot survive a panel marked as an
       accident — the diagram is the more specific statement, so it wins here
       exactly as it does in the form. */
    $rawPanels = json_decode((string)($post['panels'] ?? ''), true);
    if ($status === 'repaint' && is_array($rawPanels) && in_array('accident', $rawPanels, true)) {
        $status = 'accident';
    }

    /* panels: {"hood":"painted","door_fl":"accident"} */
    $panels = [];
    $raw = json_decode((string)($post['panels'] ?? ''), true);
    if (is_array($raw)) {
        $valid = car_parts();
        $states = cm_states();
        foreach ($raw as $k => $v) {
            $k = (string)$k; $v = (string)$v;
            if (isset($valid[$k]) && isset($states[$v])) $panels[$k] = $v;
        }
    }
    if ($status === 'original') $panels = [];      // nothing can be marked on an untouched car

    $q = [];
    foreach (cond_scales() as $key => $def) {
        $v = (string)($post['q_' . $key] ?? '');
        $q[$key] = isset($def['opts'][$v]) ? $v : '';
    }

    return [
        'paint_status' => $status,
        'paint_extent' => $extent,
        'panels'       => $panels,
        'quality'      => $q,
    ];
}

/** the condition block of a stored request, with safe defaults for old rows */
function cond_of(array $r): array
{
    $c = (array)($r['condition'] ?? []);
    return [
        'paint_status' => (string)($c['paint_status'] ?? ''),
        'paint_extent' => (string)($c['paint_extent'] ?? ''),
        'panels'       => (array)($c['panels'] ?? []),
        'quality'      => (array)($c['quality'] ?? []),
    ];
}

function cond_has_any(array $r): bool
{
    $c = cond_of($r);
    return $c['paint_status'] !== '' || $c['panels'] || array_filter($c['quality']);
}

/** e.g. “صبغ فقط — بدون أي حادث · جزئي” */
function cond_paint_text(array $c, string $lang = 'ar'): string
{
    $o = cond_paint_options();
    if ($c['paint_status'] === '' || !isset($o[$c['paint_status']])) return '';
    $t = (string)$o[$c['paint_status']][$lang === 'en' ? 'en' : 'ar'];
    if ($c['paint_extent'] !== '') {
        $e = cond_extent_options();
        $t .= ' · ' . (string)$e[$c['paint_extent']][$lang === 'en' ? 'en' : 'ar'];
    }
    return $t;
}

/** “90% فأكثر” for one of interior / engine / gearbox */
function cond_quality_text(array $c, string $key, string $lang = 'ar'): string
{
    $s = cond_scales();
    $v = (string)($c['quality'][$key] ?? '');
    if ($v === '' || !isset($s[$key]['opts'][$v])) return '';
    return (string)$s[$key]['opts'][$v][$lang === 'en' ? 'en' : 'ar'];
}

/** the marked panels grouped by state, in diagram order */
function cond_panels_by_state(array $c): array
{
    $out = [];
    foreach (cm_states() as $state => $_) $out[$state] = [];
    foreach (car_part_keys() as $k) {
        $st = (string)($c['panels'][$k] ?? '');
        if ($st !== '' && isset($out[$st])) $out[$st][] = $k;
    }
    return array_filter($out);
}

/** everything the customer answered, as flat “label → value” rows */
function cond_summary_rows(array $r): array
{
    $c    = cond_of($r);
    $rows = [];

    $paintAr = cond_paint_text($c, 'ar');
    if ($paintAr !== '') {
        $rows[] = ['ar' => 'حالة الصبغ', 'en' => 'Paint status',
                   'v_ar' => $paintAr, 'v_en' => cond_paint_text($c, 'en')];
    }

    foreach (cond_panels_by_state($c) as $state => $keys) {
        $rows[] = [
            'ar' => 'الأجزاء — ' . cm_state_label($state, 'ar'),
            'en' => 'Panels — ' . cm_state_label($state, 'en'),
            'v_ar' => implode('، ', array_map(function ($k) { return car_part_label($k, 'ar'); }, $keys)),
            'v_en' => implode(', ', array_map(function ($k) { return car_part_label($k, 'en'); }, $keys)),
        ];
    }

    foreach (cond_scales() as $key => $def) {
        $v = cond_quality_text($c, $key, 'ar');
        if ($v === '') continue;
        $rows[] = ['ar' => $def['ar'], 'en' => $def['en'],
                   'v_ar' => $v, 'v_en' => cond_quality_text($c, $key, 'en')];
    }

    return $rows;
}

/**
 * Everything assets/app.js needs for step 2 — the panel names, the two
 * marking states, and every option label in both languages so switching
 * language never needs a page reload.
 */
function cond_js_config(): array
{
    $parts = [];
    foreach (car_parts() as $k => $p) $parts[$k] = ['ar' => $p['ar'], 'en' => $p['en']];

    $i18n = ['ar' => [], 'en' => []];
    $put = function (string $key, string $ar, string $en) use (&$i18n) {
        $i18n['ar'][$key] = $ar;
        $i18n['en'][$key] = $en;
    };

    foreach (cm_states() as $k => $st) $put('cm_' . $k, $st['ar'], $st['en']);
    foreach (cond_paint_options() as $k => $o) {
        $put('cp_' . $k,  $o['ar'], $o['en']);
        $put('cph_' . $k, $o['hint_ar'], $o['hint_en']);
    }
    foreach (cond_extent_options() as $k => $o) $put('ce_' . $k, $o['ar'], $o['en']);
    foreach (cond_scales() as $key => $def) {
        $put('q_' . $key,  $def['ar'], $def['en']);
        $put('qs_' . $key, $def['sub_ar'], $def['sub_en']);
        foreach ($def['opts'] as $v => $o) $put('qo_' . $key . '_' . $v, $o['ar'], $o['en']);
    }

    return [
        'parts'  => $parts,
        'states' => array_map(function ($s) { return ['ar' => $s['ar'], 'en' => $s['en']]; }, cm_states()),
        'order'  => array_keys(cm_states()),
        'i18n'   => $i18n,
    ];
}
