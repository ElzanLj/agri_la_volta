# Prompt 09 — IT/EN, SEO, accessibilità, performance e immagini

## Obiettivo

Portare l'interfaccia pubblica ai requisiti di qualità della specifica senza inventare contenuti mancanti.

## Multilingua

- IT/EN separati;
- niente traduzione automatica runtime;
- URL coerenti;
- metadata appropriati alla lingua;
- hreflang quando appropriato.

## Accessibilità

Verifica e correggi almeno: semantica, skip link, tastiera, focus visibile, label, errori associati, annunci asincroni, menu mobile, `aria-expanded` quando necessario, alt text, contrasto, touch target e `prefers-reduced-motion`. Non aggiungere ARIA inutile.

## SEO

Lingua pagina, title/description unici, canonical, Open Graph, favicon, robots.txt, sitemap, breadcrumb, URL leggibili, 404/errori e rendering indicizzabile. Schema.org solo con dati reali.

## Immagini

- censisci originali e varianti;
- segnala provenienza incerta;
- niente hotlink;
- WebP/AVIF quando appropriato;
- responsive images;
- width/height;
- lazy loading fuori prima schermata;
- hero ottimizzata;
- preserva gli originali validi.

## Performance

Riduci JS, librerie/font/asset inutili e duplicazioni. Non sacrificare manutenibilità per micro-ottimizzazioni premature.

## Verifica

Manuale mobile, desktop, tastiera, pagine principali, IT/EN; usa strumenti automatici disponibili solo come supporto e registra ciò che hai realmente eseguito.

Aggiorna `TEST_REPORT`, `TODO`, `MISSING_DATA`, `SESSION_STATE`.
