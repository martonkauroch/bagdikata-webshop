<?php
define('BK_API', 1);
require __DIR__ . '/_lib.php';
require __DIR__ . '/_mail.php';
require __DIR__ . '/_report.php';
require __DIR__ . '/_backup.php';
require __DIR__ . '/_newsletter.php';
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
$r = bk_daily_run($cfg, $force, 0);
foreach ($r['lines'] as $l) { echo $l . "\n"; }
$b = bk_backup_run(false, false);
foreach ($b['lines'] as $l) { echo $l . "\n"; }
try { $nr = nl_remind_run($cfg); if ($nr) { echo 'hírlevél-emlékeztető: ' . $nr . " elküldve\n"; } } catch (Throwable $e) { }
if ($r['fail'] || $b['fail']) { exit(2); }
