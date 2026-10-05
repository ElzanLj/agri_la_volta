# Test report

> Regola: non segnare PASS un test che non è stato realmente eseguito.

## Ambiente

- Data: 2026-10-05 (baseline audit)
- Commit/branch: `07baca4` / `main`
- PHP: non installato
- Database: non installato (legacy usa Firebase, non contattato)
- Browser/device: nessuno (solo richieste HTTP al dev server)
- Note ambiente: Windows 11, Node v24.18.0, npm 11.16.0

## Baseline stack legacy (React/Vite)

| Controllo | Comando | Risultato | Note |
|---|---|---|---|
| Install | `npm ci --no-audit --no-fund` | PASS | warning allow-scripts esbuild/protobufjs |
| Lint | `npm run lint` | FAIL | 90 errori (42 prop-types, 25 no-unused-vars, 14 no-undef, 9 no-unescaped-entities) |
| Build | `npm run build` | PASS | warning: `fotoSalso3.jpeg` non risolto, CSS syntax, chunk JS 676 kB |
| Dev server | `npx vite --port 5179` | PASS | `/` e `/dovesiamo` → HTTP 200 |
| Audit dipendenze | `npm audit --omit=dev` | 14 vulnerabilità | 8 moderate, 6 high |

I test della tabella seguente riguardano il nuovo sistema PHP e non sono applicabili allo stack legacy.

## Fase 1 — fondamenta PHP/MariaDB (2026-10-05)

Ambiente: Docker, PHP 8.2.34 + Apache (document root = root repository, fallback `.htaccess`), MariaDB 10.11. Verifiche manuali eseguite con `curl` e client `mariadb`; nessuna suite automatica ancora.

| Verifica | Procedura | Risultato | Evidenza |
|---|---|---|---|
| Sintassi PHP | `php -l` su `app/ bin/ public/ templates/` | PASS | nessun errore |
| DB da zero + migrazioni | `php bin/migrate.php` su DB vuoto | PASS | 2 migrazioni applicate, 10 tabelle, InnoDB `utf8mb4_unicode_ci` |
| Idempotenza migrazioni | seconda esecuzione | PASS | "Nothing to migrate." |
| Import alternativo (phpMyAdmin) | file SQL importati col client in DB di prova, poi eliminato | PASS | `schema_migrations` con 0001 e 0002, 6 appartamenti |
| Seed appartamenti | `SELECT` | PASS | 6 righe, solo nome/slug, altri campi NULL |
| CHECK date (`check_out = check_in`) | INSERT in `bookings` | PASS (rifiutato) | errore 4025 `chk_bookings_dates` |
| CHECK origine non valida | INSERT `origin='airbnb'` | PASS (rifiutato) | `chk_bookings_origin` |
| CHECK stato richiesta non valido | INSERT `status='approved'` | PASS (rifiutato) | `chk_booking_requests_status` |
| FK appartamento inesistente | INSERT `availability_blocks` | PASS (rifiutato) | errore 1452 |
| Default stato richiesta | INSERT senza stato (in transazione, rollback) | PASS | `pending` |
| Connessione app → DB | dashboard admin | PASS | conteggi 0 richieste / 6 appartamenti |
| Routing | `/` 200, `/non-esiste` 404, `POST /` 405, `/admin/` → 301 `/admin`, `HEAD /` 200 | PASS | |
| File privati non raggiungibili | `/.env`, `/app/...`, `/migrations/...`, `/bin/...`, `/templates/...`, `/legacy/.../.env`, `/.git/config`, `/docker-compose.yml`, `/AGENTS.md` | PASS | tutti 404 dall'app, nessun contenuto |
| Directory | `/assets`, `/storage` | PASS dopo correzione | inizialmente loop di redirect + esposizione del percorso `/public/assets/` (FAIL); risolto con `DirectorySlash Off`; ora 404. `/public` → 403 senza contenuto |
| Asset statici | `/assets/css/site.css` | PASS | 200 |
| Header di sicurezza | risposta di `/` | PASS | CSP, X-Frame-Options, nosniff, Referrer-Policy; nessun cookie sulle pagine pubbliche |
| Limite dimensione richiesta | POST 1,1 MB | PASS | 413 |
| `create-admin.php` | password corta / non coincidente / username non valido / valida | PASS dopo correzione | inizialmente fatal error su `stream_isatty` dopo lettura STDIN (FAIL), corretto; ora exit 1/1/1/0 |
| Login senza CSRF | POST | PASS | 400 |
| Login utente inesistente / password errata | POST | PASS | 422, messaggio generico |
| Escaping input nel form | username `<script>` | PASS | reso come `&lt;script&gt;` |
| Login corretto | POST | PASS | 303 → `/admin`, nuovo ID di sessione, cookie `HttpOnly; SameSite=Lax` |
| Header admin | `/admin` | PASS | `X-Robots-Tag: noindex, nofollow`, `Cache-Control: no-store` |
| Accesso admin non autenticato | `/admin` | PASS | 303 → `/admin/login` |
| Logout con CSRF errato | POST | PASS | sessione mantenuta |
| Logout | POST con token | PASS | dopo logout `/admin` → login |
| Rate limiting login | 6 tentativi errati | PASS | 5×422 poi 429; anche la password corretta riceve 429 durante il blocco |
| Audit log | creazione admin | PASS | riga `admin / created` |
| Log senza segreti | ricerca della password di test nei log | PASS | 0 occorrenze |
| Errore DB in development | DB fermo, POST login | PASS | dettaglio eccezione mostrato (solo debug) |
| Errore DB in production | DB fermo, `APP_ENV=production` | PASS | pagina generica 500, dettaglio solo nel log |
| Cookie `Secure` in HTTPS | — | NOT RUN | ambiente locale solo HTTP |
| Legacy dopo spostamento | `cd legacy && npm run build` | PASS | stessi warning della baseline |

## Fase 2A — booking e disponibilità (2026-10-05)

Ambiente: Docker, PHP 8.2.34, MariaDB 10.11, PHPUnit 10.5.66, database dedicato `agriturismo_test` (guardia: il nome deve finire con `_test`). Comando: `docker compose exec web composer test`.

| Suite | Test | Asserzioni | Risultato |
|---|---|---|---|
| `unit` (`StayDatesTest`, `GuestCountsTest`) | 85 | 105 | PASS |
| `integration` (disponibilità, regole, richieste, blocchi/cancellazione/atomicità) | 84 | 250 | PASS |
| `concurrency` (processi PHP reali con connessioni DB separate, 8 round per scenario) | 8 | circa 1400 | PASS |
| **Totale** | **177** | **1748 e 1751 nelle due esecuzioni** | **PASS in 2 esecuzioni consecutive, circa 75 s ciascuna** |

Scenari di concorrenza (ogni round: DB azzerato, N processi rilasciati nello stesso istante, poi controllo SQL indipendente delle sovrapposizioni):

1. 8 richieste per le stesse date confermate insieme: esattamente 1 conferma, 7 conflitti puliti, 7 richieste restano `pending`.
2. Stessa richiesta confermata da 8 processi: 1 sola prenotazione.
3. Conferma e rifiuto della stessa richiesta insieme: una sola decisione vince; stato e prenotazioni coerenti.
4. 3 conferme + 3 prenotazioni manuali + 2 blocchi sulle stesse date: o 1 prenotazione e 0 blocchi, o 0 prenotazioni e 2 blocchi.
5. 4 soggiorni adiacenti (check-out = check-in) in parallelo: tutti riescono.
6. 6 appartamenti diversi in parallelo: tutti riescono.
7. Cancellazione contro conferma sulle stesse date: la cancellazione riesce sempre; stato finale coerente.
8. 16 soggiorni casuali (seme fisso) in parallelo: nessuna sovrapposizione; successi dichiarati = prenotazioni salvate.

In tutti i round: nessun errore tecnico (nessuna eccezione, lock timeout o deadlock).

**Prova di sensibilità (mutation check, eseguita una volta a mano):** ho disattivato temporaneamente il lock sulla riga appartamento in `BookingService::withApartmentLock` (sostituendo `lockForUpdate` con una lettura semplice). Risultato: **3 test su 8 falliti** al primo round: 7 conferme su 8 per le stesse date, 6 prenotazioni confermate nello scenario misto, 19 coppie sovrapposte nello scenario casuale. Il codice originale è stato ripristinato (file identico alla copia salvata) e la suite è tornata verde. Questo dimostra che i test rilevano davvero le gare.

Difetti trovati durante la fase: nessuno nel codice di prodotto. Due errori miei nei test, corretti: un metodo helper in conflitto con `TestCase::status()` di PHPUnit, e uno scenario di concorrenza che creava una richiesta su date già occupate (la richiesta veniva correttamente rifiutata).

Limiti noti:

- Autorizzazione server-side delle azioni admin: **NON APPLICABILE ora**. Le azioni esistono solo come servizi e non sono esposte via HTTP; saranno testate in Fase 3 con l'interfaccia admin. Resta verificata la protezione di `/admin` (Fase 1).
- Provato solo su MariaDB 10.11; MySQL 8 non provato (comportamento atteso uguale per `SELECT ... FOR UPDATE` e `READ COMMITTED`).
- Nessun vincolo di esclusione a livello DB (MariaDB/MySQL non li supportano): l'invariante regge perché ogni scrittura passa da `BookingService`. Scritture dirette via SQL possono violarla.
- Il pricing è solo l'interfaccia `PriceQuoter` (implementazione nulla): `quoted_total_cents` resta NULL fino alla Fase 2B.

## Fase 4 — email, robustezza SMTP e WhatsApp (2026-10-06)

Comando: `docker compose exec web composer test`. Il vero `SmtpTransport` (PHPMailer) è provato contro un **server SMTP finto** (`tests/Support/fake-smtp-server.php`) che parla il protocollo e può guastarsi in 10 modi (più uno scenario lento). **Nessuna credenziale SMTP reale è stata usata né esiste ancora: la consegna reale NON è stata verificata** (vedi limiti).

| Suite | Test | Asserzioni | Risultato |
|---|---|---|---|
| `unit` (+ `WhatsAppTest`, `MailUnitTest`) | 278 | 520 | PASS |
| `integration` (+ `SmtpTransportTest`, `OutboxFlowTest`, `MailResilienceTest`) | 223 | circa 1900 | PASS |
| `http` (+ `AdminMailTest`) | 101 | circa 1500 | PASS |
| `concurrency` (+ `MailConcurrencyTest`) | 11 | circa 1700 | PASS |
| **Totale** | **613** | **5285 e 5291 nelle due esecuzioni** | **PASS in 2 esecuzioni consecutive, circa 4,5 minuti ciascuna** |

### La garanzia centrale: lo stato non si perde mai
- **Commit prima dell'invio.** Il trasporto di prova apre, nel momento esatto dell'invio, una **seconda connessione** al database: vede già la richiesta salvata, la conferma con prenotazione e voce di audit, il rifiuto, e nessuna transazione aperta. Valido per nuova richiesta, conferma e rifiuto.
- **13 tipi di guasto** (connessione rifiutata, timeout, 4xx, destinatario 5xx, autenticazione, non configurato, `RuntimeException`, `LogicException`, `TypeError`, `DivisionByZeroError`, `ErrorException` da warning di rete, `Error` da memoria esaurita, `PDOException`): per ognuno richiesta salvata, conferma intatta (richiesta, prenotazione, audit, nessuna sovrapposizione, nessuna transazione aperta), rifiuto intatto, nessuna eccezione al chiamante, riga della coda `failed` con codice, tentativi e testo **ripulito** (nessuna password, nessun indirizzo, nessun nome nei log).
- **Server SMTP vero in 9 scenari** (destinatario 550, 450, autenticazione 535, rifiuto dopo DATA 554, rinvio dopo DATA 451, caduta a metà messaggio, caduta prima dell'esito, server che non risponde, 421 al saluto), in più porta chiusa e SMTP non configurato: l'intero flusso (richiesta → conferma) lascia lo stato intatto, classifica l'errore, pianifica o no il retry; poi, "riparato" il server, **un tentativo manuale consegna ogni messaggio una sola volta** ai destinatari giusti.
- **Timeout:** un server che non risponde costa circa il timeout configurato (verificato: tra 0,8 e 4 s con timeout di 1 s; richiesta HTTP di rifiuto con timeout 2 s < 8 s).
- **Anche registrare l'esito può fallire:** con un trigger che impedisce di aggiornare la riga a `sent`/`failed`, nessuna eccezione, stato intatto, riga "in invio" che torna disponibile alla scadenza della presa e viene poi consegnata.
- **Hook dopo il commit rotto** (`Error` dentro l'hook): contenuto e registrato; i messaggi restano in coda.
- **Outbox rotta o assente** (trigger che fa fallire l'INSERT; tabella rinominata come se la migrazione 0004 mancasse): richiesta, conferma e rifiuto vengono comunque salvati, l'errore è registrato (anche senza logger). *Trovato da una prova di sensibilità e corretto: prima l'INSERT nella transazione avrebbe annullato anche la prenotazione.*
- **Atomicità:** se la transazione fallisce a metà (audit), non restano né richiesta/prenotazione né riga in coda; se un'operazione è rifiutata non si accoda nulla; cancellando una richiesta spariscono le sue righe in coda.

### Coda, retry e concorrenza
- Backoff 5, 30, 120 minuti, quarto tentativo ultimo; nessun ritentativo anticipato; errore permanente mai ritentato da solo ma ritentabile a mano; riga inviata mai rinviata; presa fresca rispettata, presa scaduta (10 min) ripresa; ordine per id; budget di tempo; id sconosciuto innocuo.
- **Concorrenza reale** (8 processi PHP, 5 round): lo stesso messaggio tentato da 8 processi parte **una sola volta** (1 `sent`, 7 `busy`, 1 file); 8 processi che svuotano insieme la coda di 6 messaggi ne inviano esattamente 6, un tentativo ciascuno; un messaggio già consegnato non riparte.

### Contenuto dei messaggi
Notifica al gestore (riferimento, appartamento, date, notti, ospiti, cliente, email, telefono, lingua, prezzo calcolato o "da confermare", note, link alla richiesta, `Reply-To` sul cliente, oggetto con il solo riferimento); conferma e rifiuto in italiano e inglese (orari solo se configurati, totale solo se noto, nessun motivo inventato, nessun riferimento a pagamenti); nessun "richiesta ricevuta" al cliente; indirizzo del gestore mancante → errore di configurazione, non crash; cliente senza email → `skipped`; righe inviate senza dati personali né testo; header injection (a capo in nome, oggetto, destinatario) neutralizzata sia a livello di messaggio sia sul canale SMTP.

### Protocollo SMTP reale
Invio riuscito con busta, intestazioni (oggetto UTF-8, From, To, Reply-To, Content-Type), corpo con accenti e riga composta da un solo punto, sequenza `EHLO, AUTH, MAIL, RCPT, DATA, QUIT`, nessuna AUTH senza credenziali, la password mai nel registro del server, nessun banner `X-Mailer`; **TLS non declassato in chiaro** (con `tls` e server senza STARTTLS nessun messaggio parte); classificazione corretta di ogni guasto con messaggio privo di indirizzi e credenziali.

### Admin (HTTP reale, con SMTP finto)
Conferma con invio riuscito ("Email al cliente inviata") e con server giù (decisione salvata, messaggio onesto, riga `failed`, contatore in dashboard, pagina Email con prossimo tentativo, nulla di sensibile in pagina/DB/log); riprova a mano fino alla consegna e nessun doppio invio; rifiuto in inglese e con timeout; email in coda recapitate insieme alla decisione successiva; pagina Email con filtri validati (400 su valori non validi); **cancellazione che non invia né accoda nulla**; bozza modificabile (IT/EN), `mailto:`, rifiuto del segnaposto non modificato, validazione, invio del **testo modificato** e cancellazione del testo dopo l'invio, testo conservato se l'invio fallisce, solo per prenotazioni cancellate, senza indirizzo non si può inviare, testo del cliente escapato; script `bin/send-queued-mail.php`; pagine protette come le altre (anonimo 303/401, senza token 403, GET su azione 405).

### WhatsApp
31 casi di normalizzazione (più prefissi predefiniti diversi) (formati internazionali, `00`, nazionali, prefisso digitato senza `+`, fissi italiani, UK/US, prefissi predefiniti diversi; rifiutati testo, estensioni, doppio `+`, troppo corti/lunghi, iniezione HTML/query/`javascript:`, cifre non ASCII), URL `wa.me` valido riletto dopo la decodifica, **nulla nel testo può uscire dall'URL**, messaggio dell'agriturismo identico all'esempio SPEC (IT ed EN, combinazioni parziali), messaggi verso i clienti con plurali e animali, link nelle pagine di richiesta e prenotazione (anche manuale), nessun link per numeri inutilizzabili.

### Prove di sensibilità (eseguite a mano su una copia pulita dei file, ripristino automatico)

| Indebolimento introdotto | Test falliti |
|---|---|
| email inviata **dentro** la transazione (prima del commit) | 18 su 38 |
| errori inattesi del trasporto non contenuti | 8 su 38 |
| errori dell'hook dopo il commit propagati al chiamante | 1 su 38 |
| presa atomica rimossa (chiunque può inviare) | 5 su 30 |
| testi di errore non ripuliti | 4 su 62 |
| a capo ammessi negli header | 7 su 73 |
| WhatsApp accetta qualunque carattere | 3 su 46 |
| la cancellazione accoda un'email da sola | 2 su 48 |
| risposte SMTP ignorate (ogni errore sembra "connessione") | 12 su 60 |
| TLS declassato in chiaro | 1 su 22 |
| bozza inviabile con il segnaposto non modificato | 1 su 21 |
| un messaggio consegnato può essere preso e inviato di nuovo | 3 su 30 |
| i tentativi automatici non si fermano mai | 1 su 27 |
| un'outbox rotta o assente blocca la prenotazione | 3 su 38 |

### Difetti trovati e corretti durante la fase
1. **Un'outbox rotta o mancante avrebbe annullato anche la prenotazione** (INSERT nella transazione): ora contenuto (`queueMail`) e coperto da test. Trovato perché una prova di sensibilità non rilevava lo spostamento dell'accodamento.
2. La classificazione degli errori SMTP leggeva lo stato di PHPMailer dopo il suo `RSET` di pulizia (vuoto): destinatario 550, 450 e autenticazione 535 risultavano "connessione", e una porta chiusa "rifiutato" (`errno` scambiato per codice SMTP). Ora si classifica dalle risposte del server catturate durante la conversazione.
3. `WhatsApp::link()` accettava un numero con a-capo finale (`$` prima del newline); corretto con `\z` (e nei filtri della lista).
4. PHPMailer 6.12 emette il banner `X-Mailer` anche con valore vuoto: ora si sopprime con un valore blank.
5. Una prova di sensibilità interrotta ha lasciato per un momento `BookingService.php` con una modifica di prova; individuata, ripristinata e resa impossibile per il futuro (le prove ripristinano sempre da una copia pulita).
6. Errori nei miei test, corretti: confronto di chiavi di array, asserzioni prive di senso, numeri "inutilizzabili" che la validazione pubblica già rifiuta.

### Limiti noti (NOT RUN)
- **Consegna reale NON verificata**: TLS/STARTTLS con certificato vero (il server finto non ha TLS), autenticazione con un provider vero, SPF/DKIM/reputazione e rischio spam. Checklist a credenziali disponibili: (1) impostare `SMTP_*` e `MAIL_*` in produzione; (2) inviare una richiesta di prova e verificare notifica al gestore; (3) confermarla e verificare ricezione e intestazioni dal lato cliente; (4) controllare SPF/DKIM/DMARC del dominio con il fornitore (senza modificarli senza autorizzazione); (5) provare un errore di password per vedere il messaggio in Admin > Email.
- **Invio dopo la chiusura della risposta con PHP-FPM** (`fastcgi_finish_request`) non provato (il test usa la CLI): verificata solo la logica di `DeferredWork` con un finto "chiudi connessione" e l'invio in linea.
- Testi delle email provvisori (da approvare dal titolare), nessun test manuale nel browser, provato solo con MariaDB 10.11.

## Fase 3 — area amministrativa e sicurezza (2026-10-05)

Ambiente come le fasi precedenti. Comando: `docker compose exec web composer test`. La nuova suite `http` avvia l'applicazione vera dietro il **server PHP built-in** (database di test) e usa un client HTTP con cookie: status, header, redirect e cookie sono quelli reali. Prezzi usati nei test: FITTIZI.

| Suite | Test | Asserzioni | Risultato |
|---|---|---|---|
| `unit` (+ `CsvTest`, `RouterGuardTest`, `ListFiltersTest`) | 208 | 368 | PASS |
| `integration` (+ `ApartmentAdminServiceTest`) | 136 | 466 | PASS |
| `http` (`AdminAccessTest`, `AdminCsrfTest`, `AdminSessionTest`, `AdminActionsTest`, `AdminExportTest`) | 80 | circa 1250 | PASS |
| `concurrency` (invariata) | 8 | circa 1400 | PASS |
| **Totale** | **432** | **3474 e 3480 nelle due esecuzioni** | **PASS in 2 esecuzioni consecutive, circa 3 minuti ciascuna** |

### Sicurezza verificata

- **Non autenticato:** le rotte `/admin/*` sono ricavate dal router (non scritte a mano; un test fallisce se l'elenco è vuoto). Ogni GET → 303 verso il login senza contenuti; ogni POST con più payload plausibili → 401; il database (checksum di 9 tabelle) non cambia. Anche URL inesistenti o strani (`/admin/.env`, `/admin/../...`, metodi DELETE/PUT/OPTIONS) si comportano come qualunque URL protetto: nessuna informazione sulla loro esistenza. `/administrator` e simili restano URL pubblici (404).
- **Nessuna sessione né cookie per il traffico anonimo**; il sito pubblico non contiene link a `/admin`; non esiste alcuna registrazione.
- **Header:** ogni risposta admin (redirect, 401, 403, 404, pagine) ha `Cache-Control: no-store`, `X-Robots-Tag: noindex, nofollow`, CSP rigida, `X-Frame-Options: DENY`, `nosniff`.
- **CSRF:** per ogni rotta POST (login e logout inclusi) senza token / token vuoto / sbagliato / di un'altra sessione / passato come array → 403 e database invariato; il token nella query string è ignorato; `Origin` o `Referer` estranei (host, schema o porta diversi, `null`) → 403 anche con token valido; con token valido e Origin/Referer del sito o senza intestazioni → la richiesta arriva al gestore. Il token e l'id di sessione cambiano al login (il token di prima non vale più). I rifiuti sono registrati nel log senza token né dati.
- **Nessuna modifica via GET:** con sessione valida tutte le rotte GET lasciano il database invariato; gli URL di azione (conferma, rifiuto, rimozione blocco, eliminazioni, logout) rispondono 405 a un GET; `_method` e PUT non fungono da scorciatoia.
- **Sessioni:** logout con cookie rigiocato inutile; fissazione di sessione sconfitta (nuovo id al login, id sconosciuti non adottati); scadenza per inattività (2 h) e assoluta (12 h) verificate invecchiando il file di sessione; l'attività rinnova il timer; il **cambio password con `bin/create-admin.php` chiude tutte le sessioni aperte**; l'eliminazione o la sostituzione dell'account espelle la sessione; cookie `HttpOnly`, `SameSite=Lax`, senza `Secure` su HTTP e **con `Secure` su un sito https** (chiude il NOT RUN della Fase 1).
- **Login:** password errata e utente inesistente producono pagine identiche; blocco dopo 5 tentativi falliti (429, anche per la password giusta); login senza token rifiutato.
- **Input:** filtri con SQL injection, date impossibili, periodi invertiti, pagine negative, parametri in forma di array → 400 senza dati né errori SQL; ID di route non numerici → 404; corpo oltre 1 MB → 413. Testo con `<script>`, `<img onerror>`, `<b>`, `<i onmouseover>`, `<u>` inserito da cliente o admin risulta escapato in 11 pagine e anche negli attributi (`title`, `value`).

### Azioni admin verificate end to end (token valido, effetto su database, audit e pagine)

Elenco richieste con filtri (stato, appartamento, periodo semiaperto, combinati) e paginazione (50 per pagina); elenco prenotazioni con filtri (origine, stato, periodo); dettaglio richiesta con prezzo calcolato; **conferma** (prenotazione creata, flash mostrato una volta, voci nello storico, visibile in calendario); conferma su periodo occupato (spiega cosa blocca, nulla cambia); conferma ripetuta (nessun duplicato); **rifiuto**; **prenotazione manuale** (tutte le origini tranne `website`, dati conservati e messaggi accanto ai campi in caso di errore o conflitto, capienza); **blocchi** (creazione, rifiuto su prenotazione, rimozione, effetto sulle prenotazioni); **cancellazione** (pagina di conferma che non modifica nulla, date riaperte e riprenotabili, richiesta collegata aggiornata, doppia cancellazione); **modifica appartamenti** (valori, traduzioni IT/EN, slug immutabile, checkbox disattivati, audit solo dei campi cambiati, errori); **listino** (tariffe e regole: creazione, sovrapposizione rifiutata, modifica, eliminazione, date senza tariffa, effetto immediato sui prezzi delle nuove richieste e nessun effetto su quelle già date); **storico modifiche** con filtro; **dashboard**; **calendario** mensile (notti giuste, soggiorni a cavallo di mesi, mese non valido); **export CSV** richieste e prenotazioni.

### CSV
Delimitatore `;`, BOM UTF-8, righe CRLF, quoting RFC 4180 (punto e virgola, virgolette, a capo nelle note sopravvivono al round trip); **nessuna cella può iniziare con `=`, `+`, `-`, `@`, tab o CR** (preceduta da apostrofo, anche i telefoni che iniziano con `+`); filtri per stato, appartamento, origine e periodo; filtri non validi → 400; senza login nessun contenuto.

### Prove di sensibilità (eseguite a mano, file ripristinati e verificati identici)

| Indebolimento introdotto | Test falliti |
|---|---|
| guardia di autenticazione rimossa | 9 su 16 |
| guardia CSRF rimossa | 5 su 11 |
| token CSRF non controllato (solo Origin) | 4 su 11 |
| controllo Origin disattivato | 1 su 11 |
| sessione non più legata all'hash della password | 1 su 12 |
| neutralizzazione formule CSV rimossa | 9 su 28 |
| escaping rimosso dalla pagina dettaglio richiesta | 1 su 33 |
| prefisso `/admin` confrontato troppo largo (`/administrator`) | 2 su 24 |
| id di sessione non rigenerato al login | 2 su 23 |

### Difetti trovati e corretti durante la fase

1. I campi numerici lasciati vuoti nei form (bambini, animali, quantità gratuite, ordine) producevano un errore invece del valore 0: ora valgono 0.
2. I parametri di filtro in forma di array (`?stato[]=x`) venivano ignorati in silenzio: ora sono rifiutati (400).
3. Il redirect dopo una cancellazione non riuscita passava per un secondo redirect: ora va direttamente al dettaglio.
4. (Test) `resetDatabase()` non ripristinava tutti i campi modificabili degli appartamenti e un test lasciava il nome «Hacked» ai test successivi: ora lo stato seminato è ripristinato per intero.
5. (Test) un helper `follow()` sbagliato, e asserzioni sui flag del cookie sensibili alle maiuscole: corretti.

### Limiti noti

- **Nessuna email viene inviata** alla conferma, al rifiuto o alla cancellazione, e non esiste ancora la bozza di cancellazione: è la Fase 4. Le pagine lo dicono esplicitamente.
- Nessun test manuale nel browser (mobile/desktop/tastiera): NOT RUN. L'accessibilità dell'admin è stata curata (etichette, errori collegati con `aria-describedby`, skip link, tabelle con `th scope`) ma non verificata con strumenti.
- Il limite di tentativi di login usa l'indirizzo IP del client: dietro un proxy che nasconde l'IP reale tutti gli utenti potrebbero condividere lo stesso contatore. Da verificare con l'hosting scelto.
- Il cambio password è solo da riga di comando (`bin/create-admin.php`).
- Appartamenti: foto e dotazioni (servizi) non gestite.
- Provato solo con MariaDB 10.11 e con il server built-in/Apache del container Docker.

## Fase 2B — pricing (2026-10-05)

Ambiente come Fase 2A. Comando: `docker compose exec web composer test`. **Tutti i prezzi dei test sono FITTIZI** (`tests/Support/PricingFixtures.php`, etichette `[TEST]`, importi artificiali): non sono il listino del titolare.

| Suite | Test | Asserzioni | Risultato |
|---|---|---|---|
| `unit` (+ `PriceCalculatorTest`, `MoneyAndRangesTest`) | 142 | 243 | PASS |
| `integration` (+ `PricingIntegrationTest`, `PricingConfigTest`) | 125 | 415 | PASS |
| `concurrency` (invariata, rieseguita) | 8 | circa 1400 | PASS |
| **Totale** | **275** | **2054 e 2048 nelle due esecuzioni** | **PASS in 2 esecuzioni consecutive, circa 74 s ciascuna** |

Copertura richiesta dal piano:

- **Soggiorni a cavallo di più stagioni:** 13-17 giugno = 2 notti bassa + 2 alta; check-out sul confine non addebita la stagione successiva; check-in sul confine = tutto stagione successiva; 1 notte per lato; 3 stagioni; cambio d'anno; notte senza tariffa (lacuna all'inizio, in mezzo, alla fine, nessuna tariffa, periodo non attivo, altro appartamento, periodi sovrapposti).
- **Limiti bambini/animali:** `max_children`/`max_pets` vuoti = nessun limite; al limite passa, oltre rifiutato senza salvare nulla; `max_pets = 0` = animali non ammessi; limiti per appartamento; non si applicano alle prenotazioni manuali dell'admin.
- **Supplementi:** fisso una volta per soggiorno, per notte, con finestra di validità che copre solo parte del soggiorno, finestra prima/dopo il soggiorno, finestra per-soggiorno decisa dalla data di arrivo (4 casi di confine), regole globali + di appartamento additive, regole di altri appartamenti e disattivate ignorate, ordine stabile (`sort_order`, `id`).
- **Soggiorno minimo:** sotto/uguale/sopra, nessun minimo configurato, minimo deciso dal periodo della data di arrivo (3 casi), nessun minimo se la notte di arrivo non ha tariffa; rifiutato in `createRequest` senza salvare nulla; non applicato alle prenotazioni manuali.
- **Integrazione server-side:** totale e istantanea salvati con la richiesta; prezzi inviati dal browser ignorati; quote già date non cambiano se il listino viene modificato dopo; la conferma copia il totale in `bookings.total_cents`; listino assente o parziale = richiesta salvata con totale NULL e istantanea `unquoted` con le date scoperte.
- **Consistenza e audit del listino (`PricingConfigService`):** sovrapposizioni parziali/totali/contenute/contenenti rifiutate; periodi adiacenti, appartamenti diversi e bozze inattive ammessi; riattivare una bozza sovrapposta rifiutato; un aggiornamento fallito non cambia nulla; appartamento immutabile; validazione di date, importi, minimo, etichette, tipi di regola, finestre; vincoli CHECK del DB; audit con valori vecchi/nuovi per creazione, modifica ed eliminazione; elenco dei periodi senza tariffa.
- **Nessun dato di produzione:** un test controlla che nessuna migrazione inserisca tariffe o regole, e un altro migra un database vuoto temporaneo verificando 0 tariffe, 0 regole, 6 appartamenti e nessun limite/capienza/prezzo indicativo.
- **Regressioni:** i 177 test della Fase 2A passano invariati; la suite di concorrenza passa dopo l'estrazione di `TransactionRunner` e l'integrazione del pricing in `createRequest`.

Prove di sensibilità sul calcolatore (eseguite a mano, codice ripristinato e verificato identico):

| Errore introdotto | Test falliti |
|---|---|
| confine di stagione inclusivo (`night < end` → `night <= end`) | 14 (1 errore + 13 failure) |
| minimo soggiorno preso dall'ultima notte invece che dalla notte di arrivo | 2 |
| `free_units` ignorato | 10 |

Difetti trovati: nessuno nel codice di prodotto. Tre errori miei nei test, corretti: un `+` tra array che non sovrascriveva un valore, un periodo di "10 anni" che era in realtà sotto il tetto di 3660 notti, un test senza asserzioni.

Limiti noti:

- Nessun listino reale: il motore non è mai stato provato con i prezzi del titolare.
- Interfaccia admin per il listino e anteprima prezzo nel form: Fase 3 e Fase 5 (qui solo servizi).
- Tassa di soggiorno, sconti percentuali/per età, supplementi opzionali non implementati (vedi `docs/MISSING_DATA.md`).
- Autorizzazione admin delle azioni di configurazione prezzi: non applicabile finché non esistono le route (Fase 3).
- Provato solo su MariaDB 10.11.

## Fase 1b — migrazione controllata (2026-10-05)

| Controllo | Prima | Dopo | Note |
|---|---|---|---|
| `npm run build` (legacy) | PASS, JS 676 kB (174 kB gzip) | PASS, JS 184 kB (60 kB gzip) | restano i warning su `fotoSalso3.jpeg` e sintassi CSS |
| `npm run lint` (legacy) | FAIL, 90 errori | FAIL, 43 errori (25 prop-types, 11 no-unused-vars, 7 no-unescaped-entities) | gli errori `no-undef` sono spariti con il codice morto; non corretti i restanti |
| `npm audit --omit=dev` (legacy) | 14 vulnerabilità (8 moderate, 6 high) | 2 moderate | dipendenze di produzione ora: react, react-dom, react-router-dom |
| Riferimenti a Firebase/nodemailer/pagamenti | presenti | assenti nel codice versionato | ricerca testuale fuori da `docs/`, `prompts/`, guide: nessun risultato tranne il file locale non tracciato `legacy/src/EmailStatus/.env` |
| `php bin/migrate.php --status` | — | PASS | 0001 e 0002 applicate |
| Pagine PHP | — | PASS | `/` 200, `/admin` 303, `/admin/login` 200, `/nope` 404, `/legacy/package.json` 404, `/.env` 404 |
| Dati nel DB | — | PASS | 6 appartamenti, 1 admin |
| `php -l` su tutti i file PHP | — | PASS | |
| Regressioni visibili del legacy | — | — | rimossi dal sito legacy: login/registrazione, flusso di prenotazione e pagamento, modulo contatti. Intenzionale; il legacy non è pubblicato. Nessun test manuale nel browser del legacy (NOT RUN) |


## Test automatici

| Test | Comando / procedura | Risultato | Evidenza / note |
|---|---|---|---|
| Date non valide | `composer test` | PASS | `StayDatesTest` (17 formati x 2 campi), `RequestFlowTest`, `BookingRulesTest` |
| Checkout precedente/uguale al check-in | `composer test` | PASS | `StayDatesTest`, `BookingRulesTest`, `RequestFlowTest` |
| Numero notti | `composer test` | PASS | 9 casi: fine mese, febbraio bisestile/non, cambio ora legale (ott/mar), capodanno, 60 notti |
| Soggiorni consecutivi | `composer test` | PASS | check-out e check-in nello stesso giorno, notti singole adiacenti, 4 soggiorni adiacenti prenotati in parallelo |
| Overlap parziale | `composer test` | PASS | unit (16 casi, simmetria) + integrazione con DB |
| Overlap completo | `composer test` | PASS | identico, contenuto, contenente, una notte |
| Blocchi manuali | `composer test` | PASS | blocco occupa le notti, giorno finale libero, rimozione libera, blocco su prenotazione rifiutato |
| Conferma concorrente | `composer test` (suite `concurrency`) | PASS | 8 processi x 8 round: 1 sola conferma; con lock disattivato i test FALLISCONO come atteso (vedi sotto) |
| Prezzi stagionali | `composer test` | PASS | `PriceCalculatorTest`: confini di stagione, 3 stagioni, cambio d'anno, lacune. Dati FITTIZI |
| Variazione adulti | `composer test` | PASS | adulti inclusi gratis, extra a pagamento, 1 adulto, limite esatto |
| Variazione bambini | `composer test` | PASS | primo gratis, successivi a pagamento, limite `max_children` |
| Animali | `composer test` | PASS | per notte/per soggiorno, primo gratis, limite `max_pets`, 0 = non ammessi |
| Autorizzazione admin | `composer test` (suite `http`) | PASS | ogni rotta `/admin` enumerata dal router: anonimo GET → 303, scritture → 401, database invariato |
| Richiesta pubblica | `composer test` | PASS (solo livello servizio) | sempre `pending`, mai confermata; form/endpoint HTTP non ancora esistenti |
| Fallimento email | | NOT RUN | |
| Validazione form | `composer test` | PASS (solo livello servizio) | validazione server-side di date, ospiti, contatti, privacy; form HTML in Fase 5 |
| Cancellazione | `composer test` | PASS | date riaperte e riprenotabili, doppia cancellazione rifiutata, richiesta collegata `cancelled`, audit |
| Export CSV | `composer test` (suite `http`, `AdminExportTest`) | PASS | formato, filtri, BOM, quoting, neutralizzazione formule |

## Verifica manuale

| Area | Risultato | Browser/device | Note |
|---|---|---|---|
| Mobile | NOT RUN | | |
| Desktop | NOT RUN | | |
| Tastiera | NOT RUN | | |
| Pagine principali | NOT RUN | | |
| Italiano | NOT RUN | | |
| Inglese | NOT RUN | | |

## Problemi aperti

- Lint legacy in FAIL (90 errori): non corretto, stack destinato alla sostituzione.
- Nessuna suite di test automatica PHP: PHPUnit previsto in Fase 2A.
- Cookie `Secure` sotto HTTPS non verificato (NOT RUN).


## Convenzioni evidenza

Quando possibile registra:

- comando esatto;
- exit code/esito;
- test name o scenario;
- ambiente/DB;
- eventuale commit;
- per test manuali: browser/device e passaggi minimi.

Non incollare segreti, credenziali o dati personali reali nel report.
