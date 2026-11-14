<?php
// Az oldaljelszó (teszt-belépés) módosítása az adminból. Csak bejelentkezett adminnak.
define('BK_API', 1);
require __DIR__ . '/_lib.php';
ensure_data();
require_post();
$cfg = load_config();
if (!$cfg) { fail(503, 'A szerver még nincs beállítva.'); }
require_admin($cfg);
if (rl_blocked('sitepass', 10, 900)) { fail(429, 'Túl sok próbálkozás. Próbáld újra 15 perc múlva.'); }
$d = body_json(2048);
$new = (is_object($d) && isset($d->password) && is_string($d->password)) ? $d->password : '';
$adm = (is_object($d) && isset($d->adminPassword) && is_string($d->adminPassword)) ? $d->adminPassword : '';
rl_hit('sitepass', 900);
if (!isset($cfg['admin_hash']) || !password_verify($adm, $cfg['admin_hash'])) { usleep(400000); fail(403, 'Az admin jelszó nem egyezik.'); }
if (strlen($new) < 6) { fail(400, 'Az új jelszó legalább 6 karakter legyen.'); }
if (strlen($new) > 200) { fail(400, 'Az új jelszó túl hosszú.'); }
$cfg['site_hash'] = password_hash($new, PASSWORD_DEFAULT);
$f = DATA_DIR . '/config.php';
$tmp = $f . '.tmp' . getmypid();
if (@file_put_contents($tmp, "<?php\nreturn " . var_export($cfg, true) . ";\n", LOCK_EX) === false) { fail(500, 'A beállítás nem írható.'); }
@chmod($tmp, 0600);
if (!@rename($tmp, $f)) { @unlink($tmp); fail(500, 'A beállítás nem menthető.'); }
if (function_exists('opcache_invalidate')) { @opcache_invalidate($f, true); }
rl_clear('sitepass');
json_out(array('ok' => true));
