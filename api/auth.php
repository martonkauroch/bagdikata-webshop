<?php
define('BK_API', 1);
require __DIR__ . '/_lib.php';
ensure_data();
require_post();
$cfg = load_config();
if (!$cfg) { fail(503, 'A szerver még nincs beállítva.'); }
if (rl_blocked('site', 8, 900)) { fail(429, 'Túl sok próbálkozás. Próbáld újra 15 perc múlva.'); }
$d = body_json(2048);
$pw = (is_object($d) && isset($d->password) && is_string($d->password)) ? $d->password : '';
$remember = is_object($d) && !empty($d->remember);
if ($pw === '' || !password_verify($pw, $cfg['site_hash'])) {
    rl_hit('site', 900);
    usleep(400000);
    fail(401, 'Hibás jelszó.');
}
rl_clear('site');
$ttl = $remember ? 30 * 86400 : 12 * 3600;
put_cookie('bk_site', make_token($cfg, 'site', $ttl), $ttl);
json_out(array('ok' => true));
