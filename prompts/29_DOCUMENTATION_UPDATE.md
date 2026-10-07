# Prompt 29 — Aggiornamento della documentazione

Prerequisito: prompt 28 completato (nessun FAIL aperto).

Contesto: la documentazione di consegna è stata scritta nel prompt 12, **prima** della gestione contenuti. Le fasi 15–28 hanno corretto l'esistente e aggiunto tabelle, pagine admin, upload, regole, email, strumenti di sistema e test-guardiani. Questa fase **aggiorna**, non riscrive.

## Obiettivo

Riportare README e documenti di consegna allo stato reale del progetto e dare al titolare un manuale per usare l'admin senza assistenza.

## Prima di modificare

- Verifica lo stato del prompt 28 nel codice e in `TEST_REPORT`.
- Rispetta `docs/GUARDRAIL_FASI.md`: nessun segreto, nessun deploy, numeri verificati.
- Leggi il `README.md` e i documenti prodotti nel prompt 12, `CMS_DESIGN.md`, `THREAT_MODEL.md`, `COMMANDS.md`, `SECURITY_REVIEW.md`, `IMAGES.md`, `REGOLE_E_PREZZI.md`, `MANUAL_CHECKLIST.md`, `CAMPI_CONTENUTI.md` e `docs/NOVITA_ADMIN.md` (le righe per il titolare scritte alla fine di ogni fase: sono la base del manuale).
- Leggi in `docs/REVIEW_PRE_ROADMAP.md` le sezioni "Disaster recovery: procedure mancanti", D.3 (controlli periodici) e "Cosa farà il titolare tra sei mesi".
- `git status` e diff locali.

## Domande per l'utente (all'inizio della fase)

Prima di modificare qualsiasi file, apri `docs/RISPOSTE_UTENTE.md`: se l'utente ha già segnato le risposte per questo prompt, mostrale e chiedi solo conferma. Altrimenti presenta le domande di questa sezione **così come sono scritte** (risposte possibili e consiglio) e **fermati finché non risponde**.

Le domande sono di due tipi, indicati nel titolo:

- **[titolare]**: decisioni sul business (prezzi, regole, testi, comunicazioni agli ospiti, dati legali, servizi esterni). Non applicare mai una risposta ✅ al posto dell'utente: serve la sua risposta scritta in chat ("consigliate" vale, se detto esplicitamente per questa fase). Senza risposta la fase non inizia.
- **[tecnica]**: scelte di implementazione. Se l'utente risponde "consigliate" o non ha preferenze, vale la risposta ✅; elencale comunque nel riepilogo finale.

Registra ogni scelta in `docs/DECISIONS.md`. In `docs/RISPOSTE_UTENTE.md` (casella con `x`) scrivi **solo** ciò che l'utente ha detto in chat, mai risposte tue. Le voci rinviate o rifiutate vanno in `docs/TODO.md` con il motivo. Non implementare nulla che non sia stato approvato. Se una voce approvata supera lo scope di questa fase, proponi di spostarla e fermati.

### D1 — Manuale per il titolare [titolare]

| Risposta | Pro / contro |
|---|---|
| A. ✅ `docs/MANUALE_TITOLARE.md`, scritto per un non tecnico, stampabile | Un solo documento, facile da aggiornare |
| B. Pagina "Aiuto" dentro l'admin | Sempre a portata; testo da mantenere nel codice |
| C. Entrambi | Completo; due posti da tenere allineati |

**Consiglio: A.** Le pagine admin hanno già spiegazioni brevi dove servono.

### D2 — Pacchetto di prompt dopo la pubblicazione [titolare]

I prompt, la guida per le AI e la bozza dell'admin servono per costruire il sito, non per gestirlo.

| Risposta | Pro / contro |
|---|---|
| A. ✅ Dopo il prompt 32 si spostano in `docs/archive/` (restano nella cronologia Git); in `docs/` restano solo i documenti di consegna | Chi riprende il progetto non si confonde; nulla va perso |
| B. Restano dove sono | Nessun lavoro; il repository resta pieno di documenti di lavoro |
| C. Si eliminano | Repository più leggero; si perde il metodo di lavoro |

**Consiglio: A.** Questa fase registra l'attività in `TODO` (da eseguire dopo il prompt 32), non la esegue.

## Aggiorna almeno

1. architettura e struttura (nuove tabelle, servizi, pagine admin);
2. schema DB e migrazioni (import da phpMyAdmin senza SSH);
3. `.env.example`: cosa resta in `.env` e cosa si gestisce dall'admin;
4. requisiti hosting: versioni decise nel prompt 28, estensioni `pdo_mysql`, `gd` con JPEG/WebP, `fileinfo`, limiti di upload;
5. creazione e recupero dell'admin senza SSH;
6. backup e ripristino: **database + cartella foto + originali**;
7. attività pianificate: esecuzione automatica a ogni visita, cron facoltativo, pagina dell'admin; cosa succede senza cron;
7b. strumenti di sistema dall'admin (stato, email, manutenzione, registro errori, aggiornamenti, backup, conservazione dati) e **ciò che resta fuori dall'admin**;
7c. `docs/CAMPI_CONTENUTI.md` e `docs/THREAT_MODEL.md` come riferimento per chi modifica il codice; i test-guardiani (migrazioni immutabili, escaping, funzioni vietate, matrice delle rotte) e come aggiungere una nuova rotta o un nuovo campo senza romperli;
8. modalità manutenzione, aggiornamenti dell'applicazione e pacchetto di rilascio (se approvato);
9. test eseguiti, risultati, limitazioni, dati mancanti;
10. **manuale del titolare** (D1): richieste e prenotazioni, listino e simulatore, appartamenti, foto (provenienza e testo alternativo), pagine, impostazioni, password, checklist di pubblicazione, backup e manutenzione, cosa succede se lasci un campo vuoto (dal contratto dei campi), piccolo glossario (richiesta e prenotazione, blocco, periodo tariffario, regola aggiuntiva);
11. **"cosa fare se…"** nel manuale: un'email non parte; il sito mostra un errore o "torniamo presto"; un aggiornamento fallisce; le foto non si caricano; si dimentica la password; sospetti che il sito sia stato compromesso (cambiare password dell'admin, del database e della posta; rigenerare `APP_SECRET`; reinstallare il codice dal repository e non dai file sul server; ripristinare l'ultimo backup sano; controllare lo Storico);
12. **disaster recovery**: database cancellato o corrotto, cartella foto cancellata, `.env` perso (tenerne una copia nel gestore di password del titolare, **non** nel backup del sito), `APP_SECRET` perso (conseguenze: token e rate limit si rigenerano, la password SMTP va reinserita), password SMTP persa, PHP cambiato dal provider;
13. **promemoria periodici**: ogni settimana (backup, dashboard senza voci rosse), ogni mese (`composer audit` per chi mantiene il codice, prova email, spazio su disco), ogni 6 mesi (prova di ripristino, versione PHP del provider, rinnovo di dominio e certificato), ogni anno (listino dell'anno dopo, testi legali, privacy e cookie con il consulente); pagina "rinnovi e scadenze" da compilare (dominio, hosting, certificato, consulente privacy);
14. **numeri verificati**: ogni numero che scrivi (test, migrazioni, tabelle, versioni) è stato letto dal repository in questa sessione.

## Regole

Documenta il repository reale; verifica i comandi o marcali NOT RUN; niente segreti; niente deploy; evidenzia i passaggi che richiedono provider o autorizzazioni esterne.

## Stop condition

Fermati quando README, documenti e manuale descrivono lo stato reale. Fermati prima di iniziare se l'utente non ha risposto alle domande [titolare]. **Non** aggiornare la review finale (prompt 30); nessuna modifica al codice salvo commenti o esempi errati.
