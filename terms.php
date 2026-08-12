<?php
/* الشروط والأحكام — Terms & Conditions
   The words are edited in admin.php → Site content → Terms & Conditions. */
declare(strict_types=1);
require __DIR__ . '/lib.php';
require __DIR__ . '/legalpage.php';

render_legal_page('termsTitle', 'termsBody');
