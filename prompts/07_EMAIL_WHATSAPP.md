# Prompt 07 — Email, robustezza SMTP e WhatsApp

## Obiettivo

Implementare i flussi di comunicazione senza rendere SMTP una dipendenza della persistenza.

## Regole

- database prima, email dopo;
- errore SMTP non perde richiesta/prenotazione;
- credenziali via environment;
- niente password/PII inutili nei log;
- cancellazione = bozza modificabile, non invio automatico;
- WhatsApp = normale link, nessuna API richiesta.

## Implementa

- configurazione SMTP;
- email al gestore per nuova richiesta con i dati previsti;
- email cliente conferma;
- email cliente rifiuto;
- bozza cancellazione;
- registrazione stato/fallimento;
- retry manuale o semplice solo se utile e compatibile con hosting condiviso;
- link WhatsApp con testo precompilato e modificabile, includendo dati disponibili previsti dalla specifica.

## Test

Simula/verifica errore SMTP e dimostra che il record rimane salvato. Se non ci sono credenziali reali, usa modalità test/dev appropriata e registra il limite.

Aggiorna `TEST_REPORT`, `TODO`, `MISSING_DATA`, `SESSION_STATE`.

## Stop condition

Fermati quando i flussi sono verificati o i blocchi SMTP sono chiaramente documentati.
