<?php
$basePath = dirname(__DIR__);

$count = [
    'keep' => 0,
    'plus' => 0,
    'minus' => 0,
];
foreach (glob($basePath . '/data/slip/*/*.json') as $jsonFile) {
    $jsonFile109 = str_replace('/slip/', '/slip109/', $jsonFile);
    if(file_exists($jsonFile109)) {
        $json = json_decode(file_get_contents($jsonFile), true);
        $json109 = json_decode(file_get_contents($jsonFile109), true);
        $pool = [];
        $sumDiff = 0;

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
                        if(!isset($pool[$y])) {
                            $pool[$y] = [];
                        }
                        if(!isset($pool[$y][$p])) {
                            $pool[$y][$p] = [];
                        }
                        if(!isset($pool[$y][$p][$class])) {
                            $pool[$y][$p][$class] = [];
                        }
                        $pool[$y][$p][$class][109] = $l3['全學期總收費']['小計'];
                        if(isset($pool[$y][$p][$class][110])) {
                            $sumDiff += $pool[$y][$p][$class][110] - $pool[$y][$p][$class][109];
                        }
                    }
                }
            }
        }
        if($sumDiff == 0) {
            $count['keep'] += 1;
        } elseif($sumDiff > 0) {
            $count['plus'] += 1;
        } else {
            $count['minus'] += 1;
        }
    }
}

print_r($count);