# Prompt 23 — Regole di prenotazione e strumenti per il listino

Prerequisito: prompt 22 completato.

Contesto: regole e prezzi sono spiegati in `docs/REGOLE_E_PREZZI.md`. Controlli delle richieste in un unico punto (`BookingService::prepareRequest`); calcolo puro in `PriceCalculator`; listino in `seasonal_rates` e `pricing_rules` (`PricingConfigService`); conferma sotto lock sulla riga dell'appartamento. Questa è la **parte critica** del progetto: ogni modifica alla disponibilità richiede test di concorrenza.

Riferimento visivo (non vincolante): `docs/mockup-admin/appartamento.html`, `listino.html`, `simulatore.html`, `prenotazione-modifica.html`, `richiesta.html`, `blocchi.html`.

## Obiettivo

1. Implementare la regola confermata **minimo 2 persone per appartamento**, attivabile o disattivabile dall'admin.
2. Implementare le migliorie a regole, prezzi e strumenti del listino approvate dall'utente.

## Prima di modificare

- Vincoli: nessun nuovo framework, dipendenza Composer di produzione, JavaScript pubblico, React, Firebase o WordPress; nessun refactor fuori scope; preserva ciò che funziona; codice rimosso solo se dimostrato inutile. Rispetta `docs/GUARDRAIL_FASI.md` (ramo Git per fase, prove obbligatorie, contenuti come dati, file vietati, nessun segreto).
- Verifica lo stato dei prompt 15 e 22; leggi `docs/REGOLE_E_PREZZI.md`, `docs/PROPOSTE_PRENOTAZIONI_LISTINO.md`, `DECISIONS` (Fasi 2A, 2B, 3, 5) e in `docs/REVIEW_PRE_ROADMAP.md` le schede A4, A5, B7, B10, B11, B24 e i test D10, D11, D12.
- Leggi `BookingService`, `PriceCalculator`, `PricingConfigService`, `AvailabilityService`, `RequestFlowController`, i template admin di prenotazioni, blocchi e listino, `ConcurrencyTest`.
- Leggi `docs/CAMPI_CONTENUTI.md` (approvato nel prompt 16): ogni campo toccato da questa fase segue la sua riga (obbligatorietà, limiti, comportamento se vuoto, segnalazione). Se un campo nuovo non ha una riga, aggiungila e falla confermare.
- `git status` e diff locali.

## Requisito confermato: minimo di persone

Fornito dall'utente il 2026-10-07: **negli appartamenti devono esserci almeno 2 persone**, e deve essere una **regola che si può attivare o disattivare**. Quando è disattivata vale il comportamento attuale (almeno 1 adulto). Si implementa in ogni caso; le domande D1–D5 (con D3b) ne definiscono i dettagli.

## Domande per l'utente (all'inizio della fase)

Prima di modificare qualsiasi file, apri `docs/RISPOSTE_UTENTE.md`: se l'utente ha già segnato le risposte per questo prompt, mostrale e chiedi solo conferma. Altrimenti presenta le domande di questa sezione **così come sono scritte** (risposte possibili e consiglio) e **fermati finché non risponde**.

Le domande sono di due tipi, indicati nel titolo:

- **[titolare]**: decisioni sul business (prezzi, regole, testi, comunicazioni agli ospiti, dati legali, servizi esterni). Non applicare mai una risposta ✅ al posto dell'utente: serve la sua risposta scritta in chat ("consigliate" vale, se detto esplicitamente per questa fase). Senza risposta la fase non inizia.
- **[tecnica]**: scelte di implementazione. Se l'utente risponde "consigliate" o non ha preferenze, vale la risposta ✅; elencale comunque nel riepilogo finale.

Registra ogni scelta in `docs/DECISIONS.md`. In `docs/RISPOSTE_UTENTE.md` (casella con `x`) scrivi **solo** ciò che l'utente ha detto in chat, mai risposte tue. Le voci rinviate o rifiutate vanno in `docs/TODO.md` con il motivo. Non implementare nulla che non sia stato approvato. Se una voce approvata supera lo scope di questa fase, proponi di spostarla e fermati.

### D1 — Chi conta nelle 2 persone [titolare]

| Risposta | Pro / contro |
|---|---|
| A. ✅ Persone totali (adulti + bambini), con almeno 1 adulto come oggi | Coerente con la capienza massima, che conta già così |
| B. Almeno 2 adulti | Un genitore solo con un figlio non può richiedere; raramente è l'intenzione |

**Consiglio: A.**

### D2 — Se qualcuno chiede per 1 persona [titolare]

| Risposta | Pro / contro |
|---|---|
| A. ✅ Non può inviare la richiesta per quell'appartamento: vede il motivo e l'invito a contattarvi | È letteralmente la regola data |
| B. Può inviarla ma paga come 2 persone | Diffuso nel settore; è una regola di prezzo diversa, da confermare col titolare |

**Consiglio: A.** Chiedi comunque: "minimo 2 persone" a volte significa "si paga almeno per 2".

### D3 — Dove si attiva la regola [tecnica]

| Risposta | Pro / contro |
|---|---|
| A. ✅ Per appartamento: casella "Regola attiva" + numero minimo | Stesso modello di capienza, bambini e animali; si può spegnere su un solo appartamento (es. uno che accetta single) |
| B. Un unico interruttore e un unico numero per tutto il sito, nelle Impostazioni | Un solo punto da gestire; nessuna eccezione per appartamento |
| C. Interruttore e numero globali, con eccezioni per appartamento | Massima flessibilità; due livelli da capire e da testare |

**Consiglio: A.** Se si spegne la casella, il numero resta salvato e torna valido quando la si riaccende.

### D3b — Stato iniziale della regola [titolare]

| Risposta | Pro / contro |
|---|---|
| A. ✅ Attiva con minimo 2 su tutti e sei gli appartamenti | Rispecchia la regola fornita; impostabile dalla migrazione documentandone la fonte |
| B. Disattivata, la accende il titolare | Nessun cambiamento finché non si decide; rischio di dimenticarla |

**Consiglio: A.**

### D4 — Prenotazioni manuali dell'admin [titolare]

| Risposta | Pro / contro |
|---|---|
| A. ✅ Avviso, ma salvataggio permesso | Il gestore può fare eccezioni |
| B. Blocco anche per l'admin | Nessuna eccezione possibile |

**Consiglio: A.**

### D5 — I neonati contano come persone (per minimo e capienza) [titolare]

| Risposta | Pro / contro |
|---|---|
| A. ✅ Sì, per ora tutti contano | Nessun cambiamento al modulo |
| B. No, fascia 0–2 anni esclusa | Più realistico (culla); serve chiedere l'età nel modulo e aggiornare capienza e prezzi |

**Consiglio: A** finché il titolare non chiede diversamente. Minimo e capienza devono usare la stessa regola.

### D6 — Soggiorno minimo a cavallo di due stagioni [titolare]

Oggi vale il minimo del periodo della notte di arrivo: chi arriva il giorno prima dell'alta stagione evita il minimo dell'alta.

| Risposta | Pro / contro |
|---|---|
| A. Lasciare com'è | Nessun lavoro; minimo aggirabile |
| B. ✅ Minimo più alto tra i periodi attraversati | Regola chiara, protegge le settimane di punta; cambia una decisione del 2026-10-05 |
| C. Minimo dell'arrivo + periodi "forti" che impongono il minimo a chi li attraversa | Più flessibile; un campo in più nel listino |

**Consiglio: B.**

### D7 — Preavviso minimo per l'arrivo [titolare]

Oggi si può chiedere l'arrivo per il giorno stesso, anche alle 23.

| Risposta | Pro / contro |
|---|---|
| A. Nessuno | Massima flessibilità; richieste impossibili da gestire |
| B. ✅ 1 giorno | Dà il tempo di rispondere e preparare |
| C. 2 giorni | Più margine; perde qualche ultimo minuto |

**Consiglio: B**, configurabile.

### D8 — Date alternative quando non c'è posto [titolare]

| Risposta | Pro / contro |
|---|---|
| A. No | Nessun lavoro; il visitatore vede solo "nessun appartamento disponibile" |
| B. ✅ Stesso numero di notti, arrivo spostato fino a ±3 giorni | Recupera molte richieste; calcolo lato server, poche query |
| C. Fino a ±7 giorni | Più alternative; più calcoli e risultati meno pertinenti |

**Consiglio: B.**

### D9 — Confronto prezzi alla conferma [titolare]

La conferma usa il prezzo preventivato al momento della richiesta, anche se nel frattempo il listino è cambiato.

| Risposta | Pro / contro |
|---|---|
| A. Nessun avviso (come oggi) | Nessun lavoro; il gestore non lo sa |
| B. ✅ Avviso "preventivato X, listino attuale Y"; si conferma al prezzo preventivato | Trasparente, nessun cambio di regola |
| C. Avviso + possibilità di confermare al prezzo nuovo | Flessibile; il cliente riceve un prezzo diverso da quello visto |

**Consiglio: B.**

### D10 — Prezzo indicativo ("Da X a notte") [titolare]

| Risposta | Pro / contro |
|---|---|
| A. Campo libero come oggi | Nessun lavoro; può contraddire il listino |
| B. ✅ Avviso in admin se è più basso della tariffa minima attiva | Resta controllabile dal titolare |
| C. Calcolato automaticamente dal listino | Sempre coerente; il titolare non può arrotondare |

**Consiglio: B.**

### D11 — Condizioni di cancellazione e pagamento [titolare]

| Risposta | Pro / contro |
|---|---|
| A. ✅ Testo nelle impostazioni, mostrato nel riepilogo prima dell'invio e nell'email di conferma | Il cliente le conosce prima di impegnarsi; testo da verificare col consulente |
| B. No | Nessun lavoro |
| C. Anche coordinate bancarie (IBAN) | Comodo; `ScopeTest` vieta campi IBAN: va contro una scelta del progetto |

**Consiglio: A.** Le coordinate si comunicano a mano.

### D12 — Tassa di soggiorno [titolare]

| Risposta | Pro / contro |
|---|---|
| A. ✅ Solo una nota di testo ("esclusa, da pagare in struttura"), se il Comune la applica | Evita contestazioni, nessun calcolo |
| B. Niente | — |
| C. Calcolo automatico | Esenzioni ed età lo rendono fragile |

**Consiglio: A.**

### D13 — Altre proposte (sì / no) [titolare]

| Proposta | Risposte | Consiglio e perché |
|---|---|---|
| Modifica di una prenotazione confermata (date, ospiti, totale, note) con controllo di disponibilità sotto lock | Sì / No | ✅ **Sì**: oggi bisogna cancellare e ricreare, perdendo il legame con la richiesta |
| Simulatore prezzi in admin (date + ospiti → preventivo per ogni appartamento) | Sì / No | ✅ **Sì**: utile al telefono e per collaudare il listino; riusa `previewRequest` |
| Totale suggerito dal listino nelle prenotazioni manuali | Sì / No | ✅ **Sì**: evita importi scritti a mano non coerenti |
| Copia di periodi su altri appartamenti e di una stagione all'anno successivo | Sì / No | ✅ **Sì**: 6 appartamenti × 4–5 periodi ogni anno sono tanti |
| Blocco su tutti gli appartamenti in un'azione | Sì / No | ✅ **Sì**: chiusure stagionali con un clic |
| Messaggio per i gruppi quando nessun appartamento basta | Sì / No | ✅ **Sì**: costo minimo, evita che il gruppo se ne vada |
| Giorni di arrivo vincolati (es. agosto solo sabato) | Sì / No / Rinvia | ✅ **Rinvia**: solo se il titolare lavora a settimane fisse |
| Giorno di pulizia obbligatorio tra due soggiorni | Sì / No | ✅ **No**: tocca la disponibilità; solo se le pulizie non si fanno nel giorno di partenza |
| Supplementi scelti dal cliente (lettino, letto aggiunto) | Sì / Rinvia | ✅ **Rinvia**: mancano gli importi |
| Sconto per soggiorni lunghi | Sì / Rinvia | ✅ **Rinvia**: serve sapere se il titolare lo applica |
| Ripristino di una prenotazione cancellata per errore (solo se le date sono ancora libere, stessa transazione e lock della conferma) | Sì / No | ✅ **Sì**: oggi la cancellazione è irreversibile; si riusa la logica di conferma |
| Richieste in attesa con data di arrivo passata: etichetta "scaduta" e blocco della conferma | Sì / No | ✅ **Sì**: a novembre non si conferma per sbaglio una richiesta di agosto |

Se le voci approvate sono molte, proponi di dividerle in due sessioni (prima regole e prezzi, poi strumenti admin) mantenendo questo prompt.

## Implementa

- Per ogni campo: limite scritto sotto il campo, frase "se lo lasci vuoto…" per i facoltativi, errori accanto al campo con i valori conservati, comportamento sul sito come da `docs/CAMPI_CONTENUTI.md`.
- **Controlli tra campi**: minimo persone ≤ capienza massima; minimo obbligatorio se la regola è attiva (righe di `docs/CAMPI_CONTENUTI.md`).
- **Minimo persone**: migrazione con i campi decisi in D3 (interruttore + numero) e lo stato iniziale di D3b; controllo in `prepareRequest` accanto a capienza, bambini e animali, **solo se la regola è attiva** (vale a ogni passo e all'invio); motivo al passo 2 senza pulsante; testi IT/EN in `content/*.php`; casella e numero in admin con audit dei cambi; comportamento manuale secondo D4.
- Le voci approvate D6–D13, ognuna con il proprio test, riusando servizi esistenti.
- **Regole di prezzo che si sommano** (A4, B7, sempre attivo): al salvataggio di una regola, avviso (non blocco) se ne esiste un'altra attiva con lo stesso destinatario, la stessa base di calcolo e un ambito o un periodo sovrapposto ("Queste due regole si sommeranno"); il listino mostra l'elenco delle regole che si sommano e il simulatore mostra ogni riga.
- **Valori implausibili** (B24, sempre attivo): avviso sopra 1.000 € a notte o a 0 €, con conferma esplicita; nessun blocco.
- **Prenotazioni esistenti** (B10): abbassare la capienza o alzare il minimo persone non modifica le prenotazioni già confermate; il modulo lo dice e mostra quelle che non rispetterebbero più la nuova regola.
- **Doppio clic sulle azioni admin**: una seconda "Conferma" o "Cancella" dà il messaggio "già confermata/cancellata", mai un errore 500.
- **Blocco ottimistico** (helper del prompt 18) sui moduli di appartamento, tariffa e regola.
- Modifica prenotazione (se approvata): stessa transazione e lock della conferma, **ricontrollo della disponibilità escludendo la prenotazione stessa**, divieto di modificare prenotazioni cancellate, audit con valori vecchi e nuovi (mai testo libero personale), nessuna email automatica. Se la prenotazione nasce da una richiesta del sito, la richiesta resta com'era (storico di ciò che il cliente ha chiesto e visto); il dettaglio della prenotazione segnala che è stata modificata.
- Valori globali nuovi (preavviso D7, condizioni D11, nota tassa D12): colonne nuove in `site_settings` con una **nuova** migrazione (mai modificare quella del prompt 18), modificabili da Impostazioni.

## Test obbligatori

- **contratto dei campi**: per ogni campo di questa fase i "Test minimi" di `docs/CAMPI_CONTENUTI.md` (vuoto, solo spazi, troppo lungo, non valido, con HTML, resa IT/EN, Storico) e i controlli tra campi che lo riguardano;
- minimo persone **attiva**: 1 persona rifiutata (passo 2, URL del passo 3, invio), 2 accettate, regola D1, avviso manuale D4, IT/EN;
- minimo persone **disattivata**: 1 adulto accettato come oggi; il numero salvato resta e torna valido alla riattivazione; audit di attivazione e disattivazione;
- regole esistenti (capienza, bambini, animali, minimo notti) invariate dove non toccate;
- D6: soggiorno che attraversa un periodo con minimo più alto, nei due sensi;
- D7, D8: casi limite di date (oggi, domani, fine anno, nessuna alternativa);
- modifica prenotazione: sovrapposizione rifiutata, **concorrenza** con due modifiche e con una conferma in parallelo (`ConcurrencyTest`);
- copia listino: nessuna sovrapposizione creata, anni bisestili;
- simulatore: stesso risultato del flusso pubblico;
- **proprietà del calcolo prezzi** (test a proprietà con molti casi generati): totale = somma delle righe; un soggiorno diviso in due soggiorni consecutivi con le stesse persone ha la stessa tariffa base; il totale non diminuisce all'aumentare degli adulti oltre i gratuiti; mai un totale negativo; notti per periodo = notti del soggiorno;
- **casi di data** (D11): attorno alla mezzanotte di Roma (23:59/00:01, quando UTC è ancora il giorno prima), 29 febbraio, cambio d'ora di marzo e ottobre, 31 dicembre → 1 gennaio, 60 notti esatte, arrivo tra esattamente due anni, "oggi" e "domani" con preavviso;
- **regole che si sommano**: avviso presente, preventivo = somma documentata; valori implausibili: avviso con conferma;
- **richieste scadute** e **ripristino prenotazione** (se approvati): conferma bloccata; ripristino solo con date libere, **concorrenza** con una nuova conferma sulle stesse date, richiesta collegata riportata allo stato coerente;
- **connessione che cade a metà conferma o modifica** (la connessione viene interrotta da un secondo processo): nessuna prenotazione a metà, richiesta ancora in attesa;
- doppio clic: seconda azione → messaggio chiaro; blocco ottimistico: due schede, la seconda viene avvisata;
- verifica di coerenza del prompt 15 eseguita dopo ogni scenario di concorrenza: nessuna incoerenza;
- autorizzazione e CSRF su ogni nuova azione; prezzi mai letti dal browser; suite completa PASS.

Aggiorna `docs/REGOLE_E_PREZZI.md`, `MISSING_DATA`, `TEST_REPORT`, `DECISIONS`, `TODO`, `SESSION_STATE`.

## Prova tu (5–10 minuti, a fine fase)

L'agente elenca questi passi nel riepilogo finale; l'utente li esegue in locale.

1. Con la regola del minimo persone **attiva**, cerca dal sito un soggiorno per 1 adulto: l'appartamento compare con il motivo e senza pulsante.
2. Spegni la regola su un appartamento (`docs/mockup-admin/appartamento.html`): ora 1 adulto può inviare la richiesta. Riaccendila e verifica che il numero sia ancora 2.
3. Prova le voci approvate: simulatore (`simulatore.html`), modifica prenotazione (`prenotazione-modifica.html`), copia periodi (`listino.html`).
4. Prova a spostare una prenotazione su date già occupate: deve essere rifiutato.
5. Crea due regole "adulto in più" (una per tutti, una per un appartamento): deve comparire l'avviso che si sommano; controlla il totale nel simulatore.
6. Se approvato: cancella una prenotazione di prova e ripristinala; poi prova a ripristinarla dopo aver occupato le sue date con un'altra prenotazione: deve essere rifiutato.

## Stop condition

Fermati quando la regola del minimo di persone (attivabile) e le voci approvate sono implementati e testati, concorrenza inclusa. Fermati prima di iniziare se l'utente non ha risposto alle domande [titolare]. **Non** implementare email, pagina arrivi, ricerca, note o calendari iCal (prompt 24).
