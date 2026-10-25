<?php
/**
 * Standalone smoke test for MintCart\Seo\LanguagePrefix.
 *
 * Run: php tests/mintcart/seo/language-prefix-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

require __DIR__ . '/../../../upload/system/library/mintcart/seo/languageprefix.php';

use MintCart\Seo\LanguagePrefix;

$failures = [];
$check = function ($ok, string $message) use (&$failures): void {
    if (!$ok) {
        $failures[] = $message;
    }
};

$p = new LanguagePrefix(['en-gb', 'uk-ua', 'ru-ru'], 'en-gb');

$check($p->folder('en-gb') === '' && $p->folder('uk-ua') === 'uk' && $p->folder('ru-ru') === 'ru', 'default has no folder, others the short code');
$check($p->split('uk/shoes/red') === ['code' => 'uk-ua', 'rest' => 'shoes/red'], 'folder split off');
$check($p->split('uk') === ['code' => 'uk-ua', 'rest' => ''], 'bare folder is the language home');
$check($p->split('UK/') === ['code' => 'uk-ua', 'rest' => ''], 'case-insensitive, trailing slash');
$check($p->split('shoes/uk') === ['code' => null, 'rest' => 'shoes/uk'], 'only the first segment counts');
$check($p->split('ukulele') === ['code' => null, 'rest' => 'ukulele'], 'a keyword starting with a folder is not a folder');
$check($p->split('en/shoes')['code'] === 'en-gb', 'default language folder recognised (for redirect)');

$bases = ['http://shop.test/', 'https://shop.test/'];
$check($p->link('http://shop.test/shoes?x=1', $bases, 'uk-ua') === 'http://shop.test/uk/shoes?x=1', 'folder inserted after the base');
$check($p->link('https://shop.test/', $bases, 'uk-ua') === 'https://shop.test/uk/', 'home link');
$check($p->link('http://shop.test/shoes', $bases, 'en-gb') === 'http://shop.test/shoes', 'default language untouched');
$check($p->link('http://other.test/x', $bases, 'uk-ua') === 'http://other.test/x', 'foreign link untouched');
$check($p->link('http://shop.test/index.php?route=a/b', ['http://shop.test/'], 'ru-ru') === 'http://shop.test/ru/index.php?route=a/b', 'non-SEO link gets the folder too');

$q = new LanguagePrefix(['en-gb', 'en-us', 'de-de'], 'en-gb');
$check($q->folder('en-us') === 'en-us' && $q->folder('de-de') === 'de' && $q->folder('en-gb') === '', 'shared short code falls back to the full code');
$check($q->split('en-us/x')['code'] === 'en-us' && $q->split('en/x')['code'] === null, 'ambiguous short folder is not accepted');

if ($failures) {
    echo implode("\n", $failures), "\n";
    exit(1);
}
echo "OK\n";
