<?php
namespace MintCart\Commerce;

/**
 * Decides whether the current request should run OpenCart core's
 * stale-cart cleanup query (see MC-0008, `docs/fixes/registry.md`).
 *
 * `\Cart\Cart::__construct()` runs an unconditional
 * `DELETE FROM ` . DB_PREFIX . `cart WHERE ...` query on every single
 * request, because `Cart` is instantiated once per page load by both
 * `catalog/controller/startup/startup.php` and
 * `admin/controller/startup/startup.php` — not only when a cart is
 * actually touched. On a typical store, the overwhelming majority of
 * requests (anonymous browsing, search, static pages) have nothing to
 * clean up, so this is a write query paid by every single page view.
 *
 * The fix does not remove the cleanup — abandoned/ownerless carts still
 * need to expire — it only gates *how often* it runs, the same
 * probability-gate pattern PHP's own session garbage collection uses
 * (`session.gc_probability` / `session.gc_divisor`). Deliberately has no
 * OpenCart dependency so the gating decision is unit-testable in
 * isolation; the query itself stays in `Cart::__construct()`.
 */
final class CartGarbageCollector
{
    /** @var int */
    private $probability;

    /** @var int */
    private $divisor;

    public function __construct(int $probability = 5, int $divisor = 100)
    {
        $this->probability = $probability;
        $this->divisor = $divisor;
    }

    /**
     * @return bool Whether this request should run the cleanup query.
     */
    public function shouldRun(): bool
    {
        if ($this->probability <= 0) {
            return false;
        }

        if ($this->probability >= $this->divisor) {
            return true;
        }

        return mt_rand(1, $this->divisor) <= $this->probability;
    }
}
