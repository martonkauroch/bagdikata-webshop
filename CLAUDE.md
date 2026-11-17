# bagdikata.hu – munkaleírás (átadás Claude Code-nak)

Bagdi Kata kézzel kötött sapka webshopja, kétnyelvű (HU/EN), cPanel PHP tárhelyen. **A felhasználó magyarul kommunikál – minden válasz magyarul legyen.** A felhasználó e-mail címét ne írd ki parancsokba/üzenetekbe.

## Felépítés
- Frontend: lefordított (Vite+React) csomag az `assets/` mappában, **nincs forráskód**. A módosítás a minifikált JS-ek szövegcseréjével történik (Python `str.replace`, mindig `assert count==1`). Tailwind le van tisztítva → új UI inline stílussal vagy `index.html` `<style>` blokkba.
- Fő csomag: `assets/index-0f-fFOYT.js` (nyilvános oldal, útvonalak). Admin darabok: `ProtectedAdminLayout-fotos1.js` (menü, csoportosított), `SettingsPage-*.js`, `MediaLibrary-leg1.js` (Fotók), `ProductsPage-*.js`, `OrdersPage-*.js`, `ContentPage-*.js`, `AdminMarketing-leg1.js` stb.
- Hash-útvonalak: `#/admin/...`. Szerveres mód: `window.__BK` (`base`, `shared`, `admin`).
- Szerver: `api/*.php` (PHP 8). Segédek: `_lib.php` (`require_admin`, `read_raw`, `write_raw`, `mutate_json`, `load_config`, `json_out`, `fail`), `_media.php` (fotótár), `_report.php` + `cron_daily.php` (napi statisztikai mail), `_mail.php`.
- Adatok: `api/data/` (**nincs a repóban**, gitignore): `config.php` (admin_hash, site_hash, admin_email), `store/content/settings/media.json`, `img/` stb. Képek `srv:<32hex>.<ext>` néven.
- `save.php` a rendeléseket egyesíti (union merge) → törlés csak dedikált szerver-végponttal.
- Admin API minta: `fetch(window.__BK.base+'api/x.php',{method:'POST',credentials:'same-origin',headers:{'X-BK':'1','Content-Type':'application/json'}})`, a válaszban `ok:true` kell.
- Napi jelentés: cPanel cron óránként (`/usr/local/bin/php <gyökér>/api/cron_daily.php`) + tartalék: oldalbetöltés-trigger (`bk_daily_tick`).

## Kiadási szabály (minden módosításnál)
1. Új bejegyzés az `assets/changelog.js` tetejére (`window.__BK_CHANGELOG=[` után), `index.html`-ben a `changelog.js?v=<verzió>` emelése (patch/minor).
2. `node --check` (a bundle-öket `.mjs` másolaton), `php -l`.
3. `git pull --rebase origin main`, majd push a `main`-re (más is pusholhat; ütközésnél a changelog/index.html verziót kézzel kell összefésülni).
4. Éles telepítés a felhasználó részéről: cPanel Git „Update from Remote” → „Deploy HEAD Commit” → Ctrl+F5. `.cpanel.yml` a gyökérfájlokat, `assets/`-t és `api/*.php`-t másolja; `api/data` nem.

## Tesztelés
- Helyi teszt: a repó másolata egy mappába (pl. `/tmp/site2`) + `php -S 127.0.0.1:8766`; Playwright (Chromium). Admin teszthez az `api/data/config.php` `admin_hash`-ét ideiglenesen `password_hash("adminpw")`-re kell cserélni, utána visszaállítani; a belépés gombja `button:has-text("Belépés")`.
- Jelszófal tesztjéhez a `api/data/public.flag` fájlt kell eltávolítani (visszarakni utána).
- Az éles oldal a fejlesztői környezetből nem biztos, hogy elérhető.

## Nyitott tételek
- Süti-sáv / Google Fonts adatvédelmi kérdés (saját tárolás vagy elhagyás).
- Jogi szövegek („vázlat”) átnézése élesítés előtt; `mail.php` base_url.
- Szerveren a régi `api/mailtest.php`, `api/selftest.php` törlendő; a cPanel cron-ban a régi `mailtest` sor törlendő, helyette `cron_daily.php`.
- `api/data/img_orig` (eredeti képek mentése) törölhető a cPanel File Managerben.
- Anthropic API-kulcs az Admin → Fotók oldalon (AI leírás-generáláshoz) – a valódi hívás végponttól végpontig nincs ellenőrizve.
- Kapcsolat-adatok admin szerkesztése; a „C/L” ajánló blokkok kézi választása.
