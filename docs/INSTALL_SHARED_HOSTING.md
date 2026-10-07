# Installazione su hosting condiviso

Guida per mettere online il sito su un normale hosting Linux con Apache, PHP e MySQL/MariaDB. **Non fa deploy e non contatta nessun provider**: sono istruzioni da eseguire a mano, con le autorizzazioni del titolare.

> **Cosa è stato verificato.** I passaggi con il segno ✔ sono stati eseguiti realmente in ambiente Docker (PHP 8.2, Apache 2.4, MariaDB 10.11) il 2026-10-06. **Nessun hosting reale è stato provato**: versione di PHP 8.1, MySQL, PHP-FPM, permessi e limiti del provider vanno controllati. Le voci ⚠ richiedono un provider, un dominio o credenziali esterne.

## 1. Requisiti

| Cosa | Minimo | Note |
|---|---|---|
| PHP | 8.1 o superiore | provato solo con 8.2 |
| Estensioni PHP | `pdo_mysql`, `mbstring`, `ctype`, `json`, `session`, `openssl`, `filter`, `hash` | quasi sempre già attive; `openssl` serve per SMTP con TLS |
| Database | MySQL 5.7+ / MariaDB 10.3+, InnoDB, `utf8mb4` | i vincoli `CHECK` sono applicati da MariaDB 10.2+ e MySQL 8.0.16+ (con MySQL 5.7 li ignora, l'applicazione valida comunque); provato solo MariaDB 10.11 |
| Web server | Apache con `mod_rewrite` e `.htaccess` abilitati (`AllowOverride All`) | senza `mod_rewrite` il sito risponde 403 invece di esporre file |
| HTTPS | certificato valido (di solito gratuito, dal pannello) | indispensabile prima di pubblicare |
| Posta | un account SMTP (host, porta, utente, password) | senza, le email restano in coda e le richieste si salvano comunque |
| Facoltativi | PHP-FPM (invio email dopo la risposta), cron (ripetizione degli invii falliti), accesso SSH | tutto funziona anche senza |

## 2. Preparare i file (sul proprio computer)

1. Ottieni il codice (clone o archivio del repository).
2. Genera la cartella `vendor/` **senza** strumenti di sviluppo ✔ (684 KB, solo PHPMailer):

   ```bash
   composer install --no-dev --optimize-autoloader
   ```

   Se non hai Composer in locale puoi usare Docker: `docker compose exec web composer install --no-dev --optimize-autoloader` (poi ripristina gli strumenti di sviluppo con `composer install` quando serve).
3. **Carica solo questi elementi**: `public/`, `app/`, `templates/`, `content/`, `migrations/`, `bin/`, `storage/` (le cartelle vuote `logs`, `sessions`, `mail`), `vendor/` e il file `.htaccess` della radice.
4. **Non caricare**: `.git/`, `tests/`, `docs/`, `docker/`, `docker-compose.yml`, `prompts/`, `node_modules/`, `.phpunit.cache/`, il tuo `.env` locale.

## 3. Document root

- **Caso A, la radice del sito può puntare a `public/`** (consigliato): imposta nel pannello la *document root* su `…/public`. Tutto il resto resta fuori dal web.
- **Caso B, la radice non si può cambiare**: carica tutto nella radice del sito. Il `.htaccess` della radice rimanda ogni richiesta dentro `public/` e rende irraggiungibili `.env`, `app/`, `migrations/`, `storage/`, `vendor/`, `bin/` ✔ (è la configurazione dell'ambiente Docker di sviluppo, quindi è quella più provata). Dopo l'installazione esegui la prova del §10.
- Sottocartella (`https://dominio.it/sito`): il codice la supporta tramite il percorso di `APP_URL`, ma **non è stata provata**: preferisci un dominio o sottodominio.

## 4. Database ⚠ (accesso al pannello dell'hosting)

1. Dal pannello crea un database **vuoto** con collation `utf8mb4_unicode_ci` e un utente con tutti i privilegi su quel database. Annota host, nome, utente e password (non vanno scritti in nessun file del repository).
2. Crea le tabelle in **uno** dei due modi:
   - **Con SSH** ✔: `php bin/migrate.php` (poi `php bin/migrate.php --status` mostra `[x]` per ogni migrazione).
   - **Solo con phpMyAdmin** ✔: scheda *Importa*, set di caratteri `utf8mb4`, importa **in ordine** `migrations/0001_initial_schema.sql`, `0002_seed_apartments.sql`, `0003_pricing.sql`, `0004_email_outbox.sql`, `0005_apartment_amenities.sql`, `0006_submission_key.sql`, `0007_data_constraints.sql`. Con phpMyAdmin non c'è il controllo dei dati che `bin/migrate.php` fa prima della `0007`: se la `0007` dà errore, un dato esistente viola un vincolo (vedi `docs/COMMANDS.md`, "Migrazione interrotta a metà"). Ogni file si registra da solo nella tabella `schema_migrations`, quindi `bin/migrate.php` li riconoscerà come già applicati se in futuro lo userai (verificato: `--status` → tutti `[x]`, "Nothing to migrate").
3. Controlla: le tabelle sono 12 (`admin`, `apartments`, `apartment_translations`, `audit_log`, `availability_blocks`, `booking_requests`, `bookings`, `email_outbox`, `pricing_rules`, `rate_limit_hits`, `schema_migrations`, `seasonal_rates`) e `apartments` contiene i sei appartamenti (Margherita, Girasole, Rosa, Mimosa, Ciclamino, Viola). Le migrazioni non inseriscono nessun prezzo.

Importa le migrazioni sempre **prima** di mettere online una versione nuova del codice che ne contiene di nuove.

## 5. Configurazione

Crea il file `.env` nella **radice del progetto** (sopra `public/`, mai dentro) oppure imposta le stesse variabili dal pannello dell'hosting (le variabili reali hanno la precedenza sul file). Parti da `.env.example`. Valori di produzione:

```ini
APP_ENV=production
APP_DEBUG=false                  # in produzione il debug è comunque ignorato
APP_URL=https://www.tuodominio.it   # senza barra finale; deve essere https
APP_TIMEZONE=Europe/Rome
APP_SECRET=                      # stringa casuale lunga, vedi sotto

DB_HOST=…  DB_PORT=3306  DB_NAME=…  DB_USER=…  DB_PASSWORD=…

MAIL_TRANSPORT=smtp
SMTP_HOST=…  SMTP_PORT=587  SMTP_ENCRYPTION=tls   # oppure 465 con ssl
SMTP_USERNAME=…  SMTP_PASSWORD=…
MAIL_FROM_ADDRESS=…  MAIL_FROM_NAME="Agriturismo La Volta"
MAIL_ADMIN_ADDRESS=…             # chi riceve le nuove richieste

PUBLIC_PHONE=…  PUBLIC_EMAIL=…  PUBLIC_ADDRESS=…   # mostrati solo se compilati
WHATSAPP_NUMBER=…                # cifre con prefisso, senza +; vuoto = nessun pulsante WhatsApp
```

- Genera `APP_SECRET` con `php -r "echo bin2hex(random_bytes(32));"` ✔. Se manca, viene derivato dalle credenziali del database (funziona, ma è meglio impostarlo). Cambiarlo invalida i moduli aperti, non i dati.
- `MAIL_TRANSPORT=log` è solo per lo sviluppo e viene **rifiutato in produzione**.
- `HSTS_MAX_AGE` (predefinito 180 giorni, attivo solo con `APP_URL` https): lascia il valore predefinito solo quando HTTPS funziona su tutto il dominio, perché i browser poi rifiutano HTTP; `0` lo disattiva.
- `DATA_RETENTION_MONTHS`: lascialo vuoto finché il titolare non decide il periodo di conservazione dei dati (vedi `docs/OPERATIONS.md`).
- Le tre cartelle `storage/logs`, `storage/sessions`, `storage/mail` devono essere **scrivibili da PHP** (di solito permessi 755 o 775). Se `sessions` non è scrivibile PHP usa la cartella predefinita del sistema; se `logs` non lo è, gli errori vanno nel log di PHP.

## 6. Creare l'amministratore

L'account è uno solo; non esiste registrazione. Scegli una password lunga (almeno 12 caratteri) e non riutilizzata.

**Con SSH** ✔ (la password è chiesta due volte e non compare nei comandi):

```bash
php bin/create-admin.php admin
```

Lo stesso comando, se l'account esiste già, ne cambia nome utente e password e **chiude tutte le sessioni aperte**.

**Senza SSH** ✔ (phpMyAdmin): 1) sul tuo computer genera l'hash della password, per esempio `php -r "echo password_hash('LA-TUA-PASSWORD-LUNGA', PASSWORD_DEFAULT);"` (o con Docker: `docker compose exec web php -r "…"`); il risultato inizia con `$2y$`. 2) In phpMyAdmin, scheda *SQL* del database:

```sql
INSERT INTO admin (username, password_hash) VALUES ('admin', 'INCOLLA-QUI-L-HASH');
-- se l'account esiste già:
-- UPDATE admin SET password_hash = 'INCOLLA-QUI-L-HASH' WHERE username = 'admin';
```

Verificato: un hash inserito così permette l'accesso (password sbagliata → rifiutata, giusta → pannello). Non conservare la password in chiaro nella cronologia della shell o in file; la lunghezza minima di 12 caratteri è imposta solo dal comando `create-admin`, quindi in questo caso rispettala tu.

Poi accedi da `https://tuodominio.it/admin`.

## 7. Posta (SMTP) ⚠

1. Inserisci le variabili del §5 con i dati dell'account di posta fornito dal provider.
2. Fai una richiesta di prova dal sito (con dati inventati) e controlla **Admin → Email**: lo stato deve passare a *inviata*. Se resta in coda o fallisce, il motivo è indicato (senza password) e c'è il pulsante *Riprova invio*. La richiesta è comunque salvata.
3. Controlla anche la cartella *spam* e l'indirizzo del mittente: `MAIL_FROM_ADDRESS` dovrebbe appartenere al dominio dell'account SMTP.
4. ⚠ Per una buona consegna servono record **SPF e DKIM** del dominio: richiedono l'accesso al DNS e **non vanno modificati senza l'autorizzazione del titolare**; l'agente di sviluppo non li ha toccati. La consegna reale non è stata verificata (mancano le credenziali).

Cron facoltativo ⚠ (se l'hosting lo offre; riprova gli invii falliti ogni 10 minuti):

```text
*/10 * * * * /usr/bin/php /percorso/del/sito/bin/send-queued-mail.php
```

## 8. HTTPS ⚠

Attiva il certificato dal pannello, imposta `APP_URL=https://…` e controlla che tutte le pagine si aprano in https. Con `APP_URL` https il cookie di sessione dell'admin è `Secure` e parte l'HSTS. Il reindirizzamento da http a https si configura dal pannello o con una regola `.htaccess` del provider; non è incluso nel progetto per non interferire con i diversi ambienti.

## 9. Primi contenuti

Dall'admin: **Appartamenti** (capienza, camere, orari, descrizione, regole e **servizi** — una voce per riga — in IT ed EN, limiti per bambini e animali), **Listino** (tariffe stagionali e regole per adulti, bambini, animali, supplementi, soggiorno minimo). Finché non c'è un listino, le richieste sono accettate come "prezzo da confermare". I testi di *L'agriturismo* e *Dintorni* e le foto vanno forniti dal titolare (`docs/MISSING_DATA.md`, `docs/IMAGES.md`): oggi le pagine mostrano un avviso e segnaposto.

## 10. Prova di fumo dopo l'installazione

Dal tuo computer (sostituisci il dominio) o dal browser:

```bash
curl -sI https://www.tuodominio.it/            | head -1   # 200
curl -sI https://www.tuodominio.it/en          | head -1   # 200
curl -sI https://www.tuodominio.it/robots.txt  | head -1   # 200
curl -sI https://www.tuodominio.it/sitemap.xml | head -1   # 200
curl -sI https://www.tuodominio.it/admin       | head -1   # 303 verso /admin/login
# Questi devono dare 403 o 404, MAI 200:
for p in /.env /app/Site/Text.php /storage/logs/ /vendor/autoload.php /migrations/0001_initial_schema.sql /bin/migrate.php /composer.json; do
  printf "%s " $p; curl -s -o /dev/null -w "%{http_code}\n" https://www.tuodominio.it$p; done
```

Poi: accedi all'admin; invia una richiesta di prova e controlla la notifica; conferma e poi cancella la richiesta di prova dall'admin (Prenotazioni → Cancella) in modo che le date tornino libere; controlla `https://…/` con le intestazioni (`curl -sI`): devono esserci `Content-Security-Policy`, `X-Content-Type-Options`, `Permissions-Policy` e, in https, `Strict-Transport-Security`; non deve esserci `X-Powered-By`. Percorri infine `docs/MANUAL_CHECKLIST.md`.

## 11. Cosa richiede un provider o un'autorizzazione esterna

| Passaggio | Chi | Perché |
|---|---|---|
| Scelta dell'hosting, dominio e certificato HTTPS | titolare | acquisto/servizio esterno |
| Creazione di database e utente | titolare (pannello) | accesso al pannello |
| Account SMTP e record SPF/DKIM | titolare/provider di posta | credenziali e DNS: non vanno toccati senza autorizzazione |
| Caricamento dei file e `vendor/` | chi pubblica | deploy: non eseguito dall'agente |
| Redirect http → https, cron, PHP-FPM | provider | impostazioni del pannello |

Per la sequenza completa di pubblicazione (DNS da richiedere, prove prima del cambio, HSTS graduale, rollback) vedi `docs/RELEASE_GUIDE.md`; per backup, ripristino, aggiornamenti e problemi frequenti `docs/OPERATIONS.md`. Dopo l'installazione eseguire `php bin/check-production.php --strict`.
