<?php
$posEnd = 0;
$dataPath = dirname(__DIR__) . '/data';
if (!file_exists($dataPath)) {
    mkdir($dataPath, 0777);
}
$fh = [];
foreach (glob(dirname(__DIR__) . '/raw/*.html') as $htmlFile) {
    $page = file_get_contents($htmlFile);
    $posEnd = 0;
    $pos = strpos($page, '<h4><span id="GridView', $posEnd);
    $finalBlock = false;
    while (false !== $pos) {
        $nextPos = strpos($page, '<h4><span id="GridView', $pos + 1);
        if (false === $nextPos) {
            $nextPos = strlen($page);
            $finalBlock = true;
        }
        $text = strip_tags(substr($page, $pos, $nextPos - $pos));
        $text = str_replace('&nbsp;', ' ', $text);
        $lines = explode("\n", preg_replace('/[ \n\r]+/', "\n", $text));
        $data = [
            'title' => $lines[0],
            'city' => '',
            'town' => '',
            'type' => '',
            'address' => '',
            'tel' => '',
            'url' => '',
            'count_approved' => '',
            'pre_public' => '',
            'is_free5' => '',
            'is_after' => '',
            'penalty' => '',
            'has_slip' => 'no',
        ];
        foreach ($lines as $k => $line) {
            switch ($line) {
                case '縣市：':
                    $data['city'] = $lines[$k + 1];
                    break;
                case '鄉鎮：':
                    $data['town'] = $lines[$k + 1];
                    break;
                case '設立別：':
                    $data['type'] = $lines[$k + 1];
                    break;
                case '地址：':
                    $data['address'] = $lines[$k + 1];
                    break;
                case '電話：':
                    $data['tel'] = $lines[$k + 1];
                    break;
                case '園所網址：':
                    $data['url'] = $lines[$k + 1];
                    if ($data['url'] === '收費明細：') {
                        $data['url'] = '';
                    }
                    break;
                case '核定人數：':
                    $data['count_approved'] = $lines[$k + 1];
                    break;
                case '準公共幼兒園：':
                    $data['pre_public'] = $lines[$k + 1];
                    break;
                case '5歲免學費：':
                    $data['is_free5'] = $lines[$k + 1];
                    break;
                case '兼辦國小課後：':
                    $data['is_after'] = $lines[$k + 1];
                    break;
                case '裁罰情形：':
                    if ($lines[$k + 1] !== '無') {
                        $data['penalty'] = '有';
                    } else {
                        $data['penalty'] = $lines[$k + 1];
                    }
                    break;
                case '收費明細：':
                    if('109學年度收費明細' === $lines[$k + 1]) {
                        $data['has_slip'] = 'yes';
                    }
                    break;
            }
        }
        if (!isset($fh[$data['city']])) {
            $fh[$data['city']] = fopen($dataPath . '/' . $data['city'] . '.csv', 'w');
            fputcsv($fh[$data['city']], array_keys($data));
        }
        fputcsv($fh[$data['city']], $data);
        if (false === $finalBlock) {
            $pos = $nextPos;
        } else {
            $pos = false;
        }
    }
}
