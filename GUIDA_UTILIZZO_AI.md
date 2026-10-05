# Guida completa all'uso dell'AI — Agriturismo La Volta

Versione pacchetto: **V2.1**  
Obiettivo: usare Codex, Claude Code o Cursor come agente di sviluppo mantenendo controllo, qualità, continuità e tracciabilità fino alla consegna. L'obiettivo temporale attuale è il 31 ottobre 2026, ma è indicativo e può essere posticipato.

---

## 1. A cosa serve questo pacchetto

Questo pacchetto non serve a dare all'AI un unico enorme prompt e sperare che realizzi tutto correttamente. Serve a trasformare il repository in una **fonte di verità persistente** che l'AI può leggere a ogni sessione.

La chat dell'agente è temporanea. Il repository, Git e i file in `docs/` sono la memoria del progetto.

Le priorità sono:

1. rispettare `docs/SPEC.md`;
2. evitare che l'AI inventi dati o requisiti;
3. lavorare per fasi piccole e verificabili;
4. eseguire test reali prima di dichiarare una fase completata;
5. lasciare il progetto sempre riprendibile;
6. poter usare una sola AI per tutto il progetto oppure cambiare agente senza perdere contesto.

---

# 2. Posso usare una sola AI?

Sì. È anzi il workflow più semplice.

Puoi scegliere:

- **Codex soltanto**;
- **Claude Code soltanto**;
- **Cursor soltanto**.

Non devi usare tutte e tre. I file condivisi funzionano con qualunque scelta.

Se rimani sempre con lo stesso agente, puoi ignorare quasi sempre `prompts/95_AGENT_HANDOFF.md` e `docs/HANDOFF.md`.

---

# 3. Struttura mentale del pacchetto

Non serve memorizzare tutti i file. Dividili mentalmente in quattro gruppi.

## A. Regole permanenti

- `AGENTS.md`
- `CLAUDE.md`
- `.cursor/rules/project.mdc`

Servono a far capire all'agente **come deve comportarsi**.

## B. Fonte di verità e memoria

- `docs/SPEC.md`
- `docs/PROJECT_CONTEXT.md`
- `docs/SESSION_STATE.md`
- `docs/DECISIONS.md`
- `docs/MISSING_DATA.md`
- `docs/TODO.md`
- `docs/TEST_REPORT.md`

Servono a ricordare **cosa deve fare il progetto, cosa è già stato fatto e cosa manca**.

## C. Controllo qualità e consegna

- `docs/ACCEPTANCE_MATRIX.md`
- `docs/SECURITY_REVIEW.md`
- `docs/DELIVERY_CHECKLIST.md`
- `docs/FINAL_REVIEW.md`
- `docs/RELEASE_GUIDE.md`
- `docs/COMMANDS.md`

Servono a verificare che il progetto sia realmente consegnabile.

## D. Prompt operativi

- `prompts/00...14` = fasi principali;
- `prompts/90...95` = gestione delle sessioni e casi speciali.

Servono a dire all'agente **cosa fare adesso**.

---

# 4. Installazione corretta nel repository

Estrai il pacchetto e copia **il suo contenuto** nella root del repository.

Risultato atteso:

```text
agri_la_volta/
├── .cursor/
│   └── rules/
│       └── project.mdc
├── docs/
├── prompts/
├── AGENTS.md
├── CLAUDE.md
├── START_HERE.md
├── GUIDA_UTILIZZO_AI.md
├── README.md
└── ...file applicativi esistenti...
```

Non mettere il pacchetto in una sottocartella tipo:

```text
agri_la_volta/Agriturismo_La_Volta_AI_Prompt_Pack_V2.1/
```

perché l'agente potrebbe non caricare automaticamente i file root.

Prima di iniziare:

```bash
git status
```

Devi sapere se esistevano già modifiche locali prima dell'arrivo dell'AI.

Se il repository è in uno stato coerente e puoi creare commit:

```bash
git add AGENTS.md CLAUDE.md .cursor docs prompts START_HERE.md GUIDA_UTILIZZO_AI.md README.md
git commit -m "docs: add AI development workflow"
```

Il commit è facoltativo, ma avere un checkpoint prima dei cambi applicativi è molto utile.

---

# 5. Prima sessione assoluta

La prima sessione deve essere **solo comprensione e audit**.

## Passo 1 — Avvia l'agente dalla root

### Codex

Apri Codex dalla root del repository.

Prompt:

```text
Leggi e applica le istruzioni del repository. Esegui prompts/00_BOOTSTRAP.md.
In questa fase non implementare funzionalità, non installare dipendenze e non modificare servizi esterni.
```

### Claude Code

Avvia Claude Code dalla root. `CLAUDE.md` deve indirizzarlo alle regole condivise.

Prompt:

```text
Esegui prompts/00_BOOTSTRAP.md seguendo CLAUDE.md, AGENTS.md e docs/SPEC.md.
Non implementare ancora funzionalità.
```

### Cursor

Apri in Cursor la root del repository e usa l'agente.

Prompt:

```text
Esegui prompts/00_BOOTSTRAP.md rispettando le Project Rules, AGENTS.md e docs/SPEC.md.
Non modificare ancora l'applicazione.
```

## Passo 2 — Audit

Dopo il bootstrap:

```text
Esegui prompts/01_AUDIT_REPOSITORY.md.
Al termine fermati. Non iniziare la fase 02.
Mostrami un riepilogo di:
- architettura attuale;
- dipendenze e servizi esterni;
- eventuali pagamenti o dati sensibili trovati;
- codice/contenuti riutilizzabili;
- rischi principali;
- gap rispetto a docs/SPEC.md;
- proposta di migrazione minima.
```

## Passo 3 — Fermati davvero

Il risultato importante è `docs/AUDIT.md`.

Non dire subito:

```text
Procedi con tutto.
```

Leggi almeno:

- cosa vuole conservare;
- cosa vuole eliminare;
- come vuole arrivare a PHP/MySQL;
- quali dipendenze vuole aggiungere;
- se propone framework o infrastruttura non necessaria;
- quali rischi considera critici.

Se propone una riscrittura totale senza motivazione, chiedi di confrontarla con una migrazione incrementale.

---

# 6. Regola di lavoro: un obiettivo principale per sessione

Evita prompt come:

```text
Fai database, admin, frontend, email e test.
```

Meglio:

```text
Oggi lavoriamo soltanto sul controllo di disponibilità e sui relativi test.
Leggi lo stato corrente e proponi un piano limitato a questo obiettivo.
```

Una buona unità di lavoro deve poter terminare con qualcosa di verificabile, per esempio:

- schema DB + migrazioni eseguibili;
- autenticazione admin + test;
- algoritmo non-overlap + test;
- pricing engine + test;
- form richiesta + validazione server-side;
- SMTP + gestione fallimento;
- export CSV;
- pagina appartamento responsive e accessibile.

---

# 7. Workflow consigliato per una sessione da 2–3 ore

Questo è il workflow standard per i giorni infrasettimanali.

## Minuti 0–10 — Ricostruisci il contesto

Prompt:

```text
Esegui prompts/90_SESSION_START.md.
Poi proponimi UN solo obiettivo principale realistico per questa sessione, coerente con docs/PLAN.md e docs/TODO.md.
Non modificare ancora file finché non hai ricostruito lo stato.
```

Controlla che l'obiettivo non sia troppo grande.

## Minuti 10–25 — Piano breve

Chiedi un piano con:

- file probabili da toccare;
- rischi;
- test da eseguire;
- stop condition.

Un buon prompt:

```text
Prima di implementare, dammi un piano di massimo 8 punti limitato a questa fase. Indica i test che eseguirai e le condizioni che ti farebbero fermare per chiedere una decisione.
```

## Minuti 25–100 — Implementazione

Lascia lavorare l'agente, ma evita di approvare ciecamente operazioni rischiose.

Se il task è grande, usa checkpoint intermedi:

```text
Prima di continuare oltre questo sotto-obiettivo, mostrami cosa hai modificato e quali test hai eseguito.
```

## Minuti 100–140 — Test e diff

Chiedi:

```text
Ora non aggiungere nuove funzionalità.
Esegui i test pertinenti alla fase, controlla il diff Git e cerca regressioni o modifiche fuori scope.
Non correggere problemi estranei senza prima segnalarli.
```

## Ultimi 15–20 minuti — Chiusura

```text
Esegui prompts/91_SESSION_END.md.
Aggiorna docs/SESSION_STATE.md, docs/TODO.md e docs/TEST_REPORT.md con soli fatti verificati.
Mostrami:
1. cosa è stato completato;
2. cosa resta aperto;
3. test PASS/FAIL/NOT RUN;
4. eventuali rischi;
5. file modificati;
6. messaggio di commit suggerito.
```

Dopo aver controllato il diff puoi fare un commit coerente.

---

# 8. Workflow per una sessione da 5–6 ore

Nel weekend puoi affrontare attività più strutturali, ma non trasformare una sessione lunga in “fai tutto”.

Dividila in **due blocchi principali**, con un checkpoint tra i due.

Esempio:

### Blocco A

- implementazione booking/disponibilità;
- unit/integration test;
- review diff.

### Checkpoint

```text
Fermati qui. Verifica test e diff. Aggiorna SESSION_STATE con lo stato intermedio, ma non iniziare il prossimo blocco finché questo non è coerente.
```

### Blocco B

- casi limite/concorrenza;
- bugfix;
- documentazione del comportamento.

Non usare una sessione lunga per mescolare, per esempio, booking + redesign grafico + SEO + SMTP.

---

# 9. Come usare i prompt principali 00–14

## `00_BOOTSTRAP`

Quando: una sola volta all'inizio, oppure se vuoi forzare un agente a leggere il contesto da zero.

Cosa deve fare: comprendere il progetto, non implementare.

## `01_AUDIT_REPOSITORY`

Quando: prima di qualunque migrazione importante.

Stop: audit scritto e review umana.

## `02_ARCHITECTURE_DATABASE`

Quando: audit approvato.

Obiettivo: decidere architettura minima, DB e migrazioni senza overengineering.

## `03_FOUNDATION_MIGRATION`

Quando: architettura definita.

Obiettivo: creare fondamenta funzionanti e una base eseguibile.

## `04_BOOKING_AVAILABILITY`

Quando: DB/fondamenta sono stabili.

È una fase critica. Deve coprire intervalli `[check_in, check_out)`, sovrapposizioni, prenotazioni manuali, blocchi, cancellazioni e concorrenza.

Non considerarla completata senza test.

## `05_PRICING`

Quando: disponibilità e modello dati sono chiari.

Non deve inventare importi. Può usare fixture chiaramente marcate come test.

## `06_ADMIN`

Quando: core booking/pricing funziona.

Area protetta, filtri, conferma/rifiuto, prenotazioni manuali, blocchi, prezzi, audit log, CSV.

## `07_EMAIL_WHATSAPP`

Quando: gli eventi applicativi esistono davvero.

Regola critica: salvare i dati prima del tentativo SMTP. Un fallimento email non deve far perdere la richiesta.

## `08_PUBLIC_FRONTEND`

Quando: backend core è pronto abbastanza da supportare il flusso pubblico.

Preserva componenti/contenuti utili quando conviene.

## `09_I18N_SEO_A11Y_PERF`

Quando: struttura pubblica è stabile.

Non ottimizzare prematuramente parti che stanno ancora cambiando molto.

## `10_SECURITY_PRIVACY_SPAM`

Quando: i flussi principali esistono.

Non trattarla come semplice checklist teorica: deve cercare vulnerabilità concrete nel codice.

## `11_TEST_REGRESSION`

Quando: core completo.

Non usare questa fase per inventare feature mancanti. Deve soprattutto verificare e correggere.

## `12_DOCUMENTATION`

Quando: installazione e comandi sono realmente noti.

La documentazione deve riportare comandi verificati, non supposizioni.

## `13_FINAL_REVIEW`

Quando: pensi che il progetto sia quasi finito.

Aggiorna la matrice di accettazione.

## `14_RELEASE_PREP_NO_DEPLOY`

Quando: consegna pronta.

Prepara release e checklist, **senza pubblicare**.

---

# 10. Prompt 90–95: quando usarli

## `90_SESSION_START`

Usalo all'inizio della maggior parte delle giornate.

## `91_SESSION_END`

Usalo prima di chiudere una sessione significativa.

## `92_CODE_REVIEW`

Usalo quando:

- hai finito una fase importante;
- hai dubbi su una modifica grossa;
- vuoi fare review prima del commit;
- vuoi verificare una modifica fatta manualmente.

Prompt aggiuntivo utile:

```text
Concentrati su bug, regressioni, sicurezza, integrità dati e violazioni di docs/SPEC.md. Non proporre refactor estetici salvo che risolvano un rischio concreto.
```

## `93_BUGFIX`

Usalo per un problema specifico.

Evita:

```text
Ci sono errori, sistemali tutti.
```

Meglio:

```text
Usa prompts/93_BUGFIX.md per questo problema:
[descrizione precisa]
Comportamento atteso:
[...]
Comportamento attuale:
[...]
Prima riproduci il problema; poi proponi la correzione minima; infine esegui il test di regressione.
```

## `94_CONTEXT_RECOVERY`

Usalo dopo:

- diversi giorni di pausa;
- nuova chat;
- perdita del contesto dell'agente;
- sessione precedente molto lunga/confusa.

## `95_AGENT_HANDOFF`

Solo se cambi AI o persona.

---

# 11. Come controllare il diff senza essere un reviewer perfetto

Non devi leggere ogni riga con la stessa profondità. Fai una review a livelli.

## Livello 1 — Scope

Chiediti:

- ha modificato solo file pertinenti?
- ha riscritto file enormi senza motivo?
- ha eliminato componenti/dati?
- ha aggiunto dipendenze inattese?

Comando utile:

```bash
git status --short
```

poi:

```bash
git diff --stat
```

## Livello 2 — Parti critiche

Leggi con maggiore attenzione modifiche che toccano:

- SQL/migrazioni;
- autenticazione/sessioni;
- disponibilità;
- pricing;
- conferma/cancellazione prenotazioni;
- gestione dati personali;
- invio email;
- file `.env`/configurazione;
- cancellazioni o rename massivi.

## Livello 3 — Chiedi all'agente di spiegare il diff

```text
Spiegami il diff corrente per gruppi funzionali. Per ogni gruppo indica:
- perché era necessario;
- rischio principale;
- test eseguiti;
- requisito di docs/SPEC.md che soddisfa.
Non modificare nulla mentre rispondi.
```

---

# 12. Git: strategia semplice

Git è la tua cintura di sicurezza.

## Fai commit quando

- una unità di lavoro è coerente;
- i test pertinenti sono passati oppure i limiti sono documentati;
- il diff è comprensibile;
- non ci sono modifiche sperimentali che vuoi perdere facilmente.

Esempi:

```text
feat: add booking availability validation
feat: add configurable seasonal pricing
feat: add protected admin authentication
fix: prevent overlapping confirmed bookings
test: cover consecutive booking dates
docs: document shared hosting setup
```

## Non accumulare una settimana in un solo commit

Se l'AI rompe qualcosa, commit piccoli rendono molto più facile capire dove.

## Non lasciare che l'agente faccia operazioni Git distruttive senza motivo

Approva con attenzione:

- `reset --hard`;
- `clean -fd`;
- force push;
- rebase massivi;
- cancellazione branch;
- checkout che sovrascrive modifiche locali.

---

# 13. Test: come non farsi ingannare dall'AI

La regola è semplice:

**“Non vedo un comando realmente eseguito = non considero il test eseguito.”**

L'agente deve registrare risultati come:

- `PASS`;
- `FAIL`;
- `NOT RUN`;
- eventualmente `BLOCKED`.

Non deve trasformare “dovrebbe funzionare” in PASS.

Prompt utile:

```text
Elenca soltanto i test che hai realmente eseguito in questa sessione, con comando e risultato. Tutto il resto deve essere NOT RUN o non elencato come test eseguito.
```

Per le parti critiche, chiedi test negativi e casi limite, non solo happy path.

Esempio disponibilità:

- checkout prima del check-in;
- zero notti;
- soggiorni consecutivi;
- overlap parziale;
- overlap totale;
- prenotazione cancellata;
- blocco manuale;
- due conferme concorrenti.

---

# 14. Cosa non devi mai approvare alla cieca

## Dati e produzione

- cancellazione DB;
- modifica dati reali;
- migrazione distruttiva senza backup/piano;
- deploy;
- upload su hosting;
- modifica DNS;
- modifica MX/SPF/DKIM/DMARC;
- cambio provider.

## Sicurezza

- credenziali inserite nel codice;
- `.env` reale committato;
- password admin in chiaro;
- disabilitazione CSRF “per far funzionare il form”;
- query SQL concatenate con input utente;
- bypass di autenticazione per velocizzare i test.

## Architettura

- Firebase/Supabase/cloud aggiunti senza necessità;
- Docker/VPS richiesti quando non necessari;
- framework pesante introdotto senza vantaggio concreto;
- microservizi/code/Redis per un problema che non li richiede.

## Requisiti di business

Non approvare valori inventati per:

- prezzi;
- stagioni;
- supplementi;
- bambini/animali;
- soggiorni minimi;
- Novasol;
- testi legali;
- foto con provenienza incerta;
- credenziali SMTP.

Devono finire in `docs/MISSING_DATA.md` se mancanti.

---

# 15. Segnali che l'AI sta andando fuori strada

Fermala se noti uno di questi comportamenti:

1. continua ad aggiungere “miglioramenti” non richiesti;
2. propone una nuova tecnologia ogni volta che incontra un problema;
3. modifica decine di file per una correzione piccola;
4. riscrive codice funzionante solo per stile;
5. dichiara completato qualcosa senza test;
6. non aggiorna `SESSION_STATE`/`TEST_REPORT`;
7. ignora `MISSING_DATA` e inventa valori;
8. vuole effettuare deploy per “verificare”;
9. costruisce integrazioni Booking/Airbnb/Novasol non richieste;
10. confonde richiesta `pending` con prenotazione `confirmed`;
11. considera il prezzo del browser come definitivo;
12. controlla la disponibilità solo lato client;
13. aggiunge account/login per gli ospiti;
14. reintroduce pagamenti.

Prompt di reset:

```text
Fermati. Non effettuare altre modifiche.
Rileggi AGENTS.md e le sezioni pertinenti di docs/SPEC.md.
Confronta il diff corrente con l'obiettivo della sessione e segnala tutto ciò che è fuori scope.
Non correggere ancora nulla.
```

---

# 16. Se l'agente commette un errore

Non chiedere immediatamente “rifai tutto”.

Procedura:

1. blocca nuove modifiche;
2. identifica il comportamento atteso;
3. individua il commit/diff che ha introdotto il problema;
4. usa `93_BUGFIX`;
5. chiedi correzione minima;
6. esegui test di regressione;
7. aggiorna stato/test.

Prompt:

```text
Non fare refactor aggiuntivi. Individua la causa del bug nel diff recente e correggila con la modifica minima compatibile con docs/SPEC.md. Prima della modifica spiegami la causa probabile. Dopo la modifica esegui il test che dimostra la regressione corretta.
```

---

# 17. Se un test fallisce

Un test fallito non significa automaticamente che il test sia sbagliato.

Chiedi:

```text
Analizza il test fallito senza modificarlo inizialmente. Determina se il problema è:
A) bug applicativo;
B) test errato;
C) ambiente/configurazione;
D) requisito ambiguo o dato mancante.
Portami evidenze prima di cambiare codice o test.
```

Non permettere all'agente di “far diventare verde” un test allentando un requisito senza spiegazione.

---

# 18. Se l'AI vuole installare una dipendenza

Chiedile sempre:

```text
Prima di installarla, spiegami:
1. quale problema concreto risolve;
2. perché non basta il codice/librerie già presenti;
3. impatto su hosting Linux condiviso;
4. manutenzione e sicurezza;
5. alternativa più semplice.
Non installare finché non hai risposto.
```

L'obiettivo non è evitare tutte le dipendenze, ma evitare quelle senza beneficio reale.

---

# 19. Come gestire una nuova chat o perdita di contesto

Non reinviare manualmente 21 pagine.

Usa:

```text
Esegui prompts/94_CONTEXT_RECOVERY.md.
Non modificare file finché non hai ricostruito lo stato da repository, Git e docs/.
```

Poi chiedi:

```text
Riassumi in massimo 15 punti:
- stato corrente;
- ultimo lavoro completato;
- test noti;
- problemi aperti;
- dati mancanti;
- prossimo obiettivo consigliato.
```

---

# 20. Come cambiare AI senza perdere lavoro

Se passi da Codex a Claude Code o Cursor:

## Con il vecchio agente

```text
Esegui prompts/91_SESSION_END.md e prepara docs/HANDOFF.md per un altro agente. Non iniziare nuovi task.
```

## Con il nuovo agente

```text
Esegui prompts/95_AGENT_HANDOFF.md. Prima ricostruisci lo stato e confrontalo con git status/diff. Non modificare nulla finché non hai segnalato eventuali incongruenze.
```

Il nuovo agente deve fidarsi prima del repository e dei documenti, non di una descrizione verbale incompleta.

---

# 21. Come usare Codex al meglio in questo progetto

Usalo come agente principale se sei già abituato al suo workflow.

Buone pratiche:

- avvialo sempre dalla root;
- lascia `AGENTS.md` stabile e relativamente breve;
- usa i prompt in `prompts/` per limitare lo scope;
- fagli eseguire comandi e test, non solo scrivere codice;
- per task critici chiedi prima il piano e poi l'implementazione;
- a fine sessione obbligalo a sincronizzare documentazione e stato.

Prompt tipo:

```text
Leggi lo stato corrente del repository e applica AGENTS.md. Oggi esegui solo prompts/04_BOOKING_AVAILABILITY.md. Prima proponi il sotto-obiettivo più piccolo che possiamo completare e testare in questa sessione. Non passare al pricing.
```

---

# 22. Come usare Claude Code al meglio

`CLAUDE.md` è volutamente breve. La specifica vera resta `docs/SPEC.md`.

Buone pratiche:

- evita di duplicare requisiti dentro `CLAUDE.md`;
- usa una nuova sessione quando il contesto diventa confuso e recuperalo da `94_CONTEXT_RECOVERY`;
- per modifiche ampie chiedi un piano scritto prima dell'implementazione;
- limita esplicitamente il task corrente.

Prompt tipo:

```text
Segui CLAUDE.md, AGENTS.md e docs/SPEC.md. Esegui prompts/05_PRICING.md soltanto per il pricing. Non modificare disponibilità o frontend se non strettamente necessario. Prima mostrami il piano e i test previsti.
```

---

# 23. Come usare Cursor al meglio

Le regole di progetto sono in `.cursor/rules/project.mdc`.

Buone pratiche:

- apri l'intera root, non singoli file isolati;
- usa l'agente per task multi-file;
- non tenere una singola conversazione infinita per l'intero progetto;
- quando la fase è grande, separa pianificazione e implementazione;
- review del diff prima di accettare modifiche importanti.

Prompt tipo:

```text
Applica le Project Rules e AGENTS.md. Usa docs/SPEC.md come fonte di verità. Esegui prompts/06_ADMIN.md limitandoti al sotto-obiettivo che possiamo completare e testare oggi. Prima dammi un piano breve.
```

---

# 24. Come scegliere l'AI senza cambiare il pacchetto

Non scegliere in base a “quale è più intelligente in assoluto”. Scegli quella con cui riesci a mantenere il workflow più affidabile.

Valuta:

- facilità nel leggere e modificare l'intero repository;
- qualità dei diff;
- capacità di eseguire test e correggersi;
- rispetto delle istruzioni;
- comodità del tuo IDE/terminale;
- limiti di utilizzo reali sul tuo account;
- velocità con cui riesci a revieware ciò che produce.

Se un agente funziona bene, **non c'è motivo di cambiarlo durante il progetto**.

---

# 25. Gestione del tempo e obiettivo di consegna

La pianificazione di dettaglio è in `docs/PLAN.md`.

Principio operativo:

- sessioni brevi: un sotto-obiettivo chiuso e testabile;
- sessioni lunghe: una fase importante divisa in blocchi verificabili;
- nelle fasi finali: niente nuove grandi feature se mettono a rischio stabilità e test;
- l'ultima fase va usata come buffer, review finale e preparazione consegna.

L'obiettivo temporale attuale è il **31 ottobre 2026**, ma è **indicativo e posticipabile**. Non saltare requisiti, sicurezza, integrità dei dati o test critici per rispettarlo.

Se sei in ritardo, taglia prima:

- refactor estetici;
- animazioni;
- miglioramenti non richiesti;
- integrazioni future;
- automazioni non necessarie.

Non tagliare:

- integrità prenotazioni;
- controllo server-side;
- sicurezza admin;
- salvataggio affidabile richieste;
- test critici;
- compatibilità hosting;
- documentazione necessaria all'installazione.

---

# 26. Una giornata tipo concreta

Supponiamo che siano le 18:00 e tu abbia circa 2 ore e mezza.

### 18:00

Apri repository e agente.

```text
Esegui prompts/90_SESSION_START.md e proponimi un unico obiettivo realistico per oggi.
```

### 18:10

Leggi proposta e restringila se necessario.

```text
Va bene. Prima dell'implementazione mostrami piano, file coinvolti e test previsti.
```

### 18:20–19:30

Implementazione.

Se l'agente amplia lo scope:

```text
Non estendere la fase. Metti eventuali miglioramenti futuri in docs/TODO.md e continua solo con l'obiettivo concordato.
```

### 19:30

```text
Fermati con le feature. Esegui i test pertinenti e controlla il diff per modifiche fuori scope.
```

### 20:00

Correggi solo bug della fase.

### 20:15

```text
Esegui prompts/91_SESSION_END.md e suggerisci il commit.
```

### 20:25

Tu controlli diff/status e fai commit se coerente.

Questo ciclo vale più di una sessione in cui l'agente produce 3.000 righe senza checkpoint.

---

# 27. “Se succede X, fai Y”

| Situazione | Cosa fare |
|---|---|
| Nuova giornata | `90_SESSION_START` |
| Nuova chat ma stessa AI | `94_CONTEXT_RECOVERY` |
| Cambio AI | `95_AGENT_HANDOFF` |
| Bug preciso | `93_BUGFIX` |
| Diff grande/dubbio | `92_CODE_REVIEW` |
| AI vuole riscrivere tutto | Fermala, chiedi confronto con migrazione incrementale |
| AI inventa prezzi/dati | Fermala, sposta il dato in `MISSING_DATA` |
| Test non eseguito | Segna `NOT RUN`, mai PASS |
| Test fallisce | Classifica causa prima di cambiare test/codice |
| Dipendenza nuova | Chiedi motivazione, compatibilità hosting e alternativa |
| Vuole deployare | Nega; usa fase 14 solo per preparazione |
| Trova dati reali/segreti | Non stamparli; ferma e segnala il rischio |
| Working tree confuso | Stop, `git status`, diff, recupero contesto |
| Tempo quasi finito | Stop feature, test + `91_SESSION_END` |
| Sei in ritardo sulla roadmap | Riduci scope non essenziale, non requisiti critici |
| Non sai cosa fare dopo | `SESSION_STATE` + `TODO` + `PLAN`, poi chiedi un solo prossimo obiettivo |

---

# 28. Domande utili da fare spesso all'AI

Non servono tutte insieme. Usale quando opportuno.

### Prima di implementare

```text
Qual è la soluzione più semplice che soddisfa integralmente questo requisito su hosting condiviso PHP/MySQL?
```

### Prima di aggiungere tecnologia

```text
Possiamo risolvere questo requisito senza aggiungere una nuova dipendenza o servizio?
```

### Prima di chiudere una fase

```text
Quale requisito della SPEC resta ancora non coperto in questa fase?
```

### Per sicurezza

```text
Quali input non fidati entrano in questo flusso e dove vengono validati/escaped/autorizzati?
```

### Per booking

```text
Dimostrami con test che due prenotazioni confirmed dello stesso appartamento non possono sovrapporsi, incluso il caso concorrente.
```

### Per pricing

```text
Dimostrami che il totale viene ricalcolato lato server e non accetta come autorevole il prezzo inviato dal browser.
```

### Per email

```text
Dimostrami che la richiesta resta salvata se SMTP fallisce.
```

### Per fine sessione

```text
Cosa hai verificato realmente e cosa stai soltanto inferendo?
```

---

# 29. Cosa deve contenere SESSION_STATE

Deve essere breve e utile a una persona che riapre il progetto domani.

Idealmente:

- fase corrente;
- ultima cosa completata;
- working tree pulito/sporco;
- branch/commit rilevante;
- test eseguiti;
- bug/blocchi aperti;
- dati mancanti che bloccano;
- prossimo obiettivo suggerito.

Non trasformarlo in un diario di 50 pagine.

---

# 30. Cosa deve contenere DECISIONS

Registra decisioni che un futuro agente potrebbe altrimenti rimettere in discussione.

Esempi:

- scelta architettura;
- libreria SMTP scelta e perché;
- strategia transazioni/locking;
- formato traduzioni;
- strategia URL IT/EN;
- gestione rate limiting;
- scelta su asset build-time.

Ogni decisione dovrebbe includere motivazione e conseguenze principali.

---

# 31. Cosa NON mettere nei documenti del repository

Non inserire:

- password reali;
- credenziali SMTP;
- segreti API;
- dati carta;
- dati personali non necessari;
- token di produzione;
- copie di database reali.

Usa placeholder e `.env.example`.

---

# 32. Review finale prima della consegna

Negli ultimi giorni usa questa sequenza:

1. `11_TEST_REGRESSION`;
2. `10_SECURITY_PRIVACY_SPAM` se serve una review finale aggiornata;
3. `12_DOCUMENTATION`;
4. `13_FINAL_REVIEW`;
5. correggi solo gap reali/ad alto impatto;
6. `14_RELEASE_PREP_NO_DEPLOY`.

Controlla `docs/ACCEPTANCE_MATRIX.md` riga per riga.

Uno stato corretto può essere:

```text
PASS
PARTIAL
FAIL
BLOCKED
NOT APPLICABLE
```

Non forzare tutto a PASS per “finire”. Una limitazione dichiarata è meglio di una falsa conferma.

---

# 33. Checklist personale prima di accettare una fase

Chiediti:

- L'AI ha rispettato lo scope?
- Ha toccato file inattesi?
- Ha aggiunto dipendenze?
- Ha inventato dati?
- Ha eseguito i test dichiarati?
- Il diff è comprensibile?
- I requisiti critici sono server-side?
- È compatibile con hosting condiviso?
- `SESSION_STATE` e `TEST_REPORT` sono aggiornati?
- Posso riprendere domani senza ricordarmi la chat?

Se la risposta all'ultima domanda è “no”, la sessione non è ancora chiusa bene.

---

# 34. Regola finale per usare bene l'AI

Usa l'agente come **sviluppatore molto veloce che deve però lavorare dentro un processo controllato**.

Non ottieni il risultato migliore dandogli più libertà possibile. Lo ottieni dandogli:

- contesto stabile;
- obiettivo limitato;
- criteri di uscita;
- test;
- checkpoint;
- Git;
- documentazione dello stato.

Per questo progetto la strategia consigliata è:

```text
comprendi → pianifica → implementa → testa → review diff → documenta stato → commit → prossima fase
```

Ripeti questo ciclo fino alla consegna.

