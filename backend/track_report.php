<?php
/**
 * track_report.php — Look up a theft report by tracking ID
 *
 * Method: GET or POST
 * Input: tracking_id (or id), phone (optional — extra verification)
 * Output: { success, report: { … } }
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

// Read from GET (URL) or POST (JSON body)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = getJsonInput();
} else {
    $input = $_GET;
}

$trackingId = strtoupper(cleanInput($input['tracking_id'] ?? $input['id'] ?? '', 20));
$phoneForVerification = cleanInput($input['phone'] ?? '', 20);

if ($trackingId === '') {
    jsonResponse(false, 'Tracking ID is required.');
}

// --- Find report in database ---
$statement = $conn->prepare('SELECT * FROM reports WHERE tracking_id = ? LIMIT 1');
$statement->bind_param('s', $trackingId);
$statement->execute();
$reportRow = $statement->get_result()->fetch_assoc();

if (!$reportRow) {
    jsonResponse(false, 'No report found with that Tracking ID.', ['found' => false], 404);
}

// --- Optional: verify phone number matches ---
if ($phoneForVerification !== '') {
    $inputDigits = preg_replace('/\D/', '', $phoneForVerification);
    $storedDigits = preg_replace('/\D/', '', $reportRow['phone']);

    if ($inputDigits !== '' && $inputDigits !== $storedDigits) {
        jsonResponse(false, 'Phone number does not match this report.', [
            'found' => true,
            'phone_mismatch' => true,
        ], 403);
    }
}

jsonResponse(true, 'Report found.', [
    'found' => true,
    'report' => formatReportForApi($conn, $reportRow),
]);
