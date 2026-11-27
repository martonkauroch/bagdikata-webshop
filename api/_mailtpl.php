<?php
if (!defined('BK_API')) { exit; }

function mtpl_defaults() {
    return array(
        'logo' => '', 'logoWidth' => 56,
        'title' => array('hu' => 'Bagdi Kata', 'en' => 'Bagdi Kata'),
        'header' => array('hu' => 'Kézzel kötött melírozott sapkák', 'en' => 'Hand-knitted melange hats'),
        'footer' => array('hu' => 'Ez a Bagdi Kata webshop automatikus üzenete.', 'en' => 'This is an automatic message sent from the Bagdi Kata webshop.'),
        'order' => array(
            'subject' => array('hu' => 'Rendelés visszaigazolás {rendelesszam} – Bagdi Kata', 'en' => 'Order confirmation {rendelesszam} – Bagdi Kata'),
            'heading' => array('hu' => 'Köszönjük a rendelésedet', 'en' => 'Thank you for your order'),
            'body' => array('hu' => "Kedves {nev}!\n\nMegkaptuk a rendelésedet ({rendelesszam}). A részleteket alább találod. A csomag feladásáról e-mailben értesítünk; a számlát külön levélben küldjük.", 'en' => "Dear {nev},\n\nwe have received your order ({rendelesszam}). The details are below. We will contact you by e-mail about the shipping of your parcel; you will receive your invoice separately."),
            'closing' => array('hu' => "Ha kérdésed van, írj nekünk a webshop üzenetküldő űrlapján ({kapcsolat}), és add meg a rendelésszámot is.\n\nÜdvözlettel:\nBagdi Kata", 'en' => "If you have any question, send us a message using the form on our website ({kapcsolat}) and quote the order number.\n\nKind regards,\nBagdi Kata")),
        'contact' => array(
            'subject' => array('hu' => 'Üzenetedet megkaptuk – Bagdi Kata [{azonosito}]', 'en' => 'We received your message – Bagdi Kata [{azonosito}]'),
            'heading' => array('hu' => 'Köszönjük az üzenetedet', 'en' => 'Thank you for your message'),
            'body' => array('hu' => "Kedves {nev}!\n\nÜzenetedet megkaptuk, és a fenti azonosítón iktattuk. A megadott e-mail-címen válaszolunk, általában 1–2 munkanapon belül.\n\nEz automatikus visszaigazolás, kérjük, ne erre a levélre válaszolj. Ha szeretnél valamit hozzátenni, írj nekünk, és hivatkozz a fenti azonosítóra.\n\nÜdvözlettel:\nBagdi Kata", 'en' => "Dear {nev},\n\nwe have received your message and registered it under the reference number above. We will reply to this e-mail address as soon as possible, usually within 1–2 working days.\n\nThis is an automatic confirmation, please do not reply to it. If you want to add something, simply write to us and quote the reference number.\n\nKind regards,\nBagdi Kata")),
        'withdrawal' => array(
            'heading' => array('hu' => 'Az elállási nyilatkozatot megkaptuk', 'en' => 'We received your withdrawal'),
            'body' => array('hu' => '', 'en' => '')),
    );
}
function mtpl_clean($in) {
    $d = mtpl_defaults(); $in = is_array($in) ? $in : array(); $out = $d;
    $str = function ($v, $max) { return is_string($v) ? mb_substr(str_replace("\r", '', $v), 0, $max, 'UTF-8') : null; };
    foreach (array('title' => 60, 'header' => 120, 'footer' => 600) as $k => $max) {
        foreach (array('hu', 'en') as $l) { $x = isset($in[$k][$l]) ? $str($in[$k][$l], $max) : null; if ($x !== null) { $out[$k][$l] = $k === 'footer' ? trim($x) : trim(preg_replace('/\s+/u', ' ', $x)); } }
    }
    if (isset($in['logo']) && is_string($in['logo']) && preg_match('/^(|c:(png|jpg|gif|webp):[a-f0-9]{8,40})$/', $in['logo'])) { $out['logo'] = $in['logo']; }
    if (isset($in['logoWidth']) && is_numeric($in['logoWidth'])) { $out['logoWidth'] = max(30, min(220, (int) $in['logoWidth'])); }
    $lim = array('subject' => 150, 'heading' => 120, 'body' => 3000, 'closing' => 1500);
    foreach (array('order', 'contact', 'withdrawal') as $k) {
        foreach ($d[$k] as $f => $pair) {
            foreach (array('hu', 'en') as $l) {
                $x = isset($in[$k][$f][$l]) ? $str($in[$k][$f][$l], $lim[$f]) : null;
                if ($x === null) { continue; }
                $x = ($f === 'body' || $f === 'closing') ? trim($x) : trim(preg_replace('/\s+/u', ' ', $x));
                if ($x === '' && $f !== 'body' && $f !== 'closing') { continue; }
                if ($x === '' && $k !== 'withdrawal') { continue; }
                $out[$k][$f][$l] = $x;
            }
        }
    }
    return $out;
}
function mtpl_get() {
    $raw = read_raw('mail_tpl'); $a = $raw ? json_decode($raw, true) : null;
    if (is_array($a)) { array_walk_recursive($a, function (&$v) { if (is_string($v) && strpos($v, 'info@bagdikata.hu') !== false) { $v = str_replace(array('Ha kérdésed van, válaszolj erre a levélre, vagy írj az info@bagdikata.hu címre a rendelésszám megadásával.', 'If you have any question, reply to this e-mail or write to info@bagdikata.hu and quote the order number.'), array('Ha kérdésed van, írj nekünk a webshop üzenetküldő űrlapján ({kapcsolat}), és add meg a rendelésszámot is.', 'If you have any question, send us a message using the form on our website ({kapcsolat}) and quote the order number.'), $v); } }); }
    return mtpl_clean($a);
}
function mtpl_contact_url($en) { return mail_site_url() . ($en ? '/#/en/kapcsolat' : '/#/kapcsolat'); }
function mtpl_fill($s, $vars) { if (!isset($vars['{kapcsolat}'])) { $vars['{kapcsolat}'] = mtpl_contact_url(false); } return strtr((string) $s, $vars); }
function mtpl_lines($s) { $s = (string) $s; return $s === '' ? array() : preg_split('/\n/', $s); }
function mtpl_frame_begin($T, $en) {
    $L = $en ? 'en' : 'hu';
    $GLOBALS['bk_mail_header_text'] = trim($T['header'][$L]);
    $GLOBALS['bk_mail_title'] = trim($T['title'][$L]);
    $GLOBALS['bk_mail_logo_w'] = (int) $T['logoWidth'];
    $ft = trim($T['footer'][$L]);
    $GLOBALS['bk_mail_footer_html'] = $ft !== '' ? '<p style="margin:0 0 6px">' . nl2br(htmlspecialchars($ft)) . '</p>' : '';
    $GLOBALS['bk_mail_footer_text'] = $ft;
}
function mtpl_frame_end() { unset($GLOBALS['bk_mail_header_text'], $GLOBALS['bk_mail_title'], $GLOBALS['bk_mail_footer_html'], $GLOBALS['bk_mail_footer_text'], $GLOBALS['bk_mail_logo_w']); }
function mtpl_logo_file($logo) { return preg_match('/^c:(png|jpg|gif|webp):([a-f0-9]{8,40})$/', (string) $logo, $m) ? array(DATA_DIR . '/mail-logo.' . $m[1], $m[1], $m[2]) : null; }
function mtpl_logo_url_for($logo) {
    $f = mtpl_logo_file($logo);
    return ($f && is_file($f[0])) ? 'api/maillogo.php?v=' . substr($f[2], 0, 10) : 'mail-logo.png';
}
function mtpl_logo() {
    static $c = null;
    if ($c !== null) { return $c; }
    $raw = read_raw('mail_tpl'); $t = $raw ? json_decode($raw, true) : null;
    $w = (is_array($t) && isset($t['logoWidth']) && is_numeric($t['logoWidth'])) ? max(30, min(220, (int) $t['logoWidth'])) : 56;
    $f = is_array($t) && isset($t['logo']) ? mtpl_logo_file($t['logo']) : null;
    $url = ($f && is_file($f[0])) ? mail_site_url() . '/assets/mail-logo-c.' . $f[1] . '?v=' . substr($f[2], 0, 10) : mail_site_url() . '/mail-logo.png';
    return $c = array($url, $w);
}
function mtpl_name($name, $en) { $name = trim((string) $name); return $name !== '' ? $name : ($en ? 'Customer' : 'Vásárló'); }
