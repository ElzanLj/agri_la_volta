# Prompt 32 — Verifica dopo la pubblicazione

Prerequisito: prompt 31 completato **e** sito pubblicato dall'utente, con autorizzazione scritta in chat per questa verifica: l'utente indica l'indirizzo del sito e dice esplicitamente che sono ammesse le prove della sezione "Cosa si prova" (D1). Senza questa frase la fase non inizia.

Contesto: tutti i test finora sono stati eseguiti in Docker, con `AllowOverride All`, PHP-FPM e un solo host. Questa fase controlla ciò che in Docker non si vede: hosting reale, HTTPS, proxy, `.htaccess` accettati, estensioni PHP, email vere, ripristino del backup. È **sola verifica**: i problemi trovati diventano finding da correggere in locale (nuova fase e nuovo rilascio), mai modifiche fatte sul sito pubblicato. Si può **ripetere dopo ogni aggiornamento importante** con la stessa checklist.

## Obiettivo

Verificare sull'hosting reale che il sito si comporti come in sviluppo e che le protezioni funzionino, e produrre un rapporto con esito ed evidenza di ogni prova.

## Prima di modificare

- Vincoli: nessuna modifica al codice né alla configurazione dell'hosting; nessun accesso a servizi esterni oltre al sito indicato dall'utente; rispetta `docs/GUARDRAIL_FASI.md` (autorizzazioni solo in chat, nessun segreto).
- Leggi `docs/RELEASE_GUIDE.md`, `docs/POST_DEPLOY_CHECKLIST.md`, `docs/THREAT_MODEL.md`, `docs/MANUAL_CHECKLIST.md`, `docs/FINAL_REVIEW.md` e in `docs/REVIEW_PRE_ROADMAP.md` le schede A7, A8, A9, A10, A14, C24, la sezione D.2 e "Disaster recovery".
- `git status` e diff locali (la fase scrive solo `docs/POST_DEPLOY_REPORT.md`).

## Domande per l'utente (all'inizio della fase)

Prima di modificare qualsiasi file, apri `docs/RISPOSTE_UTENTE.md`: se l'utente ha già segnato le risposte per questo prompt, mostrale e chiedi solo conferma. Altrimenti presenta le domande di questa sezione **così come sono scritte** (risposte possibili e consiglio) e **fermati finché non risponde**.

Le domande sono di due tipi, indicati nel titolo:

- **[titolare]**: decisioni sul business (prezzi, regole, testi, comunicazioni agli ospiti, dati legali, servizi esterni). Non applicare mai una risposta ✅ al posto dell'utente: serve la sua risposta scritta in chat ("consigliate" vale, se detto esplicitamente per questa fase). Senza risposta la fase non inizia.
- **[tecnica]**: scelte di implementazione. Se l'utente risponde "consigliate" o non ha preferenze, vale la risposta ✅; elencale comunque nel riepilogo finale.

Registra ogni scelta in `docs/DECISIONS.md`. In `docs/RISPOSTE_UTENTE.md` (casella con `x`) scrivi **solo** ciò che l'utente ha detto in chat, mai risposte tue. Le voci rinviate o rifiutate vanno in `docs/TODO.md` con il motivo. Non implementare nulla che non sia stato approvato. Se una voce approvata supera lo scope di questa fase, proponi di spostarla e fermati.


### D1 — Cosa si prova sul sito reale [titolare]

| Risposta | Pro / contro |
|---|---|
| A. ✅ Sola lettura e prove che non lasciano dati: richieste di file sensibili, intestazioni, upload ostili **rifiutati**, richieste non valide; in più **una sola richiesta di prova** (nome "PROVA") e **una sola foto di prova** che il titolare elimina e anonimizza subito dopo | Copre il flusso completo (email vere comprese) con un impatto minimo e reversibile |
| B. Solo sola lettura: niente richiesta e niente foto | Nessun dato di prova nel sito reale; non si verifica l'invio dell'email né il caricamento |
| C. Anche prove di carico o scansioni attive | **Sconsigliata** e vietata da questo prompt: può bloccare il sito o far scattare i controlli dell'hosting |

**Consiglio: A.**

### D2 — Strumenti di terzi (securityheaders.com, SSL Labs, mail-tester.com, PageSpeed) [titolare]

| Risposta | Pro / contro |
|---|---|
| A. ✅ Li esegue l'utente e incolla i risultati; l'agente non invia l'indirizzo del sito a servizi di terzi | Nessun dato a terzi senza il tuo gesto; l'agente interpreta i risultati |
| B. L'agente li esegue | Più veloce; invia l'indirizzo del sito a servizi esterni: serve un'autorizzazione esplicita per ognuno |

**Consiglio: A.**

### D3 — Ripristino del backup su un database di prova dell'hosting [tecnica]

| Risposta | Pro / contro |
|---|---|
| A. ✅ Il titolare scarica il backup dall'admin e lo importa in un **secondo database** dell'hosting (mai in quello reale), poi verifica richieste, prenotazioni e foto | Si scopre se il ripristino funziona con le versioni e i limiti reali dell'hosting |
| B. Si segna NOT RUN | Nessun lavoro; il ripristino non è provato dove serve |

**Consiglio: A**, se l'hosting permette un secondo database; altrimenti B con il motivo scritto.

## Cosa si prova (in quest'ordine)

1. **File sensibili** (solo richieste GET): `/.env`, `/.git/`, `/composer.json`, `/composer.lock`, `/phpunit.xml`, `/storage/logs/`, `/storage/sessions/`, `/migrations/0001_initial_schema.sql`, `/legacy/`, `/docs/`, `/prompts/`, `/tests/`, `/bin/`, `/vendor/`, `/app/`. Atteso: 403 o 404, mai il contenuto. Un solo contenuto esposto è un finding **critico**: l'agente lo segnala subito all'utente.
2. **Host e HTTPS**: `http://` → `https://` con un solo redirect; `www` e non-`www` → un solo host canonico (301); barra finale → URL canonico; certificato valido e non in scadenza a breve.
3. **Intestazioni** (`curl -I`): CSP, HSTS (solo su https), `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, COOP, CORP, nessun `X-Powered-By`; pagina di login admin con cookie `Secure`, `HttpOnly`, `SameSite`; nessun cookie sulle pagine pubbliche.
4. **Funzioni del sito**: pagine IT/EN, appartamenti, flusso di richiesta (passi 1–4) con dati non validi e con un minimo di persone non rispettato (se la regola è attiva), 404, `/dovesiamo`, sitemap, robots, hreflang, `lang`.
5. **Stato del sistema** (lo apre l'utente e riferisce): nessuna voce rossa; estensioni e limiti di PHP; "invio dopo la risposta" disponibile o no; orologio PHP/database; `storage/` scrivibile; controllo HTTP dei file sensibili coerente con il punto 1; header di proxy rilevati.
6. **Email vere**: "Invia email di prova" dall'admin; con D1 = A, la richiesta di prova → email al titolare **e** ricevuta all'ospite (se prevista) arrivano, non finiscono nello spam; punteggio di mail-tester (D2).
7. **Upload ostili** (il titolare li esegue dall'admin con i file di `tests/fixtures/images/`; l'agente guida): file PHP rinominato, polyglot, SVG, HEIC, immagine enorme → tutti rifiutati con il messaggio previsto, **nessun** file nella cartella delle foto; foto di prova valida (D1 = A) caricata, visibile, con le varianti, poi eliminata. Richiesta HTTP a un `.php` inesistente nella cartella delle foto → 404, mai esecuzione.
8. **Rate limit e proxy**: il rate limit non blocca il titolare dopo un singolo errore; se l'hosting usa un proxy, l'IP letto non è sempre lo stesso (finding se tutti i visitatori risultano un solo indirizzo).
9. **Backup** (D3): backup scaricato, impronta SHA-256 uguale a quella registrata, importato nel database di prova, verifica di coerenza e conteggi.
10. **Prestazioni e accessibilità base** (D2): PageSpeed/Lighthouse e axe sulla home, un appartamento e il modulo, su telefono: risultati registrati (nessun obiettivo numerico vincolante, ma nessuna regressione evidente rispetto a Docker).
11. **Monitoraggio**: il servizio scelto nel prompt 31 è attivo e invia un avviso di prova.
12. **Pulizia delle prove**: richiesta di prova anonimizzata, foto di prova eliminata, nessun dato di prova rimasto (controllo dell'agente guidando l'utente).

## Divieti

Nessuna modifica al codice, ai file o alla configurazione dell'hosting; nessun accesso all'admin da parte dell'agente (le prove in admin le esegue l'utente, che riferisce l'esito); nessuna lettura di `.env` né di backup; nessuna prova di carico, brute force o scansione attiva; nessuna modifica a DNS, email o servizi; nessun dato reale di ospiti usato per le prove. Se una prova modifica dati reali o dà un risultato inatteso, **fermati** e avvisa l'utente.

## Output

Crea `docs/POST_DEPLOY_REPORT.md` con data, indirizzo del sito, versione (`VERSION`), versioni di PHP e database e, per ogni prova: esito (`PASS`, `FAIL`, `NOT RUN`), evidenza (comando e risposta sintetica, mai segreti) e azione correttiva. Ogni `FAIL` diventa un finding in `TODO` con la fase che lo corregge. Aggiorna `SESSION_STATE` e `DELIVERY_CHECKLIST`.

## Prova tu

L'agente guida l'utente; l'utente esegue i passi che richiedono l'admin o strumenti di terzi e riferisce l'esito:

1. Apri **Stato del sistema** e leggi all'agente le voci rosse e gialle (senza valori segreti).
2. Esegui le prove dei punti 6, 7 e 9 dall'admin.
3. Se D2 = A, esegui gli strumenti di terzi e incolla i risultati.
4. A fine fase elimina la richiesta e la foto di prova e conferma.

## Stop condition

Fermati quando `docs/POST_DEPLOY_REPORT.md` è compilato con esito ed evidenza per ogni prova e ogni `FAIL` ha un finding in `TODO`. Fermati prima di iniziare se l'utente non ha dato l'autorizzazione scritta in chat o non ha risposto alle domande [titolare]. **Non** correggere nulla sul sito pubblicato: le correzioni si fanno in locale, con una nuova fase e un nuovo rilascio.
