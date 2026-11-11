<?php
// Napi jelentés összeállítása a szerveren (ugyanaz a tartalom, mint az admin felületen).
// Szükséges: _lib.php, _mail.php betöltve.
function rep_money($n) { return number_format((float) $n, 0, ',', "\xC2\xA0") . "\xC2\xA0Ft"; }
function rep_add_days($d, $n) { $t = new DateTime($d . ' 00:00:00', new DateTimeZone('UTC')); $t->modify(($n >= 0 ? '+' : '') . $n . ' day'); return $t->format('Y-m-d'); }
function rep_diff_days($a, $b) { return (int) round((strtotime($b . ' UTC') - strtotime($a . ' UTC')) / 86400); }
function rep_label($d) {
    $m = array('január', 'február', 'március', 'április', 'május', 'június', 'július', 'augusztus', 'szeptember', 'október', 'november', 'december');
    $w = array('vasárnap', 'hétfő', 'kedd', 'szerda', 'csütörtök', 'péntek', 'szombat');
    $t = strtotime($d . ' 12:00:00 UTC');
    return gmdate('Y', $t) . '. ' . $m[(int) gmdate('n', $t) - 1] . ' ' . (int) gmdate('j', $t) . '., ' . $w[(int) gmdate('w', $t)];
}
function rep_share_split($sh) {
    $lab = array('facebook' => 'Facebook', 'messenger' => 'Messenger', 'whatsapp' => 'WhatsApp', 'viber' => 'Viber', 'telegram' => 'Telegram', 'x' => 'X', 'pinterest' => 'Pinterest', 'linkedin' => 'LinkedIn', 'email' => 'e-mail', 'instagram' => 'Instagram', 'copy' => 'link', 'native' => 'telefon');
    arsort($sh); $parts = array();
    foreach ($sh as $k => $n) { if ($n > 0) { $parts[] = (isset($lab[$k]) ? $lab[$k] : $k) . ' ' . $n; } }
    return count($parts) ? ' (' . implode(', ', $parts) . ')' : '';
}
function rep_range($days, $from, $to) {
    $r = array('visitors' => 0, 'pageViews' => 0, 'impressions' => 0, 'clicks' => 0, 'views' => 0, 'addToCart' => 0, 'shares' => array('facebook' => 0, 'copy' => 0, 'native' => 0), 'products' => array());
    for ($d = $from; $d <= $to; $d = rep_add_days($d, 1)) {
        if (!isset($days[$d]) || !is_array($days[$d])) { continue; }
        $x = $days[$d];
        $r['visitors'] += isset($x['visitors']) ? (int) $x['visitors'] : 0;
        $r['pageViews'] += isset($x['pageViews']) ? (int) $x['pageViews'] : 0;
        if (isset($x['products']) && is_array($x['products'])) {
            foreach ($x['products'] as $id => $p) {
                if (!isset($r['products'][$id])) { $r['products'][$id] = array('impressions' => 0, 'clicks' => 0, 'views' => 0, 'addToCart' => 0, 'shares' => array('facebook' => 0, 'copy' => 0, 'native' => 0)); }
                foreach (array('impressions', 'clicks', 'views', 'addToCart') as $k) { $r['products'][$id][$k] += isset($p[$k]) ? (int) $p[$k] : 0; }
                if (isset($p['shares']) && is_array($p['shares'])) { foreach ($p['shares'] as $k => $n) { $r['products'][$id]['shares'][$k] = (isset($r['products'][$id]['shares'][$k]) ? $r['products'][$id]['shares'][$k] : 0) + (int) $n; } }
            }
        }
    }
    foreach ($r['products'] as $p) {
        foreach (array('impressions', 'clicks', 'views', 'addToCart') as $k) { $r[$k] += $p[$k]; }
        foreach ($p['shares'] as $k => $n) { $r['shares'][$k] = (isset($r['shares'][$k]) ? $r['shares'][$k] : 0) + $n; }
    }
    return $r;
}
function rep_delta($e, $t) {
    if (!$t) { return $e ? ' (új)' : ''; }
    $n = (int) round(($e - $t) / $t * 100);
    return $n === 0 ? ' (változatlan)' : ' (' . ($n > 0 ? '+' : '') . $n . '% az előző naphoz képest)';
}
function rep_pct($e, $t) { return $t ? (round($e / $t * 1000) / 10) . '%' : '–'; }
// "Népszerűek elöl" rangsor pontszámai
function rep_scores($days, $products, $rank, $today) {
    $s = array();
    foreach ($products as $p) { $s[$p['id']] = 0.0; }
    for ($t = 0; $t < $rank['windowDays']; $t++) {
        $d = rep_add_days($today, -$t);
        if (!isset($days[$d]['products']) || !is_array($days[$d]['products'])) { continue; }
        $o = pow(0.5, $t / max(1, $rank['halfLifeDays']));
        foreach ($days[$d]['products'] as $id => $p) {
            if (array_key_exists($id, $s)) { $s[$id] += $o * ((isset($p['views']) ? $p['views'] : 0) * $rank['viewWeight'] + (isset($p['clicks']) ? $p['clicks'] : 0) * $rank['clickWeight']); }
        }
    }
    if (!empty($rank['newcomerBoost'])) {
        $v = array_values($s); sort($v);
        $med = count($v) ? $v[(int) floor(count($v) / 2)] : 0;
        foreach ($products as $p) {
            $cd = isset($p['createdAt']) ? substr((string) $p['createdAt'], 0, 10) : '';
            if ($cd !== '' && rep_diff_days($cd, $today) < 14) { $s[$p['id']] = max($s[$p['id']], $med); }
        }
    }
    return $s;
}
function rep_link($label, $path) { return (object) array('label' => $label, 'appPath' => $path, 'href' => mail_site_url() . $path); }

// A napi jelentés levélleírója ($date = a jelentett nap, pl. tegnap). Címzettet a mail_dispatch tölti.
function build_daily_report($date, $sections, $to) {
    $today = date('Y-m-d');
    $raw = read_raw('analytics'); $days = $raw !== null ? json_decode($raw, true) : array();
    if (!is_array($days)) { $days = array(); }
    $st = read_raw('store'); $store = $st !== null ? json_decode($st, true) : array();
    $products = (is_array($store) && isset($store['products']) && is_array($store['products'])) ? $store['products'] : array();
    $orders = (is_array($store) && isset($store['orders']) && is_array($store['orders'])) ? $store['orders'] : array();
    $se = read_raw('settings'); $set = $se !== null ? json_decode($se, true) : array();
    $rk = (is_array($set) && isset($set['ranking']) && is_array($set['ranking'])) ? $set['ranking'] : array();
    $rank = array_merge(array('viewWeight' => 1, 'clickWeight' => 3, 'halfLifeDays' => 7, 'windowDays' => 30, 'newcomerBoost' => true), $rk);
    $names = array(); foreach ($products as $p) { $names[$p['id']] = isset($p['name']) ? $p['name'] : $p['id']; }
    $nm = function ($id) use ($names) { return isset($names[$id]) ? $names[$id] : $id; };
    $s = rep_range($days, $date, $date);
    $c = rep_range($days, rep_add_days($date, -1), rep_add_days($date, -1));
    $tz = new DateTimeZone('Europe/Budapest');
    $dayOrders = array(); $sum = 0;
    foreach ($orders as $o) {
        if (!is_array($o) || !isset($o['createdAt']) || (isset($o['status']) && $o['status'] === 'torolve')) { continue; }
        try { $od = (new DateTime($o['createdAt']))->setTimezone($tz)->format('Y-m-d'); } catch (Throwable $e) { continue; }
        if ($od === $date) { $dayOrders[] = $o; $sum += isset($o['total']) ? $o['total'] : 0; }
    }
    $f = array();
    if (!empty($sections['traffic'])) {
        $sh = $s['shares'];
        $f[] = (object) array('heading' => 'Forgalom', 'lines' => array(
            'Látogatók: ' . $s['visitors'] . rep_delta($s['visitors'], $c['visitors']),
            'Oldalmegtekintések: ' . $s['pageViews'] . rep_delta($s['pageViews'], $c['pageViews']),
            'Termékoldal-megtekintések: ' . $s['views'] . ' · kattintások a listában: ' . $s['clicks'] . ' (átkattintás: ' . rep_pct($s['clicks'], $s['impressions']) . ')',
            'Kosárba tétel: ' . $s['addToCart'] . ' · megosztás: ' . array_sum($sh) . rep_share_split($sh)));
    }
    if (!empty($sections['topProducts'])) {
        $exist = array(); foreach ($products as $p) { $exist[$p['id']] = true; }
        $rows = array();
        foreach ($s['products'] as $id => $p) { if (isset($exist[$id])) { $rows[] = array($id, $p); } }
        usort($rows, function ($a, $b) { return ($b[1]['views'] + $b[1]['clicks'] * 3) - ($a[1]['views'] + $a[1]['clicks'] * 3); });
        $rows = array_slice($rows, 0, 5);
        $tbl = array();
        foreach ($rows as $r) { $tbl[] = array($nm($r[0]), (string) $r[1]['views'], (string) $r[1]['clicks'], (string) $r[1]['addToCart']); }
        $sc = rep_scores($days, $products, $rank, $today);
        $top = '';
        if (count($sc)) { $ids = array_keys($sc); usort($ids, function ($a, $b) use ($sc) { return $sc[$b] <=> $sc[$a]; }); $top = $nm($ids[0]); }
        $f[] = (object) array('heading' => 'Legjobban teljesítő termékek',
            'table' => (object) array('head' => array('Termék', 'Megtekintés', 'Kattintás', 'Kosárba'), 'rows' => $tbl),
            'lines' => array('A boltban a „Népszerűek elöl” sorrend első helyén most: ' . $top . '.'));
    }
    if (!empty($sections['orders'])) {
        $links = array();
        foreach (array_slice($dayOrders, 0, 20) as $o) {
            $links[] = rep_link((isset($o['orderNumber']) ? $o['orderNumber'] : '') . ' – ' . (isset($o['customer']['name']) ? $o['customer']['name'] : '') . ', ' . rep_money(isset($o['total']) ? $o['total'] : 0), '/admin/rendelesek?rendeles=' . rawurlencode($o['id']));
        }
        $f[] = (object) array('heading' => 'Rendelések',
            'lines' => count($dayOrders) ? array(count($dayOrders) . ' rendelés, összesen ' . rep_money($sum)) : array('Ezen a napon nem érkezett rendelés.'),
            'links' => $links);
    }
    if (!empty($sections['lowStock'])) {
        $low = array_values(array_filter($products, function ($p) { return isset($p['stock']) && $p['stock'] <= 5; }));
        usort($low, function ($a, $b) { return $a['stock'] <=> $b['stock']; });
        $links = array();
        foreach (array_slice($low, 0, 20) as $p) { $links[] = rep_link($p['name'] . ' – ' . $p['stock'] . ' db', '/admin/termekek?termek=' . rawurlencode($p['id'])); }
        $f[] = (object) array('heading' => 'Alacsony készlet', 'lines' => count($low) ? array() : array('Minden termékből van elég.'), 'links' => $links);
    }
    $f[] = (object) array('links' => array(rep_link('Részletes statisztika az adminban', '/admin/statisztika')));
    return (object) array(
        'kind' => 'daily', 'to' => $to,
        'subject' => 'Napi jelentés – ' . rep_label($date) . ' · ' . $s['visitors'] . ' látogató · ' . count($dayOrders) . ' rendelés',
        'preheader' => $s['views'] . ' termékmegtekintés, ' . $s['clicks'] . ' kattintás, ' . rep_money($sum) . ' bevétel',
        'blocks' => $f);
}
function bk_cron_key($cfg) { return substr(hash_hmac('sha256', 'cron-daily', (string) $cfg['secret']), 0, 32); }
