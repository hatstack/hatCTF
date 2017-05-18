<?php
	session_start();
	if ($_SESSION['loggedin'] !== true) {
		die('Not logged in. Please login on <a href="..">the ctf</a>');
	}

	if ($_SESSION['permissions'] !== 2) {
		die('Insufficient permissions. Log in with an admin account on <a href="..">the ctf</a>.');
	}

	require('../config.php');

	$db = new mysqli($db_host, $db_user, $db_pass, $db_name);
	if ($db->connect_error) {
		die('Database connection error.');
	}

	function dang() {
		if ($_SESSION['dangerousmode']) {
			return 'class=dangerous ';
		}
		return 'class=notdangerous ';
	}

	function csrf() {
		echo "<input type=hidden name=csrf value=$_SESSION[csrf]>";
	}

	function recomputePoints() {
		global $db;

		$db->query("UPDATE users u
			SET points = (
				SELECT SUM(points)
				FROM challenges c
				INNER JOIN solves s ON s.challenge = c.id
				WHERE s.userid = u.id
					AND c.enabled = 1
			)
			") or die('Database error 3837');
	}

	if (isset($_POST['action'])) {
		if (md5($_POST['csrf'] . $_SESSION['csrf']) != md5($_SESSION['csrf'] . $_SESSION['csrf'])) {
			die('Invalid CSRF token.');
		}

		if ($_POST['action'] == 'Toggle') {
			$_SESSION['dangerousmode'] = !$_SESSION['dangerousmode'];
		}

		if ($_POST['action'] == 'Delete all users') {
			if (!$_SESSION['dangerousmode']) {
				die('Dangerous mode is off.');
			}
			$db->query('DELETE FROM users WHERE permissions = 0') or die('Database error 1749582');
		}

		if ($_POST['action'] == 'addchall') {
			$title = $db->escape_string($_POST['title']);
			$description = $db->escape_string($_POST['description']);
			$flag = $db->escape_string($_POST['flag']);
			$points = intval($_POST['points']);

			$result = $db->query("SELECT COUNT(*) FROM challenges WHERE flag = '$flag'") or die('Database error 5921');
			if ($result->fetch_row()[0] > 0) {
				die('Flag already in use.');
			}

			$db->query("INSERT INTO challenges (title, description, byuser, flag, points) VALUES(
					'$title',
					'$description',
					$_SESSION[userid],
					'$flag',
					$points
				)") or die('Database error 424239');
		}

		if ($_POST['action'] == 'disallchall') {
			// Does not require dangerous mode because while it has a big impact, it's not permanent
			$db->query("UPDATE challenges SET enabled = 0") or die('Database error 23755');
			// Should we recomputePoints? I think not.
		}

		if ($_POST['action'] == 'editchall') {
			$challid = intval($_POST['challid']);

			if ($_POST['challaction'] == 'Delete') {
				if (!$_SESSION['dangerousmode']) {
					die('Dangerous mode is off.');
				}
				/* disabled because uploads are todo
				$result = $db->query("SELECT folder FROM challenges WHERE id = $challid") or die('Database error 7099');
				if ($result->num_rows > 0) {
					$folder = $result->fetch_row()[0];
					if (!empty($folder) && strpos($folder, '.') === false && strpos($folder, '/') === false && is_dir('../challenges/' . $folder)) {
						`rm -r ../challenges/$folder`;
					}
				}*/
				$db->query("DELETE FROM challenges WHERE id = $challid") or die('Database error 23475');
			}

			if ($_POST['challaction'] == 'Enable') {
				$db->query("UPDATE challenges SET enabled = 1 WHERE id = $challid") or die('Database error 18721');
				recomputePoints();
			}

			if ($_POST['challaction'] == 'Disable') {
				$db->query("UPDATE challenges SET enabled = 0 WHERE id = $challid") or die('Database error 18863');
				recomputePoints();
			}

			if ($_POST['challaction'] == 'Save') {
				$title = $db->escape_string($_POST['title']);
				$description = $db->escape_string($_POST['description']);
				if (isset($_POST['flag'])) {
					$flag = $db->escape_string($_POST['flag']);
				}
				$points = intval($_POST['points']);

				$db->query("UPDATE challenges SET
						title = '$title',
						description = '$description',
						" . (isset($_POST['flag']) ? "flag = '$flag'," : '') . "
						points = $points
					WHERE id = $challid") or die('Database error 11473');

				$result = $db->query("SELECT COUNT(*) FROM challenges WHERE flag = '$flag'") or die('Database error 18872');
				if ($result->fetch_row()[0] > 1) {
					echo '<span style="color:red;font-size:16pt;">Warning:</span> '
						. 'duplicate flag in the challenge you just edited.';
				}

			}
		}

		if ($_POST['action'] == 'edituser') {
			$userid = intval($_POST['userid']);
			if ($_POST['useraction'] == 'Demote') {
				$db->query("UPDATE users SET permissions = permissions - 1 WHERE id = $userid") or die('Database error 14308');
				$db->query("UPDATE users SET permissions = 0 WHERE id = $userid AND permissions < 0") or die('Database error 13272');
			}

			if ($_POST['useraction'] == 'Promote') {
				$db->query("UPDATE users SET permissions = permissions + 1 WHERE id = $userid") or die('Database error 20363');
				$db->query("UPDATE users SET permissions = 2 WHERE id = $userid AND permissions > 2") or die('Database error 20241');
			}

			if ($_POST['useraction'] == 'Delete') {
				if (!$_SESSION['dangerousmode']) {
					die('Dangerous mode is off.');
				}
				$db->query("DELETE FROM users WHERE id = $userid") or die('Database error 825954');
			}

			if ($_POST['useraction'] == 'Delete submissions') {
				if (!$_SESSION['dangerousmode']) {
					die('Dangerous mode is off.');
				}
				$db->query("DELETE FROM solves WHERE userid = $userid") or die('Database error 25955');
				$db->query("UPDATE users SET points = 0 WHERE id = $userid") or die('Database error 7379');
			}

			if ($_POST['useraction'] == 'Show attempt solves') {
				$result = $db->query("SELECT flag, timestamp, fromip
					FROM flagattempts
					WHERE userid = $userid
					ORDER BY timestamp") or die('Database error 2452');
				echo '<span style="color:#00f">';
				if ($result->num_rows == 0) {
					echo "No attempts for this user.";
				}
				else {
					echo '<table border=1><tr><th>flag</th><th>when</th><th>ip</th></tr>';
					while ($row = $result->fetch_row()) {
						$ts = date('Y-m-d H:i:s', $row[1]);
						echo '<tr><td>' . htmlspecialchars($row[0]) . "</td><td>$row[1]</td><td>$ts</td><td>$row[2]</td></tr>";
					}
					echo '</table>';
				}
				echo '</span>';
			}

			if ($_POST['useraction'] == 'Show solves') {
				$result = $db->query("SELECT c.title, c.points, s.timestamp, s.fromIP
					FROM solves s
					INNER JOIN challenges c ON s.challenge = c.id
					WHERE s.userid = $userid
					ORDER BY timestamp") or die('Database error 512');
				echo '<span style="color:#00f">';
				if ($result->num_rows == 0) {
					echo "No solves for this user.";
				}
				else {
					echo '<table border=1><tr><th>challenge</th><th>pts</th><th>when</th><th>ip</th></tr>';
					while ($row = $result->fetch_row()) {
						$ts = date('Y-m-d H:i:s', $row[2]);
						echo '<tr><td>' . htmlspecialchars($row[0]) . "</td><td>$row[1]</td><td>$ts</td><td>$row[3]</td></tr>";
					}
					echo '</table>';
				}
				echo '</span>';
			}
		}
	}

?>
<style>
	input {
		margin-top: 3px;
		margin-bottom: 3px;
	}
	input, textarea, fieldset {
		border: 1px solid #aaa;
	}
	body, input, textarea {
		color: #eee;
	}
	/*a, td, input:not([type]), input[type="text"], input[type="number"], textarea, .username {*/
	a, .username {
		color: #88f;
	}
	body, input, textarea {
		background-color: #333;
		font-family: Arial;
		font-size: 11pt;
	}
	strong {
		display: block;
		margin-top: 20px;
	}
	td {
		padding: 5px;
	}
	table {
		border-collapse: collapse;
	}
	form {
		margin: 0;
	}
	.dangerous {
		color: red;
	}
	.notdangerous {
		color: #aaa;
	}
</style>
<h1 style='font-size: 1.1em; font-weight: bold; margin-bottom: 0px;'>Hatstack CTF admin panel</h1>
<a href='..'>Return to CTF</a><br>

<form method=post>
<strong>Dangerous mode</strong>
<?php csrf(); echo $_SESSION['dangerousmode'] ? '<span class=dangerous>on</span>' : 'off'; ?> <input type=submit name=action value=Toggle><br>
<?php if (!$_SESSION['dangerousmode']) {
	echo 'Enable to do stuff like delete all users.';
}
else { 
	echo '<span class=dangerous>have fun</span>';
} ?>
</form><br>

<form method=post style="width: 630px;">
	<fieldset>
		<legend>Add a challenge</legend>
		<input type=hidden name=action value=addchall>
		<?php csrf(); ?>

		Title: <input name=title> | points: <input type=number name=points style='width:80px;'><br>
		flag: <input name=flag size=30> | enabled: no<br>
		description:<br>
		<textarea name=description rows=5 cols=60></textarea><br>
		<br>
		<input type=submit value=save><br>
		You can upload files and enable the challenge after saving.
	</fieldset>
</form>

<strong>Challenges</strong>
<?php
	$result = $db->query("SELECT id, title, description, byuser, points, flag, enabled FROM challenges")
		or die('Database error 29788');
	if ($result->num_rows == 0) {
		echo 'There are no challenges. What a boring CTF.';
	}
	else {
		echo "Showing " . $result->num_rows . " challenges.<br>";
		echo '<form method=post><input type=hidden name=action value=disallchall>';
		csrf();
		echo '<input type=submit value="Disable all challenges"></form>';
		while ($row = $result->fetch_row()) {
			list($id, $title, $description, $byuser, $points, $flag, $enabled) = $row;

			$id = intval($id);
			$byuser = intval($byuser);
			$title = htmlspecialchars($title);
			$flag = htmlspecialchars($flag);
			$description = htmlspecialchars($description);
			$isenabled = $enabled == 1 ? 'yes' : 'no';

			$showflag = 'Hidden (enable dangerous mode).';
			if ($_SESSION['dangerousmode']) {
				$showflag = "<input size=30 name=flag value='$flag'>";
			}

			echo '<form method=post style="width: 630px; display: inline-block; margin: 5px;">';
			echo "<fieldset><legend>Edit challenge $id</legend>";
			csrf();
			echo ' <input type=hidden name=action value=editchall>';
			echo " <input type=hidden name=challid value=$id>";

			echo " byuser: $byuser | title: <input name=title value='$title'> | enabled: $isenabled<br>";
			echo " flag: $showflag | points: <input style='width:80px;' type=number name=points value=$points><br>";
			echo " description: <textarea name=description cols=60 rows=5>$description</textarea><br>";
			echo ' challenge files: todo.<br>';

			echo ' <input type=submit name=challaction value=Save>';
			if ($enabled == 1) {
				echo ' <input type=submit name=challaction value=Disable>';
			}
			else {
				echo ' <input type=submit name=challaction value=Enable>';
			}
			echo ' <input type=submit name=challaction ' . dang() . 'value=Delete>';
			echo '</fieldset></form>';
		}
	}
?><br><br>

<strong>User administration</strong>
<?php
	$result = $db->query("SELECT id, username, points, permissions FROM users")
		or die('Database error 481488');

	echo "<form method=post>Showing " . $result->num_rows . " users.";
	if ($result->num_rows > 0) {
		csrf();
		echo ' <input type=submit name=action value="Delete all users" ' . dang() . '> (not admins or uploaders)';
	}
	echo '</form>';
	while ($row = $result->fetch_row()) {
		if ($row[3] == 0) {
			$level = 'user';
		}
		else if ($row[3] == 1) {
			$level = 'uploader';
		}
		else if ($row[3] == 2) {
			$level = 'admin';
		}
		else {
			$level = 'unknown';
		}
		echo "<form method=post>
			<input type=hidden name=userid value=$row[0]>
			<input type=hidden name=action value=edituser>
			#$row[0] <span class=username>" . htmlspecialchars($row[1]) . "</span>: $row[2]pts, $level ";
		if ($row[3] > 0) {
			echo '<input type=submit name=useraction value=Demote> ';
		}
		echo '<input type=submit name=useraction value=Promote> ';
		echo '<input type=submit name=useraction ' . dang() . 'value=Delete> ';
		echo '<input type=submit name=useraction value="Show solves"> ';
		echo '<input type=submit name=useraction value="Show attempt solves"> ';
		echo '<input type=submit name=useraction ' . dang() . 'value="Delete submissions"> ';
		csrf();
		echo '</form>';
	}
?><br>

<strong>Solves per challenge</strong>
<?php
	$result = $db->query("SELECT c.title, u.username
			FROM solves s
			INNER JOIN challenges c ON s.challenge = c.id
			INNER JOIN users u ON s.userid = u.id
		") or die('Database error 11545');

	if ($result->num_rows == 0) {
		echo 'There are no solves yet.';
	}
	else {
		$solves = [];
		while ($row = $result->fetch_row()) {
			if (!isset($solves[$row[0]])) {
				$solves[$row[0]] = [];
			}
			$solves[$row[0]][] = $row[1];
		}

		echo '<table border=1><tr><th>challenge</th><th>solves</th><th>users</th></tr>';
		foreach ($solves as $chal=>$users) {
			echo '<tr><td>' . htmlspecialchars($chal) . '</td><td>' . count($users) . '</td><td>' . implode(', ', $users) . '</td></tr>';
		}
		echo '</table>';
	}
?>
<br><br>

<strong>150 most recent correct flag submissions</strong>
<?php
	$result = $db->query("SELECT flag, timestamp, fromip
		FROM solves
		ORDER BY timestamp
		LIMIT 150") or die('Database error 12553');
	if ($result->num_rows == 0) {
		echo "No submissions.<br><br>";
	}
	else {
		echo '<table border=1><tr><th>flag</th><th>when</th><th>ip</th></tr>';
		while ($row = $result->fetch_row()) {
			$ts = date('Y-m-d H:i:s', $row[1]);
			echo '<tr><td>' . htmlspecialchars($row[0]) . "</td><td>$ts</td><td>$row[2]</td></tr>";
		}
		echo '</table><br><br>';
	}
?>

<strong>250 most recent failed flag attempts</strong>
<?php
	$result = $db->query("SELECT flag, timestamp, fromip
		FROM flagattempts
		ORDER BY timestamp
		LIMIT 250") or die('Database error 29212');
	if ($result->num_rows == 0) {
		echo "No attempts.<br><br>";
	}
	else {
		echo '<table border=1><tr><th>flag</th><th>when</th><th>ip</th></tr>';
		while ($row = $result->fetch_row()) {
			$ts = date('Y-m-d H:i:s', $row[1]);
			echo '<tr><td>' . htmlspecialchars($row[0]) . "</td><td>$ts</td><td>$row[2]</td></tr>";
		}
		echo '</table><br><br>';
	}

