<?php
namespace MintCart\Commerce;

/**
 * Normalizes the raw `total` extension pipeline's output
 * (`MintCart\Compatibility\OpenCart3\TotalsAdapter::calculate()`) into the
 * Theme API's checkout-totals contract - the same normalization role
 * `ProductViewProvider`/`CartService::snapshot()` play elsewhere.
 *
 * Deliberately has no OpenCart dependency (no `\Registry`, no `\Currency`)
 * so it is unit-testable in isolation - the caller supplies a
 * `formatMoney` callback (e.g. `$this->currency->format(...)`) rather than
 * this class reaching for OpenCart's currency service itself, the same
 * "presentation values resolved upstream, this class only reshapes the
 * array" split `CartService::snapshot()` already uses for cart totals.
 */
final class CheckoutTotalsService
{
    /**
     * @param list<array{code: string, title: string, value: float, sort_order?: int}> $rawTotals
     *        `TotalsAdapter::calculate()['totals']` - already sorted by
     *        `sort_order`; this method does not re-sort.
     * @param callable(float): string $formatMoney Formats a raw numeric
     *        total value into a display string, e.g.
     *        `fn ($value) => $this->currency->format($value, $this->session->data['currency'])`.
     *
     * @return list<array{code: string, title: string, text: string}>
     *         `sort_order`/raw `value` are intentionally dropped from the
     *         public contract - order is already correct on the way in,
     *         and a caller that needs the raw number for anything beyond
     *         display has `TotalsAdapter::calculate()`'s own return value
     *         available upstream of this call.
     */
    public function normalize(array $rawTotals, callable $formatMoney): array
    {
        $lines = [];

        foreach ($rawTotals as $line) {
            $lines[] = [
                'code'  => $line['code'],
                'title' => $line['title'],
                'text'  => $formatMoney($line['value']),
            ];
        }

        return $lines;
    }
}
