<?php
require_once __DIR__ . '/../sonioncastbest2005revival.php';
require_once __DIR__ . '/email.php';

$db = parse_ini_file(__DIR__ . '/../db.ini', true, INI_SCANNER_TYPED)['Database'];

$pdo = new PDO(
	'mysql:host=' . $db['host'] . ';dbname=' . $db['name'] . ';charset=utf8mb4',
	$db['username'],
	$db['password'],
	[
		PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
		PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
		PDO::ATTR_EMULATE_PREPARES => false,
	]
);

require __DIR__ . '/check_ban.php';

if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['uid'])) {
	$accountQuery = $pdo->prepare('SELECT termination, suspended_until, email_verified,
		(suspended_until IS NOT NULL AND suspended_until > UTC_TIMESTAMP()) AS is_suspended
		FROM users WHERE uid = ? LIMIT 1');
	$accountQuery->execute([$_SESSION['uid']]);
	$accountStatus = $accountQuery->fetch();
	if (!$accountStatus || (int)$accountStatus['termination'] === 1) {
		$_SESSION = [];
		$_SESSION['account_terminated'] = (bool)$accountStatus;
		session_regenerate_id(true);
	} elseif ((int)$accountStatus['is_suspended'] === 1) {
		$_SESSION = [];
		$_SESSION['account_suspended_until'] = $accountStatus['suspended_until'];
		session_regenerate_id(true);
	} else {
		$currentScript = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
		$uploadPages = ['my_videos_upload.php', 'my_videos_upload_2.php', 'my_videos_post.php'];
		if (email_verification_enabled()
			&& (int)$accountStatus['email_verified'] !== 1
			&& in_array($currentScript, $uploadPages, true)) {
			$scriptDirectory = str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/')));
			$siteRoot = rtrim($scriptDirectory, '/');
			header('Location: ' . $siteRoot . '/email_confirm.php');
			exit;
		}

		$sessionIp = normalize_ip_address((string)($_SERVER['REMOTE_ADDR'] ?? ''));
		if ($sessionIp !== null && !is_loopback_ip($sessionIp)) {
			$ipUpdate = $pdo->prepare('UPDATE users SET last_ip = ?
				WHERE uid = ? AND (last_ip IS NULL OR last_ip <> ?)');
			$ipUpdate->execute([$sessionIp, $_SESSION['uid'], $sessionIp]);
		}
	}
}
