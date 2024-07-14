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

$oFh = fopen($basePath . '/docs/data/id/id.csv', 'w');
foreach ($idPool as $k => $v) {
    fputcsv($oFh, [$k, $v]);
}
