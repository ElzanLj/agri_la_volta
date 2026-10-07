# Guida alla pubblicazione (release guide)

> **Questo documento non autorizza e non esegue nessuna pubblicazione.** Contiene la sequenza da seguire **dopo** che il titolare ha autorizzato esplicitamente ogni passaggio che coinvolge un servizio esterno. Nulla di quanto descritto qui è stato eseguito su hosting, dominio, DNS o posta reali (vedi §14).

Stato: **PREPARATA, NON ESEGUITA** — 2026-10-07 (vecchio prompt 14, oggi `31_RELEASE_PREP_NO_DEPLOY`: sarà aggiornata nel prompt 31 dopo le fasi 15–30).

Indirizzo canonico del sito: **`https://www.agriturismolavolta.com`** (scelta del titolare). Casella di posta indicata dalla SPEC: `info@agriturismolavolta.com`. Entrambi compaiono qui solo come testo negli esempi: nessun servizio è stato contattato.

Documenti collegati: installazione dettagliata `docs/INSTALL_SHARED_HOSTING.md`, backup e manutenzione `docs/OPERATIONS.md`, prove manuali `docs/MANUAL_CHECKLIST.md`, elenco da spuntare `docs/DELIVERY_CHECKLIST.md`.

## 1. Prerequisiti (da avere prima di cominciare)

| # | Cosa | Chi | Dove si verifica |
|---|---|---|---|
| 1 | Autorizzazione scritta del titolare a pubblicare (punti A1–A8 del §2) | titolare | — |
| 2 | Hosting Linux condiviso con PHP 8.1+, `pdo_mysql`, Apache `mod_rewrite`/`.htaccess`, MySQL/MariaDB, certificato HTTPS | titolare | `docs/INSTALL_SHARED_HOSTING.md` §1 |
| 3 | Accesso al pannello del provider di dominio (record DNS **web**) | titolare | §7 |
| 4 | Parametri SMTP della casella (host, porta, cifratura, utente, password) forniti dal provider di posta | titolare / provider di posta | §8 |
| 5 | Contenuti: listino, descrizioni e servizi degli appartamenti, testi di *L'agriturismo* e *Dintorni*, foto con provenienza verificata (o segnaposto accettati), recapiti, numero WhatsApp | titolare | `docs/MISSING_DATA.md` |
| 6 | Testi di privacy e cookie verificati; periodo di conservazione dei dati deciso | titolare / consulente | `docs/MISSING_DATA.md` |
| 7 | Prove manuali eseguite (tastiera, mobile, desktop, IT/EN) | titolare | `docs/MANUAL_CHECKLIST.md` |
| 8 | Il pacchetto del sito (§3) e un backup dell'eventuale sito attuale | chi pubblica | §3, §12 |

Senza i punti 5 e 6 il sito si può installare ma **non è pronto per il pubblico**: pagine vuote, prezzi "da confermare", testi legali in bozza.

## 2. Punti di autorizzazione

Nessuno di questi passaggi va eseguito senza un "sì" esplicito del titolare. L'agente di sviluppo non li ha eseguiti.

| ID | Azione | Effetto | Reversibile? |
|---|---|---|---|
| A1 | Acquistare o attivare l'hosting e un certificato HTTPS | costo, nuovo servizio | in genere sì (disdetta) |
| A2 | Creare database e utente sull'hosting; caricare i file | nuovo sistema pronto ma non raggiungibile dal dominio | sì |
| A3 | Inserire le credenziali SMTP nella configurazione dell'hosting | il sito inizia a inviare email reali | sì (si svuotano) |
| A4 | Modificare i record DNS **web** del dominio (§7) | il dominio punta al nuovo sito | sì, ma con tempi di propagazione (TTL) |
| A5 | Modificare record di posta (MX, SPF, DKIM, DMARC) | può far arrivare o respingere la posta del dominio | **a rischio**: non previsto da questa guida salvo conferma del provider di posta (§7) |
| A6 | Attivare l'HSTS lungo (`HSTS_MAX_AGE` predefinito) | i browser rifiutano HTTP per mesi | **no** per i browser che l'hanno già visto: partire con un valore breve (§11) |
| A7 | Importare dati reali (richieste, prenotazioni) da un altro sistema | dati personali nel database | solo con backup |
| A8 | Eliminare dati o file esistenti sull'hosting o sul dominio | perdita di dati | no |

## 3. Preparare il pacchetto (sul proprio computer, senza toccare nulla di esterno)

1. **Fissare la versione**: lavorare su un commit preciso (o un tag) con il working tree pulito; annotare l'hash. Eseguire la suite: `docker compose exec web composer test` → tutti i test devono passare (a oggi 791 test; vedi `docs/TEST_REPORT.md`).
2. **Generare le dipendenze di produzione** ✔ (verificato: solo PHPMailer, 684 KB):

   ```bash
   composer install --no-dev --optimize-autoloader
   ```

3. **Elenco dei file da caricare** ✔ (verificato in una copia pulita): `public/`, `app/`, `templates/`, `content/`, `migrations/`, `bin/`, `storage/` (cartelle vuote), `vendor/`, `.htaccess` della radice. **Non** caricare: `.git/`, `tests/`, `docs/`, `docker/`, `docker-compose.yml`, `prompts/`, `node_modules/`, `.phpunit.cache/`, il proprio `.env` locale.
4. **Creare un archivio** del pacchetto (per tenerne una copia e poterlo ricaricare in caso di rollback):

   ```bash
   tar czf lavolta-AAAAMMGG-<hash>.tar.gz public app templates content migrations bin storage vendor .htaccess
   ```

   Conservare l'archivio **di ogni versione pubblicata** (serve al §12).
5. **Preparare il file `.env` di produzione** (non nel pacchetto, non nel repository). Modello completo, con valori **di esempio**:

   ```ini
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://www.agriturismolavolta.com
   APP_TIMEZONE=Europe/Rome
   APP_SECRET=<stringa casuale di almeno 32 caratteri>

   DB_HOST=<host del database dell'hosting>
   DB_PORT=3306
   DB_NAME=<nome>
   DB_USER=<utente>
   DB_PASSWORD=<password>

   MAIL_TRANSPORT=smtp
   SMTP_HOST=<host SMTP del provider>
   SMTP_PORT=587
   SMTP_ENCRYPTION=tls
   SMTP_USERNAME=<utente SMTP>
   SMTP_PASSWORD=<password SMTP>
   MAIL_FROM_ADDRESS=info@agriturismolavolta.com
   MAIL_FROM_NAME="Agriturismo La Volta"
   MAIL_ADMIN_ADDRESS=info@agriturismolavolta.com

   PUBLIC_PHONE=<telefono ufficiale>
   PUBLIC_EMAIL=info@agriturismolavolta.com
   PUBLIC_ADDRESS=<indirizzo ufficiale>
   WHATSAPP_NUMBER=<cifre con prefisso, senza +>

   HSTS_MAX_AGE=300            # all'inizio breve, vedi §11
   DATA_RETENTION_MONTHS=      # lasciare vuoto finché il titolare non decide
   ```

   `APP_SECRET`: `php -r "echo bin2hex(random_bytes(32));"`. Le password non vanno in chat, email o repository: si inseriscono direttamente nel pannello dell'hosting o nel file sul server. `MAIL_TRANSPORT=log` è vietato in produzione.

## 4. Hosting e database ⚠ A1, A2

1. Dal pannello creare un database **vuoto** (`utf8mb4_unicode_ci`) e un utente con privilegi solo su quel database. Non importare dati reali di altro tipo (A7).
2. Importare **in ordine** `migrations/0001_initial_schema.sql` … `0005_apartment_amenities.sql` (phpMyAdmin → *Importa*, `utf8mb4`) oppure `php bin/migrate.php` se c'è SSH ✔ (verificato: 12 tabelle, 6 appartamenti, nessun prezzo).
3. Creare l'amministratore: `php bin/create-admin.php admin` oppure l'inserimento SQL con hash generato in locale ✔ (`docs/INSTALL_SHARED_HOSTING.md` §6).
4. **Non ripristinare mai** un backup di sviluppo o di test sul database di produzione.

## 5. Caricare i file e impostare i permessi ⚠ A2

1. **Document root**: preferibilmente `…/public`. Se il pannello non lo consente, caricare tutto nella radice del sito (il `.htaccess` della radice rimanda a `public/` e nasconde `.env`, `app/`, `storage/`, `vendor/`, `bin/`, `migrations/` ✔).
2. Permessi (valori tipici; se PHP gira con lo stesso utente dei file bastano questi):

   | Elemento | Permessi | Note |
   |---|---|---|
   | cartelle | `755` | `find . -type d -exec chmod 755 {} \;` |
   | file | `644` | `find . -type f -exec chmod 644 {} \;` |
   | `.env` (se usato) | `640` o `600` | non leggibile da altri utenti; `bin/check-production.php` lo controlla |
   | `storage/logs`, `storage/sessions` | scrivibili da PHP (`755`, oppure `775` se il gruppo è quello di PHP) | senza, le sessioni dell'admin non si salvano e gli errori non si registrano |
   | `bin/*.php` | `644` | si lanciano con `php bin/…`, non servono permessi di esecuzione |

3. Creare `.env` sul server (o impostare le variabili dal pannello) con i valori del §3.5.
4. Verificare che **non** siano raggiungibili dal browser: `/.env`, `/app/…`, `/storage/…`, `/vendor/…`, `/migrations/…`, `/bin/…`, `/composer.json` (devono dare 403 o 404 ✔ in simulazione).

## 6. Controllo preliminare automatico ✔

Sull'hosting, dopo aver caricato i file e creato database e `.env`:

```bash
php bin/check-production.php --strict
```

Lo strumento è **di sola lettura**: controlla configurazione, file, cartelle, database, migrazioni, amministratore, listino, descrizioni, impostazioni di posta e recapiti; **non contatta il server SMTP né altri servizi**, non scrive nulla e non stampa mai password. Esito atteso: *"Pronto dal punto di vista tecnico"*, exit code 0. Gli **errori** (`[ERRORE]`) vanno corretti prima di procedere; gli **avvisi** (`[ATTENZIONE]`) segnalano cose da decidere (per esempio recapiti o descrizioni ancora vuoti). Senza accesso SSH, i controlli corrispondenti si fanno a mano con la prova di fumo del §10.

Provato in una copia pulita con impostazioni di produzione fittizie: 27 controlli superati, 0 errori, 0 avvisi; con le impostazioni di sviluppo: errore su `APP_ENV`.

## 7. Dominio e DNS ⚠ A4 (e A5)

**Indirizzo canonico: `https://www.agriturismolavolta.com`.** Il dominio senza `www` deve reindirizzare con un **301** al `www`, e `http` a `https`.

### 7.1 Richiesta da inviare a chi gestisce il dominio (modello, **non applicato**)

Compilare i segnaposto con i valori forniti dall'hosting scelto:

| Tipo | Nome | Valore | TTL | Note |
|---|---|---|---|---|
| `A` (oppure `CNAME`) | `www` | `<indirizzo IP dell'hosting>` (o il nome host indicato dall'hosting) | 300 prima del cambio, poi 3600 | punta il sito |
| `A` | `@` (dominio senza www) | `<indirizzo IP dell'hosting>` | 300 poi 3600 | serve solo per poter reindirizzare al `www` |
| `AAAA` | `www`, `@` | `<indirizzo IPv6>` | idem | solo se l'hosting lo fornisce |

- **24–48 ore prima** del cambio chiedere di **abbassare il TTL** dei record da modificare a 300 secondi: permette un rollback rapido.
- **Non modificare**: `NS` (nameserver), `MX`, `TXT` di **SPF**, **DKIM**, **DMARC** e ogni altro record di posta. Il sito non ne ha bisogno: invia le email **autenticandosi sul server SMTP del provider di posta** (`SMTP_HOST`), quindi i messaggi partono dai server del provider, che ha già i propri SPF/DKIM per `info@agriturismolavolta.com`. Verificarlo con il provider di posta: *se* il provider chiedesse di aggiungere un record per autorizzare l'invio, quella è una richiesta A5, da autorizzare a parte.
- Non trasferire il dominio e non cambiare provider.

### 7.2 Reindirizzamenti (da configurare sull'hosting) — **non provato**

Se il pannello offre i reindirizzamenti, usarli. Altrimenti, regola per il `.htaccess` della **radice del sito** (da mettere prima della regola che porta a `public/`), adattandola all'hosting (se il certificato è gestito da un proxy, il controllo `HTTPS` può richiedere `X-Forwarded-Proto`):

```apache
RewriteEngine On
# dominio senza www e/o http -> https://www
RewriteCond %{HTTP_HOST} ^agriturismolavolta\.com$ [NC,OR]
RewriteCond %{HTTPS} !=on
RewriteRule ^ https://www.agriturismolavolta.com%{REQUEST_URI} [L,R=301]
```

Questa regola non è nel repository perché dipende dall'hosting; non è stata eseguita né provata. Dopo averla attivata, verificare con `curl -sI http://agriturismolavolta.com/` (301 verso `https://www…`).

## 8. Posta (SMTP) ⚠ A3

1. Il provider di posta fornisce host, porta (587 `tls` oppure 465 `ssl`), utente e password della casella che invia (di norma `info@agriturismolavolta.com`).
2. Inserirli **solo** in `.env`/pannello (§3.5). `MAIL_FROM_ADDRESS` deve appartenere al dominio dell'account SMTP.
3. Non serve alcuna modifica DNS (§7.1). La consegna reale non è mai stata provata: si prova al §10.
4. Se l'invio non funziona la richiesta resta salvata e *Admin → Email* mostra il motivo e il pulsante *Riprova invio*; il cron (`bin/send-queued-mail.php`) è facoltativo.

## 9. Provare PRIMA del cambio DNS

Attenzione: `APP_URL` deve coincidere **esattamente** con l'indirizzo con cui si usa il sito, perché i moduli controllano l'`Origin` della richiesta. Provando da un indirizzo provvisorio dell'hosting con `APP_URL=https://www.agriturismolavolta.com`, i moduli darebbero errore 403. Per provare con l'indirizzo vero **prima** di cambiare il DNS:

- aggiungere sul **proprio computer** una riga nel file `hosts` (`<IP dell'hosting>  www.agriturismolavolta.com`), oppure
- usare un sottodominio provvisorio **con un `APP_URL` provvisorio**, ricordandosi di rimettere quello definitivo e di rieseguire il controllo del §6.

Il certificato HTTPS deve essere già valido per quel nome. Rimuovere la riga di `hosts` a prova finita.

## 10. Prova di fumo dopo la pubblicazione (da eseguire in futuro)

Dal proprio computer, sostituendo il dominio se diverso:

```bash
curl -sI https://www.agriturismolavolta.com/            | head -1   # 200
curl -sI https://www.agriturismolavolta.com/en          | head -1   # 200
curl -sI https://www.agriturismolavolta.com/robots.txt  | head -1   # 200
curl -sI https://www.agriturismolavolta.com/sitemap.xml | head -1   # 200
curl -sI https://www.agriturismolavolta.com/admin       | head -1   # 303 verso /admin/login
curl -sI http://agriturismolavolta.com/                 | head -2   # 301 verso https://www...
curl -sI https://www.agriturismolavolta.com/ | grep -iE "strict-transport|content-security|x-content-type|permissions-policy"   # presenti
curl -sI https://www.agriturismolavolta.com/ | grep -ciE "x-powered-by|set-cookie"                                              # 0
for p in /.env /app/Site/Text.php /storage/logs/ /vendor/autoload.php /migrations/0001_initial_schema.sql /bin/migrate.php /composer.json; do
  printf "%s " $p; curl -s -o /dev/null -w "%{http_code}\n" https://www.agriturismolavolta.com$p; done      # 403/404, mai 200
```

Poi, a mano (voci complete in `docs/MANUAL_CHECKLIST.md`):

1. Aprire home e almeno un appartamento in italiano e in inglese: testi, canonical sul dominio `www`, nessun segnaposto non voluto.
2. **Accedere all'admin** (`/admin`): il cookie di sessione deve essere `Secure`; cambiare la password provvisoria se è stata usata una password di prova.
3. **Richiesta di prova** (con dati inventati) dal sito: arriva la notifica alla casella del gestore; in *Admin → Email* lo stato è *inviata*. Confermarla e poi **cancellare la prenotazione di prova** così le date tornano libere.
4. Rieseguire `php bin/check-production.php --strict`.
5. Controllare `storage/logs/`: nessun dato personale né password (ci sono solo eventi).
6. Verificare un backup: esportare il database e **ripristinarlo in un database di prova** (`docs/OPERATIONS.md` §3).
7. Inviare la sitemap ai motori di ricerca **solo se il titolare lo autorizza** (richiede un account esterno: A-extra).

## 11. Sequenza di pubblicazione consigliata (una volta autorizzata)

1. **T-48h**: chiedere l'abbassamento del TTL (§7.1); preparare pacchetto, `.env`, database vuoto e importato (§3–§5).
2. **T-24h**: caricare i file, creare l'admin, eseguire il controllo del §6, provare con il file `hosts` (§9); eseguire le prove manuali.
3. **Backup** del sito attuale (se esiste) e annotazione dei valori DNS attuali (per il rollback).
4. **T0**: cambiare i record `A`/`CNAME` del §7.1 (A4); attivare i reindirizzamenti (§7.2).
5. Subito dopo: prova di fumo del §10.
6. **HSTS graduale**: tenere `HSTS_MAX_AGE=300` per i primi giorni; se tutto va bene, portarlo a `86400`, poi al valore predefinito di 180 giorni (`15552000`). Una volta che un browser ha visto un HSTS lungo non c'è modo di annullarlo (A6).
7. Nei primi giorni: controllare ogni giorno *Admin → Email* e i log; fare un backup giornaliero.

## 12. Rollback

| Quando | Cosa fare |
|---|---|
| **Prima del cambio DNS** | niente da annullare per il pubblico: il vecchio sito non è stato toccato. Correggere e riprovare con `hosts` |
| **Dopo il cambio DNS, il nuovo sito ha problemi gravi** | 1) ripristinare i valori DNS annotati (§11.3): con il TTL basso torna operativo in pochi minuti; 2) mentre si propaga, attivare la pagina di manutenzione (sotto); 3) analizzare i log e correggere; 4) riprovare |
| **Una nuova versione del codice funziona male** | ricaricare l'**archivio della versione precedente** (§3.4) sopra i file attuali **senza toccare `.env` e `storage/`**; se la nuova versione conteneva migrazioni, ripristinare **anche** il backup del database fatto subito prima (le migrazioni vanno solo in avanti) |
| **Errore di dati o migrazione** | ripristinare l'ultimo backup nel database di produzione (meglio in un database nuovo e poi puntare `DB_NAME` lì: `docs/OPERATIONS.md` §3) |
| **Credenziali compromesse** | cambiare password dell'admin (`docs/OPERATIONS.md` §5), password SMTP e `APP_SECRET`, verificare i log |

**Cosa il rollback non annulla:** le email già inviate; le richieste ricevute tra il cambio e il rollback (esportarle in CSV o conservare un backup *prima* di tornare indietro, e reinserirle a mano se servono); l'HSTS già memorizzato dai browser.

**Pagina di manutenzione** (da attivare solo durante il rollback o gli aggiornamenti; **non provata**): creare a mano `public/manutenzione.html` con un messaggio e aggiungere in cima al `.htaccess` della radice:

```apache
ErrorDocument 503 /manutenzione.html
RewriteEngine On
RewriteCond %{REQUEST_URI} !^/manutenzione\.html$
RewriteCond %{REMOTE_ADDR} !=<IL-TUO-IP>
RewriteRule ^ - [R=503,L]
```

## 13. Dopo la pubblicazione

- Backup del database a frequenza fissa e fuori dall'hosting (`docs/OPERATIONS.md` §2); provare il ripristino.
- Decidere e impostare `DATA_RETENTION_MONTHS`, poi pianificare `php bin/privacy.php purge` (con `--apply` solo dopo una simulazione).
- Aggiornare `docs/MISSING_DATA.md` e `docs/SESSION_STATE.md` con ciò che è stato fornito.
- Ricordare che il layout cambierà con il nuovo design del titolare: dopo ogni cambio rieseguire la suite di test (`PublicSeoTest`, `PublicPagesTest`, `ContrastTest`) e il controllo del §6.

## 14. Cosa NON è stato eseguito

Questa guida è stata **scritta e verificata solo in locale**. Non è stato fatto nessuno dei seguenti passaggi, né alcun accesso ai relativi servizi:

| Cosa | Stato |
|---|---|
| Acquisto o attivazione di hosting, dominio o certificato | **non eseguito** |
| Caricamento di file su un hosting; creazione di database o utenti reali | **non eseguito** |
| Modifiche DNS (record web) o nameserver | **non eseguite** |
| Modifiche a MX, SPF, DKIM, DMARC o alla configurazione della posta | **non eseguite** |
| Connessione al server SMTP reale; invio di email reali | **non eseguiti** (nessuna credenziale) |
| Importazione di dati reali | **non eseguita** |
| Reindirizzamento 301 `non-www → www` e `http → https` (§7.2), pagina di manutenzione (§12) | **non provati** (dipendono dall'hosting) |
| Prove manuali di accessibilità, mobile, desktop | **non eseguite** (`docs/MANUAL_CHECKLIST.md`) |
| Installazione su un hosting reale, PHP 8.1, MySQL | **non eseguite** (provato solo PHP 8.2 e MariaDB 10.11 in Docker) |

Verificato in locale: pacchetto pulito con `vendor/` di produzione; import da zero delle migrazioni; creazione dell'admin; backup e ripristino; **simulazione di produzione** con impostazioni fittizie (`APP_ENV=production`, `APP_URL` con il dominio canonico, SMTP verso una porta locale chiusa): controllo preliminare superato, pagine, intestazioni (incluso HSTS), canonical e sitemap sul dominio `www`, cookie di sessione `Secure`, file riservati non raggiungibili, e una richiesta salvata anche con il server di posta irraggiungibile (consegna fallita registrata nei log senza dati personali). Dettagli in `docs/TEST_REPORT.md`.
