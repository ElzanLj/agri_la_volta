# Prompt 31 — Preparazione pubblicazione, SENZA deploy (ex prompt 14)

Prerequisito: prompt 30 completato (`docs/FINAL_REVIEW.md` aggiornato).

Contesto: questa fase scrive solo documenti e checklist. La verifica **sull'hosting reale** è il prompt 32, da eseguire solo dopo una pubblicazione autorizzata a parte.

## Obiettivo

Preparare istruzioni e checklist per una futura pubblicazione autorizzata senza modificare servizi esterni.

## Prima di modificare

- Rispetta `docs/GUARDRAIL_FASI.md`: nessun deploy, nessuna operazione esterna, nessun segreto.
- Verifica lo stato del prompt 30 e leggi `FINAL_REVIEW`, `DELIVERY_CHECKLIST`, `COMMANDS`, il manuale del titolare, `docs/THREAT_MODEL.md` e in `docs/REVIEW_PRE_ROADMAP.md` le schede A7, A8, A9, C11 e le sezioni D.2, D.3 e "Disaster recovery".
- `git status` e diff locali.

## Domande per l'utente (all'inizio della fase)

Prima di modificare qualsiasi file, apri `docs/RISPOSTE_UTENTE.md`: se l'utente ha già segnato le risposte per questo prompt, mostrale e chiedi solo conferma. Altrimenti presenta le domande di questa sezione **così come sono scritte** (risposte possibili e consiglio) e **fermati finché non risponde**.

Le domande sono di due tipi, indicati nel titolo:

- **[titolare]**: decisioni sul business (prezzi, regole, testi, comunicazioni agli ospiti, dati legali, servizi esterni). Non applicare mai una risposta ✅ al posto dell'utente: serve la sua risposta scritta in chat ("consigliate" vale, se detto esplicitamente per questa fase). Senza risposta la fase non inizia.
- **[tecnica]**: scelte di implementazione. Se l'utente risponde "consigliate" o non ha preferenze, vale la risposta ✅; elencale comunque nel riepilogo finale.

Registra ogni scelta in `docs/DECISIONS.md`. In `docs/RISPOSTE_UTENTE.md` (casella con `x`) scrivi **solo** ciò che l'utente ha detto in chat, mai risposte tue. Le voci rinviate o rifiutate vanno in `docs/TODO.md` con il motivo. Non implementare nulla che non sia stato approvato. Se una voce approvata supera lo scope di questa fase, proponi di spostarla e fermati.

### D1 — Backup in produzione [titolare]

| Risposta | Pro / contro |
|---|---|
| A. Solo i backup dell'hosting | Nessun lavoro; se l'account viene compromesso o chiuso, spariscono anche i backup |
| B. ✅ Procedura documentata: "Scarica backup" dall'admin (prompt 27) almeno ogni settimana e prima di ogni aggiornamento, copia su un supporto del titolare (disco o cloud personale) | Sicuro, nessun servizio nuovo; la dashboard ricorda se il backup è vecchio |
| C. Backup automatico verso un servizio esterno | Nessuna dimenticanza; un servizio in più da autorizzare e pagare |

**Consiglio: B**, con promemoria nella checklist; C se il titolare preferisce automatizzare.

### D2 — Monitoraggio esterno (avviso se il sito non risponde) [titolare]

| Risposta | Pro / contro |
|---|---|
| A. ✅ Sì, con un servizio gratuito che attiva il titolare | Sapere subito se il sito è giù; un account esterno da creare (non lo fa l'agente) |
| B. No | Nessun servizio esterno; ce ne si accorge dai clienti |

**Consiglio: A.** L'agente scrive solo le istruzioni. Il controllo deve coprire anche la scadenza del certificato.

### D3 — Prova di ripristino sull'hosting [tecnica]

| Risposta | Pro / contro |
|---|---|
| A. ✅ Prevista nel prompt 32: il backup scaricato dall'admin si ripristina su un **database di prova separato** dell'hosting (non su quello reale) | Si scopre se il ripristino funziona davvero con le versioni e i limiti dell'hosting |
| B. Solo la prova in locale | Nessun lavoro sull'hosting; nessuna garanzia lì |

**Consiglio: A**, se l'hosting permette un secondo database (altrimenti si documenta come NOT RUN).

### D4 — `security.txt` [tecnica]

| Risposta | Pro / contro |
|---|---|
| A. ✅ File `/.well-known/security.txt` con l'email del titolare per segnalazioni di sicurezza | Chi trova un problema sa a chi scrivere; richiede un indirizzo che il titolare legge |
| B. No | Nessun impegno |

**Consiglio: A.**

## Verifica/prepara

- configurazione di produzione documentata; `.env.example` completo senza segreti;
- estensioni PHP e limiti (`pdo_mysql`, `gd` con JPEG/WebP, `fileinfo`, `upload_max_filesize`, `post_max_size`) da verificare sull'hosting scelto;
- backup/ripristino (database + foto + originali) secondo D1; ripristino guidato via phpMyAdmin, provato almeno una volta in locale con un backup scaricato dall'admin;
- primo impianto del database (import via phpMyAdmin) e aggiornamenti successivi dalla pagina "Aggiornamenti" dell'admin (prompt 26), con backup e manutenzione;
- permessi: `storage/` e cartella foto scrivibili, nessuno script eseguibile nella cartella foto;
- creazione dell'admin senza SSH (procedura del prompt 17);
- HTTPS e cookie `Secure` come requisito; HSTS solo dopo HTTPS funzionante;
- configurazione email dall'admin (Sistema > Email) con email di prova; nessuna credenziale nei documenti; checklist DNS/record email (SPF, DKIM, DMARC) da comunicare al provider, **senza applicarla**;
- pacchetto di rilascio (script del prompt 28 se approvato; `docs/` e `prompts/` inclusi tra le esclusioni): cosa caricare e cosa escludere (`legacy/`, `tests/`, `docker/`, `docs/`, `prompts/`, `.cursor/`, `AGENTS.md`, `CLAUDE.md`, `GUIDA_UTILIZZO_AI.md`, `START_HERE.md`, `.env`, PHPUnit in `vendor/`);
- modalità manutenzione durante gli aggiornamenti: interruttore dell'admin, e file `storage/maintenance` come riserva quando si caricano file via FTP;
- URL del vecchio sito da preservare (redirect `/dovesiamo` già previsto; verificare altri URL indicizzati);
- **host canonico e HTTPS**: il sito risponde su un solo host (`www` o no) e il redirect a https lo fa l'hosting; elenco di cosa controllare se arrivano header di proxy (`TRUSTED_PROXIES`);
- **file sensibili**: `document root` su `public/` quando possibile; altrimenti `.htaccess` di root attivo; elenco di `curl` da eseguire dopo la pubblicazione (`/.env`, `/storage/logs/`, `/composer.json`, `/migrations/0001_initial_schema.sql`, `/legacy/`, `/docs/`); regole equivalenti per Nginx; cosa fare se l'hosting ignora i `.htaccess`;
- contenuti pronti prima del lancio: checklist "pronto per la pubblicazione" dell'admin senza voci aperte; nessuna foto con credito "LEGACY – NON PUBBLICARE"; privacy e cookie non più in bozza;
- **copia del `.env`** nel gestore di password del titolare (non nel backup del sito) e elenco delle voci;
- **primo giorno online**: checklist per il titolare (cosa guardare ogni ora il primo giorno, a chi telefonare);
- **ogni aggiornamento futuro** segue la stessa procedura: backup, manutenzione, caricamento, "Aggiornamenti", smoke test (registrala come procedura ripetibile, non solo per il primo rilascio);
- **regola**: dopo la pubblicazione nessuna migrazione scrive contenuti, solo schema (decisione del prompt 22);
- smoke test post-pubblicazione: "Stato del sistema" senza voci rosse, email di prova riuscita, pagine IT/EN, flusso di richiesta (se la regola del minimo persone è attiva, una richiesta per 1 persona viene rifiutata), login admin, caricamento di una foto, invio email, redirect legacy, calendario pubblico (se approvato);
- monitoraggio secondo D2 (disponibilità e scadenza del certificato);
- `security.txt` secondo D4;
- prova di ripristino sull'hosting secondo D3 (istruzioni per il prompt 32);
- rollback plan;
- ciò che resta fuori dall'admin (credenziali DB, `APP_SECRET`, `APP_URL`, `APP_ENV`, `APP_DEBUG`, `APP_TIMEZONE`, `HSTS_MAX_AGE`, `PUBLIC_FORM_MIN_SECONDS`): elencato con dove si imposta sull'hosting;
- **prova generale del titolare** (se fatta nel prompt 30): problemi aperti elencati;
- `DELIVERY_CHECKLIST` aggiornata.

## Divieti

Non pubblicare, non accedere o modificare DNS, nameserver, MX/SPF/DKIM/DMARC, non acquistare hosting o servizi, non creare account esterni, non importare dati reali in modo distruttivo.

## Output

Crea `docs/RELEASE_GUIDE.md` con passaggi numerati, prerequisiti, punti di autorizzazione e rollback. Segna chiaramente ciò che NON è stato eseguito. Crea anche la checklist per il prompt 32 (`docs/POST_DEPLOY_CHECKLIST.md`).

## Stop condition

Fermati quando `RELEASE_GUIDE.md` e `DELIVERY_CHECKLIST.md` sono completi e coerenti con `FINAL_REVIEW.md`. Fermati prima di iniziare se l'utente non ha risposto alle domande [titolare]. Nessun deploy, nessun accesso a servizi esterni: la pubblicazione richiede un'autorizzazione esplicita separata.
