<?php
define('BK_API', 1);
require __DIR__ . '/_lib.php';
require __DIR__ . '/_coupon.php';
ensure_data();
require_post();
$cfg = load_config();
require_site($cfg);
if (rl_blocked('coupon', 40, 3600)) { fail(429, 'Túl sok próbálkozás. Próbáld később.'); }
rl_hit('coupon', 3600);
$d = body_json(1000);
$code = (is_object($d) && isset($d->code) && is_string($d->code)) ? coupon_code_norm($d->code) : '';
if ($code === '') { json_out(array('ok' => true, 'valid' => false, 'reason' => 'unknown')); }
list($st, $c) = coupon_check($code);
if ($st === 'ok') { json_out(array('ok' => true, 'valid' => true, 'code' => $code, 'percent' => (int) $c['percent'])); }
json_out(array('ok' => true, 'valid' => false, 'reason' => $st));
