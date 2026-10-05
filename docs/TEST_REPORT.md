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

## Fase 2A — booking e disponibilità (2026-10-05)

Ambiente: Docker, PHP 8.2.34, MariaDB 10.11, PHPUnit 10.5.66, database dedicato `agriturismo_test` (guardia: il nome deve finire con `_test`). Comando: `docker compose exec web composer test`.

| Suite | Test | Asserzioni | Risultato |
|---|---|---|---|
| `unit` (`StayDatesTest`, `GuestCountsTest`) | 85 | 105 | PASS |
| `integration` (disponibilità, regole, richieste, blocchi/cancellazione/atomicità) | 84 | 250 | PASS |
| `concurrency` (processi PHP reali con connessioni DB separate, 8 round per scenario) | 8 | circa 1400 | PASS |
| **Totale** | **177** | **1748 e 1751 nelle due esecuzioni** | **PASS in 2 esecuzioni consecutive, circa 75 s ciascuna** |

Scenari di concorrenza (ogni round: DB azzerato, N processi rilasciati nello stesso istante, poi controllo SQL indipendente delle sovrapposizioni):

1. 8 richieste per le stesse date confermate insieme: esattamente 1 conferma, 7 conflitti puliti, 7 richieste restano `pending`.
2. Stessa richiesta confermata da 8 processi: 1 sola prenotazione.
3. Conferma e rifiuto della stessa richiesta insieme: una sola decisione vince; stato e prenotazioni coerenti.
4. 3 conferme + 3 prenotazioni manuali + 2 blocchi sulle stesse date: o 1 prenotazione e 0 blocchi, o 0 prenotazioni e 2 blocchi.
5. 4 soggiorni adiacenti (check-out = check-in) in parallelo: tutti riescono.
6. 6 appartamenti diversi in parallelo: tutti riescono.
7. Cancellazione contro conferma sulle stesse date: la cancellazione riesce sempre; stato finale coerente.
8. 16 soggiorni casuali (seme fisso) in parallelo: nessuna sovrapposizione; successi dichiarati = prenotazioni salvate.

In tutti i round: nessun errore tecnico (nessuna eccezione, lock timeout o deadlock).

**Prova di sensibilità (mutation check, eseguita una volta a mano):** ho disattivato temporaneamente il lock sulla riga appartamento in `BookingService::withApartmentLock` (sostituendo `lockForUpdate` con una lettura semplice). Risultato: **3 test su 8 falliti** al primo round: 7 conferme su 8 per le stesse date, 6 prenotazioni confermate nello scenario misto, 19 coppie sovrapposte nello scenario casuale. Il codice originale è stato ripristinato (file identico alla copia salvata) e la suite è tornata verde. Questo dimostra che i test rilevano davvero le gare.

Difetti trovati durante la fase: nessuno nel codice di prodotto. Due errori miei nei test, corretti: un metodo helper in conflitto con `TestCase::status()` di PHPUnit, e uno scenario di concorrenza che creava una richiesta su date già occupate (la richiesta veniva correttamente rifiutata).

Limiti noti:

- Autorizzazione server-side delle azioni admin: **NON APPLICABILE ora**. Le azioni esistono solo come servizi e non sono esposte via HTTP; saranno testate in Fase 3 con l'interfaccia admin. Resta verificata la protezione di `/admin` (Fase 1).
- Provato solo su MariaDB 10.11; MySQL 8 non provato (comportamento atteso uguale per `SELECT ... FOR UPDATE` e `READ COMMITTED`).
- Nessun vincolo di esclusione a livello DB (MariaDB/MySQL non li supportano): l'invariante regge perché ogni scrittura passa da `BookingService`. Scritture dirette via SQL possono violarla.
- Il pricing è solo l'interfaccia `PriceQuoter` (implementazione nulla): `quoted_total_cents` resta NULL fino alla Fase 2B.

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
| Date non valide | `composer test` | PASS | `StayDatesTest` (17 formati x 2 campi), `RequestFlowTest`, `BookingRulesTest` |
| Checkout precedente/uguale al check-in | `composer test` | PASS | `StayDatesTest`, `BookingRulesTest`, `RequestFlowTest` |
| Numero notti | `composer test` | PASS | 9 casi: fine mese, febbraio bisestile/non, cambio ora legale (ott/mar), capodanno, 60 notti |
| Soggiorni consecutivi | `composer test` | PASS | check-out e check-in nello stesso giorno, notti singole adiacenti, 4 soggiorni adiacenti prenotati in parallelo |
| Overlap parziale | `composer test` | PASS | unit (16 casi, simmetria) + integrazione con DB |
| Overlap completo | `composer test` | PASS | identico, contenuto, contenente, una notte |
| Blocchi manuali | `composer test` | PASS | blocco occupa le notti, giorno finale libero, rimozione libera, blocco su prenotazione rifiutato |
| Conferma concorrente | `composer test` (suite `concurrency`) | PASS | 8 processi x 8 round: 1 sola conferma; con lock disattivato i test FALLISCONO come atteso (vedi sotto) |
| Prezzi stagionali | | NOT RUN | |
| Variazione adulti | | NOT RUN | |
| Variazione bambini | | NOT RUN | |
| Animali | | NOT RUN | |
| Autorizzazione admin | | NOT RUN | |
| Richiesta pubblica | `composer test` | PASS (solo livello servizio) | sempre `pending`, mai confermata; form/endpoint HTTP non ancora esistenti |
| Fallimento email | | NOT RUN | |
| Validazione form | `composer test` | PASS (solo livello servizio) | validazione server-side di date, ospiti, contatti, privacy; form HTML in Fase 5 |
| Cancellazione | `composer test` | PASS | date riaperte e riprenotabili, doppia cancellazione rifiutata, richiesta collegata `cancelled`, audit |
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
