<?php
define('BK_API', 1);
require __DIR__ . '/_lib.php';
ensure_data();
require_post();
$cfg = load_config();
if (!$cfg) { json_out(array('ok' => false), 503); }
$site = has_site($cfg);
if (has_admin($cfg)) { json_out(array('ok' => true)); }
if ((isset($_SERVER['HTTP_DNT']) && $_SERVER['HTTP_DNT'] === '1') || (isset($_SERVER['HTTP_SEC_GPC']) && $_SERVER['HTTP_SEC_GPC'] === '1')) { json_out(array('ok' => true)); }
$ua = isset($_SERVER['HTTP_USER_AGENT']) ? (string) $_SERVER['HTTP_USER_AGENT'] : '';
if ($ua === '' || preg_match('/bot|crawl|spider|slurp|facebookexternalhit|headless|lighthouse|monitor|curl|wget|python|preview|pingdom|uptime/i', $ua)) { json_out(array('ok' => true)); }
if (rl_blocked('track', 900, 3600)) { json_out(array('ok' => true)); }
rl_hit('track', 3600);
$d = body_json(2000);
if (!is_object($d) || !isset($d->type) || !is_string($d->type)) { json_out(array('ok' => false), 400); }
$type = $d->type;
if (!in_array($type, array('pageview', 'impression', 'click', 'view', 'cart', 'share', 'vid', 'time', 'out'), true)) { json_out(array('ok' => false), 400); }
if (!$site && $type !== 'pageview' && $type !== 'time') { fail(401, 'Nincs belépve (oldaljelszó)'); }
$path = (isset($d->path) && is_string($d->path) && preg_match('#^/[A-Za-z0-9_/-]{0,80}$#', $d->path)) ? $d->path : '';
if (!$site) { $path = '/hamarosan'; }
$pid = (isset($d->productId) && is_string($d->productId) && preg_match('/^[A-Za-z0-9_-]{1,60}$/', $d->productId)) ? $d->productId : '';
$ch = (isset($d->channel) && is_string($d->channel) && in_array($d->channel, array('facebook', 'messenger', 'whatsapp', 'viber', 'telegram', 'x', 'pinterest', 'linkedin', 'email', 'instagram', 'copy', 'native'), true)) ? $d->channel : '';
if ($type === 'time') {
    $ms = isset($d->ms) && is_numeric($d->ms) ? (int) $d->ms : 0;
    if ($path === '' || $ms < 1000) { json_out(array('ok' => true)); }
    $sec = min(1800, (int) round($ms / 1000));
    $first = !empty($d->first);
    date_default_timezone_set('Europe/Budapest');
    $today = date('Y-m-d');
    mutate_json('analytics', function ($cur) use ($today, $path, $sec, $first) {
        $a = $cur ? json_decode(json_encode($cur), true) : array();
        if (!is_array($a)) { $a = array(); }
        if (!isset($a[$today]) || !is_array($a[$today])) { $a[$today] = array('date' => $today, 'visitors' => 0, 'pageViews' => 0, 'products' => array()); }
        $day = &$a[$today];
        if (!isset($day['dur']) || !is_array($day['dur'])) { $day['dur'] = array(); }
        if (!isset($day['dur'][$path]) && count($day['dur']) >= 300) { return $a; }
        $e = isset($day['dur'][$path]) && is_array($day['dur'][$path]) ? $day['dur'][$path] : array('s' => 0, 'n' => 0);
        $e['s'] = (int) $e['s'] + $sec;
        if ($first) { $e['n'] = (int) $e['n'] + 1; }
        $day['dur'][$path] = $e;
        return $a;
    });
    json_out(array('ok' => true));
}
if ($type === 'out') {
    $key = (isset($d->key) && is_string($d->key) && preg_match('/^[a-z0-9]{1,16}$/', $d->key)) ? $d->key : '';
    if ($key === '') { json_out(array('ok' => false), 400); }
    $nm = isset($d->name) && is_string($d->name) ? mb_substr(trim(preg_replace('/[\x00-\x1f<>]/u', '', $d->name)), 0, 60) : '';
    date_default_timezone_set('Europe/Budapest');
    $today = date('Y-m-d');
    mutate_json('analytics', function ($cur) use ($today, $key) {
        $a = $cur ? json_decode(json_encode($cur), true) : array();
        if (!is_array($a)) { $a = array(); }
        if (!isset($a[$today]) || !is_array($a[$today])) { $a[$today] = array('date' => $today, 'visitors' => 0, 'pageViews' => 0, 'products' => array()); }
        $day = &$a[$today];
        if (!isset($day['out']) || !is_array($day['out'])) { $day['out'] = array(); }
        if (!isset($day['out'][$key]) && count($day['out']) >= 50) { return $a; }
        $day['out'][$key] = (isset($day['out'][$key]) ? (int) $day['out'][$key] : 0) + 1;
        return $a;
    });
    live_push('out', array_filter(array('k' => $key, 'n' => $nm, 'p' => $path)));
    json_out(array('ok' => true));
}
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
mutate_json('analytics', function ($cur) use ($type, $pid, $ch, $newVisit, $isNewUnique, $today, $keepFrom, $path) {
    $a = $cur ? json_decode(json_encode($cur), true) : array();
    if (!is_array($a)) { $a = array(); }
    foreach (array_keys($a) as $k) { if ($k < $keepFrom) { unset($a[$k]); } }
    if (!isset($a[$today]) || !is_array($a[$today])) { $a[$today] = array('date' => $today, 'visitors' => 0, 'pageViews' => 0, 'products' => array()); }
    $day = &$a[$today];
    if ($type === 'pageview') {
        $day['pageViews'] = (isset($day['pageViews']) ? $day['pageViews'] : 0) + 1;
        if ($isNewUnique) { $day['visitors'] = (isset($day['visitors']) ? $day['visitors'] : 0) + 1; }
        if ($newVisit) { $day['visits'] = (isset($day['visits']) ? $day['visits'] : 0) + 1; }
        if ($path !== '') {
            if (!isset($day['pages']) || !is_array($day['pages'])) { $day['pages'] = array(); }
            if (isset($day['pages'][$path]) || count($day['pages']) < 300) { $day['pages'][$path] = (isset($day['pages'][$path]) ? (int) $day['pages'][$path] : 0) + 1; }
        }
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
if ($type === 'pageview') { live_push('pageview', array('p' => $path, 'nv' => $newVisit ? 1 : 0)); }
elseif ($type !== 'impression') { live_push($type, array_filter(array('pid' => $pid, 'ch' => $ch))); }
if ($type === 'cart') {
    try {
        require_once __DIR__ . '/_push.php';
        if (push_wants('cart')) {
            require_once __DIR__ . '/_seo.php';
            $pp = seo_find_product($pid); $pn = $pp && isset($pp->name) ? (string) $pp->name : $pid;
            push_notify('cart', 'Kosárba tétel', $pn, $pn, '/#/admin/statisztika', 'cart-' . $pid);
        }
    } catch (Throwable $e) { }
}
json_out(array('ok' => true));
