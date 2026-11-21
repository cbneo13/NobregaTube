<?php
require_once __DIR__ . '/../includes/sess.php';

if (empty($_SESSION['uid'])) {
	header('Location: ../login.php');
	exit;
}

require __DIR__ . '/../includes/database.php';
$currentUserQuery = $pdo->prepare('SELECT uid, username, staff FROM users WHERE uid = ? LIMIT 1');
$currentUserQuery->execute([$_SESSION['uid']]);
$currentUser = $currentUserQuery->fetch();
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



$page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$page = $page === false ? 1 : $page;
$perPage = 20;
$featuredQuery = $pdo->query('SELECT v.vid, v.title, v.uploaded, u.username
	FROM picks p
	INNER JOIN videos v ON v.vid = p.video
	INNER JOIN users u ON u.uid = v.uid
	WHERE v.privacy = 1 AND v.converted = 1 AND u.termination = 0
	ORDER BY p.featured DESC');
$featuredVideos = $featuredQuery->fetchAll();

$countQuery = $pdo->query('SELECT COUNT(*) FROM videos v
	INNER JOIN users u ON u.uid = v.uid
	WHERE v.privacy = 1 AND v.converted = 1 AND u.termination = 0
	AND NOT EXISTS (SELECT 1 FROM picks p WHERE p.video = v.vid)');
$availableCount = (int)$countQuery->fetchColumn();
$totalPages = max(1, (int)ceil($availableCount / $perPage));
if ($page > $totalPages) {
	$page = $totalPages;
}
$offset = ($page - 1) * $perPage;

$availableQuery = $pdo->query('SELECT v.vid, v.title, v.uploaded, v.views, u.username
	FROM videos v
	INNER JOIN users u ON u.uid = v.uid
	WHERE v.privacy = 1 AND v.converted = 1 AND u.termination = 0
	AND NOT EXISTS (SELECT 1 FROM picks p WHERE p.video = v.vid)
	ORDER BY v.uploaded DESC
	LIMIT ' . $perPage . ' OFFSET ' . $offset);
$availableVideos = $availableQuery->fetchAll();
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html lang="en">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
	<title>ManagerFilm</title>
	<link rel="stylesheet" href="../styles2005july.css">
	<style>
		.managercast-bar { width: 80%; margin: 20px auto; border: 1px solid #999; background: #eeeedd; }
		.managercast-bar td { padding: 9px; text-align: center; font-weight: bold; }
		.managercast-bar .managercast-brand { background: #D5E5F5; font-size: 20px; }
		.managercast-tabs { width: 80%; margin: 0px auto 20px auto; border-bottom: 1px solid #999; }
		.managercast-tabs td { padding: 8px 12px; background: #EEEEEE; font-weight: bold; }
		.managercast-tabs .managercast-tab-active { background: #D5E5F5; }
		.managercast-tabs .managercast-tab-active a { font-weight: bold; }
		.managercast-section { width: 80%; margin: 20px auto; }
		.managercast-actions { white-space: nowrap; text-align: right; }
	</style>
</head>
<body>
	<?php $sitePrefix = '../'; $activeHeaderPage = ''; $showSubNav = true; include __DIR__ . '/../includes/header.php'; ?>

	<div class="pageTitle">ManagerFilm</div>
	<table class="managercast-bar" cellpadding="0" cellspacing="0" border="0">
		<tbody><tr>
			<td class="managercast-brand">ManagerFilm</td>
			<td><a href="../index.php">Home</a></td>
			<td><a href="../browse.php">Browse Videos</a></td>
			<td>Signed in as <?php echo h($currentUser['username']); ?></td>
		</tr></tbody>
	</table>
	<table class="managercast-tabs" cellpadding="0" cellspacing="0" border="0">
		<tbody><tr>
			<td class="managercast-tab-active">Featured Videos</td>
			<td><a href="message.php">Notice</a></td>
			<td><a href="videos_remove.php">Videos Remove</a></td>
			<td><a href="user_ban.php">User Ban</a></td>
			<td><a href="user_suspend.php">Suspend User</a></td>
			<td><a href="contact_vault.php">Contact Vault</a></td>
		</tr></tbody>
	</table>

	<?php if (($_GET['featured'] ?? '') === 'added'): ?>
		<p class="success">Featured!</p>
	<?php elseif (($_GET['featured'] ?? '') === 'removed'): ?>
		<p class="success">Unfeatured!</p>
	<?php elseif (($_GET['featured'] ?? '') === 'already'): ?>
		<p class="highlight">The video was already featured.</p>
	<?php elseif (($_GET['featured'] ?? '') === 'unavailable'): ?>
		<p class="highlight">This video is unavailable for featuring.</p>
	<?php elseif (($_GET['featured'] ?? '') === 'error'): ?>
		<p class="error">The featured video action could not be completed.</p>
	<?php endif; ?>

	<div class="managercast-section">
		<div class="tableSubTitle">Featured Videos (<?php echo count($featuredVideos); ?>)</div>
		<?php if (!$featuredVideos): ?>
			<div class="moduleEntry">There are no featured videos.</div>
		<?php endif; ?>
		<?php foreach ($featuredVideos as $video): ?>
			<div class="moduleEntry">
				<table width="100%" cellpadding="0" cellspacing="0" border="0">
					<tbody><tr valign="top">
						<td width="140"><a href="../watch.php?v=<?php echo rawurlencode($video['vid']); ?>"><img src="../get_still.php?video_id=<?php echo rawurlencode($video['vid']); ?>" class="moduleEntryThumb" width="120" height="90" alt="<?php echo h($video['title']); ?>"></a></td>
						<td width="100%">
							<div class="moduleEntryTitle"><a href="../watch.php?v=<?php echo rawurlencode($video['vid']); ?>"><?php echo h($video['title']); ?></a></div>
							<div class="moduleEntryDetails">by <a href="../profile.php?user=<?php echo rawurlencode($video['username']); ?>"><?php echo h($video['username']); ?></a> | Added: <?php echo h(date('F j, Y', strtotime($video['uploaded']))); ?></div>
							<form class="managercast-actions" method="post" action="feature_video.php">
								<input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
								<input type="hidden" name="video_id" value="<?php echo h($video['vid']); ?>">
								<input type="hidden" name="action" value="unfeature">
								<input type="submit" value="Remove Featured">
							</form>
						</td>
					</tr></tbody>
				</table>
			</div>
		<?php endforeach; ?>
	</div>

	<div class="managercast-section">
		<div class="tableSubTitle">Feature a Video (<?php echo $availableCount; ?>)</div>
		<?php if (!$availableVideos): ?>
			<div class="moduleEntry">There are no other public videos available to feature.</div>
		<?php endif; ?>
		<?php foreach ($availableVideos as $video): ?>
			<div class="moduleEntry">
				<table width="100%" cellpadding="0" cellspacing="0" border="0">
					<tbody><tr valign="top">
						<td width="140"><a href="../watch.php?v=<?php echo rawurlencode($video['vid']); ?>"><img src="../get_still.php?video_id=<?php echo rawurlencode($video['vid']); ?>" class="moduleEntryThumb" width="120" height="90" alt="<?php echo h($video['title']); ?>"></a></td>
						<td width="100%">
							<div class="moduleEntryTitle"><a href="../watch.php?v=<?php echo rawurlencode($video['vid']); ?>"><?php echo h($video['title']); ?></a></div>
							<div class="moduleEntryDetails">by <a href="../profile.php?user=<?php echo rawurlencode($video['username']); ?>"><?php echo h($video['username']); ?></a> | Added: <?php echo h(date('F j, Y', strtotime($video['uploaded']))); ?> | Views: <?php echo number_format((int)$video['views']); ?></div>
							<form class="managercast-actions" method="post" action="feature_video.php">
								<input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
								<input type="hidden" name="video_id" value="<?php echo h($video['vid']); ?>">
								<input type="hidden" name="action" value="feature">
								<input type="hidden" name="page" value="<?php echo $page; ?>">
								<input type="submit" value="Feature Video">
							</form>
						</td>
					</tr></tbody>
				</table>
			</div>
		<?php endforeach; ?>
	</div>

	<?php if ($totalPages > 1): ?>
		<div class="results-pagination">Videos:
			<?php for ($pageNumber = 1; $pageNumber <= $totalPages; $pageNumber++): ?>
				<?php if ($pageNumber === $page): ?>
					<strong><?php echo $pageNumber; ?></strong>
				<?php else: ?>
					<a href="index.php?page=<?php echo $pageNumber; ?>"><?php echo $pageNumber; ?></a>
				<?php endif; ?>
			<?php endfor; ?>
		</div>
	<?php endif; ?>

	<br>
	<?php $footerPrefix = '../'; include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
