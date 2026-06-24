<?php
/**
 * register.php — Create a new user account
 *
 * Method: POST
 * Input (JSON): fullname, email, phone, password, confirm
 * Output:        { success, message, user: { … } }
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed.', [], 405);
}

$input = getJsonInput();

$fullname = cleanInput($input['fullname'] ?? '', 100);
$email = strtolower(cleanInput($input['email'] ?? '', 100));
$phone = cleanInput($input['phone'] ?? '', 20);
$password = $input['password'] ?? '';
$confirmPassword = $input['confirm'] ?? '';

// --- Validation ---
if ($fullname === '' || $email === '' || $phone === '') {
    jsonResponse(false, 'Full name, email, and phone are required.');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(false, 'Invalid email address.');
}

if (strlen($password) < 6) {
    jsonResponse(false, 'Password must be at least 6 characters.');
}

if ($password !== $confirmPassword) {
    jsonResponse(false, 'Passwords do not match.');
}

// --- Check email is not already used ---
$checkStatement = $conn->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
$checkStatement->bind_param('s', $email);
$checkStatement->execute();

if ($checkStatement->get_result()->num_rows > 0) {
    jsonResponse(false, 'This email is already registered.');
}

// --- Insert new user (password is hashed, never store plain text) ---
$passwordHash = password_hash($password, PASSWORD_DEFAULT);

$insertStatement = $conn->prepare(
    'INSERT INTO users (fullname, email, phone, password) VALUES (?, ?, ?, ?)'
);
$insertStatement->bind_param('ssss', $fullname, $email, $phone, $passwordHash);
$insertStatement->execute();

$newUserId = (int) $conn->insert_id;

// --- Log them in automatically ---
startAppSession();
$_SESSION['user_id'] = $newUserId;
$_SESSION['fullname'] = $fullname;
$_SESSION['email'] = $email;
$_SESSION['phone'] = $phone;

jsonResponse(true, 'Account created successfully.', [
    'user' => [
        'id' => $newUserId,
        'fullname' => $fullname,
        'email' => $email,
        'phone' => $phone,
    ],
]);
