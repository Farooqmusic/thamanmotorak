<?php
/* ============================================================
   api.php — JSON endpoints:  ?do=submit   ?do=status
   ============================================================ */
declare(strict_types=1);
require __DIR__ . '/lib.php';
require_once __DIR__ . '/receipt.php';     // pd_available(), so we only offer a PDF we can build
ensure_dirs();

$do = $_GET['do'] ?? '';

/* ------------------------------------------------------------------ */
/*  CONFIG — everything the mobile app needs to draw itself             */
/*                                                                     */
/*  The app ships with no car database, no translations and no panel    */
/*  geometry. It asks here once per launch and caches the answer, so a  */
/*  word changed in the control panel reaches every phone without a     */
/*  store update. See appapi.php.                                       */
/* ------------------------------------------------------------------ */
if ($do === 'config') {
    require_once __DIR__ . '/appapi.php';
    /* Public and unchanging between edits — let a phone on a weak Qatari
       connection reuse it rather than pull 300 kB of car names again. */
    header('Cache-Control: public, max-age=300');
    json_out(app_config_payload());
}

/* ------------------------------------------------------------------ */
/*  STATUS  — login with ID only, no password                          */
/* ------------------------------------------------------------------ */
if ($do === 'status') {
    $id = strtoupper(trim((string)($_REQUEST['id'] ?? '')));
    if ($id === '') json_out(['ok' => false, 'error' => 'no_id'], 400);

    $r = find_request($id);
    if (!$r) json_out(['ok' => false, 'error' => 'not_found'], 404);

    /* The PDF, reachable with nothing but the 6-character code.

       It used to exist only inside the email — so a customer who mistyped his
       address, or whose mail was filed as spam, had no way to reach his own
       report. The link is signed, and only ever appears once the price is in. */
    $pdf = null;
    if (($r['status'] ?? '') === 'done' && has_price($r) && pd_available()) {
        $pdf = 'receipt.php?id=' . rawurlencode((string)$r['id'])
             . '&k=' . link_token((string)$r['id']) . '&dl=1';
    }

    json_out([
        'ok'      => true,
        'id'      => $r['id'],
        'status'  => $r['status'],                 // review | done
        'price'   => $r['price'] ?? null,
        /* already formatted, so the browser never has to guess how to join
           two numbers or where the thousands separators belong */
        'price_text' => price_display($r),
        'price_range' => is_price_range($r),
        'pdf'     => $pdf,
        'note_ar' => $r['note_ar'] ?? '',
        'note_en' => $r['note_en'] ?? '',
        'car'     => car_title($r),
        'created' => $r['created'],
        'expires' => $r['expires_at'],
        'photos'  => count($r['photos'] ?? []),
        'videos'  => count($r['videos'] ?? []),
    ]);
}

/* ------------------------------------------------------------------ */
/*  SUPPORT STATUS — “what happened to my message?”                    */
/* ------------------------------------------------------------------ */
if ($do === 'support_status') {
    $id = strtoupper(trim((string)($_REQUEST['id'] ?? '')));
    if ($id === '') json_out(['ok' => false, 'error' => 'no_id'], 400);

    $r = find_support($id);
    if (!$r) json_out(['ok' => false, 'error' => 'not_found'], 404);

    json_out([
        'ok'      => true,
        'id'      => $r['id'],
        'kind'    => $r['kind'],
        'kind_ar' => support_kind_label((string)$r['kind'], 'ar'),
        'kind_en' => support_kind_label((string)$r['kind'], 'en'),
        'msg'     => $r['msg'],
        'reply'   => (string)($r['reply'] ?? ''),
        'created' => $r['created'],
        'replied' => (string)($r['replied_at'] ?? ''),
        /* “read” means Khalid has opened it — worth telling the sender */
        'seen'    => !empty($r['read']),
    ]);
}

/* ------------------------------------------------------------------ */
/*  SUPPORT — “something is wrong / I have a suggestion”               */
/* ------------------------------------------------------------------ */
if ($do === 'support') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['ok' => false, 'error' => 'method'], 405);

    /* A bot fills in every field it can find; a person never sees this one.
       It is NOT called “website” any more: that is a real autocomplete token,
       and a browser or password manager will happily fill it in for a genuine
       visitor — whose message was then thrown away while the screen said it
       had been sent. Every rejection is logged now, so nothing is ever
       discarded without a trace. */
    if (trim((string)($_POST['eyc_hp'] ?? '')) !== '') {
        log_line('SUPPORT rejected: honeypot filled (value="'
               . mb_substr(trim((string)$_POST['eyc_hp']), 0, 40) . '")');
        json_out(['ok' => true, 'id' => '']);
    }

    $kind  = (string)($_POST['sup_kind'] ?? 'problem');
    if (!isset(support_kinds()[$kind])) $kind = 'problem';
    $name  = trim((string)($_POST['s_name'] ?? ''));
    $email = trim((string)($_POST['s_email'] ?? ''));
    $phone = trim((string)($_POST['s_phone'] ?? ''));
    $ref   = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)($_POST['s_ref'] ?? '')));
    $msg   = trim((string)($_POST['s_msg'] ?? ''));
    $lang  = ($_POST['lang'] ?? 'ar') === 'en' ? 'en' : 'ar';

    /* one contact route is enough — not everyone has email */
    $hasEmail = filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    $hasPhone = strlen(preg_replace('/[^0-9]/', '', $phone)) >= 7;
    if (!$hasEmail && !$hasPhone) { log_line('SUPPORT rejected: no usable email or phone'); json_out(['ok' => false, 'error' => 'contact'], 422); }
    if (mb_strlen($msg) < 10)     { log_line('SUPPORT rejected: message too short'); json_out(['ok' => false, 'error' => 'short'], 422); }
    if (mb_strlen($msg) > 4000)   $msg = mb_substr($msg, 0, 4000);

    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    if (support_recent($ip, $email) >= 5) { log_line('SUPPORT rejected: rate limit for ' . $ip); json_out(['ok' => false, 'error' => 'too_many'], 429); }

    $row = [
        'id'      => new_support_id(),
        'kind'    => $kind,
        'name'    => mb_substr($name, 0, 120),
        'email'   => $hasEmail ? $email : '',
        'phone'   => mb_substr($phone, 0, 40),
        'ref'     => mb_substr($ref, 0, 6),
        'msg'     => $msg,
        'lang'    => $lang,
        'page'    => mb_substr(trim((string)($_POST['s_page'] ?? '')), 0, 300),
        'agent'   => mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 300),
        'ip'      => $ip,
        'created' => gmdate('c'),
        'read'    => false,
        'reply'   => '',
        'replied_at' => '',
    ];

    /* disk first — a mail problem must never lose what someone wrote */
    try {
        support_write(function (array $rows) use ($row) { $rows[] = $row; return $rows; });
    } catch (Throwable $e) {
        log_line('SUPPORT STORE FAILED :: ' . $e->getMessage());
        json_out(['ok' => false, 'error' => 'storage'], 500);
    }
    log_line('SUPPORT saved ' . $row['id'] . ' (' . $kind . ') from ' . ($email ?: $phone)
           . ' -> ' . SUPPORT_FILE);

    /* Saved. Tell the browser immediately — the two emails below are just
       notifications, and nobody should watch a spinner while they go out. */
    respond_and_continue(['ok' => true, 'id' => $row['id']]);

    /* the message is already safe on disk; a mail failure only affects the alert */
    try {
        $sent = notify_owner_support($row);
        log_line('SUPPORT alert to ' . owner_email() . ' :: ' . ($sent ? 'sent' : 'NOT sent'));
    } catch (Throwable $e) {
        log_line('SUPPORT alert failed :: ' . $e->getMessage());
    }
    /* and a receipt for the visitor, so the reference exists somewhere besides
       the screen he is about to close */
    try {
        if ($row['email'] !== '') {
            log_line('SUPPORT receipt to ' . $row['email'] . ' :: '
                   . (support_ack_mail($row) ? 'sent' : 'NOT sent'));
        }
    } catch (Throwable $e) {
        log_line('SUPPORT receipt failed :: ' . $e->getMessage());
    }
    exit;
}

/* ------------------------------------------------------------------ */
/*  SUBMIT                                                             */
/* ------------------------------------------------------------------ */
if ($do === 'submit') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['ok' => false, 'error' => 'method'], 405);

    $lang  = ($_POST['lang'] ?? 'ar') === 'en' ? 'en' : 'ar';
    $name  = trim((string)($_POST['name'] ?? ''));
    $phone = trim((string)($_POST['phone'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $make  = trim((string)($_POST['car_make'] ?? ''));
    $class = trim((string)($_POST['car_class'] ?? ''));
    $model = trim((string)($_POST['car_model'] ?? ''));
    $year  = trim((string)($_POST['car_year'] ?? ''));
    $km    = trim((string)($_POST['mileage'] ?? ''));
    $reg   = trim((string)($_POST['registration'] ?? ''));
    $vin   = trim((string)($_POST['chassis'] ?? ''));
    $notes = trim((string)($_POST['notes'] ?? ''));
    $keep  = (int)($_POST['retention'] ?? 3);

    if (!in_array($keep, cfg('retention_days'), true)) $keep = (int)cfg('retention_days')[0];

    /* --- step 2: the paint diagram and the three quality questions ---
       cond_from_post() silently drops anything it does not recognise, so a
       hand-made POST can never put junk into the stored record. */
    $cond = cond_from_post($_POST);

    /* --- required fields --- */
    if ($name === '' || $phone === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $make === '' || $class === '' || $year === '') {
        json_out(['ok' => false, 'error' => 'fields'], 422);
    }
    /* model / trim and mileage are required too */
    if ($model === '') json_out(['ok' => false, 'error' => 'car_model'], 422);
    if (preg_replace('/[^0-9]/', '', $km) === '') json_out(['ok' => false, 'error' => 'mileage'], 422);
    if ($cond['paint_status'] === '') json_out(['ok' => false, 'error' => 'paint_status'], 422);
    /* partial or full is part of the answer whenever the car was resprayed */
    if (in_array($cond['paint_status'], ['repaint', 'accident'], true) && $cond['paint_extent'] === '') {
        json_out(['ok' => false, 'error' => 'paint_extent'], 422);
    }

    /* --- collect the named photo slots --- */
    $incoming = [];
    foreach (slots() as $s) {
        $field = 'photo_' . $s['key'];
        if (!isset($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
        $incoming[$s['key']] = $_FILES[$field];
    }
    foreach (slots() as $s) {
        if ($s['req'] && !isset($incoming[$s['key']])) {
            json_out(['ok' => false, 'error' => 'missing_slot', 'slot' => $s['key']], 422);
        }
    }
    if (count($incoming) < (int)cfg('min_photos')) json_out(['ok' => false, 'error' => 'photo_count'], 422);

    $videos = normalise_files($_FILES['videos'] ?? null);
    if (count($videos) > (int)cfg('max_videos')) json_out(['ok' => false, 'error' => 'video_count'], 422);

    $id  = new_id();
    $dir = UPLOAD_DIR . '/' . $id;
    if (!@mkdir($dir, 0755, true)) json_out(['ok' => false, 'error' => 'storage'], 500);

    /* --- save photos under their position name --- */
    $okExt  = ['jpg','jpeg','png','webp','heic','heif'];
    $maxMb  = (int)cfg('max_photo_mb');
    $saved  = [];
    foreach (slots() as $s) {
        if (!isset($incoming[$s['key']])) continue;
        $f   = $incoming[$s['key']];
        $ext = strtolower(pathinfo((string)$f['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $okExt, true))            { rrmdir($dir); json_out(['ok' => false, 'error' => 'photo_type'], 422); }
        if ((int)$f['size'] > $maxMb * 1024 * 1024)   { rrmdir($dir); json_out(['ok' => false, 'error' => 'photo_big'], 422); }

        $fname = str_replace('_', '-', $s['key']) . '.' . $ext;
        $dest  = $dir . '/' . $fname;
        if (!(@move_uploaded_file($f['tmp_name'], $dest) || @rename($f['tmp_name'], $dest) || @copy($f['tmp_name'], $dest))) {
            rrmdir($dir); json_out(['ok' => false, 'error' => 'storage'], 500);
        }
        @chmod($dest, 0644);
        $saved[] = ['slot' => $s['key'], 'file' => $fname, 'size' => (int)$f['size']];
    }

    /* --- save videos --- */
    $savedVideos = [];
    $n = 0;
    foreach ($videos as $v) {
        $ext = strtolower(pathinfo($v['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['mp4','mov','m4v','3gp','webm'], true)) { rrmdir($dir); json_out(['ok' => false, 'error' => 'video_type'], 422); }
        if ($v['size'] > (int)cfg('max_video_mb') * 1024 * 1024)     { rrmdir($dir); json_out(['ok' => false, 'error' => 'video_big'], 422); }
        $n++;
        $fname = 'video-' . $n . '.' . $ext;
        $dest  = $dir . '/' . $fname;
        if (!(@move_uploaded_file($v['tmp'], $dest) || @rename($v['tmp'], $dest) || @copy($v['tmp'], $dest))) {
            rrmdir($dir); json_out(['ok' => false, 'error' => 'storage'], 500);
        }
        @chmod($dest, 0644);
        $savedVideos[] = ['file' => $fname, 'size' => $v['size']];
    }

    $now = time();
    $row = [
        'id'           => $id,
        'status'       => 'review',
        'price'        => null,
        'note_ar'      => '',
        'note_en'      => '',
        'lang'         => $lang,
        'name'         => $name,
        'phone'        => $phone,
        'email'        => $email,
        'car_make'     => $make,
        'car_class'    => $class,
        'car_model'    => $model,
        'car_year'     => $year,
        'mileage'      => $km,
        'registration' => $reg,
        'chassis'      => $vin,
        'notes'        => $notes,
        'condition'    => $cond,
        'retention'    => $keep,
        'photos'       => $saved,
        'videos'       => $savedVideos,
        'created'      => gmdate('c', $now),
        'expires_at'   => gmdate('c', $now + $keep * 86400),
        'ip'           => $_SERVER['REMOTE_ADDR'] ?? '',
        'files_purged' => false,
    ];

    db_write(function (array $rows) use ($row) { $rows[] = $row; return $rows; });

    notify_owner($row);
    notify_customer_received($row);

    json_out(['ok' => true, 'id' => $id, 'expires' => $row['expires_at']]);
}

json_out(['ok' => false, 'error' => 'unknown'], 404);


/* ================================================================== */
/*  helpers                                                            */
/* ================================================================== */

function normalise_files($f): array {
    if (!$f || !isset($f['name'])) return [];
    $out = [];
    foreach ((array)$f['name'] as $i => $n) {
        if (($f['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
        $out[] = ['name' => (string)$n, 'tmp' => $f['tmp_name'][$i], 'size' => (int)$f['size'][$i]];
    }
    return $out;
}

function rrmdir(string $dir): void {
    if (!is_dir($dir)) return;
    foreach (array_diff(scandir($dir) ?: [], ['.', '..']) as $f) {
        $p = $dir . '/' . $f;
        is_dir($p) ? rrmdir($p) : @unlink($p);
    }
    @rmdir($dir);
}

function notify_owner(array $r): void {
    $b    = base_url();
    $gal  = $b . '/gallery.php?id=' . $r['id'] . '&k=' . link_token($r['id']);
    $dir  = UPLOAD_DIR . '/' . $r['id'];

    /* resized copies so the email stays small enough for any inbox */
    $inline = [];
    $px = (int)cfg('email_photo_px'); $q = (int)cfg('email_photo_q');
    foreach ($r['photos'] as $p) {
        $bytes = resized_jpeg($dir . '/' . $p['file'], $px, $q);
        if ($bytes === null) continue;
        $inline[] = ['cid' => 'img' . $p['slot'], 'name' => $p['slot'] . '.jpg', 'bytes' => $bytes, 'type' => 'image/jpeg'];
    }

    $grid = '';
    foreach ($r['photos'] as $p) {
        $has = false;
        foreach ($inline as $i) if ($i['cid'] === 'img' . $p['slot']) $has = true;
        $grid .= '<td style="width:50%;padding:5px;vertical-align:top">'
              . '<div style="border:1px solid #eddfe4;border-radius:10px;overflow:hidden">'
              . ($has
                  ? '<img src="cid:img' . $p['slot'] . '" width="260" style="width:100%;display:block" alt="">'
                  : '<div style="padding:26px;text-align:center;color:#7c6a71;font-size:13px">' . e(slot_label($p['slot'], 'en')) . '</div>')
              . '<div style="padding:7px 10px;font-size:12.5px;color:#2a1119 !important;background:#faf5f7">'
              . e(slot_label($p['slot'], 'ar')) . ' &nbsp;·&nbsp; ' . e(slot_label($p['slot'], 'en'))
              . '</div></div></td>';
    }
    /* two per row */
    $cells = explode('</td>', $grid);
    $rowsHtml = ''; $i = 0; $buf = '';
    foreach ($cells as $c) {
        if (trim($c) === '') continue;
        $buf .= $c . '</td>'; $i++;
        if ($i % 2 === 0) { $rowsHtml .= '<tr>' . $buf . '</tr>'; $buf = ''; }
    }
    if ($buf !== '') $rowsHtml .= '<tr>' . $buf . '<td style="width:50%"></td></tr>';

    $inner =
      '<p style="margin:0 0 14px"><b>طلب تقييم جديد / New valuation request</b></p>'
    . '<table style="width:100%;border-collapse:collapse;font-size:14px">'
    . row_('Request ID', '<b style="font-size:18px;letter-spacing:2px">' . e($r['id']) . '</b>')
    . row_('Customer',   e($r['name']))
    . row_('Phone',      '<a href="https://wa.me/' . e(preg_replace('/[^0-9]/', '', $r['phone'])) . '" style="color:#8a1538;font-weight:700">' . e($r['phone']) . '</a>')
    . row_('Email',      e($r['email']))
    . row_('Car',        '<b>' . e(car_title($r)) . '</b>')
    . row_('Mileage',    e($r['mileage'] ?: '—'))
    . row_('Registration', e($r['registration'] ?: '—'))
    . row_('Chassis / VIN', e($r['chassis'] ?: '—'))
    . row_('Photos',     count($r['photos']) . ' photo(s), ' . count($r['videos']) . ' video(s)')
    . row_('Keep files', $r['retention'] . ' days (until ' . gmdate('d M Y', strtotime($r['expires_at'])) . ')')
    . row_('Notes',      nl2br(e($r['notes'] ?: '—')))
    . cond_owner_rows($r)
    . '</table>'

    . '<p style="margin:22px 0 10px;font-weight:700">الصور / Photos</p>'
    . '<table style="width:100%;border-collapse:collapse">' . $rowsHtml . '</table>'

    . '<p style="margin:22px 0 0">' . em_button($gal, 'عرض كل الصور بالحجم الكامل &nbsp;·&nbsp; Open full-size gallery', true) . '</p>'
    . '<p style="margin:12px 0 0">' . em_button($b . '/admin.php', 'تسعير الطلب &nbsp;·&nbsp; Enter the price') . '</p>';

    send_mail(owner_email(), 'طلب تقييم جديد ' . $r['id'] . ' — ' . car_title($r), email_shell($inner), $inline);
}

function notify_customer_received(array $r): void {
    $b = base_url();

    $inner =
      '<p style="margin:0 0 4px;font-size:16px" dir="rtl">شكراً ' . e($r['name']) . '، استلمنا صور موترك ✅</p>'
    . '<p style="margin:0 0 20px;font-size:16px">Thank you ' . e($r['name']) . ', we have received your car photos.</p>'

    /* ---- the confirmation code ---- */
    . '<div style="background:#fdf2f5;border:1px dashed #e8c3cf;border-radius:14px;padding:20px;text-align:center;margin:0 0 22px">'
    . '<div class="tx-mute" style="color:#7c6a71 !important;font-size:12.5px" dir="rtl">رمز التأكيد — تدخل به بدون كلمة مرور</div>'
    . '<div class="tx-mute" style="color:#7c6a71 !important;font-size:12.5px;margin-bottom:6px">Confirmation code — no password needed</div>'
    . '<div style="font-size:36px;font-weight:800;letter-spacing:9px;color:#8a1538 !important;direction:ltr">' . e($r['id']) . '</div>'
    . '</div>'

    . '<p style="margin:0 0 22px;text-align:center">'
    . em_button($b . '/?id=' . $r['id'], 'تابع حالة طلبك &nbsp;·&nbsp; Check your status') . '</p>'

    /* ---- everything he entered ---- */
    . '<p style="margin:0 0 10px;font-weight:700;font-size:15px">بيانات طلبك / Your submitted details</p>'
    . customer_details_table($r)

    /* ---- what happens next ---- */
    . '<div style="background:#faf5f7;border-radius:12px;padding:16px;margin:22px 0 0">'
    . '<p style="margin:0 0 8px;font-weight:700;font-size:14px">ماذا بعد؟ / What happens next</p>'
    . '<p style="margin:0 0 6px;font-size:13.5px;color:#4a5765" dir="rtl">🔴 ضوء أحمر = طلبك تحت المراجعة الآن.</p>'
    . '<p style="margin:0 0 10px;font-size:13.5px;color:#4a5765">🔴 Red light = your request is under review.</p>'
    . '<p style="margin:0 0 6px;font-size:13.5px;color:#4a5765" dir="rtl">🟢 ضوء أخضر = السعر جاهز، وسيصلك بريد آخر فوراً.</p>'
    . '<p style="margin:0;font-size:13.5px;color:#4a5765">🟢 Green light = the price is ready, and we email you straight away.</p>'
    . '</div>'

    . em_junk_note()

    /* only the contact details the control panel actually holds */
    . public_contact_html()

    . '<p style="margin:18px 0 0;color:#7c6a71;font-size:12.5px" dir="rtl">تُحذف صورك وفيديوهاتك تلقائياً بعد ' . (int)$r['retention'] . ' أيام. لا نشاركها مع أي جهة أخرى.</p>'
    . '<p style="margin:0;color:#7c6a71;font-size:12.5px">Your photos and videos are deleted automatically after ' . (int)$r['retention'] . ' days. We never share them.</p>';

    $html = email_shell($inner);
    send_mail($r['email'], 'رمز التأكيد ' . $r['id'] . ' — Your confirmation code', $html);

    // keep a copy for Khalid's records if he wants one
    if (cfg('copy_owner')) {
        send_mail(owner_email(), '[نسخة/copy] ' . $r['id'] . ' — customer confirmation', $html);
    }
}

/** A plain, light message — no images, so it is the easiest kind to deliver. */
function notify_owner_support(array $r): bool {
    $wa = preg_replace('/[^0-9]/', '', (string)$r['phone']);
    $inner =
      '<p style="margin:0 0 14px"><b>' . e(support_kind_label((string)$r['kind'], 'ar'))
    . ' / ' . e(support_kind_label((string)$r['kind'], 'en')) . '</b></p>'
    . '<table style="width:100%;border-collapse:collapse;font-size:14px">'
    . row_('Reference', '<b style="letter-spacing:1px">' . e($r['id']) . '</b>')
    . row_('Name',  e($r['name'] ?: '—'))
    . row_('Email', $r['email'] !== '' ? '<a href="mailto:' . e($r['email']) . '" style="color:#8a1538;font-weight:700">' . e($r['email']) . '</a>' : '—')
    . row_('Phone', $wa !== '' ? '<a href="https://wa.me/' . e($wa) . '" style="color:#8a1538;font-weight:700">' . e($r['phone']) . '</a>' : '—')
    . ($r['ref'] !== '' ? row_('About request', '<b>' . e($r['ref']) . '</b>') : '')
    . row_('Language', e($r['lang']))
    . row_('Page', e($r['page'] ?: '—'))
    . row_('Browser', '<span style="font-size:11.5px;color:#7c6a71">' . e($r['agent'] ?: '—') . '</span>')
    . '</table>'
    . '<p style="margin:20px 0 8px;font-weight:700">الرسالة / Message</p>'
    . '<div style="background:#faf5f7;border-radius:12px;padding:16px;font-size:15px;line-height:1.85" dir="auto">'
    . nl2br(e($r['msg'])) . '</div>'
    . '<p style="margin:18px 0 0;font-size:12.5px;color:#7c6a71">'
    . 'كل الرسائل محفوظة أيضاً في لوحة التحكم · every message is also saved in the control panel.</p>';

    return send_mail(owner_email(),
        'رسالة دعم ' . $r['id'] . ' — ' . support_kind_label((string)$r['kind'], 'en'),
        email_shell($inner));
}

/** the condition answers, for the owner's copy of a new request */
function cond_owner_rows(array $r): string {
    $h = '';
    foreach (cond_summary_rows($r) as $x) {
        $h .= row_(e($x['en']), '<b>' . e($x['v_en'] !== '' ? $x['v_en'] : $x['v_ar']) . '</b>');
    }
    return $h;
}

function row_(string $k, string $v): string {
    return '<tr><td style="padding:7px 10px 7px 0;color:#7c6a71;white-space:nowrap;vertical-align:top">' . $k . '</td>'
         . '<td style="padding:7px 0;color:#2a1119">' . $v . '</td></tr>';
}
