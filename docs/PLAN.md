# Piano di lavoro — sequenza operativa

Il piano è una **guida operativa per fasi**, non un calendario rigido. Le fasi non hanno una data di scadenza propria e possono durare più o meno del previsto in base allo stato reale del repository, agli esiti dei test e alle decisioni prese durante il lavoro.

L'ordine delle fasi rappresenta soprattutto **priorità e dipendenze tecniche**. Se una fase richiede più tempo, completala e validala prima di passare a quella successiva, salvo che esista un motivo tecnico documentato per lavorare in parallelo.

**Obiettivo temporale attuale:** 31 ottobre 2026. È una data indicativa e **può essere posticipata**. Non sacrificare correttezza, sicurezza, integrità dei dati, test critici o requisiti di `docs/SPEC.md` solo per rispettare questa data.

Il piano può essere aggiornato dopo l'audit, ma non devono essere eliminati requisiti o criteri di accettazione di `docs/SPEC.md` senza una decisione esplicita.

## Fase 0: audit e baseline

- repository, dipendenze, struttura, immagini e configurazioni;
- codice morto, pagamenti, servizi esterni e possibili dati reali;
- build/test/lint/dev attuali;
- cosa preservare;
- gap rispetto alla specifica;
- proposta architettura minima PHP/MySQL e hosting condiviso.

**Output:** `docs/AUDIT.md`, aggiornamenti a stato, dati mancanti e decisioni.

## Fase 1: architettura, DB e fondamenta

- struttura semplice;
- configurazione ambiente e `.env.example`;
- MySQL/MariaDB;
- migrazioni versionate;
- tabelle richieste;
- routing/error handling;
- autenticazione admin minima;
- strategia di migrazione dal progetto esistente senza riscritture inutili.

**Exit:** installazione locale riproducibile e DB inizializzabile da zero.

## Fase 2A: booking e disponibilità

- date e notti;
- `[check_in, check_out)`;
- richieste `pending`;
- prenotazioni `confirmed`;
- manual booking/origin;
- blocchi;
- cancellazione;
- non-overlap;
- transazioni/concorrenza;
- test critici.

## Fase 2B: pricing

- struttura tariffe configurabile;
- periodi/stagioni;
- adulti/bambini/animali;
- supplementi;
- soggiorno minimo se configurato;
- ricalcolo server-side;
- fixture test separate dai dati reali.

## Fase 3: area amministrativa

- `/admin` protetta;
- liste/filtri/dettagli;
- conferma/rifiuto;
- prenotazioni manuali;
- cancellazioni e blocchi;
- calendario semplice;
- gestione appartamenti e prezzi;
- audit log;
- export CSV.

## Fase 4: email e WhatsApp

- SMTP via environment;
- salvataggio prima dell'invio;
- nuova richiesta, conferma, rifiuto;
- fallimento/retry semplice se utile;
- bozza cancellazione;
- link WhatsApp con messaggio modificabile.

## Fase 5: frontend pubblico e flusso richiesta

- pagine richieste;
- appartamenti indicizzabili;
- flusso completo richiesta;
- mobile/desktop;
- riuso di componenti/contenuti utili esistenti.

## Fase 6: IT/EN, SEO, accessibilità, performance e immagini

- contenuti separati IT/EN;
- metadata/canonical/hreflang dove appropriato;
- sitemap/robots/breadcrumb/404;
- WCAG 2.2 AA per aspetti richiesti;
- immagini, responsive assets, hero e performance;
- provenienza dubbia segnalata.

## Fase 7: sicurezza, privacy tecnica e antispam

- SQLi/XSS/CSRF/sessioni/auth;
- segreti/log;
- rate limiting/honeypot/controlli semplici;
- privacy tecnica e minimizzazione dati;
- dipendenze e configurazione produzione.

## Fase 8: test, regressioni, documentazione

- suite test prevista dalla specifica;
- verifiche manuali;
- bugfix ad alta priorità;
- README, installazione, hosting condiviso, DB, SMTP, admin, backup/ripristino, CSV;
- `TEST_REPORT` aggiornato con risultati reali.

## Fase 9: review finale e buffer

- matrice criteri di accettazione;
- solo correzioni bloccanti/ad alto impatto;
- limitazioni residue e dati mancanti;
- preparazione pubblicazione **senza deploy**.

## Gestione del tempo

Nelle sessioni più brevi scegli un obiettivo principale completabile e testabile. Usa le sessioni più lunghe per migrazioni, flussi end-to-end, concorrenza, sicurezza e review trasversali.

Se il progetto è in ritardo rispetto all'obiettivo indicativo, ripianifica le fasi senza saltare i controlli critici. Riduci prima attività non essenziali come refactor estetici, animazioni, miglioramenti non richiesti, integrazioni future e automazioni non necessarie.

## Mappa prompt ↔ fase (00–32)

Questa è l'unica mappa valida. Le fasi 0–9 sono completate; la roadmap 14–32 è descritta in `docs/PRE_RELEASE_ROADMAP.md` (motivazioni e priorità) e le regole comuni sono in `docs/GUARDRAIL_FASI.md`.

| Prompt | Fase | Stato al 2026-10-07 |
|---|---|---|
| 00 | Bootstrap | completato |
| 01 | Fase 0: audit e baseline | completato |
| 02 | Fase 1: architettura, DB e fondamenta | completato |
| 03 | Fase 1b: migrazione controllata | completato |
| 04 | Fase 2A: booking e disponibilità | completato |
| 05 | Fase 2B: pricing | completato |
| 06 | Fase 3: area amministrativa | completato |
| 07 | Fase 4: email e WhatsApp | completato |
| 08 | Fase 5: frontend pubblico e flusso richiesta | completato |
| 09 | Fase 6: IT/EN, SEO, accessibilità, prestazioni, immagini | completato (con PARTIAL) |
| 10 | Fase 7: sicurezza, privacy tecnica, antispam | completato |
| 11 | Fase 8: test e regressioni | completato |
| 12 | Fase 8: documentazione | completato |
| 13 | Fase 9: review finale | completato |
| 14 | Sync dello stato, guardrail, audit dei contenuti | in corso |
| 15 | Correzioni dell'esistente | da fare |
| 16 | Progetto della gestione contenuti | da fare |
| 17 | Account admin senza SSH | da fare |
| 18 | Impostazioni del sito | da fare |
| 19 | Libreria foto | da fare |
| 20 | Foto, servizi e dettagli degli appartamenti | da fare |
| 21 | Pagine amministrabili | da fare |
| 22 | Migrazione dei contenuti e testi nuovi | da fare |
| 23 | Regole di prenotazione e strumenti per il listino | da fare |
| 24 | Operatività e comunicazione con l'ospite | da fare |
| 25 | Usabilità admin e rifiniture pubbliche | da fare |
| 26 | Stato del sistema, email, manutenzione | da fare |
| 27 | Aggiornamenti, backup, conservazione dei dati | da fare |
| 28 | Hardening pre-release | da fare |
| 29 | Aggiornamento della documentazione | da fare |
| 30 | Aggiornamento della review finale | da fare |
| 31 | Preparazione pubblicazione, senza deploy (ex 14) | da fare |
| 32 | Verifica dopo la pubblicazione | da fare (dopo un deploy autorizzato) |
| 90–95 | Utility di sessione, review, bugfix, handoff | invariati |

Nota: la "Fase 9" qui sopra (review finale) corrisponde ai prompt 13 e al vecchio 14; da ora la preparazione al rilascio è il prompt 31.
