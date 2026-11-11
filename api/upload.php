<?php
define('BK_API', 1);
require __DIR__ . '/_lib.php';
ensure_data();
require_post();
$cfg = load_config();
require_admin($cfg);
$raw = body_raw(20 * 1024 * 1024);
if ($raw === '') { fail(400, 'Üres fájl'); }
$info = @getimagesizefromstring($raw);
$types = array('image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif');
if (!$info || !isset($types[$info['mime']])) { fail(415, 'Csak JPG, PNG, WebP vagy GIF kép tölthető fel.'); }
$id = bin2hex(random_bytes(16)) . '.' . $types[$info['mime']];
if (@file_put_contents(DATA_DIR . '/img/' . $id, $raw) === false) { fail(500, 'A kép nem menthető'); }
json_out(array('ok' => true, 'id' => $id));
