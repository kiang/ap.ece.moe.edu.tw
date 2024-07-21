<?php
$basePath = dirname(__DIR__);

$fh = [];
foreach (glob($basePath . '/docs/data/slip113/*/*.json') as $jsonFile) {
    $json = json_decode(file_get_contents($jsonFile), true);
    if(empty($json['meta'])) {
        unlink($jsonFile);
        continue;
    }
    $city = $json['meta']['city'];
    $cityPath = $basePath . '/docs/data/summary1/' . $json['meta']['city'];
    if (!file_exists($cityPath)) {
        mkdir($cityPath, 0777, true);
    }
    if (!isset($fh[$city])) {
        $fh[$city] = [];
    }
    foreach ($json['slip'] as $y => $l1) {
        $yPrice = [
            'months' => 0,
            'total1' => 0,
            'total2' => 0,
            'total3' => 0,
            'monthly1' => 0,
            'monthly2' => 0,
        ];
        if (empty($y)) {
            continue;
        }
        foreach ($l1 as $period => $l2) {
            $yPrice['months'] += floatval($l2['months']);
            foreach ($l2['class'] as $class => $l3) {
                if ($class === '全日班') {
                    foreach ($l3 as $item => $l4) {
                        if (!empty($l4['小計'])) {
                            switch ($item) {
                                case '學費':
                                case '雜費':
                                case '材料費':
                                case '活動費':
                                case '午餐費':
                                case '點心費':
                                    $yPrice['total1'] += intval($l4['小計']);
                                    break;
                                case '全學期總收費':
                                    $yPrice['total3'] += intval($l4['小計']);
                                    break;
                                case '課後延托費':
                                case '家長會費':
                                    $yPrice['total2'] += intval($l4['小計']);
                                    break;
                            }
                        }
                    }
                }
            }
        }
        if(!empty($yPrice['total3'])) {
            $yPrice['monthly1'] = round($yPrice['total3'] / $yPrice['months']);
        } else {
            $yPrice['monthly1'] = round($yPrice['total1'] / $yPrice['months']);
        }        
        $yPrice['monthly2'] = round($yPrice['total2'] / $yPrice['months']);
        if (!isset($fh[$city][$y])) {
            $fh[$city][$y] = fopen($cityPath . '/' . $y . '.csv', 'w');
            fputcsv($fh[$city][$y], ['point', 'area', 'type', 'months', 'total1', 'total2', 'monthly1', 'monthly2', 'total3']);
        }
        $type = '';
        if ($json['meta']['pre_public'] !== '無') {
            $type = '準公共化';
        } else {
            $type = $json['meta']['type'];
        }
        fputcsv($fh[$city][$y], [
            $json['meta']['title'],
            $json['meta']['town'],
            $type,
            $yPrice['months'],
            $yPrice['total1'],
            $yPrice['total2'],
            $yPrice['monthly1'],
            $yPrice['monthly2'],
            $yPrice['total3'],
        ]);
    }
}
