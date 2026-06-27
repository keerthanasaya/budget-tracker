<?php
/**
 * Categories API — full CRUD, scoped to the logged-in user.
 *
 * GET    /api/categories.php           -> list all categories for the user
 * POST   /api/categories.php           -> create  { name, type }
 * PUT    /api/categories.php?id=3      -> update  { name, type }
 * DELETE /api/categories.php?id=3      -> delete
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

header('Content-Type: application/json');

$pdo = getDbConnection();
$userId = requireAuth();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        $stmt = $pdo->prepare("SELECT id, name, type FROM categories WHERE user_id = ? ORDER BY type, name");
        $stmt->execute([$userId]);
        jsonResponse(['categories' => $stmt->fetchAll()]);
        break;

    case 'POST':
        $input = getJsonInput();
        $name = trim($input['name'] ?? '');
        $type = $input['type'] ?? '';

        if ($name === '' || !in_array($type, ['income', 'expense'], true)) {
            jsonResponse(['error' => 'Name and a valid type (income/expense) are required.'], 422);
        }

        try {
            $stmt = $pdo->prepare("INSERT INTO categories (user_id, name, type) VALUES (?, ?, ?)");
            $stmt->execute([$userId, $name, $type]);
            jsonResponse(['message' => 'Category created.', 'id' => (int) $pdo->lastInsertId()], 201);
        } catch (PDOException $e) {
            jsonResponse(['error' => 'Category already exists.'], 409);
        }
        break;

    case 'PUT':
        if (!isset($_GET['id'])) {
            jsonResponse(['error' => 'Category id is required.'], 400);
        }
        $id = (int) $_GET['id'];
        $input = getJsonInput();
        $name = trim($input['name'] ?? '');
        $type = $input['type'] ?? '';

        if ($name === '' || !in_array($type, ['income', 'expense'], true)) {
            jsonResponse(['error' => 'Name and a valid type (income/expense) are required.'], 422);
        }

        $stmt = $pdo->prepare("UPDATE categories SET name = ?, type = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([$name, $type, $id, $userId]);

        if ($stmt->rowCount() === 0) {
            jsonResponse(['error' => 'Category not found.'], 404);
        }
        jsonResponse(['message' => 'Category updated.']);
        break;

    case 'DELETE':
        if (!isset($_GET['id'])) {
            jsonResponse(['error' => 'Category id is required.'], 400);
        }
        $id = (int) $_GET['id'];

        $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $userId]);

        if ($stmt->rowCount() === 0) {
            jsonResponse(['error' => 'Category not found.'], 404);
        }
        jsonResponse(['message' => 'Category deleted.']);
        break;

    default:
        jsonResponse(['error' => 'Method not allowed.'], 405);
}
