<?php
session_start();

$users = [
    'customer' => [
        'username'  => 'customer',
        'password'  => '$2y$10$wK8rJp7v/P7.1X.X8xU7uO91Xn3N93.N17u2Xn3N93.N17u2Xn3N9',
        'name'      => 'Customer',
        'user_id'   => 'SM-CUST-001',
        'role'      => 'Customer',
        'expiry'    => '2026-12-31'
    ],
    'admin' => [
        'username'  => 'admin',
        'password'  => '$2y$10$K8rJp7v/P7.1X.X8xU7uO91Xn3N93.N17u2Xn3N93.N17u2Xn3N9',
        'name'      => 'Admin',
        'user_id'   => 'SM-ADM-001',
        'role'      => 'Admin',
        'expiry'    => '2026-12-31'
    ]
];

if (isset($_SESSION['users']) && is_array($_SESSION['users'])) {
    foreach ($_SESSION['users'] as $user) {
        $users[$user['username']] = $user;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!empty($username) && isset($users[$username])) {
        $user = $users[$username];

        $passwordCorrect = false;
        if (isset($user['registered']) && $user['registered'] === true) {
            $passwordCorrect = password_verify($password, $user['password']);
        } else {
            $passwordCorrect = ($username === 'customer' && $password === 'Customer@12345') || 
                               ($username === 'admin' && $password === 'Admin@123456');
        }

        if ($passwordCorrect) {
            if (strtotime(date('Y-m-d')) > strtotime($user['expiry'])) {
                header('Location: index.php?login=expired');
                exit;
            }

            $_SESSION['logged_in'] = true;
            $_SESSION['user_name'] = htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8');
            $_SESSION['username']  = htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8');
            $_SESSION['user_id']   = $user['user_id'];
            $_SESSION['user_role'] = $user['role'];

            if ($user['role'] === 'Admin') {
                header('Location: admin/index.php');
            } else {
                header('Location: index.php?login=success');
            }
            exit;
        }
    }

    header('Location: index.php?login=error');
    exit;
}

header('Location: index.php');
exit;
?>