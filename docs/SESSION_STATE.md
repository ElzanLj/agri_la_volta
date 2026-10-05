# Stato corrente del progetto

> Questo file è la memoria operativa breve da aggiornare alla fine di ogni sessione sostanziale o prima di cambiare agente.

## Snapshot

- **Data aggiornamento:** 2026-10-05
- **Agente/strumento ultimo utilizzato:** Claude Code
- **Branch:** `main`
- **Commit di riferimento:** `556a2be` (audit); il lavoro della Fase 1 non è ancora committato
- **Fase corrente:** Fase 1 — architettura, DB e fondamenta: **COMPLETATA** (in attesa di review/commit)
- **Prompt corrente:** `prompts/02_ARCHITECTURE_DATABASE.md` (completato)
- **Stato complessivo:** fondamenta PHP/MariaDB installabili e verificate in Docker

## Obiettivo corrente

Review dell'utente e commit della Fase 1. Poi `prompts/03_FOUNDATION_MIGRATION.md`.

## Ultimo lavoro completato

- `src/EmailStatus/.env` rimosso dal tracking (P7); `.gitignore` aggiornato.
- SPA React spostata in `legacy/` con `git mv` (P4); `node_modules` spostato lì (non tracciato).
- Nuovo scheletro PHP: `public/index.php` (front controller), `app/` (config, DB, router, view, sessione, CSRF, auth admin, rate limit, audit log, logger), `templates/`, `migrations/0001–0002`, `bin/migrate.php`, `bin/create-admin.php`, `storage/`.
- Ambiente Docker: `docker/php/Dockerfile` (pdo_mysql, rewrite, headers), `docker-compose.yml` riscritto senza segreti (valori da `.env`, volume `db_data`, porte su 127.0.0.1).
- `.env.example` nuovo (SPEC §35); quello Firebase è in `legacy/.env.example`.

## Working tree / modifiche locali da preservare

- `README_home.md` non tracciato: copia di `README.md` dell'utente. Non toccata.
- `.env` locale con password DB casuali di sviluppo (ignorato).
- Copia del `docker-compose.yml` originale dell'utente nello scratchpad della sessione (conteneva solo una password di sviluppo).
- Account admin di sviluppo `admin` creato con password casuale non salvata: rieseguire `docker compose exec web php bin/create-admin.php admin` per impostarne una propria.
- Il volume Docker anonimo del vecchio container MariaDB (DB `agriturismo` vuoto) non è stato eliminato.

## Test/comandi più recenti

Vedi `docs/TEST_REPORT.md` (sezione Fase 1) e `docs/COMMANDS.md`. Tutte le verifiche PASS tranne cookie `Secure` in HTTPS (NOT RUN). Due difetti trovati e corretti durante la verifica (redirect directory, `stream_isatty`).

## Blocchi aperti

1. Hosting di produzione non scelto (requisiti minimi in `docs/COMMANDS.md`).
2. Dati mancanti: vedi `docs/MISSING_DATA.md`.

## Decisioni da non reinterpretare

Vedi `docs/DECISIONS.md`: P1–P7 approvate; decisioni Fase 1 registrate.

## Prossimo passo esatto

Commit della Fase 1, poi `prompts/03_FOUNDATION_MIGRATION.md`.

## Note per il prossimo agente

- Non leggere né stampare `legacy/src/EmailStatus/.env` né i valori di `legacy/src/components/NavBar/firebaseConfig.js`.
- Non avviare `legacy/src/EmailStatus/EmailServer.js`. Non contattare Firebase.
- I prezzi nel codice legacy non sono dati validi.
- Migrazioni: un file nuovo per ogni modifica di schema, mai modificare `0001`/`0002` già applicate.
- Ogni nuovo form deve usare `csrf_field()` + `Csrf::isValid()`; output sempre con `e()`.
