# Stato corrente del progetto

> Questo file è la memoria operativa breve da aggiornare alla fine di ogni sessione sostanziale o prima di cambiare agente.

## Snapshot

- **Data aggiornamento:** 2026-10-06
- **Agente/strumento ultimo utilizzato:** Claude Code
- **Branch:** `main`
- **Commit di riferimento:** `d0451eb` (Fase 5); la Fase 6 è nel commit successivo
- **Fase corrente:** Fase 6 — IT/EN, SEO, accessibilità, prestazioni e immagini: **COMPLETATA per la parte automatizzabile** (prompt 09); la verifica manuale resta NOT RUN
- **Prompt corrente:** `prompts/09_I18N_SEO_A11Y_PERF.md` (completato)
- **Stato complessivo:** sito pubblico IT/EN con SEO tecnica, struttura accessibile verificata da test e contrasto WCAG misurato; 730 test PASS. **Mancano contenuti e dati reali** (testi, foto, recapiti, listino, credenziali SMTP) e le prove manuali con tastiera, screen reader e dispositivi

## Obiettivo corrente

Fase 7 (sicurezza, privacy tecnica, antispam): `prompts/10_SECURITY_PRIVACY_SPAM.md`. Molto è già fatto in Fasi 3-5 (vedi `docs/TODO.md`, promemoria): la fase deve verificare e completare.

## Ultimo lavoro completato

- SEO: `robots.txt` e `sitemap.xml` generati (`app/Site/Seo.php`, `SeoController`), Open Graph/Twitter card, favicon SVG, breadcrumb visibile + `BreadcrumbList`, `LodgingBusiness` solo con recapiti configurati; asset con `?v=` (data di modifica), cache lunga e compressione in `public/.htaccess`.
- Accessibilità: ogni pagina ha un `h1` e nessun salto di livello (schede appartamento a `h2` in elenco), landmark etichettati, label su ogni campo, avvisi sui campi obbligatori, `caption` nascosta sulla tabella prezzi, bersagli da 44 px. Palette in variabili `:root`, contrasto verificato da `ContrastTest` (nessun colore cambiato: già conforme).
- Immagini: `app/Site/ImageSet.php` + `_photo.php` (picture, srcset, width/height, lazy, hero), `bin/optimize-images.php` (non eseguito), `docs/IMAGES.md` con censimento e procedura. Foto reali: nessuna (segnaposto).
- Test: 46 nuovi (25 contrasto, 4 immagini, 17 SEO/accessibilità); `tests/Support/router.php` serve i file statici nel server di test; 7 prove di sensibilità tutte rilevate.

## Azioni e funzioni: verificate e incomplete

- **Verificate (automaticamente):** robots, sitemap, metadati, dati strutturati, breadcrumb, intestazioni, landmark, label, id unici, nomi dei link, contrasto, bersagli e focus nel CSS, assenza di JavaScript e risorse esterne, peso delle pagine.
- **Incomplete/non verificabili ora:** prova manuale con tastiera/screen reader/mobile/zoom (NOT RUN); Lighthouse/axe; foto reali e ottimizzazione immagini; gestione foto nel DB/admin (rinviata); testi di L'agriturismo e Dintorni; recapiti e WhatsApp; testi legali.
## Working tree / modifiche locali da preservare

- Nessuna modifica locale non committata a fine fase.
- `vendor/` e `.phpunit.cache/` locali, `storage/mail/` per `MAIL_TRANSPORT=log` (ignorati da Git). Il `.env` locale usa `MAIL_TRANSPORT=log`; i recapiti `PUBLIC_*` e `WHATSAPP_NUMBER` locali sono vuoti.
- `.env` locale con password DB casuali di sviluppo (ignorato).
- Password admin locale impostata dall'utente; volumi Docker vecchi puliti dall'utente.

## Test/comandi più recenti

`docker compose exec web composer test` → **730 test, 8038 asserzioni, PASS** (circa 5 minuti 40 s: 328 unit, 223 integrazione, 168 http, 11 concorrenza). Dettagli in `docs/TEST_REPORT.md` (sezione Fase 6). Nessuna nuova migrazione.

## Blocchi aperti

1. Hosting di produzione non scelto (requisiti minimi in `docs/COMMANDS.md`; per le email servono la cartella `vendor/` caricata e, se possibile, PHP-FPM e cron; con proxy inverso verificare che `REMOTE_ADDR` sia l'IP del cliente per il rate limit).
2. **Credenziali SMTP reali non fornite**: finché mancano, le email restano in coda e la consegna reale non è verificata.
3. Dati mancanti: vedi `docs/MISSING_DATA.md` (listino, foto, testi, recapiti, WhatsApp, `APP_SECRET`).

## Decisioni da non reinterpretare

Vedi `docs/DECISIONS.md`: P1–P7 approvate; decisioni Fase 1, 1b, 2A, 2B, 3, 4, 5 e 6 registrate (locking, tetti tecnici, tariffa per appartamento/notte, soggiorno minimo dalla data di arrivo, listino mancante = "prezzo da confermare", outbox email, token firmato senza sessione, passi separati, segnaposto per le foto, dati solo dal DB).

## Prossimo passo esatto

`prompts/10_SECURITY_PRIVACY_SPAM.md` (vedi il promemoria in `docs/TODO.md`).

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
