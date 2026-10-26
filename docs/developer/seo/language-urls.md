# Language folder in the URL

With **SEO URLs on** and **two or more enabled languages**, a non-default language lives in a
folder: `/uk/kategoriya`. The default language keeps its plain URLs (`/kategoriya`). One URL, one
language: an unprefixed page request is always the default language, whatever the session,
cookie or browser says.

## Folder name

The language code before the dash (`uk-ua` => `uk`). If two enabled languages share it (`en-gb`,
`en-us`) both use the full code. No setting; `MintCart\Seo\LanguagePrefix` derives it.

## Pieces

- `startup/mc_language_prefix` (runs before `startup/startup`): reads the folder from `_route_`,
  sets the session language, strips the folder. `/en/foo` for the default language is a 301 to
  `/foo`. `route=...` requests without `_route_` (AJAX, callbacks) keep the session language.
- `startup/mc_language_url` (runs after `startup/seo_url`): puts the current language's folder in
  front of every link built with `$this->url->link()`. `alternates()` returns the current page's URL
  in every language (the SEO keywords are looked up per language).
- Language switcher (`common/language`): each language is a plain link (`href`).
- `<link rel="alternate" hreflang>` in the header, with `x-default` = the default language;
  none on cart, checkout, account, error and other non-indexed pages.

## Server

The stock `.htaccess` rule (`index.php?_route_=$1`) already carries `/uk/...` through; nothing to add.
Nginx: the usual `try_files $uri $uri/ /index.php?_route_=$uri&$args` form works the same way.

## Not covered yet

The XML sitemap lists one language; per-language URLs there, and redirects for stores that already
had indexed non-default-language pages at unprefixed URLs, are separate work.
