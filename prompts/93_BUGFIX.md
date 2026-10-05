# Prompt 93 — Bugfix focalizzato

## Input

Bug/segnalazione: [DESCRIVERE QUI]

## Metodo

1. riproduci il bug se possibile;
2. identifica causa radice;
3. verifica i requisiti SPEC coinvolti;
4. applica il fix minimo robusto;
5. aggiungi/regola un test di regressione quando appropriato;
6. esegui i test pertinenti;
7. controlla che non siano state introdotte modifiche estranee.

## Divieti

Non approfittare del bugfix per refactoring ampi. Non cambiare architettura senza necessità dimostrata.

## Output

Causa, fix, file, test/esito, rischi residui. Aggiorna `TEST_REPORT`, `TODO` e `SESSION_STATE` se il bug incide sullo stato del progetto.
