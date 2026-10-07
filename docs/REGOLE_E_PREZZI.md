# Regole degli appartamenti e prezzi — come funzionano oggi

Spiegazione del 2026-10-07, ricavata dal codice. Descrive lo stato **attuale**: le modifiche possibili sono domande nel prompt 23.

**Regola confermata in arrivo:** minimo 2 persone per appartamento, attivabile o disattivabile dall'admin (fornita dall'utente il 2026-10-07), da implementare nel prompt 23. Da spenta vale il minimo attuale di 1 adulto.

File principali: `app/Domain/PriceCalculator.php`, `app/Domain/ChargeRule.php`, `app/Domain/RatePeriod.php`, `app/Service/PricingConfigService.php`, `app/Service/BookingService.php`, `app/Service/ApartmentAdminService.php`, `migrations/0001` e `0003`.

## 1. Regole degli appartamenti

"Regole" nel progetto significa due cose diverse.

### 1.1 Testo "Regole della casa"

- Campo di testo libero in italiano e in inglese (`apartment_translations.rules`).
- Si compila da Admin > Appartamenti.
- Compare nella pagina pubblica dell'appartamento solo se compilato.
- Il sistema non lo interpreta: è solo informazione per l'ospite.

### 1.2 Regole applicate dal sistema

Campi dell'appartamento, modificabili da Admin > Appartamenti:

- **Attivo / non attivo**: se non attivo, l'appartamento sparisce dal sito e la sua pagina dà 404.
- **Accetta richieste online**: se spento, la pagina resta visibile ma invita a contattare direttamente (es. appartamento gestito da Novasol).
- **Capienza massima** (`max_guests`): conta adulti + bambini insieme.
- **Massimo bambini** (`max_children`).
- **Massimo animali** (`max_pets`): `0` = animali non ammessi; vuoto = nessun limite.
- **Orari di arrivo e partenza**: solo informativi (pagina ed email), non bloccano nulla.

Limiti tecnici uguali per tutti, nel codice:

- massimo 60 notti per una richiesta online (`StayDates::MAX_NIGHTS_STAY`);
- arrivo entro 2 anni (`MAX_ADVANCE_YEARS`);
- massimo 20 persone e 10 animali (`GuestCounts`).

Dove vengono controllate:

- sul server, in un unico punto: `BookingService::prepareRequest`;
- richiamato a ogni passo del modulo pubblico e di nuovo all'invio finale, quindi il browser non può aggirarle;
- al passo 2 gli appartamenti non adatti sono mostrati con il motivo (es. "In questo appartamento gli animali non sono ammessi") e senza pulsante;
- per le prenotazioni manuali dell'admin viene ricontrollata solo la capienza.

## 2. Prezzi

Si gestiscono da Admin > Listino. Nessun prezzo è nel codice o nelle migrazioni: oggi il listino è vuoto e lo inserisce il titolare.

### 2.1 Tariffe stagionali (`seasonal_rates`)

Prezzo base per appartamento e per notte in un periodo. Ogni tariffa ha:

- etichetta IT/EN (es. "Alta stagione");
- data di inizio e fine (la fine è esclusa, come per i soggiorni);
- tariffa a notte;
- soggiorno minimo facoltativo;
- attiva / non attiva.

Due periodi attivi dello stesso appartamento non possono sovrapporsi: l'admin rifiuta il salvataggio (controllo sotto lock).

### 2.2 Regole aggiuntive (`pricing_rules`)

Supplementi che si sommano alla tariffa base. Vocabolario chiuso, non un motore di regole generico. Ogni regola indica:

- **a chi si applica**: adulti, bambini, animali, oppure soggiorno (supplemento fisso);
- **unità gratuite**: es. "2 adulti inclusi" = si paga dal terzo;
- **base di calcolo**: per notte oppure una volta per soggiorno;
- **importo**;
- **finestra di validità facoltativa**: una regola per notte conta solo le notti dentro la finestra; una regola per soggiorno si applica se la data di arrivo è nella finestra;
- **ambito**: un appartamento oppure tutti;
- ordine di visualizzazione ed etichette IT/EN.

### 2.3 Come si calcola un soggiorno (`PriceCalculator`)

Il calcolo non legge database né orologio: a parità di dati, stesso risultato.

1. **Tariffa base notte per notte**: ogni notte usa il periodo che la contiene. Un soggiorno a cavallo di due stagioni produce due righe.
2. **Soggiorno minimo**: vale quello del periodo che contiene la notte di arrivo. Se non è rispettato la richiesta è bloccata ("Per queste date il soggiorno minimo è di N notti").
3. **Supplementi**: per ogni regola attiva, unità da pagare (oltre quelle gratuite) × importo × notti valide, oppure una volta sola se per soggiorno.
4. **Totale**: somma delle righe, in centesimi interi (nessun errore di arrotondamento).

### 2.4 Esempio (cifre inventate solo per illustrare)

Listino:

- Bassa stagione 80 € fino al 30 giugno;
- Alta stagione 110 € dal 1° luglio, minimo 3 notti;
- 2 adulti inclusi, 15 €/notte per ogni adulto in più;
- 1 bambino gratis, 10 €/notte per gli altri;
- 5 €/notte per animale;
- pulizia finale 30 € per soggiorno.

Soggiorno dal 29 giugno al 3 luglio (4 notti), 3 adulti, 2 bambini, 1 cane:

| Riga | Calcolo | Importo |
|---|---|---|
| Bassa stagione | 2 notti × 80 € | 160 € |
| Alta stagione | 2 notti × 110 € | 220 € |
| Adulto in più | 1 × 15 € × 4 notti | 60 € |
| Bambino oltre il gratuito | 1 × 10 € × 4 notti | 40 € |
| Animale | 1 × 5 € × 4 notti | 20 € |
| Pulizia finale | una volta | 30 € |
| **Totale** | | **530 €** |

Il minimo di 3 notti dell'alta stagione non scatta: la notte di arrivo (29 giugno) è in bassa stagione. Il totale vale solo se le 5 persone rientrano nella capienza e il cane è ammesso.

### 2.5 Se manca qualcosa

- Se anche una sola notte non ha tariffa, il sistema non inventa un prezzo: la richiesta si può inviare, ma il cliente vede "il gestore ti indicherà il prezzo nella risposta".
- L'admin elenca le date ancora scoperte (`PricingConfigService::coverageGaps`).

### 2.6 Quando il prezzo viene fissato

- Il prezzo mostrato al cliente è calcolato sempre dal server; importi inviati dal browser sono ignorati.
- All'invio la richiesta salva totale e dettaglio (`quoted_total_cents`, `price_breakdown`).
- Alla conferma il sistema ricontrolla la disponibilità ma **non ricalcola il prezzo**: la prenotazione prende il totale preventivato. Se il listino cambia nel frattempo, vale ciò che il cliente ha visto.
- Nelle prenotazioni manuali il totale lo scrive l'admin (facoltativo).
- Il prezzo indicativo dell'appartamento ("Da X a notte") è solo per la vetrina, mai usato nei calcoli.

## 3. Cosa non è gestito (escluso di proposito, vedi `docs/MISSING_DATA.md`)

- sconti in percentuale (servono regole di arrotondamento);
- prezzi per età dei bambini (il modulo non chiede l'età);
- supplementi opzionali scelti dal cliente (lettino, letto aggiunto, pulizia infrasettimanale);
- sconti per soggiorni lunghi;
- tassa di soggiorno (dipende dalle regole del Comune).
