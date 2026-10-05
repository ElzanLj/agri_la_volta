# Prompt finale – Agriturismo La Volta

Specifiche operative per sviluppo, manutenzione e pubblicazione del nuovo sito

Agisci come senior full-stack engineer, UX designer e technical lead.

Devi trasformare e migliorare il progetto esistente dell’Agriturismo La Volta fino a ottenere un sito professionale, veloce, accessibile, sicuro, semplice da mantenere e pronto per essere pubblicato in produzione.

### Repository di partenza

`https://github.com/ElzanLj/agri_la_volta`

Il progetto sarà sviluppato e mantenuto internamente dalle persone che gestiscono l’agriturismo. Privilegia quindi semplicità, chiarezza, affidabilità e facilità di manutenzione rispetto a soluzioni architetturali sofisticate.

Non introdurre framework, servizi cloud, microservizi, container, code, database esterni o infrastrutture aggiuntive se non sono realmente necessarie.

## Obiettivo generale

Il sito deve:

- presentare l’agriturismo;
- presentare i singoli appartamenti;
- mostrare fotografie, servizi, descrizioni e prezzi;
- permettere di verificare la disponibilità indicativa;
- permettere al cliente di inviare una richiesta;
- calcolare correttamente il prezzo del soggiorno;
- permettere all’amministratore di accettare o rifiutare una richiesta;
- permettere di inserire manualmente prenotazioni provenienti da altri canali;
- gestire blocchi di disponibilità;
- inviare email;
- avere una semplice area amministrativa;
- funzionare bene su mobile e desktop;
- essere accessibile;
- essere ottimizzato per SEO e prestazioni;
- essere disponibile in italiano e inglese.
Il sito non deve gestire pagamenti online.

Gli ospiti non devono creare account e non devono effettuare login.

## 1. Hosting e infrastruttura

Il sito deve poter funzionare su un normale hosting Linux condiviso compatibile con PHP e MySQL/MariaDB.

La soluzione non deve richiedere necessariamente:

- VPS;
- Docker;
- Node.js persistente;
- Redis;
- accesso root;
- database cloud esterni;
- Supabase;
- Firebase;
- servizi server dedicati.
Utilizza dipendenze esterne soltanto quando portano un vantaggio concreto.

Il progetto non deve essere legato a un provider di hosting specifico.

### Dominio

Il dominio di produzione è:

`agriturismolavolta.com`

Il dominio è già gestito da un fornitore esterno.

Non:

- trasferire il dominio;
- cambiare nameserver;
- modificare DNS;
- modificare configurazioni del dominio;
- pubblicare il sito;
senza autorizzazione esplicita.

Il fornitore attuale può modificare i record web necessari per puntare il dominio al nuovo hosting.

### Email

L’indirizzo principale è:

`info@agriturismolavolta.com`

La posta elettronica è già gestita da un fornitore esterno.

Non modificare:

- MX;
- SPF;
- DKIM;
- DMARC;
- configurazioni della posta;
senza autorizzazione.

Le credenziali SMTP saranno fornite successivamente.

La configurazione SMTP deve avvenire tramite variabili d’ambiente e non deve essere inserita nel repository.

## 2. Architettura tecnica

Realizza il backend principalmente in PHP.

Utilizza MySQL o MariaDB per il database.

Non imporre un framework se non necessario.

È possibile utilizzare:

- Composer;
- librerie PHP mature;
- JavaScript moderno;
- CSS moderno;
- strumenti di build utilizzati soltanto durante lo sviluppo.
Il sito in produzione deve comunque funzionare normalmente su hosting condiviso.

Mantieni una separazione chiara tra:

- interfaccia;
- logica applicativa;
- accesso al database;
- validazione;
- configurazione.
Evita overengineering.

Il codice deve essere comprensibile e modificabile da altri sviluppatori senza dover conoscere un’architettura complessa.

## 3. Vincolo assoluto: nessun pagamento

Rimuovi completamente qualsiasi funzionalità di pagamento eventualmente presente nel progetto.

Rimuovi:

- componenti Pagamenti;
- numero di carta;
- data di scadenza;
- CVV/CVC;
- conferma pagamento;
- simulazioni di pagamento;
- Stripe;
- PayPal;
- altre integrazioni di pagamento;
- dipendenze utilizzate esclusivamente per pagamenti.
Non memorizzare mai dati di carta.

Se nel progetto esistente trovi codice o dati riconducibili a carte di credito:

- non leggerli inutilmente;
- non stamparli;
- non copiarli;
- non inserirli nei log;
- segnala la loro possibile presenza.
Non cancellare dati reali senza autorizzazione.

## 4. Appartamenti

Gli appartamenti sono:

- Margherita;
- Girasole;
- Rosa;
- Mimosa;
- Ciclamino;
- Viola.
Ogni appartamento deve avere una pagina pubblica dedicata e indicizzabile.

Prevedi almeno:

- nome;
- slug;
- descrizione italiana;
- descrizione inglese;
- fotografie;
- capienza;
- numero camere;
- numero letti;
- servizi;
- prezzo indicativo;
- eventuali supplementi;
- regole;
- check-in;
- check-out;
- stato attivo/non attivo.
Non inventare dati mancanti.

Quando una informazione non è disponibile:

- utilizza un placeholder chiaramente identificato;
- oppure predisponi il campo nel pannello amministrativo.
## 5. Prezzi

I prezzi attualmente presenti nel vecchio sito potrebbero non essere aggiornati.

Non considerarli automaticamente corretti.

Il sistema deve permettere all’amministratore di configurare e modificare i prezzi senza intervenire sul codice.

Il sistema tariffario deve poter considerare almeno:

- appartamento;
- intervallo di date;
- stagione o periodo;
- numero di adulti;
- numero di bambini;
- presenza o numero di animali;
- eventuali supplementi;
- eventuale soggiorno minimo, se configurato.
Predisponi una struttura sufficientemente flessibile da poter aggiungere altre regole in futuro.

Non inventare gli importi.

Se le regole tariffarie definitive non sono ancora state fornite, implementa la struttura e utilizza dati chiaramente marcati come temporanei.

## 6. Calcolo del soggiorno

Il cliente deve poter selezionare:

- check-in;
- check-out;
- numero di adulti;
- numero di bambini;
- eventuali animali.
Il sistema deve:

1. controllare le date;

2. calcolare il numero di notti;

3. identificare le tariffe applicabili;

4. applicare le eventuali variazioni per adulti, bambini e animali;

5. calcolare il totale;

6. mostrare un riepilogo chiaro.

Il prezzo visualizzato dal browser non deve essere considerato definitivo.

Prima di salvare una richiesta, il prezzo deve essere ricalcolato lato server.

## 7. Disponibilità

Gli intervalli devono essere trattati come:

`[check_in, check_out)`

Il giorno del checkout non deve quindi essere considerato occupato per la prenotazione successiva.

Esempio: una prenotazione dal 10 al 15 occupa le notti 10, 11, 12, 13 e 14. Un altro ospite può arrivare il giorno 15.

La disponibilità mostrata all’utente deve essere verificata nuovamente lato server.

Due prenotazioni confermate dello stesso appartamento non devono poter sovrapporsi.

Utilizza transazioni e un meccanismo appropriato di locking/controllo concorrenza.

Non affidarti esclusivamente al controllo eseguito dal browser.

## 8. Flusso pubblico di richiesta

Il flusso deve essere:

1. il cliente seleziona check-in e check-out;

2. inserisce adulti, bambini ed eventuali animali;

3. il sistema calcola prezzo e disponibilità;

4. vengono mostrati gli appartamenti disponibili;

5. il cliente seleziona un appartamento;

6. inserisce i propri dati;

7. visualizza il riepilogo;

8. accetta l’informativa privacy;

9. invia la richiesta;

10. la richiesta viene salvata nel database;

11. viene mostrato il messaggio “Richiesta ricevuta”.

Non mostrare mai “Prenotazione confermata” in questa fase.

Una richiesta diventa prenotazione soltanto dopo approvazione manuale dell’amministratore.

## 9. Dati cliente

Il modulo deve prevedere almeno:

- nome;
- cognome;
- email;
- telefono;
- eventuali note;
- consenso privacy obbligatorio.
Non creare account cliente.

Non chiedere password.

Non memorizzare più dati personali di quelli necessari.

Se il modulo produce un errore, conserva i dati già compilati quando tecnicamente possibile.

## 10. Stati

Utilizza almeno questi stati:

- pending;
- confirmed;
- rejected;
- cancelled.
Mantieni distinta una richiesta da una prenotazione confermata.

## 11. Prenotazioni provenienti da altri canali

L’amministratore deve poter inserire manualmente prenotazioni ricevute tramite:

- telefono;
- email;
- agenzie;
- Novasol;
- altri canali.
Prevedi un campo origine, ad esempio:

- website;
- phone;
- email;
- agency;
- novasol;
- other.
Le prenotazioni inserite manualmente devono influire normalmente sulla disponibilità.

## 12. Novasol e gestione esterna

Alcuni appartamenti possono essere gestiti da Novasol o da altre agenzie.

Le regole definitive non sono ancora disponibili.

Non implementare al momento integrazioni automatiche con Novasol.

Predisponi soltanto una struttura che permetta in futuro di indicare che un appartamento:

- è gestito direttamente;
- è gestito temporaneamente da un’agenzia;
- può essere reso disponibile manualmente dal pannello.
Non inventare regole sui giorni di disponibilità.

Questa logica verrà definita successivamente.

## 13. Booking, Airbnb e iCal

Gli appartamenti gestiti direttamente non sono attualmente collegati a Booking.com o Airbnb.

Non implementare ora:

- API Booking;
- API Airbnb;
- sincronizzazione automatica;
- import/export iCal;
a meno che durante l’analisi emerga una necessità concreta.

L’architettura deve comunque poter essere estesa in futuro.

## 14. Area amministrativa

Crea una sezione:

`/admin`

Non mostrarla nella navigazione pubblica.

È previsto un unico account amministratore condiviso.

Non è necessario identificare quale persona fisica abbia effettuato una modifica.

L’area admin deve permettere almeno di:

- effettuare login;
- effettuare logout;
- vedere le nuove richieste;
- filtrare per periodo;
- filtrare per appartamento;
- filtrare per stato;
- aprire una richiesta;
- confermare una richiesta;
- rifiutare una richiesta;
- creare manualmente una prenotazione;
- cancellare una prenotazione;
- creare blocchi di disponibilità;
- rimuovere blocchi;
- vedere un calendario semplice;
- modificare prezzi;
- modificare regole tariffarie;
- modificare informazioni degli appartamenti;
- esportare dati.
Non permettere registrazione pubblica di nuovi amministratori.

## 15. Storico delle modifiche

Non è necessario registrare chi abbia effettuato una modifica.

Registra però almeno:

- cosa è stato modificato;
- tipo di operazione;
- data e ora;
- valore precedente, quando utile;
- nuovo valore, quando utile;
- entità interessata.
Esempi:

- richiesta pending → confirmed;
- prenotazione confirmed → cancelled;
- prezzo modificato;
- blocco disponibilità creato;
- blocco disponibilità rimosso.
Lo storico deve essere utile per capire cosa è successo nel sistema.

## 16. Cancellazione prenotazione

Una prenotazione confermata può essere cancellata dall’amministratore.

Quando viene cancellata:

- cambia stato in cancelled;
- le date tornano disponibili;
- lo storico viene aggiornato.
Non inviare automaticamente l’email di cancellazione.

Prepara invece una bozza email modificabile.

L’amministratore deve poter:

1. leggere la bozza;

2. modificarla;

3. copiarla o inviarla quando previsto.

Nel caso di una modifica significativa a una prenotazione:

- annullare la prenotazione precedente;
- crearne una nuova.
Non è necessario costruire un sistema complesso di versioning delle prenotazioni.

## 17. Email

Utilizza SMTP configurabile.

Le credenziali verranno fornite successivamente.

Non inserire password o credenziali nel repository.

Utilizza variabili d’ambiente.

### Nuova richiesta

Email al gestore con:

- cliente;
- appartamento;
- date;
- ospiti;
- prezzo calcolato;
- contatti;
- note;
- link o riferimento alla richiesta.
### Conferma

Email al cliente quando una richiesta viene confermata.

### Rifiuto

Email al cliente quando una richiesta viene rifiutata.

### Cancellazione

Genera una bozza modificabile.

Non inviarla automaticamente.

## 18. Robustezza dell’invio email

Il database è la fonte principale dei dati.

Una richiesta deve essere salvata prima del tentativo di invio dell’email.

Se l’email fallisce:

- non perdere la richiesta;
- registra il fallimento;
- mostra un comportamento appropriato;
- permetti eventualmente un nuovo tentativo.
Non salvare password SMTP nei log.

Non registrare inutilmente dati personali nei log.

## 19. WhatsApp

Aggiungi un pulsante WhatsApp facilmente raggiungibile.

Non è necessaria una API WhatsApp.

Utilizza un normale link con messaggio precompilato.

Quando disponibili, includi automaticamente nel testo:

- appartamento;
- check-in;
- check-out;
- numero adulti;
- numero bambini.
Esempio:

`Buongiorno, vorrei informazioni sull’appartamento Mimosa dal 12/06 al 16/06 per 2 adulti e 1 bambino.`

Il testo deve poter essere modificato dall’utente prima dell’invio.

## 20. Multilingua

Il sito deve essere disponibile in:

- italiano;
- inglese.
Non utilizzare traduzione automatica runtime.

Predisponi contenuti separati IT/EN.

Le traduzioni definitive possono essere corrette manualmente.

Ogni pagina pubblica deve avere:

- URL coerenti;
- title;
- description;
- canonical;
- metadata appropriati alla lingua.
Predisponi correttamente hreflang, se appropriato.

## 21. Pagine pubbliche

Realizza almeno:

- /
- /agriturismo
- /appartamenti
- pagina singola appartamento;
- /dintorni
- /richiedi-disponibilita
- /contatti
- /privacy
- /cookie
- /admin
La struttura degli URL inglesi può essere definita in modo coerente durante l’implementazione.

Ogni appartamento deve avere una propria pagina indicizzabile.

## 22. Design e UX

Mantieni un’identità:

- rurale;
- elegante;
- autentica;
- semplice.
Utilizza:

- palette naturale;
- fotografie ampie;
- tipografia leggibile;
- spaziatura coerente;
- layout pulito;
- CTA chiare.
Mantieni facilmente raggiungibili:

- telefono;
- email;
- WhatsApp;
- richiesta disponibilità.
Evita:

- eccesso di caroselli;
- slider automatici aggressivi;
- popup inutili;
- alert();
- animazioni decorative non necessarie;
- componenti cliccabili poco riconoscibili;
- testo poco leggibile sopra le immagini.
## 23. Fotografie

Analizza le immagini già presenti nel progetto.

Alcune fotografie attuali potrebbero essere state recuperate da Google o da altre fonti.

Non presumere che siano utilizzabili legalmente.

Per ogni immagine:

- verifica la provenienza quando possibile;
- segnala quelle di origine incerta;
- non effettuare hotlink da Google, Booking, Tripadvisor o altri siti;
- non considerare automaticamente utilizzabile un’immagine solo perché pubblicamente accessibile online.
Le fotografie di provenienza incerta devono essere sostituite prima della pubblicazione con fotografie di proprietà dell’agriturismo o regolarmente utilizzabili.

Mantieni gli originali delle immagini corrette.

Genera versioni ottimizzate quando appropriato.

Utilizza:

- WebP e/o AVIF quando compatibile;
- dimensioni responsive;
- width e height;
- lazy loading per immagini fuori dalla prima schermata.
Ottimizza in particolare la hero image.

## 24. Analytics e cookie

Al lancio non installare strumenti di tracciamento non necessari.

Non installare automaticamente:

- Google Analytics;
- Meta Pixel;
- Hotjar;
- altri tracker marketing.
Mantieni l’architettura predisposta per aggiungerli in futuro.

Se non vengono utilizzati cookie non tecnici, non introdurre un cookie banner complesso senza necessità.

La privacy policy e la cookie policy devono comunque essere presenti.

Non presentare testi legali generici come consulenza legale definitiva.

Segnala quali testi devono essere verificati dal titolare o da un consulente.

## 25. Mappe

Se serve mostrare la posizione dell’agriturismo, preferisci inizialmente una soluzione semplice.

È sufficiente, se appropriato, un collegamento a Google Maps.

Non introdurre necessariamente Google Maps embedded o API Google Maps se non portano un vantaggio concreto.

## 26. Accessibilità

Punta almeno a WCAG 2.2 AA.

Implementa:

- HTML semantico;
- header;
- nav;
- main;
- footer;
- link “Salta al contenuto”;
- navigazione da tastiera;
- focus visibile;
- label reali;
- messaggi di errore associati ai campi;
- annunci accessibili per operazioni asincrone;
- menu mobile accessibile;
- aria-expanded dove necessario;
- testi alternativi;
- contrasto sufficiente;
- target touch adeguati;
- supporto a prefers-reduced-motion.
Non aggiungere ARIA inutile dove HTML semantico è sufficiente.

## 27. SEO

Implementa almeno:

- lingua pagina;
- title unici;
- description uniche;
- canonical;
- Open Graph;
- favicon;
- robots.txt;
- sitemap;
- breadcrumb;
- URL leggibili;
- 404;
- gestione degli errori;
- rendering indicizzabile.
Quando appropriato aggiungi dati strutturati Schema.org per:

- agriturismo / lodging;
- appartamenti.
Mantieni coerenti:

- nome;
- indirizzo;
- telefono;
- email.
Non inventare informazioni aziendali mancanti.

## 28. Sicurezza

Implementa le normali misure di sicurezza necessarie per un’applicazione PHP pubblica.

In particolare:

- query parametrizzate;
- protezione da SQL injection;
- escaping output;
- validazione server-side;
- protezione CSRF per operazioni sensibili;
- sessioni sicure;
- cookie HttpOnly;
- cookie Secure in HTTPS;
- configurazione SameSite appropriata;
- hashing sicuro della password amministratore;
- rate limiting dove necessario;
- limitazione dimensione richieste;
- sanitizzazione appropriata;
- controllo degli accessi admin lato server.
Non affidarti a controlli JavaScript per la sicurezza.

## 29. Antispam

Proteggi i moduli pubblici dallo spam.

Preferisci inizialmente sistemi semplici e poco invasivi.

Puoi utilizzare:

- honeypot;
- rate limiting;
- controlli temporali;
- CAPTCHA/Turnstile soltanto se realmente necessario.
Non aggiungere dipendenze esterne senza una ragione concreta.

## 30. Database

Prevedi almeno strutture equivalenti a:

- apartments;
- booking_requests;
- bookings;
- availability_blocks;
- seasonal_rates;
- admin;
- audit_log.
Aggiungi le tabelle necessarie per:

- regole prezzi;
- traduzioni;
- eventuali supplementi;
- retry email;
soltanto se realmente utili.

Utilizza migrazioni SQL versionate.

Non salvare prenotazioni come array o JSON dentro il record dell’appartamento quando devono essere interrogate separatamente.

## 31. Esportazione dati

L’amministratore deve poter esportare:

- richieste;
- prenotazioni.
Utilizza almeno CSV.

Non è necessario generare file Excel nativi se il CSV risolve adeguatamente il problema.

L’esportazione deve poter essere aperta in:

- Excel;
- LibreOffice;
- Google Sheets.
Prevedi filtri per intervallo di date se semplice da implementare.

## 32. Privacy

Prevedi:

- privacy policy;
- cookie policy;
- informativa breve nei moduli;
- consenso privacy obbligatorio;
- eventuale consenso marketing separato e facoltativo se verrà utilizzato;
- possibilità tecnica di cancellare dati;
- possibilità tecnica di esportare dati;
- conservazione ragionevole.
Non utilizzare dati personali nei log se non necessario.

## 33. Prestazioni

Ottimizza il sito per connessioni mobili.

Evita:

- JavaScript eccessivo;
- librerie molto pesanti;
- immagini enormi;
- font inutili;
- dipendenze duplicate;
- codice morto;
- asset inutilizzati.
Le pagine principali devono caricarsi rapidamente anche su smartphone.

## 34. Codice

Mantieni:

- funzioni semplici;
- file di dimensioni ragionevoli;
- nomi chiari;
- responsabilità separate;
- configurazione centralizzata;
- commenti soltanto dove utili.
Non introdurre pattern architetturali complessi senza necessità.

Preferisci codice leggibile rispetto a astrazioni sofisticate.

## 35. Configurazione

Crea un file di esempio, ad esempio:

`.env.example`

senza credenziali reali.

Prevedi almeno configurazione per:

- database;
- URL produzione;
- SMTP host;
- SMTP porta;
- SMTP username;
- SMTP password;
- mittente email;
- numero WhatsApp;
- ambiente produzione/sviluppo.
Non inserire segreti nel repository.

## 36. Test

Aggiungi test appropriati almeno per le parti critiche.

Verifica:

- date non valide;
- checkout precedente al check-in;
- numero notti;
- soggiorni consecutivi;
- sovrapposizioni parziali;
- sovrapposizioni complete;
- blocchi manuali;
- conferma concorrente della stessa disponibilità;
- prezzi stagionali;
- variazioni adulti;
- variazioni bambini;
- animali;
- autorizzazione admin;
- richieste pubbliche;
- fallimento email;
- validazione form;
- cancellazione;
- export CSV.
Verifica manualmente anche:

- mobile;
- desktop;
- tastiera;
- principali pagine;
- italiano;
- inglese.
Non dichiarare superato un test che non è stato realmente eseguito.

## 37. Processo di lavoro

Prima di modificare il progetto:

1. ispeziona repository;

2. analizza dipendenze;

3. analizza struttura;

4. analizza immagini;

5. individua codice morto;

6. individua eventuali sistemi di pagamento;

7. identifica configurazioni e servizi esterni;

8. verifica eventuali dati reali.

Poi presenta un breve piano.

Implementa progressivamente.

Non riscrivere componenti funzionanti soltanto per uniformarli a una preferenza personale.

Mantieni quanto possibile:

- contenuti utili;
- identità;
- fotografie utilizzabili;
- informazioni reali.
Dopo ogni fase esegui i controlli pertinenti.

## 38. Cose da non fare senza autorizzazione

Non:

- pubblicare il sito;
- modificare DNS;
- trasferire dominio;
- modificare email;
- modificare provider email;
- eliminare database reali;
- cancellare prenotazioni reali;
- modificare servizi esterni;
- creare account cloud;
- acquistare servizi;
- attivare integrazioni a pagamento.
Se qualcosa richiede accesso a servizi esterni, prepara istruzioni e attendi autorizzazione.

## 39. Dati ancora da completare

Non inventare le informazioni che ancora mancano.

Mantieni chiaramente configurabili:

- prezzi definitivi;
- periodi stagionali;
- regole adulti;
- regole bambini;
- supplementi animali;
- eventuali soggiorni minimi;
- appartamenti gestiti da Novasol;
- regole Novasol;
- testi mancanti;
- traduzioni definitive;
- fotografie sostitutive;
- parametri SMTP;
- eventuali informazioni legali.
Utilizza placeholder chiaramente identificati quando necessario.

## 40. Consegna finale

Al termine fornisci:

1. riepilogo delle modifiche;

2. architettura finale;

3. struttura del progetto;

4. schema database;

5. migrazioni SQL;

6. configurazioni necessarie;

7. .env.example;

8. istruzioni per sviluppo locale;

9. istruzioni per installazione su hosting condiviso;

10. procedura per importare il database;

11. procedura per configurare SMTP;

12. procedura per creare/modificare l’admin;

13. procedura di backup;

14. procedura di ripristino;

15. procedura export CSV;

16. elenco dei test eseguiti;

17. risultati dei test;

18. limitazioni residue;

19. elenco delle informazioni mancanti;

20. checklist per la pubblicazione.

La documentazione deve permettere a un’altra persona di installare il progetto senza dover ricostruire mentalmente come funziona.

## 41. Criteri di accettazione

Il progetto è considerato pronto soltanto se:

- non contiene sistemi di pagamento;
- non contiene dati carta;
- gli ospiti non devono registrarsi;
- una richiesta non viene presentata come prenotazione confermata;
- soltanto l’amministratore può confermare;
- due prenotazioni confermate dello stesso appartamento non possono sovrapporsi;
- la disponibilità viene ricontrollata lato server;
- il prezzo viene calcolato lato server;
- le tariffe possono essere modificate senza cambiare il codice;
- adulti, bambini e animali possono influire sul prezzo;
- prenotazioni esterne possono essere inserite manualmente;
- una prenotazione cancellata libera le date;
- la cancellazione genera una bozza email modificabile;
- una richiesta resta salvata anche se SMTP fallisce;
- l’area admin è protetta;
- esiste uno storico delle modifiche;
- il sito funziona in italiano e inglese;
- il sito è utilizzabile da tastiera;
- il sito è responsive;
- le immagini sono ottimizzate;
- le immagini di provenienza dubbia vengono segnalate;
- ogni appartamento ha una pagina indicizzabile;
- il CSV è esportabile;
- il progetto può funzionare su normale hosting Linux condiviso;
- non dipende obbligatoriamente da un provider specifico;
- README e configurazione permettono di installarlo altrove;
- nessun servizio esterno è stato modificato senza autorizzazione.
## Principio guida finale

Quando esistono più soluzioni tecniche valide, scegli quella:

1. più semplice;

2. più affidabile;

3. più sicura;

4. più economica da mantenere;

5. più facile da comprendere;

6. compatibile con hosting condiviso;

7. con meno dipendenze esterne.

Non costruire funzionalità che non servono al funzionamento reale dell’Agriturismo La Volta.
