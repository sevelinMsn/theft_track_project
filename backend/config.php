<?php
/**
 * config.php — Application settings
 *
 * Change these values to match your XAMPP / MySQL setup.
 * Other PHP files include this file automatically via db.php or helpers.php.
 */

// --- MySQL database (default XAMPP) ---
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', ''); // XAMPP default: empty password
define('DB_NAME', 'thefttrack_db');

// --- Admin panel login (change before going live) ---
define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD', 'admin123');

// --- PHP session cookie name for logged-in users ---
define('SESSION_NAME', 'thefttrack_session');

// --- Report status labels (must match database ENUM values) ---
define('STATUS_PENDING', 'Pending');
define('STATUS_INVESTIGATING', 'Under Investigation');
define('STATUS_RESOLVED', 'Resolved');

define('ALLOWED_STATUSES', [
    STATUS_PENDING,
    STATUS_INVESTIGATING,
    STATUS_RESOLVED,
]);

// --- Suspect photo upload limits (admin panel) ---
define('SUSPECT_PHOTO_MAX_BYTES', 2 * 1024 * 1024); // 2 MB
define('SUSPECT_PHOTO_ALLOWED_TYPES', ['image/jpeg', 'image/png', 'image/webp']);
