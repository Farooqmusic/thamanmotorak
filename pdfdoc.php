<?php
/* ============================================================
   pdfdoc.php — draw pages with GD, then wrap them into a PDF.

   No libraries, no Composer. Each page is laid out as an image
   (so Arabic renders exactly as we shaped it) and then embedded
   in the PDF straight as JPEG data.
   ============================================================ */
declare(strict_types=1);
require_once __DIR__ . '/arabic.php';

define('PD_DPI',  150);
define('PD_W',    1240);          // A4 at 150 dpi
define('PD_H',    1754);
define('PD_FONTS', __DIR__ . '/fonts');

function pd_available(): bool {
    return function_exists('imagettftext') && function_exists('imagecreatetruecolor')
        && is_file(PD_FONTS . '/Poppins-Regular.ttf');
}

function pd_font(bool $bold, bool $arabic): string {
    if ($arabic) return PD_FONTS . ($bold ? '/NotoNaskhArabic-Bold.ttf' : '/NotoNaskhArabic-Regular.ttf');
    return PD_FONTS . ($bold ? '/Poppins-Bold.ttf' : '/Poppins-Regular.ttf');
}

/* ---------------- canvas ---------------- */

function pd_new(int $w = PD_W, int $h = PD_H) {
    $im = imagecreatetruecolor($w, $h);
    imagealphablending($im, true);
    imagefilledrectangle($im, 0, 0, $w, $h, imagecolorallocate($im, 255, 255, 255));
    return $im;
}

function pd_rgb($im, string $hex) {
    $hex = ltrim($hex, '#');
    return imagecolorallocate($im,
        (int)hexdec(substr($hex, 0, 2)), (int)hexdec(substr($hex, 2, 2)), (int)hexdec(substr($hex, 4, 2)));
}

function pd_fill($im, int $x, int $y, int $w, int $h, string $hex): void {
    imagefilledrectangle($im, $x, $y, $x + $w - 1, $y + $h - 1, pd_rgb($im, $hex));
}

function pd_round($im, int $x, int $y, int $w, int $h, int $r, string $hex): void {
    $c = pd_rgb($im, $hex);
    imagefilledrectangle($im, $x + $r, $y,        $x + $w - $r - 1, $y + $h - 1, $c);
    imagefilledrectangle($im, $x,      $y + $r,   $x + $w - 1,      $y + $h - $r - 1, $c);
    $d = $r * 2;
    imagefilledellipse($im, $x + $r,          $y + $r,          $d, $d, $c);
    imagefilledellipse($im, $x + $w - $r - 1, $y + $r,          $d, $d, $c);
    imagefilledellipse($im, $x + $r,          $y + $h - $r - 1, $d, $d, $c);
    imagefilledellipse($im, $x + $w - $r - 1, $y + $h - $r - 1, $d, $d, $c);
}

function pd_hr($im, int $x, int $y, int $w, string $hex = 'e7ecf1', int $thick = 1): void {
    pd_fill($im, $x, $y, $w, $thick, $hex);
}

/* ---------------- text ---------------- */

/**
 * Split an already-visual string into runs of Arabic / non-Arabic.
 *
 * A space is deliberately kept inside whichever run it follows. On its own it
 * would become a one-character run, and imagettfbbox() reports zero width for a
 * glyph with no ink — which is why two Arabic words could end up touching.
 */
function pd_runs(string $vis): array {
    $runs = [];
    foreach (ar_cps($vis) as $c) {
        $ch    = ar_chr($c);
        $space = ($ch === ' ' || $ch === "\t" || $ch === "\xC2\xA0");
        if ($space && $runs) { $runs[count($runs) - 1]['s'] .= $ch; continue; }
        $isAr = ar_is_rtl($c) || ar_is_mark($c);
        if ($runs && $runs[count($runs) - 1]['ar'] === $isAr) $runs[count($runs) - 1]['s'] .= $ch;
        else $runs[] = ['ar' => $isAr, 's' => $ch];
    }
    return $runs;
}

function pd_width(string $text, float $size, bool $bold = false): float {
    $vis = ar_shape($text);
    $w = 0.0;
    foreach (pd_runs($vis) as $r) {
        $b = imagettfbbox($size, 0, pd_font($bold, $r['ar']), $r['s']);
        if ($b) $w += ($b[2] - $b[0]);
    }
    return $w;
}

/**
 * Draw text. $align = 'left' | 'right' | 'center'.  $y is the BASELINE.
 * Returns the width that was drawn.
 */
function pd_text($im, float $x, float $y, float $size, string $hex, string $text,
                 string $align = 'left', bool $bold = false): float {
    if (trim($text) === '') return 0.0;
    $vis   = ar_shape($text);
    $runs  = pd_runs($vis);
    $total = 0.0;
    $ws    = [];
    foreach ($runs as $i => $r) {
        $b = imagettfbbox($size, 0, pd_font($bold, $r['ar']), $r['s']);
        $ws[$i] = $b ? ($b[2] - $b[0]) : 0;
        $total += $ws[$i];
    }
    $startX = $align === 'right' ? $x - $total : ($align === 'center' ? $x - $total / 2 : $x);
    $c = pd_rgb($im, $hex);
    foreach ($runs as $i => $r) {
        imagettftext($im, $size, 0, (int)round($startX), (int)round($y), $c, pd_font($bold, $r['ar']), $r['s']);
        $startX += $ws[$i];
    }
    return $total;
}

/** wrap to $maxW and draw; returns the y after the last line */
function pd_para($im, float $x, float $y, float $maxW, float $size, string $hex, string $text,
                 string $align = 'left', bool $bold = false, float $lead = 1.55, bool $draw = true): float {
    $words = preg_split('/\s+/u', trim($text)) ?: [];
    $line  = '';
    foreach ($words as $w) {
        $try = $line === '' ? $w : $line . ' ' . $w;
        if (pd_width($try, $size, $bold) > $maxW && $line !== '') {
            if ($draw) pd_text($im, $x, $y, $size, $hex, $line, $align, $bold);
            $y += $size * $lead;
            $line = $w;
        } else {
            $line = $try;
        }
    }
    if ($line !== '') {
        if ($draw) pd_text($im, $x, $y, $size, $hex, $line, $align, $bold);
        $y += $size * $lead;
    }
    return $y;
}

/** how tall would pd_para be? (nothing is drawn) */
function pd_para_h(float $maxW, float $size, string $text, bool $bold = false, float $lead = 1.55): float {
    return pd_para(null, 0, 0, $maxW, $size, '000000', $text, 'left', $bold, $lead, false);
}

/* ---------------- images ---------------- */

/** load any supported image file into a GD resource, honouring EXIF rotation */
function pd_load(string $path) {
    if (!is_file($path)) return null;
    $info = @getimagesize($path);
    if (!$info) return null;
    $im = null;
    switch ($info[2]) {
        case IMAGETYPE_JPEG: $im = @imagecreatefromjpeg($path); break;
        case IMAGETYPE_PNG:  $im = @imagecreatefrompng($path);  break;
        case IMAGETYPE_WEBP: if (function_exists('imagecreatefromwebp')) $im = @imagecreatefromwebp($path); break;
    }
    if (!$im) return null;
    if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $ex = @exif_read_data($path);
        $o  = (int)($ex['Orientation'] ?? 0);
        if ($o === 3) $im = imagerotate($im, 180, 0);
        elseif ($o === 6) $im = imagerotate($im, -90, 0);
        elseif ($o === 8) $im = imagerotate($im, 90, 0);
    }
    return $im;
}

/** draw $src into the box, cropped to fill, with rounded-ish framing */
function pd_photo($im, $src, int $x, int $y, int $w, int $h): void {
    pd_fill($im, $x, $y, $w, $h, 'ede4e8');
    if (!$src) return;
    $sw = imagesx($src); $sh = imagesy($src);
    $scale = max($w / $sw, $h / $sh);
    $cw = (int)round($w / $scale);
    $ch = (int)round($h / $scale);
    $sx = (int)round(($sw - $cw) / 2);
    $sy = (int)round(($sh - $ch) / 2);
    imagecopyresampled($im, $src, $x, $y, $sx, $sy, $w, $h, $cw, $ch);
}

/* ---------------- PDF assembly ---------------- */

/** @param string[] $jpegs raw JPEG bytes, one per page */
function pdf_build(array $jpegs, int $pxW = PD_W, int $pxH = PD_H): string {
    $wPt = round($pxW * 72 / PD_DPI, 2);      // 595.2 x 841.92 for A4
    $hPt = round($pxH * 72 / PD_DPI, 2);

    $objs   = [];                              // 1-based list of object bodies
    $add    = function (string $body) use (&$objs): int { $objs[] = $body; return count($objs); };

    $catalog = $add('');                       // 1 — filled in later
    $pages   = $add('');                       // 2 — filled in later

    $kids = [];
    foreach ($jpegs as $jpg) {
        $imgNo = $add("<< /Type /XObject /Subtype /Image /Width $pxW /Height $pxH "
                    . "/ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode "
                    . "/Length " . strlen($jpg) . " >>\nstream\n" . $jpg . "\nendstream");
        $content = "q\n$wPt 0 0 $hPt 0 0 cm\n/Im0 Do\nQ";
        $cNo = $add("<< /Length " . strlen($content) . " >>\nstream\n" . $content . "\nendstream");
        $pNo = $add("<< /Type /Page /Parent $pages 0 R /MediaBox [0 0 $wPt $hPt] "
                  . "/Resources << /XObject << /Im0 $imgNo 0 R >> /ProcSet [/PDF /ImageC] >> "
                  . "/Contents $cNo 0 R >>");
        $kids[] = "$pNo 0 R";
    }

    $objs[$catalog - 1] = "<< /Type /Catalog /Pages $pages 0 R >>";
    $objs[$pages - 1]   = "<< /Type /Pages /Kids [" . implode(' ', $kids) . "] /Count " . count($kids) . " >>";

    $info = $add("<< /Producer (Evaluate Your Car) /Title (Car valuation) /CreationDate (D:" . gmdate('YmdHis') . "Z) >>");

    $pdf     = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
    $offsets = [];
    foreach ($objs as $i => $body) {
        $offsets[$i + 1] = strlen($pdf);
        $pdf .= ($i + 1) . " 0 obj\n" . $body . "\nendobj\n";
    }
    $xref = strlen($pdf);
    $n    = count($objs) + 1;
    $pdf .= "xref\n0 $n\n0000000000 65535 f \n";
    for ($i = 1; $i < $n; $i++) $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
    $pdf .= "trailer\n<< /Size $n /Root $catalog 0 R /Info $info 0 R >>\nstartxref\n$xref\n%%EOF\n";
    return $pdf;
}

function pd_jpeg($im, int $quality = 86): string {
    ob_start();
    imagejpeg($im, null, $quality);
    return (string)ob_get_clean();
}
