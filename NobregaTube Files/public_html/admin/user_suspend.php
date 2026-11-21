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

	$targetUid = trim((string)($_POST['user_id'] ?? ''));
	if (!preg_match('/^[A-Za-z0-9_-]{1,20}$/', $targetUid) || $targetUid === (string)$currentUser['uid']) {
		header('Location: user_suspend.php?result=protected');
		exit;
	}

	$action = (string)($_POST['action'] ?? '');
	if ($action === 'unsuspend') {
		$unsuspendQuery = $pdo->prepare('UPDATE users SET suspended_until = NULL
			WHERE uid = ? AND staff = 0 AND termination = 0
			AND suspended_until IS NOT NULL AND suspended_until > UTC_TIMESTAMP()');
		$unsuspendQuery->execute([$targetUid]);
		header('Location: user_suspend.php?result=' . ($unsuspendQuery->rowCount() === 1 ? 'unsuspended' : 'protected'));
		exit;
	}

	if ($action !== 'suspend') {
		header('Location: user_suspend.php?result=error');
		exit;
	}

	$duration = filter_var($_POST['duration'] ?? null, FILTER_VALIDATE_INT);
	$allowedDurations = [1, 2, 4, 6, 7];
	if (!in_array($duration, $allowedDurations, true)) {
		header('Location: user_suspend.php?result=invalid');
		exit;
	}

	$suspendQuery = $pdo->prepare('UPDATE users
		SET suspended_until = DATE_ADD(UTC_TIMESTAMP(), INTERVAL ' . $duration . ' DAY)
		WHERE uid = ? AND staff = 0 AND termination = 0
		AND (suspended_until IS NULL OR suspended_until <= UTC_TIMESTAMP())');
	$suspendQuery->execute([$targetUid]);
	header('Location: user_suspend.php?result=' . ($suspendQuery->rowCount() === 1 ? 'suspended' : 'protected'));
	exit;
}

$activeUsersQuery = $pdo->query('SELECT uid, username, joined
	FROM users
	WHERE termination = 0 AND staff = 0
	AND (suspended_until IS NULL OR suspended_until <= UTC_TIMESTAMP())
	ORDER BY joined DESC
	LIMIT 100');
$activeUsers = $activeUsersQuery->fetchAll();

$suspendedUsersQuery = $pdo->query('SELECT uid, username, suspended_until
	FROM users
	WHERE termination = 0 AND staff = 0 AND suspended_until > UTC_TIMESTAMP()
	ORDER BY suspended_until ASC
	LIMIT 100');
$suspendedUsers = $suspendedUsersQuery->fetchAll();
$result = (string)($_GET['result'] ?? '');
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html lang="en">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
	<title>ManagerFilm - Suspend User</title>
	<link rel="stylesheet" href="../styles2005july.css">
	<style>
		.managercast-bar { width: 80%; margin: 20px auto; border: 1px solid #999; background: #eeeedd; }
		.managercast-bar td { padding: 9px; text-align: center; font-weight: bold; }
		.managercast-bar .managercast-brand { background: #D5E5F5; font-size: 20px; }
		.managercast-tabs { width: 80%; margin: 0px auto 20px auto; border-bottom: 1px solid #999; }
		.managercast-tabs td { padding: 8px 12px; background: #EEEEEE; font-weight: bold; }
		.managercast-tabs .managercast-tab-active { background: #D5E5F5; }
		.managercast-section { width: 80%; margin: 20px auto; }
		.managercast-actions { white-space: nowrap; text-align: right; }
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
			<td><a href="user_ban.php">User Ban</a></td>
			<td class="managercast-tab-active">Suspend User</td>
			<td><a href="contact_vault.php">Contact Vault</a></td>
		</tr></tbody>
	</table>

	<?php if ($result === 'suspended'): ?>
		<p class="success">User suspended.</p>
	<?php elseif ($result === 'unsuspended'): ?>
		<p class="success">User suspension removed.</p>
	<?php elseif ($result === 'protected'): ?>
		<p class="error">This account is already suspended or cannot be changed.</p>
	<?php elseif ($result === 'invalid'): ?>
		<p class="error">Choose a valid suspension duration.</p>
	<?php elseif ($result === 'error'): ?>
		<p class="error">The suspension action could not be completed.</p>
	<?php endif; ?>

	<div class="managercast-section">
		<div class="tableSubTitle">Suspend a User (up to 100 shown)</div>
		<?php if (!$activeUsers): ?>
			<div class="moduleEntry">There are no active non-staff accounts to suspend.</div>
		<?php endif; ?>
		<?php foreach ($activeUsers as $user): ?>
			<div class="moduleEntry">
				<table width="100%" cellpadding="0" cellspacing="0" border="0">
					<tbody><tr valign="top">
						<td width="100%">
							<div class="moduleEntryTitle"><a href="../profile.php?user=<?php echo rawurlencode($user['username']); ?>"><?php echo h($user['username']); ?></a></div>
							<div class="moduleEntryDetails">Joined: <?php echo h(date('F j, Y', strtotime($user['joined']))); ?></div>
						</td>
						<td class="managercast-actions">
							<form method="post" action="user_suspend.php" onsubmit="return confirm('Suspend this account for the selected duration?');">
								<input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
								<input type="hidden" name="action" value="suspend">
								<input type="hidden" name="user_id" value="<?php echo h($user['uid']); ?>">
								<select name="duration" aria-label="Suspension duration">
									<option value="1">1 day</option>
									<option value="2">2 days</option>
									<option value="4">4 days</option>
									<option value="6">6 days</option>
									<option value="7">1 week</option>
								</select>
								<input type="submit" value="Suspend User">
							</form>
						</td>
					</tr></tbody>
				</table>
			</div>
		<?php endforeach; ?>
	</div>

	<div class="managercast-section">
		<div class="tableSubTitle">Suspended Users (up to 100 shown)</div>
		<?php if (!$suspendedUsers): ?>
			<div class="moduleEntry">There are no currently suspended non-staff accounts.</div>
		<?php endif; ?>
		<?php foreach ($suspendedUsers as $user): ?>
			<div class="moduleEntry">
				<table width="100%" cellpadding="0" cellspacing="0" border="0">
					<tbody><tr valign="top">
						<td width="100%">
							<div class="moduleEntryTitle"><a href="../profile.php?user=<?php echo rawurlencode($user['username']); ?>"><?php echo h($user['username']); ?></a></div>
							<div class="moduleEntryDetails">Suspended until: <?php echo h(gmdate('F j, Y H:i', strtotime($user['suspended_until'] . ' UTC'))); ?> UTC</div>
						</td>
						<td class="managercast-actions">
							<form method="post" action="user_suspend.php" onsubmit="return confirm('Remove this account suspension now?');">
								<input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
								<input type="hidden" name="action" value="unsuspend">
								<input type="hidden" name="user_id" value="<?php echo h($user['uid']); ?>">
								<input type="submit" value="Unsuspend User">
							</form>
						</td>
					</tr></tbody>
				</table>
			</div>
		<?php endforeach; ?>
	</div>

	<br>
	<?php $footerPrefix = '../'; include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
