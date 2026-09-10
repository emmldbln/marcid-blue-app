<?php

date_default_timezone_set('Asia/Manila');
require_once '../auth/auth.php';
require_once '../config/database.php';
requireAdmin();

header('Content-Type: application/json; charset=utf-8');

function failResponse(string $message, int $status = 400): void
{
    http_response_code($status);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        failResponse('Method not allowed.', 405);
    }

    $dailyId = filter_input(INPUT_POST, 'daily_id', FILTER_VALIDATE_INT);
    if (!$dailyId) {
        failResponse('Invalid daily record.');
    }

    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        "SELECT daily_id, status
         FROM daily_records
         WHERE daily_id = ?
         LIMIT 1
         FOR UPDATE"
    );
    $stmt->execute([$dailyId]);
    $dailyRecord = $stmt->fetch();

    if (!$dailyRecord) {
        throw new Exception('Daily record was not found.');
    }

    if ($dailyRecord['status'] !== 'Open') {
        throw new Exception('This daily record is already closed and cannot be reset.');
    }

    // Remove the autosaved draft for this day.
    if ($pdo->query("SHOW TABLES LIKE 'daily_closing_drafts'")->fetchColumn()) {
        $stmt = $pdo->prepare("DELETE FROM daily_closing_drafts WHERE daily_id = ?");
        $stmt->execute([$dailyId]);
    }

    // Remove any permanent records already associated with this still-open day.
    // This makes Reset a true clean slate for testing autosave.
    $stmt = $pdo->prepare("DELETE FROM payments WHERE daily_id = ?");
    $stmt->execute([$dailyId]);

    $stmt = $pdo->prepare("DELETE FROM deliveries WHERE daily_id = ?");
    $stmt->execute([$dailyId]);

    $stmt = $pdo->prepare("DELETE FROM expenses WHERE daily_id = ?");
    $stmt->execute([$dailyId]);

    $stmt = $pdo->prepare("DELETE FROM daily_sales WHERE daily_id = ?");
    $stmt->execute([$dailyId]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'daily_id' => (int) $dailyId,
        'message' => 'Daily closing data has been completely reset.'
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    failResponse($e->getMessage());
}
