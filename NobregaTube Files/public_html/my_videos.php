<?php
require_once __DIR__ . '/includes/sess.php';
require __DIR__ . '/includes/database.php';
require __DIR__ . '/includes/countries.php';

if (empty($_SESSION['uid'])) {
	header('Location: login.php');
	exit;
}

$currentUserQuery = $pdo->prepare('SELECT uid, username, staff FROM users WHERE uid = ? LIMIT 1');
$currentUserQuery->execute([$_SESSION['uid']]);
$currentUser = $currentUserQuery->fetch();
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



$videosQuery = $pdo->prepare('SELECT v.*,
	(SELECT COUNT(*) FROM comments c WHERE c.vid = v.vid AND c.removed = 0) AS comment_count,
	(SELECT COUNT(*) FROM favorites f WHERE f.vid = v.vid) AS favorite_count
	FROM videos v
	WHERE v.uid = ?
	ORDER BY v.uploaded DESC');
$videosQuery->execute([$currentUser['uid']]);
$videos = $videosQuery->fetchAll();

$relatedTags = [];
foreach ($videos as $video) {
	foreach (preg_split('/\s+/', trim($video['tags']), -1, PREG_SPLIT_NO_EMPTY) as $tag) {
		$relatedTags[strtolower($tag)] = $tag;
	}
}

$siteHost = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
if (!preg_match('/^[A-Za-z0-9.-]+(?::[0-9]{1,5})?$/', $siteHost)) {
	$siteHost = 'localhost';
}
$siteBase = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://') . $siteHost;
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html lang="en">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
	<title>NobregaTube - My Videos</title>
	<link rel="stylesheet" href="styles2005july.css">
</head>
<body>
	<?php
	$activeHeaderPage = 'videos';
	$showSubNav = true;
	include __DIR__ . '/includes/header.php';
	?>

	<div class="pageTitle">My Videos</div>
	<?php if (isset($_GET['removed'])): ?>
		<p class="success">Your video was removed.</p>
	<?php endif; ?>

	<table class="my-videos-layout" width="100%" align="center" cellpadding="0" cellspacing="0" border="0">
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
								<div class="moduleTitleBar"><div class="moduleTitle">My Videos (<?php echo count($videos); ?>)</div></div>
								<?php if (!$videos): ?>
									<div class="moduleEntry">You haven't uploaded any videos yet. <a href="my_videos_upload.php">Upload a video</a>.</div>
								<?php endif; ?>
								<?php foreach ($videos as $video): ?>
									<div class="moduleEntry">
										<table width="100%" cellpadding="0" cellspacing="0" border="0">
											<tbody><tr valign="top">
												<td width="140" align="center">
													<a href="watch.php?v=<?php echo rawurlencode($video['vid']); ?>"><img src="get_still.php?video_id=<?php echo rawurlencode($video['vid']); ?>" class="moduleEntryThumb" width="120" height="90" alt="<?php echo h($video['title']); ?>"></a>
													<form method="get" action="my_videos_edit.php">
														<input type="hidden" name="video_id" value="<?php echo h($video['vid']); ?>">
														<input type="submit" value="Edit Video">
													</form>
													<form method="post" action="remove_video.php" onsubmit="return confirm('Remove this video permanently?');">
														<input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
														<input type="hidden" name="video_id" value="<?php echo h($video['vid']); ?>">
														<input type="submit" value="Remove Video">
													</form>
												</td>
												<td width="100%">
													<div class="moduleEntryTitle"><a href="watch.php?v=<?php echo rawurlencode($video['vid']); ?>"><?php echo h($video['title']); ?></a></div>
													<div class="moduleEntryDescription"><?php echo nl2br(h($video['description'])); ?></div>
													<div class="moduleEntryTags">Tags // <?php echo h($video['tags']); ?></div>
													<div class="moduleEntryDetails">Recorded: <?php echo $video['recorddate'] ? h(date('F j, Y', strtotime($video['recorddate']))) : 'Not specified'; ?> | Location: <?php echo h($video['address'] ?: 'Not specified'); ?><?php if (!empty($video['addrcountry']) && isset($countries[$video['addrcountry']])): ?>, <?php echo h($countries[$video['addrcountry']]); ?><?php endif; ?></div>
													<div class="moduleEntryDetails">Added: <?php echo h(date('F j, Y', strtotime($video['uploaded']))); ?></div>
													<div class="moduleEntryDetails">Runtime: <?php echo (int)$video['duration'] > 0 ? gmdate('i:s', (int)$video['duration']) : '--:--'; ?> | Views: <?php echo (int)$video['views']; ?> | Comments: <?php echo (int)$video['comment_count']; ?> | Fans: <?php echo (int)$video['favorite_count']; ?></div>
													<hr style="border: 0; border-bottom: 1px dashed #999999; margin: 1em 0;">
													<div class="moduleEntryDetails">File: <?php echo h($video['file'] ?: 'undefined'); ?></div>
													<div class="moduleEntryDetails">Broadcast: <?php echo (int)$video['privacy'] === 1 ? '<span style="color: #3e7335; font-weight: bold;">Public Video</span>' : '<span style="color: #d72d11; font-weight: bold;">Private Video</span>'; ?></div>
													<div class="moduleEntryDetails">Status:
														<?php if ((int)$video['converted'] === 1): ?>
															Live!
														<?php elseif ((int)$video['converted'] === 3): ?>
															<span class="error">Processing failed: <?php echo h($video['queue_error']); ?></span>
														<?php else: ?>
															Processing...
														<?php endif; ?>
													</div>
													<div class="moduleEntryDetails">
														<label for="video_link_<?php echo h($video['vid']); ?>">Video URL:</label><br>
														<input id="video_link_<?php echo h($video['vid']); ?>" type="text" value="<?php echo h($siteBase . '/watch.php?v=' . rawurlencode($video['vid'])); ?>" size="50" readonly onclick="this.focus();this.select();">
														<br>Share this video with friends! Copy the link<?php if ((int)$video['privacy'] === 1 && (int)$video['converted'] === 1): ?> or <a href="outbox.php?video=<?php echo rawurlencode($video['vid']); ?>">send it to a NobregaTube user</a><?php endif; ?>.
													</div>
												</td>
											</tr></tbody>
										</table>
									</div>
								<?php endforeach; ?>
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
			<td class="my-videos-tags" width="160">
				<div style="font-weight: bold; margin-bottom: 3px; width: 160px;">My Tags:</div>
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
