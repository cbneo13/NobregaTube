<?php
require_once __DIR__ . '/includes/sess.php';
require __DIR__ . '/includes/database.php';

$currentUser = null;
$unreadMessages = 0;
if (!empty($_SESSION['uid'])) {
	$userQuery = $pdo->prepare('SELECT uid, username, staff FROM users WHERE uid = ? LIMIT 1');
	$userQuery->execute([$_SESSION['uid']]);
	$currentUser = $userQuery->fetch();
	if ($currentUser) {
		$unreadQuery = $pdo->prepare('SELECT COUNT(*) FROM messages WHERE receiver = ? AND isRead = 0');
		$unreadQuery->execute([$currentUser['uid']]);
		$unreadMessages = (int)$unreadQuery->fetchColumn();
	} else {
		unset($_SESSION['uid'], $_SESSION['username']);
	}
}
$embedUser = $currentUser ? (string)$currentUser['username'] : 'YOUR_USERNAME';
$embedHost = (string)($_SERVER['HTTP_HOST'] ?? '');
$embedHostIsValid = preg_match('/\A(?:[a-z0-9.-]+|\[[0-9a-f:]+\])(?::[0-9]{1,5})?\z/i', $embedHost) === 1;
$embedUrl = $embedHostIsValid
	? ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://')
		. $embedHost . '/videos_list.php?user=' . rawurlencode($embedUser)
	: '';
$embedBaseUrl = $embedHostIsValid
	? ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://') . $embedHost . '/'
	: '';
$fallbackVideos = [];
if ($currentUser) {
	$fallbackQuery = $pdo->prepare('SELECT v.vid, v.title, u.username
		FROM videos v
		INNER JOIN users u ON u.uid = v.uid
		WHERE v.uid = ? AND v.privacy = 1 AND v.converted = 1 AND u.termination = 0
		ORDER BY v.uploaded DESC
		LIMIT 5');
	$fallbackQuery->execute([$currentUser['uid']]);
	$fallbackVideos = $fallbackQuery->fetchAll();
}
if (!$fallbackVideos) {
	$fallbackQuery = $pdo->query('SELECT v.vid, v.title, u.username
		FROM videos v
		INNER JOIN users u ON u.uid = v.uid
		WHERE v.privacy = 1 AND v.converted = 1 AND u.termination = 0
		ORDER BY v.uploaded DESC
		LIMIT 5');
	$fallbackVideos = $fallbackQuery->fetchAll();
}
$fallbackProfileUser = $currentUser
	? (string)$currentUser['username']
	: (string)($fallbackVideos[0]['username'] ?? '');
$fallbackProfileUrl = $fallbackProfileUser !== ''
	? 'profile.php?user=' . rawurlencode($fallbackProfileUser) . '&tab=videos'
	: 'index.php';
$embedFallback = '<div style="font-family:Arial,Helvetica,sans-serif;background:#DDD;padding:5px;">'
	. '<a href="' . h($embedBaseUrl . $fallbackProfileUrl) . '" target="_blank">'
	. '<img src="' . h($embedBaseUrl . 'img/logo.gif') . '" width="76" height="30" alt="NobregaTube"></a>';
if ($fallbackVideos) {
	foreach ($fallbackVideos as $fallbackVideo) {
		$videoUrl = $embedBaseUrl . 'watch.php?v=' . rawurlencode($fallbackVideo['vid']);
		$embedFallback .= '<div style="padding:6px 0;border-bottom:1px dashed #999;">'
			. '<a href="' . h($videoUrl) . '" target="_blank">'
			. '<img src="' . h($embedBaseUrl . 'get_still.php?video_id=' . rawurlencode($fallbackVideo['vid'])) . '" width="80" height="60" alt=""></a> '
			. '<a href="' . h($videoUrl) . '" target="_blank">'
			. h($fallbackVideo['title']) . '</a></div>';
	}
} else {
	$embedFallback .= '<p>No public videos are available in this fallback preview.</p>';
}
$embedFallback .= '</div>';
$embedSnippet = $embedHostIsValid
	? '<iframe id="videos_list" name="videos_list" src="' . $embedUrl . '" scrolling="auto" width="265" height="400" frameborder="0" marginheight="0" marginwidth="0">' . "\n"
		. $embedFallback . "\n</iframe>"
	: '';
$embedPreviewUrl = $currentUser ? 'videos_list.php?user=' . rawurlencode((string)$currentUser['username']) : null;

$flashNotice = '';
if (isset($_GET['flash']) && in_array((string)$_GET['flash'], ['0', '1'], true)) {
	$enableFlash = (string)$_GET['flash'] === '1';
	setcookie('flash', $enableFlash ? '1' : '', [
		'expires' => $enableFlash ? 2147483647 : time() - 3600,
		'path' => '/',
		'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
		'httponly' => true,
		'samesite' => 'Lax',
	]);
	$flashNotice = $enableFlash
		? 'Videos will now be displayed in the Flash Player when available.'
		: 'Videos will now be displayed in the HTML5 Video Player.';
}
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html lang="en">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<title>NobregaTube - Help</title>
	<link rel="stylesheet" href="styles2005july.css">
</head>
<body>
	<?php $activeHeaderPage = ''; $showSubNav = true; include __DIR__ . '/includes/header.php'; ?>
	<div class="pageTitle">Help</div>
	<div class="pageTable">
		<?php if ($flashNotice !== ''): ?><p class="success"><?php echo h($flashNotice); ?></p><?php endif; ?>

		<br><br><span class="highlight">Q: What kind of videos can I upload?</span>
		<br><br>A: Upload videos you have the right to share and that follow our <a href="terms.php">Terms of Use</a>. Supported formats are MP4, WebM, Ogg, MOV, AVI, and FLV. Videos must be less than 100 MB.

		<br><br><span class="highlight">Q: How do I upload a video?</span>
		<br><br>A: Sign in, choose <a href="my_videos_upload.php">Upload Videos</a>, enter a title, description, and at least three keywords, then select your video file. You can choose whether the video is public or private.

		<br><br><span class="highlight">Q: How do I edit or remove one of my videos?</span>
		<br><br>A: Open <a href="my_videos.php">My Videos</a> and choose Edit Video or Remove Video beside the upload.

		<br><br><span class="highlight">Q: Where can I find videos I want to watch again?</span>
		<br><br>A: Add a video to <a href="my_favorites.php">My Favorites</a> while signed in. Your favorites are listed on your account.

		<br><br><span class="highlight">Q: Can I send messages to other NobregaTube members?</span>
		<br><br>A: Yes. Open a member's profile or video page and choose Contact Me. Replies and comment notifications appear in <a href="my_messages.php">My Messages</a>.

		<br><br><span class="highlight">Q: How do I show my videos on another website?</span>
		<?php if ($embedHostIsValid): ?>
			<br><br><textarea cols="65" rows="3" readonly><?php echo h($embedSnippet); ?></textarea>
		<?php else: ?>
			<br><br>The video list embed code is unavailable for this host.
		<?php endif; ?>
		<br><br><strong>What Shows Up</strong>
		<?php if ($embedPreviewUrl !== null): ?>
			<br><br><iframe id="videos_list" name="videos_list" src="<?php echo h($embedPreviewUrl); ?>" scrolling="auto" width="265" height="400" frameborder="0" marginheight="0" marginwidth="0"><?php echo $embedFallback; ?></iframe>
		<?php else: ?>
			<br><br>Sign in to preview your public video list here.
		<?php endif; ?>

		<br><br><span class="highlight">Q: How do I contact NobregaTube?</span>
		<br><br>A: For account or video issues, please contact us <a href="contact.php">here</a>. Suggestions are welcome too.
		<br><br>
	</div>
	<br>
	<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
