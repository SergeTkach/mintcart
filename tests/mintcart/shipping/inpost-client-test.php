<?php
/**
 * Standalone smoke test for MintCart\Shipping\InpostClient (fake transport).
 *
 * Run: php tests/mintcart/shipping/inpost-client-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

require __DIR__ . '/../../../upload/system/library/mintcart/shipping/inpostclient.php';

use MintCart\Shipping\InpostClient;

$failures = [];
$check = function ($ok, string $message) use (&$failures): void {
    if (!$ok) {
        $failures[] = $message;
    }
};

$calls = [];
$transport = function (string $url, array $headers) use (&$calls): ?string {
    $calls[] = [$url, $headers];

    return json_encode(['items' => [
        [
            'name' => 'UKL123',
            'address' => ['line1' => '1 High Street', 'line2' => 'London SW1A 1AA'],
            'address_details' => ['city' => 'London', 'post_code' => 'SW1A 1AA', 'street' => 'High Street', 'building_number' => '1'],
            'location_description' => 'Inside Tesco',
        ],
        ['name' => 'UKL124', 'address_details' => ['city' => 'Leeds', 'street' => 'Main Road', 'building_number' => '5']],
        ['name' => ''],
    ]]);
};

$client = new InpostClient('https://api-uk-points.easypack24.net/v1/', 'TOKEN', $transport);
$points = $client->searchPoints(' sw1a   1aa ');

$check(count($points) === 2, 'rows without a name skipped');
$check($points[0]['id'] === 'UKL123' && $points[0]['city'] === 'London' && $points[0]['address'] === '1 High Street', 'locker parsed');
$check($points[0]['text'] === 'UKL123: 1 High Street, London, SW1A 1AA (Inside Tesco)', 'locker text: name, street, city, postcode, where');
$check($points[1]['address'] === 'Main Road 5' || $points[1]['address'] === 'Main Road 5', 'street built from street + number when line1 is missing');
$check(strpos($calls[0][0], 'https://api-uk-points.easypack24.net/v1/points?') === 0 && strpos($calls[0][0], 'relative_post_code=SW1A+1AA') !== false, 'asks the base URL with the normalised postcode');
$check(in_array('Authorization: Bearer TOKEN', $calls[0][1], true), 'token sent as a bearer');

$before = count($calls);
$check($client->searchPoints('  ') === [] && $client->searchPoints(str_repeat('A', 30)) === [] && count($calls) === $before, 'empty or absurd input makes no request');

(new InpostClient('https://x', '', function (string $url, array $headers) use (&$calls) {
    $calls[] = [$url, $headers];

    return null;
}))->searchPoints('E1 6AN');
$check(!in_array('Authorization: Bearer ', end($calls)[1], true) && count(end($calls)[1]) === 1, 'no token, no Authorization header');
$check((new InpostClient('https://x', '', fn () => null))->searchPoints('E1 6AN') === [], 'network failure gives an empty list');
$check((new InpostClient('https://x', '', fn () => '{"error":"bad"}'))->searchPoints('E1 6AN') === [], 'unexpected answer gives an empty list');

if ($failures) {
    echo implode("\n", $failures), "\n";
    exit(1);
}

echo "OK inpost-client-test\n";
