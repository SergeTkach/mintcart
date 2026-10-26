<?php
/**
 * Standalone test for MintCart\Blocks\SliderBlockProvider (normalization and
 * output safety; no OpenCart/DB dependency).
 *
 * Run: php tests/mintcart/blocks/slider-block-provider-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

require __DIR__ . '/../../../upload/system/library/mintcart/blocks/sliderblockprovider.php';

use MintCart\Blocks\SliderBlockProvider;

$failures = [];

function check($expected, $actual, string $message, array &$failures): void
{
    if ($expected !== $actual) {
        $failures[] = $message . ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')';
    }
}

$provider = new SliderBlockProvider();

// 1. Defaults.
$r = $provider->build([], []);
check('split', $r['layout'], 'default layout', $failures);
check(6000, $r['autoplay'], 'default autoplay', $failures);
check([], $r['slides'], 'no slides by default', $failures);
check([], $r['cards'], 'no cards by default', $failures);

// 2. A full slide: structure from settings, text from content, matched by position.
$r = $provider->build(
    ['layout' => 'full', 'autoplay' => 4000, 'items' => [['image' => 'catalog/a.jpg', 'link' => '/shop', 'btn2_link' => '/more', 'color' => 'light']]],
    ['items' => [[
        'eyebrow' => 'New', 'title' => 'Light for <em>slow</em> evenings', 'subtitle' => 'Lamps', 'price' => '$39', 'old_price' => '$49',
        'btn_text' => 'Shop', 'btn2_text' => 'More',
    ]]]
);
check('full', $r['layout'], 'layout kept', $failures);
check(4000, $r['autoplay'], 'autoplay kept', $failures);
check(1, count($r['slides']), 'one slide', $failures);
$slide = $r['slides'][0];
check('catalog/a.jpg', $slide['image'], 'image from settings', $failures);
check('light', $slide['color'], 'colour from settings', $failures);
check('Light for <em>slow</em> evenings', $slide['title'], 'em survives in the title', $failures);
check('$49', $slide['old_price'], 'old price', $failures);
check('/more', $slide['btn2_link'], 'second link', $failures);

// 3. Safety: only <em>/<br> in titles, everything else escaped; attributes dropped.
$r = $provider->build(['items' => [[]]], ['items' => [[
    'title' => '<script>alert(1)</script><em onclick="x()">Hi</em><br/>', 'eyebrow' => '<b>x</b>', 'subtitle' => 'a & b',
]]]);
$slide = $r['slides'][0];
check('&lt;script&gt;alert(1)&lt;/script&gt;&lt;em onclick=&quot;x()&quot;&gt;Hi</em><br>', $slide['title'], 'script and attributes are escaped', $failures);
check('&lt;b&gt;x&lt;/b&gt;', $slide['eyebrow'], 'eyebrow escaped', $failures);
check('a &amp; b', $slide['subtitle'], 'subtitle escaped', $failures);

// 4. Autoplay: 0 = off, too fast raised to 2000, junk layout/colour fall back.
check(0, $provider->build(['autoplay' => 0], [])['autoplay'], 'autoplay 0 = off', $failures);
check(2000, $provider->build(['autoplay' => 300], [])['autoplay'], 'autoplay raised to 2000', $failures);
check('split', $provider->build(['layout' => 'grid'], [])['layout'], 'unknown layout -> split', $failures);
$r = $provider->build(['items' => [['color' => 'red']]], ['items' => [['title' => 'T']]]);
check('navy', $r['slides'][0]['color'], 'unknown colour -> navy', $failures);

// 5. A slide with neither title nor picture is dropped; text without picture stays.
$r = $provider->build(['items' => [[], ['image' => 'catalog/b.jpg'], []]], ['items' => [['subtitle' => 's'], ['title' => ''], ['title' => 'Only text']]]);
check(2, count($r['slides']), 'empty slide dropped', $failures);
check('', $r['slides'][1]['image'], 'text-only slide has no picture', $failures);

// 6. Cards only for the cards layout, at most two, picture required.
$cards = ['cards' => [['image' => 'catalog/c1.jpg', 'link' => '/a'], ['image' => ''], ['image' => 'catalog/c2.jpg'], ['image' => 'catalog/c3.jpg']]];
$content = ['cards' => [['kicker' => 'Sale', 'title' => 'Up to <em>30%</em>', 'link_text' => 'Shop'], [], ['title' => 'Two'], ['title' => 'Three']]];
check([], $provider->build($cards + ['layout' => 'split'], $content)['cards'], 'cards ignored outside the cards layout', $failures);
$r = $provider->build($cards + ['layout' => 'cards'], $content);
check(2, count($r['cards']), 'at most two cards, pictureless skipped', $failures);
check('Up to <em>30%</em>', $r['cards'][0]['title'], 'card title', $failures);
check('Two', $r['cards'][1]['title'], 'second card is the next one with a picture', $failures);

if ($failures) {
    fwrite(STDERR, "FAIL\n - " . implode("\n - ", $failures) . "\n");
    exit(1);
}

echo "PASS: SliderBlockProvider (6 scenarios)\n";
