<?php
require_once __DIR__ . '/includes/sess.php';
require __DIR__ . '/includes/database.php';

$currentUser = null;
$unreadMessages = 0;
if (!empty($_SESSION['uid'])) {
	$userQuery = $pdo->prepare('SELECT uid, username, staff FROM users WHERE uid = ? LIMIT 1');
	$userQuery->execute([$_SESSION['uid']]);
	$currentUser = $userQuery->fetch();
	if ($currentUser) {
		$unreadQuery = $pdo->prepare('SELECT COUNT(*) FROM messages WHERE receiver = ? AND isRead = 0');
		$unreadQuery->execute([$currentUser['uid']]);
		$unreadMessages = (int)$unreadQuery->fetchColumn();
	} else {
		unset($_SESSION['uid'], $_SESSION['username']);
	}
}
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html lang="en">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
	<title>NobregaTube - About Us</title>
	<link rel="stylesheet" href="styles2005july.css">
</head>
<body>
	<?php $activeHeaderPage = ''; $showSubNav = true; include __DIR__ . '/includes/header.php'; ?>
	<div class="formTitle">About Us</div>
	<div class="pageTable">
		<span class="highlight">What is NobregaTube?</span>
		<br><br>
		NobregaTube is a place to share videos with the people who matter to you. With NobregaTube you can:
		<ul>
			<li>Show your favorite videos to the world</li>
			<li>Share videos of your pets, family, and everyday life</li>
			<li>Upload videos from your digital camera or phone</li>
			<li>Choose to share a video publicly or keep it private</li>
			<li>Discover videos and communicate with other members</li>
			<li>... and much, much more!</li>
		</ul>
		<?php if (!$currentUser): ?>
			<br><span class="highlight"><a href="signup.php">Sign up now</a> and open a free account.</span><br><br>
		<?php endif; ?>
		To learn more about our service, please see our <a href="help.php">Help</a> section.<br><br>
		Please feel free to <a href="contact.php">contact us</a>.<br><br><br>
	</div>
	<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
