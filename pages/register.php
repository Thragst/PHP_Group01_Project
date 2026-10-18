<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

$envFile = __DIR__ . '/../.env';
$recaptchaSecret = '';

if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
        if (trim($key) === 'RECAPTCHA_SECRET_KEY') {
            $recaptchaSecret = trim($value);
        }
    }
}

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
//Commented out for local testing, uncomment for live
/*
$recaptchaResponse = $_POST['g-recaptcha-response'] ?? '';

if ($recaptchaResponse === '' || $recaptchaSecret === '') {
    redirectSignup('captcha');
}

$verifyUrl = 'https://www.google.com/recaptcha/api/siteverify';
$postData = http_build_query([
    'secret'   => $recaptchaSecret,
    'response' => $recaptchaResponse,
    'remoteip' => $_SERVER['REMOTE_ADDR']
]);

$options = [
    'http' => [
        'method'  => 'POST',
        'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
        'content' => $postData
    ]
];

$context = stream_context_create($options);
$verifyResult = @file_get_contents($verifyUrl, false, $context);
$verifyData = json_decode($verifyResult, true);

if (!$verifyData || empty($verifyData['success'])) {
    redirectSignup('captcha');
}
*/

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

$hasLength  = strlen($password) >= 12 && strlen($password) <= 64;
$hasUpper   = preg_match('/[A-Z]/', $password);
$hasLower   = preg_match('/[a-z]/', $password);
$hasNumber  = preg_match('/[0-9]/', $password);
$hasSpecial = preg_match('/[^A-Za-z0-9]/', $password);

if (!$hasLength || !$hasUpper || !$hasLower || !$hasNumber || !$hasSpecial) {
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
            user_id, username, full_name, email, password_hash, role, account_status, login_attempts, created_at
        ) VALUES (
            ?, ?, ?, ?, ?, 'Customer', 'Active', 0, NOW()
        )
    ");
    $insertStmt->execute([$userId, $username, $fullname, $email, $passwordHash]);
    
    redirectSignup('success');
} catch (PDOException $e) {
    redirectSignup('error');
}