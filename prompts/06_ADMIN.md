# Prompt 06 — Area amministrativa

## Obiettivo

Creare `/admin` semplice, sicura e operativa, riusando i servizi server-side già testati.

## Funzioni minime

- login/logout;
- nuove richieste;
- filtri periodo/appartamento/stato;
- dettaglio richiesta;
- conferma/rifiuto;
- prenotazione manuale con origine;
- cancellazione;
- blocchi disponibilità;
- calendario semplice;
- modifica appartamenti;
- modifica prezzi/regole;
- export CSV richieste/prenotazioni;
- visualizzazione storico modifiche.

## Sicurezza

Ogni operazione sensibile deve essere autorizzata lato server e protetta in modo appropriato; includi CSRF, sessioni sicure, validazione ed escaping.

## UX

Preferisci tabelle/form/filtri leggibili. Nessuna registrazione pubblica admin. Non esporre `/admin` nella navigazione pubblica.

## Verifica

Login/logout, accesso non autorizzato, conferma/rifiuto, manual booking, blocchi, cancellazione, modifica prezzi e CSV.

Aggiorna `TEST_REPORT`, `TODO`, `SESSION_STATE`.

## Stop condition

Fermati con elenco delle azioni admin realmente verificate e di quelle incomplete/bloccate.
