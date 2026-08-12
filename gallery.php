<?php
/* ============================================================
   gallery.php — the organized photo page linked from Khalid's email.
   Opens with the signed link (?id=XXXXXX&k=TOKEN) or an admin session.
   ============================================================ */
declare(strict_types=1);
require __DIR__ . '/lib.php';
ensure_dirs();
session_start();

$id = strtoupper(preg_replace('/[^A-Z0-9]/i', '', (string)($_GET['id'] ?? '')));
$k  = (string)($_GET['k'] ?? '');
$isAdmin = !empty($_SESSION['eyc_admin']);

if ($id === '' || (!$isAdmin && !link_ok($id, $k))) { http_response_code(403); exit('Forbidden'); }

$r = find_request($id);
if (!$r) { http_response_code(404); exit('Not found'); }

$q = $isAdmin ? '' : '&k=' . urlencode($k);          // token passed on to file.php
$byslot = [];
foreach ($r['photos'] as $p) $byslot[$p['slot']] = $p['file'];
$purged = !empty($r['files_purged']);
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="robots" content="noindex,nofollow">
<meta id="tc" name="theme-color" content="#8a1538">
<title><?= e($id) ?> — <?= e(car_title($r)) ?></title>
<link rel="stylesheet" href="<?= asset('assets/app.css') ?>">
<script src="<?= asset('assets/theme.js') ?>"></script>
<style>
  *{box-sizing:border-box}
  body{margin:0;background:var(--bg);color:var(--ink);
       font-family:"Segoe UI",system-ui,-apple-system,"Noto Naskh Arabic",Tahoma,Arial,sans-serif;
       line-height:1.6;overflow-x:hidden}
  .wrap{max-width:1080px;margin:0 auto;padding:0 14px 40px}
  header{background:linear-gradient(180deg,var(--brand),var(--brand-2));color:#fff;padding:22px 0 24px;margin-bottom:18px}
  header .wrap{padding-bottom:0}
  .rid{display:inline-block;background:var(--gold);color:var(--brand);font-weight:800;letter-spacing:5px;
       padding:6px 16px;border-radius:99px;font-size:18px}
  h1{margin:12px 0 4px;font-size:24px}
  header p{margin:0;opacity:.8;font-size:14px}
  .card{background:var(--card);border:1px solid var(--line);border-radius:16px;padding:18px;margin-bottom:16px;
        box-shadow:0 1px 2px rgba(16,32,48,.05),0 8px 22px rgba(16,32,48,.06)}
  .card h2{margin:0 0 12px;font-size:17px}
  table.kv{width:100%;border-collapse:collapse;font-size:14.5px;table-layout:fixed}
  table.kv td{padding:9px 0;border-bottom:1px solid var(--line);word-break:break-word}
  table.kv td:first-child{color:var(--muted);width:42%}
  table.kv tr:last-child td{border-bottom:0}
  .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:14px}
  figure{margin:0;background:var(--card);border:1px solid var(--line);border-radius:14px;overflow:hidden}
  figure a{display:block;background:var(--surface-3)}
  figure img{width:100%;display:block;aspect-ratio:4/3;object-fit:cover}
  figure video{width:100%;display:block;background:#000}
  figcaption{padding:11px 14px;font-size:14px;display:flex;justify-content:space-between;
             align-items:center;gap:10px;background:var(--surface-2)}
  figcaption b{font-weight:700}
  figcaption span{color:var(--muted);font-size:12.5px}
  figure.empty{border-style:dashed;background:var(--surface-2)}
  figure.empty .ph{aspect-ratio:4/3;display:grid;place-items:center;color:var(--muted);font-size:13.5px}
  .req{display:inline-block;background:rgba(201,162,39,.16);color:#8a6d10;font-size:11px;
       font-weight:700;padding:2px 8px;border-radius:99px;margin-inline-start:6px}
  .btn{display:inline-block;width:auto;margin:0 6px 8px 0;background:var(--brand);color:#fff;
       text-decoration:none;padding:11px 20px;border-radius:10px;font-weight:700;font-size:14px;
       border:0;cursor:pointer;font-family:inherit}
  .btn.gold{background:var(--gold);color:var(--brand)}
  .warn{background:rgba(214,59,59,.1);color:#a52c2c;padding:12px 15px;border-radius:11px;font-size:14px}
  @media print{
    body{background:#fff} header{background:var(--brand) !important;-webkit-print-color-adjust:exact;print-color-adjust:exact}
    .noprint{display:none} .card{box-shadow:none} .grid{grid-template-columns:1fr 1fr}
  }
</style>
</head>
<body>

<header>
  <div class="wrap">
    <button class="iconbtn noprint" id="themeBtn" type="button" aria-label="Light / dark"
            style="float:inline-end;margin-inline-start:10px">🌙</button>
    <span class="rid"><?= e($id) ?></span>
    <h1 dir="ltr" style="text-align:start"><?= e(car_title($r)) ?></h1>
    <p><?= e($r['name']) ?> · <span dir="ltr"><?= e($r['phone']) ?></span> · <span dir="ltr"><?= e(gmdate('d M Y H:i', strtotime($r['created']))) ?> UTC</span></p>
  </div>
</header>

<div class="wrap">

  <?php if ($purged): ?>
    <div class="card"><div class="warn">
      تم حذف الصور تلقائياً بعد انتهاء مدة الحفظ.<br>
      The photos were deleted automatically once the retention period ended.
    </div></div>
  <?php endif; ?>

  <div class="card">
    <h2>بيانات السيارة / Car details</h2>
    <table class="kv">
      <tr><td>الشركة المصنعة / Make</td><td><?= e($r['car_make']) ?></td></tr>
      <tr><td>الفئة / Class</td><td><?= e($r['car_class'] ?? '') ?></td></tr>
      <tr><td>الموديل / Model</td><td><?= e($r['car_model'] ?: '—') ?></td></tr>
      <tr><td>سنة الصنع / Year</td><td><span dir="ltr"><?= e($r['car_year']) ?></span></td></tr>
      <tr><td>الممشى / Mileage</td><td><span dir="ltr"><?= e($r['mileage'] ?: '—') ?></span></td></tr>
      <tr><td>رقم الاستمارة / Registration</td><td><span dir="ltr"><?= e($r['registration'] ?: '—') ?></span></td></tr>
      <tr><td>رقم الشاصي / Chassis</td><td><span dir="ltr"><?= e($r['chassis'] ?: '—') ?></span></td></tr>
      <tr><td>ملاحظات / Notes</td><td><?= nl2br(e($r['notes'] ?: '—')) ?></td></tr>
      <tr><td>البريد / Email</td><td><a href="mailto:<?= e($r['email']) ?>" style="color:var(--brand)"><?= e($r['email']) ?></a></td></tr>
      <tr><td>تُحذف الملفات / Files deleted on</td><td><span dir="ltr"><?= e(gmdate('d M Y', strtotime($r['expires_at']))) ?> (<?= (int)$r['retention'] ?> days)</span></td></tr>
    </table>
    <p style="margin:16px 0 0" class="noprint">
      <a class="btn gold" href="https://wa.me/<?= e(preg_replace('/[^0-9]/', '', $r['phone'])) ?>">WhatsApp العميل</a>
      <button class="btn" onclick="window.print()">طباعة / Print</button>
      <a class="btn" href="receipt.php?id=<?= e($id) ?><?= $q ?>&dl=1">📄 PDF report</a>
    </p>
  </div>

  <div class="card">
    <h2>الصور مرتبة / Photos in order</h2>
    <div class="grid">
      <?php foreach (slots() as $s):
        $file = $byslot[$s['key']] ?? null; ?>
        <?php if ($file && !$purged): ?>
          <figure>
            <a href="file.php?id=<?= e($id) ?>&f=<?= e($file) ?><?= $q ?>" target="_blank" rel="noopener">
              <img src="file.php?id=<?= e($id) ?>&f=<?= e($file) ?><?= $q ?>" alt="<?= e($s['en']) ?>" loading="lazy">
            </a>
            <figcaption>
              <b><?= e($s['ar']) ?> · <?= e($s['en']) ?></b>
              <span class="noprint"><a href="file.php?id=<?= e($id) ?>&f=<?= e($file) ?><?= $q ?>&dl=1" style="color:var(--brand)">تنزيل</a></span>
            </figcaption>
          </figure>
        <?php else: ?>
          <figure class="empty">
            <div class="ph"><?= $purged ? 'deleted' : 'لم تُرسل / not provided' ?></div>
            <figcaption><b><?= e($s['ar']) ?> · <?= e($s['en']) ?></b>
              <?php if ($s['req']): ?><span class="req">required</span><?php endif; ?></figcaption>
          </figure>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>
  </div>

  <?php if (!empty($r['videos']) && !$purged): ?>
  <div class="card">
    <h2>الفيديو / Video</h2>
    <div class="grid">
      <?php foreach ($r['videos'] as $i => $v): ?>
        <figure>
          <video src="file.php?id=<?= e($id) ?>&f=<?= e($v['file']) ?><?= $q ?>" controls playsinline preload="metadata"></video>
          <figcaption><b>فيديو <?= $i + 1 ?> / Video <?= $i + 1 ?></b><span><?= e(human_size((int)$v['size'])) ?></span></figcaption>
        </figure>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <p style="text-align:center;color:var(--muted);font-size:12.5px;margin:22px 0 0">
    ثـمـــن مــوتــرك · Evaluate Your Car
  </p>
</div>
</body></html>
