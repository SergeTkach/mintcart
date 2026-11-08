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

    if ($request['calledMethod'] === 'getAreas') {
        return json_encode(['success' => true, 'data' => [
            ['Ref' => 'a1', 'Description' => 'Київська', 'DescriptionRu' => 'Киевская'],
            ['Ref' => '', 'Description' => 'broken'],
        ]]);
    }

    if ($request['calledMethod'] === 'getCities') {
        return json_encode(['success' => true, 'data' => [
            ['Ref' => 'c1', 'Description' => 'Київ', 'DescriptionRu' => 'Киев', 'Area' => 'a1'],
            ['Ref' => 'c2', 'Description' => '', 'Area' => 'a1'],
        ]]);
    }

    return json_encode(['success' => true, 'data' => [
        ['Ref' => 'w1', 'CityRef' => 'c1', 'Number' => '1', 'Description' => 'Відділення №1', 'TypeOfWarehouse' => 'branch'],
        ['Ref' => 'w2', 'CityRef' => 'c1', 'Number' => '2', 'Description' => 'Поштомат №2', 'TypeOfWarehouse' => NovaPoshtaClient::POSTOMAT_TYPE],
        ['Ref' => '', 'CityRef' => 'c1', 'Description' => 'broken'],
    ]]);
};

$client = new NovaPoshtaClient('KEY', $transport);

$areas = $client->getAreas();
$check($areas === ['a1' => ['name' => 'Київська', 'name_ru' => 'Киевская']], 'areas parsed by ref, broken rows skipped');
$check($sent[0]['apiKey'] === 'KEY' && $sent[0]['methodProperties'] === [], 'areas request carries the key and no properties');

$cities = $client->getCitiesPage(3);
$check(count($cities['rows']) === 1 && $cities['rows'][0]['city_ref'] === 'c1' && $cities['rows'][0]['area_ref'] === 'a1', 'cities parsed, rows without a name skipped');
$check($cities['raw'] === 2, 'raw count includes skipped rows (it decides the last page)');
$check($sent[1]['methodProperties']['Page'] === '3' && $sent[1]['methodProperties']['Limit'] === '500', 'cities asked page by page');

$points = $client->getWarehousesPage(1);
$check(count($points['rows']) === 2 && $points['rows'][0]['postomat'] === false && $points['rows'][1]['postomat'] === true, 'points parsed, locker flagged, broken rows skipped');
$check($points['rows'][1]['number'] === 2 && $points['rows'][0]['city_ref'] === 'c1', 'point number and city ref kept');

$calls = count($sent);
$check((new NovaPoshtaClient('', $transport))->getCitiesPage(1) === ['rows' => [], 'raw' => 0] && count($sent) === $calls, 'no API key makes no request');
$check((new NovaPoshtaClient('K', fn () => null))->getWarehousesPage(1)['raw'] === 0, 'network failure gives an empty page');
$check((new NovaPoshtaClient('K', fn () => '{"success":false,"errors":["bad"]}'))->getAreas() === [], 'API error gives an empty list');

if ($failures) {
    echo implode("\n", $failures), "\n";
    exit(1);
}

echo "OK novaposhta-client-test\n";
