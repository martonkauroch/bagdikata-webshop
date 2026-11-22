<?php
define('BK_API', 1);
require __DIR__ . '/_lib.php';
require __DIR__ . '/_backup.php';
ensure_data();
date_default_timezone_set('Europe/Budapest');
$cfg = load_config();
if (!$cfg) { fail(503, 'A szerver még nincs beállítva.'); }
require_admin($cfg);
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['dl'])) {
    $n = (string) $_GET['dl'];
    if (!preg_match('/^(adatok|kepek)-\d{4}-\d{2}-\d{2}(-\d{6})?\.zip$/', $n)) { fail(400, 'Érvénytelen fájlnév'); }
    $f = bk_backup_dir() . '/' . $n;
    if (!is_file($f)) { fail(404, 'A mentés nem található'); }
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="bagdikata-' . $n . '"');
    header('Content-Length: ' . filesize($f));
    header('X-Content-Type-Options: nosniff');
    readfile($f);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post();
    $d = body_json(512);
    if (!is_object($d) || !isset($d->action) || $d->action !== 'run') { fail(400, 'Ismeretlen művelet'); }
    if (rl_blocked('backup', 10, 3600)) { fail(429, 'Túl sok mentés egy órán belül.'); }
    rl_hit('backup', 3600);
    $r = bk_backup_run(true, !empty($d->images));
    if ($r['fail']) { fail(500, implode('; ', $r['lines'])); }
    json_out(array('ok' => true, 'lines' => $r['lines'], 'backups' => bk_backup_list()));
}
$free = function_exists('disk_free_space') ? @disk_free_space(DATA_DIR) : false;
json_out(array('ok' => true, 'backups' => bk_backup_list(), 'zip' => class_exists('ZipArchive'), 'free' => $free === false ? null : $free));
