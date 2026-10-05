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

- [ ] Intervalli `[check_in, check_out)`
- [ ] Date validate lato server
- [ ] Numero notti corretto
- [ ] Disponibilità ricalcolata lato server
- [ ] Nessuna sovrapposizione tra prenotazioni confirmed dello stesso appartamento
- [ ] Controllo concorrenza/transazione implementato
- [ ] Richiesta pubblica salvata come pending
- [ ] Conferma manuale admin
- [ ] Rifiuto admin
- [ ] Prenotazioni manuali da canali esterni
- [ ] Blocchi disponibilità
- [ ] Cancellazione libera le date

## Prezzi

- [ ] Tariffe modificabili senza codice
- [ ] Intervalli/stagioni supportati
- [ ] Adulti supportati
- [ ] Bambini supportati
- [ ] Animali supportati
- [ ] Supplementi supportati
- [ ] Soggiorno minimo configurabile se previsto
- [ ] Prezzo ricalcolato lato server prima del salvataggio
- [ ] Nessun importo inventato

## Admin

- [x] Login/logout
- [x] Nessuna registrazione pubblica
- [ ] Nuove richieste visibili
- [ ] Filtri periodo/appartamento/stato
- [ ] Dettaglio richiesta
- [ ] Conferma/rifiuto
- [ ] Prenotazione manuale
- [ ] Cancellazione
- [ ] Blocchi disponibilità
- [ ] Calendario semplice
- [ ] Modifica prezzi/regole
- [ ] Modifica appartamenti
- [ ] Export CSV
- [ ] Audit log utile

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
- [ ] `/admin` non esposto nella nav pubblica
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

- [ ] Query parametrizzate
- [ ] Escaping output
- [ ] Validazione server-side
- [~] CSRF (login/logout admin; da applicare a ogni nuovo form)
- [x] Sessioni sicure
- [~] HttpOnly/Secure/SameSite appropriati (HttpOnly/SameSite verificati; Secure in HTTPS non ancora verificato)
- [x] Hash password admin sicuro (`password_hash` default/bcrypt)
- [~] Rate limiting dove necessario (login admin; moduli pubblici in Fase 5/7)
- [~] Limiti richieste (corpo max 1 MB nel front controller)
- [x] Accesso admin controllato lato server
- [ ] Honeypot/antispam semplice
- [ ] Nessun pagamento/dato carta

## Test e consegna

- [ ] Test date non valide
- [ ] Test checkout <= check-in
- [ ] Test numero notti
- [ ] Test soggiorni consecutivi
- [ ] Test overlap parziale/completo
- [ ] Test blocchi manuali
- [ ] Test conferma concorrente
- [ ] Test prezzi stagionali
- [ ] Test variazioni adulti/bambini/animali
- [ ] Test autorizzazione admin
- [ ] Test richieste pubbliche
- [ ] Test fallimento email
- [ ] Test validazione form
- [ ] Test cancellazione
- [ ] Test export CSV
- [ ] Test manuale mobile/desktop/tastiera/IT/EN
- [ ] README/installazione locale
- [ ] Installazione hosting condiviso
- [ ] Import database
- [ ] Configurazione SMTP
- [ ] Creazione/modifica admin
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
