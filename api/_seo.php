<?php
if (!defined('BK_API')) { exit; }

function seo_site_url() { require_once __DIR__ . '/_mail.php'; return rtrim(mail_site_url(), '/'); }
function seo_h($s) { return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function seo_products() {
    $raw = read_raw('store'); $st = $raw ? json_decode($raw) : null;
    $all = (is_object($st) && isset($st->products) && is_array($st->products)) ? $st->products : array();
    return array_values(array_filter($all, 'seo_product_on'));
}
function seo_product_on($p) {
    if (!is_object($p)) { return false; }
    return !(isset($p->active) && $p->active === false);
}
function seo_find_product($id) {
    foreach (seo_products() as $p) { if (is_object($p) && isset($p->id) && $p->id === $id) { return $p; } }
    $m = seo_slug_map();
    foreach (seo_products() as $p) { if (isset($m[$p->id]) && $m[$p->id] === $id) { return $p; } }
    return null;
}
function seo_slug($s) {
    $s = (string) $s;
    if (class_exists('Normalizer')) {
        $s = preg_replace('/\p{Mn}+/u', '', Normalizer::normalize(mb_strtolower($s, 'UTF-8'), Normalizer::FORM_D));
    } else {
        $s = strtr(mb_strtolower($s, 'UTF-8'), array('á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ö' => 'o', 'ő' => 'o', 'ú' => 'u', 'ü' => 'u', 'ű' => 'u',
            'à' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a', 'å' => 'a', 'ç' => 'c', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'ñ' => 'n', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ù' => 'u', 'û' => 'u', 'ý' => 'y', 'ÿ' => 'y', 'č' => 'c', 'ć' => 'c', 'š' => 's', 'ž' => 'z', 'ř' => 'r', 'ě' => 'e', 'ł' => 'l', 'ś' => 's', 'ź' => 'z', 'ż' => 'z', 'ń' => 'n', 'ę' => 'e', 'ą' => 'a'));
    }
    $s = str_replace('ß', 'ss', $s);
    $s = trim(preg_replace('/[^a-z0-9]+/', '_', $s), '_');
    return rtrim(substr($s, 0, 80), '_');
}
function seo_slug_map() {
    static $map = null;
    if ($map !== null) { return $map; }
    $raw = read_raw('store'); $st = $raw ? json_decode($raw) : null;
    $all = (is_object($st) && isset($st->products) && is_array($st->products)) ? $st->products : array();
    $map = array(); $used = array();
    foreach ($all as $p) {
        if (!is_object($p) || !isset($p->id) || !is_string($p->id) || $p->id === '') { continue; }
        $b = seo_slug(isset($p->name) ? $p->name : ''); if ($b === '') { $b = seo_slug($p->id); } if ($b === '') { $b = 'termek'; }
        $x = $b; $i = 2; while (isset($used[$x])) { $x = $b . '_' . $i++; }
        $used[$x] = true; $map[$p->id] = $x;
    }
    return $map;
}
function seo_product_slug($p) { $m = seo_slug_map(); return isset($m[$p->id]) ? $m[$p->id] : $p->id; }
function seo_is_bot() {
    $ua = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
    return (bool) preg_match('/bot|crawl|spider|slurp|facebookexternalhit|facebot|whatsapp|telegram|twitter|pinterest|linkedin|embedly|preview|skype|viber|discord|slack|google-inspectiontool|bingpreview|duckduck/i', $ua);
}
function seo_image($src) {
    $site = seo_site_url();
    if (!is_string($src) || $src === '') { return array($site . '/assets/hero-1-DalRuRma.jpg', __DIR__ . '/../assets/hero-1-DalRuRma.jpg'); }
    if (preg_match('#^https?://#i', $src)) { return array($src, null); }
    if (strpos($src, 'srv:') === 0) {
        $id = substr($src, 4);
        if (preg_match('/^[a-f0-9]{32}\.(jpg|png|webp|gif)$/', $id)) { return array($site . '/api/img.php?id=' . $id, DATA_DIR . '/img/' . $id); }
        return array('', null);
    }
    if (strpos($src, 'asset:') === 0) {
        $base = preg_replace('/\.(jpg|jpeg|png|webp)$/i', '', basename(substr($src, 6)));
        $ext = strtolower(pathinfo($src, PATHINFO_EXTENSION));
        foreach (glob(__DIR__ . '/../assets/' . $base . '-*.' . $ext) ?: array() as $f) {
            if (preg_match('/^' . preg_quote($base, '/') . '-[A-Za-z0-9_-]{8}\.' . preg_quote($ext, '/') . '$/', basename($f))) { return array($site . '/assets/' . basename($f), $f); }
        }
    }
    return array('', null);
}
function seo_money($n, $lang) { return $lang === 'en' ? number_format((float) $n, 0, '.', ',') . ' HUF' : number_format((float) $n, 0, ',', ' ') . ' Ft'; }
function seo_pages() {
    return array(
        'rolunk' => array('Rólunk', 'About us', 'Ismerd meg Bagdi Katát és a kézzel kötött melanzs sapkák történetét.', 'Meet Bagdi Kata and the story behind the hand-knitted melange hats.'),
        'kapcsolat' => array('Kapcsolat', 'Contact', 'Írj nekünk kérdéssel vagy egyedi kéréssel, hamar válaszolunk.', 'Write to us with a question or a custom request, we reply quickly.'),
        'szallitas' => array('Szállítás és fizetés', 'Shipping and payment', 'Szállítási módok, díjak és fizetési lehetőségek.', 'Delivery options, fees and payment methods.'),
        'aszf' => array('ÁSZF', 'Terms and conditions', 'Általános szerződési feltételek.', 'General terms and conditions.'),
        'adatvedelem' => array('Adatkezelési tájékoztató', 'Privacy policy', 'Adatkezelési tájékoztató.', 'Privacy policy.'),
        'elallas' => array('Elállás a szerződéstől', 'Right of withdrawal', 'Elállási jog és a teendők.', 'Right of withdrawal and how to use it.'),
        'impresszum' => array('Impresszum', 'Legal notice', 'Üzemeltető adatai.', 'Operator details.'),
    );
}
