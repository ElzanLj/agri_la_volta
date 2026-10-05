# Stato corrente del progetto

> Questo file è la memoria operativa breve da aggiornare alla fine di ogni sessione sostanziale o prima di cambiare agente.

## Snapshot

- **Data aggiornamento:** 2026-10-05
- **Agente/strumento ultimo utilizzato:** Claude Code
- **Branch:** `main`
- **Commit di riferimento:** `e76dcea` (Fase 2B); la Fase 3 è nel commit successivo
- **Fase corrente:** Fase 3 — area amministrativa e sicurezza: **COMPLETATA** (prompt 06)
- **Prompt corrente:** `prompts/06_ADMIN.md` (completato)
- **Stato complessivo:** `/admin` operativa (richieste, prenotazioni, blocchi, calendario, appartamenti, listino, storico, CSV), protetta da guardie di prefisso; 432 test PASS

## Obiettivo corrente

Fase 4 (email e WhatsApp): `prompts/07_EMAIL_WHATSAPP.md`. Collegherà l'invio SMTP agli eventi già presenti (nuova richiesta, conferma, rifiuto) e la bozza di cancellazione; **i dati SMTP non sono ancora stati forniti** (vedi `docs/MISSING_DATA.md`).

## Ultimo lavoro completato

- Livello HTTP: router con guardie per prefisso (default-deny), middleware `PrivateResponse`/`RequireAdmin`/`VerifyCsrf`, sessione legata all'hash password, controllo Origin, nessuna sessione per il traffico anonimo.
- Controller admin sottili in `app/Http/Controllers/Admin/`, repository di sola lettura `AdminQueryRepository`, `ApartmentAdminService`, `ListFilters`, `Csv`, `Money::parse/plain`; 20 viste HTML senza JavaScript in `templates/admin/`.
- Suite di sicurezza via HTTP reale (server PHP built-in sul DB di test): matrice non autenticato, matrice CSRF, sessioni, azioni end to end, CSV, escaping, SQL injection nei filtri.
- 9 prove di sensibilità sulla sicurezza (tutte rilevate); 5 difetti trovati e corretti (vedi `docs/TEST_REPORT.md`).
- 432 test (208 unit, 136 integrazione, 80 http, 8 concorrenza): PASS in 2 esecuzioni consecutive.

## Azioni admin: verificate e incomplete

- **Verificate end to end:** login/logout, elenchi con filtri e paginazione, dettaglio richiesta, conferma, rifiuto, prenotazione manuale, blocchi (crea/rimuovi), cancellazione, calendario, modifica appartamenti, listino (tariffe e regole), storico modifiche, export CSV, dashboard.
- **Incomplete/rinviate:** invio email su conferma/rifiuto e bozza di cancellazione (Fase 4); link WhatsApp (Fase 4); foto e servizi degli appartamenti; cambio password da interfaccia (resta da riga di comando); test manuali nel browser (mobile/tastiera) NOT RUN.

## Working tree / modifiche locali da preservare

- Nessuna modifica locale non committata a fine fase (il file `.env` della credenziale revocata è stato eliminato dall'utente).
- `vendor/` e `.phpunit.cache/` locali (ignorati da Git).
- `.env` locale con password DB casuali di sviluppo (ignorato).
- Copia del `docker-compose.yml` originale dell'utente nello scratchpad della sessione (conteneva solo una password di sviluppo).
- Password admin locale impostata dall'utente; volumi Docker vecchi puliti dall'utente.

## Test/comandi più recenti

`docker compose exec web composer test` → 432 test, 3474-3480 asserzioni, PASS (2 esecuzioni, circa 3 minuti). Dettagli in `docs/TEST_REPORT.md` (sezione Fase 3).

## Blocchi aperti

1. Hosting di produzione non scelto (requisiti minimi in `docs/COMMANDS.md`).
2. Dati mancanti: vedi `docs/MISSING_DATA.md`.

## Decisioni da non reinterpretare

Vedi `docs/DECISIONS.md`: P1–P7 approvate; decisioni Fase 1, 1b, 2A, 2B e 3 registrate (locking, tetti tecnici, tariffa per appartamento/notte con voci additive, soggiorno minimo dalla data di arrivo, listino mancante = "prezzo da confermare").

## Prossimo passo esatto

`prompts/07_EMAIL_WHATSAPP.md` (richiede i parametri SMTP reali solo per l'invio vero; si sviluppa con un trasporto di prova).

## Note per il prossimo agente

- Non leggere né stampare `legacy/src/EmailStatus/.env` (credenziale revocata, file locale non tracciato). Non contattare Firebase.
- Il legacy è solo riferimento per contenuti/stile (Fase 5); non reintrodurre Firebase, login o pagamenti.
- I prezzi nel codice legacy non sono dati validi.
- Migrazioni: un file nuovo per ogni modifica di schema, mai modificare `0001`/`0002` già applicate.
- Ogni nuovo form deve usare `csrf_field()` + `Csrf::isValid()`; output sempre con `e()`.
- Ogni scrittura che cambia l'occupazione di un appartamento deve passare da `BookingService`: non scrivere mai direttamente in `bookings` o `availability_blocks`. L'invariante di non-sovrapposizione dipende da questo.
- Eseguire `composer test` (almeno le suite `unit` e `integration`) prima di ogni modifica a `app/Service`, `app/Repository` o `app/Domain`; la suite `concurrency` quando si tocca il locking.
- Non inserire mai prezzi reali o inventati nel codice, nelle migrazioni o nei test: il listino lo inserisce il titolare dall'admin. I test usano solo `tests/Support/PricingFixtures.php` (etichette `[TEST]`).
- Il prezzo è sempre calcolato da `PriceQuoter` lato server; mai fidarsi di importi ricevuti dal browser.
- Ogni nuova rotta `/admin/...` va registrata in `app/routes_admin.php`: eredita le guardie. Le modifiche sono solo POST con `csrf_field()`; i GET non devono mai scrivere (un test lo verifica). Output sempre con `e()`.
- Quando si aggiungono pagine admin, aggiungere i test in `tests/Http/` (la matrice non autenticato/CSRF le copre automaticamente se la rotta è registrata).
- Il database di test viene ripristinato da `DatabaseTestCase::resetDatabase()`: se si aggiungono tabelle o campi modificabili dall'admin, aggiornarlo.
