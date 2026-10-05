# START HERE — Agriturismo La Volta AI Prompt Pack V2.1

Questo file è il punto di partenza rapido. Per la guida dettagliata leggi `GUIDA_UTILIZZO_AI.md`.

## Se è la prima volta che apri il progetto

1. Copia **il contenuto** di questo pacchetto nella root del repository `agri_la_volta`.
2. Verifica `git status` e assicurati di sapere quali modifiche erano già presenti.
3. Crea un checkpoint Git prima di modifiche importanti, se il repository lo consente.
4. Apri **una sola AI** dalla root del repository: Codex, Claude Code oppure Cursor.
5. Dille di eseguire `prompts/00_BOOTSTRAP.md`.
6. Poi dille di eseguire `prompts/01_AUDIT_REPOSITORY.md`.
7. **Fermati dopo l'audit.** Controlla `docs/AUDIT.md` prima di autorizzare migrazioni o grandi refactor.

Prompt minimo da usare:

```text
Leggi e applica le istruzioni del repository. Esegui prompts/00_BOOTSTRAP.md.
Non implementare ancora funzionalità e non modificare servizi esterni.
```

Dopo il bootstrap:

```text
Esegui prompts/01_AUDIT_REPOSITORY.md.
Al termine fermati e mostrami un riepilogo dei rischi, delle decisioni proposte e dei file creati/modificati.
```

## Se il progetto è già iniziato

All'inizio della sessione:

```text
Esegui prompts/90_SESSION_START.md. Poi proponimi UN solo obiettivo principale coerente con docs/PLAN.md e docs/TODO.md. Non iniziare a modificarlo finché non hai ricostruito lo stato corrente.
```

Alla fine della sessione:

```text
Esegui prompts/91_SESSION_END.md. Registra solo test realmente eseguiti, aggiorna lo stato del progetto e mostrami il diff/riepilogo prima di suggerire il commit.
```

## Regola più importante

**Una fase alla volta.** Non chiedere mai all'AI di “fare tutto il sito”. Ogni fase deve lasciare un risultato verificabile, testato e riprendibile.

## Non autorizzare alla cieca

Fermati e controlla prima di approvare operazioni che:

- cancellano o sovrascrivono dati;
- modificano DNS, dominio, email provider o servizi esterni;
- installano servizi cloud o dipendenze non previste;
- effettuano deploy o pubblicazione;
- eseguono migrazioni distruttive;
- rimuovono grandi parti del frontend esistente;
- modificano autenticazione, disponibilità, pricing o concorrenza senza test;
- inseriscono prezzi, dati legali, regole Novasol o altre informazioni non fornite.

## Quale AI usare

Puoi usare **solo Codex**, **solo Claude Code** oppure **solo Cursor** per tutto il progetto. Non è necessario alternarle.

- Codex: usa `AGENTS.md` + i documenti condivisi.
- Claude Code: parte da `CLAUDE.md`, che rimanda alle regole condivise.
- Cursor: usa `.cursor/rules/project.mdc` + i documenti condivisi.

Se cambi agente, usa `prompts/95_AGENT_HANDOFF.md`.

## File da conoscere davvero

- `docs/SPEC.md` — fonte di verità dei requisiti.
- `docs/PLAN.md` — calendario di lavoro.
- `docs/TODO.md` — cosa resta da fare.
- `docs/SESSION_STATE.md` — dove siamo adesso.
- `docs/MISSING_DATA.md` — dati che l'AI non deve inventare.
- `docs/TEST_REPORT.md` — test realmente eseguiti.
- `docs/DECISIONS.md` — decisioni tecniche importanti.
- `GUIDA_UTILIZZO_AI.md` — manuale operativo completo.

