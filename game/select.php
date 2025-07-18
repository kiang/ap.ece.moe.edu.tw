<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CAPTCHA Manual Correction</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .image-container {
            text-align: center;
            margin: 20px 0;
            padding: 20px;
            background: #f9f9f9;
            border-radius: 8px;
        }
        .captcha-image {
            max-width: 100%;
            border: 2px solid #ddd;
            border-radius: 5px;
            background: white;
            padding: 10px;
        }
        .form-group {
            margin: 20px 0;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
        }
        input[type="text"] {
            width: 100%;
            padding: 12px;
            font-size: 18px;
            font-family: monospace;
            border: 2px solid #ddd;
            border-radius: 5px;
            text-align: center;
            letter-spacing: 3px;
            box-sizing: border-box;
        }
        input[type="text"]:focus {
            border-color: #4CAF50;
            outline: none;
        }
        .button-group {
            text-align: center;
            margin: 30px 0;
        }
        button {
            padding: 12px 24px;
            margin: 0 10px;
            font-size: 16px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        .btn-submit {
            background-color: #4CAF50;
            color: white;
        }
        .btn-submit:hover {
            background-color: #45a049;
        }
        .btn-skip {
            background-color: #f44336;
            color: white;
        }
        .btn-skip:hover {
            background-color: #da190b;
        }
        .btn-next {
            background-color: #2196F3;
            color: white;
        }
        .btn-next:hover {
            background-color: #0b7dda;
        }
        .stats {
            background: #e8f5e8;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .prediction-info {
            background: #f0f8ff;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
            border-left: 4px solid #2196F3;
        }
        .error {
            background: #ffebee;
            color: #c62828;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
            border-left: 4px solid #f44336;
        }
        .success {
            background: #e8f5e8;
            color: #2e7d32;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
            border-left: 4px solid #4CAF50;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>CAPTCHA Manual Correction</h1>
        
        <?php
        $failedDir = __DIR__ . '/failed';
        $baseDir = __DIR__ . '/base';
        $pythonScript = __DIR__ . '/predict_captcha.py';
        $modelPath = __DIR__ . '/captcha_model_best.pth';
        
        // Ensure directories exist
        if (!is_dir($failedDir)) {
            mkdir($failedDir, 0777, true);
        }
        if (!is_dir($baseDir)) {
            mkdir($baseDir, 0777, true);
        }
        
        // Function to get prediction from PyTorch model
        function getPrediction($imagePath, $pythonScript, $modelPath) {
            // Use the bash wrapper script for better environment handling
            $wrapperScript = __DIR__ . '/predict_wrapper.sh';
            
            if (!file_exists($wrapperScript)) {
                return ['text' => '', 'confidence' => 0, 'error' => 'Wrapper script not found'];
            }
            
            if (!file_exists($modelPath)) {
                return ['text' => '', 'confidence' => 0, 'error' => 'Model file not found. Please train the model first.'];
            }
            
            // Use exec with the wrapper script
            $cmd = sprintf(
                '%s %s %s 2>&1',
                escapeshellarg($wrapperScript),
                escapeshellarg($imagePath),
                escapeshellarg($modelPath)
            );
            
            $output = [];
            $returnCode = 0;
            exec($cmd, $output, $returnCode);
            
            if (empty($output)) {
                return ['text' => '', 'confidence' => 0, 'error' => "No output from prediction script. Return code: $returnCode. Command: $cmd"];
            }
            
            // Get the last line which should be JSON
            $jsonLine = trim(end($output));
            
            if (empty($jsonLine)) {
                return ['text' => '', 'confidence' => 0, 'error' => 'Empty output from prediction script'];
            }
            
            $result = json_decode($jsonLine, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return ['text' => '', 'confidence' => 0, 'error' => 'Invalid JSON output. Return code: ' . $returnCode . '. Full output: ' . implode(' | ', $output)];
            }
            
            return $result;
        }
        
        // Handle form submission
        if ($_POST['action'] === 'submit' && !empty($_POST['text']) && !empty($_POST['filename'])) {
            $text = trim($_POST['text']);
            $filename = basename($_POST['filename']);
            $sourcePath = $failedDir . '/' . $filename;
            
            if (preg_match('/^[A-Za-z0-9]{5}$/', $text) && file_exists($sourcePath)) {
                $targetPath = $baseDir . '/' . $text . '.png';
                
                if (rename($sourcePath, $targetPath)) {
                    echo '<div class="success">✓ Successfully moved ' . htmlspecialchars($filename) . ' to base/' . htmlspecialchars($text) . '.png</div>';
                } else {
                    echo '<div class="error">✗ Failed to move file</div>';
                }
            } else {
                echo '<div class="error">✗ Invalid text (must be exactly 5 alphanumeric characters) or file not found</div>';
            }
        }
        
        // Handle skip action
        if ($_POST['action'] === 'skip' && !empty($_POST['filename'])) {
            // Just reload to get next image
        }
        
        // Get failed images
        $failedImages = glob($failedDir . '/*.png');
        $totalFailed = count($failedImages);
        $totalBase = count(glob($baseDir . '/*.png'));
        
        echo '<div class="stats">';
        echo '<strong>Statistics:</strong><br>';
        echo 'Failed images: ' . $totalFailed . '<br>';
        echo 'Base images: ' . $totalBase . '<br>';
        echo 'Success rate: ' . ($totalBase > 0 ? round($totalBase / ($totalBase + $totalFailed) * 100, 2) : 0) . '%';
        echo '</div>';
        
        if (empty($failedImages)) {
            echo '<div class="success">🎉 No failed images to process! All CAPTCHAs have been resolved.</div>';
        } else {
            // Select a random failed image
            $selectedImage = $failedImages[array_rand($failedImages)];
            $filename = basename($selectedImage);
            $imageUrl = 'failed/' . $filename;
            
            // Get prediction from model
            $prediction = getPrediction($selectedImage, $pythonScript, $modelPath);
            ?>
            
            <div class="image-container">
                <h3>Current Image: <?php echo htmlspecialchars($filename); ?></h3>
                <img src="<?php echo htmlspecialchars($imageUrl); ?>" alt="CAPTCHA" class="captcha-image">
            </div>
            
            <?php if (isset($prediction['error'])): ?>
                <div class="error">
                    <strong>Prediction Error:</strong> <?php echo htmlspecialchars($prediction['error']); ?>
                </div>
            <?php else: ?>
                <div class="prediction-info">
                    <strong>AI Prediction:</strong> <?php echo htmlspecialchars($prediction['text']); ?><br>
                    <strong>Confidence:</strong> <?php echo round($prediction['confidence'] * 100, 2); ?>%
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <input type="hidden" name="filename" value="<?php echo htmlspecialchars($filename); ?>">
                
                <div class="form-group">
                    <label for="text">Correct Text (5 characters):</label>
                    <input type="text" 
                           id="text" 
                           name="text" 
                           maxlength="5" 
                           pattern="[A-Za-z0-9]{5}" 
                           value="<?php echo htmlspecialchars($prediction['text'] ?? ''); ?>"
                           placeholder="Enter 5 characters"
                           required
                           autocomplete="off">
                </div>
                
                <div class="button-group">
                    <button type="submit" name="action" value="submit" class="btn-submit">
                        ✓ Save & Move to Base
                    </button>
                    <button type="submit" name="action" value="skip" class="btn-skip">
                        ✗ Skip This Image
                    </button>
                    <button type="button" onclick="window.location.reload()" class="btn-next">
                        → Next Random Image
                    </button>
                </div>
            </form>
            
            <script>
                // Auto-focus and select text input
                document.getElementById('text').focus();
                document.getElementById('text').select();
                
                // Auto-uppercase input
                document.getElementById('text').addEventListener('input', function(e) {
                    this.value = this.value.toUpperCase();
                });
                
                // Keyboard shortcuts
                document.addEventListener('keydown', function(e) {
                    if (e.ctrlKey || e.metaKey) {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            document.querySelector('button[value="submit"]').click();
                        } else if (e.key === 's') {
                            e.preventDefault();
                            document.querySelector('button[value="skip"]').click();
                        } else if (e.key === 'r') {
                            e.preventDefault();
                            window.location.reload();
                        }
                    }
                });
            </script>
            
            <?php
        }
        ?>
        
        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; color: #666; font-size: 14px;">
            <strong>Keyboard Shortcuts:</strong><br>
            Ctrl+Enter: Save & Move<br>
            Ctrl+S: Skip Image<br>
            Ctrl+R: Next Random Image
        </div>
    </div>
</body>
</html>