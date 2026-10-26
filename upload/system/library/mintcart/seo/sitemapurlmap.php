<?php
namespace MintCart\Seo;

/**
 * Page URLs for the sitemap, in any language, from one in-memory copy of the
 * `seo_url` table. A sitemap lists every page in every language, and asking
 * `$this->url->link()` for each of them is one database query per link; on a
 * big catalog that is what makes a sitemap run out of time half way. Here the
 * table is read once and a link is string work only.
 *
 * Produces the same URLs `startup/seo_url` + `startup/mc_language_url` do:
 * `base[folder/]keyword[/keyword]`, falling back to the plain
 * `index.php?route=...` form when a language has no keyword for the page.
 */
final class SitemapUrlMap
{
    /** @var array<string, array<string, string>> language code => query => keyword */
    private array $keywords;

    private string $base;

    private LanguagePrefix $prefix;

    /**
     * @param array<string, array<string, string>> $keywords e.g. ['uk-ua' => ['product_id=5' => 'chorni-krosivky']]
     */
    public function __construct(array $keywords, string $base, LanguagePrefix $prefix)
    {
        $this->keywords = $keywords;
        $this->base = rtrim($base, '/') . '/';
        $this->prefix = $prefix;
    }

    /** product_id, manufacturer_id or information_id page. */
    public function entity(string $code, string $route, string $key, int $id): string
    {
        $keyword = $this->keywords[$code][$key . '=' . $id] ?? '';

        return $this->finish($code, $keyword !== '' ? $keyword : 'index.php?route=' . $route . '&' . $key . '=' . $id);
    }

    /** @param string $path category path, e.g. "20_27" */
    public function category(string $code, string $path): string
    {
        $parts = [];

        foreach (explode('_', $path) as $id) {
            $keyword = $this->keywords[$code]['category_id=' . (int)$id] ?? '';

            if ($keyword === '') {
                return $this->finish($code, 'index.php?route=product/category&path=' . $path);
            }

            $parts[] = $keyword;
        }

        return $this->finish($code, implode('/', $parts));
    }

    private function finish(string $code, string $tail): string
    {
        return $this->prefix->link($this->base . $tail, [$this->base], $code);
    }
}
