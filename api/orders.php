<?php
define('BK_API', 1);
require __DIR__ . '/_lib.php';
ensure_data();
require_post();
$cfg = load_config();
require_admin($cfg);
$d = body_json(65536);
$act = (is_object($d) && isset($d->action) && is_string($d->action)) ? $d->action : '';
$ids = array();
if (is_object($d) && isset($d->ids) && is_array($d->ids)) { foreach ($d->ids as $i) { if (is_string($i) && strlen($i) <= 80) { $ids[$i] = true; } } }
$all = is_object($d) && isset($d->all) && $d->all === true;
if ($act !== 'mark' && $act !== 'delete') { fail(400, 'Ismeretlen művelet'); }
if (!$all && !$ids) { fail(400, 'Nincs kijelölt rendelés'); }
$flag = ($act === 'mark') ? (!isset($d->test) || $d->test !== false) : true;
$n = 0; $restored = 0;
mutate_json('store', function ($cur) use ($act, $ids, $all, $flag, &$n, &$restored) {
    if (!is_object($cur) || !isset($cur->orders) || !is_array($cur->orders)) { return $cur; }
    $keep = array();
    foreach ($cur->orders as $o) {
        if (!is_object($o) || !isset($o->id)) { $keep[] = $o; continue; }
        $hit = $all || isset($ids[$o->id]);
        if ($act === 'mark') {
            if ($hit) { $o->test = $flag; $n++; }
            $keep[] = $o; continue;
        }
        if ($hit && !empty($o->test)) {
            $n++;
            $cancelled = isset($o->status) && $o->status === 'torolve';
            if (!$cancelled && isset($o->items) && is_array($o->items) && isset($cur->products) && is_array($cur->products)) {
                foreach ($o->items as $it) {
                    if (!is_object($it) || !isset($it->productId, $it->quantity)) { continue; }
                    foreach ($cur->products as $p) {
                        if (is_object($p) && isset($p->id) && $p->id === $it->productId && isset($p->stock) && is_numeric($p->stock)) { $p->stock = (int) $p->stock + (int) $it->quantity; $restored++; }
                    }
                }
            }
            continue;
        }
        $keep[] = $o;
    }
    $cur->orders = $keep;
    return $cur;
});
json_out(array('ok' => true, 'count' => $n, 'restored' => $restored));
