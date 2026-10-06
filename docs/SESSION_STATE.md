# Stato corrente del progetto

> Questo file è la memoria operativa breve da aggiornare alla fine di ogni sessione sostanziale o prima di cambiare agente.

## Snapshot

- **Data aggiornamento:** 2026-10-07
- **Agente/strumento ultimo utilizzato:** Claude Code
- **Branch:** `main`
- **Commit di riferimento:** `523dbd2` (review finale); la preparazione al rilascio è nel commit successivo
- **Fase corrente:** preparazione alla pubblicazione, **senza deploy** (prompt 14): **COMPLETATA** — `docs/RELEASE_GUIDE.md`
- **Prompt corrente:** `prompts/14_RELEASE_PREP_NO_DEPLOY.md` (completato)
- **Stato complessivo:** 791 test PASS; 23 criteri di accettazione PASS, 4 PARTIAL (18, 19, 20, 24), 0 FAIL. Guida di rilascio compilata (prerequisiti, autorizzazioni A1–A8, pacchetto, permessi, DNS da richiedere, prova di fumo, HSTS graduale, rollback) e simulazione di produzione eseguita in locale. **Non pubblicato; nessun servizio esterno contattato; tutte le prove manuali e l'installazione su hosting reale sono NOT RUN**

## Obiettivo corrente

Nessuno aperto per lo sviluppo. Il passo successivo dipende dal titolare: contenuti e dati (`docs/MISSING_DATA.md`), autorizzazioni (`docs/RELEASE_GUIDE.md` §2), scelta dell'hosting, prove manuali (`docs/MANUAL_CHECKLIST.md`). Il dominio canonico è `https://www.agriturismolavolta.com`.

## Ultimo lavoro completato

- `docs/RELEASE_GUIDE.md`: prerequisiti, punti di autorizzazione A1–A8, pacchetto, `.env` di produzione, permessi, controllo preliminare, DNS web da richiedere (con TTL e **senza** toccare NS/MX/SPF/DKIM/DMARC), reindirizzamento non-www → www, prove prima del cambio DNS (attenzione a `APP_URL` e `Origin`), prova di fumo, sequenza con HSTS graduale, rollback, pagina di manutenzione, elenco di ciò che non è stato eseguito.
- `bin/check-production.php` e `app/Support/ProductionCheck.php`: controllo di sola lettura (nessuna connessione a SMTP o altri servizi, nessun segreto stampato); 13 test, 7 prove di sensibilità rilevate.
- Simulazione di produzione in locale (copia pulita, `--no-dev`, database usa-e-getta, SMTP verso una porta locale chiusa): controllo superato (27/27), pagine, intestazioni con HSTS, canonical sul dominio `www`, cookie `Secure`, file riservati non raggiungibili, richiesta salvata con la posta irraggiungibile. Tutto eliminato al termine.
- `docs/DELIVERY_CHECKLIST.md`, `README.md`, `docs/COMMANDS.md`, `docs/INSTALL_SHARED_HOSTING.md`, `.env.example` aggiornati.

## Azioni e funzioni: verificate e incomplete

- **Verificate:** vedi `docs/ACCEPTANCE_MATRIX.md`, `docs/FINAL_REVIEW.md`, `docs/TEST_REPORT.md` (sezione Preparazione al rilascio).
- **Incomplete/non verificabili ora:** prove manuali (tastiera, screen reader, mobile, desktop, zoom), installazione su hosting reale, DNS, consegna email reale, HTTPS reale, reindirizzamenti e pagina di manutenzione `.htaccess` (non provati), strumenti esterni (Lighthouse, axe, ZAP), foto reali, contenuti e dati del titolare.
## Working tree / modifiche locali da preservare

- Nessuna modifica locale non committata a fine fase.
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

nessuno aperto per lo sviluppo: dipende dal titolare (vedi `docs/RELEASE_GUIDE.md` e il promemoria in `docs/TODO.md`).

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
