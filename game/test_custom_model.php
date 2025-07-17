<?php
/**
 * Test script to compare default Tesseract vs custom trained model
 */

$testDir = __DIR__ . '/failed'; // Test on failed cases
$customModel = __DIR__ . '/training/output/captcha.traineddata';

// Check if custom model exists
if (!file_exists($customModel)) {
    die("Custom model not found. Please run train_tesseract_model.sh first.\n");
}

$modelDir = dirname($customModel);

// Get test samples
$testFiles = array_slice(glob($testDir . '/*.png'), 0, 50);

$defaultCorrect = 0;
$customCorrect = 0;
$total = 0;

echo "Testing " . count($testFiles) . " images...\n\n";
echo str_pad("Image", 15) . str_pad("Expected", 10) . str_pad("Default", 10) . str_pad("Custom", 10) . "Status\n";
echo str_repeat("-", 55) . "\n";

foreach ($testFiles as $file) {
    $filename = basename($file, '.png');
    $expected = $filename;
    
    // Skip if not valid format
    if (!preg_match('/^[A-Za-z0-9]{5}$/', $expected)) {
        continue;
    }
    
    // Apply preprocessing
    $processedFile = '/tmp/test_captcha.png';
    $cmd = "/usr/bin/convert \"$file\" \\( +clone -threshold 70% -negate -type bilevel -define connected-components:area-threshold=5 -define connected-components:mean-color=true -connected-components 1 \\) -alpha off -compose copy_opacity -composite -compose over -background white -flatten \"$processedFile\"";
    exec($cmd);
    
    // Test with default model
    exec("/usr/bin/tesseract \"$processedFile\" /tmp/default_result --psm 8 -c load_system_dawg=0 -c load_freq_dawg=0 letters 2>/dev/null");
    $defaultResult = trim(file_get_contents('/tmp/default_result.txt'));
    $defaultResult = preg_replace('/[^0-9a-zA-Z]/', '', $defaultResult);
    
    // Test with custom model
    exec("/usr/bin/tesseract \"$processedFile\" /tmp/custom_result -l captcha --tessdata-dir \"$modelDir\" --psm 8 -c load_system_dawg=0 -c load_freq_dawg=0 2>/dev/null");
    $customResult = trim(file_get_contents('/tmp/custom_result.txt'));
    $customResult = preg_replace('/[^0-9a-zA-Z]/', '', $customResult);
    
    $defaultMatch = ($defaultResult === $expected);
    $customMatch = ($customResult === $expected);
    
    if ($defaultMatch) $defaultCorrect++;
    if ($customMatch) $customCorrect++;
    $total++;
    
    $status = '';
    if (!$defaultMatch && $customMatch) {
        $status = '✓ Improved!';
    } elseif ($defaultMatch && !$customMatch) {
        $status = '✗ Worse';
    } elseif ($customMatch) {
        $status = '✓ Both correct';
    }
    
    echo str_pad($filename, 15) . str_pad($expected, 10) . str_pad($defaultResult ?: '(empty)', 10) . str_pad($customResult ?: '(empty)', 10) . $status . "\n";
}

// Clean up
unlink('/tmp/test_captcha.png');
unlink('/tmp/default_result.txt');
unlink('/tmp/custom_result.txt');

// Summary
echo "\n" . str_repeat("=", 55) . "\n";
echo "Summary:\n";
echo "Total tested: $total\n";
echo "Default model accuracy: " . round($defaultCorrect / $total * 100, 2) . "% ($defaultCorrect/$total)\n";
echo "Custom model accuracy: " . round($customCorrect / $total * 100, 2) . "% ($customCorrect/$total)\n";
$improvement = round(($customCorrect - $defaultCorrect) / $total * 100, 2);
echo "Improvement: " . ($improvement >= 0 ? '+' : '') . "$improvement%\n";