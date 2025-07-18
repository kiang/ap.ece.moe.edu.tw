<!DOCTYPE html>
<html>
<head>
    <title>PyTorch CAPTCHA Setup Check</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; }
        .check { margin: 10px 0; padding: 10px; border-radius: 5px; }
        .ok { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .warning { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 3px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>PyTorch CAPTCHA Setup Check</h1>
    
    <?php
    $checks = [];
    
    // Check Python
    $pythonCmd = '/usr/bin/python3 --version 2>&1';
    $pythonVersion = shell_exec($pythonCmd);
    if ($pythonVersion) {
        $checks[] = ['ok', 'Python 3', $pythonVersion];
    } else {
        $checks[] = ['error', 'Python 3', 'Not found'];
    }
    
    // Check PyTorch
    $torchCmd = '/usr/bin/python3 -c "import torch; print(\'PyTorch\', torch.__version__)" 2>&1';
    $torchVersion = shell_exec($torchCmd);
    if (strpos($torchVersion, 'PyTorch') !== false) {
        $checks[] = ['ok', 'PyTorch', $torchVersion];
    } else {
        $checks[] = ['error', 'PyTorch', $torchVersion];
    }
    
    // Check directories
    $dirs = ['base', 'failed', 'training'];
    foreach ($dirs as $dir) {
        if (is_dir($dir)) {
            $count = count(glob($dir . '/*.png'));
            $checks[] = ['ok', "Directory: $dir", "Exists with $count PNG files"];
        } else {
            $checks[] = ['warning', "Directory: $dir", 'Not found (will be created)'];
        }
    }
    
    // Check scripts
    $scripts = [
        'train_pytorch_model.py' => 'Training script',
        'predict_captcha.py' => 'Prediction script', 
        'predict_safe.py' => 'Safe prediction script',
        'train_simple.py' => 'Simple training script'
    ];
    
    foreach ($scripts as $script => $desc) {
        if (file_exists($script)) {
            $checks[] = ['ok', $desc, "Found: $script"];
        } else {
            $checks[] = ['error', $desc, "Missing: $script"];
        }
    }
    
    // Check model
    if (file_exists('captcha_model_best.pth')) {
        $size = round(filesize('captcha_model_best.pth') / 1024 / 1024, 2);
        $checks[] = ['ok', 'Trained Model', "Found: captcha_model_best.pth ({$size} MB)"];
        
        // Test prediction
        $testImages = glob('failed/*.png');
        if (!empty($testImages)) {
            $testImage = $testImages[0];
            $cmd = '/usr/bin/python3 predict_safe.py ' . escapeshellarg($testImage) . ' captcha_model_best.pth 2>&1';
            $result = shell_exec($cmd);
            $json = json_decode($result, true);
            
            if ($json && isset($json['text'])) {
                $checks[] = ['ok', 'Model Test', "Prediction: {$json['text']} (confidence: " . round($json['confidence']*100, 1) . "%)"];
            } else {
                $checks[] = ['error', 'Model Test', "Failed: $result"];
            }
        }
    } else {
        $checks[] = ['warning', 'Trained Model', 'Not found - you need to train the model first'];
    }
    
    // Display results
    foreach ($checks as $check) {
        $class = $check[0];
        $title = $check[1];
        $message = $check[2];
        echo "<div class='check $class'><strong>$title:</strong> $message</div>";
    }
    ?>
    
    <h2>Next Steps</h2>
    
    <?php if (!file_exists('captcha_model_best.pth')): ?>
    <div class="warning">
        <h3>⚠️ Model Not Trained</h3>
        <p>You need to train the model first. Run one of these commands:</p>
        <pre>cd game && python3 train_simple.py</pre>
        <p>Or for full training:</p>
        <pre>cd game && python3 train_pytorch_model.py</pre>
    </div>
    <?php else: ?>
    <div class="ok">
        <h3>✅ Ready to Use</h3>
        <p>Your setup looks good! You can now use:</p>
        <ul>
            <li><a href="select.php">select.php</a> - Manual CAPTCHA correction interface</li>
            <li><a href="../fast_pytorch.php">fast_pytorch.php</a> - Automated CAPTCHA solving</li>
        </ul>
    </div>
    <?php endif; ?>
    
    <h2>Commands</h2>
    <pre>
# Train model (simple/fast)
python3 train_simple.py

# Train model (full)
python3 train_pytorch_model.py

# Test prediction
python3 predict_safe.py failed/some_image.png

# Use web interface
# Navigate to: select.php
    </pre>
    
</body>
</html>