# Decision log

Registra qui solo decisioni tecniche o di prodotto realmente prese. Non usare il file per inventare requisiti.

| Data | Decisione | Alternative considerate | Motivo | Impatto/file |
|---|---|---|---|---|
| 2026-10-05 | `docs/SPEC.md` è la fonte di verità funzionale | Prompt singolo non versionato | Mantiene requisiti e vincoli nel repository | Tutto il progetto |
| 2026-10-05 | Workflow AI-agnostic Codex/Claude/Cursor | Regole separate e divergenti per agente | Consente di cambiare agente mantenendo una sola memoria/versione dei requisiti | `AGENTS.md`, `CLAUDE.md`, `.cursor/`, `docs/`, `prompts/` |

## Decisioni P1–P7 (proposte nell'audit 2026-10-05)

**Approvate in blocco dall'utente il 2026-10-05.** Dettagli in `docs/AUDIT.md`.

| # | Proposta | Alternative | Motivo |
|---|---|---|---|
| P1 | PHP 8.1+ senza framework, rendering server-side, PDO + MySQL/MariaDB | Laravel/Slim; mantenere React con API PHP | semplicità, SEO senza SSR JS, hosting condiviso, manutenzione interna |
| P2 | Composer solo per PHPMailer (+ PHPUnit in dev) | `mail()` nativa; nessun Composer | SMTP autenticato affidabile; test |
| P3 | CSS/JS vanilla senza build in produzione | mantenere Vite per asset | meno dipendenze e meno passaggi |
| P4 | Spostare la SPA esistente in `legacy/` (spostamento Git) come riferimento, eliminarla dopo la Fase 5 | riscrittura in place; cancellazione immediata | preserva contenuti/stile senza bloccare la nuova struttura |
| P5 | URL IT senza prefisso, EN con prefisso `/en/` | sottodominio; `?lang=` | semplice, hreflang chiaro |
| P6 | Conferma con transazione InnoDB + `SELECT … FOR UPDATE` sulla riga appartamento | lock applicativi; `GET_LOCK` | robusto e portabile su MySQL/MariaDB condiviso |
| P7 | `git rm --cached src/EmailStatus/.env` + `.gitignore` per `.env*` (eccetto `.env.example`); niente riscrittura storia senza autorizzazione | riscrittura storia (vietata senza autorizzazione) | la revoca della credenziale è la mitigazione reale |

## Altre decisioni

| Data | Decisione | Alternative considerate | Motivo | Impatto/file |
|---|---|---|---|---|
| 2026-10-05 | Ambiente di sviluppo locale con Docker (`docker-compose.yml`: PHP 8.2 + Apache, MariaDB 10.11) | XAMPP/Laragon; PHP portable | scelto e attivato dall'utente. **Solo sviluppo**: la produzione resta hosting condiviso senza Docker | `docker-compose.yml` |
| 2026-10-05 | Le foto coperte da copyright verranno rimosse | richiedere licenze | decisione dell'utente dopo l'audit | `src/assets`, Fase 6 |

## Decisioni Fase 1 — architettura e database (2026-10-05)

| Data | Decisione | Alternative considerate | Motivo | Impatto/file |
|---|---|---|---|---|
| 2026-10-05 | Autoloader PSR-4 interno (`App\` → `app/`), Composer rinviato a quando serve PHPMailer/PHPUnit | Composer subito | nessuna dipendenza esterna necessaria per le fondamenta; i namespace sono già compatibili con l'autoload di Composer | `app/bootstrap.php` |
| 2026-10-05 | Struttura `public/` (document root) + `app/`, `templates/`, `migrations/`, `bin/`, `storage/`; `.htaccess` in root come fallback che mappa tutto in `public/` | solo `public/`; file PHP sparsi nella root | funziona sia con document root configurabile sia senza, senza esporre file privati | `.htaccess`, `public/.htaccess` |
| 2026-10-05 | Migrazioni SQL solo in avanti, eseguite da `bin/migrate.php`; ogni file registra sé stesso in `schema_migrations` (compatibile con import phpMyAdmin) | tool di migrazione esterni; down-migration | semplicità su hosting condiviso; DDL MySQL non è transazionale | `migrations/`, `app/Database/Migrator.php` |
| 2026-10-05 | DATETIME in UTC (sessione DB `time_zone = '+00:00'`); date soggiorno come DATE; importi in centesimi interi | DATETIME locali; DECIMAL | nessuna ambiguità con l'ora legale; niente errori di arrotondamento | schema, `Connection.php` |
| 2026-10-05 | Intervalli semiaperti `[start, end)` anche per blocchi e tariffe stagionali | intervalli chiusi per blocchi/tariffe | un'unica regola di overlap in tutto il sistema | schema |
| 2026-10-05 | Stati separati: `booking_requests` (`pending/confirmed/rejected/cancelled`), `bookings` (`confirmed/cancelled`) con `origin`; solo `bookings.confirmed` e `availability_blocks` occupano date | tabella unica | SPEC §10: richiesta distinta dalla prenotazione | schema |
| 2026-10-05 | `bookings.guest_name` testo libero, email/telefono facoltativi | nome/cognome obbligatori | le prenotazioni manuali da agenzia possono avere solo un riferimento | schema |
| 2026-10-05 | Gestione agenzia come `management_mode` (`direct`/`agency`) + `managing_agency` + `accepts_online_requests` | tabella agenzie | SPEC §12 chiede solo la predisposizione; regole Novasol ancora ignote | `apartments` |
| 2026-10-05 | Migrazione `0002` inserisce solo nomi e slug dei 6 appartamenti (da SPEC §4); tutti gli altri campi NULL | nessun seed; seed da contenuti legacy | i nomi sono dati certi; il resto è da confermare | `migrations/0002_seed_apartments.sql` |
| 2026-10-05 | Foto, servizi/dotazioni e tabelle email/regole prezzi rinviate alle fasi che le usano | crearle ora | solo tabelle motivate (prompt 02); verranno aggiunte con nuove migrazioni | Fasi 2B, 4, 5 |
| 2026-10-05 | Sessioni PHP native in `storage/sessions` (GC proprio), avviate solo su admin/moduli; cookie `HttpOnly`, `SameSite=Lax`, `Secure` in HTTPS; timeout inattività 2 h, assoluto 12 h | sessioni in DB | semplice e sufficiente per un solo admin; le pagine pubbliche non impostano cookie | `app/Security/Session.php`, `AdminAuth.php` |
| 2026-10-05 | Rate limiting su tabella DB `rate_limit_hits` con chiave hash SHA-256, conservazione 24 h; login admin: 5 fallimenti / 15 min per IP | file su disco; servizi esterni | funziona su hosting condiviso; riusabile per i moduli pubblici | `app/Security/RateLimiter.php` |
| 2026-10-05 | `docker-compose.yml` versionato senza segreti (valori da `.env`), porte legate a `127.0.0.1`, document root del container = root del repository | lasciarlo non tracciato | ambiente locale riproducibile; esercita il fallback `.htaccess` | `docker-compose.yml`, `docker/php/Dockerfile` |

## Decisioni Fase 1b — migrazione controllata (2026-10-05)

| Data | Decisione | Alternative considerate | Motivo | Impatto/file |
|---|---|---|---|---|
| 2026-10-05 | Rimossi da `legacy/`: `Pagamenti`, `PrenotazioniPopUp`, `AuthPopup`, `BookingSystem.jsx`, `GenitoreComponente.jsx`, `ListaAppartamenti.jsx`, `firebaseConfig.js`, `EmailStatus/EmailServer.js`, `.env.example` Firebase | lasciarli fino alla Fase 5 | la sostituzione PHP (DB, config, admin auth) è pronta e verificata; SPEC §3 impone la rimozione dei pagamenti | `legacy/src/**` |
| 2026-10-05 | Rimosse le dipendenze `firebase`, `nodemailer`, `dotenv`, `all`, `react-datepicker`, `react-icons` da `legacy/package.json` | mantenerle | vietate (Firebase) o non usate | `legacy/package*.json` |
| 2026-10-05 | `legacy/` resta come sola sorgente di contenuti/stile (Hero, Appartamenti, DoveSiamo, CSS): non viene servita né pubblicata. Rimossi dal legacy il modulo contatti (usava il server Node) e la voce "Prenota" | riscrivere i componenti per il legacy | il form contatti e il flusso di prenotazione saranno nuovi in PHP (Fasi 2A/4/5) | `ContactUs.jsx`, `NavBar.jsx`, `App.jsx` |
| 2026-10-05 | Foto, hotlink e CSS legacy non migrati in `public/` in questa fase | migrare gli asset ora | le foto di provenienza dubbia vanno sostituite (decisione utente); i contenuti verranno riportati in Fase 5/6 con provenienza verificata | `legacy/src/assets` |
| 2026-10-05 | Node/Vite resta solo come strumento di build del legacy, non richiesto in produzione | rimuovere subito | utile per consultare/compilare il riferimento fino alla sua eliminazione (Fase 5) | `legacy/` |

## Template nuova decisione

- **Data:** YYYY-MM-DD
- **Decisione:**
- **Alternative considerate:**
- **Motivo:**
- **Vincoli della specifica coinvolti:**
- **Impatto:**
- **Reversibile:** sì/no
