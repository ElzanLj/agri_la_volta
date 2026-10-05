# Stato corrente del progetto

> Questo file è la memoria operativa breve da aggiornare alla fine di ogni sessione sostanziale o prima di cambiare agente.

## Snapshot

- **Data aggiornamento:** 2026-10-05
- **Agente/strumento ultimo utilizzato:** Claude Code
- **Branch:** `main`
- **Commit di riferimento:** `16ced96` (Fase 2A); la Fase 2B è nel commit successivo
- **Fase corrente:** Fase 2B — pricing: **COMPLETATA** (prompt 05)
- **Prompt corrente:** `prompts/05_PRICING.md` (completato)
- **Stato complessivo:** motore tariffario configurabile, testato e integrato lato server; **nessun listino reale** inserito; 275 test PASS

## Obiettivo corrente

Fase 3 (area admin): `prompts/06_ADMIN.md`. Esporrà via HTTP, con autenticazione e CSRF, i servizi già pronti (`BookingService`, `PricingConfigService`) e testerà l'autorizzazione server-side delle azioni.

## Ultimo lavoro completato

- Migrazione `0003_pricing.sql`: tabella `pricing_rules`, colonne `apartments.max_children`/`max_pets`, `seasonal_rates.label_en`. Nessun dato inserito.
- `app/Domain`: `PriceCalculator` (puro), `PriceQuote`, `RatePeriod`, `ChargeRule`, `Money`, `DateRanges`; `PriceQuoter` ora restituisce sempre un `PriceQuote`.
- `app/Service`: `ConfiguredPriceQuoter`, `PricingConfigService` (validazioni + audit), integrazione in `BookingService::createRequest` (limiti, soggiorno minimo, totale e istantanea salvati, prezzi del browser ignorati).
- `app/Database/TransactionRunner` condiviso.
- 275 test (142 unit, 125 integrazione, 8 concorrenza): PASS in 2 esecuzioni; 3 prove di sensibilità sul calcolatore.
- `docs/MISSING_DATA.md` e `docs/DECISIONS.md` aggiornati (tassa di soggiorno, sconti, supplementi opzionali, listino, limiti per appartamento).

## Working tree / modifiche locali da preservare

- Nessuna modifica locale non committata a fine fase (il file `.env` della credenziale revocata è stato eliminato dall'utente).
- `vendor/` e `.phpunit.cache/` locali (ignorati da Git).
- `.env` locale con password DB casuali di sviluppo (ignorato).
- Copia del `docker-compose.yml` originale dell'utente nello scratchpad della sessione (conteneva solo una password di sviluppo).
- Password admin locale impostata dall'utente; volumi Docker vecchi puliti dall'utente.

## Test/comandi più recenti

`docker compose exec web composer test` → 275 test, 2048-2054 asserzioni, PASS (2 esecuzioni). Dettagli in `docs/TEST_REPORT.md` (sezione Fase 2B). Migrazione 0003 applicata anche al DB di sviluppo (0 tariffe, 0 regole).

## Blocchi aperti

1. Hosting di produzione non scelto (requisiti minimi in `docs/COMMANDS.md`).
2. Dati mancanti: vedi `docs/MISSING_DATA.md`.

## Decisioni da non reinterpretare

Vedi `docs/DECISIONS.md`: P1–P7 approvate; decisioni Fase 1, 1b, 2A e 2B registrate (locking, tetti tecnici, tariffa per appartamento/notte con voci additive, soggiorno minimo dalla data di arrivo, listino mancante = "prezzo da confermare").

## Prossimo passo esatto

`prompts/06_ADMIN.md`.

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
