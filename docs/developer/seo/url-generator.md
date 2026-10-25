# SEO -> URL Generator

Screen `mintcart/seo/url_generator`, SEO URL auto-fill in the admin forms, and the batch generator. All of it
writes to the stock `seo_url` table (store 0, keyword per language); nothing is stored twice.

## Keyword rules

`MintCart\Seo\Slugger::slug($text, $languageCode)`: HTML removed, lower case, Ukrainian and Russian letters
transliterated to Latin (Ukrainian national table, KMU 2010: `г`->`h`, `и`->`y`, `є ї й ю я` -> `ye yi y yu ya` at the
start of a word and `ie i i iu ia` inside it; Russian `г`->`g`, `и`->`i`), common Latin diacritics folded, anything else
a dash, at most 100 characters (cut at a dash). `Slugger::unique()` adds `-2`, `-3` ... until the keyword is free.

A keyword is unique in the **store across all languages**, because OpenCart's own product / category / page / brand
forms refuse a keyword another page - or another language of the same page - already has.

## Templates

`MintCart\Seo\UrlTemplates` (setting `mintcart_seo_url`, JSON `{"product": "{brand} {name}"}`): a template per type, in the
meta tag template syntax (`MetaTemplate`), rendered and then slugified. Variables: product `{name} {brand} {category}
{model}`, category `{name} {parent}`, information `{name}`, brand `{name}`. A keyword cannot contain `/`, so the parts are
joined with dashes. Default `{name}`; a template that renders empty falls back to the name.

## Auto-fill in the forms

`assets/src/js/components/seo-url-autofill.js` (in the admin bundle) fills an **empty** "SEO URL" field of a language while the
name / title of that language is typed (debounced), asking `mintcart/seo/url_generator/slug`. A keyword that was already
there, or that the user typed, is never touched; the one the script filled keeps following the name until the user edits it.
Keywords already typed in the other languages of the form are sent as `avoid[]`. The bundle is loaded on those forms by events
`admin/controller/catalog/{product,category,information,manufacturer}/{add,edit}/before` -> `mintcart/seo/url_generator/assets`
(migration `202610251400_seo_url_autofill.php`): no OpenCart template is patched.

## Missing keywords

The screen lists, per type and language, how many items have no keyword (after an import, for example) and generates
them in batches of 50 (`run`): `{created, remaining}`. Existing keywords are never changed - a changed URL breaks links and
indexed pages. An item whose name gives an empty keyword gets `<type>-<id>`.

Tests: `tests/mintcart/seo/slugger-test.php`.

## A language added in the Setup Wizard

`ControllerMintcartWizard::addLanguage()` (Language & currency step), after the language is created: for `uk-ua` the Ukrainian
text of the demo catalogue is applied (`system/mintcart/data/demo-uk-ua.sql`), then keywords are generated for the new
language from its own names (`ModelMintcartSeoUrl::generateMissing()`, the first 300 items of each type; a bigger catalogue
finishes in SEO -> URL Generator). The Ukrainian demo file does not copy the English keywords: they would repeat, and a keyword
must be unique in the store. A language added directly in Localisation > Languages gets neither the translation nor the
keywords - run URL Generator for it.
