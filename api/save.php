<?php
define('BK_API', 1);
require __DIR__ . '/_lib.php';
ensure_data();
require_post();
$cfg = load_config();
require_admin($cfg);
$key = isset($_GET['key']) ? (string) $_GET['key'] : '';
if (!in_array($key, shared_keys(), true)) { fail(400, 'Ismeretlen kulcs'); }
$raw = body_raw(MAX_SAVE_BYTES);
$val = json_decode($raw);
if ($val === null) { fail(400, 'Érvénytelen JSON'); }
if ($key === 'store') {
    if (!is_object($val)) { fail(400, 'Érvénytelen adat'); }
    mutate_json('store', function ($cur) use ($val) {
        $server = (is_object($cur) && isset($cur->orders) && is_array($cur->orders)) ? $cur->orders : array();
        $client = (isset($val->orders) && is_array($val->orders)) ? $val->orders : array();
        $val->orders = union_by_id($server, $client, 2000);
        return $val;
    });
} elseif ($key === 'outbox' || $key === 'withdrawals') {
    if (!is_array($val)) { fail(400, 'Érvénytelen adat'); }
    $cap = ($key === 'outbox') ? 200 : 1000;
    $added = array();
    mutate_json($key, function ($cur) use ($val, $cap, $key, &$added) {
        $cur = is_array($cur) ? $cur : array();
        if ($key === 'outbox') {
            $have = array();
            foreach ($cur as $o) { if (is_object($o) && isset($o->id)) { $have[$o->id] = true; } }
            foreach ($val as $o) { if (is_object($o) && isset($o->id) && is_string($o->id) && !isset($have[$o->id])) { $added[] = $o; } }
        }
        return union_by_id($cur, $val, $cap);
    });
    // Az admin felületről indított statisztikai levelek (napi jelentés, teszt) itt mennek ki
    if ($key === 'outbox' && count($added)) {
        $send = array();
        foreach (array_slice($added, 0, 10) as $it) {
            $k = (isset($it->kind) && is_string($it->kind)) ? $it->kind : '';
            if ($k === 'daily' || $k === 'test') { $send[] = $it; }
        }
        if (count($send)) { require __DIR__ . '/_mail.php'; mail_dispatch($cfg, $send, true); }
    }
} elseif ($key === 'audit') {
    if (!is_array($val)) { fail(400, 'Érvénytelen adat'); }
    mutate_json('audit', function ($cur) use ($val) {
        return union_by_id(is_array($cur) ? $cur : array(), $val, 2000);
    });
} else {
    write_raw($key, $raw);
    if ($key === 'settings') {
        // oldal állapota: nyilvános / jelszóval védett (jelzőfájl a szerver és az .htaccess számára)
        $flag = DATA_DIR . '/public.flag';
        $pub = (is_object($val) && isset($val->access) && is_object($val->access) && isset($val->access->public) && $val->access->public === true);
        if ($pub) { @file_put_contents($flag, date('c')); } elseif (is_file($flag)) { @unlink($flag); }
    }
}
json_out(array('ok' => true));
