var cookieRegistry = [];
function setGalleta() {
	const galleta = readCookie("PHPSESSID");
	window.localStorage.setItem('::app3_sys_sess', galleta);
}

function getGalleta() {
	return window.localStorage.getItem('::app3_sys_sess');
}

function restaurarGalleta() {
	const galleta = getGalleta();	
	createCookie("PHPSESSID", galleta, 1);
}

function createCookie(name, value, days) {
	if (days) {
		var date = new Date();
		date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
		var expires = "; expires=" + date.toGMTString();
	}
	else var expires = "";
	document.cookie = name + "=" + value + expires + "; path=/";
}

function readCookie(name) {
	var nameEQ = name + "=";
	var ca = document.cookie.split(';');
	for (var i = 0; i < ca.length; i++) {
		var c = ca[i];
		while (c.charAt(0) == ' ') c = c.substring(1, c.length);
		if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length, c.length);
	}
	return null;
}

function eraseCookie(name) {
	createCookie(name, "", -1);
}

function listenCookieChange(callback) {
	const galleta = getGalleta();	
	cookieName = "PHPSESSID";
	setInterval(function () {
		if (cookieRegistry[cookieName]) {
			if (readCookie(cookieName) != cookieRegistry[cookieName]) {				
				// update registry so we dont get triggered again
				cookieRegistry[cookieName] = readCookie(cookieName);
				if (galleta === readCookie(cookieName)) { return; }
				return callback();
			}
		} else {
			cookieRegistry[cookieName] = readCookie(cookieName);
		}
	}, 1000);
}