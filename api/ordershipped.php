<?php
define('BK_API', 1);
require __DIR__ . '/_lib.php';
require __DIR__ . '/_mail.php';
ensure_data();
$cfg = load_config();
if (!$cfg || !has_admin($cfg)) { fail(401, 'Admin belépés szükséges'); }
require_post();
$d = body_json(3000);
$id = (is_object($d) && isset($d->id) && is_string($d->id)) ? $d->id : '';
$tracking = (is_object($d) && isset($d->tracking) && is_string($d->tracking)) ? trim(preg_replace('/[^A-Za-z0-9 ._\/-]/', '', $d->tracking)) : '';
$tracking = mb_substr($tracking, 0, 60);
$force = is_object($d) && !empty($d->force);
if (!mail_shipped_enabled()) { json_out(array('ok' => true, 'sent' => false, 'reason' => 'off')); }
$raw = read_raw('store'); $st = $raw ? json_decode($raw) : null; $o = null;
if (is_object($st) && isset($st->orders) && is_array($st->orders)) { foreach ($st->orders as $x) { if (is_object($x) && isset($x->id) && $x->id === $id) { $o = $x; break; } } }
if (!$o || !isset($o->orderNumber)) { fail(404, 'A rendelés nem található a szerveren.'); }
$num = (string) $o->orderNumber;
if (!$force && !mail_order_claim($num, 's')) { json_out(array('ok' => true, 'sent' => false, 'reason' => 'already')); }
if (rl_blocked('shipmail', 60, 3600)) { mail_order_unclaim($num, 's'); fail(429, 'Túl sok értesítő egy órán belül. Próbáld később.'); }
rl_hit('shipmail', 3600);
try {
    $to = mail_confirm_shipped($o, $tracking);
    mail_log('OK feladási értesítő (' . $num . ')' . ($tracking !== '' ? ' csomagszámmal' : '') . ' -> vásárló');
} catch (Throwable $e) {
    if (!$force) { mail_order_unclaim($num, 's'); }
    mail_log('HIBA feladási értesítő (' . $num . '): ' . mail_clean($e->getMessage(), 200));
    fail(502, 'Az értesítőt nem sikerült elküldeni: ' . mail_clean($e->getMessage(), 160));
}
json_out(array('ok' => true, 'sent' => true, 'to' => $to));
