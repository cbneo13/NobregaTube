<?php
require __DIR__ . '/includes/database.php';



$mode = '';
$queryText = '';
$userName = '';
$singleVideo = null;
$videos = [];
$totalVideos = 0;
$listError = '';

if (isset($_GET['id']) && (string)$_GET['id'] !== '') {
	$mode = 'id';
	$videoId = substr((string)$_GET['id'], 0, 20);
	$videoQuery = $pdo->prepare('SELECT v.vid, v.title, v.uploaded, v.views, u.username,
		(SELECT COUNT(*) FROM comments c WHERE c.vid = v.vid AND c.removed = 0) AS comment_count
		FROM videos v
		INNER JOIN users u ON u.uid = v.uid
		WHERE v.vid = ? AND v.privacy = 1 AND v.converted = 1 AND u.termination = 0
		LIMIT 1');
	$videoQuery->execute([$videoId]);
	$singleVideo = $videoQuery->fetch();
	if (!$singleVideo) {
		http_response_code(404);
		$listError = 'Video not found.';
	}
} elseif (isset($_GET['tag']) && trim((string)$_GET['tag']) !== '') {
	$mode = 'tag';
	$queryText = substr(trim((string)$_GET['tag']), 0, 128);
	$terms = preg_split('/\s+/', $queryText, 8, PREG_SPLIT_NO_EMPTY);
	$whereParts = [];
	$parameters = [];
	foreach ($terms as $term) {
		$escapedTerm = strtr($term, ['\\' => '\\\\', '%' => '\\%', '_' => '\\_']);
		$pattern = '%' . $escapedTerm . '%';
		$whereParts[] = '(v.tags LIKE ? OR v.description LIKE ? OR v.title LIKE ? OR u.username LIKE ?)';
		array_push($parameters, $pattern, $pattern, $pattern, $pattern);
	}
	$whereSql = implode(' OR ', $whereParts);
	$baseSql = 'FROM videos v
		INNER JOIN users u ON u.uid = v.uid
		WHERE v.privacy = 1 AND v.converted = 1 AND u.termination = 0
		AND (' . $whereSql . ')';
	$countQuery = $pdo->prepare('SELECT COUNT(*) ' . $baseSql);
	$countQuery->execute($parameters);
	$totalVideos = (int)$countQuery->fetchColumn();

	$videoQuery = $pdo->prepare('SELECT v.vid, v.title, v.uploaded, v.views, u.username,
		(SELECT COUNT(*) FROM comments c WHERE c.vid = v.vid AND c.removed = 0) AS comment_count
		' . $baseSql . '
		ORDER BY (INSTR(LOWER(v.description), LOWER(?)) > 0) DESC, v.uploaded DESC
		LIMIT 50');
	$videoQuery->execute(array_merge($parameters, [$queryText]));
	$videos = $videoQuery->fetchAll();
} elseif (isset($_GET['user']) && trim((string)$_GET['user']) !== '') {
	$mode = 'user';
	$userName = trim((string)$_GET['user']);
	if (!preg_match('/^[A-Za-z0-9_]{3,20}$/', $userName)) {
		http_response_code(400);
		$listError = 'Invalid username.';
	} else {
		$countQuery = $pdo->prepare('SELECT COUNT(*)
			FROM videos v
			INNER JOIN users u ON u.uid = v.uid
			WHERE u.username = ? AND v.privacy = 1 AND v.converted = 1 AND u.termination = 0');
		$countQuery->execute([$userName]);
		$totalVideos = (int)$countQuery->fetchColumn();

		$videoQuery = $pdo->prepare('SELECT v.vid, v.title, v.uploaded, v.views, u.username,
			(SELECT COUNT(*) FROM comments c WHERE c.vid = v.vid AND c.removed = 0) AS comment_count
			FROM videos v
			INNER JOIN users u ON u.uid = v.uid
			WHERE u.username = ? AND v.privacy = 1 AND v.converted = 1 AND u.termination = 0
			ORDER BY v.uploaded DESC
			LIMIT 50');
		$videoQuery->execute([$userName]);
		$videos = $videoQuery->fetchAll();
	}
} else {
	http_response_code(400);
	$listError = 'Choose a username, tag, or video ID to display.';
}

$seeMoreUrl = '';
if ($totalVideos > 50) {
	if ($mode === 'tag') {
		$seeMoreUrl = 'results.php?search=' . rawurlencode($queryText);
	} elseif ($mode === 'user') {
		$seeMoreUrl = 'profile.php?user=' . rawurlencode($userName) . '&tab=videos';
	}
}
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html lang="en">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<title>NobregaTube - Video List</title>
	<link rel="stylesheet" href="styles2005july.css">
</head>
<body style="background-color:#DDDDDD">
	<div style="font-weight:bold; padding:3px 5px; border-bottom:1px dashed #999;">
		<table width="100%" cellpadding="0" cellspacing="0" border="0">
			<tbody><tr>
				<td>
					<?php if ($mode === 'id' && $singleVideo): ?>
						NobregaTube // <?php echo h($singleVideo['title']); ?>
					<?php else: ?>
						My Videos // <span style="text-transform:capitalize;"><?php echo h($mode === 'user' ? $userName : $queryText); ?></span>
						<?php if ($listError === ''): ?>
							(<?php echo $totalVideos > 50 ? 'over 50' : $totalVideos; ?> <?php echo $totalVideos === 1 ? 'video' : 'videos'; ?>)
							<?php if ($seeMoreUrl !== ''): ?> // <a href="<?php echo h($seeMoreUrl); ?>" target="_parent">see more</a><?php endif; ?>
						<?php endif; ?>
					<?php endif; ?>
				</td>
				<td align="right"><strong><a href="index.php" target="_blank">NobregaTube</a></strong></td>
			</tr></tbody>
		</table>
	</div>

	<?php if ($listError !== ''): ?>
		<div class="moduleEntry"><?php echo h($listError); ?></div>
	<?php else: ?>
		<?php
		$displayVideos = $mode === 'id' ? [$singleVideo] : $videos;
		foreach ($displayVideos as $video):
		?>
			<div class="moduleEntry">
				<table width="100%" cellpadding="0" cellspacing="0" border="0">
					<tbody><tr valign="top">
						<td>
							<a href="watch.php?v=<?php echo rawurlencode($video['vid']); ?>" class="bold" target="_parent">
								<img src="get_still.php?video_id=<?php echo rawurlencode($video['vid']); ?>" class="moduleEntryThumb" width="80" height="60" alt="<?php echo h($video['title']); ?>">
							</a>
						</td>
						<td width="100%">
							<div class="moduleFrameTitle"><a href="watch.php?v=<?php echo rawurlencode($video['vid']); ?>" target="_parent"><?php echo h($video['title']); ?></a></div>
							<div class="moduleFrameDetails">Added: <?php echo h(date('F j, Y', strtotime($video['uploaded']))); ?><br>by <a href="profile.php?user=<?php echo rawurlencode($video['username']); ?>" target="_parent"><?php echo h($video['username']); ?></a></div>
							<div class="moduleFrameDetails">Views: <?php echo (int)$video['views']; ?><br>Comments: <?php echo (int)$video['comment_count']; ?></div>
						</td>
					</tr></tbody>
				</table>
			</div>
		<?php endforeach; ?>
		<?php if (!$displayVideos): ?><div class="moduleEntry">No videos found.</div><?php endif; ?>
	<?php endif; ?>
</body>
</html>
