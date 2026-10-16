<?php
session_start();

$envFile = __DIR__ . '/../.env';

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

$recaptchaSecret = $recaptchaSecret ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $recaptchaResponse = $_POST['g-recaptcha-response'] ?? '';

    if ($recaptchaResponse === '') {
        header('Location: ../index.php?signup=captcha');
        exit;
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
    $verifyResult = file_get_contents($verifyUrl, false, $context);
    $verifyData = json_decode($verifyResult, true);

    if (!$verifyData || empty($verifyData['success'])) {
        header('Location: ../index.php?signup=captcha');
        exit;
    }
}

if (!isset($_SESSION['users']) || !is_array($_SESSION['users'])) {
    $_SESSION['users'] = [];
}

function redirectSignup($status) {
    header('Location: ../index.php?signup=' . $status);
    exit;
}

function postString($key) {
    $value = $_POST[$key] ?? '';

    return is_string($value) ? $value : '';
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php');
    exit;
}

$fullname = preg_replace('/\s+/', ' ', trim(postString('fullname')));
$username = trim(postString('username'));
$email    = strtolower(trim(postString('email')));
$password = postString('password');
$confirm  = postString('confirm_password');

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

foreach ($_SESSION['users'] as $existingUser) {
    if (strcasecmp($existingUser['username'], $username) === 0) {
        redirectSignup('exists');
    }
}

foreach ($_SESSION['users'] as $existingUser) {
    if (($existingUser['email'] ?? '') === $email) {
        redirectSignup('email_taken');
    }
}

$newUser = [
    'username' => $username,
    'password' => password_hash($password, PASSWORD_DEFAULT),
    'name'     => $fullname,    
    'email'    => $email,
    'user_id'  => 'SM-USER-' . (count($_SESSION['users']) + 1),
    'role'     => 'Customer'
];

$_SESSION['users'][$username] = $newUser;

redirectSignup('success');