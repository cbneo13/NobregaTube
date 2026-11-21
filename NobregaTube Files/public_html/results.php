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

$search = trim((string)($_GET['search'] ?? ''));
$related = trim((string)($_GET['related'] ?? ''));
$isRelatedSearch = $search === '' && $related !== '';
$queryText = $isRelatedSearch ? $related : $search;
if (strlen($queryText) > 128) {
	$queryText = substr($queryText, 0, 128);
}
$page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$page = $page === false ? 1 : $page;
$perPage = 10;
$videos = [];
$relatedTags = [];
$totalResults = 0;
$totalPages = 1;

if ($queryText !== '') {
	$terms = preg_split('/\s+/', $queryText, 8, PREG_SPLIT_NO_EMPTY);
	$whereParts = [];
	$parameters = [];
	foreach ($terms as $term) {
		$escapedTerm = strtr($term, ['\\' => '\\\\', '%' => '\\%', '_' => '\\_']);
		$pattern = '%' . $escapedTerm . '%';
		$whereParts[] = '(v.tags LIKE ? OR v.title LIKE ? OR v.description LIKE ? OR u.username LIKE ?)';
		array_push($parameters, $pattern, $pattern, $pattern, $pattern);
	}
	$whereSql = implode(' OR ', $whereParts);
	$baseSql = 'FROM videos v
		INNER JOIN users u ON u.uid = v.uid
		WHERE v.privacy = 1 AND v.converted = 1 AND u.termination = 0 AND (' . $whereSql . ')';

	$countQuery = $pdo->prepare('SELECT COUNT(*) ' . $baseSql);
	$countQuery->execute($parameters);
	$totalResults = (int)$countQuery->fetchColumn();
	$totalPages = max(1, (int)ceil($totalResults / $perPage));
	$page = min($page, $totalPages);
	$offset = ($page - 1) * $perPage;

	if ($isRelatedSearch) {
		$orderBy = '(INSTR(LOWER(v.tags), LOWER(?)) > 0) DESC, (INSTR(LOWER(v.description), LOWER(?)) > 0) DESC, v.uploaded DESC';
		$resultsParameters = array_merge($parameters, [$queryText, $queryText]);
	} else {
		$orderBy = 'v.uploaded DESC';
		$resultsParameters = $parameters;
	}

	$resultsQuery = $pdo->prepare('SELECT v.vid, v.title, v.description, v.tags, v.uploaded, v.views, v.duration, v.recorddate, v.address, v.addrcountry, u.username,
		(SELECT COUNT(*) FROM comments c WHERE c.vid = v.vid AND c.removed = 0) AS comment_count
		' . $baseSql . '
		ORDER BY ' . $orderBy . '
		LIMIT ' . $perPage . ' OFFSET ' . $offset);
	$resultsQuery->execute($resultsParameters);
	$videos = $resultsQuery->fetchAll();

	foreach ($videos as $video) {
		foreach (preg_split('/\s+/', trim((string)$video['tags']), -1, PREG_SPLIT_NO_EMPTY) as $tag) {
			$relatedTags[strtolower($tag)] = $tag;
		}
	}
	$relatedTags = array_values($relatedTags);
}

$displayTitle = $isRelatedSearch ? 'Tag' : 'Search';
$paginationQuery = $isRelatedSearch ? ['related' => $queryText] : ['search' => $queryText];
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html lang="en">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
	<title>NobregaTube - Search Results</title>
	<link rel="stylesheet" href="styles2005july.css">
</head>
<body>
	<?php $activeHeaderPage = ''; $showSubNav = true; include __DIR__ . '/includes/header.php'; ?>

	<?php if ($queryText === ''): ?>
		<div class="pageTitle">Search Results</div>
		<p>Enter one or more keywords to search videos.</p>
	<?php else: ?>
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
									<div class="moduleTitleBar">
										<table width="100%" cellpadding="0" cellspacing="0" border="0">
											<tbody><tr valign="top">
												<td><div class="moduleTitle"><?php echo h($displayTitle); ?> // <?php echo h($queryText); ?> (<?php echo $totalResults; ?> result<?php echo $totalResults === 1 ? '' : 's'; ?>)</div></td>
											</tr></tbody>
										</table>
									</div>

									<?php foreach ($videos as $video): ?>
										<div class="moduleEntry">
											<table width="100%" cellpadding="0" cellspacing="0" border="0">
												<tbody><tr valign="top">
													<td>
														<table cellpadding="0" cellspacing="0" border="0">
															<tbody><tr>
																<?php for ($stillId = 1; $stillId <= 3; $stillId++): ?>
																	<td><a href="watch.php?v=<?php echo rawurlencode($video['vid']); ?>&amp;search=<?php echo rawurlencode($queryText); ?>"><img src="get_still.php?video_id=<?php echo rawurlencode($video['vid']); ?>&amp;still_id=<?php echo $stillId; ?>" class="moduleEntryThumb" width="100" height="75" alt="<?php echo h($video['title']); ?>"></a></td>
																<?php endfor; ?>
															</tr></tbody>
														</table>
													</td>
													<td width="100%">
														<div class="moduleEntryTitle" style="word-break: break-all;"><a href="watch.php?v=<?php echo rawurlencode($video['vid']); ?>&amp;search=<?php echo rawurlencode($queryText); ?>"><?php echo h($video['title']); ?></a></div>
														<div class="moduleEntryDescription"><?php
															$description = (string)$video['description'];
															if (strlen($description) > 100) {
																$description = substr($description, 0, 100) . '...';
															}
															echo nl2br(h($description));
														?></div>
														<div class="moduleEntryTags">Tags // <?php foreach (preg_split('/\s+/', trim((string)$video['tags']), -1, PREG_SPLIT_NO_EMPTY) as $tag): ?><a href="results.php?search=<?php echo rawurlencode($tag); ?>"><?php echo h($tag); ?></a> : <?php endforeach; ?></div>
														<div class="moduleEntryDetails">Added: <?php echo h(date('F j, Y', strtotime($video['uploaded']))); ?> by <a href="profile.php?user=<?php echo rawurlencode($video['username']); ?>"><?php echo h($video['username']); ?></a></div>
														<div class="moduleEntryDetails">Runtime: <?php echo (int)$video['duration'] > 0 ? gmdate('i:s', (int)$video['duration']) : '--:--'; ?> | Views: <?php echo number_format((int)$video['views']); ?> | Comments: <?php echo (int)$video['comment_count']; ?></div>
													</td>
												</tr></tbody>
											</table>
										</div>
									<?php endforeach; ?>

									<?php if (!$videos): ?>
										<div class="moduleEntry">
											<div class="highlight">Found no videos matching &quot;<?php echo h($queryText); ?>&quot;. Do you have one? <a href="my_videos_upload.php">Upload</a> it!</div>
										</div>
									<?php endif; ?>

									<?php if ($totalPages > 1): ?>
										<div style="font-size: 13px; font-weight: bold; color: #444; text-align: right; padding: 5px;">
											Result Page:
											<?php
											$pagesPerSet = 7;
											$startPage = (int)(floor(($page - 1) / $pagesPerSet) * $pagesPerSet) + 1;
											$endPage = min($startPage + $pagesPerSet - 1, $totalPages);
											?>
											<?php if ($page > 1): ?>
												<a href="results.php?<?php echo http_build_query($paginationQuery); ?>&amp;page=<?php echo $page - 1; ?>">&lt; Previous</a>
											<?php endif; ?>
											<?php for ($pageNumber = $startPage; $pageNumber <= $endPage; $pageNumber++): ?>
												<?php if ($pageNumber === $page): ?>
													<span style="color: #444; background-color: #FFFFFF; padding: 1px 4px; border: 1px solid #999; margin-right: 5px;"><?php echo $pageNumber; ?></span>
												<?php else: ?>
													<span style="background-color: #CCC; padding: 1px 4px; border: 1px solid #999; margin-right: 5px;"><a href="results.php?<?php echo http_build_query($paginationQuery); ?>&amp;page=<?php echo $pageNumber; ?>"><?php echo $pageNumber; ?></a></span>
												<?php endif; ?>
											<?php endfor; ?>
											<?php if ($page < $totalPages): ?>
												<a href="results.php?<?php echo http_build_query($paginationQuery); ?>&amp;page=<?php echo $page + 1; ?>">Next &gt;</a>
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
					<div style="font-weight: bold; margin-bottom: 3px; width: 160px;">Related Tags:</div>
					<?php foreach ($relatedTags as $tag): ?>
						<div style="padding: 0px 0px 5px 0px; color: #999;">&raquo; <a href="results.php?search=<?php echo rawurlencode($tag); ?>"><?php echo h($tag); ?></a></div>
					<?php endforeach; ?>
				</td>
			</tr></tbody>
		</table>
	<?php endif; ?>

	<br>
	<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
