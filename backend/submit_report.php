<?php
/**
 * submit_report.php — Save a new theft report (guest or logged-in user)
 *
 * Method: POST
 * Input (JSON): fullname, phone, email, category, itemName, description,
 *               location, incidentDate, suspect (optional)
 * Output: { success, tracking_id, report: { … } }
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed.', [], 405);
}

$input = getJsonInput();

$fullname = cleanInput($input['fullname'] ?? '', 100);
$phone = cleanInput($input['phone'] ?? '', 20);
$email = cleanInput($input['email'] ?? '', 100);
$category = cleanInput($input['category'] ?? '', 50);
$itemName = cleanInput($input['itemName'] ?? '', 150);
$description = cleanInput($input['description'] ?? '', 5000);
$location = cleanInput($input['location'] ?? '', 200);
$incidentDate = cleanInput($input['incidentDate'] ?? '', 20);
$suspectInfo = cleanInput($input['suspect'] ?? '', 2000);

// --- Required fields ---
if ($fullname === '' || $phone === '' || $itemName === '' || $description === '' || $location === '') {
    jsonResponse(false, 'Please fill in all required fields.');
}

if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(false, 'Invalid email address.');
}

// --- Build values for database ---
// Item is stored as: [category] Item name
$itemColumn = $category !== '' ? '[' . $category . '] ' . $itemName : $itemName;

// Extra details are appended to the description column
$descriptionColumn = $description;
if ($incidentDate !== '') {
    $descriptionColumn .= "\n\nDate of theft: " . $incidentDate;
}
if ($suspectInfo !== '') {
    $descriptionColumn .= "\n\nSuspect information: " . $suspectInfo;
}

startAppSession();
$loggedInUserId = !empty($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;

if ($loggedInUserId && $email === '' && !empty($_SESSION['email'])) {
    $email = $_SESSION['email'];
}

$trackingId = generateTrackingId($conn);
$statusLabel = STATUS_PENDING;
$emailForDatabase = $email !== '' ? $email : null;

// --- Insert: slightly different SQL if user is logged in ---
if ($loggedInUserId !== null) {
    $insertStatement = $conn->prepare(
        'INSERT INTO reports (tracking_id, user_id, fullname, phone, email, item, description, location, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $insertStatement->bind_param(
        'sisssssss',
        $trackingId,
        $loggedInUserId,
        $fullname,
        $phone,
        $emailForDatabase,
        $itemColumn,
        $descriptionColumn,
        $location,
        $statusLabel
    );
} else {
    $insertStatement = $conn->prepare(
        'INSERT INTO reports (tracking_id, fullname, phone, email, item, description, location, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $insertStatement->bind_param(
        'ssssssss',
        $trackingId,
        $fullname,
        $phone,
        $emailForDatabase,
        $itemColumn,
        $descriptionColumn,
        $location,
        $statusLabel
    );
}

try {
    $insertStatement->execute();
} catch (mysqli_sql_exception $exception) {
    // Very rare: tracking ID collision — try once more with a suffix
    if (strpos($exception->getMessage(), 'Duplicate') !== false) {
        $trackingId = generateTrackingId($conn) . random_int(10, 99);

        if ($loggedInUserId !== null) {
            $insertStatement = $conn->prepare(
                'INSERT INTO reports (tracking_id, user_id, fullname, phone, email, item, description, location, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $insertStatement->bind_param(
                'sisssssss',
                $trackingId,
                $loggedInUserId,
                $fullname,
                $phone,
                $emailForDatabase,
                $itemColumn,
                $descriptionColumn,
                $location,
                $statusLabel
            );
        } else {
            $insertStatement = $conn->prepare(
                'INSERT INTO reports (tracking_id, fullname, phone, email, item, description, location, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $insertStatement->bind_param(
                'ssssssss',
                $trackingId,
                $fullname,
                $phone,
                $emailForDatabase,
                $itemColumn,
                $descriptionColumn,
                $location,
                $statusLabel
            );
        }
        $insertStatement->execute();
    } else {
        throw $exception;
    }
}

// --- Load the saved row and log first status ---
$selectStatement = $conn->prepare('SELECT * FROM reports WHERE tracking_id = ? LIMIT 1');
$selectStatement->bind_param('s', $trackingId);
$selectStatement->execute();
$savedReportRow = $selectStatement->get_result()->fetch_assoc();
$reportDatabaseId = (int) $savedReportRow['id'];

try {
    logCaseUpdate($conn, $reportDatabaseId, 'status', STATUS_PENDING, null, 'System');
} catch (mysqli_sql_exception $e) {
    // Old databases may not have case_updates — run sql/upgrade_v2.sql
}

jsonResponse(true, 'Report submitted successfully. Save your Tracking ID.', [
    'tracking_id' => $trackingId,
    'report' => formatReportForApi($conn, $savedReportRow),
]);
