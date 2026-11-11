<?php
// Levélküldés diagnosztika: csak belépett adminnak. Használat után töröld!
define('BK_API', 1);
require __DIR__ . '/_lib.php';
require __DIR__ . '/_mail.php';
require __DIR__ . '/_report.php';
ensure_data();
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
$cfg = load_config();
$rows = array();
function row(&$rows, $n, $v, $ok) { $rows[] = array($n, $v, $ok); }
if (!$cfg || !has_admin($cfg)) {
    http_response_code(401);
    echo '<!doctype html><meta charset="utf-8"><body style="font-family:sans-serif;max-width:40rem;margin:2rem auto"><h2>Előbb lépj be az adminba</h2><p>Nyisd meg a webshopot ugyanebben a böngészőben, add meg az oldaljelszót, lépj be az Adminba, majd frissítsd ezt az oldalt.</p>';
    exit;
}
row($rows, 'PHP verzió', PHP_VERSION, true);
$ap = @file_get_contents(__DIR__ . '/append.php');
row($rows, 'append.php új változat (mail_dispatch benne van)', ($ap !== false && strpos($ap, 'mail_dispatch') !== false) ? 'igen' : 'NEM – töltsd fel újra az api/append.php fájlt', $ap !== false && strpos($ap, 'mail_dispatch') !== false);
$mf = DATA_DIR . '/mail.php';
row($rows, 'api/data/mail.php megvan', is_file($mf) ? 'igen' : 'NEM', is_file($mf));
$parse = true; $msg = 'rendben';
if (is_file($mf)) { try { $c = include $mf; if (!is_array($c)) { $parse = false; $msg = 'a fájl nem tömböt ad vissza'; } } catch (Throwable $e) { $parse = false; $msg = 'HIBA: ' . $e->getMessage(); } }
row($rows, 'mail.php beolvasható', $msg, $parse);
$mc = mail_cfg();
$smtp = !empty($mc['smtp']) && is_array($mc['smtp']) && !empty($mc['smtp']['host']);
row($rows, 'Küldési mód', $smtp ? 'SMTP: ' . $mc['smtp']['host'] . ':' . (isset($mc['smtp']['port']) ? $mc['smtp']['port'] : '?') . ' (' . (isset($mc['smtp']['secure']) ? $mc['smtp']['secure'] : 'ssl') . '), felhasználó: ' . (isset($mc['smtp']['user']) ? $mc['smtp']['user'] : '–') : 'a tárhely mail() függvénye', true);
row($rows, 'Feladó (alapértelmezett)', mail_default_sender($mc), true);
row($rows, 'mail() függvény elérhető', function_exists('mail') ? 'igen' : 'NEM', function_exists('mail'));
row($rows, 'api/data írható', is_writable(DATA_DIR) ? 'igen' : 'NEM – állítsd 755/775-re', is_writable(DATA_DIR));
if ($smtp) {
    $h = (string) $mc['smtp']['host']; $ip = @gethostbyname($h);
    row($rows, 'SMTP szerver neve feloldható', (($ip !== $h) || filter_var($h, FILTER_VALIDATE_IP) !== false) ? $h . ' → ' . $ip : 'NEM: a „' . $h . '" név nem létezik. Használd a cPanel Connect Devices oldalán látható szervernevet.', ($ip !== $h) || filter_var($h, FILTER_VALIDATE_IP) !== false);
}
$sent = null; $err = '';
if (isset($_GET['kuldes'])) {
    try {
        mail_send(array($cfg['admin_email']), 'Teszt levél a webshopból', "Ez egy teszt levél.\r\nHa ezt olvasod, a levélküldés működik.", '<p>Ez egy teszt levél.</p><p>Ha ezt olvasod, a levélküldés működik.</p>', $cfg['admin_email']);
        $sent = true; mail_log('OK diagnosztikai teszt -> ' . $cfg['admin_email']);
    } catch (Throwable $e) { $sent = false; $err = $e->getMessage(); mail_log('HIBA diagnosztikai teszt: ' . mail_clean($err, 200)); }
}
$log = is_file(DATA_DIR . '/mail.log') ? implode("\n", array_slice(file(DATA_DIR . '/mail.log', FILE_IGNORE_NEW_LINES), -12)) : '(még nincs napló)';
?><!doctype html><html lang="hu"><head><meta charset="utf-8"><meta name="robots" content="noindex"><title>Levél diagnosztika</title>
<style>body{font-family:system-ui,sans-serif;max-width:46rem;margin:2rem auto;padding:0 1rem}td{padding:.4rem .8rem;border-bottom:1px solid #ddd;vertical-align:top}.ok{color:#0a7a3a}.bad{color:#b00020;font-weight:700}pre{background:#f4f4f4;padding:.8rem;overflow:auto}a.b{display:inline-block;background:#222;color:#fff;padding:.6rem 1rem;border-radius:6px;text-decoration:none}</style></head><body>
<h1>Levél diagnosztika</h1><table>
<?php foreach ($rows as $r) { echo '<tr><td>' . htmlspecialchars($r[0]) . '</td><td class="' . ($r[2] ? 'ok' : 'bad') . '">' . htmlspecialchars((string) $r[1]) . '</td></tr>'; } ?>
</table>
<?php if ($sent === true) { echo '<p class="ok"><b>A levelet a szerver elfogadta</b> (' . htmlspecialchars($cfg['admin_email']) . '). Nézd meg a postaládát és a spam mappát is.</p>'; }
if ($sent === false) { echo '<p class="bad">Küldési hiba: ' . htmlspecialchars($err) . '</p>'; } ?>
<p><a class="b" href="?kuldes=1">Teszt levél küldése az admin címre</a></p>
<?php
$ckey = bk_cron_key($cfg);
$base = (isset($_SERVER['HTTP_HOST']) ? 'https://' . $_SERVER['HTTP_HOST'] : mail_site_url());
$cronUrl = $base . '/api/cron_daily.php?key=' . $ckey;
$lastF = DATA_DIR . '/daily_last.json';
$lastS = is_file($lastF) ? (string) file_get_contents($lastF) : '(még nem ment ki automatikusan)';
?>
<h3>Napi jelentés időzítése (cron)</h3>
<p>Az automatikus napi jelentéshez a cPanelben egy időzített feladat kell: <b>cPanel → Advanced → Cron Jobs → Add New Cron Job</b>, gyakoriság: <b>percenként vagy 10 percenként</b> (pl. <code>*/10 * * * *</code>), parancs az alábbiak egyike:</p>
<pre>/usr/local/bin/php <?php echo htmlspecialchars(__DIR__ . '/cron_daily.php'); ?></pre>
<p>vagy, ha az nem működik:</p>
<pre>wget -q -O /dev/null "<?php echo htmlspecialchars($cronUrl); ?>"</pre>
<p>A feladat a Statisztika oldalon beállított időpont után naponta egyszer elküldi az előző napi jelentést. Utolsó automatikus küldés: <code><?php echo htmlspecialchars($lastS); ?></code></p>
<p><a class="b" href="<?php echo htmlspecialchars($cronUrl . '&force=1'); ?>">Napi jelentés küldése most (próba)</a></p>
<h3>Utolsó naplósorok</h3><pre><?php echo htmlspecialchars($log); ?></pre>
<p>Használat után töröld az <code>api/mailtest.php</code> fájlt.</p></body></html>
