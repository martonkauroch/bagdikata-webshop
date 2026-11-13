<?php
// Közös segédfüggvények. Közvetlenül nem hívható.
if (!defined('BK_API')) { http_response_code(403); exit; }
@ini_set('display_errors', '0');
error_reporting(E_ALL);

define('DATA_DIR', __DIR__ . '/data');
define('SHARED_KEYS', 'content,settings,store,audit,outbox,withdrawals');
define('MAX_SAVE_BYTES', 8 * 1024 * 1024);

function shared_keys() { return explode(',', SHARED_KEYS); }
function is_https() {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
}
function ensure_data() {
    if (!is_dir(DATA_DIR)) { @mkdir(DATA_DIR, 0755, true); }
    $h = DATA_DIR . '/.htaccess';
    if (!is_file($h)) {
        @file_put_contents($h, "Require all denied\n<IfModule !mod_authz_core.c>\nOrder allow,deny\nDeny from all\n</IfModule>\n");
    }
    if (!is_file(DATA_DIR . '/index.html')) { @file_put_contents(DATA_DIR . '/index.html', ''); }
    if (!is_dir(DATA_DIR . '/img')) { @mkdir(DATA_DIR . '/img', 0755, true); }
}
function json_out($arr, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($arr, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function fail($code, $msg) { json_out(array('ok' => false, 'error' => $msg), $code); }
function load_config() {
    $f = DATA_DIR . '/config.php';
    if (!is_file($f)) { return null; }
    $c = include $f;
    return is_array($c) ? $c : null;
}
function b64u($s) { return rtrim(strtr(base64_encode($s), '+/', '-_'), '='); }
function b64d($s) { return base64_decode(strtr($s, '-_', '+/')); }
function make_token($cfg, $type, $ttl) {
    $p = b64u(json_encode(array('t' => $type, 'exp' => time() + $ttl, 'n' => bin2hex(random_bytes(8)))));
    return $p . '.' . b64u(hash_hmac('sha256', $p, $cfg['secret'], true));
}
function check_token($cfg, $type, $tok) {
    if (!is_string($tok) || strpos($tok, '.') === false) { return false; }
    list($p, $sig) = explode('.', $tok, 2);
    $exp = b64u(hash_hmac('sha256', $p, $cfg['secret'], true));
    if (!hash_equals($exp, $sig)) { return false; }
    $d = json_decode((string) b64d($p), true);
    return is_array($d) && isset($d['t'], $d['exp']) && $d['t'] === $type && $d['exp'] > time();
}
function put_cookie($name, $val, $ttl) {
    setcookie($name, $val, array(
        'expires' => $ttl > 0 ? time() + $ttl : time() - 3600,
        'path' => '/', 'secure' => is_https(), 'httponly' => true, 'samesite' => 'Lax',
    ));
}
// Az oldal állapota: nyilvános (nincs oldaljelszó) vagy jelszóval védett. A jelzőfájlt a save.php írja, amikor az admin a Beállításokban menti.
function site_is_public() { return is_file(DATA_DIR . '/public.flag'); }
// A „Hamarosan” oldal szerkeszthető tartalma (settings.access.landing), megtisztítva – az oldaljelszó nélküli látogatók is megkapják.
function landing_public() {
    $raw = read_raw('settings'); $s = $raw ? json_decode($raw) : null;
    if (!is_object($s) || !isset($s->access) || !is_object($s->access) || !isset($s->access->landing) || !is_object($s->access->landing)) { return null; }
    $l = $s->access->landing; $out = array();
    foreach (array('title' => 160, 'lead' => 700, 'writeTitle' => 80, 'hint' => 300) as $k => $max) {
        if (!isset($l->$k) || !is_object($l->$k)) { continue; }
        foreach (array('hu', 'en') as $lg) {
            if (isset($l->$k->$lg) && is_string($l->$k->$lg)) { $out[$k][$lg] = mb_substr($l->$k->$lg, 0, $max, 'UTF-8'); }
        }
    }
    if (isset($l->form)) { $out['form'] = ($l->form === true); }
    if (isset($l->images) && is_array($l->images)) {
        $imgs = array();
        foreach ($l->images as $im) {
            if (count($imgs) >= 4 || !is_object($im) || !isset($im->src) || !is_string($im->src)) { continue; }
            if (!preg_match('#^(assets/[A-Za-z0-9._-]+\.(jpg|jpeg|png|webp)|srv:[a-f0-9]{32}\.(jpg|png|webp|gif))$#', $im->src)) { continue; }
            $pos = isset($im->pos) && is_numeric($im->pos) ? max(0, min(100, (int) $im->pos)) : 50;
            $imgs[] = array('src' => $im->src, 'pos' => $pos);
        }
        $out['images'] = $imgs;
    }
    return count($out) ? (object) $out : null;
}
function landing_has_image($id) {
    $l = landing_public();
    if (!$l || empty($l->images)) { return false; }
    foreach ($l->images as $im) { if ($im['src'] === 'srv:' . $id) { return true; } }
    return false;
}
function has_site($cfg) { if (!$cfg) { return false; } if (site_is_public()) { return true; } return isset($_COOKIE['bk_site']) && check_token($cfg, 'site', $_COOKIE['bk_site']); }
function has_admin($cfg) { return $cfg && isset($_COOKIE['bk_admin']) && check_token($cfg, 'admin', $_COOKIE['bk_admin']); }
// Opcionális robotvédelem (Cloudflare Turnstile): ha az api/data/security.php-ban nincs kulcs, kikapcsolt.
function bot_cfg() {
    $f = DATA_DIR . '/security.php';
    $c = is_file($f) ? @include $f : null;
    return is_array($c) ? $c : array();
}
function bot_site_key() { $c = bot_cfg(); return (!empty($c['turnstile_site']) && !empty($c['turnstile_secret'])) ? (string) $c['turnstile_site'] : ''; }
function bot_verify($token) {
    $c = bot_cfg();
    if (empty($c['turnstile_secret']) || empty($c['turnstile_site'])) { return true; }
    if (!is_string($token) || $token === '' || strlen($token) > 2048) { return false; }
    $post = http_build_query(array('secret' => $c['turnstile_secret'], 'response' => $token, 'remoteip' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : ''));
    $ctx = stream_context_create(array('http' => array('method' => 'POST', 'header' => "Content-Type: application/x-www-form-urlencoded\r\n", 'content' => $post, 'timeout' => 6)));
    $r = @file_get_contents('https://challenges.cloudflare.com/turnstile/v0/siteverify', false, $ctx);
    if ($r === false) { return true; }   // ha a Cloudflare nem elérhető, ne akadályozzuk a vásárlást (a rate limit így is véd)
    $j = json_decode($r, true);
    return is_array($j) && !empty($j['success']);
}
function require_post() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { fail(405, 'POST szükséges'); }
    if (!isset($_SERVER['HTTP_X_BK']) || $_SERVER['HTTP_X_BK'] !== '1') { fail(403, 'Hiányzó fejléc'); }
}
function require_site($cfg) { if (!has_site($cfg)) { fail(401, 'Nincs belépve (oldaljelszó)'); } }
function require_admin($cfg) { require_site($cfg); if (!has_admin($cfg)) { fail(401, 'Admin belépés szükséges'); } }
function body_raw($max) {
    $s = file_get_contents('php://input', false, null, 0, $max + 1);
    if ($s === false) { $s = ''; }
    if (strlen($s) > $max) { fail(413, 'Túl nagy kérés'); }
    return $s;
}
function body_json($max) {
    $s = body_raw($max);
    $d = json_decode($s);
    if ($d === null && trim($s) !== 'null') { fail(400, 'Érvénytelen JSON'); }
    return $d;
}
function rl_file($bucket) {
    $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0';
    return DATA_DIR . '/rl_' . sha1($bucket . '|' . $ip) . '.json';
}
function rl_blocked($bucket, $max, $window) {
    $f = rl_file($bucket);
    if (!is_file($f)) { return false; }
    $a = json_decode((string) @file_get_contents($f), true);
    if (!is_array($a)) { return false; }
    $now = time(); $n = 0;
    foreach ($a as $t) { if ($t > $now - $window) { $n++; } }
    return $n >= $max;
}
function rl_hit($bucket, $window) {
    $f = rl_file($bucket);
    $a = is_file($f) ? json_decode((string) @file_get_contents($f), true) : array();
    if (!is_array($a)) { $a = array(); }
    $now = time(); $keep = array();
    foreach ($a as $t) { if ($t > $now - $window) { $keep[] = $t; } }
    $keep[] = $now;
    @file_put_contents($f, json_encode($keep));
}
function rl_clear($bucket) { $f = rl_file($bucket); if (is_file($f)) { @unlink($f); } }
function read_raw($name) {
    $f = DATA_DIR . '/' . $name . '.json';
    if (!is_file($f)) { return null; }
    $s = @file_get_contents($f);
    return ($s === false || $s === '') ? null : $s;
}
function write_raw($name, $json) {
    ensure_data();
    $f = DATA_DIR . '/' . $name . '.json';
    $fh = fopen($f, 'c+');
    if (!$fh) { fail(500, 'Nem írható az adatmappa'); }
    flock($fh, LOCK_EX);
    ftruncate($fh, 0); rewind($fh); fwrite($fh, $json); fflush($fh);
    flock($fh, LOCK_UN); fclose($fh);
}
function mutate_json($name, $fn) {
    ensure_data();
    $f = DATA_DIR . '/' . $name . '.json';
    $fh = fopen($f, 'c+');
    if (!$fh) { fail(500, 'Nem írható az adatmappa'); }
    flock($fh, LOCK_EX);
    $s = stream_get_contents($fh);
    $cur = ($s !== false && $s !== '') ? json_decode($s) : null;
    $new = $fn($cur);
    $json = json_encode($new, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) { flock($fh, LOCK_UN); fclose($fh); fail(500, 'Kódolási hiba'); }
    ftruncate($fh, 0); rewind($fh); fwrite($fh, $json); fflush($fh);
    flock($fh, LOCK_UN); fclose($fh);
    return $new;
}
function redact_settings($v) {
    if (is_array($v)) { foreach ($v as $i => $x) { $v[$i] = redact_settings($x); } return $v; }
    if (!is_object($v)) { return $v; }
    foreach (get_object_vars($v) as $k => $x) {
        if ($k === 'recipients') { $v->$k = array(); }
        elseif (in_array($k, array('payeeEmail', 'username', 'clientNumber', 'pixelId', 'blockId', 'senderLabel'), true) && is_string($x)) { $v->$k = ''; }
        else { $v->$k = redact_settings($x); }
    }
    return $v;
}
function union_by_id($server, $client, $cap) {
    $map = array(); $order = array();
    foreach ((array) $client as $o) { if (is_object($o) && isset($o->id) && is_string($o->id)) { $map[$o->id] = $o; $order[] = $o->id; } }
    $front = array();
    foreach ((array) $server as $o) { if (is_object($o) && isset($o->id) && is_string($o->id) && !isset($map[$o->id])) { $map[$o->id] = $o; $front[] = $o->id; } }
    $ids = array_merge($front, $order);
    $out = array();
    foreach ($ids as $id) { $out[] = $map[$id]; if (count($out) >= $cap) { break; } }
    return $out;
}
