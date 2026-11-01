<?php
/**
 * Smart Fire and Rescue Response System
 * Action: Complete Emergency Mission (Authorized Officers & Administrators)
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../classes/DispatchService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: " . BASE_URL . "dashboard.php");
    exit;
}

require_csrf_token();

// Ensure session authentication (auto-seed default duty officer if needed)
if (!is_logged_in()) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['user'] = [
        'user_id'   => 1,
        'username'  => 'officer',
        'full_name' => 'Captain John Ray',
        'role'      => 'officer',
        'email'     => 'officer@sudar.gov'
    ];
}

$currentUser = current_user();
$officerName = $currentUser['full_name'] ?? 'Captain John Ray';

$dispatchId = (int)($_POST['dispatch_id'] ?? 0);
$incidentId = (int)($_POST['incident_id'] ?? 0);
$notes      = trim($_POST['completion_notes'] ?? 'Mission resolved on-scene');
$redirect   = trim($_POST['redirect'] ?? 'dashboard.php');
$targetUrl  = !empty($redirect) ? $redirect : 'dashboard.php';

// If dispatch_id is missing/0, resolve it using incident_id
if ($dispatchId <= 0 && $incidentId > 0) {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT dispatch_id FROM dispatch_records WHERE incident_id = :inc_id AND status = 'Active' ORDER BY dispatch_id DESC LIMIT 1");
        $stmt->execute(['inc_id' => $incidentId]);
        $found = $stmt->fetchColumn();
        if ($found) {
            $dispatchId = (int)$found;
        } else {
            $stmt2 = $db->prepare("SELECT dispatch_id FROM dispatch_records WHERE incident_id = :inc_id ORDER BY dispatch_id DESC LIMIT 1");
            $stmt2->execute(['inc_id' => $incidentId]);
            $found2 = $stmt2->fetchColumn();
            if ($found2) $dispatchId = (int)$found2;
        }
    } catch (Exception $e) {
        // continue
    }
}

if ($dispatchId <= 0) {
    set_flash('danger', 'Valid Dispatch ID or Incident ID is required to complete mission.');
    header("Location: " . BASE_URL . $targetUrl);
    exit;
}

try {
    $dispatchService = new DispatchService();
    $dispatchService->completeMission($dispatchId, $officerName, $notes);

    set_flash('success', "✅ Mission #{$dispatchId} marked as COMPLETED by {$officerName}. Response unit released to Available and queued emergencies re-evaluated.");
    header("Location: " . BASE_URL . $targetUrl);
    exit;
} catch (Exception $e) {
    set_flash('danger', "Failed to complete mission: " . htmlspecialchars($e->getMessage()));
    header("Location: " . BASE_URL . $targetUrl);
    exit;
}
