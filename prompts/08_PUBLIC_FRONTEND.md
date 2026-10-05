# Prompt 08 — Frontend pubblico e flusso richiesta

## Obiettivo

Completare le pagine pubbliche e il flusso di richiesta preservando l'identità utile del progetto esistente.

## Pagine minime

- `/`
- `/agriturismo`
- `/appartamenti`
- pagina singola per ogni appartamento
- `/dintorni`
- `/richiedi-disponibilita`
- `/contatti`
- `/privacy`
- `/cookie`

Gli URL EN possono essere definiti coerentemente senza inventare contenuti definitivi.

## Flusso richiesta

Date → ospiti/animali → disponibilità/prezzo → appartamenti disponibili → appartamento → dati cliente → riepilogo → privacy → invio → “Richiesta ricevuta”.

Mai mostrare “Prenotazione confermata” in questa fase.

## Design

Rurale, elegante, autentico, semplice. Fotografie ampie, tipografia leggibile, spaziatura coerente, CTA chiare. Telefono, email, WhatsApp e richiesta disponibilità facilmente raggiungibili.

Evita popup/carousel/slider/animazioni non necessari e `alert()`.

## Integrazione

Usa i servizi server-side del core per prezzo/disponibilità; non duplicare logica critica nel browser.

## Stop condition

Pagine e flusso devono essere navigabili e integrati. SEO/a11y/performance approfonditi vengono nella fase 09, ma non introdurre problemi evidenti sapendo che dovranno essere corretti dopo.
