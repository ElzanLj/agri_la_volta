# Prompt 18 — Impostazioni del sito

Prerequisito: prompt 17 completato; modello approvato in `docs/CMS_DESIGN.md`.

Contesto: oggi recapiti e WhatsApp arrivano da `.env` (`app/Site/Contacts.php`) e sono mostrati solo se configurati; dati aziendali, link alle recensioni e mappa esistono solo nel legacy. Nessun dato va inventato.

Riferimento visivo (non vincolante): `docs/mockup-admin/impostazioni.html`.

## Obiettivo

Rendere amministrabili le impostazioni globali del sito con una sola tabella tipizzata e una pagina admin "Impostazioni".

## Prima di modificare

- Vincoli: nessun nuovo framework, dipendenza Composer di produzione, JavaScript pubblico, React, Firebase o WordPress; nessun refactor fuori scope; preserva ciò che funziona; codice rimosso solo se dimostrato inutile. Rispetta `docs/GUARDRAIL_FASI.md` (ramo Git per fase, prove obbligatorie, contenuti come dati, file vietati, nessun segreto).
- Verifica lo stato del prompt 17 e la sezione impostazioni di `docs/CMS_DESIGN.md`; leggi `docs/THREAT_MODEL.md` e in `docs/REVIEW_PRE_ROADMAP.md` le schede B12, B13, B14, J12.
- Leggi `Contacts.php`, `templates/layout.php`, `templates/public/contact.php`, `app/Mail/MessageBuilder.php`, `CancellationDraft.php`, `app/Support/WhatsApp.php`, `ScopeTest`.
- Leggi `docs/CAMPI_CONTENUTI.md` (approvato nel prompt 16): ogni campo toccato da questa fase segue la sua riga (obbligatorietà, limiti, comportamento se vuoto, segnalazione). Se un campo nuovo non ha una riga, aggiungila e falla confermare.
- `git status` e diff locali.

## Domande per l'utente (all'inizio della fase)

Prima di modificare qualsiasi file, apri `docs/RISPOSTE_UTENTE.md`: se l'utente ha già segnato le risposte per questo prompt, mostrale e chiedi solo conferma. Altrimenti presenta le domande di questa sezione **così come sono scritte** (risposte possibili e consiglio) e **fermati finché non risponde**.

Le domande sono di due tipi, indicati nel titolo:

- **[titolare]**: decisioni sul business (prezzi, regole, testi, comunicazioni agli ospiti, dati legali, servizi esterni). Non applicare mai una risposta ✅ al posto dell'utente: serve la sua risposta scritta in chat ("consigliate" vale, se detto esplicitamente per questa fase). Senza risposta la fase non inizia.
- **[tecnica]**: scelte di implementazione. Se l'utente risponde "consigliate" o non ha preferenze, vale la risposta ✅; elencale comunque nel riepilogo finale.

Registra ogni scelta in `docs/DECISIONS.md`. In `docs/RISPOSTE_UTENTE.md` (casella con `x`) scrivi **solo** ciò che l'utente ha detto in chat, mai risposte tue. Le voci rinviate o rifiutate vanno in `docs/TODO.md` con il motivo. Non implementare nulla che non sia stato approvato. Se una voce approvata supera lo scope di questa fase, proponi di spostarla e fermati.

### D1 — Numeri di telefono [titolare]

| Risposta | Pro / contro |
|---|---|
| A. Un solo campo | Più semplice; il legacy aveva fisso e cellulare |
| B. ✅ Due campi: fisso e cellulare, entrambi facoltativi | Rispecchia la realtà dell'agriturismo; un campo in più |

**Consiglio: B.**

### D2 — Ragione sociale, P.IVA e REA [titolare]

| Risposta | Pro / contro |
|---|---|
| A. ✅ Nel piè di pagina di tutte le pagine, solo se compilati | È dove di norma vanno per i siti di imprese (verificare col consulente) |
| B. Solo nella pagina Contatti | Footer più pulito; meno visibili |
| C. Non mostrarli | Nessun lavoro; possibile mancanza di un obbligo |

**Consiglio: A.**

### D3 — Link alle recensioni [titolare]

| Risposta | Pro / contro |
|---|---|
| A. ✅ Due campi facoltativi: TripAdvisor e Google | Copre le due fonti principali; mostrati solo se compilati |
| B. Solo TripAdvisor (come il legacy) | Minimo; Google è spesso più usato |
| C. Nessun link | Nessun lavoro; si perde un elemento di fiducia |

**Consiglio: A.** Mai recensioni o stelle copiate senza fonte.

### D4 — Posizione sulla mappa [titolare]

| Risposta | Pro / contro |
|---|---|
| A. ✅ Un link "Apri in Google Maps" (URL inserito dal titolare) | Nessun cookie di terzi, nessuna dipendenza (SPEC §25) |
| B. Link + coordinate (apre anche Apple Maps e altre app) | Più universale; due campi da compilare |
| C. Mappa incorporata (iframe) | Più visiva; cookie di terzi: richiederebbe banner e modifica della cookie policy |

**Consiglio: A.**

### D5 — Orari di arrivo/partenza e regole della casa uguali per tutti gli appartamenti [titolare]

| Risposta | Pro / contro |
|---|---|
| A. ✅ Valori comuni nelle impostazioni; il singolo appartamento può sovrascriverli | Si compila una volta invece di sei (e dodici testi IT/EN) |
| B. Restano solo per appartamento | Nessun lavoro; dati ripetuti e facili da disallineare |

**Consiglio: A.** Il campo dell'appartamento, se compilato, vince.

### D6 — Firma delle email dalle impostazioni [titolare]

| Risposta | Pro / contro |
|---|---|
| A. ✅ Sì: nome, telefoni ed email in fondo alle email arrivano dalle impostazioni | Sempre aggiornata; i testi delle email restano nel codice |
| B. No, resta nel codice | Nessun lavoro; ogni cambio richiede uno sviluppatore |

**Consiglio: A.**

### D7 — Avviso globale in cima a tutte le pagine [titolare]

Es. "Chiusi dal 7 gennaio al 15 febbraio", "Piscina aperta da giugno".

| Risposta | Pro / contro |
|---|---|
| A. ✅ Sì: interruttore, testo IT/EN, data di fine facoltativa (dopo la data sparisce da solo) | Comunica chiusure e novità su tutto il sito; senza data di fine resta online finché non lo spegni |
| B. No | Si usa solo l'offerta in home (prompt 21) |

**Consiglio: A.** Testo semplice, niente link né HTML; ruolo `region` con etichetta, non un popup.

### D8 — Orari in cui rispondete al telefono [titolare]

| Risposta | Pro / contro |
|---|---|
| A. ✅ Sì, un campo IT/EN accanto ai telefoni (es. "tutti i giorni 9–20") | Evita chiamate a ore impossibili |
| B. No | — |

**Consiglio: A.**

### D9 — Instagram e Facebook [titolare]

| Risposta | Pro / contro |
|---|---|
| A. ✅ Due campi facoltativi, mostrati solo se compilati, come semplici link (nessun widget né script dei social) | Pronti quando i profili esisteranno; nessun cookie di terzi |
| B. No | — |

**Consiglio: A.**

## Implementa

- Per ogni campo: limite scritto sotto il campo, frase "se lo lasci vuoto…" per i facoltativi, errori accanto al campo con i valori conservati, comportamento sul sito come da `docs/CAMPI_CONTENUTI.md`.
- Migrazione (prossimo numero libero): `site_settings` a riga unica con le colonne approvate. Nessun valore reale nella migrazione.
- Servizio con validazione: email, telefoni, WhatsApp normalizzato con `WhatsApp::normalize`, URL solo `https://`, P.IVA nel formato previsto se compilata. Audit con valori vecchi e nuovi.
- `Contacts` legge dal DB, con la riserva `.env` decisa nel prompt 16.
- Pagina admin "Impostazioni" con spiegazioni brevi per un non tecnico.
- Resa pubblica: footer, pagina Contatti, JSON-LD `LodgingBusiness`, email, avviso globale (su tutte le pagine IT/EN, non indicizzato come contenuto principale), secondo le risposte.
- **Cache su file** (decisione D13 del prompt 16): `storage/cache/settings.php`, riscritta in modo atomico (file temporaneo + `rename`) a ogni salvataggio; contiene solo dati pubblici (mai segreti); se manca viene ricreata dal database. Le pagine pubbliche leggono impostazioni e contatti da lì.
- **Database non raggiungibile**: le pagine pubbliche che ne hanno bisogno rispondono con una pagina 503 breve (IT/EN) con `Retry-After` e i contatti presi dalla cache (o da `.env` come riserva). L'admin non è coinvolto.
- **Blocco ottimistico riusabile** (decisione D14 del prompt 16): helper unico che aggiunge al modulo la data dell'ultima modifica e rifiuta il salvataggio se nel frattempo è cambiata ("Qualcun altro ha modificato queste impostazioni: ricarica prima di salvare"), conservando ciò che l'utente ha scritto. Usalo qui; lo riuseranno i prompt 20 e 21.
- **Partita IVA**: oltre alle 11 cifre, controllo della cifra di controllo (algoritmo pubblico); un errore dice "Controlla le cifre".
- **Avviso globale**: `role="region"` con etichetta (non `role="alert"`, che verrebbe letto a ogni pagina); data di fine verificata a ogni richiesta, anche con la cache attiva.
- Aggiorna l'elenco tabelle atteso in `ScopeTest` motivandolo.

## Test obbligatori

- **contratto dei campi**: per ogni campo di questa fase i "Test minimi" di `docs/CAMPI_CONTENUTI.md` (vuoto, solo spazi, troppo lungo, non valido, con HTML, resa IT/EN, Storico) e i controlli tra campi che lo riguardano;
- migrazione da zero e su DB esistente; `bin/migrate.php --status`;
- autorizzazione e CSRF; validazione di ogni campo; URL `javascript:` e `http:` rifiutati;
- escape in HTML, attributi `href`, JSON-LD (`</script>`), email;
- campi vuoti omessi ovunque; riserva `.env` se approvata;
- valori comuni e sovrascrittura per appartamento (se D5 = A);
- **cache**: scritta a ogni salvataggio, ricreata se manca, mai letta a metà (scrittura atomica), senza segreti; modifica nel database senza passare dall'admin → vale la cache fino al prossimo salvataggio (comportamento documentato);
- **database offline** (connessione che fallisce nel test): home, Contatti, Privacy rispondono con la cache o con la pagina 503 e `Retry-After`; nessun errore 500; nessun dettaglio tecnico nella risposta;
- **blocco ottimistico**: due schede aperte, salvataggio della prima ok, della seconda rifiutato con i valori preservati; salvataggio normale invariato;
- P.IVA: valida accettata, cifra di controllo sbagliata e lunghezza errata rifiutate;
- avviso: scade alla data di fine anche con la cache; `role="region"`; testo con HTML mostrato come testo;
- audit della modifica; suite completa PASS.

Aggiorna `MISSING_DATA`, `COMMANDS`, `TEST_REPORT`, `DECISIONS`, `TODO`, `SESSION_STATE`.

## Prova tu (5–10 minuti, a fine fase)

L'agente elenca questi passi nel riepilogo finale; l'utente li esegue in locale.

1. Apri **Impostazioni** e compila i campi con valori di prova: confronta con `docs/mockup-admin/impostazioni.html`.
2. Controlla piè di pagina, pagina Contatti (IT ed EN) e pulsante WhatsApp.
3. Svuota un campo: deve sparire dal sito, senza testi segnaposto.
4. Invia una richiesta di prova e guarda la firma dell'email in `storage/mail/` (sviluppo con `MAIL_TRANSPORT=log`).
5. Ferma il database (`docker compose stop db`) e apri la home e Contatti: devono comparire i contatti oppure la pagina "torniamo presto", mai un errore tecnico. Poi riavvia il database.
6. Apri **Impostazioni** in due schede, salva dalla prima e poi dalla seconda: la seconda deve avvisarti senza farti perdere ciò che hai scritto.

## Stop condition

Fermati quando le impostazioni approvate sono modificabili, validate, usate nelle pagine e nelle email e testate. Fermati prima di iniziare se l'utente non ha risposto alle domande [titolare]. **Non** gestire foto, pagine o servizi degli appartamenti (prompt 19–21) e **non** applicare il blocco ottimistico ai moduli esistenti degli appartamenti e del listino (lo fanno i prompt 20 e 23).
