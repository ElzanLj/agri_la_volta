# Prompt 10 — Sicurezza, privacy tecnica e antispam

Agisci come security reviewer del progetto reale, non come generatore di checklist teoriche.

## Controlla

- SQL injection/query parametrizzate;
- XSS/escaping;
- validazione server-side;
- CSRF;
- session fixation/hijacking e cookie;
- hashing password admin;
- autorizzazione lato server;
- rate limiting e limiti dimensione richieste;
- upload se presenti;
- error handling/leakage;
- `.env`, repository, log e segreti;
- dati personali nei log;
- SMTP secrets;
- moduli pubblici/spam;
- honeypot, rate limiting, controlli temporali prima di CAPTCHA;
- assenza pagamenti/dati carta;
- dipendenze rilevanti.

## Privacy tecnica

Verifica minimizzazione dati, consenso privacy obbligatorio nei moduli, possibilità tecnica di cancellazione/esportazione e assenza di tracking non necessario. Non presentare testi legali come consulenza definitiva.

## Output

Aggiorna `docs/SECURITY_REVIEW.md`. Per ogni finding reale: severità, file/percorso, scenario, fix, verifica, stato.

Applica correzioni ragionevoli e contenute. Non acquistare/attivare servizi esterni.

Aggiorna `TEST_REPORT`, `TODO`, `SESSION_STATE`.

## Stop condition

Fermati quando i finding ad alta priorità sono corretti o chiaramente documentati/bloccati.
