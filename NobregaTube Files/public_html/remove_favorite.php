<?php
require_once __DIR__ . '/includes/sess.php';
require __DIR__ . '/includes/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_SESSION['uid'])) {
	header('Location: login.php');
	exit;
}

if (!hash_equals((string)($_SESSION['csrf_token'] ?? ''), (string)($_POST['csrf_token'] ?? ''))) {
	http_response_code(403);
	exit('Your form session expired. Refresh the page and try again.');
}

$videoId = trim((string)($_POST['video_id'] ?? ''));
if (!preg_match('/^[A-Za-z0-9_-]{1,20}$/', $videoId)) {
	header('Location: my_favorites.php');
	exit;
}

$removeQuery = $pdo->prepare('DELETE f FROM favorites f
	INNER JOIN videos v ON v.vid = f.vid
	INNER JOIN users u ON u.uid = v.uid
	WHERE f.uid = ? AND f.vid = ?
	AND v.converted = 1 AND v.privacy = 1 AND u.termination = 0');
$removeQuery->execute([$_SESSION['uid'], $videoId]);

$page = filter_var($_POST['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$page = $page === false ? 1 : $page;
header('Location: my_favorites.php?page=' . $page . ($removeQuery->rowCount() === 1 ? '&removed=1' : ''));
exit;
