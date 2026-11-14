<?php
define('BK_API', 1);
require __DIR__ . '/_lib.php';
require __DIR__ . '/_newsletter.php';
ensure_data();
$cfg = load_config();
if (!$cfg) { fail(503, 'Nincs beállítva'); }
$method = $_SERVER['REQUEST_METHOD'];

function nl_find_lang($id) { foreach (nl_list() as $s) { if (isset($s['id']) && $s['id'] === $id) { return (isset($s['lang']) && $s['lang'] === 'en') ? 'en' : 'hu'; } } return 'hu'; }
function nl_set_status($id, $status) {
    $found = false;
    mutate_json('newsletter', function ($cur) use ($id, $status, &$found) {
        $a = is_array($cur) ? json_decode(json_encode($cur), true) : array();
        if (!is_array($a)) { $a = array(); }
        foreach ($a as $i => $s) {
            if (!isset($s['id']) || $s['id'] !== $id) { continue; }
            $found = true;
            if ($status === 'confirmed' && (!isset($s['status']) || $s['status'] !== 'confirmed')) { $a[$i]['status'] = 'confirmed'; $a[$i]['confirmedAt'] = date('c'); }
            if ($status === 'unsubscribed' && (!isset($s['status']) || $s['status'] !== 'unsubscribed')) { $a[$i]['status'] = 'unsubscribed'; $a[$i]['unsubscribedAt'] = date('c'); }
        }
        return array_values($a);
    });
    return $found;
}

if ($method === 'GET' && isset($_GET['c'])) {
    $id = (string) $_GET['c']; $t = isset($_GET['t']) ? (string) $_GET['t'] : '';
    $lang = nl_find_lang($id); $en = ($lang === 'en');
    if (!nl_token_ok($cfg, $id, 'confirm', $t) || !nl_set_status($id, 'confirmed')) {
        nl_page($en ? 'Invalid link' : 'Érvénytelen link', $en ? 'This confirmation link is not valid. Please subscribe again.' : 'Ez a megerősítő link nem érvényes. Kérjük, iratkozz fel újra.', $lang);
    }
    nl_page($en ? 'Subscription confirmed' : 'Feliratkozás megerősítve', $en ? 'Thank you! You are now subscribed to the Bagdi Kata newsletter. You can unsubscribe at any time from the link at the bottom of each newsletter.' : 'Köszönjük! Mostantól kapod a Bagdi Kata hírlevelét. Bármikor leiratkozhatsz a hírlevelek alján lévő linkkel.', $lang);
}
if (($method === 'GET' && isset($_GET['u'])) || ($method === 'POST' && (isset($_POST['u']) || isset($_GET['u'])))) {
    $id = (string) (isset($_POST['u']) ? $_POST['u'] : $_GET['u']);
    $t = (string) (isset($_POST['t']) ? $_POST['t'] : (isset($_GET['t']) ? $_GET['t'] : ''));
    $lang = nl_find_lang($id); $en = ($lang === 'en');
    if (!nl_token_ok($cfg, $id, 'unsub', $t)) { nl_page($en ? 'Invalid link' : 'Érvénytelen link', $en ? 'This unsubscribe link is not valid. Please write to info@bagdikata.hu and we will remove you manually.' : 'Ez a leiratkozó link nem érvényes. Írj az info@bagdikata.hu címre, és kézzel töröljük a címedet.', $lang); }
    if ($method === 'POST') {
        nl_set_status($id, 'unsubscribed');
        if (isset($_POST['List-Unsubscribe'])) { header('Content-Type: text/plain; charset=utf-8'); echo 'OK'; exit; }
        nl_page($en ? 'You have unsubscribed' : 'Leiratkoztál', $en ? 'We will not send you any more newsletters. You can subscribe again at any time.' : 'Nem küldünk több hírlevelet erre a címre. Bármikor újra feliratkozhatsz.', $lang);
    }
    $btn = '<form method="post" action="newsletter.php" style="margin-top:1.2rem"><input type="hidden" name="u" value="' . htmlspecialchars($id) . '"><input type="hidden" name="t" value="' . htmlspecialchars($t) . '">'
        . '<button type="submit" style="background:#161314;color:#fff;border:0;padding:.8rem 1.4rem;font-size:1rem;cursor:pointer">' . ($en ? 'Yes, unsubscribe me' : 'Igen, leiratkozom') . '</button></form>';
    nl_page($en ? 'Unsubscribe' : 'Leiratkozás a hírlevélről', $en ? 'Do you want to stop receiving the Bagdi Kata newsletter?' : 'Szeretnél leiratkozni a Bagdi Kata hírlevélről?', $lang, $btn);
}

if ($method === 'GET' && isset($_GET['export'])) {
    if (!has_admin($cfg)) { fail(401, 'Admin belépés szükséges'); }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="hirlevel-feliratkozok-' . date('Y-m-d') . '.csv"');
    header('Cache-Control: no-store');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    fputcsv($out, array('email', 'nev', 'allapot', 'nyelv', 'forras', 'hozzajarulas', 'megerositve', 'leiratkozott'), ';');
    $safe = function ($v) { $v = (string) $v; return (preg_match('/^[=+\-@\t\r]/', $v)) ? "'" . $v : $v; };
    foreach (nl_list() as $s) {
        fputcsv($out, array($safe($s['email']), $safe(isset($s['name']) ? $s['name'] : ''), isset($s['status']) ? $s['status'] : '', isset($s['lang']) ? $s['lang'] : '', isset($s['source']) ? $s['source'] : '',
            isset($s['consentAt']) ? $s['consentAt'] : '', isset($s['confirmedAt']) ? $s['confirmedAt'] : '', isset($s['unsubscribedAt']) ? $s['unsubscribedAt'] : ''), ';');
    }
    exit;
}

require_post();
$d = body_json(40000);
$act = (is_object($d) && isset($d->action) && is_string($d->action)) ? $d->action : '';

if ($act === 'subscribe') {
    $src = (isset($d->source) && $d->source === 'landing') ? 'landing' : 'footer';
    if ($src !== 'landing') { require_site($cfg); }
    if (rl_blocked('nlsub', 8, 3600)) { fail(429, 'Túl sok próbálkozás. Próbáld később.'); }
    rl_hit('nlsub', 3600);
    if (!empty($d->hp)) { json_out(array('ok' => true)); }
    if (empty($d->consent)) { fail(400, 'A feliratkozáshoz hozzájárulás szükséges.'); }
    if (!bot_verify(isset($d->captcha) ? $d->captcha : '')) { fail(400, 'A robotellenőrzés nem sikerült.'); }
    $email = (isset($d->email) && is_string($d->email)) ? $d->email : '';
    if (!mail_valid(trim($email))) { fail(400, 'Érvénytelen e-mail-cím'); }
    nl_subscribe($cfg, $email, (isset($d->name) && is_string($d->name)) ? $d->name : '', (isset($d->lang) && $d->lang === 'en') ? 'en' : 'hu', $src);
    json_out(array('ok' => true));
}

require_admin($cfg);

function nl_public($s) {
    return array('id' => $s['id'], 'email' => $s['email'], 'name' => isset($s['name']) ? $s['name'] : '', 'lang' => isset($s['lang']) ? $s['lang'] : 'hu',
        'status' => isset($s['status']) ? $s['status'] : 'pending', 'source' => isset($s['source']) ? $s['source'] : '', 'consentAt' => isset($s['consentAt']) ? $s['consentAt'] : null,
        'confirmedAt' => isset($s['confirmedAt']) ? $s['confirmedAt'] : null, 'unsubscribedAt' => isset($s['unsubscribedAt']) ? $s['unsubscribedAt'] : null);
}
function nl_camps() { $raw = read_raw('nl_campaigns'); $a = $raw !== null ? json_decode($raw, true) : array(); return is_array($a) ? array_values($a) : array(); }
function nl_camp_public($c) {
    return array('id' => $c['id'], 'subject' => $c['subject'], 'createdAt' => $c['createdAt'], 'total' => (int) $c['total'], 'sent' => (int) $c['sent'], 'failed' => (int) $c['failed'],
        'remaining' => count(isset($c['queue']) ? $c['queue'] : array()), 'status' => $c['status'], 'lastError' => isset($c['lastError']) ? $c['lastError'] : '');
}
function nl_snapshot() {
    $subs = nl_list(); $n = array('confirmed' => 0, 'pending' => 0, 'unsubscribed' => 0);
    $pub = array();
    foreach ($subs as $s) { $st = isset($s['status']) ? $s['status'] : 'pending'; if (isset($n[$st])) { $n[$st]++; } $pub[] = nl_public($s); }
    usort($pub, function ($a, $b) { return strcmp((string) $b['consentAt'], (string) $a['consentAt']); });
    $camps = array(); foreach (array_slice(nl_camps(), 0, 20) as $c) { $camps[] = nl_camp_public($c); }
    return array('ok' => true, 'subscribers' => $pub, 'counts' => $n, 'campaigns' => $camps);
}
function nl_draft($d) {
    $subject = (isset($d->subject) && is_string($d->subject)) ? mb_substr(trim(preg_replace('/[\r\n\t]+/u', ' ', $d->subject)), 0, 150, 'UTF-8') : '';
    $body = (isset($d->body) && is_string($d->body)) ? mb_substr(str_replace("\r", '', $d->body), 0, 10000, 'UTF-8') : '';
    $subjectEn = (isset($d->subjectEn) && is_string($d->subjectEn)) ? mb_substr(trim(preg_replace('/[\r\n\t]+/u', ' ', $d->subjectEn)), 0, 150, 'UTF-8') : '';
    $bodyEn = (isset($d->bodyEn) && is_string($d->bodyEn)) ? mb_substr(str_replace("\r", '', $d->bodyEn), 0, 10000, 'UTF-8') : '';
    $cl = (isset($d->ctaLabel) && is_string($d->ctaLabel)) ? mb_substr(trim($d->ctaLabel), 0, 60, 'UTF-8') : '';
    $cu = (isset($d->ctaUrl) && is_string($d->ctaUrl)) ? trim($d->ctaUrl) : '';
    if ($subject === '' || trim($body) === '') { fail(400, 'A tárgy és a levél szövege kötelező.'); }
    if (($cl !== '' || $cu !== '') && (!preg_match('#^https://[^\s<>"]{4,300}$#i', $cu) || $cl === '')) { fail(400, 'A gombhoz címke és https:// kezdetű link is kell.'); }
    if (($subjectEn !== '') !== (trim($bodyEn) !== '')) { fail(400, 'Az angol változathoz tárgy és szöveg is kell (vagy mindkettő maradjon üresen).'); }
    return array('subject' => $subject, 'body' => $body, 'subjectEn' => $subjectEn, 'bodyEn' => $bodyEn, 'ctaLabel' => $cu === '' ? '' : $cl, 'ctaUrl' => $cu);
}

if ($act === 'list') { json_out(nl_snapshot()); }
if ($act === 'delete' || $act === 'unsub') {
    $id = (isset($d->id) && is_string($d->id) && preg_match('/^[a-f0-9]{16}$/', $d->id)) ? $d->id : '';
    if ($id === '') { fail(400, 'Hibás azonosító'); }
    if ($act === 'unsub') { nl_set_status($id, 'unsubscribed'); }
    else {
        mutate_json('newsletter', function ($cur) use ($id) {
            $a = is_array($cur) ? json_decode(json_encode($cur), true) : array(); if (!is_array($a)) { $a = array(); }
            $out = array(); foreach ($a as $s) { if (isset($s['id']) && $s['id'] === $id) { continue; } $out[] = $s; } return $out;
        });
    }
    json_out(nl_snapshot());
}
if ($act === 'preview') {
    $dr = nl_draft($d);
    $lang = (isset($d->lang) && $d->lang === 'en') ? 'en' : 'hu';
    $fake = array('id' => '0000000000000000', 'email' => 'feliratkozo@example.com', 'lang' => $lang, 'consentAt' => date('c'));
    $r = nl_send_one($cfg, $dr, $fake, true, true);
    $r['subject'] = preg_replace('/^\[TESZT\] /', '', $r['subject']);
    $r['ok'] = true;
    json_out($r);
}
if ($act === 'test') {
    $dr = nl_draft($d);
    if (rl_blocked('nltest', 20, 3600)) { fail(429, 'Túl sok tesztlevél. Próbáld később.'); }
    rl_hit('nltest', 3600);
    $lang = (isset($d->lang) && $d->lang === 'en') ? 'en' : 'hu';
    $fake = array('id' => '0000000000000000', 'email' => $cfg['admin_email'], 'lang' => $lang, 'consentAt' => date('c'));
    $err = nl_send_one($cfg, $dr, $fake, true);
    if ($err) { fail(502, 'A tesztlevél küldése nem sikerült: ' . $err); }
    json_out(array('ok' => true, 'to' => $cfg['admin_email']));
}
if ($act === 'campaign_create') {
    $dr = nl_draft($d);
    $queue = array();
    foreach (nl_list() as $s) { if (isset($s['status']) && $s['status'] === 'confirmed') { $queue[] = $s['id']; } }
    if (!count($queue)) { fail(400, 'Nincs megerősített feliratkozó, akinek a levelet ki lehetne küldeni.'); }
    $queue = array_slice($queue, 0, 5000);
    $c = array_merge($dr, array('id' => bin2hex(random_bytes(6)), 'createdAt' => date('c'), 'total' => count($queue), 'sent' => 0, 'failed' => 0, 'queue' => $queue, 'status' => 'sending', 'busyUntil' => 0, 'lastError' => ''));
    mutate_json('nl_campaigns', function ($cur) use ($c) { $a = is_array($cur) ? json_decode(json_encode($cur), true) : array(); if (!is_array($a)) { $a = array(); } array_unshift($a, $c); return array_slice($a, 0, 40); });
    $r = nl_snapshot(); $r['campaignId'] = $c['id']; json_out($r);
}
if ($act === 'campaign_send' || $act === 'campaign_cancel') {
    $cid = (isset($d->id) && is_string($d->id) && preg_match('/^[a-f0-9]{12}$/', $d->id)) ? $d->id : '';
    if ($cid === '') { fail(400, 'Hibás kampányazonosító'); }
    if ($act === 'campaign_cancel') {
        mutate_json('nl_campaigns', function ($cur) use ($cid) {
            $a = is_array($cur) ? json_decode(json_encode($cur), true) : array(); if (!is_array($a)) { $a = array(); }
            foreach ($a as $i => $c) { if ($c['id'] === $cid && $c['status'] !== 'done') { $a[$i]['status'] = 'stopped'; $a[$i]['queue'] = array(); } }
            return array_values($a);
        });
        json_out(nl_snapshot());
    }
    $camp = null; $busy = false;
    mutate_json('nl_campaigns', function ($cur) use ($cid, &$camp, &$busy) {
        $a = is_array($cur) ? json_decode(json_encode($cur), true) : array(); if (!is_array($a)) { $a = array(); }
        foreach ($a as $i => $c) {
            if ($c['id'] !== $cid) { continue; }
            if (isset($c['busyUntil']) && (int) $c['busyUntil'] > time()) { $busy = true; break; }
            if ($c['status'] === 'done' || $c['status'] === 'stopped' || !count($c['queue'])) { break; }
            $a[$i]['busyUntil'] = time() + 45; $a[$i]['status'] = 'sending'; $camp = $c;
        }
        return array_values($a);
    });
    if ($busy) { fail(409, 'Ennél a kampánynál már fut egy kiküldés. Várj pár másodpercet.'); }
    if (!$camp) { json_out(nl_snapshot()); }
    @set_time_limit(60);
    $byId = array(); foreach (nl_list() as $s) { if (isset($s['id'])) { $byId[$s['id']] = $s; } }
    $done = array(); $sent = 0; $failed = 0; $lastErr = ''; $streak = 0; $t0 = microtime(true);
    foreach (array_slice($camp['queue'], 0, 20) as $sid) {
        if (microtime(true) - $t0 > 25) { break; }
        $done[] = $sid;
        if (!isset($byId[$sid]) || !isset($byId[$sid]['status']) || $byId[$sid]['status'] !== 'confirmed') { continue; }
        $err = nl_send_one($cfg, $camp, $byId[$sid]);
        if ($err) { $failed++; $streak++; $lastErr = $err; mail_log('HIBA hírlevél: ' . $err); if ($streak >= 3) { break; } }
        else { $sent++; $streak = 0; }
    }
    if ($streak >= 3) { array_splice($done, -$streak); $failed -= $streak; }
    mutate_json('nl_campaigns', function ($cur) use ($cid, $done, $sent, $failed, $lastErr, $streak) {
        $a = is_array($cur) ? json_decode(json_encode($cur), true) : array(); if (!is_array($a)) { $a = array(); }
        foreach ($a as $i => $c) {
            if ($c['id'] !== $cid) { continue; }
            $q = array_values(array_diff($c['queue'], $done));
            $a[$i]['queue'] = $q; $a[$i]['sent'] += $sent; $a[$i]['failed'] += $failed; $a[$i]['busyUntil'] = 0;
            if ($lastErr !== '') { $a[$i]['lastError'] = $lastErr; }
            $a[$i]['status'] = ($streak >= 3) ? 'paused' : (count($q) ? 'sending' : 'done');
        }
        return array_values($a);
    });
    json_out(nl_snapshot());
}
fail(400, 'Ismeretlen művelet');
