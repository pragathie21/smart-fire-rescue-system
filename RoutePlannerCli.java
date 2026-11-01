import java.util.*;

/**
 * Smart Fire and Rescue Response System
 * CLI Interface for Java Dijkstra Routing
 *
 * Usage:
 *   java RoutePlannerCli <origin> <destination> [closedRoadFrom1,closedRoadTo1 ...]
 * Example:
 *   java RoutePlannerCli StationA Hospital
 */
public class RoutePlannerCli {

    public static void main(String[] args) {
        if (args.length < 2) {
            System.err.println("Usage: java RoutePlannerCli <start> <destination> [closedFrom:closedTo ...]");
            System.exit(1);
        }

        String start = args[0];
        String destination = args[1];

        RoadNetwork network = RoutePlannerTest.createSudarRoadNetwork();

        // Process any closed road arguments
        for (int i = 2; i < args.length; i++) {
            String[] parts = args[i].split(":");
            if (parts.length == 2) {
                network.closeRoad(parts[0], parts[1]);
            }
        }

        RoutePlanner planner = new RoutePlanner(network);
        List<String> route = planner.findFastestRoute(start, destination);
        int travelTime = planner.getTravelTime(route);

        if (route.isEmpty() || travelTime == -1) {
            System.out.println("{\"success\":false,\"error\":\"Destination unreachable. Path is blocked.\"}");
        } else {
            StringBuilder sb = new StringBuilder();
            sb.append("{\"success\":true,\"start\":\"").append(start)
              .append("\",\"destination\":\"").append(destination)
              .append("\",\"travel_time\":").append(travelTime)
              .append(",\"path\":[");
            for (int i = 0; i < route.size(); i++) {
                sb.append("\"").append(route.get(i)).append("\"");
                if (i < route.size() - 1) sb.append(",");
            }
            sb.append("],\"route_string\":\"").append(String.join(" ➔ ", route)).append("\"}");
            System.out.println(sb.toString());
        }
    }
}
