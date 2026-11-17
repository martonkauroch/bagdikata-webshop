<?php
// Kétlépcsős admin belépés (TOTP – Google/Microsoft Authenticator, 1Password …), a szerveren ellenőrizve.
// Adat: api/data/mfa.json  { secret, enrolledAt, lastCounter, recovery:[hash…], pending:{secret, created} }
// VÉSZKIJÁRAT: ha elveszett a telefon ÉS a helyreállító kódok is, a cPanel File Managerben töröld az
// api/data/mfa.json fájlt – a következő (jelszavas) belépéskor újra be kell olvasni egy QR-kódot.
if (!defined('BK_API')) { http_response_code(403); exit; }

const MFA_PRE_TTL = 600;          // jelszó után ennyi mp áll rendelkezésre a kód megadására
const MFA_ISSUER = 'Bagdi Kata Admin';

function mfa_data() {
    $raw = read_raw('mfa');
    $d = $raw ? json_decode($raw, true) : null;
    return is_array($d) ? $d : array();
}
function mfa_save($d) { write_raw('mfa', json_encode($d, JSON_UNESCAPED_SLASHES)); }
function mfa_enrolled($d = null) { $d = $d === null ? mfa_data() : $d; return isset($d['secret']) && is_string($d['secret']) && $d['secret'] !== ''; }

function b32_encode($bin) {
    $a = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; $bits = ''; $out = '';
    foreach (str_split($bin) as $c) { $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT); }
    foreach (str_split($bits, 5) as $chunk) { $out .= $a[bindec(str_pad($chunk, 5, '0'))]; }
    return $out;
}
function b32_decode($s) {
    $a = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; $bits = ''; $out = '';
    foreach (str_split(strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $s))) as $c) { $bits .= str_pad(decbin(strpos($a, $c)), 5, '0', STR_PAD_LEFT); }
    foreach (str_split($bits, 8) as $byte) { if (strlen($byte) === 8) { $out .= chr(bindec($byte)); } }
    return $out;
}
function totp_at($secret, $counter) {
    $h = hash_hmac('sha1', pack('J', $counter), b32_decode($secret), true);
    $o = ord($h[19]) & 0x0f;
    $n = ((ord($h[$o]) & 0x7f) << 24) | (ord($h[$o + 1]) << 16) | (ord($h[$o + 2]) << 8) | ord($h[$o + 3]);
    return str_pad((string) ($n % 1000000), 6, '0', STR_PAD_LEFT);
}
// visszaadja az elfogadott időablak számát, vagy null-t (±30 mp tűrés, egy kód csak egyszer használható)
function totp_check($secret, $code, $last = -1) {
    $code = preg_replace('/\D/', '', (string) $code);
    if (strlen($code) !== 6) { return null; }
    $now = (int) floor(time() / 30);
    foreach (array($now, $now - 1, $now + 1) as $c) {
        if ($c > $last && hash_equals(totp_at($secret, $c), $code)) { return $c; }
    }
    return null;
}
function mfa_uri($secret, $email) {
    return 'otpauth://totp/' . rawurlencode(MFA_ISSUER . ':' . $email) . '?secret=' . $secret . '&issuer=' . rawurlencode(MFA_ISSUER) . '&algorithm=SHA1&digits=6&period=30';
}
function mfa_recovery_codes() {
    $a = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; $codes = array();
    for ($i = 0; $i < 8; $i++) {
        $s = ''; for ($j = 0; $j < 8; $j++) { $s .= $a[random_int(0, strlen($a) - 1)]; }
        $codes[] = substr($s, 0, 4) . '-' . substr($s, 4);
    }
    return $codes;
}
function mfa_norm_recovery($c) { return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $c)); }

// jelszóval már igazolt, de kódra váró állapot (rövid életű süti)
function mfa_has_pre($cfg) { return $cfg && isset($_COOKIE['bk_pre']) && check_token($cfg, 'pre', $_COOKIE['bk_pre']); }
function mfa_start_pre($cfg) {
    put_cookie('bk_pre', make_token($cfg, 'pre', MFA_PRE_TTL), MFA_PRE_TTL);
    $d = mfa_data();
    if (!mfa_enrolled($d)) {
        // új titkos kulcs a beállításhoz (1 órán belül ugyanazt adjuk vissza, hogy a QR-kód ne változzon)
        if (!isset($d['pending']['secret'], $d['pending']['created']) || $d['pending']['created'] < time() - 3600) {
            $d['pending'] = array('secret' => b32_encode(random_bytes(20)), 'created' => time());
            mfa_save($d);
        }
        return 'enroll';
    }
    return 'challenge';
}
// boot.php-nak: a belépési képernyő melyik lépést mutassa
function mfa_boot_state($cfg) {
    if (!mfa_has_pre($cfg)) { return null; }
    $d = mfa_data();
    if (mfa_enrolled($d)) { return array('stage' => 'challenge'); }
    if (!isset($d['pending']['secret'])) { return null; }
    return array('stage' => 'enroll', 'secret' => $d['pending']['secret'], 'uri' => mfa_uri($d['pending']['secret'], $cfg['admin_email']));
}
function mfa_grant_admin($cfg) {
    put_cookie('bk_pre', '', 0);
    put_cookie('bk_admin', make_token($cfg, 'admin', 8 * 3600), 8 * 3600);
}
