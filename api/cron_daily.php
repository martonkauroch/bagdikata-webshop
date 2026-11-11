<?php
// Napi jelentés időzített küldése. A cPanel „Cron Jobs" hívja (lásd api/mailtest.php: kész parancsok).
// Működés: a Beállítások > Értesítések > Napi jelentés időpontja után, naponta egyszer elküldi az előző napi jelentést.
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
$st = mail_settings_obj();
$dr = ($st && isset($st->notifications->dailyReport) && is_object($st->notifications->dailyReport)) ? $st->notifications->dailyReport : null;
if (!$dr || empty($dr->enabled)) { echo "a napi jelentés ki van kapcsolva (Statisztika > Napi jelentés)\n"; exit; }
$time = (isset($dr->time) && is_string($dr->time) && preg_match('/^(\d{1,2}):(\d{2})$/', $dr->time, $m)) ? array((int) $m[1], (int) $m[2]) : array(7, 0);
$today = date('Y-m-d');
$due = mktime($time[0], $time[1], 0);
$lastFile = DATA_DIR . '/daily_last.json';
$last = is_file($lastFile) ? json_decode((string) file_get_contents($lastFile), true) : null;
if (!$force) {
    if (time() < $due) { echo "még nincs itt az ideje (" . sprintf('%02d:%02d', $time[0], $time[1]) . ")\n"; exit; }
    if (is_array($last) && isset($last['date']) && $last['date'] === $today) { echo "ma már elment\n"; exit; }
}
$sections = array('traffic' => true, 'topProducts' => true, 'orders' => true, 'lowStock' => true);
if (isset($dr->sections) && is_object($dr->sections)) { foreach ($sections as $k => $v) { if (isset($dr->sections->$k)) { $sections[$k] = !empty($dr->sections->$k); } } }
$date = date('Y-m-d', strtotime('yesterday'));
$to = array();
if (isset($dr->recipients) && is_array($dr->recipients)) { foreach ($dr->recipients as $r) { if (mail_valid($r)) { $to[] = $r; } } }
$item = build_daily_report($date, $sections, $to);
$n = mail_dispatch($cfg, array($item), true);
if ($n > 0) {
    if (!$force) { @file_put_contents($lastFile, json_encode(array('date' => $today, 'sentAt' => date('c')))); }
    echo "elküldve: " . $item->subject . "\n";
} else {
    echo "NEM sikerült elküldeni, lásd api/data/mail.log\n";
    exit(2);
}
