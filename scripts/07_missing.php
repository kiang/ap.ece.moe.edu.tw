<?php
$basePath = dirname(__DIR__);

$featurePath = $basePath . '/docs/data/features';
if (!file_exists($featurePath)) {
    mkdir($featurePath, 0777, true);
}
$idPool = [];
$idFile = $basePath . '/docs/data/id/id.csv';
if (file_exists($idFile)) {
    $fh = fopen($idFile, 'r');
    while ($line = fgetcsv($fh, 2048)) {
        $idPool[$line[0]] = $line[1];
    }
}

foreach (glob($basePath . '/git_preschools/*.json') as $jsonFile) {
    $json = json_decode(file_get_contents($jsonFile), true);
    foreach ($json['features'] as $f) {
        $data = $f['properties'];
        $key = $data['reg_docno'] . $data['title'];
        if (isset($idPool[$key])) {
            $f['properties']['id'] = $idPool[$key];
            $targetFile = $featurePath . '/' . $idPool[$key] . '.json';
            file_put_contents($targetFile, json_encode($f, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        }
    }
}
