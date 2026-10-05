# Prompt 13 — Review finale pre-consegna

## Obiettivo

Confrontare lo stato reale del repository con **ogni criterio di accettazione**.

## Metodo

Rileggi integralmente `docs/SPEC.md` e usa `docs/ACCEPTANCE_MATRIX.md`.

Per ogni criterio assegna:

- `PASS` — implementato e verificato;
- `PARTIAL` — incompleto o verifica insufficiente;
- `FAIL` — non soddisfatto;
- `BLOCKED` — richiede dato/accesso non disponibile.

Aggiungi evidenza concreta: file, test, comando o procedura manuale.

## Controlli prioritari

Pagamenti/dati carta; account ospite; pending vs confirmed; conferma admin; overlap e concorrenza; ricalcoli server-side; tariffe configurabili; manual booking; cancellazioni; SMTP; admin; audit log; IT/EN; tastiera/responsive; immagini; pagine appartamento; CSV; hosting condiviso; installabilità; servizi esterni.

## Correzioni

Solo bloccanti o alto impatto e contenute. Riesegui i test pertinenti dopo ogni fix.

## Output

Crea/aggiorna `docs/FINAL_REVIEW.md` con matrice, bug critici risolti, test finali, limitazioni, dati mancanti e operazioni ancora necessarie.

Aggiorna `SESSION_STATE` e `DELIVERY_CHECKLIST`. Non fare deploy.
