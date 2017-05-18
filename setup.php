<pre>
<?php
	session_start();

	if ($_SESSION['loggedin'] !== true) {
		die('Not logged in. Please register or log in on <a href="./">the CTF</a>.');
	}

	require('config.php');

	$db = new mysqli($db_host, $db_user, $db_pass, $db_name);
	if ($db->connect_error) {
		die('Database connection error.');
	}

	$result = $db->query('SELECT COUNT(*) FROM users WHERE permissions = 2')
		or die('Database error 6443');
	if ($result->num_rows != 1 || $result->fetch_row()[0] != 0) {
		?>
An admin is already configured, so you cannot use setup.php anymore.
Log in to <a href="./admin">the admin panel</a> to setup more admins, or edit the
database manually if you lost the password.
		<?php
		exit;
	}

	if (isset($_POST['dbpass'])) {
		// Constant time comparisons
		$passmatch = md5($_POST['dbpass'] . $db_pass) == md5($db_pass . $db_pass);
		$usermatch = md5($_POST['dbuser'] . $db_user) == md5($db_user . $db_user);

		if (!$passmatch || !$usermatch) {
			die('Invalid credentials.');
		}

		$db->query("UPDATE users SET permissions = 2 WHERE id = $_SESSION[userid]")
			or die('Database error 27006');
		
		die("User " . htmlspecialchars($_SESSION['username'])
			. " (id $_SESSION[userid]) is now an admin. <a href='./admin'>"
			. "Click here</a> for the admin panel.");
	}
?>
<form method=POST>
Enter the database username and password to make your user (username: <?php echo htmlspecialchars($_SESSION['username']); ?>) an admin.
Database username: <input name=dbuser>
Database password: <input type=password name=dbpass>
<input type=submit value="Make me an admin">
</form>
</pre>

