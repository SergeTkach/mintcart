<?php
/**
 * Standalone smoke test for MintCart\Core\Stage.
 *
 * No PHPUnit/Composer in the repo yet, same as diagnostics-test.php.
 * Stage is duck-typed against OpenCart's \Event class (only needs a
 * trigger(string $event, array $args = []) method), so this test uses a
 * tiny fake recorder instead of bootstrapping OpenCart's registry/engine.
 *
 * Run: php tests/mintcart/core/stage-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

require __DIR__ . '/../../../upload/system/library/mintcart/core/stage.php';

use MintCart\Core\Stage;

$failures = [];

function assertSame($expected, $actual, string $message, array &$failures): void
{
    if ($expected !== $actual) {
        $failures[] = $message . ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')';
    }
}

function assertTrue(bool $condition, string $message, array &$failures): void
{
    if (!$condition) {
        $failures[] = $message;
    }
}

final class FakeEventRecorder
{
    /** @var array<int, array{event: string, args: array}> */
    public array $calls = [];

    /** @var array<string, callable> event name => listener */
    public array $listeners = [];

    public function trigger(string $event, array $args = [])
    {
        $this->calls[] = ['event' => $event, 'args' => $args];

        if (isset($this->listeners[$event])) {
            return ($this->listeners[$event])(...$args);
        }

        return null;
    }
}

// 1. Fires "{prefix}.{name}.before" then runs $work() then fires
//    "{prefix}.{name}.after", in that order.
$recorder = new FakeEventRecorder();
$stage = new Stage($recorder, 'catalog.manufacturer');
$data = [];
$ran = false;

$stage->run('breadcrumbs', $data, function () use (&$ran) {
    $ran = true;
});

assertTrue($ran, 'run: executes $work()', $failures);
assertSame(2, count($recorder->calls), 'run: fires exactly two events', $failures);
assertSame('catalog.manufacturer.breadcrumbs.before', $recorder->calls[0]['event'], 'run: fires the dot-separated "before" event first', $failures);
assertSame('catalog.manufacturer.breadcrumbs.after', $recorder->calls[1]['event'], 'run: fires the dot-separated "after" event second', $failures);

// 2. $work()'s return value passes through unchanged.
$recorder = new FakeEventRecorder();
$stage = new Stage($recorder, 'catalog.manufacturer');
$data = [];

$result = $stage->run('listing', $data, function () {
    return 'built';
});

assertSame('built', $result, 'run: returns $work()\'s return value unchanged', $failures);

// 3. $data is passed to both events by reference - a listener on "before"
//    can add to $data and the addition is visible to $work() and to the
//    "after" listener.
$recorder = new FakeEventRecorder();
$recorder->listeners['catalog.manufacturer.breadcrumbs.before'] = function (array &$data) {
    $data['injected'] = 'from-before-listener';
};
$stage = new Stage($recorder, 'catalog.manufacturer');
$data = ['original' => true];
$seenInsideWork = null;

$stage->run('breadcrumbs', $data, function () use (&$data, &$seenInsideWork) {
    $seenInsideWork = $data['injected'] ?? null;
});

assertSame('from-before-listener', $seenInsideWork, 'run: a "before" listener mutating $data by reference is visible inside $work()', $failures);
assertSame('from-before-listener', $data['injected'] ?? null, 'run: a "before" listener\'s mutation to $data is visible to the caller after run() returns', $failures);
assertTrue($data['original'], 'run: pre-existing $data keys survive untouched', $failures);

// 4. An "after" listener sees mutations $work() itself made to $data.
$recorder = new FakeEventRecorder();
$seenByAfterListener = null;
$recorder->listeners['catalog.manufacturer.listing.after'] = function (array &$data) use (&$seenByAfterListener) {
    $seenByAfterListener = $data['built_by_work'] ?? null;
};
$stage = new Stage($recorder, 'catalog.manufacturer');
$data = [];

$stage->run('listing', $data, function () use (&$data) {
    $data['built_by_work'] = 'yes';
});

assertSame('yes', $seenByAfterListener, 'run: an "after" listener sees mutations $work() made to $data', $failures);

// 5. A different $prefix produces differently-namespaced event names -
//    two controllers using Stage never collide.
$recorder = new FakeEventRecorder();
$stage = new Stage($recorder, 'catalog.category');
$data = [];

$stage->run('breadcrumbs', $data, function () {});

assertSame('catalog.category.breadcrumbs.before', $recorder->calls[0]['event'], 'run: $prefix namespaces the event name', $failures);

// 6. If $work() throws, the exception propagates and ".after" never fires.
$recorder = new FakeEventRecorder();
$stage = new Stage($recorder, 'catalog.manufacturer');
$data = [];
$threw = false;

try {
    $stage->run('breadcrumbs', $data, function () {
        throw new \RuntimeException('boom');
    });
} catch (\RuntimeException $e) {
    $threw = true;
    assertSame('boom', $e->getMessage(), 'run: the original exception propagates unchanged', $failures);
}

assertTrue($threw, 'run: a $work() exception propagates to the caller', $failures);
assertSame(1, count($recorder->calls), 'run: only the "before" event fired before $work() threw - "after" is skipped', $failures);
assertSame('catalog.manufacturer.breadcrumbs.before', $recorder->calls[0]['event'], 'run: the one event that did fire was "before"', $failures);

if ($failures) {
    foreach ($failures as $failure) {
        fwrite(STDERR, "FAIL: {$failure}\n");
    }
    exit(1);
}

echo "PASS: Stage smoke test (6 scenarios)\n";
