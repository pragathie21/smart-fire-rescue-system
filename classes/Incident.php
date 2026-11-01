<?php
/**
 * Smart Fire and Rescue Response System
 * Incident Model & Operations
 *
 * Implements AUTO_INCREMENT sequential IDs (1, 2, 3...), GPS geolocation,
 * rules-based triage scoring, equipment requirements, and lifecycle state transitions.
 */

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/SeverityScorer.php';

class Incident {
    private PDO $db;

    public function __construct() {
        $this->db = getDB();
    }

    /**
     * Create a new incident report with database AUTO_INCREMENT ID in a transaction.
     *
     * @param string $type Incident Type ('Fire', 'Rescue', 'Medical Emergency')
     * @param ?string $locationId Nearest Sudar road network node / sector
     * @param ?float $latitude Real GPS latitude
     * @param ?float $longitude Real GPS longitude
     * @param ?string $locationAddress Resolved street address or custom address
     * @param ?string $locationDescription Landmarks and directions
     * @param array $assessment Assessment questionnaire answers
     * @param array $equipment Selected required equipment codes
     * @param string $notes Optional caller description
     * @return int Database-generated sequential Incident ID (1, 2, 3...)
     */
    public function create(
        string $type,
        ?string $locationId,
        ?float $latitude,
        ?float $longitude,
        ?string $locationAddress,
        ?string $locationDescription,
        array $assessment,
        array $equipment,
        string $notes = ''
    ): int {
        // 1. Validate incident type
        if (!in_array($type, ALLOWED_INCIDENT_TYPES, true)) {
            throw new InvalidArgumentException("Invalid incident type specified: {$type}");
        }

        // 2. Validate location (either a valid road network node or GPS coords or manual address)
        if (!empty($locationId)) {
            $locStmt = $this->db->prepare("SELECT location_id, latitude, longitude, location_name FROM locations WHERE location_id = ?");
            $locStmt->execute([$locationId]);
            $locRow = $locStmt->fetch();
            if (!$locRow) {
                throw new InvalidArgumentException("Selected road node '{$locationId}' does not exist.");
            }
            if ($latitude === null && !empty($locRow['latitude'])) {
                $latitude = (float)$locRow['latitude'];
            }
            if ($longitude === null && !empty($locRow['longitude'])) {
                $longitude = (float)$locRow['longitude'];
            }
            if (empty($locationAddress) && !empty($locRow['location_name'])) {
                $locationAddress = $locRow['location_name'];
            }
        } else {
            // If locationId not selected, default to nearest node or Hospital
            $locationId = 'Hospital';
            $locStmt = $this->db->prepare("SELECT latitude, longitude, location_name FROM locations WHERE location_id = ?");
            $locStmt->execute([$locationId]);
            $locRow = $locStmt->fetch();
            if ($locRow) {
                if ($latitude === null) $latitude = (float)$locRow['latitude'];
                if ($longitude === null) $longitude = (float)$locRow['longitude'];
                if (empty($locationAddress)) $locationAddress = $locRow['location_name'];
            }
        }

        // 3. Validate coordinates if provided
        if ($latitude !== null && ($latitude < -90 || $latitude > 90)) {
            throw new InvalidArgumentException("Latitude must be between -90 and 90 degrees.");
        }
        if ($longitude !== null && ($longitude < -180 || $longitude > 180)) {
            throw new InvalidArgumentException("Longitude must be between -180 and 180 degrees.");
        }

        // 4. Validate equipment
        $cleanEquip = [];
        foreach ($equipment as $eq) {
            $eqCode = trim($eq);
            if (in_array($eqCode, ALLOWED_EQUIPMENT, true)) {
                $cleanEquip[] = $eqCode;
            }
        }
        $cleanEquip = array_unique($cleanEquip);
        if (empty($cleanEquip)) {
            throw new InvalidArgumentException("At least one valid required equipment capability must be selected.");
        }

        // 5. Evaluate rules-based severity and priority score
        $assessment['incident_type'] = $type;
        $eval = SeverityScorer::evaluate($assessment);

        $reportedAt = date('Y-m-d H:i:s');

        // Extract normalized assessment fields
        $fireSize        = ($type === 'Fire') ? ($assessment['fire_size'] ?? 'Small') : null;
        $fireSpreading   = ($type === 'Fire') ? (!empty($assessment['fire_spreading']) ? 1 : 0) : 0;
        $peopleTrapped   = !empty($assessment['people_trapped']) ? 1 : 0;
        $injuriesReported= !empty($assessment['injuries_reported']) ? 1 : 0;
        $heavySmoke      = ($type === 'Fire') ? (!empty($assessment['heavy_smoke']) ? 1 : 0) : 0;
        $buildingType    = trim($assessment['building_type'] ?? 'Residential');
        $hazmat          = in_array($assessment['hazmat_involved'] ?? '', ['Yes', 'No', 'Unknown']) ? $assessment['hazmat_involved'] : 'No';
        $peopleExposed   = max(0, (int)($assessment['people_exposed'] ?? 0));
        $criticalInfra   = !empty($assessment['critical_infrastructure']) ? 1 : 0;

        // 6. Begin transaction to insert incident and equipment requirements
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("
                INSERT INTO incidents (
                    incident_type, location_id, latitude, longitude,
                    location_address, location_description,
                    fire_size, fire_spreading, people_trapped, injuries_reported,
                    heavy_smoke, building_type, hazmat_involved, people_exposed,
                    critical_infrastructure, description,
                    priority_score, severity_tier, assessment_reason,
                    status, reported_at
                ) VALUES (
                    :type, :loc, :lat, :lng,
                    :addr, :loc_desc,
                    :f_size, :f_spread, :trapped, :injuries,
                    :smoke, :bldg, :hazmat, :exposed,
                    :critical, :desc,
                    :score, :tier, :reason,
                    'Pending', :reported_at
                )
            ");

            $stmt->execute([
                'type'        => $type,
                'loc'         => $locationId,
                'lat'         => $latitude,
                'lng'         => $longitude,
                'addr'        => $locationAddress,
                'loc_desc'    => $locationDescription,
                'f_size'      => $fireSize,
                'f_spread'    => $fireSpreading,
                'trapped'     => $peopleTrapped,
                'injuries'    => $injuriesReported,
                'smoke'       => $heavySmoke,
                'bldg'        => $buildingType,
                'hazmat'      => $hazmat,
                'exposed'     => $peopleExposed,
                'critical'    => $criticalInfra,
                'desc'        => trim($notes),
                'score'       => $eval['score'],
                'tier'        => $eval['tier'],
                'reason'      => $eval['reason_text'],
                'reported_at' => $reportedAt
            ]);

            // Database generates sequential primary key (Requirement 1)
            $incidentId = (int)$this->db->lastInsertId();

            // Insert required equipment rows
            $eqInsert = $this->db->prepare("
                INSERT INTO incident_equipment (incident_id, equipment_code) 
                VALUES (:inc_id, :code)
            ");
            foreach ($cleanEquip as $eqCode) {
                $eqInsert->execute([
                    'inc_id' => $incidentId,
                    'code'   => $eqCode
                ]);
            }

            $this->db->commit();
            return $incidentId;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Retrieve a single incident by its sequential integer ID
     */
    public function getById(int $incidentId): ?array {
        $stmt = $this->db->prepare("
            SELECT i.*, 
                   l.location_name, l.location_type,
                   l.latitude AS default_lat, l.longitude AS default_lng,
                   GROUP_CONCAT(ie.equipment_code SEPARATOR ', ') AS equipment_list
            FROM incidents i
            LEFT JOIN locations l ON i.location_id = l.location_id
            LEFT JOIN incident_equipment ie ON i.incident_id = ie.incident_id
            WHERE i.incident_id = :id
            GROUP BY i.incident_id
        ");
        $stmt->execute(['id' => $incidentId]);
        $row = $stmt->fetch();

        if (!$row) {
            return null;
        }

        $row['equipment_array'] = !empty($row['equipment_list'])
            ? array_map('trim', explode(',', $row['equipment_list']))
            : [];

        // Effective coordinates: custom GPS coordinates if present, else road network location coordinates
        $row['effective_lat'] = $row['latitude'] ?? $row['default_lat'] ?? SUDAR_DEFAULT_LAT;
        $row['effective_lng'] = $row['longitude'] ?? $row['default_lng'] ?? SUDAR_DEFAULT_LNG;

        // Fetch latest dispatch record if assigned
        $dispStmt = $this->db->prepare("
            SELECT d.*, 
                   u.unit_type, u.station_id,
                   s.station_name, s.contact_email
            FROM dispatch_records d
            JOIN response_units u ON d.unit_id = u.unit_id
            JOIN stations s ON u.station_id = s.station_id
            WHERE d.incident_id = :id
            ORDER BY d.dispatch_id DESC
            LIMIT 1
        ");
        $dispStmt->execute(['id' => $incidentId]);
        $row['dispatch'] = $dispStmt->fetch() ?: null;

        return $row;
    }

    /**
     * Retrieve all incidents ordered by priority ranking
     */
    public function getAll(?string $statusFilter = null, ?string $search = null): array {
        $query = "
            SELECT i.*, 
                   l.location_name, l.location_type,
                   GROUP_CONCAT(ie.equipment_code SEPARATOR ', ') AS equipment_list,
                   MAX(d.dispatch_id) AS dispatch_id,
                   MAX(d.unit_id) AS assigned_unit_id,
                   MAX(d.status) AS dispatch_status,
                   MAX(d.estimated_travel_time) AS estimated_travel_time,
                   MAX(d.route) AS dijkstra_route,
                   MAX(d.email_status) AS email_status,
                   MAX(u.station_id) AS assigned_station_id,
                   MAX(s.station_name) AS assigned_station_name
            FROM incidents i
            LEFT JOIN locations l ON i.location_id = l.location_id
            LEFT JOIN incident_equipment ie ON i.incident_id = ie.incident_id
            LEFT JOIN (
                SELECT d1.* FROM dispatch_records d1
                INNER JOIN (
                    SELECT incident_id, MAX(dispatch_id) AS max_id 
                    FROM dispatch_records 
                    GROUP BY incident_id
                ) d2 ON d1.dispatch_id = d2.max_id
            ) d ON i.incident_id = d.incident_id
            LEFT JOIN response_units u ON d.unit_id = u.unit_id
            LEFT JOIN stations s ON u.station_id = s.station_id
            WHERE 1=1
        ";
        $params = [];

        if (!empty($statusFilter) && $statusFilter !== 'all') {
            $query .= " AND i.status = :status";
            $params['status'] = $statusFilter;
        }

        if (!empty($search)) {
            $query .= " AND (i.incident_id = :search_id OR i.incident_type LIKE :search_like OR l.location_name LIKE :search_like OR i.location_address LIKE :search_like)";
            $params['search_id'] = is_numeric($search) ? (int)$search : 0;
            $params['search_like'] = '%' . $search . '%';
        }

        $query .= " GROUP BY i.incident_id";

        // Priority Precedence Order:
        // 1. Critical Infrastructure = 1 first
        // 2. Highest priority_score DESC
        // 3. Earlier reported_at ASC
        $query .= " ORDER BY i.critical_infrastructure DESC, i.priority_score DESC, i.reported_at ASC";

        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        foreach ($rows as &$r) {
            $r['equipment_array'] = !empty($r['equipment_list'])
                ? array_map('trim', explode(',', $r['equipment_list']))
                : [];
        }

        return $rows;
    }

    /**
     * Retrieve all pending incidents awaiting dispatch
     */
    public function getPendingIncidents(): array {
        return $this->getAll('Pending');
    }

    /**
     * Fetch live incident statistics
     */
    public function getStatistics(): array {
        $stats = [
            'total'      => 0,
            'pending'    => 0,
            'assigned'   => 0,
            'in_progress'=> 0,
            'completed'  => 0,
            'critical'   => 0,
            'high'       => 0
        ];

        $stmt = $this->db->query("
            SELECT status, severity_tier, COUNT(*) AS cnt 
            FROM incidents 
            GROUP BY status, severity_tier
        ");
        $rows = $stmt->fetchAll();

        foreach ($rows as $r) {
            $stats['total'] += (int)$r['cnt'];
            $st = strtolower($r['status']);
            if ($st === 'pending') {
                $stats['pending'] += (int)$r['cnt'];
            } elseif ($st === 'assigned' || $st === 'dispatched') {
                $stats['assigned'] += (int)$r['cnt'];
            } elseif ($st === 'in progress') {
                $stats['in_progress'] += (int)$r['cnt'];
            } elseif ($st === 'completed') {
                $stats['completed'] += (int)$r['cnt'];
            }

            $tier = strtolower($r['severity_tier']);
            if ($tier === 'critical') {
                $stats['critical'] += (int)$r['cnt'];
            } elseif ($tier === 'high') {
                $stats['high'] += (int)$r['cnt'];
            }
        }

        return $stats;
    }

    /**
     * Authorized override of rules-based severity assessment (Requirement 3)
     */
    public function overrideSeverity(int $incidentId, string $newSeverity, string $reason, string $officer): bool {
        $validTiers = ['Low', 'Medium', 'High', 'Critical'];
        if (!in_array($newSeverity, $validTiers, true)) {
            throw new InvalidArgumentException("Invalid severity tier: {$newSeverity}");
        }
        if (trim($reason) === '') {
            throw new InvalidArgumentException("Operational justification reason is required for assessment correction.");
        }

        $stmt = $this->db->prepare("
            UPDATE incidents 
            SET override_severity = :tier,
                override_reason   = :reason,
                override_by       = :officer,
                override_at       = NOW()
            WHERE incident_id = :id
        ");
        return $stmt->execute([
            'tier'   => $newSeverity,
            'reason' => trim($reason),
            'officer'=> trim($officer),
            'id'     => $incidentId
        ]);
    }

    /**
     * Mark an incident as In Progress
     */
    public function markInProgress(int $incidentId, string $officer = ''): bool {
        $stmt = $this->db->prepare("
            UPDATE incidents 
            SET status = 'In Progress', 
                in_progress_at = NOW()
            WHERE incident_id = :id AND status IN ('Assigned', 'Dispatched')
        ");
        return $stmt->execute(['id' => $incidentId]);
    }
}
