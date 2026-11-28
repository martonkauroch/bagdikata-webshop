<?php
if (!defined('BK_API')) { exit; }

function ig_cfg() { $raw = read_raw('instagram'); $a = $raw ? json_decode($raw, true) : null; return is_array($a) ? $a : array(); }
function ig_cfg_save($a) { write_raw('instagram', json_encode($a, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); @chmod(DATA_DIR . '/instagram.json', 0600); }
function ig_base() { $c = ig_cfg(); return (isset($c['base']) && is_string($c['base']) && preg_match('#^http://127\.0\.0\.1:\d+$#', $c['base'])) ? $c['base'] : 'https://graph.instagram.com'; }

function ig_http($method, $url, $params) {
    $q = http_build_query($params);
    if ($method === 'GET') { $url .= (strpos($url, '?') === false ? '?' : '&') . $q; }
    if (function_exists('curl_init')) {
        $c = curl_init($url);
        $o = array(CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 40, CURLOPT_CONNECTTIMEOUT => 8);
        if ($method === 'POST') { $o[CURLOPT_POST] = true; $o[CURLOPT_POSTFIELDS] = $q; }
        curl_setopt_array($c, $o);
        $res = curl_exec($c); $code = (int) curl_getinfo($c, CURLINFO_HTTP_CODE); $err = curl_error($c); curl_close($c);
        if ($res === false) { throw new Exception('Az Instagram nem érhető el: ' . $err); }
    } else {
        $ctx = stream_context_create(array('http' => array('method' => $method, 'header' => 'Content-Type: application/x-www-form-urlencoded', 'content' => $method === 'POST' ? $q : '', 'timeout' => 40, 'ignore_errors' => true)));
        $res = @file_get_contents($url, false, $ctx);
        $code = 0; if (isset($http_response_header[0]) && preg_match('#\s(\d{3})\s#', $http_response_header[0] . ' ', $m)) { $code = (int) $m[1]; }
        if ($res === false) { throw new Exception('Az Instagram nem érhető el.'); }
    }
    $j = json_decode((string) $res, true);
    if (!is_array($j)) { throw new Exception('Váratlan válasz az Instagramtól (' . $code . ').'); }
    if (isset($j['error'])) {
        $e = $j['error']; $msg = is_array($e) ? (isset($e['error_user_msg']) ? $e['error_user_msg'] : (isset($e['message']) ? $e['message'] : 'ismeretlen hiba')) : (string) $e;
        throw new Exception('Instagram: ' . mb_substr((string) $msg, 0, 300));
    }
    if ($code >= 400) { throw new Exception('Instagram hiba (' . $code . ').'); }
    return $j;
}
function ig_api($method, $path, $params = array()) {
    $c = ig_cfg();
    if (empty($c['token'])) { throw new Exception('Nincs összekötött Instagram-fiók.'); }
    $params['access_token'] = $c['token'];
    return ig_http($method, ig_base() . '/v21.0/' . ltrim($path, '/'), $params);
}

function ig_connect($token) {
    $token = trim((string) $token);
    if (!preg_match('/^[A-Za-z0-9_.|-]{40,600}$/', $token)) { throw new Exception('Ez nem tűnik érvényes Instagram hozzáférési kulcsnak.'); }
    $me = ig_http('GET', ig_base() . '/v21.0/me', array('fields' => 'user_id,username,account_type,profile_picture_url', 'access_token' => $token));
    if (empty($me['user_id']) && empty($me['id'])) { throw new Exception('A kulcs nem azonosít Instagram-fiókot.'); }
    $c = array('token' => $token, 'userId' => (string) (!empty($me['user_id']) ? $me['user_id'] : $me['id']), 'username' => isset($me['username']) ? (string) $me['username'] : '',
        'accountType' => isset($me['account_type']) ? (string) $me['account_type'] : '', 'picture' => isset($me['profile_picture_url']) ? (string) $me['profile_picture_url'] : '',
        'connectedAt' => date('c'), 'refreshedAt' => date('c'), 'expiresAt' => date('c', time() + 60 * 86400));
    $old = ig_cfg(); if (isset($old['base'])) { $c['base'] = $old['base']; }
    try {
        $r = ig_http('GET', ig_base() . '/refresh_access_token', array('grant_type' => 'ig_refresh_token', 'access_token' => $token));
        if (!empty($r['access_token'])) { $c['token'] = (string) $r['access_token']; $c['expiresAt'] = date('c', time() + (isset($r['expires_in']) ? (int) $r['expires_in'] : 60 * 86400)); }
    } catch (Throwable $e) { }
    ig_cfg_save($c);
    return $c;
}

function ig_refresh_if_needed($force = false) {
    $c = ig_cfg();
    if (empty($c['token'])) { return false; }
    $last = isset($c['refreshedAt']) ? strtotime($c['refreshedAt']) : 0;
    if (!$force && $last > time() - 7 * 86400) { return false; }
    try {
        $r = ig_http('GET', ig_base() . '/refresh_access_token', array('grant_type' => 'ig_refresh_token', 'access_token' => $c['token']));
        if (!empty($r['access_token'])) {
            $c['token'] = (string) $r['access_token']; $c['refreshedAt'] = date('c');
            $c['expiresAt'] = date('c', time() + (isset($r['expires_in']) ? (int) $r['expires_in'] : 60 * 86400));
            unset($c['lastError']); ig_cfg_save($c); return true;
        }
    } catch (Throwable $e) { $c['lastError'] = mb_substr($e->getMessage(), 0, 300); $c['lastErrorAt'] = date('c'); ig_cfg_save($c); }
    return false;
}

function ig_img_register($ref, $fy) {
    require_once __DIR__ . '/_seo.php';
    list($url, $file) = seo_image($ref);
    if (!$file || !is_file($file)) { throw new Exception('A kép nem található: ' . mb_substr((string) $ref, 0, 80)); }
    $id = bin2hex(random_bytes(8)); $now = time();
    mutate_json('ig_imgs', function ($cur) use ($id, $file, $fy, $now) {
        $a = is_array($cur) || is_object($cur) ? json_decode(json_encode($cur), true) : array();
        if (!is_array($a)) { $a = array(); }
        foreach ($a as $k => $v) { if (!isset($v['exp']) || $v['exp'] < $now) { unset($a[$k]); } }
        $a[$id] = array('f' => $file, 'y' => $fy, 'exp' => $now + 3 * 3600);
        return $a;
    });
    return seo_site_url() . '/assets/igp/' . $id . '.jpg';
}

function ig_render($file, $fy, $out) {
    $info = @getimagesize($file); $src = null;
    if ($info) {
        if ($info[2] === IMAGETYPE_JPEG) { $src = @imagecreatefromjpeg($file); }
        elseif ($info[2] === IMAGETYPE_PNG) { $src = @imagecreatefrompng($file); }
        elseif ($info[2] === IMAGETYPE_WEBP && function_exists('imagecreatefromwebp')) { $src = @imagecreatefromwebp($file); }
        elseif ($info[2] === IMAGETYPE_GIF) { $src = @imagecreatefromgif($file); }
    }
    if (!$src) { return false; }
    $sw = imagesx($src); $sh = imagesy($src); $r = $sw / $sh;
    $cx = 0; $cy = 0; $cw = $sw; $ch = $sh;
    if ($r < 0.8) { $ch = (int) round($sw / 0.8); $cy = (int) round(($sh - $ch) * max(0, min(100, $fy)) / 100); }
    elseif ($r > 1.91) { $cw = (int) round($sh * 1.91); $cx = (int) round(($sw - $cw) / 2); }
    $W = min(1440, $cw); $H = (int) round($ch * $W / $cw);
    $dst = imagecreatetruecolor($W, $H);
    imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
    imagecopyresampled($dst, $src, 0, 0, $cx, $cy, $W, $H, $cw, $ch);
    return imagejpeg($dst, $out, 90);
}

function ig_wait($cid) {
    for ($i = 0; $i < 12; $i++) {
        $s = ig_api('GET', $cid, array('fields' => 'status_code,status'));
        $st = isset($s['status_code']) ? $s['status_code'] : '';
        if ($st === 'FINISHED' || $st === '') { return; }
        if ($st === 'ERROR' || $st === 'EXPIRED') { throw new Exception('Az Instagram nem tudta feldolgozni a képet' . (!empty($s['status']) ? ': ' . $s['status'] : '.')); }
        sleep(2);
    }
    throw new Exception('Az Instagram feldolgozása túl sokáig tart – próbáld újra később.');
}

function ig_publish($refs, $caption, $meta) {
    $c = ig_cfg();
    if (empty($c['userId'])) { throw new Exception('Nincs összekötött Instagram-fiók.'); }
    $uid = $c['userId'];
    $urls = array();
    foreach (array_slice($refs, 0, 10) as $r) { $urls[] = ig_img_register($r['ref'], $r['y']); }
    if (!$urls) { throw new Exception('Legalább egy kép kell.'); }
    if (count($urls) === 1) {
        $m = ig_api('POST', $uid . '/media', array('image_url' => $urls[0], 'caption' => $caption));
        $cid = $m['id'];
    } else {
        $kids = array();
        foreach ($urls as $u) { $k = ig_api('POST', $uid . '/media', array('image_url' => $u, 'is_carousel_item' => 'true')); $kids[] = $k['id']; }
        foreach ($kids as $k) { ig_wait($k); }
        $m = ig_api('POST', $uid . '/media', array('media_type' => 'CAROUSEL', 'children' => implode(',', $kids), 'caption' => $caption));
        $cid = $m['id'];
    }
    ig_wait($cid);
    $p = ig_api('POST', $uid . '/media_publish', array('creation_id' => $cid));
    $mid = isset($p['id']) ? (string) $p['id'] : '';
    $link = '';
    try { $x = ig_api('GET', $mid, array('fields' => 'permalink')); $link = isset($x['permalink']) ? (string) $x['permalink'] : ''; } catch (Throwable $e) { }
    $rec = array('at' => date('c'), 'kind' => $meta['kind'], 'refId' => $meta['id'], 'label' => $meta['label'], 'images' => count($urls), 'caption' => mb_substr($caption, 0, 300), 'mediaId' => $mid, 'permalink' => $link);
    mutate_json('ig_posts', function ($cur) use ($rec) {
        $a = is_array($cur) ? $cur : (is_object($cur) ? json_decode(json_encode($cur), true) : array());
        if (!is_array($a)) { $a = array(); }
        array_unshift($a, $rec);
        return array_slice($a, 0, 100);
    });
    return $rec;
}
