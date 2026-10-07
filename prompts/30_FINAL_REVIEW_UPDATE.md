# Prompt 30 — Aggiornamento della review finale

Prerequisito: prompt 29 completato.

Contesto: la review finale è stata fatta nel prompt 13, prima delle fasi 15–28 e prima della review `docs/REVIEW_PRE_ROADMAP.md`. Questa fase la **aggiorna** sullo stato reale e chiude, uno per uno, i finding di quella review.

## Obiettivo

Confrontare di nuovo il repository con **ogni criterio di accettazione**, inclusi quelli toccati o aggiunti dopo il prompt 13, e stabilire per ogni finding della review se è chiuso, rinviato o rifiutato.

## Prima di modificare

- Vincoli: nessun nuovo framework, dipendenza Composer di produzione, JavaScript pubblico, React, Firebase o WordPress; nessun refactor fuori scope; preserva ciò che funziona. Rispetta `docs/GUARDRAIL_FASI.md`.
- Verifica lo stato del prompt 29 e leggi `docs/SPEC.md`, `docs/ACCEPTANCE_MATRIX.md`, `docs/FINAL_REVIEW.md`, `docs/THREAT_MODEL.md`, `docs/CAMPI_CONTENUTI.md`, `TODO` (sezione "Finding della review") e le tabelle in testa a `docs/REVIEW_PRE_ROADMAP.md`.
- `git status` e diff locali; esegui la suite completa e registra comando, data, numero di test e riga finale.

## Domande per l'utente (all'inizio della fase)

Prima di modificare qualsiasi file, apri `docs/RISPOSTE_UTENTE.md`: se l'utente ha già segnato le risposte per questo prompt, mostrale e chiedi solo conferma. Altrimenti presenta le domande di questa sezione **così come sono scritte** (risposte possibili e consiglio) e **fermati finché non risponde**.

Le domande sono di due tipi, indicati nel titolo:

- **[titolare]**: decisioni sul business (prezzi, regole, testi, comunicazioni agli ospiti, dati legali, servizi esterni). Non applicare mai una risposta ✅ al posto dell'utente: serve la sua risposta scritta in chat ("consigliate" vale, se detto esplicitamente per questa fase). Senza risposta la fase non inizia.
- **[tecnica]**: scelte di implementazione. Se l'utente risponde "consigliate" o non ha preferenze, vale la risposta ✅; elencale comunque nel riepilogo finale.

Registra ogni scelta in `docs/DECISIONS.md`. In `docs/RISPOSTE_UTENTE.md` (casella con `x`) scrivi **solo** ciò che l'utente ha detto in chat, mai risposte tue. Le voci rinviate o rifiutate vanno in `docs/TODO.md` con il motivo. Non implementare nulla che non sia stato approvato. Se una voce approvata supera lo scope di questa fase, proponi di spostarla e fermati.


### D1 — Prova generale del titolare [titolare]

Nessun test automatico vede dove una persona non tecnica si blocca.

| Risposta | Pro / contro |
|---|---|
| A. ✅ Sì: 1–2 ore in locale con dati di prova. Chi userà l'admin esegue una lista di compiti (inserire recapiti e dati aziendali, caricare 3 foto, assegnare servizi, scrivere una sezione e nasconderla, creare un listino, ricevere una richiesta dal sito e confermarla, cancellare una prenotazione, scaricare un backup, cambiare la password) e l'agente annota dove esita, sbaglia o non capisce | Trova i problemi di usabilità che contano davvero; richiede tempo del titolare |
| B. No | Nessun impegno; i problemi emergono dopo il lancio |

**Consiglio: A.** Gli esiti vanno in `docs/FINAL_REVIEW.md`; i problemi bloccanti si correggono, gli altri vanno in `TODO`.

### D2 — Matrice "controllo → test" [tecnica]

| Risposta | Pro / contro |
|---|---|
| A. ✅ Tabella in `docs/FINAL_REVIEW.md`: per ogni controllo di sicurezza e per ogni finding chiuso, il test (o la procedura manuale) che lo dimostra | Si vede subito cosa è garantito da un test e cosa solo da una dichiarazione |
| B. Solo l'elenco dei finding | Meno lavoro; nessuna prova collegata |

**Consiglio: A.**

## Metodo

Rileggi integralmente `docs/SPEC.md`, `docs/ACCEPTANCE_MATRIX.md` e `docs/FINAL_REVIEW.md`. Verifica il codice, non solo i documenti. Per ogni criterio: `PASS`, `PARTIAL`, `FAIL` o `BLOCKED`, con evidenza concreta (file, test, comando o procedura manuale). Per ogni finding della review: **chiuso** (con evidenza), **rinviato** (con motivo e fase futura) o **rifiutato dall'utente** (con la sua risposta registrata).

## Controlli prioritari

Pagamenti e dati carta; account ospite; pending vs confirmed; conferma e rifiuto admin (con la pagina di conferma); overlap e concorrenza (anche con modifica e ripristino della prenotazione) e verifica di coerenza senza incoerenze; ricalcoli server-side; tariffe configurabili e regole che si sommano; minimo e massimo di persone; prenotazioni manuali; cancellazioni; doppio invio; SMTP e nuove email (tetti, nessun testo dell'ospite); admin anche senza SSH; riautenticazione sulle azioni sensibili; audit log e Storico senza dati personali; **anonimizzazione completa** (scansione dell'intero database); IT/EN; tastiera e telefono (sito e admin); foto (provenienza, upload sicuro, EXIF, varianti, quota); servizi degli appartamenti; contenuti amministrabili con escape; sezioni nascoste mai pubbliche; pagine appartamento; CSV; hosting condiviso e prove con `AllowOverride` ridotto; installabilità; backup con **prova di ripristino**; aggiornamenti del database con lock, checksum e manutenzione; **gestione dall'admin**: tutto ciò che il titolare deve poter fare è possibile dal browser, tranne l'elenco esplicito di `COMMANDS` ("Cosa resta fuori dall'admin"); **contratto dei campi**: ogni riga di `docs/CAMPI_CONTENUTI.md` implementata e testata; **test-guardiani** attivi (migrazioni immutabili, escaping dei template, funzioni vietate, matrice delle rotte); servizi esterni (iCal e CI solo se autorizzati).

## Correzioni

Solo bloccanti o ad alto impatto, contenute, con un test che le riproduce. Riesegui i test pertinenti dopo ogni correzione e la suite completa alla fine.

## Output

Aggiorna `docs/FINAL_REVIEW.md` (sezione nuova datata, senza cancellare la review del prompt 13, con la tabella dei finding e, se D2 = A, la matrice controllo → test), `ACCEPTANCE_MATRIX`, `DELIVERY_CHECKLIST` (rimuovi le note "Da riaprire" solo per ciò che è davvero chiuso), `SESSION_STATE`. Non fare deploy.

## Prova tu (10–20 minuti, a fine fase)

L'agente elenca questi passi nel riepilogo finale; l'utente li esegue.

1. Leggi la tabella dei finding: ogni voce rinviata o rifiutata deve avere un motivo che condividi.
2. Se hai scelto D1 = A, esegui la lista dei compiti e confronta le note dell'agente con la tua impressione.
3. Apri `docs/FINAL_REVIEW.md` e controlla che nessun criterio sia PASS senza un'evidenza che puoi aprire (file, test o procedura).

## Stop condition

Fermati quando ogni criterio e ogni finding hanno stato ed evidenza aggiornati. Fermati prima di iniziare se l'utente non ha risposto alle domande [titolare]. **Non** preparare la guida di rilascio (prompt 31) e non introdurre nuove funzioni.
