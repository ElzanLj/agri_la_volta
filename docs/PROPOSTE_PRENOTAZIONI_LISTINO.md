# Proposte di miglioramento — prenotazioni, regole e listino

Proposte del 2026-10-07, basate sul codice. Sono inserite come **domande** nei prompt 23 (regole e listino) e 23 (operatività): l'agente le pone all'inizio della fase. Regola già confermata: **minimo 2 persone per appartamento, attivabile o disattivabile** (prompt 23).

Priorità: **ESSENTIAL** prima della release · **RECOMMENDED** buon rapporto valore/costo · **OPTIONAL** utile ma non necessario · **NOT WORTH IT** sconsigliato · **DA DECIDERE** serve una scelta del titolare.

## 1. Utili subito, a basso rischio

| Priorità | Proposta | Perché | Costo |
|---|---|---|---|
| RECOMMENDED | Modifica di una prenotazione confermata (date, ospiti, totale, note) con controllo di disponibilità sotto lock | Oggi le rotte admin permettono solo di creare o cancellare: per spostare un soggiorno di un giorno si cancella e si ricrea, perdendo il legame con la richiesta e generando una bozza di cancellazione inutile | Medio: parte critica, servono test di concorrenza |
| RECOMMENDED | Simulatore prezzi in admin (date + ospiti → preventivo per ogni appartamento) | Per rispondere al telefono e verificare il listino appena inserito; riusa `previewRequest` | Basso |
| RECOMMENDED | Totale suggerito dal listino nelle prenotazioni manuali, modificabile | Oggi si scrive a mano e può non coincidere col listino | Basso |
| RECOMMENDED | Confronto alla conferma: "preventivato X, listino attuale Y" se diversi | Oggi la conferma usa il preventivo senza avvisare; il comportamento resta, diventa visibile | Basso |
| RECOMMENDED | Copia del listino: un periodo su altri appartamenti, una stagione all'anno successivo | 6 appartamenti × 4–5 periodi × ogni anno a mano è lento e soggetto a errori | Basso-medio |
| RECOMMENDED | Blocco su tutti gli appartamenti in un'unica azione | Chiusure stagionali o ferie richiedono oggi 6 blocchi | Basso |
| RECOMMENDED | Nel dettaglio di una richiesta, elenco delle altre richieste in attesa sulle stesse date e appartamento | Oggi il conflitto emerge solo alla conferma | Basso |
| RECOMMENDED | Richieste in attesa da troppo tempo evidenziate in dashboard | Una richiesta dimenticata è un cliente perso | Basso |
| RECOMMENDED | Orari e regole della casa comuni a tutti gli appartamenti, con eccezioni per il singolo | Oggi gli stessi dati vanno ripetuti 6 volte in IT e 6 in EN (da inserire nel prompt 16) | Basso |
| RECOMMENDED | Prezzo indicativo coerente: avviso se è più basso della tariffa minima attiva, oppure calcolo automatico | Oggi è un campo libero che può contraddire il listino | Basso |
| RECOMMENDED | Messaggio per i gruppi quando nessun appartamento basta: "contattaci per soluzioni con più appartamenti" | Oggi il gruppo vede solo "nessun appartamento disponibile" | Molto basso |

## 2. Utili, ma serve prima una decisione del titolare

| Priorità | Proposta | Domanda per il titolare | Note |
|---|---|---|---|
| RECOMMENDED | Preavviso minimo per l'arrivo (es. 1 giorno) | "Accettate richieste per stasera?" | Oggi si può chiedere l'arrivo per il giorno stesso |
| RECOMMENDED | Condizioni di cancellazione e pagamento (solo testo) nel riepilogo e nell'email di conferma | "Quali sono le vostre condizioni?" (verifica col consulente) | `ScopeTest` vieta campi IBAN: le coordinate bancarie richiedono una decisione esplicita o vanno comunicate a mano |
| RECOMMENDED | Nota sulla tassa di soggiorno ("esclusa, da pagare in struttura"), senza calcolo | "Il Comune la applica? Importo?" | Evita contestazioni |
| DA DECIDERE | Soggiorno minimo più severo: il minimo più alto tra i periodi attraversati | "Il minimo di Ferragosto vale per chiunque tocchi quelle date?" | Oggi vale il minimo della notte di arrivo: chi arriva il giorno prima dell'alta stagione lo aggira. Cambia una regola già confermata |
| OPTIONAL | Giorni di arrivo vincolati (es. agosto solo sabato) | "Avete settimane fisse in alta stagione?" | Si aggiunge al periodo tariffario |
| OPTIONAL | Giorno di pulizia tra due soggiorni | "Riuscite a pulire il giorno stesso della partenza?" | Tocca la disponibilità, la parte più critica |
| OPTIONAL | Neonati esclusi dalla capienza (fascia 0–2 anni) | "Un neonato in culla conta come ospite?" | Serve raccogliere l'età nel modulo |
| OPTIONAL | Supplementi opzionali scelti dal cliente (lettino, letto aggiunto) | Importi confermati | Mancano la scelta nel modulo e il collegamento alla regola |
| OPTIONAL | Sconto per soggiorni lunghi (es. da 7 notti) | "Fate sconti per settimane intere?" | Regola condizionata dal numero di notti, senza percentuali |
| OPTIONAL | Calendario in sola lettura (link iCal privato per il telefono del titolare) | Richiede autorizzazione (SPEC §13) | Solo esportazione, nessuna sincronizzazione con Booking/Airbnb |

## 3. Sconsigliato

- Sconti in percentuale: arrotondamenti e complessità; un importo fisso copre quasi sempre lo stesso bisogno.
- Prezzi dinamici in base all'occupazione: eccessivi per sei appartamenti.
- Codici sconto: inutili senza pagamento online.
- Calcolo automatico della tassa di soggiorno: esenzioni ed età lo rendono fragile; basta la nota testuale.

## 4. Dove sono nei prompt

- Regole di prenotazione e listino (minimo persone, soggiorno minimo, preavviso, date alternative, prezzo alla conferma, prezzo indicativo, condizioni, tassa di soggiorno, modifica e ripristino prenotazione, simulatore, copia listino, blocchi globali, gruppi, regole che si sommano, richieste scadute): **prompt 23**.
- Richieste sovrapposte, richieste in attesa, iCal: **prompt 24**.
- Orari e regole della casa comuni a tutti gli appartamenti: **prompt 18**.
