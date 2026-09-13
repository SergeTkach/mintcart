<?php
/**
 * Standalone smoke test for MintCart\Onboarding\SocialProfileLinks.
 *
 * No PHPUnit/Composer in the repo yet, same as every other tests/mintcart/
 * smoke test. SocialProfileLinks has no OpenCart dependency by design, so
 * this runs entirely against plain arrays/strings.
 *
 * Run: php tests/mintcart/onboarding/social-profile-links-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

require __DIR__ . '/../../../upload/system/library/mintcart/onboarding/socialprofilelinks.php';

use MintCart\Onboarding\SocialProfileLinks;

$failures = [];

function assertTrue(bool $condition, string $message, array &$failures): void
{
    if (!$condition) {
        $failures[] = $message;
    }
}

function assertSame($expected, $actual, string $message, array &$failures): void
{
    if ($expected !== $actual) {
        $failures[] = $message . ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')';
    }
}

// 1. Blank is always valid - every network is optional.
assertTrue(SocialProfileLinks::isValidUrl(''), 'isValidUrl: blank is valid', $failures);

// 2. A well-formed http(s) URL is valid.
assertTrue(SocialProfileLinks::isValidUrl('https://facebook.com/example'), 'isValidUrl: https URL is valid', $failures);
assertTrue(SocialProfileLinks::isValidUrl('http://example.com'), 'isValidUrl: http URL is valid', $failures);

// 3. Syntactically valid but non-http(s) schemes are rejected - the
// FILTER_VALIDATE_URL-alone gap this class exists to close.
assertTrue(!SocialProfileLinks::isValidUrl('javascript:alert(1)'), 'isValidUrl: javascript: scheme rejected', $failures);
assertTrue(!SocialProfileLinks::isValidUrl('data:text/html,<script>alert(1)</script>'), 'isValidUrl: data: scheme rejected', $failures);

// 4. Garbage / non-URL text is rejected.
assertTrue(!SocialProfileLinks::isValidUrl('not a url'), 'isValidUrl: garbage text rejected', $failures);

// 5. normalize() drops blanks, trims, and preserves NETWORKS order
// regardless of the input array's key order.
$normalized = SocialProfileLinks::normalize([
    'youtube'   => ' https://youtube.com/@example ',
    'facebook'  => 'https://facebook.com/example',
    'instagram' => '',
    'twitter'   => '',
]);
assertSame(
    [
        ['network' => 'facebook', 'href' => 'https://facebook.com/example'],
        ['network' => 'youtube', 'href' => 'https://youtube.com/@example'],
    ],
    $normalized,
    'normalize: drops blanks, trims, preserves NETWORKS order',
    $failures
);

// 6. normalize() with everything blank returns an empty list.
assertSame([], SocialProfileLinks::normalize([]), 'normalize: empty input returns empty list', $failures);

// 7. byNetwork() is the inverse of normalize().
$byNetwork = SocialProfileLinks::byNetwork([
    ['network' => 'facebook', 'href' => 'https://facebook.com/example'],
    ['network' => 'youtube', 'href' => 'https://youtube.com/@example'],
]);
assertSame(
    ['facebook' => 'https://facebook.com/example', 'youtube' => 'https://youtube.com/@example'],
    $byNetwork,
    'byNetwork: re-keys a stored list by network',
    $failures
);

// 8. byNetwork() ignores malformed entries - missing keys, or a network
// outside NETWORKS (e.g. hand-edited storage, or a future wider list) -
// rather than trusting storage as-is.
$dirty = SocialProfileLinks::byNetwork([
    ['network' => 'facebook', 'href' => 'https://facebook.com/example'],
    ['network' => 'tiktok', 'href' => 'https://tiktok.com/@example'],
    ['network' => '', 'href' => 'https://example.com'],
    ['href' => 'https://example.com'],
    ['network' => 'twitter'],
]);
assertSame(['facebook' => 'https://facebook.com/example'], $dirty, 'byNetwork: ignores unknown network / missing keys', $failures);

// 9. normalize() then byNetwork() round-trips.
$roundTrip = SocialProfileLinks::byNetwork(SocialProfileLinks::normalize(['facebook' => 'https://facebook.com/example']));
assertSame(['facebook' => 'https://facebook.com/example'], $roundTrip, 'round-trip: normalize() then byNetwork() preserves the value', $failures);

if ($failures) {
    fwrite(STDERR, 'FAIL: ' . count($failures) . " assertion(s) failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, " - {$failure}\n");
    }
    exit(1);
}

echo 'PASS: SocialProfileLinks smoke test (' . 9 . " scenarios)\n";
exit(0);
