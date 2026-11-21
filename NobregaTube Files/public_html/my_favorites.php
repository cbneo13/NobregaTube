<?php
require_once __DIR__ . '/includes/sess.php';
require __DIR__ . '/includes/database.php';
require __DIR__ . '/includes/countries.php';

if (empty($_SESSION['uid'])) {
	header('Location: login.php');
	exit;
}

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
if (empty($_SESSION['csrf_token'])) {
	$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}



$perPage = 10;
$page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$page = $page === false ? 1 : $page;

$countQuery = $pdo->prepare('SELECT COUNT(*)
	FROM favorites f
	INNER JOIN videos v ON v.vid = f.vid
	INNER JOIN users u ON u.uid = v.uid
	WHERE f.uid = ? AND v.converted = 1 AND v.privacy = 1 AND u.termination = 0');
$countQuery->execute([$currentUser['uid']]);
$favoriteCount = (int)$countQuery->fetchColumn();
$totalPages = max(1, (int)ceil($favoriteCount / $perPage));
if ($page > $totalPages) {
	$page = $totalPages;
}
$offset = ($page - 1) * $perPage;

$videosQuery = $pdo->prepare('SELECT v.vid, v.title, v.description, v.tags, v.recorddate,
	v.address, v.addrcountry, v.duration, v.uploaded, v.views, u.username,
	(SELECT COUNT(*) FROM comments c WHERE c.vid = v.vid AND c.removed = 0) AS comment_count
	FROM favorites f
	INNER JOIN videos v ON v.vid = f.vid
	INNER JOIN users u ON u.uid = v.uid
	WHERE f.uid = ? AND v.converted = 1 AND v.privacy = 1 AND u.termination = 0
	ORDER BY f.created DESC, v.vid DESC
	LIMIT ' . $perPage . ' OFFSET ' . $offset);
$videosQuery->execute([$currentUser['uid']]);
$videos = $videosQuery->fetchAll();

$relatedTags = [];
foreach ($videos as $video) {
	foreach (preg_split('/\s+/', trim((string)$video['tags']), -1, PREG_SPLIT_NO_EMPTY) as $tag) {
		$relatedTags[strtolower($tag)] = $tag;
	}
}
natcasesort($relatedTags);
$relatedTags = array_values($relatedTags);
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html lang="en">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
	<title>NobregaTube - My Favorites</title>
	<link rel="stylesheet" href="styles2005july.css">
</head>
<body>
	<?php
	$activeHeaderPage = 'favorites';
	$showSubNav = true;
	include __DIR__ . '/includes/header.php';
	?>

	<div class="pageTitle">My Favorites</div>

	<table width="100%" align="center" cellpadding="0" cellspacing="0" border="0">
		<tbody><tr valign="top">
			<td width="100%">
				<table width="100%" align="center" cellpadding="0" cellspacing="0" border="0" bgcolor="#CCCCCC">
					<tbody>
						<tr>
							<td><img src="img/box_login_tl.gif" width="5" height="5" alt=""></td>
							<td width="100%"><img src="img/pixel.gif" width="1" height="5" alt=""></td>
							<td><img src="img/box_login_tr.gif" width="5" height="5" alt=""></td>
						</tr>
						<tr>
							<td><img src="img/pixel.gif" width="5" height="1" alt=""></td>
							<td>
								<div class="moduleTitleBar"><div class="moduleTitle">My Favorites (<?php echo $favoriteCount; ?>)</div></div>
								<?php if (!$videos): ?>
									<div class="moduleEntry">You haven't added any videos to your favorites yet. <a href="browse.php">Browse videos</a> to find one.</div>
								<?php endif; ?>
								<?php foreach ($videos as $video): ?>
									<div class="moduleEntry">
										<table width="100%" cellpadding="0" cellspacing="0" border="0" class="tableFavRemove">
											<tbody><tr valign="top">
												<td width="140" align="center">
													<a href="watch.php?v=<?php echo rawurlencode($video['vid']); ?>"><img src="get_still.php?video_id=<?php echo rawurlencode($video['vid']); ?>" class="moduleEntryThumb" width="120" height="90" alt="<?php echo h($video['title']); ?>"></a>
													<form method="post" action="remove_favorite.php" onsubmit="return confirm('Do you want to remove this video from your favorites?');">
														<input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
														<input type="hidden" name="video_id" value="<?php echo h($video['vid']); ?>">
														<input type="hidden" name="page" value="<?php echo $page; ?>">
														<input type="submit" value="Remove Favorite">
													</form>
												</td>
												<td width="100%">
													<div class="moduleEntryTitle"><a href="watch.php?v=<?php echo rawurlencode($video['vid']); ?>"><?php echo h($video['title']); ?></a></div>
													<div class="moduleEntryDescription"><?php echo nl2br(h($video['description'])); ?></div>
													<div class="moduleEntryTags">Tags // <?php
														$videoTags = preg_split('/\s+/', trim((string)$video['tags']), -1, PREG_SPLIT_NO_EMPTY);
														$tagLinks = [];
														foreach ($videoTags as $tag) {
															$tagLinks[] = '<a href="results.php?search=' . rawurlencode($tag) . '">' . h($tag) . '</a>';
														}
														echo implode(' : ', $tagLinks);
													?></div>
													<div class="moduleEntryDetails">Recorded: <?php echo $video['recorddate'] ? h(date('F j, Y', strtotime($video['recorddate']))) : 'Not specified'; ?> | Location: <?php echo h($video['address'] ?: 'Not specified'); ?><?php if (!empty($video['addrcountry']) && isset($countries[$video['addrcountry']])): ?>, <?php echo h($countries[$video['addrcountry']]); ?><?php endif; ?></div>
													<div class="moduleEntryDetails">Added: <?php echo h(date('F j, Y', strtotime($video['uploaded']))); ?> by <a href="profile.php?user=<?php echo rawurlencode($video['username']); ?>"><?php echo h($video['username']); ?></a></div>
													<div class="moduleEntryDetails">Runtime: <?php echo (int)$video['duration'] > 0 ? gmdate('i:s', (int)$video['duration']) : '--:--'; ?> | Views: <?php echo (int)$video['views']; ?> | Comments: <?php echo (int)$video['comment_count']; ?></div>
												</td>
											</tr></tbody>
										</table>
									</div>
								<?php endforeach; ?>

								<?php if ($totalPages > 1): ?>
									<div class="results-pagination">My Favorites Page:
										<?php if ($page > 1): ?>
											<a href="my_favorites.php?page=<?php echo $page - 1; ?>">&lt; Previous</a>
										<?php endif; ?>
										<?php for ($pageNumber = 1; $pageNumber <= $totalPages; $pageNumber++): ?>
											<?php if ($pageNumber === $page): ?>
												<strong><?php echo $pageNumber; ?></strong>
											<?php else: ?>
												<a href="my_favorites.php?page=<?php echo $pageNumber; ?>"><?php echo $pageNumber; ?></a>
											<?php endif; ?>
										<?php endfor; ?>
										<?php if ($page < $totalPages): ?>
											<a href="my_favorites.php?page=<?php echo $page + 1; ?>">Next &gt;</a>
										<?php endif; ?>
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
			</td>
			<td width="15"><img src="img/pixel.gif" width="15" height="1" alt=""></td>
			<td width="160">
				<div style="font-weight: bold; margin-bottom: 3px; width: 160px;">Favorite Tags:</div>
				<?php foreach ($relatedTags as $tag): ?>
					<div style="padding: 0px 0px 5px 0px; color: #999;">&raquo; <a href="results.php?search=<?php echo rawurlencode($tag); ?>"><?php echo h($tag); ?></a></div>
				<?php endforeach; ?>
			</td>
		</tr></tbody>
	</table>

	<br>
	<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
