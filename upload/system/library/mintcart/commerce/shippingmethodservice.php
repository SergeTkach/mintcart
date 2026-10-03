<?php
namespace MintCart\Commerce;

/**
 * Normalizes `MintCart\Compatibility\OpenCart3\ShippingQuoteAdapter::calculate()`'s
 * raw output into the Theme API's checkout-shipping-methods contract - the
 * same normalization role `CheckoutTotalsService` plays for totals, applied
 * to shipping method quotes.
 *
 * Deliberately has no OpenCart dependency, same as `CheckoutTotalsService` -
 * but unlike it, takes no `$formatMoney` callback: every stock
 * `extension/shipping/*.php` model already returns its quote's `text` key
 * pre-formatted via `$this->currency->format()` (see e.g.
 * `upload/catalog/model/extension/shipping/flat.php`), so there is no raw
 * numeric value left for this class to format - only to reshape.
 */
final class ShippingMethodService
{
    /**
     * @param array<string, array{
     *     title: string,
     *     quote: array<string, array{code: string, title: string, cost: float, tax_class_id: int, text: string}>,
     *     sort_order: int,
     *     error: bool
     * }> $methodData `ShippingQuoteAdapter::calculate()`'s return value -
     *        already sorted by `sort_order`; this method does not re-sort.
     *
     * @return list<array{
     *     method: string,
     *     options: list<array{id: string, title: string, text: string}>
     * }> One entry per shipping extension (its group title plus every
     *   quote option it offers - most extensions offer exactly one, a few
     *   like table-rate ones can offer several). `options[].id` is the
     *   exact `"<method_code>.<quote_key>"` string
     *   `checkout/shipping_method.php::save()` already expects back in
     *   `$this->request->post['shipping_method']` - a Mint-native checkout
     *   UI can post this value straight through to that existing endpoint
     *   without any translation layer, per `MINTCART_TECHNICAL_PLAN.md`
     *   §27's "poverh standard OpenCart ... totals; order model."
     *   `sort_order`/`error`/`cost`/`tax_class_id` are dropped from the
     *   public shape - no real consumer needs them yet (`CLAUDE.md` §85).
     */
    public function normalize(array $methodData): array
    {
        $methods = [];

        foreach ($methodData as $code => $method) {
            $options = [];

            foreach ($method['quote'] as $quoteKey => $quote) {
                $options[] = [
                    'id'    => $code . '.' . $quoteKey,
                    'title' => $quote['title'],
                    'text'  => $quote['text'],
                ];
            }

            $methods[] = [
                'method'  => $method['title'],
                'options' => $options,
            ];
        }

        return $methods;
    }
}
