# Dati mancanti / decisioni di business aperte

Non compilare con valori inventati. Aggiorna questo file quando emergono informazioni mancanti.

| Voce | Stato | Valore / decisione fornita | Impatto | Fonte / nota |
|---|---|---|---|---|
| Prezzi definitivi | MANCANTE | — | Calcolo soggiorno | Da fornire dal titolare. Struttura pronta (migrazione 0003): il listino va inserito dall'admin, nessun prezzo è nel codice o nelle migrazioni. Senza listino le richieste restano "prezzo da confermare" |
| Periodi stagionali | MANCANTE | — | Pricing | Da fornire; vanno inseriti come periodi per appartamento. `PricingConfigService::coverageGaps` elenca le date ancora senza tariffa |
| Regole adulti | MANCANTE | — | Pricing | Da fornire: quanti adulti sono inclusi nella tariffa base e quanto costa ogni adulto in più (per notte o per soggiorno) |
| Regole bambini | MANCANTE | — | Pricing | Da fornire: quanti bambini gratis e quanto costano gli altri. Il sito legacy citava sconti per bambini 1-3 anni: non applicabili finché non si raccoglie l'età |
| Supplementi animali | MANCANTE | — | Pricing | Da fornire: importo, per notte o per soggiorno, quanti animali gratis, e limite per appartamento (`max_pets`; 0 = non ammessi) |
| Soggiorno minimo | DA DEFINIRE | — | Pricing/disponibilità | Solo se previsto. Convenzione tecnica confermata dall'utente il 2026-10-05: vale il minimo del periodo che contiene la data di arrivo; da confermare anche col titolare |
| Appartamenti gestiti da Novasol | MANCANTE | — | Disponibilità | Da fornire |
| Regole Novasol | MANCANTE | — | Disponibilità | Non implementare integrazione automatica ora |
| Traduzioni definitive EN | DA VERIFICARE | — | Contenuti | Correzione manuale possibile |
| Fotografie sostitutive/licenze | DA VERIFICARE | — | Pubblicazione | Sostituire provenienza dubbia |
| Parametri SMTP | MANCANTE | — | Email | Struttura pronta (Fase 4): `SMTP_HOST/PORT/ENCRYPTION/USERNAME/PASSWORD`, `MAIL_FROM_*`, `MAIL_ADMIN_ADDRESS`. Finché mancano le email restano in coda (Admin > Email) e le prenotazioni non ne risentono. Inserire solo in environment/hosting |
| Informazioni legali definitive | DA VERIFICARE | — | Privacy/cookie | Verifica titolare/consulente |
| Revoca App Password Gmail committata | FORNITO | revocata dal titolare | Sicurezza | Audit 2026-10-05: `src/EmailStatus/.env` tracciato con credenziali reali; revoca su account Google, non eseguibile dall'agente |
| Contenuto Firestore `prenotazioni` (possibili dati carta/personali) | FORNITO | verificato dal titolare il 2026-10-05 (esito non dettagliato) | Sicurezza/privacy | Audit 2026-10-05: `Pagamenti.jsx` salva campi carta su Firestore; cancellazione solo con autorizzazione |
| Regole Firestore / utenti Firebase Auth registrati / dismissione progetto Firebase | DA VERIFICARE | Firebase verificato dal titolare; dismissione del progetto da decidere | Sicurezza/privacy | Audit 2026-10-05 |
| Capienza, camere, letti per appartamento | MANCANTE | — | Appartamenti/disponibilità | Descrizioni legacy indicative; limite 6 persone hardcoded non confermato |
| Fotografie proprie per ciascun appartamento | MANCANTE | — | Pagine appartamento | Gallerie legacy riusano la stessa foto per più appartamenti |
| Hero image propria | MANCANTE | — | Home | Hero attuale hotlinkata da un sito terzo e non raffigura La Volta |
| Origine foto 1024×651 (`agri1`, `appartamento6/8/9/10`, `piscina1/2`) e `agri5/6/7.JPG` | DA VERIFICARE | — | Immagini | Audit 2026-10-05 |
| Email di contatto ufficiale | DA VERIFICARE | — | Contatti/SEO | Legacy: `.com` (ContactUs, SPEC) vs `.it` (footer DoveSiamo) |
| Telefoni, indirizzo completo, P.IVA/REA/ragione sociale | DA VERIFICARE | — | Contatti/SEO/legale | Presenti in `ContactUs.jsx`, da confermare |
| Numero WhatsApp | MANCANTE | — | WhatsApp | Probabilmente il cellulare in `ContactUs.jsx`, da confermare |
| Orari check-in/check-out | DA VERIFICARE | — | Appartamenti/email | Legacy: consegna 11:00–18:00, rilascio entro 9:00 |
| Servizio "affitto sala per eventi" | DA VERIFICARE | — | Contenuti | Presente tra i motivi di contatto legacy |
| Valutazioni a stelle per appartamento | DA VERIFICARE | — | Contenuti | Fonte ignota; non pubblicare senza fonte |
| Hosting di produzione (versione PHP, mod_rewrite, cron) | DA DEFINIRE | — | Architettura/release | Requisiti minimi: PHP 8.1+ con pdo_mysql, MySQL 5.7+/MariaDB 10.3+, Apache con mod_rewrite e .htaccess (vedi `docs/COMMANDS.md`) |

## Regola

Se una feature dipende da una di queste informazioni, implementa la struttura configurabile e usa un placeholder chiaramente identificato solo dove previsto dalla specifica. Non trasformare un placeholder in dato reale senza fonte.


## Come aggiornare questo file

Usa stati coerenti: `MANCANTE`, `DA DEFINIRE`, `DA VERIFICARE`, `FORNITO`.

Quando un'informazione viene fornita, registra la fonte/nota e la data senza cancellare la storia utile. Non inserire segreti reali (es. password SMTP); registra soltanto che sono disponibili tramite il canale/configurazione appropriato.
| Tassa di soggiorno | MANCANTE | — | Pricing/legale | Non implementata (regole comunali, esenzioni): richiede dati e verifica del titolare/Comune; la struttura attuale non la calcola |
| Sconti percentuali e per età dei bambini | MANCANTE | — | Pricing | Esclusi dalla Fase 2B per scelta confermata: servono regole di arrotondamento e, per l'età, un campo età nella richiesta |
| Supplementi opzionali (lettino, letto aggiunto, pulizia infrasettimanale) | MANCANTE | — | Pricing/form | Rinviati: il form non può ancora selezionarli; importi non confermati (legacy: lettino 10 euro/giorno, letto aggiuntivo 20 euro, pulizia infrasettimanale 10 euro — NON validi) |
| Pulizia finale e altri supplementi obbligatori | MANCANTE | — | Pricing | Il legacy citava 30 euro di pulizia finale: non confermato. Va inserito come regola `stay` per soggiorno |
| Limiti bambini/animali per appartamento (`max_children`, `max_pets`) | MANCANTE | — | Disponibilità/form | Colonne vuote = nessun limite. Da compilare per appartamento |
| Sconti soggiorni lunghi / offerte speciali | MANCANTE | — | Pricing | Il legacy citava formule da concordare: non implementate |
| Testi definitivi delle email (notifica al gestore, conferma, rifiuto, bozza di cancellazione) in IT e EN | DA VERIFICARE | testi provvisori e sobri in `app/Mail/MessageBuilder.php` e `CancellationDraft.php` | Email | Da approvare dal titolare: formula di saluto, firma, indirizzo e telefono, istruzioni di arrivo, politica di cancellazione. Gli orari di arrivo/partenza compaiono solo se configurati per l'appartamento |
| Numero WhatsApp dell'agriturismo (`WHATSAPP_NUMBER`) | MANCANTE | — | WhatsApp | Finché è vuoto il pulsante pubblico non viene mostrato (la funzione è pronta per la Fase 5) |
| Prefisso internazionale predefinito per i numeri senza prefisso | FORNITO | 39 (Italia) | WhatsApp | Confermato dall'utente il 2026-10-06; configurabile con `WHATSAPP_DEFAULT_COUNTRY_CODE` |
| Capacità dell'hosting per le email (PHP-FPM, cron, estensioni curl/openssl, caricamento della cartella `vendor/`) | DA DEFINIRE | — | Email/release | Con PHP-FPM l'invio avviene dopo la chiusura della risposta; senza, in linea con timeout di 10 s. Il cron è facoltativo (`bin/send-queued-mail.php`) |
| Verifica di consegna reale (TLS/STARTTLS con certificato vero, autenticazione con il provider, SPF/DKIM, finire o no nello spam) | NON ESEGUITA | — | Email | Richiede le credenziali SMTP reali. Checklist in `docs/TEST_REPORT.md`. Nessuna modifica a DNS/posta senza autorizzazione |
| Email di "richiesta ricevuta" al cliente | NON PREVISTA | — | Email | Scelta confermata dall'utente il 2026-10-06: la SPEC prevede solo la notifica al gestore e conferma/rifiuto al cliente |
