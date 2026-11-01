<?php
/**
 * Smart Fire and Rescue Response System
 * Unified JSON API Gateway
 *
 * Powers both AJAX frontend clients (script.js / HTML pages)
 * and mobile/remote CAD terminals directly against the MySQL database.
 */

header('Content-Type: application/json; charset=utf-8');

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

$action = $_GET['action'] ?? $_POST['action'] ?? '';

$incidentModel   = new Incident();
$unitModel       = new ResponseUnit();
$roadNetwork     = new RoadNetwork();
$dispatchService = new DispatchService();

try {
    switch ($action) {
        case 'stats':
            $incStats  = $incidentModel->getStatistics();
            $unitStats = $unitModel->getStatistics();
            $activeCnt = $dispatchService->getActiveMissionsCount();
            $roadStats = $roadNetwork->getStatistics();

            echo json_encode([
                'success'   => true,
                'incidents' => $incStats,
                'units'     => $unitStats,
                'missions'  => ['active' => $activeCnt],
                'roads'     => $roadStats
            ]);
            break;

        case 'incidents':
            $filter = $_GET['status'] ?? null;
            $list = $incidentModel->getAll($filter);
            echo json_encode([
                'success' => true,
                'data'    => $list
            ]);
            break;

        case 'incident_details':
            $id = (int)($_GET['id'] ?? 0);
            $inc = $incidentModel->getById($id);
            if (!$inc) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => "Incident #{$id} not found."]);
                exit;
            }
            echo json_encode(['success' => true, 'data' => $inc]);
            break;

        case 'units':
            $units = $unitModel->getAll();
            echo json_encode([
                'success' => true,
                'data'    => $units
            ]);
            break;

        case 'unit_status':
            $unitId = trim($_GET['unit_id'] ?? '');
            $u = $unitModel->getById($unitId);
            if (!$u) {
                echo json_encode(['success' => false, 'error' => 'Unit not found']);
                exit;
            }
            $db = getDB();
            $stmt = $db->prepare("
                SELECT d.*, 
                       i.incident_id, i.incident_type, i.location_id, i.location_address,
                       i.latitude, i.longitude, i.priority_score, i.severity_tier,
                       i.people_trapped, i.injuries_reported, i.hazmat_involved, i.description AS caller_notes,
                       l.location_name
                FROM dispatch_records d
                JOIN incidents i ON d.incident_id = i.incident_id
                LEFT JOIN locations l ON i.location_id = l.location_id
                WHERE d.unit_id = :unit AND d.status = 'Active'
                ORDER BY d.dispatch_id DESC
                LIMIT 1
            ");
            $stmt->execute(['unit' => $unitId]);
            $activeDispatch = $stmt->fetch(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'unit'    => $u,
                'mission' => $activeDispatch ?: null
            ]);
            break;

        case 'dispatch_history':
        case 'history':
            $filter = $_GET['status'] ?? null;
            $history = $dispatchService->getAllDispatchRecords($filter);
            echo json_encode([
                'success' => true,
                'data'    => $history
            ]);
            break;

        case 'roads':
            $roads = $roadNetwork->getAllRoads();
            echo json_encode([
                'success' => true,
                'data'    => $roads
            ]);
            break;

        case 'locations':
            $locs = $roadNetwork->getLocations();
            echo json_encode([
                'success' => true,
                'data'    => $locs
            ]);
            break;

        case 'create_incident':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new BadMethodCallException("POST required for incident creation.");
            }

            // Read JSON or POST body
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) {
                $input = $_POST;
            }

            $type        = trim($input['incident_type'] ?? $input['type'] ?? 'Fire');
            $locationId  = trim($input['location_id'] ?? $input['location'] ?? 'Hospital');
            $lat         = isset($input['latitude']) && is_numeric($input['latitude']) ? (float)$input['latitude'] : null;
            $lng         = isset($input['longitude']) && is_numeric($input['longitude']) ? (float)$input['longitude'] : null;
            $address     = trim($input['location_address'] ?? $input['address'] ?? '');
            $locDesc     = trim($input['location_description'] ?? '');
            $notes       = trim($input['description'] ?? '');
            $equipment   = $input['required_equipment'] ?? $input['equipment'] ?? [];

            if (is_string($equipment)) {
                $equipment = [$equipment];
            }

            $assessment = [
                'fire_size'               => $input['fire_size'] ?? 'Small',
                'fire_spreading'          => !empty($input['fire_spreading']) ? 1 : 0,
                'people_trapped'          => !empty($input['people_trapped']) ? 1 : 0,
                'injuries_reported'       => !empty($input['injuries_reported']) ? 1 : 0,
                'heavy_smoke'             => !empty($input['heavy_smoke']) ? 1 : 0,
                'building_type'           => $input['building_type'] ?? 'Residential',
                'hazmat_involved'         => $input['hazmat_involved'] ?? 'No',
                'people_exposed'          => max(0, (int)($input['people_exposed'] ?? 0)),
                'critical_infrastructure' => !empty($input['critical_infrastructure']) || !empty($input['critical']) ? 1 : 0,
                'description'             => $notes
            ];

            // Insert into MySQL with sequential ID
            $newId = $incidentModel->create(
                $type,
                $locationId,
                $lat,
                $lng,
                $address,
                $locDesc,
                $assessment,
                $equipment,
                $notes
            );

            // Trigger automated priority dispatch
            $dispatchOutcome = $dispatchService->runAutoDispatch($newId);
            $freshIncident = $incidentModel->getById($newId);

            echo json_encode([
                'success'     => true,
                'incident_id' => $newId,
                'incident'    => $freshIncident,
                'dispatch'    => $dispatchOutcome
            ]);
            break;

        case 'complete_mission':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new BadMethodCallException("POST required.");
            }
            $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $dispatchId = (int)($input['dispatch_id'] ?? 0);
            $notes      = trim($input['notes'] ?? $input['completion_notes'] ?? '');
            $officer    = $_SESSION['user']['full_name'] ?? 'Authorized Officer';

            if ($dispatchId <= 0) {
                throw new InvalidArgumentException("Valid dispatch_id is required.");
            }

            $dispatchService->completeMission($dispatchId, $officer, $notes);
            echo json_encode([
                'success' => true,
                'message' => "Mission #{$dispatchId} completed and unit released."
            ]);
            break;

        case 'calculate_route':
            $start = trim($_GET['start'] ?? 'StationA');
            $dest  = trim($_GET['destination'] ?? 'Hospital');
            $planner = new RoutePlanner();
            $route = $planner->findShortestRoute($start, $dest);
            echo json_encode([
                'success' => $route['success'],
                'data'    => $route
            ]);
            break;

        case 'update_road_status':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new BadMethodCallException("POST required.");
            }
            $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $roadId = (int)($input['road_id'] ?? 0);
            $status = trim($input['status'] ?? 'Open');
            if ($roadId <= 0 || !in_array($status, ['Open', 'Closed'], true)) {
                throw new InvalidArgumentException("Valid road_id and status ('Open'|'Closed') required.");
            }
            $roadNetwork->updateRoadStatus($roadId, $status);
            echo json_encode([
                'success' => true,
                'message' => "Road #{$roadId} status changed to {$status}."
            ]);
            break;

        case 'auto_dispatch':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new BadMethodCallException("POST required.");
            }
            $outcome = $dispatchService->runAutoDispatch();
            echo json_encode([
                'success' => true,
                'data'    => $outcome
            ]);
            break;

        default:
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error'   => "Unknown API action: {$action}"
            ]);
            break;
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage()
    ]);
}
