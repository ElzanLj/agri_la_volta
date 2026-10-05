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

## Template nuova decisione

- **Data:** YYYY-MM-DD
- **Decisione:**
- **Alternative considerate:**
- **Motivo:**
- **Vincoli della specifica coinvolti:**
- **Impatto:**
- **Reversibile:** sì/no
