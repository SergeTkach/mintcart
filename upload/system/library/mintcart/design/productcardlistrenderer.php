<?php
namespace MintCart\Design;

use MintCart\Commerce\ProductViewProvider;
use MintCart\Core\Logger;

/**
 * Renders a stream of raw OpenCart product rows into `product-card`
 * component HTML via ProductViewProvider + ComponentRenderer, shared across
 * every listing controller (MINTCART_TECHNICAL_PLAN.md §15C/§15D,
 * MINTCART_IMPLEMENTATION_GUIDE.md §38D: Category/Search/Manufacturer/
 * Special/Blocks must all receive the same normalized ProductView — one
 * `product-card` override must apply to every listing context).
 *
 * ControllerProductCategory (Phase 8) originally inlined this exact
 * sequence — construct ComponentRenderer + ProductViewProvider, assemble the
 * component's shared `text_*` strings once, then per product: build the
 * ProductView and render it, logging and disabling the pipeline for the
 * rest of the request on the first failure so a broken/missing template
 * degrades to "no product cards" instead of a fatal error for every
 * visitor. Manufacturer is this class's second real consumer
 * (CLAUDE.md §85) — extracting it now, rather than a third inline copy,
 * is exactly what MINTCART_TECHNICAL_PLAN.md §15C asks for.
 *
 * Deliberately does NOT touch the product query/pagination/filter side of
 * listing controllers (that remains each controller's own
 * `model_catalog_product->getProducts()` call) — this class only replaces
 * the "turn one already-fetched result row into product-card HTML" step.
 * A full `ProductListingService` covering the query side too is a larger,
 * separate piece of work with no consumer yet (CLAUDE.md §85) and stays
 * out of scope here.
 */
final class ProductCardListRenderer
{
    private ComponentRenderer $componentRenderer;

    private ProductViewProvider $productViewProvider;

    /**
     * @var array<string, mixed>
     */
    private array $text;

    private Logger $logger;

    private bool $failed = false;

    /**
     * @param array<string, mixed> $text Component-shared language strings:
     *        `badge` (array{sale: string, out-of-stock: string, new: string}),
     *        `wishlist`, `compare`, `rated`, `tax`, `add_to_cart`,
     *        `quick_order` — see docs/developer/components/product-card.md.
     */
    public function __construct(
        ComponentRenderer $componentRenderer,
        ProductViewProvider $productViewProvider,
        array $text,
        ?Logger $logger = null
    ) {
        $this->componentRenderer = $componentRenderer;
        $this->productViewProvider = $productViewProvider;
        $this->text = $text;
        $this->logger = $logger ?? new Logger();
    }

    /**
     * True once rendering has failed once — callers should stop calling
     * render() for the rest of the request (same "give up after the first
     * failure" behavior ControllerProductCategory established).
     */
    public function hasFailed(): bool
    {
        return $this->failed;
    }

    /**
     * @param array<string, mixed> $product Same shape ProductViewProvider::build() expects.
     * @param array<string, mixed> $display
     * @param array<string, mixed> $context
     *
     * @return string|null Rendered `product-card` HTML, or null if this call
     *         (or an earlier one on this instance) failed.
     */
    public function render(array $product, array $display = [], array $context = []): ?string
    {
        if ($this->failed) {
            return null;
        }

        try {
            $productView = $this->productViewProvider->build($product, $display, $context);

            return $this->componentRenderer->render('product-card', $productView + [
                'text_badge' => $this->text['badge'] ?? [],
                'text_wishlist' => $this->text['wishlist'] ?? '',
                'text_compare' => $this->text['compare'] ?? '',
                'text_rated' => $this->text['rated'] ?? '',
                'text_tax' => $this->text['tax'] ?? '',
                'text_add_to_cart' => $this->text['add_to_cart'] ?? '',
                'text_quick_order' => $this->text['quick_order'] ?? '',
            ]);
        } catch (\Throwable $e) {
            $this->logger->error(
                'ProductCardListRenderer failed to render product-card for product_id '
                . ($product['id'] ?? '?') . ': ' . $e->getMessage()
            );

            $this->failed = true;

            return null;
        }
    }
}
