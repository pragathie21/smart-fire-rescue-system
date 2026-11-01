import java.util.*;

/**
 * Smart Fire and Rescue Response System
 * Sudar Road Network Weighted Adjacency Graph
 */
public class RoadNetwork {

    private Map<String, Map<String, Integer>> graph;
    private Map<String, Integer> masterRoadRegistry;

    public RoadNetwork() {
        graph = new HashMap<>();
        masterRoadRegistry = new HashMap<>();
    }

    public void addLocation(String location) {
        graph.putIfAbsent(location, new HashMap<>());
    }

    public void addRoad(String from, String to, int travelTime) {
        addLocation(from);
        addLocation(to);

        graph.get(from).put(to, travelTime);
        graph.get(to).put(from, travelTime);

        String key = getRoadKey(from, to);
        masterRoadRegistry.put(key, travelTime);
    }

    public void closeRoad(String from, String to) {
        if (graph.containsKey(from)) {
            graph.get(from).remove(to);
        }

        if (graph.containsKey(to)) {
            graph.get(to).remove(from);
        }
    }

    public void reopenRoad(String from, String to, int travelTime) {
        addRoad(from, to, travelTime);
    }

    public void reopenRoad(String from, String to) {
        String key = getRoadKey(from, to);
        int time = masterRoadRegistry.getOrDefault(key, 5);
        addRoad(from, to, time);
    }

    public boolean isRoadOpen(String from, String to) {
        return graph.containsKey(from) && graph.get(from).containsKey(to);
    }

    public Map<String, Map<String, Integer>> getGraph() {
        return graph;
    }

    private String getRoadKey(String a, String b) {
        return a.compareTo(b) < 0 ? a + "<->" + b : b + "<->" + a;
    }

    public void displayRoads() {
        for (String location : graph.keySet()) {
            System.out.println(location + " -> " + graph.get(location));
        }
    }
}