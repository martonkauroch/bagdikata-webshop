<?php
define('BK_API', 1);
require __DIR__ . '/_lib.php';
ensure_data();
require_post();
$cfg = load_config();
if (!$cfg) { fail(503, 'A szerver még nincs beállítva.'); }
require_site($cfg);
if (rl_blocked('admin', 6, 900)) { fail(429, 'Túl sok sikertelen próbálkozás. Próbáld újra 15 perc múlva.'); }
$d = body_json(2048);
$email = (is_object($d) && isset($d->email) && is_string($d->email)) ? strtolower(trim($d->email)) : '';
$pw = (is_object($d) && isset($d->password) && is_string($d->password)) ? $d->password : '';
$okEmail = hash_equals(strtolower($cfg['admin_email']), $email);
$okPw = password_verify($pw, $cfg['admin_hash']);
if (!($okEmail && $okPw)) {
    rl_hit('admin', 900);
    usleep(400000);
    fail(401, 'Hibás felhasználónév vagy jelszó.');
}
rl_clear('admin');
put_cookie('bk_admin', make_token($cfg, 'admin', 8 * 3600), 8 * 3600);
json_out(array('ok' => true, 'email' => $cfg['admin_email']));
