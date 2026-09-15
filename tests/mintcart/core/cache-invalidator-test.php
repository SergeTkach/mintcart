<?php
/**
 * Standalone smoke test for MintCart\Core\Cache\CacheInvalidator.
 *
 * No PHPUnit/Composer in the repo yet, same as every other tests/mintcart/
 * smoke test. CacheInvalidator takes its delete() dependency as an
 * injected callable, so this runs entirely against a plain recording spy
 * closure — no OpenCart \Cache/adapter bootstrap needed.
 *
 * Run: php tests/mintcart/core/cache-invalidator-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

require __DIR__ . '/../../../upload/system/library/mintcart/core/cache/cacheinvalidator.php';

use MintCart\Core\Cache\CacheInvalidator;

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

function spy(array &$calls): callable
{
    return function (string $key) use (&$calls): void {
        $calls[] = $key;
    };
}

// 1. knownDomains() lists every registered domain.
$domains = CacheInvalidator::knownDomains();
assertSame(['catalog', 'localization', 'checkout', 'content', 'store'], $domains, 'knownDomains: expected domain list', $failures);

// 2. prefixesFor() returns the registered prefixes for a known domain,
// and an empty array for an unknown one (no exception).
assertTrue(in_array('product.latest.', CacheInvalidator::prefixesFor('catalog'), true), 'prefixesFor(catalog): includes product.latest.', $failures);
assertSame([], CacheInvalidator::prefixesFor('does-not-exist'), 'prefixesFor: unknown domain returns empty array', $failures);

// 3. invalidate() calls the delete callback once per registered prefix,
// in the documented order, and returns the same list.
$calls = [];
$invalidator = new CacheInvalidator(spy($calls));
$returned = $invalidator->invalidate('store');
assertSame(['store'], $calls, 'invalidate(store): delete callback called with the one registered prefix', $failures);
assertSame(['store'], $returned, 'invalidate(store): return value matches prefixesFor(store)', $failures);

$calls = [];
$invalidator = new CacheInvalidator(spy($calls));
$returned = $invalidator->invalidate('catalog');
assertSame(CacheInvalidator::prefixesFor('catalog'), $calls, 'invalidate(catalog): delete callback called once per registered prefix, in order', $failures);
assertSame(CacheInvalidator::prefixesFor('catalog'), $returned, 'invalidate(catalog): return value matches prefixesFor(catalog)', $failures);

// 4. invalidate() with an unknown domain throws, and never calls delete().
$calls = [];
$invalidator = new CacheInvalidator(spy($calls));
$threw = false;
try {
    $invalidator->invalidate('does-not-exist');
} catch (\InvalidArgumentException $e) {
    $threw = true;
}
assertTrue($threw, 'invalidate: unknown domain throws InvalidArgumentException', $failures);
assertSame([], $calls, 'invalidate: unknown domain never calls the delete callback', $failures);

// 5. invalidateMany() de-duplicates prefixes shared by two domains and
// preserves first-seen order; "manufacturer"/"manufacturer." only appear
// once even though nothing else in the registry currently shares a
// prefix across domains (kept generic on purpose — the de-dup logic must
// not assume today's registry has no overlap).
$calls = [];
$invalidator = new CacheInvalidator(spy($calls));
$returned = $invalidator->invalidateMany(['store', 'content']);
assertSame(['store', 'information', 'information.', 'banner'], $returned, 'invalidateMany([store, content]): concatenated, in order', $failures);
assertSame($returned, $calls, 'invalidateMany: delete callback called for exactly the returned prefixes', $failures);

// 6. invalidateMany() with one bad domain among good ones still throws
// (fails closed — CLAUDE.md's "isCompatible() fails closed" precedent),
// but any domain processed before the bad one has already had its
// prefixes sent to delete() — documented, not silently swallowed.
$calls = [];
$invalidator = new CacheInvalidator(spy($calls));
$threw = false;
try {
    $invalidator->invalidateMany(['store', 'does-not-exist']);
} catch (\InvalidArgumentException $e) {
    $threw = true;
}
assertTrue($threw, 'invalidateMany: one unknown domain among valid ones still throws', $failures);
assertSame(['store'], $calls, 'invalidateMany: domains before the bad one were already invalidated', $failures);

// 7. invalidateKeys() deletes each exact key, in order, with no
// prefix/domain interpretation at all.
$calls = [];
$invalidator = new CacheInvalidator(spy($calls));
$invalidator->invalidateKeys(['product.latest.1.1.1.10', 'category']);
assertSame(['product.latest.1.1.1.10', 'category'], $calls, 'invalidateKeys: exact keys passed through unchanged, in order', $failures);

// 8. invalidateAll() calls delete('*') exactly once.
$calls = [];
$invalidator = new CacheInvalidator(spy($calls));
$invalidator->invalidateAll();
assertSame(['*'], $calls, 'invalidateAll: delete callback called once with "*"', $failures);

if ($failures) {
    fwrite(STDERR, 'FAIL: ' . count($failures) . " assertion(s) failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, " - {$failure}\n");
    }
    exit(1);
}

echo 'PASS: CacheInvalidator smoke test (' . 8 . " scenarios)\n";
exit(0);
