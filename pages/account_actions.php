<?php
require_once __DIR__ . '/../includes/ssl.php';
session_start();
require_once __DIR__ . '/../includes/auth_guard.php';
keepStaffOut();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/audit_helper.php';
enforceIdleTimeout($pdo);

if (!isLoggedIn()) {
    redirect('../index.php?login=required');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../index.php?page=profile');
}

function postValue($key)
{
    $value = $_POST[$key] ?? '';
    return is_string($value) ? trim($value) : '';
}

$userId = $_SESSION['user_id'];
$action = postValue('action');
$backPage = ($action === 'update_profile') ? 'profile' : 'settings';
$backUrl = '../index.php?page=' . $backPage;

if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    setFlash('error', 'Invalid request. Please try again.');
    redirect($backUrl);
}

if ($action === 'update_profile') {
    $fullname = preg_replace('/\s+/', ' ', postValue('fullname'));
    $username = postValue('username');

    if ($fullname === '' || $username === '') {
        setFlash('error', 'Please fill in all fields.');
        redirect($backUrl);
    }

    if (
        preg_match('/[0-9]/', $fullname) ||
        !preg_match("/^\p{L}[\p{L} .'-]*$/u", $fullname) ||
        mb_strlen($fullname) < 2 ||
        mb_strlen($fullname) > 100
    ) {
        setFlash('error', 'Full name cannot contain numbers.');
        redirect($backUrl);
    }

    if (!preg_match('/^[A-Za-z0-9]{3,30}$/', $username) || !preg_match('/[A-Za-z]/', $username)) {
        setFlash('error', 'Username can only contain letters and numbers, and cannot be numbers only.');
        redirect($backUrl);
    }

    $stmt = $pdo->prepare("SELECT username FROM users WHERE user_id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $current = $stmt->fetch();

    if (!$current) {
        redirect('../pages/logout.php');
    }

    if (strcasecmp($current['username'] ?? '', $username) !== 0) {
        $reserved = ['admin', 'customer'];

        if (in_array(strtolower($username), $reserved, true)) {
            setFlash('error', 'Username already exists.');
            redirect($backUrl);
        }

        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE username = ? AND user_id <> ? LIMIT 1");
        $stmt->execute([$username, $userId]);

        if ($stmt->fetch()) {
            setFlash('error', 'Username already exists.');
            redirect($backUrl);
        }
    }

    try {
        $stmt = $pdo->prepare("UPDATE users SET full_name = ?, username = ? WHERE user_id = ?");
        $stmt->execute([$fullname, $username, $userId]);

        $_SESSION['user_name'] = $fullname;
        $_SESSION['username'] = $username;
        logAudit($pdo, $userId, "User updated profile details (Full Name: {$fullname}, Username: {$username})");

        setFlash('success', 'Profile updated successfully.');
    } catch (PDOException $e) {
        setFlash('error', 'Unable to update your profile.');
    }

    redirect($backUrl);
}

if ($action === 'update_shipping') {
    $address = preg_replace('/\s+/', ' ', postValue('address'));
    $phone = preg_replace('/[\s\-]/', '', postValue('phone'));

    if ($phone !== '' && !isValidPhMobile($phone)) {
        setFlash('error', 'Please enter a valid mobile number (e.g. 09123456789).');
        redirect($backUrl);
    }

    if ($address !== '' && mb_strlen($address) < 10) {
        setFlash('error', 'Please enter your complete shipping address.');
        redirect($backUrl);
    }

    if (mb_strlen($address) > 255) {
        setFlash('error', 'Shipping address must be 255 characters or less.');
        redirect($backUrl);
    }

    try {
        $stmt = $pdo->prepare("UPDATE users SET physical_address = ?, phone = ? WHERE user_id = ?");
        $stmt->execute([
            $address === '' ? null : encryptField($address),
            $phone === '' ? null : encryptField($phone),
            $userId
        ]);

        logAudit($pdo, $userId, "User updated default shipping address/contact info");
        setFlash('success', 'Shipping information saved.');
    } catch (PDOException $e) {
        setFlash('error', 'Unable to save your shipping information.');
    }

    redirect($backUrl);
}

if ($action === 'change_password') {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
        setFlash('error', 'Please fill in all password fields.');
        redirect($backUrl);
    }

    $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE user_id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $row = $stmt->fetch();

    if (!$row || !password_verify($currentPassword, $row['password_hash'])) {
        setFlash('error', 'Current password is incorrect.');
        redirect($backUrl);
    }

    if (!meetsPasswordPolicy($newPassword, $pdo)) {
        setFlash('error', 'New password does not meet the requirements.');
        redirect($backUrl);
    }

    if ($newPassword !== $confirmPassword) {
        setFlash('error', 'New passwords do not match. Please re-enter your password.');
        redirect($backUrl);
    }

    if (password_verify($newPassword, $row['password_hash'])) {
        setFlash('error', 'New password must be different from your current password.');
        redirect($backUrl);
    }

    try {
        $stmt = $pdo->prepare("UPDATE users SET password_hash = ?, login_attempts = 0 WHERE user_id = ?");
        $stmt->execute([password_hash($newPassword, PASSWORD_DEFAULT), $userId]);

        logAudit($pdo, $userId, "User successfully changed their password");
        session_regenerate_id(true);
        setFlash('success', 'Password changed successfully.');
    } catch (PDOException $e) {
        setFlash('error', 'Unable to change your password.');
    }

    redirect($backUrl);
}

redirect('../index.php?page=profile');