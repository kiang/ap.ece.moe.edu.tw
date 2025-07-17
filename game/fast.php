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

if (!file_exists(__DIR__ . '/base')) {
    mkdir(__DIR__ . '/base', 0777, true);
}
if (!file_exists(__DIR__ . '/failed')) {
    mkdir(__DIR__ . '/failed', 0777, true);
}

if (!file_exists(__DIR__ . '/qq.txt')) {
    file_put_contents(__DIR__ . '/qq.txt', '');
}

$form = $crawler->selectButton('搜尋')->form();
$taskFound = false;
$domDocument = new \DOMDocument;
foreach (glob($basePath . '/docs/data/*.csv') as $csvFile) {
    $fh = fopen($csvFile, 'r');
    $head = fgetcsv($fh, 2048);
    while ($line = fgetcsv($fh, 2048)) {
        $data = array_combine($head, $line);
        if ($data['has_slip'] === 'no') {
            continue;
        }
        $rawPath = $basePath . '/raw/slip114/' . $data['city'];
        if (!file_exists($rawPath)) {
            mkdir($rawPath, 0777, true);
        }
        $rawFile = $rawPath . '/' . $data['title'] . '.html';
        if (!file_exists($rawFile)) {
            $crawler = $client->submit($form, ['txtKeyNameS' => $data['title']]);
            $subForm = $crawler->selectButton('搜尋')->form();


            // $domInput = $domDocument->createElement('input');
            // $domInput->setAttribute('name', 'PageControl1$txtPages');
            // $domInput->setAttribute('value', '1');
            // $formInput = new \Symfony\Component\DomCrawler\Field\InputFormField($domInput);
            // $subForm->set($formInput);

            $subForm->remove('btnSearch');

            $crawler = $client->submit($subForm, [
                'txtKeyNameS' => '',
                '__EVENTTARGET' => 'GridView1$ctl02$lbChgList',
            ]);
            $page = $client->getResponse()->getContent();

            $cookies = $client->getCookieJar()->all();
            $cookies = array_map('strval', $cookies); // Cookie::__toString
            file_put_contents($cookieFile, json_encode($cookies));

            $pos = strpos($page, 'ChgValidateCode.aspx');
            if (false !== $pos) {
                $borderWidth = 20;
                $borderColor = 'rgba(255, 255, 255, 1)';
                $posEnd = strpos($page, '"', $pos);
                $imgUrl = 'https://ap.ece.moe.edu.tw/webecems/' . substr($page, $pos, $posEnd - $pos);
                $ansDone = false;
                while (false === $ansDone) {
                    $origImgFile = __DIR__ . '/qq_orig.png';
                    $imgFile = __DIR__ . '/qq.png';
                    $client->request('GET', $imgUrl);
                    file_put_contents(__DIR__ . '/qq_orig.png', $client->getResponse()->getContent());
                    exec("/usr/bin/convert {$origImgFile} \( +clone -threshold 70% -negate -type bilevel -define connected-components:area-threshold=5 -define connected-components:mean-color=true -connected-components 1 \) -alpha off -compose copy_opacity -composite -compose over -background white -flatten {$imgFile}");

                    /**
                     * add /usr/share/tesseract-ocr/5/tessdata/configs/letters with the line (apt installed version)
                     * add ~/snap/tesseract/common/configs/letters with the line (snap installed version)
                     * 
                     * tessedit_char_whitelist abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890
                     */
                    exec('/usr/bin/tesseract ' . __DIR__ . '/qq.png ' . __DIR__ . '/qq --psm 8 -c load_system_dawg=0 -c load_freq_dawg=0 letters');
                    $ans = file_get_contents(__DIR__ . '/qq.txt');
                    $ans = preg_replace('/[^0-9a-z]+/i', '', $ans);
                    if (strlen($ans) === 5) {
                        $subForm = $crawler->selectButton('搜尋')->form();

                        $domInput = $domDocument->createElement('input');
                        $domInput->setAttribute('name', 'ScriptManager1');
                        $domInput->setAttribute('value', 'UpdatePanel1|btnNext');
                        $formInput = new \Symfony\Component\DomCrawler\Field\InputFormField($domInput);
                        $subForm->set($formInput);

                        $domInput = $domDocument->createElement('input');
                        $domInput->setAttribute('name', 'txtVerify');
                        $domInput->setAttribute('value', $ans);
                        $formInput = new \Symfony\Component\DomCrawler\Field\InputFormField($domInput);
                        $subForm->set($formInput);

                        $domInput = $domDocument->createElement('input');
                        $domInput->setAttribute('name', 'btnNext');
                        $domInput->setAttribute('value', 'Next');
                        $formInput = new \Symfony\Component\DomCrawler\Field\InputFormField($domInput);
                        $subForm->set($formInput);

                        $subForm->remove('btnSearch');

                        $crawler = $client->submit($subForm, [
                            'txtKeyNameS' => '',
                            '__EVENTTARGET' => '',
                        ]);

                        $content = $client->getResponse()->getContent();
                        if (false === strpos($content, '驗證碼錯誤，請重新輸入')) {
                            $ansDone = true;
                            copy(__DIR__ . '/qq_orig.png', __DIR__ . '/base/' . $ans . '.png');
                            if (false === strpos($content, '幼生系統收費登載取得錯誤')) {
                                $rawFile = $rawPath . '/' . $data['title'] . '.html';
                                file_put_contents($rawFile, $content);
                                echo "{$rawFile}\n";
                            } else {
                                echo "{$data['title']} 幼生系統收費登載取得錯誤\n";
                            }
                        } else {
                            copy(__DIR__ . '/qq_orig.png', __DIR__ . '/failed/' . $ans . '.png');
                        }
                    }
                }
            }
        }
    }
}
