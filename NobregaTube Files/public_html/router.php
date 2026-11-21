<?php
$feedRoute = 'rss/global/recently_added.rss';
$route = (string)($_GET['route'] ?? '');
$requestPath = parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);

if ($route === $feedRoute || (is_string($requestPath) && ltrim($requestPath, '/') === $feedRoute)) {
	require __DIR__ . '/rss/global/recently_added.php';
	return;
}

if (!is_string($requestPath)) {
	http_response_code(404);
	exit('Not Found');
}

$decodedPath = rawurldecode($requestPath);
$relativePath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($decodedPath, '/\\'));
$documentRoot = realpath(__DIR__);
$requestedFile = realpath($documentRoot . DIRECTORY_SEPARATOR . $relativePath);
$rootPrefix = rtrim($documentRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

if ($requestedFile === false
	|| ($requestedFile !== $documentRoot
		&& strncasecmp($requestedFile, $rootPrefix, strlen($rootPrefix)) !== 0)) {
	http_response_code(404);
	exit('Not Found');
}

if (is_dir($requestedFile)) {
	$requestedFile = realpath($requestedFile . DIRECTORY_SEPARATOR . 'index.php');
	if ($requestedFile === false || strncasecmp($requestedFile, $rootPrefix, strlen($rootPrefix)) !== 0) {
		http_response_code(404);
		exit('Not Found');
	}
}

if (strtolower(pathinfo($requestedFile, PATHINFO_EXTENSION)) === 'php') {
	require $requestedFile;
	return;
}

return false;
