<?php
define('BK_API', 1);
require __DIR__ . '/_lib.php';
$f = isset($_GET['f']) && is_string($_GET['f']) && preg_match('/^([a-f0-9]{16})\.(png|jpg|gif|webp)$/', $_GET['f'], $m) ? $m : null;
$path = $f ? DATA_DIR . '/flogo/' . $f[0] : '';
if (!$f || !is_file($path)) { http_response_code(404); exit; }
$types = array('png' => 'image/png', 'jpg' => 'image/jpeg', 'gif' => 'image/gif', 'webp' => 'image/webp');
header('Content-Type: ' . $types[$f[2]]);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=31536000, immutable');
header('Content-Length: ' . filesize($path));
readfile($path);
