# Proposte di miglioramento — intero progetto

Proposte del 2026-10-07, basate su controlli mirati del codice. Escludono quanto in `docs/PROPOSTE_PRENOTAZIONI_LISTINO.md`. Sono inserite come **domande** nei prompt indicati in fondo.

Valutazione generale: concorrenza, sicurezza, test e accessibilità di base sono fatti con cura. Le migliorie riguardano soprattutto manutenzione nel tempo, operatività quotidiana e comodità del titolare.

Correzioni rispetto all'analisi precedente:

- il problema dei `CHECK` ignorati da MySQL 5.7 è **già documentato** in `docs/COMMANDS.md`: nel prompt 22 resta solo la prova pratica su MySQL 8;
- le email impostano **già** il `Reply-To` (`MailMessage`, `SmtpTransport`).

## 1. Versioni e manutenzione del codice

| Priorità | Proposta | Perché |
|---|---|---|
| ESSENTIAL | Requisito minimo PHP 8.3 e prova della suite anche su 8.4 | `composer.json` dichiara PHP ≥ 8.1, senza aggiornamenti di sicurezza da fine 2025; l'8.2 usato in Docker li perde a fine 2026 |
| ESSENTIAL | Requisiti database aggiornati: almeno MySQL 8.0 o MariaDB 10.6/10.11 | MySQL 5.7 e MariaDB 10.3 (minimi attuali) sono fuori supporto da anni; chiude anche la questione `CHECK` |
| RECOMMENDED | Analisi statica (PHPStan) solo in sviluppo | Trova errori di tipo e codice irraggiungibile a costo basso |
| RECOMMENDED | `composer audit` a ogni rilascio | PHPMailer è l'unica dipendenza di produzione e ha avuto vulnerabilità gravi in passato |
| RECOMMENDED | Test automatici a ogni push (es. GitHub Actions con MariaDB) | Oggi i 763 test girano solo a mano. Attivarlo richiede autorizzazione |
| OPTIONAL | PHPUnit da 10 a 11/12 | La 10 non riceve più correzioni; solo sviluppo |

## 2. Operatività in produzione

| Priorità | Proposta | Perché |
|---|---|---|
| ESSENTIAL | Cancellazione automatica dei log vecchi (es. oltre 90 giorni) | `app/Support/Logger.php` crea un file al giorno in `storage/logs` e non li cancella mai: spazio limitato sugli hosting e dati da conservare il meno possibile |
| RECOMMENDED | Modalità manutenzione con file `storage/maintenance` (pagina 503) | Durante aggiornamenti via FTP/phpMyAdmin il sito resta qualche minuto a metà tra due versioni |
| RECOMMENDED | Script (solo sviluppo) per il pacchetto di rilascio | Zip con i soli file da caricare: niente `legacy/`, `tests/`, `docker/`, `.env`; `vendor/` senza PHPUnit (prompt 31) |
| RECOMMENDED | Versione visibile nel piè di pagina dell'admin | Sapere quale versione gira sull'hosting accelera ogni diagnosi |
| OPTIONAL | Monitoraggio esterno gratuito della raggiungibilità | Avvisa se il sito va giù; servizio esterno da autorizzare |
| OPTIONAL | Avviso se le email falliscono ripetutamente | Se l'SMTP è rotto un avviso via email non arriva: meglio il banner in dashboard (prompt 25) |

## 3. L'admin nel lavoro quotidiano

| Priorità | Proposta | Perché |
|---|---|---|
| RECOMMENDED | Pagina "Arrivi e partenze" dei prossimi giorni, stampabile | È la domanda quotidiana di chi gestisce una struttura; oggi va ricostruita dal calendario |
| RECOMMENDED | Ricerca libera per riferimento `LV-…`, nome, email, telefono | I filtri attuali (`app/Http/Admin/ListFilters.php`) sono solo periodo, appartamento e stato |
| RECOMMENDED | Admin usabile da telefono (tabelle provate a 360 px, schede dove serve) | Il titolare risponderà spesso dal cellulare; le prove manuali coprono oggi solo il sito pubblico |
| OPTIONAL | Note interne su richieste e prenotazioni, mai visibili al cliente | "Arriva tardi", "chiede il seggiolone" |
| OPTIONAL | Statistiche semplici: richieste al mese, confermate/rifiutate, occupazione per appartamento | Utili per i prezzi, dai dati già nelle tabelle, senza tracciamento |
| OPTIONAL | Riepilogo giornaliero via email (richieste in attesa, arrivi di domani) | Richiede cron; la dashboard copre quasi tutto |
| OPTIONAL | Esportazione e anonimizzazione dei dati di un cliente dall'admin | Oggi solo con `bin/privacy.php`: senza SSH impossibile; le richieste privacy vanno evase entro un mese |

## 4. Sito pubblico

| Priorità | Proposta | Perché |
|---|---|---|
| RECOMMENDED | Revisione dell'inglese da un madrelingua | `MISSING_DATA` lo segna come provvisorio |
| RECOMMENDED | Indicazioni "Come arrivare" (auto, treno, uscita autostradale) | Agriturismo in collina; si fa con una sezione in Dintorni o Contatti |
| RECOMMENDED | Pagina 404 con link ad appartamenti, richiesta e contatti | Oggi offre solo "torna alla home" |
| OPTIONAL | Icone per la schermata del telefono (apple-touch-icon, manifest) | Quando c'è un logo verificato |
| OPTIONAL | Dati strutturati per ogni appartamento (capienza, servizi) | Solo con dati reali |
| OPTIONAL | `lastmod` nella sitemap | Piccolo aiuto all'indicizzazione |
| OPTIONAL | Minimo JavaScript facoltativo: data di partenza aggiornata dopo l'arrivo | Miglioramento progressivo; va pesato contro la scelta "zero JS" |

## 5. Email

| Priorità | Proposta | Perché |
|---|---|---|
| RECOMMENDED | Email "richiesta ricevuta" al cliente | Esclusa il 2026-10-06 perché la SPEC non la prevede; senza conferma molti pensano che la richiesta sia persa. Coda e modelli IT/EN esistono già: costo basso. Decisione tua |
| OPTIONAL | Email in HTML con versione testo | Oggi solo testo (`isHTML(false)`), che va bene ed è meno spesso marcato come spam |

## 6. Sicurezza e privacy

| Priorità | Proposta | Perché |
|---|---|---|
| RECOMMENDED | Backup automatici esterni e protetti (database + foto) | Se l'account hosting viene compromesso o chiuso, i backup ospitati lì spariscono; soprattutto procedura (prompt 31) |
| OPTIONAL | Secondo fattore (TOTP) per l'admin | Unica porta verso i dati dei clienti, ma con un account condiviso la gestione dei codici si complica |
| OPTIONAL | Registro dei trattamenti e informativa definitiva | Adempimento del titolare con il consulente |

## 7. Fuori dal codice, con impatto reale

- Profilo Google Business aggiornato con link al sito e alla pagina di richiesta.
- Pagina TripAdvisor collegata.
- Foto professionali o almeno coerenti: oggi il punto più debole.
- Tempi di risposta alle richieste: il modello "richiesta e conferma" funziona solo con risposte in poche ore.

## 8. Sconsigliato

- App per telefono.
- Chat dal vivo.
- Newsletter con gestione iscritti (nuovi obblighi privacy).
- Lingue oltre IT/EN senza dati di visita che lo giustifichino.
- Framework PHP per "modernizzare": il codice è chiaro e testato.
- Cache lato server: inutile per sei appartamenti.

## 9. Dove sono nei prompt

- Correzioni dell'esistente (Storico, rifiuto con conferma, doppio invio, rate limit, host canonico, migrazioni, coerenza dei dati, `APP_ENV`, log, invio dopo la risposta): **prompt 15**.
- Versioni PHP e database, PHPStan, mutation testing, `composer audit`, CI, prove con `AllowOverride` ridotto, pacchetto di rilascio, legacy: **prompt 28**.
- Stato del sistema, configurazione email dall'admin, attività pianificate, registro errori, modalità manutenzione, conservazione dei log, pagina di coerenza dei dati: **prompt 26**.
- Aggiornamenti del database, backup (con prova di ripristino), conservazione dei dati personali: **prompt 27**.
- Backup esterni (procedura), monitoraggio, `security.txt`, host canonico e file sensibili in produzione: **prompt 31**; verifica sull'hosting reale: **prompt 32**.
- Arrivi e partenze, ricerca, note interne, statistiche, esportazione dati dall'admin, email "richiesta ricevuta", email in HTML, riepilogo giornaliero: **prompt 24**.
- Admin da telefono, 404 utile, versione nell'admin, `lastmod`, icone, JavaScript facoltativo: **prompt 25**.
- TOTP e riautenticazione: **prompt 17**. Link recensioni: **prompt 18**.
- Come arrivare e revisione dell'inglese: **prompt 22** (contenuti) e `MISSING_DATA` (testi del titolare).
