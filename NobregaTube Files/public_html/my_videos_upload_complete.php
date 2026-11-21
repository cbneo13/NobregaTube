<?php
require_once __DIR__ . '/includes/sess.php';
require __DIR__ . '/includes/database.php';

if (empty($_SESSION['uid'])) {
	header('Location: login.php');
	exit;
}

$currentUser = null;
$unreadMessages = 0;
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

$vid = substr((string)($_GET['v'] ?? ''), 0, 20);
$videoQuery = $pdo->prepare('SELECT vid, title, converted, queue_error FROM videos WHERE vid = ? AND uid = ? LIMIT 1');
$videoQuery->execute([$vid, $_SESSION['uid']]);
$video = $videoQuery->fetch();
if (!$video) {
	header('Location: index.php');
	exit;
}

?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html lang="en">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
	<title>NobregaTube - Your Digital Video Repository</title>
	<link rel="stylesheet" href="styles2005july.css">
</head>
<body>
	<?php $activeHeaderPage = 'upload'; include __DIR__ . '/includes/header_search.php'; ?>

	<div class="pageTitle">Thank You</div>

	<?php if ((int)$video['converted'] === 1): ?>
		<span class="success">Your video was successfully added!</span>
		<p>Your video is ready to watch.</p>
		<p><a href="watch.php?v=<?php echo rawurlencode($video['vid']); ?>">Watch your video</a></p>
	<?php elseif ((int)$video['converted'] === 3): ?>
		<span class="error">Your video could not be processed.</span>
		<p><?php echo h($video['queue_error']); ?></p>
	<?php else: ?>
		<span class="success">Your video was successfully added!</span>
		<p>Your video is currently being processed and will be available to view in a few minutes.</p>
	<?php endif; ?>

	<p>Want to upload more videos? <a href="my_videos_upload.php">Click here</a></p>

	<br>
	<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
