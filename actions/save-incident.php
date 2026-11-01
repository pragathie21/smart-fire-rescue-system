<?php
/**
 * Smart Fire and Rescue Response System
 * Action: Save New Incident Report & Execute Priority-Based CAD Dispatch
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../classes/Incident.php';
require_once __DIR__ . '/../classes/DispatchService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: " . BASE_URL . "report-incident.php");
    exit;
}

// CSRF Verification (gentle fallback for public emergency reporting)
$token = $_POST['csrf_token'] ?? null;
if (!empty($token) && !verify_csrf_token($token)) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Intake form inputs
$type        = trim($_POST['incident_type'] ?? 'Fire');
$locationId  = trim($_POST['location_id'] ?? '');
$latRaw      = trim($_POST['latitude'] ?? '');
$lngRaw      = trim($_POST['longitude'] ?? '');
$latitude    = ($latRaw !== '' && is_numeric($latRaw)) ? (float)$latRaw : null;
$longitude   = ($lngRaw !== '' && is_numeric($lngRaw)) ? (float)$lngRaw : null;
$address     = trim($_POST['location_address'] ?? '');
$locDesc     = trim($_POST['location_description'] ?? '');
$equipment   = $_POST['required_equipment'] ?? [];
$notes       = trim($_POST['description'] ?? '');

// Smart Defaults for Location if not provided
if (empty($locationId)) {
    $locationId = 'Hospital'; // Default safe landmark in Sudar
}

// Smart Defaults for Equipment if none checked
if (!is_array($equipment) || empty($equipment)) {
    if ($type === 'Rescue') {
        $equipment = ['RescueKit', 'FirstAid'];
    } elseif ($type === 'Medical Emergency') {
        $equipment = ['FirstAid'];
    } else {
        $equipment = ['WaterPump', 'Ladder'];
    }
}

// Assessment questionnaire inputs (Requirement 3)
$assessment = [
    'fire_size'               => trim($_POST['fire_size'] ?? 'Medium'),
    'fire_spreading'          => !empty($_POST['fire_spreading']) ? 1 : 0,
    'people_trapped'          => !empty($_POST['people_trapped']) ? 1 : 0,
    'injuries_reported'       => !empty($_POST['injuries_reported']) ? 1 : 0,
    'heavy_smoke'             => !empty($_POST['heavy_smoke']) ? 1 : 0,
    'building_type'           => trim($_POST['building_type'] ?? 'Residential'),
    'hazmat_involved'         => trim($_POST['hazmat_involved'] ?? 'No'),
    'people_exposed'          => max(0, (int)($_POST['people_exposed'] ?? 0)),
    'critical_infrastructure' => !empty($_POST['critical_infrastructure']) ? 1 : 0,
    'description'             => $notes
];

// Server-side validation
$errors = [];

if (empty($type) || !in_array($type, ALLOWED_INCIDENT_TYPES, true)) {
    $errors[] = "Please select a valid emergency incident type.";
}

// Ensure equipment contains valid items
$validEquipment = [];
foreach ($equipment as $eq) {
    if (in_array($eq, ALLOWED_EQUIPMENT, true)) {
        $validEquipment[] = $eq;
    }
}
if (empty($validEquipment)) {
    $validEquipment = ($type === 'Rescue') ? ['RescueKit'] : (($type === 'Medical Emergency') ? ['FirstAid'] : ['WaterPump']);
}
$equipment = array_unique($validEquipment);

if ($latitude !== null && ($latitude < -90 || $latitude > 90)) {
    $errors[] = "Latitude must be between -90 and 90 degrees.";
}
if ($longitude !== null && ($longitude < -180 || $longitude > 180)) {
    $errors[] = "Longitude must be between -180 and 180 degrees.";
}

if (!empty($errors)) {
    set_flash('danger', implode('<br>', $errors));
    header("Location: " . BASE_URL . "report-incident.php");
    exit;
}

try {
    // 1. Create incident in MySQL (generates sequential AUTO_INCREMENT ID)
    $incidentModel = new Incident();
    $incidentId = $incidentModel->create(
        $type,
        $locationId ?: null,
        $latitude,
        $longitude,
        $address,
        $locDesc,
        $assessment,
        $equipment,
        $notes
    );

    // 2. Trigger automatic priority-based dispatch (Requirement 5)
    $dispatchService = new DispatchService();
    $dispatchOutcome = $dispatchService->runAutoDispatch($incidentId);

    // Check if our new incident was automatically dispatched
    $assignedUnit = null;
    foreach ($dispatchOutcome['dispatched'] as $d) {
        if ((int)$d['incident_id'] === $incidentId) {
            $assignedUnit = $d;
            break;
        }
    }

    if ($assignedUnit) {
        $emailMsg = "";
        if (!empty($assignedUnit['email_result'])) {
            $eRes = $assignedUnit['email_result'];
            $emailMsg = $eRes['success']
                ? " &bull; ✉️ Notification dispatched to officer."
                : " &bull; ⚠️ Email notification failed: " . htmlspecialchars($eRes['error']);
        }

        set_flash('success', "🚨 Incident <strong>#{$incidentId}</strong> registered! Automatic dispatch assigned Unit <strong>{$assignedUnit['unit_id']}</strong> ({$assignedUnit['unit_type']}) from {$assignedUnit['station_name']} (ETA: <strong>{$assignedUnit['travel_time']} mins</strong>){$emailMsg}");
    } else {
        // Incident queued in Pending state
        $pendingReason = "Awaiting available compatible response unit";
        foreach ($dispatchOutcome['pending'] as $p) {
            if ((int)$p['incident_id'] === $incidentId) {
                $pendingReason = $p['reason'];
                break;
            }
        }
        set_flash('warning', "🚨 Incident <strong>#{$incidentId}</strong> registered with High-Priority CAD status! Currently <strong>Pending</strong> ({$pendingReason}). Will automatically assign once a compatible unit is released.");
    }

    header("Location: " . BASE_URL . "dashboard.php?new_incident=" . urlencode((string)$incidentId));
    exit;
} catch (Exception $e) {
    set_flash('danger', "Error creating incident report: " . htmlspecialchars($e->getMessage()));
    header("Location: " . BASE_URL . "report-incident.php");
    exit;
}
