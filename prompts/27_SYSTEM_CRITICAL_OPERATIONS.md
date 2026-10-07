# Prompt 27 — Operazioni critiche dall'admin: aggiornamenti, backup e conservazione dei dati

Prerequisito: prompt 26 completato.

Contesto: sono le operazioni **irreversibili o ad alto impatto**: modificano lo schema del database, producono una copia di tutti i dati personali, anonimizzano o cancellano. Oggi esistono `bin/migrate.php` (con lock, checksum dei file e splitter sicuro dal prompt 15) e `bin/privacy.php`; non esiste alcun backup. Hosting condiviso: timeout, limiti di memoria, nessun SSH. Questa fase usa la manutenzione e lo stato del sistema (prompt 26) e la riautenticazione (prompt 17). Il ripristino di un backup **resta fuori dall'admin** (phpMyAdmin con guida): un pulsante rischierebbe di cancellare dati reali per un clic sbagliato.

Riferimento visivo (non vincolante): `docs/mockup-admin/manutenzione.html`.

## Obiettivo

Implementare aggiornamenti del database, backup scaricabile (con prova di ripristino) e conservazione dei dati personali, con le protezioni necessarie per ognuno.

## Prima di modificare

- Vincoli: nessun nuovo framework, dipendenza Composer di produzione, JavaScript pubblico, React, Firebase o WordPress; nessun refactor fuori scope; preserva ciò che funziona; codice rimosso solo se dimostrato inutile. Rispetta `docs/GUARDRAIL_FASI.md` (ramo Git per fase, prove obbligatorie, contenuti come dati, file vietati, nessun segreto).
- Verifica lo stato dei prompt 15, 17 e 26.
- Leggi `app/Database/Migrator.php`, `bin/migrate.php`, `bin/privacy.php`, `PersonalDataService`, il servizio di coerenza (prompt 15), `ReauthGuard` (prompt 17), la manutenzione (prompt 26), `docs/IMAGES.md`, `docs/COMMANDS.md`, `docs/SECURITY_REVIEW.md`, `docs/THREAT_MODEL.md`.
- Leggi in `docs/REVIEW_PRE_ROADMAP.md` le schede A12, C40–C52, C59–C63, G11, i test D4, D15, D29 e le sezioni "Aggiornamenti/migrazioni", "Backup" e "Disaster recovery".
- Leggi `docs/CAMPI_CONTENUTI.md`: ogni campo nuovo (durata di conservazione, ecc.) segue la sua riga.
- `git status` e diff locali.

## Domande per l'utente (all'inizio della fase)

Prima di modificare qualsiasi file, apri `docs/RISPOSTE_UTENTE.md`: se l'utente ha già segnato le risposte per questo prompt, mostrale e chiedi solo conferma. Altrimenti presenta le domande di questa sezione **così come sono scritte** (risposte possibili e consiglio) e **fermati finché non risponde**.

Le domande sono di due tipi, indicati nel titolo:

- **[titolare]**: decisioni sul business (prezzi, regole, testi, comunicazioni agli ospiti, dati legali, servizi esterni). Non applicare mai una risposta ✅ al posto dell'utente: serve la sua risposta scritta in chat ("consigliate" vale, se detto esplicitamente per questa fase). Senza risposta la fase non inizia.
- **[tecnica]**: scelte di implementazione. Se l'utente risponde "consigliate" o non ha preferenze, vale la risposta ✅; elencale comunque nel riepilogo finale.

Registra ogni scelta in `docs/DECISIONS.md`. In `docs/RISPOSTE_UTENTE.md` (casella con `x`) scrivi **solo** ciò che l'utente ha detto in chat, mai risposte tue. Le voci rinviate o rifiutate vanno in `docs/TODO.md` con il motivo. Non implementare nulla che non sia stato approvato. Se una voce approvata supera lo scope di questa fase, proponi di spostarla e fermati.


### D1 — Aggiornamenti del database dall'admin [tecnica]

| Risposta | Pro / contro |
|---|---|
| A. Solo phpMyAdmin o riga di comando | Nessun rischio dal browser; il titolare non può aggiornare da solo |
| B. ✅ Pagina "Aggiornamenti": elenca le migrazioni in attesa (solo file `NNNN_nome.sql` già presenti in `migrations/`), verifica i checksum, chiede **password admin, backup scaricato negli ultimi 30 minuti (o conferma esplicita "ho un backup di oggi") e la parola "AGGIORNA"**, attiva la manutenzione, ne applica una per richiesta, registra l'esito | Come fanno i CMS diffusi; mai SQL scritto o caricato dal browser |
| C. Applicazione automatica al primo accesso dopo un aggiornamento | Nessun passaggio; un errore lascia il sito a metà senza che nessuno l'abbia deciso: **sconsigliata** |

**Consiglio: B.** Se una migrazione fallisce, la manutenzione **resta attiva**, l'esito è registrato e la pagina mostra la procedura di recupero (restituire il backup o correggere e riprovare).

### D2 — Sito pubblico con aggiornamenti in attesa [tecnica]

Se il codice nuovo viene caricato via FTP prima di applicare le migrazioni, il sito gira su uno schema vecchio.

| Risposta | Pro / contro |
|---|---|
| A. ✅ Il sito pubblico va in 503 automatico finché ci sono migrazioni in attesa; l'admin resta accessibile. Il controllo confronta il nome dell'ultima migrazione presente con un file di cache (`storage/cache/schema-version`): **nessuna query in ogni pagina** | Evita errori e dati scritti male; il sito è fermo finché non si aggiorna |
| B. Solo un avviso in dashboard | Il sito resta raggiungibile ma può dare errori |

**Consiglio: A.**

### D3 — Database più recente del codice [tecnica]

Succede se per errore si ricarica una versione vecchia del codice.

| Risposta | Pro / contro |
|---|---|
| A. ✅ Blocco: "Il database è più recente del codice: ricarica la versione corretta"; il sito pubblico va in 503 | Nessun dato scritto con regole vecchie |
| B. Solo un avviso | Il sito continua a funzionare in condizioni non previste |

**Consiglio: A.**

### D4 — Contenuto del backup [tecnica]

| Risposta | Pro / contro |
|---|---|
| A. Solo il database | Veloce e affidabile; le foto vanno copiate via FTP o con il backup dell'hosting |
| B. ✅ Database + foto in zip **a blocchi** (ognuno di al massimo 200 MB) con l'elenco dei blocchi da scaricare; senza `ZipArchive` solo database e elenco dei file | Copre anche le foto senza i timeout di un unico zip enorme |
| C. Database + foto in un unico zip | Un solo file; su hosting condiviso va facilmente in timeout o esaurisce la memoria |

**Consiglio: B.** Il database e le foto (master compresi, prompt 19) vanno sempre salvati insieme: un database senza foto lascia pagine con immagini rotte.

### D5 — Sicurezza del backup [tecnica]

Il backup contiene dati personali, l'hash della password admin e (se D1 = B del prompt 26) la password SMTP cifrata.

| Risposta | Pro / contro |
|---|---|
| A. ✅ Generato al volo, **mai salvato sul server** (tranne il file temporaneo dello zip, cancellato subito), con riautenticazione, limite di 3 all'ora, nome con la data, avviso "contiene dati personali: conservalo protetto", e registro dell'impronta SHA-256 (data, dimensione, hash: mai il contenuto) con cui verificare il file scaricato | Copie in mano al titolare in un clic; integrità verificabile |
| B. Come A, più zip cifrato con una password scelta al download (richiede libzip 1.2 o successivo) | Protegge il file se il computer è condiviso; la password va custodita |
| C. Senza registro e senza avvisi | Più semplice; nessuna verifica dell'integrità |

**Consiglio: A**, B se il computer è condiviso.

### D6 — Conservazione dei dati personali [titolare]

La durata va decisa con il consulente privacy; il sito la applica.

| Risposta | Pro / contro |
|---|---|
| A. ✅ Durata impostabile dall'admin (6–120 mesi, vuoto = nessuna pulizia), pulsante "Simula" che mostra quante richieste verrebbero anonimizzate, poi "Esegui" con riautenticazione e parola "ANONIMIZZA"; la pulizia **automatica** parte solo se la durata è impostata **e** una simulazione è stata confermata almeno una volta | Il titolare applica ciò che decide senza riga di comando, con più protezioni contro cancellazioni involontarie |
| B. Solo pulsante manuale, mai automatico | Nessuna cancellazione a sorpresa; facile dimenticarsene |
| C. Resta `DATA_RETENTION_MONTHS` in `.env` e `bin/privacy.php` | Nessun lavoro; serve la riga di comando |

**Consiglio: A.** Stesse regole di `bin/privacy.php` (richieste in attesa e soggiorni in corso esclusi) con **un solo servizio condiviso**, nessuna duplicazione.

### D7 — Prova di ripristino [tecnica]

La domanda non è "il backup viene creato?" ma "sappiamo che si può ripristinare?".

| Risposta | Pro / contro |
|---|---|
| A. ✅ Test automatico (backup generato → importato in un database vuoto → verifica di coerenza, conteggi identici, caratteri speciali e emoji intatti, marcatore finale presente) + procedura phpMyAdmin documentata e provata una volta in locale; la prova reale sull'hosting è nel prompt 32 | Un backup inutilizzabile si scopre subito, non il giorno del disastro |
| B. Solo la procedura documentata | Meno lavoro; nessuna garanzia |

**Consiglio: A.**

## Implementa

- **Aggiornamenti** (D1–D3): nuova migrazione con la colonna `checksum` in `schema_migrations` (riempita per le migrazioni esistenti dal file `migrations/CHECKSUMS`) e tabella `migration_runs` (inizio, fine, esito, errore breve, senza dati); pagina con elenco, stato, avvisi ("file modificato dopo l'applicazione", "database più recente del codice"); riautenticazione, backup recente, parola di conferma, manutenzione automatica; `ignore_user_abort(true)` e `set_time_limit(0)` dove permesso; **una migrazione per richiesta** con ricarica della pagina e stato visibile; `storage/cache/schema-version` aggiornato a ogni migrazione riuscita (D2). Nessun SQL che arrivi dal browser: si applicano solo file già presenti, nell'ordine.
- **Backup** (D4, D5): dump generato **senza `mysqldump`**, in streaming e a blocchi di 500 righe (memoria costante), con `CREATE TABLE` da `SHOW CREATE TABLE`, `utf8mb4`, `SET FOREIGN_KEY_CHECKS=0` in testa, ordine delle tabelle, valori `NULL` e binari corretti, intestazione con versione del sito, ultima migrazione e data, **riga finale di chiusura** (un file troncato si riconosce); escluso `rate_limit_hits`; `Content-Disposition` con la sola data, `Cache-Control: no-store`; zip delle foto a blocchi (D4) con file temporaneo casuale, permessi 0600 e cancellazione garantita (anche se l'utente interrompe); tabella `backup_log` (data, tipo, dimensione, SHA-256, mai il contenuto); audit "backup scaricato"; limite di 3 all'ora; nessun contenuto nei log.
- **Conservazione** (D6): campo durata (riga in `docs/CAMPI_CONTENUTI.md`), simulazione identica all'esecuzione, riautenticazione, parola "ANONIMIZZA", audit senza dati personali; la pulizia automatica gira come attività pianificata (prompt 26) solo alle condizioni di D6; test di scansione dell'intero database dopo l'esecuzione.
- **Dashboard** (registro del prompt 25): migrazioni in attesa, database/codice disallineati, backup vecchio (giallo oltre 7 giorni, rosso oltre 30), manutenzione attiva da più di un'ora.
- **Documentazione**: `COMMANDS` con "cosa fare se un aggiornamento fallisce" e "ripristino con phpMyAdmin passo per passo" (compreso il fuso orario della sessione di phpMyAdmin), `docs/THREAT_MODEL.md`, `docs/IMAGES.md` (backup di foto e master).

## Test obbligatori

- autorizzazione, CSRF e **riautenticazione** per ogni azione; parola di conferma obbligatoria per aggiornamenti e pulizia; backup recente richiesto (e conferma esplicita come alternativa);
- **aggiornamenti**: elenco corretto e in ordine; due esecuzioni parallele → una sola applicazione; fallimento a metà file (seconda istruzione che fallisce) → manutenzione ancora attiva, esito registrato, messaggio di recupero; richiesta HTTP interrotta dal client → l'esito è comunque registrato; file già applicato e modificato → blocco; nome di file non valido → ignorato; database con migrazioni sconosciute al codice → blocco e 503; migrazioni in attesa → 503 pubblico **senza query aggiuntive** sulle pagine; nessun SQL accettato da input;
- **backup**: il dump si importa in un database vuoto (prova reale); emoji, accenti, `NULL`, valori binari e chiavi esterne intatti; `rate_limit_hits` escluso; file troncato → marcatore finale assente; l'impronta registrata è uguale all'SHA-256 dei byte scaricati; nessun file temporaneo resta dopo il download, anche con interruzione; limite orario rispettato; intestazioni `no-store`; nessun contenuto nei log né nello Storico; zip a blocchi completo (tutte le foto e i master) e senza `ZipArchive` solo database + elenco;
- **prova di ripristino** (D7): backup → database vuoto → verifica di coerenza (prompt 15) senza incoerenze, conteggi per tabella identici, impronta di righe campione identica;
- **fault injection**: disco pieno o cartella temporanea non scrivibile durante lo zip → errore chiaro, nessun file parziale; connessione che cade durante una migrazione → esito registrato;
- **conservazione**: simulazione = esecuzione sulle stesse righe; esclusioni rispettate (in attesa, soggiorni in corso); pulizia automatica ferma finché non c'è una simulazione confermata; audit senza dati personali; scansione di tutto il database senza tracce;
- suite completa PASS.

Aggiorna `COMMANDS`, `SECURITY_REVIEW`, `docs/THREAT_MODEL.md`, `docs/IMAGES.md`, `TEST_REPORT`, `DECISIONS`, `MISSING_DATA`, `TODO`, `SESSION_STATE`.

## Prova tu (5–10 minuti, a fine fase)

L'agente elenca questi passi nel riepilogo finale; l'utente li esegue in locale.

1. In **Sistema › Manutenzione** scarica un backup e importalo in un database vuoto con phpMyAdmin o Docker; controlla che ci siano richieste, prenotazioni e foto (confronta con `docs/mockup-admin/manutenzione.html`).
2. Confronta l'impronta SHA-256 mostrata dalla pagina con quella del file scaricato (`sha256sum`): devono coincidere.
3. Aggiungi di proposito una migrazione di prova e apri **Aggiornamenti**: il sito pubblico deve andare in manutenzione, la pagina chiederti password, backup recente e la parola "AGGIORNA"; dopo l'applicazione il sito deve tornare normale.
4. Prova a premere "Applica" senza backup recente: deve rifiutare.
5. Con una durata di conservazione impostata, premi "Simula": deve dirti quante richieste verrebbero anonimizzate senza modificarne nessuna.

## Stop condition

Fermati quando le voci approvate sono implementate, sicure e testate, e la prova di ripristino automatica è PASS. Fermati prima di iniziare se l'utente non ha risposto alle domande [titolare]. **Non** aggiungere il ripristino dall'admin, **non** fare la review di sicurezza complessiva né cambiare versioni di PHP o del database (prompt 28), **non** eseguire prove sull'hosting reale (prompt 32).
