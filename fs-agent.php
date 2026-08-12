<?php
/* =====================================================================
   fs-agent.php — MASTER ADMIN کا چھوٹا نمائندہ   (v3 · ٩ اگست ٢٠٢٦)
   ---------------------------------------------------------------------
   یہ فائل ہر اُس سائٹ میں رکھی جاتی ہے جس کا password آپ
   farooqstars.com/master.php سے بدلنا چاہتے ہیں۔

   یہ **صرف** master کی بات سنتی ہے، اور صرف تین کام کرتی ہے:
        ping     → «میں زندہ ہوں، اور اِس سائٹ پر password ایسے بدلتا ہے»
        setpass  → نیا password لگا دو
        setuser  → username بدل دو (جہاں username ہوتا ہو)

   ⚠ حفاظت — یہ فائل کھلی سڑک پر پڑی ہے، سو تالے سخت ہیں:
     • ہر درخواست پر HMAC-SHA256 کے دستخط لازم — چابی کبھی سفر نہیں کرتی
     • دستخط میں وقت شامل ہے؛ دو منٹ سے پرانی درخواست رد
     • ہر درخواست کا nonce ایک بار ہی چلتا ہے (دوبارہ بھیجنا بےکار)
     • صرف POST؛ GET پر کچھ نہیں بتاتی
     • غلط دستخط کی گنتی رکھی جاتی ہے — بیس غلطیوں پر پندرہ منٹ کا تالا
     • جواب ہمیشہ JSON، اور کبھی کوئی راز نہیں

   ⚠ لگانے کا طریقہ (ہر سائٹ پر ایک بار):
     ١) نیچے $AGENT_KEY میں ایک لمبی، بےترتیب چابی لکھیں (کم از کم ٣٢ حروف)
        — ہر سائٹ کی **الگ** چابی ہو۔
     ٢) یہ فائل اُس سائٹ کے اُسی folder میں رکھیں جہاں admin.php ہے۔
     ٣) master.php میں اُس سائٹ کے سامنے یہی پتہ اور یہی چابی لکھ دیں۔
     ٤) master سے "🔌 جانچیں" دبائیں — سبز آ جائے تو کام ہو گیا۔

   ⚠ یہ فائل **خود سے کچھ نہیں پڑھتی**۔ password کبھی سادہ شکل میں محفوظ نہیں
     ہوتا — سائٹ کا اپنا ہی طریقہ (password_hash) استعمال ہوتا ہے۔
   ===================================================================== */

/* ---------------- ⚙ صرف یہ ایک سطر بدلنی ہے ---------------- */
$AGENT_KEY = '4e940531c1006ab8de03fd6135c7be5cfca29278948a5deb';
/* ------------------------------------------------------------ */

@ini_set('display_errors','0');
@error_reporting(0);
header('Content-Type: application/json; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');
while (ob_get_level() > 0) { @ob_end_clean(); }

function ag_out($a, $code = 200){ http_response_code($code); echo json_encode($a, JSON_UNESCAPED_UNICODE); exit; }

/* script beech me mar jaye to bhi JSON — HTML kabhi nahi */
register_shutdown_function(function(){
  $e = error_get_last();
  if ($e && in_array($e['type'], [E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR], true)) {
    while (ob_get_level() > 0) { @ob_end_clean(); }
    if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok'=>false,'err'=>'fatal','msg'=>$e['message']], JSON_UNESCAPED_UNICODE);
  }
});

if ($AGENT_KEY === 'PASTE_A_LONG_RANDOM_KEY_HERE' || strlen($AGENT_KEY) < 32)
  ag_out(['ok'=>false,'err'=>'no-key','msg'=>'Is file me abhi chabi nahi lagi (kam az kam 32 huroof).'], 500);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST')
  ag_out(['ok'=>false,'err'=>'post-only'], 405);

/* ---------------- چھوٹا سا گودام (nonce + غلطیاں) ---------------- */
function ag_store_file(){
  $d = __DIR__ . '/data';
  if (!is_dir($d)) $d = sys_get_temp_dir();
  return $d . '/.fs-agent-state.json';
}
function ag_state(){
  $j = json_decode((string)@file_get_contents(ag_store_file()), true);
  if (!is_array($j)) $j = [];
  return $j + ['nonces'=>[], 'fails'=>[]];
}
function ag_state_save($s){
  $now = time();
  $s['nonces'] = array_filter((array)$s['nonces'], function($t) use($now){ return $t > $now - 600; });
  $s['fails']  = array_values(array_filter((array)$s['fails'], function($t) use($now){ return $t > $now - 900; }));
  @file_put_contents(ag_store_file(), json_encode($s), LOCK_EX);
  @chmod(ag_store_file(), 0600);
}

$S = ag_state();
if (count($S['fails']) >= 20) ag_out(['ok'=>false,'err'=>'locked','msg'=>'Bohat ghalat koshishen — 15 minute baad.'], 429);

/* ---------------- دستخط کی جانچ ---------------- */
$t    = (string)($_POST['t']    ?? '');
$n    = (string)($_POST['n']    ?? '');
$act  = (string)($_POST['act']  ?? '');
$data = (string)($_POST['data'] ?? '');
$sig  = (string)($_POST['sig']  ?? '');

function ag_bad(&$S, $err, $msg = ''){ $S['fails'][] = time(); ag_state_save($S);
  ag_out(['ok'=>false,'err'=>$err,'msg'=>$msg], 401); }

if (!preg_match('/^\d{10}\z/', $t))            ag_bad($S, 'time');
if (!preg_match('/^[a-f0-9]{32}\z/', $n))      ag_bad($S, 'nonce');
if (!preg_match('/^[a-z]{1,20}\z/', $act))     ag_bad($S, 'act');
if (!preg_match('/^[a-f0-9]{64}\z/', $sig))    ag_bad($S, 'sig');
if (abs(time() - (int)$t) > 120)               ag_bad($S, 'stale', 'Server ke waqt me farq hai (2 minute se zyada).');
if (isset($S['nonces'][$n]))                   ag_bad($S, 'replay');

$mine = hash_hmac('sha256', $t . '|' . $n . '|' . $act . '|' . $data, $AGENT_KEY);
if (!hash_equals($mine, $sig))                 ag_bad($S, 'sig-bad');

$S['nonces'][$n] = time();
$S['fails'] = [];
ag_state_save($S);

$body = json_decode($data, true);
if (!is_array($body)) $body = [];

/* =====================================================================
   سائٹ کا اپنا طریقہ — خود پہچان لیتا ہے
   ---------------------------------------------------------------------
   ١) Thaman Motorak قسم : admin_auth.php  →  admin_set_password() / admin_set_username()
   ٢) Our Inspired Scent : lib.php+lib_ext.php  →  set_password()
   ٣) MyMandoob قسم      : mm-lock.php  →  mm_lock_set_password() / mm_lock_set_username()
   کوئی نئی سائٹ ہو تو نیچے ایک اور صورت بڑھا دیں — باقی سب ویسا ہی رہے گا۔
   ===================================================================== */
function ag_dirs(){
  /* ⚠ ٩ اگست ٢٠٢٦ — پہلے صرف اپنے folder میں دیکھتا تھا۔ مگر MyMandoob پر
     admin صفحہ `private/` میں ہے اور `mm-lock.php` جڑ میں — سو نمائندہ
     `private/` میں رکھا گیا اور اُسے کچھ ملا ہی نہیں۔ اب دو جگہ دیکھتا ہے:
     اپنا folder، اور اُس کا باپ۔ اِسی سے وہ صورت خود ٹھیک ہو جاتی ہے۔ */
  $d = [__DIR__];
  $up = dirname(__DIR__);
  if ($up !== __DIR__ && is_dir($up)) $d[] = $up;
  return $d;
}
function ag_found(){
  $hit = [];
  foreach (ag_dirs() as $dir)
    foreach (['fs-users.php','lib.php','lib_ext.php','admin_auth.php','mm-lock.php'] as $f)
      if (is_file($dir . '/' . $f)) $hit[] = basename($dir) . '/' . $f;
  return $hit;
}
function ag_adapter(){
  /* پہلے سائٹ کی اپنی بنیاد لوڈ کرو (جو جہاں بھی ملے) */
  foreach (ag_dirs() as $dir) {
    foreach (['fs-users.php','lib.php','lib_ext.php','admin_auth.php','mm-lock.php'] as $f) {
      if (is_file($dir . '/' . $f)) { @require_once $dir . '/' . $f; }
    }
  }
  /* ٠) نیا مشترکہ نظام : fs-users.php — کئی صارف، درجے، سب کچھ۔
        یہی سب سے پہلے دیکھا جاتا ہے؛ جہاں یہ لگا ہو وہاں master پوری فہرست
        سنبھال سکتا ہے، صرف ایک password نہیں۔ */
  if (function_exists('fsu_list'))
    return ['kind'=>'fs_users', 'pass'=>'fsu_set_password', 'user'=>'fsu_set_username',
            'min'=>defined('FSU_MIN_PW') ? (int)FSU_MIN_PW : 8, 'multi'=>true];
  /* ٣) MyMandoob قسم : mm-lock.php  →  mm_lock_set_password() / mm_lock_set_username()
        (وہی users.json جو team.php کا ہے — الگ تجوری نہیں بنتی) */
  if (function_exists('mm_lock_set_password'))
    return ['kind'=>'mm_lock', 'pass'=>'mm_lock_set_password',
            'user'=>function_exists('mm_lock_set_username') ? 'mm_lock_set_username' : null,
            'min'=>8];
  if (function_exists('admin_set_password'))
    return ['kind'=>'admin_auth', 'pass'=>'admin_set_password',
            'user'=>function_exists('admin_set_username') ? 'admin_set_username' : null,
            'min'=>defined('ADMIN_MIN_PW') ? (int)ADMIN_MIN_PW : 8];
  if (function_exists('set_password'))
    return ['kind'=>'lib', 'pass'=>'set_password',
            /* ٩ اگست ٢٠٢٦ — Our Inspired Scent par ab username bhi hai */
            'user'=>function_exists('set_username') ? 'set_username' : null,
            'min'=>8];
  return null;
}

$A = ag_adapter();

/* ---------------- ping — میں زندہ ہوں ---------------- */
if ($act === 'ping') {
  /* ⚠ پہلے یہ ہمیشہ ok:true کہتا تھا — چاہے سائٹ پر password بدلنے کا کوئی
     طریقہ ملا ہی نہ ہو۔ master سبز دکھا دیتا اور بعد میں کام نہ کرتا۔
     اب سچ بولتا ہے، اور یہ بھی بتاتا ہے کہ کہاں کہاں دیکھا۔ */
  $where = array_map(function($d){ return basename($d); }, ag_dirs());
  if (!$A) ag_out(['ok'=>false, 'err'=>'no-adapter', 'agent'=>'1.1',
                   'host'=>($_SERVER['HTTP_HOST'] ?? ''), 'looked_in'=>$where, 'found'=>ag_found(),
                   'msg'=>'Yahan fs-users.php / lib.php / admin_auth.php / mm-lock.php me se koi nahi mili. '
                         .'fs-agent.php ko usi folder me rakhein jahan site ka apna admin hai.'], 200);
  ag_out(['ok'=>true, 'agent'=>'1.2', 'host'=>($_SERVER['HTTP_HOST'] ?? ''),
          'adapter'=> $A['kind'],
          'multi'=> !empty($A['multi']),
          'users'=> !empty($A['multi']) ? count(fsu_list()) : 1,
          'can'=> !empty($A['multi'])
                  ? ['users','setpass','setuser','adduser','deluser','setrole','setactive']
                  : array_values(array_filter(['setpass', $A['user'] ? 'setuser' : null])),
          'min'=> $A['min'], 'looked_in'=>$where, 'found'=>ag_found(),
          'time'=> time()]);
}

if (!$A) ag_out(['ok'=>false,'err'=>'no-adapter',
   'msg'=>'Is site par password badalne ka tareeqa nahi mila — fs-agent.php usi folder me rakhein jahan admin.php hai.'], 500);

/* ---------------- setpass ---------------- */
if ($act === 'setpass') {
  $pw = (string)($body['pass'] ?? '');
  if (strlen($pw) < max(8, (int)$A['min']))
    ag_out(['ok'=>false,'err'=>'short','msg'=>'Password kam az kam '.max(8,(int)$A['min']).' huroof ka ho.'], 400);
  /* ⚠ کچھ سائٹوں کا setter کچھ واپس نہیں کرتا (Our Inspired Scent کا
     set_password() ایسا ہی ہے) — سو `null` کو ناکامی نہ سمجھو، ورنہ
     password بدل جانے کے باوجود «نہیں ہوا» کہتا رہے گا۔ صرف صریح `false`
     ناکامی ہے۔ (یہ browser میں پکڑی گئی خرابی ہے، اندازہ نہیں۔) */
  $fn = $A['pass'];
  if (!empty($A['multi'])) {
    /* کئی صارف والی سائٹ: کس کا password؟ نہ بتایا جائے تو مالک کا۔ */
    $who = trim((string)($body['email'] ?? '')) ?: (defined('FSU_OWNER') ? FSU_OWNER : '');
    $r = @$fn($who, $pw);
  } else {
    $r = @$fn($pw);
  }
  $ok = ($r !== false);
  ag_out($ok ? ['ok'=>true,'did'=>'setpass','adapter'=>$A['kind']]
             : ['ok'=>false,'err'=>'write','msg'=>'Site ne password mehfooz nahi kiya (folder ki permission dekh lein).'],
         $ok ? 200 : 500);
}

/* ---------------- setuser ---------------- */
if ($act === 'setuser') {
  if (!$A['user']) ag_out(['ok'=>false,'err'=>'no-user','msg'=>'Is site par username hota hi nahi — sirf password.'], 400);
  $u = trim((string)($body['user'] ?? ''));
  if ($u === '' || mb_strlen($u) > 60) ag_out(['ok'=>false,'err'=>'bad-user'], 400);
  $fn = $A['user'];
  if (!empty($A['multi'])) {
    $old = trim((string)($body['old'] ?? ''));
    if ($old === '') ag_out(['ok'=>false,'err'=>'need-old','msg'=>'Kis ka username badalna hai? "old" bhejein.'], 400);
    $r = @$fn($old, $u);
  } else {
    $r = @$fn($u);
  }
  $ok = ($r !== false);
  ag_out($ok ? ['ok'=>true,'did'=>'setuser','user'=>$u]
             : ['ok'=>false,'err'=>'write'], $ok ? 200 : 500);
}

/* =====================================================================
   کئی صارف والے کام — صرف اُن سائٹوں پر جہاں fs-users.php لگی ہو
   ===================================================================== */
if (in_array($act, ['users','adduser','deluser','setrole','setactive'], true)) {
  if (empty($A['multi']))
    ag_out(['ok'=>false,'err'=>'not-multi',
            'msg'=>'Is site par abhi purana (ek-user) nizam hai — fs-users.php lagayein.'], 400);

  if ($act === 'users')     ag_out(['ok'=>true,'users'=>fsu_list(),'owner'=>FSU_OWNER]);

  $em = trim((string)($body['email'] ?? ''));
  if ($em === '') ag_out(['ok'=>false,'err'=>'need-email'], 400);

  if ($act === 'adduser') {
    $r = fsu_add_user($em, (string)($body['name'] ?? ''), (string)($body['role'] ?? 'staff'), (string)($body['pass'] ?? ''));
    ag_out($r === 'ok' ? ['ok'=>true,'did'=>'adduser','email'=>$em]
                       : ['ok'=>false,'err'=>$r], $r === 'ok' ? 200 : 400);
  }
  if ($act === 'deluser')   { $ok = fsu_remove_user($em);
    ag_out($ok ? ['ok'=>true,'did'=>'deluser'] : ['ok'=>false,'err'=>'refused',
      'msg'=>'Malik ko nahi hataya ja sakta, ya woh mojood nahi.'], $ok ? 200 : 400); }
  if ($act === 'setrole')   { $ok = fsu_set_role($em, (string)($body['role'] ?? ''));
    ag_out($ok ? ['ok'=>true,'did'=>'setrole'] : ['ok'=>false,'err'=>'refused',
      'msg'=>'Malik ka darja nahi badla ja sakta.'], $ok ? 200 : 400); }
  if ($act === 'setactive') { $ok = fsu_set_active($em, !empty($body['on']));
    ag_out($ok ? ['ok'=>true,'did'=>'setactive'] : ['ok'=>false,'err'=>'refused'], $ok ? 200 : 400); }
}

ag_out(['ok'=>false,'err'=>'unknown-act'], 400);
