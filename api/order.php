<?php
define('BK_API', 1);
require __DIR__ . '/_lib.php';
ensure_data();
require_post();
$cfg = load_config();
require_site($cfg);
if (rl_blocked('order', 30, 3600)) { fail(429, 'Túl sok rendelés ebből a címről. Próbáld később.'); }
$d = body_json(60000);
$o = (is_object($d) && isset($d->order) && is_object($d->order)) ? $d->order : null;
if (!$o || !isset($o->id) || !is_string($o->id) || strlen($o->id) > 80 || !isset($o->items) || !is_array($o->items) || count($o->items) < 1 || count($o->items) > 60) { fail(400, 'Érvénytelen rendelés'); }
foreach ($o->items as $it) {
    if (!is_object($it) || !isset($it->productId) || !is_string($it->productId) || !isset($it->quantity) || !is_numeric($it->quantity) || $it->quantity < 1 || $it->quantity > 999) { fail(400, 'Érvénytelen tétel'); }
}
if (!bot_verify(isset($d->captcha) ? $d->captcha : '')) { fail(400, 'A robotellenőrzés nem sikerült.'); }
rl_hit('order', 3600);
mutate_json('store', function ($cur) use ($o) {
    if (!is_object($cur)) { $cur = new stdClass(); }
    $orders = (isset($cur->orders) && is_array($cur->orders)) ? $cur->orders : array();
    foreach ($orders as $x) { if (is_object($x) && isset($x->id) && $x->id === $o->id) { return $cur; } }
    array_unshift($orders, $o);
    $cur->orders = array_slice($orders, 0, 2000);
    if (isset($cur->products) && is_array($cur->products)) {
        foreach ($o->items as $it) {
            foreach ($cur->products as $p) {
                if (is_object($p) && isset($p->id) && $p->id === $it->productId && isset($p->stock) && is_numeric($p->stock)) {
                    $p->stock = max(0, (int) $p->stock - (int) $it->quantity);
                }
            }
        }
    }
    return $cur;
});
json_out(array('ok' => true));
