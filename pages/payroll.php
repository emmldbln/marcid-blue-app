<?php

declare(strict_types=1);

date_default_timezone_set('Asia/Manila');

require_once '../auth/auth.php';

requireAdmin();

$currentPage = 'payroll';

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Payroll | Marcid Blue
    </title>

    <link
        rel="stylesheet"
        href="../assets/css/app.css"
    >

    <style>

        /* =========================================================
           PAYROLL
        ========================================================= */

        .payroll-page {
            padding: 30px;
        }

        .payroll-header {
            margin-bottom: 28px;
        }

        .payroll-header h1 {
            margin: 0 0 6px;
            font-size: 28px;
            color: var(--text);
        }

        .payroll-header p {
            margin: 0;
            color: var(--text-muted);
            font-size: 14px;
        }


        /* =========================================================
           CARD
        ========================================================= */

        .payroll-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            box-shadow: var(--shadow-sm);
            overflow: hidden;
        }

        .payroll-card-header {
            padding: 18px 20px;
            border-bottom: 1px solid var(--border);
        }

        .payroll-card-header-flex {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
        }

        .payroll-card-header h2 {
            margin: 0 0 4px;
            font-size: 16px;
            color: var(--text);
        }

        .payroll-card-header p {
            margin: 0;
            font-size: 12px;
            color: var(--text-muted);
        }

        .payroll-card-body {
            padding: 20px;
        }


        /* =========================================================
           TABLE
        ========================================================= */

        .payroll-table-wrapper {
            overflow-x: auto;
        }

        .payroll-table {
            width: 100%;
            border-collapse: collapse;
        }

        .payroll-table th {
            padding: 11px 12px;
            text-align: left;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .04em;
            color: var(--text-muted);
            border-bottom: 1px solid var(--border);
            white-space: nowrap;
        }

        .payroll-table td {
            padding: 13px 12px;
            font-size: 13px;
            color: var(--text);
            border-bottom: 1px solid var(--border);
            vertical-align: middle;
        }

        .payroll-table tr:last-child td {
            border-bottom: 0;
        }


        /* =========================================================
           EMPLOYEE
        ========================================================= */

        .employee-name {
            font-weight: 600;
        }

        .employee-position {
            color: var(--text-muted);
        }


        /* =========================================================
           STATUS
        ========================================================= */

        .employee-status {
            display: inline-flex;
            align-items: center;
            padding: 4px 9px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
        }

        .employee-status.active {
            background: var(--secondary-light);
            color: var(--secondary-dark);
        }

        .employee-status.inactive {
            background: #f5f5f5;
            color: var(--text-muted);
        }


        /* =========================================================
           ACTIONS
        ========================================================= */

        .payroll-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 8px;
            flex-wrap: wrap;
        }

        .payroll-btn-small {
            padding: 7px 11px;
            font-size: 12px;
        }


        /* =========================================================
           EMPTY
        ========================================================= */

        .payroll-empty {
            padding: 30px;
            text-align: center;
            color: var(--text-muted);
            font-size: 13px;
        }


        /* =========================================================
           MODAL
        ========================================================= */

        .payroll-modal {
            position: fixed;
            inset: 0;

            display: none;
            align-items: center;
            justify-content: center;

            padding: 20px;

            background: rgba(15, 30, 40, .45);

            z-index: 1000;
        }

        .payroll-modal.show {
            display: flex;
        }

        .payroll-modal-card {
            width: 100%;
            max-width: 500px;

            max-height: calc(100vh - 40px);

            overflow-y: auto;

            background: var(--surface);

            border-radius: 12px;

            box-shadow: var(--shadow-md);
        }

        .payroll-modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;

            padding: 18px 20px;

            border-bottom: 1px solid var(--border);
        }

        .payroll-modal-header h3 {
            margin: 0;
            font-size: 17px;
        }

        .payroll-modal-description {
            margin: 4px 0 0;
            font-size: 12px;
            color: var(--text-muted);
        }

        .payroll-modal-close {
            width: 32px;
            height: 32px;

            border: 0;
            border-radius: 7px;

            background: transparent;

            color: var(--text-muted);

            font-size: 20px;

            cursor: pointer;
        }

        .payroll-modal-close:hover {
            background: var(--background);
            color: var(--text);
        }

        .payroll-modal-body {
            padding: 20px;
        }

        .payroll-modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 8px;

            padding: 15px 20px;

            border-top: 1px solid var(--border);
        }


        /* =========================================================
           FORM
        ========================================================= */

        .payroll-form-group {
            margin-bottom: 16px;
        }

        .payroll-form-group:last-child {
            margin-bottom: 0;
        }

        .payroll-form-label {
            display: block;
            margin-bottom: 7px;

            font-size: 12px;
            font-weight: 600;

            color: var(--text);
        }

        .payroll-form-input {
            width: 100%;

            height: 40px;

            padding: 0 11px;

            border: 1px solid var(--border);
            border-radius: 7px;

            background: var(--surface);
            color: var(--text);

            font-size: 13px;

            outline: none;
        }

        .payroll-form-input:focus {
            border-color: var(--primary);

            box-shadow:
                0 0 0 3px
                rgba(22, 135, 201, .10);
        }

        .payroll-form-row {
            display: grid;

            grid-template-columns:
                repeat(2, minmax(0, 1fr));

            gap: 14px;
        }


        /* =========================================================
           TOAST
        ========================================================= */

        .payroll-toast {
            position: fixed;

            right: 25px;
            bottom: 25px;

            min-width: 260px;

            padding: 13px 16px;

            border-radius: 8px;

            background: var(--text);
            color: #ffffff;

            box-shadow: var(--shadow-md);

            font-size: 13px;

            opacity: 0;
            transform: translateY(10px);

            pointer-events: none;

            transition: .2s ease;

            z-index: 2000;
        }

        .payroll-toast.show {
            opacity: 1;
            transform: translateY(0);
        }

        .payroll-toast.success {
            background: var(--success);
        }

        .payroll-toast.error {
            background: var(--danger);
        }


        /* =========================================================
           RESPONSIVE
        ========================================================= */

        @media (max-width: 600px) {

            .payroll-page {
                padding: 20px;
            }

            .payroll-header h1 {
                font-size: 24px;
            }

            .payroll-card-header-flex {
                align-items: flex-start;
            }

            .payroll-card-header-flex > button {
                flex-shrink: 0;
            }

            .payroll-form-row {
                grid-template-columns: 1fr;
            }

        }

    </style>

</head>


<body>


<?php require_once '../includes/sidebar.php'; ?>


<div class="main">


    <header class="topbar">

        <div class="topbar-left">

            <h2>
                Payroll
            </h2>

        </div>

    </header>


    <main class="payroll-page">


        <section class="payroll-header">

            <h1>
                Payroll Management
            </h1>

            <p>
                Manage employees and their payroll information.
            </p>

        </section>


        <section class="payroll-card">


            <div class="payroll-card-header">

                <div class="payroll-card-header-flex">

                    <div>

                        <h2>
                            Employees
                        </h2>

                        <p>
                            Active employees can be used for payroll and employee-related transactions.
                        </p>

                    </div>


                    <button
                        type="button"
                        class="btn btn-primary"
                        id="addEmployeeButton"
                    >
                        + Add Employee
                    </button>

                </div>

            </div>


            <div class="payroll-card-body">

                <div
                    class="payroll-table-wrapper"
                    id="employeesContainer"
                >

                    <div class="payroll-empty">
                        Loading employees...
                    </div>

                </div>

            </div>


        </section>


    </main>


</div>


<!-- =========================================================
     EMPLOYEE MODAL
========================================================= -->

<div
    class="payroll-modal"
    id="employeeModal"
>


    <div class="payroll-modal-card">


        <div class="payroll-modal-header">

            <div>

                <h3 id="employeeModalTitle">
                    Add Employee
                </h3>

                <p
                    class="payroll-modal-description"
                    id="employeeModalDescription"
                >
                    Add a new active employee.
                </p>

            </div>


            <button
                type="button"
                class="payroll-modal-close"
                id="closeEmployeeModal"
                aria-label="Close"
            >
                ×
            </button>

        </div>


        <form id="employeeForm">


            <div class="payroll-modal-body">


                <input
                    type="hidden"
                    name="employee_id"
                    id="employeeId"
                    value=""
                >


                <div class="payroll-form-group">

                    <label
                        class="payroll-form-label"
                        for="employeeFullName"
                    >
                        Full Name
                    </label>

                    <input
                        type="text"
                        class="payroll-form-input"
                        id="employeeFullName"
                        name="full_name"
                        maxlength="150"
                        placeholder="e.g. Juan Dela Cruz"
                        autocomplete="off"
                        required
                    >

                </div>


                <div class="payroll-form-row">


                    <div class="payroll-form-group">

                        <label
                            class="payroll-form-label"
                            for="employeePosition"
                        >
                            Position
                        </label>

                        <input
                            type="text"
                            class="payroll-form-input"
                            id="employeePosition"
                            name="position"
                            maxlength="100"
                            placeholder="e.g. Driver"
                            autocomplete="off"
                            required
                        >

                    </div>


                    <div class="payroll-form-group">

                        <label
                            class="payroll-form-label"
                            for="employeeDailyRate"
                        >
                            Daily Rate
                        </label>

                        <input
                            type="number"
                            class="payroll-form-input"
                            id="employeeDailyRate"
                            name="daily_rate"
                            min="0"
                            step="0.01"
                            placeholder="0.00"
                            inputmode="decimal"
                            required
                        >

                    </div>


                </div>


                <div class="payroll-form-group">

                    <label
                        class="payroll-form-label"
                        for="employeeDateAdded"
                    >
                        Date Added
                    </label>

                    <input
                        type="date"
                        class="payroll-form-input"
                        id="employeeDateAdded"
                        name="date_added"
                        required
                    >

                </div>


            </div>


            <div class="payroll-modal-footer">

                <button
                    type="button"
                    class="btn btn-secondary"
                    id="cancelEmployeeButton"
                >
                    Cancel
                </button>


                <button
                    type="submit"
                    class="btn btn-primary"
                    id="saveEmployeeButton"
                >
                    Save Employee
                </button>

            </div>


        </form>


    </div>


</div>


<div
    class="payroll-toast"
    id="payrollToast"
></div>


<script>

const PAYROLL_URL =
    'backend/payroll-backend.php';


let employees = [];


/* =========================================================
   HELPERS
========================================================= */

function escapeHtml(value)
{
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}


function formatMoney(value)
{
    return '₱' +
        Number(value || 0).toLocaleString(
            'en-PH',
            {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }
        );
}


function formatDate(value)
{
    if (!value) {
        return '—';
    }

    const parts =
        String(value).split('-');

    if (parts.length !== 3) {
        return escapeHtml(value);
    }

    return `${parts[1]}/${parts[2]}/${parts[0]}`;
}


function todayDate()
{
    const now =
        new Date();

    const year =
        now.getFullYear();

    const month =
        String(
            now.getMonth() + 1
        ).padStart(2, '0');

    const day =
        String(
            now.getDate()
        ).padStart(2, '0');

    return `${year}-${month}-${day}`;
}


/* =========================================================
   TOAST
========================================================= */

let toastTimer = null;


function showToast(
    message,
    type = 'success'
)
{
    const toast =
        document.getElementById(
            'payrollToast'
        );

    toast.textContent =
        message;

    toast.className =
        `payroll-toast ${type} show`;


    clearTimeout(
        toastTimer
    );


    toastTimer =
        setTimeout(
            () => {

                toast.classList.remove(
                    'show'
                );

            },
            2500
        );
}


/* =========================================================
   LOAD EMPLOYEES
========================================================= */

async function loadEmployees()
{
    const container =
        document.getElementById(
            'employeesContainer'
        );


    container.innerHTML =
        '<div class="payroll-empty">' +
        'Loading employees...' +
        '</div>';


    try {

        const form =
            new FormData();


        form.append(
            'action',
            'get_employees'
        );


        const response =
            await fetch(
                PAYROLL_URL,
                {
                    method: 'POST',
                    body: form
                }
            );


        const data =
            await response.json();


        if (
            !response.ok ||
            !data.success
        ) {

            throw new Error(
                data.message ||
                'Unable to load employees.'
            );

        }


        employees =
            Array.isArray(
                data.employees
            )
                ? data.employees
                : [];


        renderEmployees();


    } catch (error) {

        container.innerHTML =
            '<div class="payroll-empty">' +
            escapeHtml(
                error.message
            ) +
            '</div>';

    }
}


/* =========================================================
   RENDER
========================================================= */

function renderEmployees()
{
    const container =
        document.getElementById(
            'employeesContainer'
        );


    if (!employees.length) {

        container.innerHTML =
            '<div class="payroll-empty">' +
            'No employees found.' +
            '</div>';

        return;
    }


    let html =

        '<table class="payroll-table">' +

        '<thead>' +

        '<tr>' +

        '<th>Employee</th>' +
        '<th>Position</th>' +
        '<th>Daily Rate</th>' +
        '<th>Date Added</th>' +
        '<th>Status</th>' +
        '<th>Action</th>' +

        '</tr>' +

        '</thead>' +

        '<tbody>';


    employees.forEach(
        employee => {

            const active =
                Number(
                    employee.is_active
                ) === 1;


            html +=

                '<tr>' +

                '<td>' +

                '<div class="employee-name">' +
                escapeHtml(
                    employee.full_name
                ) +
                '</div>' +

                '</td>' +


                '<td>' +

                '<span class="employee-position">' +
                escapeHtml(
                    employee.position
                ) +
                '</span>' +

                '</td>' +


                '<td>' +

                formatMoney(
                    employee.daily_rate
                ) +

                '</td>' +


                '<td>' +

                formatDate(
                    employee.date_added
                ) +

                '</td>' +


                '<td>' +

                '<span class="employee-status ' +
                (
                    active
                        ? 'active'
                        : 'inactive'
                ) +
                '">' +

                (
                    active
                        ? 'Active'
                        : 'Inactive'
                ) +

                '</span>' +

                '</td>' +


                '<td>' +

                '<div class="payroll-actions">' +

                '<button ' +
                'type="button" ' +
                'class="btn btn-secondary payroll-btn-small" ' +
                'onclick="editEmployee(' +
                Number(
                    employee.employee_id
                ) +
                ')">' +
                'Edit' +
                '</button>' +


                (
                    active

                        ? '<button ' +
                          'type="button" ' +
                          'class="btn btn-danger payroll-btn-small" ' +
                          'onclick="changeEmployeeStatus(' +
                          Number(
                              employee.employee_id
                          ) +
                          ', false)">' +
                          'Deactivate' +
                          '</button>'

                        : '<button ' +
                          'type="button" ' +
                          'class="btn btn-primary payroll-btn-small" ' +
                          'onclick="changeEmployeeStatus(' +
                          Number(
                              employee.employee_id
                          ) +
                          ', true)">' +
                          'Reactivate' +
                          '</button>'
                ) +

                '</div>' +

                '</td>' +

                '</tr>';

        }
    );


    html +=
        '</tbody>' +
        '</table>';


    container.innerHTML =
        html;
}


/* =========================================================
   MODAL
========================================================= */

function openAddEmployee()
{
    document.getElementById(
        'employeeForm'
    ).reset();


    document.getElementById(
        'employeeId'
    ).value =
        '';


    document.getElementById(
        'employeeDateAdded'
    ).value =
        todayDate();


    document.getElementById(
        'employeeModalTitle'
    ).textContent =
        'Add Employee';


    document.getElementById(
        'employeeModalDescription'
    ).textContent =
        'Add a new active employee.';


    document.getElementById(
        'saveEmployeeButton'
    ).textContent =
        'Save Employee';


    document.getElementById(
        'employeeModal'
    ).classList.add(
        'show'
    );


    setTimeout(
        () => {

            document.getElementById(
                'employeeFullName'
            ).focus();

        },
        50
    );
}


function editEmployee(
    id
)
{
    const employee =
        employees.find(
            item =>
                Number(
                    item.employee_id
                ) === Number(id)
        );


    if (!employee) {
        return;
    }


    document.getElementById(
        'employeeId'
    ).value =
        employee.employee_id;


    document.getElementById(
        'employeeFullName'
    ).value =
        employee.full_name || '';


    document.getElementById(
        'employeePosition'
    ).value =
        employee.position || '';


    document.getElementById(
        'employeeDailyRate'
    ).value =
        employee.daily_rate || '';


    document.getElementById(
        'employeeDateAdded'
    ).value =
        employee.date_added || '';


    document.getElementById(
        'employeeModalTitle'
    ).textContent =
        'Edit Employee';


    document.getElementById(
        'employeeModalDescription'
    ).textContent =
        'Update the employee information.';


    document.getElementById(
        'saveEmployeeButton'
    ).textContent =
        'Save Changes';


    document.getElementById(
        'employeeModal'
    ).classList.add(
        'show'
    );
}


function closeEmployeeModal()
{
    document.getElementById(
        'employeeModal'
    ).classList.remove(
        'show'
    );
}


/* =========================================================
   SAVE
========================================================= */

document.getElementById(
    'employeeForm'
).addEventListener(
    'submit',
    async event => {

        event.preventDefault();


        const id =
            document.getElementById(
                'employeeId'
            ).value;


        const action =
            id
                ? 'update_employee'
                : 'add_employee';


        const form =
            new FormData(
                event.target
            );


        form.append(
            'action',
            action
        );


        const button =
            document.getElementById(
                'saveEmployeeButton'
            );


        button.disabled =
            true;


        button.textContent =
            'Saving...';


        try {

            const response =
                await fetch(
                    PAYROLL_URL,
                    {
                        method: 'POST',
                        body: form
                    }
                );


            const data =
                await response.json();


            if (
                !response.ok ||
                !data.success
            ) {

                throw new Error(
                    data.message ||
                    'Unable to save employee.'
                );

            }


            closeEmployeeModal();


            showToast(
                data.message
            );


            await loadEmployees();


        } catch (error) {

            showToast(
                error.message,
                'error'
            );

        } finally {

            button.disabled =
                false;

            button.textContent =
                id
                    ? 'Save Changes'
                    : 'Save Employee';

        }

    }
);


/* =========================================================
   STATUS
========================================================= */

async function changeEmployeeStatus(
    id,
    activate
)
{
    const action =
        activate
            ? 'reactivate_employee'
            : 'deactivate_employee';


    const message =
        activate
            ? 'Reactivate this employee?'
            : 'Deactivate this employee?';


    if (!confirm(message)) {
        return;
    }


    const form =
        new FormData();


    form.append(
        'action',
        action
    );


    form.append(
        'employee_id',
        id
    );


    try {

        const response =
            await fetch(
                PAYROLL_URL,
                {
                    method: 'POST',
                    body: form
                }
            );


        const data =
            await response.json();


        if (
            !response.ok ||
            !data.success
        ) {

            throw new Error(
                data.message ||
                'Unable to update employee.'
            );

        }


        showToast(
            data.message
        );


        await loadEmployees();


    } catch (error) {

        showToast(
            error.message,
            'error'
        );

    }
}


/* =========================================================
   BUTTONS
========================================================= */

document.getElementById(
    'addEmployeeButton'
).addEventListener(
    'click',
    openAddEmployee
);


document.getElementById(
    'closeEmployeeModal'
).addEventListener(
    'click',
    closeEmployeeModal
);


document.getElementById(
    'cancelEmployeeButton'
).addEventListener(
    'click',
    closeEmployeeModal
);


document.getElementById(
    'employeeModal'
).addEventListener(
    'click',
    event => {

        if (
            event.target.id ===
            'employeeModal'
        ) {

            closeEmployeeModal();

        }

    }
);


/* =========================================================
   INITIAL LOAD
========================================================= */

loadEmployees();

</script>


</body>

</html>