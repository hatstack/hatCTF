<?php
	$db = new mysqli($db_host, $db_user, $db_pass, $db_name);
	if ($db->connect_error) {
		die('Database connection error.');
	}

	// Clear these variables as defense in depth
	$db_pass = '';
	$db_user = '';
	$db_host = '';

	$lifetime = 3600 * 24 * 14; // two weeks should be plenty for a CTF
	$path = '/'; // might want to tighten this if we run on a subpath
	$domain = ''; // emptystring seems to be the way to skip the parameter
	$secure = isset($_SERVER['HTTPS']) && !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off';
	$httponly = true;
	session_set_cookie_params($lifetime, $path, $domain, $secure, $httponly);

	session_start();

	if (!isset($_SESSION['username'])) {
		$_SESSION['loggedin'] = false;
		$_SESSION['userid'] = -1;
		$_SESSION['csrf'] = hash('sha256', openssl_random_pseudo_bytes(16));
		$_SESSION['username'] = 'Guest';
		$_SESSION['permissions'] = -1;
		$_SESSION['points'] = 0;
		$_SESSION['solves'] = [];
	}

