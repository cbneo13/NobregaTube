<?php
require_once __DIR__ . '/../includes/sess.php';

if (empty($_SESSION['uid'])) {
	header('Location: ../login.php');
	exit;
}

require __DIR__ . '/../includes/database.php';
$staffQuery = $pdo->prepare('SELECT uid, username, staff FROM users WHERE uid = ? LIMIT 1');
$staffQuery->execute([$_SESSION['uid']]);
$currentUser = $staffQuery->fetch();
if (!$currentUser) {
	unset($_SESSION['uid'], $_SESSION['username']);
	header('Location: ../login.php');
	exit;
}
if ((int)$currentUser['staff'] !== 1) {
	header('Location: ../index.php');
	exit;
}

$unreadQuery = $pdo->prepare('SELECT COUNT(*) FROM messages WHERE receiver = ? AND isRead = 0');
$unreadQuery->execute([$currentUser['uid']]);
$unreadMessages = (int)$unreadQuery->fetchColumn();
if (empty($_SESSION['csrf_token'])) {
	$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}



$topics = [
	1 => 'Product Question',
	2 => 'Business Inquiry',
	3 => 'Marketing and Advertising Inquiry',
	4 => 'Developer Question',
	5 => 'Press Inquiry',
	6 => 'Job Inquiry',
	7 => 'Other',
];
$ticketId = filter_var($_POST['ticket'] ?? $_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$ticketId = $ticketId === false ? null : $ticketId;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
	if (empty($_SESSION['csrf_token'])
		|| !hash_equals((string)$_SESSION['csrf_token'], (string)($_POST['csrf_token'] ?? ''))) {
		http_response_code(403);
		exit('Your form session expired. Refresh the page and try again.');
	}
	if ($ticketId === null) {
		header('Location: contact_vault.php?result=error');
		exit;
	}

	$action = (string)($_POST['action'] ?? '');
	if ($action === 'reopen') {
		$reopenQuery = $pdo->prepare('UPDATE contact_tickets SET resolved = 0, resolved_at = NULL, resolved_by = NULL
			WHERE ticket = ? AND resolved = 1');
		$reopenQuery->execute([$ticketId]);
		header('Location: contact_vault.php?id=' . $ticketId . '&status=open&result=' . ($reopenQuery->rowCount() === 1 ? 'reopened' : 'unchanged'));
		exit;
	}
	if ($action !== 'resolve') {
		header('Location: contact_vault.php?id=' . $ticketId . '&result=error');
		exit;
	}

	$solution = trim((string)($_POST['solution'] ?? ''));
	if ($solution === '' || strlen($solution) > 350000) {
		header('Location: contact_vault.php?id=' . $ticketId . '&result=invalid');
		exit;
	}

	$pdo->beginTransaction();
	try {
		$ticketQuery = $pdo->prepare('SELECT account_uid, sender, subject FROM contact_tickets WHERE ticket = ? AND resolved = 0 FOR UPDATE');
		$ticketQuery->execute([$ticketId]);
		$ticket = $ticketQuery->fetch();
		if (!$ticket) {
			$pdo->rollBack();
			header('Location: contact_vault.php?id=' . $ticketId . '&result=unchanged');
			exit;
		}

		$resolveQuery = $pdo->prepare('UPDATE contact_tickets SET resolved = 1, solution = ?, resolved_at = NOW(), resolved_by = ?
			WHERE ticket = ? AND resolved = 0');
		$resolveQuery->execute([$solution, $currentUser['uid'], $ticketId]);
		if ($resolveQuery->rowCount() !== 1) {
			throw new RuntimeException('The contact request could not be resolved.');
		}

		$topic = $topics[(int)$ticket['subject']] ?? 'Other';
		if (!empty($ticket['account_uid'])) {
			$accountQuery = $pdo->prepare('SELECT uid FROM users WHERE uid = ? AND termination = 0 LIMIT 1');
			$accountQuery->execute([$ticket['account_uid']]);
			$accountUid = $accountQuery->fetchColumn();
			if ($accountUid !== false) {
				$message = $pdo->prepare('INSERT INTO messages (pmid, sender, receiver, subject, body)
					VALUES (?, ?, ?, ?, ?)');
				$message->execute([
					bin2hex(random_bytes(10)),
					$currentUser['uid'],
					$accountUid,
					'Support reply: ' . $topic,
					$solution,
				]);
			}
		}

		try {
			$mail = mailer();
			$mail->addAddress((string)$ticket['sender']);
			$mail->isHTML(true);
			$mail->Subject = 'Ticket #' . $ticketId . ' Solved (' . $topic . ')';
			$mail->Body = 'We have solved your issue.<p><strong>Solution:</strong></p>'
				. '<p>' . nl2br(h($solution)) . '</p>'
				. '<p>Thank you for using NobregaTube,<br>The NobregaTube Team</p>';
			$mail->AltBody = "We have solved your issue.\n\nSolution:\n"
				. $solution . "\n\nThank you for using NobregaTube,\nThe NobregaTube Team";
			$mail->send();
		} catch (Throwable $mailException) {
			error_log('NobregaTube could not email ticket #' . $ticketId . ': ' . $mailException->getMessage());
			$pdo->rollBack();
			header('Location: contact_vault.php?id=' . $ticketId . '&result=email_error');
			exit;
		}

		$pdo->commit();
	} catch (Throwable $exception) {
		if ($pdo->inTransaction()) {
			$pdo->rollBack();
		}
		throw $exception;
	}
	header('Location: contact_vault.php?id=' . $ticketId . '&status=resolved&result=resolved');
	exit;
}

$status = (string)($_GET['status'] ?? 'open');
if (!in_array($status, ['open', 'resolved', 'all'], true)) {
	$status = 'open';
}
$ticket = null;
if ($ticketId !== null) {
	$ticketQuery = $pdo->prepare('SELECT t.*, u.username AS account_name, r.username AS resolver_name
		FROM contact_tickets t
		LEFT JOIN users u ON u.uid = t.account_uid
		LEFT JOIN users r ON r.uid = t.resolved_by
		WHERE t.ticket = ? LIMIT 1');
	$ticketQuery->execute([$ticketId]);
	$ticket = $ticketQuery->fetch() ?: null;
}

$filterSql = $status === 'all' ? '' : ' WHERE resolved = ' . ($status === 'resolved' ? '1' : '0');
$countQuery = $pdo->query('SELECT COUNT(*) FROM contact_tickets' . $filterSql);
$ticketCount = (int)$countQuery->fetchColumn();
$ticketsQuery = $pdo->query('SELECT ticket, sender, subject, submitted, resolved
	FROM contact_tickets' . $filterSql . '
	ORDER BY resolved ASC, submitted DESC
	LIMIT 100');
$tickets = $ticketsQuery->fetchAll();
$result = (string)($_GET['result'] ?? '');
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html lang="en">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
	<title>ManagerFilm - Contact Vault</title>
	<link rel="stylesheet" href="../styles2005july.css">
	<style>
		.managercast-bar { width: 90%; margin: 20px auto; border: 1px solid #999; background: #eeeedd; }
		.managercast-bar td { padding: 9px; text-align: center; font-weight: bold; }
		.managercast-bar .managercast-brand { background: #D5E5F5; font-size: 20px; }
		.managercast-tabs { width: 90%; margin: 0px auto 20px auto; border-bottom: 1px solid #999; }
		.managercast-tabs td { padding: 8px 12px; background: #EEEEEE; font-weight: bold; }
		.managercast-tabs .managercast-tab-active { background: #D5E5F5; }
		.managercast-section { width: 90%; margin: 20px auto; }
		.contact-ticket-meta { color: #555; margin-bottom: 8px; }
	</style>
</head>
<body>
	<?php $sitePrefix = '../'; $activeHeaderPage = ''; $showSubNav = true; include __DIR__ . '/../includes/header.php'; ?>

	<div class="pageTitle">ManagerFilm</div>
	<table class="managercast-bar" cellpadding="0" cellspacing="0" border="0">
		<tbody><tr>
			<td class="managercast-brand">ManagerFilm</td>
			<td><a href="index.php">Home</a></td>
			<td><a href="../browse.php">Browse Videos</a></td>
			<td>Signed in as <?php echo h($currentUser['username']); ?></td>
		</tr></tbody>
	</table>
	<table class="managercast-tabs" cellpadding="0" cellspacing="0" border="0">
		<tbody><tr>
			<td><a href="index.php">Featured Videos</a></td>
			<td><a href="message.php">Notice</a></td>
			<td><a href="videos_remove.php">Videos Remove</a></td>
			<td><a href="user_ban.php">User Ban</a></td>
			<td><a href="user_suspend.php">Suspend User</a></td>
			<td class="managercast-tab-active">Contact Vault</td>
		</tr></tbody>
	</table>

	<?php if ($result === 'resolved'): ?><p class="success" align="center">Request resolved. A reply was emailed to the sender<?php if (!empty($ticket['account_uid'])): ?> and delivered to My Messages<?php endif; ?>.</p>
	<?php elseif ($result === 'reopened'): ?><p class="success" align="center">Request reopened.</p>
	<?php elseif ($result === 'invalid'): ?><p class="error" align="center">Enter a reply before resolving the request.</p>
	<?php elseif ($result === 'email_error'): ?><p class="error" align="center">The reply email could not be sent. The request is still open; check the email address and SMTP settings, then try again.</p>
	<?php elseif ($result === 'unchanged'): ?><p class="highlight" align="center">This request has already changed.</p>
	<?php elseif ($result === 'error'): ?><p class="error" align="center">The request action could not be completed.</p><?php endif; ?>

	<div class="managercast-section">
		<div class="tableSubTitle">Contact Vault // <?php echo ucfirst($status); ?> Requests (<?php echo $ticketCount; ?>)</div>
		<p>
			<a href="contact_vault.php?status=open">Open</a> |
			<a href="contact_vault.php?status=resolved">Resolved</a> |
			<a href="contact_vault.php?status=all">All Requests</a>
		</p>

		<?php if ($ticketId !== null): ?>
			<?php if (!$ticket): ?>
				<div class="moduleEntry">Contact request not found.</div>
			<?php else: ?>
				<div class="moduleEntry">
					<div class="moduleEntryTitle">Request #<?php echo (int)$ticket['ticket']; ?> // <?php echo h($topics[(int)$ticket['subject']] ?? 'Other'); ?></div>
					<div class="contact-ticket-meta">
						From: <?php echo h($ticket['sender']); ?>
						<?php if ($ticket['account_name']): ?> | Account: <a href="../profile.php?user=<?php echo rawurlencode($ticket['account_name']); ?>"><?php echo h($ticket['account_name']); ?></a><?php endif; ?>
						| Submitted: <?php echo h(date('F j, Y H:i', strtotime($ticket['submitted']))); ?>
						| Status: <?php echo (int)$ticket['resolved'] === 1 ? 'Resolved' : 'Open'; ?>
					</div>
					<div class="mailMessageArea"><?php echo nl2br(h($ticket['message'])); ?></div>
					<?php if (!empty($ticket['solution'])): ?>
						<p><strong>Previous response<?php if ($ticket['resolver_name']): ?> by <?php echo h($ticket['resolver_name']); ?><?php endif; ?>:</strong></p>
						<div class="mailMessageArea"><?php echo nl2br(h($ticket['solution'])); ?></div>
					<?php endif; ?>
					<?php if ((int)$ticket['resolved'] === 0): ?>
						<form method="post" action="contact_vault.php?id=<?php echo (int)$ticket['ticket']; ?>">
							<input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
							<input type="hidden" name="ticket" value="<?php echo (int)$ticket['ticket']; ?>">
							<label for="solution"><strong>Reply / resolution:</strong></label><br>
							<textarea id="solution" name="solution" cols="75" rows="7" maxlength="350000" required></textarea><br>
							<input type="hidden" name="action" value="resolve">
							<input type="submit" value="Resolve Request">
							<?php if (!$ticket['account_uid']): ?><span>This request is not linked to an account; the reply will be sent by email only.</span><?php endif; ?>
						</form>
					<?php else: ?>
						<form method="post" action="contact_vault.php?id=<?php echo (int)$ticket['ticket']; ?>">
							<input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
							<input type="hidden" name="ticket" value="<?php echo (int)$ticket['ticket']; ?>">
							<input type="hidden" name="action" value="reopen">
							<input type="submit" value="Reopen Request">
						</form>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		<?php endif; ?>

		<?php if (!$tickets): ?>
			<div class="moduleEntry">There are no contact requests in this view.</div>
		<?php endif; ?>
		<?php foreach ($tickets as $contactTicket): ?>
			<div class="moduleEntry">
				<div class="moduleEntryTitle">
					<a href="contact_vault.php?id=<?php echo (int)$contactTicket['ticket']; ?>&amp;status=<?php echo rawurlencode($status); ?>">
						#<?php echo (int)$contactTicket['ticket']; ?> - <?php echo h($topics[(int)$contactTicket['subject']] ?? 'Other'); ?>
					</a>
				</div>
				<div class="moduleEntryDetails">
					<?php echo h($contactTicket['sender']); ?> |
					<?php echo h(date('F j, Y H:i', strtotime($contactTicket['submitted']))); ?> |
					<?php echo (int)$contactTicket['resolved'] === 1 ? 'Resolved' : 'Open'; ?>
				</div>
			</div>
		<?php endforeach; ?>
	</div>
	<br>
	<?php $footerPrefix = '../'; include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
