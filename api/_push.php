<?php
if (!defined('BK_API')) { exit; }

function push_b64u($s) { return rtrim(strtr(base64_encode($s), '+/', '-_'), '='); }
function push_b64d($s) { $s = strtr((string) $s, '-_', '+/'); $p = strlen($s) % 4; if ($p) { $s .= str_repeat('=', 4 - $p); } return base64_decode($s, true); }

function push_pem_from_point($pt) {
    $der = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200') . $pt;
    return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
}
function push_ec_new() {
    $k = openssl_pkey_new(array('curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC));
    if (!$k) { throw new Exception('EC kulcs nem hozható létre'); }
    $d = openssl_pkey_get_details($k);
    $pt = "\x04" . str_pad($d['ec']['x'], 32, "\0", STR_PAD_LEFT) . str_pad($d['ec']['y'], 32, "\0", STR_PAD_LEFT);
    return array($k, $pt);
}

function push_vapid() {
    $raw = read_raw('push');
    $v = $raw ? json_decode($raw, true) : null;
    if (is_array($v) && !empty($v['pem']) && !empty($v['pub'])) { return $v; }
    list($k, $pt) = push_ec_new();
    $pem = '';
    openssl_pkey_export($k, $pem);
    $v = array('pub' => push_b64u($pt), 'pem' => $pem, 'createdAt' => date('c'));
    write_raw('push', json_encode($v));
    @chmod(DATA_DIR . '/push.json', 0600);
    return $v;
}

function push_der_to_raw($der) {
    $o = 2; if (ord($der[1]) & 0x80) { $o += ord($der[1]) & 0x7f; }
    $out = '';
    for ($i = 0; $i < 2; $i++) {
        $len = ord($der[$o + 1]); $v = substr($der, $o + 2, $len); $o += 2 + $len;
        $v = ltrim($v, "\0"); $out .= str_pad($v, 32, "\0", STR_PAD_LEFT);
    }
    return $out;
}

function push_jwt($aud, $v) {
    $h = push_b64u(json_encode(array('typ' => 'JWT', 'alg' => 'ES256')));
    $p = push_b64u(json_encode(array('aud' => $aud, 'exp' => time() + 12 * 3600, 'sub' => 'https://bagdikata.hu')));
    $sig = '';
    if (!openssl_sign($h . '.' . $p, $sig, $v['pem'], OPENSSL_ALGO_SHA256)) { throw new Exception('Aláírási hiba'); }
    return $h . '.' . $p . '.' . push_b64u(push_der_to_raw($sig));
}

function push_hkdf($salt, $ikm, $info, $len) {
    $prk = hash_hmac('sha256', $ikm, $salt, true);
    return substr(hash_hmac('sha256', $info . "\x01", $prk, true), 0, $len);
}

function push_encrypt($payload, $p256dh, $auth) {
    $ua = push_b64d($p256dh); $as = push_b64d($auth);
    if ($ua === false || strlen($ua) !== 65 || $as === false || strlen($as) < 16) { throw new Exception('Hibás eszközkulcs'); }
    list($k, $pt) = push_ec_new();
    $secret = openssl_pkey_derive(push_pem_from_point($ua), $k, 32);
    if ($secret === false) { throw new Exception('Kulcscsere hiba'); }
    $ikm = push_hkdf($as, $secret, "WebPush: info\0" . $ua . $pt, 32);
    $salt = random_bytes(16);
    $cek = push_hkdf($salt, $ikm, "Content-Encoding: aes128gcm\0", 16);
    $nonce = push_hkdf($salt, $ikm, "Content-Encoding: nonce\0", 12);
    $tag = '';
    $ct = openssl_encrypt($payload . "\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag);
    if ($ct === false) { throw new Exception('Titkosítási hiba'); }
    return $salt . pack('N', 4096) . chr(65) . $pt . $ct . $tag;
}

function push_endpoint_ok($u) {
    if (!is_string($u) || strlen($u) > 1000 || !preg_match('#^https://([a-z0-9.-]+)/#i', $u, $m)) { return false; }
    $h = strtolower($m[1]);
    foreach (array('fcm.googleapis.com', 'android.googleapis.com', 'updates.push.services.mozilla.com', 'web.push.apple.com', '.notify.windows.com', '.push.apple.com', '.push.services.mozilla.com') as $ok) {
        if ($h === $ok || ($ok[0] === '.' && substr($h, -strlen($ok)) === $ok)) { return true; }
    }
    return false;
}

function push_post($sub, $payload, $v, $ttl = 86400) {
    $ep = $sub['endpoint'];
    if (!push_endpoint_ok($ep)) { return array(0, 'nem engedélyezett cím'); }
    preg_match('#^(https://[^/]+)#', $ep, $m);
    $body = push_encrypt($payload, $sub['p256dh'], $sub['auth']);
    $hdr = array('TTL: ' . (int) $ttl, 'Urgency: high', 'Content-Type: application/octet-stream', 'Content-Encoding: aes128gcm',
        'Authorization: vapid t=' . push_jwt($m[1], $v) . ', k=' . $v['pub'], 'Content-Length: ' . strlen($body));
    if (function_exists('curl_init')) {
        $c = curl_init($ep);
        curl_setopt_array($c, array(CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_HTTPHEADER => $hdr, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_CONNECTTIMEOUT => 5));
        $res = curl_exec($c); $code = (int) curl_getinfo($c, CURLINFO_HTTP_CODE); $err = curl_error($c); curl_close($c);
        return array($code, $code ? substr((string) $res, 0, 200) : $err);
    }
    $ctx = stream_context_create(array('http' => array('method' => 'POST', 'header' => implode("\r\n", $hdr), 'content' => $body, 'timeout' => 10, 'ignore_errors' => true)));
    $res = @file_get_contents($ep, false, $ctx);
    $code = 0;
    if (isset($http_response_header[0]) && preg_match('#\s(\d{3})\s#', $http_response_header[0] . ' ', $mm)) { $code = (int) $mm[1]; }
    return array($code, substr((string) $res, 0, 200));
}

function push_subs() { $raw = read_raw('push_subs'); $a = $raw ? json_decode($raw, true) : array(); return is_array($a) ? array_values($a) : array(); }

function push_prefs_default() { return array('order' => true, 'nl' => true, 'msg' => true, 'cart' => false, 'hide' => false); }
function push_prefs() {
    $raw = read_raw('push_prefs'); $p = $raw ? json_decode($raw, true) : null;
    $d = push_prefs_default();
    if (is_array($p)) { foreach ($d as $k => $x) { if (isset($p[$k])) { $d[$k] = (bool) $p[$k]; } } }
    return $d;
}

function push_send_all($msg, $onlyId = null) {
    $subs = push_subs();
    if (!$subs) { return array(); }
    $v = push_vapid();
    $payload = json_encode($msg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $out = array(); $gone = array(); $ok = array();
    foreach ($subs as $s) {
        if (!isset($s['id'], $s['endpoint'], $s['p256dh'], $s['auth'])) { continue; }
        if ($onlyId !== null && $s['id'] !== $onlyId) { continue; }
        try { list($code, $info) = push_post($s, $payload, $v); } catch (Throwable $e) { $code = 0; $info = $e->getMessage(); }
        $out[] = array('id' => $s['id'], 'label' => isset($s['label']) ? $s['label'] : '', 'code' => $code, 'info' => $code >= 200 && $code < 300 ? '' : $info);
        if ($code === 404 || $code === 410) { $gone[] = $s['id']; } elseif ($code >= 200 && $code < 300) { $ok[] = $s['id']; }
    }
    if ($gone || $ok) {
        $now = date('c');
        mutate_json('push_subs', function ($cur) use ($gone, $ok, $now) {
            $a = is_array($cur) ? json_decode(json_encode($cur), true) : array();
            if (!is_array($a)) { $a = array(); }
            $r = array();
            foreach ($a as $s) {
                if (!isset($s['id']) || in_array($s['id'], $gone, true)) { continue; }
                if (in_array($s['id'], $ok, true)) { $s['lastOkAt'] = $now; }
                $r[] = $s;
            }
            return $r;
        });
    }
    return $out;
}

function push_wants($type) { try { if (!push_subs()) { return false; } $p = push_prefs(); return !empty($p[$type]); } catch (Throwable $e) { return false; } }
function push_notify($type, $title, $body, $bodyHidden, $url, $tag = '') {
    try {
        if (!push_wants($type)) { return; }
        $p = push_prefs();
        $msg = array('t' => mb_substr((string) $title, 0, 120), 'b' => mb_substr((string) ($p['hide'] ? $bodyHidden : $body), 0, 240), 'u' => $url, 'g' => $tag !== '' ? $tag : $type . '-' . time(), 'y' => $type);
        register_shutdown_function(function () use ($msg) {
            try {
                if (function_exists('fastcgi_finish_request')) { @fastcgi_finish_request(); } elseif (function_exists('litespeed_finish_request')) { @litespeed_finish_request(); }
                ignore_user_abort(true);
                push_send_all($msg);
            } catch (Throwable $e) { }
        });
    } catch (Throwable $e) { }
}
