# TODO operativo

Legenda: `[ ]` da fare · `[~]` in corso · `[x]` completato **e verificato** · `[!]` bloccato

Regole:
- non segnare `[x]` sulla sola base del codice scritto; serve la verifica pertinente;
- se una voce è bloccata da dati/accessi mancanti usa `[!]` e registra il motivo in `MISSING_DATA.md` o `SESSION_STATE.md`;
- mantieni una sola fonte di stato: questo file, non checklist parallele nelle chat.


## ▶ Promemoria: da dove ripartire (aggiornato 2026-10-06)

**Sviluppo e review finale completati** (prompt 0-13). **Prossimo passo: `prompts/14_RELEASE_PREP_NO_DEPLOY.md`** (preparazione alla pubblicazione, **senza deploy**; compila `docs/RELEASE_GUIDE.md`), oppure, se l'utente lo decide, l'estensione `prompts/15_ADMIN_CONTENT_BLOCKS.md` (bozza dell'utente da approvare: contenuti e pagine a blocchi modificabili dall'admin; prima di scrivere codice l'agente deve riproporre le scelte **[DA CONFERMARE]**).

Per ripartire:
1. `docker compose up -d` e verifica della baseline: `docker compose exec web composer test` (attesi **778 test PASS**, circa 7 minuti; se `vendor/` manca: `docker compose exec web composer install`).
2. Leggere `docs/SESSION_STATE.md`, `docs/FINAL_REVIEW.md`, `docs/DELIVERY_CHECKLIST.md`, `docs/MISSING_DATA.md`, poi il prompt scelto.
3. Come nelle fasi precedenti: **prima un piano conciso** da far approvare.

Da tenere presente:
- stato: 23 criteri PASS, 4 PARTIAL (18 tastiera, 19 responsive, 20 immagini, 24 hosting reale), 0 FAIL; vedi `docs/FINAL_REVIEW.md` per la conformità sezione per sezione della SPEC;
- **migrazioni**: l'ultima è `0005_apartment_amenities.sql`; la prossima deve avere il numero successivo e non modificare le precedenti;
- **file dell'utente non committati**: `prompts/15_ADMIN_CONTENT_BLOCKS.md` e una riga in `prompts/README.md` sono bozze dell'utente (non committate dall'agente): ispezionarle con `git status`/`git diff` e non sovrascriverle;
- `legacy/` è stata rimossa dal repository (recuperabile da `ae3129e`); restano sul disco dell'utente solo file non tracciati (es. `node_modules`);
- **restano NOT RUN**: prove manuali (`docs/MANUAL_CHECKLIST.md`, a cura del titolare), installazione su hosting reale, consegna email reale, HTTPS reale, Lighthouse/axe/penetration test;
- da fare **prima di pubblicare** (a carico del titolare, `docs/DELIVERY_CHECKLIST.md`): hosting e dominio, HTTPS, credenziali SMTP, contenuti (listino, testi, servizi, foto, recapiti, WhatsApp), testi legali, periodo di conservazione dei dati, `APP_SECRET`;
- il nuovo design del titolare (Figma) cambierà il layout: i test `PublicSeoTest`, `PublicPagesTest` e `ContrastTest` controllano struttura, accessibilità e contrasto e vanno aggiornati con il nuovo layout;
- la cronologia Git contiene la credenziale Gmail del legacy (già revocata) e non è stata riscritta (decisione P7): riscriverla richiede autorizzazione esplicita;
- strumenti: `bin/migrate.php`, `bin/create-admin.php`, `bin/send-queued-mail.php`, `bin/privacy.php`, `bin/optimize-images.php` (mai eseguito: manca GD/WebP nel container).

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
