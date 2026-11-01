
public class Incident {
    private String incidentId;
    private String incidentType;
    private String location;
    private int intensity;
    private int populationExposureRank;
    private boolean criticalInfrastructure;
    private String requiredEquipment;
    private int arrivalOrder;

    public Incident(String incidentId, String incidentType,
                    String location, int intensity,
                    int populationExposureRank,
                    boolean criticalInfrastructure,
                    String requiredEquipment, int arrivalOrder) {
        this.incidentId = incidentId;
        this.incidentType = incidentType;
        this.location = location;
        this.intensity = intensity;
        this.populationExposureRank = populationExposureRank;
        this.criticalInfrastructure = criticalInfrastructure;
        this.requiredEquipment = requiredEquipment;
        this.arrivalOrder = arrivalOrder;
    }

    public String getIncidentId() {
        return incidentId;
    }

    public String getIncidentType() {
        return incidentType;
    }

    public String getLocation() {
        return location;
    }

    public int getIntensity() {
        return intensity;
    }

    public int getPopulationExposureRank() {
        return populationExposureRank;
    }

    public boolean isCriticalInfrastructure() {
        return criticalInfrastructure;
    }

    public String getRequiredEquipment() {
        return requiredEquipment;
    }

    public int getArrivalOrder() {
        return arrivalOrder;
    }

    public void displayIncident() {
        System.out.println("Incident ID: " + incidentId);
        System.out.println("Type: " + incidentType);
        System.out.println("Location: " + location);
        System.out.println("Intensity: " + intensity);
        System.out.println("Population Exposure Rank: "
                + populationExposureRank);
        System.out.println("Critical Infrastructure: "
                + criticalInfrastructure);
        System.out.println("Required Equipment: "
                + requiredEquipment);
    }
}