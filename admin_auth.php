<?php
/* ============================================================
   admin_auth.php — who is allowed into the control panel.

   Everything about the admin identity lives here:
     • the username and the password (stored HASHED, never in clear text)
     • changing both from inside the panel
     • “I forgot the password” → a one-time link emailed to the
       recovery addresses
     • a cool-off after repeated wrong passwords

   Storage:  data/admin.json   (created the first time something is
   changed).  Until then the values in config.php are used, so an
   existing installation keeps working after an upgrade.

   Included by lib.php (for the login check) and by admin.php
   (for the screens).  Never opened directly — see .htaccess.
   ============================================================ */
declare(strict_types=1);

if (!defined('DATA_DIR')) { require_once __DIR__ . '/lib.php'; }

define('ADMIN_FILE', DATA_DIR . '/admin.json');

/* how long a reset link stays valid (seconds) */
if (!defined('ADMIN_RESET_TTL'))   define('ADMIN_RESET_TTL',   3600);   // 60 minutes
/* wrong passwords allowed before a cool-off, and how long it lasts */
if (!defined('ADMIN_MAX_FAILS'))   define('ADMIN_MAX_FAILS',   8);
if (!defined('ADMIN_LOCK_SECS'))   define('ADMIN_LOCK_SECS',   900);    // 15 minutes
/* smallest gap between two reset emails */
if (!defined('ADMIN_RESEND_SECS')) define('ADMIN_RESEND_SECS', 120);
/* shortest password we accept */
if (!defined('ADMIN_MIN_PW'))      define('ADMIN_MIN_PW',      8);

/* ------------------------------------------------------------------
   the little store
   ------------------------------------------------------------------ */

function admin_store(bool $fresh = false): array
{
    static $s = null;
    if ($s !== null && !$fresh) return $s;

    $j = json_decode((string)@file_get_contents(ADMIN_FILE), true);
    if (!is_array($j)) $j = [];

    $s = $j + [
        'user'      => '',
        'hash'      => '',
        'recovery'  => [],
        'reset'     => null,
        'fails'     => 0,
        'lock_till' => 0,
        'last_send' => 0,
        'changed'   => '',
    ];
    return $s;
}

function admin_store_write(array $a): bool
{
    if (!is_dir(dirname(ADMIN_FILE))) @mkdir(dirname(ADMIN_FILE), 0755, true);
    $tmp = ADMIN_FILE . '.tmp';
    $ok  = @file_put_contents($tmp, json_encode($a, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) !== false
        && @rename($tmp, ADMIN_FILE);
    if ($ok) {
        @chmod(ADMIN_FILE, 0600);
        admin_store(true);            // drop the cached copy
        $GLOBALS['__admin_store'] = $a;
    }
    return $ok;
}

/** The username that must be typed. Empty means "any username is accepted". */
function admin_username(): string
{
    $s = admin_store();
    if (trim((string)$s['user']) !== '') return trim((string)$s['user']);
    return trim((string)(cfg('admin_user') ?? ''));
}

/** Where a "forgot the password" link is sent. */
function admin_recovery_emails(): array
{
    $s   = admin_store();
    $out = [];

    $list = is_array($s['recovery']) && $s['recovery'] ? $s['recovery'] : (array)(cfg('admin_recovery') ?: []);
    foreach ($list as $m) {
        $m = trim((string)$m);
        if ($m !== '' && filter_var($m, FILTER_VALIDATE_EMAIL)) $out[strtolower($m)] = $m;
    }
    if (!$out) {
        $fallback = owner_email();
        if (filter_var($fallback, FILTER_VALIDATE_EMAIL)) $out[strtolower($fallback)] = $fallback;
    }
    return array_values($out);
}

/** true once the password has been changed from the panel (config.php no longer used). */
function admin_password_is_hashed(): bool
{
    return trim((string)admin_store()['hash']) !== '';
}

/* ------------------------------------------------------------------
   checking a password
   ------------------------------------------------------------------ */

/**
 * How many wrong passwords have been typed in a row.
 *
 * Note what this deliberately does NOT do: it never blocks the panel.
 * An earlier build locked the door for 15 minutes after 8 wrong tries, and
 * that turned out to be the wrong trade for a one-person panel — the person
 * it locked out was always the owner, never an attacker. Instead every wrong
 * answer now comes back a little slower (see admin_auth_check), which stops a
 * machine guessing thousands of passwords while a human typing the right one
 * is let straight in.
 */
function admin_recent_fails(): int
{
    return (int)admin_store()['fails'];
}

/** Kept so old calls still work. Always 0 — the panel is never locked any more. */
function admin_locked_for(): int
{
    return 0;
}

/** The pause, in seconds, before a wrong password is answered. */
function admin_fail_delay(int $fails): int
{
    return $fails > 3 ? min($fails - 3, 6) : 0;
}

function admin_note_fail(): void
{
    $s = admin_store();
    $s['fails']     = (int)$s['fails'] + 1;
    $s['lock_till'] = 0;                       // clears any lock left by an older build
    admin_store_write($s);

    /* slow the guesser down, but never shut the door */
    $wait = admin_fail_delay((int)$s['fails']);
    if ($wait > 0) {
        if ((int)$s['fails'] === ADMIN_MAX_FAILS) {
            log_line('ADMIN ' . ADMIN_MAX_FAILS . ' wrong passwords in a row from ' . admin_ip() . ' — replies are being slowed');
        }
        sleep($wait);
    }
}

function admin_note_success(): void
{
    $s = admin_store();
    if ((int)$s['fails'] === 0 && (int)$s['lock_till'] === 0) return;
    $s['fails'] = 0; $s['lock_till'] = 0;
    admin_store_write($s);
}

function admin_ip(): string
{
    return (string)($_SERVER['REMOTE_ADDR'] ?? '?');
}

/** Just the password, no lockout bookkeeping. */
function admin_password_matches(string $pass): bool
{
    $s = admin_store();
    if (trim((string)$s['hash']) !== '') {
        return password_verify($pass, (string)$s['hash']);
    }
    /* nothing saved yet — fall back to the value shipped in config.php */
    $legacy = (string)(cfg('admin_password') ?? '');
    return $legacy !== '' && hash_equals($legacy, $pass);
}

/**
 * The real login check, used by admin.php, mailtest.php, archive.php.
 * An empty username box is still accepted so nobody can be locked out.
 */
function admin_auth_check(string $user, string $pass): bool
{
    $want = strtolower(admin_username());
    $got  = strtolower(trim($user));
    $okUser = ($want === '' || $got === '' || $got === $want);
    $okPass = admin_password_matches($pass);

    if ($okUser && $okPass) { admin_note_success(); return true; }
    admin_note_fail();
    return false;
}

/* ------------------------------------------------------------------
   changing things
   ------------------------------------------------------------------ */

function admin_set_password(string $new): bool
{
    $s = admin_store();
    $s['hash']    = password_hash($new, PASSWORD_DEFAULT);
    $s['reset']   = null;
    $s['fails']   = 0;
    $s['lock_till'] = 0;
    $s['changed'] = gmdate('c');
    return admin_store_write($s);
}

function admin_set_username(string $u): bool
{
    $s = admin_store();
    $s['user']    = trim($u);
    $s['changed'] = gmdate('c');
    return admin_store_write($s);
}

function admin_set_recovery(array $emails): bool
{
    $clean = [];
    foreach ($emails as $m) {
        $m = trim((string)$m);
        if ($m !== '' && filter_var($m, FILTER_VALIDATE_EMAIL)) $clean[strtolower($m)] = $m;
    }
    $s = admin_store();
    $s['recovery'] = array_values($clean);
    $s['changed']  = gmdate('c');
    return admin_store_write($s);
}

/* ------------------------------------------------------------------
   forgot the password
   ------------------------------------------------------------------ */

function admin_reset_url(string $token): string
{
    return base_url() . '/admin.php?reset=' . rawurlencode($token);
}

/**
 * Make a one-time link and email it to every recovery address.
 * Returns true if at least one email left the server.
 */
function admin_start_reset(string &$msg, string &$bad): bool
{
    $s = admin_store();

    if (time() - (int)$s['last_send'] < ADMIN_RESEND_SECS) {
        $wait = ADMIN_RESEND_SECS - (time() - (int)$s['last_send']);
        $bad  = aa_t('⏳ تم إرسال رابط قبل قليل. انتظر ' . $wait . ' ثانية ثم حاول مرة أخرى.',
                     '⏳ A link was just sent. Wait ' . $wait . ' seconds before asking for another.');
        return false;
    }

    $to = admin_recovery_emails();
    if (!$to) {
        $bad = aa_t('❌ لا يوجد بريد للاسترجاع. أضِف واحداً في قسم «الأمان» أو في config.php.',
                    '❌ No recovery address is set. Add one under “Security”, or in config.php.');
        return false;
    }

    $token = bin2hex(random_bytes(24));
    $s['reset'] = [
        'hash' => hash('sha256', $token),
        'exp'  => time() + ADMIN_RESET_TTL,
        'ip'   => admin_ip(),
    ];
    $s['last_send'] = time();
    if (!admin_store_write($s)) {
        $bad = aa_t('❌ تعذّرت الكتابة في data/admin.json — تأكد أن مجلد data قابل للكتابة (755).',
                    '❌ Could not write data/admin.json — make sure the data folder is writable (755).');
        return false;
    }

    $url  = admin_reset_url($token);
    $mins = (int)round(ADMIN_RESET_TTL / 60);

    $inner =
      '<p style="margin:0 0 4px;font-size:16px" dir="rtl">استعادة كلمة مرور لوحة التحكم</p>'
    . '<p style="margin:0 0 18px;font-size:16px">Control-panel password reset</p>'
    . '<p style="margin:0 0 8px;font-size:14.5px" dir="rtl">اضغط الزر لاختيار كلمة مرور جديدة. الرابط صالح لمدة ' . $mins . ' دقيقة ويعمل مرة واحدة فقط.</p>'
    . '<p style="margin:0 0 20px;font-size:14.5px">Press the button to choose a new password. The link is valid for '
    . $mins . ' minutes and works only once.</p>'
    . '<p style="margin:0 0 18px;text-align:center">' . em_button($url, 'تعيين كلمة مرور جديدة &nbsp;·&nbsp; Set a new password', true) . '</p>'
    . '<p style="margin:0 0 18px;font-size:12.5px;color:#7b8794;word-break:break-all;direction:ltr">' . e($url) . '</p>'
    . '<div style="background:#faf6f7;border-radius:12px;padding:14px">'
    . '<p style="margin:0 0 6px;font-size:13.5px;color:#4a3a40" dir="rtl">إن لم تطلب هذا، تجاهل الرسالة — لم يتغيّر شيء.</p>'
    . '<p style="margin:0;font-size:13.5px;color:#4a3a40">If you did not ask for this, ignore the message — nothing has changed.'
    . ' Request came from IP ' . e(admin_ip()) . ' at ' . e(gmdate('d M Y H:i')) . ' UTC.</p></div>';

    $sent = 0;
    foreach ($to as $addr) {
        if (send_mail($addr, 'استعادة كلمة المرور — Control-panel password reset', email_shell($inner))) $sent++;
    }
    log_line('ADMIN reset link requested from ' . admin_ip() . ' — emailed to ' . $sent . '/' . count($to) . ' address(es)');

    if ($sent === 0) {
        $bad = aa_t('❌ تعذّر إرسال البريد. افتح «فحص البريد» لمعرفة السبب.',
                    '❌ The email could not be sent. Open “Mail check” to see why.');
        return false;
    }

    /* the addresses are shown half-hidden so the screen never leaks a full address */
    $shown = implode('، ', array_map('admin_mask_email', $to));
    $msg = aa_t('📧 أُرسل الرابط إلى: ' . $shown . ' — صالح ' . $mins . ' دقيقة. تحقّق من مجلد Junk أيضاً.',
                '📧 A link was sent to: ' . $shown . ' — valid for ' . $mins . ' minutes. Check the Junk folder too.');
    return true;
}

function admin_mask_email(string $m): string
{
    $p = explode('@', $m);
    if (count($p) !== 2) return '***';
    $u = $p[0];
    $u = mb_strlen($u) <= 2 ? mb_substr($u, 0, 1) . '*' : mb_substr($u, 0, 2) . str_repeat('*', min(6, mb_strlen($u) - 2));
    return $u . '@' . $p[1];
}

function admin_reset_token_ok(string $token): bool
{
    if ($token === '' || !preg_match('/^[a-f0-9]{48}$/', $token)) return false;
    $r = admin_store()['reset'];
    if (!is_array($r) || empty($r['hash'])) return false;
    if ((int)($r['exp'] ?? 0) < time()) return false;
    return hash_equals((string)$r['hash'], hash('sha256', $token));
}

function admin_complete_reset(string $token, string $newPw): bool
{
    if (!admin_reset_token_ok($token)) return false;
    if (!admin_set_password($newPw)) return false;      // admin_set_password clears the token
    log_line('ADMIN password changed through a reset link (' . admin_ip() . ')');
    return true;
}

/* ------------------------------------------------------------------
   text helper — works whether or not the panel translator is loaded
   ------------------------------------------------------------------ */

function aa_t(string $ar, string $en): string
{
    return function_exists('A') ? A($ar, $en) : $en;
}

function aa_dir(): string { return function_exists('admin_dir') ? admin_dir() : 'ltr'; }
function aa_lang(): string { return aa_dir() === 'rtl' ? 'ar' : 'en'; }

/* ==================================================================
   THE SCREENS
   ================================================================== */

/**
 * The signed-out screen. $mode is 'login' | 'forgot' | 'reset'.
 * Prints a whole page and returns — admin.php exits straight after.
 */
function admin_login_screen(string $mode, string $msg = '', string $bad = '', string $token = ''): void
{
    $appName = function_exists('ct') ? ct('appName', aa_lang()) : 'Admin';
    ?><!doctype html><html lang="<?= e(aa_lang()) ?>" dir="<?= e(aa_dir()) ?>"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?= e(aa_t('لوحة التحكم', 'Admin panel')) ?> — <?= e($appName) ?></title>
<link rel="stylesheet" href="<?= asset('assets/app.css') ?>">
<script src="<?= asset('assets/theme.js') ?>"></script></head>
<body><div class="app" style="padding-bottom:24px">
<header class="top"><div class="brand"><div class="mark">ث</div><div class="txt">
  <h1><?= e(aa_t('لوحة التحكم', 'Admin Panel')) ?></h1><p><?= e($appName) ?></p></div></div>
  <button class="iconbtn" id="themeBtn" type="button" aria-label="Light / dark">🌙</button>
  <a class="langbtn" href="?alang=<?= aa_lang() === 'ar' ? 'en' : 'ar' ?>" style="text-decoration:none"><?= aa_lang() === 'ar' ? 'English' : 'العربية' ?></a>
</header>
<main>

<?php if ($msg !== ''): ?><div style="background:rgba(26,155,90,.1);color:#127a45;padding:11px 14px;border-radius:11px;font-size:14px;margin-bottom:14px;line-height:1.7"><?= $msg ?></div><?php endif; ?>
<?php if ($bad !== ''): ?><div style="background:rgba(214,59,59,.1);color:#a52c2c;padding:11px 14px;border-radius:11px;font-size:14px;margin-bottom:14px;line-height:1.7"><?= $bad ?></div><?php endif; ?>

<?php if ($mode === 'reset'): ?>
  <div class="card">
    <h2><?= e(aa_t('اختر كلمة مرور جديدة', 'Choose a new password')) ?></h2>
    <p class="sub"><?= e(aa_t('هذا الرابط يعمل مرة واحدة فقط.', 'This link works only once.')) ?></p>
    <form method="post" action="admin.php">
      <input type="hidden" name="action" value="reset_pw">
      <input type="hidden" name="token" value="<?= e($token) ?>">
      <div class="field"><label><?= e(aa_t('كلمة المرور الجديدة', 'New password')) ?></label>
        <input type="password" name="pw1" dir="ltr" autocomplete="new-password" autofocus
               minlength="<?= (int)ADMIN_MIN_PW ?>" required></div>
      <div class="field"><label><?= e(aa_t('أعِد كتابتها', 'Type it again')) ?></label>
        <input type="password" name="pw2" dir="ltr" autocomplete="new-password"
               minlength="<?= (int)ADMIN_MIN_PW ?>" required></div>
      <p class="sub" style="margin:0 0 12px"><?= e(aa_t('على الأقل ' . ADMIN_MIN_PW . ' حروف/أرقام.',
             'At least ' . ADMIN_MIN_PW . ' characters.')) ?></p>
      <button class="btn gold" type="submit"><?= e(aa_t('حفظ كلمة المرور', 'Save the password')) ?></button>
    </form>
    <p style="margin:14px 0 0"><a href="admin.php" style="color:var(--muted);font-size:14px"><?= e(aa_t('رجوع لتسجيل الدخول', 'Back to sign in')) ?></a></p>
  </div>

<?php elseif ($mode === 'forgot'): ?>
  <div class="card">
    <h2><?= e(aa_t('نسيت كلمة المرور', 'Forgot the password')) ?></h2>
    <p class="sub"><?= e(aa_t('سنرسل رابطاً لتعيين كلمة مرور جديدة إلى بريد الاسترجاع المسجَّل.',
                              'We will email a link for setting a new password to the registered recovery address.')) ?></p>
    <table class="kv" style="margin-bottom:14px">
      <?php foreach (admin_recovery_emails() as $m): ?>
        <tr><td><?= e(aa_t('يُرسَل إلى', 'Sent to')) ?></td><td dir="ltr"><?= e(admin_mask_email($m)) ?></td></tr>
      <?php endforeach; ?>
      <?php if (!admin_recovery_emails()): ?>
        <tr><td colspan="2" style="color:#d63b3b"><?= e(aa_t('لا يوجد بريد استرجاع مسجَّل.', 'No recovery address is registered.')) ?></td></tr>
      <?php endif; ?>
    </table>
    <form method="post" action="admin.php">
      <input type="hidden" name="action" value="forgot">
      <button class="btn gold" type="submit"><?= e(aa_t('أرسل الرابط', 'Send the link')) ?></button>
    </form>
    <p style="margin:14px 0 0"><a href="admin.php" style="color:var(--muted);font-size:14px"><?= e(aa_t('رجوع لتسجيل الدخول', 'Back to sign in')) ?></a></p>
  </div>

<?php else: $fails = admin_recent_fails(); ?>
  <div class="card">
    <h2><?= e(aa_t('تسجيل الدخول', 'Sign in')) ?></h2>
    <form method="post" action="admin.php">
      <input type="hidden" name="action" value="login">
      <div class="field"><label><?= e(aa_t('اسم المستخدم', 'Username')) ?></label>
        <input type="text" name="username" id="username" autocomplete="username"
               dir="ltr" autocapitalize="none" autocorrect="off" spellcheck="false"
               value="<?= e((string)($_POST['username'] ?? '')) ?>" autofocus></div>
      <div class="field"><label><?= e(aa_t('كلمة المرور', 'Password')) ?></label>
        <input type="password" name="password" id="password" autocomplete="current-password" dir="ltr"></div>
      <button class="btn" type="submit"><?= e(aa_t('دخول', 'Sign in')) ?></button>
    </form>
    <p style="margin:14px 0 0"><a href="admin.php?forgot=1" style="color:var(--brand);font-weight:700;font-size:14px">
      <?= e(aa_t('نسيت كلمة المرور؟', 'Forgot the password?')) ?></a></p>
  </div>

  <?php if ($fails >= 3): ?>
  <div class="card">
    <h2><?= e(aa_t('لا تستطيع الدخول؟', 'Cannot get in?')) ?></h2>
    <p class="sub" style="line-height:1.95">
      <?= e(aa_t('الباب لا يُقفل أبداً — يمكنك المحاولة كما تشاء، الرد يتأخر ثوانٍ فقط بعد عدة محاولات خاطئة.',
                 'The door is never locked — you may keep trying. After a few wrong answers the reply just comes back a few seconds slower.')) ?><br><br>
      <b><?= e(aa_t('الحل المضمون:', 'The guaranteed way back in:')) ?></b>
      <?= e(aa_t('افتح مدير الملفات في الاستضافة واحذف الملف data/admin.json. ترجع كلمة المرور فوراً إلى المكتوبة في config.php.',
                 'Open the hosting file manager and delete the file data/admin.json. The password goes straight back to the one written in config.php.')) ?>
    </p>
  </div>
  <?php endif; ?>
<?php endif; ?>

</main></div></body></html><?php
}

/* ------------------------------------------------------------------
   the Security section inside the panel
   ------------------------------------------------------------------ */

/** Handles the POSTs of the Security page. Returns true when it dealt with one. */
function admin_security_post(string &$msg, string &$bad): bool
{
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'sec_login') {
        $cur  = (string)($_POST['cur_pw'] ?? '');
        $user = trim((string)($_POST['new_user'] ?? ''));
        $pw1  = (string)($_POST['pw1'] ?? '');
        $pw2  = (string)($_POST['pw2'] ?? '');

        if (!admin_password_matches($cur)) {
            $bad = aa_t('❌ كلمة المرور الحالية غير صحيحة — لم يتغيّر شيء.',
                        '❌ The current password is wrong — nothing was changed.');
            return true;
        }
        if ($user !== '' && !preg_match('/^[A-Za-z0-9._@-]{3,64}$/', $user)) {
            $bad = aa_t('❌ اسم المستخدم: حروف وأرقام و . _ - @ فقط، من 3 إلى 64 خانة.',
                        '❌ Username: letters, digits and . _ - @ only, 3 to 64 characters.');
            return true;
        }
        if ($pw1 !== '' || $pw2 !== '') {
            if ($pw1 !== $pw2) {
                $bad = aa_t('❌ كلمتا المرور غير متطابقتين.', '❌ The two new passwords do not match.');
                return true;
            }
            if (mb_strlen($pw1) < ADMIN_MIN_PW) {
                $bad = aa_t('❌ كلمة المرور قصيرة — الحد الأدنى ' . ADMIN_MIN_PW . ' خانات.',
                            '❌ That password is too short — the minimum is ' . ADMIN_MIN_PW . ' characters.');
                return true;
            }
        }

        $done = [];
        if ($user !== '' && strtolower($user) !== strtolower(admin_username())) {
            admin_set_username($user);
            $done[] = aa_t('اسم المستخدم', 'the username');
        }
        if ($pw1 !== '') {
            admin_set_password($pw1);
            $done[] = aa_t('كلمة المرور', 'the password');
        }

        if (!$done) {
            $msg = aa_t('لم يتغيّر شيء — الخانات كانت فارغة أو كما هي.',
                        'Nothing changed — the boxes were empty or already had those values.');
        } else {
            log_line('ADMIN credentials updated (' . implode(', ', $done) . ') from ' . admin_ip());
            $msg = aa_t('✅ تم تحديث ' . implode(' و', $done) . '. استخدمها في المرة القادمة.',
                        '✅ Updated ' . implode(' and ', $done) . '. Use it the next time you sign in.');
        }
        return true;
    }

    if ($action === 'sec_recovery') {
        $list = [(string)($_POST['rec1'] ?? ''), (string)($_POST['rec2'] ?? '')];
        $bad1 = false;
        foreach ($list as $m) { if (trim($m) !== '' && !filter_var(trim($m), FILTER_VALIDATE_EMAIL)) $bad1 = true; }
        if ($bad1) {
            $bad = aa_t('❌ أحد العناوين غير صالح — لم يُحفظ شيء.', '❌ One of the addresses is not a valid email — nothing was saved.');
            return true;
        }
        admin_set_recovery($list);
        log_line('ADMIN recovery addresses updated from ' . admin_ip());
        $msg = aa_t('✅ حُفظ بريد الاسترجاع.', '✅ The recovery addresses were saved.');
        return true;
    }

    if ($action === 'sec_test') {
        $to = admin_recovery_emails();
        $n  = 0;
        foreach ($to as $addr) {
            if (send_mail($addr, 'اختبار بريد الاسترجاع — Recovery address test',
                          email_shell('<p style="margin:0 0 10px;font-size:15px" dir="rtl">وصلت هذه الرسالة، إذن بريد الاسترجاع يعمل. ✅</p>'
                                    . '<p style="margin:0;font-size:15px">This message arrived, so the recovery address works. ✅</p>'))) $n++;
        }
        $msg = $n
            ? aa_t('📧 أُرسلت رسالة اختبار إلى ' . $n . ' عنوان. تحقّق من Junk أيضاً.',
                   '📧 A test message was sent to ' . $n . ' address(es). Check the Junk folder too.')
            : '';
        if (!$n) $bad = aa_t('❌ لم تُرسل — افتح «فحص البريد».', '❌ Nothing went out — open “Mail check”.');
        return true;
    }

    return false;
}

function admin_security_page(): void
{
    $rec = admin_recovery_emails();
    ?>
    <div class="card">
      <h2><?= e(aa_t('🔐 اسم المستخدم وكلمة المرور', '🔐 Username & password')) ?></h2>
      <p class="sub"><?= e(aa_t('اكتب كلمة المرور الحالية أولاً، ثم غيّر ما تريد. اترك أي خانة فارغة لتبقى كما هي.',
              'Type the current password first, then change what you want. Leave a box empty to keep it as it is.')) ?></p>

      <form method="post" action="<?= e(admin_url(['page' => 'security', 'open' => null])) ?>" autocomplete="off">
        <input type="hidden" name="action" value="sec_login">
        <div class="field"><label><?= e(aa_t('كلمة المرور الحالية', 'Current password')) ?> *</label>
          <input type="password" name="cur_pw" dir="ltr" autocomplete="current-password" required></div>

        <div class="field"><label><?= e(aa_t('اسم المستخدم', 'Username')) ?></label>
          <input type="text" name="new_user" dir="ltr" autocapitalize="none" spellcheck="false"
                 value="<?= e(admin_username()) ?>" placeholder="admin"></div>

        <div class="field"><label><?= e(aa_t('كلمة مرور جديدة', 'New password')) ?></label>
          <input type="password" name="pw1" dir="ltr" autocomplete="new-password" placeholder="<?= e(aa_t('اتركها فارغة إن لم ترد تغييرها', 'leave empty to keep the current one')) ?>"></div>

        <div class="field"><label><?= e(aa_t('أعِد كتابة الجديدة', 'Repeat the new password')) ?></label>
          <input type="password" name="pw2" dir="ltr" autocomplete="new-password"></div>

        <div class="btnrow"><button class="btn gold" type="submit"><?= e(aa_t('حفظ', 'Save')) ?></button></div>
      </form>

      <table class="kv" style="margin-top:16px">
        <tr><td><?= e(aa_t('اسم المستخدم الحالي', 'Current username')) ?></td>
            <td dir="ltr"><b><?= e(admin_username() !== '' ? admin_username() : aa_t('أي اسم مقبول', 'any username accepted')) ?></b></td></tr>
        <tr><td><?= e(aa_t('كلمة المرور مخزَّنة', 'Password stored')) ?></td>
            <td><?= admin_password_is_hashed()
                    ? e(aa_t('✅ مشفّرة في data/admin.json', '✅ hashed, in data/admin.json'))
                    : '<span style="color:#d63b3b">' . e(aa_t('⚠️ ما زالت النص الظاهر في config.php — غيّرها الآن', '⚠️ still the plain-text one in config.php — change it now')) . '</span>' ?></td></tr>
        <?php if (admin_store()['changed']): ?>
        <tr><td><?= e(aa_t('آخر تغيير', 'Last changed')) ?></td><td><?= e(fmt_dt((string)admin_store()['changed'])) ?></td></tr>
        <?php endif; ?>
      </table>
    </div>

    <div class="card">
      <h2><?= e(aa_t('📧 بريد استعادة كلمة المرور', '📧 Password-recovery email')) ?></h2>
      <p class="sub"><?= e(aa_t('إذا نُسيت كلمة المرور، يُرسَل رابط لتعيين كلمة جديدة إلى هذين العنوانين. ضَع عنواناً تستطيع فتحه دائماً.',
              'If the password is ever forgotten, a link for setting a new one is emailed to these addresses. Use addresses you can always open.')) ?></p>
      <form method="post" action="<?= e(admin_url(['page' => 'security', 'open' => null])) ?>">
        <input type="hidden" name="action" value="sec_recovery">
        <div class="field"><label><?= e(aa_t('العنوان الأول', 'First address')) ?></label>
          <input type="email" name="rec1" dir="ltr" value="<?= e($rec[0] ?? '') ?>"></div>
        <div class="field"><label><?= e(aa_t('العنوان الثاني (اختياري)', 'Second address (optional)')) ?></label>
          <input type="email" name="rec2" dir="ltr" value="<?= e($rec[1] ?? '') ?>"></div>
        <div class="btnrow"><button class="btn gold" type="submit"><?= e(aa_t('حفظ', 'Save')) ?></button></div>
      </form>
      <form method="post" action="<?= e(admin_url(['page' => 'security', 'open' => null])) ?>" style="margin-top:10px">
        <input type="hidden" name="action" value="sec_test">
        <button class="btn ghost" type="submit"><?= e(aa_t('إرسال رسالة اختبار الآن', 'Send a test message now')) ?></button>
      </form>
      <p class="sub" style="margin:12px 0 0"><?= e(aa_t('نصيحة: بعض رسائل الاسترجاع تصل إلى Junk. افتح المجلد وضع العنوان في «المرسلون الموثوقون».',
              'Tip: recovery mail sometimes lands in Junk. Open that folder once and mark the sender as safe.')) ?></p>
    </div>

    <div class="card">
      <h2><?= e(aa_t('ماذا لو ضاع كل شيء؟', 'What if everything is lost?')) ?></h2>
      <p class="sub" style="line-height:1.9">
        <?= e(aa_t('احذف الملف data/admin.json من مدير الملفات في الاستضافة. عندها ترجع كلمة المرور إلى القيمة المكتوبة في config.php ويمكنك الدخول بها.',
                   'Delete the file data/admin.json from the hosting file manager. The password then falls back to the value written in config.php, and you can sign in with that.')) ?>
      </p>
    </div>
    <?php
}
