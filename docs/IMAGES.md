# Immagini: censimento, provenienza e procedura

Stato al 2026-10-06 (Fase 6). **Nessuna fotografia è pubblicata dal nuovo sito**: le pagine mostrano segnaposto marcati ("Fotografia in arrivo") perché nessuna immagine del legacy ha provenienza verificata (SPEC §23). Il censimento dettagliato dei file è in `docs/AUDIT.md` (sezione Immagini); qui il riepilogo operativo.

## Cosa esiste in `legacy/src/assets` (~33 MB, 64 file)

| Classe | File | Stato | Azione |
|---|---|---|---|
| Probabilmente proprie (EXIF iPhone, 2564–4032 px) | `fotoDintorni/fotoAgri1–5.jpeg` | DA VERIFICARE | chiedere conferma al titolare; poi ottimizzare |
| 1024×651, origine tipica di un sito/portale precedente | `fotoAppartamenti/agri1.jpg`, `appartamento6/8/9/10.jpg`, `fotoGenerali/piscina1/2.jpg` | DA VERIFICARE | chiedere l'origine al titolare |
| Miniature a bassa risoluzione | `appartamento1–4.jpeg`, `appartamenti16.jpeg`, `appartamento12.jpeg`, `appartamento5.jpg`, `fotoGenerali/agri2.jpeg`, `fotoDintorni/laVolta.jpeg` | DUBBIA | sostituire con foto proprie |
| Screenshot | `appartamento13/14/15.png`, `laVolta2.jpeg`, `laVolta4/5.png` | DUBBIA | non usare |
| Fotografia di uno studio esterno | `fotoDintorni/appartamentoFoto.jpg` | DUBBIA (diritti di terzi) | non usare senza licenza |
| Dintorni, probabile terzi | `Berzieri`, `Busseto`, `Torrechiara`, `CastelloTabiano`, `castello-fontanellato`, `vigoleno`, `Stirone2`, `castell-arquato`, `grazzanoVisconti`, `PanoramaSalso2/3`, `fotoSalso5` | DUBBIA | non usare senza fonte/licenza |
| Originali fotocamera senza EXIF | `fotoGenerali/agri5/6/7.JPG` (4176×2784) | DA VERIFICARE | chiedere al titolare |
| Marchi di terzi, non pertinenti, icone | `icon/tripadvisorLogo.*`, `minion.jpeg`, `spritz3.png`, `dragon.png`, `icon/*Logo.png` | non utilizzabili | non portare nel nuovo sito |
| Hero attuale | hotlink a un sito terzo, non raffigura La Volta | da sostituire | serve una foto propria |

Il nuovo sito non copia nessuno di questi file e non fa hotlink (verificato da test: nessun `<img>` e nessuna risorsa esterna nelle pagine). Gli originali restano in `legacy/` finché la cartella non viene eliminata (decisione P4).

## Cosa è già pronto per quando arrivano le foto verificate

- `app/Site/ImageSet.php` + `templates/public/_photo.php`: se alla vista si passa un'immagine (`base`, `widths`, `width`, `height`, `alt`) produce `<picture>` con sorgenti WebP e JPEG di riserva, `srcset`/`sizes`, `width` e `height` (nessuno spostamento del layout), `loading="lazy"` sotto la prima schermata e `fetchpriority="high"` per la hero (`eager`). Verificato da `tests/Unit/ImageSetTest.php`.
- `bin/optimize-images.php <originale> <nome> [larghezze…]`: genera `public/assets/img/<nome>-<larghezza>.webp` e `.jpg` (480/800/1200/1600, mai ingrandendo, originale intatto). **NON ESEGUITO**: il container di sviluppo non ha l'estensione GD con WebP; lo script la richiede ed esce con un messaggio chiaro se manca. In alternativa si possono produrre le varianti con qualsiasi strumento (Squoosh, `cwebp`) rispettando i nomi.
- AVIF non è generato (richiede strumenti non disponibili); WebP + JPEG coprono tutti i browser attuali.

## Cosa manca (decisione dell'utente, 2026-10-06)

Una **gestione delle foto nel DB e nell'admin non è stata realizzata**: si farà quando ci sarà un set verificato (almeno: hero, foto per ciascun appartamento, testo alternativo IT/EN). Oggi non esiste un modo per associare una foto a un appartamento: le pagine usano il segnaposto.

## Procedura quando il titolare fornisce le foto

1. Registrare per ciascuna file originale, autore/fonte e conferma di proprietà o licenza (anche in questo file).
2. Conservare l'originale fuori da `public/` (es. cartella `originals/` non pubblicata).
3. Generare le varianti (`bin/optimize-images.php` su una macchina con GD/WebP, oppure uno strumento esterno).
4. Scrivere testo alternativo IT/EN descrittivo (non "foto").
5. Collegare la foto alla pagina (con la gestione foto, o provvisoriamente nei template) e controllare che hero e prima schermata non siano `lazy`.
