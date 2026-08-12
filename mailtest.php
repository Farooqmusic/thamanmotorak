<?php
/* ============================================================
   mailtest.php — why is email not arriving? Find out here.
   Open:  https://thamanmotorak.com/mailtest.php
   ============================================================ */
declare(strict_types=1);
require __DIR__ . '/lib.php';
ensure_dirs();
session_start();

$C   = cfg();
$msg = '';
$err = '';

if (isset($_GET['logout'])) { $_SESSION = []; session_destroy(); header('Location: mailtest.php'); exit; }

if (($_POST['action'] ?? '') === 'login') {
    if (admin_login_ok((string)($_POST['username'] ?? ''), (string)($_POST['password'] ?? ''))) {
        session_regenerate_id(true);
        $_SESSION['eyc_admin'] = true;
        header('Location: mailtest.php'); exit;
    }
    $err = 'Wrong password';
}

if (empty($_SESSION['eyc_admin'])) {
    ?><!doctype html><html lang="en"><head><meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow">
    <title>Mail test</title><link rel="stylesheet" href="<?= asset('assets/app.css') ?>">
<script src="<?= asset('assets/theme.js') ?>"></script></head>
    <body><div class="app" style="padding-bottom:24px">
    <header class="top"><div class="brand"><div class="mark">ث</div><div class="txt">
      <h1>Mail diagnostics</h1><p>ثـمـــن مــوتــرك</p></div></div>
      <button class="iconbtn" id="themeBtn" type="button" aria-label="Light / dark">🌙</button>
  </header>
    <main><div class="card"><h2>Sign in</h2>
      <form method="post" action="mailtest.php"><input type="hidden" name="action" value="login">
      <div class="field"><label>Username</label><input type="text" name="username" autocomplete="username"
           dir="ltr" autocapitalize="none" autocorrect="off" spellcheck="false" autofocus></div>
      <div class="field"><label>Password</label><input type="password" name="password" autocomplete="current-password" dir="ltr"></div>
      <div class="err"><?= e($err) ?></div><button class="btn" type="submit">Sign in</button></form>
    </div></main></div></body></html><?php
    exit;
}

/* ---------------- run a test ---------------- */
$detail = '';
if (($_POST['action'] ?? '') === 'test') {
    $to     = trim((string)($_POST['to'] ?? ''));
    $method = ($_POST['method'] ?? 'config');

    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        $err = 'That does not look like a valid email address.';
    } else {
        // temporarily override the method for this one send
        if ($method !== 'config') {
            $GLOBALS['__eyc_force_method'] = $method;
        }
        $inner = '<p style="margin:0 0 12px;font-size:16px">✅ This is a test message from <b>ثـمـــن مــوتــرك</b>.</p>'
               . '<p style="margin:0 0 6px;color:var(--ink);font-size:14px">If you can read this, sending works and the customer '
               . 'confirmation emails will arrive too.</p>'
               . '<table style="width:100%;border-collapse:collapse;margin-top:14px">'
               . em_row('Sent at',      '<span dir="ltr">' . gmdate('d M Y H:i:s') . ' UTC</span>')
               . em_row('Method',       e($method === 'config' ? (string)cfg('mail_method') : $method))
               . em_row('From',         '<span dir="ltr">' . e((string)cfg('from_email')) . '</span>')
               . em_row('Server',       '<span dir="ltr">' . e($_SERVER['HTTP_HOST'] ?? '') . '</span>')
               . '</table>';

        clearstatcache();
        $before = (int)(@filesize(DATA_DIR . '/log.txt') ?: 0);
        $ok = send_mail($to, 'Test — ثـمـــن مــوتــرك mail check', email_shell($inner));
        unset($GLOBALS['__eyc_force_method']);

        // grab whatever was just appended to the log
        clearstatcache();
        $raw = (string)@file_get_contents(DATA_DIR . '/log.txt');
        $detail = trim(substr($raw, $before));

        $used = ($method === 'config') ? (string)cfg('mail_method') : $method;
        $msg = $ok
            ? '✅ Sent via ' . strtoupper($used) . ' and accepted by the server for ' . $to
              . '. Check the inbox — and the junk/spam folder.'
            : '❌ Sending via ' . strtoupper($used) . ' failed. The reason is below.';
    }
}

/* ---------------- environment facts ---------------- */
$facts = [
    'PHP version'                => PHP_VERSION,
    'mail() available'           => function_exists('mail') ? 'yes' : 'NO — you must use SMTP',
    'sendmail_path'              => ini_get('sendmail_path') ?: '(not set)',
    'GD (for email photos)'      => has_gd() ? 'yes' : 'NO — emails will go without inline photos',
    'OpenSSL (needed for SMTP)'  => extension_loaded('openssl') ? 'yes' : 'NO — SMTP over TLS will not work',
    'Configured method'          => (string)cfg('mail_method'),
    'From address'               => (string)cfg('from_email'),
    'Owner address'              => (string)cfg('owner_email'),
    'SMTP host'                  => (string)(cfg('smtp')['host'] ?? '—') . ':' . (string)(cfg('smtp')['port'] ?? '—')
                                    . ' (' . (string)(cfg('smtp')['secure'] ?? '—') . ')',
    'SMTP password set'          => (($p = (string)(cfg('smtp')['pass'] ?? '')) !== '' && strpos($p, 'PUT-THE') === false) ? 'yes' : 'NO — still the placeholder',
];

$log = '';
if (is_file(DATA_DIR . '/log.txt')) {
    $lines = file(DATA_DIR . '/log.txt', FILE_IGNORE_NEW_LINES) ?: [];
    $log = implode("\n", array_slice($lines, -60));
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="robots" content="noindex,nofollow"><meta name="theme-color" content="var(--brand)">
<title>Mail diagnostics — ثـمـــن مــوتــرك</title>
<link rel="stylesheet" href="<?= asset('assets/app.css') ?>">
<script src="<?= asset('assets/theme.js') ?>"></script>
<style>
  .app{padding-bottom:24px}
  pre{background:var(--brand);color:#d6e3ef;padding:14px;border-radius:11px;overflow-x:auto;
      font-size:12px;line-height:1.55;white-space:pre-wrap;word-break:break-word;margin:0}
  .ok{background:rgba(26,155,90,.1);color:#127a45;padding:12px 15px;border-radius:11px;font-size:14px;margin-bottom:14px}
  .bad{background:rgba(214,59,59,.1);color:#a52c2c;padding:12px 15px;border-radius:11px;font-size:14px;margin-bottom:14px}
  table.kv td{font-size:13.5px}
  table.kv td:first-child{width:46%}
.no{color:#d63b3b;font-weight:700}
  .warnbox{background:#fff5e0;border:1px solid #f0d79a;color:#7a5a0a;padding:14px 16px;
           border-radius:12px;font-size:14px;line-height:1.7;margin-bottom:14px}
  .infobox{background:#eef4fa;border:1px solid #c5d8e8;color:#2b4a67;padding:14px 16px;
           border-radius:12px;font-size:14px;line-height:1.7;margin-bottom:14px}
  code{background:rgba(15,45,74,.08);padding:1px 6px;border-radius:5px;font-size:12.5px}
</style></head>
<body><div class="app">
<header class="top">
  <div class="brand"><div class="mark">ث</div><div class="txt"><h1>Mail diagnostics</h1><p>why email is or is not arriving</p></div></div>
  <button class="iconbtn" id="themeBtn" type="button" aria-label="Light / dark">🌙</button>
  <a class="langbtn" href="archive.php" style="text-decoration:none;margin-inline-end:6px">Archive</a>
  <a class="langbtn" href="admin.php" style="text-decoration:none">Admin</a>
</header>

<main>
<?php $mhWarn = mail_health_html(false); if ($mhWarn !== ''): ?>
  <div class="card" style="border-color:#d63b3b">
    <p style="margin:0;color:#a52c2c;font-size:14px;line-height:1.8"><?= $mhWarn ?></p>
    <p class="sub" style="margin:10px 0 0">
      Fix the SMTP block in <code>config.php</code>, then send a test. This clears itself
      as soon as one message leaves through SMTP again. It is deliberately not shown in
      the owner's panel — see <code>tech_banner</code> in <code>config.php</code>.
    </p>
  </div>
<?php endif; ?>

  <?php
    $smtpPass   = (string)(cfg('smtp')['pass'] ?? '');
    $smtpReady  = ($smtpPass !== '' && strpos($smtpPass, 'PUT-THE') === false);
    $usingPhp   = (cfg('mail_method') !== 'smtp');
    $sendmail   = (string)ini_get('sendmail_path');
    $isHostinger = stripos($sendmail, 'hsendmail') !== false;
  ?>
  <?php if ($smtpReady && $usingPhp): ?>
    <div class="warnbox">
      <b>⚠️ The switch is still off.</b><br>
      Your SMTP password is filled in, but <code>config.php</code> still says
      <code>'mail_method' =&gt; 'php'</code>. Change that one line to
      <code>'mail_method' =&gt; 'smtp'</code> and mail will start leaving through the real mailbox
      instead of the server's own sender — that is what Hotmail and Gmail are refusing to trust.
    </div>
  <?php endif; ?>
  <?php if ($isHostinger): ?>
    <div class="infobox">
      <b>This looks like a Hostinger server</b> (<code><?= e($sendmail) ?></code>).<br>
      If <code>mail.thamanmotorak.com</code> does not connect, use Hostinger's own server instead:
      host <code>smtp.hostinger.com</code>, port <code>465</code>, secure <code>ssl</code>.
    </div>
  <?php endif; ?>
  <?php if ($msg): ?><div class="<?= strpos($msg, '✅') === 0 ? 'ok' : 'bad' ?>"><?= e($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="bad"><?= e($err) ?></div><?php endif; ?>

  <div class="card">
    <h2>Send a test email</h2>
    <p class="sub">Try your own address first, then Khalid's Hotmail.</p>
    <form method="post">
      <input type="hidden" name="action" value="test">
      <div class="field"><label>Send to</label>
        <input name="to" type="email" dir="ltr" value="<?= e((string)($_POST['to'] ?? cfg('owner_email'))) ?>"></div>
      <div class="field"><label>Method</label>
        <?php $sel = (string)($_POST['method'] ?? 'config'); ?>
        <select name="method">
          <option value="config"<?= $sel === 'config' ? ' selected' : '' ?>>Use config.php (<?= e((string)cfg('mail_method')) ?>)</option>
          <option value="php"<?= $sel === 'php' ? ' selected' : '' ?>>Force PHP mail()</option>
          <option value="smtp"<?= $sel === 'smtp' ? ' selected' : '' ?>>Force SMTP</option>
        </select></div>
      <button class="btn" type="submit">Send test</button>
    </form>
    <?php if ($detail): ?>
      <p class="sub" style="margin:16px 0 8px"><b>What the server said</b></p>
      <pre><?= e($detail) ?></pre>
    <?php endif; ?>
  </div>

  <div class="card">
    <h2>This server</h2>
    <table class="kv">
      <?php foreach ($facts as $k => $v): ?>
        <tr><td><?= e($k) ?></td>
            <td<?= stripos((string)$v, 'NO') === 0 ? ' class="no"' : '' ?> dir="ltr"><?= e((string)$v) ?></td></tr>
      <?php endforeach; ?>
    </table>
  </div>


  <?php
    /* ---- live DNS check for this domain ---- */
    $fromDomain = explode('@', (string)cfg('from_email'))[1] ?? '';
    $smtpHost   = (string)(cfg('smtp')['host'] ?? '');

    $spf = $dmarc = ''; $mx = [];
    if ($fromDomain !== '') {
        foreach ((array)@dns_get_record($fromDomain, DNS_TXT) as $t) {
            $v = $t['txt'] ?? '';
            if (stripos($v, 'v=spf1') === 0) $spf = $v;
        }
        foreach ((array)@dns_get_record('_dmarc.' . $fromDomain, DNS_TXT) as $t) {
            $v = $t['txt'] ?? '';
            if (stripos($v, 'v=DMARC1') === 0) $dmarc = $v;
        }
        foreach ((array)@dns_get_record($fromDomain, DNS_MX) as $m) $mx[] = $m['target'] ?? '';
    }
    $smtpIp = $smtpHost !== '' ? @gethostbyname($smtpHost) : '';
    $smtpResolves = ($smtpIp !== '' && $smtpIp !== $smtpHost);
  ?>
  <div class="card">
    <h2>Domain records <span style="font-weight:400;color:var(--muted);font-size:13px">(live check)</span></h2>
    <p class="sub">These decide whether Hotmail and Gmail trust your mail.</p>
    <table class="kv">
      <tr><td>SPF</td><td dir="ltr"><?= $spf
            ? '<span style="color:#127a45;font-weight:700">✔ present</span><br><span style="font-size:12px;color:var(--muted)">' . e($spf) . '</span>'
            : '<span class="no">✖ missing — add a TXT record on @ : v=spf1 include:_spf.mail.hostinger.com ~all</span>' ?></td></tr>
      <tr><td>DMARC</td><td dir="ltr"><?= $dmarc
            ? '<span style="color:#127a45;font-weight:700">✔ present</span><br><span style="font-size:12px;color:var(--muted)">' . e($dmarc) . '</span>'
            : '<span style="color:#e08a1e;font-weight:700">— not set (optional)</span>' ?></td></tr>
      <tr><td>MX (incoming mail)</td><td dir="ltr"><?= $mx ? e(implode(', ', $mx)) : '<span class="no">✖ none</span>' ?></td></tr>
      <tr><td>SMTP host resolves</td><td dir="ltr"><?= $smtpResolves
            ? '<span style="color:#127a45;font-weight:700">✔ ' . e($smtpHost) . ' → ' . e($smtpIp) . '</span>'
            : '<span class="no">✖ ' . e($smtpHost) . ' does not exist in DNS — SMTP cannot connect.<br>Use smtp.hostinger.com, port 465, secure ssl.</span>' ?></td></tr>
    </table>
    <p class="sub" style="margin:14px 0 0">DKIM is verified inside hPanel → Emails → your domain → Custom DKIM; it cannot be read from here.</p>
  </div>

  <div class="card">
    <h2>If mail is not arriving</h2>
    <ol style="margin:0;padding-inline-start:20px;font-size:14px;color:var(--ink);line-height:1.8">
      <li><b>Check the junk / spam folder first</b> — Hotmail is strict with new domains.</li>
      <li>In hPanel → <b>Emails</b> (or cPanel → Email Accounts), make sure <code><?= e((string)cfg('from_email')) ?></code>
          really exists as a mailbox.</li>
      <li>Best fix: set <code>'mail_method' =&gt; 'smtp'</code> in <code>config.php</code> and fill in that mailbox's password.
          Mail then leaves through the real mailbox and is signed, so Hotmail and Gmail accept it.</li>
      <li>Check the <b>Domain records</b> box above — SPF and MX are read live from DNS. DKIM is in
          hPanel → Emails → your domain → <b>Custom DKIM</b>.</li>
      <li>Still failing? Send the black box above to your developer — it contains the exact refusal from the mail server.</li>
    </ol>
  </div>

  <div class="card">
    <h2>Recent send log</h2>
    <p class="sub">data/log.txt — last 60 lines</p>
    <pre><?= e($log ?: '(empty)') ?></pre>
  </div>
</main>
</div></body></html>
