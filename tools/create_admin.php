<?php

require_once '../config/database.php';

$username = 'admin';
$password = 'MB031515';
$fullName = 'Marcid Blue Admin';

$stmt = $pdo->prepare("
    SELECT user_id
    FROM users
    WHERE username = ?
    LIMIT 1
");

$stmt->execute([$username]);

if ($stmt->fetch()) {
    exit('Admin username already exists.');
}

$passwordHash = password_hash(
    $password,
    PASSWORD_DEFAULT
);

$stmt = $pdo->prepare("
    INSERT INTO users (
        username,
        password_hash,
        full_name,
        role,
        is_active
    )
    VALUES (?, ?, ?, 'Admin', TRUE)
");

$stmt->execute([
    $username,
    $passwordHash,
    $fullName
]);

echo 'Admin account created successfully.';