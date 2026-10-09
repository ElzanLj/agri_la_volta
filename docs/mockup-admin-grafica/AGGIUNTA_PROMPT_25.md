# Aggiunta al prompt 25 — Pagine admin divise in schede, con spiegazione ed esempi

Da inserire nel prompt 25 (struttura e usabilità dell'admin), dopo il raggruppamento del menu, come **nuova attività**. Riferimento visivo: `docs/mockup-admin-grafica/` (le 13 bozze approvate); lo stile dei componenti arriva dal prompt 22b (`admin.css`).

## Attività: schede, «A cosa serve» ed «Esempio»

Obiettivo: ogni pagina admin lunga (oggi un'unica pagina con tutti i campi in fila) viene divisa in **schede**, così la titolare vede subito che cosa fa ogni parte e non scorre pagine lunghe. Nessuna logica nuova.

1. **Schede come link, senza JavaScript.** Ogni scheda è un link alla stessa pagina con `?scheda=<nome>` (o una sezione della pagina): il server mostra solo il contenuto della scheda scelta; la scheda corrente ha `aria-current="page"`. Le bozze usano un piccolo script solo per essere navigabili: nel prodotto **non va copiato**.
2. **Schede previste** (come nelle bozze; se una scheda dipende da una funzione non approvata, non si crea):
   - Impostazioni: Contatti · Azienda e link · Avviso globale · Orari e regole · Prenotazioni e pagamenti.
   - Appartamento: Dati e regole · Descrizioni · Foto · Servizi · Date bloccate · Calendario (iCal solo se approvato, D6 del prompt 24).
   - Pagine: Tutte le pagine · Titolo e Google · Sezioni · Modifica sezione.
   - Servizi e foto: Libreria foto · Carica una foto · Catalogo servizi.
   - Listino: Tariffe stagionali · Supplementi · Prova un preventivo.
   - Arrivi e ricerca: Arrivi e partenze · Cerca · Statistiche · Dati di un ospite (solo le voci approvate nel prompt 24).
   - Email: Stato della posta · Messaggi agli ospiti · Avvisi per te · Server di posta.
   - Stato del sistema: Controlli · Manutenzione e backup · Conservazione dati · Registro errori · Il tuo account.
3. **Una scheda = un modulo = un pulsante «Salva questa scheda».** Cambiare scheda con modifiche non salvate non le perde in silenzio: avvisa (l'avviso è un messaggio nella pagina, non una finestra del browser), oppure il salvataggio è sempre per scheda e la scheda lo dice.
4. **Riga «A cosa serve»** in cima a ogni scheda: una frase che dice che cosa cambia sul sito (testo semplice, senza gergo tecnico).
5. **Riga «Esempio:»** sotto i campi di testo, email, telefono, numero e indirizzo web, e sotto le aree di testo: un valore verosimile come riferimento, **mai** salvato e **mai** un testo reale del titolare. Gli esempi vivono in un solo posto (un elenco nei testi dell'admin), non sparsi nei template. Quelli su temi decisi dal titolare (condizioni di cancellazione, tassa di soggiorno, regole della casa) sono segnalati nel riepilogo per conferma.
6. **Accessibilità**: le schede si raggiungono con Tab, la corrente è annunciata, bersagli da 44 px; la riga «Esempio» è collegata al campo con `aria-describedby` insieme all'aiuto e all'errore.
7. **Test**: ogni scheda risponde 200 e mostra solo il proprio modulo; una scheda sconosciuta torna alla prima; il salvataggio di una scheda non tocca i campi delle altre; nessun `<script>` nei template admin; la riga «A cosa serve» è presente in ogni scheda.

## Divieti

Nessun JavaScript, nessun campo nuovo, nessuna nuova funzione: si riorganizzano i campi già approvati nei prompt 18–24. Non rendere visibile una scheda di una funzione non approvata.
