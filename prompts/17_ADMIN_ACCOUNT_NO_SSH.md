# Prompt 17 — Account admin gestibile senza SSH

Prerequisito: prompt 16 completato e design approvato.

Contesto: unico account admin (tabella `admin`), creato e modificato solo da `bin/create-admin.php` (riga di comando). Molti hosting condivisi non offrono SSH: oggi il titolare non può cambiare la password. Le sessioni sono legate all'hash della password (`app/Security/AdminAuth.php`, `Session.php`).

Riferimento visivo (non vincolante): `docs/mockup-admin/account.html`.

## Obiettivo

Permettere il cambio password dall'admin e documentare creazione e recupero dell'account **senza SSH**, senza registrazione pubblica né reset via email.

## Prima di modificare

- Vincoli: nessun nuovo framework, dipendenza Composer di produzione, JavaScript pubblico, React, Firebase o WordPress; nessun refactor fuori scope; preserva ciò che funziona; codice rimosso solo se dimostrato inutile. Rispetta `docs/GUARDRAIL_FASI.md` (ramo Git per fase, prove obbligatorie, contenuti come dati, file vietati, nessun segreto).
- Verifica che i prompt 15 e 16 risultino completati in `SESSION_STATE`/`DECISIONS` (il rate limit corretto e `docs/THREAT_MODEL.md` esistono già).
- Leggi `AdminAuth`, `Session`, `Csrf`, `RateLimiter`, `AdminController`, `bin/create-admin.php`, `app/routes_admin.php`, `docs/SECURITY_REVIEW.md` e in `docs/REVIEW_PRE_ROADMAP.md` le schede C1–C7, B18, B19.
- `git status` e diff locali.

## Domande per l'utente (all'inizio della fase)

Prima di modificare qualsiasi file, apri `docs/RISPOSTE_UTENTE.md`: se l'utente ha già segnato le risposte per questo prompt, mostrale e chiedi solo conferma. Altrimenti presenta le domande di questa sezione **così come sono scritte** (risposte possibili e consiglio) e **fermati finché non risponde**.

Le domande sono di due tipi, indicati nel titolo:

- **[titolare]**: decisioni sul business (prezzi, regole, testi, comunicazioni agli ospiti, dati legali, servizi esterni). Non applicare mai una risposta ✅ al posto dell'utente: serve la sua risposta scritta in chat ("consigliate" vale, se detto esplicitamente per questa fase). Senza risposta la fase non inizia.
- **[tecnica]**: scelte di implementazione. Se l'utente risponde "consigliate" o non ha preferenze, vale la risposta ✅; elencale comunque nel riepilogo finale.

Registra ogni scelta in `docs/DECISIONS.md`. In `docs/RISPOSTE_UTENTE.md` (casella con `x`) scrivi **solo** ciò che l'utente ha detto in chat, mai risposte tue. Le voci rinviate o rifiutate vanno in `docs/TODO.md` con il motivo. Non implementare nulla che non sia stato approvato. Se una voce approvata supera lo scope di questa fase, proponi di spostarla e fermati.

### D1 — Creare o recuperare l'account senza SSH [tecnica]

| Risposta | Pro / contro |
|---|---|
| A. ✅ `php bin/create-admin.php --print-sql` sul computer dello sviluppatore, poi import del SQL in phpMyAdmin | Nessuna pagina pericolosa online; serve PHP o Docker in locale |
| B. Installer web una tantum che si disattiva dopo l'uso | Tutto dal browser; se resta attivo per errore chiunque crea un admin |
| C. Chiedere all'assistenza dell'hosting | Nessun lavoro; tempi e disponibilità incerti |

**Consiglio: A.** Sicuro e documentabile in cinque passaggi.

### D2 — Cambio del nome utente dall'admin [tecnica]

| Risposta | Pro / contro |
|---|---|
| A. ✅ No, solo password | Meno superficie; il nome si cambia con la procedura D1 |
| B. Sì | Comodo; un campo in più da proteggere |

**Consiglio: A.**

### D3 — Secondo fattore di accesso (codice da app, TOTP) [titolare]

| Risposta | Pro / contro |
|---|---|
| A. ✅ Non ora | Account condiviso: con più persone la gestione dei codici è scomoda |
| B. Sì | Protezione molto più forte; codici di recupero da custodire |
| C. Solo se l'admin sarà usato da una sola persona | Equilibrio; serve saperlo |

**Consiglio: A**, da riconsiderare se l'admin resta di una sola persona.

### D4 — Controllo delle password comuni [tecnica]

| Risposta | Pro / contro |
|---|---|
| A. ✅ La nuova password viene rifiutata se è in un piccolo elenco di password comuni (file nel repository), contiene il nome utente o il nome dell'agriturismo | Impedisce "Agriturismo2026!" e simili; nessun servizio esterno |
| B. Solo la lunghezza minima di 12 caratteri | Nessun lavoro; password prevedibili ma lunghe |

**Consiglio: A.**

### D5 — "Esci da tutti i dispositivi" [tecnica]

| Risposta | Pro / contro |
|---|---|
| A. ✅ Pulsante nella pagina Account (chiede la password attuale): chiude ogni altra sessione senza cambiare la password | Telefono perso o password mostrata a qualcuno: si chiude tutto in un clic |
| B. Non serve: basta cambiare la password | Meno lavoro; cambiare la password è più scomodo |

**Consiglio: A.**

### D6 — Segnalare accessi sospetti [titolare]

| Risposta | Pro / contro |
|---|---|
| A. Niente | Nessun lavoro |
| B. ✅ Mostrare in Account la data dell'ultimo accesso riuscito e il numero di tentativi falliti recenti (già in dashboard dal prompt 15) | Si nota un accesso non proprio; nessuna email, nessun dato personale |
| C. Anche un'email "nuovo accesso" quando l'IP non è mai stato visto | Avviso immediato; serve un nuovo tipo di email e l'archivio degli IP (come hash): da aggiungere nel prompt 24 se lo vuoi |

**Consiglio: B.**

## Implementa

- Pagina `Account` sotto `/admin` (eredita le tre guardie di `routes_admin.php`): password attuale, nuova password due volte, minimo 12 caratteri come la CLI.
- Riusa hashing, confronto a tempo costante e invalidazione delle sessioni esistenti. Dopo il cambio la sessione corrente resta valida (rigenerata), le altre decadono.
- Rate limit sui tentativi con password attuale errata (stesso `RateLimiter`).
- Voce di audit "password cambiata", senza hash né valori.
- **Riautenticazione riusabile** (`ReauthGuard`): richiede di nuovo la password attuale per le azioni sensibili e ricorda la conferma per **5 minuti** nella sessione; scade con la sessione, con il cambio password e con "Esci da tutti i dispositivi"; ha un proprio rate limit; la pagina "Conferma la password" riporta all'azione richiesta. La usano da subito il cambio password e "Esci da tutti"; la useranno gli strumenti dei prompt 24 (esportazione e anonimizzazione di un ospite), 26 (impostazioni email) e 27 (aggiornamenti, backup, pulizia dati). Registra in `COMMANDS`/`SECURITY_REVIEW` come si applica a una nuova azione.
- Controllo password comuni (D4), "Esci da tutti i dispositivi" (D5) e segnalazioni di D6.
- Procedura scelta in D1; documenta anche il recupero della password dimenticata. **Niente installer web permanente.**

## Test obbligatori

- accesso negato senza login; POST senza token CSRF o con Origin estraneo rifiutato;
- password attuale errata, nuova troppo corta, conferma diversa: nessuna modifica;
- successo: vecchia password rifiutata, nuova accettata, altre sessioni chiuse, sessione corrente valida;
- rate limit dopo errori ripetuti;
- audit e log senza hash né password;
- `--print-sql` (se scelto): SQL valido, hash verificabile, password mai negli argomenti;
- riautenticazione: azione protetta senza conferma → pagina di conferma; password errata → nessuna conferma e tentativo contato; conferma valida entro 5 minuti, scaduta dopo 5 minuti e non valida in un'altra sessione; azzerata dal cambio password;
- "Esci da tutti": le altre sessioni decadono, quella corrente resta valida, la password non cambia;
- password comuni: elenco, nome utente e nome dell'agriturismo rifiutati (anche con maiuscole e cifre aggiunte), password lunga e casuale accettata.

Aggiorna `TEST_REPORT`, `COMMANDS` (Amministratore), `SECURITY_REVIEW`, `TODO`, `SESSION_STATE`.

## Prova tu (5–10 minuti, a fine fase)

L'agente elenca questi passi nel riepilogo finale; l'utente li esegue in locale.

1. Entra in `/admin`, apri **Account** e cambia la password: confronta con `docs/mockup-admin/account.html`.
2. Apri l'admin in un secondo browser prima del cambio: dopo il cambio quella sessione deve chiedere di nuovo il login.
3. Prova la password attuale sbagliata e una nuova troppo corta: nessun cambio, messaggio chiaro.
4. Segui la procedura senza SSH scritta in `COMMANDS.md` su una copia del database locale.
5. Con due browser aperti, premi "Esci da tutti i dispositivi" da uno: l'altro deve chiedere di nuovo il login (confronta con `docs/mockup-admin/account.html`).
6. Prova a usare una password comune come "Agriturismo2026!": deve essere rifiutata con un messaggio che spiega perché.

## Stop condition

Fermati quando cambio password e procedura senza SSH sono implementati, testati e documentati, e la suite completa è PASS. Fermati prima di iniziare se l'utente non ha risposto alle domande [titolare]. **Non** creare impostazioni del sito, pagine o foto (prompt 18+) e **non** applicare la riautenticazione alle azioni delle fasi successive (la applicano quelle fasi).
