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
     * Price history.
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

         ORDER BY setting_group, setting_key"
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


    respond([
        'success' => true,

        'settings' =>
            $settings,

        'users' =>
            $users,

        'walk_in_price_history' =>
            $priceHistory
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
     * Add a history record only when the price actually
     * changes or when the effective date is new.
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
| Add User
|--------------------------------------------------------------------------
*/

function actionAddUser(PDO $pdo): void
{
    if (!tableExists($pdo, 'users')) {

        respond([
            'success' => false,
            'message' =>
                'Users table does not exist.'
        ], 500);
    }


    $username =
        postString('username');

    $password =
        (string)($_POST['password'] ?? '');

    $role =
        postString(
            'role',
            'Staff'
        );

    $name =
        postString('name');


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
            ['Admin', 'Staff'],
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
     * Build INSERT dynamically so this remains compatible
     * with the existing users table.
     */

    $columns = [
        'username'
    ];

    $values = [
        $username
    ];


    /*
     * Existing authentication should use password_hash().
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

        $columns[] = 'password';
        $values[] = $passwordHash;

    } elseif (
        columnExists(
            $pdo,
            'users',
            'password_hash'
        )
    ) {

        $columns[] = 'password_hash';
        $values[] = $passwordHash;

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

        $columns[] = 'role';
        $values[] = $role;
    }


    if (
        columnExists(
            $pdo,
            'users',
            'is_active'
        )
    ) {

        $columns[] = 'is_active';
        $values[] = 1;
    }


    if (
        $name !== '' &&
        columnExists(
            $pdo,
            'users',
            'full_name'
        )
    ) {

        $columns[] = 'full_name';
        $values[] = $name;

    } elseif (
        $name !== '' &&
        columnExists(
            $pdo,
            'users',
            'name'
        )
    ) {

        $columns[] = 'name';
        $values[] = $name;
    }


    if (
        columnExists(
            $pdo,
            'users',
            'created_at'
        )
    ) {

        $columns[] = 'created_at';

        $values[] =
            date('Y-m-d H:i:s');
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
        $pdo->prepare($sql);

    $stmt->execute($values);


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
        postInt('user_id');


    if ($userId <= 0) {

        respond([
            'success' => false,
            'message' =>
                'Invalid user.'
        ], 400);
    }


    $username =
        postString('username');

    $password =
        (string)($_POST['password'] ?? '');

    $role =
        postString(
            'role',
            'Staff'
        );

    $name =
        postString('name');

    $isActive =
        isset($_POST['is_active'])
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
            ['Admin', 'Staff'],
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

        $sets[] = 'username = ?';
        $values[] = $username;
    }


    if (
        columnExists(
            $pdo,
            'users',
            'role'
        )
    ) {

        $sets[] = 'role = ?';
        $values[] = $role;
    }


    if (
        columnExists(
            $pdo,
            'users',
            'is_active'
        )
    ) {

        $sets[] = 'is_active = ?';
        $values[] = $isActive;
    }


    if (
        columnExists(
            $pdo,
            'users',
            'full_name'
        )
    ) {

        $sets[] = 'full_name = ?';
        $values[] = $name;

    } elseif (
        columnExists(
            $pdo,
            'users',
            'name'
        )
    ) {

        $sets[] = 'name = ?';
        $values[] = $name;
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
| Delete User
|--------------------------------------------------------------------------
|
| We don't actually delete the account.
| We deactivate it instead.
|
*/

function actionDeactivateUser(PDO $pdo): void
{
    $userId =
        postInt('user_id');


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
        postInt('user_id');


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