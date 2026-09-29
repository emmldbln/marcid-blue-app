<?php

declare(strict_types=1);

date_default_timezone_set('Asia/Manila');

require_once '../../auth/auth.php';
require_once '../../config/database.php';

requireAdmin();

header('Content-Type: application/json; charset=utf-8');


/*
|--------------------------------------------------------------------------
| Response Helpers
|--------------------------------------------------------------------------
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


function postString(
    string $key,
    string $default = ''
): string {
    return trim(
        (string)($_POST[$key] ?? $default)
    );
}


function postInt(
    string $key,
    int $default = 0
): int {
    return (int)($_POST[$key] ?? $default);
}


function tableExists(
    PDO $pdo,
    string $table
): bool {

    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM information_schema.tables
         WHERE table_schema = DATABASE()
           AND table_name = ?"
    );

    $stmt->execute([$table]);

    return (int)$stmt->fetchColumn() > 0;
}


function columnExists(
    PDO $pdo,
    string $table,
    string $column
): bool {

    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM information_schema.columns
         WHERE table_schema = DATABASE()
           AND table_name = ?
           AND column_name = ?"
    );

    $stmt->execute([
        $table,
        $column
    ]);

    return (int)$stmt->fetchColumn() > 0;
}


/*
|--------------------------------------------------------------------------
| Settings Schema
|--------------------------------------------------------------------------
*/

function ensureSettingsSchema(PDO $pdo): void
{
    /*
     * Main application settings.
     */

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS app_settings (

            setting_id INT UNSIGNED NOT NULL AUTO_INCREMENT,

            setting_key VARCHAR(100) NOT NULL,

            setting_value TEXT NULL,

            setting_group VARCHAR(50) NOT NULL DEFAULT 'general',

            updated_at DATETIME NOT NULL
                DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP,

            PRIMARY KEY (setting_id),

            UNIQUE KEY unique_setting_key
                (setting_key)

        ) ENGINE=InnoDB
          DEFAULT CHARSET=utf8mb4
          COLLATE=utf8mb4_unicode_ci"
    );


    /*
     * Walk-in price history.
     *
     * This is intentionally separate from app_settings
     * so historical transactions remain understandable
     * when the current walk-in price changes.
     */

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS walk_in_price_history (

            price_history_id INT UNSIGNED NOT NULL AUTO_INCREMENT,

            price DECIMAL(10,2) NOT NULL,

            effective_date DATE NOT NULL,

            created_at DATETIME NOT NULL
                DEFAULT CURRENT_TIMESTAMP,

            PRIMARY KEY (price_history_id),

            INDEX idx_walk_in_effective_date
                (effective_date)

        ) ENGINE=InnoDB
          DEFAULT CHARSET=utf8mb4
          COLLATE=utf8mb4_unicode_ci"
    );


    /*
     * Seed the default walk-in price only if no
     * setting exists yet.
     */

    $stmt = $pdo->prepare(
        "SELECT setting_value
         FROM app_settings
         WHERE setting_key = ?
         LIMIT 1"
    );

    $stmt->execute([
        'walk_in_price'
    ]);

    $existing =
        $stmt->fetchColumn();


    if ($existing === false) {

        $insert = $pdo->prepare(
            "INSERT INTO app_settings
                (
                    setting_key,
                    setting_value,
                    setting_group
                )
             VALUES
                (?, ?, ?)"
        );

        $insert->execute([
            'walk_in_price',
            '30.00',
            'pricing'
        ]);
    }


    /*
     * Seed the first price-history record if needed.
     */

    $count =
        (int)$pdo->query(
            "SELECT COUNT(*)
             FROM walk_in_price_history"
        )->fetchColumn();


    if ($count === 0) {

        $insert = $pdo->prepare(
            "INSERT INTO walk_in_price_history
                (
                    price,
                    effective_date
                )
             VALUES
                (?, ?)"
        );

        $insert->execute([
            30.00,
            date('Y-m-d')
        ]);
    }


    /*
     * Payment methods.
     *
     * These are configurable from Settings.
     * Existing transaction records continue to store
     * their original payment method text.
     */

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS payment_methods (

            method_id INT UNSIGNED NOT NULL AUTO_INCREMENT,

            method_name VARCHAR(100) NOT NULL,

            is_active TINYINT(1) NOT NULL DEFAULT 1,

            sort_order INT NOT NULL DEFAULT 0,

            created_at DATETIME NOT NULL
                DEFAULT CURRENT_TIMESTAMP,

            updated_at DATETIME NOT NULL
                DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP,

            PRIMARY KEY (method_id),

            UNIQUE KEY unique_payment_method_name
                (method_name),

            INDEX idx_payment_method_active
                (is_active),

            INDEX idx_payment_method_sort
                (sort_order)

        ) ENGINE=InnoDB
          DEFAULT CHARSET=utf8mb4
          COLLATE=utf8mb4_unicode_ci"
    );


    /*
     * Seed the original Marcid Blue payment methods.
     *
     * INSERT IGNORE prevents duplicates when the backend
     * is loaded multiple times.
     */

    $seedPaymentMethods = [
        [
            'Cash',
            1
        ],
        [
            'GCash',
            2
        ],
        [
            'Bank Transfer',
            3
        ],
        [
            'Other',
            4
        ]
    ];


    $insertPaymentMethod = $pdo->prepare(
        "INSERT IGNORE INTO payment_methods
            (
                method_name,
                is_active,
                sort_order
            )
         VALUES
            (?, 1, ?)"
    );


    foreach ($seedPaymentMethods as $paymentMethod) {

        $insertPaymentMethod->execute([
            $paymentMethod[0],
            $paymentMethod[1]
        ]);
    }
}


/*
|--------------------------------------------------------------------------
| Get Settings
|--------------------------------------------------------------------------
*/

function actionGetSettings(PDO $pdo): void
{
    ensureSettingsSchema($pdo);


    /*
     * Current application settings.
     */

    $stmt = $pdo->query(
        "SELECT
            setting_key,
            setting_value,
            setting_group

         FROM app_settings

         ORDER BY
            setting_group,
            setting_key"
    );


    $settings = [];

    foreach (
        $stmt->fetchAll(PDO::FETCH_ASSOC)
        as $row
    ) {

        $settings[
            $row['setting_key']
        ] = [
            'value' =>
                $row['setting_value'],

            'group' =>
                $row['setting_group']
        ];
    }


    /*
     * User accounts.
     */

    $users = [];

    if (tableExists($pdo, 'users')) {

        $columns = [
            'user_id',
            'username',
            'role',
            'is_active'
        ];

        $select = [];

        foreach ($columns as $column) {

            if (
                columnExists(
                    $pdo,
                    'users',
                    $column
                )
            ) {
                $select[] = $column;
            }
        }


        /*
         * Optional display-name columns.
         */

        if (
            columnExists(
                $pdo,
                'users',
                'full_name'
            )
        ) {

            $select[] = 'full_name';

        } elseif (
            columnExists(
                $pdo,
                'users',
                'name'
            )
        ) {

            $select[] = 'name';
        }


        if (
            columnExists(
                $pdo,
                'users',
                'created_at'
            )
        ) {

            $select[] = 'created_at';
        }


        if (
            columnExists(
                $pdo,
                'users',
                'last_login'
            )
        ) {

            $select[] = 'last_login';
        }


        if (
            !empty($select) &&
            in_array(
                'user_id',
                $select,
                true
            )
        ) {

            $stmt = $pdo->query(
                "SELECT
                    " .
                    implode(
                        ',',
                        $select
                    ) .
                "
                 FROM users
                 ORDER BY user_id ASC"
            );

            $users =
                $stmt->fetchAll(
                    PDO::FETCH_ASSOC
                );
        }
    }


    /*
     * Walk-in price history.
     */

    $stmt = $pdo->query(
        "SELECT
            price_history_id,
            price,
            effective_date,
            created_at

         FROM walk_in_price_history

         ORDER BY
            effective_date DESC,
            price_history_id DESC"
    );


    $priceHistory =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );


    /*
     * Payment methods.
     */

    $paymentMethods = [];

    if (
        tableExists(
            $pdo,
            'payment_methods'
        )
    ) {

        $stmt = $pdo->query(
            "SELECT
                method_id,
                method_name,
                is_active,
                sort_order,
                created_at,
                updated_at

             FROM payment_methods

             ORDER BY
                sort_order ASC,
                method_id ASC"
        );


        $paymentMethods =
            $stmt->fetchAll(
                PDO::FETCH_ASSOC
            );
    }


    respond([
        'success' => true,

        'settings' =>
            $settings,

        'users' =>
            $users,

        'walk_in_price_history' =>
            $priceHistory,

        'payment_methods' =>
            $paymentMethods
    ]);
}


/*
|--------------------------------------------------------------------------
| Save Walk-in Price
|--------------------------------------------------------------------------
*/

function actionSaveWalkInPrice(PDO $pdo): void
{
    ensureSettingsSchema($pdo);


    $price =
        (float)($_POST['price'] ?? 0);


    $effectiveDate =
        postString(
            'effective_date',
            date('Y-m-d')
        );


    if ($price <= 0) {

        respond([
            'success' => false,
            'message' =>
                'Walk-in price must be greater than zero.'
        ], 400);
    }


    $dateObject =
        DateTime::createFromFormat(
            'Y-m-d',
            $effectiveDate
        );


    if (
        !$dateObject ||
        $dateObject->format('Y-m-d') !==
        $effectiveDate
    ) {

        respond([
            'success' => false,
            'message' =>
                'Invalid effective date.'
        ], 400);
    }


    /*
     * Get current price.
     */

    $stmt = $pdo->prepare(
        "SELECT setting_value
         FROM app_settings
         WHERE setting_key = ?
         LIMIT 1"
    );


    $stmt->execute([
        'walk_in_price'
    ]);


    $oldPrice =
        (float)$stmt->fetchColumn();


    /*
     * Update current price.
     */

    $stmt = $pdo->prepare(
        "INSERT INTO app_settings
            (
                setting_key,
                setting_value,
                setting_group
            )
         VALUES
            (?, ?, ?)

         ON DUPLICATE KEY UPDATE

            setting_value =
                VALUES(setting_value),

            setting_group =
                VALUES(setting_group)"
    );


    $stmt->execute([
        'walk_in_price',

        number_format(
            $price,
            2,
            '.',
            ''
        ),

        'pricing'
    ]);


    /*
     * Add a history record only when the same
     * price/effective-date combination does not
     * already exist.
     */

    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM walk_in_price_history
         WHERE price = ?
           AND effective_date = ?"
    );


    $stmt->execute([
        $price,
        $effectiveDate
    ]);


    $exists =
        (int)$stmt->fetchColumn() > 0;


    if (!$exists) {

        $stmt = $pdo->prepare(
            "INSERT INTO walk_in_price_history
                (
                    price,
                    effective_date
                )
             VALUES
                (?, ?)"
        );


        $stmt->execute([
            $price,
            $effectiveDate
        ]);
    }


    respond([
        'success' => true,

        'message' =>
            'Walk-in price updated successfully.',

        'old_price' =>
            number_format(
                $oldPrice,
                2,
                '.',
                ''
            ),

        'new_price' =>
            number_format(
                $price,
                2,
                '.',
                ''
            )
    ]);
}


/*
|--------------------------------------------------------------------------
| Add Payment Method
|--------------------------------------------------------------------------
*/

function actionAddPaymentMethod(PDO $pdo): void
{
    ensureSettingsSchema($pdo);


    if (
        !tableExists(
            $pdo,
            'payment_methods'
        )
    ) {

        respond([
            'success' => false,
            'message' =>
                'Payment methods table does not exist.'
        ], 500);
    }


    $methodName =
        postString(
            'method_name'
        );


    if ($methodName === '') {

        respond([
            'success' => false,
            'message' =>
                'Payment method name is required.'
        ], 400);
    }


    if (strlen($methodName) > 100) {

        respond([
            'success' => false,
            'message' =>
                'Payment method name is too long.'
        ], 400);
    }


    /*
     * Prevent duplicate names.
     */

    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM payment_methods
         WHERE LOWER(method_name) = LOWER(?)"
    );


    $stmt->execute([
        $methodName
    ]);


    if (
        (int)$stmt->fetchColumn() > 0
    ) {

        respond([
            'success' => false,
            'message' =>
                'That payment method already exists.'
        ], 400);
    }


    /*
     * Put the new method after the current
     * last payment method.
     */

    $stmt = $pdo->query(
        "SELECT COALESCE(
            MAX(sort_order),
            0
        )
        FROM payment_methods"
    );


    $sortOrder =
        (int)$stmt->fetchColumn() + 1;


    $stmt = $pdo->prepare(
        "INSERT INTO payment_methods
            (
                method_name,
                is_active,
                sort_order
            )
         VALUES
            (?, 1, ?)"
    );


    $stmt->execute([
        $methodName,
        $sortOrder
    ]);


    respond([
        'success' => true,

        'message' =>
            'Payment method added successfully.'
    ]);
}


/*
|--------------------------------------------------------------------------
| Update Payment Method
|--------------------------------------------------------------------------
*/

function actionUpdatePaymentMethod(PDO $pdo): void
{
    $methodId =
        postInt(
            'method_id'
        );


    $methodName =
        postString(
            'method_name'
        );


    if ($methodId <= 0) {

        respond([
            'success' => false,
            'message' =>
                'Invalid payment method.'
        ], 400);
    }


    if ($methodName === '') {

        respond([
            'success' => false,
            'message' =>
                'Payment method name is required.'
        ], 400);
    }


    if (strlen($methodName) > 100) {

        respond([
            'success' => false,
            'message' =>
                'Payment method name is too long.'
        ], 400);
    }


    /*
     * Check that the method exists.
     */

    $stmt = $pdo->prepare(
        "SELECT
            method_id
         FROM payment_methods
         WHERE method_id = ?
         LIMIT 1"
    );


    $stmt->execute([
        $methodId
    ]);


    if (!$stmt->fetchColumn()) {

        respond([
            'success' => false,
            'message' =>
                'Payment method not found.'
        ], 404);
    }


    /*
     * Prevent duplicate names.
     */

    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM payment_methods
         WHERE LOWER(method_name) = LOWER(?)
           AND method_id <> ?"
    );


    $stmt->execute([
        $methodName,
        $methodId
    ]);


    if (
        (int)$stmt->fetchColumn() > 0
    ) {

        respond([
            'success' => false,
            'message' =>
                'That payment method already exists.'
        ], 400);
    }


    $stmt = $pdo->prepare(
        "UPDATE payment_methods
         SET method_name = ?
         WHERE method_id = ?"
    );


    $stmt->execute([
        $methodName,
        $methodId
    ]);


    respond([
        'success' => true,

        'message' =>
            'Payment method updated successfully.'
    ]);
}


/*
|--------------------------------------------------------------------------
| Change Payment Method Status
|--------------------------------------------------------------------------
*/

function actionChangePaymentMethodStatus(
    PDO $pdo
): void {

    $methodId =
        postInt(
            'method_id'
        );


    $activate =
        (int)(
            $_POST['activate'] ?? 0
        );


    if ($methodId <= 0) {

        respond([
            'success' => false,
            'message' =>
                'Invalid payment method.'
        ], 400);
    }


    /*
     * Make sure the payment method exists.
     */

    $stmt = $pdo->prepare(
        "SELECT
            method_id,
            is_active
         FROM payment_methods
         WHERE method_id = ?
         LIMIT 1"
    );


    $stmt->execute([
        $methodId
    ]);


    $method =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );


    if (!$method) {

        respond([
            'success' => false,
            'message' =>
                'Payment method not found.'
        ], 404);
    }


    /*
     * Do not allow all payment methods to be
     * deactivated.
     *
     * At least one active payment method must
     * always remain available.
     */

    if ($activate !== 1) {

        $stmt = $pdo->query(
            "SELECT COUNT(*)
             FROM payment_methods
             WHERE is_active = 1"
        );


        $activeCount =
            (int)$stmt->fetchColumn();


        if (
            (int)$method['is_active'] === 1 &&
            $activeCount <= 1
        ) {

            respond([
                'success' => false,
                'message' =>
                    'At least one payment method must remain active.'
            ], 400);
        }
    }


    $stmt = $pdo->prepare(
        "UPDATE payment_methods
         SET is_active = ?
         WHERE method_id = ?"
    );


    $stmt->execute([
        $activate === 1 ? 1 : 0,
        $methodId
    ]);


    respond([
        'success' => true,

        'message' =>
            $activate === 1
                ? 'Payment method activated.'
                : 'Payment method deactivated.'
    ]);
}


/*
|--------------------------------------------------------------------------
| Add User
|--------------------------------------------------------------------------
*/

function actionAddUser(PDO $pdo): void
{
    if (
        !tableExists(
            $pdo,
            'users'
        )
    ) {

        respond([
            'success' => false,
            'message' =>
                'Users table does not exist.'
        ], 500);
    }


    $username =
        postString(
            'username'
        );


    $password =
        (string)(
            $_POST['password'] ?? ''
        );


    $role =
        postString(
            'role',
            'Staff'
        );


    $name =
        postString(
            'name'
        );


    if ($username === '') {

        respond([
            'success' => false,
            'message' =>
                'Username is required.'
        ], 400);
    }


    if (strlen($password) < 6) {

        respond([
            'success' => false,
            'message' =>
                'Password must contain at least 6 characters.'
        ], 400);
    }


    if (
        !in_array(
            $role,
            [
                'Admin',
                'Staff'
            ],
            true
        )
    ) {

        respond([
            'success' => false,
            'message' =>
                'Invalid user role.'
        ], 400);
    }


    /*
     * Check username.
     */

    if (
        !columnExists(
            $pdo,
            'users',
            'username'
        )
    ) {

        respond([
            'success' => false,
            'message' =>
                'The users table does not have a username column.'
        ], 500);
    }


    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM users
         WHERE username = ?"
    );


    $stmt->execute([
        $username
    ]);


    if (
        (int)$stmt->fetchColumn() > 0
    ) {

        respond([
            'success' => false,
            'message' =>
                'That username is already in use.'
        ], 400);
    }


    /*
     * Build INSERT dynamically so this remains
     * compatible with the existing users table.
     */

    $columns = [
        'username'
    ];


    $values = [
        $username
    ];


    /*
     * Existing authentication should use
     * password_hash().
     */

    $passwordHash =
        password_hash(
            $password,
            PASSWORD_DEFAULT
        );


    if (
        columnExists(
            $pdo,
            'users',
            'password'
        )
    ) {

        $columns[] =
            'password';

        $values[] =
            $passwordHash;

    } elseif (
        columnExists(
            $pdo,
            'users',
            'password_hash'
        )
    ) {

        $columns[] =
            'password_hash';

        $values[] =
            $passwordHash;

    } else {

        respond([
            'success' => false,
            'message' =>
                'The users table has no password field.'
        ], 500);
    }


    if (
        columnExists(
            $pdo,
            'users',
            'role'
        )
    ) {

        $columns[] =
            'role';

        $values[] =
            $role;
    }


    if (
        columnExists(
            $pdo,
            'users',
            'is_active'
        )
    ) {

        $columns[] =
            'is_active';

        $values[] =
            1;
    }


    if (
        $name !== '' &&
        columnExists(
            $pdo,
            'users',
            'full_name'
        )
    ) {

        $columns[] =
            'full_name';

        $values[] =
            $name;

    } elseif (
        $name !== '' &&
        columnExists(
            $pdo,
            'users',
            'name'
        )
    ) {

        $columns[] =
            'name';

        $values[] =
            $name;
    }


    if (
        columnExists(
            $pdo,
            'users',
            'created_at'
        )
    ) {

        $columns[] =
            'created_at';

        $values[] =
            date(
                'Y-m-d H:i:s'
            );
    }


    $placeholders =
        implode(
            ',',
            array_fill(
                0,
                count($values),
                '?'
            )
        );


    $sql =
        "INSERT INTO users
            (" .
            implode(
                ',',
                $columns
            ) .
        ")
         VALUES
            (" .
            $placeholders .
        ")";


    $stmt =
        $pdo->prepare(
            $sql
        );


    $stmt->execute(
        $values
    );


    respond([
        'success' => true,

        'message' =>
            'User created successfully.'
    ]);
}


/*
|--------------------------------------------------------------------------
| Update User
|--------------------------------------------------------------------------
*/

function actionUpdateUser(PDO $pdo): void
{
    $userId =
        postInt(
            'user_id'
        );


    if ($userId <= 0) {

        respond([
            'success' => false,
            'message' =>
                'Invalid user.'
        ], 400);
    }


    $username =
        postString(
            'username'
        );


    $password =
        (string)(
            $_POST['password'] ?? ''
        );


    $role =
        postString(
            'role',
            'Staff'
        );


    $name =
        postString(
            'name'
        );


    $isActive =
        isset(
            $_POST['is_active']
        )
            ? (int)$_POST['is_active']
            : 1;


    if ($username === '') {

        respond([
            'success' => false,
            'message' =>
                'Username is required.'
        ], 400);
    }


    if (
        !in_array(
            $role,
            [
                'Admin',
                'Staff'
            ],
            true
        )
    ) {

        respond([
            'success' => false,
            'message' =>
                'Invalid user role.'
        ], 400);
    }


    /*
     * Prevent duplicate username.
     */

    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM users
         WHERE username = ?
           AND user_id <> ?"
    );


    $stmt->execute([
        $username,
        $userId
    ]);


    if (
        (int)$stmt->fetchColumn() > 0
    ) {

        respond([
            'success' => false,
            'message' =>
                'That username is already in use.'
        ], 400);
    }


    /*
     * Prevent disabling the last active Admin.
     */

    if (
        $role !== 'Admin' ||
        $isActive !== 1
    ) {

        $stmt = $pdo->prepare(
            "SELECT COUNT(*)
             FROM users
             WHERE role = 'Admin'
               AND is_active = 1
               AND user_id <> ?"
        );


        $stmt->execute([
            $userId
        ]);


        $otherAdmins =
            (int)$stmt->fetchColumn();


        $stmt = $pdo->prepare(
            "SELECT role
             FROM users
             WHERE user_id = ?
             LIMIT 1"
        );


        $stmt->execute([
            $userId
        ]);


        $currentRole =
            $stmt->fetchColumn();


        if (
            $currentRole === 'Admin' &&
            $otherAdmins <= 0
        ) {

            respond([
                'success' => false,
                'message' =>
                    'The last active Admin account cannot be disabled or changed to Staff.'
            ], 400);
        }
    }


    /*
     * Build dynamic update.
     */

    $sets = [];

    $values = [];


    if (
        columnExists(
            $pdo,
            'users',
            'username'
        )
    ) {

        $sets[] =
            'username = ?';

        $values[] =
            $username;
    }


    if (
        columnExists(
            $pdo,
            'users',
            'role'
        )
    ) {

        $sets[] =
            'role = ?';

        $values[] =
            $role;
    }


    if (
        columnExists(
            $pdo,
            'users',
            'is_active'
        )
    ) {

        $sets[] =
            'is_active = ?';

        $values[] =
            $isActive;
    }


    if (
        columnExists(
            $pdo,
            'users',
            'full_name'
        )
    ) {

        $sets[] =
            'full_name = ?';

        $values[] =
            $name;

    } elseif (
        columnExists(
            $pdo,
            'users',
            'name'
        )
    ) {

        $sets[] =
            'name = ?';

        $values[] =
            $name;
    }


    if ($password !== '') {

        if (strlen($password) < 6) {

            respond([
                'success' => false,
                'message' =>
                    'New password must contain at least 6 characters.'
            ], 400);
        }


        $hash =
            password_hash(
                $password,
                PASSWORD_DEFAULT
            );


        if (
            columnExists(
                $pdo,
                'users',
                'password'
            )
        ) {

            $sets[] =
                'password = ?';

            $values[] =
                $hash;

        } elseif (
            columnExists(
                $pdo,
                'users',
                'password_hash'
            )
        ) {

            $sets[] =
                'password_hash = ?';

            $values[] =
                $hash;
        }
    }


    if (empty($sets)) {

        respond([
            'success' => false,
            'message' =>
                'Nothing to update.'
        ], 400);
    }


    $values[] =
        $userId;


    $stmt = $pdo->prepare(
        "UPDATE users
         SET " .
            implode(
                ',',
                $sets
            ) .
        "
         WHERE user_id = ?"
    );


    $stmt->execute(
        $values
    );


    respond([
        'success' => true,

        'message' =>
            'User updated successfully.'
    ]);
}


/*
|--------------------------------------------------------------------------
| Deactivate User
|--------------------------------------------------------------------------
|
| We don't actually delete the account.
| We deactivate it instead.
|
*/

function actionDeactivateUser(PDO $pdo): void
{
    $userId =
        postInt(
            'user_id'
        );


    if ($userId <= 0) {

        respond([
            'success' => false,
            'message' =>
                'Invalid user.'
        ], 400);
    }


    /*
     * Make sure another active Admin exists.
     */

    $stmt = $pdo->prepare(
        "SELECT role
         FROM users
         WHERE user_id = ?
         LIMIT 1"
    );


    $stmt->execute([
        $userId
    ]);


    $role =
        $stmt->fetchColumn();


    if ($role === 'Admin') {

        $stmt = $pdo->query(
            "SELECT COUNT(*)
             FROM users
             WHERE role = 'Admin'
               AND is_active = 1"
        );


        $adminCount =
            (int)$stmt->fetchColumn();


        if ($adminCount <= 1) {

            respond([
                'success' => false,
                'message' =>
                    'The last active Admin account cannot be deactivated.'
            ], 400);
        }
    }


    $stmt = $pdo->prepare(
        "UPDATE users
         SET is_active = 0
         WHERE user_id = ?"
    );


    $stmt->execute([
        $userId
    ]);


    respond([
        'success' => true,

        'message' =>
            'User account deactivated.'
    ]);
}


/*
|--------------------------------------------------------------------------
| Reactivate User
|--------------------------------------------------------------------------
*/

function actionReactivateUser(PDO $pdo): void
{
    $userId =
        postInt(
            'user_id'
        );


    if ($userId <= 0) {

        respond([
            'success' => false,
            'message' =>
                'Invalid user.'
        ], 400);
    }


    $stmt = $pdo->prepare(
        "UPDATE users
         SET is_active = 1
         WHERE user_id = ?"
    );


    $stmt->execute([
        $userId
    ]);


    respond([
        'success' => true,

        'message' =>
            'User account activated.'
    ]);
}


/*
|--------------------------------------------------------------------------
| Main
|--------------------------------------------------------------------------
*/

try {

    $action =
        postString(
            'action',
            'get_settings'
        );


    switch ($action) {

        case 'get_settings':

            actionGetSettings($pdo);

            break;


        case 'save_walk_in_price':

            actionSaveWalkInPrice($pdo);

            break;


        /*
         * Payment Methods
         */

        case 'add_payment_method':

            actionAddPaymentMethod($pdo);

            break;


        case 'update_payment_method':

            actionUpdatePaymentMethod($pdo);

            break;


        case 'change_payment_method_status':

            actionChangePaymentMethodStatus($pdo);

            break;


        /*
         * Users
         */

        case 'add_user':

            actionAddUser($pdo);

            break;


        case 'update_user':

            actionUpdateUser($pdo);

            break;


        case 'deactivate_user':

            actionDeactivateUser($pdo);

            break;


        case 'reactivate_user':

            actionReactivateUser($pdo);

            break;


        default:

            respond([
                'success' => false,
                'message' =>
                    'Unknown settings action.'
            ], 400);
    }

} catch (Throwable $e) {

    respond([
        'success' => false,
        'message' =>
            $e->getMessage(),
        'error_type' =>
            get_class($e)
    ], 500);
}