<?php
require_once __DIR__ . '/helpers.php';

startAppSession();
unset($_SESSION['admin_logged_in']);

jsonResponse(true, 'Admin logged out.');
