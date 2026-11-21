<?php
require_once __DIR__ . '/includes/sess.php';
require __DIR__ . '/includes/database.php';

$captchaToken = (string)($_GET['c'] ?? '');
$sessionToken = (string)($_SESSION['captcha_token'] ?? '');
if ($captchaToken === '' || $sessionToken === '' || !hash_equals($sessionToken, $captchaToken)) {
	http_response_code(400);
	exit;
}

$characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
$answer = '';
for ($index = 0; $index < 5; $index++) {
	$answer .= $characters[random_int(0, strlen($characters) - 1)];
}
$_SESSION['signup_captcha'] = $answer;

$image = imagecreatetruecolor(110, 40);
$background = imagecolorallocate($image, 232, 238, 246);
$textColor = imagecolorallocate($image, 25, 45, 80);
$noiseColor = imagecolorallocate($image, 150, 165, 185);
imagefilledrectangle($image, 0, 0, 109, 39, $background);
for ($index = 0; $index < 18; $index++) {
	imageline($image, random_int(0, 109), random_int(0, 39), random_int(0, 109), random_int(0, 39), $noiseColor);
}
imagestring($image, 5, 14, 11, $answer, $textColor);

header('Content-Type: image/png');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
imagepng($image);
imagedestroy($image);
