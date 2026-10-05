# Prompt 05 — Motore tariffario configurabile

## Obiettivo

Implementare pricing server-side configurabile senza inventare importi o regole di business.

## Deve poter considerare almeno

- appartamento;
- intervallo date / stagione / periodo;
- adulti;
- bambini;
- animali;
- supplementi;
- soggiorno minimo se configurato.

La struttura deve poter essere estesa, ma senza costruire un motore di regole eccessivamente generico.

## Regole

- prezzi del vecchio sito non sono automaticamente corretti;
- prezzo mostrato nel browser non è definitivo;
- ricalcolo server-side prima del salvataggio della richiesta;
- fixture di test chiaramente separate dai dati reali;
- dati mancanti → `MISSING_DATA.md`.

## Implementa

- schema/configurazione coerenti con DB già definito;
- servizio/calcolatore deterministico e testabile;
- spiegazione/riepilogo prezzo comprensibile all'utente;
- validazioni di consistenza delle regole;
- integrazione con richiesta soggiorno lato server.

## Test

Crea fixture di test per periodi/stagioni, adulti, bambini, animali, supplementi e soggiorno minimo quando la struttura li supporta. Non etichettare fixture come prezzi reali.

Aggiorna `TEST_REPORT`, `TODO`, `MISSING_DATA`, `SESSION_STATE`.

## Stop condition

Fermati quando l'engine è configurabile, testabile e integrato server-side; non inventare il listino definitivo.
