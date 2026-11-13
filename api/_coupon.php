<?php
// Kuponkezelés segédfüggvényei (api/data/coupons.json). Közvetlenül nem hívható.
if (!defined('BK_API')) { exit; }

function coupon_code_norm($c) {
    $c = strtoupper(trim((string) $c));
    return preg_match('/^[A-Z0-9][A-Z0-9_-]{2,23}$/', $c) ? $c : '';
}
function coupon_list() {
    $raw = read_raw('coupons');
    $a = $raw !== null ? json_decode($raw, true) : array();
    return is_array($a) ? array_values($a) : array();
}
// 'ok' | 'unknown' | 'disabled' | 'exhausted'
function coupon_state($c) {
    if (!is_array($c)) { return 'unknown'; }
    if (!empty($c['disabled'])) { return 'disabled'; }
    $max = isset($c['max']) ? (int) $c['max'] : 0;
    if ($max > 0 && (int) (isset($c['used']) ? $c['used'] : 0) >= $max) { return 'exhausted'; }
    return 'ok';
}
function coupon_find($code) {
    $code = coupon_code_norm($code);
    if ($code === '') { return null; }
    foreach (coupon_list() as $c) { if (isset($c['code']) && $c['code'] === $code) { return $c; } }
    return null;
}
// Ellenőrzés beváltás nélkül -> array(állapot, kupon)
function coupon_check($code) {
    $c = coupon_find($code);
    return array($c ? coupon_state($c) : 'unknown', $c);
}
// Beváltás (számláló növelése) zárolva, hogy az utolsó darabot ne válthassa be két vásárló egyszerre.
// Visszatérés: array(állapot, százalék)
function coupon_redeem($code) {
    $code = coupon_code_norm($code);
    $res = array('unknown', 0);
    if ($code === '') { return $res; }
    mutate_json('coupons', function ($cur) use ($code, &$res) {
        $a = is_array($cur) ? json_decode(json_encode($cur), true) : array();
        if (!is_array($a)) { $a = array(); }
        foreach ($a as $i => $c) {
            if (!isset($c['code']) || $c['code'] !== $code) { continue; }
            $st = coupon_state($c);
            $res = array($st, (int) (isset($c['percent']) ? $c['percent'] : 0));
            if ($st === 'ok') {
                $a[$i]['used'] = (int) (isset($c['used']) ? $c['used'] : 0) + 1;
                $a[$i]['lastUsedAt'] = date('c');
            }
            break;
        }
        return array_values($a);
    });
    return $res;
}
