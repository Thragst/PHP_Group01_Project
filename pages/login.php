<?php
session_start();

$users = [
    'customer' => [
        'username' => 'customer',
        'password' => '$2y$10$wK8rJp7v/P7.1X.X8xU7uO91Xn3N93.N17u2Xn3N93.N17u2Xn3N9',
        'name'     => 'Customer',
        'user_id'  => 'SM-CUST-001',
        'role'     => 'Customer'
    ],
    'admin' => [
        'username' => 'admin',
        'password' => '$2y$10$K8rJp7v/P7.1X.X8xU7uO91Xn3N93.N17u2Xn3N93.N17u2Xn3N9',
        'name'     => 'Admin',
        'user_id'  => 'SM-ADM-001',
        'role'     => 'Admin'
    ]
];

if (isset($_SESSION['users']) && is_array($_SESSION['users'])) {
    foreach ($_SESSION['users'] as $u) {
        $users[$u['username']] = $u;
    }
}

function postString($key) {
    $value = $_POST[$key] ?? '';

    return is_string($value) ? $value : '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim(postString('username'));
    $password = postString('password');

    $validUsername = preg_match('/^[A-Za-z0-9]{3,30}$/', $username);
    $validPassword = $password !== '' && strlen($password) <= 64;

    if ($validUsername && $validPassword && isset($users[$username])) {
    $user = $users[$username];

    // Check if account is already locked
    $failedAttempts = $_SESSION['failed_attempts'][$username] ?? 0;

    if ($failedAttempts >= 3) {
        header('Location: ../index.php?login=locked');
        exit;
    }
    if (password_verify($password, $user['password'])) {
        $_SESSION['failed_attempts'][$username] = 0;
        session_regenerate_id(true);
        $_SESSION['logged_in'] = true;
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['username']  = $user['username'];
        $_SESSION['user_id']   = $user['user_id'];
        $_SESSION['user_role'] = $user['role'];
        if ($user['role'] === 'Admin') {
            header('Location: ../admin/index.php');
        } else {
            header('Location: ../index.php?login=success');
        }
        exit;
    }
    $_SESSION['failed_attempts'][$username] = $failedAttempts + 1;
    if ($_SESSION['failed_attempts'][$username] >= 3) { header('Location: ../index.php?login=locked'); exit; }}
    header('Location: ../index.php?login=error');
    exit;
}

header('Location: ../index.php');
exit;