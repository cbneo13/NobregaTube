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



$perPage = 35;
$page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$page = $page === false ? 1 : $page;
$countQuery = $pdo->prepare('SELECT COUNT(*) FROM messages WHERE receiver = ?');
$countQuery->execute([$currentUser['uid']]);
$messageCount = (int)$countQuery->fetchColumn();
$totalPages = max(1, (int)ceil($messageCount / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$messageQuery = $pdo->prepare('SELECT m.pmid, m.subject, m.created, m.isRead, u.uid AS sender_uid, u.username
	FROM messages m
	INNER JOIN users u ON u.uid = m.sender
	WHERE m.receiver = ?
	ORDER BY m.created DESC, m.pmid DESC
	LIMIT ' . $perPage . ' OFFSET ' . $offset);
$messageQuery->execute([$currentUser['uid']]);
$messages = $messageQuery->fetchAll();
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html lang="en">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
	<title>NobregaTube - Inbox Messages</title>
	<link rel="stylesheet" href="styles2005july.css">
</head>
<body>
	<?php $activeHeaderPage = 'messages'; $showSubNav = true; include __DIR__ . '/includes/header.php'; ?>

	<div class="pageTitle">Inbox Messages</div>
	<table width="45%" align="center" cellpadding="5" cellspacing="0" border="0">
		<tbody><tr align="center"><td><a href="my_messages.php" class="bold">Inbox Messages</a> | <a href="outbox.php">Sent Messages</a></td></tr></tbody>
	</table>

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
							Messages // Inbox
						</div>
					</div>
					<table width="100%" cellpadding="3" cellspacing="0" align="center">
						<tbody>
							<tr><td colspan="5" height="10"></td></tr>
							<tr>
								<td width="20">&nbsp;</td>
								<td><b>Subject</b></td>
								<td width="20">&nbsp;</td>
								<td width="120"><b>From</b></td>
								<td width="180"><b>Date</b></td>
							</tr>
							<?php if (!$messages): ?>
								<tr><td colspan="5" align="center">You have no messages.</td></tr>
							<?php endif; ?>
							<?php foreach ($messages as $message): ?>
								<tr bgcolor="<?php echo (int)$message['isRead'] === 0 ? '#FFCC66' : '#eeeeee'; ?>">
									<td width="20"><img src="img/mail<?php echo (int)$message['isRead'] === 0 ? '_unread' : ''; ?>.gif" alt="<?php echo (int)$message['isRead'] === 0 ? 'Unread' : 'Read'; ?>"></td>
									<td><a href="read_msg.php?id=<?php echo rawurlencode($message['pmid']); ?>"<?php echo (int)$message['isRead'] === 0 ? ' class="bold"' : ''; ?>><?php echo h($message['subject']); ?></a></td>
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
							<?php
							$pagesPerSet = 10;
							$startPage = (int)(floor(($page - 1) / $pagesPerSet) * $pagesPerSet) + 1;
							$endPage = min($startPage + $pagesPerSet - 1, $totalPages);
							?>
							<?php if ($page > 1): ?><a href="my_messages.php?page=<?php echo $page - 1; ?>">&lt; Previous</a><?php endif; ?>
							<?php for ($pageNumber = $startPage; $pageNumber <= $endPage; $pageNumber++): ?>
								<?php if ($pageNumber === $page): ?>
									<span style="color: #444; background-color: #FFFFFF; padding: 1px 4px; border: 1px solid #999; margin-right: 5px;"><?php echo $pageNumber; ?></span>
								<?php else: ?>
									<span style="background-color: #CCC; padding: 1px 4px; border: 1px solid #999; margin-right: 5px;"><a href="my_messages.php?page=<?php echo $pageNumber; ?>"><?php echo $pageNumber; ?></a></span>
								<?php endif; ?>
							<?php endfor; ?>
							<?php if ($page < $totalPages): ?><a href="my_messages.php?page=<?php echo $page + 1; ?>">Next &gt;</a><?php endif; ?>
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
