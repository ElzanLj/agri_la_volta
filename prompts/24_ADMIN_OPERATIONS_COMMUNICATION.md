# Prompt 24 — Operatività quotidiana e comunicazione con l'ospite

Prerequisito: prompt 23 completato.

Contesto: email gestite da una coda (`email_outbox`, `NotificationService`, `MessageBuilder`) con invio dopo il salvataggio e nuovi tentativi; oggi il cliente riceve email solo a conferma o rifiuto. Admin con filtri per periodo, appartamento e stato (`app/Http/Admin/ListFilters.php`), senza ricerca libera. Dati personali minimizzati (`PersonalDataService`, `bin/privacy.php`).

Riferimento visivo (non vincolante): `docs/mockup-admin/index.html`, `arrivi.html`, `richieste.html`, `richiesta.html`, `statistiche.html`.

## Obiettivo

Implementare gli strumenti quotidiani per il gestore e le comunicazioni con l'ospite approvati dall'utente.

## Prima di modificare

- Vincoli: nessun nuovo framework, dipendenza Composer di produzione, JavaScript pubblico, React, Firebase o WordPress; nessun refactor fuori scope; preserva ciò che funziona; codice rimosso solo se dimostrato inutile. Rispetta `docs/GUARDRAIL_FASI.md` (ramo Git per fase, prove obbligatorie, contenuti come dati, file vietati, nessun segreto).
- Verifica lo stato dei prompt 17 (riautenticazione) e 23; leggi `docs/PROPOSTE_PROGETTO.md`, `docs/PROPOSTE_SITO_TITOLARE.md`, `docs/THREAT_MODEL.md` e in `docs/REVIEW_PRE_ROADMAP.md` le schede C19, C22, C23, B19, B22, C18 e i test D23, D28.
- Leggi `app/Mail/**`, `migrations/0004_email_outbox.sql`, `bin/send-queued-mail.php`, `ListFilters`, `AdminQueryRepository`, i template admin di richieste e dashboard, `RequestFlowController`, `PersonalDataService`, `ScopeTest`.
- Leggi `docs/CAMPI_CONTENUTI.md` (approvato nel prompt 16): ogni campo toccato da questa fase segue la sua riga (obbligatorietà, limiti, comportamento se vuoto, segnalazione). Se un campo nuovo non ha una riga, aggiungila e falla confermare.
- `git status` e diff locali.

## Domande per l'utente (all'inizio della fase)

Prima di modificare qualsiasi file, apri `docs/RISPOSTE_UTENTE.md`: se l'utente ha già segnato le risposte per questo prompt, mostrale e chiedi solo conferma. Altrimenti presenta le domande di questa sezione **così come sono scritte** (risposte possibili e consiglio) e **fermati finché non risponde**.

Le domande sono di due tipi, indicati nel titolo:

- **[titolare]**: decisioni sul business (prezzi, regole, testi, comunicazioni agli ospiti, dati legali, servizi esterni). Non applicare mai una risposta ✅ al posto dell'utente: serve la sua risposta scritta in chat ("consigliate" vale, se detto esplicitamente per questa fase). Senza risposta la fase non inizia.
- **[tecnica]**: scelte di implementazione. Se l'utente risponde "consigliate" o non ha preferenze, vale la risposta ✅; elencale comunque nel riepilogo finale.

Registra ogni scelta in `docs/DECISIONS.md`. In `docs/RISPOSTE_UTENTE.md` (casella con `x`) scrivi **solo** ciò che l'utente ha detto in chat, mai risposte tue. Le voci rinviate o rifiutate vanno in `docs/TODO.md` con il motivo. Non implementare nulla che non sia stato approvato. Se una voce approvata supera lo scope di questa fase, proponi di spostarla e fermati.

### D1 — Email "richiesta ricevuta" al cliente [titolare]

Esclusa il 2026-10-06 perché la SPEC non la prevede.

| Risposta | Pro / contro |
|---|---|
| A. ✅ Sì, subito dopo l'invio, con riepilogo (riferimento, appartamento, date) e "non è ancora una prenotazione"; **senza alcun testo scritto dall'ospite** (né nome né note) | Il cliente sa che la richiesta è arrivata; coda e modelli IT/EN esistono già; con queste regole il modulo non può essere usato per recapitare messaggi scelti da un attaccante |
| B. No (come oggi) | Nessun lavoro; molti pensano che la richiesta sia persa |

**Consiglio: A.** Cambia una decisione registrata: annotalo in `DECISIONS`.

### D2 — Email di pre-arrivo [titolare]

| Risposta | Pro / contro |
|---|---|
| A. No | Nessun lavoro |
| B. ✅ Sì, 3 giorni prima dell'arrivo: indicazioni, orari, contatti | Meno telefonate, ottima impressione; serve un cron, oppure l'invio parte alla prima visita del sito di quel giorno |
| C. Sì, 7 giorni prima | Più anticipo; dettagli magari ancora da definire |

**Consiglio: B.** Testo dalle impostazioni e dalle pagine, mai inventato.

### D3 — Campi facoltativi nel modulo di richiesta [titolare]

| Proposta | Risposte | Consiglio e perché |
|---|---|---|
| "Orario di arrivo previsto" | Sì / No | ✅ **Sì**: utile all'organizzazione, facoltativo |
| "Come ci hai conosciuto?" (Google, Booking, passaparola, già ospite, altro) | Sì / No | ✅ **Sì**: capire i canali senza cookie né tracciamento |

Aggiorna `ScopeTest` (campi ammessi) e la privacy (dati raccolti).

### D4 — Richieste in attesa da troppo tempo (evidenziate in dashboard) [titolare]

| Risposta | Pro / contro |
|---|---|
| A. Dopo 24 ore | Spinge a rispondere subito; molte segnalazioni |
| B. ✅ Dopo 48 ore | Equilibrato |
| C. Dopo 72 ore | Meno rumore; rischio di perdere il cliente |

**Consiglio: B**, configurabile.

### D5 — Statistiche [titolare]

| Risposta | Pro / contro |
|---|---|
| A. Rinviare | Nessun lavoro |
| B. ✅ Versione minima: richieste al mese, confermate/rifiutate, notti occupate per appartamento, canali (da D3) | Dati già nel DB, nessun tracciamento; aiuta a decidere i prezzi |
| C. Grafici e confronti tra anni | Più ricco; più lavoro e probabilmente JavaScript |

**Consiglio: B**, solo tabelle.

### D6 — Calendari iCal [titolare]

SPEC §13 vieta integrazioni non autorizzate: la risposta dell'utente vale come autorizzazione, da registrare in `DECISIONS`.

| Risposta | Pro / contro |
|---|---|
| A. Niente | Nessun lavoro; prenotazioni degli altri canali sempre a mano |
| B. Solo esportazione: link privato da aggiungere al calendario del telefono | Comodo, rischio basso (link segreto revocabile) |
| C. ✅ Esportazione **e** import in sola lettura dei calendari di Booking/Novasol come blocchi | Riduce il rischio di doppie prenotazioni; l'import è grande: **va in una fase dedicata** dopo questo prompt |

**Consiglio: C** se l'agriturismo usa altri canali, altrimenti B. In questo prompt si fa solo l'esportazione; per l'import crea il prompt dedicato e fermati.

### D7 — Altre proposte (sì / no) [titolare]

| Proposta | Risposte | Consiglio e perché |
|---|---|---|
| Pagina "Arrivi e partenze" dei prossimi giorni, stampabile | Sì / No | ✅ **Sì**: è la domanda di ogni mattina |
| Ricerca libera per riferimento `LV-…`, nome, email, telefono | Sì / No | ✅ **Sì**: quando un cliente telefona si cerca per nome |
| Nel dettaglio richiesta: altre richieste in attesa sulle stesse date | Sì / No | ✅ **Sì**: il conflitto si vede prima della conferma |
| Note interne su richieste e prenotazioni (mai visibili al cliente) | Sì / No | ✅ **Sì**: "arriva tardi", "chiede il seggiolone" |
| Esportazione e anonimizzazione dei dati di un cliente dall'admin | Sì / No | ✅ **Sì**: oggi solo da riga di comando, impossibile senza SSH; con riautenticazione, simulazione e parola di conferma |
| Ricerca per data ("chi c'è il 12 agosto?") oltre a quella per nome, riferimento, email e telefono | Sì / No | ✅ **Sì**: è la domanda tipica al telefono |
| Riepilogo giornaliero via email al gestore | Sì / Rinvia | ✅ **Rinvia**: la dashboard copre quasi tutto e serve cron |
| Email in HTML | Sì / No | ✅ **No**: il testo semplice funziona ed è meno spesso marcato come spam |

### D8 — Messaggio personale nelle email all'ospite [titolare]

I modelli delle email restano nel codice (con segnaposto protetti). Il titolare può aggiungere un proprio testo.

| Risposta | Pro / contro |
|---|---|
| A. ✅ Un testo IT/EN nelle Impostazioni, aggiunto in fondo all'email di conferma (e del pre-arrivo, se approvato) | Personalizza senza rischi; testo semplice con escape |
| B. Modelli delle email modificabili interamente | Massima libertà; si possono rompere riferimenti, prezzi e date |
| C. Niente | — |

**Consiglio: A.**

### D9 — Email al titolare dopo molti accessi falliti [titolare]

Il prompt 15 mostra già in dashboard i tentativi falliti. L'email avvisa anche chi non apre l'admin.

| Risposta | Pro / contro |
|---|---|
| A. ✅ Sì: un'email se in un'ora ci sono più di 20 tentativi di accesso falliti (da qualsiasi indirizzo), al massimo una ogni ora | Un attacco distribuito non passa inosservato; richiede un nuovo tipo di email |
| B. No: basta il numero in dashboard | Nessuna email in più |

**Consiglio: A.**

## Implementa

- Per ogni campo: limite scritto sotto il campo, frase "se lo lasci vuoto…" per i facoltativi, errori accanto al campo con i valori conservati, comportamento sul sito come da `docs/CAMPI_CONTENUTI.md`.
Le voci approvate, ognuna con i propri test, riusando la coda email, le guardie admin, la riautenticazione del prompt 17 e i servizi esistenti. Nuove email solo tramite la coda (salvataggio prima, invio dopo). Nuove colonne o tabelle con una migrazione dedicata (la sua riga va in `migrations/CHECKSUMS`).

Dettagli tecnici da rispettare:

- **Tipi di email**: `migrations/0004_email_outbox.sql` ammette solo quattro tipi nel vincolo `chk_email_outbox_type`. Le nuove email (richiesta ricevuta, pre-arrivo, avviso accessi falliti) richiedono una migrazione che lo sostituisca. `DROP CONSTRAINT` vale per MariaDB 10.2.1+ e MySQL 8.0.19+ (su MySQL 8.0.16–8.0.18 serve `DROP CHECK`): scrivi la forma che funziona sulle versioni minime decise nel prompt 28 e provala su entrambi i database in quella fase.
- **Email "richiesta ricevuta"** (se D1 = A): contenuto fisso e senza testo dell'ospite; **tetto globale** (30 all'ora) e **per indirizzo** (3 in 24 ore); oltre il tetto la richiesta resta salvata e l'email non parte, con una riga nel registro; mai inviata se l'indirizzo è già "skipped".
- **Email di pre-arrivo** (se D2 ≠ A): nessun invio per prenotazioni cancellate o già iniziate; idempotente; testo preso da impostazioni e pagine, mai inventato.
- **Nuovi campi della richiesta** (orario di arrivo, canale): colonne in `booking_requests`; canale da elenco chiuso; vanno aggiunti all'anonimizzazione (`PersonalDataService`), al test che scansiona tutto il database, all'informativa privacy (dati raccolti), ai campi ammessi di `ScopeTest` e, se utile, all'export CSV.
- **Note interne**: tabella o colonna dedicata; escluse da CSV ed email; incluse nell'anonimizzazione e nel test di scansione.
- **Ricerca**: `LIKE` con escape di `%` e `_` (cercare `100%` non trova tutto); minimo 3 caratteri; risultati paginati; le ricerche per nome, email o telefono usano POST, così i dati personali non finiscono nei log di accesso né nella cronologia (il riferimento `LV-…` può usare GET). Nomi ed email salvati e cercati in forma Unicode NFC (così "José" scritto in due modi si trova), con indici su cognome ed email.
- **Link iCal** (se approvato): token casuale di almeno 32 byte, salvato **come hash**, mostrato una sola volta alla creazione; revocabile e rigenerabile; mai nei log né nello Storico (sostituito da `[token]`); contenuto "Occupato" senza nomi; solo GET; `Cache-Control: private`; rate limit.
- **Esportazione e anonimizzazione di un ospite dall'admin** (se approvate): riautenticazione (prompt 17); sempre prima la **simulazione** (quante righe, quali tabelle); poi esecuzione con **parola di conferma** ("ANONIMIZZA") digitata; stessa logica di `bin/privacy.php`, nessuna duplicazione; audit senza dati personali.
- **Email "accessi falliti"** (se D9 = A): al più una all'ora, senza indirizzi né nomi nel testo.
- **Pagina Email**: una riga spiega che "inviata" significa accettata dal server di posta, non letta né consegnata; le categorie d'errore del trasporto sono mostrate come istruzioni (la configurazione è nel prompt 26).
- **Messaggio personale** (D8): testo semplice, escape, massimo 1000 caratteri, mai HTML.

## Test obbligatori

- **contratto dei campi**: per ogni campo di questa fase i "Test minimi" di `docs/CAMPI_CONTENUTI.md` (vuoto, solo spazi, troppo lungo, non valido, con HTML, resa IT/EN, Storico) e i controlli tra campi che lo riguardano;
- email nuove: messe in coda nella stessa transazione, mai inviate due volte, IT/EN, fallimento SMTP che non perde nulla; **tetti** globale e per indirizzo rispettati; contenuto "richiesta ricevuta" senza testo dell'ospite (provalo con un nome e una nota ostili);
- **header injection**: nome, appartamento e oggetto con `\r\n`, `Bcc:` e caratteri di direzione → nessuna intestazione aggiunta, oggetto codificato in UTF-8;
- **Unicode**: emoji, caratteri combinati, U+202E, zero-width e apostrofi tipografici in nomi e note → salvati, cercabili (NFC), mostrati e codificati nelle email senza alterare la visualizzazione;
- **ricerca**: `%` e `_` letterali, query di 1–2 caratteri rifiutata, nessuna SQL injection, nessun dato personale nell'URL per le ricerche per nome;
- pre-arrivo: nessun invio per prenotazioni cancellate o già arrivate; idempotente;
- campi nuovi: validazione, escape, `ScopeTest` aggiornato, privacy aggiornata;
- ricerca: nessuna SQL injection, escape dei risultati, nessun dato in URL indicizzabili;
- link iCal: segreto di almeno 32 byte salvato come hash, revocato → 404, token assente dai log e dallo Storico, nessun nome nel contenuto;
- anonimizzazione dall'admin: stessa logica di `bin/privacy.php`, riautenticazione richiesta, simulazione identica all'esecuzione, parola di conferma obbligatoria, audit senza dati personali; **test di scansione** dell'intero database dopo l'anonimizzazione (inclusi note interne, orario di arrivo e canale);
- autorizzazione e CSRF su ogni azione; suite completa PASS.

Aggiorna `TEST_REPORT`, `DECISIONS`, `MISSING_DATA` (testi email da approvare), `TODO`, `SESSION_STATE`.

## Prova tu (5–10 minuti, a fine fase)

L'agente elenca questi passi nel riepilogo finale; l'utente li esegue in locale.

1. Invia una richiesta dal sito e guarda le email in coda in **Email** (`docs/mockup-admin/email.html`).
2. Apri **Arrivi e partenze** e stampa la pagina: confronta con `arrivi.html`.
3. Cerca una richiesta per nome e per riferimento `LV-…` in **Richieste**.
4. Prova esportazione e anonimizzazione di un ospite di prova in **Export**: deve chiederti la password, mostrarti prima la simulazione e farti scrivere la parola di conferma.
5. Cerca `100%` nella ricerca: non deve trovare tutte le richieste. Cerca un nome con l'accento e controlla che l'indirizzo della pagina non contenga il nome.
6. Dopo l'anonimizzazione cerca il nome dell'ospite in **Richieste**, **Prenotazioni**, **Storico** ed **Email**: non deve comparire da nessuna parte.

## Stop condition

Fermati quando le voci approvate sono implementate e testate. Fermati prima di iniziare se l'utente non ha risposto alle domande [titolare]. Se è stato approvato l'import iCal, crea il prompt dedicato (numerazione da concordare con l'utente) e fermati senza implementarlo. **Non** rifinire menu admin, checklist o pagine pubbliche (prompt 25).
