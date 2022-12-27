<?php
$basePath = dirname(__DIR__);
$config = require $basePath . '/config.php';
$rawPath = $basePath . '/raw/geocoding';
$fc = [
    'type' => 'FeatureCollection',
    'features' => [],
];

$monthlyPool = [];
foreach (glob($basePath . '/docs/data/summary1/*/*.csv') as $csvFile) {
    $fh = fopen($csvFile, 'r');
    $head = fgetcsv($fh, 2048);
    while ($line = fgetcsv($fh, 2048)) {
        $data = array_combine($head, $line);
        if ('準公共化' != $data['type']) {
            $monthly = $data['monthly1'] + $data['monthly2'];
        } else {
            $monthly = 3500 + $data['monthly2'];
        }

        if (!isset($monthlyPool[$data['point']]) || $monthlyPool[$data['point']] > $monthly) {
            $monthlyPool[$data['point']] = $monthly;
        }
    }
}

$pool = [
    '桃園市私立華樂幼兒園' => [121.324484, 25.005288],
    '臺中市私立日光城堡幼兒園' => [120.722742, 24.246432],
    '臺南市私立萊德幼兒園' => [120.180611, 23.025655],
    '高雄市私立華威幼兒園' => [120.322247, 22.644182],
    '日月光集團聯合職工福利委員會附設高雄市私立三好幼兒園' => [120.302834, 22.710302],
    '高雄市鳳陽非營利幼兒園（委託社團法人高雄市關懷家庭教育發展協會辦理）' => [120.370153, 22.561458],
    '高雄市私立聰明幼兒園' => [120.436158, 22.719569],
    '臺南市私立伊凡卡幼兒園' => [120.226284, 22.971274],
    '高雄市私立常春藤仁武幼兒園' => [120.341834, 22.682608],
    '彰化縣明倫非營利幼兒園（委託社團法人彰化縣親職福利服務協會辦理）' => [120.585749, 23.947434],
    '彰化縣萬興非營利幼兒園（委託社團法人彰化縣劍橋社會關懷協會辦理）' => [120.415117, 23.956079],
    '彰化縣三潭非營利幼兒園（委託社團法人彰化縣愛兒教育協會辦理）' => [120.585127, 23.8653],
    '彰化縣永興非營利幼兒園（委託社團法人彰化縣中華幼兒教保職涯發展協會辦理）' => [120.566865, 23.925461],
    '南投縣私立華陽幼兒園' => [120.680948, 23.925665],
    '嘉義縣新港鄉新港國民小學附設幼兒園' => [120.344974, 23.555743],
    '嘉義縣民雄鄉東榮國民小學附設幼兒園' => [120.434864, 23.557673],
    '桃園市私立明日之星幼兒園' => [121.155128, 24.909041],
    '苗栗縣照南非營利幼兒園（委託明新學校財團法人設立之明新科技大學辦理）' => [120.879232, 24.694041],
    '苗栗縣山佳非營利幼兒園（委託社團法人新竹縣遠見多元教育發展協會辦理）' => [120.878043, 24.702041],
    '苗栗縣六合非營利幼兒園（委託明新學校財團法人設立之明新科技大學辦理）' => [120.901204, 24.68559],
    '苗栗縣后庄非營利幼兒園（委託社團法人幼兒教保協會辦理）' => [120.896318, 24.698128],
    '苗栗縣銅鑼鄉新隆國民小學附設幼兒園' => [120.809213, 24.430106],
    '嘉義縣竹崎鄉圓崇國民小學附設幼兒園' => [120.504025, 23.49023],
];
foreach (glob($basePath . '/raw/map/*.json') as $jsonFile) {
    $json = json_decode(file_get_contents($jsonFile), true);
    if (!empty($json)) {
        foreach ($json as $item) {
            $pos = strpos($item['name'], ')');
            if (false !== $pos) {
                $item['name'] = substr($item['name'], $pos + 1);
            }
            $pool[$item['name']] = [$item['lng'], $item['lat']];
        }
    }
}

$listRedo = ['臺北市私立娃娃果幼兒園', '新北市私立葳瑪藝術幼兒園', '臺中市私立光大幼兒園'];

foreach ($listRedo as $key) {
    if (isset($pool[$key])) {
        unset($pool[$key]);
    }
}

$idPool = [];
$idFile = $basePath . '/docs/data/id/id.csv';
if (file_exists($idFile)) {
    $fh = fopen($idFile, 'r');
    while ($line = fgetcsv($fh, 2048)) {
        $idPool[$line[0]] = $line[1];
    }
}
$idFh = fopen($idFile, 'a+');

foreach (glob($basePath . '/docs/data/*.csv') as $csvFile) {
    $fh = fopen($csvFile, 'r');
    $head = fgetcsv($fh, 2048);
    while ($line = fgetcsv($fh, 2048)) {
        $data = array_combine($head, $line);
        $key = $data['reg_no'] . $data['title'];
        if (isset($idPool[$key])) {
            $uuid = $idPool[$key];
        } else {
            $uuid = uuid_create();
            $idPool[$key] = $uuid;
            fputcsv($idFh, [$key, $uuid]);
        }
        $data['id'] = $uuid;
        if (isset($monthlyPool[$data['title']])) {
            $data['monthly'] = $monthlyPool[$data['title']];
        } else {
            $data['monthly'] = '';
        }
        $pointFound = false;

        if (isset($pool[$data['title']])) {
            $pointFound = true;
            $fc['features'][] = [
                'type' => 'Feature',
                'properties' => $data,
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [
                        $pool[$data['title']][0],
                        $pool[$data['title']][1],
                    ],
                ],
            ];
        } else {
            $fullAddress = $data['city'] . $data['town'] . substr($data['address'], strpos($data['address'], ']') + 1);
            $cityPath = $rawPath . '/' . $data['city'];
            if (!file_exists($cityPath)) {
                mkdir($cityPath, 0777, true);
            }
            $rawFile = $cityPath . '/' . $fullAddress . '.json';
            if (!file_exists($rawFile)) {
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
                    'oIsSupportPast' => 'true',
                    'oIsShowCodeBase' => 'true',
                ]);
                $content = file_get_contents($apiUrl);
                $pos = strpos($content, '{');
                $posEnd = strrpos($content, '}') + 1;
                $resultline = substr($content, $pos, $posEnd - $pos);
                if (strlen($resultline) > 10) {
                    file_put_contents($rawFile, substr($content, $pos, $posEnd - $pos));
                }
            }
            if (file_exists($rawFile)) {
                $json = json_decode(file_get_contents($rawFile), true);
                if (!empty($json['AddressList'][0]['X'])) {
                    $pointFound = true;
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
        if (false === $pointFound) {
            print_r($data);
        }
    }
}
file_put_contents($basePath . '/docs/preschools.json', json_encode($fc, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
