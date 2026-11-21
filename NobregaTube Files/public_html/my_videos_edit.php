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



$videoId = substr((string)($_GET['video_id'] ?? $_POST['video_id'] ?? ''), 0, 20);
$videoQuery = $pdo->prepare('SELECT * FROM videos WHERE vid = ? AND uid = ? LIMIT 1');
$videoQuery->execute([$videoId, $currentUser['uid']]);
$video = $videoQuery->fetch();
if (!$video) {
	http_response_code(404);
	exit('Video not found.');
}

$errors = [];
$saved = isset($_GET['saved']);
$title = (string)$video['title'];
$description = (string)$video['description'];
$tags = (string)$video['tags'];
$address = (string)($video['address'] ?? '');
$country = (string)($video['addrcountry'] ?? '');
$privacy = (int)$video['privacy'];
$allowComments = isset($video['allow_comments']) ? (int)$video['allow_comments'] : 1;
$month = $video['recorddate'] ? date('n', strtotime($video['recorddate'])) : '---';
$day = $video['recorddate'] ? date('j', strtotime($video['recorddate'])) : '---';
$year = $video['recorddate'] ? date('Y', strtotime($video['recorddate'])) : '---';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$title = trim((string)($_POST['field_upload_title'] ?? ''));
	$description = trim((string)($_POST['field_upload_description'] ?? ''));
	$tags = trim((string)($_POST['field_upload_tags'] ?? ''));
	$address = trim((string)($_POST['field_upload_address'] ?? ''));
	$country = (string)($_POST['field_upload_country'] ?? '');
	$privacy = (int)($_POST['private'] ?? 0);
	$allowComments = (int)($_POST['allow_comments'] ?? -1);
	$month = (string)($_POST['addr_month'] ?? '---');
	$day = (string)($_POST['addr_day'] ?? '---');
	$year = (string)($_POST['addr_yr'] ?? '---');

	if (!hash_equals((string)$_SESSION['csrf_token'], (string)($_POST['csrf_token'] ?? ''))) {
		$errors[] = 'Your form session expired. Please refresh the page and try again.';
	}
	if ($title === '' || strlen($title) < 2 || strlen($title) > 100) {
		$errors[] = 'Title must be between 2 and 100 characters.';
	}
	if ($description === '' || strlen($description) > 1000) {
		$errors[] = 'Description is required and must be 1000 characters or less.';
	}
	$tagWords = preg_split('/\s+/', $tags, -1, PREG_SPLIT_NO_EMPTY);
	if (strlen($tags) > 900 || count(array_unique(array_map('strtolower', $tagWords))) < 2) {
		$errors[] = 'Enter at least two different tags, separated by spaces.';
	}
	if (strlen($address) > 255) {
		$errors[] = 'Address must be 255 characters or less.';
	}
	if ($country !== '' && !isset($countries[$country])) {
		$errors[] = 'Please select a valid country.';
	}
	if (!in_array($privacy, [1, 2], true)) {
		$errors[] = 'Please select a valid broadcast setting.';
	}
	if (!in_array($allowComments, [0, 1], true)) {
		$errors[] = 'Please select whether comments are allowed.';
	}

	$recordedDate = null;
	$dateParts = [$month, $day, $year];
	if ($dateParts !== ['---', '---', '---']) {
		if (!ctype_digit($month) || !ctype_digit($day) || !ctype_digit($year) || !checkdate((int)$month, (int)$day, (int)$year)) {
			$errors[] = 'Please enter a valid recording date or leave it blank.';
		} else {
			$recordedDate = sprintf('%04d-%02d-%02d', (int)$year, (int)$month, (int)$day);
		}
	}

	if (!$errors) {
		$update = $pdo->prepare('UPDATE videos
			SET title = ?, description = ?, tags = ?, recorddate = ?, address = ?, addrcountry = ?, privacy = ?, allow_comments = ?
			WHERE vid = ? AND uid = ?');
		$update->execute([
			$title,
			$description,
			$tags,
			$recordedDate,
			$address !== '' ? $address : null,
			$country !== '' ? $country : null,
			$privacy,
			$allowComments,
			$videoId,
			$currentUser['uid'],
		]);
		header('Location: my_videos_edit.php?video_id=' . rawurlencode($videoId) . '&saved=1');
		exit;
	}
}

$siteHost = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
if (!preg_match('/^[A-Za-z0-9.-]+(?::[0-9]{1,5})?$/', $siteHost)) {
	$siteHost = 'localhost';
}
$videoUrl = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://') . $siteHost . '/watch.php?v=' . rawurlencode($videoId);
$months = [1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Aug', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec'];
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html lang="en">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
	<title>NobregaTube - Video Details</title>
	<link rel="stylesheet" href="styles2005july.css">
</head>
<body>
	<?php $activeHeaderPage = 'videos'; $showSubNav = true; include __DIR__ . '/includes/header.php'; ?>

	<div class="formTitle">Video Details <span style="float:right; font-size: 12px; font-weight: normal;"><a href="my_videos.php">Back to &quot;My Videos&quot;</a></span></div>
	<?php if ($saved): ?><p class="success">Video has been updated!</p><?php endif; ?>
	<?php foreach ($errors as $error): ?>
		<table width="100%" align="center" bgcolor="#FF0000" cellpadding="6" cellspacing="3" border="0"><tbody><tr><td align="center" bgcolor="#FFFFFF"><span class="error"><?php echo h($error); ?></span></td></tr></tbody></table><br>
	<?php endforeach; ?>

	<div class="pageTable">
		<form method="post" action="my_videos_edit.php?video_id=<?php echo rawurlencode($videoId); ?>">
			<input type="hidden" name="video_id" value="<?php echo h($videoId); ?>">
			<input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
			<input type="hidden" name="field_command" value="update_video">
			<table width="100%" cellpadding="5" cellspacing="0" border="0">
				<tbody>
					<tr><td width="200" align="right"><span class="label">Title:</span></td><td><input type="text" size="30" maxlength="100" name="field_upload_title" value="<?php echo h($title); ?>"></td></tr>
					<tr><td align="right" valign="top"><span class="label">Description:</span></td><td><textarea name="field_upload_description" cols="40" rows="4" maxlength="1000"><?php echo h($description); ?></textarea></td></tr>
					<tr><td width="200" align="right"><span class="label">Tags:</span></td><td><input type="text" size="50" maxlength="900" name="field_upload_tags" value="<?php echo h($tags); ?>"></td></tr>
				</tbody>
			</table>
			<div class="formFieldInfo"><strong>Enter two or more tags, separated by spaces.</strong> Tags describe your video so it can be searched and organized.</div>

			<div class="formTitle">Date &amp; Address Details</div>
			<table width="100%" cellpadding="5" cellspacing="0" border="0">
				<tbody>
					<tr>
						<td width="200" align="right"><span class="label">Date Recorded:</span><br><span class="formFieldInfo">(Optional)</span></td>
						<td>
							<select name="addr_month"><option value="---"<?php if ($month === '---') echo ' selected'; ?>>---</option><?php foreach ($months as $number => $monthName): ?><option value="<?php echo $number; ?>"<?php if ((string)$number === $month) echo ' selected'; ?>><?php echo $monthName; ?></option><?php endforeach; ?></select>
							<select name="addr_day"><option value="---"<?php if ($day === '---') echo ' selected'; ?>>---</option><?php for ($dayOption = 1; $dayOption <= 31; $dayOption++): ?><option value="<?php echo $dayOption; ?>"<?php if ((string)$dayOption === $day) echo ' selected'; ?>><?php echo $dayOption; ?></option><?php endfor; ?></select>
							<select name="addr_yr"><option value="---"<?php if ($year === '---') echo ' selected'; ?>>---</option><?php for ($yearOption = (int)date('Y'); $yearOption >= 1900; $yearOption--): ?><option value="<?php echo $yearOption; ?>"<?php if ((string)$yearOption === $year) echo ' selected'; ?>><?php echo $yearOption; ?></option><?php endfor; ?></select>
						</td>
					</tr>
					<tr><td width="200" align="right"><span class="label">Address Recorded:</span><br><span class="formFieldInfo">(Optional)</span></td><td><input type="text" size="50" maxlength="255" name="field_upload_address" value="<?php echo h($address); ?>"></td></tr>
					<tr><td width="200" align="right"><span class="label">Country:</span><br><span class="formFieldInfo">(Optional)</span></td><td><select name="field_upload_country"><?php foreach ($countries as $code => $countryName): ?><option value="<?php echo h((string)$code); ?>"<?php if ((string)$code === $country) echo ' selected'; ?>><?php echo h((string)$countryName); ?></option><?php endforeach; ?></select></td></tr>
				</tbody>
			</table>

			<div class="formTitle">Sharing</div>
			<table width="100%" cellpadding="5" cellspacing="0" border="0">
				<tbody>
					<tr><td width="200" align="right"><span class="label">Video URL:</span></td><td><input type="text" value="<?php echo h($videoUrl); ?>" size="50" readonly onclick="this.focus();this.select();"></td></tr>
					<tr>
						<td width="200" align="right" valign="top"><span class="label">Broadcast:</span></td>
						<td>
							<label><input type="radio" name="private" value="1"<?php if ($privacy === 1) echo ' checked'; ?>> <strong>Public</strong>: Share your video with the world! (Recommended)</label><br>
							<label><input type="radio" name="private" value="2"<?php if ($privacy === 2) echo ' checked'; ?>> <strong>Private</strong>: Only viewable by you.</label>
						</td>
					</tr>
					<tr>
						<td width="200" align="right" valign="top"><span class="label">Allow Comments:</span></td>
						<td>
							<label><input type="radio" name="allow_comments" value="1"<?php if ($allowComments === 1) echo ' checked'; ?>> <strong>Yes</strong>: Allow comments to be added to your video.</label><br>
							<label><input type="radio" name="allow_comments" value="0"<?php if ($allowComments === 0) echo ' checked'; ?>> <strong>No</strong>: Disallow comments to be added to your video.</label>
						</td>
					</tr>
					<tr><td>&nbsp;</td><td><input type="submit" value="Update Video"></td></tr>
				</tbody>
			</table>
		</form>
	</div>

	<br>
	<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
