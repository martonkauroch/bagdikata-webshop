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
        $out[] = array('id' => $id, 'name' => isset($m['name']) ? $m['name'] : '', 'w' => isset($m['w']) ? $m['w'] : 0, 'h' => isset($m['h']) ? $m['h'] : 0, 'size' => isset($m['size']) ? $m['size'] : 0, 'added' => isset($m['added']) ? $m['added'] : '', 'used' => strpos($use, 'srv:' . $id) !== false || (isset($m['src']) && strpos($use, $m['src']) !== false), 'credit' => isset($m['credit']) ? $m['credit'] : null);
    }
    $cand = 0; $savable = 0;
    foreach ($idx as $id => $m) { $m['_id'] = $id; if (media_needs_opt($m)) { $cand++; $savable += isset($m['size']) ? (int) $m['size'] : 0; } }
    usort($out, function ($a, $b) { return strcmp($b['added'], $a['added']); });
    json_out(array('ok' => true, 'items' => $out, 'toOptimize' => $cand, 'toOptimizeBytes' => $savable));
}
if ($act === 'dupes') {
    @set_time_limit(60);
    $idx = media_sync();
    $dir = DATA_DIR . '/img';
    $t0 = microtime(true); $pending = 0; $changed = false;
    foreach ($idx as $id => $m) {
        if (!empty($m['sig'])) { continue; }
        if (microtime(true) - $t0 > 15) { $pending++; continue; }
        $sg = media_sig_of($dir . '/' . $id, $id);
        $idx[$id]['sig'] = $sg ? $sg['sig'] : 'x'; $idx[$id]['ar'] = $sg ? $sg['ar'] : 0; $changed = true;
    }
    if ($changed) {
        mutate_json('media', function ($cur) use ($idx) {
            $a = is_object($cur) ? json_decode(json_encode($cur), true) : array();
            if (!is_array($a)) { $a = array(); }
            foreach ($idx as $id => $m) { if (isset($a[$id]) && isset($m['sig'])) { $a[$id]['sig'] = $m['sig']; $a[$id]['ar'] = $m['ar']; } }
            return $a;
        });
    }
    if ($pending > 0) { json_out(array('ok' => true, 'pending' => $pending, 'groups' => array())); }
    $use = media_usage_text();
    $ids = array_keys($idx); $n = count($ids); $par = array();
    foreach ($ids as $id) { $par[$id] = $id; }
    $find = function ($x) use (&$par) { while ($par[$x] !== $x) { $par[$x] = $par[$par[$x]]; $x = $par[$x]; } return $x; };
    for ($i = 0; $i < $n; $i++) { for ($j = $i + 1; $j < $n; $j++) {
        if (media_is_dupe($idx[$ids[$i]], $idx[$ids[$j]])) { $a = $find($ids[$i]); $b = $find($ids[$j]); if ($a !== $b) { $par[$b] = $a; } }
    } }
    $grp = array();
    foreach ($ids as $id) { $grp[$find($id)][] = $id; }
    $out = array();
    foreach ($grp as $g) {
        if (count($g) < 2) { continue; }
        usort($g, function ($x, $y) use ($idx, $use) {
            $a = media_keep_rank($idx[$x], strpos($use, 'srv:' . $x) !== false); $b = media_keep_rank($idx[$y], strpos($use, 'srv:' . $y) !== false);
            return $a == $b ? 0 : ($a < $b ? 1 : -1);
        });
        $row = array();
        foreach ($g as $id) { $m = $idx[$id]; $row[] = array('id' => $id, 'name' => isset($m['name']) ? $m['name'] : '', 'w' => $m['w'], 'h' => $m['h'], 'size' => $m['size'], 'used' => strpos($use, 'srv:' . $id) !== false); }
        $out[] = array('keep' => $row[0], 'drop' => array_slice($row, 1));
    }
    json_out(array('ok' => true, 'pending' => 0, 'groups' => $out));
}
if ($act === 'dedupe') {
    @set_time_limit(60);
    $groups = (is_object($d) && isset($d->groups) && is_array($d->groups)) ? $d->groups : array();
    $idx = media_index(); $map = array(); $drop = array();
    foreach ($groups as $g) {
        $keep = (is_object($g) && isset($g->keep) && is_string($g->keep)) ? $g->keep : '';
        if (!isset($idx[$keep]) || !is_file(DATA_DIR . '/img/' . $keep)) { continue; }
        $list = (isset($g->drop) && is_array($g->drop)) ? $g->drop : array();
        foreach ($list as $id) {
            if (!is_string($id) || $id === $keep || !isset($idx[$id]) || isset($drop[$id]) || isset($drop[$keep])) { continue; }
            if (!media_is_dupe($idx[$keep], $idx[$id])) { continue; }
            $map['srv:' . $id] = 'srv:' . $keep; $drop[$id] = $keep;
        }
    }
    if (!$drop) { fail(400, 'Nincs törölhető duplikált kép.'); }
    $replaced = 0;
    foreach (array('store', 'content', 'settings') as $nm) {
        if (read_raw($nm) === null) { continue; }
        mutate_json($nm, function ($cur) use ($map, &$replaced) {
            $j = json_encode($cur, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            foreach ($map as $from => $to) { $replaced += substr_count($j, $from); }
            $new = json_decode(strtr($j, $map));
            return $new === null ? $cur : $new;
        });
    }
    foreach ($drop as $id => $keep) {
        @unlink(DATA_DIR . '/img/' . $id); @unlink(DATA_DIR . '/imgwm/' . $id);
        foreach (array(160, 320, 640) as $tw) { @unlink(DATA_DIR . '/imgthumb/' . $tw . '_' . $id . '.jpg'); }
    }
    mutate_json('media', function ($cur) use ($drop) {
        $a = is_object($cur) ? json_decode(json_encode($cur), true) : array();
        if (!is_array($a)) { $a = array(); }
        foreach ($drop as $id => $keep) {
            if (!isset($a[$id])) { continue; }
            if (isset($a[$keep])) {
                if ((!isset($a[$keep]['name']) || $a[$keep]['name'] === '') && !empty($a[$id]['name'])) { $a[$keep]['name'] = $a[$id]['name']; }
                if (empty($a[$keep]['credit']) && !empty($a[$id]['credit'])) { $a[$keep]['credit'] = $a[$id]['credit']; }
            }
            unset($a[$id]);
        }
        return $a;
    });
    json_out(array('ok' => true, 'removed' => count($drop), 'replaced' => $replaced));
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
if ($act === 'credit') {
    $id = (is_object($d) && isset($d->id) && is_string($d->id)) ? $d->id : '';
    $idx = media_index();
    if (!isset($idx[$id])) { fail(404, 'Nincs ilyen kép'); }
    $c = array();
    foreach (array('pn', 'pl', 'mn', 'ml') as $k) {
        $v = (isset($d->$k) && is_string($d->$k)) ? trim($d->$k) : '';
        if ($k === 'pl' || $k === 'ml') { $v = media_clean_link($v); if ($v === null) { fail(400, 'A hivatkozás legyen http(s):// webcím, @felhasználónév vagy instagram.com/… cím.'); } }
        else { $v = mb_substr($v, 0, 80); }
        if ($v !== '') { $c[$k] = $v; }
    }
    if ($c) { $idx[$id]['credit'] = $c; } else { unset($idx[$id]['credit']); }
    write_raw('media', json_encode($idx, JSON_UNESCAPED_UNICODE));
    json_out(array('ok' => true, 'credit' => $c ? $c : null));
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
