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


/*
|--------------------------------------------------------------------------
| Employee Validation
|--------------------------------------------------------------------------
*/

function validateEmployeeData(
    string $fullName,
    string $position,
    float $dailyRate
): void {

    if ($fullName === '') {

        respond([
            'success' => false,
            'message' =>
                'Employee name is required.'
        ], 400);
    }


    if (strlen($fullName) > 150) {

        respond([
            'success' => false,
            'message' =>
                'Employee name is too long.'
        ], 400);
    }


    if ($position === '') {

        respond([
            'success' => false,
            'message' =>
                'Employee position is required.'
        ], 400);
    }


    if (strlen($position) > 100) {

        respond([
            'success' => false,
            'message' =>
                'Employee position is too long.'
        ], 400);
    }


    if ($dailyRate < 0) {

        respond([
            'success' => false,
            'message' =>
                'Daily rate cannot be negative.'
        ], 400);
    }


    if ($dailyRate > 99999999.99) {

        respond([
            'success' => false,
            'message' =>
                'Daily rate is too large.'
        ], 400);
    }
}


/*
|--------------------------------------------------------------------------
| Get Employees
|--------------------------------------------------------------------------
*/

function actionGetEmployees(PDO $pdo): void
{
    $stmt = $pdo->query(
        "SELECT
            employee_id,
            full_name,
            position,
            daily_rate,
            is_active,
            date_added,
            date_inactive,
            created_at,
            updated_at

         FROM employees

         ORDER BY
            is_active DESC,
            full_name ASC,
            employee_id ASC"
    );


    $employees =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );


    respond([
        'success' => true,
        'employees' => $employees
    ]);
}


/*
|--------------------------------------------------------------------------
| Add Employee
|--------------------------------------------------------------------------
*/

function actionAddEmployee(PDO $pdo): void
{
    $fullName =
        postString(
            'full_name'
        );


    $position =
        postString(
            'position'
        );


    $dailyRate =
        (float)(
            $_POST['daily_rate'] ?? 0
        );


    validateEmployeeData(
        $fullName,
        $position,
        $dailyRate
    );


    /*
     * Prevent an exact duplicate active employee
     * name from being accidentally created.
     *
     * Inactive employees are allowed to retain the
     * same name because they represent historical
     * employee records.
     */

    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM employees
         WHERE LOWER(full_name) = LOWER(?)
           AND is_active = 1"
    );


    $stmt->execute([
        $fullName
    ]);


    if (
        (int)$stmt->fetchColumn() > 0
    ) {

        respond([
            'success' => false,
            'message' =>
                'An active employee with that name already exists.'
        ], 400);
    }


    $dateAdded =
        postString(
            'date_added',
            date('Y-m-d')
        );


    /*
     * Validate date_added.
     */

    $dateObject =
        DateTime::createFromFormat(
            'Y-m-d',
            $dateAdded
        );


    if (
        !$dateObject ||
        $dateObject->format('Y-m-d') !==
        $dateAdded
    ) {

        respond([
            'success' => false,
            'message' =>
                'Invalid employee date.'
        ], 400);
    }


    /*
     * New employees are always active.
     */

    $stmt = $pdo->prepare(
        "INSERT INTO employees
            (
                full_name,
                position,
                daily_rate,
                is_active,
                date_added,
                date_inactive
            )
         VALUES
            (
                ?,
                ?,
                ?,
                1,
                ?,
                NULL
            )"
    );


    $stmt->execute([
        $fullName,
        $position,
        $dailyRate,
        $dateAdded
    ]);


    $employeeId =
        (int)$pdo->lastInsertId();


    respond([
        'success' => true,
        'message' =>
            'Employee added successfully.',
        'employee_id' =>
            $employeeId
    ]);
}


/*
|--------------------------------------------------------------------------
| Update Employee
|--------------------------------------------------------------------------
*/

function actionUpdateEmployee(PDO $pdo): void
{
    $employeeId =
        postInt(
            'employee_id'
        );


    if ($employeeId <= 0) {

        respond([
            'success' => false,
            'message' =>
                'Invalid employee.'
        ], 400);
    }


    $fullName =
        postString(
            'full_name'
        );


    $position =
        postString(
            'position'
        );


    $dailyRate =
        (float)(
            $_POST['daily_rate'] ?? 0
        );


    validateEmployeeData(
        $fullName,
        $position,
        $dailyRate
    );


    /*
     * Make sure the employee exists.
     */

    $stmt = $pdo->prepare(
        "SELECT
            employee_id,
            is_active
         FROM employees
         WHERE employee_id = ?
         LIMIT 1"
    );


    $stmt->execute([
        $employeeId
    ]);


    $employee =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );


    if (!$employee) {

        respond([
            'success' => false,
            'message' =>
                'Employee not found.'
        ], 404);
    }


    /*
     * Prevent duplicate active employee names.
     */

    if (
        (int)$employee['is_active'] === 1
    ) {

        $stmt = $pdo->prepare(
            "SELECT COUNT(*)
             FROM employees
             WHERE LOWER(full_name) = LOWER(?)
               AND is_active = 1
               AND employee_id <> ?"
        );


        $stmt->execute([
            $fullName,
            $employeeId
        ]);


        if (
            (int)$stmt->fetchColumn() > 0
        ) {

            respond([
                'success' => false,
                'message' =>
                    'An active employee with that name already exists.'
            ], 400);
        }
    }


    $stmt = $pdo->prepare(
        "UPDATE employees
         SET
            full_name = ?,
            position = ?,
            daily_rate = ?
         WHERE employee_id = ?"
    );


    $stmt->execute([
        $fullName,
        $position,
        $dailyRate,
        $employeeId
    ]);


    respond([
        'success' => true,
        'message' =>
            'Employee updated successfully.'
    ]);
}


/*
|--------------------------------------------------------------------------
| Deactivate Employee
|--------------------------------------------------------------------------
|
| Employees are never deleted.
|
| Historical payroll records will continue to reference
| the employee even after the employee becomes inactive.
|
*/

function actionDeactivateEmployee(PDO $pdo): void
{
    $employeeId =
        postInt(
            'employee_id'
        );


    if ($employeeId <= 0) {

        respond([
            'success' => false,
            'message' =>
                'Invalid employee.'
        ], 400);
    }


    /*
     * Make sure the employee exists.
     */

    $stmt = $pdo->prepare(
        "SELECT
            employee_id,
            is_active
         FROM employees
         WHERE employee_id = ?
         LIMIT 1"
    );


    $stmt->execute([
        $employeeId
    ]);


    $employee =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );


    if (!$employee) {

        respond([
            'success' => false,
            'message' =>
                'Employee not found.'
        ], 404);
    }


    if (
        (int)$employee['is_active'] === 0
    ) {

        respond([
            'success' => true,
            'message' =>
                'Employee is already inactive.'
        ]);
    }


    $today =
        date('Y-m-d');


    $stmt = $pdo->prepare(
        "UPDATE employees
         SET
            is_active = 0,
            date_inactive = ?
         WHERE employee_id = ?"
    );


    $stmt->execute([
        $today,
        $employeeId
    ]);


    respond([
        'success' => true,
        'message' =>
            'Employee deactivated successfully.'
    ]);
}


/*
|--------------------------------------------------------------------------
| Reactivate Employee
|--------------------------------------------------------------------------
*/

function actionReactivateEmployee(PDO $pdo): void
{
    $employeeId =
        postInt(
            'employee_id'
        );


    if ($employeeId <= 0) {

        respond([
            'success' => false,
            'message' =>
                'Invalid employee.'
        ], 400);
    }


    /*
     * Make sure the employee exists.
     */

    $stmt = $pdo->prepare(
        "SELECT
            employee_id,
            full_name,
            is_active
         FROM employees
         WHERE employee_id = ?
         LIMIT 1"
    );


    $stmt->execute([
        $employeeId
    ]);


    $employee =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );


    if (!$employee) {

        respond([
            'success' => false,
            'message' =>
                'Employee not found.'
        ], 404);
    }


    if (
        (int)$employee['is_active'] === 1
    ) {

        respond([
            'success' => true,
            'message' =>
                'Employee is already active.'
        ]);
    }


    /*
     * Do not allow two active employee records
     * with the exact same name.
     */

    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM employees
         WHERE LOWER(full_name) = LOWER(?)
           AND is_active = 1
           AND employee_id <> ?"
    );


    $stmt->execute([
        $employee['full_name'],
        $employeeId
    ]);


    if (
        (int)$stmt->fetchColumn() > 0
    ) {

        respond([
            'success' => false,
            'message' =>
                'Another active employee already uses this name.'
        ], 400);
    }


    $stmt = $pdo->prepare(
        "UPDATE employees
         SET
            is_active = 1,
            date_inactive = NULL
         WHERE employee_id = ?"
    );


    $stmt->execute([
        $employeeId
    ]);


    respond([
        'success' => true,
        'message' =>
            'Employee reactivated successfully.'
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
            'get_employees'
        );


    switch ($action) {

        case 'get_employees':

            actionGetEmployees($pdo);

            break;


        case 'add_employee':

            actionAddEmployee($pdo);

            break;


        case 'update_employee':

            actionUpdateEmployee($pdo);

            break;


        case 'deactivate_employee':

            actionDeactivateEmployee($pdo);

            break;


        case 'reactivate_employee':

            actionReactivateEmployee($pdo);

            break;


        default:

            respond([
                'success' => false,
                'message' =>
                    'Unknown payroll action.'
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