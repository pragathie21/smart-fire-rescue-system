<?php
/**
 * Smart Fire and Rescue Response System
 * Response Unit Model & Availability Operations
 */

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../config.php';

class ResponseUnit {
    private PDO $db;

    public function __construct() {
        $this->db = getDB();
    }

    /**
     * Retrieve all units with station details and equipment array
     */
    public function getAll(): array {
        $stmt = $this->db->query("
            SELECT u.*, s.station_name, s.location_id AS station_location_id,
                   GROUP_CONCAT(ue.equipment_code ORDER BY ue.equipment_code SEPARATOR ', ') AS equipment_list,
                   dr.dispatch_id AS active_dispatch_id, dr.incident_id AS active_incident_id
            FROM response_units u
            JOIN stations s ON u.station_id = s.station_id
            LEFT JOIN unit_equipment ue ON u.unit_id = ue.unit_id
            LEFT JOIN dispatch_records dr ON u.unit_id = dr.unit_id AND dr.status = 'Active'
            GROUP BY u.unit_id
            ORDER BY u.station_id ASC, u.unit_id ASC
        ");
        $units = $stmt->fetchAll();

        foreach ($units as &$unit) {
            $unit['equipment_array'] = !empty($unit['equipment_list'])
                ? array_map('trim', explode(',', $unit['equipment_list']))
                : [];
        }
        unset($unit);

        return $units;
    }

    /**
     * Retrieve single unit by ID
     */
    public function getById(string $unitId): ?array {
        $stmt = $this->db->prepare("
            SELECT u.*, s.station_name, s.location_id AS station_location_id,
                   GROUP_CONCAT(ue.equipment_code ORDER BY ue.equipment_code SEPARATOR ', ') AS equipment_list,
                   dr.dispatch_id AS active_dispatch_id, dr.incident_id AS active_incident_id
            FROM response_units u
            JOIN stations s ON u.station_id = s.station_id
            LEFT JOIN unit_equipment ue ON u.unit_id = ue.unit_id
            LEFT JOIN dispatch_records dr ON u.unit_id = dr.unit_id AND dr.status = 'Active'
            WHERE u.unit_id = :id
            GROUP BY u.unit_id
        ");
        $stmt->execute(['id' => $unitId]);
        $unit = $stmt->fetch();

        if ($unit) {
            $unit['equipment_array'] = !empty($unit['equipment_list'])
                ? array_map('trim', explode(',', $unit['equipment_list']))
                : [];
        }
        return $unit ?: null;
    }

    /**
     * Retrieve available response units
     */
    public function getAvailable(): array {
        $units = $this->getAll();
        return array_values(array_filter($units, fn($u) => $u['status'] === 'Available'));
    }

    /**
     * Check compatibility between response unit and incident type
     */
    public function isCompatibleWithType(string $unitType, string $incidentType): bool {
        if ($incidentType === 'Fire') {
            return ($unitType === 'Fire Engine');
        } elseif ($incidentType === 'Medical Emergency') {
            return ($unitType === 'Rescue Van');
        } elseif ($incidentType === 'Rescue') {
            // Both Fire Engines and Rescue Vans can handle Rescue missions if they have the equipment
            return true;
        }
        return true;
    }

    /**
     * Check if a unit possesses ALL required equipment items
     */
    public function hasAllEquipment(array $unitEquipment, array $requiredEquipment): bool {
        foreach ($requiredEquipment as $req) {
            if (!in_array($req, $unitEquipment, true)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Update unit status
     */
    public function updateStatus(string $unitId, string $status, ?int $incidentId = null): bool {
        $allowed = ['Available', 'Busy', 'Dispatched', 'Out of Service'];
        if (!in_array($status, $allowed, true)) {
            throw new InvalidArgumentException("Invalid response unit status: {$status}");
        }
        $stmt = $this->db->prepare("
            UPDATE response_units 
            SET status = :status, current_incident_id = :inc_id 
            WHERE unit_id = :id
        ");
        return $stmt->execute([
            'status' => $status,
            'inc_id' => $incidentId,
            'id'     => $unitId
        ]);
    }

    /**
     * Retrieve unit availability statistics from MySQL
     */
    public function getStatistics(): array {
        $stats = [
            'total'          => 0,
            'available'      => 0,
            'busy'           => 0,
            'out_of_service' => 0
        ];

        $stmt = $this->db->query("SELECT status, COUNT(*) AS cnt FROM response_units GROUP BY status");
        $rows = $stmt->fetchAll();

        foreach ($rows as $r) {
            $stats['total'] += (int)$r['cnt'];
            $st = $r['status'];
            if ($st === 'Available') {
                $stats['available'] += (int)$r['cnt'];
            } elseif ($st === 'Busy' || $st === 'Dispatched') {
                $stats['busy'] += (int)$r['cnt'];
            } elseif ($st === 'Out of Service') {
                $stats['out_of_service'] += (int)$r['cnt'];
            }
        }

        return $stats;
    }
}
