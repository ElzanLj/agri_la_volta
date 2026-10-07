# Prompt 19 — Libreria foto

Prerequisito: prompt 18 completato; regole foto approvate in `docs/CMS_DESIGN.md`.

Contesto: nessuna foto pubblicata; `ImageSet` genera già `<picture>` responsive da varianti `-<larghezza>.webp/.jpg`; `bin/optimize-images.php` crea le varianti con GD ma solo da riga di comando. Limite corpo richieste 1 MB (`public/index.php`, `public/.htaccess`). Foto di provenienza incerta non pubblicabili (SPEC §23, `docs/IMAGES.md`). È la superficie di attacco più grande della roadmap: sugli hosting condivisi `memory_limit` è spesso 128 MB (una foto da 40 megapixel non si decodifica), `upload_max_filesize` può essere 2–8 MB, e le foto dell'iPhone sono HEIC.

Riferimento visivo (non vincolante): `docs/mockup-admin/foto.html`.

## Obiettivo

Permettere al titolare di caricare foto dall'admin in modo sicuro, con varianti ottimizzate e metadati, senza ancora collegarle a pagine o appartamenti. Ogni caricamento deve riuscire del tutto o non lasciare nulla (né file né righe).

## Prima di modificare

- Vincoli: nessun nuovo framework, dipendenza Composer di produzione, JavaScript pubblico, React, Firebase o WordPress; nessun refactor fuori scope; preserva ciò che funziona; codice rimosso solo se dimostrato inutile. Rispetta `docs/GUARDRAIL_FASI.md` (ramo Git per fase, prove obbligatorie, contenuti come dati, file vietati, nessun segreto).
- Verifica lo stato del prompt 18 e le decisioni sulle foto (in particolare D5 del prompt 16: master senza EXIF).
- Leggi `docs/THREAT_MODEL.md` e in `docs/REVIEW_PRE_ROADMAP.md` le schede C24–C39 e i test D16 e D17.
- Leggi `ImageSet.php`, `bin/optimize-images.php`, `public/index.php`, `public/.htaccess`, `.htaccess` in root, `Response.php` (CSP), `docker/php/Dockerfile`, `docs/IMAGES.md`.
- Leggi `docs/CAMPI_CONTENUTI.md` (approvato nel prompt 16): ogni campo toccato da questa fase segue la sua riga (obbligatorietà, limiti, comportamento se vuoto, segnalazione). Se un campo nuovo non ha una riga, aggiungila e falla confermare.
- `git status` e diff locali.

## Domande per l'utente (all'inizio della fase)

Prima di modificare qualsiasi file, apri `docs/RISPOSTE_UTENTE.md`: se l'utente ha già segnato le risposte per questo prompt, mostrale e chiedi solo conferma. Altrimenti presenta le domande di questa sezione **così come sono scritte** (risposte possibili e consiglio) e **fermati finché non risponde**.

Le domande sono di due tipi, indicati nel titolo:

- **[titolare]**: decisioni sul business (prezzi, regole, testi, comunicazioni agli ospiti, dati legali, servizi esterni). Non applicare mai una risposta ✅ al posto dell'utente: serve la sua risposta scritta in chat ("consigliate" vale, se detto esplicitamente per questa fase). Senza risposta la fase non inizia.
- **[tecnica]**: scelte di implementazione. Se l'utente risponde "consigliate" o non ha preferenze, vale la risposta ✅; elencale comunque nel riepilogo finale.

Registra ogni scelta in `docs/DECISIONS.md`. In `docs/RISPOSTE_UTENTE.md` (casella con `x`) scrivi **solo** ciò che l'utente ha detto in chat, mai risposte tue. Le voci rinviate o rifiutate vanno in `docs/TODO.md` con il motivo. Non implementare nulla che non sia stato approvato. Se una voce approvata supera lo scope di questa fase, proponi di spostarla e fermati.

### D1 — Dimensione massima di una foto caricata [tecnica]

| Risposta | Pro / contro |
|---|---|
| A. 5 MB | Sicuro su quasi ogni hosting; molte foto da telefono vanno ridotte prima |
| B. ✅ Fino al limite del server (il minore tra `upload_max_filesize` e `post_max_size`), con un massimo di 10 MB; l'admin mostra il valore reale e, se una foto lo supera, spiega come ridurla | Funziona su ogni hosting senza scoprire il limite per tentativi |
| C. 20 MB | Accetta tutto; molti hosting non lo permettono |

**Consiglio: B.**

### D2 — Se il server non ha GD (la libreria PHP per le immagini) [tecnica]

| Risposta | Pro / contro |
|---|---|
| A. ✅ Caricamento rifiutato con messaggio chiaro | Nessuna foto pesante pubblicata per errore; serve GD sull'hosting (quasi sempre presente) |
| B. Salvare l'originale senza varianti | Funziona ovunque; pagine lente e metadati EXIF/GPS non rimossi |

**Consiglio: A.**

### D3 — Dichiarazione di provenienza [titolare]

| Risposta | Pro / contro |
|---|---|
| A. ✅ Casella obbligatoria ("foto nostra o con licenza") + campo credito facoltativo | Rispetta SPEC §23, lascia traccia |
| B. Solo il campo credito | Più veloce; nessuna conferma esplicita |
| C. Nulla | Nessun attrito; rischio di pubblicare foto altrui |

**Consiglio: A.**

### D4 — Prova con le foto del legacy, solo in locale [titolare]

Dopo questa fase si possono caricare le foto di `legacy/src/assets` **nel database locale** per vedere il sito con immagini vere. Molte hanno provenienza dubbia: mai in produzione, mai in un commit.

| Risposta | Pro / contro |
|---|---|
| A. No | Nessun rischio; si aspetta il set definitivo per vedere il risultato |
| B. ✅ Sì, a fine fase, caricandole dall'admin nel DB locale e segnando il credito "LEGACY – NON PUBBLICARE" | Collauda la libreria con foto reali; da svuotare prima di qualsiasi rilascio |
| C. Sì, su un ramo Git temporaneo con template modificati a mano | Più veloce da vedere; modifiche al codice da buttare, test che falliscono |

**Consiglio: B.** La prova diventa anche il collaudo della funzione.

### D5 — Spazio massimo per le foto [tecnica]

Gli hosting condivisi hanno spazio limitato, condiviso con sessioni, log e database.

| Risposta | Pro / contro |
|---|---|
| A. 500 MB | Prudente; bastano per decine di foto ben ottimizzate |
| B. ✅ 1,5 GB, con avviso in admin all'80% | Largo margine per sei appartamenti; non satura un hosting da 5 GB |
| C. Nessun limite | Una cartella che cresce senza controllo può bloccare il sito |

**Consiglio: B.** Il limite è modificabile nelle impostazioni e conta varianti e master.

### D6 — Formati particolari [tecnica]

HEIC/HEIF (iPhone), JPEG CMYK, PNG a 16 bit, WebP animati, GIF.

| Risposta | Pro / contro |
|---|---|
| A. ✅ Rifiutati con un messaggio che spiega cosa fare (per l'iPhone: Impostazioni › Fotocamera › Formati › "Più compatibile", oppure condividere la foto come JPEG) | Comportamento prevedibile; nessun colore sbagliato, nessun errore incomprensibile |
| B. Si convertono quelli che GD sa gestire (CMYK, 16 bit) e si rifiutano HEIC e animati | Più comodo; la conversione può alterare i colori |
| C. Si accettano solo JPEG/PNG/WebP "normali" senza spiegare il motivo degli altri | Meno testi; il titolare non capisce perché fallisce |

**Consiglio: A.**

### D7 — Testo alternativo [tecnica]

| Risposta | Pro / contro |
|---|---|
| A. ✅ Italiano obbligatorio, 5–150 caratteri; rifiutati i valori generici (uguale al nome del file, "foto", "immagine", "IMG_1234") | Accessibilità reale, senza false descrizioni |
| B. Solo la lunghezza minima | Più veloce; "foto1" passa |

**Consiglio: A.**

## Implementa

- Per ogni campo: limite scritto sotto il campo, frase "se lo lasci vuoto…" per i facoltativi, errori accanto al campo con i valori conservati, comportamento sul sito come da `docs/CAMPI_CONTENUTI.md`.
- Migrazione: tabella `media` come da design (nome generato, MIME, dimensioni, peso, hash del contenuto, alt IT/EN, credito, provenienza, data).
- **Servizio unico** condiviso da admin e `bin/optimize-images.php` (lo script deve continuare a funzionare), con questo ordine di controlli, **tutti prima di decodificare l'immagine**:
  1. dimensione del file e upload completo; file vuoto o troncato rifiutato;
  2. tipo reale con `finfo` e `getimagesize` (mai l'estensione né il tipo dichiarato dal browser); solo JPEG, PNG e WebP statici; HEIC/HEIF, GIF, WebP/APNG animati, SVG, CMYK e 16 bit secondo D6;
  3. **limite di pixel calcolato dalla memoria**: stima ≈ larghezza × altezza × 5 byte (verifica con `memory_limit` e memoria già usata); se non entra, rifiuto con messaggio e indicazione del massimo in megapixel; l'admin mostra questo valore. Il limite di 40 megapixel della bozza vale solo se la memoria lo permette;
  4. decodifica, orientamento corretto (`exif` se presente; altrimenti lettura dell'orientamento dal file oppure avviso "la foto potrebbe risultare ruotata"), **ricodifica che elimina EXIF e GPS**, PNG con trasparenza su sfondo **bianco**;
  5. **master** senza metadati (lato lungo 2400–3000 px, mai ingrandito) in `storage/`, fuori dalla cartella pubblica (decisione del prompt 16), e varianti (480, 800, 1200, 1600) senza ingrandire, WebP solo se `imagewebp` esiste, JPEG di riserva.
- **Scrittura atomica**: file temporaneo fuori dalla cartella pubblica → elaborazione → riga nel database → spostamento finale. Qualsiasi errore (memoria, tempo, disco, permessi) pulisce temporanei, varianti e riga: mai file orfani né righe senza file. `set_time_limit` dove permesso; varianti generate una alla volta.
- **Nomi** casuali di 128 bit (32 caratteri esadecimali) senza data né identificativi; mai il nome caricato nel percorso; estensioni scritte solo dall'applicazione (`.jpg`, `.webp`, `.png`).
- **Cartella pubblica delle foto** con `.htaccess` compatibile con PHP-FPM: `<FilesMatch "\.(php\d?|phtml|phar|cgi|pl)$">` con `Require all denied` (e riserva `Deny from all`), `Options -ExecCGI -Indexes`; **mai** `php_flag` né `php_admin_flag` (con PHP-FPM causano errore 500). Permessi documentati.
- **Limite di richiesta**: il limite di Apache nel `.htaccess` sale al massimo consentito per le foto (12 MB); l'applicazione continua a rifiutare con 413 ogni rotta **tranne** l'upload sopra 1 MB. L'admin mostra `upload_max_filesize`, `post_max_size`, `memory_limit` e il limite di megapixel risultante.
- **Quota** (D5): spazio usato mostrato in admin, avviso all'80%, caricamento rifiutato oltre il limite.
- **Doppioni**: se l'hash del contenuto è già in libreria compare un avviso "questa foto è già presente" (non blocca).
- **Testo alternativo** secondo D7; credito e provenienza secondo D3.
- Admin "Foto": elenco con anteprime, caricamento, modifica metadati, azione "Rigenera varianti" dal master, eliminazione **solo se non usata** (la verifica d'uso sarà estesa nei prompt 20–21); l'eliminazione rimuove riga, varianti e master.
- La verifica di coerenza del prompt 15 viene estesa: righe senza file, file senza riga, varianti mancanti.
- `docker/php/Dockerfile` (solo sviluppo): GD con JPEG e WebP.
- `ImageSet` accetta un record `media`; CSP invariata (`img-src 'self'`).

## Test obbligatori

- **contratto dei campi**: per ogni campo di questa fase i "Test minimi" di `docs/CAMPI_CONTENUTI.md` (vuoto, solo spazi, troppo lungo, non valido, con HTML, resa IT/EN, Storico) e i controlli tra campi che lo riguardano;
- upload valido JPEG, PNG, WebP; varianti con dimensioni corrette, mai ingrandite; master presente e senza EXIF;
- **file ostili** (fixture piccole in `tests/fixtures/images/`): PHP rinominato `.jpg`; polyglot GIF/PHP; PNG con un chunk di testo contenente `<?php` (dopo la ricodifica non ne resta traccia); SVG con script; MIME falso; JPEG troncato; file vuoto; upload parziale;
- **formati particolari**: HEIC, JPEG CMYK, PNG a 16 bit, WebP animato, GIF → rifiuto con il messaggio previsto da D6, mai un errore 500;
- **bomba di decompressione**: PNG con dimensioni dichiarate enormi e file minuscolo → rifiutato **prima** della decodifica; con `memory_limit` abbassato nel test (`ini_set`) una foto grande viene rifiutata con messaggio, non con errore fatale;
- **nomi**: nome file con `../`, byte nulli, Unicode di direzione, 300 caratteri, doppia estensione → ignorato; nessun file fuori dalle cartelle previste; nessuna collisione tra due caricamenti identici;
- **atomicità**: cartella non scrivibile, disco pieno simulato, errore a metà → nessuna riga, nessun file, nessun temporaneo;
- EXIF e GPS assenti in varianti e master; foto con orientamento EXIF raddrizzata; PNG trasparente su sfondo bianco;
- nessuno script eseguibile nella cartella pubblica (prova su Apache nel container); `.htaccess` valido con PHP-FPM (nessun `php_flag`);
- quota: avviso all'80%, rifiuto oltre il limite; doppione: avviso;
- eliminazione di foto non usata rimuove riga, varianti e master; foto usata non eliminabile; autorizzazione e CSRF; ID inesistente → 404;
- limite di 1 MB invariato su tutte le altre rotte; comportamento senza GD o senza WebP; `bin/optimize-images.php` funzionante; suite completa PASS.

Aggiorna `docs/IMAGES.md`, `docs/THREAT_MODEL.md`, `SECURITY_REVIEW`, `COMMANDS`, `TEST_REPORT`, `DECISIONS`, `TODO`, `SESSION_STATE`.

## Prova tu (5–10 minuti, a fine fase)

L'agente elenca questi passi nel riepilogo finale; l'utente li esegue in locale.

1. Apri **Foto** e carica una foto dal telefono: confronta con `docs/mockup-admin/foto.html`.
2. Prova a caricare un file che non è una foto (es. un PDF rinominato `.jpg`): deve essere rifiutato.
3. Scarica una variante creata e verifica che non contenga più la posizione GPS (proprietà del file).
4. Prova una foto molto grande (oltre 30 megapixel) e una foto HEIC dell'iPhone: devono comparire messaggi che spiegano cosa fare, mai un errore tecnico.
5. Guarda la pagina **Foto**: deve mostrare spazio usato, limite del server e dimensione massima accettata.
6. Se hai approvato D4: carica qualche foto legacy, con credito "LEGACY – NON PUBBLICARE".

## Stop condition

Fermati quando caricamento, varianti, metadati ed eliminazione sono sicuri e testati, e l'eventuale prova D4 è fatta. Fermati prima di iniziare se l'utente non ha risposto alle domande [titolare]. **Non** collegare le foto ad appartamenti, pagine o hero (prompt 20–21), **non** includere le foto nel backup (prompt 27) e non portare foto legacy fuori dal DB locale.
