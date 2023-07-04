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
foreach (glob($basePath . '/docs/data/*.csv') as $csvFile) {
    $fh = fopen($csvFile, 'r');
    $head = fgetcsv($fh, 2048);
    while ($line = fgetcsv($fh, 2048)) {
        $data = array_combine($head, $line);
        if ($data['has_slip'] === 'no') {
            continue;
        }
        $rawPath = $basePath . '/raw/slip112/' . $data['city'];
        if (!file_exists($rawPath)) {
            mkdir($rawPath, 0777, true);
        }
        $rawFile = $rawPath . '/' . $data['title'] . '.html';
        if (!file_exists($rawFile)) {
            $crawler = $client->submit($form, ['txtKeyNameS' => $data['title']]);
            $page = $client->getResponse()->getContent();

            $cookies = $client->getCookieJar()->all();
            $cookies = array_map('strval', $cookies); // Cookie::__toString
            file_put_contents($cookieFile, json_encode($cookies));

            $pos = strpos($page, '/dtl/chglist.aspx');
            if (false !== $pos) {
                $borderWidth = 20;
                $borderColor = 'rgba(255, 255, 255, 1)';
                $posEnd = strpos($page, '&amp;', $pos);
                $url = 'https://ap.ece.moe.edu.tw/webecems' . substr($page, $pos, $posEnd - $pos) . '&v=';

                $pos = strpos($page, 'ChgValidateCode.aspx');
                $posEnd = strpos($page, '"', $pos);
                $imgUrl = 'https://ap.ece.moe.edu.tw/webecems/' . substr($page, $pos, $posEnd - $pos);
                $ansDone = false;
                while (false === $ansDone) {
                    $client->request('GET', $imgUrl);
                    file_put_contents(__DIR__ . '/qq.png', $client->getResponse()->getContent());
                    copy(__DIR__ . '/qq.png', __DIR__ . '/qq_orig.png');
                    $img = new Imagick(__DIR__ . '/qq.png');

                    // set the image to black and white
                    $img->setImageType(Imagick::IMGTYPE_GRAYSCALE);
                    $img->adaptiveResizeImage(300, 300, true);
                    $img->medianFilterImage(15);
                    $img->adaptiveSharpenImage(19, 15);
                    $img->blackThresholdImage("rgb(254, 254, 254)");
                    $imageWidth = $img->getImageWidth() + (2 * ($borderWidth));
                    $imageHeight = $img->getImageHeight() + (2 * ($borderWidth));
                    $image = new Imagick();
                    $image->newImage($imageWidth, $imageHeight, new ImagickPixel('none'));
                    $border = new ImagickDraw();
                    $border->setStrokeColor(new ImagickPixel($borderColor));
                    $border->setStrokeWidth($borderWidth);
                    $border->setStrokeAntialias(false);
                    // Draw border
                    $border->rectangle(
                        $borderWidth / 2 - 1,
                        $borderWidth / 2 - 1,
                        $imageWidth - (($borderWidth / 2)),
                        $imageHeight - (($borderWidth / 2))
                    );
                    // Apply drawed border to final image
                    $image->drawImage($border);
                    $image->setImageFormat('png');
                    $image->compositeImage(
                        $img, Imagick::COMPOSITE_DEFAULT,
                        $borderWidth,
                        $borderWidth
                    );
                    $image->writeImage(__DIR__ . '/qq.png');

                    /**
                     * add /usr/share/tesseract-ocr/5/tessdata/configs/letters with the line
                     * tessedit_char_whitelist abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890
                     */
                    exec('/usr/bin/tesseract ' . __DIR__ . '/qq.png ' . __DIR__ . '/qq nobatch letters');
                    $ans = file_get_contents(__DIR__ . '/qq.txt');
                    $ans = preg_replace('/[^0-9a-z]+/i', '', $ans);
                    if (strlen($ans) === 4) {
                        $client->request('GET', $url . trim($ans));
                        $content = $client->getResponse()->getContent();
                        if (false === strpos($content, '驗證碼錯誤，請重新輸入')) {
                            $ansDone = true;
                            copy(__DIR__ . '/qq_orig.png', __DIR__ . '/base/' . $ans . '.png');
                            $rawFile = $rawPath . '/' . $data['title'] . '.html';
                            file_put_contents($rawFile, $content);
                            echo "{$rawFile}\n";
                        }
                    }
                }
            }
        }
    }
}