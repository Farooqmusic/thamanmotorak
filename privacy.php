<?php
/* سياسة الخصوصية — Privacy Policy
   The words are edited in admin.php → Site content → Privacy Policy. */
declare(strict_types=1);
require __DIR__ . '/lib.php';
require __DIR__ . '/legalpage.php';

render_legal_page('privacyTitle', 'privacyBody');
