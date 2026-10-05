# Prompt 14 — Preparazione pubblicazione, SENZA deploy

## Obiettivo

Preparare istruzioni e checklist per una futura pubblicazione autorizzata senza modificare servizi esterni.

## Verifica/prepara

- configurazione produzione documentata;
- `.env.example` completo senza segreti;
- backup/ripristino documentati;
- migrazioni/import DB;
- permessi directory/file se necessari;
- HTTPS/cookie Secure come requisito di produzione;
- configurazione SMTP descritta senza credenziali;
- checklist DNS/web record da comunicare al provider, senza applicarla;
- cache/build asset se prevista;
- smoke test post-deploy da eseguire in futuro;
- rollback plan;
- `DELIVERY_CHECKLIST` aggiornata.

## Divieti

Non pubblicare, non accedere/modificare DNS, nameserver, MX/SPF/DKIM/DMARC, non acquistare hosting/servizi, non importare dati reali distruttivamente.

## Output

Crea `docs/RELEASE_GUIDE.md` con passaggi numerati, prerequisiti, punti di autorizzazione e rollback. Segna chiaramente ciò che NON è stato eseguito.
