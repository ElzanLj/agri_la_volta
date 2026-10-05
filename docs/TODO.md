# TODO operativo

Legenda: `[ ]` da fare · `[~]` in corso · `[x]` completato **e verificato** · `[!]` bloccato

Regole:
- non segnare `[x]` sulla sola base del codice scritto; serve la verifica pertinente;
- se una voce è bloccata da dati/accessi mancanti usa `[!]` e registra il motivo in `MISSING_DATA.md` o `SESSION_STATE.md`;
- mantieni una sola fonte di stato: questo file, non checklist parallele nelle chat.


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

- [ ] SMTP da variabili d'ambiente
- [ ] Richiesta salvata prima dell'email
- [ ] Nuova richiesta → email gestore
- [ ] Conferma → email cliente
- [ ] Rifiuto → email cliente
- [ ] Cancellazione → bozza modificabile, non inviata automaticamente
- [ ] Fallimento SMTP non perde dati
- [ ] Fallimento registrato senza segreti/dati inutili
- [ ] Retry previsto se utile
- [ ] WhatsApp con messaggio precompilato modificabile

## Pubblico e contenuti

- [ ] `/`
- [ ] `/agriturismo`
- [ ] `/appartamenti`
- [ ] pagina singola per ogni appartamento
- [ ] `/dintorni`
- [ ] `/richiedi-disponibilita`
- [ ] `/contatti`
- [ ] `/privacy`
- [ ] `/cookie`
- [x] `/admin` non esposto nella nav pubblica (verificato da test)
- [ ] IT e EN
- [ ] Nessun dato mancante inventato

## UX / Accessibilità / SEO / Prestazioni

- [ ] Responsive mobile/desktop
- [ ] Navigazione tastiera
- [ ] Focus visibile
- [ ] Label e messaggi errore accessibili
- [ ] Skip link
- [ ] Menu mobile accessibile
- [ ] Alt text
- [ ] Contrasto adeguato
- [ ] prefers-reduced-motion
- [ ] Title e description unici
- [ ] canonical
- [ ] hreflang quando appropriato
- [ ] Open Graph
- [ ] robots.txt
- [ ] sitemap
- [ ] breadcrumb
- [ ] 404/errori
- [ ] Schema.org solo con dati reali
- [ ] Immagini responsive/ottimizzate/lazy fuori above-the-fold
- [ ] Hero ottimizzata
- [ ] JS/font/dipendenze non necessari rimossi

## Sicurezza

- [x] Query parametrizzate (nessuna concatenazione di input; verificato con tentativi di SQL injection nei filtri)
- [~] Escaping output (area admin verificata con payload XSS su ogni pagina; pagine pubbliche in Fase 5)
- [~] Validazione server-side (servizi e area admin; form pubblico in Fase 5)
- [x] CSRF (guardia a livello di prefisso su tutto `/admin`: token + controllo Origin, nessuna eccezione)
- [x] Sessioni sicure
- [x] HttpOnly/Secure/SameSite appropriati (verificati su HTTP e su sito https)
- [x] Hash password admin sicuro (`password_hash` default/bcrypt)
- [~] Rate limiting dove necessario (login admin; moduli pubblici in Fase 5/7)
- [~] Limiti richieste (corpo max 1 MB nel front controller)
- [x] Accesso admin controllato lato server
- [ ] Honeypot/antispam semplice
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
- [~] Test richieste pubbliche (a livello servizio; test HTTP del form in Fase 5)
- [ ] Test fallimento email
- [~] Test validazione form (validazione server-side testata a livello servizio; form HTML in Fase 5)
- [x] Test cancellazione
- [x] Test export CSV
- [ ] Test manuale mobile/desktop/tastiera/IT/EN
- [ ] README/installazione locale
- [ ] Installazione hosting condiviso
- [ ] Import database
- [ ] Configurazione SMTP
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
