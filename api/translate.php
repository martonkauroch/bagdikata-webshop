<?php
// Automatikus fordítás (Google Cloud Translation, v2). Csak adminnak.
// A kulcs az api/data/translate.php fájlban él (a böngészőbe soha nem kerül ki), a fordítások gyorsítótárazva vannak.
define('BK_API', 1);
require __DIR__ . '/_lib.php';
ensure_data();
require_post();
$cfg = load_config();
require_admin($cfg);

function tr_conf() {
    $f = DATA_DIR . '/translate.php';
    if (!is_file($f)) { return array(); }
    $c = include $f;
    return is_array($c) ? $c : array();
}
function tr_save_conf($c) {
    $php = "<?php\nreturn " . var_export($c, true) . ";\n";
    $f = DATA_DIR . '/translate.php';
    if (@file_put_contents($f, $php, LOCK_EX) === false) { fail(500, 'Nem írható az adatmappa'); }
    @chmod($f, 0600);
}
function tr_endpoint($c) {
    $e = isset($c['endpoint']) && is_string($c['endpoint']) && $c['endpoint'] !== '' ? $c['endpoint'] : 'https://translation.googleapis.com/language/translate/v2';
    return $e;
}
// Egy kérés a Google felé: $parts = szövegrészek tömbje. Visszaad: fordított tömb vagy hibaszöveg (string).
function tr_call($c, $parts, $src, $dst) {
    $body = json_encode(array('q' => array_values($parts), 'source' => $src, 'target' => $dst, 'format' => 'text'), JSON_UNESCAPED_UNICODE);
    $url = tr_endpoint($c) . '?key=' . rawurlencode($c['key']);
    $raw = false; $code = 0;
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, array(CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 25,
            CURLOPT_HTTPHEADER => array('Content-Type: application/json; charset=utf-8')));
        $raw = curl_exec($ch); $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    } else {
        $ctx = stream_context_create(array('http' => array('method' => 'POST', 'header' => "Content-Type: application/json; charset=utf-8\r\n", 'content' => $body, 'timeout' => 25, 'ignore_errors' => true)));
        $raw = @file_get_contents($url, false, $ctx);
        if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) { $code = (int) $m[1]; }
    }
    if ($raw === false || $raw === '') { return 'A Google Fordító nem érhető el.'; }
    $j = json_decode($raw, true);
    if ($code !== 200 || !is_array($j) || !isset($j['data']['translations'])) {
        $msg = (is_array($j) && isset($j['error']['message'])) ? (string) $j['error']['message'] : ('HTTP ' . $code);
        return 'Google Fordító hiba: ' . mb_substr($msg, 0, 200);
    }
    $out = array();
    foreach ($j['data']['translations'] as $t) { $out[] = isset($t['translatedText']) ? html_entity_decode((string) $t['translatedText'], ENT_QUOTES | ENT_HTML5, 'UTF-8') : ''; }
    if (count($out) !== count($parts)) { return 'Google Fordító hiba: hiányos válasz.'; }
    return $out;
}
// Hosszú szöveget bekezdések mentén ~4000 karakteres darabokra vág, majd fordítás után összefűzi.
function tr_split($text) {
    $paras = preg_split('/(\n{2,})/', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
    $chunks = array(); $cur = '';
    foreach ($paras as $p) {
        if ($cur !== '' && mb_strlen($cur) + mb_strlen($p) > 4000) { $chunks[] = $cur; $cur = ''; }
        while (mb_strlen($p) > 4000) { $chunks[] = mb_substr($p, 0, 4000); $p = mb_substr($p, 4000); }
        $cur .= $p;
    }
    if ($cur !== '') { $chunks[] = $cur; }
    return $chunks;
}
function tr_cache_key($src, $dst, $t) { return substr(hash('sha256', $src . '>' . $dst . '|' . $t), 0, 32); }
function tr_month() { return gmdate('Y-m'); }

$d = body_json(400000);
$act = (is_object($d) && isset($d->action) && is_string($d->action)) ? $d->action : 'status';
$c = tr_conf();
$haveKey = !empty($c['key']) && is_string($c['key']);

function tr_status_out($c) {
    $u = read_raw('translate_usage'); $u = $u ? json_decode($u, true) : array();
    $m = tr_month();
    json_out(array('ok' => true, 'configured' => !empty($c['key']),
        'keyTail' => (!empty($c['key']) ? substr($c['key'], -4) : ''),
        'monthChars' => (isset($u['month']) && $u['month'] === $m) ? (int) $u['chars'] : 0,
        'totalChars' => isset($u['total']) ? (int) $u['total'] : 0,
        'lastError' => isset($u['lastError']) ? $u['lastError'] : '', 'lastOk' => isset($u['lastOk']) ? $u['lastOk'] : ''));
}

if ($act === 'status') { tr_status_out($c); }

if ($act === 'setkey') {
    $k = (is_object($d) && isset($d->key) && is_string($d->key)) ? trim($d->key) : '';
    if ($k !== '' && !preg_match('/^[A-Za-z0-9_\-]{20,80}$/', $k)) { fail(400, 'Ez nem tűnik érvényes API kulcsnak.'); }
    if ($k === '') { unset($c['key']); } else { $c['key'] = $k; }
    tr_save_conf($c);
    tr_status_out($c);
}

if (!$haveKey) { fail(400, 'Nincs megadva a Google Fordító API kulcs (Admin → Nyelv és pénznem).'); }
if (rl_blocked('translate', 600, 3600)) { fail(429, 'Túl sok fordítási kérés. Próbáld később.'); }
rl_hit('translate', 3600);

if ($act === 'test') {
    $r = tr_call($c, array('Kézzel kötött sapka'), 'hu', 'en');
    if (is_string($r)) { mutate_json('translate_usage', function ($u) use ($r) { $u = is_object($u) ? (array) $u : array(); $u['lastError'] = $r; return $u; }); fail(502, $r); }
    json_out(array('ok' => true, 'text' => $r[0]));
}

if ($act === 'translate') {
    $texts = (is_object($d) && isset($d->texts) && is_array($d->texts)) ? $d->texts : array();
    $src = (isset($d->source) && $d->source === 'en') ? 'en' : 'hu';
    $dst = (isset($d->target) && $d->target === 'hu') ? 'hu' : 'en';
    if (count($texts) < 1 || count($texts) > 60) { fail(400, 'Hibás kérés.'); }
    $total = 0;
    foreach ($texts as $t) { if (!is_string($t)) { fail(400, 'Hibás kérés.'); } $total += mb_strlen($t); }
    if ($total > 60000) { fail(413, 'Túl hosszú szöveg egy kérésben.'); }
    $cache = read_raw('translate_cache'); $cache = $cache ? json_decode($cache, true) : array();
    if (!is_array($cache)) { $cache = array(); }
    $result = array(); $need = array(); $newCache = array(); $chars = 0;
    foreach ($texts as $i => $t) {
        if (trim($t) === '') { $result[$i] = ''; continue; }
        $ck = tr_cache_key($src, $dst, $t);
        if (isset($cache[$ck])) { $result[$i] = $cache[$ck]; continue; }
        $need[$i] = $t;
    }
    // darabok gyűjtése
    $parts = array(); $map = array();
    foreach ($need as $i => $t) { foreach (tr_split($t) as $ch) { $parts[] = $ch; $map[] = $i; $chars += mb_strlen($ch); } }
    $err = '';
    if ($parts) {
        $outs = array();
        foreach (array_chunk($parts, 40, true) as $grp) {
            $r = tr_call($c, $grp, $src, $dst);
            if (is_string($r)) { $err = $r; break; }
            foreach ($r as $k2 => $v) { $outs[] = $v; }
        }
        if ($err === '') {
            $acc = array();
            foreach ($map as $k2 => $i) { $acc[$i] = (isset($acc[$i]) ? $acc[$i] : '') . $outs[$k2]; }
            foreach ($acc as $i => $v) { $result[$i] = $v; $newCache[tr_cache_key($src, $dst, $need[$i])] = $v; }
        }
    }
    $m = tr_month();
    mutate_json('translate_usage', function ($u) use ($err, $chars, $m) {
        $u = is_object($u) ? (array) $u : array();
        if (!isset($u['month']) || $u['month'] !== $m) { $u['month'] = $m; $u['chars'] = 0; }
        if ($err === '') { $u['chars'] = (int) $u['chars'] + $chars; $u['total'] = (isset($u['total']) ? (int) $u['total'] : 0) + $chars; $u['lastOk'] = gmdate('c'); $u['lastError'] = ''; }
        else { $u['lastError'] = $err; }
        return $u;
    });
    if ($err !== '') { fail(502, $err); }
    if ($newCache) {
        mutate_json('translate_cache', function ($cur) use ($newCache) {
            $a = is_object($cur) ? (array) $cur : array();
            foreach ($newCache as $k => $v) { $a[$k] = $v; }
            if (count($a) > 1500) { $a = array_slice($a, -1000, null, true); }
            return $a;
        });
    }
    $out = array(); foreach ($texts as $i => $t) { $out[] = isset($result[$i]) ? $result[$i] : ''; }
    json_out(array('ok' => true, 'texts' => $out, 'chars' => $chars));
}
fail(400, 'Ismeretlen művelet.');
