<?php
namespace MintCart\Onboarding;

/**
 * Computes "how ready is this store to take an order"
 * (MINTCART_PRODUCT_VISION.md §3/§6.1, MINTCART_TECHNICAL_PLAN.md §22,
 * MINTCART_IMPLEMENTATION_GUIDE.md §55) — a normalized checklist + a
 * percentage that is *derived* from the checklist, never hardcoded
 * (CLAUDE.md §22: "Не hardcode percentage").
 *
 * Deliberately has no OpenCart dependency (no \Registry, no \DB, no
 * \Config) so it is unit-testable in isolation, same "resolved values in,
 * normalized contract out" shape as `CartService`/`ProductViewProvider`.
 * `ModelMintcartReadiness` (admin/model/mintcart/readiness.php) is the one
 * place that knows how each boolean is actually derived from OpenCart's
 * own existing storage (config settings, `oc_language`/`oc_currency`
 * status, the `extension` table + its `<type>_<code>_status` settings,
 * `catalog/product`'s own product count) — no new Mint-specific storage
 * for any of this, per CLAUDE.md §39/§6.
 */
final class StoreReadiness
{
    /**
     * Checklist item keys, in the fixed display order Functional Scope §2
     * lists them (Store Readiness `checks` above `logo/contacts/currency/
     * language/shipping/payment/first product/test-order possible`),
     * mapped to their default label. A caller may override a label via
     * `$labels` (e.g. for translation) without changing the key/order
     * contract downstream UI (dashboard widget, Wizard "Ready" step) reads.
     */
    private const DEFAULT_LABELS = [
        'logo' => 'Store logo uploaded',
        'contacts' => 'Store contact details configured',
        'language' => 'Storefront language configured',
        'currency' => 'Storefront currency configured',
        'shipping' => 'At least one shipping method enabled',
        'payment' => 'At least one payment method enabled',
        'first_product' => 'At least one product added',
    ];

    /**
     * @param array{
     *     logo?: bool,
     *     contacts?: bool,
     *     language?: bool,
     *     currency?: bool,
     *     shipping?: bool,
     *     payment?: bool,
     *     first_product?: bool,
     * } $signals Already-resolved booleans. A missing key is treated as
     *        incomplete (false) rather than throwing, so a caller can pass
     *        a partial signal set while a signal is still unimplemented.
     * @param array<string, string> $labels Optional label overrides, keyed
     *        by the same checklist keys as `$signals` (e.g. translated
     *        strings). Unknown keys are ignored; missing keys fall back to
     *        `DEFAULT_LABELS`.
     *
     * @return array{
     *     percent: int,
     *     checks: list<array{key: string, label: string, complete: bool}>,
     *     ready_for_test_order: bool
     * }
     */
    public function evaluate(array $signals, array $labels = []): array
    {
        $checks = [];
        $completeCount = 0;

        foreach (self::DEFAULT_LABELS as $key => $defaultLabel) {
            $complete = !empty($signals[$key]);

            $checks[] = [
                'key' => $key,
                'label' => $labels[$key] ?? $defaultLabel,
                'complete' => $complete,
            ];

            if ($complete) {
                $completeCount++;
            }
        }

        $total = count($checks);
        $percent = $total > 0 ? (int) round($completeCount / $total * 100) : 0;

        // "Ready for a test order" is a narrower, more specific claim than
        // the overall percentage: a store can be 100% ready on paper (logo,
        // contacts, language, currency all set) and still not be able to
        // actually complete a checkout without a purchasable product plus
        // both a shipping and a payment method wired up.
        $readyForTestOrder = !empty($signals['first_product'])
            && !empty($signals['shipping'])
            && !empty($signals['payment']);

        return [
            'percent' => $percent,
            'checks' => $checks,
            'ready_for_test_order' => $readyForTestOrder,
        ];
    }
}
