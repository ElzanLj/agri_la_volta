# Prompt 21 — Pagine amministrabili

Prerequisito: prompt 19 completato (verifica anche lo stato del 19).

Contesto: le pagine pubbliche sono fisse (`app/Site/Routes.php`, `SiteController`); titoli, meta description e segnaposto stanno in `content/*.php`; Agriturismo e Dintorni mostrano "i testi saranno inseriti a cura del titolare"; Privacy e Cookie sono bozze marcate (`legal.draft`). Niente JavaScript pubblico né cookie per i visitatori.

Riferimento visivo (non vincolante): `docs/mockup-admin/pagine.html`, `pagina.html`.

## Obiettivo

Rendere modificabili dall'admin i contenuti delle pagine fisse con campi di pagina e sezioni semplici, **mantenendo l'aspetto attuale quando il database è vuoto**.

## Prima di modificare

- Vincoli: nessun nuovo framework, dipendenza Composer di produzione, JavaScript pubblico, React, Firebase o WordPress; nessun refactor fuori scope; preserva ciò che funziona; codice rimosso solo se dimostrato inutile. Rispetta `docs/GUARDRAIL_FASI.md` (ramo Git per fase, prove obbligatorie, contenuti come dati, file vietati, nessun segreto).
- Verifica lo stato dei prompt 19–20 e il design delle pagine (`docs/CMS_DESIGN.md`); leggi `docs/THREAT_MODEL.md` e in `docs/REVIEW_PRE_ROADMAP.md` le schede B14, D22, J8, J9.
- Leggi `SiteController`, `SitePage`, `View`, `Text`, i template di `templates/public/`, `layout.php`, `PublicSiteUnitTest` (chiavi IT = EN), `PublicSeoTest`.
- Leggi `docs/CAMPI_CONTENUTI.md` (approvato nel prompt 16): ogni campo toccato da questa fase segue la sua riga (obbligatorietà, limiti, comportamento se vuoto, segnalazione). Se un campo nuovo non ha una riga, aggiungila e falla confermare.
- `git status` e diff locali.

## Domande per l'utente (all'inizio della fase)

Prima di modificare qualsiasi file, apri `docs/RISPOSTE_UTENTE.md`: se l'utente ha già segnato le risposte per questo prompt, mostrale e chiedi solo conferma. Altrimenti presenta le domande di questa sezione **così come sono scritte** (risposte possibili e consiglio) e **fermati finché non risponde**.

Le domande sono di due tipi, indicati nel titolo:

- **[titolare]**: decisioni sul business (prezzi, regole, testi, comunicazioni agli ospiti, dati legali, servizi esterni). Non applicare mai una risposta ✅ al posto dell'utente: serve la sua risposta scritta in chat ("consigliate" vale, se detto esplicitamente per questa fase). Senza risposta la fase non inizia.
- **[tecnica]**: scelte di implementazione. Se l'utente risponde "consigliate" o non ha preferenze, vale la risposta ✅; elencale comunque nel riepilogo finale.

Registra ogni scelta in `docs/DECISIONS.md`. In `docs/RISPOSTE_UTENTE.md` (casella con `x`) scrivi **solo** ciò che l'utente ha detto in chat, mai risposte tue. Le voci rinviate o rifiutate vanno in `docs/TODO.md` con il motivo. Non implementare nulla che non sia stato approvato. Se una voce approvata supera lo scope di questa fase, proponi di spostarla e fermati.

### D1 — Foto per sezione [tecnica]

| Risposta | Pro / contro |
|---|---|
| A. ✅ Al massimo una foto per sezione | Impaginazione prevedibile, semplice da gestire |
| B. Una piccola galleria per sezione | Più ricco (es. "i castelli"); più complesso in admin e nella resa |

**Consiglio: A.** Per più foto si usano più sezioni.

### D2 — Link esterno in una sezione (es. sito delle terme o di un castello) [titolare]

| Risposta | Pro / contro |
|---|---|
| A. Nessun link | Più semplice |
| B. ✅ Un link facoltativo per sezione (testo + indirizzo `https://`) | Utile per Dintorni; validazione già nota |

**Consiglio: B.**

### D3 — "Offerta del momento" in home [titolare]

| Risposta | Pro / contro |
|---|---|
| A. ✅ Una sezione normale che il titolare mostra o nasconde | Nessun lavoro in più |
| B. Sezione con data di fine visibilità automatica | Non resta online un'offerta scaduta; un campo e una regola in più |
| C. Nessuna offerta | — |

**Consiglio: A** per partire. B se il titolare tende a dimenticare di toglierle.

### D4 — Galleria (più foto insieme) [tecnica]

Il vecchio sito aveva una galleria in home. D1 consiglia una foto per sezione.

| Risposta | Pro / contro |
|---|---|
| A. ✅ No per ora: più foto = più sezioni | Nessun tipo di blocco in più |
| B. Un secondo tipo di sezione "Galleria" (foto dalla libreria, ordinabili, senza slider) | Più vicino al vecchio sito; più codice, admin e test |

**Consiglio: A**, da riconsiderare quando ci saranno foto verificate a sufficienza.

## Implementa

- Per ogni campo: limite scritto sotto il campo, frase "se lo lasci vuoto…" per i facoltativi, errori accanto al campo con i valori conservati, comportamento sul sito come da `docs/CAMPI_CONTENUTI.md`.
- Migrazione: pagine fisse e sezioni come da design (campi per lingua in tabelle figlie, ordine, visibilità, foto facoltativa, link se approvato, hero per la home, flag bozza per privacy/cookie). Nessun testo inserito qui.
- Servizio con validazione (lunghezze, chiavi di pagina ammesse solo da `Routes::PATHS`) e audit.
- Admin "Pagine": elenco delle pagine fisse; modifica di titolo, intro e meta per lingua; sezioni con aggiungi, modifica, sposta su/giù, mostra/nascondi, elimina con conferma; foto dalla libreria.
- Resa pubblica: dati dal DB con **ritorno** ai testi attuali di `content/*.php` se vuoti; formattazione decisa nel prompt 16; regola EN del prompt 16; avviso bozza guidato dal flag; hero e `og:image` in home.
- La verifica "foto in uso" include sezioni e hero.
- Pagine e URL non si creano né si rinominano dall'admin.
- **Limite di sezioni** per pagina (30, decisione del prompt 16) con messaggio chiaro; blocco ottimistico (helper del prompt 18) su pagine e sezioni.
- **Sezioni nascoste**: non compaiono in nessuna risposta pubblica (HTML, sitemap, JSON-LD, Open Graph, meta description di riserva).
- **Testi lunghi** (anche 5.000 caratteri senza spazi) non rompono il layout pubblico né quello dell'admin (`overflow-wrap`), né il CSV.
- **Prestazioni**: sezioni e foto di una pagina si leggono con query cumulative; `width` e `height` sulle immagini; prima immagine `fetchpriority="high"`.
- **Storico**: i testi dei contenuti sono ammessi nello Storico (non sono dati personali); il ripristino dei testi è nel prompt 25.

## Test obbligatori

- **contratto dei campi**: per ogni campo di questa fase i "Test minimi" di `docs/CAMPI_CONTENUTI.md` (vuoto, solo spazi, troppo lungo, non valido, con HTML, resa IT/EN, Storico) e i controlli tra campi che lo riguardano;
- migrazione da zero; chiave di pagina sconosciuta rifiutata; sezione di un'altra pagina rifiutata (IDOR);
- autorizzazione e CSRF su ogni azione; audit;
- XSS: HTML e `<script>` in ogni campo mostrati come testo, anche in `<title>`, meta, Open Graph e JSON-LD; link solo `https://`;
- ordine e visibilità rispettati; sezioni nascoste mai nel sorgente;
- DB vuoto: HTML principale delle pagine **identico** a prima (confronto registrato);
- IT/EN e regola EN; titoli unici, canonical, hreflang, sitemap invariati;
- **limite di sezioni**: la 31ª viene rifiutata; **blocco ottimistico**: due schede, la seconda viene avvisata;
- **sezione nascosta** assente da HTML, sitemap, JSON-LD, Open Graph e meta description in ogni lingua;
- testo di 5.000 caratteri senza spazi e testo con `<b>` e `<script>`: layout intatto, tutto mostrato come testo;
- numero di query costante al crescere di sezioni e foto;
- flag bozza mostra/nasconde l'avviso; suite completa PASS.

Aggiorna `TEST_REPORT`, `DECISIONS`, `TODO`, `SESSION_STATE`.

## Prova tu (5–10 minuti, a fine fase)

L'agente elenca questi passi nel riepilogo finale; l'utente li esegue in locale.

1. In **Pagine** modifica Dintorni: aggiungi due sezioni, spostale, nasconditene una: confronta con `docs/mockup-admin/pagina.html`.
2. Scrivi in un testo `<b>prova</b>`: sul sito deve comparire così com'è, non in grassetto.
3. Apri la pagina inglese di una pagina senza traduzione: deve comportarsi come deciso nel prompt 16 (D3).
4. Togli la spunta "bozza" da Privacy e verifica che l'avviso sparisca.

## Stop condition

Fermati quando le pagine sono modificabili e il sito con DB vuoto è invariato. Fermati prima di iniziare se l'utente non ha risposto alle domande [titolare]. **Non** spostare i testi esistenti nel DB, non rimuovere chiavi da `content/*.php`, non importare testi legacy né aggiungere redirect (prompt 22).
