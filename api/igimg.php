<?php
define('BK_API', 1);
require __DIR__ . '/_lib.php';
require __DIR__ . '/_ig.php';
$id = isset($_GET['id']) && is_string($_GET['id']) && preg_match('/^[a-f0-9]{16}$/', $_GET['id']) ? $_GET['id'] : '';
$raw = $id !== '' ? read_raw('ig_imgs') : null; $a = $raw ? json_decode($raw, true) : array();
$e = is_array($a) && isset($a[$id]) ? $a[$id] : null;
if (!$e || $e['exp'] < time() || !is_file($e['f']) || !function_exists('imagecreatetruecolor')) { http_response_code(404); exit; }
$dir = DATA_DIR . '/og';
if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
$cache = $dir . '/ig-' . sha1($e['f'] . '|' . filemtime($e['f']) . '|' . $e['y'] . '|v1') . '.jpg';
if (!is_file($cache) && !ig_render($e['f'], (float) $e['y'], $cache)) { http_response_code(404); exit; }
header('Content-Type: image/jpeg');
header('Cache-Control: public, max-age=3600');
header('Content-Length: ' . filesize($cache));
readfile($cache);
