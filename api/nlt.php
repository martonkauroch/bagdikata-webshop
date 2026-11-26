<?php
define('BK_API', 1);
require __DIR__ . '/_lib.php';
require __DIR__ . '/_newsletter.php';
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Robots-Tag: noindex, nofollow');
$cfg = load_config();
$parse = function ($v) { return (is_string($v) && preg_match('/^([a-f0-9]{12})\.([a-f0-9]{16})\.([a-f0-9]{16})$/', $v, $m)) ? array($m[1], $m[2], $m[3]) : null; };
if (isset($_GET['c'])) {
    $p = $parse($_GET['c']);
    $u = isset($_GET['u']) && is_string($_GET['u']) ? base64_decode(strtr($_GET['u'], '-_', '+/'), true) : false;
    $ok = $cfg && $p && is_string($u) && preg_match('#^https://[^\s<>"]{4,300}$#i', $u) && hash_equals(nl_trk_sig($cfg, array('c', $p[0], $p[1], $u)), $p[2]);
    if (!$ok) { header('Location: ' . mail_site_url() . '/', true, 302); exit; }
    if (!rl_blocked('nltrk', 300, 3600)) { rl_hit('nltrk', 3600); try { nl_stat_hit('c', $p[0], $p[1], $u); } catch (Throwable $e) { } }
    header('Referrer-Policy: no-referrer');
    header('Location: ' . $u, true, 302);
    exit;
}
$p = isset($_GET['o']) ? $parse($_GET['o']) : null;
if ($cfg && $p && hash_equals(nl_trk_sig($cfg, array('o', $p[0], $p[1])), $p[2]) && !rl_blocked('nltrk', 300, 3600)) {
    rl_hit('nltrk', 3600);
    try { nl_stat_hit('o', $p[0], $p[1]); } catch (Throwable $e) { }
}
header('Content-Type: image/gif');
echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
