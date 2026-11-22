<?php
// Automatikus biztonsági mentés az api/data mappáról → api/data/backups/ (kívülről nem elérhető).
//  - naponta:  adatok-ÉÉÉÉ-HH-NN.zip  (minden .json + a szerverbeállítások), 14 db marad meg
//  - hetente:  kepek-ÉÉÉÉ-HH-NN.zip   (feltöltött képek: api/data/img), 4 db marad meg
// Indítja: cron_daily.php (cPanel cron) és tartalékként az oldalbetöltés (bk_daily_tick), naponta egyszer.
// VISSZAÁLLÍTÁS: cPanel File Manager → api/data/backups → a kívánt zip → Extract az api/data mappába
// (a meglévő fájlok felülírásával). A képek zipjét is az api/data mappába kell kibontani (img/ almappát tartalmaz).
if (!defined('BK_API')) { http_response_code(403); exit; }

const BK_BACKUP_KEEP_DATA = 14;
const BK_BACKUP_KEEP_IMG = 4;
const BK_BACKUP_IMG_DAYS = 7;

function bk_backup_dir() {
    $d = DATA_DIR . '/backups';
    if (!is_dir($d)) { @mkdir($d, 0755, true); }
    if (!is_file($d . '/.htaccess')) { @file_put_contents($d . '/.htaccess', "Require all denied\n<IfModule !mod_authz_core.c>\nOrder allow,deny\nDeny from all\n</IfModule>\n"); }
    if (!is_file($d . '/index.html')) { @file_put_contents($d . '/index.html', ''); }
    return $d;
}
function bk_backup_list() {
    $out = array();
    foreach (glob(bk_backup_dir() . '/*.zip') ?: array() as $f) {
        $n = basename($f);
        if (!preg_match('/^(adatok|kepek)-\d{4}-\d{2}-\d{2}(-\d{6})?\.zip$/', $n, $m)) { continue; }
        $out[] = array('name' => $n, 'kind' => $m[1], 'size' => filesize($f), 'at' => date('c', filemtime($f)));
    }
    usort($out, function ($a, $b) { return strcmp($b['at'], $a['at']); });
    return $out;
}
function bk_backup_prune($kind, $keep) {
    $l = array_values(array_filter(bk_backup_list(), function ($x) use ($kind) { return $x['kind'] === $kind; }));
    foreach (array_slice($l, $keep) as $x) { @unlink(bk_backup_dir() . '/' . $x['name']); }
}
// a mentendő adatfájlok (gyökérszint): .json, .php beállítások, public.flag – a számlálók, zárak, naplók nélkül
function bk_backup_data_files() {
    $files = array();
    foreach (scandir(DATA_DIR) ?: array() as $n) {
        $f = DATA_DIR . '/' . $n;
        if (!is_file($f) || $n[0] === '.') { continue; }
        if (preg_match('/^rl_|\.lock$|\.log$|\.tmp/', $n)) { continue; }
        if (!preg_match('/\.(json|php)$/', $n) && $n !== 'public.flag') { continue; }
        $files[] = $n;
    }
    return $files;
}
function bk_backup_zip($kind, $suffix = '') {
    if (!class_exists('ZipArchive')) { return array(false, 'a szerveren nincs ZIP-támogatás (ZipArchive)'); }
    @set_time_limit(300);
    $dir = bk_backup_dir();
    $name = $kind . '-' . date('Y-m-d') . $suffix . '.zip';
    $tmp = $dir . '/.' . $name . '.tmp' . getmypid();
    $z = new ZipArchive();
    if ($z->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) { return array(false, 'a mentés nem hozható létre'); }
    $n = 0;
    if ($kind === 'adatok') {
        foreach (bk_backup_data_files() as $f) { $z->addFile(DATA_DIR . '/' . $f, $f); $n++; }
    } else {
        $img = DATA_DIR . '/img';
        foreach (is_dir($img) ? (scandir($img) ?: array()) : array() as $f) {
            if (!is_file($img . '/' . $f) || $f[0] === '.') { continue; }
            $z->addFile($img . '/' . $f, 'img/' . $f);
            if (method_exists($z, 'setCompressionName')) { $z->setCompressionName('img/' . $f, ZipArchive::CM_STORE); } // a képek már tömörítettek
            $n++;
        }
    }
    if ($n === 0) { $z->close(); @unlink($tmp); return array(true, 'nincs mit menteni (még nincs feltöltött kép)'); }
    $z->setArchiveComment('Bagdi Kata webshop – ' . $kind . ' – ' . date('c'));
    if (!$z->close() || !is_file($tmp)) { @unlink($tmp); return array(false, 'a mentés írása nem sikerült (tárhely megtelt?)'); }
    if (!@rename($tmp, $dir . '/' . $name)) { @unlink($tmp); return array(false, 'a mentés nem nevezhető át'); }
    return array(true, $name . ' (' . $n . ' fájl, ' . round(filesize($dir . '/' . $name) / 1024) . ' KB)');
}
// $force: azonnali mentés (admin gomb) – ilyenkor időbélyeg kerül a névbe, hogy a napi ne íródjon felül
function bk_backup_run($force = false, $withImages = false) {
    $lines = array(); $fail = 0;
    $lock = DATA_DIR . '/backup.lock';
    if (is_file($lock) && time() - filemtime($lock) < 600) { return array('lines' => array('mentés: épp folyamatban'), 'fail' => 0); }
    @touch($lock);
    try {
        if (function_exists('rl_cleanup')) { $c = rl_cleanup(); if ($c) { $lines[] = 'régi próbálkozás-számlálók törölve: ' . $c; } }
        $list = bk_backup_list();
        $today = date('Y-m-d');
        $hasToday = false; $lastImg = 0;
        foreach ($list as $x) {
            if ($x['kind'] === 'adatok' && strpos($x['name'], 'adatok-' . $today) === 0) { $hasToday = true; }
            if ($x['kind'] === 'kepek') { $lastImg = max($lastImg, strtotime($x['at'])); }
        }
        $suffix = $force ? '-' . date('His') : '';
        if ($force || !$hasToday) {
            list($ok, $msg) = bk_backup_zip('adatok', $suffix);
            $lines[] = 'adatmentés: ' . ($ok ? 'kész – ' : 'HIBA – ') . $msg; if (!$ok) { $fail++; }
            bk_backup_prune('adatok', BK_BACKUP_KEEP_DATA);
        } else { $lines[] = 'adatmentés: ma már elkészült'; }
        if ($withImages || time() - $lastImg > BK_BACKUP_IMG_DAYS * 86400) {
            list($ok, $msg) = bk_backup_zip('kepek', $suffix);
            $lines[] = 'képmentés: ' . ($ok ? 'kész – ' : 'HIBA – ') . $msg; if (!$ok) { $fail++; }
            bk_backup_prune('kepek', BK_BACKUP_KEEP_IMG);
        }
    } catch (Throwable $e) { $lines[] = 'mentés: HIBA – ' . $e->getMessage(); $fail++; }
    @unlink($lock);
    if ($fail && function_exists('mail_log')) { mail_log('biztonsági mentés: ' . implode('; ', $lines)); }
    return array('lines' => $lines, 'fail' => $fail);
}
