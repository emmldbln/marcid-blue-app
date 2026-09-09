<?php

session_start();

require_once 'config/database.php';

if (isset($_SESSION['user_id'])) {
    header('Location: pages/home.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {

        $error = 'Please enter your username and password.';

    } else {

        $stmt = $pdo->prepare("
            SELECT
                user_id,
                username,
                password_hash,
                full_name,
                role,
                is_active
            FROM users
            WHERE username = ?
            LIMIT 1
        ");

        $stmt->execute([$username]);

        $user = $stmt->fetch();

        if (
            $user &&
            $user['is_active'] &&
            $user['role'] === 'Admin' &&
            password_verify($password, $user['password_hash'])
        ) {

            session_regenerate_id(true);

            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];

            header('Location: pages/home.php');
            exit;

        } else {

            $error = 'Invalid username or password.';
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Marcid Blue - Login</title>

    <link
        rel="stylesheet"
        href="assets/css/app.css"
    >

    <style>

        .login-page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            background:
                linear-gradient(
                    135deg,
                    #eaf7fd 0%,
                    #f5f8fb 50%,
                    #e9f9f8 100%
                );
        }

        .login-card {
            width: 100%;
            max-width: 420px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            padding: 36px;
        }

        .login-brand {
            text-align: center;
            margin-bottom: 30px;
        }

        .login-logo {
            width: 64px;
            height: 64px;
            margin: 0 auto 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--primary);
            color: white;
            border-radius: 14px;
            font-size: 30px;
            box-shadow: var(--shadow-sm);
        }

        .login-brand h1 {
            font-size: 24px;
            margin-bottom: 5px;
        }

        .login-brand p {
            color: var(--text-muted);
            font-size: 13px;
        }

        .login-form {
            margin-top: 10px;
        }

        .login-button {
            width: 100%;
            margin-top: 5px;
            padding: 12px 16px;
            font-size: 14px;
        }

        .login-error {
            padding: 11px 13px;
            margin-bottom: 18px;
            background: var(--danger-light);
            color: var(--danger);
            border-radius: var(--radius-sm);
            font-size: 13px;
        }

        .login-footer {
            margin-top: 24px;
            text-align: center;
            color: var(--text-muted);
            font-size: 12px;
        }

    </style>

</head>

<body>

<div class="login-page">

    <div class="login-card">

        <div class="login-brand">

            <div class="login-logo">
                💧
            </div>

            <h1>Marcid Blue</h1>

            <p>
                Water Station Management System
            </p>

        </div>


        <?php if ($error !== ''): ?>

            <div class="login-error">
                <?= htmlspecialchars($error) ?>
            </div>

        <?php endif; ?>


        <form
            method="POST"
            class="login-form"
        >

            <div class="form-group">

                <label
                    for="username"
                    class="form-label"
                >
                    Username
                </label>

                <input
                    type="text"
                    id="username"
                    name="username"
                    class="form-input"
                    placeholder="Enter your username"
                    autocomplete="username"
                    required
                >

            </div>


            <div class="form-group">

                <label
                    for="password"
                    class="form-label"
                >
                    Password
                </label>

                <input
                    type="password"
                    id="password"
                    name="password"
                    class="form-input"
                    placeholder="Enter your password"
                    autocomplete="current-password"
                    required
                >

            </div>


            <button
                type="submit"
                class="btn btn-primary login-button"
            >
                Log In
            </button>

        </form>


        <div class="login-footer">
            Authorized access only
        </div>

    </div>

</div>


<script src="assets/js/app.js"></script>

</body>

</html>