<?php
require_once __DIR__ . '/includes/sess.php';
require __DIR__ . '/includes/database.php';
require __DIR__ . '/includes/tag_cloud.php';

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

$latestVideosQuery = $pdo->query('SELECT v.tags, v.views FROM videos v
	INNER JOIN users u ON u.uid = v.uid
	WHERE v.privacy = 1 AND v.converted = 1 AND u.termination = 0
	ORDER BY v.uploaded DESC
	LIMIT 100');
$latestTags = tag_frequency($latestVideosQuery->fetchAll());

$popularVideosQuery = $pdo->query('SELECT v.tags, v.views FROM videos v
	INNER JOIN users u ON u.uid = v.uid
	WHERE v.privacy = 1 AND v.converted = 1 AND u.termination = 0
	ORDER BY v.views DESC, v.uploaded DESC');
$popularTags = tag_frequency($popularVideosQuery->fetchAll(), true);
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html lang="en">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
	<title>NobregaTube - Tags</title>
	<link rel="stylesheet" href="styles2005july.css">
</head>
<body>
	<?php $activeHeaderPage = ''; $showSubNav = true; include __DIR__ . '/includes/header.php'; ?>

	<div class="formTitle">Tags</div>
	<div class="pageTable">
		<div class="tag-list-title">Latest Tags //</div>
		<div class="tag-list">
			<?php if (!$latestTags): ?>
				<p>No tags yet.</p>
			<?php else: ?>
				<?php $latestTagMax = max(array_column($latestTags, 'frequency')); ?>
				<?php foreach ($latestTags as $tagData): ?>
					<a style="font-size: <?php echo tag_font_size($tagData['frequency'], $latestTagMax); ?>px;" href="results.php?search=<?php echo rawurlencode($tagData['tag']); ?>"><?php echo h($tagData['tag']); ?></a> :
				<?php endforeach; ?>
			<?php endif; ?>
		</div>

		<div class="tag-list-title tag-list-title-popular">Most Popular Tags //</div>
		<div class="tag-list">
			<?php if (!$popularTags): ?>
				<p>No tags yet.</p>
			<?php else: ?>
				<?php $popularTagMax = max(array_column($popularTags, 'frequency')); ?>
				<?php foreach ($popularTags as $tagData): ?>
					<a style="font-size: <?php echo tag_font_size($tagData['frequency'], $popularTagMax); ?>px;" href="results.php?search=<?php echo rawurlencode($tagData['tag']); ?>"><?php echo h($tagData['tag']); ?></a> :
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
	</div>

	<br>
	<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
