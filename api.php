<?php
// api.php — Full CRUD backend for Student Information System.
// Every request needs a logged-in admin (see auth.php).

require __DIR__ . '/common.php'; // JSON headers, respond(), db_connect(), sessions

// Handle preflight. The app is same-origin, so no CORS headers are sent.
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_login(); // 401 for anyone who isn't logged in as admin

class ValidationError extends Exception {}

/* ---------- Helpers ---------- */

function str_length(string $s): int {
    return function_exists('mb_strlen') ? mb_strlen($s) : strlen($s);
}

// Cast numeric columns so the JS renderer works (id, age, year, gpa)
function cast_row(array $row): array {
    $row['id']   = (int)$row['id'];
    $row['age']  = $row['age']  !== null ? (int)$row['age']   : null;
    $row['year'] = (int)$row['year'];
    $row['gpa']  = $row['gpa']  !== null ? (float)$row['gpa'] : null;
    return $row;
}

function find_student(mysqli $conn, int $id): ?array {
    $res = $conn->query("SELECT * FROM students WHERE id = " . (int)$id);
    $row = $res->fetch_assoc();
    return $row ? cast_row($row) : null;
}

function str_field(array $in, string $key): string {
    $v = $in[$key] ?? '';
    return is_scalar($v) ? trim((string)$v) : '';
}

function number_field(array $in, string $key, string $label) {
    $v = $in[$key] ?? '';
    if ($v === '' || $v === null) return null;
    if (!is_numeric($v)) throw new ValidationError("$label must be a number");
    return $v + 0;
}

// Validate + normalise the JSON body for POST and PUT
function read_student(array $in): array {
    $name = str_field($in, 'name');
    if ($name === '') throw new ValidationError('Name is required');
    if (str_length($name) > 100) throw new ValidationError('Name is too long (max 100 characters)');

    $email = str_field($in, 'email');
    if (str_length($email) > 150) throw new ValidationError('Email is too long (max 150 characters)');
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new ValidationError('Email is not valid');
    }

    $major = str_field($in, 'major');
    if (str_length($major) > 100) throw new ValidationError('Program/major is too long (max 100 characters)');

    $age = number_field($in, 'age', 'Age');
    if ($age !== null) {
        $age = (int)$age;
        if ($age < 1 || $age > 120) throw new ValidationError('Age must be between 1 and 120');
    }

    $year = number_field($in, 'year', 'Year level');
    $year = $year === null ? 1 : (int)$year;
    if ($year < 1 || $year > 4) throw new ValidationError('Year level must be between 1 and 4');

    $gpa = number_field($in, 'gpa', 'GPA');
    if ($gpa !== null) {
        $gpa = (float)$gpa;
        if ($gpa < 0 || $gpa > 4) throw new ValidationError('GPA must be between 0 and 4');
    }

    return [
        'name'  => $name,
        'age'   => $age,
        'email' => $email,
        'major' => $major,
        'year'  => $year,
        'gpa'   => $gpa,
    ];
}

/* ---------- Main ---------- */

try {
    $conn   = db_connect();
    $method = $_SERVER['REQUEST_METHOD'];

    // Parse incoming JSON (POST / PUT only)
    $input = [];
    if ($method === 'POST' || $method === 'PUT') {
        require_json_request();
        $input = json_decode(file_get_contents("php://input"), true);
        if (!is_array($input)) $input = [];
    }

    // ---------- GET: list all students ----------
    if ($method === 'GET') {
        $result   = $conn->query("SELECT * FROM students ORDER BY id ASC");
        $students = [];
        while ($row = $result->fetch_assoc()) {
            $students[] = cast_row($row);
        }
        respond($students);
    }

    // ---------- POST: create a student ----------
    if ($method === 'POST') {
        $s = read_student($input);
        $name  = $s['name'];
        $age   = $s['age'];
        $email = $s['email'];
        $major = $s['major'];
        $year  = $s['year'];
        $gpa   = $s['gpa'];

        $stmt = $conn->prepare(
            "INSERT INTO students (name, age, email, major, year, gpa) VALUES (?, ?, ?, ?, ?, ?)"
        );
        // s=string, i=int, d=double  →  name, age, email, major, year, gpa
        $stmt->bind_param("sissid", $name, $age, $email, $major, $year, $gpa);
        $stmt->execute();
        $newId = $conn->insert_id;
        $stmt->close();

        // Return the full record so app.js can display it
        respond(find_student($conn, $newId));
    }

    // ---------- PUT: update a student ----------
    if ($method === 'PUT') {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if ($id <= 0) respond(["error" => "Missing or invalid id"], 400);

        $s = read_student($input);
        $name  = $s['name'];
        $age   = $s['age'];
        $email = $s['email'];
        $major = $s['major'];
        $year  = $s['year'];
        $gpa   = $s['gpa'];

        $stmt = $conn->prepare(
            "UPDATE students SET name=?, age=?, email=?, major=?, year=?, gpa=? WHERE id=?"
        );
        $stmt->bind_param("sissidi", $name, $age, $email, $major, $year, $gpa, $id);
        $stmt->execute();
        $stmt->close();

        // Look the row up instead of trusting affected_rows: MySQL reports 0
        // affected rows when you save without changing anything.
        $row = find_student($conn, $id);
        if ($row === null) respond(["error" => "Student not found"], 404);
        respond($row);
    }

    // ---------- DELETE: remove a student ----------
    if ($method === 'DELETE') {
        // Support "clear all" via ?all=1
        if (isset($_GET['all']) && $_GET['all'] === '1') {
            $conn->query("DELETE FROM students");
            respond(["success" => true]);
        }

        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if ($id <= 0) respond(["error" => "Missing or invalid id"], 400);

        $stmt = $conn->prepare("DELETE FROM students WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $deleted = $stmt->affected_rows;
        $stmt->close();

        if ($deleted === 0) respond(["error" => "Student not found"], 404);
        respond(["success" => true]);
    }

    respond(["error" => "Method not allowed"], 405);

} catch (ValidationError $e) {
    respond(["error" => $e->getMessage()], 400);
} catch (Throwable $e) {
    respond(["error" => "Server error: " . $e->getMessage()], 500);
}
