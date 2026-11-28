<?php
define('BK_API', 1);
require __DIR__ . '/_lib.php';
require __DIR__ . '/_ig.php';
require_once __DIR__ . '/_seo.php';
ensure_data();
$cfg = load_config();
if (!$cfg || !has_admin($cfg)) { fail(401, 'Admin belépés szükséges'); }
header('Cache-Control: no-store');
@set_time_limit(120);

function ig_view() {
    $c = ig_cfg();
    $out = array('ok' => true, 'connected' => !empty($c['token']), 'username' => isset($c['username']) ? $c['username'] : '', 'accountType' => isset($c['accountType']) ? $c['accountType'] : '',
        'picture' => isset($c['picture']) ? $c['picture'] : '', 'connectedAt' => isset($c['connectedAt']) ? $c['connectedAt'] : null, 'expiresAt' => isset($c['expiresAt']) ? $c['expiresAt'] : null,
        'lastError' => isset($c['lastError']) ? $c['lastError'] : '', 'public' => site_is_public());
    $raw = read_raw('store'); $st = $raw ? json_decode($raw) : null; $prods = array();
    if (is_object($st) && isset($st->products) && is_array($st->products)) {
        foreach ($st->products as $p) {
            if (!is_object($p) || !isset($p->id) || (isset($p->active) && $p->active === false)) { continue; }
            $imgs = array();
            foreach (array_merge(array(isset($p->imageUrl) ? $p->imageUrl : ''), isset($p->gallery) && is_array($p->gallery) ? $p->gallery : array()) as $r) {
                if (!is_string($r) || $r === '') { continue; }
                list($u, $f) = seo_image($r);
                if ($f && is_file($f)) { $imgs[] = array('ref' => $r, 'url' => $u); }
            }
            $prods[] = array('id' => $p->id, 'name' => (string) $p->name, 'nameEn' => isset($p->nameEn) ? (string) $p->nameEn : '', 'price' => isset($p->price) ? (int) $p->price : 0,
                'stock' => isset($p->stock) ? (int) $p->stock : 0, 'description' => isset($p->description) ? (string) $p->description : '', 'descriptionEn' => isset($p->descriptionEn) ? (string) $p->descriptionEn : '',
                'link' => seo_site_url() . '/?p=' . rawurlencode(seo_product_slug($p)), 'images' => $imgs);
        }
    }
    $out['products'] = $prods;
    $raw = read_raw('content'); $ct = $raw ? json_decode($raw, true) : null; $news = array();
    if (is_array($ct) && isset($ct['news']['items']) && is_array($ct['news']['items'])) {
        foreach (array_slice($ct['news']['items'], 0, 50) as $n) {
            if (!is_array($n) || empty($n['id'])) { continue; }
            $news[] = array('id' => (string) $n['id'], 'kind' => isset($n['kind']) ? $n['kind'] : 'news', 'date' => isset($n['date']) ? $n['date'] : '',
                'title' => isset($n['title']['hu']) ? (string) $n['title']['hu'] : '', 'text' => isset($n['text']['hu']) ? (string) $n['text']['hu'] : '', 'link' => isset($n['link']) ? (string) $n['link'] : '');
        }
    }
    $out['news'] = $news;
    $raw = read_raw('ig_posts'); $ps = $raw ? json_decode($raw, true) : array();
    $out['posts'] = is_array($ps) ? array_slice($ps, 0, 30) : array();
    return $out;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') { try { ig_refresh_if_needed(); } catch (Throwable $e) { } json_out(ig_view()); }
require_post();
$d = body_json(20000);
$act = (is_object($d) && isset($d->action) && is_string($d->action)) ? $d->action : '';
try {
    if ($act === 'connect') {
        ig_connect(isset($d->token) && is_string($d->token) ? $d->token : '');
        json_out(ig_view());
    }
    if ($act === 'disconnect') {
        $c = ig_cfg(); $n = array(); if (isset($c['base'])) { $n['base'] = $c['base']; }
        ig_cfg_save($n);
        json_out(ig_view());
    }
    if ($act === 'refresh') { ig_refresh_if_needed(true); json_out(ig_view()); }
    if ($act === 'publish') {
        $cap = isset($d->caption) && is_string($d->caption) ? trim(str_replace("\r", '', $d->caption)) : '';
        if (mb_strlen($cap) > 2200) { fail(400, 'A szöveg legfeljebb 2200 karakter lehet.'); }
        if (preg_match_all('/#[\p{L}\p{N}_]+/u', $cap) > 30) { fail(400, 'Legfeljebb 30 hashtag lehet egy posztban.'); }
        $refs = array();
        foreach ((isset($d->images) && is_array($d->images) ? array_slice($d->images, 0, 10) : array()) as $im) {
            $r = is_object($im) && isset($im->ref) && is_string($im->ref) ? $im->ref : '';
            if ($r === '' || strlen($r) > 200 || preg_match('#^https?://#i', $r)) { continue; }
            $refs[] = array('ref' => $r, 'y' => is_object($im) && isset($im->y) && is_numeric($im->y) ? max(0, min(100, (float) $im->y)) : 50);
        }
        if (!$refs) { fail(400, 'Válassz legalább egy képet.'); }
        if (rl_blocked('igpub', 25, 86400)) { fail(429, 'Ma már sok poszt ment ki – próbáld holnap.'); }
        rl_hit('igpub', 86400);
        $meta = array('kind' => isset($d->kind) && in_array($d->kind, array('product', 'news', 'custom'), true) ? $d->kind : 'custom',
            'id' => isset($d->id) && is_string($d->id) ? mb_substr($d->id, 0, 80) : '', 'label' => isset($d->label) && is_string($d->label) ? mb_substr($d->label, 0, 120) : '');
        $rec = ig_publish($refs, $cap, $meta);
        $v = ig_view(); $v['post'] = $rec; json_out($v);
    }
} catch (Throwable $e) {
    fail(502, $e->getMessage());
}
fail(400, 'Ismeretlen művelet');
