<?php
require_once __DIR__ . '/../includes/sess.php';

if (empty($_SESSION['uid'])) {
	header('Location: ../login.php');
	exit;
}

require __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/ip_ranges.php';
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

	$action = (string)($_POST['action'] ?? '');
	if ($action === 'remove') {
		$banId = filter_var($_POST['ban_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
		if ($banId === false) {
			header('Location: ip_ban.php?result=error');
			exit;
		}
		$deleteQuery = $pdo->prepare('DELETE FROM ip_bans WHERE ban_id = ?');
		$deleteQuery->execute([$banId]);
		header('Location: ip_ban.php?result=' . ($deleteQuery->rowCount() === 1 ? 'removed' : 'missing'));
		exit;
	}

	if ($action !== 'add') {
		header('Location: ip_ban.php?result=error');
		exit;
	}

	$normalizedRange = normalize_ip_range((string)($_POST['ip_range'] ?? ''));
	$duration = filter_var($_POST['duration'] ?? null, FILTER_VALIDATE_INT);
	$allowedDurations = [1, 2, 4, 6, 7];
	$requestIp = (string)($_SERVER['REMOTE_ADDR'] ?? '');
	if ($normalizedRange === null || !in_array($duration, $allowedDurations, true)) {
		header('Location: ip_ban.php?result=invalid');
		exit;
	}
	if ($requestIp === '' || inet_pton($requestIp) === false) {
		header('Location: ip_ban.php?result=error');
		exit;
	}
	if (ip_matches_range($requestIp, $normalizedRange)) {
		header('Location: ip_ban.php?result=self');
		exit;
	}

	$expiresAt = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
		->modify('+' . $duration . ' days')
		->format('Y-m-d H:i:s');
	$insertQuery = $pdo->prepare('INSERT INTO ip_bans (ip_range, expires_at, created_at, created_by)
		VALUES (?, ?, UTC_TIMESTAMP(), ?)
		ON DUPLICATE KEY UPDATE expires_at = VALUES(expires_at),
			created_at = UTC_TIMESTAMP(), created_by = VALUES(created_by)');
	$insertQuery->execute([$normalizedRange, $expiresAt, $currentUser['uid']]);
	header('Location: ip_ban.php?result=added');
	exit;
}

$bansQuery = $pdo->query('SELECT b.ban_id, b.ip_range, b.expires_at, b.created_at, u.username
	FROM ip_bans b
	LEFT JOIN users u ON u.uid = b.created_by
	WHERE b.expires_at > UTC_TIMESTAMP()
	ORDER BY b.expires_at ASC');
$activeBans = $bansQuery->fetchAll();
$selectedUser = null;
$selectedUserId = trim((string)($_GET['user'] ?? ''));
if ($selectedUserId !== '' && preg_match('/^[A-Za-z0-9_-]{1,20}$/', $selectedUserId)) {
	$selectedUserQuery = $pdo->prepare('SELECT uid, username, last_ip
		FROM users
		WHERE uid = ? AND staff = 0
		LIMIT 1');
	$selectedUserQuery->execute([$selectedUserId]);
	$selectedUser = $selectedUserQuery->fetch() ?: null;
}
$selectedAccountIp = $selectedUser ? normalize_ip_address((string)($selectedUser['last_ip'] ?? '')) : null;
$selectedIpIsLoopback = $selectedAccountIp !== null && is_loopback_ip($selectedAccountIp);
$prefilledIp = $selectedIpIsLoopback ? null : $selectedAccountIp;
$result = (string)($_GET['result'] ?? '');
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html lang="en">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
	<title>ManagerFilm - IP Ban</title>
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
			<td class="managercast-tab-active"><a href="user_ban.php">User Ban</a></td>
			<td><a href="user_suspend.php">Suspend User</a></td>
			<td><a href="contact_vault.php">Contact Vault</a></td>
		</tr></tbody>
	</table>

	<?php if ($result === 'added'): ?>
		<p class="success">IP ban saved.</p>
	<?php elseif ($result === 'removed'): ?>
		<p class="success">IP ban removed.</p>
	<?php elseif ($result === 'self'): ?>
		<p class="error">You cannot ban an IP range that includes your current address.</p>
	<?php elseif ($result === 'invalid'): ?>
		<p class="error">Enter a valid IP address or CIDR range and choose an allowed duration.</p>
	<?php elseif ($result === 'missing'): ?>
		<p class="error">That IP ban is no longer active.</p>
	<?php elseif ($result === 'error'): ?>
		<p class="error">The IP ban action could not be completed.</p>
	<?php endif; ?>

	<div class="managercast-section">
		<div class="tableSubTitle">Ban an IP address or network</div>
		<div class="moduleEntry">
			<?php if ($selectedUser): ?>
				<div class="moduleEntryDetails">
					IP address for <?php echo h($selectedUser['username']); ?>:
					<?php if ($selectedIpIsLoopback): ?>
						<?php echo h($selectedAccountIp); ?> is a localhost address. It does not identify the user's public IP.
					<?php elseif ($prefilledIp === null): ?>
						not available yet; the user must log in from a non-local address first.
					<?php else: ?>
						<?php echo h($prefilledIp); ?>
					<?php endif; ?>
				</div>
				<?php if ($selectedIpIsLoopback): ?>
					<p class="error">The site is being accessed through localhost. Open NobregaTube through its network address or domain to record the user's address. Do not use an untrusted forwarded-IP header.</p>
				<?php endif; ?>
			<?php elseif ($selectedUserId !== ''): ?>
				<div class="error">The selected account was not found or cannot be IP banned.</div>
			<?php endif; ?>
			<form method="post" action="ip_ban.php">
				<input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
				<input type="hidden" name="action" value="add">
				<label for="ip_range">IP address or CIDR range:</label>
				<input type="text" id="ip_range" name="ip_range" maxlength="43" required placeholder="192.0.2.10 or 192.0.2.0/24" value="<?php echo h($prefilledIp ?? ''); ?>">
				<label for="duration">Duration:</label>
				<select id="duration" name="duration" required>
					<option value="1">1 day</option>
					<option value="2">2 days</option>
					<option value="4">4 days</option>
					<option value="6">6 days</option>
					<option value="7">1 week</option>
				</select>
				<input type="submit" value="Ban IP">
			</form>
			<div class="moduleEntryDetails">IPv4 and IPv6 are supported. Networks must be at least /16 for IPv4 or /32 for IPv6. Your current IP address cannot be included.</div>
		</div>
	</div>

	<div class="managercast-section">
		<div class="tableSubTitle">Active IP Bans (<?php echo count($activeBans); ?>)</div>
		<?php if (!$activeBans): ?>
			<div class="moduleEntry">There are no active IP bans.</div>
		<?php endif; ?>
		<?php foreach ($activeBans as $ban): ?>
			<div class="moduleEntry">
				<table width="100%" cellpadding="0" cellspacing="0" border="0">
					<tbody><tr valign="top">
						<td width="100%">
							<div class="moduleEntryTitle"><?php echo h($ban['ip_range']); ?></div>
							<div class="moduleEntryDetails">
								Expires: <?php echo h(gmdate('F j, Y H:i', strtotime($ban['expires_at'] . ' UTC'))); ?> UTC
								<?php if ($ban['username'] !== null): ?> | Banned by: <?php echo h($ban['username']); ?><?php endif; ?>
							</div>
						</td>
						<td align="right">
							<form method="post" action="ip_ban.php" onsubmit="return confirm('Remove this IP ban?');">
								<input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
								<input type="hidden" name="action" value="remove">
								<input type="hidden" name="ban_id" value="<?php echo (int)$ban['ban_id']; ?>">
								<input type="submit" value="Remove Ban">
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
