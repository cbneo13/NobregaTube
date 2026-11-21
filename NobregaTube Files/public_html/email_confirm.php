<?php
require_once __DIR__ . '/includes/sess.php';
require __DIR__ . '/includes/database.php';

if (empty($_SESSION['uid'])) {
	header('Location: login.php');
	exit;
}

$userQuery = $pdo->prepare('SELECT uid, username, email, email_verified, staff FROM users WHERE uid = ? LIMIT 1');
$userQuery->execute([$_SESSION['uid']]);
$user = $userQuery->fetch();
if (!$user) {
	unset($_SESSION['uid'], $_SESSION['username']);
	header('Location: login.php');
	exit;
}
$sendFailed = !empty($_SESSION['email_verification_send_failed']);
unset($_SESSION['email_verification_send_failed']);
if ((int)$user['email_verified'] === 1 && !$sendFailed) {
	header('Location: index.php');
	exit;
}

$verificationOn = email_verification_enabled();
if (empty($_SESSION['csrf_token'])) {
	$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$errors = [];
$sent = !empty($_GET['sent']);
$email = (string)$user['email'];
$notice = '';
$noticeColor = '#666666';
$noticeClass = 'success';

if ($sendFailed) {
	$notice = 'The confirmation email could not be sent. Check your email settings or try again.';
	$noticeColor = '#FF0000';
	$noticeClass = 'error';
} elseif ($sent) {
	$notice = 'A confirmation email has been sent. Check your inbox and spam folder.';
} elseif (!$verificationOn) {
	$notice = 'Email confirmation is turned off. You can keep using NobregaTube.';
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
	$email = trim((string)($_POST['email'] ?? ''));
	if (!hash_equals((string)$_SESSION['csrf_token'], (string)($_POST['csrf_token'] ?? ''))) {
		$errors[] = 'Your form session expired. Please refresh the page and try again.';
	} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) {
		$errors[] = 'Enter a valid email address.';
	} elseif (!$verificationOn) {
		$errors[] = 'Email verification is turned off. You can keep using your account.';
	} else {
		$check = $pdo->prepare('SELECT uid FROM users WHERE email = ? AND uid <> ? LIMIT 1');
		$check->execute([$email, $user['uid']]);
		if ($check->fetch()) {
			$errors[] = 'Somebody is already using this email address.';
		} elseif (send_verification_email($pdo, (string)$user['uid'], (string)$user['username'], $email)) {
			header('Location: email_confirm.php?sent=1');
			exit;
		} else {
			$_SESSION['email_verification_send_failed'] = true;
			header('Location: email_confirm.php');
			exit;
		}
	}
}

$currentUser = ['username' => $user['username'], 'staff' => (int)$user['staff']];
$showSubNav = true;
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html lang="en">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
	<title>NobregaTube - Confirm Your Email</title>
	<link rel="stylesheet" href="styles2005july.css">
</head>
<body>
	<?php include __DIR__ . '/includes/header.php'; ?>
	<div class="pageTitle">Please Confirm Your Email</div>
	<?php if ($notice !== ''): ?>
		<table width="100%" align="center" bgcolor="<?php echo $noticeColor; ?>" cellpadding="6" cellspacing="3" border="0">
			<tbody><tr><td align="center" bgcolor="#FFFFFF"><span class="<?php echo $noticeClass; ?>"><?php echo h($notice); ?></span></td></tr></tbody>
		</table><br>
	<?php endif; ?>
	<?php foreach ($errors as $error): ?>
		<table width="100%" align="center" bgcolor="#FF0000" cellpadding="6" cellspacing="3" border="0">
			<tbody><tr><td align="center" bgcolor="#FFFFFF"><span class="error"><?php echo h($error); ?></span></td></tr></tbody>
		</table><br>
	<?php endforeach; ?>
	<?php if ($verificationOn && (int)$user['email_verified'] !== 1): ?>
		<form method="post" action="email_confirm.php">
			<input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
			<table width="100%" cellpadding="5" cellspacing="0" border="0">
				<tbody>
					<tr>
						<td width="200" align="right"><span class="label">Send a confirmation email to:</span></td>
						<td><input type="text" size="30" maxlength="100" name="email" value="<?php echo h($email); ?>"></td>
					</tr>
					<tr><td>&nbsp;</td><td><input type="submit" value="Send Email"></td></tr>
				</tbody>
			</table>
		</form>
	<?php elseif ((int)$user['email_verified'] === 1 && !$sendFailed): ?>
		<p><a href="index.php">Return to NobregaTube</a></p>
	<?php endif; ?>
	<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
