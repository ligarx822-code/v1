<?php
require_once 'config/config.php';
require_once 'includes/security.php';

$security = new Security();

// Generate and output captcha image
$captcha_data = $security->generateCaptcha();

// Extract base64 data and output as image
$image_data = str_replace('data:image/png;base64,', '', $captcha_data);
$image_data = base64_decode($image_data);

header('Content-Type: image/png');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

echo $image_data;
?>
