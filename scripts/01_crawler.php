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

$form = $crawler->selectButton('搜尋')->form();
$crawler = $client->submit($form);
$pageContent = $client->getResponse()->getContent();
file_put_contents($rawPath . '/1.html', $pageContent);
$currentPage = 1;
$pageTotal = 2;
$pageTotalDone = false;
for ($i = 2; $i <= $pageTotal; $i++) {
    if(false === $pageTotalDone) {
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
    file_put_contents($rawPath . '/' . $i . '.html', $pageContent);
    echo "page {$i}\n";
    ++$currentPage;
}
