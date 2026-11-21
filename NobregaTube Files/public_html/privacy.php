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
	<title>NobregaTube - Privacy Policy</title>
	<link rel="stylesheet" href="styles2005july.css">
</head>
<body>
	<?php $activeHeaderPage = ''; $showSubNav = true; include __DIR__ . '/includes/header.php'; ?>
	<div style="padding: 0px 5px 0px 5px;">
		<div class="pageTitle">Privacy Policy</div>
		<div class="pageTable">
			NobregaTube respects your privacy. This policy explains what information may be collected when you use NobregaTube and how it is used to operate and protect the service.

			<br><br><strong>Information you provide</strong><br>
			When you create or update an account, NobregaTube stores information such as your username, e-mail address, and optional profile details. Videos, comments, favorites, messages, and support requests you submit are stored so the related features can work.

			<br><br><strong>Technical information</strong><br>
			NobregaTube may record technical information needed to operate and secure the site, such as your IP address for account security and abuse prevention, pages requested, and basic session data. The site uses session cookies to keep you signed in and support site features.

			<br><br><strong>How information is used</strong><br>
			Information is used to provide accounts, video hosting and discovery, internal messaging, support, and moderation; to diagnose technical problems; and to protect NobregaTube and its users. NobregaTube does not send real e-mail through its messaging or support features.

			<br><br><strong>Videos and messages</strong><br>
			Public videos and information you choose to display publicly may be visible to visitors. Public videos may also appear in video lists embedded on other websites. Private video access is controlled by NobregaTube's privacy settings. Internal messages are available to their sender and recipient, and authorized staff may access information when needed to address abuse or support requests.

			<br><br><strong>Sharing and retention</strong><br>
			NobregaTube does not sell personal account information. Information may be disclosed when required by law or when reasonably necessary to protect users, enforce the Terms of Use, investigate abuse, or maintain the service. Data may remain in backups or records for a limited time after changes or deletion.

			<br><br><strong>Security</strong><br>
			NobregaTube uses safeguards intended to protect stored information, but no internet service can guarantee absolute security. Use a unique password and do not send sensitive information through videos or messages.

			<br><br><strong>Children</strong><br>
			NobregaTube accounts are intended for people at least 13 years old. Do not create an account if you are under 13.

			<br><br><strong>Changes and contact</strong><br>
			This policy may be updated as the service changes. Questions about privacy can be sent through our <a href="contact.php">Contact Us</a> page.
			<br><br>
		</div>
	</div>
	<br>
	<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
