# ADR 017: Language folder in the URL

## Context
OpenCart 3 picks the language from session, cookie or Accept-Language, so every language shares one
URL. Search engines cannot index a translation, and hreflang has no address to point at.

## Decision
A non-default language is served from a folder (`/uk/...`), the default language from the root.
A page request without a folder is always the default language. Two MintCart startup controllers
do the work; the folder name is derived from the language code (no new setting or table). Active
only with SEO URLs on and at least two enabled languages.

## Consequences
Each language has its own crawlable URLs and hreflang alternates. Stores that already had
non-default-language pages indexed at unprefixed URLs need redirects (not built). Two small core
edits (pre-action list, switcher and header data) are listed in `docs/upstream-diff.md`.
