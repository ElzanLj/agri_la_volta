# Stato corrente del progetto

> Questo file è la memoria operativa breve da aggiornare alla fine di ogni sessione sostanziale o prima di cambiare agente.

## Snapshot

- **Data aggiornamento:** 2026-10-06
- **Agente/strumento ultimo utilizzato:** Claude Code
- **Branch:** `main`
- **Commit di riferimento:** `521824c` (Fase 3); la Fase 4 è nel commit successivo
- **Fase corrente:** Fase 4 — email, robustezza SMTP e WhatsApp: **COMPLETATA** (prompt 07)
- **Prompt corrente:** `prompts/07_EMAIL_WHATSAPP.md` (completato)
- **Stato complessivo:** coda email transazionale (outbox), invio dopo il commit con ogni guasto contenuto, server SMTP finto per i test, link WhatsApp; 613 test PASS. **Consegna reale non verificata: mancano le credenziali SMTP**

## Obiettivo corrente

Fase 5 (frontend pubblico e flusso di richiesta): `prompts/08_PUBLIC_FRONTEND.md`. Riuserà `BookingService::createRequest` (che già accoda la notifica al gestore e prezzo/limiti lato server), `WhatsApp::businessMessage/businessLink` per il pulsante pubblico e `DeferredWork` per l'invio dopo la risposta. Contenuti e stile da `legacy/`; foto solo con provenienza verificata.

## Ultimo lavoro completato

- Migrazione `0004_email_outbox.sql`; `app/Mail/` (`MailTransport`, `SmtpTransport` su PHPMailer, `LogTransport` solo sviluppo, `NotConfiguredTransport`, `MessageBuilder`, `CancellationDraft`, `NotificationService`, `PostCommitNotifier`, `ErrorSanitizer`); `OutboxRepository`; `Services` (composizione); `DeferredWork`; `WhatsApp`.
- `BookingService`: accoda nella transazione (`queueMail`, che non può mai bloccare lo stato), hook `afterCommit` che non propaga errori.
- Admin: pagina **Email** (stato, filtri, "Riprova invio"), stato email nei dettagli, contatore in dashboard, **bozza di cancellazione** modificabile con invio solo esplicito, link WhatsApp verso i clienti; `bin/send-queued-mail.php` per il cron.
- Server SMTP finto (`tests/Support/fake-smtp-server.php`) con 10 scenari di guasto più uno lento; 613 test (278 unit, 223 integrazione, 101 http, 11 concorrenza); 14 prove di sensibilità tutte rilevate; 1 difetto di progetto e 5 bug trovati e corretti (vedi `docs/TEST_REPORT.md`).

## Azioni e funzioni: verificate e incomplete

- **Verificate:** accodamento atomico, invio dopo il commit, contenimento di ogni errore, retry e backoff, nessun doppio invio (anche con processi paralleli), notifica al gestore, conferma e rifiuto IT/EN, bozza di cancellazione, link WhatsApp, pagina Email e riprova, script cron.
- **Incomplete/non verificabili ora:** consegna reale (TLS con certificato vero, provider, SPF/DKIM), invio dopo chiusura risposta su PHP-FPM, testi definitivi delle email, numero WhatsApp dell'agriturismo (pulsante pubblico in Fase 5), test manuali nel browser.

## Working tree / modifiche locali da preservare

- Nessuna modifica locale non committata a fine fase.
- `vendor/` (ora con PHPMailer) e `.phpunit.cache/` locali, `storage/mail/` per `MAIL_TRANSPORT=log` (ignorati da Git). Il `.env` locale usa `MAIL_TRANSPORT=log`.
- `.env` locale con password DB casuali di sviluppo (ignorato).
- Copia del `docker-compose.yml` originale dell'utente nello scratchpad della sessione (conteneva solo una password di sviluppo).
- Password admin locale impostata dall'utente; volumi Docker vecchi puliti dall'utente.

## Test/comandi più recenti

`docker compose exec web composer test` → 613 test, 5285-5291 asserzioni, PASS (2 esecuzioni, circa 4,5 minuti). Dettagli in `docs/TEST_REPORT.md` (sezione Fase 4). Migrazione 0004 applicata anche al DB di sviluppo.

## Blocchi aperti

1. Hosting di produzione non scelto (requisiti minimi in `docs/COMMANDS.md`; per le email servono la cartella `vendor/` caricata e, se possibile, PHP-FPM e cron).
2. **Credenziali SMTP reali non fornite**: finché mancano, le email restano in coda e la consegna reale non è verificata.
3. Dati mancanti: vedi `docs/MISSING_DATA.md`.

## Decisioni da non reinterpretare

Vedi `docs/DECISIONS.md`: P1–P7 approvate; decisioni Fase 1, 1b, 2A, 2B, 3 e 4 registrate (locking, tetti tecnici, tariffa per appartamento/notte con voci additive, soggiorno minimo dalla data di arrivo, listino mancante = "prezzo da confermare").

## Prossimo passo esatto

`prompts/08_PUBLIC_FRONTEND.md`.

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
- Ogni nuova rotta `/admin/...` va registrata in `app/routes_admin.php`: eredita le guardie. Le modifiche sono solo POST con `csrf_field()`; i GET non devono mai scrivere (un test lo verifica). Output sempre con `e()`.
- Quando si aggiungono pagine admin, aggiungere i test in `tests/Http/` (la matrice non autenticato/CSRF le copre automaticamente se la rotta è registrata).
- Il database di test viene ripristinato da `DatabaseTestCase::resetDatabase()`: se si aggiungono tabelle o campi modificabili dall'admin, aggiornarlo.
- Lo stato (richiesta, conferma, prenotazione) non deve mai dipendere da SMTP né dalla coda: l'email si accoda con `queueMail` dentro la transazione (errori contenuti) e si invia solo dall'hook dopo il commit. Mai inviare dentro una transazione.
- Nuove email: aggiungere il tipo in migrazione (CHECK di `email_outbox.type`), in `MessageBuilder` e un test in `OutboxFlowTest`/`MailResilienceTest`. Il rendering avviene a invio, mai nella transazione.
- Non salvare mai testi di errore del server o indirizzi nei log o in `error_message`: passare da `ErrorSanitizer`.
- Per provare l'SMTP usare `FakeSmtpServer`, mai un server reale; i test dell'admin usano `MAIL_TRANSPORT=log` su cartella temporanea.
