<?php
// Levélküldés: SMTP (ha az api/data/mail.php-ben meg van adva), különben a tárhely PHP mail() függvénye.
if (!defined('BK_API')) { exit; }

function mail_cfg() {
    $f = DATA_DIR . '/mail.php';
    if (!is_file($f)) { return array(); }
    try { $c = include $f; } catch (Throwable $e) { mail_log('HIBA a mail.php fájlban: ' . mail_clean($e->getMessage(), 160)); return array(); }
    return is_array($c) ? $c : array();
}
function mail_route_list($mc, $name) {
    $r = (isset($mc['routes']) && is_array($mc['routes']) && isset($mc['routes'][$name])) ? $mc['routes'][$name] : array();
    if (is_string($r)) { $r = array($r); }
    $out = array();
    if (is_array($r)) { foreach ($r as $a) { if (mail_valid($a)) { $out[] = $a; } } }
    return $out;
}
function mail_settings_obj() {
    $raw = read_raw('settings');
    $st = $raw !== null ? json_decode($raw) : null;
    return is_object($st) ? $st : null;
}
// Az admin "Feladó címek" beállítása: alapértelmezett feladó, ha az a mail.php alapcíme vagy a felvett alternatívák egyike
function mail_default_sender($mc) {
    $base = mail_from_addr($mc);
    $st = mail_settings_obj();
    if ($st && isset($st->notifications->senders) && is_object($st->notifications->senders)) {
        $se = $st->notifications->senders;
        $d = (isset($se->default) && is_string($se->default)) ? $se->default : '';
        if ($d !== '' && mail_valid($d)) {
            if (strtolower($d) === strtolower($base)) { return $base; }
            if (isset($se->alternatives) && is_array($se->alternatives)) {
                foreach (array_slice($se->alternatives, 0, 8) as $a) { if (is_string($a) && strtolower($a) === strtolower($d)) { return $d; } }
            }
        }
    }
    return $base;
}
// Az admin beállításában megadott címzettek (rendelési értesítő / napi jelentés); a régi alapcímek kimaradnak
function mail_settings_recipients($key) {
    $st = mail_settings_obj();
    $out = array();
    if ($st && isset($st->notifications->$key->recipients) && is_array($st->notifications->$key->recipients)) {
        foreach (array_slice($st->notifications->$key->recipients, 0, 10) as $r) {
            if (mail_valid($r) && !in_array(strtolower($r), array('rendeles@bagdikata.hu', 'ertesites@bagdikata.hu'), true)) { $out[] = $r; }
        }
    }
    return $out;
}
function mail_sender_for($mc, $kind) {
    if ($kind !== '' && isset($mc['senders']) && is_array($mc['senders']) && isset($mc['senders'][$kind]) && mail_valid($mc['senders'][$kind])) { return $mc['senders'][$kind]; }
    return mail_default_sender($mc);
}
function mail_log($line) {
    $f = DATA_DIR . '/mail.log';
    if (is_file($f) && filesize($f) > 200000) { @file_put_contents($f, ''); }
    @file_put_contents($f, date('Y-m-d H:i:s') . ' ' . $line . "\n", FILE_APPEND | LOCK_EX);
}
function mail_clean($s, $max) {
    $s = preg_replace('/[\r\n\t]+/u', ' ', (string) $s);
    return mb_substr(trim($s), 0, $max, 'UTF-8');
}
function mail_valid($a) { return is_string($a) && strlen($a) <= 120 && filter_var($a, FILTER_VALIDATE_EMAIL) !== false; }
function mail_from_addr($mc) {
    if (!empty($mc['from']) && mail_valid($mc['from'])) { return $mc['from']; }
    $h = isset($_SERVER['HTTP_HOST']) ? preg_replace('/^www\./i', '', preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'])) : 'localhost';
    return 'noreply@' . $h;
}
function mail_http_url($u) { return (is_string($u) && preg_match('#^https?://#i', $u) && strlen($u) < 500) ? $u : null; }

// Az outbox-elem (kind, subject, preheader, blocks) -> [szöveges, HTML] törzs
function mail_render($it) {
    $t = array();
    $h = array();
    $pre = isset($it->preheader) && is_string($it->preheader) ? mail_clean($it->preheader, 300) : '';
    if ($pre !== '') { $t[] = $pre; $t[] = ''; $h[] = '<p style="color:#6b6b6b;margin:0 0 16px">' . htmlspecialchars($pre) . '</p>'; }
    $blocks = (isset($it->blocks) && is_array($it->blocks)) ? array_slice($it->blocks, 0, 30) : array();
    foreach ($blocks as $b) {
        if (!is_object($b)) { continue; }
        if (isset($b->heading) && is_string($b->heading)) {
            $x = mail_clean($b->heading, 200);
            $t[] = $x; $t[] = str_repeat('-', min(40, mb_strlen($x, 'UTF-8')));
            $h[] = '<h2 style="font-size:17px;margin:20px 0 8px">' . htmlspecialchars($x) . '</h2>';
        }
        if (isset($b->lines) && is_array($b->lines)) {
            foreach (array_slice($b->lines, 0, 60) as $l) {
                if (!is_string($l)) { continue; }
                $x = mb_substr(preg_replace('/[\r\t]+/u', ' ', $l), 0, 600, 'UTF-8');
                $t[] = $x; $h[] = ($x === '') ? '<div style="height:10px;line-height:10px">&nbsp;</div>' : '<p style="margin:4px 0">' . nl2br(htmlspecialchars($x)) . '</p>';
            }
        }
        if (isset($b->table) && is_object($b->table) && isset($b->table->rows) && is_array($b->table->rows)) {
            $rows = array_slice($b->table->rows, 0, 100);
            if (isset($b->table->head) && is_array($b->table->head)) { array_unshift($rows, $b->table->head); }
            $t[] = '';
            $hh = '<table style="border-collapse:collapse;margin:8px 0;width:100%">';
            foreach ($rows as $ri => $r) {
                if (!is_array($r)) { continue; }
                $cells = array();
                foreach (array_slice($r, 0, 8) as $c) { $cells[] = mb_substr(preg_replace('/[\r\n\t]+/u', ' ', (string) $c), 0, 200, 'UTF-8'); }
                $t[] = implode(' | ', $cells);
                $hh .= '<tr>';
                foreach ($cells as $c) { $hh .= '<td style="border-bottom:1px solid #e5e5e5;padding:5px 8px' . ($ri === 0 && isset($b->table->head) ? ';font-weight:700' : '') . '">' . htmlspecialchars($c) . '</td>'; }
                $hh .= '</tr>';
            }
            $h[] = $hh . '</table>';
        }
        if (isset($b->links) && is_array($b->links)) {
            foreach (array_slice($b->links, 0, 20) as $lk) {
                if (!is_object($lk) || !isset($lk->label)) { continue; }
                $u = isset($lk->href) ? mail_http_url($lk->href) : null;
                $lab = mail_clean($lk->label, 200);
                if ($u) { $t[] = $lab . ': ' . $u; $h[] = '<p style="margin:4px 0"><a href="' . htmlspecialchars($u) . '">' . htmlspecialchars($lab) . '</a></p>'; }
            }
        }
        $t[] = '';
    }
    $lang = (isset($it->lang) && $it->lang === 'en') ? 'en' : 'hu';
    $ref = (isset($it->ref) && is_string($it->ref)) ? mail_clean($it->ref, 60) : '';
    if (!empty($GLOBALS['bk_mail_footer_text'])) { $t[] = $GLOBALS['bk_mail_footer_text']; $t[] = ''; }
    $t[] = '-- Bagdi Kata | ' . mail_site_url();
    if ($ref !== '') { $t[] = ($lang === 'en' ? 'Reference: ' : 'Azonosító: ') . $ref; }
    $html = mail_wrap(implode("\n", $h), $lang, $ref, $pre);
    return array(implode("\r\n", $t), $html);
}

function mail_site_url() {
    $mc = mail_cfg();
    $u = (!empty($mc['base_url']) && is_string($mc['base_url'])) ? rtrim($mc['base_url'], '/') : 'https://bagdikata.hu';
    return preg_match('#^https?://#i', $u) ? $u : 'https://bagdikata.hu';
}

// Arculatos levélkeret: fejléc logóval, tartalom, lábléc azonosítóval
function mail_wrap($inner, $lang, $ref, $pre) {
    $en = ($lang === 'en');
    $site = mail_site_url();
    $mc = mail_cfg();
    $contact = (!empty($mc['senders']['contact']) && mail_valid($mc['senders']['contact'])) ? $mc['senders']['contact'] : 'info@bagdikata.hu';
    $logo = htmlspecialchars($site . '/mail-logo.png');
    $host = htmlspecialchars(preg_replace('#^https?://#i', '', $site));
    $tag = $en ? 'Hand-knitted melange hats' : 'Kézzel kötött melírozott sapkák';
    $auto = $en ? 'This is an automatic message sent from the Bagdi Kata webshop.' : 'Ez a Bagdi Kata webshop automatikus üzenete.';
    $refLbl = $en ? 'Reference' : 'Azonosító';
    $refHtml = $ref !== '' ? '<p style="margin:0 0 6px;font-size:12px;color:#161314"><strong>' . $refLbl . ':</strong> ' . htmlspecialchars($ref) . '</p>' : '';
    $preHtml = $pre !== '' ? '<div style="display:none;max-height:0;overflow:hidden;opacity:0">' . htmlspecialchars($pre) . '</div>' : '';
    return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>'
        . '<body style="margin:0;padding:0;background:#f6f3ee">' . $preHtml
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f6f3ee"><tr><td align="center" style="padding:24px 12px">'
        . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:100%;max-width:600px;background:#ffffff;border:1px solid #ece6dc">'
        . '<tr><td align="center" style="background:#ece6dc;padding:22px 16px 16px;border-bottom:3px solid #161314">'
        . '<a href="' . htmlspecialchars($site) . '" style="text-decoration:none"><img src="' . $logo . '" width="56" alt="Bagdi Kata" style="display:block;margin:0 auto 8px;border:0"></a>'
        . '<div style="font-family:Georgia,\'Times New Roman\',serif;font-size:22px;letter-spacing:1px;color:#161314">Bagdi Kata</div>'
        . '<div style="font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#6b6b6b;margin-top:2px">' . $tag . '</div>'
        . '</td></tr>'
        . '<tr><td style="padding:24px 28px;font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.5;color:#222222">' . $inner . '</td></tr>'
        . '<tr><td style="background:#f6f3ee;padding:16px 28px;font-family:Arial,Helvetica,sans-serif;font-size:12px;line-height:1.5;color:#6b6b6b;border-top:1px solid #ece6dc">'
        . $refHtml
        . (isset($GLOBALS['bk_mail_footer_html']) ? $GLOBALS['bk_mail_footer_html'] : '<p style="margin:0 0 4px">' . $auto . '</p>')
        . '<p style="margin:0">Bagdi Kata &middot; <a href="' . htmlspecialchars($site) . '" style="color:#161314">' . $host . '</a> &middot; <a href="mailto:' . htmlspecialchars($contact) . '" style="color:#161314">' . htmlspecialchars($contact) . '</a></p>'
        . '</td></tr></table></td></tr></table></body></html>';
}

// Automatikus visszaigazolások (rögzített, kétnyelvű szöveggel)
function mail_confirm_send($to, $kind, $lang, $ref, $subject, $heading, $paras, $table, $senderKind) {
    $mc = mail_cfg();
    $blocks = array();
    if ($table) {
        $blocks[] = (object) array('heading' => $heading, 'lines' => array_slice($paras, 0, count($paras) - 3));
        $blocks[] = (object) array('table' => $table);
        $blocks[] = (object) array('lines' => array_slice($paras, -3));
    } else {
        $blocks[] = (object) array('heading' => $heading, 'lines' => $paras);
    }
    $it = (object) array('subject' => $subject, 'blocks' => $blocks, 'lang' => $lang, 'ref' => $ref);
    list($text, $html) = mail_render($it);
    $sender = mail_sender_for($mc, $senderKind);
    mail_send(array($to), $subject, $text, $html, $sender, $sender);
}
function mail_confirm_contact($email, $name, $lang, $ref) {
    $en = ($lang === 'en');
    $name = mail_clean($name, 80);
    if ($en) {
        mail_confirm_send($email, 'contact', 'en', $ref, 'We received your message – Bagdi Kata [' . $ref . ']', 'Thank you for your message',
            array('Dear ' . $name . ',', '', 'we have received your message and registered it under the reference number above. We will reply to this e-mail address as soon as possible, usually within 1–2 working days.', '', 'This is an automatic confirmation, please do not reply to it. If you want to add something, simply write to us and quote the reference number.', '', 'Kind regards,', 'Bagdi Kata'), null, 'contact');
    } else {
        mail_confirm_send($email, 'contact', 'hu', $ref, 'Üzenetedet megkaptuk – Bagdi Kata [' . $ref . ']', 'Köszönjük az üzenetedet',
            array('Kedves ' . $name . '!', '', 'Üzenetedet megkaptuk, és a fenti azonosítón iktattuk. A megadott e-mail-címen válaszolunk, általában 1–2 munkanapon belül.', '', 'Ez automatikus visszaigazolás, kérjük, ne erre a levélre válaszolj. Ha szeretnél valamit hozzátenni, írj nekünk, és hivatkozz a fenti azonosítóra.', '', 'Üdvözlettel:', 'Bagdi Kata'), null, 'contact');
    }
}
function mail_confirm_order($email, $name, $lang, $number, $table) {
    $en = ($lang === 'en');
    $name = mail_clean($name, 80);
    $number = mail_clean($number, 60);
    if ($en) {
        mail_confirm_send($email, 'order', 'en', $number, 'Order confirmation ' . $number . ' – Bagdi Kata', 'Thank you for your order',
            array('Dear ' . $name . ',', '', 'we have received your order (' . $number . '). The details are below. We will contact you by e-mail about the shipping of your parcel; you will receive your invoice separately.', '', 'If you have any question, reply to this e-mail or write to info@bagdikata.hu and quote the order number.', '', 'Kind regards,', 'Bagdi Kata'), $table, 'order');
    } else {
        mail_confirm_send($email, 'order', 'hu', $number, 'Rendelés visszaigazolás ' . $number . ' – Bagdi Kata', 'Köszönjük a rendelésedet',
            array('Kedves ' . $name . '!', '', 'Megkaptuk a rendelésedet (' . $number . '). A részleteket alább találod. A csomag feladásáról e-mailben értesítünk; a számlát külön levélben küldjük.', '', 'Ha kérdésed van, válaszolj erre a levélre, vagy írj az info@bagdikata.hu címre a rendelésszám megadásával.', '', 'Üdvözlettel:', 'Bagdi Kata'), $table, 'order');
    }
}

function smtp_read($fp) {
    $r = '';
    while (($l = fgets($fp, 1024)) !== false) { $r .= $l; if (strlen($l) < 4 || $l[3] === ' ') { break; } }
    return $r;
}
function smtp_cmd($fp, $cmd, $ok) {
    if ($cmd !== null) { fwrite($fp, $cmd . "\r\n"); }
    $r = smtp_read($fp);
    if (!in_array((int) substr($r, 0, 3), $ok, true)) { throw new Exception(trim(substr($r, 0, 120))); }
    return $r;
}
function mail_send_smtp($s, $from, $fromName, $to, $headersRaw, $body) {
    $secure = isset($s['secure']) ? $s['secure'] : 'ssl';
    $host = (string) $s['host'];
    $port = isset($s['port']) ? (int) $s['port'] : ($secure === 'ssl' ? 465 : 587);
    $fp = @stream_socket_client(($secure === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port, $en, $es, 10);
    if (!$fp) { throw new Exception('SMTP kapcsolódás sikertelen: ' . $es); }
    stream_set_timeout($fp, 10);
    try {
        smtp_cmd($fp, null, array(220));
        $me = isset($_SERVER['HTTP_HOST']) ? preg_replace('/[^a-z0-9.\-]/i', '', $_SERVER['HTTP_HOST']) : 'localhost';
        smtp_cmd($fp, 'EHLO ' . $me, array(250));
        if ($secure === 'tls') {
            smtp_cmd($fp, 'STARTTLS', array(220));
            if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) { throw new Exception('STARTTLS sikertelen'); }
            smtp_cmd($fp, 'EHLO ' . $me, array(250));
        }
        if (!empty($s['user'])) {
            smtp_cmd($fp, 'AUTH LOGIN', array(334));
            smtp_cmd($fp, base64_encode((string) $s['user']), array(334));
            smtp_cmd($fp, base64_encode((string) $s['pass']), array(235));
        }
        smtp_cmd($fp, 'MAIL FROM:<' . $from . '>', array(250));
        foreach ($to as $a) { smtp_cmd($fp, 'RCPT TO:<' . $a . '>', array(250, 251)); }
        smtp_cmd($fp, 'DATA', array(354));
        $data = preg_replace('/^\./m', '..', str_replace(array("\r\n", "\r"), "\n", $headersRaw . "\r\n\r\n" . $body));
        $data = str_replace("\n", "\r\n", $data);
        fwrite($fp, $data . "\r\n.\r\n");
        smtp_cmd($fp, null, array(250));
        @fwrite($fp, "QUIT\r\n");
    } catch (Exception $e) { @fclose($fp); throw $e; }
    @fclose($fp);
}

// Az admin "Kimenő levelek másolata" beállításának címei (rejtett másolat)
function mail_copy_list($to) {
    if (!empty($GLOBALS['bk_mail_nocopy'])) { return array(); }
    $raw = read_raw('settings');
    $st = $raw !== null ? json_decode($raw) : null;
    $out = array();
    if (is_object($st) && isset($st->notifications->outgoingCopy) && is_object($st->notifications->outgoingCopy)
        && !empty($st->notifications->outgoingCopy->enabled) && isset($st->notifications->outgoingCopy->recipients) && is_array($st->notifications->outgoingCopy->recipients)) {
        $have = array();
        foreach ($to as $a) { $have[strtolower($a)] = 1; }
        foreach (array_slice($st->notifications->outgoingCopy->recipients, 0, 5) as $r) {
            if (mail_valid($r) && !isset($have[strtolower($r)])) { $out[strtolower($r)] = $r; }
        }
    }
    return array_values($out);
}

function mail_send($to, $subject, $text, $html, $replyTo, $fromOverride = null) {
    $mc = mail_cfg();
    $bcc = mail_copy_list($to);
    $from = ($fromOverride && mail_valid($fromOverride)) ? $fromOverride : mail_from_addr($mc);
    $fromName = isset($mc['from_name']) && is_string($mc['from_name']) ? mail_clean($mc['from_name'], 60) : 'Bagdi Kata';
    $bd = 'bk' . bin2hex(random_bytes(8));
    $encName = '=?UTF-8?B?' . base64_encode($fromName) . '?=';
    $encSub = '=?UTF-8?B?' . base64_encode(mail_clean($subject, 200)) . '?=';
    $dom = (strpos($from, '@') !== false) ? substr(strrchr($from, '@'), 1) : 'localhost';
    $h = array('From: ' . $encName . ' <' . $from . '>', 'Message-ID: <' . bin2hex(random_bytes(14)) . '@' . $dom . '>', 'Date: ' . date('r'), 'MIME-Version: 1.0',
        'Content-Type: multipart/alternative; boundary="' . $bd . '"', 'X-Mailer: bagdikata');
    if ($replyTo && mail_valid($replyTo)) { $h[] = 'Reply-To: ' . $replyTo; }
    if (!empty($GLOBALS['bk_mail_headers']) && is_array($GLOBALS['bk_mail_headers'])) { foreach ($GLOBALS['bk_mail_headers'] as $xh) { $h[] = $xh; } }
    $body = "--$bd\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($text))
        . "--$bd\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($html))
        . "--$bd--\r\n";
    if (!empty($mc['smtp']) && is_array($mc['smtp']) && !empty($mc['smtp']['host'])) {
        $raw = implode("\r\n", $h) . "\r\nTo: " . implode(', ', $to) . "\r\nSubject: " . $encSub;
        $sm = $mc['smtp'];
        if (isset($sm['user']) && $sm['user'] === '*') { $sm['user'] = $from; }
        try {
            mail_send_smtp($sm, $from, $fromName, array_merge($to, $bcc), $raw, $body);
        } catch (Throwable $e) {
            $base = mail_from_addr($mc);
            if (strtolower($from) !== strtolower($base)) {
                mail_log('a(z) ' . $from . ' feladó nem működött (' . mail_clean($e->getMessage(), 100) . '), újrapróbálás innen: ' . $base);
                mail_send($to, $subject, $text, $html, $replyTo, $base);
                return;
            }
            throw $e;
        }
        return;
    }
    if (count($bcc)) { $h[] = 'Bcc: ' . implode(', ', $bcc); }
    $ok = @mail(implode(', ', $to), $encSub, $body, implode("\r\n", $h), '-f' . $from);
    if (!$ok) { $ok = @mail(implode(', ', $to), $encSub, $body, implode("\r\n", $h)); }
    if (!$ok) { throw new Exception('a mail() függvény sikertelen (a tárhely letilthatja)'); }
}

// Új outbox-elemek tényleges kiküldése. Védelem: látogató csak a beállított címzetteknek (és az elállásnál az ügyfélnek) küldhet.
// Sikeres statisztikai levél után bejegyzés az admin Előzmények oldalára (a levél tartalmával)
function mail_audit_report($kind, $subject, $to, $text) {
    try {
        $entry = (object) array(
            'id' => 'a-' . bin2hex(random_bytes(6)),
            'at' => gmdate('Y-m-d\TH:i:s') . '.000Z',
            'actor' => (object) array('type' => 'system', 'name' => 'Értesítő'),
            'entity' => 'settings',
            'entityId' => 'daily-report',
            'entityLabel' => 'Napi jelentés',
            'action' => 'send',
            'summary' => ($kind === 'test' ? 'Teszt napi jelentés' : 'Napi jelentés') . ' elküldve: ' . implode(', ', $to),
            'changes' => array(
                (object) array('field' => 'mail.subject', 'label' => 'Tárgy', 'from' => '', 'to' => mail_clean($subject, 200)),
                (object) array('field' => 'mail.to', 'label' => 'Címzettek', 'from' => '', 'to' => implode(', ', $to)),
                (object) array('field' => 'mail.body', 'label' => 'Levél tartalma', 'from' => '', 'to' => mb_substr(str_replace("\r\n", "\n", $text), 0, 4000, 'UTF-8')),
            ),
        );
        mutate_json('audit', function ($cur) use ($entry) {
            $cur = is_array($cur) ? $cur : array();
            array_unshift($cur, $entry);
            return array_slice($cur, 0, 2000);
        });
    } catch (Throwable $e) { mail_log('HIBA az előzmény rögzítésénél: ' . mail_clean($e->getMessage(), 120)); }
}

function mail_dispatch($cfg, $items, $isAdmin) {
    $okCount = 0;
    $allowed = array(strtolower($cfg['admin_email']));
    $raw = read_raw('settings');
    $st = $raw !== null ? json_decode($raw) : null;
    if (is_object($st) && isset($st->notifications) && is_object($st->notifications)) {
        foreach (array('orderEmail', 'dailyReport') as $k) {
            if (isset($st->notifications->$k->recipients) && is_array($st->notifications->$k->recipients)) {
                foreach ($st->notifications->$k->recipients as $r) { if (mail_valid($r)) { $allowed[] = strtolower($r); } }
            }
        }
    }
    foreach ($items as $it) {
        if (!is_object($it) || !isset($it->to) || !is_array($it->to)) { mail_log('kihagyva: a levélnek nincs címzett listája'); continue; }
        $kind = (isset($it->kind) && is_string($it->kind)) ? $it->kind : '';
        $to = array();
        $external = 0;
        foreach (array_slice($it->to, 0, 5) as $a) {
            if (!mail_valid($a)) { continue; }
            $l = strtolower($a);
            if ($isAdmin || in_array($l, $allowed, true)) { $to[$l] = $a; }
            elseif ($kind === 'withdrawal' && $external < 1) { $to[$l] = $a; $external++; }
        }
        $mc = mail_cfg();
        if ($kind === 'order' || $kind === 'daily') {
            $rt = array_merge(mail_route_list($mc, $kind), mail_settings_recipients($kind === 'order' ? 'orderEmail' : 'dailyReport'));
            if (count($rt)) { $to = array(); foreach ($rt as $a) { $to[strtolower($a)] = $a; } }
        }
        if ($kind === 'withdrawal') { foreach (mail_route_list($mc, 'withdrawal_copy') as $a) { $to[strtolower($a)] = $a; } }
        if (!$isAdmin && $kind !== 'order' && $kind !== 'withdrawal') { mail_log('kihagyva (' . $kind . '): látogatótól nem küldhető'); continue; }
        if (!count($to)) { mail_log('kihagyva (' . $kind . '): nincs érvényes, engedélyezett címzett (Beállítások > Rendelési értesítő)'); continue; }
        if (rl_blocked($isAdmin ? 'mailsend_a' : 'mailsend_v', $isAdmin ? 100 : 10, 3600)) { mail_log('limit: ' . $kind); continue; }
        rl_hit($isAdmin ? 'mailsend_a' : 'mailsend_v', 3600);
        $subject = isset($it->subject) && is_string($it->subject) ? $it->subject : 'Értesítés';
        list($text, $html) = mail_render($it);
        try {
            $sender = mail_sender_for($mc, $kind);
            mail_send(array_values($to), $subject, $text, $html, $sender, $sender);
            mail_log('OK ' . $kind . ' -> ' . count($to) . ' cím');
            $okCount++;
            if ($kind === 'daily' || ($kind === 'test' && strpos($subject, 'Napi jelentés') === 0)) { mail_audit_report($kind, $subject, array_values($to), $text); }
        } catch (Throwable $e) {
            mail_log('HIBA ' . $kind . ': ' . mail_clean($e->getMessage(), 200));
        }
        if ($kind === 'order' && isset($it->customer) && is_object($it->customer) && isset($it->customer->email) && mail_valid($it->customer->email)) {
            $tbl = null;
            if (isset($it->blocks) && is_array($it->blocks)) {
                foreach ($it->blocks as $b) { if (is_object($b) && isset($b->table) && is_object($b->table)) { $tbl = $b->table; break; } }
            }
            $num = (isset($it->orderNumber) && is_string($it->orderNumber)) ? $it->orderNumber : '';
            $cn = (isset($it->customer->name) && is_string($it->customer->name)) ? $it->customer->name : '';
            $lg = (isset($it->lang) && $it->lang === 'en') ? 'en' : 'hu';
            if ($num !== '' && !rl_blocked('confirm_v', 10, 3600)) {
                rl_hit('confirm_v', 3600);
                try {
                    mail_confirm_order($it->customer->email, $cn, $lg, $num, $tbl);
                    mail_log('OK order-visszaigazolas -> vásárló');
                } catch (Throwable $e) {
                    mail_log('HIBA order-visszaigazolas: ' . mail_clean($e->getMessage(), 200));
                }
            }
        }
    }
    return $okCount;
}
