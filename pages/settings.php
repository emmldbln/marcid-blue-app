<?php

declare(strict_types=1);

date_default_timezone_set('Asia/Manila');

require_once '../auth/auth.php';

requireAdmin();

$currentPage = 'settings';

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
        Settings | Marcid Blue
    </title>

    <link
        rel="stylesheet"
        href="../assets/css/app.css"
    >

    <style>

        /* =========================================================
           SETTINGS
        ========================================================= */

        .settings-page {
            padding: 30px;
        }

        .settings-header {
            margin-bottom: 28px;
        }

        .settings-header h1 {
            margin: 0 0 6px;
            font-size: 28px;
            color: var(--text);
        }

        .settings-header p {
            margin: 0;
            color: var(--text-muted);
            font-size: 14px;
        }


        /* =========================================================
           GRID
        ========================================================= */

        .settings-grid {
            display: grid;
            grid-template-columns:
                minmax(0, 1fr)
                minmax(0, 1fr);
            gap: 20px;
        }

        .settings-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            box-shadow: var(--shadow-sm);
            overflow: hidden;
        }

        .settings-card.full-width {
            grid-column: 1 / -1;
        }

        .settings-card-header {
            padding: 18px 20px;
            border-bottom: 1px solid var(--border);
        }

        .settings-card-header-flex {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
        }

        .settings-card-header h2 {
            margin: 0 0 4px;
            font-size: 16px;
            color: var(--text);
        }

        .settings-card-header p {
            margin: 0;
            font-size: 12px;
            color: var(--text-muted);
        }

        .settings-card-body {
            padding: 20px;
        }


        /* =========================================================
           PRICE
        ========================================================= */

        .price-layout {
            display: grid;
            grid-template-columns:
                minmax(0, 1fr)
                auto;
            gap: 20px;
            align-items: end;
        }

        .current-price {
            padding: 18px;
            border: 1px solid var(--border);
            border-radius: 10px;
            background: var(--background);
        }

        .current-price-label {
            font-size: 12px;
            color: var(--text-muted);
            margin-bottom: 7px;
        }

        .current-price-value {
            font-size: 30px;
            font-weight: 700;
            color: var(--primary);
        }


        /* =========================================================
           TABLE
        ========================================================= */

        .settings-table-wrapper {
            overflow-x: auto;
        }

        .settings-table {
            width: 100%;
            border-collapse: collapse;
        }

        .settings-table th {
            padding: 11px 12px;
            text-align: left;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .04em;
            color: var(--text-muted);
            border-bottom: 1px solid var(--border);
        }

        .settings-table td {
            padding: 13px 12px;
            font-size: 13px;
            color: var(--text);
            border-bottom: 1px solid var(--border);
            vertical-align: middle;
        }

        .settings-table tr:last-child td {
            border-bottom: 0;
        }


        /* =========================================================
           USER
        ========================================================= */

        .user-name {
            font-weight: 600;
        }

        .user-username {
            display: block;
            margin-top: 3px;
            color: var(--text-muted);
            font-size: 11px;
        }

        .role-badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 9px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
        }

        .role-admin {
            background: var(--primary-light);
            color: var(--primary-dark);
        }

        .role-staff {
            background: var(--secondary-light);
            color: var(--secondary-dark);
        }


        /* =========================================================
           STATUS
        ========================================================= */

        .status-active {
            color: var(--success);
            font-weight: 700;
        }

        .status-inactive {
            color: var(--danger);
            font-weight: 700;
        }


        /* =========================================================
           BUTTONS
        ========================================================= */

        .settings-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 8px;
            flex-wrap: wrap;
        }

        .btn-small {
            padding: 7px 11px;
            font-size: 12px;
        }


        /* =========================================================
           MODAL
        ========================================================= */

        .settings-modal {
            position: fixed;
            inset: 0;

            display: none;
            align-items: center;
            justify-content: center;

            padding: 20px;

            background: rgba(15, 30, 40, .45);

            z-index: 1000;
        }

        .settings-modal.show {
            display: flex;
        }

        .settings-modal-card {
            width: 100%;
            max-width: 500px;

            max-height: calc(100vh - 40px);

            overflow-y: auto;

            background: var(--surface);

            border-radius: 12px;

            box-shadow: var(--shadow-md);
        }

        .settings-modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;

            padding: 18px 20px;

            border-bottom: 1px solid var(--border);
        }

        .settings-modal-header h3 {
            margin: 0;
            font-size: 17px;
        }

        .modal-close {
            width: 32px;
            height: 32px;

            border: 0;
            border-radius: 7px;

            background: transparent;

            color: var(--text-muted);

            font-size: 20px;

            cursor: pointer;
        }

        .modal-close:hover {
            background: var(--background);
            color: var(--text);
        }

        .settings-modal-body {
            padding: 20px;
        }

        .settings-modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 8px;

            padding: 15px 20px;

            border-top: 1px solid var(--border);
        }


        /* =========================================================
           FORM
        ========================================================= */

        .settings-form-group {
            margin-bottom: 16px;
        }

        .settings-form-group:last-child {
            margin-bottom: 0;
        }

        .settings-form-label {
            display: block;
            margin-bottom: 7px;

            font-size: 12px;
            font-weight: 600;

            color: var(--text);
        }

        .settings-form-input,
        .settings-form-select {
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

        .settings-form-input:focus,
        .settings-form-select:focus {
            border-color: var(--primary);

            box-shadow:
                0 0 0 3px
                rgba(22, 135, 201, .10);
        }

        .settings-form-help {
            margin-top: 5px;

            font-size: 11px;

            color: var(--text-muted);
        }

        .settings-form-row {
            display: grid;

            grid-template-columns:
                repeat(2, minmax(0, 1fr));

            gap: 14px;
        }


        /* =========================================================
           TOAST
        ========================================================= */

        .settings-toast {
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

        .settings-toast.show {
            opacity: 1;
            transform: translateY(0);
        }

        .settings-toast.success {
            background: var(--success);
        }

        .settings-toast.error {
            background: var(--danger);
        }


        /* =========================================================
           EMPTY
        ========================================================= */

        .settings-empty {
            padding: 30px;

            text-align: center;

            color: var(--text-muted);

            font-size: 13px;
        }


        /* =========================================================
           RESPONSIVE
        ========================================================= */

        @media (max-width: 1000px) {

            .settings-grid {
                grid-template-columns: 1fr;
            }

            .settings-card.full-width {
                grid-column: auto;
            }
        }

        @media (max-width: 600px) {

            .settings-page {
                padding: 20px;
            }

            .settings-header h1 {
                font-size: 24px;
            }

            .price-layout {
                grid-template-columns: 1fr;
            }

            .settings-form-row {
                grid-template-columns: 1fr;
            }

            .settings-card-header-flex {
                align-items: flex-start;
            }

            .settings-card-header-flex > button {
                flex-shrink: 0;
            }
        }

    </style>

</head>

<body>

<?php require_once '../includes/sidebar.php'; ?>


<div class="main">

    <header class="topbar">

        <div class="topbar-left"></div>

        <div class="topbar-user">

            👤

            <?= htmlspecialchars(
                $_SESSION['full_name'] ?? 'Marcid Blue Admin'
            ) ?>

        </div>

    </header>


    <main class="settings-page">

        <section class="settings-header">

            <h1>
                System Settings
            </h1>

            <p>
                Manage users and business settings for Marcid Blue.
            </p>

        </section>


        <div
            id="settingsError"
            style="
                display:none;
                margin-bottom:20px;
                padding:13px 15px;
                border:1px solid #f1c2c0;
                border-radius:8px;
                background:#fff4f3;
                color:var(--danger);
                font-size:13px;
            "
        ></div>


        <section class="settings-grid">


            <!-- =================================================
                 USER MANAGEMENT
            ================================================== -->

            <article class="settings-card full-width">

                <div class="settings-card-header">

                    <div class="settings-card-header-flex">

                        <div>

                            <h2>
                                User Management
                            </h2>

                            <p>
                                Manage the accounts that can access Marcid Blue.
                            </p>

                        </div>


                        <button
                            type="button"
                            class="btn btn-primary"
                            id="addUserButton"
                        >
                            + Add User
                        </button>

                    </div>

                </div>


                <div class="settings-card-body">

                    <div
                        class="settings-table-wrapper"
                        id="usersContainer"
                    >

                        <div class="settings-empty">
                            Loading users...
                        </div>

                    </div>

                </div>

            </article>


            <!-- =================================================
                 WALK-IN PRICE
            ================================================== -->

            <article class="settings-card">

                <div class="settings-card-header">

                    <h2>
                        Walk-in Pricing
                    </h2>

                    <p>
                        Change the default price charged for walk-in customers.
                    </p>

                </div>


                <div class="settings-card-body">

                    <div class="price-layout">

                        <div class="current-price">

                            <div class="current-price-label">
                                Current Walk-in Price
                            </div>

                            <div
                                class="current-price-value"
                                id="currentWalkInPrice"
                            >
                                ₱0.00
                            </div>

                        </div>


                        <button
                            type="button"
                            class="btn btn-primary"
                            id="changePriceButton"
                        >
                            Change Price
                        </button>

                    </div>

                </div>

            </article>


            <!-- =================================================
                 PRICE HISTORY
            ================================================== -->

            <article class="settings-card">

                <div class="settings-card-header">

                    <h2>
                        Price History
                    </h2>

                    <p>
                        Previous walk-in prices and their effective dates.
                    </p>

                </div>


                <div class="settings-card-body">

                    <div
                        class="settings-table-wrapper"
                        id="priceHistoryContainer"
                    >

                        <div class="settings-empty">
                            Loading price history...
                        </div>

                    </div>

                </div>

            </article>


            <!-- =================================================
                 PAYMENT METHODS
            ================================================== -->

            <article class="settings-card full-width">

                <div class="settings-card-header">

                    <div class="settings-card-header-flex">

                        <div>

                            <h2>
                                Payment Methods
                            </h2>

                            <p>
                                Manage the payment methods available for transactions.
                            </p>

                        </div>


                        <button
                            type="button"
                            class="btn btn-primary"
                            id="addPaymentMethodButton"
                        >
                            + Add Method
                        </button>

                    </div>

                </div>


                <div class="settings-card-body">

                    <div
                        class="settings-table-wrapper"
                        id="paymentMethodsContainer"
                    >

                        <div class="settings-empty">
                            Loading payment methods...
                        </div>

                    </div>

                </div>

            </article>


        </section>

    </main>

</div>


<!-- =========================================================
     USER MODAL
========================================================= -->

<div
    class="settings-modal"
    id="userModal"
>

    <div class="settings-modal-card">

        <div class="settings-modal-header">

            <h3 id="userModalTitle">
                Add User
            </h3>

            <button
                type="button"
                class="modal-close"
                id="closeUserModal"
            >
                ×
            </button>

        </div>


        <form id="userForm">

            <div class="settings-modal-body">

                <input
                    type="hidden"
                    id="userId"
                    name="user_id"
                    value=""
                >


                <div class="settings-form-group">

                    <label
                        class="settings-form-label"
                        for="userName"
                    >
                        Display Name
                    </label>

                    <input
                        type="text"
                        id="userName"
                        name="name"
                        class="settings-form-input"
                        placeholder="e.g. Juan Dela Cruz"
                    >

                </div>


                <div class="settings-form-group">

                    <label
                        class="settings-form-label"
                        for="userUsername"
                    >
                        Username
                    </label>

                    <input
                        type="text"
                        id="userUsername"
                        name="username"
                        class="settings-form-input"
                        placeholder="Enter username"
                        autocomplete="off"
                        required
                    >

                </div>


                <div class="settings-form-row">

                    <div class="settings-form-group">

                        <label
                            class="settings-form-label"
                            for="userRole"
                        >
                            Role
                        </label>

                        <select
                            id="userRole"
                            name="role"
                            class="settings-form-select"
                        >

                            <option value="Staff">
                                Staff
                            </option>

                            <option value="Admin">
                                Admin
                            </option>

                        </select>

                    </div>


                    <div
                        class="settings-form-group"
                        id="userStatusGroup"
                        style="display:none;"
                    >

                        <label
                            class="settings-form-label"
                            for="userStatus"
                        >
                            Status
                        </label>

                        <select
                            id="userStatus"
                            name="is_active"
                            class="settings-form-select"
                        >

                            <option value="1">
                                Active
                            </option>

                            <option value="0">
                                Inactive
                            </option>

                        </select>

                    </div>

                </div>


                <div class="settings-form-group">

                    <label
                        class="settings-form-label"
                        for="userPassword"
                    >
                        Password
                    </label>

                    <input
                        type="password"
                        id="userPassword"
                        name="password"
                        class="settings-form-input"
                        placeholder="Enter password"
                        autocomplete="new-password"
                    >

                    <div
                        class="settings-form-help"
                        id="passwordHelp"
                    >
                        Minimum 6 characters.
                    </div>

                </div>

            </div>


            <div class="settings-modal-footer">

                <button
                    type="button"
                    class="btn btn-secondary"
                    id="cancelUserButton"
                >
                    Cancel
                </button>

                <button
                    type="submit"
                    class="btn btn-primary"
                    id="saveUserButton"
                >
                    Save User
                </button>

            </div>

        </form>

    </div>

</div>


<!-- =========================================================
     PRICE MODAL
========================================================= -->

<div
    class="settings-modal"
    id="priceModal"
>

    <div class="settings-modal-card">

        <div class="settings-modal-header">

            <h3>
                Change Walk-in Price
            </h3>

            <button
                type="button"
                class="modal-close"
                id="closePriceModal"
            >
                ×
            </button>

        </div>


        <form id="priceForm">

            <div class="settings-modal-body">

                <div class="settings-form-group">

                    <label
                        class="settings-form-label"
                        for="walkInPrice"
                    >
                        New Walk-in Price
                    </label>

                    <input
                        type="number"
                        id="walkInPrice"
                        name="price"
                        class="settings-form-input"
                        min="0.01"
                        step="0.01"
                        required
                    >

                </div>


                <div class="settings-form-group">

                    <label
                        class="settings-form-label"
                        for="effectiveDate"
                    >
                        Effective Date
                    </label>

                    <input
                        type="date"
                        id="effectiveDate"
                        name="effective_date"
                        class="settings-form-input"
                        value="<?php echo date('Y-m-d'); ?>"
                        required
                    >

                    <div class="settings-form-help">

                        Existing historical transactions will
                        keep their original amounts.

                    </div>

                </div>

            </div>


            <div class="settings-modal-footer">

                <button
                    type="button"
                    class="btn btn-secondary"
                    id="cancelPriceButton"
                >
                    Cancel
                </button>

                <button
                    type="submit"
                    class="btn btn-primary"
                    id="savePriceButton"
                >
                    Save Price
                </button>

            </div>

        </form>

    </div>

</div>


<!-- =========================================================
     PAYMENT METHOD MODAL
========================================================= -->

<div
    class="settings-modal"
    id="paymentMethodModal"
>

    <div class="settings-modal-card">

        <div class="settings-modal-header">

            <h3 id="paymentMethodModalTitle">
                Add Payment Method
            </h3>

            <button
                type="button"
                class="modal-close"
                id="closePaymentMethodModal"
            >
                ×
            </button>

        </div>


        <form id="paymentMethodForm">

            <div class="settings-modal-body">

                <input
                    type="hidden"
                    id="paymentMethodId"
                    name="method_id"
                    value=""
                >


                <div class="settings-form-group">

                    <label
                        class="settings-form-label"
                        for="paymentMethodName"
                    >
                        Payment Method Name
                    </label>

                    <input
                        type="text"
                        id="paymentMethodName"
                        name="method_name"
                        class="settings-form-input"
                        placeholder="e.g. Maya"
                        maxlength="100"
                        autocomplete="off"
                        required
                    >

                    <div class="settings-form-help">

                        Enter the name exactly as it should appear
                        when recording a payment.

                    </div>

                </div>

            </div>


            <div class="settings-modal-footer">

                <button
                    type="button"
                    class="btn btn-secondary"
                    id="cancelPaymentMethodButton"
                >
                    Cancel
                </button>

                <button
                    type="submit"
                    class="btn btn-primary"
                    id="savePaymentMethodButton"
                >
                    Save Method
                </button>

            </div>

        </form>

    </div>

</div>


<div
    class="settings-toast"
    id="settingsToast"
></div>


<script>

const SETTINGS_URL =
    'backend/settings-backend.php';


let settingsData = null;


/* =========================================================
   HELPERS
========================================================= */

function formatMoney(value) {

    return '₱' +
        Number(value || 0).toLocaleString(
            'en-PH',
            {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }
        );
}


function escapeHtml(value) {

    const div =
        document.createElement('div');

    div.textContent =
        value ?? '';

    return div.innerHTML;
}


async function requestSettings(
    action,
    extra = {}
) {

    const data =
        new URLSearchParams();

    data.append(
        'action',
        action
    );


    Object.entries(extra).forEach(
        ([key, value]) => {

            data.append(
                key,
                value
            );

        }
    );


    const response =
        await fetch(
            SETTINGS_URL,
            {
                method: 'POST',

                headers: {
                    'Content-Type':
                        'application/x-www-form-urlencoded'
                },

                body: data
            }
        );


    const json =
        await response.json();


    if (!json.success) {

        throw new Error(
            json.message ||
            'Unable to complete settings request.'
        );

    }


    return json;
}


function showToast(
    message,
    type = 'success'
) {

    const toast =
        document.getElementById(
            'settingsToast'
        );


    toast.textContent =
        message;


    toast.className =
        'settings-toast show ' +
        type;


    setTimeout(
        () => {

            toast.className =
                'settings-toast';

        },
        3000
    );
}


/* =========================================================
   LOAD SETTINGS
========================================================= */

async function loadSettings() {

    try {

        const data =
            await requestSettings(
                'get_settings'
            );


        settingsData =
            data;


        renderUsers(
            data.users || []
        );


        renderPrice(
            data.settings || {}
        );


        renderPriceHistory(
            data.walk_in_price_history || []
        );


        renderPaymentMethods(
            data.payment_methods || []
        );


        document.getElementById(
            'settingsError'
        ).style.display =
            'none';


    } catch (error) {

        console.error(error);


        const errorBox =
            document.getElementById(
                'settingsError'
            );


        errorBox.textContent =
            error.message;


        errorBox.style.display =
            'block';

    }
}


/* =========================================================
   USERS
========================================================= */

function renderUsers(users) {

    const container =
        document.getElementById(
            'usersContainer'
        );


    if (!users.length) {

        container.innerHTML =
            '<div class="settings-empty">' +
            'No users found.' +
            '</div>';

        return;
    }


    let html =
        '<table class="settings-table">' +

        '<thead>' +

        '<tr>' +

        '<th>User</th>' +
        '<th>Role</th>' +
        '<th>Status</th>' +
        '<th>Created</th>' +
        '<th></th>' +

        '</tr>' +

        '</thead>' +

        '<tbody>';


    users.forEach(
        user => {

            const displayName =
                user.full_name ||
                user.name ||
                user.username ||
                'User';


            const role =
                user.role ||
                'Staff';


            const isActive =
                Number(
                    user.is_active
                ) === 1;


            const roleClass =
                role === 'Admin'
                    ? 'role-admin'
                    : 'role-staff';


            html +=

                '<tr>' +

                '<td>' +

                '<span class="user-name">' +
                escapeHtml(
                    displayName
                ) +
                '</span>' +

                '<span class="user-username">' +
                escapeHtml(
                    user.username
                ) +
                '</span>' +

                '</td>' +

                '<td>' +

                '<span class="role-badge ' +
                roleClass +
                '">' +

                escapeHtml(
                    role
                ) +

                '</span>' +

                '</td>' +

                '<td>' +

                (
                    isActive

                        ? '<span class="status-active">' +
                          'Active' +
                          '</span>'

                        : '<span class="status-inactive">' +
                          'Inactive' +
                          '</span>'
                ) +

                '</td>' +

                '<td>' +

                (
                    user.created_at
                        ? escapeHtml(
                            new Date(
                                user.created_at
                                    .replace(
                                        ' ',
                                        'T'
                                    )
                            ).toLocaleDateString(
                                'en-PH',
                                {
                                    month: 'short',
                                    day: 'numeric',
                                    year: 'numeric'
                                }
                            )
                        )
                        : '—'
                ) +

                '</td>' +

                '<td>' +

                '<div class="settings-actions">' +

                '<button ' +
                'type="button" ' +
                'class="btn btn-secondary btn-small" ' +
                'onclick="editUser(' +
                Number(
                    user.user_id
                ) +
                ')">' +
                'Edit' +
                '</button>' +

                (
                    isActive

                        ? '<button ' +
                          'type="button" ' +
                          'class="btn btn-danger btn-small" ' +
                          'onclick="changeUserStatus(' +
                          Number(
                              user.user_id
                          ) +
                          ', false)">' +
                          'Deactivate' +
                          '</button>'

                        : '<button ' +
                          'type="button" ' +
                          'class="btn btn-primary btn-small" ' +
                          'onclick="changeUserStatus(' +
                          Number(
                              user.user_id
                          ) +
                          ', true)">' +
                          'Activate' +
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
   PRICE
========================================================= */

function renderPrice(settings) {

    const price =
        settings.walk_in_price
            ? settings.walk_in_price.value
            : 0;


    document.getElementById(
        'currentWalkInPrice'
    ).textContent =
        formatMoney(
            price
        );


    document.getElementById(
        'walkInPrice'
    ).value =
        Number(
            price || 0
        ).toFixed(2);
}


/* =========================================================
   PRICE HISTORY
========================================================= */

function renderPriceHistory(history) {

    const container =
        document.getElementById(
            'priceHistoryContainer'
        );


    if (!history.length) {

        container.innerHTML =
            '<div class="settings-empty">' +
            'No price history available.' +
            '</div>';

        return;
    }


    let html =
        '<table class="settings-table">' +

        '<thead>' +

        '<tr>' +

        '<th>Effective Date</th>' +
        '<th>Price</th>' +
        '<th>Recorded</th>' +

        '</tr>' +

        '</thead>' +

        '<tbody>';


    history.forEach(
        row => {

            html +=

                '<tr>' +

                '<td>' +
                escapeHtml(
                    row.effective_date
                ) +
                '</td>' +

                '<td>' +
                formatMoney(
                    row.price
                ) +
                '</td>' +

                '<td>' +
                escapeHtml(
                    row.created_at || '—'
                ) +
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
   PAYMENT METHODS
========================================================= */

function renderPaymentMethods(methods) {

    const container =
        document.getElementById(
            'paymentMethodsContainer'
        );


    if (!methods.length) {

        container.innerHTML =
            '<div class="settings-empty">' +
            'No payment methods configured.' +
            '</div>';

        return;
    }


    let html =
        '<table class="settings-table">' +

        '<thead>' +

        '<tr>' +

        '<th>Method</th>' +
        '<th>Status</th>' +
        '<th>Action</th>' +

        '</tr>' +

        '</thead>' +

        '<tbody>';


    methods.forEach(
        method => {

            const isActive =
                Number(
                    method.is_active
                ) === 1;


            html +=

                '<tr>' +

                '<td>' +

                '<span class="user-name">' +
                escapeHtml(
                    method.method_name
                ) +
                '</span>' +

                '</td>' +

                '<td>' +

                (
                    isActive

                        ? '<span class="status-active">' +
                          'Active' +
                          '</span>'

                        : '<span class="status-inactive">' +
                          'Inactive' +
                          '</span>'
                ) +

                '</td>' +

                '<td>' +

                '<div class="settings-actions">' +

                '<button ' +
                'type="button" ' +
                'class="btn btn-secondary btn-small" ' +
                'onclick="editPaymentMethod(' +
                Number(
                    method.method_id
                ) +
                ')">' +
                'Edit' +
                '</button>' +

                (
                    isActive

                        ? '<button ' +
                          'type="button" ' +
                          'class="btn btn-danger btn-small" ' +
                          'onclick="changePaymentMethodStatus(' +
                          Number(
                              method.method_id
                          ) +
                          ', false)">' +
                          'Deactivate' +
                          '</button>'

                        : '<button ' +
                          'type="button" ' +
                          'class="btn btn-primary btn-small" ' +
                          'onclick="changePaymentMethodStatus(' +
                          Number(
                              method.method_id
                          ) +
                          ', true)">' +
                          'Activate' +
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
   USER MODAL
========================================================= */

function openAddUser() {

    document.getElementById(
        'userModalTitle'
    ).textContent =
        'Add User';


    document.getElementById(
        'userId'
    ).value =
        '';


    document.getElementById(
        'userName'
    ).value =
        '';


    document.getElementById(
        'userUsername'
    ).value =
        '';


    document.getElementById(
        'userRole'
    ).value =
        'Staff';


    document.getElementById(
        'userStatus'
    ).value =
        '1';


    document.getElementById(
        'userStatusGroup'
    ).style.display =
        'none';


    document.getElementById(
        'userPassword'
    ).value =
        '';


    document.getElementById(
        'passwordHelp'
    ).textContent =
        'Minimum 6 characters.';


    document.getElementById(
        'saveUserButton'
    ).textContent =
        'Save User';


    document.getElementById(
        'userModal'
    ).classList.add(
        'show'
    );
}


function editUser(userId) {

    const user =
        (settingsData.users || [])
            .find(
                item =>
                    Number(
                        item.user_id
                    ) ===
                    Number(userId)
            );


    if (!user) {
        return;
    }


    document.getElementById(
        'userModalTitle'
    ).textContent =
        'Edit User';


    document.getElementById(
        'userId'
    ).value =
        user.user_id;


    document.getElementById(
        'userName'
    ).value =
        user.full_name ||
        user.name ||
        '';


    document.getElementById(
        'userUsername'
    ).value =
        user.username || '';


    document.getElementById(
        'userRole'
    ).value =
        user.role || 'Staff';


    document.getElementById(
        'userStatus'
    ).value =
        Number(
            user.is_active
        ) === 1
            ? '1'
            : '0';


    document.getElementById(
        'userStatusGroup'
    ).style.display =
        'block';


    document.getElementById(
        'userPassword'
    ).value =
        '';


    document.getElementById(
        'passwordHelp'
    ).textContent =
        'Leave blank to keep the current password.';


    document.getElementById(
        'saveUserButton'
    ).textContent =
        'Save Changes';


    document.getElementById(
        'userModal'
    ).classList.add(
        'show'
    );
}


function closeUserModal() {

    document.getElementById(
        'userModal'
    ).classList.remove(
        'show'
    );
}


/* =========================================================
   USER FORM
========================================================= */

document.getElementById(
    'userForm'
).addEventListener(
    'submit',
    async event => {

        event.preventDefault();


        const userId =
            document.getElementById(
                'userId'
            ).value;


        const action =
            userId
                ? 'update_user'
                : 'add_user';


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
                'saveUserButton'
            );


        button.disabled =
            true;


        button.textContent =
            'Saving...';


        try {

            const response =
                await fetch(
                    SETTINGS_URL,
                    {
                        method: 'POST',
                        body: form
                    }
                );


            const json =
                await response.json();


            if (!json.success) {

                throw new Error(
                    json.message ||
                    'Unable to save user.'
                );

            }


            closeUserModal();


            showToast(
                json.message
            );


            await loadSettings();


        } catch (error) {

            showToast(
                error.message,
                'error'
            );

        } finally {

            button.disabled =
                false;

            button.textContent =
                userId
                    ? 'Save Changes'
                    : 'Save User';

        }

    }
);


/* =========================================================
   USER STATUS
========================================================= */

async function changeUserStatus(
    userId,
    activate
) {

    const action =
        activate
            ? 'reactivate_user'
            : 'deactivate_user';


    const message =
        activate
            ? 'Activate this user account?'
            : 'Deactivate this user account?';


    if (!confirm(message)) {
        return;
    }


    try {

        const response =
            await requestSettings(
                action,
                {
                    user_id:
                        userId
                }
            );


        showToast(
            response.message
        );


        await loadSettings();


    } catch (error) {

        showToast(
            error.message,
            'error'
        );

    }
}


/* =========================================================
   PRICE MODAL
========================================================= */

function openPriceModal() {

    const current =
        settingsData &&
        settingsData.settings &&
        settingsData.settings.walk_in_price

            ? settingsData
                .settings
                .walk_in_price
                .value

            : 0;


    document.getElementById(
        'walkInPrice'
    ).value =
        Number(
            current || 0
        ).toFixed(2);


    document.getElementById(
        'effectiveDate'
    ).value =
        new Date()
            .toISOString()
            .slice(0, 10);


    document.getElementById(
        'priceModal'
    ).classList.add(
        'show'
    );
}


function closePriceModal() {

    document.getElementById(
        'priceModal'
    ).classList.remove(
        'show'
    );
}


/* =========================================================
   PRICE FORM
========================================================= */

document.getElementById(
    'priceForm'
).addEventListener(
    'submit',
    async event => {

        event.preventDefault();


        const button =
            document.getElementById(
                'savePriceButton'
            );


        const form =
            new FormData(
                event.target
            );


        form.append(
            'action',
            'save_walk_in_price'
        );


        button.disabled =
            true;


        button.textContent =
            'Saving...';


        try {

            const response =
                await fetch(
                    SETTINGS_URL,
                    {
                        method: 'POST',
                        body: form
                    }
                );


            const json =
                await response.json();


            if (!json.success) {

                throw new Error(
                    json.message ||
                    'Unable to save price.'
                );

            }


            closePriceModal();


            showToast(
                json.message
            );


            await loadSettings();


        } catch (error) {

            showToast(
                error.message,
                'error'
            );

        } finally {

            button.disabled =
                false;

            button.textContent =
                'Save Price';

        }

    }
);


/* =========================================================
   PAYMENT METHOD MODAL
========================================================= */

function openAddPaymentMethod() {

    document.getElementById(
        'paymentMethodModalTitle'
    ).textContent =
        'Add Payment Method';


    document.getElementById(
        'paymentMethodId'
    ).value =
        '';


    document.getElementById(
        'paymentMethodName'
    ).value =
        '';


    document.getElementById(
        'savePaymentMethodButton'
    ).textContent =
        'Save Method';


    document.getElementById(
        'paymentMethodModal'
    ).classList.add(
        'show'
    );
}


function editPaymentMethod(
    methodId
) {

    const method =
        (settingsData.payment_methods || [])
            .find(
                item =>
                    Number(
                        item.method_id
                    ) ===
                    Number(methodId)
            );


    if (!method) {
        return;
    }


    document.getElementById(
        'paymentMethodModalTitle'
    ).textContent =
        'Edit Payment Method';


    document.getElementById(
        'paymentMethodId'
    ).value =
        method.method_id;


    document.getElementById(
        'paymentMethodName'
    ).value =
        method.method_name || '';


    document.getElementById(
        'savePaymentMethodButton'
    ).textContent =
        'Save Changes';


    document.getElementById(
        'paymentMethodModal'
    ).classList.add(
        'show'
    );
}


function closePaymentMethodModal() {

    document.getElementById(
        'paymentMethodModal'
    ).classList.remove(
        'show'
    );
}


/* =========================================================
   PAYMENT METHOD FORM
========================================================= */

document.getElementById(
    'paymentMethodForm'
).addEventListener(
    'submit',
    async event => {

        event.preventDefault();


        const methodId =
            document.getElementById(
                'paymentMethodId'
            ).value;


        const action =
            methodId
                ? 'update_payment_method'
                : 'add_payment_method';


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
                'savePaymentMethodButton'
            );


        button.disabled =
            true;


        button.textContent =
            'Saving...';


        try {

            const response =
                await fetch(
                    SETTINGS_URL,
                    {
                        method: 'POST',
                        body: form
                    }
                );


            const json =
                await response.json();


            if (!json.success) {

                throw new Error(
                    json.message ||
                    'Unable to save payment method.'
                );

            }


            closePaymentMethodModal();


            showToast(
                json.message
            );


            await loadSettings();


        } catch (error) {

            showToast(
                error.message,
                'error'
            );

        } finally {

            button.disabled =
                false;

            button.textContent =
                methodId
                    ? 'Save Changes'
                    : 'Save Method';

        }

    }
);


/* =========================================================
   PAYMENT METHOD STATUS
========================================================= */

async function changePaymentMethodStatus(
    methodId,
    activate
) {

    const message =
        activate
            ? 'Activate this payment method?'
            : 'Deactivate this payment method?';


    if (!confirm(message)) {
        return;
    }


    try {

        const response =
            await requestSettings(
                'change_payment_method_status',
                {
                    method_id:
                        methodId,

                    activate:
                        activate
                            ? '1'
                            : '0'
                }
            );


        showToast(
            response.message
        );


        await loadSettings();


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
    'addUserButton'
).addEventListener(
    'click',
    openAddUser
);


document.getElementById(
    'closeUserModal'
).addEventListener(
    'click',
    closeUserModal
);


document.getElementById(
    'cancelUserButton'
).addEventListener(
    'click',
    closeUserModal
);


document.getElementById(
    'changePriceButton'
).addEventListener(
    'click',
    openPriceModal
);


document.getElementById(
    'closePriceModal'
).addEventListener(
    'click',
    closePriceModal
);


document.getElementById(
    'cancelPriceButton'
).addEventListener(
    'click',
    closePriceModal
);


document.getElementById(
    'addPaymentMethodButton'
).addEventListener(
    'click',
    openAddPaymentMethod
);


document.getElementById(
    'closePaymentMethodModal'
).addEventListener(
    'click',
    closePaymentMethodModal
);


document.getElementById(
    'cancelPaymentMethodButton'
).addEventListener(
    'click',
    closePaymentMethodModal
);


/* =========================================================
   MODAL BACKDROP
========================================================= */

document.getElementById(
    'userModal'
).addEventListener(
    'click',
    event => {

        if (
            event.target.id ===
            'userModal'
        ) {

            closeUserModal();

        }

    }
);


document.getElementById(
    'priceModal'
).addEventListener(
    'click',
    event => {

        if (
            event.target.id ===
            'priceModal'
        ) {

            closePriceModal();

        }

    }
);


document.getElementById(
    'paymentMethodModal'
).addEventListener(
    'click',
    event => {

        if (
            event.target.id ===
            'paymentMethodModal'
        ) {

            closePaymentMethodModal();

        }

    }
);


/* =========================================================
   INITIAL LOAD
========================================================= */

loadSettings();

</script>

</body>

</html>

