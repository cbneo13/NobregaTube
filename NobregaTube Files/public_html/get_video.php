<?php
require_once __DIR__ . '/includes/sess.php';
require __DIR__ . '/includes/database.php';

$videoId = substr((string)($_GET['v'] ?? $_GET['video_id'] ?? ''), 0, 20);
$query = $pdo->prepare('SELECT v.uid, v.file_path, v.flv_path, v.mime_type, v.privacy, v.converted, u.termination
	FROM videos v INNER JOIN users u ON u.uid = v.uid WHERE v.vid = ? LIMIT 1');
$query->execute([$videoId]);
$video = $query->fetch();
if (!$video || (int)$video['termination'] === 1 || ((int)$video['privacy'] === 2 && (string)($_SESSION['uid'] ?? '') !== (string)$video['uid'])) {
	http_response_code(404);
	exit;
}
$wantFlv = (isset($_GET['format']) && $_GET['format'] === 'flv') || (isset($_GET['video_id']) && !isset($_GET['format']));
if ((int)$video['converted'] !== 1) {
	http_response_code(404);
	exit;
}
$fileName = $wantFlv ? $video['flv_path'] : $video['file_path'];
if (!$fileName) {
	http_response_code(404);
	exit;
}
$path = __DIR__ . '/data/videos/' . basename($fileName);
if (!is_file($path)) {
	http_response_code(404);
	exit;
}
header('Content-Type: ' . ($wantFlv ? 'video/x-flv' : $video['mime_type']));
header('X-Content-Type-Options: nosniff');
$size = filesize($path);
$start = 0;
$end = $size - 1;
if (!empty($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d*)-(\d*)/', $_SERVER['HTTP_RANGE'], $range)) {
	if ($range[1] === '' && $range[2] !== '') {
		$start = max(0, $size - (int)$range[2]);
	} else {
		$start = (int)$range[1];
		if ($range[2] !== '') {
			$end = min($end, (int)$range[2]);
		}
	}
	if ($start > $end || $start >= $size) {
		header('HTTP/1.1 416 Requested Range Not Satisfiable');
		header('Content-Range: bytes */' . $size);
		exit;
	}
	header('HTTP/1.1 206 Partial Content');
	header('Accept-Ranges: bytes');
	header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
} else {
	header('Accept-Ranges: bytes');
}
header('Content-Length: ' . ($end - $start + 1));
$handle = fopen($path, 'rb');
fseek($handle, $start);
$remaining = $end - $start + 1;
while ($remaining > 0 && !feof($handle)) {
	$chunk = fread($handle, min(8192, $remaining));
	if ($chunk === false || $chunk === '') {
		break;
	}
	echo $chunk;
	$remaining -= strlen($chunk);
}
fclose($handle);
