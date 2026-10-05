# Test report

> Regola: non segnare PASS un test che non è stato realmente eseguito.

## Ambiente

- Data: 2026-10-05 (baseline audit)
- Commit/branch: `07baca4` / `main`
- PHP: non installato
- Database: non installato (legacy usa Firebase, non contattato)
- Browser/device: nessuno (solo richieste HTTP al dev server)
- Note ambiente: Windows 11, Node v24.18.0, npm 11.16.0

## Baseline stack legacy (React/Vite)

| Controllo | Comando | Risultato | Note |
|---|---|---|---|
| Install | `npm ci --no-audit --no-fund` | PASS | warning allow-scripts esbuild/protobufjs |
| Lint | `npm run lint` | FAIL | 90 errori (42 prop-types, 25 no-unused-vars, 14 no-undef, 9 no-unescaped-entities) |
| Build | `npm run build` | PASS | warning: `fotoSalso3.jpeg` non risolto, CSS syntax, chunk JS 676 kB |
| Dev server | `npx vite --port 5179` | PASS | `/` e `/dovesiamo` → HTTP 200 |
| Audit dipendenze | `npm audit --omit=dev` | 14 vulnerabilità | 8 moderate, 6 high |

I test della tabella seguente riguardano il nuovo sistema PHP e non sono applicabili allo stack legacy.

## Test automatici

| Test | Comando / procedura | Risultato | Evidenza / note |
|---|---|---|---|
| Date non valide | | NOT RUN | |
| Checkout precedente/uguale al check-in | | NOT RUN | |
| Numero notti | | NOT RUN | |
| Soggiorni consecutivi | | NOT RUN | |
| Overlap parziale | | NOT RUN | |
| Overlap completo | | NOT RUN | |
| Blocchi manuali | | NOT RUN | |
| Conferma concorrente | | NOT RUN | |
| Prezzi stagionali | | NOT RUN | |
| Variazione adulti | | NOT RUN | |
| Variazione bambini | | NOT RUN | |
| Animali | | NOT RUN | |
| Autorizzazione admin | | NOT RUN | |
| Richiesta pubblica | | NOT RUN | |
| Fallimento email | | NOT RUN | |
| Validazione form | | NOT RUN | |
| Cancellazione | | NOT RUN | |
| Export CSV | | NOT RUN | |

## Verifica manuale

| Area | Risultato | Browser/device | Note |
|---|---|---|---|
| Mobile | NOT RUN | | |
| Desktop | NOT RUN | | |
| Tastiera | NOT RUN | | |
| Pagine principali | NOT RUN | | |
| Italiano | NOT RUN | | |
| Inglese | NOT RUN | | |

## Problemi aperti

- Lint legacy in FAIL (90 errori): non corretto, stack destinato alla sostituzione.
- Nessun test automatico esistente.


## Convenzioni evidenza

Quando possibile registra:

- comando esatto;
- exit code/esito;
- test name o scenario;
- ambiente/DB;
- eventuale commit;
- per test manuali: browser/device e passaggi minimi.

Non incollare segreti, credenziali o dati personali reali nel report.
