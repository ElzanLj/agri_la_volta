# Stato corrente del progetto

> Questo file è la memoria operativa breve da aggiornare alla fine di ogni sessione sostanziale o prima di cambiare agente.

## Snapshot

- **Data aggiornamento:** 2026-10-06
- **Agente/strumento ultimo utilizzato:** Claude Code
- **Branch:** `main`
- **Commit di riferimento:** `95229a8` (Fase 7); la Fase 8 è nel commit successivo
- **Fase corrente:** Fase 8 — test completo, regressioni e bugfix: **COMPLETATA per la parte automatizzabile** (prompt 11)
- **Prompt corrente:** `prompts/11_TEST_REGRESSION.md` (completato)
- **Stato complessivo:** 763 test PASS in due esecuzioni complete (ordine predefinito e casuale) più 3 ripetizioni della concorrenza; nessun test instabile, nessun bug nuovo nel codice di produzione, nessun FAIL. Criteri di accettazione: 22 PASS, 5 PARTIAL (18, 19, 20, 24, 26), 0 FAIL (`docs/ACCEPTANCE_MATRIX.md`). **Tutte le prove manuali sono NOT RUN** (`docs/MANUAL_CHECKLIST.md`)

## Obiettivo corrente

Fase 9 (documentazione): `prompts/12_DOCUMENTATION.md`, a partire dal `README.md` da riscrivere (scelta dell'utente: non toccato prima).

## Ultimo lavoro completato

- `tests/Http/EndToEndTest.php` (3 test): percorso completo visitatore → conferma admin → date non più offerte → CSV/storico/calendario → cancellazione → date di nuovo offerte; due visitatori sulle stesse date; un visitatore non può decidere.
- `tests/Unit/ScopeTest.php` (9 test): nessun pagamento né dato carta, nessun account ospite, solo i controller admin decidono, il flusso pubblico crea solo richieste `pending`. 7 prove di sensibilità tutte rilevate.
- `docs/ACCEPTANCE_MATRIX.md` compilata con evidenza per ognuno dei 27 criteri; `docs/MANUAL_CHECKLIST.md` creata (tutte le voci NOT RUN, a cura del titolare); `docs/TEST_REPORT.md` con le sezioni PASS / FAIL / NOT RUN / rischi residui.

## Azioni e funzioni: verificate e incomplete

- **Verificate:** vedi `docs/ACCEPTANCE_MATRIX.md` (evidenza per riga) e `docs/TEST_REPORT.md`.
- **Incomplete/non verificabili ora:** prove manuali (tastiera, screen reader, mobile, desktop, zoom), consegna email reale, HTTPS/hosting reali, strumenti esterni (Lighthouse, axe, ZAP), foto reali e ottimizzazione immagini, README, contenuti e dati del titolare.
## Working tree / modifiche locali da preservare

- Nessuna modifica locale non committata a fine fase.
- `vendor/` e `.phpunit.cache/` locali, `storage/mail/` per `MAIL_TRANSPORT=log` (ignorati da Git). Il `.env` locale usa `MAIL_TRANSPORT=log`; i recapiti `PUBLIC_*` e `WHATSAPP_NUMBER` locali sono vuoti.
- `.env` locale con password DB casuali di sviluppo (ignorato).
- Password admin locale impostata dall'utente; volumi Docker vecchi puliti dall'utente.

## Test/comandi più recenti

`docker compose exec web composer test` → **763 test, 9809-9810 asserzioni, PASS** (circa 7 minuti: 337 unit, 237 integrazione, 178 http, 11 concorrenza; eseguita in ordine predefinito e casuale). Dettagli in `docs/TEST_REPORT.md` (sezione Fase 8). Nessuna nuova migrazione.

## Blocchi aperti

1. Hosting di produzione non scelto (requisiti minimi in `docs/COMMANDS.md`; per le email servono la cartella `vendor/` caricata e, se possibile, PHP-FPM e cron; con proxy inverso verificare che `REMOTE_ADDR` sia l'IP del cliente per il rate limit).
2. **Credenziali SMTP reali non fornite**: finché mancano, le email restano in coda e la consegna reale non è verificata.
3. Dati mancanti: vedi `docs/MISSING_DATA.md` (listino, foto, testi, recapiti, WhatsApp, `APP_SECRET`).

## Decisioni da non reinterpretare

Vedi `docs/DECISIONS.md`: P1–P7 approvate; decisioni Fase 1, 1b, 2A, 2B, 3, 4, 5, 6 e 7 registrate (locking, tetti tecnici, tariffa per appartamento/notte, soggiorno minimo dalla data di arrivo, listino mancante = "prezzo da confermare", outbox email, token firmato senza sessione, passi separati, segnaposto per le foto, dati solo dal DB).

## Prossimo passo esatto

`prompts/12_DOCUMENTATION.md` (vedi il promemoria in `docs/TODO.md`).

## Note per il prossimo agente

- Non leggere né stampare `legacy/src/EmailStatus/.env` (credenziale revocata, file locale non tracciato). Non contattare Firebase.
- Il legacy è solo riferimento per contenuti/stile; **nessun testo o foto legacy è stato riusato** in Fase 5 senza verifica. Non reintrodurre Firebase, login o pagamenti. I prezzi nel codice legacy non sono dati validi.
- Migrazioni: un file nuovo per ogni modifica di schema, mai modificare `0001`/`0002` già applicate.
- Admin: ogni nuovo form usa `csrf_field()` + `Csrf::isValid()`; ogni nuova rotta `/admin/...` va in `app/routes_admin.php` (eredita le guardie); le modifiche sono solo POST; i GET non scrivono.
- **Sito pubblico:** niente sessioni né cookie per i visitatori (la cookie policy lo afferma): i moduli usano `FormToken`, non `Csrf`. Ogni nuova pagina si aggiunge in `Routes::PATHS` (IT + EN) e in `app/routes.php`; i testi in **entrambi** `content/it.php` e `content/en.php` (un test verifica chiavi e segnaposto); output sempre con `e()`; nessun dato inventato: se un campo è vuoto si omette.
- Ogni scrittura che cambia l'occupazione deve passare da `BookingService`: mai scrivere direttamente in `bookings` o `availability_blocks`. Il flusso pubblico usa `previewRequest` per mostrare e `createRequest` per salvare: non duplicare mai la logica di prezzo o validazione nei controller.
- Eseguire `composer test` (almeno `unit` e `integration`) prima di ogni modifica a `app/Service`, `app/Repository` o `app/Domain`; la suite `concurrency` quando si tocca il locking.
- Non inserire mai prezzi reali o inventati nel codice, nelle migrazioni o nei test: il listino lo inserisce il titolare dall'admin. I test usano `tests/Support/PricingFixtures.php` (etichette `[TEST]`) e le date dei test pubblici sono relative a oggi (`PublicSiteTestCase::stay`).
- Il prezzo è sempre calcolato lato server; mai fidarsi di importi, stato o lingua ricevuti dal browser.
- Il database di test viene ripristinato da `DatabaseTestCase::resetDatabase()`: se si aggiungono tabelle o campi modificabili dall'admin, aggiornarlo.
- Lo stato (richiesta, conferma, prenotazione) non deve mai dipendere da SMTP né dalla coda: l'email si accoda con `queueMail` dentro la transazione (errori contenuti) e si invia solo dall'hook dopo il commit. Mai inviare dentro una transazione. Nuove email: tipo in migrazione (CHECK di `email_outbox.type`), in `MessageBuilder` e un test.
- Non salvare mai testi di errore del server o indirizzi nei log o in `error_message`: passare da `ErrorSanitizer`. Per provare l'SMTP usare `FakeSmtpServer`; i test HTTP usano `MAIL_TRANSPORT=log` su cartella temporanea e un `APP_SECRET` di prova (`TestServer::APP_SECRET`).
