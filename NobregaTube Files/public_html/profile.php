<?php
require_once __DIR__ . '/includes/sess.php';
require __DIR__ . '/includes/database.php';
require __DIR__ . '/includes/countries.php';

$currentUser = null;
$unreadMessages = 0;
if (!empty($_SESSION['uid'])) {
	$userQuery = $pdo->prepare('SELECT uid, username, staff FROM users WHERE uid = ? LIMIT 1');
	$userQuery->execute([$_SESSION['uid']]);
	$currentUser = $userQuery->fetch();
	if ($currentUser) {
		$messageQuery = $pdo->prepare('SELECT COUNT(*) FROM messages WHERE receiver = ? AND isRead = 0');
		$messageQuery->execute([$currentUser['uid']]);
		$unreadMessages = (int)$messageQuery->fetchColumn();
	} else {
		unset($_SESSION['uid'], $_SESSION['username']);
	}
}
if (empty($_SESSION['csrf_token'])) {
	$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}



function render_profile_video_list(array $videos, string $emptyMessage): void
{
	if (!$videos) {
		echo '<p class="profile-empty">' . h($emptyMessage) . '</p>';
		return;
	}

	foreach ($videos as $video) {
		?>
		<div class="moduleEntry">
			<table width="100%" cellpadding="0" cellspacing="0" border="0">
				<tbody><tr valign="top">
					<td>
						<a href="watch.php?v=<?php echo rawurlencode($video['vid']); ?>">
							<img src="get_still.php?video_id=<?php echo rawurlencode($video['vid']); ?>" class="moduleEntryThumb" width="120" height="90" alt="<?php echo h($video['title']); ?>">
						</a>
					</td>
					<td width="100%">
						<div class="moduleEntryTitle"><a href="watch.php?v=<?php echo rawurlencode($video['vid']); ?>"><?php echo h($video['title']); ?></a></div>
						<div class="moduleEntryDescription"><?php echo nl2br(h($video['description'])); ?></div>
						<div class="moduleEntryDetails">Added: <?php echo h(date('F j, Y', strtotime($video['uploaded']))); ?></div>
						<div class="moduleEntryDetails">Views: <?php echo (int)$video['views']; ?> | Comments: <?php echo (int)$video['comment_count']; ?></div>
					</td>
				</tr></tbody>
			</table>
		</div>
		<?php
	}
}

$username = trim((string)($_GET['user'] ?? ''));
$activeTab = (string)($_GET['tab'] ?? 'profile');
if (!in_array($activeTab, ['profile', 'videos', 'favorites'], true)) {
	$activeTab = 'profile';
}
$profile = null;
$videos = [];
$favorites = [];
$videoCount = 0;
$favoriteCount = 0;
$latestVideo = null;
$profileAge = null;
$profileGender = null;
$profileRelationship = null;
$profileCountry = null;
$profileWebsiteIsValid = false;

if (preg_match('/^[A-Za-z0-9_]{3,20}$/', $username)) {
	$profileQuery = $pdo->prepare('SELECT uid, username, joined, staff, name, birthday, gender, relationship, about, website, hometown, city, country, occupations, companies, schools, hobbies, fav_media, music, books
		FROM users WHERE username = ? AND termination = 0 LIMIT 1');
	$profileQuery->execute([$username]);
	$profile = $profileQuery->fetch();
}

if ($profile) {
	if (!empty($profile['birthday'])) {
		$profileAge = (new DateTime($profile['birthday']))->diff(new DateTime('today'))->y;
	}
	$profileGender = [1 => 'Male', 2 => 'Female', 3 => 'Other'][(int)$profile['gender']] ?? null;
	$profileRelationship = [1 => 'Single', 2 => 'Taken'][(int)$profile['relationship']] ?? null;
	$profileCountry = $countries[$profile['country'] ?? ''] ?? null;
	$websiteParts = parse_url((string)$profile['website']);
	$profileWebsiteIsValid = !empty($profile['website'])
		&& filter_var($profile['website'], FILTER_VALIDATE_URL)
		&& in_array(strtolower((string)($websiteParts['scheme'] ?? '')), ['http', 'https'], true);

	$videoCountQuery = $pdo->prepare('SELECT COUNT(*) FROM videos WHERE uid = ? AND privacy = 1 AND converted = 1');
	$videoCountQuery->execute([$profile['uid']]);
	$videoCount = (int)$videoCountQuery->fetchColumn();

	$favoriteCountQuery = $pdo->prepare('SELECT COUNT(*)
		FROM favorites f
		INNER JOIN videos v ON v.vid = f.vid
		WHERE f.uid = ? AND v.privacy = 1 AND v.converted = 1');
	$favoriteCountQuery->execute([$profile['uid']]);
	$favoriteCount = (int)$favoriteCountQuery->fetchColumn();

	$latestVideoQuery = $pdo->prepare('SELECT v.vid, v.title, v.uploaded, v.views,
		(SELECT COUNT(*) FROM comments c WHERE c.vid = v.vid AND c.removed = 0) AS comment_count
		FROM videos v
		WHERE v.uid = ? AND v.privacy = 1 AND v.converted = 1
		ORDER BY v.uploaded DESC
		LIMIT 1');
	$latestVideoQuery->execute([$profile['uid']]);
	$latestVideo = $latestVideoQuery->fetch();

	$videosQuery = $pdo->prepare('SELECT v.vid, v.title, v.description, v.uploaded, v.views,
		(SELECT COUNT(*) FROM comments c WHERE c.vid = v.vid AND c.removed = 0) AS comment_count
		FROM videos v
		WHERE v.uid = ? AND v.privacy = 1 AND v.converted = 1
		ORDER BY v.uploaded DESC
		LIMIT 10');
	$videosQuery->execute([$profile['uid']]);
	$videos = $videosQuery->fetchAll();

	$favoritesQuery = $pdo->prepare('SELECT v.vid, v.title, v.description, v.uploaded, v.views,
		(SELECT COUNT(*) FROM comments c WHERE c.vid = v.vid AND c.removed = 0) AS comment_count
		FROM favorites f
		INNER JOIN videos v ON v.vid = f.vid
		WHERE f.uid = ? AND v.privacy = 1 AND v.converted = 1
		ORDER BY f.created DESC
		LIMIT 10');
	$favoritesQuery->execute([$profile['uid']]);
	$favorites = $favoritesQuery->fetchAll();
} else {
	http_response_code(404);
}
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html lang="en">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
	<title><?php echo $profile ? h($profile['username']) . ' - NobregaTube Profile' : 'Profile Not Found - NobregaTube'; ?></title>
	<link rel="stylesheet" href="styles2005july.css">
</head>
<body>
	<?php
	$activeHeaderPage = '';
	include __DIR__ . '/includes/header_search.php';
	?>

	<?php if (!$profile): ?>
		<div class="pageTitle">User Profile</div>
		<p>We couldn't find that user. Check the user name and try again.</p>
	<?php else: ?>
		<div class="pageTitle">Profile // <span style="text-transform: capitalize;"><?php echo h($profile['username']); ?></span></div>

		<div style="text-align:center; margin-bottom: 5px;">
			<a href="profile.php?user=<?php echo rawurlencode($profile['username']); ?>&amp;tab=profile"<?php if ($activeTab === 'profile') echo ' class="bold"'; ?>>Profile</a>
			<span style="padding-right: 5px; padding-left: 5px;">|</span>
			<a href="profile.php?user=<?php echo rawurlencode($profile['username']); ?>&amp;tab=videos"<?php if ($activeTab === 'videos') echo ' class="bold"'; ?>>Videos</a> (<?php echo $videoCount; ?>)
			<span style="padding-right: 5px; padding-left: 5px;">|</span>
			<a href="profile.php?user=<?php echo rawurlencode($profile['username']); ?>&amp;tab=favorites"<?php if ($activeTab === 'favorites') echo ' class="bold"'; ?>>Favorites</a> (<?php echo $favoriteCount; ?>)
		</div>

		<?php if ($activeTab === 'profile'): ?>
		<table width="80%" align="center" cellpadding="5" cellspacing="0" border="0">
			<tbody><tr valign="top">
				<td width="200">
					<div style="background-color: #D5E5F5; padding: 10px; padding-top: 5px; border: 6px double #FFFFFF;">
						<table width="100%" bgcolor="#D5E5F5" cellpadding="5" cellspacing="0" border="0">
							<tbody><tr><td align="center">
								<?php if ($latestVideo): ?>
									<div class="highlight">Last Video Added</div>
									<a href="watch.php?v=<?php echo rawurlencode($latestVideo['vid']); ?>"><img src="get_still.php?video_id=<?php echo rawurlencode($latestVideo['vid']); ?>" class="moduleFeaturedThumb" width="120" height="90" alt="<?php echo h($latestVideo['title']); ?>"></a>
									<div class="moduleFeaturedTitle"><a href="watch.php?v=<?php echo rawurlencode($latestVideo['vid']); ?>"><?php echo h($latestVideo['title']); ?></a></div>
									<div class="moduleFeaturedDetails">Added: <?php echo h(date('F j, Y', strtotime($latestVideo['uploaded']))); ?><br>by <a href="profile.php?user=<?php echo rawurlencode($profile['username']); ?>"><?php echo h($profile['username']); ?></a></div>
									<div class="moduleFeaturedDetails">Views: <?php echo (int)$latestVideo['views']; ?></div>
									<div class="moduleFeaturedDetails">Comments: <?php echo (int)$latestVideo['comment_count']; ?></div>
								<?php endif; ?>
							</td></tr></tbody>
						</table>
					</div>
					<?php if ($currentUser && (string)$currentUser['uid'] !== (string)$profile['uid']): ?>
						<form method="get" action="outbox.php" style="text-align: center; margin-top: 8px;">
							<input type="hidden" name="user" value="<?php echo h($profile['username']); ?>">
							<input type="submit" value="Contact Me!">
						</form>
					<?php endif; ?>
				</td>
				<td>
					<div class="tableSubTitle">User Details:</div>
					<table width="100%" cellpadding="5" cellspacing="0" border="0">
						<tbody>
							<tr>
								<td width="150" align="right"><span class="label">User Name:</span></td>
								<td><?php echo h($profile['username']); ?></td>
							</tr>
							<tr>
								<td align="right"><span class="label">Joined:</span></td>
								<td><?php echo h(date('F j, Y', strtotime($profile['joined']))); ?></td>
							</tr>
							<?php if (!empty($profile['name'])): ?>
							<tr><td align="right"><span class="label">Name:</span></td><td><?php echo h($profile['name']); ?></td></tr>
							<?php endif; ?>
							<?php if ($profileAge !== null): ?>
							<tr><td align="right"><span class="label">Age:</span></td><td><?php echo $profileAge; ?> years old</td></tr>
							<?php endif; ?>
							<?php if ($profileGender !== null): ?>
							<tr><td align="right"><span class="label">Gender:</span></td><td><?php echo h($profileGender); ?></td></tr>
							<?php endif; ?>
							<?php if ($profileRelationship !== null): ?>
							<tr><td align="right"><span class="label">Relationship Status:</span></td><td><?php echo h($profileRelationship); ?></td></tr>
							<?php endif; ?>
							<?php if (!empty($profile['about'])): ?>
							<tr><td align="right" valign="top"><span class="label">About Me:</span></td><td><?php echo nl2br(h($profile['about'])); ?></td></tr>
							<?php endif; ?>
							<?php if (!empty($profile['website'])): ?>
							<tr><td align="right"><span class="label">Personal Website:</span></td><td><?php if ($profileWebsiteIsValid): ?><a href="<?php echo h($profile['website']); ?>" target="_blank" rel="noopener noreferrer"><?php echo h($profile['website']); ?></a><?php else: ?><?php echo h($profile['website']); ?><?php endif; ?></td></tr>
							<?php endif; ?>
							<?php if (!empty($profile['hometown'])): ?>
							<tr><td align="right"><span class="label">Hometown:</span></td><td><?php echo h($profile['hometown']); ?></td></tr>
							<?php endif; ?>
							<?php if (!empty($profile['city'])): ?>
							<tr><td align="right"><span class="label">Current City:</span></td><td><?php echo h($profile['city']); ?></td></tr>
							<?php endif; ?>
							<?php if ($profileCountry): ?>
							<tr><td align="right"><span class="label">Current Country:</span></td><td><?php echo h($profileCountry); ?></td></tr>
							<?php endif; ?>
							<?php if (!empty($profile['occupations'])): ?>
							<tr><td align="right"><span class="label">Occupations:</span></td><td><?php echo h($profile['occupations']); ?></td></tr>
							<?php endif; ?>
							<?php if (!empty($profile['companies'])): ?>
							<tr><td align="right"><span class="label">Companies:</span></td><td><?php echo h($profile['companies']); ?></td></tr>
							<?php endif; ?>
							<?php if (!empty($profile['schools'])): ?>
							<tr><td align="right"><span class="label">Schools:</span></td><td><?php echo h($profile['schools']); ?></td></tr>
							<?php endif; ?>
							<?php if (!empty($profile['hobbies'])): ?>
							<tr><td align="right"><span class="label">Interests &amp; Hobbies:</span></td><td><?php echo h($profile['hobbies']); ?></td></tr>
							<?php endif; ?>
							<?php if (!empty($profile['fav_media'])): ?>
							<tr><td align="right"><span class="label">Favorite Movies &amp; Shows:</span></td><td><?php echo h($profile['fav_media']); ?></td></tr>
							<?php endif; ?>
							<?php if (!empty($profile['music'])): ?>
							<tr><td align="right"><span class="label">Favorite Music:</span></td><td><?php echo h($profile['music']); ?></td></tr>
							<?php endif; ?>
							<?php if (!empty($profile['books'])): ?>
							<tr><td align="right"><span class="label">Favorite Books:</span></td><td><?php echo h($profile['books']); ?></td></tr>
							<?php endif; ?>
						</tbody>
					</table>
				</td>
			</tr></tbody>
		</table>
		<?php elseif ($activeTab === 'videos'): ?>
		<div class="pageTable">
			<div class="tableSubTitle">Videos // <?php echo h($profile['username']); ?> (<?php echo $videoCount; ?>)</div>
			<?php render_profile_video_list($videos, 'This user has not uploaded any public videos yet.'); ?>
		</div>
		<?php else: ?>
		<div class="pageTable">
			<div class="tableSubTitle">Favorites // <?php echo h($profile['username']); ?> (<?php echo $favoriteCount; ?>)</div>
			<?php render_profile_video_list($favorites, 'This user has no public favorite videos yet.'); ?>
		</div>
		<?php endif; ?>
	<?php endif; ?>

	<br>
	<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
