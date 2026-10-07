<<<<<<< HEAD
# Agriturismo La Volta — sito con richieste di disponibilità

Sito web in **PHP + MySQL/MariaDB** per un agriturismo con sei appartamenti, pensato per un normale **hosting Linux condiviso**. I visitatori consultano gli appartamenti (italiano e inglese) e inviano una **richiesta di disponibilità**; il gestore la conferma o la rifiuta dall'area amministrativa. **Non ci sono pagamenti online né account per gli ospiti.**
=======
# Agriturismo La Volta 
>>>>>>> 9455058276e776fa5332d8206796d7a428093f04

> Stato: sviluppo completato per la parte automatizzabile, **non ancora pubblicato**. Mancano dati e contenuti del titolare e le prove manuali: vedi [Stato e limiti](#stato-e-limiti).

## Cosa fa

- **Sito pubblico** IT/EN: home, agriturismo, appartamenti (una pagina indicizzabile per ciascuno), dintorni, contatti, privacy, cookie.
- **Richiesta di disponibilità** a passi, senza JavaScript: date e ospiti → appartamenti disponibili con prezzo → dati → riepilogo e consenso privacy → "Richiesta ricevuta". Il prezzo è sempre calcolato dal server. Una richiesta non è una prenotazione.
- **Area amministrativa** (`/admin`, un solo account): richieste, conferma/rifiuto, prenotazioni (anche da altri canali), blocchi di date, calendario, appartamenti, listino prezzi, email, storico delle modifiche, export CSV.
- **Disponibilità senza sovrapposizioni**: due prenotazioni confermate dello stesso appartamento non possono sovrapporsi (verificato con processi concorrenti reali).
- **Prezzi configurabili** dall'admin: tariffe stagionali, adulti, bambini, animali, supplementi, soggiorno minimo.
- **Email** (SMTP generico, da variabili d'ambiente) con coda: un errore di invio non perde mai una richiesta; la cancellazione prepara una bozza modificabile e non invia nulla da sola. Link **WhatsApp** con messaggio precompilato.
- **Sicurezza e privacy**: CSRF, sessioni sicure, antispam senza servizi esterni, nessun cookie ai visitatori, strumenti per esportare e anonimizzare i dati personali.

## Avvio in locale (Docker)

Requisiti: Docker con Compose.

```bash
cp .env.example .env
# modifica .env: APP_ENV=development, APP_DEBUG=true, DB_HOST=db, e scegli DB_PASSWORD e DB_ROOT_PASSWORD (valori locali)
docker compose up -d --build
docker compose exec web composer install
docker compose exec web php bin/migrate.php
docker compose exec web php bin/create-admin.php admin     # chiede la password (almeno 12 caratteri)
```

Sito: <http://localhost:8080> · Admin: <http://localhost:8080/admin>

Test: `docker compose exec web composer test` (circa 7 minuti). Prima di una pubblicazione: `php bin/check-production.php` (controllo di sola lettura, `docs/RELEASE_GUIDE.md`). Altri comandi in [`docs/COMMANDS.md`](docs/COMMANDS.md).

## Installazione su hosting condiviso

Vedi [`docs/INSTALL_SHARED_HOSTING.md`](docs/INSTALL_SHARED_HOSTING.md): requisiti (PHP 8.1+, `pdo_mysql`, Apache con `mod_rewrite`), caricamento dei file, import del database (anche solo da phpMyAdmin), creazione dell'amministratore (anche senza SSH), SMTP, HTTPS, prova di fumo.

## Configurazione

Tutto in variabili d'ambiente o nel file `.env` (mai nel repository). L'esempio commentato è [`.env.example`](.env.example).

<<<<<<< HEAD
| Gruppo | Variabili |
|---|---|
| Applicazione | `APP_ENV`, `APP_DEBUG`, `APP_URL`, `APP_TIMEZONE`, `APP_SECRET` |
| Database | `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD` (`DB_ROOT_PASSWORD` solo per Docker e test) |
| Email | `MAIL_TRANSPORT`, `SMTP_HOST`, `SMTP_PORT`, `SMTP_ENCRYPTION`, `SMTP_USERNAME`, `SMTP_PASSWORD`, `SMTP_TIMEOUT`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`, `MAIL_ADMIN_ADDRESS` |
| Contatti pubblici | `PUBLIC_PHONE`, `PUBLIC_EMAIL`, `PUBLIC_ADDRESS`, `WHATSAPP_NUMBER`, `WHATSAPP_DEFAULT_COUNTRY_CODE` (mostrati solo se compilati) |
| Sicurezza e privacy | `HSTS_MAX_AGE` (solo con `APP_URL` https), `PUBLIC_FORM_MIN_SECONDS`, `DATA_RETENTION_MONTHS` |

## Struttura del progetto
=======
>>>>>>> 9455058276e776fa5332d8206796d7a428093f04

```text
public/            unica cartella raggiungibile dal web (index.php, assets/)
app/               codice PHP: Http (controller, router, middleware), Domain, Service, Repository,
                   Security, Mail, Site (pagine pubbliche), Support
templates/         viste PHP (public/, admin/)
content/           testi fissi del sito: it.php ed en.php (stesse chiavi)
migrations/        schema e dati iniziali del database (SQL 0001–0005, solo in avanti)
bin/               strumenti da riga di comando: migrate, create-admin, send-queued-mail, privacy, check-production, optimize-images
storage/           log, sessioni, email di prova (scrivibile; non raggiungibile dal web)
tests/             suite PHPUnit (unit, integration, http, concurrency)
docs/              documentazione, specifica, decisioni, esiti dei test
docker/ docker-compose.yml   solo sviluppo locale
```

Dettagli e schema del database: [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md).

## Documentazione

| Documento | Contenuto |
|---|---|
| [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) | architettura finale, struttura, schema del database, flussi principali |
| [`docs/INSTALL_SHARED_HOSTING.md`](docs/INSTALL_SHARED_HOSTING.md) | installazione su hosting condiviso, database, admin, SMTP |
| [`docs/RELEASE_GUIDE.md`](docs/RELEASE_GUIDE.md) | guida alla pubblicazione (preparata, **non eseguita**): pacchetto, permessi, DNS da richiedere, prova di fumo, rollback |
| [`docs/OPERATIONS.md`](docs/OPERATIONS.md) | backup, ripristino, CSV, password admin, privacy, aggiornamenti, problemi frequenti |
| [`docs/COMMANDS.md`](docs/COMMANDS.md) | comandi verificati e configurazione dettagliata |
| [`docs/CHANGES.md`](docs/CHANGES.md) | riepilogo delle modifiche per fase |
| [`docs/DELIVERY_CHECKLIST.md`](docs/DELIVERY_CHECKLIST.md) | checklist di consegna e pubblicazione |
| [`docs/ACCEPTANCE_MATRIX.md`](docs/ACCEPTANCE_MATRIX.md) | criteri di accettazione con evidenza |
| [`docs/TEST_REPORT.md`](docs/TEST_REPORT.md) | test eseguiti e risultati (PASS / FAIL / NOT RUN) |
| [`docs/MANUAL_CHECKLIST.md`](docs/MANUAL_CHECKLIST.md) | prove manuali da eseguire (tastiera, mobile, email reali) |
| [`docs/SECURITY_REVIEW.md`](docs/SECURITY_REVIEW.md) | revisione di sicurezza e rischi residui |
| [`docs/MISSING_DATA.md`](docs/MISSING_DATA.md) | dati e decisioni che mancano |
| [`docs/IMAGES.md`](docs/IMAGES.md) | censimento delle immagini e provenienza |
| [`docs/DECISIONS.md`](docs/DECISIONS.md), [`docs/SPEC.md`](docs/SPEC.md) | decisioni prese e specifica del committente |
| [`docs/PROMPT_PACK.md`](docs/PROMPT_PACK.md) | pacchetto di prompt AI usato per lo sviluppo (non serve per installare il sito) |

## Stato e limiti

- **Test:** 791 test automatici PASS; 23 criteri di accettazione su 27 PASS, 4 PARTIAL, nessun FAIL.
- **Non eseguito (NOT RUN):** prove manuali con tastiera, screen reader, mobile e desktop; consegna email reale (mancano le credenziali SMTP); installazione su un hosting reale; HTTPS reale. Lista di controllo: [`docs/MANUAL_CHECKLIST.md`](docs/MANUAL_CHECKLIST.md).
- **Mancano dati del titolare** (nessun dato è stato inventato): listino prezzi, testi di "L'agriturismo" e "Dintorni", descrizioni degli appartamenti, foto con provenienza verificata, recapiti, numero WhatsApp, testi legali, credenziali SMTP, periodo di conservazione dei dati. Elenco completo in [`docs/MISSING_DATA.md`](docs/MISSING_DATA.md). Finché mancano, il sito mostra segnaposto marcati o omette l'informazione.
- **Vecchia applicazione React/Firebase:** rimossa dal repository (recuperabile dalla cronologia Git, commit `ae3129e`).
- **Fuori ambito:** pagamenti online, account ospiti, integrazioni automatiche con Booking/Airbnb/Novasol.
- Rischi di sicurezza accettati e residui: [`docs/SECURITY_REVIEW.md`](docs/SECURITY_REVIEW.md).

## Licenza e sviluppo

Codice proprietario del committente. Il lavoro di sviluppo è stato fatto per fasi con assistenza AI; le regole e il metodo sono in [`AGENTS.md`](AGENTS.md) e [`docs/WORKFLOW.md`](docs/WORKFLOW.md).
