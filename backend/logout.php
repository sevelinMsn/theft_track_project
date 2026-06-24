<?php
/**
 * logout.php — End the current user session
 *
 * Method: POST
 * Output: { success, message }
 */
require_once __DIR__ . '/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed.', [], 405);
}

startAppSession();

// Remove user data from the session
unset($_SESSION['user_id'], $_SESSION['fullname'], $_SESSION['email'], $_SESSION['phone']);

jsonResponse(true, 'Logged out successfully.');
