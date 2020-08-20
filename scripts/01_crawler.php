<?php
include dirname(__DIR__) . '/vendor/autoload.php';
use Goutte\Client;
use Symfony\Component\DomCrawler\Field\InputFormField;

$cities = array(
    '01' => '基隆市',
    '02' => '臺北市',
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
);

$client = new Client();
$client->setServerParameter('HTTP_USER_AGENT', 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:73.0) Gecko/20100101 Firefox/73.0');

$crawler = $client->request('GET', 'https://ap.ece.moe.edu.tw/webecems/punishSearch.aspx');

$dom = new \DOMDocument('1.0', 'utf-8');

$rawPath = dirname(__DIR__) . '/raw';
if(!file_exists($rawPath)) {
    mkdir($rawPath, 0777);
}

foreach($cities AS $code => $city) {
    $form = $crawler->selectButton('搜尋')->form();
    $crawler = $client->submit($form, array('ddlCityS' => $code));
    file_put_contents($rawPath . '/' . $city . '-1.html', $client->getResponse()->getContent());

    // $form = $crawler->selectButton('搜尋')->form();
    // $crawler = $client->submit($form, array('ddlCityS' => $code, '__EVENTTARGET' => 'PageControl1$lbNextPage'));
    // file_put_contents(__DIR__ . '/tmp2.html', $client->getResponse()->getContent());
}