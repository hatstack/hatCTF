MENU_TYPE_BUTTON = 0;
MENU_TYPE_PAGE = 1;

PERMS_GUEST = -1;
PERMS_USER = 0;
PERMS_UPLOADER = 1;
PERMS_ADMIN = 2;

PAGE_CHALLENGES = 0;
PAGE_LEADERBOARD = 1;
PAGE_UPLOAD = 2;

function addMenuItem(type, callback, text) {
	var a = document.createElement('a');

	if (type == MENU_TYPE_BUTTON) {
		a.classList.add('button');
	}

	a.innerText = text;
	a.onclick = callback;

	$("header nav").appendChild(a);
}

function clearMenu() {
	$("header nav").innerHTML = '';
}

function updateUser(apiData) {
	apiData = JSON.parse(apiData);
	if (apiData.message) {
		swal({
			type: 'warning',
			title: apiData.message,
		});
		return;
	}

	if (registering) {
		swal({
			type: 'success',
			title: 'Welcome!',
		});
		registering = false;
	}

	if (loggingin) {
		swal({
			type: 'success',
			title: 'Welcome back!',
		});
		loggingin = false;
	}

	user = apiData;

	renderUser();
	renderChallenges();
	renderLeaders();
}

function renderUser() {
	if (user.permissions == PERMS_GUEST) {
		$("header .username").innerText = 'Guest';
		$("header .points").innerText = '0';
	}
	else {
		$("header .username").innerText = user.username;
		$("header .points").innerText = user.points;
	}
	renderMenu();
}

function logOut() {
	GET('api/?logout', null, alert);

	user = {
		userid: -1,
		permissions: PERMS_GUEST,
		points: 0,
		username: 'Guest',
		solves: [],
	};

	renderChallenges();
	renderLeaders();
	renderUser();
}

function showLeaderboardPage() {
	page = PAGE_LEADERBOARD;

	renderLeaders();
	renderChallenges();
	renderMenu();
}

function showChallengesPage() {
	page = PAGE_CHALLENGES;

	renderLeaders();
	renderChallenges();
	renderMenu();
}

function showUploadPage() {
	page = PAGE_UPLOAD;
}

function showAdminPage() {
	location = "./admin";
}

function showLoginPage() {
	swal({
		title: "Log in",
		text: "Username:",
		type: "input",
		showCancelButton: true,
		closeOnConfirm: false,
	}, function (username) {
		if (!username) {
			return;
		}
		swal({
			title: "Log in",
			text: "Password:",
			type: "input",
			inputType: "password",
			showCancelButton: true,
			closeOnConfirm: false,
		}, function (password) {
			if (!password) {
				return;
			}
			loggingin = true;
			POST('api/?login', {
				username: username,
				password: password,
			}, updateUser);
		});
	});
}

function showRegisterPage() {
	swal({
		title: "Register",
		text: "Enter a username:",
		type: "input",
		showCancelButton: true,
		closeOnConfirm: false,
	}, function (username) {
		if (!username) {
			return;
		}
		swal({
			title: "Register",
			text: "Choose a password:",
			type: "input",
			inputType: "password",
			showCancelButton: true,
			closeOnConfirm: false,
		}, function (password) {
			if (!password) {
				return;
			}
			registering = true;
			POST('api/?register', {
				username: username,
				password: password,
			}, updateUser);
		});
	});
}

function renderMenu() {
	clearMenu();

	if (page != PAGE_LEADERBOARD) {
		addMenuItem(MENU_TYPE_PAGE, showLeaderboardPage, 'Leaderboard');
	}

	if (page != PAGE_CHALLENGES) {
		addMenuItem(MENU_TYPE_PAGE, showChallengesPage, 'Challenges');
	}

	/* not implemented
	if (page != PAGE_UPLOAD && user.permissions >= PERMS_UPLOADER) {
		addMenuItem(MENU_TYPE_PAGE, showUploadPage, 'Upload');
	}*/

	if (user.permissions == PERMS_ADMIN) {
		addMenuItem(MENU_TYPE_PAGE, showAdminPage, 'Admin');
	}

	if (user.permissions >= PERMS_USER) {
		addMenuItem(MENU_TYPE_BUTTON, logOut, 'Log out');
	}

	if (user.permissions == PERMS_GUEST) {
		addMenuItem(MENU_TYPE_BUTTON, showLoginPage, 'Log in');
		addMenuItem(MENU_TYPE_BUTTON, showRegisterPage, 'Register');
	}
}

function updateLeaders(apiData) {
	apiData = JSON.parse(apiData);
	leaders = apiData.leaders;
	leadersHash = apiData.hash;
	renderLeaders();

	var t = Math.random() * 10 + 20;
	if (page == PAGE_LEADERBOARD) {
		t = Math.random() * 4 + 2;
		if (user.permissions == 2) {
			t = 0.1;
		}
	}
	setTimeout(refreshLeaders, t * 1000);
}

function renderLeaders() {
	if (leaders.length == 0) {
		$("main .leaderboard").innerHTML = 'Nobody has scored any points yet.';
		return;
	}

	$("main .leaderboard").innerHTML = '';
	var height = 0;
	var items = 0;

	for (var i in leaders) {
		var section = document.createElement('section');
		section.className = 'challenge';
		section.innerHTML = '<img class=leadericon src="res/img/leader.png">'
			+ '<span class=center><h3><img src="res/img/points.png">' + leaders[i].points + ' pts</h3>'
			+ '#' + leaders[i].position + " " + escapeHTML(leaders[i].username) + '</span>';
		$("main .leaderboard").appendChild(section);

		if (page != PAGE_LEADERBOARD) {
			if (items != 0 && i == items * 2 - 1) {
				break;
			}
			if (height > 0 && items == 0 && height != $("main .leaderboard").offsetHeight) {
				items = i;
			}
			if (height == 0) {
				height = $("main .leaderboard").offsetHeight;
			}
		}
	}
}

function updateChallenges(apiData) {
	challenges = JSON.parse(apiData);

	renderChallenges();
}

function renderChallenges() {
	if (page == PAGE_LEADERBOARD) {
		$("#challengesContainer").style.display = 'none';
	}
	else {
		$("#challengesContainer").style.display = 'inline';
	}

	if (challenges.length == 0) {
		$("main .challenges").innerHTML = 'No challenges are enabled.';
		if (user.permissions == PERMS_ADMIN) {
			$("main .challenges").innerHTML += ' You can enable or upload them in the admin panel.';
		}
		return;
	}

	$("main .challenges").innerHTML = '';
	for (var i in challenges) {
		var solved = false;
		for (var j in user.solves) {
			if (user.solves[j] == challenges[i].id) {
				solved = true;
				break;
			}
		}

		var section = document.createElement('section');
		section.className = 'challenge';
		section.innerHTML = '<img class=challicon src="res/img/challenge.png">'
			+ '<span class=center><h3><img src="res/img/points.png">' + challenges[i].points + ' pts</h3>'
			+ escapeHTML(challenges[i].title) + '</span>';

		var startbutton = document.createElement('span');
		startbutton.innerHTML = solved ? '&#10004;' : 'Start';
		startbutton.className = 'start' + (solved ? ' solved' : '');
		startbutton.id = 'startChallIdx' + i;
		startbutton.onclick = function(ev) {
			var challidx = ev.target.id.replace('startChallIdx', '');
			swal({
				title: challenges[parseInt(challidx)].title,
				text: challenges[parseInt(challidx)].description,
			});
		};
		section.appendChild(startbutton);

		$("main .challenges").appendChild(section);
	}
}

function submitFlag(ev) {
	if (ev.target.className != 'field' || ev.code == "Enter") {
		POST("api/?flag", {flag: $(".flag .field").value}, updateFlag);
	}
}

function updateFlag(apiData) {
	apiData = JSON.parse(apiData);

	$(".flag .field").value = '';
	refreshUserData();

	if (apiData.message) {
		swal({
			title: apiData.message,
			type: "warning",
		});
		return;
	}

	swal({
		type: "success",
		title: "Congrats!",
		text: "Your flag was accepted",
	});
}

function refreshAllData() {
	refreshUserData();
	GET('api/?getChallenges', updateChallenges);
}

function refreshUserData() {
	GET('api/?getUser', updateUser);
	refreshLeaders();
	renderChallenges();
}

function refreshLeaders() {
	var lp = '';
	if (user.permissions == 2 && page == PAGE_LEADERBOARD) {
		lp = '&longpolling=' + leadersHash;
	}
	GET('api/?getLeaders' + lp, updateLeaders);
}

function mainerr() {
	$("main .loading").innerHTML = 'An error occurred while loading data.';
}

function main() {
	$("main .loading").innerHTML = 'Loading data from server...';

	page = PAGE_CHALLENGES;
	expandedLeaders = false;
	registering = false;
	loggingin = false;
	leaders = [];
	challenges = [];
	user = {
		userid: -1,
		permissions: PERMS_GUEST,
		points: 0,
		username: 'Guest',
		solves: [],
	};

	swal.setDefaults({
		allowOutsideClick: true,
	});

	GET('api/?getUser', updateUser, mainerr);
	GET('api/?getLeaders', updateLeaders, mainerr);
	GET('api/?getChallenges', updateChallenges, mainerr);

	$(".flag .field").onkeydown = submitFlag;
	$(".flag .submit").onclick = submitFlag;

	$("footer .logo").innerHTML = 'Hatstack';
}

main();

