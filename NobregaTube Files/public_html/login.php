<?php
require_once __DIR__ . '/includes/sess.php';
require __DIR__ . '/includes/database.php';

if (!empty($_SESSION['uid'])) {
	header('Location: index.php');
	exit;
}

if (empty($_SESSION['csrf_token'])) {
	$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errors = [];
$username = '';
if (!empty($_SESSION['account_terminated'])) {
	$errors[] = 'This account has been terminated.';
	unset($_SESSION['account_terminated']);
}
if (!empty($_SESSION['account_suspended_until'])) {
	$suspendedUntil = (string)$_SESSION['account_suspended_until'];
	$errors[] = 'This account is suspended until ' . gmdate('F j, Y H:i', strtotime($suspendedUntil . ' UTC')) . ' UTC.';
	unset($_SESSION['account_suspended_until']);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
	$username = trim((string)($_POST['field_login_username'] ?? ''));
	$password = (string)($_POST['field_login_password'] ?? '');
	$submittedToken = (string)($_POST['csrf_token'] ?? '');

	if (!hash_equals($_SESSION['csrf_token'], $submittedToken)) {
		$errors[] = 'Your form session expired. Please refresh the page and try again.';
	} elseif ($username === '' || $password === '') {
		$errors[] = 'All fields are required.';
	} else {
		$userQuery = $pdo->prepare('SELECT uid, username, password, termination, suspended_until,
			(suspended_until IS NOT NULL AND suspended_until > UTC_TIMESTAMP()) AS is_suspended
			FROM users WHERE username = ?');
		$userQuery->execute([$username]);
		$user = $userQuery->fetch();

		if (!$user || !password_verify($password, $user['password'])) {
			$errors[] = 'The user name or password is incorrect.';
		} elseif ((int)$user['termination'] === 1) {
			$errors[] = 'This account has been terminated.';
		} elseif ((int)$user['is_suspended'] === 1) {
			$errors[] = 'This account is suspended until ' . gmdate('F j, Y H:i', strtotime($user['suspended_until'] . ' UTC')) . ' UTC.';
		} else {
			$lastIp = normalize_ip_address((string)($_SERVER['REMOTE_ADDR'] ?? ''));
			if ($lastIp !== null && !is_loopback_ip($lastIp)) {
				$ipUpdate = $pdo->prepare('UPDATE users SET last_ip = ? WHERE uid = ?');
				$ipUpdate->execute([$lastIp, $user['uid']]);
			}
			session_regenerate_id(true);
			$_SESSION['uid'] = $user['uid'];
			$_SESSION['username'] = $user['username'];
			$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
			header('Location: index.php');
			exit;
		}
	}
}

?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html lang="en">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
	<title>NobregaTube - Your Digital Video Repository</title>
	<link rel="stylesheet" href="styles2005july.css">
</head>
<body>
	<?php
	$activeHeaderPage = 'login';
	include __DIR__ . '/includes/header.php';
	?>

	<?php foreach ($errors as $error): ?>
		<table width="100%" align="center" bgcolor="#FF0000" cellpadding="6" cellspacing="3" border="0">
			<tbody><tr><td align="center" bgcolor="#FFFFFF"><span class="error"><?php echo h($error); ?></span></td></tr></tbody>
		</table><br>
	<?php endforeach; ?>

	<div class="pageTitle">Log In</div>
	<table width="80%" align="center" cellpadding="5" cellspacing="0" border="0">
		<tbody><tr valign="top">
			<td>
				<span class="highlight">What is NobregaTube?</span>
				<br><br>
				NobregaTube is a way to get your videos to the people who matter to you. With NobregaTube you can:
				<ul>
					<li>Show off your favorite videos to the world</li>
					<li>Blog the videos you take with your digital camera or cell phone</li>
					<li>Securely and privately show videos to your friends and family around the world</li>
					<li>... and much, much more!</li>
				</ul>
				<br><span class="highlight"><a href="signup.php">Sign up now</a> and open a free account.</span>
				<br><br><br>
				To learn more about our service, please see our <a href="help.php">Help</a> section.<br><br><br>
			</td>
			<td width="20"><img src="img/pixel.gif" width="20" height="1" alt=""></td>
			<td width="300">
				<div class="login-panel">
					<form method="post" action="login.php">
						<input type="hidden" name="field_command" value="login_submit">
						<input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
					<table width="100%" bgcolor="#D5E5F5" cellpadding="5" cellspacing="0" border="0">
						<tbody>
							<tr><td align="center" colspan="2"><div class="login-panel-title">NobregaTube Log In</div></td></tr>
							<tr>
								<td align="right"><span class="label">User Name:</span></td>
								<td><input type="text" size="20" name="field_login_username" value="<?php echo h($username); ?>"></td>
							</tr>
							<tr>
								<td align="right"><span class="label">Password:</span></td>
								<td><input type="password" size="20" name="field_login_password" value=""></td>
							</tr>
							<tr>
								<td align="right">&nbsp;</td>
								<td><input type="submit" value="Log In"></td>
							</tr>
							<tr><td align="center" colspan="2"><a href="contact.php">Need help accessing your account?</a></td></tr>
						</tbody>
					</table>
					</form>
				</div>
			</td>
		</tr></tbody>
	</table>

	<?php include __DIR__ . '/includes/footer.php'; ?>
