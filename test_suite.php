<?php
/**
 * Smart Fire and Rescue Response System
 * Comprehensive Automated Verification Test Suite
 *
 * Validates all system requirements:
 * 1. Automatic Sequential Incident IDs (1, 2, 3...)
 * 2. GPS Location & Manual Address Fallback
 * 3. Rules-Based Triage Severity & Priority Scoring
 * 4. Multiple Equipment Capabilities (7 types)
 * 5. Priority-Based Automatic Dispatch & Concurrency Protection
 * 6. Email Dispatch Notification & Retry
 * 7. Dijkstra Routing, Road Closures & Reopening
 * 8. Incident Completion, Officer Audit & Automatic Queue Processing
 * 9. Unified MySQL Database Consistency
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/classes/Incident.php';
require_once __DIR__ . '/classes/ResponseUnit.php';
require_once __DIR__ . '/classes/RoadNetwork.php';
require_once __DIR__ . '/classes/RoutePlanner.php';
require_once __DIR__ . '/classes/DispatchService.php';
require_once __DIR__ . '/classes/SeverityScorer.php';
require_once __DIR__ . '/classes/EmailNotificationService.php';

$db = getDB();
$testResults = [];

function record_test(string $id, string $title, bool $pass, string $details = ''): void {
    global $testResults;
    $testResults[] = [
        'id'      => $id,
        'title'   => $title,
        'pass'    => $pass,
        'details' => $details
    ];
    $icon = $pass ? "✅ PASS" : "❌ FAIL";
    echo "{$icon} [{$id}] {$title}: {$details}\n";
}

echo "=======================================================\n";
echo "SMART FIRE & RESCUE RESPONSE SYSTEM - TEST SUITE\n";
echo "Town: " . TOWN_NAME . " | Database: " . DB_NAME . "\n";
echo "=======================================================\n\n";

// ---------------------------------------------------------------------
// TEST SETUP: Clean test incidents and reset unit availability
// ---------------------------------------------------------------------
echo ">>> Initializing Clean Database State...\n";
try { $db->exec("DELETE FROM email_logs"); } catch (Exception $e) {}
$db->exec("DELETE FROM dispatch_records");
$db->exec("DELETE FROM incident_equipment");
$db->exec("DELETE FROM incidents");
$db->exec("ALTER TABLE incidents AUTO_INCREMENT = 1");
$db->exec("UPDATE response_units SET status = 'Available', current_incident_id = NULL");
$db->exec("UPDATE roads SET status = 'Open'");
echo ">>> Database state clean. AUTO_INCREMENT reset to 1.\n\n";

$incidentModel   = new Incident();
$unitModel       = new ResponseUnit();
$dispatchService = new DispatchService();
$roadNetwork     = new RoadNetwork();
$routePlanner    = new RoutePlanner();
$emailService    = new EmailNotificationService();

// ---------------------------------------------------------------------
// TEST 1: Automatic Sequential Incident ID (1, 2, 3...)
// ---------------------------------------------------------------------
try {
    $inc1 = $incidentModel->create(
        'Fire',
        'Hospital',
        13.0780,
        80.2650,
        'Hospital Main Block',
        'Near emergency wing',
        ['fire_size' => 'Medium', 'people_trapped' => 0, 'critical_infrastructure' => 0],
        ['WaterPump', 'Ladder'],
        'First test incident'
    );

    $inc2 = $incidentModel->create(
        'Rescue',
        'RiverBridge',
        13.0750,
        80.2820,
        'Bridge Suspension South',
        'Near river bank',
        ['people_trapped' => 1, 'injuries_reported' => 1, 'critical_infrastructure' => 1],
        ['FirstAid', 'RescueKit'],
        'Second test incident'
    );

    $inc3 = $incidentModel->create(
        'Medical Emergency',
        'Market',
        13.0840,
        80.2750,
        'Central Bazaar Stall #4',
        'Opposite clock tower',
        ['injuries_reported' => 1],
        ['FirstAid', 'BreathingApparatus'],
        'Third test incident'
    );

    $passSeq = ($inc1 === 1 && $inc2 === 2 && $inc3 === 3);
    record_test('TEST 1', 'Sequential Incident IDs', $passSeq, "Generated IDs: [{$inc1}, {$inc2}, {$inc3}] (Expected: [1, 2, 3])");
} catch (Exception $e) {
    record_test('TEST 1', 'Sequential Incident IDs', false, $e->getMessage());
}

// ---------------------------------------------------------------------
// TEST 2: GPS Location & Geocoded Address Persistence
// ---------------------------------------------------------------------
try {
    $rec1 = $incidentModel->getById(1);
    $passGps = ($rec1 !== null &&
                abs((float)$rec1['latitude'] - 13.0780) < 0.0001 &&
                abs((float)$rec1['longitude'] - 80.2650) < 0.0001 &&
                $rec1['location_address'] === 'Hospital Main Block');
    record_test('TEST 2', 'GPS & Address Persistence', $passGps, "Stored Lat: {$rec1['latitude']}, Lng: {$rec1['longitude']}, Address: {$rec1['location_address']}");
} catch (Exception $e) {
    record_test('TEST 2', 'GPS & Address Persistence', false, $e->getMessage());
}

// ---------------------------------------------------------------------
// TEST 3: Rules-Based Severity & Priority Scoring
// ---------------------------------------------------------------------
try {
    $rec2 = $incidentModel->getById(2); // Critical infrastructure + Trapped civilians + Casualties
    $evalLow = SeverityScorer::evaluate([
        'incident_type' => 'Medical Emergency',
        'building_type' => 'Other',
        'people_trapped' => 0,
        'injuries_reported' => 0,
        'critical_infrastructure' => 0,
        'people_exposed' => 0
    ]);

    $passSev = ($rec2['severity_tier'] === 'Critical' &&
                $rec2['priority_score'] >= 80 &&
                $evalLow['tier'] === 'Low' &&
                $evalLow['score'] < 30);
    record_test('TEST 3', 'Rules-Based Severity Triage', $passSev, "Incident #2 Score: {$rec2['priority_score']}/100 ({$rec2['severity_tier']}) | Low Triage Score: {$evalLow['score']}/100 ({$evalLow['tier']})");
} catch (Exception $e) {
    record_test('TEST 3', 'Rules-Based Severity Triage', false, $e->getMessage());
}

// ---------------------------------------------------------------------
// TEST 4: Multiple Equipment Capabilities (7 types)
// ---------------------------------------------------------------------
try {
    $inc4 = $incidentModel->create(
        'Fire',
        'Factory',
        13.0650,
        80.2500,
        'Apex Chemical Plant Block C',
        'Chemical tank leak',
        ['fire_size' => 'Large', 'hazmat_involved' => 'Yes', 'people_trapped' => 1, 'critical_infrastructure' => 1],
        ['WaterPump', 'Ladder', 'BreathingApparatus', 'ProtectiveEquipment', 'HazmatKit'],
        'Hazardous fire emergency'
    );

    $rec4 = $incidentModel->getById($inc4);
    $equip = $rec4['equipment_array'] ?? [];
    $passEquip = (count($equip) === 5 && in_array('HazmatKit', $equip, true) && in_array('BreathingApparatus', $equip, true));
    record_test('TEST 4', 'Multiple Equipment Capabilities', $passEquip, "Incident #{$inc4} saved with " . count($equip) . " equipment requirements: [" . implode(', ', $equip) . "]");
} catch (Exception $e) {
    record_test('TEST 4', 'Multiple Equipment Capabilities', false, $e->getMessage());
}

// ---------------------------------------------------------------------
// TEST 5: Priority-Based Automatic Dispatch & Double Assignment Prevention
// ---------------------------------------------------------------------
try {
    // Run automated CAD priority dispatch
    $dispatchResult = $dispatchService->runAutoDispatch();
    $dispatchedUnits = [];
    $doubleAssigned = false;

    foreach ($dispatchResult['dispatched'] as $d) {
        $uId = $d['unit_id'];
        if (isset($dispatchedUnits[$uId])) {
            $doubleAssigned = true;
        }
        $dispatchedUnits[$uId] = true;
    }

    $passDisp = (!$doubleAssigned && count($dispatchResult['dispatched']) > 0);
    record_test('TEST 5', 'Auto Priority Dispatch & Locking', $passDisp, "Dispatched " . count($dispatchResult['dispatched']) . " unit(s) without double assignment. Assigned units: " . implode(', ', array_keys($dispatchedUnits)));
} catch (Exception $e) {
    record_test('TEST 5', 'Auto Priority Dispatch & Locking', false, $e->getMessage());
}

// ---------------------------------------------------------------------
// TEST 6: Dijkstra Shortest Route (Normal Path)
// ---------------------------------------------------------------------
try {
    $route1 = $routePlanner->findShortestRoute('StationA', 'Hospital');
    $passRoute1 = ($route1['success'] === true &&
                   $route1['distance'] === 7 &&
                   $route1['path'] === ['StationA', 'Road1', 'Hospital']);
    record_test('TEST 6', 'Dijkstra Normal Shortest Path', $passRoute1, "StationA ➔ Hospital: {$route1['path_string']} (Travel time: {$route1['distance']} mins, expected: 7)");
} catch (Exception $e) {
    record_test('TEST 6', 'Dijkstra Normal Shortest Path', false, $e->getMessage());
}

// ---------------------------------------------------------------------
// TEST 7: Road Closure & Alternate Dijkstra Recalculation
// ---------------------------------------------------------------------
try {
    // Find road_id for Road1 <-> Hospital and close it
    $roadStmt = $db->prepare("SELECT road_id FROM roads WHERE (from_location_id = 'Road1' AND to_location_id = 'Hospital') OR (from_location_id = 'Hospital' AND to_location_id = 'Road1')");
    $roadStmt->execute();
    $roadId = (int)$roadStmt->fetchColumn();

    $roadNetwork->updateRoadStatus($roadId, 'Closed');

    $routeClosed = $routePlanner->findShortestRoute('StationA', 'Hospital');
    $passClosure = ($routeClosed['success'] === true &&
                    $routeClosed['distance'] === 20 &&
                    !in_array('Road1', $routeClosed['path'], true));
    record_test('TEST 7', 'Dijkstra Alternate on Road Closure', $passClosure, "Closed Road1-Hospital. New Path: {$routeClosed['path_string']} ({$routeClosed['distance']} mins, avoids closed segment)");

    // Reopen road segment
    $roadNetwork->updateRoadStatus($roadId, 'Open');
    $routeReopened = $routePlanner->findShortestRoute('StationA', 'Hospital');
    $passReopen = ($routeReopened['success'] === true && $routeReopened['distance'] === 7);
    record_test('TEST 8', 'Dijkstra Road Reopening Restoration', $passReopen, "Reopened segment. Path restored: {$routeReopened['path_string']} ({$routeReopened['distance']} mins)");
} catch (Exception $e) {
    record_test('TEST 7', 'Dijkstra Road Closure & Reopening', false, $e->getMessage());
}

// ---------------------------------------------------------------------
// TEST 9: Unreachable Destination Detection
// ---------------------------------------------------------------------
try {
    // Isolate TechPark by closing all incoming roads: Factory-Road4, Road4-TechPark, Road4-StationB, RiverBridge-TechPark, Hospital-TechPark
    $db->exec("UPDATE roads SET status = 'Closed' WHERE from_location_id = 'TechPark' OR to_location_id = 'TechPark'");

    $routeBlocked = $routePlanner->findShortestRoute('StationA', 'TechPark');
    $passUnreachable = ($routeBlocked['success'] === false && $routeBlocked['distance'] === INF);
    record_test('TEST 9', 'Dijkstra Unreachable Detection', $passUnreachable, "Isolated TechPark. Result: success=" . ($routeBlocked['success'] ? 'true' : 'false') . ", Error: {$routeBlocked['error']}");

    // Restore roads
    $db->exec("UPDATE roads SET status = 'Open'");
} catch (Exception $e) {
    record_test('TEST 9', 'Dijkstra Unreachable Detection', false, $e->getMessage());
}

// ---------------------------------------------------------------------
// TEST 10: Email Dispatch Notification & Retry Mechanism
// ---------------------------------------------------------------------
try {
    $firstDisp = $db->query("SELECT dispatch_id FROM dispatch_records ORDER BY dispatch_id ASC LIMIT 1")->fetchColumn();
    if ($firstDisp) {
        $emailRes = $emailService->sendDispatchNotification((int)$firstDisp);
        $passEmail = ($emailRes['status'] === 'Sent');
        record_test('TEST 10', 'Email Dispatch Notification', $passEmail, "Dispatch #{$firstDisp} delivery status: {$emailRes['status']} ({$emailRes['note']})");

        // Test retry
        $retryRes = $emailService->retryNotification((int)$firstDisp);
        $passRetry = ($retryRes['status'] === 'Sent');
        record_test('TEST 11', 'Email Retry Mechanism', $passRetry, "Retry delivery status: {$retryRes['status']}");
    } else {
        record_test('TEST 10', 'Email Dispatch Notification', false, "No dispatch record available for email test");
    }
} catch (Exception $e) {
    record_test('TEST 10', 'Email Dispatch Notification', false, $e->getMessage());
}

// ---------------------------------------------------------------------
// TEST 12: Mission Completion, Unit Release & Queue Re-evaluation
// ---------------------------------------------------------------------
try {
    $activeDisp = $db->query("SELECT * FROM dispatch_records WHERE status = 'Active' LIMIT 1")->fetch();
    if ($activeDisp) {
        $dId = (int)$activeDisp['dispatch_id'];
        $uId = $activeDisp['unit_id'];
        $incId = (int)$activeDisp['incident_id'];

        $dispatchService->completeMission($dId, 'Captain John Ray', 'Extinguishment complete, area secured.');

        // Verify unit status is now Available
        $uCheck = $unitModel->getById($uId);
        $incCheck = $incidentModel->getById($incId);
        $dCheck = $db->query("SELECT * FROM dispatch_records WHERE dispatch_id = {$dId}")->fetch();

        $passComplete = ($uCheck['status'] === 'Available' &&
                         $incCheck['status'] === 'Completed' &&
                         $dCheck['status'] === 'Completed' &&
                         !empty($incCheck['completed_at']) &&
                         $incCheck['completed_by'] === 'Captain John Ray');

        record_test('TEST 12', 'Mission Completion & Unit Release', $passComplete, "Unit {$uId} released to {$uCheck['status']} | Incident #{$incId} status: {$incCheck['status']} | Officer: {$incCheck['completed_by']}");
    } else {
        record_test('TEST 12', 'Mission Completion & Unit Release', false, "No active mission found to complete.");
    }
} catch (Exception $e) {
    record_test('TEST 12', 'Mission Completion & Unit Release', false, $e->getMessage());
}

// ---------------------------------------------------------------------
// TEST 13: Authorized Severity Override Audit
// ---------------------------------------------------------------------
try {
    $incidentModel->overrideSeverity(1, 'Critical', 'Structure integrity compromised during response', 'Chief Smith');
    $checkOverride = $incidentModel->getById(1);
    $passOverride = ($checkOverride['override_severity'] === 'Critical' &&
                     $checkOverride['override_by'] === 'Chief Smith' &&
                     !empty($checkOverride['override_at']));
    record_test('TEST 13', 'Authorized Severity Override', $passOverride, "Incident #1 override tier: {$checkOverride['override_severity']} by {$checkOverride['override_by']} (Reason: {$checkOverride['override_reason']})");
} catch (Exception $e) {
    record_test('TEST 13', 'Authorized Severity Override', false, $e->getMessage());
}

// ---------------------------------------------------------------------
// TEST 14: Unified MySQL Database Consistency
// ---------------------------------------------------------------------
try {
    $stats = $incidentModel->getStatistics();
    $dbTotal = (int)$db->query("SELECT COUNT(*) FROM incidents")->fetchColumn();
    $dbPending = (int)$db->query("SELECT COUNT(*) FROM incidents WHERE status = 'Pending'")->fetchColumn();
    $dbCompleted = (int)$db->query("SELECT COUNT(*) FROM incidents WHERE status = 'Completed'")->fetchColumn();

    $passSync = ($stats['total'] === $dbTotal &&
                 $stats['pending'] === $dbPending &&
                 $stats['completed'] === $dbCompleted);
    record_test('TEST 14', 'MySQL Database Consistency', $passSync, "Model Stats: Total={$stats['total']}, Pending={$stats['pending']}, Completed={$stats['completed']} matches direct MySQL queries");
} catch (Exception $e) {
    record_test('TEST 14', 'MySQL Database Consistency', false, $e->getMessage());
}

// ---------------------------------------------------------------------
// Summary
// ---------------------------------------------------------------------
echo "\n=======================================================\n";
$totalTests = count($testResults);
$passedTests = count(array_filter($testResults, fn($t) => $t['pass']));
echo "TEST SUMMARY: {$passedTests} / {$totalTests} TESTS PASSED\n";
if ($passedTests === $totalTests) {
    echo "🎉 ALL VERIFICATION TESTS PASSED PERFECTLY!\n";
} else {
    echo "⚠️ ONE OR MORE VERIFICATION TESTS FAILED.\n";
}
echo "=======================================================\n";
