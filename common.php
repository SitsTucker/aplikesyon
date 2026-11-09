<?php
// common.php — shared by api.php and auth.php:
// JSON responses, the database connection, and admin login sessions.

header('Content-Type: application/json; charset=utf-8');

// Make mysqli throw exceptions so callers can catch them and return JSON errors
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Log the admin out after this many seconds without any activity
const SESSION_IDLE_SECONDS = 7200; // 2 hours

/* ---------- Responses ---------- */

function respond($data, int $code = 200): void {
    http_response_code($code);
    header('Cache-Control: no-store'); // never cache data or login state
    echo json_encode($data);
    exit;
}

// POST/PUT bodies must be sent as JSON. Besides being what api.js does, this
// stops other websites from submitting plain HTML forms to these endpoints.
function require_json_request(): void {
    $type = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($type, 'application/json') !== 0) {
        respond(["error" => "Content-Type must be application/json"], 415);
    }
}

/* ---------- Database ---------- */

function db_connect(): mysqli {
    try {
        $conn = new mysqli("localhost", "root", "", "sis_db");
        $conn->set_charset("utf8mb4");
        return $conn;
    } catch (mysqli_sql_exception $e) {
        respond(["error" => "Database connection failed: " . $e->getMessage()], 500);
    }
}

/* ---------- Admin sessions ---------- */

function start_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;

    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_name('sis_session');
    session_set_cookie_params([
        'lifetime' => 0,        // ends when the browser is closed
        'path'     => '/',
        'secure'   => $https,   // HTTPS-only cookie when the site uses HTTPS
        'httponly' => true,     // not readable from JavaScript
        'samesite' => 'Lax',    // not sent with cross-site form posts
    ]);
    session_start();

    // Idle timeout
    if (isset($_SESSION['admin_id'])) {
        if (time() - ($_SESSION['last_active'] ?? 0) > SESSION_IDLE_SECONDS) {
            $_SESSION = [];
        } else {
            $_SESSION['last_active'] = time();
        }
    }
}

// Username of the logged-in admin, or null when nobody is logged in
function current_admin(): ?string {
    start_session();
    return isset($_SESSION['admin_id']) ? (string)($_SESSION['admin_username'] ?? '') : null;
}

function require_login(): void {
    if (current_admin() === null) {
        respond(["error" => "Please log in to continue."], 401);
    }
}

function sign_in(int $id, string $username): void {
    start_session();
    session_regenerate_id(true); // new session id on login (prevents session fixation)
    $_SESSION['admin_id']       = $id;
    $_SESSION['admin_username'] = $username;
    $_SESSION['last_active']    = time();
}

function sign_out(): void {
    start_session();
    $_SESSION = [];
    $p = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires'  => time() - 3600,
        'path'     => $p['path'],
        'domain'   => $p['domain'],
        'secure'   => $p['secure'],
        'httponly' => $p['httponly'],
        'samesite' => $p['samesite'] ?: 'Lax',
    ]);
    session_destroy();
}
