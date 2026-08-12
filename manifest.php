<?php
/* ============================================================
   manifest.php — the PWA manifest, built from the site name the
   client set in the control panel, so the icon on the phone's home
   screen carries whatever he renamed the site to.
   ============================================================ */
declare(strict_types=1);
require __DIR__ . '/lib.php';

header('Content-Type: application/manifest+json; charset=UTF-8');
header('Cache-Control: public, max-age=300');

echo json_encode([
    'name'             => trim(ct('appName', 'ar') . ' | ' . ct('appName', 'en')),
    'short_name'       => ct('appName', 'ar'),
    'description'      => trim(ct('metaDescription', 'ar') . ' — ' . ct('metaDescription', 'en')),
    'lang'             => 'ar',
    'dir'              => 'rtl',
    'start_url'        => './index.php',
    'scope'            => './',
    'display'          => 'standalone',
    'orientation'      => 'portrait',
    'background_color' => '#8a1538',
    'theme_color'      => '#8a1538',
    'categories'       => ['business', 'utilities', 'finance'],
    'icons' => [
        ['src' => asset('icons/icon-72.png'),      'sizes' => '72x72',   'type' => 'image/png'],
        ['src' => asset('icons/icon-96.png'),      'sizes' => '96x96',   'type' => 'image/png'],
        ['src' => asset('icons/icon-144.png'),     'sizes' => '144x144', 'type' => 'image/png'],
        ['src' => asset('icons/icon-152.png'),     'sizes' => '152x152', 'type' => 'image/png'],
        ['src' => asset('icons/icon-192.png'),     'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => asset('icons/icon-512.png'),     'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => asset('icons/maskable-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
    ],
    'shortcuts' => [[
        'name'  => 'حالة الطلب / Check status',
        'url'   => './index.php#status',
        'icons' => [['src' => asset('icons/icon-96.png'), 'sizes' => '96x96']],
    ]],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
