<?php

$currentPage = $currentPage ?? 'home';

$validPages = [
    'home',
    'customers',
    'daily-records',
    'daily-closing'
];

if (!in_array($currentPage, $validPages, true)) {
    $currentPage = 'home';
}

$navItems = [
    'home' => [
        'href' => 'home.php',
        'icon' => '🏠',
        'label' => 'Home'
    ],

    'customers' => [
        'href' => '#',
        'icon' => '👥',
        'label' => 'Customers'
    ],

    'daily-records' => [
        'href' => 'daily-records.php',
        'icon' => '📅',
        'label' => 'Daily Records'
    ],

    'daily-closing' => [
        'href' => 'daily-closing.php',
        'icon' => '🧾',
        'label' => 'Daily Closing'
    ]
];

?>

<aside class="sidebar">

    <div class="sidebar-brand">

        <div class="brand-icon">

            <img
                src="../assets/images/mb-logo.png"
                alt="Marcid Blue Logo"
            >

        </div>

    </div>


    <nav class="sidebar-nav">

        <div class="nav-section-title">
            Main
        </div>


        <?php foreach ($navItems as $key => $item): ?>

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


        <a href="#" class="nav-item">

            ⚙️

            <span>
                Settings
            </span>

        </a>


        <a href="#" class="nav-item">

            🚪

            <span>
                Logout
            </span>

        </a>

    </nav>

</aside>