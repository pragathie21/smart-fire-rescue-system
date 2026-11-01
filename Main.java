
import java.util.*;

public class Main {

    static Scanner sc = new Scanner(System.in);
    static int arrivalOrder = 1;

    static int readInt(String message) {
        while (true) {
            System.out.print(message);
            try {
                return Integer.parseInt(sc.nextLine().trim());
            } catch (NumberFormatException e) {
                System.out.println("Enter a valid number.");
            }
        }
    }

    static String readText(String message) {
        System.out.print(message);
        return sc.nextLine().trim();
    }

    static boolean readBoolean(String message) {
        while (true) {
            String answer = readText(message).toLowerCase();

            if (answer.equals("true") || answer.equals("yes")) {
                return true;
            }

            if (answer.equals("false") || answer.equals("no")) {
                return false;
            }

            System.out.println("Enter true/false or yes/no.");
        }
    }

    public static void main(String[] args) {

        // 1. Equipment
        Set<String> fireEquipment = new HashSet<>(
                Arrays.asList("WaterPump", "Ladder"));

        Set<String> rescueEquipment = new HashSet<>(
                Arrays.asList("FirstAid", "RescueKit"));

        // 2. Four response units
        FireEngine engine1 =
                new FireEngine("FE01", "StationA", fireEquipment);

        FireEngine engine2 =
                new FireEngine("FE02", "StationB", fireEquipment);

        RescueVan van1 =
                new RescueVan("RV01", "StationA", rescueEquipment);

        RescueVan van2 =
                new RescueVan("RV02", "StationB", rescueEquipment);

        // 3. Two fire stations
        FireStation stationA = new FireStation(
                "FS01", "Central Fire Station", "StationA");

        FireStation stationB = new FireStation(
                "FS02", "North Fire Station", "StationB");

        stationA.addUnit(engine1);
        stationA.addUnit(van1);
        stationB.addUnit(engine2);
        stationB.addUnit(van2);

        List<FireStation> stations = new ArrayList<>();
        stations.add(stationA);
        stations.add(stationB);

        // 4. Road network with 17 locations
        RoadNetwork roads = new RoadNetwork();

        roads.addRoad("StationA", "Road1", 4);
        roads.addRoad("Road1", "Hospital", 3);
        roads.addRoad("StationA", "Road2", 6);
        roads.addRoad("Road2", "Factory", 2);

        roads.addRoad("StationB", "Road3", 3);
        roads.addRoad("Road3", "Hospital", 5);
        roads.addRoad("StationB", "Road4", 4);
        roads.addRoad("Road4", "Factory", 3);

        roads.addRoad("Hospital", "Road5", 2);
        roads.addRoad("Road5", "School", 3);
        roads.addRoad("School", "Road6", 2);
        roads.addRoad("Road6", "Market", 4);

        roads.addRoad("Market", "Road7", 3);
        roads.addRoad("Road7", "Residential", 2);
        roads.addRoad("Residential", "Road8", 3);
        roads.addRoad("Road8", "Bridge", 2);
        roads.addRoad("Bridge", "Park", 3);
        roads.addRoad("Park", "Factory", 4);

        RoutePlanner planner = new RoutePlanner(roads);
        DispatchService dispatchService =
                new DispatchService(stations, planner);

        IncidentManager manager = new IncidentManager();

        // 5. Interactive menu
        while (true) {
            System.out.println("\n==================================");
            System.out.println(" SMART FIRE AND RESCUE SYSTEM");
            System.out.println("==================================");
            System.out.println("1. Report New Incident");
            System.out.println("2. Dispatch Highest-Priority Incident");
            System.out.println("3. Show Waiting Incidents");
            System.out.println("4. Complete Mission");
            System.out.println("5. Close a Road");
            System.out.println("6. Show Dispatch Records");
            System.out.println("7. Show Road Network");
            System.out.println("8. Show Unit Availability");
            System.out.println("0. Exit");

            int choice = readInt("Enter your choice: ");

            switch (choice) {

                case 1: {
                    String id = readText("Incident ID: ");
                    String type = readText("Incident type: ");
                    String location = readText(
                            "Location (example: Hospital): ");

                    if (!roads.getGraph().containsKey(location)) {
                        System.out.println(
                                "Invalid location. Check the road network.");
                        break;
                    }

                    int intensity = readInt("Intensity (1-5): ");
                    int exposure = readInt(
                            "Population exposure rank (1-5): ");

                    if (intensity < 1 || intensity > 5
                            || exposure < 1 || exposure > 5) {
                        System.out.println(
                                "Intensity and exposure must be 1 to 5.");
                        break;
                    }

                    boolean critical = readBoolean(
                            "Critical infrastructure? (true/false): ");

                    String equipment = readText(
                            "Required equipment (WaterPump, Ladder, "
                            + "FirstAid, RescueKit): ");

                    Set<String> validEquipment = new HashSet<>(
                            Arrays.asList(
                                    "WaterPump", "Ladder",
                                    "FirstAid", "RescueKit"));

                    if (!validEquipment.contains(equipment)) {
                        System.out.println("Unknown equipment.");
                        break;
                    }

                    Incident incident = new Incident(
                            id, type, location, intensity, exposure,
                            critical, equipment, arrivalOrder++);

                    manager.addIncident(incident);
                    System.out.println("Incident reported successfully.");
                    break;
                }

                case 2: {
                    if (!manager.hasPendingIncidents()) {
                        System.out.println("No waiting incidents.");
                        break;
                    }

                    Incident incident = manager.getNextIncident();

                    boolean success =
                            dispatchService.dispatchIncident(incident);

                    if (!success) {
                        // Keep the incident pending for another attempt.
                        manager.addIncident(incident);
                    }

                    break;
                }

                case 3:
                    manager.displayWaitingIncidents();
                    break;

                case 4: {
                    String id = readText("Enter incident ID to complete: ");
                    dispatchService.completeIncident(id);
                    break;
                }

                case 5: {
                    String from = readText("Road starts at: ");
                    String to = readText("Road ends at: ");

                    if (roads.getGraph().containsKey(from)
                            && roads.getGraph().containsKey(to)
                            && roads.getGraph().get(from).containsKey(to)) {
                        roads.closeRoad(from, to);
                    } else {
                        System.out.println(
                                "Road not found or already closed.");
                    }

                    break;
                }

                case 6:
                    dispatchService.displayRecords();
                    break;

                case 7:
                    roads.displayRoads();
                    break;

                case 8:
                    System.out.println("\nUNIT STATUS");

                    for (FireStation station : stations) {
                        System.out.println(
                                "\n" + station.getStationName());

                        for (ResponseUnit unit : station.getUnits()) {
                            System.out.println(
                                    unit.getUnitId() + " at "
                                    + unit.getCurrentLocation()
                                    + " | Available: "
                                    + unit.isAvailable());
                        }
                    }

                    break;

                case 0:
                    System.out.println("System closed.");
                    sc.close();
                    return;

                default:
                    System.out.println("Invalid choice. Try again.");
            }
        }
    }
}