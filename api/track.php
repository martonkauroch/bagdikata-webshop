<?php
define('BK_API', 1);
require __DIR__ . '/_lib.php';
ensure_data();
require_post();
$cfg = load_config();
if (!$cfg) { json_out(array('ok' => false), 503); }
require_site($cfg);
if (has_admin($cfg)) { json_out(array('ok' => true)); }
if ((isset($_SERVER['HTTP_DNT']) && $_SERVER['HTTP_DNT'] === '1') || (isset($_SERVER['HTTP_SEC_GPC']) && $_SERVER['HTTP_SEC_GPC'] === '1')) { json_out(array('ok' => true)); }
$ua = isset($_SERVER['HTTP_USER_AGENT']) ? (string) $_SERVER['HTTP_USER_AGENT'] : '';
if ($ua === '' || preg_match('/bot|crawl|spider|slurp|facebookexternalhit|headless|lighthouse|monitor|curl|wget|python|preview|pingdom|uptime/i', $ua)) { json_out(array('ok' => true)); }
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
$isNewUnique = false; $isNewVisit = false;
if ($type === 'pageview') {
    $ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '';
    $hk = substr(hash_hmac('sha256', $today . '|' . $ip . '|' . $ua, $cfg['secret'] . '|daily'), 0, 16);
    $now = time();
    mutate_json('visitors_today', function ($cur) use ($hk, $today, $now, &$isNewUnique, &$isNewVisit) {
        $v = $cur ? json_decode(json_encode($cur), true) : array();
        if (!is_array($v) || !isset($v['date']) || $v['date'] !== $today) { $v = array('date' => $today, 'h' => array()); }
        if (count($v['h']) > 50000) { return $v; }
        if (!isset($v['h'][$hk])) { $isNewUnique = true; $isNewVisit = true; }
        elseif ($now - (int) $v['h'][$hk] > 1800) { $isNewVisit = true; }
        $v['h'][$hk] = $now;
        return $v;
    });
}
$newVisit = $isNewVisit;
$keepFrom = date('Y-m-d', strtotime('-120 days'));
mutate_json('analytics', function ($cur) use ($type, $pid, $ch, $newVisit, $isNewUnique, $today, $keepFrom) {
    $a = $cur ? json_decode(json_encode($cur), true) : array();
    if (!is_array($a)) { $a = array(); }
    foreach (array_keys($a) as $k) { if ($k < $keepFrom) { unset($a[$k]); } }
    if (!isset($a[$today]) || !is_array($a[$today])) { $a[$today] = array('date' => $today, 'visitors' => 0, 'pageViews' => 0, 'products' => array()); }
    $day = &$a[$today];
    if ($type === 'pageview') {
        $day['pageViews'] = (isset($day['pageViews']) ? $day['pageViews'] : 0) + 1;
        if ($isNewUnique) { $day['visitors'] = (isset($day['visitors']) ? $day['visitors'] : 0) + 1; }
        if ($newVisit) { $day['visits'] = (isset($day['visits']) ? $day['visits'] : 0) + 1; }
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
