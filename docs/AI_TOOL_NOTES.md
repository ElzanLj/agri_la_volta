# Note per l'uso con Codex, Claude Code e Cursor

Per la procedura completa, inclusi workflow giornaliero, Git, review dei diff, test e gestione errori, leggere `../GUIDA_UTILIZZO_AI.md`.

Il pacchetto usa gli stessi documenti e gli stessi prompt per tutti gli agenti.

## Codex

- Avvia dalla root del repository.
- `AGENTS.md` contiene le regole condivise.
- Inizia con `prompts/00_BOOTSTRAP.md`.
- Per sessioni nuove usa `90_SESSION_START` o `94_CONTEXT_RECOVERY`.

## Claude Code

- Avvia dalla root del repository.
- `CLAUDE.md` è un bootstrap breve che rimanda a `AGENTS.md`.
- Non duplicare le specifiche dentro `CLAUDE.md`.
- Se subentra a un altro agente usa `95_AGENT_HANDOFF.md`.

## Cursor

- Apri la root del repository.
- `.cursor/rules/project.mdc` richiama le regole condivise.
- Per attività grandi è preferibile far produrre prima un piano limitato alla fase e poi implementare.
- Non lasciare che una singola chat attraversi automaticamente tutte le fasi.

## Regola comune

Se il comportamento dello strumento differisce, il repository resta la fonte di verità: `docs/SPEC.md` + `AGENTS.md` + stato/documentazione Git. Non modificare i requisiti per adattarli al modello.
