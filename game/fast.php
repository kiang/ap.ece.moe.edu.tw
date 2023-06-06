<?php
$basePath = dirname(__DIR__);
include $basePath . '/vendor/autoload.php';

use Goutte\Client;
use Symfony\Component\BrowserKit\CookieJar;

$cookieFile = __DIR__ . '/cookies.json';

if (file_exists($cookieFile)) {
    $cookies = json_decode(file_get_contents($cookieFile), true);
}
if (!empty($cookies)) {
    $cookieJar = new CookieJar();
    $cookieJar->updateFromSetCookie($cookies);
    $client = new Client(null, null, $cookieJar);
} else {
    $client = new Client();
}

$client->setServerParameter('HTTP_USER_AGENT', 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:73.0) Gecko/20100101 Firefox/73.0');

$crawler = $client->request('GET', 'https://ap.ece.moe.edu.tw/webecems/pubSearch.aspx');

$form = $crawler->selectButton('搜尋')->form();
$taskFound = false;
$countTotal = 0;
$countFailed = 0;
foreach (glob($basePath . '/docs/data/*.csv') as $csvFile) {
    $fh = fopen($csvFile, 'r');
    $head = fgetcsv($fh, 2048);
    while ($line = fgetcsv($fh, 2048)) {
        $data = array_combine($head, $line);
        if ($data['has_slip'] === 'no') {
            continue;
        }
        $rawPath = $basePath . '/raw/slip/' . $data['city'];
        if (!file_exists($rawPath)) {
            mkdir($rawPath, 0777, true);
        }
        $rawFile = $rawPath . '/' . $data['title'] . '.html';
        if (!file_exists($rawFile)) {
            ++$countTotal;
            $crawler = $client->submit($form, ['txtKeyNameS' => $data['title']]);
            $page = $client->getResponse()->getContent();

            $cookies = $client->getCookieJar()->all();
            $cookies = array_map('strval', $cookies); // Cookie::__toString
            file_put_contents($cookieFile, json_encode($cookies));

            $pos = strpos($page, '/dtl/chglist.aspx');
            if (false !== $pos) {
                $posEnd = strpos($page, '&amp;', $pos);
                $url = 'https://ap.ece.moe.edu.tw/webecems' . substr($page, $pos, $posEnd - $pos) . '&v=';

                $pos = strpos($page, 'ChgValidateCode.aspx');
                $posEnd = strpos($page, '"', $pos);
                $img = 'https://ap.ece.moe.edu.tw/webecems/' . substr($page, $pos, $posEnd - $pos);
                $client->request('GET', $img);
                file_put_contents(__DIR__ . '/qq.png', $client->getResponse()->getContent());
                $img = new Imagick(__DIR__ . '/qq.png');
                // set the image to black and white
                $img->setImageType(Imagick::IMGTYPE_GRAYSCALE);
                $img->adaptiveResizeImage(300, 300, true);
                $img->medianFilterImage(15);                
                $img->blackThresholdImage("rgb(254, 254, 254)");

                $img->writeImage(__DIR__ . '/qq.png');
                /**
                 * add /usr/share/tesseract-ocr/5/tessdata/configs/letters with the line
                 * tessedit_char_whitelist abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890
                */
                exec('/usr/bin/tesseract ' . __DIR__ . '/qq.png ' . __DIR__ . '/qq nobatch letters');
                $ans = trim(file_get_contents(__DIR__ . '/qq.txt'));
                $client->request('GET', $url . trim($ans));
                $content = $client->getResponse()->getContent();
                if (false === strpos($content, '驗證碼錯誤，請重新輸入')) {
                    $rawFile = $rawPath . '/' . $data['title'] . '.html';
                    file_put_contents($rawFile, $content);
                    echo "{$rawFile}\n";
                } else {
                    ++$countFailed;
                }
            }
        }
    }
}

echo "{$countTotal} tasks, {$countFailed} failed\n";