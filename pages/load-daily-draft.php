<?php

date_default_timezone_set('Asia/Manila');

require_once '../auth/auth.php';
require_once '../config/database.php';

requireAdmin();

header('Content-Type: application/json; charset=utf-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
        exit;
    }

    $dailyId = filter_input(INPUT_GET, 'daily_id', FILTER_VALIDATE_INT);

    if (!$dailyId) {
        $stmt = $pdo->query(
            "SELECT daily_id, business_date
             FROM daily_records
             WHERE status = 'Open'
             ORDER BY daily_id DESC
             LIMIT 1"
        );
        $dailyRecord = $stmt->fetch();
    } else {
        $stmt = $pdo->prepare(
            "SELECT daily_id, business_date
             FROM daily_records
             WHERE daily_id = ?
               AND status = 'Open'
             LIMIT 1"
        );
        $stmt->execute([$dailyId]);
        $dailyRecord = $stmt->fetch();
    }

    if (!$dailyRecord) {
        throw new Exception('No open daily record is available.');
    }

    $dailyId = (int) $dailyRecord['daily_id'];

    $tableExists = $pdo->query(
        "SHOW TABLES LIKE 'daily_closing_drafts'"
    )->fetchColumn();

    if (!$tableExists) {
        echo json_encode([
            'success' => true,
            'has_draft' => false,
            'daily_id' => $dailyId,
        ]);
        exit;
    }

    $stmt = $pdo->prepare(
        "SELECT draft_data, updated_at
         FROM daily_closing_drafts
         WHERE daily_id = ?
         LIMIT 1"
    );
    $stmt->execute([$dailyId]);
    $draftRow = $stmt->fetch();

    if (!$draftRow) {
        echo json_encode([
            'success' => true,
            'has_draft' => false,
            'daily_id' => $dailyId,
        ]);
        exit;
    }

    $draft = json_decode($draftRow['draft_data'], true);

    if (!is_array($draft)) {
        throw new Exception('Saved daily draft is invalid.');
    }

    echo json_encode([
        'success' => true,
        'has_draft' => true,
        'daily_id' => $dailyId,
        'updated_at' => $draftRow['updated_at'],
        'draft' => $draft,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
