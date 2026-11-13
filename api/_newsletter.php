<?php
// Hírlevél: feliratkozók (api/data/newsletter.json), kettős megerősítés, leiratkozás, kampányok. Közvetlenül nem hívható.
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

// Feliratkozás indítása (nem megerősített állapot) + megerősítő levél. Soha nem árulja el, hogy a cím már szerepel-e.
function nl_subscribe($cfg, $email, $name, $lang, $source) {
    $email = trim((string) $email);
    if (!mail_valid($email)) { return 'invalid'; }
    $key = strtolower($email);
    $name = mail_clean($name, 80);
    $lang = ($lang === 'en') ? 'en' : 'hu';
    $source = in_array($source, array('checkout', 'footer'), true) ? $source : 'footer';
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
            $a[$i]['lang'] = $lang; $a[$i]['unsubscribedAt'] = null; $a[$i]['confirmedAt'] = null; $a[$i]['consentVer'] = NL_CONSENT_VER;
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
    if ($send) { nl_send_confirm($cfg, $send); }
    return 'ok';
}

function nl_send_confirm($cfg, $s) {
    $en = (isset($s['lang']) && $s['lang'] === 'en');
    $name = (isset($s['name']) && $s['name'] !== '') ? $s['name'] : '';
    $url = nl_link($cfg, $s['id'], 'confirm');
    if ($en) {
        $subject = 'Please confirm your newsletter subscription – Bagdi Kata';
        $lines = array(($name !== '' ? 'Dear ' . $name . ',' : 'Hello,'), '',
            'a newsletter subscription was requested for this e-mail address (' . $s['email'] . ') at the Bagdi Kata webshop. It only takes effect once you confirm it with the link below.', '',
            'If it was not you, simply ignore this message – we will not send you any newsletter without your confirmation.', '',
            'You can withdraw your consent at any time, free of charge, using the unsubscribe link at the bottom of every newsletter. How we handle your data: ' . nl_privacy_url('en'));
        $label = 'Confirm subscription';
    } else {
        $subject = 'Erősítsd meg a hírlevél-feliratkozásodat – Bagdi Kata';
        $lines = array(($name !== '' ? 'Kedves ' . $name . '!' : 'Kedves Érdeklődő!'), '',
            'Ezen az e-mail-címen (' . $s['email'] . ') hírlevél-feliratkozást kezdeményeztek a Bagdi Kata webshopban. A feliratkozás csak akkor lép érvénybe, ha az alábbi linkkel megerősíted.', '',
            'Ha nem te kérted, egyszerűen hagyd figyelmen kívül ezt a levelet – megerősítés nélkül nem küldünk hírlevelet.', '',
            'Hozzájárulásodat bármikor, ingyenesen visszavonhatod a hírlevelek alján lévő leiratkozás linkkel. Az adatkezelésről itt olvashatsz: ' . nl_privacy_url('hu'));
        $label = 'Feliratkozás megerősítése';
    }
    $it = (object) array('subject' => $subject, 'lang' => $en ? 'en' : 'hu',
        'blocks' => array((object) array('heading' => $en ? 'Confirm your subscription' : 'Feliratkozás megerősítése', 'lines' => $lines), (object) array('links' => array((object) array('label' => $label, 'href' => $url)))));
    list($text, $html) = mail_render($it);
    try { $sender = nl_sender(); mail_send(array($s['email']), $subject, $text, $html, $sender, $sender); mail_log('OK hírlevél-megerősítő -> 1 cím'); }
    catch (Throwable $e) { mail_log('HIBA hírlevél-megerősítő: ' . mail_clean($e->getMessage(), 120)); }
}

// Egy hírlevél-levél kiküldése egy feliratkozónak (leiratkozó lábléc, List-Unsubscribe fejlécek)
function nl_send_one($cfg, $camp, $s, $preview = false) {
    $en = (isset($s['lang']) && $s['lang'] === 'en');
    $subj = ($en && !empty($camp['subjectEn']) && !empty($camp['bodyEn'])) ? $camp['subjectEn'] : $camp['subject'];
    $body = ($en && !empty($camp['subjectEn']) && !empty($camp['bodyEn'])) ? $camp['bodyEn'] : $camp['body'];
    $unsub = $preview ? mail_site_url() : nl_link($cfg, $s['id'], 'unsub');
    $consent = !empty($s['consentAt']) ? date('Y-m-d', strtotime($s['consentAt'])) : '';
    $priv = nl_privacy_url($en ? 'en' : 'hu'); $imp = mail_site_url() . ($en ? '/#/en/impresszum' : '/#/impresszum');
    $mc = mail_cfg();
    $contact = (!empty($mc['senders']['contact']) && mail_valid($mc['senders']['contact'])) ? $mc['senders']['contact'] : 'info@bagdikata.hu';
    if ($en) {
        $why = 'You are receiving this e-mail because you subscribed to the Bagdi Kata newsletter with ' . $s['email'] . ($consent ? ' on ' . $consent : '') . '.';
        $lU = 'Unsubscribe'; $lP = 'Privacy notice'; $lI = 'Legal notice';
    } else {
        $why = 'Azért kapod ezt a levelet, mert ' . ($consent ? $consent . ' napján ' : '') . 'a(z) ' . $s['email'] . ' címmel feliratkoztál a Bagdi Kata hírlevelére.';
        $lU = 'Leiratkozás'; $lP = 'Adatkezelési tájékoztató'; $lI = 'Impresszum';
    }
    $GLOBALS['bk_mail_footer_html'] = '<p style="margin:0 0 6px">' . htmlspecialchars($why) . '</p>'
        . '<p style="margin:0 0 6px"><a href="' . htmlspecialchars($unsub) . '" style="color:#161314">' . $lU . '</a> &middot; <a href="' . htmlspecialchars($priv) . '" style="color:#161314">' . $lP . '</a> &middot; <a href="' . htmlspecialchars($imp) . '" style="color:#161314">' . $lI . '</a></p>';
    $GLOBALS['bk_mail_footer_text'] = $why . "\r\n" . $lU . ': ' . $unsub . "\r\n" . $lP . ': ' . $priv;
    $GLOBALS['bk_mail_nocopy'] = true;
    if (!$preview) {
        $GLOBALS['bk_mail_headers'] = array('List-Unsubscribe: <' . $unsub . '>, <mailto:' . $contact . '?subject=unsubscribe>', 'List-Unsubscribe-Post: List-Unsubscribe=One-Click', 'Precedence: bulk');
    }
    $blocks = array((object) array('lines' => preg_split('/\r\n|\r|\n/', (string) $body)));
    if (!empty($camp['ctaLabel']) && !empty($camp['ctaUrl'])) { $blocks[] = (object) array('links' => array((object) array('label' => $camp['ctaLabel'], 'href' => $camp['ctaUrl']))); }
    $it = (object) array('subject' => $subj, 'lang' => $en ? 'en' : 'hu', 'blocks' => $blocks);
    try {
        list($text, $html) = mail_render($it);
        $sender = nl_sender();
        mail_send(array($s['email']), ($preview ? '[TESZT] ' : '') . $subj, $text, $html, $sender, $sender);
        $err = null;
    } catch (Throwable $e) { $err = mail_clean($e->getMessage(), 160); }
    unset($GLOBALS['bk_mail_footer_html'], $GLOBALS['bk_mail_footer_text'], $GLOBALS['bk_mail_nocopy'], $GLOBALS['bk_mail_headers']);
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
