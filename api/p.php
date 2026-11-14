<?php
define('BK_API', 1);
require __DIR__ . '/_lib.php';
require __DIR__ . '/_seo.php';
$cfg = load_config();
$site = seo_site_url();
$lang = (isset($_GET['lang']) && $_GET['lang'] === 'en') ? 'en' : 'hu';
$id = isset($_GET['id']) ? (string) $_GET['id'] : '';
$page = isset($_GET['page']) ? (string) $_GET['page'] : '';
$en = $lang === 'en';
$pub = $cfg && site_is_public();
$prefix = $en ? '/en' : '';
$title = 'Bagdi Kata'; $desc = ''; $img = ''; $imgFile = null; $path = ''; $hash = '/' . ($en ? 'en/' : ''); $ld = array(); $body = '';
if ($id !== '') {
    $p = preg_match('/^[A-Za-z0-9_-]{1,80}$/', $id) && $pub ? seo_find_product($id) : null;
    if (!$p) {
        if (!$pub) { header('Location: ' . $site . '/', true, 302); exit; }
        http_response_code(404);
        header('Content-Type: text/html; charset=utf-8'); header('X-Robots-Tag: noindex');
        echo '<!doctype html><meta charset="utf-8"><title>404 – Bagdi Kata</title><p>' . ($en ? 'Product not found.' : 'A termék nem található.') . ' <a href="' . seo_h($site) . '/">Bagdi Kata</a></p>';
        exit;
    }
    $name = $en && !empty($p->nameEn) ? $p->nameEn : (isset($p->name) ? $p->name : $id);
    $d = $en && !empty($p->descriptionEn) ? $p->descriptionEn : (isset($p->description) ? $p->description : '');
    $title = $name . ' – Bagdi Kata';
    $desc = trim(preg_replace('/\s+/', ' ', strip_tags($d)));
    list($img, $imgFile) = seo_image(isset($p->imageUrl) ? $p->imageUrl : '');
    if ($imgFile && function_exists('imagecreatetruecolor')) { $img = $site . '/api/ogimg.php?id=' . rawurlencode($id); $imgFile = null; $ogW = 1200; $ogH = 630; }
    $path = $prefix . '/termek/' . $id; $hash = ($en ? '/en' : '') . '/termek/' . $id;
    $price = isset($p->price) && is_numeric($p->price) ? (float) $p->price : null;
    $inStock = !isset($p->stock) || !is_numeric($p->stock) || $p->stock > 0;
    $ld = array('@context' => 'https://schema.org', '@type' => 'Product', 'name' => $name, 'description' => $desc, 'image' => $img ? array($img) : array(), 'sku' => $id, 'brand' => array('@type' => 'Brand', 'name' => 'Bagdi Kata'), 'url' => $site . $path);
    if ($price !== null) { $ld['offers'] = array('@type' => 'Offer', 'url' => $site . $path, 'priceCurrency' => 'HUF', 'price' => (string) $price, 'availability' => $inStock ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock', 'itemCondition' => 'https://schema.org/NewCondition'); }
    $body = '<h1>' . seo_h($name) . '</h1>' . ($img ? '<p><img src="' . seo_h($img) . '" alt="' . seo_h($name) . '" style="max-width:100%;height:auto;border-radius:8px"></p>' : '')
        . ($price !== null ? '<p class="price">' . seo_h(seo_money($price, $lang)) . '</p>' : '') . '<p>' . seo_h($d) . '</p>';
} elseif ($page !== '' && isset(seo_pages()[$page])) {
    $pg = seo_pages()[$page];
    $title = ($en ? $pg[1] : $pg[0]) . ' – Bagdi Kata'; $desc = $en ? $pg[3] : $pg[2];
    list($img, $imgFile) = seo_image('');
    $path = $prefix . '/' . $page; $hash = ($en ? '/en' : '') . '/' . $page;
    $body = '<h1>' . seo_h($en ? $pg[1] : $pg[0]) . '</h1><p>' . seo_h($desc) . '</p>';
} else { header('Location: ' . $site . '/', true, 302); exit; }
$w = isset($ogW) ? $ogW : 0; $h = isset($ogH) ? $ogH : 0;
if ($imgFile && is_file($imgFile)) { $sz = @getimagesize($imgFile); if ($sz) { $w = $sz[0]; $h = $sz[1]; } }
$canon = $site . $path;
$alt = ($en ? $site . preg_replace('#^/en#', '', $path) : $site . '/en' . $path);
$huUrl = $en ? $alt : $canon; $enUrl = $en ? $canon : $alt;
$app = $site . '/#' . $hash;
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: public, max-age=300');
header('Vary: User-Agent');
if (!$pub) { header('X-Robots-Tag: noindex, nofollow'); }
?><!doctype html>
<html lang="<?= $en ? 'en' : 'hu' ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= seo_h($title) ?></title>
<meta name="description" content="<?= seo_h($desc) ?>">
<link rel="canonical" href="<?= seo_h($canon) ?>">
<link rel="alternate" hreflang="hu" href="<?= seo_h($huUrl) ?>">
<link rel="alternate" hreflang="en" href="<?= seo_h($enUrl) ?>">
<link rel="alternate" hreflang="x-default" href="<?= seo_h($huUrl) ?>">
<link rel="icon" type="image/svg+xml" href="<?= seo_h($site) ?>/favicon.svg">
<meta property="og:site_name" content="Bagdi Kata">
<meta property="og:type" content="<?= $id !== '' ? 'product' : 'website' ?>">
<meta property="og:locale" content="<?= $en ? 'en_US' : 'hu_HU' ?>">
<meta property="og:title" content="<?= seo_h($title) ?>">
<meta property="og:description" content="<?= seo_h($desc) ?>">
<meta property="og:url" content="<?= seo_h($canon) ?>">
<?php if ($img): ?>
<meta property="og:image" content="<?= seo_h($img) ?>">
<meta property="og:image:secure_url" content="<?= seo_h($img) ?>">
<meta property="og:image:alt" content="<?= seo_h($title) ?>">
<?php if ($w && $h): ?>
<meta property="og:image:width" content="<?= (int) $w ?>">
<meta property="og:image:height" content="<?= (int) $h ?>">
<?php endif; ?>
<?php endif; ?>
<?php if ($id !== '' && isset($price) && $price !== null): ?>
<meta property="product:price:amount" content="<?= seo_h($price) ?>">
<meta property="product:price:currency" content="HUF">
<meta property="product:availability" content="<?= !empty($inStock) ? 'in stock' : 'out of stock' ?>">
<?php endif; ?>
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= seo_h($title) ?>">
<meta name="twitter:description" content="<?= seo_h($desc) ?>">
<?php if ($img): ?><meta name="twitter:image" content="<?= seo_h($img) ?>"><?php endif; ?>
<?php if (!$pub): ?><meta name="robots" content="noindex, nofollow"><?php endif; ?>
<?php if ($ld): ?><script type="application/ld+json"><?= json_encode($ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></script><?php endif; ?>
<?php if (!seo_is_bot()): ?><script>location.replace(<?= json_encode($app, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?>);</script><noscript><meta http-equiv="refresh" content="0;url=<?= seo_h($app) ?>"></noscript><?php endif; ?>
<style>body{font-family:Georgia,serif;max-width:640px;margin:32px auto;padding:0 16px;color:#161314;background:#f6f3ee}a.btn{display:inline-block;background:#161314;color:#fff;padding:10px 18px;border-radius:999px;text-decoration:none;font-family:system-ui,sans-serif}.price{font-size:1.3em;font-weight:600}</style>
</head>
<body>
<p><a href="<?= seo_h($site) ?>/">Bagdi Kata</a></p>
<?= $body ?>
<p><a class="btn" href="<?= seo_h($app) ?>"><?= $en ? 'View in the shop' : 'Megnézem a boltban' ?></a></p>
</body>
</html>
