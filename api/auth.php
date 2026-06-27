<?php
/**
 * Auth API
 * --------
 * POST   /api/auth.php?action=register   { username, email, password }
 * POST   /api/auth.php?action=login       { username, password }
 * POST   /api/auth.php?action=logout
 * GET    /api/auth.php?action=me          -> current logged-in user info
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

startApiSession();
header('Content-Type: application/json');

$pdo = getDbConnection();
$action = $_GET['action'] ?? '';

switch ($action) {

    case 'register':
        registerUser($pdo);
        break;

    case 'login':
        loginUser($pdo);
        break;

    case 'logout':
        $_SESSION = [];
        session_destroy();
        jsonResponse(['message' => 'Logged out successfully.']);
        break;

    case 'me':
        if (isset($_SESSION['user_id'])) {
            jsonResponse([
                'loggedIn' => true,
                'user' => [
                    'id'       => $_SESSION['user_id'],
                    'username' => $_SESSION['username'],
                ],
            ]);
        } else {
            jsonResponse(['loggedIn' => false]);
        }
        break;

    default:
        jsonResponse(['error' => 'Unknown action.'], 400);
}

function registerUser(PDO $pdo): void {
    $input = getJsonInput();
    $username = trim($input['username'] ?? '');
    $email    = trim($input['email'] ?? '');
    $password = $input['password'] ?? '';

    if ($username === '' || $email === '' || $password === '') {
        jsonResponse(['error' => 'Username, email, and password are required.'], 422);
    }
    if (strlen($password) < 6) {
        jsonResponse(['error' => 'Password must be at least 6 characters.'], 422);
    }

    // Check for existing username/email
    $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ? OR email = ?');
    $stmt->execute([$username, $email]);
    if ($stmt->fetch()) {
        jsonResponse(['error' => 'Username or email already in use.'], 409);
    }

    $hash = password_hash($password, PASSWORD_BCRYPT);

    $stmt = $pdo->prepare('INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)');
    $stmt->execute([$username, $email, $hash]);
    $userId = (int) $pdo->lastInsertId();

    // Seed default categories for the new user
    $defaults = [
        ['Salary', 'income'], ['Freelance', 'income'],
        ['Food', 'expense'], ['Rent', 'expense'],
        ['Transport', 'expense'], ['Utilities', 'expense'],
        ['Entertainment', 'expense'], ['Other', 'expense'],
    ];
    $catStmt = $pdo->prepare('INSERT INTO categories (user_id, name, type) VALUES (?, ?, ?)');
    foreach ($defaults as [$name, $type]) {
        $catStmt->execute([$userId, $name, $type]);
    }

    // Auto-login after registering
    $_SESSION['user_id']  = $userId;
    $_SESSION['username'] = $username;

    jsonResponse(['message' => 'Registered successfully.', 'user' => ['id' => $userId, 'username' => $username]], 201);
}

function loginUser(PDO $pdo): void {
    $input = getJsonInput();
    $username = trim($input['username'] ?? '');
    $password = $input['password'] ?? '';

    if ($username === '' || $password === '') {
        jsonResponse(['error' => 'Username and password are required.'], 422);
    }

    $stmt = $pdo->prepare('SELECT id, username, password_hash FROM users WHERE username = ? OR email = ?');
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        jsonResponse(['error' => 'Invalid username or password.'], 401);
    }

    $_SESSION['user_id']  = $user['id'];
    $_SESSION['username'] = $user['username'];

    jsonResponse(['message' => 'Logged in successfully.', 'user' => ['id' => $user['id'], 'username' => $user['username']]]);
}
