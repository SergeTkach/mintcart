# ADR 019 — SEO keyword scope: per language, per parent category

## Context
OpenCart 3 requires a SEO keyword to be unique in the whole store: across every language and across every kind
of page. Two wishes were blocked by that:

1. the same product may have the same keyword in every language (`/white-mug` in `en` and in `uk`);
2. two sub-categories with the same alias under different parents (`clothes/mens`, `shoes/mens`).

The decoder (`startup/seo_url`) also matched each URL segment on keyword alone, so it could not tell the two
`mens` apart.

## Decision
- A keyword is unique per **store and language**. The "same keyword in another language of this form" check
  (`error_unique`) is gone from the product, category, information and brand forms.
- A **nested category** (parent_id > 0) must only differ from its **siblings**. Everything else (products, pages,
  brands, top-level categories, system pages) stays one shared **root** namespace per store and language.
- The decoder resolves a segment in the context of the previous one: after a category segment it looks among
  that category's children first, then at the root level, then (old bookmarks to a bare sub-category) at any row.
- One implementation of the rule for the admin: `ModelMintcartSeoUrl::keywordTaken()` / `uniqueKeyword()`, used by
  the four catalog forms, the manual SEO URL screen, System pages and the URL Generator.
- No schema change: no unique index existed; the rule lives in code.

## Consequences
- `clothes/mens` and `shoes/mens` work; `/mens` alone still opens the first one (old links).
- Outgoing links, canonical URLs, redirects and the sitemap were already built from category paths and need no change.
- Several stores of one installation keep separate scopes, as before.
- Identical keywords in several languages only make sense with the language folder in the URL (ADR 017,
  `/uk/white-mug`); without it one address would serve different languages by cookie and hreflang cannot point at it.
- Third-party SEO extensions that replace `startup/seo_url` do not know this rule.
- Upstream change: `startup/seo_url.php`, four catalog controllers, `design/seo_url.php` (see `docs/upstream-diff.md`).
