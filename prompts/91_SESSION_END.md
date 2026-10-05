# Prompt 91 — Fine sessione / checkpoint

Non iniziare nuove feature.

1. esegui test/lint/build pertinenti alle modifiche;
2. correggi errori introdotti dalla sessione quando possibile;
3. aggiorna `TODO` solo per attività realmente completate/verificate;
4. aggiorna `TEST_REPORT`;
5. aggiorna `DECISIONS`/`MISSING_DATA` se necessario;
6. aggiorna **sempre** `SESSION_STATE.md`;
7. controlla `git diff` per segreti o modifiche estranee;
8. se stai per cambiare agente, aggiorna anche `HANDOFF.md`.

Riporta:

- obiettivo;
- completato;
- file principali;
- test e risultati;
- FAIL/NOT RUN;
- blocchi;
- prossimo passo esatto.

Se il repo è coerente, proponi un messaggio di commit. Non fare push/merge/deploy senza autorizzazione.
