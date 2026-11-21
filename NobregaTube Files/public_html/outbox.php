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

$unreadQuery = $pdo->prepare('SELECT COUNT(*) FROM messages WHERE receiver = ? AND isRead = 0');
$unreadQuery->execute([$currentUser['uid']]);
$unreadMessages = (int)$unreadQuery->fetchColumn();
if (empty($_SESSION['csrf_token'])) {
	$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}



$composeRecipient = null;
$recipientName = trim((string)($_GET['user'] ?? ''));
if ($recipientName !== '' && preg_match('/^[A-Za-z0-9_]{3,20}$/', $recipientName)) {
	$recipientQuery = $pdo->prepare('SELECT uid, username FROM users
		WHERE username = ? AND termination = 0 AND uid <> ? LIMIT 1');
	$recipientQuery->execute([$recipientName, $currentUser['uid']]);
	$composeRecipient = $recipientQuery->fetch() ?: null;
}

$shareVideoId = trim((string)($_GET['video'] ?? ''));
$shareVideo = null;
$shareRecipients = [];
if ($shareVideoId !== '' && preg_match('/^[A-Za-z0-9_-]{1,20}$/', $shareVideoId)) {
	$shareVideoQuery = $pdo->prepare('SELECT vid, uid, title FROM videos
		WHERE vid = ? AND privacy = 1 AND converted = 1 LIMIT 1');
	$shareVideoQuery->execute([$shareVideoId]);
	$shareVideo = $shareVideoQuery->fetch() ?: null;
	if ($shareVideo) {
		$shareRecipientsQuery = $pdo->prepare('SELECT uid, username FROM users
			WHERE termination = 0 AND uid <> ? ORDER BY username');
		$shareRecipientsQuery->execute([$currentUser['uid']]);
		$shareRecipients = $shareRecipientsQuery->fetchAll();
	}
}

$formError = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
	if (empty($_SESSION['csrf_token'])
		|| !hash_equals((string)$_SESSION['csrf_token'], (string)($_POST['csrf_token'] ?? ''))) {
		http_response_code(403);
		exit('Your form session expired. Refresh the page and try again.');
	}

	$recipientId = trim((string)($_POST['receiver'] ?? ''));
	$subject = trim((string)($_POST['subject'] ?? ''));
	$body = trim((string)($_POST['body'] ?? ''));
	$attachedVideo = $shareVideo ? (string)$shareVideo['vid'] : '';
	if ($shareVideoId !== '' && !$shareVideo) {
		$formError = 'This video is unavailable for sharing.';
	} elseif (!preg_match('/^[A-Za-z0-9_-]{1,20}$/', $recipientId)
		|| strlen($subject) < 3 || strlen($subject) > 75
		|| $body === '' || strlen($body) > 50000) {
		$formError = 'Enter a subject of 3-75 characters and a message of up to 50,000 characters.';
	} else {
		$recipientQuery = $pdo->prepare('SELECT uid, username FROM users
			WHERE uid = ? AND termination = 0 AND uid <> ? LIMIT 1');
		$recipientQuery->execute([$recipientId, $currentUser['uid']]);
		$recipient = $recipientQuery->fetch();
		if (!$recipient) {
			$formError = 'The selected recipient is unavailable.';
		} else {
			$pdo->beginTransaction();
			try {
				$senderQuery = $pdo->prepare('SELECT uid FROM users WHERE uid = ? AND termination = 0 FOR UPDATE');
				$senderQuery->execute([$currentUser['uid']]);
				if (!$senderQuery->fetchColumn()) {
					$pdo->rollBack();
					unset($_SESSION['uid'], $_SESSION['username']);
					header('Location: login.php');
					exit;
				}

				$recentMessages = $pdo->prepare('SELECT COUNT(*) FROM messages
					WHERE sender = ? AND created >= DATE_SUB(NOW(), INTERVAL 15 SECOND)');
				$recentMessages->execute([$currentUser['uid']]);
				if ((int)$recentMessages->fetchColumn() >= 12) {
					$pdo->prepare('UPDATE users SET suspended_until = GREATEST(
						COALESCE(suspended_until, UTC_TIMESTAMP()),
						DATE_ADD(UTC_TIMESTAMP(), INTERVAL 3 HOUR)
					) WHERE uid = ?')->execute([$currentUser['uid']]);
					$pdo->commit();
					header('Location: outbox.php?raid=1');
					exit;
				}

				$insertMessage = $pdo->prepare('INSERT INTO messages (pmid, sender, receiver, subject, attached, body)
					VALUES (?, ?, ?, ?, ?, ?)');
				$insertMessage->execute([
					bin2hex(random_bytes(10)),
					$currentUser['uid'],
					$recipient['uid'],
					$subject,
					$attachedVideo !== '' ? $attachedVideo : null,
					$body,
				]);
				$pdo->commit();
				header('Location: outbox.php?sent=1');
				exit;
			} catch (Throwable $exception) {
				if ($pdo->inTransaction()) {
					$pdo->rollBack();
				}
				throw $exception;
			}
		}
	}
}

$page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$page = $page === false ? 1 : $page;
$perPage = 35;
$countQuery = $pdo->prepare('SELECT COUNT(*) FROM messages WHERE sender = ?');
$countQuery->execute([$currentUser['uid']]);
$messageCount = (int)$countQuery->fetchColumn();
$totalPages = max(1, (int)ceil($messageCount / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$messagesQuery = $pdo->prepare('SELECT m.pmid, m.subject, m.created, m.isRead, u.username
	FROM messages m
	INNER JOIN users u ON u.uid = m.receiver
	WHERE m.sender = ?
	ORDER BY m.created DESC, m.pmid DESC
	LIMIT ' . $perPage . ' OFFSET ' . $offset);
$messagesQuery->execute([$currentUser['uid']]);
$messages = $messagesQuery->fetchAll();
$sentNotice = (string)($_GET['sent'] ?? '') === '1';
$raidNotice = (string)($_GET['raid'] ?? '') === '1';
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html lang="en">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
	<title>NobregaTube - Sent Messages</title>
	<link rel="stylesheet" href="styles2005july.css">
</head>
<body>
	<?php $activeHeaderPage = 'messages'; $showSubNav = true; include __DIR__ . '/includes/header.php'; ?>

	<div class="pageTitle">Sent Messages</div>
	<table width="45%" align="center" cellpadding="5" cellspacing="0" border="0">
		<tbody><tr align="center"><td><a href="my_messages.php">Inbox Messages</a> | <a href="outbox.php" class="bold">Sent Messages</a></td></tr></tbody>
	</table>

	<?php if ($sentNotice): ?><p class="success" align="center">Message sent.</p><?php endif; ?>
	<?php if ($raidNotice): ?><p class="error" align="center">Your account has been suspended for 3 hours for sending messages too quickly.</p><?php endif; ?>
	<?php if ($formError !== ''): ?><p class="error" align="center"><?php echo h($formError); ?></p><?php endif; ?>

	<?php if ($recipientName !== '' || $shareVideoId !== ''): ?>
		<div class="pageTitle"><?php echo $shareVideo ? 'Share Video with a NobregaTube User' : 'Compose Message'; ?></div>
		<?php if ($shareVideoId !== '' && !$shareVideo): ?>
			<p class="error" align="center">This video is unavailable for sharing.</p>
		<?php elseif (!$shareVideo && !$composeRecipient): ?>
			<p class="error" align="center">The selected recipient was not found or is unavailable.</p>
		<?php else: ?>
			<form id="compose-message" method="post" action="outbox.php?<?php echo $shareVideo ? 'video=' . rawurlencode($shareVideo['vid']) : 'user=' . rawurlencode($composeRecipient['username']); ?>">
			<table width="75%" align="center" cellpadding="4" cellspacing="9" border="0" bgcolor="#EEEEEE">
				<tbody>
					<?php if ($shareVideo): ?>
						<tr><td align="right" valign="top"><span class="label">Video:</span></td><td><?php echo h($shareVideo['title']); ?></td></tr>
						<tr><td align="right" valign="top"><span class="label">To:</span></td><td>
							<select name="receiver" required>
								<option value="">Select a NobregaTube user</option>
								<?php foreach ($shareRecipients as $shareRecipient): ?>
									<option value="<?php echo h($shareRecipient['uid']); ?>"<?php echo (string)($_POST['receiver'] ?? '') === (string)$shareRecipient['uid'] ? ' selected' : ''; ?>><?php echo h($shareRecipient['username']); ?></option>
								<?php endforeach; ?>
							</select>
						</td></tr>
					<?php else: ?>
						<tr><td align="right" valign="top"><span class="label">To:</span></td><td><?php echo h($composeRecipient['username']); ?></td></tr>
					<?php endif; ?>
					<tr><td align="right" valign="top"><span class="label">Subject:</span></td><td><input type="text" name="subject" maxlength="75" size="50" value="<?php echo h($_POST['subject'] ?? $_GET['subject'] ?? ($shareVideo ? 'Check out my video: ' . $shareVideo['title'] : '')); ?>"></td></tr>
					<tr><td align="right" valign="top"><span class="label">Message:</span></td><td><textarea name="body" maxlength="50000" cols="66" rows="8"><?php echo h($_POST['body'] ?? ($shareVideo ? 'I thought you might like this video.' : '')); ?></textarea></td></tr>
					<tr><td></td><td>
							<input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
							<?php if ($composeRecipient): ?><input type="hidden" name="receiver" value="<?php echo h($composeRecipient['uid']); ?>"><?php endif; ?>
							<input type="submit" value="Send Message">
					</td></tr>
				</tbody>
			</table>
			</form>
		<?php endif; ?>
	<?php endif; ?>

	<table width="91%" align="center" cellpadding="0" cellspacing="0" border="0" bgcolor="#CCCCCC">
		<tbody>
			<tr>
				<td><img src="img/box_login_tl.gif" width="5" height="5" alt=""></td>
				<td width="100%"><img src="img/pixel.gif" width="1" height="5" alt=""></td>
				<td><img src="img/box_login_tr.gif" width="5" height="5" alt=""></td>
			</tr>
			<tr>
				<td><img src="img/pixel.gif" width="5" height="1" alt=""></td>
				<td>
					<div class="moduleTitleBar">
						<div class="moduleTitle">
							<?php if ($messageCount > 0): ?>
								<div style="float: right; padding: 1px 5px 0px 0px; font-size: 12px;">Messages <?php echo $offset + 1; ?>-<?php echo min($offset + $perPage, $messageCount); ?> of <?php echo $messageCount; ?></div>
							<?php endif; ?>
							Messages // Outbox
						</div>
					</div>
					<table width="100%" cellpadding="3" cellspacing="0" align="center">
						<tbody>
							<tr><td colspan="5" height="10"></td></tr>
							<tr><td width="20">&nbsp;</td><td><b>Subject</b></td><td width="20">&nbsp;</td><td width="120"><b>To</b></td><td width="180"><b>Date</b></td></tr>
							<?php if (!$messages): ?><tr><td colspan="5" align="center">You have no sent messages.</td></tr><?php endif; ?>
							<?php foreach ($messages as $message): ?>
								<tr bgcolor="<?php echo (int)$message['isRead'] === 0 ? '#FFCC66' : '#eeeeee'; ?>">
									<td width="20"><img src="img/mail<?php echo (int)$message['isRead'] === 0 ? '_unread' : ''; ?>.gif" alt="<?php echo (int)$message['isRead'] === 0 ? 'Unread' : 'Read'; ?>"></td>
									<td><a href="out_msg.php?id=<?php echo rawurlencode($message['pmid']); ?>"<?php echo (int)$message['isRead'] === 0 ? ' class="bold"' : ''; ?>><?php echo h($message['subject']); ?></a></td>
									<td width="20">&nbsp;</td>
									<td><a href="profile.php?user=<?php echo rawurlencode($message['username']); ?>"><?php echo h($message['username']); ?></a></td>
									<td><?php echo h(date('l, F j, Y', strtotime($message['created']))); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					<?php if ($totalPages > 1): ?>
						<div style="font-size: 13px; font-weight: bold; color: #444; text-align: right; padding: 5px;">
							Browse Pages:
							<?php $startPage = (int)(floor(($page - 1) / 10) * 10) + 1; $endPage = min($startPage + 9, $totalPages); ?>
							<?php if ($page > 1): ?><a href="outbox.php?page=<?php echo $page - 1; ?>">&lt; Previous</a><?php endif; ?>
							<?php for ($pageNumber = $startPage; $pageNumber <= $endPage; $pageNumber++): ?>
								<?php if ($pageNumber === $page): ?><span style="color: #444; background-color: #FFFFFF; padding: 1px 4px; border: 1px solid #999; margin-right: 5px;"><?php echo $pageNumber; ?></span><?php else: ?><span style="background-color: #CCC; padding: 1px 4px; border: 1px solid #999; margin-right: 5px;"><a href="outbox.php?page=<?php echo $pageNumber; ?>"><?php echo $pageNumber; ?></a></span><?php endif; ?>
							<?php endfor; ?>
							<?php if ($page < $totalPages): ?><a href="outbox.php?page=<?php echo $page + 1; ?>">Next &gt;</a><?php endif; ?>
						</div>
					<?php endif; ?>
				</td>
				<td><img src="img/pixel.gif" width="5" height="1" alt=""></td>
			</tr>
			<tr>
				<td><img src="img/box_login_bl.gif" width="5" height="5" alt=""></td>
				<td><img src="img/pixel.gif" width="1" height="5" alt=""></td>
				<td><img src="img/box_login_br.gif" width="5" height="5" alt=""></td>
			</tr>
		</tbody>
	</table>

	<br>
	<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
