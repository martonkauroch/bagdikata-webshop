<?php
define('BK_API', 1);
require __DIR__ . '/_lib.php';
require __DIR__ . '/_media.php';
ensure_data();
require_post();
$cfg = load_config();
require_admin($cfg);
$d = body_json(4096);
$act = (is_object($d) && isset($d->action) && is_string($d->action)) ? $d->action : 'list';
if ($act === 'list') {
    $idx = media_sync();
    $use = media_usage_text();
    $seen = array(); $out = array();
    foreach ($idx as $id => $m) {
        $h = isset($m['hash']) ? $m['hash'] : $id;
        if (isset($seen[$h])) { continue; }
        $seen[$h] = true;
        $out[] = array('id' => $id, 'name' => isset($m['name']) ? $m['name'] : '', 'w' => isset($m['w']) ? $m['w'] : 0, 'h' => isset($m['h']) ? $m['h'] : 0, 'size' => isset($m['size']) ? $m['size'] : 0, 'added' => isset($m['added']) ? $m['added'] : '', 'used' => strpos($use, 'srv:' . $id) !== false);
    }
    $cand = 0; $savable = 0;
    foreach ($idx as $id => $m) { $m['_id'] = $id; if (media_needs_opt($m)) { $cand++; $savable += isset($m['size']) ? (int) $m['size'] : 0; } }
    usort($out, function ($a, $b) { return strcmp($b['added'], $a['added']); });
    json_out(array('ok' => true, 'items' => $out, 'toOptimize' => $cand, 'toOptimizeBytes' => $savable));
}
if ($act === 'delete') {
    $id = (is_object($d) && isset($d->id) && is_string($d->id)) ? $d->id : '';
    if (!preg_match('/^[a-f0-9]{32}\.(jpg|png|webp|gif)$/', $id)) { fail(400, 'Érvénytelen kép'); }
    if (strpos(media_usage_text(), 'srv:' . $id) !== false) { fail(409, 'A kép használatban van (termék vagy tartalom), ezért nem törölhető.'); }
    @unlink(DATA_DIR . '/img/' . $id);
    @unlink(DATA_DIR . '/imgwm/' . $id);
    $idx = media_index(); unset($idx[$id]);
    write_raw('media', json_encode($idx, JSON_UNESCAPED_UNICODE));
    json_out(array('ok' => true));
}
if ($act === 'rename') {
    $id = (is_object($d) && isset($d->id) && is_string($d->id)) ? $d->id : '';
    $name = (is_object($d) && isset($d->name) && is_string($d->name)) ? mb_substr(trim($d->name), 0, 120) : '';
    $idx = media_index();
    if (!isset($idx[$id])) { fail(404, 'Nincs ilyen kép'); }
    $idx[$id]['name'] = $name;
    write_raw('media', json_encode($idx, JSON_UNESCAPED_UNICODE));
    json_out(array('ok' => true));
}
if ($act === 'optimize') {
    @set_time_limit(60);
    $idx = media_sync();
    $t0 = microtime(true); $done = 0; $before = 0; $after = 0; $left = 0;
    $bak = DATA_DIR . '/img_orig';
    foreach ($idx as $id => $m) {
        $m['_id'] = $id;
        if (!media_needs_opt($m)) { continue; }
        if ($done >= 12 || microtime(true) - $t0 > 20) { $left++; continue; }
        $p = DATA_DIR . '/img/' . $id;
        $raw = @file_get_contents($p);
        $info = $raw !== false ? @getimagesizefromstring($raw) : false;
        $ext = strtolower(pathinfo($id, PATHINFO_EXTENSION));
        if (!$info) { $idx[$id]['opt'] = true; $done++; continue; }
        list($data, $ext2, $opt) = media_optimize($raw, $info, $ext, false);
        if ($opt && $ext2 === $ext) {
            if (!is_dir($bak)) { @mkdir($bak, 0755, true); }
            if (!is_file($bak . '/' . $id)) { @copy($p, $bak . '/' . $id); }
            $tmp = $p . '.tmp' . getmypid();
            if (@file_put_contents($tmp, $data) !== false && @rename($tmp, $p)) {
                $ni = @getimagesizefromstring($data) ?: $info;
                $idx[$id]['size'] = strlen($data); $idx[$id]['w'] = (int) $ni[0]; $idx[$id]['h'] = (int) $ni[1];
                $before += strlen($raw); $after += strlen($data);
                @unlink(DATA_DIR . '/imgwm/' . $id);
            } else { @unlink($tmp); }
        }
        $idx[$id]['opt'] = true;
        $done++;
    }
    write_raw('media', json_encode($idx, JSON_UNESCAPED_UNICODE));
    json_out(array('ok' => true, 'done' => $done, 'left' => $left, 'before' => $before, 'after' => $after));
}
fail(400, 'Ismeretlen művelet');
