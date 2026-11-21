<?php
if (!isset($showHeaderLogo)) {
	$showHeaderLogo = true;
}
if (!isset($activeHeaderPage)) {
	$activeHeaderPage = '';
}
if (!isset($currentUser)) {
	$currentUser = null;
}
if (!isset($showSubNav)) {
	$showSubNav = false;
}
if (!isset($sitePrefix)) {
	$sitePrefix = '';
}
$isManagerFilm = strpos((string)($_SERVER['PHP_SELF'] ?? ''), '/admin/') !== false;
$utm_source = $_GET['utm_source'] ?? '';
if ($utm_source === 'chatgpt.com') {
	die('sonion, bro are opening an revival from chatgpt🥀 <br> <br> <a href="' . h($sitePrefix . 'index.php', ENT_QUOTES, 'UTF-8') . '">show website</a>');
}
$siteNoticeQuery = $pdo->query('SELECT message FROM site_notice WHERE notice_id = 1 LIMIT 1');
$siteNotice = trim((string)$siteNoticeQuery->fetchColumn());
?>
<table class="nav-box" align="center" width="100%" cellpadding="0" cellspacing="0" border="0">
	<tbody>
		<tr class="nav-box-corners">
			<td><img src="<?php echo $sitePrefix; ?>img/box_login_tl.gif" width="5" height="5" alt=""></td>
			<td><img src="<?php echo $sitePrefix; ?>img/pixel.gif" width="1" height="5" alt=""></td>
			<td><img src="<?php echo $sitePrefix; ?>img/box_login_tr.gif" width="5" height="5" alt=""></td>
		</tr>
		<tr>
			<td><img src="<?php echo $sitePrefix; ?>img/pixel.gif" width="5" height="1" alt=""></td>
			<td class="nav-box-content" width="100%">
				<table class="nav-links" width="100%" cellpadding="0" cellspacing="0" border="0">
					<tbody><tr><td>
						<table cellpadding="2" cellspacing="0" border="0">
							<tbody><tr>
								<td>&nbsp;<a href="<?php echo $sitePrefix; ?>index.php"<?php if ($activeHeaderPage === 'home') echo ' class="bold"'; ?>>Home</a></td>
								<td>&nbsp;|&nbsp;</td>
								<td><a href="<?php echo $sitePrefix; ?>my_videos.php"<?php if ($activeHeaderPage === 'videos') echo ' class="bold"'; ?>>My Videos</a></td>
								<td>&nbsp;|&nbsp;</td>
								<td><a href="<?php echo $sitePrefix; ?>my_favorites.php"<?php if ($activeHeaderPage === 'favorites') echo ' class="bold"'; ?>>My Favorites</a></td>
								<td>&nbsp;|&nbsp;</td>
								<td><a href="<?php echo $sitePrefix; ?>my_messages.php"<?php if ($activeHeaderPage === 'messages') echo ' class="bold"'; ?>>My Messages</a></td>
								<td>&nbsp;|&nbsp;</td>
								<td><a href="<?php echo $sitePrefix . ($currentUser ? 'my_profile.php' : 'login.php'); ?>"<?php if ($activeHeaderPage === 'profile') echo ' class="bold"'; ?>>My Profile</a></td>
							</tr></tbody>
						</table>
					</td></tr></tbody>
				</table>
			</td>
			<td><img src="<?php echo $sitePrefix; ?>img/pixel.gif" width="5" height="1" alt=""></td>
		</tr>
		<tr class="nav-box-corners">
			<td><img src="<?php echo $sitePrefix; ?>img/box_login_bl.gif" width="5" height="5" alt=""></td>
			<td><img src="<?php echo $sitePrefix; ?>img/pixel.gif" width="1" height="5" alt=""></td>
			<td><img src="<?php echo $sitePrefix; ?>img/box_login_br.gif" width="5" height="5" alt=""></td>
		</tr>
	</tbody>
</table>

<div class="table-link-bar">
	<table width="100%" cellpadding="0" cellspacing="0" border="0">
		<tbody><tr>
			<?php if ($showHeaderLogo): ?>
				<td width="130" rowspan="2"><a href="<?php echo $sitePrefix; ?>index.php"><img src="<?php echo $sitePrefix; ?>img/logo.gif" width="180" height="48" alt="YouFilm" border="0" hspace="5" vspace="8"></a></td>
			<?php endif; ?>
			<td width="100%" align="right">
				<table align="right" cellpadding="2" cellspacing="0" border="0">
					<tbody><tr>
						<?php if ($currentUser): ?>
							<td>Hello, <a href="<?php echo $sitePrefix; ?>profile.php?user=<?php echo rawurlencode($currentUser['username']); ?>"><?php echo h($currentUser['username'], ENT_QUOTES, 'UTF-8'); ?></a>!</td>
							<td>&nbsp;<a href="<?php echo $sitePrefix; ?>my_messages.php"><img src="<?php echo $sitePrefix; ?>img/<?php echo !empty($unreadMessages) ? 'mail_unread.gif' : 'mail.gif'; ?>" id="mailico" border="0" alt="Messages"></a>&nbsp;(<a href="<?php echo $sitePrefix; ?>my_messages.php"><?php echo isset($unreadMessages) ? (int)$unreadMessages : 0; ?></a>)</td>
							<td style="padding: 0px 5px 0px 5px;">|</td>
							<td><a href="<?php echo $sitePrefix; ?>logout.php?csrf_token=<?php echo rawurlencode($_SESSION['csrf_token']); ?>">Log Out</a></td>
						<?php else: ?>
							<td><a href="<?php echo $sitePrefix; ?>signup.php"<?php if ($activeHeaderPage === 'signup') echo ' class="bold"'; ?>>Sign Up</a></td>
							<td>&nbsp;|&nbsp;</td>
							<td><a href="<?php echo $sitePrefix; ?>login.php"<?php if ($activeHeaderPage === 'login') echo ' class="bold"'; ?>>Log In</a></td>
						<?php endif; ?>
						<td>&nbsp;|&nbsp;</td>
						<td><a href="<?php echo $sitePrefix; ?>help.php">Help</a>&nbsp;</td>
						<?php if ($currentUser && isset($currentUser['staff']) && (int)$currentUser['staff'] === 1): ?>
							<td>&nbsp;|&nbsp;</td>
							<td><a href="<?php echo $sitePrefix; ?>admin/index.php"<?php echo $isManagerFilm ? ' class="bold"' : ''; ?> style="color: #ff0000;">ManagerFilm</a>&nbsp;</td>
						<?php endif; ?>
					</tr></tbody>
				</table>
			</td>
		</tr>
		<?php if ($showSubNav): ?>
		<tr>
			<td width="100%">
				<table cellpadding="2" cellspacing="0" border="0">
					<tbody><tr>
						<td>
							<form class="header-search-form" method="get" action="<?php echo $sitePrefix; ?>results.php">
								<table cellpadding="2" cellspacing="0" border="0"><tbody><tr>
									<td nowrap><input class="header-search-input" type="text" value="" name="search" size="30" maxlength="128"></td>
									<td nowrap><input type="submit" value="Search Videos"></td>
								</tr></tbody></table>
							</form>
						</td>
						<td width="100%"><div class="header-action-links"><a href="<?php echo $sitePrefix; ?>browse.php">Browse Videos</a><img border="0" src="<?php echo $sitePrefix; ?>img/new.gif" alt="New"> &nbsp;//&nbsp; <a href="<?php echo $sitePrefix; ?>my_videos_upload.php">Upload Videos</a></div></td>
					</tr></tbody>
				</table>
			</td>
		</tr>
		<?php endif; ?>
		</tbody>
	</table>
	</div>

<?php if ($siteNotice !== '' && basename((string)($_SERVER['PHP_SELF'] ?? '')) === 'index.php' && !$isManagerFilm): ?>
<table width="100%" align="center" bgcolor="#666666" cellpadding="6" cellspacing="3" border="0">
		<tbody><tr>
			<td align="center" bgcolor="#FFFFFF"><span class="success"><?php echo notice_html('Notice: ' . $siteNotice); ?></span></td>
		</tr>
	</tbody></table></br>
<?php endif; ?>
