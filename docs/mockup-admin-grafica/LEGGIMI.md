# Bozze grafiche dell'admin (prompt 22b)

Riferimento visivo per il prompt 22b. **Sono bozze, non specifica**: dati e importi sono inventati, i testi di esempio sotto i campi non sono decisioni del titolare.

Apri i file con un browser (nessun server): partire da `home.html`.

| File | Mostra |
|---|---|
| `home.html` | cruscotto |
| `richieste.html`, `dettaglio-richiesta.html` | elenco e dettaglio richiesta |
| `impostazioni.html` | impostazioni del sito (5 schede) |
| `appartamento.html` | scheda appartamento (6 schede) |
| `pagine.html` | pagine e sezioni (4 schede) |
| `servizi-foto.html` | libreria foto e catalogo servizi |
| `listino.html` | tariffe, supplementi, prova preventivo |
| `arrivi-ricerca.html` | arrivi, ricerca, statistiche, dati ospite |
| `email.html` | stato posta, messaggi agli ospiti, server |
| `stato-sistema.html` | controlli, manutenzione, conservazione, account |
| `telefono.html` | schede al posto delle tabelle, menu a scomparsa |
| `componenti.html` | colori, pulsanti, stati, errori, tipografia |

## Cosa è vincolante e cosa no

- **Vincolante**: gerarchia, palette (verde bosco, ocra per le azioni principali, colori di stato), componenti, bersagli da 44 px, errori accanto al campo, distanza fra azione principale e distruttiva.
- **Non vincolante**: i dati, le voci di menu non ancora decise, i testi «Esempio:».
- **Voci che dipendono da risposte del titolare** (se la risposta è «no», la scheda si toglie): calendario iCal, statistiche, anonimizzazione dall'admin, email di pre-arrivo.
- Le **schede** (tab) sono pulsanti con un piccolo script solo per la bozza. Nel prodotto vero sono link (`?scheda=…`) o sezioni, **senza JavaScript**.
- I caratteri sono quelli di sistema (serif per i titoli, sans per il testo): nessun font esterno.
