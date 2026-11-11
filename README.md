# bagdikata.hu webshop

Kétnyelvű (HU/EN) webshop PHP-s tárhelyre (cPanel). A módosítások a GitHub Actions segítségével FTPS-en kerülnek fel a tárhelyre.

- `api/data/` – az éles adatok és titkok mappája, **nem kerül a tárolóba és feltöltéskor sem íródik felül**. Csak sablonok vannak itt (`*.example`).
- `api/mailtest.php`, `api/selftest.php` – diagnosztikai eszközök, az automatikus feltöltés kihagyja őket.
- Titkok: `FTP_SERVER`, `FTP_USERNAME`, `FTP_PASSWORD` (GitHub → Settings → Secrets and variables → Actions).
