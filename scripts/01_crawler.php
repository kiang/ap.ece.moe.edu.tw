<?php
include dirname(__DIR__) . '/vendor/autoload.php';

use Goutte\Client;
use Symfony\Component\DomCrawler\Field\InputFormField;

$client = new Client();
$client->setServerParameter('HTTP_USER_AGENT', 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:73.0) Gecko/20100101 Firefox/73.0');

$crawler = $client->request('GET', 'https://ap.ece.moe.edu.tw/webecems/pubSearch.aspx');

$dom = new \DOMDocument('1.0', 'utf-8');

$rawPath = dirname(__DIR__) . '/raw';
if (!file_exists($rawPath)) {
    mkdir($rawPath, 0777);
}
$dataPath = dirname(__DIR__) . '/docs/data';
if (!file_exists($dataPath)) {
    mkdir($dataPath, 0777);
}

$fh = [];
function page2csv($page)
{
    global $fh, $dataPath;
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
        $text = str_replace(['&nbsp;', '\\'], [' ', ''], $text);
        $lines = explode("\n", preg_replace('/[ \n\r]+/', "\n", $text));
        $data = [
            'reg_no' => '',
            'reg_docno' => '',
            'reg_date' => '',
            'title' => $lines[0],
            'owner' => '',
            'city' => '',
            'town' => '',
            'type' => '',
            'address' => '',
            'floor' => '',
            'size' => '',
            'size_in' => '',
            'size_out' => '',
            'tel' => '',
            'url' => '',
            'count_approved' => '',
            'shuttle' => '',
            'pre_public' => '',
            'is_free5' => '',
            'is_after' => '',
            'penalty' => '',
            'has_slip' => 'no',
        ];
        foreach ($lines as $k => $line) {
            switch ($line) {
                case '幼童專用車：':
                    if ('無' !== $lines[$k + 1]) {
                        $data['shuttle'] = $lines[$k + 1];
                    }
                    break;
                case '使用樓層：':
                    if ('幼童專用車：' !== $lines[$k + 1] && '無' !== $lines[$k + 1]) {
                        $data['floor'] = $lines[$k + 1];
                    }
                    break;
                case '全園總面積：':
                    if ('室內總面積：' !== $lines[$k + 1] && '無' !== $lines[$k + 1]) {
                        $data['size'] = $lines[$k + 1] . $lines[$k + 2];
                    }
                    break;
                case '室內總面積：':
                    if ('室外活動空間總面積：' !== $lines[$k + 1] && '無' !== $lines[$k + 1]) {
                        $data['size_in'] = $lines[$k + 1] . $lines[$k + 2];
                    }
                    break;
                case '室外活動空間總面積：':
                    if ('使用樓層：' !== $lines[$k + 1] && '無' !== $lines[$k + 1]) {
                        $data['size_out'] = $lines[$k + 1] . $lines[$k + 2];
                    }
                    break;
                case '負責人：':
                    if ('園所網址：' !== $lines[$k + 1] && '無' !== $lines[$k + 1]) {
                        $data['owner'] = $lines[$k + 1];
                    }
                    break;
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
                    if ('收費明細：' !== $lines[$k + 1] && '無' !== $lines[$k + 1]) {
                        $data['url'] = $lines[$k + 1];
                    }
                    break;
                case '核定人數：':
                    $data['count_approved'] = $lines[$k + 1];
                    break;
                case '準公共幼兒園：':
                    $data['pre_public'] = $lines[$k + 1];
                    break;
                case '5歲就學補助：':
                    $data['is_free5'] = $lines[$k + 1];
                    break;
                case '兼辦國小課後：':
                    $data['is_after'] = $lines[$k + 1];
                    break;
                case '設立許可文號：':
                    $data['reg_docno'] = $lines[$k + 1];
                    break;
                case '設立許可證號：':
                    $data['reg_no'] = $lines[$k + 1];
                    break;
                case '核准設立日期：':
                    $data['reg_date'] = $lines[$k + 1];
                    break;
                case '裁罰情形：':
                    if ($lines[$k + 1] !== '無') {
                        $data['penalty'] = '有';
                    } else {
                        $data['penalty'] = $lines[$k + 1];
                    }
                    break;
                case '收費明細：':
                    if ('113學年度收費明細' === $lines[$k + 1]) {
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

$form = $crawler->selectButton('搜尋')->form();
$crawler = $client->submit($form);
$pageContent = $client->getResponse()->getContent();
page2csv($pageContent);
$currentPage = 1;
$pageTotal = 2;
$pageTotalDone = false;
for ($i = 2; $i <= $pageTotal; $i++) {
    if (false === $pageTotalDone) {
        $pageTotalDone = true;
        $pos = strpos($pageContent, 'PageControl1_lblTotalPage');
        $pos = strpos($pageContent, '>', $pos) + 1;
        $posEnd = strpos($pageContent, '</span>', $pos);
        $pageTotal = intval(substr($pageContent, $pos, $posEnd - $pos));
    }
    $form = $crawler->filter('#form1')->form();
    $crawler = $client->submit($form, [
        'PageControl1$txtPages' => $currentPage,
        '__EVENTTARGET' => 'PageControl1$lbNextPage',
    ]);
    $pageContent = $client->getResponse()->getContent();
    page2csv($pageContent);
    echo "page {$i}\n";
    ++$currentPage;
}