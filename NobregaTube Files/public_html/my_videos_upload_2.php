<?php
require_once __DIR__ . '/includes/sess.php';
require __DIR__ . '/includes/database.php';

$currentUser = null;
$unreadMessages = 0;
if (!empty($_SESSION['uid'])) {
	$userQuery = $pdo->prepare('SELECT uid, username FROM users WHERE uid = ?');
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

if (empty($_SESSION['pending_upload']) || !is_array($_SESSION['pending_upload'])) {
	header('Location: my_videos_upload.php');
	exit;
}

$pendingUpload = $_SESSION['pending_upload'];
$title = $pendingUpload['title'];
$description = $pendingUpload['description'];
$tags = $pendingUpload['tags'];
$address = $pendingUpload['address'];
$country = $pendingUpload['country'];
$month = $pendingUpload['month'];
$day = $pendingUpload['day'];
$year = $pendingUpload['year'];
$errors = [];
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html lang="en">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
	<title>NobregaTube - Video Upload</title>
	<link rel="stylesheet" href="styles2005july.css">
</head>
<body>
	<?php
	$activeHeaderPage = 'upload';
	include __DIR__ . '/includes/header_search.php';
	?>

	<?php foreach ($errors as $error): ?>
		<table width="100%" align="center" bgcolor="#FF0000" cellpadding="6" cellspacing="3" border="0">
			<tbody><tr><td align="center" bgcolor="#FFFFFF"><span class="error"><?php echo h($error); ?></span></td></tr></tbody>
		</table><br>
	<?php endforeach; ?>

	<div class="form-title">Video Upload (Step 2 of 2)</div>
	<div class="page-table">
		<form method="post" action="my_videos_post.php" enctype="multipart/form-data">
			<input type="hidden" name="field_command" value="upload_video">
			<input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
			<input type="hidden" name="field_upload_title" value="<?php echo h($title); ?>">
			<input type="hidden" name="field_upload_description" value="<?php echo h($description); ?>">
			<input type="hidden" name="field_upload_tags" value="<?php echo h($tags); ?>">
			<input type="hidden" name="field_upload_address" value="<?php echo h($address); ?>">
			<input type="hidden" name="field_upload_country" value="<?php echo h($country); ?>">
			<input type="hidden" name="addr_month" value="<?php echo h($month); ?>">
			<input type="hidden" name="addr_day" value="<?php echo h($day); ?>">
			<input type="hidden" name="addr_yr" value="<?php echo h($year); ?>">
			<table width="100%" cellpadding="5" cellspacing="0" border="0">
				<tbody>
					<tr>
						<td width="200" align="right" valign="top"><span class="label">File:</span></td>
						<td>
							<div class="form-highlight">
								<input type="file" name="fileToUpload"><br>
								<span class="form-highlight-text"><b>Max file size: 100 MB. No copyrighted or obscene material.</b></span><br>
								<span class="form-highlight-text">After uploading, you can edit or remove this video at anytime under the &quot;My Videos&quot; link on top of the page.</span>
							</div>
						</td>
					</tr>
					<tr>
						<td width="200" align="right" valign="top"><span class="label">Broadcast:</span></td>
						<td>
							<input type="radio" name="private" value="1" checked><strong>Public</strong>: Share your video with the world! (Recommended)<br>
							<input type="radio" name="private" value="2"><strong>Private</strong>: Only viewable by you and the people you specify.
						</td>
					</tr>
				</tbody>
			</table>
			<div class="upload-notice"><b>PLEASE BE PATIENT, THIS MAY TAKE SEVERAL MINUTES.<br>ONCE COMPLETED, YOU WILL SEE A CONFIRMATION MESSAGE.</b></div>
			<div class="upload-actions">
				<input type="submit" value="Upload Video">
				<br><br><a href="my_videos_upload.php">Return to Step 1</a>
			</div>
		</form>
	</div>

	<?php include __DIR__ . '/includes/footer.php'; ?>
