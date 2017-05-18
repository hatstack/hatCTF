function $(q) {
	return document.querySelector(q);
}

function $$(q) {
	return document.querySelectorAll(q);
}


function escapeHTML(unsafe) {
	// From http://stackoverflow.com/a/6234804/1201863
	// cc by-sa 3.0
	return unsafe
		.replace(/&/g, "&amp;")
		.replace(/</g, "&lt;")
		.replace(/>/g, "&gt;")
		.replace(/"/g, "&quot;")
		.replace(/'/g, "&#039;");
}

function req(uri, method, cb, err, data) {
	var xhr = new XMLHttpRequest();
	xhr.open(method, uri, true);
	if (method == 'POST') {
		xhr.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
	}
	xhr.send(data ? data : null);
	xhr.onreadystatechange = function() {
		if (xhr.readyState == 4 && cb) {
			cb(xhr.responseText);
		}
		if (xhr.readyState == 5) {
			if (err) {
				err(xhr.responseText);
			}
			else {
				console.log("Request failed.");
				console.log(xhr);
			}
		}
	};
}

function GET(uri, cb, err) {
	return req(uri, 'GET', cb, err);
}

function POST(uri, data, cb, err) {
	var enc = ''; // encoded data
	var ampersand = '';
	for (var i in data) {
		enc += ampersand + escape(i) + '=' + escape(data[i]);
		ampersand = '&';
	}
	return req(uri, 'POST', cb, err, enc);
}

