# Prompt 03 — Migrazione controllata delle fondamenta

## Obiettivo

Portare il progetto dalla struttura di partenza alla nuova baseline architetturale **senza una riscrittura indiscriminata**.

## Regole

- Preserva contenuti, CSS, componenti, immagini e logiche utili identificati dall'audit.
- Rimuovi dipendenze/servizi incompatibili (es. Firebase) solo quando la sostituzione è pronta e verificata.
- Se esiste codice di pagamento, rimuovilo in sicurezza senza leggere/esporre dati carta.
- Mantieni il progetto eseguibile il più spesso possibile durante la migrazione.
- Node/Vite può esistere come strumento di sviluppo/build solo se la produzione finale non richiede Node persistente.

## Attività

1. applica la struttura definita nella fase 02;
2. collega configurazione, routing e DB;
3. migra/preserva asset e layout utili;
4. sostituisci accessi a servizi esterni non ammessi con la nuova struttura;
5. elimina codice morto solo quando è dimostrato non necessario;
6. aggiorna dipendenze solo se necessarie per compatibilità/sicurezza/funzionalità della migrazione.

## Verifica

Esegui build/dev/test pertinenti prima e dopo. Documenta regressioni visibili o funzionali introdotte.

## Stop condition

La baseline deve avviarsi, usare la nuova configurazione/DB prevista e non dipendere obbligatoriamente dai servizi incompatibili rimossi. Aggiorna stato e fermati prima di implementare la logica booking completa.
