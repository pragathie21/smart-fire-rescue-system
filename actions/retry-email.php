<?php
/**
 * Smart Fire and Rescue Response System
 * Action: Retry Email Dispatch Notification
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../classes/EmailNotificationService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: " . BASE_URL . "records.php");
    exit;
}

require_csrf_token();

$dispatchId = (int)($_POST['dispatch_id'] ?? 0);
$incidentId = (int)($_POST['incident_id'] ?? 0);
$redirect   = trim($_POST['redirect'] ?? 'records.php');

if ($dispatchId <= 0) {
    set_flash('danger', 'Valid Dispatch ID is required to retry email alert.');
    header("Location: " . BASE_URL . $redirect);
    exit;
}

try {
    $emailService = new EmailNotificationService();
    $result = $emailService->retryNotification($dispatchId);

    if ($result['success']) {
        set_flash('success', "✉️ Email notification for Dispatch #{$dispatchId} successfully delivered to {$result['recipient']}.");
    } else {
        set_flash('danger', "❌ Email transmission failed: " . htmlspecialchars($result['error']));
    }

    if ($incidentId > 0 && strpos($redirect, 'incident-details.php') !== false) {
        header("Location: " . BASE_URL . "incident-details.php?id=" . urlencode((string)$incidentId));
    } else {
        header("Location: " . BASE_URL . "records.php");
    }
    exit;
} catch (Exception $e) {
    set_flash('danger', "Error retrying email: " . htmlspecialchars($e->getMessage()));
    header("Location: " . BASE_URL . $redirect);
    exit;
}
