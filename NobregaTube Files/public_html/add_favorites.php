<?php
require_once __DIR__ . '/includes/sess.php';
require __DIR__ . '/includes/database.php';
$videoId = substr((string)($_POST['video_id'] ?? ''), 0, 20);
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_SESSION['uid'])) {
	header('Location: login.php');
	exit;
}
if (!hash_equals((string)($_SESSION['csrf_token'] ?? ''), (string)($_POST['csrf_token'] ?? ''))) {
	header('Location: watch.php?v=' . rawurlencode($videoId));
	exit;
}
$exists = $pdo->prepare('SELECT v.vid FROM videos v
	INNER JOIN users u ON u.uid = v.uid
	WHERE v.vid = ? AND (v.privacy = 1 OR v.uid = ?) AND v.converted = 1 AND u.termination = 0 LIMIT 1');
$exists->execute([$videoId, $_SESSION['uid']]);
if (!$exists->fetchColumn()) {
	header('Location: watch.php');
	exit;
}
$favorite = $pdo->prepare('SELECT 1 FROM favorites WHERE uid = ? AND vid = ?');
$favorite->execute([$_SESSION['uid'], $videoId]);
if ($favorite->fetchColumn()) {
	$pdo->prepare('DELETE FROM favorites WHERE uid = ? AND vid = ?')->execute([$_SESSION['uid'], $videoId]);
} else {
	$pdo->prepare('INSERT IGNORE INTO favorites (uid, vid) VALUES (?, ?)')->execute([$_SESSION['uid'], $videoId]);
}
header('Location: watch.php?v=' . rawurlencode($videoId));
exit;
