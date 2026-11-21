<?php
require_once __DIR__ . '/includes/sess.php';
require __DIR__ . '/includes/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_SESSION['uid'])) {
	header('Location: login.php');
	exit;
}

if (empty($_SESSION['csrf_token']) || !hash_equals((string)$_SESSION['csrf_token'], (string)($_POST['csrf_token'] ?? ''))) {
	http_response_code(403);
	exit('Your form session expired. Refresh the page and try again.');
}

$videoId = substr((string)($_POST['video_id'] ?? ''), 0, 20);
$videoQuery = $pdo->prepare('SELECT file_path, flv_path FROM videos WHERE vid = ? AND uid = ? LIMIT 1');
$videoQuery->execute([$videoId, $_SESSION['uid']]);
$video = $videoQuery->fetch();
if (!$video) {
	http_response_code(404);
	exit('Video not found.');
}

$pdo->beginTransaction();
try {
	$delete = $pdo->prepare('DELETE FROM videos WHERE vid = ? AND uid = ?');
	$delete->execute([$videoId, $_SESSION['uid']]);
	if ($delete->rowCount() !== 1) {
		$pdo->rollBack();
		http_response_code(404);
		exit('Video not found.');
	}
	$pdo->commit();
} catch (Throwable $error) {
	if ($pdo->inTransaction()) {
		$pdo->rollBack();
	}
	throw $error;
}

$paths = array_unique(array_filter([$video['file_path'], $video['flv_path']]));
foreach ($paths as $storedName) {
	$filename = basename((string)$storedName);
	if ($filename !== (string)$storedName || $filename === '' || !preg_match('/^[A-Za-z0-9._-]+$/', $filename)) {
		error_log('NobregaTube could not remove video file with an invalid stored path.');
		continue;
	}
	$path = __DIR__ . '/data/videos/' . $filename;
	if (is_file($path) && !unlink($path)) {
		error_log('NobregaTube could not remove video file: ' . $path);
	}
}

foreach ([1, 2, 3] as $stillId) {
	$stillPath = __DIR__ . '/data/thmbs/' . $videoId . '_' . $stillId . '.jpg';
	if (is_file($stillPath) && !unlink($stillPath)) {
		error_log('NobregaTube could not remove video thumbnail: ' . $stillPath);
	}
}

header('Location: my_videos.php?removed=1');
exit;
