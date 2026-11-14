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
// A képmappában lévő, még nem indexelt fájlok felvétele (korábbi feltöltések), duplikátumok jelölése.
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
    foreach ($idx as $id => $m) { if (!is_file($dir . '/' . $id)) { unset($idx[$id]); $changed = true; } }
    if ($changed) { write_raw('media', json_encode($idx, JSON_UNESCAPED_UNICODE)); }
    return $idx;
}
function media_usage_text() {
    $t = '';
    foreach (array('store', 'content', 'settings') as $n) { $r = read_raw($n); if ($r) { $t .= $r; } }
    return $t;
}

// Nagy képek optimalizálása: leskálázás (hosszabb oldal max. 2000 px), JPEG újratömörítés, EXIF-forgatás megtartása.
// Visszatérés: array(adat, kiterjesztés, optimalizálták-e). Ha nem jár előnnyel, az eredeti marad.
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

function media_needs_opt($m) {
    if (!empty($m['opt'])) { return false; }
    $ext = strtolower(pathinfo(isset($m['_id']) ? $m['_id'] : '', PATHINFO_EXTENSION));
    if ($ext === 'gif') { return false; }
    return (isset($m['size']) && $m['size'] > 1200000) || (isset($m['w'], $m['h']) && max($m['w'], $m['h']) > 2000);
}
