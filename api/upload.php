<?php
define('BK_API', 1);
require __DIR__ . '/_lib.php';
require __DIR__ . '/_media.php';
ensure_data();
require_post();
$cfg = load_config();
require_admin($cfg);
$raw = body_raw(20 * 1024 * 1024);
if ($raw === '') { fail(400, 'Üres fájl'); }
$info = @getimagesizefromstring($raw);
$types = array('image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif');
if (!$info || !isset($types[$info['mime']])) { fail(415, 'Csak JPG, PNG, WebP vagy GIF kép tölthető fel.'); }
$hash = hash('sha256', $raw);
$idx = media_sync();
$dup = media_find_hash($idx, $hash);
if ($dup !== null) { json_out(array('ok' => true, 'id' => $dup, 'dup' => true)); }
$id = bin2hex(random_bytes(16)) . '.' . $types[$info['mime']];
if (@file_put_contents(DATA_DIR . '/img/' . $id, $raw) === false) { fail(500, 'A kép nem menthető'); }
$name = isset($_SERVER['HTTP_X_NAME']) ? mb_substr(trim(rawurldecode((string) $_SERVER['HTTP_X_NAME'])), 0, 120) : '';
media_add($id, $hash, $name, $info, strlen($raw));
json_out(array('ok' => true, 'id' => $id, 'dup' => false));
