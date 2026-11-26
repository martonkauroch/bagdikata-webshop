<?php
define('BK_API', 1);
require __DIR__ . '/_lib.php';
require __DIR__ . '/_newsletter.php';
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Robots-Tag: noindex, nofollow');
$cfg = load_config();
$home = mail_site_url() . '/';
$parse = function ($v) { return (is_string($v) && preg_match('/^([a-f0-9]{12})\.([a-f0-9]{16})\.([a-f0-9]{16})$/', $v, $m)) ? array($m[1], $m[2], $m[3]) : null; };
$camp_url = function ($cid) {
    $raw = read_raw('nl_campaigns'); $a = $raw ? json_decode($raw, true) : array();
    foreach ((is_array($a) ? $a : array()) as $c) {
        if (isset($c['id'], $c['ctaUrl']) && $c['id'] === $cid && is_string($c['ctaUrl']) && preg_match('#^https://[^\s<>"]{4,300}$#i', $c['ctaUrl'])) { return $c['ctaUrl']; }
    }
    return '';
};
if (isset($_GET['c'])) {
    $p = $parse($_GET['c']);
    $target = '';
    if ($cfg && $p && hash_equals(nl_trk_sig($cfg, array('c', $p[0], $p[1])), $p[2])) {
        $target = $camp_url($p[0]);
        if ($target !== '' && !rl_blocked('nltrk', 300, 3600)) { rl_hit('nltrk', 3600); try { nl_stat_hit('c', $p[0], $p[1], $target); } catch (Throwable $e) { } }
    }
    header('Referrer-Policy: no-referrer');
    header('Location: ' . ($target !== '' ? $target : $home), true, 302);
    exit;
}
$p = isset($_GET['o']) ? $parse($_GET['o']) : null;
if ($cfg && $p && hash_equals(nl_trk_sig($cfg, array('o', $p[0], $p[1])), $p[2]) && !rl_blocked('nltrk', 300, 3600)) {
    rl_hit('nltrk', 3600);
    try { nl_stat_hit('o', $p[0], $p[1]); } catch (Throwable $e) { }
}
$px = __DIR__ . '/../assets/nl-px.gif';
header('Content-Type: image/gif');
header('Content-Length: ' . filesize($px));
readfile($px);
