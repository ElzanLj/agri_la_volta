# Stato corrente del progetto

> Questo file è la memoria operativa breve da aggiornare alla fine di ogni sessione sostanziale o prima di cambiare agente.

## Snapshot

- **Data aggiornamento:** 2026-10-06
- **Agente/strumento ultimo utilizzato:** Claude Code
- **Branch:** `main`
- **Commit di riferimento:** `7300e17` (Fase 4 + promemoria); la Fase 5 è nel commit successivo
- **Fase corrente:** Fase 5 — frontend pubblico e flusso di richiesta: **COMPLETATA** (prompt 08)
- **Prompt corrente:** `prompts/08_PUBLIC_FRONTEND.md` (completato)
- **Stato complessivo:** pagine pubbliche IT/EN, pagina di ogni appartamento (dati dal DB), flusso di richiesta a 4 passi fino a "Richiesta ricevuta", antispam senza cookie; 684 test PASS. **Mancano contenuti e dati reali** (testi, foto, recapiti, listino, credenziali SMTP): le pagine li omettono o mostrano segnaposto marcati

## Obiettivo corrente

Fase 6 (IT/EN, SEO, accessibilità, prestazioni, immagini): `prompts/09_I18N_SEO_A11Y_PERF.md`. Parte da ciò che esiste già: canonical, hreflang, `lang`, skip link, landmark, 404 localizzata; mancano Open Graph, favicon, robots.txt, sitemap, verifica contrasto/tastiera/menu mobile, immagini reali ottimizzate (oggi segnaposto).

## Ultimo lavoro completato

- `app/Site/` (`Routes` tabella URL IT/EN, `Locale`, `Text` + `t()`/`lurl()`, `FormToken` token firmato senza sessione, `Contacts`, `Format`); `app/Security/OriginCheck.php` (condiviso con la guardia CSRF admin); `app/Http/Controllers/Site/` (`SitePage`, `SiteController`, `RequestFlowController`); `templates/layout.php` e `templates/public/`; testi in `content/it.php` e `content/en.php`; CSS pubblico in fondo a `public/assets/css/site.css`.
- `BookingService`: la validazione di `createRequest` è stata estratta in `prepareRequest`, riusata da `previewRequest` (anteprima senza scrivere): il prezzo mostrato e quello salvato vengono dallo stesso codice. `ApartmentRepository::listPublic`/`findPublicBySlug`, `Services::priceQuoter`.
- Flusso: date e ospiti → appartamenti disponibili con prezzo (con motivo se non adatti) → dati → riepilogo + consenso → invio (303 verso la pagina "ricevuta"). Nulla si scrive prima dell'invio; stato sempre `pending`.
- Antispam: token firmato (scadenza 2 h, controllo "troppo veloce"), `Origin`, honeypot, rate limit 6/ora per IP. Nessun cookie per i visitatori.
- 71 test nuovi (21 unit, 19 pagine, 22 flusso, 9 antispam); 7 prove di sensibilità tutte rilevate; 1 bug trovato e corretto (formato del riferimento nella pagina "ricevuta"); timeout di Composer alzato a 1200 s perché la suite supera i 300 s.

## Azioni e funzioni: verificate e incomplete

- **Verificate (HTTP reale):** tutte le pagine IT/EN, 404 localizzata, nessun cookie e nessun link a `/admin`, flusso completo IT ed EN, prezzo e disponibilità solo dal server, limiti di appartamento, validazione e dati conservati, consenso privacy, token/Origin/honeypot/rate limit, escaping.
- **Incomplete/non verificabili ora:** nessuna foto reale (segnaposto); testi di "L'agriturismo" e "Dintorni" (avviso al loro posto); recapiti pubblici e WhatsApp (assenti finché non configurati); privacy e cookie sono bozze; EN provvisorio; nessun test manuale in browser, da tastiera, su mobile; contrasto non misurato; SEO/performance rinviate alla Fase 6.

## Working tree / modifiche locali da preservare

- Nessuna modifica locale non committata a fine fase.
- `vendor/` e `.phpunit.cache/` locali, `storage/mail/` per `MAIL_TRANSPORT=log` (ignorati da Git). Il `.env` locale usa `MAIL_TRANSPORT=log`; i recapiti `PUBLIC_*` e `WHATSAPP_NUMBER` locali sono vuoti.
- `.env` locale con password DB casuali di sviluppo (ignorato).
- Password admin locale impostata dall'utente; volumi Docker vecchi puliti dall'utente.

## Test/comandi più recenti

`docker compose exec web composer test` → **684 test, 6912 asserzioni, PASS** (circa 5 minuti: 299 unit, 223 integrazione, 151 http, 11 concorrenza). Dettagli in `docs/TEST_REPORT.md` (sezione Fase 5). Nessuna nuova migrazione in Fase 5.

## Blocchi aperti

1. Hosting di produzione non scelto (requisiti minimi in `docs/COMMANDS.md`; per le email servono la cartella `vendor/` caricata e, se possibile, PHP-FPM e cron; con proxy inverso verificare che `REMOTE_ADDR` sia l'IP del cliente per il rate limit).
2. **Credenziali SMTP reali non fornite**: finché mancano, le email restano in coda e la consegna reale non è verificata.
3. Dati mancanti: vedi `docs/MISSING_DATA.md` (listino, foto, testi, recapiti, WhatsApp, `APP_SECRET`).

## Decisioni da non reinterpretare

Vedi `docs/DECISIONS.md`: P1–P7 approvate; decisioni Fase 1, 1b, 2A, 2B, 3, 4 e 5 registrate (locking, tetti tecnici, tariffa per appartamento/notte, soggiorno minimo dalla data di arrivo, listino mancante = "prezzo da confermare", outbox email, token firmato senza sessione, passi separati, segnaposto per le foto, dati solo dal DB).

## Prossimo passo esatto

`prompts/09_I18N_SEO_A11Y_PERF.md` (vedi il promemoria in `docs/TODO.md`).

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
