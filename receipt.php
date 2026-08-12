<?php
/* ============================================================
   receipt.php — the 2-page PDF the customer receives with the price.
     page 1 : thank-you, price, request code, every detail he entered
     page 2 : his photos, laid out in order (no videos)

   Direct use:  receipt.php?id=XXXXXX&k=TOKEN   (or with an admin session)
   ============================================================ */
declare(strict_types=1);
require_once __DIR__ . '/lib.php';
require_once __DIR__ . '/pdfdoc.php';

const RC_M     = 90;                      // page margin
const RC_NAVY  = '8a1538';        // Qatar maroon
const RC_NAVY2 = 'a81c46';
const RC_GOLD  = 'c9a227';
const RC_INK   = '1b1418';
const RC_MUTE  = '7c6a71';
const RC_LINE  = 'ece2e6';

/* ---------------- shared bits ---------------- */

/**
 * The TMK mark, cut out of its background, laid onto the maroon band.
 * Returns the width it used, so the name can sit beside it. Falls back to the
 * old gold “ث” tile if the file is not on the server yet — a report must never
 * fail to build over a missing decoration.
 */
function rc_logo($im, int $x, int $cy, int $height): int
{
    $file = __DIR__ . '/assets/brand/logo-mark.png';
    if (is_file($file)) {
        $src = @imagecreatefrompng($file);
        if ($src) {
            imagealphablending($src, true);
            imagesavealpha($src, true);
            $sw = imagesx($src); $sh = imagesy($src);
            $w  = (int)round($height * $sw / $sh);
            imagealphablending($im, true);
            imagecopyresampled($im, $src, $x, (int)($cy - $height / 2), 0, 0, $w, $height, $sw, $sh);
            imagedestroy($src);
            return $w;
        }
    }
    pd_round($im, $x, (int)($cy - $height / 2), $height, $height, (int)($height / 4), RC_GOLD);
    pd_text($im, $x + (int)($height / 2), $cy + (int)($height / 4), $height / 2, RC_NAVY, 'ث', 'center', true);
    return $height;
}

function rc_header($im, string $title, string $sub, int $h = 250): void {
    // navy band with a soft gradient
    for ($y = 0; $y < $h; $y++) {
        $t = $y / max(1, $h - 1);
        $c = imagecolorallocate($im,
            (int)(0xa8 + (0x8a - 0xa8) * $t), (int)(0x1c + (0x15 - 0x1c) * $t), (int)(0x46 + (0x38 - 0x46) * $t));
        imageline($im, 0, $y, PD_W, $y, $c);
    }

    /* the mark, then the name beside whatever width it turned out to be */
    $mh   = $h >= 240 ? 96 : 74;
    $cy   = (int)round($h * 0.52);
    $used = rc_logo($im, RC_M, $cy, $mh);
    $tx   = RC_M + $used + 26;

    /* the name comes from the control panel, so a rename reaches the PDF too */
    pd_text($im, $tx, $cy - 6,  $h >= 240 ? 36 : 30, 'ffffff', ct('appName', 'ar'), 'left', true);
    pd_text($im, $tx, $cy + 40, $h >= 240 ? 28 : 24, 'ffffff', ct('appName', 'en'), 'left', true);
    pd_text($im, PD_W - RC_M, $cy - 18, 19, 'f0cdd8', $title, 'right', true);
    pd_text($im, PD_W - RC_M, $cy + 16, 17, 'e0b0c0', $sub, 'right', false);
}

function rc_footer($im, array $r): void {
    $y = PD_H - 96;
    pd_hr($im, RC_M, $y - 34, PD_W - 2 * RC_M, RC_LINE);
    pd_text($im, RC_M, $y, 16, RC_MUTE, ct('appName', 'ar') . ' · ' . ct('appName', 'en'), 'left', true);
    /* Whatever the control panel holds — and nothing else. Clear the phone
       field and no number is printed here; the email and Instagram stay.
       Only as many details as fit: the request number sits on the same line. */
    $max  = PD_W - 2 * RC_M - 340;
    $line = '';
    foreach (public_contact() as $c) {
        if ($c['k'] === 'Website') continue;                       // already above
        $bit = ($c['k'] === 'Email' ? '' : $c['k'] . ' ') . $c['v'];
        $try = $line === '' ? $bit : $line . '   ·   ' . $bit;
        if (pd_width($try, 15) > $max) break;
        $line = $try;
    }
    if ($line !== '') pd_text($im, RC_M, $y + 30, 15, RC_MUTE, $line, 'left');
    pd_text($im, PD_W - RC_M, $y, 15, RC_MUTE, 'Request ' . (string)$r['id'], 'right');
    pd_text($im, PD_W - RC_M, $y + 30, 15, RC_MUTE, fmt_dt($r['done_at'] ?? $r['created'], 'd M Y · H:i'), 'right');
}

/** one detail row: English label, Arabic label under it, value on the right */
function rc_row($im, float $y, string $en, string $ar, string $value, bool $strong = false): float {
    $v  = trim($value) === '' ? '—' : $value;
    $vw = PD_W - 2 * RC_M - 330;
    $sz = $strong ? 21 : 19;
    $h  = max(50.0, pd_para_h($vw, $sz, $v, $strong) + 22);
    pd_text($im, RC_M, $y, 16, RC_MUTE, $en, 'left', false);
    pd_text($im, RC_M, $y + 24, 15, 'a3adb7', $ar, 'left', false);
    pd_para($im, PD_W - RC_M, $y + 2, $vw, $sz, RC_INK, $v, 'right', $strong);
    pd_hr($im, RC_M, (int)($y + $h - 12), PD_W - 2 * RC_M, RC_LINE);
    return $y + $h;
}

/* ---------------- page 3 : the condition report ---------------- */

/** a filled quality meter — “Engine 75%” */
function rc_meter($im, float $y, string $en, string $ar, string $sub_en, string $label, int $pct): float
{
    $x  = RC_M;
    $w  = PD_W - 2 * RC_M;
    $barY = (int)round($y + 46);
    $barH = 30;
    $fill = $pct >= 80 ? '1a8f52' : ($pct >= 50 ? 'c9a227' : 'd1435b');

    pd_text($im, $x, $y + 14, 22, RC_INK, $en, 'left', true);
    pd_text($im, $x, $y + 38, 17, 'a3adb7', $sub_en, 'left', false);
    pd_text($im, PD_W - RC_M, $y + 14, 22, RC_INK, $ar, 'right', true);
    pd_text($im, PD_W - RC_M, $y + 40, 19, $fill, $label, 'right', true);

    pd_round($im, $x, $barY + 18, $w, $barH, 15, 'efe8eb');
    $fw = (int)round($w * max(4, min(100, $pct)) / 100);
    pd_round($im, $x, $barY + 18, max(30, $fw), $barH, 15, $fill);

    /* the four scale marks, so 75 % reads as “three of four” at a glance */
    for ($i = 1; $i < 4; $i++) {
        $mx = (int)round($x + $w * $i / 4);
        pd_fill($im, $mx, $barY + 18, 2, $barH, 'ffffff');
    }
    return $barY + $barH + 46;
}

/** returns raw JPEG bytes for page 3, or null when there is nothing to show */
function build_condition_page(array $r): ?string
{
    if (!cond_has_any($r)) return null;
    $c = cond_of($r);

    $p = pd_new();
    rc_header($p, (string)$r['id'], car_title($r), 190);

    $y = 262;
    pd_text($p, RC_M, $y, 26, RC_INK, 'Condition report', 'left', true);
    pd_text($p, PD_W - RC_M, $y, 26, RC_INK, 'تقرير حالة السيارة', 'right', true);
    $y += 34;
    pd_hr($p, RC_M, (int)$y, PD_W - 2 * RC_M, RC_NAVY, 2);
    $y += 30;

    /* ---- the diagram, left ---- */
    $mapW = 520;
    $mapH = car_map_gd($p, RC_M, (int)$y, $mapW, $c['panels']);

    /* ---- the answers, right ---- */
    $cx = RC_M + $mapW + 44;
    $cw = PD_W - RC_M - $cx;
    $ry = $y + 6;

    $paintAr = cond_paint_text($c, 'ar');
    if ($paintAr !== '') {
        $enTxt = cond_paint_text($c, 'en');
        $boxH  = (int)round(pd_para_h($cw - 56, 21, $paintAr, true) + pd_para_h($cw - 56, 18, $enTxt) + 76);
        pd_round($p, (int)$cx, (int)$ry, $cw, $boxH, 16, 'fdf2f5');
        pd_fill($p, (int)$cx, (int)$ry, 8, $boxH, RC_NAVY);
        pd_text($p, $cx + 28, $ry + 34, 15, RC_NAVY, 'PAINT STATUS  ·  حالة الصبغ', 'left', true);
        $ty = pd_para($p, $cx + $cw - 28, $ry + 68, $cw - 56, 21, '5c0f26', $paintAr, 'right', true);
        pd_para($p, $cx + 28, $ty + 6, $cw - 56, 18, '7a5560', $enTxt, 'left');
        $ry += $boxH + 26;
    }

    /* legend + the panels that were marked */
    $byState = cond_panels_by_state($c);
    pd_text($p, $cx, $ry + 18, 20, RC_INK, 'Marked panels', 'left', true);
    pd_text($p, $cx + $cw, $ry + 18, 20, RC_INK, 'الأجزاء المحددة', 'right', true);
    $ry += 42;
    pd_hr($p, (int)$cx, (int)$ry, $cw, RC_LINE);
    $ry += 24;

    if (!$byState) {
        pd_text($p, $cx, $ry + 18, 18, RC_MUTE, 'No panel was marked.', 'left');
        pd_text($p, $cx + $cw, $ry + 44, 18, RC_MUTE, 'لم يتم تحديد أي جزء.', 'right');
    } else {
        foreach (cm_states() as $state => $meta) {
            if (empty($byState[$state])) continue;
            $keys = $byState[$state];
            pd_round($p, (int)$cx, (int)$ry, 26, 26, 8, ltrim($meta['color'], '#'));
            pd_text($p, $cx + 38, $ry + 20, 18, RC_INK, $meta['en'] . '  ·  ' . count($keys), 'left', true);
            pd_text($p, $cx + $cw, $ry + 20, 18, RC_INK, $meta['ar'], 'right', true);
            $ry += 36;
            foreach ($keys as $k) {
                pd_text($p, $cx + 38, $ry + 18, 17, '5d4a51', '• ' . car_part_label($k, 'en'), 'left');
                pd_text($p, $cx + $cw, $ry + 18, 17, RC_MUTE, car_part_label($k, 'ar'), 'right');
                $ry += 28;
            }
            $ry += 14;
        }
    }

    /* ---- the three quality meters, full width under the diagram ---- */
    $y = max($y + $mapH, $ry) + 46;
    $scales = cond_scales();
    $any    = false;
    foreach ($scales as $key => $def) if (($c['quality'][$key] ?? '') !== '') $any = true;

    if ($any && $y + 120 < PD_H - 190) {
        pd_text($p, RC_M, $y, 24, RC_INK, 'Mechanical & interior', 'left', true);
        pd_text($p, PD_W - RC_M, $y, 24, RC_INK, 'الميكانيكا والمقصورة', 'right', true);
        $y += 34;
        pd_hr($p, RC_M, (int)$y, PD_W - 2 * RC_M, RC_NAVY, 2);
        $y += 34;

        foreach ($scales as $key => $def) {
            $v = (string)($c['quality'][$key] ?? '');
            if ($v === '') continue;
            if ($y + 130 > PD_H - 170) break;
            $y = rc_meter($p, $y, $def['en'], $def['ar'], $def['sub_en'],
                          cond_quality_text($c, $key, 'en'), (int)$v);
        }
    } elseif ($y + 80 < PD_H - 190) {
        /* say so plainly rather than leaving a silent gap */
        pd_round($p, RC_M, (int)$y, PD_W - 2 * RC_M, 84, 16, 'f8f4f6');
        pd_text($p, RC_M + 28, $y + 34, 17, RC_MUTE,
                'The customer did not rate the interior, engine or gearbox.', 'left');
        pd_text($p, PD_W - RC_M - 28, $y + 62, 17, RC_MUTE,
                'لم يقيّم العميل المقصورة أو المحرك أو القير.', 'right');
        $y += 110;
    }

    /* the customer's own words carry more weight than any tick box */
    if (trim((string)($r['notes'] ?? '')) !== '' && $y + 110 < PD_H - 160) {
        pd_round($p, RC_M, (int)$y, PD_W - 2 * RC_M, 96, 16, 'faf6ee');
        pd_text($p, RC_M + 28, $y + 34, 16, '7a6216', 'CUSTOMER NOTES  ·  ملاحظات العميل', 'left', true);
        pd_para($p, PD_W - RC_M - 28, $y + 68, PD_W - 2 * RC_M - 56, 18, '6b5a2a',
                (string)$r['notes'], has_arabic((string)$r['notes']) ? 'right' : 'left');
    }

    rc_footer($p, $r);
    $jpg = pd_jpeg($p, 88);
    imagedestroy($p);
    return $jpg;
}

/* ---------------- the document ---------------- */

function build_receipt_pdf(array $r): ?string {
    if (!pd_available()) return null;
    @ini_set('memory_limit', '512M');          // phone photos are big; one is loaded at a time
    @set_time_limit(120);
    $C    = cfg();
    $dir  = UPLOAD_DIR . '/' . $r['id'];
    $done = ($r['status'] ?? '') === 'done';

    /* ============ PAGE 1 ============ */
    $p1 = pd_new();
    rc_header($p1, $done ? 'تقييم موترك جاهز' : 'استلمنا طلبك',
                    $done ? 'Your valuation is ready' : 'We have received your request');

    $y = 330;

    pd_text($p1, RC_M, $y, 34, RC_INK, 'شكراً لاستخدامك خدمتنا', 'left', true);
    $y += 46;
    pd_text($p1, RC_M, $y, 27, '5d4a51', 'Thank you for using our service', 'left', false);
    $y += 50;

    /* price + code side by side */
    $boxW = (PD_W - 2 * RC_M - 28);
    $priceW = (int)round($boxW * 0.62);
    $codeW  = $boxW - $priceW - 28;

    pd_round($p1, RC_M, (int)$y, $priceW, 190, 22, RC_NAVY);
    $rng = is_price_range($r);
    pd_text($p1, RC_M + 36, $y + 52, 19, 'edc2ce', $rng ? 'النطاق السعري التقديري لموترك' : 'السعر التقديري لموترك', 'left');
    pd_text($p1, RC_M + 36, $y + 82, 17, 'dfaebc', $rng ? 'Estimated price range for your car' : 'Estimated price for your car', 'left');
    if ($done && has_price($r)) {
        /* a range needs more room than one number, so it is set smaller */
        $money = price_display($r);
        $size  = is_price_range($r) ? 38.0 : 52.0;
        pd_text($p1, RC_M + 36, $y + 150, $size, 'ffffff', $money, 'left', true);
        $pw = pd_width($money, $size, true);
        pd_text($p1, RC_M + 52 + $pw, $y + 150, 26, RC_GOLD, (string)$C['currency_en'], 'left', true);
    } else {
        pd_text($p1, RC_M + 36, $y + 148, 34, 'ffffff', 'تحت المراجعة / under review', 'left', true);
    }

    pd_round($p1, RC_M + $priceW + 28, (int)$y, $codeW, 190, 22, 'fdf2f5');
    $cx = RC_M + $priceW + 28 + (int)($codeW / 2);
    pd_text($p1, $cx, $y + 52, 17, RC_MUTE, 'رمز الطلب', 'center');
    pd_text($p1, $cx, $y + 78, 16, RC_MUTE, 'Request code', 'center');
    pd_text($p1, $cx, $y + 146, 46, RC_NAVY, (string)$r['id'], 'center', true);

    $y += 190 + 58;

    /* Khalid's note */
    $note = trim((string)($r['note_ar'] ?? '')) . (trim((string)($r['note_en'] ?? '')) !== '' ? "\n" . $r['note_en'] : '');
    if (trim($note) !== '') {
        $lines = array_values(array_filter(array_map('trim', explode("\n", $note)), 'strlen'));
        $inner = PD_W - 2 * RC_M - 76;
        $need  = 0.0;
        foreach ($lines as $ln) $need += pd_para_h($inner, 21, $ln, true);
        $boxH = (int)round($need + 78);
        pd_round($p1, RC_M, (int)$y, PD_W - 2 * RC_M, $boxH, 16, 'fdf2f5');
        pd_fill($p1, RC_M, (int)$y, 9, $boxH, RC_NAVY);              // maroon bar
        pd_text($p1, RC_M + 30, $y + 34, 16, RC_NAVY, 'NOTE FROM OUR TEAM  ·  ملاحظة من الفريق', 'left', true);
        $ty = $y + 68;
        foreach ($lines as $ln) {
            $ty = pd_para($p1, has_arabic($ln) ? PD_W - RC_M - 30 : RC_M + 30, $ty,
                          $inner, 21, '5c0f26', $ln, has_arabic($ln) ? 'right' : 'left', true);
        }
        $y += $boxH + 26;
    }

    /* details */
    pd_text($p1, RC_M, $y, 24, RC_INK, 'Your details', 'left', true);
    pd_text($p1, PD_W - RC_M, $y, 24, RC_INK, 'بياناتك', 'right', true);
    $y += 40;
    pd_hr($p1, RC_M, (int)$y, PD_W - 2 * RC_M, RC_NAVY, 2);
    $y += 26;

    $y = rc_row($p1, $y, 'Name',            'الاسم',            (string)($r['name'] ?? ''), true);
    $y = rc_row($p1, $y, 'Mobile',          'رقم الجوال',        (string)($r['phone'] ?? ''));
    $y = rc_row($p1, $y, 'Email',           'البريد الإلكتروني',  (string)($r['email'] ?? ''));
    $y = rc_row($p1, $y, 'Make & class',    'الشركة والفئة',
                trim((string)($r['car_make'] ?? '') . '  ' . (string)($r['car_class'] ?? '')), true);
    $y = rc_row($p1, $y, 'Model / trim',    'الموديل',           (string)($r['car_model'] ?? ''));
    $y = rc_row($p1, $y, 'Year',            'سنة الصنع',         (string)($r['car_year'] ?? ''));
    $y = rc_row($p1, $y, 'Mileage',         'الممشى',            (string)($r['mileage'] ?? ''));
    $y = rc_row($p1, $y, 'Registration',    'رقم الاستمارة',     (string)($r['registration'] ?? ''));
    $y = rc_row($p1, $y, 'Chassis / VIN',   'رقم الشاصي',        (string)($r['chassis'] ?? ''));
    $y = rc_row($p1, $y, 'Notes',           'ملاحظات',           (string)($r['notes'] ?? ''));
    $y = rc_row($p1, $y, 'Submitted',       'تاريخ الإرسال',     fmt_dt($r['created'] ?? null));

    /* disclaimer — only if it still fits above the footer */
    $discH = 92;
    if ($y + 12 + $discH < PD_H - 120) {
        $y += 12;
        pd_round($p1, RC_M, (int)$y, PD_W - 2 * RC_M, $discH, 16, 'faf6ee');
        pd_para($p1, PD_W - RC_M - 28, $y + 38, PD_W - 2 * RC_M - 56, 17, '7a6216',
                'هذا سعر تقديري بناءً على الصور والبيانات المرسلة، وقد يختلف بعد المعاينة على الطبيعة.', 'right');
        pd_para($p1, RC_M + 28, $y + 72, PD_W - 2 * RC_M - 56, 16, '8a7326',
                'This is an estimate based on the photos and details you sent; it may change after a physical inspection.', 'left');
    }

    rc_footer($p1, $r);
    $jpegs = [pd_jpeg($p1, 88)];
    imagedestroy($p1);

    /* ============ PAGE 2 : the photos ============ */
    $photos = [];
    foreach (slots() as $s) {
        foreach ((array)($r['photos'] ?? []) as $p) {
            if ((string)($p['slot'] ?? '') === $s['key']) { $photos[] = [$s, $p['file']]; break; }
        }
    }

    if ($photos && empty($r['files_purged'])) {
        $p2 = pd_new();
        rc_header($p2, (string)$r['id'], car_title($r), 190);

        $y = 262;
        pd_text($p2, RC_M, $y, 26, RC_INK, 'Photos you sent', 'left', true);
        pd_text($p2, PD_W - RC_M, $y, 26, RC_INK, 'الصور المرسلة', 'right', true);
        $y += 34;
        pd_hr($p2, RC_M, (int)$y, PD_W - 2 * RC_M, RC_NAVY, 2);
        $y += 34;

        $cols = 3;
        $gap  = 26;
        $cw   = (int)((PD_W - 2 * RC_M - $gap * ($cols - 1)) / $cols);
        $ch   = (int)round($cw * 0.75);
        $i    = 0;
        foreach ($photos as [$slot, $file]) {
            $col = $i % $cols;
            $row = intdiv($i, $cols);
            $x   = RC_M + $col * ($cw + $gap);
            $yy  = (int)$y + $row * ($ch + 92);
            if ($yy + $ch + 92 > PD_H - 150) break;          // never spill past the footer

            $src = pd_load($dir . '/' . $file);
            pd_fill($p2, $x - 2, $yy - 2, $cw + 4, $ch + 4, 'e6d9de');
            pd_photo($p2, $src, $x, $yy, $cw, $ch);
            if ($src) imagedestroy($src);

            pd_text($p2, $x, $yy + $ch + 34, 18, RC_INK, $slot['en'], 'left', true);
            pd_text($p2, $x + $cw, $yy + $ch + 62, 17, RC_MUTE, $slot['ar'], 'right');
            $i++;
        }

        $vids = count((array)($r['videos'] ?? []));
        if ($vids) {
            pd_text($p2, RC_M, PD_H - 178, 17, RC_MUTE,
                    $vids . ' video clip(s) were also sent — they are not included in this PDF.', 'left');
        }

        rc_footer($p2, $r);
        $jpegs[] = pd_jpeg($p2, 84);
        imagedestroy($p2);
    }

    /* ============ PAGE 3 : the condition report ============ */
    $p3 = build_condition_page($r);
    if ($p3 !== null) $jpegs[] = $p3;

    return pdf_build($jpegs);
}

function receipt_filename(array $r): string {
    $car = preg_replace('/[^A-Za-z0-9]+/', '-', car_title($r));
    $car = trim((string)$car, '-');
    return 'Thaman-Sayaratak-' . $r['id'] . ($car !== '' ? '-' . $car : '') . '.pdf';
}

/* ---------------- direct download ---------------- */
if (basename((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === 'receipt.php') {
    ensure_dirs();
    session_start();

    $id = strtoupper(preg_replace('/[^A-Z0-9]/i', '', (string)($_GET['id'] ?? '')));
    $k  = (string)($_GET['k'] ?? '');
    $ok = !empty($_SESSION['eyc_admin']) || ($id !== '' && link_ok($id, $k));
    if (!$ok) { http_response_code(403); exit('Forbidden'); }

    $r = find_request($id);
    if (!$r) { http_response_code(404); exit('Not found'); }

    $pdf = build_receipt_pdf($r);
    if ($pdf === null) { http_response_code(500); exit('PDF support is not available on this server (GD with FreeType is required).'); }

    header('Content-Type: application/pdf');
    header('Content-Disposition: ' . (isset($_GET['dl']) ? 'attachment' : 'inline')
         . '; filename="' . receipt_filename($r) . '"');
    header('Content-Length: ' . strlen($pdf));
    echo $pdf;
    exit;
}
