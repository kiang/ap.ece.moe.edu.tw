<?php
/**
 * Prepare training data for Tesseract custom model
 * Converts successful CAPTCHA images into Tesseract training format
 */

$baseDir = __DIR__ . '/base';
$trainDir = __DIR__ . '/training';
$outputDir = $trainDir . '/ground-truth';

// Create directories
if (!file_exists($trainDir)) {
    mkdir($trainDir, 0777, true);
}
if (!file_exists($outputDir)) {
    mkdir($outputDir, 0777, true);
}

// Process each successful CAPTCHA
$files = glob($baseDir . '/*.png');
$count = 0;
$maxFiles = 5000; // Limit for initial training

echo "Processing " . count($files) . " images...\n";

foreach ($files as $file) {
    if ($count >= $maxFiles) {
        break;
    }
    
    $filename = basename($file, '.png');
    // The filename is the actual text in the image
    $text = $filename;
    
    // Validate: should be exactly 5 alphanumeric characters
    if (!preg_match('/^[A-Za-z0-9]{5}$/', $text)) {
        echo "Skipping invalid filename: $filename\n";
        continue;
    }
    
    // Apply the same preprocessing as in fast.php
    $origFile = $file;
    $processedFile = $outputDir . '/' . $filename . '.png';
    $cmd = "/usr/bin/convert \"$origFile\" \\( +clone -threshold 70% -negate -type bilevel -define connected-components:area-threshold=5 -define connected-components:mean-color=true -connected-components 1 \\) -alpha off -compose copy_opacity -composite -compose over -background white -flatten \"$processedFile\"";
    exec($cmd);
    
    // Create ground truth text file
    $gtFile = $outputDir . '/' . $filename . '.gt.txt';
    file_put_contents($gtFile, $text);
    
    $count++;
    if ($count % 100 == 0) {
        echo "Processed $count images...\n";
    }
}

echo "Completed! Processed $count images.\n";
echo "Training data saved in: $outputDir\n";

// Create a list file for training
$listFile = $trainDir . '/training_files.txt';
$listContent = '';
$gtFiles = glob($outputDir . '/*.png');
foreach ($gtFiles as $gtFile) {
    $basename = basename($gtFile, '.png');
    $listContent .= $basename . "\n";
}
file_put_contents($listFile, $listContent);
echo "Training list saved to: $listFile\n";