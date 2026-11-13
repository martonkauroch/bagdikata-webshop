<?php
// Látogatói események központi számlálása (napi összesítés). Csak a webshop kliens hívja.
define('BK_API', 1);
require __DIR__ . '/_lib.php';
ensure_data();
require_post();
$cfg = load_config();
if (!$cfg) { json_out(array('ok' => false), 503); }
require_site($cfg);
if (has_admin($cfg)) { json_out(array('ok' => true)); }   // az admin böngészését nem számoljuk
if (rl_blocked('track', 900, 3600)) { json_out(array('ok' => true)); }
rl_hit('track', 3600);
$d = body_json(2000);
if (!is_object($d) || !isset($d->type) || !is_string($d->type)) { json_out(array('ok' => false), 400); }
$type = $d->type;
if (!in_array($type, array('pageview', 'impression', 'click', 'view', 'cart', 'share', 'vid'), true)) { json_out(array('ok' => false), 400); }
$pid = (isset($d->productId) && is_string($d->productId) && preg_match('/^[A-Za-z0-9_-]{1,60}$/', $d->productId)) ? $d->productId : '';
$ch = (isset($d->channel) && is_string($d->channel) && in_array($d->channel, array('facebook', 'messenger', 'whatsapp', 'viber', 'telegram', 'x', 'pinterest', 'linkedin', 'email', 'instagram', 'copy', 'native'), true)) ? $d->channel : '';
if ($type !== 'pageview' && $type !== 'vid' && $pid === '') { json_out(array('ok' => false), 400); }
if ($type === 'share' && $ch === '') { json_out(array('ok' => false), 400); }
$newVisit = !empty($d->newVisit);
date_default_timezone_set('Europe/Budapest');
$today = date('Y-m-d');
// Visszatérő látogatók: csak akkor, ha a látogató elfogadta a statisztikai azonosítót (a böngésző küldi a vid-et); az azonosító sózott lenyomata kerül tárolásra
$vid = (isset($d->vid) && is_string($d->vid) && preg_match('/^[a-f0-9]{16,40}$/', $d->vid)) ? $d->vid : '';
if ($vid !== '' && ($type === 'vid' || ($type === 'pageview' && $newVisit))) {
    $vkey = substr(hash_hmac('sha256', $vid, $cfg['secret']), 0, 20);
    $vKeep = date('Y-m-d', strtotime('-200 days'));
    mutate_json('returning', function ($cur) use ($vkey, $today, $vKeep) {
        $v = $cur ? json_decode(json_encode($cur), true) : array();
        if (!is_array($v)) { $v = array(); }
        foreach (array_keys($v) as $k) { if (!isset($v[$k]['l']) || $v[$k]['l'] < $vKeep) { unset($v[$k]); } }
        if (count($v) > 60000) { return $v; }
        if (!isset($v[$vkey])) { $v[$vkey] = array('f' => $today, 'l' => $today, 's' => 0, 'd' => array()); }
        $e = &$v[$vkey];
        if (!in_array($today, $e['d'], true)) { $e['d'][] = $today; $e['d'] = array_slice($e['d'], -150); }
        $e['l'] = $today; $e['s'] = (int) $e['s'] + 1;
        return $v;
    });
}
if ($type === 'vid') { json_out(array('ok' => true)); }
$keepFrom = date('Y-m-d', strtotime('-120 days'));
mutate_json('analytics', function ($cur) use ($type, $pid, $ch, $newVisit, $today, $keepFrom) {
    $a = $cur ? json_decode(json_encode($cur), true) : array();
    if (!is_array($a)) { $a = array(); }
    foreach (array_keys($a) as $k) { if ($k < $keepFrom) { unset($a[$k]); } }
    if (!isset($a[$today]) || !is_array($a[$today])) { $a[$today] = array('date' => $today, 'visitors' => 0, 'pageViews' => 0, 'products' => array()); }
    $day = &$a[$today];
    if ($type === 'pageview') {
        $day['pageViews'] = (isset($day['pageViews']) ? $day['pageViews'] : 0) + 1;
        if ($newVisit) { $day['visitors'] = (isset($day['visitors']) ? $day['visitors'] : 0) + 1; }
    } else {
        if (!isset($day['products']) || !is_array($day['products'])) { $day['products'] = array(); }
        if (!isset($day['products'][$pid])) { $day['products'][$pid] = array('impressions' => 0, 'clicks' => 0, 'views' => 0, 'addToCart' => 0, 'shares' => array('facebook' => 0, 'copy' => 0, 'native' => 0)); }
        $p = &$day['products'][$pid];
        if ($type === 'impression') { $p['impressions']++; }
        if ($type === 'click') { $p['clicks']++; }
        if ($type === 'view') { $p['views']++; }
        if ($type === 'cart') { $p['addToCart']++; }
        if ($type === 'share') { if (!isset($p['shares']) || !is_array($p['shares'])) { $p['shares'] = array(); } $p['shares'][$ch] = (isset($p['shares'][$ch]) ? (int) $p['shares'][$ch] : 0) + 1; }
    }
    return $a;
});
json_out(array('ok' => true));
