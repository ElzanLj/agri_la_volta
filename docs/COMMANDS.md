# Comandi verificati del progetto

> Inserire solo comandi realmente presenti o verificati nel repository.

## Ambiente rilevato

Verificato il 2026-10-05.

- OS/runtime host: Windows 11 Pro, Git Bash / PowerShell
- Docker: Docker Compose v5.4.0
- PHP: 8.2.34 nel container `web` (`docker/php/Dockerfile`: `php:8.2-apache` + `pdo_mysql`, `mod_rewrite`, `mod_headers`). PHP non installato sull'host.
- Composer: non usato in questa fase (autoloader interno, vedi `docs/DECISIONS.md`)
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
