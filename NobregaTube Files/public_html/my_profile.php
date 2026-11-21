<?php
require_once __DIR__ . '/includes/sess.php';
require __DIR__ . '/includes/database.php';
require __DIR__ . '/includes/countries.php';

if (empty($_SESSION['uid'])) {
	header('Location: login.php');
	exit;
}

$userQuery = $pdo->prepare('SELECT uid, username, email, name, birthday, gender, relationship, about, website, hometown, city, country, occupations, companies, schools, hobbies, fav_media, music, books, staff
	FROM users WHERE uid = ? LIMIT 1');
$userQuery->execute([$_SESSION['uid']]);
$member = $userQuery->fetch();
if (!$member) {
	unset($_SESSION['uid'], $_SESSION['username']);
	header('Location: login.php');
	exit;
}

$unreadQuery = $pdo->prepare('SELECT COUNT(*) FROM messages WHERE receiver = ? AND isRead = 0');
$unreadQuery->execute([$member['uid']]);
$unreadMessages = (int)$unreadQuery->fetchColumn();
if (empty($_SESSION['csrf_token'])) {
	$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}



$fields = [
	'email', 'name', 'about', 'website', 'hometown', 'city', 'country',
	'occupations', 'companies', 'schools', 'hobbies', 'fav_media', 'music', 'books',
];
$values = [];
foreach ($fields as $field) {
	$values[$field] = (string)($member[$field] ?? '');
}
$gender = (string)$member['gender'];
$relationship = (string)$member['relationship'];
$birthdayMonth = $member['birthday'] ? date('n', strtotime($member['birthday'])) : '---';
$birthdayDay = $member['birthday'] ? date('j', strtotime($member['birthday'])) : '---';
$birthdayYear = $member['birthday'] ? date('Y', strtotime($member['birthday'])) : '---';
$errors = [];
$saved = isset($_GET['saved']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	foreach ($fields as $field) {
		$values[$field] = trim((string)($_POST[$field] ?? ''));
	}
	$gender = (string)($_POST['gender'] ?? '');
	$relationship = (string)($_POST['relationship'] ?? '');
	$birthdayMonth = (string)($_POST['birthday_mon'] ?? '---');
	$birthdayDay = (string)($_POST['birthday_day'] ?? '---');
	$birthdayYear = (string)($_POST['birthday_yr'] ?? '---');

	if (empty($_SESSION['csrf_token']) || !hash_equals((string)$_SESSION['csrf_token'], (string)($_POST['csrf_token'] ?? ''))) {
		$errors[] = 'Your form session expired. Please refresh the page and try again.';
	}
	if ($values['email'] === '' || !filter_var($values['email'], FILTER_VALIDATE_EMAIL) || strlen($values['email']) > 100) {
		$errors[] = 'Please enter a valid email address (100 characters or fewer).';
	}
	if ($values['website'] !== '') {
		$websiteParts = parse_url($values['website']);
		if (!filter_var($values['website'], FILTER_VALIDATE_URL) || !in_array(strtolower((string)($websiteParts['scheme'] ?? '')), ['http', 'https'], true)) {
			$errors[] = 'This URL does not look right. Enter a URL beginning with http:// or https://.';
		}
	}
	if ($values['country'] !== '' && !isset($countries[$values['country']])) {
		$errors[] = 'Please select a valid country.';
	}
	if (!in_array($gender, ['0', '1', '2', '3'], true)) {
		$errors[] = 'Please select a valid gender option.';
	}
	if (!in_array($relationship, ['0', '1', '2'], true)) {
		$errors[] = 'Please select a valid relationship status.';
	}
	foreach (['name', 'about', 'website', 'hometown', 'city', 'occupations', 'companies', 'schools', 'hobbies', 'fav_media', 'music', 'books'] as $field) {
		if (strlen($values[$field]) > 500) {
			$errors[] = 'Please shorten the information entered; each profile field is limited to 500 characters.';
			break;
		}
	}

	$birthday = null;
	if ([$birthdayMonth, $birthdayDay, $birthdayYear] !== ['---', '---', '---']) {
		if (!ctype_digit($birthdayMonth) || !ctype_digit($birthdayDay) || !ctype_digit($birthdayYear) || !checkdate((int)$birthdayMonth, (int)$birthdayDay, (int)$birthdayYear)) {
			$errors[] = 'Please enter a valid birthday or leave all birthday fields blank.';
		} else {
			$birthdayDate = DateTime::createFromFormat('!Y-n-j', $birthdayYear . '-' . $birthdayMonth . '-' . $birthdayDay);
			$dateErrors = DateTime::getLastErrors();
			if (!$birthdayDate || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))) {
				$errors[] = 'Please enter a valid birthday.';
			} else {
				$age = $birthdayDate->diff(new DateTime('today'));
				if ($birthdayDate > new DateTime('today') || $age->y < 13 || $age->y > 123) {
					$errors[] = 'Invalid age. You must be between 13 and 123 years old.';
				} else {
					$birthday = $birthdayDate->format('Y-m-d');
				}
			}
		}
	}

	if (!$errors) {
		$emailCheck = $pdo->prepare('SELECT uid FROM users WHERE email = ? AND uid <> ? LIMIT 1');
		$emailCheck->execute([$values['email'], $member['uid']]);
		if ($emailCheck->fetchColumn()) {
			$errors[] = 'That email address is already in use.';
		}
	}

	if (!$errors) {
		$update = $pdo->prepare('UPDATE users SET email = ?, name = ?, birthday = ?, gender = ?, relationship = ?, about = ?, website = ?, hometown = ?, city = ?, country = ?, occupations = ?, companies = ?, schools = ?, hobbies = ?, fav_media = ?, music = ?, books = ? WHERE uid = ?');
		try {
			$update->execute([
				$values['email'],
				$values['name'],
				$birthday,
				(int)$gender,
				(int)$relationship,
				$values['about'],
				$values['website'],
				$values['hometown'],
				$values['city'],
				$values['country'] !== '' ? $values['country'] : null,
				$values['occupations'],
				$values['companies'],
				$values['schools'],
				$values['hobbies'],
				$values['fav_media'],
				$values['music'],
				$values['books'],
				$member['uid'],
			]);
		} catch (PDOException $error) {
			if ($error->getCode() !== '23000') {
				throw $error;
			}
			$errors[] = 'That email address is already in use.';
		}
		if ($errors) {
			$saved = false;
		} else {
			header('Location: my_profile.php?saved=1');
			exit;
		}
	}
}

$currentUser = $member;
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html lang="en">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
	<title>NobregaTube - My Profile</title>
	<link rel="stylesheet" href="styles2005july.css">
</head>
<body>
	<?php $activeHeaderPage = 'profile'; include __DIR__ . '/includes/header.php'; ?>

	<div class="formTitle">My Profile</div>
	<?php if ($saved): ?><p class="success">Profile has been successfully updated.</p><?php endif; ?>
	<?php foreach ($errors as $error): ?>
		<table width="100%" align="center" bgcolor="#FF0000" cellpadding="6" cellspacing="3" border="0"><tbody><tr><td align="center" bgcolor="#FFFFFF"><span class="error"><?php echo h($error); ?></span></td></tr></tbody></table><br>
	<?php endforeach; ?>

	<div class="pageTable">
		<form method="post" action="my_profile.php">
			<input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
			<table width="100%" cellpadding="5" cellspacing="0" border="0">
				<tbody>
					<tr><td colspan="2"><div class="formTitle">Account Information</div><div class="tableSubTitleInfo">* Indicates required field.</div></td></tr>
					<tr><td width="200" align="right"><span class="label">User Name:</span></td><td><?php echo h($member['username']); ?></td></tr>
					<tr><td align="right"><span class="label">Email:</span></td><td><input type="email" size="35" maxlength="100" name="email" value="<?php echo h($values['email']); ?>" required> *</td></tr>

					<tr><td colspan="2"><div class="formTitle">Personal Information</div></td></tr>
					<tr><td align="right"><span class="label">Name:</span></td><td><input type="text" size="40" maxlength="500" name="name" value="<?php echo h($values['name']); ?>"></td></tr>
					<tr>
						<td align="right"><span class="label">Birthday:</span></td>
						<td>
							<select name="birthday_mon"><option value="---"<?php if ($birthdayMonth === '---') echo ' selected'; ?>>---</option><?php foreach ([1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Aug', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec'] as $number => $monthName): ?><option value="<?php echo $number; ?>"<?php if ((string)$number === $birthdayMonth) echo ' selected'; ?>><?php echo $monthName; ?></option><?php endforeach; ?></select>
							<select name="birthday_day"><option value="---"<?php if ($birthdayDay === '---') echo ' selected'; ?>>---</option><?php for ($dayOption = 1; $dayOption <= 31; $dayOption++): ?><option value="<?php echo $dayOption; ?>"<?php if ((string)$dayOption === $birthdayDay) echo ' selected'; ?>><?php echo $dayOption; ?></option><?php endfor; ?></select>
							<select name="birthday_yr"><option value="---"<?php if ($birthdayYear === '---') echo ' selected'; ?>>---</option><?php for ($yearOption = (int)date('Y') - 13; $yearOption >= (int)date('Y') - 123; $yearOption--): ?><option value="<?php echo $yearOption; ?>"<?php if ((string)$yearOption === $birthdayYear) echo ' selected'; ?>><?php echo $yearOption; ?></option><?php endfor; ?></select>
						</td>
					</tr>
					<tr><td align="right"><span class="label">Gender:</span></td><td><select name="gender"><option value="0"<?php if ($gender === '0') echo ' selected'; ?>>Prefer not to say</option><option value="1"<?php if ($gender === '1') echo ' selected'; ?>>Male</option><option value="2"<?php if ($gender === '2') echo ' selected'; ?>>Female</option><option value="3"<?php if ($gender === '3') echo ' selected'; ?>>Other</option></select></td></tr>
					<tr><td align="right"><span class="label">Relationship Status:</span></td><td><select name="relationship"><option value="0"<?php if ($relationship === '0') echo ' selected'; ?>>Prefer not to say</option><option value="1"<?php if ($relationship === '1') echo ' selected'; ?>>Single</option><option value="2"<?php if ($relationship === '2') echo ' selected'; ?>>Taken</option></select></td></tr>
					<tr><td align="right" valign="top"><span class="label">About Me:</span><br><span class="formFieldInfo">(Describe Yourself)</span></td><td><textarea maxlength="500" name="about" rows="5" cols="45"><?php echo h($values['about']); ?></textarea></td></tr>
					<tr><td align="right"><span class="label">Personal Website:</span></td><td><input type="url" size="50" maxlength="500" name="website" value="<?php echo h($values['website']); ?>"></td></tr>

					<tr><td colspan="2"><br><div class="formTitle">Location Information</div></td></tr>
					<tr><td align="right"><span class="label">Hometown:</span></td><td><input type="text" size="50" maxlength="500" name="hometown" value="<?php echo h($values['hometown']); ?>"></td></tr>
					<tr><td align="right"><span class="label">Current City:</span></td><td><input type="text" size="50" maxlength="500" name="city" value="<?php echo h($values['city']); ?>"></td></tr>
					<tr><td align="right"><span class="label">Current Country:</span></td><td><select name="country"><option value="">---</option><?php foreach ($countries as $code => $countryName): if ($code === null) continue; ?><option value="<?php echo h($code); ?>"<?php if ($values['country'] === $code) echo ' selected'; ?>><?php echo h($countryName); ?></option><?php endforeach; ?></select></td></tr>

					<tr><td colspan="2"><br><div class="formTitle">Random Information</div><div class="tableSubTitleInfo">Separate items with commas.</div></td></tr>
					<tr><td align="right"><span class="label">Occupations:</span></td><td><input maxlength="500" size="50" name="occupations" value="<?php echo h($values['occupations']); ?>"></td></tr>
					<tr><td align="right"><span class="label">Companies:</span></td><td><input maxlength="500" size="50" name="companies" value="<?php echo h($values['companies']); ?>"></td></tr>
					<tr><td align="right"><span class="label">Schools:</span></td><td><input maxlength="500" size="50" name="schools" value="<?php echo h($values['schools']); ?>"></td></tr>
					<tr><td align="right" valign="top"><span class="label">Interests &amp; Hobbies:</span></td><td><textarea maxlength="500" name="hobbies" rows="4" cols="45"><?php echo h($values['hobbies']); ?></textarea></td></tr>
					<tr><td align="right" valign="top"><span class="label">Favorite Movies &amp; Shows:</span></td><td><textarea maxlength="500" name="fav_media" rows="4" cols="45"><?php echo h($values['fav_media']); ?></textarea></td></tr>
					<tr><td align="right" valign="top"><span class="label">Favorite Music:</span></td><td><textarea maxlength="500" name="music" rows="4" cols="45"><?php echo h($values['music']); ?></textarea></td></tr>
					<tr><td align="right" valign="top"><span class="label">Favorite Books:</span></td><td><textarea maxlength="500" name="books" rows="4" cols="45"><?php echo h($values['books']); ?></textarea></td></tr>
					<tr><td></td><td><input type="submit" id="save" name="save" value="Update Profile"></td></tr>
				</tbody>
			</table>
		</form>
	</div>

	<br>
	<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
