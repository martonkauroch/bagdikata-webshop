# bagdikata.hu – projekt-térkép (ágak → mappák → fájlok)

> Csak dokumentáció: a fájlok fizikailag a helyükön maradnak (a minifikált bundle-ök és a
> `.cpanel.yml` a jelenlegi lapos elrendezésre épülnek). A `docs/` mappát a telepítés nem másolja ki.
> Állapot: v1.40.8 (2026-11-17).

```
bagdikata-webshop/
│
├── 1. GYÖKÉR – belépési pont és szerverbeállítás
│   ├── index.html ............ SPA-váz, SEO/OG meta, JSON-LD, Google Fonts, <style> blokk,
│   │                           script-betöltés (bk-intro.js, assets/changelog.js?v=…, fő bundle)
│   ├── bk-intro.js ........... első látogatáskori intró-animáció (localStorage: bk_intro_seen, ?intro kényszerít)
│   ├── .htaccess ............. jelszófal/nyilvános mód (api/data/public.flag), botszűrés,
│   │                           keresőbarát címek → api/p.php, sitemap.xml → api/sitemap.php,
│   │                           _*.php tiltás, biztonsági fejlécek, cache
│   ├── robots.txt / robots-public.txt ... zárt ill. nyilvános mód robots-a (a .htaccess választ)
│   ├── favicon.svg, mail-logo.png
│   ├── .cpanel.yml ........... cPanel „Deploy HEAD Commit”: gyökérfájlok + assets/ + api/*.php → public_html
│   ├── .gitignore ............ api/data/* kizárva (kivéve *.example)
│   └── README.md, LICENSE.txt, CLAUDE.md (munkaleírás), docs/ (ez a fájl)
│
├── 2. FRONTEND – assets/ (Vite+React build, forráskód nélkül; módosítás szövegcserével)
│   │
│   ├── 2.1 Mag (minden oldalon betöltődik)
│   │   ├── index-0f-fFOYT.js ........ FŐ CSOMAG (~390 KB): router, nyilvános bolt (kezdőlap,
│   │   │                              termékoldal, kosár/pénztár, rendelés-visszaigazolás), lazy-route-ok
│   │   │                              API: analytics, contact, coupon, credits, newsletter
│   │   ├── createLucideIcon-Bc0h7bbI.js  közös darab (~130 KB): ikon-gyár + SZERVERES RÉTEG
│   │   │                              (window.__BK): boot, login/logout, auth, save, append, order,
│   │   │                              track, upload, img, translate
│   │   ├── index-DxU-lOkS.css ....... (letisztított) Tailwind CSS
│   │   ├── changelog.js ............. window.__BK_CHANGELOG – kiadási napló (verziózott betöltés)
│   │   └── parallax.js .............. háttér-SVG parallax effekt
│   │
│   ├── 2.2 Nyilvános oldalak (lazy)
│   │   └── StorePages-leg1.js ....... #/aszf, #/adatvedelem, #/szallitas, #/impresszum,
│   │                                  #/elallas, #/kapcsolat, #/hirlevel   (API: contact)
│   │       (a fő csomagban: #/, #/termek/:id, #/rolunk, #/penztar, #/rendeles/:orderNumber)
│   │
│   ├── 2.3 Admin (#/admin/…)
│   │   ├── AdminLoginPage-IRidwtmX.js ...... #/admin/belepes
│   │   ├── ProtectedAdminLayout-fotos1.js .. admin-keret, csoportosított oldalmenü, jogosultság
│   │   ├── DashboardPage-BRyHrjKA.js ....... #/admin (vezérlőpult)
│   │   ├── Bolt
│   │   │   ├── ProductsPage-FgTnUej9.js .... #/admin/termekek        (API: describe – AI leírás)
│   │   │   ├── CategoriesPage-CBTRzccw.js .. #/admin/kategoriak
│   │   │   ├── OrdersPage-DbZbtf28.js ...... #/admin/rendelesek      (API: orders – törlés)
│   │   │   └── InvoicesPage-BT4XYWTA.js .... #/admin/szamlak
│   │   ├── Tartalom
│   │   │   ├── ContentPage-sajto2.js ....... #/admin/tartalom
│   │   │   ├── MediaLibrary-leg1.js ........ #/admin/fotok           (API: media, upload, img, describe)
│   │   │   └── GalleryUploader-DYxiAlzr.js . képfeltöltő komponens
│   │   ├── Marketing – AdminMarketing-leg1.js (több oldal egy csomagban)
│   │   │   ├── AdminNewsPage ............... #/admin/hirek
│   │   │   ├── AdminEventsPage ............. #/admin/esemenyek
│   │   │   ├── AdminCouponsPage ............ #/admin/kuponok         (API: coupons)
│   │   │   ├── AdminNewsletterPage ......... #/admin/hirlevel        (API: newsletter)
│   │   │   └── AdminLanguagePage ........... #/admin/nyelv           (API: translate, reports)
│   │   ├── Jog – AdminCompliance-leg1.js
│   │   │   ├── AdminWithdrawalsPage ........ #/admin/ellallasok
│   │   │   └── AdminLegalPage .............. #/admin/jogi-szovegek
│   │   ├── Elemzés / rendszer
│   │   │   ├── StatisticsPage-CktmdMVm.js .. #/admin/statisztika     (API: visitors)
│   │   │   ├── AuditPage-C3ZXWWn3.js ....... #/admin/naplo
│   │   │   └── SettingsPage-Dg897Hbx.js .... #/admin/beallitasok, #/admin/szamlazas
│   │   │                                     (API: sitepass, contact; mail-beállítás)
│   │   └── Közös admin-komponensek
│   │       Modal-O5sFS_mC, PageHeader-BYypCJGs, OrderStatusBadge-DEqHWIxQ, StockBadge-CvmEi_7d,
│   │       OutboxList-D2mFl8Nr, fields-Ipu0AmZf, integrations-f2FAs8Bh
│   │
│   ├── 2.4 Ikonok (Lucide, 1-1 apró darab)
│   │   arrow-up, chart-column, copy, external-link, file-text, key-round, mail, pencil,
│   │   rotate-ccw, rotate-ccw-clock, search, settings, shield-check, shopping-cart,
│   │   smartphone, triangle-alert
│   │
│   └── 2.5 Beépített képek
│       ├── Hős: hero-1…4-*.jpg, hero-*.png
│       ├── Termékek: ejkek-bordo, kobaltkek, lila (+2), menta, mustar, oliva, rozsda (+2)
│       │   sapka (+ „-reszlet” részletképek), szivarvany-nyakmelegito, szivarvany-szett
│       └── Arculat: mark-*.svg, wordmark-*.svg, watermark.png, watermark-dark.png
│
├── 3. BACKEND – api/ (PHP 8; _*.php = belső segéd, kívülről tiltott)
│   │
│   ├── 3.1 Segédkönyvtárak
│   │   ├── _lib.php ........ konfig (load_config), jogosultság (require_admin, has_site, has_admin),
│   │   │                     JSON-tár (read_raw, write_raw, mutate_json → api/data/<név>.json),
│   │   │                     rate-limit (rl_blocked), json_out/fail, ensure_data
│   │   ├── _mail.php ....... levélküldés, sablonok, napi tick (bk_daily_tick), audit
│   │   ├── _media.php ...... fotótár-index, kiskép-készítés, duplikátum-kezelés
│   │   ├── _report.php ..... napi statisztikai jelentés összeállítása
│   │   ├── _coupon.php ..... kuponszabályok, érvényesítés
│   │   ├── _newsletter.php . feliratkozók, kampányküldés
│   │   └── _seo.php ........ oldal-URL, aktív termékek listája SEO-hoz
│   │
│   ├── 3.2 Belépés és hozzáférés
│   │   ├── boot.php ........ induló állapot a frontendnek (+ napi tick tartalék-trigger)
│   │   ├── auth.php ........ jelszófal (látogatói jelszó) ellenőrzése
│   │   ├── login.php / logout.php .. admin munkamenet
│   │   └── sitepass.php .... látogatói jelszó / nyilvános mód állítása (admin)
│   │
│   ├── 3.3 Bolt és rendelés
│   │   ├── order.php ....... új rendelés leadása (kupon, hírlevél-feliratkozás)
│   │   ├── orders.php ...... rendelés törlése (admin, dedikált végpont)
│   │   ├── save.php ........ admin mentés (store/content/settings), rendelések union-merge-dzsel
│   │   ├── append.php ...... kimenő levelek (outbox) és elállások hozzáfűzése
│   │   ├── coupon.php ...... kupon ellenőrzése a pénztárban
│   │   └── coupons.php ..... kuponok kezelése (admin)
│   │
│   ├── 3.4 Média
│   │   ├── img.php ......... képkiszolgálás (srv:<32hex>.<ext>), kiskép
│   │   ├── upload.php ...... képfeltöltés (admin)
│   │   ├── media.php ....... fotótár-műveletek, képaláírások (admin)
│   │   ├── credits.php ..... képforrás-/szerzői adatok a nyilvános oldalnak
│   │   └── describe.php .... AI termékleírás (Anthropic API, kulcs: api/data/ai.php)
│   │
│   ├── 3.5 Marketing és kommunikáció
│   │   ├── contact.php ..... kapcsolati űrlap (rate-limit)
│   │   ├── newsletter.php .. feliratkozás + kampányok (admin)
│   │   └── translate.php ... HU→EN fordítás gyorsítótárral, használat-számlálással (admin)
│   │
│   ├── 3.6 SEO
│   │   ├── p.php ........... szerveroldali termék-/jogi oldal (keresőknek, /termek/<id>, /en/…)
│   │   ├── sitemap.php ..... /sitemap.xml
│   │   └── ogimg.php ....... megosztási (OG) kép termékhez
│   │
│   ├── 3.7 Statisztika és jelentés
│   │   ├── track.php ....... látogatásmérés (analytics, returning, visitors_today)
│   │   ├── analytics.php ... analitika lekérése (admin)
│   │   ├── visitors.php .... látogatói összesítés (admin)
│   │   ├── reports.php ..... jelentés kézi küldése/előnézete (admin)
│   │   └── cron_daily.php .. cPanel cron: napi jelentés (óránként fut, naponta egyszer küld)
│   │
│   └── 3.8 Adatok – api/data/ (NINCS a repóban, csak a szerveren)
│       ├── config.php ........ admin_hash, site_hash, admin_email
│       ├── mail.php, security.php, ai.php  (sablon: *.php.example)
│       ├── public.flag ....... ha létezik: nyilvános mód (nincs jelszófal, indexelhető)
│       ├── JSON-tárak: store, content, settings, media, coupons, newsletter, nl_campaigns,
│       │   audit, analytics, returning, visitors_today, translate_cache, translate_usage
│       ├── rl_*.json ......... rate-limit számlálók
│       └── img/ (feltöltött képek), img_orig/ (törölhető)
│
└── 4. KIADÁS ÉS ÜZEMELTETÉS
    ├── Változás → assets/changelog.js új bejegyzés + index.html ?v= emelés
    ├── Ellenőrzés → node --check (.mjs másolaton), php -l
    ├── git pull --rebase origin main → push main
    └── cPanel Git: Update from Remote → Deploy HEAD Commit → Ctrl+F5
```

## Fő adatfolyamok

| Folyamat | Frontend | API | Adat |
|---|---|---|---|
| Oldalbetöltés | fő csomag + createLucideIcon (szerveres réteg) | boot.php, track.php | settings, store, content, analytics |
| Jelszófal | fő csomag | auth.php | config.php (site_hash) |
| Vásárlás | #/penztar | coupon.php → order.php | store, coupons, newsletter |
| Admin mentés | admin oldalak | save.php | store/content/settings (+audit) |
| Rendelés törlése | OrdersPage | orders.php | store |
| Fotótár | MediaLibrary | upload.php, media.php, img.php, describe.php | media, img/ |
| Hírlevél / kupon | AdminMarketing | newsletter.php, coupons.php | newsletter, nl_campaigns, coupons |
| Napi jelentés | – | cron_daily.php / boot.php tick | analytics, returning, store → e-mail |
| Keresők | – | p.php, sitemap.php, ogimg.php (.htaccess átírással) | store, content |

## Megjegyzések
- A `README.md` még GitHub Actions + FTPS feltöltést említ, a repóban azonban nincs `.github/`; a tényleges telepítés a `.cpanel.yml`.
- A `createLucideIcon-*.js` neve félrevezető: a közös Vite-darabba került a szerveres adatréteg is.
- A `createLucideIcon-*.js` hivatkozik `selftest.php`-ra és `setup.php`-ra, ezek a repóban nincsenek (a selftest a szerverről törlendő).
