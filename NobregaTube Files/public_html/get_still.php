<?php
require __DIR__ . '/includes/database.php';
$videoId = substr((string)($_GET['video_id'] ?? ''), 0, 20);
$stillId = (int)($_GET['still_id'] ?? 2);
if ($stillId < 1 || $stillId > 3) {
	$stillId = 2;
}
$query = $pdo->prepare('SELECT v.converted, u.termination
	FROM videos v INNER JOIN users u ON u.uid = v.uid WHERE v.vid = ? LIMIT 1');
$query->execute([$videoId]);
$video = $query->fetch();
$still = __DIR__ . '/data/thmbs/' . $videoId . '_' . $stillId . '.jpg';
if ($video && (int)$video['converted'] === 1 && (int)$video['termination'] === 0 && is_file($still)) {
	header('Content-Type: image/jpeg');
	readfile($still);
	exit;
}
header('Content-Type: image/jpeg');
header('Cache-Control: no-cache');
readfile(__DIR__ . '/unavail.jpg');
