<?php

declare(strict_types=1);

date_default_timezone_set('Asia/Manila');

require_once '../auth/auth.php';
require_once '../config/database.php';

requireAdmin();

$currentPage = 'daily-records';

?>
<!DOCTYPE html>
<html lang="en">
<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Daily Records - Marcid Blue</title>

    <link
        rel="stylesheet"
        href="../assets/css/app.css"
    >

    <style>

        /* =========================================================
           DAILY RECORDS
           ========================================================= */

        .records-toolbar {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 24px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        .records-filters {
            display: flex;
            gap: 18px;
            align-items: flex-end;
            flex-wrap: wrap;
            width: 100%;
        }

        .records-filter {
            min-width: 190px;
            margin-bottom: 0;
        }

        .records-filter.date-filter {
            min-width: 190px;
        }

        .records-filter.status-filter {
            min-width: 190px;
        }

        .records-actions {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .records-actions .btn {
            height: 42px;
            white-space: nowrap;
        }

        .records-table-wrap {
            overflow-x: auto;
        }

        .records-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .records-table th,
        .records-table td {
            padding: 14px 18px;
            border-bottom: 1px solid var(--border);
            text-align: left;
            vertical-align: middle;
        }

        .records-table th {
            color: var(--text-muted);
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .04em;
            background: var(--background);
        }

        .records-table tbody tr:hover {
            background: var(--background);
        }

        /* ---------- Column widths ---------- */

        /* ---------- Column widths ---------- */

        .records-table th:nth-child(1),
        .records-table td:nth-child(1) {
            width: 14%;
        }

        .records-table th:nth-child(2),
        .records-table td:nth-child(2) {
            width: 9%;
        }

        .records-table th:nth-child(3),
        .records-table td:nth-child(3) {
            width: 15%;
        }

        .records-table th:nth-child(4),
        .records-table td:nth-child(4) {
            width: 15%;
        }

        .records-table th:nth-child(5),
        .records-table td:nth-child(5) {
            width: 11%;
        }

        .records-table th:nth-child(6),
        .records-table td:nth-child(6) {
            width: 18%;
        }

        .records-table th:nth-child(7),
        .records-table td:nth-child(7) {
            width: 18%;
        }

        .record-date {
            font-weight: 700;
            color: var(--text);
        }

        .record-id {
            color: var(--text-muted);
            font-size: 13px;
        }

        /* ---------- Record Actions ---------- */

        .records-table td:nth-child(7) {
            white-space: nowrap;
        }

        .records-table td:nth-child(7) .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 38px;
            padding: 8px 14px;
            white-space: nowrap;
            flex-shrink: 0;
        }

        /* ---------- Closing Result ---------- */

        .closing-result {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 90px;
            padding: 5px 10px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
            line-height: 1.2;
        }

        .closing-result-balanced {
            background: var(--success-light);
            color: var(--success);
        }

        .closing-result-short {
            background: var(--danger-light);
            color: var(--danger);
        }

        .closing-result-over {
            background: var(--warning-light);
            color: var(--warning);
        }

        .closing-result-pending {
            background: var(--background);
            color: var(--text-muted);
        }

        /* ---------- Driver Remittance ---------- */

        .driver-remittance {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 90px;
            padding: 5px 10px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
            line-height: 1.2;
        }

        .driver-remittance-balanced {
            background: var(--success-light);
            color: var(--success);
        }

        .driver-remittance-short {
            background: var(--danger-light);
            color: var(--danger);
        }

        .driver-remittance-over {
            background: var(--warning-light);
            color: var(--warning);
        }

        .driver-remittance-none {
            background: var(--background);
            color: var(--text-muted);
        }

        /* ---------- Record Status ---------- */

        .status-open {
            color: var(--warning);
        }

        .status-saved {
            color: var(--success);
        }

        .status-reopened {
            color: var(--primary);
        }

        .status-unknown {
            color: var(--text-muted);
        }

        .record-status-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 72px;
        }

        /* ---------- Empty / Loading ---------- */

        .record-empty {
            padding: 48px 20px;
            text-align: center;
            color: var(--text-muted);
        }

        .record-empty strong {
            display: block;
            margin-bottom: 6px;
            color: var(--text);
            font-size: 15px;
        }

        .record-loading {
            padding: 36px 20px;
            text-align: center;
            color: var(--text-muted);
        }

        /* ---------- Pagination ---------- */

        .records-pagination {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-top: 16px;
        }

        .records-page-info {
            color: var(--text-muted);
            font-size: 13px;
        }

        @media (max-width: 900px) {

            .records-filters {
                gap: 12px;
            }

            .records-filter {
                min-width: 160px;
            }

            .records-table {
                min-width: 950px;
            }

        }

        @media (max-width: 700px) {

            .records-toolbar {
                align-items: stretch;
            }

            .records-filters {
                width: 100%;
                flex-direction: column;
                align-items: stretch;
            }

            .records-filter,
            .records-filter.date-filter,
            .records-filter.status-filter {
                width: 100%;
                min-width: 0;
            }

            .records-actions {
                width: 100%;
            }

            .records-actions .btn {
                width: 100%;
            }

        }

    </style>

</head>

<body>

<div class="app">

    <?php require_once '../includes/sidebar.php'; ?>

    <main class="main">

        <header class="topbar">

            <div class="topbar-title">
                Daily Records
            </div>

            <div class="topbar-user">

                👤

                <?php
                echo htmlspecialchars(
                    $_SESSION['full_name'] ?? 'Admin'
                );
                ?>

            </div>

        </header>

        <section class="page">

            <div class="page-header">

                <h1 class="page-title">
                    Daily Records
                </h1>

                <p class="page-subtitle">
                    Review finalized daily records and return to an open day when needed.
                </p>

            </div>

            <div class="card">

                <div class="card-body">

                    <!-- =====================================================
                         FILTER TOOLBAR
                         ===================================================== -->

                    <div class="records-toolbar">

                        <div class="records-filters">

                            <div class="form-group records-filter date-filter">

                                <label
                                    class="form-label"
                                    for="recordDate"
                                >
                                    Date
                                </label>

                                <input
                                    id="recordDate"
                                    class="form-input"
                                    type="date"
                                    value=""
                                >

                            </div>

                            <div class="form-group records-filter status-filter">

                                <label
                                    class="form-label"
                                    for="recordStatus"
                                >
                                    Status
                                </label>

                                <select
                                    id="recordStatus"
                                    class="form-input"
                                >

                                    <option value="">
                                        All statuses
                                    </option>

                                    <option value="Open">
                                        Open
                                    </option>

                                    <option value="Saved">
                                        Saved
                                    </option>

                                    <option value="Reopened">
                                        Reopened
                                    </option>

                                </select>

                            </div>

                            <div class="records-actions">

                                <button
                                    type="button"
                                    class="btn btn-primary"
                                    id="refreshRecords"
                                >
                                    Refresh
                                </button>

                            </div>

                        </div>

                    </div>

                    <!-- =====================================================
                         LOADING
                         ===================================================== -->

                    <div
                        id="recordsLoading"
                        class="record-loading"
                    >
                        Loading daily records...
                    </div>

                    <!-- =====================================================
                         TABLE
                         ===================================================== -->

                    <div
                        id="recordsTableWrap"
                        class="records-table-wrap"
                        hidden
                    >

                        <table class="records-table">

                            <thead>

                                <tr>

                                    <th>
                                        Date
                                    </th>

                                    <th>
                                        Daily ID
                                    </th>

                                    <th>
                                        Closing Result
                                    </th>

                                    <th>
                                        Driver Remittance
                                    </th>

                                    <th>
                                        Status
                                    </th>

                                    <th>
                                        Last Updated
                                    </th>

                                    <th>
                                        Action
                                    </th>

                                </tr>

                            </thead>

                            <tbody id="recordsTableBody"></tbody>

                        </table>

                        <!-- =================================================
                             PAGINATION
                             ================================================= -->

                        <div class="records-pagination">

                            <div
                                class="records-page-info"
                                id="recordsPageInfo"
                            ></div>

                            <div class="records-actions">

                                <button
                                    type="button"
                                    class="btn btn-outline"
                                    id="previousPage"
                                >
                                    Previous
                                </button>

                                <button
                                    type="button"
                                    class="btn btn-outline"
                                    id="nextPage"
                                >
                                    Next
                                </button>

                            </div>

                        </div>

                    </div>

                    <!-- =====================================================
                         EMPTY
                         ===================================================== -->

                    <div
                        id="recordsEmpty"
                        class="record-empty"
                        hidden
                    >

                        <strong>
                            No daily records found.
                        </strong>

                        Try another date or change the filters.

                    </div>

                    <!-- =====================================================
                         ERROR
                         ===================================================== -->

                    <div
                        id="recordsError"
                        class="record-empty"
                        hidden
                    >

                        <strong>
                            Unable to load daily records.
                        </strong>

                        <span id="recordsErrorMessage">
                            Please refresh the page and try again.
                        </span>

                    </div>

                </div>

            </div>

        </section>

    </main>

</div>

<script>

(function () {

    'use strict';

    const BACKEND_URL =
        'backend/daily-records-backend.php';

    const PAGE_SIZE = 20;

    let currentPage = 1;

    let totalPages = 1;

    const dateInput =
        document.getElementById('recordDate');

    const statusInput =
        document.getElementById('recordStatus');

    const loading =
        document.getElementById('recordsLoading');

    const tableWrap =
        document.getElementById('recordsTableWrap');

    const empty =
        document.getElementById('recordsEmpty');

    const error =
        document.getElementById('recordsError');

    const errorMessage =
        document.getElementById('recordsErrorMessage');

    const body =
        document.getElementById('recordsTableBody');

    const pageInfo =
        document.getElementById('recordsPageInfo');

    const previous =
        document.getElementById('previousPage');

    const next =
        document.getElementById('nextPage');


    /* =========================================================
       HTML ESCAPING
       ========================================================= */

    function escapeHtml(value) {

        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');

    }


    /* =========================================================
       STATUS CLASS
       ========================================================= */

    function statusClass(status) {

        if (status === 'Open') {
            return 'status-open';
        }

        if (status === 'Saved') {
            return 'status-saved';
        }

        if (status === 'Reopened') {
            return 'status-reopened';
        }

        return 'status-unknown';

    }


    /* =========================================================
       CLOSING RESULT CLASS
       ========================================================= */

        function closingResultClass(result) {

            const normalized =
                String(result || '').toLowerCase();

            if (normalized === 'balanced') {
                return 'closing-result-balanced';
            }

            if (normalized === 'short') {
                return 'closing-result-short';
            }

            if (normalized === 'over') {
                return 'closing-result-over';
            }

            return 'closing-result-pending';

        }


        /* =========================================================
        CLOSING RESULT LABEL
        ========================================================= */

        function closingResultLabel(result) {

        const normalized =
            String(result || '').trim().toLowerCase();

        if (normalized === 'balanced') {
            return 'Balanced';
        }

        if (normalized === 'short') {
            return 'Short';
        }

        if (normalized === 'over') {
            return 'Over';
        }

        return 'Pending';

    }


    /* =========================================================
    DRIVER REMITTANCE
    ========================================================= */

    function driverRemittanceClass(status) {

        const normalized =
            String(status || '').trim().toLowerCase();

        if (normalized === 'balanced') {
            return 'driver-remittance-balanced';
        }

        if (normalized === 'short') {
            return 'driver-remittance-short';
        }

        if (normalized === 'over') {
            return 'driver-remittance-over';
        }

        return 'driver-remittance-none';

    }


    function driverRemittanceLabel(status) {

        const normalized =
            String(status || '').trim().toLowerCase();

        if (normalized === 'balanced') {
            return 'Balanced';
        }

        if (normalized === 'short') {
            return 'Short';
        }

        if (normalized === 'over') {
            return 'Over';
        }

        return '—';

    }


    /* =========================================================
       DATE FORMAT
       ========================================================= */

    function formatDate(value) {

        if (!value) {
            return '—';
        }

        const parts =
            String(value).split('-');

        if (parts.length !== 3) {
            return value;
        }

        return new Date(
            Number(parts[0]),
            Number(parts[1]) - 1,
            Number(parts[2])
        ).toLocaleDateString(
            'en-PH',
            {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            }
        );

    }


    /* =========================================================
       DATE/TIME FORMAT
       ========================================================= */

    function formatDateTime(value) {

        if (!value) {
            return '—';
        }

        const date =
            new Date(
                String(value).replace(' ', 'T')
            );

        if (Number.isNaN(date.getTime())) {
            return value;
        }

        return date.toLocaleString(
            'en-PH',
            {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: 'numeric',
                minute: '2-digit'
            }
        );

    }


    /* =========================================================
       UI STATE
       ========================================================= */

    function showState(state) {

        loading.hidden =
            state !== 'loading';

        tableWrap.hidden =
            state !== 'table';

        empty.hidden =
            state !== 'empty';

        error.hidden =
            state !== 'error';

    }


    /* =========================================================
       LOAD RECORDS
       ========================================================= */

    async function loadRecords() {

        showState('loading');

        errorMessage.textContent =
            'Please refresh the page and try again.';


        const params =
            new URLSearchParams({
                action: 'list',
                page: String(currentPage),
                page_size: String(PAGE_SIZE)
            });


        /*
         * IMPORTANT:
         *
         * Date is intentionally optional.
         *
         * When empty, the backend receives no date
         * filter and should return all records.
         */

        if (dateInput.value) {

            params.set(
                'date',
                dateInput.value
            );

        }


        if (statusInput.value) {

            params.set(
                'status',
                statusInput.value
            );

        }


        const requestUrl =
            BACKEND_URL +
            '?' +
            params.toString();


        console.log(
            '[Daily Records] Request:',
            requestUrl
        );


        try {

            const response =
                await fetch(
                    requestUrl,
                    {
                        credentials: 'same-origin',
                        cache: 'no-store',
                        headers: {
                            Accept: 'application/json'
                        }
                    }
                );


            /*
             * Read response as text first.
             *
             * This is intentional so that if PHP produces
             * a warning/fatal error instead of JSON, we can
             * see the actual response.
             */

            const responseText =
                await response.text();


            console.log(
                '[Daily Records] HTTP status:',
                response.status
            );

            console.log(
                '[Daily Records] Response:',
                responseText
            );


            let result;


            try {

                result =
                    JSON.parse(responseText);

            } catch (jsonError) {

                console.error(
                    '[Daily Records] Invalid JSON response:',
                    jsonError
                );

                throw new Error(
                    'The Daily Records backend returned an invalid response. Check the browser console for the PHP error.'
                );

            }


            if (
                !response.ok ||
                !result.success
            ) {

                throw new Error(
                    result.message ||
                    'Request failed.'
                );

            }


            totalPages =
                Math.max(
                    1,
                    Number(result.total_pages) || 1
                );


            body.innerHTML = '';


            /*
             * No records
             */

            if (
                !Array.isArray(result.records) ||
                result.records.length === 0
            ) {

                pageInfo.textContent = '';

                showState('empty');

                updatePagination();

                return;

            }


            /*
             * Render records
             */

            result.records.forEach(
                function (record) {

                    const row =
                        document.createElement('tr');


                    /*
                     * Closing Result
                     */

                    const closingResult =
                        closingResultLabel(
                            record.closing_result
                        );
                    
                    const driverRemittance =
                        driverRemittanceLabel(
                            record.driver_remittance_status
                        );


                    let action;


                    if (
                        record.status === 'Open' ||
                        record.status === 'Reopened'
                    ) {
                        action =
                            '<a ' +
                            'class="btn btn-outline" ' +
                            'href="daily-closing.php">' +
                            'Open Daily Closing' +
                            '</a>';
                    } else {
                        action =
                            '<a ' +
                            'class="btn btn-outline" ' +
                            'href="subpages/daily-record-view.php?daily_id=' +
                            encodeURIComponent(record.daily_id) +
                            '">' +
                            'View Record' +
                            '</a>';
                    }


                    row.innerHTML =

                        '<td>' +

                            '<div class="record-date">' +

                                escapeHtml(
                                    formatDate(
                                        record.business_date
                                    )
                                ) +

                            '</div>' +

                        '</td>' +


                        '<td>' +

                            '<span class="record-id">' +

                                '#' +

                                escapeHtml(
                                    record.daily_id
                                ) +

                            '</span>' +

                        '</td>' +


                        '<td>' +

                            '<span class="closing-result ' +

                                closingResultClass(
                                    closingResult
                                ) +

                            '">' +

                                escapeHtml(
                                    closingResult
                                ) +

                            '</span>' +

                        '</td>' +


                        '<td>' +

                            '<span class="driver-remittance ' +

                                driverRemittanceClass(
                                    driverRemittance
                                ) +

                            '">' +

                                escapeHtml(
                                    driverRemittance
                                ) +

                            '</span>' +

                        '</td>' +


                        '<td>' +

                            '<span class="badge record-status-badge ' +

                                statusClass(
                                    record.status
                                ) +

                            '">' +

                                escapeHtml(
                                    record.status
                                ) +

                            '</span>' +

                        '</td>' +


                        '<td>' +

                            escapeHtml(
                                formatDateTime(
                                    record.updated_at
                                )
                            ) +

                        '</td>' +


                        '<td>' +

                            action +

                        '</td>';


                    body.appendChild(row);

                }
            );


            pageInfo.textContent =
                'Page ' +
                currentPage +
                ' of ' +
                totalPages;


            showState('table');


            updatePagination();


            console.log(
                '[Daily Records] Records loaded:',
                result.records
            );

        } catch (requestError) {

            console.error(
                '[Daily Records] Error:',
                requestError
            );


            errorMessage.textContent =
                requestError.message ||
                'Please refresh the page and try again.';


            showState('error');

        }

    }


    /* =========================================================
       PAGINATION
       ========================================================= */

    function updatePagination() {

        previous.disabled =
            currentPage <= 1;

        next.disabled =
            currentPage >= totalPages;

    }


    /* =========================================================
       REFRESH
       ========================================================= */

    document
        .getElementById('refreshRecords')
        .addEventListener(
            'click',
            function () {

                loadRecords();

            }
        );


    /* =========================================================
       STATUS FILTER
       ========================================================= */

    statusInput.addEventListener(
        'change',
        function () {

            currentPage = 1;

            loadRecords();

        }
    );


    /* =========================================================
       DATE FILTER
       ========================================================= */

    dateInput.addEventListener(
        'change',
        function () {

            currentPage = 1;

            loadRecords();

        }
    );


    /* =========================================================
       PREVIOUS PAGE
       ========================================================= */

    previous.addEventListener(
        'click',
        function () {

            if (currentPage > 1) {

                currentPage--;

                loadRecords();

            }

        }
    );


    /* =========================================================
       NEXT PAGE
       ========================================================= */

    next.addEventListener(
        'click',
        function () {

            if (currentPage < totalPages) {

                currentPage++;

                loadRecords();

            }

        }
    );


    /* =========================================================
       INITIAL LOAD
       ========================================================= */

    loadRecords();

})();

</script>

</body>
</html>