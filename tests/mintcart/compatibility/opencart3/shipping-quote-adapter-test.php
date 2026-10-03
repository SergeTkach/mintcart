<?php
/**
 * Standalone smoke test for
 * MintCart\Compatibility\OpenCart3\ShippingQuoteAdapter.
 *
 * All three collaborators (OpenCart's `Config`/`Loader`/`Registry`
 * objects) are duck-typed fakes — no OpenCart bootstrap needed, same
 * style as tests/mintcart/compatibility/totals-adapter-test.php.
 *
 * Run: php tests/mintcart/compatibility/opencart3/shipping-quote-adapter-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

require __DIR__ . '/../../../../upload/system/library/mintcart/compatibility/opencart3/shippingquoteadapter.php';

use MintCart\Compatibility\OpenCart3\ShippingQuoteAdapter;

final class FakeShippingConfig
{
    private array $values;

    public function __construct(array $values)
    {
        $this->values = $values;
    }

    public function get(string $key)
    {
        return $this->values[$key] ?? null;
    }
}

final class FakeShippingRegistry
{
    private array $data = [];

    public function set(string $key, $value): void
    {
        $this->data[$key] = $value;
    }

    public function get(string $key)
    {
        return $this->data[$key] ?? null;
    }

    public function has(string $key): bool
    {
        return isset($this->data[$key]);
    }
}

final class FakeShippingLoader
{
    private FakeShippingRegistry $registry;
    /** @var array<string, object> */
    private array $modelsByRoute;

    /** @param array<string, object> $modelsByRoute route => model instance */
    public function __construct(FakeShippingRegistry $registry, array $modelsByRoute)
    {
        $this->registry = $registry;
        $this->modelsByRoute = $modelsByRoute;
    }

    public function model(string $route): void
    {
        if (!$this->registry->has('model_' . str_replace('/', '_', $route)) && isset($this->modelsByRoute[$route])) {
            $this->registry->set('model_' . str_replace('/', '_', $route), $this->modelsByRoute[$route]);
        }
    }
}

final class FakeModelSettingExtensionForShipping
{
    /** @param list<array{code: string}> $shippingExtensions */
    public function __construct(private array $shippingExtensions)
    {
    }

    public function getExtensions(string $type): array
    {
        return $type === 'shipping' ? $this->shippingExtensions : [];
    }
}

/** Mirrors upload/catalog/model/extension/shipping/flat.php's exact return shape. */
final class FakeShippingExtensionModel
{
    public function __construct(private array $methodData)
    {
    }

    public function getQuote($address): array
    {
        return $this->methodData;
    }
}

/** Mirrors what happens when a geo zone check fails - getQuote() returns [] (falsy). */
final class FakeNoQuoteShippingExtensionModel
{
    public function getQuote($address): array
    {
        return [];
    }
}

$failures = [];

function assertSameShippingQuote($expected, $actual, string $message, array &$failures): void
{
    if ($expected !== $actual) {
        $failures[] = $message . ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')';
    }
}

// 1. No shipping extensions installed at all — empty result.
$registry = new FakeShippingRegistry();
$registry->set('model_setting_extension', new FakeModelSettingExtensionForShipping([]));
$loader = new FakeShippingLoader($registry, []);
$adapter = new ShippingQuoteAdapter(new FakeShippingConfig([]), $loader, $registry);
$result = $adapter->calculate(['country_id' => 222, 'zone_id' => 3573]);
assertSameShippingQuote([], $result, 'Scenario 1: no installed shipping extensions is an empty result', $failures);

// 2. Installed but its own status flag is off — skipped entirely.
$registry = new FakeShippingRegistry();
$registry->set('model_setting_extension', new FakeModelSettingExtensionForShipping([['code' => 'flat']]));
$registry->set('model_extension_shipping_flat', new FakeShippingExtensionModel([
    'code' => 'flat',
    'title' => 'Flat Shipping Rate',
    'quote' => ['flat' => ['code' => 'flat.flat', 'title' => 'Flat Shipping Rate', 'cost' => 5.0, 'tax_class_id' => 0, 'text' => '$5.00']],
    'sort_order' => 1,
    'error' => false,
]));
$loader = new FakeShippingLoader($registry, []);
$adapter = new ShippingQuoteAdapter(new FakeShippingConfig(['shipping_flat_status' => 0]), $loader, $registry);
$result = $adapter->calculate(['country_id' => 222, 'zone_id' => 3573]);
assertSameShippingQuote([], $result, 'Scenario 2: installed but disabled shipping extension contributes nothing', $failures);

// 3. Installed and enabled — keyed by extension code, quote preserved.
$registry = new FakeShippingRegistry();
$registry->set('model_setting_extension', new FakeModelSettingExtensionForShipping([['code' => 'flat']]));
$registry->set('model_extension_shipping_flat', new FakeShippingExtensionModel([
    'title' => 'Flat Shipping Rate',
    'quote' => ['flat' => ['code' => 'flat.flat', 'title' => 'Flat Shipping Rate', 'cost' => 5.0, 'tax_class_id' => 0, 'text' => '$5.00']],
    'sort_order' => 1,
    'error' => false,
]));
$loader = new FakeShippingLoader($registry, []);
$adapter = new ShippingQuoteAdapter(new FakeShippingConfig(['shipping_flat_status' => 1]), $loader, $registry);
$result = $adapter->calculate(['country_id' => 222, 'zone_id' => 3573]);
assertSameShippingQuote(['flat'], array_keys($result), 'Scenario 3: one enabled extension is keyed by its own code', $failures);
assertSameShippingQuote('Flat Shipping Rate', $result['flat']['title'], 'Scenario 3: title passes through', $failures);
assertSameShippingQuote('$5.00', $result['flat']['quote']['flat']['text'], 'Scenario 3: quote option passes through unchanged', $failures);

// 4. An enabled extension whose getQuote() returns an empty array (e.g. no
//    matching geo zone) is excluded, same as a disabled one.
$registry = new FakeShippingRegistry();
$registry->set('model_setting_extension', new FakeModelSettingExtensionForShipping([['code' => 'flat']]));
$registry->set('model_extension_shipping_flat', new FakeNoQuoteShippingExtensionModel());
$loader = new FakeShippingLoader($registry, []);
$adapter = new ShippingQuoteAdapter(new FakeShippingConfig(['shipping_flat_status' => 1]), $loader, $registry);
$result = $adapter->calculate(['country_id' => 1, 'zone_id' => 1]);
assertSameShippingQuote([], $result, 'Scenario 4: an enabled extension with no applicable quote is excluded', $failures);

// 5. Multiple enabled extensions come back sorted by sort_order, not by
//    getExtensions()'s own (here, deliberately reversed) return order —
//    and string keys survive the sort (array_multisort preserves them).
$registry = new FakeShippingRegistry();
$registry->set('model_setting_extension', new FakeModelSettingExtensionForShipping([
    ['code' => 'weight'],
    ['code' => 'flat'],
]));
$registry->set('model_extension_shipping_flat', new FakeShippingExtensionModel([
    'title' => 'Flat Shipping Rate',
    'quote' => ['flat' => ['code' => 'flat.flat', 'title' => 'Flat Shipping Rate', 'cost' => 5.0, 'tax_class_id' => 0, 'text' => '$5.00']],
    'sort_order' => 1,
    'error' => false,
]));
$registry->set('model_extension_shipping_weight', new FakeShippingExtensionModel([
    'title' => 'Weight Based Shipping',
    'quote' => ['weight' => ['code' => 'weight.weight', 'title' => 'Weight Based Shipping', 'cost' => 8.0, 'tax_class_id' => 0, 'text' => '$8.00']],
    'sort_order' => 9,
    'error' => false,
]));
$loader = new FakeShippingLoader($registry, []);
$adapter = new ShippingQuoteAdapter(new FakeShippingConfig([
    'shipping_flat_status' => 1,
    'shipping_weight_status' => 1,
]), $loader, $registry);
$result = $adapter->calculate(['country_id' => 222, 'zone_id' => 3573]);
assertSameShippingQuote(['flat', 'weight'], array_keys($result), 'Scenario 5: result is sorted by sort_order ascending, keyed by extension code', $failures);

if ($failures) {
    fwrite(STDERR, count($failures) . " failure(s):\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, "- {$failure}\n");
    }
    exit(1);
}

echo "PASS: ShippingQuoteAdapter smoke test (5 scenarios)\n";
exit(0);
