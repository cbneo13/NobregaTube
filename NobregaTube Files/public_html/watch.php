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
if (empty($_SESSION['csrf_token'])) {
	$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function needs_flash($ua)
{
	$ua = (string)$ua;
	if ($ua === '') {
		return false;
	}
	$isMobile = (bool)preg_match('/Mobile|Android|iPhone|iPad|iPod|Windows Phone|IEMobile/i', $ua);
	if ($isMobile) {
		return false;
	}
	if (preg_match('/(?:MSIE\s+\d|Trident\/)/i', $ua)) {
		return true;
	}
	if (stripos($ua, 'Safari') !== false && preg_match('/Version\/(\d+)(?:\.(\d+))?/', $ua, $m)) {
		$major = (int)$m[1];
		$minor = isset($m[2]) ? (int)$m[2] : 0;
		if ($major < 14 || ($major === 14 && $minor < 1)) {
			return true;
		}
	}
	if (preg_match('/Chrome\/(\d+)/i', $ua, $m) && (int)$m[1] < 6) {
		return true;
	}
	if (preg_match('/Firefox\/(\d+)/i', $ua, $m) && (int)$m[1] < 4) {
		return true;
	}
	if (preg_match('/Opera\/(\d+)/i', $ua, $m) && (int)$m[1] < 11) {
		return true;
	}
	return false;
}

$useFlash = isset($_COOKIE['flash'])
	|| (isset($_GET['flash']) && $_GET['flash'] === '1')
	|| needs_flash($_SERVER['HTTP_USER_AGENT'] ?? '');

$vid = substr((string)($_GET['v'] ?? ''), 0, 20);
if ($vid === '') {
	header('Location: index.php');
	exit;
}

$videoQuery = $pdo->prepare('SELECT v.*, u.username, u.termination FROM videos v LEFT JOIN users u ON u.uid = v.uid WHERE v.vid = ? LIMIT 1');
$videoQuery->execute([$vid]);
$video = $videoQuery->fetch();
if (!$video || (int)$video['termination'] === 1) {
	header('Location: index.php');
	exit;
}

$sessionUid = (string)($_SESSION['uid'] ?? '');
$isOwner = $sessionUid !== '' && $sessionUid === (string)$video['uid'];
$isStaff = $currentUser && (int)$currentUser['staff'] === 1;
$managerCastTab = $isStaff && (string)($_GET['tab'] ?? '') === 'managercast';

if ((int)$video['privacy'] === 2 && !$isOwner && !$isStaff) {
	header('Location: index.php');
	exit;
}

if ((int)$video['converted'] !== 1 && (int)$video['converted'] !== 3 && !$managerCastTab) {
	header('Location: index.php');
	exit;
}

if ((int)$video['converted'] === 3 && !$managerCastTab) {
	$activeHeaderPage = '';
	$title = $video['title'];
	?><!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
	<html lang="en"><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"><title>NobregaTube - Your Digital Video Repository</title><link rel="stylesheet" href="styles2005july.css"></head><body>
	<?php include __DIR__ . '/includes/header_search.php'; ?>
	<div class="pageTitle"><?php echo h($video['title']); ?></div>
	<p><span class="error">This video could not be processed.</span></p>
	<p><?php echo h($video['queue_error']); ?></p>
	<p><a href="index.php">Return to the home page</a></p>
	<br>
	<?php include __DIR__ . '/includes/footer.php'; ?></body></html><?php
	exit;
}

$viewKey = 'viewed_videos';
if (!$managerCastTab && (int)$video['converted'] === 1
	&& (!isset($_SESSION[$viewKey][$vid]) || time() - (int)$_SESSION[$viewKey][$vid] > 600)) {
	$pdo->prepare('UPDATE videos SET views = views + 1 WHERE vid = ?')->execute([$vid]);
	$_SESSION[$viewKey][$vid] = time();
	$video['views'] = (int)$video['views'] + 1;
}

$countQuery = $pdo->prepare('SELECT COUNT(*) FROM videos WHERE uid = ? AND privacy = 1 AND converted = 1');
$countQuery->execute([$video['uid']]);
$videos = (int)$countQuery->fetchColumn();

$countQuery = $pdo->prepare('SELECT COUNT(*)
	FROM favorites f
	INNER JOIN videos v ON v.vid = f.vid
	WHERE f.uid = ? AND v.privacy = 1 AND v.converted = 1');
$countQuery->execute([$video['uid']]);
$favorites = (int)$countQuery->fetchColumn();

$commentQuery = $pdo->prepare('SELECT c.*, u.username,
	(SELECT COUNT(*) FROM videos vv WHERE vv.uid = c.uid AND vv.privacy = 1 AND vv.converted = 1) AS user_videos,
	(SELECT COUNT(*) FROM favorites ff
		INNER JOIN videos fv ON fv.vid = ff.vid
		WHERE ff.uid = c.uid AND fv.privacy = 1 AND fv.converted = 1) AS user_favorites
	FROM comments c LEFT JOIN users u ON u.uid = c.uid
	WHERE c.vid = ? AND c.removed = 0 AND u.termination = 0 ORDER BY c.post_date DESC');
$commentQuery->execute([$vid]);
$comments = $commentQuery->fetchAll();
$commentc = count($comments);

$isFavorite = false;
if ($sessionUid !== '') {
	$favoriteQuery = $pdo->prepare('SELECT 1 FROM favorites WHERE uid = ? AND vid = ?');
	$favoriteQuery->execute([$sessionUid, $vid]);
	$isFavorite = (bool)$favoriteQuery->fetchColumn();
}

$isFeatured = false;
if ($isStaff) {
	$featuredQuery = $pdo->prepare('SELECT 1 FROM picks WHERE video = ? LIMIT 1');
	$featuredQuery->execute([$vid]);
	$isFeatured = (bool)$featuredQuery->fetchColumn();
}

$managerCastNotice = [
	'added' => 'Video featured.',
	'removed' => 'Video removed from featured videos.',
	'already' => 'This video was already featured.',
	'unavailable' => 'This video cannot be featured.',
	'error' => 'The featured video action could not be completed.',
][(string)($_GET['featured'] ?? '')] ?? '';

$tagList = preg_split('/\s+/', trim($video['tags']), -1, PREG_SPLIT_NO_EMPTY);

$results = [];
if ($video['tags'] !== '') {
	$rx = str_replace(' ', '|', preg_quote($video['tags'], '/'));
	$relatedQuery = $pdo->prepare('SELECT v.vid, v.tags FROM videos v LEFT JOIN users u ON u.uid = v.uid
		WHERE (v.tags REGEXP ? OR v.description REGEXP ? OR v.title REGEXP ? OR u.username REGEXP ?)
		AND v.privacy = 1 AND v.converted = 1 AND u.termination = 0 ORDER BY v.uploaded DESC LIMIT 200');
	$relatedQuery->execute([$rx, $rx, $rx, $rx]);
	$results = $relatedQuery->fetchAll();
}
$related_vid_count = count($results);
$related_tags = [];
foreach ($results as $result) {
	foreach (explode(' ', $result['tags']) as $tag) {
		if ($tag !== '') {
			$related_tags[$tag] = $tag;
		}
	}
}
$related_tags = array_values($related_tags);

$siteUrl = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost');
$shareUrl = $siteUrl . '/watch.php?v=' . rawurlencode($vid);

$description = nl2br(h($video['description']));
$description = preg_replace('~(https?://[^\s<]+)~i', '<a href="$1" target="_blank">$1</a>', $description);
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html lang="en">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
	<title>NobregaTube - Your Digital Video Repository</title>
	<link rel="stylesheet" href="styles2005july.css">
	<?php if ($useFlash): ?><script src="flashobject.js" language="javascript"></script><?php endif; ?>
	<?php if (!$useFlash): ?>
	<script type="text/javascript">
	(function () {
		var ua = navigator.userAgent || '';
		if (/Android|iPhone|iPad|iPod|Windows Phone|IEMobile|Mobile/i.test(ua)) return;
		var ok = false;
		try {
			var video = document.createElement('video');
			var canWebm = !!(video.canPlayType && video.canPlayType('video/webm; codecs="vp8, vorbis"'));
			var canApi = !!(video.addEventListener && document.addEventListener);
			var input = document.createElement('input');
			input.type = 'range';
			var canRange = input.type === 'range';
			var canProgress = ('max' in document.createElement('progress'));
			ok = canWebm && canApi && canRange && canProgress;
		} catch (e) { ok = false; }
		if (ok) return;
		var href = window.location.href.split('#')[0];
		if (href.indexOf('flash=1') !== -1) return;
		window.location.replace(href + (href.indexOf('?') === -1 ? '?' : '&') + 'flash=1');
	})();
	</script>
	<?php endif; ?>
</head>
<body>
	<?php $activeHeaderPage = ''; include __DIR__ . '/includes/header_search.php'; ?>

	<div class="pageTitle"><?php echo h($video['title']); ?></div>

	<table width="795" align="center" cellpadding="0" cellspacing="0" border="0">
		<tbody><tr valign="top">
			<td width="515" style="padding-right: 15px;">

			<div style="font-size: 13px; font-weight: bold; text-align: center;">
			<?php if ($sessionUid !== '' && (int)$video['privacy'] === 1 && (int)$video['converted'] === 1): ?><a href="outbox.php?video=<?php echo rawurlencode($vid); ?>">Share with a NobregaTube user</a><?php else: ?><a href="login.php">Share with a NobregaTube user</a><?php endif; ?>
			// <a href="#comment">Comment</a>
			// <?php if ($sessionUid !== ''): ?><form method="post" action="add_favorites.php" style="display: inline;"><input type="hidden" name="video_id" value="<?php echo h($vid); ?>"><input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>"><input class="link-button" type="submit" value="<?php echo $isFavorite ? 'Remove from Favorites' : 'Add to Favorites'; ?>"></form><?php else: ?><a href="login.php">Add to Favorites</a><?php endif; ?>
			// <?php if ($sessionUid !== '' && $sessionUid !== (string)$video['uid']): ?><a href="outbox.php?user=<?php echo rawurlencode($video['username']); ?>&amp;subject=<?php echo rawurlencode('Re: ' . $video['title']); ?>">Contact Me</a><?php elseif ($sessionUid === ''): ?><a href="login.php">Contact Me</a><?php endif; ?>
			</div>

			<?php if ((int)$video['converted'] !== 1): ?>
			<p><?php echo (int)$video['converted'] === 3 ? 'This video could not be processed.' : 'This video is still processing.'; ?></p>
			<?php elseif ($useFlash): ?>
			<div style="text-align: center; padding-bottom: 10px;">
			<div id="flashcontent">
			<div style="padding: 20px; font-size: 14px; font-weight: bold;">
				<embed src="player.swf?video_id=<?php echo h($vid); ?>&amp;l=<?php echo (int)$video['duration']; ?>&amp;c=14" width="425" height="350">
			</div>
			</div>
			</div>
			<script type="text/javascript">
				var fo = new FlashObject("player.swf?video_id=<?php echo h($vid); ?>&l=<?php echo (int)$video['duration']; ?>&c=14", "player", "425", "350", 7, "#FFFFFF");
				fo.write("flashcontent");
			</script>
			<?php else: ?>
			<div style="padding: 10px; margin-left: 20px;">
			<link rel="stylesheet" href="viewfinder/player.css">
			<div class="player" id="playerBox">
				<div class="mainContainer">
					<div class="playerScreen">
						<div class="playbackArea">
							<div class="videoContainer">
								<video class="videoObject" id="video" preload="metadata">
									<source src="get_video.php?video_id=<?php echo h($vid); ?>&amp;format=webm" type="video/webm">
								</video>
							</div>
						</div>
						<div class="watermark">
							<img src="viewfinder/resource/watermark.png" height="35" alt="NobregaTube">
						</div>
					</div>
					<div class="controlBackground">
						<div class="controlContainer">
							<div class="lBtnContainer">
								<div class="button" id="playButton">
									<img src="viewfinder/resource/play.png" id="playIcon" alt="Play">
									<img src="viewfinder/resource/pause.png" class="hidden" id="pauseIcon" alt="Pause">
								</div>
							</div>
							<div class="centerContainer">
								<div class="seekbarElementContainer">
									<progress class="seekProgress" id="seekProgress" value="0" min="0"></progress>
								</div>
								<div class="seekbarElementContainer">
									<input class="seekHandle" id="seekHandle" value="0" min="0" step="1" type="range">
								</div>
							</div>
							<div class="rBtnContainer">
								<div class="button" id="muteButton">
									<img src="viewfinder/resource/mute.png" id="muteIcon" alt="Mute">
									<img src="viewfinder/resource/unmute.png" class="hidden" id="unmuteIcon" alt="Unmute">
								</div>
							</div>
						</div>
					</div>
				</div>
				<div class="aboutBox hidden" id="aboutBox">
					<div class="aboutBoxContent">
					<div class="aboutHeader">Viewfinder</div>
					<div class="aboutBody">
						Version 1.0<br>
						<br>
						2005-Style HTML5 player<br>
						<br>
						Created by Purpleblaze
					</div>
					<div align="right"><button id="aboutCloseBtn">Close</button></div>
					</div>
				</div>
				<div class="contextMenu hidden" id="playerContextMenu">
					<div class="contextItem" id="contextMute">
						<span>Mute</span>
						<div id="muteTick" class="tick hidden"></div>
					</div>
					<div class="contextItem" id="contextLoop">
						<span>Loop</span>
						<div id="loopTick" class="tick hidden"></div>
					</div>
					<div class="contextSeparator"></div>
					<div class="contextItem" id="contextAbout">About</div>
				</div>
			</div>
			<script src="viewfinder/player.js"></script>
			</div>
			<?php endif; ?>

			<table width="425" cellpadding="0" cellspacing="0" border="0" align="center">
				<tbody><tr>
					<td>
						<div class="watchDescription"><?php echo $description; ?></div>

						<div class="watchTags">Tags // <?php foreach ($tagList as $tag): ?><a href="results.php?search=<?php echo rawurlencode($tag); ?>"><?php echo h($tag); ?></a> : <?php endforeach; ?></div>

						<div class="watchAdded">
						Added: <?php echo h(date('F j, Y', strtotime($video['uploaded']))); ?> by <a href="profile.php?user=<?php echo rawurlencode($video['username']); ?>"><?php echo h($video['username']); ?></a> //
						<a href="profile.php?user=<?php echo rawurlencode($video['username']); ?>&amp;tab=videos">Videos</a> (<?php echo $videos; ?>) | <a href="profile.php?user=<?php echo rawurlencode($video['username']); ?>&amp;tab=favorites">Favorites</a> (<?php echo $favorites; ?>)
						</div>

						<div class="watchDetails">
						Views: <?php echo number_format((int)$video['views']); ?> | <a href="#comment">Comments</a>: <?php echo number_format($commentc); ?>
						</div>

						<?php if (!empty($video['recorddate']) || !empty($video['address']) || !empty($video['addrcountry'])): ?>
						<br>
						<div class="watchDetails">
						<?php if (!empty($video['recorddate'])): ?>Recorded: <?php echo h(date('Y-m-d', strtotime($video['recorddate']))); ?> | <?php endif; ?>
						<?php if (!empty($video['address'])): ?>Location: <a href="http://maps.google.com/maps?t=h&amp;q=<?php echo rawurlencode($video['address']); ?>"><?php echo h($video['address']); ?></a> | <?php endif; ?>
						<?php if (!empty($video['addrcountry'])): ?>Country: <?php echo h($video['addrcountry']); ?><?php endif; ?>
						</div>
						<?php endif; ?>
					</td>
				</tr>
			</tbody></table>

			<?php if ((int)$video['privacy'] === 1 && (int)$video['converted'] === 1): ?>
			<div style="padding: 15px 0px 10px 0px;">
			<table width="100%" align="center" cellpadding="0" cellspacing="0" border="0" bgcolor="#E5ECF9">
				<tbody>
				<tr>
					<td><img src="img/box_login_tl.gif" width="5" height="5" alt=""></td>
					<td width="100%"><img src="img/pixel.gif" width="1" height="5" alt=""></td>
					<td><img src="img/box_login_tr.gif" width="5" height="5" alt=""></td>
				</tr>
				<tr>
					<td><img src="img/pixel.gif" width="5" height="1" alt=""></td>
					<td align="center">
						<div style="font-size: 11px; font-weight: bold; color: #CC6600; padding: 5px 0px 5px 0px;">Share this video! Copy and paste this link:</div>
						<div style="font-size: 11px; padding-bottom: 15px;">
							<input type="text" value="<?php echo h($shareUrl); ?>" size="50" readonly="readonly" onclick="this.focus();this.select();" style="font-size: 10px; text-align: center;">
						</div>
					</td>
					<td><img src="img/pixel.gif" width="5" height="1" alt=""></td>
				</tr>
				<tr>
					<td><img src="img/box_login_bl.gif" width="5" height="5" alt=""></td>
					<td><img src="img/pixel.gif" width="1" height="5" alt=""></td>
					<td><img src="img/box_login_br.gif" width="5" height="5" alt=""></td>
				</tr>
			</tbody></table>
			</div>
			<?php endif; ?>

			<?php if ($isStaff): ?>
			<div>
				<strong>ManagerFilm:</strong>
				<a href="admin/index.php" onclick="document.forms['watch_feature_video'].submit(); return false;"><?php echo $isFeatured ? 'Unfeature Video' : 'Feature Video'; ?></a>
				<form name="watch_feature_video" method="post" action="admin/feature_video.php" style="display:none;">
					<input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
					<input type="hidden" name="video_id" value="<?php echo h($vid); ?>">
					<input type="hidden" name="action" value="<?php echo $isFeatured ? 'unfeature' : 'feature'; ?>">
					<input type="hidden" name="return_video" value="<?php echo h($vid); ?>">
				</form>
				|
				<a href="admin/videos_remove.php" onclick="if (confirm('Permanently remove this video and its comments, favorites, and files?')) { document.forms['watch_remove_video'].submit(); } return false;">Remove Video</a>
				<form name="watch_remove_video" method="post" action="admin/videos_remove.php" style="display:none;">
					<input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
					<input type="hidden" name="video_id" value="<?php echo h($vid); ?>">
					<input type="hidden" name="confirm_remove" value="yes">
				</form>
				<?php if ($managerCastNotice !== ''): ?>
					<br><span class="<?php echo in_array((string)($_GET['featured'] ?? ''), ['error', 'unavailable'], true) ? 'error' : 'success'; ?>"><?php echo h($managerCastNotice); ?></span>
				<?php endif; ?>
			</div>
			<?php endif; ?>

			<a name="comment"></a>

			<?php if (isset($_GET['comment']) && $_GET['comment'] === 'added'): ?>
				<p class="success">Your comment has been added.</p>
			<?php elseif (isset($_GET['comment']) && $_GET['comment'] === 'suspended'): ?>
				<p class="error">Your account has been suspended for 3 hours for posting comments too quickly.</p>
			<?php elseif (isset($_GET['comment'])): ?>
				<p class="error">Please sign in and enter a comment of 1 to 1000 characters.</p>
			<?php endif; ?>

			<?php if ((int)$video['allow_comments'] === 1): ?>
			<div style="padding-bottom: 5px; font-weight: bold; color: #444;">Comment on this video:</div>

			<?php if ($sessionUid !== ''): ?>
			<form name="comment_form" id="comment_form" method="post" action="add_comment.php">
				<input type="hidden" name="video_id" value="<?php echo h($vid); ?>">
				<input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
				<textarea name="comment" cols="55" rows="3" maxlength="1000"></textarea>
				<br>
				<input type="submit" name="comment_button" value="Add Comment">
			</form>
			<?php else: ?>
			<p><a href="login.php">Log in</a> to comment on this video.</p>
			<?php endif; ?>
			<?php else: ?>
			<p>Comments are disabled for this video.</p>
			<?php endif; ?>

			<br>
			<div class="commentsTitle">Comments (<?php echo number_format($commentc); ?>):</div>

			<?php foreach ($comments as $comment): ?>
			<div class="commentsEntry">
			<?php if ((int)$comment['removed'] === 1): ?>----- Comment deleted by user -----<?php else: ?>
			&quot;<?php echo nl2br(h($comment['body'])); ?>&quot;
			<?php endif; ?><br>
			- <a href="profile.php?user=<?php echo rawurlencode($comment['username']); ?>"><?php echo h($comment['username']); ?></a> // <a href="profile.php?user=<?php echo rawurlencode($comment['username']); ?>&amp;tab=videos">Videos</a> (<?php echo (int)$comment['user_videos']; ?>) | <a href="profile.php?user=<?php echo rawurlencode($comment['username']); ?>&amp;tab=favorites">Favorites</a> (<?php echo (int)$comment['user_favorites']; ?>) - (<?php echo h(date('M j, Y g:i a', strtotime($comment['post_date']))); ?>)
			<?php if ((int)$comment['removed'] === 0 && ($sessionUid !== '' && $sessionUid === (string)$comment['uid'] || $isStaff)): ?>
			&nbsp;<input type="submit" form="remove_comment_<?php echo h($comment['cid']); ?>" id="<?php echo h($comment['cid']); ?>" value="Remove Comment">
			<form method="post" action="remove_comment.php" id="remove_comment_<?php echo h($comment['cid']); ?>">
				<input type="hidden" name="video_id" value="<?php echo h($vid); ?>">
				<input type="hidden" name="comment_id" value="<?php echo h($comment['cid']); ?>">
				<input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
			</form>
			<?php endif; ?>
			</div>
			<?php endforeach; ?>
			<?php if (!$comments): ?><div class="commentsEntry">No comments yet.</div><?php endif; ?>

			</td>

			<td width="300">
			<table width="300" align="center" cellpadding="0" cellspacing="0" border="0" bgcolor="#CCCCCC">
				<tbody>
				<tr>
					<td><img src="img/box_login_tl.gif" width="5" height="5" alt=""></td>
					<td><img src="img/pixel.gif" width="1" height="5" alt=""></td>
					<td><img src="img/box_login_tr.gif" width="5" height="5" alt=""></td>
				</tr>
				<tr>
					<td><img src="img/pixel.gif" width="5" height="1" alt=""></td>
					<td width="290">
					<div class="moduleTitleBar">
					<table width="290" cellpadding="0" cellspacing="0" border="0">
						<tbody><tr valign="top">
							<td><div class="moduleFrameBarTitle">Tag // <?php echo h(implode(' ', $tagList)); ?> (<?php echo $related_vid_count; ?>)</div></td>
							<td align="right"><div style="font-size: 11px; margin-right: 5px;"><a href="results.php?search=<?php echo rawurlencode($video['tags']); ?>">See more Results</a></div></td>
						</tr></tbody>
					</table>
					</div>

					<iframe id="side_results" name="side_results" src="include_results.php?v=<?php echo h($vid); ?>&amp;search=<?php echo rawurlencode($video['tags']); ?>#selected" scrolling="auto" width="290" height="400" frameborder="0" marginheight="0" marginwidth="0">
					[Content for browsers that don't support iframes goes here]
					</iframe>
					</td>
					<td><img src="img/pixel.gif" width="5" height="1" alt=""></td>
				</tr>
				<tr>
					<td><img src="img/box_login_bl.gif" width="5" height="5" alt=""></td>
					<td><img src="img/pixel.gif" width="1" height="5" alt=""></td>
					<td><img src="img/box_login_br.gif" width="5" height="5" alt=""></td>
				</tr>
			</tbody></table>

			<div style="font-weight: bold; color: #333; margin: 10px 0px 5px 0px;">Related tags:</div>
			<?php foreach ($related_tags as $tag): ?>
			<div style="padding: 0px 0px 5px 0px; color: #999;">&#187; <a href="results.php?search=<?php echo rawurlencode($tag); ?>"><?php echo h($tag); ?></a></div>
			<?php endforeach; ?>
			</td>
		</tr>
	</tbody></table>

	<br>
	<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
