<?php
/**
 * Smart Fire and Rescue Response System
 * Action: Run CAD Auto-Dispatch on all pending incidents
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../classes/DispatchService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: " . BASE_URL . "dashboard.php");
    exit;
}

try {
    $dispatchService = new DispatchService();
    $result = $dispatchService->runAutoDispatch();
    $count = $result['count'] ?? 0;

    if ($count > 0) {
        $unitsList = implode(', ', array_map(function($d) {
            return "Unit <strong>{$d['unit_id']}</strong> ➔ Incident #{$d['incident_id']} (ETA: {$d['travel_time']}m)";
        }, $result['dispatched']));
        set_flash('success', "⚡ <strong>CAD Auto-Dispatch Complete:</strong> Successfully dispatched {$count} unit(s): {$unitsList} via Dijkstra shortest route.");
    } else {
        set_flash('info', "ℹ️ <strong>CAD Engine Checked:</strong> No pending incidents waiting, or all compatible emergency units are currently engaged in active missions.");
    }
    $redirect = !empty($_POST['redirect']) ? $_POST['redirect'] : 'dashboard.php';
    header("Location: " . BASE_URL . $redirect);
    exit;
} catch (Exception $e) {
    set_flash('danger', "Auto-dispatch error: " . htmlspecialchars($e->getMessage()));
    $redirect = !empty($_POST['redirect']) ? $_POST['redirect'] : 'dashboard.php';
    header("Location: " . BASE_URL . $redirect);
    exit;
}
