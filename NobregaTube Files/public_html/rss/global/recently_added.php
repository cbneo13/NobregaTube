<?php
require __DIR__ . '/../../includes/database.php';

function rss_xml($value): string
{
	$value = (string)$value;
	$value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value);
	return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$host = (string)($_SERVER['HTTP_HOST'] ?? '');
if (!preg_match('/\A(?:[a-z0-9.-]+|\[[0-9a-f:]+\])(?::[0-9]{1,5})?\z/i', $host)) {
	$host = (string)($_SERVER['SERVER_NAME'] ?? 'localhost');
	if (!preg_match('/\A(?:[a-z0-9.-]+|\[[0-9a-f:]+\])(?::[0-9]{1,5})?\z/i', $host)) {
		$host = 'localhost';
	}
}
$scheme = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
$projectPath = str_replace('\\', '/', dirname(dirname(dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/rss/global/recently_added.php')))));
$projectPath = $projectPath === '/' || $projectPath === '.' ? '' : '/' . trim($projectPath, '/');
$siteUrl = $scheme . '://' . $host . $projectPath;
$feedUrl = $siteUrl . '/rss/global/recently_added.rss';

$videoQuery = $pdo->query('SELECT v.vid, v.title, v.description, v.uploaded, u.username
	FROM videos v
	INNER JOIN users u ON u.uid = v.uid
	WHERE v.privacy = 1 AND v.converted = 1 AND u.termination = 0
	ORDER BY v.uploaded DESC, v.vid DESC
	LIMIT 20');
$videos = $videoQuery->fetchAll();

header('Content-Type: application/rss+xml; charset=utf-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">' . "\n";
echo "  <channel>\n";
echo "    <title>NobregaTube - Recently Added Videos</title>\n";
echo '    <link>' . rss_xml($siteUrl . '/') . "</link>\n";
echo "    <description>Recently added public videos on NobregaTube.</description>\n";
echo "    <language>en-us</language>\n";
echo '    <lastBuildDate>' . rss_xml(gmdate(DATE_RSS)) . "</lastBuildDate>\n";
echo '    <atom:link href="' . rss_xml($feedUrl) . '" rel="self" type="application/rss+xml" />' . "\n";

foreach ($videos as $video) {
	$videoUrl = $siteUrl . '/watch.php?v=' . rawurlencode($video['vid']);
	$description = trim((string)$video['description']);
	$description = $description !== ''
		? $video['username'] . ': ' . $description
		: 'Video by ' . $video['username'];
	$uploadedAt = strtotime($video['uploaded'] . ' UTC');
	$publishedAt = $uploadedAt === false ? '' : gmdate(DATE_RSS, $uploadedAt);

	echo "    <item>\n";
	echo '      <title>' . rss_xml($video['title']) . "</title>\n";
	echo '      <link>' . rss_xml($videoUrl) . "</link>\n";
	echo '      <description>' . rss_xml($description) . "</description>\n";
	if ($publishedAt !== '') {
		echo '      <pubDate>' . rss_xml($publishedAt) . "</pubDate>\n";
	}
	echo '      <guid isPermaLink="true">' . rss_xml($videoUrl) . "</guid>\n";
	echo "    </item>\n";
}

echo "  </channel>\n";
echo "</rss>\n";
