# Stato corrente del progetto

> Questo file è la memoria operativa breve da aggiornare alla fine di ogni sessione sostanziale o prima di cambiare agente.

## Snapshot

- **Data aggiornamento:** 2026-10-06
- **Agente/strumento ultimo utilizzato:** Claude Code
- **Branch:** `main`
- **Commit di riferimento:** `ae3129e` (documentazione); la review finale è nel commit successivo
- **Fase corrente:** review finale pre-consegna (prompt 13): **COMPLETATA** — `docs/FINAL_REVIEW.md`
- **Prompt corrente:** `prompts/13_FINAL_REVIEW.md` (completato)
- **Stato complessivo:** 778 test PASS; 23 criteri di accettazione PASS, 4 PARTIAL (18 tastiera, 19 responsive, 20 immagini, 24 hosting reale), 0 FAIL, 0 BLOCKED. Rilette tutte le sezioni della SPEC: 6 lacune trovate, 5 corrette (servizi degli appartamenti, WhatsApp con date nel flusso, link Maps, dati strutturati `Apartment`, rimozione di `legacy/`), 1 scelta documentata. **Non pubblicabile finché mancano dati e contenuti del titolare; tutte le prove manuali e l'installazione su hosting reale sono NOT RUN**

## Obiettivo corrente

Preparazione alla pubblicazione **senza deploy**: `prompts/14_RELEASE_PREP_NO_DEPLOY.md` (compila `docs/RELEASE_GUIDE.md`). In alternativa, su decisione dell'utente, l'estensione `prompts/15_ADMIN_CONTENT_BLOCKS.md` (bozza non committata dall'agente, da approvare).

## Ultimo lavoro completato

- Rilettura integrale di `docs/SPEC.md` e confronto con il codice; `docs/FINAL_REVIEW.md` (sintesi, 27 criteri, conformità sezione per sezione, lacune, limitazioni, dati mancanti, operazioni prima della pubblicazione).
- G1 servizi degli appartamenti: migrazione `0005`, `app/Site/Amenities.php`, campo nel form admin, elenco pubblico, dati strutturati. G2 WhatsApp con appartamento/date/ospiti nel flusso. G3 link Maps. G4 JSON-LD `Apartment` con soli campi inseriti. G5 `git rm -r legacy`. G6 recapiti nel piè di pagina (scelta).
- 15 test nuovi, 7 prove di sensibilità rilevate; un test esistente aggiornato (conseguenza attesa di G4); suite completa 778 PASS.

## Azioni e funzioni: verificate e incomplete

- **Verificate:** vedi `docs/ACCEPTANCE_MATRIX.md`, `docs/FINAL_REVIEW.md`, `docs/TEST_REPORT.md`.
- **Incomplete/non verificabili ora:** prove manuali (tastiera, screen reader, mobile, desktop, zoom), installazione su hosting reale, consegna email reale, HTTPS reale, strumenti esterni (Lighthouse, axe, ZAP), foto reali, contenuti e dati del titolare, `docs/RELEASE_GUIDE.md` (prompt 14).
## Working tree / modifiche locali da preservare

- Nessuna modifica locale non committata a fine fase.
- `vendor/` e `.phpunit.cache/` locali, `storage/mail/` per `MAIL_TRANSPORT=log` (ignorati da Git). Il `.env` locale usa `MAIL_TRANSPORT=log`; i recapiti `PUBLIC_*` e `WHATSAPP_NUMBER` locali sono vuoti.
- `.env` locale con password DB casuali di sviluppo (ignorato).
- Password admin locale impostata dall'utente; volumi Docker vecchi puliti dall'utente.

## Test/comandi più recenti

`docker compose exec web composer test` → **778 test, 9914 asserzioni, PASS** (circa 7 minuti: 342 unit, 242 integrazione, 183 http, 11 concorrenza). Dettagli in `docs/TEST_REPORT.md` (sezione Review finale). Nuova migrazione: `0005`. Nessuna nuova migrazione.

## Blocchi aperti

1. Hosting di produzione non scelto (requisiti minimi in `docs/COMMANDS.md`; per le email servono la cartella `vendor/` caricata e, se possibile, PHP-FPM e cron; con proxy inverso verificare che `REMOTE_ADDR` sia l'IP del cliente per il rate limit).
2. **Credenziali SMTP reali non fornite**: finché mancano, le email restano in coda e la consegna reale non è verificata.
3. Dati mancanti: vedi `docs/MISSING_DATA.md` (listino, foto, testi, recapiti, WhatsApp, `APP_SECRET`).

## Decisioni da non reinterpretare

Vedi `docs/DECISIONS.md`: P1–P7 approvate; decisioni Fase 1, 1b, 2A, 2B, 3, 4, 5, 6, 7 e review finale registrate (locking, tetti tecnici, tariffa per appartamento/notte, soggiorno minimo dalla data di arrivo, listino mancante = "prezzo da confermare", outbox email, token firmato senza sessione, passi separati, segnaposto per le foto, dati solo dal DB).

## Prossimo passo esatto

`prompts/14_RELEASE_PREP_NO_DEPLOY.md` (vedi il promemoria in `docs/TODO.md`).

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
