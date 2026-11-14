<?php
define('BK_API', 1);
require __DIR__ . '/_lib.php';
require __DIR__ . '/_coupon.php';
ensure_data();
require_post();
$cfg = load_config();
require_admin($cfg);
$d = body_json(4000);
$act = (is_object($d) && isset($d->action) && is_string($d->action)) ? $d->action : '';
$code = (is_object($d) && isset($d->code) && is_string($d->code)) ? coupon_code_norm($d->code) : '';
function cp_int($d, $k, $min, $max, $def) {
    if (!isset($d->$k) || !is_numeric($d->$k)) { return $def; }
    return max($min, min($max, (int) $d->$k));
}
function cp_note($d) { return (isset($d->note) && is_string($d->note)) ? mb_substr(trim(preg_replace('/[\r\n\t]+/u', ' ', $d->note)), 0, 120, 'UTF-8') : ''; }

if ($act === 'list') { json_out(array('ok' => true, 'coupons' => coupon_list())); }

if ($act === 'create') {
    if ($code === '') { fail(400, 'A kód 3–24 karakter lehet: betű (ékezet nélkül), szám, kötőjel vagy alsó vonás.'); }
    $pct = cp_int($d, 'percent', 0, 100, 0);
    if ($pct < 1 || $pct > 90) { fail(400, 'A kedvezmény 1 és 90 % között lehet.'); }
    $max = cp_int($d, 'max', 0, 100000, 0);
    $err = null;
    mutate_json('coupons', function ($cur) use ($code, $pct, $max, $d, &$err) {
        $a = is_array($cur) ? json_decode(json_encode($cur), true) : array();
        if (!is_array($a)) { $a = array(); }
        foreach ($a as $c) { if (isset($c['code']) && $c['code'] === $code) { $err = 'Ilyen kuponkód már létezik.'; return array_values($a); } }
        if (count($a) >= 500) { $err = 'Legfeljebb 500 kupon tárolható. Töröld a feleslegeseket.'; return array_values($a); }
        array_unshift($a, array('code' => $code, 'percent' => $pct, 'max' => $max, 'used' => 0, 'disabled' => false, 'note' => cp_note($d), 'createdAt' => date('c')));
        return array_values($a);
    });
    if ($err) { fail(409, $err); }
    json_out(array('ok' => true, 'coupons' => coupon_list()));
}

if ($code === '') { fail(400, 'Hiányzó kuponkód'); }
$found = false;
if ($act === 'update' || $act === 'toggle') {
    mutate_json('coupons', function ($cur) use ($act, $code, $d, &$found) {
        $a = is_array($cur) ? json_decode(json_encode($cur), true) : array();
        if (!is_array($a)) { $a = array(); }
        foreach ($a as $i => $c) {
            if (!isset($c['code']) || $c['code'] !== $code) { continue; }
            $found = true;
            if ($act === 'toggle') { $a[$i]['disabled'] = !empty($d->disabled); }
            else {
                if (isset($d->max)) { $a[$i]['max'] = cp_int($d, 'max', 0, 100000, (int) $c['max']); }
                if (isset($d->percent)) { $p = cp_int($d, 'percent', 1, 90, (int) $c['percent']); $a[$i]['percent'] = $p; }
                if (isset($d->note)) { $a[$i]['note'] = cp_note($d); }
            }
        }
        return array_values($a);
    });
    if (!$found) { fail(404, 'A kupon nem található'); }
    json_out(array('ok' => true, 'coupons' => coupon_list()));
}
if ($act === 'delete') {
    mutate_json('coupons', function ($cur) use ($code, &$found) {
        $a = is_array($cur) ? json_decode(json_encode($cur), true) : array();
        if (!is_array($a)) { $a = array(); }
        $out = array();
        foreach ($a as $c) { if (isset($c['code']) && $c['code'] === $code) { $found = true; continue; } $out[] = $c; }
        return $out;
    });
    if (!$found) { fail(404, 'A kupon nem található'); }
    json_out(array('ok' => true, 'coupons' => coupon_list()));
}
fail(400, 'Ismeretlen művelet');
