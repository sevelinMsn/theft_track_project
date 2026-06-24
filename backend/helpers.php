<?php
/**
 * helpers.php — Shared functions for all TheftTrack API files
 *
 * This file is included by most endpoints. It does NOT connect to the database
 * by itself — include db.php first when you need $conn.
 */

require_once __DIR__ . '/config.php';

// Hide PHP warnings from the browser during development (errors still go to the log)
ini_set('display_errors', '0');

// Allow the frontend (running on localhost) to call the API from another port
handleCors();


// =============================================================================
// HTTP & JSON — how we talk to the frontend
// =============================================================================

/**
 * Allow requests from localhost (needed when frontend uses Live Server or another port).
 */
function handleCors(): void
{
    $requestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($requestOrigin === '') {
        return;
    }

    $parsedUrl = parse_url($requestOrigin);
    $host = $parsedUrl['host'] ?? '';
    $allowedHosts = ['localhost', '127.0.0.1'];

    if (!in_array($host, $allowedHosts, true)) {
        return;
    }

    header('Access-Control-Allow-Origin: ' . $requestOrigin);
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Accept');

    // Browser "preflight" check before POST
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

/**
 * Send a JSON response and stop the script.
 *
 * @param bool   $isSuccess  true = OK, false = error
 * @param string $message    Human-readable message for the user
 * @param array  $extraData  More keys to add to the JSON (e.g. 'user', 'reports')
 * @param int    $httpCode   HTTP status code (200, 401, 404, …)
 */
function jsonResponse(bool $isSuccess, string $message, array $extraData = [], int $httpCode = 200): void
{
    http_response_code($httpCode);
    header('Content-Type: application/json; charset=utf-8');

    $response = array_merge([
        'success' => $isSuccess,
        'message' => $message,
    ], $extraData);

    echo json_encode($response);
    exit;
}

/**
 * Read JSON body from a POST request (or fall back to $_POST for forms).
 */
function getJsonInput(): array
{
    $rawBody = file_get_contents('php://input');

    if ($rawBody === false || $rawBody === '') {
        return $_POST;
    }

    $decoded = json_decode($rawBody, true);
    return is_array($decoded) ? $decoded : $_POST;
}


// =============================================================================
// Input cleaning & display safety
// =============================================================================

/**
 * Trim text and limit length before saving to the database.
 */
function cleanInput(?string $value, int $maxLength = 255): string
{
    $value = trim((string) $value);

    if (strlen($value) > $maxLength) {
        $value = substr($value, 0, $maxLength);
    }

    return $value;
}

/**
 * Escape text for safe HTML output (admin pages).
 */
function escapeHtml(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}


// =============================================================================
// Sessions — remember who is logged in
// =============================================================================

/**
 * Start the PHP session if it is not already active.
 */
function startAppSession(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        session_start();
    }
}

/**
 * Stop the script if no user is logged in. Returns the user info array.
 */
function requireUserSession(): array
{
    startAppSession();

    if (empty($_SESSION['user_id'])) {
        jsonResponse(false, 'Not logged in.', [], 401);
    }

    return [
        'id' => (int) $_SESSION['user_id'],
        'fullname' => $_SESSION['fullname'] ?? '',
        'email' => $_SESSION['email'] ?? '',
        'phone' => $_SESSION['phone'] ?? '',
    ];
}

/**
 * Stop the script if admin is not logged in.
 */
function requireAdminSession(): void
{
    startAppSession();

    if (empty($_SESSION['admin_logged_in'])) {
        jsonResponse(false, 'Admin access required.', [], 403);
    }
}

/**
 * Check admin username/password (config file or admins table).
 */
function verifyAdminLogin(mysqli $database, string $username, string $password): bool
{
    if ($username === ADMIN_USERNAME && $password === ADMIN_PASSWORD) {
        return true;
    }

    $statement = $database->prepare('SELECT password FROM admins WHERE username = ? LIMIT 1');
    $statement->bind_param('s', $username);
    $statement->execute();
    $adminRow = $statement->get_result()->fetch_assoc();

    if ($adminRow && password_verify($password, $adminRow['password'])) {
        return true;
    }

    return false;
}


// =============================================================================
// Reports — tracking IDs, parsing, formatting
// =============================================================================

/**
 * Create a unique tracking ID like TT-2026-A1B2C3.
 */
function generateTrackingId(mysqli $database): string
{
    $year = date('Y');
    $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    for ($attempt = 0; $attempt < 12; $attempt++) {
        $randomCode = '';
        for ($i = 0; $i < 6; $i++) {
            $randomCode .= $characters[random_int(0, strlen($characters) - 1)];
        }

        $trackingId = 'TT-' . $year . '-' . $randomCode;

        $statement = $database->prepare('SELECT id FROM reports WHERE tracking_id = ? LIMIT 1');
        $statement->bind_param('s', $trackingId);
        $statement->execute();

        if (!$statement->get_result()->fetch_assoc()) {
            return $trackingId;
        }
    }

    // Fallback if many collisions (very unlikely)
    return 'TT-' . $year . '-' . strtoupper(bin2hex(random_bytes(3)));
}

/**
 * Convert database status text to a short key for JavaScript (pending, investigating, resolved).
 */
function statusToKey(string $statusLabel): string
{
    $statusMap = [
        'Pending' => 'pending',
        'Under Investigation' => 'investigating',
        'Resolved' => 'resolved',
    ];

    return $statusMap[$statusLabel] ?? 'pending';
}

/**
 * Split the "item" column into category + item name.
 * Stored format: [electronics] Samsung Phone
 */
function parseItemField(string $itemColumn): array
{
    if (preg_match('/^\[([^\]]+)\]\s*(.+)$/u', $itemColumn, $matches)) {
        return [
            'category' => $matches[1],
            'itemName' => trim($matches[2]),
        ];
    }

    return [
        'category' => 'other',
        'itemName' => $itemColumn,
    ];
}

/**
 * The description column may contain extra blocks added on submit:
 *   Date of theft: …
 *   Suspect information: …
 * This function separates them for the API response.
 */
function parseDescriptionExtras(string $fullDescription): array
{
    $mainDescription = $fullDescription;
    $incidentDate = '';
    $suspectInfo = '';

    if (preg_match('/\n\nDate of theft:\s*(.+?)(?=\n\nSuspect information:|$)/su', $fullDescription, $matches)) {
        $incidentDate = trim($matches[1]);
    }

    if (preg_match('/\n\nSuspect information:\s*(.+)$/su', $fullDescription, $matches)) {
        $suspectInfo = trim($matches[1]);
    }

    if ($incidentDate !== '' || $suspectInfo !== '') {
        $mainDescription = preg_replace('/\n\nDate of theft:.*$/su', '', $fullDescription);
        $mainDescription = trim($mainDescription);
    }

    return [
        'description' => $mainDescription,
        'incidentDate' => $incidentDate,
        'suspect' => $suspectInfo,
    ];
}

/**
 * Find one report row by tracking ID.
 */
function getReportByTrackingId(mysqli $database, string $trackingId): ?array
{
    $statement = $database->prepare('SELECT * FROM reports WHERE tracking_id = ? LIMIT 1');
    $statement->bind_param('s', $trackingId);
    $statement->execute();
    $reportRow = $statement->get_result()->fetch_assoc();

    return $reportRow ?: null;
}

/**
 * True if this user owns the report (by user_id or matching email).
 */
function userOwnsReport(array $loggedInUser, array $reportRow): bool
{
    if (!empty($reportRow['user_id']) && (int) $reportRow['user_id'] === (int) $loggedInUser['id']) {
        return true;
    }

    if (!empty($reportRow['email']) && strtolower($reportRow['email']) === strtolower($loggedInUser['email'])) {
        return true;
    }

    return false;
}


// =============================================================================
// Case updates & timeline
// =============================================================================

function fetchCaseUpdates(mysqli $database, int $reportId): array
{
    try {
        $statement = $database->prepare(
            'SELECT update_type, status_value, note_text, created_by, created_at
             FROM case_updates WHERE report_id = ? ORDER BY created_at ASC'
        );
        $statement->bind_param('i', $reportId);
        $statement->execute();

        $updates = [];
        $result = $statement->get_result();
        while ($row = $result->fetch_assoc()) {
            $updates[] = $row;
        }
        return $updates;
    } catch (mysqli_sql_exception $e) {
        return [];
    }
}

function logCaseUpdate(
    mysqli $database,
    int $reportId,
    string $updateType,
    ?string $statusValue,
    ?string $noteText,
    string $createdBy
): void {
    $statement = $database->prepare(
        'INSERT INTO case_updates (report_id, update_type, status_value, note_text, created_by)
         VALUES (?, ?, ?, ?, ?)'
    );
    $statement->bind_param('issss', $reportId, $updateType, $statusValue, $noteText, $createdBy);
    $statement->execute();
}

function buildTimelineFromUpdates(array $caseUpdates, string $status, string $createdAt): array
{
    $timeline = [
        [
            'title' => 'Report Received',
            'message' => 'Your theft report was submitted successfully.',
            'date' => $createdAt,
            'type' => 'info',
        ],
    ];

    foreach ($caseUpdates as $update) {
        if ($update['update_type'] === 'status' && $update['status_value']) {
            $timeline[] = [
                'title' => 'Status Updated',
                'message' => 'Case status changed to: ' . $update['status_value'],
                'date' => $update['created_at'],
                'type' => statusToKey($update['status_value']),
            ];
        } elseif ($update['update_type'] === 'note' && $update['note_text']) {
            $timeline[] = [
                'title' => 'Investigation Note',
                'message' => $update['note_text'],
                'date' => $update['created_at'],
                'type' => 'note',
            ];
        }
    }

    if (count($timeline) === 1) {
        $timeline[] = [
            'title' => 'Pending Review',
            'message' => 'Your case is in the review queue.',
            'date' => null,
            'type' => 'pending',
        ];
    }

    return $timeline;
}

/**
 * Turn one database row from "reports" into the JSON shape the frontend expects.
 */
function formatReportForApi(mysqli $database, array $reportRow, bool $includeLinkedUser = false): array
{
    $parsedItem = parseItemField($reportRow['item']);
    $parsedDescription = parseDescriptionExtras($reportRow['description']);
    $reportId = (int) $reportRow['id'];
    $caseUpdates = fetchCaseUpdates($database, $reportId);

    // Collect investigation notes only
    $notesList = [];
    foreach ($caseUpdates as $update) {
        if ($update['update_type'] === 'note') {
            $notesList[] = [
                'text' => $update['note_text'],
                'by' => $update['created_by'],
                'date' => $update['created_at'],
            ];
        }
    }

    $report = [
        'db_id' => $reportId,
        'id' => $reportRow['tracking_id'],
        'tracking_id' => $reportRow['tracking_id'],
        'reporterName' => $reportRow['fullname'],
        'fullname' => $reportRow['fullname'],
        'phone' => $reportRow['phone'],
        'email' => $reportRow['email'],
        'category' => $parsedItem['category'],
        'itemName' => $parsedItem['itemName'],
        'item' => $reportRow['item'],
        'description' => $parsedDescription['description'],
        'incidentDate' => $parsedDescription['incidentDate'],
        'suspect' => $parsedDescription['suspect'] !== '' ? $parsedDescription['suspect'] : null,
        'location' => $reportRow['location'],
        'status' => statusToKey($reportRow['status']),
        'statusLabel' => $reportRow['status'],
        'user_id' => $reportRow['user_id'] ? (int) $reportRow['user_id'] : null,
        'createdAt' => $reportRow['created_at'],
        'created_at' => $reportRow['created_at'],
        'updates' => buildTimelineFromUpdates($caseUpdates, $reportRow['status'], $reportRow['created_at']),
        'notes' => $notesList,
    ];

    if ($includeLinkedUser && !empty($reportRow['user_id'])) {
        $userStatement = $database->prepare(
            'SELECT id, fullname, email, phone, created_at FROM users WHERE id = ? LIMIT 1'
        );
        $userId = (int) $reportRow['user_id'];
        $userStatement->bind_param('i', $userId);
        $userStatement->execute();
        $report['linked_user'] = $userStatement->get_result()->fetch_assoc() ?: null;
    } else {
        $report['linked_user'] = null;
    }

    return $report;
}


// =============================================================================
// Fraud page — risk levels for areas and reports
// =============================================================================

function calculateRiskFromReportCount(int $reportCount): string
{
    if ($reportCount >= 10) {
        return 'critical';
    }
    if ($reportCount >= 5) {
        return 'high';
    }
    if ($reportCount >= 2) {
        return 'medium';
    }
    return 'low';
}

function calculateRiskFromReportStatus(string $statusLabel): string
{
    $statusKey = statusToKey($statusLabel);

    if ($statusKey === 'investigating') {
        return 'high';
    }
    if ($statusKey === 'resolved') {
        return 'low';
    }
    return 'medium';
}


// =============================================================================
// Suspects — admin uploads & public fraud page
// =============================================================================

function suspectInitialsFromAlias(string $alias): string
{
    $words = preg_split('/\s+/', trim($alias));
    $initials = '';

    foreach ($words as $word) {
        if ($word !== '' && preg_match('/[A-Za-z]/', $word[0])) {
            $initials .= strtoupper($word[0]);
            if (strlen($initials) >= 2) {
                break;
            }
        }
    }

    return $initials !== '' ? substr($initials, 0, 2) : '??';
}

function suspectPhotoPublicUrl(?string $photoFilename): ?string
{
    if ($photoFilename === null || trim($photoFilename) === '') {
        return null;
    }

    return 'uploads/suspects/' . basename($photoFilename);
}

/**
 * Format one row from the "suspects" table for JSON output.
 */
function formatSuspectForApi(array $suspectRow): array
{
    $photoFilename = $suspectRow['photo_path'] ?? null;
    $publicPhotoPath = suspectPhotoPublicUrl($photoFilename);

    return [
        'id' => (int) $suspectRow['id'],
        'alias' => $suspectRow['alias'],
        'initials' => suspectInitialsFromAlias($suspectRow['alias']),
        'caseType' => $suspectRow['case_type'],
        'lastSeen' => $suspectRow['last_seen'],
        'description' => $suspectRow['description'] ?? '',
        'risk' => $suspectRow['risk_level'],
        'photo' => $publicPhotoPath,
        'photo_url' => $publicPhotoPath ? '../backend/' . $publicPhotoPath : null,
        'tracking_id' => $suspectRow['linked_tracking_id'] ?? null,
        'status' => $suspectRow['status'],
        'source' => 'database',
    ];
}

/**
 * Save an uploaded suspect photo. Returns the filename only (stored in database).
 */
function saveSuspectPhotoFile(array $uploadedFile, string $uploadDirectory): ?string
{
    if (empty($uploadedFile['tmp_name']) || !is_uploaded_file($uploadedFile['tmp_name'])) {
        return null;
    }

    if ($uploadedFile['error'] !== UPLOAD_ERR_OK) {
        jsonResponse(false, 'Photo upload failed.');
    }

    if ($uploadedFile['size'] > SUSPECT_PHOTO_MAX_BYTES) {
        jsonResponse(false, 'Photo must be 2MB or smaller.');
    }

    $fileInfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $fileInfo->file($uploadedFile['tmp_name']);

    if (!in_array($mimeType, SUSPECT_PHOTO_ALLOWED_TYPES, true)) {
        jsonResponse(false, 'Photo must be JPG, PNG, or WebP.');
    }

    if ($mimeType === 'image/png') {
        $extension = 'png';
    } elseif ($mimeType === 'image/webp') {
        $extension = 'webp';
    } else {
        $extension = 'jpg';
    }

    if (!is_dir($uploadDirectory)) {
        mkdir($uploadDirectory, 0755, true);
    }

    $newFilename = 'suspect_' . bin2hex(random_bytes(8)) . '.' . $extension;
    $destinationPath = $uploadDirectory . $newFilename;

    if (!move_uploaded_file($uploadedFile['tmp_name'], $destinationPath)) {
        jsonResponse(false, 'Could not save photo.');
    }

    return $newFilename;
}

/**
 * Delete a suspect photo file from disk when the record is removed or replaced.
 */
function deleteSuspectPhotoFile(?string $filename, string $uploadDirectory): void
{
    if ($filename === null || $filename === '') {
        return;
    }

    $safeFilename = basename($filename);
    $fullPath = $uploadDirectory . $safeFilename;

    if (is_file($fullPath)) {
        unlink($fullPath);
    }
}
