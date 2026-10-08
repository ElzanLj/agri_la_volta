# TODO operativo

Legenda: `[ ]` da fare · `[~]` in corso · `[x]` completato **e verificato** · `[!]` bloccato

Regole:
- non segnare `[x]` sulla sola base del codice scritto; serve la verifica pertinente;
- se una voce è bloccata da dati/accessi mancanti usa `[!]` e registra il motivo in `MISSING_DATA.md` o `SESSION_STATE.md`;
- mantieni una sola fonte di stato: questo file, non checklist parallele nelle chat.


## ▶ Roadmap pre-release 14–32 (aggiornato 2026-10-07)

La sequenza operativa ora è **14 → 32** (`prompts/README.md`, `docs/PRE_RELEASE_ROADMAP.md`, mappa 00–32 in `docs/PLAN.md`). Il promemoria qui sotto descrive lo stato al termine del vecchio prompt 14 (oggi 31) ed è ancora valido per ciò che riguarda dati mancanti e autorizzazioni.

- [x] 14 — sync dello stato, guardrail, audit dei contenuti (commit `c8aec3d` sul ramo `fase-14-state-sync`)
- [x] 15 — correzioni dell'esistente (commit `c424b28` sul ramo `fase-15-existing-fixes`; 905 test PASS, poi 906 con il test del Logger)
- [x] 16 — progetto della gestione contenuti (ramo `fase-16-content-model`; `docs/CMS_DESIGN.md`, `docs/THREAT_MODEL.md`, `docs/CAMPI_CONTENUTI.md` approvato; **nel working tree, non committato**)
- [x] 17 — account admin senza SSH (ramo `fase-17-admin-account`; **nel working tree, non committato**)
- [ ] 18 — impostazioni del sito (**prossimo passo**) · 19–30 · 31 (ex 14) · 32 (dopo un deploy autorizzato)

### Promemoria per le fasi 17–27 (dal design del prompt 16)

- [ ] **Prompt 19, prima di iniziare:** chiedere il consenso a modificare l'immagine Docker di sviluppo (`gd` con jpeg/webp, `exif`, `zip`, limiti di upload) e **riconfermare** che la copia delle foto è un master senza metadati e non il file originale (SPEC §23; `MISSING_DATA`)
- [ ] **Prompt 26, prima di iniziare:** se si sceglie la password SMTP nel database (opzioni B o C) scrivere in `AGENTS.md` l'eccezione a "SMTP tramite variabili d'ambiente", con il consenso dell'utente (finding A13)
- [ ] Prompt 18 e 19: aggiungere a `.gitignore` `/storage/cache/*`, `/storage/media/*`, `/public/media/*` (con `.gitkeep`) e un `.htaccess` di negazione nelle cartelle nuove di `storage/`
- [ ] Ogni fase: aggiornare `ScopeTest` (elenco esatto delle tabelle), `DatabaseTestCase::resetDatabase()`, `AdminAccessTest::REVIEWED_ADMIN_ROUTES`, `migrations/CHECKSUMS`, e le righe di `CAMPI_CONTENUTI.md` e `THREAT_MODEL.md`
- [ ] Prompt 18: quando le impostazioni sono nel database, valutare di togliere la riserva `.env` per i recapiti (decisione D4 = A: riserva transitoria)
- [ ] Titolare: verificare da quali paesi arrivano gli ospiti (terza lingua, D7) e le estensioni PHP dell'hosting (`MISSING_DATA`)

## ▶ Promemoria: da dove ripartire (aggiornato 2026-10-07)

**Sviluppo, review finale e preparazione al rilascio completati** (prompt 0-14). Il codice è pronto per la parte automatizzabile; **nulla è stato pubblicato** e nessun servizio esterno è stato contattato. Prossimo passo: dipende dal titolare — fornire i contenuti e le autorizzazioni, eseguire le prove manuali, scegliere l'hosting; poi seguire `docs/RELEASE_GUIDE.md`. Eventuali nuove richieste di sviluppo partono da un nuovo prompt (es. l'estensione per i contenuti modificabili dall'admin, se l'utente la riprende).

Per ripartire:
1. `docker compose up -d` e verifica della baseline: `docker compose exec web composer test` (attesi **791 test PASS**, circa 7 minuti; se `vendor/` manca: `docker compose exec web composer install`).
2. Leggere `docs/SESSION_STATE.md`, `docs/RELEASE_GUIDE.md`, `docs/DELIVERY_CHECKLIST.md`, `docs/FINAL_REVIEW.md`, `docs/MISSING_DATA.md`.
3. Come nelle fasi precedenti: **prima un piano conciso** da far approvare.

Da tenere presente:
- stato: 23 criteri PASS, 4 PARTIAL (18 tastiera, 19 responsive, 20 immagini, 24 hosting reale), 0 FAIL;
- **migrazioni**: l'ultima è `0007_data_constraints.sql` (0006: chiave di invio del modulo; 0007: vincoli); la prossima deve avere il numero successivo e non modificare le precedenti;
- dominio canonico scelto dal titolare: `https://www.agriturismolavolta.com` (redirect 301 dal dominio senza www, da configurare sull'hosting: `docs/RELEASE_GUIDE.md` §7);
- **restano NOT RUN**: prove manuali (`docs/MANUAL_CHECKLIST.md`), installazione su hosting reale, consegna email reale, HTTPS reale, reindirizzamenti e pagina di manutenzione `.htaccess`, Lighthouse/axe/penetration test;
- da fare **prima di pubblicare** (a carico del titolare): autorizzazioni A1–A8, hosting e dominio, HTTPS, credenziali SMTP, contenuti (listino, testi, servizi, foto, recapiti, WhatsApp), testi legali, periodo di conservazione dei dati, `APP_SECRET`;
- prima e dopo la pubblicazione eseguire `php bin/check-production.php --strict`;
- il nuovo design del titolare (Figma) cambierà il layout: i test `PublicSeoTest`, `PublicPagesTest` e `ContrastTest` controllano struttura, accessibilità e contrasto e vanno aggiornati con il nuovo layout;
- il ramo locale può essere avanti di alcuni commit rispetto a `origin/main`: l'agente non ha mai fatto `git push`;
- la cronologia Git contiene la credenziale Gmail del legacy (già revocata) e non è stata riscritta (decisione P7);
- strumenti: `bin/migrate.php`, `bin/create-admin.php`, `bin/send-queued-mail.php`, `bin/privacy.php`, `bin/check-production.php`, `bin/optimize-images.php` (mai eseguito: manca GD/WebP nel container).

Bloccanti/dati mancanti che non dipendono dal codice (vedi `docs/MISSING_DATA.md`): credenziali SMTP reali, testi definitivi (email, pagine, legali), numero WhatsApp e recapiti pubblici, listino prezzi reale, servizi e descrizioni degli appartamenti, foto con provenienza verificata, scelta dell'hosting, `APP_SECRET` di produzione, periodo di conservazione dei dati.

## Audit

- [x] Repository e struttura analizzati
- [x] Dipendenze analizzate
- [x] Immagini analizzate e provenienza dubbia segnalata
- [x] Codice morto individuato
- [x] Sistemi di pagamento individuati/rimossi in sicurezza (`Pagamenti` e `PrenotazioniPopUp` rimossi da `legacy/` senza leggerne i dati; nessuna dipendenza di pagamento)
- [x] Servizi/configurazioni esterne censiti
- [~] Possibili dati reali identificati senza esposizione (repo verificato; contenuto Firestore non accessibile → verifica titolare)
- [x] Baseline build/test documentata

## Fondamenta

- [x] Backend principalmente PHP (scheletro: front controller, router, view, admin login)
- [x] MySQL/MariaDB configurato (MariaDB 10.11 Docker, PDO)
- [~] Compatibilità hosting condiviso verificata (fallback `.htaccess` verificato in Docker; hosting reale non ancora scelto)
- [x] Ambiente locale PHP/MariaDB via Docker
- [x] `.env.example` creato
- [x] Segreti esclusi dal repository (App Password revocata; `.env` non tracciato e ignorato; resta nella cronologia Git, vedi P7)
- [x] Migrazioni SQL versionate
- [x] Gestione errori/404 predisposta

## Database

- [x] apartments (+ apartment_translations)
- [x] booking_requests
- [x] bookings
- [x] availability_blocks
- [~] seasonal_rates / struttura tariffe equivalente (tabella base; regole ospiti/animali/supplementi in Fase 2B)
- [x] admin
- [x] audit_log
- [~] eventuali tabelle supplementi/regole/traduzioni/email retry solo se necessarie (traduzioni appartamenti e rate limit fatti; foto/servizi Fase 5, email outbox Fase 4, regole prezzi Fase 2B)

## Booking e disponibilità

- [x] Intervalli `[check_in, check_out)`
- [x] Date validate lato server (`StayDates`)
- [x] Numero notti corretto
- [x] Disponibilità ricalcolata lato server (all'invio e di nuovo sotto lock alla conferma; il form pubblico arriva in Fase 5)
- [x] Nessuna sovrapposizione tra prenotazioni confirmed dello stesso appartamento
- [x] Controllo concorrenza/transazione implementato (lock sulla riga appartamento, READ COMMITTED)
- [x] Richiesta pubblica salvata come pending (servizio; endpoint/form in Fase 5)
- [x] Conferma manuale admin (servizio; interfaccia in Fase 3)
- [x] Rifiuto admin (servizio; interfaccia in Fase 3)
- [x] Prenotazioni manuali da canali esterni (servizio; interfaccia in Fase 3)
- [x] Blocchi disponibilità (servizio; interfaccia in Fase 3)
- [x] Cancellazione libera le date

## Prezzi

- [x] Tariffe modificabili senza codice (`PricingConfigService` con validazioni e audit; interfaccia admin in Fase 3)
- [x] Intervalli/stagioni supportati (`seasonal_rates`, soggiorni a cavallo di più stagioni)
- [x] Adulti supportati (adulti inclusi + extra per notte/soggiorno)
- [x] Bambini supportati (bambini gratis + a pagamento; limite `max_children`; niente fasce d'età: dato non raccolto)
- [x] Animali supportati (a pagamento per notte/soggiorno; limite `max_pets`)
- [x] Supplementi supportati (obbligatori, fissi o per notte, con finestra di validità; opzionali rinviati)
- [x] Soggiorno minimo configurabile se previsto (per periodo, deciso dalla data di arrivo)
- [x] Prezzo ricalcolato lato server prima del salvataggio (`createRequest`; prezzi del browser ignorati)
- [x] Nessun importo inventato (migrazioni senza dati di prezzo, verificato da test; fixture solo in `tests/` con etichetta `[TEST]`)

## Admin

- [x] Login/logout
- [x] Nessuna registrazione pubblica
- [x] Nuove richieste visibili (elenco richieste, dashboard)
- [x] Filtri periodo/appartamento/stato (anche origine sulle prenotazioni; filtri validati)
- [x] Dettaglio richiesta (con riepilogo prezzo calcolato dal server)
- [x] Conferma/rifiuto (nessuna email ancora: Fase 4)
- [x] Prenotazione manuale (con origine)
- [x] Cancellazione (con pagina di conferma; la bozza email è Fase 4)
- [x] Blocchi disponibilità (creazione, rimozione, rifiuto su prenotazioni)
- [x] Calendario semplice (tabella mensile, senza JavaScript)
- [x] Modifica prezzi/regole (tariffe, regole, date senza tariffa)
- [x] Modifica appartamenti (dati, capienza, limiti, orari, testi IT/EN; slug immutabile; foto e servizi rinviati)
- [x] Export CSV (richieste e prenotazioni, `;` + BOM, formule neutralizzate)
- [x] Audit log utile (registrazione + pagina di consultazione)

## Email e WhatsApp

- [x] SMTP da variabili d'ambiente (`MAIL_TRANSPORT`, `SMTP_*`, `MAIL_*`; credenziali reali non ancora fornite)
- [x] Richiesta salvata prima dell'email (outbox nella stessa transazione, invio dopo il commit)
- [x] Nuova richiesta → email gestore (con tutti i dati previsti dalla SPEC e link all'admin)
- [x] Conferma → email cliente (IT/EN)
- [x] Rifiuto → email cliente (IT/EN)
- [x] Cancellazione → bozza modificabile, non inviata automaticamente (invio solo con azione esplicita sul testo modificato)
- [x] Fallimento SMTP non perde dati (verificato con 13 tipi di errore e con un server SMTP finto in 9 scenari)
- [x] Fallimento registrato senza segreti/dati inutili (codice + testo ripulito; nessun indirizzo, password o nome nei log)
- [x] Retry previsto se utile (backoff 5/30/120 min, pulsante "Riprova", script per cron)
- [x] WhatsApp con messaggio precompilato modificabile (link `wa.me` verso i clienti dall'admin; funzione per il pulsante pubblico pronta, numero dell'agriturismo ancora mancante)

## Pubblico e contenuti

- [x] `/` (IT e EN, appartamenti letti dal DB; verificato da test HTTP)
- [~] `/agriturismo` (pagina e URL pronti; testo mancante: dato dal titolare)
- [x] `/appartamenti`
- [x] pagina singola per ogni appartamento (solo campi compilati dall'admin; 404 se inattivo)
- [~] `/dintorni` (pagina e URL pronti; testo mancante: dato dal titolare)
- [x] `/richiedi-disponibilita` (flusso a 4 passi fino a "Richiesta ricevuta"; prezzo e disponibilità dal server)
- [~] `/contatti` (recapiti mostrati solo se configurati: oggi mancano)
- [~] `/privacy` (bozza marcata, da verificare dal titolare)
- [~] `/cookie` (bozza marcata; descrive solo ciò che il sito fa oggi)
- [x] `/admin` non esposto nella nav pubblica (verificato da test)
- [x] IT e EN (contenuti separati, chiavi verificate da un test; EN provvisorio, da correggere a mano)
- [x] Nessun dato mancante inventato (campi vuoti omessi, foto = segnaposto, recapiti solo se configurati; verificato da test)

## UX / Accessibilità / SEO / Prestazioni

- [~] Responsive mobile/desktop (layout fluido con griglie e `min()`; **non provato su dispositivi reali**)
- [~] Navigazione tastiera (nessun elemento personalizzato, tutto nativo; controlli di struttura automatici; **prova manuale da fare**)
- [x] Focus visibile (`:focus-visible` con contorno 4,08:1; verificato da test sul CSS)
- [x] Label e messaggi errore accessibili (ogni controllo ha la sua label, errori collegati con `aria-describedby`, riepilogo con `role="alert"`; verificato da test su tutte le pagine)
- [x] Skip link (presente su ogni pagina)
- [x] Menu mobile accessibile (elenco sempre visibile che va a capo, nessun JavaScript: vedi DECISIONS Fase 6)
- [x] Alt text (segnaposto `role="img"` con etichetta; `ImageSet` richiede `alt`; foto reali ancora assenti)
- [x] Contrasto adeguato (rapporti WCAG calcolati da un test sulle variabili del CSS: min 4,5:1 per il testo, 3:1 per gli elementi non testuali)
- [x] prefers-reduced-motion
- [x] Title e description unici (per lingua; verificato da test)
- [x] canonical (pagine indicizzabili; verificato da test)
- [x] hreflang quando appropriato (it, en, x-default; verificato da test)
- [x] Open Graph (senza `og:image` finché non c'è una foto verificata)
- [x] robots.txt
- [x] sitemap (generata, con hreflang)
- [x] breadcrumb (visibile + dati strutturati)
- [x] 404/errori (anche in inglese sotto /en)
- [x] Schema.org solo con dati reali (BreadcrumbList; LodgingBusiness solo con recapiti configurati)
- [~] Immagini responsive/ottimizzate/lazy fuori above-the-fold (markup e script pronti e testati; **nessuna foto verificata**, script non eseguito)
- [~] Hero ottimizzata (supportata da `ImageSet` con `eager`; manca la foto)
- [x] JS/font/dipendenze non necessari rimossi (nessun JavaScript, nessun font o risorsa esterna; HTML < 20 KB, CSS < 30 KB)
- [ ] Verifica manuale con tastiera, screen reader e dispositivi mobili (NOT RUN)
## Sicurezza

- [x] Query parametrizzate (nessuna concatenazione di input; verificato con tentativi di SQL injection nei filtri)
- [x] Escaping output (area admin e pagine pubbliche verificate con payload XSS)
- [x] Validazione server-side (servizi, area admin e form pubblico)
- [x] CSRF (guardia a livello di prefisso su tutto `/admin`: token + controllo Origin, nessuna eccezione)
- [x] Sessioni sicure
- [x] HttpOnly/Secure/SameSite appropriati (verificati su HTTP e su sito https)
- [x] Hash password admin sicuro (`password_hash` default/bcrypt)
- [x] Rate limiting dove necessario (login admin; invio richiesta: 6 all'ora per IP)
- [x] Limiti richieste (corpo max 1 MB nel front controller e, anche con invio a blocchi, in Apache con `LimitRequestBody`)
- [x] Intestazioni di sicurezza (CSP, Permissions-Policy, COOP, nosniff, DENY, HSTS solo in HTTPS; `X-Powered-By` rimosso)
- [x] Nessun dato personale né segreto nei log (provato con valori-marcatore)
- [x] Nessuna fuga di informazioni negli errori 500, anche con `APP_DEBUG=true` in produzione
- [x] Hash degli IP del rate limit con chiave (HMAC)
- [x] Esportazione e anonimizzazione dei dati personali (`bin/privacy.php`); conservazione automatica spenta finché il titolare non decide `DATA_RETENTION_MONTHS`
- [x] Security review documentata (`docs/SECURITY_REVIEW.md`: nessun finding alto, 4 corretti, 2 rischi accettati)
- [x] Accesso admin controllato lato server
- [x] Honeypot/antispam semplice (honeypot, controllo temporale, rate limit, token firmato, Origin)
- [x] Nessun pagamento/dato carta (nessun codice o campo di pagamento nel progetto)

## Test e consegna

- [x] Test date non valide
- [x] Test checkout <= check-in
- [x] Test numero notti
- [x] Test soggiorni consecutivi
- [x] Test overlap parziale/completo
- [x] Test blocchi manuali
- [x] Test conferma concorrente (processi reali + prova con lock disattivato)
- [x] Test prezzi stagionali
- [x] Test variazioni adulti/bambini/animali
- [x] Test autorizzazione admin (matrice su tutte le rotte `/admin`, via HTTP reale)
- [x] Test richieste pubbliche (HTTP reale: flusso completo, disponibilità, prezzo lato server, antispam)
- [x] Test fallimento email
- [x] Test validazione form (HTTP reale, IT e EN, dati conservati, errori collegati ai campi)
- [x] Test cancellazione
- [x] Test export CSV
- [ ] Test manuale mobile/desktop/tastiera/IT/EN (**NOT RUN**: lista di controllo pronta in `docs/MANUAL_CHECKLIST.md`, da eseguire a cura del titolare)
- [x] README/installazione locale (`README.md`, `docs/COMMANDS.md`)
- [~] Installazione hosting condiviso (`docs/INSTALL_SHARED_HOSTING.md`; passaggi verificati in Docker, **nessun hosting reale provato**)
- [x] Import database (SQL importati a mano in un database vuoto e riconosciuti da `bin/migrate.php`)
- [~] Configurazione SMTP (variabili e comportamento documentati in `docs/COMMANDS.md`; guida di consegna in Fase 8; credenziali reali mancanti)
- [~] Creazione/modifica admin (`bin/create-admin.php` testato, cambio password chiude le sessioni; guida completa in Fase 8)
- [x] Backup/ripristino (`docs/OPERATIONS.md`; andata e ritorno con checksum identici)
- [x] Export CSV documentato (`docs/OPERATIONS.md` §4)
- [x] Limitazioni residue documentate (`README.md`, `docs/TEST_REPORT.md`, `docs/SECURITY_REVIEW.md`)
- [x] Informazioni mancanti documentate (`docs/MISSING_DATA.md`)
- [~] Checklist pubblicazione (compilata in `docs/DELIVERY_CHECKLIST.md`; i passi a carico del titolare restano da fare)


## Continuità / handoff

- [x] `SESSION_STATE.md` riflette la fase corrente
- [x] Decisioni tecniche significative registrate (`docs/DECISIONS.md`)
- [x] Dati mancanti aggiornati
- [x] Test report aggiornato
- [x] Nessuna modifica locale non compresa prima di cambiare agente (working tree pulito a fine sessione)

## Finding della review 2026-10-07

Fonte: `docs/REVIEW_PRE_ROADMAP.md` (tabella "Dove si chiude ciascun finding"; i numeri di prompt sono quelli attuali, 14–32). Il prompt 14 non ha corretto nulla; **il prompt 15 ha chiuso i finding segnati `[x]`** e avviato quelli `[~]` (la parte che resta ha la sua fase). Dettagli e prove: `docs/SECURITY_REVIEW.md` (riesame del 2026-10-07) e `docs/TEST_REPORT.md`.

### A — Problemi reali

- [x] A1 Anonimizzazione: testo libero rimasto nello Storico → **15** — **chiuso nel prompt 15**
- [x] A2 Doppio invio del modulo = richieste duplicate → **15** — **chiuso nel prompt 15**
- [x] A3 "Rifiuta richiesta" senza conferma, invia subito l'email → **15** — **chiuso nel prompt 15**
- [ ] A4 Regole di prezzo "per tutti" e specifica si sommano senza avviso → **23**
- [ ] A5 Conferma possibile con arrivo già passato → **23**
- [ ] A6 Disattivare un appartamento con prenotazioni future senza avviso → **20**
- [~] A7 `.htaccess` unica barriera davanti a `.env`, sessioni, log → **15**, 26, 28, 32 — fatto in 15: `.htaccess` di negazione; restano 26, 28, 32
- [ ] A8 `LimitRequestBody` può dare 500 con `AllowOverride` ridotto → **28**, 31
- [x] A9 Nessun redirect all'host canonico (moduli in 403 su `www`) → **15** — **chiuso nel prompt 15**
- [~] A10 Rate limit: race e IP condivisi → **15**, 24 — fatto in 15: race e proxy (D4 = A); resta 24
- [x] A11 Lo splitter delle migrazioni rompe testi con `;` → **15** — **chiuso nel prompt 15**
- [~] A12 Migrazioni senza lock, checksum, tracciamento dei fallimenti → **15**, 27 — fatto in 15: lock e `CHECKSUMS`; resta 27
- [ ] A13 Il prompt 26 contraddice `AGENTS.md` (password SMTP nel DB) → **26**
- [~] A14 Lavoro "dopo la risposta" senza PHP-FPM → **15**, 26 — fatto in 15: LiteSpeed e `ignore_user_abort`; resta 26
- [x] A15 Messaggi di errore del DB nei log → **15** — **chiuso nel prompt 15**
- [x] A16 `APP_ENV` sbagliato spegne le protezioni → **15** — **chiuso nel prompt 15**
- [x] A17 Pagina "ricevuta" mostra qualsiasi riferimento passato nell'URL → **15** — **chiuso nel prompt 15**
- [x] A18 Nessun avviso su cosa non scrivere in "motivo" e "note" → **15** — **chiuso nel prompt 15**

### B — Migliorie pre-release

- [~] B1 Verifica di coerenza dei dati (sola lettura) → **15** (servizio, comando), 26 (pagina, banner) — fatto in 15: servizio e comando; restano pagina e banner in 26
- [x] B2 Test di immutabilità delle migrazioni (`CHECKSUMS`) → **15** — **chiuso nel prompt 15**
- [x] B3 Test architetturale (niente `exec`/`eval`, URL esterne, Node) → **15** — **chiuso nel prompt 15**
- [x] B4 Idempotenza dell'invio pubblico → **15** — **chiuso nel prompt 15**
- [x] B5 Conferma del rifiuto con anteprima → **15** — **chiuso nel prompt 15**
- [ ] B6 Email dell'ospite ben visibile + suggerimento sui domini → **25**
- [ ] B7 Avviso sulle regole di prezzo che si sommano → **23**
- [ ] B8 Listino dei prossimi 12 mesi in dashboard → **25**
- [x] B9 Redirect all'host canonico → **15** — **chiuso nel prompt 15**
- [ ] B10 Avvisi quando una modifica tocca prenotazioni esistenti → **20**, 23
- [ ] B11 Blocco/avviso su richieste scadute → **23**
- [ ] B12 Pagina 503 quando il DB non risponde → **18**
- [ ] B13 Cache su file delle impostazioni → **18**
- [ ] B14 Blocco ottimistico sui moduli admin → **18** (poi 20, 21, 23)
- [x] B15 Supporto LiteSpeed + `ignore_user_abort` → **15** — **chiuso nel prompt 15**
- [x] B16 Whitelist di `APP_ENV` → **15** — **chiuso nel prompt 15**
- [x] B17 Sanificazione delle eccezioni PDO nei log → **15** — **chiuso nel prompt 15**
- [x] B18 "Esci da tutti i dispositivi" → **17** — **chiuso nel prompt 17**
- [ ] B19 Email al titolare dopo molti login falliti → **24**
- [x] B20 Riferimento nella pagina "ricevuta" solo se reale → **15** — **chiuso nel prompt 15**
- [x] B21 Caratteri invisibili e di direzione rimossi → **15** — **chiuso nel prompt 15**
- [ ] B22 Normalizzazione Unicode NFC → **24**
- [ ] B23 Etichetta "inglese da aggiornare" → **25**
- [ ] B24 Avviso su prezzi implausibili → **23**

### C — Difesa in profondità

- [x] C1 Race e ritardo globale nel rate limit → **15** — **chiuso nel prompt 15**
- [~] C2 Riautenticazione riusabile (`ReauthGuard`, 5 minuti) → **17** — fatto in 17 (classe, pagina di conferma, test); la applicano i prompt 24, 26, 27 alle loro azioni
- [x] C4 Password comuni rifiutate → **17** — **chiuso nel prompt 17**
- [x] C5 Segnalare accessi sospetti → **17** — **chiuso nel prompt 17** nella forma D6 = B (accesso precedente e tentativi falliti nella pagina Account); l'email «nuovo accesso» non è stata scelta
- [x] C6 Secondo fattore TOTP → **17** — **deciso: non ora** (D3 = A); da riconsiderare se l'admin resta di una persona sola
- [ ] C3, C20, C21, C23, C53–C57, C62, C64–C67 Sessioni, SMTP, registro errori, log, permessi, fuso orario, proxy → **26**
- [~] C8 `.htaccess` di negazione + controllo HTTP → **15**, 26, 28, 32 — fatto in 15: file di negazione; restano 26, 28, 32
- [ ] C9, C10, C22 Intestazioni, limite URL, `Message-ID` → **28**
- [ ] C11 `security.txt` → **31**
- [x] C12–C15, C17 Vincoli `CHECK` nel DB, audit senza testo libero → **15** — **chiuso nel prompt 15** (C17: la conservazione a tempo dello Storico non è stata impostata; nessuna scadenza decisa)
- [ ] C16 Unicità delle notti garantita dal DB (consigliato: non ora) → **28**
- [ ] C18, C19 Indici per la ricerca; email "ricevuta" senza testo dell'ospite e con tetti → **24**
- [ ] C24–C39 Libreria foto (pixel/memoria, HEIC, scrittura atomica, `.htaccess` PHP-FPM, CMYK, animati, EXIF, quota, nomi) → **19**
- [ ] C40–C52 Aggiornamenti DB e backup (lock, backup obbligatorio, manutenzione, prova di ripristino, zip) → **27**
- [~] C58–C63 Privacy: Storico, backup, CSV, casella email, pulizie, pagina "Dati di un ospite" → **15**, 24, 27, 29, 31 — fatto in 15: Storico (C58); restano 24, 27, 29, 31

### D, E, F, G, J, K

- [ ] D1–D30 Test guardiani → nel prompt indicato dalla tabella D.1 della review (numerazione precedente: usare la corrispondenza in testa al documento)
- [ ] E.0 Guardrail → `docs/GUARDRAIL_FASI.md` (**14**: in vigore con D1/D2) · E.1 prompt per prompt → nei prompt corrispondenti
- [ ] F1 Correzioni dell'esistente → **15** · F2 Verifica dopo la pubblicazione → **32** · F3 Divisione 26/27 → **26**, 27 · F4 Threat model → **16** · F5 Prova generale del titolare → **30**
- [ ] G1–G13 Usabilità admin → **15**, 17, 23, 25, 27 (G5 in 23; G11 in 27)
- [ ] J1–J13 Esperienza cliente, SEO, prestazioni, accessibilità → **18**, 21, 25, 28
- [ ] K1, K8 L'agente non risponde al posto dell'utente → `GUARDRAIL_FASI.md` · K2, K3 → `GUARDRAIL_FASI.md` · K4 → **28** · K5 → **22**, 31 · K6 → **14** (fatto: note "Da riaprire" in `FINAL_REVIEW` e `DELIVERY_CHECKLIST`) · K7 → **27**, 29 · K9 → riepilogo finale e **29** (`docs/NOVITA_ADMIN.md`) · K10 → **28**

### Altri impegni registrati dal prompt 14

- [ ] Decisione P4 (eliminazione di `legacy/`): chiudere nel prompt 28 (i file tracciati sono già stati rimossi il 2026-10-06; vedi `DECISIONS`)
- [ ] Template morto `templates/pages/home.php`: rimozione nel prompt 28
- [ ] Regola "minimo 2 persone" (attivabile/disattivabile): prompt 23
- [ ] Riscrivere/aggiornare `docs/RELEASE_GUIDE.md` e i documenti 12–13 per le funzioni nuove: prompt 29, 30, 31
