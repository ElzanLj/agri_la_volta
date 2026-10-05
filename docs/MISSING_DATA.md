# Dati mancanti / decisioni di business aperte

Non compilare con valori inventati. Aggiorna questo file quando emergono informazioni mancanti.

| Voce | Stato | Valore / decisione fornita | Impatto | Fonte / nota |
|---|---|---|---|---|
| Prezzi definitivi | MANCANTE | — | Calcolo soggiorno | Da fornire dal titolare |
| Periodi stagionali | MANCANTE | — | Pricing | Da fornire |
| Regole adulti | MANCANTE | — | Pricing | Da fornire |
| Regole bambini | MANCANTE | — | Pricing | Da fornire |
| Supplementi animali | MANCANTE | — | Pricing | Da fornire |
| Soggiorno minimo | DA DEFINIRE | — | Pricing/disponibilità | Solo se previsto |
| Appartamenti gestiti da Novasol | MANCANTE | — | Disponibilità | Da fornire |
| Regole Novasol | MANCANTE | — | Disponibilità | Non implementare integrazione automatica ora |
| Traduzioni definitive EN | DA VERIFICARE | — | Contenuti | Correzione manuale possibile |
| Fotografie sostitutive/licenze | DA VERIFICARE | — | Pubblicazione | Sostituire provenienza dubbia |
| Parametri SMTP | MANCANTE | — | Email | Inserire solo in environment |
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
| Hosting di produzione (versione PHP, mod_rewrite, cron) | DA DEFINIRE | — | Architettura/release | Non ancora scelto |

## Regola

Se una feature dipende da una di queste informazioni, implementa la struttura configurabile e usa un placeholder chiaramente identificato solo dove previsto dalla specifica. Non trasformare un placeholder in dato reale senza fonte.


## Come aggiornare questo file

Usa stati coerenti: `MANCANTE`, `DA DEFINIRE`, `DA VERIFICARE`, `FORNITO`.

Quando un'informazione viene fornita, registra la fonte/nota e la data senza cancellare la storia utile. Non inserire segreti reali (es. password SMTP); registra soltanto che sono disponibili tramite il canale/configurazione appropriato.
