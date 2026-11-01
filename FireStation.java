
import java.util.ArrayList;
import java.util.List;

public class FireStation {
    private String stationId;
    private String stationName;
    private String location;
    private List<ResponseUnit> units;

    public FireStation(String stationId, String stationName,
                       String location) {
        this.stationId = stationId;
        this.stationName = stationName;
        this.location = location;
        this.units = new ArrayList<>();
    }

    public void addUnit(ResponseUnit unit) {
        units.add(unit);
    }

    public String getStationId() {
        return stationId;
    }

    public String getStationName() {
        return stationName;
    }

    public String getLocation() {
        return location;
    }

    public List<ResponseUnit> getUnits() {
        return units;
    }

    public void displayStation() {
        System.out.println("Station ID: " + stationId);
        System.out.println("Station Name: " + stationName);
        System.out.println("Location: " + location);
        System.out.println("Total Units: " + units.size());
    }
}