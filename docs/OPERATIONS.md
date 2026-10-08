# Operazioni: backup, ripristino, CSV, manutenzione

Guida per chi gestisce il sito dopo l'installazione (`docs/INSTALL_SHARED_HOSTING.md`). I comandi segnati ✔ sono stati eseguiti realmente in Docker (MariaDB 10.11, PHP 8.2) il 2026-10-06; su un hosting reale vanno adattati (host, utente, percorsi).

## 1. Cosa fare backup

| Cosa | Perché | Dove |
|---|---|---|
| **Il database** | contiene richieste, prenotazioni, tariffe, appartamenti, storico, coda email: è l'unico dato che non si ricostruisce | pannello dell'hosting o `mysqldump` |
| Il file **`.env`** (o le variabili del pannello) | contiene le impostazioni e il segreto dell'applicazione; **contiene password: conservalo cifrato e separato** | radice del sito |
| Le **foto** pubblicate (quando esisteranno) e gli originali | non sono nel database | `public/assets/img/` e la cartella degli originali |
| Il codice | si riscarica dal repository; serve ricordare quale versione è online | repository Git |

Non serve salvare `storage/sessions/` né `storage/logs/`.

## 2. Backup del database ✔

**Con SSH o da terminale**:

```bash
mysqldump --single-transaction --routines --default-character-set=utf8mb4 \
  -h HOST -u UTENTE -p NOME_DATABASE > backup-AAAAMMGG.sql
```

(Il comando chiede la password; non scriverla nella riga di comando.) **Senza SSH**: phpMyAdmin → scheda *Esporta* → metodo *Rapido*, formato SQL. Molti pannelli offrono anche backup automatici: controlla frequenza e conservazione.

Frequenza consigliata: **ogni giorno** in alta stagione, almeno una volta a settimana nel resto dell'anno, e **sempre prima** di un aggiornamento o di una migrazione. Conserva più copie (almeno 7 giornaliere), **fuori dall'hosting** (un altro computer o un servizio di archiviazione scelto dal titolare).

> **Attenzione:** il file contiene i **dati personali dei clienti** e l'**hash della password dell'amministratore**. Trattalo come un dato riservato: cifrato, accessibile solo a chi ne ha bisogno, cancellato quando non serve. L'anonimizzazione dei dati (§6) non modifica i backup già fatti.

## 3. Ripristino ✔

1. Crea un database **vuoto** (`utf8mb4_unicode_ci`) — meglio un database nuovo che sovrascrivere quello attuale.
2. Importalo:

   ```bash
   mysql --default-character-set=utf8mb4 -h HOST -u UTENTE -p NOME_DATABASE < backup-AAAAMMGG.sql
   ```

   oppure phpMyAdmin → *Importa* (set di caratteri `utf8mb4`; se il file supera il limite di upload del pannello, dividilo o usa l'importazione guidata dell'hosting).
3. Punta il sito al nuovo database cambiando `DB_NAME` (e, se serve, utente e password) in `.env`.
4. Controlla: `php bin/migrate.php --status` (tutte `[x]`) e apri il sito e l'admin.

Prova eseguita: dump di un database con una richiesta confermata (nome `Zoë Müller`, note con accenti e €), ripristino in un database nuovo, confronto dei checksum: **identici** per `admin`, `apartments`, `booking_requests`, `bookings`, `audit_log`, `email_outbox` e `schema_migrations`; gli accenti sono intatti. **Prova il ripristino sul tuo hosting almeno una volta**, non aspettare un'emergenza.

Dopo un ripristino le sessioni dell'admin restano valide solo se la password non è cambiata; in dubbio basta accedere di nuovo.

## 4. Esportazione dei dati in CSV (Admin → Export) ✔

Due file, scaricabili dall'area amministrativa: **Richieste** e **Prenotazioni** (`richieste-AAAA-MM-GG.csv`, `prenotazioni-AAAA-MM-GG.csv`). Si possono filtrare per stato, appartamento, periodo (soggiorni dal / al escluso) e, per le prenotazioni, per origine.

- Formato: separatore `;`, codifica UTF-8 con BOM (Excel legge bene gli accenti), righe CRLF. Aprili con Excel, LibreOffice o Google Sheets.
- Le celle che comincerebbero con `=`, `+`, `-`, `@` (anche i numeri di telefono con `+`) sono neutralizzate con un apostrofo, così non vengono eseguite come formule.
- I file contengono **dati personali**: conservali e cancellali con cura.
- Lo stesso contenuto è visibile dalle pagine *Richieste* e *Prenotazioni*; lo storico delle modifiche è in *Storico*.

## 5. Cambiare la password dell'amministratore ✔

**Se conosci la password attuale:** *Admin → Account → Cambia la password*. Gli altri dispositivi collegati vengono disconnessi. Se hai perso il telefono o hai mostrato la password a qualcuno, nella stessa pagina c'è *Esci da tutti i dispositivi*.

**Se l'hai dimenticata:** non esiste un recupero via email. Serve una persona con il progetto sul computer che prepari il comando `php bin/create-admin.php nome-utente --print-sql > admin.sql` e lo importi in phpMyAdmin (`docs/COMMANDS.md`, «Amministratore»); funziona anche senza SSH. Con SSH basta `php bin/create-admin.php`. In ogni caso le sessioni aperte si chiudono. Se hai troppi tentativi falliti, il login si blocca per 15 minuti per quell'indirizzo; per sbloccare subito: `DELETE FROM rate_limit_hits;`.

## 6. Privacy: esportare e anonimizzare i dati di una persona ✔

Da riga di comando (richiede SSH o un'esecuzione locale puntata al database). Ogni comando **simula** finché non si aggiunge `--apply`:

```bash
php bin/privacy.php export mario.rossi@example.com           # tutto ciò che è conservato su quella persona (JSON)
php bin/privacy.php erase mario.rossi@example.com            # simula l'anonimizzazione
php bin/privacy.php erase mario.rossi@example.com --apply    # esegue
php bin/privacy.php purge --months=24 --apply                # anonimizza i soggiorni terminati da più di 24 mesi
```

Lo Storico non conserva più il testo libero dei motivi (solo "motivo presente"). Per ripulire le voci vecchie: `php bin/privacy.php audit-clean` (simula) e `--apply`. Un controllo di coerenza dei dati si lancia con `php bin/check-consistency.php` (sola lettura).

L'anonimizzazione toglie nome, email, telefono, note e testi di email, ma **conserva** appartamento, date e prezzo del soggiorno (le date restano occupate). Richieste in attesa e soggiorni non ancora finiti vengono saltati e segnalati. Il **periodo di conservazione lo decide il titolare** (con un consulente): finché `DATA_RETENTION_MONTHS` è vuoto non viene fatto nulla in automatico. Dettagli in `docs/COMMANDS.md`.

## 7. Email in coda

*Admin → Email* mostra lo stato di ogni messaggio (in coda, inviato, fallito, non c'è un destinatario), il numero di tentativi, il motivo dell'errore (senza password né indirizzi) e il pulsante *Riprova invio*. La richiesta o la prenotazione non dipendono mai dall'invio. Il cron (`bin/send-queued-mail.php`) è facoltativo. La cancellazione di una prenotazione **non invia mai email da sola**: apre una bozza da modificare e inviare.

## 8. Aggiornare il sito

1. **Backup** del database (§2) e nota della versione attuale.
2. Carica i file nuovi (stesse regole del §2 dell'installazione; non sovrascrivere `.env` né `storage/`).
3. Se la nuova versione contiene migrazioni (`migrations/`): importale **in ordine** (`php bin/migrate.php`, o phpMyAdmin con i soli file nuovi). `bin/migrate.php` impedisce due esecuzioni insieme e, per la migrazione dei vincoli (`0007`), controlla i dati prima di partire; con phpMyAdmin questi controlli non ci sono. Se una migrazione si interrompe: `docs/COMMANDS.md`, "Migrazione interrotta a metà".
4. Esegui la prova di fumo (`docs/INSTALL_SHARED_HOSTING.md` §10).
5. **Rollback**: le migrazioni vanno solo in avanti. Per tornare indietro ripristina il backup del database del punto 1 e ricarica i file della versione precedente.

## 9. Log e manutenzione

- I log sono in `storage/logs/app-AAAA-MM-GG.log`, un file al giorno, con eventi (login riuscito/fallito, moduli rifiutati, email, errori) e **senza dati personali, password o indirizzi IP**. Non sono raggiungibili dal web. Cancella i file più vecchi di qualche mese; il sito non lo fa da solo.
- La tabella `rate_limit_hits` si pulisce da sola (24 ore). Le sessioni in `storage/sessions/` si ripuliscono con la scadenza (12 ore al massimo).
- In caso di errore 500 (pagina "Errore del server"): guarda l'ultima riga `ERROR` del log del giorno; il visitatore non vede mai i dettagli.

## 10. Problemi frequenti

| Sintomo | Causa probabile | Cosa fare |
|---|---|---|
| Ogni pagina dà 403 | `mod_rewrite` o `.htaccess` non attivi | chiedere al provider di abilitarli (`AllowOverride All`) |
| Pagine senza stile | `APP_URL` sbagliato o file `public/assets` non caricati | controllare `APP_URL` e i file |
| Errore del server su tutte le pagine | credenziali del database errate, database non importato | controllare `DB_*`, importare le migrazioni, leggere il log |
| "Il sistema è occupato" in conferma | un'altra operazione sullo stesso appartamento in corso | riprovare dopo qualche secondo |
| Le email non partono | SMTP non configurato o rifiutato | *Admin → Email* mostra il motivo; controllare `SMTP_*` e la porta/cifratura |
| Pulsante WhatsApp assente | `WHATSAPP_NUMBER` vuoto o non valido | inserire cifre con prefisso, senza `+` |
| Recapiti assenti dal sito | `PUBLIC_*` vuote | compilarle in `.env` |
| Date sbagliate di un giorno | fuso orario | verificare `APP_TIMEZONE` |
| Troppi tentativi di accesso | blocco temporaneo | attendere 15 minuti o `DELETE FROM rate_limit_hits;` |
| Dopo un aggiornamento mancano tabelle o colonne | migrazioni non importate | `php bin/migrate.php` o importare i file nuovi |
