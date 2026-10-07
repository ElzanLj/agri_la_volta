# Indice dei prompt

## Prima volta

1. `00_BOOTSTRAP.md`
2. `01_AUDIT_REPOSITORY.md`
3. dopo review dell'audit: `02_ARCHITECTURE_DATABASE.md`
4. quindi seguire la sequenza 03 → 32 in base allo stato reale.

## Sequenza delle fasi

| Prompt | Fase | Stato al 2026-10-07 |
|---|---|---|
| 02–13 | fondamenta, booking, pricing, admin, email, frontend, SEO/a11y, sicurezza, test, documentazione, review finale | completati |
| `14_STATE_SYNC_CONTENT_AUDIT.md` | verifica di 12–13, sync, guardrail e strategia Git, finding della review, inventario contenuti | completato |
| `15_EXISTING_FIXES.md` | **correzioni dell'esistente** (anonimizzazione dello Storico, rifiuto con conferma, doppio invio, rate limit, host canonico, migrazioni sicure, coerenza dei dati) e test-guardiani | completato (da committare) |
| `16_CONTENT_MODEL_DESIGN.md` | progetto della gestione contenuti, contratto dei campi, threat model | completato (da committare) |
| `17_ADMIN_ACCOUNT_NO_SSH.md` | cambio password dall'admin, procedura senza SSH, riautenticazione riusabile | |
| `18_SITE_SETTINGS.md` | impostazioni del sito (recapiti, dati aziendali, link, avviso globale, valori comuni), cache e pagina 503 | |
| `19_MEDIA_LIBRARY.md` | libreria foto sicura (+ prova facoltativa con foto legacy in locale) | |
| `20_APARTMENT_PHOTOS_SERVICES.md` | foto, servizi e dettagli degli appartamenti | |
| `21_PAGE_CONTENT.md` | pagine amministrabili | |
| `22_CONTENT_MIGRATION_LEGACY.md` | testi nel DB, testi legacy e contenuti nuovi (nascosti), redirect | |
| `23_BOOKING_RULES_PRICING.md` | **regola minimo 2 persone (attivabile)**, regole di prenotazione, strumenti per il listino | |
| `24_ADMIN_OPERATIONS_COMMUNICATION.md` | arrivi e partenze, ricerca, note, email all'ospite, iCal | |
| `25_ADMIN_UX_PUBLIC_POLISH.md` | menu admin, checklist, indicatori, ripristino testi, admin da telefono, rifiniture pubbliche | |
| `26_SYSTEM_DIAGNOSTICS_EMAIL.md` | stato del sistema, configurazione email, attività pianificate, registro errori, manutenzione, coerenza dei dati | |
| `27_SYSTEM_CRITICAL_OPERATIONS.md` | aggiornamenti del database, backup con prova di ripristino, conservazione dei dati | |
| `28_PRE_RELEASE_HARDENING.md` | sicurezza, versioni PHP/DB, prove con hosting meno permissivi, regressione, pulizia, legacy | |
| `29_DOCUMENTATION_UPDATE.md` | aggiornamento documentazione + manuale del titolare | |
| `30_FINAL_REVIEW_UPDATE.md` | aggiornamento della review finale, chiusura dei finding, prova generale del titolare | |
| `31_RELEASE_PREP_NO_DEPLOY.md` | preparazione al rilascio senza deploy (ex 14) | |
| `32_POST_DEPLOY_VERIFICATION.md` | **verifica sull'hosting reale dopo una pubblicazione autorizzata** (ripetibile) | |

Motivazioni e priorità: `docs/PRE_RELEASE_ROADMAP.md`. Regole comuni a ogni fase: `docs/GUARDRAIL_FASI.md`. Review che ha originato le modifiche 15, 26–28 e 32: `docs/REVIEW_PRE_ROADMAP.md`. Comportamento di ogni campo dell'admin (obbligatorio, limiti, se vuoto): `docs/CAMPI_CONTENUTI.md`. Bozza visiva dell'admin: `docs/mockup-admin/leggimi.html`.

## Domande all'utente

I prompt 14–32 contengono una sezione **"Domande per l'utente"**, con risposte possibili e consiglio (✅). Le domande sono di due tipi:

- **[titolare]**: decisioni sul business. L'agente si ferma finché non rispondi ("consigliate" vale, se lo dici esplicitamente per quella fase);
- **[tecnica]**: scelte di implementazione. Se rispondi "consigliate" o non hai preferenze, vale ✅, e l'agente le elenca nel riepilogo.

Tutte le domande sono raccolte in `docs/RISPOSTE_UTENTE.md`: puoi compilarlo in anticipo e l'agente ti chiederà solo conferma. L'agente non scrive mai risposte al posto tuo.

I prompt 15 e 17–28, il 30 e il 32 terminano con **"Prova tu"**: pochi passi manuali per controllare il risultato, confrontandolo con la bozza in `docs/mockup-admin/`.

## Sessioni normali

- apertura: `90_SESSION_START.md`
- chiusura: `91_SESSION_END.md`

## Casi speciali

- review di codice: `92_CODE_REVIEW.md`
- bug specifico: `93_BUGFIX.md`
- chat nuova / lunga pausa: `94_CONTEXT_RECOVERY.md`
- cambio Codex / Claude / Cursor: `95_AGENT_HANDOFF.md`

## Importante

Non inviare tutti i prompt insieme. Ogni file contiene una stop condition. I prompt non sostituiscono `docs/SPEC.md`. Il pacchetto non esegue deploy: la pubblicazione richiede sempre un'autorizzazione esplicita separata.
