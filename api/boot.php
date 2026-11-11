<?php
define('BK_API', 1);
require __DIR__ . '/_lib.php';
ensure_data();
$cfg = load_config();
$out = array('shared' => true, 'configured' => (bool) $cfg, 'site' => false, 'public' => false, 'admin' => false, 'turnstile' => bot_site_key(), 'adminEmail' => '', 'data' => new stdClass());
if ($cfg && has_site($cfg)) {
    $out['site'] = true;
    $out['public'] = site_is_public();
    $admin = has_admin($cfg);
    $out['admin'] = $admin;
    if ($admin) { $out['adminEmail'] = $cfg['admin_email']; }
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
