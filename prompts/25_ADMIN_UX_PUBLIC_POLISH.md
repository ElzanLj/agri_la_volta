# Prompt 25 — Usabilità dell'admin e rifiniture pubbliche

Prerequisito: prompt 24 completato.

Contesto: l'admin ha una navigazione piatta (`templates/admin/layout.php`) che ora supera le 15 voci; il titolare non vede cosa manca per pubblicare; dalla pagina appartamento il pulsante porta al modulo generico; le tabelle admin non sono pensate per il telefono. Niente JavaScript, niente nuove dipendenze.

Riferimento visivo (non vincolante): tutta `docs/mockup-admin/`, in particolare `index.html` e la navigazione.

## Obiettivo

Rendere l'admin comprensibile e usabile anche da telefono, e chiudere le rifiniture pubbliche approvate, senza nuove funzioni oltre a quelle elencate.

## Prima di modificare

- Vincoli: nessun nuovo framework, dipendenza Composer di produzione, JavaScript pubblico, React, Firebase o WordPress; nessun refactor fuori scope; preserva ciò che funziona; codice rimosso solo se dimostrato inutile. Rispetta `docs/GUARDRAIL_FASI.md` (ramo Git per fase, prove obbligatorie, contenuti come dati, file vietati, nessun segreto).
- Verifica lo stato del prompt 24 e la decisione sul menu (prompt 16, D9); leggi in `docs/REVIEW_PRE_ROADMAP.md` le schede B6, B8, B23, A3, I3 e le sezioni G (admin) e J (cliente, SEO, prestazioni, accessibilità).
- Leggi `templates/admin/layout.php`, `dashboard.php`, `PricingConfigService::coverageGaps`, `RequestFlowController`, `templates/public/apartment.php`, `request/search.php`, `request/apartments.php`, `templates/error.php`, `app/Site/Seo.php`.
- Leggi `docs/CAMPI_CONTENUTI.md` (approvato nel prompt 16): ogni campo toccato da questa fase segue la sua riga (obbligatorietà, limiti, comportamento se vuoto, segnalazione). Se un campo nuovo non ha una riga, aggiungila e falla confermare.
- `git status` e diff locali.

## Domande per l'utente (all'inizio della fase)

Prima di modificare qualsiasi file, apri `docs/RISPOSTE_UTENTE.md`: se l'utente ha già segnato le risposte per questo prompt, mostrale e chiedi solo conferma. Altrimenti presenta le domande di questa sezione **così come sono scritte** (risposte possibili e consiglio) e **fermati finché non risponde**.

Le domande sono di due tipi, indicati nel titolo:

- **[titolare]**: decisioni sul business (prezzi, regole, testi, comunicazioni agli ospiti, dati legali, servizi esterni). Non applicare mai una risposta ✅ al posto dell'utente: serve la sua risposta scritta in chat ("consigliate" vale, se detto esplicitamente per questa fase). Senza risposta la fase non inizia.
- **[tecnica]**: scelte di implementazione. Se l'utente risponde "consigliate" o non ha preferenze, vale la risposta ✅; elencale comunque nel riepilogo finale.

Registra ogni scelta in `docs/DECISIONS.md`. In `docs/RISPOSTE_UTENTE.md` (casella con `x`) scrivi **solo** ciò che l'utente ha detto in chat, mai risposte tue. Le voci rinviate o rifiutate vanno in `docs/TODO.md` con il motivo. Non implementare nulla che non sia stato approvato. Se una voce approvata supera lo scope di questa fase, proponi di spostarla e fermati.

### D1 — Admin da telefono [tecnica]

| Risposta | Pro / contro |
|---|---|
| A. ✅ Sotto i 600 px le tabelle diventano schede (una riga = una scheda) | Leggibile davvero sul telefono; solo CSS |
| B. Tabelle con scorrimento orizzontale | Poco lavoro; scomodo da usare |
| C. Niente | — |

**Consiglio: A.**

### D2 — Appartamento scelto dalla sua pagina [titolare]

| Risposta | Pro / contro |
|---|---|
| A. ✅ Nel modulo è evidenziato e mostrato per primo; gli altri restano visibili | Il visitatore trova subito ciò che cercava e vede le alternative |
| B. Solo evidenziato | Meno cambiamenti |
| C. Niente | — |

**Consiglio: A.** Lo slug è solo un suggerimento: disponibilità e prezzo restano calcolati dal server.

### D3 — Calendario "libero/occupato" pubblico [titolare]

Mostrare a chiunque quando la struttura è vuota ha un rischio di sicurezza fisica se il titolare non vive sul posto, va tenuto aggiornato e può scoraggiare chi scriverebbe lo stesso.

| Risposta | Pro / contro |
|---|---|
| A. ✅ No: bastano le "date alternative" del prompt 23 e l'invito a scrivere | Nessun dato di occupazione pubblico; nessun calendario da mantenere |
| B. Sì, per appartamento, solo i prossimi 3 mesi, senza nomi, solo appartamenti con richieste online | Meno richieste inutili; mostra i periodi vuoti |
| C. Solo "prossime date libere" in testo | Più leggero; meno informativo |

**Consiglio: A.** Se scegli B: solo HTML generato dal server, richieste in attesa mai mostrate, blocchi e prenotazioni entrambi "occupato".

### D4 — Altre proposte (sì / no) [titolare]

| Proposta | Risposte | Consiglio e perché |
|---|---|---|
| Checklist "pronto per la pubblicazione" in dashboard (date senza tariffa e **listino scoperto nei prossimi 12 mesi**, appartamenti senza foto o testi, impostazioni vuote, privacy in bozza, traduzioni EN mancanti o da aggiornare, sezioni "[DA VERIFICARE]", foto con credito "LEGACY", richieste in attesa da troppo o scadute, avviso globale attivo da più di 60 giorni), con link dove si risolve; le voci di sistema (email, backup, aggiornamenti, coerenza) le aggiungono i prompt 26 e 27 | Sì / No | ✅ **Sì**: il titolare vede da solo cosa manca |
| Email dell'ospite ben visibile nel riepilogo ("Ti risponderemo a: …") con suggerimento sui domini mal digitati (gmial, hotmial, yahho…) | Sì / No | ✅ **Sì**: un errore di battitura significa un cliente mai ricontattato |
| Frase sui tempi di risposta scritta dal titolare nelle Impostazioni (es. "Rispondiamo di solito entro 24 ore"), mostrata nella pagina "ricevuta" e nell'email di ricevuta | Sì / No | ✅ **Sì**: l'ospite sa cosa aspettarsi; da promettere solo se vero |
| Pulsanti distruttivi distanti e di colore diverso in tutta l'admin; errori scritti come istruzioni ("Il server di posta ha rifiutato la password: controllala in Sistema › Email"); esempi sotto i campi; link "Vedi sul sito" dopo il salvataggio | Sì / No | ✅ **Sì**: meno errori umani, nessuna logica nuova |
| Etichetta "inglese da aggiornare" quando il testo italiano è stato modificato dopo quello inglese | Sì / No | ✅ **Sì**: le traduzioni non restano indietro in silenzio |
| Tabella prezzi per stagione nella pagina appartamento, generata dal listino | Sì / No | ✅ **Sì**: prezzi visibili prima di compilare il modulo |
| Pagina 404 con link ad appartamenti, richiesta e contatti | Sì / No | ✅ **Sì**: costo minimo |
| Versione del sito nel piè di pagina dell'admin | Sì / No | ✅ **Sì**: utile per ogni diagnosi |
| `lastmod` nella sitemap | Sì / No | ✅ **Sì**: dalle date di modifica già nel DB |
| Icone per la schermata del telefono (apple-touch-icon, manifest) | Sì / Rinvia | ✅ **Rinvia**: serve un logo verificato |
| Un po' di JavaScript facoltativo per aggiornare la data di partenza | Sì / No | ✅ **No**: il sito funziona bene senza, e "zero JS" semplifica sicurezza e test |

### D5 — Ripristinare un testo precedente [tecnica]

| Risposta | Pro / contro |
|---|---|
| A. ✅ Nello Storico, per i campi di testo (pagine, sezioni, appartamenti, impostazioni): pulsante "Ripristina questo valore", con conferma | Chi cancella un paragrafo per sbaglio lo recupera da solo; i valori vecchi sono già nell'audit log |
| B. Solo mostrare il valore vecchio, da copiare a mano | Meno codice; più scomodo |
| C. Niente | — |

**Consiglio: A.** Mai per password, prenotazioni o dati personali; il ripristino è a sua volta registrato.

## Implementa

- **Indicatori negli elenchi** (Pagine, Appartamenti, Foto, Servizi) e **checklist** letti dalle colonne "Segnalato" di `docs/CAMPI_CONTENUTI.md`, non da regole scritte a parte.
- Per ogni campo: limite scritto sotto il campo, frase "se lo lasci vuoto…" per i facoltativi, errori accanto al campo con i valori conservati, comportamento sul sito come da `docs/CAMPI_CONTENUTI.md`.
- Navigazione admin raggruppata secondo il prompt 16 (solo HTML/CSS), voce corrente con `aria-current`.
- Le voci approvate. La checklist è un **registro estendibile** di controlli (ognuno con titolo, stato, link per risolvere); nessun segreto nell'HTML (mostra solo "configurato / non configurato"). Il calcolo non rallenta la dashboard: risultati semplici e query cumulative.
- **Cliente**: `autocomplete` corretti (`given-name`, `family-name`, `email`, `tel`) e `inputmode` su tutti i campi pubblici; sotto la data di partenza il testo "partenza = giorno in cui lasci l'appartamento"; messaggi 429 e "troppo veloce" che spiegano cosa fare **senza far perdere i dati** compilati; token scaduto anche al passo del riepilogo con i dati conservati.
- **Pagina "ricevuta"**: cosa succede adesso, entro quando risponderete (frase del titolare, se presente), cosa fare se non arriva nulla (controllare lo spam, scrivere su WhatsApp).
- **Traduzioni**: la data di ultima modifica per lingua serve a segnalare "inglese da aggiornare".
- **Versione**: file `VERSION` nella radice, mostrato nel piè di pagina dell'admin; `lastmod` della sitemap dalle date di modifica.
- **Accessibilità dell'admin**: `ContrastTest` esteso al CSS dell'admin; focus visibile e target di almeno 44 px sulle parti nuove.
- Controllo di resa delle parti nuove (sezioni, gallerie, servizi, tabelle, calendario) a 320 px e su desktop: correggi solo problemi evidenti di layout, contrasto e focus.

## Test obbligatori

- **contratto dei campi**: per ogni campo di questa fase i "Test minimi" di `docs/CAMPI_CONTENUTI.md` (vuoto, solo spazi, troppo lungo, non valido, con HTML, resa IT/EN, Storico) e i controlli tra campi che lo riguardano;
- navigazione: ogni voce raggiungibile, `aria-current` corretto, nessuna rotta nuova senza guardie;
- checklist: ogni condizione accesa e spenta con dati di test; nessun valore segreto nell'HTML;
- slug inesistente, inattivo o non prenotabile online ignorato senza errori; prezzo e disponibilità invariati;
- calendario pubblico (solo se approvato): nessun nome o dato personale, blocchi e prenotazioni mostrati come "occupato", richieste in attesa **non** mostrate;
- **email dell'ospite**: dominio mal digitato → suggerimento; indirizzo corretto → nessun suggerimento; il suggerimento non blocca l'invio;
- **pulsanti distruttivi**: nel markup non sono adiacenti a quello principale e hanno classe e colore diversi (test sulla struttura dell'HTML);
- **errori come istruzioni**: ogni categoria d'errore del trasporto email e dell'upload ha un messaggio-istruzione (test che elenca le categorie e fallisce se una manca);
- **dati conservati**: 429, token scaduto e validazione fallita non svuotano il modulo;
- **traduzioni**: testo italiano modificato dopo quello inglese → "inglese da aggiornare" nell'elenco e nella checklist;
- 404: link utili presenti, nessun dato dell'URL riflesso senza escape; `VERSION` mostrata solo in admin;
- `ContrastTest` (esteso all'admin) e controlli di accessibilità PASS anche sulle parti nuove; suite completa PASS.

Aggiorna `MANUAL_CHECKLIST` (prove manuali per admin, foto e telefono), `TEST_REPORT`, `TODO`, `SESSION_STATE`.

## Prova tu (5–10 minuti, a fine fase)

L'agente elenca questi passi nel riepilogo finale; l'utente li esegue in locale.

1. Usa l'admin **dal telefono** per 5 minuti: richieste, una conferma, una foto. Le tabelle devono diventare schede.
2. Controlla la checklist in **Home** (`docs/mockup-admin/index.html`): ogni voce rossa deve portare dove si risolve.
3. Dalla pagina di un appartamento premi "Richiedi disponibilità": l'appartamento deve comparire per primo.
4. Apri un indirizzo inesistente del sito: la pagina 404 deve offrire link utili.
5. Nel modulo pubblico scrivi un'email con `gmial.com`: nel riepilogo deve comparire il suggerimento "Intendevi gmail.com?".
6. Provoca un errore (es. carica una foto troppo grande): il messaggio deve dirti cosa fare, non solo che c'è un errore.
7. Modifica un testo italiano senza toccare quello inglese: nell'elenco deve comparire "inglese da aggiornare".

## Stop condition

Fermati quando navigazione e voci approvate sono implementate e testate. Fermati prima di iniziare se l'utente non ha risposto alle domande [titolare]. **Non** implementare gli strumenti di sistema (configurazione email, manutenzione, stato del sistema: prompt 26; aggiornamenti, backup, conservazione dati: prompt 27) né la review di sicurezza complessiva (prompt 28).
