<?php
namespace MintCart\Design;

/**
 * Renders one `product-card` component per product row in a listing,
 * composing `MintCart\Commerce\ProductViewProvider` (normalizes the row)
 * with `ComponentRenderer` (resolves + renders the template) — the shared
 * per-product step `MINTCART_TECHNICAL_PLAN.md` §15C/§15D and `CLAUDE.md`
 * §8A ask every listing controller (Category, Search, Manufacturer,
 * Special, Blocks) to reuse instead of duplicating.
 *
 * `ControllerProductCategory` (Phase 8) originally inlined this loop with
 * its own try/catch-and-disable-for-the-rest-of-the-request degrade
 * behaviour; `docs/upstream-diff.md`'s category.php entry explicitly named
 * that extraction as expected "once Search/Manufacturer/Special need the
 * same thing". `ControllerProductSearch` (this run) is that second real
 * consumer, so the shared shape is extracted here and both controllers now
 * use it.
 *
 * Deliberately holds no reference to the calling controller (`Registry`,
 * `Config`, `Language`, ...) — it only ever sees the already-resolved
 * `product`/`display`/`context`/language-string arrays a controller passes
 * in, so it stays unit-testable the same way `ComponentRenderer` and
 * `ProductViewProvider` are.
 *
 * `$slotRegistry` (optional 3rd constructor argument, `null` by default)
 * is `MintCart\Design\SlotRegistry` (`MINTCART_TECHNICAL_PLAN.md` §15,
 * `MINTCART_IMPLEMENTATION_GUIDE.md` §35 "Extension slots") — the first
 * real wiring of `product-card.twig`'s documented `product.after_actions`
 * slot. `null` (every existing call site, byte-for-byte unaffected) keeps
 * `render()` doing exactly what it always did — no `slots` key is added to
 * the template data, matching `ComponentRenderer`'s own optional
 * `$diagnostics` precedent for a zero-behavior-change-by-default addition.
 */
final class ProductCardListRenderer
{
    private ComponentRenderer $componentRenderer;

    private \MintCart\Commerce\ProductViewProvider $productViewProvider;

    private ?SlotRegistry $slotRegistry;

    /**
     * Once a product in this list fails to render, every further product in
     * the same list is skipped too — a broken/missing `product-card.twig`
     * degrades the whole grid for the rest of the request rather than
     * throwing per-product errors that would fatal the page, matching
     * `ControllerProductCategory`'s original behaviour exactly.
     */
    private bool $enabled = true;

    public function __construct(
        ComponentRenderer $componentRenderer,
        ?\MintCart\Commerce\ProductViewProvider $productViewProvider = null,
        ?SlotRegistry $slotRegistry = null
    ) {
        $this->componentRenderer = $componentRenderer;
        $this->productViewProvider = $productViewProvider ?? new \MintCart\Commerce\ProductViewProvider();
        $this->slotRegistry = $slotRegistry;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * @param array<string, mixed> $product Passed straight through to
     *        `ProductViewProvider::build()` as its `$product` argument.
     * @param array<string, mixed> $display Passed straight through as
     *        `ProductViewProvider::build()`'s `$display` argument.
     * @param array<string, mixed> $context Passed straight through as
     *        `ProductViewProvider::build()`'s `$context` argument.
     * @param array<string, mixed> $languageStrings The component's
     *        documented `text_*` template variables
     *        (`docs/developer/components/product-card.md`).
     * @param callable|null $onError Invoked with `(\Throwable $e, array $product)`
     *        when rendering fails, before this renderer disables itself for
     *        the rest of the list — the caller decides how/where to log it
     *        (each controller keeps its own `MintCart\Core\Logger` message
     *        wording).
     *
     * @return string|null The rendered `product-card` markup, or `null` if
     *         this renderer is disabled (this call or an earlier one in the
     *         same list failed) — the caller should skip the product, not
     *         treat `null` as an error itself.
     */
    public function render(
        array $product,
        array $display,
        array $context,
        array $languageStrings,
        ?callable $onError = null
    ): ?string {
        if (!$this->enabled) {
            return null;
        }

        try {
            $productView = $this->productViewProvider->build($product, $display, $context);

            if ($this->slotRegistry !== null) {
                $productView['slots'] = [
                    'product.after_actions' => $this->slotRegistry->render(
                        'product.after_actions',
                        $productView['product']
                    ),
                ];
            }

            return $this->componentRenderer->render('product-card', $productView + $languageStrings);
        } catch (\Throwable $e) {
            $this->enabled = false;

            if ($onError !== null) {
                $onError($e, $product);
            }

            return null;
        }
    }
}
