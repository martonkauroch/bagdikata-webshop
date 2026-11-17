<?php
define('BK_API', 1);
require __DIR__ . '/_lib.php';
ensure_data();
require_once __DIR__ . '/_mail.php';
require_once __DIR__ . '/_report.php';
require_once __DIR__ . '/_mfa.php';
date_default_timezone_set('Europe/Budapest');
bk_daily_tick();
$cfg = load_config();
$landing = landing_public();
$out = array('shared' => true, 'configured' => (bool) $cfg, 'site' => false, 'public' => false, 'admin' => false, 'turnstile' => bot_site_key(), 'adminEmail' => '', 'landing' => $landing, 'data' => new stdClass());
if ($cfg && has_site($cfg)) {
    $out['site'] = true;
    $out['public'] = site_is_public();
    $admin = has_admin($cfg);
    $out['admin'] = $admin;
    if ($admin) { $out['adminEmail'] = $cfg['admin_email']; }
    elseif ($mfa = mfa_boot_state($cfg)) { $out['mfa'] = $mfa; $out['adminEmail'] = $cfg['admin_email']; }
    $data = array();
    foreach (shared_keys() as $k) {
        $raw = read_raw($k);
        if ($raw === null) { continue; }
        $v = json_decode($raw);
        if ($k === 'settings' && !$admin) { $v = redact_settings($v); }
        if (!$admin && ($k === 'audit')) { continue; }
        if (!$admin && $k === 'store' && is_object($v)) { $v->orders = array(); $v->invoices = array(); }
        if (!$admin && ($k === 'outbox' || $k === 'withdrawals')) { $v = array(); }
        $data[$k] = $v;
    }
    if (count($data)) { $out['data'] = (object) $data; }
}
json_out($out);
