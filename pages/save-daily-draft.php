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

    /* Keep drafts separate from permanent accounting records. */
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS daily_closing_drafts (
            draft_id INT NOT NULL AUTO_INCREMENT,
            daily_id INT NOT NULL,
            draft_data LONGTEXT NOT NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (draft_id),
            UNIQUE KEY uq_daily_closing_draft_daily_id (daily_id),
            KEY idx_daily_closing_draft_daily_id (daily_id),
            CONSTRAINT fk_daily_closing_draft_daily
                FOREIGN KEY (daily_id)
                REFERENCES daily_records (daily_id)
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci"
    );

    $encodedDraft = json_encode(
        $draft,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    if ($encodedDraft === false) {
        throw new Exception('Unable to encode daily draft.');
    }

    $stmt = $pdo->prepare(
        "INSERT INTO daily_closing_drafts
            (daily_id, draft_data)
         VALUES
            (?, ?)
         ON DUPLICATE KEY UPDATE
            draft_data = VALUES(draft_data),
            updated_at = CURRENT_TIMESTAMP"
    );

    $stmt->execute([
        $dailyId,
        $encodedDraft,
    ]);

    echo json_encode([
        'success' => true,
        'daily_id' => (int) $dailyRecord['daily_id'],
        'business_date' => $dailyRecord['business_date'],
        'message' => 'Daily draft saved to MySQL.'
    ]);
} catch (Throwable $e) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
