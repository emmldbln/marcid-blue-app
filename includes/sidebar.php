<?php

$currentPage = $currentPage ?? 'home';

/*
 * Path configuration
 *
 * Main pages:
 *   pages/daily-records.php
 *
 * Subpages:
 *   pages/subpages/daily-record-view.php
 *
 * Main pages:
 *   $pageRoot = ''
 *   $assetRoot = '../'
 *
 * Subpages:
 *   $pageRoot = '../'
 *   $assetRoot = '../../'
 */

$pageRoot = $pageRoot ?? '';
$assetRoot = $assetRoot ?? '../';

$validPages = [
    'home',
    'customers',
    'daily-records',
    'daily-closing',
    'settings'
];

if (!in_array($currentPage, $validPages, true)) {
    $currentPage = 'home';
}

$navItems = [
    'home' => [
        'href' => $pageRoot . 'home.php',
        'icon' => '🏠',
        'label' => 'Home'
    ],

    'customers' => [
        'href' => $pageRoot . 'customers.php',
        'icon' => '👥',
        'label' => 'Customers'
    ],

    'daily-records' => [
        'href' => $pageRoot . 'daily-records.php',
        'icon' => '📅',
        'label' => 'Daily Records'
    ],

    'daily-closing' => [
        'href' => $pageRoot . 'daily-closing.php',
        'icon' => '🧾',
        'label' => 'Daily Closing'
    ],

    'settings' => [
        'href' => $pageRoot . 'settings.php',
        'icon' => '⚙️',
        'label' => 'Settings'
    ]
];

?>

<aside class="sidebar">

    <div class="sidebar-brand">

        <div class="brand-icon">

            <img
                src="<?= htmlspecialchars($assetRoot) ?>assets/images/mb-logo.png"
                alt="Marcid Blue Logo"
            >

        </div>

    </div>


    <nav class="sidebar-nav">

        <div class="nav-section-title">
            Main
        </div>


        <?php foreach ($navItems as $key => $item): ?>

            <?php if ($key === 'settings') continue; ?>

            <a
                href="<?= htmlspecialchars($item['href']) ?>"
                class="nav-item<?= $currentPage === $key ? ' active' : '' ?>"
            >

                <?= $item['icon'] ?>

                <span>
                    <?= htmlspecialchars($item['label']) ?>
                </span>

            </a>

        <?php endforeach; ?>


        <div
            class="nav-section-title"
            style="margin-top:25px;"
        >
            System
        </div>


        <a
            href="<?= htmlspecialchars($navItems['settings']['href']) ?>"
            class="nav-item<?= $currentPage === 'settings' ? ' active' : '' ?>"
        >

            ⚙️

            <span>
                Settings
            </span>

        </a>


        <a
            href="#"
            class="nav-item"
        >

            🚪

            <span>
                Logout
            </span>

        </a>

    </nav>

</aside>