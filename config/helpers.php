<?php
/**
 * Shared helper functions used across all API endpoints.
 */

function startApiSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

function jsonResponse(array $data, int $statusCode = 200): void {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function getJsonInput(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

/**
 * Ensures the request comes from a logged-in user.
 * Returns the user_id, or sends a 401 response and stops execution.
 */
function requireAuth(): int {
    startApiSession();
    if (!isset($_SESSION['user_id'])) {
        jsonResponse(['error' => 'Unauthorized. Please log in.'], 401);
    }
    return (int) $_SESSION['user_id'];
}
