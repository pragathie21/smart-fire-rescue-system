<?php
/**
 * Smart Fire and Rescue Response System
 * Action: Update Incident Lifecycle Status (e.g. Mark In Progress)
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../classes/Incident.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: " . BASE_URL . "incidents.php");
    exit;
}

require_csrf_token();
require_auth(); // Only logged-in personnel

$currentUser = current_user();
$officer = $currentUser['full_name'] ?? 'Authorized Officer';

$incidentId = (int)($_POST['incident_id'] ?? 0);
$status     = trim($_POST['status'] ?? '');

if ($incidentId <= 0) {
    set_flash('danger', 'Valid Incident ID is required.');
    header("Location: " . BASE_URL . "incidents.php");
    exit;
}

try {
    $incidentModel = new Incident();
    if ($status === 'In Progress') {
        $incidentModel->markInProgress($incidentId, $officer);
        set_flash('success', "⚡ Incident #{$incidentId} marked as IN PROGRESS by {$officer}. Units on-scene.");
    }

    header("Location: " . BASE_URL . "incident-details.php?id=" . urlencode((string)$incidentId));
    exit;
} catch (Exception $e) {
    set_flash('danger', "Status update failed: " . htmlspecialchars($e->getMessage()));
    header("Location: " . BASE_URL . "incident-details.php?id=" . urlencode((string)$incidentId));
    exit;
}
