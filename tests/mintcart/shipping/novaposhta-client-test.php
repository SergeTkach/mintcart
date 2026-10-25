<?php
/**
 * Standalone smoke test for MintCart\Shipping\NovaPoshtaClient (fake transport).
 *
 * Run: php tests/mintcart/shipping/novaposhta-client-test.php
 * Exit code 0 = pass, 1 = fail.
 */

declare(strict_types=1);

require __DIR__ . '/../../../upload/system/library/mintcart/shipping/novaposhtaclient.php';

use MintCart\Shipping\NovaPoshtaClient;

$failures = [];
$check = function ($ok, string $message) use (&$failures): void {
    if (!$ok) {
        $failures[] = $message;
    }
};

$sent = [];
$transport = function (string $url, string $json) use (&$sent): ?string {
    $sent[] = json_decode($json, true);
    $request = end($sent);

    if ($request['calledMethod'] === 'searchSettlements') {
        return json_encode(['success' => true, 'data' => [['Addresses' => [
            ['Present' => 'м. Київ, Київська обл.', 'MainDescription' => 'Київ', 'DeliveryCity' => 'ref-kyiv'],
            ['Present' => 'без міста', 'MainDescription' => 'x', 'DeliveryCity' => ''],
        ]]]]);
    }

    return json_encode(['success' => true, 'data' => [
        ['Ref' => 'w1', 'Description' => 'Відділення №1', 'TypeOfWarehouse' => 'branch'],
        ['Ref' => 'w2', 'Description' => 'Поштомат №2', 'TypeOfWarehouse' => NovaPoshtaClient::POSTOMAT_TYPE],
        ['Ref' => '', 'Description' => 'broken'],
    ]]);
};

$client = new NovaPoshtaClient('KEY', $transport);

$cities = $client->searchCities(' Киї ');
$check(count($cities) === 1 && $cities[0]['id'] === 'ref-kyiv' && $cities[0]['text'] === 'м. Київ, Київська обл.', 'city search parsed, rows without a city ref skipped');
$check($sent[0]['apiKey'] === 'KEY' && $sent[0]['methodProperties']['CityName'] === 'Киї', 'city search sends the key and the trimmed term');

$points = $client->getPoints('ref-kyiv');
$check(count($points) === 2 && $points[0]['postomat'] === false && $points[1]['postomat'] === true, 'points parsed, locker flagged, broken rows skipped');
$check($sent[1]['methodProperties']['CityRef'] === 'ref-kyiv', 'points asked for the city ref');

$calls = count($sent);
$check($client->searchCities('') === [] && $client->getPoints('') === [] && count($sent) === $calls, 'empty input makes no request');
$check((new NovaPoshtaClient('', $transport))->searchCities('Київ') === [], 'no API key makes no request');
$check((new NovaPoshtaClient('K', fn () => null))->getPoints('x') === [], 'network failure gives an empty list');
$check((new NovaPoshtaClient('K', fn () => '{"success":false,"errors":["bad"]}'))->getPoints('x') === [], 'API error gives an empty list');

if ($failures) {
    echo implode("\n", $failures), "\n";
    exit(1);
}
echo "OK\n";
