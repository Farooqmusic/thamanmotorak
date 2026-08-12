<?php
/* ============================================================
   dnscheck.php — “why does our email land in Junk?”

   Mail arrives in the Junk folder for one reason far more often
   than any other: the receiving server cannot prove the message
   really came from thamanmotorak.com. Three DNS records prove it —
   SPF, DKIM and DMARC. This page reads the live DNS for the
   sending domain, says which of the three are missing, and prints
   the exact record to paste into hPanel → Domains → DNS Zone.

   Open:  https://thamanmotorak.com/dnscheck.php   (admin sign-in)
   ============================================================ */
declare(strict_types=1);
require __DIR__ . '/lib.php';
ensure_dirs();
session_start();

$C = cfg();

if (isset($_GET['logout'])) { $_SESSION = []; session_destroy(); header('Location: dnscheck.php'); exit; }

if (($_POST['action'] ?? '') === 'login') {
    if (admin_login_ok((string)($_POST['username'] ?? ''), (string)($_POST['password'] ?? ''))) {
        session_regenerate_id(true);
        $_SESSION['eyc_admin'] = true;
        header('Location: dnscheck.php'); exit;
    }
    $err = 'Wrong username or password';
}

if (empty($_SESSION['eyc_admin'])) {
    ?><!doctype html><html lang="en"><head><meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow">
    <title>DNS / Junk check</title><link rel="stylesheet" href="<?= asset('assets/app.css') ?>">
    <script src="<?= asset('assets/theme.js') ?>"></script></head>
    <body><div class="app" style="padding-bottom:24px">
    <header class="top"><div class="brand"><div class="mark">ث</div><div class="txt">
      <h1>Junk / DNS check</h1><p>ثـمـــن مــوتــرك</p></div></div>
      <button class="iconbtn" id="themeBtn" type="button" aria-label="Light / dark">🌙</button></header>
    <main><div class="card"><h2>Sign in</h2>
      <form method="post" action="dnscheck.php"><input type="hidden" name="action" value="login">
      <div class="field"><label>Username</label><input type="text" name="username" autocomplete="username"
           dir="ltr" autocapitalize="none" spellcheck="false" autofocus></div>
      <div class="field"><label>Password</label><input type="password" name="password" autocomplete="current-password" dir="ltr"></div>
      <div class="err"><?= e($err ?? '') ?></div>
      <button class="btn" type="submit">Sign in</button></form>
    </div></main></div></body></html><?php
    exit;
}

/* ------------------------------------------------------------------ */

$fromEmail = (string)cfg('from_email');
$domain    = strtolower(trim(explode('@', $fromEmail)[1] ?? ''));
$smtp      = (array)cfg('smtp');
$smtpUser  = (string)($smtp['user'] ?? '');
$smtpHost  = strtolower((string)($smtp['host'] ?? ''));

/** dns_get_record, but never fatal on a host with DNS lookups disabled. */
function dns_txt(string $host): array {
    if (!function_exists('dns_get_record')) return [];
    $r = @dns_get_record($host, DNS_TXT);
    if (!is_array($r)) return [];
    $out = [];
    foreach ($r as $row) {
        $t = $row['txt'] ?? (isset($row['entries']) ? implode('', (array)$row['entries']) : '');
        if ($t !== '') $out[] = (string)$t;
    }
    return $out;
}
function dns_cname(string $host): string {
    if (!function_exists('dns_get_record')) return '';
    $r = @dns_get_record($host, DNS_CNAME);
    return is_array($r) && !empty($r[0]['target']) ? (string)$r[0]['target'] : '';
}
function dns_mx(string $host): array {
    $mx = [];
    if (function_exists('getmxrr')) { $w = []; @getmxrr($host, $mx, $w); }
    return $mx;
}

$dnsAvailable = function_exists('dns_get_record');

/* ---- SPF ---- */
$spf = '';
foreach (dns_txt($domain) as $t) { if (stripos($t, 'v=spf1') === 0) $spf = $t; }
$spfOk    = $spf !== '';
$spfHoster= $spfOk && (stripos($spf, 'hostinger') !== false || stripos($spf, 'titan') !== false);

/* ---- DKIM (Hostinger publishes three CNAMEs) ---- */
$dkim = [];
foreach (['hostingermail-a', 'hostingermail-b', 'hostingermail-c'] as $sel) {
    $dkim[$sel] = dns_cname($sel . '._domainkey.' . $domain);
}
$dkimOk = count(array_filter($dkim)) > 0;

/* ---- DMARC ---- */
$dmarc = '';
foreach (dns_txt('_dmarc.' . $domain) as $t) { if (stripos($t, 'v=DMARC1') === 0) $dmarc = $t; }
$dmarcOk = $dmarc !== '';

/* ---- MX ---- */
$mx = dns_mx($domain);

/* ---- alignment between the login mailbox and the From: address ---- */
$alignOk = $smtpUser !== '' && strtolower(explode('@', $smtpUser)[1] ?? '') === $domain;

$score = ($spfHoster ? 1 : 0) + ($dkimOk ? 1 : 0) + ($dmarcOk ? 1 : 0);

function pill(bool $ok, string $good = 'OK', string $badTxt = 'MISSING'): string {
    return $ok
        ? '<span style="background:rgba(26,155,90,.14);color:#127a45;padding:3px 10px;border-radius:99px;font-size:11.5px;font-weight:700">✅ ' . e($good) . '</span>'
        : '<span style="background:rgba(214,59,59,.14);color:#a52c2c;padding:3px 10px;border-radius:99px;font-size:11.5px;font-weight:700">❌ ' . e($badTxt) . '</span>';
}
?>
<!doctype html><html lang="en" dir="ltr"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><meta name="theme-color" content="#8a1538">
<title>Junk / DNS check — <?= e(ct('appName', 'en')) ?></title>
<link rel="stylesheet" href="<?= asset('assets/app.css') ?>">
<script src="<?= asset('assets/theme.js') ?>"></script>
<style>
  code,pre{font-family:ui-monospace,Menlo,Consolas,monospace;font-size:12.5px}
  pre{background:var(--surface-3);border-radius:10px;padding:12px 14px;overflow-x:auto;
      white-space:pre-wrap;word-break:break-all;line-height:1.8;margin:8px 0 0}
  .rec{border-top:1px solid var(--line);padding:14px 0}
  .rec:first-of-type{border-top:0}
  .rec b{display:block;font-size:14px;margin-bottom:4px}
  table.dns{width:100%;border-collapse:collapse;font-size:13px;margin-top:6px}
  table.dns td{padding:6px 8px;border-bottom:1px solid var(--line);vertical-align:top}
  table.dns td:first-child{color:var(--muted);white-space:nowrap;width:78px}
</style></head>
<body><div class="app">
<header class="top"><div class="brand"><div class="mark">ث</div><div class="txt">
  <h1>Junk / DNS check</h1><p><?= e(ct('appName', 'en')) ?></p></div></div>
  <button class="iconbtn" id="themeBtn" type="button" aria-label="Light / dark">🌙</button>
  <a class="langbtn" href="admin.php" style="text-decoration:none">← Admin</a>
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


<div class="card">
  <h2>Sending domain: <span dir="ltr"><?= e($domain) ?></span></h2>
  <p class="sub">Mail leaves as <b dir="ltr"><?= e($fromEmail) ?></b>, authenticated to
     <b dir="ltr"><?= e($smtpHost) ?></b> as <b dir="ltr"><?= e($smtpUser) ?></b>.</p>

  <?php if (!$dnsAvailable): ?>
    <p class="sub" style="color:#d63b3b">This server has DNS lookups switched off, so the checks below could not run.
       Use an outside checker such as mxtoolbox.com instead.</p>
  <?php else: ?>
    <p style="font-size:15px;margin:10px 0 0">
      <?= $score === 3
          ? '✅ <b>All three records are published.</b> If mail still lands in Junk it is reputation, not authentication — see the last box.'
          : '⚠️ <b>' . (3 - $score) . ' of the 3 records ' . (3 - $score === 1 ? 'is' : 'are') . ' missing.</b> That is the usual reason messages go to Junk.' ?>
    </p>
  <?php endif; ?>
</div>

<div class="card">
  <h2>1 · SPF <?= pill($spfHoster, 'published', $spfOk ? 'wrong value' : 'missing') ?></h2>
  <p class="sub">Says which servers are allowed to send as <?= e($domain) ?>.</p>
  <?php if ($spf !== ''): ?><pre><?= e($spf) ?></pre><?php endif; ?>
  <?php if (!$spfHoster): ?>
    <div class="rec"><b>Add this TXT record</b>
      <table class="dns">
        <tr><td>Type</td><td>TXT</td></tr>
        <tr><td>Name</td><td><code>@</code></td></tr>
        <tr><td>Value</td><td><code>v=spf1 include:_spf.mail.hostinger.com ~all</code></td></tr>
        <tr><td>TTL</td><td>3600</td></tr>
      </table>
      <p class="sub" style="margin:8px 0 0">There must be exactly <b>one</b> SPF record on the domain.
         If one already exists, edit it — do not add a second.</p>
    </div>
  <?php endif; ?>
</div>

<div class="card">
  <h2>2 · DKIM <?= pill($dkimOk, 'published', 'missing') ?></h2>
  <p class="sub">A signature the mail server adds, which the receiver checks against DNS.</p>
  <table class="dns">
    <?php foreach ($dkim as $sel => $target): ?>
      <tr><td dir="ltr"><?= e($sel) ?></td>
          <td dir="ltr"><?= $target !== '' ? '✅ ' . e($target) : '<span style="color:#d63b3b">— not found</span>' ?></td></tr>
    <?php endforeach; ?>
  </table>
  <?php if (!$dkimOk): ?>
    <div class="rec"><b>Add these three CNAME records</b>
      <pre>hostingermail-a._domainkey   CNAME   hostingermail-a.dkim.mail.hostinger.com
hostingermail-b._domainkey   CNAME   hostingermail-b.dkim.mail.hostinger.com
hostingermail-c._domainkey   CNAME   hostingermail-c.dkim.mail.hostinger.com</pre>
      <p class="sub" style="margin:8px 0 0">In hPanel these are usually offered as a one-click
         “enable DKIM” button under Emails → your domain.</p>
    </div>
  <?php endif; ?>
</div>

<div class="card">
  <h2>3 · DMARC <?= pill($dmarcOk, 'published', 'missing') ?></h2>
  <p class="sub">Tells Hotmail and Gmail what to do when SPF or DKIM fails — and, more importantly,
     tells them this domain is looked after. Outlook.com weighs this heavily.</p>
  <?php if ($dmarc !== ''): ?><pre><?= e($dmarc) ?></pre><?php endif; ?>
  <?php if ($dmarcOk && (stripos($dmarc, 'rua=') === false || stripos($dmarc, 'p=none') !== false)): ?>
    <div class="rec"><b>It is published, but it is the weakest form</b>
      <p class="sub" style="margin:0 0 6px"><code>p=none</code> with no reporting address tells Microsoft
         “I have heard of DMARC” and nothing more. Since SPF and DKIM both pass here, the stronger
         policy is safe and carries noticeably more weight with Outlook and Hotmail:</p>
      <table class="dns">
        <tr><td>Type</td><td>TXT</td></tr>
        <tr><td>Name</td><td><code>_dmarc</code></td></tr>
        <tr><td>Value</td><td><code>v=DMARC1; p=quarantine; rua=mailto:<?= e($fromEmail) ?>; adkim=r; aspf=r; fo=1; pct=100</code></td></tr>
      </table>
      <p class="sub" style="margin:8px 0 0">Edit the existing record — do not add a second one.</p>
    </div>
  <?php endif; ?>
  <?php if (!$dmarcOk): ?>
    <div class="rec"><b>Add this TXT record</b>
      <table class="dns">
        <tr><td>Type</td><td>TXT</td></tr>
        <tr><td>Name</td><td><code>_dmarc</code></td></tr>
        <tr><td>Value</td><td><code>v=DMARC1; p=none; rua=mailto:<?= e($fromEmail) ?>; adkim=r; aspf=r; pct=100</code></td></tr>
        <tr><td>TTL</td><td>3600</td></tr>
      </table>
      <p class="sub" style="margin:8px 0 0">Start at <code>p=none</code>. After a few weeks of clean reports,
         move to <code>p=quarantine</code>.</p>
    </div>
  <?php endif; ?>
</div>

<div class="card">
  <h2>4 · Mailbox / From address <?= pill($alignOk, 'aligned', 'different domain') ?></h2>
  <table class="dns">
    <tr><td>Login</td><td dir="ltr"><?= e($smtpUser) ?></td></tr>
    <tr><td>From</td><td dir="ltr"><?= e($fromEmail) ?></td></tr>
    <tr><td>MX</td><td dir="ltr"><?= $mx ? e(implode(', ', $mx)) : '—' ?></td></tr>
  </table>
  <p class="sub" style="margin:10px 0 0">
    Both addresses must be on <b><?= e($domain) ?></b> for DMARC to line up. Sending as
    <code>contact@</code> while logging in as <code>admin@</code> is fine — they share the domain.
    What must never happen is a From address on gmail.com or hotmail.com.
  </p>
</div>

<?php
/* --------------------------------------------------------------------
   The site writing to its own mailbox is a different problem from the
   site writing to a customer, and it has a different fix. Show it
   separately, because the two get confused constantly.
   -------------------------------------------------------------------- */
$notify   = owner_email();
$selfMail = strcasecmp(trim($notify), trim($fromEmail)) === 0;
?>
<div class="card">
  <h2>5 · Notifications to yourself <?= pill(!$selfMail, 'separate address', 'same address') ?></h2>
  <table class="dns">
    <tr><td>Sends as</td><td dir="ltr"><?= e($fromEmail) ?></td></tr>
    <tr><td>Notifies</td><td dir="ltr"><?= e($notify) ?></td></tr>
  </table>
  <?php if ($selfMail): ?>
  <p class="sub" style="margin:10px 0 0;line-height:1.95">
    <b>New requests are sent from this address to the same address.</b> A message whose
    From and To are identical is one of the oldest spam patterns there is, and every
    filter — including the one on this very mailbox — scores it for that. It is the most
    likely reason a <i>new request</i> notification sits in Spam while the customer's own
    copy arrives normally.
    <br><br>
    <b>The fix takes a minute and needs no code:</b> open
    <a href="admin.php?page=content&amp;g=contact">✏️ Site content → 📞 Contact &amp; links</a>
    and set <b>“Email that receives new requests”</b> to a <i>different</i> mailbox from
    <code dir="ltr"><?= e($fromEmail) ?></code>. Either create
    <code dir="ltr">alerts@<?= e($domain) ?></code> in hPanel, or simply use a personal
    Gmail — this address is never shown to customers, so anything works.
  </p>
  <?php else: ?>
  <p class="sub" style="margin:10px 0 0">
    Good — notifications go to a different mailbox from the sending address, so nothing
    the site sends has an identical From and To.
  </p>
  <?php endif; ?>
  <p class="sub" style="margin:10px 0 0">
    The duplicate <code>[نسخة/copy]</code> message is now off by default
    (<code>copy_owner</code> in <code>config.php</code>). Two near-identical messages
    arriving together is itself a scored pattern, and the new-request notification
    already contains everything that copy did.
  </p>
</div>

<div class="card">
  <h2>6 · Read the spam score of a message that was filed as Junk</h2>
  <p class="sub" style="line-height:1.95">
    Guessing is slow. This server runs SpamAssassin and it writes its verdict into every
    message it files, so the exact reason is sitting in the email itself.
    <br><br>
    In <b>mail.hostinger.com</b>, open the message in <b>Spam</b> → the <b>⋯</b> menu →
    <b>Show source</b> (or <i>View source</i>), then look near the top for lines beginning
    <code dir="ltr">X-Spam-</code>:
  </p>
  <pre style="background:var(--surface-3);padding:12px 14px;border-radius:11px;overflow:auto;font-size:12px;direction:ltr">X-Spam-Status: Yes, score=6.2 required=5.0
	tests=FROM_EQ_TO,HTML_IMAGE_RATIO_02,RDNS_NONE
X-Spam-Report: ...</pre>
  <p class="sub" style="line-height:1.95">
    The <code dir="ltr">tests=</code> list names every rule that fired and what each one cost.
    That turns this from guesswork into a short, fixable list.
  </p>
</div>

<div class="card" style="border-color:#c9a227">
  <h2>7 · When everything above is green and mail still lands in Junk</h2>
  <p class="sub" style="line-height:1.95">
    At that point it is not authentication and it is not the message. It is
    <b>reputation</b>, and reputation is not a setting — it is a history the
    domain has not had time to build.
    <br><br>
    A domain days old, sending from a shared hosting relay, is an unknown sender.
    Gmail and Microsoft give unknown senders the benefit of the doubt only after
    they have seen mail that people actually open and keep. That is why a
    recipient who once pressed <i>Not spam</i> keeps receiving fine while every
    <b>new</b> address starts in the spam folder again: the trust is being earned
    per-recipient instead of per-domain.
  </p>
  <p class="sub" style="line-height:1.95">
    Two things move it, and only two:
  </p>
  <table class="dns">
    <tr><td style="white-space:nowrap"><b>Time&nbsp;+&nbsp;engagement</b></td>
        <td>Every message opened, replied to, or dragged out of Junk teaches the
            filter. Two to four weeks of real customers is the usual turn.</td></tr>
    <tr><td style="white-space:nowrap"><b>A sending service</b></td>
        <td>A transactional provider — Brevo, Mailgun, Postmark, Amazon SES —
            sends from IP addresses whose reputation is already established and
            actively defended. You keep your own address and your own DKIM; only
            the road changes. Free tiers cover a few hundred messages a day,
            which is far above what this site sends. This is the switch that
            usually ends the problem outright.</td></tr>
  </table>
  <p class="sub" style="margin:12px 0 0;line-height:1.95">
    Switching means: create the account, publish the DKIM and SPF records it
    gives you, then change <code>host</code>, <code>port</code>, <code>user</code>
    and <code>pass</code> in the <code>smtp</code> block of <code>config.php</code>.
    Nothing else in the site changes.
    <br><br>
    Free and worth doing either way: <b>Google Postmaster Tools</b> shows what
    Gmail thinks of the domain, and <b>Microsoft SNDS / JMRP</b> does the same
    for Outlook and Hotmail. Without them this is guesswork.
  </p>
</div>

<div class="card">
  <h2>8 · Hotmail / Outlook in particular</h2>
  <p class="sub" style="line-height:1.95">
    Microsoft is stricter than everyone else with a young domain, and the three records above are
    only the entry ticket. After they are live:
  </p>
  <ol style="font-size:14px;line-height:2;padding-inline-start:20px;margin:6px 0 0">
    <li>Open the message once from the Junk folder and press <b>“Not junk”</b>, then add
        <code dir="ltr"><?= e($fromEmail) ?></code> to Contacts. This alone fixes it for that mailbox.</li>
    <li>Sign up for <b>Microsoft SNDS</b> and the <b>Junk Mail Reporting Programme</b>
        (<code dir="ltr">sendersupport.olc.protection.outlook.com</code>) — free, and it is how Microsoft
        tells you why they are filtering you.</li>
    <li>Send a small number of messages a day for the first two weeks. A brand-new domain that
        suddenly sends in volume looks exactly like a spam run.</li>
    <li>Never send from a “no-reply” address, and always keep a real reply address — currently
        <code dir="ltr"><?= e(cv('contactEmail') ?: $fromEmail) ?></code>.</li>
  </ol>
  <p class="sub" style="margin:12px 0 0">
    Send a test to <a href="https://www.mail-tester.com" target="_blank" rel="noopener" style="color:var(--brand);font-weight:700">mail-tester.com</a>
    from <a href="mailtest.php" style="color:var(--brand);font-weight:700">Mail check</a> — it scores the message out of 10 and names whatever is still wrong.
  </p>
</div>

<?php
/* ---- did any message ever leave without authentication? ---- */
$log      = (string)@file_get_contents(DATA_DIR . '/log.txt');
$fellBack = substr_count($log, 'fell back to PHP mail()');
$smtpFail = substr_count($log, 'SMTP FAIL');
$smtpOk   = substr_count($log, 'SMTP OK');
?>
<div class="card">
  <h2>9 · How the last messages actually left</h2>
  <table class="dns">
    <tr><td>Signed</td><td><?= (int)$smtpOk ?> sent over authenticated SMTP <?= $smtpOk ? '✅' : '' ?></td></tr>
    <tr><td>Failed</td><td><?= (int)$smtpFail ?> SMTP failures</td></tr>
    <tr><td>Fallback</td><td><?= $fellBack
        ? '<b style="color:#d63b3b">' . (int)$fellBack . ' message(s) went out through PHP mail()</b>'
        : 'never used ✅' ?></td></tr>
  </table>
  <?php if ($fellBack): ?>
    <p class="sub" style="margin:10px 0 0;color:#a52c2c">
      When SMTP stalls the app falls back to PHP <code>mail()</code> so a message is never lost — but that
      path leaves from the web server’s own IP, which is <b>not</b> covered by the SPF record and carries no
      DKIM signature. Those messages fail authentication and go straight to Junk. If this number keeps
      rising, the SMTP connection is the thing to fix, not the DNS.
    </p>
  <?php endif; ?>
</div>

<div class="card">
  <h2>10 · What was changed inside the message itself</h2>
  <p class="sub">Authentication was never the whole story. An Outlook filter also scores the shape of the
     message, and the earlier build tripped three of its rules. All three are fixed in this version:</p>
  <ol style="font-size:14px;line-height:2;padding-inline-start:20px;margin:6px 0 0">
    <li><b>A plain-text part is now included.</b> An HTML-only message is the single most common
        “bulk mail” signal; real correspondence always carries both.</li>
    <li><b>Every part is base64 with 76-character lines.</b> The old build sent the HTML as one
        unbroken line thousands of characters long, which breaks RFC 5322 and scores badly.</li>
    <li><b>List-Unsubscribe is set</b>, along with Date, Message-ID and a per-message reference, so
        Gmail stops collapsing separate valuations into one thread.</li>
  </ol>
</div>

</main></div></body></html>
