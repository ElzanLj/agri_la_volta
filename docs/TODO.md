# TODO operativo

Legenda: `[ ]` da fare · `[~]` in corso · `[x]` completato **e verificato** · `[!]` bloccato

Regole:
- non segnare `[x]` sulla sola base del codice scritto; serve la verifica pertinente;
- se una voce è bloccata da dati/accessi mancanti usa `[!]` e registra il motivo in `MISSING_DATA.md` o `SESSION_STATE.md`;
- mantieni una sola fonte di stato: questo file, non checklist parallele nelle chat.


## Audit

- [ ] Repository e struttura analizzati
- [ ] Dipendenze analizzate
- [ ] Immagini analizzate e provenienza dubbia segnalata
- [ ] Codice morto individuato
- [ ] Sistemi di pagamento individuati/rimossi in sicurezza
- [ ] Servizi/configurazioni esterne censiti
- [ ] Possibili dati reali identificati senza esposizione
- [ ] Baseline build/test documentata

## Fondamenta

- [ ] Backend principalmente PHP
- [ ] MySQL/MariaDB configurato
- [ ] Compatibilità hosting condiviso verificata
- [ ] `.env.example` creato
- [ ] Segreti esclusi dal repository
- [ ] Migrazioni SQL versionate
- [ ] Gestione errori/404 predisposta

## Database

- [ ] apartments
- [ ] booking_requests
- [ ] bookings
- [ ] availability_blocks
- [ ] seasonal_rates / struttura tariffe equivalente
- [ ] admin
- [ ] audit_log
- [ ] eventuali tabelle supplementi/regole/traduzioni/email retry solo se necessarie

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

- [ ] Login/logout
- [ ] Nessuna registrazione pubblica
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
- [ ] CSRF
- [ ] Sessioni sicure
- [ ] HttpOnly/Secure/SameSite appropriati
- [ ] Hash password admin sicuro
- [ ] Rate limiting dove necessario
- [ ] Limiti richieste
- [ ] Accesso admin controllato lato server
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
