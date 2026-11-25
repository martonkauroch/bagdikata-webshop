<?php
define('BK_API', 1);
require __DIR__ . '/_lib.php';
ensure_data();
date_default_timezone_set('Europe/Budapest');
$cfg = load_config();
if (!$cfg || !has_admin($cfg)) { fail(401, 'Nincs jogosultság'); }
header('Cache-Control: no-store');
$now = time();
$today = date('Y-m-d');
$lv = json_decode((string) read_raw('live'), true);
if (!is_array($lv)) { $lv = array(); }
$vt = json_decode((string) read_raw('visitors_today'), true);
$a5 = 0; $a30 = 0;
if (is_array($vt) && isset($vt['date'], $vt['h']) && $vt['date'] === $today && is_array($vt['h'])) {
    foreach ($vt['h'] as $ts) { if ($ts > $now - 300) { $a5++; } if ($ts > $now - 1800) { $a30++; } }
}
$an = json_decode((string) read_raw('analytics'), true);
$d = (is_array($an) && isset($an[$today]) && is_array($an[$today])) ? $an[$today] : array();
$t = array('visitors' => (int) (isset($d['visitors']) ? $d['visitors'] : 0), 'visits' => (int) (isset($d['visits']) ? $d['visits'] : 0), 'pageViews' => (int) (isset($d['pageViews']) ? $d['pageViews'] : 0), 'views' => 0, 'clicks' => 0, 'cart' => 0, 'shares' => 0, 'nl' => 0, 'orders' => 0, 'msgs' => 0, 'landing' => (int) (isset($d['pages']['/hamarosan']) ? $d['pages']['/hamarosan'] : 0));
foreach ((isset($d['products']) && is_array($d['products'])) ? $d['products'] : array() as $p) {
    $t['views'] += (int) (isset($p['views']) ? $p['views'] : 0);
    $t['clicks'] += (int) (isset($p['clicks']) ? $p['clicks'] : 0);
    $t['cart'] += (int) (isset($p['addToCart']) ? $p['addToCart'] : 0);
    if (isset($p['shares']) && is_array($p['shares'])) { $t['shares'] += array_sum(array_map('intval', $p['shares'])); }
}
$midnight = strtotime('today');
foreach ((isset($lv['ev']) && is_array($lv['ev'])) ? $lv['ev'] : array() as $e) {
    if (!isset($e['t']) || $e['t'] < $midnight) { continue; }
    if ($e['y'] === 'nl') { $t['nl']++; } elseif ($e['y'] === 'order') { $t['orders']++; } elseif ($e['y'] === 'msg') { $t['msgs']++; }
}
$min = array(); $cur = intdiv($now, 60); $m = (isset($lv['m']) && is_array($lv['m'])) ? $lv['m'] : array();
for ($i = 29; $i >= 0; $i--) { $k = (string) ($cur - $i); $min[] = array('t' => ($cur - $i) * 60, 'n' => isset($m[$k]) ? (int) $m[$k] : 0); }
$ev = array_reverse(array_slice((isset($lv['ev']) && is_array($lv['ev'])) ? $lv['ev'] : array(), -30));
json_out(array('ok' => true, 'now' => $now, 'active5' => $a5, 'active30' => $a30, 'today' => $t, 'minutes' => $min, 'events' => $ev));
