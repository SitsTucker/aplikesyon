// api.js — talks to the PHP/MySQL backend (api.php for students, auth.php for login)

// One place that sends the request and turns every kind of failure into a
// readable Error message (shown in the toast).
// A 401 ("not logged in") sends the browser to the login page, except for the
// login calls themselves (authRedirect: false).
async function apiRequest(url, options, { authRedirect = true } = {}) {
    let res;
    try {
        res = await fetch(url, options);
    } catch (err) {
        throw new Error(
            'Cannot reach the server. Open this page through your local server ' +
            '(e.g. http://localhost/your-folder/), not by double-clicking the file.'
        );
    }

    const text = await res.text();
    let json = null;
    try {
        json = text ? JSON.parse(text) : null;
    } catch (err) {
        // Not JSON, e.g. PHP isn't running or a PHP error page came back
    }

    if (res.status === 401 && authRedirect) {
        window.location.replace('login.html');
        throw new Error('Please log in to continue.');
    }
    if (!res.ok) {
        throw new Error((json && json.error) || `Request failed (${res.status})`);
    }
    if (json === null) {
        throw new Error(
            'The server did not return valid data. Make sure PHP and MySQL are running ' +
            'and that database.sql has been imported.'
        );
    }
    return json;
}

const JSON_HEADERS = { 'Content-Type': 'application/json' };
const NO_REDIRECT = { authRedirect: false };

const API = {
    /* ----- students (admin only) ----- */
    list() {
        return apiRequest('api.php');
    },

    create(data) {
        return apiRequest('api.php', {
            method: 'POST',
            headers: JSON_HEADERS,
            body: JSON.stringify(data),
        });
    },

    update(id, data) {
        return apiRequest(`api.php?id=${encodeURIComponent(id)}`, {
            method: 'PUT',
            headers: JSON_HEADERS,
            body: JSON.stringify(data),
        });
    },

    async remove(id) {
        await apiRequest(`api.php?id=${encodeURIComponent(id)}`, { method: 'DELETE' });
        return true;
    },

    async clearAll() {
        await apiRequest('api.php?all=1', { method: 'DELETE' });
        return true;
    },

    /* ----- admin login ----- */

    // → { loggedIn, username, needsSetup }
    session() {
        return apiRequest('auth.php', undefined, NO_REDIRECT);
    },

    login(username, password) {
        return apiRequest('auth.php', {
            method: 'POST',
            headers: JSON_HEADERS,
            body: JSON.stringify({ action: 'login', username, password }),
        }, NO_REDIRECT);
    },

    // Creates the very first admin account (only works while none exists)
    setup(username, password) {
        return apiRequest('auth.php', {
            method: 'POST',
            headers: JSON_HEADERS,
            body: JSON.stringify({ action: 'setup', username, password }),
        }, NO_REDIRECT);
    },

    async logout() {
        await apiRequest('auth.php', { method: 'DELETE' }, NO_REDIRECT);
        return true;
    },
};
