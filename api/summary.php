<?php
/**
 * Summary API — aggregated data for the dashboard.
 *
 * GET /api/summary.php?month=YYYY-MM   (month is optional; defaults to current month)
 *
 * Returns:
 *  - total income, total expense, balance (for the month)
 *  - breakdown by category (for expense pie/bar chart use)
 *  - overall all-time balance
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

header('Content-Type: application/json');

$pdo = getDbConnection();
$userId = requireAuth();

$month = $_GET['month'] ?? date('Y-m'); // default: current month, e.g. "2026-06"

// ----- Totals for the selected month -----
$stmt = $pdo->prepare("
    SELECT type, COALESCE(SUM(amount), 0) AS total
    FROM transactions
    WHERE user_id = ? AND DATE_FORMAT(transaction_date, '%Y-%m') = ?
    GROUP BY type
");
$stmt->execute([$userId, $month]);
$rows = $stmt->fetchAll();

$monthIncome = 0.0;
$monthExpense = 0.0;
foreach ($rows as $row) {
    if ($row['type'] === 'income') {
        $monthIncome = (float) $row['total'];
    } else {
        $monthExpense = (float) $row['total'];
    }
}

// ----- All-time balance -----
$stmt = $pdo->prepare("
    SELECT type, COALESCE(SUM(amount), 0) AS total
    FROM transactions
    WHERE user_id = ?
    GROUP BY type
");
$stmt->execute([$userId]);
$allTimeRows = $stmt->fetchAll();

$allTimeIncome = 0.0;
$allTimeExpense = 0.0;
foreach ($allTimeRows as $row) {
    if ($row['type'] === 'income') {
        $allTimeIncome = (float) $row['total'];
    } else {
        $allTimeExpense = (float) $row['total'];
    }
}

// ----- Expense breakdown by category (for the selected month) -----
$stmt = $pdo->prepare("
    SELECT COALESCE(c.name, 'Uncategorized') AS category, SUM(t.amount) AS total
    FROM transactions t
    LEFT JOIN categories c ON t.category_id = c.id
    WHERE t.user_id = ? AND t.type = 'expense' AND DATE_FORMAT(t.transaction_date, '%Y-%m') = ?
    GROUP BY category
    ORDER BY total DESC
");
$stmt->execute([$userId, $month]);
$categoryBreakdown = $stmt->fetchAll();

jsonResponse([
    'month' => $month,
    'monthly' => [
        'income'  => round($monthIncome, 2),
        'expense' => round($monthExpense, 2),
        'balance' => round($monthIncome - $monthExpense, 2),
    ],
    'allTime' => [
        'income'  => round($allTimeIncome, 2),
        'expense' => round($allTimeExpense, 2),
        'balance' => round($allTimeIncome - $allTimeExpense, 2),
    ],
    'categoryBreakdown' => $categoryBreakdown,
]);
