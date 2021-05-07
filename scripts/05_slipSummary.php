<?php
$basePath = dirname(__DIR__);

$fh = [];
foreach (glob($basePath . '/data/slip/*/*.json') as $jsonFile) {
    $json = json_decode(file_get_contents($jsonFile), true);    
    $city = $json['meta']['city'];
    $cityPath = $basePath . '/data/summary1/' . $json['meta']['city'];
    if(!file_exists($cityPath)) {
        mkdir($cityPath, 0777, true);
    }
    if(!isset($fh[$city])) {
        $fh[$city] = [];
    }
    foreach($json['slip'] AS $y => $l1) {
        $yPrice = [
            'months' => 0,
            'total' => 0,
            'monthly' => 0,
        ];
        if(empty($y)) {
            continue;
        }
        foreach($l1 AS $period => $l2) {
            $yPrice['months'] += intval($l2['months']);
            foreach($l2['class'] AS $class => $l3) {
                if($class === '全日班') {
                    foreach($l3 AS $item => $l4) {
                        if(!empty($l4['小計']) && $item !== '交通費') {
                            $yPrice['total'] += intval($l4['小計']);
                        }
                    }
                }
            }
        }
        $yPrice['monthly'] = round($yPrice['total'] / $yPrice['months']);
        if(!isset($fh[$city][$y])) {
            $fh[$city][$y] = fopen($cityPath . '/' . $y . '.csv', 'w');
            fputcsv($fh[$city][$y], ['point', 'area', 'type', 'months', 'total', 'monthly']);
        }
        $type = '';
        if($json['meta']['pre_public'] !== '無') {
            $type = '準公共化';
        } else {
            $type = $json['meta']['type'];
        }
        fputcsv($fh[$city][$y], [$json['meta']['title'], $json['meta']['town'], $type, $yPrice['months'], $yPrice['total'], $yPrice['monthly']]);
    }
}