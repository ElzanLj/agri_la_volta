# Final review

> **Da riaprire:** la review del 2026-10-07 (`docs/REVIEW_PRE_ROADMAP.md`) ha trovato punti non coperti (vedi `docs/TODO.md`, sezione "Finding della review 2026-10-07"); si ricontrollano nei prompt 15 e 30. Gli stati PASS sotto non sono stati modificati. Questo documento descrive il progetto **prima** della gestione contenuti: sarà aggiornato nel prompt 30, non riscritto.

Stato: **ESEGUITA il 2026-10-06** (prompt 13), sul repository reale, rileggendo `docs/SPEC.md` per intero (§1–§41) e confrontandola con il codice, i test e i documenti. Nessun deploy è stato eseguito né è autorizzato da questo documento.

## Sintesi

Il sito soddisfa **tutti i requisiti funzionali e tecnici della SPEC che si possono verificare senza un ambiente reale**: richieste a passi senza pagamenti né account, conferma solo da admin, nessuna sovrapposizione (provata con processi concorrenti), prezzi e disponibilità solo sul server, tariffe configurabili, email resilienti, area admin protetta con storico ed esportazioni, sito IT/EN con SEO, sicurezza e strumenti per la privacy, documentazione di installazione e manutenzione.

**Non è ancora pubblicabile**, per ragioni che non dipendono dal codice:

1. mancano dati e contenuti del titolare (listino, testi, foto, recapiti, WhatsApp, descrizioni degli appartamenti, testi legali, credenziali SMTP);
2. restano **NOT RUN** le prove che richiedono persone, dispositivi o servizi reali: tastiera, screen reader, mobile, desktop, installazione su un hosting reale, consegna email reale, HTTPS reale.

Esito della matrice: **23 criteri PASS, 4 PARTIAL (18, 19, 20, 24), 0 FAIL, 0 BLOCKED**. Rileggendo l'intera SPEC sono emerse 6 lacune non coperte dai 27 criteri: 5 sono state corrette in questa review, 1 è una scelta documentata (vedi sotto).

## Metodo

1. Rilettura integrale di `docs/SPEC.md` e confronto sezione per sezione con il codice (`app/`, `templates/`, `migrations/`, `content/`), i test e i documenti.
2. Verifica a campione sul codice delle voci che la matrice dà per scontate (campi dell'amministrazione degli appartamenti, testi delle pagine, rotte).
3. Correzione delle sole lacune a basso rischio e contenute; ogni correzione ha test e una prova di sensibilità.
4. Suite completa eseguita dopo le correzioni (vedi Test finali).

## Criteri di accettazione (SPEC §41)

Evidenza completa per ogni riga in `docs/ACCEPTANCE_MATRIX.md`. Riepilogo:

| # | Criterio | Stato |
|---|---|---|
| 1 | Nessun sistema di pagamento | PASS |
| 2 | Nessun dato carta | PASS |
| 3 | Nessun account/login ospite | PASS |
| 4 | Richiesta non presentata come prenotazione confermata | PASS |
| 5 | Solo admin può confermare | PASS |
| 6 | Nessun overlap tra confirmed dello stesso appartamento | PASS |
| 7 | Disponibilità ricontrollata lato server | PASS |
| 8 | Prezzo ricalcolato lato server | PASS |
| 9 | Tariffe modificabili senza codice | PASS |
| 10 | Adulti/bambini/animali influiscono sul prezzo | PASS |
| 11 | Prenotazioni esterne inseribili manualmente | PASS |
| 12 | Cancellazione libera le date | PASS |
| 13 | Cancellazione genera bozza email modificabile | PASS |
| 14 | Fallimento SMTP non perde la richiesta | PASS (consegna reale NOT RUN) |
| 15 | Area admin protetta | PASS |
| 16 | Storico modifiche presente | PASS |
| 17 | Sito IT/EN | PASS (inglese provvisorio) |
| 18 | Utilizzabile da tastiera | **PARTIAL** (controlli automatici sì, prova manuale NOT RUN) |
| 19 | Responsive | **PARTIAL** (prova su dispositivi NOT RUN) |
| 20 | Immagini ottimizzate | **PARTIAL** (nessuna foto pubblicata) |
| 21 | Immagini di provenienza dubbia segnalate | PASS |
| 22 | Ogni appartamento ha pagina indicizzabile | PASS |
| 23 | CSV esportabile | PASS |
| 24 | Funziona su hosting Linux condiviso | **PARTIAL** (verificato in Docker/Apache, nessun hosting reale) |
| 25 | Non dipende da un provider specifico | PASS |
| 26 | README/config permettono l'installazione altrove | PASS (nessuno ha ancora installato seguendo la guida) |
| 27 | Nessun servizio esterno modificato senza autorizzazione | PASS |

## Conformità alla SPEC, sezione per sezione

| § | Argomento | Stato | Evidenza / nota |
|---|---|---|---|
| 1 | Hosting e infrastruttura | PASS | PHP + MySQL/MariaDB senza Docker, VPS, Node, Redis né servizi cloud in produzione; Docker solo per lo sviluppo. Dominio, DNS e posta **non toccati** |
| 2 | Architettura | PASS | PHP senza framework; separazione in `Http`, `Domain`, `Service`, `Repository`, configurazione in `.env` (`docs/ARCHITECTURE.md`) |
| 3 | Nessun pagamento | PASS | `ScopeTest`; i file di pagamento del legacy sono stati rimossi senza leggerne i dati |
| 4 | Appartamenti | **PARTIAL** | Pagina dedicata e indicizzabile per ciascuno; nome, slug, descrizioni IT/EN, capienza, camere, letti, **servizi** (aggiunti in questa review), prezzo indicativo, regole, check-in/out, stato attivo; supplementi nelle regole di prezzo. **Fotografie: BLOCKED** (nessuna con provenienza verificata, segnaposto marcati) |
| 5 | Prezzi | PASS | Configurabili da admin per appartamento, periodo, adulti, bambini, animali, supplementi, soggiorno minimo; nessun importo inventato; listino reale da fornire |
| 6 | Calcolo del soggiorno | PASS | `PriceCalculator`, riepilogo chiaro nel passo 4, ricalcolo all'invio |
| 7 | Disponibilità | PASS | `[check_in, check_out)`, transazioni con lock per appartamento, `ConcurrencyTest` |
| 8 | Flusso pubblico di richiesta | PASS | 4 passi + "Richiesta ricevuta"; mai "Prenotazione confermata" |
| 9 | Dati cliente | PASS | nome, cognome, email, telefono, note, consenso privacy; nessun account; dati conservati in caso di errore |
| 10 | Stati | PASS | `pending`, `confirmed`, `rejected`, `cancelled`; richieste e prenotazioni distinte |
| 11 | Altri canali | PASS | inserimento manuale con origine; influisce sulla disponibilità |
| 12 | Novasol e gestione esterna | PASS | solo la struttura prevista: gestione diretta/agenzia, "non accetta richieste online" per disponibilità manuale; nessuna integrazione né regola inventata |
| 13 | Booking, Airbnb, iCal | PASS | non implementati, come richiesto |
| 14 | Area amministrativa | PASS | tutte le funzioni elencate (login/logout, richieste con filtri, conferma/rifiuto, prenotazioni manuali, cancellazione, blocchi, calendario, prezzi e regole, appartamenti, export); non nella navigazione pubblica; nessuna registrazione |
| 15 | Storico delle modifiche | PASS | `audit_log` con entità, operazione, data, valori prima/dopo; pagina *Storico* |
| 16 | Cancellazione | PASS | stato `cancelled`, date libere, storico, bozza modificabile mai inviata da sola |
| 17 | Email | PASS | notifica al gestore, conferma, rifiuto; SMTP da variabili d'ambiente. **Consegna reale NOT RUN** |
| 18 | Robustezza dell'invio | PASS | coda transazionale, retry, nessun segreto nei log |
| 19 | WhatsApp | PASS | link `wa.me` con messaggio modificabile; **ora anche nel flusso di richiesta** con appartamento, date e ospiti (corretto in questa review); compare solo con il numero configurato (mancante) |
| 20 | Multilingua | PASS | contenuti IT/EN separati, nessuna traduzione automatica, canonical e hreflang; inglese provvisorio |
| 21 | Pagine pubbliche | PASS | tutte le pagine richieste esistono in IT ed EN; i testi di *L'agriturismo* e *Dintorni* sono da fornire |
| 22 | Design e UX | **PARTIAL** | struttura, contrasto, bersagli e assenza di popup/caroselli/`alert()` verificati; **prova visiva e su dispositivi NOT RUN**; foto assenti; un nuovo design è previsto dal titolare |
| 23 | Fotografie | **PARTIAL** | provenienza dubbia segnalata (`docs/IMAGES.md`), nessun hotlink, markup responsive pronto; nessuna foto pubblicata né ottimizzata |
| 24 | Analytics e cookie | PASS | nessun tracciamento, nessun cookie ai visitatori |
| 25 | Mappe | PASS | solo un link a Google Maps dall'indirizzo configurato (aggiunto in questa review), nessuna mappa incorporata |
| 26 | Accessibilità | **PARTIAL** | HTML semantico, skip link, label, errori associati, contrasto, bersagli, riduzione del movimento verificati automaticamente; **tastiera, screen reader e menu mobile NOT RUN** (il menu è un elenco sempre visibile, senza `aria-expanded`) |
| 27 | SEO | PASS | lingua, title e description unici, canonical, Open Graph, favicon, `robots.txt`, sitemap, breadcrumb, 404; Schema.org con dati reali per attività (solo con recapiti configurati) e **appartamenti** (aggiunto in questa review, solo campi inseriti) |
| 28 | Sicurezza | PASS | `docs/SECURITY_REVIEW.md` (nessun finding alto) |
| 29 | Antispam | PASS | honeypot, controllo temporale, rate limit, token firmato; nessun servizio esterno |
| 30 | Database | PASS | tabelle richieste, traduzioni, regole prezzi, coda email; migrazioni SQL versionate 0001–0005 |
| 31 | Esportazione dati | PASS | CSV di richieste e prenotazioni con filtri per periodo |
| 32 | Privacy | **PARTIAL** | consenso obbligatorio, minimizzazione, esportazione e anonimizzazione (`bin/privacy.php`), niente dati personali nei log; **testi di privacy e cookie sono bozze da far verificare** al titolare/consulente |
| 33 | Prestazioni | PASS | nessun JavaScript, nessun font o risorsa esterna, asset con cache lunga; **codice morto e asset inutilizzati rimossi con `legacy/`** (corretto in questa review). Lighthouse NOT RUN |
| 34 | Codice | PASS | file di dimensioni ragionevoli (il più grande, `BookingService`, ~620 righe), nomi chiari, configurazione centralizzata |
| 35 | Configurazione | PASS | `.env.example` commentato, nessun segreto nel repository |
| 36 | Test | **PARTIAL** | suite automatica estesa (vedi Test finali); **prove manuali (mobile, desktop, tastiera, pagine, IT/EN) NOT RUN** — `docs/MANUAL_CHECKLIST.md` |
| 37 | Processo di lavoro | PASS | audit, piano approvato e controlli a ogni fase (`docs/DECISIONS.md`) |
| 38 | Azioni da non fare senza autorizzazione | PASS | nessun deploy, DNS, dominio, posta, servizio o acquisto |
| 39 | Dati ancora da completare | **BLOCKED** (dati del titolare) | tutto resta configurabile e segnalato: `docs/MISSING_DATA.md` |
| 40 | Consegna finale | PASS | `README.md`, `docs/ARCHITECTURE.md`, `docs/INSTALL_SHARED_HOSTING.md`, `docs/OPERATIONS.md`, `docs/CHANGES.md`, `docs/DELIVERY_CHECKLIST.md`, `.env.example`, test e risultati |

## Lacune trovate rileggendo la SPEC e loro esito

Nessuna era bloccante. Decisioni dell'utente del 2026-10-06.

| ID | Sezione | Lacuna | Esito |
|---|---|---|---|
| G1 | §4 | nessun campo **servizi** degli appartamenti | **Corretto**: migrazione `0005` (`apartment_translations.amenities`, una voce per riga per lingua), campo nel form admin (IT/EN), elenco nella pagina pubblica, voci nei dati strutturati; validazione (30 voci, 100 caratteri), audit, escaping. Nessun servizio inserito: li fornisce il titolare |
| G2 | §19 | WhatsApp con date e ospiti assente nel flusso | **Corretto**: link nel passo "appartamenti disponibili" (con il nome dell'appartamento) e in "nessun appartamento disponibile"; solo se il numero è configurato |
| G3 | §25 | nessun link a Google Maps | **Corretto**: link dalla pagina Contatti, solo con l'indirizzo configurato |
| G4 | §27 | nessun dato strutturato per gli appartamenti | **Corretto**: blocco `Apartment` con soli campi esistenti (nome, descrizione, capienza, camere, servizi); mai prezzi, valutazioni o indirizzi |
| G5 | §33 | `legacy/` (97 file) ancora nel repository | **Corretto**: `git rm -r legacy` dei soli file tracciati; recuperabili dalla cronologia (ultimo commit che li contiene: `ae3129e`). I file non tracciati sul disco dell'utente non sono stati toccati |
| G6 | §22 | recapiti solo nel piè di pagina | **Scelta documentata**: restano nel piè di pagina; il layout sarà ridisegnato con il nuovo design del titolare |

## Bug critici risolti durante la review

Nessun bug critico trovato né risolto: nessuna doppia prenotazione, prezzo errato, richiesta persa, accesso non autorizzato o esposizione di dati in questa fase né nelle esecuzioni precedenti (due esecuzioni complete, una in ordine casuale, più quattro della sola concorrenza). L'unico fallimento di test emerso (un test dei dati strutturati che si aspettava un solo blocco nella pagina dell'appartamento) era una conseguenza attesa di G4 ed è stato aggiornato.

## Test finali

Vedi `docs/TEST_REPORT.md` (sezione "Review finale"): suite completa PASS dopo le correzioni, con 15 test nuovi (5 integrazione sui servizi, 5 unit, 5 HTTP) e 7 prove di sensibilità tutte rilevate. Le esecuzioni complete in ordine predefinito e casuale della fase 8 e le prove di sicurezza della fase 7 restano valide: le correzioni di questa review non toccano il codice di prenotazione, disponibilità, prezzo, email o autorizzazione.

## Limitazioni residue

1. **Prove manuali NOT RUN**: tastiera, screen reader, mobile, desktop, zoom, browser diversi (`docs/MANUAL_CHECKLIST.md`).
2. **Nessun hosting reale**: guida di installazione verificata solo in Docker/Apache + MariaDB 10.11 + PHP 8.2; PHP 8.1 e MySQL non provati.
3. **Consegna email reale non provata** (nessuna credenziale SMTP; SPF/DKIM non toccati).
4. **Foto**: tutte segnaposto; `bin/optimize-images.php` mai eseguito (manca GD/WebP nel container); nessuna gestione foto nel DB (scelta dell'utente: quando ci sarà un set verificato).
5. **Inglese provvisorio** e testi legali in bozza.
6. Rischi di sicurezza accettati: blocco del login per IP, token del modulo non monouso, rate limit per IP (`docs/SECURITY_REVIEW.md`).
7. Nessun penetration test indipendente, Lighthouse, axe o validatori esterni.
8. Il menu è un elenco sempre visibile (scelta documentata), senza menu a comparsa.

## Dati mancanti

Elenco completo e stato in `docs/MISSING_DATA.md`. In sintesi, da fornire dal titolare: listino e regole di prezzo (stagioni, adulti, bambini, animali, supplementi, soggiorno minimo), descrizioni, regole, capienza, servizi e orari di ogni appartamento, testi di *L'agriturismo* e *Dintorni*, foto con provenienza verificata, recapiti ufficiali e numero WhatsApp, credenziali SMTP e testi definitivi delle email, testi legali, periodo di conservazione dei dati, `APP_SECRET`, indicazioni su Novasol, scelta dell'hosting.

## Operazioni necessarie prima della pubblicazione

Tutte a carico del titolare o di chi pubblica, **con autorizzazione esplicita**; elenco spuntabile in `docs/DELIVERY_CHECKLIST.md` e procedura in `docs/INSTALL_SHARED_HOSTING.md`:

1. scegliere hosting e dominio, attivare HTTPS;
2. creare il database, importare `migrations/0001`–`0005`, creare l'amministratore;
3. compilare `.env` di produzione (con `APP_SECRET` e SMTP) e caricare `vendor/` generato con `--no-dev`;
4. inserire dall'admin listino, descrizioni, servizi e orari; fornire testi, foto e recapiti;
5. far verificare privacy e cookie; decidere il periodo di conservazione dei dati;
6. eseguire la prova di fumo e `docs/MANUAL_CHECKLIST.md` (tastiera, mobile, IT/EN, email reale);
7. solo dopo, a cura di chi ha l'autorizzazione: eventuali modifiche DNS/posta (SPF/DKIM) e il puntamento del dominio. `docs/RELEASE_GUIDE.md` (prompt 14) raccoglierà questi passaggi **senza eseguirli**.
