# Roadmap pre-release (prompt 14–32)

Analisi del 2026-10-07 sul repository `agri_la_volta-main` (zip GitHub, commit successivo a `95229a8`), aggiornata con le informazioni fornite dall'utente (12 e 13 completati in locale). Aggiornata il 2026-10-07 con le correzioni emerse dalla review `docs/REVIEW_PRE_ROADMAP.md`. Documento di supporto alla roadmap: **non autorizza modifiche al codice**. Le priorità sono proposte: l'utente le conferma rispondendo alle domande dei prompt.

## 1. Stato reale della roadmap esistente

| # | Prompt | Stato | Evidenza | Note |
|---|---|---|---|---|
| 00 | BOOTSTRAP | completed | `AGENTS.md`, `CLAUDE.md`, documenti condivisi | |
| 01 | AUDIT_REPOSITORY | completed | `docs/AUDIT.md`, decisioni P1–P7 | |
| 02 | ARCHITECTURE_DATABASE | completed | `app/`, `migrations/0001`–`0002`, `bin/migrate.php`, `bin/create-admin.php` | |
| 03 | FOUNDATION_MIGRATION | completed | SPA in `legacy/`, Firebase/pagamenti rimossi | Decisione P4 "eliminare `legacy/` dopo la Fase 5" **non eseguita** |
| 04 | BOOKING_AVAILABILITY | completed | `BookingService`, `AvailabilityService`, `ConcurrencyTest` | |
| 05 | PRICING | completed | `migrations/0003_pricing.sql`, `PriceCalculator` | Listino reale mancante (dato, non codice) |
| 06 | ADMIN | completed | `app/routes_admin.php`, `AdminAccessTest`, `AdminCsrfTest` | Cambio password solo da CLI |
| 07 | EMAIL_WHATSAPP | completed | `migrations/0004_email_outbox.sql`, `app/Mail/` | Consegna reale NOT RUN |
| 08 | PUBLIC_FRONTEND | completed | `templates/public/`, `RequestFlowController` | Testi delle pagine modificabili solo nel codice |
| 09 | I18N_SEO_A11Y_PERF | completed (con PARTIAL) | `Seo.php`, `ImageSet.php`, `ContrastTest` | Nessuna foto e nessuna gestione foto |
| 10 | SECURITY_PRIVACY_SPAM | completed | `docs/SECURITY_REVIEW.md`, `PersonalDataService` | |
| 11 | TEST_REGRESSION | completed | 763 test PASS, matrice 22 PASS / 5 PARTIAL | Prove manuali NOT RUN |
| 12 | DOCUMENTATION | completed (locale) | Dichiarato dall'utente il 2026-10-07; nello zip GitHub analizzato non era ancora visibile | Da verificare nel prompt 14 |
| 13 | FINAL_REVIEW | completed (locale) | Come sopra | Da verificare nel prompt 14 |
| 14 | RELEASE_PREP_NO_DEPLOY | pending | `docs/RELEASE_GUIDE.md` modello | Sostituito dal **31** |
| 90–95 | sessione/review/bugfix/handoff | utility | — | Invariati |

Correzione rispetto alla prima analisi: avevo indicato 12 e 13 come non eseguiti perché lo zip di GitHub non era sincronizzato. L'utente ha confermato che sono completati in locale.

Discrepanze da sistemare nel prompt 14: numerazione delle fasi diversa tra `PLAN.md` e `SESSION_STATE.md`; riferimenti al vecchio prompt 14; decisione P4 aperta (si chiude nel 28); template morto `templates/pages/home.php` (rimozione nel 28).

## 2. Perché la sequenza è questa

- **Documentazione (12) e review (13)** descrivono il progetto prima della gestione contenuti: dopo le fasi 15–28 vanno **aggiornate** (29, 30), non riscritte.
- **Correzioni dell'esistente prima del CMS (15)**: la review ha trovato problemi nel codice attuale (anonimizzazione, rifiuto con un clic, doppio invio, migrazioni…). Si correggono prima di costruire dieci fasi sopra, e i test-guardiani proteggono tutto il resto.
- **Strumenti di sistema divisi in due (26, 27)**: diagnostica ed email sono utili e a basso rischio; aggiornamenti del database, backup e conservazione dei dati sono irreversibili e meritano una sessione, test e review a sé.
- **Verifica sull'hosting reale (32)**: in Docker non si vedono `.htaccess` ignorati, proxy, estensioni, email vere e ripristino del backup.
- **Guardrail comuni** (`docs/GUARDRAIL_FASI.md`): regole per gli agenti valide in ogni fase (ramo Git, prove obbligatorie, contenuti come dati, nessun segreto).

## Requisiti confermati dall'utente

- **Minimo 2 persone per appartamento, come regola attivabile o disattivabile** (2026-10-07): registrata nel prompt 14, implementata nel prompt 23 con domande di dettaglio.

## 3. Valutazione CMS: cosa serve davvero

Oggi una persona non tecnica **non può** modificare: testi delle pagine (`content/*.php`), recapiti e WhatsApp (`.env`), foto (script CLI + codice), servizi degli appartamenti (inesistenti). Può già modificare dall'admin appartamenti (nome, descrizioni IT/EN, regole, SEO, capienza, orari, prezzo indicativo), listino e regole.

Conclusione: serve una **gestione contenuti specifica e piccola**, non un CMS generico.

| Esigenza reale | Soluzione proposta | Scartato e perché |
|---|---|---|
| Recapiti, dati aziendali, link (mappa, TripAdvisor) | `site_settings` a **riga unica con colonne tipizzate** | key/value generico: niente tipi né vincoli, validazione sparsa |
| Testi di Home, Agriturismo, Dintorni, Contatti, Privacy, Cookie | pagine **fisse** (le rotte esistono già) con campi di pagina IT/EN + **un solo tipo di sezione** ordinabile: titolo, testo semplice, foto facoltativa, visibile sì/no | page builder, blocchi multipli, drag&drop, pagine arbitrarie (URL e SEO sono fissi in `Routes::PATHS`) |
| Formattazione | testo semplice: paragrafi separati da riga vuota, sempre con escape | WYSIWYG/HTML: richiede JavaScript e un sanitizzatore HTML (nuova superficie XSS) |
| Foto | **media library minima**: upload, varianti responsive con GD, alt IT/EN, dichiarazione di provenienza, eliminazione solo se non usata | cartelle, ritaglio, editor, AVIF obbligatorio |
| Foto e servizi degli appartamenti | estensione dell'**entità strutturata** esistente: `apartment_photos`, `amenities` + `apartment_amenities` | blocchi generici per gli appartamenti |
| SEO per pagina | meta title/description per pagina e lingua; canonical, hreflang, sitemap restano **derivati** | campi canonical manuali |
| Bozze e revisioni | flag "bozza legale" solo per Privacy/Cookie; storico già coperto da `audit_log` | workflow draft/published, versioni |
| Etichette UI, errori, testi delle email | restano nel codice (`content/*.php`, `MessageBuilder`) | modificarli dall'admin rischia di rompere segnaposto e flusso; firma email derivata dalle impostazioni |
| Menu e footer | derivati da rotte e impostazioni | menu editabile: le pagine sono fisse |

## 4. Gap e migliorie (classificate)

Il dettaglio dei finding della review è in `docs/REVIEW_PRE_ROADMAP.md`; le altre proposte sono inserite come domande nei prompt (`PROPOSTE_PRENOTAZIONI_LISTINO.md`, `PROPOSTE_PROGETTO.md`, `PROPOSTE_SITO_TITOLARE.md`).

| Priorità | Area | Problema / miglioria | Evidenza | Azione proposta | Prompt |
|---|---|---|---|---|---|
| ESSENTIAL | Roadmap | Stato e numerazione da riallineare (GitHub non sincronizzato, riferimenti al vecchio 14) | §1 | Verifica 12–13, sync, sostituzione del 14 con il 31 | 14 |
| ESSENTIAL | Contenuti | Testi pagine modificabili solo nel codice | `content/*.php`, `page.pending`, `templates/public/farm.php`, `around.php` | Pagine + sezioni amministrabili | 16, 21, 22 |
| ESSENTIAL | Contenuti | Recapiti e WhatsApp solo da `.env` (serve FTP) | `app/Site/Contacts.php`, DECISIONS Fase 5 | `site_settings` amministrabili, `.env` come riserva transitoria | 18 |
| ESSENTIAL | Foto | Nessun modo di pubblicare foto senza CLI e modifiche al codice; SPEC §4 chiede foto per appartamento | `ImageSet.php`, `docs/IMAGES.md`, nessun `public/assets/img` | Libreria foto minima + galleria appartamento + hero | 19, 20, 21 |
| ESSENTIAL | Appartamenti | "Servizi" richiesti da SPEC §4 assenti | schema `apartments`; legacy `DatiAppartamenti.jsx` | Catalogo servizi IT/EN + assegnazione per appartamento | 20 |
| ESSENTIAL | Operatività | Creazione admin e cambio password solo da CLI: senza SSH il titolare non può cambiare password | `docs/COMMANDS.md` §Amministratore | Cambio password dall'admin + procedura senza SSH + riautenticazione riusabile | 17 |
| ESSENTIAL | Legale | Ragione sociale, P.IVA, REA non mostrati nel nuovo sito (da verificare col consulente) | legacy `ContactUs.jsx`; `MISSING_DATA` | Campi in impostazioni, footer solo se compilati | 18 |
| ESSENTIAL | Sicurezza | Nuove superfici: upload, testi amministrabili, ID nelle rotte, strumenti di sistema | — | Controlli nello scope di ogni fase + threat model + review dedicata | 16, 19–21, 26–28 |
| ESSENTIAL | Upload | Limite corpo 1 MB globale incompatibile con l'upload foto | `public/index.php`, `public/.htaccess` | Eccezione solo per la rotta di upload | 19 |
| ESSENTIAL | Admin | Email (SMTP, mittente, destinatario notifiche) configurabili solo da `.env` | `.env.example`, `MailTransportFactory` | Impostazioni email dall'admin, email di prova | 26 |
| ESSENTIAL | Admin | Aggiornamenti del database solo da riga di comando | `bin/migrate.php` | Pagina "Aggiornamenti" con riautenticazione, backup e manutenzione | 27 |
| ESSENTIAL | Admin | Backup e log raggiungibili solo via FTP/hosting | `COMMANDS.md`, `storage/logs` | "Scarica backup" (27) e registro errori (26) nell'admin | 26, 27 |
| RECOMMENDED | Admin | Coda email e pulizie dipendono da cron/CLI, invisibili al titolare | `bin/send-queued-mail.php`, `bin/privacy.php` | Attività pianificate a ogni visita + pagina con "Esegui ora" | 26 |
| ESSENTIAL | Contenuti | Non è definito cosa succede se un campo resta vuoto, né limiti e segnalazioni coerenti | prompt 18–23 prima della revisione | Contratto dei campi `docs/CAMPI_CONTENUTI.md`, frase "se lo lasci vuoto", indicatori, checklist | 16, 18–27 |
| ESSENTIAL | Contenuti | Minimo persone potrebbe superare la capienza (appartamento mai prenotabile) | — | Controlli tra campi | 23 |
| RECOMMENDED | Contenuti | Nessun modo di avvisare i visitatori di chiusure o novità | — | Avviso globale con data di fine | 18 |
| RECOMMENDED | Contenuti | Un testo cancellato per errore non si recupera | `audit_log` ha già i valori vecchi | "Ripristina" dallo Storico | 25 |
| RECOMMENDED | Email | Il titolare non può personalizzare le email | `MessageBuilder` | Messaggio personale IT/EN aggiunto ai modelli | 24 |
| ESSENTIAL | Documentazione | README e documenti del prompt 12 non descriveranno le funzioni nuove | — | Aggiornamento dopo l'hardening, manuale del titolare | 29 |
| RECOMMENDED | Privacy | Le foto da telefono contengono EXIF/GPS | — | Ricodifica che elimina i metadati; master senza EXIF | 16, 19 |
| RECOMMENDED | SEO | URL legacy `/dovesiamo` non reindirizzato | `legacy/src/App.jsx` | 301 → `/dintorni` | 22 |
| RECOMMENDED | Mappa | Legacy usava un iframe Google Maps (cookie di terzi) | `DoveSiamo.jsx`; SPEC §25 | Link a Google Maps da impostazioni, niente embed | 18 |
| RECOMMENDED | Email | Firma e recapiti nelle email scritti nel codice | `app/Mail/MessageBuilder.php` | Firma derivata dalle impostazioni | 18 |
| RECOMMENDED | UX pubblica | Dalla pagina appartamento il pulsante porta al modulo generico | `templates/public/apartment.php` | Appartamento preselezionato/evidenziato (mai fidato per il prezzo) | 25 |
| RECOMMENDED | Admin UX | 10 voci piatte, diventeranno ~15 | `templates/admin/layout.php` | Navigazione raggruppata, senza JS | 25 |
| RECOMMENDED | Admin UX | Il titolare non vede cosa manca per pubblicare | `PricingConfigService::coverageGaps` | Checklist "pronto per la pubblicazione" estendibile | 25, 26, 27 |
| RECOMMENDED | SEO | `og:image` assente | `layout.php`, DECISIONS Fase 6 | Derivata da hero/foto di copertina verificate | 20, 21 |
| RECOMMENDED | Database | Testato solo su MariaDB; versioni minime dichiarate fuori supporto | `COMMANDS.md`, migrazioni | Prova su MySQL 8.0/8.4 e MariaDB; requisiti aggiornati | 28 |
| RECOMMENDED | Pulizia | Template morto `templates/pages/home.php` | nessun riferimento | Rimozione dopo prova | 28 |
| RECOMMENDED | Rilascio | `legacy/` (~33 MB, foto di provenienza dubbia) nel repository; P4 aperta | `docs/IMAGES.md`, DECISIONS P4 | Decisione utente: eliminare o escludere dal pacchetto | 28, 31 |
| RECOMMENDED | Backup | Il backup documentato copre solo il DB; le foto caricate saranno file | `COMMANDS.md` §Backup | Backup = dump DB + foto e master, con prova di ripristino | 27, 29, 31 |
| OPTIONAL | Contenuti | Descrizione breve per le schede appartamento | `_apartment_card.php` | Campo IT/EN facoltativo | 20 |
| OPTIONAL | Contenuti | Galleria in home / FAQ | legacy `GallerySlider` | Coperte da sezioni con foto | 21 (se richiesto) |
| RECOMMENDED | Privacy | Esportazione/anonimizzazione dati solo da CLI (impossibile senza SSH) | `bin/privacy.php` | Azione in admin con riautenticazione e simulazione | 24 |
| OPTIONAL | Pubblico | Disponibilità visibile solo cercando | — | Calendario libero/occupato: **consigliato no** (sicurezza fisica); bastano le date alternative | 23, 25 |
| HIGH | Privacy | L'anonimizzazione lascia il motivo di cancellazioni e blocchi nello Storico (A1) | `BookingService::cancelBooking`, `PersonalDataTest` | Storico senza testo libero + test che scansiona tutto il database | 15 |
| HIGH | Admin UX | "Rifiuta richiesta" è un clic irreversibile che invia subito l'email (A3) | `templates/admin/requests/show.php` | Pagina di conferma con anteprima | 15 |
| MEDIUM | Integrità | Doppio invio del modulo = due richieste e due email (A2) | `FormToken` non monouso | Chiave di invio unica | 15 |
| HIGH | Pricing | Regola "per tutti" + regola specifica si sommano senza avviso (A4) | `PricingRepository`, `PriceCalculator` | Avviso sulle regole che si sommano | 23 |
| MEDIUM | Booking | Si può confermare una richiesta con arrivo già passato (A5) | `BookingService::confirmRequest` | Etichetta "scaduta" e blocco | 23 |
| MEDIUM | Admin | Disattivare un appartamento con prenotazioni future non avvisa (A6) | `ApartmentAdminService::update` | Avviso con elenco e conferma | 20 |
| HIGH | Hosting | `.htaccess` è l'unica barriera davanti a `.env`, sessioni e log (A7) | `.htaccess`, `public/.htaccess` | File di negazione, controllo HTTP in Stato del sistema, prove con `AllowOverride` ridotto, verifica dopo il rilascio | 15, 26, 28, 32 |
| MEDIUM | Hosting | `LimitRequestBody` può causare errore 500 con `AllowOverride` ridotto (A8) | `public/.htaccess` | Prova e documentazione | 28, 31 |
| MEDIUM | Hosting | Moduli in 403 se il sito risponde anche su `www` (A9) | `OriginCheck` | Redirect all'host canonico | 15 |
| MEDIUM | Sicurezza | Rate limit con race condition e IP condiviso dietro proxy (A10) | `RateLimiter`, `Request::ip` | Registra poi conta; `TRUSTED_PROXIES` facoltativa; email dopo molti accessi falliti | 15, 24 |
| HIGH | Database | Lo splitter delle migrazioni spezza testi con `;` e righe `--` (A11) | `Migrator::splitStatements` | Splitter che rispetta le stringhe | 15 |
| MEDIUM | Database | Migrazioni senza lock, senza checksum, senza tracciamento dei fallimenti (A12) | `Migrator` | Lock + `CHECKSUMS` (15); checksum nel DB e registro (27) | 15, 27 |
| MEDIUM | Hosting | Invio dopo la risposta solo con PHP-FPM; nessun `ignore_user_abort` (A14) | `DeferredWork` | LiteSpeed + `ignore_user_abort` | 15, 26 |
| MEDIUM | Privacy | Messaggi di errore del database nei log (A15) | `Logger` | Solo SQLSTATE e codice | 15 |
| LOW | Configurazione | `APP_ENV` sbagliato spegne le protezioni di produzione (A16) | `Config::isProduction` | Valori ammessi | 15 |
| ESSENTIAL | Foto | Limite di pixel non adatto a `memory_limit`; HEIC; scrittura non atomica; quota; `.htaccess` per PHP-FPM (C24–C39) | — | Controlli nel prompt della libreria foto | 19 |
| ESSENTIAL | Backup | Non si sa se un backup si può ripristinare (C47) | — | Prova di ripristino automatica + prova reale sull'hosting | 27, 32 |
| HIGH | Email | L'email di ricevuta può trasformare il modulo in strumento di spam (C19) | — | Nessun testo dell'ospite, tetto globale e per indirizzo | 24 |
| RECOMMENDED | Admin UX | Listino dell'anno nuovo dimenticato; email dell'ospite digitata male | — | Listino scoperto a 12 mesi in dashboard; suggerimento sui domini | 25 |
| RECOMMENDED | Admin | Due persone che salvano insieme: vince l'ultimo senza avviso (B14) | — | Blocco ottimistico riusabile | 18, 20, 21, 23 |
| RECOMMENDED | Hosting | Con il CMS ogni pagina dipende dal database (B12, B13) | — | Cache su file + pagina 503 con i contatti | 18 |
| RECOMMENDED | Sicurezza | Azioni sensibili senza riautenticazione (C2) | — | `ReauthGuard` riusabile | 17, 24, 26, 27 |
| HIGH | Versioni | PHP 8.1 e database minimi fuori supporto | `composer.json`, `COMMANDS.md` | PHP 8.3, MySQL 8.0.19+/MariaDB 10.6+ | 28 |
| RECOMMENDED | Qualità | Test eseguiti solo a mano | — | CI su GitHub (da autorizzare) | 28 |
| HIGH | Rilascio | Nessuna verifica sull'hosting reale | — | Verifica dopo la pubblicazione, ripetibile | 32 |
| NOT WORTH IT | CMS | Page builder, blocchi multipli, drag&drop, pagine arbitrarie, menu editabile | — | — | — |
| NOT WORTH IT | CMS | Rich text/WYSIWYG, workflow bozza/pubblicato, revisioni | — | Testo semplice; `audit_log` | — |
| NOT WORTH IT | CMS | Etichette UI, errori e testi email modificabili dall'admin | — | Restano nel codice | — |
| NOT WORTH IT | Media | Cartelle, ritaglio, AVIF obbligatorio, antivirus | — | — | — |
| NOT WORTH IT | Contenuti | Stelle/recensioni senza fonte | legacy `stelle` | — | — |
| NOT WORTH IT | Auth | Reset password via email | — | Procedura documentata senza SSH | 17 |

## 5. Nuova roadmap

| Ordine | File | Tipo | Obiettivo | Dipende da |
|---|---|---|---|---|
| 14 | `14_STATE_SYNC_CONTENT_AUDIT.md` | audit | Verifica 12–13, sync, guardrail e strategia Git, finding della review, inventario contenuti | 13 |
| 15 | `15_EXISTING_FIXES.md` | hardening | Correzioni dell'esistente (Storico, rifiuto, doppio invio, rate limit, host, migrazioni, coerenza) e test-guardiani | 14 |
| 16 | `16_CONTENT_MODEL_DESIGN.md` | design | Modello di gestione contenuti, contratto dei campi, menu admin, regole foto, threat model | 15 |
| 17 | `17_ADMIN_ACCOUNT_NO_SSH.md` | implementation | Cambio password dall'admin, procedura senza SSH, riautenticazione riusabile | 16 |
| 18 | `18_SITE_SETTINGS.md` | implementation | Impostazioni (recapiti, dati aziendali, link, avviso globale, valori comuni), cache, pagina 503, blocco ottimistico | 17 |
| 19 | `19_MEDIA_LIBRARY.md` | implementation | Upload sicuro, varianti, master, quota, provenienza; prova foto legacy in locale | 18 |
| 20 | `20_APARTMENT_PHOTOS_SERVICES.md` | implementation | Galleria, servizi, piano/accessibilità, confronto, avvisi sulle conseguenze | 19 |
| 21 | `21_PAGE_CONTENT.md` | implementation | Pagine fisse amministrabili con sezioni | 19 |
| 22 | `22_CONTENT_MIGRATION_LEGACY.md` | migration | Testi nel DB, testi legacy e contenuti nuovi nascosti, redirect | 18, 20, 21 |
| 23 | `23_BOOKING_RULES_PRICING.md` | implementation | Regola minimo persone (attivabile), regole di prenotazione, strumenti listino | 22 |
| 24 | `24_ADMIN_OPERATIONS_COMMUNICATION.md` | implementation | Arrivi e partenze, ricerca, note, email all'ospite, iCal, anonimizzazione dall'admin | 23 |
| 25 | `25_ADMIN_UX_PUBLIC_POLISH.md` | implementation | Menu admin, checklist estendibile, indicatori, ripristino testi, admin da telefono, rifiniture | 24 |
| 26 | `26_SYSTEM_DIAGNOSTICS_EMAIL.md` | implementation | Stato del sistema, email SMTP, attività pianificate, registro errori, manutenzione, coerenza | 25 |
| 27 | `27_SYSTEM_CRITICAL_OPERATIONS.md` | implementation | Aggiornamenti del database, backup con prova di ripristino, conservazione dei dati | 26 |
| 28 | `28_PRE_RELEASE_HARDENING.md` | hardening | Sicurezza, versioni PHP/DB, hosting meno permissivi, regressione, pulizia, legacy | 27 |
| 29 | `29_DOCUMENTATION_UPDATE.md` | release | Aggiornamento documentazione + manuale del titolare | 28 |
| 30 | `30_FINAL_REVIEW_UPDATE.md` | release | Aggiornamento della review finale, chiusura dei finding, prova generale | 29 |
| 31 | `31_RELEASE_PREP_NO_DEPLOY.md` (ex 14) | release | Guida di rilascio e checklist, senza deploy | 30 |
| 32 | `32_POST_DEPLOY_VERIFICATION.md` | verification | Verifica sull'hosting reale dopo una pubblicazione autorizzata (ripetibile) | 31 + deploy autorizzato |

Rinumerazione: il vecchio `14_RELEASE_PREP_NO_DEPLOY.md` è sostituito dal nuovo `31_RELEASE_PREP_NO_DEPLOY.md` (il vecchio si elimina con `git rm`, non si rinomina: vedi `COME_APPLICARE.md` e prompt 14).

Ogni prompt dal 14 contiene "Domande per l'utente" con risposte possibili e consiglio (✅), di due tipi: **[titolare]** (l'agente non risponde mai al posto tuo) e **[tecnica]** (vale ✅ se dici "consigliate"). Ogni prompt di implementazione termina con "Prova tu".

Scelta di sequenza: **fette verticali** (migrazione + servizio + admin + resa pubblica + test della propria area), come le fasi 2A–7 già eseguite. La migrazione dei contenuti resta separata (22) perché sostituisce testi già visibili.

## 5b. Se il tempo stringe

`docs/PLAN.md` indica come obiettivo indicativo il **31 ottobre 2026**, posticipabile. Diciannove fasi, ognuna con domande, prove e test di circa 7 minuti per esecuzione, difficilmente stanno in tre settimane; e il vero collo di bottiglia sono i contenuti del titolare (listino, foto, testi, recapiti).

Percorso minimo per una prima pubblicazione corretta:

| Fase | Nel percorso minimo | Note |
|---|---|---|
| 14, 15, 16 | sì | Base per tutto il resto: guardrail, correzioni dell'esistente, contratto dei campi |
| 17 | sì | Senza SSH il titolare non può cambiare la password |
| 18, 19, 20, 21, 22 | sì | Permettono al titolare di inserire recapiti, foto e testi da solo |
| 23 | in parte | Regola del minimo persone sì; strumenti del listino e altre proposte rinviabili |
| 24 | in parte | Valutare solo l'email "richiesta ricevuta" (con i tetti anti-spam); il resto dopo il lancio |
| 25 | in parte | Checklist di pubblicazione e admin da telefono; il resto dopo il lancio |
| 26 | in parte | Stato del sistema, email dall'admin e manutenzione sì; registro errori e coerenza (pagina) rinviabili |
| 27 | sì | Aggiornamenti e backup con prova di ripristino: senza, il titolare dipende da un tecnico e non è protetto |
| 28, 29, 30, 31 | sì | Da rifare comunque per ciò che è stato incluso |
| 32 | sì (dopo la pubblicazione) | È l'unica verifica sull'hosting reale |

Le voci rinviate restano nei prompt: dopo il lancio si riprendono con un nuovo giro di prompt (33 e seguenti), ripartendo dal foglio `docs/RISPOSTE_UTENTE.md`.

## 5c. Cosa si gestisce dall'admin e cosa no

Alla fine della roadmap il titolare gestisce dal browser: richieste, prenotazioni, blocchi, listino, appartamenti (con foto, servizi, regole), pagine e sezioni, foto, impostazioni del sito, avviso globale, email (server, mittente, messaggi, password), password dell'admin, backup, aggiornamenti del database, manutenzione, conservazione dei dati, registro errori, stato del sistema.

Restano fuori, per sicurezza o perché servono ad avviare il sito: credenziali del database, `APP_SECRET`, `APP_URL`, `APP_ENV`, `APP_DEBUG`, `APP_TIMEZONE`, `HSTS_MAX_AGE`, `PUBLIC_FORM_MIN_SECONDS`, `TRUSTED_PROXIES`. Si impostano una volta sull'hosting; l'admin ne mostra solo lo stato. Il ripristino di un backup resta guidato via phpMyAdmin.

## 6. Cosa resta comunque al titolare

Dati mancanti (`docs/MISSING_DATA.md`), foto con provenienza verificata, testi legali verificati, credenziali SMTP, hosting, prove manuali (`docs/MANUAL_CHECKLIST.md`). Nessuna fase della roadmap può inventarli.
