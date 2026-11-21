<?php
require_once __DIR__ . '/includes/sess.php';
require __DIR__ . '/includes/database.php';

$currentUser = null;
$unreadMessages = 0;
if (!empty($_SESSION['uid'])) {
	$userQuery = $pdo->prepare('SELECT uid, username, email, staff FROM users WHERE uid = ? AND termination = 0 LIMIT 1');
	$userQuery->execute([$_SESSION['uid']]);
	$currentUser = $userQuery->fetch();
	if (!$currentUser) {
		unset($_SESSION['uid'], $_SESSION['username']);
	} else {
		$unreadQuery = $pdo->prepare('SELECT COUNT(*) FROM messages WHERE receiver = ? AND isRead = 0');
		$unreadQuery->execute([$currentUser['uid']]);
		$unreadMessages = (int)$unreadQuery->fetchColumn();
	}
}

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
$errors = [];
$topic = 0;
$email = (string)($currentUser['email'] ?? '');
$body = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
	if (!hash_equals((string)$_SESSION['csrf_token'], (string)($_POST['csrf_token'] ?? ''))) {
		http_response_code(403);
		exit('Your form session expired. Refresh the page and try again.');
	}

	$email = trim((string)($_POST['field_contact_email'] ?? ''));
	$topic = filter_var($_POST['field_contact_subject'] ?? null, FILTER_VALIDATE_INT);
	$body = trim((string)($_POST['field_contact_message'] ?? ''));

	if ($email === '' || strlen($email) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
		$errors[] = $email === '' ? 'Please enter an email.' : 'Please enter a valid email.';
	}
	if ($topic === false || !isset($topics[$topic])) {
		$errors[] = 'What is this about?';
	}
	$wordCount = count(array_unique(explode(' ', strtolower($body))));
	if ($wordCount < 8) {
		$errors[] = 'Please provide enough details so we can properly process your e-mail';
	} elseif ($wordCount > 620) {
		$errors[] = 'Too much words.';
	}

	if (!$errors) {
		$cooldownQuery = $pdo->prepare('SELECT COUNT(*) FROM contact_tickets
			WHERE sender = ? AND submitted > DATE_SUB(NOW(), INTERVAL 3 HOUR)');
		$cooldownQuery->execute([$email]);
		if ((int)$cooldownQuery->fetchColumn() > 2) {
			$errors[] = 'Are you sure you meant to send that 2 times?';
		}
	}

	if (!$errors) {
		$accountUid = null;
		if ($currentUser && strcasecmp($email, (string)$currentUser['email']) === 0) {
			$accountUid = $currentUser['uid'];
		}
		$insert = $pdo->prepare('INSERT INTO contact_tickets (account_uid, sender, subject, message)
			VALUES (?, ?, ?, ?)');
		$insert->execute([$accountUid, $email, $topic, $body]);
		header('Location: contact.php?sent=1');
		exit;
	}
}
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html lang="en">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
	<title>NobregaTube - Contact Us</title>
	<link rel="stylesheet" href="styles2005july.css">
</head>
<body>
	<?php $activeHeaderPage = ''; $showSubNav = true; include __DIR__ . '/includes/header.php'; ?>

	<div style="padding: 0px 5px 0px 5px;">
		<div class="pageTitle">Contact Us</div>
		<div class="pageTable">
			<div class="pageIntro">NobregaTube is located in Salvador, Bahia, Brazil.<br><br>If you have questions or comments for us, please fill out the form below.</div>
			<?php if ((string)($_GET['sent'] ?? '') === '1'): ?>
				<p class="success">Thank you for your message.</p>
			<?php endif; ?>
			<?php foreach ($errors as $error): ?>
				<table width="100%" align="center" bgcolor="#FF0000" cellpadding="6" cellspacing="3" border="0">
					<tbody><tr><td align="center" bgcolor="#FFFFFF"><span class="error"><?php echo h($error); ?></span></td></tr></tbody>
				</table><br>
			<?php endforeach; ?>
			<table width="100%" cellpadding="5" cellspacing="0" border="0">
				<form method="post" action="contact.php">
					<input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
					<input type="hidden" name="field_command" value="contact_submit">
					<tbody>
						<tr>
							<td width="200" align="right"><span class="label">Your Email:</span></td>
							<td><input type="text" size="30" maxlength="60" name="field_contact_email" value="<?php echo h($email); ?>"></td>
						</tr>
						<tr>
							<td align="right"><span class="label">Subject:</span></td>
							<td>
								<select name="field_contact_subject">
									<option value="0">---</option>
									<?php foreach ($topics as $topicId => $topicName): ?>
										<option value="<?php echo $topicId; ?>"<?php echo (int)$topic === $topicId ? ' selected' : ''; ?>><?php echo h($topicName); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
						<tr>
							<td align="right" valign="top"><span class="label">Message:</span></td>
							<td><textarea name="field_contact_message" cols="40" rows="4"><?php echo h($body); ?></textarea></td>
						</tr>
						<tr>
							<td>&nbsp;</td>
							<td><input type="submit" value="Contact Now"></td>
						</tr>
					</tbody>
				</form>
			</table>
		</div>
	</div>
	<br>
	<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
