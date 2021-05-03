<?php
$basePath = dirname(__DIR__);
$config = require $basePath . '/config.php';
$rawPath = $basePath . '/raw/geocoding';
$fc = [
    'type' => 'FeatureCollection',
    'features' => [],
];

foreach(glob($basePath . '/data/*.csv') AS $csvFile) {
    $fh = fopen($csvFile, 'r');
    $head = fgetcsv($fh, 2048);
    while($line = fgetcsv($fh, 2048)) {
        $data = array_combine($head, $line);
        $fullAddress = $data['city'] . $data['town'] . substr($data['address'], strpos($data['address'], ']') + 1);
        $cityPath = $rawPath . '/' . $data['city'];
        if(!file_exists($cityPath)) {
            mkdir($cityPath, 0777, true);
        }
        $rawFile = $cityPath . '/' . $fullAddress . '.json';
        if(!file_exists($rawFile)) {
            $apiUrl = $config['tgos']['url'] . '?' . http_build_query([
                'oAPPId' => $config['tgos']['APPID'], //應用程式識別碼(APPId)
                'oAPIKey' => $config['tgos']['APIKey'], // 應用程式介接驗證碼(APIKey)
                'oAddress' => $fullAddress, //所要查詢的門牌位置
                'oSRS' => 'EPSG:4326', //回傳的坐標系統
                'oFuzzyType' => '2', //模糊比對的代碼
                'oResultDataType' => 'JSON', //回傳的資料格式
                'oFuzzyBuffer' => '0', //模糊比對回傳門牌號的許可誤差範圍
                'oIsOnlyFullMatch' => 'false', //是否只進行完全比對
                'oIsLockCounty' => 'true', //是否鎖定縣市
                'oIsLockTown' => 'false', //是否鎖定鄉鎮市區
                'oIsLockVillage' => 'false', //是否鎖定村里
                'oIsLockRoadSection' => 'false', //是否鎖定路段
                'oIsLockLane' => 'false', //是否鎖定巷
                'oIsLockAlley' => 'false', //是否鎖定弄
                'oIsLockArea' => 'false', //是否鎖定地區
                'oIsSameNumber_SubNumber' => 'true', //號之、之號是否視為相同
                'oCanIgnoreVillage' => 'true', //找不時是否可忽略村里
                'oCanIgnoreNeighborhood' => 'true', //找不時是否可忽略鄰
                'oReturnMaxCount' => '0', //如為多筆時，限制回傳最大筆數
            ]);
            $content = file_get_contents($apiUrl);
            $pos = strpos($content, '{');
            $posEnd = strrpos($content, '}') + 1;
            file_put_contents($rawFile, substr($content, $pos, $posEnd - $pos));
        }
        $json = json_decode(file_get_contents($rawFile), true);
        if(!empty($json['AddressList'][0]['X'])) {
            $fc['features'][] = [
                'type' => 'Feature',
                'properties' => $data,
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [
                        $json['AddressList'][0]['X'],
                        $json['AddressList'][0]['Y']
                    ],
                ],
            ];
        }
    }
}
file_put_contents($basePath . '/preschools.json', json_encode($fc, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));