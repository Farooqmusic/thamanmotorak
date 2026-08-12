<?php
/* ============================================================
   lib.php — shared helpers (flat-file storage, no MySQL needed)
   ============================================================ */
declare(strict_types=1);

define('APP_ROOT', __DIR__);
define('DATA_DIR', APP_ROOT . '/data');
define('UPLOAD_DIR', APP_ROOT . '/uploads');
define('DB_FILE', DATA_DIR . '/requests.json');

function cfg(?string $key = null) {
    static $c = null;
    if ($c === null) $c = require APP_ROOT . '/config.php';
    return $key === null ? $c : ($c[$key] ?? null);
}

/* every editable word, link and contact detail */
require_once APP_ROOT . '/content.php';

/* the car diagram and the paint / interior / engine / gearbox questions */
require_once APP_ROOT . '/carmap.php';

function ensure_dirs(): void {
    foreach ([DATA_DIR, UPLOAD_DIR] as $d) {
        if (!is_dir($d)) @mkdir($d, 0755, true);
    }
    // Uploaded files are served only through file.php (admin session required).
    $ht = UPLOAD_DIR . '/.htaccess';
    if (!file_exists($ht)) {
        @file_put_contents($ht, "Options -Indexes\nphp_flag engine off\nRequire all denied\nDeny from all\n");
    }
    $ht2 = DATA_DIR . '/.htaccess';
    if (!file_exists($ht2)) @file_put_contents($ht2, "Require all denied\nDeny from all\n");
    if (!file_exists(DB_FILE)) @file_put_contents(DB_FILE, "[]");
}

/* ---------- storage ---------- */

function db_read(): array {
    ensure_dirs();
    $raw = @file_get_contents(DB_FILE);
    $rows = json_decode((string)$raw, true);
    return is_array($rows) ? $rows : [];
}

/** Read-modify-write under an exclusive lock. $fn receives and returns the rows array. */
function db_write(callable $fn) {
    ensure_dirs();
    $fp = fopen(DB_FILE, 'c+');
    if (!$fp) throw new RuntimeException('storage unavailable');
    flock($fp, LOCK_EX);
    $raw  = stream_get_contents($fp);
    $rows = json_decode((string)$raw, true);
    if (!is_array($rows)) $rows = [];
    $rows = $fn($rows);
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    return $rows;
}

function find_request(string $id): ?array {
    $id = strtoupper(trim($id));
    foreach (db_read() as $r) {
        if (strtoupper($r['id'] ?? '') === $id) return $r;
    }
    return null;
}

/* ---------- id generation ---------- */

/** 6 characters, no 0/O/1/I/L — easy to read off a phone screen. */
function new_id(): string {
    $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    do {
        $id = '';
        for ($i = 0; $i < 6; $i++) $id .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    } while (find_request($id) !== null);
    return $id;
}

/* ---------- the 8 photo positions ---------- */

function slots(): array {
    return [
        ['key' => 'front',      'req' => true,  'ar' => 'الأمام',            'en' => 'Front'],
        ['key' => 'back',       'req' => true,  'ar' => 'الخلف',             'en' => 'Back'],
        ['key' => 'left',       'req' => true,  'ar' => 'الجانب الأيسر',      'en' => 'Left side'],
        ['key' => 'right',      'req' => true,  'ar' => 'الجانب الأيمن',      'en' => 'Right side'],
        ['key' => 'roof',       'req' => true,  'ar' => 'السقف',             'en' => 'Roof'],
        ['key' => 'under',      'req' => false, 'ar' => 'أسفل السيارة',       'en' => 'Underbody'],
        ['key' => 'dashboard',  'req' => false, 'ar' => 'التابلوه والعداد',   'en' => 'Dashboard & odometer'],
        ['key' => 'rear_seats', 'req' => false, 'ar' => 'المقاعد الخلفية',    'en' => 'Rear seats'],
    ];
}

function slot_keys(): array {
    return array_column(slots(), 'key');
}

function slot_label(?string $key, string $lang = 'en'): string {
    $key = (string)$key;
    foreach (slots() as $s) if ($s['key'] === $key) return $s[$lang] ?? $s['en'];
    return $key;
}

/* ---------- signed links (gallery, opened straight from the email) ---------- */

function link_token(string $id): string {
    return substr(hash_hmac('sha256', strtoupper($id), (string)cfg('link_secret')), 0, 24);
}
function link_ok(string $id, string $token): bool {
    return hash_equals(link_token($id), (string)$token);
}

/* ---------- image resizing (for the inline email copies) ---------- */

function has_gd(): bool { return function_exists('imagecreatetruecolor') && function_exists('imagejpeg'); }

/** Returns raw JPEG bytes resized to fit $max px, or null if it cannot be done. */
function resized_jpeg(string $path, int $max, int $quality): ?string {
    if (!has_gd() || !is_file($path)) return null;
    $info = @getimagesize($path);
    if (!$info) return null;
    [$w, $h] = $info;
    $src = null;
    switch ($info[2]) {
        case IMAGETYPE_JPEG: $src = @imagecreatefromjpeg($path); break;
        case IMAGETYPE_PNG:  $src = @imagecreatefrompng($path);  break;
        case IMAGETYPE_WEBP: if (function_exists('imagecreatefromwebp')) $src = @imagecreatefromwebp($path); break;
    }
    if (!$src) return null;

    $scale = min(1, $max / max($w, $h));
    $nw = max(1, (int)round($w * $scale));
    $nh = max(1, (int)round($h * $scale));
    $dst = imagecreatetruecolor($nw, $nh);
    imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);

    ob_start();
    imagejpeg($dst, null, $quality);
    $bytes = ob_get_clean();
    imagedestroy($src); imagedestroy($dst);
    return $bytes ?: null;
}

/* ---------- mail ---------- */

/**
 * The brand name written plainly, for mail headers only.
 *
 * “ثـمـــن مــوتــرك” carries eight tatweel characters (U+0640) stretched
 * through it. On the website that is the logo's typography. Inside a From name
 * or a Subject it is something else entirely: characters padded into the
 * middle of words is the oldest trick spammers use to slip past word matching,
 * and filters score it. The pages keep the decorative spelling; the envelope
 * gets the plain one.
 */
function mail_name(string $s): string {
    $s = str_replace("\u{0640}", '', $s);          // tatweel
    $s = preg_replace('/\s{2,}/u', ' ', $s);
    return trim((string)$s);
}

/* ---------- mail ---------- */

/**
 * A readable plain-text version of the HTML mail.
 *
 * Every mailbox provider — Outlook/Hotmail most of all — treats an HTML-only
 * message as a spam signal, because real correspondence carries both parts.
 * This turns the HTML into something a text-only reader can follow.
 */
function mail_plain_text(string $html): string {
    $t = preg_replace('~<(script|style)\b[^>]*>.*?</\1>~is', '', $html);
    /* keep the link addresses, they are the useful part in text form */
    $t = preg_replace('~<a\b[^>]*href="([^"]+)"[^>]*>(.*?)</a>~is', '$2 <$1>', (string)$t);
    $t = preg_replace('~<(br|hr)\s*/?>~i', "\n", (string)$t);
    $t = preg_replace('~</(p|div|tr|h[1-6]|table|li)\s*>~i', "\n", (string)$t);
    $t = preg_replace('~</t[dh]\s*>~i', '  ', (string)$t);
    $t = strip_tags((string)$t);
    $t = html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $t = str_replace(["\xC2\xA0", "\r\n", "\r"], [' ', "\n", "\n"], $t);
    $t = preg_replace('~[ \t]{2,}~u', '  ', $t);
    $t = preg_replace('~[ \t]*\n[ \t]*~u', "\n", (string)$t);
    $t = preg_replace('~\n{3,}~', "\n\n", (string)$t);
    return trim((string)$t);
}

/** base64 body part, hard-wrapped at 76 chars so no line ever breaks RFC 5322. */
function mail_b64(string $s): string {
    return chunk_split(base64_encode($s), 76, "\r\n");
}

/**
 * $inline = [ ['cid'=>'front', 'name'=>'front.jpg', 'bytes'=>'...', 'type'=>'image/jpeg'], ... ]
 * Images are embedded so they show inside the email body (multipart/related).
 *
 * The message is always built as:
 *      multipart/mixed                 (only when there are attachments)
 *        multipart/alternative
 *          text/plain                  ← keeps us out of the junk folder
 *          text/html  or  multipart/related (html + inline images)
 *        attachment…
 * and every part is base64, because a single un-wrapped 6 000-character HTML
 * line is by itself enough for a filter to score the message as spam.
 */
function send_mail(string $to, string $subject, string $htmlBody, array $inline = [], array $attach = []): bool {
    $from = (string)cfg('from_email');
    /* The sender name follows the site name set in the control panel — plainly
       spelled, and joined with a middle dot. A vertical bar between two halves
       of a From name is a shape filters see mostly in advertising. */
    $name = trim(mail_name(ct('appName', 'ar')) . ' · ' . mail_name(ct('appName', 'en')), ' ·');
    if ($name === '') $name = (string)cfg('from_name');
    $encSubject = mb_encode_mimeheader(mail_name($subject), 'UTF-8');

    /* ---- build the body ---- */
    /* replies go to the public contact address the client set in the control panel */
    $replyTo = cv('contactEmail');
    if (!filter_var($replyTo, FILTER_VALIDATE_EMAIL)) $replyTo = $from;

    $domain = explode('@', $from)[1] ?? 'localhost';

    /* Is this message going to an outside person, or is the site writing to
       itself? An unsubscribe header on a message you sent to your own mailbox is
       meaningless, and filters read it as a bulk-mailing marker. */
    $internal = strcasecmp(trim($to), trim($from)) === 0;

    $common = [
        'MIME-Version: 1.0',
        'Date: ' . gmdate('D, d M Y H:i:s') . ' +0000',
        'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $domain . '>',
        'From: ' . mb_encode_mimeheader($name, 'UTF-8') . ' <' . $from . '>',
        'Reply-To: ' . $replyTo,
        'Auto-Submitted: auto-generated',
        /* stops Gmail collapsing separate valuations into one thread */
        'X-Entity-Ref-ID: ' . bin2hex(random_bytes(8)),
        'X-Mailer: ThamanMotorak',
    ];
    if (!$internal) {
        /* Hotmail and Gmail both look for a way out of a mailing; giving them one
           on a message to a real recipient costs nothing and improves placement. */
        $common[] = 'List-Unsubscribe: <mailto:' . $replyTo . '?subject=unsubscribe>';
        $common[] = 'List-Unsubscribe-Post: List-Unsubscribe=One-Click';
    }

    /* The header logo is carried by every message that shows it. Done here, in
       one place, rather than at each call site — a message that forgot to bring
       it would show a broken picture where the brand should be. */
    if (strpos($htmlBody, 'cid:' . EMAIL_LOGO_CID) !== false) {
        $logo = email_logo_bytes();
        if ($logo !== null) {
            array_unshift($inline, [
                'cid'   => EMAIL_LOGO_CID,
                'name'  => 'tmk.png',
                'type'  => 'image/png',
                'bytes' => $logo,
            ]);
        }
    }

    /* ---- level 1: the HTML, wrapped with its inline images when there are any ---- */
    if (!$inline) {
        $htmlPart = "Content-Type: text/html; charset=UTF-8\r\n"
                  . "Content-Transfer-Encoding: base64\r\n\r\n" . mail_b64($htmlBody);
    } else {
        $rb = '=_rel_' . bin2hex(random_bytes(10));
        $htmlPart = 'Content-Type: multipart/related; boundary="' . $rb . '"; type="text/html"' . "\r\n\r\n"
                  . "--$rb\r\nContent-Type: text/html; charset=UTF-8\r\n"
                  . "Content-Transfer-Encoding: base64\r\n\r\n" . mail_b64($htmlBody) . "\r\n";
        foreach ($inline as $img) {
            $htmlPart .= "--$rb\r\n"
                   . 'Content-Type: ' . $img['type'] . '; name="' . $img['name'] . "\"\r\n"
                   . "Content-Transfer-Encoding: base64\r\n"
                   . 'Content-ID: <' . $img['cid'] . ">\r\n"
                   . 'Content-Disposition: inline; filename="' . $img['name'] . "\"\r\n\r\n"
                   . chunk_split(base64_encode($img['bytes']), 76, "\r\n");
        }
        $htmlPart .= "--$rb--\r\n";
    }

    /* ---- level 2: plain text + HTML, so the message is never HTML-only ---- */
    $ab = '=_alt_' . bin2hex(random_bytes(10));
    $altType = 'multipart/alternative; boundary="' . $ab . '"';
    $altPart = 'Content-Type: ' . $altType . "\r\n\r\n"
             . "--$ab\r\nContent-Type: text/plain; charset=UTF-8\r\n"
             . "Content-Transfer-Encoding: base64\r\n\r\n" . mail_b64(mail_plain_text($htmlBody)) . "\r\n"
             . "--$ab\r\n" . $htmlPart . "\r\n"
             . "--$ab--\r\n";

    /* ---- level 3: the attachments ---- */
    if (!$attach) {
        $common[] = 'Content-Type: ' . $altType;
        $body = substr($altPart, strpos($altPart, "\r\n\r\n") + 4);
    } else {
        $mb = '=_mix_' . bin2hex(random_bytes(10));
        $common[] = 'Content-Type: multipart/mixed; boundary="' . $mb . '"';
        $body = "This is a multi-part message in MIME format.\r\n\r\n"
              . "--$mb\r\n" . $altPart . "\r\n";
        foreach ($attach as $f) {
            $body .= "--$mb\r\n"
                   . 'Content-Type: ' . ($f['type'] ?? 'application/octet-stream') . '; name="' . $f['name'] . "\"\r\n"
                   . "Content-Transfer-Encoding: base64\r\n"
                   . 'Content-Disposition: attachment; filename="' . $f['name'] . "\"\r\n\r\n"
                   . chunk_split(base64_encode($f['bytes']), 76, "\r\n");
        }
        $body .= "--$mb--\r\n";
    }

    $tag = ' :: ' . $subject . ($inline ? ' (' . count($inline) . ' inline images)' : '')
         . ($attach ? ' (+' . count($attach) . ' attachment, ' . human_size(array_sum(array_map(function ($a) { return strlen($a['bytes']); }, $attach))) . ')' : '');

    /* ---- send it ---- */
    $method = (string)($GLOBALS['__eyc_force_method'] ?? cfg('mail_method'));

    if ($method === 'smtp') {
        require_once APP_ROOT . '/smtp.php';
        /* Date and Message-ID are already in $common — they must appear once only */
        $hdr = array_merge([
            'To: ' . $to,
            'Subject: ' . $encSubject,
        ], $common);

        $c = (array)cfg('smtp');
        $c['from'] = $from;
        $err = '';
        $ok = smtp_send($to, implode("\r\n", $hdr), $body, $c, $err);
        log_line(($ok ? 'SMTP OK   ' : 'SMTP FAIL ') . $to . $tag);
        if ($ok) return true;

        log_line('   └─ ' . str_replace("\n", "\n      ", $err));

        /* SMTP is down or misconfigured — never lose the message, fall back to mail() */
        if (function_exists('mail')) {
            $ok2 = @mail($to, $encSubject, $body, implode("\r\n", $common), '-f' . $from)
                || @mail($to, $encSubject, $body, implode("\r\n", $common));
            log_line('   └─ ' . ($ok2 ? 'fell back to PHP mail() and it was accepted'
                                      : 'PHP mail() fallback also failed'));
            return (bool)$ok2;
        }
        return false;
    }

    if (!function_exists('mail')) {
        log_line('MAIL FAIL ' . $to . $tag . '  (mail() is disabled on this server — switch to SMTP)');
        return false;
    }

    $ok = @mail($to, $encSubject, $body, implode("\r\n", $common), '-f' . $from);
    if (!$ok) {  // some hosts reject the -f parameter; try once without it
        $ok = @mail($to, $encSubject, $body, implode("\r\n", $common));
        if ($ok) log_line('   └─ note: sent without the -f envelope parameter');
    }
    log_line(($ok ? 'MAIL OK   ' : 'MAIL FAIL ') . $to . $tag);
    if (!$ok) {
        $e = error_get_last();
        log_line('   └─ ' . ($e['message'] ?? 'mail() returned false with no error message')
               . '  |  hint: does ' . $from . ' exist in cPanel → Email Accounts? Otherwise switch mail_method to smtp.');
    }
    return (bool)$ok;
}

/**
 * Did the most recent message leave properly?
 *
 * Only the latest state matters. An old failure that has since been fixed must
 * stop reporting the moment a good send follows it — otherwise the warning
 * outlives the problem and everyone learns to ignore it.
 */
function mail_health(): array {
    $out = ['ok' => true, 'bad' => 0, 'last' => ''];
    $f = DATA_DIR . '/log.txt';
    if (!is_file($f)) return $out;

    $sz = (int)@filesize($f);
    $fp = @fopen($f, 'rb');
    if (!$fp) return $out;
    if ($sz > 40000) fseek($fp, $sz - 40000);
    $tail = (string)fread($fp, 40000);
    fclose($fp);

    /* walk forwards; a good send resets the count to zero */
    foreach (preg_split('/\R/', $tail) ?: [] as $l) {
        if (strpos($l, 'SMTP OK') !== false || strpos($l, 'MAIL OK') !== false) {
            $out['bad'] = 0; $out['last'] = '';
        } elseif (strpos($l, 'SMTP FAIL') !== false || strpos($l, 'MAIL FAIL') !== false) {
            $out['bad']++; $out['last'] = trim($l);
        }
        /* the “fell back to PHP mail()” line belongs to the failure above it */
    }
    $out['ok'] = ($out['bad'] === 0);
    return $out;
}

/** The warning itself, so the admin panel and the diagnostics pages agree. */
function mail_health_html(bool $arabic = false): string {
    $mh = mail_health();
    if ($mh['ok']) return '';
    $n = (int)$mh['bad'];
    $t = $arabic
        ? '<b>⚠️ البريد يخرج بدون توقيع</b><br>فشل الاتصال بـ SMTP في آخر ' . $n
          . ' رسالة، فخرجت عبر mail() من عنوان الخادم نفسه — بدون توقيع DKIM وبدون مطابقة SPF، '
          . 'وسياسة DMARC عندك p=quarantine، أي أن المستقبِل مُطالَب بوضعها في Junk.'
        : '<b>⚠️ Mail is leaving unsigned</b><br>The SMTP login failed for the last ' . $n
          . ' message(s), so they went out through mail() from the server\'s own address — '
          . 'no DKIM signature, no SPF alignment. With DMARC at p=quarantine the recipient is '
          . 'being asked to file them as spam.';
    return $t . '<br><span style="font-size:12px;opacity:.85;direction:ltr;display:inline-block;margin-top:6px">'
         . e($mh['last']) . '</span>';
}

function log_line(string $line): void {
    ensure_dirs();
    @file_put_contents(DATA_DIR . '/log.txt', gmdate('Y-m-d H:i:s') . ' UTC  ' . $line . "\n", FILE_APPEND);
}

/* ---------- the customer-facing emails (used by api.php and admin.php) ---------- */

function em_row(string $label, string $value): string {
    return '<tr>'
         . '<td class="tx-mute" style="padding:9px 12px 9px 0;color:#7c6a71 !important;font-size:13px;vertical-align:top;white-space:nowrap;border-bottom:1px solid #f2e7ea">' . $label . '</td>'
         . '<td class="tx" style="padding:9px 0;color:#2a1119 !important;font-size:14px;border-bottom:1px solid #f2e7ea" dir="auto">' . $value . '</td>'
         . '</tr>';
}

/** Everything the customer typed, so the email is a complete record. */
function customer_details_table(array $r): string {
    $photoList = [];
    foreach ((array)($r['photos'] ?? []) as $p) {
        $k = (string)($p['slot'] ?? '');
        $photoList[] = $k === '' ? (string)($p['file'] ?? '') : slot_label($k, 'ar') . ' / ' . slot_label($k, 'en');
    }

    $h = '<table style="width:100%;border-collapse:collapse">'
       . em_row('رقم الطلب<br><span style="font-size:11px">Request ID</span>',
                '<b style="font-size:17px;letter-spacing:2px">' . e($r['id']) . '</b>')
       . em_row('الاسم<br><span style="font-size:11px">Name</span>', e($r['name']))
       . em_row('الجوال<br><span style="font-size:11px">Mobile</span>', '<span dir="ltr">' . e($r['phone']) . '</span>')
       . em_row('البريد<br><span style="font-size:11px">Email</span>', '<span dir="ltr">' . e($r['email']) . '</span>')
       . em_row('الشركة<br><span style="font-size:11px">Make</span>', e($r['car_make']))
       . em_row('الفئة<br><span style="font-size:11px">Class</span>', e($r['car_class'] ?? '—'))
       . em_row('الموديل<br><span style="font-size:11px">Model / trim</span>', e($r['car_model'] ?: '—'))
       . em_row('سنة الصنع<br><span style="font-size:11px">Year</span>', '<span dir="ltr">' . e($r['car_year']) . '</span>')
       . em_row('الممشى<br><span style="font-size:11px">Mileage</span>', '<span dir="ltr">' . e($r['mileage'] ?: '—') . '</span>')
       . em_row('رقم الاستمارة<br><span style="font-size:11px">Registration</span>', '<span dir="ltr">' . e($r['registration'] ?: '—') . '</span>')
       . em_row('رقم الشاصي<br><span style="font-size:11px">Chassis / VIN</span>', '<span dir="ltr">' . e($r['chassis'] ?: '—') . '</span>')
       . em_row('ملاحظات<br><span style="font-size:11px">Notes</span>', nl2br(e($r['notes'] ?: '—')))
       . cond_email_rows($r)
       . em_row('الصور المستلمة<br><span style="font-size:11px">Photos received</span>',
                ($photoList ? implode('<br>', array_map('e', $photoList)) : '—')
                . (!empty($r['videos']) ? '<br><b>' . count($r['videos']) . ' فيديو / video</b>' : ''))
       . em_row('تاريخ الإرسال<br><span style="font-size:11px">Submitted</span>',
                '<span dir="ltr">' . e(gmdate('d M Y H:i', strtotime($r['created']))) . ' UTC</span>')
       . em_row('تُحذف الملفات<br><span style="font-size:11px">Files deleted on</span>',
                '<span dir="ltr">' . e(gmdate('d M Y', strtotime($r['expires_at']))) . ' (' . (int)$r['retention'] . ' days)</span>')
       . '</table>';
    return $h;
}

/** the paint / interior / engine / gearbox answers, as email rows */
function cond_email_rows(array $r): string {
    $h = '';
    foreach (cond_summary_rows($r) as $row) {
        $h .= em_row(
            e($row['ar']) . '<br><span style="font-size:11px">' . e($row['en']) . '</span>',
            '<span dir="rtl">' . e($row['v_ar']) . '</span>'
            . ($row['v_en'] !== '' && $row['v_en'] !== $row['v_ar']
                ? '<br><span style="font-size:12.5px;color:#7c6a71" dir="ltr">' . e($row['v_en']) . '</span>' : '')
        );
    }
    return $h;
}

/* ---------- the public contact details ----------
   Whatever the control panel holds is what the customer sees — nothing is
   hard-coded. Clearing the phone field in 📇 Contact details removes the
   number from the emails and from the PDF too. */

function public_contact(): array {
    $out = [];
    $phone = trim(cv('contactPhone'));
    if ($phone !== '' && whatsapp_digits() !== '') {
        $out[] = ['k' => 'WhatsApp', 'v' => $phone, 'href' => 'https://wa.me/' . whatsapp_digits()];
    }
    if (filter_var(cv('contactEmail'), FILTER_VALIDATE_EMAIL)) {
        $out[] = ['k' => 'Email', 'v' => cv('contactEmail'), 'href' => 'mailto:' . cv('contactEmail')];
    }
    if (cv('instagramUrl') !== '') {
        $out[] = ['k' => 'Instagram', 'v' => cv('instagramName') ?: cv('instagramUrl'), 'href' => cv('instagramUrl')];
    }
    if (cv('websiteUrl') !== '') {
        $out[] = ['k' => 'Website', 'v' => cv('websiteName') ?: cv('websiteUrl'), 'href' => cv('websiteUrl')];
    }
    foreach (['extraLink1', 'extraLink2'] as $x) {
        if (cv($x . 'Url') === '') continue;
        $out[] = ['k' => ct($x . 'Label', 'en') ?: (ct($x . 'Label', 'ar') ?: cv($x . 'Url')),
                  'v' => cv($x . 'Url'), 'href' => cv($x . 'Url')];
    }
    return $out;
}

/** one plain line for the PDF footer — empty string when nothing is set */
function public_contact_line(): string {
    $p = [];
    foreach (public_contact() as $c) {
        if ($c['k'] === 'Website') continue;                 // already on the footer
        $p[] = ($c['k'] === 'Email' ? '' : $c['k'] . ' ') . $c['v'];
    }
    return implode('   ·   ', $p);
}

/** the same list as an HTML block for the foot of an email */
function public_contact_html(): string {
    $items = public_contact();
    if (!$items) return '';
    $h = '<p style="margin:10px 0 0;font-size:13.5px;color:#4a3a40">';
    $bits = [];
    foreach ($items as $c) {
        $bits[] = ($c['k'] === 'Email' ? '' : e($c['k']) . ': ')
                . '<a href="' . e($c['href']) . '" style="color:#8a1538;font-weight:700;direction:ltr">' . e($c['v']) . '</a>';
    }
    return $h . implode(' &nbsp;·&nbsp; ', $bits) . '</p>';
}

/**
 * “If you found this in Junk, press Not spam.”
 *
 * Not decoration — it is the one thing a recipient can do that actually moves
 * the domain's reputation. A message rescued from the spam folder, and an
 * address added to the contacts, is what teaches Gmail and Outlook that this
 * sender is wanted. On a young domain that is worth more than any header.
 */
function em_junk_note(): string {
    $from = (string)cfg('from_email');
    return '<div style="background:#faf6ee;border:1px solid #efe0c2;border-radius:12px;padding:14px 16px;margin:22px 0 0">'
         . '<p style="margin:0 0 6px;font-size:13.5px;color:#6b5a2a;font-weight:700" dir="rtl">'
         . '📬 وجدت هذا البريد في «الرسائل غير المرغوب فيها»؟</p>'
         . '<p style="margin:0 0 10px;font-size:13px;color:#6b5a2a;line-height:1.85" dir="rtl">'
         . 'اضغط <b>«ليس بريداً مزعجاً»</b> وأضف <span dir="ltr">' . e($from) . '</span> إلى جهات الاتصال، '
         . 'حتى تصلك رسائلنا القادمة في البريد الوارد مباشرة.</p>'
         . '<p style="margin:0;font-size:13px;color:#6b5a2a;line-height:1.85">'
         . 'Found this in your Junk or Spam folder? Press <b>“Not spam”</b> and add '
         . '<span dir="ltr">' . e($from) . '</span> to your contacts, so our next messages reach your inbox.</p>'
         . '</div>';
}

function em_button(string $href, string $text, bool $gold = false): string {
    $bg  = $gold ? '#c9a227' : '#8a1538';
    $fg  = $gold ? '#3a2408' : '#ffffff';
    $cls = $gold ? 'btn-b'   : 'btn-a';
    return '<table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center"'
         . ' style="margin:0 auto"><tr>'
         . '<td bgcolor="' . $bg . '" align="center"'
         . ' style="background:' . $bg . ';border-radius:9px;padding:14px 26px">'
         . '<a href="' . e($href) . '" class="' . $cls . '"'
         . ' style="display:inline-block;color:' . $fg . ' !important;text-decoration:none;'
         . 'font-family:Segoe UI,Tahoma,Arial,sans-serif;font-size:15px;font-weight:700;line-height:1.3">'
         . '<span style="color:' . $fg . ' !important">' . $text . '</span></a>'
         . '</td></tr></table>';
}

/* ---------------------------------------------------------------------------
   The band across the top of every message: the TMK mark, then the name in
   Arabic and English beside it.

   The picture travels inside the message itself (a "cid" reference, attached
   by send_mail) rather than being fetched from the website. Mail programs
   block pictures loaded from the internet until the reader presses "show
   images" — a logo that only appears after the reader asks for it is worse
   than none. One that is part of the message is drawn straight away, and it
   also means the message looks the same when it is read offline.

   If the file is ever missing the row simply falls back to the name alone.
   --------------------------------------------------------------------------- */
define('EMAIL_LOGO', APP_ROOT . '/assets/brand/logo-email.png');
define('EMAIL_LOGO_CID', 'tmklogo');

function email_logo_bytes(): ?string {
    $b = @file_get_contents(EMAIL_LOGO);
    return ($b === false || $b === '') ? null : $b;
}

function email_header_row(): string {
    $ar = e(ct('appName', 'ar'));
    $en = e(ct('appName', 'en'));
    $name = '<span class="on-brand" style="color:#ffffff !important;font-size:17px;'
          . 'font-weight:700;line-height:1.5;white-space:nowrap">' . $ar . '</span>'
          . '<br><span class="on-brand" style="color:#ffffff !important;font-size:12px;'
          . 'font-weight:600;letter-spacing:.3px;opacity:.9;line-height:1.5">' . $en . '</span>';

    if (email_logo_bytes() === null) {
        return '<div style="text-align:start">' . $name . '</div>';
    }

    /* a two-cell table, because this is the only way Outlook reliably keeps a
       picture and a line of text side by side */
    return '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse">'
         . '<tr>'
         . '<td width="78" valign="middle" style="padding:0 12px 0 0">'
         . '<img src="cid:' . EMAIL_LOGO_CID . '" width="78" height="36" alt="TMK"'
         . ' style="display:block;width:78px;height:36px;border:0;outline:none;text-decoration:none">'
         . '</td>'
         . '<td valign="middle" style="padding:0">' . $name . '</td>'
         . '</tr></table>';
}

function email_shell(string $inner): string {
    /* Dark-mode mail apps (Gmail, Apple Mail, Outlook) recolour emails unless we
       declare that we handle both schemes — that is what turned the buttons pink.
       Every colour below is also forced with !important so nothing gets swapped. */
    return '<!doctype html><html lang="ar"><head>'
         . '<meta charset="utf-8">'
         . '<meta name="viewport" content="width=device-width,initial-scale=1">'
         . '<meta name="color-scheme" content="light only">'
         . '<meta name="supported-color-schemes" content="light only">'
         . '<style>'
         . ':root{color-scheme:light only;supported-color-schemes:light only;}'
         . 'a{text-decoration:none}'
         . 'u + .body .glist{width:100% !important}'
         . '@media (prefers-color-scheme:dark){'
         . '  .card,.body{background:#ffffff !important}'
         . '  .tx{color:#2a1119 !important}'
         . '  .tx-mute{color:#7c6a71 !important}'
         . '  .btn-a{color:#ffffff !important}'
         . '  .btn-b{color:#3a2408 !important}'
         . '  .on-brand{color:#ffffff !important}'
         . '}'
         . '</style></head>'
         . '<body class="body" style="margin:0;padding:0;background:#f7f4f5;'
         . 'font-family:Segoe UI,Tahoma,Arial,sans-serif;-webkit-text-size-adjust:100%">'
         . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#f7f4f5"'
         . ' style="background:#f7f4f5"><tr><td align="center" style="padding:24px 12px">'
         . '<table role="presentation" class="card" width="560" cellpadding="0" cellspacing="0" border="0" bgcolor="#ffffff"'
         . ' style="width:100%;max-width:560px;background:#ffffff;border:1px solid #eddfe4;border-radius:14px;overflow:hidden">'
         . '<tr><td bgcolor="#8a1538" style="background:#8a1538;padding:15px 22px">'
         . email_header_row()
         . '</td></tr>'
         . '<tr><td class="tx" style="padding:22px;color:#2a1119 !important;font-size:15px;line-height:1.7">'
         . $inner . '</td></tr>'
         . '<tr><td bgcolor="#faf5f7" style="background:#faf5f7;padding:14px 22px">'
         . '<span class="tx-mute" style="color:#7c6a71 !important;font-size:12px">'
         . e(cv('websiteName') ?: 'thamanmotorak.com') . '</span>'
         . '</td></tr></table></td></tr></table></body></html>';
}

/* ---------- support messages ----------
   Written to disk FIRST and emailed second. A message the customer took the
   trouble to write must not depend on the mail server being healthy — it is
   always readable in the control panel. */

define('SUPPORT_FILE', DATA_DIR . '/support.json');

function support_read(): array {
    ensure_dirs();
    $j = json_decode((string)@file_get_contents(SUPPORT_FILE), true);
    return is_array($j) ? $j : [];
}

function support_write(callable $fn): array {
    ensure_dirs();
    $fp = fopen(SUPPORT_FILE, 'c+');
    if (!$fp) throw new RuntimeException('storage unavailable');
    flock($fp, LOCK_EX);
    $rows = json_decode((string)stream_get_contents($fp), true);
    if (!is_array($rows)) $rows = [];
    $rows = $fn($rows);
    ftruncate($fp, 0); rewind($fp);
    fwrite($fp, json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    fflush($fp); flock($fp, LOCK_UN); fclose($fp);
    return $rows;
}

function support_unread(): int {
    $n = 0;
    foreach (support_read() as $r) if (empty($r['read'])) $n++;
    return $n;
}

/** how many messages this address/IP sent in the last hour */
function support_recent(string $ip, string $email): int {
    $since = time() - 3600;
    $n = 0;
    foreach (support_read() as $r) {
        if (strtotime((string)($r['created'] ?? '')) < $since) continue;
        if (($r['ip'] ?? '') === $ip || strcasecmp((string)($r['email'] ?? ''), $email) === 0) $n++;
    }
    return $n;
}

/** S + 6 characters from the same easy-to-read alphabet as a request ID */
function new_support_id(): string {
    $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    do {
        $id = 'S';
        for ($i = 0; $i < 6; $i++) $id .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    } while (find_support($id) !== null);
    return $id;
}

function find_support(string $id): ?array {
    $id = strtoupper(trim($id));
    foreach (support_read() as $r) if (strtoupper((string)($r['id'] ?? '')) === $id) return $r;
    return null;
}

/** “Chrome 151 · Windows” — the useful half of a 120-character user-agent */
function ua_short(string $ua): string {
    if (trim($ua) === '') return '—';
    $os = 'Unknown';
    if (preg_match('~iPhone OS (\d+)~', $ua, $m))            $os = 'iPhone iOS ' . $m[1];
    elseif (strpos($ua, 'iPad') !== false)                    $os = 'iPad';
    elseif (preg_match('~Android (\d+)~', $ua, $m))          $os = 'Android ' . $m[1];
    elseif (strpos($ua, 'Windows NT 10.0') !== false)         $os = 'Windows 10/11';
    elseif (strpos($ua, 'Windows') !== false)                 $os = 'Windows';
    elseif (strpos($ua, 'Mac OS X') !== false)                $os = 'Mac';
    elseif (strpos($ua, 'Linux') !== false)                   $os = 'Linux';

    $br = 'Browser';
    if (preg_match('~(Edg|EdgA)/(\d+)~', $ua, $m))                       $br = 'Edge ' . $m[2];
    elseif (preg_match('~(OPR|Opera)/(\d+)~', $ua, $m))                  $br = 'Opera ' . $m[2];
    elseif (preg_match('~SamsungBrowser/(\d+)~', $ua, $m))               $br = 'Samsung ' . $m[1];
    elseif (preg_match('~FxiOS/(\d+)|Firefox/(\d+)~', $ua, $m))         $br = 'Firefox ' . ($m[1] ?: ($m[2] ?? ''));
    elseif (preg_match('~CriOS/(\d+)~', $ua, $m))                        $br = 'Chrome ' . $m[1];
    elseif (preg_match('~Chrome/(\d+)~', $ua, $m))                       $br = 'Chrome ' . $m[1];
    elseif (preg_match('~Version/(\d+).*Safari~', $ua, $m))              $br = 'Safari ' . $m[1];

    return trim($br . ' · ' . $os);
}

function support_kinds(): array {
    return [
        'problem'    => ['ar' => 'مشكلة في الموقع', 'en' => 'A problem with the site'],
        'suggestion' => ['ar' => 'اقتراح',           'en' => 'A suggestion'],
        'question'   => ['ar' => 'استفسار',          'en' => 'A question'],
    ];
}

function support_kind_label(string $k, string $lang = 'ar'): string {
    $m = support_kinds();
    return isset($m[$k]) ? (string)$m[$k][$lang === 'en' ? 'en' : 'ar'] : $k;
}

/**
 * A short receipt so the visitor has the reference in writing.
 * Deliberately plain — no images, no attachment: the easiest kind to deliver.
 */
function support_ack_mail(array $r): bool {
    if (($r['email'] ?? '') === '') return false;
    $b   = base_url();
    $ar  = ($r['lang'] ?? 'ar') !== 'en';

    $inner =
      '<p style="margin:0 0 4px;font-size:16px" dir="rtl">وصلتنا رسالتك، شكراً لك ✅</p>'
    . '<p style="margin:0 0 20px;font-size:16px">We have received your message, thank you.</p>'

    . '<div style="background:#fdf2f5;border:1px dashed #e8c3cf;border-radius:14px;padding:20px;text-align:center;margin:0 0 22px">'
    . '<div class="tx-mute" style="color:#7c6a71 !important;font-size:12.5px" dir="rtl">رقم المتابعة</div>'
    . '<div class="tx-mute" style="color:#7c6a71 !important;font-size:12.5px;margin-bottom:6px">Your reference</div>'
    . '<div style="font-size:32px;font-weight:800;letter-spacing:7px;color:#8a1538 !important;direction:ltr">' . e($r['id']) . '</div>'
    . '</div>'

    . '<p style="margin:0 0 20px;text-align:center">'
    . em_button($b . '/?support=' . rawurlencode((string)$r['id']),
                'تابع رسالتك &nbsp;·&nbsp; Check your message') . '</p>'

    . '<p style="margin:0 0 10px;font-weight:700;font-size:15px">رسالتك / Your message</p>'
    . '<div style="background:#faf5f7;border-radius:12px;padding:16px;font-size:15px;line-height:1.85" dir="auto">'
    . nl2br(e((string)$r['msg'])) . '</div>'

    . '<p style="margin:20px 0 0;color:#7c6a71;font-size:12.5px" dir="rtl">سنقرأ رسالتك ونرد عليك قريباً على هذا البريد.</p>'
    . '<p style="margin:0;color:#7c6a71;font-size:12.5px">We will read it and reply to you at this address.</p>'
    . em_junk_note();

    return send_mail((string)$r['email'],
        ($ar ? 'وصلتنا رسالتك ' : 'We got your message ') . $r['id'] . ' — ' . ct('appName', 'en'),
        email_shell($inner));
}

/** The owner's answer, sent to whoever wrote in. */
function support_reply_mail(array $r): bool {
    if (($r['email'] ?? '') === '') return false;
    $b = base_url();

    $inner =
      '<p style="margin:0 0 4px;font-size:16px" dir="rtl">رد على رسالتك ✅</p>'
    . '<p style="margin:0 0 20px;font-size:16px">A reply to your message.</p>'

    . '<table style="width:100%;border-collapse:collapse;margin:0 0 20px"><tr>'
    . '<td style="width:6px;background:#8a1538;border-radius:4px 0 0 4px"></td>'
    . '<td style="background:#fdf2f5;border:1px solid #f0d3dc;border-inline-start:0;border-radius:0 12px 12px 0;padding:16px 18px">'
    . '<div style="font-size:11.5px;font-weight:800;letter-spacing:.6px;text-transform:uppercase;color:#8a1538 !important;margin-bottom:8px">'
    . '💬 ردنا &nbsp;·&nbsp; Our reply</div>'
    . '<div style="font-size:16px;line-height:1.85;color:#5c0f26 !important" dir="auto">' . nl2br(e((string)$r['reply'])) . '</div>'
    . '</td></tr></table>'

    . '<p style="margin:0 0 10px;font-weight:700;font-size:14px">رسالتك الأصلية / Your original message</p>'
    . '<div style="background:#faf5f7;border-radius:12px;padding:14px;font-size:14px;line-height:1.8;color:#5d4a51" dir="auto">'
    . nl2br(e((string)$r['msg'])) . '</div>'

    . '<p style="margin:20px 0 0;text-align:center">'
    . em_button($b . '/?support=' . rawurlencode((string)$r['id']),
                'فتح المحادثة &nbsp;·&nbsp; Open the conversation') . '</p>';

    return send_mail((string)$r['email'],
        'رد على رسالتك ' . $r['id'] . ' — Reply to your message',
        email_shell($inner));
}

/* ---------- the price ----------
   Khalid can give one number or a range. A range is the honest answer for a
   car nobody has driven yet, and it is what the market quotes. Records made
   before this existed carry only 'price', and they still read correctly. */

function price_from(array $r): string { return trim((string)($r['price']    ?? '')); }
function price_to(array $r):   string { return trim((string)($r['price_to'] ?? '')); }

function has_price(array $r): bool { return price_from($r) !== ''; }
function is_price_range(array $r): bool {
    return price_from($r) !== '' && price_to($r) !== '' && price_to($r) !== price_from($r);
}

/** digits grouped for reading: 185000 → 185,000 */
function price_num(string $v): string {
    $clean = preg_replace('/[^0-9.]/', '', $v);
    if ($clean === '' || !is_numeric($clean)) return $v;
    return number_format((float)$clean, 0, '.', ',');
}

/** “185,000” or “180,000 – 195,000” — the number only, no currency */
function price_display(array $r): string {
    if (!has_price($r)) return '';
    $a = price_num(price_from($r));
    return is_price_range($r) ? $a . ' – ' . price_num(price_to($r)) : $a;
}

/** the same with the currency, for a subject line or a plain-text row */
function price_line(array $r, string $lang = 'en'): string {
    if (!has_price($r)) return '';
    $cur = $lang === 'ar' ? (string)cfg('currency_ar') : (string)cfg('currency_en');
    return price_display($r) . ' ' . $cur;
}

/* ---------- misc ---------- */

/** Format a stored UTC timestamp in the local timezone from config.php. */
function fmt_dt(?string $iso, string $format = 'd M Y · H:i'): string {
    if (!$iso) return '—';
    try {
        $d = new DateTime($iso);
        $d->setTimezone(new DateTimeZone((string)(cfg('timezone') ?: 'UTC')));
        return $d->format($format);
    } catch (Throwable $e) {
        return (string)$iso;
    }
}

/** Shared login check for admin.php / mailtest.php / archive.php.
    The real work is in admin_auth.php: a hashed password in data/admin.json,
    falling back to the value in config.php on a fresh installation. */
function admin_login_ok(string $user, string $pass): bool {
    require_once APP_ROOT . '/admin_auth.php';
    return admin_auth_check($user, $pass);
}

function car_title(array $r): string {
    return trim(implode(' ', array_filter([
        $r['car_year'] ?? '', $r['car_make'] ?? '', $r['car_class'] ?? '', $r['car_model'] ?? '',
    ], function ($v) { return trim((string)$v) !== ''; })));
}

function base_url(): string {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (int)($_SERVER['SERVER_PORT'] ?? 0) === 443;
    $host  = $_SERVER['HTTP_HOST'] ?? 'thamanmotorak.com';
    $path  = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/api.php')), '/');
    return ($https ? 'https://' : 'http://') . $host . $path;
}

function human_size(int $b): string {
    if ($b >= 1048576) return round($b / 1048576, 1) . ' MB';
    if ($b >= 1024)    return round($b / 1024) . ' KB';
    return $b . ' B';
}

/**
 * Answer the browser now, keep working afterwards.
 *
 * A support message triggers two emails. Sending them before replying makes the
 * visitor stare at a spinner for as long as the mail server takes — and if SMTP
 * is slow that is many seconds for something already safely saved. The reply
 * goes out first; the mail is sent after the connection has been let go.
 */
function respond_and_continue($data): void {
    @ignore_user_abort(true);
    $out = json_encode($data, JSON_UNESCAPED_UNICODE);
    http_response_code(200);
    header('Content-Type: application/json; charset=UTF-8');
    header('Content-Length: ' . strlen((string)$out));
    header('Connection: close');
    echo $out;
    if (function_exists('fastcgi_finish_request')) { fastcgi_finish_request(); return; }
    while (ob_get_level() > 0) @ob_end_flush();
    @flush();
}

function json_out($data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function e(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/* ---------------------------------------------------------------------------
   asset() — the address of a file in assets/ or icons/, stamped with the
   moment that file was last changed.

   Why this exists.  A phone is allowed to keep app.css for days, and it is
   right to let it: the file rarely changes and keeping it makes the site open
   instantly.  The phone decides whether it already has the file by looking at
   the ADDRESS, so as long as the address stays the same it never asks the
   server again — exactly what we want, right up until we change the file.
   Before, the address carried a number typed by hand (app.css?v=10).  Forget
   to raise that number after an upload and every phone keeps yesterday's
   design for a week, with no cure except clearing the cache by hand on each
   device.  That is the "some devices do not show the update" problem, and
   clearing the cache on the SERVER cannot fix it — the old copy is on the
   phone.

   Now the number is the file's own modification time.  Upload a new app.css
   and its address changes by itself; every phone sees an address it has never
   seen, fetches it once, and the update lands everywhere at the same moment.
   Nothing to remember, nothing to bump.
   --------------------------------------------------------------------------- */
function asset(string $path): string {
    $path = ltrim($path, '/');
    $v = @filemtime(APP_ROOT . '/' . $path);
    if ($v === false) $v = 1;               // missing file — still a stable address
    return $path . '?v=' . $v;
}

/* The build number written INSIDE assets/app.css and assets/app.js, read from
   the files that are actually on the server. The page hands these to the
   browser, which compares them with the numbers it really loaded — so the
   "old files" warning is never based on a number typed by hand. */
function declared_build(string $path, string $pattern): int {
    $s = @file_get_contents(APP_ROOT . '/' . $path, false, null, 0, 4096);
    if ($s === false) return 0;                        // file missing entirely
    return preg_match($pattern, $s, $m) ? (int)$m[1] : -1;
}
function css_build(): int { return declared_build('assets/app.css', '/--eyc-css-build\s*:\s*(\d+)/'); }
function js_build(): int  { return declared_build('assets/app.js',  '/__EYC_BUILD\s*=\s*(\d+)/'); }

/* The newest stamp across the three shell files. The service worker uses it as
   its cache name, so a new upload retires the old offline copy as well. */
function asset_build(): string {
    $t = 0;
    foreach (['assets/app.css', 'assets/app.js', 'assets/cars.js'] as $f) {
        $m = @filemtime(APP_ROOT . '/' . $f);
        if ($m !== false && $m > $t) $t = $m;
    }
    return (string)($t ?: 1);
}
