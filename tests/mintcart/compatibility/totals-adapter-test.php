<?php
/**
 * Standalone smoke test for
 * MintCart\Compatibility\OpenCart3\TotalsAdapter.
 *
 * All three collaborators (OpenCart's `Config`/`Loader`/`Registry`
 * objects) are duck-typed fakes — no OpenCart bootstrap needed, same
 * style as tests/mintcart/compatibility/extension-adapter-test.php.
 *
 * The fake total-extension models replicate the exact by-reference-inside-
 * a-plain-array trick the real `model/extension/total/*.php` files rely on
 * (`$total['totals'][] = ...; $total['total'] += ...;` on a parameter that
 * was never declared `&$total` — see TotalsAdapter's own class doc-comment
 * for why that still mutates the caller's variables), so this test
 * exercises the same PHP behavior the adapter depends on, not a
 * simplified stand-in for it.
 *
 * Run: php tests/mintcart/compatibility/totals-adapter-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

require __DIR__ . '/../../../upload/system/library/mintcart/compatibility/opencart3/totalsadapter.php';

use MintCart\Compatibility\OpenCart3\TotalsAdapter;

final class FakeTotalsConfig
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

final class FakeTotalsRegistry
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

final class FakeTotalsLoader
{
    private FakeTotalsRegistry $registry;
    /** @var array<string, object> */
    private array $modelsByRoute;

    /** @param array<string, object> $modelsByRoute route => model instance (or model-list proxy) */
    public function __construct(FakeTotalsRegistry $registry, array $modelsByRoute)
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

final class FakeModelSettingExtensionForTotals
{
    /** @param list<array{code: string}> $totalExtensions */
    public function __construct(private array $totalExtensions)
    {
    }

    public function getExtensions(string $type): array
    {
        return $type === 'total' ? $this->totalExtensions : [];
    }
}

/**
 * Mirrors upload/catalog/model/extension/total/sub_total.php's exact shape:
 * mutates $total['totals']/$total['total'] on a parameter with no `&`,
 * relying on the same array-of-references trick TotalsAdapter's own
 * doc-comment describes.
 */
final class FakeTotalExtensionModel
{
    public function __construct(private string $code, private string $title, private float $value, private int $sortOrder)
    {
    }

    public function getTotal($total): void
    {
        $total['totals'][] = [
            'code' => $this->code,
            'title' => $this->title,
            'value' => $this->value,
            'sort_order' => $this->sortOrder,
        ];

        $total['total'] += $this->value;
    }
}

$failures = [];

function assertSameTotalsAdapter($expected, $actual, string $message, array &$failures): void
{
    if ($expected !== $actual) {
        $failures[] = $message . ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')';
    }
}

// 1. No total extensions installed at all — empty totals, zero total.
$registry = new FakeTotalsRegistry();
$registry->set('model_setting_extension', new FakeModelSettingExtensionForTotals([]));
$loader = new FakeTotalsLoader($registry, []);
$adapter = new TotalsAdapter(new FakeTotalsConfig([]), $loader, $registry);
$result = $adapter->calculate();
assertSameTotalsAdapter([], $result['totals'], 'Scenario 1: no installed total extensions is an empty totals list', $failures);
assertSameTotalsAdapter(0, $result['total'], 'Scenario 1: no installed total extensions leaves total at 0', $failures);

// 2. Installed but its own status flag is off — skipped entirely (same
//    installed-vs-enabled distinction ExtensionAdapter normalizes
//    elsewhere, applied here to the total-extension pipeline).
$registry = new FakeTotalsRegistry();
$registry->set('model_setting_extension', new FakeModelSettingExtensionForTotals([['code' => 'sub_total']]));
$registry->set('model_extension_total_sub_total', new FakeTotalExtensionModel('sub_total', 'Sub-Total', 100.0, 1));
$loader = new FakeTotalsLoader($registry, []);
$adapter = new TotalsAdapter(new FakeTotalsConfig(['total_sub_total_status' => 0]), $loader, $registry);
$result = $adapter->calculate();
assertSameTotalsAdapter([], $result['totals'], 'Scenario 2: installed but disabled total extension contributes nothing', $failures);
assertSameTotalsAdapter(0, $result['total'], 'Scenario 2: disabled total extension leaves total at 0', $failures);

// 3. Installed and enabled — contributes its row and its value to the total.
$registry = new FakeTotalsRegistry();
$registry->set('model_setting_extension', new FakeModelSettingExtensionForTotals([['code' => 'sub_total']]));
$registry->set('model_extension_total_sub_total', new FakeTotalExtensionModel('sub_total', 'Sub-Total', 100.0, 1));
$loader = new FakeTotalsLoader($registry, []);
$adapter = new TotalsAdapter(new FakeTotalsConfig(['total_sub_total_status' => 1, 'total_sub_total_sort_order' => 1]), $loader, $registry);
$result = $adapter->calculate();
assertSameTotalsAdapter(1, count($result['totals']), 'Scenario 3: one enabled total extension produces one row', $failures);
assertSameTotalsAdapter('sub_total', $result['totals'][0]['code'], 'Scenario 3: the row is the enabled extension', $failures);
assertSameTotalsAdapter(100.0, $result['total'], 'Scenario 3: total accumulates the enabled extension value', $failures);

// 4. Multiple enabled extensions come back sorted by sort_order, not by
//    getExtensions()'s own (here, deliberately reversed) return order.
$registry = new FakeTotalsRegistry();
$registry->set('model_setting_extension', new FakeModelSettingExtensionForTotals([
    ['code' => 'total'],
    ['code' => 'sub_total'],
]));
$registry->set('model_extension_total_sub_total', new FakeTotalExtensionModel('sub_total', 'Sub-Total', 50.0, 1));
$registry->set('model_extension_total_total', new FakeTotalExtensionModel('total', 'Total', 50.0, 9));
$loader = new FakeTotalsLoader($registry, []);
$adapter = new TotalsAdapter(new FakeTotalsConfig([
    'total_total_status' => 1,
    'total_total_sort_order' => 9,
    'total_sub_total_status' => 1,
    'total_sub_total_sort_order' => 1,
]), $loader, $registry);
$result = $adapter->calculate();
assertSameTotalsAdapter(['sub_total', 'total'], array_column($result['totals'], 'code'), 'Scenario 4: totals list is sorted by sort_order ascending', $failures);

// 5. A seed taxes array is passed through and can be added to by a total
//    extension (mirrors extension/total/tax.php reading $total['taxes']).
final class FakeTaxAddingTotalExtensionModel
{
    public function getTotal($total): void
    {
        foreach ($total['taxes'] as $rateId => $amount) {
            $total['totals'][] = ['code' => 'tax', 'title' => 'Tax', 'value' => $amount, 'sort_order' => 5];
            $total['total'] += $amount;
        }
    }
}

$registry = new FakeTotalsRegistry();
$registry->set('model_setting_extension', new FakeModelSettingExtensionForTotals([['code' => 'tax']]));
$registry->set('model_extension_total_tax', new FakeTaxAddingTotalExtensionModel());
$loader = new FakeTotalsLoader($registry, []);
$adapter = new TotalsAdapter(new FakeTotalsConfig(['total_tax_status' => 1, 'total_tax_sort_order' => 5]), $loader, $registry);
$result = $adapter->calculate([1 => 3.5]);
assertSameTotalsAdapter(3.5, $result['total'], 'Scenario 5: seeded taxes are visible to and summed by a total extension', $failures);
assertSameTotalsAdapter([1 => 3.5], $result['taxes'], 'Scenario 5: the taxes array itself is returned back to the caller', $failures);

if ($failures) {
    fwrite(STDERR, count($failures) . " failure(s):\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, "- {$failure}\n");
    }
    exit(1);
}

echo "PASS: TotalsAdapter smoke test (5 scenarios)\n";
exit(0);
