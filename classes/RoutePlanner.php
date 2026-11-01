<?php
/**
 * Smart Fire and Rescue Response System
 * RoutePlanner - Dijkstra's Shortest Path Algorithm Implementation
 * 
 * Algorithm: Dijkstra's Algorithm
 * Time Complexity: O(V^2 + E) using array-based min-distance extraction, 
 * or O((V + E) log V) with SplPriorityQueue.
 * Graph: Weighted, undirected graph where weights represent travel time in minutes.
 */

require_once __DIR__ . '/RoadNetwork.php';

class RoutePlanner {
    private RoadNetwork $roadNetwork;

    public function __construct(?RoadNetwork $network = null) {
        $this->roadNetwork = $network ?? new RoadNetwork();
    }

    /**
     * Compute shortest path between start location and end location using Dijkstra's algorithm.
     * Only open roads are considered.
     * 
     * @param string $startLocationId Starting location node (e.g. 'StationA')
     * @param string $targetLocationId Destination location node (e.g. 'Hospital')
     * @return array Result array with status, total distance (minutes), node path, and error if unreachable.
     */
    public function findShortestRoute(string $startLocationId, string $targetLocationId): array {
        // If start and target are the same
        if ($startLocationId === $targetLocationId) {
            return [
                'success'     => true,
                'distance'    => 0,
                'path'        => [$startLocationId],
                'path_string' => $startLocationId,
                'segments'    => [],
                'error'       => null
            ];
        }

        // Retrieve dynamic graph of currently open roads from MySQL
        $graph = $this->roadNetwork->getAdjacencyList(true);

        if (!isset($graph[$startLocationId])) {
            return [
                'success'  => false,
                'distance' => INF,
                'path'     => [],
                'path_string' => '',
                'segments' => [],
                'error'    => "Origin location '{$startLocationId}' does not exist in the road network."
            ];
        }

        if (!isset($graph[$targetLocationId])) {
            return [
                'success'  => false,
                'distance' => INF,
                'path'     => [],
                'path_string' => '',
                'segments' => [],
                'error'    => "Destination location '{$targetLocationId}' does not exist in the road network."
            ];
        }

        // Initialize distances, predecessor map, and unvisited set
        $distances = [];
        $previous  = [];
        $unvisited = [];

        foreach ($graph as $node => $edges) {
            $distances[$node] = INF;
            $previous[$node]  = null;
            $unvisited[$node] = true;
        }

        $distances[$startLocationId] = 0;

        while (!empty($unvisited)) {
            // Find unvisited node with smallest tentative distance
            $minNode = null;
            $minDist = INF;

            foreach ($unvisited as $node => $_) {
                if ($distances[$node] < $minDist) {
                    $minDist = $distances[$node];
                    $minNode = $node;
                }
            }

            // If the smallest distance is INF, remaining nodes are unreachable
            if ($minNode === null || $minDist === INF) {
                break;
            }

            // Early exit if we reached the target node
            if ($minNode === $targetLocationId) {
                break;
            }

            unset($unvisited[$minNode]);

            // Update distances to neighboring nodes
            foreach ($graph[$minNode] as $neighbor => $weight) {
                if (isset($unvisited[$neighbor])) {
                    $alt = $distances[$minNode] + $weight;
                    if ($alt < $distances[$neighbor]) {
                        $distances[$neighbor] = $alt;
                        $previous[$neighbor]  = $minNode;
                    }
                }
            }
        }

        // If target distance is still INF, no open route exists
        if ($distances[$targetLocationId] === INF) {
            return [
                'success'     => false,
                'distance'    => INF,
                'path'        => [],
                'path_string' => 'No path available',
                'segments'    => [],
                'error'       => "No open route exists between '{$startLocationId}' and '{$targetLocationId}'. All connecting paths may be blocked by road closures."
            ];
        }

        // Reconstruct path from target to start
        $path = [];
        $curr = $targetLocationId;
        while ($curr !== null) {
            array_unshift($path, $curr);
            $curr = $previous[$curr];
        }

        // Build turn-by-turn segments with individual road weights
        $segments = [];
        for ($i = 0; $i < count($path) - 1; $i++) {
            $from = $path[$i];
            $to   = $path[$i + 1];
            $time = $graph[$from][$to] ?? 0;
            $segments[] = [
                'from' => $from,
                'to'   => $to,
                'time' => $time
            ];
        }

        return [
            'success'     => true,
            'distance'    => (int)$distances[$targetLocationId],
            'path'        => $path,
            'path_string' => implode(' ➔ ', $path),
            'segments'    => $segments,
            'error'       => null
        ];
    }
}
