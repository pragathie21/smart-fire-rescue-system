import java.util.*;

/**
 * Smart Fire and Rescue Response System
 * Automated Dijkstra Routing Test Cases (Requirement 7)
 *
 * Demonstrates:
 * 1. Normal shortest route (StationA to Hospital)
 * 2. Alternate route after road closure (Road1-Hospital closed)
 * 3. Unreachable destination (all paths blocked)
 */
public class RoutePlannerTest {

    public static RoadNetwork createSudarRoadNetwork() {
        RoadNetwork roads = new RoadNetwork();

        roads.addRoad("StationA", "Road1", 4);
        roads.addRoad("StationA", "Road2", 6);
        roads.addRoad("Road1", "Hospital", 3);
        roads.addRoad("Road2", "Factory", 2);
        roads.addRoad("Factory", "Road4", 3);
        roads.addRoad("Road4", "StationB", 4);
        roads.addRoad("StationB", "Road3", 3);
        roads.addRoad("Road3", "Hospital", 5);
        roads.addRoad("Road1", "Market", 4);
        roads.addRoad("Market", "School", 3);
        roads.addRoad("Road2", "ResidentialArea", 5);
        roads.addRoad("ResidentialArea", "School", 4);
        roads.addRoad("Factory", "IndustrialArea", 4);
        roads.addRoad("Road4", "TechPark", 3);
        roads.addRoad("Road3", "RiverBridge", 4);
        roads.addRoad("RiverBridge", "TechPark", 5);
        roads.addRoad("IndustrialArea", "RiverBridge", 6);
        roads.addRoad("Market", "ResidentialArea", 3);
        roads.addRoad("Road1", "Road2", 4);
        roads.addRoad("Hospital", "TechPark", 6);

        return roads;
    }

    public static void main(String[] args) {
        System.out.println("=======================================================");
        System.out.println("JAVA DIJKSTRA SHORTEST-ROUTE TEST SUITE (SUDAR TOWN)");
        System.out.println("Weights represent simulated estimated travel times in minutes");
        System.out.println("=======================================================\n");

        RoadNetwork network = createSudarRoadNetwork();
        RoutePlanner planner = new RoutePlanner(network);

        // -----------------------------------------------------------------
        // TEST CASE 1: Normal Shortest Route from StationA to Hospital
        // Expected: StationA -> Road1 -> Hospital (4 + 3 = 7 minutes)
        // -----------------------------------------------------------------
        System.out.println("--- TEST CASE 1: Normal Shortest Route ---");
        List<String> route1 = planner.findFastestRoute("StationA", "Hospital");
        int time1 = planner.getTravelTime(route1);
        System.out.println("Origin: StationA | Destination: Hospital");
        System.out.println("Calculated Route: " + String.join(" ➔ ", route1));
        System.out.println("Estimated Travel Time: " + time1 + " minutes");
        boolean pass1 = time1 == 7 && route1.size() == 3 && route1.get(1).equals("Road1");
        System.out.println("Result: " + (pass1 ? "✅ PASS" : "❌ FAIL") + "\n");

        // -----------------------------------------------------------------
        // TEST CASE 2: Alternate Route After Road Closure
        // Close primary road: Road1 <-> Hospital
        // Shortest alternate route must be automatically recalculated
        // -----------------------------------------------------------------
        System.out.println("--- TEST CASE 2: Road Closure & Alternate Recalculation ---");
        System.out.println("Simulating road closure: Road1 <-> Hospital (due to fallen tree/construction)");
        network.closeRoad("Road1", "Hospital");

        List<String> route2 = planner.findFastestRoute("StationA", "Hospital");
        int time2 = planner.getTravelTime(route2);
        System.out.println("Recalculated Route: " + String.join(" ➔ ", route2));
        System.out.println("New Travel Time: " + time2 + " minutes");
        boolean pass2 = time2 > time1 && !route2.contains("Road1 ➔ Hospital") && route2.get(route2.size() - 1).equals("Hospital");
        System.out.println("Avoids Closed Road: " + (!route2.contains("Road1") || !route2.get(route2.indexOf("Road1") + 1).equals("Hospital")));
        System.out.println("Result: " + (pass2 ? "✅ PASS" : "❌ FAIL") + "\n");

        // -----------------------------------------------------------------
        // TEST CASE 3: Unreachable Destination Detection
        // Close all connecting roads to an isolated destination
        // -----------------------------------------------------------------
        System.out.println("--- TEST CASE 3: Unreachable Destination Detection ---");
        System.out.println("Isolating Hospital completely by closing Road3 <-> Hospital and Hospital <-> TechPark...");
        network.closeRoad("Road3", "Hospital");
        network.closeRoad("Hospital", "TechPark");

        List<String> route3 = planner.findFastestRoute("StationA", "Hospital");
        int time3 = planner.getTravelTime(route3);
        System.out.println("Route when blocked: " + (route3.isEmpty() ? "[NO VALID ROUTE FOUND]" : String.join(" ➔ ", route3)));
        System.out.println("Travel Time returned: " + time3);
        boolean pass3 = route3.isEmpty() && time3 == -1;
        System.out.println("Unreachable Detected Gracefully: " + pass3);
        System.out.println("Result: " + (pass3 ? "✅ PASS" : "❌ FAIL") + "\n");

        // -----------------------------------------------------------------
        // Reopen road test
        // -----------------------------------------------------------------
        System.out.println("--- TEST CASE 4: Reopening Road ---");
        System.out.println("Reopening Road1 <-> Hospital (travel time 3 mins)...");
        network.reopenRoad("Road1", "Hospital", 3);
        List<String> route4 = planner.findFastestRoute("StationA", "Hospital");
        int time4 = planner.getTravelTime(route4);
        System.out.println("Restored Route: " + String.join(" ➔ ", route4));
        System.out.println("Restored Travel Time: " + time4 + " minutes");
        boolean pass4 = time4 == 7;
        System.out.println("Result: " + (pass4 ? "✅ PASS" : "❌ FAIL") + "\n");

        System.out.println("=======================================================");
        if (pass1 && pass2 && pass3 && pass4) {
            System.out.println("🎉 ALL 4 JAVA ROUTING TESTS PASSED PERFECTLY!");
        } else {
            System.out.println("❌ ONE OR MORE TESTS FAILED.");
        }
        System.out.println("=======================================================");
    }
}
