<?php
require_once __DIR__ . '/ip_ranges.php';

$requestIp = (string)($_SERVER['REMOTE_ADDR'] ?? '');
if ($requestIp !== '' && inet_pton($requestIp) !== false) {
	$ipBanQuery = $pdo->query('SELECT ip_range FROM ip_bans WHERE expires_at > UTC_TIMESTAMP()');
	foreach ($ipBanQuery->fetchAll(PDO::FETCH_COLUMN) as $bannedRange) {
		if (ip_matches_range($requestIp, (string)$bannedRange)) {
			die('Your ip is in the blacklist');
		}
	}
}
