# Prompt 95 — Handoff tra Codex, Claude Code e Cursor

Stai subentrando a un altro agente. **Non ricominciare da zero e non reinterpretare automaticamente le decisioni già prese.**

## Fase 1 — Read-only

1. leggi `AGENTS.md` e il bootstrap specifico disponibile (`CLAUDE.md` o `.cursor/rules/...` se applicabile);
2. leggi `docs/SESSION_STATE.md` e `docs/HANDOFF.md`;
3. leggi `docs/DECISIONS.md`, `docs/MISSING_DATA.md`, `docs/TODO.md`, `docs/TEST_REPORT.md`;
4. leggi il prompt della fase corrente;
5. controlla branch/commit, `git status` e diff;
6. leggi le sezioni SPEC pertinenti.

## Fase 2 — Verifica continuità

Confronta documentazione e repository. Identifica:

- modifiche non committate da preservare;
- decisioni implementate;
- test già eseguiti;
- test non eseguiti;
- blocchi;
- eventuali incongruenze.

## Output prima di modificare

In massimo 15 righe dichiara:

- cosa consideri stato corrente;
- cosa NON toccherai;
- obiettivo successivo;
- file probabili;
- test previsti.

## Regola

Non rifare una parte funzionante soltanto perché useresti un approccio diverso. Se ritieni necessaria una deviazione significativa, registrala/proponila in `DECISIONS.md` con motivazione prima di implementarla.

Dopo il riepilogo, procedi soltanto con l'obiettivo già previsto dalla fase corrente.
