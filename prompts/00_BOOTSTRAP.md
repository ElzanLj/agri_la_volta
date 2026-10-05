# Prompt 00 — Bootstrap / caricamento contesto

Questa fase è **read-only** rispetto al codice applicativo.

1. Leggi `AGENTS.md`.
2. Leggi `docs/SESSION_STATE.md`, `docs/PROJECT_CONTEXT.md`, `docs/PLAN.md`, `docs/TODO.md`, `docs/MISSING_DATA.md`, `docs/DECISIONS.md`.
3. Leggi `docs/SPEC.md` almeno nelle sezioni rilevanti alla fase corrente; se è la prima sessione, leggila integralmente.
4. Leggi `docs/AI_TOOL_NOTES.md`.
5. Esegui `git status` e ispeziona i diff locali senza modificarli.
6. Identifica branch/commit corrente e file modificati/non tracciati.

## Output in chat

Riassumi in massimo 15 righe:

- obiettivo del progetto;
- fase corrente;
- vincoli assoluti;
- working tree da preservare;
- dati mancanti rilevanti;
- prossimo prompt da eseguire.

## Stop condition

Non modificare codice, dipendenze, database o servizi esterni. Fermati dopo il riepilogo.
