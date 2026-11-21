<?php
require_once __DIR__ . '/includes/sess.php';
require __DIR__ . '/includes/database.php';

if (empty($_SESSION['uid'])) {
	header('Location: login.php');
	exit;
}

$userQuery = $pdo->prepare('SELECT uid, username, staff FROM users WHERE uid = ? LIMIT 1');
$userQuery->execute([$_SESSION['uid']]);
$currentUser = $userQuery->fetch();
if (!$currentUser) {
	unset($_SESSION['uid'], $_SESSION['username']);
	header('Location: login.php');
	exit;
}

$messageId = trim((string)($_GET['id'] ?? ''));
if (!preg_match('/^[A-Za-z0-9_-]{1,20}$/', $messageId)) {
	header('Location: my_messages.php');
	exit;
}

$messageQuery = $pdo->prepare('SELECT m.pmid, m.sender, m.receiver, m.subject, m.body, m.attached, m.created,
	u.username AS sender_name
	FROM messages m
	INNER JOIN users u ON u.uid = m.sender
	WHERE m.pmid = ? AND m.receiver = ?
	LIMIT 1');
$messageQuery->execute([$messageId, $_SESSION['uid']]);
$message = $messageQuery->fetch();
if (!$message) {
	header('Location: my_messages.php');
	exit;
}

$pdo->prepare('UPDATE messages SET isRead = 1 WHERE pmid = ? AND receiver = ?')
	->execute([$messageId, $_SESSION['uid']]);

$unreadQuery = $pdo->prepare('SELECT COUNT(*) FROM messages WHERE receiver = ? AND isRead = 0');
$unreadQuery->execute([$currentUser['uid']]);
$unreadMessages = (int)$unreadQuery->fetchColumn();
if (empty($_SESSION['csrf_token'])) {
	$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}



$messageIdsQuery = $pdo->prepare('SELECT pmid FROM messages WHERE receiver = ? ORDER BY created DESC, pmid DESC LIMIT 1000');
$messageIdsQuery->execute([$currentUser['uid']]);
$messageIds = $messageIdsQuery->fetchAll(PDO::FETCH_COLUMN);
$messagePosition = array_search($messageId, $messageIds, true);
$previousId = $messagePosition !== false && $messagePosition > 0 ? $messageIds[$messagePosition - 1] : null;
$nextId = $messagePosition !== false && $messagePosition < count($messageIds) - 1 ? $messageIds[$messagePosition + 1] : null;

$statsQuery = $pdo->prepare('SELECT
	(SELECT COUNT(*) FROM videos WHERE uid = ? AND privacy = 1 AND converted = 1) AS video_count,
	(SELECT COUNT(*) FROM favorites f INNER JOIN videos v ON v.vid = f.vid WHERE f.uid = ? AND v.privacy = 1 AND v.converted = 1) AS favorite_count');
$statsQuery->execute([$message['sender'], $message['sender']]);
$senderStats = $statsQuery->fetch();
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html lang="en">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
	<title>NobregaTube - Inbox Message</title>
	<link rel="stylesheet" href="styles2005july.css">
</head>
<body>
	<?php $activeHeaderPage = 'messages'; $showSubNav = true; include __DIR__ . '/includes/header.php'; ?>

	<div class="pageTitle">Inbox Messages</div>
	<table width="70%" align="center" cellpadding="5" cellspacing="0" border="0">
		<tbody><tr align="center"><td><a href="my_messages.php" class="bold">Inbox Messages</a> | <a href="outbox.php">Sent Messages</a></td></tr></tbody>
	</table>
	<table width="100%" align="center" cellpadding="1" cellspacing="1" border="0" bgcolor="#EEEEEE">
		<tbody>
			<tr><td width="100%"><img src="img/pixel.gif" width="1" height="5" alt="">
				<?php if ($previousId !== null): ?><a href="read_msg.php?id=<?php echo rawurlencode($previousId); ?>">&lt;&lt; Prev</a><?php endif; ?>
				<?php if ($previousId !== null && $nextId !== null): ?>&nbsp;|&nbsp;<?php endif; ?>
				<?php if ($nextId !== null): ?><a href="read_msg.php?id=<?php echo rawurlencode($nextId); ?>">Next &gt;&gt;</a><?php endif; ?>
			</td></tr>
			<tr><td>
				<table width="75%" cellpadding="4" cellspacing="9" align="center">
					<tbody>
						<tr valign="top"><td align="right" width="90"><span class="label">From:</span></td><td><a href="profile.php?user=<?php echo rawurlencode($message['sender_name']); ?>"><?php echo h($message['sender_name']); ?></a></td></tr>
						<tr><td></td><td><a href="profile.php?user=<?php echo rawurlencode($message['sender_name']); ?>&amp;tab=videos">Videos</a> (<?php echo (int)$senderStats['video_count']; ?>) | <a href="profile.php?user=<?php echo rawurlencode($message['sender_name']); ?>&amp;tab=favorites">Favorites</a> (<?php echo (int)$senderStats['favorite_count']; ?>)</td></tr>
						<tr valign="top"><td align="right"><span class="label">Sent:</span></td><td><?php echo h(date('F j, Y H:i', strtotime($message['created']))); ?></td></tr>
						<tr valign="top"><td align="right"><span class="label">Subject:</span></td><td><?php echo h($message['subject']); ?></td></tr>
						<tr valign="top"><td align="right"><span class="label">Message:</span></td><td><div class="mailMessageArea"><?php echo nl2br(h($message['body'])); ?>
							<?php if (!empty($message['attached'])): ?><p><a href="watch.php?v=<?php echo rawurlencode($message['attached']); ?>">Watch the related video</a></p><?php endif; ?>
						</div></td></tr>
						<tr valign="top"><td></td><td>
							<form method="post" action="f_message.php" onsubmit="return confirm('Delete this message? This cannot be undone.');" style="display:inline;">
								<input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
								<input type="hidden" name="message_id" value="<?php echo h($message['pmid']); ?>">
								<input type="hidden" name="return_to" value="inbox">
								<input type="submit" value="Delete">
							</form>
							<a href="outbox.php?user=<?php echo rawurlencode($message['sender_name']); ?>&amp;subject=<?php echo rawurlencode('Re: ' . $message['subject']); ?>">Reply</a>
						</td></tr>
					</tbody>
				</table>
			</td></tr>
		</tbody>
	</table>
	<br>
	<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
