<link href="styles2005july.css" rel="stylesheet" type="text/css">
<style>
body { background-color: #DDD; }
</style>
<?php
require __DIR__ . '/includes/database.php';

$search = trim((string)($_GET['search'] ?? ''));
$vid = substr((string)($_GET['v'] ?? ''), 0, 20);
$videos = [];

if ($search !== '' && $vid !== '') {
	$rx = str_replace(' ', '|', preg_quote($search, '/'));
	$query = $pdo->prepare('SELECT v.*, u.username,
		(SELECT COUNT(*) FROM comments c WHERE c.vid = v.vid AND c.removed = 0) AS comment_count
		FROM videos v LEFT JOIN users u ON u.uid = v.uid
		WHERE (v.tags REGEXP ? OR v.description REGEXP ? OR v.title REGEXP ? OR u.username REGEXP ?)
		AND v.privacy = 1 AND v.converted = 1 AND u.termination = 0
		ORDER BY v.uploaded DESC LIMIT 10');
	$query->execute([$rx, $rx, $rx, $rx]);
	$videos = $query->fetchAll();
}

if (!$videos) { ?>
<p align="center"><div class="highlight">We found nothing!</div></p>
<?php
	exit;
}

foreach ($videos as $video) {
	if ($vid === (string)$video['vid']) {
		echo '<a id="selected"></a>';
		$moduleEntry = 'moduleFrameEntrySelected';
	} else {
		$moduleEntry = 'moduleFrameEntry';
	}
?>
<div class="<?php echo $moduleEntry; ?>">
<table width="100%" cellpadding="0" cellspacing="0" border="0">
	<tbody><tr valign="top">
		<td><a href="watch.php?v=<?php echo h($video['vid']); ?>" class="bold" target="_parent"><img src="get_still.php?video_id=<?php echo h($video['vid']); ?>" class="moduleEntryThumb" width="80" height="60" alt=""></a></td>
		<td width="100%">
			<div class="moduleFrameTitle"><a href="watch.php?v=<?php echo h($video['vid']); ?>" target="_parent"><?php echo h($video['title']); ?></a></div>
			<div class="moduleFrameDetails">Added: <?php echo h(date('F j, Y', strtotime($video['uploaded']))); ?><br>by <a href="profile.php?user=<?php echo rawurlencode($video['username']); ?>" target="_parent"><?php echo h($video['username']); ?></a></div>
			<div class="moduleFrameDetails">Views: <?php echo (int)$video['views']; ?><br>Comments: <?php echo (int)$video['comment_count']; ?></div>
			<?php if ($moduleEntry === 'moduleFrameEntrySelected'): ?><div style="font-size: 13px; font-weight: bold; padding: 4px 6px; background-color: #FFCC66; color: #AA7733; width: 125px;">&#60;&#60;&#60; NOW PLAYING!</div><?php endif; ?>
		</td>
	</tr></tbody>
</table>
</div>
<?php } ?>
