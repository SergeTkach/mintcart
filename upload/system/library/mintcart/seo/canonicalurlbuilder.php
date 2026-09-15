<?php
namespace MintCart\Seo;

/**
 * Computes the canonical/prev/next pagination link query suffixes for a
 * paginated listing (`MINTCART_FUNCTIONAL_SCOPE.md` §16 "SEO Core" —
 * canonical; `MINTCART_TECHNICAL_PLAN.md` §31 "URL management").
 *
 * `catalog/controller/product/category.php`, `manufacturer.php`,
 * `search.php` and `special.php` each carry an identical inline block
 * (comment and all: "http://googlewebmastercentral.blogspot.com/2011/09/
 * pagination-with-relnext-and-relprev.html") that decides, given the
 * current page/limit/total, whether page 1 gets a bare canonical link or
 * later pages get `&page=N` appended, whether a `prev` link is emitted at
 * all (and whether *it* carries `&page=N` or is bare for page 2 → page 1),
 * and whether a `next` link exists. This class is a pure, OpenCart-
 * independent extraction of that shared decision — the same
 * "primitive first, wire the controllers in a later increment" pattern
 * `MintCart\Update\PackageManifest` and `MintCart\Catalog\CategoryMoveValidator`
 * already established (`CLAUDE.md` §85).
 *
 * Route names and each listing's own non-pagination query parameters
 * (`path=`, `manufacturer_id=`, the search filter set, ...) stay the
 * controller's job — this class only ever sees the already-built
 * "everything except page" query suffix (`$baseQuery`) and returns the
 * three page-aware variants of it. It never calls `Url::link()` itself, so
 * it has no opinion on SSL/route/language/currency — same separation of
 * concerns `ProductViewProvider` keeps from URL generation.
 */
final class CanonicalUrlBuilder
{
    /**
     * @return array{canonical: string, prev: ?string, next: ?string}
     *   Each present value is a query suffix suitable for
     *   `Url::link($route, $suffix)` — never a full URL. `prev`/`next` are
     *   `null` when that link should not be emitted at all (page 1 has no
     *   `prev`; the last page has no `next`).
     */
    public function build(int $page, int $limit, int $total, string $baseQuery = ''): array
    {
        $page = max(1, $page);
        $limit = max(0, $limit);
        $total = max(0, $total);

        $totalPages = $limit > 0 ? (int) ceil($total / $limit) : 0;

        $result = [
            'canonical' => $this->withPage($baseQuery, $page),
            'prev' => null,
            'next' => null,
        ];

        if ($page > 1) {
            $result['prev'] = $this->withPage($baseQuery, $page - 1);
        }

        if ($limit > 0 && $totalPages > $page) {
            $result['next'] = $this->withPage($baseQuery, $page + 1);
        }

        return $result;
    }

    /**
     * Page 1 never carries a `page` param (matches every controller's
     * existing "page == 1 → bare link" branch); `Url::link()` always
     * prefixes `$args` with its own `&` and `ltrim()`s any existing
     * leading `&`, so joining with a plain `&page=N` is safe whether or
     * not `$baseQuery` is empty.
     */
    private function withPage(string $baseQuery, int $page): string
    {
        return $page > 1 ? $baseQuery . '&page=' . $page : $baseQuery;
    }
}
