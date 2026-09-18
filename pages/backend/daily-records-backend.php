<?php

declare(strict_types=1);

date_default_timezone_set('Asia/Manila');

require_once '../../auth/auth.php';
require_once '../../config/database.php';

requireAdmin();

header('Content-Type: application/json; charset=utf-8');

function respond(array $data, int $status = 200): never
{
    http_response_code($status);

    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );

    exit;
}

try {
    $action = trim((string) ($_GET['action'] ?? ''));

    if ($action !== 'list') {
        respond([
            'success' => false,
            'message' => 'Unknown action.'
        ], 400);
    }

    $page = filter_input(
        INPUT_GET,
        'page',
        FILTER_VALIDATE_INT
    );

    $pageSize = filter_input(
        INPUT_GET,
        'page_size',
        FILTER_VALIDATE_INT
    );

    $page = $page && $page > 0 ? $page : 1;
    $pageSize = $pageSize && $pageSize > 0
        ? min($pageSize, 100)
        : 20;

    $date = trim((string) ($_GET['date'] ?? ''));
    $status = trim((string) ($_GET['status'] ?? ''));

    $where = [];
    $params = [];

    if ($date !== '') {
        $dateObject = DateTime::createFromFormat('Y-m-d', $date);

        if (!$dateObject || $dateObject->format('Y-m-d') !== $date) {
            respond([
                'success' => false,
                'message' => 'Invalid date filter.'
            ], 400);
        }

        $where[] = 'business_date = ?';
        $params[] = $date;
    }

    if ($status !== '') {
        $allowedStatuses = [
            'Open',
            'Saved',
            'Reopened'
        ];

        if (!in_array($status, $allowedStatuses, true)) {
            respond([
                'success' => false,
                'message' => 'Invalid status filter.'
            ], 400);
        }

        $where[] = 'status = ?';
        $params[] = $status;
    }

    $whereSql = $where
        ? 'WHERE ' . implode(' AND ', $where)
        : '';

    $countStmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM daily_records
         {$whereSql}"
    );

    $countStmt->execute($params);

    $total = (int) $countStmt->fetchColumn();

    $offset = ($page - 1) * $pageSize;

    $stmt = $pdo->prepare(
        "SELECT
            daily_id,
            business_date,
            status,
            COALESCE(
                updated_at,
                created_at,
                CONCAT(business_date, ' 00:00:00')
            ) AS updated_at
         FROM daily_records
         {$whereSql}
         ORDER BY business_date DESC, daily_id DESC
         LIMIT {$pageSize}
         OFFSET {$offset}"
    );

    $stmt->execute($params);

    respond([
        'success' => true,
        'records' => $stmt->fetchAll(PDO::FETCH_ASSOC),
        'page' => $page,
        'page_size' => $pageSize,
        'total' => $total,
        'total_pages' => max(1, (int) ceil($total / $pageSize))
    ]);

} catch (Throwable $e) {
    respond([
        'success' => false,
        'message' => 'Unable to load daily records.'
    ], 500);
}
