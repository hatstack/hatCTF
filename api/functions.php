<?php
	function login($username, $password) {
		global $db;

		// Returns status code 0 for success.
		// A message to display otherwise.

		$username = $db->escape_string($username);

		$result = $db->query("SELECT id, permissions, username, points, password
		            FROM users
		            WHERE username = '$username'")
			or die('Database error 682693');

		if ($result->num_rows == 0) {
			return 'Unknown username';
		}

		list($id, $permissions, $username, $points, $hash) = $result->fetch_row();

		if (!password_verify($password, $hash)) {
			return 'Password does not match.';
		}

		$_SESSION['loggedin'] = true;
		$_SESSION['userid'] = intval($id);
		$_SESSION['permissions'] = intval($permissions);
		$_SESSION['username'] = $username;
		$_SESSION['points'] = intval($points);
		$_SESSION['solves'] = [];

		if ($points > 0) {
			updateSolves();
		}

		return 0;
	}

	function register($username, $password) {
		global $db;

		if (empty($username) || empty($password)) {
			return 'Cannot use an empty username or password.';
		}

		// Keep the original username in $username so we can pass it to login()
		$_username = $db->escape_string($username);

		$result = $db->query("SELECT id FROM users WHERE username = '$_username'")
			or die('Database error 49581');

		if ($result->num_rows != 0) {
			if (login($username, $password) === 0) {
				return 0;
			}
			return 'Username in use.';
		}

		$_password = password_hash($password, PASSWORD_BCRYPT, ["cost" => 11]);
		$db->query("INSERT INTO users (username, password) VALUES('$_username', '$_password')")
			or die('Database error 651876');

		return login($username, $password);
	}

	function updateSolves() {
		global $db;

		if (!$_SESSION['loggedin']) {
			// If we are not logged in, we can hardly update solves.
			return;
		}

		$result = $db->query("SELECT c.id, c.points
			FROM solves s
			INNER JOIN challenges c ON s.challenge = c.id
			WHERE s.userid = $_SESSION[userid]
				AND c.enabled = 1
			") or die('Database error 8951');

		$_SESSION['solves'] = [];
		$points = 0; // might as well re-count points while we're at it
		while ($row = $result->fetch_row()) {
			$_SESSION['solves'][] = $row[0];
			$points += $row[1];
		}

		if ($points != $_SESSION['points']) {
			$_SESSION['points'] = $points;
			$db->query("UPDATE users SET points = $points WHERE id = $_SESSION[userid]")
				or die('Database error 6196791');
		}
	}

	function getChallenges() {
		global $db;

		$challenges = [];

		$result = $db->query('SELECT id, points, title, description
			FROM challenges
			WHERE enabled = 1
			ORDER BY points')
			or die('Database error 158158');

		while ($row = $result->fetch_assoc()) {
			$challenges[] = $row;
		}

		return $challenges;
	}

	function getLeaderdata() {
		global $db;

		$leaders = [];

		$result = $db->query("SELECT id, username, points
			FROM users
			WHERE points > 0
			ORDER BY points DESC, lastupdate
			") or die('Database error 424238');

		$position = 0;
		while ($row = $result->fetch_assoc()) {
			$position++;

			$row['position'] = $position;
			$leaders[] = $row;
		}

		$data = ['leaders' => $leaders, 'hash' => md5(json_encode($leaders))];

		return $data;
	}

	function getLeaders($longpolling) {
		if ($longpolling) {
			$i = 0;
			while ($i < 90) {
				$data = getLeaderdata();
				if ($data['hash'] != $longpolling) {
					return $data;
				}
				usleep(300 * 1000);
				$i++;
			}
		}

		return getLeaderdata();
	}

	function submitFlag($flag) {
		global $db;

		if ($_SESSION['loggedin'] !== true) {
			return 'You must log in to submit a flag.';
		}

		// Check if the flag is correct
		$flag = $db->escape_string(trim($flag));
		$result = $db->query("SELECT id, points
			FROM challenges
			WHERE flag = '$flag'
				AND enabled = 1
			") or die('Database error 9527672');

		if ($result->num_rows == 0) {
			$challenge = false;
		}
		else {
			$result = $result->fetch_row();
			$challenge = $result[0];
			$points = $result[1];
		}

		// Check the recent number of attempts by this user
		$result = $db->query("SELECT COUNT(*)
			FROM flagattempts
			WHERE userid = $_SESSION[userid]
				AND timestamp > " . (time() - 3600 * 24))
			or die('Database error 14242');
		$attempts = $result->fetch_row()[0];

		// Flag not found, log it
		if ($challenge === false) {
			$db->query("INSERT INTO flagattempts (userid, flag, fromIP, timestamp)
				VALUES($_SESSION[userid],
					'$flag',
					'$_SERVER[REMOTE_ADDR]',
					" . microtime(true) . "
				)") or die('Database error 17');
		}

		// We fetched $attempts earlier, now check it.
		// Don't check it earlier because we want to log all attempts.
		if ($attempts > 5000) {
			return 'You submitted an awful lot of incorrect flags recently. Try again later.';
		}

		if ($challenge === false) {
			return 'Flag not found.';
		}

		$result = $db->query("SELECT id FROM solves
			WHERE challenge = $challenge AND userid = $_SESSION[userid]")
			or die('Database error 525284');

		if ($result->num_rows > 0) {
			return 'You already solved this challenge.';
		}

		// Log the solve :)
		$db->query("INSERT INTO solves (challenge, userid, fromIP, flag, timestamp) VALUES(
				$challenge,
				$_SESSION[userid],
				'$_SERVER[REMOTE_ADDR]',
				'$flag',
				" . microtime(true) . "
			)") or die('Database error 1418592');

		$db->query("UPDATE users
			SET points = points + $points, lastupdate = " . microtime(true) . "
			WHERE id = $_SESSION[userid]")
			or die('Database error 29738');

		return 0;
	}

