<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

function redirectSignup($status) {
    header('Location: ../index.php?signup=' . $status);
    exit();
}

function postString($key) {
    $value = $_POST[$key] ?? '';
    return is_string($value) ? trim($value) : '';
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php');
    exit();
}

$fullname = preg_replace('/\s+/', ' ', postString('fullname'));
$username = postString('username');
$email    = strtolower(postString('email'));
$password = $_POST['password'] ?? '';
$confirm  = $_POST['confirm_password'] ?? '';

if ($fullname === '' || $username === '' || $email === '' || $password === '' || $confirm === '') {
    redirectSignup('empty');
}

if (
    preg_match('/[0-9]/', $fullname) ||
    !preg_match("/^\p{L}[\p{L} .'-]*$/u", $fullname) ||
    mb_strlen($fullname) < 2 ||
    mb_strlen($fullname) > 100
) {
    redirectSignup('name');
}

if (!preg_match('/^[A-Za-z0-9]{3,30}$/', $username) || !preg_match('/[A-Za-z]/', $username)) {
    redirectSignup('username');
}

if (strlen($email) > 255 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    redirectSignup('email');
}

$defaultSettings = [
    'min_password_length' => '12',
    'require_uppercase'   => '1',
    'require_lowercase'   => '1',
    'require_number'      => '1',
    'require_special'     => '1'
];

try {
    $rawSettings = $pdo->query("SELECT setting_key, setting_value FROM system_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
    $policy = array_merge($defaultSettings, $rawSettings ?: []);
} catch (PDOException $e) {
    $policy = $defaultSettings;
}

$minLength       = (int)$policy['min_password_length'];
$requireUpper   = $policy['require_uppercase'] === '1';
$requireLower   = $policy['require_lowercase'] === '1';
$requireNumber  = $policy['require_number'] === '1';
$requireSpecial = $policy['require_special'] === '1';

if (strlen($password) < $minLength || strlen($password) > 64) {
    redirectSignup('password');
}
if ($requireUpper && !preg_match('/[A-Z]/', $password)) {
    redirectSignup('password');
}
if ($requireLower && !preg_match('/[a-z]/', $password)) {
    redirectSignup('password');
}
if ($requireNumber && !preg_match('/[0-9]/', $password)) {
    redirectSignup('password');
}
if ($requireSpecial && !preg_match('/[^A-Za-z0-9]/', $password)) {
    redirectSignup('password');
}

if ($password !== $confirm) {
    redirectSignup('mismatch');
}

$reservedUsernames = ['admin', 'customer'];
if (in_array(strtolower($username), $reservedUsernames, true)) {
    redirectSignup('exists');
}

$stmt = $pdo->prepare("SELECT user_id FROM users WHERE username = ? LIMIT 1");
$stmt->execute([$username]);
if ($stmt->fetch()) {
    redirectSignup('exists');
}

$stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ? LIMIT 1");
$stmt->execute([$email]);
if ($stmt->fetch()) {
    redirectSignup('email_taken');
}

$stmt = $pdo->query("SELECT COUNT(*) AS total FROM users");
$row = $stmt->fetch();
$nextNumber = ((int)$row['total']) + 1;
$userId = 'SM-CUST-' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);

$passwordHash = password_hash($password, PASSWORD_DEFAULT);

try {
    $insertStmt = $pdo->prepare("
        INSERT INTO users (
            user_id, username, full_name, email, password_hash, role, status, account_status, failed_attempts, login_attempts, created_at
        ) VALUES (
            ?, ?, ?, ?, ?, 'Customer', 'Active', 'Active', 0, 0, NOW()
        )
    ");
    $insertStmt->execute([$userId, $username, $fullname, $email, $passwordHash]);
    
    redirectSignup('success');
} catch (PDOException $e) {
    redirectSignup('error');
}