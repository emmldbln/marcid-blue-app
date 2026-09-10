<?php

$config = require __DIR__ . '/local.php';

try {
    $pdo = new PDO(
        "mysql:host={$config['db_host']};dbname={$config['db_name']};charset=utf8mb4",
        $config['db_user'],
        $config['db_pass']
    );

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    /* Load Daily Closing autosave and panel modules only on that page. */
    register_shutdown_function(function (): void {
        if (basename((string) ($_SERVER['SCRIPT_NAME'] ?? '')) !== 'daily-closing.php') {
            return;
        }

        echo <<<'HTML'
<script>
(function () {
    var scripts = [
        '../assets/js/customer-autosave.js',
        '../assets/js/daily-closing-autosave-live.js',
        '../assets/js/deliveries-panel.js'
    ];

    scripts.forEach(function (src, index) {
        var script = document.createElement('script');
        script.id = 'marcid-blue-autosave-' + index;
        script.src = src;
        script.async = false;
        document.body.appendChild(script);
    });
})();
</script>
HTML;
    });

} catch (PDOException $e) {
    die("Database connection failed.");
}