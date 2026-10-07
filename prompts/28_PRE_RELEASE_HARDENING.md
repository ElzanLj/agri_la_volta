# Prompt 28 — Hardening pre-release

Prerequisito: prompt 27 completato.

Contesto: le fasi 15–27 hanno corretto l'esistente e aggiunto superfici nuove (account, impostazioni, upload, testi amministrabili, ID nelle rotte, email, ricerca, calendari, strumenti di sistema: aggiornamenti del database, backup, SMTP, registro errori). `composer.json` dichiara PHP ≥ 8.1 (senza aggiornamenti di sicurezza da fine 2025) e `docs/COMMANDS.md` MySQL 5.7+/MariaDB 10.3+ (fuori supporto). Test eseguiti finora solo su MariaDB 10.11 e PHP 8.2, in Docker con `AllowOverride All`.

## Obiettivo

Riportare l'intero progetto a uno stato verificato e mantenibile prima dell'aggiornamento della documentazione: sicurezza delle nuove superfici, compatibilità con più versioni e con hosting meno permissivi, regressione completa, pulizia dimostrata.

## Prima di modificare

- Vincoli: nessun nuovo framework, dipendenza Composer di produzione, JavaScript pubblico, React, Firebase o WordPress; nessun refactor fuori scope; preserva ciò che funziona; codice rimosso solo se dimostrato inutile. Rispetta `docs/GUARDRAIL_FASI.md` (ramo Git per fase, prove obbligatorie, contenuti come dati, file vietati, nessun segreto).
- Verifica lo stato dei prompt 15–27 **nel codice**, non solo nei documenti.
- Leggi `SECURITY_REVIEW`, `ACCEPTANCE_MATRIX`, `FINAL_REVIEW` (prompt 13), `TEST_REPORT`, `DECISIONS` (P4), `docs/THREAT_MODEL.md`, `docs/CAMPI_CONTENUTI.md`.
- Leggi `docs/REVIEW_PRE_ROADMAP.md`: sezioni C (difesa in profondità), D.2 (test esterni e manuali), I (cose da non fare) e K (ultima passata).
- `git status` e diff locali.

## Domande per l'utente (all'inizio della fase)

Prima di modificare qualsiasi file, apri `docs/RISPOSTE_UTENTE.md`: se l'utente ha già segnato le risposte per questo prompt, mostrale e chiedi solo conferma. Altrimenti presenta le domande di questa sezione **così come sono scritte** (risposte possibili e consiglio) e **fermati finché non risponde**.

Le domande sono di due tipi, indicati nel titolo:

- **[titolare]**: decisioni sul business (prezzi, regole, testi, comunicazioni agli ospiti, dati legali, servizi esterni). Non applicare mai una risposta ✅ al posto dell'utente: serve la sua risposta scritta in chat ("consigliate" vale, se detto esplicitamente per questa fase). Senza risposta la fase non inizia.
- **[tecnica]**: scelte di implementazione. Se l'utente risponde "consigliate" o non ha preferenze, vale la risposta ✅; elencale comunque nel riepilogo finale.

Registra ogni scelta in `docs/DECISIONS.md`. In `docs/RISPOSTE_UTENTE.md` (casella con `x`) scrivi **solo** ciò che l'utente ha detto in chat, mai risposte tue. Le voci rinviate o rifiutate vanno in `docs/TODO.md` con il motivo. Non implementare nulla che non sia stato approvato. Se una voce approvata supera lo scope di questa fase, proponi di spostarla e fermati.


### D1 — Versione minima di PHP [tecnica]

| Risposta | Pro / contro |
|---|---|
| A. PHP 8.2 | Più hosting compatibili; supporto di sicurezza fino a fine 2026 |
| B. ✅ PHP 8.3 | Supporto fino a fine 2027, diffuso sugli hosting |
| C. PHP 8.4 | Più longevo; non ancora ovunque |

**Consiglio: B.** Immagine Docker di sviluppo su 8.3, suite provata anche su 8.4.

### D2 — Versione minima del database [tecnica]

| Risposta | Pro / contro |
|---|---|
| A. ✅ MySQL 8.0.19+ oppure MariaDB 10.6+ | Versioni supportate, `CHECK` applicati da entrambi, `DROP CONSTRAINT` disponibile (usato dal prompt 24) |
| B. Solo MariaDB 10.11+ | Massima coerenza con lo sviluppo; esclude hosting con MySQL |

**Consiglio: A**, con prova reale su MySQL 8.0 e 8.4 (servizio Docker solo di sviluppo).

### D3 — Analisi statica (PHPStan, solo sviluppo) [tecnica]

| Risposta | Pro / contro |
|---|---|
| A. No | Nessun lavoro |
| B. ✅ Sì, livello 5, poi alzarlo gradualmente | Trova errori reali senza mole di lavoro iniziale |
| C. Livello massimo subito | Più rigoroso; molte correzioni non urgenti |

**Consiglio: B.** È una dipendenza di sviluppo nuova: serve la tua risposta.

### D4 — Mutation testing (Infection, solo sviluppo) [tecnica]

Cambia di proposito il codice per verificare che i test se ne accorgano: trova test che passano anche con il codice sbagliato.

| Risposta | Pro / contro |
|---|---|
| A. ✅ Non ora | Nessuna dipendenza in più; il calcolo dei prezzi ha già test a proprietà (prompt 23) |
| B. Sì, solo su `app/Domain` | Misura la qualità dei test sulle regole più delicate; esecuzione lenta |

**Consiglio: A**, da riconsiderare dopo la prima stagione.

### D5 — Test automatici su GitHub a ogni push (CI) [titolare]

| Risposta | Pro / contro |
|---|---|
| A. ✅ Sì (GitHub Actions con MariaDB e MySQL) | I test girano sempre, non solo quando qualcuno se ne ricorda; attivazione sul repository da autorizzare |
| B. No | Nessun servizio esterno |

**Consiglio: A.** L'agente prepara il file; l'attivazione la fai tu in chat o sul repository.

### D6 — Cartella `legacy/` (decisione P4) [titolare]

| Risposta | Pro / contro |
|---|---|
| A. ✅ Eliminarla dal repository (resta nella cronologia Git); svuotare prima eventuali foto legacy caricate nel DB locale | Repository leggero, nessun rischio di pubblicare foto dubbie |
| B. Tenerla, ma escluderla dal pacchetto di rilascio | Riferimento sempre a portata; 33 MB inutili nel repository |
| C. Tenerla com'è | — |

**Consiglio: A**, solo dopo conferma esplicita e dopo aver verificato che inventario e proposte non ne abbiano più bisogno.

### D7 — Pacchetto di rilascio (sì / no) [tecnica]

| Proposta | Risposte | Consiglio e perché |
|---|---|---|
| Script (solo sviluppo) che crea lo zip di rilascio: senza `legacy/`, `tests/`, `docker/`, `docs/`, `prompts/`, file per le AI, `.env`; con `vendor/` senza PHPUnit | Sì / No | ✅ **Sì**: ogni aggiornamento carica esattamente i file giusti |

### D8 — Unicità delle notti garantita dal database [tecnica]

Oggi nessuna notte può essere prenotata due volte perché ogni scrittura prende il lock sull'appartamento e ricontrolla. È una sola garanzia, applicativa.

| Risposta | Pro / contro |
|---|---|
| A. ✅ Non ora: lock e controllo di coerenza (prompt 15 e 26) bastano per sei appartamenti | Nessuna modifica alla parte più delicata |
| B. Tabella `booking_nights(apartment_id, night, booking_id)` con `UNIQUE(apartment_id, night)`, riempita nella stessa transazione di conferma, modifica e cancellazione | Il database stesso impedisce la doppia prenotazione; tocca ogni percorso di scrittura e i test di concorrenza |

**Consiglio: A.** Registra la decisione e la condizione per riconsiderarla (più canali o più appartamenti).

## Attività

1. **Review di sicurezza** delle superfici nuove: autorizzazione e CSRF su tutte le rotte `/admin` (la matrice di `AdminAccessTest` elenca le rotte registrate), riautenticazione sulle azioni sensibili, IDOR su foto, sezioni, servizi, note e prenotazioni, XSS in testi e metadati, upload (set di file ostili), link iCal, cambio password, strumenti di sistema (aggiornamenti solo da `migrations/`, backup mai salvati in cartelle pubbliche, password SMTP, registro errori senza dati personali), log senza dati personali. Aggiorna `SECURITY_REVIEW` e `docs/THREAT_MODEL.md`.
2. **Contratto dei campi**: un test confronta i nomi dei campi dei moduli admin (`name="…"`) con le righe di `docs/CAMPI_CONTENUTI.md` e fallisce per ogni campo senza riga; ogni campo ha i test vuoto / troppo lungo / non valido / con HTML; dove mancano, aggiungili.
3. **Versioni** (D1–D2): `composer.json`, Dockerfile, `COMMANDS`; suite su PHP 8.3 e 8.4 e su MariaDB 10.6/10.11 e MySQL 8.0/8.4; **tutte** le migrazioni su MySQL, comprese quelle che sostituiscono vincoli `CHECK` (prompt 15 e 24).
4. **Hosting meno permissivi** (variante solo sviluppo del container): Apache con `AllowOverride None` e con `AllowOverride FileInfo`. Per ciascuna registra cosa succede: `/.env`, `/storage/logs/` e `/composer.json` non devono essere raggiungibili; se una direttiva dei `.htaccess` causa un 500, documenta quale commentare (il limite di 1 MB resta comunque nell'applicazione). Aggiungi le regole equivalenti per Nginx alla documentazione.
5. **Piccole protezioni**: `Cross-Origin-Resource-Policy: same-origin`; URL oltre 2.000 caratteri → 414; `Cache-Control: no-store` verificato sulle pagine del flusso di richiesta che mostrano dati inseriti.
6. **Strumenti** (D3–D5, D7): PHPStan, CI, pacchetto di rilascio, `composer audit` eseguito e registrato.
7. **Regressione**: suite completa in ordine predefinito e casuale (seme registrato), concorrenza ripetuta, `php -l`, `ConsistencyChecker` dopo i test di concorrenza e un **percorso end-to-end "primo giorno"** su database vuoto: installazione → creazione admin → impostazioni → foto → appartamento → listino → richiesta pubblica → conferma → modifica → cancellazione → anonimizzazione → backup → ripristino in un database vuoto → verifica di coerenza.
8. **Pulizia dimostrata**: rimuovi `templates/pages/home.php` e altro codice morto solo dopo averne provato l'inutilizzo; `legacy/` secondo D6; controlla che le credenziali dei test siano chiaramente finte e non riusate altrove.
9. Aggiorna `ACCEPTANCE_MATRIX` (anche foto, servizi, minimo persone, strumenti di sistema) e `MANUAL_CHECKLIST` con le prove manuali di `docs/REVIEW_PRE_ROADMAP.md` (sezione D.2), estese anche all'admin: ZAP baseline sul container locale, axe e Lighthouse, solo tastiera, VoiceOver e TalkBack, smartphone reali, zoom al 200%.

Correzioni: solo difetti reali, con un test che li riproduce; niente refactor estetici.

## Verifica

Tutte le esecuzioni registrate con esito reale in `TEST_REPORT` (comando, data, numero di test, riga finale); FAIL e NOT RUN con motivo.

## Prova tu (5–10 minuti, a fine fase)

L'agente elenca questi passi nel riepilogo finale; l'utente li esegue in locale.

1. Apri il report di OWASP ZAP (baseline) prodotto dall'agente: nessun allarme alto non spiegato.
2. Dal container con `AllowOverride None`, prova `curl -I http://localhost:8080/.env`: non deve restituire il file.
3. Carica nell'admin un file `prova.php.jpg` che in realtà è testo PHP: deve essere rifiutato.
4. Esegui `docker compose exec web composer test` due volte: la seconda con `--order-by=random`; entrambe devono passare.

## Stop condition

Fermati quando review, versioni, prove su hosting meno permissivi, regressione e pulizia sono completate e documentate e non restano FAIL aperti nel codice. Fermati prima di iniziare se l'utente non ha risposto alle domande [titolare]. **Non** aggiungere funzioni, **non** aggiornare README e documentazione di consegna (prompt 29), **non** eseguire prove sull'hosting reale (prompt 32).
