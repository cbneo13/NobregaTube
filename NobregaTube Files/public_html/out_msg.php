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
	header('Location: outbox.php');
	exit;
}

$messageQuery = $pdo->prepare('SELECT m.pmid, m.sender, m.receiver, m.subject, m.body, m.attached, m.created, m.isRead,
	u.uid AS receiver_uid, u.username AS receiver_name
	FROM messages m
	INNER JOIN users u ON u.uid = m.receiver
	WHERE m.pmid = ? AND m.sender = ?
	LIMIT 1');
$messageQuery->execute([$messageId, $_SESSION['uid']]);
$message = $messageQuery->fetch();
if (!$message) {
	header('Location: outbox.php');
	exit;
}

$unreadQuery = $pdo->prepare('SELECT COUNT(*) FROM messages WHERE receiver = ? AND isRead = 0');
$unreadQuery->execute([$currentUser['uid']]);
$unreadMessages = (int)$unreadQuery->fetchColumn();
if (empty($_SESSION['csrf_token'])) {
	$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}


?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html lang="en">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
	<title>NobregaTube - Sent Message</title>
	<link rel="stylesheet" href="styles2005july.css">
</head>
<body>
	<?php $activeHeaderPage = 'messages'; $showSubNav = true; include __DIR__ . '/includes/header.php'; ?>

	<div class="pageTitle">Sent Messages</div>
	<table width="70%" align="center" cellpadding="5" cellspacing="0" border="0">
		<tbody><tr align="center"><td><a href="my_messages.php">Inbox Messages</a> | <a href="outbox.php" class="bold">Sent Messages</a></td></tr></tbody>
	</table>
	<table width="100%" align="center" cellpadding="1" cellspacing="1" border="0" bgcolor="#EEEEEE">
		<tbody><tr><td>
			<table width="75%" cellpadding="4" cellspacing="9" align="center">
				<tbody>
					<tr valign="top"><td align="right" width="90"><span class="label">To:</span></td><td><a href="profile.php?user=<?php echo rawurlencode($message['receiver_name']); ?>"><?php echo h($message['receiver_name']); ?></a></td></tr>
					<tr valign="top"><td align="right"><span class="label">Sent:</span></td><td><?php echo h(date('F j, Y H:i', strtotime($message['created']))); ?></td></tr>
					<tr valign="top"><td align="right"><span class="label">Subject:</span></td><td><?php echo h($message['subject']); ?></td></tr>
					<tr valign="top"><td align="right"><span class="label">Message:</span></td><td><div class="mailMessageArea"><?php echo nl2br(h($message['body'])); ?>
						<?php if (!empty($message['attached'])): ?><p><a href="watch.php?v=<?php echo rawurlencode($message['attached']); ?>">Watch the related video</a></p><?php endif; ?>
					</div></td></tr>
					<tr valign="top"><td></td><td><form method="post" action="f_message.php" onsubmit="return confirm('Delete this message? This cannot be undone.');">
						<input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
						<input type="hidden" name="message_id" value="<?php echo h($message['pmid']); ?>">
						<input type="hidden" name="return_to" value="outbox">
						<input type="submit" value="Delete">
					</form></td></tr>
				</tbody>
			</table>
		</td></tr></tbody>
	</table>
	<br>
	<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
