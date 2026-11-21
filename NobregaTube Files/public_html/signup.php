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
if (empty($_SESSION['captcha_token'])) {
	$_SESSION['captcha_token'] = bin2hex(random_bytes(16));
}

$errors = [];
$email = '';
$username = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
	$email = trim((string)($_POST['field_signup_email'] ?? ''));
	$username = trim((string)($_POST['field_signup_username'] ?? ''));
	$clientIp = normalize_ip_address((string)($_SERVER['REMOTE_ADDR'] ?? ''));
	$password = (string)($_POST['field_signup_password_1'] ?? '');
	$passwordAgain = (string)($_POST['field_signup_password_2'] ?? '');
	$submittedToken = (string)($_POST['csrf_token'] ?? '');
	$captchaToken = (string)($_POST['captcha_token'] ?? '');
	$captchaAnswer = strtoupper(trim((string)($_POST['field_signup_captcha'] ?? '')));

	if (!hash_equals($_SESSION['csrf_token'], $submittedToken)) {
		$errors[] = 'Your form session expired. Please refresh the page and try again.';
	} else {
		if (!hash_equals((string)$_SESSION['captcha_token'], $captchaToken)
			|| empty($_SESSION['signup_captcha'])
			|| !hash_equals((string)$_SESSION['signup_captcha'], $captchaAnswer)) {
			$errors[] = 'Incorrect or expired CAPTCHA answer.';
		}
		if ($email === '' || $username === '' || $password === '' || $passwordAgain === '') {
			$errors[] = 'All fields are required.';
		}
		if (strlen($email) > 100) {
			$errors[] = 'Email address must be no more than 100 characters.';
		}
		if (!preg_match('/^[A-Za-z0-9_]{3,20}$/', $username)) {
			$errors[] = 'Screen name must be 3-20 characters and contain only letters, numbers, or underscore.';
		}
		if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
			$errors[] = 'Invalid email address.';
		}
		if (strlen($password) < 6) {
			$errors[] = 'Password must be at least 6 characters.';
		}
		if ($password !== '' && $passwordAgain !== '' && $password !== $passwordAgain) {
			$errors[] = 'The passwords you entered do not match.';
		}

		if (!$errors) {
			$globalLimit = $pdo->query('SELECT blocked_until > UTC_TIMESTAMP() AS is_blocked
				FROM signup_global_limit WHERE id = 1')->fetchColumn();
			if ((int)$globalLimit === 1) {
				$errors[] = 'Sign up is temporarily paused. Please try again in 2 minutes.';
			}
		}

		if (!$errors && $clientIp !== null && !is_loopback_ip($clientIp)) {
			if (!function_exists('curl_init')) {
				error_log('Signup proxy check unavailable: cURL is not enabled.');
			} else {
				$proxyCheck = curl_init('https://proxycheck.io/v3/' . rawurlencode($clientIp) . '?tag=0');
				if ($proxyCheck === false) {
					error_log('Signup proxy check unavailable: could not initialize cURL.');
				} else {
					curl_setopt_array($proxyCheck, [
						CURLOPT_RETURNTRANSFER => true,
						CURLOPT_CONNECTTIMEOUT => 2,
						CURLOPT_TIMEOUT => 4,
						CURLOPT_SSL_VERIFYPEER => true,
						CURLOPT_SSL_VERIFYHOST => 2,
					]);
					$proxyResponse = curl_exec($proxyCheck);
					$proxyStatus = curl_getinfo($proxyCheck, CURLINFO_RESPONSE_CODE);
					$proxyError = curl_error($proxyCheck);
					curl_close($proxyCheck);
					$proxyData = $proxyResponse !== false ? json_decode($proxyResponse, true) : null;
					$detections = is_array($proxyData) ? ($proxyData[$clientIp]['detections'] ?? null) : null;

					if ($proxyStatus !== 200 || !is_array($detections)
						|| !isset($detections['proxy'], $detections['vpn'])) {
						error_log('Signup proxy check unavailable: ' . ($proxyError !== '' ? $proxyError : 'invalid API response.'));
					} elseif ($detections['proxy'] === true || $detections['vpn'] === true) {
						http_response_code(403);
						die('Sign up is not available while using a VPN or proxy.');
					}
				}
			}
		}

		if (!$errors) {
			$check = $pdo->prepare('SELECT username, email FROM users WHERE username = ? OR email = ?');
			$check->execute([$username, $email]);
			foreach ($check->fetchAll() as $existingUser) {
				if (strcasecmp($existingUser['username'], $username) === 0) {
					$errors[] = 'Sorry, that user name has already been taken.';
				}
				if (strcasecmp($existingUser['email'], $email) === 0) {
					$errors[] = 'Sorry, somebody is already using this e-mail.';
				}
			}
		}

		if (!$errors) {
			$uid = bin2hex(random_bytes(10));
			$lastIp = $clientIp !== null && !is_loopback_ip($clientIp) ? $clientIp : null;
			$verificationEnabled = email_verification_enabled();
			try {
				$pdo->exec('INSERT IGNORE INTO signup_global_limit (id) VALUES (1)');
				$pdo->beginTransaction();
				$globalLimit = $pdo->query('SELECT blocked_until > UTC_TIMESTAMP() AS is_blocked
					FROM signup_global_limit WHERE id = 1 FOR UPDATE')->fetchColumn();
				if ((int)$globalLimit === 1) {
					$pdo->commit();
					$errors[] = 'Sign up is temporarily paused. Please try again in 2 minutes.';
				} elseif ($lastIp !== null) {
					$limit = $pdo->prepare('INSERT IGNORE INTO signup_limits (ip_address) VALUES (?)');
					$limit->execute([$lastIp]);
					$limit = $pdo->prepare('SELECT blocked_until,
						blocked_until > UTC_TIMESTAMP() AS is_blocked
						FROM signup_limits WHERE ip_address = ? FOR UPDATE');
					$limit->execute([$lastIp]);
					$ipLimit = $limit->fetch();
					if ((int)$ipLimit['is_blocked'] === 1) {
						$pdo->commit();
						$errors[] = 'Too many accounts were created from this IP. Please wait 1 hour before signing up again.';
					} else {
						$recentSignups = $pdo->prepare('SELECT COUNT(*) FROM users
							WHERE last_ip = ? AND joined >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)');
						$recentSignups->execute([$lastIp]);
						if ((int)$recentSignups->fetchColumn() >= 2) {
							$pdo->prepare('UPDATE signup_limits SET blocked_until = DATE_ADD(UTC_TIMESTAMP(), INTERVAL 1 HOUR)
								WHERE ip_address = ?')->execute([$lastIp]);
							$pdo->commit();
							$errors[] = 'Too many accounts were created from this IP. Please wait 1 hour before signing up again.';
						}
					}
				}

				if (!$errors) {
					$insert = $pdo->prepare('INSERT INTO users
						(uid, username, password, email, email_verified, last_ip) VALUES (?, ?, ?, ?, ?, ?)');
					$insert->execute([
						$uid,
						$username,
						password_hash($password, PASSWORD_DEFAULT),
						$email,
						$verificationEnabled ? 0 : 1,
						$lastIp,
					]);
					$recentSignups = (int)$pdo->query('SELECT COUNT(*) FROM users
						WHERE joined >= DATE_SUB(NOW(), INTERVAL 30 SECOND)')->fetchColumn();
					if ($recentSignups >= 15) {
						$pdo->exec('UPDATE signup_global_limit
							SET blocked_until = DATE_ADD(UTC_TIMESTAMP(), INTERVAL 2 MINUTE)
							WHERE id = 1');
					}
					if ($lastIp !== null) {
						$pdo->prepare('DELETE FROM signup_limits WHERE ip_address = ?')->execute([$lastIp]);
					}
					$pdo->commit();
				}
			} catch (PDOException $exception) {
				if ($pdo->inTransaction()) {
					$pdo->rollBack();
				}
				if ($exception->getCode() === '23000') {
					$errors[] = 'That user name or e-mail address is already in use.';
				} else {
					throw $exception;
				}
			} catch (Throwable $exception) {
				if ($pdo->inTransaction()) {
					$pdo->rollBack();
				}
				throw $exception;
			}

			if (!$errors) {
				$verificationSent = $verificationEnabled
					? send_verification_email($pdo, $uid, $username, $email)
					: false;
				if ($verificationEnabled && !$verificationSent) {
					$_SESSION['email_verification_send_failed'] = true;
				}
				session_regenerate_id(true);
				$_SESSION['uid'] = $uid;
				$_SESSION['username'] = $username;
				$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
				unset($_SESSION['signup_captcha'], $_SESSION['captcha_token']);
				header('Location: ' . ($verificationSent || !empty($_SESSION['email_verification_send_failed'])
					? 'email_confirm.php' . ($verificationSent ? '?sent=1' : '')
					: 'index.php'));
				exit;
			}
		}
	}
	unset($_SESSION['signup_captcha']);
	$_SESSION['captcha_token'] = bin2hex(random_bytes(16));
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
	$activeHeaderPage = 'signup';
	$showSubNav = true;
	include __DIR__ . '/includes/header.php';
	?>

	<?php if ($errors): ?>
		<?php foreach ($errors as $error): ?>
			<table width="100%" align="center" bgcolor="#FF0000" cellpadding="6" cellspacing="3" border="0">
				<tbody><tr><td align="center" bgcolor="#FFFFFF"><span class="error"><?php echo h($error); ?></span></td></tr></tbody>
			</table><br>
		<?php endforeach; ?>
	<?php endif; ?>

	<div class="formTitle">Sign Up</div>
	<div class="formTable">
		<div class="formIntro">Please enter your account information below. All fields are required.</div>
		<form method="post" action="signup.php">
			<input type="hidden" name="field_command" value="signup_submit">
			<input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
			<input type="hidden" name="captcha_token" value="<?php echo h($_SESSION['captcha_token']); ?>">
			<table width="720" cellpadding="5" cellspacing="0" border="0">
				<tbody>
					<tr>
						<td width="200" align="right"><span class="label">Email Address:</span></td>
						<td><input type="text" size="30" maxlength="100" name="field_signup_email" value="<?php echo h($email); ?>"></td>
					</tr>
					<tr>
						<td align="right"><span class="label">User Name:</span></td>
						<td><input type="text" size="20" maxlength="20" name="field_signup_username" value="<?php echo h($username); ?>"></td>
					</tr>
					<tr>
						<td align="right"><span class="label">Password:</span></td>
						<td><input type="password" size="20" name="field_signup_password_1" value=""></td>
					</tr>
					<tr>
						<td align="right"><span class="label">Retype Password:</span></td>
						<td><input type="password" size="20" name="field_signup_password_2" value=""></td>
					</tr>
					<tr>
						<td align="right"><span class="label">CAPTCHA:</span></td>
						<td>
							<a href="#" onclick="document.verificationImg.src='cimg.php?c=<?php echo h($_SESSION['captcha_token']); ?>&amp;r='+Math.random();return false"><img name="verificationImg" src="cimg.php?c=<?php echo h($_SESSION['captcha_token']); ?>" border="0" align="texttop" alt="CAPTCHA image"></a>
							<br><br><input type="text" size="20" maxlength="5" name="field_signup_captcha" value="" autocomplete="off" aria-label="CAPTCHA answer">
						</td>
					</tr>
					<tr>
						<td>&nbsp;</td>
						<td><br>- I certify I am over 13 years old.<br>- I agree to the <a href="terms.php" target="_blank">terms of use</a> and <a href="privacy.php" target="_blank">privacy policy</a>.</td>
					</tr>
					<tr>
						<td>&nbsp;</td>
						<td><input type="submit" value="Sign Up"></td>
					</tr>
					<tr>
						<td>&nbsp;</td>
						<td><br>Or, <a href="index.php">return to the homepage</a>.</td>
					</tr>
				</tbody>
			</table>
		</form>
	</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
