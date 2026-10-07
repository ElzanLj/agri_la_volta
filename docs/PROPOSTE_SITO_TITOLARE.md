# Proposte "se fosse il mio sito" e prova delle foto legacy

Proposte del 2026-10-07, pensate dal punto di vista di chi gestisce l'agriturismo. Sono inserite come **domande** nei prompt indicati. Molte dipendono da informazioni che solo il titolare ha: non vanno inventate.

La domanda di partenza: **perché un ospite dovrebbe scegliere La Volta e prenotare qui invece che su Booking?**

## 1. Foto e racconto (prima di tutto)

- Una giornata con un fotografo: appartamenti, piscina, colline, luce del tramonto, dettagli. È l'investimento con il ritorno più alto.
- La storia dell'azienda agricola (società agricola della famiglia Oretti): cosa coltivate o producete, chi accoglie gli ospiti.
- Un'identità coerente: logo semplice, colori dal paesaggio, foto con lo stesso stile.

Nei prompt: sezione "storia dell'azienda e prodotti" predisposta nascosta (**prompt 22, D3**); libreria foto (**18**); icone e logo rinviati (**24**).

## 2. Evitare doppie prenotazioni con gli altri canali

Se un appartamento è anche su Booking o Novasol, le loro prenotazioni oggi vanno inserite a mano. Proposta: import in sola lettura dei calendari iCal come blocchi, aggiornato ogni ora da cron. Va contro SPEC §13 senza autorizzazione esplicita.

Nei prompt: **prompt 24, D6** (se approvato, fase dedicata).

## 3. Prezzi e disponibilità visibili prima del modulo

- Tabella prezzi per stagione nella pagina appartamento, generata dal listino (**prompt 25, D4**).
- Calendario "libero/occupato" senza nomi (**prompt 25, D3**).
- Date alternative quando non c'è posto (**prompt 23, D8**).
- Tabella di confronto degli appartamenti (**prompt 20, D5**).

## 4. Far sentire seguito chi scrive

- Email "richiesta ricevuta" (**prompt 24, D1**).
- Promessa visibile di tempi di risposta (es. "entro 24 ore"), da mantenere davvero: testo del titolare, nessun codice.
- Campi facoltativi "orario di arrivo previsto" e "come ci hai conosciuto?" (**prompt 24, D3**).
- Email di pre-arrivo con indicazioni e contatti (**prompt 24, D2**).

## 5. Il territorio come motivo per venire

- Idee di soggiorno per tipo di ospite: famiglie (piscina, spazi, animali), coppie (terme di Salsomaggiore e Tabiano, castelli), buongustai (Parmigiano, prosciutto, culatello, cantine), camminatori e ciclisti (colline, Via Francigena).
- Distanze reali in km e minuti (terme, Parma, Fidenza, Busseto, castelli, stazione, autostrada): da verificare, mai stimare.
- Indicazioni per arrivare, perché in collina i navigatori sbagliano.

Nei prompt: sezioni predisposte nascoste (**prompt 22, D3**).

## 6. Informazioni pratiche / FAQ

Wi-Fi, culla e seggiolone, regole per gli animali, stagione e orari della piscina, parcheggio e ricarica auto, negozi e ristoranti vicini, appartamenti al piano terra o senza scale.

Nei prompt: FAQ in **prompt 22, D3**; campo "piano / accessibile senza scale" in **prompt 20, D3**.

## 7. Recensioni vere

Link a TripAdvisor e Google (**prompt 18, D3**); qualche frase di ospiti reali solo con il loro permesso; mai stelle o recensioni senza fonte.

## 8. Perché prenotare direttamente

Se è vero: "nessuna commissione", "contatto diretto", "richieste particolari più facili". Da verificare prima: i contratti con Booking possono vietare prezzi più bassi sul proprio sito. Solo testo del titolare.

## 9. Due verifiche sui dati che il titolare ha già

- Da quali paesi arrivano gli ospiti (dati Booking/Novasol): decide se serve una terza lingua (**prompt 16, D7**).
- Offerta del momento in home (**prompt 21, D3**).

## 10. Cosa non farei

Pagamento online; chatbot o chat dal vivo (WhatsApp basta); animazioni, video in apertura, slider; newsletter subito.

## 11. Le tre priorità

1. Foto e racconto.
2. Import dei calendari degli altri canali.
3. Date alternative quando non c'è posto.

## 12. Prova del sito con le foto del legacy (solo in locale)

Usare le foto di `legacy/src/assets` per vedere come si comporta il sito va bene **solo in locale**: molte hanno provenienza dubbia (`docs/IMAGES.md`) e non devono finire in un commit né in produzione.

Cosa mostrerebbe la prova: resa con immagini vere, peso e velocità delle pagine (~33 MB nel legacy, PNG pesanti), proporzioni miste, effetto delle foto ripetute tra appartamenti; collaudo di `ImageSet` e dello script di ottimizzazione.

Opzioni:

| Opzione | Come | Pro / contro |
|---|---|---|
| ✅ A. Dopo la libreria foto | Caricarle dall'admin nel DB locale, credito "LEGACY – NON PUBBLICARE" | Nessuna modifica temporanea al codice; collauda la funzione vera |
| B. Subito, ramo Git solo locale | GD nel Docker, varianti con lo script, template modificati a mano | Più veloce; modifiche da buttare, alcuni test falliscono di proposito |
| C. Anteprima statica | Pagina HTML con il CSS del sito e le foto | Solo effetto visivo, non il comportamento reale |

Nei prompt: **prompt 19, D4**. Prima del rilascio: nessuna foto con credito "LEGACY – NON PUBBLICARE" (**prompt 31**); `legacy/` eliminato o escluso (**prompt 28, D6**).
