<?php
/**
 * Standalone smoke test for ModelDesignMedia
 * (upload/admin/model/design/media.php), the backend for `mc-media-picker`.
 *
 * Unlike most `tests/mintcart/` smoke tests, this one exercises a real
 * OpenCart *Model* subclass, so it needs the base `Model` engine class
 * plus a minimal `Registry`/`Loader`/`Request` stand-in — not a full
 * OpenCart bootstrap (no MySQL, no other engine classes), just enough for
 * `ModelDesignMedia`'s own `$this->load->model(...)` /
 * `$this->request->server[...]` calls to resolve. Every filesystem
 * operation runs against a real temporary directory tree created by this
 * test (removed at the end), so directory listing, folder discovery and
 * deletion are exercised against real files/directories, not mocks.
 *
 * `addFile()`'s actual `move_uploaded_file()` call cannot be exercised
 * here — that function only succeeds against a file PHP's own SAPI
 * registered as uploaded during a real HTTP request, which does not exist
 * under the CLI SAPI this test runs under. Its *validation* path
 * (directory/filename/extension checks, all of which run before the move)
 * is still covered. Same "no live install in this environment" gap noted
 * throughout `docs/IMPLEMENTATION_STATUS.md` — a human should confirm a
 * real upload on a live install.
 *
 * Run: php tests/mintcart/design/media-model-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

require __DIR__ . '/../../../upload/system/engine/model.php';
require __DIR__ . '/../../../upload/system/helper/utf8.php';
require __DIR__ . '/../../../upload/system/helper/general.php';
require __DIR__ . '/../../../upload/system/library/mintcart/design/mediapath.php';
require __DIR__ . '/../../../upload/admin/model/design/media.php';

// --- Minimal stand-ins for the OpenCart engine pieces ModelDesignMedia touches ---

class StubRegistry
{
    private $data = [];

    public function get($key)
    {
        return $this->data[$key] ?? null;
    }

    public function set($key, $value)
    {
        $this->data[$key] = $value;
    }
}

class StubModelToolImage
{
    public function resize($filename, $width, $height)
    {
        return 'thumb://' . $filename . '?' . $width . 'x' . $height;
    }
}

class StubLoader
{
    private $registry;

    public function __construct($registry)
    {
        $this->registry = $registry;
    }

    public function model($route)
    {
        if ($route === 'tool/image') {
            $this->registry->set('model_tool_image', new StubModelToolImage());
        }
    }
}

class StubRequest
{
    public $server = ['HTTPS' => false];
}

// --- Fixture: a real temporary directory tree mirroring image/catalog/ ---

$fixtureRoot = sys_get_temp_dir() . '/mintcart-media-model-test-' . uniqid();

mkdir($fixtureRoot . '/catalog/shirts', 0777, true);
file_put_contents($fixtureRoot . '/catalog/logo.png', 'fake-png-bytes');
file_put_contents($fixtureRoot . '/catalog/shirts/red.jpg', 'fake-jpg-bytes');
file_put_contents($fixtureRoot . '/catalog/ignored.txt', 'not an image, must not be listed');

define('DIR_IMAGE', $fixtureRoot . '/');
define('HTTP_CATALOG', 'http://example.test/');
define('HTTPS_CATALOG', 'https://example.test/');

function rrmdir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }

    foreach (scandir($dir) as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }

        $path = $dir . '/' . $entry;

        is_dir($path) ? rrmdir($path) : unlink($path);
    }

    rmdir($dir);
}

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

$registry = new StubRegistry();
$registry->set('load', new StubLoader($registry));
$registry->set('request', new StubRequest());

$model = new ModelDesignMedia($registry);

// 1. getFolders(): only the top-level "shirts" directory, never the
// non-image "ignored.txt" file or logo.png.
$folders = $model->getFolders();
assertSame(1, count($folders), '1. getFolders: exactly one top-level folder', $failures);
assertSame('catalog/shirts', $folders[0]['id'], '1. getFolders: id is the path relative to DIR_IMAGE', $failures);
assertSame('shirts', $folders[0]['label'], '1. getFolders: label is the basename', $failures);

// 2. getFiles() at the catalog root: the "shirts" directory and
// "logo.png" are both listed (folders first, then files); the non-image
// "ignored.txt" is never listed.
$result = $model->getFiles(['directory' => '', 'filter_name' => '', 'page' => 1, 'limit' => 20]);
assertSame(2, $result['total'], '2. getFiles (root): total is 2 (one folder, one image) - ignored.txt excluded', $failures);
assertSame('directory', $result['items'][0]['type'], '2. getFiles (root): first item is the directory (folders sort before files)', $failures);
assertSame('catalog/shirts', $result['items'][0]['id'], '2. getFiles (root): directory id is the relative path', $failures);
assertSame('image', $result['items'][1]['type'], '2. getFiles (root): second item is the image', $failures);
assertSame('catalog/logo.png', $result['items'][1]['id'], '2. getFiles (root): image id is the relative path', $failures);
assertSame('thumb://catalog/logo.png?100x100', $result['items'][1]['thumbnail'], '2. getFiles (root): thumbnail comes from the injected ModelToolImage stub', $failures);
assertSame('http://example.test/image/catalog/logo.png', $result['items'][1]['url'], '2. getFiles (root): url is built from HTTP_CATALOG (HTTPS off)', $failures);

// 3. getFiles() inside a subdirectory: only that directory's own image is
// listed.
$result = $model->getFiles(['directory' => 'shirts', 'filter_name' => '', 'page' => 1, 'limit' => 20]);
assertSame(1, $result['total'], '3. getFiles (subdirectory): total is 1', $failures);
assertSame('catalog/shirts/red.jpg', $result['items'][0]['id'], '3. getFiles (subdirectory): id includes the subdirectory', $failures);

// 4. getFiles() with a filter_name narrows the result.
$result = $model->getFiles(['directory' => '', 'filter_name' => 'logo', 'page' => 1, 'limit' => 20]);
assertSame(1, $result['total'], '4. getFiles (filter_name): matches only "logo.png"', $failures);
assertSame('catalog/logo.png', $result['items'][0]['id'], '4. getFiles (filter_name): matched id is logo.png', $failures);

// 5. getFiles() with a directory that resolves outside the catalog root
// (a traversal attempt) returns an empty, well-formed result rather than
// listing anything or throwing.
$result = $model->getFiles(['directory' => '../../../../etc', 'filter_name' => '', 'page' => 1, 'limit' => 20]);
assertSame(0, $result['total'], '5. getFiles (traversal attempt): total is 0', $failures);
assertSame([], $result['items'], '5. getFiles (traversal attempt): items is empty', $failures);

// 6. deleteFiles(): a real file is actually removed from disk.
assertTrue(is_file($fixtureRoot . '/catalog/shirts/red.jpg'), '6. deleteFiles: fixture file exists before delete', $failures);
$deleteResult = $model->deleteFiles(['catalog/shirts/red.jpg']);
assertSame(true, $deleteResult['success'] ?? null, '6. deleteFiles: reports success', $failures);
assertTrue(!is_file($fixtureRoot . '/catalog/shirts/red.jpg'), '6. deleteFiles: the file is actually gone from disk', $failures);

// 7. deleteFiles(): the catalog root itself can never be deleted.
$deleteResult = $model->deleteFiles(['catalog']);
assertSame('delete', $deleteResult['error'] ?? null, '7. deleteFiles: refuses to delete the catalog root itself', $failures);
assertTrue(is_dir($fixtureRoot . '/catalog'), '7. deleteFiles: the catalog root still exists on disk', $failures);

// 8. deleteFiles(): a path that resolves outside the catalog root is
// rejected, and nothing else in the same batch is deleted either (the
// whole call fails together, so a mistake never causes a partial delete).
assertTrue(is_file($fixtureRoot . '/catalog/logo.png'), '8. deleteFiles (mixed batch): fixture file exists before the rejected call', $failures);
$deleteResult = $model->deleteFiles(['catalog/logo.png', '../../../../etc/passwd']);
assertSame('delete', $deleteResult['error'] ?? null, '8. deleteFiles (mixed batch): the whole batch is rejected', $failures);
assertTrue(is_file($fixtureRoot . '/catalog/logo.png'), '8. deleteFiles (mixed batch): the valid file in the same batch was not deleted either', $failures);

// 9. addFile(): the directory/filename/extension validation rejects bad
// input before ever attempting the filesystem move.
assertSame('directory', $model->addFile('../../../../etc', 'x.jpg', '/tmp/does-not-exist')['error'] ?? null, '9. addFile: a directory outside the catalog root is rejected', $failures);
assertSame('filetype', $model->addFile('', 'x.php', '/tmp/does-not-exist')['error'] ?? null, '9. addFile: a disallowed extension is rejected before touching the filesystem', $failures);
assertSame('filename', $model->addFile('', 'ab', '/tmp/does-not-exist')['error'] ?? null, '9. addFile: a too-short filename is rejected', $failures);

if ($failures) {
    fwrite(STDERR, 'FAIL: ' . count($failures) . " assertion(s) failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, " - {$failure}\n");
    }
    rrmdir($fixtureRoot);
    exit(1);
}

rrmdir($fixtureRoot);

echo 'PASS: ModelDesignMedia smoke test (' . 9 . " scenarios)\n";
exit(0);
