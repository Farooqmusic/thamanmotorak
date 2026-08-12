<?php
/* ============================================================
   arabic.php — turns logical Arabic text into the connected,
   right-to-left form that GD can actually draw.

   GD/FreeType draws glyphs one by one with no shaping, so we do
   the shaping ourselves: pick the isolated / initial / medial /
   final presentation form for every letter, glue lam+alef into
   its ligature, then reorder the runs right-to-left.
   ============================================================ */
declare(strict_types=1);

function ar_tables(): array {
    static $t = null;
    if ($t === null) {
        $t = require __DIR__ . '/arabic-forms.php';

        /* ARABIC TATWEEL (U+0640) — the stretching dash used in the logo
           «ثـمـــن مــوتــرك». The generated Unicode table leaves it out because
           it has no presentation forms of its own, but it DOES join on both
           sides. Without this entry the shaper treated it as a full stop:
           every letter around it fell back to its isolated shape and the name
           came out broken in the PDF. It is its own glyph in all four forms. */
        $t['forms'][0x0640] = [
            'isolated' => 0x0640, 'final' => 0x0640,
            'initial'  => 0x0640, 'medial' => 0x0640,
        ];
    }
    return $t;
}

/** UTF-8 string -> array of codepoints */
function ar_cps(string $s): array {
    $out = [];
    $len = strlen($s);
    for ($i = 0; $i < $len;) {
        $c = ord($s[$i]);
        if ($c < 0x80)      { $out[] = $c; $i += 1; }
        elseif ($c < 0xE0)  { $out[] = (($c & 0x1F) << 6) | (ord($s[$i+1] ?? "\0") & 0x3F); $i += 2; }
        elseif ($c < 0xF0)  { $out[] = (($c & 0x0F) << 12) | ((ord($s[$i+1] ?? "\0") & 0x3F) << 6) | (ord($s[$i+2] ?? "\0") & 0x3F); $i += 3; }
        else                { $out[] = (($c & 0x07) << 18) | ((ord($s[$i+1] ?? "\0") & 0x3F) << 12) | ((ord($s[$i+2] ?? "\0") & 0x3F) << 6) | (ord($s[$i+3] ?? "\0") & 0x3F); $i += 4; }
    }
    return $out;
}

function ar_chr(int $cp): string {
    if ($cp < 0x80)    return chr($cp);
    if ($cp < 0x800)   return chr(0xC0 | ($cp >> 6)) . chr(0x80 | ($cp & 0x3F));
    if ($cp < 0x10000) return chr(0xE0 | ($cp >> 12)) . chr(0x80 | (($cp >> 6) & 0x3F)) . chr(0x80 | ($cp & 0x3F));
    return chr(0xF0 | ($cp >> 18)) . chr(0x80 | (($cp >> 12) & 0x3F)) . chr(0x80 | (($cp >> 6) & 0x3F)) . chr(0x80 | ($cp & 0x3F));
}

/** harakat and other marks: they never break a join */
function ar_is_mark(int $cp): bool {
    return ($cp >= 0x0610 && $cp <= 0x061A) || ($cp >= 0x064B && $cp <= 0x065F)
        || $cp === 0x0670 || ($cp >= 0x06D6 && $cp <= 0x06ED);
}

function ar_is_letter(int $cp): bool {
    $t = ar_tables();
    return isset($t['forms'][$cp]);
}

/** true when this letter connects to the letter that follows it */
function ar_joins_next(int $cp): bool {
    $t = ar_tables();
    return isset($t['forms'][$cp]['initial']);
}
/** true when this letter connects to the letter before it */
function ar_joins_prev(int $cp): bool {
    $t = ar_tables();
    return isset($t['forms'][$cp]['final']);
}

function ar_is_rtl(int $cp): bool {
    return ($cp >= 0x0600 && $cp <= 0x06FF) || ($cp >= 0x0750 && $cp <= 0x077F)
        || ($cp >= 0xFB50 && $cp <= 0xFDFF) || ($cp >= 0xFE70 && $cp <= 0xFEFF)
        || ($cp >= 0x0590 && $cp <= 0x05FF);
}

/**
 * Shape + reorder. Returns a string ready to hand to imagettftext().
 * Latin words and numbers inside Arabic keep their own left-to-right order.
 */
function ar_shape(string $text): string {
    if ($text === '') return '';
    $t   = ar_tables();
    $cps = ar_cps($text);
    $n   = count($cps);

    /* ---- 1. pick the presentation form for every letter ---- */
    $shaped = [];
    for ($i = 0; $i < $n; $i++) {
        $c = $cps[$i];

        if (!ar_is_letter($c)) { $shaped[] = $c; continue; }

        // nearest non-mark neighbours
        $p = null;
        for ($j = $i - 1; $j >= 0; $j--) { if (!ar_is_mark($cps[$j])) { $p = $cps[$j]; break; } }
        $q = null; $qi = null;
        for ($j = $i + 1; $j < $n; $j++) { if (!ar_is_mark($cps[$j])) { $q = $cps[$j]; $qi = $j; break; } }

        $prevJoins = ($p !== null && ar_is_letter($p) && ar_joins_next($p));
        $nextJoins = ($q !== null && ar_is_letter($q) && ar_joins_prev($q));

        /* lam + alef -> one ligature glyph */
        if ($c === 0x0644 && $q !== null && isset($t['lam_alef'][$q])) {
            $lig = $t['lam_alef'][$q];
            $pick = ($prevJoins && isset($lig['final'])) ? $lig['final'] : ($lig['isolated'] ?? ($lig['final'] ?? null));
            if ($pick === null) { $shaped[] = $c; continue; }   // font has no ligature: keep the lam
            $shaped[] = $pick;
            // consume the alef (and any marks between)
            for ($j = $i + 1; $j <= $qi; $j++) { if (ar_is_mark($cps[$j])) $shaped[] = $cps[$j]; }
            $i = $qi;
            continue;
        }

        $f = $t['forms'][$c];
        if ($prevJoins && $nextJoins)      $form = $f['medial']   ?? ($f['final']    ?? ($f['isolated'] ?? $c));
        elseif ($prevJoins)                $form = $f['final']    ?? ($f['isolated'] ?? $c);
        elseif ($nextJoins)                $form = $f['initial']  ?? ($f['isolated'] ?? $c);
        else                               $form = $f['isolated'] ?? $c;
        $shaped[] = (int)$form;
    }

    /* ---- 2. bidi: work out the display order ---- */
    /* class: 'R' arabic · 'L' latin · 'D' digits (weak) · 'N' spaces & punctuation */
    $cls = function (int $c): string {
        if (ar_is_rtl($c) || ar_is_mark($c))                       return 'R';
        if ($c >= 0x30 && $c <= 0x39)                              return 'D';
        if (($c >= 0x41 && $c <= 0x5A) || ($c >= 0x61 && $c <= 0x7A) || $c >= 0x00C0) return 'L';
        return 'N';
    };
    $k = array_map($cls, $shaped);
    $n2 = count($shaped);

    /* base direction = first strong letter (digits do not count) */
    $base = 'L';
    foreach ($k as $x) { if ($x === 'R' || $x === 'L') { $base = $x; break; } }

    /* digits behave like latin for ordering */
    foreach ($k as $i => $x) if ($x === 'D') $k[$i] = 'L';

    /* a neutral run between two identical directions joins them, otherwise it follows the base */
    for ($i = 0; $i < $n2; $i++) {
        if ($k[$i] !== 'N') continue;
        $j = $i; while ($j < $n2 && $k[$j] === 'N') $j++;
        $before = $i > 0   ? $k[$i - 1] : $base;
        $after  = $j < $n2 ? $k[$j]     : $base;
        $fill   = ($before === $after) ? $before : $base;
        for ($m = $i; $m < $j; $m++) $k[$m] = $fill;
        $i = $j - 1;
    }

    /* group into runs */
    $runs = [];
    foreach ($shaped as $i => $c) {
        if ($runs && $runs[count($runs) - 1][0] === $k[$i]) $runs[count($runs) - 1][1][] = $c;
        else $runs[] = [$k[$i], [$c]];
    }

    if ($base === 'R') $runs = array_reverse($runs);

    $out = '';
    foreach ($runs as $r) {
        $cs = ($r[0] === 'R') ? ar_flip($r[1]) : $r[1];
        foreach ($cs as $c) $out .= ar_chr($c);
    }
    return $out;
}

/** reverse a run, keeping each mark glued behind its letter, and mirror brackets */
function ar_flip(array $cs): array {
    $mirror = ['(' => ')', ')' => '(', '[' => ']', ']' => '[', '{' => '}', '}' => '{',
               '<' => '>', '>' => '<'];
    $groups = [];
    foreach ($cs as $c) {
        if (ar_is_mark($c) && $groups) { $groups[count($groups) - 1][] = $c; }
        else $groups[] = [$c];
    }
    $groups = array_reverse($groups);
    $out = [];
    foreach ($groups as $g) {
        foreach ($g as $c) {
            $ch = ar_chr($c);
            $out[] = isset($mirror[$ch]) ? ord($mirror[$ch]) : $c;
        }
    }
    return $out;
}

/** does this string contain any Arabic at all? */
function has_arabic(string $s): bool {
    foreach (ar_cps($s) as $c) if (ar_is_rtl($c)) return true;
    return false;
}
