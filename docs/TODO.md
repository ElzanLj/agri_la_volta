# TODO operativo

Legenda: `[ ]` da fare · `[~]` in corso · `[x]` completato **e verificato** · `[!]` bloccato

Regole:
- non segnare `[x]` sulla sola base del codice scritto; serve la verifica pertinente;
- se una voce è bloccata da dati/accessi mancanti usa `[!]` e registra il motivo in `MISSING_DATA.md` o `SESSION_STATE.md`;
- mantieni una sola fonte di stato: questo file, non checklist parallele nelle chat.


## ▶ Promemoria: da dove ripartire (aggiornato 2026-10-06)

**Prossima sessione: eseguire `prompts/09_I18N_SEO_A11Y_PERF.md`** (Fase 6: IT/EN, SEO, accessibilità, prestazioni, immagini). Fasi 0-5 completate e committate; working tree pulito a fine sessione.

Per ripartire:
1. `docker compose up -d` e verifica della baseline: `docker compose exec web composer test` (attesi **tutti i test PASS**, numero in `docs/TEST_REPORT.md`, circa 5 minuti; se `vendor/` manca: `docker compose exec web composer install`).
2. Leggere `docs/SESSION_STATE.md`, `docs/MISSING_DATA.md` e `docs/DECISIONS.md` (sezione Fase 5), poi `prompts/09_I18N_SEO_A11Y_PERF.md`.
3. Come nelle fasi precedenti: **prima un piano conciso** da far approvare, poi il codice.

Da tenere presente nella Fase 6:
- le pagine pubbliche esistono già (`app/Http/Controllers/Site/`, `templates/public/`, testi in `content/it.php` e `content/en.php`, URL in `app/Site/Routes.php`); canonical, hreflang, `lang`, skip link, landmark e breadcrumb (solo pagina appartamento) sono già impostati: la fase 6 deve verificarli e completarli, non rifarli;
- mancano: Open Graph, favicon, `robots.txt`, sitemap, menu mobile con `aria-expanded` se necessario, contrasto verificato, test manuale con tastiera e screen reader, descrizioni uniche per ogni appartamento (dal DB);
- le foto sono **segnaposto marcati** (`templates/public/_photo.php`): nessuna foto legacy ha provenienza verificata. Quando il titolare le fornisce servono una tabella/gestione foto (non esiste ancora), varianti WebP/responsive, `width`/`height`, lazy loading, hero ottimizzata;
- non inventare testi per "L'agriturismo" e "Dintorni" (mostrano un avviso); privacy e cookie sono bozze da far verificare al titolare;
- il test `PublicSiteUnitTest` controlla che le chiavi di `content/it.php` e `content/en.php` coincidano e che ogni chiave usata nei template esista: aggiornarlo se si aggiungono testi;
- stile: la regola `h1, h2, h3` in `public/assets/css/site.css` vale anche per l'admin; il CSS pubblico è in fondo al file;
- le pagine del flusso (richiesta) sono `noindex` e `no-store` per scelta.

Bloccanti/dati mancanti che non dipendono dal codice (vedi `docs/MISSING_DATA.md`): credenziali SMTP reali (consegna email non verificata), testi definitivi delle email e dei contenuti, numero WhatsApp e recapiti pubblici, listino prezzi reale, foto con provenienza verificata, scelta dell'hosting (PHP-FPM, cron, `vendor/` da caricare), `APP_SECRET` di produzione.

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

- [ ] Responsive mobile/desktop
- [ ] Navigazione tastiera
- [ ] Focus visibile
- [ ] Label e messaggi errore accessibili
- [x] Skip link (presente su ogni pagina; verifica manuale con tastiera in Fase 6)
- [ ] Menu mobile accessibile
- [ ] Alt text
- [ ] Contrasto adeguato
- [ ] prefers-reduced-motion
- [ ] Title e description unici
- [x] canonical (pagine indicizzabili; verificato da test)
- [x] hreflang quando appropriato (it, en, x-default; verificato da test)
- [ ] Open Graph
- [ ] robots.txt
- [ ] sitemap
- [ ] breadcrumb
- [x] 404/errori (anche in inglese sotto /en)
- [ ] Schema.org solo con dati reali
- [ ] Immagini responsive/ottimizzate/lazy fuori above-the-fold
- [ ] Hero ottimizzata
- [ ] JS/font/dipendenze non necessari rimossi

## Sicurezza

- [x] Query parametrizzate (nessuna concatenazione di input; verificato con tentativi di SQL injection nei filtri)
- [x] Escaping output (area admin e pagine pubbliche verificate con payload XSS)
- [x] Validazione server-side (servizi, area admin e form pubblico)
- [x] CSRF (guardia a livello di prefisso su tutto `/admin`: token + controllo Origin, nessuna eccezione)
- [x] Sessioni sicure
- [x] HttpOnly/Secure/SameSite appropriati (verificati su HTTP e su sito https)
- [x] Hash password admin sicuro (`password_hash` default/bcrypt)
- [x] Rate limiting dove necessario (login admin; invio richiesta: 6 all'ora per IP)
- [~] Limiti richieste (corpo max 1 MB nel front controller)
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
- [ ] Test manuale mobile/desktop/tastiera/IT/EN
- [ ] README/installazione locale
- [ ] Installazione hosting condiviso
- [ ] Import database
- [~] Configurazione SMTP (variabili e comportamento documentati in `docs/COMMANDS.md`; guida di consegna in Fase 8; credenziali reali mancanti)
- [~] Creazione/modifica admin (`bin/create-admin.php` testato, cambio password chiude le sessioni; guida completa in Fase 8)
- [ ] Backup/ripristino
- [ ] Export CSV documentato
- [ ] Limitazioni residue documentate
- [ ] Informazioni mancanti documentate
- [ ] Checklist pubblicazione completata


## Continuità / handoff

- [ ] `SESSION_STATE.md` riflette la fase corrente
- [ ] Decisioni tecniche significative registrate
- [ ] Dati mancanti aggiornati
- [ ] Test report aggiornato
- [ ] Nessuna modifica locale non compresa prima di cambiare agente
