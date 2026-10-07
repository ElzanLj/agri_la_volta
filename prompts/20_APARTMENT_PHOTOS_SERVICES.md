# Prompt 20 — Foto, servizi e dettagli degli appartamenti

Prerequisito: prompt 19 completato.

Contesto: SPEC §4 richiede per ogni appartamento fotografie e servizi; oggi mancano entrambi. Gli appartamenti sono già un'entità strutturata con admin (`ApartmentAdminService`, `templates/admin/apartments/edit.php`). Il legacy elencava servizi (piscina, posto auto, terrazzo/portico, TV, barbecue, animali, ricarica auto): **solo proposta**, da confermare col titolare.

Riferimento visivo (non vincolante): `docs/mockup-admin/appartamento.html`, `servizi.html`.

## Obiettivo

Estendere l'entità appartamento con galleria foto, servizi e i dettagli approvati, amministrabili e mostrati nelle pagine pubbliche.

## Prima di modificare

- Vincoli: nessun nuovo framework, dipendenza Composer di produzione, JavaScript pubblico, React, Firebase o WordPress; nessun refactor fuori scope; preserva ciò che funziona; codice rimosso solo se dimostrato inutile. Rispetta `docs/GUARDRAIL_FASI.md` (ramo Git per fase, prove obbligatorie, contenuti come dati, file vietati, nessun segreto).
- Verifica lo stato del prompt 19 e il design per foto e servizi; leggi in `docs/REVIEW_PRE_ROADMAP.md` le schede A6, B10, B14, J9, J13 e `docs/THREAT_MODEL.md`.
- Leggi `ApartmentRepository`, `ApartmentAdminService`, i template admin e pubblici degli appartamenti, `_apartment_card.php`, `_photo.php`, `_facts.php`, `layout.php` (Open Graph).
- Leggi `docs/CAMPI_CONTENUTI.md` (approvato nel prompt 16): ogni campo toccato da questa fase segue la sua riga (obbligatorietà, limiti, comportamento se vuoto, segnalazione). Se un campo nuovo non ha una riga, aggiungila e falla confermare.
- `git status` e diff locali.

## Domande per l'utente (all'inizio della fase)

Prima di modificare qualsiasi file, apri `docs/RISPOSTE_UTENTE.md`: se l'utente ha già segnato le risposte per questo prompt, mostrale e chiedi solo conferma. Altrimenti presenta le domande di questa sezione **così come sono scritte** (risposte possibili e consiglio) e **fermati finché non risponde**.

Le domande sono di due tipi, indicati nel titolo:

- **[titolare]**: decisioni sul business (prezzi, regole, testi, comunicazioni agli ospiti, dati legali, servizi esterni). Non applicare mai una risposta ✅ al posto dell'utente: serve la sua risposta scritta in chat ("consigliate" vale, se detto esplicitamente per questa fase). Senza risposta la fase non inizia.
- **[tecnica]**: scelte di implementazione. Se l'utente risponde "consigliate" o non ha preferenze, vale la risposta ✅; elencale comunque nel riepilogo finale.

Registra ogni scelta in `docs/DECISIONS.md`. In `docs/RISPOSTE_UTENTE.md` (casella con `x`) scrivi **solo** ciò che l'utente ha detto in chat, mai risposte tue. Le voci rinviate o rifiutate vanno in `docs/TODO.md` con il motivo. Non implementare nulla che non sia stato approvato. Se una voce approvata supera lo scope di questa fase, proponi di spostarla e fermati.

### D1 — Catalogo servizi del legacy [titolare]

| Risposta | Pro / contro |
|---|---|
| A. Partire da un catalogo vuoto | Nessun dato non verificato; il titolare scrive tutto |
| B. ✅ Proporre il catalogo legacy (etichette IT/EN) inserito come **non attivo**, da confermare voce per voce | Il titolare spunta invece di scrivere; nulla è pubblico senza conferma |
| C. Inserirlo attivo | Veloce; pubblica dati non verificati |

**Consiglio: B.** L'assegnazione ai singoli appartamenti resta comunque al titolare.

### D2 — Icone per i servizi [tecnica]

| Risposta | Pro / contro |
|---|---|
| A. ✅ Solo testo | Nessun file in più, accessibile, nessuna licenza da verificare |
| B. Piccole icone SVG disegnate nel progetto | Più leggibile a colpo d'occhio; da disegnare e mantenere |

**Consiglio: A.** Si possono aggiungere in seguito.

### D3 — Piano e accessibilità [titolare]

| Risposta | Pro / contro |
|---|---|
| A. ✅ Due campi: "piano" e "accessibile senza scale" sì/no | Informazione chiave per famiglie con passeggino e ospiti anziani; filtrabile in futuro |
| B. Testo libero nelle regole della casa | Nessun lavoro; difficile da trovare |
| C. Niente | — |

**Consiglio: A.** "Accessibile senza scale" è un'affermazione sul tuo alloggio, visibile agli ospiti: compilala solo dopo averla verificata di persona (nessun gradino dall'auto all'ingresso e dentro l'appartamento). Se non sei sicuro, lascia il campo vuoto: sul sito non compare nulla.

### D4 — Descrizione breve per le schede [titolare]

| Risposta | Pro / contro |
|---|---|
| A. ✅ Sì, un campo IT/EN facoltativo (massimo ~160 caratteri) | Schede più invitanti in home ed elenco |
| B. No, le schede mostrano solo i dati numerici | Nessun lavoro |

**Consiglio: A.**

### D5 — Tabella di confronto nella pagina "Appartamenti" [tecnica]

| Risposta | Pro / contro |
|---|---|
| A. ✅ Sì, generata dai dati: persone, camere, letti, animali, piano, servizi principali, prezzo da | Aiuta a scegliere in pochi secondi; nessun dato da inserire due volte |
| B. No | Nessun lavoro |

**Consiglio: A.** Su telefono la tabella scorre in orizzontale dentro il proprio contenitore.

### D6 — Eliminare un servizio assegnato a degli appartamenti [tecnica]

| Risposta | Pro / contro |
|---|---|
| A. ✅ Bloccata, con l'elenco degli appartamenti che lo usano | Nessuna perdita silenziosa |
| B. Permessa, rimuove anche le assegnazioni | Più veloce; facile perdere dati |

**Consiglio: A.**

## Implementa

- Per ogni campo: limite scritto sotto il campo, frase "se lo lasci vuoto…" per i facoltativi, errori accanto al campo con i valori conservati, comportamento sul sito come da `docs/CAMPI_CONTENUTI.md`.
- Migrazione: `apartment_photos` (appartamento, foto, ordine; la prima è la copertina), `amenities` + `apartment_amenities`, e i campi approvati (D3, D4) sull'appartamento.
- Admin: nella scheda appartamento scegli foto dalla libreria, ordina con pulsanti su/giù (POST, niente JavaScript), rimuovi; spunta i servizi; campi approvati. Pagina per il catalogo servizi IT/EN.
- La verifica "foto in uso" del prompt 19 include le gallerie.
- Controllo tra campi: massimo bambini ≤ capienza massima (il minimo persone è nel prompt 23).
- **Avviso sulle conseguenze** (A6, B10): prima di salvare una scheda che disattiva l'appartamento, spegne le richieste online o abbassa la capienza, il modulo elenca le prenotazioni e le richieste future che ne sono toccate (riferimento, date, persone) e chiede una conferma esplicita ("Ho capito, salva comunque"). Non blocca e non modifica nulla di esistente.
- **Blocco ottimistico** (helper del prompt 18) sul modulo dell'appartamento.
- **Prestazioni**: foto e servizi di tutti gli appartamenti di un elenco si leggono con query cumulative (niente N+1); `width` e `height` su ogni immagine; la prima immagine di una pagina con `fetchpriority="high"`, le altre `loading="lazy"`.
- **Testo alternativo inglese**: se manca, la pagina inglese usa quello italiano e la scheda foto lo segnala ("manca EN"); la checklist del prompt 25 lo conta.
- Pubblico: galleria senza slider JavaScript (copertina `eager`, altre `lazy`, `width`/`height`), copertina nelle schede, elenco servizi, voci approvate; segnaposto attuale se non ci sono foto; `og:image` dalla copertina.
- Nessuna foto né assegnazione inserita dalla migrazione.

## Test obbligatori

- **contratto dei campi**: per ogni campo di questa fase i "Test minimi" di `docs/CAMPI_CONTENUTI.md` (vuoto, solo spazi, troppo lungo, non valido, con HTML, resa IT/EN, Storico) e i controlli tra campi che lo riguardano;
- migrazione da zero; vincoli e chiavi esterne; foto o servizio inesistente rifiutato;
- IDOR: nessuna azione su foto o servizi di un altro appartamento modificando gli ID;
- autorizzazione e CSRF su ogni azione; audit;
- ordine e copertina corretti; appartamento inattivo resta 404;
- foto usata non eliminabile; regola D6;
- **avviso A6**: disattivare un appartamento con prenotazioni future mostra l'elenco e richiede la conferma; senza conferma non si salva; con conferma si salva e lo Storico lo registra;
- **blocco ottimistico**: due schede aperte → la seconda viene avvisata e non perde il testo;
- **query**: l'elenco degli appartamenti con foto e servizi usa un numero costante di query al crescere degli appartamenti;
- foto con file mancante → segnaposto e nessun errore 500; alt inglese mancante → fallback italiano;
- escape di etichette, alt e descrizioni brevi; IT ed EN; `og:image` solo con foto;
- pagine senza foto invariate rispetto a oggi; suite completa PASS.

Aggiorna `TEST_REPORT`, `MISSING_DATA` (servizi e dettagli per appartamento da confermare), `DECISIONS`, `TODO`, `SESSION_STATE`.

## Prova tu (5–10 minuti, a fine fase)

L'agente elenca questi passi nel riepilogo finale; l'utente li esegue in locale.

1. In **Appartamenti > Modifica** aggiungi 3 foto, cambia l'ordine e spunta i servizi: confronta con `docs/mockup-admin/appartamento.html`.
2. Apri la pagina pubblica dell'appartamento da telefono e da computer: galleria, servizi, copertina.
3. Prova a eliminare dalla libreria una foto usata: deve essere bloccata.
4. Controlla un appartamento senza foto: deve restare il segnaposto di oggi.
5. Disattiva un appartamento che ha prenotazioni future: deve comparire l'elenco di cosa viene toccato e una casella di conferma.

## Stop condition

Fermati quando gallerie, servizi e dettagli approvati sono amministrabili, visibili e testati. Fermati prima di iniziare se l'utente non ha risposto alle domande [titolare]. **Non** creare pagine o sezioni amministrabili (prompt 21) e **non** toccare le regole di prenotazione come il minimo di persone (prompt 23).
