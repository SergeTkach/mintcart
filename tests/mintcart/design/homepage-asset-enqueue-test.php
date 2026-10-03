<?php
/**
 * Standalone smoke test for
 * `ControllerDesignHomepage::enqueueMintAssets()`
 * (`upload/admin/controller/design/homepage.php`) — the Homepage Builder
 * admin editor's second real consumer of the admin asset-enqueue pattern
 * `docs/developer/admin/asset-enqueue.md` documents
 * (`ControllerCatalogProduct::getForm()` was the first). Confirms the
 * method queues exactly the two documented URLs
 * (`assets/dist/css/components.min.css`, not `index.min.css` — the
 * Bootstrap-5-free bundle safe to layer on the legacy admin theme's
 * Bootstrap 3 — and `assets/dist/js/admin.min.js`) via `HTTP_CATALOG`, the
 * same two-line shape as that first consumer.
 *
 * No PHPUnit/Composer and no MySQL server in this environment (same gap
 * every previous phase's test carries). Loads the real
 * `upload/system/engine/controller.php` (OpenCart's base `Controller`,
 * which just proxies property access to a `Registry`) rather than
 * re-implementing it, plus a minimal stub `Document` double that duck-types
 * just enough of the real `upload/system/library/document.php`'s shape
 * (`addStyle()`/`addScript()`) to record what gets queued — same approach
 * `tests/mintcart/design/homepage-admin-schema-test.php` takes for
 * `Request`/`Language`/`User`.
 *
 * Run: php tests/mintcart/design/homepage-asset-enqueue-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

define('HTTP_CATALOG', 'http://example.test/');

require __DIR__ . '/../../../upload/system/engine/controller.php';
require __DIR__ . '/../../../upload/admin/controller/design/homepage.php';

/** @var string[] $failures */
$failures = [];

function assertSame_____($expected, $actual, string $message, array &$failures): void
{
    if ($expected !== $actual) {
        $failures[] = $message . ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')';
    }
}

final class FakeRegistry
{
    /** @var array<string, mixed> */
    private array $data = [];

    public function get($key)
    {
        return $this->data[$key] ?? null;
    }

    public function set($key, $value): void
    {
        $this->data[$key] = $value;
    }
}

final class FakeDocument
{
    /** @var list<array{href: string, rel: string, media: string, position: string}> */
    public array $styles = [];

    /** @var list<array{href: string, position: string}> */
    public array $scripts = [];

    public function addStyle($href, $rel = 'stylesheet', $media = 'screen', $position = 'header')
    {
        $this->styles[] = ['href' => $href, 'rel' => $rel, 'media' => $media, 'position' => $position];
    }

    public function addScript($href, $position = 'header')
    {
        $this->scripts[] = ['href' => $href, 'position' => $position];
    }
}

function callProtected(object $object, string $method, array $args = [])
{
    $reflection = new ReflectionMethod($object, $method);
    $reflection->setAccessible(true);

    return $reflection->invokeArgs($object, $args);
}

$registry = new FakeRegistry();
$document = new FakeDocument();
$registry->set('document', $document);

$controller = new ControllerDesignHomepage($registry);

callProtected($controller, 'enqueueMintAssets');

// 1. Exactly one stylesheet is queued, and it's the Bootstrap-5-free
//    `components.min.css` bundle (never `index.min.css` — that one still
//    carries the full Bootstrap 5 reset/grid/utility layer, which
//    `docs/developer/admin/asset-enqueue.md` documents as unsafe to load
//    alongside the legacy admin theme's Bootstrap 3).
assertSame_____(1, count($document->styles), '1. enqueueMintAssets() should queue exactly one stylesheet', $failures);
assertSame_____(
    'http://example.test/assets/dist/css/components.min.css',
    $document->styles[0]['href'] ?? null,
    '1. The queued stylesheet should be components.min.css under HTTP_CATALOG, not index.min.css',
    $failures
);

// 2. Exactly one script is queued, and it's admin.min.js.
assertSame_____(1, count($document->scripts), '2. enqueueMintAssets() should queue exactly one script', $failures);
assertSame_____(
    'http://example.test/assets/dist/js/admin.min.js',
    $document->scripts[0]['href'] ?? null,
    '2. The queued script should be admin.min.js under HTTP_CATALOG',
    $failures
);

// 3. Calling it again (e.g. a second getList() in the same request, which
//    never actually happens today, but the method itself has no internal
//    guard) queues a second, duplicate pair rather than silently
//    deduplicating — documents the method's actual behavior rather than
//    assuming OpenCart's own Document::addStyle()/addScript() dedupe for
//    it (they don't; this test only certifies this method's own contract,
//    not Document's).
callProtected($controller, 'enqueueMintAssets');

assertSame_____(2, count($document->styles), '3. A second call queues a second stylesheet entry (no internal guard)', $failures);
assertSame_____(2, count($document->scripts), '3. A second call queues a second script entry (no internal guard)', $failures);

if ($failures) {
    fwrite(STDERR, count($failures) . " failure(s):\n");

    foreach ($failures as $failure) {
        fwrite(STDERR, '- ' . $failure . "\n");
    }

    exit(1);
}

echo "All homepage-asset-enqueue-test assertions passed.\n";
exit(0);
