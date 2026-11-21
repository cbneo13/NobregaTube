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



if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
	if (!hash_equals((string)$_SESSION['csrf_token'], (string)($_POST['csrf_token'] ?? ''))) {
		http_response_code(403);
		exit('Your form session expired. Refresh the page and try again.');
	}

	$message = trim((string)($_POST['message'] ?? ''));
	$messageLength = preg_match_all('/./us', $message, $messageCharacters);
	if ($messageLength === false) {
		http_response_code(400);
		exit('The announcement must be valid UTF-8 text.');
	}
	if ($messageLength > 1000) {
		http_response_code(400);
		exit('The announcement must be 1000 characters or fewer.');
	}

	if ($message === '') {
		$pdo->prepare('DELETE FROM site_notice WHERE notice_id = 1')->execute();
		header('Location: message.php?saved=cleared');
		exit;
	}

	$saveQuery = $pdo->prepare('INSERT INTO site_notice (notice_id, message)
		VALUES (1, ?)
		ON DUPLICATE KEY UPDATE message = VALUES(message)');
	$saveQuery->execute([$message]);
	header('Location: message.php?saved=1');
	exit;
}

$noticeQuery = $pdo->query('SELECT message FROM site_notice WHERE notice_id = 1 LIMIT 1');
$notice = (string)($noticeQuery->fetchColumn() ?: '');
$saved = (string)($_GET['saved'] ?? '');
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html lang="en">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
	<title>ManagerFilm - Notice</title>
	<link rel="stylesheet" href="../styles2005july.css">
	<style>
		.managercast-bar { width: 80%; margin: 20px auto; border: 1px solid #999; background: #eeeedd; }
		.managercast-bar td { padding: 9px; text-align: center; font-weight: bold; }
		.managercast-bar .managercast-brand { background: #D5E5F5; font-size: 20px; }
		.managercast-tabs { width: 80%; margin: 0px auto 20px auto; border-bottom: 1px solid #999; }
		.managercast-tabs td { padding: 8px 12px; background: #EEEEEE; font-weight: bold; }
		.managercast-tabs .managercast-tab-active { background: #D5E5F5; }
		.managercast-section { width: 80%; margin: 20px auto; }
	</style>
</head>
<body>
	<?php $sitePrefix = '../'; $activeHeaderPage = ''; $showSubNav = true; include __DIR__ . '/../includes/header.php'; ?>

	<div class="pageTitle">ManagerFilm // Notice</div>
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
			<td class="managercast-tab-active">Notice</td>
			<td><a href="videos_remove.php">Videos Remove</a></td>
			<td><a href="user_ban.php">User Ban</a></td>
			<td><a href="user_suspend.php">Suspend User</a></td>
			<td><a href="contact_vault.php">Contact Vault</a></td>
		</tr></tbody>
	</table>

	<div class="managercast-section">
		<?php if ($saved === '1'): ?><p class="success">Notice saved.</p>
		<?php elseif ($saved === 'cleared'): ?><p class="success">Notice removed.</p><?php endif; ?>
		<div class="tableSubTitle">Site Notice</div>
		<p>Leave this blank to hide the notice.</p>
		<form method="post" action="message.php">
			<textarea name="message" rows="4" cols="70" maxlength="1000"><?php echo h($notice); ?></textarea>
			<br>
			<input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
			<input type="submit" value="Save Notice">
		</form>
	</div>

	<br>
	<?php $footerPrefix = '../'; include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
