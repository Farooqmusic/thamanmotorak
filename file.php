<?php
/* ============================================================
   file.php — serves an uploaded photo/video to the admin only.
   ============================================================ */
declare(strict_types=1);
require __DIR__ . '/lib.php';
session_start();

$id = strtoupper(preg_replace('/[^A-Z0-9]/i', '', (string)($_GET['id'] ?? '')));
$f  = basename((string)($_GET['f'] ?? ''));

/* Access: either a logged-in admin, or the signed link that was emailed. */
$allowed = !empty($_SESSION['eyc_admin']) || ($id !== '' && link_ok($id, (string)($_GET['k'] ?? '')));
if (!$allowed) { http_response_code(403); exit('Forbidden'); }

if ($id === '' || $f === '' || strpos($f, '..') !== false) { http_response_code(400); exit('Bad request'); }

$path = UPLOAD_DIR . '/' . $id . '/' . $f;
$real = realpath($path);
if ($real === false || strpos($real, realpath(UPLOAD_DIR)) !== 0 || !is_file($real)) {
    http_response_code(404); exit('Not found');
}

$ext = strtolower(pathinfo($real, PATHINFO_EXTENSION));
$types = [
  'jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp',
  'heic'=>'image/heic','heif'=>'image/heif',
  'mp4'=>'video/mp4','mov'=>'video/quicktime','m4v'=>'video/x-m4v','3gp'=>'video/3gpp','webm'=>'video/webm',
];
header('Content-Type: ' . ($types[$ext] ?? 'application/octet-stream'));
header('Content-Length: ' . filesize($real));
header('Cache-Control: private, max-age=600');
if (isset($_GET['dl'])) header('Content-Disposition: attachment; filename="' . $id . '-' . $f . '"');
readfile($real);
