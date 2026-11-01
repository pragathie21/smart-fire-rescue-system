<?php
/**
 * Smart Fire and Rescue Response System
 * Action: Authorized Severity Assessment Override / Correction
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
require_auth(); // Only logged-in officers or admins can correct triage

$currentUser = current_user();
$officer = $currentUser['full_name'] ?? 'Authorized Officer';

$incidentId   = (int)($_POST['incident_id'] ?? 0);
$newSeverity  = trim($_POST['override_severity'] ?? '');
$reason       = trim($_POST['override_reason'] ?? '');

if ($incidentId <= 0) {
    set_flash('danger', 'Valid Incident ID is required.');
    header("Location: " . BASE_URL . "incidents.php");
    exit;
}

if (!in_array($newSeverity, ['Low', 'Medium', 'High', 'Critical'], true)) {
    set_flash('danger', 'Please select a valid severity tier.');
    header("Location: " . BASE_URL . "incident-details.php?id=" . urlencode((string)$incidentId));
    exit;
}

if (empty($reason)) {
    set_flash('danger', 'Operational justification reason is required for assessment correction.');
    header("Location: " . BASE_URL . "incident-details.php?id=" . urlencode((string)$incidentId));
    exit;
}

try {
    $incidentModel = new Incident();
    $incidentModel->overrideSeverity($incidentId, $newSeverity, $reason, $officer);

    set_flash('success', "⚖️ Severity for Incident #{$incidentId} corrected to <strong>{$newSeverity}</strong> by {$officer}. Audit reason recorded.");
    header("Location: " . BASE_URL . "incident-details.php?id=" . urlencode((string)$incidentId));
    exit;
} catch (Exception $e) {
    set_flash('danger', "Failed to correct assessment: " . htmlspecialchars($e->getMessage()));
    header("Location: " . BASE_URL . "incident-details.php?id=" . urlencode((string)$incidentId));
    exit;
}
