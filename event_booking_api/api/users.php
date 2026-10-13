
<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, X-Requested-With");

require_once __DIR__ . "/admin_auth.php";
require_once __DIR__ . "/../config/database.php";

function respond($status, $success, $message, $extra = []) {
    http_response_code($status);
    echo json_encode(array_merge([
        "success" => $success,
        "message" => $message
    ], $extra));
    exit;
}

function getId() {
    $id = filter_var(
        $_GET["id"] ?? null,
        FILTER_VALIDATE_INT
    );

    if ($id === false || $id === null || $id <= 0) {
        respond(400, false, "Valid user ID is required");
    }

    return $id;
}

function getBody() {
    $data = json_decode(file_get_contents("php://input"), true);

    if (!is_array($data)) {
        respond(400, false, "Invalid JSON body");
    }

    return $data;
}

function validateUser($data, $creating = false) {
    $name = trim((string)($data["name"] ?? ""));
    $email = strtolower(trim((string)($data["email"] ?? "")));
    $role = strtolower(trim((string)($data["role"] ?? "")));
    $password = (string)($data["password"] ?? "");

    if ($name === "" || $email === "" || $role === "") {
        respond(400, false, "Name, email and role are required");
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        respond(400, false, "Invalid email format");
    }

    if (!in_array($role, ["admin", "organiser", "customer"], true)) {
        respond(400, false, "Invalid user role");
    }

    if ($creating && $password === "") {
        respond(400, false, "Password is required");
    }

    if ($password !== "" && strlen($password) < 6) {
        respond(400, false, "Password must be at least 6 characters");
    }

    return [
        "name" => $name,
        "email" => $email,
        "role" => $role,
        "password" => $password
    ];
}

try {
    $db = (new Database())->getConnection();

    if (!$db) {
        respond(500, false, "Database connection failed");
    }

    $method = $_SERVER["REQUEST_METHOD"];

    // GET: View all users or one user
    if ($method === "GET") {
        if (isset($_GET["id"])) {
            $id = getId();

            $stmt = $db->prepare("
                SELECT user_id, name, email, role, created_at
                FROM users
                WHERE user_id = ?
            ");
            $stmt->execute([$id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                respond(404, false, "User not found");
            }

            respond(200, true, "User retrieved", [
                "data" => $user
            ]);
        }

        $stmt = $db->query("
            SELECT user_id, name, email, role, created_at
            FROM users
            ORDER BY user_id ASC
        ");

        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        respond(200, true, "Users retrieved", [
            "count" => count($users),
            "data" => $users
        ]);
    }

    // POST: Create user
    if ($method === "POST") {
        $user = validateUser(getBody(), true);

        $check = $db->prepare("
            SELECT user_id FROM users WHERE email = ?
        ");
        $check->execute([$user["email"]]);

        if ($check->fetch()) {
            respond(409, false, "Email already exists");
        }

        $stmt = $db->prepare("
            INSERT INTO users (name, email, password, role)
            VALUES (?, ?, ?, ?)
        ");

        $stmt->execute([
            $user["name"],
            $user["email"],
            password_hash($user["password"], PASSWORD_DEFAULT),
            $user["role"]
        ]);

        respond(201, true, "User created successfully", [
            "user_id" => (int)$db->lastInsertId()
        ]);
    }

    // PUT: Update user
    if ($method === "PUT") {
        $id = getId();
        $user = validateUser(getBody());

        $check = $db->prepare("
            SELECT user_id FROM users WHERE user_id = ?
        ");
        $check->execute([$id]);

        if (!$check->fetch()) {
            respond(404, false, "User not found");
        }

        $duplicate = $db->prepare("
            SELECT user_id
            FROM users
            WHERE email = ? AND user_id != ?
        ");
        $duplicate->execute([$user["email"], $id]);

        if ($duplicate->fetch()) {
            respond(409, false, "Email already exists");
        }

        if ($user["password"] !== "") {
            $stmt = $db->prepare("
                UPDATE users
                SET name = ?, email = ?, password = ?, role = ?
                WHERE user_id = ?
            ");

            $stmt->execute([
                $user["name"],
                $user["email"],
                password_hash($user["password"], PASSWORD_DEFAULT),
                $user["role"],
                $id
            ]);
        } else {
            $stmt = $db->prepare("
                UPDATE users
                SET name = ?, email = ?, role = ?
                WHERE user_id = ?
            ");

            $stmt->execute([
                $user["name"],
                $user["email"],
                $user["role"],
                $id
            ]);
        }

        respond(200, true, "User updated successfully", [
            "user_id" => $id
        ]);
    }

    // DELETE: Delete user
    if ($method === "DELETE") {
        $id = getId();

        if ($id === (int)$_SESSION["user_id"]) {
            respond(403, false, "You cannot delete your own account");
        }

        $check = $db->prepare("
            SELECT user_id FROM users WHERE user_id = ?
        ");
        $check->execute([$id]);

        if (!$check->fetch()) {
            respond(404, false, "User not found");
        }

        try {
            $stmt = $db->prepare("
                DELETE FROM users WHERE user_id = ?
            ");
            $stmt->execute([$id]);

            respond(200, true, "User deleted successfully", [
                "user_id" => $id
            ]);
        } catch (PDOException $e) {
            respond(409, false, "User has related records and cannot be deleted");
        }
    }

    respond(405, false, "Method not allowed");

} catch (PDOException $e) {
    error_log($e->getMessage());
    respond(500, false, "Database operation failed");
}
