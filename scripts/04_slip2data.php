<?php
$basePath = dirname(__DIR__);
$fh = fopen($basePath . '/data/臺南市.csv', 'r');
$head = fgetcsv($fh, 2048);
$meta = [];
while($line = fgetcsv($fh, 2048)) {
    $data = array_combine($head, $line);
    $meta[$data['title']] = $data;
}
$counter = [];
foreach (glob($basePath . '/raw/slip/臺南市/*.html') as $htmlFile) {
    $p = pathinfo($htmlFile);
    $targetPath = str_replace('raw/slip', 'data/slip', $p['dirname']);
    if (!file_exists($targetPath)) {
        mkdir($targetPath, 0777, true);
    }
    $page = file_get_contents($htmlFile);
    $page = str_replace(['&nbsp;', ','], [' ', ''], $page);
    $parts = explode('適用年齡：', $page);
    array_shift($parts);
    foreach ($parts as $part) {
        $ageFound = false;
        $lines = explode('</tr>', $part);
        foreach ($lines as $line) {
            $cols = explode('</td>', $line);
            foreach ($cols as $k => $v) {
                $cols[$k] = trim(strip_tags($v));
            }
            if (false === $ageFound) {
                $ageFound = intval($cols[0]);
            } else {
                switch ($cols[0]) {
                    case '學費':
                        break;
                    case '雜費':
                        break;
                    case '代辦費':
                        break;
                    case '活動費':
                        break;
                    case '午餐費':
                        break;
                    case '點心費':
                        break;
                    case '全學期總收費':
                        break;
                    case '交通費':
                        break;
                    case '課後延托費':
                        if (!empty($cols[5])) {
                            $type = ($meta[$p['filename']]['pre_public'] === '無') ? '私立' : '準公共化';
                            $monthFee = round(($cols[5] + $cols[9]) / 12);
                            if(!isset($counter[$type])) {
                                $counter[$type] = [];
                            }
                            if (!isset($counter[$type][$monthFee])) {
                                $counter[$type][$monthFee] = 0;
                            }
                            ++$counter[$type][$monthFee];
                        }

                        break;
                    case '家長會費':
                        break;
                }
            }
        }
    }
}
ksort($counter['準公共化']);
ksort($counter['私立']);
print_r($counter);
