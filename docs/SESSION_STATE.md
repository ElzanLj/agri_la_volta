# Stato corrente del progetto

> Questo file è la memoria operativa breve da aggiornare alla fine di ogni sessione sostanziale o prima di cambiare agente.

## Snapshot

- **Data aggiornamento:** 2026-10-05
- **Agente/strumento ultimo utilizzato:** Claude Code
- **Branch:** `main`
- **Commit di riferimento:** `07baca4` (docs: add AI development workflow)
- **Fase corrente:** Fase 0 — audit e baseline: **COMPLETATA**
- **Prompt corrente:** `prompts/01_AUDIT_REPOSITORY.md` (completato); prossimo: `prompts/02_ARCHITECTURE_DATABASE.md`, dopo approvazione delle proposte e sblocco dei punti sotto
- **Stato complessivo:** audit completato; P1–P7 approvate; avvio Fase 1

## Obiettivo corrente

Eseguire `prompts/02_ARCHITECTURE_DATABASE.md`.

## Ultimo lavoro completato

Audit read-only del repository: `docs/AUDIT.md`. Nessun file applicativo modificato. Eseguiti `npm ci`, lint, build, dev server, `npm audit`.

## Working tree / modifiche locali da preservare

- `README_home.md` non tracciato: copia identica di `README.md`, creata dall'utente. Non toccata.
- `node_modules/` installato da `npm ci` (ignorato da Git). `dist/` generata e rimossa.
- Modifiche di questa sessione: solo file in `docs/`.

## Test/comandi più recenti

Vedi `docs/TEST_REPORT.md` e `docs/COMMANDS.md`: install PASS, lint FAIL (90 errori legacy), build PASS con warning, dev server PASS.

## Blocchi aperti

1. Risolto (utente, 2026-10-05): App Password Gmail revocata; Firebase verificato dal titolare.
2. Risolto (utente): ambiente locale PHP 8.2 + MariaDB 10.11 tramite Docker (`docker-compose.yml`, non tracciato).
3. Dati mancanti: vedi `docs/MISSING_DATA.md`.

## Decisioni da non reinterpretare

Vedi `docs/DECISIONS.md` (P1–P7 approvate dall'utente il 2026-10-05).

## Prossimo passo esatto

`prompts/02_ARCHITECTURE_DATABASE.md`.

## Note per il prossimo agente

- Non leggere né stampare i valori di `src/EmailStatus/.env` e `src/components/NavBar/firebaseConfig.js`.
- Non avviare `EmailServer.js` (userebbe credenziali reali).
- Non contattare Firebase.
- I prezzi nel codice legacy non sono dati validi.
