<?php
/**
 * Smart Fire and Rescue Response System
 * DispatchService - Intelligent Priority-Based Dispatch & Mission Coordination
 *
 * Implements automated CAD unit assignment based on priority ranking, equipment match,
 * Dijkstra shortest travel time, atomic MySQL transactions, email alerts,
 * and automatic queue re-evaluation upon mission completion.
 */

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/Incident.php';
require_once __DIR__ . '/ResponseUnit.php';
require_once __DIR__ . '/RoadNetwork.php';
require_once __DIR__ . '/RoutePlanner.php';
require_once __DIR__ . '/EmailNotificationService.php';

class DispatchService {
    private PDO $db;
    private Incident $incidentModel;
    private ResponseUnit $unitModel;
    private RoutePlanner $planner;
    private EmailNotificationService $emailService;

    public function __construct() {
        $this->db = getDB();
        $this->incidentModel = new Incident();
        $this->unitModel = new ResponseUnit();
        $this->planner = new RoutePlanner();
        $this->emailService = new EmailNotificationService();
    }

    /**
     * Automatic Priority-Based Dispatch Engine (Requirement 5)
     * Evaluates pending incidents in strict priority order, matches compatible
     * available response units, calculates fastest Dijkstra route, assigns unit,
     * updates statuses atomically, and transmits email notification.
     *
     * @param ?int $targetIncidentId If specified, prioritizes this incident
     * @return array Summary of dispatch results
     */
    public function runAutoDispatch(?int $targetIncidentId = null): array {
        $results = [
            'dispatched' => [],
            'pending'    => [],
            'count'      => 0
        ];

        // Fetch all pending incidents sorted by priority
        $pendingIncidents = $this->incidentModel->getPendingIncidents();

        if (empty($pendingIncidents)) {
            return $results;
        }

        // If a specific target incident is requested, place it first
        if ($targetIncidentId !== null) {
            usort($pendingIncidents, function ($a, $b) use ($targetIncidentId) {
                if ((int)$a['incident_id'] === $targetIncidentId) return -1;
                if ((int)$b['incident_id'] === $targetIncidentId) return 1;
                return 0;
            });
        }

        foreach ($pendingIncidents as $incident) {
            $incId = (int)$incident['incident_id'];
            $reqEquipment = $incident['equipment_array'] ?? [];
            $targetLoc = $incident['location_id'] ?: 'Road1';

            // Find all currently available response units
            $availableUnits = $this->unitModel->getAvailable();
            $eligibleCandidates = [];

            foreach ($availableUnits as $unit) {
                // 1. Type compatibility check
                if (!$this->unitModel->isCompatibleWithType($unit['unit_type'], $incident['incident_type'])) {
                    continue;
                }

                // 2. Equipment inventory check (unit must possess ALL required equipment)
                if (!$this->unitModel->hasAllEquipment($unit['equipment_array'], $reqEquipment)) {
                    continue;
                }

                // 3. Dijkstra routing check (must have an open road path)
                $route = $this->planner->findShortestRoute($unit['station_location_id'], $targetLoc);
                if (!$route['success']) {
                    continue;
                }

                $eligibleCandidates[] = [
                    'unit'        => $unit,
                    'travel_time' => $route['distance'],
                    'route'       => $route['path_string']
                ];
            }

            // If at least one compatible unit with open route is available
            if (!empty($eligibleCandidates)) {
                // Select unit with shortest estimated travel time
                usort($eligibleCandidates, fn($a, $b) => $a['travel_time'] <=> $b['travel_time']);
                $bestMatch = $eligibleCandidates[0];
                $selectedUnit = $bestMatch['unit'];

                try {
                    $dispatchId = $this->assignUnitToIncident(
                        $incId,
                        $selectedUnit['unit_id'],
                        $bestMatch['route'],
                        $bestMatch['travel_time'],
                        "Automated CAD priority assignment."
                    );

                    // Send email notification (Requirement 6)
                    $emailResult = $this->emailService->sendDispatchNotification($dispatchId);

                    $results['dispatched'][] = [
                        'incident_id'  => $incId,
                        'unit_id'      => $selectedUnit['unit_id'],
                        'unit_type'    => $selectedUnit['unit_type'],
                        'station_id'   => $selectedUnit['station_id'],
                        'station_name' => $selectedUnit['station_name'],
                        'travel_time'  => $bestMatch['travel_time'],
                        'route'        => $bestMatch['route'],
                        'dispatch_id'  => $dispatchId,
                        'email_result' => $emailResult
                    ];
                    $results['count']++;
                } catch (Exception $e) {
                    // If unit was locked by concurrent worker, continue to next
                    continue;
                }
            } else {
                // Determine reason why incident could not be dispatched immediately
                $allUnits = $this->unitModel->getAll();
                $busyCount = 0;
                $lackEquipCount = 0;
                $noRouteCount = 0;

                foreach ($allUnits as $u) {
                    if ($u['status'] !== 'Available') {
                        $busyCount++;
                    } elseif (!$this->unitModel->hasAllEquipment($u['equipment_array'], $reqEquipment)) {
                        $lackEquipCount++;
                    } else {
                        $r = $this->planner->findShortestRoute($u['station_location_id'], $targetLoc);
                        if (!$r['success']) {
                            $noRouteCount++;
                        }
                    }
                }

                $reason = "Awaiting Resources: ";
                $reasonsArr = [];
                if ($busyCount > 0) $reasonsArr[] = "{$busyCount} unit(s) currently busy on active missions";
                if ($lackEquipCount > 0) $reasonsArr[] = "available units lack required equipment [" . implode(', ', $reqEquipment) . "]";
                if ($noRouteCount > 0) $reasonsArr[] = "open road path to {$targetLoc} is currently blocked by closures";
                $reason .= !empty($reasonsArr) ? implode('; ', $reasonsArr) : "No compatible unit found";

                $results['pending'][] = [
                    'incident_id'    => $incId,
                    'priority_score' => $incident['priority_score'],
                    'severity_tier'  => $incident['severity_tier'],
                    'reason'         => $reason
                ];
            }
        }

        return $results;
    }

    /**
     * Atomically assign a response unit to an incident using MySQL transactions and row locking.
     */
    private function assignUnitToIncident(
        int $incidentId,
        string $unitId,
        string $routeString,
        int $travelTime,
        string $notes = ''
    ): int {
        $this->db->beginTransaction();
        try {
            // 1. Lock and verify incident status
            $incStmt = $this->db->prepare("SELECT * FROM incidents WHERE incident_id = :id FOR UPDATE");
            $incStmt->execute(['id' => $incidentId]);
            $incident = $incStmt->fetch();

            if (!$incident) {
                throw new RuntimeException("Incident #{$incidentId} not found.");
            }
            if ($incident['status'] !== 'Pending') {
                throw new RuntimeException("Incident #{$incidentId} is no longer pending (current: {$incident['status']}).");
            }

            // 2. Lock and verify unit availability
            $unitStmt = $this->db->prepare("SELECT * FROM response_units WHERE unit_id = :id FOR UPDATE");
            $unitStmt->execute(['id' => $unitId]);
            $unit = $unitStmt->fetch();

            if (!$unit) {
                throw new RuntimeException("Response Unit {$unitId} not found.");
            }
            if ($unit['status'] !== 'Available') {
                throw new RuntimeException("Unit {$unitId} is not available (current: {$unit['status']}).");
            }

            // 3. Update response unit to Busy
            $upUnit = $this->db->prepare("
                UPDATE response_units 
                SET status = 'Busy', current_incident_id = :inc_id 
                WHERE unit_id = :unit_id
            ");
            $upUnit->execute([
                'inc_id'  => $incidentId,
                'unit_id' => $unitId
            ]);

            // 4. Update incident status to Dispatched
            $upInc = $this->db->prepare("
                UPDATE incidents 
                SET status = 'Dispatched' 
                WHERE incident_id = :id
            ");
            $upInc->execute(['id' => $incidentId]);

            // 5. Insert dispatch record
            $dispStmt = $this->db->prepare("
                INSERT INTO dispatch_records (
                    incident_id, unit_id, route, estimated_travel_time, 
                    dispatched_at, status, notes, email_status
                ) VALUES (
                    :incident_id, :unit_id, :route, :travel_time, 
                    NOW(), 'Active', :notes, 'Pending'
                )
            ");
            $dispStmt->execute([
                'incident_id' => $incidentId,
                'unit_id'     => $unitId,
                'route'       => $routeString,
                'travel_time' => $travelTime,
                'notes'       => trim($notes)
            ]);

            $dispatchId = (int)$this->db->lastInsertId();

            $this->db->commit();
            return $dispatchId;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Complete an active emergency mission (Requirement 8)
     * Marks dispatch and incident as Completed, releases unit to Available,
     * and IMMEDIATELY re-runs dispatch for any pending incidents in the queue.
     */
    public function completeMission(int $dispatchId, string $officer = '', string $notes = ''): bool {
        $this->db->beginTransaction();
        try {
            // Find active dispatch record
            $stmt = $this->db->prepare("SELECT * FROM dispatch_records WHERE dispatch_id = :id FOR UPDATE");
            $stmt->execute(['id' => $dispatchId]);
            $record = $stmt->fetch();

            if (!$record) {
                throw new RuntimeException("Dispatch record #{$dispatchId} not found.");
            }
            if ($record['status'] !== 'Active') {
                throw new RuntimeException("Mission is already marked as {$record['status']}.");
            }

            $incidentId = (int)$record['incident_id'];
            $unitId     = $record['unit_id'];

            // 1. Mark dispatch record as Completed
            $appendNote = trim($notes) !== '' ? ' | Note: ' . trim($notes) : '';
            $upDisp = $this->db->prepare("
                UPDATE dispatch_records 
                SET status = 'Completed', completed_at = NOW(),
                    notes = CONCAT(IFNULL(notes, ''), :append_note)
                WHERE dispatch_id = :id
            ");
            $upDisp->execute(['id' => $dispatchId, 'append_note' => $appendNote]);

            // 2. Mark response unit as Available and clear assigned incident
            $upUnit = $this->db->prepare("
                UPDATE response_units 
                SET status = 'Available', current_incident_id = NULL 
                WHERE unit_id = :id
            ");
            $upUnit->execute(['id' => $unitId]);

            // 3. Mark incident as Completed and record responsible officer
            $upInc = $this->db->prepare("
                UPDATE incidents 
                SET status = 'Completed', completed_at = NOW(),
                    completed_by = :officer, completion_notes = :notes
                WHERE incident_id = :id
            ");
            $upInc->execute([
                'id'      => $incidentId,
                'officer' => trim($officer ?: 'Duty Officer'),
                'notes'   => trim($notes)
            ]);

            $this->db->commit();

            // 4. CRITICAL: Re-run dispatch for any waiting incidents in priority order!
            // Newly freed unit may immediately service the highest priority pending incident
            $this->runAutoDispatch();

            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Mark an incident as In Progress by response officer
     */
    public function markInProgress(int $incidentId, string $officer = ''): bool {
        return $this->incidentModel->markInProgress($incidentId, $officer);
    }

    /**
     * Explicit manual dispatch by authorized officer
     */
    public function dispatch(int $incidentId, string $unitId, string $notes = ''): int {
        $incident = $this->incidentModel->getById($incidentId);
        if (!$incident) throw new RuntimeException("Incident #{$incidentId} not found.");

        $unit = $this->unitModel->getById($unitId);
        if (!$unit) throw new RuntimeException("Unit {$unitId} not found.");

        $route = $this->planner->findShortestRoute($unit['station_location_id'], $incident['location_id'] ?: 'Road1');
        if (!$route['success']) {
            throw new RuntimeException("Cannot dispatch: " . $route['error']);
        }

        $dispatchId = $this->assignUnitToIncident($incidentId, $unitId, $route['path_string'], $route['distance'], $notes);
        $this->emailService->sendDispatchNotification($dispatchId);
        return $dispatchId;
    }

    /**
     * Evaluate all response units for an incident (for UI inspection)
     */
    public function getUnitAssessmentsForIncident(int $incidentId): array {
        $incident = $this->incidentModel->getById($incidentId);
        if (!$incident) {
            throw new InvalidArgumentException("Incident not found: #{$incidentId}");
        }

        $requiredEquipment = $incident['equipment_array'] ?? [];
        $targetLocation = $incident['location_id'] ?: 'Road1';
        $units = $this->unitModel->getAll();

        $eligibleUnits = [];
        $ineligibleUnits = [];

        foreach ($units as $unit) {
            $stationLocation = $unit['station_location_id'];
            $unitEquipment   = $unit['equipment_array'];
            $isAvailable     = ($unit['status'] === 'Available');
            $typeMatch       = $this->unitModel->isCompatibleWithType($unit['unit_type'], $incident['incident_type']);

            // Equipment compatibility
            $missingEquip = [];
            foreach ($requiredEquipment as $req) {
                if (!in_array($req, $unitEquipment, true)) {
                    $missingEquip[] = $req;
                }
            }
            $hasAllEquipment = empty($missingEquip);

            // Dijkstra route
            $routeResult = $this->planner->findShortestRoute($stationLocation, $targetLocation);

            $assessment = [
                'unit'             => $unit,
                'has_equipment'    => $hasAllEquipment,
                'missing_equip'    => $missingEquip,
                'is_available'     => $isAvailable,
                'type_match'       => $typeMatch,
                'route_success'    => $routeResult['success'],
                'travel_time'      => $routeResult['distance'],
                'route_string'     => $routeResult['path_string'],
                'route_path'       => $routeResult['path'],
                'route_segments'   => $routeResult['segments'],
                'route_error'      => $routeResult['error'],
                'is_eligible'      => false,
                'rejection_reason' => ''
            ];

            if (!$isAvailable) {
                $assessment['rejection_reason'] = "Unit is '{$unit['status']}' (not available for new dispatch).";
                $ineligibleUnits[] = $assessment;
            } elseif (!$typeMatch) {
                $assessment['rejection_reason'] = "Unit type ({$unit['unit_type']}) is incompatible with {$incident['incident_type']}.";
                $ineligibleUnits[] = $assessment;
            } elseif (!$hasAllEquipment) {
                $assessment['rejection_reason'] = "Lacks required equipment: " . implode(', ', $missingEquip) . ".";
                $ineligibleUnits[] = $assessment;
            } elseif (!$routeResult['success']) {
                $assessment['rejection_reason'] = "No open road path exists between {$stationLocation} and {$targetLocation}.";
                $ineligibleUnits[] = $assessment;
            } else {
                $assessment['is_eligible'] = true;
                $eligibleUnits[] = $assessment;
            }
        }

        // Sort eligible units by lowest estimated travel time
        usort($eligibleUnits, fn($a, $b) => $a['travel_time'] <=> $b['travel_time']);

        return [
            'incident'   => $incident,
            'eligible'   => $eligibleUnits,
            'ineligible' => $ineligibleUnits
        ];
    }

    /**
     * Retrieve all dispatch records with related incident and unit details
     */
    public function getAllDispatchRecords(?string $statusFilter = null): array {
        $query = "
            SELECT d.*, 
                   i.incident_type, i.location_id, i.priority_score, i.severity_tier, i.critical_infrastructure,
                   l.location_name,
                   u.unit_type, u.station_id,
                   s.station_name
            FROM dispatch_records d
            JOIN incidents i ON d.incident_id = i.incident_id
            LEFT JOIN locations l ON i.location_id = l.location_id
            JOIN response_units u ON d.unit_id = u.unit_id
            JOIN stations s ON u.station_id = s.station_id
            WHERE 1=1
        ";
        $params = [];

        if (!empty($statusFilter) && $statusFilter !== 'all') {
            $query .= " AND d.status = :status";
            $params['status'] = $statusFilter;
        }

        $query .= " ORDER BY d.dispatch_id DESC";

        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Retrieve total active missions count
     */
    public function getActiveMissionsCount(): int {
        $stmt = $this->db->query("SELECT COUNT(*) FROM dispatch_records WHERE status = 'Active'");
        return (int)$stmt->fetchColumn();
    }
}
