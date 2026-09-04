<?php
namespace MintCart\Seo;

/**
 * Builds the `rel="alternate" hreflang="..."` link set for a page
 * (`MINTCART_FUNCTIONAL_SCOPE.md` §4/§16, `MINTCART_TECHNICAL_PLAN.md`
 * §31 "hreflang" under Structured Data/Metadata) — no multi-language SEO
 * signal of this kind exists anywhere in this repository today (unlike
 * canonical, which most listing controllers already emit via
 * `$this->document->addLink(..., 'canonical')`).
 *
 * Deliberately has no OpenCart dependency (no \Registry, no \DB, no
 * `\Language`/`\Url`) so it is unit-testable in isolation — same shape as
 * `MintCart\Commerce\ProductViewProvider`/`SearchService`/`CartService`.
 * Resolving which languages a store has enabled and building each one's
 * equivalent URL for the current page stays the caller's job upstream of
 * this class; this class only assembles, validates and de-duplicates the
 * `{hreflang, href}` pairs into the shape a template can render directly.
 *
 * OpenCart's own `oc_language.code` values (e.g. `en-gb`, `de-de`,
 * `uk-ua`) are already valid `hreflang` values as-is — BCP 47 language
 * tags are case-insensitive and a region subtag is joined with a hyphen,
 * so `en-gb` and `en-GB` are the same tag. No code-format translation
 * layer is needed here.
 */
final class HreflangBuilder
{
    /**
     * @param list<array{hreflang?: string, href?: string}> $alternates
     *        One entry per language a page has an equivalent URL for.
     *        Caller-resolved (a store's enabled `oc_language` rows plus
     *        each one's URL for the current route) — this class never
     *        talks to OpenCart's language/URL services itself. An entry
     *        missing/blank `hreflang` or `href` is skipped rather than
     *        thrown on: one bad entry (e.g. a language whose URL failed
     *        to resolve) must not drop every other language's tag.
     * @param string|null $defaultHreflang When set and it matches one of
     *        `$alternates`' `hreflang` values, that same URL is also
     *        emitted under the special `x-default` tag — Google's
     *        documented convention for "which version to show when no
     *        other tag matches the visitor's language/region"
     *        (distinct from a `default` *language*, which stays a normal
     *        entry in the list under its own real `hreflang`).
     *
     * @return list<array{hreflang: string, href: string}> De-duplicated
     *         (first occurrence of a given `hreflang` wins), in input
     *         order, with a synthesized `x-default` entry appended last
     *         when applicable.
     */
    public function build(array $alternates, ?string $defaultHreflang = null): array
    {
        $result = [];
        $seen = [];
        $defaultHref = null;

        foreach ($alternates as $alternate) {
            $hreflang = isset($alternate['hreflang']) ? trim((string) $alternate['hreflang']) : '';
            $href = isset($alternate['href']) ? trim((string) $alternate['href']) : '';

            if ($hreflang === '' || $href === '') {
                continue;
            }

            if (isset($seen[$hreflang])) {
                continue;
            }

            $seen[$hreflang] = true;
            $result[] = ['hreflang' => $hreflang, 'href' => $href];

            if ($defaultHreflang !== null && $hreflang === $defaultHreflang) {
                $defaultHref = $href;
            }
        }

        if ($defaultHref !== null) {
            $result[] = ['hreflang' => 'x-default', 'href' => $defaultHref];
        }

        return $result;
    }
}
