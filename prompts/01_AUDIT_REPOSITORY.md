# Prompt 01 — Audit completo del repository

Prima di iniziare esegui il bootstrap e leggi `AGENTS.md` + `docs/SPEC.md`.

## Obiettivo

Produrre una baseline affidabile **prima** di migrare o implementare. Non rifare l'architettura in questa fase.

## Analizza

1. struttura e entry point;
2. linguaggi/framework/package manager/dipendenze;
3. script build/test/lint/dev;
4. configurazioni e environment;
5. database/servizi esterni;
6. pagamenti o riferimenti a carte;
7. autenticazione;
8. prenotazioni/disponibilità/prezzi esistenti;
9. email/SMTP/API;
10. immagini: percorso, dimensioni, duplicati, hotlink e provenienza incerta;
11. codice morto/file duplicati/dipendenze inutilizzate apparenti;
12. possibili dati reali/segretI senza esporli;
13. compatibilità attuale con hosting Linux condiviso PHP + MySQL/MariaDB;
14. componenti, CSS, contenuti, immagini e logiche da preservare;
15. gap rispetto a `docs/SPEC.md`.

## Comandi

Se possibile e sicuro, esegui realmente installazione prevista dal progetto, build, test/lint esistenti e avvio locale minimo. Non cambiare stack solo per far passare l'audit.

Registra i comandi verificati in `docs/COMMANDS.md`.

## Output file

Crea `docs/AUDIT.md` con:

- executive summary;
- stack e struttura;
- comandi eseguiti e risultato;
- cosa preservare;
- cosa rimuovere/migrare e perché;
- pagamenti/dati sensibili individuati senza esposizione;
- servizi esterni;
- immagini dubbie;
- gap funzionali/tecnici;
- proposta di architettura minima;
- strategia di migrazione incrementale;
- rischi e blocchi;
- stima relativa per fasi: `S/M/L`, senza inventare ore precise se non supportate.

Aggiorna `TODO`, `MISSING_DATA`, `DECISIONS` solo con evidenze reali. Aggiorna `SESSION_STATE` indicando che l'audit è completato o bloccato.

## Divieti

- niente migrazione applicativa;
- niente nuove feature;
- niente rimozioni massive;
- niente aggiornamenti dipendenze non necessari;
- niente deploy/servizi esterni.

## Stop condition

Fermati dopo l'audit. Riporta principali risultati, rischi, file creati/modificati e proposta per la fase successiva.
