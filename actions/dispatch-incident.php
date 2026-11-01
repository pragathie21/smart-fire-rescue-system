<?php
/**
 * Smart Fire and Rescue Response System
 * Action: Dispatch Emergency Response Unit (Manual or Re-dispatch)
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../classes/DispatchService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: " . BASE_URL . "dispatch.php");
    exit;
}

require_csrf_token();

$incidentId = (int)($_POST['incident_id'] ?? 0);
$unitId     = trim($_POST['unit_id'] ?? '');
$notes      = trim($_POST['notes'] ?? '');

if ($incidentId <= 0 || empty($unitId)) {
    set_flash('danger', 'Both Incident ID and Response Unit ID are required for dispatch.');
    header("Location: " . BASE_URL . "dispatch.php?incident_id=" . urlencode((string)$incidentId));
    exit;
}

try {
    $dispatchService = new DispatchService();
    $dispatchId = $dispatchService->dispatch($incidentId, $unitId, $notes);

    set_flash('success', "🚒 Response Unit <strong>{$unitId}</strong> has been successfully dispatched to Incident <strong>#{$incidentId}</strong>! (Dispatch #{$dispatchId})");
    header("Location: " . BASE_URL . "incident-details.php?id=" . urlencode((string)$incidentId));
    exit;
} catch (Exception $e) {
    set_flash('danger', "Dispatch failed: " . htmlspecialchars($e->getMessage()));
    header("Location: " . BASE_URL . "dispatch.php?incident_id=" . urlencode((string)$incidentId));
    exit;
}
