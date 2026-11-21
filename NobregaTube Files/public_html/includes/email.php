<?php
require_once __DIR__ . '/../vendor/autoload.php';

function email_settings(): array
{
	static $settings;
	if (isset($settings)) {
		return $settings;
	}

	$config = @parse_ini_file(__DIR__ . '/../email.ini', true, INI_SCANNER_TYPED);
	$settings = is_array($config) && isset($config['Email']) && is_array($config['Email'])
		? $config['Email']
		: [];
	return $settings;
}

function public_url(): string
{
	static $url;
	if (isset($url)) {
		return $url;
	}

	$config = @parse_ini_file(__DIR__ . '/../url.ini', true, INI_SCANNER_RAW);
	$url = is_array($config) && isset($config['URL']['url'])
		? rtrim(trim((string)$config['URL']['url']), '/')
		: '';
	$parts = $url !== '' ? parse_url($url) : false;
	if ($parts === false
		|| empty($parts['scheme'])
		|| !in_array(strtolower($parts['scheme']), ['http', 'https'], true)
		|| empty($parts['host'])
		|| isset($parts['user'])
		|| isset($parts['pass'])
		|| isset($parts['query'])
		|| isset($parts['fragment'])) {
		error_log('NobregaTube public URL is missing or invalid in url.ini.');
		$url = '';
	}

	return $url;
}

function smtp_is_configured(): bool
{
	$settings = email_settings();
	$host = trim((string)($settings['host'] ?? ''));
	$sender = trim((string)($settings['email'] ?? ''));
	$username = trim((string)($settings['username'] ?? ''));
	$password = (string)($settings['password'] ?? '');
	$port = filter_var($settings['port'] ?? null, FILTER_VALIDATE_INT);

	return $host !== ''
		&& !preg_match('/example\.com$/i', $host)
		&& filter_var($sender, FILTER_VALIDATE_EMAIL)
		&& !preg_match('/@example\.com$/i', $sender)
		&& $username !== ''
		&& !preg_match('/@example\.com$/i', $username)
		&& $password !== ''
		&& $password !== 'your-email-password'
		&& $port !== false
		&& $port >= 1
		&& $port <= 65535
		&& class_exists(\PHPMailer\PHPMailer\PHPMailer::class);
}

function email_verification_enabled(): bool
{
	return filter_var(email_settings()['verify'] ?? false, FILTER_VALIDATE_BOOLEAN)
		&& smtp_is_configured();
}

function mailer(): \PHPMailer\PHPMailer\PHPMailer
{
	if (!smtp_is_configured()) {
		throw new RuntimeException('SMTP is not configured.');
	}

	$settings = email_settings();
	$mail = new \PHPMailer\PHPMailer\PHPMailer(true);
	$mail->isSMTP();
	$mail->Host = (string)$settings['host'];
	$mail->SMTPAuth = true;
	$mail->Username = (string)$settings['username'];
	$mail->Password = (string)$settings['password'];
	$mail->Port = (int)$settings['port'];
	$mail->SMTPAutoTLS = false;
	$mail->SMTPSecure = filter_var($settings['ssl'] ?? false, FILTER_VALIDATE_BOOLEAN)
		? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
		: \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
	$mail->setFrom((string)$settings['email'], 'NobregaTube');
	return $mail;
}

function send_verification_email(PDO $pdo, string $uid, string $username, string $email): bool
{
	if (!email_verification_enabled()) {
		$pdo->prepare('UPDATE users SET email_verified = 1, email_verification_token = NULL,
			email_verification_expires = NULL WHERE uid = ?')->execute([$uid]);
		return false;
	}

	$publicUrl = public_url();
	if ($publicUrl === '') {
		error_log('NobregaTube verification email was not sent for account ' . $uid . ': public URL is invalid.');
		$pdo->prepare('UPDATE users SET email_verified = 0, email_verification_token = NULL,
			email_verification_expires = NULL WHERE uid = ?')->execute([$uid]);
		return false;
	}

	$token = bin2hex(random_bytes(32));
	$tokenHash = hash('sha256', $token);
	$expires = gmdate('Y-m-d H:i:s', time() + 86400);
	$pdo->prepare('UPDATE users SET email = ?, email_verified = 0, email_verification_token = ?,
		email_verification_expires = ? WHERE uid = ?')->execute([$email, $tokenHash, $expires, $uid]);

	$confirmationUrl = $publicUrl . '/confirm_email.php?token=' . rawurlencode($token);

	try {
		$mail = mailer();
		$mail->addAddress($email);
		$mail->isHTML(true);
		$mail->Subject = 'NobregaTube email confirmation';
		$mail->Body = 'Hi ' . h($username) . ',<p>Please click the following link to confirm your email address: '
			. '<a href="' . h($confirmationUrl) . '">' . h($confirmationUrl) . '</a></p><p>The NobregaTube Team</p>';
		$mail->AltBody = 'Hi ' . $username . ', confirm your email address: ' . $confirmationUrl;
		$mail->send();
		return true;
	} catch (\Throwable $exception) {
		error_log('NobregaTube verification email failed for account ' . $uid . ': ' . $exception->getMessage());
		$pdo->prepare('UPDATE users SET email_verified = 0, email_verification_token = NULL,
			email_verification_expires = NULL WHERE uid = ?')->execute([$uid]);
		return false;
	}
}
