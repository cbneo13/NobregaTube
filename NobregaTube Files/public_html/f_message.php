<?php
require_once __DIR__ . '/includes/sess.php';
require __DIR__ . '/includes/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_SESSION['uid'])) {
	header('Location: login.php');
	exit;
}
$userQuery = $pdo->prepare('SELECT uid FROM users WHERE uid = ? LIMIT 1');
$userQuery->execute([$_SESSION['uid']]);
if (!$userQuery->fetchColumn()) {
	unset($_SESSION['uid'], $_SESSION['username']);
	header('Location: login.php');
	exit;
}
if (empty($_SESSION['csrf_token'])
	|| !hash_equals((string)$_SESSION['csrf_token'], (string)($_POST['csrf_token'] ?? ''))) {
	http_response_code(403);
	exit('Your form session expired. Refresh the page and try again.');
}

$messageId = trim((string)($_POST['message_id'] ?? ''));
$returnTo = (string)($_POST['return_to'] ?? '');
if (!preg_match('/^[A-Za-z0-9_-]{1,20}$/', $messageId) || !in_array($returnTo, ['inbox', 'outbox'], true)) {
	header('Location: my_messages.php');
	exit;
}

$column = $returnTo === 'inbox' ? 'receiver' : 'sender';
$deleteQuery = $pdo->prepare('DELETE FROM messages WHERE pmid = ? AND ' . $column . ' = ?');
$deleteQuery->execute([$messageId, $_SESSION['uid']]);
header('Location: ' . ($returnTo === 'inbox' ? 'my_messages.php' : 'outbox.php'));
exit;
