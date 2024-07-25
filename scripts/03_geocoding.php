<?php
$basePath = dirname(__DIR__);
$rawPath = $basePath . '/raw/geocoding';

$monthlyPool = [];
foreach (glob($basePath . '/docs/data/summary1/*/*.csv') as $csvFile) {
    $fh = fopen($csvFile, 'r');
    $head = fgetcsv($fh, 2048);
    while ($line = fgetcsv($fh, 2048)) {
        $data = array_combine($head, $line);
        if ('準公共化' != $data['type']) {
            $monthly = $data['monthly1'] + $data['monthly2'];
        } else {
            $monthly = 3000 + $data['monthly2'];
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
    '南投縣私立光隆幼兒園' => [120.682517, 23.995032]
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
        if (false !== strpos($data['reg_no'], '負責人')) {
            continue;
        }
        $key = $data['reg_docno'] . $data['title'];
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
            $data['is_active'] = 1;
            $f = [
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
                $command = <<<EOD
curl 'https://api.nlsc.gov.tw/MapSearch/ContentSearch?word=___KEYWORD___&mode=AutoComplete&count=1&feedback=XML' \
   -H 'Accept: application/xml, text/xml, */*; q=0.01' \
   -H 'Accept-Language: zh-TW,zh;q=0.9,en-US;q=0.8,en;q=0.7' \
   -H 'Connection: keep-alive' \
   -H 'Origin: https://maps.nlsc.gov.tw' \
   -H 'Referer: https://maps.nlsc.gov.tw/' \
   -H 'Sec-Fetch-Dest: empty' \
   -H 'Sec-Fetch-Mode: cors' \
   -H 'Sec-Fetch-Site: same-site' \
   -H 'User-Agent: Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36' \
   -H 'sec-ch-ua: "Google Chrome";v="123", "Not:A-Brand";v="8", "Chromium";v="123"' \
   -H 'sec-ch-ua-mobile: ?0' \
   -H 'sec-ch-ua-platform: "Linux"'
EOD;
                $result = shell_exec(strtr($command, [
                    '___KEYWORD___' => urlencode($fullAddress),
                ]));
                $cleanKeyword = trim(strip_tags($result));
                if (!empty($cleanKeyword)) {
                    $command = <<<EOD
                    curl 'https://api.nlsc.gov.tw/MapSearch/QuerySearch' \
                      -H 'Accept: application/xml, text/xml, */*; q=0.01' \
                      -H 'Accept-Language: zh-TW,zh;q=0.9,en-US;q=0.8,en;q=0.7' \
                      -H 'Connection: keep-alive' \
                      -H 'Content-Type: application/x-www-form-urlencoded; charset=UTF-8' \
                      -H 'Origin: https://maps.nlsc.gov.tw' \
                      -H 'Referer: https://maps.nlsc.gov.tw/' \
                      -H 'Sec-Fetch-Dest: empty' \
                      -H 'Sec-Fetch-Mode: cors' \
                      -H 'Sec-Fetch-Site: same-site' \
                      -H 'User-Agent: Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36' \
                      -H 'sec-ch-ua: "Google Chrome";v="123", "Not:A-Brand";v="8", "Chromium";v="123"' \
                      -H 'sec-ch-ua-mobile: ?0' \
                      -H 'sec-ch-ua-platform: "Linux"' \
                      --data-raw 'word=___KEYWORD___&feedback=XML&center=120.218280%2C23.007292'
                    EOD;
                    $result = shell_exec(strtr($command, [
                        '___KEYWORD___' => urlencode(urlencode($cleanKeyword)),
                    ]));
                    $json = json_decode(json_encode(simplexml_load_string($result)), true);
                    if (!empty($json['ITEM']['LOCATION'])) {
                        $parts = explode(',', $json['ITEM']['LOCATION']);
                        if (count($parts) === 2) {
                            file_put_contents($rawFile, json_encode([
                                'AddressList' => [
                                    [
                                        'X' => $parts[0],
                                        'Y' => $parts[1],
                                    ],
                                ],
                            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                        }
                    }
                }
            }
            if (file_exists($rawFile)) {
                $json = json_decode(file_get_contents($rawFile), true);
                if (!empty($json['AddressList'][0]['X'])) {
                    $data['is_active'] = 1;
                    $pointFound = true;
                    $f = [
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
        if ($pointFound) {
            file_put_contents($basePath . '/docs/data/features/' . $data['id'] . '.json', json_encode($f, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        }
    }
}
$fc = [
    'type' => 'FeatureCollection',
    'features' => [],
];
foreach (glob($basePath . '/docs/data/features/*.json') as $jsonFile) {
    $json = json_decode(file_get_contents($jsonFile), true);
    if (empty($json['properties']['is_active'])) {
        $json['properties']['is_active'] = 0;
    }
    $fc['features'][$json['properties']['id']] = $json;
}
ksort($fc['features']);
$fc['features'] = array_values($fc['features']);
file_put_contents($basePath . '/docs/preschools.json', json_encode($fc, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
