# Prompt 15 — Correzioni dell'esistente prima della gestione contenuti

Prerequisito: prompt 14 completato.

Contesto: la review `docs/REVIEW_PRE_ROADMAP.md` ha trovato problemi nel codice **attuale**, indipendenti dal CMS (i numeri dei prompt in quel documento seguono la numerazione precedente: usa le sigle A1, B1… e non i numeri). Correggerli ora significa costruire le fasi 16–27 su una base verificata e avere subito attivi i test-guardiani. Stack: PHP 8.1+ senza framework, MariaDB/MySQL, PHPUnit in Docker (solo sviluppo), hosting condiviso.

Riferimento visivo (non vincolante): `docs/mockup-admin/rifiuto.html`, `docs/mockup-admin/coerenza.html`.

## Obiettivo

Correggere i problemi verificati dell'esistente e attivare i controlli automatici che proteggono le fasi successive. **Nessuna nuova funzione** oltre a quelle elencate.

## Prima di modificare

- Vincoli: nessun nuovo framework, dipendenza Composer di produzione, JavaScript pubblico, React, Firebase o WordPress; nessun refactor fuori scope; preserva ciò che funziona; codice rimosso solo se dimostrato inutile. Rispetta `docs/GUARDRAIL_FASI.md` (ramo Git per fase, prove obbligatorie, contenuti come dati, file vietati, nessun segreto).
- Verifica lo stato del prompt 14 (roadmap sincronizzata, baseline registrata).
- Leggi in `docs/REVIEW_PRE_ROADMAP.md` le schede A1, A2, A3, A9, A10, A11, A12, A14, A15, A16, A17, A18, B1, B2, B3 e i test D1–D9, D18–D21.
- Leggi `PersonalDataService`, `BookingService` (`cancelBooking`, `createBlock`, `removeBlock`), `AuditLog`, `RequestFlowController` (`submit`, `received`), `FormToken`, `RateLimiter`, `AdminController::login`, `OriginCheck`, `Request::ip`, `Migrator`, `Config`, `Logger`, `DeferredWork`, `templates/admin/requests/show.php`, `public/.htaccess`, `.htaccess`, `ScopeTest`, `AdminAccessTest`, `docs/SECURITY_REVIEW.md` (finding F2).
- `git status` e diff locali.

## Domande per l'utente (all'inizio della fase)

Prima di modificare qualsiasi file, apri `docs/RISPOSTE_UTENTE.md`: se l'utente ha già segnato le risposte per questo prompt, mostrale e chiedi solo conferma. Altrimenti presenta le domande di questa sezione **così come sono scritte** (risposte possibili e consiglio) e **fermati finché non risponde**.

Le domande sono di due tipi, indicati nel titolo:

- **[titolare]**: decisioni sul business (prezzi, regole, testi, comunicazioni agli ospiti, dati legali, servizi esterni). Non applicare mai una risposta ✅ al posto dell'utente: serve la sua risposta scritta in chat ("consigliate" vale, se detto esplicitamente per questa fase). Senza risposta la fase non inizia.
- **[tecnica]**: scelte di implementazione. Se l'utente risponde "consigliate" o non ha preferenze, vale la risposta ✅; elencale comunque nel riepilogo finale.

Registra ogni scelta in `docs/DECISIONS.md`. In `docs/RISPOSTE_UTENTE.md` (casella con `x`) scrivi **solo** ciò che l'utente ha detto in chat, mai risposte tue. Le voci rinviate o rifiutate vanno in `docs/TODO.md` con il motivo. Non implementare nulla che non sia stato approvato. Se una voce approvata supera lo scope di questa fase, proponi di spostarla e fermati.


### D1 — Rifiuto di una richiesta [titolare]

Oggi "Rifiuta richiesta" è un solo clic, accanto a "Conferma", e invia subito l'email all'ospite.

| Risposta | Pro / contro |
|---|---|
| A. ✅ Pagina di conferma con riepilogo, anteprima dell'email che riceverà l'ospite e pulsanti separati ("Rifiuta e avvisa l'ospite" / "Torna indietro") | Un tocco sbagliato dal telefono non è più irreversibile; nessuna colonna nuova |
| B. Come A, più un messaggio facoltativo all'ospite (es. "possiamo proporvi altre date") | Più cortese; il testo va salvato nella coda email (colonna `body`, già azzerata dopo l'invio e dall'anonimizzazione) e sottoposto a escape |
| C. Nessuna conferma (come oggi) | Nessun lavoro; errore umano irreversibile |

**Consiglio: A.** Il messaggio personale per le email di conferma è già previsto nel prompt 24.

### D2 — Storico e testo libero (privacy) [tecnica]

Oggi il motivo di una cancellazione e quello di un blocco vengono copiati nello Storico (`audit_log`) e **restano anche dopo l'anonimizzazione** dell'ospite.

| Risposta | Pro / contro |
|---|---|
| A. ✅ Lo Storico non contiene più testo libero personale: dei motivi registra solo "motivo presente: sì/no"; le voci già esistenti vengono ripulite da una migrazione/comando idempotente | Semplice e sicuro; lo Storico perde il testo del motivo (resta nella prenotazione, che viene anonimizzata con l'ospite) |
| B. Si mantiene il testo e l'anonimizzazione lo cancella anche dallo Storico delle entità coinvolte | Storico più ricco; richiede di collegare ogni voce all'ospite: più fragile |
| C. Nessuna modifica | — |

**Consiglio: A.** Regola per le fasi successive: nello Storico i testi dei contenuti (pagine, impostazioni) sono ammessi, i campi con dati personali o motivi liberi no.

### D3 — Doppio invio del modulo pubblico [tecnica]

| Risposta | Pro / contro |
|---|---|
| A. ✅ Chiave di invio unica: colonna `submission_key` con `UNIQUE` (hash del token del modulo e dei dati); un secondo POST identico mostra la stessa pagina "ricevuta" con lo stesso riferimento, senza nuova richiesta né nuova email | Risolve doppio clic e reinvio da telefono lento; nessuna sessione per i visitatori |
| B. Token monouso con tabella dedicata | Più rigido; più codice e pulizia da gestire |
| C. Niente | Richieste e email duplicate |

**Consiglio: A.** Un nuovo caricamento del modulo genera un nuovo token, quindi una richiesta davvero nuova con gli stessi dati resta possibile.

### D4 — IP del visitatore dietro proxy o CDN [tecnica]

Il rate limit usa solo `REMOTE_ADDR`: dietro Cloudflare o un proxy dell'hosting tutti i visitatori hanno lo stesso IP, quindi 5 password sbagliate di *chiunque* bloccano il login di tutti.

| Risposta | Pro / contro |
|---|---|
| A. ✅ Solo `REMOTE_ADDR` come oggi; il prompt 26 aggiunge in "Stato del sistema" un avviso se arrivano header di proxy | Sicuro per impostazione; il problema resta finché l'hosting non è noto |
| B. Variabile facoltativa `TRUSTED_PROXIES` (elenco di IP/CIDR): solo da quei proxy si legge `X-Forwarded-For` o `CF-Connecting-IP` | Risolve il caso proxy; vuota per impostazione, quindi sicura |
| C. Leggere sempre gli header | Chiunque può falsificarli e aggirare il rate limit: **sconsigliata** |

**Consiglio: A** finché non si conosce l'hosting; scegli B se l'hosting usa un proxy o Cloudflare.

### D5 — Host canonico [tecnica]

Se il sito risponde sia su `www.` sia senza, i POST dall'host diverso da `APP_URL` ricevono 403 (moduli pubblici e login).

| Risposta | Pro / contro |
|---|---|
| A. ✅ Redirect 301 delle GET verso l'host di `APP_URL` quando `Host` è diverso (nessun redirect se `APP_URL` non è configurato); il redirect a https resta compito dell'hosting | Niente moduli rotti su `www`; nessun rischio di loop dietro un proxy |
| B. Nessun redirect, solo documentazione | Nessun lavoro; il problema si scopre in produzione |
| C. Redirect a https anche nel PHP | Loop dietro un proxy che termina TLS: **sconsigliata** |

**Consiglio: A.**

### D6 — Verifica di coerenza dei dati [tecnica]

Il lock sulla riga dell'appartamento è l'unica garanzia contro gli overlap: un controllo indipendente segnala un eventuale bug prima che diventi una famiglia davanti alla porta.

| Risposta | Pro / contro |
|---|---|
| A. ✅ Servizio in sola lettura + comando `bin/check-consistency.php` + test (la pagina admin e l'allarme in dashboard arrivano nel prompt 26) | Utile subito in sviluppo e nei test; nessuna scrittura |
| B. Solo il comando, senza servizio riusabile | Meno lavoro; la pagina admin dovrà riscriverlo |
| C. Niente | — |

**Consiglio: A.** Il controllo cerca: prenotazioni confermate sovrapposte, blocchi sovrapposti a prenotazioni, richieste "confermate" senza prenotazione e viceversa, prenotazioni attive collegate a richieste rifiutate o cancellate, email in `sending` oltre il lease.

### D7 — Vincoli aggiuntivi nel database [tecnica]

| Risposta | Pro / contro |
|---|---|
| A. ✅ Nuova migrazione con `CHECK`: `bookings.status='cancelled'` ⇔ `cancelled_at` presente; `booking_requests.decided_at` coerente con lo stato; limiti su adulti, bambini, animali; `email_outbox.status='sent'` ⇒ `sent_at`. Prima dell'`ALTER` la migrazione verifica che i dati esistenti li rispettino | Stati impossibili rifiutati dal database, non solo dal PHP; i `CHECK` valgono da MySQL 8.0.16 e MariaDB 10.2 (versione minima decisa nel prompt 28) |
| B. Nessun vincolo nuovo | Nessun rischio sulla migrazione; invarianti solo nel PHP |

**Consiglio: A.** Se un dato esistente viola un vincolo, la migrazione si ferma con un messaggio chiaro e non modifica nulla.

### D8 — Tentativi di accesso falliti [tecnica]

| Risposta | Pro / contro |
|---|---|
| A. ✅ Numero di tentativi falliti nelle ultime 24 ore mostrato in dashboard | Un attacco distribuito diventa visibile; nessuna email |
| B. Niente | — |

**Consiglio: A.** L'email al titolare dopo molti tentativi è una domanda del prompt 24.

## Implementa

1. **Storico (D2)**: `cancelBooking`, `createBlock`, `removeBlock` non scrivono più il motivo; migrazione o comando idempotente che ripulisce le voci esistenti; avviso sotto i campi "motivo" e "note" ("Scrivi solo ciò che serve: niente dati sanitari né dati di altre persone"). Verifica anche `PersonalDataService`: ogni colonna di testo libero collegata all'ospite è coperta.
2. **Rifiuto (D1)**: la richiesta di rifiuto passa da una pagina di conferma (GET con riepilogo e anteprima, POST con campo di conferma). Un POST diretto senza passaggio di conferma non modifica nulla e rimanda alla pagina di conferma. Pulsante "Rifiuta" visivamente distante e di colore diverso da "Conferma". Aggiorna in modo mirato i test che oggi fanno il POST diretto e segnalalo in `TEST_REPORT`.
3. **Doppio invio (D3)**: `submission_key` con `UNIQUE`, gestione della collisione dentro la transazione, stessa risposta del primo invio.
4. **Rate limit**: prima si registra il tentativo, poi si conta (il tentativo corrente conta subito), per il login e per i moduli pubblici. Se D4 = B: `TRUSTED_PROXIES`, vuota per impostazione.
5. **Host canonico (D5)**: redirect delle GET, con test per `APP_URL` vuoto e configurato.
6. **Migrazioni**: `GET_LOCK` per impedire due esecuzioni contemporanee; splitter che rispetta le stringhe tra apici (un `;` o una riga che inizia con `--` dentro un testo non spezza né mutila la query) e le virgolette; solo file con nome `NNNN_nome.sql`; `migrations/CHECKSUMS` con l'hash di ogni migrazione esistente e test che fallisce se una cambia.
7. **Coerenza (D6)** e **vincoli (D7)**.
8. **`APP_ENV`**: valori ammessi `production`, `development`, `testing`; qualsiasi altro valore vale `production` e viene segnalato.
9. **Pagina "ricevuta"**: il riferimento si mostra solo se esiste nel database ed è stato creato da pochi minuti; altrimenti un testo generico.
10. **Log**: per le eccezioni del database si registrano solo SQLSTATE e codice, mai il messaggio completo (può contenere email o credenziali).
11. **Invio dopo la risposta**: supporto a `litespeed_finish_request` e `ignore_user_abort(true)` prima dei lavori differiti, così una pagina chiusa dal visitatore non lascia una email in `sending`.
12. **Caratteri invisibili e di direzione** (U+200B–U+200F, U+202A–U+202E, U+2066–U+2069, U+FEFF) rimossi da nomi, note, motivi ed etichette inseriti da pubblico o admin.
13. **File di negazione** `.htaccess` (`Require all denied`, con riserva `Deny from all`) in `storage/`, `app/`, `migrations/`, `bin/`, `templates/`, `content/`, `docs/`, `prompts/`, `tests/`, `docker/`, `legacy/`. È una difesa **parziale**: se l'hosting ignora tutti i `.htaccess` non protegge; la difesa vera è il document root su `public/` e la verifica HTTP dei prompt 26 e 32. Scrivilo nella documentazione.
14. **Test-guardiani** (restano attivi per tutta la roadmap): checksum delle migrazioni; ogni `<?=` nei template passa da `e()`, `url()`, `asset()` o da un helper in allowlist esplicita; nessuna funzione pericolosa (`exec`, `shell_exec`, `system`, `passthru`, `proc_open`, `eval`, `unserialize` su dati esterni) in `app/`; nessuna URL esterna in `templates/` e `public/`; dipendenze di produzione solo PHPMailer; ogni rotta registrata compare nella matrice di `AdminAccessTest` (il test elenca le rotte e fallisce per quelle non coperte).
15. **Documenti**: riapri il finding F2 di `SECURITY_REVIEW` e chiudilo con la correzione; aggiungi una nota "riaperto" alla `FINAL_REVIEW` del prompt 13 dove le correzioni la modificano; registra in `TODO` i finding della review che non sono in questa fase (con la fase che li chiude).

## Test obbligatori

- **anonimizzazione**: cancellazione con un motivo che contiene un nome, poi `erase`; scansione di **tutte** le colonne di testo di **tutte** le tabelle: del nome non resta traccia (stesso test per i blocchi); le voci di Storico preesistenti vengono ripulite dalla migrazione;
- **rifiuto**: POST diretto senza conferma → nessuna modifica; con conferma → stato e email in coda; anteprima uguale all'email realmente accodata;
- **doppio invio**: due POST identici in parallelo (worker di `ConcurrencyTest`) → una sola riga e una sola email; nuovo token → nuova richiesta;
- **rate limit**: 20 login errati in parallelo → al massimo il limite configurato viene elaborato; `X-Forwarded-For` falsi non cambiano il client usato (con `TRUSTED_PROXIES` vuota);
- **host canonico**: GET con host diverso → 301; POST dall'host corretto invariato;
- **migrazioni**: due `migrate` in parallelo → una sola applicazione; splitter con `;`, `--`, apici e virgolette dentro le stringhe → testo identico riletto dal database; file già applicato modificato → test dei checksum fallisce; recupero dopo un errore a metà file documentato;
- **vincoli**: ogni `CHECK` rifiuta lo stato impossibile e accetta quelli validi; la migrazione si ferma con dati incoerenti senza modificarli;
- **coerenza**: database di prova con ciascuna incoerenza → il servizio la trova; database sano → nessun risultato;
- **APP_ENV**: `prod`, `PRODUCTION`, ` production`, vuoto → trattati come produzione;
- **log**: eccezione del database che contiene un'email → non compare nel log; cartella dei log non scrivibile → il sito funziona e usa `error_log`;
- **invio differito**: finto `finish_request`; con visitatore che abbandona nessuna riga resta in `sending`;
- **caratteri invisibili**: input con U+202E e zero-width → ripuliti, nome visibile corretto;
- **guardiani**: ciascuno fallisce se si inserisce di proposito la violazione (verificalo una volta);
- suite completa PASS, anche con `--order-by=random` (registra il seme).

Aggiorna `SECURITY_REVIEW`, `TEST_REPORT`, `COMMANDS` (`bin/check-consistency.php`, nuovo formato delle migrazioni, `TRUSTED_PROXIES` se scelta), `DECISIONS`, `TODO`, `SESSION_STATE`.

## Prova tu (5–10 minuti, a fine fase)

L'agente elenca questi passi nel riepilogo finale; l'utente li esegue in locale.

1. Da una richiesta in attesa premi "Rifiuta": deve comparire la pagina di conferma con l'anteprima dell'email (confronta con `docs/mockup-admin/rifiuto.html`); "Torna indietro" non cambia nulla.
2. Nel modulo pubblico premi due volte "Invia" (con la rete rallentata dagli strumenti del browser): deve esistere una sola richiesta.
3. Cancella una prenotazione di prova scrivendo un nome nel motivo, anonimizza quell'ospite e apri lo **Storico**: il nome non deve comparire.
4. Esegui `php bin/check-consistency.php`: su dati sani non deve segnalare nulla.
5. Con `curl -I -H "Host: www.esempio.it" http://localhost:8080/` (e `APP_URL` impostato) deve arrivare un redirect verso l'host canonico.

## Stop condition

Fermati quando i problemi elencati sono corretti, i test-guardiani sono attivi, la suite completa è PASS e `SECURITY_REVIEW` riporta F2 riaperto e chiuso. Fermati prima di iniziare se l'utente non ha risposto alle domande [titolare]. **Non** aggiungere funzioni del CMS (prompt 16 e seguenti), **non** cambiare regole di prezzo o prenotazione (prompt 23), **non** creare pagine admin nuove oltre alla conferma del rifiuto (la pagina Coerenza dati arriva nel prompt 26).
