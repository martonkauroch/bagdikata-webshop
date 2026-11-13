<?php
// Kapcsolatfelvételi űrlap: a látogató üzenete a mail.php-ban megadott "contact" címre (info@) megy.
define('BK_API', 1);
require __DIR__ . '/_lib.php';
require __DIR__ . '/_mail.php';
ensure_data();
require_post();
$cfg = load_config();
if (!$cfg) { fail(503, 'A szerver még nincs beállítva.'); }
// a kapcsolatfelvétel az oldaljelszó nélkül is elérhető (a „Hamarosan” oldalról)
if (rl_blocked('contact', 5, 3600)) { fail(429, 'Túl sok üzenet ebből a címről. Próbáld újra később.'); }
$d = body_json(12000);
if (!is_object($d)) { fail(400, 'Érvénytelen adat'); }
function cf_str($d, $k, $max) { return (isset($d->$k) && is_string($d->$k)) ? mb_substr(trim($d->$k), 0, $max, 'UTF-8') : ''; }
// rejtett csapdamező: ha kitöltötték, robot volt; úgy teszünk, mintha sikerült volna
if (cf_str($d, 'website', 200) !== '') { json_out(array('ok' => true)); }
if (!bot_verify(isset($d->captcha) ? $d->captcha : '')) { fail(400, 'A robotellenőrzés nem sikerült. Frissítsd az oldalt és próbáld újra.'); }
$name = cf_str($d, 'name', 120);
$email = cf_str($d, 'email', 160);
$msg = cf_str($d, 'message', 3000);
$lang = (cf_str($d, 'locale', 5) === 'en') ? 'en' : 'hu';
if ($name === '' || !mail_valid($email) || mb_strlen($msg, 'UTF-8') < 5) { fail(400, 'Hiányzó vagy érvénytelen adat'); }
rl_hit('contact', 3600);
$mc = mail_cfg();
$to = mail_route_list($mc, 'contact');
if (!count($to)) { mail_log('HIBA contact: nincs "contact" cím a mail.php-ban'); fail(503, 'A kapcsolatfelvétel most nem érhető el.'); }
$sender = mail_sender_for($mc, 'contact');
$ref = 'K-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
$item = (object) array(
    'subject' => 'Üzenet a webshopból – ' . mail_clean($name, 80) . ' [' . $ref . ']',
    'ref' => $ref,
    'preheader' => 'Válaszolj erre a levélre, a válasz a feladóhoz megy.',
    'blocks' => array((object) array('heading' => 'Új üzenet érkezett a weboldalról', 'lines' => array(
        'Azonosító: ' . $ref,
        'Név: ' . mail_clean($name, 120),
        'E-mail: ' . $email,
        'Idő: ' . date('Y-m-d H:i:s'),
        '',
        $msg,
    ))),
);
list($text, $html) = mail_render($item);
try {
    mail_send($to, $item->subject, $text, $html, $email, $sender);
    mail_log('OK contact ' . $ref . ' -> ' . count($to) . ' cím');
    try { mail_confirm_contact($email, $name, $lang, $ref); mail_log('OK contact-visszaigazolas ' . $ref); }
    catch (Throwable $e) { mail_log('HIBA contact-visszaigazolas: ' . mail_clean($e->getMessage(), 200)); }
    json_out(array('ok' => true));
} catch (Throwable $e) {
    mail_log('HIBA contact: ' . mail_clean($e->getMessage(), 200));
    fail(502, 'Az üzenetet most nem sikerült elküldeni.');
}
