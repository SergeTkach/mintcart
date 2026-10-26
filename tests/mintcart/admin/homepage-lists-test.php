<?php
/**
 * Smoke test for the Homepage block form's row editor server side
 * (ControllerDesignHomepage::fieldsForType() / normalizeLists()).
 *
 * Run: php tests/mintcart/admin/homepage-lists-test.php
 */

declare(strict_types=1);

class Controller
{
}

require __DIR__ . '/../../../upload/admin/controller/design/homepage.php';

$failures = [];
$check = function ($ok, string $message) use (&$failures): void {
    if (!$ok) {
        $failures[] = $message;
    }
};

$controller = (new ReflectionClass('ControllerDesignHomepage'))->newInstanceWithoutConstructor();

$call = function (string $method, ...$args) use ($controller) {
    $m = new ReflectionMethod($controller, $method);
    $m->setAccessible(true);

    return $m->invoke($controller, ...$args);
};

$fields = (new ReflectionMethod('ControllerDesignHomepage', 'fieldsForType'));
$fields->setAccessible(true);

// Every list type has its rows described, and no raw-JSON field is left.
foreach (['slider', 'banner-grid', 'benefits', 'testimonials', 'brands', 'gallery', 'faq'] as $type) {
    $f = $fields->invoke(null, $type);
    $check(!empty($f['lists']), "$type has a list");

    foreach (['settings', 'content'] as $scope) {
        foreach ($f[$scope] as $field) {
            $check($field['type'] !== 'json', "$type $scope has no json field");
        }
    }
}

$slider = $fields->invoke(null, 'slider');

// Rows come back in the posted order; an empty row is dropped from every language alike.
$result = $call(
    'normalizeLists',
    $slider['lists'],
    ['items' => [
        '0' => ['image' => 'catalog/a.jpg', 'link' => 'x', 'color' => 'light', 'btn2_link' => ''],
        '1' => ['image' => '', 'link' => '', 'color' => 'navy', 'btn2_link' => ''],
        '2' => ['image' => 'catalog/c.jpg', 'link' => '', 'color' => 'bogus', 'btn2_link' => ''],
    ]],
    [
        1 => ['items' => ['0' => ['title' => 'One'], '1' => ['title' => ''], '2' => ['title' => 'Three']]],
        2 => ['items' => ['0' => ['title' => 'Один'], '1' => ['title' => ''], '2' => ['title' => '']]],
    ]
);

$check(count($result['settings']['items']) === 2, 'the empty row is dropped');
$check($result['settings']['items'][0]['image'] === 'catalog/a.jpg' && $result['settings']['items'][0]['color'] === 'light', 'first row keeps its values');
$check($result['settings']['items'][1]['image'] === 'catalog/c.jpg', 'rows keep the posted order');
$check($result['settings']['items'][1]['color'] === '', 'an unknown select value is dropped');
$check($result['content'][1]['items'][1]['title'] === 'Three', 'language 1 text of row 2');
$check($result['content'][2]['items'][0]['title'] === 'Один' && $result['content'][2]['items'][1]['title'] === '', 'language 2 stays aligned with the shared rows');
$check($result['settings']['cards'] === [] && $result['content'][1]['cards'] === [], 'a list with no rows is saved empty');

// Row order follows the numeric keys, not the order they arrived in.
$result = $call('normalizeLists', $fields->invoke(null, 'faq')['lists'], [], [1 => ['items' => ['10' => ['question' => 'B', 'answer' => ''], '2' => ['question' => 'A', 'answer' => '']]]]);
$check($result['content'][1]['items'][0]['question'] === 'A' && $result['content'][1]['items'][1]['question'] === 'B', 'keys are sorted numerically');

// The cards list is capped at two.
$cards = $slider['lists'];
$posted = [];

for ($i = 0; $i < 4; $i++) {
    $posted[$i] = ['image' => "catalog/c$i.jpg", 'link' => ''];
}

$result = $call('normalizeLists', ['cards' => $cards['cards']], ['cards' => $posted], [1 => []]);
$check(count($result['settings']['cards']) === 2, 'promo cards are capped at two');

// OpenCart HTML-escapes posted strings; they are stored as typed.
$result = $call('normalizeLists', ['items' => $slider['lists']['items']], ['items' => [0 => ['image' => '', 'link' => 'index.php?route=a&amp;path=1']]], [1 => ['items' => [0 => ['title' => 'A &lt;em&gt;b&lt;/em&gt;']]]]);
$check($result['settings']['items'][0]['link'] === 'index.php?route=a&path=1', 'posted entities in a link are decoded');
$check($result['content'][1]['items'][0]['title'] === 'A <em>b</em>', 'posted entities in a text are decoded');

if ($failures) {
    echo implode("\n", $failures), "\n";
    exit(1);
}

echo "OK homepage-lists-test\n";
