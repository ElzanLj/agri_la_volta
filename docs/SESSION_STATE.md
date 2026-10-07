# Stato corrente del progetto

> Questo file è la memoria operativa breve da aggiornare alla fine di ogni sessione sostanziale o prima di cambiare agente.

## Snapshot

- **Data aggiornamento:** 2026-10-07
- **Agente/strumento ultimo utilizzato:** Claude Code
- **Branch:** `fase-15-existing-fixes` (strategia Git D1 = A: un ramo per fase, unito a mano dall'utente a `main`; partito da `fase-14-state-sync`, commit `c8aec3d`). Nessun push.
- **Commit di riferimento:** `c8aec3d` (prompt 14). Le modifiche del prompt 15 sono nel working tree, **non committate** (serve una frase esplicita dell'utente in chat)
- **Fase corrente:** roadmap pre-release, **prompt 15** (correzioni dell'esistente): **COMPLETATO nel working tree**, in attesa della revisione dell'utente e del suo "Prova tu" (sotto)
- **Prompt corrente:** `prompts/15_EXISTING_FIXES.md` (completato); prossimo: `prompts/16_CONTENT_MODEL_DESIGN.md`
- **Stato complessivo:** 905 test PASS (ordine normale e casuale); 23 criteri di accettazione PASS, 4 PARTIAL (18, 19, 20, 24), 0 FAIL (matrice non rivista dopo il prompt 15: se ne occupa il prompt 30). **Non pubblicato; nessun servizio esterno contattato; tutte le prove manuali e l'installazione su hosting reale sono NOT RUN**

## Obiettivo corrente

Concluso il prompt 15. Risposte dell'utente (2026-10-07): "Consigliate" per D1–D8; D1 = A confermata, D4 = A ("scelta prudente"). Cosa è stato fatto, con le prove in `docs/TEST_REPORT.md` e i finding in `docs/SECURITY_REVIEW.md` ("Riesame del 2026-10-07"):

- **Storico senza testo libero** (`reason_present`), `bin/privacy.php audit-clean`, avviso sotto i campi motivo e note, chiave di invio azzerata con l'ospite;
- **Rifiuto con conferma**: pagina con riepilogo e anteprima dell'email; POST senza `conferma=1` non fa nulla;
- **Doppio invio**: `submission_key` (migrazione `0006`), `createRequest` idempotente anche con invii simultanei;
- **Rate limit** "registra poi conta" (`RateLimiter::attempt`), login falliti nelle ultime 24 ore in dashboard; solo `REMOTE_ADDR` (D4 = A);
- **Host canonico** (301 verso l'host di `APP_URL`), **migrazioni** con lock, parser corretto, solo `NNNN_nome.sql`, `migrations/CHECKSUMS`, pre-controllo dei dati, **vincoli `CHECK`** (migrazione `0007`);
- **Coerenza dei dati** (`ConsistencyChecker`, `bin/check-consistency.php`), `APP_ENV` con valori ammessi, log del database senza messaggi, LiteSpeed e `ignore_user_abort`, riferimento "ricevuta" solo se reale, caratteri invisibili, `.htaccess` di negazione;
- **Guardiani sempre attivi** (checksum, escape nei template, funzioni pericolose, indirizzi esterni, matrice delle rotte admin), tutti con prova di sensibilità (12 su 12 rilevate).

Per le attività del titolare, il passo successivo dipende da lui: contenuti e dati (`docs/MISSING_DATA.md`), autorizzazioni (`docs/RELEASE_GUIDE.md` §2), scelta dell'hosting, prove manuali (`docs/MANUAL_CHECKLIST.md`). Il dominio canonico è `https://www.agriturismolavolta.com`.

## Ultimo lavoro completato

- Prompt 14 (commit `c8aec3d`): roadmap 14–32 sincronizzata, guardrail in `AGENTS.md`, finding della review in `docs/TODO.md`, inventario dei contenuti verificato, conflitto di merge risolto nel `README.md`.
- Prompt 15 (working tree): vedi "Obiettivo corrente". File nuovi principali: `app/Http/CanonicalHost.php`, `app/Http/InvisibleChars.php`, `app/Service/ConsistencyChecker.php`, `app/Support/AuditCleaner.php`, `app/Database/Migration*.php`, `bin/check-consistency.php`, `bin/migration-checksums.php`, `migrations/0006`, `0007`, `CHECKSUMS`, `templates/admin/requests/reject.php`, dieci `.htaccess` di negazione.
- Preparazione al rilascio (vecchio prompt 14, oggi `31_RELEASE_PREP_NO_DEPLOY`): `docs/RELEASE_GUIDE.md` e `bin/check-production.php`, da aggiornare nel prompt 31.

## Azioni e funzioni: verificate e incomplete

- **Verificate:** vedi `docs/ACCEPTANCE_MATRIX.md`, `docs/FINAL_REVIEW.md`, `docs/TEST_REPORT.md` (sezione Prompt 15).
- **Incomplete/non verificabili ora:** prove manuali (tastiera, screen reader, mobile, desktop, zoom), installazione su hosting reale, DNS, consegna email reale, HTTPS reale, comportamento dei `.htaccess` (redirect, pagina di manutenzione, negazione) su server che li limitano, MySQL 8, PHP-FPM/LiteSpeed reali, strumenti esterni (Lighthouse, axe, ZAP), foto reali, contenuti e dati del titolare.

## Working tree / modifiche locali da preservare

- Modifiche del prompt 15 non committate: codice in `app/`, `bin/`, `public/`, `templates/`, `migrations/` (0006, 0007, CHECKSUMS), `tests/`, dieci `.htaccess`, documenti in `docs/` e `README.md`.
- `vendor/` e `.phpunit.cache/` locali, `storage/mail/` per `MAIL_TRANSPORT=log` (ignorati da Git). Il `.env` locale usa `MAIL_TRANSPORT=log`; i recapiti `PUBLIC_*` e `WHATSAPP_NUMBER` locali sono vuoti.
- `.env` locale con password DB casuali di sviluppo (ignorato). Le migrazioni `0006` e `0007` sono già applicate al database di sviluppo e a quello di test.
- Password admin locale impostata dall'utente; volumi Docker vecchi puliti dall'utente. I container Docker sono in esecuzione (avviati in questa sessione).

## Test/comandi più recenti

`docker compose exec web composer test` → **905 test, 10917 asserzioni, PASS** (7 min 10 s: 397 unit, 291 integrazione, 203 http, 14 concorrenza); con `--order-by=random` (seme `1791401578`): 905 test, PASS. Dettagli in `docs/TEST_REPORT.md` (sezione Prompt 15). Ultima migrazione: `0007_data_constraints`.

## Blocchi aperti

1. Hosting di produzione non scelto (requisiti minimi in `docs/COMMANDS.md`; per le email servono la cartella `vendor/` caricata e, se possibile, PHP-FPM e cron; con proxy inverso verificare che `REMOTE_ADDR` sia l'IP del cliente per il rate limit: scelta D4 = A, `TRUSTED_PROXIES` non introdotta).
2. **Credenziali SMTP reali non fornite**: finché mancano, le email restano in coda e la consegna reale non è verificata.
3. Dati mancanti: vedi `docs/MISSING_DATA.md` (listino, foto, testi, recapiti, WhatsApp, `APP_SECRET`).
4. Decisione P4 (`legacy/`): aperta, da chiudere nel prompt 28 (i file tracciati sono già stati rimossi il 2026-10-06). Nel repository esiste ancora una cartella locale `legacy/` non tracciata, che non è stata aperta.
5. Finding della review con parte residua in altre fasi: `docs/TODO.md`, sezione "Finding della review 2026-10-07" (voci `[~]`).

## Decisioni da non reinterpretare

Vedi `docs/DECISIONS.md`: P1–P7 approvate; decisioni Fase 1, 1b, 2A, 2B, 3, 4, 5, 6, 7 e review finale registrate (locking, tetti tecnici, tariffa per appartamento/notte, soggiorno minimo dalla data di arrivo, listino mancante = "prezzo da confermare", outbox email, token firmato senza sessione, passi separati, segnaposto per le foto, dati solo dal DB).

## Prossimo passo esatto

1. L'utente rivede le modifiche del prompt 15 ed esegue il "Prova tu" (riepilogo finale di questa sessione); dice esplicitamente in chat se committare (nessun commit, push o deploy senza una sua frase).
2. Poi `prompts/16_CONTENT_MODEL_DESIGN.md` (progetto della gestione contenuti), in un nuovo ramo `fase-16-content-model` (partendo da `fase-15-existing-fixes`); le sue domande D1–D14 vanno prima all'utente.
3. Le attività del titolare (contenuti, autorizzazioni, hosting) restano indicate in `docs/TODO.md` e `docs/RELEASE_GUIDE.md`.

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
- **Migrazioni (prompt 15):** per ogni modifica di schema un file nuovo `NNNN_nome.sql` col numero successivo e poi `php bin/migration-checksums.php` (aggiunge la riga a `migrations/CHECKSUMS`; un test fallisce se manca o se un file rilasciato cambia). Mai modificare `0001`–`0007`. Un'istruzione termina con `;` fuori da testo e commenti. Se una migrazione deve controllare i dati prima, si aggiunge in `MigrationPreconditions`.
- **Testi inseriti dall'utente:** passano da `Request::input()` / `query()`, che tolgono i caratteri invisibili; per le password si usa `rawInput()`.
- **Rate limit:** `RateLimiter::attempt()` registra e poi conta; non tornare a "conta poi registra". Il client è sempre `REMOTE_ADDR`.
- **Storico:** mai testo libero personale (motivi, note): solo `*_present`. Una nuova rotta admin va aggiunta anche a `AdminAccessTest::REVIEWED_ADMIN_ROUTES`; un nuovo `<?=` nei template deve usare `e()` (o essere rivisto in `ArchitectureGuardTest`).
- **Rifiuto di una richiesta:** passa dalla pagina di conferma; i test che rifiutano con un POST diretto devono inviare `conferma=1`.
- **Test HTTP con `APP_URL` diverso dall'indirizzo del server di prova:** le GET verso un altro host sono reindirizzate (301); inviare l'header `Host` del sito.
- **Token del modulo pubblico:** contiene l'ora in secondi; due pagine caricate nello stesso secondo hanno lo stesso token (e quindi, con gli stessi dati, sono lo stesso invio).
