<?php
namespace MintCart\Catalog;

/**
 * Extracts the "sort" dropdown option list that
 * `catalog/controller/product/category.php`, `manufacturer.php`,
 * `search.php` (and, once wired, `special.php`) each build with an
 * identical inline block: the same nine (or seven, when reviews are
 * disabled) `sort`/`order`/language-key combinations, in the same order,
 * gated by the same `config_review_status` check
 * (`MINTCART_TECHNICAL_PLAN.md` §15C/§15E/§38C "reusable Product Listing
 * pipeline" / "SortOptionsBuilder" candidate).
 *
 * Deliberately OpenCart-independent, same "primitive first, wire the
 * controllers in a later increment" pattern
 * `MintCart\Seo\CanonicalUrlBuilder`/`MintCart\Catalog\CategoryMoveValidator`
 * already established (`CLAUDE.md` §85): it never calls
 * `$this->language->get()` or `$this->url->link()` itself, so it has no
 * opinion on the active language or route. The controller maps each
 * returned `text_key` through its own already-loaded language file and
 * builds the `href` with its own `Url::link()` — this class only owns the
 * list, its order and the review-status gate.
 */
final class SortOptionsBuilder
{
    /**
     * @return list<array{sort: string, order: string, text_key: string}>
     *   `sort`/`order` are the exact query values the controller already
     *   passes straight through to the product model's `getProducts()`
     *   call; `text_key` is the language key each controller's own
     *   `catalog/language/en-gb/product/*.php` file already defines.
     */
    public function build(bool $reviewEnabled): array
    {
        $options = [
            ['sort' => 'p.sort_order', 'order' => 'ASC', 'text_key' => 'text_default'],
            ['sort' => 'pd.name', 'order' => 'ASC', 'text_key' => 'text_name_asc'],
            ['sort' => 'pd.name', 'order' => 'DESC', 'text_key' => 'text_name_desc'],
            ['sort' => 'p.price', 'order' => 'ASC', 'text_key' => 'text_price_asc'],
            ['sort' => 'p.price', 'order' => 'DESC', 'text_key' => 'text_price_desc'],
        ];

        if ($reviewEnabled) {
            $options[] = ['sort' => 'rating', 'order' => 'DESC', 'text_key' => 'text_rating_desc'];
            $options[] = ['sort' => 'rating', 'order' => 'ASC', 'text_key' => 'text_rating_asc'];
        }

        $options[] = ['sort' => 'p.model', 'order' => 'ASC', 'text_key' => 'text_model_asc'];
        $options[] = ['sort' => 'p.model', 'order' => 'DESC', 'text_key' => 'text_model_desc'];

        return $options;
    }
}
