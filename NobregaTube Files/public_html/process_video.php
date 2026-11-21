<?php
if (PHP_SAPI !== 'cli' || empty($argv[1]) || !preg_match('/^[a-f0-9]{20}$/', $argv[1])) {
	exit(1);
}
require __DIR__ . '/includes/database.php';
$videoId = $argv[1];
$query = $pdo->prepare('SELECT file_path FROM videos WHERE vid = ? LIMIT 1');
$query->execute([$videoId]);
$video = $query->fetch();
if (!$video) {
	exit(1);
}
$source = __DIR__ . '/data/videos/' . basename($video['file_path']);
if (!is_file($source)) {
	$pdo->prepare('UPDATE videos SET converted = 3, queue_error = ? WHERE vid = ?')->execute(['The uploaded source file is missing.', $videoId]);
	exit(1);
}
$pdo->prepare('UPDATE videos SET converted = 2, queue_error = NULL WHERE vid = ?')->execute([$videoId]);
$videoDir = __DIR__ . '/data/videos';
$thumbDir = __DIR__ . '/data/thmbs';
$webmName = $videoId . '.webm';
$flvName = $videoId . '.flv';
$webm = $videoDir . '/' . $webmName;
$flv = $videoDir . '/' . $flvName;
try {
	$durationOutput = [];
	exec('ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 '
		. escapeshellarg($source) . ' 2>&1', $durationOutput, $durationStatus);
	$duration = $durationStatus === 0 ? (int)round((float)trim(implode("\n", $durationOutput))) : 0;
	if ($duration < 1) {
		throw new RuntimeException('The video duration could not be read.');
	}
	$output = [];
	exec('ffmpeg -y -i ' . escapeshellarg($source)
		. ' -c:v libvpx -vf ' . escapeshellarg('scale=-2:240,format=yuv420p')
		. ' -q:v 10 -c:a libvorbis -b:a 80k -ar 22050 -ac 1 '
		. escapeshellarg($webm) . ' 2>&1', $output, $webmStatus);
	if ($webmStatus !== 0 || !is_file($webm)) {
		throw new RuntimeException('WebM conversion failed.');
	}
	$output = [];
	exec('ffmpeg -y -i ' . escapeshellarg($source)
		. ' -vf ' . escapeshellarg('scale=320:240:force_original_aspect_ratio=decrease,pad=320:240:(ow-iw)/2:(oh-ih)/2:color=black')
		. ' -c:v flv1 -b:v 350k -c:a mp3 -b:a 80k -ar 22050 '
		. escapeshellarg($flv) . ' 2>&1', $output, $flvStatus);
	if ($flvStatus !== 0 || !is_file($flv)) {
		throw new RuntimeException('Flash video conversion failed.');
	}
	foreach ([1 => 0.25, 2 => 0.50, 3 => 0.75] as $stillId => $position) {
		$stillTime = max(0, (int)floor($duration * $position));
		$still = $thumbDir . '/' . $videoId . '_' . $stillId . '.jpg';
		$output = [];
		exec('ffmpeg -y -ss ' . $stillTime . ' -i ' . escapeshellarg($source)
			. ' -frames:v 1 -vf ' . escapeshellarg('scale=120:90:force_original_aspect_ratio=decrease,pad=120:90:(ow-iw)/2:(oh-ih)/2')
			. ' -an -q:v 5 ' . escapeshellarg($still) . ' 2>&1', $output, $stillStatus);
		if ($stillStatus !== 0 || !is_file($still)) {
			throw new RuntimeException('Still image generation failed.');
		}
	}
	$complete = $pdo->prepare('UPDATE videos SET file_path = ?, flv_path = ?, mime_type = ?, duration = ?, converted = 1, queue_error = NULL WHERE vid = ?');
	$complete->execute([$webmName, $flvName, 'video/webm', $duration, $videoId]);
	if ($complete->rowCount() === 0) {
		$stillExists = $pdo->prepare('SELECT 1 FROM videos WHERE vid = ? LIMIT 1');
		$stillExists->execute([$videoId]);
		if (!$stillExists->fetchColumn()) {
			foreach ([$source, $webm, $flv] as $orphanedFile) {
				if (is_file($orphanedFile)) {
					unlink($orphanedFile);
				}
			}
			foreach ([1, 2, 3] as $stillId) {
				$orphanedStill = $thumbDir . '/' . $videoId . '_' . $stillId . '.jpg';
				if (is_file($orphanedStill)) {
					unlink($orphanedStill);
				}
			}
			exit;
		}
	}
	@unlink($source);
} catch (Throwable $error) {
	$failed = $pdo->prepare('UPDATE videos SET converted = 3, queue_error = ? WHERE vid = ?');
	$failed->execute([substr($error->getMessage(), 0, 255), $videoId]);
	if ($failed->rowCount() === 0) {
		$stillExists = $pdo->prepare('SELECT 1 FROM videos WHERE vid = ? LIMIT 1');
		$stillExists->execute([$videoId]);
		if (!$stillExists->fetchColumn()) {
			foreach ([$source, $webm, $flv] as $orphanedFile) {
				if (is_file($orphanedFile)) {
					unlink($orphanedFile);
				}
			}
			foreach ([1, 2, 3] as $stillId) {
				$orphanedStill = $thumbDir . '/' . $videoId . '_' . $stillId . '.jpg';
				if (is_file($orphanedStill)) {
					unlink($orphanedStill);
				}
			}
		}
	}
}
