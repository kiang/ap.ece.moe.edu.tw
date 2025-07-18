<?php
/**
 * CAPTCHA resolver using PyTorch model
 * This version uses the trained PyTorch CNN model for better accuracy
 */
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

// Check if PyTorch model exists
$modelPath = __DIR__ . '/captcha_model_best.pth';
$pythonScript = __DIR__ . '/predict_captcha.py';

if (!file_exists($modelPath)) {
    echo "PyTorch model not found at $modelPath\n";
    echo "Please run train_pytorch_model.py first to train the model.\n";
    exit(1);
}

if (!file_exists($pythonScript)) {
    echo "Prediction script not found at $pythonScript\n";
    exit(1);
}

// Function to predict CAPTCHA using PyTorch model
function predictCaptcha($imagePath, $pythonScript, $modelPath) {
    $cmd = sprintf(
        'python3 %s %s %s 2>&1',
        escapeshellarg($pythonScript),
        escapeshellarg($imagePath),
        escapeshellarg($modelPath)
    );
    
    $output = shell_exec($cmd);
    if ($output === null) {
        return ['text' => '', 'confidence' => 0, 'error' => 'Failed to execute Python script'];
    }
    
    $result = json_decode($output, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['text' => '', 'confidence' => 0, 'error' => 'Invalid JSON output: ' . $output];
    }
    
    return $result;
}

$form = $crawler->selectButton('搜尋')->form();
$taskFound = false;
$domDocument = new \DOMDocument;
$successCount = 0;
$failCount = 0;

foreach (glob($basePath . '/docs/data/*.csv') as $csvFile) {
    $fh = fopen($csvFile, 'r');
    $head = fgetcsv($fh, 2048);
    while ($line = fgetcsv($fh, 2048)) {
        // Skip empty lines or lines with wrong field count
        if (empty($line) || count($head) !== count($line)) {
            continue;
        }
        $data = array_combine($head, $line);
        if (!$data || !isset($data['has_slip']) || $data['has_slip'] === 'no') {
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

            $subForm->remove('btnSearch');

            $crawler = $client->submit($subForm, [
                'txtKeyNameS' => '',
                '__EVENTTARGET' => 'GridView1$ctl02$lbChgList',
            ]);
            $page = $client->getResponse()->getContent();

            $cookies = $client->getCookieJar()->all();
            $cookies = array_map('strval', $cookies);
            file_put_contents($cookieFile, json_encode($cookies));

            $pos = strpos($page, 'ChgValidateCode.aspx');
            if (false !== $pos) {
                $posEnd = strpos($page, '"', $pos);
                $imgUrl = 'https://ap.ece.moe.edu.tw/webecems/' . substr($page, $pos, $posEnd - $pos);
                $ansDone = false;
                $attempts = 0;
                $maxAttempts = 10;
                
                while (false === $ansDone && $attempts < $maxAttempts) {
                    $attempts++;
                    $origImgFile = __DIR__ . '/qq_orig.png';
                    $imgFile = __DIR__ . '/qq.png';
                    $client->request('GET', $imgUrl);
                    file_put_contents($origImgFile, $client->getResponse()->getContent());
                    
                    // Apply the same preprocessing as before
                    exec("/usr/bin/convert {$origImgFile} \( +clone -threshold 70% -negate -type bilevel -define connected-components:area-threshold=5 -define connected-components:mean-color=true -connected-components 1 \) -alpha off -compose copy_opacity -composite -compose over -background white -flatten {$imgFile}");

                    // Use PyTorch model for prediction
                    $prediction = predictCaptcha($imgFile, $pythonScript, $modelPath);
                    
                    if (isset($prediction['text']) && strlen($prediction['text']) === 5) {
                        $ans = $prediction['text'];
                        $confidence = $prediction['confidence'] ?? 0;
                        
                        echo "Predicted: $ans (confidence: " . round($confidence * 100, 2) . "%)\n";
                        
                        // Only attempt if confidence is high enough
                        if ($confidence > 0.7) {
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
                                $successCount++;
                                copy($origImgFile, __DIR__ . '/base/' . $ans . '.png');
                                if (false === strpos($content, '幼生系統收費登載取得錯誤')) {
                                    $rawFile = $rawPath . '/' . $data['title'] . '.html';
                                    file_put_contents($rawFile, $content);
                                    echo "{$rawFile} - SUCCESS\n";
                                } else {
                                    echo "{$data['title']} 幼生系統收費登載取得錯誤 - SUCCESS\n";
                                }
                            } else {
                                $failCount++;
                                copy($origImgFile, __DIR__ . '/failed/' . $ans . '.png');
                                echo "Failed attempt $attempts/$maxAttempts\n";
                            }
                        } else {
                            echo "Low confidence, skipping attempt\n";
                        }
                    } else {
                        echo "Invalid prediction or error: " . ($prediction['error'] ?? 'Unknown error') . "\n";
                    }
                }
                
                if (!$ansDone) {
                    echo "Failed to solve CAPTCHA after $maxAttempts attempts\n";
                }
            }
        }
    }
}

echo "\n=== Summary ===\n";
echo "Success: $successCount\n";
echo "Failed: $failCount\n";
$successRate = $successCount > 0 ? round(($successCount / ($successCount + $failCount)) * 100, 2) : 0;
echo "Success Rate: $successRate%\n";