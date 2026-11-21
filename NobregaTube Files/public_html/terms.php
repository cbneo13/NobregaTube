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
	<title>NobregaTube - Terms of Use</title>
	<link rel="stylesheet" href="styles2005july.css">
</head>
<body>
	<?php $activeHeaderPage = ''; $showSubNav = true; include __DIR__ . '/includes/header.php'; ?>
	<div style="padding: 0px 5px 0px 5px;">
		<div class="pageTitle">Terms of Use</div>
		<div class="pageTable">
			<strong>1. Your acceptance</strong><br>
			By using NobregaTube, you agree to these Terms of Use and our <a href="privacy.php">Privacy Policy</a>. If you do not agree, do not use the service.

			<br><br><strong>2. Using NobregaTube</strong><br>
			NobregaTube is provided for sharing and discovering videos. You may use the service only in ways permitted by law and these terms. Do not attempt to disrupt, misuse, or gain unauthorized access to the site, accounts, or content.

			<br><br><strong>3. Accounts</strong><br>
			Keep your sign-in details secure and make sure information you provide is accurate. You are responsible for activity carried out through your account. You must be at least 13 years old to create an account.

			<br><br><strong>4. Videos and other submissions</strong><br>
			You are responsible for content you upload or send. You must own or have permission to share it, and you must have any necessary permissions from identifiable people shown in it. Do not upload unlawful, threatening, harassing, defamatory, obscene, infringing, or otherwise prohibited material, or content that violates another person's rights.

			<br><br>By uploading content, you give NobregaTube permission to store, process, and display it as needed to provide the service and according to the privacy and visibility settings you choose. You retain responsibility for your content and may remove your uploads through My Videos.

			<br><br><strong>5. Public and private videos</strong><br>
			Public videos may be viewed by other visitors and may appear in video lists embedded on other websites. Private videos are limited by the access controls shown by NobregaTube. Do not share sensitive information in a video or assume that online content can never be copied by someone who can view it.

			<br><br><strong>6. Moderation and availability</strong><br>
			NobregaTube may restrict or remove content or accounts that violate these terms or threaten the service or its users. The service and its features may change or become unavailable.

			<br><br><strong>7. Third-party sites</strong><br>
			NobregaTube may link to third-party websites. NobregaTube does not control those sites and is not responsible for their content, terms, or privacy practices.

			<br><br><strong>8. Changes to these terms</strong><br>
			These terms may be updated as the service changes. Continued use of NobregaTube after updated terms are posted means you accept the revised terms.

			<br><br><strong>9. Contact</strong><br>
			If you have a question about these terms, please <a href="contact.php">contact us</a>.
			<br><br>
		</div>
	</div>
	<br>
	<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
