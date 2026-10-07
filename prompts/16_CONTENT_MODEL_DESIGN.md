# Prompt 16 — Progetto della gestione contenuti

Prerequisito: prompt 14 completato (`docs/CONTENT_INVENTORY.md` verificato).

Contesto: sito di un agriturismo con pagine fisse (`app/Site/Routes.php`), sei appartamenti già gestiti dall'admin, admin unico senza JavaScript, nessun cookie per i visitatori, hosting condiviso. Non è una piattaforma CMS generica. Il prompt 15 ha già corretto l'esistente (Storico senza testo libero personale, migrazioni con lock, checksum e splitter sicuro, test-guardiani attivi): questa fase li dà per presenti.

Riferimento visivo (non vincolante): `docs/mockup-admin/` — apri `leggimi.html`.

## Obiettivo

Decidere il **modello minimo** che permetta a una persona non tecnica di modificare dall'admin solo ciò che ha senso, e farlo approvare **prima** di scrivere codice.

## Prima di modificare

- Leggi `docs/CAMPI_CONTENUTI.md` (bozza), `docs/CONTENT_INVENTORY.md`, `docs/PRE_RELEASE_ROADMAP.md` (§3), `docs/SPEC.md` §4, §14, §20–27, `docs/DECISIONS.md` (Fasi 5–7), lo schema in `migrations/`, `ApartmentRepository`, `ApartmentAdminService`, `ImageSet`, `bin/optimize-images.php`.
- Leggi anche `docs/GUARDRAIL_FASI.md`, `docs/SECURITY_REVIEW.md`, `docs/REVIEW_PRE_ROADMAP.md` (schede su foto, sistema, CMS e le sezioni "Cosa noterebbe un senior…") e `docs/mockup-admin/leggimi.html`.
- Controlla il diff Git e lo stato dei prompt 14 e 15.

## Proposta di partenza (da verificare, non da copiare)

- `site_settings`: una riga con colonne tipizzate.
- Pagine **fisse** con campi per lingua (titolo, intro, meta title, meta description), hero in home, flag "bozza legale" per privacy/cookie.
- **Un solo tipo di sezione** ordinabile: titolo, testo, foto facoltativa, visibile sì/no; testi per lingua in tabella figlia.
- `media`: file con nome casuale, dimensioni, alt IT/EN, credito, provenienza dichiarata.
- Appartamenti: `apartment_photos`, `amenities` + `apartment_amenities`.
- Esclusi: page builder, blocchi multipli, drag&drop, pagine o menu arbitrari, editor visuale/HTML, workflow bozza/pubblicato, revisioni (basta `audit_log`), testi UI ed email modificabili.

## Domande per l'utente (all'inizio della fase)

Prima di modificare qualsiasi file, apri `docs/RISPOSTE_UTENTE.md`: se l'utente ha già segnato le risposte per questo prompt, mostrale e chiedi solo conferma. Altrimenti presenta le domande di questa sezione **così come sono scritte** (risposte possibili e consiglio) e **fermati finché non risponde**.

Le domande sono di due tipi, indicati nel titolo:

- **[titolare]**: decisioni sul business (prezzi, regole, testi, comunicazioni agli ospiti, dati legali, servizi esterni). Non applicare mai una risposta ✅ al posto dell'utente: serve la sua risposta scritta in chat ("consigliate" vale, se detto esplicitamente per questa fase). Senza risposta la fase non inizia.
- **[tecnica]**: scelte di implementazione. Se l'utente risponde "consigliate" o non ha preferenze, vale la risposta ✅; elencale comunque nel riepilogo finale.

Registra ogni scelta in `docs/DECISIONS.md`. In `docs/RISPOSTE_UTENTE.md` (casella con `x`) scrivi **solo** ciò che l'utente ha detto in chat, mai risposte tue. Le voci rinviate o rifiutate vanno in `docs/TODO.md` con il motivo. Non implementare nulla che non sia stato approvato. Se una voce approvata supera lo scope di questa fase, proponi di spostarla e fermati.

### D1 — Dove salvare le impostazioni del sito [tecnica]

| Risposta | Pro / contro |
|---|---|
| A. ✅ Una tabella a riga unica con una colonna per ogni dato | Tipi e vincoli chiari, validazione in un punto; aggiungere un dato = una migrazione |
| B. Tabella chiave/valore | Nuovi dati senza migrazione; nessun tipo né vincolo, errori silenziosi |
| C. Restare su `.env` | Zero lavoro; il titolare deve usare l'FTP per cambiare un telefono |

**Consiglio: A.** I dati sono pochi e noti: ogni colonna è documentata e validata.

### D2 — Formattazione dei testi nelle pagine [tecnica]

| Risposta | Pro / contro |
|---|---|
| A. Solo paragrafi (riga vuota = nuovo paragrafo) | Semplicissimo; niente elenchi |
| B. ✅ Paragrafi + elenchi puntati con righe che iniziano con `- ` | Copre quasi tutti i casi (servizi, cosa portare); sempre testo con escape |
| C. Markdown ridotto (grassetto, link) | Più espressivo; più casi da gestire e testare |
| D. Editor visuale (WYSIWYG) | Comodo; richiede JavaScript e un sanitizzatore HTML: nuova superficie XSS |

**Consiglio: B.** Massimo risultato con rischio quasi nullo.

### D3 — Pagina inglese quando il testo inglese manca [titolare]

| Risposta | Pro / contro |
|---|---|
| A. Mostrare il testo italiano | Pagina mai vuota; un ospite straniero legge italiano senza preavviso, pessimo per SEO |
| B. ✅ Mostrare un avviso "testo in preparazione" (come oggi) e omettere la sezione | Onesto e coerente con le scelte attuali; pagine EN più povere finché manca la traduzione |
| C. Pagina EN in `noindex` finché incompleta | Protegge la SEO; più logica da gestire |

**Consiglio: B.** È il comportamento già usato dal sito; la dashboard segnalerà le traduzioni mancanti.

### D4 — Recapiti oggi in `.env` (`PUBLIC_*`, `WHATSAPP_NUMBER`) [tecnica]

| Risposta | Pro / contro |
|---|---|
| A. ✅ Database prima, `.env` come riserva se il campo è vuoto | Nessuna interruzione durante il passaggio; due fonti da documentare |
| B. Import una tantum, poi solo database | Una sola fonte; serve un passaggio manuale in produzione |
| C. Solo database da subito | Più semplice; i valori in `.env` vanno reinseriti a mano |

**Consiglio: A**, con nota per rimuovere la riserva in futuro.

### D5 — Cosa conservare della foto caricata [tecnica]

L'originale di una foto da telefono contiene la **posizione GPS** e il modello del telefono: se resta sul server finisce anche nei backup.

| Risposta | Pro / contro |
|---|---|
| A. Solo le varianti ridimensionate | Meno spazio e nessun metadato; per cambiare dimensioni bisogna ricaricare la foto |
| B. ✅ Un "master" ricodificato **senza EXIF/GPS** (lato lungo 2400–3000 px) fuori dalla cartella pubblica (`storage/`), da cui si rigenerano le varianti | Varianti rigenerabili e nessun dato nascosto; più spazio occupato, da includere nei backup |
| C. L'originale grezzo fuori dalla cartella pubblica | Massima qualità; GPS e dati del telefono restano nei file e nei backup: **sconsigliata** |

**Consiglio: B.**

### D6 — Privacy e cookie modificabili dall'admin [titolare]

| Risposta | Pro / contro |
|---|---|
| A. ✅ Sì, come pagine con sezioni e flag "bozza" | Il titolare inserisce il testo del consulente senza sviluppatore |
| B. Restano nel codice | Nessun rischio di modifica accidentale; ogni aggiornamento richiede uno sviluppatore |
| C. Solo privacy; la cookie policy resta nel codice | La cookie policy descrive il comportamento tecnico: protetta; meno uniforme |

**Consiglio: A**, con avviso nell'admin che la cookie policy deve descrivere ciò che il sito fa davvero.

### D7 — Terza lingua [titolare]

| Risposta | Pro / contro |
|---|---|
| A. ✅ No per ora; il titolare verifica da quali paesi arrivano gli ospiti (dati Booking/Novasol) | Nessuna complessità senza dati; decisione rinviata con criterio |
| B. Tedesco subito | Utile se gli ospiti sono di lingua tedesca; un terzo set di testi da mantenere |
| C. Struttura multilingua generica | Pronta per ogni lingua; lavoro oggi per un bisogno non provato |

**Consiglio: A.** Registrare la verifica in `MISSING_DATA`.

### D8 — Pagina "Appartamenti" (elenco) amministrabile [tecnica]

| Risposta | Pro / contro |
|---|---|
| A. ✅ Sì, solo introduzione e meta | Poco lavoro, utile per SEO |
| B. No | Nessun lavoro; testo fisso nel codice |

**Consiglio: A.**

### D9 — Organizzazione del menu admin [tecnica]

| Risposta | Pro / contro |
|---|---|
| A. ✅ Quattro gruppi: Gestione · Listino · Sito · Sistema | Chiaro per un non tecnico; solo HTML/CSS |
| B. Elenco piatto come oggi | Nessun lavoro; 15+ voci difficili da scorrere |
| C. Menu a tendina | Compatto; serve JavaScript |

**Consiglio: A.** Vedi `docs/mockup-admin/`.

### D10 — Contratto dei campi [tecnica]

`docs/CAMPI_CONTENUTI.md` è una bozza con ogni campo amministrabile: obbligatorietà, limiti, cosa succede sul sito se resta vuoto, dove viene segnalato.

| Risposta | Pro / contro |
|---|---|
| A. ✅ Approvarlo (con eventuali correzioni) come riferimento vincolante per i prompt 17–28 | Ogni campo si comporta in modo prevedibile e testato; il titolare sa sempre cosa succede |
| B. Decidere campo per campo durante le fasi | Più libertà; comportamenti diversi tra pagine, test a macchia di leopardo |

**Consiglio: A.** Mostra all'utente le righe che hai corretto rispetto alla bozza.

### D11 — Segnalazione dei campi vuoti [tecnica]

| Risposta | Pro / contro |
|---|---|
| A. ✅ Tre livelli: frase sotto ogni campo facoltativo ("se lo lasci vuoto…"), indicatori negli elenchi ("manca EN", "senza foto"), checklist in dashboard | Il titolare non deve indovinare; nessun blocco inutile |
| B. Solo checklist in dashboard | Meno lavoro; il titolare scopre le conseguenze dopo |
| C. Rendere obbligatori quasi tutti i campi | Niente vuoti; il titolare non può salvare finché non ha tutti i testi, e i testi mancano |

**Consiglio: A.**

### D12 — Threat model delle nuove superfici [tecnica]

| Risposta | Pro / contro |
|---|---|
| A. ✅ Una pagina `docs/THREAT_MODEL.md`, scritta ora e aggiornata da ogni fase che aggiunge una superficie (casi d'abuso, impatto, controllo previsto e fase) | Le protezioni nascono dai casi d'abuso, non dal caso; poche righe da mantenere |
| B. Nessun documento | Nessun lavoro; le protezioni restano sparse nei prompt |

**Consiglio: A.**

### D13 — Pagine pubbliche quando il database non risponde [tecnica]

Con la gestione contenuti **ogni pagina pubblica leggerà dal database**: oggi Privacy e Cookie funzionano anche senza.

| Risposta | Pro / contro |
|---|---|
| A. ✅ Cache su file di impostazioni e contatti (riscritta a ogni salvataggio) + pagina 503 con i contatti e `Retry-After` quando il database non risponde | Le pagine restano brevi e leggibili anche con il database in difficoltà; un file in più da gestire |
| B. Solo la pagina 503 | Meno lavoro; nessun contatto se il database è giù |
| C. Niente | Errore generico 500 |

**Consiglio: A.** La cache non contiene mai segreti e viene scritta in modo atomico.

### D14 — Due persone che modificano lo stesso contenuto [tecnica]

| Risposta | Pro / contro |
|---|---|
| A. ✅ Blocco ottimistico: ogni modulo porta la data dell'ultima modifica; se nel frattempo è cambiata compare "Qualcun altro ha modificato questa pagina: ricarica prima di salvare", senza perdere ciò che hai scritto | Nessuna modifica sovrascritta in silenzio; un helper riusabile da tutti i moduli |
| B. L'ultimo salvataggio vince | Nessun lavoro; si perdono modifiche senza accorgersene |

**Consiglio: A.**

## Decisioni tecniche da scrivere nel design (senza chiedere)

Colonne vs tabelle figlie per ogni elemento (JSON solo se motivato); cartella delle foto (proposta `public/media/`) con divieto di esecuzione script; eccezione al limite di 1 MB solo per la rotta di upload; numerazione migrazioni (una per fase, prossimo numero libero); aggiornamento dell'elenco tabelle in `ScopeTest`; piano di test per i prompt 17–27; limite al numero di sezioni per pagina (proposta: 30); ogni nuova migrazione aggiunge la propria riga a `migrations/CHECKSUMS` quando la fase viene consegnata; regola "nessun testo libero personale nello Storico" estesa ai nuovi moduli; ogni campo personale nuovo aggiorna anonimizzazione e test di scansione.

## Output

- `docs/CAMPI_CONTENUTI.md` aggiornato con le correzioni e le risposte (stato: APPROVATO, data).
- In `docs/CMS_DESIGN.md` una sezione "Cosa resta fuori dall'admin" (credenziali DB, `APP_SECRET`, `APP_URL`, `APP_ENV`, `APP_DEBUG`, `APP_TIMEZONE`, `HSTS_MAX_AGE`, `PUBLIC_FORM_MIN_SECONDS`) e perché; tutto il resto è gestibile dall'admin entro il prompt 27.
- `docs/CMS_DESIGN.md` (breve): schema con colonne, vincoli e indici; regole di resa e fallback; organizzazione dell'admin; regole di sicurezza per superficie; elenco di ciò che resta nel codice e perché.
- `docs/THREAT_MODEL.md` (se D12 = A), una pagina, che copre almeno: upload di file; testi amministrabili (XSS, link, SEO e JSON-LD manipolati); impostazioni email (host SMTP scelto dall'admin, abuso del modulo come strumento di spam); strumenti di sistema (aggiornamenti, backup con dati personali, registro errori); sessione admin rubata; token dei calendari; ricerca; contenuti importati dal legacy o da CSV trattati come istruzioni; cache delle impostazioni. Per ogni caso: attacco, impatto, controllo previsto, fase che lo realizza.
- Voci in `DECISIONS`; nuove mancanze in `MISSING_DATA`.

## Stop condition

Fermati quando il design è scritto, coerente con l'inventario e con le risposte dell'utente. Fermati prima di iniziare se l'utente non ha risposto alle domande [titolare]. **Non** creare migrazioni, codice, template o test: iniziano dal prompt 17.
