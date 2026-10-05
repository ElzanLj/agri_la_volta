# Checklist di consegna / pubblicazione

Questa checklist non autorizza il deploy. Pubblicazione, DNS e servizi esterni richiedono autorizzazione esplicita.

## Funzionalità critiche

- [ ] Nessun sistema di pagamento
- [ ] Nessun dato carta
- [ ] Nessun account/login ospite
- [ ] Richiesta pubblica non presentata come prenotazione confermata
- [ ] Solo admin può confermare
- [ ] Nessun overlap tra confirmed dello stesso appartamento
- [ ] Disponibilità ricontrollata server-side
- [ ] Prezzo ricalcolato server-side
- [ ] Tariffe modificabili senza codice
- [ ] Adulti/bambini/animali possono influire sul prezzo
- [ ] Prenotazioni esterne inseribili manualmente
- [ ] Cancellazione libera le date
- [ ] Bozza email cancellazione modificabile
- [ ] Fallimento SMTP non perde la richiesta
- [ ] Admin protetta
- [ ] Audit log presente
- [ ] IT/EN
- [ ] Tastiera e responsive
- [ ] Immagini ottimizzate e provenienza dubbia segnalata
- [ ] Pagina indicizzabile per ogni appartamento
- [ ] CSV esportabile
- [ ] Compatibilità hosting condiviso
- [ ] Nessuna dipendenza obbligatoria da provider specifico

## Documentazione

- [ ] Riepilogo modifiche
- [ ] Architettura finale
- [ ] Struttura progetto
- [ ] Schema database
- [ ] Migrazioni SQL
- [ ] Configurazioni richieste
- [ ] `.env.example`
- [ ] Sviluppo locale
- [ ] Installazione hosting condiviso
- [ ] Import database
- [ ] Configurazione SMTP
- [ ] Creazione/modifica admin
- [ ] Backup
- [ ] Ripristino
- [ ] Export CSV
- [ ] Test eseguiti e risultati
- [ ] Limitazioni residue
- [ ] Informazioni mancanti

## Operazioni esterne — BLOCCATE fino ad autorizzazione

- [ ] Autorizzazione esplicita ricevuta prima del deploy
- [ ] Nessuna modifica DNS/nameserver non autorizzata
- [ ] Nessuna modifica MX/SPF/DKIM/DMARC non autorizzata
- [ ] Nessun acquisto/servizio a pagamento non autorizzato
- [ ] Backup verificato prima di qualsiasi operazione su dati reali


## Handoff finale

- [ ] `SESSION_STATE.md` aggiornato
- [ ] `ACCEPTANCE_MATRIX.md` completata con evidenze
- [ ] `TEST_REPORT.md` aggiornato
- [ ] `SECURITY_REVIEW.md` aggiornato
- [ ] `MISSING_DATA.md` aggiornato
- [ ] `FINAL_REVIEW.md` prodotto
- [ ] Nessun segreto nel diff finale
- [ ] Nessun deploy effettuato senza autorizzazione
