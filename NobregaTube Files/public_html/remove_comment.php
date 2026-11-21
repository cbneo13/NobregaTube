<?php
require_once __DIR__ . '/includes/sess.php';
require __DIR__ . '/includes/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_SESSION['uid'])) {
	header('Location: index.php');
	exit;
}

$videoId = substr((string)($_POST['video_id'] ?? ''), 0, 20);
$commentId = substr((string)($_POST['comment_id'] ?? ''), 0, 20);
$returnUrl = 'watch.php?v=' . rawurlencode($videoId);

if (empty($_SESSION['csrf_token']) || !hash_equals((string)$_SESSION['csrf_token'], (string)($_POST['csrf_token'] ?? ''))) {
	header('Location: ' . $returnUrl);
	exit;
}

$query = $pdo->prepare('SELECT c.cid, c.uid AS comment_uid, v.uid AS video_uid
	FROM comments c JOIN videos v ON v.vid = c.vid
	WHERE c.cid = ? AND c.vid = ? LIMIT 1');
$query->execute([$commentId, $videoId]);
$comment = $query->fetch();

if (!$comment) {
	header('Location: ' . $returnUrl);
	exit;
}

$staffQuery = $pdo->prepare('SELECT staff FROM users WHERE uid = ? LIMIT 1');
$staffQuery->execute([$_SESSION['uid']]);
$isStaff = (int)$staffQuery->fetchColumn() === 1;

if ((string)$_SESSION['uid'] === (string)$comment['comment_uid'] || (string)$_SESSION['uid'] === (string)$comment['video_uid'] || $isStaff) {
	$pdo->prepare('UPDATE comments SET removed = 1 WHERE cid = ? AND vid = ?')->execute([$commentId, $videoId]);
}

header('Location: ' . $returnUrl);
exit;
