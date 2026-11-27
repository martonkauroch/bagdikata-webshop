<?php
define('BK_API', 1);
require __DIR__ . '/_lib.php';
require __DIR__ . '/_mail.php';
$raw = read_raw('mail_tpl'); $t = $raw ? json_decode($raw, true) : null;
$f = is_array($t) && isset($t['logo']) ? mtpl_logo_file($t['logo']) : null;
if (!$f || !is_file($f[0])) { header('Location: ' . mail_site_url() . '/mail-logo.png', true, 302); exit; }
$types = array('png' => 'image/png', 'jpg' => 'image/jpeg', 'gif' => 'image/gif', 'webp' => 'image/webp');
header('Content-Type: ' . $types[$f[1]]);
header('Cache-Control: public, max-age=604800');
header('Content-Length: ' . filesize($f[0]));
readfile($f[0]);
