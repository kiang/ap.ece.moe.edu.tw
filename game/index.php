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

if (!empty($_POST['url'])) {
    $data = json_decode(file_get_contents(__DIR__ . '/task.json'), true);
    $client->request('GET', $_POST['url'] . $_POST['v']);
    $rawPath = $basePath . '/raw/slip/' . $data['city'];
    if (!file_exists($rawPath)) {
        mkdir($rawPath, 0777, true);
    }
    $rawFile = $rawPath . '/' . $data['title'] . '.html';
    file_put_contents($rawFile, $client->getResponse()->getContent());
}
$crawler = $client->request('GET', 'https://ap.ece.moe.edu.tw/webecems/pubSearch.aspx');

$form = $crawler->selectButton('搜尋')->form();
$taskFound = false;
foreach (glob($basePath . '/data/*.csv') as $csvFile) {
    if (!$taskFound) {
        $fh = fopen($csvFile, 'r');
        $head = fgetcsv($fh, 2048);
        while ($line = fgetcsv($fh, 2048)) {
            $data = array_combine($head, $line);
            $rawPath = $basePath . '/raw/slip/' . $data['city'];
            if (!file_exists($rawPath)) {
                mkdir($rawPath, 0777, true);
            }
            $rawFile = $rawPath . '/' . $data['title'] . '.html';
            if (!file_exists($rawFile)) {
                $taskFound = true;
                fseek($fh, 0, SEEK_END);
                file_put_contents(__DIR__ . '/task.json', json_encode($data));
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
?>
                    <img src="qq.png?<?php echo mt_rand(); ?>" />
                    <form method="post">
                        <textarea name="url"><?php echo $url; ?></textarea>
                        <input type="text" name="v" />
                        <input type="submit" value="go" />
                    </form>
<?php
                }
            }
        }
    }
}
