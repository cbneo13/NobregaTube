<?php
require_once __DIR__ . '/../includes/sess.php';

$page = filter_var($_POST['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$page = $page === false ? 1 : $page;
$redirect = 'index.php?page=' . $page;

if (empty($_SESSION['uid'])) {
	header('Location: ../login.php');
	exit;
}

require __DIR__ . '/../includes/database.php';
$staffQuery = $pdo->prepare('SELECT staff FROM users WHERE uid = ? LIMIT 1');
$staffQuery->execute([$_SESSION['uid']]);
if ((int)$staffQuery->fetchColumn() !== 1) {
	header('Location: ' . $redirect);
	exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST'
	|| empty($_SESSION['csrf_token'])
	|| !hash_equals((string)$_SESSION['csrf_token'], (string)($_POST['csrf_token'] ?? ''))) {
	header('Location: ' . $redirect . '&featured=error');
	exit;
}

$videoId = substr((string)($_POST['video_id'] ?? ''), 0, 20);
$action = (string)($_POST['action'] ?? '');
if ($videoId === '' || !in_array($action, ['feature', 'unfeature'], true)) {
	header('Location: ' . $redirect . '&featured=error');
	exit;
}
$returnVideo = (string)($_POST['return_video'] ?? '');
if (preg_match('/^[A-Za-z0-9_-]{1,20}$/', $returnVideo) && hash_equals($videoId, $returnVideo)) {
	$redirect = '../watch.php?v=' . rawurlencode($returnVideo) . '&tab=managercast';
}

if ($action === 'feature') {
	$featureQuery = $pdo->prepare('INSERT IGNORE INTO picks (video)
		SELECT v.vid FROM videos v
		INNER JOIN users u ON u.uid = v.uid
		WHERE v.vid = ? AND v.privacy = 1 AND v.converted = 1 AND u.termination = 0');
	$featureQuery->execute([$videoId]);
	if ($featureQuery->rowCount() === 1) {
		$status = 'added';
	} else {
		$featuredQuery = $pdo->prepare('SELECT 1 FROM picks WHERE video = ?');
		$featuredQuery->execute([$videoId]);
		$status = $featuredQuery->fetchColumn() ? 'already' : 'unavailable';
	}
} else {
	$unfeatureQuery = $pdo->prepare('DELETE FROM picks WHERE video = ?');
	$unfeatureQuery->execute([$videoId]);
	$status = $unfeatureQuery->rowCount() === 1 ? 'removed' : 'unavailable';
}

header('Location: ' . $redirect . '&featured=' . $status);
exit;
