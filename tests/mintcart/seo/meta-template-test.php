<?php
/**
 * Smoke test for MintCart\Seo\MetaTemplate, MetaRules and the template path of MetaBuilder.
 *
 * Run: php tests/mintcart/seo/meta-template-test.php
 */

declare(strict_types=1);

require __DIR__ . '/../../../upload/system/library/mintcart/seo/metatemplate.php';
require __DIR__ . '/../../../upload/system/library/mintcart/seo/metarules.php';
require __DIR__ . '/../../../upload/system/library/mintcart/seo/metabuilder.php';

use MintCart\Seo\MetaBuilder;
use MintCart\Seo\MetaRules;
use MintCart\Seo\MetaTemplate;

$failures = [];
$check = function ($ok, string $message) use (&$failures): void {
    if (!$ok) {
        $failures[] = $message;
    }
};

// --- MetaTemplate ------------------------------------------------------------------------
$check(MetaTemplate::render('{name} - {store}', ['name' => 'Mug', 'store' => 'Shop']) === 'Mug - Shop', 'plain substitution');
$check(MetaTemplate::render('{name} - {brand} - {store}', ['name' => 'Mug', 'brand' => '', 'store' => 'Shop']) === 'Mug - Shop', 'a missing part drops its separator');
$check(MetaTemplate::render('{name} | {store}', ['name' => 'Mug']) === 'Mug', 'a trailing separator is dropped');
$check(MetaTemplate::render('{brand}: {name}', ['name' => 'Mug']) === 'Mug', 'a leading separator is dropped');
$check(MetaTemplate::render('Buy {name} online', ['name' => '<b>Mug &amp; Cup</b>']) === 'Buy Mug & Cup online', 'HTML is stripped and entities decoded');
$check(MetaTemplate::render('{nope}', ['name' => 'Mug']) === '', 'an unknown variable renders empty');
$long = '<p>' . str_repeat('word ', 80) . '</p>';
$cut = MetaTemplate::render('{description:50}', ['description' => $long]);
$check(mb_strlen($cut) <= 51 && str_ends_with($cut, '…') && !str_contains($cut, 'wor…'), 'description is cut on a word boundary');
$check(MetaTemplate::render('{name:100}', ['name' => 'Short']) === 'Short', 'a short value is not cut');
$check(MetaTemplate::render('{name} — {store}', ['name' => 'Горня', 'store' => 'Магазин']) === 'Горня — Магазин', 'multibyte text');
$check(MetaTemplate::variables('{name} - {description:160} {name}') === ['name', 'description'], 'variables used');

// --- MetaBuilder -------------------------------------------------------------------------
$plain = (new MetaBuilder())->build('', null, 'Mug');
$check($plain['title'] === 'Mug' && $plain['description'] === '', 'no rules: the old fallback rule');

$rules = ['title' => ['template' => '{name} - {store}', 'always' => false], 'description' => ['template' => '{description:20}', 'always' => false]];
$vars = ['name' => 'Mug', 'store' => 'Shop', 'description' => 'A thick comfortable handle mug for coffee'];
$built = (new MetaBuilder($rules))->build('', '', 'Mug', null, $vars);
$check($built['title'] === 'Mug - Shop', 'blank title uses the template');
$check($built['description'] === 'A thick comfortable…', 'blank description uses the template');
$built = (new MetaBuilder($rules))->build('My own title', 'My own text', 'Mug', null, $vars);
$check($built['title'] === 'My own title' && $built['description'] === 'My own text', 'a filled field is kept when the rule is not "always"');
$built = (new MetaBuilder(['title' => ['template' => '{name} - {store}', 'always' => true]]))->build('', '', 'x', null, ['name' => 'Mug &amp; Cup', 'store' => 'A "B" & C']);
$check($built['title'] === 'Mug &amp; Cup - A &quot;B&quot; &amp; C', 'a rendered template is HTML-escaped like the stored values');
$rules['title']['always'] = true;
$built = (new MetaBuilder($rules))->build('My own title', '', 'Mug', null, $vars);
$check($built['title'] === 'Mug - Shop', '"always" overrides a filled title');
$built = (new MetaBuilder(['title' => ['template' => '{brand}', 'always' => true]]))->build('Own', '', 'Mug', null, $vars);
$check($built['title'] === 'Own', 'a template that renders empty falls back');

// --- MetaRules ---------------------------------------------------------------------------
$json = MetaRules::normalize([
    'product' => [2 => ['title' => ['template' => ' {name} - {store} ', 'always' => '1'], 'description' => ['template' => '']]],
    'bogus' => [2 => ['title' => ['template' => 'x']]],
    'category' => [9 => ['title' => ['template' => 'y']]],
], [1, 2]);
$decoded = json_decode($json, true);
$check(array_keys($decoded) === ['product'], 'unknown types / languages / blank templates are dropped');
$rule = MetaRules::forPage($json, 'product', 2);
$check($rule['title'] === ['template' => '{name} - {store}', 'always' => true] && !isset($rule['description']), 'forPage reads the saved rule');
$check(MetaRules::forPage($json, 'product', 1) === [] && MetaRules::forPage('', 'product', 2) === [] && MetaRules::forPage('not json', 'product', 2) === [], 'no rule / bad JSON -> none');

if ($failures) {
    echo implode("\n", $failures), "\n";
    exit(1);
}

echo "OK meta-template-test\n";
