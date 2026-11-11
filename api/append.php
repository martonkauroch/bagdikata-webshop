<?php
define('BK_API', 1);
require __DIR__ . '/_lib.php';
require __DIR__ . '/_mail.php';
ensure_data();
require_post();
$cfg = load_config();
require_site($cfg);
$key = isset($_GET['key']) ? (string) $_GET['key'] : '';
if ($key !== 'outbox' && $key !== 'withdrawals') { fail(400, 'Ismeretlen kulcs'); }
if (rl_blocked('append_' . $key, 40, 3600)) { fail(429, 'Túl sok kérés'); }
$d = body_json(60000);
if (!is_object($d) || !isset($d->items) || !is_array($d->items) || count($d->items) > 10) { fail(400, 'Érvénytelen adat'); }
$items = array();
foreach ($d->items as $it) {
    if (!is_object($it) || !isset($it->id) || !is_string($it->id) || strlen($it->id) > 80) { continue; }
    if (strlen(json_encode($it)) > 20000) { continue; }
    $items[] = $it;
}
rl_hit('append_' . $key, 3600);
$cap = ($key === 'outbox') ? 200 : 1000;
$added = array();
mutate_json($key, function ($cur) use ($items, $cap, &$added) {
    $cur = is_array($cur) ? $cur : array();
    $have = array();
    foreach ($cur as $o) { if (is_object($o) && isset($o->id)) { $have[$o->id] = true; } }
    $new = array();
    foreach ($items as $it) { if (!isset($have[$it->id])) { $new[] = $it; } }
    $added = $new;
    return array_slice(array_merge($new, $cur), 0, $cap);
});
if ($key === 'outbox' && count($added)) { mail_dispatch($cfg, $added, has_admin($cfg)); }
json_out(array('ok' => true));
