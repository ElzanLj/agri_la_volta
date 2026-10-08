# Stato corrente del progetto

> Questo file è la memoria operativa breve da aggiornare alla fine di ogni sessione sostanziale o prima di cambiare agente.

## Snapshot

- **Data aggiornamento:** 2026-10-08
- **Agente/strumento ultimo utilizzato:** Claude Code
- **Branch:** `fase-17-admin-account` (strategia Git D1 = A: un ramo per fase, unito a mano dall'utente a `main`; partito da `fase-16-content-model`). Nessun push.
- **Commit di riferimento:** `48477d1` (prompt 16); prompt 15 `c424b28`, prompt 14 `c8aec3d`. Le modifiche del prompt 17 sono nel working tree, **non committate** (serve una frase esplicita dell'utente in chat)
- **Fase corrente:** roadmap pre-release, **prompt 17** (account admin gestibile senza SSH): **COMPLETATO nel working tree**, in attesa della revisione dell'utente e del suo «Prova tu»
- **Prompt corrente:** `prompts/17_ADMIN_ACCOUNT_NO_SSH.md` (completato); prossimo: `prompts/18_SITE_SETTINGS.md`
- **Stato complessivo:** 1005 test PASS (ordine normale e casuale); 23 criteri di accettazione PASS, 4 PARTIAL (18, 19, 20, 24), 0 FAIL (matrice non rivista dopo i prompt 15–17: se ne occupa il prompt 30). **Non pubblicato; nessun servizio esterno contattato; tutte le prove manuali e l'installazione su hosting reale sono NOT RUN**

## Obiettivo corrente

Concluso il prompt 17. Risposte dell'utente (2026-10-07): «consigliato» per D1–D6, quindi D1 A, D2 A, D3 A, D4 A, D5 A, D6 B. Cosa è stato fatto, con prove in `docs/TEST_REPORT.md` e controlli in `docs/SECURITY_REVIEW.md` («Account e riautenticazione»):

- **Pagina Account** (`/admin/account`): cambio password (password attuale + nuova due volte), «Esci da tutti i dispositivi» (alza `admin.session_version`, migrazione **`0008`**), accesso precedente e tentativi falliti delle ultime 24 ore (D6 = B);
- **`PasswordPolicy`**: minimo 12, password comuni (`app/Security/common-passwords.txt`, 114 parole), nome utente, nome dell'agriturismo, ripetitive, uguale all'attuale; stesse regole nel comando;
- **`ReauthGuard`** + pagina `/admin/conferma-password` + helper `BasePage::needsReauth()`: conferma valida 5 minuti, solo nella sessione corrente, con limite di tentativi proprio; la useranno i prompt 24, 26 e 27 (qui non è applicata ad azioni di altre fasi);
- **Senza SSH**: `php bin/create-admin.php nome --print-sql > admin.sql` e import in phpMyAdmin (crea o recupera, un solo account, nessun accesso al database); **nessun installer web**;
- guardiani aggiornati (`ScopeTest`, `AdminAccessTest::REVIEWED_ADMIN_ROUTES`), `THREAT_MODEL` con le righe A5–A7, 13 prove di sensibilità rilevate.

Per le attività del titolare, il passo successivo dipende da lui: contenuti e dati (`docs/MISSING_DATA.md`), autorizzazioni (`docs/RELEASE_GUIDE.md` §2), scelta dell'hosting, prove manuali (`docs/MANUAL_CHECKLIST.md`). Il dominio canonico è `https://www.agriturismolavolta.com`.

## Ultimo lavoro completato

- Prompt 17 (working tree): vedi «Obiettivo corrente».
- Prompt 16 (commit `48477d1`): `docs/CMS_DESIGN.md`, `docs/THREAT_MODEL.md`, `docs/CAMPI_CONTENUTI.md` approvato (solo documenti).
- Prompt 15 (commit `c424b28`): Storico senza testo libero, rifiuto con conferma, invio idempotente, rate limit, host canonico, migrazioni sicure, coerenza dei dati, guardiani architetturali.
- Prompt 14 (commit `c8aec3d`): roadmap sincronizzata, guardrail in `AGENTS.md`, finding della review, inventario dei contenuti.

## Azioni e funzioni: verificate e incomplete

- **Verificate:** vedi `docs/ACCEPTANCE_MATRIX.md`, `docs/FINAL_REVIEW.md`, `docs/TEST_REPORT.md` (sezione Prompt 15).
- **Incomplete/non verificabili ora:** prove manuali (tastiera, screen reader, mobile, desktop, zoom), installazione su hosting reale, DNS, consegna email reale, HTTPS reale, comportamento dei `.htaccess` (redirect, pagina di manutenzione, negazione) su server che li limitano, MySQL 8, PHP-FPM/LiteSpeed reali, strumenti esterni (Lighthouse, axe, ZAP), foto reali, contenuti e dati del titolare.

## Working tree / modifiche locali da preservare

- Modifiche del prompt 17 non committate: `app/Security/{PasswordPolicy,ReauthGuard}.php` + `common-passwords.txt`, `app/Service/AccountService.php`, `app/Http/Controllers/Admin/AccountController.php`, `AdminAuth`, `BasePage`, `AdminController`, `routes_admin.php`, `bin/create-admin.php`, `migrations/0008_admin_session_version.sql` + `CHECKSUMS`, `templates/admin/account/`, voce «Account» nel menu, test (`AccountTest`, `PasswordPolicyTest`, `ReauthGuardTest`, `CreateAdminCommandTest`, adattamenti a `ScopeTest`, `AdminAccessTest`, `DataConstraintsMigrationTest`) e documenti.
- `vendor/` e `.phpunit.cache/` locali, `storage/mail/` per `MAIL_TRANSPORT=log` (ignorati da Git). Il `.env` locale usa `MAIL_TRANSPORT=log`; i recapiti `PUBLIC_*` e `WHATSAPP_NUMBER` locali sono vuoti.
- `.env` locale con password DB casuali di sviluppo (ignorato). Le migrazioni `0006`, `0007` e `0008` sono già applicate al database di sviluppo e a quello di test.
- Password admin locale impostata dall'utente: **le prove sono su un database di test separato e non l'hanno toccata**. Volumi Docker vecchi puliti dall'utente. I container Docker sono in esecuzione (avviati in questa sessione). C'è una cartella locale `legacy/` non tracciata, mai aperta.

## Test/comandi più recenti

`docker compose exec -u www-data web composer test` → **1005 test, 11494 asserzioni, PASS** (7 min 42 s: 429 unit, 326 integrazione, 236 http, 14 concorrenza); con `--order-by=random` (seme `1791415114`): 1005 test, 11498 asserzioni, PASS. Dettagli in `docs/TEST_REPORT.md` (sezione Prompt 17). Ultima migrazione: `0008_admin_session_version`. **Eseguire i comandi nel container con `-u www-data`** (altrimenti si creano file di `root` che Apache non può scrivere e PHPUnit non riesce a salvare la sua cache).

## Blocchi aperti

1. Hosting di produzione non scelto (requisiti minimi in `docs/COMMANDS.md`; per le email servono la cartella `vendor/` caricata e, se possibile, PHP-FPM e cron; con proxy inverso verificare che `REMOTE_ADDR` sia l'IP del cliente per il rate limit: scelta D4 = A, `TRUSTED_PROXIES` non introdotta).
2. **Credenziali SMTP reali non fornite**: finché mancano, le email restano in coda e la consegna reale non è verificata.
3. Dati mancanti: vedi `docs/MISSING_DATA.md` (listino, foto, testi, recapiti, WhatsApp, `APP_SECRET`, paesi degli ospiti, estensioni PHP dell'hosting).
4. Decisione P4 (`legacy/`): aperta, da chiudere nel prompt 28 (i file tracciati sono già stati rimossi il 2026-10-06). Nel repository esiste ancora una cartella locale `legacy/` non tracciata, che non è stata aperta.
5. Finding della review con parte residua in altre fasi: `docs/TODO.md`, sezione "Finding della review 2026-10-07" (voci `[~]`).

## Decisioni da non reinterpretare

Vedi `docs/DECISIONS.md`: P1–P7 approvate; decisioni Fase 1, 1b, 2A, 2B, 3, 4, 5, 6, 7 e review finale registrate (locking, tetti tecnici, tariffa per appartamento/notte, soggiorno minimo dalla data di arrivo, listino mancante = "prezzo da confermare", outbox email, token firmato senza sessione, passi separati, segnaposto per le foto, dati solo dal DB).

## Prossimo passo esatto

1. L'utente rivede le modifiche del prompt 17, esegue il «Prova tu» (riepilogo finale di questa sessione) e dice esplicitamente in chat se committare (nessun commit, push o deploy senza una sua frase).
2. Poi `prompts/18_SITE_SETTINGS.md` (impostazioni del sito: recapiti, dati aziendali, link, avviso globale, cache e pagina 503, blocco ottimistico), in un nuovo ramo `fase-18-site-settings` (partendo da `fase-17-admin-account`); le sue domande D1–D9 vanno prima all'utente. Leggere prima `docs/CMS_DESIGN.md` §3.1, §6, §7 e la tabella Impostazioni di `CAMPI_CONTENUTI.md`.
3. Promemoria per i prompt 19 e 26 (consenso per l'immagine Docker, conferma sul master delle foto, eccezione ad `AGENTS.md` per l'SMTP): `docs/TODO.md`.
4. Le attività del titolare (contenuti, autorizzazioni, hosting) restano indicate in `docs/TODO.md` e `docs/RELEASE_GUIDE.md`.

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
- **Account e conferma della password (prompt 17):** per un'azione delicata, nel controller admin: `if ($redirect = $this->needsReauth('/admin/pagina-del-modulo')) { return $redirect; }` (vedi `docs/COMMANDS.md`, «Amministratore»). Le password si leggono con `Request::rawInput()` e non entrano mai in Storico, log, messaggi o test di scansione. Le sessioni portano `sv` (versione) e `pwfp` (impronta della password): dopo un cambio si chiama `AdminAuth::refreshCurrentSession()`.
- **Un test che cambia la password dell'admin** deve ripartire da `HttpTestCase::setUp()` (riscrive l'account); `AccountTest` è l'esempio.
