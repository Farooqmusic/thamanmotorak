<?php
/* ============================================================
   cleanup.php — deletes photos/videos once the customer's chosen
   retention window (3 or 7 days) has passed.

   Run daily from cPanel → Cron Jobs:
       /usr/local/bin/php /home/USER/public_html/car/cleanup.php

   Or open it in a browser with the secret key:
       https://thamanmotorak.com/cleanup.php?key=YOUR_ADMIN_PASSWORD
   ============================================================ */
declare(strict_types=1);
require __DIR__ . '/lib.php';
ensure_dirs();

$cli = (PHP_SAPI === 'cli');
if (!$cli) {
    if (!hash_equals((string)cfg('admin_password'), (string)($_GET['key'] ?? ''))) {
        http_response_code(403); exit('Forbidden');
    }
    header('Content-Type: text/plain; charset=UTF-8');
}

$now     = time();
$deleted = 0;
$freed   = 0;
$report  = [];

db_write(function (array $rows) use ($now, &$deleted, &$freed, &$report) {
    foreach ($rows as &$r) {
        if (!empty($r['files_purged'])) continue;
        if (strtotime($r['expires_at']) > $now) continue;

        $dir = UPLOAD_DIR . '/' . preg_replace('/[^A-Z0-9]/', '', strtoupper($r['id']));
        if (is_dir($dir)) {
            foreach (array_diff(scandir($dir) ?: [], ['.', '..']) as $f) {
                $p = $dir . '/' . $f;
                if (is_file($p)) { $freed += (int)filesize($p); @unlink($p); $deleted++; }
            }
            @rmdir($dir);
        }
        $r['files_purged'] = true;
        $r['purged_at']    = gmdate('c');
        $report[] = $r['id'];
    }
    unset($r);
    return $rows;
});

$line = 'cleanup: ' . $deleted . ' file(s) deleted, ' . human_size($freed) . ' freed'
      . ($report ? ', requests: ' . implode(', ', $report) : '');
log_line($line);
echo $line . "\n";
