<?php
/**
 * Smoke test for MintCart\Seo\Slugger and UrlTemplates.
 *
 * Run: php tests/mintcart/seo/slugger-test.php
 */

declare(strict_types=1);

require __DIR__ . '/../../../upload/system/library/mintcart/seo/metatemplate.php';
require __DIR__ . '/../../../upload/system/library/mintcart/seo/slugger.php';
require __DIR__ . '/../../../upload/system/library/mintcart/seo/urltemplates.php';

use MintCart\Seo\Slugger;
use MintCart\Seo\UrlTemplates;

$failures = [];
$check = function ($ok, string $message) use (&$failures): void {
    if (!$ok) {
        $failures[] = $message;
    }
};

// --- Slugger -----------------------------------------------------------------------------
$check(Slugger::slug('Stoneware Mug - White') === 'stoneware-mug-white', 'latin');
$check(Slugger::slug('Oak & Co') === 'oak-co', 'ampersand and spaces');
$check(Slugger::slug('Горня для кави', 'uk-ua') === 'hornia-dlia-kavy', 'ukrainian г is h');
$check(Slugger::slug('Горшок и город', 'ru-ru') === 'gorshok-i-gorod', 'russian г is g, и is i');
$check(Slugger::slug('Щільний рушник', 'uk-ua') === 'shchilnyi-rushnyk', 'ukrainian щ, ь, и');
$check(Slugger::slug('П\'ять ґанків, ї, є') === 'piat-gankiv-yi-ye' || Slugger::slug("П'ять ґанків, ї, є") === 'piat-gankiv-yi-ye', 'apostrophe dropped, ґ ї є');
$check(Slugger::slug('Горня') === 'gornya', 'no hint, no ukrainian letters: russian-style я');
$check(Slugger::slug('Єдність їжак') === 'yednist-yizhak', 'ukrainian initial є / ї by letters alone');
$check(Slugger::slug('Мармурова чашка', '') === 'marmurova-chashka', 'no hint, no ukrainian letters: russian-style');
$check(Slugger::slug('Café Müller ß Łódź') === 'cafe-muller-ss-lodz', 'latin diacritics');
$check(Slugger::slug('<b>Mug</b> &amp; Cup') === 'mug-cup', 'html and entities');
$check(Slugger::slug('  --  ') === '' && Slugger::slug('日本') === '', 'nothing usable -> empty');
$long = Slugger::slug(str_repeat('word ', 60));
$check(strlen($long) <= 100 && !str_ends_with($long, '-'), 'long text is cut on a dash');

$taken = ['mug' => true, 'mug-2' => true];
$check(Slugger::unique('mug', fn (string $k): bool => isset($taken[$k])) === 'mug-3', 'unique adds -3');
$check(Slugger::unique('cup', fn (string $k): bool => isset($taken[$k])) === 'cup', 'a free keyword is kept');
$check(Slugger::unique('', fn (string $k): bool => false) === '', 'empty stays empty');

// --- UrlTemplates ------------------------------------------------------------------------
$check(UrlTemplates::forType('', 'product') === '{name}', 'default template');
$check(UrlTemplates::forType('{"product":"{brand} {name}"}', 'product') === '{brand} {name}', 'saved template');
$check(UrlTemplates::forType('{"product":"{brand} {name}"}', 'category') === '{name}', 'other type -> default');
$json = UrlTemplates::normalize(['product' => ' {brand} {name} ', 'category' => '{name}', 'bogus' => 'x', 'information' => '']);
$check(json_decode($json, true) === ['product' => '{brand} {name}'], 'normalize keeps only non-default known templates');
$check(UrlTemplates::keyword('{brand} {name}', ['brand' => 'Nordhem', 'name' => 'Горня'], 'uk-ua') === 'nordhem-hornia', 'keyword from template');
$check(UrlTemplates::keyword('{brand}-{name}', ['brand' => '', 'name' => 'Mug']) === 'mug', 'missing part: no stray dash');
$check(UrlTemplates::keyword('{brand}', ['brand' => '', 'name' => 'Mug']) === 'mug', 'empty template result falls back to the name');

if ($failures) {
    echo implode("\n", $failures), "\n";
    exit(1);
}

echo "OK slugger-test\n";
