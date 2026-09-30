<?php

declare(strict_types=1);

date_default_timezone_set('Asia/Manila');

require_once '../auth/auth.php';
requireAdmin();

$currentPage = 'customers';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customers | Marcid Blue</title>

    <link rel="stylesheet" href="../assets/css/app.css">

    <style>
        .customers-page {
            padding: 28px;
        }

        .customers-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 24px;
        }

        .customers-title h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 700;
        }

        .customers-title p {
            margin: 6px 0 0;
            color: #6b7280;
            font-size: 14px;
        }

        .customer-actions {
            display: flex;
            gap: 10px;
        }

        .customer-btn {
            border: 0;
            border-radius: 9px;
            padding: 10px 16px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.15s ease;
        }

        .customer-btn.primary {
            background: #1687c9;
            color: #fff;
        }

        .customer-btn.primary:hover {
            background: #1177b2;
        }

        .customer-btn.secondary {
            background: #f1f5f9;
            color: #334155;
        }

        .customer-btn.secondary:hover {
            background: #e2e8f0;
        }

        .customer-btn.danger {
            background: #fee2e2;
            color: #b91c1c;
        }

        .customer-btn.small {
            padding: 7px 11px;
            font-size: 12px;
            border-radius: 7px;
        }

        .customers-toolbar {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 14px;
            display: flex;
            gap: 10px;
            margin-bottom: 18px;
        }

        .customers-search {
            flex: 1;
            min-width: 200px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            padding: 10px 12px;
            font-size: 14px;
            outline: none;
        }

        .customers-search:focus {
            border-color: #1687c9;
            box-shadow: 0 0 0 3px rgba(22, 135, 201, 0.10);
        }

        .customers-card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            overflow: hidden;
            margin-bottom: 22px;
        }

        .customers-card-header {
            padding: 17px 20px;
            border-bottom: 1px solid #edf0f2;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
        }

        .customers-card-header h2 {
            margin: 0;
            font-size: 17px;
            font-weight: 700;
        }

        .customers-card-header span {
            color: #64748b;
            font-size: 13px;
        }

        .customers-table-wrap {
            overflow-x: auto;
        }

        .customers-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 900px;
        }

        .customers-table th {
            background: #f8fafc;
            color: #64748b;
            font-size: 12px;
            font-weight: 700;
            text-align: left;
            padding: 12px 16px;
            border-bottom: 1px solid #e5e7eb;
            white-space: nowrap;
        }

        .customers-table td {
            padding: 14px 16px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 13px;
            vertical-align: middle;
        }

        .customers-table tr:last-child td {
            border-bottom: 0;
        }

        .customer-name {
            font-weight: 700;
            color: #172033;
            cursor: pointer;
        }

        .customer-name:hover {
            color: #1687c9;
        }

        .customer-contact {
            color: #64748b;
            font-size: 12px;
            margin-top: 3px;
        }

        .location-text {
            color: #475569;
        }

        .price-text {
            font-weight: 700;
            white-space: nowrap;
        }

        .balance-debt {
            color: #dc2626;
            font-weight: 700;
        }

        .balance-clear {
            color: #16a34a;
            font-weight: 700;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 5px 9px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
        }

        .status-active {
            background: #dcfce7;
            color: #15803d;
        }

        .status-inactive {
            background: #f1f5f9;
            color: #64748b;
        }

        .row-actions {
            display: flex;
            gap: 6px;
        }

        .empty-state {
            padding: 42px 20px;
            text-align: center;
            color: #64748b;
        }

        .empty-state strong {
            display: block;
            color: #334155;
            margin-bottom: 5px;
        }

        /* Folders */

        .folders-grid {
            padding: 18px;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 14px;
        }

        .folder-card {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            background: #fbfdff;
            overflow: hidden;
            transition: 0.15s ease;
        }

        .folder-card.drag-over {
            border-color: #1687c9;
            background: #eff8fd;
            box-shadow: 0 0 0 3px rgba(22, 135, 201, 0.08);
        }

        .folder-header {
            padding: 13px 14px;
            display: flex;
            align-items: center;
            gap: 9px;
            border-bottom: 1px solid #edf2f7;
        }

        .folder-icon {
            font-size: 21px;
        }

        .folder-title {
            flex: 1;
            min-width: 0;
        }

        .folder-title strong {
            display: block;
            font-size: 14px;
            color: #1e293b;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .folder-title small {
            color: #94a3b8;
            font-size: 11px;
        }

        .folder-menu {
            display: flex;
            gap: 4px;
        }

        .icon-btn {
            width: 29px;
            height: 29px;
            border: 0;
            border-radius: 7px;
            background: #f1f5f9;
            color: #475569;
            cursor: pointer;
            font-size: 13px;
        }

        .icon-btn:hover {
            background: #e2e8f0;
        }

        .folder-children {
            padding: 10px;
        }

        .subfolder {
            border: 1px solid #e5e7eb;
            border-radius: 9px;
            margin-bottom: 8px;
            background: #fff;
            overflow: hidden;
        }

        .subfolder:last-child {
            margin-bottom: 0;
        }

        .subfolder-header {
            padding: 9px 10px;
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }

        .subfolder-header:hover {
            background: #f8fafc;
        }

        .subfolder-name {
            flex: 1;
            font-size: 13px;
            font-weight: 600;
            color: #334155;
        }

        .subfolder-count {
            color: #94a3b8;
            font-size: 11px;
        }

        .subfolder-actions {
            display: flex;
            gap: 4px;
        }

        .subfolder-content {
            padding: 0 10px 10px;
            display: grid;
            gap: 6px;
        }

        .subfolder-content.nested {
            margin-left: 15px;
        }

        .folder-customer {
            padding: 8px 10px;
            border-radius: 7px;
            background: #f8fafc;
            border: 1px solid #edf2f7;
            font-size: 12px;
            cursor: grab;
        }

        .folder-customer:active {
            cursor: grabbing;
        }

        .folder-customer strong {
            display: block;
            color: #334155;
        }

        .folder-customer small {
            color: #94a3b8;
        }

        .folder-empty {
            padding: 14px 8px;
            text-align: center;
            color: #94a3b8;
            font-size: 12px;
        }

        .unassigned {
            margin: 0 18px 18px;
            padding: 16px;
            border: 1px dashed #cbd5e1;
            border-radius: 11px;
            background: #f8fafc;
        }

        .unassigned.drag-over {
            border-color: #1687c9;
            background: #eff8fd;
        }

        .unassigned-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .unassigned-header strong {
            font-size: 14px;
        }

        .unassigned-header span {
            color: #94a3b8;
            font-size: 11px;
        }

        .unassigned-list {
            display: flex;
            flex-wrap: wrap;
            gap: 7px;
        }

        .unassigned-customer {
            padding: 7px 10px;
            border: 1px solid #e2e8f0;
            background: #fff;
            border-radius: 7px;
            font-size: 12px;
            cursor: grab;
        }

        /* Modals */

        .customer-modal {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.48);
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
            z-index: 9999;
        }

        .customer-modal.show {
            display: flex;
        }

        .customer-modal-box {
            background: #fff;
            width: min(700px, 100%);
            max-height: 90vh;
            overflow-y: auto;
            border-radius: 15px;
            box-shadow: 0 25px 60px rgba(15, 23, 42, 0.20);
        }

        .customer-modal-box.large {
            width: min(1000px, 100%);
        }

        .modal-header {
            padding: 18px 20px;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
        }

        .modal-header h3 {
            margin: 0;
            font-size: 18px;
        }

        .modal-close {
            border: 0;
            background: #f1f5f9;
            width: 32px;
            height: 32px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 18px;
            color: #475569;
        }

        .modal-body {
            padding: 20px;
        }

        .modal-footer {
            padding: 15px 20px;
            border-top: 1px solid #e5e7eb;
            display: flex;
            justify-content: flex-end;
            gap: 9px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 15px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .form-group.full {
            grid-column: 1 / -1;
        }

        .form-group label {
            font-size: 12px;
            font-weight: 700;
            color: #475569;
        }

        .form-control {
            width: 100%;
            box-sizing: border-box;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            padding: 10px 11px;
            font-size: 13px;
            outline: none;
            background: #fff;
        }

        .form-control:focus {
            border-color: #1687c9;
            box-shadow: 0 0 0 3px rgba(22, 135, 201, 0.09);
        }

        textarea.form-control {
            resize: vertical;
            min-height: 80px;
        }

        .profile-header {
            padding: 20px;
            background: #f8fafc;
            border-bottom: 1px solid #e5e7eb;
        }

        .profile-name {
            font-size: 23px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .profile-location {
            color: #64748b;
            font-size: 13px;
        }

        .profile-summary {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 10px;
            padding: 16px 20px;
            border-bottom: 1px solid #e5e7eb;
        }

        .profile-stat {
            padding: 12px;
            background: #f8fafc;
            border-radius: 9px;
        }

        .profile-stat small {
            display: block;
            color: #64748b;
            font-size: 10px;
            margin-bottom: 4px;
        }

        .profile-stat strong {
            font-size: 16px;
        }

        .profile-section {
            padding: 18px 20px;
            border-bottom: 1px solid #edf0f2;
        }

        .profile-section:last-child {
            border-bottom: 0;
        }

        .profile-section h4 {
            margin: 0 0 12px;
            font-size: 14px;
        }

        .profile-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }

        .profile-table th {
            text-align: left;
            color: #64748b;
            background: #f8fafc;
            padding: 8px;
            font-size: 11px;
        }

        .profile-table td {
            padding: 9px 8px;
            border-top: 1px solid #f1f5f9;
        }

        .loading {
            padding: 35px;
            text-align: center;
            color: #64748b;
        }

        .toast {
            position: fixed;
            right: 22px;
            bottom: 22px;
            background: #172033;
            color: #fff;
            padding: 11px 15px;
            border-radius: 9px;
            font-size: 13px;
            box-shadow: 0 10px 30px rgba(0,0,0,.18);
            opacity: 0;
            transform: translateY(10px);
            pointer-events: none;
            transition: .2s ease;
            z-index: 10000;
        }

        .toast.show {
            opacity: 1;
            transform: translateY(0);
        }

        .toast.error {
            background: #b91c1c;
        }

        @media (max-width: 800px) {
            .customers-page {
                padding: 18px;
            }

            .customers-header {
                flex-direction: column;
            }

            .customer-actions {
                width: 100%;
            }

            .customer-actions .customer-btn {
                flex: 1;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-group.full {
                grid-column: auto;
            }

            .profile-summary {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>

<?php
$pageRoot = '';
$assetRoot = '../';
require '../includes/sidebar.php';
?>

<main class="main">

    <!-- Customers Topbar -->
    <header class="topbar">

        <div class="topbar-left"></div>

        <div class="topbar-user">

            👤

            <?= htmlspecialchars(
                $_SESSION['full_name'] ?? 'Marcid Blue Admin'
            ) ?>

        </div>

    </header>

    <div class="customers-page">

        <div class="customers-header">

            <div class="customers-title">

                <h1>Customers</h1>

                <p>
                    Manage customer profiles, pricing, locations, and account history.
                </p>

            </div>

            <div class="customer-actions">

                <button
                    type="button"
                    class="customer-btn secondary"
                    id="refreshCustomers"
                >
                    ↻ Refresh
                </button>

                <button
                    type="button"
                    class="customer-btn primary"
                    id="addCustomerBtn"
                >
                    + Add Customer
                </button>

            </div>

        </div>


        <!-- =========================================================
             FOLDERS
             ========================================================= -->

        <section class="customers-card">

            <div class="customers-card-header">

                <div>

                    <h2>Folders</h2>

                    <span>
                        Organize customers by location or area.
                    </span>

                </div>

                <button
                    type="button"
                    class="customer-btn primary small"
                    id="addFolderBtn"
                >
                    + Add Folder
                </button>

            </div>


            <!-- Folder Tree / Drag & Drop Area -->

            <div
                id="foldersGrid"
                class="folders-grid"
            >

                <div class="loading">
                    Loading folders...
                </div>

            </div>


            <!-- Unassigned Customers -->

            <div
                id="unassignedDropZone"
                class="unassigned"
            >

                <div class="unassigned-header">

                    <strong>
                        Unassigned Customers
                    </strong>

                    <span>
                        Drag customers here to remove their folder
                    </span>

                </div>

                <div
                    id="unassignedList"
                    class="unassigned-list"
                ></div>

            </div>

        </section>


        <!-- =========================================================
             ALL CUSTOMERS
             ========================================================= -->

        <section class="customers-card">

            <div class="customers-card-header">

                <div>

                    <h2>All Customers</h2>

                    <span id="customerCount">
                        Loading...
                    </span>

                </div>

            </div>


            <!-- Search -->

            <div class="customers-toolbar">

                <input
                    type="text"
                    id="customerSearch"
                    class="customers-search"
                    placeholder="Search customer, contact number, or address..."
                    autocomplete="off"
                >

            </div>


            <!-- Customer Table -->

            <div class="customers-table-wrap">

                <table class="customers-table">

                    <thead>

                        <tr>

                            <th>
                                Customer
                            </th>

                            <th>
                                Location
                            </th>

                            <th>
                                Price / Gal
                            </th>

                            <th>
                                Last Order
                            </th>

                            <th>
                                Balance
                            </th>

                            <th>
                                Status
                            </th>

                            <th>
                                Actions
                            </th>

                        </tr>

                    </thead>

                    <tbody id="customersTableBody">

                        <tr>

                            <td colspan="7">

                                <div class="loading">
                                    Loading customers...
                                </div>

                            </td>

                        </tr>

                    </tbody>

                </table>

            </div>

        </section>

    </div>

</main>

<!-- Customer Modal -->
<div class="customer-modal" id="customerModal">

    <div class="customer-modal-box">

        <div class="modal-header">
            <h3 id="customerModalTitle">Add Customer</h3>

            <button
                type="button"
                class="modal-close"
                data-close-modal="customerModal"
            >
                ×
            </button>
        </div>

        <form id="customerForm">

            <div class="modal-body">

                <input type="hidden" name="customer_id" id="customerId">

                <div class="form-grid">

                    <div class="form-group full">
                        <label for="customerName">Customer Name *</label>

                        <input
                            type="text"
                            class="form-control"
                            id="customerName"
                            name="customer_name"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="customerPrice">Price per Gallon *</label>

                        <input
                            type="number"
                            class="form-control"
                            id="customerPrice"
                            name="gallon_price"
                            min="0.01"
                            step="0.01"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="customerStatus">Status</label>

                        <select
                            class="form-control"
                            id="customerStatus"
                            name="status"
                        >
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="customerContact">Contact Number</label>

                        <input
                            type="text"
                            class="form-control"
                            id="customerContact"
                            name="contact_number"
                        >
                    </div>

                    <div class="form-group">
                        <label for="customerFolder">Folder / Location</label>

                        <select
                            class="form-control"
                            id="customerFolder"
                            name="folder_id"
                        >
                            <option value="0">Unassigned</option>
                        </select>
                    </div>

                    <div class="form-group full">
                        <label for="customerAddress">Address</label>

                        <textarea
                            class="form-control"
                            id="customerAddress"
                            name="address"
                        ></textarea>
                    </div>

                    <div class="form-group">
                        <label for="effectiveDate">Price Effective Date</label>

                        <input
                            type="date"
                            class="form-control"
                            id="effectiveDate"
                            name="effective_date"
                        >
                    </div>

                    <div class="form-group">
                        <label for="priceReason">Price Change Reason</label>

                        <input
                            type="text"
                            class="form-control"
                            id="priceReason"
                            name="price_reason"
                            placeholder="Optional"
                        >
                    </div>

                </div>

            </div>

            <div class="modal-footer">

                <button
                    type="button"
                    class="customer-btn secondary"
                    data-close-modal="customerModal"
                >
                    Cancel
                </button>

                <button
                    type="submit"
                    class="customer-btn primary"
                >
                    Save Customer
                </button>

            </div>

        </form>

    </div>

</div>

<!-- Folder Modal -->
<div class="customer-modal" id="folderModal">

    <div class="customer-modal-box">

        <div class="modal-header">
            <h3 id="folderModalTitle">Add Folder</h3>

            <button
                type="button"
                class="modal-close"
                data-close-modal="folderModal"
            >
                ×
            </button>
        </div>

        <form id="folderForm">

            <div class="modal-body">

                <input type="hidden" name="folder_id" id="folderId">

                <div class="form-group">
                    <label for="folderName">Folder Name *</label>

                    <input
                        type="text"
                        class="form-control"
                        id="folderName"
                        name="folder_name"
                        required
                    >
                </div>

                <div
                    class="form-group"
                    style="margin-top:15px;"
                >
                    <label for="folderParent">
                        Parent Folder
                    </label>

                    <select
                        class="form-control"
                        id="folderParent"
                        name="parent_folder_id"
                    >
                        <option value="0">No Parent — Root Folder</option>
                    </select>
                </div>

            </div>

            <div class="modal-footer">

                <button
                    type="button"
                    class="customer-btn secondary"
                    data-close-modal="folderModal"
                >
                    Cancel
                </button>

                <button
                    type="submit"
                    class="customer-btn primary"
                >
                    Save Folder
                </button>

            </div>

        </form>

    </div>

</div>

<!-- Profile Modal -->
<div class="customer-modal" id="profileModal">

    <div class="customer-modal-box large">

        <div class="modal-header">
            <h3>Customer Profile</h3>

            <button
                type="button"
                class="modal-close"
                data-close-modal="profileModal"
            >
                ×
            </button>
        </div>

        <div id="profileContent">
            <div class="loading">
                Loading customer profile...
            </div>
        </div>

    </div>

</div>

<div id="toast" class="toast"></div>

<script>
const BACKEND_URL = 'backend/customers-backend.php';

let customers = [];
let folders = [];
let draggedCustomerId = null;

const customerModal = document.getElementById('customerModal');
const folderModal = document.getElementById('folderModal');
const profileModal = document.getElementById('profileModal');

const customerForm = document.getElementById('customerForm');
const folderForm = document.getElementById('folderForm');

const customerSearch = document.getElementById('customerSearch');

function showToast(message, isError = false) {
    const toast = document.getElementById('toast');

    toast.textContent = message;
    toast.classList.toggle('error', isError);
    toast.classList.add('show');

    clearTimeout(window.toastTimer);

    window.toastTimer = setTimeout(() => {
        toast.classList.remove('show');
    }, 2800);
}

function openModal(modal) {
    modal.classList.add('show');
}

function closeModal(modal) {
    modal.classList.remove('show');
}

document.querySelectorAll('[data-close-modal]').forEach(button => {
    button.addEventListener('click', () => {
        const id = button.dataset.closeModal;
        const modal = document.getElementById(id);

        if (modal) {
            closeModal(modal);
        }
    });
});

document.querySelectorAll('.customer-modal').forEach(modal => {
    modal.addEventListener('click', event => {
        if (event.target === modal) {
            closeModal(modal);
        }
    });
});

document.addEventListener('keydown', event => {
    if (event.key === 'Escape') {
        document.querySelectorAll('.customer-modal.show').forEach(modal => {
            closeModal(modal);
        });
    }
});

async function api(action, data = {}) {
    const formData = new FormData();

    formData.append('action', action);

    Object.entries(data).forEach(([key, value]) => {
        formData.append(key, value ?? '');
    });

    const response = await fetch(BACKEND_URL, {
        method: 'POST',
        body: formData
    });

    const text = await response.text();

    let result;

    try {
        result = JSON.parse(text);
    } catch (error) {
        console.error('Backend response:', text);
        throw new Error('The server returned an invalid response.');
    }

    if (!response.ok || !result.success) {
        throw new Error(result.message || 'Request failed.');
    }

    return result;
}

function money(value) {
    const amount = Number(value || 0);

    return '₱' + amount.toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

function formatDate(value) {
    if (!value) {
        return '—';
    }

    const raw = String(value).split(' ')[0];

    const parts = raw.split('-');

    if (parts.length !== 3) {
        return value;
    }

    return `${parts[1]}/${parts[2]}/${parts[0]}`;
}

function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function customerLocation(customer) {
    return customer.folder_name
        ? escapeHtml(customer.folder_name)
        : '<span style="color:#94a3b8;">Unassigned</span>';
}

function renderCustomers() {
    const tbody = document.getElementById('customersTableBody');
    const search = customerSearch.value.trim().toLowerCase();

    const filtered = customers.filter(customer => {
        if (!search) {
            return true;
        }

        return [
            customer.customer_name,
            customer.contact_number,
            customer.address,
            customer.folder_name
        ]
            .filter(Boolean)
            .some(value =>
                String(value).toLowerCase().includes(search)
            );
    });

    document.getElementById('customerCount').textContent =
        `${filtered.length} customer${filtered.length === 1 ? '' : 's'}`;

    if (!filtered.length) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7">
                    <div class="empty-state">
                        <strong>No customers found</strong>
                        Try another search or add a new customer.
                    </div>
                </td>
            </tr>
        `;

        return;
    }

    tbody.innerHTML = filtered.map(customer => {

        const debt = Number(customer.debt_amount || 0);

        const balanceHtml = debt > 0
            ? `<span class="balance-debt">${money(debt)}</span>`
            : `<span class="balance-clear">₱0.00</span>`;

        const statusClass =
            customer.status === 'Inactive'
                ? 'status-inactive'
                : 'status-active';

        return `
            <tr
                draggable="true"
                data-customer-id="${customer.customer_id}"
                class="customer-row"
            >
                <td>
                    <div
                        class="customer-name"
                        onclick="openCustomerProfile(${customer.customer_id})"
                    >
                        ${escapeHtml(customer.customer_name)}
                    </div>

                    ${
                        customer.contact_number
                            ? `<div class="customer-contact">${escapeHtml(customer.contact_number)}</div>`
                            : ''
                    }
                </td>

                <td class="location-text">
                    ${customerLocation(customer)}
                </td>

                <td>
                    <span class="price-text">
                        ${money(customer.gallon_price)}
                    </span>
                </td>

                <td>
                    ${formatDate(customer.last_order)}
                </td>

                <td>
                    ${balanceHtml}
                </td>

                <td>
                    <span class="status-badge ${statusClass}">
                        ${escapeHtml(customer.status || 'Active')}
                    </span>
                </td>

                <td>
                    <div class="row-actions">
                        <button
                            type="button"
                            class="customer-btn secondary small"
                            onclick="editCustomer(${customer.customer_id})"
                        >
                            Edit
                        </button>

                        <button
                            type="button"
                            class="customer-btn danger small"
                            onclick="deleteCustomer(${customer.customer_id})"
                        >
                            Delete
                        </button>
                    </div>
                </td>
            </tr>
        `;
    }).join('');

    document.querySelectorAll('.customer-row').forEach(row => {
        row.addEventListener('dragstart', event => {
            draggedCustomerId = Number(row.dataset.customerId);

            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData(
                'text/plain',
                String(draggedCustomerId)
            );
        });

        row.addEventListener('dragend', () => {
            draggedCustomerId = null;
        });
    });
}

function getChildren(parentId) {
    return folders
        .filter(folder =>
            Number(folder.parent_folder_id || 0) === Number(parentId || 0)
        )
        .sort((a, b) =>
            String(a.folder_name).localeCompare(String(b.folder_name))
        );
}

function customersInFolder(folderId) {
    return customers.filter(
        customer => Number(customer.folder_id || 0) === Number(folderId)
    );
}

function renderFolderCustomers(folderId) {
    const folderCustomers = customersInFolder(folderId);

    if (!folderCustomers.length) {
        return `
            <div class="folder-empty">
                Drop customers here
            </div>
        `;
    }

    return folderCustomers.map(customer => `
        <div
            class="folder-customer"
            draggable="true"
            data-customer-id="${customer.customer_id}"
        >
            <strong>${escapeHtml(customer.customer_name)}</strong>
            <small>${money(customer.gallon_price)} / gallon</small>
        </div>
    `).join('');
}

function renderFolderTree(parentId, depth = 0) {
    const children = getChildren(parentId);

    if (!children.length) {
        return '';
    }

    return children.map(folder => {

        const childFolders = getChildren(folder.folder_id);

        return `
            <div
                class="subfolder"
                data-folder-id="${folder.folder_id}"
                style="margin-left:${Math.min(depth, 4) * 12}px;"
            >

                <div class="subfolder-header">

                    <span>📁</span>

                    <span class="subfolder-name">
                        ${escapeHtml(folder.folder_name)}
                    </span>

                    <span class="subfolder-count">
                        ${Number(folder.customer_count || 0)}
                    </span>

                    <div class="subfolder-actions">

                        <button
                            type="button"
                            class="icon-btn"
                            title="Add subfolder"
                            onclick="addSubfolder(${folder.folder_id})"
                        >
                            +
                        </button>

                        <button
                            type="button"
                            class="icon-btn"
                            title="Rename"
                            onclick="editFolder(${folder.folder_id})"
                        >
                            ✎
                        </button>

                        <button
                            type="button"
                            class="icon-btn"
                            title="Delete"
                            onclick="deleteFolder(${folder.folder_id})"
                        >
                            ×
                        </button>

                    </div>

                </div>

                <div
                    class="subfolder-content"
                    data-drop-folder="${folder.folder_id}"
                >
                    ${renderFolderCustomers(folder.folder_id)}

                    ${
                        childFolders.length
                            ? renderFolderTree(folder.folder_id, depth + 1)
                            : ''
                    }
                </div>

            </div>
        `;
    }).join('');
}

function renderFolders() {
    const grid = document.getElementById('foldersGrid');

    const roots = getChildren(0);

    if (!roots.length) {
        grid.innerHTML = `
            <div class="empty-state" style="grid-column:1/-1;">
                <strong>No folders yet</strong>
                Create a folder to start organizing customers.
            </div>
        `;

        renderUnassigned();
        populateFolderSelects();

        return;
    }

    grid.innerHTML = roots.map(folder => {

        const folderCustomers = customersInFolder(folder.folder_id);

        return `
            <div
                class="folder-card"
                data-folder-id="${folder.folder_id}"
            >

                <div class="folder-header">

                    <span class="folder-icon">📁</span>

                    <div class="folder-title">
                        <strong>${escapeHtml(folder.folder_name)}</strong>
                        <small>
                            ${folderCustomers.length}
                            direct customer${folderCustomers.length === 1 ? '' : 's'}
                        </small>
                    </div>

                    <div class="folder-menu">

                        <button
                            type="button"
                            class="icon-btn"
                            title="Add subfolder"
                            onclick="addSubfolder(${folder.folder_id})"
                        >
                            +
                        </button>

                        <button
                            type="button"
                            class="icon-btn"
                            title="Rename"
                            onclick="editFolder(${folder.folder_id})"
                        >
                            ✎
                        </button>

                        <button
                            type="button"
                            class="icon-btn"
                            title="Delete"
                            onclick="deleteFolder(${folder.folder_id})"
                        >
                            ×
                        </button>

                    </div>

                </div>

                <div
                    class="folder-children"
                    data-drop-folder="${folder.folder_id}"
                >
                    ${renderFolderCustomers(folder.folder_id)}

                    ${renderFolderTree(folder.folder_id)}
                </div>

            </div>
        `;
    }).join('');

    setupFolderDropZones();
    renderUnassigned();
    populateFolderSelects();
}

function renderUnassigned() {
    const list = document.getElementById('unassignedList');

    const unassigned = customers.filter(
        customer => !customer.folder_id
    );

    if (!unassigned.length) {
        list.innerHTML = `
            <span style="color:#94a3b8;font-size:12px;">
                No unassigned customers.
            </span>
        `;

        return;
    }

    list.innerHTML = unassigned.map(customer => `
        <div
            class="unassigned-customer"
            draggable="true"
            data-customer-id="${customer.customer_id}"
        >
            ${escapeHtml(customer.customer_name)}
        </div>
    `).join('');

    list.querySelectorAll('[data-customer-id]').forEach(item => {
        item.addEventListener('dragstart', event => {
            draggedCustomerId = Number(item.dataset.customerId);

            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData(
                'text/plain',
                String(draggedCustomerId)
            );
        });
    });
}

function setupFolderDropZones() {
    document.querySelectorAll('[data-drop-folder]').forEach(zone => {

        zone.addEventListener('dragover', event => {
            event.preventDefault();

            event.dataTransfer.dropEffect = 'move';

            const card = zone.closest('.folder-card, .subfolder');

            if (card) {
                card.classList.add('drag-over');
            }
        });

        zone.addEventListener('dragleave', event => {
            const card = zone.closest('.folder-card, .subfolder');

            if (
                card &&
                !card.contains(event.relatedTarget)
            ) {
                card.classList.remove('drag-over');
            }
        });

        zone.addEventListener('drop', async event => {
            event.preventDefault();

            const folderId = Number(zone.dataset.dropFolder || 0);
            const customerId = Number(
                event.dataTransfer.getData('text/plain')
                || draggedCustomerId
                || 0
            );

            const card = zone.closest('.folder-card, .subfolder');

            if (card) {
                card.classList.remove('drag-over');
            }

            if (!customerId) {
                return;
            }

            await moveCustomer(customerId, folderId);
        });
    });

    const unassignedZone =
        document.getElementById('unassignedDropZone');

    unassignedZone.addEventListener('dragover', event => {
        event.preventDefault();

        event.dataTransfer.dropEffect = 'move';

        unassignedZone.classList.add('drag-over');
    });

    unassignedZone.addEventListener('dragleave', event => {
        if (!unassignedZone.contains(event.relatedTarget)) {
            unassignedZone.classList.remove('drag-over');
        }
    });

    unassignedZone.addEventListener('drop', async event => {
        event.preventDefault();

        unassignedZone.classList.remove('drag-over');

        const customerId = Number(
            event.dataTransfer.getData('text/plain')
            || draggedCustomerId
            || 0
        );

        if (!customerId) {
            return;
        }

        await moveCustomer(customerId, 0);
    });
}

function populateFolderSelects() {
    const customerSelect =
        document.getElementById('customerFolder');

    const parentSelect =
        document.getElementById('folderParent');

    const currentCustomerFolder =
        customerSelect.value;

    const currentParent =
        parentSelect.value;

    const options = folders
        .sort((a, b) =>
            String(a.folder_name).localeCompare(
                String(b.folder_name)
            )
        )
        .map(folder => {

            const path = getFolderPath(Number(folder.folder_id));

            return `
                <option value="${folder.folder_id}">
                    ${escapeHtml(path)}
                </option>
            `;
        })
        .join('');

    customerSelect.innerHTML =
        `<option value="0">Unassigned</option>${options}`;

    parentSelect.innerHTML =
        `<option value="0">No Parent — Root Folder</option>${options}`;

    if (
        currentCustomerFolder &&
        [...customerSelect.options].some(
            option => option.value === currentCustomerFolder
        )
    ) {
        customerSelect.value = currentCustomerFolder;
    }

    if (
        currentParent &&
        [...parentSelect.options].some(
            option => option.value === currentParent
        )
    ) {
        parentSelect.value = currentParent;
    }
}

function getFolderPath(folderId) {
    const parts = [];
    let currentId = Number(folderId);

    let guard = 0;

    while (currentId && guard < 100) {
        const folder = folders.find(
            item => Number(item.folder_id) === currentId
        );

        if (!folder) {
            break;
        }

        parts.unshift(folder.folder_name);

        currentId = Number(folder.parent_folder_id || 0);
        guard++;
    }

    return parts.join(' / ');
}

async function loadCustomers() {
    try {
        const result = await api('list');

        customers = result.customers || [];

        renderCustomers();
        renderFolders();

    } catch (error) {
        console.error(error);

        document.getElementById('customersTableBody').innerHTML = `
            <tr>
                <td colspan="7">
                    <div class="empty-state">
                        <strong>Unable to load customers</strong>
                        ${escapeHtml(error.message)}
                    </div>
                </td>
            </tr>
        `;

        showToast(error.message, true);
    }
}

async function loadFolders() {
    try {
        const result = await api('folders');

        folders = result.folders || [];

        renderFolders();

    } catch (error) {
        console.error(error);

        document.getElementById('foldersGrid').innerHTML = `
            <div class="empty-state" style="grid-column:1/-1;">
                <strong>Unable to load folders</strong>
                ${escapeHtml(error.message)}
            </div>
        `;

        showToast(error.message, true);
    }
}

async function loadAll() {
    await Promise.all([
        loadCustomers(),
        loadFolders()
    ]);
}

function resetCustomerForm() {
    customerForm.reset();

    document.getElementById('customerId').value = '';
    document.getElementById('customerStatus').value = 'Active';
    document.getElementById('customerFolder').value = '0';

    const today = new Date();

    const year = today.getFullYear();
    const month = String(today.getMonth() + 1).padStart(2, '0');
    const day = String(today.getDate()).padStart(2, '0');

    document.getElementById('effectiveDate').value =
        `${year}-${month}-${day}`;
}

function openAddCustomer() {
    resetCustomerForm();

    document.getElementById('customerModalTitle').textContent =
        'Add Customer';

    openModal(customerModal);
}

async function editCustomer(id) {
    const customer = customers.find(
        item => Number(item.customer_id) === Number(id)
    );

    if (!customer) {
        showToast('Customer not found.', true);
        return;
    }

    resetCustomerForm();

    document.getElementById('customerModalTitle').textContent =
        'Edit Customer';

    document.getElementById('customerId').value =
        customer.customer_id;

    document.getElementById('customerName').value =
        customer.customer_name || '';

    document.getElementById('customerPrice').value =
        customer.gallon_price || '';

    document.getElementById('customerStatus').value =
        customer.status || 'Active';

    document.getElementById('customerContact').value =
        customer.contact_number || '';

    document.getElementById('customerAddress').value =
        customer.address || '';

    document.getElementById('customerFolder').value =
        customer.folder_id || '0';

    openModal(customerModal);
}

customerForm.addEventListener('submit', async event => {
    event.preventDefault();

    const submitButton =
        customerForm.querySelector('button[type="submit"]');

    submitButton.disabled = true;
    submitButton.textContent = 'Saving...';

    try {
        const formData = new FormData(customerForm);

        const data = {};

        formData.forEach((value, key) => {
            data[key] = value;
        });

        const result = await api('save_customer', data);

        closeModal(customerModal);

        showToast(
            result.customer?.customer_id
                ? 'Customer saved successfully.'
                : 'Customer saved.'
        );

        await loadAll();

    } catch (error) {
        console.error(error);
        showToast(error.message, true);

    } finally {
        submitButton.disabled = false;
        submitButton.textContent = 'Save Customer';
    }
});

async function deleteCustomer(id) {
    const customer = customers.find(
        item => Number(item.customer_id) === Number(id)
    );

    if (!customer) {
        return;
    }

    const confirmed = confirm(
        `Delete "${customer.customer_name}"?\n\n` +
        `If this customer already has transaction history, ` +
        `the customer will be marked Inactive instead of being deleted.`
    );

    if (!confirmed) {
        return;
    }

    try {
        const result = await api('delete_customer', {
            customer_id: id
        });

        showToast(result.message || 'Customer deleted.');

        await loadAll();

    } catch (error) {
        console.error(error);
        showToast(error.message, true);
    }
}

async function moveCustomer(customerId, folderId) {
    try {
        await api('move_customer', {
            customer_id: customerId,
            folder_id: folderId
        });

        showToast(
            folderId
                ? 'Customer moved successfully.'
                : 'Customer moved to Unassigned.'
        );

        await loadAll();

    } catch (error) {
        console.error(error);
        showToast(error.message, true);
    }
}

function resetFolderForm() {
    folderForm.reset();

    document.getElementById('folderId').value = '';
    document.getElementById('folderParent').value = '0';
}

function openAddFolder(parentId = 0) {
    resetFolderForm();

    document.getElementById('folderModalTitle').textContent =
        parentId
            ? 'Add Subfolder'
            : 'Add Folder';

    document.getElementById('folderParent').value =
        String(parentId || 0);

    openModal(folderModal);
}

function addSubfolder(parentId) {
    openAddFolder(parentId);
}

function editFolder(id) {
    const folder = folders.find(
        item => Number(item.folder_id) === Number(id)
    );

    if (!folder) {
        showToast('Folder not found.', true);
        return;
    }

    resetFolderForm();

    document.getElementById('folderModalTitle').textContent =
        'Rename / Move Folder';

    document.getElementById('folderId').value =
        folder.folder_id;

    document.getElementById('folderName').value =
        folder.folder_name;

    document.getElementById('folderParent').value =
        folder.parent_folder_id || '0';

    openModal(folderModal);
}

folderForm.addEventListener('submit', async event => {
    event.preventDefault();

    const submitButton =
        folderForm.querySelector('button[type="submit"]');

    submitButton.disabled = true;
    submitButton.textContent = 'Saving...';

    try {
        const formData = new FormData(folderForm);

        const data = {};

        formData.forEach((value, key) => {
            data[key] = value;
        });

        await api('save_folder', data);

        closeModal(folderModal);

        showToast('Folder saved successfully.');

        await loadFolders();

    } catch (error) {
        console.error(error);
        showToast(error.message, true);

    } finally {
        submitButton.disabled = false;
        submitButton.textContent = 'Save Folder';
    }
});

async function deleteFolder(id) {
    const folder = folders.find(
        item => Number(item.folder_id) === Number(id)
    );

    if (!folder) {
        return;
    }

    const confirmed = confirm(
        `Delete the folder "${folder.folder_name}"?\n\n` +
        `Customers will NOT be deleted.\n` +
        `Customers inside this folder will move to its parent or Unassigned, ` +
        `and subfolders will move up one level.`
    );

    if (!confirmed) {
        return;
    }

    try {
        await api('delete_folder', {
            folder_id: id
        });

        showToast('Folder deleted.');

        await loadAll();

    } catch (error) {
        console.error(error);
        showToast(error.message, true);
    }
}

async function openCustomerProfile(id) {
    openModal(profileModal);

    document.getElementById('profileContent').innerHTML = `
        <div class="loading">
            Loading customer profile...
        </div>
    `;

    try {
        const result = await api('profile', {
            customer_id: id
        });

        renderCustomerProfile(result);

    } catch (error) {
        console.error(error);

        document.getElementById('profileContent').innerHTML = `
            <div class="empty-state">
                <strong>Unable to load profile</strong>
                ${escapeHtml(error.message)}
            </div>
        `;
    }
}

function renderCustomerProfile(result) {
    const customer = result.customer;
    const summary = result.summary || {};

    const orders = result.orders || [];
    const payments = result.payments || [];
    const prices = result.price_history || [];
    const credits = result.credits || [];

    const location =
        customer.folder_path ||
        'Unassigned';

    let html = `
        <div class="profile-header">

            <div class="profile-name">
                ${escapeHtml(customer.customer_name)}
            </div>

            <div class="profile-location">
                📁 ${escapeHtml(location)}
            </div>

        </div>

        <div class="profile-summary">

            <div class="profile-stat">
                <small>Price / Gallon</small>
                <strong>${money(customer.gallon_price)}</strong>
            </div>

            <div class="profile-stat">
                <small>Total Orders</small>
                <strong>${Number(summary.order_count || 0)}</strong>
            </div>

            <div class="profile-stat">
                <small>Outstanding Debt</small>
                <strong>${money(summary.debt)}</strong>
            </div>

            <div class="profile-stat">
                <small>Credit</small>
                <strong>${money(summary.credit)}</strong>
            </div>

        </div>

        <div class="profile-section">

            <h4>Customer Information</h4>

            <table class="profile-table">
                <tbody>

                    <tr>
                        <td><strong>Contact</strong></td>
                        <td>${escapeHtml(customer.contact_number || '—')}</td>
                    </tr>

                    <tr>
                        <td><strong>Address</strong></td>
                        <td>${escapeHtml(customer.address || '—')}</td>
                    </tr>

                    <tr>
                        <td><strong>Status</strong></td>
                        <td>${escapeHtml(customer.status || 'Active')}</td>
                    </tr>

                </tbody>
            </table>

        </div>

        <div class="profile-section">

            <h4>Price History</h4>

            ${
                prices.length
                    ? `
                        <table class="profile-table">
                            <thead>
                                <tr>
                                    <th>Effective Date</th>
                                    <th>Price / Gallon</th>
                                    <th>Reason</th>
                                </tr>
                            </thead>

                            <tbody>
                                ${prices.map(price => `
                                    <tr>
                                        <td>${formatDate(price.effective_date)}</td>
                                        <td>${money(price.price_per_gallon)}</td>
                                        <td>${escapeHtml(price.reason || '—')}</td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    `
                    : `
                        <div class="folder-empty">
                            No price history.
                        </div>
                    `
            }

        </div>

        <div class="profile-section">

            <h4>Order History</h4>

            ${
                orders.length
                    ? `
                        <table class="profile-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>S</th>
                                    <th>R</th>
                                    <th>Amount Due</th>
                                    <th>Paid</th>
                                    <th>Remaining</th>
                                </tr>
                            </thead>

                            <tbody>
                                ${orders.map(order => `
                                    <tr>
                                        <td>${formatDate(order.delivery_date)}</td>
                                        <td>${Number(order.slim_quantity || 0)}</td>
                                        <td>${Number(order.round_quantity || 0)}</td>
                                        <td>${money(order.amount_due)}</td>
                                        <td>${money(order.paid)}</td>
                                        <td>${money(order.remaining)}</td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    `
                    : `
                        <div class="folder-empty">
                            No orders recorded.
                        </div>
                    `
            }

        </div>

        <div class="profile-section">

            <h4>Debt History</h4>

            ${
                orders.filter(order => Number(order.remaining) > 0).length
                    ? `
                        <table class="profile-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Amount Due</th>
                                    <th>Paid</th>
                                    <th>Outstanding</th>
                                </tr>
                            </thead>

                            <tbody>
                                ${orders
                                    .filter(order => Number(order.remaining) > 0)
                                    .map(order => `
                                        <tr>
                                            <td>${formatDate(order.delivery_date)}</td>
                                            <td>${money(order.amount_due)}</td>
                                            <td>${money(order.paid)}</td>
                                            <td>${money(order.remaining)}</td>
                                        </tr>
                                    `)
                                    .join('')}
                            </tbody>
                        </table>
                    `
                    : `
                        <div class="folder-empty">
                            No outstanding debt.
                        </div>
                    `
            }

        </div>

        <div class="profile-section">

            <h4>Payment History</h4>

            ${
                payments.length
                    ? `
                        <table class="profile-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Amount</th>
                                    <th>Method</th>
                                    <th>Location</th>
                                    <th>Notes</th>
                                </tr>
                            </thead>

                            <tbody>
                                ${payments.map(payment => `
                                    <tr>
                                        <td>${formatDate(payment.payment_date)}</td>
                                        <td>${money(payment.amount)}</td>
                                        <td>${escapeHtml(payment.payment_method || '—')}</td>
                                        <td>${escapeHtml(payment.collection_location || '—')}</td>
                                        <td>${escapeHtml(payment.notes || '—')}</td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    `
                    : `
                        <div class="folder-empty">
                            No payment history.
                        </div>
                    `
            }

        </div>

        <div class="profile-section">

            <h4>Credit History</h4>

            ${
                credits.length
                    ? `
                        <table class="profile-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Amount</th>
                                    <th>Method</th>
                                    <th>Notes</th>
                                </tr>
                            </thead>

                            <tbody>
                                ${credits.map(credit => `
                                    <tr>
                                        <td>${formatDate(credit.credit_date)}</td>
                                        <td>${money(credit.amount)}</td>
                                        <td>${escapeHtml(credit.payment_method || '—')}</td>
                                        <td>${escapeHtml(credit.notes || '—')}</td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    `
                    : `
                        <div class="folder-empty">
                            No credit history.
                        </div>
                    `
            }

        </div>
    `;

    document.getElementById('profileContent').innerHTML = html;
}

document.getElementById('addCustomerBtn')
    .addEventListener('click', openAddCustomer);

document.getElementById('addFolderBtn')
    .addEventListener('click', () => openAddFolder(0));

document.getElementById('refreshCustomers')
    .addEventListener('click', loadAll);

customerSearch.addEventListener('input', renderCustomers);

loadAll();
</script>

</body>
</html>

