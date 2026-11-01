
import java.util.HashSet;
import java.util.Set;

public class RescueVan extends ResponseUnit {
    private Set<String> equipment;

    public RescueVan(String unitId, String currentLocation,
                     Set<String> equipment) {
        super(unitId, currentLocation);
        this.equipment = new HashSet<>(equipment);
    }

    public boolean supportsRequirement(String requiredEquipment) {
        return equipment.contains(requiredEquipment);
    }

    public void displayEquipment() {
        System.out.println("Rescue Van ID: " + getUnitId());
        System.out.println("Equipment: " + equipment);
    }
}