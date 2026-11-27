<?php
define('BK_API', 1);
require __DIR__ . '/_lib.php';
require __DIR__ . '/_mail.php';
ensure_data();
$cfg = load_config();
if (!$cfg || !has_admin($cfg)) { fail(401, 'Admin belépés szükséges'); }
header('Cache-Control: no-store');
require_post();
if (isset($_FILES['logo']) && is_array($_FILES['logo'])) {
    $u = $_FILES['logo'];
    if (!empty($u['error']) || !is_uploaded_file($u['tmp_name'])) { fail(400, 'A feltöltés nem sikerült.'); }
    if ($u['size'] > 1024 * 1024) { fail(400, 'A logó legfeljebb 1 MB lehet.'); }
    $info = @getimagesize($u['tmp_name']);
    $map = array(IMAGETYPE_PNG => 'png', IMAGETYPE_JPEG => 'jpg', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp');
    if (!$info || !isset($map[$info[2]])) { fail(400, 'Csak PNG, JPG, GIF vagy WEBP kép tölthető fel.'); }
    $ext = $map[$info[2]];
    foreach (glob(DATA_DIR . '/mail-logo.*') ?: array() as $old) { @unlink($old); }
    if (!@move_uploaded_file($u['tmp_name'], DATA_DIR . '/mail-logo.' . $ext)) { fail(500, 'A logó nem menthető.'); }
    $id = 'c:' . $ext . ':' . sha1_file(DATA_DIR . '/mail-logo.' . $ext);
    $T = mtpl_get(); $T['logo'] = $id;
    write_raw('mail_tpl', json_encode($T, JSON_UNESCAPED_UNICODE));
    json_out(array('ok' => true, 'tpl' => $T, 'defaults' => mtpl_defaults(), 'logoUrl' => mtpl_logo_url_for($id), 'size' => array($info[0], $info[1])));
}
$d = body_json(40000);
$act = (is_object($d) && isset($d->action) && is_string($d->action)) ? $d->action : '';
if ($act === 'get') { $T = mtpl_get(); json_out(array('ok' => true, 'tpl' => $T, 'defaults' => mtpl_defaults(), 'logoUrl' => mtpl_logo_url_for($T['logo']))); }
if ($act === 'save') {
    $T = mtpl_clean(isset($d->tpl) ? json_decode(json_encode($d->tpl), true) : null);
    write_raw('mail_tpl', json_encode($T, JSON_UNESCAPED_UNICODE));
    json_out(array('ok' => true, 'tpl' => $T, 'defaults' => mtpl_defaults(), 'logoUrl' => mtpl_logo_url_for($T['logo'])));
}
if ($act === 'preview' || $act === 'test') {
    $T = mtpl_clean(isset($d->tpl) ? json_decode(json_encode($d->tpl), true) : null);
    $kind = (isset($d->kind) && in_array($d->kind, array('order', 'contact', 'withdrawal'), true)) ? $d->kind : 'order';
    $en = isset($d->lang) && $d->lang === 'en'; $L = $en ? 'en' : 'hu';
    $v = array('{nev}' => $en ? 'Anna Smith' : 'Kiss Anna', '{name}' => $en ? 'Anna Smith' : 'Kiss Anna', '{rendelesszam}' => 'BK-2611271234', '{azonosito}' => 'K-20261127-AB12C', '{email}' => 'anna@example.com');
    $C = $T[$kind]; $blocks = array(); $ref = '';
    if ($kind === 'order') {
        $ref = 'BK-2611271234';
        $blocks[] = (object) array('heading' => mtpl_fill($C['heading'][$L], $v), 'lines' => mtpl_lines(mtpl_fill($C['body'][$L], $v)));
        $blocks[] = (object) array('table' => (object) array('head' => $en ? array('Item', 'Qty', 'Amount') : array('Termék', 'Db', 'Összeg'), 'rows' => array(array($en ? 'Mint beanie' : 'Menta sapka', '1', '8 990 Ft'), array($en ? 'Shipping and fees' : 'Szállítás és díjak', '', '1 290 Ft'), array($en ? 'Total' : 'Összesen', '', '10 280 Ft'))));
        $blocks[] = (object) array('lines' => mtpl_lines(mtpl_fill($C['closing'][$L], $v)));
        $subject = mtpl_fill($C['subject'][$L], $v);
    } elseif ($kind === 'contact') {
        $ref = 'K-20261127-AB12C';
        $blocks[] = (object) array('heading' => mtpl_fill($C['heading'][$L], $v), 'lines' => mtpl_lines(mtpl_fill($C['body'][$L], $v)));
        $subject = mtpl_fill($C['subject'][$L], $v);
    } else {
        $data = $en ? array('Reference number: E-2611271234', 'Order number: BK-2611271234', 'Received: 27/11/2026 14:30', 'Scope: the whole order') : array('Hivatkozási szám: E-2611271234', 'Rendelésszám: BK-2611271234', 'Beérkezés ideje: 2026. 11. 27. 14:30', 'Hatály: a teljes rendelés');
        $wb = trim($C['body'][$L]);
        $blocks[] = (object) array('heading' => $C['heading'][$L], 'lines' => $wb !== '' ? array_merge(mtpl_lines($wb), array(''), $data) : $data);
        $subject = ($en ? 'Acknowledgement of your withdrawal – ' : 'Elállási nyilatkozat visszaigazolása – ') . 'E-2611271234';
    }
    mtpl_frame_begin($T, $en);
    list($text, $html) = mail_render((object) array('subject' => $subject, 'blocks' => $blocks, 'lang' => $L, 'ref' => $ref));
    mtpl_frame_end();
    if ($act === 'preview') { json_out(array('ok' => true, 'subject' => $subject, 'html' => $html)); }
    if (rl_blocked('mtpltest', 20, 3600)) { fail(429, 'Túl sok tesztlevél. Próbáld később.'); }
    rl_hit('mtpltest', 3600);
    try {
        $GLOBALS['bk_mail_nocopy'] = true;
        $mc = mail_cfg(); $sender = mail_sender_for($mc, $kind === 'contact' ? 'contact' : 'order');
        mail_send(array($cfg['admin_email']), '[TESZT] ' . $subject, $text, $html, $sender, $sender);
        unset($GLOBALS['bk_mail_nocopy']);
        mail_log('OK ügyféllevél-sablon teszt (' . $kind . ')');
    } catch (Throwable $e) { unset($GLOBALS['bk_mail_nocopy']); fail(502, 'A tesztlevelet nem sikerült elküldeni (lásd mail.log).'); }
    json_out(array('ok' => true));
}
fail(400, 'Ismeretlen művelet');
