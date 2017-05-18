<?php
	if (isset($_GET['logout'])) {
		session_destroy();
		unset($_SESSION);
		exit;
	}

	if (isset($_GET['login'])) {
		$result = login($_POST['username'], $_POST['password']);
		if ($result !== 0) {
			die(json_encode(['message' => $result]));
		}
	}

	else if (isset($_GET['register'])) {
		$result = register($_POST['username'], $_POST['password']);
		if ($result !== 0) {
			die(json_encode(['message' => $result]));
		}
	}

	if (isset($_GET['getUser']) || isset($_GET['login']) || isset($_GET['register'])) {
		updateSolves();

		die(json_encode([
			'userid' => $_SESSION['userid'],
			'csrf' => $_SESSION['csrf'],
			'username' => $_SESSION['username'],
			'permissions' => $_SESSION['permissions'],
			'points' => $_SESSION['points'],
			'solves' => $_SESSION['solves'],
		]));
	}

	if (isset($_GET['getChallenges'])) {
		die(json_encode(getChallenges()));
	}

	if (isset($_GET['getLeaders'])) {
		if (isset($_GET['longpolling']) && $_SESSION['permissions'] != 2) {
			die('Only admins may do long polling to prevent the connection pool from being flooded.');
		}
		$longpolling = $_GET['longpolling'] ? $_GET['longpolling'] : false;
		die(json_encode(getLeaders($longpolling)));
	}

	if (isset($_GET['flag'])) {
		$result = submitFlag($_POST['flag']);
		if ($result === 0) {
			die('{}');
		}
		die(json_encode(['message' => $result]));
	}

	header('HTTP/1.1 400 Bad Request');

