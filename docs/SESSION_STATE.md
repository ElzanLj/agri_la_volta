# Stato corrente del progetto

> Questo file è la memoria operativa breve da aggiornare alla fine di ogni sessione sostanziale o prima di cambiare agente.

## Snapshot

- **Data aggiornamento:** 2026-10-05
- **Agente/strumento ultimo utilizzato:** Claude Code
- **Branch:** `main`
- **Commit di riferimento:** `d8f3865` (Fase 1b); la Fase 2A è nel commit successivo
- **Fase corrente:** Fase 2A — booking e disponibilità: **COMPLETATA** (prompt 04)
- **Prompt corrente:** `prompts/04_BOOKING_AVAILABILITY.md` (completato)
- **Stato complessivo:** core di richieste/prenotazioni/blocchi/cancellazioni implementato come servizi PHP, 177 test PASS

## Obiettivo corrente

Fase 2B (pricing): `prompts/05_PRICING.md`. Poi Fase 3 (admin) che esporrà i servizi via HTTP.

## Ultimo lavoro completato

- `app/Domain` (`StayDates`, `GuestCounts`, eccezioni, `PriceQuoter` + implementazione nulla), `app/Repository` (SQL), `app/Service` (`AvailabilityService`, `BookingService`).
- Locking per appartamento (`SELECT ... FOR UPDATE`, READ COMMITTED) come da P6; descritto in `docs/DECISIONS.md` e nel docblock di `BookingService`.
- Composer + PHPUnit nell'immagine Docker; database di test `agriturismo_test`.
- 177 test (85 unit, 84 integrazione, 8 concorrenza con processi reali): PASS in due esecuzioni consecutive. Prova con lock disattivato: i test falliscono (7 conferme su 8, 19 sovrapposizioni), poi codice ripristinato.

## Working tree / modifiche locali da preservare

- Nessuna modifica locale non committata a fine fase (il file `.env` della credenziale revocata è stato eliminato dall'utente).
- `vendor/` e `.phpunit.cache/` locali (ignorati da Git).
- `.env` locale con password DB casuali di sviluppo (ignorato).
- Copia del `docker-compose.yml` originale dell'utente nello scratchpad della sessione (conteneva solo una password di sviluppo).
- Password admin locale impostata dall'utente; volumi Docker vecchi puliti dall'utente.

## Test/comandi più recenti

`docker compose exec web composer test` → 177 test, 1748-1751 asserzioni, PASS (2 esecuzioni). Dettagli e limiti in `docs/TEST_REPORT.md` (sezione Fase 2A). Autorizzazione admin delle azioni: non applicabile finché non esiste l'interfaccia (Fase 3).

## Blocchi aperti

1. Hosting di produzione non scelto (requisiti minimi in `docs/COMMANDS.md`).
2. Dati mancanti: vedi `docs/MISSING_DATA.md`.

## Decisioni da non reinterpretare

Vedi `docs/DECISIONS.md`: P1–P7 approvate; decisioni Fase 1, 1b e 2A registrate (tetti tecnici, blocchi rifiutati su prenotazioni, richieste su date occupate rifiutate, locking).

## Prossimo passo esatto

`prompts/05_PRICING.md`.

## Note per il prossimo agente

- Non leggere né stampare `legacy/src/EmailStatus/.env` (credenziale revocata, file locale non tracciato). Non contattare Firebase.
- Il legacy è solo riferimento per contenuti/stile (Fase 5); non reintrodurre Firebase, login o pagamenti.
- I prezzi nel codice legacy non sono dati validi.
- Migrazioni: un file nuovo per ogni modifica di schema, mai modificare `0001`/`0002` già applicate.
- Ogni nuovo form deve usare `csrf_field()` + `Csrf::isValid()`; output sempre con `e()`.
- Ogni scrittura che cambia l'occupazione di un appartamento deve passare da `BookingService`: non scrivere mai direttamente in `bookings` o `availability_blocks`. L'invariante di non-sovrapposizione dipende da questo.
- Eseguire `composer test` (almeno le suite `unit` e `integration`) prima di ogni modifica a `app/Service`, `app/Repository` o `app/Domain`; la suite `concurrency` quando si tocca il locking.
