<?php
/* ============================================================
   archive.php — the permanent record: every request ever made,
   with customer, car, price and timestamps. No photos here, so
   it stays useful long after the images are auto-deleted.
   ============================================================ */
declare(strict_types=1);
require __DIR__ . '/lib.php';
ensure_dirs();
session_start();

$C   = cfg();
$msg = '';

if (isset($_GET['logout'])) { $_SESSION = []; session_destroy(); header('Location: archive.php'); exit; }

if (($_POST['action'] ?? '') === 'login') {
    if (admin_login_ok((string)($_POST['username'] ?? ''), (string)($_POST['password'] ?? ''))) {
        session_regenerate_id(true);
        $_SESSION['eyc_admin'] = true;
        header('Location: archive.php'); exit;
    }
    $msg = 'Wrong username or password';
}

if (empty($_SESSION['eyc_admin'])) {
    ?><!doctype html><html lang="en"><head><meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow">
    <title>Archive — ثـمـــن مــوتــرك</title><link rel="stylesheet" href="<?= asset('assets/app.css') ?>">
<script src="<?= asset('assets/theme.js') ?>"></script></head>
    <body><div class="app" style="padding-bottom:24px">
    <header class="top"><div class="brand"><div class="mark">ث</div><div class="txt">
      <h1>Archive</h1><p>ثـمـــن مــوتــرك — السجل</p></div></div>
      <button class="iconbtn" id="themeBtn" type="button" aria-label="Light / dark">🌙</button>
  </header>
    <main><div class="card"><h2>Sign in</h2>
      <form method="post" action="archive.php"><input type="hidden" name="action" value="login">
      <div class="field"><label>Username</label><input type="text" name="username" autocomplete="username"
           dir="ltr" autocapitalize="none" autocorrect="off" spellcheck="false" autofocus></div>
      <div class="field"><label>Password</label><input type="password" name="password" autocomplete="current-password" dir="ltr"></div>
      <div class="err"><?= e($msg) ?></div><button class="btn" type="submit">Sign in</button></form>
    </div></main></div></body></html><?php
    exit;
}

/* ---------------- data ---------------- */
$rows = db_read();
usort($rows, function ($a, $b) { return strcmp((string)($b['created'] ?? ''), (string)($a['created'] ?? '')); });

$q      = trim((string)($_GET['q'] ?? ''));
$status = (string)($_GET['status'] ?? '');
$from   = (string)($_GET['from'] ?? '');
$to     = (string)($_GET['to'] ?? '');

$filtered = array_values(array_filter($rows, function ($r) use ($q, $status, $from, $to) {
    if ($status === 'done'   && ($r['status'] ?? '') !== 'done')   return false;
    if ($status === 'review' && ($r['status'] ?? '') !== 'review') return false;

    $day = substr((string)($r['created'] ?? ''), 0, 10);
    if ($from !== '' && $day < $from) return false;
    if ($to   !== '' && $day > $to)   return false;

    if ($q === '') return true;
    $hay = implode(' ', [
        $r['id'] ?? '', $r['name'] ?? '', $r['phone'] ?? '', $r['email'] ?? '',
        $r['car_make'] ?? '', $r['car_class'] ?? '', $r['car_model'] ?? '', $r['car_year'] ?? '',
        $r['registration'] ?? '', $r['chassis'] ?? '', price_display($r),
    ]);
    return mb_stripos($hay, $q) !== false;
}));

/* ---------------- CSV download ---------------- */
if (isset($_GET['csv'])) {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="thaman-sayaratak-archive-' . gmdate('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");   // BOM so Excel shows Arabic correctly
    fputcsv($out, ['Date','Time','Request ID','Customer','Mobile','Email','Make','Class','Model','Year',
                   'Mileage','Registration','Chassis','Status','Price','Currency','Priced on','Photos','Videos','Files kept until','Notes']);
    foreach ($filtered as $r) {
        fputcsv($out, [
            fmt_dt($r['created'] ?? null, 'Y-m-d'),
            fmt_dt($r['created'] ?? null, 'H:i'),
            $r['id'] ?? '', $r['name'] ?? '', $r['phone'] ?? '', $r['email'] ?? '',
            $r['car_make'] ?? '', $r['car_class'] ?? '', $r['car_model'] ?? '', $r['car_year'] ?? '',
            $r['mileage'] ?? '', $r['registration'] ?? '', $r['chassis'] ?? '',
            ($r['status'] ?? '') === 'done' ? 'Priced' : 'Under review',
            price_display($r), $C['currency_en'],
            fmt_dt($r['done_at'] ?? null, 'Y-m-d H:i'),
            count((array)($r['photos'] ?? [])), count((array)($r['videos'] ?? [])),
            fmt_dt($r['expires_at'] ?? null, 'Y-m-d'),
            str_replace(["\r", "\n"], ' ', (string)($r['notes'] ?? '')),
        ]);
    }
    fclose($out);
    exit;
}

/* ---------------- totals ---------------- */
$countDone = 0; $sumDone = 0.0;
foreach ($filtered as $r) {
    if (($r['status'] ?? '') === 'done') {
        $countDone++;
        $sumDone += (float)str_replace([',', ' '], '', (string)($r['price'] ?? '0'));
    }
}
$qs = function (array $over = []) use ($q, $status, $from, $to) {
    return http_build_query(array_merge(['q' => $q, 'status' => $status, 'from' => $from, 'to' => $to], $over));
};
?>
<!doctype html><html lang="en" dir="ltr"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="robots" content="noindex,nofollow"><meta name="theme-color" content="var(--brand)">
<title>Archive — ثـمـــن مــوتــرك</title>
<link rel="stylesheet" href="<?= asset('assets/app.css') ?>">
<script src="<?= asset('assets/theme.js') ?>"></script>
<style>
  .app{padding-bottom:24px}
  .tools{display:grid;grid-template-columns:1fr 1fr;gap:10px}
  @media (min-width:820px){ .tools{grid-template-columns:repeat(3,1fr)} }
  .tools .wide{grid-column:1 / -1}
  .tools > *{min-width:0}          /* grid items must be allowed to shrink */
  .tools input,.tools select{max-width:100%}
  .scroller{overflow-x:auto;-webkit-overflow-scrolling:touch;margin:0 -18px;padding:0 18px}
  table.arch{border-collapse:collapse;font-size:13.5px;min-width:940px;width:100%}
  table.arch th{position:sticky;top:0;background:var(--surface-3);color:var(--muted);font-size:11.5px;
                text-transform:uppercase;letter-spacing:.4px;text-align:start;padding:10px 12px;white-space:nowrap;z-index:2}
  table.arch td{padding:11px 12px;border-bottom:1px solid var(--bg);vertical-align:top;white-space:nowrap}
  table.arch tr:hover td{background:var(--surface-2)}
  table.arch td.wrap{white-space:normal;min-width:170px}
  .chip{display:inline-block;padding:3px 9px;border-radius:99px;font-size:11px;font-weight:700}
  .chip.r{background:rgba(214,59,59,.12);color:#d63b3b}
  .chip.g{background:rgba(26,155,90,.12);color:#1a9b5a}
  .money{font-weight:800;color:var(--brand)}
  .muted{color:var(--muted)}
  .stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:10px;margin-bottom:14px}
  .stat{background:var(--card);border:1px solid var(--line);border-radius:14px;padding:14px 16px}
  .stat b{display:block;font-size:22px;font-weight:800;color:var(--brand);line-height:1.3}
  .stat span{font-size:12px;color:var(--muted)}
  a.rid{font-weight:800;letter-spacing:2px;color:var(--brand);text-decoration:none}
  a.rid:hover{text-decoration:underline}
  .gone{font-size:11px;color:#c0392b}
  @media print{ .noprint{display:none} .scroller{overflow:visible;margin:0;padding:0} table.arch{min-width:0;font-size:11px} }
</style></head>
<body><div class="app">

<header class="top">
  <div class="brand"><div class="mark">ث</div><div class="txt">
    <h1>Archive</h1><p>السجل الدائم — بدون صور</p></div></div>
  <button class="iconbtn" id="themeBtn" type="button" aria-label="Light / dark">🌙</button>
  <a class="langbtn" href="admin.php" style="text-decoration:none;margin-inline-end:6px">Admin</a>
  <a class="langbtn" href="?logout=1" style="text-decoration:none">Logout</a>
</header>

<main style="max-width:1200px">

  <div class="stats">
    <div class="stat"><b><?= count($filtered) ?></b><span>requests shown / الطلبات</span></div>
    <div class="stat"><b><?= $countDone ?></b><span>priced / تم تسعيرها</span></div>
    <div class="stat"><b><?= $countDone ? number_format($sumDone) : 0 ?></b><span>total value <?= e($C['currency_en']) ?></span></div>
    <div class="stat"><b><?= $countDone ? number_format($sumDone / max(1, $countDone)) : 0 ?></b><span>average price</span></div>
  </div>

  <div class="card noprint">
    <form method="get" class="tools">
      <div class="field wide" style="margin:0">
        <label>Search — name, mobile, ID, car, plate…</label>
        <input name="q" value="<?= e($q) ?>" placeholder="Ahmed / 5555 / Tahoe / QA-882…">
      </div>
      <div class="field" style="margin:0"><label>Status</label>
        <select name="status">
          <option value=""       <?= $status === ''       ? 'selected' : '' ?>>All</option>
          <option value="review" <?= $status === 'review' ? 'selected' : '' ?>>Under review</option>
          <option value="done"   <?= $status === 'done'   ? 'selected' : '' ?>>Priced</option>
        </select></div>
      <div class="field" style="margin:0"><label>From</label><input type="date" name="from" value="<?= e($from) ?>"></div>
      <div class="field" style="margin:0"><label>To</label><input type="date" name="to" value="<?= e($to) ?>"></div>
      <div class="wide" style="display:flex;gap:10px;flex-wrap:wrap">
        <button class="btn" type="submit" style="width:auto;padding-inline:26px">Search</button>
        <a class="btn ghost" href="archive.php" style="width:auto;padding-inline:26px;text-decoration:none;line-height:1.4">Reset</a>
        <a class="btn gold" href="?<?= e($qs(['csv' => 1])) ?>" style="width:auto;padding-inline:26px;text-decoration:none;line-height:1.4">⬇ Excel / CSV</a>
        <button class="btn ghost" type="button" onclick="window.print()" style="width:auto;padding-inline:26px">Print</button>
      </div>
    </form>
  </div>

  <div class="card">
    <h2>All requests</h2>
    <p class="sub">Newest first. Photos disappear after 3–7 days; these details never do.</p>

    <?php if (!$filtered): ?>
      <p class="sub" style="margin:0">Nothing matches that search.</p>
    <?php else: ?>
    <div class="scroller">
      <table class="arch">
        <thead><tr>
          <th>Date &amp; time</th><th>ID</th><th>Customer</th><th>Mobile</th>
          <th>Car</th><th>Year</th><th>Mileage</th><th>Plate</th>
          <th>Status</th><th>Price</th><th>Priced on</th><th>Files</th>
        </tr></thead>
        <tbody>
        <?php foreach ($filtered as $r):
          $done = ($r['status'] ?? '') === 'done'; ?>
          <tr>
            <td><?= e(fmt_dt($r['created'] ?? null)) ?></td>
            <td><a class="rid" href="admin.php?open=<?= e((string)$r['id']) ?>"><?= e((string)$r['id']) ?></a></td>
            <td class="wrap"><?= e((string)($r['name'] ?? '')) ?><br>
                <span class="muted" style="font-size:11.5px"><?= e((string)($r['email'] ?? '')) ?></span></td>
            <td><a href="https://wa.me/<?= e(preg_replace('/[^0-9]/', '', (string)($r['phone'] ?? ''))) ?>"
                   style="color:var(--brand);font-weight:700"><?= e((string)($r['phone'] ?? '')) ?></a></td>
            <td class="wrap"><?= e(trim(($r['car_make'] ?? '') . ' ' . ($r['car_class'] ?? '')
                  . ($r['car_model'] ? ' ' . $r['car_model'] : ''))) ?></td>
            <td><?= e((string)($r['car_year'] ?? '')) ?></td>
            <td><?= e((string)($r['mileage'] ?? '') ?: '—') ?></td>
            <td><?= e((string)($r['registration'] ?? '') ?: '—') ?></td>
            <td><span class="chip <?= $done ? 'g' : 'r' ?>"><?= $done ? 'PRICED' : 'REVIEW' ?></span></td>
            <td><?= $done && has_price($r)
                  ? '<span class="money">' . e(price_display($r)) . '</span> <span class="muted">' . e($C['currency_en']) . '</span>'
                  : '<span class="muted">—</span>' ?></td>
            <td><?= e(fmt_dt($r['done_at'] ?? null)) ?></td>
            <td><?= count((array)($r['photos'] ?? [])) ?>📷<?= count((array)($r['videos'] ?? [])) ? ' ' . count((array)$r['videos']) . '🎬' : '' ?>
                <?= !empty($r['files_purged']) ? '<br><span class="gone">deleted</span>' : '' ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>

  <p class="sub" style="text-align:center">
    ثـمـــن مــوتــرك · <?= count($rows) ?> record(s) in total · generated <?= e(fmt_dt(gmdate('c'))) ?>
  </p>
</main>
</div></body></html>
