# Prompt 22b — Identità visiva dell'area di amministrazione

Prerequisito: prompt 18 (impostazioni), 19 (libreria foto), 20 (foto e servizi degli appartamenti), 21 (pagine con sezioni) e 22 (testi nel database) completati. Posizione: **subito dopo il 22 e prima del 23**, così le schermate dei prompt 23–27 nascono già con il nuovo stile.

Contesto: oggi l'admin non ha uno stile suo. `templates/admin/layout.php` carica lo stesso `css/site.css` del sito pubblico, e le uniche regole specifiche sono `.admin-nav`. Il risultato è un'amministrazione che sembra una pagina del sito, con la stessa palette e senza componenti pensati per un lavoro quotidiano (tabelle, stati, avvisi, moduli lunghi). Nessun prompt della roadmap ridisegna l'aspetto dell'admin: il 25 si occupa di struttura e usabilità (menu raggruppato, checklist, tabelle a schede su telefono) e il 28 di sicurezza e regressione. Questa fase si occupa **solo dell'aspetto** dell'admin. Il sito pubblico non cambia: la sua identità visiva è rinviata a dopo l'arrivo di foto e marchio verificati (il vecchio 22b, "identità visiva del sito pubblico", diventa un prompt successivo).

Riferimento visivo: **`docs/mockup-admin-grafica/`** contiene già le bozze grafiche approvate dall'utente (13 pagine HTML statiche, dati inventati, vedi il suo `LEGGIMI.md`). Per la **struttura** delle schermate resta `docs/mockup-admin/`; per lo **stile** (palette, gerarchia, componenti) valgono le bozze di `docs/mockup-admin-grafica/`, non `mockup.css` né `site.css`, che coincidono con il sito pubblico.

## Obiettivo

Dare all'admin uno stile proprio, coerente, leggibile e adatto a un lavoro quotidiano da computer e da telefono, **riproducendo le bozze di `docs/mockup-admin-grafica/`**, con un insieme di componenti riusabili che i prompt 23–27 useranno senza inventare altro. Senza JavaScript, senza dipendenze, senza toccare logica, database o indirizzi.

## Prima di modificare

- Rispetta `docs/GUARDRAIL_FASI.md` e `AGENTS.md`: nessun deploy, nessun servizio esterno, nessun segreto, nessuna nuova dipendenza.
- Verifica lo stato dei prompt 18–22 in `docs/SESSION_STATE.md`. Leggi in `docs/DECISIONS.md` le decisioni su CSS e JavaScript semplici, contrasto, cache degli asset e menu; leggi `docs/CAMPI_CONTENUTI.md` (testi sotto i campi, errori accanto al campo), `docs/MANUAL_CHECKLIST.md` e le sezioni dei prompt 24 e 25 che toccano l'admin, per non sovrapporti.
- Leggi `templates/admin/layout.php`, `login.php`, `dashboard.php`, i template in `templates/admin/` e `public/assets/css/site.css` (in particolare le regole `.admin-*`), il test del contrasto e `ArchitectureGuardTest`.
- `git status` e diff locali; ramo di fase `fase-22b-admin-visual` da `main`.

## Domande per l'utente (all'inizio della fase)

Prima di modificare qualsiasi file, apri `docs/RISPOSTE_UTENTE.md`: se l'utente ha già segnato le risposte per questo prompt, mostrale e chiedi solo conferma. Altrimenti presenta le domande di questa sezione **così come sono scritte** (risposte possibili e consiglio) e **fermati finché non risponde**.

Le domande sono di due tipi, indicati nel titolo:

- **[titolare]**: decisioni sul business. Non applicare mai una risposta ✅ al posto dell'utente: serve la sua risposta scritta in chat ("consigliate" vale, se detto esplicitamente per questa fase). Senza risposta la fase non inizia.
- **[tecnica]**: scelte di implementazione. Se l'utente risponde "consigliate" o non ha preferenze, vale la risposta ✅; elencale comunque nel riepilogo finale.

Registra ogni scelta in `docs/DECISIONS.md`. In `docs/RISPOSTE_UTENTE.md` (casella con `x`) scrivi **solo** ciò che l'utente ha detto in chat, mai risposte tue. Le voci rinviate o rifiutate vanno in `docs/TODO.md` con il motivo. Non implementare nulla che non sia stato approvato.

### D1 — Direzione dello stile [titolare]

| Risposta | Pro / contro |
|---|---|
| A. Tenere l'aspetto attuale (condiviso col sito) e rifinire | Pochissimo lavoro; l'admin resta una pagina del sito, senza componenti da lavoro |
| B. ✅ Stile dedicato, come nelle bozze di `docs/mockup-admin-grafica/`: sfondo chiaro, verde bosco per menu e intestazioni, **ocra scuro per l'azione principale di ogni pagina**, colori di stato (nuova, in attesa, confermata, rifiutata, avviso, errore) ben distinti, azioni distruttive riconoscibili | Più chiaro per chi lavora ogni giorno; richiede un foglio di stile in più |
| C. Stile molto personalizzato, con illustrazioni e identità forte | Più impatto; più lavoro, più rischio, poco utile in un'area di lavoro |

**Consiglio: B.**

### D2 — Disposizione del menu [tecnica]

Qui si decide solo **come appare** il menu; il raggruppamento in Gestione, Listino, Sito e Sistema resta del prompt 25.

| Risposta | Pro / contro |
|---|---|
| A. ✅ Barra laterale a sinistra su schermi larghi; su telefono diventa un blocco in alto che va a capo (solo HTML e CSS) | Spazio per più di 15 voci senza ammassarle; nessun JavaScript |
| B. Barra orizzontale in alto che va a capo | Più semplice; con molte voci occupa molto spazio |
| C. Lasciare com'è | Nessun lavoro; il problema delle voci troppo numerose resta |

**Consiglio: A.**

### D3 — Tabelle [tecnica]

| Risposta | Pro / contro |
|---|---|
| A. ✅ Righe comode e leggibili; la modalità a schede sotto i 600 px è **predisposta nello stile** e adottata pagina per pagina dal prompt 25 | Nessuna sovrapposizione con il 25; il telefono migliora in due tempi |
| B. Tabelle compatte, con scorrimento orizzontale su telefono | Più semplice; scomodo da telefono |

**Consiglio: A.**

### D4 — Caratteri e icone [tecnica]

| Risposta | Pro / contro |
|---|---|
| A. ✅ Caratteri di sistema (serif per i titoli, sans per il testo, come nelle bozze); nessuna libreria di icone: solo testo e pochi simboli SVG scritti nel progetto (al massimo dieci) | Nessun file in più, nessuna dipendenza, massima velocità |
| B. Libreria di icone o font esterno | **Vietata**: nuova dipendenza e richieste esterne |

**Consiglio: A.**

### D5 — Tema scuro [tecnica]

| Risposta | Pro / contro |
|---|---|
| A. ✅ No: un solo tema | Meno stili e meno prove di contrasto |
| B. Sì, seguendo l'impostazione del dispositivo | Raddoppia colori e prove |

**Consiglio: A.**

### D6 — Bozze prima del codice [tecnica]

| Risposta | Pro / contro |
|---|---|
| A. ✅ Le bozze **già fornite** in `docs/mockup-admin-grafica/` sono il punto di partenza: l'agente le legge, aggiunge solo le schermate mancanti (elenco prenotazioni, modifica prenotazione, accesso, calendario) con lo stesso stile e aspetta l'approvazione di queste ultime prima di toccare il codice | Il grosso è già approvato; un passaggio in più solo per ciò che manca |
| B. Rifare tutte le bozze da zero | Più lavoro; rischia di scostarsi da ciò che l'utente ha già visto |

**Consiglio: A.**

## Attività

1. **Inventario**: elenca le schermate admin e i componenti oggi presenti (menu, intestazioni, pulsanti, moduli, tabelle, filtri, impaginazione, avvisi, stati, schede della dashboard, login) e quali regole di `site.css` usano. Se l'ambiente lo permette, salva immagini "prima" delle schermate principali a 360 e 1280 px; altrimenti segna **NOT RUN** con il motivo.
2. **Bozze** (D6 = A): apri e confronta con l'inventario le 13 pagine di `docs/mockup-admin-grafica/` (cruscotto, richieste, dettaglio richiesta, impostazioni, appartamento, pagine, servizi e foto, listino, arrivi e ricerca, email, stato del sistema, telefono, componenti). Segna quali schermate reali non hanno una bozza (elenco prenotazioni, modifica prenotazione, calendario, accesso, ogni altra) e **crea solo quelle**, nello stesso stile, in italiano, a 360 e a 1280 px, con tutti gli stati (vuoto, errore, avviso, successo). Non modificare le bozze esistenti. **Fermati e attendi l'approvazione dell'utente** sulle bozze nuove prima di proseguire. Le bozze sono un riferimento di **aspetto**: se una voce non corrisponde a una funzione che esiste o che il titolare ha approvato (per esempio iCal, statistiche, anonimizzazione dall'admin, email di pre-arrivo), non la implementare e segnalalo nel riepilogo.
3. **Foglio di stile dell'admin**: crea `public/assets/css/admin.css`, caricato **solo** da `templates/admin/layout.php` e `login.php`. Dal foglio pubblico togli soltanto le regole `.admin-*` dopo aver dimostrato che il sito pubblico non le usa. Il sito pubblico deve restare identico.
4. **Variabili di design dell'admin**: colori, stati, scala dei caratteri, spaziature, raggi, ombre e dimensioni come variabili; nessun colore scritto direttamente nel testo. Estendi il test del contrasto a `admin.css` (testo 4,5:1, elementi non testuali 3:1, incluse tutte le coppie degli stati).
5. **Componenti riusabili**, documentati con un esempio ciascuno in `docs/COMMANDS.md` o nel documento che i prompt successivi leggono: struttura della pagina, menu (stile adatto sia all'elenco piatto di oggi sia al raggruppamento del 25), intestazione di pagina, pulsanti (principale, secondario, distruttivo con aspetto chiaramente diverso), moduli (etichetta, aiuto, **riga «Esempio:» sotto il campo**, limite di caratteri, errore accanto al campo), **schede (tab) come link con voce corrente evidenziata**, **riga «A cosa serve» in cima a ogni scheda**, tabelle e modalità a schede su telefono, etichette di stato, avvisi, impaginazione, barra dei filtri, riquadri della dashboard, stati vuoti, liste di dettaglio. In questa fase si crea **solo lo stile** di schede, riga «A cosa serve» e riga «Esempio»; li adotta pagina per pagina il prompt 25 (vedi l’attività «Schede, A cosa serve ed Esempio» da aggiungere al prompt 25).
6. **Applicazione ai template esistenti**: cambiano classi e struttura HTML, non la logica. L'output resta sempre con `e()`, nessuna rotta nuova, nessuna azione cambia comportamento.
7. **Accessibilità e uso da telefono**: focus visibile, bersagli di almeno 44 px, salto al contenuto, voce corrente con `aria-current` dove già presente, zoom al 200% senza scorrimento orizzontale, rispetto delle animazioni ridotte, nessun JavaScript.
8. **Prestazioni**: dichiara un budget per `admin.css` (peso e numero di regole) e verificalo; nessuna risorsa esterna.
9. **Guardiani**: le pagine admin caricano `admin.css` e quelle pubbliche no; nessuna richiesta a domini esterni dall'admin; nessun `<script>` nei template admin; nessun colore letterale nel testo di `admin.css`; i test esistenti restano verdi senza essere indeboliti.
10. **Regola per le fasi successive**: in `docs/DECISIONS.md` e nelle "Note per il prossimo agente" di `docs/SESSION_STATE.md` scrivi che, da ora, ogni nuova schermata admin (prompt 23–27) usa i componenti di `admin.css` e non introduce colori o stili propri.
11. **Documentazione**: `DECISIONS`, `MANUAL_CHECKLIST` (prove visive su telefono reale), `TEST_REPORT`, `SESSION_STATE`. Non aggiornare ancora README e manuale del titolare (prompt 29).

## Divieti

Nessun JavaScript, nessuna libreria di icone o di stile, nessun font o risorsa da indirizzi esterni, nessuna nuova dipendenza. Non modificare logica, database, migrazioni, indirizzi delle pagine, testi dei campi né l'aspetto del sito pubblico. Non anticipare il raggruppamento del menu, la divisione delle pagine in schede, le righe «A cosa serve» e «Esempio» nei moduli esistenti e l'adozione delle schede su telefono (prompt 25): qui si crea solo lo stile. Non indebolire un test per farlo passare. Nessun deploy.

## Verifica

Suite completa in ordine normale e casuale, `php -l`, test del contrasto esteso e controllo che l'admin non richieda risorse esterne. Ogni esecuzione registrata con esito reale in `TEST_REPORT` (comando, data, numero di test, riga finale); FAIL e NOT RUN con motivo. Le prove visive e gli strumenti non disponibili (axe, Lighthouse, telefono reale) restano **NOT RUN**, mai PASS.

## Prova tu (5–10 minuti, a fine fase)

L'agente elenca questi passi nel riepilogo finale; l'utente li esegue in locale.

1. Apri cruscotto, richieste, prenotazioni, listino e impostazioni e confrontali con la bozza approvata: stessa gerarchia, stessi colori, nessun elemento tagliato.
2. Restringi la finestra a circa 360 px o apri l'admin dal telefono: menu raggiungibile, moduli leggibili, nessuno scorrimento orizzontale.
3. Naviga solo con la tastiera (Tab): ogni link e pulsante mostra un contorno ben visibile.
4. Nella finestra di un dettaglio controlla che il pulsante distruttivo (per esempio "Rifiuta") sia riconoscibile e distante da quello principale.
5. Apri il sito pubblico: deve avere lo stesso aspetto di prima.
6. Esegui la suite completa nel container due volte, la seconda con `--order-by=random`: devono passare entrambe.

## Stop condition

Fermati quando le bozze approvate sono realizzate, `admin.css` e i componenti sono documentati, i guardiani e i test sono verdi e ciò che non si è potuto provare è marcato NOT RUN. Fermati prima di iniziare se l'utente non ha risposto alle domande [titolare]. Fermati dopo le bozze **nuove** (attività 2) finché l'utente non le approva; le 13 bozze già in `docs/mockup-admin-grafica/` sono approvate e non vanno rifatte. **Non** cambiare l'aspetto del sito pubblico, **non** aggiungere funzioni, **non** raggruppare il menu, **non** dividere le pagine in schede né adottare le schede su telefono (prompt 25), **non** aggiornare README e manuale del titolare (prompt 29), **non** pubblicare.
