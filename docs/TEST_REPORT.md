# Test report

> Regola: non segnare PASS un test che non è stato realmente eseguito.

## Ambiente

- Data: 2026-10-05 (baseline audit)
- Commit/branch: `07baca4` / `main`
- PHP: non installato
- Database: non installato (legacy usa Firebase, non contattato)
- Browser/device: nessuno (solo richieste HTTP al dev server)
- Note ambiente: Windows 11, Node v24.18.0, npm 11.16.0

## Baseline stack legacy (React/Vite)

| Controllo | Comando | Risultato | Note |
|---|---|---|---|
| Install | `npm ci --no-audit --no-fund` | PASS | warning allow-scripts esbuild/protobufjs |
| Lint | `npm run lint` | FAIL | 90 errori (42 prop-types, 25 no-unused-vars, 14 no-undef, 9 no-unescaped-entities) |
| Build | `npm run build` | PASS | warning: `fotoSalso3.jpeg` non risolto, CSS syntax, chunk JS 676 kB |
| Dev server | `npx vite --port 5179` | PASS | `/` e `/dovesiamo` → HTTP 200 |
| Audit dipendenze | `npm audit --omit=dev` | 14 vulnerabilità | 8 moderate, 6 high |

I test della tabella seguente riguardano il nuovo sistema PHP e non sono applicabili allo stack legacy.

## Fase 1 — fondamenta PHP/MariaDB (2026-10-05)

Ambiente: Docker, PHP 8.2.34 + Apache (document root = root repository, fallback `.htaccess`), MariaDB 10.11. Verifiche manuali eseguite con `curl` e client `mariadb`; nessuna suite automatica ancora.

| Verifica | Procedura | Risultato | Evidenza |
|---|---|---|---|
| Sintassi PHP | `php -l` su `app/ bin/ public/ templates/` | PASS | nessun errore |
| DB da zero + migrazioni | `php bin/migrate.php` su DB vuoto | PASS | 2 migrazioni applicate, 10 tabelle, InnoDB `utf8mb4_unicode_ci` |
| Idempotenza migrazioni | seconda esecuzione | PASS | "Nothing to migrate." |
| Import alternativo (phpMyAdmin) | file SQL importati col client in DB di prova, poi eliminato | PASS | `schema_migrations` con 0001 e 0002, 6 appartamenti |
| Seed appartamenti | `SELECT` | PASS | 6 righe, solo nome/slug, altri campi NULL |
| CHECK date (`check_out = check_in`) | INSERT in `bookings` | PASS (rifiutato) | errore 4025 `chk_bookings_dates` |
| CHECK origine non valida | INSERT `origin='airbnb'` | PASS (rifiutato) | `chk_bookings_origin` |
| CHECK stato richiesta non valido | INSERT `status='approved'` | PASS (rifiutato) | `chk_booking_requests_status` |
| FK appartamento inesistente | INSERT `availability_blocks` | PASS (rifiutato) | errore 1452 |
| Default stato richiesta | INSERT senza stato (in transazione, rollback) | PASS | `pending` |
| Connessione app → DB | dashboard admin | PASS | conteggi 0 richieste / 6 appartamenti |
| Routing | `/` 200, `/non-esiste` 404, `POST /` 405, `/admin/` → 301 `/admin`, `HEAD /` 200 | PASS | |
| File privati non raggiungibili | `/.env`, `/app/...`, `/migrations/...`, `/bin/...`, `/templates/...`, `/legacy/.../.env`, `/.git/config`, `/docker-compose.yml`, `/AGENTS.md` | PASS | tutti 404 dall'app, nessun contenuto |
| Directory | `/assets`, `/storage` | PASS dopo correzione | inizialmente loop di redirect + esposizione del percorso `/public/assets/` (FAIL); risolto con `DirectorySlash Off`; ora 404. `/public` → 403 senza contenuto |
| Asset statici | `/assets/css/site.css` | PASS | 200 |
| Header di sicurezza | risposta di `/` | PASS | CSP, X-Frame-Options, nosniff, Referrer-Policy; nessun cookie sulle pagine pubbliche |
| Limite dimensione richiesta | POST 1,1 MB | PASS | 413 |
| `create-admin.php` | password corta / non coincidente / username non valido / valida | PASS dopo correzione | inizialmente fatal error su `stream_isatty` dopo lettura STDIN (FAIL), corretto; ora exit 1/1/1/0 |
| Login senza CSRF | POST | PASS | 400 |
| Login utente inesistente / password errata | POST | PASS | 422, messaggio generico |
| Escaping input nel form | username `<script>` | PASS | reso come `&lt;script&gt;` |
| Login corretto | POST | PASS | 303 → `/admin`, nuovo ID di sessione, cookie `HttpOnly; SameSite=Lax` |
| Header admin | `/admin` | PASS | `X-Robots-Tag: noindex, nofollow`, `Cache-Control: no-store` |
| Accesso admin non autenticato | `/admin` | PASS | 303 → `/admin/login` |
| Logout con CSRF errato | POST | PASS | sessione mantenuta |
| Logout | POST con token | PASS | dopo logout `/admin` → login |
| Rate limiting login | 6 tentativi errati | PASS | 5×422 poi 429; anche la password corretta riceve 429 durante il blocco |
| Audit log | creazione admin | PASS | riga `admin / created` |
| Log senza segreti | ricerca della password di test nei log | PASS | 0 occorrenze |
| Errore DB in development | DB fermo, POST login | PASS | dettaglio eccezione mostrato (solo debug) |
| Errore DB in production | DB fermo, `APP_ENV=production` | PASS | pagina generica 500, dettaglio solo nel log |
| Cookie `Secure` in HTTPS | — | NOT RUN | ambiente locale solo HTTP |
| Legacy dopo spostamento | `cd legacy && npm run build` | PASS | stessi warning della baseline |

## Fase 1b — migrazione controllata (2026-10-05)

| Controllo | Prima | Dopo | Note |
|---|---|---|---|
| `npm run build` (legacy) | PASS, JS 676 kB (174 kB gzip) | PASS, JS 184 kB (60 kB gzip) | restano i warning su `fotoSalso3.jpeg` e sintassi CSS |
| `npm run lint` (legacy) | FAIL, 90 errori | FAIL, 43 errori (25 prop-types, 11 no-unused-vars, 7 no-unescaped-entities) | gli errori `no-undef` sono spariti con il codice morto; non corretti i restanti |
| `npm audit --omit=dev` (legacy) | 14 vulnerabilità (8 moderate, 6 high) | 2 moderate | dipendenze di produzione ora: react, react-dom, react-router-dom |
| Riferimenti a Firebase/nodemailer/pagamenti | presenti | assenti nel codice versionato | ricerca testuale fuori da `docs/`, `prompts/`, guide: nessun risultato tranne il file locale non tracciato `legacy/src/EmailStatus/.env` |
| `php bin/migrate.php --status` | — | PASS | 0001 e 0002 applicate |
| Pagine PHP | — | PASS | `/` 200, `/admin` 303, `/admin/login` 200, `/nope` 404, `/legacy/package.json` 404, `/.env` 404 |
| Dati nel DB | — | PASS | 6 appartamenti, 1 admin |
| `php -l` su tutti i file PHP | — | PASS | |
| Regressioni visibili del legacy | — | — | rimossi dal sito legacy: login/registrazione, flusso di prenotazione e pagamento, modulo contatti. Intenzionale; il legacy non è pubblicato. Nessun test manuale nel browser del legacy (NOT RUN) |


## Test automatici

| Test | Comando / procedura | Risultato | Evidenza / note |
|---|---|---|---|
| Date non valide | | NOT RUN | |
| Checkout precedente/uguale al check-in | | NOT RUN | |
| Numero notti | | NOT RUN | |
| Soggiorni consecutivi | | NOT RUN | |
| Overlap parziale | | NOT RUN | |
| Overlap completo | | NOT RUN | |
| Blocchi manuali | | NOT RUN | |
| Conferma concorrente | | NOT RUN | |
| Prezzi stagionali | | NOT RUN | |
| Variazione adulti | | NOT RUN | |
| Variazione bambini | | NOT RUN | |
| Animali | | NOT RUN | |
| Autorizzazione admin | | NOT RUN | |
| Richiesta pubblica | | NOT RUN | |
| Fallimento email | | NOT RUN | |
| Validazione form | | NOT RUN | |
| Cancellazione | | NOT RUN | |
| Export CSV | | NOT RUN | |

## Verifica manuale

| Area | Risultato | Browser/device | Note |
|---|---|---|---|
| Mobile | NOT RUN | | |
| Desktop | NOT RUN | | |
| Tastiera | NOT RUN | | |
| Pagine principali | NOT RUN | | |
| Italiano | NOT RUN | | |
| Inglese | NOT RUN | | |

## Problemi aperti

- Lint legacy in FAIL (90 errori): non corretto, stack destinato alla sostituzione.
- Nessuna suite di test automatica PHP: PHPUnit previsto in Fase 2A.
- Cookie `Secure` sotto HTTPS non verificato (NOT RUN).


## Convenzioni evidenza

Quando possibile registra:

- comando esatto;
- exit code/esito;
- test name o scenario;
- ambiente/DB;
- eventuale commit;
- per test manuali: browser/device e passaggi minimi.

Non incollare segreti, credenziali o dati personali reali nel report.
