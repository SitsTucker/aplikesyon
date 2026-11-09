<?php
// auth.php — admin login, logout and session status
//
//   GET     → { loggedIn, username, needsSetup }
//   POST    → { action: "login" | "setup", username, password }
//   DELETE  → log out
//
// "setup" creates the very first admin account and only works while the
// admins table is empty. The table itself is created automatically.

require __DIR__ . '/common.php';

const MIN_PASSWORD_LENGTH = 8;
const MAX_PASSWORD_LENGTH = 72; // bcrypt only uses the first 72 bytes

// A valid bcrypt hash of a throw-away string. Checked against when the
// username doesn't exist, so a wrong username takes as long as a wrong password.
const DUMMY_HASH = '$2y$10$mFBCfCXiBKinW/qKWVoLuO02zX/S6/otugzfTQbhBuBiJuWyJCH7y';

function ensure_admins_table(mysqli $conn): void {
    $conn->query(
        "CREATE TABLE IF NOT EXISTS admins (
            id            INT AUTO_INCREMENT PRIMARY KEY,
            username      VARCHAR(50)  NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}

function admin_count(mysqli $conn): int {
    $res = $conn->query("SELECT COUNT(*) AS n FROM admins");
    return (int)$res->fetch_assoc()['n'];
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}

try {
    // ---------- DELETE: log out ----------
    if ($method === 'DELETE') {
        sign_out();
        respond(["success" => true]);
    }

    $conn = db_connect();
    ensure_admins_table($conn);

    // ---------- GET: who is logged in? ----------
    if ($method === 'GET') {
        $username = current_admin();
        respond([
            "loggedIn"   => $username !== null,
            "username"   => $username,
            "needsSetup" => admin_count($conn) === 0,
        ]);
    }

    // ---------- POST: log in, or create the first admin ----------
    if ($method === 'POST') {
        require_json_request();

        $in = json_decode(file_get_contents("php://input"), true);
        if (!is_array($in)) $in = [];

        $action   = is_string($in['action'] ?? null)   ? $in['action']        : '';
        $username = is_string($in['username'] ?? null) ? trim($in['username']) : '';
        $password = is_string($in['password'] ?? null) ? $in['password']      : '';

        if ($action === 'setup') {
            if (admin_count($conn) > 0) {
                respond(["error" => "An admin account already exists. Please log in."], 409);
            }
            if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username)) {
                respond(["error" => "Username must be 3-50 characters: letters, numbers, dot, dash or underscore."], 400);
            }
            if (strlen($password) < MIN_PASSWORD_LENGTH) {
                respond(["error" => "Password must be at least " . MIN_PASSWORD_LENGTH . " characters."], 400);
            }
            if (strlen($password) > MAX_PASSWORD_LENGTH) {
                respond(["error" => "Password is too long (max " . MAX_PASSWORD_LENGTH . " characters)."], 400);
            }

            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO admins (username, password_hash) VALUES (?, ?)");
            $stmt->bind_param("ss", $username, $hash);
            $stmt->execute();
            $newId = $conn->insert_id;
            $stmt->close();

            sign_in((int)$newId, $username);
            respond(["success" => true, "username" => $username]);
        }

        if ($action === 'login') {
            $stmt = $conn->prepare("SELECT id, username, password_hash FROM admins WHERE username = ?");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $stmt->bind_result($id, $dbUser, $dbHash);
            $found = ($stmt->fetch() === true);
            $stmt->close();

            $passwordOk = password_verify($password, $found ? $dbHash : DUMMY_HASH);

            if (!$found || !$passwordOk) {
                usleep(random_int(300000, 600000)); // slow down guessing
                respond(["error" => "Incorrect username or password."], 401);
            }

            sign_in((int)$id, (string)$dbUser);
            respond(["success" => true, "username" => $dbUser]);
        }

        respond(["error" => "Unknown action"], 400);
    }

    respond(["error" => "Method not allowed"], 405);

} catch (Throwable $e) {
    respond(["error" => "Server error: " . $e->getMessage()], 500);
}
