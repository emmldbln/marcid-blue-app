<?php

require_once 'auth.php';

logout();

if (($_SERVER['HTTP_HOST'] ?? '') === 'localhost') {
    header('Location: /marcid-blue/');
} else {
    header('Location: /');
}

exit;
