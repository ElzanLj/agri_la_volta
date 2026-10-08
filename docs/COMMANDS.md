# Comandi verificati del progetto

Per l'installazione su hosting, il database, l'amministratore, i backup e la manutenzione vedi `docs/INSTALL_SHARED_HOSTING.md` e `docs/OPERATIONS.md`; per l'architettura `docs/ARCHITECTURE.md`.

> Inserire solo comandi realmente presenti o verificati nel repository.

## Ambiente rilevato

Verificato il 2026-10-05.

- OS/runtime host: Windows 11 Pro, Git Bash / PowerShell
- Docker: Docker Compose v5.4.0
- PHP: 8.2.34 nel container `web` (`docker/php/Dockerfile`: `php:8.2-apache` + `pdo_mysql`, `mod_rewrite`, `mod_headers`). PHP non installato sull'host.
- Composer: 2.10.3 nel container `web`, solo per PHPUnit (sviluppo); l'applicazione usa un autoloader interno e la produzione non richiede Composer
- MySQL/MariaDB: MariaDB 10.11 nel container `db`

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

### Permessi dei file creati nel container (solo sviluppo)

`docker compose exec web ...` esegue i comandi come **root**, mentre Apache usa `www-data`. Se un comando o un test crea per primo il file di log del giorno (`storage/logs/app-AAAA-MM-GG.log`) o una sessione, il file resta di root e Apache non può più scriverci. Il sito non si rompe più (il `Logger` ripiega su `error_log`), ma il log del giorno non viene scritto. Per rimediare:

```bash
docker compose exec web chown -R www-data:www-data storage
```

Per evitarlo, lancia i comandi come `www-data`: `docker compose exec -u www-data web php bin/migrate.php`.

## Database / migrazioni

```bash
php bin/migrate.php            # applica le migrazioni mancanti, in ordine
php bin/migrate.php --status   # [x] applicata / [ ] da applicare
```

- File in `migrations/NNNN_descrizione.sql`, applicati una sola volta (tabella `schema_migrations`).
- Ogni file termina con `INSERT IGNORE INTO schema_migrations ...`: su hosting senza riga di comando si possono importare i file **in ordine** da phpMyAdmin e `bin/migrate.php` li riconoscerà come già applicati.
- Le migrazioni sono **solo in avanti**: MySQL/MariaDB confermano le istruzioni DDL una per una, quindi non esiste rollback automatico.

### Formato e protezioni delle migrazioni

- Contano solo i file `NNNN_nome.sql` (quattro cifre, nome in minuscolo con `a-z`, cifre e `_`), con numeri consecutivi. Qualsiasi altro file in `migrations/` viene ignorato (e un test segnala quelli con un nome diverso).
- Un'istruzione termina con un `;` **fuori da testo tra apici, virgolette, backtick e commenti**: un `;` o una riga che inizia con `--` dentro un testo non spezza la query. I commenti `-- `, `#` e `/* */` vengono ignorati.
- **Due esecuzioni insieme sono impossibili**: `bin/migrate.php` prende un lock del database (`GET_LOCK`). La seconda esecuzione si ferma subito con "Another migration run is in progress"; riprova quando la prima ha finito.
- **Una migrazione rilasciata non si modifica mai**: `migrations/CHECKSUMS` elenca l'impronta SHA-256 di ogni file (a capo normalizzati) e un test fallisce se un file cambia o se ne manca la riga. Per modificare lo schema si crea un file nuovo col numero successivo, poi `php bin/migration-checksums.php` ne aggiunge la riga (aggiunge soltanto, non riscrive le esistenti).
- Alcune migrazioni controllano i dati **prima** di partire. Esempio: la `0007` aggiunge vincoli e, se nel database ci sono righe che li violano, si ferma con un messaggio che dice cosa correggere e **non modifica nulla**.

### Migrazione interrotta a metà

Una migrazione che fallisce a metà lascia applicate le istruzioni già eseguite e **non** viene registrata come applicata. Prima di riprovare:

1. leggi l'errore (nome del file e istruzione);
2. con `php bin/migrate.php --status` verifica che il file sia ancora `[ ]`;
3. guarda nel database cosa è già cambiato (tabelle e colonne create dal file) e annulla a mano le istruzioni già eseguite, oppure ripristina il backup fatto prima di migrare (consigliato);
4. correggi la causa e riesegui `php bin/migrate.php`.

Non cancellare mai righe da `schema_migrations` per "forzare" un file già applicato.

### Coerenza dei dati

```bash
php bin/check-consistency.php   # sola lettura; esce con 0 se tutto è coerente, 1 se trova qualcosa, 2 se non riesce a controllare
```

Cerca: prenotazioni confermate sovrapposte nello stesso appartamento, prenotazioni sovrapposte a un blocco, richieste "confermate" senza prenotazione confermata, prenotazioni attive collegate a una richiesta non confermata, email rimaste in invio oltre il tempo consentito. Stampa solo numeri e riferimenti, **non corregge nulla**: serve capire la causa. La pagina nell'admin e l'avviso in dashboard arrivano con il prompt 26.

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
docker compose exec web composer test         # prepara il DB di test (applica le migrazioni), poi esegue tutte le suite (763 test, circa 7 minuti; con `-- --order-by=random` si prova l'indipendenza dall'ordine)
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

Storico: da ottobre 2026 lo Storico non copia più il testo libero dei motivi (registra solo "motivo presente: sì/no"). Per ripulire le voci scritte da versioni precedenti:

```bash
php bin/privacy.php audit-clean            # simula: quante voci contengono un motivo scritto a mano
php bin/privacy.php audit-clean --apply    # le sostituisce con "motivo presente"; si può ripetere senza danni
```

Un'anonimizzazione toglie anche la chiave di invio del modulo (un hash derivato dai dati dell'ospite).

Client e proxy: il limite dei tentativi usa **solo** l'indirizzo della connessione (`REMOTE_ADDR`); gli header `X-Forwarded-For`, `CF-Connecting-IP` e simili vengono ignorati perché chiunque può falsificarli. Se l'hosting mette un proxy davanti al sito, tutti i visitatori sembreranno avere lo stesso indirizzo e condivideranno i limiti: va verificato sull'hosting scelto (l'avviso in "Stato del sistema" arriva con il prompt 26).

Host canonico: se `APP_URL` è impostato, le richieste GET/HEAD verso un altro nome di dominio (per esempio senza `www`) ricevono un redirect 301 verso l'host di `APP_URL`, mantenendo percorso e parametri. Non c'è redirect se `APP_URL` è vuoto, per i POST o se la richiesta porta `X-Forwarded-Host` (un proxy che riscrive `Host` creerebbe un ciclo: in quel caso lascia `APP_URL` vuoto). Il passaggio da http a https resta compito dell'hosting.

`APP_ENV` accetta solo `production`, `development`, `testing`; qualsiasi altro valore (anche `prod` o `Production`) vale `production` e viene segnalato nel log.

Variabili: `HSTS_MAX_AGE` (secondi, solo con `APP_URL` https, 0 disattiva), `DATA_RETENTION_MONTHS`, `APP_SECRET`. Revisione completa in `docs/SECURITY_REVIEW.md`.

## Controllo di produzione

```bash
php bin/check-production.php            # esce con 1 se c'è un errore bloccante
php bin/check-production.php --strict   # esce con 1 anche per gli avvisi
```

Sola lettura: controlla `APP_ENV`, `APP_URL` https, `APP_SECRET`, PHP e estensioni, `vendor/` senza strumenti di sviluppo, cartelle `storage`, permessi di `.env`, file di sviluppo, database, migrazioni, amministratore, listino, descrizioni, posta (trasporto `smtp`, host, mittente) e recapiti. **Non contatta SMTP né altri servizi e non stampa mai password.** Usato nella guida `docs/RELEASE_GUIDE.md` §6.

## Area amministrativa

- Indirizzo: `/admin` (sul container: http://localhost:8080/admin). Non compare nella navigazione pubblica e non è indicizzata.
- Pagine: Home, Richieste, Prenotazioni (+ nuova manuale), Calendario, Blocchi, Appartamenti, Listino, Storico, Export (CSV richieste e prenotazioni).
- Tutta l'area è protetta da tre guardie a livello di prefisso (vedi `app/routes_admin.php`): risposte private, autenticazione, CSRF. Le nuove rotte sotto `/admin` le ereditano automaticamente; regola: **le modifiche sono solo POST**.
- Il cambio password si fa solo da riga di comando e chiude le sessioni aperte.
- Conferma e rifiuto inviano l'email al cliente (se l'invio fallisce la decisione resta salvata e lo stato è visibile in Admin > Email). La cancellazione **non invia nulla da sola**: prepara una bozza modificabile che si invia solo con un'azione esplicita.
- Dal dettaglio di richiesta e prenotazione: link WhatsApp verso il cliente (testo modificabile prima dell'invio).

## Amministratore

C'è **un solo account**. Non esiste registrazione pubblica né recupero via email.

**Cambiare la password** (la cosa che si fa di solito): *Admin → Account → Cambia la password*. Servono la password attuale e la nuova due volte; dopo il cambio gli altri dispositivi collegati vengono disconnessi e questo no. *Esci da tutti i dispositivi* (stessa pagina) chiude le altre sessioni senza cambiare la password; la pagina mostra anche l'accesso riuscito precedente a questo e i tentativi falliti delle ultime 24 ore.

**Regole della password** (Account e comando): almeno 12 caratteri; rifiutata se è una password comune (anche con maiuscole, numeri o simboli aggiunti, o con caratteri "somiglianti" come `@` per `a`), se contiene il nome utente o il nome dell'agriturismo, se è troppo ripetitiva o uguale a quella attuale. Una frase lunga va benissimo. L'elenco delle password comuni è il file `app/Security/common-passwords.txt`.

### Creare l'account o recuperare la password

**Con SSH** (o in locale con accesso al database):

```bash
php bin/create-admin.php [nome-utente]
```

Crea l'account oppure, se esiste già, ne cambia nome utente e password. La password è letta da terminale (nascosta su Linux/macOS) o da due righe di standard input; **mai come argomento** (un secondo argomento viene rifiutato).

**Senza SSH** (hosting condiviso con solo phpMyAdmin) — vale sia per creare l'account sia per recuperare una password dimenticata, e non richiede la vecchia password:

1. Sul computer di chi ha il progetto (PHP oppure Docker; **non serve il database**), nella cartella del progetto:
   ```bash
   docker compose exec web php bin/create-admin.php nome-utente --print-sql > admin.sql
   ```
   (senza Docker: `php bin/create-admin.php nome-utente --print-sql > admin.sql`).
2. Scrivi la password due volte quando richiesto (le domande compaiono a video, il file contiene solo SQL).
3. Apri `admin.sql`: contiene tre istruzioni (aggiorna l'account se c'è, lo crea solo se la tabella è vuota, registra l'evento nello Storico) e **l'impronta (hash) della password, mai la password**.
4. In phpMyAdmin scegli il database del sito → scheda *SQL* → incolla il contenuto (oppure *Importa* il file) → *Esegui*.
5. Cancella `admin.sql` e accedi da `/admin`. Se l'account esisteva, le sessioni aperte si chiudono da sole.

Gli stessi controlli della pagina Account valgono per il comando: una password debole viene rifiutata **prima** di scrivere qualsiasi SQL. Se hai troppi tentativi falliti il login si blocca per 15 minuti per quell'indirizzo; per sbloccare subito: `DELETE FROM rate_limit_hits;`.

**Niente installer web**: non esiste una pagina che crea l'amministratore e non verrà aggiunta.

### Per chi sviluppa: chiedere di nuovo la password per un'azione delicata

`App\Security\ReauthGuard` ricorda per **5 minuti**, solo nella sessione corrente, che la persona ha riscritto la password. La conferma finisce con la sessione, con il cambio password e con "Esci da tutti i dispositivi"; gli errori hanno un limite di tentativi proprio (5 in 15 minuti) e contano tra gli accessi falliti. In un controller di `app/Http/Controllers/Admin`, all'inizio dell'azione:

```php
if ($redirect = $this->needsReauth('/admin/pagina-con-il-modulo')) {
    return $redirect;
}
```

La persona va alla pagina «Conferma la tua password» e poi torna a `/admin/pagina-con-il-modulo` (solo percorsi sotto `/admin`, nessun indirizzo esterno); l'azione non viene rieseguita da sola. Un modulo che chiede già la password attuale (come Account) può chiamare direttamente `ReauthGuard::confirm()`. Ogni rotta nuova va comunque nella matrice di `AdminAccessTest::REVIEWED_ADMIN_ROUTES`.

## Verifiche eseguite in Fase 1

```bash
# sintassi PHP
docker compose exec web sh -c 'for f in $(find app bin public templates -name "*.php"); do php -l "$f" >/dev/null || echo "FAIL $f"; done'
```

Test HTTP manuali con `curl` (vedi `docs/TEST_REPORT.md`). Nessuna suite automatica ancora: PHPUnit arriva in Fase 2A.

## Note hosting condiviso

- Document root su `public/` quando il pannello lo consente; altrimenti caricare il progetto nella root del sito: il `.htaccess` principale reindirizza tutto in `public/` e rende irraggiungibili `.env`, `app/`, `migrations/`, `storage/` ecc. Se `mod_rewrite` manca, il sito risponde 403 invece di esporre file.
- Le cartelle private (`storage/`, `app/`, `migrations/`, `bin/`, `templates/`, `content/`, `docs/`, `prompts/`, `tests/`, `docker/`) contengono un `.htaccess` che nega ogni accesso. È una **difesa parziale**: funziona solo se il server legge i `.htaccess`. Se l'hosting li ignora, queste cartelle sono raggiungibili: la difesa vera è puntare il document root a `public/`, e la verifica va fatta con richieste HTTP reali (`docs/RELEASE_GUIDE.md`; controllo automatico in "Stato del sistema" nel prompt 26 e verifica dopo la pubblicazione nel prompt 32).
- Con server LiteSpeed, l'invio delle email dopo la risposta funziona come con PHP-FPM (`litespeed_finish_request`).
- `storage/logs/` e `storage/sessions/` devono essere scrivibili da PHP.
- Variabili d'ambiente reali del pannello hosting, se presenti, prevalgono su `.env`.
