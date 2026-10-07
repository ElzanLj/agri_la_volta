# Stato corrente del progetto

> Questo file è la memoria operativa breve da aggiornare alla fine di ogni sessione sostanziale o prima di cambiare agente.

## Snapshot

- **Data aggiornamento:** 2026-10-07
- **Agente/strumento ultimo utilizzato:** Claude Code
- **Branch:** `fase-14-state-sync` (strategia Git D1 = A: un ramo per fase, unito a mano dall'utente a `main`; `main` = `3068327`, nessun push)
- **Commit di riferimento:** `3068327` (roadmap 14–32); le modifiche del prompt 14 sono nel working tree, **non committate**
- **Fase corrente:** roadmap pre-release, **prompt 14** (sync dello stato, guardrail, audit dei contenuti): Parti A–E fatte, in attesa della revisione dell'utente; `AGENTS.md` (D2 = A) salvato dopo approvazione
- **Prompt corrente:** `prompts/14_STATE_SYNC_CONTENT_AUDIT.md`
- **Fase precedente completata:** preparazione alla pubblicazione senza deploy (vecchio prompt 14, oggi `31_RELEASE_PREP_NO_DEPLOY`): `docs/RELEASE_GUIDE.md`
- **Stato complessivo:** 791 test PASS; 23 criteri di accettazione PASS, 4 PARTIAL (18, 19, 20, 24), 0 FAIL. Guida di rilascio compilata (prerequisiti, autorizzazioni A1–A8, pacchetto, permessi, DNS da richiedere, prova di fumo, HSTS graduale, rollback) e simulazione di produzione eseguita in locale. **Non pubblicato; nessun servizio esterno contattato; tutte le prove manuali e l'installazione su hosting reale sono NOT RUN**

## Obiettivo corrente

Prompt 14 (solo documentazione, nessun codice). Risposte dell'utente (2026-10-07, "consigliate"): D1 = A, D2 = A. Baseline del 2026-10-07 prima di modificare i file: `docker compose exec web composer test` → 791 test, 10055 asserzioni, PASS (6 min 34 s); `composer test -- --order-by=random` (seme `1791397690`, PHP 8.2.34) → 791 test, 10054 asserzioni, PASS (6 min 33 s). La differenza di una asserzione fra i due ordini è da indagare nel prompt 15 (i test sono tutti verdi). Verifica di 12–13: documenti presenti; trovato e risolto nel `README.md` un conflitto di merge non risolto (`3f26b2b`). Dopo il 14: **prompt 15**.

Fino ad allora, per le attività del titolare, il passo successivo dipende dal titolare: contenuti e dati (`docs/MISSING_DATA.md`), autorizzazioni (`docs/RELEASE_GUIDE.md` §2), scelta dell'hosting, prove manuali (`docs/MANUAL_CHECKLIST.md`). Il dominio canonico è `https://www.agriturismolavolta.com`.

## Ultimo lavoro completato

- `docs/RELEASE_GUIDE.md`: prerequisiti, punti di autorizzazione A1–A8, pacchetto, `.env` di produzione, permessi, controllo preliminare, DNS web da richiedere (con TTL e **senza** toccare NS/MX/SPF/DKIM/DMARC), reindirizzamento non-www → www, prove prima del cambio DNS (attenzione a `APP_URL` e `Origin`), prova di fumo, sequenza con HSTS graduale, rollback, pagina di manutenzione, elenco di ciò che non è stato eseguito.
- `bin/check-production.php` e `app/Support/ProductionCheck.php`: controllo di sola lettura (nessuna connessione a SMTP o altri servizi, nessun segreto stampato); 13 test, 7 prove di sensibilità rilevate.
- Simulazione di produzione in locale (copia pulita, `--no-dev`, database usa-e-getta, SMTP verso una porta locale chiusa): controllo superato (27/27), pagine, intestazioni con HSTS, canonical sul dominio `www`, cookie `Secure`, file riservati non raggiungibili, richiesta salvata con la posta irraggiungibile. Tutto eliminato al termine.
- `docs/DELIVERY_CHECKLIST.md`, `README.md`, `docs/COMMANDS.md`, `docs/INSTALL_SHARED_HOSTING.md`, `.env.example` aggiornati.

## Azioni e funzioni: verificate e incomplete

- **Verificate:** vedi `docs/ACCEPTANCE_MATRIX.md`, `docs/FINAL_REVIEW.md`, `docs/TEST_REPORT.md` (sezione Preparazione al rilascio).
- **Incomplete/non verificabili ora:** prove manuali (tastiera, screen reader, mobile, desktop, zoom), installazione su hosting reale, DNS, consegna email reale, HTTPS reale, reindirizzamenti e pagina di manutenzione `.htaccess` (non provati), strumenti esterni (Lighthouse, axe, ZAP), foto reali, contenuti e dati del titolare.
## Working tree / modifiche locali da preservare

- Modifiche locali del prompt 14, tutte `.md` e non committate: `README.md` (conflitto di merge risolto), `GUIDA_UTILIZZO_AI.md`, `docs/{PLAN,DECISIONS,MISSING_DATA,TODO,SESSION_STATE,CONTENT_INVENTORY,FINAL_REVIEW,DELIVERY_CHECKLIST,PRE_RELEASE_ROADMAP,PROMPT_PACK,RELEASE_GUIDE,TEST_REPORT}.md`, `prompts/README.md`.
- `vendor/` e `.phpunit.cache/` locali, `storage/mail/` per `MAIL_TRANSPORT=log` (ignorati da Git). Il `.env` locale usa `MAIL_TRANSPORT=log`; i recapiti `PUBLIC_*` e `WHATSAPP_NUMBER` locali sono vuoti.
- `.env` locale con password DB casuali di sviluppo (ignorato).
- Password admin locale impostata dall'utente; volumi Docker vecchi puliti dall'utente.

## Test/comandi più recenti

`docker compose exec web composer test` → **791 test, 10049 asserzioni, PASS** (circa 7 minuti: 342 unit, 255 integrazione, 183 http, 11 concorrenza). Dettagli in `docs/TEST_REPORT.md` (sezione Preparazione al rilascio). Ultima migrazione: `0005`. Nessuna nuova migrazione.

## Blocchi aperti

1. Hosting di produzione non scelto (requisiti minimi in `docs/COMMANDS.md`; per le email servono la cartella `vendor/` caricata e, se possibile, PHP-FPM e cron; con proxy inverso verificare che `REMOTE_ADDR` sia l'IP del cliente per il rate limit).
2. **Credenziali SMTP reali non fornite**: finché mancano, le email restano in coda e la consegna reale non è verificata.
3. Dati mancanti: vedi `docs/MISSING_DATA.md` (listino, foto, testi, recapiti, WhatsApp, `APP_SECRET`).

## Decisioni da non reinterpretare

Vedi `docs/DECISIONS.md`: P1–P7 approvate; decisioni Fase 1, 1b, 2A, 2B, 3, 4, 5, 6, 7 e review finale registrate (locking, tetti tecnici, tariffa per appartamento/notte, soggiorno minimo dalla data di arrivo, listino mancante = "prezzo da confermare", outbox email, token firmato senza sessione, passi separati, segnaposto per le foto, dati solo dal DB).

## Prossimo passo esatto

1. L'utente rivede le modifiche del prompt 14, approva il testo per `AGENTS.md` (D2) e dice esplicitamente in chat se committare (nessun commit, push o deploy senza una sua frase).
2. Poi `prompts/15_EXISTING_FIXES.md` (correzioni dell'esistente), in un nuovo ramo `fase-15-existing-fixes`; le sue domande (D1–D8) vanno prima all'utente.
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
