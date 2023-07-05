<?php
$basePath = dirname(__DIR__);
include $basePath . '/vendor/autoload.php';

use Goutte\Client;

$client = new Client();
$client->setServerParameter('HTTP_USER_AGENT', 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:73.0) Gecko/20100101 Firefox/73.0');
$cities = [
    '02' => '臺北市',
    '01' => '基隆市',
    '03' => '新北市',
    '05' => '桃園市',
    '06' => '新竹市',
    '07' => '新竹縣',
    '08' => '苗栗縣',
    '09' => '臺中市',
    '11' => '彰化縣',
    '12' => '南投縣',
    '13' => '雲林縣',
    '14' => '嘉義市',
    '15' => '嘉義縣',
    '16' => '臺南市',
    '18' => '高雄市',
    '20' => '屏東縣',
    '21' => '臺東縣',
    '22' => '花蓮縣',
    '04' => '宜蘭縣',
    '23' => '澎湖縣',
    '24' => '金門縣',
    '25' => '連江縣',
];

$crawler = $client->request('GET', 'https://ap.ece.moe.edu.tw/webecems/punishSearch.aspx');
$pageLimit = 4;

foreach ($cities as $code => $city) {
    $rawPath = $basePath . '/raw/punish/' . $city;
    if (!file_exists($rawPath)) {
        mkdir($rawPath, 0777, true);
    }
    $dataPath = $basePath . '/docs/data/punish/' . $city;
    if (!file_exists($dataPath)) {
        mkdir($dataPath, 0777, true);
    }
    $form = $crawler->selectButton('搜尋')->form();
    $crawler = $client->submit($form, ['ddlCityS' => $code]);
    $pageContent = $client->getResponse()->getContent();
    file_put_contents($rawPath . '/1.html', $pageContent);

    $pos = strpos($pageContent, '<div class="kdCard-txt">');
    while (false !== $pos) {
        $nextPos = strpos($pageContent, '</td>', $pos + 1);
        $block = substr($pageContent, $pos, $nextPos - $pos);
        $urlPos = strpos($block, '/dtl/punish_view.aspx');
        $urlPosEnd = strpos($block, '&#39;', $urlPos);
        $url = 'https://ap.ece.moe.edu.tw/webecems' . substr($block, $urlPos, $urlPosEnd - $urlPos);

        $text = strip_tags($block);
        $text = str_replace('&nbsp;', ' ', $text);
        $blockLines = explode("\n", preg_replace('/[ \n\r]+/', "\n", $text));

        $client->request('GET', $url);
        $page = $client->getResponse()->getContent();
        $page = str_replace('&nbsp;', '', $page);
        $pos = strpos($page, '<div id="mainPanel">');
        $posEnd = strpos($page, '<input type="submit" name="btnExit"', $pos);
        $lines = explode('</tr>', substr($page, $pos, $posEnd - $pos));
        $theFile = $dataPath . '/' . $blockLines[1] . '.json';
        $keyPool = [];
        if (file_exists($theFile)) {
            $punishments = json_decode(file_get_contents($theFile), true);
            foreach ($punishments as $punishment) {
                $keyPool[$punishment[1]] = true;
            }
        } else {
            $punishments = [];
        }
        foreach ($lines as $line) {
            $cols = explode('</td>', $line);
            if (count($cols) === 7) {
                foreach ($cols as $k => $v) {
                    $cols[$k] = trim(strip_tags($v));
                }
                array_pop($cols);
                if (!isset($keyPool[$cols[1]])) {
                    $punishments[] = $cols;
                    $keyPool[$cols[1]] = true;
                }
            }
        }
        file_put_contents($theFile, json_encode($punishments, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $pos = strpos($pageContent, '<div class="kdCard-txt">', $nextPos);
    }

    $currentPage = 1;
    while (false !== strpos($pageContent, 'PageControl1$lbNextPage') && $currentPage < $pageLimit) {
        ++$currentPage;
        $form = $crawler->filter('#form1')->form();
        $crawler = $client->submit($form, [
            '__EVENTTARGET' => 'PageControl1$lbNextPage',
        ]);
        $pageContent = $client->getResponse()->getContent();
        file_put_contents($rawPath . '/' . $currentPage . '.html', $pageContent);

        $pos = strpos($pageContent, '<div class="kdCard-txt">');
        while (false !== $pos) {
            $nextPos = strpos($pageContent, '</td>', $pos + 1);
            $block = substr($pageContent, $pos, $nextPos - $pos);
            $urlPos = strpos($block, '/dtl/punish_view.aspx');
            $urlPosEnd = strpos($block, '&#39;', $urlPos);
            $url = 'https://ap.ece.moe.edu.tw/webecems' . substr($block, $urlPos, $urlPosEnd - $urlPos);

            $text = strip_tags($block);
            $text = str_replace('&nbsp;', ' ', $text);
            $blockLines = explode("\n", preg_replace('/[ \n\r]+/', "\n", $text));

            $client->request('GET', $url);
            $page = $client->getResponse()->getContent();
            $page = str_replace('&nbsp;', '', $page);
            $pos = strpos($page, '<div id="mainPanel">');
            $posEnd = strpos($page, '<input type="submit" name="btnExit"', $pos);
            $lines = explode('</tr>', substr($page, $pos, $posEnd - $pos));
            $theFile = $dataPath . '/' . $blockLines[1] . '.json';
            $keyPool = [];
            if (file_exists($theFile)) {
                $punishments = json_decode(file_get_contents($theFile), true);
                foreach ($punishments as $punishment) {
                    $keyPool[$punishment[1]] = true;
                }
            } else {
                $punishments = [];
            }
            foreach ($lines as $line) {
                $cols = explode('</td>', $line);
                if (count($cols) === 7) {
                    foreach ($cols as $k => $v) {
                        $cols[$k] = trim(strip_tags($v));
                    }
                    array_pop($cols);
                    if (!isset($keyPool[$cols[1]])) {
                        $punishments[] = $cols;
                        $keyPool[$cols[1]] = true;
                    }
                }
            }
            file_put_contents($theFile, json_encode($punishments, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            $pos = strpos($pageContent, '<div class="kdCard-txt">', $nextPos);
        }
    }
}