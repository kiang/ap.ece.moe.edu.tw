<?php
$basePath = dirname(__DIR__);
$count = [
    'monthly1' => [],
    'monthly2' => [],
];
foreach (glob($basePath . '/docs/data/summary1/*/*.csv') as $csvFile) {
    $fh = fopen($csvFile, 'r');
    $head = fgetcsv($fh, 2048);
    while($line = fgetcsv($fh, 2048)) {
        $data = array_combine($head, $line);
        if($data['type'] === '準公共化') {
            if(!isset($count['monthly1'][$data['monthly1']])) {
                $count['monthly1'][$data['monthly1']] = [];
            }
            $count['monthly1'][$data['monthly1']][$data['point']] = true;
            
            if(!isset($count['monthly2'][$data['monthly2']])) {
                $count['monthly2'][$data['monthly2']] = [];
            }
            $count['monthly2'][$data['monthly2']][$data['point']] = true;
            
        }
    }
}
ksort($count['monthly1']);
ksort($count['monthly2']);
$schoolCounter = 0;
foreach($count['monthly2'] AS $fee => $schools) {
    if($fee > 4500) {
        echo $fee;
        print_r($schools);
        $schoolCounter += count($schools);
    }
}
echo $schoolCounter;