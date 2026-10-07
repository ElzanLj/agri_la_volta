# Progetto della gestione contenuti (prompt 16)

Stato: **APPROVATO il 2026-10-07** con le risposte "consigliate" dell'utente (D1–D14, tutte la risposta ✅). Documento di design: **nessun codice, nessuna migrazione, nessun test** sono stati scritti in questa fase; iniziano dal prompt 17. Il comportamento di ogni campo è nel contratto `docs/CAMPI_CONTENUTI.md`, i casi d'abuso in `docs/THREAT_MODEL.md`.

Non è una piattaforma CMS: è una gestione contenuti **specifica e piccola** per un sito di sei appartamenti, con pagine fisse, un solo tipo di sezione, nessun JavaScript e nessun cookie per i visitatori.

## 1. Decisioni approvate

| | Decisione | Risposta |
|---|---|---|
| D1 | Impostazioni in **una tabella a riga unica** con una colonna per dato | A |
| D2 | Testi: paragrafi + elenchi puntati (`- `), sempre testo con escape, nessun HTML né Markdown | B |
| D3 | Inglese mancante: **avviso "testo in preparazione"** e sezione omessa (con l'eccezione delle etichette brevi, §5) | B |
| D4 | Recapiti: **database prima, `.env` come riserva** se il campo è vuoto | A |
| D5 | Foto: **master ricodificato senza EXIF/GPS** (lato lungo 2400–3000 px) in `storage/`, varianti rigenerabili | B |
| D6 | Privacy e cookie **modificabili dall'admin**, pagine con sezioni e flag "bozza" | A |
| D7 | **Niente terza lingua** per ora; il titolare verifica da quali paesi arrivano gli ospiti (`MISSING_DATA`) | A |
| D8 | Pagina "Appartamenti" amministrabile: **solo introduzione e meta** | A |
| D9 | Menu admin in **quattro gruppi**: Gestione · Listino · Sito · Sistema | A |
| D10 | `CAMPI_CONTENUTI.md` è il **contratto vincolante** per i prompt 17–28 | A |
| D11 | Segnalazione dei vuoti a **tre livelli** (frase, indicatori, checklist) | A |
| D12 | `docs/THREAT_MODEL.md` scritto ora e aggiornato da ogni fase | A |
| D13 | **Cache su file** di impostazioni e contatti + **pagina 503** con i contatti se il database non risponde | A |
| D14 | **Blocco ottimistico** sui moduli di modifica | A |

## 2. Cosa resta fuori dall'admin, e perché

Si impostano una volta sull'hosting (variabili d'ambiente o `.env`); l'admin ne mostra al massimo lo **stato** (mai il valore dei segreti).

| Voce | Perché fuori |
|---|---|
| Credenziali del database (`DB_*`) | Servono per avviare l'applicazione: se l'admin le modificasse sbagliando, perderebbe l'accesso all'admin stesso. Sono segreti |
| `APP_SECRET` | Firma i token dei moduli e cifra/deriva chiavi; cambiarlo da un'interfaccia raggiungibile via web significherebbe che chi ruba una sessione admin può invalidare o forgiare token |
| `APP_URL` | Determina host canonico, `Origin` dei moduli, link nelle email e HSTS; un valore sbagliato rende il sito irraggiungibile o i moduli inutilizzabili |
| `APP_ENV`, `APP_DEBUG` | Spengono o accendono le protezioni di produzione; non devono dipendere da un'interfaccia web |
| `APP_TIMEZONE` | Cambia il significato di "oggi" per disponibilità e preavviso; è una scelta di installazione |
| `HSTS_MAX_AGE` | Una volta attivo, i browser rifiutano HTTP per mesi: si attiva solo quando HTTPS funziona, con un'azione tecnica deliberata (`RELEASE_GUIDE`) |
| `PUBLIC_FORM_MIN_SECONDS` | Parametro antispam di installazione, non contenuto |
| `TRUSTED_PROXIES` (non adottata, D4 = A del prompt 15) | Si deciderebbe con l'hosting noto; vuota per impostazione |

Tutto il resto è gestibile dall'admin **entro il prompt 27**: richieste e prenotazioni, blocchi, listino, appartamenti (foto, servizi, regole), pagine e sezioni, foto, impostazioni del sito, avviso globale, email (server, mittente, messaggi, password: vedi §11 punto 2), password dell'admin, backup, aggiornamenti del database, manutenzione, conservazione dei dati, registro errori e stato del sistema. Il **ripristino** di un backup resta guidato via phpMyAdmin (rischio troppo alto per un pulsante).

## 3. Schema

Convenzioni: `utf8mb4_unicode_ci`, InnoDB, DATETIME in UTC, chiavi esterne esplicite, `CHECK` per le invarianti (MySQL ≥ 8.0.16 / MariaDB ≥ 10.2, come la `0007`). Ogni tabella modificabile dal titolare ha `row_version INT UNSIGNED NOT NULL DEFAULT 1` (blocco ottimistico, §7) e `updated_at`. **Nessun JSON**: ogni dato è interrogabile (SPEC §30); lo storico delle modifiche resta in `audit_log`.

Perché ogni tabella nuova è "realmente utile" (SPEC §30):

| Tabella | Perché serve |
|---|---|
| `site_settings` | recapiti, dati aziendali e avviso oggi stanno in `.env` (serve l'FTP per cambiare un telefono) |
| `pages`, `page_translations` | titolo, introduzione e meta di sette pagine fisse oggi nel codice |
| `page_sections`, `page_section_translations` | i testi lunghi (agriturismo, dintorni, privacy, cookie) oggi non esistono o stanno nel codice |
| `media` | nessun modo di pubblicare una foto senza riga di comando e modifiche al codice |
| `apartment_photos` | SPEC §4 chiede le fotografie di ogni appartamento |
| `amenities`, `apartment_amenities` | SPEC §4 chiede i servizi; oggi sono un testo libero per lingua (`0005`), senza traduzioni coerenti né assegnazione verificabile |

### 3.1 `site_settings` (prompt 18; colonne aggiunte dalle fasi 19, 23–27)

Una sola riga: `id TINYINT UNSIGNED PRIMARY KEY CHECK (id = 1)`. La migrazione inserisce la riga con **tutti i campi NULL** (nessun dato inventato). Colonne del prompt 18:

| Colonna | Tipo | Vincolo |
|---|---|---|
| `phone_landline`, `phone_mobile` | VARCHAR(30) NULL | 6–20 cifre (verifica nel servizio) |
| `email` | VARCHAR(254) NULL | email valida |
| `address` | VARCHAR(300) NULL | più righe |
| `whatsapp` | VARCHAR(30) NULL | normalizzabile con `WhatsApp::normalize` |
| `phone_hours_it`, `phone_hours_en` | VARCHAR(100) NULL | |
| `company_name` VARCHAR(200), `vat_number` CHAR(11), `rea_number` VARCHAR(30) | NULL | `vat_number`: 11 cifre |
| `maps_url`, `tripadvisor_url`, `google_reviews_url`, `instagram_url`, `facebook_url` | VARCHAR(500) NULL | `https://` (validato dal servizio) |
| `notice_active` | TINYINT(1) NOT NULL DEFAULT 0 | |
| `notice_text_it`, `notice_text_en` | VARCHAR(200) NULL | `CHECK (notice_active = 0 OR notice_text_it IS NOT NULL)` |
| `notice_until` | DATE NULL | |
| `default_check_in_from`, `default_check_in_until`, `default_check_out_until` | TIME NULL | `CHECK` arrivo dalle < arrivo entro le |
| `house_rules_it`, `house_rules_en` | TEXT NULL | max 2000 (servizio) |

Le colonne delle altre fasi si aggiungono con `ALTER TABLE` nella migrazione della fase che le introduce (preavviso, condizioni di cancellazione e tassa di soggiorno: 23; messaggio personale e soglia richieste: 24; frase tempi di risposta: 25; email, log, manutenzione: 26; conservazione dati: 27; quota foto: 19). Il contratto dei campi dice quali.

**Lettura dei recapiti** (D4 = A): `valore del database` se non vuoto, altrimenti `.env` (`PUBLIC_PHONE`, `PUBLIC_EMAIL`, `PUBLIC_ADDRESS`, `WHATSAPP_NUMBER`). Il prefisso internazionale resta `WHATSAPP_DEFAULT_COUNTRY_CODE`. Le variabili `.env` non vengono copiate nel database; la riserva si potrà togliere in futuro (annotato in `TODO`). Il telefono unico di `PUBLIC_PHONE` diventa il "fisso" solo se il titolare lo decide: non si indovina quale dei due sia.

### 3.2 Pagine e sezioni (prompt 21)

```
pages(page_key VARCHAR(20) PK, is_draft TINYINT(1), hero_media_id INT NULL FK media RESTRICT, row_version, updated_at)
  CHECK page_key IN ('home','farm','apartments','around','contact','privacy','cookies')
  CHECK is_draft = 0 OR page_key IN ('privacy','cookies')
page_translations(page_key FK, locale CHAR(2), title VARCHAR(120) NULL, intro VARCHAR(1000) NULL,
                  meta_title VARCHAR(120) NULL, meta_description VARCHAR(300) NULL, PK(page_key, locale))
page_sections(id PK, page_key FK, sort_order SMALLINT, is_visible TINYINT(1), media_id INT NULL FK media RESTRICT,
              link_url VARCHAR(500) NULL, row_version, updated_at, INDEX(page_key, sort_order))
page_section_translations(section_id FK CASCADE, locale CHAR(2), title VARCHAR(120) NULL, body TEXT NULL,
                          link_text VARCHAR(60) NULL, PK(section_id, locale))
```

- Le sette pagine sono righe **fisse** inserite dalla migrazione: nessuna pagina o URL arbitrario (gli URL stanno in `Routes::PATHS`). `hero_media_id` ha senso solo per `home` (verifica nel servizio).
- `is_draft`: solo privacy e cookie; se attivo compare l'avviso "Bozza" (oggi `legal.draft`). Nasce **attivo** per entrambe finché il titolare non lo spegne.
- **Sezioni**: massimo **30 per pagina** (servizio). La pagina "Appartamenti" (D8) **non** ha sezioni: solo introduzione e meta. Il link di una sezione (testo + indirizzo) è tutto o niente.
- Ordine: pulsanti "Su" / "Giù" (POST che scambia due `sort_order`, senza JavaScript e senza trascinamento).
- `ON DELETE CASCADE` dalle sezioni alle traduzioni; la pagina non si elimina mai (nessun pulsante).

### 3.3 Foto (prompt 19, 20)

```
media(id PK, file_key CHAR(32) UNIQUE, width SMALLINT, height SMALLINT, bytes_total INT UNSIGNED,
      alt_it VARCHAR(150) NOT NULL, alt_en VARCHAR(150) NULL, credit VARCHAR(150) NULL,
      provenance_declared TINYINT(1) NOT NULL CHECK (= 1), row_version, created_at, updated_at)
apartment_photos(id PK, apartment_id FK CASCADE, media_id FK RESTRICT, sort_order SMALLINT,
                 UNIQUE(apartment_id, media_id), INDEX(apartment_id, sort_order))
```

- `file_key`: 128 bit casuali in esadecimale, **senza informazioni** (né data, né id, né nome del file caricato).
- `width`/`height` sono quelle del master. `bytes_total` = master + varianti, per la quota. L'impronta del contenuto per segnalare i doppioni (`content_hash`) è una scelta del prompt 19.
- La prima foto di un appartamento è la **copertina**; massimo 20 foto per appartamento (servizio).
- Una foto **in uso** (galleria, sezione, hero) non si elimina: `ON DELETE RESTRICT` dalle tre tabelle che la usano, con messaggio che dice dove è usata.
- **File** (D5 = B): il caricamento viene **ricodificato** con GD (che non copia EXIF/GPS) in un *master* JPEG (lato lungo 2400–3000 px) in `storage/media/<file_key>.jpg`, mai raggiungibile dal web; da lì si generano le varianti fisse **480 / 800 / 1200 / 1600** px (solo se non superano il master) in `public/media/<aa>/<file_key>-<larghezza>.webp` e `.jpg` (`aa` = primi due caratteri della chiave: cartelle piccole). Le varianti si rigenerano dal master (controllo di coerenza del prompt 26). Scrittura **atomica**: file temporaneo fuori dal web → riga nel database → spostamento finale; in caso di errore si ripulisce tutto.
- `ImageSet::picture()` sarà esteso per leggere da `public/media/` (oggi legge `public/assets/img/` con nomi scelti a mano); `width`/`height` e `alt` vengono dal database.

### 3.4 Servizi (prompt 20)

```
amenities(id PK, name_it VARCHAR(60) NOT NULL UNIQUE, name_en VARCHAR(60) NULL, is_active TINYINT(1), sort_order SMALLINT, row_version, updated_at)
apartment_amenities(apartment_id FK CASCADE, amenity_id FK RESTRICT, PK(apartment_id, amenity_id))
```

Il catalogo parte **vuoto** (o con le voci del legacy *non attive*, se il titolare lo sceglie nel prompt 20). Un servizio assegnato a degli appartamenti non si elimina (si disattiva). La colonna `apartment_translations.amenities` (`0005`, testo libero) resta finché il prompt 20/22 non ne migra il contenuto; poi non è più letta.

### 3.5 Altre colonne nelle tabelle esistenti

`apartments`: piano e accessibilità senza scale, `row_version` (prompt 20); minimo persone e interruttore della regola (prompt 23). `apartment_translations`: descrizione breve (prompt 20). Nessuna colonna cambia significato.

## 4. Migrazioni e test di schema

Una migrazione **per fase**, sempre il prossimo numero libero (da `0008`): `0008` impostazioni (18) · `0009` foto (19) · `0010` foto e servizi degli appartamenti (20) · `0011` pagine e sezioni (21) · `0012` contenuti iniziali (22) · `0013` regole di prenotazione (23) · `0014` operatività (24) · `0015` sistema (26) · `0016` operazioni critiche (27). I numeri sono indicativi: vale "il successivo". Regole:

- ogni fase consegnata aggiunge la sua riga a `migrations/CHECKSUMS` (`php bin/migration-checksums.php`); una migrazione già rilasciata non si tocca mai;
- le migrazioni **non inseriscono dati inventati**; quella del prompt 22 inserisce i testi attuali solo dove il campo è vuoto e **dopo il lancio nessuna migrazione scrive contenuti** (sovrascriverebbe le modifiche del titolare);
- `ScopeTest::testNoGuestAccountsExistInTheDatabaseSchema` elenca le tabelle **esatte**: ogni fase aggiorna quell'elenco, ed è il momento in cui qualcuno rivede la tabella nuova (`site_settings`, `pages`, `page_translations`, `page_sections`, `page_section_translations`, `media`, `apartment_photos`, `amenities`, `apartment_amenities`);
- `.gitignore`: oggi ignora solo `storage/logs`, `storage/sessions` e `storage/mail`. I prompt 18 e 19 aggiungono `/storage/cache/*` e `/storage/media/*` (con `.gitkeep`) e `/public/media/*`: le foto caricate dal titolare non devono mai finire nel repository. Ogni cartella nuova di `storage/` riceve il suo `.htaccess` di negazione;
- `DatabaseTestCase::resetDatabase()` va aggiornato con le tabelle nuove (nota di `SESSION_STATE`).

## 5. Regole di resa e ripiego

1. **Testo**: titolo, introduzione e meta della pagina → valore del database se non vuoto, altrimenti il testo di `content/it.php` / `content/en.php` (che restano come riserva, D4 del prompt 22). I testi fissi dell'interfaccia (menu, moduli, errori, email) **restano nel codice**.
2. **Corpo di una pagina**: le sezioni *visibili*, in ordine. Zero sezioni visibili: per `farm` e `around` resta il segnaposto attuale ("i testi saranno inseriti a cura del titolare"); per `privacy` e `cookies` si usa il testo attuale del codice finché il prompt 22 non li porta nel database (una pagina legale non è mai vuota).
3. **Inglese incompleto (D3 = B)**: una sezione è mostrabile in inglese se per ogni campo compilato in italiano (titolo, testo, testo del link) esiste il corrispondente inglese; altrimenti è **omessa** dalla pagina inglese e, se non resta nessuna sezione, compare "testo in preparazione". La pagina inglese **non** viene messa in `noindex` (scelta B, non C). La checklist segnala ogni traduzione mancante ("manca EN").
4. **Eccezione motivata alla regola 3 per le etichette brevi**: *testo alternativo di una foto, nome di un servizio, nome di un appartamento* → se manca l'inglese si usa l'italiano. Omettere una foto o un servizio per un'etichetta mancante sarebbe peggio; l'attributo `alt` non può dichiarare la lingua (limite noto, segnalato nella checklist).
5. **Formato dei testi lunghi (D2 = B)**: si normalizzano gli a capo (`\r\n` → `\n`) e si divide in blocchi sulle righe vuote. Un blocco in cui **ogni** riga inizia con `- ` è un elenco puntato (una voce per riga); ogni altro blocco è un paragrafo (gli a capo singoli diventano `<br>`). Ogni voce e ogni paragrafo passa da `e()`. Nessun HTML, nessun Markdown, nessun link nel testo: il link è un campo separato della sezione. Un'unica funzione (`ContentText`) fa questa conversione, con test.
6. **Link**: solo `https://` con host, senza credenziali nell'indirizzo, senza spazi né caratteri di controllo, massimo 500 caratteri; i link esterni hanno `rel="noopener noreferrer"`.
7. **Anteprima social e dati strutturati**: `og:image` = foto principale della home / copertina dell'appartamento / foto della pagina, **solo** se esiste una foto con provenienza dichiarata; altrimenti nessuna. Il JSON-LD `LodgingBusiness` è costruito dalle impostazioni, **omette ogni campo vuoto** e non contiene mai testo scritto liberamente dal titolare oltre a nome, recapiti e indirizzo (nessuna descrizione né recensione).
8. **Avviso globale**: compare in cima a tutte le pagine pubbliche se attivo, non scaduto e con il testo della lingua della pagina (senza testo inglese non compare sulle pagine inglesi); è una regione con etichetta, non un `role="alert"` (sarebbe annunciato a ogni pagina).
9. **Sitemap e hreflang** restano derivati dalle rotte; nessun campo canonical manuale.

## 6. Pagine pubbliche quando il database non risponde (D13 = A)

- **Cache** `storage/cache/settings.php`: un array PHP con i soli dati **pubblici** (recapiti, dati aziendali, link, avviso, orari del telefono). Nessun segreto, nessun dato personale, nessuna password. Si riscrive **a ogni salvataggio** delle impostazioni, in modo atomico (file temporaneo + `rename`); se la scrittura fallisce si cancella la cache vecchia e si registra l'evento (mai una cache obsoleta).
- **Lettura**: cache → altrimenti database. Le pagine con sezioni leggono sempre il database.
- **503**: se la connessione al database fallisce su una pagina pubblica, risposta **503** con `Retry-After`, `noindex`, italiano o inglese secondo il percorso, e i contatti letti dalla cache (se manca, solo il testo generico). L'admin mostra un errore chiaro. Il 503 non rivela host, utenti né messaggi del database.
- La pagina 503 serve anche per la modalità manutenzione (prompt 26) e per le migrazioni in attesa (prompt 27).

## 7. Due persone che salvano insieme (D14 = A)

Un solo helper riusabile (prompt 18) per tutti i moduli di modifica. Ogni modulo porta in un campo nascosto la **versione** (`row_version`) letta all'apertura. Il salvataggio fa `UPDATE … SET …, row_version = row_version + 1 WHERE id = ? AND row_version = ?`; se nessuna riga cambia, il contenuto è stato modificato nel frattempo: **non si salva**, il modulo riappare con **tutto ciò che l'utente ha scritto** e il messaggio "Qualcun altro ha modificato questa pagina: ricarica prima di salvare", con il link per aprire il valore attuale. (Si usa un contatore e non la data: due salvataggi nello stesso secondo non si distinguerebbero con un `DATETIME`.) Vale per impostazioni, pagine, sezioni, servizi, foto, appartamenti e regole; non per il listino già esistente (fuori dallo scopo).

## 8. Organizzazione dell'admin (D9 = A)

Quattro gruppi come nella bozza `docs/mockup-admin/`: **Gestione** (Home, Richieste, Prenotazioni, Arrivi e partenze, Calendario, Blocchi) · **Listino** (Listino, Simulatore prezzi) · **Sito** (Pagine, Appartamenti, Servizi, Foto, Impostazioni) · **Sistema** (Email, Statistiche, Storico, Export, Manutenzione, Coerenza dati, Stato del sistema, Aggiornamenti, Backup, Account). Solo HTML e CSS: elenchi annidati con intestazioni, nessun menu a tendina e nessun JavaScript. Le voci compaiono man mano che le fasi le realizzano; il raggruppamento si fa nel prompt 25.

Nuove rotte (tutte sotto `/admin`, tutte nel file `routes_admin.php`, quindi dietro le tre guardie; le modifiche sono POST, le GET non scrivono): `/admin/impostazioni` (18), `/admin/foto` (19), `/admin/servizi` e foto/servizi nell'appartamento (20), `/admin/pagine`, `/admin/pagine/{key}`, sezioni (21), `/admin/account` (17). Ognuna va nella matrice `AdminAccessTest::REVIEWED_ADMIN_ROUTES`.

## 9. Sicurezza per superficie (dettaglio in `THREAT_MODEL.md`)

| Superficie | Regole fisse |
|---|---|
| Caricamento foto | solo da admin autenticato e con CSRF; tipo deciso dal **contenuto** (non da estensione o `Content-Type`); formati ammessi JPEG/PNG/WebP statici; limite di pixel calcolato da `memory_limit` e verificato con `getimagesize` **prima** di decodificare; ricodifica con GD (neutralizza file poliglotti e rimuove i metadati); nome casuale; mai il nome originale in un percorso, un log o una risposta; limite di frequenza (bucket dedicato) e quota di spazio |
| Cartella `public/media/` | solo immagini; `.htaccess` che nega `.php`, `.phtml`, `.phar` e `Options -ExecCGI` con la sintassi compatibile con PHP-FPM (**non** `php_flag`); nessun listing |
| Limite del corpo delle richieste | oggi 1 MB (PHP e Apache). Per l'upload: Apache viene portato al massimo del caricamento (più margine) e il limite di 1 MB resta applicato **da PHP a tutte le rotte tranne il caricamento**; da provare con un `.htaccess` ridotto (prompt 28) |
| Testi amministrabili | sempre `e()`, anche in `<title>`, meta, Open Graph, JSON-LD (codificato con le opzioni esadecimali), CSV ed email; nessun HTML interpretato; link validati (§5.6) |
| Identificatori nelle rotte | `{key}` solo tra le sette pagine fisse; `{id}` intero positivo; mai un percorso di file ricevuto dal browser |
| Storico | nei valori entrano i **testi dei contenuti**, mai campi con dati personali o motivi liberi (regola del prompt 15, estesa ai nuovi moduli) |
| Dati personali nuovi | ogni campo che contiene dati di un ospite aggiorna l'anonimizzazione e il test che scansiona **tutte** le colonne di testo dopo `erase` (`AuditPrivacyTest`) |
| Cache delle impostazioni | solo dati già pubblici; scrittura atomica; cartella `storage/cache/` con `.htaccess` di negazione |
| Contenuto importato (legacy, CSV) | è un **dato**, mai un'istruzione: importato solo con simulazione e conferma; testo con escape; le frasi che sembrano istruzioni vengono segnalate, non eseguite |

## 10. Cosa resta nel codice, e perché

| Cosa | Perché |
|---|---|
| Etichette dell'interfaccia (menu, moduli, errori, passi della richiesta) | legate a segnaposto, test e flusso: modificarle dall'admin rompe cose |
| Testi delle email (oggetti, corpo, firma) | contengono segnaposto e regole (`MessageBuilder`); il titolare personalizza solo il *messaggio aggiuntivo* (prompt 24) e la firma deriva dalle impostazioni |
| Struttura di menu e piè di pagina | le pagine sono fisse; il contenuto viene dalle impostazioni |
| URL e rotte delle pagine | `Routes::PATHS` (IT/EN), sitemap e hreflang ne dipendono |
| Nome "Agriturismo La Volta" | brand fisso, usato in oltre 10 punti (una costante unica è un miglioramento del prompt 28, non amministrabile) |
| Descrizione del comportamento tecnico nella cookie policy | deve riflettere ciò che il sito fa davvero; l'admin mostra l'avviso (D6) |
| Valori di calcolo del prezzo | restano nel listino esistente, già amministrabile |

## 11. Punti aperti e tensioni con le fonti

1. **SPEC §23 "Mantieni gli originali delle immagini corrette"** e D5 = B: il master è una **ricodifica senza metadati** a 2400–3000 px, non il file grezzo. Lo si legge come "non distruggere le fotografie corrette": l'originale resta quello che il titolare conserva sul proprio telefono o computer (`docs/IMAGES.md` già prevedeva l'originale *fuori da* `public/`). Il design non conserva il file grezzo perché contiene GPS e modello del telefono e finirebbe nei backup. **Da confermare esplicitamente all'inizio del prompt 19**; se si vuole anche il grezzo, serve una decisione consapevole sulla privacy dei backup.
2. **`AGENTS.md`: "SMTP tramite variabili d'ambiente"** contro il prompt 26 (opzioni B e C: server, utente, mittente e perfino la password cifrata dall'admin). Nessuna di quelle opzioni è decisa qui. Quando si arriva al prompt 26, se si sceglie B o C va **scritta una eccezione in `AGENTS.md`** con il consenso dell'utente (finding A13); con A resta tutto in `.env`.
3. **Il container di sviluppo non ha GD, exif né zip** (verificato: `gd=NO exif=NO zip=NO`; limiti PHP del container `upload_max_filesize=2M`, `post_max_size=8M`, `memory_limit=128M`). Senza GD la libreria foto non si può sviluppare né provare; senza zip non si prova il backup con le foto. All'inizio del prompt 19 va chiesto il consenso a **modificare l'immagine Docker di sviluppo** (`docker/php/Dockerfile`: `gd` con jpeg, webp e freetype se serve, `exif`, `zip`; `php.ini` con limiti di upload adeguati). È solo per lo sviluppo, ma è un cambiamento dell'ambiente: non si fa in silenzio. Sull'hosting reale le estensioni vanno verificate: `php bin/check-production.php` le controllerà (prompt 28) e "Stato del sistema" (prompt 26) le mostrerà. Hosting senza GD: il caricamento viene rifiutato con un messaggio chiaro (D2 del prompt 19, consigliata A).
4. **SPEC §14** elenca le funzioni dell'admin e non cita la gestione dei contenuti: è un'estensione **richiesta dall'utente** e approvata nella roadmap; non contraddice la specifica ma non ne discende. Nessun requisito della SPEC è eliminato.
5. **Terza lingua (D7 = A)**: nessuna struttura multilingua generica. Se un giorno si aggiunge il tedesco servirà una migrazione per colonna/tabella di traduzione e le chiavi di `content/*.php`: costo noto e accettato. Verifica del titolare registrata in `MISSING_DATA`.
6. **Piano di backup**: le foto (master e varianti) sono file e non stanno nel dump del database; il prompt 27 le include (zip a blocchi). Fino ad allora il backup di una installazione con foto è incompleto: è scritto nella checklist di consegna.

## 12. Piano di test per i prompt 17–27

Valgono per ogni fase: la suite completa (`unit`, `integration`, `http`, `concurrency`) in ordine normale e casuale, i guardiani del prompt 15 (checksum, escape nei template, funzioni vietate, indirizzi esterni, matrice delle rotte), e per **ogni campo** le sette prove del contratto (vuoto · solo spazi · troppo lungo · formato non valido · HTML/`<script>` · resa in IT e EN · voce nello Storico).

| Fase | Prove specifiche |
|---|---|
| 17 Account | cambio password con la password attuale; sessioni chiuse; password comuni rifiutate; riautenticazione scaduta dopo 5 minuti; "esci da tutti i dispositivi"; procedura senza SSH provata su un database vuoto |
| 18 Impostazioni | lettura database→`.env`; cache scritta in modo atomico e riscritta a ogni salvataggio; 503 con database irraggiungibile (porta chiusa) e contatti dalla cache; blocco ottimistico con due salvataggi (anche in parallelo); JSON-LD senza campi vuoti; avviso globale (scadenza, lingua mancante, `role`); firma delle email dalle impostazioni |
| 19 Foto | file ostili (poliglotti, estensione falsa, HEIC, CMYK, WebP animato, PNG con trasparenza, bomba di decompressione, nome con `../` e a capo, EXIF con GPS) → rifiutati o ripuliti; il GPS **non** compare in nessun file prodotto; `memory_limit` basso; quota; scrittura atomica con errore a metà; `public/media/` non esegue PHP (provato con un file `.php` e con `.phtml`); limite del corpo solo per la rotta di upload; foto in uso non eliminabile |
| 20 Appartamenti | galleria e copertina; servizi attivi/inattivi; servizio in uso non eliminabile; avviso di disattivazione con prenotazioni future; confronto generato dai dati; `og:image` solo con foto verificata |
| 21 Pagine | sezioni visibili/nascoste (una nascosta non è mai nel sorgente), ordine, limite di 30, link tutto-o-niente, regola D3 in inglese, ripiego sul testo del codice, privacy e cookie con flag bozza, pagina Appartamenti senza sezioni |
| 22 Migrazione | ogni testo del codice è nel database o è registrato come riserva; nessuna sovrascrittura di un campo già compilato (idempotenza); testi del legacy solo come sezioni nascoste "[DA VERIFICARE]"; redirect 301 di `/dovesiamo` |
| 23–24 | come da prompt; per ogni campo personale nuovo: anonimizzazione e scansione dell'intero database |
| 25 | matrice delle rotte, checklist di pubblicazione, indicatori "manca EN", ripristino dallo Storico solo di campi di testo |
| 26–27 | stato del sistema senza segreti; manutenzione; aggiornamenti con backup obbligatorio; prova di ripristino automatica; `ScopeTest` e `ArchitectureGuardTest` aggiornati |

Ogni fase che aggiunge un campo ne aggiunge la riga in `CAMPI_CONTENUTI.md` (e un test del prompt 28 verifica che ogni `name="…"` dei moduli admin abbia la sua riga), e ogni superficie nuova una riga in `THREAT_MODEL.md`.
