<?php
echo "<h2>PyTorch Debug Information</h2>";

echo "<h3>1. Testing wrapper script debug mode:</h3>";
echo "<pre>";
$cmd = "./predict_wrapper.sh failed/006GZ.png captcha_model_best.pth debug 2>&1";
$output = shell_exec($cmd);
echo htmlspecialchars($output);
echo "</pre>";

echo "<h3>2. Testing direct prediction:</h3>";
echo "<pre>";
$cmd = "./predict_wrapper.sh failed/006GZ.png captcha_model_best.pth 2>&1";
$output = shell_exec($cmd);
echo htmlspecialchars($output);
echo "</pre>";

echo "<h3>3. Testing with exec() (same as select.php):</h3>";
echo "<pre>";
$cmd = escapeshellarg('./predict_wrapper.sh') . ' ' . escapeshellarg('failed/006GZ.png') . ' ' . escapeshellarg('captcha_model_best.pth') . ' 2>&1';
$output = [];
$returnCode = 0;
exec($cmd, $output, $returnCode);
echo "Return code: $returnCode\n";
echo "Output:\n" . implode("\n", $output);
echo "</pre>";

echo "<h3>4. Testing from web server user context:</h3>";
echo "<pre>";
echo "Current user: " . get_current_user() . "\n";
echo "Current working directory: " . getcwd() . "\n";
echo "Script directory: " . __DIR__ . "\n";
echo "Wrapper script exists: " . (file_exists(__DIR__ . '/predict_wrapper.sh') ? 'YES' : 'NO') . "\n";
echo "Wrapper script executable: " . (is_executable(__DIR__ . '/predict_wrapper.sh') ? 'YES' : 'NO') . "\n";
echo "</pre>";
?>