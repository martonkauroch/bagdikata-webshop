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

function bk_watermarked($src, $ext, $id) {
    $wmLight = dirname(__DIR__) . '/assets/watermark.png';
    $wmDark = dirname(__DIR__) . '/assets/watermark-dark.png';
    if ($ext === 'gif' || !is_file($wmLight) || !is_file($wmDark) || !function_exists('imagecreatefrompng')) { return null; }
    $cacheDir = DATA_DIR . '/imgwm';
    $cache = $cacheDir . '/' . $id;
    $ver = 'v3-' . (string) @filemtime($wmLight) . '-' . (string) @filemtime($wmDark) . '-' . (string) @filemtime($src);
    if (is_file($cache) && is_file($cache . '.v') && @file_get_contents($cache . '.v') === $ver) { return $cache; }
    $info = @getimagesize($src);
    if (!$info || $info[0] < 120 || $info[1] < 120 || $info[0] * $info[1] > 24000000) { return null; }
    if ($info[2] === IMAGETYPE_JPEG) { $im = @imagecreatefromjpeg($src); }
    elseif ($info[2] === IMAGETYPE_PNG) { $im = @imagecreatefrompng($src); }
    elseif ($info[2] === IMAGETYPE_WEBP && function_exists('imagecreatefromwebp')) { $im = @imagecreatefromwebp($src); }
    else { return null; }
    if (!$im) { return null; }
    $W = imagesx($im); $H = imagesy($im);
    $w0 = imagesx(imagecreatefrompng($wmLight)); $h0 = imagesy(imagecreatefrompng($wmLight));
    $w = max(60, (int) round($W * 0.12)); $h = (int) round($h0 * $w / $w0); $m = (int) round($W * 0.022);
    $x = $W - $w - $m; $y = $H - $h - $m;
    $rx = max(0, $x - (int) ($m / 2)); $ry = max(0, $y - (int) ($m / 2));
    $probe = imagecreatetruecolor(1, 1);
    imagecopyresampled($probe, $im, 0, 0, $rx, $ry, 1, 1, $W - $rx, $H - $ry);
    $c = imagecolorat($probe, 0, 0); imagedestroy($probe);
    $lum = 0.299 * (($c >> 16) & 255) + 0.587 * (($c >> 8) & 255) + 0.114 * ($c & 255);
    $wm = @imagecreatefrompng($lum >= 128 ? $wmDark : $wmLight);
    if (!$wm) { return null; }
    imagealphablending($im, true);
    imagecopyresampled($im, $wm, $x, $y, 0, 0, $w, $h, imagesx($wm), imagesy($wm));
    if (!is_dir($cacheDir)) { @mkdir($cacheDir, 0755, true); }
    $tmp = $cache . '.tmp' . getmypid();
    if ($ext === 'jpg') { $ok = imagejpeg($im, $tmp, 90); }
    elseif ($ext === 'png') { imagesavealpha($im, true); $ok = imagepng($im, $tmp, 6); }
    elseif ($ext === 'webp' && function_exists('imagewebp')) { imagesavealpha($im, true); $ok = imagewebp($im, $tmp, 90); }
    else { $ok = false; }
    imagedestroy($im); imagedestroy($wm);
    if (!$ok) { @unlink($tmp); return null; }
    @rename($tmp, $cache); @file_put_contents($cache . '.v', $ver);
    return is_file($cache) ? $cache : null;
}
$out = null;
$tw = isset($_GET['w']) ? (int) $_GET['w'] : 0;
if (in_array($tw, array(160, 320, 640), true) && $m[1] !== 'gif' && function_exists('imagecreatetruecolor')) {
    $dir = DATA_DIR . '/imgthumb';
    $cache = $dir . '/' . $tw . '_' . $id . '.jpg';
    if (!is_file($cache) || filemtime($cache) < filemtime($f)) {
        $info = @getimagesize($f);
        if ($info && $info[0] * $info[1] <= 40000000) {
            if ($info[2] === IMAGETYPE_JPEG) { $im = @imagecreatefromjpeg($f); }
            elseif ($info[2] === IMAGETYPE_PNG) { $im = @imagecreatefrompng($f); }
            elseif ($info[2] === IMAGETYPE_WEBP && function_exists('imagecreatefromwebp')) { $im = @imagecreatefromwebp($f); }
            else { $im = false; }
            if ($im) {
                $W = imagesx($im); $H = imagesy($im);
                if (max($W, $H) > $tw) {
                    $k = $tw / max($W, $H); $nw = max(1, (int) round($W * $k)); $nh = max(1, (int) round($H * $k));
                    $t = imagecreatetruecolor($nw, $nh);
                    imagefill($t, 0, 0, imagecolorallocate($t, 255, 255, 255));
                    imagecopyresampled($t, $im, 0, 0, 0, 0, $nw, $nh, $W, $H); imagedestroy($im); $im = $t;
                }
                if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
                $tmp = $cache . '.tmp' . getmypid();
                if (@imagejpeg($im, $tmp, 78)) { @rename($tmp, $cache); }
                imagedestroy($im);
            }
        }
    }
    if (is_file($cache)) { header('Content-Type: image/jpeg'); header('Cache-Control: private, max-age=604800'); header('X-Content-Type-Options: nosniff'); header('Content-Length: ' . filesize($cache)); readfile($cache); exit; }
}
$send = $out ? $out : $f;
header('Content-Type: ' . $mime[$m[1]]);
header('Cache-Control: private, max-age=86400');
header('X-Content-Type-Options: nosniff');
header('Content-Length: ' . filesize($send));
readfile($send);
