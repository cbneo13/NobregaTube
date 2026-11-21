<?php
require_once __DIR__ . '/includes/sess.php';
require __DIR__ . '/includes/database.php';

$token = trim((string)($_GET['token'] ?? ''));
$confirmed = false;
if (preg_match('/\A[a-f0-9]{64}\z/i', $token)) {
	$confirm = $pdo->prepare('UPDATE users SET email_verified = 1, email_verification_token = NULL,
		email_verification_expires = NULL
		WHERE email_verification_token = ? AND email_verification_expires > UTC_TIMESTAMP()');
	$confirm->execute([hash('sha256', $token)]);
	$confirmed = $confirm->rowCount() === 1;
}

$currentUser = null;
$unreadMessages = 0;
if (!empty($_SESSION['uid'])) {
	$userQuery = $pdo->prepare('SELECT uid, username, staff FROM users WHERE uid = ? LIMIT 1');
	$userQuery->execute([$_SESSION['uid']]);
	$currentUser = $userQuery->fetch();
	if ($currentUser) {
		$messageQuery = $pdo->prepare('SELECT COUNT(*) FROM messages WHERE receiver = ? AND isRead = 0');
		$messageQuery->execute([$currentUser['uid']]);
		$unreadMessages = (int)$messageQuery->fetchColumn();
	} else {
		unset($_SESSION['uid'], $_SESSION['username']);
	}
}
if (empty($_SESSION['csrf_token'])) {
	$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$showSubNav = true;

$message = $confirmed
	? 'Your email address has been confirmed.'
	: 'This confirmation link is invalid or has expired.';
$messageColor = $confirmed ? '#666666' : '#FF0000';
$messageClass = $confirmed ? 'success' : 'error';
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html lang="en">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
	<title>NobregaTube - Email Confirmation</title>
	<link rel="stylesheet" href="styles2005july.css">
</head>
<body>
	<?php include __DIR__ . '/includes/header.php'; ?>
	<div class="pageTitle">Email Confirmation</div>
	<table width="100%" align="center" bgcolor="<?php echo $messageColor; ?>" cellpadding="6" cellspacing="3" border="0">
		<tbody><tr><td align="center" bgcolor="#FFFFFF"><span class="<?php echo $messageClass; ?>"><?php echo h($message); ?></span></td></tr></tbody>
	</table><br>
	<?php if ($confirmed): ?>
		<p><a href="index.php">Continue to NobregaTube</a></p>
	<?php else: ?>
		<p><a href="login.php">Log in to request another confirmation email.</a></p>
	<?php endif; ?>
	<?php include __DIR__ . '/includes/footer.php'; ?>
