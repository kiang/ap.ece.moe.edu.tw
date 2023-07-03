<?php
$basePath = dirname(__DIR__);
$meta = [];
foreach (glob($basePath . '/docs/data/*.csv') as $csvFile) {
    $p = pathinfo($csvFile);
    if (!isset($meta[$p['filename']])) {
        $meta[$p['filename']] = [];
    }
    $fh = fopen($csvFile, 'r');
    $head = fgetcsv($fh, 2048);
    while ($line = fgetcsv($fh, 2048)) {
        $data = array_combine($head, $line);
        $meta[$data['city']][$data['title']] = $data;
    }
}

foreach (glob($basePath . '/raw/slip112/*/*.html') as $htmlFile) {
    $p = pathinfo($htmlFile);
    $city = pathinfo($p['dirname'])['filename'];
    if(!isset($meta[$city][$p['filename']])) {
        continue;
    }
    $targetPath = str_replace('raw/slip112', 'docs/data/slip112', $p['dirname']);
    if (!file_exists($targetPath)) {
        mkdir($targetPath, 0777, true);
    }
    $pointMeta = $meta[$city][$p['filename']];
    $page = file_get_contents($htmlFile);
    $page = str_replace(['&nbsp;', ','], [' ', ''], $page);
    $parts = explode('適用年齡：', $page);
    array_shift($parts);
    $pageData = [
        'meta' => $pointMeta,
        'slip' => [],
    ];
    foreach ($parts as $part) {
        $ageFound = $periodFound = false;
        $lines = explode('</tr>', $part);
        foreach ($lines as $line) {
            $cols = explode('</td>', $line);
            foreach ($cols as $k => $v) {
                $cols[$k] = trim(strip_tags($v));
            }

            if (false === $ageFound) {
                $ageFound = intval($cols[0]);
                if($ageFound < 2) {
                    continue(2);
                }
                $pageData['slip'][$ageFound] = [];
            } elseif (false === $periodFound) {
                $periodFound = true;
                $period1 = preg_split('/[\s]+/', $cols[1]);
                $period2 = preg_split('/[\s]+/', $cols[2]);
                $pageData['slip'][$ageFound]['上學期'] = [
                    'months' => $period1[1],
                    'class' => [
                        '半日班' => [],
                        '全日班' => [],
                    ],
                ];
                $pageData['slip'][$ageFound]['下學期'] = [
                    'months' => $period2[1],
                    'class' => [
                        '半日班' => [],
                        '全日班' => [],
                    ],
                ];
            } else {
                switch ($cols[0]) {
                    case '學費':
                    case '雜費':
                    case '活動費':
                    case '午餐費':
                    case '點心費':
                    case '交通費':
                    case '課後延托費':
                    case '家長會費':
                        $pageData['slip'][$ageFound]['上學期']['class']['半日班'][$cols[0]] = [
                            '收費期間' => $cols[1],
                            '單價' => $cols[2],
                            '小計' => $cols[3]
                        ];
                        $pageData['slip'][$ageFound]['上學期']['class']['全日班'][$cols[0]] = [
                            '收費期間' => $cols[1],
                            '單價' => $cols[4],
                            '小計' => $cols[5]
                        ];
                        $pageData['slip'][$ageFound]['下學期']['class']['半日班'][$cols[0]] = [
                            '收費期間' => $cols[1],
                            '單價' => $cols[6],
                            '小計' => $cols[7]
                        ];
                        $pageData['slip'][$ageFound]['下學期']['class']['全日班'][$cols[0]] = [
                            '收費期間' => $cols[1],
                            '單價' => $cols[8],
                            '小計' => $cols[9]
                        ];
                        break;
                    case '全學期總收費':
                        if(isset($cols[4])) {
                            $pageData['slip'][$ageFound]['上學期']['class']['半日班'][$cols[0]] = [
                                '收費期間' => '學期',
                                '單價' => $cols[2],
                                '小計' => $cols[2]
                            ];
                            $pageData['slip'][$ageFound]['上學期']['class']['全日班'][$cols[0]] = [
                                '收費期間' => '學期',
                                '單價' => $cols[3],
                                '小計' => $cols[3]
                            ];
                            $pageData['slip'][$ageFound]['下學期']['class']['半日班'][$cols[0]] = [
                                '收費期間' => '學期',
                                '單價' => $cols[4],
                                '小計' => $cols[4]
                            ];
                            $pageData['slip'][$ageFound]['下學期']['class']['全日班'][$cols[0]] = [
                                '收費期間' => '學期',
                                '單價' => $cols[5],
                                '小計' => $cols[5]
                            ];
                        } else {
                            $pageData['slip'][$ageFound]['上學期']['class']['全日班'][$cols[0]] = [
                                '收費期間' => '學期',
                                '單價' => $cols[1],
                                '小計' => $cols[1]
                            ];
                            $pageData['slip'][$ageFound]['下學期']['class']['全日班'][$cols[0]] = [
                                '收費期間' => '學期',
                                '單價' => $cols[2],
                                '小計' => $cols[2]
                            ];
                        }
                        break;
                    case '代辦費':
                        $pageData['slip'][$ageFound]['上學期']['class']['半日班']['材料費'] = [
                            '收費期間' => $cols[2],
                            '單價' => $cols[3],
                            '小計' => $cols[4]
                        ];
                        $pageData['slip'][$ageFound]['上學期']['class']['全日班']['材料費'] = [
                            '收費期間' => $cols[2],
                            '單價' => $cols[5],
                            '小計' => $cols[6]
                        ];
                        $pageData['slip'][$ageFound]['下學期']['class']['半日班']['材料費'] = [
                            '收費期間' => $cols[2],
                            '單價' => $cols[7],
                            '小計' => $cols[8]
                        ];
                        $pageData['slip'][$ageFound]['下學期']['class']['全日班']['材料費'] = [
                            '收費期間' => $cols[2],
                            '單價' => $cols[9],
                            '小計' => $cols[10]
                        ];
                        break;
                }
            }
        }
    }
    file_put_contents($targetPath . '/' . $p['filename'] . '.json', json_encode($pageData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}