<?php
if (!defined('BK_API')) { exit; }

function mtpl_defaults() {
    return array(
        'header' => array('hu' => '', 'en' => ''),
        'footer' => array('hu' => '', 'en' => ''),
        'order' => array(
            'subject' => array('hu' => 'Rendelés visszaigazolás {rendelesszam} – Bagdi Kata', 'en' => 'Order confirmation {rendelesszam} – Bagdi Kata'),
            'heading' => array('hu' => 'Köszönjük a rendelésedet', 'en' => 'Thank you for your order'),
            'body' => array('hu' => "Kedves {nev}!\n\nMegkaptuk a rendelésedet ({rendelesszam}). A részleteket alább találod. A csomag feladásáról e-mailben értesítünk; a számlát külön levélben küldjük.", 'en' => "Dear {nev},\n\nwe have received your order ({rendelesszam}). The details are below. We will contact you by e-mail about the shipping of your parcel; you will receive your invoice separately."),
            'closing' => array('hu' => "Ha kérdésed van, válaszolj erre a levélre, vagy írj az info@bagdikata.hu címre a rendelésszám megadásával.\n\nÜdvözlettel:\nBagdi Kata", 'en' => "If you have any question, reply to this e-mail or write to info@bagdikata.hu and quote the order number.\n\nKind regards,\nBagdi Kata")),
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
    foreach (array('header' => 120, 'footer' => 600) as $k => $max) {
        foreach (array('hu', 'en') as $l) { $x = isset($in[$k][$l]) ? $str($in[$k][$l], $max) : null; if ($x !== null) { $out[$k][$l] = $k === 'header' ? trim(preg_replace('/\s+/u', ' ', $x)) : trim($x); } }
    }
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
function mtpl_get() { $raw = read_raw('mail_tpl'); return mtpl_clean($raw ? json_decode($raw, true) : null); }
function mtpl_fill($s, $vars) { return strtr((string) $s, $vars); }
function mtpl_lines($s) { $s = (string) $s; return $s === '' ? array() : preg_split('/\n/', $s); }
function mtpl_frame_begin($T, $en) {
    $L = $en ? 'en' : 'hu';
    $hd = trim($T['header'][$L]);
    if ($hd !== '') { $GLOBALS['bk_mail_header_text'] = $hd; }
    $ft = trim($T['footer'][$L]);
    $auto = $en ? 'This is an automatic message sent from the Bagdi Kata webshop.' : 'Ez a Bagdi Kata webshop automatikus üzenete.';
    if ($ft !== '') {
        $GLOBALS['bk_mail_footer_html'] = '<p style="margin:0 0 10px">' . nl2br(htmlspecialchars($ft)) . '</p><p style="margin:0 0 4px">' . htmlspecialchars($auto) . '</p>';
        $GLOBALS['bk_mail_footer_text'] = $ft;
    }
}
function mtpl_frame_end() { unset($GLOBALS['bk_mail_header_text'], $GLOBALS['bk_mail_footer_html'], $GLOBALS['bk_mail_footer_text']); }
function mtpl_name($name, $en) { $name = trim((string) $name); return $name !== '' ? $name : ($en ? 'Customer' : 'Vásárló'); }
