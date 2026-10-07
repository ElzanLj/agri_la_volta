# Security review

Stato: **ESEGUITA il 2026-10-06 (Fase 7)** sul codice reale, con prove automatiche (HTTP reale, database, processi) e verifiche manuali su Apache nel container. Non sostituisce un penetration test indipendente né una verifica legale della privacy.

## Ambiente / commit

- Data: 2026-10-06
- Branch/commit: `main`, partendo da `c90d6d6`; le correzioni sono nel commit della Fase 7
- PHP 8.2.34 (container `php:8.2-apache`), MariaDB 10.11, PHPMailer 6.x; `composer audit`: nessun avviso di sicurezza
- Non verificato: hosting reale, HTTPS reale, PHP-FPM, versioni diverse di PHP/MySQL

## Finding

| ID | Severità | Area/file | Evidenza / scenario | Correzione | Verifica | Stato |
|---|---|---|---|---|---|---|
| F1 | Media | `app/Security/RateLimiter.php` | Il limitatore salvava `sha256(IP)`: senza chiave un hash di un indirizzo IPv4 si inverte provando tutti gli indirizzi, quindi chi legge il database (backup, SQL injection altrove) ricavava gli IP dei visitatori per 24 h | HMAC-SHA256 con il segreto dell'applicazione (`app/Security/AppSecret.php`: `APP_SECRET`, in mancanza derivato dalle credenziali DB) | `SecurityIntegrationTest` (valore atteso = HMAC, diverso da SHA-256 semplice, il conteggio per client funziona); prova di sensibilità M2 rilevata | **Corretto** |
| F2 | Media | privacy / intero sistema | Nessun modo tecnico di esportare o cancellare i dati di una persona (SPEC §32) né una conservazione definita: richieste e prenotazioni restano per sempre | `app/Service/PersonalDataService.php` e `bin/privacy.php`: `export <email>` (JSON), `erase <email>` e `purge --months=N` che **anonimizzano** (nome, email, telefono, note, motivi, testi di email) mantenendo date e appartamento; simulazione di default, `--apply` per eseguire; richieste in attesa e soggiorni non finiti saltati salvo `--include-active`; voce di audit senza dati personali | `PersonalDataTest` (10 test, anche da riga di comando); prove M5, M6, M8 rilevate | **Riaperto il 2026-10-07 e chiuso di nuovo** (vedi "Riesame" sotto): lo Storico conservava il motivo scritto a mano di cancellazioni e blocchi e la correzione di Fase 7 non lo raggiungeva. La durata di conservazione resta una decisione del titolare: `DATA_RETENTION_MONTHS` vuoto = nessuna pulizia automatica |
| F3 | Bassa | `app/Http/Response.php`, `public/.htaccess` | Mancavano `Permissions-Policy`, `Cross-Origin-Opener-Policy`, `X-Permitted-Cross-Domain-Policies` e HSTS; `X-Powered-By: PHP/8.2.34` rivelava la versione | Intestazioni aggiunte a ogni risposta dell'applicazione; `X-Powered-By` rimosso (anche da Apache per i file statici); HSTS (`max-age=15552000`, senza `includeSubDomains`/`preload`) **solo** se `APP_URL` inizia con `https://` e `HSTS_MAX_AGE` non è 0 | `SecurityTest` su 11 tipi di risposta (home, 404, 405, 403, login, redirect admin, robots, sitemap…) e su 4 configurazioni HSTS; verificato con curl su Apache; prove M1, M3, M7 rilevate | **Corretto** |
| F4 | Bassa | `public/.htaccess`, `public/index.php` | Il limite di 1 MB si basava su `Content-Length`: un invio a blocchi (chunked) arrivava fino al limite di PHP (8 MB) | `LimitRequestBody 1048576` in `.htaccess` | Apache nel container: chunked 2 MB → 413, con `Content-Length` 2 MB → 413, richiesta piccola → risposta normale; `SecurityTest::testRequestBodiesAboveTheLimitAreRefused` per il controllo a livello applicazione | **Corretto** (il limite di Apache non è provabile con PHPUnit: verificato a mano) |
| F5 | Bassa | `app/Http/Controllers/AdminController.php` | Il blocco del login è per IP (5 errori in 15 minuti): un attacco da molti indirizzi non è limitato | Nessuna modifica: account unico, password minima di 12 caratteri scelta dal titolare, confronto a tempo costante anche per utenti inesistenti, sessioni legate alla password | `AdminSessionTest`, `AdminAccessTest` | **Rischio accettato** (vedi sotto) |
| F6 | Informativa | `FormToken`, `RateLimiter` | Il token del modulo pubblico non è monouso (un replay dello stesso modulo può creare più richieste) e il rate limit è per IP (utenti dietro lo stesso NAT lo condividono; dietro un proxy inverso `REMOTE_ADDR` potrebbe essere l'IP del proxy) | Nessuna modifica in Fase 7: replay limitato da rate limit (6 invii/ora), validazione e controllo di disponibilità. **Aggiornamento 2026-10-07:** lo stesso modulo inviato due volte (stesso token, stessi dati) ora produce una sola richiesta (chiave di invio, A2); il limite per IP resta | `PublicAntispamTest`, `ExistingFixesTest` | **Replay chiuso**; il rischio per IP condiviso resta accettato (`TRUSTED_PROXIES` non adottata: D4 = A), da verificare con l'hosting scelto |

Nessun finding di gravità alta o critica.

## Riesame del 2026-10-07 (prompt 15)

La review `docs/REVIEW_PRE_ROADMAP.md` ha trovato punti che la Fase 7 non copriva. Quelli del codice esistente sono stati corretti nel prompt 15, ciascuno con test (vedi `docs/TEST_REPORT.md`); gli altri hanno la fase indicata in `docs/TODO.md`.

| Rif. | Severità | Problema | Correzione | Stato |
|---|---|---|---|---|
| F2 / A1 | Alta | Il motivo di una cancellazione o di un blocco finiva in `audit_log` e **restava dopo l'anonimizzazione** dell'ospite. `PersonalDataTest` controllava solo la voce nuova, non le tracce: falso senso di sicurezza | Lo Storico registra solo `reason_present: true/false`; `bin/privacy.php audit-clean [--apply]` ripulisce le voci precedenti (idempotente); avviso sotto i campi "motivo" e "note"; la chiave di invio (derivata dai dati) viene azzerata con l'ospite. Test che legge **ogni** colonna di testo di **ogni** tabella dopo l'anonimizzazione | **Corretto** |
| A2 | Media | Doppio invio del modulo = due richieste e due email | `booking_requests.submission_key` UNIQUE (hash del token e dei dati), migrazione `0006`; secondo invio identico = stessa pagina "ricevuta", niente richiesta né email. Provato con 8 processi in parallelo | **Corretto** |
| A3 | Alta | "Rifiuta richiesta" era un clic irreversibile che inviava subito l'email | Pagina di conferma con riepilogo e anteprima dell'email reale; un POST diretto senza conferma non modifica nulla | **Corretto** |
| A7 | Alta | `.htaccess` era l'unica barriera davanti a `.env`, sessioni e log | `.htaccess` di negazione in `storage/`, `app/`, `migrations/`, `bin/`, `templates/`, `content/`, `docs/`, `prompts/`, `tests/`, `docker/`. **Difesa parziale**: serve solo dove il server legge i `.htaccess`; la difesa vera è il document root su `public/` e la verifica HTTP (prompt 26 e 32) | **Mitigato** (la verifica sull'hosting reale resta aperta) |
| A9 | Media | Moduli in 403 se il sito risponde anche su `www` | Redirect 301 delle GET verso l'host di `APP_URL`; nessun redirect senza `APP_URL`, dietro `X-Forwarded-Host` o per i POST; il passaggio a https resta all'hosting | **Corretto** |
| A10 | Media | Rate limit: "conta poi registra" lasciava passare più tentativi in parallelo | `RateLimiter::attempt()` registra prima e conta dopo (il tentativo corrente conta subito); un tentativo negato non viene conservato. Provato con 20 processi in parallelo: mai più del limite. Header di proxy ignorati (`TRUSTED_PROXIES` non adottata, D4 = A) | **Corretto** |
| A11 | Alta | Lo splitter delle migrazioni spezzava testi con `;` e righe che iniziano con `--` | Parser che rispetta apici, virgolette, backtick e commenti | **Corretto** |
| A12 | Media | Migrazioni senza lock né protezione dalle modifiche | `GET_LOCK` (due esecuzioni insieme: una sola applica), solo file `NNNN_nome.sql`, `migrations/CHECKSUMS` e test che fallisce se una migrazione rilasciata cambia; pre-controllo dei dati prima della `0007` | **Corretto** (checksum nel DB e registro: prompt 27) |
| A14 | Media | Invio "dopo la risposta" solo con PHP-FPM, nessun `ignore_user_abort` | Supporto a `litespeed_finish_request`; `ignore_user_abort(true)` prima dei lavori differiti | **Corretto** |
| A15 | Media | Messaggi di errore del database nei log (email, credenziali) | Per le eccezioni PDO si registrano solo SQLSTATE e codice del driver | **Corretto** |
| A16 | Bassa | `APP_ENV` sbagliato (es. `prod`) spegneva le protezioni | Valori ammessi `production`, `development`, `testing`; ogni altro valore = produzione, con un avviso nel log | **Corretto** |
| A17 | Bassa | La pagina "ricevuta" mostrava qualsiasi riferimento passato nell'indirizzo | Il riferimento si mostra solo se esiste ed è stato creato negli ultimi 15 minuti | **Corretto** |
| A18 | Bassa | Nessun avviso su cosa non scrivere in "motivo" e "note" | Frase sotto i campi della cancellazione, dei blocchi e delle prenotazioni manuali | **Corretto** (il modulo pubblico non è stato modificato) |
| B1 | Alta | Nessun controllo indipendente dell'unicità delle notti | `ConsistencyChecker` (sola lettura) e `bin/check-consistency.php`; pagina admin e allarme: prompt 26 | **Corretto** (parte comando) |
| B21 | Bassa | Caratteri invisibili e di direzione nei testi | Rimossi in `Request::input()` e `query()` (le password no) | **Corretto** |
| C12–C15 | Media | Stati impossibili possibili nel database | Migrazione `0007` con `CHECK` (cancellata ⇔ data di cancellazione, decisione ⇔ stato, limiti degli ospiti, email inviata ⇒ data di invio); la migrazione si ferma prima di toccare nulla se i dati esistenti li violano | **Corretto** |
| — | — | Test architetturali | Cinque guardiani sempre attivi: checksum delle migrazioni, ogni `<?=` dei template è escapato o in elenco, nessuna funzione che esegue comandi o codice in `app/`, nessun indirizzo esterno in `templates/` e `public/`, rotte admin in una matrice rivista | **Attivi** |

### Rischi che restano aperti dopo il prompt 15

1. **Blocco del login per IP** (F5): in dashboard si vede il numero di accessi falliti nelle ultime 24 ore; l'email al titolare dopo molti tentativi è nel prompt 24.
2. **IP dietro proxy o CDN** (D4 = A): si usa solo `REMOTE_ADDR`; se l'hosting usa un proxy tutti i visitatori condividerebbero lo stesso limite. L'avviso in "Stato del sistema" è nel prompt 26.
3. **I `.htaccess` di negazione non sostituiscono il document root su `public/`** (A7).
4. **Il motivo di un blocco resta nella tabella dei blocchi** (non è collegato a un ospite, quindi l'anonimizzazione non lo raggiunge): per questo c'è l'avviso sotto il campo.

## Checklist minima

- [x] **Query parametrizzate / SQL injection:** tutte le query con dati dell'utente usano `prepare`; le sole `exec`/`query` con testo variabile contengono costanti interne (verificato leggendo ogni occorrenza). 20 stringhe ostili (UNION, DROP, null byte, a capo, unicode, 5000 caratteri…) su ogni parametro pubblico: nessun errore 500, database invariato, tabelle intatte (`SecurityTest`); filtri admin già provati in Fase 3
- [x] **Escaping output / XSS:** `e()` ovunque; payload XSS in nome, note, descrizioni e nomi degli appartamenti provati su pagine pubbliche e admin (Fasi 3, 5 e 7); CSP senza `unsafe-inline` né `unsafe-eval`, nessun JavaScript
- [x] **Validazione server-side:** `BookingService` valida tutto (date, ospiti, capienza, email, telefono, lunghezze, consenso); il browser non decide prezzo, stato, appartamento né lingua; tetti anti-abuso (60 notti, 20 persone…)
- [x] **CSRF:** admin con token di sessione + `Origin` su ogni metodo non sicuro (guardia di prefisso, nessuna eccezione); moduli pubblici con token firmato + `Origin`
- [x] **Sessioni/cookie:** `HttpOnly`, `SameSite=Lax`, `Secure` in HTTPS, `use_strict_mode`, rigenerazione dell'id al login, scadenza inattiva 2 h e assoluta 12 h, sessione legata all'impronta della password, nessun cookie ai visitatori (`AdminSessionTest`, `PublicPagesTest`)
- [x] **Password admin:** `password_hash` (bcrypt/default) con riesame automatico, minimo 12 caratteri, impostata solo da riga di comando, cambio password chiude le sessioni
- [x] **Autorizzazione admin lato server:** default-deny su tutto `/admin` (anche URL inesistenti), matrice su ogni rotta registrata (`AdminAccessTest`)
- [x] **Rate limiting / limiti richiesta:** login admin (5/15 min per IP), invio richieste (6/ora per IP), corpo massimo 1 MB (applicazione e Apache)
- [x] **Upload:** non presenti
- [x] **Segreti / `.env` / repository:** `.env` mai tracciato (verificato con `git ls-files`), nessuna credenziale nei file versionati né nel diff; la cronologia contiene la credenziale Gmail del legacy **già revocata** dal titolare (P7) e le chiavi pubbliche Firebase del legacy; `storage/`, `vendor/`, `.git`, `docs/`, `tests/`, `bin/` non raggiungibili dal web (404)
- [x] **Dati personali nei log:** i log contengono solo percorso, tipo di evento e identificativi. Un test percorre un'intera richiesta, conferma, token falso, honeypot, rate limit, login fallito e CSRF rifiutato con valori-marcatore e controlla che nessun nome, email, telefono, nota, nome utente, password o IP compaia (`SecurityTest::testNoPersonalDataOrSecretReachesTheLogs`, prova M4 rilevata)
- [x] **SMTP secrets:** solo da variabili d'ambiente; `ErrorSanitizer` toglie password, indirizzi e testi del server (Fase 4); intestazioni email: ogni a capo in destinatario, oggetto e nome viene sostituito, indirizzi con a capo, virgole o `<>` rifiutati dalla validazione (`SecurityIntegrationTest`)
- [x] **Antispam pubblico:** honeypot, controllo temporale, token firmato, `Origin`, rate limit; nessun CAPTCHA né servizio esterno (`PublicAntispamTest`)
- [x] **Assenza pagamenti / dati carta:** nessun codice, campo o dipendenza di pagamento (Fase 1b, rimozione dei file legacy senza leggerne i dati)
- [x] **Error handling / leakage:** in produzione `APP_DEBUG` è ignorato; con il database inesistente le pagine rispondono 500 generico in italiano e in inglese, senza SQLSTATE, percorsi, nomi di database, password né stack trace, e con le intestazioni di sicurezza (`SecurityTest`)
- [x] **Dipendenze rilevanti:** `composer audit` pulito (PHPMailer, PHPUnit); nessuna dipendenza JavaScript né risorsa esterna nel sito
- [x] **Privacy tecnica:** consenso obbligatorio nel modulo (6 varianti provate), dati raccolti ridotti ai necessari (nome, cognome, email, telefono, note facoltative, date e ospiti), nessun tracking né cookie per i visitatori, esportazione e anonimizzazione disponibili (F2)

## Rischi accettati / residui

1. **Blocco del login solo per IP** (F5): un attacco distribuito non è fermato dal rate limit; mitigato da password lunga, account unico e assenza di indicazioni sull'esistenza dell'utente. Valutare un blocco per nome utente o l'autenticazione a due fattori se il titolare lo ritiene necessario.
2. **Token del modulo non monouso, rate limit per IP** (F6): impatto limitato (spam di richieste `pending` comunque visibili e gestite dal titolare). Da rivedere con l'hosting (NAT, proxy inverso: se `REMOTE_ADDR` è l'IP del proxy tutti i visitatori condividerebbero il limite).
3. **Segreto derivato dalle credenziali DB** quando `APP_SECRET` manca: funziona ma conviene impostare `APP_SECRET` in produzione (`docs/MISSING_DATA.md`).
4. **HSTS**: attivo solo con `APP_URL` in https. Una volta attivo, i browser rifiutano HTTP per 180 giorni: abilitare HTTPS prima.
5. **Cronologia Git:** contiene i file di configurazione del legacy (credenziale Gmail già revocata, chiavi Firebase pubbliche). Nessuna riscrittura della storia senza autorizzazione (P7).
6. **Dati già salvati nei backup** del database non sono toccati dall'anonimizzazione: i backup vanno ruotati o ripuliti secondo la politica del titolare.
7. **Non verificato:** HTTPS reale e cookie `Secure` in produzione, PHP-FPM, configurazione dell'hosting (permessi di `storage/`, versione di PHP), consegna reale delle email, test di penetrazione indipendente, informativa legale.
