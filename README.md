# Agriturismo La Volta — AI Prompt Pack V2.1

Pacchetto **AI-agnostic** per lavorare sul repository con **Codex, Claude Code o Cursor** senza cambiare metodo, specifiche o documentazione.

**Prima di iniziare:** leggi `START_HERE.md`. Per il manuale operativo completo usa `GUIDA_UTILIZZO_AI.md`.

La fonte di verità funzionale è `docs/SPEC.md`. I file specifici del singolo agente sono volutamente sottili: servono solo a far caricare le stesse regole condivise.

## Struttura

```text
START_HERE.md                   # avvio rapido: cosa fare la prima volta e ogni sessione
GUIDA_UTILIZZO_AI.md            # manuale operativo completo per Codex / Claude Code / Cursor
AGENTS.md                       # istruzioni principali; nativo/utile per Codex e leggibile da tutti
CLAUDE.md                       # bootstrap per Claude Code -> rimanda alle regole condivise
.cursor/rules/project.mdc       # bootstrap per Cursor -> rimanda alle regole condivise

docs/
  SPEC.md                       # specifica completa del committente: fonte di verità
  PROJECT_CONTEXT.md            # contesto sintetico e vincoli critici
  WORKFLOW.md                   # metodo operativo indipendente dall'AI
  PLAN.md                       # sequenza operativa per fasi, senza scadenze rigide
  TODO.md                       # checklist operativa
  SESSION_STATE.md              # stato corrente per riprendere o cambiare agente
  DECISIONS.md                  # decision log
  MISSING_DATA.md               # dati mancanti da NON inventare
  ACCEPTANCE_MATRIX.md          # matrice dei criteri di accettazione
  TEST_REPORT.md                # risultati reali dei test
  SECURITY_REVIEW.md            # template review sicurezza
  HANDOFF.md                    # template passaggio fra agenti/persone
  DELIVERY_CHECKLIST.md         # checklist pre-consegna
  COMMANDS.md                   # comandi verificati nel repository
  AI_TOOL_NOTES.md              # istruzioni pratiche per Codex / Claude Code / Cursor

prompts/
  README.md                     # indice e sequenza consigliata
  00_BOOTSTRAP.md               # caricamento contesto senza modifiche
  01_AUDIT_REPOSITORY.md        # audit iniziale
  02_ARCHITECTURE_DATABASE.md   # architettura e DB
  03_FOUNDATION_MIGRATION.md    # fondamenta e migrazione controllata
  04_BOOKING_AVAILABILITY.md    # richieste/prenotazioni/disponibilità
  05_PRICING.md                 # motore tariffario
  06_ADMIN.md                   # area amministrativa
  07_EMAIL_WHATSAPP.md          # SMTP e WhatsApp
  08_PUBLIC_FRONTEND.md         # pagine pubbliche e flusso richiesta
  09_I18N_SEO_A11Y_PERF.md     # IT/EN, SEO, accessibilità, performance, immagini
  10_SECURITY_PRIVACY_SPAM.md   # sicurezza, privacy tecnica, antispam
  11_TEST_REGRESSION.md         # test e bugfix
  12_DOCUMENTATION.md           # documentazione di consegna
  13_FINAL_REVIEW.md            # verifica requisiti
  14_RELEASE_PREP_NO_DEPLOY.md  # preparazione pubblicazione, senza deploy
  90_SESSION_START.md           # apertura di una sessione di lavoro
  91_SESSION_END.md             # checkpoint di fine sessione
  92_CODE_REVIEW.md             # review di modifiche già fatte
  93_BUGFIX.md                  # correzione bug focalizzata
  94_CONTEXT_RECOVERY.md        # recupero contesto dopo una pausa/chat nuova
  95_AGENT_HANDOFF.md           # passaggio Codex <-> Claude <-> Cursor
```

## Installazione nel repository

Copia **il contenuto** di questa cartella nella root del repository `agri_la_volta`, senza cancellare file applicativi esistenti.

Prima di modificare il progetto:

1. verifica `git status`;
2. aggiungi questo prompt pack;
3. crea un checkpoint Git se il workflow lo consente;
4. avvia l'agente dalla root del repository;
5. usa `prompts/00_BOOTSTRAP.md` e poi `prompts/01_AUDIT_REPOSITORY.md`.

## Regola essenziale

Non lanciare tutti i prompt in sequenza automaticamente. Ogni prompt è una **fase con stop condition**. La fase successiva parte solo quando lo stato corrente è coerente e i test pertinenti sono stati realmente eseguiti oppure i blocchi sono documentati.

## Se cambi AI

Non ricominciare da zero. Usa `prompts/95_AGENT_HANDOFF.md`. Il nuovo agente deve leggere almeno `AGENTS.md`, `docs/SESSION_STATE.md`, `docs/DECISIONS.md`, `docs/TODO.md`, `docs/TEST_REPORT.md`, `docs/MISSING_DATA.md` e il diff Git corrente.

## Nessun deploy automatico

Questo pacchetto non autorizza pubblicazione, DNS, email provider, acquisti, servizi cloud o modifiche a dati reali. Tali attività restano bloccate fino ad autorizzazione esplicita.
