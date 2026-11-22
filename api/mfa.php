<?php
define('BK_API', 1);
require __DIR__ . '/_lib.php';
require __DIR__ . '/_mfa.php';
ensure_data();
require_post();
$cfg = load_config();
if (!$cfg) { fail(503, 'A szerver még nincs beállítva.'); }
require_site($cfg);
if (!mfa_has_pre($cfg)) { fail(401, 'A munkamenet lejárt, jelentkezz be újra a jelszóval.'); }
if (rl_blocked('mfa', 6, 900)) { fail(429, 'Túl sok hibás kód. Próbáld újra 15 perc múlva.'); }
$d = body_json(1024);
$action = (is_object($d) && isset($d->action) && is_string($d->action)) ? $d->action : '';
$code = (is_object($d) && isset($d->code) && is_string($d->code)) ? trim($d->code) : '';
$m = mfa_data();

if ($action === 'confirm') {
    if (mfa_enrolled($m)) { fail(409, 'A kétlépcsős azonosítás már be van állítva.'); }
    if (!isset($m['pending']['secret'])) { fail(409, 'A beállítás lejárt, jelentkezz be újra.'); }
    $c = totp_check($m['pending']['secret'], $code);
    if ($c === null) { rl_hit('mfa', 900); usleep(300000); fail(400, 'A kód nem megfelelő. Ellenőrizd, hogy a telefon órája pontos-e.'); }
    $codes = mfa_recovery_codes();
    $m = array(
        'secret' => $m['pending']['secret'], 'enrolledAt' => date('c'), 'lastCounter' => $c,
        'recovery' => array_map(function ($x) { return password_hash(mfa_norm_recovery($x), PASSWORD_DEFAULT); }, $codes),
    );
    mfa_save($m);
    rl_clear('mfa');
    mfa_grant_admin($cfg);
    json_out(array('ok' => true, 'recoveryCodes' => $codes));
}

if ($action === 'verify') {
    if (!mfa_enrolled($m)) { fail(409, 'A kétlépcsős azonosítás nincs beállítva, jelentkezz be újra.'); }
    $used = false;
    $c = totp_check($m['secret'], $code, isset($m['lastCounter']) ? (int) $m['lastCounter'] : -1);
    if ($c !== null) {
        $m['lastCounter'] = $c;
    } else {
        $n = mfa_norm_recovery($code);
        if (strlen($n) === 8 && isset($m['recovery']) && is_array($m['recovery'])) {
            foreach ($m['recovery'] as $i => $h) {
                if (password_verify($n, $h)) { array_splice($m['recovery'], $i, 1); $used = true; break; }
            }
        }
        if (!$used) { rl_hit('mfa', 900); usleep(300000); fail(400, 'Hibás vagy lejárt kód.'); }
    }
    mfa_save($m);
    rl_clear('mfa');
    mfa_grant_admin($cfg);
    json_out(array('ok' => true, 'usedRecoveryCode' => $used, 'recoveryLeft' => isset($m['recovery']) ? count($m['recovery']) : 0));
}

fail(400, 'Ismeretlen művelet');
