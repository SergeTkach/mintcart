<?php
/**
 * Standalone smoke test for ControllerDesignHomepage's generic,
 * schema-driven settings/content field handling
 * (`upload/admin/controller/design/homepage.php`) — the piece that lets
 * all shipped Homepage Builder block types (Products through Gallery,
 * MINTCART_IMPLEMENTATION_GUIDE.md §51) be configured through this screen
 * instead of only Products.
 *
 * No PHPUnit/Composer and no MySQL server in this environment (same gap
 * every previous phase's test carries). Loads the real
 * `upload/system/engine/controller.php` (OpenCart's base `Controller`,
 * which just proxies property access to a `Registry`) rather than
 * re-implementing it, plus minimal stub `Request`/`Language`/`User`
 * doubles that duck-type just enough of the real classes' shape for
 * `prepareBlockData()`/`validateForm()` to run end to end - same approach
 * `tests/mintcart/design/homepage-model-test.php` takes for `$db`.
 *
 * Run: php tests/mintcart/design/homepage-admin-schema-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

require __DIR__ . '/../../../upload/system/engine/controller.php';
require __DIR__ . '/../../../upload/admin/controller/design/homepage.php';

/** @var string[] $failures */
$failures = [];

function assertSame____($expected, $actual, string $message, array &$failures): void
{
    if ($expected !== $actual) {
        $failures[] = $message . ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')';
    }
}

function assertTrue____($condition, string $message, array &$failures): void
{
    if (!$condition) {
        $failures[] = $message;
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

final class FakeRequest
{
    /** @var array<string, mixed> */
    public array $post = [];

    /** @var array<string, mixed> */
    public array $get = [];

    /** @var array<string, mixed> */
    public array $server = ['REQUEST_METHOD' => 'POST'];
}

final class FakeLanguage
{
    public function get($key)
    {
        return $key;
    }
}

final class FakeUser
{
    public function hasPermission($type, $route)
    {
        return true;
    }
}

function makeController(array $post): ControllerDesignHomepage
{
    $registry = new FakeRegistry();
    $request = new FakeRequest();
    $request->post = $post;

    $registry->set('request', $request);
    $registry->set('language', new FakeLanguage());
    $registry->set('user', new FakeUser());

    return new ControllerDesignHomepage($registry);
}

function callPrivate(object $object, string $method, array $args = [])
{
    $reflection = new ReflectionMethod($object, $method);
    $reflection->setAccessible(true);

    return $reflection->invokeArgs($object, $args);
}

// 1. A flat type (CTA - all content, no settings) round-trips through
//    prepareBlockData() with exactly its schema's fields, nothing more,
//    nothing dropped.
$controller = makeController([
    'type' => 'cta',
    'status' => '1',
    'settings' => ['category_id' => '999'], // stray field from a different type - must be ignored
    'block_content' => [
        1 => ['title' => 'Sale', 'text' => 'Ends soon', 'cta_label' => 'Shop now', 'cta_url' => 'index.php?route=product/special'],
    ],
]);

$data = callPrivate($controller, 'prepareBlockData');

assertSame____('cta', $data['type'], 'Scenario 1: type should round-trip unchanged', $failures);
assertSame____(true, $data['status'], 'Scenario 1: status "1" should normalize to true', $failures);
assertSame____([], $data['settings'], 'Scenario 1: CTA has no settings fields - stray posted settings must be dropped, not leaked into storage', $failures);
assertSame____(
    ['title' => 'Sale', 'text' => 'Ends soon', 'cta_label' => 'Shop now', 'cta_url' => 'index.php?route=product/special'],
    $data['content'][1],
    'Scenario 1: all four CTA content fields should be captured for language 1',
    $failures
);

// 2. A type with a `json` items field (Banner Grid) decodes valid JSON
//    text into a real PHP array, ready for json_encode() by the model -
//    not a double-encoded string.
$controller = makeController([
    'type' => 'banner-grid',
    'settings' => [
        'columns' => '3',
        'items' => '[{"image":"catalog/b1.jpg","url":"","image_width":600,"image_height":400}]',
    ],
    'block_content' => [
        1 => ['title' => 'Deals', 'items' => '[{"alt":"Sale","caption":"Up to 50% off"}]'],
    ],
]);

$data = callPrivate($controller, 'prepareBlockData');

assertSame____('3', $data['settings']['columns'], 'Scenario 2: select field value should round-trip', $failures);
assertTrue____(is_array($data['settings']['items']), 'Scenario 2: JSON settings field should decode to a real array', $failures);
assertSame____('catalog/b1.jpg', $data['settings']['items'][0]['image'] ?? null, 'Scenario 2: decoded settings items should keep their structure', $failures);
assertTrue____(is_array($data['content'][1]['items']), 'Scenario 2: JSON content field should decode to a real array', $failures);
assertSame____('Sale', $data['content'][1]['items'][0]['alt'] ?? null, 'Scenario 2: decoded content items should keep their structure', $failures);

// 3. Blank/omitted fields fall back to the schema's own default, never to
//    PHP null-coercion surprises (e.g. Products' category_id default is
//    literally `null` - "no category filter" - not 0).
$controller = makeController(['type' => 'products', 'settings' => [], 'block_content' => [1 => []]]);
$data = callPrivate($controller, 'prepareBlockData');

assertSame____(null, $data['settings']['category_id'], 'Scenario 3: blank category_id should fall back to its null default (no filter), not 0 or ""', $failures);
assertSame____(8, $data['settings']['limit'], 'Scenario 3: blank limit should fall back to its default of 8', $failures);
assertSame____('p.date_added', $data['settings']['sort'], 'Scenario 3: blank sort should fall back to its default', $failures);
assertSame____('', $data['content'][1]['title'], 'Scenario 3: blank title should fall back to the empty-string default', $failures);

// 4. An out-of-vocabulary `select` value (tampered POST, not something the
//    <select> itself would ever submit) falls back to the field's default
//    rather than being persisted as-is.
$controller = makeController(['type' => 'spacer', 'settings' => ['size' => 'huge'], 'block_content' => []]);
$data = callPrivate($controller, 'prepareBlockData');

assertSame____('md', $data['settings']['size'], 'Scenario 4: an unrecognized select value should fall back to the default, not pass through', $failures);

// 5. validateForm(): a genuinely malformed JSON items field is rejected...
$controller = makeController([
    'type' => 'gallery',
    'settings' => ['columns' => '4', 'items' => '{not valid json'],
    'block_content' => [1 => ['title' => 'Gallery']],
]);

assertSame____(false, callPrivate($controller, 'validateForm'), 'Scenario 5: malformed JSON in a settings items field should fail validation', $failures);

// ...but a blank items field (nothing entered yet) is valid - an admin
// should be able to save a block before filling in every optional list.
$controller = makeController([
    'type' => 'gallery',
    'settings' => ['columns' => '4', 'items' => ''],
    'block_content' => [1 => ['title' => 'Gallery', 'items' => '']],
]);

assertSame____(true, callPrivate($controller, 'validateForm'), 'Scenario 5b: a blank JSON items field should pass validation (normalizes to an empty list)', $failures);

// 6. Every shipped type resolves to a real field schema (no type
//    silently falls through to the empty default), and every field
//    referenced by that schema has a resolvable language string - a typo
//    in a label_key/help_key would otherwise only surface as a broken
//    label on a real admin screen.
$fieldsForType = new ReflectionMethod('ControllerDesignHomepage', 'fieldsForType');
$fieldsForType->setAccessible(true);

require __DIR__ . '/../../../upload/admin/language/en-gb/design/homepage.php';
/** @var array<string, string> $_ */
$languageStrings = $_;

foreach (array_keys(ControllerDesignHomepage::TYPES) as $type) {
    $fields = $fieldsForType->invoke(null, $type);

    assertTrue____(
        isset($fields['settings']) && isset($fields['content']),
        "Scenario 6 ($type): fieldsForType() should always return both a settings and a content list",
        $failures
    );

    foreach (array_merge($fields['settings'], $fields['content']) as $field) {
        assertTrue____(
            array_key_exists($field['label_key'], $languageStrings),
            "Scenario 6 ($type.{$field['name']}): label_key '{$field['label_key']}' should exist in the en-gb language file",
            $failures
        );

        if (isset($field['help_key'])) {
            assertTrue____(
                array_key_exists($field['help_key'], $languageStrings),
                "Scenario 6 ($type.{$field['name']}): help_key '{$field['help_key']}' should exist in the en-gb language file",
                $failures
            );
        }

        if ($field['type'] === 'select') {
            assertTrue____(
                isset($field['options']) && is_array($field['options']) && $field['options'] !== [],
                "Scenario 6 ($type.{$field['name']}): a select field must declare at least one option",
                $failures
            );
        }
    }
}

if ($failures) {
    fwrite(STDERR, 'FAIL: ' . count($failures) . " assertion(s) failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, " - {$failure}\n");
    }
    exit(1);
}

echo "PASS: Homepage admin schema test (6 scenarios, all block types)\n";
exit(0);
