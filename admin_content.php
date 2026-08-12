<?php
/* ============================================================
   admin_content.php — the "Site content" part of the control panel.

   This is what lets Khalid rewrite the whole site himself:
   every heading and paragraph on the home page and the info page,
   the Overview block, his phone, email and Instagram link, and the
   full Terms & Conditions and Privacy Policy — in Arabic and in
   English — without touching a single file.

   Included by admin.php.
   ============================================================ */
declare(strict_types=1);

/* ---------------- the language of the panel itself ---------------- */

function admin_lang(): string
{
    if (isset($_GET['alang'])) {
        $l = ($_GET['alang'] === 'en') ? 'en' : 'ar';
        @setcookie('eyc_alang', $l, time() + 31536000, '/');
        $_COOKIE['eyc_alang'] = $l;
        return $l;
    }
    return (($_COOKIE['eyc_alang'] ?? 'en') === 'ar') ? 'ar' : 'en';
}

/** A('عربي', 'English') — prints the one that matches the panel language. */
function A(string $ar, string $en): string
{
    return admin_lang() === 'ar' ? $ar : $en;
}

function admin_dir(): string { return admin_lang() === 'ar' ? 'rtl' : 'ltr'; }

/** Keep the current page/request when a link is built. */
function admin_url(array $params = []): string
{
    $q = array_merge($_GET, $params);
    foreach ($q as $k => $v) if ($v === null || $v === '') unset($q[$k]);
    return 'admin.php' . ($q ? '?' . http_build_query($q) : '');
}

/* ---------------- saving ---------------- */

/**
 * Handles the control-panel POSTs. Returns true when it dealt with the request.
 * $msg / $bad are the green and red banners admin.php already prints.
 */
function admin_content_post(string &$msg, string &$bad): bool
{
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'content_save') {
        $n = content_save($_POST);
        if ($n === 0) {
            $bad = A('⚠️ لم يصل أي حقل — لم يُحفظ شيء.', '⚠️ No fields arrived — nothing was saved.');
            return true;
        }
        /* prove it landed on disk */
        if (!is_file(CONTENT_FILE) || !is_readable(CONTENT_FILE)) {
            $bad = A('❌ تعذّر الكتابة في data/content.json — تأكد أن مجلد data قابل للكتابة (755).',
                     '❌ Could not write data/content.json — make sure the data folder is writable (755).');
            return true;
        }
        log_line('ADMIN content saved (' . $n . ' fields)');
        $msg = A('✅ تم الحفظ. افتح الموقع لترى التغيير مباشرة.',
                 '✅ Saved. Open the site and you will see the change immediately.');
        return true;
    }

    if ($action === 'content_reset') {
        content_restore_defaults();
        log_line('ADMIN content reset to defaults');
        $msg = A('↩️ رجعت كل النصوص إلى الأصل.', '↩️ All text is back to the original wording.');
        return true;
    }

    return false;
}

/* ---------------- one field ---------------- */

function admin_field(string $key, array $f): string
{
    $label = A((string)$f['la'], (string)$f['le']);
    $type  = (string)$f['type'];
    $h     = '<div class="cfield"><label class="clab">' . e($label)
           . ' <span class="ckey">' . e($key) . '</span></label>';

    if (!empty($f['bi'])) {
        foreach (['ar' => 'العربية / Arabic', 'en' => 'English / الإنجليزية'] as $L => $tag) {
            $val = ct($key, $L);
            $h .= '<div class="cpair"><span class="ctag">' . e($tag) . '</span>';
            if ($type === 'textarea') {
                $h .= '<textarea name="c_' . e($key) . '_' . $L . '" rows="' . ($key === 'termsBody' || $key === 'privacyBody' ? 22 : 4) . '"'
                    . ' dir="' . ($L === 'ar' ? 'rtl' : 'ltr') . '">' . e($val) . '</textarea>';
            } else {
                $h .= '<input type="text" name="c_' . e($key) . '_' . $L . '"'
                    . ' dir="' . ($L === 'ar' ? 'rtl' : 'ltr') . '" value="' . e($val) . '">';
            }
            $h .= '</div>';
        }
    } elseif ($type === 'toggle') {
        $on = cv($key) !== '0';
        $h .= '<select name="c_' . e($key) . '">'
            . '<option value="1"' . ($on ? ' selected' : '') . '>' . e(A('نعم — مُفعّل', 'Yes — on')) . '</option>'
            . '<option value="0"' . ($on ? '' : ' selected') . '>' . e(A('لا — مُعطّل', 'No — off')) . '</option>'
            . '</select>';
    } else {
        $val  = cv($key);
        $itype = $type === 'email' ? 'email' : ($type === 'url' ? 'url' : ($type === 'tel' ? 'tel' : 'text'));
        $h .= '<input type="' . $itype . '" name="c_' . e($key) . '" dir="ltr" value="' . e($val) . '">';
    }

    return $h . '</div>';
}

/* ---------------- the page ---------------- */

function admin_content_page(string $group): void
{
    $groups = content_groups();
    if (!isset($groups[$group])) $group = 'brand';
    $fields = content_fields();
    $L      = admin_lang();
    ?>
    <div class="card">
      <h2><?= e($groups[$group]['ic'] . ' ' . $groups[$group][$L]) ?></h2>
      <p class="sub">
        <?= e(A('اكتب النص كما تريده أن يظهر للزائر. اترك أي خانة فارغة لتبقى الكلمة الأصلية.',
                'Type the words exactly as the visitor should see them. Leave a box empty to keep the original wording.')) ?>
      </p>

      <div class="ctabs">
        <?php foreach ($groups as $g => $meta): ?>
          <a href="<?= e(admin_url(['page' => 'content', 'g' => $g, 'open' => null])) ?>"
             class="ctab<?= $g === $group ? ' on' : '' ?>"><?= e($meta['ic'] . ' ' . $meta[$L]) ?></a>
        <?php endforeach; ?>
      </div>

      <?php if ($group === 'terms' || $group === 'privacy'): ?>
        <div class="chelp">
          <b><?= e(A('طريقة الكتابة', 'How to write it')) ?></b><br>
          <?= e(A('سطر يبدأ بـ ##  →  عنوان جانبي', 'A line starting with ##  →  a heading')) ?><br>
          <?= e(A('سطر يبدأ بـ -  →  نقطة في قائمة', 'A line starting with -  →  a bullet point')) ?><br>
          <?= e(A('سطر فارغ  →  فقرة جديدة', 'An empty line  →  a new paragraph')) ?><br>
          <?= e(A('لا تُستخدم أكواد HTML — النص يُعرض كما تكتبه بالضبط.',
                  'HTML is not used — the text appears exactly as you type it.')) ?>
          &nbsp;·&nbsp;
          <a href="<?= $group === 'terms' ? 'terms.php' : 'privacy.php' ?>" target="_blank" rel="noopener"
             style="color:var(--brand);font-weight:700"><?= e(A('عرض الصفحة ↗', 'View the page ↗')) ?></a>
        </div>
      <?php endif; ?>

      <form method="post" action="<?= e(admin_url(['page' => 'content', 'g' => $group, 'open' => null])) ?>">
        <input type="hidden" name="action" value="content_save">
        <?php foreach ($fields as $key => $f): ?>
          <?php if (($f['group'] ?? '') !== $group) continue; ?>
          <?= admin_field($key, $f) ?>
        <?php endforeach; ?>

        <div class="btnrow" style="margin-top:18px">
          <button class="btn gold" type="submit"><?= e(A('حفظ التغييرات', 'Save changes')) ?></button>
        </div>
      </form>
    </div>

    <div class="card">
      <h2><?= e(A('استعادة النص الأصلي', 'Restore the original text')) ?></h2>
      <p class="sub"><?= e(A('يرجع كل شيء في كل الأقسام إلى الكلمات التي سُلّم بها الموقع.',
                             'Puts every section back to the words the site was delivered with.')) ?></p>
      <form method="post" action="<?= e(admin_url(['page' => 'content', 'g' => $group, 'open' => null])) ?>"
            onsubmit="return confirm('<?= e(A('استعادة كل النصوص الأصلية؟', 'Restore all the original text?')) ?>')">
        <input type="hidden" name="action" value="content_reset">
        <button class="btn ghost" type="submit" style="color:#d63b3b">
          <?= e(A('استعادة الأصل', 'Restore defaults')) ?></button>
      </form>
    </div>
    <?php
}
