<?php
/**
 * fraud_public.php — Data for the public Fraud Alerts page (fraud.html)
 *
 * Method: GET only
 * Output: reports as "alerts", locations as "highRiskAreas", suspects from suspects table
 *
 * All data comes from the database — no fake/demo cards.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(false, 'Method not allowed.', [], 405);
}

$reportAlerts = [];
$highRiskAreas = [];
$suspectsList = [];
$totalReports = 0;
$totalSuspects = 0;

try {
    // --- Count reports ---
    $countResult = $conn->query('SELECT COUNT(*) AS total FROM reports');
    $totalReports = (int) ($countResult->fetch_assoc()['total'] ?? 0);

    // --- One card per report ---
    $reportsResult = $conn->query(
        'SELECT tracking_id, item, location, description, status, created_at
         FROM reports
         ORDER BY created_at DESC'
    );

    while ($reportRow = $reportsResult->fetch_assoc()) {
        $parsedItem = parseItemField($reportRow['item']);
        $parsedDescription = parseDescriptionExtras($reportRow['description']);

        $shortDescription = $parsedDescription['description'];
        if (strlen($shortDescription) > 200) {
            $shortDescription = substr($shortDescription, 0, 197) . '…';
        }

        $reportAlerts[] = [
            'type' => $parsedItem['itemName'],
            'category' => $parsedItem['category'],
            'description' => $shortDescription !== '' ? $shortDescription : 'No additional description provided.',
            'location' => $reportRow['location'],
            'date' => substr($reportRow['created_at'], 0, 10),
            'risk' => calculateRiskFromReportStatus($reportRow['status']),
            'status' => statusToKey($reportRow['status']),
            'tracking_id' => $reportRow['tracking_id'],
            'source' => 'reports',
        ];
    }

    // --- Group reports by location for "high risk areas" ---
    $areasResult = $conn->query(
        "SELECT location, COUNT(*) AS report_count
         FROM reports
         WHERE location IS NOT NULL AND TRIM(location) <> ''
         GROUP BY location
         ORDER BY report_count DESC"
    );

    while ($areaRow = $areasResult->fetch_assoc()) {
        $countAtLocation = (int) $areaRow['report_count'];

        $highRiskAreas[] = [
            'name' => $areaRow['location'],
            'count' => $countAtLocation,
            'risk' => calculateRiskFromReportCount($countAtLocation),
            'source' => 'reports',
        ];
    }

    // --- Suspects added by admin (active only) ---
    $suspectsResult = $conn->query(
        "SELECT * FROM suspects WHERE status = 'active' ORDER BY created_at DESC"
    );

    while ($suspectRow = $suspectsResult->fetch_assoc()) {
        $suspectsList[] = formatSuspectForApi($suspectRow);
    }

    $totalSuspects = count($suspectsList);
} catch (mysqli_sql_exception $exception) {
    if (strpos($exception->getMessage(), 'suspects') !== false) {
        jsonResponse(false, 'Suspects table missing. Run sql/upgrade_v3_suspects.sql in phpMyAdmin.', [
            'alerts' => $reportAlerts,
            'highRiskAreas' => $highRiskAreas,
            'suspects' => [],
            'reportCount' => $totalReports,
            'suspectCount' => 0,
            'hasLiveData' => $totalReports > 0,
        ], 500);
    }

    jsonResponse(false, 'Could not load report data. Check database connection.', [
        'alerts' => [],
        'highRiskAreas' => [],
        'suspects' => [],
        'reportCount' => 0,
        'suspectCount' => 0,
        'hasLiveData' => false,
    ], 500);
}

$hasAnyData = $totalReports > 0 || $totalSuspects > 0;

if (!$hasAnyData) {
    $footerMessage = 'No reports or suspects in the database yet. Admins can add suspects in the admin panel.';
} else {
    $summaryParts = [];
    if ($totalReports > 0) {
        $summaryParts[] = $totalReports . ' report' . ($totalReports === 1 ? '' : 's');
    }
    if ($totalSuspects > 0) {
        $summaryParts[] = $totalSuspects . ' suspect' . ($totalSuspects === 1 ? '' : 's');
    }
    $footerMessage = 'Showing ' . implode(' and ', $summaryParts) . ' from the database.';
}

jsonResponse(true, 'Fraud data loaded from database.', [
    'alerts' => $reportAlerts,
    'highRiskAreas' => $highRiskAreas,
    'suspects' => $suspectsList,
    'reportCount' => $totalReports,
    'suspectCount' => $totalSuspects,
    'hasLiveData' => $hasAnyData,
    'disclaimer' => $footerMessage,
]);
