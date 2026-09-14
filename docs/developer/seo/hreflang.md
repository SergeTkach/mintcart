# `MintCart\Seo\HreflangBuilder`

Assembles the `<link rel="alternate" hreflang="...">` set a multi-language
store's page should emit (`MINTCART_FUNCTIONAL_SCOPE.md` §4/§16 name
`hreflang` explicitly under SEO Core; `MINTCART_TECHNICAL_PLAN.md` §31
groups it under "Metadata"). No hreflang signal of any kind exists
anywhere in this repository before this class — unlike canonical, which
most listing controllers already emit via
`$this->document->addLink(..., 'canonical')`.

PHP: `upload/system/library/mintcart/seo/hreflangbuilder.php`
(class `MintCart\Seo\HreflangBuilder`)

Test: `tests/mintcart/seo/hreflang-builder-test.php`
(`php tests/mintcart/seo/hreflang-builder-test.php`, 10 scenarios)

**Consumer: none yet.** This is a shipped, tested, documented contract with
no live controller wiring — see "Why no live wiring yet" below. Same
"ship a tested contract before its consumer" precedent already set in this
codebase (`CLAUDE.md` §85), e.g. `MintCart\Commerce\SearchService` before
a `mc-search-box` UI existed, or `SchemaOrgBuilder::product()` before
`mint_default` had a product detail page template.

## Design

`HreflangBuilder` has **no OpenCart dependency** — no `\Registry`, `\DB`,
`\Language`, `\Url`. Same shape as `ProductViewProvider`/`SearchService`/
`CartService`/`SchemaOrgBuilder`: a caller resolves the raw per-language
data (which languages a store has enabled, and each one's absolute URL for
the current route) upstream; this class only assembles, validates and
de-duplicates the `{hreflang, href}` pairs into a shape a template can loop
over directly.

### `build(array $alternates, ?string $defaultHreflang = null): array`

```text
$alternates: [{hreflang, href}, ...]   // one per language with an equivalent URL
$defaultHreflang: 'en-gb' | null       // which entry, if any, is also x-default

returns: [{hreflang, href}, ...]       // de-duplicated, input order, x-default appended last
```

- An entry missing or blank `hreflang`/`href` is **skipped, not thrown
  on** — one language's URL failing to resolve upstream must not drop
  every other language's tag from the page.
- A duplicate `hreflang` (two entries for the same language) keeps only
  the **first** occurrence.
- When `$defaultHreflang` is given and matches one of the (valid,
  de-duplicated) entries, that entry's URL is also emitted under the
  special `x-default` tag, appended last — [Google's documented
  convention](https://developers.google.com/search/docs/specialty/international/localized-versions)
  for "which version to show when no other tag matches the visitor's
  language/region", distinct from a `default` *language* itself (which
  stays a normal entry under its own real `hreflang`, e.g. `en-gb`).

### No BCP 47 conversion needed

OpenCart's own `oc_language.code` values (`en-gb`, `de-de`, `uk-ua`, ...)
are already valid `hreflang` values as-is: BCP 47 language tags are
case-insensitive, and a region subtag is joined with a hyphen — `en-gb`
and `en-GB` are the same tag. A caller can pass `oc_language.code` straight
through as `hreflang` with no translation layer.

## Why no live wiring yet

Building the actual per-language alternate URL for "this same page, in
that other language" needs OpenCart's own SEO-URL/route machinery run
**as if** `config_language_id` were switched to each other enabled
language for the current request — every listing/product controller in
this repo (`category.php`, `search.php`, `manufacturer.php`,
`special.php`, `product.php`, `common/home.php`) builds its links via
`$this->url->link(...)`, which resolves against the *current* session
language only; there is no existing helper anywhere in this codebase (Mint
or stock OpenCart) that resolves "this route, in a different language."
Building that resolver is a real, separate piece of work, not something
`HreflangBuilder` itself should grow into (it stays a pure assembler, same
principle `SchemaOrgBuilder` follows for JSON-LD structure vs. the raw
values a controller resolves).

The natural live-wiring point once that resolver exists is
`ControllerCommonHeader::index()` (`upload/catalog/controller/common/header.php`)
— hreflang links, like Organization JSON-LD (`docs/developer/seo/schema-org.md`,
once merged), belong in `<head>` on every Mint-aware page, not duplicated
per listing controller. That file (and `mint_default/template/common/header.twig`)
is also where an in-flight PR (`docs/IMPLEMENTATION_STATUS.md`'s Phase 18
"Schema.org" entry) already adds the site-wide Organization schema block —
deliberately not touched by this increment to avoid two open PRs editing
the same lines of the same shared page-chrome files at once.

## What this is not

- **Not a language-URL resolver.** Building each alternate's `href` (the
  hard, OpenCart-specific part) stays out of scope for this class — see
  "Why no live wiring yet" above.
- **Not wired into any controller or template yet.** No page emits
  hreflang links today; this ships the tested contract those call sites
  will build on.
- **Not a full internationalization layer.** Region-only variants (e.g.
  `en` vs. `en-GB` vs. `en-US` as genuinely different targets) are the
  caller's data to supply — this class treats every `hreflang` string
  opaquely and never inspects or re-derives it.

## Definition of Done checklist

- [x] Documented contract (this file)
- [x] No OpenCart dependency — unit-testable in isolation
- [x] No OCMOD, no modification to any existing OpenCart file (this
      increment adds only new files)
- [x] Smoke test covering empty input, pass-through, missing-key
      skipping, de-duplication, and `x-default` synthesis (10 scenarios)
- [ ] A language-URL resolver that can build `$alternates` for a real
      OpenCart route — not built yet, see "Why no live wiring yet"
- [ ] Live controller/template wiring (`ControllerCommonHeader` +
      `mint_default/template/common/header.twig`) — blocked on the above,
      and deliberately sequenced after the in-flight Schema.org PR to
      avoid a two-PR collision on the same shared header files
- [ ] Live verification against a real install — this environment has no
      MySQL server (same pre-existing gap noted throughout
      `docs/IMPLEMENTATION_STATUS.md`)
