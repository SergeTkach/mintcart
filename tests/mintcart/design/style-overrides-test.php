<?php
/**
 * Standalone smoke test for MintCart\Design\StyleOverrides.
 *
 * Run: php tests/mintcart/design/style-overrides-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

require __DIR__ . '/../../../upload/system/library/mintcart/design/styleoverrides.php';

use MintCart\Design\StyleOverrides;

$failures = [];
$check = function ($ok, string $message) use (&$failures): void {
    if (!$ok) {
        $failures[] = $message;
    }
};

$empty = StyleOverrides::build([]);
$check($empty['css'] === '' && $empty['fonts_url'] === '', 'nothing chosen, nothing emitted');

$check(StyleOverrides::hex('#ABC') === '#aabbcc' && StyleOverrides::hex(' #0A7B5C ') === '#0a7b5c', 'hex normalised');
$check(StyleOverrides::hex('red') === '' && StyleOverrides::hex('#12345') === '' && StyleOverrides::hex('#fff;}body{') === '', 'non-hex rejected');

$check(StyleOverrides::onColor('#ffffff') === '#111111' && StyleOverrides::onColor('#000000') === '#ffffff', 'text colour flips with luminance');
$check(StyleOverrides::onColor('#0a7b5c') === '#ffffff' && StyleOverrides::onColor('#ffd400') === '#111111', 'brand green takes white, yellow takes dark');

$n = StyleOverrides::normalize(['primary' => 'x', 'radius' => 'hacked', 'border' => 'strong', 'button_style' => 'outline', 'font_body' => 'Comic Sans', 'container' => 'wide']);
$check($n['primary'] === '' && $n['radius'] === '' && $n['font_body'] === '', 'unknown values dropped');
$check($n['border'] === 'strong' && $n['button_style'] === 'outline' && $n['container'] === 'wide', 'known values kept');

$full = StyleOverrides::build([
    'primary' => '#0a7b5c', 'accent' => '#ffd400', 'radius' => 'rounded', 'border' => 'strong',
    'button_style' => 'outline', 'container' => 'wide',
    'font_heading' => 'Playfair Display', 'font_body' => 'Inter',
]);
$css = $full['css'];
$check(strpos($css, '--mc-primary:#0a7b5c') !== false && strpos($css, '--mc-on-primary:#ffffff') !== false, 'primary + contrast text');
$check(strpos($css, '--mc-radius-lg:1rem') !== false, 'radius scale');
$check(strpos($css, '--mc-border-width:2px') !== false, 'strong border');
$check(strpos($css, '--mc-font-family-heading:"Playfair Display", serif') !== false && strpos($css, '--mc-font-family-base:"Inter", sans-serif') !== false, 'fonts');
$check(strpos($css, 'max-width:1520px') !== false, 'wide container');
$check(strpos($css, '--mc-button-bg:transparent') !== false && strpos($css, 'button-radius') === false, 'outline button, shape always follows the corners');
$check($full['fonts_url'] === 'https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Inter:wght@400;600;700&display=swap', 'Google Fonts URL');

$system = StyleOverrides::build(['font_body' => 'system', 'font_heading' => 'georgia']);
$check($system['fonts_url'] === '', 'system fonts load nothing');

$bodyOnly = StyleOverrides::build(['font_body' => 'Rubik']);
$check(strpos($bodyOnly['css'], '--mc-font-family-heading:var(--mc-font-family-base)') !== false, 'headings follow the body font when only it is set');

if ($failures) {
    echo implode("\n", $failures), "\n";
    exit(1);
}
echo "OK\n";
