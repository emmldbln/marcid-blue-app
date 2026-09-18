<?php

declare(strict_types=1);

date_default_timezone_set('Asia/Manila');

require_once '../auth/auth.php';
require_once '../config/database.php';

requireAdmin();

$today = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daily Records - Marcid Blue</title>
    <link rel="stylesheet" href="../assets/css/app.css">
    <style>
        .records-toolbar {
            display:flex;
            align-items:flex-end;
            justify-content:space-between;
            gap:16px;
            flex-wrap:wrap;
            margin-bottom:20px;
        }

        .records-filters {
            display:flex;
            gap:12px;
            flex-wrap:wrap;
            align-items:flex-end;
        }

        .records-filter {
            min-width:170px;
        }

        .records-actions {
            display:flex;
            gap:8px;
            align-items:center;
        }

        .records-table-wrap {
            overflow-x:auto;
        }

        .records-table {
            width:100%;
            border-collapse:collapse;
        }

        .records-table th,
        .records-table td {
            padding:14px 16px;
            border-bottom:1px solid var(--border);
            text-align:left;
            white-space:nowrap;
        }

        .records-table th {
            color:var(--text-muted);
            font-size:12px;
            font-weight:700;
            text-transform:uppercase;
            letter-spacing:.04em;
        }

        .records-table tbody tr:hover {
            background:var(--background);
        }

        .record-date {
            font-weight:700;
            color:var(--text);
        }

        .record-id {
            color:var(--text-muted);
            font-size:13px;
        }

        .record-empty {
            padding:48px 20px;
            text-align:center;
            color:var(--text-muted);
        }

        .record-empty strong {
            display:block;
            margin-bottom:6px;
            color:var(--text);
            font-size:15px;
        }

        .records-pagination {
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:12px;
            margin-top:16px;
        }

        .records-page-info {
            color:var(--text-muted);
            font-size:13px;
        }

        .record-loading {
            padding:36px 20px;
            text-align:center;
            color:var(--text-muted);
        }

        .status-open { color:var(--warning); }
        .status-saved { color:var(--success); }
        .status-reopened { color:var(--primary); }
        .status-unknown { color:var(--text-muted); }

        @media (max-width: 700px) {
            .records-filter {
                width:100%;
                min-width:0;
            }

            .records-filters {
                width:100%;
            }

            .records-toolbar .btn {
                width:100%;
            }
        }
    </style>
</head>

<body>
<div class="app">
    <aside class="sidebar">
        <div class="sidebar-brand">
            <div class="brand-icon">
                <img src="../assets/images/mb-logo.png" alt="Marcid Blue Logo">
            </div>
        </div>

        <nav class="sidebar-nav">
            <div class="nav-section-title">Main</div>

            <a href="home.php" class="nav-item">
                🏠
                <span>Home</span>
            </a>

            <a href="#" class="nav-item">
                👥
                <span>Customers</span>
            </a>

            <a href="daily-records.php" class="nav-item active">
                📅
                <span>Daily Records</span>
            </a>

            <a href="daily-closing.php" class="nav-item">
                🧾
                <span>Daily Closing</span>
            </a>

            <div class="nav-section-title" style="margin-top:25px;">System</div>

            <a href="#" class="nav-item">
                ⚙️
                <span>Settings</span>
            </a>

            <a href="#" class="nav-item">
                🚪
                <span>Logout</span>
            </a>
        </nav>
    </aside>

    <main class="main">
        <header class="topbar">
            <div class="topbar-title">Daily Records</div>
            <div class="topbar-user">
                👤 <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Admin'); ?>
            </div>
        </header>

        <section class="page">
            <div class="page-header">
                <h1 class="page-title">Daily Records</h1>
                <p class="page-subtitle">
                    Review finalized daily records and return to an open day when needed.
                </p>
            </div>

            <div class="card">
                <div class="card-body">
                    <div class="records-toolbar">
                        <div class="records-filters">
                            <div class="form-group records-filter">
                                <label class="form-label" for="recordDate">Date</label>
                                <input
                                    id="recordDate"
                                    class="form-input"
                                    type="date"
                                    value="<?php echo htmlspecialchars($today); ?>"
                                >
                            </div>

                            <div class="form-group records-filter">
                                <label class="form-label" for="recordStatus">Status</label>
                                <select id="recordStatus" class="form-input">
                                    <option value="">All statuses</option>
                                    <option value="Open">Open</option>
                                    <option value="Saved">Saved</option>
                                    <option value="Reopened">Reopened</option>
                                </select>
                            </div>

                            <div class="records-actions">
                                <button type="button" class="btn btn-outline" id="clearRecordFilters">
                                    Clear
                                </button>
                                <button type="button" class="btn btn-primary" id="refreshRecords">
                                    Refresh
                                </button>
                            </div>
                        </div>
                    </div>

                    <div id="recordsLoading" class="record-loading">
                        Loading daily records...
                    </div>

                    <div id="recordsTableWrap" class="records-table-wrap" hidden>
                        <table class="records-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Daily ID</th>
                                    <th>Status</th>
                                    <th>Last Updated</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody id="recordsTableBody"></tbody>
                        </table>

                        <div class="records-pagination">
                            <div class="records-page-info" id="recordsPageInfo"></div>
                            <div class="records-actions">
                                <button type="button" class="btn btn-outline" id="previousPage">
                                    Previous
                                </button>
                                <button type="button" class="btn btn-outline" id="nextPage">
                                    Next
                                </button>
                            </div>
                        </div>
                    </div>

                    <div id="recordsEmpty" class="record-empty" hidden>
                        <strong>No daily records found.</strong>
                        Try another date or clear the filters.
                    </div>

                    <div id="recordsError" class="record-empty" hidden>
                        <strong>Unable to load daily records.</strong>
                        Please refresh the page and try again.
                    </div>
                </div>
            </div>
        </section>
    </main>
</div>

<script>
(function () {
    'use strict';

    const BACKEND_URL = 'backend/daily-records-backend.php';
    const PAGE_SIZE = 20;

    let currentPage = 1;
    let totalPages = 1;

    const dateInput = document.getElementById('recordDate');
    const statusInput = document.getElementById('recordStatus');
    const loading = document.getElementById('recordsLoading');
    const tableWrap = document.getElementById('recordsTableWrap');
    const empty = document.getElementById('recordsEmpty');
    const error = document.getElementById('recordsError');
    const body = document.getElementById('recordsTableBody');
    const pageInfo = document.getElementById('recordsPageInfo');
    const previous = document.getElementById('previousPage');
    const next = document.getElementById('nextPage');

    function escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function statusClass(status) {
        if (status === 'Open') return 'status-open';
        if (status === 'Saved') return 'status-saved';
        if (status === 'Reopened') return 'status-reopened';
        return 'status-unknown';
    }

    function formatDate(value) {
        if (!value) return '—';

        const parts = value.split('-');
        if (parts.length !== 3) return value;

        return new Date(
            Number(parts[0]),
            Number(parts[1]) - 1,
            Number(parts[2])
        ).toLocaleDateString('en-PH', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
    }

    function formatDateTime(value) {
        if (!value) return '—';

        const date = new Date(value.replace(' ', 'T'));

        if (Number.isNaN(date.getTime())) {
            return value;
        }

        return date.toLocaleString('en-PH', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: 'numeric',
            minute: '2-digit'
        });
    }

    function showState(state) {
        loading.hidden = state !== 'loading';
        tableWrap.hidden = state !== 'table';
        empty.hidden = state !== 'empty';
        error.hidden = state !== 'error';
    }

    async function loadRecords() {
        showState('loading');

        const params = new URLSearchParams({
            action: 'list',
            page: String(currentPage),
            page_size: String(PAGE_SIZE)
        });

        if (dateInput.value) {
            params.set('date', dateInput.value);
        }

        if (statusInput.value) {
            params.set('status', statusInput.value);
        }

        try {
            const response = await fetch(
                BACKEND_URL + '?' + params.toString(),
                {
                    credentials: 'same-origin',
                    cache: 'no-store',
                    headers: { Accept: 'application/json' }
                }
            );

            const result = await response.json();

            if (!response.ok || !result.success) {
                throw new Error(result.message || 'Request failed.');
            }

            totalPages = Math.max(1, Number(result.total_pages) || 1);

            body.innerHTML = '';

            if (!Array.isArray(result.records) || result.records.length === 0) {
                pageInfo.textContent = '';
                showState('empty');
                updatePagination();
                return;
            }

            result.records.forEach(function (record) {
                const row = document.createElement('tr');

                const action =
                    record.status === 'Open' || record.status === 'Reopened'
                        ? '<a class="btn btn-outline" href="daily-closing.php">Open Daily Closing</a>'
                        : '<button type="button" class="btn btn-outline record-view" data-id="' +
                          escapeHtml(record.daily_id) +
                          '">View Record</button>';

                row.innerHTML =
                    '<td><div class="record-date">' +
                        escapeHtml(formatDate(record.business_date)) +
                    '</div></td>' +
                    '<td><span class="record-id">#' +
                        escapeHtml(record.daily_id) +
                    '</span></td>' +
                    '<td><span class="badge ' + statusClass(record.status) + '">' +
                        escapeHtml(record.status) +
                    '</span></td>' +
                    '<td>' +
                        escapeHtml(formatDateTime(record.updated_at)) +
                    '</td>' +
                    '<td>' + action + '</td>';

                body.appendChild(row);
            });

            pageInfo.textContent =
                'Page ' + currentPage + ' of ' + totalPages;

            showState('table');
            updatePagination();

        } catch (requestError) {
            console.error(requestError);
            showState('error');
        }
    }

    function updatePagination() {
        previous.disabled = currentPage <= 1;
        next.disabled = currentPage >= totalPages;
    }

    document.getElementById('refreshRecords')
        .addEventListener('click', function () {
            loadRecords();
        });

    document.getElementById('clearRecordFilters')
        .addEventListener('click', function () {
            dateInput.value = '';
            statusInput.value = '';
            currentPage = 1;
            loadRecords();
        });

    statusInput.addEventListener('change', function () {
        currentPage = 1;
        loadRecords();
    });

    dateInput.addEventListener('change', function () {
        currentPage = 1;
        loadRecords();
    });

    previous.addEventListener('click', function () {
        if (currentPage > 1) {
            currentPage--;
            loadRecords();
        }
    });

    next.addEventListener('click', function () {
        if (currentPage < totalPages) {
            currentPage++;
            loadRecords();
        }
    });

    loadRecords();
})();
</script>
</body>
</html>
