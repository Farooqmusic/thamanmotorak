<?php
/* ============================================================
   sitemap.php — the map Google reads to discover the pages.
   Reached as  /sitemap.xml  (see the rewrite in .htaccess) and
   also directly as /sitemap.php if rewriting is off.

   Every page is listed twice, once per language, each entry
   pointing at the other with xhtml:link — that is what tells
   Google the two addresses are translations, not duplicates.
   ============================================================ */
declare(strict_types=1);
require __DIR__ . '/lib.php';
require __DIR__ . '/seo.php';

header('Content-Type: application/xml; charset=UTF-8');

/* a site nobody is allowed to index gets an empty map rather than a wrong one */
if (!seo_indexable()) {
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
       . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>';
    exit;
}

$pages = [
    ['file' => '',            'freq' => 'weekly',  'pri' => '1.0'],
    ['file' => 'terms.php',   'freq' => 'yearly',  'pri' => '0.3'],
    ['file' => 'privacy.php', 'freq' => 'yearly',  'pri' => '0.3'],
];

$lastmod = gmdate('Y-m-d', (int)(@filemtime(APP_ROOT . '/data/content.json') ?: @filemtime(__FILE__) ?: time()));

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . "\n"
   . '        xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";

foreach ($pages as $p) {
    $ar = seo_url($p['file']);
    $en = seo_url($p['file'], 'en');
    foreach (['ar' => $ar, 'en' => $en] as $code => $loc) {
        echo "  <url>\n";
        echo '    <loc>' . e($loc) . "</loc>\n";
        echo '    <xhtml:link rel="alternate" hreflang="ar" href="' . e($ar) . "\"/>\n";
        echo '    <xhtml:link rel="alternate" hreflang="en" href="' . e($en) . "\"/>\n";
        echo '    <xhtml:link rel="alternate" hreflang="x-default" href="' . e($ar) . "\"/>\n";
        echo '    <lastmod>' . $lastmod . "</lastmod>\n";
        echo '    <changefreq>' . $p['freq'] . "</changefreq>\n";
        echo '    <priority>' . ($code === 'ar' ? $p['pri'] : (string)(max(0.1, (float)$p['pri'] - 0.1))) . "</priority>\n";
        echo "  </url>\n";
    }
}

echo '</urlset>';
