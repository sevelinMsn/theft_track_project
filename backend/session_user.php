<?php
/**
 * session_user.php — Check if a user is logged in
 *
 * Method: GET (or POST)
 * Output: { success, user: { id, fullname, … } } or user: null
 */
require_once __DIR__ . '/helpers.php';

startAppSession();

if (empty($_SESSION['user_id'])) {
    jsonResponse(true, 'Not logged in.', ['user' => null]);
}

jsonResponse(true, 'Session active.', [
    'user' => [
        'id' => (int) $_SESSION['user_id'],
        'fullname' => $_SESSION['fullname'] ?? '',
        'email' => $_SESSION['email'] ?? '',
        'phone' => $_SESSION['phone'] ?? '',
    ],
]);
