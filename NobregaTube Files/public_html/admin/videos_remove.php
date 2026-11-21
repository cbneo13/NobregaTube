<?php
require_once __DIR__ . '/../includes/sess.php';

if (empty($_SESSION['uid'])) {
	header('Location: ../login.php');
	exit;
}

require __DIR__ . '/../includes/database.php';
$staffQuery = $pdo->prepare('SELECT uid, username, staff FROM users WHERE uid = ? LIMIT 1');
$staffQuery->execute([$_SESSION['uid']]);
$currentUser = $staffQuery->fetch();
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



$page = filter_var($_POST['page'] ?? $_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$page = $page === false ? 1 : $page;
$perPage = 20;
$status = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
	if (empty($_SESSION['csrf_token'])
		|| !hash_equals((string)$_SESSION['csrf_token'], (string)($_POST['csrf_token'] ?? ''))) {
		http_response_code(403);
		exit('Your form session expired. Refresh the page and try again.');
	}

	$videoId = (string)($_POST['video_id'] ?? '');
	if (!preg_match('/^[A-Za-z0-9_-]{1,20}$/', $videoId)
		|| (string)($_POST['confirm_remove'] ?? '') !== 'yes') {
		header('Location: videos_remove.php?page=' . $page . '&removed=error');
		exit;
	}

	$pdo->beginTransaction();
	try {
		$videoQuery = $pdo->prepare('SELECT file_path, flv_path FROM videos WHERE vid = ? FOR UPDATE');
		$videoQuery->execute([$videoId]);
		$video = $videoQuery->fetch();
		if (!$video) {
			$pdo->rollBack();
			header('Location: videos_remove.php?page=' . $page . '&removed=missing');
			exit;
		}

		$deleteQuery = $pdo->prepare('DELETE FROM videos WHERE vid = ?');
		$deleteQuery->execute([$videoId]);
		if ($deleteQuery->rowCount() !== 1) {
			throw new RuntimeException('The video record could not be removed.');
		}
		$pdo->commit();
	} catch (Throwable $error) {
		if ($pdo->inTransaction()) {
			$pdo->rollBack();
		}
		throw $error;
	}

	foreach (array_unique(array_filter([$video['file_path'], $video['flv_path']])) as $storedName) {
		$filename = basename((string)$storedName);
		if ($filename !== (string)$storedName || $filename === '' || !preg_match('/^[A-Za-z0-9._-]+$/', $filename)) {
			error_log('NobregaTube could not remove an admin-deleted video file with an invalid stored path.');
			continue;
		}
		$path = __DIR__ . '/../data/videos/' . $filename;
		if (is_file($path) && !unlink($path)) {
			error_log('NobregaTube could not remove admin-deleted video file: ' . $path);
		}
	}

	if (preg_match('/^[A-Za-z0-9_-]{1,20}$/', $videoId)) {
		foreach ([1, 2, 3] as $stillId) {
			$stillPath = __DIR__ . '/../data/thmbs/' . $videoId . '_' . $stillId . '.jpg';
			if (is_file($stillPath) && !unlink($stillPath)) {
				error_log('NobregaTube could not remove admin-deleted video thumbnail: ' . $stillPath);
			}
		}
	}

	header('Location: videos_remove.php?page=' . $page . '&removed=success');
	exit;
}

$countQuery = $pdo->query('SELECT COUNT(*) FROM videos');
$videoCount = (int)$countQuery->fetchColumn();
$totalPages = max(1, (int)ceil($videoCount / $perPage));
if ($page > $totalPages) {
	$page = $totalPages;
}
$offset = ($page - 1) * $perPage;
$videosQuery = $pdo->query('SELECT v.vid, v.title, v.uploaded, v.privacy, v.converted, v.views, u.username
	FROM videos v
	INNER JOIN users u ON u.uid = v.uid
	ORDER BY v.uploaded DESC
	LIMIT ' . $perPage . ' OFFSET ' . $offset);
$videos = $videosQuery->fetchAll();

$message = (string)($_GET['removed'] ?? '');
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html lang="en">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
	<title>ManagerFilm - Videos Remove</title>
	<link rel="stylesheet" href="../styles2005july.css">
	<style>
		.managercast-bar { width: 80%; margin: 20px auto; border: 1px solid #999; background: #eeeedd; }
		.managercast-bar td { padding: 9px; text-align: center; font-weight: bold; }
		.managercast-bar .managercast-brand { background: #D5E5F5; font-size: 20px; }
		.managercast-tabs { width: 80%; margin: 0px auto 20px auto; border-bottom: 1px solid #999; }
		.managercast-tabs td { padding: 8px 12px; background: #EEEEEE; font-weight: bold; }
		.managercast-tabs .managercast-tab-active { background: #D5E5F5; }
		.managercast-section { width: 80%; margin: 20px auto; }
		.managercast-actions { margin-top: 8px; }
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
			<td><a href="index.php">Featured Videos</a></td>
			<td><a href="message.php">Notice</a></td>
			<td class="managercast-tab-active">Videos Remove</td>
			<td><a href="user_ban.php">User Ban</a></td>
			<td><a href="user_suspend.php">Suspend User</a></td>
			<td><a href="contact_vault.php">Contact Vault</a></td>
		</tr></tbody>
	</table>

	<?php if ($message === 'success'): ?>
		<p class="success">Video removed.</p>
	<?php elseif ($message === 'missing'): ?>
		<p class="error">This video no longer exists.</p>
	<?php elseif ($message === 'error'): ?>
		<p class="error">The video was not removed. Please confirm the action.</p>
	<?php endif; ?>

	<div class="managercast-section">
		<div class="tableSubTitle">Videos Remove (<?php echo $videoCount; ?>)</div>
		<?php if (!$videos): ?>
			<div class="moduleEntry">There are no videos to remove.</div>
		<?php endif; ?>
		<?php foreach ($videos as $video): ?>
			<div class="moduleEntry">
				<table width="100%" cellpadding="0" cellspacing="0" border="0">
					<tbody><tr valign="top">
						<td width="140"><a href="../watch.php?v=<?php echo rawurlencode($video['vid']); ?>"><img src="../get_still.php?video_id=<?php echo rawurlencode($video['vid']); ?>" class="moduleEntryThumb" width="120" height="90" alt="<?php echo h($video['title']); ?>"></a></td>
						<td width="100%">
							<div class="moduleEntryTitle"><a href="../watch.php?v=<?php echo rawurlencode($video['vid']); ?>"><?php echo h($video['title']); ?></a></div>
							<div class="moduleEntryDetails">by <a href="../profile.php?user=<?php echo rawurlencode($video['username']); ?>"><?php echo h($video['username']); ?></a> | Added: <?php echo h(date('F j, Y', strtotime($video['uploaded']))); ?></div>
							<div class="moduleEntryDetails">Views: <?php echo number_format((int)$video['views']); ?> | <?php echo (int)$video['privacy'] === 1 ? 'Public' : 'Private'; ?> | <?php echo (int)$video['converted'] === 1 ? 'Processed' : 'Processing'; ?></div>
							<form class="managercast-actions" method="post" action="videos_remove.php" onsubmit="return confirm('Permanently remove this video and its comments, favorites, and files?');">
								<input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
								<input type="hidden" name="video_id" value="<?php echo h($video['vid']); ?>">
								<input type="hidden" name="page" value="<?php echo $page; ?>">
								<input type="hidden" name="confirm_remove" value="yes">
								<input type="submit" value="Remove Video">
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
					<a href="videos_remove.php?page=<?php echo $pageNumber; ?>"><?php echo $pageNumber; ?></a>
				<?php endif; ?>
			<?php endfor; ?>
		</div>
	<?php endif; ?>

	<br>
	<?php $footerPrefix = '../'; include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
