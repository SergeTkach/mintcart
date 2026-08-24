<?php
namespace MintCart\Commerce;

/**
 * Normalizes cart data for the Theme API's Cart contract
 * (MINTCART_TECHNICAL_PLAN.md §25, MINTCART_IMPLEMENTATION_GUIDE.md §58) —
 * the same normalization role `ProductViewProvider` plays for a single
 * product, applied to the cart as a whole.
 *
 * Deliberately has no OpenCart dependency (no \Registry, no \DB, no
 * `Cart\Cart`) so it is unit-testable in isolation — fetching cart rows,
 * resizing thumbs, formatting currency, and mutating the cart itself
 * (`$this->cart->add()`/`update()`/`remove()`) all stay
 * `ControllerMintcartCart`'s job upstream of this class, exactly the same
 * split `ProductViewProvider` already established for product listings.
 */
final class CartService
{
    /**
     * Which of a product's required options are missing from what was
     * submitted. Mirrors (does not duplicate the storage/session side of)
     * `catalog/controller/checkout/cart.php::add()`'s own required-option
     * check — that stock method is untouched; this is the same validation
     * rule expressed as pure array logic so a second caller
     * (`ControllerMintcartCart::add()`) doesn't have to re-derive it.
     *
     * @param list<array{product_option_id: int|string, required: bool}> $productOptions
     *        Already-fetched option definitions for the product
     *        (`model_catalog_product->getProductOptions()`'s shape).
     * @param array<int|string, mixed> $submittedOptions Keyed by
     *        `product_option_id`, whatever value(s) were submitted.
     *
     * @return list<int|string> The `product_option_id`s that are required
     *         but missing/empty in `$submittedOptions`. Empty = valid.
     */
    public function validateOptions(array $productOptions, array $submittedOptions): array
    {
        $missing = [];

        foreach ($productOptions as $option) {
            if (empty($option['required'])) {
                continue;
            }

            $id = $option['product_option_id'];
            $value = $submittedOptions[$id] ?? null;

            if ($value === null || $value === '' || $value === []) {
                $missing[] = $id;
            }
        }

        return $missing;
    }

    /**
     * @param list<array{
     *     cart_id: int|string,
     *     product_id: int|string,
     *     name: string,
     *     url: string,
     *     thumb: string,
     *     quantity: int,
     *     price: string,
     *     total: string,
     *     option_summary?: list<array{name: string, value: string}>|null
     * }> $items Already-resolved cart rows (thumb resized, price/total
     *        already formatted via `$this->currency`) — same
     *        "presentation values already resolved upstream" contract
     *        `ProductViewProvider::build()` uses for a single product.
     * @param array{subtotal: string, total: string, tax?: string|null} $totals
     *        Already-formatted summary figures
     *        (`$this->cart->getSubTotal()`/`getTotal()`, formatted by the
     *        caller) — deliberately not OpenCart's full extension-totals
     *        breakdown (shipping/coupon/etc.), which stays checkout's job.
     *
     * @return array{
     *     count: int,
     *     subtotal: string,
     *     total: string,
     *     items: list<array<string, mixed>>,
     *     totals: list<array{title: string, text: string}>
     * }
     */
    public function snapshot(array $items, array $totals): array
    {
        $count = 0;

        foreach ($items as $item) {
            $count += (int) $item['quantity'];
        }

        $totalsList = [];

        if (isset($totals['subtotal'])) {
            $totalsList[] = ['title' => 'Sub-Total', 'text' => $totals['subtotal']];
        }

        if (!empty($totals['tax'])) {
            $totalsList[] = ['title' => 'Tax', 'text' => $totals['tax']];
        }

        if (isset($totals['total'])) {
            $totalsList[] = ['title' => 'Total', 'text' => $totals['total']];
        }

        return [
            'count' => $count,
            'subtotal' => $totals['subtotal'] ?? '',
            'total' => $totals['total'] ?? '',
            'items' => array_values($items),
            'totals' => $totalsList,
        ];
    }
}
