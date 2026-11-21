<?php
require_once __DIR__ . '/includes/sess.php';
require __DIR__ . '/includes/database.php';

$sessionToken = isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : '';
$submittedToken = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$submittedToken = isset($_POST['csrf_token']) ? (string)$_POST['csrf_token'] : '';
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
	$submittedToken = isset($_GET['csrf_token']) ? (string)$_GET['csrf_token'] : '';
}

if ($sessionToken !== '' && $submittedToken !== '' && hash_equals($sessionToken, $submittedToken)) {
	$_SESSION = [];
	if (ini_get('session.use_cookies')) {
		$cookie = session_get_cookie_params();
		setcookie(session_name(), '', time() - 42000, $cookie['path'], $cookie['domain'], $cookie['secure'], $cookie['httponly']);
	}
	session_destroy();
}

header('Location: index.php');
exit;
