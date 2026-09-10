<?php

ob_start();
date_default_timezone_set('Asia/Manila');
require_once '../auth/auth.php';
require_once '../config/database.php';
requireAdmin();

header('Content-Type: application/json; charset=utf-8');

function resetJsonResponse(array $data, int $status = 200): void
{
    if (ob_get_level() > 0) {
        ob_clean();
    }

    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        resetJsonResponse([
            'success' => false,
            'message' => 'Method not allowed.'
        ], 405);
    }

    $dailyId = filter_input(INPUT_POST, 'daily_id', FILTER_VALIDATE_INT);

    $pdo->beginTransaction();

    if ($dailyId) {
        $stmt = $pdo->prepare(
            "SELECT daily_id, status
             FROM daily_records
             WHERE daily_id = ?
             LIMIT 1
             FOR UPDATE"
        );
        $stmt->execute([$dailyId]);
        $dailyRecord = $stmt->fetch();
    } else {
        $stmt = $pdo->query(
            "SELECT daily_id, status
             FROM daily_records
             WHERE status = 'Open'
             ORDER BY daily_id DESC
             LIMIT 1
             FOR UPDATE"
        );
        $dailyRecord = $stmt->fetch();
    }

    if (!$dailyRecord) {
        throw new RuntimeException('No open daily record was found.');
    }

    if ($dailyRecord['status'] !== 'Open') {
        throw new RuntimeException('This daily record is already closed and cannot be reset.');
    }

    $dailyId = (int) $dailyRecord['daily_id'];

    // Delete the MySQL autosave draft first.
    $draftTableExists = (bool) $pdo
        ->query("SHOW TABLES LIKE 'daily_closing_drafts'")
        ->fetchColumn();

    if ($draftTableExists) {
        $stmt = $pdo->prepare("DELETE FROM daily_closing_drafts WHERE daily_id = ?");
        $stmt->execute([$dailyId]);
    }

    // Delete records created for this still-open day.
    // Payments must be deleted before deliveries because payments reference deliveries.
    $stmt = $pdo->prepare("DELETE FROM payments WHERE daily_id = ?");
    $stmt->execute([$dailyId]);

    $stmt = $pdo->prepare("DELETE FROM deliveries WHERE daily_id = ?");
    $stmt->execute([$dailyId]);

    $stmt = $pdo->prepare("DELETE FROM expenses WHERE daily_id = ?");
    $stmt->execute([$dailyId]);

    $stmt = $pdo->prepare("DELETE FROM daily_sales WHERE daily_id = ?");
    $stmt->execute([$dailyId]);

    $pdo->commit();

    resetJsonResponse([
        'success' => true,
        'daily_id' => $dailyId,
        'message' => 'Daily Closing has been completely reset.'
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    resetJsonResponse([
        'success' => false,
        'message' => $e->getMessage()
    ], 500);
}
