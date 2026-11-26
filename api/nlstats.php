<?php
define('BK_API', 1);
require __DIR__ . '/_lib.php';
require __DIR__ . '/_newsletter.php';
ensure_data();
$cfg = load_config();
if (!$cfg || !has_admin($cfg)) { fail(401, 'Admin belépés szükséges'); }
header('Cache-Control: no-store');
$n = array('confirmed' => 0, 'confirmedEn' => 0, 'pending' => 0, 'unsubscribed' => 0, 'new30' => 0, 'unsub30' => 0);
$t30 = time() - 30 * 86400;
foreach (nl_list() as $s) {
    $st = isset($s['status']) ? $s['status'] : 'pending';
    if (isset($n[$st])) { $n[$st]++; }
    if ($st === 'confirmed' && isset($s['lang']) && $s['lang'] === 'en') { $n['confirmedEn']++; }
    if ($st === 'confirmed' && !empty($s['confirmedAt']) && strtotime($s['confirmedAt']) >= $t30) { $n['new30']++; }
    if ($st === 'unsubscribed' && !empty($s['unsubscribedAt']) && strtotime($s['unsubscribedAt']) >= $t30) { $n['unsub30']++; }
}
$raw = read_raw('nl_stats'); $stats = $raw ? json_decode($raw, true) : array(); if (!is_array($stats)) { $stats = array(); }
$raw = read_raw('nl_campaigns'); $camps = $raw ? json_decode($raw, true) : array(); if (!is_array($camps)) { $camps = array(); }
$out = array();
foreach (array_slice($camps, 0, 20) as $c) {
    if (!isset($c['id'])) { continue; }
    $e = isset($stats[$c['id']]) ? $stats[$c['id']] : array();
    $links = array();
    foreach ((isset($e['l']) && is_array($e['l']) ? $e['l'] : array()) as $u => $k) { $links[] = array('url' => $u, 'n' => (int) $k); }
    $out[] = array('id' => $c['id'], 'subject' => isset($c['subject']) ? $c['subject'] : '', 'createdAt' => isset($c['createdAt']) ? $c['createdAt'] : null,
        'status' => isset($c['status']) ? $c['status'] : '', 'total' => (int) (isset($c['total']) ? $c['total'] : 0), 'sent' => (int) (isset($c['sent']) ? $c['sent'] : 0),
        'sentEn' => isset($c['sentEn']) ? (int) $c['sentEn'] : null, 'failed' => (int) (isset($c['failed']) ? $c['failed'] : 0), 'bilingual' => !empty($c['subjectEn']), 'hasLink' => !empty($c['ctaUrl']),
        'opens' => isset($e['o']) ? (int) $e['o'] : 0, 'uniqueOpens' => isset($e['uo']) ? count($e['uo']) : 0,
        'clicks' => isset($e['c']) ? (int) $e['c'] : 0, 'uniqueClicks' => isset($e['uc']) ? count($e['uc']) : 0,
        'firstOpenAt' => isset($e['fo']) ? $e['fo'] : null, 'lastOpenAt' => isset($e['lo']) ? $e['lo'] : null, 'links' => $links,
        'tracked' => !empty($c['trk']));
}
json_out(array('ok' => true, 'counts' => $n, 'campaigns' => $out));
