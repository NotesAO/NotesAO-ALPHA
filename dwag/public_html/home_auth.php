<?php
include 'auth.php';
check_loggedin($con);
?>
<!DOCTYPE html>
<html>
	<head>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width,minimum-scale=1">
		<title>NotesAO - Home Page</title>
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

		<div class="content">

			<h2>Home Page</h2>

			<p class="block">Welcome back, <?=$_SESSION['name']?>!</p>

		</div>

	</body>
</html>