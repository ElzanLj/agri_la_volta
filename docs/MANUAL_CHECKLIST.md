# Lista di controllo manuale (da eseguire a cura del titolare)

Stato di **tutte** le voci: **NOT RUN** (non eseguite). Motivo: richiedono un browser reale, dispositivi, una tastiera e, per alcune, uno screen reader o servizi esterni che l'agente di sviluppo non ha. I controlli automatici (`docs/TEST_REPORT.md`) coprono struttura, contrasto, nomi accessibili, SEO e flussi, ma **non sostituiscono** queste prove (SPEC §26 e §36).

Come usarla: lavora con il sito in locale (`docker compose up -d`, http://localhost:8080) o sull'hosting, per ogni riga scrivi `OK`, `KO` o `N/A` e, se `KO`, cosa hai visto (pagina, passaggio, browser). Poi riporta i risultati nella sessione successiva: gli esiti `KO` diventano bug da correggere.

Dati di prova: nelle prove usa dati inventati (es. "Prova Test", `prova@example.com`), mai dati di persone reali. Per avere appartamenti con prezzo, inserisci un listino di prova dall'admin (Listino) **in un ambiente locale**, non in quello di produzione.

## 1. Preparazione (5 minuti)

| # | Passo | Esito | Note |
|---|---|---|---|
| 1.1 | Accedi all'admin (`/admin`) e inserisci, in Appartamenti, capienza, descrizione e alcuni servizi (una voce per riga) per almeno un appartamento (IT e EN) | | |
| 1.2 | In Listino inserisci una tariffa di prova che copra le date che userai | | |

## 2. Tastiera (solo tastiera, senza mouse) — SPEC §26

Usa Tab / Maiusc+Tab / Invio / Spazio / frecce. Ripeti almeno su Chrome o Edge **e** su Firefox o Safari.

| # | Controllo | Esito | Note |
|---|---|---|---|
| 2.1 | Primo Tab dalla home: appare "Salta al contenuto"; Invio porta al contenuto principale | | |
| 2.2 | Si raggiungono, in ordine logico, logo, menu, pulsante "Richiedi disponibilità" e tutti i link della pagina | | |
| 2.3 | Il punto in cui si trova il focus è **sempre ben visibile** (contorno arancione) su link, pulsanti e campi | | |
| 2.4 | Nessun punto in cui il focus resta intrappolato o sparisce | | |
| 2.5 | Modulo di richiesta: si possono compilare date, ospiti, dati e consenso e inviare **solo con la tastiera** | | |
| 2.6 | Errori del modulo (lascia vuoto un campo e invia): il messaggio è chiaro e i link nell'elenco errori portano al campo giusto | | |
| 2.7 | Il campo nascosto antispam non riceve mai il focus | | |
| 2.8 | Cambio lingua (IT ↔ EN) raggiungibile e utilizzabile da tastiera | | |
| 2.9 | Admin: login, elenco richieste, conferma/rifiuto, cancellazione, calendario si usano da tastiera | | |

## 3. Screen reader (facoltativo ma consigliato: NVDA su Windows, VoiceOver su Mac/iPhone, TalkBack su Android)

| # | Controllo | Esito | Note |
|---|---|---|---|
| 3.1 | Titolo pagina e lingua letti correttamente (italiano / inglese) | | |
| 3.2 | I punti di riferimento (intestazione, navigazione, contenuto principale, piè di pagina) sono annunciati | | |
| 3.3 | Ogni campo del modulo è annunciato con la sua etichetta; gli errori vengono letti | | |
| 3.4 | I segnaposto delle foto sono letti come "Fotografia di …: segnaposto" e non creano confusione | | |
| 3.5 | La tabella del prezzo nel riepilogo è letta in modo comprensibile (voce e importo) | | |

## 4. Mobile (telefono vero, in verticale e in orizzontale) — SPEC §22

| # | Controllo | Esito | Note |
|---|---|---|---|
| 4.1 | Nessuno scorrimento orizzontale su nessuna pagina | | |
| 4.2 | Menu: tutte le voci sono visibili, toccabili senza errori (bersagli abbastanza grandi) | | |
| 4.3 | Testo leggibile senza zoom; pulsanti e campi comodi da toccare | | |
| 4.4 | Il campo data apre il selettore del telefono e le date scelte compaiono correttamente | | |
| 4.5 | Flusso completo di richiesta fino a "Richiesta ricevuta" | | |
| 4.6 | Pulsante telefono/WhatsApp (se configurati) aprono l'app giusta, con messaggio precompilato **modificabile** | | |
| 4.7 | Zoom del browser al 200%: nessun testo tagliato o sovrapposto | | |

## 5. Desktop

| # | Controllo | Esito | Note |
|---|---|---|---|
| 5.1 | Home, L'agriturismo, Appartamenti, pagina di ogni appartamento, Dintorni, Richiedi disponibilità, Contatti, Privacy, Cookie si aprono e sono ben impaginate | | |
| 5.2 | Finestra stretta e finestra larga: layout ordinato | | |
| 5.3 | Stampa o anteprima di stampa del riepilogo: leggibile | | |

## 6. Italiano e inglese (SPEC §20)

| # | Controllo | Esito | Note |
|---|---|---|---|
| 6.1 | Ogni pagina ha la versione inglese sotto `/en` e il link di cambio lingua porta alla pagina equivalente | | |
| 6.2 | Nessun testo in italiano nelle pagine inglesi (e viceversa), nemmeno nei messaggi di errore | | |
| 6.3 | Le email (conferma, rifiuto) arrivano nella lingua scelta dal cliente | | |
| 6.4 | Revisione dell'inglese provvisorio: segnala le correzioni (i testi sono in `content/en.php`) | | |

## 7. Contenuti e foto (decisioni del titolare)

| # | Controllo | Esito | Note |
|---|---|---|---|
| 7.1 | Nessuna foto di provenienza dubbia è pubblicata; ogni foto nuova ha fonte e licenza annotate in `docs/IMAGES.md` | | |
| 7.2 | Telefono, email, indirizzo e WhatsApp mostrati sono quelli ufficiali | | |
| 7.3 | Testi di L'agriturismo e Dintorni forniti e controllati | | |
| 7.4 | Privacy e cookie policy verificate da titolare/consulente | | |

## 8. Email e hosting reali (solo con credenziali e hosting scelto)

| # | Controllo | Esito | Note |
|---|---|---|---|
| 8.1 | Una richiesta di prova genera la notifica al gestore e arriva davvero (anche controllare spam) | | |
| 8.2 | Conferma e rifiuto arrivano al cliente; la cancellazione **non** invia nulla da sola, la bozza si modifica e si invia a mano | | |
| 8.3 | Con SMTP spento o sbagliato la richiesta resta salvata e Admin > Email mostra l'errore e il pulsante "Riprova" | | |
| 8.4 | HTTPS attivo su tutto il dominio; il cookie di sessione dell'admin è `Secure`; solo allora attivare `HSTS_MAX_AGE` | | |
| 8.5 | `storage/`, `.env`, `app/`, `migrations/` non raggiungibili dal browser (devono dare 403/404) | | |
| 8.6 | Il sito funziona sull'hosting scelto (PHP 8.1+, `pdo_mysql`, `mod_rewrite`) e la cartella `vendor/` è stata caricata | | |

## Esito complessivo

Data della prova: ____________ · Browser/dispositivi: ______________________________ · Eseguita da: ____________

I risultati KO vanno riportati in `docs/TEST_REPORT.md` (sezione "Prove manuali") e aperti come bug.
