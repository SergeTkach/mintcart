<?php
/**
 * Standalone smoke test for MintCart\Design\SlotRegistry
 * (MINTCART_TECHNICAL_PLAN.md §15, MINTCART_IMPLEMENTATION_GUIDE.md §35
 * "Extension slots").
 *
 * No PHPUnit/Composer test runner in the repo yet, same as every other
 * MintCart smoke test. SlotRegistry has no OpenCart dependency at all, so
 * every scenario below runs with no fixtures/bootstrapping.
 *
 * Run: php tests/mintcart/design/slot-registry-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

require __DIR__ . '/../../../upload/system/library/mintcart/design/slotregistry.php';

use MintCart\Design\SlotRegistry;

/** @var string[] $failures */
$failures = [];

function assertSame____($expected, $actual, string $message, array &$failures): void
{
    if ($expected !== $actual) {
        $failures[] = $message . ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')';
    }
}

// 1. An unregistered slot has() false and render()s to an empty string -
// this is the state of every slot in the codebase today (no listener is
// registered anywhere yet), so this must be a safe, silent no-op.
$registry = new SlotRegistry();

assertSame____(false, $registry->has('product.after_actions'), 'Scenario 1: has() is false for a slot with no listener', $failures);
assertSame____('', $registry->render('product.after_actions'), 'Scenario 1: render() returns an empty string for a slot with no listener', $failures);

// 2. listen() + render() returns the listener's own output, and has()
// flips to true for that slot only.
$registry = new SlotRegistry();
$registry->listen('product.after_actions', function (array $context): string {
    return '<span>' . $context['id'] . '</span>';
});

assertSame____(true, $registry->has('product.after_actions'), 'Scenario 2: has() is true once a listener is registered', $failures);
assertSame____(false, $registry->has('header.after'), 'Scenario 2: has() stays false for an unrelated slot name', $failures);
assertSame____('<span>42</span>', $registry->render('product.after_actions', ['id' => 42]), 'Scenario 2: render() returns the listener\'s output, built from the given context', $failures);

// 3. Multiple listeners on the same slot all run, concatenated in
// registration order - a second extension never silently overrides the
// first one's fragment.
$registry = new SlotRegistry();
$registry->listen('product.after_actions', function (): string {
    return 'first';
});
$registry->listen('product.after_actions', function (): string {
    return 'second';
});

assertSame____('firstsecond', $registry->render('product.after_actions'), 'Scenario 3: multiple listeners concatenate in registration order', $failures);

// 4. A listener returning a non-string-but-stringable value is cast to
// string - render()'s own contract is "always returns a string", a
// listener should not have to remember to cast itself.
$registry = new SlotRegistry();
$registry->listen('product.after_actions', function (): int {
    return 7;
});

assertSame____('7', $registry->render('product.after_actions'), 'Scenario 4: a non-string listener return value is cast to string', $failures);

// 5. render() with no $context argument still works (an empty array
// default) - a slot caller that has nothing meaningful to pass isn't
// forced to pass an empty array explicitly.
$registry = new SlotRegistry();
$seenContext = 'not called';
$registry->listen('header.after', function (array $context) use (&$seenContext): string {
    $seenContext = $context;

    return '';
});
$registry->render('header.after');

assertSame____([], $seenContext, 'Scenario 5: render() with no $context passes an empty array to the listener', $failures);

if ($failures) {
    fwrite(STDERR, 'FAIL: ' . count($failures) . " assertion(s) failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, " - {$failure}\n");
    }
    exit(1);
}

echo 'PASS: SlotRegistry smoke test (' . 5 . " scenarios)\n";
exit(0);
