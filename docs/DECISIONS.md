# Decision log

Registra qui solo decisioni tecniche o di prodotto realmente prese. Non usare il file per inventare requisiti.

| Data | Decisione | Alternative considerate | Motivo | Impatto/file |
|---|---|---|---|---|
| 2026-10-05 | `docs/SPEC.md` è la fonte di verità funzionale | Prompt singolo non versionato | Mantiene requisiti e vincoli nel repository | Tutto il progetto |
| 2026-10-05 | Workflow AI-agnostic Codex/Claude/Cursor | Regole separate e divergenti per agente | Consente di cambiare agente mantenendo una sola memoria/versione dei requisiti | `AGENTS.md`, `CLAUDE.md`, `.cursor/`, `docs/`, `prompts/` |

## Template nuova decisione

- **Data:** YYYY-MM-DD
- **Decisione:**
- **Alternative considerate:**
- **Motivo:**
- **Vincoli della specifica coinvolti:**
- **Impatto:**
- **Reversibile:** sì/no
