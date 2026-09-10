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

    /*
     * Daily Closing does not currently include app.js directly.
     * Load only the Daily Closing autosave modules on that page,
     * without changing the page's large inline script.
     */
    register_shutdown_function(function (): void {
        $scriptName = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));

        if ($scriptName !== 'daily-closing.php') {
            return;
        }

        echo <<<'HTML'
<script>
(function () {
    var scripts = [
        '../assets/js/customer-autosave.js',
        '../assets/js/daily-closing-autosave.js'
    ];

    scripts.forEach(function (src, index) {
        var id = 'marcid-blue-autosave-' + index;

        if (document.getElementById(id)) {
            return;
        }

        var script = document.createElement('script');
        script.id = id;
        script.src = src;
        script.defer = true;
        document.body.appendChild(script);
    });
})();
</script>
HTML;
    });

} catch (PDOException $e) {
    die("Database connection failed.");
}