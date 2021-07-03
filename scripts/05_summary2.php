<?php
$basePath = dirname(__DIR__);

$fh = [];
foreach (glob($basePath . '/data/slip/*/*.json') as $jsonFile) {
    $json = json_decode(file_get_contents($jsonFile), true);
    $city = $json['meta']['city'];
    $cityPath = $basePath . '/data/summary2';
    if (!file_exists($cityPath)) {
        mkdir($cityPath, 0777, true);
    }
    if (!isset($fh[$city])) {
        $fh[$city] = fopen($cityPath . '/' . $city . '.csv', 'w');
        fputcsv($fh[$city], ['鄉鎮市區', '學校', '類型', '年齡', '學期', '班別', '全學期總收費', '課後延托費']);
    }
    foreach ($json['slip'] as $y => $l1) {
        if (empty($y)) {
            continue;
        }
        foreach ($l1 as $p => $l2) {
            foreach ($l2['class'] as $class => $l3) {
                if (!empty($l3['全學期總收費']['小計'])) {
                    fputcsv($fh[$city], [
                        $json['meta']['town'],
                        $json['meta']['title'],
                        $json['meta']['type'],
                        $y,
                        $p,
                        $class,
                        isset($l3['全學期總收費']) ? $l3['全學期總收費']['小計'] : '',
                        isset($l3['課後延托費']) ? $l3['課後延托費']['小計'] : '',
                    ]);
                }
            }
        }
    }
}
