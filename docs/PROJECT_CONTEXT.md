# Project context — sintesi operativa

> Documento sintetico. In caso di dubbio prevale `docs/SPEC.md`.

## Obiettivo

Rendere il sito dell'Agriturismo La Volta professionale, veloce, accessibile, sicuro, semplice da mantenere e installabile su hosting Linux condiviso compatibile con PHP e MySQL/MariaDB.

## Repository di partenza

`https://github.com/ElzanLj/agri_la_volta`

## Funzioni principali richieste

- presentazione agriturismo e singoli appartamenti;
- fotografie, servizi, descrizioni e prezzi;
- disponibilità indicativa;
- richiesta soggiorno pubblica senza account;
- calcolo prezzo;
- approvazione/rifiuto manuale admin;
- prenotazioni manuali da altri canali;
- blocchi di disponibilità;
- email;
- area amministrativa semplice;
- IT/EN;
- responsive, accessibilità, SEO e performance.

## Appartamenti

Margherita, Girasole, Rosa, Mimosa, Ciclamino, Viola.

Ogni appartamento deve avere una pagina pubblica dedicata e indicizzabile.

## Flusso pubblico

1. check-in/check-out;
2. adulti/bambini/animali;
3. disponibilità e prezzo;
4. appartamenti disponibili;
5. selezione appartamento;
6. dati cliente;
7. riepilogo;
8. consenso privacy;
9. invio;
10. salvataggio DB;
11. messaggio “Richiesta ricevuta”.

La richiesta non è una prenotazione confermata finché l'admin non la approva.

## Stati minimi

- `pending`
- `confirmed`
- `rejected`
- `cancelled`

## Intervalli

`[check_in, check_out)`: il giorno di checkout non è occupato per la prenotazione successiva.

## Origini prenotazione

Almeno: `website`, `phone`, `email`, `agency`, `novasol`, `other`.

## Admin minimo

Login/logout, nuove richieste, filtri, dettaglio, conferma/rifiuto, prenotazione manuale, cancellazione, blocchi, calendario semplice, prezzi/regole, informazioni appartamenti, export CSV e storico modifiche.

## Email

SMTP configurabile via environment. Database prima, email dopo. Email nuova richiesta al gestore; conferma/rifiuto al cliente; cancellazione come bozza modificabile e non inviata automaticamente.

## Produzione e servizi esterni

- Dominio di produzione: `agriturismolavolta.com`.
- Email principale: `info@agriturismolavolta.com`.
- Non modificare dominio, DNS o configurazione posta senza autorizzazione.
- Nessun pagamento online.
- Niente integrazioni automatiche Novasol/Booking/Airbnb/iCal per ora.
- Niente tracker marketing al lancio se non necessari.

## Dati ancora mancanti

Vedi `docs/MISSING_DATA.md`. In particolare non inventare prezzi/regole tariffarie, Novasol, SMTP, testi/traduzioni definitive, fotografie/licenze e informazioni legali.
