# bagdikata.hu – munkaleírás (átadás Claude Code-nak)

Bagdi Kata kézzel kötött sapka webshopja, kétnyelvű (HU/EN), cPanel PHP tárhelyen. **A felhasználó magyarul kommunikál – minden válasz magyarul legyen.** A felhasználó e-mail címét ne írd ki parancsokba/üzenetekbe.

## Felépítés
- Frontend: lefordított (Vite+React) csomag az `assets/` mappában, **nincs forráskód**. A módosítás a minifikált JS-ek szövegcseréjével történik (Python `str.replace`, mindig `assert count==1`). Tailwind le van tisztítva → új UI inline stílussal vagy `index.html` `<style>` blokkba.
- Fő csomag: `assets/index-0f-fFOYT.js` (nyilvános oldal, útvonalak). Admin darabok: `ProtectedAdminLayout-fotos1.js` (menü, csoportosított), `SettingsPage-*.js`, `MediaLibrary-leg1.js` (Fotók), `ProductsPage-*.js`, `OrdersPage-*.js`, `ContentPage-*.js`, `AdminMarketing-leg1.js` stb.
- Termékcímek (slug): a névből, ékezet nélkül, `_` elválasztóval; JS: `bkSlug`/`bkSlugMap`/`bkPS` (fő csomag), PHP: `seo_slug`/`seo_slug_map` (`_seo.php`) – a kettőnek egyeznie kell; régi id-s cím → 301 a slugra (`p.php`).
- Hash-útvonalak: `#/admin/...`. Szerveres mód: `window.__BK` (`base`, `shared`, `admin`).
- Szerver: `api/*.php` (PHP 8). Segédek: `_lib.php` (`require_admin`, `read_raw`, `write_raw`, `mutate_json`, `load_config`, `json_out`, `fail`), `_media.php` (fotótár), `_report.php` + `cron_daily.php` (napi statisztikai mail), `_mail.php`.
- Adatok: `api/data/` (**nincs a repóban**, gitignore): `config.php` (admin_hash, site_hash, admin_email), `store/content/settings/media.json`, `img/` stb. Képek `srv:<32hex>.<ext>` néven.
- `save.php` a rendeléseket egyesíti (union merge) → törlés csak dedikált szerver-végponttal.
- Admin API minta: `fetch(window.__BK.base+'api/x.php',{method:'POST',credentials:'same-origin',headers:{'X-BK':'1','Content-Type':'application/json'}})`, a válaszban `ok:true` kell.
- Napi biztonsági mentés: `_backup.php` (adatok naponta/14 db, képek hetente/4 db → `api/data/backups/`), indítja `cron_daily.php` és `bk_daily_tick`; admin: `backup.php` + Beállítások › „Biztonsági mentések” kártya.
- Hírlevél-jelvény az admin menüben: `newsletter.php?unseen=1` (GET, admin) / `{action:"seen"}` (POST), adat: `api/data/nl_seen.json`; a menü (`ProtectedAdminLayout-*.js`) 700 ms-onként figyeli a hash-t (a router nem küld hashchange-et).
- **Új `settings` mező felvételekor** a fő csomag `Ze` (settings-normalizáló) függvényébe is fel kell venni, különben admin-belépéskor a kliens elhagyja és visszamenti a szerverre (a `content` `Zt`-je `...e`-vel kezd, az megtartja). 
- Google Analytics: beállítás `settings.analytics {enabled,gaId,propertyId}` (Admin › Statisztika, `StatisticsPage-*.js` GACard); a `boot.php` `ga` mezőben kiküldi (a „Hamarosan” oldalnak is) → `window.__BK.ga`; betöltő: `assets/bk-ga.js` (csak `bk_consent==="1"` után, admin nélkül, hash → szép útvonal page_view; a „Hamarosan” oldalon saját sáv + link). CSP-ben: googletagmanager, *.google-analytics.com, *.analytics.google.com.
- Facebook-megosztás: `content.share {title,description,image,frame,showImage}`; a `.htaccess` a megosztási robotokat (UA-lista) a főoldalon az `api/p.php?page=home`-ra irányítja (`seo_share()`), saját kép esetén `/assets/og-share.jpg` (a `.htaccess` az `api/ogimg.php?share=1`-re írja át; 1200×630, fókuszpont – azért az /assets/ alatt, mert a tárhely Imunify-kivétele csak a főoldalra és az /assets/-re szól); `img.php` a megosztási képet jelszó nélkül is kiadja (`share_has_image`).
- Élő statisztika: `live_push()` (`_lib.php`) → `api/data/live.json` (utolsó ≤80 esemény ≤2 óra + percenkénti oldalletöltés-számláló); hívja a `track.php` (pageview + útvonal, view/click/cart/share), `newsletter.php`, `contact.php`, `order.php`; admin végpont: `api/live.php`; UI: `StatisticsPage-*.js` `LivePanel` (5 mp-es frissítés). Napi oldalbontás: `analytics.json` → `[nap].pages[útvonal]` (≤300 útvonal/nap), UI: `PagesCard`. A „Hamarosan” oldal a közös csomagból küld pageview-t (`/hamarosan`), a `track.php` jelszó nélkül csak pageview-t fogad.
- Napi jelentés: cPanel cron óránként (`/usr/local/bin/php <gyökér>/api/cron_daily.php`) + tartalék: oldalbetöltés-trigger (`bk_daily_tick`).

- **Content-Security-Policy:** `index.html` `<meta http-equiv>` (a `.htaccess`-ben csak frame-ancestors/base-uri/object-src). Új külső szkript-, betűtípus-, kapcsolat- vagy keretforrásnál a CSP-t is bővíteni kell. Helyi teszthez az `upgrade-insecure-requests` részt ki kell venni (http). Robotszűrő: Cloudflare Turnstile, kulcsok az `api/data/security.php`-ban (`turnstile_site`, `turnstile_secret`; Invisible mód).
- **A forráskódban nincsenek fejlesztői kommentek** (a felhasználó kérése): PHP, JS, `.htaccess`, `.cpanel.yml` – új kódba se kerüljön; a magyarázat ide, a CLAUDE.md-be való. Kivétel: az `index.html` szerzői jogi megjegyzése és az `api/data/*.example` kitöltési útmutatói.

## Kiadási szabály (minden módosításnál)
1. Új bejegyzés az `assets/changelog.js` tetejére (`window.__BK_CHANGELOG=[` után), `index.html`-ben a `changelog.js?v=<verzió>` emelése (patch/minor).
2. `node --check` (a bundle-öket `.mjs` másolaton), `php -l`.
3. `git pull --rebase origin main`, majd push a `main`-re (más is pusholhat; ütközésnél a changelog/index.html verziót kézzel kell összefésülni).
4. Éles telepítés a felhasználó részéről: cPanel Git „Update from Remote” → „Deploy HEAD Commit” → Ctrl+F5. `.cpanel.yml` a gyökérfájlokat, `assets/`-t és `api/*.php`-t másolja; `api/data` nem.

## Tesztelés
- Helyi teszt: a repó másolata egy mappába (pl. `/tmp/site2`) + `php -S 127.0.0.1:8766`; Playwright (Chromium). Admin teszthez az `api/data/config.php` `admin_hash`-ét ideiglenesen `password_hash("adminpw")`-re kell cserélni, utána visszaállítani; a belépés gombja `button:has-text("Belépés")`. A jelszó után **kétlépcsős kód** kell (szerveren: `api/_mfa.php`, `api/mfa.php`, adat: `api/data/mfa.json`): tesztben az `mfa.json` törlése után a belépés QR-kódos beállítással indul, a kulcs a `[data-testid=totp-secret]` elemből olvasható, a kód Node `crypto`-val (HMAC-SHA1, 30 mp) számolható. Vészkijárat élesben: `api/data/mfa.json` törlése (cPanel File Manager).
- Jelszófal tesztjéhez a `api/data/public.flag` fájlt kell eltávolítani (visszarakni utána).
- Az éles oldal a fejlesztői környezetből nem biztos, hogy elérhető.

## Nyitott tételek
- **Webshop megnyitásakor:** a főoldal megosztási címe/leírása/képe az adminban (Kezdőlap és tartalom › Facebook-megosztás) átírható; az alapértelmezés (`seo_share()` a `_seo.php`-ban, és az `index.html` statikus og-címkéi tartalékként) még a „Hamarosan” időszakra szól (`assets/og-hamarosan.jpg`). A `robots.txt` jelszavas módban csak a megosztási robotokat engedi (nyilvános módban a `robots-public.txt` él).
- Süti-sáv / Google Fonts adatvédelmi kérdés (saját tárolás vagy elhagyás).
- Jogi szövegek („vázlat”) átnézése élesítés előtt; `mail.php` base_url.
- Szerveren a régi `api/mailtest.php`, `api/selftest.php` törlendő; a cPanel cron-ban a régi `mailtest` sor törlendő, helyette `cron_daily.php`.
- `api/data/img_orig` (eredeti képek mentése) törölhető a cPanel File Managerben.
- Anthropic API-kulcs az Admin → Fotók oldalon (AI leírás-generáláshoz) – a valódi hívás végponttól végpontig nincs ellenőrizve.
- Kapcsolat-adatok admin szerkesztése; a „C/L” ajánló blokkok kézi választása.
