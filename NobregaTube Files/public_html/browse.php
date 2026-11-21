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
		$messageQuery = $pdo->prepare('SELECT COUNT(*) FROM messages WHERE receiver = ? AND isRead = 0');
		$messageQuery->execute([$currentUser['uid']]);
		$unreadMessages = (int)$messageQuery->fetchColumn();
	} else {
		unset($_SESSION['uid'], $_SESSION['username']);
	}
}



$sortOptions = [
	'mr' => ['navigation' => 'Most Recent', 'title' => 'Most Recent', 'order' => 'v.uploaded DESC'],
	'mp' => ['navigation' => 'Most Popular', 'title' => 'Most Viewed', 'order' => 'v.views DESC'],
	'md' => ['navigation' => 'Most Discussed', 'title' => 'Most Discussed', 'order' => 'comment_count DESC'],
	'mf' => ['navigation' => 'Most Added to Favorites', 'title' => 'Most Added to Favorites', 'order' => 'favorite_count DESC'],
	'r' => ['navigation' => 'Random', 'title' => 'Random', 'order' => 'RAND()'],
];
$sort = (string)($_GET['s'] ?? 'mr');
if (!isset($sortOptions[$sort])) {
	$sort = 'mr';
}

$perPage = 20;
$page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$page = $page === false ? 1 : $page;
$videoCountQuery = $pdo->query('SELECT COUNT(*)
	FROM videos v
	INNER JOIN users u ON u.uid = v.uid
	WHERE v.converted = 1 AND v.privacy = 1 AND u.termination = 0');
$videoCount = (int)$videoCountQuery->fetchColumn();
$totalPages = max(1, (int)ceil($videoCount / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$videosQuery = $pdo->prepare('SELECT v.vid, v.title, v.uploaded, v.views, u.username,
	COALESCE(c.comment_count, 0) AS comment_count,
	COALESCE(f.favorite_count, 0) AS favorite_count
	FROM videos v
	INNER JOIN users u ON u.uid = v.uid
	LEFT JOIN (
		SELECT vid, COUNT(*) AS comment_count
		FROM comments
		WHERE removed = 0
		GROUP BY vid
	) c ON c.vid = v.vid
	LEFT JOIN (
		SELECT vid, COUNT(*) AS favorite_count
		FROM favorites
		GROUP BY vid
	) f ON f.vid = v.vid
	WHERE v.converted = 1 AND v.privacy = 1 AND u.termination = 0
	ORDER BY ' . $sortOptions[$sort]['order'] . '
	LIMIT ' . $perPage . ' OFFSET ' . $offset);
$videosQuery->execute();
$videos = $videosQuery->fetchAll();
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html lang="en">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
	<title>NobregaTube - Browse Videos</title>
	<link rel="stylesheet" href="styles2005july.css">
</head>
<body>
	<?php $activeHeaderPage = ''; $showSubNav = true; include __DIR__ . '/includes/header.php'; ?>

	<table align="center" cellpadding="5" cellspacing="0" border="0">
		<tbody><tr>
			<?php foreach ($sortOptions as $sortKey => $sortOption): ?>
				<?php if ($sortKey !== 'mr'): ?><td>|</td><?php endif; ?>
				<td>
					<?php if ($sort === $sortKey): ?>
						<strong><?php echo h($sortOption['navigation']); ?></strong>
					<?php else: ?>
						<a class="bold" href="browse.php?s=<?php echo rawurlencode($sortKey); ?>"><?php echo h($sortOption['navigation']); ?></a>
					<?php endif; ?>
				</td>
			<?php endforeach; ?>
		</tr></tbody>
	</table>

	<br>

	<table width="80%" align="center" cellpadding="0" cellspacing="0" border="0" bgcolor="#CCCCCC">
		<tbody>
			<tr>
				<td><img src="img/box_login_tl.gif" width="5" height="5" alt=""></td>
				<td width="100%"><img src="img/pixel.gif" width="1" height="5" alt=""></td>
				<td><img src="img/box_login_tr.gif" width="5" height="5" alt=""></td>
			</tr>
			<tr>
				<td><img src="img/pixel.gif" width="5" height="1" alt=""></td>
				<td>
					<div class="moduleTitleBar"><div class="moduleTitle"><?php echo h($sortOptions[$sort]['title']); ?></div></div>
					<?php if (!$videos): ?>
						<div class="moduleEntry">No videos have been uploaded yet.</div>
					<?php else: ?>
						<div class="moduleFeatured">
							<table width="100%" cellpadding="0" cellspacing="0" border="0">
								<tbody>
									<?php foreach (array_chunk($videos, 5) as $videoRow): ?>
										<tr valign="top">
											<?php foreach ($videoRow as $video): ?>
												<td width="20%" align="center">
													<a href="watch.php?v=<?php echo rawurlencode($video['vid']); ?>"><img src="get_still.php?video_id=<?php echo rawurlencode($video['vid']); ?>" width="120" height="90" class="moduleFeaturedThumb" alt="<?php echo h($video['title']); ?>"></a>
													<div class="moduleFeaturedTitle"><a href="watch.php?v=<?php echo rawurlencode($video['vid']); ?>"><?php echo h($video['title']); ?></a></div>
													<div class="moduleFeaturedDetails">Added: <?php echo h(date('F j, Y', strtotime($video['uploaded']))); ?><br>by <a href="profile.php?user=<?php echo rawurlencode($video['username']); ?>"><?php echo h($video['username']); ?></a></div>
													<div class="moduleFeaturedDetails">Views: <?php echo number_format((int)$video['views']); ?> | Comments: <?php echo (int)$video['comment_count']; ?></div>
												</td>
											<?php endforeach; ?>
											<?php for ($emptyCell = count($videoRow); $emptyCell < 5; $emptyCell++): ?>
												<td width="20%">&nbsp;</td>
											<?php endfor; ?>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					<?php endif; ?>
				</td>
				<td><img src="img/pixel.gif" width="5" height="1" alt=""></td>
			</tr>
			<tr>
				<td><img src="img/box_login_bl.gif" width="5" height="5" alt=""></td>
				<td><img src="img/pixel.gif" width="1" height="5" alt=""></td>
				<td><img src="img/box_login_br.gif" width="5" height="5" alt=""></td>
			</tr>
		</tbody>
	</table>

	<?php if ($totalPages > 1): ?>
		<div class="results-pagination">Videos Page:
			<?php if ($page > 1): ?>
				<a href="browse.php?s=<?php echo rawurlencode($sort); ?>&amp;page=<?php echo $page - 1; ?>">&lt; Previous</a>
			<?php endif; ?>
			<?php for ($pageNumber = 1; $pageNumber <= $totalPages; $pageNumber++): ?>
				<?php if ($pageNumber === $page): ?>
					<strong><?php echo $pageNumber; ?></strong>
				<?php else: ?>
					<a href="browse.php?s=<?php echo rawurlencode($sort); ?>&amp;page=<?php echo $pageNumber; ?>"><?php echo $pageNumber; ?></a>
				<?php endif; ?>
			<?php endfor; ?>
			<?php if ($page < $totalPages): ?>
				<a href="browse.php?s=<?php echo rawurlencode($sort); ?>&amp;page=<?php echo $page + 1; ?>">Next &gt;</a>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<br>
	<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
