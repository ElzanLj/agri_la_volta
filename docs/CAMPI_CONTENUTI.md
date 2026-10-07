# Contratto dei campi amministrabili — BOZZA

Stato: **BOZZA del 2026-10-07**, da verificare e approvare nel prompt 16. I prompt 17–27 implementano e testano ogni riga; il prompt 28 controlla (con un test) che ogni campo dei moduli admin abbia la sua riga. Se una fase aggiunge un campo, aggiunge qui la sua riga.

## Regole valide per tutti i campi

1. **Spazi**: gli spazi all'inizio e alla fine si tolgono; un campo fatto solo di spazi è **vuoto**.
2. **HTML**: nessun HTML viene interpretato. `<b>ciao</b>` compare così com'è; ogni valore è sempre stampato con escape (anche in `<title>`, meta, Open Graph, JSON-LD, email, CSV).
3. **Formattazione dei testi lunghi**: secondo il prompt 16 (D2): riga vuota = nuovo paragrafo; righe che iniziano con `- ` = elenco.
4. **Errori**: messaggio accanto al campo, collegato con `aria-describedby`, riepilogo in cima al modulo; tutti i valori inseriti restano nel modulo; nulla viene salvato se c'è un errore.
5. **Frase "se lo lasci vuoto"**: ogni campo facoltativo ha sotto una frase che dice cosa succede sul sito se resta vuoto (colonna "Se vuoto, sul sito").
6. **Limiti**: il limite massimo è scritto sotto il campo ("massimo 160 caratteri") e verificato dal server; i campi per Google hanno un **avviso** oltre la lunghezza consigliata, non un blocco.
7. **Italiano e inglese**: l'italiano è la lingua principale. Dove l'inglese manca vale la regola del prompt 16 (D3) salvo indicazione diversa qui sotto; il campo inglese vuoto è segnalato con "manca EN" negli elenchi e nella checklist.
8. **Link**: solo `https://`; rifiutati `javascript:`, `data:`, `http:`; aperti con `rel="noopener noreferrer"` se esterni.
9. **Storico**: ogni salvataggio registra valori vecchi e nuovi (mai password); i testi si possono ripristinare dallo Storico (prompt 25, se approvato).
10. **Segnalazioni**: tre livelli: frase sotto il campo · indicatori negli elenchi ("manca EN", "senza foto", "vuota") · checklist "pronto per la pubblicazione" in dashboard.
11. **Storico**: nei valori vecchi e nuovi dello Storico entrano i testi dei contenuti (pagine, impostazioni) ma **mai** campi con dati personali o motivi liberi (decisione del prompt 15).
12. **Campi personali nuovi**: ogni campo che contiene dati di un ospite (nota interna, orario di arrivo, canale…) aggiorna l'anonimizzazione e il test che scansiona **tutto** il database dopo `erase` (prompt 15, test D1 della review).
13. **Blocco ottimistico**: ogni modulo di modifica porta la data dell'ultima modifica; se è cambiata prima del salvataggio, si avvisa senza perdere ciò che è stato scritto (helper del prompt 18).

Legenda: **Sì** = obbligatorio · **Sì*** = obbligatorio solo in certe condizioni (spiegate nella riga).

## Impostazioni (prompt 18)

| Campo | Obbl. | Limiti e formato | Se vuoto, sul sito | Segnalato |
|---|---|---|---|---|
| Telefono fisso | no | numero con prefisso, 6–20 cifre | non compare; il JSON-LD lo omette | checklist (se vuoti entrambi i telefoni) |
| Cellulare | no | come sopra | non compare | come sopra |
| Email pubblica | no | email valida, max 254 | non compare | checklist |
| Indirizzo | no | max 300, più righe | non compare; niente indirizzo nel JSON-LD | checklist |
| WhatsApp | no | numero valido (normalizzato con prefisso predefinito) | pulsante WhatsApp nascosto ovunque | checklist |
| Orari in cui rispondete al telefono (IT/EN) | no | max 100 | non compare accanto al telefono | — |
| Ragione sociale | no | max 200 | non compare nel piè di pagina | checklist (consulente: spesso obbligatoria) |
| Partita IVA | no | 11 cifre | non compare | checklist |
| REA | no | max 30 | non compare | — |
| Link Google Maps | no | `https://`, max 500 | niente pulsante "Apri in Google Maps" | — |
| Recensioni TripAdvisor / Google | no | `https://`, max 500 | link non mostrati | — |
| Instagram / Facebook (se approvati) | no | `https://`, max 500 | icone/link non mostrati | — |
| Avviso globale: attivo | — | sì/no | nessun avviso | — |
| Avviso globale: testo IT | Sì* | max 200; obbligatorio se l'avviso è attivo | — | errore se attivo e vuoto |
| Avviso globale: testo EN | no | max 200 | sulle pagine inglesi l'avviso **non** compare | "manca EN" |
| Avviso globale: fino al | no | data futura | resta finché non lo spegni | dashboard: "avviso attivo da più di 60 giorni" |
| Orari comuni di arrivo/partenza | no | ora; "arrivo dalle" < "arrivo entro le" | gli appartamenti senza orari propri non mostrano orari | checklist |
| Regole della casa comuni IT/EN | no | max 2000 | gli appartamenti senza regole proprie non mostrano la sezione | — |

## Impostazioni aggiunte da altre fasi

| Campo | Fase | Obbl. | Limiti e formato | Se vuoto, sul sito | Segnalato |
|---|---|---|---|---|---|
| Preavviso minimo (giorni) | 23 | no | 0–30 | 0 = arrivo anche oggi | — |
| Condizioni di cancellazione e pagamento IT/EN | 23 | no | max 3000, niente coordinate bancarie | il riepilogo e la conferma non le mostrano | checklist |
| Nota tassa di soggiorno IT/EN | 23 | no | max 300 | non compare | — |
| Messaggio personale nell'email di conferma IT/EN | 24 | no | max 1000 | l'email resta quella standard | — |
| Soglia "richieste in attesa da troppo" (ore) | 24 | no | 12–168 | 48 | — |
| Email: server, porta, cifratura, utente | 26 | Sì* | host valido, porta 25/465/587/2525, `tls` o `ssl` (`none` solo per `localhost`) | le email restano in coda (nessuna persa) | checklist + stato del sistema |
| Email: password | 26 | Sì* | mai mostrata; vuoto = invariata | come sopra | come sopra |
| Mittente, nome mittente | 26 | Sì* | email valida, max 100 | le email restano in coda | checklist |
| Destinatario delle notifiche | 26 | Sì* | email valida | le notifiche di nuova richiesta restano in coda | checklist |
| Conservazione dati (mesi) | 27 | no | 6–120 | nessuna pulizia automatica | stato del sistema |
| Frase sui tempi di risposta IT/EN | 25 | no | max 150 | non compare nella pagina "ricevuta" né nell'email di ricevuta | — |
| Conservazione dei log (giorni) | 26 | no | 30–365 | 90 | stato del sistema |
| Spazio massimo per le foto (MB) | 19 | no | 200–20000 | 1500; avviso all'80% | stato del sistema |
| Modalità manutenzione | 26 | — | sì/no | sito pubblico in 503 con i contatti; admin accessibile | dashboard se attiva da più di 1 ora |

## Foto (prompt 19)

| Campo | Obbl. | Limiti e formato | Se vuoto, sul sito | Segnalato |
|---|---|---|---|---|
| File | Sì | JPEG/PNG/WebP, limite approvato (es. 10 MB), max 40 milioni di pixel | — | errore sul campo |
| Testo alternativo IT | Sì | 5–150 | — | errore sul campo |
| Testo alternativo EN | no | 5–150 | sulle pagine inglesi si usa il testo italiano | "manca EN" in Foto |
| Credito / autore | no | max 150 | non compare (resta interno) | — |
| Dichiarazione di provenienza | Sì | casella | — | non si può caricare senza |

## Appartamenti (esistenti + prompt 20 e 23)

| Campo | Obbl. | Limiti e formato | Se vuoto, sul sito | Segnalato |
|---|---|---|---|---|
| Nome | Sì | 2–100 | — | errore |
| Capienza massima | no | 1–50 | nessun limite di capienza | checklist |
| Minimo persone + interruttore | Sì* | 1–capienza; obbligatorio se la regola è attiva | regola spenta: almeno 1 adulto | errore se attiva e vuoto, o se supera la capienza |
| Massimo bambini / animali | no | 0–20 (animali: 0 = non ammessi) | nessun limite | — |
| Camere, posti letto | no | 0–30 / 0–60 | non compaiono | checklist |
| Piano, accessibile senza scale | no | max 50 / sì-no | non compaiono | — |
| Orari propri | no | come gli orari comuni | si usano gli orari comuni | — |
| Prezzo indicativo | no | importo ≥ 0 | "Da … a notte" non compare | avviso se inferiore alla tariffa minima attiva |
| Descrizione breve IT/EN | no | max 160 | la scheda mostra solo i dati numerici | "manca EN" |
| Descrizione IT | no | max 5000 | sezione "Descrizione" assente | checklist ("appartamento senza descrizione") |
| Descrizione EN | no | max 5000 | regola D3 del prompt 16 | "manca EN" |
| Regole della casa IT/EN | no | max 2000 | si usano le regole comuni | — |
| Titolo per Google IT/EN | no | avviso oltre 60 | si usa il nome dell'appartamento | — |
| Descrizione per Google IT/EN | no | avviso oltre 155 | si usa la descrizione breve, poi l'inizio della descrizione | "manca" in Appartamenti |
| Galleria | no | ordine; la prima è la copertina | segnaposto "Fotografia in arrivo"; nessuna `og:image` | "senza foto" + checklist |
| Servizi | no | dal catalogo attivo | sezione "Servizi" assente | — |

## Servizi (prompt 20)

| Campo | Obbl. | Limiti e formato | Se vuoto, sul sito | Segnalato |
|---|---|---|---|---|
| Nome IT | Sì | 2–60, unico | — | errore |
| Nome EN | no | 2–60 | sulle pagine inglesi si usa il nome italiano | "manca EN" |
| Attivo | — | sì/no | se non attivo non compare da nessuna parte | — |

## Pagine (prompt 21)

| Campo | Obbl. | Limiti e formato | Se vuoto, sul sito | Segnalato |
|---|---|---|---|---|
| Titolo IT | no | max 120 | si usa il titolo attuale di `content/it.php` | — |
| Titolo EN | no | max 120 | si usa il titolo attuale di `content/en.php` | — |
| Introduzione IT/EN | no | max 1000 | non compare | — |
| Titolo per Google IT/EN | no | avviso oltre 60 | si usa il titolo della pagina | — |
| Descrizione per Google IT/EN | no | avviso oltre 155 | si usa l'introduzione accorciata, poi la descrizione attuale di `content/*.php` | "manca" in Pagine |
| Foto principale (solo home) | no | dalla libreria | segnaposto attuale; nessuna `og:image` | checklist |
| Bozza (privacy, cookie) | — | sì/no | se attiva compare l'avviso "Bozza" | checklist ("privacy in bozza") |

## Sezioni di pagina (prompt 21)

| Campo | Obbl. | Limiti e formato | Se vuoto, sul sito | Segnalato |
|---|---|---|---|---|
| Titolo IT | Sì* | max 120; obbligatorio se la sezione è visibile | — | errore se visibile e vuoto |
| Testo IT | Sì* | max 5000; testo **o** foto obbligatori se visibile | — | errore se visibile senza testo né foto |
| Titolo/testo EN | no | come IT | regola D3 del prompt 16 | "manca EN" |
| Foto (o galleria, se approvata) | no | dalla libreria | nessuna immagine | — |
| Link: testo + indirizzo | no | testo max 60, indirizzo `https://`; entrambi o nessuno | nessun link | errore se uno solo dei due |
| Visibile | — | sì/no | nascosta = mai nel sorgente pubblico | indicatore "nascosta" |

## Listino (esistente, prompt 5; strumenti nel 22)

Valgono le validazioni già presenti in `PricingConfigService` (periodi senza sovrapposizioni, date valide, importi ≥ 0, unità gratuite 0 per i supplementi a soggiorno). Date senza tariffa: il sito mostra "il gestore ti indicherà il prezzo"; segnalato nella checklist.

## Controlli tra campi

| Regola | Dove |
|---|---|
| Minimo persone ≤ capienza massima | Appartamento |
| Massimo bambini ≤ capienza massima | Appartamento |
| "Arrivo dalle" < "arrivo entro le" | Appartamento, Impostazioni |
| Avviso globale attivo → testo IT presente | Impostazioni |
| Sezione visibile → titolo e (testo o foto) | Pagine |
| Link: testo e indirizzo insieme | Sezioni |
| Foto in uso → non eliminabile | Foto |
| Servizio in uso → non eliminabile | Servizi |
| Password SMTP salvabile solo con `APP_SECRET` impostato | Email |

## Test minimi per ogni campo

Per ogni riga: vuoto · solo spazi · troppo lungo · formato non valido · con HTML/`<script>` · resa sul sito in IT e EN · voce nello Storico.
