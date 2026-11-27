<?php
define('BK_API', 1);
require __DIR__ . '/_lib.php';
ensure_data();
$cfg = load_config();
if (!$cfg || !has_admin($cfg)) { fail(401, 'Admin belépés szükséges'); }
header('Cache-Control: no-store');
require_post();
if (!isset($_FILES['logo']) || !is_array($_FILES['logo'])) { fail(400, 'Nincs feltöltött fájl.'); }
$u = $_FILES['logo'];
if (!empty($u['error']) || !is_uploaded_file($u['tmp_name'])) { fail(400, 'A feltöltés nem sikerült.'); }
if ($u['size'] > 2 * 1024 * 1024) { fail(400, 'A logó legfeljebb 2 MB lehet.'); }
$info = @getimagesize($u['tmp_name']);
$map = array(IMAGETYPE_PNG => 'png', IMAGETYPE_JPEG => 'jpg', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp');
if (!$info || !isset($map[$info[2]])) { fail(400, 'Csak PNG, JPG, GIF vagy WEBP kép tölthető fel.'); }
$dir = DATA_DIR . '/flogo';
if (!is_dir($dir) && !@mkdir($dir, 0755, true)) { fail(500, 'A mappa nem hozható létre.'); }
$name = substr(sha1_file($u['tmp_name']), 0, 16) . '.' . $map[$info[2]];
if (!is_file($dir . '/' . $name) && !@move_uploaded_file($u['tmp_name'], $dir . '/' . $name)) { fail(500, 'A logó nem menthető.'); }
json_out(array('ok' => true, 'file' => $name, 'size' => array($info[0], $info[1])));
