<?php
require_once __DIR__ . '/includes/sess.php';
require __DIR__ . '/includes/database.php';
$videoId = substr((string)($_POST['video_id'] ?? ''), 0, 20);
$returnUrl = 'watch.php?v=' . rawurlencode($videoId);
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_SESSION['uid'])) {
	header('Location: login.php');
	exit;
}
if (!hash_equals((string)($_SESSION['csrf_token'] ?? ''), (string)($_POST['csrf_token'] ?? ''))) {
	header('Location: ' . $returnUrl . '&comment=csrf#comment');
	exit;
}
$body = trim((string)($_POST['comment'] ?? ''));
if ($body === '' || strlen($body) > 1000) {
	header('Location: ' . $returnUrl . '&comment=invalid#comment');
	exit;
}
$exists = $pdo->prepare('SELECT v.vid, v.uid AS video_owner, v.title
	FROM videos v
	INNER JOIN users u ON u.uid = v.uid
	WHERE v.vid = ? AND (v.privacy = 1 OR v.uid = ?) AND v.converted = 1 AND v.allow_comments = 1 AND u.termination = 0 LIMIT 1');
$exists->execute([$videoId, $_SESSION['uid']]);
$video = $exists->fetch();
if (!$video) {
	header('Location: watch.php');
	exit;
}

$pdo->beginTransaction();
try {
	$accountQuery = $pdo->prepare('SELECT uid FROM users WHERE uid = ? AND termination = 0 FOR UPDATE');
	$accountQuery->execute([$_SESSION['uid']]);
	if (!$accountQuery->fetchColumn()) {
		$pdo->rollBack();
		header('Location: login.php');
		exit;
	}

	$recentComments = $pdo->prepare('SELECT COUNT(*) FROM comments
		WHERE uid = ? AND post_date >= DATE_SUB(NOW(), INTERVAL 15 SECOND)');
	$recentComments->execute([$_SESSION['uid']]);
	if ((int)$recentComments->fetchColumn() >= 12) {
		$pdo->prepare('UPDATE users SET suspended_until = GREATEST(
			COALESCE(suspended_until, UTC_TIMESTAMP()),
			DATE_ADD(UTC_TIMESTAMP(), INTERVAL 3 HOUR)
		)
			WHERE uid = ?')->execute([$_SESSION['uid']]);
		$pdo->commit();
		header('Location: ' . $returnUrl . '&comment=suspended#comment');
		exit;
	}

	$commentId = bin2hex(random_bytes(10));
	$insert = $pdo->prepare('INSERT INTO comments (cid, vid, uid, body) VALUES (?, ?, ?, ?)');
	$insert->execute([$commentId, $videoId, $_SESSION['uid'], $body]);

	if ((string)$video['video_owner'] !== (string)$_SESSION['uid']) {
		$senderQuery = $pdo->prepare('SELECT username FROM users WHERE uid = ? LIMIT 1');
		$senderQuery->execute([$_SESSION['uid']]);
		$senderUsername = $senderQuery->fetchColumn();
		if ($senderUsername !== false) {
			$subject = $senderUsername . ' commented on your video';
			$messageBody = $senderUsername . ' commented on "' . $video['title'] . '":' . "\n\n"
				. $body . "\n\n"
				. 'Watch your video to view the comment.';
			$messageInsert = $pdo->prepare('INSERT INTO messages (pmid, sender, receiver, subject, attached, body)
				VALUES (?, ?, ?, ?, ?, ?)');
			$messageInsert->execute([
				bin2hex(random_bytes(10)),
				$_SESSION['uid'],
				$video['video_owner'],
				$subject,
				$videoId,
				$messageBody,
			]);
		}
	}
	$pdo->commit();
} catch (Throwable $exception) {
	if ($pdo->inTransaction()) {
		$pdo->rollBack();
	}
	throw $exception;
}
header('Location: ' . $returnUrl . '&comment=added#comment');
exit;
