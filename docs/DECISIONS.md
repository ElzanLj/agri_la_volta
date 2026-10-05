# Decision log

Registra qui solo decisioni tecniche o di prodotto realmente prese. Non usare il file per inventare requisiti.

| Data | Decisione | Alternative considerate | Motivo | Impatto/file |
|---|---|---|---|---|
| 2026-10-05 | `docs/SPEC.md` è la fonte di verità funzionale | Prompt singolo non versionato | Mantiene requisiti e vincoli nel repository | Tutto il progetto |
| 2026-10-05 | Workflow AI-agnostic Codex/Claude/Cursor | Regole separate e divergenti per agente | Consente di cambiare agente mantenendo una sola memoria/versione dei requisiti | `AGENTS.md`, `CLAUDE.md`, `.cursor/`, `docs/`, `prompts/` |

## Decisioni P1–P7 (proposte nell'audit 2026-10-05)

**Approvate in blocco dall'utente il 2026-10-05.** Dettagli in `docs/AUDIT.md`.

| # | Proposta | Alternative | Motivo |
|---|---|---|---|
| P1 | PHP 8.1+ senza framework, rendering server-side, PDO + MySQL/MariaDB | Laravel/Slim; mantenere React con API PHP | semplicità, SEO senza SSR JS, hosting condiviso, manutenzione interna |
| P2 | Composer solo per PHPMailer (+ PHPUnit in dev) | `mail()` nativa; nessun Composer | SMTP autenticato affidabile; test |
| P3 | CSS/JS vanilla senza build in produzione | mantenere Vite per asset | meno dipendenze e meno passaggi |
| P4 | Spostare la SPA esistente in `legacy/` (spostamento Git) come riferimento, eliminarla dopo la Fase 5 | riscrittura in place; cancellazione immediata | preserva contenuti/stile senza bloccare la nuova struttura |
| P5 | URL IT senza prefisso, EN con prefisso `/en/` | sottodominio; `?lang=` | semplice, hreflang chiaro |
| P6 | Conferma con transazione InnoDB + `SELECT … FOR UPDATE` sulla riga appartamento | lock applicativi; `GET_LOCK` | robusto e portabile su MySQL/MariaDB condiviso |
| P7 | `git rm --cached src/EmailStatus/.env` + `.gitignore` per `.env*` (eccetto `.env.example`); niente riscrittura storia senza autorizzazione | riscrittura storia (vietata senza autorizzazione) | la revoca della credenziale è la mitigazione reale |

## Altre decisioni

| Data | Decisione | Alternative considerate | Motivo | Impatto/file |
|---|---|---|---|---|
| 2026-10-05 | Ambiente di sviluppo locale con Docker (`docker-compose.yml`: PHP 8.2 + Apache, MariaDB 10.11) | XAMPP/Laragon; PHP portable | scelto e attivato dall'utente. **Solo sviluppo**: la produzione resta hosting condiviso senza Docker | `docker-compose.yml` |
| 2026-10-05 | Le foto coperte da copyright verranno rimosse | richiedere licenze | decisione dell'utente dopo l'audit | `src/assets`, Fase 6 |

## Decisioni Fase 1 — architettura e database (2026-10-05)

| Data | Decisione | Alternative considerate | Motivo | Impatto/file |
|---|---|---|---|---|
| 2026-10-05 | Autoloader PSR-4 interno (`App\` → `app/`), Composer rinviato a quando serve PHPMailer/PHPUnit | Composer subito | nessuna dipendenza esterna necessaria per le fondamenta; i namespace sono già compatibili con l'autoload di Composer | `app/bootstrap.php` |
| 2026-10-05 | Struttura `public/` (document root) + `app/`, `templates/`, `migrations/`, `bin/`, `storage/`; `.htaccess` in root come fallback che mappa tutto in `public/` | solo `public/`; file PHP sparsi nella root | funziona sia con document root configurabile sia senza, senza esporre file privati | `.htaccess`, `public/.htaccess` |
| 2026-10-05 | Migrazioni SQL solo in avanti, eseguite da `bin/migrate.php`; ogni file registra sé stesso in `schema_migrations` (compatibile con import phpMyAdmin) | tool di migrazione esterni; down-migration | semplicità su hosting condiviso; DDL MySQL non è transazionale | `migrations/`, `app/Database/Migrator.php` |
| 2026-10-05 | DATETIME in UTC (sessione DB `time_zone = '+00:00'`); date soggiorno come DATE; importi in centesimi interi | DATETIME locali; DECIMAL | nessuna ambiguità con l'ora legale; niente errori di arrotondamento | schema, `Connection.php` |
| 2026-10-05 | Intervalli semiaperti `[start, end)` anche per blocchi e tariffe stagionali | intervalli chiusi per blocchi/tariffe | un'unica regola di overlap in tutto il sistema | schema |
| 2026-10-05 | Stati separati: `booking_requests` (`pending/confirmed/rejected/cancelled`), `bookings` (`confirmed/cancelled`) con `origin`; solo `bookings.confirmed` e `availability_blocks` occupano date | tabella unica | SPEC §10: richiesta distinta dalla prenotazione | schema |
| 2026-10-05 | `bookings.guest_name` testo libero, email/telefono facoltativi | nome/cognome obbligatori | le prenotazioni manuali da agenzia possono avere solo un riferimento | schema |
| 2026-10-05 | Gestione agenzia come `management_mode` (`direct`/`agency`) + `managing_agency` + `accepts_online_requests` | tabella agenzie | SPEC §12 chiede solo la predisposizione; regole Novasol ancora ignote | `apartments` |
| 2026-10-05 | Migrazione `0002` inserisce solo nomi e slug dei 6 appartamenti (da SPEC §4); tutti gli altri campi NULL | nessun seed; seed da contenuti legacy | i nomi sono dati certi; il resto è da confermare | `migrations/0002_seed_apartments.sql` |
| 2026-10-05 | Foto, servizi/dotazioni e tabelle email/regole prezzi rinviate alle fasi che le usano | crearle ora | solo tabelle motivate (prompt 02); verranno aggiunte con nuove migrazioni | Fasi 2B, 4, 5 |
| 2026-10-05 | Sessioni PHP native in `storage/sessions` (GC proprio), avviate solo su admin/moduli; cookie `HttpOnly`, `SameSite=Lax`, `Secure` in HTTPS; timeout inattività 2 h, assoluto 12 h | sessioni in DB | semplice e sufficiente per un solo admin; le pagine pubbliche non impostano cookie | `app/Security/Session.php`, `AdminAuth.php` |
| 2026-10-05 | Rate limiting su tabella DB `rate_limit_hits` con chiave hash SHA-256, conservazione 24 h; login admin: 5 fallimenti / 15 min per IP | file su disco; servizi esterni | funziona su hosting condiviso; riusabile per i moduli pubblici | `app/Security/RateLimiter.php` |
| 2026-10-05 | `docker-compose.yml` versionato senza segreti (valori da `.env`), porte legate a `127.0.0.1`, document root del container = root del repository | lasciarlo non tracciato | ambiente locale riproducibile; esercita il fallback `.htaccess` | `docker-compose.yml`, `docker/php/Dockerfile` |

## Decisioni Fase 1b — migrazione controllata (2026-10-05)

| Data | Decisione | Alternative considerate | Motivo | Impatto/file |
|---|---|---|---|---|
| 2026-10-05 | Rimossi da `legacy/`: `Pagamenti`, `PrenotazioniPopUp`, `AuthPopup`, `BookingSystem.jsx`, `GenitoreComponente.jsx`, `ListaAppartamenti.jsx`, `firebaseConfig.js`, `EmailStatus/EmailServer.js`, `.env.example` Firebase | lasciarli fino alla Fase 5 | la sostituzione PHP (DB, config, admin auth) è pronta e verificata; SPEC §3 impone la rimozione dei pagamenti | `legacy/src/**` |
| 2026-10-05 | Rimosse le dipendenze `firebase`, `nodemailer`, `dotenv`, `all`, `react-datepicker`, `react-icons` da `legacy/package.json` | mantenerle | vietate (Firebase) o non usate | `legacy/package*.json` |
| 2026-10-05 | `legacy/` resta come sola sorgente di contenuti/stile (Hero, Appartamenti, DoveSiamo, CSS): non viene servita né pubblicata. Rimossi dal legacy il modulo contatti (usava il server Node) e la voce "Prenota" | riscrivere i componenti per il legacy | il form contatti e il flusso di prenotazione saranno nuovi in PHP (Fasi 2A/4/5) | `ContactUs.jsx`, `NavBar.jsx`, `App.jsx` |
| 2026-10-05 | Foto, hotlink e CSS legacy non migrati in `public/` in questa fase | migrare gli asset ora | le foto di provenienza dubbia vanno sostituite (decisione utente); i contenuti verranno riportati in Fase 5/6 con provenienza verificata | `legacy/src/assets` |
| 2026-10-05 | Node/Vite resta solo come strumento di build del legacy, non richiesto in produzione | rimuovere subito | utile per consultare/compilare il riferimento fino alla sua eliminazione (Fase 5) | `legacy/` |

## Decisioni Fase 2A — booking e disponibilità (2026-10-05)

Piano approvato dall'utente il 2026-10-05, con le quattro scelte confermate (punti 1-4 sotto).

| Data | Decisione | Alternative considerate | Motivo | Impatto/file |
|---|---|---|---|---|
| 2026-10-05 | Un blocco su date con una prenotazione confermata viene **rifiutato** con l'elenco dei conflitti; blocchi sovrapposti tra loro sono ammessi | creare comunque il blocco | evita dati incoerenti | `BookingService::createBlock` |
| 2026-10-05 | Una richiesta pubblica su date già non disponibili viene **rifiutata all'invio** | accettarla `pending` | SPEC §7: disponibilità ricontrollata lato server; la verifica vincolante resta alla conferma | `BookingService::createRequest` |
| 2026-10-05 | Tetti tecnici anti-abuso: 60 notti per richiesta/prenotazione, 366 per blocco, 2 anni di anticipo per le richieste pubbliche, max 20 persone e 10 animali. **Non sono regole commerciali** | nessun tetto | valori confermati dall'utente | `StayDates`, `GuestCounts` |
| 2026-10-05 | Composer e PHPUnit solo in sviluppo (immagine Docker); `composer.lock` versionato, `vendor/` ignorato; la produzione non richiede Composer | PHPUnit scaricato a mano | P2 | `Dockerfile`, `composer.json` |
| 2026-10-05 | Locking: ogni operazione che può cambiare l'occupazione apre una transazione `READ COMMITTED` e prende `SELECT ... FOR UPDATE` sulla riga dell'appartamento; il controllo di sovrapposizione si ripete sotto lock; timeout attesa 10 s (errore `BusyException`, ritentabile). Ordine fisso: appartamento, poi richiesta/prenotazione | lock a intervallo sul controllo di sovrapposizione; `GET_LOCK`; tabella notti con chiave unica | un solo lock per transazione = nessun deadlock; semplice e portabile (P6) | `BookingService` |
| 2026-10-05 | `rejectRequest` non prende il lock appartamento (non cambia l'occupazione), solo quello sulla riga della richiesta | lock appartamento anche qui | meno contesa; verificato dal test conferma/rifiuto concorrenti | `BookingService::rejectRequest` |
| 2026-10-05 | Origine `website` riservata alle richieste approvate; le prenotazioni manuali accettano solo `phone`, `email`, `agency`, `novasol`, `other` | `website` ammesso anche manualmente | SPEC §11: `website` = richiesta del sito | `BookingService::MANUAL_ORIGINS` |
| 2026-10-05 | L'audit delle richieste pubbliche non contiene dati personali (solo appartamento, date, ospiti, stato) | loggare anche nome/email | minimizzazione dei dati (SPEC §32) | `BookingService` |
| 2026-10-05 | Capienza controllata solo se `apartments.max_guests` è valorizzato (oggi NULL); gli animali non contano | capienza di default | dato mancante: nessuna capienza inventata | `GuestCounts::exceedsCapacity` |
| 2026-10-05 | Nessun trigger né vincolo di esclusione nel DB: invariante garantita dal servizio e verificata da una query indipendente nei test | trigger `BEFORE INSERT` sul DB | i trigger non sono affidabili su hosting condiviso; limite documentato | `tests/Support/Invariants.php` |
| 2026-10-05 | Il pricing è solo un'interfaccia (`PriceQuoter`, implementazione nulla): `quoted_total_cents` resta NULL | prezzi provvisori | niente importi inventati; Fase 2B | `app/Domain/PriceQuoter.php` |

## Decisioni Fase 2B — pricing (2026-10-05)

Piano e assunzioni approvati dall'utente il 2026-10-05 (punti 1-4 sotto confermati esplicitamente).

| Data | Decisione | Alternative considerate | Motivo | Impatto/file |
|---|---|---|---|---|
| 2026-10-05 | La tariffa base è **per appartamento e per notte**; adulti, bambini, animali e supplementi sono voci **additive** separate | prezzo per persona; regole che si sostituiscono a vicenda | modello semplice; il prezzo per persona resta esprimibile con tariffa base 0 | `seasonal_rates`, `pricing_rules` |
| 2026-10-05 | Il soggiorno minimo è quello del periodo che contiene la **data di arrivo**; i supplementi per soggiorno con finestra di validità si applicano se la data di arrivo è nella finestra | massimo tra i periodi toccati; periodo di uscita | convenzione tecnica coerente con la pratica di prenotazione; da confermare col titolare | `PriceCalculator` |
| 2026-10-05 | Esclusi da questa fase: sconti percentuali, sconti per età dei bambini, supplementi opzionali, tassa di soggiorno, sconti per soggiorni lunghi | motore di regole generico | mancano i dati e la richiesta non raccoglie l'età né le scelte opzionali; registrati in `MISSING_DATA.md` | — |
| 2026-10-05 | Un listino mancante o con lacune **non blocca** la richiesta: viene salvata con totale NULL e istantanea `unquoted` ("prezzo da confermare") | rifiutare la richiesta | non perdere richieste mentre il listino non è completo | `BookingService::createRequest` |
| 2026-10-05 | Bloccanti per la richiesta pubblica: soggiorno sotto il minimo, `max_children` e `max_pets` superati. Le prenotazioni manuali dell'admin non applicano minimo e limiti (la capienza `max_guests` resta applicata) | applicarli anche all'admin | l'admin può registrare eccezioni concordate al telefono | `BookingService` |
| 2026-10-05 | Vocabolario fisso di regole: `adult`/`child`/`pet`/`stay` × `per_night`/`per_stay`, con `free_units`, importo, finestra di validità opzionale, appartamento opzionale (NULL = tutti) | motore di espressioni generico | richiesto: estendibile ma non eccessivamente generico | `pricing_rules`, `ChargeRule` |
| 2026-10-05 | Il calcolo è in `PriceCalculator` (puro, senza DB né orologio, interi in centesimi); `ConfiguredPriceQuoter` lo alimenta dal DB. `PriceQuoter` restituisce sempre un `PriceQuote` (anche incompleto) invece di `null` | restituire `null`/lanciare eccezioni | permette di spiegare perché manca il prezzo | `app/Domain`, `app/Service` |
| 2026-10-05 | Il totale e l'istantanea del calcolo (`price_breakdown`, JSON versione 1) sono salvati con la richiesta e non cambiano se il listino viene modificato; alla conferma il totale passa a `bookings.total_cents` | ricalcolare alla conferma | il cliente riceve il prezzo che gli è stato mostrato | `booking_requests`, `bookings` |
| 2026-10-05 | Le tariffe attive di uno stesso appartamento non possono sovrapporsi (validato sotto lock sulla riga appartamento); le bozze inattive possono | vincolo solo nel calcolo | evita prezzi ambigui; il calcolatore segnala comunque `ambiguous_rate` | `PricingConfigService` |
| 2026-10-05 | Tutte le modifiche al listino finiscono nell'audit log con valori vecchi e nuovi | solo log applicativo | SPEC §15 ("prezzo modificato") | `PricingConfigService` |
| 2026-10-05 | Limiti tecnici di sicurezza sugli importi (max 100.000 euro per voce, 20 unità gratuite, periodi fino a 3660 notti): non sono regole commerciali | nessun limite | prevenire errori di inserimento e overflow | `PricingConfigService`, `PriceCalculator` |
| 2026-10-05 | Fixture di prezzo solo in `tests/Support/PricingFixtures.php`, etichette `[TEST]` e importi artificiali; un test verifica che le migrazioni non inseriscano tariffe, regole o limiti | fixture nelle migrazioni | separazione netta test/produzione | `tests/` |
| 2026-10-05 | `TransactionRunner` estratto da `BookingService` e condiviso con `PricingConfigService`; comportamento invariato (verificato dalla suite di concorrenza) | duplicare il codice | un solo punto con isolamento READ COMMITTED e gestione timeout/deadlock | `app/Database/TransactionRunner.php` |

## Decisioni Fase 3 — area amministrativa (2026-10-05)

Piano e le 5 scelte tecniche approvati dall'utente il 2026-10-05.

| Data | Decisione | Alternative considerate | Motivo | Impatto/file |
|---|---|---|---|---|
| 2026-10-05 | **Default-deny a livello di prefisso**: tre guardie (`PrivateResponse`, `RequireAdmin`, `VerifyCsrf`) coprono tutto `/admin`, anche gli URL inesistenti; solo `GET/POST /admin/login` sono esenti dall'autenticazione, nessuna rotta dal CSRF | controllo in ogni controller | una rotta dimenticata non può restare scoperta; un test enumera le rotte dal router | `app/Http/Router.php`, `app/Http/Middleware/`, `app/routes_admin.php` |
| 2026-10-05 | Anonimo: **GET → 303** verso il login, **qualunque scrittura → 401** senza eseguire nulla; token CSRF mancante/errato o Origin estraneo → **403** | 401 ovunque | redirect comodo per il browser, rifiuto netto per le scritture | `RequireAdmin`, `VerifyCsrf` |
| 2026-10-05 | CSRF: token di sessione (`hash_equals`, mai dalla query string) **più** controllo `Origin`/`Referer` quando presenti (`null` rifiutato); token e id di sessione rigenerati al login | solo token | difesa in profondità oltre a `SameSite=Lax` | `VerifyCsrf`, `AdminAuth`, `Csrf` |
| 2026-10-05 | Sessione **legata all'impronta SHA-256 dell'hash password** e riconvalidata nel DB a ogni richiesta admin; cambiare la password con `bin/create-admin.php` o eliminare l'account chiude tutte le sessioni | sessioni indipendenti dalla password | sicurezza dopo reset delle credenziali, senza nuove colonne | `AdminAuth` |
| 2026-10-05 | Il traffico anonimo non crea sessioni né cookie (la sessione parte solo se arriva il cookie, o sulla pagina di login) | sessione per ogni visitatore | meno file di sessione e nessun cookie sulle pagine pubbliche | `Session::hasCookie`, `AdminAuth::isAuthenticated` |
| 2026-10-05 | Nessuna modifica via GET: azioni solo POST; la cancellazione passa da una pagina di conferma (GET di sola lettura) | `confirm()` JavaScript | nessun JavaScript e nessuna azione accidentale | `routes_admin.php` |
| 2026-10-05 | Interfaccia spartana: HTML server-side, nessun JavaScript, CSP senza `unsafe-inline`; italiano soltanto; flash message in sessione | libreria UI, SPA | semplicità e manutenibilità (SPEC §2) | `templates/admin/` |
| 2026-10-05 | URL admin in italiano (`/admin/richieste`, `/prenotazioni`, `/calendario`, `/blocchi`, `/appartamenti`, `/listino`, `/storico`, `/export`) | URL inglesi | admin usato in italiano; non indicizzato | `routes_admin.php` |
| 2026-10-05 | Controller admin sottili: tutte le scritture passano da `BookingService`, `PricingConfigService` e il nuovo `ApartmentAdminService`; le letture da `AdminQueryRepository`; filtri validati da `ListFilters` (valori non validi → 400, mai usati nelle query) | query nei controller | nessuna logica di business duplicata | `app/Http/Controllers/Admin/` |
| 2026-10-05 | Importi digitati in euro (`80`, `80,50`, `80.5`) e convertiti in centesimi con `Money::parse`; massimo 2 decimali, niente separatore delle migliaia | importi in centesimi nel form | più naturale per il titolare | `Money` |
| 2026-10-05 | Campi numerici vuoti nei form valgono 0 (bambini, animali, quantità gratuite, ordine); parametri di filtro in forma di array rifiutati | trattarli come errore / ignorarli | trovati dai test; comportamento atteso dall'utente | `BookingController`, `PricingController`, `ListFilters` |
| 2026-10-05 | CSV: `;` + BOM UTF-8 + CRLF; neutralizzazione di formule (`=`, `+`, `-`, `@`, tab, CR) con apostrofo iniziale, **anche per i telefoni che iniziano con `+`**; lingua e date in ISO, orari locali `gg/mm/aaaa hh:mm`; massimo 20.000 righe | `,`; esenzione per i telefoni | sicurezza prima della comodità | `Csv`, `ExportController` |
| 2026-10-05 | Slug degli appartamenti immutabile; foto e servizi rinviati; prezzo indicativo solo per visualizzazione (mai nei calcoli) | slug modificabile | URL stabili (SEO) | `ApartmentAdminService` |
| 2026-10-05 | Test di sicurezza via **HTTP reale** (server PHP built-in sul DB di test, client con cookie), con confronto del database prima/dopo | simulazioni in memoria | misurano ciò che vede un browser | `tests/Support/TestServer.php`, `tests/Http/` |

## Template nuova decisione

- **Data:** YYYY-MM-DD
- **Decisione:**
- **Alternative considerate:**
- **Motivo:**
- **Vincoli della specifica coinvolti:**
- **Impatto:**
- **Reversibile:** sì/no
