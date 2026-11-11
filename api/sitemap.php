<?php
// sitemap.xml – a nyilvános módban lévő termékekből és aloldalakból generálódik (hu + en változat, hreflang)
define('BK_API', 1);
require __DIR__ . '/_lib.php';
require __DIR__ . '/_seo.php';
$cfg = load_config();
header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: public, max-age=3600');
if (!$cfg || !site_is_public()) { http_response_code(404); echo '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"/>'; exit; }
$site = seo_site_url();
$urls = array(array('/', null, 'weekly', '1.0'));
foreach (seo_pages() as $k => $v) { $urls[] = array('/' . $k, null, 'monthly', '0.4'); }
foreach (seo_products() as $p) {
    if (!is_object($p) || !isset($p->id) || !preg_match('/^[A-Za-z0-9_-]{1,80}$/', $p->id)) { continue; }
    $urls[] = array('/termek/' . $p->id, isset($p->createdAt) && preg_match('/^\d{4}-\d{2}-\d{2}/', $p->createdAt) ? substr($p->createdAt, 0, 10) : null, 'weekly', '0.8');
}
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";
foreach ($urls as $u) {
    $hu = $site . ($u[0] === '/' ? '/' : $u[0]); $en = $site . '/en' . $u[0];
    $alts = $u[0] === '/' ? '' : '<xhtml:link rel="alternate" hreflang="hu" href="' . seo_h($hu) . '"/><xhtml:link rel="alternate" hreflang="en" href="' . seo_h($en) . '"/>';
    echo "<url><loc>" . seo_h($hu) . "</loc>" . ($u[1] ? "<lastmod>{$u[1]}</lastmod>" : '') . "<changefreq>{$u[2]}</changefreq><priority>{$u[3]}</priority>" . $alts . "</url>\n";
    if ($u[0] !== '/') { echo "<url><loc>" . seo_h($en) . "</loc><changefreq>{$u[2]}</changefreq><priority>{$u[3]}</priority>" . $alts . "</url>\n"; }
}
echo '</urlset>';
