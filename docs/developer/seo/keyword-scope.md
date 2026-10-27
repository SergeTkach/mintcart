# SEO keyword scope

Which keyword (alias) may be used where. Decision: `docs/adr/019-seo-keyword-scope.md`.

| Case | Allowed? |
|---|---|
| The same product / page / category with the same keyword in several languages | yes (needs the language folder in the URL for hreflang, ADR 017) |
| Two different pages with the same keyword in the same language at the root level | no |
| A product and a nested category with the same keyword | yes (the nested one is only reached through its parent) |
| Two sub-categories of the **same** parent with the same keyword | no |
| Sub-categories of **different** parents with the same keyword (`clothes/mens`, `shoes/mens`) | yes |

"Root level" = products, information pages, brands, top-level categories and system pages.

## Where the rule lives
- Admin check and generator: `ModelMintcartSeoUrl::keywordTaken($keyword, $store_id, $language_id, $ignore_query, $parent_id)`
  (`upload/admin/model/mintcart/seo_url.php`). `$parent_id` is the parent of a category, `null` for everything else.
  Used by Catalog > Products / Categories / Information / Brands, Design > SEO URL, SEO > System pages and the
  URL Generator (`uniqueKeyword()` adds `-2`, `-3`, ... inside the same scope).
- Decoder: `ControllerStartupSeoUrl::findKeyword()` (`upload/catalog/controller/startup/seo_url.php`). Order: current
  language, then any language; among several rows with that keyword: a child of the category resolved by the previous
  segment, then a root-level row, then the first row.
- Tests: `tests/mintcart/seo/keyword-scope-test.php`.

## Caveats

1. **The same keyword in several languages needs the language folder in the URL** (ADR 017, `/uk/white-mug`). Without it
   one address serves different languages by cookie or browser setting, and hreflang cannot point at it. Turn the
   language folder on before using identical keywords across languages.
2. **A bare sub-category alias is ambiguous.** `/mens` (no parent in the URL) opens the first sub-category with that alias.
   Links the site generates always contain the parents; old bookmarks to a bare alias keep working through this fallback.
3. **Third-party SEO extensions** that replace `startup/seo_url` (or write their own `seo_url` rows) do not know this rule;
   they can create keywords the decoder then resolves in the first-row order, or refuse the ones this scope allows.
4. **Direct core change.** The decoder and the form validation sit in stock controllers (`docs/upstream-diff.md`, "SEO
   keyword scope"). A merge from upstream OpenCart has to keep them.
5. **A category's parent change can create a clash** with its new siblings; the category form checks it on save, but data
   edited straight in the database is not checked.
6. **Not clicked through yet.** The rule is unit-tested with an in-memory database
   (`tests/mintcart/seo/keyword-scope-test.php`); it has not been tried in the admin and on a running store (two categories
   with the same alias under different parents, the same product keyword in two languages, an old `/mens` link).
7. **The scope is per store.** Several stores of one installation keep separate scopes, as before.

## Notes
- Existing data needs no migration: a rule that was stricter is now looser.
- No unique index exists on `seo_url.keyword`, so nothing in the schema had to change.
