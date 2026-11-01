
public class ResponseUnit {
    private String unitId;
    private String currentLocation;
    private boolean available;

    public ResponseUnit(String unitId, String currentLocation) {
        this.unitId = unitId;
        this.currentLocation = currentLocation;
        this.available = true;
    }

    public String getUnitId() {
        return unitId;
    }

    public String getCurrentLocation() {
        return currentLocation;
    }

    public boolean isAvailable() {
        return available;
    }

    public void updateLocation(String newLocation) {
        currentLocation = newLocation;
    }

    public void assignIncident() {
        if (available) {
            available = false;
            System.out.println(unitId + " assigned to incident.");
        } else {
            System.out.println(unitId + " is already busy.");
        }
    }

    public void completeMission() {
        available = true;
        System.out.println(unitId + " mission completed. Unit is available.");
    }
}