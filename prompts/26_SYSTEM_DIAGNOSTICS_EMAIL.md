# Prompt 26 — Stato del sistema, email e manutenzione dall'admin

Prerequisito: prompt 25 completato.

Contesto: il titolare deve poter gestire il sito **senza SSH, FTP o riga di comando**. Oggi restano fuori dall'admin: la configurazione email (`SMTP_*`, `MAIL_FROM_*`, `MAIL_ADMIN_ADDRESS` in `.env`), la coda email e il pre-arrivo (`bin/send-queued-mail.php`, cron facoltativo), la lettura dei log (`storage/logs`) e qualsiasi indicazione di "salute" del sito. Hosting condiviso: niente processi permanenti. Le operazioni critiche (aggiornamenti del database, backup, conservazione dei dati) sono nel prompt 27 e usano gli strumenti di questa fase (manutenzione, stato) e la riautenticazione del prompt 17.

Riferimento visivo (non vincolante): `docs/mockup-admin/manutenzione.html`, `docs/mockup-admin/email.html`, `docs/mockup-admin/coerenza.html`.

## Obiettivo

Rendere visibile e gestibile dall'admin lo stato di salute del sito (diagnostica, coerenza dei dati, email, attività pianificate, registro errori, manutenzione) e dichiarare con chiarezza ciò che **deve** restare fuori.

## Cosa resta fuori dall'admin (non modificabile dal browser, solo mostrato come stato)

Credenziali del database, `APP_SECRET`, `APP_URL`, `APP_ENV`, `APP_DEBUG`, `APP_TIMEZONE`, `HSTS_MAX_AGE`, `PUBLIC_FORM_MIN_SECONDS`, `TRUSTED_PROXIES`. Servono per avviare l'applicazione o proteggono l'admin stesso: se si potessero cambiare dal browser, chi ruba la sessione admin prenderebbe il controllo del server. La pagina di stato li mostra come "configurato / non configurato", mai il valore.

## Prima di modificare

- Vincoli: nessun nuovo framework, dipendenza Composer di produzione, JavaScript pubblico, React, Firebase o WordPress; nessun refactor fuori scope; preserva ciò che funziona; codice rimosso solo se dimostrato inutile. Rispetta `docs/GUARDRAIL_FASI.md` (ramo Git per fase, prove obbligatorie, contenuti come dati, file vietati, nessun segreto).
- Verifica lo stato dei prompt 15, 17 e 25.
- Leggi `bin/*.php`, `app/Mail/**` (in particolare `MailTransportFactory`, `SmtpTransport`, `NotificationService`), `app/Support/DeferredWork.php`, `app/Support/Logger.php`, il servizio di coerenza e `ReauthGuard` (prompt 15 e 17), `AppSecret`, `AdminAuth`, `AGENTS.md` (regola "SMTP tramite variabili d'ambiente"), `docs/COMMANDS.md`, `docs/SECURITY_REVIEW.md`, `docs/THREAT_MODEL.md`.
- Leggi in `docs/REVIEW_PRE_ROADMAP.md` le schede A7, A13, A14, A15, B1, C3, C20–C23, C53–C57, C62, C64–C67, G3, I1, I5 e la sezione "Stato del sistema e dashboard: cosa mostrerei".
- Leggi `docs/CAMPI_CONTENUTI.md` (approvato nel prompt 16): ogni campo toccato da questa fase segue la sua riga. Se un campo nuovo non ha una riga, aggiungila e falla confermare.
- `git status` e diff locali.

## Domande per l'utente (all'inizio della fase)

Prima di modificare qualsiasi file, apri `docs/RISPOSTE_UTENTE.md`: se l'utente ha già segnato le risposte per questo prompt, mostrale e chiedi solo conferma. Altrimenti presenta le domande di questa sezione **così come sono scritte** (risposte possibili e consiglio) e **fermati finché non risponde**.

Le domande sono di due tipi, indicati nel titolo:

- **[titolare]**: decisioni sul business (prezzi, regole, testi, comunicazioni agli ospiti, dati legali, servizi esterni). Non applicare mai una risposta ✅ al posto dell'utente: serve la sua risposta scritta in chat ("consigliate" vale, se detto esplicitamente per questa fase). Senza risposta la fase non inizia.
- **[tecnica]**: scelte di implementazione. Se l'utente risponde "consigliate" o non ha preferenze, vale la risposta ✅; elencale comunque nel riepilogo finale.

Registra ogni scelta in `docs/DECISIONS.md`. In `docs/RISPOSTE_UTENTE.md` (casella con `x`) scrivi **solo** ciò che l'utente ha detto in chat, mai risposte tue. Le voci rinviate o rifiutate vanno in `docs/TODO.md` con il motivo. Non implementare nulla che non sia stato approvato. Se una voce approvata supera lo scope di questa fase, proponi di spostarla e fermati.


### D1 — Configurazione email (SMTP) [tecnica]

La password della casella cambia ogni tanto (il provider la fa ruotare, si cambia casella): senza admin serve l'FTP.

| Risposta | Pro / contro |
|---|---|
| A. Tutto resta in `.env` | Nessun segreto nel database; ogni cambio richiede l'FTP o il pannello dell'hosting |
| B. ✅ Tutto dall'admin: server, porta, cifratura, utente, mittente, nome, destinatario delle notifiche e **password cifrata** nel database (chiave derivata da `APP_SECRET` con HKDF e un contesto dedicato, versione della chiave salvata con il cifrato); se i valori sono in `.env` prevalgono e l'admin li mostra in sola lettura; salvataggio con riautenticazione; pulsante "Invia email di prova" | Il titolare cambia casella o password da solo; richiede `APP_SECRET` impostato (senza, il salvataggio della password è rifiutato) e **un'eccezione scritta alla regola di `AGENTS.md`** ("SMTP tramite variabili d'ambiente"), da registrare in `DECISIONS` |
| C. Ibrida: tutto dall'admin **tranne la password**, che resta in `.env` (l'admin mostra solo "configurata / non configurata") | Nessuna crittografia né gestione di chiavi nel database; cambiare la password della casella richiede di toccare `.env` |

**Consiglio: B**, perché vuoi che tutto sia gestibile dall'admin. La C è la più semplice e sicura: scegli quella se accetti di modificare `.env` quando cambia la password. Se `APP_SECRET` cambia, la password cifrata diventa illeggibile: l'admin lo dice ("Reinserisci la password della posta"), la dashboard diventa rossa e le email restano in coda; mai un errore fatale.

### D2 — Attività pianificate (coda email, pre-arrivo, pulizie) [tecnica]

| Risposta | Pro / contro |
|---|---|
| A. Solo cron dell'hosting | Affidabile; non tutti gli hosting lo offrono e il titolare non vede se funziona |
| B. ✅ Esecuzione automatica "a ogni visita": al massimo ogni 15 minuti, decisa dalla data di un file (`storage/cache/last-run`, **nessuna query in ogni pagina**), dopo l'invio della risposta; cron facoltativo; pagina che mostra l'ultima esecuzione e l'esito, con "Esegui ora" | Funziona ovunque ed è visibile al titolare |
| C. Solo pulsante manuale | Nessuna automazione; facile dimenticarsene |

**Consiglio: B.** Riusa `DeferredWork` e i comandi esistenti, senza duplicarne la logica; un lock (`GET_LOCK`) impedisce due esecuzioni in parallelo.

### D3 — Registro errori nell'admin [tecnica]

| Risposta | Pro / contro |
|---|---|
| A. No, solo via FTP | — |
| B. ✅ Gli ultimi 200 eventi, con filtro per livello; solo livello, data e messaggio fisso (mai contesto, percorsi, SQL, email, IP); lettura limitata agli ultimi 256 KB del file | Diagnosi senza FTP, senza rischio di mostrare segreti |

**Consiglio: B.**

### D4 — Modalità manutenzione [tecnica]

| Risposta | Pro / contro |
|---|---|
| A. Solo file `storage/maintenance` | Funziona anche con l'admin rotto; serve l'FTP |
| B. ✅ Interruttore nell'admin **e** file `storage/maintenance` (entrambi efficaci); i visitatori vedono una pagina 503 IT/EN con `Retry-After`, l'admin resta accessibile; avviso in dashboard se è attiva da più di un'ora | Uso quotidiano dal browser, file come riserva durante gli aggiornamenti via FTP |

**Consiglio: B.**

### D5 — Stato del sistema [tecnica]

| Risposta | Pro / contro |
|---|---|
| A. No | — |
| B. ✅ Pagina con: versione del sito, PHP ed estensioni (`pdo_mysql`, `gd` con JPEG/WebP, `fileinfo`, `zip`, `sodium`/`openssl`, `exif`), limiti (upload, memoria, tempo), spazio usato da foto, log e database, orologio di PHP e del database, scrivibilità di `storage/sessions` e `storage/logs`, permessi di `.env`, migrazioni applicate e in attesa, stato email, ultima esecuzione delle attività, voci di `.env` configurate (mai i valori), **controllo HTTP** che `.env`, `storage/logs/`, `composer.json` e `migrations/` non siano raggiungibili dal web, rilevamento di header di proxy senza `TRUSTED_PROXIES` | Il titolare o un tecnico capiscono in un minuto cosa non va |

**Consiglio: B.** Elenco di cosa è rosso, giallo o informativo: vedi la sezione "Stato del sistema e dashboard: cosa mostrerei" della review.

### D6 — Conservazione dei log e pulizie automatiche [tecnica]

I log crescono per sempre (`app/Support/Logger.php` non li ruota) e contengono comunque tracce operative.

| Risposta | Pro / contro |
|---|---|
| A. 30 giorni | Minimo ingombro; poca storia per indagare |
| B. ✅ 90 giorni | Equilibrio tra diagnosi e privacy |
| C. 180 giorni | Più storia; più dati conservati |

**Consiglio: B**, modificabile dall'admin. Le attività pianificate cancellano anche le righe vecchie di `rate_limit_hits`.

### D7 — Pagina "Coerenza dei dati" [tecnica]

Il servizio e il comando esistono dal prompt 15.

| Risposta | Pro / contro |
|---|---|
| A. ✅ Pagina admin con l'elenco delle incoerenze, esecuzione nelle attività pianificate e banner rosso in dashboard se ne trova | Un bug nella gestione delle date si scopre prima del cliente |
| B. Solo il comando | Nessuna visibilità per il titolare |

**Consiglio: A.**

## Implementa

- **Configurazione email** secondo D1: validazioni (host valido, porta tra 25, 465, 587 e 2525; cifratura solo `tls` o `ssl`, mai `none` se l'host non è `localhost` o `127.0.0.1`); la password non compare mai nell'HTML (campo vuoto = invariata), né nei log, nello Storico, negli export o nei backup in chiaro; riautenticazione per salvare; **"Invia email di prova"** verso il destinatario delle notifiche, con timeout breve, esito e data mostrati, errori **generici** (non rivelano risposte del server SMTP né reti interne). Le categorie d'errore del trasporto sono mostrate come **istruzioni** ("il server ha rifiutato la password: controllala"). Aggiorna `AGENTS.md` e `DECISIONS` se D1 = B.
- **Attività pianificate** (D2): invio coda email, pre-arrivo (prompt 24), pulizia dei log e di `rate_limit_hits` (D6), verifica di coerenza (D7); ognuna con ultima esecuzione ed esito; pulsante "Esegui ora" (POST + CSRF); lavori differiti dopo la risposta, mai nel percorso della pagina.
- **Manutenzione** (D4): interruttore, file, pagina 503 IT/EN, `Retry-After`, admin escluso.
- **Stato del sistema** (D5): nessun valore segreto nell'HTML; il controllo HTTP interroga solo l'indirizzo di `APP_URL` con timeout di 3 secondi e dice "non verificabile" se l'hosting non lo permette (il controllo vero dall'esterno è nei prompt 31 e 32).
- **Registro errori** (D3): escape completo, nessun percorso né contesto; un file di log enorme non rallenta la pagina.
- **Coerenza dei dati** (D7).
- **Dashboard**: aggiungi al registro della checklist (prompt 25) le voci di sistema: email non configurata o prova fallita, password SMTP non leggibile, incoerenze, attività ferme da più di un giorno, spazio foto oltre il 90%, manutenzione attiva da più di un'ora, estensioni mancanti, `.env` raggiungibile dal web.
- Ogni campo nuovo segue `docs/CAMPI_CONTENUTI.md` (aggiungi le righe mancanti).

## Test obbligatori

- autorizzazione, CSRF e riautenticazione per ogni azione sensibile (scaduta, errata, assente);
- **SMTP**: password cifrata nel database, mai nell'HTML, nei log, nello Storico o negli export; prevalenza di `.env`; email di prova con un server SMTP finto (successo, errore di autenticazione, timeout, rifiuto del certificato) con messaggi generici; `APP_SECRET` assente → salvataggio rifiutato con messaggio chiaro; `APP_SECRET` cambiato → "password non leggibile", email in coda, nessun errore fatale; `none` rifiutato per host remoti; porte fuori elenco rifiutate;
- **stato del sistema**: nessun valore segreto nell'HTML (test con valori noti nelle variabili); estensione mancante simulata → voce rossa; `storage/sessions` non scrivibile → avviso; orologio sfasato → avviso; controllo HTTP con un finto server che risponde 200 a `/.env` → rosso;
- **attività**: due esecuzioni parallele → una sola; intervallo minimo rispettato senza query sul database; "Esegui ora" funzionante; lavoro fallito non blocca gli altri;
- **manutenzione**: 503 con `Retry-After` in IT ed EN, admin accessibile, file e interruttore entrambi efficaci, avviso dopo un'ora;
- **registro errori**: messaggio con `<script>` mostrato come testo; file da 50 MB letto in tempo breve; mai percorsi, SQL, email, IP;
- **coerenza**: database di prova con ogni incoerenza → pagina e banner; database sano → nessun banner;
- `ConsistencyChecker` eseguito dopo i test di concorrenza esistenti: nessuna incoerenza;
- suite completa PASS.

Aggiorna `COMMANDS` (cosa resta fuori dall'admin e dove si imposta), `SECURITY_REVIEW`, `docs/THREAT_MODEL.md`, `TEST_REPORT`, `DECISIONS`, `MISSING_DATA`, `TODO`, `SESSION_STATE`.

## Prova tu (5–10 minuti, a fine fase)

L'agente elenca questi passi nel riepilogo finale; l'utente li esegue in locale.

1. In **Sistema › Email** inserisci un server SMTP di prova e premi "Invia email di prova"; ricarica la pagina: la password non deve comparire (confronta con `docs/mockup-admin/email.html`).
2. Apri **Stato del sistema**: nessuna password o valore segreto visibile; prova a rendere non scrivibile `storage/sessions` e ricarica: deve comparire un avviso.
3. Attiva la manutenzione e apri il sito in una finestra anonima: deve comparire "Torniamo presto" e l'admin deve restare utilizzabile.
4. Apri il **Registro errori** dopo aver provocato un errore (es. password SMTP sbagliata): niente percorsi né dati personali.
5. Apri **Coerenza dei dati**: su dati sani non deve segnalare nulla (confronta con `docs/mockup-admin/coerenza.html`).

## Stop condition

Fermati quando le voci approvate sono implementate, sicure e testate, e la sezione "Cosa resta fuori dall'admin" è documentata in `COMMANDS` e nel manuale. Fermati prima di iniziare se l'utente non ha risposto alle domande [titolare]. **Non** implementare aggiornamenti del database, backup né conservazione dei dati (prompt 27), **non** fare la review di sicurezza complessiva e **non** cambiare versioni di PHP o del database (prompt 28).
