<?php
$basePath = dirname(__DIR__);
$cityPath = $basePath . '/data/summary3';

$fh = [];
foreach (glob($basePath . '/data/slip/*/*.json') as $jsonFile) {
    $jsonFile109 = str_replace('/slip/', '/slip109/', $jsonFile);
    if(file_exists($jsonFile109)) {
        $json = json_decode(file_get_contents($jsonFile), true);
        $json109 = json_decode(file_get_contents($jsonFile109), true);
        $city = $json['meta']['city'];
        $pool = [];
        if (!isset($fh[$city])) {
            $fh[$city] = fopen($cityPath . '/' . $city . '.csv', 'w');
            fputcsv($fh[$city], ['鄉鎮市區', '學校', '類型', '年齡', '學期', '班別', '109全學期總收費', '110全學期總收費']);
        }

        foreach ($json['slip'] as $y => $l1) {
            if (empty($y)) {
                continue;
            }
            foreach ($l1 as $p => $l2) {
                foreach ($l2['class'] as $class => $l3) {
                    if (!empty($l3['全學期總收費']['小計'])) {
                        if(!isset($pool[$y])) {
                            $pool[$y] = [];
                        }
                        if(!isset($pool[$y][$p])) {
                            $pool[$y][$p] = [];
                        }
                        if(!isset($pool[$y][$p][$class])) {
                            $pool[$y][$p][$class] = [];
                        }
                        $pool[$y][$p][$class][110] = $l3['全學期總收費']['小計'];
                    }
                }
            }
        }
        foreach ($json109['slip'] as $y => $l1) {
            if (empty($y)) {
                continue;
            }
            foreach ($l1 as $p => $l2) {
                foreach ($l2['class'] as $class => $l3) {
                    if (!empty($l3['全學期總收費']['小計'])) {
                        if(isset($pool[$y][$p][$class][110]) && $pool[$y][$p][$class][110] != $l3['全學期總收費']['小計']) {
                            fputcsv($fh[$city], [
                                $json['meta']['town'],
                                $json['meta']['title'],
                                $json['meta']['type'],
                                $y,
                                $p,
                                $class,
                                $l3['全學期總收費']['小計'],
                                $pool[$y][$p][$class][110],
                            ]);
                        }
                    }
                }
            }
        }
    }
}