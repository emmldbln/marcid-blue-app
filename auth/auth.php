<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check whether a user is logged in.
 */
function isLoggedIn(): bool
{
    return isset($_SESSION['user_id']);
}

/**
 * Require the user to be logged in.
 */
function requireLogin(): void
{
    if (!isLoggedIn()) {
        header('Location: /marcid-blue/');
        exit;
    }
}

/**
 * Require administrator access.
 */
function requireAdmin(): void
{
    requireLogin();

    if (($_SESSION['role'] ?? '') !== 'Admin') {
        http_response_code(403);
        exit('Access denied.');
    }
}

/**
 * Log the current user out.
 */
function logout(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();

        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}