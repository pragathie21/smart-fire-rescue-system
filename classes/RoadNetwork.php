<?php
/**
 * Smart Fire and Rescue Response System
 * Road Network Model & Graph Representation
 */

require_once __DIR__ . '/../db.php';

class RoadNetwork {
    private PDO $db;

    public function __construct() {
        $this->db = getDB();
    }

    /**
     * Retrieve all locations
     */
    public function getLocations(): array {
        $stmt = $this->db->query("SELECT * FROM locations ORDER BY location_type ASC, location_name ASC");
        return $stmt->fetchAll();
    }

    /**
     * Retrieve single location by ID
     */
    public function getLocationById(string $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM locations WHERE location_id = ?");
        $stmt->execute([$id]);
        $loc = $stmt->fetch();
        return $loc ?: null;
    }

    /**
     * Retrieve all road segments with endpoint names
     */
    public function getAllRoads(): array {
        $stmt = $this->db->query("
            SELECT r.*, 
                   lf.location_name AS from_name, lf.location_type AS from_type,
                   lt.location_name AS to_name, lt.location_type AS to_type
            FROM roads r
            JOIN locations lf ON r.from_location_id = lf.location_id
            JOIN locations lt ON r.to_location_id = lt.location_id
            ORDER BY r.road_id ASC
        ");
        return $stmt->fetchAll();
    }

    /**
     * Retrieve single road by ID
     */
    public function getRoadById(int $roadId): ?array {
        $stmt = $this->db->prepare("
            SELECT r.*, 
                   lf.location_name AS from_name, lt.location_name AS to_name
            FROM roads r
            JOIN locations lf ON r.from_location_id = lf.location_id
            JOIN locations lt ON r.to_location_id = lt.location_id
            WHERE r.road_id = :id
        ");
        $stmt->execute(['id' => $roadId]);
        $road = $stmt->fetch();
        return $road ?: null;
    }

    /**
     * Update road status (Open or Closed)
     */
    public function updateRoadStatus(int $roadId, string $status): bool {
        if (!in_array($status, ['Open', 'Closed'], true)) {
            throw new InvalidArgumentException("Invalid road status: {$status}");
        }
        $stmt = $this->db->prepare("UPDATE roads SET status = :status WHERE road_id = :id");
        return $stmt->execute(['status' => $status, 'id' => $roadId]);
    }

    /**
     * Build weighted adjacency list for Dijkstra route planning
     * Returns array structure: [ 'LocationA' => [ 'LocationB' => 4, 'LocationC' => 6 ], ... ]
     */
    public function getAdjacencyList(bool $onlyOpen = true): array {
        $query = "SELECT from_location_id, to_location_id, travel_time, is_bidirectional, status FROM roads";
        if ($onlyOpen) {
            $query .= " WHERE status = 'Open'";
        }
        $stmt = $this->db->query($query);
        $roads = $stmt->fetchAll();

        // Also fetch all locations to ensure every node exists in graph even if isolated
        $locs = $this->getLocations();
        $graph = [];
        foreach ($locs as $loc) {
            $graph[$loc['location_id']] = [];
        }

        foreach ($roads as $road) {
            $u = $road['from_location_id'];
            $v = $road['to_location_id'];
            $weight = (int)$road['travel_time'];

            $graph[$u][$v] = $weight;
            if ($road['is_bidirectional']) {
                $graph[$v][$u] = $weight;
            }
        }

        return $graph;
    }

    /**
     * Retrieve road network statistics
     */
    public function getStatistics(): array {
        $stmt = $this->db->query("
            SELECT 
                COUNT(*) AS total,
                SUM(CASE WHEN status = 'Open' THEN 1 ELSE 0 END) AS open,
                SUM(CASE WHEN status = 'Closed' THEN 1 ELSE 0 END) AS closed
            FROM roads
        ");
        $data = $stmt->fetch();

        return [
            'total'  => (int)($data['total'] ?? 0),
            'open'   => (int)($data['open'] ?? 0),
            'closed' => (int)($data['closed'] ?? 0)
        ];
    }
}
