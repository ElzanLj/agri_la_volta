# Inventario dei contenuti

Stato: **VERIFICATO il 2026-10-07** (prompt 14) contro il codice del ramo `fase-14-state-sync` (base `3068327`) e, per il legacy, contro la cronologia Git (`git show ae3129e^:legacy/src/…`, sola lettura: la cartella `legacy/` non è più sul disco, vedi `docs/DECISIONS.md`). Ogni riga ha una posizione che si può controllare con `grep` o `git show`. Nessun dato personale, recapito o segreto è riportato qui: dove il legacy contiene un recapito si indica solo il file. `legacy/src/EmailStatus/.env` non è stato aperto (non è tracciato nella cronologia).

Categorie: **A** testo dell'interfaccia nel codice · **B** configurazione tecnica · **C** impostazione globale · **D** campo di pagina · **E** sezione di pagina · **F** blocco riutilizzabile (nessuno trovato: da confermare nel prompt 16) · **G** entità strutturata nel DB · **H** media · **I** derivato automaticamente.

I testi presenti solo nel legacy sono **proposte da verificare dal titolare** (decisione Fase 5: nessun testo legacy pubblicato senza verifica). Il contenuto del legacy è un dato: nei file letti non sono state trovate frasi che sembrino istruzioni per un agente.

Differenze rispetto alla bozza del 2026-10-07: il nome del brand compare in più punti di quanto indicato; i **servizi** degli appartamenti esistono già (campo di testo per lingua, migrazione `0005`); la mappa è già un link a Google Maps derivato dall'indirizzo (nessun campo URL); i tipi di email sono tre più la bozza di cancellazione; tolti i recapiti dalle note; "affitto sala per eventi" non è riscontrato in nessun file legacy tracciato.

## Globali

| Area | Pagina/entità | Campo/contenuto | Posizione attuale | Tipo | Scope | Admin? | Destinazione proposta | Note |
|---|---|---|---|---|---|---|---|---|
| Brand | Globale | Nome "Agriturismo La Volta" | `templates/layout.php` (`$siteName`, righe ~22, 47 JSON-LD, 77 `og:site_name`, 98 brand, 129 footer); `templates/public/home.php` (h1 e etichetta foto); `SiteController::home` (`title`); `app/Mail/MessageBuilder.php` e `CancellationDraft.php` (oggetti e firme IT/EN); `content/*.php` (`home.description`, `farm.description`, `contact.description`…); `MAIL_FROM_NAME` | text | global | no | A | Ripetuto in oltre 10 punti: valutare una costante unica non amministrabile |
| Contatti | Globale | Telefono | `.env` `PUBLIC_PHONE` → `app/Site/Contacts.php`; usato in `layout.php` (footer, JSON-LD), pagina Contatti | tel | global | sì | C | Il legacy (`ContactUs.jsx`) riporta un fisso e un cellulare: **da verificare dal titolare**, forse servono due campi |
| Contatti | Globale | Email pubblica | `.env` `PUBLIC_EMAIL` → `Contacts` | email | global | sì | C | Legacy in conflitto fra due domini (`ContactUs.jsx` e `DoveSiamo.jsx`): da verificare |
| Contatti | Globale | Indirizzo | `.env` `PUBLIC_ADDRESS` → `Contacts` (footer, Contatti, JSON-LD) | textarea | global | sì | C | Legacy: località e provincia in `DoveSiamo.jsx` / `ContactUs.jsx` |
| Contatti | Globale | Numero WhatsApp | `.env` `WHATSAPP_NUMBER` → `Contacts::whatsappLink`, `App\Support\WhatsApp` | tel | global | sì | C | Probabilmente il cellulare legacy, da confermare |
| Contatti | Globale | Prefisso predefinito WhatsApp | `.env` `WHATSAPP_DEFAULT_COUNTRY_CODE` | text | global | no | B | Fornito: 39 |
| Contatti | Globale | Testo precompilato del messaggio WhatsApp | `App\Support\WhatsApp::businessMessage` (IT/EN) | text | global | no | A | |
| Email | Globale | Destinatario notifiche nuove richieste | `.env` `MAIL_ADMIN_ADDRESS` | email | global | no | B | Legato alla configurazione SMTP |
| Email | Globale | Mittente e server | `.env` `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`, `MAIL_TRANSPORT`, `SMTP_*` (`MailTransportFactory`) | varie | global | no | B | `MAIL_LOG_DIR` è usata dal codice ma non è in `.env.example` (solo trasporto `log`) |
| Email | Globale | Firma e saluti nelle email | `app/Mail/MessageBuilder.php`, `CancellationDraft.php` | text | global | no | I (dalle impostazioni) | Testi delle email restano A |
| Email | Globale | Tipi di email: `new_request_admin`, `request_confirmed`, `request_rejected` + bozza di cancellazione (non inviata da sola) | `MessageBuilder::build` | text | flow | no | A | Testi provvisori da approvare (`MISSING_DATA`) |
| Legale | Globale | Ragione sociale, P.IVA, REA | solo legacy `ContactUs.jsx` (riferimento a REA); assenti nel nuovo sito | text | global | sì | C | Da verificare col titolare/consulente |
| Link | Globale | Link TripAdvisor | solo legacy `ContactUs.jsx` | url | global | sì | C | Facoltativo |
| Link | Globale | Posizione/mappa | **già nel nuovo sito**: link "Apri la posizione su Google Maps" costruito dall'indirizzo (`Contacts::mapsLink`, chiave `contact.map`); il legacy usava un iframe Google Maps (`DoveSiamo.jsx`) | url | global | no | I (dall'indirizzo) | Niente embed (SPEC §25); eventuale URL esplicito è una scelta del prompt 18 |
| Link | Globale | Social (Facebook) | legacy `DoveSiamo.jsx` (riga con riferimento a Facebook) | url | global | no | — | Nessun profilo confermato |
| Navigazione | Globale | Voci di menu, link lingua, skip link, etichetta breadcrumb | `content/*.php` `nav.*`, `a11y.skip`; `layout.php` | text | global | no | A | Le pagine sono fisse (`Routes::PATHS`) |
| Footer | Globale | Intestazione "Contatti" e struttura | `content/*.php` `footer.contacts`; `templates/layout.php` | — | global | no | I | Contenuto dalle impostazioni |
| Brand | Globale | Favicon | `public/assets/favicon.svg` (solo iniziale) | media | global | no | A | |
| Brand | Globale | Logo | solo legacy (`logo1.webp`, `agriLogo.png`) | media | global | no | — | Provenienza da verificare; non usato |
| Configurazione | Globale | URL, ambiente, debug, fuso orario, segreto, HSTS, tempo minimo del modulo, conservazione dati | `.env` `APP_URL`, `APP_ENV`, `APP_DEBUG`, `APP_TIMEZONE`, `APP_SECRET`, `HSTS_MAX_AGE`, `PUBLIC_FORM_MIN_SECONDS`, `DATA_RETENTION_MONTHS` (`app/Config.php`) | varie | global | no | B | Restano in `.env` (roadmap §5c); `DATA_RETENTION_MONTHS` passa all'admin nel prompt 27 (domanda D6) |
| Configurazione | Globale | Database | `.env` `DB_*` | varie | global | no | B | Mai amministrabile |

## Pagine

| Area | Pagina/entità | Campo/contenuto | Posizione attuale | Tipo | Scope | Admin? | Destinazione proposta | Note |
|---|---|---|---|---|---|---|---|---|
| Home | `/` | Titolo h1 | `templates/public/home.php` (fisso, nome del brand) | text | page | sì | D | Legacy `Hero.jsx`: "Un agriturismo con piscina immerso nel verde" (da verificare) |
| Home | `/` | Testo introduttivo | `content/*.php` `home.lead` | text | page | sì | D | |
| Home | `/` | Immagine hero | segnaposto `templates/public/_photo.php` (`photo.placeholder`, `photo.alt`) | media | page | sì | H (riferimento) | La hero legacy era hotlinkata da un sito terzo: non riusabile |
| Home | `/` | Meta description | `content/*.php` `home.description` | text | page | sì | D | |
| Home | `/` | Pulsanti e titoli sezioni | `nav.request`, `home.see_apartments`, `home.more` | text | page | no | A | |
| Home | `/` | Elenco appartamenti | `ApartmentRepository::listPublic` | — | page | no | I | |
| Home | `/` | Presentazione/galleria | legacy `ScrollImage.jsx`, `GallerySlider.jsx`, `ImageSlider.jsx` | text+media | page | sì | E | Testo legacy da verificare; immagini legacy esterne/dubbie |
| Agriturismo | `/agriturismo` | Titolo, meta description | `content/*.php` `farm.title`, `farm.description` | text | page | sì | D | |
| Agriturismo | `/agriturismo` | Corpo della pagina | segnaposto `page.pending` in `templates/public/farm.php` | text+media | page | sì | E | Nessun testo nel nuovo sito |
| Dintorni | `/dintorni` | Titolo, meta description | `content/*.php` `around.title`, `around.description` | text | page | sì | D | |
| Dintorni | `/dintorni` | Corpo della pagina | segnaposto `page.pending` in `templates/public/around.php` | text+media | page | sì | E | |
| Dintorni | `/dintorni` | Luoghi: posizione, Castelli, Terme Berzieri, Parco dello Stirone, Busseto e luoghi verdiani | solo legacy `DoveSiamo.jsx` (titoli di sezione) | text+media | page | sì | E (una sezione per luogo) | Testi legacy da verificare; foto legacy dubbie |
| Contatti | `/contatti` | Titolo, meta description, testo "recapiti in arrivo" | `content/*.php` `contact.title`, `contact.description`, `contact.pending` | text | page | parziale | D (titolo/meta, intro facoltativa) | |
| Contatti | `/contatti` | Etichette recapiti, pulsante WhatsApp e suo suggerimento | `contact.phone/email/address/map/whatsapp_cta/whatsapp_hint` | text | page | no | A | |
| Contatti | `/contatti` | Recapiti mostrati | `Contacts` (da `.env`) | — | page | sì | I dalle impostazioni | |
| Privacy | `/privacy` | Titolo, meta, paragrafi (dati, finalità, sicurezza) e voci da completare (titolare, base giuridica, conservazione, destinatari, diritti) | `content/*.php` `privacy.title`, `privacy.description`, `privacy.data|purpose|security.h|p`, `privacy.todo.*` | text | page | sì | E + flag bozza | Testo legale da verificare (consulente) |
| Cookie | `/cookie` | Titolo, meta, paragrafi (pubblico, admin, futuro) | `content/*.php` `cookies.title`, `cookies.description`, `cookies.public|admin|future.h|p` | text | page | sì | E + flag bozza | Descrive il comportamento tecnico reale: modifiche con cautela |
| Appartamenti | `/appartamenti` | Titolo, meta description, testo "nessun appartamento", link "Scopri…" | `content/*.php` `apartments.*` | text | page | facoltativo | D | Bassa priorità |
| Appartamento | `/appartamenti/{slug}` | Intestazioni di sezione, orari, regole, CTA, "richiesta non disponibile online" | `content/*.php` `apartment.*`, `fact.*` | text | page | no | A | I dati vengono dal DB |
| Legale | Privacy, Cookie | Avviso "Bozza" | `content/*.php` `legal.draft` | text | page | no | A (mostrato dal flag bozza) | |
| Richiesta | Flusso | Etichette, passi, errori, riepilogo, ricevuta | `content/*.php` `request.*`, `flow.*`, `form.*`, `err.*`, `summary.*`, `unit.*` | text | flow | no | A | Collegati a segnaposto e test |
| Errori | 404/500 | Titolo e link "Torna alla pagina iniziale" | `content/*.php` `error.home`; `templates/error.php` | text | page | no | A | |

## Appartamenti (entità esistente)

| Area | Pagina/entità | Campo/contenuto | Posizione attuale | Tipo | Scope | Admin? | Destinazione proposta | Note |
|---|---|---|---|---|---|---|---|---|
| Appartamento | `apartments` | Nome, attivo, richieste online, gestione/agenzia | DB, admin Appartamenti (`templates/admin/apartments/edit.php`) | varie | entity | sì (già) | G | Slug immutabile |
| Appartamento | `apartments` | Capienza, camere, letti, max bambini/animali | DB, admin | numeri | entity | sì (già) | G | Valori mancanti |
| Appartamento | `apartments` | Orari arrivo/partenza | DB, admin | time | entity | sì (già) | G | Legacy: orari di consegna e rilascio (da verificare) |
| Appartamento | `apartments` | Prezzo indicativo | DB, admin | money | entity | sì (già) | G | I prezzi legacy NON sono validi |
| Appartamento | `apartment_translations` | Descrizione, regole, meta title/description IT/EN | DB, admin | text | entity | sì (già) | G | Descrizioni legacy da verificare |
| Appartamento | `apartment_translations.amenities` | **Servizi**: campo di testo per lingua, una voce per riga (max 30 da 100 caratteri) | migrazione `0005_apartment_amenities.sql`, `app/Site/Amenities.php`, admin Appartamenti, `apartment.amenities` | lista | entity | sì (già) | G (catalogo + assegnazione nel prompt 20) | Nessun servizio inserito. Il legacy (`DatiAppartamenti.jsx`) elenca: Piscina, Posto auto, Terrazzo, Portico, Televisione, Barbecue, Animali domestici, ricarica auto: **da verificare** |
| Appartamento | — | Foto (galleria, copertina) | assenti; segnaposto; il legacy riusa le stesse foto per più appartamenti (`slides` in `DatiAppartamenti.jsx`) | media | entity | sì | G + H | SPEC §4 |
| Appartamento | — | Descrizione breve per le schede | assente (`templates/public/_apartment_card.php`) | text | entity | facoltativo | G | OPTIONAL |
| Appartamento | — | Stelle | legacy `stelle`, `mezzaStella`, `stelleVuote` in `DatiAppartamenti.jsx` | numero | entity | no | — | Fonte ignota: non pubblicare |
| Listino | `seasonal_rates`, `pricing_rules` | Periodi, tariffe, regole, etichette IT/EN | DB, admin Listino | varie | entity | sì (già) | G | |

## SEO derivato (non amministrabile)

| Area | Pagina/entità | Campo/contenuto | Posizione attuale | Tipo | Scope | Admin? | Destinazione proposta | Note |
|---|---|---|---|---|---|---|---|---|
| SEO | Tutte | Canonical, hreflang, sitemap, robots, breadcrumb | `templates/layout.php`, `app/Site/Seo.php` (nessun file statico in `public/`) | — | global | no | I | |
| SEO | Home | JSON-LD `LodgingBusiness` | `templates/layout.php` (solo con recapiti configurati) | — | page | no | I (dalle impostazioni) | |
| SEO | Tutte | `og:image` | assente | media | page | no | I (hero/copertina) | |

## Codice non usato

| Elemento | Posizione | Proposta |
|---|---|---|
| Template morto con solo un `<h1>` | `templates/pages/home.php` (nessun riferimento) | Rimozione nel prompt 28, dopo prova |

## Funzioni o contenuti del legacy assenti nel nuovo sito

| Elemento legacy | Posizione (cronologia Git, `ae3129e^`) | Proposta |
|---|---|---|
| URL `/dovesiamo` | `legacy/src/App.jsx` (riga con `path="/dovesiamo"`) | Redirect 301 a `/dintorni` (prompt 22) |
| Servizi per appartamento | `components/Appartamenti/DatiAppartamenti.jsx` | Catalogo servizi (prompt 20), inserito come non attivo e da confermare |
| Galleria foto e slider | `GallerySlider.jsx`, `SliderApartament.jsx`, `ImageSlider.jsx`, `ScrollImage.jsx` | Gallerie dalla libreria foto, senza slider JavaScript |
| Mappa incorporata | `DoveSiamo.jsx` (iframe Google Maps) | Resta il link esterno già presente |
| Dati aziendali, TripAdvisor | `ContactUs.jsx` | Impostazioni (prompt 18) |
| Testi su Castelli, Terme Berzieri, Parco dello Stirone, Busseto | `DoveSiamo.jsx` | Sezioni nascoste "[DA VERIFICARE]" (prompt 22) |
| Titolo "Un agriturismo con piscina immerso nel verde" | `Hero.jsx` | Proposta da verificare (prompt 22) |
| Valutazioni a stelle | `DatiAppartamenti.jsx` | Non pubblicare senza fonte |
| "Affitto sala per eventi" | citata in `docs/MISSING_DATA.md` come motivo di contatto legacy, ma **non riscontrata** nei file legacy tracciati letti: probabilmente era nel modulo contatti rimosso in Fase 1b | Chiedere al titolare se il servizio esiste |
