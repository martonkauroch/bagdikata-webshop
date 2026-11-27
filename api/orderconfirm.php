<?php
define('BK_API', 1);
require __DIR__ . '/_lib.php';
require __DIR__ . '/_mail.php';
ensure_data();
$cfg = load_config();
if (!$cfg || !has_admin($cfg)) { fail(401, 'Admin belépés szükséges'); }
require_post();
$d = body_json(2000);
$id = (is_object($d) && isset($d->id) && is_string($d->id)) ? $d->id : '';
$raw = read_raw('store'); $st = $raw ? json_decode($raw) : null; $o = null;
if (is_object($st) && isset($st->orders) && is_array($st->orders)) { foreach ($st->orders as $x) { if (is_object($x) && isset($x->id) && $x->id === $id) { $o = $x; break; } } }
if (!$o) { fail(404, 'A rendelés nem található a szerveren.'); }
$email = isset($o->customer->email) && is_string($o->customer->email) ? trim($o->customer->email) : '';
if (!mail_valid($email)) { fail(400, 'A rendelésben nincs érvényes e-mail-cím.'); }
if (rl_blocked('reconfirm', 30, 3600)) { fail(429, 'Túl sok újraküldés. Próbáld később.'); }
rl_hit('reconfirm', 3600);
$en = isset($o->locale) && $o->locale === 'en';
$fmt = function ($n) { return number_format((float) $n, 0, ',', ' ') . ' Ft'; };
$rows = array();
foreach ((isset($o->items) && is_array($o->items) ? $o->items : array()) as $it) {
    if (!is_object($it)) { continue; }
    $q = isset($it->quantity) ? (int) $it->quantity : 1;
    $rows[] = array(isset($it->name) ? (string) $it->name : '', (string) $q, $fmt((isset($it->unitPrice) ? $it->unitPrice : 0) * $q));
}
$rows[] = array($en ? 'Shipping and fees' : 'Szállítás és díjak', '', $fmt(isset($o->shippingFee) ? $o->shippingFee : 0));
$rows[] = array($en ? 'Total' : 'Összesen', '', $fmt(isset($o->total) ? $o->total : 0));
$tbl = (object) array('head' => $en ? array('Item', 'Qty', 'Amount') : array('Termék', 'Db', 'Összeg'), 'rows' => $rows);
try {
    mail_confirm_order($email, isset($o->customer->name) ? (string) $o->customer->name : '', $en ? 'en' : 'hu', (string) $o->orderNumber, $tbl);
    mail_log('OK order-visszaigazolas újraküldve (' . $o->orderNumber . ')');
} catch (Throwable $e) {
    mail_log('HIBA order-visszaigazolas újraküldés: ' . mail_clean($e->getMessage(), 200));
    fail(502, 'A levelet nem sikerült elküldeni (lásd mail.log).');
}
json_out(array('ok' => true, 'to' => $email));
