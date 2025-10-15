<?php
include 'auth.php';
// Check logged-in
check_loggedin($con);
// output message (errors, etc)
$msg = '';
// Retrieve additional account info from the database because we don't have them stored in sessions
$stmt = $con->prepare('SELECT password, email, activation_code, role, registered FROM accounts WHERE id = ?');
// In this case, we can use the account ID to retrieve the account info.
$stmt->bind_param('i', $_SESSION['id']);
$stmt->execute();
$stmt->bind_result($password, $email, $activation_code, $role, $registered_date);
$stmt->fetch();
$stmt->close();
// Handle edit profile post data
if (isset($_POST['username'], $_POST['password'], $_POST['cpassword'], $_POST['email'])) {
	// Make sure the submitted registration values are not empty.
	if (empty($_POST['username']) || empty($_POST['email'])) {
		$msg = 'The input fields must not be empty!';
	} else if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
		$msg = 'Please provide a valid email address!';
	} else if (!preg_match('/^[a-zA-Z0-9]+$/', $_POST['username'])) {
	    $msg = 'Username must contain only letters and numbers!';
	} else if (!empty($_POST['password']) && (strlen($_POST['password']) > 20 || strlen($_POST['password']) < 5)) {
		$msg = 'Password must be between 5 and 20 characters long!';
	} else if ($_POST['cpassword'] != $_POST['password']) {
		$msg = 'Passwords do not match!';
	}
	// No validation errors... Process update
	if (empty($msg)) {
		// Check if new username or email already exists in the database
		$stmt = $con->prepare('SELECT * FROM accounts WHERE (username = ? OR email = ?) AND username != ? AND email != ?');
		$stmt->bind_param('ssss', $_POST['username'], $_POST['email'], $_SESSION['name'], $email);
		$stmt->execute();
		$stmt->store_result();
		// Account exists? Output error...
		if ($stmt->num_rows > 0) {
			$msg = 'Account already exists with that username and/or email!';
		} else {
			// No errors occured, update the account...
			$stmt->close();
			// If email has changed, generate a new activation code
			$uniqid = account_activation && $email != $_POST['email'] ? uniqid() : $activation_code;
			$stmt = $con->prepare('UPDATE accounts SET username = ?, password = ?, email = ?, activation_code = ? WHERE id = ?');
			// We do not want to expose passwords in our database, so hash the password and use password_verify when a user logs in.
			$password = !empty($_POST['password']) ? password_hash($_POST['password'], PASSWORD_DEFAULT) : $password;
			$stmt->bind_param('ssssi', $_POST['username'], $password, $_POST['email'], $uniqid, $_SESSION['id']);
			$stmt->execute();
			$stmt->close();
			// Update the session variables
			$_SESSION['name'] = $_POST['username'];
			if (account_activation && $email != $_POST['email']) {
				// Account activation required, send the user the activation email with the "send_activation_email" function from the "main.php" file
				send_activation_email($_POST['email'], $uniqid);
				// Logout the user
				unset($_SESSION['loggedin']);
				$msg = 'You have changed your email address! You need to re-activate your account!';
			} else {
				// Profile updated successfully, redirect the user back to the profile page
				header('Location: profile.php');
				exit;
			}
		}
	}
}
?>
<!DOCTYPE html>
<html>
	<head>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width,minimum-scale=1">
		<title>NotesAO - Profile</title>
		<!-- FAVICON LINKS (from index.html) -->
		<link rel="icon" type="image/x-icon" href="/favicons/favicon.ico">
		<link rel="icon" type="image/png" sizes="32x32" href="/favicons/favicon-32x32.png">
		<link rel="icon" type="image/png" sizes="16x16" href="/favicons/favicon-16x16.png">
		<link rel="icon" type="image/png" sizes="96x96" href="/favicons/favicon-96x96.png">
		<link rel="icon" type="image/svg+xml" href="/favicons/favicon.svg">

		<link rel="mask-icon" href="/favicons/safari-pinned-tab.svg" color="#211c56">

		<link rel="apple-touch-icon" sizes="180x180" href="/favicons/apple-touch-icon.png">
		<link rel="apple-touch-icon" sizes="167x167" href="/favicons/apple-touch-icon-ipad-pro.png">
		<link rel="apple-touch-icon" sizes="152x152" href="/favicons/apple-touch-icon-ipad.png">
		<link rel="apple-touch-icon" sizes="120x120" href="/favicons/apple-touch-icon-120x120.png">

		<link rel="manifest" href="/favicons/site.webmanifest">
		<meta name="apple-mobile-web-app-title" content="NotesAO">
		<link href="style.css" rel="stylesheet" type="text/css">
		<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css" integrity="sha512-xh6O/CkQoPOWDdYTDqeRdPCVd1SpvCA9XXcUnZS2FmJNp1coAFzvtCN9BmamE+4aHK8yyUHUSCcJHgXloTyT2A==" crossorigin="anonymous" referrerpolicy="no-referrer">
	</head>
	<body class="loggedin">

		<header class="header">

			<div class="wrapper">

				<h1>Website Title</h1>

				<input type="checkbox" id="menu">
				<label for="menu">
					<i class="fa-solid fa-bars"></i>
				</label>

				<nav class="menu">
					<a href="home.php"><i class="fas fa-home"></i>Home</a>
					<a href="profile.php"><i class="fas fa-user-circle"></i>Profile</a>
					<?php if ($_SESSION['role'] == 'Admin'): ?>
					<a href="admin/index.php" target="_blank"><i class="fas fa-user-cog"></i>Admin</a>
					<?php endif; ?>
					<a href="logout.php"><i class="fas fa-sign-out-alt"></i>Logout</a>
				</nav>
				
			</div>

		</header>

		<?php if (!isset($_GET['action'])): ?>
		<div class="content profile">

			<h2>Profile Page</h2>

			<div class="block">

				<p>Your account details are below.</p>

				<div class="profile-detail">
					<strong>Username</strong>
					<?=$_SESSION['name']?>
				</div>

				<div class="profile-detail">
					<strong>Email</strong>
					<?=$email?>
				</div>

				<div class="profile-detail">
					<strong>Role</strong>
					<?=$role?>
				</div>

				<div class="profile-detail">
					<strong>Registered</strong>
					<?=date('Y-m-d H:ia', strtotime($registered_date))?>
				</div>

				<a class="profile-btn" href="profile.php?action=edit">Edit Details</a>

			</div>

		</div>
		<?php elseif ($_GET['action'] == 'edit'): ?>
		<div class="content profile">

			<h2>Edit Profile Page</h2>
			
			<div class="block">

				<form action="profile.php?action=edit" method="post">

					<label for="username">Username</label>
					<input type="text" value="<?=$_SESSION['name']?>" name="username" id="username" placeholder="Username">

					<label for="password">New Password</label>
					<input type="password" name="password" id="password" placeholder="New Password">

					<label for="cpassword">Confirm Password</label>
					<input type="password" name="cpassword" id="cpassword" placeholder="Confirm Password">

					<label for="email">Email</label>
					<input type="email" value="<?=$email?>" name="email" id="email" placeholder="Email">

					<div>
						<input class="profile-btn" type="submit" value="Save">
					</div>

					<p><?=$msg?></p>

				</form>

			</div>

		</div>
		<?php endif; ?>

	</body>
</html>