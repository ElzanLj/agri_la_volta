# Architettura

Descrive il progetto **così com'è** (2026-10-06, dopo la Fase 8). Per le ragioni delle scelte vedi `docs/DECISIONS.md`; per i requisiti `docs/SPEC.md`.

## In breve

PHP 8.1+ **senza framework**, MySQL/MariaDB (InnoDB), nessuna dipendenza obbligatoria in produzione tranne PHPMailer (cartella `vendor/` da caricare). Un solo punto d'ingresso (`public/index.php`), un autoloader interno (`App\` → `app/`), viste PHP semplici, nessun JavaScript, nessun servizio esterno obbligatorio. Composer e PHPUnit servono solo in sviluppo.

## Struttura

```text
public/index.php          front controller: legge la richiesta, instrada, invia la risposta, poi le email in coda
public/assets/            CSS, favicon (asset() aggiunge ?v=<data di modifica>)
public/.htaccess          rewrite verso index.php, cache/compressione asset, limite del corpo (1 MB)
.htaccess (radice)        riserva per hosting senza document root su public/: tutto passa da public/

app/bootstrap.php         autoloader, .env, errori, fuso orario, oggetto App
app/routes.php            rotte pubbliche (tabella Routes IT/EN) + robots/sitemap
app/routes_admin.php      rotte /admin con le tre guardie di prefisso
app/helpers.php           e(), url(), asset(), csrf_field(), t(), lurl(), old(), field_error()

app/Http/                 Request, Response (intestazioni di sicurezza), Router (guardie per prefisso), View
  Controllers/Site/       SiteController (pagine), RequestFlowController (flusso richiesta), SeoController
  Controllers/Admin/      Request, Booking, Block, Calendar, Apartment, Pricing, Email, Audit, Export (+ AdminController: login)
  Middleware/             PrivateResponse, RequireAdmin, VerifyCsrf
  Admin/                  filtri, etichette, messaggi per le pagine admin
app/Domain/               regole pure: StayDates, GuestCounts, Money, PriceCalculator, PriceQuote, eccezioni
app/Service/              BookingService (unico punto che scrive occupazione), AvailabilityService,
                          PricingConfigService, ApartmentAdminService, PersonalDataService, Services (composizione)
app/Repository/           accesso al database (PDO, query parametrizzate)
app/Security/             AdminAuth, Session, Csrf, OriginCheck, RateLimiter, AppSecret
app/Mail/                 coda email, trasporti (SMTP, log, non configurato), costruzione dei messaggi, bozza di cancellazione
app/Site/                 Routes, Locale, Text, Format, Contacts, Amenities, FormToken, Seo, ImageSet
app/Support/              Logger, AuditLog, Csv, WhatsApp, DeferredWork, ProductionCheck
templates/                layout.php, error.php, public/, admin/
content/it.php, en.php    testi fissi del sito pubblico (chiavi identiche, verificato da un test)
migrations/               0001 schema, 0002 appartamenti, 0003 prezzi, 0004 coda email, 0005 servizi degli appartamenti
bin/                      migrate, create-admin, send-queued-mail, privacy, check-production, optimize-images
storage/                  logs/, sessions/, mail/ (non versionati)
tests/                    Unit, Integration, Http, Concurrency, Support
```

## Vita di una richiesta HTTP

1. Apache (o il server PHP in test) manda tutto a `public/index.php`, tranne i file statici.
2. `Locale::fromPath` sceglie la lingua (`/en…` = inglese, altrimenti italiano). Un corpo oltre 1 MB riceve 413; un URL con `/` finale viene reindirizzato (301) a quello canonico.
3. `Router::dispatch` applica le **guardie di prefisso**: tutto `/admin` passa da `PrivateResponse` (no-store), `RequireAdmin` (autenticazione, default-deny anche per URL inesistenti) e `VerifyCsrf` (token + `Origin` su ogni metodo non sicuro). Le rotte pubbliche non hanno sessione né cookie.
4. Il controller usa i servizi e restituisce una `Response`; `Response::send` aggiunge le intestazioni di sicurezza (CSP, Permissions-Policy, COOP, nosniff, DENY, HSTS solo con `APP_URL` https) e toglie `X-Powered-By`.
5. Dopo la risposta `DeferredWork::flush` invia le email in coda (con PHP-FPM dopo aver chiuso la connessione, altrimenti in linea con timeout di 10 s).

## Convenzioni dei dati

- **Intervalli** `[check_in, check_out)`: il giorno di partenza è libero per l'ospite successivo; blocchi e tariffe usano la stessa convenzione.
- **Denaro** in centesimi interi (EUR), mai decimali. **Date/ora** DATETIME in UTC; le date di soggiorno sono date di calendario.
- **Nessun dato di business nelle migrazioni** salvo i nomi dei sei appartamenti: prezzi, regole, capienze e testi li inserisce il titolare dall'admin.
- Ogni modifica di schema = una nuova migrazione, mai modificare quelle già applicate; solo in avanti.

## Schema del database

Dodici tabelle InnoDB, `utf8mb4_unicode_ci`. Ricavato da `migrations/` (e coperto da test).

| Tabella | Scopo | Campi principali / vincoli |
|---|---|---|
| `schema_migrations` | migrazioni applicate | `version` (PK), `applied_at` |
| `admin` | l'unico amministratore | `username` (unico), `password_hash` (`password_hash` di PHP), `last_login_at` |
| `apartments` | i sei appartamenti | `slug` (unico), `name`, `is_active`, `accepts_online_requests`, `management_mode` (`direct`/`agency`), `managing_agency`, `max_guests`, `max_children`, `max_pets`, `bedrooms`, `beds`, orari di arrivo/partenza, `indicative_price_cents` (solo "da…", mai usato per calcolare un totale), `sort_order`. Campi sconosciuti = NULL |
| `apartment_translations` | testi IT/EN per appartamento | PK (`apartment_id`, `locale`), `description`, `rules`, `amenities` (servizi, una voce per riga), `meta_title`, `meta_description` |
| `booking_requests` | richieste pubbliche | `reference` (unico, `LV-XXXXXXXX`), appartamento, date, `adults/children/pets`, `first_name`, `last_name`, `email`, `phone`, `notes`, `locale`, `quoted_total_cents` (NULL = "prezzo da confermare"), `price_breakdown` (istantanea JSON del calcolo), `status` (`pending`/`confirmed`/`rejected`/`cancelled`), `privacy_accepted_at`, `decided_at`. CHECK su date, adulti ≥ 1, stato, lingua |
| `bookings` | soggiorni confermati da qualunque canale | `booking_request_id` (unico, NULL per le manuali), `origin` (`website`/`phone`/`email`/`agency`/`novasol`/`other`), `status` (`confirmed`/`cancelled`), date, ospiti, `guest_name`, `email`, `phone`, `total_cents`, `notes`, `cancelled_at`, `cancellation_reason`. **Solo `confirmed` occupa le date** |
| `availability_blocks` | date chiuse dall'admin | appartamento, `start_date`, `end_date` (CHECK `end > start`), `reason` |
| `seasonal_rates` | tariffa per notte, per appartamento e periodo | `label`, `label_en`, `start_date`, `end_date`, `nightly_rate_cents`, `min_nights`, `is_active` |
| `pricing_rules` | adulti, bambini, animali, supplementi | `apartment_id` (NULL = tutti), `applies_to` (`adult`/`child`/`pet`/`stay`), `charge_basis` (`per_night`/`per_stay`), `free_units`, `amount_cents`, finestra di validità, `label_it/en` |
| `email_outbox` | coda delle email | `type` (`new_request_admin`/`request_confirmed`/`request_rejected`/`cancellation`), id di richiesta/prenotazione (solo identificatori), `status` (`pending`/`sending`/`sent`/`failed`/`skipped`), `attempts`, `error_code`, `error_message` (ripulito), `retryable`, `next_attempt_at`, `locked_at`, `sent_at`; `subject`/`body` solo per la bozza di cancellazione scritta dall'admin |
| `audit_log` | storico delle modifiche | `entity_type`, `entity_id`, `action`, `summary`, `old_values`/`new_values` (JSON). Nessun dato personale; un solo admin, quindi nessun autore |
| `rate_limit_hits` | limitatore di richieste | `bucket`, `key_hash` (HMAC-SHA256 del client, mai l'IP), `created_at`; righe cancellate dopo 24 ore |

Chiavi esterne: gli appartamenti sono `RESTRICT` per richieste e prenotazioni, `CASCADE` per tariffe, regole, blocchi e traduzioni; l'outbox si cancella con la richiesta/prenotazione.

## Flussi principali

**Richiesta pubblica** (`RequestFlowController`, passi senza JavaScript). I passi 1-3 leggono soltanto. `BookingService::previewRequest` valida tutto e calcola il prezzo (stesso codice di `createRequest`); all'invio `createRequest` ricontrolla, salva la richiesta `pending`, scrive nell'audit e accoda l'email al gestore **nella stessa transazione**. Nessun importo, stato, appartamento o lingua inviato dal browser viene letto.

**Conferma** (solo admin). `confirmRequest` apre una transazione `READ COMMITTED`, prende `SELECT … FOR UPDATE` sulla **riga dell'appartamento** (il mutex per appartamento), ricontrolla sovrapposizioni con prenotazioni confermate e blocchi, crea la prenotazione, aggiorna la richiesta, scrive l'audit e accoda l'email al cliente. Ogni operazione che cambia l'occupazione passa da `BookingService`: nessun altro codice scrive in `bookings` o `availability_blocks`. Verificato con processi PHP reali in parallelo.

**Prezzo.** `PriceCalculator` (puro) somma tariffe per notte su più stagioni, adulti oltre quelli inclusi, bambini, animali e supplementi, rispetta il soggiorno minimo del periodo che contiene l'arrivo e restituisce un'istantanea JSON salvata con la richiesta. Senza listino il totale è NULL: la richiesta è accettata come "prezzo da confermare".

**Email.** Schema *outbox transazionale*: la riga nasce con la modifica, l'invio avviene **dopo il commit**, fuori da ogni transazione; un errore SMTP non tocca mai lo stato. Reclamo atomico (nessun doppio invio), tentativi con attesa crescente (5/30/120 minuti, massimo 4), errori permanenti non ritentati da soli, riprova manuale da Admin > Email, `bin/send-queued-mail.php` per il cron (facoltativo). I messaggi si costruiscono al momento dell'invio. La cancellazione prepara una bozza che l'admin modifica e invia esplicitamente.

**Lingue.** IT senza prefisso, EN sotto `/en`; tabella unica `Routes::PATHS`; testi in `content/*.php`; contenuti degli appartamenti da `apartment_translations`. Nessuna traduzione automatica; canonical e hreflang su ogni pagina indicizzabile, sitemap generata.

## Sicurezza (sintesi)

Query parametrizzate; escaping con `e()`; CSP senza `unsafe-inline`; admin con default-deny, CSRF e sessioni legate alla password; moduli pubblici con token firmato senza sessione, `Origin`, honeypot, controllo temporale e rate limit; log senza dati personali; strumenti per esportare e anonimizzare i dati. Dettagli, finding e rischi accettati in `docs/SECURITY_REVIEW.md`.

## Test

PHPUnit 10 in quattro suite: `unit` (regole pure, testi, contrasto, ambito), `integration` (database di test dedicato `agriturismo_test`, protetto dal suffisso `_test`), `http` (applicazione reale dietro il server PHP, cookie e redirect come un browser), `concurrency` (processi PHP paralleli). Un server SMTP finto prova 10 scenari di guasto. Esiti: `docs/TEST_REPORT.md`.
