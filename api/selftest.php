<?php
define('BK_API', 1);
require __DIR__ . '/_lib.php';
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
ensure_data();
$rows = array();
$rows[] = array('PHP verzió (7.4 vagy újabb kell)', PHP_VERSION, version_compare(PHP_VERSION, '7.4.0', '>='));
$rows[] = array('json bővítmény', function_exists('json_encode') ? 'van' : 'nincs', function_exists('json_encode'));
$rows[] = array('password_hash / password_verify', function_exists('password_hash') ? 'van' : 'nincs', function_exists('password_hash'));
$rows[] = array('random_bytes', function_exists('random_bytes') ? 'van' : 'nincs', function_exists('random_bytes'));
$rows[] = array('getimagesizefromstring (képfeltöltéshez)', function_exists('getimagesizefromstring') ? 'van' : 'nincs', function_exists('getimagesizefromstring'));
$w = is_dir(DATA_DIR) && is_writable(DATA_DIR);
$rows[] = array('api/data mappa írható', $w ? 'igen' : 'NEM – állítsd 755/775-re', $w);
$ok = false;
if ($w) { $t = DATA_DIR . '/selftest.tmp'; $ok = (@file_put_contents($t, 'ok') !== false) && (@file_get_contents($t) === 'ok'); @unlink($t); }
$rows[] = array('fájl írás/olvasás próba', $ok ? 'sikeres' : 'sikertelen', $ok);
$rows[] = array('HTTPS', is_https() ? 'igen' : 'NEM – használd a https:// címet', is_https());
$rows[] = array('Beállítva (config)', load_config() ? 'igen' : 'NEM – töltsd fel az api/data/config.php fájlt', (bool) load_config());
?><!doctype html><html lang="hu"><head><meta charset="utf-8"><meta name="robots" content="noindex"><title>Önellenőrzés</title>
<style>body{font-family:system-ui,sans-serif;max-width:40rem;margin:2rem auto;padding:0 1rem}td{padding:.4rem .8rem;border-bottom:1px solid #ddd}.ok{color:#0a7a3a}.bad{color:#b00020;font-weight:700}</style></head><body>
<h1>Önellenőrzés</h1><table>
<?php foreach ($rows as $r) { echo '<tr><td>' . htmlspecialchars($r[0]) . '</td><td class="' . ($r[2] ? 'ok' : 'bad') . '">' . htmlspecialchars((string) $r[1]) . '</td></tr>'; } ?>
</table><p>Ha minden sor zöld, a telepítés rendben van. Ezt a fájlt (selftest.php) a beállítás után töröld.</p></body></html>
