<?php
$basePath = dirname(__DIR__);
$idPool = [];
$idFile = $basePath . '/docs/data/id/id.csv';
if (file_exists($idFile)) {
    $fh = fopen($idFile, 'r');
    while ($line = fgetcsv($fh, 2048)) {
        $idPool[$line[0]] = $line[1];
    }
}

$oFh = fopen($basePath . '/docs/data/id/idn.csv', 'w');
foreach (glob($basePath . '/docs/data/*.csv') as $csvFile) {
    $fh = fopen($csvFile, 'r');
    $head = fgetcsv($fh, 2048);
    while ($line = fgetcsv($fh, 2048)) {
        $data = array_combine($head, $line);
        if (false !== strpos($data['reg_no'], '負責人')) {
            continue;
        }
        $key = $data['reg_no'] . $data['title'];
        $newKey = $data['reg_docno'] . $data['title'];
        if (isset($idPool[$key])) {
            fputcsv($oFh, [$newKey, $idPool[$key]]);
        }
    }
}
