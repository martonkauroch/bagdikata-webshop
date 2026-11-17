<?php
// Admin jelszó módosítása: { current, password, code } – a régi jelszó és egy friss ellenőrző kód is kell.
define('BK_API', 1);
require __DIR__ . '/_lib.php';
require __DIR__ . '/_mfa.php';
ensure_data();
require_post();
$cfg = load_config();
if (!$cfg) { fail(503, 'A szerver még nincs beállítva.'); }
require_admin($cfg);
if (rl_blocked('adminpass', 6, 900)) { fail(429, 'Túl sok próbálkozás. Próbáld újra 15 perc múlva.'); }
$d = body_json(2048);
$cur = (is_object($d) && isset($d->current) && is_string($d->current)) ? $d->current : '';
$new = (is_object($d) && isset($d->password) && is_string($d->password)) ? $d->password : '';
$code = (is_object($d) && isset($d->code) && is_string($d->code)) ? $d->code : '';
rl_hit('adminpass', 900);
if (!isset($cfg['admin_hash']) || !password_verify($cur, $cfg['admin_hash'])) { usleep(400000); fail(403, 'A jelenlegi jelszó nem egyezik.'); }
$m = mfa_data();
if (mfa_enrolled($m)) {
    $c = totp_check($m['secret'], $code, isset($m['lastCounter']) ? (int) $m['lastCounter'] : -1);
    if ($c === null) { usleep(300000); fail(403, 'Hibás vagy lejárt ellenőrző kód (hitelesítő alkalmazás).'); }
    $m['lastCounter'] = $c;
    mfa_save($m);
}
// erős jelszó: legalább 12 karakter, betű és szám is, nem lehet a régi és nem tartalmazhatja az e-mail cím elejét
if (strlen($new) > 200) { fail(400, 'Az új jelszó túl hosszú.'); }
if (mb_strlen($new, 'UTF-8') < 12) { fail(400, 'Az új jelszó legalább 12 karakter legyen.'); }
if (!preg_match('/\pL/u', $new) || !preg_match('/\d/', $new)) { fail(400, 'Az új jelszóban betű és szám is legyen.'); }
if (password_verify($new, $cfg['admin_hash'])) { fail(400, 'Az új jelszó nem lehet ugyanaz, mint a régi.'); }
$local = strtolower((string) strstr((string) $cfg['admin_email'], '@', true));
if (strlen($local) >= 4 && stripos($new, $local) !== false) { fail(400, 'Az új jelszó ne tartalmazza az e-mail címed elejét.'); }
$cfg['admin_hash'] = password_hash($new, PASSWORD_DEFAULT);
$f = DATA_DIR . '/config.php';
$tmp = $f . '.tmp' . getmypid();
if (@file_put_contents($tmp, "<?php\nreturn " . var_export($cfg, true) . ";\n", LOCK_EX) === false) { fail(500, 'A beállítás nem írható.'); }
@chmod($tmp, 0600);
if (!@rename($tmp, $f)) { @unlink($tmp); fail(500, 'A beállítás nem menthető.'); }
if (function_exists('opcache_invalidate')) { @opcache_invalidate($f, true); }
rl_clear('adminpass');
json_out(array('ok' => true));
