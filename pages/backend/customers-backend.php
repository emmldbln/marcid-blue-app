<?php

declare(strict_types=1);

date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/../../auth/auth.php';
require_once __DIR__ . '/../../config/database.php';

requireAdmin();

header('Content-Type: application/json; charset=utf-8');

function respond(array $data, int $status = 200): never
{
    http_response_code($status);

    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    exit;
}

function postString(string $key, string $default = ''): string
{
    return trim((string)($_POST[$key] ?? $default));
}

function postInt(string $key): int
{
    return (int)($_POST[$key] ?? 0);
}

function columnExists(
    PDO $pdo,
    string $table,
    string $column
): bool {
    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = ?
           AND COLUMN_NAME = ?"
    );

    $stmt->execute([
        $table,
        $column
    ]);

    return (int)$stmt->fetchColumn() > 0;
}

function tableExists(
    PDO $pdo,
    string $table
): bool {
    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM INFORMATION_SCHEMA.TABLES
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = ?"
    );

    $stmt->execute([$table]);

    return (int)$stmt->fetchColumn() > 0;
}

/*
|--------------------------------------------------------------------------
| Customer / Folder Schema
|--------------------------------------------------------------------------
*/

function ensureSchema(PDO $pdo): void
{
    /*
     * Customer folders
     *
     * parent_folder_id makes folders self-referencing,
     * allowing unlimited nesting.
     */

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS customer_folders (
            folder_id INT NOT NULL AUTO_INCREMENT,
            parent_folder_id INT NULL,
            folder_name VARCHAR(150) NOT NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP,

            PRIMARY KEY (folder_id),

            KEY idx_customer_folders_parent
                (parent_folder_id),

            CONSTRAINT fk_customer_folder_parent
                FOREIGN KEY (parent_folder_id)
                REFERENCES customer_folders(folder_id)
                ON DELETE SET NULL
        )
        ENGINE=InnoDB
        DEFAULT CHARSET=utf8mb4
        COLLATE=utf8mb4_unicode_ci"
    );

    /*
     * Add folder_id to customers if it does not exist.
     */

    if (!columnExists($pdo, 'customers', 'folder_id')) {
        $pdo->exec(
            "ALTER TABLE customers
             ADD COLUMN folder_id INT NULL,
             ADD KEY idx_customers_folder_id (folder_id)"
        );
    }

    /*
     * Customer active/inactive status.
     */

    if (!columnExists($pdo, 'customers', 'status')) {
        $pdo->exec(
            "ALTER TABLE customers
             ADD COLUMN status VARCHAR(20)
             NOT NULL DEFAULT 'Active'"
        );
    }

    /*
     * Price history.
     */

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS customer_price_history (
            price_history_id INT NOT NULL AUTO_INCREMENT,
            customer_id INT NOT NULL,
            effective_date DATE NOT NULL,
            price_per_gallon DECIMAL(10,2) NOT NULL,
            reason VARCHAR(255) NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,

            PRIMARY KEY (price_history_id),

            KEY idx_customer_price_history_customer
                (customer_id),

            KEY idx_customer_price_history_date
                (effective_date),

            CONSTRAINT fk_customer_price_history_customer
                FOREIGN KEY (customer_id)
                REFERENCES customers(customer_id)
                ON DELETE CASCADE
        )
        ENGINE=InnoDB
        DEFAULT CHARSET=utf8mb4
        COLLATE=utf8mb4_unicode_ci"
    );

    /*
     * Existing customers receive their current gallon price
     * as their initial price-history entry.
     */

    $pdo->exec(
        "INSERT INTO customer_price_history
            (
                customer_id,
                effective_date,
                price_per_gallon,
                reason
            )

         SELECT
            c.customer_id,
            COALESCE(
                DATE(c.created_at),
                CURDATE()
            ),
            c.gallon_price,
            'Initial price'

         FROM customers c

         LEFT JOIN customer_price_history h
            ON h.customer_id = c.customer_id

         WHERE h.price_history_id IS NULL"
    );
}

/*
|--------------------------------------------------------------------------
| Customer Helpers
|--------------------------------------------------------------------------
*/

function getCustomer(
    PDO $pdo,
    int $customerId
): ?array {
    $stmt = $pdo->prepare(
        "SELECT
            c.customer_id,
            c.customer_name,
            c.contact_number,
            c.address,
            c.gallon_price,
            c.status,
            c.folder_id,
            f.folder_name

         FROM customers c

         LEFT JOIN customer_folders f
            ON f.folder_id = c.folder_id

         WHERE c.customer_id = ?

         LIMIT 1"
    );

    $stmt->execute([$customerId]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function getFolderPath(
    PDO $pdo,
    ?int $folderId
): string {
    if (!$folderId) {
        return '';
    }

    $parts = [];

    $currentId = $folderId;

    /*
     * Safety limit prevents accidental infinite loops.
     */

    for ($i = 0; $i < 100 && $currentId; $i++) {

        $stmt = $pdo->prepare(
            "SELECT
                folder_id,
                parent_folder_id,
                folder_name

             FROM customer_folders

             WHERE folder_id = ?

             LIMIT 1"
        );

        $stmt->execute([$currentId]);

        $folder = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$folder) {
            break;
        }

        array_unshift(
            $parts,
            $folder['folder_name']
        );

        $currentId = (int)(
            $folder['parent_folder_id'] ?? 0
        );
    }

    return implode(' / ', $parts);
}

/*
|--------------------------------------------------------------------------
| Action: List Customers
|--------------------------------------------------------------------------
*/

function listCustomers(PDO $pdo): void
{
    $search = postString('search');

    $folderId = postInt('folder_id');

    $where = [];

    $params = [];

    if ($search !== '') {

        $where[] = "
            (
                c.customer_name LIKE ?
                OR c.contact_number LIKE ?
                OR c.address LIKE ?
            )
        ";

        $term = '%' . $search . '%';

        $params[] = $term;
        $params[] = $term;
        $params[] = $term;
    }

    if ($folderId > 0) {

        $where[] = "c.folder_id = ?";

        $params[] = $folderId;

    } elseif ($folderId === -1) {

        $where[] = "c.folder_id IS NULL";
    }

    $whereSql = '';

    if ($where) {
        $whereSql =
            'WHERE ' . implode(' AND ', $where);
    }

    /*
     * Order statistics.
     */

    $orderStats = "
        SELECT
            customer_id,
            COUNT(*) AS order_count,
            MAX(delivery_date) AS last_order

        FROM deliveries

        GROUP BY customer_id
    ";

    /*
     * Debt statistics.
     *
     * Only unpaid portions of deliveries are counted.
     */

    $debtStats = "
        SELECT
            d.customer_id,

            SUM(
                GREATEST(
                    d.amount_due
                    -
                    COALESCE(p.paid, 0),
                    0
                )
            ) AS debt_amount

        FROM deliveries d

        LEFT JOIN (
            SELECT
                delivery_id,
                SUM(amount) AS paid

            FROM payments

            GROUP BY delivery_id
        ) p
            ON p.delivery_id = d.delivery_id

        GROUP BY d.customer_id
    ";

    /*
     * Credit statistics are optional.
     *
     * Some versions of the Marcid Blue database may not
     * have customer_credits yet.
     */

    $hasCreditsTable =
        tableExists(
            $pdo,
            'customer_credits'
        );

    if ($hasCreditsTable) {

        $creditJoin = "
            LEFT JOIN (
                SELECT
                    customer_id,
                    SUM(amount) AS credit_amount

                FROM customer_credits

                GROUP BY customer_id
            ) credit_stats

                ON credit_stats.customer_id =
                   c.customer_id
        ";

        $creditSelect =
            "COALESCE(
                credit_stats.credit_amount,
                0
            ) AS credit_amount";

    } else {

        $creditJoin = '';

        $creditSelect =
            "0 AS credit_amount";
    }

    $sql = "
        SELECT

            c.customer_id,
            c.customer_name,
            c.contact_number,
            c.address,
            c.gallon_price,
            c.status,
            c.folder_id,

            f.folder_name,

            COALESCE(
                order_stats.order_count,
                0
            ) AS order_count,

            order_stats.last_order,

            COALESCE(
                debt_stats.debt_amount,
                0
            ) AS debt_amount,

            {$creditSelect}

        FROM customers c

        LEFT JOIN customer_folders f
            ON f.folder_id = c.folder_id

        LEFT JOIN (
            {$orderStats}
        ) order_stats

            ON order_stats.customer_id =
               c.customer_id

        LEFT JOIN (
            {$debtStats}
        ) debt_stats

            ON debt_stats.customer_id =
               c.customer_id

        {$creditJoin}

        {$whereSql}

        ORDER BY c.customer_name ASC
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute($params);

    respond([
        'success' => true,
        'customers' =>
            $stmt->fetchAll(PDO::FETCH_ASSOC)
    ]);
}

/*
|--------------------------------------------------------------------------
| Action: List Folders
|--------------------------------------------------------------------------
*/

function listFolders(PDO $pdo): void
{
    $stmt = $pdo->query(
        "SELECT

            f.folder_id,
            f.parent_folder_id,
            f.folder_name,

            COUNT(
                c.customer_id
            ) AS customer_count

         FROM customer_folders f

         LEFT JOIN customers c
            ON c.folder_id = f.folder_id

         GROUP BY
            f.folder_id,
            f.parent_folder_id,
            f.folder_name

         ORDER BY
            f.folder_name ASC"
    );

    respond([
        'success' => true,
        'folders' =>
            $stmt->fetchAll(PDO::FETCH_ASSOC)
    ]);
}

/*
|--------------------------------------------------------------------------
| Action: Save Folder
|--------------------------------------------------------------------------
*/

function saveFolder(PDO $pdo): void
{
    $folderId =
        postInt('folder_id');

    $folderName =
        postString('folder_name');

    $parentId =
        postInt('parent_folder_id');

    if ($folderName === '') {

        respond([
            'success' => false,
            'message' =>
                'Folder name is required.'
        ], 400);
    }

    /*
     * Folder cannot be its own parent.
     */

    if (
        $folderId > 0 &&
        $parentId === $folderId
    ) {

        respond([
            'success' => false,
            'message' =>
                'A folder cannot be its own parent.'
        ], 400);
    }

    /*
     * Validate parent.
     */

    if ($parentId > 0) {

        $stmt = $pdo->prepare(
            "SELECT folder_id
             FROM customer_folders
             WHERE folder_id = ?
             LIMIT 1"
        );

        $stmt->execute([
            $parentId
        ]);

        if (!$stmt->fetch()) {

            respond([
                'success' => false,
                'message' =>
                    'Parent folder not found.'
            ], 404);
        }
    }

    /*
     * Editing an existing folder.
     */

    if ($folderId > 0) {

        $stmt = $pdo->prepare(
            "SELECT
                folder_id,
                parent_folder_id

             FROM customer_folders

             WHERE folder_id = ?

             LIMIT 1"
        );

        $stmt->execute([
            $folderId
        ]);

        $existing =
            $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$existing) {

            respond([
                'success' => false,
                'message' =>
                    'Folder not found.'
            ], 404);
        }

        /*
         * Prevent moving a folder inside one
         * of its own descendants.
         */

        $currentParent =
            $parentId;

        for (
            $i = 0;
            $i < 100 && $currentParent;
            $i++
        ) {

            if (
                $currentParent ===
                $folderId
            ) {

                respond([
                    'success' => false,
                    'message' =>
                        'A folder cannot be moved inside itself or one of its descendants.'
                ], 400);
            }

            $stmt = $pdo->prepare(
                "SELECT parent_folder_id
                 FROM customer_folders
                 WHERE folder_id = ?
                 LIMIT 1"
            );

            $stmt->execute([
                $currentParent
            ]);

            $currentParent =
                (int)(
                    $stmt->fetchColumn() ?? 0
                );
        }

        $stmt = $pdo->prepare(
            "UPDATE customer_folders

             SET
                folder_name = ?,
                parent_folder_id = ?

             WHERE folder_id = ?"
        );

        $stmt->execute([
            $folderName,
            $parentId > 0
                ? $parentId
                : null,
            $folderId
        ]);

    } else {

        /*
         * Create a new folder.
         */

        $stmt = $pdo->prepare(
            "INSERT INTO customer_folders
                (
                    folder_name,
                    parent_folder_id
                )

             VALUES (?, ?)"
        );

        $stmt->execute([
            $folderName,
            $parentId > 0
                ? $parentId
                : null
        ]);

        $folderId =
            (int)$pdo->lastInsertId();
    }

    respond([
        'success' => true,
        'folder' => [
            'folder_id' =>
                $folderId,

            'folder_name' =>
                $folderName,

            'parent_folder_id' =>
                $parentId > 0
                    ? $parentId
                    : null
        ]
    ]);
}

/*
|--------------------------------------------------------------------------
| Action: Delete Folder
|--------------------------------------------------------------------------
*/

function deleteFolder(PDO $pdo): void
{
    $folderId =
        postInt('folder_id');

    if ($folderId <= 0) {

        respond([
            'success' => false,
            'message' =>
                'Invalid folder.'
        ], 400);
    }

    /*
     * Get parent first.
     */

    $stmt = $pdo->prepare(
        "SELECT parent_folder_id

         FROM customer_folders

         WHERE folder_id = ?

         LIMIT 1"
    );

    $stmt->execute([
        $folderId
    ]);

    $parent =
        $stmt->fetchColumn();

    if ($parent === false) {

        respond([
            'success' => false,
            'message' =>
                'Folder not found.'
        ], 404);
    }

    $parentId =
        $parent !== null
            ? (int)$parent
            : null;

    $pdo->beginTransaction();

    try {

        /*
         * Customers move to the deleted folder's
         * parent. If there is no parent, they become
         * unassigned.
         */

        $stmt = $pdo->prepare(
            "UPDATE customers

             SET folder_id = ?

             WHERE folder_id = ?"
        );

        $stmt->execute([
            $parentId,
            $folderId
        ]);

        /*
         * Direct subfolders move up one level.
         */

        $stmt = $pdo->prepare(
            "UPDATE customer_folders

             SET parent_folder_id = ?

             WHERE parent_folder_id = ?"
        );

        $stmt->execute([
            $parentId,
            $folderId
        ]);

        /*
         * Delete only the folder itself.
         */

        $stmt = $pdo->prepare(
            "DELETE FROM customer_folders

             WHERE folder_id = ?"
        );

        $stmt->execute([
            $folderId
        ]);

        $pdo->commit();

    } catch (Throwable $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }

    respond([
        'success' => true
    ]);
}

/*
|--------------------------------------------------------------------------
| Action: Save Customer
|--------------------------------------------------------------------------
*/

function saveCustomer(PDO $pdo): void
{
    $customerId =
        postInt('customer_id');

    $name =
        postString('customer_name');

    $contact =
        postString('contact_number');

    $address =
        postString('address');

    $price =
        postString('gallon_price');

    $status =
        postString(
            'status',
            'Active'
        );

    $folderId =
        postInt('folder_id');

    $effectiveDate =
        postString(
            'effective_date',
            date('Y-m-d')
        );

    $reason =
        postString('price_reason');

    if ($name === '') {

        respond([
            'success' => false,
            'message' =>
                'Customer name is required.'
        ], 400);
    }

    if (
        $price === '' ||
        !is_numeric($price) ||
        (float)$price <= 0
    ) {

        respond([
            'success' => false,
            'message' =>
                'Price per gallon must be greater than zero.'
        ], 400);
    }

    $price =
        round(
            (float)$price,
            2
        );

    if (
        !in_array(
            $status,
            ['Active', 'Inactive'],
            true
        )
    ) {
        $status = 'Active';
    }

    /*
     * Validate folder.
     */

    if ($folderId > 0) {

        $stmt = $pdo->prepare(
            "SELECT folder_id

             FROM customer_folders

             WHERE folder_id = ?

             LIMIT 1"
        );

        $stmt->execute([
            $folderId
        ]);

        if (!$stmt->fetch()) {
            $folderId = 0;
        }
    }

    $pdo->beginTransaction();

    try {

        /*
         * Existing customer.
         */

        if ($customerId > 0) {

            $old =
                getCustomer(
                    $pdo,
                    $customerId
                );

            if (!$old) {

                $pdo->rollBack();

                respond([
                    'success' => false,
                    'message' =>
                        'Customer not found.'
                ], 404);
            }

            $stmt = $pdo->prepare(
                "UPDATE customers

                 SET
                    customer_name = ?,
                    contact_number = ?,
                    address = ?,
                    gallon_price = ?,
                    status = ?,
                    folder_id = ?

                 WHERE customer_id = ?"
            );

            $stmt->execute([
                $name,

                $contact !== ''
                    ? $contact
                    : null,

                $address !== ''
                    ? $address
                    : null,

                $price,
                $status,

                $folderId > 0
                    ? $folderId
                    : null,

                $customerId
            ]);

            /*
             * Only create a new price-history row
             * when the actual price changed.
             */

            if (
                abs(
                    (float)$old['gallon_price']
                    -
                    $price
                ) > 0.009
            ) {

                $stmt = $pdo->prepare(
                    "INSERT INTO customer_price_history
                        (
                            customer_id,
                            effective_date,
                            price_per_gallon,
                            reason
                        )

                     VALUES (?, ?, ?, ?)"
                );

                $stmt->execute([
                    $customerId,

                    $effectiveDate !== ''
                        ? $effectiveDate
                        : date('Y-m-d'),

                    $price,

                    $reason !== ''
                        ? $reason
                        : 'Price update'
                ]);
            }

        } else {

            /*
             * New customer.
             */

            $stmt = $pdo->prepare(
                "INSERT INTO customers
                    (
                        customer_name,
                        contact_number,
                        address,
                        gallon_price,
                        status,
                        folder_id
                    )

                 VALUES (?, ?, ?, ?, ?, ?)"
            );

            $stmt->execute([
                $name,

                $contact !== ''
                    ? $contact
                    : null,

                $address !== ''
                    ? $address
                    : null,

                $price,
                $status,

                $folderId > 0
                    ? $folderId
                    : null
            ]);

            $customerId =
                (int)$pdo->lastInsertId();

            /*
             * Initial price history.
             */

            $stmt = $pdo->prepare(
                "INSERT INTO customer_price_history
                    (
                        customer_id,
                        effective_date,
                        price_per_gallon,
                        reason
                    )

                 VALUES (?, ?, ?, ?)"
            );

            $stmt->execute([
                $customerId,

                $effectiveDate !== ''
                    ? $effectiveDate
                    : date('Y-m-d'),

                $price,

                $reason !== ''
                    ? $reason
                    : 'Initial price'
            ]);
        }

        $pdo->commit();

    } catch (Throwable $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }

    respond([
        'success' => true,
        'customer' =>
            getCustomer(
                $pdo,
                $customerId
            )
    ]);
}

/*
|--------------------------------------------------------------------------
| Action: Delete Customer
|--------------------------------------------------------------------------
*/

function deleteCustomer(PDO $pdo): void
{
    $customerId =
        postInt('customer_id');

    if ($customerId <= 0) {

        respond([
            'success' => false,
            'message' =>
                'Invalid customer.'
        ], 400);
    }

    /*
     * We never delete customers that already have
     * transaction history.
     */

    $hasDeliveries = false;

    if (tableExists($pdo, 'deliveries')) {

        $stmt = $pdo->prepare(
            "SELECT COUNT(*)

             FROM deliveries

             WHERE customer_id = ?"
        );

        $stmt->execute([
            $customerId
        ]);

        $hasDeliveries =
            (int)$stmt->fetchColumn() > 0;
    }

    $hasPayments = false;

    if (tableExists($pdo, 'payments')) {

        $stmt = $pdo->prepare(
            "SELECT COUNT(*)

             FROM payments

             WHERE customer_id = ?"
        );

        $stmt->execute([
            $customerId
        ]);

        $hasPayments =
            (int)$stmt->fetchColumn() > 0;
    }

    if (
        $hasDeliveries ||
        $hasPayments
    ) {

        $stmt = $pdo->prepare(
            "UPDATE customers

             SET status = 'Inactive'

             WHERE customer_id = ?"
        );

        $stmt->execute([
            $customerId
        ]);

        respond([
            'success' => true,
            'mode' => 'inactive',
            'message' =>
                'This customer has transaction history, so the record was marked Inactive instead of being deleted.'
        ]);
    }

    /*
     * Customer has no transaction history,
     * so a real delete is allowed.
     */

    $stmt = $pdo->prepare(
        "DELETE FROM customers

         WHERE customer_id = ?"
    );

    $stmt->execute([
        $customerId
    ]);

    respond([
        'success' => true,
        'mode' => 'deleted'
    ]);
}

/*
|--------------------------------------------------------------------------
| Action: Move Customer
|--------------------------------------------------------------------------
*/

function moveCustomer(PDO $pdo): void
{
    $customerId =
        postInt('customer_id');

    $folderId =
        postInt('folder_id');

    if ($customerId <= 0) {

        respond([
            'success' => false,
            'message' =>
                'Invalid customer.'
        ], 400);
    }

    /*
     * Validate target folder.
     */

    if ($folderId > 0) {

        $stmt = $pdo->prepare(
            "SELECT folder_id

             FROM customer_folders

             WHERE folder_id = ?

             LIMIT 1"
        );

        $stmt->execute([
            $folderId
        ]);

        if (!$stmt->fetch()) {

            respond([
                'success' => false,
                'message' =>
                    'Folder not found.'
            ], 404);
        }
    }

    $stmt = $pdo->prepare(
        "UPDATE customers

         SET folder_id = ?

         WHERE customer_id = ?"
    );

    $stmt->execute([
        $folderId > 0
            ? $folderId
            : null,

        $customerId
    ]);

    respond([
        'success' => true
    ]);
}

/*
|--------------------------------------------------------------------------
| Action: Customer Profile
|--------------------------------------------------------------------------
*/

function customerProfile(PDO $pdo): void
{
    $customerId =
        postInt('customer_id');

    if ($customerId <= 0) {

        respond([
            'success' => false,
            'message' =>
                'Invalid customer.'
        ], 400);
    }

    $customer =
        getCustomer(
            $pdo,
            $customerId
        );

    if (!$customer) {

        respond([
            'success' => false,
            'message' =>
                'Customer not found.'
        ], 404);
    }

    /*
     * Folder path.
     */

    $customer['folder_path'] =
        getFolderPath(
            $pdo,
            $customer['folder_id']
                ? (int)$customer['folder_id']
                : null
        );

    /*
     * Order history.
     */

    $orders = [];

    if (tableExists($pdo, 'deliveries')) {

        $stmt = $pdo->prepare(
            "SELECT

                d.delivery_id,
                d.daily_id,
                d.delivery_date,
                d.slim_quantity,
                d.round_quantity,
                d.price_per_gallon,
                d.amount_due,

                COALESCE(
                    SUM(p.amount),
                    0
                ) AS paid

             FROM deliveries d

             LEFT JOIN payments p
                ON p.delivery_id =
                   d.delivery_id

             WHERE d.customer_id = ?

             GROUP BY
                d.delivery_id,
                d.daily_id,
                d.delivery_date,
                d.slim_quantity,
                d.round_quantity,
                d.price_per_gallon,
                d.amount_due

             ORDER BY
                d.delivery_date DESC,
                d.delivery_id DESC"
        );

        $stmt->execute([
            $customerId
        ]);

        $orders =
            $stmt->fetchAll(
                PDO::FETCH_ASSOC
            );
    }

    foreach ($orders as &$order) {

        $order['remaining'] =
            round(
                max(
                    (float)$order['amount_due']
                    -
                    (float)$order['paid'],
                    0
                ),
                2
            );
    }

    unset($order);

    /*
     * Price history.
     */

    $stmt = $pdo->prepare(
        "SELECT

            price_history_id,
            effective_date,
            price_per_gallon,
            reason

         FROM customer_price_history

         WHERE customer_id = ?

         ORDER BY
            effective_date DESC,
            price_history_id DESC"
    );

    $stmt->execute([
        $customerId
    ]);

    $priceHistory =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );

    /*
     * Payment history.
     */

    $payments = [];

    if (tableExists($pdo, 'payments')) {

        $stmt = $pdo->prepare(
            "SELECT

                payment_id,
                delivery_id,
                payment_date,
                amount,
                payment_method,
                collection_location,
                notes

             FROM payments

             WHERE customer_id = ?

             ORDER BY
                payment_date DESC,
                payment_id DESC"
        );

        $stmt->execute([
            $customerId
        ]);

        $payments =
            $stmt->fetchAll(
                PDO::FETCH_ASSOC
            );
    }

    /*
     * Credit history.
     */

    $credits = [];

    if (
        tableExists(
            $pdo,
            'customer_credits'
        )
    ) {

        $stmt = $pdo->prepare(
            "SELECT

                credit_id,
                credit_date,
                amount,
                payment_method,
                collection_location,
                daily_id,
                notes

             FROM customer_credits

             WHERE customer_id = ?

             ORDER BY
                credit_date DESC,
                credit_id DESC"
        );

        $stmt->execute([
            $customerId
        ]);

        $credits =
            $stmt->fetchAll(
                PDO::FETCH_ASSOC
            );
    }

    /*
     * Calculate current debt.
     */

    $debt = 0.00;

    foreach ($orders as $order) {

        $debt +=
            (float)$order['remaining'];
    }

    /*
     * Calculate credits.
     */

    $credit = 0.00;

    foreach ($credits as $entry) {

        $credit +=
            (float)$entry['amount'];
    }

    respond([
        'success' => true,

        'customer' =>
            $customer,

        'orders' =>
            $orders,

        'payments' =>
            $payments,

        'price_history' =>
            $priceHistory,

        'credits' =>
            $credits,

        'summary' => [
            'order_count' =>
                count($orders),

            'debt' =>
                round(
                    max($debt, 0),
                    2
                ),

            'credit' =>
                round(
                    $credit,
                    2
                )
        ]
    ]);
}

/*
|--------------------------------------------------------------------------
| Main Request
|--------------------------------------------------------------------------
*/

try {

    /*
     * Make sure the customer-related tables/columns
     * exist before processing the request.
     */

    ensureSchema($pdo);

    $action =
        postString('action');

    switch ($action) {

        case 'list':

            listCustomers($pdo);

            break;

        case 'folders':

            listFolders($pdo);

            break;

        case 'save_folder':

            saveFolder($pdo);

            break;

        case 'delete_folder':

            deleteFolder($pdo);

            break;

        case 'save_customer':

            saveCustomer($pdo);

            break;

        case 'delete_customer':

            deleteCustomer($pdo);

            break;

        case 'move_customer':

            moveCustomer($pdo);

            break;

        case 'profile':

            customerProfile($pdo);

            break;

        default:

            respond([
                'success' => false,
                'message' =>
                    'Unknown action.'
            ], 400);
    }

} catch (Throwable $e) {

    if (
        isset($pdo) &&
        $pdo instanceof PDO &&
        $pdo->inTransaction()
    ) {
        $pdo->rollBack();
    }

    respond([
        'success' => false,

        'message' =>
            $e->getMessage(),

        'error_type' =>
            get_class($e)
    ], 500);
}