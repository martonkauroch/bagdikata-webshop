<?php
define('BK_API', 1);
require __DIR__ . '/_lib.php';
$cfg = load_config();
$id = isset($_GET['id']) ? (string) $_GET['id'] : '';
if (!has_site($cfg) && !landing_has_image($id)) { http_response_code(401); exit; }
if (!preg_match('/^[a-f0-9]{32}\.(jpg|png|webp|gif)$/', $id, $m)) { http_response_code(404); exit; }
$f = DATA_DIR . '/img/' . $id;
if (!is_file($f)) { http_response_code(404); exit; }
$mime = array('jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif');
header('Content-Type: ' . $mime[$m[1]]);
header('Cache-Control: private, max-age=31536000, immutable');
header('X-Content-Type-Options: nosniff');
header('Content-Length: ' . filesize($f));
readfile($f);
