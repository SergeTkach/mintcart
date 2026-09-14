<?php
namespace MintCart\Seo;

/**
 * Builds a valid XML sitemap (sitemaps.org protocol) from a normalized list
 * of URL entries — `MINTCART_FUNCTIONAL_SCOPE.md` §16 /
 * `MINTCART_TECHNICAL_PLAN.md` §31's last still-unclaimed Phase 18 (SEO
 * Core) checklist item; #66's and #70's own "Next steps" notes both name
 * it explicitly as unclaimed.
 *
 * Same pure, OpenCart-independent shape as `HreflangBuilder`/
 * `RobotsBuilder`/`SchemaOrgBuilder` (this same `MintCart\Seo` namespace):
 * it only assembles and validates XML from data the caller already
 * resolved. Enumerating real product/category/manufacturer/information
 * URLs (via OpenCart's own models — no `MintCart\Compatibility` adapter
 * exists yet per `MINTCART_TECHNICAL_PLAN.md` §5) and wiring an actual
 * `mintcart/seo/sitemap.xml` route stays a live-DB follow-up this
 * environment (no MySQL) can't verify end to end — the same deferral every
 * sibling `MintCart\Seo` class in this backlog already documented for
 * itself (see `docs/developer/seo/sitemap.md`).
 */
final class SitemapBuilder
{
    /**
     * sitemaps.org protocol hard limit: a single sitemap file must not list
     * more than 50,000 URLs (or exceed 50MB uncompressed). MintCart enforces
     * only the URL-count half here — a real product/category/manufacturer/
     * information URL list is nowhere near byte-size territory before it
     * would hit 50,000 entries first, and the caller controls entry size.
     */
    public const MAX_URLS_PER_SITEMAP = 50000;

    private const CHANGEFREQ_VALUES = ['always', 'hourly', 'daily', 'weekly', 'monthly', 'yearly', 'never'];

    /**
     * @param list<array{
     *     loc: string,
     *     lastmod?: string|\DateTimeInterface|null,
     *     changefreq?: string|null,
     *     priority?: float|int|null
     * }> $entries Already-resolved absolute URLs (via `$this->url->link(...)`
     *        upstream, the same "presentation values already resolved
     *        upstream" contract `SearchService::results()`/
     *        `ProductViewProvider::build()` use) — this class does no URL
     *        generation, DB access, or routing of its own.
     *
     * @return string A complete `<?xml ...?><urlset ...>...</urlset>`
     *         document, UTF-8, always well-formed even for empty/all-invalid
     *         input (an empty `<urlset>` is valid per the protocol).
     */
    public function urlset(array $entries): string
    {
        $normalized = array_slice($this->normalize($entries), 0, self::MAX_URLS_PER_SITEMAP);

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($normalized as $entry) {
            $xml .= "\t<url>\n";
            $xml .= "\t\t<loc>" . $this->escape($entry['loc']) . "</loc>\n";

            if ($entry['lastmod'] !== null) {
                $xml .= "\t\t<lastmod>" . $this->escape($entry['lastmod']) . "</lastmod>\n";
            }

            if ($entry['changefreq'] !== null) {
                $xml .= "\t\t<changefreq>" . $entry['changefreq'] . "</changefreq>\n";
            }

            if ($entry['priority'] !== null) {
                $xml .= "\t\t<priority>" . $entry['priority'] . "</priority>\n";
            }

            $xml .= "\t</url>\n";
        }

        $xml .= '</urlset>' . "\n";

        return $xml;
    }

    /**
     * A sitemap index document referencing multiple `urlset()` files — the
     * protocol's own answer to a catalog large enough to exceed
     * `MAX_URLS_PER_SITEMAP` (a real concern for XML sitemaps specifically,
     * not a hypothetical — CLAUDE.md §17 covers speculative abstractions,
     * not a documented protocol limit paired one-to-one with `urlset()`).
     *
     * @param list<array{loc: string, lastmod?: string|\DateTimeInterface|null}> $sitemaps
     *
     * @return string A complete `<?xml ...?><sitemapindex ...>...</sitemapindex>` document.
     */
    public function sitemapIndex(array $sitemaps): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        $seen = [];

        foreach ($sitemaps as $sitemap) {
            $loc = trim((string) ($sitemap['loc'] ?? ''));

            if ($loc === '' || isset($seen[$loc])) {
                continue;
            }

            $seen[$loc] = true;

            $xml .= "\t<sitemap>\n";
            $xml .= "\t\t<loc>" . $this->escape($loc) . "</loc>\n";

            $lastmod = $this->formatLastmod($sitemap['lastmod'] ?? null);
            if ($lastmod !== null) {
                $xml .= "\t\t<lastmod>" . $this->escape($lastmod) . "</lastmod>\n";
            }

            $xml .= "\t</sitemap>\n";
        }

        $xml .= '</sitemapindex>' . "\n";

        return $xml;
    }

    /**
     * @return list<array{loc: string, lastmod: string|null, changefreq: string|null, priority: string|null}>
     */
    private function normalize(array $entries): array
    {
        $seen = [];
        $result = [];

        foreach ($entries as $entry) {
            $loc = trim((string) ($entry['loc'] ?? ''));

            if ($loc === '' || isset($seen[$loc])) {
                continue;
            }

            $seen[$loc] = true;

            $changefreq = $entry['changefreq'] ?? null;
            if ($changefreq !== null && !in_array($changefreq, self::CHANGEFREQ_VALUES, true)) {
                $changefreq = null;
            }

            $priority = $entry['priority'] ?? null;
            if ($priority !== null) {
                $priority = number_format(max(0.0, min(1.0, (float) $priority)), 1);
            }

            $result[] = [
                'loc' => $loc,
                'lastmod' => $this->formatLastmod($entry['lastmod'] ?? null),
                'changefreq' => $changefreq,
                'priority' => $priority,
            ];
        }

        return $result;
    }

    /**
     * @param string|\DateTimeInterface|null $lastmod
     */
    private function formatLastmod($lastmod): ?string
    {
        if ($lastmod instanceof \DateTimeInterface) {
            return $lastmod->format('c');
        }

        if (is_string($lastmod)) {
            $lastmod = trim($lastmod);

            return $lastmod === '' ? null : $lastmod;
        }

        return null;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
