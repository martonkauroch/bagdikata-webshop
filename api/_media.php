<?php
if (!defined('BK_API')) { exit; }

function media_index() {
    $raw = read_raw('media');
    $a = $raw ? json_decode($raw, true) : array();
    return is_array($a) ? $a : array();
}
function media_find_hash($idx, $hash) {
    foreach ($idx as $id => $m) { if (isset($m['hash']) && $m['hash'] === $hash && is_file(DATA_DIR . '/img/' . $id)) { return $id; } }
    return null;
}
function media_add($id, $hash, $name, $info, $size) {
    mutate_json('media', function ($cur) use ($id, $hash, $name, $info, $size) {
        $a = is_object($cur) ? json_decode(json_encode($cur), true) : array();
        if (!is_array($a)) { $a = array(); }
        $a[$id] = array('hash' => $hash, 'name' => $name, 'w' => $info ? (int) $info[0] : 0, 'h' => $info ? (int) $info[1] : 0, 'size' => (int) $size, 'added' => gmdate('c'));
        return $a;
    });
}
function media_sync() {
    $idx = media_index();
    $dir = DATA_DIR . '/img';
    $changed = false;
    $files = is_dir($dir) ? scandir($dir) : array();
    foreach ($files as $f) {
        if (!preg_match('/^[a-f0-9]{32}\.(jpg|png|webp|gif)$/', $f)) { continue; }
        if (isset($idx[$f]) && isset($idx[$f]['hash'])) { continue; }
        $p = $dir . '/' . $f;
        $info = @getimagesize($p);
        $idx[$f] = array('hash' => hash_file('sha256', $p), 'name' => '', 'w' => $info ? (int) $info[0] : 0, 'h' => $info ? (int) $info[1] : 0, 'size' => (int) @filesize($p), 'added' => gmdate('c', (int) @filemtime($p)));
        $changed = true;
    }
    $have = array();
    foreach ($idx as $m) { if (isset($m['src'])) { $have[$m['src']] = true; } }
    $ad = dirname(__DIR__) . '/assets';
    foreach (is_dir($ad) ? scandir($ad) : array() as $f) {
        if (!preg_match('/^(.+)-[A-Za-z0-9_-]{8}\.(jpg|jpeg)$/', $f, $mm) || preg_match('/^watermark/', $f)) { continue; }
        $rel = 'assets/' . $f; if (isset($have[$rel])) { continue; }
        $p = $ad . '/' . $f; $hash = hash_file('sha256', $p);
        $dup = media_find_hash($idx, $hash);
        if ($dup !== null) { $idx[$dup]['src'] = $rel; $changed = true; continue; }
        $nid = substr($hash, 0, 32) . '.jpg';
        if (!@copy($p, $dir . '/' . $nid)) { continue; }
        $info = @getimagesize($p);
        $idx[$nid] = array('hash' => $hash, 'name' => str_replace('-', ' ', $mm[1]), 'w' => $info ? (int) $info[0] : 0, 'h' => $info ? (int) $info[1] : 0, 'size' => (int) @filesize($p), 'added' => gmdate('c', 1700000000), 'src' => $rel);
        $changed = true;
    }
    foreach ($idx as $id => $m) { if (!is_file($dir . '/' . $id)) { unset($idx[$id]); $changed = true; } }
    if ($changed) { write_raw('media', json_encode($idx, JSON_UNESCAPED_UNICODE)); }
    return $idx;
}
function media_usage_text() {
    $t = '';
    foreach (array('store', 'content', 'settings') as $n) { $r = read_raw($n); if ($r) { $t .= $r; } }
    return $t;
}

function media_optimize($raw, $info, $ext, $allowConvert = true) {
    $MAX = 2000;
    if (!function_exists('imagecreatefromstring') || $ext === 'gif') { return array($raw, $ext, false); }
    $w = (int) $info[0]; $h = (int) $info[1];
    if ($w < 1 || $h < 1 || $w * $h > 40000000) { return array($raw, $ext, false); }
    $big = max($w, $h) > $MAX;
    if (!$big && strlen($raw) < 1200000) { return array($raw, $ext, false); }
    $im = @imagecreatefromstring($raw);
    if (!$im) { return array($raw, $ext, false); }
    if ($ext === 'jpg' && function_exists('exif_read_data')) {
        $ex = @exif_read_data('data://image/jpeg;base64,' . base64_encode(substr($raw, 0, 262144)));
        $o = (is_array($ex) && isset($ex['Orientation'])) ? (int) $ex['Orientation'] : 1;
        $deg = ($o === 3) ? 180 : (($o === 6) ? -90 : (($o === 8) ? 90 : 0));
        if ($deg !== 0) { $r = @imagerotate($im, $deg, 0); if ($r) { imagedestroy($im); $im = $r; } }
    }
    $cw = imagesx($im); $ch = imagesy($im);
    if (max($cw, $ch) > $MAX) {
        $k = $MAX / max($cw, $ch); $nw = max(1, (int) round($cw * $k)); $nh = max(1, (int) round($ch * $k));
        $t = imagecreatetruecolor($nw, $nh);
        if ($ext !== 'jpg') { imagealphablending($t, false); imagesavealpha($t, true); imagefill($t, 0, 0, imagecolorallocatealpha($t, 0, 0, 0, 127)); }
        imagecopyresampled($t, $im, 0, 0, 0, 0, $nw, $nh, $cw, $ch); imagedestroy($im); $im = $t; $cw = $nw; $ch = $nh;
    }
    $out = $ext;
    if ($allowConvert && $ext === 'png' && strlen($raw) > 1000000) {
        $alpha = false;
        for ($y = 0; $y < 24 && !$alpha; $y++) { for ($x = 0; $x < 24; $x++) { if (((imagecolorat($im, (int) ($x * ($cw - 1) / 23), (int) ($y * ($ch - 1) / 23)) >> 24) & 127) > 0) { $alpha = true; break; } } }
        if (!$alpha) { $out = 'jpg'; }
    }
    ob_start();
    if ($out === 'jpg') { imageinterlace($im, true); $ok = imagejpeg($im, null, 85); }
    elseif ($out === 'png') { imagesavealpha($im, true); $ok = imagepng($im, null, 7); }
    elseif ($out === 'webp' && function_exists('imagewebp')) { imagesavealpha($im, true); $ok = imagewebp($im, null, 85); }
    else { $ok = false; }
    $data = ob_get_clean(); imagedestroy($im);
    if (!$ok || $data === '' || $data === false) { return array($raw, $ext, false); }
    if (strlen($data) >= strlen($raw) * 0.9 && !$big) { return array($raw, $ext, false); }
    if (strlen($data) >= strlen($raw) && $out === $ext) { return array($raw, $ext, false); }
    return array($data, $out, true);
}

function media_thumb($f, $id, $tw) {
    if (!function_exists('imagecreatetruecolor')) { return null; }
    $dir = DATA_DIR . '/imgthumb';
    $cache = $dir . '/' . $tw . '_' . $id . '.jpg';
    if (is_file($cache) && filemtime($cache) >= filemtime($f) && filesize($cache) > 0) { return $cache; }
    if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
    $lock = null;
    for ($try = 0; $try < 30 && !$lock; $try++) {
        for ($slot = 0; $slot < 2; $slot++) {
            $h = @fopen($dir . '/.lock' . $slot, 'c');
            if ($h && flock($h, LOCK_EX | LOCK_NB)) { $lock = $h; break; }
            if ($h) { fclose($h); }
        }
        if (!$lock) { usleep(100000); clearstatcache(); if (is_file($cache) && filesize($cache) > 0 && filemtime($cache) >= filemtime($f)) { return $cache; } }
    }
    if (!$lock) { return null; }
    clearstatcache();
    if (!(is_file($cache) && filesize($cache) > 0 && filemtime($cache) >= filemtime($f))) {
        $info = @getimagesize($f);
        $im = false;
        if ($info && $info[0] * $info[1] <= 40000000) {
            @ini_set('memory_limit', '256M');
            if ($info[2] === IMAGETYPE_JPEG) { $im = @imagecreatefromjpeg($f); }
            elseif ($info[2] === IMAGETYPE_PNG) { $im = @imagecreatefrompng($f); }
            elseif ($info[2] === IMAGETYPE_WEBP && function_exists('imagecreatefromwebp')) { $im = @imagecreatefromwebp($f); }
        }
        if ($im) {
            $W = imagesx($im); $H = imagesy($im);
            if (max($W, $H) > $tw) {
                $k = $tw / max($W, $H); $nw = max(1, (int) round($W * $k)); $nh = max(1, (int) round($H * $k));
                $t = imagecreatetruecolor($nw, $nh);
                imagefill($t, 0, 0, imagecolorallocate($t, 255, 255, 255));
                imagecopyresampled($t, $im, 0, 0, 0, 0, $nw, $nh, $W, $H); imagedestroy($im); $im = $t;
            }
            $tmp = $cache . '.tmp' . getmypid();
            if (@imagejpeg($im, $tmp, 78)) { @rename($tmp, $cache); } else { @unlink($tmp); }
            imagedestroy($im);
        }
    }
    flock($lock, LOCK_UN); fclose($lock);
    clearstatcache();
    return (is_file($cache) && filesize($cache) > 0) ? $cache : null;
}

function media_sig_of($f, $id) {
    $src = media_thumb($f, $id, 320);
    $p = $src !== null ? $src : $f;
    $info = @getimagesize($p);
    if (!$info || $info[0] * $info[1] > 40000000) { return null; }
    @ini_set('memory_limit', '256M');
    if ($info[2] === IMAGETYPE_JPEG) { $im = @imagecreatefromjpeg($p); }
    elseif ($info[2] === IMAGETYPE_PNG) { $im = @imagecreatefrompng($p); }
    elseif ($info[2] === IMAGETYPE_WEBP && function_exists('imagecreatefromwebp')) { $im = @imagecreatefromwebp($p); }
    elseif ($info[2] === IMAGETYPE_GIF) { $im = @imagecreatefromgif($p); }
    else { $im = false; }
    if (!$im) { return null; }
    $W = imagesx($im); $H = imagesy($im);
    $t = imagecreatetruecolor(16, 16);
    imagecopyresampled($t, $im, 0, 0, 0, 0, 16, 16, $W, $H); imagedestroy($im);
    $a = array();
    for ($y = 0; $y < 16; $y++) { for ($x = 0; $x < 16; $x++) { $c = imagecolorat($t, $x, $y); $a[] = 0.299 * (($c >> 16) & 255) + 0.587 * (($c >> 8) & 255) + 0.114 * ($c & 255); } }
    imagedestroy($t);
    $mean = array_sum($a) / 256; $v = 0; foreach ($a as $x) { $v += ($x - $mean) * ($x - $mean); }
    $sd = sqrt($v / 256); if ($sd < 1) { $sd = 1; }
    $b = '';
    foreach ($a as $x) { $b .= chr(max(0, min(255, (int) round(($x - $mean) / $sd * 40 + 128)))); }
    return array('sig' => base64_encode($b), 'ar' => round($W / max(1, $H), 3));
}
function media_sig_dist($a, $b) {
    $x = base64_decode($a); $y = base64_decode($b);
    if (strlen($x) !== 256 || strlen($y) !== 256) { return 999; }
    $s = 0; for ($i = 0; $i < 256; $i++) { $s += abs(ord($x[$i]) - ord($y[$i])); }
    return $s / 256;
}
function media_is_dupe($m1, $m2) {
    if (isset($m1['hash'], $m2['hash']) && $m1['hash'] === $m2['hash']) { return true; }
    if (empty($m1['sig']) || empty($m2['sig'])) { return false; }
    if (abs($m1['ar'] - $m2['ar']) > 0.02) { return false; }
    return media_sig_dist($m1['sig'], $m2['sig']) < 4.0;
}
function media_keep_rank($m, $used) {
    return array((int) $m['w'] * (int) $m['h'], (isset($m['name']) && $m['name'] !== '') ? 1 : 0, $used ? 1 : 0, -strtotime(isset($m['added']) ? $m['added'] : 'now'));
}

function media_needs_opt($m) {
    if (!empty($m['opt'])) { return false; }
    $ext = strtolower(pathinfo(isset($m['_id']) ? $m['_id'] : '', PATHINFO_EXTENSION));
    if ($ext === 'gif') { return false; }
    return (isset($m['size']) && $m['size'] > 1200000) || (isset($m['w'], $m['h']) && max($m['w'], $m['h']) > 2000);
}

function media_clean_link($v) {
    $v = trim($v);
    if ($v === '') { return ''; }
    if (strlen($v) > 300 || preg_match('/\s/', $v)) { return null; }
    if ($v[0] === '@') { return preg_match('/^@[A-Za-z0-9._]{1,40}$/', $v) ? 'https://www.instagram.com/' . substr($v, 1) : null; }
    if (!preg_match('#^https?://#i', $v)) { $v = 'https://' . $v; }
    $p = @parse_url($v);
    if (!$p || empty($p['host']) || !preg_match('/^[A-Za-z0-9.-]+\.[A-Za-z]{2,}$/', $p['host'])) { return null; }
    if (isset($p['scheme']) && !in_array(strtolower($p['scheme']), array('http', 'https'), true)) { return null; }
    return $v;
}
