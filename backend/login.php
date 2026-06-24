<?php
/**
 * login.php — Sign in a registered user
 *
 * Method: POST
 * Input (JSON):  identifier (email or phone), password
 * Output:        { success, message, user: { id, fullname, email, phone } }
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

// --- Step 1: Only POST is allowed ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed.', [], 405);
}

// --- Step 2: Read and clean input ---
$input = getJsonInput();
$identifier = cleanInput($input['identifier'] ?? '', 100);
$password = $input['password'] ?? '';

if ($identifier === '' || $password === '') {
    jsonResponse(false, 'Email/phone and password are required.');
}

// --- Step 3: Find user by email OR phone ---
$isEmail = filter_var($identifier, FILTER_VALIDATE_EMAIL);
$phoneDigitsOnly = preg_replace('/\D/', '', $identifier);

if ($isEmail) {
    $email = strtolower($identifier);
    $statement = $conn->prepare(
        'SELECT id, fullname, email, phone, password FROM users WHERE email = ? LIMIT 1'
    );
    $statement->bind_param('s', $email);
} else {
    $statement = $conn->prepare(
        'SELECT id, fullname, email, phone, password FROM users
         WHERE REPLACE(REPLACE(REPLACE(phone, " ", ""), "-", ""), "+", "") = ? LIMIT 1'
    );
    $statement->bind_param('s', $phoneDigitsOnly);
}

$statement->execute();
$userRow = $statement->get_result()->fetch_assoc();

// --- Step 4: Check password ---
if (!$userRow || !password_verify($password, $userRow['password'])) {
    jsonResponse(false, 'Invalid email/phone or password.', [], 401);
}

// --- Step 5: Save user in session ---
startAppSession();
$_SESSION['user_id'] = (int) $userRow['id'];
$_SESSION['fullname'] = $userRow['fullname'];
$_SESSION['email'] = $userRow['email'];
$_SESSION['phone'] = $userRow['phone'];

// --- Step 6: Send success response ---
jsonResponse(true, 'Login successful.', [
    'user' => [
        'id' => (int) $userRow['id'],
        'fullname' => $userRow['fullname'],
        'email' => $userRow['email'],
        'phone' => $userRow['phone'],
    ],
]);
