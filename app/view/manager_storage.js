function setLocalSotrage(key, val) {
    localStorage.setItem(key, val);
}

function getLocalStorage(key) {
    return localStorage.getItem(key);
}

function removeLocalStorage(key) {
    localStorage.removeItem(key);
}

function removeAllLocalStorage() {
    localStorage.clear();
}