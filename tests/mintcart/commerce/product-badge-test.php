<?php
/**
 * Standalone test for MintCart\Commerce\ProductBadge and the manual-badge
 * part of ProductViewProvider (no OpenCart dependency).
 *
 * Run: php tests/mintcart/commerce/product-badge-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

require __DIR__ . '/../../../upload/system/library/mintcart/commerce/productbadge.php';
require __DIR__ . '/../../../upload/system/library/mintcart/commerce/productviewprovider.php';

use MintCart\Commerce\ProductBadge;
use MintCart\Commerce\ProductViewProvider;

$failures = [];

function assertSame($expected, $actual, string $message, array &$failures): void
{
    if ($expected !== $actual) {
        $failures[] = $message . ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')';
    }
}

// normalize(): valid, trimmed, whitespace collapsed, colour lower-cased.
assertSame(['label' => 'Hit of sales', 'color' => '#d92d20'], ProductBadge::normalize(['label' => "  Hit   of sales ", 'color' => '#D92D20']), 'normalize trims, collapses spaces, lower-cases colour', $failures);
assertSame(['label' => '-20%', 'color' => ''], ProductBadge::normalize(['label' => '-20%']), 'colour is optional', $failures);

// normalize(): rejections.
assertSame(null, ProductBadge::normalize(['label' => '   ']), 'empty label rejected', $failures);
assertSame(null, ProductBadge::normalize([]), 'missing label rejected', $failures);
assertSame(null, ProductBadge::normalize(['label' => str_repeat('a', 33)]), '33 chars rejected', $failures);
assertSame(32, strlen(ProductBadge::normalize(['label' => str_repeat('a', 32)])['label']), '32 chars accepted', $failures);
assertSame(null, ProductBadge::normalize(['label' => 'x', 'color' => 'red']), 'named colour rejected', $failures);
assertSame(null, ProductBadge::normalize(['label' => 'x', 'color' => '#fff']), 'short hex rejected', $failures);
assertSame(null, ProductBadge::normalize(['label' => 'x', 'color' => '#ff0000;background:url(x)']), 'css injection rejected', $failures);
assertSame('Хіт', ProductBadge::normalize(['label' => 'Хіт'])['label'], 'multibyte label accepted', $failures);

// readableTextColor(): dark background -> white text, light -> dark text.
assertSame('#ffffff', ProductBadge::readableTextColor('#d92d20'), 'white text on red', $failures);
assertSame('#ffffff', ProductBadge::readableTextColor('#000000'), 'white text on black', $failures);
assertSame('#1d1f23', ProductBadge::readableTextColor('#ffe066'), 'dark text on yellow', $failures);
assertSame('#1d1f23', ProductBadge::readableTextColor('#ffffff'), 'dark text on white', $failures);

// toView(): never lets a non-hex colour through.
$view = ProductBadge::toView(['label' => 'Hit', 'color' => 'red;x']);
assertSame('', $view['color'], 'toView drops an invalid colour', $failures);
assertSame('', $view['on_color'], 'no text colour without a colour', $failures);
assertSame('custom', $view['type'], 'toView type', $failures);

// ProductViewProvider appends manual badges after the automatic ones.
$provider = new ProductViewProvider();
$card = $provider->build([
    'id' => 1, 'name' => 'Mug', 'url' => '/mug', 'image' => '/mug.jpg',
    'special' => '$9', 'quantity' => 5,
    'manual_badges' => [
        ['label' => 'Bestseller', 'color' => '#2457d6'],
        ['label' => '   ', 'color' => ''],
        ['label' => 'Eco', 'color' => ''],
    ],
]);
$types = array_map(static fn ($b) => $b['type'], $card['product']['badges']);
assertSame(['sale', 'custom', 'custom'], $types, 'automatic first, then manual, blank label skipped', $failures);
assertSame('Bestseller', $card['product']['badges'][1]['label'], 'manual label passed through', $failures);
assertSame('#2457d6', $card['product']['badges'][1]['color'], 'manual colour passed through', $failures);
assertSame('', $card['product']['badges'][2]['color'], 'no colour -> empty', $failures);

// Without manual_badges nothing changes.
$plain = $provider->build(['id' => 2, 'name' => 'Cup', 'url' => '/cup', 'image' => '/cup.jpg']);
assertSame([], $plain['product']['badges'], 'no badges by default', $failures);

if ($failures) {
    fwrite(STDERR, "FAIL\n - " . implode("\n - ", $failures) . "\n");
    exit(1);
}

echo "OK product-badge-test\n";
