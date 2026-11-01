
public class DispatchRecord {
    private String incidentId;
    private String unitId;
    private String route;
    private int travelTime;
    private String status;

    public DispatchRecord(String incidentId, String unitId,
                          String route, int travelTime) {
        this.incidentId = incidentId;
        this.unitId = unitId;
        this.route = route;
        this.travelTime = travelTime;
        this.status = "ASSIGNED";
    }

    public String getIncidentId() {
        return incidentId;
    }

    public String getUnitId() {
        return unitId;
    }

    public String getRoute() {
        return route;
    }

    public int getTravelTime() {
        return travelTime;
    }

    public String getStatus() {
        return status;
    }

    public void completeRecord() {
        status = "COMPLETED";
    }

    public void displayRecord() {
        System.out.println("Incident ID: " + incidentId);
        System.out.println("Assigned Unit: " + unitId);
        System.out.println("Route: " + route);
        System.out.println("Travel Time: " + travelTime + " minutes");
        System.out.println("Status: " + status);
    }
}