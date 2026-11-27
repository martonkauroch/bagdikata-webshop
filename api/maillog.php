<?php
define('BK_API', 1);
require __DIR__ . '/_lib.php';
ensure_data();
$cfg = load_config();
if (!$cfg || !has_admin($cfg)) { fail(401, 'Admin belépés szükséges'); }
header('Cache-Control: no-store');
$f = DATA_DIR . '/mail.log';
$lines = array();
if (is_file($f)) {
    $sz = filesize($f); $fp = @fopen($f, 'rb');
    if ($fp) { if ($sz > 60000) { fseek($fp, $sz - 60000); fgets($fp); } $raw = stream_get_contents($fp); fclose($fp); $lines = preg_split('/\r?\n/', trim((string) $raw)); }
}
$lines = array_reverse(array_slice(array_values(array_filter($lines, 'strlen')), -150));
json_out(array('ok' => true, 'lines' => $lines));
