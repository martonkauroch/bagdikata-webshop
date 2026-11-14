<?php
define('BK_API', 1);
require __DIR__ . '/_lib.php';
require __DIR__ . '/_mail.php';
require __DIR__ . '/_report.php';
ensure_data();
date_default_timezone_set('Europe/Budapest');
$cli = (PHP_SAPI === 'cli');
if (!$cli) { header('Content-Type: text/plain; charset=utf-8'); header('Cache-Control: no-store'); }
$cfg = load_config();
if (!$cfg) { echo "nincs beállítva\n"; exit(1); }
if (!$cli) {
    $key = isset($_GET['key']) ? (string) $_GET['key'] : '';
    if (!hash_equals(bk_cron_key($cfg), $key)) { http_response_code(403); echo "tiltott\n"; exit; }
}
$force = !$cli && isset($_GET['force']);
$reports = bk_daily_reports();
$active = array_values(array_filter($reports, function ($r) { return $r['enabled']; }));
if (!$active) { echo "nincs bekapcsolt napi jelentés (Statisztika > Napi jelentések)\n"; exit; }
$today = date('Y-m-d');
$date = date('Y-m-d', strtotime('yesterday'));
$last = bk_daily_last();
$fail = 0;
foreach ($active as $r) {
    $tm = array(7, 0);
    if (preg_match('/^(\d{1,2}):(\d{2})$/', $r['time'], $m)) { $tm = array((int) $m[1], (int) $m[2]); }
    if (!$force) {
        if (time() < mktime($tm[0], $tm[1], 0)) { echo $r['email'] . ": még nincs itt az ideje (" . sprintf('%02d:%02d', $tm[0], $tm[1]) . ")\n"; continue; }
        if (isset($last[$r['id']]['date']) && $last[$r['id']]['date'] === $today && !empty($last[$r['id']]['ok'])) { echo $r['email'] . ": ma már elment\n"; continue; }
    }
    $item = build_daily_report($date, $r['sections'], array($r['email']));
    $n = mail_dispatch($cfg, array($item), true);
    if ($n > 0) { if (!$force) { bk_daily_last_set($r['id'], $today, true); } echo $r['email'] . ": elküldve – " . $item->subject . "\n"; }
    else { bk_daily_last_set($r['id'], $today, false); $fail++; echo $r['email'] . ": NEM sikerült elküldeni, lásd api/data/mail.log\n"; }
}
if ($fail) { exit(2); }
