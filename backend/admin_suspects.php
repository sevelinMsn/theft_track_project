<?php
/**
 * admin_suspects.php — Admin: manage suspects shown on fraud.html
 *
 * GET:           list all suspects
 * POST (form):   create new suspect OR update existing (send id in form)
 * DELETE (JSON): { "id": 1 }
 *
 * Photo upload: field name "photo" (multipart form)
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

requireAdminSession();

$photoUploadFolder = __DIR__ . '/uploads/suspects/';


// =============================================================================
// GET — return list of all suspects
// =============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $result = $conn->query('SELECT * FROM suspects ORDER BY created_at DESC');
        $suspectList = [];

        while ($row = $result->fetch_assoc()) {
            $formatted = formatSuspectForApi($row);
            // Admin panel uses a slightly different photo path
            $formatted['photo_admin_url'] = $formatted['photo']
                ? '../uploads/suspects/' . basename($formatted['photo'])
                : null;
            $suspectList[] = $formatted;
        }

        jsonResponse(true, 'Suspects loaded.', ['suspects' => $suspectList]);
    } catch (mysqli_sql_exception $e) {
        jsonResponse(false, 'Suspects table missing. Run sql/upgrade_v3_suspects.sql in phpMyAdmin.', [], 500);
    }
}


// =============================================================================
// DELETE — remove one suspect (and photo file)
// =============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $input = getJsonInput();
    $suspectId = (int) ($input['id'] ?? 0);

    if ($suspectId <= 0) {
        jsonResponse(false, 'Invalid suspect ID.');
    }

    $findStatement = $conn->prepare('SELECT photo_path FROM suspects WHERE id = ? LIMIT 1');
    $findStatement->bind_param('i', $suspectId);
    $findStatement->execute();
    $existingRow = $findStatement->get_result()->fetch_assoc();

    if (!$existingRow) {
        jsonResponse(false, 'Suspect not found.');
    }

    $deleteStatement = $conn->prepare('DELETE FROM suspects WHERE id = ?');
    $deleteStatement->bind_param('i', $suspectId);
    $deleteStatement->execute();

    deleteSuspectPhotoFile($existingRow['photo_path'], $photoUploadFolder);

    jsonResponse(true, 'Suspect deleted.');
}


// =============================================================================
// POST — create or update a suspect
// =============================================================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed.', [], 405);
}

$alias = cleanInput($_POST['alias'] ?? '', 150);
$caseType = cleanInput($_POST['case_type'] ?? 'Theft / Fraud', 100);
$lastSeen = cleanInput($_POST['last_seen'] ?? '', 200);
$description = cleanInput($_POST['description'] ?? '', 5000);
$riskLevel = cleanInput($_POST['risk_level'] ?? 'medium', 20);
$status = cleanInput($_POST['status'] ?? 'active', 20);
$linkedTrackingId = cleanInput($_POST['linked_tracking_id'] ?? '', 20);
$editingId = (int) ($_POST['id'] ?? 0);

$allowedRiskLevels = ['low', 'medium', 'high', 'critical'];
$allowedStatuses = ['active', 'inactive'];

if (!in_array($riskLevel, $allowedRiskLevels, true)) {
    $riskLevel = 'medium';
}
if (!in_array($status, $allowedStatuses, true)) {
    $status = 'active';
}

if ($alias === '' || $lastSeen === '') {
    jsonResponse(false, 'Alias and last seen location are required.');
}

$adminName = $_SESSION['admin_name'] ?? 'Admin';
$linkedCaseId = $linkedTrackingId !== '' ? $linkedTrackingId : null;

// Handle optional new photo upload
$newPhotoFilename = null;
if (!empty($_FILES['photo']['tmp_name'])) {
    $newPhotoFilename = saveSuspectPhotoFile($_FILES['photo'], $photoUploadFolder);
}

try {
    // --- UPDATE existing record ---
    if ($editingId > 0) {
        $findStatement = $conn->prepare('SELECT photo_path FROM suspects WHERE id = ? LIMIT 1');
        $findStatement->bind_param('i', $editingId);
        $findStatement->execute();
        $existingRow = $findStatement->get_result()->fetch_assoc();

        if (!$existingRow) {
            jsonResponse(false, 'Suspect not found.');
        }

        $photoPathToSave = $existingRow['photo_path'];

        if ($newPhotoFilename !== null) {
            deleteSuspectPhotoFile($photoPathToSave, $photoUploadFolder);
            $photoPathToSave = $newPhotoFilename;
        }

        $updateStatement = $conn->prepare(
            'UPDATE suspects SET alias = ?, case_type = ?, last_seen = ?, description = ?,
             photo_path = ?, risk_level = ?, status = ?, linked_tracking_id = ?
             WHERE id = ?'
        );
        $updateStatement->bind_param(
            'ssssssssi',
            $alias,
            $caseType,
            $lastSeen,
            $description,
            $photoPathToSave,
            $riskLevel,
            $status,
            $linkedCaseId,
            $editingId
        );
        $updateStatement->execute();

        $loadStatement = $conn->prepare('SELECT * FROM suspects WHERE id = ? LIMIT 1');
        $loadStatement->bind_param('i', $editingId);
        $loadStatement->execute();
        $updatedRow = $loadStatement->get_result()->fetch_assoc();

        jsonResponse(true, 'Suspect updated.', ['suspect' => formatSuspectForApi($updatedRow)]);
    }

    // --- INSERT new record ---
    $insertStatement = $conn->prepare(
        'INSERT INTO suspects (alias, case_type, last_seen, description, photo_path, risk_level, status, linked_tracking_id, created_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $insertStatement->bind_param(
        'sssssssss',
        $alias,
        $caseType,
        $lastSeen,
        $description,
        $newPhotoFilename,
        $riskLevel,
        $status,
        $linkedCaseId,
        $adminName
    );
    $insertStatement->execute();

    $newId = (int) $conn->insert_id;

    $loadStatement = $conn->prepare('SELECT * FROM suspects WHERE id = ? LIMIT 1');
    $loadStatement->bind_param('i', $newId);
    $loadStatement->execute();
    $newRow = $loadStatement->get_result()->fetch_assoc();

    jsonResponse(true, 'Suspect added.', ['suspect' => formatSuspectForApi($newRow)]);
} catch (mysqli_sql_exception $e) {
    jsonResponse(false, 'Suspects table missing. Run sql/upgrade_v3_suspects.sql in phpMyAdmin.', [], 500);
}
