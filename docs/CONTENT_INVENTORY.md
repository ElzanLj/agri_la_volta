# Inventario dei contenuti — BOZZA

Stato: **BOZZA del 2026-10-07**, da verificare e completare con `prompts/14_STATE_SYNC_CONTENT_AUDIT.md`. Ogni riga è stata trovata nel repository; le posizioni vanno ricontrollate prima di usarle.

Categorie: **A** testo dell'interfaccia nel codice · **B** configurazione tecnica · **C** impostazione globale · **D** campo di pagina · **E** sezione di pagina · **F** blocco riutilizzabile (nessuno trovato: da confermare nel prompt 16) · **G** entità strutturata nel DB · **H** media · **I** derivato automaticamente.

I testi presenti solo in `legacy/` sono **proposte da verificare dal titolare** (decisione Fase 5: nessun testo legacy pubblicato senza verifica).

## Globali

| Area | Pagina/entità | Campo/contenuto | Posizione attuale | Tipo | Scope | Admin? | Destinazione proposta | Note |
|---|---|---|---|---|---|---|---|---|
| Brand | Globale | Nome "Agriturismo La Volta" | `templates/layout.php` (`$siteName`), `templates/public/home.php` (h1), `SiteController::home`, `MessageBuilder` (oggetti email), `MAIL_FROM_NAME` | text | global | no | A | Ripetuto in 4 punti: valutare una costante unica (non amministrabile) |
| Contatti | Globale | Telefono | `.env` `PUBLIC_PHONE` → `app/Site/Contacts.php`; usato in footer, Contatti, JSON-LD | tel | global | sì | C | Legacy: fisso 0524 587057 e cellulare +39 338 5772918 (DA VERIFICARE): forse servono due campi |
| Contatti | Globale | Email pubblica | `.env` `PUBLIC_EMAIL` | email | global | sì | C | Legacy in conflitto: `.com` (ContactUs) vs `.it` (DoveSiamo) |
| Contatti | Globale | Indirizzo | `.env` `PUBLIC_ADDRESS` | textarea | global | sì | C | Legacy: "Marzano, Salsomaggiore Terme (PR)" |
| Contatti | Globale | Numero WhatsApp | `.env` `WHATSAPP_NUMBER` | tel | global | sì | C | Probabilmente il cellulare legacy |
| Contatti | Globale | Prefisso predefinito WhatsApp | `.env` `WHATSAPP_DEFAULT_COUNTRY_CODE` | text | global | no | B | |
| Email | Globale | Destinatario notifiche nuove richieste | `.env` `MAIL_ADMIN_ADDRESS` | email | global | no | B | Legato alla configurazione SMTP |
| Email | Globale | Firma/recapiti nelle email | `app/Mail/MessageBuilder.php`, `CancellationDraft.php` | text | global | no | I (dalle impostazioni) | Testi delle email restano A |
| Legale | Globale | Ragione sociale, P.IVA, REA | solo legacy `ContactUs.jsx` | text | global | sì | C | Assenti nel nuovo sito; da verificare col titolare |
| Link | Globale | Link TripAdvisor | solo legacy `ContactUs.jsx` | url | global | sì | C | Facoltativo |
| Link | Globale | Posizione/mappa | legacy `DoveSiamo.jsx` (iframe Google Maps, coordinate 44.798, 9.965) | url | global | sì | C (link, niente embed) | SPEC §25; il sito pubblico non usa cookie |
| Link | Globale | Social (Facebook) | legacy `DoveSiamo.jsx` (commentato) | url | global | no | — | Nessun profilo confermato |
| Navigazione | Globale | Voci di menu, link lingua, skip link | `content/*.php` `nav.*`, `a11y.skip`; `layout.php` | text | global | no | A | Le pagine sono fisse |
| Footer | Globale | Struttura footer | `templates/layout.php` | — | global | no | I | Contenuto dalle impostazioni |
| Brand | Globale | Favicon / logo | `public/assets/favicon.svg`; legacy `logo1.webp`, `agriLogo.png` | media | global | no | A/H | Logo legacy di provenienza da verificare |

## Pagine

| Area | Pagina/entità | Campo/contenuto | Posizione attuale | Tipo | Scope | Admin? | Destinazione proposta | Note |
|---|---|---|---|---|---|---|---|---|
| Home | Home | Titolo h1 | `templates/public/home.php` (fisso) | text | page | sì | D | Legacy: "Un agriturismo con piscina immerso nel verde" (da verificare) |
| Home | Home | Testo introduttivo | `content/*.php` `home.lead` | text | page | sì | D | |
| Home | Home | Immagine hero | segnaposto `_photo.php` | media | page | sì | H (riferimento) | Hero legacy hotlinkata: non riusabile |
| Home | Home | Meta description | `content/*.php` `home.description` | text | page | sì | D | |
| Home | Home | Pulsanti e titoli sezioni | `nav.request`, `home.see_apartments`, `home.more` | text | page | no | A | |
| Home | Home | Elenco appartamenti | `ApartmentRepository::listPublic` | — | page | no | I | |
| Home | Home | Presentazione/galleria | legacy `ScrollImage.jsx`, `GallerySlider.jsx` | text+media | page | sì | E | Testo legacy da verificare; immagini legacy esterne/dubbie |
| Agriturismo | /agriturismo | Titolo, meta description | `content/*.php` `farm.title`, `farm.description` | text | page | sì | D | |
| Agriturismo | /agriturismo | Corpo della pagina | segnaposto `page.pending` | text+media | page | sì | E | Nessun testo nel nuovo sito |
| Dintorni | /dintorni | Titolo, meta description | `content/*.php` `around.*` | text | page | sì | D | |
| Dintorni | /dintorni | Luoghi (posizione, castelli, Terme Berzieri, Parco dello Stirone, Busseto) | legacy `DoveSiamo.jsx` | text+media | page | sì | E (una sezione per luogo) | Testi legacy da verificare; foto legacy dubbie |
| Contatti | /contatti | Titolo, meta description, testo "in arrivo" | `content/*.php` `contact.*` | text | page | parziale | D (titolo/meta, intro facoltativa) | Recapiti: I dalle impostazioni |
| Privacy | /privacy | Paragrafi e voci da completare | `content/*.php` `privacy.*` | text | page | sì | E + flag bozza | Testo legale da verificare (consulente) |
| Cookie | /cookie | Paragrafi | `content/*.php` `cookies.*` | text | page | sì | E + flag bozza | Descrive il comportamento tecnico reale: modifiche con cautela |
| Appartamenti | /appartamenti | Titolo, meta description, testo vuoto | `content/*.php` `apartments.*` | text | page | facoltativo | D | Bassa priorità |
| Legale | Tutte | Avviso "Bozza" | `content/*.php` `legal.draft` | text | page | no | A (mostrato dal flag bozza) | |
| Richiesta | Flusso | Etichette, passi, errori, messaggi di ricevuta | `content/*.php` `flow.*`, `form.*`, `err.*`, `summary.*` | text | flow | no | A | Collegati a segnaposto e test |

## Appartamenti (entità esistente)

| Area | Pagina/entità | Campo/contenuto | Posizione attuale | Tipo | Scope | Admin? | Destinazione proposta | Note |
|---|---|---|---|---|---|---|---|---|
| Appartamento | apartments | Nome, attivo, richieste online, gestione/agenzia | DB, admin Appartamenti | varie | entity | sì (già) | G | Slug immutabile |
| Appartamento | apartments | Capienza, camere, letti, max bambini/animali | DB, admin | numeri | entity | sì (già) | G | Valori mancanti |
| Appartamento | apartments | Orari arrivo/partenza | DB, admin | time | entity | sì (già) | G | Legacy 11–18 / entro 9 (da verificare) |
| Appartamento | apartments | Prezzo indicativo | DB, admin | money | entity | sì (già) | G | Prezzi legacy NON validi |
| Appartamento | apartment_translations | Descrizione, regole, meta title/description IT/EN | DB, admin | text | entity | sì (già) | G | Descrizioni legacy da verificare |
| Appartamento | — | Foto (galleria, copertina) | assenti; legacy `DatiAppartamenti.jsx` riusa le stesse foto | media | entity | sì | G + H | SPEC §4 |
| Appartamento | — | Servizi (piscina, posto auto, terrazzo/portico, TV, barbecue, animali, ricarica auto) | assenti; legacy `DatiAppartamenti.jsx` | lista | entity | sì | G (catalogo + assegnazione) | SPEC §4; icone non necessarie |
| Appartamento | — | Descrizione breve per le schede | assente | text | entity | facoltativo | G | OPTIONAL |
| Appartamento | — | Stelle | legacy `stelle` | numero | entity | no | — | Fonte ignota |
| Listino | seasonal_rates, pricing_rules | Periodi, tariffe, regole, etichette IT/EN | DB, admin Listino | varie | entity | sì (già) | G | |

## SEO derivato (non amministrabile)

| Area | Pagina/entità | Campo/contenuto | Posizione attuale | Tipo | Scope | Admin? | Destinazione proposta | Note |
|---|---|---|---|---|---|---|---|---|
| SEO | Tutte | Canonical, hreflang, sitemap, robots, breadcrumb | `layout.php`, `app/Site/Seo.php` | — | global | no | I | |
| SEO | Home | JSON-LD `LodgingBusiness` | `layout.php` | — | page | no | I (dalle impostazioni) | |
| SEO | Tutte | `og:image` | assente | media | page | no | I (hero/copertina) | |

## Funzioni o contenuti del legacy assenti nel nuovo sito

| Elemento legacy | Posizione | Proposta |
|---|---|---|
| URL `/dovesiamo` | `legacy/src/App.jsx` | Redirect 301 a `/dintorni` |
| Servizi per appartamento | `DatiAppartamenti.jsx` | Catalogo servizi (prompt 20) |
| Galleria foto | `GallerySlider.jsx`, `SliderApartament.jsx` | Gallerie da media library, senza slider JS |
| Mappa incorporata | `DoveSiamo.jsx` | Link esterno (impostazioni) |
| Dati aziendali, TripAdvisor | `ContactUs.jsx` | Impostazioni |
| "Affitto sala per eventi" | motivi di contatto legacy (`MISSING_DATA`) | Sezione di pagina solo se confermato |
