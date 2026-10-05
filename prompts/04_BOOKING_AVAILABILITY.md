# Prompt 04 — Richieste, prenotazioni e disponibilità

## Obiettivo

Implementare la logica critica di booking/disponibilità lato server.

## Regole non negoziabili

- `[check_in, check_out)`;
- richiesta pubblica = `pending`;
- `confirmed` solo dopo azione admin;
- nessun overlap tra `confirmed` dello stesso appartamento;
- disponibilità ricontrollata nel punto critico;
- transazione + controllo concorrenza/locking appropriato;
- prenotazioni manuali e blocchi incidono sulla disponibilità;
- cancellazione libera le date;
- origine prenotazione coerente con la specifica;
- audit log per eventi rilevanti.

## Implementa

- validazione date;
- numero notti;
- query/servizio disponibilità;
- richiesta `pending`;
- conferma/rifiuto;
- prenotazione manuale;
- blocchi;
- cancellazione;
- audit log.

Non implementare il pricing dettagliato oltre alle interfacce necessarie: quello è nella fase 05.

## Test obbligatori

- date invalide;
- checkout <= check-in;
- numero notti;
- soggiorni consecutivi;
- overlap parziale/completo;
- blocchi;
- conferma concorrente;
- cancellazione e riapertura date;
- autorizzazione server-side delle azioni admin se già disponibile.

Aggiorna `TEST_REPORT`, `TODO`, `SESSION_STATE`.

## Stop condition

Non procedere al pricing/admin UI se il core non ha test riproducibili, salvo blocchi documentati.
