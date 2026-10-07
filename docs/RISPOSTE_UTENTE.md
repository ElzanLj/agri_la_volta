# Foglio risposte — domande dei prompt 14–32

Puoi compilarlo **prima** di eseguire i prompt: metti una `x` nella casella scelta (`[x]`) e aggiungi note se vuoi. Quando l'agente arriva a un prompt, legge qui le tue risposte e ti chiede solo conferma. Le domande senza risposta te le farà in quel momento.

Le domande sono di due tipi:

- **[titolare]**: decisioni sul business. L'agente non applica mai la risposta consigliata al posto tuo: serve la tua risposta scritta in chat (basta "consigliate", se lo dici per quella fase).
- **[tecnica]**: scelte di implementazione. Se rispondi "consigliate" o non hai preferenze, vale la risposta *(consigliata)* e l'agente la elenca nel riepilogo.

L'agente scrive in questo file **solo** ciò che gli hai detto in chat, mai risposte sue.

Il testo completo di ogni domanda, con pro e contro, è nel prompt indicato. Il comportamento dei campi (obbligatori, limiti, cosa succede se vuoti) è in `docs/CAMPI_CONTENUTI.md`.

## Prompt 14 — Sync dello stato, guardrail e audit dei contenuti

File: `prompts/14_STATE_SYNC_CONTENT_AUDIT.md`

### D1 — Strategia Git per le fasi [titolare]

- [x] A. Un ramo per fase (`fase-NN-nome`), che l'utente unisce a mano al ramo principale quando ha provato il risultato *(consigliata)*
- [ ] B. Si resta sul ramo corrente e prima di ogni fase si crea un tag locale `prima-fase-NN`
- [ ] C. Nessuna precauzione

Note: Risposta in chat il 2026-10-07: "consigliate" (A).

### D2 — Guardrail negli strumenti degli agenti [titolare]

- [x] A. Aggiungere ad `AGENTS.md` (letto da Claude, Codex e Cursor) una breve sezione che rimanda a `docs/GUARDRAIL_FASI.md` e ne riassume i punti vietati *(consigliata)*
- [ ] B. Lasciare solo il documento: i prompt lo richiamano comunque
- [ ] C. Non usarli

Note: Risposta in chat il 2026-10-07: "consigliate" (A). Testo approvato in chat ("Salva AGENTS.md con il testo proposto").

## Prompt 15 — Correzioni dell'esistente prima della gestione contenuti

File: `prompts/15_EXISTING_FIXES.md`

### D1 — Rifiuto di una richiesta [titolare]

- [x] A. Pagina di conferma con riepilogo, anteprima dell'email che riceverà l'ospite e pulsanti separati ("Rifiuta e avvisa l'ospite" / "Torna indietro") *(consigliata)*
- [ ] B. Come A, più un messaggio facoltativo all'ospite (es. "possiamo proporvi altre date")
- [ ] C. Nessuna conferma (come oggi)

Note: Risposta in chat il 2026-10-07: "Consigliate, per la D1 va bene quello che hai scelto te" (A).

### D2 — Storico e testo libero (privacy) [tecnica]

- [x] A. Lo Storico non contiene più testo libero personale: dei motivi registra solo "motivo presente: sì/no"; le voci già esistenti vengono ripulite da una migrazione/comando idempotente *(consigliata)*
- [ ] B. Si mantiene il testo e l'anonimizzazione lo cancella anche dallo Storico delle entità coinvolte
- [ ] C. Nessuna modifica

Note: Risposta in chat il 2026-10-07: "Consigliate" (A).

### D3 — Doppio invio del modulo pubblico [tecnica]

- [x] A. Chiave di invio unica: colonna `submission_key` con `UNIQUE` (hash del token del modulo e dei dati); un secondo POST identico mostra la stessa pagina "ricevuta" con lo stesso riferimento, senza nuova richiesta né nuova email *(consigliata)*
- [ ] B. Token monouso con tabella dedicata
- [ ] C. Niente

Note: Risposta in chat il 2026-10-07: "Consigliate" (A).

### D4 — IP del visitatore dietro proxy o CDN [tecnica]

- [x] A. Solo `REMOTE_ADDR` come oggi; il prompt 26 aggiunge in "Stato del sistema" un avviso se arrivano header di proxy *(consigliata)*
- [ ] B. Variabile facoltativa `TRUSTED_PROXIES` (elenco di IP/CIDR): solo da quei proxy si legge `X-Forwarded-For` o `CF-Connecting-IP`
- [ ] C. Leggere sempre gli header

Note: Risposta in chat il 2026-10-07: "per D4 vado sulla scelta prudente" (A).

### D5 — Host canonico [tecnica]

- [x] A. Redirect 301 delle GET verso l'host di `APP_URL` quando `Host` è diverso (nessun redirect se `APP_URL` non è configurato); il redirect a https resta compito dell'hosting *(consigliata)*
- [ ] B. Nessun redirect, solo documentazione
- [ ] C. Redirect a https anche nel PHP

Note: Risposta in chat il 2026-10-07: "Consigliate" (A).

### D6 — Verifica di coerenza dei dati [tecnica]

- [x] A. Servizio in sola lettura + comando `bin/check-consistency.php` + test (la pagina admin e l'allarme in dashboard arrivano nel prompt 26) *(consigliata)*
- [ ] B. Solo il comando, senza servizio riusabile
- [ ] C. Niente

Note: Risposta in chat il 2026-10-07: "Consigliate" (A).

### D7 — Vincoli aggiuntivi nel database [tecnica]

- [x] A. Nuova migrazione con `CHECK`: `bookings.status='cancelled'` ⇔ `cancelled_at` presente; `booking_requests.decided_at` coerente con lo stato; limiti su adulti, bambini, animali; `email_outbox.status='sent'` ⇒ `sent_at`. Prima dell'`ALTER` la migrazione verifica che i dati esistenti li rispettino *(consigliata)*
- [ ] B. Nessun vincolo nuovo

Note: Risposta in chat il 2026-10-07: "Consigliate" (A).

### D8 — Tentativi di accesso falliti [tecnica]

- [x] A. Numero di tentativi falliti nelle ultime 24 ore mostrato in dashboard *(consigliata)*
- [ ] B. Niente

Note: Risposta in chat il 2026-10-07: "Consigliate" (A).

## Prompt 16 — Progetto della gestione contenuti

File: `prompts/16_CONTENT_MODEL_DESIGN.md`

### D1 — Dove salvare le impostazioni del sito [tecnica]

- [ ] A. Una tabella a riga unica con una colonna per ogni dato *(consigliata)*
- [ ] B. Tabella chiave/valore
- [ ] C. Restare su `.env`

Note: 

### D2 — Formattazione dei testi nelle pagine [tecnica]

- [ ] A. Solo paragrafi (riga vuota = nuovo paragrafo)
- [ ] B. Paragrafi + elenchi puntati con righe che iniziano con `- ` *(consigliata)*
- [ ] C. Markdown ridotto (grassetto, link)
- [ ] D. Editor visuale (WYSIWYG)

Note: 

### D3 — Pagina inglese quando il testo inglese manca [titolare]

- [ ] A. Mostrare il testo italiano
- [ ] B. Mostrare un avviso "testo in preparazione" (come oggi) e omettere la sezione *(consigliata)*
- [ ] C. Pagina EN in `noindex` finché incompleta

Note: 

### D4 — Recapiti oggi in `.env` (`PUBLIC_*`, `WHATSAPP_NUMBER`) [tecnica]

- [ ] A. Database prima, `.env` come riserva se il campo è vuoto *(consigliata)*
- [ ] B. Import una tantum, poi solo database
- [ ] C. Solo database da subito

Note: 

### D5 — Cosa conservare della foto caricata [tecnica]

- [ ] A. Solo le varianti ridimensionate
- [ ] B. Un "master" ricodificato senza EXIF/GPS (lato lungo 2400–3000 px) fuori dalla cartella pubblica (`storage/`), da cui si rigenerano le varianti *(consigliata)*
- [ ] C. L'originale grezzo fuori dalla cartella pubblica

Note: 

### D6 — Privacy e cookie modificabili dall'admin [titolare]

- [ ] A. Sì, come pagine con sezioni e flag "bozza" *(consigliata)*
- [ ] B. Restano nel codice
- [ ] C. Solo privacy; la cookie policy resta nel codice

Note: 

### D7 — Terza lingua [titolare]

- [ ] A. No per ora; il titolare verifica da quali paesi arrivano gli ospiti (dati Booking/Novasol) *(consigliata)*
- [ ] B. Tedesco subito
- [ ] C. Struttura multilingua generica

Note: 

### D8 — Pagina "Appartamenti" (elenco) amministrabile [tecnica]

- [ ] A. Sì, solo introduzione e meta *(consigliata)*
- [ ] B. No

Note: 

### D9 — Organizzazione del menu admin [tecnica]

- [ ] A. Quattro gruppi: Gestione · Listino · Sito · Sistema *(consigliata)*
- [ ] B. Elenco piatto come oggi
- [ ] C. Menu a tendina

Note: 

### D10 — Contratto dei campi [tecnica]

- [ ] A. Approvarlo (con eventuali correzioni) come riferimento vincolante per i prompt 17–28 *(consigliata)*
- [ ] B. Decidere campo per campo durante le fasi

Note: 

### D11 — Segnalazione dei campi vuoti [tecnica]

- [ ] A. Tre livelli: frase sotto ogni campo facoltativo ("se lo lasci vuoto…"), indicatori negli elenchi ("manca EN", "senza foto"), checklist in dashboard *(consigliata)*
- [ ] B. Solo checklist in dashboard
- [ ] C. Rendere obbligatori quasi tutti i campi

Note: 

### D12 — Threat model delle nuove superfici [tecnica]

- [ ] A. Una pagina `docs/THREAT_MODEL.md`, scritta ora e aggiornata da ogni fase che aggiunge una superficie (casi d'abuso, impatto, controllo previsto e fase) *(consigliata)*
- [ ] B. Nessun documento

Note: 

### D13 — Pagine pubbliche quando il database non risponde [tecnica]

- [ ] A. Cache su file di impostazioni e contatti (riscritta a ogni salvataggio) + pagina 503 con i contatti e `Retry-After` quando il database non risponde *(consigliata)*
- [ ] B. Solo la pagina 503
- [ ] C. Niente

Note: 

### D14 — Due persone che modificano lo stesso contenuto [tecnica]

- [ ] A. Blocco ottimistico: ogni modulo porta la data dell'ultima modifica; se nel frattempo è cambiata compare "Qualcun altro ha modificato questa pagina: ricarica prima di salvare", senza perdere ciò che hai scritto *(consigliata)*
- [ ] B. L'ultimo salvataggio vince

Note: 

## Prompt 17 — Account admin gestibile senza SSH

File: `prompts/17_ADMIN_ACCOUNT_NO_SSH.md`

### D1 — Creare o recuperare l'account senza SSH [tecnica]

- [ ] A. `php bin/create-admin.php --print-sql` sul computer dello sviluppatore, poi import del SQL in phpMyAdmin *(consigliata)*
- [ ] B. Installer web una tantum che si disattiva dopo l'uso
- [ ] C. Chiedere all'assistenza dell'hosting

Note: 

### D2 — Cambio del nome utente dall'admin [tecnica]

- [ ] A. No, solo password *(consigliata)*
- [ ] B. Sì

Note: 

### D3 — Secondo fattore di accesso (codice da app, TOTP) [titolare]

- [ ] A. Non ora *(consigliata)*
- [ ] B. Sì
- [ ] C. Solo se l'admin sarà usato da una sola persona

Note: 

### D4 — Controllo delle password comuni [tecnica]

- [ ] A. La nuova password viene rifiutata se è in un piccolo elenco di password comuni (file nel repository), contiene il nome utente o il nome dell'agriturismo *(consigliata)*
- [ ] B. Solo la lunghezza minima di 12 caratteri

Note: 

### D5 — "Esci da tutti i dispositivi" [tecnica]

- [ ] A. Pulsante nella pagina Account (chiede la password attuale): chiude ogni altra sessione senza cambiare la password *(consigliata)*
- [ ] B. Non serve: basta cambiare la password

Note: 

### D6 — Segnalare accessi sospetti [titolare]

- [ ] A. Niente
- [ ] B. Mostrare in Account la data dell'ultimo accesso riuscito e il numero di tentativi falliti recenti (già in dashboard dal prompt 15) *(consigliata)*
- [ ] C. Anche un'email "nuovo accesso" quando l'IP non è mai stato visto

Note: 

## Prompt 18 — Impostazioni del sito

File: `prompts/18_SITE_SETTINGS.md`

### D1 — Numeri di telefono [titolare]

- [ ] A. Un solo campo
- [ ] B. Due campi: fisso e cellulare, entrambi facoltativi *(consigliata)*

Note: 

### D2 — Ragione sociale, P.IVA e REA [titolare]

- [ ] A. Nel piè di pagina di tutte le pagine, solo se compilati *(consigliata)*
- [ ] B. Solo nella pagina Contatti
- [ ] C. Non mostrarli

Note: 

### D3 — Link alle recensioni [titolare]

- [ ] A. Due campi facoltativi: TripAdvisor e Google *(consigliata)*
- [ ] B. Solo TripAdvisor (come il legacy)
- [ ] C. Nessun link

Note: 

### D4 — Posizione sulla mappa [titolare]

- [ ] A. Un link "Apri in Google Maps" (URL inserito dal titolare) *(consigliata)*
- [ ] B. Link + coordinate (apre anche Apple Maps e altre app)
- [ ] C. Mappa incorporata (iframe)

Note: 

### D5 — Orari di arrivo/partenza e regole della casa uguali per tutti gli appartamenti [titolare]

- [ ] A. Valori comuni nelle impostazioni; il singolo appartamento può sovrascriverli *(consigliata)*
- [ ] B. Restano solo per appartamento

Note: 

### D6 — Firma delle email dalle impostazioni [titolare]

- [ ] A. Sì: nome, telefoni ed email in fondo alle email arrivano dalle impostazioni *(consigliata)*
- [ ] B. No, resta nel codice

Note: 

### D7 — Avviso globale in cima a tutte le pagine [titolare]

- [ ] A. Sì: interruttore, testo IT/EN, data di fine facoltativa (dopo la data sparisce da solo) *(consigliata)*
- [ ] B. No

Note: 

### D8 — Orari in cui rispondete al telefono [titolare]

- [ ] A. Sì, un campo IT/EN accanto ai telefoni (es. "tutti i giorni 9–20") *(consigliata)*
- [ ] B. No

Note: 

### D9 — Instagram e Facebook [titolare]

- [ ] A. Due campi facoltativi, mostrati solo se compilati, come semplici link (nessun widget né script dei social) *(consigliata)*
- [ ] B. No

Note: 

## Prompt 19 — Libreria foto

File: `prompts/19_MEDIA_LIBRARY.md`

### D1 — Dimensione massima di una foto caricata [tecnica]

- [ ] A. 5 MB
- [ ] B. Fino al limite del server (il minore tra `upload_max_filesize` e `post_max_size`), con un massimo di 10 MB; l'admin mostra il valore reale e, se una foto lo supera, spiega come ridurla *(consigliata)*
- [ ] C. 20 MB

Note: 

### D2 — Se il server non ha GD (la libreria PHP per le immagini) [tecnica]

- [ ] A. Caricamento rifiutato con messaggio chiaro *(consigliata)*
- [ ] B. Salvare l'originale senza varianti

Note: 

### D3 — Dichiarazione di provenienza [titolare]

- [ ] A. Casella obbligatoria ("foto nostra o con licenza") + campo credito facoltativo *(consigliata)*
- [ ] B. Solo il campo credito
- [ ] C. Nulla

Note: 

### D4 — Prova con le foto del legacy, solo in locale [titolare]

- [ ] A. No
- [ ] B. Sì, a fine fase, caricandole dall'admin nel DB locale e segnando il credito "LEGACY – NON PUBBLICARE" *(consigliata)*
- [ ] C. Sì, su un ramo Git temporaneo con template modificati a mano

Note: 

### D5 — Spazio massimo per le foto [tecnica]

- [ ] A. 500 MB
- [ ] B. 1,5 GB, con avviso in admin all'80% *(consigliata)*
- [ ] C. Nessun limite

Note: 

### D6 — Formati particolari [tecnica]

- [ ] A. Rifiutati con un messaggio che spiega cosa fare (per l'iPhone: Impostazioni › Fotocamera › Formati › "Più compatibile", oppure condividere la foto come JPEG) *(consigliata)*
- [ ] B. Si convertono quelli che GD sa gestire (CMYK, 16 bit) e si rifiutano HEIC e animati
- [ ] C. Si accettano solo JPEG/PNG/WebP "normali" senza spiegare il motivo degli altri

Note: 

### D7 — Testo alternativo [tecnica]

- [ ] A. Italiano obbligatorio, 5–150 caratteri; rifiutati i valori generici (uguale al nome del file, "foto", "immagine", "IMG_1234") *(consigliata)*
- [ ] B. Solo la lunghezza minima

Note: 

## Prompt 20 — Foto, servizi e dettagli degli appartamenti

File: `prompts/20_APARTMENT_PHOTOS_SERVICES.md`

### D1 — Catalogo servizi del legacy [titolare]

- [ ] A. Partire da un catalogo vuoto
- [ ] B. Proporre il catalogo legacy (etichette IT/EN) inserito come non attivo, da confermare voce per voce *(consigliata)*
- [ ] C. Inserirlo attivo

Note: 

### D2 — Icone per i servizi [tecnica]

- [ ] A. Solo testo *(consigliata)*
- [ ] B. Piccole icone SVG disegnate nel progetto

Note: 

### D3 — Piano e accessibilità [titolare]

- [ ] A. Due campi: "piano" e "accessibile senza scale" sì/no *(consigliata)*
- [ ] B. Testo libero nelle regole della casa
- [ ] C. Niente

Note: 

### D4 — Descrizione breve per le schede [titolare]

- [ ] A. Sì, un campo IT/EN facoltativo (massimo ~160 caratteri) *(consigliata)*
- [ ] B. No, le schede mostrano solo i dati numerici

Note: 

### D5 — Tabella di confronto nella pagina "Appartamenti" [tecnica]

- [ ] A. Sì, generata dai dati: persone, camere, letti, animali, piano, servizi principali, prezzo da *(consigliata)*
- [ ] B. No

Note: 

### D6 — Eliminare un servizio assegnato a degli appartamenti [tecnica]

- [ ] A. Bloccata, con l'elenco degli appartamenti che lo usano *(consigliata)*
- [ ] B. Permessa, rimuove anche le assegnazioni

Note: 

## Prompt 21 — Pagine amministrabili

File: `prompts/21_PAGE_CONTENT.md`

### D1 — Foto per sezione [tecnica]

- [ ] A. Al massimo una foto per sezione *(consigliata)*
- [ ] B. Una piccola galleria per sezione

Note: 

### D2 — Link esterno in una sezione (es. sito delle terme o di un castello) [titolare]

- [ ] A. Nessun link
- [ ] B. Un link facoltativo per sezione (testo + indirizzo `https://`) *(consigliata)*

Note: 

### D3 — "Offerta del momento" in home [titolare]

- [ ] A. Una sezione normale che il titolare mostra o nasconde *(consigliata)*
- [ ] B. Sezione con data di fine visibilità automatica
- [ ] C. Nessuna offerta

Note: 

### D4 — Galleria (più foto insieme) [tecnica]

- [ ] A. No per ora: più foto = più sezioni *(consigliata)*
- [ ] B. Un secondo tipo di sezione "Galleria" (foto dalla libreria, ordinabili, senza slider)

Note: 

## Prompt 22 — Migrazione dei contenuti, legacy e contenuti nuovi

File: `prompts/22_CONTENT_MIGRATION_LEGACY.md`

### D1 — Come portare i testi attuali nel database [tecnica]

- [ ] A. Migrazione SQL idempotente (inserisce solo dove è vuoto) *(consigliata)*
- [ ] B. Comando `bin/` con simulazione e `--apply`

Note: 

### D2 — Testi del vecchio sito (presentazione, castelli, terme, Parco dello Stirone, Busseto) [titolare]

- [ ] A. Non usarli
- [ ] B. Inserirli come sezioni nascoste con titolo "[DA VERIFICARE]" *(consigliata)*
- [ ] C. Solo elencarli in un documento

Note: 

### D3 — Contenuti nuovi consigliati, da far scrivere al titolare [titolare]

- [ ] A. Creare sezioni nascoste e vuote con titolo e una traccia di cosa scrivere *(consigliata)*
- [ ] B. Solo un elenco per il titolare in `MISSING_DATA`
- [ ] C. Niente

Note: 

### D4 — Chiavi in `content/*.php` non più usate dopo il passaggio [tecnica]

- [ ] A. Rimuovere solo quelle dimostrate inutilizzate; tenere i testi di riserva *(consigliata)*
- [ ] B. Tenere tutto

Note: 

### D5 — Proposte non verificate [titolare]

- [ ] A. Restano nascoste e la checklist del prompt 25 le conta; dopo 30 giorni la voce diventa rossa *(consigliata)*
- [ ] B. Cancellate automaticamente dopo 30 giorni
- [ ] C. Nessun promemoria

Note: 

## Prompt 23 — Regole di prenotazione e strumenti per il listino

File: `prompts/23_BOOKING_RULES_PRICING.md`

### D1 — Chi conta nelle 2 persone [titolare]

- [ ] A. Persone totali (adulti + bambini), con almeno 1 adulto come oggi *(consigliata)*
- [ ] B. Almeno 2 adulti

Note: 

### D2 — Se qualcuno chiede per 1 persona [titolare]

- [ ] A. Non può inviare la richiesta per quell'appartamento: vede il motivo e l'invito a contattarvi *(consigliata)*
- [ ] B. Può inviarla ma paga come 2 persone

Note: 

### D3 — Dove si attiva la regola [tecnica]

- [ ] A. Per appartamento: casella "Regola attiva" + numero minimo *(consigliata)*
- [ ] B. Un unico interruttore e un unico numero per tutto il sito, nelle Impostazioni
- [ ] C. Interruttore e numero globali, con eccezioni per appartamento

Note: 

### D3b — Stato iniziale della regola [titolare]

- [ ] A. Attiva con minimo 2 su tutti e sei gli appartamenti *(consigliata)*
- [ ] B. Disattivata, la accende il titolare

Note: 

### D4 — Prenotazioni manuali dell'admin [titolare]

- [ ] A. Avviso, ma salvataggio permesso *(consigliata)*
- [ ] B. Blocco anche per l'admin

Note: 

### D5 — I neonati contano come persone (per minimo e capienza) [titolare]

- [ ] A. Sì, per ora tutti contano *(consigliata)*
- [ ] B. No, fascia 0–2 anni esclusa

Note: 

### D6 — Soggiorno minimo a cavallo di due stagioni [titolare]

- [ ] A. Lasciare com'è
- [ ] B. Minimo più alto tra i periodi attraversati *(consigliata)*
- [ ] C. Minimo dell'arrivo + periodi "forti" che impongono il minimo a chi li attraversa

Note: 

### D7 — Preavviso minimo per l'arrivo [titolare]

- [ ] A. Nessuno
- [ ] B. 1 giorno *(consigliata)*
- [ ] C. 2 giorni

Note: 

### D8 — Date alternative quando non c'è posto [titolare]

- [ ] A. No
- [ ] B. Stesso numero di notti, arrivo spostato fino a ±3 giorni *(consigliata)*
- [ ] C. Fino a ±7 giorni

Note: 

### D9 — Confronto prezzi alla conferma [titolare]

- [ ] A. Nessun avviso (come oggi)
- [ ] B. Avviso "preventivato X, listino attuale Y"; si conferma al prezzo preventivato *(consigliata)*
- [ ] C. Avviso + possibilità di confermare al prezzo nuovo

Note: 

### D10 — Prezzo indicativo ("Da X a notte") [titolare]

- [ ] A. Campo libero come oggi
- [ ] B. Avviso in admin se è più basso della tariffa minima attiva *(consigliata)*
- [ ] C. Calcolato automaticamente dal listino

Note: 

### D11 — Condizioni di cancellazione e pagamento [titolare]

- [ ] A. Testo nelle impostazioni, mostrato nel riepilogo prima dell'invio e nell'email di conferma *(consigliata)*
- [ ] B. No
- [ ] C. Anche coordinate bancarie (IBAN)

Note: 

### D12 — Tassa di soggiorno [titolare]

- [ ] A. Solo una nota di testo ("esclusa, da pagare in struttura"), se il Comune la applica *(consigliata)*
- [ ] B. Niente
- [ ] C. Calcolo automatico

Note: 

### D13 — Altre proposte (sì / no) [titolare]

- Modifica di una prenotazione confermata (date, ospiti, totale, note) con controllo di disponibilità sotto lock  
  [ ] Sì  [ ] No — consigliato: **Sì**
- Simulatore prezzi in admin (date + ospiti → preventivo per ogni appartamento)  
  [ ] Sì  [ ] No — consigliato: **Sì**
- Totale suggerito dal listino nelle prenotazioni manuali  
  [ ] Sì  [ ] No — consigliato: **Sì**
- Copia di periodi su altri appartamenti e di una stagione all'anno successivo  
  [ ] Sì  [ ] No — consigliato: **Sì**
- Blocco su tutti gli appartamenti in un'azione  
  [ ] Sì  [ ] No — consigliato: **Sì**
- Messaggio per i gruppi quando nessun appartamento basta  
  [ ] Sì  [ ] No — consigliato: **Sì**
- Giorni di arrivo vincolati (es. agosto solo sabato)  
  [ ] Sì  [ ] No  [ ] Rinvia — consigliato: **Rinvia**
- Giorno di pulizia obbligatorio tra due soggiorni  
  [ ] Sì  [ ] No — consigliato: **No**
- Supplementi scelti dal cliente (lettino, letto aggiunto)  
  [ ] Sì  [ ] Rinvia — consigliato: **Rinvia**
- Sconto per soggiorni lunghi  
  [ ] Sì  [ ] Rinvia — consigliato: **Rinvia**
- Ripristino di una prenotazione cancellata per errore (solo se le date sono ancora libere, stessa transazione e lock della conferma)  
  [ ] Sì  [ ] No — consigliato: **Sì**
- Richieste in attesa con data di arrivo passata: etichetta "scaduta" e blocco della conferma  
  [ ] Sì  [ ] No — consigliato: **Sì**

Note: 

## Prompt 24 — Operatività quotidiana e comunicazione con l'ospite

File: `prompts/24_ADMIN_OPERATIONS_COMMUNICATION.md`

### D1 — Email "richiesta ricevuta" al cliente [titolare]

- [ ] A. Sì, subito dopo l'invio, con riepilogo (riferimento, appartamento, date) e "non è ancora una prenotazione"; senza alcun testo scritto dall'ospite (né nome né note) *(consigliata)*
- [ ] B. No (come oggi)

Note: 

### D2 — Email di pre-arrivo [titolare]

- [ ] A. No
- [ ] B. Sì, 3 giorni prima dell'arrivo: indicazioni, orari, contatti *(consigliata)*
- [ ] C. Sì, 7 giorni prima

Note: 

### D3 — Campi facoltativi nel modulo di richiesta [titolare]

- "Orario di arrivo previsto"  
  [ ] Sì  [ ] No — consigliato: **Sì**
- "Come ci hai conosciuto?" (Google, Booking, passaparola, già ospite, altro)  
  [ ] Sì  [ ] No — consigliato: **Sì**

Note: 

### D4 — Richieste in attesa da troppo tempo (evidenziate in dashboard) [titolare]

- [ ] A. Dopo 24 ore
- [ ] B. Dopo 48 ore *(consigliata)*
- [ ] C. Dopo 72 ore

Note: 

### D5 — Statistiche [titolare]

- [ ] A. Rinviare
- [ ] B. Versione minima: richieste al mese, confermate/rifiutate, notti occupate per appartamento, canali (da D3) *(consigliata)*
- [ ] C. Grafici e confronti tra anni

Note: 

### D6 — Calendari iCal [titolare]

- [ ] A. Niente
- [ ] B. Solo esportazione: link privato da aggiungere al calendario del telefono
- [ ] C. Esportazione e import in sola lettura dei calendari di Booking/Novasol come blocchi *(consigliata)*

Note: 

### D7 — Altre proposte (sì / no) [titolare]

- Pagina "Arrivi e partenze" dei prossimi giorni, stampabile  
  [ ] Sì  [ ] No — consigliato: **Sì**
- Ricerca libera per riferimento `LV-…`, nome, email, telefono  
  [ ] Sì  [ ] No — consigliato: **Sì**
- Nel dettaglio richiesta: altre richieste in attesa sulle stesse date  
  [ ] Sì  [ ] No — consigliato: **Sì**
- Note interne su richieste e prenotazioni (mai visibili al cliente)  
  [ ] Sì  [ ] No — consigliato: **Sì**
- Esportazione e anonimizzazione dei dati di un cliente dall'admin  
  [ ] Sì  [ ] No — consigliato: **Sì**
- Ricerca per data ("chi c'è il 12 agosto?") oltre a quella per nome, riferimento, email e telefono  
  [ ] Sì  [ ] No — consigliato: **Sì**
- Riepilogo giornaliero via email al gestore  
  [ ] Sì  [ ] Rinvia — consigliato: **Rinvia**
- Email in HTML  
  [ ] Sì  [ ] No — consigliato: **No**

Note: 

### D8 — Messaggio personale nelle email all'ospite [titolare]

- [ ] A. Un testo IT/EN nelle Impostazioni, aggiunto in fondo all'email di conferma (e del pre-arrivo, se approvato) *(consigliata)*
- [ ] B. Modelli delle email modificabili interamente
- [ ] C. Niente

Note: 

### D9 — Email al titolare dopo molti accessi falliti [titolare]

- [ ] A. Sì: un'email se in un'ora ci sono più di 20 tentativi di accesso falliti (da qualsiasi indirizzo), al massimo una ogni ora *(consigliata)*
- [ ] B. No: basta il numero in dashboard

Note: 

## Prompt 25 — Usabilità dell'admin e rifiniture pubbliche

File: `prompts/25_ADMIN_UX_PUBLIC_POLISH.md`

### D1 — Admin da telefono [tecnica]

- [ ] A. Sotto i 600 px le tabelle diventano schede (una riga = una scheda) *(consigliata)*
- [ ] B. Tabelle con scorrimento orizzontale
- [ ] C. Niente

Note: 

### D2 — Appartamento scelto dalla sua pagina [titolare]

- [ ] A. Nel modulo è evidenziato e mostrato per primo; gli altri restano visibili *(consigliata)*
- [ ] B. Solo evidenziato
- [ ] C. Niente

Note: 

### D3 — Calendario "libero/occupato" pubblico [titolare]

- [ ] A. No: bastano le "date alternative" del prompt 23 e l'invito a scrivere *(consigliata)*
- [ ] B. Sì, per appartamento, solo i prossimi 3 mesi, senza nomi, solo appartamenti con richieste online
- [ ] C. Solo "prossime date libere" in testo

Note: 

### D4 — Altre proposte (sì / no) [titolare]

- Checklist "pronto per la pubblicazione" in dashboard (date senza tariffa e listino scoperto nei prossimi 12 mesi, appartamenti senza foto o testi, impostazioni vuote, privacy in bozza, traduzioni EN mancanti o da aggiornare, sezioni "[DA VERIFICARE]", foto con credito "LEGACY", richieste in attesa da troppo o scadute, avviso globale attivo da più di 60 giorni), con link dove si risolve; le voci di sistema (email, backup, aggiornamenti, coerenza) le aggiungono i prompt 26 e 27  
  [ ] Sì  [ ] No — consigliato: **Sì**
- Email dell'ospite ben visibile nel riepilogo ("Ti risponderemo a: …") con suggerimento sui domini mal digitati (gmial, hotmial, yahho…)  
  [ ] Sì  [ ] No — consigliato: **Sì**
- Frase sui tempi di risposta scritta dal titolare nelle Impostazioni (es. "Rispondiamo di solito entro 24 ore"), mostrata nella pagina "ricevuta" e nell'email di ricevuta  
  [ ] Sì  [ ] No — consigliato: **Sì**
- Pulsanti distruttivi distanti e di colore diverso in tutta l'admin; errori scritti come istruzioni ("Il server di posta ha rifiutato la password: controllala in Sistema › Email"); esempi sotto i campi; link "Vedi sul sito" dopo il salvataggio  
  [ ] Sì  [ ] No — consigliato: **Sì**
- Etichetta "inglese da aggiornare" quando il testo italiano è stato modificato dopo quello inglese  
  [ ] Sì  [ ] No — consigliato: **Sì**
- Tabella prezzi per stagione nella pagina appartamento, generata dal listino  
  [ ] Sì  [ ] No — consigliato: **Sì**
- Pagina 404 con link ad appartamenti, richiesta e contatti  
  [ ] Sì  [ ] No — consigliato: **Sì**
- Versione del sito nel piè di pagina dell'admin  
  [ ] Sì  [ ] No — consigliato: **Sì**
- `lastmod` nella sitemap  
  [ ] Sì  [ ] No — consigliato: **Sì**
- Icone per la schermata del telefono (apple-touch-icon, manifest)  
  [ ] Sì  [ ] Rinvia — consigliato: **Rinvia**
- Un po' di JavaScript facoltativo per aggiornare la data di partenza  
  [ ] Sì  [ ] No — consigliato: **No**

Note: 

### D5 — Ripristinare un testo precedente [tecnica]

- [ ] A. Nello Storico, per i campi di testo (pagine, sezioni, appartamenti, impostazioni): pulsante "Ripristina questo valore", con conferma *(consigliata)*
- [ ] B. Solo mostrare il valore vecchio, da copiare a mano
- [ ] C. Niente

Note: 

## Prompt 26 — Stato del sistema, email e manutenzione dall'admin

File: `prompts/26_SYSTEM_DIAGNOSTICS_EMAIL.md`

### D1 — Configurazione email (SMTP) [tecnica]

- [ ] A. Tutto resta in `.env`
- [ ] B. Tutto dall'admin: server, porta, cifratura, utente, mittente, nome, destinatario delle notifiche e password cifrata nel database (chiave derivata da `APP_SECRET` con HKDF e un contesto dedicato, versione della chiave salvata con il cifrato); se i valori sono in `.env` prevalgono e l'admin li mostra in sola lettura; salvataggio con riautenticazione; pulsante "Invia email di prova" *(consigliata)*
- [ ] C. Ibrida: tutto dall'admin tranne la password, che resta in `.env` (l'admin mostra solo "configurata / non configurata")

Note: 

### D2 — Attività pianificate (coda email, pre-arrivo, pulizie) [tecnica]

- [ ] A. Solo cron dell'hosting
- [ ] B. Esecuzione automatica "a ogni visita": al massimo ogni 15 minuti, decisa dalla data di un file (`storage/cache/last-run`, nessuna query in ogni pagina), dopo l'invio della risposta; cron facoltativo; pagina che mostra l'ultima esecuzione e l'esito, con "Esegui ora" *(consigliata)*
- [ ] C. Solo pulsante manuale

Note: 

### D3 — Registro errori nell'admin [tecnica]

- [ ] A. No, solo via FTP
- [ ] B. Gli ultimi 200 eventi, con filtro per livello; solo livello, data e messaggio fisso (mai contesto, percorsi, SQL, email, IP); lettura limitata agli ultimi 256 KB del file *(consigliata)*

Note: 

### D4 — Modalità manutenzione [tecnica]

- [ ] A. Solo file `storage/maintenance`
- [ ] B. Interruttore nell'admin e file `storage/maintenance` (entrambi efficaci); i visitatori vedono una pagina 503 IT/EN con `Retry-After`, l'admin resta accessibile; avviso in dashboard se è attiva da più di un'ora *(consigliata)*

Note: 

### D5 — Stato del sistema [tecnica]

- [ ] A. No
- [ ] B. Pagina con: versione del sito, PHP ed estensioni (`pdo_mysql`, `gd` con JPEG/WebP, `fileinfo`, `zip`, `sodium`/`openssl`, `exif`), limiti (upload, memoria, tempo), spazio usato da foto, log e database, orologio di PHP e del database, scrivibilità di `storage/sessions` e `storage/logs`, permessi di `.env`, migrazioni applicate e in attesa, stato email, ultima esecuzione delle attività, voci di `.env` configurate (mai i valori), controllo HTTP che `.env`, `storage/logs/`, `composer.json` e `migrations/` non siano raggiungibili dal web, rilevamento di header di proxy senza `TRUSTED_PROXIES` *(consigliata)*

Note: 

### D6 — Conservazione dei log e pulizie automatiche [tecnica]

- [ ] A. 30 giorni
- [ ] B. 90 giorni *(consigliata)*
- [ ] C. 180 giorni

Note: 

### D7 — Pagina "Coerenza dei dati" [tecnica]

- [ ] A. Pagina admin con l'elenco delle incoerenze, esecuzione nelle attività pianificate e banner rosso in dashboard se ne trova *(consigliata)*
- [ ] B. Solo il comando

Note: 

## Prompt 27 — Operazioni critiche dall'admin: aggiornamenti, backup e conservazione dei dati

File: `prompts/27_SYSTEM_CRITICAL_OPERATIONS.md`

### D1 — Aggiornamenti del database dall'admin [tecnica]

- [ ] A. Solo phpMyAdmin o riga di comando
- [ ] B. Pagina "Aggiornamenti": elenca le migrazioni in attesa (solo file `NNNN_nome.sql` già presenti in `migrations/`), verifica i checksum, chiede password admin, backup scaricato negli ultimi 30 minuti (o conferma esplicita "ho un backup di oggi") e la parola "AGGIORNA", attiva la manutenzione, ne applica una per richiesta, registra l'esito *(consigliata)*
- [ ] C. Applicazione automatica al primo accesso dopo un aggiornamento

Note: 

### D2 — Sito pubblico con aggiornamenti in attesa [tecnica]

- [ ] A. Il sito pubblico va in 503 automatico finché ci sono migrazioni in attesa; l'admin resta accessibile. Il controllo confronta il nome dell'ultima migrazione presente con un file di cache (`storage/cache/schema-version`): nessuna query in ogni pagina *(consigliata)*
- [ ] B. Solo un avviso in dashboard

Note: 

### D3 — Database più recente del codice [tecnica]

- [ ] A. Blocco: "Il database è più recente del codice: ricarica la versione corretta"; il sito pubblico va in 503 *(consigliata)*
- [ ] B. Solo un avviso

Note: 

### D4 — Contenuto del backup [tecnica]

- [ ] A. Solo il database
- [ ] B. Database + foto in zip a blocchi (ognuno di al massimo 200 MB) con l'elenco dei blocchi da scaricare; senza `ZipArchive` solo database e elenco dei file *(consigliata)*
- [ ] C. Database + foto in un unico zip

Note: 

### D5 — Sicurezza del backup [tecnica]

- [ ] A. Generato al volo, mai salvato sul server (tranne il file temporaneo dello zip, cancellato subito), con riautenticazione, limite di 3 all'ora, nome con la data, avviso "contiene dati personali: conservalo protetto", e registro dell'impronta SHA-256 (data, dimensione, hash: mai il contenuto) con cui verificare il file scaricato *(consigliata)*
- [ ] B. Come A, più zip cifrato con una password scelta al download (richiede libzip 1.2 o successivo)
- [ ] C. Senza registro e senza avvisi

Note: 

### D6 — Conservazione dei dati personali [titolare]

- [ ] A. Durata impostabile dall'admin (6–120 mesi, vuoto = nessuna pulizia), pulsante "Simula" che mostra quante richieste verrebbero anonimizzate, poi "Esegui" con riautenticazione e parola "ANONIMIZZA"; la pulizia automatica parte solo se la durata è impostata e una simulazione è stata confermata almeno una volta *(consigliata)*
- [ ] B. Solo pulsante manuale, mai automatico
- [ ] C. Resta `DATA_RETENTION_MONTHS` in `.env` e `bin/privacy.php`

Note: 

### D7 — Prova di ripristino [tecnica]

- [ ] A. Test automatico (backup generato → importato in un database vuoto → verifica di coerenza, conteggi identici, caratteri speciali e emoji intatti, marcatore finale presente) + procedura phpMyAdmin documentata e provata una volta in locale; la prova reale sull'hosting è nel prompt 32 *(consigliata)*
- [ ] B. Solo la procedura documentata

Note: 

## Prompt 28 — Hardening pre-release

File: `prompts/28_PRE_RELEASE_HARDENING.md`

### D1 — Versione minima di PHP [tecnica]

- [ ] A. PHP 8.2
- [ ] B. PHP 8.3 *(consigliata)*
- [ ] C. PHP 8.4

Note: 

### D2 — Versione minima del database [tecnica]

- [ ] A. MySQL 8.0.19+ oppure MariaDB 10.6+ *(consigliata)*
- [ ] B. Solo MariaDB 10.11+

Note: 

### D3 — Analisi statica (PHPStan, solo sviluppo) [tecnica]

- [ ] A. No
- [ ] B. Sì, livello 5, poi alzarlo gradualmente *(consigliata)*
- [ ] C. Livello massimo subito

Note: 

### D4 — Mutation testing (Infection, solo sviluppo) [tecnica]

- [ ] A. Non ora *(consigliata)*
- [ ] B. Sì, solo su `app/Domain`

Note: 

### D5 — Test automatici su GitHub a ogni push (CI) [titolare]

- [ ] A. Sì (GitHub Actions con MariaDB e MySQL) *(consigliata)*
- [ ] B. No

Note: 

### D6 — Cartella `legacy/` (decisione P4) [titolare]

- [ ] A. Eliminarla dal repository (resta nella cronologia Git); svuotare prima eventuali foto legacy caricate nel DB locale *(consigliata)*
- [ ] B. Tenerla, ma escluderla dal pacchetto di rilascio
- [ ] C. Tenerla com'è

Note: 

### D7 — Pacchetto di rilascio (sì / no) [tecnica]

- Script (solo sviluppo) che crea lo zip di rilascio: senza `legacy/`, `tests/`, `docker/`, `docs/`, `prompts/`, file per le AI, `.env`; con `vendor/` senza PHPUnit  
  [ ] Sì  [ ] No — consigliato: **Sì**

Note: 

### D8 — Unicità delle notti garantita dal database [tecnica]

- [ ] A. Non ora: lock e controllo di coerenza (prompt 15 e 26) bastano per sei appartamenti *(consigliata)*
- [ ] B. Tabella `booking_nights(apartment_id, night, booking_id)` con `UNIQUE(apartment_id, night)`, riempita nella stessa transazione di conferma, modifica e cancellazione

Note: 

## Prompt 29 — Aggiornamento della documentazione

File: `prompts/29_DOCUMENTATION_UPDATE.md`

### D1 — Manuale per il titolare [titolare]

- [ ] A. `docs/MANUALE_TITOLARE.md`, scritto per un non tecnico, stampabile *(consigliata)*
- [ ] B. Pagina "Aiuto" dentro l'admin
- [ ] C. Entrambi

Note: 

### D2 — Pacchetto di prompt dopo la pubblicazione [titolare]

- [ ] A. Dopo il prompt 32 si spostano in `docs/archive/` (restano nella cronologia Git); in `docs/` restano solo i documenti di consegna *(consigliata)*
- [ ] B. Restano dove sono
- [ ] C. Si eliminano

Note: 

## Prompt 30 — Aggiornamento della review finale

File: `prompts/30_FINAL_REVIEW_UPDATE.md`

### D1 — Prova generale del titolare [titolare]

- [ ] A. Sì: 1–2 ore in locale con dati di prova. Chi userà l'admin esegue una lista di compiti (inserire recapiti e dati aziendali, caricare 3 foto, assegnare servizi, scrivere una sezione e nasconderla, creare un listino, ricevere una richiesta dal sito e confermarla, cancellare una prenotazione, scaricare un backup, cambiare la password) e l'agente annota dove esita, sbaglia o non capisce *(consigliata)*
- [ ] B. No

Note: 

### D2 — Matrice "controllo → test" [tecnica]

- [ ] A. Tabella in `docs/FINAL_REVIEW.md`: per ogni controllo di sicurezza e per ogni finding chiuso, il test (o la procedura manuale) che lo dimostra *(consigliata)*
- [ ] B. Solo l'elenco dei finding

Note: 

## Prompt 31 — Preparazione pubblicazione, SENZA deploy (ex prompt 14)

File: `prompts/31_RELEASE_PREP_NO_DEPLOY.md`

### D1 — Backup in produzione [titolare]

- [ ] A. Solo i backup dell'hosting
- [ ] B. Procedura documentata: "Scarica backup" dall'admin (prompt 27) almeno ogni settimana e prima di ogni aggiornamento, copia su un supporto del titolare (disco o cloud personale) *(consigliata)*
- [ ] C. Backup automatico verso un servizio esterno

Note: 

### D2 — Monitoraggio esterno (avviso se il sito non risponde) [titolare]

- [ ] A. Sì, con un servizio gratuito che attiva il titolare *(consigliata)*
- [ ] B. No

Note: 

### D3 — Prova di ripristino sull'hosting [tecnica]

- [ ] A. Prevista nel prompt 32: il backup scaricato dall'admin si ripristina su un database di prova separato dell'hosting (non su quello reale) *(consigliata)*
- [ ] B. Solo la prova in locale

Note: 

### D4 — `security.txt` [tecnica]

- [ ] A. File `/.well-known/security.txt` con l'email del titolare per segnalazioni di sicurezza *(consigliata)*
- [ ] B. No

Note: 

## Prompt 32 — Verifica dopo la pubblicazione

File: `prompts/32_POST_DEPLOY_VERIFICATION.md`

### D1 — Cosa si prova sul sito reale [titolare]

- [ ] A. Sola lettura e prove che non lasciano dati: richieste di file sensibili, intestazioni, upload ostili rifiutati, richieste non valide; in più una sola richiesta di prova (nome "PROVA") e una sola foto di prova che il titolare elimina e anonimizza subito dopo *(consigliata)*
- [ ] B. Solo sola lettura: niente richiesta e niente foto
- [ ] C. Anche prove di carico o scansioni attive

Note: 

### D2 — Strumenti di terzi (securityheaders.com, SSL Labs, mail-tester.com, PageSpeed) [titolare]

- [ ] A. Li esegue l'utente e incolla i risultati; l'agente non invia l'indirizzo del sito a servizi di terzi *(consigliata)*
- [ ] B. L'agente li esegue

Note: 

### D3 — Ripristino del backup su un database di prova dell'hosting [tecnica]

- [ ] A. Il titolare scarica il backup dall'admin e lo importa in un secondo database dell'hosting (mai in quello reale), poi verifica richieste, prenotazioni e foto *(consigliata)*
- [ ] B. Si segna NOT RUN

Note: 
