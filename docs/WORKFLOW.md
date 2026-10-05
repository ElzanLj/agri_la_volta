# Workflow operativo AI-agnostic

## Principio

Il repository, Git e i file in `docs/` sono la memoria condivisa. La chat del singolo agente è temporanea.

## Ciclo di lavoro

### 1. Start

Usa `prompts/90_SESSION_START.md` oppure, dopo una pausa lunga/cambio agente, `prompts/94_CONTEXT_RECOVERY.md` o `95_AGENT_HANDOFF.md`.

### 2. Una sola fase/obiettivo principale

Scegli una unità di lavoro verificabile. Evita sessioni che mescolano DB, frontend, sicurezza e documentazione senza necessità.

### 3. Ispezione prima della modifica

Prima di modificare:

- controlla `git status`;
- leggi i diff locali;
- identifica test/comandi disponibili;
- leggi i file rilevanti;
- verifica i vincoli di `docs/SPEC.md` pertinenti.

### 4. Implementazione incrementale

- preserva il codice utile;
- fai cambi piccoli e coerenti;
- non fare refactoring estetico nello stesso task di una feature critica;
- non modificare servizi esterni;
- non inventare dati mancanti.

### 5. Verifica

Esegui realmente i controlli pertinenti: test, lint, build, migrazioni, query, test manuali. Se un controllo non è eseguibile, segna `NOT RUN` e il motivo.

### 6. Aggiornamento memoria del progetto

Aggiorna `SESSION_STATE`, `TODO`, `TEST_REPORT` e, quando necessario, `DECISIONS`/`MISSING_DATA`.

### 7. End

Usa `prompts/91_SESSION_END.md`. Lascia il repository in uno stato comprensibile e riprendibile.

## Quando cambiare agente

Puoi passare liberamente fra Codex, Claude Code e Cursor. Prima del cambio:

1. completa un checkpoint coerente se possibile;
2. aggiorna `docs/SESSION_STATE.md`;
3. registra test e blocchi;
4. usa `prompts/95_AGENT_HANDOFF.md` con il nuovo agente.

## Git

- Non richiedere un commit per ogni singolo file.
- Preferisci checkpoint piccoli e semanticamente coerenti.
- Non fare push/merge/rebase distruttivi senza istruzione esplicita.
- Un agente non deve annullare modifiche non sue solo per ottenere un working tree pulito.

## Escalation

Ferma la fase e segnala subito se trovi:

- possibili dati carta o segreti reali;
- rischio di cancellazione dati reali;
- modifica necessaria a DNS/email/provider;
- requisito incompatibile con hosting condiviso;
- conflitto sostanziale nella specifica;
- impossibilità di garantire non-overlap/concorrenza;
- dato di business indispensabile mancante che impedisce una decisione corretta.
