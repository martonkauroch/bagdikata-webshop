<?php
define('BK_API', 1);
require __DIR__ . '/_lib.php';
ensure_data();
$cfg = load_config();
if (!$cfg || !has_admin($cfg)) { fail(401, 'Nincs jogosultság'); }
header('Cache-Control: no-store');
$raw = read_raw('analytics');
$days = $raw !== null ? json_decode($raw) : null;
if (!is_object($days)) { $days = new stdClass(); }
json_out(array('ok' => true, 'days' => $days));
