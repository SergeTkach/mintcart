<?php
/**
 * Standalone smoke test for MintCart\Catalog\DescriptionSplitter.
 *
 * Run: php tests/mintcart/catalog/description-splitter-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

require __DIR__ . '/../../../upload/system/library/mintcart/catalog/descriptionsplitter.php';

use MintCart\Catalog\DescriptionSplitter;

$failures = [];
$check = function ($ok, string $message) use (&$failures): void {
    if (!$ok) {
        $failures[] = $message;
    }
};
$splitter = new DescriptionSplitter();

$short = $splitter->split('<p>Коротко</p>', 360);
$check($short['intro'] === '<p>Коротко</p>' && $short['rest'] === '', 'short text stays whole');

$blocks = $splitter->split('<p>' . str_repeat('а', 200) . '</p><p>' . str_repeat('б', 200) . '</p><p>third</p>', 360);
$check(substr_count($blocks['intro'], '<p>') === 1 && substr_count($blocks['rest'], '<p>') === 2, 'whole blocks move to rest');

$long = $splitter->split('<p>' . str_repeat('слово ', 200) . '</p>', 360);
$check(mb_strlen(strip_tags($long['intro'])) <= 362 && mb_substr(strip_tags($long['intro']), -1) === '…', 'long block cut with ellipsis');
$check($long['rest'] !== '' && substr($long['rest'], 0, 3) === '<p>', 'remainder keeps the tag');

$mixed = $splitter->split('<p>' . str_repeat('x ', 300) . '<b>bold</b></p><p>next</p>', 360);
$check($mixed['rest'] !== '' && strpos($mixed['rest'], 'next') !== false, 'block with inline markup goes to rest, intro is an excerpt');

if ($failures) {
    echo implode("\n", $failures), "\n";
    exit(1);
}
echo "OK\n";
