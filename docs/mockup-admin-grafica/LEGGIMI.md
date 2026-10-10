# Bozze grafiche dell'admin (prompt 22b)

Riferimento visivo per il prompt 22b. **Sono bozze, non specifica**: dati e importi sono inventati, i testi di esempio sotto i campi non sono decisioni del titolare.

Apri i file con un browser (nessun server): partendo da `home.html`.

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

- **Vincolante**: gerarchia, palette (fondo chiaro, viola scuro solo per l'azione principale, verde bosco per le azioni secondarie, colori di stato), sezioni a due colonne con titolo e spiegazione a sinistra, componenti, bersagli da 44 px, errori accanto al campo, distanza fra azione principale e distruttiva.
- **Non vincolante**: i dati, le voci di menu non ancora decise, i testi «Esempio:».
- **Voci che dipendono da risposte del titolare** (se la risposta è «no», la scheda si toglie): calendario iCal, statistiche, anonimizzazione dall'admin, email di pre-arrivo.
- Le **schede** (tab) sono pulsanti con un piccolo script solo per la bozza. Nel prodotto vero sono link (`?scheda=…`) o sezioni, **senza JavaScript**.
- I caratteri sono quelli di sistema (un solo sans-serif): nessun font esterno.
- La **Home** con «Da fare oggi» e la griglia delle due settimane per appartamento è una proposta: dipende dai dati disponibili (prenotazioni confermate, richieste, blocchi) e va decisa nel prompt che realizza la Home.
