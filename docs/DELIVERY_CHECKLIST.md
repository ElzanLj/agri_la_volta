# Checklist di consegna / pubblicazione

Questa checklist non autorizza il deploy. Pubblicazione, DNS e servizi esterni richiedono autorizzazione esplicita.

Aggiornata il 2026-10-06 (prompt 12, documentazione). Legenda: `[x]` fatto **e verificato** (evidenza indicata); `[~]` fatto in parte; `[ ]` da fare / non eseguito. Per ogni criterio funzionale l'evidenza completa è in `docs/ACCEPTANCE_MATRIX.md`.

## Funzionalità critiche

- [x] Nessun sistema di pagamento (`ScopeTest`)
- [x] Nessun dato carta (`ScopeTest`)
- [x] Nessun account/login ospite (`ScopeTest`)
- [x] Richiesta pubblica non presentata come prenotazione confermata (`PublicRequestFlowTest`, `ScopeTest`)
- [x] Solo admin può confermare (`EndToEndTest`, `AdminAccessTest`, `ScopeTest`)
- [x] Nessun overlap tra confirmed dello stesso appartamento (`ConcurrencyTest`, processi reali)
- [x] Disponibilità ricontrollata server-side
- [x] Prezzo ricalcolato server-side
- [x] Tariffe modificabili senza codice (il listino reale va inserito dal titolare)
- [x] Adulti/bambini/animali possono influire sul prezzo
- [x] Prenotazioni esterne inseribili manualmente
- [x] Cancellazione libera le date
- [x] Bozza email cancellazione modificabile
- [x] Fallimento SMTP non perde la richiesta (consegna reale **non** provata)
- [x] Admin protetta
- [x] Audit log presente
- [x] IT/EN (inglese provvisorio)
- [~] Tastiera e responsive (controlli automatici sì; **prova manuale NOT RUN**)
- [~] Immagini ottimizzate e provenienza dubbia segnalata (segnalazione sì; nessuna foto pubblicata, ottimizzazione non eseguita)
- [x] Pagina indicizzabile per ogni appartamento
- [x] CSV esportabile
- [~] Compatibilità hosting condiviso (verificata in Docker/Apache; nessun hosting reale provato)
- [x] Nessuna dipendenza obbligatoria da provider specifico

## Documentazione

- [x] Riepilogo modifiche → `docs/CHANGES.md`
- [x] Architettura finale → `docs/ARCHITECTURE.md`
- [x] Struttura progetto → `README.md`, `docs/ARCHITECTURE.md`
- [x] Schema database → `docs/ARCHITECTURE.md` (12 tabelle), `migrations/`
- [x] Migrazioni SQL → `migrations/0001`–`0005`, importate da zero in un database vuoto: 12 tabelle, `bin/migrate.php --status` tutte applicate
- [x] Configurazioni richieste → `README.md`, `docs/INSTALL_SHARED_HOSTING.md` §5
- [x] `.env.example` → commentato, senza segreti
- [x] Sviluppo locale → `README.md`, `docs/COMMANDS.md`
- [x] Installazione hosting condiviso → `docs/INSTALL_SHARED_HOSTING.md` (non provata su hosting reale)
- [x] Import database → `docs/INSTALL_SHARED_HOSTING.md` §4 (verificato: SQL importati a mano, `migrate --status` tutti applicati)
- [x] Configurazione SMTP → `docs/INSTALL_SHARED_HOSTING.md` §7 (consegna reale non provata)
- [x] Creazione/modifica admin → `docs/INSTALL_SHARED_HOSTING.md` §6, `docs/OPERATIONS.md` §5 (da CLI e da SQL, verificate con login reale)
- [x] Backup → `docs/OPERATIONS.md` §2
- [x] Ripristino → `docs/OPERATIONS.md` §3 (andata e ritorno verificata con checksum identici)
- [x] Export CSV → `docs/OPERATIONS.md` §4, `AdminExportTest`
- [x] Test eseguiti e risultati → `docs/TEST_REPORT.md` (778 test PASS)
- [x] Limitazioni residue → `README.md`, `docs/TEST_REPORT.md` (rischi residui), `docs/SECURITY_REVIEW.md`
- [x] Informazioni mancanti → `docs/MISSING_DATA.md`

## Operazioni esterne — BLOCCATE fino ad autorizzazione

- [ ] Autorizzazione esplicita ricevuta prima del deploy (**non ricevuta; nessun deploy eseguito**)
- [x] Nessuna modifica DNS/nameserver non autorizzata (nessuna eseguita)
- [x] Nessuna modifica MX/SPF/DKIM/DMARC non autorizzata (nessuna eseguita; SPF/DKIM descritti solo come istruzione)
- [x] Nessun acquisto/servizio a pagamento non autorizzato (nessuno)
- [ ] Backup verificato prima di qualsiasi operazione su dati reali (la procedura è verificata su dati di prova; da rifare sull'hosting reale prima di toccare dati veri)

## Prima di pubblicare (a carico del titolare / di chi pubblica)

- [ ] Hosting, dominio e HTTPS scelti e attivati
- [ ] Database creato e migrazioni importate; amministratore creato
- [ ] `.env` di produzione compilato (con `APP_SECRET`) e `vendor/` caricato
- [ ] Credenziali SMTP inserite e una richiesta di prova ricevuta nella casella del gestore (`docs/MANUAL_CHECKLIST.md` §8)
- [ ] Prove manuali eseguite (`docs/MANUAL_CHECKLIST.md`): tastiera, mobile, desktop, IT/EN
- [ ] Listino, descrizioni degli appartamenti, testi delle pagine, recapiti, WhatsApp forniti (`docs/MISSING_DATA.md`)
- [ ] Foto con provenienza verificata, o segnaposto accettati (`docs/IMAGES.md`)
- [ ] Privacy e cookie policy verificate da titolare/consulente
- [ ] Periodo di conservazione dei dati deciso (`DATA_RETENTION_MONTHS`)
- [x] Cartella `legacy/` rimossa dal repository (review finale; il file `.env` locale del legacy risulta già cancellato dal titolare)
- [ ] Prova di fumo del §10 di `docs/INSTALL_SHARED_HOSTING.md` superata
- [ ] Guida di rilascio compilata (`docs/RELEASE_GUIDE.md`, prompt 14)

## Handoff finale

- [x] `SESSION_STATE.md` aggiornato
- [x] `ACCEPTANCE_MATRIX.md` completata con evidenze (23 PASS, 4 PARTIAL, 0 FAIL)
- [x] `TEST_REPORT.md` aggiornato
- [x] `SECURITY_REVIEW.md` aggiornato
- [x] `MISSING_DATA.md` aggiornato
- [x] `FINAL_REVIEW.md` prodotto (prompt 13)
- [x] Nessun segreto nel diff finale (scansione a ogni commit)
- [x] Nessun deploy effettuato senza autorizzazione (nessun deploy eseguito)
