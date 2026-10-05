# Prompt 92 — Code review mirata

## Scope

Revisiona esclusivamente le modifiche indicate dall'utente o il diff corrente. Non riscrivere il progetto.

## Priorità finding

1. perdita/corruzione dati;
2. doppie prenotazioni/concorrenza;
3. prezzi/disponibilità errati;
4. bypass admin/sicurezza/privacy;
5. incompatibilità con hosting condiviso;
6. regressioni funzionali;
7. manutenibilità concreta;
8. stile solo se crea un problema reale.

## Procedura

- leggi `AGENTS.md` e sezioni SPEC pertinenti;
- ispeziona diff e test;
- verifica finding contro codice reale;
- per ogni finding: severità, file/riga, scenario, perché viola requisito, fix minimo;
- evita finding teorici non dimostrati.

Non modificare codice salvo richiesta esplicita. Se richiesto di correggere, applica fix minimi e riesegui test pertinenti.
