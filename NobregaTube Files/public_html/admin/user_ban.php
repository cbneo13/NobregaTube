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
	if (empty($_SESSION['csrf_token'])
		|| !hash_equals((string)$_SESSION['csrf_token'], (string)($_POST['csrf_token'] ?? ''))) {
		http_response_code(403);
		exit('Your form session expired. Refresh the page and try again.');
	}

	$targetUid = substr((string)($_POST['user_id'] ?? ''), 0, 20);
	if ($targetUid === '' || $targetUid === (string)$currentUser['uid']) {
		header('Location: user_ban.php?result=protected');
		exit;
	}

	$banQuery = $pdo->prepare('UPDATE users SET termination = 1
		WHERE uid = ? AND staff = 0 AND termination = 0');
	$banQuery->execute([$targetUid]);
	$result = $banQuery->rowCount() === 1 ? 'banned' : 'protected';
	header('Location: user_ban.php?result=' . $result);
	exit;
}

$page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$page = $page === false ? 1 : $page;
$perPage = 20;
$countQuery = $pdo->query('SELECT COUNT(*) FROM users WHERE termination = 0 AND staff = 0');
$activeUserCount = (int)$countQuery->fetchColumn();
$totalPages = max(1, (int)ceil($activeUserCount / $perPage));
if ($page > $totalPages) {
	$page = $totalPages;
}
$offset = ($page - 1) * $perPage;
$usersQuery = $pdo->query('SELECT uid, username, joined
	FROM users
	WHERE termination = 0 AND staff = 0
	ORDER BY joined DESC
	LIMIT ' . $perPage . ' OFFSET ' . $offset);
$users = $usersQuery->fetchAll();
$result = (string)($_GET['result'] ?? '');
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html lang="en">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
	<title>ManagerFilm - User Ban</title>
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
			<td><a href="videos_remove.php">Videos Remove</a></td>
			<td class="managercast-tab-active">User Ban</td>
			<td><a href="user_suspend.php">Suspend User</a></td>
			<td><a href="contact_vault.php">Contact Vault</a></td>
		</tr></tbody>
	</table>

	<?php if ($result === 'banned'): ?>
		<p class="success">User banned.</p>
	<?php elseif ($result === 'protected'): ?>
		<p class="error">This account is already banned or cannot be banned.</p>
	<?php endif; ?>

	<div class="managercast-section">
		<div class="tableSubTitle">User Ban (<?php echo $activeUserCount; ?>)</div>
		<?php if (!$users): ?>
			<div class="moduleEntry">There are no active non-staff accounts to ban.</div>
		<?php endif; ?>
		<?php foreach ($users as $user): ?>
			<div class="moduleEntry">
				<table width="100%" cellpadding="0" cellspacing="0" border="0">
					<tbody><tr valign="top">
						<td width="100%">
							<div class="moduleEntryTitle"><a href="../profile.php?user=<?php echo rawurlencode($user['username']); ?>"><?php echo h($user['username']); ?></a></div>
							<div class="moduleEntryDetails">Joined: <?php echo h(date('F j, Y', strtotime($user['joined']))); ?></div>
						</td>
						<td align="right">
							<form method="get" action="ip_ban.php">
								<input type="hidden" name="user" value="<?php echo h($user['uid']); ?>">
								<input type="submit" value="IP Ban">
							</form>
							<form method="post" action="user_ban.php" onsubmit="return confirm('Ban this account? The user will be logged out and will no longer be able to sign in.');">
								<input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
								<input type="hidden" name="user_id" value="<?php echo h($user['uid']); ?>">
								<input type="submit" value="Ban User">
							</form>
						</td>
					</tr></tbody>
				</table>
			</div>
		<?php endforeach; ?>
	</div>

	<?php if ($totalPages > 1): ?>
		<div class="results-pagination">Users:
			<?php for ($pageNumber = 1; $pageNumber <= $totalPages; $pageNumber++): ?>
				<?php if ($pageNumber === $page): ?>
					<strong><?php echo $pageNumber; ?></strong>
				<?php else: ?>
					<a href="user_ban.php?page=<?php echo $pageNumber; ?>"><?php echo $pageNumber; ?></a>
				<?php endif; ?>
			<?php endfor; ?>
		</div>
	<?php endif; ?>

	<br>
	<?php $footerPrefix = '../'; include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
