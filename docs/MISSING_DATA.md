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

## Regola

Se una feature dipende da una di queste informazioni, implementa la struttura configurabile e usa un placeholder chiaramente identificato solo dove previsto dalla specifica. Non trasformare un placeholder in dato reale senza fonte.


## Come aggiornare questo file

Usa stati coerenti: `MANCANTE`, `DA DEFINIRE`, `DA VERIFICARE`, `FORNITO`.

Quando un'informazione viene fornita, registra la fonte/nota e la data senza cancellare la storia utile. Non inserire segreti reali (es. password SMTP); registra soltanto che sono disponibili tramite il canale/configurazione appropriato.
