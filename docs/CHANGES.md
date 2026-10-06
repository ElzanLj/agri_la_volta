# Riepilogo delle modifiche

Dalla situazione di partenza (una SPA React con Firebase, pagamenti simulati e un'email con credenziali nel repository) al sito PHP + MySQL attuale. Lavoro svolto **per fasi**, ognuna con piano approvato, test e commit. Hash dei commit tra parentesi; il dettaglio dei risultati è in `docs/TEST_REPORT.md`, le ragioni in `docs/DECISIONS.md`.

## Punto di partenza

SPA React/Vite con Firebase (dati e login), un finto flusso di pagamento che salvava campi carta su Firestore, un server Node per le email con una **App Password Gmail committata**, fotografie di provenienza incerta (alcune in hotlink da siti terzi), nessun test, nessun controllo di disponibilità sul server.

## Fasi

| Fase | Contenuto | Commit |
|---|---|---|
| 0 — Audit | rischi, dati reali esposti, immagini, dipendenze, decisioni P1–P7 (`docs/AUDIT.md`); la credenziale Gmail è stata revocata dal titolare | `556a2be` |
| 1 — Fondamenta | PHP senza framework, Docker solo per lo sviluppo, schema MySQL/MariaDB con migrazioni, router, login admin | `3a6762f` |
| 1b — Pulizia | applicazione React spostata in `legacy/`, rimossi Firebase, pagamenti e server email legacy | `d8f3865` |
| 2A — Prenotazioni | richieste `pending`, conferma solo admin, intervalli `[check_in, check_out)`, blocchi, cancellazione, nessuna sovrapposizione con blocco per appartamento e test con processi concorrenti | `16ced96` |
| 2B — Prezzi | motore tariffario configurabile (stagioni, adulti, bambini, animali, supplementi, soggiorno minimo), calcolo sempre sul server, "prezzo da confermare" senza listino | `e76dcea` |
| 3 — Area admin | pagine di gestione con guardie di prefisso, CSRF, sessioni sicure, calendario, listino, storico, export CSV; test di sicurezza via HTTP reale | `521824c` |
| 4 — Email e WhatsApp | coda email transazionale, SMTP resiliente, bozza di cancellazione, link WhatsApp; server SMTP finto con 10 scenari di guasto | `3010439` |
| 5 — Sito pubblico | pagine IT/EN, pagina di ogni appartamento, flusso di richiesta a passi senza JavaScript, token firmato senza cookie, antispam | `d0451eb` |
| 6 — SEO e accessibilità | robots, sitemap, Open Graph, breadcrumb, dati strutturati, contrasto verificato da test, immagini responsive predisposte | `c90d6d6` |
| 7 — Sicurezza e privacy | revisione, intestazioni, hash IP con chiave, limite del corpo, esportazione e anonimizzazione dei dati | `95229a8` |
| 8 — Test e regressioni (prompt 11) | percorso completo end-to-end, test di ambito (niente pagamenti/account), matrice di accettazione, lista di prove manuali | `6c30551` |
| 8 — Documentazione (prompt 12) | README, architettura, installazione, operazioni, riepilogo, checklist di consegna | commit successivo |

## Cosa è cambiato per chi usa il sito

- Gli ospiti **non si registrano e non pagano**: inviano una richiesta di disponibilità e ricevono la risposta del gestore.
- La **disponibilità e il prezzo sono decisi dal server**; il browser non può influenzarli.
- Il gestore ha **un'area unica** (`/admin`) per richieste, prenotazioni da qualsiasi canale, blocchi, calendario, appartamenti, listino, email, storico ed esportazioni.
- Una **richiesta non è mai una prenotazione** finché il gestore non la conferma; una conferma non può sovrapporsi a un'altra.
- Un **errore di posta non perde nulla**: tutto è salvato, l'invio si ripete e si controlla da *Admin → Email*.

## Rimosso o sostituito

Firebase (dati, login), il flusso di pagamento e ogni campo carta, il server Node per le email, le dipendenze React/Vite dal sito servito (restano solo in `legacy/`, da eliminare), gli hotlink di immagini, i prezzi e le formule del legacy (non erano dati validi).

## Numeri (2026-10-06)

763 test automatici PASS (337 unit, 237 integrazione, 178 HTTP, 11 concorrenza), eseguiti in ordine predefinito e casuale; 23 criteri di accettazione su 27 PASS, 4 PARTIAL, 0 FAIL. Dettagli e limiti (prove manuali NOT RUN, consegna email non provata, nessun hosting reale) in `docs/TEST_REPORT.md` e `docs/ACCEPTANCE_MATRIX.md`.
