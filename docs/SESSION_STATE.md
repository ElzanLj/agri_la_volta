# Stato corrente del progetto

> Questo file è la memoria operativa breve da aggiornare alla fine di ogni sessione sostanziale o prima di cambiare agente.

## Snapshot

- **Data aggiornamento:** 2026-10-05
- **Agente/strumento ultimo utilizzato:** Claude Code
- **Branch:** `main`
- **Commit di riferimento:** `3a6762f` (Fase 1: fondamenta PHP/DB); le modifiche della Fase 1b non sono ancora committate
- **Fase corrente:** Fase 1 — fondamenta: **COMPLETATA** (prompt 02 e 03)
- **Prompt corrente:** `prompts/03_FOUNDATION_MIGRATION.md` (completato)
- **Stato complessivo:** baseline PHP/MariaDB avviabile; codice legacy ripulito da pagamenti, Firebase, login e server email

## Obiettivo corrente

Review/commit della Fase 1b, poi Fase 2A: `prompts/04_BOOKING_AVAILABILITY.md`.

## Ultimo lavoro completato

- Rimossi da `legacy/` pagamenti, `AuthPopup`, `BookingSystem`, Firebase, `EmailServer.js`, codice morto e le dipendenze `firebase`, `nodemailer`, `dotenv`, `all`, `react-datepicker`, `react-icons`.
- `App.jsx`, `NavBar.jsx`, `ContactUs.jsx` del legacy adattati (niente login, niente Prenota, niente modulo contatti).
- Baseline legacy: build PASS (JS 184 kB), lint 43 errori (era 90), audit 2 vulnerabilità moderate (erano 14).
- Baseline PHP riverificata: migrazioni, pagine, DB, lint PHP.
- `README_home.md` (duplicato) eliminato su richiesta dell'utente.

## Working tree / modifiche locali da preservare

- Modifiche non committate della Fase 1b (solo `legacy/` e `docs/`).
- File locale non tracciato `legacy/src/EmailStatus/.env` con la credenziale Gmail già revocata: non letto, non cancellato (può eliminarlo il titolare).
- `.env` locale con password DB casuali di sviluppo (ignorato).
- Copia del `docker-compose.yml` originale dell'utente nello scratchpad della sessione (conteneva solo una password di sviluppo).
- Password admin locale impostata dall'utente; volumi Docker vecchi puliti dall'utente.

## Test/comandi più recenti

Vedi `docs/TEST_REPORT.md` (sezione Fase 1) e `docs/COMMANDS.md`. Tutte le verifiche PASS tranne cookie `Secure` in HTTPS (NOT RUN). Due difetti trovati e corretti durante la verifica (redirect directory, `stream_isatty`).

## Blocchi aperti

1. Hosting di produzione non scelto (requisiti minimi in `docs/COMMANDS.md`).
2. Dati mancanti: vedi `docs/MISSING_DATA.md`.

## Decisioni da non reinterpretare

Vedi `docs/DECISIONS.md`: P1–P7 approvate; decisioni Fase 1 registrate.

## Prossimo passo esatto

`prompts/04_BOOKING_AVAILABILITY.md` (dopo commit della Fase 1b).

## Note per il prossimo agente

- Non leggere né stampare `legacy/src/EmailStatus/.env` (credenziale revocata, file locale non tracciato). Non contattare Firebase.
- Il legacy è solo riferimento per contenuti/stile (Fase 5); non reintrodurre Firebase, login o pagamenti.
- I prezzi nel codice legacy non sono dati validi.
- Migrazioni: un file nuovo per ogni modifica di schema, mai modificare `0001`/`0002` già applicate.
- Ogni nuovo form deve usare `csrf_field()` + `Csrf::isValid()`; output sempre con `e()`.
