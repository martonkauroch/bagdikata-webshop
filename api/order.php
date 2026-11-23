<?php
define('BK_API', 1);
require __DIR__ . '/_lib.php';
require __DIR__ . '/_coupon.php';
require __DIR__ . '/_newsletter.php';
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
$str = function ($o, $k, $max) { return (is_object($o) && isset($o->$k) && is_scalar($o->$k)) ? mb_substr(trim((string) $o->$k), 0, $max, 'UTF-8') : ''; };
$c = isset($o->customer) ? $o->customer : null;
$o->customer = (object) array('name' => $str($c, 'name', 120), 'email' => $str($c, 'email', 200), 'phone' => $str($c, 'phone', 40));
if ($o->customer->name === '' || !filter_var($o->customer->email, FILTER_VALIDATE_EMAIL)) { fail(400, 'Hiányzó vagy hibás név / e-mail-cím.'); }
$ad = isset($o->address) ? $o->address : null;
$o->address = (object) array('zip' => $str($ad, 'zip', 12), 'city' => $str($ad, 'city', 80), 'street' => $str($ad, 'street', 200));

$store = json_decode((string) read_raw('store'));
$byId = array();
if (is_object($store) && isset($store->products) && is_array($store->products)) {
    foreach ($store->products as $p) { if (is_object($p) && isset($p->id) && is_string($p->id)) { $byId[$p->id] = $p; } }
}
$items = array();
$sub = 0;
foreach ($o->items as $it) {
    if (strpos($it->productId, '__') === 0) { continue; }
    $p = isset($byId[$it->productId]) ? $byId[$it->productId] : null;
    if (!$p || (isset($p->active) && $p->active === false) || !isset($p->price) || !is_numeric($p->price) || $p->price < 0) {
        fail(409, 'Egy termék már nem elérhető. Kérjük, ellenőrizd a kosarat.');
    }
    $q = (int) $it->quantity;
    $pname = (isset($p->name) && is_string($p->name)) ? $p->name : 'Termék';
    if (isset($p->stock) && is_numeric($p->stock) && $q > (int) $p->stock) {
        fail(409, $pname . ': csak ' . max(0, (int) $p->stock) . ' db van készleten.');
    }
    $it->unitPrice = (int) round($p->price);
    $it->quantity = $q;
    $it->name = $pname;
    $items[] = $it;
    $sub += $it->unitPrice * $q;
}
if (!count($items)) { fail(400, 'A kosár üres.'); }
$set = json_decode((string) read_raw('settings'));
$sh = (is_object($set) && isset($set->shipping) && is_object($set->shipping)) ? $set->shipping : new stdClass();
$SHIP = array('foxpost' => array('foxpost', 1290, true), 'packeta' => array('packeta', 1390, true), 'gls_haz' => array('gls', 1990, true), 'szemelyes' => array('pickup', 0, false));
$sm = (isset($o->shippingMethod) && is_string($o->shippingMethod)) ? $o->shippingMethod : '';
if (!isset($SHIP[$sm])) { fail(400, 'Érvénytelen szállítási mód.'); }
list($sk, $fee, $codDef) = $SHIP[$sm];
$sc = (isset($sh->$sk) && is_object($sh->$sk)) ? $sh->$sk : new stdClass();
if (isset($sc->enabled) && $sc->enabled === false) { fail(400, 'Ez a szállítási mód jelenleg nem elérhető.'); }
if ($sk === 'gls' && ($o->address->zip === '' || $o->address->city === '' || $o->address->street === '')) { fail(400, 'Házhoz szállításnál a teljes cím megadása kötelező.'); }
if (isset($sc->fee) && is_numeric($sc->fee)) { $fee = max(0, (int) round($sc->fee)); }
$codOk = $sk === 'pickup' ? false : (isset($sc->codEnabled) ? $sc->codEnabled !== false : $codDef);
$pm = (isset($o->paymentMethod) && is_string($o->paymentMethod)) ? $o->paymentMethod : '';
if (!in_array($pm, array('kartya', 'atutalas', 'utanvet'), true)) { fail(400, 'Érvénytelen fizetési mód.'); }
if ($pm === 'utanvet' && (!$codOk || (isset($o->currency) && $o->currency !== 'HUF'))) { fail(400, 'Utánvét ennél a szállítási módnál nem választható.'); }
$free = (isset($sh->freeShippingFrom) && is_numeric($sh->freeShippingFrom)) ? (int) $sh->freeShippingFrom : 20000;
$codFee = (isset($sh->codFee) && is_numeric($sh->codFee)) ? max(0, (int) $sh->codFee) : 490;
$shipFee = ($sub >= $free ? 0 : $fee) + ($pm === 'utanvet' ? $codFee : 0);
$clientTotal = (isset($o->total) && is_numeric($o->total)) ? (int) round($o->total) : null;
rl_hit('order', 3600);
$isNew = false;
mutate_json('store', function ($cur) use ($o, &$isNew, $items, $sub, $shipFee, $clientTotal) {
    if (!is_object($cur)) { $cur = new stdClass(); }
    $orders = (isset($cur->orders) && is_array($cur->orders)) ? $cur->orders : array();
    foreach ($orders as $x) { if (is_object($x) && isset($x->id) && $x->id === $o->id) { return $cur; } }
    $isNew = true;
    $disc = 0;
    if (isset($o->coupon) && is_object($o->coupon)) {
        $code = isset($o->coupon->code) && is_string($o->coupon->code) ? coupon_code_norm($o->coupon->code) : '';
        if ($code === '') { unset($o->coupon); }
        else {
            list($cs, $cpct) = coupon_redeem($code);
            $pct = $cs === 'ok' ? min(90, max(0, (int) $cpct)) : 0;
            $disc = (int) round($sub * $pct / 100);
            $o->coupon = (object) array('code' => $code, 'percent' => $pct, 'discount' => $disc);
            if ($cs !== 'ok') {
                $o->coupon->invalid = true;
                $why = array('unknown' => 'ismeretlen', 'disabled' => 'letiltva', 'exhausted' => 'elfogyott');
                $o->note = trim(((isset($o->note) && is_string($o->note)) ? $o->note . ' ' : '') . '[FIGYELEM: a(z) ' . $code . ' kupon a leadáskor már nem volt érvényes (' . (isset($why[$cs]) ? $why[$cs] : $cs) . '), kedvezmény nélkül rögzítve]');
            }
        }
    }
    $all = $items;
    if ($disc > 0) {
        $all[] = (object) array('productId' => '__coupon', 'name' => 'Kupon: ' . $o->coupon->code . ' (−' . $o->coupon->percent . '%)', 'unitPrice' => -$disc, 'quantity' => 1,
            'vatRate' => isset($items[0]->vatRate) ? $items[0]->vatRate : null);
    }
    $o->items = $all;
    $o->subtotal = $sub - $disc;
    $o->shippingFee = $shipFee;
    $o->total = $sub - $disc + $shipFee;
    if ($clientTotal !== null && abs($clientTotal - $o->total) >= 1) {
        $o->note = trim(((isset($o->note) && is_string($o->note)) ? $o->note . ' ' : '') . '[FIGYELEM: a böngészőből ' . $clientTotal . ' Ft végösszeg érkezett, a szerver az aktuális árakkal ' . $o->total . ' Ft-ot számolt – ez rögzült]');
        $o->priceCorrected = true;
    }
    $o->test = !site_is_public();
    $o->createdAt = date('c');
    if (!isset($o->orderNumber) || !is_string($o->orderNumber) || !preg_match('/^BK-\d{1,12}$/', $o->orderNumber)) { $o->orderNumber = 'BK-' . date('ymdHis'); }
    if (!isset($o->status) || !in_array($o->status, array('uj', 'fizetve'), true)) { $o->status = 'uj'; }
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
if ($isNew && !empty($o->newsletter) && isset($o->customer) && is_object($o->customer) && isset($o->customer->email) && is_string($o->customer->email)) {
    try {
        nl_subscribe($cfg, $o->customer->email, isset($o->customer->name) && is_string($o->customer->name) ? $o->customer->name : '',
            (isset($o->locale) && $o->locale === 'en') ? 'en' : 'hu', 'checkout');
    } catch (Throwable $e) {  }
}
json_out(array('ok' => true));
