
import java.util.*;

public class DispatchService {
    private List<FireStation> stations;
    private RoutePlanner routePlanner;
    private Map<String, DispatchRecord> records;

    public DispatchService(List<FireStation> stations,
                           RoutePlanner routePlanner) {
        this.stations = stations;
        this.routePlanner = routePlanner;
        this.records = new LinkedHashMap<>();
    }

    public boolean dispatchIncident(Incident incident) {
        if (records.containsKey(incident.getIncidentId())) {
            System.out.println("Incident ID already exists.");
            return false;
        }

        ResponseUnit bestUnit = null;
        List<String> bestRoute = new ArrayList<>();
        int bestTime = Integer.MAX_VALUE;

        for (FireStation station : stations) {
            for (ResponseUnit unit : station.getUnits()) {
                if (!unit.isAvailable()) {
                    continue;
                }

                boolean compatible = false;

                if (unit instanceof FireEngine) {
                    compatible = ((FireEngine) unit)
                            .supportsRequirement(
                                    incident.getRequiredEquipment());
                } else if (unit instanceof RescueVan) {
                    compatible = ((RescueVan) unit)
                            .supportsRequirement(
                                    incident.getRequiredEquipment());
                }

                if (!compatible) {
                    continue;
                }

                List<String> route = routePlanner.findFastestRoute(
                        unit.getCurrentLocation(),
                        incident.getLocation());

                if (route.isEmpty()) {
                    continue;
                }

                int time = routePlanner.getTravelTime(route);

                if (time >= 0 && time < bestTime) {
                    bestTime = time;
                    bestUnit = unit;
                    bestRoute = route;
                }
            }
        }

        if (bestUnit == null) {
            System.out.println("Incident "
                    + incident.getIncidentId()
                    + " remains PENDING. No suitable available unit or route.");
            return false;
        }

        bestUnit.assignIncident();

        DispatchRecord record = new DispatchRecord(
                incident.getIncidentId(),
                bestUnit.getUnitId(),
                String.join(" -> ", bestRoute),
                bestTime);

        records.put(incident.getIncidentId(), record);

        System.out.println("\nIncident successfully dispatched!");
        record.displayRecord();

        return true;
    }

    public void completeIncident(String incidentId) {
        DispatchRecord record = records.get(incidentId);

        if (record == null) {
            System.out.println("No dispatch record found.");
            return;
        }

        if (record.getStatus().equals("COMPLETED")) {
            System.out.println("Incident is already completed.");
            return;
        }

        for (FireStation station : stations) {
            for (ResponseUnit unit : station.getUnits()) {
                if (unit.getUnitId().equals(record.getUnitId())) {
                    unit.completeMission();
                    record.completeRecord();
                    System.out.println(
                            "Incident " + incidentId + " completed.");
                    return;
                }
            }
        }
    }

    public void displayRecords() {
        if (records.isEmpty()) {
            System.out.println("No dispatch records available.");
            return;
        }

        for (DispatchRecord record : records.values()) {
            record.displayRecord();
            System.out.println("------------------");
        }
    }
}