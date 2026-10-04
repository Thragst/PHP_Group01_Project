<?php
session_start();

if (!isset($_SESSION['users'])) {
    $_SESSION['users'] = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $fullname = trim($_POST['fullname'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if ($fullname === '' || $username === '' || $email === '' || $password === '' || $confirm === '') {
        header('Location: index.php?signup=error');
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header('Location: index.php?signup=email');
        exit;
    }

    $hasLength  = strlen($password) >= 12;
    $hasUpper   = preg_match('/[A-Z]/', $password);
    $hasLower   = preg_match('/[a-z]/', $password);
    $hasNumber  = preg_match('/[0-9]/', $password);
    $hasSpecial = preg_match('/[^A-Za-z0-9]/', $password);

    if (!$hasLength || !$hasUpper || !$hasLower || !$hasNumber || !$hasSpecial) {
        header('Location: index.php?signup=password');
        exit;
    }

    if ($password !== $confirm) {
        header('Location: index.php?signup=mismatch');
        exit;
    }

    foreach ($_SESSION['users'] as $user) {
        if (strtolower($user['username']) === strtolower($username)) {
            header('Location: index.php?signup=exists');
            exit;
        }
    }

    $newUser = [
        'username'   => $username,
        'password'   => password_hash($password, PASSWORD_DEFAULT),
        'name'       => $fullname,
        'email'      => $email,
        'user_id'    => 'SM-USER-' . (count($_SESSION['users']) + 1),
        'role'       => 'Customer',
        'expiry'     => '2026-12-31',
        'registered' => true
    ];

    $_SESSION['users'][$username] = $newUser;

    $_SESSION['logged_in'] = true;
    $_SESSION['user_name'] = htmlspecialchars($fullname, ENT_QUOTES, 'UTF-8');
    $_SESSION['username']  = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
    $_SESSION['user_id']   = $newUser['user_id'];
    $_SESSION['user_role'] = 'Customer';

    header('Location: index.php?signup=success');
    exit;
}

header('Location: index.php');
exit;
?>