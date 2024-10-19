<?php

/**
 * open data from https://data.gov.tw/dataset/96649
 */
$basePath = dirname(__DIR__);
include $basePath . '/vendor/autoload.php';

use Goutte\Client;

$client = new Client();
$client->setServerParameter('HTTP_USER_AGENT', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36');
$client->request('GET', 'https://www.thb.gov.tw/Common/ThbOpenDataService.ashx?SN=589&format=1');
$csvFile = $basePath . '/raw/kids_vehicles.csv';
file_put_contents($csvFile, $client->getResponse()->getContent());
exit();

$areas = json_decode(file_get_contents(__DIR__ . '/areas.json'), true);
$codes = $pool = [];
foreach ($areas as $city => $data1) {
    foreach ($data1 as $town => $code) {
        $codes[$city . $town] = $code;
    }
}

$fc = json_decode(file_get_contents($basePath . '/docs/preschools.json'), true);
foreach ($fc['features'] as $f) {
    if (isset($codes[$f['properties']['city'] . $f['properties']['town']])) {
        $code = $codes[$f['properties']['city'] . $f['properties']['town']];
    } else {
        $parts = preg_split('/[^0-9]+/', $f['properties']['address']);
        $code = $parts[1];
    }

    if (!isset($pool[$code])) {
        $pool[$code] = [];
    }
    $pool[$code][$f['properties']['title']] = $f['properties']['id'];
}

$fh = fopen($csvFile, 'r');
$head = fgetcsv($fh, 2048);
$head[0] = 'plate_no';
$result = [];
while ($line = fgetcsv($fh, 2048)) {
    $data = array_combine($head, $line);
    if (isset($pool[$data['owner_zip']][$data['owner_name']])) {
        $id = $pool[$data['owner_zip']][$data['owner_name']];
        if (!isset($result[$id])) {
            $result[$id] = [];
        }
        unset($data['owner_zip']);
        unset($data['owner_name']);
        $result[$id][] = $data;
    }
}

file_put_contents($basePath . '/docs/kids_vehicles.json', json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
