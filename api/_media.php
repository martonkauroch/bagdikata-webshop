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
