<?php
require_once __DIR__ . '/includes/sess.php';
require __DIR__ . '/includes/database.php';

$currentUser = null;
$unreadMessages = 0;
if (!empty($_SESSION['uid'])) {
	$userQuery = $pdo->prepare('SELECT uid, username FROM users WHERE uid = ?');
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
if (empty($_SESSION['csrf_token'])) {
	$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errors = [];
if (isset($_GET['tags'])) {
	$errors[] = 'You need at least three keywords!';
}
if (isset($_GET['title'])) {
	$errors[] = 'Title is too short.';
}
if (isset($_GET['desc'])) {
	$errors[] = 'Please fill in a description.';
}

$months = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
require __DIR__ . '/includes/countries.php';

$title = '';
$description = '';
$tags = '';
$address = '';
$country = '';
$month = '---';
$day = '---';
$year = '---';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$title = trim((string)($_POST['field_upload_title'] ?? ''));
	$description = trim((string)($_POST['field_upload_description'] ?? ''));
	$tags = trim((string)($_POST['field_upload_tags'] ?? ''));
	$address = trim((string)($_POST['field_upload_address'] ?? ''));
	$country = (string)($_POST['field_upload_country'] ?? '');
	$month = (string)($_POST['addr_month'] ?? '---');
	$day = (string)($_POST['addr_day'] ?? '---');
	$year = (string)($_POST['addr_yr'] ?? '---');
	$submittedToken = (string)($_POST['csrf_token'] ?? '');

	if (!hash_equals($_SESSION['csrf_token'], $submittedToken)) {
		$errors[] = 'Your form session expired. Please refresh the page and try again.';
	} else {
		if ($title === '' || $description === '' || $tags === '') {
			$errors[] = 'All fields are required.';
		}
		foreach (['Title' => $title, 'Description' => $description, 'Tags' => $tags] as $fieldName => $fieldValue) {
			if (preg_match('/[\\p{Arabic}\\p{Cf}]/u', $fieldValue) === 1 || stripos($fieldValue, 'bismillah') !== false) {
				$errors[] = $fieldName . ' contains unsupported or suspicious characters.';
			}
		}
		if (strlen($title) < 2 || strlen($title) > 100) {
			$errors[] = 'Title must be between 2 and 100 characters.';
		}
		$tagWords = preg_split('/\\s+/', $tags, -1, PREG_SPLIT_NO_EMPTY);
		if (count(array_unique(array_map('strtolower', $tagWords))) < 3) {
			$errors[] = 'You need at least three keywords!';
		}
		if (strlen($description) > 1000) {
			$errors[] = 'Description must be 1000 characters or less.';
		}
		if ($month !== '---' || $day !== '---' || $year !== '---') {
			if (!ctype_digit($month) || !ctype_digit($day) || !ctype_digit($year) || !checkdate((int)$month, (int)$day, (int)$year)) {
				$errors[] = 'Please enter a valid recording date or leave it blank.';
			}
		}
		if ($country !== '' && !isset($countries[$country])) {
			$errors[] = 'Please select a valid country.';
		}

		if (!$errors) {
			$_SESSION['pending_upload'] = [
				'title' => $title,
				'description' => $description,
				'tags' => $tags,
				'address' => $address,
				'country' => $country,
				'month' => $month,
				'day' => $day,
				'year' => $year,
			];
			header('Location: my_videos_upload_2.php');
			exit;
		}
	}
}
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html lang="en">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
	<title>NobregaTube - Video Upload</title>
	<link rel="stylesheet" href="styles2005july.css">
</head>
<body>
	<?php
	$activeHeaderPage = 'upload';
	include __DIR__ . '/includes/header_search.php';
	?>

	<?php foreach ($errors as $error): ?>
		<table width="100%" align="center" bgcolor="#FF0000" cellpadding="6" cellspacing="3" border="0">
			<tbody><tr><td align="center" bgcolor="#FFFFFF"><span class="error"><?php echo h($error); ?></span></td></tr></tbody>
		</table><br>
	<?php endforeach; ?>

	<div class="form-title">Video Upload (Step 1 of 2)</div>
	<form method="post" action="my_videos_upload.php">
		<input type="hidden" name="field_command" value="upload_video">
		<input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
		<div class="page-table">
			<table width="100%" cellpadding="5" cellspacing="0" border="0">
				<tbody>
					<tr>
						<td width="200" align="right"><span class="label">Title:</span></td>
						<td><input type="text" size="30" maxlength="60" name="field_upload_title" value="<?php echo h($title); ?>"></td>
					</tr>
					<tr>
						<td align="right" valign="top"><span class="label">Description:</span></td>
						<td><textarea name="field_upload_description" cols="40" rows="4"><?php echo h($description); ?></textarea></td>
					</tr>
					<tr>
						<td width="200" align="right"><span class="label">Tags:</span></td>
						<td><input type="text" size="30" maxlength="60" name="field_upload_tags" value="<?php echo h($tags); ?>"></td>
					</tr>
					<tr>
						<td>&nbsp;</td>
						<td><div class="form-field-info"><strong>Enter three or more keywords, separated by spaces, describing your video.</strong><br>It helps to use relevant keywords so others can find your video!</div></td>
					</tr>
				</tbody>
			</table>
		</div>

		<div class="form-title">Date &amp; Address Details</div>
		<div class="page-table">
			<table width="100%" cellpadding="5" cellspacing="0" border="0">
				<tbody>
					<tr>
						<td width="200" align="right"><span class="label">Date Recorded:</span><br><span class="form-field-info">(Optional)</span></td>
						<td>
							<select name="addr_month"><option value="---"<?php if ($month === '---') echo ' selected'; ?>>---</option><?php foreach ($months as $number => $monthName): ?><option value="<?php echo $number; ?>"<?php if ((string)$number === $month) echo ' selected'; ?>><?php echo $monthName; ?></option><?php endforeach; ?></select>
							<select name="addr_day"><option value="---"<?php if ($day === '---') echo ' selected'; ?>>---</option><?php for ($dayOption = 1; $dayOption <= 31; $dayOption++): ?><option value="<?php echo $dayOption; ?>"<?php if ((string)$dayOption === $day) echo ' selected'; ?>><?php echo $dayOption; ?></option><?php endfor; ?></select>
							<select name="addr_yr"><option value="---"<?php if ($year === '---') echo ' selected'; ?>>---</option><?php for ($yearOption = (int)date('Y'); $yearOption >= 1900; $yearOption--): ?><option value="<?php echo $yearOption; ?>"<?php if ((string)$yearOption === $year) echo ' selected'; ?>><?php echo $yearOption; ?></option><?php endfor; ?></select>
						</td>
					</tr>
					<tr>
						<td width="200" align="right"><span class="label">Address Recorded:</span><br><span class="form-field-info">(Optional)</span></td>
						<td><input type="text" size="30" maxlength="160" name="field_upload_address" value="<?php echo h($address); ?>"></td>
					</tr>
				</tbody>
			</table>
			<div class="address-example"><span class="form-field-info">Examples: &quot;165 University Ave, Palo Alto, CA&quot; &quot;New York City, NY&quot; &quot;Tokyo&quot;</span></div>
			<table width="100%" cellpadding="5" cellspacing="0" border="0">
				<tbody>
					<tr>
						<td width="200" align="right"><span class="label">Country:</span><br><span class="form-field-info">(Optional)</span></td>
		<td><select name="field_upload_country"><?php foreach ($countries as $code => $countryName): ?><option value="<?php echo h((string)$code); ?>"<?php if ((string)$code === $country) echo ' selected'; ?>><?php echo h((string)$countryName); ?></option><?php endforeach; ?></select></td>
					</tr>
					<tr><td colspan="2"><br></td></tr>
					<tr><td>&nbsp;</td><td><input type="submit" value="Continue -&gt;"></td></tr>
				</tbody>
			</table>
		</div>
	</form>

	<?php include __DIR__ . '/includes/footer.php'; ?>
