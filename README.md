# Agriturismo La Volta

Sito web dell'Agriturismo La Volta (Salsomaggiore Terme, PR): presentazione,
galleria fotografica, sistema di prenotazione appartamenti collegato a
Firebase e invio email tramite un piccolo server Node.

## Requisiti

- Node.js 18 o superiore
- Un account Google (per generare la App Password Gmail)
- Un progetto Firebase attivo (Auth + Firestore)

## 1. Installazione

```
npm install
```

## 2. Configurazione (obbligatoria prima di avviare)

Il progetto ha bisogno di **due** file `.env`, entrambi esclusi da git per
sicurezza. Copia i template e inserisci i tuoi valori:

```
cp .env.example .env
cp src/EmailStatus/.env.example src/EmailStatus/.env
```

- **`.env`** (root del progetto): chiavi Firebase. Le trovi su
  console.firebase.google.com → Impostazioni progetto → Le tue app.
- **`src/EmailStatus/.env`**: `GMAIL_USER` (il tuo indirizzo Gmail) e
  `GMAIL_APP_PASS`, una "App Password" generata da
  https://myaccount.google.com/apppasswords (richiede la verifica in due
  passaggi attiva). **Non è la password normale del tuo account Gmail.**

> ⚠️ **Da fare subito**: le credenziali che erano nel progetto originale
> (chiave Firebase e password app Gmail) erano state committate su git in
> chiaro. Ho tolto i file dal tracking, ma restano visibili nella cronologia
> git già esistente. Ti consiglio di **rigenerare la App Password Gmail**
> (le chiavi Firebase pubbliche sono meno critiche, ma vanno comunque
> protette con le Regole di Sicurezza Firestore lato console).

## 3. Avvio in sviluppo

Il sito e il server email sono due processi separati. Puoi avviarli insieme:

```
npm run dev:all
```

oppure in due terminali distinti:

```
npm run dev      # sito React, su http://localhost:5173
npm run server   # server email, su http://localhost:3001
```

## 4. Build di produzione

```
npm run build
npm run preview
```

I file pronti per il deploy vengono generati in `dist/`. Il server email
(`src/EmailStatus/EmailServer.js`) va invece ospitato separatamente (es. un
piccolo VPS, Render, Railway...) e la variabile `VITE_API_URL` nel `.env`
va aggiornata con il suo indirizzo reale prima del build. Sul server in
produzione va anche impostata la variabile `CORS_ORIGIN` con l'URL reale
del sito.

## Nota importante sul pagamento online

Il modulo di prenotazione raccoglie solo i dati di contatto del cliente
(nome, email, telefono), **non numeri di carta di credito**. In precedenza
il form salvava numero di carta, scadenza e CVV in chiaro su Firestore: è
stato rimosso perché non conforme agli standard PCI-DSS e a rischio legale.
Per accettare pagamenti reali con carta, integra un gestore certificato
come Stripe o PayPal: il numero di carta passa dal browser del cliente
direttamente al loro server, senza mai transitare dal tuo.

## Struttura del progetto

```
src/
├── App.jsx                     # routing principale
├── components/
│   ├── NavBar/                 # menu + configurazione Firebase
│   ├── Hero/                   # sezione iniziale
│   ├── ImageScroller/          # scroller immagini agriturismo
│   ├── GallerySlider/          # galleria fotografica
│   ├── Appartamenti/           # elenco e dettaglio appartamenti
│   ├── Booking/                # sistema di prenotazione (Firestore)
│   ├── Pagamenti/              # form dati di contatto per la prenotazione
│   ├── AuthPopup/              # login/registrazione (Firebase Auth)
│   ├── ContactUs/              # modulo contatti (invia email)
│   └── DoveSiamo/               # pagina "dove siamo"
└── EmailStatus/
    └── EmailServer.js          # mini-server Node per l'invio email
```

## Cosa è stato sistemato in questa revisione

- Chiavi Firebase e credenziali Gmail spostate da codice/git a variabili
  d'ambiente (`.env`, escluso da git)
- Rimosso il salvataggio in chiaro di numeri di carta di credito e CVV
- Corretto il link "Prenota" nel menu (puntava a una rotta inesistente)
- Corretto il popup di login/registrazione (non si apriva per un nome di
  prop sbagliato)
- Le email di conferma prenotazione ora includono i veri dati del cliente
- Rimosso un doppio meccanismo di prenotazione che entrava in conflitto
- URL del server email non più hardcoded, ora configurabile per la
  produzione
- Rimossi 3 file orfani mai utilizzati, con bug (variabili non definite,
  percorsi di importazione inesistenti)
- Pulizia generale: dipendenza fasulla nel `package.json`, file `.DS_Store`,
  link e favicon rotti
