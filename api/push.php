<?php
define('BK_API', 1);
require __DIR__ . '/_lib.php';
require __DIR__ . '/_push.php';
ensure_data();
$cfg = load_config();
if (!$cfg || !has_admin($cfg)) { fail(401, 'Admin belépés szükséges'); }
header('Cache-Control: no-store');

function push_view() {
    $list = array();
    foreach (push_subs() as $s) {
        if (!isset($s['id'])) { continue; }
        $list[] = array('id' => $s['id'], 'label' => isset($s['label']) ? $s['label'] : '', 'createdAt' => isset($s['createdAt']) ? $s['createdAt'] : null,
            'lastOkAt' => isset($s['lastOkAt']) ? $s['lastOkAt'] : null, 'ep' => substr(hash('sha256', (string) $s['endpoint']), 0, 16));
    }
    return array('ok' => true, 'key' => push_vapid()['pub'], 'devices' => $list, 'prefs' => push_prefs());
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') { json_out(push_view()); }
require_post();
$d = body_json(6000);
$act = (is_object($d) && isset($d->action) && is_string($d->action)) ? $d->action : '';

if ($act === 'subscribe') {
    $s = isset($d->sub) && is_object($d->sub) ? $d->sub : null;
    $ep = $s && isset($s->endpoint) ? $s->endpoint : '';
    $k = $s && isset($s->keys) && is_object($s->keys) ? $s->keys : null;
    $p256 = $k && isset($k->p256dh) && is_string($k->p256dh) ? $k->p256dh : '';
    $auth = $k && isset($k->auth) && is_string($k->auth) ? $k->auth : '';
    if (!push_endpoint_ok($ep)) { fail(400, 'Ez a böngésző értesítési szolgáltatása nem támogatott.'); }
    if (!preg_match('/^[A-Za-z0-9_-]{80,100}$/', $p256) || !preg_match('/^[A-Za-z0-9_-]{16,32}$/', $auth)) { fail(400, 'Hibás eszközkulcs.'); }
    $label = isset($d->label) && is_string($d->label) ? trim(preg_replace('/[\x00-\x1f<>]/u', '', $d->label)) : '';
    $label = mb_substr($label !== '' ? $label : 'Eszköz', 0, 60);
    $id = '';
    mutate_json('push_subs', function ($cur) use ($ep, $p256, $auth, $label, &$id) {
        $a = is_array($cur) ? json_decode(json_encode($cur), true) : array();
        if (!is_array($a)) { $a = array(); }
        foreach ($a as $i => $s) {
            if (isset($s['endpoint']) && $s['endpoint'] === $ep) { $a[$i]['p256dh'] = $p256; $a[$i]['auth'] = $auth; $a[$i]['label'] = $label; $id = $s['id']; return array_values($a); }
        }
        if (count($a) >= 20) { array_shift($a); }
        $id = bin2hex(random_bytes(6));
        $a[] = array('id' => $id, 'endpoint' => $ep, 'p256dh' => $p256, 'auth' => $auth, 'label' => $label, 'createdAt' => date('c'), 'lastOkAt' => null);
        return array_values($a);
    });
    @chmod(DATA_DIR . '/push_subs.json', 0600);
    $v = push_view(); $v['id'] = $id; json_out($v);
}

if ($act === 'delete') {
    $id = isset($d->id) && is_string($d->id) ? $d->id : '';
    $ep = isset($d->endpoint) && is_string($d->endpoint) ? $d->endpoint : '';
    mutate_json('push_subs', function ($cur) use ($id, $ep) {
        $a = is_array($cur) ? json_decode(json_encode($cur), true) : array();
        if (!is_array($a)) { $a = array(); }
        return array_values(array_filter($a, function ($s) use ($id, $ep) { return !(($id !== '' && isset($s['id']) && $s['id'] === $id) || ($ep !== '' && isset($s['endpoint']) && $s['endpoint'] === $ep)); }));
    });
    json_out(push_view());
}

if ($act === 'prefs') {
    $p = push_prefs();
    $in = isset($d->prefs) && is_object($d->prefs) ? $d->prefs : new stdClass();
    foreach (array_keys($p) as $k) { if (isset($in->$k)) { $p[$k] = (bool) $in->$k; } }
    write_raw('push_prefs', json_encode($p));
    json_out(push_view());
}

if ($act === 'test') {
    $id = isset($d->id) && is_string($d->id) && $d->id !== '' ? $d->id : null;
    $res = push_send_all(array('t' => 'Próbaértesítés – Bagdi Kata', 'b' => 'Ha ezt látod, a telefonos értesítések működnek.', 'u' => '/#/admin/beallitasok', 'g' => 'test', 'y' => 'test'), $id);
    $v = push_view(); $v['results'] = $res; json_out($v);
}

fail(400, 'Ismeretlen művelet');
