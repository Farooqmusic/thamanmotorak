<?php
/* =====================================================================
   fs-users.php — ایک ہی نظام، ساری سائٹوں پر   (v1 · ٩ اگست ٢٠٢٦)
   👥 کئی صارف · username + password · تین درجے · ایک ہی شکل

   فاروق کا حکم (٩ اگست): «I want to make things easy, I can not remember
   for each and every site what type of system is working. All sites should
   work with one type of system, with usernames and rights. Each site should
   have multiple user capability, and in master I can reset usernames and
   passwords, and see which site has how many users.»

   ⚠ یہ فائل اکیلی کافی ہے۔ نہ database چاہیے، نہ کوئی اور فائل۔ بس اِسے
     سائٹ میں رکھیں اور اپنے admin صفحے کے اوپر ایک سطر لکھ دیں:

         require __DIR__ . '/fs-users.php';   fsu_require('admin');

     JSON واپس کرنے والی کسی فائل میں:
         require __DIR__ . '/fs-users.php';   fsu_guard_api('staff');

   ⚠ تین درجے:
        admin  — سب کچھ، اور صارف بھی سنبھال سکتا ہے
        staff  — کام کر سکتا ہے، مگر صارف نہیں بدل سکتا
        view   — صرف دیکھ سکتا ہے

   ⚠ مالک: babaqatar@gmail.com — ہمیشہ admin، ہمیشہ چالو، کبھی ہٹ نہیں سکتا۔
     یہ جان بوجھ کر ہے: کوئی غلطی سے (یا جان بوجھ کر) آپ کو باہر نہ کر سکے۔

   ⚠ پہلی بار: اگر سائٹ پر پہلے سے صارف موجود ہوں (MyMandoob کا mm-team،
     Our Inspired Scent کا settings.json، Thaman کا data/admin.json) تو یہ
     فائل اُنہیں **خود اٹھا لیتی ہے** — کسی کا password نہیں ٹوٹتا، کوئی
     دوبارہ نہیں بنانا پڑتا۔ کچھ نہ ملے تو مالک کے email پر code بھیج کر
     پہلا password بنوا لیتی ہے۔

   ⚠ راز کہاں: `fs-vault/` میں — پہلے public_html سے **باہر** رکھنے کی کوشش،
     نہ ہو سکے تو اندر مگر .htaccess کے دو تالوں کے ساتھ۔ فائلیں 0600۔
   ===================================================================== */

if (defined('FSU')) return;
define('FSU', '1.0');

/* ---------------- ⚙ صرف یہ دو سطریں بدلی جا سکتی ہیں ---------------- */
if (!defined('FSU_OWNER'))     define('FSU_OWNER', 'babaqatar@gmail.com');
if (!defined('FSU_SITE'))      define('FSU_SITE', ($_SERVER['HTTP_HOST'] ?? 'site'));
/* --------------------------------------------------------------------- */

if (!defined('FSU_MAIL_FROM')) define('FSU_MAIL_FROM', 'no-reply@' . preg_replace('/^www\./', '', (string)($_SERVER['HTTP_HOST'] ?? 'localhost')));
if (!defined('FSU_SESS_DAYS')) define('FSU_SESS_DAYS', 14);
if (!defined('FSU_CODE_TTL'))  define('FSU_CODE_TTL', 900);      /* پندرہ منٹ */
if (!defined('FSU_MIN_PW'))    define('FSU_MIN_PW', 8);

/* ================================================================
   تجوری
   ================================================================ */
function fsu_vault(){
  static $v = null;
  if ($v !== null) return $v;
  $out = dirname(__DIR__) . '/fs-vault';         /* بہتر: public_html سے باہر */
  $in  = __DIR__ . '/fs-vault';
  if (is_dir($out)) { $v = $out; return $v; }
  if (is_dir($in))  { $v = $in;  return $v; }
  if (@mkdir($out, 0755, true)) { $v = $out; return $v; }
  @mkdir($in, 0755, true);
  /* اندر رکھنی پڑی تو دو تالے لگا دو */
  @file_put_contents($in . '/.htaccess',
    "Require all denied\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n");
  @file_put_contents($in . '/index.html', '');
  $v = $in;
  return $v;
}
function fsu_read($f, $dflt = []){
  $j = json_decode((string)@file_get_contents(fsu_vault() . '/' . $f), true);
  return is_array($j) ? $j : $dflt;
}
function fsu_write($f, $data){
  $p = fsu_vault() . '/' . $f; $t = $p . '.tmp';
  $ok = @file_put_contents($t, json_encode($data, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT), LOCK_EX) !== false
     && @rename($t, $p);
  if ($ok) @chmod($p, 0600);
  return $ok;
}
function fsu_users(){    return fsu_read('users.json', []); }
function fsu_users_save($u){ return fsu_write('users.json', array_values($u)); }
function fsu_state(){    return fsu_read('state.json', ['fails'=>[], 'code'=>null]); }
function fsu_state_save($s){ return fsu_write('state.json', $s); }

/* ================================================================
   درجے
   ================================================================ */
function fsu_roles(){ return ['admin','staff','view']; }
function fsu_role_ok($r){ return in_array($r, fsu_roles(), true) ? $r : 'view'; }
function fsu_rank($r){ $m = ['view'=>1, 'staff'=>2, 'admin'=>3]; return $m[$r] ?? 0; }
function fsu_is_owner_email($e){ return strtolower(trim((string)$e)) === strtolower(FSU_OWNER); }

/* ================================================================
   پہلی بار — پرانے صارف خود اٹھا لو
   ---------------------------------------------------------------
   ⚠ کسی کا password نہیں ٹوٹتا: پرانا hash جوں کا توں اٹھایا جاتا ہے،
     سو ہر شخص اپنے پرانے password ہی سے اندر آتا رہے گا۔
   ================================================================ */
function fsu_import(){
  $found = [];

  /* ١) MyMandoob — mm-team/users.json  (owner|member) */
  foreach ([__DIR__ . '/mm-team/users.json', dirname(__DIR__) . '/mm-team/users.json'] as $p) {
    if (!is_file($p)) continue;
    $j = json_decode((string)@file_get_contents($p), true);
    if (!is_array($j)) continue;
    foreach ($j as $x) {
      $em = trim((string)($x['email'] ?? '')); if ($em === '') continue;
      $found[strtolower($em)] = [
        'id'   => (string)($x['id'] ?? bin2hex(random_bytes(4))),
        'email'=> $em,
        'name' => (string)($x['name'] ?? $em),
        'role' => (($x['role'] ?? '') === 'owner') ? 'admin' : 'staff',
        'on'   => !isset($x['on']) || !empty($x['on']),
        'pass' => (string)($x['pass'] ?? ''),
        'made' => time(), 'seen' => (int)($x['seen'] ?? 0), 'must' => !empty($x['must']),
        'from' => 'mm-team',
      ];
    }
    break;
  }

  /* ٢) Our Inspired Scent — data/settings.json کا pw_hash (اکیلا مالک) */
  if (!$found) {
    $sp = __DIR__ . '/data/settings.json';
    $ap = __DIR__ . '/data/auth.json';
    if (is_file($sp)) {
      $sj = json_decode((string)@file_get_contents($sp), true) ?: [];
      $aj = is_file($ap) ? (json_decode((string)@file_get_contents($ap), true) ?: []) : [];
      $h  = (string)($sj['pw_hash'] ?? '');
      if ($h !== '') {
        $em = trim((string)($aj['admin_user'] ?? '')) ?: FSU_OWNER;
        $found[strtolower($em)] = ['id'=>bin2hex(random_bytes(4)), 'email'=>$em,
          'name'=>'Admin', 'role'=>'admin', 'on'=>true, 'pass'=>$h,
          'made'=>time(), 'seen'=>0, 'must'=>false, 'from'=>'ois'];
      }
    }
  }

  /* ٣) Thaman Motorak — data/admin.json (user + hash) */
  if (!$found) {
    $p = __DIR__ . '/data/admin.json';
    if (is_file($p)) {
      $j = json_decode((string)@file_get_contents($p), true) ?: [];
      $h = (string)($j['hash'] ?? '');
      if ($h !== '') {
        $em = trim((string)($j['user'] ?? '')) ?: FSU_OWNER;
        $found[strtolower($em)] = ['id'=>bin2hex(random_bytes(4)), 'email'=>$em,
          'name'=>'Admin', 'role'=>'admin', 'on'=>true, 'pass'=>$h,
          'made'=>time(), 'seen'=>0, 'must'=>false, 'from'=>'admin_auth'];
      }
    }
  }

  /* مالک ہمیشہ فہرست میں، اور ہمیشہ admin */
  $ok = strtolower(FSU_OWNER);
  if (!isset($found[$ok])) {
    $found[$ok] = ['id'=>bin2hex(random_bytes(4)), 'email'=>FSU_OWNER, 'name'=>'Farooq',
                   'role'=>'admin', 'on'=>true, 'pass'=>'',        /* خالی = ابھی بنا نہیں */
                   'made'=>time(), 'seen'=>0, 'must'=>true, 'from'=>'owner'];
  } else {
    $found[$ok]['role'] = 'admin';
    $found[$ok]['on']   = true;
  }
  return array_values($found);
}
function fsu_ensure(){
  $u = fsu_users();
  if ($u) {
    /* مالک کہیں ہٹ گیا ہو تو واپس ڈال دو */
    $has = false;
    foreach ($u as $i => $x) if (fsu_is_owner_email($x['email'] ?? '')) {
      $has = true; $u[$i]['role'] = 'admin'; $u[$i]['on'] = true;
    }
    if (!$has) { $u[] = ['id'=>bin2hex(random_bytes(4)), 'email'=>FSU_OWNER, 'name'=>'Farooq',
                         'role'=>'admin', 'on'=>true, 'pass'=>'', 'made'=>time(),
                         'seen'=>0, 'must'=>true, 'from'=>'owner']; }
    fsu_users_save($u);
    return $u;
  }
  $u = fsu_import();
  fsu_users_save($u);
  return $u;
}

/* ================================================================
   کون اندر ہے
   ================================================================ */
function fsu_cookie(){ return 'fsu_s'; }
function fsu_sessions(){ return fsu_read('sessions.json', []); }
function fsu_sessions_save($s){ return fsu_write('sessions.json', $s); }

function fsu_me(){
  static $done = false; static $me = null;
  if ($done) return $me;
  $done = true;
  $t = (string)($_COOKIE[fsu_cookie()] ?? '');
  if (!preg_match('/^[a-f0-9]{48}\z/', $t)) return null;
  $ss = fsu_sessions();
  if (!isset($ss[$t]) || (int)($ss[$t]['exp'] ?? 0) < time()) return null;
  $uid = (string)($ss[$t]['u'] ?? '');
  foreach (fsu_ensure() as $x) {
    if ((string)($x['id'] ?? '') === $uid && !empty($x['on'])) { $me = $x; return $me; }
  }
  return null;
}
function fsu_can($need = 'view'){
  $me = fsu_me();
  return $me !== null && fsu_rank($me['role'] ?? '') >= fsu_rank($need);
}
function fsu_login_ok($uid){
  $t = bin2hex(random_bytes(24));
  $ss = fsu_sessions(); $now = time();
  foreach ($ss as $k => $v) if ((int)($v['exp'] ?? 0) < $now) unset($ss[$k]);
  $ss[$t] = ['u'=>$uid, 'exp'=>$now + FSU_SESS_DAYS*86400, 'made'=>$now,
             'ip'=>substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45)];
  fsu_sessions_save($ss);
  $sec = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
  setcookie(fsu_cookie(), $t, ['expires'=>$now + FSU_SESS_DAYS*86400, 'path'=>'/',
            'secure'=>$sec, 'httponly'=>true, 'samesite'=>'Lax']);
  /* آخری بار کب آیا */
  $u = fsu_ensure();
  foreach ($u as $i => $x) if ((string)$x['id'] === (string)$uid) $u[$i]['seen'] = $now;
  fsu_users_save($u);
}
function fsu_logout(){
  $t = (string)($_COOKIE[fsu_cookie()] ?? '');
  if ($t !== '') { $ss = fsu_sessions(); if (isset($ss[$t])) { unset($ss[$t]); fsu_sessions_save($ss); } }
  $sec = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
  setcookie(fsu_cookie(), '', ['expires'=>time()-3600, 'path'=>'/', 'secure'=>$sec,
            'httponly'=>true, 'samesite'=>'Lax']);
}

/* ---------------- پانچ غلط کوششیں ---------------- */
function fsu_locked(){
  $s = fsu_state(); $now = time();
  $f = array_filter((array)($s['fails'] ?? []), function($t) use($now){ return (int)$t > $now-900; });
  return count($f) >= 5;
}
function fsu_note_fail(){
  $s = fsu_state(); $now = time();
  $f = array_values(array_filter((array)($s['fails'] ?? []), function($t) use($now){ return (int)$t > $now-900; }));
  $f[] = $now; $s['fails'] = $f; fsu_state_save($s);
}
function fsu_clear_fails(){ $s = fsu_state(); $s['fails'] = []; fsu_state_save($s); }

/* ---------------- بھول جانے پر email کا code ---------------- */
function fsu_code_make(){
  $c = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
  $s = fsu_state();
  $s['code'] = ['h'=>password_hash($c, PASSWORD_DEFAULT), 'exp'=>time()+FSU_CODE_TTL, 'tries'=>0];
  fsu_state_save($s);
  return $c;
}
function fsu_code_check($given){
  $s = fsu_state(); $c = $s['code'] ?? null;
  if (!$c) return 'no-code';
  if (time() > (int)$c['exp'])   { $s['code']=null; fsu_state_save($s); return 'expired'; }
  if ((int)$c['tries'] >= 5)     { $s['code']=null; fsu_state_save($s); return 'too-many'; }
  $s['code']['tries'] = (int)$c['tries'] + 1; fsu_state_save($s);
  if (!password_verify((string)$given, (string)$c['h'])) return 'wrong';
  $s = fsu_state(); $s['code'] = null; fsu_state_save($s);
  return 'ok';
}
function fsu_code_send($code){
  $sub = '=?UTF-8?B?' . base64_encode(FSU_SITE . ' — code: ' . $code) . '?=';
  $body = '<div style="font-family:Arial,sans-serif;font-size:15px;line-height:1.7">'
    . '<p>' . htmlspecialchars(FSU_SITE) . '</p>'
    . '<p style="font-size:30px;letter-spacing:6px;font-weight:700;margin:14px 0">' . htmlspecialchars($code) . '</p>'
    . '<p>Yeh code 15 minute chalta hai. Agar aap ne nahi manga to nazarandaz kar dein.</p></div>';
  $h  = "From: " . FSU_SITE . " <" . FSU_MAIL_FROM . ">\r\nMIME-Version: 1.0\r\n"
      . "Content-Type: text/html; charset=UTF-8\r\n";
  return (bool)@mail(FSU_OWNER, $sub, $body, $h, '-f' . FSU_MAIL_FROM);
}

/* ================================================================
   صارف سنبھالنا — یہی کام master بھی کرواتا ہے (fs-agent.php سے)
   ================================================================ */
function fsu_find($email){
  $e = strtolower(trim((string)$email));
  foreach (fsu_ensure() as $i => $x) if (strtolower((string)$x['email']) === $e) return $i;
  return -1;
}
function fsu_list(){
  $out = [];
  foreach (fsu_ensure() as $x) {
    $out[] = ['email'=>$x['email'], 'name'=>$x['name'], 'role'=>$x['role'],
              'on'=>!empty($x['on']), 'owner'=>fsu_is_owner_email($x['email']),
              'seen'=>(int)($x['seen'] ?? 0), 'has_pass'=>((string)($x['pass'] ?? '') !== '')];
  }
  return $out;
}
function fsu_set_password($email, $pw){
  if (strlen((string)$pw) < FSU_MIN_PW) return false;
  $u = fsu_ensure(); $i = fsu_find($email);
  if ($i < 0) return false;
  $u[$i]['pass'] = password_hash((string)$pw, PASSWORD_DEFAULT);
  $u[$i]['must'] = false;
  fsu_clear_fails();
  return fsu_users_save($u);
}
function fsu_set_username($old, $new){
  $new = trim((string)$new);
  if ($new === '' || !filter_var($new, FILTER_VALIDATE_EMAIL)) return false;
  $u = fsu_ensure(); $i = fsu_find($old);
  if ($i < 0) return false;
  if (fsu_is_owner_email($u[$i]['email'])) return false;   /* مالک کا نام یہاں سے نہیں بدلتا */
  if (fsu_find($new) >= 0) return false;                   /* پہلے سے موجود */
  $u[$i]['email'] = $new;
  return fsu_users_save($u);
}
function fsu_add_user($email, $name, $role, $pw){
  $email = trim((string)$email);
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return 'bad-email';
  if (fsu_find($email) >= 0)                      return 'exists';
  if (strlen((string)$pw) < FSU_MIN_PW)           return 'short';
  $u = fsu_ensure();
  $u[] = ['id'=>bin2hex(random_bytes(4)), 'email'=>$email,
          'name'=>mb_substr(trim((string)$name) ?: $email, 0, 60),
          'role'=>fsu_role_ok($role), 'on'=>true,
          'pass'=>password_hash((string)$pw, PASSWORD_DEFAULT),
          'made'=>time(), 'seen'=>0, 'must'=>true, 'from'=>'added'];
  return fsu_users_save($u) ? 'ok' : 'write';
}
function fsu_remove_user($email){
  if (fsu_is_owner_email($email)) return false;            /* مالک نہیں ہٹ سکتا */
  $u = fsu_ensure(); $i = fsu_find($email);
  if ($i < 0) return false;
  $gone = $u[$i]['id'];
  array_splice($u, $i, 1);
  /* اُس کے کھلے session بھی ختم */
  $ss = fsu_sessions();
  foreach ($ss as $k => $v) if ((string)($v['u'] ?? '') === (string)$gone) unset($ss[$k]);
  fsu_sessions_save($ss);
  return fsu_users_save($u);
}
function fsu_set_role($email, $role){
  if (fsu_is_owner_email($email)) return false;            /* مالک ہمیشہ admin */
  $u = fsu_ensure(); $i = fsu_find($email);
  if ($i < 0) return false;
  $u[$i]['role'] = fsu_role_ok($role);
  return fsu_users_save($u);
}
function fsu_set_active($email, $on){
  if (fsu_is_owner_email($email)) return false;            /* مالک ہمیشہ چالو */
  $u = fsu_ensure(); $i = fsu_find($email);
  if ($i < 0) return false;
  $u[$i]['on'] = (bool)$on;
  if (!$on) {
    $ss = fsu_sessions();
    foreach ($ss as $k => $v) if ((string)($v['u'] ?? '') === (string)$u[$i]['id']) unset($ss[$k]);
    fsu_sessions_save($ss);
  }
  return fsu_users_save($u);
}

/* ================================================================
   API کا پہرہ
   ================================================================ */
function fsu_guard_api($need = 'staff'){
  if (fsu_can($need)) return;
  http_response_code(403);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['status'=>'error','code'=>'forbidden',
                    'msg'=>'Pehle login karein.'], JSON_UNESCAPED_UNICODE);
  exit;
}

/* ================================================================
   صفحے کا پہرہ + login صفحہ
   ================================================================ */
function fsu_boot(){
  if (session_status() === PHP_SESSION_NONE) {
    $sec = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$sec,
                               'httponly'=>true,'samesite'=>'Lax']);
    session_name('fsu_csrf_s');
    session_start();
  }
  if (empty($_SESSION['fsu_csrf'])) $_SESSION['fsu_csrf'] = bin2hex(random_bytes(16));
}
function fsu_csrf(){ fsu_boot(); return (string)$_SESSION['fsu_csrf']; }
function fsu_csrf_ok(){
  return isset($_POST['fsu_csrf']) && is_string($_POST['fsu_csrf'])
      && hash_equals(fsu_csrf(), $_POST['fsu_csrf']);
}

function fsu_require($need = 'view'){
  fsu_boot();
  fsu_ensure();
  $err = ''; $ok = ''; $stage = '';

  if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $act = (string)($_POST['fsu_act'] ?? '');
    if ($act !== '' && !fsu_csrf_ok()) { $err = 'Safha purana ho gaya tha — dobara koshish karein.'; $act = ''; }

    if ($act === 'logout') { fsu_logout(); header('Location: ' . strtok((string)$_SERVER['REQUEST_URI'], '?')); exit; }

    if ($act === 'login') {
      if (fsu_locked()) $err = 'Paanch ghalat koshishen — pandrah minute baad.';
      else {
        $who = strtolower(trim((string)($_POST['fsu_user'] ?? '')));
        $pw  = (string)($_POST['fsu_pass'] ?? '');
        $hit = null;
        foreach (fsu_ensure() as $x)
          if (strtolower((string)$x['email']) === $who) { $hit = $x; break; }
        if ($hit && !empty($hit['on']) && (string)($hit['pass'] ?? '') !== ''
            && password_verify($pw, (string)$hit['pass'])) {
          fsu_clear_fails(); fsu_login_ok((string)$hit['id']);
          header('Location: ' . strtok((string)$_SERVER['REQUEST_URI'], '?')); exit;
        }
        fsu_note_fail();
        $err = 'Email ya password theek nahi.';
      }
    }

    if ($act === 'sendcode') {
      $c = fsu_code_make();
      $ok = fsu_code_send($c) ? 'Code ' . FSU_OWNER . ' par bhej diya gaya.' : '';
      if ($ok === '') $err = 'Email nahi ja saki — hosting ki email dekh lein.';
      $stage = 'code';
    }
    if ($act === 'setpass') {
      $r  = fsu_code_check((string)($_POST['fsu_code'] ?? ''));
      $p1 = (string)($_POST['fsu_p1'] ?? ''); $p2 = (string)($_POST['fsu_p2'] ?? '');
      if ($r !== 'ok') {
        $err = ['no-code'=>'Pehle code mangwayein.', 'expired'=>'Code ka waqt guzar gaya — naya mangwayein.',
                'too-many'=>'Bohat ghalat koshishen — naya code mangwayein.',
                'wrong'=>'Code theek nahi.'][$r] ?? 'Code theek nahi.';
        $stage = 'code';
      } elseif (strlen($p1) < FSU_MIN_PW) { $err = 'Password kam az kam ' . FSU_MIN_PW . ' huroof ka ho.'; $stage='code'; }
      elseif ($p1 !== $p2)                { $err = 'Dono password ek jaise nahi.'; $stage='code'; }
      else {
        fsu_set_password(FSU_OWNER, $p1);
        $ok = 'Password ban gaya — ab usi se andar aayein.';
      }
    }
  }

  if (fsu_can($need)) return;                     /* راستہ صاف */
  if (fsu_me() !== null) { fsu_deny_screen($need); }   /* اندر تو ہے، مگر درجہ کم */
  fsu_login_screen($err, $ok, $stage);
}

function fsu_head($title){
  header('Content-Type: text/html; charset=utf-8');
  header('X-Robots-Tag: noindex, nofollow');
  header('X-Frame-Options: DENY');
  $t = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
  return <<<H
<!doctype html>
<html lang="ur" dir="rtl">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><title>{$t}</title>
<script>(function(){var t=null;try{t=localStorage.getItem('fsu_theme')}catch(e){}
if(t!=='light'&&t!=='dark'){t=(window.matchMedia&&matchMedia('(prefers-color-scheme: light)').matches)?'light':'dark'}
document.documentElement.setAttribute('data-theme',t)})();</script>
<style>
:root{--bg:linear-gradient(160deg,#141026,#0d0a17 60%);--card:#1c1730;--line:rgba(255,255,255,.10);
 --ink:#ece9f8;--mut:#9d93bd;--pri:#7c5cff;--ok:#3ddc84;--bad:#ff6b6b;--field:#120e20;--tile:#241d3d;--sh:none}
html[data-theme="light"]{--bg:linear-gradient(160deg,#f7f6fd,#eceaf8 60%);--card:#fff;--line:rgba(25,15,55,.13);
 --ink:#1a1430;--mut:#665d85;--pri:#5b3fd4;--ok:#1f9d55;--bad:#d64545;--field:#fff;--tile:#f2effb;--sh:0 1px 3px rgba(25,15,55,.07)}
*{box-sizing:border-box}
body{margin:0;background:var(--bg);color:var(--ink);font-family:"Segoe UI",Tahoma,system-ui,sans-serif;
 line-height:1.7;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.box{background:var(--card);border:1px solid var(--line);border-radius:20px;padding:26px;width:100%;
 max-width:400px;box-shadow:var(--sh);position:relative}
h1{font-size:20px;margin:0 0 4px}.sub{color:var(--mut);font-size:13px;margin-bottom:16px}
label{display:block;font-size:13px;color:var(--mut);margin:12px 0 4px}
input{width:100%;padding:11px 13px;border-radius:12px;border:1px solid var(--line);background:var(--field);
 color:var(--ink);font-size:15px;font-family:inherit}
input:focus{outline:2px solid var(--pri);outline-offset:1px}
.btn{width:100%;margin-top:18px;padding:12px;border-radius:12px;border:0;background:var(--pri);color:#fff;
 font-size:15px;font-weight:600;cursor:pointer;font-family:inherit}
.btn2{background:var(--tile);color:var(--ink);border:1px solid var(--line);margin-top:10px}
.msg{padding:9px 13px;border-radius:11px;font-size:14px;margin-bottom:12px}
.err{background:rgba(255,107,107,.12);border:1px solid rgba(255,107,107,.4)}
.ok{background:rgba(61,220,132,.12);border:1px solid rgba(61,220,132,.4)}
.ltr{direction:ltr;text-align:left}.mini{color:var(--mut);font-size:12px}
.th{position:absolute;inset-inline-end:16px;top:16px;background:var(--tile);border:1px solid var(--line);
 border-radius:10px;padding:5px 9px;cursor:pointer;font-size:14px;color:var(--ink)}
details summary{cursor:pointer;color:var(--mut);font-size:13px;margin-top:14px}
</style></head><body>
H;
}
function fsu_foot(){
  return <<<H
<script>(function(){var b=document.getElementById('th'),h=document.documentElement;
function p(){b.textContent=h.getAttribute('data-theme')==='light'?'☀️':'🌙'}
b.onclick=function(){var t=h.getAttribute('data-theme')==='light'?'dark':'light';
h.setAttribute('data-theme',t);try{localStorage.setItem('fsu_theme',t)}catch(e){}p()};p()})();</script>
</body></html>
H;
}

function fsu_login_screen($err = '', $ok = '', $stage = ''){
  $csrf = htmlspecialchars(fsu_csrf(), ENT_QUOTES, 'UTF-8');
  $site = htmlspecialchars(FSU_SITE, ENT_QUOTES, 'UTF-8');
  $owner= htmlspecialchars(FSU_OWNER, ENT_QUOTES, 'UTF-8');
  $eb = $err ? '<div class="msg err">' . htmlspecialchars($err, ENT_QUOTES, 'UTF-8') . '</div>' : '';
  $ob = $ok  ? '<div class="msg ok">'  . htmlspecialchars($ok,  ENT_QUOTES, 'UTF-8') . '</div>' : '';
  $open = ($stage === 'code') ? ' open' : '';
  http_response_code(401);
  echo fsu_head('🔒 ' . FSU_SITE);
  echo <<<H
<form class="box" method="post" autocomplete="on">
  <button class="th" type="button" id="th">🌙</button>
  <h1>🔒 {$site}</h1>
  <div class="sub">نجی صفحہ · Private area</div>
  {$eb}{$ob}
  <input type="hidden" name="fsu_csrf" value="{$csrf}">
  <input type="hidden" name="fsu_act" value="login">
  <label>Username (email)</label>
  <input type="email" name="fsu_user" class="ltr" autocomplete="username" autofocus required>
  <label>Password</label>
  <input type="password" name="fsu_pass" autocomplete="current-password" required>
  <button class="btn" type="submit">🔓 اندر آئیں · Sign in</button>
</form>
<script>document.querySelector('.box').insertAdjacentHTML('beforeend', `
  <details{$open}><summary>🔑 Password بھول گئے؟ · Forgot password?</summary>
  <form method="post" style="margin-top:8px">
    <input type="hidden" name="fsu_csrf" value="{$csrf}">
    <input type="hidden" name="fsu_act" value="sendcode">
    <p class="mini">Code <b class="ltr">{$owner}</b> پر بھیجا جائے گا۔</p>
    <button class="btn btn2" type="submit">📧 مجھے code بھیجیں</button>
  </form>
  <form method="post" style="margin-top:10px">
    <input type="hidden" name="fsu_csrf" value="{$csrf}">
    <input type="hidden" name="fsu_act" value="setpass">
    <label>Email والا code</label>
    <input type="text" name="fsu_code" class="ltr" inputmode="numeric" placeholder="123456">
    <label>نیا password</label>
    <input type="password" name="fsu_p1" autocomplete="new-password">
    <label>دوبارہ وہی</label>
    <input type="password" name="fsu_p2" autocomplete="new-password">
    <button class="btn btn2" type="submit">✅ password بدل دیں</button>
  </form></details>`);</script>
H;
  echo fsu_foot();
  exit;
}

function fsu_deny_screen($need){
  $me = fsu_me();
  $csrf = htmlspecialchars(fsu_csrf(), ENT_QUOTES, 'UTF-8');
  $em = htmlspecialchars((string)($me['email'] ?? ''), ENT_QUOTES, 'UTF-8');
  $ro = htmlspecialchars((string)($me['role'] ?? ''), ENT_QUOTES, 'UTF-8');
  $nd = htmlspecialchars((string)$need, ENT_QUOTES, 'UTF-8');
  http_response_code(403);
  echo fsu_head('⛔ اجازت نہیں');
  echo <<<H
<div class="box">
  <button class="th" type="button" id="th">🌙</button>
  <h1>⛔ اجازت نہیں</h1>
  <div class="sub">You do not have rights for this page</div>
  <p class="mini">آپ اندر تو ہیں (<b class="ltr">{$em}</b>) مگر آپ کا درجہ <b>{$ro}</b> ہے،
     اور اِس صفحے کے لیے <b>{$nd}</b> چاہیے۔</p>
  <form method="post"><input type="hidden" name="fsu_csrf" value="{$csrf}">
    <input type="hidden" name="fsu_act" value="logout">
    <button class="btn btn2" type="submit">🚪 باہر نکلیں · Sign out</button></form>
</div>
H;
  echo fsu_foot();
  exit;
}

/* ================================================================
   صارفوں کا خانہ — کسی بھی admin صفحے میں ایک سطر سے لگ جاتا ہے:
        echo fsu_users_box();
   ================================================================ */
function fsu_users_box(){
  if (!fsu_can('admin')) return '';
  fsu_boot();
  $msg = '';
  if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && fsu_csrf_ok()) {
    $a = (string)($_POST['fsu_uact'] ?? '');
    $em = (string)($_POST['fsu_email'] ?? '');
    if ($a === 'add') {
      $r = fsu_add_user($em, (string)($_POST['fsu_name'] ?? ''), (string)($_POST['fsu_role'] ?? 'staff'), (string)($_POST['fsu_pw'] ?? ''));
      $msg = ['ok'=>'✅ نیا صارف جُڑ گیا۔', 'exists'=>'یہ email پہلے سے موجود ہے۔',
              'bad-email'=>'Email ٹھیک نہیں۔', 'short'=>'Password کم از کم ' . FSU_MIN_PW . ' حروف کا ہو۔',
              'write'=>'محفوظ نہیں ہو سکا۔'][$r] ?? '';
    }
    if ($a === 'del'  && fsu_remove_user($em))                                     $msg = '✅ صارف ہٹا دیا گیا۔';
    if ($a === 'role' && fsu_set_role($em, (string)($_POST['fsu_role'] ?? '')))    $msg = '✅ درجہ بدل گیا۔';
    if ($a === 'onoff'&& fsu_set_active($em, !empty($_POST['fsu_on'])))            $msg = '✅ بدل گیا۔';
    if ($a === 'pw'   && fsu_set_password($em, (string)($_POST['fsu_pw'] ?? '')))  $msg = '✅ Password بدل گیا۔';
  }
  $csrf = htmlspecialchars(fsu_csrf(), ENT_QUOTES, 'UTF-8');
  $rows = '';
  foreach (fsu_list() as $u) {
    $e  = htmlspecialchars($u['email'], ENT_QUOTES, 'UTF-8');
    $n  = htmlspecialchars($u['name'],  ENT_QUOTES, 'UTF-8');
    $own= $u['owner'];
    $sel = '';
    foreach (fsu_roles() as $r)
      $sel .= '<option value="' . $r . '"' . ($u['role'] === $r ? ' selected' : '') . '>' . $r . '</option>';
    $seen = $u['seen'] ? gmdate('Y-m-d', $u['seen']) : '—';
    $lock = $own ? ' disabled' : '';
    $rows .= '<tr><td><b>' . $n . '</b><div class="mini ltr">' . $e . ($own ? ' 👑' : '') . '</div></td>'
      . '<td><form method="post" style="display:inline"><input type="hidden" name="fsu_csrf" value="' . $csrf . '">'
      . '<input type="hidden" name="fsu_uact" value="role"><input type="hidden" name="fsu_email" value="' . $e . '">'
      . '<select name="fsu_role" onchange="this.form.submit()"' . $lock . '>' . $sel . '</select></form></td>'
      . '<td class="mini">' . ($u['on'] ? '✅' : '⏸') . ' ' . $seen . '</td>'
      . '<td>' . ($own ? '<span class="mini">مالک</span>'
          : '<form method="post" style="display:inline" onsubmit="return confirm(\'' . $e . ' — ہٹا دیں?\')">'
            . '<input type="hidden" name="fsu_csrf" value="' . $csrf . '">'
            . '<input type="hidden" name="fsu_uact" value="del"><input type="hidden" name="fsu_email" value="' . $e . '">'
            . '<button type="submit">🗑</button></form>') . '</td></tr>';
  }
  $roleOpts = '';
  foreach (fsu_roles() as $r) $roleOpts .= '<option value="' . $r . '">' . $r . '</option>';
  $m = $msg ? '<p class="mini" style="color:var(--ok)">' . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . '</p>' : '';
  return <<<H
<div style="border:1px solid rgba(128,128,128,.25);border-radius:16px;padding:16px;margin:18px 0;font-family:inherit">
  <h3 style="margin:0 0 8px">👥 صارف · Users</h3>
  {$m}
  <div style="overflow-x:auto"><table style="width:100%;border-collapse:collapse;font-size:14px">
    <tr style="text-align:start;opacity:.7;font-size:12px"><th>نام</th><th>درجہ</th><th>حال · آخری بار</th><th></th></tr>
    {$rows}
  </table></div>
  <details style="margin-top:12px"><summary>➕ نیا صارف جوڑیں</summary>
    <form method="post" style="margin-top:8px;display:grid;gap:8px;max-width:420px">
      <input type="hidden" name="fsu_csrf" value="{$csrf}">
      <input type="hidden" name="fsu_uact" value="add">
      <input type="email" name="fsu_email" placeholder="email" required>
      <input type="text"  name="fsu_name"  placeholder="نام">
      <select name="fsu_role">{$roleOpts}</select>
      <input type="password" name="fsu_pw" placeholder="password" autocomplete="new-password" required>
      <button type="submit">جوڑ دیں</button>
    </form>
  </details>
  <details style="margin-top:8px"><summary>🔑 کسی کا password بدلیں</summary>
    <form method="post" style="margin-top:8px;display:grid;gap:8px;max-width:420px">
      <input type="hidden" name="fsu_csrf" value="{$csrf}">
      <input type="hidden" name="fsu_uact" value="pw">
      <input type="email" name="fsu_email" placeholder="email" required>
      <input type="password" name="fsu_pw" placeholder="نیا password" autocomplete="new-password" required>
      <button type="submit">بدل دیں</button>
    </form>
  </details>
</div>
H;
}
