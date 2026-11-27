<?php
if (!defined('BK_API')) { exit; }
require_once __DIR__ . '/_mail.php';

define('NL_CONSENT_VER', 'v1');

function nl_list() {
    $raw = read_raw('newsletter');
    $a = $raw !== null ? json_decode($raw, true) : array();
    return is_array($a) ? array_values($a) : array();
}
function nl_token($cfg, $id, $purpose) { return substr(hash_hmac('sha256', $purpose . '|' . $id, $cfg['secret']), 0, 32); }
function nl_token_ok($cfg, $id, $purpose, $tok) {
    return is_string($id) && preg_match('/^[a-f0-9]{16}$/', $id) && is_string($tok) && hash_equals(nl_token($cfg, $id, $purpose), $tok);
}
function nl_link($cfg, $id, $purpose) {
    $k = ($purpose === 'confirm') ? 'c' : 'u';
    return mail_site_url() . '/api/newsletter.php?' . $k . '=' . $id . '&t=' . nl_token($cfg, $id, $purpose);
}
function nl_privacy_url($lang) { return mail_site_url() . ($lang === 'en' ? '/#/en/hirlevel' : '/#/hirlevel'); }
function nl_sender() { $mc = mail_cfg(); return mail_sender_for($mc, 'newsletter'); }

function nl_subscribe($cfg, $email, $name, $lang, $source) {
    $email = trim((string) $email);
    if (!mail_valid($email)) { return 'invalid'; }
    $key = strtolower($email);
    $name = mail_clean($name, 80);
    $lang = ($lang === 'en') ? 'en' : 'hu';
    $source = in_array($source, array('checkout', 'footer', 'landing'), true) ? $source : 'footer';
    $send = null;
    mutate_json('newsletter', function ($cur) use ($email, $key, $name, $lang, $source, &$send) {
        $a = is_array($cur) ? json_decode(json_encode($cur), true) : array();
        if (!is_array($a)) { $a = array(); }
        $now = date('c');
        foreach ($a as $i => $s) {
            if (!isset($s['email']) || strtolower($s['email']) !== $key) { continue; }
            $st = isset($s['status']) ? $s['status'] : 'pending';
            if ($st === 'confirmed') { return array_values($a); }
            if ($st === 'pending') {
                $last = isset($s['lastMailAt']) && $s['lastMailAt'] ? strtotime($s['lastMailAt']) : 0;
                if (time() - $last > 600) { $a[$i]['lastMailAt'] = $now; $send = $a[$i]; }
                return array_values($a);
            }
            $a[$i]['status'] = 'pending'; $a[$i]['consentAt'] = $now; $a[$i]['lastMailAt'] = $now; $a[$i]['source'] = $source;
            $a[$i]['lang'] = $lang; $a[$i]['unsubscribedAt'] = null; $a[$i]['confirmedAt'] = null; $a[$i]['remindedAt'] = null; $a[$i]['consentVer'] = NL_CONSENT_VER;
            if ($name !== '') { $a[$i]['name'] = $name; }
            $send = $a[$i];
            return array_values($a);
        }
        if (count($a) >= 20000) { return array_values($a); }
        $new = array('id' => bin2hex(random_bytes(8)), 'email' => $email, 'name' => $name, 'lang' => $lang, 'status' => 'pending', 'source' => $source,
            'createdAt' => $now, 'consentAt' => $now, 'lastMailAt' => $now, 'confirmedAt' => null, 'unsubscribedAt' => null, 'consentVer' => NL_CONSENT_VER);
        $a[] = $new; $send = $new;
        return array_values($a);
    });
    if ($send) {
        nl_send_confirm($cfg, $send);
        try {
            require_once __DIR__ . '/_push.php';
            $srcL = array('landing' => '„Hamarosan” oldal', 'checkout' => 'rendeléskor', 'footer' => 'webshop');
            push_notify('nl', 'Új hírlevél-feliratkozás', $email . ' · ' . $srcL[$source] . ' (megerősítésre vár)', 'Forrás: ' . $srcL[$source] . ' (megerősítésre vár)', '/#/admin/hirlevel', 'nl-' . $send['id']);
        } catch (Throwable $e) { }
    }
    return 'ok';
}

function nl_tpl_defaults() {
    return array(
        'header' => array('hu' => '', 'en' => ''),
        'footer' => array('hu' => '', 'en' => ''),
        'confirm' => array(
            'subject' => array('hu' => 'Erősítsd meg a hírlevél-feliratkozásodat – Bagdi Kata', 'en' => 'Please confirm your newsletter subscription – Bagdi Kata'),
            'heading' => array('hu' => 'Feliratkozás megerősítése', 'en' => 'Confirm your subscription'),
            'body' => array(
                'hu' => "Kedves {nev}!\n\nEzen az e-mail-címen ({email}) hírlevél-feliratkozást kezdeményeztek a Bagdi Kata webshopban. A feliratkozás csak akkor lép érvénybe, ha az alábbi gombbal megerősíted.\n\nHa nem te kérted, egyszerűen hagyd figyelmen kívül ezt a levelet – megerősítés nélkül nem küldünk hírlevelet.",
                'en' => "Dear {nev},\n\na newsletter subscription was requested for this e-mail address ({email}) at the Bagdi Kata webshop. It only takes effect once you confirm it with the button below.\n\nIf it was not you, simply ignore this message – we will not send you any newsletter without your confirmation."),
            'button' => array('hu' => 'Feliratkozás megerősítése', 'en' => 'Confirm subscription')),
        'welcome' => array('enabled' => false,
            'subject' => array('hu' => 'Köszönjük a feliratkozást – Bagdi Kata', 'en' => 'Thank you for subscribing – Bagdi Kata'),
            'heading' => array('hu' => 'Üdv a hírlevelünkön!', 'en' => 'Welcome to our newsletter!'),
            'body' => array(
                'hu' => "Kedves {nev}!\n\nKöszönjük, hogy megerősítetted a feliratkozásodat. Mostantól elsőként értesülsz az új kollekciókról, akciókról és a webshop híreiről.",
                'en' => "Dear {nev},\n\nthank you for confirming your subscription. From now on you will be the first to hear about new collections, offers and news from the shop."),
            'button' => array('hu' => 'Irány a webshop', 'en' => 'Visit the shop'),
            'url' => 'https://bagdikata.hu'),
        'unsub' => array('enabled' => false,
            'subject' => array('hu' => 'Leiratkoztál a hírlevélről – Bagdi Kata', 'en' => 'You have unsubscribed – Bagdi Kata'),
            'heading' => array('hu' => 'Sikeres leiratkozás', 'en' => 'Unsubscribed'),
            'body' => array(
                'hu' => "Kedves {nev}!\n\nA(z) {email} címet levettük a hírlevél-listánkról, több hírlevelet nem küldünk. Ha tévedésből történt, bármikor újra feliratkozhatsz a webshopban.",
                'en' => "Dear {nev},\n\nwe have removed {email} from our newsletter list and will not send you any more newsletters. If this was a mistake, you can subscribe again at any time in the shop."),
            'button' => array('hu' => '', 'en' => ''),
            'url' => ''),
    );
}
function nl_tpl_clean($in) {
    $d = nl_tpl_defaults();
    $in = is_array($in) ? $in : array();
    $str = function ($v, $max) { return is_string($v) ? mb_substr(str_replace("\r", '', $v), 0, $max, 'UTF-8') : null; };
    $out = $d;
    foreach (array('header' => 120, 'footer' => 600) as $k => $max) {
        foreach (array('hu', 'en') as $l) { $x = isset($in[$k][$l]) ? $str($in[$k][$l], $max) : null; if ($x !== null) { $out[$k][$l] = $k === 'header' ? trim(preg_replace('/\s+/u', ' ', $x)) : trim($x); } }
    }
    foreach (array('confirm', 'welcome', 'unsub') as $k) {
        if ($k !== 'confirm') { $out[$k]['enabled'] = !empty($in[$k]['enabled']); }
        foreach (array('subject' => 150, 'heading' => 120, 'body' => 3000, 'button' => 60) as $f => $max) {
            foreach (array('hu', 'en') as $l) {
                $x = isset($in[$k][$f][$l]) ? $str($in[$k][$f][$l], $max) : null;
                if ($x === null) { continue; }
                $x = $f === 'body' ? trim($x) : trim(preg_replace('/\s+/u', ' ', $x));
                if ($x === '' && ($f === 'subject' || $f === 'body' || ($k === 'confirm' && $f === 'button'))) { continue; }
                $out[$k][$f][$l] = $x;
            }
        }
        if ($k !== 'confirm' && isset($in[$k]['url']) && is_string($in[$k]['url'])) { $u = trim($in[$k]['url']); $out[$k]['url'] = preg_match('#^https://[^\s<>"]{4,300}$#i', $u) ? $u : ''; }
    }
    return $out;
}
function nl_tpl() { $raw = read_raw('nl_tpl'); return nl_tpl_clean($raw ? json_decode($raw, true) : null); }
function nl_frame_footer($tpl, $en) {
    $x = trim($tpl['footer'][$en ? 'en' : 'hu']);
    if ($x === '') { return array('', ''); }
    $h = '<p style="margin:0 0 10px">' . nl2br(htmlspecialchars($x)) . '</p>';
    return array($h, $x);
}
function nl_frame_begin($tpl, $en) {
    $GLOBALS['bk_mail_logo_only'] = true;
    $hd = trim($tpl['header'][$en ? 'en' : 'hu']);
    if ($hd !== '') { $GLOBALS['bk_mail_header_text'] = $hd; }
}
function nl_frame_end() { unset($GLOBALS['bk_mail_footer_html'], $GLOBALS['bk_mail_footer_text'], $GLOBALS['bk_mail_nocopy'], $GLOBALS['bk_mail_headers'], $GLOBALS['bk_mail_logo_only'], $GLOBALS['bk_mail_header_text']); }
function nl_sys_build($cfg, $kind, $s, $tpl = null) {
    $tpl = $tpl ? $tpl : nl_tpl();
    $T = $tpl[$kind];
    $en = (isset($s['lang']) && $s['lang'] === 'en'); $L = $en ? 'en' : 'hu';
    $name = (isset($s['name']) && trim((string) $s['name']) !== '') ? trim((string) $s['name']) : ($en ? 'Subscriber' : 'Érdeklődő');
    $fill = function ($x) use ($s, $name) { return strtr((string) $x, array('{nev}' => $name, '{name}' => $name, '{email}' => (string) $s['email'])); };
    $lines = preg_split('/\n/', $fill($T['body'][$L]));
    $blocks = array((object) array('heading' => $fill($T['heading'][$L]), 'lines' => $lines));
    $href = '';
    if ($kind === 'confirm') { $href = nl_link($cfg, $s['id'], 'confirm'); }
    elseif (!empty($T['url'])) { $href = $T['url']; }
    $lab = trim($T['button'][$L]);
    if ($href !== '' && $lab !== '') { $blocks[] = (object) array('links' => array((object) array('label' => $fill($lab), 'href' => $href))); }
    list($fh, $ft) = nl_frame_footer($tpl, $en);
    $priv = nl_privacy_url($L);
    if ($kind === 'confirm') {
        $p = $en ? 'You can withdraw your consent at any time, free of charge, using the unsubscribe link at the bottom of every newsletter. How we handle your data: ' : 'Hozzájárulásodat bármikor, ingyenesen visszavonhatod a hírlevelek alján lévő leiratkozás linkkel. Az adatkezelésről itt olvashatsz: ';
        $fh .= '<p style="margin:0 0 10px">' . htmlspecialchars($p) . '<a href="' . htmlspecialchars($priv) . '" style="color:#161314">' . htmlspecialchars($priv) . '</a></p>';
        $ft .= ($ft !== '' ? "\r\n\r\n" : '') . $p . $priv;
    } elseif ($kind === 'welcome') {
        $un = nl_link($cfg, $s['id'], 'unsub');
        $la = $en ? array('Unsubscribe', 'Privacy notice', 'You can unsubscribe at any time with the link below.') : array('Leiratkozás', 'Adatkezelési tájékoztató', 'Bármikor leiratkozhatsz az alábbi linkkel.');
        $fh .= '<p style="margin:0 0 10px">' . htmlspecialchars($la[2]) . ' <a href="' . htmlspecialchars($un) . '" style="color:#161314">' . $la[0] . '</a> &middot; <a href="' . htmlspecialchars($priv) . '" style="color:#161314">' . $la[1] . '</a></p>';
        $ft .= ($ft !== '' ? "\r\n\r\n" : '') . $la[2] . "\r\n" . $la[0] . ': ' . $un . "\r\n" . $la[1] . ': ' . $priv;
    } else {
        $a = $en ? 'This is an automatic message sent from the Bagdi Kata webshop.' : 'Ez a Bagdi Kata webshop automatikus üzenete.';
        $fh .= '<p style="margin:0 0 4px">' . htmlspecialchars($a) . '</p>';
        $ft .= ($ft !== '' ? "\r\n\r\n" : '') . $a;
    }
    nl_frame_begin($tpl, $en);
    $GLOBALS['bk_mail_footer_html'] = $fh;
    $GLOBALS['bk_mail_footer_text'] = $ft;
    if ($kind !== 'confirm') { $GLOBALS['bk_mail_nocopy'] = true; }
    $it = (object) array('subject' => $fill($T['subject'][$L]), 'lang' => $L, 'blocks' => $blocks);
    list($text, $html) = mail_render($it);
    nl_frame_end();
    return array($it->subject, $text, $html);
}
function nl_send_sys($cfg, $kind, $s, $tpl = null, $to = null, $prefix = '') {
    $kl = array('confirm' => 'megerősítő', 'welcome' => 'üdvözlő', 'unsub' => 'leiratkozás-visszaigazoló');
    try {
        list($subject, $text, $html) = nl_sys_build($cfg, $kind, $s, $tpl);
        if ($to !== null) { $GLOBALS['bk_mail_nocopy'] = true; }
        $GLOBALS['bk_copy_kind'] = 'newsletter';
        $sender = nl_sender();
        mail_send(array($to !== null ? $to : $s['email']), $prefix . $subject, $text, $html, $sender, $sender);
        unset($GLOBALS['bk_mail_nocopy'], $GLOBALS['bk_copy_kind']);
        mail_log('OK hírlevél-' . $kl[$kind] . ($prefix !== '' ? ' (teszt)' : '') . ' -> 1 cím');
        return true;
    } catch (Throwable $e) { unset($GLOBALS['bk_mail_nocopy'], $GLOBALS['bk_copy_kind']); mail_log('HIBA hírlevél-' . $kl[$kind] . ': ' . mail_clean($e->getMessage(), 120)); return false; }
}
function nl_send_confirm($cfg, $s) { return nl_send_sys($cfg, 'confirm', $s); }

function nl_remind_run($cfg, $max = 25) {
    $lock = DATA_DIR . '/nl_remind.lock';
    if (is_file($lock) && time() - filemtime($lock) < 600) { return 0; }
    @touch($lock);
    $now = time(); $due = array();
    mutate_json('newsletter', function ($cur) use ($now, $max, &$due) {
        $a = is_array($cur) ? json_decode(json_encode($cur), true) : array();
        if (!is_array($a)) { return $a; }
        foreach ($a as $i => $s) {
            if (count($due) >= $max) { break; }
            if (!isset($s['id'], $s['email']) || (isset($s['status']) ? $s['status'] : 'pending') !== 'pending' || !empty($s['remindedAt'])) { continue; }
            $c = !empty($s['consentAt']) ? strtotime($s['consentAt']) : 0;
            $l = !empty($s['lastMailAt']) ? strtotime($s['lastMailAt']) : $c;
            if (!$c || $now - $c > 30 * 86400 || $now - $l < 3 * 86400) { continue; }
            $due[] = array_merge($a[$i], array('prevMailAt' => isset($s['lastMailAt']) ? $s['lastMailAt'] : null));
            $a[$i]['remindedAt'] = date('c', $now); $a[$i]['lastMailAt'] = date('c', $now);
        }
        return array_values($a);
    });
    $n = 0;
    $failed = array();
    foreach ($due as $s) { if (nl_send_confirm($cfg, $s)) { $n++; } else { $failed[$s['id']] = $s['prevMailAt']; } }
    if ($failed) {
        mutate_json('newsletter', function ($cur) use ($failed) {
            $a = is_array($cur) ? json_decode(json_encode($cur), true) : array();
            if (!is_array($a)) { return $a; }
            foreach ($a as $i => $s) { if (isset($s['id']) && array_key_exists($s['id'], $failed)) { $a[$i]['remindedAt'] = null; $a[$i]['lastMailAt'] = $failed[$s['id']]; } }
            return array_values($a);
        });
    }
    if ($due) { mail_log('hírlevél-emlékeztető (3 napja meg nem erősített): ' . $n . '/' . count($due) . ' elküldve'); }
    return $n;
}

function nl_trk_sig($cfg, $parts) { return substr(hash_hmac('sha256', implode('|', $parts), $cfg['secret'] . '|nltrk'), 0, 16); }
function nl_stat_hit($kind, $cid, $sid, $url = '') {
    $cfg = load_config(); if (!$cfg) { return; }
    $h = substr(hash_hmac('sha256', $cid . '|' . $sid, $cfg['secret'] . '|nlu'), 0, 10);
    $now = date('c');
    mutate_json('nl_stats', function ($cur) use ($kind, $cid, $h, $url, $now) {
        $a = is_array($cur) || is_object($cur) ? json_decode(json_encode($cur), true) : array();
        if (!is_array($a)) { $a = array(); }
        if (!isset($a[$cid])) {
            if (count($a) >= 60) { $a = array_slice($a, -59, null, true); }
            $a[$cid] = array('o' => 0, 'uo' => array(), 'c' => 0, 'uc' => array(), 'l' => array(), 'fo' => null, 'lo' => null);
        }
        $e = &$a[$cid];
        if ($kind === 'o') { $e['o']++; if (!$e['fo']) { $e['fo'] = $now; } $e['lo'] = $now; }
        if (count($e['uo']) < 20000) { $e['uo'][$h] = 1; }
        if ($kind === 'c') {
            $e['c']++; if (count($e['uc']) < 20000) { $e['uc'][$h] = 1; }
            if ($url !== '' && (isset($e['l'][$url]) || count($e['l']) < 20)) { $e['l'][$url] = (isset($e['l'][$url]) ? (int) $e['l'][$url] : 0) + 1; }
        }
        return $a;
    });
}
function nl_product_card($cfg, $pid, $en, $track) {
    require_once __DIR__ . '/_seo.php';
    $p = seo_find_product($pid);
    if (!$p) { return null; }
    $site = mail_site_url(); $slug = seo_product_slug($p);
    $name = ($en && !empty($p->nameEn)) ? $p->nameEn : (isset($p->name) ? $p->name : $pid);
    $price = isset($p->price) && is_numeric($p->price) ? number_format((float) $p->price, 0, ',', ' ') . ' Ft' : '';
    if (isset($p->stock) && is_numeric($p->stock) && $p->stock <= 0) { $price = $en ? 'Sold out' : 'Elfogyott'; }
    $url = $site . '/?p=' . rawurlencode($slug) . ($en ? '&l=en' : '');
    if ($track) { $url = $site . '/api/nlt.php?c=' . $track[0] . '.' . $track[1] . '.' . nl_trk_sig($cfg, array('c', $track[0], $track[1], 'p:' . $p->id)) . '&p=' . rawurlencode($p->id) . ($en ? '&l=en' : ''); }
    $img = !empty($p->imageUrl) ? $site . '/assets/og-p/' . rawurlencode($slug) . '.jpg' : '';
    return (object) array('product' => (object) array('name' => $name, 'price' => $price, 'url' => $url, 'img' => $img, 'label' => $en ? 'View in the shop' : 'Megnézem'));
}
function nl_body_blocks($cfg, $camp, $s, $body, $en, $trk) {
    $out = array(); $buf = array();
    $track = $trk ? array($camp['id'], $s['id']) : null;
    foreach (preg_split('/\r\n|\r|\n/', $body) as $ln) {
        if (preg_match('/^\s*\[\[termek:([A-Za-z0-9_-]{1,80})\]\]\s*$/u', $ln, $m)) {
            $card = nl_product_card($cfg, $m[1], $en, $track);
            if ($card) { if ($buf) { $out[] = (object) array('lines' => $buf); $buf = array(); } $out[] = $card; }
            continue;
        }
        $buf[] = $ln;
    }
    if ($buf) { $out[] = (object) array('lines' => $buf); }
    return $out ? $out : array((object) array('lines' => array('')));
}
function nl_send_one($cfg, $camp, $s, $preview = false, $capture = false) {
    $en = (isset($s['lang']) && $s['lang'] === 'en');
    $hasEn = (!empty($camp['subjectEn']) && !empty($camp['bodyEn']));
    $subj = ($en && $hasEn) ? $camp['subjectEn'] : $camp['subject'];
    $unsub = $preview ? mail_site_url() : nl_link($cfg, $s['id'], 'unsub');
    $consent = !empty($s['consentAt']) ? date('Y-m-d', strtotime($s['consentAt'])) : '';
    $mc = mail_cfg();
    $contact = (!empty($mc['senders']['contact']) && mail_valid($mc['senders']['contact'])) ? $mc['senders']['contact'] : 'info@bagdikata.hu';
    $imp = mail_site_url();
    $foot = function ($isEn) use ($s, $consent, $unsub) {
        $priv = nl_privacy_url($isEn ? 'en' : 'hu'); $impl = mail_site_url() . ($isEn ? '/#/en/impresszum' : '/#/impresszum');
        if ($isEn) {
            $why = 'You are receiving this e-mail because you subscribed to the Bagdi Kata newsletter with ' . $s['email'] . ($consent ? ' on ' . $consent : '') . '.';
            $lab = array('Unsubscribe', 'Privacy notice', 'Legal notice');
        } else {
            $why = 'Azért kapod ezt a levelet, mert ' . ($consent ? $consent . ' napján ' : '') . 'a(z) ' . $s['email'] . ' címmel feliratkoztál a Bagdi Kata hírlevelére.';
            $lab = array('Leiratkozás', 'Adatkezelési tájékoztató', 'Impresszum');
        }
        return array($why, $lab, $priv, $impl);
    };
    $useEn = $en && $hasEn;
    $langs = array($en);
    $tplF = nl_tpl();
    list($fh, $ftx) = nl_frame_footer($tplF, $en); $ft = $ftx !== '' ? array($ftx) : array();
    foreach ($langs as $isEn) {
        list($why, $lab, $priv, $impl) = $foot($isEn);
        $fh .= '<p style="margin:0 0 6px">' . htmlspecialchars($why) . '</p>'
            . '<p style="margin:0 0 10px"><a href="' . htmlspecialchars($unsub) . '" style="color:#161314">' . $lab[0] . '</a> &middot; <a href="' . htmlspecialchars($priv) . '" style="color:#161314">' . $lab[1] . '</a> &middot; <a href="' . htmlspecialchars($impl) . '" style="color:#161314">' . $lab[2] . '</a></p>';
        $ft[] = $why . "\r\n" . $lab[0] . ': ' . $unsub . "\r\n" . $lab[1] . ': ' . $priv;
    }
    $trk = !$preview && !$capture && isset($camp['id']) && is_string($camp['id']) && preg_match('/^[a-f0-9]{12}$/', $camp['id']) && isset($s['id']) && preg_match('/^[a-f0-9]{16}$/', (string) $s['id']);
    if ($trk) { $fh .= '<img src="' . htmlspecialchars(mail_site_url() . '/api/nlt.php?o=' . $camp['id'] . '.' . $s['id'] . '.' . nl_trk_sig($cfg, array('o', $camp['id'], $s['id']))) . '" width="1" height="1" alt="" style="display:block;width:1px;height:1px;border:0">'; }
    $GLOBALS['bk_mail_footer_html'] = $fh;
    $GLOBALS['bk_mail_footer_text'] = implode("\r\n\r\n", $ft);
    $GLOBALS['bk_mail_nocopy'] = true;
    nl_frame_begin($tplF, $en);
    if (!$preview) {
        $GLOBALS['bk_mail_headers'] = array('List-Unsubscribe: <' . $unsub . '>, <mailto:' . $contact . '?subject=unsubscribe>', 'List-Unsubscribe-Post: List-Unsubscribe=One-Click', 'Precedence: bulk');
    }
    $blocks = array();
    $body = $useEn ? $camp['bodyEn'] : $camp['body'];
    foreach (nl_body_blocks($cfg, $camp, $s, (string) $body, $useEn, $trk) as $bb) { $blocks[] = $bb; }
    if (!empty($camp['ctaLabel']) && !empty($camp['ctaUrl'])) {
        $cl = ($useEn && !empty($camp['ctaLabelEn'])) ? $camp['ctaLabelEn'] : $camp['ctaLabel'];
        $href = $camp['ctaUrl'];
        if ($trk) { $href = mail_site_url() . '/api/nlt.php?c=' . $camp['id'] . '.' . $s['id'] . '.' . nl_trk_sig($cfg, array('c', $camp['id'], $s['id'])); }
        $blocks[] = (object) array('links' => array((object) array('label' => $cl, 'href' => $href)));
    }
    $it = (object) array('subject' => $subj, 'lang' => $useEn ? 'en' : 'hu', 'blocks' => $blocks);
    if ($capture) {
        list($text, $html) = mail_render($it);
        nl_frame_end();
        return array('subject' => ($preview ? '[TESZT] ' : '') . $subj, 'html' => $html, 'text' => $text);
    }
    try {
        list($text, $html) = mail_render($it);
        $sender = nl_sender();
        mail_send(array($s['email']), ($preview ? '[TESZT] ' : '') . $subj, $text, $html, $sender, $sender);
        $err = null;
    } catch (Throwable $e) { $err = mail_clean($e->getMessage(), 160); }
    nl_frame_end();
    return $err;
}

function nl_page($title, $msg, $lang, $extra = '') {
    http_response_code(200);
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex, nofollow');
    $site = htmlspecialchars(mail_site_url());
    $back = ($lang === 'en') ? 'Back to the shop' : 'Vissza a webshopba';
    echo '<!doctype html><html lang="' . ($lang === 'en' ? 'en' : 'hu') . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex"><title>' . htmlspecialchars($title) . ' – Bagdi Kata</title></head>'
        . '<body style="margin:0;background:#f6f3ee;font-family:Arial,Helvetica,sans-serif;color:#222"><div style="max-width:30rem;margin:12vh auto;padding:2rem 1.5rem;background:#fff;border:1px solid #ece6dc;text-align:center">'
        . '<img src="' . $site . '/mail-logo.png" width="56" alt="Bagdi Kata" style="display:block;margin:0 auto 1rem">'
        . '<h1 style="font-family:Georgia,serif;font-weight:400;font-size:1.5rem;margin:0 0 .8rem">' . htmlspecialchars($title) . '</h1>'
        . '<p style="line-height:1.55;margin:.6rem 0">' . htmlspecialchars($msg) . '</p>' . $extra
        . '<p style="margin-top:1.6rem"><a href="' . $site . '" style="color:#161314">' . $back . '</a></p></div></body></html>';
    exit;
}
