<?php

date_default_timezone_set('Asia/Manila');

require_once '../auth/auth.php';
require_once '../config/database.php';

requireAdmin();

header('Content-Type: application/json; charset=utf-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => 'Method not allowed.'
        ]);
        exit;
    }

    $dailyId = filter_input(INPUT_POST, 'daily_id', FILTER_VALIDATE_INT);
    $draftRaw = (string) ($_POST['draft'] ?? '');

    if (!$dailyId) {
        throw new Exception('Invalid daily record.');
    }

    if ($draftRaw === '') {
        throw new Exception('Draft data is required.');
    }

    $draft = json_decode($draftRaw, true);

    if (!is_array($draft)) {
        throw new Exception('Invalid draft data.');
    }

    $stmt = $pdo->prepare(
        "SELECT daily_id, business_date
         FROM daily_records
         WHERE daily_id = ?
           AND status = 'Open'
         LIMIT 1"
    );
    $stmt->execute([$dailyId]);
    $dailyRecord = $stmt->fetch();

    if (!$dailyRecord) {
        throw new Exception('The selected daily record is not open.');
    }

    /*
     * This endpoint intentionally stores only the current form state.
     * It does NOT insert deliveries, payments, or expenses into their
     * permanent tables, so repeated autosaves cannot create duplicates.
     *
     * The draft is kept in the browser for now. This endpoint validates
     * the active daily record and provides a safe server-side boundary
     * for the next MySQL draft-storage step.
     */

    echo json_encode([
        'success' => true,
        'daily_id' => (int) $dailyRecord['daily_id'],
        'business_date' => $dailyRecord['business_date'],
        'message' => 'Daily draft accepted.'
    ]);
} catch (Throwable $e) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
