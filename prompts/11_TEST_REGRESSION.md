# Prompt 11 — Test completo, regressioni e bugfix

## Obiettivo

Portare il progetto a uno stato verificato. Non aggiungere feature decorative.

## Procedura

1. confronta test esistenti con `docs/SPEC.md`;
2. aggiungi solo i test necessari alla logica critica;
3. esegui realmente la suite;
4. correggi prima doppie prenotazioni, prezzo errato, perdita richieste, accessi admin non autorizzati, esposizione dati;
5. riesegui dopo i fix;
6. esegui verifiche manuali richieste;
7. aggiorna `TEST_REPORT` con `PASS/FAIL/NOT RUN` ed evidenza.

## Copertura minima

Date invalide; checkout <= check-in; notti; soggiorni consecutivi; overlap parziale/completo; blocchi; concorrenza; pricing; adulti/bambini/animali; admin auth; richieste pubbliche; fallimento email; form; cancellazione; CSV; mobile; desktop; tastiera; pagine principali; IT; EN.

## Output finale

Separa chiaramente:

- bug risolti;
- PASS;
- FAIL;
- NOT RUN + motivo;
- rischi residui.

Aggiorna `TODO`, `SESSION_STATE`, `ACCEPTANCE_MATRIX` solo dove l'evidenza è sufficiente.
