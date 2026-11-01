
import java.util.*;

public class RoutePlanner {

    private RoadNetwork roadNetwork;

    public RoutePlanner(RoadNetwork roadNetwork) {
        this.roadNetwork = roadNetwork;
    }

    public List<String> findFastestRoute(
            String start, String destination) {

        Map<String, Map<String, Integer>> graph =
                roadNetwork.getGraph();

        Map<String, Integer> distance = new HashMap<>();
        Map<String, String> previous = new HashMap<>();

        // PriorityQueue stores [travelTime, location]
        PriorityQueue<int[]> pq = new PriorityQueue<>(
                Comparator.comparingInt(a -> a[0]));

        Map<Integer, String> nodeNames = new HashMap<>();
        Map<String, Integer> nodeIds = new HashMap<>();

        int id = 0;

        for (String node : graph.keySet()) {
            nodeNames.put(id, node);
            nodeIds.put(node, id);
            distance.put(node, Integer.MAX_VALUE);
            id++;
        }

        if (!graph.containsKey(start)
                || !graph.containsKey(destination)) {
            return Collections.emptyList();
        }

        distance.put(start, 0);
        pq.offer(new int[]{0, nodeIds.get(start)});

        while (!pq.isEmpty()) {

            int[] currentEntry = pq.poll();

            int currentDistance = currentEntry[0];
            String current = nodeNames.get(currentEntry[1]);

            // Ignore outdated queue entries
            if (currentDistance != distance.get(current)) {
                continue;
            }

            if (current.equals(destination)) {
                break;
            }

            for (Map.Entry<String, Integer> road :
                    graph.get(current).entrySet()) {

                String next = road.getKey();
                int travelTime = road.getValue();

                int newDistance = currentDistance + travelTime;

                if (newDistance < distance.get(next)) {
                    distance.put(next, newDistance);
                    previous.put(next, current);

                    pq.offer(new int[]{
                        newDistance, nodeIds.get(next)
                    });
                }
            }
        }

        if (distance.get(destination) == Integer.MAX_VALUE) {
            return Collections.emptyList();
        }

        List<String> route = new ArrayList<>();
        String current = destination;

        while (current != null) {
            route.add(current);
            current = previous.get(current);
        }

        Collections.reverse(route);
        return route;
    }

    public int getTravelTime(List<String> route) {
        if (route == null || route.isEmpty() || route.size() < 2) {
            return -1;
        }

        Map<String, Map<String, Integer>> graph =
                roadNetwork.getGraph();

        int totalTime = 0;

        for (int i = 0; i < route.size() - 1; i++) {
            String from = route.get(i);
            String to = route.get(i + 1);

            if (!graph.containsKey(from) || !graph.get(from).containsKey(to)) {
                return -1;
            }

            Integer time = graph.get(from).get(to);

            if (time == null) {
                return -1;
            }

            totalTime += time;
        }

        return totalTime;
    }
}