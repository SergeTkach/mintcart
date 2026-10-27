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

## Notes
- `/mens` (no parent in the URL) opens the first sub-category with that alias. Links the site generates always contain the parents.
- Changing a category's parent can create a clash with its new siblings; the category form checks it on save.
- Existing data needs no migration: a rule that was stricter is now looser.
