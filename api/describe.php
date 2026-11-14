<?php
define('BK_API', 1);
require __DIR__ . '/_lib.php';
ensure_data();
require_post();
$cfg = load_config();
require_admin($cfg);

function ai_conf() {
    $f = DATA_DIR . '/ai.php';
    if (!is_file($f)) { return array(); }
    $c = include $f;
    return is_array($c) ? $c : array();
}
function ai_save($c) {
    $f = DATA_DIR . '/ai.php';
    if (@file_put_contents($f, "<?php\nreturn " . var_export($c, true) . ";\n", LOCK_EX) === false) { fail(500, 'Nem írható az adatmappa'); }
    @chmod($f, 0600);
}
function ai_model($c) { return (isset($c['model']) && is_string($c['model']) && $c['model'] !== '') ? $c['model'] : 'claude-haiku-5-5'; }
function ai_jpeg_b64($path) {
    $info = @getimagesize($path);
    if (!$info || !function_exists('imagecreatetruecolor')) { return null; }
    if ($info[2] === IMAGETYPE_JPEG) { $im = @imagecreatefromjpeg($path); }
    elseif ($info[2] === IMAGETYPE_PNG) { $im = @imagecreatefrompng($path); }
    elseif ($info[2] === IMAGETYPE_WEBP && function_exists('imagecreatefromwebp')) { $im = @imagecreatefromwebp($path); }
    elseif ($info[2] === IMAGETYPE_GIF) { $im = @imagecreatefromgif($path); }
    else { return null; }
    if (!$im) { return null; }
    $w = imagesx($im); $h = imagesy($im); $max = 1024;
    if (max($w, $h) > $max) {
        $r = $max / max($w, $h); $nw = max(1, (int) round($w * $r)); $nh = max(1, (int) round($h * $r));
        $t = imagecreatetruecolor($nw, $nh); imagecopyresampled($t, $im, 0, 0, 0, 0, $nw, $nh, $w, $h); imagedestroy($im); $im = $t;
    }
    ob_start(); imagejpeg($im, null, 82); $b = ob_get_clean(); imagedestroy($im);
    return base64_encode($b);
}
function ai_post($c, $payload) {
    $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
    $hdr = array('Content-Type: application/json', 'x-api-key: ' . $c['key'], 'anthropic-version: 2023-06-01');
    $raw = false; $code = 0;
    if (function_exists('curl_init')) {
        $ch = curl_init('https://api.anthropic.com/v1/messages');
        curl_setopt_array($ch, array(CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 60, CURLOPT_HTTPHEADER => $hdr));
        $raw = curl_exec($ch); $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    } else {
        $ctx = stream_context_create(array('http' => array('method' => 'POST', 'header' => implode("\r\n", $hdr) . "\r\n", 'content' => $body, 'timeout' => 60, 'ignore_errors' => true)));
        $raw = @file_get_contents('https://api.anthropic.com/v1/messages', false, $ctx);
        if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) { $code = (int) $m[1]; }
    }
    if ($raw === false || $raw === '') { return array(null, 'Az AI szolgáltatás nem érhető el.'); }
    $j = json_decode($raw, true);
    if ($code < 200 || $code >= 300 || !is_array($j)) {
        $msg = (is_array($j) && isset($j['error']['message'])) ? (string) $j['error']['message'] : ('HTTP ' . $code);
        if ($code === 401) { $msg = 'Az API-kulcs érvénytelen.'; }
        return array(null, 'Az AI hibát jelzett: ' . $msg);
    }
    return array($j, null);
}

$d = body_json(8192);
$act = (is_object($d) && isset($d->action) && is_string($d->action)) ? $d->action : 'status';
$c = ai_conf();

if ($act === 'status') {
    json_out(array('ok' => true, 'configured' => !empty($c['key']), 'model' => ai_model($c), 'last4' => !empty($c['key']) ? substr($c['key'], -4) : ''));
}
if ($act === 'setkey') {
    $k = (is_object($d) && isset($d->key) && is_string($d->key)) ? trim($d->key) : '';
    if (strlen($k) < 20 || strlen($k) > 300 || preg_match('/\s/', $k)) { fail(400, 'Érvénytelen API-kulcs.'); }
    $c['key'] = $k; ai_save($c);
    json_out(array('ok' => true));
}
if ($act === 'clearkey') { unset($c['key']); ai_save($c); json_out(array('ok' => true)); }
if ($act !== 'describe') { fail(400, 'Ismeretlen művelet'); }

if (empty($c['key'])) { fail(412, 'Az AI-leírás még nincs bekapcsolva: add meg az API-kulcsot a Fotók oldalon.'); }
if (rl_blocked('describe', 40, 3600)) { fail(429, 'Túl sok generálás. Próbáld újra később.'); }
rl_hit('describe', 3600);
$ids = (is_object($d) && isset($d->images) && is_array($d->images)) ? $d->images : array();
$blocks = array();
foreach ($ids as $id) {
    if (count($blocks) >= 4) { break; }
    if (!is_string($id) || !preg_match('/^[a-f0-9]{32}\.(jpg|png|webp|gif)$/', $id)) { continue; }
    $p = DATA_DIR . '/img/' . $id;
    if (!is_file($p)) { continue; }
    $b = ai_jpeg_b64($p);
    if ($b) { $blocks[] = array('type' => 'image', 'source' => array('type' => 'base64', 'media_type' => 'image/jpeg', 'data' => $b)); }
}
if (!$blocks) { fail(400, 'Nincs elemezhető kép (csak a feltöltött képekből tud leírást készíteni).'); }
$name = (is_object($d) && isset($d->name) && is_string($d->name)) ? mb_substr(trim($d->name), 0, 120) : '';
$cat = (is_object($d) && isset($d->category) && is_string($d->category)) ? mb_substr(trim($d->category), 0, 80) : '';
$ctx = '';
if ($name !== '') { $ctx .= "A termék neve: $name\n"; }
if ($cat !== '') { $ctx .= "Kategória: $cat\n"; }
$prompt = "Ezek egy kézzel kötött termék fotói a Bagdi Kata webshopból (sapkák, nyakmelegítők, kézzel kötött kiegészítők).\n" . $ctx .
"Írj a termékhez webshop-leírást magyarul, 2-3 mondatban, kedves, természetes hangon, a fotókon ténylegesen látható dolgokra építve (szín, melanzs szálak, kötésminta, forma, hangulat). " .
"Ne találj ki anyagösszetételt, méretet, mosási útmutatót, árat vagy készletet, és ne említsd magát a fotót vagy modellt. Ne használj felsorolást, emojit vagy idézőjeles szlogent. " .
"Adj angol változatot is ugyanerről (természetes angol, nem szó szerinti fordítás). Csak ezt a JSON-t add vissza, semmi mást: {\"hu\":\"...\",\"en\":\"...\"}";
$blocks[] = array('type' => 'text', 'text' => $prompt);
list($j, $err) = ai_post($c, array('model' => ai_model($c), 'max_tokens' => 700, 'messages' => array(array('role' => 'user', 'content' => $blocks))));
if ($err) { fail(502, $err); }
$txt = '';
if (isset($j['content']) && is_array($j['content'])) { foreach ($j['content'] as $b) { if (isset($b['type']) && $b['type'] === 'text') { $txt .= $b['text']; } } }
if (preg_match('/\{.*\}/s', $txt, $m)) { $r = json_decode($m[0], true); } else { $r = null; }
if (!is_array($r) || empty($r['hu'])) { fail(502, 'Az AI válasza nem értelmezhető. Próbáld újra.'); }
json_out(array('ok' => true, 'hu' => trim((string) $r['hu']), 'en' => isset($r['en']) ? trim((string) $r['en']) : ''));
