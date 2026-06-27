<?php
/**
 * Transactions API — full CRUD, scoped to the logged-in user.
 *
 * GET    /api/transactions.php              -> list all (supports ?type=, ?category_id=, ?month=YYYY-MM)
 * GET    /api/transactions.php?id=5         -> get single transaction
 * POST   /api/transactions.php              -> create  { type, amount, category_id, description, transaction_date }
 * PUT    /api/transactions.php?id=5         -> update  { type, amount, category_id, description, transaction_date }
 * DELETE /api/transactions.php?id=5         -> delete
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

header('Content-Type: application/json');

$pdo = getDbConnection();
$userId = requireAuth();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        if (isset($_GET['id'])) {
            getOne($pdo, $userId, (int) $_GET['id']);
        } else {
            getAll($pdo, $userId);
        }
        break;

    case 'POST':
        create($pdo, $userId);
        break;

    case 'PUT':
        if (!isset($_GET['id'])) {
            jsonResponse(['error' => 'Transaction id is required for update.'], 400);
        }
        update($pdo, $userId, (int) $_GET['id']);
        break;

    case 'DELETE':
        if (!isset($_GET['id'])) {
            jsonResponse(['error' => 'Transaction id is required for delete.'], 400);
        }
        delete($pdo, $userId, (int) $_GET['id']);
        break;

    default:
        jsonResponse(['error' => 'Method not allowed.'], 405);
}

// ---------------- READ (all, with optional filters) ----------------
function getAll(PDO $pdo, int $userId): void {
    $sql = "SELECT t.id, t.type, t.amount, t.description, t.transaction_date,
                   t.category_id, c.name AS category_name
            FROM transactions t
            LEFT JOIN categories c ON t.category_id = c.id
            WHERE t.user_id = :user_id";
    $params = ['user_id' => $userId];

    if (!empty($_GET['type']) && in_array($_GET['type'], ['income', 'expense'], true)) {
        $sql .= " AND t.type = :type";
        $params['type'] = $_GET['type'];
    }
    if (!empty($_GET['category_id'])) {
        $sql .= " AND t.category_id = :category_id";
        $params['category_id'] = (int) $_GET['category_id'];
    }
    if (!empty($_GET['month'])) { // format YYYY-MM
        $sql .= " AND DATE_FORMAT(t.transaction_date, '%Y-%m') = :month";
        $params['month'] = $_GET['month'];
    }

    $sql .= " ORDER BY t.transaction_date DESC, t.id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    jsonResponse(['transactions' => $stmt->fetchAll()]);
}

// ---------------- READ (single) ----------------
function getOne(PDO $pdo, int $userId, int $id): void {
    $stmt = $pdo->prepare("SELECT t.id, t.type, t.amount, t.description, t.transaction_date,
                                   t.category_id, c.name AS category_name
                            FROM transactions t
                            LEFT JOIN categories c ON t.category_id = c.id
                            WHERE t.id = ? AND t.user_id = ?");
    $stmt->execute([$id, $userId]);
    $row = $stmt->fetch();

    if (!$row) {
        jsonResponse(['error' => 'Transaction not found.'], 404);
    }
    jsonResponse(['transaction' => $row]);
}

// ---------------- CREATE ----------------
function create(PDO $pdo, int $userId): void {
    $input = getJsonInput();
    [$valid, $errors] = validateTransactionInput($input);
    if (!$valid) {
        jsonResponse(['error' => implode(' ', $errors)], 422);
    }

    $stmt = $pdo->prepare("INSERT INTO transactions (user_id, category_id, type, amount, description, transaction_date)
                            VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $userId,
        $input['category_id'] ?? null,
        $input['type'],
        $input['amount'],
        $input['description'] ?? '',
        $input['transaction_date'],
    ]);

    jsonResponse(['message' => 'Transaction created.', 'id' => (int) $pdo->lastInsertId()], 201);
}

// ---------------- UPDATE ----------------
function update(PDO $pdo, int $userId, int $id): void {
    // Confirm ownership first
    $check = $pdo->prepare("SELECT id FROM transactions WHERE id = ? AND user_id = ?");
    $check->execute([$id, $userId]);
    if (!$check->fetch()) {
        jsonResponse(['error' => 'Transaction not found.'], 404);
    }

    $input = getJsonInput();
    [$valid, $errors] = validateTransactionInput($input);
    if (!$valid) {
        jsonResponse(['error' => implode(' ', $errors)], 422);
    }

    $stmt = $pdo->prepare("UPDATE transactions
                            SET category_id = ?, type = ?, amount = ?, description = ?, transaction_date = ?
                            WHERE id = ? AND user_id = ?");
    $stmt->execute([
        $input['category_id'] ?? null,
        $input['type'],
        $input['amount'],
        $input['description'] ?? '',
        $input['transaction_date'],
        $id,
        $userId,
    ]);

    jsonResponse(['message' => 'Transaction updated.']);
}

// ---------------- DELETE ----------------
function delete(PDO $pdo, int $userId, int $id): void {
    $stmt = $pdo->prepare("DELETE FROM transactions WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $userId]);

    if ($stmt->rowCount() === 0) {
        jsonResponse(['error' => 'Transaction not found.'], 404);
    }
    jsonResponse(['message' => 'Transaction deleted.']);
}

// ---------------- VALIDATION ----------------
function validateTransactionInput(array $input): array {
    $errors = [];

    if (empty($input['type']) || !in_array($input['type'], ['income', 'expense'], true)) {
        $errors[] = "Type must be 'income' or 'expense'.";
    }
    if (!isset($input['amount']) || !is_numeric($input['amount']) || (float) $input['amount'] <= 0) {
        $errors[] = "Amount must be a positive number.";
    }
    if (empty($input['transaction_date']) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $input['transaction_date'])) {
        $errors[] = "Transaction date must be in YYYY-MM-DD format.";
    }

    return [empty($errors), $errors];
}
