<?php
define('BK_API', 1);
require __DIR__ . '/_lib.php';
require __DIR__ . '/_media.php';
$cfg = load_config();
if (!has_site($cfg)) { http_response_code(401); exit; }
$out = array();
foreach (media_index() as $id => $m) {
    if (isset($m['credit']) && is_array($m['credit']) && $m['credit']) { $out['srv:' . $id] = $m['credit']; }
}
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=300');
echo json_encode(array('ok' => true, 'credits' => (object) $out), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
