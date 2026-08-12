<?php
/* ============================================================
   admin.php — Khalid's panel: review requests, enter the price.
   ============================================================ */
declare(strict_types=1);
require __DIR__ . '/lib.php';
require_once __DIR__ . '/receipt.php';
require_once __DIR__ . '/admin_content.php';
require_once __DIR__ . '/admin_auth.php';
ensure_dirs();
session_start();

$C   = cfg();
$msg = '';
$bad = '';
$AL  = admin_lang();                       // panel language: ar | en
$page = (string)($_GET['page'] ?? 'requests');

/* ---------------- auth ---------------- */
if (isset($_GET['logout'])) { $_SESSION = []; session_destroy(); header('Location: admin.php'); exit; }

if (($_POST['action'] ?? '') === 'login') {
    if (admin_login_ok((string)($_POST['username'] ?? ''), (string)($_POST['password'] ?? ''))) {
        session_regenerate_id(true);
        $_SESSION['eyc_admin'] = true;
        header('Location: admin.php'); exit;
    }
    $bad = A('كلمة المرور أو اسم المستخدم غير صحيح', 'Wrong username or password');
}

/* ---------------- signed out: login, forgot, reset ---------------- */
if (empty($_SESSION['eyc_admin'])) {

    /* "send me a reset link" */
    if (($_POST['action'] ?? '') === 'forgot') {
        $ok = admin_start_reset($msg, $bad);
        admin_login_screen($ok ? 'login' : 'forgot', $msg, $bad);
        exit;
    }

    /* the new password typed on the reset screen */
    if (($_POST['action'] ?? '') === 'reset_pw') {
        $tok = (string)($_POST['token'] ?? '');
        $p1  = (string)($_POST['pw1'] ?? '');
        $p2  = (string)($_POST['pw2'] ?? '');

        if (!admin_reset_token_ok($tok)) {
            admin_login_screen('login', '', A('⌛ انتهت صلاحية الرابط أو سبق استخدامه. اطلب رابطاً جديداً.',
                                              '⌛ That link has expired or was already used. Ask for a new one.'));
            exit;
        }
        if ($p1 !== $p2) {
            admin_login_screen('reset', '', A('❌ كلمتا المرور غير متطابقتين.', '❌ The two passwords do not match.'), $tok);
            exit;
        }
        if (mb_strlen($p1) < ADMIN_MIN_PW) {
            admin_login_screen('reset', '', A('❌ الحد الأدنى ' . ADMIN_MIN_PW . ' خانات.',
                                              '❌ The minimum is ' . ADMIN_MIN_PW . ' characters.'), $tok);
            exit;
        }
        if (admin_complete_reset($tok, $p1)) {
            admin_login_screen('login', A('✅ تم تغيير كلمة المرور. سجّل الدخول بها الآن.',
                                          '✅ The password was changed. Sign in with it now.'), '');
            exit;
        }
        admin_login_screen('reset', '', A('❌ تعذّر الحفظ — تأكد أن مجلد data قابل للكتابة (755).',
                                          '❌ Could not save — make sure the data folder is writable (755).'), $tok);
        exit;
    }

    /* the link from the email was opened */
    $tok = (string)($_GET['reset'] ?? '');
    if ($tok !== '') {
        if (admin_reset_token_ok($tok)) { admin_login_screen('reset', '', '', $tok); exit; }
        admin_login_screen('login', '', A('⌛ انتهت صلاحية الرابط أو سبق استخدامه. اطلب رابطاً جديداً.',
                                          '⌛ That link has expired or was already used. Ask for a new one.'));
        exit;
    }

    if (isset($_GET['forgot'])) { admin_login_screen('forgot', $msg, $bad); exit; }

    /* A POST that arrives without a live session means the login timed out while the
       page was open. Say so — otherwise the click looks like it simply did nothing. */
    if (!empty($_POST) && ($_POST['action'] ?? '') !== 'login' && $bad === '') {
        $bad = A('⚠️ انتهت الجلسة ولم يُحفظ التغيير. سجّل الدخول وأعد المحاولة.',
                 '⚠️ Your session expired, so nothing was saved. Sign in and try again.');
    }

    admin_login_screen('login', $msg, $bad);
    exit;
}

/* the banner left behind by the redirect above */
if (!empty($_SESSION['eyc_flash']) && is_array($_SESSION['eyc_flash'])) {
    $msg = (string)($_SESSION['eyc_flash']['msg'] ?? '');
    $bad = (string)($_SESSION['eyc_flash']['bad'] ?? '');
    unset($_SESSION['eyc_flash']);
}

/* ---------------- control-panel: site content & security ---------------- */
admin_content_post($msg, $bad);
admin_security_post($msg, $bad);

/* ---------------- actions ---------------- */
if (in_array(($_POST['action'] ?? ''), ['save', 'resend'], true)) {
  @set_time_limit(180);
  @ignore_user_abort(true);
  try {
    $id      = strtoupper(trim((string)($_POST['id'] ?? '')));
    $resend  = ($_POST['action'] === 'resend');
    $wasDone = false;          // was this request ALREADY priced before this save?
    $price  = trim((string)($_POST['price'] ?? ''));
    $priceTo = trim((string)($_POST['price_to'] ?? ''));
    /* a range typed the wrong way round is a slip, not an instruction */
    if ($price !== '' && $priceTo !== '' && is_numeric($price) && is_numeric($priceTo) && (float)$priceTo < (float)$price) {
        [$price, $priceTo] = [$priceTo, $price];
    }
    $noteAr = trim((string)($_POST['note_ar'] ?? ''));
    $noteEn = trim((string)($_POST['note_en'] ?? ''));
    $mark   = ($_POST['mark'] ?? '') === 'done';

    if ($id === '') throw new RuntimeException('no request id was posted');

    if ($resend) {
        $updated = find_request($id);
        if (!$updated) throw new RuntimeException("request $id not found");
        if (($updated['status'] ?? '') !== 'done') throw new RuntimeException('this request is not marked done yet');
    } elseif ($mark && $price === '') {
        /* pressing "Save & send" with an empty price used to do nothing at all */
        $bad = A('⚠️ أدخل السعر أولاً ثم اضغط «حفظ وإرسال».', '⚠️ Enter the price first, then press “Save &amp; send”.');
        $updated = null;
    } else {
        $found   = false;
        $updated = null;
        db_write(function (array $rows) use ($id, $price, $priceTo, $noteAr, $noteEn, $mark, &$updated, &$found, &$wasDone) {
            foreach ($rows as &$r) {
                if (strtoupper((string)($r['id'] ?? '')) === $id) {
                    $found   = true;
                    $wasDone = (($r['status'] ?? '') === 'done');
                    $r['price']    = $price   !== '' ? $price   : null;
                    $r['price_to'] = $priceTo !== '' ? $priceTo : '';
                    $r['note_ar'] = $noteAr;
                    $r['note_en'] = $noteEn;
                    $r['status']  = $mark ? 'done' : 'review';
                    if ($mark) $r['done_at'] = gmdate('c');
                    $updated = $r;
                }
            }
            unset($r);
            return $rows;
        });

        if (!$found) throw new RuntimeException("request $id was not found in data/requests.json");

        /* read it back from disk — proves the write really landed */
        $check = find_request($id);
        if (!$check || ($check['status'] ?? '') !== ($mark ? 'done' : 'review')) {
            throw new RuntimeException('the change could not be written to data/requests.json — check the folder permissions (data/ must be writable, 755)');
        }
        log_line('ADMIN save ' . $id . ' -> ' . $check['status'] . ' price=' . price_display($check));
        $msg = $mark
            ? A('✅ تم الحفظ — الحالة الآن «جاهز» والضوء أخضر.', '✅ Saved: the status is now DONE and the light is green.')
            : A('✅ تم الحفظ كمسودة — ما زال تحت المراجعة.', '✅ Saved as draft (still under review).');
    }

    /* Email the customer only when the light actually turns green, or when
       Resend is pressed on purpose.

       It used to fire on every save of a finished request — so correcting a
       typo, or simply reloading this page after saving, sent the customer the
       whole “your valuation is ready” email again. That is why the same
       request arrived twice. */
    if ($updated && ($updated['status'] ?? '') === 'done' && !$resend && $wasDone) {
        $msg .= ' ' . A('(لم يُعد إرسال البريد — استخدم «إعادة إرسال بريد النتيجة» إذا أردت ذلك.)',
                        '(No email was re-sent — use “Resend the result email” if you want that.)');
    }
    if ($updated && ($updated['status'] ?? '') === 'done' && ($resend || !$wasDone)) {
        clearstatcache();
        $before = (int)(@filesize(DATA_DIR . '/log.txt') ?: 0);
        $sent   = false;
        try {
            $sent = notify_customer_done($updated);
        } catch (Throwable $me) {
            log_line('MAIL EXCEPTION ' . $me->getMessage());
        }
        clearstatcache();
        $mailLog = trim(substr((string)@file_get_contents(DATA_DIR . '/log.txt'), $before));
        $msg .= $sent
            ? ' ' . A('📧 وأُرسل البريد إلى ', '📧 Emailed the customer at ') . e((string)$updated['email'])
            : '';
        if (!$sent) {
            $bad = A('⚠️ الحالة حُفظت، لكن البريد لم يُرسل.', '⚠️ The status was saved, but the email did NOT go out.')
                 . '<br><span style="font-size:12px;opacity:.85">' . nl2br(e($mailLog ?: 'no detail was logged')) . '</span>'
                 . '<br><a href="mailtest.php" style="color:#a52c2c;font-weight:700">' . e(A('فتح فحص البريد →', 'Open mail diagnostics →')) . '</a>';
        }
    }
  } catch (Throwable $ex) {
    $bad = '❌ ' . e($ex->getMessage());
    log_line('ADMIN ERROR ' . $ex->getMessage());
  }

  /* Post / Redirect / Get. Without it, pressing reload after “Save & send”
     repeats the whole POST — which is the second way the customer ended up
     with two identical emails. The banner is carried across in the session. */
  $_SESSION['eyc_flash'] = ['msg' => $msg, 'bad' => $bad];
  header('Location: admin.php?' . http_build_query(array_filter([
      'open'  => $id,
      'alang' => (string)($_GET['alang'] ?? ''),
  ], 'strlen')));
  exit;
}

if (($_POST['action'] ?? '') === 'purge') {
    $id  = strtoupper(preg_replace('/[^A-Z0-9]/i', '', (string)($_POST['id'] ?? '')));
    $dir = UPLOAD_DIR . '/' . $id;
    $n = 0;
    if (is_dir($dir)) {
        foreach (array_diff(scandir($dir) ?: [], ['.', '..']) as $f) { if (@unlink($dir . '/' . $f)) $n++; }
        @rmdir($dir);
    }
    db_write(function (array $rows) use ($id) {
        foreach ($rows as &$r) {
            if (strtoupper((string)($r['id'] ?? '')) === $id) { $r['files_purged'] = true; $r['purged_at'] = gmdate('c'); }
        }
        unset($r);
        return $rows;
    });
    log_line('ADMIN purge ' . $id . ' (' . $n . ' files)');
    $msg = A('🗑️ حُذفت الصور والسجل محفوظ في الأرشيف — ', '🗑️ Photos deleted, the record stays in the Archive — ') . $n . A(' ملف.', ' file(s).');
}

if (($_POST['action'] ?? '') === 'sup_reply') {
    $sid   = (string)($_POST['sid'] ?? '');
    $reply = trim((string)($_POST['reply'] ?? ''));
    $after = null;
    if ($reply === '') {
        $bad = A('اكتب الرد أولاً.', 'Write the reply first.');
    } else {
        support_write(function (array $rows) use ($sid, $reply, &$after) {
            foreach ($rows as &$r) {
                if ((string)($r['id'] ?? '') === $sid) {
                    $r['reply']      = $reply;
                    $r['replied_at'] = gmdate('c');
                    $r['read']       = true;
                    $after = $r;
                }
            }
            unset($r);
            return $rows;
        });
        if ($after) {
            $sent = false;
            try { $sent = support_reply_mail($after); } catch (Throwable $e) { log_line('SUPPORT reply mail ' . $e->getMessage()); }
            $msg = A('✅ حُفظ الرد.', '✅ Reply saved.')
                 . ' ' . ($sent
                    ? A('📧 وأُرسل إلى ', '📧 Emailed to ') . e((string)$after['email'])
                    : A('يمكن للمُرسِل رؤيته برقم المتابعة.', 'The sender can read it with their reference number.'));
        }
    }
    $page = 'support';
}

if (in_array(($_POST['action'] ?? ''), ['sup_read', 'sup_del'], true)) {
    $sid = (string)($_POST['sid'] ?? '');
    $del = ($_POST['action'] === 'sup_del');
    support_write(function (array $rows) use ($sid, $del) {
        $out = [];
        foreach ($rows as $r) {
            if ((string)($r['id'] ?? '') === $sid) {
                if ($del) continue;
                $r['read'] = !($r['read'] ?? false);
            }
            $out[] = $r;
        }
        return $out;
    });
    $msg = $del ? A('🗑️ حُذفت الرسالة.', '🗑️ Message deleted.')
                : A('✅ تم تحديث حالة الرسالة.', '✅ Message updated.');
    $page = 'support';
}

if (($_POST['action'] ?? '') === 'delete') {
    $id = strtoupper(trim((string)($_POST['id'] ?? '')));
    db_write(function (array $rows) use ($id) {
        return array_values(array_filter($rows, function ($r) use ($id) { return strtoupper($r['id']) !== $id; }));
    });
    $dir = UPLOAD_DIR . '/' . preg_replace('/[^A-Z0-9]/', '', $id);
    if (is_dir($dir)) { foreach (array_diff(scandir($dir) ?: [], ['.','..']) as $f) @unlink($dir . '/' . $f); @rmdir($dir); }
    $msg = A('تم حذف الطلب.', 'Request deleted.');
    $_GET['open'] = null;
}

function notify_customer_done(array $r): bool {
    $C    = cfg();
    $base = base_url();

    $inner =
      '<p style="margin:0 0 4px;font-size:16px" dir="rtl">تم تقييم موترك ✅</p>'
    . '<p style="margin:0 0 20px;font-size:16px">Your car valuation is ready.</p>'

    /* ---- the price ---- */
    . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 20px">'
    . '<tr><td bgcolor="#8a1538" align="center" style="background:#8a1538;border-radius:14px;padding:22px">'
    . '<div class="on-brand" style="color:#f0cdd8 !important;font-size:13px" dir="rtl">السعر التقديري لموترك</div>'
    . '<div class="on-brand" style="color:#e6bdcb !important;font-size:13px;margin-bottom:6px">Estimated price for your car</div>'
    . '<div class="on-brand" style="color:#ffffff !important;font-size:'
    . (is_price_range($r) ? '27' : '34') . 'px;font-weight:800;direction:ltr;line-height:1.25">'
    . e(price_display($r)) . ' <span style="color:#f3d98a !important;font-size:18px;font-weight:600">' . e($C['currency_en']) . '</span></div>'
    . '<div class="on-brand" style="color:#eec3d0 !important;font-size:14px;direction:rtl;margin-top:2px">' . e($C['currency_ar']) . '</div>'
    . '</td></tr></table>'

    /* ---- Khalid's notes ---- */
    . (($r['note_ar'] || $r['note_en'])
        ? '<table style="width:100%;border-collapse:collapse;margin:0 0 20px"><tr>'
          . '<td style="width:6px;background:#8a1538;border-radius:4px 0 0 4px"></td>'
          . '<td style="background:#fdf2f5;border:1px solid #f0d3dc;border-inline-start:0;border-radius:0 12px 12px 0;padding:14px 18px">'
          . '<div style="font-size:11.5px;font-weight:800;letter-spacing:.6px;text-transform:uppercase;color:#8a1538 !important;margin-bottom:6px">'
          . '💬 ملاحظة من الفريق &nbsp;·&nbsp; Note from our team</div>'
          . ($r['note_ar'] ? '<p dir="rtl" style="margin:0 0 6px;font-size:16px;font-weight:700;color:#5c0f26 !important;line-height:1.75">' . nl2br(e($r['note_ar'])) . '</p>' : '')
          . ($r['note_en'] ? '<p style="margin:0;font-size:16px;font-weight:700;color:#5c0f26 !important;line-height:1.75">' . nl2br(e($r['note_en'])) . '</p>' : '')
          . '</td></tr></table>'
        : '')

    . '<p style="margin:0 0 10px;text-align:center">'
    . em_button($base . '/?id=' . $r['id'], 'عرض النتيجة &nbsp;·&nbsp; View the result', true) . '</p>'
    . '<p style="margin:0 0 22px;text-align:center;font-size:13.5px;color:#7b8794">'
    . '📄 تقرير التقييم بصيغة PDF مرفق مع هذا البريد &nbsp;·&nbsp; the full valuation report is attached as a PDF<br>'
    . '<a href="' . e($base . '/receipt.php?id=' . $r['id'] . '&k=' . link_token((string)$r['id']) . '&dl=1')
    . '" style="color:#8a1538;font-weight:700">تنزيل التقرير / download it again</a></p>'

    /* ---- full record of what was valued ---- */
    . '<p style="margin:0 0 10px;font-weight:700;font-size:15px">السيارة التي تم تقييمها / The car we valued</p>'
    . customer_details_table($r)

    . '<div style="background:#faf6f7;border-radius:12px;padding:16px;margin:22px 0 0">'
    . '<p style="margin:0 0 6px;font-size:13.5px;color:#4a3a40" dir="rtl">هذا سعر تقديري بناءً على الصور والبيانات المرسلة، وقد يختلف بعد المعاينة على الطبيعة.</p>'
    . '<p style="margin:0 0 10px;font-size:13.5px;color:#4a3a40">This is an estimate based on the photos and details you sent; it may change after a physical inspection.</p>'
    /* Only what the control panel actually holds. Clearing the phone field in
       📇 Contact details removes the number from this email as well — nothing
       here falls back to a number buried in config.php. */
    . public_contact_html()
    . '</div>'
    . em_junk_note();

    /* the 2-page PDF report */
    $attach = [];
    try {
        $pdf = build_receipt_pdf($r);
        if ($pdf !== null) {
            $attach[] = ['name' => receipt_filename($r), 'bytes' => $pdf, 'type' => 'application/pdf'];
        } else {
            log_line('PDF skipped for ' . $r['id'] . ' — GD/FreeType or the fonts folder is missing');
        }
    } catch (Throwable $pe) {
        log_line('PDF ERROR ' . $r['id'] . ' :: ' . $pe->getMessage());
    }

    return send_mail((string)$r['email'],
        /* No amount in the subject. “… is ready 180,000 QAR” reads like an
           offer, and money in a subject line is one of the loudest content
           signals there is. The figure is in the message, where it belongs. */
        'تقييم موترك جاهز ' . $r['id'] . ' — Your valuation is ready',
        email_shell($inner), [], $attach);
}

/* ---------------- data ---------------- */
$rows = db_read();
usort($rows, function ($a, $b) { return strcmp($b['created'], $a['created']); });
$open = null;
if (!empty($_GET['open'])) {
    foreach ($rows as $r) if (strtoupper($r['id']) === strtoupper($_GET['open'])) $open = $r;
}
$pending = count(array_filter($rows, function ($r) { return $r['status'] === 'review'; }));
?>
<!doctype html><html lang="<?= e($AL) ?>" dir="<?= e(admin_dir()) ?>"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="robots" content="noindex,nofollow"><meta name="theme-color" content="#8a1538">
<title><?= e(A('لوحة التحكم', 'Admin')) ?> — <?= e(ct('appName', $AL)) ?></title>
<link rel="stylesheet" href="<?= asset('assets/app.css') ?>">
<script src="<?= asset('assets/theme.js') ?>"></script>
<style>
  .chip{display:inline-block;padding:3px 10px;border-radius:99px;font-size:11.5px;font-weight:700}
  .chip.r{background:rgba(214,59,59,.12);color:#d63b3b}
  .chip.g{background:rgba(26,155,90,.12);color:#1a9b5a}
  .item{display:block;text-decoration:none;color:inherit;border-bottom:1px solid var(--line);padding:13px 0}
  .item:last-child{border-bottom:0}
  .item h4{margin:0 0 3px;font-size:15.5px}
  .item p{margin:0;font-size:12.5px;color:var(--muted)}
  .grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(104px,1fr));gap:8px;margin-top:12px}
  .grid a{display:block;aspect-ratio:1/1;border-radius:11px;overflow:hidden;background:var(--surface-3)}
  .grid img,.grid video{width:100%;height:100%;object-fit:cover;display:block}
  .ok{background:rgba(26,155,90,.1);color:#127a45;padding:11px 14px;border-radius:11px;font-size:14px;margin-bottom:14px;line-height:1.7}
  .badbox{background:rgba(214,59,59,.1);color:#a52c2c;padding:11px 14px;border-radius:11px;font-size:14px;margin-bottom:14px;line-height:1.7}
  .app{padding-bottom:24px}

  /* ---- control-panel navigation ---- */
  .pnav{display:flex;flex-wrap:wrap;gap:8px;margin:0 0 16px}
  .pnav a{display:block;flex:1 1 132px;text-decoration:none;color:inherit;
          background:var(--card);border:1px solid var(--line);border-radius:13px;
          padding:12px 13px;line-height:1.35}
  .pnav a.on{border-color:var(--brand);box-shadow:inset 0 0 0 1px var(--brand)}
  .pnav a b{display:block;font-size:14px}
  .pnav a span{display:block;font-size:11.5px;color:var(--muted);margin-top:2px}

  /* ---- the content editor ---- */
  .ctabs{display:flex;flex-wrap:wrap;gap:6px;margin:0 0 18px}
  .ctab{display:inline-block;padding:7px 12px;border-radius:99px;font-size:12.5px;font-weight:700;
        text-decoration:none;color:var(--muted);background:var(--surface-3)}
  .ctab.on{background:var(--brand);color:#fff}
  .cfield{border-top:1px solid var(--line);padding:14px 0 4px}
  .cfield:first-of-type{border-top:0}
  .clab{display:block;font-size:13.5px;font-weight:700;margin-bottom:8px}
  .ckey{font-size:10.5px;font-weight:400;color:var(--muted);opacity:.75;direction:ltr;display:inline-block}
  .cpair{display:flex;align-items:flex-start;gap:9px;margin-bottom:8px}
  .ctag{flex:0 0 84px;font-size:11px;color:var(--muted);padding-top:11px;line-height:1.3}
  .cfield select{
     width:100%;padding:10px 12px;border:1px solid var(--line);border-radius:10px;
     font-size:14px;font-family:inherit;color:var(--ink);background:var(--surface-2)}
  .cfield input,.cfield textarea{
     flex:1;width:100%;padding:10px 12px;border:1px solid var(--line);border-radius:10px;
     font-size:14px;font-family:inherit;color:var(--ink);background:var(--surface-2)}
  .cfield textarea{line-height:1.8;resize:vertical}
  .cfield input:focus,.cfield textarea:focus{border-color:var(--brand);background:var(--card);outline:none}
  .chelp{background:var(--surface-3);border-radius:11px;padding:12px 14px;font-size:12.5px;
         line-height:1.9;margin:0 0 16px}
  @media (max-width:560px){ .cpair{display:block} .ctag{padding:0 0 3px} }
</style></head>
<body><div class="app">

<header class="top">
  <div class="brand"><div class="mark">ث</div><div class="txt">
    <h1><?= e(A('لوحة التحكم', 'Admin')) ?></h1>
    <p><?= e(ct('appName', $AL)) ?></p></div></div>
  <button class="iconbtn" id="themeBtn" type="button" aria-label="Light / dark">🌙</button>
  <a class="langbtn" href="<?= e(admin_url(['alang' => $AL === 'ar' ? 'en' : 'ar'])) ?>"
     style="text-decoration:none;margin-inline-end:6px"><?= $AL === 'ar' ? 'English' : 'العربية' ?></a>
  <a class="langbtn" href="?logout=1" style="text-decoration:none"><?= e(A('خروج', 'Logout')) ?></a>
</header>

<main>

<nav class="pnav">
  <a href="admin.php" class="<?= $page === 'requests' ? 'on' : '' ?>">
    <b>📥 <?= e(A('الطلبات', 'Requests')) ?></b>
    <span><?= (int)$pending ?> <?= e(A('تحت المراجعة', 'under review')) ?> · <?= count($rows) ?> <?= e(A('الإجمالي', 'total')) ?></span></a>
  <a href="<?= e(admin_url(['page' => 'content', 'g' => 'brand', 'open' => null])) ?>" class="<?= $page === 'content' ? 'on' : '' ?>">
    <b>✏️ <?= e(A('نصوص الموقع', 'Site content')) ?></b>
    <span><?= e(A('كل الكلمات والروابط', 'Every word and link')) ?></span></a>
  <a href="<?= e(admin_url(['page' => 'security', 'g' => null, 'open' => null])) ?>" class="<?= $page === 'security' ? 'on' : '' ?>">
    <b>🔐 <?= e(A('الأمان', 'Security')) ?></b>
    <span><?= e(A('اسم المستخدم وكلمة المرور', 'Username & password')) ?></span></a>
  <a href="<?= e(admin_url(['page' => 'support', 'g' => null, 'open' => null])) ?>" class="<?= $page === 'support' ? 'on' : '' ?>">
    <b>💬 <?= e(A('رسائل الدعم', 'Support inbox')) ?></b>
    <span><?php $su = support_unread(); ?><?= $su > 0
      ? '<b style="color:#d63b3b">' . $su . ' ' . e(A('جديدة', 'new')) . '</b>'
      : e(A('لا جديد', 'nothing new')) ?> · <?= count(support_read()) ?> <?= e(A('الإجمالي', 'total')) ?></span></a>
  <a href="archive.php"><b>🗂️ <?= e(A('الأرشيف', 'Archive')) ?></b>
    <span><?= e(A('السجل الكامل', 'Full record')) ?></span></a>
  <a href="mailtest.php"><b>📧 <?= e(A('فحص البريد', 'Mail check')) ?></b>
    <span><?= e(A('اختبار الإرسال', 'Test sending')) ?></span></a>
  <a href="dnscheck.php"><b>🛡️ <?= e(A('فحص Junk / DNS', 'Junk / DNS check')) ?></b>
    <span><?= e(A('لماذا يذهب البريد إلى Junk', 'Why mail lands in Junk')) ?></span></a>
  <a href="index.php" target="_blank" rel="noopener"><b>🌐 <?= e(A('عرض الموقع', 'View the site')) ?></b>
    <span><?= e(cv('websiteName')) ?></span></a>
</nav>

<?php if ($msg): ?><div class="ok"><?= $msg ?></div><?php endif; ?>
<?php if ($bad): ?><div class="badbox"><?= $bad ?></div><?php endif; ?>

<?php
/* --------------------------------------------------------------------
   The mail-health warning is a developer's message, not the owner's.
   Khalid opens this panel to price cars; a red technical alert about
   DKIM and DMARC only frightens him about something he cannot fix.
   So it is OFF here by default and lives on the two diagnostics pages
   he never opens. Turn 'tech_banner' on in config.php while debugging.
   -------------------------------------------------------------------- */
$mhHtml = cfg('tech_banner') ? mail_health_html($AL === 'ar') : '';
if ($mhHtml !== ''): ?>
  <div class="badbox">
    <?= $mhHtml ?>
    <br><a href="mailtest.php" style="color:#a52c2c;font-weight:700"><?= e(A('افحص إعدادات SMTP →', 'Check the SMTP settings →')) ?></a>
  </div>
<?php endif; ?>

<?php if ($page === 'content'): ?>
  <?php admin_content_page((string)($_GET['g'] ?? 'brand')); ?>

<?php elseif ($page === 'support'): ?>
  <?php $sup = array_reverse(support_read()); ?>
  <div class="card">
    <h2>💬 <?= e(A('رسائل الدعم', 'Support inbox')) ?></h2>
    <p class="sub"><?= e(A('ما يكتبه الزوار من صفحة «الدعم». كل رسالة محفوظة هنا حتى لو تعطّل البريد.',
                           'What visitors write from the Support page. Every message is stored here even if email fails.')) ?></p>
  </div>

  <?php if (!$sup): ?>
    <div class="card"><p class="sub" style="margin:0"><?= e(A('لا توجد رسائل بعد.', 'No messages yet.')) ?></p></div>
  <?php endif; ?>

  <?php foreach ($sup as $m): $wa = preg_replace('/[^0-9]/', '', (string)($m['phone'] ?? '')); ?>
  <div class="card" style="<?= empty($m['read']) ? 'border-color:var(--brand)' : '' ?>">
    <h2 style="font-size:16px;margin-bottom:4px">
      <span class="chip <?= empty($m['read']) ? 'r' : 'g' ?>"><?= e(support_kind_label((string)$m['kind'], $AL)) ?></span>
      <span style="font-size:12.5px;color:var(--muted);letter-spacing:1px"><?= e($m['id']) ?></span>
    </h2>
    <?php /* the date is isolated, not re-aligned: “09 Aug 2026 · 22:32” was
             coming out as “Aug 2026 · 22:32 09” inside the RTL card */ ?>
    <p class="sub" style="margin:0 0 10px"><span dir="ltr"><?= e(fmt_dt($m['created'] ?? null)) ?></span></p>

    <p style="white-space:pre-line;font-size:15px;line-height:1.85;margin:0 0 14px" dir="auto"><?= e($m['msg']) ?></p>

    <table class="kv">
      <?php if (($m['name'] ?? '') !== ''): ?><tr><td><?= e(A('الاسم','Name')) ?></td><td><?= e($m['name']) ?></td></tr><?php endif; ?>
      <?php if (($m['email'] ?? '') !== ''): ?><tr><td><?= e(A('البريد','Email')) ?></td><td><a class="ltr" href="mailto:<?= e($m['email']) ?>" style="color:var(--brand);font-weight:700"><?= e($m['email']) ?></a></td></tr><?php endif; ?>
      <?php if ($wa !== ''): ?><tr><td><?= e(A('الجوال','Mobile')) ?></td><td><a class="ltr" href="https://wa.me/<?= e($wa) ?>" style="color:var(--brand);font-weight:700"><?= e($m['phone']) ?></a></td></tr><?php endif; ?>
      <?php if (($m['ref'] ?? '') !== ''): $known = find_request((string)$m['ref']); ?>
      <tr><td><?= e(A('عن الطلب','About request')) ?></td><td>
        <?php if ($known): ?>
          <a class="ltr" href="<?= e(admin_url(['page'=>'requests','open'=>$m['ref']])) ?>" style="color:var(--brand);font-weight:700"><?= e($m['ref']) ?></a>
        <?php else: ?>
          <span class="ltr"><?= e($m['ref']) ?></span>
          <span style="color:var(--muted);font-size:12px"><?= e(A('— لا يوجد طلب بهذا الرقم', '— no request with this number')) ?></span>
        <?php endif; ?>
      </td></tr>
      <?php endif; ?>
    </table>

    <?php if (($m['agent'] ?? '') !== '' || ($m['page'] ?? '') !== ''): ?>
    <details style="margin-top:12px">
      <summary style="cursor:pointer;font-size:12.5px;color:var(--muted)"><?= e(A('تفاصيل تقنية', 'Technical details')) ?></summary>
      <table class="kv" style="margin-top:8px">
        <?php if (($m['agent'] ?? '') !== ''): ?>
        <tr><td><?= e(A('الجهاز','Device')) ?></td><td class="ltr"><?= e(ua_short((string)$m['agent'])) ?></td></tr><?php endif; ?>
        <?php if (($m['page'] ?? '') !== ''): ?>
        <tr><td><?= e(A('الصفحة','Page')) ?></td><td class="ltr" style="font-size:11.5px;color:var(--muted)"><?= e($m['page']) ?></td></tr><?php endif; ?>
      </table>
    </details>
    <?php endif; ?>

    <?php if (trim((string)($m['reply'] ?? '')) !== ''): ?>
    <div class="note" style="margin-top:14px">
      <div class="hd">💬 <?= e(A('ردك', 'Your reply')) ?> · <span class="ltr" style="font-weight:400"><?= e(fmt_dt($m['replied_at'] ?? null)) ?></span></div>
      <p dir="auto"><?= nl2br(e((string)$m['reply'])) ?></p>
    </div>
    <?php endif; ?>

    <form method="post" style="margin-top:14px">
      <input type="hidden" name="action" value="sup_reply">
      <input type="hidden" name="sid" value="<?= e($m['id']) ?>">
      <div class="field">
        <label><?= e(trim((string)($m['reply'] ?? '')) !== ''
              ? A('تعديل الرد', 'Edit the reply')
              : A('اكتب رداً — يصل بالبريد، ويراه المُرسِل برقم المتابعة',
                  'Write a reply — it is emailed, and the sender can read it with their reference')) ?></label>
        <textarea name="reply" dir="auto" rows="3"><?= e((string)($m['reply'] ?? '')) ?></textarea>
      </div>
      <button class="btn gold" type="submit">📧 <?= e(A('إرسال الرد', 'Send the reply')) ?></button>
    </form>

    <div class="btnrow" style="margin-top:12px">
      <form method="post" style="flex:1">
        <input type="hidden" name="action" value="sup_read">
        <input type="hidden" name="sid" value="<?= e($m['id']) ?>">
        <button class="btn ghost" type="submit"><?= empty($m['read'])
          ? e(A('✔️ تعليم كمقروءة', '✔️ Mark as read'))
          : e(A('↩️ إرجاعها كجديدة', '↩️ Mark as unread')) ?></button>
      </form>
      <form method="post" style="flex:1" onsubmit="return confirm('<?= e(A('حذف هذه الرسالة؟','Delete this message?')) ?>')">
        <input type="hidden" name="action" value="sup_del">
        <input type="hidden" name="sid" value="<?= e($m['id']) ?>">
        <button class="btn ghost" type="submit">🗑️ <?= e(A('حذف','Delete')) ?></button>
      </form>
    </div>
  </div>
  <?php endforeach; ?>

<?php elseif ($page === 'security'): ?>
  <?php admin_security_page(); ?>

<?php elseif ($open): $o = $open; ?>
  <div class="card">
    <a href="admin.php" style="color:var(--muted);text-decoration:none;font-size:14px"><?= e(A('→ رجوع إلى القائمة', '← Back to list')) ?></a>
    <h2 style="margin-top:10px">
      <span style="letter-spacing:4px"><?= e($o['id']) ?></span>
      <span class="chip <?= $o['status'] === 'done' ? 'g' : 'r' ?>"><?= $o['status'] === 'done' ? e(A('جاهز','DONE')) : e(A('تحت المراجعة','UNDER REVIEW')) ?></span>
    </h2>
    <p class="sub"><?= e(car_title($o)) ?></p>

    <p style="margin:0 0 14px">
      <a class="btn gold" style="display:inline-block;width:auto;padding:11px 20px;text-decoration:none"
         href="gallery.php?id=<?= e($o['id']) ?>" target="_blank" rel="noopener"><?= e(A('عرض معرض الصور ↗', 'Open organized gallery ↗')) ?></a>
      <a class="btn ghost" style="display:inline-block;width:auto;padding:11px 20px;text-decoration:none;margin-inline-start:8px"
         href="receipt.php?id=<?= e($o['id']) ?>" target="_blank" rel="noopener">📄 <?= e(A('معاينة تقرير PDF', 'Preview the PDF report')) ?></a>
    </p>

    <table class="kv">
      <tr><td><?= e(A('العميل', 'Customer')) ?></td><td><?= e($o['name']) ?></td></tr>
      <tr><td><?= e(A('الجوال', 'Phone')) ?></td><td><a href="https://wa.me/<?= e(preg_replace('/[^0-9]/', '', $o['phone'])) ?>" style="color:var(--brand);font-weight:700"><?= e($o['phone']) ?></a></td></tr>
      <tr><td><?= e(A('البريد', 'Email')) ?></td><td><a href="mailto:<?= e($o['email']) ?>" style="color:var(--brand)"><?= e($o['email']) ?></a></td></tr>
      <tr><td><?= e(A('الشركة / الفئة', 'Make / Class')) ?></td><td><?= e($o['car_make']) ?> · <?= e($o['car_class'] ?? '—') ?></td></tr>
      <tr><td><?= e(A('الموديل', 'Model / trim')) ?></td><td><?= e($o['car_model'] ?: '—') ?></td></tr>
      <tr><td><?= e(A('الممشى', 'Mileage')) ?></td><td><?= e($o['mileage'] ?: '—') ?></td></tr>
      <tr><td><?= e(A('رقم الاستمارة', 'Registration')) ?></td><td><?= e($o['registration'] ?: '—') ?></td></tr>
      <tr><td><?= e(A('رقم الشاصي', 'Chassis / VIN')) ?></td><td><?= e($o['chassis'] ?: '—') ?></td></tr>
      <tr><td><?= e(A('ملاحظات', 'Notes')) ?></td><td><?= nl2br(e($o['notes'] ?: '—')) ?></td></tr>
      <tr><td><?= e(A('تاريخ الإرسال', 'Submitted')) ?></td><td><?= e(fmt_dt($o['created'])) ?></td></tr>
      <?php if (!empty($o['done_at'])): ?><tr><td><?= e(A('تاريخ التسعير', 'Priced on')) ?></td><td><?= e(fmt_dt($o['done_at'])) ?></td></tr><?php endif; ?>
      <tr><td><?= e(A('تُحذف الملفات في', 'Files deleted on')) ?></td><td><?= e(fmt_dt($o['expires_at'], 'd M Y')) ?> (<?= (int)$o['retention'] ?> <?= e(A('أيام','days')) ?>)<?= !empty($o['files_purged']) ? ' — <b>' . e(A('حُذفت','already deleted')) . '</b>' : '' ?></td></tr>
    </table>
  </div>

  <div class="card">
    <h2><?= e(A('الصور والفيديو', 'Photos & video')) ?></h2>
    <p class="sub"><?= count($o['photos']) ?> <?= e(A('صورة','photo(s)')) ?>, <?= count($o['videos']) ?> <?= e(A('فيديو — اضغط لعرضها بالحجم الكامل','video(s) — tap to open full size')) ?></p>
    <?php if (!empty($o['files_purged'])): ?>
      <p class="sub" style="color:#d63b3b"><?= e(A('حُذفت الملفات (انتهت مدة الاحتفاظ).', 'Files were deleted (retention period ended).')) ?></p>
    <?php else: ?>
    <?php $byslot = []; foreach ($o['photos'] as $p) $byslot[$p['slot'] ?? ''] = $p['file']; ?>
    <div class="grid">
      <?php foreach (slots() as $s): $file = $byslot[$s['key']] ?? null; ?>
        <div>
          <?php if ($file): ?>
            <a href="file.php?id=<?= e($o['id']) ?>&f=<?= e($file) ?>" target="_blank" rel="noopener">
              <img src="file.php?id=<?= e($o['id']) ?>&f=<?= e($file) ?>" alt="" loading="lazy"></a>
          <?php else: ?>
            <div style="aspect-ratio:1/1;border-radius:11px;border:1.5px dashed #ccd6e0;display:grid;place-items:center;color:var(--muted);font-size:11px">—</div>
          <?php endif; ?>
          <div style="font-size:11px;color:var(--muted);margin-top:4px;line-height:1.3"><?= e($AL === 'ar' ? $s['ar'] : $s['en']) ?><?= $s['req'] && !$file ? ' <b style="color:#d63b3b">' . e(A('ناقصة','missing')) . '</b>' : '' ?></div>
        </div>
      <?php endforeach; ?>
      <?php foreach ($o['videos'] as $i => $v): ?>
        <div>
          <a href="file.php?id=<?= e($o['id']) ?>&f=<?= e($v['file']) ?>" target="_blank" rel="noopener">
            <video src="file.php?id=<?= e($o['id']) ?>&f=<?= e($v['file']) ?>" muted playsinline preload="metadata"></video></a>
          <div style="font-size:11px;color:var(--muted);margin-top:4px"><?= e(A('فيديو','Video')) ?> <?= $i + 1 ?></div>
        </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

  <?php if (cond_has_any($o)): $oc = cond_of($o); ?>
  <div class="card">
    <h2><?= e(A('حالة السيارة كما وصفها العميل', 'Condition — as the customer described it')) ?></h2>
    <p class="sub"><?= e(A('نفس الرسم يظهر في الصفحة الثالثة من تقرير PDF.', 'The same diagram is printed on page 3 of the PDF report.')) ?></p>

    <div class="carmap" style="max-width:420px;margin:0 auto"><?= car_map_svg($oc['panels'], false, $AL) ?></div>
    <div class="cmlegend" style="max-width:420px;margin-inline:auto">
      <span class="cmkey"><i class="k-painted"></i><?= e(cm_state_label('painted', $AL)) ?></span>
      <span class="cmkey"><i class="k-accident"></i><?= e(cm_state_label('accident', $AL)) ?></span>
    </div>

    <table class="kv" style="margin-top:16px">
      <?php foreach (cond_summary_rows($o) as $row): ?>
      <tr><td><?= e($AL === 'ar' ? $row['ar'] : $row['en']) ?></td>
          <td><?= e($AL === 'ar' ? $row['v_ar'] : ($row['v_en'] ?: $row['v_ar'])) ?></td></tr>
      <?php endforeach; ?>
    </table>
  </div>
  <?php endif; ?>

  <div class="card">
    <h2><?= e(A('التسعير', 'Valuation')) ?></h2>
    <p class="sub"><?= e(A('أدخل السعر ثم اضغط «حفظ وإرسال». يتحول ضوء العميل إلى الأخضر ويصله بريد بالنتيجة.', 'Enter the price, then press “Save & send”. The customer’s light turns green and an email goes out.')) ?></p>
    <form method="post">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" value="<?= e($o['id']) ?>">
      <div class="field">
        <label><?= e(A('السعر التقديري', 'Estimated price')) ?> (<?= e(cfg('currency_en')) ?>)</label>
        <div class="row2">
          <div>
            <span class="sub" style="display:block;margin:0 0 5px"><?= e(A('من', 'From')) ?></span>
            <input name="price" inputmode="numeric" dir="ltr"
                   value="<?= e((string)($o['price'] ?? '')) ?>" placeholder="180000">
          </div>
          <div>
            <span class="sub" style="display:block;margin:0 0 5px"><?= e(A('إلى (اختياري)', 'To (optional)')) ?></span>
            <input name="price_to" inputmode="numeric" dir="ltr"
                   value="<?= e((string)($o['price_to'] ?? '')) ?>" placeholder="195000">
          </div>
        </div>
        <p class="hint" style="margin:8px 0 0"><?= e(A(
            'اترك خانة «إلى» فارغة لسعر واحد. املأها ليظهر نطاق سعري مثل ١٨٠٬٠٠٠ – ١٩٥٬٠٠٠.',
            'Leave “To” empty for a single price. Fill it in to show a range, e.g. 180,000 – 195,000.')) ?></p>
      </div>
      <div class="field">
        <label><?= e(A('ملاحظة للعميل — بالعربية', 'Note to customer — Arabic')) ?></label>
        <textarea name="note_ar" dir="rtl"><?= e($o['note_ar'] ?? '') ?></textarea>
      </div>
      <div class="field">
        <label><?= e(A('ملاحظة للعميل — بالإنجليزية', 'Note to customer — English')) ?></label>
        <textarea name="note_en"><?= e($o['note_en'] ?? '') ?></textarea>
      </div>
      <div class="btnrow">
        <button class="btn ghost" type="submit" name="mark" value="draft"><?= e(A('حفظ كمسودة', 'Save draft')) ?></button>
        <button class="btn gold" type="submit" name="mark" value="done"><?= e(A('حفظ وإرسال', 'Save & send')) ?> 🟢</button>
      </div>
    </form>
    <?php if (($o['status'] ?? '') === 'done'): ?>
      <form method="post" style="margin-top:10px">
        <input type="hidden" name="action" value="resend">
        <input type="hidden" name="id" value="<?= e($o['id']) ?>">
        <button class="btn ghost" type="submit"><?= e(A('📧 إعادة إرسال بريد النتيجة', '📧 Resend the result email')) ?></button>
      </form>
    <?php endif; ?>
  </div>

  <div class="card">
    <h2><?= e(A('تنظيف', 'Clean up')) ?></h2>
    <p class="sub"><?= e(A('حذف الصور يُبقي بيانات العميل والسيارة والسعر في الأرشيف للمتابعة.', 'Deleting the photos keeps the customer, car and price in the Archive for follow-up.')) ?></p>
    <form method="post" onsubmit="return confirm('<?= e(A('حذف الصور والفيديو فقط؟ يبقى السجل في الأرشيف.', 'Delete only the photos and videos? The record stays in the Archive.')) ?>')">
      <input type="hidden" name="action" value="purge">
      <input type="hidden" name="id" value="<?= e($o['id']) ?>">
      <button class="btn ghost" type="submit"><?= e(A('🗑️ حذف الصور فقط — مع بقاء السجل', '🗑️ Delete photos only — keep the record')) ?></button>
    </form>
    <form method="post" style="margin-top:10px" onsubmit="return confirm('<?= e(A('حذف كل شيء بما في ذلك سجل الأرشيف؟ لا يمكن التراجع.', 'Delete EVERYTHING including the archive record? This cannot be undone.')) ?>')">
      <input type="hidden" name="action" value="delete">
      <input type="hidden" name="id" value="<?= e($o['id']) ?>">
      <button class="btn ghost" type="submit" style="color:#d63b3b"><?= e(A('حذف الطلب بالكامل', 'Delete the whole request')) ?></button>
    </form>
  </div>

<?php else: ?>
  <div class="card">
    <h2><?= e(A('الطلبات', 'Requests')) ?></h2>
    <p class="sub"><?= e(A('الأحدث أولاً', 'Newest first')) ?></p>
    <?php if (!$rows): ?>
      <p class="sub" style="margin:0"><?= e(A('لا توجد طلبات بعد.', 'No requests yet.')) ?></p>
    <?php endif; ?>
    <?php foreach ($rows as $r): ?>
      <a class="item" href="?open=<?= e($r['id']) ?>">
        <h4><span style="letter-spacing:3px"><?= e($r['id']) ?></span>
          <span class="chip <?= $r['status'] === 'done' ? 'g' : 'r' ?>"><?= $r['status'] === 'done' ? e(A('جاهز','DONE')) : e(A('مراجعة','REVIEW')) ?></span></h4>
        <p><?= e(car_title($r)) ?>
           · <?= e($r['name']) ?>
           · <?= count($r['photos']) ?>📷<?= count($r['videos']) ? ' ' . count($r['videos']) . '🎬' : '' ?>
           · <?= e(fmt_dt($r['created'], 'd M · H:i')) ?></p>
      </a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
</main>
</div></body></html>
