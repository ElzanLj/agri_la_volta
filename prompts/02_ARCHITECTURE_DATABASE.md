# Prompt 02 — Architettura minima e database

Prerequisito: `docs/AUDIT.md` completato e revisionato.

## Obiettivo

Definire e implementare le fondamenta architetturali minime compatibili con PHP, MySQL/MariaDB e hosting condiviso, preservando ciò che l'audit ha classificato come utile.

## Prima di modificare

- Leggi `AUDIT`, `SPEC`, `DECISIONS`, `MISSING_DATA`.
- Controlla diff Git e codice esistente.
- Proponi una struttura directory concreta e spiega in poche righe perché è la più semplice sufficiente.
- Evidenzia eventuali parti React/Vite o altre tecnologie da mantenere solo come build-time oppure da migrare; non decidere per preferenza estetica.

## Implementa

- configurazione centralizzata;
- `.env.example` senza segreti;
- accesso MySQL/MariaDB sicuro;
- migrazioni SQL versionate;
- schema equivalente a `apartments`, `booking_requests`, `bookings`, `availability_blocks`, `seasonal_rates`, `admin`, `audit_log` e sole tabelle aggiuntive motivate;
- indici/vincoli ragionevoli;
- routing/error handling compatibile con hosting condiviso;
- bootstrap autenticazione admin minimo, senza costruire tutto il pannello;
- meccanismo di configurazione per produzione/sviluppo.

## Database

Non inserire prezzi/regole/dati aziendali inventati. Fixture e test data devono essere chiaramente separati dalla produzione.

## Verifica minima

- DB creato da zero;
- migrazioni applicate;
- connessione app→DB;
- rollback/strategia di ripristino documentata se disponibile/appropriata;
- login/logout minimo se implementato;
- segreti esclusi dal tracking.

Aggiorna `DECISIONS`, `COMMANDS`, `TODO`, `TEST_REPORT`, `SESSION_STATE`.

## Stop condition

Fermati quando le fondamenta sono installabili e verificabili. Non implementare ancora l'intero booking/admin/frontend.
