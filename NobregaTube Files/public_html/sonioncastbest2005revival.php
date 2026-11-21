<?php
function h($value, int $flags = ENT_QUOTES | ENT_SUBSTITUTE, string $encoding = 'UTF-8'): string
{
	return htmlspecialchars((string)$value, $flags, $encoding);
}

function notice_html($value): string
{
	return nl2br(h($value), false);
}
if (!empty($_GET['discord'])) {
	header('Content-Type: text/html; charset=UTF-8');
	echo '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">';
	echo '<html><head><title>Redirecting to Discord</title>';
	echo '<meta http-equiv="refresh" content="4;url=https://discord.gg/wKnXCFdFap">';
	echo '</head><body>';
	echo '<p>Redirecting to Discord...</p>';
	echo '<p><img src="img/monkey.png" alt="Monkey, why?"></p>';
	echo '</body></html>';
	exit;
}
if (isset($_GET['newtime'])) {
	header('Content-Type: text/html; charset=UTF-8');
	echo '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">';
	echo '<html><head><title>Redirecting to Discord</title>';
	echo '<meta http-equiv="refresh" content="4;url=http://nobregatube.lunmo.ws">';
	echo '</head><body>';
	echo '<p>RubyTube is gone... but you can use this another revival that i made!</p>';
	echo '</body></html>';
	exit;
}