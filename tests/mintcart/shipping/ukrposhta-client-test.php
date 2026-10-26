<?php
/**
 * Standalone smoke test for MintCart\Shipping\UkrposhtaClient (fake transport).
 *
 * Run: php tests/mintcart/shipping/ukrposhta-client-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

require __DIR__ . '/../../../upload/system/library/mintcart/shipping/ukrposhtaclient.php';

use MintCart\Shipping\UkrposhtaClient;

$failures = [];
$check = function ($ok, string $message) use (&$failures): void {
    if (!$ok) {
        $failures[] = $message;
    }
};

$urls = [];
$headers = [];
$transport = function (string $url, array $h) use (&$urls, &$headers): ?string {
    $urls[] = $url;
    $headers = $h;

    if (strpos($url, 'get_regions_by_region_ua') !== false) {
        // One region only: the API returns a bare object, not a list.
        return json_encode(['Entries' => ['Entry' => ['REGION_ID' => '7', 'REGION_UA' => 'Київ']]]);
    }

    return json_encode(['Entries' => ['Entry' => [
        ['DISTRICT_UA' => 'Київ', 'CITY_UA' => 'Київ', 'POSTCODE' => '01001', 'ADDRESS' => 'вул. Хрещатик, 22', 'PHONE' => '0800'],
        ['DISTRICT_UA' => 'Київ', 'CITY_UA' => 'Київ', 'PDCITY_UA' => 'Київ', 'POSTCODE' => '01002', 'ADDRESS' => ''],
        ['DISTRICT_UA' => '', 'CITY_UA' => 'x', 'POSTCODE' => '1'],
    ]]]);
};

$client = new UkrposhtaClient('KEY', $transport);

$regions = $client->getRegions();
$check($regions === [['id' => 7, 'name' => 'Київ']], 'single-entry response becomes a list');
$check(in_array('Authorization: Bearer KEY', $headers, true) && strpos($urls[0], 'apiKey=KEY') !== false, 'key sent as bearer token and query');

$directory = $client->getRegionDirectory(7);
$check(count($directory['cities']) === 1, 'one city for both offices');
$check(count($directory['branches']) === 2, 'entries without a district are skipped');
$check($directory['branches'][0]['description'] === '01001 - Київ, вул. Хрещатик, 22' && $directory['branches'][1]['description'] === '01002 - Київ', 'branch description');
$check(isset($directory['cities'][UkrposhtaClient::cityRef(7, 'Київ', 'Київ')]), 'city keyed by its ref');
$check(strpos($urls[1], 'poRegionId=7') !== false, 'region id sent');

$check((new UkrposhtaClient('', $transport))->getRegions() === [], 'no key, no request');
$check((new UkrposhtaClient('K', fn () => null))->getRegions() === [], 'network failure gives an empty list');
$check((new UkrposhtaClient('K', fn () => '{"x":1}'))->getRegionDirectory(1) === ['cities' => [], 'branches' => []], 'unexpected response gives an empty directory');

if ($failures) {
    echo implode("\n", $failures), "\n";
    exit(1);
}
echo "OK\n";
