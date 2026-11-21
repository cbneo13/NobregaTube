<?php
require_once __DIR__ . '/includes/sess.php';
require __DIR__ . '/includes/database.php';

function upload_failure($message)
{
	?><!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd"><html lang="en"><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"><title>NobregaTube - Upload Error</title><link rel="stylesheet" href="styles2005july.css"></head><body><table width="100%" align="center" bgcolor="#FF0000" cellpadding="6" cellspacing="3" border="0"><tr><td align="center" bgcolor="#FFFFFF"><span class="error"><?php echo h($message); ?></span></td></tr></table><br><p align="center"><a href="my_videos_upload_2.php">Return to Step 2</a></p></body></html><?php
	exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_SESSION['pending_upload'])) {
	header('Location: my_videos_upload.php');
	exit;
}
if (empty($_SESSION['uid'])) {
	header('Location: login.php');
	exit;
}
if (!hash_equals((string)($_SESSION['csrf_token'] ?? ''), (string)($_POST['csrf_token'] ?? ''))) {
	upload_failure('Your form session expired. Please refresh the page and try again.');
}
if (!isset($_FILES['fileToUpload']) || $_FILES['fileToUpload']['error'] !== UPLOAD_ERR_OK) {
	upload_failure('Please choose a video file to upload.');
}

$upload = $_FILES['fileToUpload'];
$maxFileSize = 100 * 1024 * 1024;
if (!is_uploaded_file($upload['tmp_name']) || $upload['size'] < 1 || $upload['size'] > $maxFileSize) {
	upload_failure('The video must be a valid file no larger than 100 MB.');
}
$extension = strtolower(pathinfo($upload['name'], PATHINFO_EXTENSION));
$mimeTypes = [
	'mp4' => 'video/mp4',
	'm4v' => 'video/mp4',
	'webm' => 'video/webm',
	'ogv' => 'video/ogg',
	'ogg' => 'video/ogg',
	'mov' => 'video/quicktime',
	'avi' => 'video/x-msvideo',
	'flv' => 'video/x-flv',
];
if (!isset($mimeTypes[$extension])) {
	upload_failure('Please upload an MP4, WebM, Ogg, MOV, AVI, or FLV video.');
}
if (function_exists('finfo_open')) {
	$finfo = finfo_open(FILEINFO_MIME_TYPE);
	$detectedMime = $finfo ? finfo_file($finfo, $upload['tmp_name']) : false;
	if ($finfo) {
		finfo_close($finfo);
	}
	if ($detectedMime && !in_array($detectedMime, [$mimeTypes[$extension], 'application/octet-stream'], true)) {
		upload_failure('The selected file does not appear to be a supported video.');
	}
}

$videoId = bin2hex(random_bytes(10));
$videoDirectory = __DIR__ . '/data/videos';
$thumbDirectory = __DIR__ . '/data/thmbs';
foreach ([$videoDirectory, $thumbDirectory] as $directory) {
	if (!is_dir($directory) && !mkdir($directory, 0755, true)) {
		upload_failure('The video storage directory could not be created.');
	}
}
$sourceName = $videoId . '_temp.' . $extension;
$sourcePath = $videoDirectory . '/' . $sourceName;
if (!move_uploaded_file($upload['tmp_name'], $sourcePath)) {
	upload_failure('The server could not save the uploaded video.');
}

$pending = $_SESSION['pending_upload'];
$privacy = ((string)($_POST['private'] ?? '1') === '2') ? 2 : 1;
$recordedDate = null;
if (($pending['month'] ?? '---') !== '---' && ($pending['day'] ?? '---') !== '---' && ($pending['year'] ?? '---') !== '---') {
	$recordedDate = sprintf('%04d-%02d-%02d', (int)$pending['year'], (int)$pending['month'], (int)$pending['day']);
}
try {
	$pdo->beginTransaction();
	$accountLock = $pdo->prepare('SELECT uid, termination, suspended_until,
		(suspended_until IS NOT NULL AND suspended_until > UTC_TIMESTAMP()) AS is_suspended
		FROM users WHERE uid = ? FOR UPDATE');
	$accountLock->execute([$_SESSION['uid']]);
	$uploadAccount = $accountLock->fetch();
	if (!$uploadAccount || (int)$uploadAccount['termination'] === 1) {
		throw new RuntimeException('This account is not permitted to upload videos.');
	}
	if ((int)$uploadAccount['is_suspended'] === 1) {
		throw new RuntimeException('This account is temporarily suspended.');
	}

	$recentUploadsQuery = $pdo->prepare('SELECT COUNT(*) FROM videos
		WHERE uid = ? AND uploaded >= DATE_SUB(NOW(), INTERVAL 60 SECOND)');
	$recentUploadsQuery->execute([$_SESSION['uid']]);
	$recentUploads = (int)$recentUploadsQuery->fetchColumn();

	$statement = $pdo->prepare('INSERT INTO videos
		(uid, vid, tags, title, description, file, file_path, mime_type, file_size, privacy, uploaded, converted, recorddate, address, addrcountry)
		VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 0, ?, ?, ?)');
	$statement->execute([
		$_SESSION['uid'],
		$videoId,
		$pending['tags'],
		$pending['title'],
		$pending['description'],
		basename($upload['name']),
		$sourceName,
		$mimeTypes[$extension],
		(int)$upload['size'],
		$privacy,
		$recordedDate,
		$pending['address'] !== '' ? $pending['address'] : null,
		$pending['country'] !== '' ? $pending['country'] : null,
	]);
	$suspendedUntil = null;
	if ($recentUploads >= 3) {
		$pdo->prepare('UPDATE users SET suspended_until = DATE_ADD(UTC_TIMESTAMP(), INTERVAL 2 DAY)
			WHERE uid = ?')->execute([$_SESSION['uid']]);
		$suspendedUntilQuery = $pdo->prepare('SELECT suspended_until FROM users WHERE uid = ?');
		$suspendedUntilQuery->execute([$_SESSION['uid']]);
		$suspendedUntil = (string)$suspendedUntilQuery->fetchColumn();
	}
	$pdo->commit();
} catch (Throwable $exception) {
	if ($pdo->inTransaction()) {
		$pdo->rollBack();
	}
	if (is_file($sourcePath)) unlink($sourcePath);
	if ($exception instanceof RuntimeException
		&& in_array($exception->getMessage(), [
			'This account is not permitted to upload videos.',
			'This account is temporarily suspended.',
		], true)) {
		upload_failure($exception->getMessage());
	}
	error_log('NobregaTube could not save an uploaded video: ' . $exception->getMessage());
	upload_failure('The video could not be added to the database. Please try again.');
}

unset($_SESSION['pending_upload']);
exec('php ' . escapeshellarg(__DIR__ . '/process_video.php') . ' '
	. escapeshellarg($videoId) . ' >/dev/null 2>&1 &');
if ($suspendedUntil !== null) {
	http_response_code(429);
	?><!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
	<html lang="en"><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"><title>NobregaTube - Upload Limit</title><link rel="stylesheet" href="styles2005july.css"></head>
	<body><div class="pageTitle">Upload Limit</div>
	<p class="success">Your fourth video was accepted and is being processed.</p>
	<p class="error">Your account has been suspended for 2 days because 4 videos were uploaded within 60 seconds. The suspension ends <?php echo h(gmdate('F j, Y H:i', strtotime($suspendedUntil . ' UTC'))); ?> UTC.</p>
	<p><a href="login.php">Continue to log in</a></p></body></html><?php
	exit;
}
header('Location: my_videos_upload_complete.php?v=' . rawurlencode($videoId));
exit;
