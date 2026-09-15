<?php
/**
 * Standalone smoke test for MintCart\Core\Diagnostics.
 *
 * No PHPUnit/Composer in the repo yet, same as every other tests/mintcart/
 * suite. Diagnostics has no OpenCart dependency by design, so this runs
 * entirely against a fake, deterministic clock (a queue of pre-set times)
 * rather than real wall-clock time.
 *
 * Run: php tests/mintcart/core/diagnostics-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

require __DIR__ . '/../../../upload/system/library/mintcart/core/diagnostics.php';

use MintCart\Core\Diagnostics;

$failures = [];

function assertTrue(bool $condition, string $message, array &$failures): void
{
    if (!$condition) {
        $failures[] = $message;
    }
}

function assertSame($expected, $actual, string $message, array &$failures): void
{
    if ($expected !== $actual) {
        $failures[] = $message . ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')';
    }
}

/**
 * Builds a deterministic clock: each call pops the next value off the
 * queue (in seconds), so tests can assert exact elapsed_ms values instead
 * of tolerating real-wall-clock timing flakiness.
 *
 * @param list<float> $times
 * @return callable(): float
 */
function fakeClock(array $times): callable
{
    return static function () use (&$times): float {
        return array_shift($times) ?? 0.0;
    };
}

// 1. Enabled by default.
$diagnostics = new Diagnostics(true, fakeClock([100.0]));
assertTrue($diagnostics->isEnabled(), 'isEnabled: true by default', $failures);

// 2. elapsedMs() reflects the injected clock, not real wall-clock time.
$diagnostics = new Diagnostics(true, fakeClock([100.0, 100.25]));
assertSame(250.0, $diagnostics->elapsedMs(), 'elapsedMs: 250ms between construction and this call', $failures);

// 3. mark() records elapsed_ms relative to construction, in call order.
$diagnostics = new Diagnostics(true, fakeClock([100.0, 100.01, 100.03]));
$diagnostics->mark('breadcrumbs_built');
$diagnostics->mark('product_cards_rendered');
$summary = $diagnostics->summary();
assertSame(2, count($summary['marks']), 'mark: two marks recorded', $failures);
assertSame('breadcrumbs_built', $summary['marks'][0]['name'], 'mark: first mark name', $failures);
assertSame(10.0, $summary['marks'][0]['elapsed_ms'], 'mark: first mark elapsed_ms', $failures);
assertSame('product_cards_rendered', $summary['marks'][1]['name'], 'mark: second mark name', $failures);
assertSame(30.0, $summary['marks'][1]['elapsed_ms'], 'mark: second mark elapsed_ms', $failures);

// 4. increment() defaults to +1, and accepts a custom amount.
$diagnostics = new Diagnostics(true, fakeClock([100.0]));
$diagnostics->increment('queries');
$diagnostics->increment('queries');
$diagnostics->increment('cache_hit', 5);
assertSame(2, $diagnostics->counter('queries'), 'increment: default +1, called twice', $failures);
assertSame(5, $diagnostics->counter('cache_hit'), 'increment: custom amount', $failures);

// 5. counter() returns 0 for a counter that was never incremented.
$diagnostics = new Diagnostics(true, fakeClock([100.0]));
assertSame(0, $diagnostics->counter('never_touched'), 'counter: unknown counter defaults to 0', $failures);

// 6. time() accumulates duration under the named bucket across multiple calls.
$diagnostics = new Diagnostics(true, fakeClock([100.0, 100.0, 100.005, 100.010, 100.014]));
$diagnostics->time('db', static function (): string {
    return 'first';
});
$diagnostics->time('db', static function (): string {
    return 'second';
});
assertSame(9.0, $diagnostics->duration('db'), 'time: accumulates duration across two calls to the same bucket', $failures);

// 7. time() passes through the callback's return value unchanged.
$diagnostics = new Diagnostics(true, fakeClock([100.0, 100.001]));
$result = $diagnostics->time('db', static function (): int {
    return 42;
});
assertSame(42, $result, 'time: returns the wrapped callback result', $failures);

// 8. Disabled: mark()/increment() are no-ops, but time() still runs the
// work and returns its result (measurement is skipped, not the work).
$diagnostics = new Diagnostics(false);
$diagnostics->mark('should_not_record');
$diagnostics->increment('should_not_count');
$result = $diagnostics->time('db', static function (): string {
    return 'ran anyway';
});
assertSame('ran anyway', $result, 'disabled: time() still executes and returns the work', $failures);
$summary = $diagnostics->summary();
assertSame(false, $summary['enabled'], 'disabled: summary reports enabled=false', $failures);
assertSame([], $summary['marks'], 'disabled: no marks recorded', $failures);
assertSame([], $summary['counters'], 'disabled: no counters recorded', $failures);
assertSame([], $summary['durations'], 'disabled: no durations recorded', $failures);

// 9. summary() shape when enabled, with a mix of marks/counters/durations.
$diagnostics = new Diagnostics(true, fakeClock([100.0, 100.01, 100.01, 100.02, 100.02]));
$diagnostics->mark('start');
$diagnostics->increment('queries', 3);
$diagnostics->time('render', static function (): void {
});
$summary = $diagnostics->summary();
assertSame(true, $summary['enabled'], 'summary: enabled=true', $failures);
assertSame(3, $summary['counters']['queries'], 'summary: counters includes queries', $failures);
assertTrue(isset($summary['durations']['render']), 'summary: durations includes the render bucket', $failures);
assertTrue(array_key_exists('elapsed_ms', $summary), 'summary: has an elapsed_ms key', $failures);

// 10. Independent counters/buckets don't bleed into each other.
$diagnostics = new Diagnostics(true, fakeClock([100.0, 100.0, 100.002]));
$diagnostics->increment('queries', 2);
$diagnostics->increment('cache_hit', 1);
$diagnostics->time('db', static function (): void {
});
assertSame(2, $diagnostics->counter('queries'), 'independence: queries counter unaffected by cache_hit', $failures);
assertSame(1, $diagnostics->counter('cache_hit'), 'independence: cache_hit counter unaffected by queries', $failures);
assertSame(0, $diagnostics->counter('cache_miss'), 'independence: untouched counter stays 0', $failures);
assertSame(0.0, $diagnostics->duration('render'), 'independence: untouched duration bucket stays 0', $failures);

if ($failures) {
    fwrite(STDERR, count($failures) . " failure(s):\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, "- {$failure}\n");
    }
    exit(1);
}

echo "PASS: Diagnostics smoke test (10 scenarios)\n";
exit(0);
