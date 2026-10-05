# Audit repository

Stato: **COMPLETATO** — 2026-10-05, Claude Code, branch `main`, commit `07baca4`.

Audit read-only: nessun file applicativo modificato, nessun servizio esterno contattato o modificato. I segreti sono stati verificati solo per presenza/lunghezza, mai stampati. Il componente `Pagamenti` è stato ispezionato solo nella struttura (import, nomi dei campi), non nei valori.

## Executive summary

Il repository contiene una **SPA React 18 + Vite 5** con **Firebase** (Auth, Firestore, Analytics) e un **mini-server Node** (`src/EmailStatus/EmailServer.js`, nodemailer/Gmail). Non esiste alcun backend PHP né database MySQL. Lo stack è incompatibile con la SPEC (Firebase, Node persistente, nessun SEO server-side, pagamenti, login ospiti).

Il valore riutilizzabile è nei **contenuti** (testi italiani, dati appartamenti, servizi, informazioni pratiche, dintorni), nell'**identità visiva** (palette/CSS, logo) e in **alcune fotografie** probabilmente proprie. La logica di prenotazione, prezzo, autenticazione ed email va **sostituita**, non migrata.

Problemi **bloccanti/urgenti**, da gestire prima di qualunque altra cosa:

1. **Credenziali Gmail reali committate** in `src/EmailStatus/.env` (tracciato da Git, presente in 3 commit, remote GitHub `ElzanLj/agri_la_volta`).
2. **Il componente `Pagamenti` invia numero carta, scadenza e codice di sicurezza a Firestore** (collezione `prenotazioni`). Il progetto Firebase reale **potrebbe contenere dati di carta**.
3. **Immagini hotlinkate** da Booking.com, Tripadvisor, Google e da un blog terzo (la hero è una foto di un agriturismo **toscano** di un altro sito).

## Stack e struttura

| Area | Rilevato |
|---|---|
| Linguaggio | JavaScript (JSX), CSS |
| Framework | React 18.3.1, react-router-dom 6.30.4 |
| Build | Vite 5.4.21 (`vite.config.js` minimale) |
| Package manager | npm (`package-lock.json` presente) |
| Backend | nessuno, eccetto `src/EmailStatus/EmailServer.js` (server HTTP Node su porta 3001, avviato a mano) |
| Database | Firebase Firestore (collezioni usate: `apartments`, `prenotazioni`, `appartamenti`) |
| Auth | Firebase Auth (email/password + Google, **registrazione pubblica**) |
| Analytics | Firebase Analytics (`getAnalytics`) attivo all'avvio |
| PHP / MySQL | **assenti** |
| Test | **nessun test** e nessuno script `test` |

### Entry point

- `index.html` → `src/main.jsx` → `src/App.jsx`.
- Route: `/` (one-page con sezioni Hero, ImageScroller, Gallery, Appartamenti, ContactUs), `/dovesiamo`, `/prenota`.
- Navigazione a sezioni tramite `scrollIntoView`; appartamenti mostrati in uno slider con modale, **nessuna pagina per appartamento**.

### File sorgente (3.739 righe totali)

`src/App.jsx`, `src/main.jsx`, `src/index.css`, `src/App.css`, `src/EmailStatus/EmailServer.js`, e `src/components/`: `Appartamenti/` (dati + slider + modale), `AuthPopup/`, `Booking/BookingSystem.jsx`, `ContactUs/`, `DoveSiamo/`, `GallerySlider/`, `Hero/`, `ImageScroller/`, `ImageSlider/`, `NavBar/` (+ `firebaseConfig.js`), `Pagamenti/`, `PrenotazioniPopUp/`, `GenitoreComponente.jsx`, `ListaAppartamenti.jsx`.

## Comandi eseguiti e risultato

Ambiente: Windows 11, Node v24.18.0, npm 11.16.0. **PHP, Composer e MySQL/MariaDB non installati** (`command not found`).

| Comando | Esito | Note |
|---|---|---|
| `npm ci --no-audit --no-fund` | PASS (exit 0) | warning: script di install di `esbuild` e `protobufjs` non approvati da `allowScripts`; la build funziona comunque |
| `npm run lint` | **FAIL** (exit 1) | 90 errori: 42 `react/prop-types`, 25 `no-unused-vars`, 14 `no-undef`, 9 `react/no-unescaped-entities` |
| `npm run build` | PASS (exit 0) | `dist/` 15 MB; bundle JS 676 kB (174 kB gzip, oltre la soglia di 500 kB); warning: `../../assets/fotoSalso3.jpeg` non risolto (path errato in `DoveSiamo.css`), 2 errori di sintassi CSS in minificazione |
| `npx vite --port 5179` + richiesta HTTP | PASS | `/` → 200, `/dovesiamo` → 200 (solo risposta HTML della SPA, nessun test funzionale) |
| `npm audit --omit=dev` | 14 vulnerabilità (8 moderate, 6 high) | `nodemailer`, `react-router`, `undici`, `@grpc/grpc-js` (via firebase). Non corrette: lo stack è destinato alla sostituzione |

`dist/` generata dalla build è stata rimossa dopo la verifica (è in `.gitignore`). `node_modules/` resta installato localmente (ignorato da Git).

## Cosa preservare

**Contenuti** (da trasferire nel nuovo sistema come dati/testi, marcati "da verificare" dove indicato):

- testi della hero e della presentazione dell'agriturismo (`Hero.jsx`, `ScrollImage.jsx`): colline di Salsomaggiore, circa 2 km dal centro termale;
- **descrizioni dei 6 appartamenti** e **elenco servizi** (`Appartamenti/DatiAppartamenti.jsx`): piscina, posto auto, terrazzo/portico, TV, barbecue, animali ammessi, colonnina di ricarica;
- composizione indicativa degli appartamenti presente nelle descrizioni (camere, divano letto, terrazza/portico): **da verificare con il titolare** prima di usarla per capienza/camere/letti;
- **informazioni pratiche** (`Appartamenti.jsx` → `InfoAggiuntive`): orari di consegna/rilascio, pulizia finale, pulizia infrasettimanale, lettino, sconti bambini, supplemento letto aggiuntivo. **Importi e regole da NON considerare validi** finché il titolare non li conferma (SPEC §5);
- testi dei **dintorni** (`DoveSiamo.jsx`): Terme Berzieri, castelli (Tabiano, Vigoleno, Torrechiara, Fontanellato), Parco dello Stirone, Busseto/luoghi verdiani;
- dati aziendali e di contatto (`ContactUs.jsx`): ragione sociale, indirizzo (Marzano, Salsomaggiore Terme), telefoni, email, P.IVA/REA: **da verificare** (vedi incoerenze sotto);
- motivi di contatto (incluso "Affitto sala per eventi": da chiarire se il servizio esiste ancora).

**Identità visiva**: palette e tipografia dei CSS esistenti (Outfit/Raleway/Ubuntu Sans), logo `fotoGenerali/logo1.webp`, impostazione con fotografie ampie. Da riprodurre in CSS semplice, non da riusare come componenti React.

**Fotografie**: solo quelle di provenienza verificata (vedi sezione immagini). Probabilmente proprie: `fotoDintorni/fotoAgri1–5.jpeg` (EXIF iPhone).

## Cosa rimuovere/migrare e perché

| Elemento | Azione proposta | Motivo |
|---|---|---|
| `src/components/Pagamenti/` | rimuovere | SPEC §3: nessun pagamento, raccoglie dati carta |
| `AuthPopup/` + login/logout in `NavBar` | rimuovere | SPEC: nessun account/login ospite; registrazione pubblica aperta |
| `NavBar/firebaseConfig.js`, dipendenza `firebase` | rimuovere | SPEC §1 vieta Firebase; Analytics attivo senza consenso (SPEC §24) |
| `Booking/BookingSystem.jsx` | sostituire | dichiara "Prenotazione confermata", scrive direttamente su Firestore, overlap errato (check-out inclusivo: blocca soggiorni consecutivi), nessun controllo server, prezzi hardcoded, seed DB lato client |
| `EmailStatus/EmailServer.js`, `nodemailer`, `dotenv` | sostituire con invio SMTP in PHP | Node persistente non ammesso; invia sempre a `GMAIL_USER` invece che al cliente; URL `localhost` hardcoded; nessun limite dimensione/validazione; CORS fisso su localhost |
| `src/EmailStatus/.env` | rimuovere dal tracking (`git rm --cached`) | contiene credenziali reali (vedi sotto) |
| `GenitoreComponente.jsx`, `ListaAppartamenti.jsx`, `PrenotazioniPopUp/` | rimuovere | codice morto: non importati da nessuna parte, import verso file inesistenti, variabili non definite |
| dipendenze `all`, `react-datepicker`, `react-icons` | rimuovere | non usate in nessun file |
| `ImageScroller` (4 URL esterni), sfondo hero esterno | sostituire con immagini locali verificate | hotlink vietati (SPEC §23) |
| `index.html`: CSS `https://example.com/path/to/swiper.min.css`, favicon `./src/assets/logo1.webp` | rimuovere/correggere | il primo è un placeholder inesistente; il favicon punta a un path inesistente |
| Font Awesome da CDN, Google Fonts via `@import` | valutare self-hosting o font di sistema | prestazioni e privacy (richieste a terzi) |
| Iframe Google Maps embed | sostituire con link a Google Maps | SPEC §25 preferisce un semplice link; l'embed carica risorse di terzi |
| Slider con autoplay (`SliderApartament`, `GallerySlider`) | sostituire con layout statico/galleria | SPEC §22: evitare slider automatici aggressivi |
| 26 asset non referenziati (vedi sezione immagini) | archiviare/eliminare dopo verifica | peso inutile; alcuni non pertinenti |

L'intero frontend React verrà sostituito progressivamente da pagine server-rendered PHP (vedi architettura). Nessuna rimozione è stata eseguita in questa fase.

## Pagamenti e dati sensibili (senza esposizione)

1. **Credenziali SMTP/Gmail committate**: `src/EmailStatus/.env` è **tracciato da Git** (non ignorato) e contiene `GMAIL_USER` (19 caratteri) e `GMAIL_APP_PASS` (20 caratteri, valorizzata). È presente nei commit `168c785`, `6f6e4a9` e `2dfe289` e nel remote GitHub. **Va considerata compromessa.** Azione richiesta **al titolare dell'account Google** (servizio esterno, non eseguibile dall'agente): revocare la App Password. Rimuovere il file dalla cronologia richiederebbe riscrittura della storia Git, che è **vietata senza autorizzazione esplicita**; la revoca è comunque la mitigazione necessaria.
2. **Dati carta inviati a Firestore**: `Pagamenti.jsx` gestisce uno stato `cartaDiCredito` con campi `numero`, `scadenza`, `codiceSicurezza` e lo salva con `addDoc` nella collezione Firestore `prenotazioni`, insieme a `datiPersonali` (nome, cognome, email). Se il componente è stato usato con dati veri, **il progetto Firebase può contenere dati di carta e dati personali**. Non ho avuto accesso a Firebase e non l'ho contattato. Nel codice sorgente non sono presenti numeri di carta letterali (verifica: nessuna sequenza di 13–19 cifre). Azione richiesta: il titolare verifica la console Firebase; l'eventuale cancellazione di dati reali richiede autorizzazione esplicita.
3. **Configurazione Firebase hardcoded**: `NavBar/firebaseConfig.js` contiene i 7 valori reali del progetto (API key 39 caratteri, project ID, ecc.), mentre `.env.example` prevede variabili `VITE_FIREBASE_*` non usate dal codice. La API key web Firebase non è un segreto in senso stretto, ma espone il progetto: le **regole di sicurezza Firestore sono sconosciute** e il codice client scrive direttamente su `apartments` e `prenotazioni`, quindi probabilmente le scritture sono aperte. Da verificare dal titolare.
4. **Dati personali nel flusso attuale**: il modulo contatti invia nome, email, telefono e messaggio al server Node, che li inserisce nell'email e logga `info.response`/errori su console. Nessun consenso privacy.
5. **Dati aziendali reali** presenti nel codice (P.IVA, REA, ragione sociale, telefoni): pubblici per natura, ma da verificare (incoerenze sotto).

## Servizi esterni censiti

| Servizio | Dove | Uso |
|---|---|---|
| Firebase (Auth, Firestore, Analytics) | `NavBar/firebaseConfig.js` | DB, login, tracciamento |
| Gmail SMTP (via nodemailer `service: 'gmail'`) | `EmailStatus/EmailServer.js` | invio email |
| Google Maps embed | `DoveSiamo.jsx` | mappa |
| Google Fonts | `index.css` (`@import`) | font |
| Font Awesome (cdnjs) | `index.html` | icone servizi/stelle |
| Tripadvisor | `ContactUs.jsx` (link + logo) | link recensioni |
| Hotlink immagini: `visitsalsomaggiore.it`, `lh3.googleusercontent.com`, `cf.bstatic.com` (Booking), `dynamic-media-cdn.tripadvisor.com`, `inviaggiodasola.com` | `ScrollImage.jsx`, `Hero.css` | immagini |
| `example.com/path/to/swiper.min.css` | `index.html` | placeholder rotto |

Nessuna integrazione Booking/Airbnb/iCal/Novasol presente. Nessun gateway di pagamento esterno (Stripe/PayPal) presente: il "pagamento" è un form che salva i dati carta su Firestore.

## Immagini

Totale `src/assets`: **~33 MB**, 64 file. La provenienza è stimata dai metadati EXIF/XMP e dalle dimensioni; **nessuna immagine può essere considerata licenziata senza conferma del titolare**.

### Hotlink (vietati, da sostituire)

- Hero (`Hero.css`): `inviaggiodasola.com/.../agriturismi-con-spa-in-toscana.jpg`. **Non raffigura La Volta** (articolo su agriturismi in Toscana).
- `ScrollImage.jsx`: 4 immagini da `visitsalsomaggiore.it`, Google (`lh3.googleusercontent.com/proxy/...`, URL proxy probabilmente scaduto), Booking (`cf.bstatic.com`), Tripadvisor.

### Classificazione file locali

| Classe | File | Valutazione |
|---|---|---|
| Probabilmente proprie | `fotoDintorni/fotoAgri1–5.jpeg` (EXIF Apple iPhone, 2564–4032 px, 1,1–1,6 MB) | **DA VERIFICARE** (probabile OK); da ottimizzare |
| Appartamenti/struttura 1024×651 | `fotoAppartamenti/agri1.jpg`, `appartamento6/8/9/10.jpg`, `fotoGenerali/piscina1/2.jpg` | **DA VERIFICARE**: risoluzione uniforme tipica di un portale/sito precedente; chiedere al titolare l'origine |
| Miniature a bassa risoluzione | `appartamento1/2/3/4.jpeg`, `appartamenti16.jpeg` (259×194), `appartamento12.jpeg` (262×192), `fotoGenerali/agri2.jpeg` (213×160), `appartamento5.jpg` (512×341), `fotoDintorni/laVolta.jpeg` (512×206) | **DUBBIA**: dimensioni tipiche di miniature da motori di ricerca; comunque inadeguate alla pubblicazione |
| Screenshot/PNG | `appartamento13/14/15.png`, `fotoDintorni/laVolta4/5.png`, `laVolta2.jpeg` | **DUBBIA**: probabili catture da altri siti |
| Fotografia professionale di terzi | `fotoDintorni/appartamentoFoto.jpg` (XMP rights: studio fotografico esterno, Capture One, 2020) | **DUBBIA**: diritti di uno studio; usata come header "Appartamenti" e "Prenota" e come foto di Ciclamino |
| Dintorni, probabile terzi | `Berzieri.jpeg` (XMP *Rights Marked*), `Busseto.jpeg`, `Torrechiara.jpeg`, `CastelloTabiano.jpeg`, `castello-fontanellato.jpeg`, `vigoleno.jpeg`, `Stirone2.jpeg`, `castell-arquato.webp`, `grazzanoVisconti.webp`, `fotoSalso5.jpeg` (1200×628, formato social), `PanoramaSalso2/3.jpeg` | **DUBBIA**: sostituire o ottenere licenza prima della pubblicazione |
| Originali fotocamera senza EXIF | `fotoGenerali/agri5/6/7.JPG` (4176×2784, ~4,8 MB ciascuna) | **DA VERIFICARE**; non usate (agri7 solo in commento) |
| Non pertinenti | `fotoGenerali/minion.jpeg`, `fotoGenerali/spritz3.png`, `icon/dragon.png` | rimuovere |
| Marchi di terzi | `icon/tripadvisorLogo.png/.svg` | uso del marchio da valutare |
| Icone UI | `icon/*Logo.png` (512×512), frecce SVG | provenienza ignota; sostituibili con SVG/icon set con licenza nota |
| Logo | `fotoGenerali/logo1.webp` (980×980), `icon/agriLogo.png` | DA VERIFICARE (presumibilmente proprietà) |

### Problemi tecnici

- Immagini fino a 4.032 px e 1,6 MB servite senza ridimensionamento; nessun `srcset`, `width/height`, `loading="lazy"` né AVIF/WebP generati.
- Immagini quasi tutte usate come `background-image` CSS: niente `alt`, non indicizzabili.
- La stessa foto è usata per più appartamenti (`appartamento8.jpg` per Mimosa e Margherita; `appartamento12.jpeg` per Girasole e Ciclamino; `appartamento3.jpeg` in 4 gallerie) → le gallerie attuali non rappresentano fedelmente i singoli appartamenti.
- **26 asset non referenziati**: `appartamento1.jpeg`, `appartamento13.png`, `appartamento5.jpg`, `PanoramaSalso2/3.jpeg`, `castell-arquato.webp`, `fotoSalso5.jpeg`, `grazzanoVisconti.webp`, `laVolta.jpeg`, `laVolta2.jpeg`, `laVolta4/5.png`, `agri2.jpeg`, `agri5/6.JPG`, `minion.jpeg`, `piscina1/2.jpg`, `spritz3.png`, `close.png`, `dragon.png`, `frecciaBianca.png`, `loading.gif`, `next.png`, `prev.png`, `tripadvisorLogo.png`.
- Nessun duplicato binario esatto (verifica MD5).

## Codice morto / qualità

- `GenitoreComponente.jsx`, `ListaAppartamenti.jsx`, `PrenotazioniPopUp/PrenotazionePopup.jsx`: mai importati; importano file inesistenti (`./firebaseConfig`, `./BarraDisponibilita`, `./Pagamenti`); variabili non definite.
- `AuthPopup` riceve `togglePopup` ma usa `onClose` → il pulsante di chiusura e la chiusura dopo login non funzionano.
- `DoveSiamo` renderizza una propria `Navbar` in aggiunta a quella di `App` → doppia navbar.
- `App.jsx`: stato `currentIndex` e flusso `isBooking` inutilizzati; voce "Prenota" punta a una sezione `Booking` inesistente.
- `BookingSystem.jsx`: `handleAdultsChange`/`handleChildrenChange` inutilizzati; `alert()` in più punti (SPEC §22); email cliente hardcoded `cliente@example.com`; usa `appartamentoFoto.jpg` per Ciclamino.
- `DoveSiamo.css` riferisce `../../assets/fotoSalso3.jpeg` (inesistente).
- `Appartamenti/SliderApartament.jsx` contiene un attributo spurio `x` dopo `className="slider-inner"`.
- `html lang="en"` con contenuti italiani; unico `<title>La Volta</title>`; nessuna meta description.
- `src/EmailStatus/EmailServer.js`: `JSON.parse` senza try/catch (crash su body non valido), nessun limite dimensione body.

## Incoerenze nei dati esistenti (da chiarire, non risolte)

- Email: `info@agriturismolavolta.com` (ContactUs, SPEC) vs `info@agriturismolavolta.it` (footer DoveSiamo).
- Prezzo Rosa: 80 €/notte in `DatiAppartamenti.jsx` vs 100 € nel seed di `BookingSystem.jsx`. Gli altri prezzi coincidono (Margherita 100, Girasole 90, Mimosa 80, Ciclamino 80, Viola 90). **Tutti da non considerare validi** (SPEC §5).
- Formula prezzo in `BookingSystem`: +20 €/notte per adulto oltre 2, +10 €/notte per bambino, 30 € di pulizia. In `InfoAggiuntive`: 50% di sconto per bambini 1–3 anni, gratis sotto 1 anno, lettino 10 €/giorno. Le due fonti non coincidono e non sono confermate.
- Capienza massima: 6 persone hardcoded per tutti gli appartamenti nel form; non confermata per appartamento.
- Valutazione a stelle per appartamento (4 / 4,5): origine ignota (rating di un portale?), **non pubblicabile senza fonte**.

## Gap rispetto a `docs/SPEC.md`

| Requisito SPEC | Stato attuale |
|---|---|
| §1–2 PHP + MySQL/MariaDB, hosting condiviso | assente (React SPA + Firebase + Node) |
| §3 nessun pagamento | **violato** (form carta su Firestore) |
| §4 pagina indicizzabile per appartamento, campi strutturati | assente (modale in uno slider) |
| §5 tariffe configurabili | assente (hardcoded) |
| §6 ricalcolo prezzo server-side | assente |
| §7 `[check_in, check_out)`, non-overlap, transazioni | assente; overlap client-side errato |
| §8 flusso richiesta → `pending` | assente; mostra "Prenotazione confermata" |
| §9 dati cliente, consenso privacy | assente (nessun nome/email raccolto nel booking; nessun consenso) |
| §10 stati | assenti |
| §11–12 prenotazioni manuali, origine, gestione agenzia | assenti |
| §14–16 admin, storico, cancellazione con bozza | assenti |
| §17–18 email transazionali, robustezza SMTP | parziale e non conforme |
| §19 WhatsApp | assente |
| §20 IT/EN | solo italiano |
| §21 pagine pubbliche | presenti solo `/`, `/dovesiamo`, `/prenota` |
| §22 UX (no alert, no slider aggressivi) | violato in più punti |
| §23 immagini | hotlink e provenienza dubbia; nessuna ottimizzazione |
| §24 analytics/cookie | Firebase Analytics attivo; nessuna privacy/cookie policy |
| §26 accessibilità | carente: hamburger su `div` non focusabile, frecce slider su `img` cliccabili, form contatti senza `label`, `alt` generici, nessuno skip link, nessun supporto `prefers-reduced-motion` |
| §27 SEO | SPA client-side, nessun metadata, sitemap, robots, canonical, 404 |
| §28–29 sicurezza/antispam | nessuna misura; segreti nel repo |
| §30 DB | prenotazioni salvate come array `bookings` dentro il documento appartamento (vietato) |
| §31 CSV | assente |
| §35 `.env.example` | presente ma solo Firebase/Vite |
| §36 test | assenti |

## Architettura minima proposta

**PHP 8.1+ senza framework, rendering server-side, MySQL/MariaDB via PDO**, Composer solo dove porta un vantaggio concreto (PHPMailer per SMTP; PHPUnit solo in sviluppo). Nessun build step obbligatorio in produzione: CSS e JavaScript vanilla scritti a mano, JavaScript solo per migliorare progressivamente il form di richiesta.

Motivazioni: pagine indicizzabili senza SSR JavaScript (SPEC §4, §27); compatibilità con qualunque hosting condiviso; minima manutenzione; un solo linguaggio lato server.

```text
public/                 # document root: index.php (front controller), .htaccess, assets/ (css, js, img ottimizzate), robots.txt
app/
  config.php            # legge l'ambiente (.env), nessun segreto nel codice
  Http/                 # router minimale, controller pubblici e admin
  Domain/               # Availability, Pricing, BookingRequest, Booking, Block (logica pura, testabile)
  Repository/           # accesso DB con PDO e query parametrizzate
  Mail/                 # invio SMTP, outbox/retry, bozze di cancellazione
  Security/             # CSRF, sessione, rate limit, auth admin
templates/              # template PHP con escaping; layout, pagine pubbliche, admin
lang/it.php, lang/en.php  # stringhe UI; contenuti IT/EN in tabelle DB dedicate
migrations/NNN_*.sql    # migrazioni versionate
bin/                    # migrate.php, create-admin.php (CLI)
storage/                # log, rate limit (fuori webroot, non versionato)
tests/
```

Database (bozza da definire in Fase 1): `apartments` (+ `apartment_translations`), `apartment_photos`, `booking_requests`, `bookings` (origine, stato, riferimento alla richiesta), `availability_blocks`, `rate_periods`/`seasonal_rates`, `pricing_rules`/supplementi, `admin`, `audit_log`, `email_outbox`. **Concorrenza**: la conferma avviene in transazione InnoDB con `SELECT … FOR UPDATE` sulla riga dell'appartamento, poi controllo overlap `existing.check_in < new.check_out AND new.check_in < existing.check_out` su prenotazioni `confirmed` e blocchi, poi insert. MySQL non ha vincoli di esclusione: il lock per appartamento serializza le conferme.

URL: italiano senza prefisso (`/`, `/agriturismo`, `/appartamenti`, `/appartamenti/{slug}`, `/dintorni`, `/richiedi-disponibilita`, `/contatti`, `/privacy`, `/cookie`), inglese con prefisso `/en/` (`/en/farmhouse`, `/en/apartments/{slug}`, `/en/surroundings`, `/en/request-availability`, `/en/contact`, `/en/privacy`, `/en/cookies`). `/admin` escluso dalla navigazione e da `robots`/sitemap.

Hosting senza document root configurabile: `.htaccess` nella root che reindirizza internamente a `public/` e nega l'accesso a `app/`, `storage/`, `migrations/`, `.env`.

## Strategia di migrazione incrementale

1. **Azioni immediate del titolare (prima della Fase 1)**: revoca della App Password Gmail; verifica Firebase (dati carta/personali in `prenotazioni`, regole Firestore, utenti Auth registrati); decisione su cosa conservare o cancellare (operazione su dati reali che richiede autorizzazione).
2. **Fase 1 — fondamenta**: `git rm --cached src/EmailStatus/.env` e regola `.gitignore` per `.env`; spostare la SPA esistente in `legacy/` (spostamento Git, non cancellazione) come riferimento per contenuti e stile; creare lo scheletro PHP, `.env.example` conforme alla SPEC §35, migrazioni, CLI admin; installare PHP e MariaDB in locale (vedi blocchi).
3. **Fase 2A/2B**: dominio disponibilità e prezzi come codice PHP puro con test, prima di qualsiasi UI.
4. **Fase 3–4**: admin ed email.
5. **Fase 5**: pagine pubbliche che riportano testi e stile da `legacy/`; seed contenuti appartamenti da `DatiAppartamenti.jsx` marcati "da verificare".
6. **Fase 6**: immagini (solo verificate), ottimizzazione, SEO, IT/EN.
7. **Rimozione di `legacy/`, Firebase, nodemailer e delle dipendenze npm** solo quando le pagine PHP coprono tutti i contenuti. A quel punto `package.json` può sparire o restare solo per tool di sviluppo opzionali.

Durante la transizione la SPA non viene pubblicata né modificata funzionalmente.

## Rischi e blocchi

| # | Rischio/blocco | Gravità | Azione |
|---|---|---|---|
| 1 | App Password Gmail reale nel repo e nella cronologia GitHub | **Critica** | revoca da parte del titolare; untrack in Fase 1; riscrittura storia solo se autorizzata |
| 2 | Possibili dati carta e dati personali in Firestore `prenotazioni` | **Critica** | verifica del titolare sulla console Firebase; nessun accesso dell'agente |
| 3 | Regole Firestore probabilmente aperte; registrazione Auth pubblica attiva | Alta | verifica del titolare; dismissione del progetto Firebase quando autorizzato |
| 4 | Hero e immagini hotlinkate o di terzi (la hero non è La Volta) | Alta | servono foto proprie prima del lancio |
| 5 | **PHP, Composer e MySQL/MariaDB non installati localmente** | **Bloccante per la Fase 1** | installare un ambiente locale (es. XAMPP, Laragon o PHP + MariaDB portable): richiede approvazione dell'utente |
| 6 | Prezzi, regole e capienze non confermati; dati di contatto incoerenti | Media | `MISSING_DATA.md` |
| 7 | Rating a stelle senza fonte | Bassa | non migrare senza fonte |
| 8 | 14 vulnerabilità npm | Bassa (stack da dismettere) | nessun aggiornamento ora |
| 9 | Hosting di produzione non ancora scelto (versione PHP, `mod_rewrite`, cron) | Media | verificare i requisiti minimi prima della Fase 9 |

## Stima relativa per fasi

| Fase | Stima | Note |
|---|---|---|
| 1 Architettura/DB/fondamenta | M | dipende dall'ambiente locale PHP/MariaDB |
| 2A Booking/disponibilità | M | logica critica + test concorrenza |
| 2B Pricing | M | struttura configurabile senza dati reali |
| 3 Admin | L | numerose schermate CRUD + calendario + CSV |
| 4 Email/WhatsApp | S | PHPMailer + outbox |
| 5 Frontend pubblico | L | riscrittura delle pagine in PHP, riuso contenuti/stile |
| 6 IT/EN, SEO, a11y, perf, immagini | M | bloccata in parte da foto e traduzioni |
| 7 Sicurezza/privacy/antispam | S–M | gran parte integrata nelle fasi precedenti |
| 8 Test/documentazione | M | |
| 9 Review/release prep | S | |
