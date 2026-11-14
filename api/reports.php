<?php
define('BK_API', 1);
require __DIR__ . '/_lib.php';
require __DIR__ . '/_mail.php';
require __DIR__ . '/_report.php';
ensure_data();
date_default_timezone_set('Europe/Budapest');
require_post();
$cfg = load_config();
require_admin($cfg);
$d = body_json(2000);
$act = (is_object($d) && isset($d->action) && is_string($d->action)) ? $d->action : 'status';
if ($act === 'status') {
    $last = bk_daily_last(); $out = array();
    foreach (bk_daily_reports() as $r) { $out[$r['id']] = isset($last[$r['id']]) ? $last[$r['id']] : null; }
    $key = bk_cron_key($cfg);
    json_out(array('ok' => true, 'last' => (object) $out, 'cronUrl' => mail_site_url() . '/api/cron_daily.php?key=' . $key));
}
if ($act === 'send') {
    if (rl_blocked('report_send', 20, 3600)) { fail(429, 'Túl sok küldés. Próbáld később.'); }
    rl_hit('report_send', 3600);
    $id = (isset($d->id) && is_string($d->id)) ? $d->id : '';
    foreach (bk_daily_reports() as $r) {
        if ($r['id'] !== $id) { continue; }
        $item = build_daily_report(date('Y-m-d', strtotime('yesterday')), $r['sections'], array($r['email']));
        $item->subject = 'Napi jelentés (teszt) – ' . preg_replace('/^Napi jelentés – /', '', $item->subject);
        $n = mail_dispatch($cfg, array($item), true);
        if ($n < 1) { fail(502, 'A levelet nem sikerült elküldeni (lásd api/data/mail.log).'); }
        json_out(array('ok' => true, 'to' => $r['email']));
    }
    fail(404, 'Nincs ilyen jelentés (előbb mentsd el a beállításokat).');
}
fail(400, 'Ismeretlen művelet.');
