# Comandi verificati del progetto

> Compilare durante l'audit. Non inventare comandi. Inserire solo comandi realmente presenti o verificati nel repository.

## Ambiente rilevato

Verificato il 2026-10-05 (audit, commit `07baca4`).

- OS/runtime: Windows 11 Pro, Git Bash / PowerShell
- PHP: **non installato** (`php: command not found`)
- Composer: **non installato**
- Node/npm (solo se usati in sviluppo): Node v24.18.0, npm 11.16.0
- MySQL/MariaDB: **non installato** (`mysql: command not found`)

## Stack attuale (legacy React/Vite, destinato alla sostituzione)

### Installazione dipendenze

```bash
npm ci --no-audit --no-fund
```

PASS. Warning `allow-scripts` per `esbuild` e `protobufjs`; la build funziona comunque.

### Dev / avvio locale

```bash
npm run dev            # vite, http://localhost:5173
```

Verificato con `npx vite --port 5179 --strictPort`: `/` e `/dovesiamo` → HTTP 200.

Il mini-server email (`node src/EmailStatus/EmailServer.js`, porta 3001) **non è stato avviato**: userebbe credenziali Gmail reali.

### Build

```bash
npm run build          # output in dist/ (ignorato da Git)
```

PASS con warning (path immagine non risolto in `DoveSiamo.css`, sintassi CSS, chunk JS > 500 kB).

### Lint / static analysis

```bash
npm run lint
```

FAIL: 90 errori (baseline, non corretti).

### Audit dipendenze

```bash
npm audit --omit=dev
```

14 vulnerabilità (8 moderate, 6 high).

## Test

Nessuno script di test presente.

## Database / migrazioni

Nessuno: il progetto attuale usa Firebase Firestore (servizio esterno, non contattato).

## Note hosting condiviso

Da verificare dopo la definizione dell'architettura finale (PHP + MySQL/MariaDB, vedi `docs/AUDIT.md`).
