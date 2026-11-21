<?php
if (isset($_GET['v'])) {
	header('Location: watch.php?v=' . rawurlencode((string)$_GET['v']), true, 303);
	exit;
}
require_once __DIR__ . '/includes/sess.php';
require __DIR__ . '/includes/database.php';
require __DIR__ . '/includes/tag_cloud.php';

$currentUser = null;
$unreadMessages = 0;
if (!empty($_SESSION['uid'])) {
	$userQuery = $pdo->prepare('SELECT uid, username, staff FROM users WHERE uid = ?');
	$userQuery->execute([$_SESSION['uid']]);
	$currentUser = $userQuery->fetch();
	if (!$currentUser) {
		unset($_SESSION['uid'], $_SESSION['username']);
	} else {
		$messageQuery = $pdo->prepare('SELECT COUNT(*) FROM messages WHERE receiver = ? AND isRead = 0');
		$messageQuery->execute([$currentUser['uid']]);
		$unreadMessages = (int)$messageQuery->fetchColumn();
	}
}
if (empty($_SESSION['csrf_token'])) {
	$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$latestTagsQuery = $pdo->query('SELECT v.tags FROM videos v
	INNER JOIN users u ON u.uid = v.uid
	WHERE v.privacy = 1 AND v.converted = 1 AND u.termination = 0
	ORDER BY v.uploaded DESC LIMIT 50');
$latestTags = tag_frequency($latestTagsQuery->fetchAll());
$featuredQuery = $pdo->query('SELECT v.vid, v.title, v.uploaded, v.views, u.username,
	(SELECT COUNT(*) FROM comments c WHERE c.vid = v.vid AND c.removed = 0) AS comment_count
	FROM picks p
	INNER JOIN videos v ON v.vid = p.video
	INNER JOIN users u ON u.uid = v.uid
	WHERE v.privacy = 1 AND v.converted = 1 AND u.termination = 0
	ORDER BY p.featured DESC
	LIMIT 5');
$featuredVideos = $featuredQuery->fetchAll();
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html lang="en">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
	<title>NobregaTube - Your Digital Video Repository</title>
	<link rel="stylesheet" href="styles2005july.css">
</head>
<body>
	<?php
	$showHeaderLogo = false;
	$activeHeaderPage = 'home';
	include __DIR__ . '/includes/header.php';
	?>

	<form class="search-form" name="f" method="get" action="results.php">
	<table class="search-header" width="80%" align="center" cellpadding="3" cellspacing="0" border="0">
		<tbody>
			<tr>
				<td align="center">
					<img src="img/logo.gif" width="257" height="71" hspace="12" vspace="12" alt="NobregaTube">
					<br>
					Your Epic Video Repoisitory
					<br><br>
				</td>
			</tr>
			<tr><td align="center"><input class="search-input" type="text" name="search" size="35" maxlength="128"></td></tr>
			<tr><td align="center"><input type="submit" value="Search Videos"></td></tr>
		</tbody>
	</table>
	</form>

	<div class="upload-link"><a href="my_videos_upload.php">Upload Videos</a> &nbsp; // &nbsp; <a href="browse.php">Browse Videos</a><img border="0" src="img/new.gif" alt="New"></div>

	<div class="tags-footer">
		<div class="tag-cloud">
			<?php if ($latestTags): ?>
				<?php $latestTagMax = max(array_column($latestTags, 'frequency')); ?>
				<?php foreach ($latestTags as $tagData): ?>
					<a style="font-size: <?php echo tag_font_size($tagData['frequency'], $latestTagMax); ?>px;" href="results.php?search=<?php echo rawurlencode($tagData['tag']); ?>"><?php echo h($tagData['tag']); ?></a> :
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
		<div class="tags-footer-link"><a href="tags.php">See More Tags</a></div>
	</div>

	<table class="featured-heading-box" width="80%" align="center" cellpadding="0" cellspacing="0" border="0">
		<tbody>
			<tr class="featured-heading-corners">
				<td><img src="img/box_login_tl.gif" width="5" height="5" alt=""></td>
				<td width="100%"><img src="img/pixel.gif" width="1" height="5" alt=""></td>
				<td><img src="img/box_login_tr.gif" width="5" height="5" alt=""></td>
			</tr>
			<tr>
				<td><img src="img/pixel.gif" width="5" height="1" alt=""></td>
				<td class="featured-heading-cell" width="100%">
					<div class="module-title-bar">
						<div class="module-title">
							<?php if ($featuredVideos): ?>
								<div class="watch-more"><a href="browse.php">See More Videos</a></div>
							<?php endif; ?>
							Featured Videos
						</div>
					</div>
					<?php if ($featuredVideos): ?>
						<div class="moduleFeatured featured-videos">
							<table width="100%" cellpadding="0" cellspacing="0" border="0">
								<tbody><tr valign="top">
									<?php foreach ($featuredVideos as $featuredVideo): ?>
										<td width="20%" align="center">
											<a href="watch.php?v=<?php echo rawurlencode($featuredVideo['vid']); ?>"><img src="get_still.php?video_id=<?php echo rawurlencode($featuredVideo['vid']); ?>" class="moduleFeaturedThumb" width="120" height="90" alt="<?php echo h($featuredVideo['title']); ?>"></a>
											<div class="moduleFeaturedTitle"><a href="watch.php?v=<?php echo rawurlencode($featuredVideo['vid']); ?>"><?php echo h($featuredVideo['title']); ?></a></div>
											<div class="moduleFeaturedDetails">Added: <?php echo h(date('F j, Y', strtotime($featuredVideo['uploaded']))); ?><br>by <a href="profile.php?user=<?php echo rawurlencode($featuredVideo['username']); ?>"><?php echo h($featuredVideo['username']); ?></a></div>
											<div class="moduleFeaturedDetails">Views: <?php echo number_format((int)$featuredVideo['views']); ?> | Comments: <?php echo (int)$featuredVideo['comment_count']; ?></div>
										</td>
									<?php endforeach; ?>
								</tr></tbody>
							</table>
						</div>
					<?php endif; ?>
				</td>
				<td><img src="img/pixel.gif" width="5" height="1" alt=""></td>
			</tr>
			<tr class="featured-heading-corners">
				<td><img src="img/box_login_bl.gif" width="5" height="5" alt=""></td>
				<td><img src="img/pixel.gif" width="1" height="5" alt=""></td>
				<td><img src="img/box_login_br.gif" width="5" height="5" alt=""></td>
			</tr>
		</tbody>
	</table>
	<?php include __DIR__ . '/includes/footer.php'; ?>
