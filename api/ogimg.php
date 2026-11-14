<?php
define('BK_API', 1);
require __DIR__ . '/_lib.php';
require __DIR__ . '/_seo.php';
$cfg = load_config();
if (!$cfg || !site_is_public()) { http_response_code(404); exit; }
$id = isset($_GET['id']) ? (string) $_GET['id'] : '';
if (!preg_match('/^[A-Za-z0-9_-]{1,80}$/', $id)) { http_response_code(404); exit; }
$p = seo_find_product($id);
if (!$p) { http_response_code(404); exit; }
list($url, $file) = seo_image(isset($p->imageUrl) ? $p->imageUrl : '');
if (!$file || !is_file($file) || !function_exists('imagecreatetruecolor')) { header('Location: ' . ($url ?: seo_site_url() . '/favicon.svg'), true, 302); exit; }
$dir = DATA_DIR . '/og';
if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
$cache = $dir . '/' . sha1($file . '|' . filemtime($file) . '|v1') . '.jpg';
if (!is_file($cache)) {
    $info = @getimagesize($file);
    $src = null;
    if ($info) {
        if ($info[2] === IMAGETYPE_JPEG) { $src = @imagecreatefromjpeg($file); }
        elseif ($info[2] === IMAGETYPE_PNG) { $src = @imagecreatefrompng($file); }
        elseif ($info[2] === IMAGETYPE_WEBP && function_exists('imagecreatefromwebp')) { $src = @imagecreatefromwebp($file); }
        elseif ($info[2] === IMAGETYPE_GIF) { $src = @imagecreatefromgif($file); }
    }
    if (!$src) { header('Location: ' . $url, true, 302); exit; }
    $W = 1200; $H = 630; $sw = imagesx($src); $sh = imagesy($src);
    $dst = imagecreatetruecolor($W, $H);
    imagefill($dst, 0, 0, imagecolorallocate($dst, 246, 243, 238));
    $ratio = $sw / $sh; $target = $W / $H;
    if ($ratio >= $target * 0.85) {
        $scale = max($W / $sw, $H / $sh); $cw = (int) round($W / $scale); $ch = (int) round($H / $scale);
        imagecopyresampled($dst, $src, 0, 0, (int) (($sw - $cw) / 2), (int) (($sh - $ch) / 2), $W, $H, $cw, $ch);
    } else {
        $scale = $H / $sh; $nw = (int) round($sw * $scale);
        imagecopyresampled($dst, $src, (int) (($W - $nw) / 2), 0, 0, 0, $nw, $H, $sw, $sh);
    }
    imagejpeg($dst, $cache, 86);
}
header('Content-Type: image/jpeg');
header('Cache-Control: public, max-age=86400');
header('Content-Length: ' . filesize($cache));
readfile($cache);
