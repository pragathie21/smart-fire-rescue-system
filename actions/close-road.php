<?php
/**
 * Smart Fire and Rescue Response System
 * Action: Close Road Segment
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../classes/RoadNetwork.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: " . BASE_URL . "roads.php");
    exit;
}

require_csrf_token();

$roadId = (int)($_POST['road_id'] ?? 0);

if ($roadId <= 0) {
    set_flash('danger', 'Invalid road segment ID.');
    header("Location: " . BASE_URL . "roads.php");
    exit;
}

try {
    $roadNetwork = new RoadNetwork();
    $road = $roadNetwork->getRoadById($roadId);

    if (!$road) {
        set_flash('danger', 'Road segment not found.');
    } else {
        $roadNetwork->updateRoadStatus($roadId, 'Closed');
        set_flash('warning', "🚧 Road Segment #{$roadId} [<strong>{$road['from_name']} ⬌ {$road['to_name']}</strong>] has been CLOSED. Emergency routes will immediately avoid this segment.");
    }
} catch (Exception $e) {
    set_flash('danger', "Error updating road status: " . htmlspecialchars($e->getMessage()));
}

header("Location: " . BASE_URL . "roads.php");
exit;
