<?php

declare(strict_types=1);

date_default_timezone_set('Asia/Manila');

require_once '../../auth/auth.php';
require_once '../../config/database.php';

requireAdmin();

header('Content-Type: application/json; charset=utf-8');


/*
 * =========================================================
 * RESPONSE HELPER
 * =========================================================
 */

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

    /*
     * =====================================================
     * ACTION
     * =====================================================
     */

    $action = trim(
        (string) ($_GET['action'] ?? '')
    );


    /*
     * =====================================================
     * LIST
     * =====================================================
     */

    if ($action === 'list') {

        /*
         * Pagination
         */

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

        $page =
            $page && $page > 0
                ? $page
                : 1;

        $pageSize =
            $pageSize && $pageSize > 0
                ? min($pageSize, 100)
                : 20;


        /*
         * Filters
         */

        $date = trim(
            (string) ($_GET['date'] ?? '')
        );

        $status = trim(
            (string) ($_GET['status'] ?? '')
        );


        $where = [];

        $params = [];


        /*
         * Date filter
         */

        if ($date !== '') {

            $dateObject =
                DateTime::createFromFormat(
                    'Y-m-d',
                    $date
                );

            if (
                !$dateObject ||
                $dateObject->format('Y-m-d') !== $date
            ) {

                respond([
                    'success' => false,
                    'message' => 'Invalid date filter.'
                ], 400);

            }

            $where[] =
                'business_date = ?';

            $params[] =
                $date;
        }


        /*
         * Status filter
         */

        if ($status !== '') {

            $allowedStatuses = [
                'Open',
                'Saved',
                'Reopened'
            ];

            if (
                !in_array(
                    $status,
                    $allowedStatuses,
                    true
                )
            ) {

                respond([
                    'success' => false,
                    'message' => 'Invalid status filter.'
                ], 400);

            }

            $where[] =
                'status = ?';

            $params[] =
                $status;
        }


        /*
         * WHERE clause
         */

        $whereSql =
            $where
                ? 'WHERE ' . implode(
                    ' AND ',
                    $where
                )
                : '';


        /*
         * Count records
         */

        $countStmt =
            $pdo->prepare(
                "SELECT COUNT(*)
                 FROM daily_records
                 {$whereSql}"
            );

        $countStmt->execute(
            $params
        );

        $total =
            (int) $countStmt->fetchColumn();


        /*
         * Pagination offset
         */

        $offset =
            ($page - 1) * $pageSize;


        /*
         * Get records
         */

        $stmt =
            $pdo->prepare(
                "SELECT
                    daily_id,
                    business_date,
                    status,
                    closing_result,
                    driver_remittance_status,
                    driver_remittance_difference,
                    COALESCE(
                        updated_at,
                        created_at,
                        CONCAT(
                            business_date,
                            ' 00:00:00'
                        )
                    ) AS updated_at
                 FROM daily_records
                 {$whereSql}
                 ORDER BY
                    business_date DESC,
                    daily_id DESC
                 LIMIT {$pageSize}
                 OFFSET {$offset}"
            );

        $stmt->execute(
            $params
        );


        $records =
            $stmt->fetchAll(
                PDO::FETCH_ASSOC
            );


        respond([
            'success' => true,
            'records' => $records,
            'page' => $page,
            'page_size' => $pageSize,
            'total' => $total,
            'total_pages' => max(
                1,
                (int) ceil(
                    $total / $pageSize
                )
            )
        ]);
    }


    /*
     * =====================================================
     * VIEW
     * =====================================================
     *
     * Read-only retrieval of one finalized daily record.
     * =====================================================
     */

    if ($action === 'view') {

        $dailyId = filter_input(
            INPUT_GET,
            'daily_id',
            FILTER_VALIDATE_INT
        );


        if (
            !$dailyId ||
            $dailyId <= 0
        ) {

            respond([
                'success' => false,
                'message' => 'Invalid daily record ID.'
            ], 400);

        }


        /*
         * -------------------------------------------------
         * Daily record
         * -------------------------------------------------
         */

        $stmt =
            $pdo->prepare(
                "SELECT
                    daily_id,
                    business_date,
                    status,
                    closing_result,
                    driver_remittance_status,
                    driver_remittance_difference,
                    actual_station_cash,
                    created_at,
                    updated_at,
                    saved_at,
                    saved_by
                 FROM daily_records
                 WHERE daily_id = ?
                 LIMIT 1"
            );

        $stmt->execute([
            $dailyId
        ]);


        $dailyRecord =
            $stmt->fetch(
                PDO::FETCH_ASSOC
            );


        if (!$dailyRecord) {

            respond([
                'success' => false,
                'message' => 'Daily record not found.'
            ], 404);

        }


        /*
         * -------------------------------------------------
         * Walk-in / daily sales
         * -------------------------------------------------
         */

        $stmt =
            $pdo->prepare(
                "SELECT
                    daily_sales_id,
                    sales_date,
                    walk_in_customers,
                    walk_in_price,
                    other_shop_payment,
                    other_sales,
                    notes
                 FROM daily_sales
                 WHERE daily_id = ?
                 ORDER BY daily_sales_id ASC"
            );

        $stmt->execute([
            $dailyId
        ]);


        $sales =
            $stmt->fetchAll(
                PDO::FETCH_ASSOC
            );


        /*
         * -------------------------------------------------
         * Deliveries
         * -------------------------------------------------
         */

        $stmt =
            $pdo->prepare(
                "SELECT
                    d.delivery_id,
                    d.customer_id,
                    c.customer_name,
                    d.delivery_date,
                    d.slim_quantity,
                    d.round_quantity,
                    d.price_per_gallon,
                    d.amount_due,
                    d.notes,
                    d.created_at
                 FROM deliveries d
                 LEFT JOIN customers c
                    ON c.customer_id = d.customer_id
                 WHERE d.daily_id = ?
                 ORDER BY
                    d.delivery_id ASC"
            );

        $stmt->execute([
            $dailyId
        ]);


        $deliveries =
            $stmt->fetchAll(
                PDO::FETCH_ASSOC
            );


        /*
         * -------------------------------------------------
         * Payments
         * -------------------------------------------------
         */

        $stmt =
            $pdo->prepare(
                "SELECT
                    p.payment_id,
                    p.delivery_id,
                    p.customer_id,
                    c.customer_name,
                    p.payment_date,
                    p.amount,
                    p.payment_method,
                    p.collection_location,
                    p.notes,
                    p.created_at
                 FROM payments p
                 LEFT JOIN customers c
                    ON c.customer_id = p.customer_id
                 WHERE p.daily_id = ?
                 ORDER BY
                    p.payment_id ASC"
            );

        $stmt->execute([
            $dailyId
        ]);


        $payments =
            $stmt->fetchAll(
                PDO::FETCH_ASSOC
            );


        /*
         * -------------------------------------------------
         * Expenses
         * -------------------------------------------------
         */

        $stmt =
            $pdo->prepare(
                "SELECT
                    expense_id,
                    expense_date,
                    category,
                    expense_location,
                    description,
                    amount,
                    notes,
                    created_at
                 FROM expenses
                 WHERE daily_id = ?
                 ORDER BY
                    expense_id ASC"
            );

        $stmt->execute([
            $dailyId
        ]);


        $expenses =
            $stmt->fetchAll(
                PDO::FETCH_ASSOC
            );


        /*
         * -------------------------------------------------
         * Return complete read-only record
         * -------------------------------------------------
         */

        respond([
            'success' => true,

            'record' => [
                'daily' => $dailyRecord,
                'sales' => $sales,
                'deliveries' => $deliveries,
                'payments' => $payments,
                'expenses' => $expenses
            ]
        ]);
    }


    /*
     * =====================================================
     * UNKNOWN ACTION
     * =====================================================
     */

    respond([
        'success' => false,
        'message' => 'Unknown action.'
    ], 400);


} catch (Throwable $e) {

    /*
     * Temporary debugging.
     */

    respond([
        'success' => false,
        'message' => $e->getMessage(),
        'error_type' => get_class($e)
    ], 500);
}