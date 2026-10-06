# Comandi verificati del progetto

> Inserire solo comandi realmente presenti o verificati nel repository.

## Ambiente rilevato

Verificato il 2026-10-05.

- OS/runtime host: Windows 11 Pro, Git Bash / PowerShell
- Docker: Docker Compose v5.4.0
- PHP: 8.2.34 nel container `web` (`docker/php/Dockerfile`: `php:8.2-apache` + `pdo_mysql`, `mod_rewrite`, `mod_headers`). PHP non installato sull'host.
- Composer: 2.10.3 nel container `web`, solo per PHPUnit (sviluppo); l'applicazione usa un autoloader interno e la produzione non richiede Composer
- MySQL/MariaDB: MariaDB 10.11 nel container `db`
- Node/npm: Node v24.18.0, npm 11.16.0 (solo per l'app legacy in `legacy/`)

## Requisiti minimi produzione

- PHP **8.1+** con estensione `pdo_mysql` (il codice usa proprietà `readonly` e il tipo di ritorno `never`).
- MySQL 5.7+ / 8.0 oppure MariaDB 10.3+, motore InnoDB, `utf8mb4`.
  - I vincoli `CHECK` sono applicati da MariaDB 10.2+ e MySQL 8.0.16+; MySQL 5.7 li ignora (l'applicazione valida comunque i dati).
- Apache con `mod_rewrite` e `.htaccess` abilitati (`AllowOverride All`).

## Sviluppo locale (Docker, solo sviluppo)

Prima volta:

```bash
cp .env.example .env
# Impostare in .env: APP_ENV=development, APP_DEBUG=true, DB_HOST=db,
# DB_PASSWORD e DB_ROOT_PASSWORD (valori a scelta, solo locali).
docker compose up -d --build
docker compose exec web php bin/migrate.php
docker compose exec web php bin/create-admin.php admin   # chiede la password due volte
```

Sito: http://localhost:8080 · Admin: http://localhost:8080/admin

Il container `web` usa la **root del repository** come document root, così in sviluppo si verifica anche il fallback `.htaccess` per hosting senza document root configurabile.

Comandi utili:

```bash
docker compose ps
docker compose exec web php bin/migrate.php --status
docker compose exec db sh -c 'mariadb -uagriturismo -p"$MARIADB_PASSWORD" agriturismo'
docker compose down            # ferma i container; i dati restano nel volume db_data
```

`docker compose down -v` **cancella** il database locale (volume `db_data`).

## Database / migrazioni

```bash
php bin/migrate.php            # applica le migrazioni mancanti, in ordine
php bin/migrate.php --status   # [x] applicata / [ ] da applicare
```

- File in `migrations/NNNN_descrizione.sql`, applicati una sola volta (tabella `schema_migrations`).
- Ogni file termina con `INSERT IGNORE INTO schema_migrations ...`: su hosting senza riga di comando si possono importare i file **in ordine** da phpMyAdmin e `bin/migrate.php` li riconoscerà come già applicati.
- Le migrazioni sono **solo in avanti**: MySQL/MariaDB confermano le istruzioni DDL una per una, quindi non esiste rollback automatico.

### Backup e ripristino (strategia)

Prima di applicare migrazioni a un database con dati reali:

```bash
mysqldump --single-transaction --routines --default-character-set=utf8mb4 -h HOST -u USER -p DB_NAME > backup-AAAAMMGG.sql
```

Ripristino: creare un database vuoto e importare il dump (`mysql ... DB_NAME < backup-AAAAMMGG.sql` o import phpMyAdmin). Procedura completa di backup in Fase 8.

## Test automatici (PHPUnit, solo sviluppo)

Prima volta: `docker compose up -d --build` (l'immagine include Composer), poi:

```bash
docker compose exec web composer install      # installa PHPUnit in vendor/ (ignorato da Git)
docker compose exec web composer test         # prepara il DB di test (applica le migrazioni), poi esegue tutte le suite (751 test, circa 7 minuti)
```

Suite singole:

```bash
docker compose exec web composer test -- --testsuite unit          # secondi, senza DB
docker compose exec web composer test -- --testsuite integration   # circa 3 s
docker compose exec web composer test -- --testsuite http          # circa 3 minuti: area admin e sito pubblico via HTTP reale (sicurezza, azioni, CSV, pagine, flusso di richiesta, antispam)
docker compose exec web composer test -- --testsuite concurrency   # circa 70 s, processi PHP reali in parallelo
docker compose exec web vendor/bin/phpunit --testsuite unit --testdox
```

- I test usano il database `agriturismo_test` (creato da `tests/prepare-db.php` con `DB_ROOT_PASSWORD` di `.env`). Si rifiutano di partire se il nome non finisce con `_test`: non toccano mai i dati di sviluppo.
- La suite `concurrency` avvia 2-16 processi PHP (`tests/Support/worker.php`), ciascuno con la propria connessione, rilasciati nello stesso istante; ripete ogni scenario per 8 round.
- La suite `http` avvia l'applicazione dietro il server PHP built-in (porta libera casuale, DB `agriturismo_test`) e la interroga con un client HTTP che gestisce i cookie; confronta checksum delle tabelle prima e dopo ogni richiesta rifiutata.
- Dopo ogni round la suite controlla con SQL che non esistano prenotazioni `confirmed` sovrapposte né prenotazioni sopra un blocco.

### Listino prezzi

Il listino non è nel codice né nelle migrazioni: si inserisce tramite `PricingConfigService` (interfaccia admin in Fase 3). Dopo `git pull` con nuove migrazioni eseguire `php bin/migrate.php`.

## Email

Configurazione (`.env` o variabili dell'hosting; esempio in `.env.example`):

| Variabile | Significato |
|---|---|
| `MAIL_TRANSPORT` | `smtp` (predefinito) oppure `log` (solo sviluppo: scrive i messaggi in `storage/mail/`, **rifiutato in produzione**) |
| `SMTP_HOST`, `SMTP_PORT`, `SMTP_ENCRYPTION` (`tls`/`ssl`/`none`), `SMTP_USERNAME`, `SMTP_PASSWORD` | server SMTP; con `SMTP_HOST` vuoto le email restano in coda |
| `SMTP_TIMEOUT` | secondi di attesa del server (10) |
| `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`, `MAIL_ADMIN_ADDRESS` | mittente e destinatario delle notifiche |
| `WHATSAPP_NUMBER`, `WHATSAPP_DEFAULT_COUNTRY_CODE` | numero dell'agriturismo (cifre, senza `+`) e prefisso per i numeri senza prefisso (39) |

Come funziona: le email nascono nella stessa transazione della modifica (tabella `email_outbox`) e partono subito dopo il commit; un errore di invio non tocca mai richieste o prenotazioni. Stato e tentativi sono in **Admin > Email**, con pulsante "Riprova invio".

Script per il cron (facoltativo): `php bin/send-queued-mail.php [--limit=20]` invia ciò che è in coda e scaduto (es. ogni 10 minuti).

**Produzione:** PHPMailer richiede la cartella `vendor/`. Va generata in locale e caricata sull'hosting:

```bash
composer install --no-dev --optimize-autoloader
```

Poi caricare `vendor/` insieme al resto del sito (non è versionata). Senza `vendor/` l'invio fallisce con "PHPMailer non installato" e le email restano in coda.

Applicare la migrazione `0004` (`php bin/migrate.php` oppure importando `migrations/0004_email_outbox.sql`) **prima** di pubblicare; se manca, il sito continua a salvare tutto ma non accoda le email (viene registrato nei log).

## Sito pubblico

Pagine (IT senza prefisso, EN sotto `/en`; la tabella degli URL è `app/Site/Routes.php`): home, L'agriturismo, Appartamenti, pagina di ogni appartamento, Dintorni, Richiedi disponibilità, Contatti, Privacy, Cookie.

Flusso di richiesta (nessun JavaScript): date e ospiti → appartamenti disponibili con prezzo → dati del cliente → riepilogo e consenso privacy → "Richiesta ricevuta". Il prezzo è sempre calcolato dal server; la richiesta nasce `pending` e non è mai una prenotazione.

Configurazione (`.env` o variabili dell'hosting; tutte facoltative, una voce vuota non viene mostrata):

| Variabile | Significato |
|---|---|
| `PUBLIC_PHONE`, `PUBLIC_EMAIL`, `PUBLIC_ADDRESS` | recapiti mostrati su Contatti e nel piè di pagina |
| `WHATSAPP_NUMBER` | abilita il pulsante WhatsApp pubblico (messaggio precompilato e modificabile) |
| `APP_SECRET` | segreto per firmare i token dei moduli pubblici; in produzione impostare un valore casuale lungo (se manca viene derivato dalle credenziali DB) |
| `PUBLIC_FORM_MIN_SECONDS` | secondi minimi per compilare il passo dei dati (3; `0` disattiva il controllo) |

Contenuti: i testi fissi sono in `content/it.php` e `content/en.php` (stesse chiavi: un test lo verifica); descrizioni, capienza, orari e regole degli appartamenti si inseriscono dall'admin e le pagine omettono ciò che è vuoto. Le foto sono segnaposto finché non ne viene verificata la provenienza.

I moduli pubblici non usano sessioni né cookie: sono protetti da un token firmato, controllo `Origin`, honeypot, controllo temporale e rate limit (6 invii all'ora per IP, salvato solo come hash).

### SEO, accessibilità e immagini

- `/robots.txt` e `/sitemap.xml` sono generati dal codice (URL e appartamenti attivi); non vanno modificati a mano.
- I colori del sito sono variabili `:root` in `public/assets/css/site.css`; `ContrastTest` verifica il contrasto WCAG: se si cambia la palette, eseguire `composer test -- --testsuite unit`.
- Immagini: nessuna foto è pubblicata finché non ne è verificata la provenienza (`docs/IMAGES.md`). Per generare le varianti responsive di una foto verificata: `php bin/optimize-images.php <originale> <nome> [larghezze]` (richiede PHP con GD e WebP; non disponibile nel container di sviluppo).
- Cache e compressione degli asset sono in `public/.htaccess`; gli URL degli asset hanno `?v=<data di modifica>`.

## Privacy e sicurezza

Dati personali (solo riga di comando; simulazione di default, `--apply` per eseguire):

```bash
php bin/privacy.php export mario.rossi@example.com            # tutto ciò che il sistema conserva su una persona (JSON)
php bin/privacy.php erase mario.rossi@example.com             # cosa verrebbe anonimizzato
php bin/privacy.php erase mario.rossi@example.com --apply     # anonimizza (nome, email, telefono, note); date e appartamento restano
php bin/privacy.php purge --months=24                         # simula: soggiorni terminati da più di 24 mesi
php bin/privacy.php purge --months=24 --apply
```

Richieste in attesa e soggiorni non ancora finiti vengono saltati e segnalati (`--include-active` per includerli in `erase`). Senza `--months` il comando usa `DATA_RETENTION_MONTHS`; se è vuota non fa nulla. I backup del database non vengono modificati.

Variabili: `HSTS_MAX_AGE` (secondi, solo con `APP_URL` https, 0 disattiva), `DATA_RETENTION_MONTHS`, `APP_SECRET`. Revisione completa in `docs/SECURITY_REVIEW.md`.

## Area amministrativa

- Indirizzo: `/admin` (sul container: http://localhost:8080/admin). Non compare nella navigazione pubblica e non è indicizzata.
- Pagine: Home, Richieste, Prenotazioni (+ nuova manuale), Calendario, Blocchi, Appartamenti, Listino, Storico, Export (CSV richieste e prenotazioni).
- Tutta l'area è protetta da tre guardie a livello di prefisso (vedi `app/routes_admin.php`): risposte private, autenticazione, CSRF. Le nuove rotte sotto `/admin` le ereditano automaticamente; regola: **le modifiche sono solo POST**.
- Il cambio password si fa solo da riga di comando e chiude le sessioni aperte.
- Conferma e rifiuto inviano l'email al cliente (se l'invio fallisce la decisione resta salvata e lo stato è visibile in Admin > Email). La cancellazione **non invia nulla da sola**: prepara una bozza modificabile che si invia solo con un'azione esplicita.
- Dal dettaglio di richiesta e prenotazione: link WhatsApp verso il cliente (testo modificabile prima dell'invio).

## Amministratore

```bash
php bin/create-admin.php [username]
```

Crea l'unico account admin o, se esiste già, ne cambia nome utente e password. Password minimo 12 caratteri, letta da terminale (nascosta su Linux/macOS) o da due righe di standard input; mai come argomento.

## Verifiche eseguite in Fase 1

```bash
# sintassi PHP
docker compose exec web sh -c 'for f in $(find app bin public templates -name "*.php"); do php -l "$f" >/dev/null || echo "FAIL $f"; done'
```

Test HTTP manuali con `curl` (vedi `docs/TEST_REPORT.md`). Nessuna suite automatica ancora: PHPUnit arriva in Fase 2A.

## App legacy (React/Vite, in `legacy/`, da dismettere)

```bash
cd legacy
npm ci --no-audit --no-fund
npm run build     # PASS con warning
npm run lint      # FAIL: 43 errori preesistenti
```

Il legacy non usa più Firebase, login, pagamenti né server email (rimossi in Fase 1b); `npm run lint` dà 43 errori preesistenti. Resta il file locale non tracciato `legacy/src/EmailStatus/.env` (credenziale già revocata): può essere cancellato dal titolare.

## Note hosting condiviso

- Document root su `public/` quando il pannello lo consente; altrimenti caricare il progetto nella root del sito: il `.htaccess` principale reindirizza tutto in `public/` e rende irraggiungibili `.env`, `app/`, `migrations/`, `storage/` ecc. Se `mod_rewrite` manca, il sito risponde 403 invece di esporre file.
- `storage/logs/` e `storage/sessions/` devono essere scrivibili da PHP.
- Variabili d'ambiente reali del pannello hosting, se presenti, prevalgono su `.env`.
